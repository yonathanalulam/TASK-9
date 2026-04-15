<?php

declare(strict_types=1);

namespace App\Service\Scraping;

use App\Entity\Scraping\ScrapeRun;
use App\Entity\Scraping\ScrapeRunItem;
use App\Entity\Scraping\SourceDefinition;
use App\Repository\Scraping\SourceDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Orchestrates a full scraping cycle across all active sources.
 */
class ScrapeOrchestratorService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SourceDefinitionRepository $sourceRepo,
        private readonly RateLimiterService $rateLimiter,
        private readonly HttpScrapeClient $httpClient,
        private readonly ContentExtractorService $extractor,
        private readonly ProxyRotationService $proxyRotation,
        private readonly SelfHealingService $selfHealing,
        private readonly JitterService $jitter,
    ) {
    }

    /**
     * Run scraping for all ACTIVE sources.
     *
     * @return list<ScrapeRun>
     */
    public function runAll(): array
    {
        $sources = $this->sourceRepo->findBy(['status' => 'ACTIVE']);
        $runs = [];

        foreach ($sources as $source) {
            $runs[] = $this->runForSource($source);
        }

        return $runs;
    }

    /**
     * Run scraping for a single source.
     */
    public function runForSource(SourceDefinition $source): ScrapeRun
    {
        $run = new ScrapeRun();
        $run->setSourceDefinition($source);
        $run->setStatus('RUNNING');
        $run->setStartedAt(new \DateTimeImmutable());

        $proxy = $this->proxyRotation->getNextProxy();
        if ($proxy !== null) {
            $run->setProxyPool($proxy);
        }

        $this->em->persist($run);
        $this->em->flush();

        try {
            $config = $source->getConfig();
            $urls = $config['urls'] ?? [$source->getBaseUrl()];
            $selectors = $config['selectors'] ?? [];

            $found = 0;
            $newItems = 0;
            $failed = 0;

            foreach ($urls as $url) {
                // Rate-limit check
                if (!$this->rateLimiter->checkAndIncrement($source)) {
                    $this->selfHealing->evaluate($source, 'RATE_LIMITED');
                    break;
                }

                // Apply jitter
                usleep($this->jitter->getDelay() * 1000);

                $proxyUrl = $proxy?->getProxyUrl();

                try {
                    $response = $this->httpClient->fetch($url, $proxyUrl);
                } catch (\Throwable $e) {
                    $item = new ScrapeRunItem();
                    $item->setScrapeRun($run);
                    $item->setSourceUrl($url);
                    $item->setStatus('FAILED');
                    $item->setErrorDetail($e->getMessage());
                    $this->em->persist($item);
                    $failed++;
                    continue;
                }

                $found++;

                // Detect adverse events
                if ($response['status'] === 403 || $response['status'] === 429) {
                    $eventType = $response['status'] === 429 ? 'RATE_LIMITED' : 'BAN_DETECTED';
                    $this->selfHealing->evaluate($source, $eventType);

                    $item = new ScrapeRunItem();
                    $item->setScrapeRun($run);
                    $item->setSourceUrl($url);
                    $item->setStatus('FAILED');
                    $item->setErrorDetail(sprintf('HTTP %d detected', $response['status']));
                    $this->em->persist($item);
                    $failed++;
                    continue;
                }

                if ($response['captcha_detected'] ?? false) {
                    $this->selfHealing->evaluate($source, 'CAPTCHA_DETECTED');

                    $item = new ScrapeRunItem();
                    $item->setScrapeRun($run);
                    $item->setSourceUrl($url);
                    $item->setStatus('FAILED');
                    $item->setErrorDetail('CAPTCHA detected');
                    $this->em->persist($item);
                    $failed++;
                    continue;
                }

                $item = new ScrapeRunItem();
                $item->setScrapeRun($run);
                $item->setSourceUrl($url);
                $item->setRawContent($response['body']);
                $item->setStatus('SCRAPED');

                // Extract data if selectors configured
                if (count($selectors) > 0 && $source->getScrapeType() === 'HTML') {
                    try {
                        $extracted = $this->extractor->extract($response['body'], $selectors);
                        $item->setExtractedData($extracted);
                        $item->setStatus('PARSED');
                    } catch (\Throwable $e) {
                        $item->setErrorDetail('Parse error: ' . $e->getMessage());
                        $item->setStatus('FAILED');
                        $failed++;
                        $this->em->persist($item);
                        continue;
                    }
                }

                $this->em->persist($item);
                $newItems++;
            }

            $run->setItemsFound($found);
            $run->setItemsNew($newItems);
            $run->setItemsFailed($failed);
            $run->setStatus($failed > 0 && $newItems > 0 ? 'PARTIAL' : ($failed > 0 ? 'FAILED' : 'SUCCEEDED'));

            // Update last successful scrape timestamp if any items succeeded
            if ($newItems > 0) {
                $source->setLastSuccessfulScrapeAt(new \DateTimeImmutable());
            }
        } catch (\Throwable $e) {
            $run->setStatus('FAILED');
            $run->setErrorDetail($e->getMessage());
        }

        $run->setCompletedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $run;
    }
}
