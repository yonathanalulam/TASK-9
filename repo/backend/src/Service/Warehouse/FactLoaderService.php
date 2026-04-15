<?php

declare(strict_types=1);

namespace App\Service\Warehouse;

use App\Entity\Warehouse\WarehouseLoadRun;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Loads fact_sales rows from source transactional data.
 *
 * Looks up surrogate keys in dimension tables. Rejects rows where any
 * required dimension key cannot be resolved.
 */
class FactLoaderService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function loadFacts(WarehouseLoadRun $run): void
    {
        $conn = $this->em->getConnection();

        // Source: join content_items (product proxy), users (customer proxy), stores (channel proxy), mdm_regions (region proxy)
        // For demonstration, we create fact rows from content_items joined with stores.
        $sourceRows = $conn->fetchAllAssociative(<<<'SQL'
            SELECT
                ci.id AS src_product_id,
                ci.view_count AS quantity,
                ci.reply_count AS order_count,
                ci.published_at AS fact_date,
                s.store_type AS channel_code,
                r.code AS region_code
            FROM content_items ci
            LEFT JOIN stores s ON ci.store_id = s.id
            LEFT JOIN mdm_regions r ON ci.region_id = r.id
            WHERE ci.published_at IS NOT NULL
        SQL);

        $run->setRowsExtracted(count($sourceRows));

        // Build dimension lookup caches
        $productMap = $this->buildMap($conn, 'SELECT product_key, product_id FROM wh_dim_product WHERE is_current = 1', 'product_id', 'product_key');
        $customerMap = $this->buildMap($conn, 'SELECT customer_key, customer_id FROM wh_dim_customer WHERE is_current = 1', 'customer_id', 'customer_key');
        $channelMap = $this->buildMap($conn, 'SELECT channel_key, channel_code FROM wh_dim_channel WHERE is_current = 1', 'channel_code', 'channel_key');
        $regionMap = $this->buildMap($conn, 'SELECT region_key, region_code FROM wh_dim_region WHERE is_current = 1', 'region_code', 'region_key');

        $loaded = 0;
        $rejected = 0;
        $rejectedDetails = [];

        foreach ($sourceRows as $idx => $row) {
            $productKey = $productMap[(int) $row['src_product_id']] ?? null;
            $channelKey = $channelMap[$row['channel_code'] ?? ''] ?? null;
            $regionKey = $regionMap[$row['region_code'] ?? ''] ?? null;

            // Use first available customer as a fallback (simplified)
            $customerKey = !empty($customerMap) ? (int) array_values($customerMap)[0] : null;

            // Resolve time key
            $factDate = $row['fact_date'] ? substr((string) $row['fact_date'], 0, 10) : null;
            $timeKey = $factDate ? (int) str_replace('-', '', $factDate) : null;

            $missing = [];
            if ($productKey === null) {
                $missing[] = 'product';
            }
            if ($customerKey === null) {
                $missing[] = 'customer';
            }
            if ($channelKey === null) {
                $missing[] = 'channel';
            }
            if ($regionKey === null) {
                $missing[] = 'region';
            }
            if ($timeKey === null) {
                $missing[] = 'time';
            }

            if (count($missing) > 0) {
                $rejected++;
                $rejectedDetails[] = [
                    'row_index' => $idx,
                    'missing_dimensions' => $missing,
                    'src_product_id' => $row['src_product_id'],
                ];
                continue;
            }

            $conn->executeStatement(
                'INSERT INTO wh_fact_sales (product_key, customer_key, channel_key, region_key, time_key, gross_sales, net_sales, quantity, order_count) VALUES (:pk, :ck, :chk, :rk, :tk, :gs, :ns, :qty, :oc)',
                [
                    'pk' => $productKey,
                    'ck' => $customerKey,
                    'chk' => $channelKey,
                    'rk' => $regionKey,
                    'tk' => $timeKey,
                    'gs' => '0.00',
                    'ns' => '0.00',
                    'qty' => max(0, (int) $row['quantity']),
                    'oc' => max(1, (int) $row['order_count']),
                ],
            );
            $loaded++;
        }

        $run->setRowsLoaded($loaded);
        $run->setRowsRejected($rejected);

        if (count($rejectedDetails) > 0) {
            $run->setRejectedDetail($rejectedDetails);
        }

        $this->em->flush();
    }

    /**
     * @return array<string|int, int>
     */
    private function buildMap(\Doctrine\DBAL\Connection $conn, string $sql, string $keyCol, string $valCol): array
    {
        $rows = $conn->fetchAllAssociative($sql);
        $map = [];
        foreach ($rows as $row) {
            $map[$row[$keyCol]] = (int) $row[$valCol];
        }

        return $map;
    }
}
