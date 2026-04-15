<?php

declare(strict_types=1);

namespace App\Tests\Api\Region;

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

final class RegionCrudApiTest extends WebTestCase
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

    public function testCreateRegionSuccessfully(): void
    {
        $token = $this->loginAsAdmin();

        $this->client->request('POST', '/api/v1/regions', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'code' => 'NORTH',
            'name' => 'North Region',
            'effective_from' => '2025-01-01',
        ]));

        $response = $this->client->getResponse();
        self::assertSame(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('data', $data);
        self::assertSame('NORTH', $data['data']['code']);
        self::assertSame('North Region', $data['data']['name']);
        self::assertTrue($data['data']['is_active']);
    }

    public function testInvalidRegionCodeLowercaseReturns422(): void
    {
        $token = $this->loginAsAdmin();

        $this->client->request('POST', '/api/v1/regions', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'code' => 'north',
            'name' => 'Invalid Region',
            'effective_from' => '2025-01-01',
        ]));

        $response = $this->client->getResponse();
        self::assertSame(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame('VALIDATION_ERROR', $data['error']['code']);
        self::assertStringContainsString('Region code must match', $data['error']['message']);
    }

    public function testDuplicateRegionCodeReturns422(): void
    {
        $token = $this->loginAsAdmin();

        // Create the first region.
        $this->client->request('POST', '/api/v1/regions', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'code' => 'DUPE',
            'name' => 'First Region',
            'effective_from' => '2025-01-01',
        ]));
        self::assertSame(201, $this->client->getResponse()->getStatusCode());

        // Attempt to create a second region with the same code.
        $this->client->request('POST', '/api/v1/regions', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'code' => 'DUPE',
            'name' => 'Duplicate Region',
            'effective_from' => '2025-01-01',
        ]));

        $response = $this->client->getResponse();
        self::assertSame(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame('VALIDATION_ERROR', $data['error']['code']);
        self::assertStringContainsString('already in use', $data['error']['message']);
    }

    public function testListRegionsReturnsPaginatedList(): void
    {
        $token = $this->loginAsAdmin();

        // Create a couple of regions.
        foreach (['LISTA', 'LISTB'] as $code) {
            $this->client->request('POST', '/api/v1/regions', [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ], json_encode([
                'code' => $code,
                'name' => 'Region ' . $code,
                'effective_from' => '2025-01-01',
            ]));
        }

        $this->client->request('GET', '/api/v1/regions?per_page=10', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('data', $data);
        self::assertIsArray($data['data']);
        self::assertGreaterThanOrEqual(2, count($data['data']));
        self::assertArrayHasKey('pagination', $data['meta']);
        self::assertArrayHasKey('total', $data['meta']['pagination']);
    }

    public function testCloseRegionWithChildReassignmentsWorks(): void
    {
        $token = $this->loginAsAdmin();

        // Create a parent region.
        $this->client->request('POST', '/api/v1/regions', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'code' => 'PAREN',
            'name' => 'Parent Region',
            'effective_from' => '2025-01-01',
        ]));

        $parentData = json_decode($this->client->getResponse()->getContent(), true);
        $parentId = $parentData['data']['id'];

        // Create a child region under the parent.
        $this->client->request('POST', '/api/v1/regions', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'code' => 'CHILD',
            'name' => 'Child Region',
            'parent_id' => $parentId,
            'effective_from' => '2025-01-01',
        ]));

        $childData = json_decode($this->client->getResponse()->getContent(), true);
        $childId = $childData['data']['id'];

        // Create a new parent to reassign the child to.
        $this->client->request('POST', '/api/v1/regions', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'code' => 'NEWPR',
            'name' => 'New Parent Region',
            'effective_from' => '2025-01-01',
        ]));

        $newParentData = json_decode($this->client->getResponse()->getContent(), true);
        $newParentId = $newParentData['data']['id'];

        // Close the original parent with child reassignment.
        $this->client->request('POST', '/api/v1/regions/' . $parentId . '/close', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'child_reassignments' => [
                $childId => $newParentId,
            ],
        ]));

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('closed successfully', $data['data']['message']);

        // Verify the child was reassigned.
        $this->client->request('GET', '/api/v1/regions/' . $childId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $childDetail = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame($newParentId, $childDetail['data']['parent_id']);
    }

    // -----------------------------------------------------------------------
    // Helper methods
    // -----------------------------------------------------------------------

    private function loginAsAdmin(): string
    {
        static $counter = 0;
        $counter++;

        $suffix = 'region_admin_' . $counter;

        $user = $this->createTestUser($suffix);
        $grantor = $user;
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
