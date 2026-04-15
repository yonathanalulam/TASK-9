<?php

declare(strict_types=1);

namespace App\Tests\Api\Behavior;

use App\Entity\MdmRegion;
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
 * Real runtime behavior tests for the mutation-replay API.
 *
 * Proves at runtime that the MutationReplayService enforces role and scope
 * constraints — replacing source-reading and reflection-based tests in
 * ReplayPermissionTest.php with assertions that actually execute the code path.
 *
 * Key behaviors tested:
 *   - Unauthenticated replay requests return 401
 *   - A role that has MUTATION_REPLAY permission but NOT ADMINISTRATOR (RECRUITER)
 *     has its store-CREATE mutation REJECTED with a permission error at runtime.
 *     This proves that the ReplayService checks rbacService.hasAnyRole at execution
 *     time, not just that the source code contains a certain method call.
 *   - An ADMINISTRATOR with a valid payload has store-CREATE mutation APPLIED.
 *   - Mutations with missing mutation_id are REJECTED with a validation error.
 *   - Mutations for unsupported entity types are REJECTED.
 *   - Idempotency: replaying the same mutation_id twice returns the cached result.
 */
final class MutationReplayBehaviorTest extends WebTestCase
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
    // Authentication
    // -----------------------------------------------------------------------

    public function testUnauthenticatedReplayReturns401(): void
    {
        $this->replay([
            [
                'mutation_id' => 'test-unauth-' . uniqid(),
                'entity_type' => 'store',
                'operation' => 'CREATE',
                'payload' => ['name' => 'Test'],
            ],
        ]);

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Permission enforcement at runtime (Workstream E)
    //
    // ReplayPermissionTest.php (unit) checks via reflection/source that
    // MutationReplayService injects RbacService and the source contains
    // "Store creation requires Administrator role".
    //
    // These tests prove that the permission check actually FIRES at runtime —
    // not just that the code structure implies it might.
    // -----------------------------------------------------------------------

    public function testRecruiterStoreCreateMutationIsRejectedAtRuntime(): void
    {
        // RECRUITER has MUTATION_REPLAY access so the HTTP endpoint returns 200,
        // but their store-CREATE mutation must be REJECTED by the service
        // because only ADMINISTRATOR can create stores via replay.
        $token = $this->loginAsRole(RoleName::RECRUITER);

        $mutationId = 'recruiter-store-create-' . bin2hex(random_bytes(4));

        $this->replay([
            [
                'mutation_id' => $mutationId,
                'client_id' => 'test-client',
                'entity_type' => 'store',
                'operation' => 'CREATE',
                'payload' => [
                    'code' => 'TEST01',
                    'name' => 'Recruiter Test Store',
                    'store_type' => 'STORE',
                    'region_id' => '00000000-0000-0000-0000-000000000001',
                ],
            ],
        ], $token);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode(),
            'Replay endpoint itself must return 200 — auth passed, per-mutation check fires inside');

        $body = json_decode($response->getContent(), true);
        self::assertNull($body['error']);
        self::assertIsArray($body['data']);

        $result = $body['data'][0];
        self::assertSame($mutationId, $result['mutation_id']);
        self::assertSame('REJECTED', $result['status'],
            'RECRUITER must not be able to create a store via replay — only ADMINISTRATOR can');
        self::assertStringContainsStringIgnoringCase(
            'administrator',
            $result['detail'] ?? '',
            'Rejection detail must mention the required Administrator role',
        );
    }

    public function testAdministratorStoreCreateMutationIsApplied(): void
    {
        // ADMINISTRATOR has MUTATION_REPLAY and ADMINISTRATOR role.
        // A store-CREATE with a valid payload (including a real region) must be APPLIED.
        $token = $this->loginAsAdmin();
        $regionId = $this->createRegion();

        $storeCode = 'MRB' . strtoupper(bin2hex(random_bytes(2)));
        $mutationId = 'admin-store-create-' . bin2hex(random_bytes(4));

        $this->replay([
            [
                'mutation_id' => $mutationId,
                'client_id' => 'test-client-admin',
                'entity_type' => 'store',
                'operation' => 'CREATE',
                'payload' => [
                    'code' => $storeCode,
                    'name' => 'Replay Created Store',
                    'store_type' => 'STORE',
                    'region_id' => $regionId,
                ],
            ],
        ], $token);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertNull($body['error']);

        $result = $body['data'][0];
        self::assertSame($mutationId, $result['mutation_id']);
        self::assertSame('APPLIED', $result['status'],
            'ADMINISTRATOR store-CREATE with valid payload must be APPLIED');
    }

    public function testStoreManagerCannotCreateStoreViaReplay(): void
    {
        // STORE_MANAGER has MUTATION_REPLAY but NOT ADMINISTRATOR role.
        // Store creation via replay must be REJECTED.
        $token = $this->loginAsRole(RoleName::STORE_MANAGER);

        $mutationId = 'sm-store-create-' . bin2hex(random_bytes(4));

        $this->replay([
            [
                'mutation_id' => $mutationId,
                'client_id' => 'test-client-sm',
                'entity_type' => 'store',
                'operation' => 'CREATE',
                'payload' => ['code' => 'TEST02', 'name' => 'SM Store', 'store_type' => 'STORE'],
            ],
        ], $token);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $result = json_decode($response->getContent(), true)['data'][0];
        self::assertSame('REJECTED', $result['status'],
            'STORE_MANAGER must not create stores via replay — only ADMINISTRATOR');
    }

    // -----------------------------------------------------------------------
    // Validation behavior
    // -----------------------------------------------------------------------

    public function testMutationWithMissingMutationIdIsRejected(): void
    {
        $token = $this->loginAsAdmin();

        $this->replay([
            [
                // mutation_id intentionally omitted
                'entity_type' => 'store',
                'operation' => 'CREATE',
                'payload' => ['name' => 'Test'],
            ],
        ], $token);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $result = json_decode($response->getContent(), true)['data'][0];
        self::assertSame('REJECTED', $result['status']);
        self::assertStringContainsString('mutation_id', $result['detail'],
            'Validation error detail must reference the missing mutation_id field');
    }

    public function testUnsupportedEntityTypeIsRejected(): void
    {
        $token = $this->loginAsAdmin();
        $mutationId = 'unsupported-entity-' . bin2hex(random_bytes(4));

        $this->replay([
            [
                'mutation_id' => $mutationId,
                'entity_type' => 'non_existent_entity',
                'operation' => 'CREATE',
                'payload' => [],
            ],
        ], $token);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $result = json_decode($response->getContent(), true)['data'][0];
        self::assertSame('REJECTED', $result['status']);
        self::assertStringContainsStringIgnoringCase('non_existent_entity', $result['detail'] ?? '');
    }

    public function testEmptyMutationsArrayReturns422(): void
    {
        $token = $this->loginAsAdmin();

        $this->client->request('POST', '/api/v1/mutations/replay', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode(['mutations' => []]));

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
    }

    public function testIdempotentReplayReturnsCachedResult(): void
    {
        // The same mutation_id replayed twice returns the original result (not re-processed).
        $token = $this->loginAsAdmin();
        $regionId = $this->createRegion();
        $storeCode = 'IMP' . strtoupper(bin2hex(random_bytes(2)));
        $mutationId = 'idempotent-' . bin2hex(random_bytes(4));

        $mutation = [
            'mutation_id' => $mutationId,
            'client_id' => 'test-idempotent',
            'entity_type' => 'store',
            'operation' => 'CREATE',
            'payload' => [
                'code' => $storeCode,
                'name' => 'Idempotent Store',
                'store_type' => 'STORE',
                'region_id' => $regionId,
            ],
        ];

        // First replay
        $this->replay([$mutation], $token);
        $first = json_decode($this->client->getResponse()->getContent(), true)['data'][0];

        // Second replay with identical mutation_id
        $this->replay([$mutation], $token);
        $second = json_decode($this->client->getResponse()->getContent(), true)['data'][0];

        self::assertSame($first['mutation_id'], $second['mutation_id']);
        self::assertSame($first['status'], $second['status'],
            'Idempotent replay must return the same status as the first replay');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * @param list<array<string, mixed>> $mutations
     */
    private function replay(array $mutations, ?string $token = null): void
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->client->request(
            'POST',
            '/api/v1/mutations/replay',
            [],
            [],
            $headers,
            json_encode(['mutations' => $mutations]),
        );
    }

    private function loginAsAdmin(): string
    {
        return $this->loginAsRole(RoleName::ADMINISTRATOR);
    }

    private function loginAsRole(RoleName $roleName): string
    {
        $suffix = 'beh_mrb_' . (++self::$seq) . '_' . bin2hex(random_bytes(3));
        $password = 'V@lid1Password!';

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername($suffix);
        $user->setDisplayName('MutationReplay Behavior Test User');
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

    private function createRegion(): string
    {
        $suffix = ++self::$seq;
        $region = new MdmRegion();
        $region->setCode('MR' . chr(65 + ($suffix % 26)) . chr(65 + (($suffix * 7) % 26)));
        $region->setName('MutationReplay Test Region ' . $suffix);
        $region->setEffectiveFrom(new \DateTimeImmutable('2025-01-01'));
        $region->setIsActive(true);
        $region->setHierarchyLevel(0);
        $this->em->persist($region);
        $this->em->flush();

        return $region->getId()->toRfc4122();
    }
}
