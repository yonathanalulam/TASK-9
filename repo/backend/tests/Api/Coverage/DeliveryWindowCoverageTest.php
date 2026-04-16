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

final class DeliveryWindowCoverageTest extends WebTestCase
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

    private function createStore(string $regionId): string
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $region = $em->getRepository(\App\Entity\MdmRegion::class)->find($regionId);
        $store = new \App\Entity\Store();
        $store->setCode('COVS' . str_pad((string)(++self::$counter), 3, '0', STR_PAD_LEFT));
        $store->setName('Coverage Store ' . self::$counter);
        $store->setStoreType(\App\Enum\StoreType::STORE);
        $store->setRegion($region);
        $store->setStatus('ACTIVE');
        $store->setTimezone('UTC');
        $store->setIsActive(true);
        $em->persist($store);
        $em->flush();
        return $store->getId()->toRfc4122();
    }

    private function createZone(string $storeId): string
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $store = $em->getRepository(\App\Entity\Store::class)->find($storeId);
        $zone = new \App\Entity\DeliveryZone();
        $zone->setStore($store);
        $zone->setName('Coverage Zone ' . (++self::$counter));
        $zone->setMinOrderThreshold('25.00');
        $zone->setDeliveryFee('3.99');
        $zone->setStatus('ACTIVE');
        $zone->setIsActive(true);
        $em->persist($zone);
        $em->flush();
        return $zone->getId()->toRfc4122();
    }

    public function testCreateDeliveryWindowReturns201(): void
    {
        $token = $this->loginAsAdmin();
        $regionId = $this->createRegion();
        $storeId = $this->createStore($regionId);
        $zoneId = $this->createZone($storeId);

        $status = $this->api('POST', '/api/v1/delivery-zones/' . $zoneId . '/windows', $token, [
            'day_of_week' => 0,
            'start_time' => '08:00',
            'end_time' => '12:00',
        ]);

        self::assertSame(201, $status);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);
        self::assertNull($body['error']);
    }

    public function testListDeliveryWindowsReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $regionId = $this->createRegion();
        $storeId = $this->createStore($regionId);
        $zoneId = $this->createZone($storeId);

        // Create a window first.
        $this->api('POST', '/api/v1/delivery-zones/' . $zoneId . '/windows', $token, [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);

        $status = $this->api('GET', '/api/v1/delivery-zones/' . $zoneId . '/windows', $token);

        self::assertSame(200, $status);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);
        self::assertIsArray($body['data']);
        self::assertNull($body['error']);
    }

    public function testUpdateDeliveryWindowReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $regionId = $this->createRegion();
        $storeId = $this->createStore($regionId);
        $zoneId = $this->createZone($storeId);

        // Create a window.
        $this->api('POST', '/api/v1/delivery-zones/' . $zoneId . '/windows', $token, [
            'day_of_week' => 2,
            'start_time' => '08:00',
            'end_time' => '12:00',
        ]);
        $resp = $this->jsonResponse();
        self::assertNotNull($resp['data']['id'] ?? null, 'Window create must return an ID — a null here means the create endpoint is broken');
        $windowId = $resp['data']['id'];

        $status = $this->api('PUT', '/api/v1/delivery-windows/' . $windowId, $token, [
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);

        self::assertSame(200, $status);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);
        self::assertNull($body['error']);
    }

    public function testDeleteDeliveryWindowReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $regionId = $this->createRegion();
        $storeId = $this->createStore($regionId);
        $zoneId = $this->createZone($storeId);

        // Create a window.
        $this->api('POST', '/api/v1/delivery-zones/' . $zoneId . '/windows', $token, [
            'day_of_week' => 3,
            'start_time' => '10:00',
            'end_time' => '14:00',
        ]);
        $resp = $this->jsonResponse();
        self::assertNotNull($resp['data']['id'] ?? null, 'Window create must return an ID — a null here means the create endpoint is broken');
        $windowId = $resp['data']['id'];

        $status = $this->api('DELETE', '/api/v1/delivery-windows/' . $windowId, $token);

        self::assertSame(200, $status);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);
        self::assertNull($body['error']);
    }
}
