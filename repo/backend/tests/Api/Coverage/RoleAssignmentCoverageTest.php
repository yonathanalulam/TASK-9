<?php

declare(strict_types=1);

namespace App\Tests\Api\Coverage;

use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRoleAssignment;
use App\Enum\ScopeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RoleAssignmentCoverageTest extends WebTestCase
{
    private KernelBrowser $client;
    private static int $counter = 0;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function loginAsAdmin(): string
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        self::$counter++;
        $username = 'cov_admin_' . self::$counter . '_' . bin2hex(random_bytes(2));

        $user = new User();
        $user->setUsername($username);
        $user->setDisplayName('Coverage Admin');
        $user->setPasswordHash($hasher->hashPassword($user, 'V@lid1Password!'));
        $em->persist($user);

        $role = $em->getRepository(Role::class)->findOneBy(['name' => 'administrator']);
        if ($role === null) {
            $role = new Role();
            $role->setName('administrator');
            $role->setDisplayName('Administrator');
            $em->persist($role);
        }

        $assignment = new UserRoleAssignment();
        $assignment->setUser($user);
        $assignment->setRole($role);
        $assignment->setScopeType(ScopeType::GLOBAL);
        $assignment->setEffectiveFrom(new \DateTimeImmutable('-1 year'));
        $assignment->setGrantedBy($user);
        $em->persist($assignment);
        $em->flush();

        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => $username, 'password' => 'V@lid1Password!']));

        $data = json_decode($this->client->getResponse()->getContent(), true);
        return $data['data']['token'];
    }

    private function api(string $method, string $url, ?string $token = null, ?array $body = null): int
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($token) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        $this->client->request($method, $url, [], [], $headers, $body ? json_encode($body) : null);
        return $this->client->getResponse()->getStatusCode();
    }

    private function jsonResponse(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true);
    }

    private function ensureRoleExists(string $name, string $displayName): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $role = $em->getRepository(Role::class)->findOneBy(['name' => $name]);
        if ($role === null) {
            $role = new Role();
            $role->setName($name);
            $role->setDisplayName($displayName);
            $em->persist($role);
            $em->flush();
        }
    }

    public function testCreateRoleAssignmentReturns201(): void
    {
        $token = $this->loginAsAdmin();
        $this->ensureRoleExists('dispatcher', 'Dispatcher');

        // Create a target user via API.
        $unique = 'cov_ra_user_' . bin2hex(random_bytes(3));
        $this->api('POST', '/api/v1/users', $token, [
            'username' => $unique,
            'display_name' => 'RA Target',
            'password' => 'V@lid1Password!',
        ]);
        $userId = $this->jsonResponse()['data']['id'];

        $status = $this->api('POST', '/api/v1/users/' . $userId . '/role-assignments', $token, [
            'role_name' => 'dispatcher',
            'scope_type' => 'GLOBAL',
            'effective_from' => '2025-01-01',
        ]);

        self::assertSame(201, $status);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);
        self::assertArrayHasKey('id', $body['data']);
        self::assertArrayHasKey('meta', $body);
        self::assertNull($body['error']);
    }

    public function testListRoleAssignmentsReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $this->ensureRoleExists('dispatcher', 'Dispatcher');

        // Create a target user via API.
        $unique = 'cov_ra_list_' . bin2hex(random_bytes(3));
        $this->api('POST', '/api/v1/users', $token, [
            'username' => $unique,
            'display_name' => 'RA List Target',
            'password' => 'V@lid1Password!',
        ]);
        $userId = $this->jsonResponse()['data']['id'];

        $status = $this->api('GET', '/api/v1/users/' . $userId . '/role-assignments', $token);

        self::assertSame(200, $status);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);
        self::assertIsArray($body['data']);
        self::assertArrayHasKey('meta', $body);
        self::assertNull($body['error']);
    }

    public function testDeleteRoleAssignmentReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $this->ensureRoleExists('dispatcher', 'Dispatcher');

        // Create a target user via API.
        $unique = 'cov_ra_del_' . bin2hex(random_bytes(3));
        $this->api('POST', '/api/v1/users', $token, [
            'username' => $unique,
            'display_name' => 'RA Delete Target',
            'password' => 'V@lid1Password!',
        ]);
        $userId = $this->jsonResponse()['data']['id'];

        // Assign a role.
        $this->api('POST', '/api/v1/users/' . $userId . '/role-assignments', $token, [
            'role_name' => 'dispatcher',
            'scope_type' => 'GLOBAL',
            'effective_from' => '2025-01-01',
        ]);
        $assignmentId = $this->jsonResponse()['data']['id'];

        // Need a fresh token because the previous admin session may have been
        // revoked when we changed the target user's roles.
        $token = $this->loginAsAdmin();

        $status = $this->api('DELETE', '/api/v1/users/' . $userId . '/role-assignments/' . $assignmentId, $token);

        self::assertSame(200, $status);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);
        self::assertArrayHasKey('meta', $body);
        self::assertNull($body['error']);
    }
}
