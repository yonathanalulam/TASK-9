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

final class UserCoverageTest extends WebTestCase
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

    private function api(string $method, string $url, ?string $token = null, ?array $body = null, array $extraHeaders = []): int
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($token) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        $headers = array_merge($headers, $extraHeaders);
        $this->client->request($method, $url, [], [], $headers, $body ? json_encode($body) : null);
        return $this->client->getResponse()->getStatusCode();
    }

    private function jsonResponse(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true);
    }

    public function testCreateUserReturns201(): void
    {
        $token = $this->loginAsAdmin();
        $unique = 'cov_user_' . bin2hex(random_bytes(3));

        $status = $this->api('POST', '/api/v1/users', $token, [
            'username' => $unique,
            'display_name' => 'Test',
            'password' => 'V@lid1Password!',
        ]);

        self::assertSame(201, $status);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);
        self::assertArrayHasKey('id', $body['data']);
        self::assertArrayHasKey('meta', $body);
        self::assertNull($body['error']);
    }

    public function testListUsersReturns200(): void
    {
        $token = $this->loginAsAdmin();

        $status = $this->api('GET', '/api/v1/users', $token);

        self::assertSame(200, $status);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);
        self::assertIsArray($body['data']);
        self::assertArrayHasKey('meta', $body);
        self::assertArrayHasKey('pagination', $body['meta']);
        self::assertNull($body['error']);
    }

    public function testShowUserReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $unique = 'cov_show_' . bin2hex(random_bytes(3));

        // Create a user first.
        $this->api('POST', '/api/v1/users', $token, [
            'username' => $unique,
            'display_name' => 'Show Test',
            'password' => 'V@lid1Password!',
        ]);
        $userId = $this->jsonResponse()['data']['id'];

        $status = $this->api('GET', '/api/v1/users/' . $userId, $token);

        self::assertSame(200, $status);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);
        self::assertArrayHasKey('id', $body['data']);
        self::assertArrayHasKey('meta', $body);
        self::assertNull($body['error']);
    }

    public function testUpdateUserReturns200Or428(): void
    {
        $token = $this->loginAsAdmin();
        $unique = 'cov_upd_' . bin2hex(random_bytes(3));

        // Create a user first.
        $this->api('POST', '/api/v1/users', $token, [
            'username' => $unique,
            'display_name' => 'Update Test',
            'password' => 'V@lid1Password!',
        ]);
        $data = $this->jsonResponse()['data'];
        $userId = $data['id'];

        $status = $this->api('PUT', '/api/v1/users/' . $userId, $token, [
            'display_name' => 'Updated',
        ], ['HTTP_IF_MATCH' => '1']);

        self::assertContains($status, [200, 428], 'Expected 200 or 428');

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);
        self::assertArrayHasKey('meta', $body);
        if ($status === 200) {
            self::assertNull($body['error']);
        } else {
            self::assertNotNull($body['error']);
        }
    }

    public function testDeactivateUserReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $unique = 'cov_deact_' . bin2hex(random_bytes(3));

        // Create a user first.
        $this->api('POST', '/api/v1/users', $token, [
            'username' => $unique,
            'display_name' => 'Deactivate Test',
            'password' => 'V@lid1Password!',
        ]);
        $userId = $this->jsonResponse()['data']['id'];

        $status = $this->api('PATCH', '/api/v1/users/' . $userId . '/deactivate', $token);

        self::assertSame(200, $status);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);
        self::assertArrayHasKey('meta', $body);
        self::assertNull($body['error']);
    }
}
