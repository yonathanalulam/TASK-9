<?php

declare(strict_types=1);

namespace App\Tests\Api\Content;

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
 * Contract test for the content version diff API endpoint.
 *
 * Verifies the response shape: top-level keys v1, v2, changes;
 * v1/v2 each contain id and version_number; changes is an array of
 * objects with field, before, after.
 */
final class ContentDiffContractTest extends WebTestCase
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

    public function testDiffResponseContractMatchesExpectedShape(): void
    {
        $token = $this->loginAsAdmin();

        // Step 1: Create a content item (v1).
        $this->client->request('POST', '/api/v1/content', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'title' => 'Diff Contract V1',
            'body' => 'Original body for contract test.',
            'author_name' => 'Contract Author',
            'content_type' => 'JOB_POST',
        ]));

        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        $createData = json_decode($this->client->getResponse()->getContent(), true);
        $contentId = $createData['data']['id'];
        $version = $createData['data']['version'];

        // Step 2: Update the content item (creates v2).
        $this->client->request('PUT', '/api/v1/content/' . $contentId, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_IF_MATCH' => '"' . $version . '"',
        ], json_encode([
            'title' => 'Diff Contract V2',
            'body' => 'Updated body for contract test.',
            'change_reason' => 'Testing diff contract shape.',
        ]));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // Step 3: Get the version timeline to obtain version IDs.
        $this->client->request('GET', '/api/v1/content/' . $contentId . '/versions', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $versionsData = json_decode($this->client->getResponse()->getContent(), true);

        self::assertArrayHasKey('data', $versionsData);
        self::assertCount(2, $versionsData['data']);

        // Versions are DESC by version_number: [0] = v2, [1] = v1.
        $v1Id = $versionsData['data'][1]['id'];
        $v2Id = $versionsData['data'][0]['id'];

        // Step 4: Call the diff endpoint.
        $this->client->request(
            'GET',
            '/api/v1/content/' . $contentId . '/versions/' . $v1Id . '/diff/' . $v2Id,
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token],
        );

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $diffPayload = json_decode($this->client->getResponse()->getContent(), true);

        self::assertArrayHasKey('data', $diffPayload);
        $diffData = $diffPayload['data'];

        // Top-level keys: v1, v2, changes.
        self::assertArrayHasKey('v1', $diffData);
        self::assertArrayHasKey('v2', $diffData);
        self::assertArrayHasKey('changes', $diffData);

        // v1 shape.
        self::assertArrayHasKey('id', $diffData['v1']);
        self::assertArrayHasKey('version_number', $diffData['v1']);

        // v2 shape.
        self::assertArrayHasKey('id', $diffData['v2']);
        self::assertArrayHasKey('version_number', $diffData['v2']);

        // changes is an array of objects.
        self::assertIsArray($diffData['changes']);
        self::assertNotEmpty($diffData['changes']);

        foreach ($diffData['changes'] as $change) {
            self::assertArrayHasKey('field', $change);
            self::assertArrayHasKey('before', $change);
            self::assertArrayHasKey('after', $change);
        }

        // Verify that title and body appear among the changed fields.
        $changedFields = array_column($diffData['changes'], 'field');
        self::assertContains('title', $changedFields);
        self::assertContains('body', $changedFields);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function loginAsAdmin(): string
    {
        static $counter = 0;
        $counter++;

        $suffix = 'diff_contract_admin_' . $counter;

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
