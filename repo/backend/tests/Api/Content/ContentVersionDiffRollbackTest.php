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
 * End-to-end tests for the content version timeline, diff, and rollback APIs.
 */
final class ContentVersionDiffRollbackTest extends WebTestCase
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

    public function testCreateUpdateAndListVersions(): void
    {
        $token = $this->loginAsAdmin();

        // Step 1: Create content item (v1 is created automatically).
        $this->client->request('POST', '/api/v1/content', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'title' => 'Original Title',
            'body' => 'Original body content for the test item.',
            'author_name' => 'Test Author',
            'content_type' => 'JOB_POST',
            'tags' => ['hiring', 'engineering'],
        ]));

        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        $createData = json_decode($this->client->getResponse()->getContent(), true);
        $contentId = $createData['data']['id'];
        $version = $createData['data']['version'];

        // Step 2: Update content item (creates v2).
        $this->client->request('PUT', '/api/v1/content/' . $contentId, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_IF_MATCH' => '"' . $version . '"',
        ], json_encode([
            'title' => 'Updated Title',
            'body' => 'Updated body content with new information.',
            'change_reason' => 'Correcting the initial content.',
        ]));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // Step 3: Verify 2 versions returned.
        $this->client->request('GET', '/api/v1/content/' . $contentId . '/versions', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $versionsData = json_decode($this->client->getResponse()->getContent(), true);

        self::assertArrayHasKey('data', $versionsData);
        self::assertCount(2, $versionsData['data']);
    }

    public function testDiffBetweenTwoVersionsReturnsChangedFields(): void
    {
        $token = $this->loginAsAdmin();

        // Create and update to produce 2 versions.
        $this->client->request('POST', '/api/v1/content', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'title' => 'Diff Title V1',
            'body' => 'Diff body V1 content.',
            'author_name' => 'Diff Author',
            'content_type' => 'OPERATIONAL_NOTICE',
        ]));

        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        $createData = json_decode($this->client->getResponse()->getContent(), true);
        $contentId = $createData['data']['id'];
        $version = $createData['data']['version'];

        $this->client->request('PUT', '/api/v1/content/' . $contentId, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_IF_MATCH' => '"' . $version . '"',
        ], json_encode([
            'title' => 'Diff Title V2',
            'body' => 'Diff body V2 content updated.',
        ]));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // Get versions to find IDs.
        $this->client->request('GET', '/api/v1/content/' . $contentId . '/versions', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $versionsData = json_decode($this->client->getResponse()->getContent(), true);
        $versions = $versionsData['data'];

        // Versions are ordered DESC by version_number, so [0] is v2, [1] is v1.
        $v1Id = $versions[1]['id'];
        $v2Id = $versions[0]['id'];

        // Get diff.
        $this->client->request(
            'GET',
            '/api/v1/content/' . $contentId . '/versions/' . $v1Id . '/diff/' . $v2Id,
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token],
        );

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $diffData = json_decode($this->client->getResponse()->getContent(), true);

        self::assertArrayHasKey('data', $diffData);
        self::assertArrayHasKey('changes', $diffData['data']);
        self::assertNotEmpty($diffData['data']['changes']);

        // Verify that title and body are among the changed fields.
        $changedFields = array_column($diffData['data']['changes'], 'field');
        self::assertContains('title', $changedFields);
        self::assertContains('body', $changedFields);
    }

    public function testRollbackWithValidReasonReturns200(): void
    {
        $token = $this->loginAsAdmin();

        // Create content.
        $this->client->request('POST', '/api/v1/content', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'title' => 'Rollback Original',
            'body' => 'Original body for rollback testing.',
            'author_name' => 'Rollback Author',
            'content_type' => 'JOB_POST',
        ]));

        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        $createData = json_decode($this->client->getResponse()->getContent(), true);
        $contentId = $createData['data']['id'];
        $version = $createData['data']['version'];

        // Update it.
        $this->client->request('PUT', '/api/v1/content/' . $contentId, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_IF_MATCH' => '"' . $version . '"',
        ], json_encode([
            'title' => 'Rollback Updated',
            'body' => 'Updated body that we will roll back from.',
        ]));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // Get v1 version ID.
        $this->client->request('GET', '/api/v1/content/' . $contentId . '/versions', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $versionsData = json_decode($this->client->getResponse()->getContent(), true);
        // v1 is the last element (DESC order)
        $v1Id = $versionsData['data'][count($versionsData['data']) - 1]['id'];

        // Rollback to v1.
        $this->client->request('POST', '/api/v1/content/' . $contentId . '/rollback', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'target_version_id' => $v1Id,
            'reason' => 'Reverting to the original version because the update was incorrect.',
        ]));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $rollbackData = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('ROLLED_BACK', $rollbackData['data']['status']);
        self::assertSame('Rollback Original', $rollbackData['data']['title']);
    }

    public function testRollbackWithShortReasonReturns422(): void
    {
        $token = $this->loginAsAdmin();

        // Create content.
        $this->client->request('POST', '/api/v1/content', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'title' => 'Short Reason Test',
            'body' => 'Body for short reason rollback test.',
            'author_name' => 'Short Author',
            'content_type' => 'VENDOR_BULLETIN',
        ]));

        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        $createData = json_decode($this->client->getResponse()->getContent(), true);
        $contentId = $createData['data']['id'];
        $version = $createData['data']['version'];

        // Update it.
        $this->client->request('PUT', '/api/v1/content/' . $contentId, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_IF_MATCH' => '"' . $version . '"',
        ], json_encode([
            'title' => 'Short Reason Updated',
        ]));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // Get v1 ID.
        $this->client->request('GET', '/api/v1/content/' . $contentId . '/versions', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $versionsData = json_decode($this->client->getResponse()->getContent(), true);
        $v1Id = $versionsData['data'][count($versionsData['data']) - 1]['id'];

        // Rollback with reason < 10 chars.
        $this->client->request('POST', '/api/v1/content/' . $contentId . '/rollback', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'target_version_id' => $v1Id,
            'reason' => 'too short',  // 9 characters
        ]));

        self::assertSame(422, $this->client->getResponse()->getStatusCode());

        $errorData = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('VALIDATION_ERROR', $errorData['error']['code']);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function loginAsAdmin(): string
    {
        static $counter = 0;
        $counter++;

        $suffix = 'cvdr_admin_' . $counter;

        $user = $this->createTestUser($suffix);
        $grantor = $user;
        $role = $this->getOrCreateRole(RoleName::ADMINISTRATOR);
        $this->createAssignment($user, $role, ScopeType::GLOBAL, null, $grantor);

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
