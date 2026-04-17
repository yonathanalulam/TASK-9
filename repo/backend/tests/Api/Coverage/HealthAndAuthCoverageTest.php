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

final class HealthAndAuthCoverageTest extends WebTestCase
{
    private KernelBrowser $client;
    private static int $counter = 0;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private string $lastCreatedUsername = '';

    private function loginAsAdmin(): string
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        self::$counter++;
        $username = 'cov_admin_' . self::$counter . '_' . bin2hex(random_bytes(2));
        $this->lastCreatedUsername = $username;

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

    public function testHealthEndpointReturns200(): void
    {
        $status = $this->api('GET', '/api/v1/health');

        self::assertSame(200, $status);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);
        self::assertArrayHasKey('status', $body['data']);
        self::assertArrayHasKey('meta', $body);
        self::assertNull($body['error']);
    }

    /**
     * Deep behavior test: the password is actually rotated, the change is
     * persisted, the old password no longer authenticates, and the new
     * password does. Replaces a previous shallow status-only check.
     */
    public function testChangePasswordRotatesPasswordAndInvalidatesOldCredential(): void
    {
        ['username' => $username, 'token' => $token] = $this->createAdminAndLogin();

        $newPassword = 'N3w$ecureRotatedP@ss1';

        $status = $this->api('POST', '/api/v1/auth/change-password', $token, [
            'current_password' => 'V@lid1Password!',
            'new_password' => $newPassword,
        ]);

        self::assertSame(200, $status, 'Valid change-password call must return exactly 200');

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertNull($body['error']);
        self::assertSame('Password changed successfully.', $body['data']['message']);

        // Old password must no longer authenticate.
        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => $username, 'password' => 'V@lid1Password!']));
        self::assertSame(
            401,
            $this->client->getResponse()->getStatusCode(),
            'Old credential must be rejected after rotation',
        );

        // New password must authenticate and yield a fresh session token.
        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => $username, 'password' => $newPassword]));
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $loginBody = json_decode($this->client->getResponse()->getContent(), true);
        self::assertNotEmpty($loginBody['data']['token'] ?? '');
    }

    public function testChangePasswordRejectsIncorrectCurrentPassword(): void
    {
        ['token' => $token] = $this->createAdminAndLogin();

        $status = $this->api('POST', '/api/v1/auth/change-password', $token, [
            'current_password' => 'WrongPasswordValue!9',
            'new_password' => 'AnotherV@l1dValue!',
        ]);

        self::assertSame(400, $status);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('INVALID_PASSWORD', $body['error']['code']);
    }

    public function testChangePasswordRejectsWeakNewPasswordWithPolicyError(): void
    {
        ['token' => $token] = $this->createAdminAndLogin();

        $status = $this->api('POST', '/api/v1/auth/change-password', $token, [
            'current_password' => 'V@lid1Password!',
            'new_password' => 'short',
        ]);

        self::assertSame(422, $status);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('PASSWORD_POLICY_VIOLATION', $body['error']['code']);
    }

    public function testChangePasswordRejectsMissingFieldsWithValidationError(): void
    {
        ['token' => $token] = $this->createAdminAndLogin();

        $status = $this->api('POST', '/api/v1/auth/change-password', $token, [
            'current_password' => '',
        ]);

        self::assertSame(422, $status);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('VALIDATION_ERROR', $body['error']['code']);
        self::assertArrayHasKey('current_password', $body['error']['details'] ?? []);
        self::assertArrayHasKey('new_password', $body['error']['details'] ?? []);
    }

    public function testChangePasswordRequiresAuthentication(): void
    {
        $status = $this->api('POST', '/api/v1/auth/change-password', null, [
            'current_password' => 'V@lid1Password!',
            'new_password' => 'AnyN3wV@lue1!Strong',
        ]);

        self::assertContains($status, [401], 'Anonymous request must be rejected with 401');
    }

    /**
     * @return array{username:string, token:string}
     */
    private function createAdminAndLogin(): array
    {
        $token = $this->loginAsAdmin();
        return ['username' => $this->lastCreatedUsername, 'token' => $token];
    }
}
