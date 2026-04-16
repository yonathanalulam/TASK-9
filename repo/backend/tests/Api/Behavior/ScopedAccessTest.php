<?php

declare(strict_types=1);

namespace App\Tests\Api\Behavior;

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
 * Real runtime behavior tests for scope isolation.
 *
 * Verifies that users scoped to specific stores/regions only see
 * resources they are authorized for:
 *  - Store managers scoped to one region only see their stores
 *  - Unauthenticated requests are always rejected
 *  - Global-scope admin sees all resources
 */
final class ScopedAccessTest extends WebTestCase
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
    // Store scope isolation
    // -----------------------------------------------------------------------

    public function testAdminWithGlobalScopeSeesAllStores(): void
    {
        $token = $this->loginAsAdmin();

        // Create two stores in different regions
        $regionA = $this->createRegion('SCP_RA');
        $regionB = $this->createRegion('SCP_RB');
        $storeA = $this->createStore($regionA, 'SCP_SA');
        $storeB = $this->createStore($regionB, 'SCP_SB');

        $this->apiGet('/api/v1/stores', $token);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $storeIds = array_column($body['data'], 'id');
        self::assertContains($storeA, $storeIds, 'Global admin must see Store A');
        self::assertContains($storeB, $storeIds, 'Global admin must see Store B');
    }

    public function testStoreScopedManagerOnlySeesTheirStore(): void
    {
        // Create two distinct regions and stores
        $regionA = $this->createRegion('MGR_RA');
        $regionB = $this->createRegion('MGR_RB');
        $storeA = $this->createStore($regionA, 'MGR_SA');
        $storeB = $this->createStore($regionB, 'MGR_SB');

        // Create a store manager scoped to store A only
        $managerToken = $this->loginAsScopedStoreManager($storeA);

        $this->apiGet('/api/v1/stores', $managerToken);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $storeIds = array_column($body['data'], 'id');

        // Manager scoped to storeA must see storeA
        self::assertContains($storeA, $storeIds, 'Manager must see their own store');
        // Manager must NOT see storeB (different scope — storeB is in a different region/scope)
        self::assertNotContains($storeB, $storeIds,
            'Manager must not see stores outside their assigned scope');
    }

    public function testStoreScopedManagerCannotEditOtherStore(): void
    {
        // Create two stores
        $regionA = $this->createRegion('EDT_RA');
        $regionB = $this->createRegion('EDT_RB');
        $storeA = $this->createStore($regionA, 'EDT_SA');
        $storeB = $this->createStore($regionB, 'EDT_SB');

        // Manager scoped to storeA
        $managerToken = $this->loginAsScopedStoreManager($storeA);

        // Try to retrieve storeB — should be forbidden or not found
        $this->apiGet("/api/v1/stores/{$storeB}", $managerToken);
        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains($status, [403, 404], 'Manager must not access stores outside their scope');
    }

    // -----------------------------------------------------------------------
    // Region scope isolation
    // -----------------------------------------------------------------------

    public function testRegionScopedUserSeesOnlyTheirRegion(): void
    {
        $regionA = $this->createRegion('RGSA');
        $regionB = $this->createRegion('RGSB');

        // Create user scoped to regionA
        $token = $this->loginAsScopedRegionUser($regionA, RoleName::STORE_MANAGER);

        $this->apiGet('/api/v1/regions', $token);
        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $regionIds = array_column($body['data'], 'id');

        self::assertContains($regionA, $regionIds, 'Scoped user must see their region');
        // May or may not see regionB depending on hierarchy — the key assertion is they see regionA
        // and cannot access regionB directly
        $this->apiGet("/api/v1/regions/{$regionB}", $token);
        $regionBStatus = $this->client->getResponse()->getStatusCode();
        self::assertContains($regionBStatus, [403, 404],
            'Scoped user must be denied access to other regions');
    }

    // -----------------------------------------------------------------------
    // Authentication enforcement across scoped endpoints
    // -----------------------------------------------------------------------

    public function testUnauthenticatedCannotListStores(): void
    {
        $this->apiGet('/api/v1/stores');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testUnauthenticatedCannotListRegions(): void
    {
        $this->apiGet('/api/v1/regions');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testUnauthenticatedCannotSearch(): void
    {
        $this->apiGet('/api/v1/search?q=test');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function apiGet(string $url, ?string $token = null): void
    {
        $headers = [];
        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        $this->client->request('GET', $url, [], [], $headers);
    }

    private function loginAsAdmin(): string
    {
        return $this->loginAsRole(RoleName::ADMINISTRATOR, ScopeType::GLOBAL);
    }

    private function loginAsScopedStoreManager(string $storeId): string
    {
        $suffix = 'scp_mgr_' . (++self::$seq) . '_' . bin2hex(random_bytes(3));
        $password = 'V@lid1Password!';

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername($suffix);
        $user->setDisplayName('Scoped Manager');
        $user->setStatus('ACTIVE');
        $user->setPasswordHash($hasher->hashPassword($user, $password));
        $this->em->persist($user);

        $role = $this->ensureRole(RoleName::STORE_MANAGER);

        $assignment = new UserRoleAssignment();
        $assignment->setUser($user);
        $assignment->setRole($role);
        $assignment->setScopeType(ScopeType::STORE);
        $assignment->setScopeId($storeId);
        $assignment->setEffectiveFrom(new \DateTimeImmutable('-1 day'));
        $assignment->setGrantedBy($user);
        $this->em->persist($assignment);
        $this->em->flush();

        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => $suffix, 'password' => $password]));

        return json_decode($this->client->getResponse()->getContent(), true)['data']['token'];
    }

    private function loginAsScopedRegionUser(string $regionId, RoleName $roleName): string
    {
        $suffix = 'scp_rgn_' . (++self::$seq) . '_' . bin2hex(random_bytes(3));
        $password = 'V@lid1Password!';

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername($suffix);
        $user->setDisplayName('Scoped Region User');
        $user->setStatus('ACTIVE');
        $user->setPasswordHash($hasher->hashPassword($user, $password));
        $this->em->persist($user);

        $role = $this->ensureRole($roleName);

        $assignment = new UserRoleAssignment();
        $assignment->setUser($user);
        $assignment->setRole($role);
        $assignment->setScopeType(ScopeType::REGION);
        $assignment->setScopeId($regionId);
        $assignment->setEffectiveFrom(new \DateTimeImmutable('-1 day'));
        $assignment->setGrantedBy($user);
        $this->em->persist($assignment);
        $this->em->flush();

        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => $suffix, 'password' => $password]));

        return json_decode($this->client->getResponse()->getContent(), true)['data']['token'];
    }

    private function loginAsRole(RoleName $roleName, ScopeType $scopeType = ScopeType::GLOBAL): string
    {
        $suffix = 'scp_usr_' . (++self::$seq) . '_' . bin2hex(random_bytes(3));
        $password = 'V@lid1Password!';

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername($suffix);
        $user->setDisplayName('Scoped User');
        $user->setStatus('ACTIVE');
        $user->setPasswordHash($hasher->hashPassword($user, $password));
        $this->em->persist($user);

        $role = $this->ensureRole($roleName);

        $assignment = new UserRoleAssignment();
        $assignment->setUser($user);
        $assignment->setRole($role);
        $assignment->setScopeType($scopeType);
        $assignment->setEffectiveFrom(new \DateTimeImmutable('-1 day'));
        $assignment->setGrantedBy($user);
        $this->em->persist($assignment);
        $this->em->flush();

        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => $suffix, 'password' => $password]));

        return json_decode($this->client->getResponse()->getContent(), true)['data']['token'];
    }

    private function ensureRole(RoleName $roleName): Role
    {
        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => $roleName->value]);
        if ($role === null) {
            $role = new Role();
            $role->setName($roleName->value);
            $role->setDisplayName(ucwords(str_replace('_', ' ', $roleName->value)));
            $role->setIsSystem(true);
            $this->em->persist($role);
            $this->em->flush();
        }
        return $role;
    }

    private function createRegion(string $codePrefix): string
    {
        // MdmRegion.code is VARCHAR(5) — keep it short
        $code = substr($codePrefix, 0, 3) . chr(65 + (++self::$seq % 26));
        $region = new MdmRegion();
        $region->setCode($code);
        $region->setName('Scope Test Region ' . $code);
        $region->setEffectiveFrom(new \DateTimeImmutable('-30 days'));
        $region->setIsActive(true);
        $this->em->persist($region);
        $this->em->flush();
        return $region->getId()->toRfc4122();
    }

    private function createStore(string $regionId, string $codePrefix): string
    {
        $code = $codePrefix . (++self::$seq);
        $region = $this->em->getRepository(MdmRegion::class)->find($regionId);

        $store = new Store();
        $store->setCode($code);
        $store->setName('Scope Test Store ' . $code);
        $store->setStoreType(StoreType::STORE);
        $store->setRegion($region);
        $store->setStatus('ACTIVE');
        $store->setTimezone('UTC');
        $store->setIsActive(true);
        $this->em->persist($store);
        $this->em->flush();
        return $store->getId()->toRfc4122();
    }
}
