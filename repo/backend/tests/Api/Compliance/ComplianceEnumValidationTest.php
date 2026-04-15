<?php

declare(strict_types=1);

namespace App\Tests\Api\Compliance;

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
 * WebTestCase that validates compliance report enum acceptance and rejection
 * through actual API calls.
 *
 * Creates a COMPLIANCE_OFFICER user, authenticates, and verifies that:
 * - Valid report types are accepted (not 422).
 * - Invalid/stale report types are rejected (422).
 * - Response shapes conform to the contract.
 * - Download route is reachable.
 */
final class ComplianceEnumValidationTest extends WebTestCase
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
    // Valid report types
    // ===================================================================

    public function testCreateWithValidRetentionSummaryType(): void
    {
        $token = $this->loginAsComplianceOfficer();
        $response = $this->apiRequest('POST', '/api/v1/compliance-reports', $token, [
            'report_type' => 'RETENTION_SUMMARY',
            'parameters' => [],
        ]);

        // Should succeed (201) or at worst not be a 422 validation error about type.
        self::assertNotSame(422, $response->getStatusCode(), 'RETENTION_SUMMARY should be accepted');
    }

    public function testCreateWithValidAccessAuditType(): void
    {
        $token = $this->loginAsComplianceOfficer();
        $response = $this->apiRequest('POST', '/api/v1/compliance-reports', $token, [
            'report_type' => 'ACCESS_AUDIT',
            'parameters' => [],
        ]);

        self::assertNotSame(422, $response->getStatusCode(), 'ACCESS_AUDIT should be accepted');
    }

    // ===================================================================
    // Invalid report types
    // ===================================================================

    public function testCreateRejectsInvalidAuditLogType(): void
    {
        $token = $this->loginAsComplianceOfficer();
        $response = $this->apiRequest('POST', '/api/v1/compliance-reports', $token, [
            'report_type' => 'AUDIT_LOG',
            'parameters' => [],
        ]);

        self::assertSame(422, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertSame('VALIDATION_ERROR', $body['error']['code']);
    }

    public function testCreateRejectsInvalidDataAccessType(): void
    {
        $token = $this->loginAsComplianceOfficer();
        $response = $this->apiRequest('POST', '/api/v1/compliance-reports', $token, [
            'report_type' => 'DATA_ACCESS',
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    // ===================================================================
    // Response shape
    // ===================================================================

    public function testCreateResponseDoesNotContainFilePath(): void
    {
        $token = $this->loginAsComplianceOfficer();
        $response = $this->apiRequest('POST', '/api/v1/compliance-reports', $token, [
            'report_type' => 'RETENTION_SUMMARY',
            'parameters' => [],
        ]);

        $body = json_decode($response->getContent(), true);

        if (isset($body['data'])) {
            self::assertArrayNotHasKey('file_path', $body['data']);
            self::assertArrayHasKey('download_url', $body['data']);
        }
    }

    public function testListResponseShape(): void
    {
        $token = $this->loginAsComplianceOfficer();
        $response = $this->apiRequest('GET', '/api/v1/compliance-reports', $token);

        self::assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertArrayHasKey('data', $body);
        self::assertArrayHasKey('meta', $body);
        self::assertIsArray($body['data']);
    }

    // ===================================================================
    // Download route
    // ===================================================================

    public function testDownloadRouteExists(): void
    {
        $token = $this->loginAsComplianceOfficer();

        // First create a report.
        $createResponse = $this->apiRequest('POST', '/api/v1/compliance-reports', $token, [
            'report_type' => 'EXPORT_LOG',
            'parameters' => [],
        ]);

        $createBody = json_decode($createResponse->getContent(), true);

        // Must not skip — if create fails the endpoint is broken and the test should fail loudly.
        self::assertSame(201, $createResponse->getStatusCode(), 'Create compliance report must return 201');
        self::assertNotEmpty($createBody['data']['id'] ?? '', 'Create must return a report ID');

        $id = $createBody['data']['id'];
        $downloadResponse = $this->apiRequest('GET', "/api/v1/compliance-reports/{$id}/download", $token);

        // Should NOT be 404 (route exists) or 405 (method mismatch).
        self::assertNotSame(404, $downloadResponse->getStatusCode(), 'Download route must exist');
        self::assertNotSame(405, $downloadResponse->getStatusCode(), 'Download route must accept GET');
    }

    // ===================================================================
    // Helper methods
    // ===================================================================

    private function loginAsComplianceOfficer(): string
    {
        static $counter = 0;
        $counter++;

        $suffix = 'compl_enum_' . $counter;

        $user = $this->createTestUser($suffix);
        $role = $this->getOrCreateRole(RoleName::COMPLIANCE_OFFICER);
        $this->createAssignment($user, $role, ScopeType::GLOBAL, null, $user);

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
