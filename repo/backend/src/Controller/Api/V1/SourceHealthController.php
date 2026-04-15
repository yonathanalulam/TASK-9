<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Entity\Scraping\SourceHealthEvent;
use App\Repository\Scraping\SourceDefinitionRepository;
use App\Repository\Scraping\SourceHealthEventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Security\Permission;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/sources')]
class SourceHealthController extends AbstractController
{
    public function __construct(
        private readonly SourceDefinitionRepository $sourceRepo,
        private readonly SourceHealthEventRepository $healthRepo,
    ) {
    }

    #[Route('/{id}/health', name: 'api_sources_health_timeline', methods: ['GET'])]
    public function healthTimeline(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::SCRAPING_VIEW);

        $source = $this->sourceRepo->find($id);

        if ($source === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Source definition not found.'),
                404,
            );
        }

        $limit = min(500, max(1, (int) $request->query->get('limit', '100')));

        $events = $this->healthRepo->createQueryBuilder('e')
            ->where('e.sourceDefinition = :source')
            ->setParameter('source', $source)
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(fn (SourceHealthEvent $e) => $this->serializeEvent($e), $events);

        return new JsonResponse(ApiEnvelope::wrap($data));
    }

    #[Route('/health/dashboard', name: 'api_sources_health_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::SCRAPING_VIEW);

        $sources = $this->sourceRepo->findAll();

        $dashboard = [];

        foreach ($sources as $source) {
            $recentEvents = $this->healthRepo->createQueryBuilder('e')
                ->where('e.sourceDefinition = :source')
                ->setParameter('source', $source)
                ->orderBy('e.createdAt', 'DESC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            $dashboard[] = [
                'source_id' => $source->getId()->toRfc4122(),
                'source_name' => $source->getName(),
                'status' => $source->getStatus(),
                'last_successful_scrape_at' => $source->getLastSuccessfulScrapeAt()?->format('c'),
                'paused_until' => $source->getPausedUntil()?->format('c'),
                'pause_reason' => $source->getPauseReason(),
                'recent_events' => array_map(
                    fn (SourceHealthEvent $e) => $this->serializeEvent($e),
                    $recentEvents,
                ),
            ];
        }

        return new JsonResponse(ApiEnvelope::wrap($dashboard));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEvent(SourceHealthEvent $event): array
    {
        return [
            'id' => $event->getId()->toRfc4122(),
            'event_type' => $event->getEventType(),
            'detail' => $event->getDetail(),
            'proxy_pool_id' => $event->getProxyPool()?->getId()->toRfc4122(),
            'created_at' => $event->getCreatedAt()->format('c'),
        ];
    }
}
