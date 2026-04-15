<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Dto\Response\PaginatedEnvelope;
use App\Entity\Scraping\ScrapeRun;
use App\Entity\Scraping\ScrapeRunItem;
use App\Repository\Scraping\ScrapeRunRepository;
use App\Repository\Scraping\SourceDefinitionRepository;
use App\Service\Scraping\ScrapeOrchestratorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Security\Permission;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/scrape-runs')]
class ScrapeRunController extends AbstractController
{
    public function __construct(
        private readonly ScrapeRunRepository $runRepo,
        private readonly SourceDefinitionRepository $sourceRepo,
        private readonly ScrapeOrchestratorService $orchestrator,
    ) {
    }

    #[Route('', name: 'api_scrape_runs_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::SCRAPING_VIEW);

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', '25')));

        $qb = $this->runRepo->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC');

        $sourceId = $request->query->get('source_id');
        if ($sourceId !== null) {
            $qb->andWhere('r.sourceDefinition = :sid')->setParameter('sid', $sourceId);
        }

        $total = (int) (clone $qb)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $data = array_map(fn (ScrapeRun $r) => $this->serializeRun($r), $items);

        return new JsonResponse(PaginatedEnvelope::wrap($data, $page, $perPage, $total));
    }

    #[Route('/{id}', name: 'api_scrape_runs_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::SCRAPING_VIEW);

        $run = $this->runRepo->find($id);

        if ($run === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Scrape run not found.'),
                404,
            );
        }

        $data = $this->serializeRun($run);
        $data['items'] = array_map(
            fn (ScrapeRunItem $item) => $this->serializeItem($item),
            $run->getItems()->toArray(),
        );

        return new JsonResponse(ApiEnvelope::wrap($data));
    }

    #[Route('/trigger/{sourceId}', name: 'api_scrape_runs_trigger', methods: ['POST'])]
    public function trigger(string $sourceId): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::SCRAPING_TRIGGER);

        $source = $this->sourceRepo->find($sourceId);

        if ($source === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Source definition not found.'),
                404,
            );
        }

        if ($source->getStatus() === 'DISABLED') {
            return new JsonResponse(
                ErrorEnvelope::create('SOURCE_DISABLED', 'Cannot trigger scrape for a disabled source.'),
                422,
            );
        }

        $run = $this->orchestrator->runForSource($source);

        return new JsonResponse(ApiEnvelope::wrap($this->serializeRun($run)), 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRun(ScrapeRun $run): array
    {
        return [
            'id' => $run->getId()->toRfc4122(),
            'source_definition_id' => $run->getSourceDefinition()->getId()->toRfc4122(),
            'source_name' => $run->getSourceDefinition()->getName(),
            'status' => $run->getStatus(),
            'items_found' => $run->getItemsFound(),
            'items_new' => $run->getItemsNew(),
            'items_updated' => $run->getItemsUpdated(),
            'items_failed' => $run->getItemsFailed(),
            'proxy_pool_id' => $run->getProxyPool()?->getId()->toRfc4122(),
            'started_at' => $run->getStartedAt()?->format('c'),
            'completed_at' => $run->getCompletedAt()?->format('c'),
            'error_detail' => $run->getErrorDetail(),
            'created_at' => $run->getCreatedAt()->format('c'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(ScrapeRunItem $item): array
    {
        return [
            'id' => $item->getId()->toRfc4122(),
            'source_url' => $item->getSourceUrl(),
            'status' => $item->getStatus(),
            'extracted_data' => $item->getExtractedData(),
            'error_detail' => $item->getErrorDetail(),
            'content_item_id' => $item->getContentItemId(),
            'created_at' => $item->getCreatedAt()->format('c'),
        ];
    }
}
