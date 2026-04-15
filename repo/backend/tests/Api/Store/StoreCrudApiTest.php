<?php

declare(strict_types=1);

namespace App\Tests\Api\Store;

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

final class StoreCrudApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
    }

    public function testCreateStoreSuccessfullyReturns201(): void
    {
        $token = $this->loginAsAdmin();
        $region = $this->createRegion('STCR');

        $this->client->request('POST', '/api/v1/stores', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'code' => 'STORE-001',
            'name' => 'Test Store One',
            'store_type' => 'STORE',
            'region_id' => $region->getId()->toRfc4122(),
        ]));

        $response = $this->client->getResponse();
        self::assertSame(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('data', $data);
        self::assertSame('STORE-001', $data['data']['code']);
        self::assertSame('Test Store One', $data['data']['name']);
        self::assertSame('STORE', $data['data']['store_type']);
        self::assertNull($data['error']);
    }

    public function testCreateStoreWithDuplicateCodeReturns422(): void
    {
        $token = $this->loginAsAdmin();
        $region = $this->createRegion('STDC');

        // Create the first store.
        $this->client->request('POST', '/api/v1/stores', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'code' => 'DUP-001',
            'name' => 'First Store',
            'store_type' => 'STORE',
            'region_id' => $region->getId()->toRfc4122(),
        ]));
        self::assertSame(201, $this->client->getResponse()->getStatusCode());

        // Attempt to create a second store with the same code.
        $this->client->request('POST', '/api/v1/stores', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'code' => 'DUP-001',
            'name' => 'Duplicate Store',
            'store_type' => 'STORE',
            'region_id' => $region->getId()->toRfc4122(),
        ]));

        $response = $this->client->getResponse();
        self::assertSame(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame('VALIDATION_ERROR', $data['error']['code']);
    }

    public function testListStoresReturnsPaginatedList(): void
    {
        $token = $this->loginAsAdmin();
        $region = $this->createRegion('STLS');

        // Create two stores.
        foreach (['LIST-A', 'LIST-B'] as $code) {
            $this->client->request('POST', '/api/v1/stores', [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ], json_encode([
                'code' => $code,
                'name' => 'Store ' . $code,
                'store_type' => 'STORE',
                'region_id' => $region->getId()->toRfc4122(),
            ]));
        }

        $this->client->request('GET', '/api/v1/stores?per_page=10', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('data', $data);
        self::assertIsArray($data['data']);
        self::assertGreaterThanOrEqual(2, count($data['data']));
        self::assertArrayHasKey('pagination', $data['meta']);
        self::assertArrayHasKey('page', $data['meta']['pagination']);
        self::assertArrayHasKey('per_page', $data['meta']['pagination']);
        self::assertArrayHasKey('total', $data['meta']['pagination']);
        self::assertArrayHasKey('total_pages', $data['meta']['pagination']);
    }

    public function testShowStoreReturnsStoreDetail(): void
    {
        $token = $this->loginAsAdmin();
        $region = $this->createRegion('STSH');

        // Create a store.
        $this->client->request('POST', '/api/v1/stores', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'code' => 'SHOW-001',
            'name' => 'Show Store',
            'store_type' => 'DARK_STORE',
            'region_id' => $region->getId()->toRfc4122(),
        ]));

        $createData = json_decode($this->client->getResponse()->getContent(), true);
        $storeId = $createData['data']['id'];

        $this->client->request('GET', '/api/v1/stores/' . $storeId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame('SHOW-001', $data['data']['code']);
        self::assertSame('DARK_STORE', $data['data']['store_type']);
    }

    public function testUpdateStoreWithIfMatchHeaderSucceeds(): void
    {
        $token = $this->loginAsAdmin();
        $region = $this->createRegion('STUP');

        // Create a store.
        $this->client->request('POST', '/api/v1/stores', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'code' => 'UPD-001',
            'name' => 'Update Store',
            'store_type' => 'STORE',
            'region_id' => $region->getId()->toRfc4122(),
        ]));

        $createData = json_decode($this->client->getResponse()->getContent(), true);
        $storeId = $createData['data']['id'];
        $version = $createData['data']['version'];

        $this->client->request('PUT', '/api/v1/stores/' . $storeId, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_IF_MATCH' => '"' . $version . '"',
        ], json_encode([
            'name' => 'Updated Store Name',
        ]));

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame('Updated Store Name', $data['data']['name']);
    }

    public function testUpdateStoreWithoutIfMatchReturns428(): void
    {
        $token = $this->loginAsAdmin();
        $region = $this->createRegion('STNO');

        // Create a store.
        $this->client->request('POST', '/api/v1/stores', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'code' => 'NOMATCH-01',
            'name' => 'No Match Store',
            'store_type' => 'STORE',
            'region_id' => $region->getId()->toRfc4122(),
        ]));

        $createData = json_decode($this->client->getResponse()->getContent(), true);
        $storeId = $createData['data']['id'];

        // Attempt update without If-Match header.
        $this->client->request('PUT', '/api/v1/stores/' . $storeId, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'name' => 'Should Fail',
        ]));

        $response = $this->client->getResponse();
        self::assertSame(428, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame('MISSING_IF_MATCH', $data['error']['code']);
    }

    public function testUnauthorizedUserCannotCreateStore(): void
    {
        // Create a regular user without the administrator role.
        $user = $this->createTestUser('nonadmin_store');
        $grantor = $this->createTestUser('nonadmin_grantor');
        $role = $this->getOrCreateRole(RoleName::DISPATCHER);
        $this->createAssignment($user, $role, ScopeType::GLOBAL, null, $grantor);

        $token = $this->loginAndGetToken('nonadmin_store', 'V@lid1Password!');

        $this->client->request('POST', '/api/v1/stores', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'code' => 'UNAUTH-01',
            'name' => 'Unauthorized Store',
            'store_type' => 'STORE',
            'region_id' => '00000000-0000-0000-0000-000000000001',
        ]));

        $response = $this->client->getResponse();
        self::assertSame(403, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Helper methods
    // -----------------------------------------------------------------------

    private function loginAsAdmin(): string
    {
        static $counter = 0;
        $counter++;

        $suffix = 'store_admin_' . $counter;

        $user = $this->createTestUser($suffix);
        $grantor = $user; // Self-grant for tests.
        $role = $this->getOrCreateRole(RoleName::ADMINISTRATOR);
        $this->createAssignment($user, $role, ScopeType::GLOBAL, null, $grantor);

        return $this->loginAndGetToken($suffix, 'V@lid1Password!');
    }

    private function loginAndGetToken(string $username, string $password): string
    {
        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $username,
            'password' => $password,
        ]));

        $data = json_decode($this->client->getResponse()->getContent(), true);

        return $data['data']['token'];
    }

    private function createTestUser(string $username): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setDisplayName('Test ' . $username);
        $user->setStatus('ACTIVE');

        $hashedPassword = $this->passwordHasher->hashPassword($user, 'V@lid1Password!');
        $user->setPasswordHash($hashedPassword);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createRegion(string $code): MdmRegion
    {
        $region = new MdmRegion();
        $region->setCode($code);
        $region->setName('Region ' . $code);
        $region->setEffectiveFrom(new \DateTimeImmutable('-30 days'));
        $region->setIsActive(true);

        $this->em->persist($region);
        $this->em->flush();

        return $region;
    }

    private function getOrCreateRole(RoleName $roleName): Role
    {
        $existing = $this->em->getRepository(Role::class)->findOneBy(['name' => $roleName->value]);
        if ($existing !== null) {
            return $existing;
        }

        $role = new Role();
        $role->setName($roleName->value);
        $role->setDisplayName(ucwords(str_replace('_', ' ', $roleName->value)));
        $role->setIsSystem(true);

        $this->em->persist($role);
        $this->em->flush();

        return $role;
    }

    private function createAssignment(
        User $user,
        Role $role,
        ScopeType $scopeType,
        ?string $scopeId,
        User $grantedBy,
    ): UserRoleAssignment {
        $assignment = new UserRoleAssignment();
        $assignment->setUser($user);
        $assignment->setRole($role);
        $assignment->setScopeType($scopeType);
        $assignment->setScopeId($scopeId);
        $assignment->setEffectiveFrom(new \DateTimeImmutable('-1 day'));
        $assignment->setGrantedBy($grantedBy);

        $this->em->persist($assignment);
        $this->em->flush();

        return $assignment;
    }
}
