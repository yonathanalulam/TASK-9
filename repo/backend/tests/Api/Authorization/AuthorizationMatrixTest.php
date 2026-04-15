<?php

declare(strict_types=1);

namespace App\Tests\Api\Authorization;

use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRoleAssignment;
use App\Enum\RoleName;
use App\Enum\ScopeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Comprehensive authorization matrix test.
 *
 * For each endpoint group, verifies that an allowed role is NOT 403
 * and that a denied role IS 403 (or 401 for unauthenticated requests).
 */
final class AuthorizationMatrixTest extends WebTestCase
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

    // ===================================================================
    // Search
    // ===================================================================

    public function testSearchAllowedForOperationsAnalyst(): void
    {
        $token = $this->loginAs(RoleName::OPERATIONS_ANALYST, 'search_oa');
        $response = $this->apiRequest('GET', '/api/v1/search?q=test', $token);

        self::assertNotSame(403, $response->getStatusCode());
    }

    public function testSearchDeniedWithoutAuth(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/search?q=test');

        self::assertSame(401, $response->getStatusCode());
    }

    // ===================================================================
    // Export
    // ===================================================================

    public function testExportAllowedForComplianceOfficer(): void
    {
        $token = $this->loginAs(RoleName::COMPLIANCE_OFFICER, 'export_co');
        $response = $this->apiRequest('POST', '/api/v1/exports', $token, [
            'dataset' => 'content',
            'format' => 'CSV',
        ]);

        // Should be 201 or 422 (validation), but never 403.
        self::assertNotSame(403, $response->getStatusCode());
    }

    public function testExportDeniedForDispatcher(): void
    {
        $token = $this->loginAs(RoleName::DISPATCHER, 'export_disp');
        $response = $this->apiRequest('POST', '/api/v1/exports', $token, [
            'dataset' => 'content',
            'format' => 'CSV',
        ]);

        self::assertSame(403, $response->getStatusCode());
    }

    // ===================================================================
    // Compliance
    // ===================================================================

    public function testComplianceReportAllowedForComplianceOfficer(): void
    {
        $token = $this->loginAs(RoleName::COMPLIANCE_OFFICER, 'compl_co');
        $response = $this->apiRequest('POST', '/api/v1/compliance-reports', $token, [
            'report_type' => 'ACCESS_AUDIT',
            'parameters' => [],
        ]);

        self::assertNotSame(403, $response->getStatusCode());
    }

    public function testComplianceReportDeniedForRecruiter(): void
    {
        $token = $this->loginAs(RoleName::RECRUITER, 'compl_rec');
        $response = $this->apiRequest('POST', '/api/v1/compliance-reports', $token, [
            'report_type' => 'ACCESS_AUDIT',
            'parameters' => [],
        ]);

        self::assertSame(403, $response->getStatusCode());
    }

    // ===================================================================
    // Import
    // ===================================================================

    public function testImportAllowedForRecruiter(): void
    {
        $token = $this->loginAs(RoleName::RECRUITER, 'import_rec');
        $response = $this->apiRequest('POST', '/api/v1/imports', $token, [
            'source_name' => 'test_source',
            'items' => [
                ['title' => 'Test Job Posting', 'company' => 'Acme'],
            ],
        ]);

        // Should be 201 or 422, but never 403.
        self::assertNotSame(403, $response->getStatusCode());
    }

    public function testImportDeniedForDispatcher(): void
    {
        $token = $this->loginAs(RoleName::DISPATCHER, 'import_disp');
        $response = $this->apiRequest('POST', '/api/v1/imports', $token, [
            'source_name' => 'test_source',
            'items' => [
                ['title' => 'Test Job Posting', 'company' => 'Acme'],
            ],
        ]);

        self::assertSame(403, $response->getStatusCode());
    }

    // ===================================================================
    // Mutation queue
    // ===================================================================

    public function testMutationReplayAllowedForStoreManager(): void
    {
        $token = $this->loginAs(RoleName::STORE_MANAGER, 'mut_replay_sm');
        $response = $this->apiRequest('POST', '/api/v1/mutations/replay', $token, [
            'mutations' => [
                [
                    'mutation_id' => 'test-001',
                    'entity_type' => 'store',
                    'entity_id' => '00000000-0000-0000-0000-000000000001',
                    'operation' => 'UPDATE',
                    'payload' => ['name' => 'Updated'],
                ],
            ],
        ]);

        // Any role can replay, so should NOT be 403.
        self::assertNotSame(403, $response->getStatusCode());
    }

    public function testMutationListDeniedForStoreManager(): void
    {
        $token = $this->loginAs(RoleName::STORE_MANAGER, 'mut_list_sm');
        $response = $this->apiRequest('GET', '/api/v1/mutations', $token);

        // Admin-only endpoint.
        self::assertSame(403, $response->getStatusCode());
    }

    public function testMutationListAllowedForAdministrator(): void
    {
        $token = $this->loginAs(RoleName::ADMINISTRATOR, 'mut_list_admin');
        $response = $this->apiRequest('GET', '/api/v1/mutations', $token);

        self::assertNotSame(403, $response->getStatusCode());
    }

    // ===================================================================
    // Scraping (sources)
    // ===================================================================

    public function testSourceCreateAllowedForAdministrator(): void
    {
        $token = $this->loginAs(RoleName::ADMINISTRATOR, 'src_admin');
        $response = $this->apiRequest('POST', '/api/v1/sources', $token, [
            'name' => 'AuthMatrix Source',
            'base_url' => 'https://example.com',
            'scrape_type' => 'HTML',
        ]);

        self::assertNotSame(403, $response->getStatusCode());
    }

    public function testSourceCreateDeniedForOperationsAnalyst(): void
    {
        $token = $this->loginAs(RoleName::OPERATIONS_ANALYST, 'src_oa');
        $response = $this->apiRequest('POST', '/api/v1/sources', $token, [
            'name' => 'Denied Source',
            'base_url' => 'https://example.com',
            'scrape_type' => 'HTML',
        ]);

        self::assertSame(403, $response->getStatusCode());
    }

    // ===================================================================
    // Warehouse
    // ===================================================================

    public function testWarehouseLoadsAllowedForOperationsAnalyst(): void
    {
        $token = $this->loginAs(RoleName::OPERATIONS_ANALYST, 'wh_oa');
        $response = $this->apiRequest('GET', '/api/v1/warehouse/loads', $token);

        self::assertNotSame(403, $response->getStatusCode());
    }

    public function testWarehouseLoadsDeniedForDispatcher(): void
    {
        $token = $this->loginAs(RoleName::DISPATCHER, 'wh_disp');
        $response = $this->apiRequest('GET', '/api/v1/warehouse/loads', $token);

        self::assertSame(403, $response->getStatusCode());
    }

    // ===================================================================
    // Analytics
    // ===================================================================

    public function testAnalyticsKpiAllowedForOperationsAnalyst(): void
    {
        $token = $this->loginAs(RoleName::OPERATIONS_ANALYST, 'ana_oa');
        $response = $this->apiRequest('GET', '/api/v1/analytics/kpi-summary', $token);

        self::assertNotSame(403, $response->getStatusCode());
    }

    public function testAnalyticsKpiDeniedForDispatcher(): void
    {
        $token = $this->loginAs(RoleName::DISPATCHER, 'ana_disp');
        $response = $this->apiRequest('GET', '/api/v1/analytics/kpi-summary', $token);

        self::assertSame(403, $response->getStatusCode());
    }

    // ===================================================================
    // Helper methods
    // ===================================================================

    private function loginAs(RoleName $roleName, string $suffix): string
    {
        $creds = $this->createUserWithRole($roleName, $suffix);

        return $this->loginAndGetToken($creds['username'], $creds['password']);
    }

    /**
     * @return array{username: string, password: string}
     */
    private function createUserWithRole(RoleName $roleName, string $suffix): array
    {
        $username = 'authmatrix_' . $suffix;
        $password = 'V@lid1Password!';

        $user = new User();
        $user->setUsername($username);
        $user->setDisplayName('Test ' . $suffix);
        $user->setStatus('ACTIVE');

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPasswordHash($hashedPassword);

        $this->em->persist($user);

        $role = $this->getOrCreateRole($roleName);

        $assignment = new UserRoleAssignment();
        $assignment->setUser($user);
        $assignment->setRole($role);
        $assignment->setScopeType(ScopeType::GLOBAL);
        $assignment->setEffectiveFrom(new \DateTimeImmutable('-1 year'));
        $assignment->setGrantedBy($user);

        $this->em->persist($assignment);
        $this->em->flush();

        return ['username' => $username, 'password' => $password];
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

    private function apiRequest(
        string $method,
        string $path,
        ?string $token = null,
        ?array $body = null,
    ): Response {
        $headers = ['CONTENT_TYPE' => 'application/json'];

        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $content = $body !== null ? json_encode($body) : null;

        $this->client->request($method, $path, [], [], $headers, $content);

        return $this->client->getResponse();
    }
}
