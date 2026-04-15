<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Service\Warehouse\AnalyticsQueryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Security\Permission;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/analytics')]
class AnalyticsController extends AbstractController
{
    public function __construct(
        private readonly AnalyticsQueryService $analyticsQuery,
    ) {
    }

    #[Route('/sales', name: 'api_analytics_sales', methods: ['GET'])]
    public function sales(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ANALYTICS_VIEW);

        $filters = [];

        if ($request->query->has('product_key')) {
            $filters['product_key'] = (int) $request->query->get('product_key');
        }
        if ($request->query->has('region_key')) {
            $filters['region_key'] = (int) $request->query->get('region_key');
        }
        if ($request->query->has('channel_key')) {
            $filters['channel_key'] = (int) $request->query->get('channel_key');
        }
        if ($request->query->has('date_from')) {
            $filters['date_from'] = $request->query->get('date_from');
        }
        if ($request->query->has('date_to')) {
            $filters['date_to'] = $request->query->get('date_to');
        }

        $data = $this->analyticsQuery->salesByProductRegionChannelDay($filters);

        return new JsonResponse(ApiEnvelope::wrap($data));
    }

    #[Route('/sales/trends', name: 'api_analytics_sales_trends', methods: ['GET'])]
    public function salesTrends(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ANALYTICS_VIEW);

        $dateFrom = $request->query->get('date_from', '2024-01-01');
        $dateTo = $request->query->get('date_to', date('Y-m-d'));
        $granularity = $request->query->get('granularity', 'DAY');

        $data = $this->analyticsQuery->salesTrends($dateFrom, $dateTo, $granularity);

        return new JsonResponse(ApiEnvelope::wrap($data));
    }

    #[Route('/content-volume', name: 'api_analytics_content_volume', methods: ['GET'])]
    public function contentVolume(): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ANALYTICS_VIEW);

        $data = $this->analyticsQuery->contentVolumeByType();

        return new JsonResponse(ApiEnvelope::wrap($data));
    }

    #[Route('/kpi-summary', name: 'api_analytics_kpi_summary', methods: ['GET'])]
    public function kpiSummary(): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ANALYTICS_VIEW);

        $data = $this->analyticsQuery->kpiSummary();

        return new JsonResponse(ApiEnvelope::wrap($data));
    }
}
