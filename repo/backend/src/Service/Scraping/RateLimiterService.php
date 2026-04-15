<?php

declare(strict_types=1);

namespace App\Service\Scraping;

use App\Entity\Scraping\SourceDefinition;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Per-source sliding-window rate limiter backed by the scrape_source_rate_limits table.
 */
class RateLimiterService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Check whether the source is under its rate limit and atomically increment
     * the counter for the current minute window.
     *
     * Uses INSERT ... ON DUPLICATE KEY UPDATE for atomicity.
     *
     * @return bool true if the request is allowed, false if rate-limited
     */
    public function checkAndIncrement(SourceDefinition $source): bool
    {
        $conn = $this->em->getConnection();

        $windowStart = new \DateTimeImmutable('now');
        // Truncate to minute boundary
        $windowKey = $windowStart->format('Y-m-d H:i:00');
        $sourceId = $source->getId()->toBinary();
        $maxRpm = $source->getMaxRequestsPerMinute();

        // Check current count
        $currentCount = $conn->fetchOne(
            'SELECT request_count FROM scrape_source_rate_limits WHERE source_definition_id = :sid AND window_start = :ws',
            ['sid' => $sourceId, 'ws' => $windowKey],
        );

        if ($currentCount !== false && (int) $currentCount >= $maxRpm) {
            return false;
        }

        // Atomic upsert
        $conn->executeStatement(
            <<<'SQL'
                INSERT INTO scrape_source_rate_limits (id, source_definition_id, window_start, request_count)
                VALUES (:id, :sid, :ws, 1)
                ON DUPLICATE KEY UPDATE request_count = request_count + 1
            SQL,
            [
                'id' => \Symfony\Component\Uid\Uuid::v7()->toBinary(),
                'sid' => $sourceId,
                'ws' => $windowKey,
            ],
        );

        return true;
    }
}
