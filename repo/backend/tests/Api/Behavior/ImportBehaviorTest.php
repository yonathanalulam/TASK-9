<?php

declare(strict_types=1);

namespace App\Tests\Api\Behavior;

use App\Entity\ImportBatch;
use App\Entity\ImportItem;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRoleAssignment;
use App\Enum\RoleName;
use App\Enum\ScopeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Real runtime behavior tests for the imports API.
 *
 * Covers the three previously uncovered endpoints:
 *   GET /api/v1/imports            — paginated list of import batches
 *   GET /api/v1/imports/{id}       — single batch with full shape
 *   GET /api/v1/imports/{id}/items — paginated items for a batch
 *
 * Tests verify:
 *   - exact 200 status codes with correct envelope shape
 *   - exact 401 for unauthenticated requests
 *   - exact 403 for roles without IMPORT_VIEW permission
 *   - exact 404 for non-existent resources with error envelope
 *   - payload field contracts (all required fields present)
 *   - pagination meta structure (page, per_page, total)
 *   - status filter returns only matching batches
 *   - item list is correctly associated to parent batch
 *
 * Batches and items are seeded via Doctrine directly (no API create endpoint)
 * so these tests are free of any upstream service bugs.
 */
final class ImportBehaviorTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private static int $seq = 0;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/imports — paginated list
    // -----------------------------------------------------------------------

    public function testListImportBatchesReturns200WithPaginatedEnvelope(): void
    {
        $token = $this->loginAsAdmin();
        $actor = $this->getLastUser();
        $this->seedBatch($actor, 'TestSource-List');

        $this->request('GET', '/api/v1/imports', $token);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertNull($body['error']);
        self::assertIsArray($body['data']);
        self::assertArrayHasKey('pagination', $body['meta']);
        self::assertArrayHasKey('total', $body['meta']['pagination']);
        self::assertArrayHasKey('page', $body['meta']['pagination']);
        self::assertArrayHasKey('per_page', $body['meta']['pagination']);
        self::assertGreaterThanOrEqual(1, $body['meta']['pagination']['total']);
    }

    public function testListImportBatchesFilterByStatusReturnsOnlyMatchingBatches(): void
    {
        $token = $this->loginAsAdmin();
        $actor = $this->getLastUser();
        // Seed a COMPLETED batch — status filter must return only COMPLETED batches
        $this->seedBatch($actor, 'FilterBatch-Completed', 'COMPLETED');

        $this->request('GET', '/api/v1/imports?status=COMPLETED&per_page=100', $token);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertNull($body['error']);
        foreach ($body['data'] as $batch) {
            self::assertSame(
                'COMPLETED',
                $batch['status'],
                'All returned batches must match the requested status filter',
            );
        }
    }

    public function testListImportBatchesPageAndPerPageRespected(): void
    {
        $token = $this->loginAsAdmin();
        $actor = $this->getLastUser();
        // Seed three batches
        $this->seedBatch($actor, 'PageBatch-1');
        $this->seedBatch($actor, 'PageBatch-2');
        $this->seedBatch($actor, 'PageBatch-3');

        $this->request('GET', '/api/v1/imports?per_page=2&page=1', $token);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertSame(2, $body['meta']['pagination']['per_page']);
        self::assertSame(1, $body['meta']['pagination']['page']);
        self::assertCount(2, $body['data'], 'page=1, per_page=2 must return exactly 2 items');
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/imports/{id} — show single batch
    // -----------------------------------------------------------------------

    public function testGetImportBatchByIdReturns200WithCompleteShape(): void
    {
        $token = $this->loginAsAdmin();
        $actor = $this->getLastUser();
        $batch = $this->seedBatch($actor, 'ShapeBatch');
        $batchId = $batch->getId()->toRfc4122();

        $this->request('GET', "/api/v1/imports/{$batchId}", $token);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertNull($body['error']);
        self::assertSame($batchId, $body['data']['id']);
        self::assertSame('ShapeBatch', $body['data']['source_name']);
        self::assertArrayHasKey('status', $body['data']);
        self::assertArrayHasKey('total_items', $body['data']);
        self::assertArrayHasKey('processed_items', $body['data']);
        self::assertArrayHasKey('merged_items', $body['data']);
        self::assertArrayHasKey('review_items', $body['data']);
        self::assertArrayHasKey('created_by', $body['data']);
        self::assertArrayHasKey('created_at', $body['data']);
        self::assertArrayHasKey('completed_at', $body['data']);
    }

    public function testGetImportBatchByIdReturnsCorrectStatusField(): void
    {
        $token = $this->loginAsAdmin();
        $actor = $this->getLastUser();
        $batch = $this->seedBatch($actor, 'PendingBatch', 'PENDING');

        $this->request('GET', "/api/v1/imports/{$batch->getId()->toRfc4122()}", $token);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('PENDING', $body['data']['status']);
    }

    public function testGetImportBatchByIdReturns404ForUnknownId(): void
    {
        $token = $this->loginAsAdmin();

        $this->request('GET', '/api/v1/imports/00000000-0000-0000-0000-000000000099', $token);

        $response = $this->client->getResponse();
        self::assertSame(404, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertSame('NOT_FOUND', $body['error']['code']);
        self::assertNotEmpty($body['error']['message']);
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/imports/{id}/items — paginated item list
    // -----------------------------------------------------------------------

    public function testGetImportItemsReturns200WithPaginatedEnvelope(): void
    {
        $token = $this->loginAsAdmin();
        $actor = $this->getLastUser();
        $batch = $this->seedBatch($actor, 'ItemsBatch');
        $this->seedItem($batch, 'Senior PHP Developer', 'NEW');
        $batchId = $batch->getId()->toRfc4122();

        $this->request('GET', "/api/v1/imports/{$batchId}/items", $token);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertNull($body['error']);
        self::assertIsArray($body['data']);
        self::assertArrayHasKey('pagination', $body['meta']);
        self::assertArrayHasKey('total', $body['meta']['pagination']);
        self::assertGreaterThanOrEqual(1, $body['meta']['pagination']['total']);
    }

    public function testGetImportItemsReturnsCorrectItemShape(): void
    {
        $token = $this->loginAsAdmin();
        $actor = $this->getLastUser();
        $batch = $this->seedBatch($actor, 'ShapeItemBatch');
        $this->seedItem($batch, 'React Developer');
        $batchId = $batch->getId()->toRfc4122();

        $this->request('GET', "/api/v1/imports/{$batchId}/items", $token);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        $firstItem = $body['data'][0];

        self::assertArrayHasKey('id', $firstItem);
        self::assertArrayHasKey('import_batch_id', $firstItem);
        self::assertArrayHasKey('raw_title', $firstItem);
        self::assertArrayHasKey('normalized_title', $firstItem);
        self::assertArrayHasKey('dedup_fingerprint', $firstItem);
        self::assertArrayHasKey('status', $firstItem);
        self::assertArrayHasKey('created_at', $firstItem);
        self::assertSame($batchId, $firstItem['import_batch_id']);
        self::assertSame('React Developer', $firstItem['raw_title']);
    }

    public function testGetImportItemsReturns404ForUnknownBatch(): void
    {
        $token = $this->loginAsAdmin();

        $this->request('GET', '/api/v1/imports/00000000-0000-0000-0000-000000000099/items', $token);

        $response = $this->client->getResponse();
        self::assertSame(404, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertSame('NOT_FOUND', $body['error']['code']);
    }

    public function testGetImportItemsFilterByStatusReturnsOnlyMatchingItems(): void
    {
        $token = $this->loginAsAdmin();
        $actor = $this->getLastUser();
        $batch = $this->seedBatch($actor, 'FilterItemsBatch');
        $this->seedItem($batch, 'Filtered Item', 'AUTO_MERGED');
        $this->seedItem($batch, 'Unfiltered Item', 'NEW');
        $batchId = $batch->getId()->toRfc4122();

        $this->request('GET', "/api/v1/imports/{$batchId}/items?status=AUTO_MERGED", $token);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        foreach ($body['data'] as $item) {
            self::assertSame('AUTO_MERGED', $item['status'], 'Only AUTO_MERGED items must be returned when filtering by status');
        }
    }

    // -----------------------------------------------------------------------
    // Authorization enforcement
    // -----------------------------------------------------------------------

    public function testUnauthenticatedAccessToImportListReturns401(): void
    {
        $this->request('GET', '/api/v1/imports');

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testUnauthenticatedAccessToImportShowReturns401(): void
    {
        $this->request('GET', '/api/v1/imports/00000000-0000-0000-0000-000000000099');

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testUnauthenticatedAccessToImportItemsReturns401(): void
    {
        $this->request('GET', '/api/v1/imports/00000000-0000-0000-0000-000000000099/items');

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testStoreManagerCannotViewImports(): void
    {
        // STORE_MANAGER does not have IMPORT_VIEW permission.
        // ImportVoter.canView() allows: RECRUITER, OPERATIONS_ANALYST, COMPLIANCE_OFFICER, ADMINISTRATOR.
        $token = $this->loginAsRole(RoleName::STORE_MANAGER);

        $this->request('GET', '/api/v1/imports', $token);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testDispatcherCannotViewImports(): void
    {
        // DISPATCHER is also not in the IMPORT_VIEW allowed roles.
        $token = $this->loginAsRole(RoleName::DISPATCHER);

        $this->request('GET', '/api/v1/imports', $token);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testRecruiterCanViewImportList(): void
    {
        // RECRUITER has IMPORT_VIEW permission — list must return 200.
        $token = $this->loginAsRole(RoleName::RECRUITER);

        $this->request('GET', '/api/v1/imports', $token);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertNull($body['error']);
        self::assertIsArray($body['data']);
    }

    public function testComplianceOfficerCanViewImportList(): void
    {
        // COMPLIANCE_OFFICER has IMPORT_VIEW permission — list must return 200.
        $token = $this->loginAsRole(RoleName::COMPLIANCE_OFFICER);

        $this->request('GET', '/api/v1/imports', $token);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function request(
        string $method,
        string $url,
        ?string $token = null,
        ?array $body = null,
    ): void {
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        $this->client->request(
            $method,
            $url,
            [],
            [],
            $headers,
            $body !== null ? json_encode($body) : null,
        );
    }

    private function loginAsAdmin(): string
    {
        return $this->loginAsRole(RoleName::ADMINISTRATOR);
    }

    private function loginAsRole(RoleName $roleName): string
    {
        $suffix = 'beh_imp_' . (++self::$seq) . '_' . bin2hex(random_bytes(3));
        $password = 'V@lid1Password!';

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername($suffix);
        $user->setDisplayName('Import Behavior Test User');
        $user->setStatus('ACTIVE');
        $user->setPasswordHash($hasher->hashPassword($user, $password));
        $this->em->persist($user);

        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => $roleName->value]);
        if ($role === null) {
            $role = new Role();
            $role->setName($roleName->value);
            $role->setDisplayName(ucwords(str_replace('_', ' ', $roleName->value)));
            $role->setIsSystem(true);
            $this->em->persist($role);
            $this->em->flush();
        }

        $assignment = new UserRoleAssignment();
        $assignment->setUser($user);
        $assignment->setRole($role);
        $assignment->setScopeType(ScopeType::GLOBAL);
        $assignment->setEffectiveFrom(new \DateTimeImmutable('-1 day'));
        $assignment->setGrantedBy($user);
        $this->em->persist($assignment);
        $this->em->flush();

        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => $suffix, 'password' => $password]));

        return json_decode($this->client->getResponse()->getContent(), true)['data']['token'];
    }

    private function seedBatch(User $actor, string $sourceName, string $status = 'PENDING'): ImportBatch
    {
        $batch = new ImportBatch();
        $batch->setSourceName($sourceName);
        $batch->setStatus($status);
        $batch->setCreatedBy($actor);
        $this->em->persist($batch);
        $this->em->flush();

        return $batch;
    }

    private function seedItem(ImportBatch $batch, string $rawTitle, string $status = 'NEW'): ImportItem
    {
        $normalized = strtolower(preg_replace('/[^a-zA-Z0-9 ]/', '', $rawTitle));

        $item = new ImportItem();
        $item->setImportBatch($batch);
        $item->setRawTitle($rawTitle);
        $item->setNormalizedTitle($normalized);
        $item->setDedupFingerprint(hash('sha256', $normalized . '_' . uniqid()));
        $item->setStatus($status);
        $this->em->persist($item);
        $this->em->flush();

        return $item;
    }

    private function getLastUser(): User
    {
        $users = $this->em->getRepository(User::class)->findBy([], ['id' => 'DESC'], 1);

        return $users[0];
    }
}
