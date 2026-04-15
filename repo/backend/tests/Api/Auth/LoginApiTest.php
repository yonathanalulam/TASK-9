<?php

declare(strict_types=1);

namespace App\Tests\Api\Auth;

use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRoleAssignment;
use App\Enum\RoleName;
use App\Enum\ScopeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class LoginApiTest extends WebTestCase
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

    public function testLoginWithValidCredentialsReturns200WithTokenAndUserData(): void
    {
        $this->createTestUser('api_login_ok', 'V@lid1Password!');

        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => 'api_login_ok',
            'password' => 'V@lid1Password!',
        ]));

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('data', $data);
        self::assertArrayHasKey('token', $data['data']);
        self::assertNotEmpty($data['data']['token']);
        self::assertArrayHasKey('user', $data['data']);
        self::assertSame('api_login_ok', $data['data']['user']['username']);
        self::assertArrayHasKey('meta', $data);
        self::assertArrayHasKey('request_id', $data['meta']);
        self::assertArrayHasKey('timestamp', $data['meta']);
        self::assertNull($data['error']);
    }

    public function testLoginWithWrongPasswordReturns401WithErrorEnvelope(): void
    {
        $this->createTestUser('api_login_bad', 'V@lid1Password!');

        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => 'api_login_bad',
            'password' => 'wrong-password',
        ]));

        $response = $this->client->getResponse();
        self::assertSame(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertNull($data['data']);
        self::assertArrayHasKey('error', $data);
        self::assertSame('AUTHENTICATION_FAILED', $data['error']['code']);
        self::assertArrayHasKey('meta', $data);
        self::assertArrayHasKey('request_id', $data['meta']);
    }

    public function testLoginWithNonExistentUserReturns401(): void
    {
        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => 'nonexistent_user',
            'password' => 'SomePassword123!',
        ]));

        $response = $this->client->getResponse();
        self::assertSame(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame('AUTHENTICATION_FAILED', $data['error']['code']);
    }

    public function testLoginWithMissingFieldsReturns422(): void
    {
        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $response = $this->client->getResponse();
        self::assertSame(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame('VALIDATION_ERROR', $data['error']['code']);
        self::assertArrayHasKey('username', $data['error']['details']);
        self::assertArrayHasKey('password', $data['error']['details']);
    }

    public function testGetMeWithValidTokenReturnsUserData(): void
    {
        $this->createTestUser('api_me_user', 'V@lid1Password!');
        $token = $this->loginAndGetToken('api_me_user', 'V@lid1Password!');

        $this->client->request('GET', '/api/v1/auth/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('data', $data);
        self::assertSame('api_me_user', $data['data']['username']);
        self::assertNull($data['error']);
    }

    public function testGetMeWithoutTokenReturns401(): void
    {
        $this->client->request('GET', '/api/v1/auth/me');

        $response = $this->client->getResponse();
        self::assertSame(401, $response->getStatusCode());
    }

    public function testLogoutRevokesSession(): void
    {
        $this->createTestUser('api_logout_user', 'V@lid1Password!');
        $token = $this->loginAndGetToken('api_logout_user', 'V@lid1Password!');

        // Verify token works before logout.
        $this->client->request('GET', '/api/v1/auth/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // Logout.
        $this->client->request('POST', '/api/v1/auth/logout', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // Token should now be invalid.
        $this->client->request('GET', '/api/v1/auth/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    private function createTestUser(string $username, string $plainPassword = 'V@lid1Password!'): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setDisplayName('Test ' . $username);
        $user->setStatus('ACTIVE');

        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPasswordHash($hashedPassword);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
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
