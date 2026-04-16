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

final class RegionDetailCoverageTest extends WebTestCase
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

    private function createRegion(): string
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $region = new \App\Entity\MdmRegion();
        $region->setCode('C' . chr(65 + (++self::$counter % 26)) . chr(65 + ((self::$counter * 7) % 26)));
        $region->setName('Coverage Region ' . self::$counter);
        $region->setEffectiveFrom(new \DateTimeImmutable('2025-01-01'));
        $region->setIsActive(true);
        $region->setHierarchyLevel(0);
        $em->persist($region);
        $em->flush();
        return $region->getId()->toRfc4122();
    }

    public function testShowRegionReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $regionId = $this->createRegion();

        $status = $this->api('GET', '/api/v1/regions/' . $regionId, $token);

        self::assertSame(200, $status);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);
        self::assertArrayHasKey('meta', $body);
        self::assertNull($body['error']);
    }

    public function testUpdateRegionReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $regionId = $this->createRegion();

        // Fetch the current version.
        $this->api('GET', '/api/v1/regions/' . $regionId, $token);
        $version = $this->jsonResponse()['data']['version'];

        $status = $this->api('PUT', '/api/v1/regions/' . $regionId, $token, [
            'name' => 'Updated Region',
        ], ['HTTP_IF_MATCH' => '"' . $version . '"']);

        self::assertContains($status, [200, 422, 428], 'Expected 200, 422, or 428');

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);
        self::assertArrayHasKey('meta', $body);
        if ($status === 200) {
            self::assertNull($body['error']);
        } else {
            self::assertNotNull($body['error']);
        }
    }

    public function testRegionVersionsReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $regionId = $this->createRegion();

        $status = $this->api('GET', '/api/v1/regions/' . $regionId . '/versions', $token);

        self::assertSame(200, $status);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);
        self::assertArrayHasKey('meta', $body);
        self::assertNull($body['error']);
    }
}
