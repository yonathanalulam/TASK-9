<?php

declare(strict_types=1);

namespace App\Service\Scraping;

use App\Entity\Scraping\SourceDefinition;
use App\Entity\Scraping\SourceHealthEvent;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Implements the degradation chain for source health management.
 *
 * Chain: (1) degrade to metadata-only -> (2) switch proxy -> (3) pause 60 min -> (4) disable.
 */
class SelfHealingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProxyRotationService $proxyRotation,
    ) {
    }

    /**
     * Evaluate a source after an adverse event and apply the appropriate
     * remediation step in the degradation chain.
     */
    public function evaluate(SourceDefinition $source, string $eventType): void
    {
        $currentStatus = $source->getStatus();

        match ($currentStatus) {
            'ACTIVE' => $this->degradeToMetadataOnly($source, $eventType),
            'DEGRADED' => $this->switchProxy($source, $eventType),
            'PAUSED' => $this->disable($source, $eventType),
            default => null, // DISABLED — no further action
        };

        $this->em->flush();
    }

    private function degradeToMetadataOnly(SourceDefinition $source, string $eventType): void
    {
        $source->setStatus('DEGRADED');
        $source->setUpdatedAt(new \DateTimeImmutable());

        $this->recordEvent($source, 'DEGRADED', sprintf(
            'Source degraded to metadata-only after %s event.',
            $eventType,
        ));
    }

    private function switchProxy(SourceDefinition $source, string $eventType): void
    {
        $newProxy = $this->proxyRotation->getNextProxy();

        if ($newProxy !== null) {
            $this->recordEvent($source, 'PROXY_SWITCHED', sprintf(
                'Proxy switched to %s after %s event.',
                $newProxy->getProxyUrl(),
                $eventType,
            ), $newProxy);

            // Stay degraded but with new proxy — give it another chance
            // If this also fails the next call will trigger pause
            $source->setStatus('DEGRADED');
        } else {
            // No proxies available — escalate to pause
            $this->pause($source, $eventType);
        }

        $source->setUpdatedAt(new \DateTimeImmutable());
    }

    private function pause(SourceDefinition $source, string $eventType): void
    {
        $source->setStatus('PAUSED');
        $source->setPausedUntil(new \DateTimeImmutable('+60 minutes'));
        $source->setPauseReason(sprintf('Auto-paused after %s event — no proxies available.', $eventType));
        $source->setUpdatedAt(new \DateTimeImmutable());

        $this->recordEvent($source, 'PAUSED', sprintf(
            'Source paused for 60 minutes after %s event.',
            $eventType,
        ));
    }

    private function disable(SourceDefinition $source, string $eventType): void
    {
        $source->setStatus('DISABLED');
        $source->setUpdatedAt(new \DateTimeImmutable());

        $this->recordEvent($source, 'ERROR', sprintf(
            'Source disabled after %s event while already paused.',
            $eventType,
        ));
    }

    private function recordEvent(
        SourceDefinition $source,
        string $recordedEventType,
        string $detail,
        ?\App\Entity\Scraping\ProxyPool $proxy = null,
    ): void {
        $event = new SourceHealthEvent();
        $event->setSourceDefinition($source);
        $event->setEventType($recordedEventType);
        $event->setDetail($detail);

        if ($proxy !== null) {
            $event->setProxyPool($proxy);
        }

        $this->em->persist($event);
    }
}
