<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthCheckController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    #[Route('/api/v1/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return new JsonResponse(ApiEnvelope::wrap([
                'status' => 'healthy',
                'database' => 'connected',
            ]));
        } catch (\Throwable) {
            return new JsonResponse(ApiEnvelope::wrap([
                'status' => 'degraded',
                'database' => 'disconnected',
            ]), 503);
        }
    }
}
