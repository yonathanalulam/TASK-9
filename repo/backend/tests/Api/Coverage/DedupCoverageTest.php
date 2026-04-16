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

final class DedupCoverageTest extends WebTestCase
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

        $suffix = 'ded_' . (++self::$counter) . '_' . bin2hex(random_bytes(4));
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

    public function testGetDedupReviewListReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $status = $this->api('GET', '/api/v1/dedup/review', $token);

        self::assertSame(200, $status);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);
        self::assertIsArray($body['data']);
        self::assertNull($body['error']);
    }

    public function testPostDedupMergeReturns404ForNonExistentItem(): void
    {
        $token = $this->loginAsAdmin();
        $status = $this->api('POST', '/api/v1/dedup/review/00000000-0000-0000-0000-000000000001/merge', $token);

        self::assertSame(404, $status);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $body);
        self::assertNotNull($body['error']);
        self::assertArrayHasKey('code', $body['error']);
    }

    public function testPostDedupRejectReturns404ForNonExistentItem(): void
    {
        $token = $this->loginAsAdmin();
        $status = $this->api('POST', '/api/v1/dedup/review/00000000-0000-0000-0000-000000000001/reject', $token);

        self::assertSame(404, $status);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $body);
        self::assertNotNull($body['error']);
        self::assertArrayHasKey('code', $body['error']);
    }

    public function testPostDedupUnmergeReturns404ForNonExistentEvent(): void
    {
        $token = $this->loginAsAdmin();
        $status = $this->api('POST', '/api/v1/dedup/unmerge/00000000-0000-0000-0000-000000000001', $token);

        self::assertSame(404, $status);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $body);
        self::assertNotNull($body['error']);
        self::assertArrayHasKey('code', $body['error']);
    }
}
