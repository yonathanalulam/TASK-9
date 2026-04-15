<?php

declare(strict_types=1);

namespace App\Service\Warehouse;

use App\Entity\Warehouse\DimChannel;
use App\Entity\Warehouse\DimProduct;
use App\Entity\Warehouse\DimCustomer;
use App\Entity\Warehouse\DimRegion;
use Doctrine\ORM\EntityManagerInterface;

/**
 * SCD Type 2 dimension loader.
 *
 * Reads from transactional tables (stores, mdm_regions, content_items)
 * and synchronises the warehouse dimension tables, closing old rows
 * (effectiveTo + isCurrent=false) and inserting new current rows when
 * source attributes change.
 */
class DimensionLoaderService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Load / refresh all dimensions using SCD Type 2 logic.
     *
     * @return array{products: array{inserted: int, closed: int}, customers: array{inserted: int, closed: int}, channels: array{inserted: int, closed: int}, regions: array{inserted: int, closed: int}}
     */
    public function loadDimensions(): array
    {
        return [
            'products' => $this->loadProducts(),
            'customers' => $this->loadCustomers(),
            'channels' => $this->loadChannels(),
            'regions' => $this->loadRegions(),
        ];
    }

    /**
     * @return array{inserted: int, closed: int}
     */
    private function loadProducts(): array
    {
        $conn = $this->em->getConnection();

        // Source: content_items treated as product catalogue (id as productId, title as productName, content_type as category)
        $sourceRows = $conn->fetchAllAssociative(
            'SELECT DISTINCT CAST(id AS UNSIGNED) AS product_id, title AS product_name, content_type AS category FROM content_items',
        );

        $currentDims = $conn->fetchAllAssociative(
            'SELECT product_key, product_id, product_name, category FROM wh_dim_product WHERE is_current = 1',
        );

        $currentMap = [];
        foreach ($currentDims as $row) {
            $currentMap[(int) $row['product_id']] = $row;
        }

        $inserted = 0;
        $closed = 0;
        $today = new \DateTimeImmutable('today');

        foreach ($sourceRows as $src) {
            $pid = (int) $src['product_id'];

            if (!isset($currentMap[$pid])) {
                // New product
                $dim = new DimProduct();
                $dim->setProductId($pid);
                $dim->setProductName((string) $src['product_name']);
                $dim->setCategory($src['category'] ?? null);
                $dim->setEffectiveFrom($today);
                $this->em->persist($dim);
                $inserted++;
            } else {
                $cur = $currentMap[$pid];
                // Check for attribute changes (SCD2)
                if ($cur['product_name'] !== $src['product_name'] || $cur['category'] !== ($src['category'] ?? null)) {
                    // Close old record
                    $conn->executeStatement(
                        'UPDATE wh_dim_product SET is_current = 0, effective_to = :today WHERE product_key = :pk',
                        ['today' => $today->format('Y-m-d'), 'pk' => $cur['product_key']],
                    );
                    $closed++;

                    // Insert new current record
                    $dim = new DimProduct();
                    $dim->setProductId($pid);
                    $dim->setProductName((string) $src['product_name']);
                    $dim->setCategory($src['category'] ?? null);
                    $dim->setEffectiveFrom($today);
                    $this->em->persist($dim);
                    $inserted++;
                }
            }
        }

        $this->em->flush();

        return ['inserted' => $inserted, 'closed' => $closed];
    }

    /**
     * @return array{inserted: int, closed: int}
     */
    private function loadCustomers(): array
    {
        $conn = $this->em->getConnection();

        // Source: users table treated as customer dimension
        $sourceRows = $conn->fetchAllAssociative(
            'SELECT CAST(id AS UNSIGNED) AS customer_id, email AS customer_name FROM users',
        );

        $currentDims = $conn->fetchAllAssociative(
            'SELECT customer_key, customer_id, customer_name, customer_segment FROM wh_dim_customer WHERE is_current = 1',
        );

        $currentMap = [];
        foreach ($currentDims as $row) {
            $currentMap[(int) $row['customer_id']] = $row;
        }

        $inserted = 0;
        $closed = 0;
        $today = new \DateTimeImmutable('today');

        foreach ($sourceRows as $src) {
            $cid = (int) $src['customer_id'];

            if (!isset($currentMap[$cid])) {
                $dim = new DimCustomer();
                $dim->setCustomerId($cid);
                $dim->setCustomerName((string) $src['customer_name']);
                $dim->setEffectiveFrom($today);
                $this->em->persist($dim);
                $inserted++;
            } else {
                $cur = $currentMap[$cid];
                if ($cur['customer_name'] !== $src['customer_name']) {
                    $conn->executeStatement(
                        'UPDATE wh_dim_customer SET is_current = 0, effective_to = :today WHERE customer_key = :pk',
                        ['today' => $today->format('Y-m-d'), 'pk' => $cur['customer_key']],
                    );
                    $closed++;

                    $dim = new DimCustomer();
                    $dim->setCustomerId($cid);
                    $dim->setCustomerName((string) $src['customer_name']);
                    $dim->setEffectiveFrom($today);
                    $this->em->persist($dim);
                    $inserted++;
                }
            }
        }

        $this->em->flush();

        return ['inserted' => $inserted, 'closed' => $closed];
    }

    /**
     * @return array{inserted: int, closed: int}
     */
    private function loadChannels(): array
    {
        $conn = $this->em->getConnection();

        // Source: stores table — store_type becomes channel
        $sourceRows = $conn->fetchAllAssociative(
            'SELECT DISTINCT store_type AS channel_code, store_type AS channel_name, store_type AS channel_type FROM stores',
        );

        $currentDims = $conn->fetchAllAssociative(
            'SELECT channel_key, channel_code, channel_name, channel_type FROM wh_dim_channel WHERE is_current = 1',
        );

        $currentMap = [];
        foreach ($currentDims as $row) {
            $currentMap[$row['channel_code']] = $row;
        }

        $inserted = 0;
        $closed = 0;

        foreach ($sourceRows as $src) {
            $code = (string) $src['channel_code'];

            if (!isset($currentMap[$code])) {
                $dim = new DimChannel();
                $dim->setChannelCode($code);
                $dim->setChannelName((string) $src['channel_name']);
                $dim->setChannelType((string) $src['channel_type']);
                $this->em->persist($dim);
                $inserted++;
            }
        }

        $this->em->flush();

        return ['inserted' => $inserted, 'closed' => $closed];
    }

    /**
     * @return array{inserted: int, closed: int}
     */
    private function loadRegions(): array
    {
        $conn = $this->em->getConnection();

        $sourceRows = $conn->fetchAllAssociative(
            'SELECT code, name, hierarchy_level FROM mdm_regions WHERE is_active = 1',
        );

        $currentDims = $conn->fetchAllAssociative(
            'SELECT region_key, region_code, region_name, region_level FROM wh_dim_region WHERE is_current = 1',
        );

        $currentMap = [];
        foreach ($currentDims as $row) {
            $currentMap[$row['region_code']] = $row;
        }

        $inserted = 0;
        $closed = 0;

        foreach ($sourceRows as $src) {
            $code = (string) $src['code'];

            if (!isset($currentMap[$code])) {
                $dim = new DimRegion();
                $dim->setRegionCode($code);
                $dim->setRegionName((string) $src['name']);
                $dim->setRegionLevel((int) $src['hierarchy_level']);
                $this->em->persist($dim);
                $inserted++;
            } else {
                $cur = $currentMap[$code];
                if ($cur['region_name'] !== $src['name'] || (int) $cur['region_level'] !== (int) $src['hierarchy_level']) {
                    $conn->executeStatement(
                        'UPDATE wh_dim_region SET is_current = 0 WHERE region_key = :pk',
                        ['pk' => $cur['region_key']],
                    );
                    $closed++;

                    $dim = new DimRegion();
                    $dim->setRegionCode($code);
                    $dim->setRegionName((string) $src['name']);
                    $dim->setRegionLevel((int) $src['hierarchy_level']);
                    $this->em->persist($dim);
                    $inserted++;
                }
            }
        }

        $this->em->flush();

        return ['inserted' => $inserted, 'closed' => $closed];
    }
}
