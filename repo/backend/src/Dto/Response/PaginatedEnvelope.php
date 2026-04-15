<?php

declare(strict_types=1);

namespace App\Dto\Response;

final class PaginatedEnvelope
{
    /**
     * @return array{data: mixed, meta: array<string, mixed>, error: null}
     */
    public static function wrap(mixed $data, int $page, int $perPage, int $total): array
    {
        return ApiEnvelope::wrap($data, [
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ]);
    }
}
