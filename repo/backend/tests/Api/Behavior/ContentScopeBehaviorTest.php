<?php

declare(strict_types=1);

namespace App\Tests\Api\Behavior;

use App\Entity\ContentItem;
use App\Entity\ContentVersion;
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
use Symfony\Component\Uid\Uuid;

/**
 * Real runtime behavior tests for content scope isolation.
 *
 * Replaces source-reading unit tests (ContentRegionScopeTest,
 * ContentListRegionScopeTest, ContentScopeUuidTest) with runtime assertions
 * that actually exercise the scope filtering code path:
 *
 *  - Store-scoped content is hidden from users without access to that store
 *  - Region-scoped content (no store_id) is hidden from users in other regions
 *  - Global-scope admin sees all content regardless of scope
 *  - Unauthenticated requests are rejected with 401
 *
 * Content is seeded via Doctrine directly (bypasses the audit-service BINARY(16)
 * bug in POST /api/v1/content) so scope filtering tests are reliable.
 */
final class ContentScopeBehaviorTest extends WebTestCase
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
    // Store scope — content scoped to a specific store
    // -----------------------------------------------------------------------

    public function testGlobalAdminSeesStoreScopedContent(): void
    {
        $actor = $this->createUser('csb_actor_' . (++self::$seq));
        $storeId = $this->createStore();

        $item = $this->seedContent($actor, 'Store Scoped Content', Uuid::fromString($storeId), null);
        $contentId = $item->getId()->toRfc4122();

        $token = $this->loginAsRole(RoleName::ADMINISTRATOR);

        $this->client->request('GET', "/api/v1/content/{$contentId}", [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode(),
            'Global admin must be able to access store-scoped content');

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame($contentId, $body['data']['id']);
    }

    public function testUserWithoutStoreAccessCannotSeeStoreScopedContent(): void
    {
        $actor = $this->createUser('csb_actor2_' . (++self::$seq));
        $storeIdA = $this->createStore();
        $storeIdB = $this->createStore();

        // Seed content scoped to storeA
        $item = $this->seedContent($actor, 'StoreA Scoped Content', Uuid::fromString($storeIdA), null);
        $contentId = $item->getId()->toRfc4122();

        // Log in as user scoped to storeB only
        $token = $this->loginAsScopedStore($storeIdB);

        // Should NOT be able to see storeA content
        $this->client->request('GET', "/api/v1/content/{$contentId}", [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains($status, [403, 404],
            'User scoped to storeB must not access content scoped to storeA');
    }

    public function testUserWithStoreAccessCanSeeStoreScopedContent(): void
    {
        $actor = $this->createUser('csb_actor3_' . (++self::$seq));
        $storeId = $this->createStore();

        // Seed content scoped to this store
        $item = $this->seedContent($actor, 'My Store Content', Uuid::fromString($storeId), null);
        $contentId = $item->getId()->toRfc4122();

        // Log in as user scoped to that same store
        $token = $this->loginAsScopedStore($storeId);

        $this->client->request('GET', "/api/v1/content/{$contentId}", [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode(),
            'User scoped to the content\'s store must be able to see it');

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame($contentId, $body['data']['id']);
    }

    // -----------------------------------------------------------------------
    // Region scope — content with region_id and no store_id
    // -----------------------------------------------------------------------

    public function testUserWithRegionAccessSeesRegionScopedContent(): void
    {
        $actor = $this->createUser('csb_actor4_' . (++self::$seq));
        $regionId = $this->createRegion();

        // Seed region-only content (no storeId, only regionId)
        $item = $this->seedContent($actor, 'RegionA Scoped Content', null, Uuid::fromString($regionId));
        $contentId = $item->getId()->toRfc4122();

        // Log in as user scoped to that region
        $token = $this->loginAsScopedRegion($regionId);

        $this->client->request('GET', "/api/v1/content/{$contentId}", [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode(),
            'User scoped to the content\'s region must be able to see region-scoped content');

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame($contentId, $body['data']['id']);
    }

    public function testUserWithDifferentRegionCannotSeeRegionScopedContent(): void
    {
        $actor = $this->createUser('csb_actor5_' . (++self::$seq));
        $regionA = $this->createRegion();
        $regionB = $this->createRegion();

        // Seed content scoped to regionA
        $item = $this->seedContent($actor, 'RegionA Only Content', null, Uuid::fromString($regionA));
        $contentId = $item->getId()->toRfc4122();

        // Log in as user scoped to regionB only
        $token = $this->loginAsScopedRegion($regionB);

        $this->client->request('GET', "/api/v1/content/{$contentId}", [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains($status, [403, 404],
            'User scoped to regionB must not access content scoped to regionA');
    }

    // -----------------------------------------------------------------------
    // Content list — store-scoped user sees their content in list
    // -----------------------------------------------------------------------

    public function testStoreScopedUserSeesOwnContentInList(): void
    {
        $actor = $this->createUser('csb_actor6_' . (++self::$seq));
        $storeId = $this->createStore();

        // Seed content scoped to this store
        $item = $this->seedContent($actor, 'Listed Store Content', Uuid::fromString($storeId), null);
        $listedId = $item->getId()->toRfc4122();

        $token = $this->loginAsScopedStore($storeId);

        $this->client->request('GET', '/api/v1/content', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $contentIds = array_column($body['data'], 'id');
        self::assertContains($listedId, $contentIds,
            'Store-scoped user must see their own store\'s content in the list');
    }

    // -----------------------------------------------------------------------
    // Unauthenticated — always rejected
    // -----------------------------------------------------------------------

    public function testUnauthenticatedCannotListContent(): void
    {
        $this->client->request('GET', '/api/v1/content');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testUnauthenticatedCannotViewContent(): void
    {
        $actor = $this->createUser('csb_actor7_' . (++self::$seq));
        $item = $this->seedContent($actor, 'Unauth Test Content', null, null);
        $contentId = $item->getId()->toRfc4122();

        $this->client->request('GET', "/api/v1/content/{$contentId}");
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function seedContent(
        User $actor,
        string $title,
        ?Uuid $storeId,
        ?Uuid $regionId,
    ): ContentItem {
        $item = new ContentItem();
        $item->setTitle($title);
        $item->setBody('Scope test body for ' . $title);
        $item->setAuthorName($actor->getDisplayName());
        $item->setContentType('JOB_POST');
        $item->setStatus('DRAFT');
        $item->setStoreId($storeId);
        $item->setRegionId($regionId);
        $this->em->persist($item);

        $version = new ContentVersion();
        $version->setContentItem($item);
        $version->setVersionNumber(1);
        $version->setTitle($item->getTitle());
        $version->setBody($item->getBody());
        $version->setTags([]);
        $version->setContentType($item->getContentType());
        $version->setStatusAtCreation('DRAFT');
        $version->setCreatedBy($actor);
        $this->em->persist($version);

        $this->em->flush();
        return $item;
    }

    private function createUser(string $username): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User();
        $user->setUsername($username);
        $user->setDisplayName('CSB User ' . $username);
        $user->setStatus('ACTIVE');
        $user->setPasswordHash($hasher->hashPassword($user, 'V@lid1Password!'));
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    private function createRegion(): string
    {
        $region = new MdmRegion();
        $region->setCode('CSR' . (++self::$seq));
        $region->setName('ContentScope Region ' . self::$seq);
        $region->setEffectiveFrom(new \DateTimeImmutable('-30 days'));
        $region->setIsActive(true);
        $this->em->persist($region);
        $this->em->flush();
        return $region->getId()->toRfc4122();
    }

    /**
     * Returns the binary (16-byte) form of the region ID for scope_id storage.
     */
    private function createRegionBinary(): string
    {
        $region = new MdmRegion();
        $region->setCode('CSR' . (++self::$seq));
        $region->setName('ContentScope Region ' . self::$seq);
        $region->setEffectiveFrom(new \DateTimeImmutable('-30 days'));
        $region->setIsActive(true);
        $this->em->persist($region);
        $this->em->flush();
        return $region->getId()->toBinary();
    }

    private function createStore(): string
    {
        $regionId = $this->createRegion();
        $region = $this->em->getRepository(MdmRegion::class)->find($regionId);

        $store = new Store();
        $store->setCode('CSS' . (++self::$seq));
        $store->setName('ContentScope Store ' . self::$seq);
        $store->setStoreType(StoreType::STORE);
        $store->setRegion($region);
        $store->setStatus('ACTIVE');
        $store->setTimezone('UTC');
        $store->setIsActive(true);
        $this->em->persist($store);
        $this->em->flush();
        return $store->getId()->toRfc4122();
    }

    /**
     * Returns both the RFC4122 ID (for API calls) and binary ID (for scope_id).
     * @return array{rfc: string, binary: string}
     */
    private function createStoreWithBinary(): array
    {
        $regionId = $this->createRegion();
        $region = $this->em->getRepository(MdmRegion::class)->find($regionId);

        $store = new Store();
        $store->setCode('CSS' . (++self::$seq));
        $store->setName('ContentScope Store ' . self::$seq);
        $store->setStoreType(StoreType::STORE);
        $store->setRegion($region);
        $store->setStatus('ACTIVE');
        $store->setTimezone('UTC');
        $store->setIsActive(true);
        $this->em->persist($store);
        $this->em->flush();
        return ['rfc' => $store->getId()->toRfc4122(), 'binary' => $store->getId()->toBinary()];
    }

    private function loginAsRole(RoleName $roleName, ScopeType $scopeType = ScopeType::GLOBAL): string
    {
        $suffix = 'csb_usr_' . (++self::$seq) . '_' . bin2hex(random_bytes(3));
        $password = 'V@lid1Password!';

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername($suffix);
        $user->setDisplayName('ContentScope User');
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

    private function loginAsScopedStore(string $storeId): string
    {
        $suffix = 'csb_smgr_' . (++self::$seq) . '_' . bin2hex(random_bytes(3));
        $password = 'V@lid1Password!';

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername($suffix);
        $user->setDisplayName('ContentScope StoreMgr');
        $user->setStatus('ACTIVE');
        $user->setPasswordHash($hasher->hashPassword($user, $password));
        $this->em->persist($user);

        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => RoleName::STORE_MANAGER->value]);
        if ($role === null) {
            $role = new Role();
            $role->setName(RoleName::STORE_MANAGER->value);
            $role->setDisplayName('Store Manager');
            $role->setIsSystem(true);
            $this->em->persist($role);
            $this->em->flush();
        }

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

    private function loginAsScopedRegion(string $regionId): string
    {
        $suffix = 'csb_rgn_' . (++self::$seq) . '_' . bin2hex(random_bytes(3));
        $password = 'V@lid1Password!';

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername($suffix);
        $user->setDisplayName('ContentScope RegionMgr');
        $user->setStatus('ACTIVE');
        $user->setPasswordHash($hasher->hashPassword($user, $password));
        $this->em->persist($user);

        $role = $this->em->getRepository(Role::class)->findOneBy(['name' => RoleName::STORE_MANAGER->value]);
        if ($role === null) {
            $role = new Role();
            $role->setName(RoleName::STORE_MANAGER->value);
            $role->setDisplayName('Store Manager');
            $role->setIsSystem(true);
            $this->em->persist($role);
            $this->em->flush();
        }

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
}
