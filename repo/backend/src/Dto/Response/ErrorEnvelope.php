<?php

declare(strict_types=1);

namespace App\Dto\Response;

use Symfony\Component\Uid\Uuid;

final class ErrorEnvelope
{
    /**
     * @param array<string, mixed> $details
     * @return array{data: null, meta: array{request_id: string, timestamp: string}, error: array{code: string, message: string, details: array<string, mixed>}}
     */
    public static function create(string $code, string $message, array $details = []): array
    {
        return [
            'data' => null,
            'meta' => [
                'request_id' => Uuid::v4()->toRfc4122(),
                'timestamp' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c'),
            ],
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ];
    }
}
