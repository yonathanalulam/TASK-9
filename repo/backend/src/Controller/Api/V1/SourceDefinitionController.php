<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Dto\Response\PaginatedEnvelope;
use App\Entity\Scraping\SourceDefinition;
use App\Repository\Scraping\SourceDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Security\Permission;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/sources')]
class SourceDefinitionController extends AbstractController
{
    public function __construct(
        private readonly SourceDefinitionRepository $sourceRepo,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'api_sources_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::SCRAPING_MANAGE);

        $body = json_decode($request->getContent(), true);

        if (!is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        $required = ['name', 'base_url', 'scrape_type'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                return new JsonResponse(
                    ErrorEnvelope::create('VALIDATION_ERROR', sprintf('Field "%s" is required.', $field)),
                    422,
                );
            }
        }

        if (!in_array($body['scrape_type'], ['HTML', 'API', 'RSS'], true)) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'scrape_type must be HTML, API, or RSS.'),
                422,
            );
        }

        $source = new SourceDefinition();
        $source->setName($body['name']);
        $source->setBaseUrl($body['base_url']);
        $source->setScrapeType($body['scrape_type']);
        $source->setConfig($body['config'] ?? []);

        if (isset($body['max_requests_per_minute'])) {
            $rpm = (int) $body['max_requests_per_minute'];
            if ($rpm < 1 || $rpm > 30) {
                return new JsonResponse(
                    ErrorEnvelope::create('VALIDATION_ERROR', 'max_requests_per_minute must be between 1 and 30.'),
                    422,
                );
            }
            $source->setMaxRequestsPerMinute($rpm);
        }

        $this->em->persist($source);
        $this->em->flush();

        return new JsonResponse(ApiEnvelope::wrap($this->serializeSource($source)), 201);
    }

    #[Route('', name: 'api_sources_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::SCRAPING_VIEW);

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', '25')));

        $qb = $this->sourceRepo->createQueryBuilder('s')
            ->orderBy('s.createdAt', 'DESC');

        $status = $request->query->get('status');
        if ($status !== null) {
            $qb->andWhere('s.status = :status')->setParameter('status', $status);
        }

        $total = (int) (clone $qb)->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $data = array_map(fn (SourceDefinition $s) => $this->serializeSource($s), $items);

        return new JsonResponse(PaginatedEnvelope::wrap($data, $page, $perPage, $total));
    }

    #[Route('/{id}', name: 'api_sources_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::SCRAPING_VIEW);

        $source = $this->sourceRepo->find($id);

        if ($source === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Source definition not found.'),
                404,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeSource($source)));
    }

    #[Route('/{id}', name: 'api_sources_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::SCRAPING_MANAGE);

        $source = $this->sourceRepo->find($id);

        if ($source === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Source definition not found.'),
                404,
            );
        }

        $body = json_decode($request->getContent(), true);

        if (!is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        if (isset($body['name'])) {
            $source->setName($body['name']);
        }
        if (isset($body['base_url'])) {
            $source->setBaseUrl($body['base_url']);
        }
        if (isset($body['scrape_type'])) {
            $source->setScrapeType($body['scrape_type']);
        }
        if (isset($body['config'])) {
            $source->setConfig($body['config']);
        }
        if (isset($body['max_requests_per_minute'])) {
            $rpm = (int) $body['max_requests_per_minute'];
            if ($rpm < 1 || $rpm > 30) {
                return new JsonResponse(
                    ErrorEnvelope::create('VALIDATION_ERROR', 'max_requests_per_minute must be between 1 and 30.'),
                    422,
                );
            }
            $source->setMaxRequestsPerMinute($rpm);
        }

        $source->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return new JsonResponse(ApiEnvelope::wrap($this->serializeSource($source)));
    }

    #[Route('/{id}/pause', name: 'api_sources_pause', methods: ['POST'])]
    public function pause(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::SCRAPING_MANAGE);

        $source = $this->sourceRepo->find($id);

        if ($source === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Source definition not found.'),
                404,
            );
        }

        $body = json_decode($request->getContent(), true);
        $reason = $body['reason'] ?? 'Manual pause';

        $source->setStatus('PAUSED');
        $source->setPauseReason($reason);
        $source->setPausedUntil(new \DateTimeImmutable('+24 hours'));
        $source->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return new JsonResponse(ApiEnvelope::wrap($this->serializeSource($source)));
    }

    #[Route('/{id}/resume', name: 'api_sources_resume', methods: ['POST'])]
    public function resume(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::SCRAPING_MANAGE);

        $source = $this->sourceRepo->find($id);

        if ($source === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Source definition not found.'),
                404,
            );
        }

        $source->setStatus('ACTIVE');
        $source->setPausedUntil(null);
        $source->setPauseReason(null);
        $source->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return new JsonResponse(ApiEnvelope::wrap($this->serializeSource($source)));
    }

    #[Route('/{id}/disable', name: 'api_sources_disable', methods: ['POST'])]
    public function disable(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::SCRAPING_MANAGE);

        $source = $this->sourceRepo->find($id);

        if ($source === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Source definition not found.'),
                404,
            );
        }

        $source->setStatus('DISABLED');
        $source->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return new JsonResponse(ApiEnvelope::wrap($this->serializeSource($source)));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSource(SourceDefinition $source): array
    {
        return [
            'id' => $source->getId()->toRfc4122(),
            'name' => $source->getName(),
            'base_url' => $source->getBaseUrl(),
            'scrape_type' => $source->getScrapeType(),
            'config' => $source->getConfig(),
            'status' => $source->getStatus(),
            'max_requests_per_minute' => $source->getMaxRequestsPerMinute(),
            'paused_until' => $source->getPausedUntil()?->format('c'),
            'pause_reason' => $source->getPauseReason(),
            'last_successful_scrape_at' => $source->getLastSuccessfulScrapeAt()?->format('c'),
            'created_at' => $source->getCreatedAt()->format('c'),
            'updated_at' => $source->getUpdatedAt()->format('c'),
        ];
    }
}
