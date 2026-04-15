<?php

declare(strict_types=1);

namespace App\Dto\Response;

use Symfony\Component\Uid\Uuid;

final class ApiEnvelope
{
    /**
     * @param array<string, mixed> $meta
     * @return array{data: mixed, meta: array<string, mixed>, error: null}
     */
    public static function wrap(mixed $data, array $meta = []): array
    {
        return [
            'data' => $data,
            'meta' => array_merge([
                'request_id' => Uuid::v4()->toRfc4122(),
                'timestamp' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c'),
            ], $meta),
            'error' => null,
        ];
    }
}
