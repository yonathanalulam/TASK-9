<?php

declare(strict_types=1);

namespace App\Tests\Api\Coverage;

use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRoleAssignment;
use App\Enum\RoleName;
use App\Enum\ScopeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ScrapingCoverageTest extends WebTestCase
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

        $suffix = 'scr_' . (++self::$counter) . '_' . bin2hex(random_bytes(4));
        $username = 'admin_' . $suffix;
        $password = 'V@lid1Password!';

        $user = new User();
        $user->setUsername($username);
        $user->setDisplayName('Admin ' . $suffix);
        $user->setStatus('ACTIVE');
        $user->setPasswordHash($hasher->hashPassword($user, $password));
        $em->persist($user);

        $role = $em->getRepository(Role::class)->findOneBy(['name' => RoleName::ADMINISTRATOR->value]);
        if ($role === null) {
            $role = new Role();
            $role->setName(RoleName::ADMINISTRATOR->value);
            $role->setDisplayName('Administrator');
            $role->setIsSystem(true);
            $em->persist($role);
            $em->flush();
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
        ], json_encode(['username' => $username, 'password' => $password]));

        $data = json_decode($this->client->getResponse()->getContent(), true);

        return $data['data']['token'];
    }

    private function api(string $method, string $url, ?string $token = null, ?array $body = null): int
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->client->request($method, $url, [], [], $headers, $body !== null ? json_encode($body) : null);

        return $this->client->getResponse()->getStatusCode();
    }

    private function createSourceAndGetId(string $token): string
    {
        $this->api('POST', '/api/v1/sources', $token, [
            'name' => 'CovSrc_' . bin2hex(random_bytes(4)),
            'base_url' => 'http://localhost',
            'scrape_type' => 'HTML',
        ]);
        $data = json_decode($this->client->getResponse()->getContent(), true);

        return $data['data']['id'];
    }

    public function testGetSourceShowReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $id = $this->createSourceAndGetId($token);

        $status = $this->api('GET', '/api/v1/sources/' . $id, $token);

        self::assertSame(200, $status);
    }

    public function testPutSourceUpdateReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $id = $this->createSourceAndGetId($token);

        $status = $this->api('PUT', '/api/v1/sources/' . $id, $token, [
            'name' => 'Updated',
        ]);

        self::assertSame(200, $status);
    }

    public function testPostSourcePauseReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $id = $this->createSourceAndGetId($token);

        $status = $this->api('POST', '/api/v1/sources/' . $id . '/pause', $token);

        self::assertSame(200, $status);
    }

    public function testPostSourceResumeReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $id = $this->createSourceAndGetId($token);

        // Pause first, then resume
        $this->api('POST', '/api/v1/sources/' . $id . '/pause', $token);
        $status = $this->api('POST', '/api/v1/sources/' . $id . '/resume', $token);

        self::assertSame(200, $status);
    }

    public function testPostSourceDisableReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $id = $this->createSourceAndGetId($token);

        $status = $this->api('POST', '/api/v1/sources/' . $id . '/disable', $token);

        self::assertSame(200, $status);
    }

    public function testGetSourceHealthReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $id = $this->createSourceAndGetId($token);

        $status = $this->api('GET', '/api/v1/sources/' . $id . '/health', $token);

        self::assertSame(200, $status);
    }

    public function testGetSourcesHealthDashboardReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $status = $this->api('GET', '/api/v1/sources/health/dashboard', $token);

        self::assertSame(200, $status);
    }

    public function testGetScrapeRunsListReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $status = $this->api('GET', '/api/v1/scrape-runs', $token);

        self::assertSame(200, $status);
    }

    public function testGetScrapeRunShowReturns404ForNonExistentId(): void
    {
        $token = $this->loginAsAdmin();
        $status = $this->api('GET', '/api/v1/scrape-runs/00000000-0000-0000-0000-000000000001', $token);

        self::assertSame(404, $status);
    }
}
