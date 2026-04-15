<?php

declare(strict_types=1);

namespace App\Service\Search;

use Doctrine\DBAL\Connection;

class SearchService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Full-text search with weighted relevance scoring.
     *
     * @param array<string, mixed> $filters  Keys: contentType, storeId, regionId, dateFrom, dateTo
     * @param string[]|null        $accessibleStoreIds   null = unrestricted access
     * @param string[]|null        $accessibleRegionIds  null = unrestricted access
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function search(
        string $query,
        array $filters,
        string $sort,
        int $page,
        int $perPage,
        ?array $accessibleStoreIds,
        ?array $accessibleRegionIds = null,
    ): array {
        $query = trim($query);

        if ($query === '') {
            return ['items' => [], 'total' => 0];
        }

        // Sanitize query for BOOLEAN MODE — escape special chars but keep + and -.
        $safeQuery = $this->sanitizeBooleanQuery($query);

        $params = ['q' => $safeQuery];
        $types = [];

        // Build WHERE clauses.
        $whereClauses = [
            "MATCH(csi.title, csi.tags_text, csi.author_name, csi.body_text) AGAINST(:q IN BOOLEAN MODE)",
            "csi.status IN ('PUBLISHED','UPDATED','ROLLED_BACK')",
        ];

        if (isset($filters['contentType']) && $filters['contentType'] !== '') {
            $whereClauses[] = 'csi.content_type = :contentType';
            $params['contentType'] = $filters['contentType'];
        }
        if (isset($filters['storeId']) && $filters['storeId'] !== '') {
            $whereClauses[] = 'csi.store_id = :storeId';
            $params['storeId'] = $filters['storeId'];
        }
        if (isset($filters['regionId']) && $filters['regionId'] !== '') {
            $whereClauses[] = 'csi.region_id = :regionId';
            $params['regionId'] = $filters['regionId'];
        }
        if (isset($filters['dateFrom']) && $filters['dateFrom'] !== '') {
            $whereClauses[] = 'csi.published_at >= :dateFrom';
            $params['dateFrom'] = $filters['dateFrom'];
        }
        if (isset($filters['dateTo']) && $filters['dateTo'] !== '') {
            $whereClauses[] = 'csi.published_at <= :dateTo';
            $params['dateTo'] = $filters['dateTo'];
        }

        // Auth filtering: restrict by accessible store IDs and region IDs.
        if ($accessibleStoreIds !== null) {
            $hasStores = count($accessibleStoreIds) > 0;
            $hasRegions = $accessibleRegionIds !== null && count($accessibleRegionIds) > 0;

            if (!$hasStores && !$hasRegions) {
                // User has no store or region access — return empty.
                return ['items' => [], 'total' => 0];
            }

            $scopeParts = [];

            if ($hasStores) {
                $storeIdPlaceholders = [];
                foreach ($accessibleStoreIds as $idx => $sid) {
                    $key = 'store_' . $idx;
                    $storeIdPlaceholders[] = ':' . $key;
                    $params[$key] = $sid;
                }
                $scopeParts[] = 'csi.store_id IN (' . implode(', ', $storeIdPlaceholders) . ')';
            }

            if ($hasRegions) {
                $regionIdPlaceholders = [];
                foreach ($accessibleRegionIds as $idx => $rid) {
                    $key = 'region_' . $idx;
                    $regionIdPlaceholders[] = ':' . $key;
                    $params[$key] = $rid;
                }
                $scopeParts[] = '(csi.store_id IS NULL AND csi.region_id IN (' . implode(', ', $regionIdPlaceholders) . '))';
            }

            $whereClauses[] = '(' . implode(' OR ', $scopeParts) . ')';
        }

        $whereSQL = implode(' AND ', $whereClauses);

        // Sort.
        $orderBy = match ($sort) {
            'newest' => 'csi.published_at DESC',
            'most_viewed' => 'csi.view_count DESC',
            'highest_reply' => 'csi.reply_count DESC',
            default => 'relevance_score DESC',
        };

        // Count query.
        $countSQL = "SELECT COUNT(*) AS total FROM content_search_index csi WHERE {$whereSQL}";
        $total = (int) $this->connection->fetchOne($countSQL, $params);

        if ($total === 0) {
            return ['items' => [], 'total' => 0];
        }

        // Data query with weighted FULLTEXT relevance.
        // Join content_items to get view_count and reply_count.
        $offset = ($page - 1) * $perPage;
        $dataSQL = <<<SQL
            SELECT csi.*, ci.view_count, ci.reply_count,
                (MATCH(csi.title) AGAINST(:q IN BOOLEAN MODE) * 5 +
                 MATCH(csi.tags_text) AGAINST(:q IN BOOLEAN MODE) * 4 +
                 MATCH(csi.author_name) AGAINST(:q IN BOOLEAN MODE) * 2 +
                 MATCH(csi.body_text) AGAINST(:q IN BOOLEAN MODE) * 1) AS relevance_score
            FROM content_search_index csi
            LEFT JOIN content_items ci ON csi.content_item_id = ci.id
            WHERE {$whereSQL}
            ORDER BY {$orderBy}
            LIMIT {$perPage} OFFSET {$offset}
            SQL;

        $rows = $this->connection->fetchAllAssociative($dataSQL, $params);

        // Generate highlight snippets and normalize to frontend contract.
        $items = array_map(
            fn (array $row) => $this->enrichWithSnippet($row, $query),
            $rows,
        );

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Generate highlight snippets and normalize row to the frontend SearchResult contract.
     *
     * @return array<string, mixed>
     */
    private function enrichWithSnippet(array $row, string $originalQuery): array
    {
        $bodyText = $row['body_text'] ?? '';
        $title = $row['title'] ?? '';
        $snippet = '';

        // Extract first non-boolean term for position matching.
        $terms = preg_split('/\s+/', $originalQuery);
        $terms = array_filter($terms, static fn (string $t) => $t !== '' && $t !== '+' && $t !== '-');
        $terms = array_values($terms);

        $snippetLength = 250;
        $bodyLength = mb_strlen($bodyText);

        if ($bodyLength === 0 || count($terms) === 0) {
            $snippet = mb_substr($bodyText, 0, $snippetLength);
        } else {
            // Find first occurrence of any term.
            $firstPos = $bodyLength;
            foreach ($terms as $term) {
                $cleanTerm = ltrim($term, '+-');
                if ($cleanTerm === '') {
                    continue;
                }
                $pos = mb_stripos($bodyText, $cleanTerm);
                if ($pos !== false && $pos < $firstPos) {
                    $firstPos = $pos;
                }
            }

            if ($firstPos >= $bodyLength) {
                $firstPos = 0;
            }

            // Center the snippet around the first match.
            $start = max(0, $firstPos - (int) ($snippetLength / 2));
            $end = min($bodyLength, $start + $snippetLength);

            // Adjust start if we hit the end.
            if ($end === $bodyLength && ($end - $start) < $snippetLength) {
                $start = max(0, $end - $snippetLength);
            }

            $snippet = mb_substr($bodyText, $start, $end - $start);

            if ($start > 0) {
                $snippet = '...' . $snippet;
            }
            if ($end < $bodyLength) {
                $snippet .= '...';
            }
        }

        // Build highlight_title: wrap matches in <mark> tags on the title.
        $highlightTitle = $title;
        foreach ($terms as $term) {
            $cleanTerm = ltrim($term, '+-');
            if ($cleanTerm === '') {
                continue;
            }
            $escaped = preg_quote($cleanTerm, '/');
            $highlightTitle = preg_replace(
                '/(' . $escaped . ')/iu',
                '<mark>$1</mark>',
                $highlightTitle,
            );
        }

        // Parse tags_text (comma-separated) into an array.
        $tagsText = $row['tags_text'] ?? '';
        $tags = $tagsText !== '' ? array_map('trim', explode(',', $tagsText)) : [];

        // Return normalized fields matching the frontend SearchResult contract.
        return [
            'id' => $row['content_item_id'],
            'content_type' => $row['content_type'] ?? '',
            'title' => $title,
            'author_name' => $row['author_name'] ?? '',
            'published_at' => $row['published_at'] ?? null,
            'tags' => $tags,
            'view_count' => (int) ($row['view_count'] ?? 0),
            'reply_count' => (int) ($row['reply_count'] ?? 0),
            'snippet' => $snippet,
            'highlight_title' => $highlightTitle,
            'relevance_score' => (float) ($row['relevance_score'] ?? 0),
        ];
    }

    /**
     * Sanitize a user query for MySQL BOOLEAN MODE.
     * Keeps + and - prefixes, strips other operators.
     */
    private function sanitizeBooleanQuery(string $query): string
    {
        // Remove chars that are special in BOOLEAN MODE except + - and whitespace.
        $sanitized = preg_replace('/[<>()~*"@]/', '', $query);

        return trim((string) $sanitized);
    }
}
