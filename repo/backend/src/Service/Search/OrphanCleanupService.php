<?php

declare(strict_types=1);

namespace App\Service\Search;

use Doctrine\DBAL\Connection;

class OrphanCleanupService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Delete search index entries where content item has been archived for 14+ days.
     *
     * @return int Number of entries deleted
     */
    public function cleanup(): int
    {
        $cutoff = (new \DateTimeImmutable())->modify('-14 days')->format('Y-m-d H:i:s');

        $deleted = $this->connection->executeStatement(
            <<<'SQL'
                DELETE csi FROM content_search_index csi
                INNER JOIN content_items ci ON ci.id = UNHEX(REPLACE(csi.content_item_id, '-', ''))
                WHERE ci.status = 'ARCHIVED'
                  AND ci.updated_at <= :cutoff
                SQL,
            ['cutoff' => $cutoff],
        );

        return $deleted;
    }
}
