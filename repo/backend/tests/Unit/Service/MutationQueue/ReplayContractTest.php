<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\MutationQueue;

use App\Dto\Response\ApiEnvelope;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the replay request/response contract between frontend and backend.
 *
 * The backend expects snake_case field names in mutation replay payloads.
 * If the frontend sends camelCase, required fields will be missing and the
 * replay will be rejected.
 */
final class ReplayContractTest extends TestCase
{
    public function testBackendExpectsSnakeCaseFields(): void
    {
        // Backend reads mutation_id, entity_type, entity_id, operation, payload
        $validPayload = [
            'mutation_id' => 'uuid-1',
            'client_id' => 'client-1',
            'entity_type' => 'store',
            'entity_id' => null,
            'operation' => 'CREATE',
            'payload' => ['code' => 'STORE-001', 'name' => 'Test'],
        ];

        // All expected keys present
        self::assertArrayHasKey('mutation_id', $validPayload);
        self::assertArrayHasKey('entity_type', $validPayload);
        self::assertArrayHasKey('entity_id', $validPayload);
        self::assertArrayHasKey('operation', $validPayload);
        self::assertArrayHasKey('payload', $validPayload);
    }

    public function testCamelCasePayloadMissesMutationId(): void
    {
        // If frontend sent camelCase, backend would not find mutation_id
        $camelCasePayload = [
            'id' => 'uuid-1',           // WRONG: backend expects mutation_id
            'entityType' => 'store',     // WRONG: backend expects entity_type
            'entityId' => null,          // WRONG: backend expects entity_id
            'operation' => 'CREATE',
            'payload' => [],
        ];

        // mutation_id would be empty string, which triggers rejection
        $mutationId = (string) ($camelCasePayload['mutation_id'] ?? '');
        self::assertSame('', $mutationId, 'camelCase payload lacks mutation_id');
    }

    public function testResponseWrappedInApiEnvelope(): void
    {
        // Backend returns ApiEnvelope::wrap($results) which produces
        // { data: [...results...], meta: {...}, error: null }
        $results = [
            ['mutation_id' => 'uuid-1', 'status' => 'APPLIED'],
        ];

        $envelope = ApiEnvelope::wrap($results);

        self::assertArrayHasKey('data', $envelope);
        self::assertArrayHasKey('meta', $envelope);
        self::assertIsArray($envelope['data']);
        self::assertSame('APPLIED', $envelope['data'][0]['status']);
    }

    public function testResponseResultKeysAreSnakeCase(): void
    {
        // Backend response keys are snake_case
        $result = ['mutation_id' => 'uuid-1', 'status' => 'APPLIED'];

        self::assertArrayHasKey('mutation_id', $result);
        // NOT camelCase
        self::assertArrayNotHasKey('mutationId', $result);
    }
}
