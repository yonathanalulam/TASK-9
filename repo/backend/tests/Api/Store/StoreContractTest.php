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

/**
 * Contract test for the Store API.
 *
 * Verifies that POST and GET endpoints return all expected fields
 * in the serialized response.
 */
final class StoreContractTest extends WebTestCase
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

    public function testCreateStoreResponseContainsAllExpectedFields(): void
    {
        $token = $this->loginAsAdmin();
        $region = $this->createRegion('SCON');

        $this->client->request('POST', '/api/v1/stores', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'code' => 'CONTRACT-01',
            'name' => 'Store Contract Test',
            'store_type' => 'STORE',
            'region_id' => $region->getId()->toRfc4122(),
            'timezone' => 'America/New_York',
            'address_line_1' => '123 Main St',
            'address_line_2' => 'Suite 100',
            'city' => 'New York',
            'postal_code' => '10001',
            'latitude' => '40.7128000',
            'longitude' => '-74.0060000',
        ]));

        $response = $this->client->getResponse();
        self::assertSame(201, $response->getStatusCode());

        $payload = json_decode($response->getContent(), true);
        self::assertArrayHasKey('data', $payload);

        $data = $payload['data'];

        $expectedKeys = [
            'id',
            'code',
            'name',
            'store_type',
            'status',
            'region_id',
            'timezone',
            'address_line_1',
            'address_line_2',
            'city',
            'postal_code',
            'latitude',
            'longitude',
            'is_active',
            'created_at',
            'updated_at',
            'version',
        ];

        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $data, sprintf('Expected key "%s" in store response.', $key));
        }

        // Verify some values.
        self::assertSame('CONTRACT-01', $data['code']);
        self::assertSame('Store Contract Test', $data['name']);
        self::assertSame('STORE', $data['store_type']);
        self::assertSame($region->getId()->toRfc4122(), $data['region_id']);
        self::assertTrue($data['is_active']);
        self::assertSame(1, $data['version']);
    }

    public function testGetStoreResponseContainsAllExpectedFields(): void
    {
        $token = $this->loginAsAdmin();
        $region = $this->createRegion('SCOG');

        // Create a store first.
        $this->client->request('POST', '/api/v1/stores', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'code' => 'CONTRACT-02',
            'name' => 'Get Contract Test',
            'store_type' => 'DARK_STORE',
            'region_id' => $region->getId()->toRfc4122(),
        ]));

        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        $createData = json_decode($this->client->getResponse()->getContent(), true);
        $storeId = $createData['data']['id'];

        // GET the store.
        $this->client->request('GET', '/api/v1/stores/' . $storeId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $payload = json_decode($response->getContent(), true);
        $data = $payload['data'];

        $expectedKeys = [
            'id',
            'code',
            'name',
            'store_type',
            'status',
            'region_id',
            'timezone',
            'address_line_1',
            'address_line_2',
            'city',
            'postal_code',
            'latitude',
            'longitude',
            'is_active',
            'created_at',
            'updated_at',
            'version',
        ];

        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $data, sprintf('Expected key "%s" in GET store response.', $key));
        }

        self::assertSame('CONTRACT-02', $data['code']);
        self::assertSame('DARK_STORE', $data['store_type']);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function loginAsAdmin(): string
    {
        static $counter = 0;
        $counter++;

        $suffix = 'store_contract_admin_' . $counter;

        $user = $this->createTestUser($suffix);
        $role = $this->getOrCreateRole(RoleName::ADMINISTRATOR);
        $this->createAssignment($user, $role, ScopeType::GLOBAL, null, $user);

        return $this->loginAndGetToken($suffix, 'V@lid1Password!');
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
}
