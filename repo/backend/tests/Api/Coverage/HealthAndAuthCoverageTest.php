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

    public function testChangePasswordEndpointIsRoutable(): void
    {
        $token = $this->loginAsAdmin();

        $status = $this->api('POST', '/api/v1/auth/change-password', $token, [
            'current_password' => 'V@lid1Password!',
            'new_password' => 'N3w$ecurePass!',
        ]);

        self::assertContains($status, [200, 400, 422], 'Expected a handled response');

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);
        self::assertArrayHasKey('meta', $body);
        if ($status === 200) {
            self::assertNull($body['error']);
        } else {
            self::assertNotNull($body['error']);
        }
    }
}
