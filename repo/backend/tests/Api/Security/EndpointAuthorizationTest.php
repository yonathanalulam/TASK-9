<?php

declare(strict_types=1);

namespace App\Tests\Api\Security;

use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRoleAssignment;
use App\Enum\RoleName;
use App\Enum\ScopeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Verifies that privileged endpoints enforce proper authorization
 * and that unauthenticated requests are rejected with 401.
 */
final class EndpointAuthorizationTest extends WebTestCase
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

    // -----------------------------------------------------------------------
    // Unauthenticated requests should return 401
    // -----------------------------------------------------------------------

    public function testUnauthenticatedGetStoresReturns401(): void
    {
        $this->client->request('GET', '/api/v1/stores');

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testUnauthenticatedGetContentReturns401(): void
    {
        $this->client->request('GET', '/api/v1/content');

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testUnauthenticatedSearchReturns401(): void
    {
        $this->client->request('GET', '/api/v1/search?q=test');

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Operations analyst should be forbidden from admin-only endpoints
    // -----------------------------------------------------------------------

    public function testAnalystCanAccessWarehouseLoads(): void
    {
        $token = $this->loginAsAnalyst('analyst_wh_loads');

        $this->client->request('GET', '/api/v1/warehouse/loads', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        // Analyst has WAREHOUSE_VIEW permission per WarehouseVoter.
        self::assertNotSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testAnalystCannotTriggerWarehouseLoad(): void
    {
        $token = $this->loginAsAnalyst('analyst_wh_trigger');

        $this->client->request('POST', '/api/v1/warehouse/loads/trigger', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testAnalystCannotCreateSources(): void
    {
        $token = $this->loginAsAnalyst('analyst_src_create');

        $this->client->request('POST', '/api/v1/sources', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'name' => 'Forbidden Source',
            'base_url' => 'https://example.com',
            'scrape_type' => 'HTML',
        ]));

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testAnalystCannotListSources(): void
    {
        $token = $this->loginAsAnalyst('analyst_src_list');

        $this->client->request('GET', '/api/v1/sources', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testAnalystCannotTriggerScrapeRun(): void
    {
        $token = $this->loginAsAnalyst('analyst_scrape_trig');

        $this->client->request('POST', '/api/v1/scrape-runs/trigger/00000000-0000-0000-0000-000000000001', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Operations analyst CAN access analytics endpoints
    // -----------------------------------------------------------------------

    public function testAnalystCanViewAnalyticsSales(): void
    {
        $token = $this->loginAsAnalyst('analyst_sales');

        $this->client->request('GET', '/api/v1/analytics/sales', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertSame(200, $status);
    }

    public function testAnalystCanViewKpiSummary(): void
    {
        $token = $this->loginAsAnalyst('analyst_kpi');

        $this->client->request('GET', '/api/v1/analytics/kpi-summary', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertSame(200, $status);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function loginAsAnalyst(string $suffix): string
    {
        $user = $this->createTestUser($suffix);
        $grantor = $user;
        $role = $this->getOrCreateRole(RoleName::OPERATIONS_ANALYST);
        $this->createAssignment($user, $role, ScopeType::GLOBAL, null, $grantor);

        return $this->loginAndGetToken($suffix, 'V@lid1Password!');
    }

    private function createTestUser(string $username): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setDisplayName('Test ' . $username);
        $user->setStatus('ACTIVE');

        $hashedPassword = $this->passwordHasher->hashPassword($user, 'V@lid1Password!');
        $user->setPasswordHash($hashedPassword);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function getOrCreateRole(RoleName $roleName): Role
    {
        $existing = $this->em->getRepository(Role::class)->findOneBy(['name' => $roleName->value]);
        if ($existing !== null) {
            return $existing;
        }

        $role = new Role();
        $role->setName($roleName->value);
        $role->setDisplayName(ucwords(str_replace('_', ' ', $roleName->value)));
        $role->setIsSystem(true);

        $this->em->persist($role);
        $this->em->flush();

        return $role;
    }

    private function createAssignment(
        User $user,
        Role $role,
        ScopeType $scopeType,
        ?string $scopeId,
        User $grantedBy,
    ): UserRoleAssignment {
        $assignment = new UserRoleAssignment();
        $assignment->setUser($user);
        $assignment->setRole($role);
        $assignment->setScopeType($scopeType);
        $assignment->setScopeId($scopeId);
        $assignment->setEffectiveFrom(new \DateTimeImmutable('-1 day'));
        $assignment->setGrantedBy($grantedBy);

        $this->em->persist($assignment);
        $this->em->flush();

        return $assignment;
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
