<?php

declare(strict_types=1);

namespace App\Service\Warehouse;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Pre-built analytics queries against the warehouse star schema.
 */
class AnalyticsQueryService
{
    private readonly Connection $conn;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        $this->conn = $this->em->getConnection();
    }

    /**
     * Sales aggregated by product, region, channel, and day.
     *
     * @param array{product_key?: int, region_key?: int, channel_key?: int, date_from?: string, date_to?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function salesByProductRegionChannelDay(array $filters = []): array
    {
        $sql = <<<'SQL'
            SELECT
                dp.product_name,
                dr.region_name,
                dc.channel_name,
                dt.full_date,
                SUM(fs.gross_sales) AS total_gross,
                SUM(fs.net_sales) AS total_net,
                SUM(fs.quantity) AS total_quantity,
                SUM(fs.order_count) AS total_orders
            FROM wh_fact_sales fs
            INNER JOIN wh_dim_product dp ON fs.product_key = dp.product_key
            INNER JOIN wh_dim_region dr ON fs.region_key = dr.region_key
            INNER JOIN wh_dim_channel dc ON fs.channel_key = dc.channel_key
            INNER JOIN wh_dim_time dt ON fs.time_key = dt.time_key
            WHERE 1=1
        SQL;

        $params = [];

        if (isset($filters['product_key'])) {
            $sql .= ' AND fs.product_key = :pk';
            $params['pk'] = $filters['product_key'];
        }
        if (isset($filters['region_key'])) {
            $sql .= ' AND fs.region_key = :rk';
            $params['rk'] = $filters['region_key'];
        }
        if (isset($filters['channel_key'])) {
            $sql .= ' AND fs.channel_key = :ck';
            $params['ck'] = $filters['channel_key'];
        }
        if (isset($filters['date_from'])) {
            $sql .= ' AND dt.full_date >= :df';
            $params['df'] = $filters['date_from'];
        }
        if (isset($filters['date_to'])) {
            $sql .= ' AND dt.full_date <= :dt';
            $params['dt'] = $filters['date_to'];
        }

        $sql .= ' GROUP BY dp.product_name, dr.region_name, dc.channel_name, dt.full_date ORDER BY dt.full_date DESC';

        return $this->conn->fetchAllAssociative($sql, $params);
    }

    /**
     * Time series of sales aggregated by the given granularity.
     *
     * @param string $granularity DAY, WEEK, MONTH, QUARTER, YEAR
     * @return list<array<string, mixed>>
     */
    public function salesTrends(string $dateFrom, string $dateTo, string $granularity = 'DAY'): array
    {
        $groupExpr = match (strtoupper($granularity)) {
            'WEEK' => 'dt.year, dt.week_of_year',
            'MONTH' => 'dt.year, dt.month_number',
            'QUARTER' => 'dt.year, dt.quarter',
            'YEAR' => 'dt.year',
            default => 'dt.full_date',
        };

        $selectExpr = match (strtoupper($granularity)) {
            'WEEK' => 'dt.year AS period_year, dt.week_of_year AS period_value',
            'MONTH' => 'dt.year AS period_year, dt.month_number AS period_value',
            'QUARTER' => 'dt.year AS period_year, dt.quarter AS period_value',
            'YEAR' => 'dt.year AS period_year, 0 AS period_value',
            default => 'dt.full_date AS period_year, 0 AS period_value',
        };

        $sql = <<<SQL
            SELECT
                {$selectExpr},
                SUM(fs.gross_sales) AS total_gross,
                SUM(fs.net_sales) AS total_net,
                SUM(fs.quantity) AS total_quantity,
                SUM(fs.order_count) AS total_orders
            FROM wh_fact_sales fs
            INNER JOIN wh_dim_time dt ON fs.time_key = dt.time_key
            WHERE dt.full_date >= :df AND dt.full_date <= :dt
            GROUP BY {$groupExpr}
            ORDER BY {$groupExpr}
        SQL;

        return $this->conn->fetchAllAssociative($sql, [
            'df' => $dateFrom,
            'dt' => $dateTo,
        ]);
    }

    /**
     * Content volume counts by type and store.
     *
     * @return list<array<string, mixed>>
     */
    public function contentVolumeByType(): array
    {
        return $this->conn->fetchAllAssociative(<<<'SQL'
            SELECT
                ci.content_type,
                s.name AS store_name,
                COUNT(*) AS content_count
            FROM content_items ci
            LEFT JOIN stores s ON ci.store_id = s.id
            GROUP BY ci.content_type, s.name
            ORDER BY content_count DESC
        SQL);
    }

    /**
     * Dashboard KPI summary.
     *
     * @return array{total_gross_sales: string, total_net_sales: string, total_orders: int, content_count: int, export_count: int, retention_case_count: int, sensitive_access_count: int}
     */
    public function kpiSummary(): array
    {
        $sales = $this->conn->fetchAssociative(<<<'SQL'
            SELECT
                COALESCE(SUM(gross_sales), 0) AS total_gross_sales,
                COALESCE(SUM(net_sales), 0) AS total_net_sales,
                COALESCE(SUM(order_count), 0) AS total_orders
            FROM wh_fact_sales
        SQL) ?: ['total_gross_sales' => '0', 'total_net_sales' => '0', 'total_orders' => 0];

        $contentCount = (int) $this->conn->fetchOne('SELECT COUNT(*) FROM content_items');
        $exportCount = (int) $this->conn->fetchOne('SELECT COUNT(*) FROM export_jobs');
        $retentionCount = (int) $this->conn->fetchOne('SELECT COUNT(*) FROM retention_cases');
        $sensitiveAccessCount = (int) $this->conn->fetchOne('SELECT COUNT(*) FROM audit_events WHERE action = \'SENSITIVE_ACCESS\'');

        return [
            'total_gross_sales' => (string) $sales['total_gross_sales'],
            'total_net_sales' => (string) $sales['total_net_sales'],
            'total_orders' => (int) $sales['total_orders'],
            'content_count' => $contentCount,
            'export_count' => $exportCount,
            'retention_case_count' => $retentionCount,
            'sensitive_access_count' => $sensitiveAccessCount,
        ];
    }
}
