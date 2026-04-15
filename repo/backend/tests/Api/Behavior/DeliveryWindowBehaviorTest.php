<?php

declare(strict_types=1);

namespace App\Tests\Api\Behavior;

use App\Entity\DeliveryZone;
use App\Entity\MdmRegion;
use App\Entity\Role;
use App\Entity\Store;
use App\Entity\User;
use App\Entity\UserRoleAssignment;
use App\Enum\RoleName;
use App\Enum\ScopeType;
use App\Enum\StoreType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Real runtime behavior tests for the delivery window API.
 *
 * Replaces DeliveryWindowCoverageTest's broad-status acceptance (allows 500)
 * with exact behavior assertions:
 *  - 201 on creation with correct response shape
 *  - 422 on validation failure
 *  - 200 on update with updated data reflected
 *  - 200 on delete (soft deactivation)
 *  - 401 unauthenticated
 *  - 403 analyst cannot mutate windows
 */
final class DeliveryWindowBehaviorTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private static int $seq = 0;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    // -----------------------------------------------------------------------
    // Window creation — exact 201 behavior
    // -----------------------------------------------------------------------

    public function testCreateDeliveryWindowReturns201WithCorrectShape(): void
    {
        $token = $this->loginAsAdmin();
        $zoneId = $this->createZone();

        $this->request('POST', "/api/v1/delivery-zones/{$zoneId}/windows", $token, [
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '12:00',
        ]);

        $response = $this->client->getResponse();
        self::assertSame(201, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertNull($body['error']);
        self::assertArrayHasKey('id', $body['data']);
        self::assertSame($zoneId, $body['data']['zone_id']);
        self::assertSame(1, $body['data']['day_of_week']);
        self::assertSame('08:00', $body['data']['start_time']);
        self::assertSame('12:00', $body['data']['end_time']);
        self::assertArrayHasKey('is_active', $body['data']);
        self::assertArrayHasKey('created_at', $body['data']);
        self::assertNotEmpty($body['data']['id']);
    }

    public function testCreateWindowWithMissingFieldsReturns422(): void
    {
        $token = $this->loginAsAdmin();
        $zoneId = $this->createZone();

        // Missing day_of_week, start_time, end_time
        $this->request('POST', "/api/v1/delivery-zones/{$zoneId}/windows", $token, []);

        $response = $this->client->getResponse();
        self::assertSame(422, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertNotEmpty($body['error']);
        self::assertNotSame(500, $response->getStatusCode(), 'Missing fields must return 422, not 500');
    }

    public function testCreateWindowForNonExistentZoneReturns404(): void
    {
        $token = $this->loginAsAdmin();

        $this->request('POST', '/api/v1/delivery-zones/00000000-0000-0000-0000-000000000001/windows', $token, [
            'day_of_week' => 0,
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Window list — exact 200 behavior
    // -----------------------------------------------------------------------

    public function testListWindowsForZoneReturns200WithShape(): void
    {
        $token = $this->loginAsAdmin();
        $zoneId = $this->createZone();

        // Create two windows
        foreach ([[0, '07:00', '11:00'], [2, '14:00', '18:00']] as [$day, $start, $end]) {
            $this->request('POST', "/api/v1/delivery-zones/{$zoneId}/windows", $token, [
                'day_of_week' => $day,
                'start_time' => $start,
                'end_time' => $end,
            ]);
            self::assertSame(201, $this->client->getResponse()->getStatusCode());
        }

        $this->request('GET', "/api/v1/delivery-zones/{$zoneId}/windows", $token);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode(), 'List must return exactly 200, not 500');

        $body = json_decode($response->getContent(), true);
        self::assertNull($body['error']);
        self::assertIsArray($body['data']);
        self::assertGreaterThanOrEqual(2, count($body['data']));

        $first = $body['data'][0];
        self::assertArrayHasKey('id', $first);
        self::assertArrayHasKey('zone_id', $first);
        self::assertArrayHasKey('day_of_week', $first);
        self::assertArrayHasKey('start_time', $first);
        self::assertArrayHasKey('end_time', $first);
    }

    // -----------------------------------------------------------------------
    // Window update — exact 200 behavior with data reflected
    // -----------------------------------------------------------------------

    public function testUpdateWindowReturns200WithUpdatedData(): void
    {
        $token = $this->loginAsAdmin();
        $zoneId = $this->createZone();

        // Create window
        $this->request('POST', "/api/v1/delivery-zones/{$zoneId}/windows", $token, [
            'day_of_week' => 3,
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);
        $created = json_decode($this->client->getResponse()->getContent(), true);
        $windowId = $created['data']['id'];

        // Update time slot
        $this->request('PUT', "/api/v1/delivery-windows/{$windowId}", $token, [
            'start_time' => '10:00',
            'end_time' => '14:00',
        ]);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode(), 'Update must return exactly 200, not 500');

        $body = json_decode($response->getContent(), true);
        self::assertNull($body['error']);
        self::assertSame('10:00', $body['data']['start_time']);
        self::assertSame('14:00', $body['data']['end_time']);
        self::assertSame($windowId, $body['data']['id']);
    }

    public function testUpdateNonExistentWindowReturns404(): void
    {
        $token = $this->loginAsAdmin();

        $this->request('PUT', '/api/v1/delivery-windows/00000000-0000-0000-0000-000000000001', $token, [
            'start_time' => '10:00',
        ]);

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Window delete — soft deactivation returns 200
    // -----------------------------------------------------------------------

    public function testDeleteWindowReturns200WithMessage(): void
    {
        $token = $this->loginAsAdmin();
        $zoneId = $this->createZone();

        // Create window
        $this->request('POST', "/api/v1/delivery-zones/{$zoneId}/windows", $token, [
            'day_of_week' => 4,
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);
        $created = json_decode($this->client->getResponse()->getContent(), true);
        $windowId = $created['data']['id'];

        // Delete (soft deactivate)
        $this->request('DELETE', "/api/v1/delivery-windows/{$windowId}", $token);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode(), 'Delete must return 200, not 500');

        $body = json_decode($response->getContent(), true);
        self::assertNull($body['error']);
        self::assertArrayHasKey('message', $body['data']);
    }

    // -----------------------------------------------------------------------
    // Authorization — analyst cannot mutate windows
    // -----------------------------------------------------------------------

    public function testAnalystCannotCreateWindow(): void
    {
        $token = $this->loginAsRole(RoleName::OPERATIONS_ANALYST);
        $zoneId = $this->createZone();

        $this->request('POST', "/api/v1/delivery-zones/{$zoneId}/windows", $token, [
            'day_of_week' => 0,
            'start_time' => '08:00',
            'end_time' => '12:00',
        ]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Authentication — unauthenticated requests rejected
    // -----------------------------------------------------------------------

    public function testUnauthenticatedCannotListWindows(): void
    {
        $zoneId = $this->createZone();
        $this->request('GET', "/api/v1/delivery-zones/{$zoneId}/windows");
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testUnauthenticatedCannotCreateWindow(): void
    {
        $zoneId = $this->createZone();
        $this->request('POST', "/api/v1/delivery-zones/{$zoneId}/windows", null, [
            'day_of_week' => 0,
            'start_time' => '08:00',
            'end_time' => '12:00',
        ]);
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function request(
        string $method,
        string $url,
        ?string $token = null,
        ?array $body = null,
    ): void {
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        $this->client->request(
            $method,
            $url,
            [],
            [],
            $headers,
            $body !== null ? json_encode($body) : null,
        );
    }

    private function loginAsAdmin(): string
    {
        return $this->loginAsRole(RoleName::ADMINISTRATOR);
    }

    private function loginAsRole(RoleName $roleName): string
    {
        $suffix = 'beh_win_' . (++self::$seq) . '_' . bin2hex(random_bytes(3));
        $password = 'V@lid1Password!';

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername($suffix);
        $user->setDisplayName('Behavior Window Test User');
        $user->setStatus('ACTIVE');
        $user->setPasswordHash($hasher->hashPassword($user, $password));
        $this->em->persist($user);

        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => $roleName->value]);
        if ($role === null) {
            $role = new Role();
            $role->setName($roleName->value);
            $role->setDisplayName(ucwords(str_replace('_', ' ', $roleName->value)));
            $role->setIsSystem(true);
            $this->em->persist($role);
            $this->em->flush();
        }

        $assignment = new UserRoleAssignment();
        $assignment->setUser($user);
        $assignment->setRole($role);
        $assignment->setScopeType(ScopeType::GLOBAL);
        $assignment->setEffectiveFrom(new \DateTimeImmutable('-1 day'));
        $assignment->setGrantedBy($user);
        $this->em->persist($assignment);
        $this->em->flush();

        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => $suffix, 'password' => $password]));

        return json_decode($this->client->getResponse()->getContent(), true)['data']['token'];
    }

    private function createZone(): string
    {
        $region = new MdmRegion();
        $region->setCode('BWR' . (++self::$seq));
        $region->setName('BehaviorWindow Region ' . self::$seq);
        $region->setEffectiveFrom(new \DateTimeImmutable('-30 days'));
        $region->setIsActive(true);
        $this->em->persist($region);

        $store = new Store();
        $store->setCode('BWS' . self::$seq);
        $store->setName('BehaviorWindow Store ' . self::$seq);
        $store->setStoreType(StoreType::STORE);
        $store->setRegion($region);
        $store->setStatus('ACTIVE');
        $store->setTimezone('UTC');
        $store->setIsActive(true);
        $this->em->persist($store);

        $zone = new DeliveryZone();
        $zone->setStore($store);
        $zone->setName('BehaviorWindow Zone ' . self::$seq);
        $zone->setMinOrderThreshold('20.00');
        $zone->setDeliveryFee('3.50');
        $zone->setStatus('ACTIVE');
        $zone->setIsActive(true);
        $this->em->persist($zone);

        $this->em->flush();
        return $zone->getId()->toRfc4122();
    }
}
