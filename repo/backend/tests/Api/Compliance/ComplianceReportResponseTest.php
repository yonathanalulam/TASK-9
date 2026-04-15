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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Verifies that compliance report API responses expose a download_url
 * and do NOT leak the internal file_path.
 */
final class ComplianceReportResponseTest extends WebTestCase
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

    public function testCreateReportContainsDownloadUrlAndNoFilePath(): void
    {
        $token = $this->loginAsComplianceOfficer();

        $this->client->request('POST', '/api/v1/compliance-reports', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'report_type' => 'ACCESS_AUDIT',
            'parameters' => [],
        ]));

        $response = $this->client->getResponse();
        self::assertSame(201, $response->getStatusCode());

        $payload = json_decode($response->getContent(), true);
        self::assertArrayHasKey('data', $payload);

        $data = $payload['data'];

        // Must contain download_url.
        self::assertArrayHasKey('download_url', $data);
        self::assertIsString($data['download_url']);
        self::assertStringStartsWith('/api/v1/compliance-reports/', $data['download_url']);

        // Must NOT expose the server-side file_path.
        self::assertArrayNotHasKey('file_path', $data);
    }

    public function testCreateReportReturns201WithExpectedKeys(): void
    {
        $token = $this->loginAsComplianceOfficer();

        $this->client->request('POST', '/api/v1/compliance-reports', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'report_type' => 'RETENTION_SUMMARY',
            'parameters' => [],
        ]));

        $response = $this->client->getResponse();
        self::assertSame(201, $response->getStatusCode());

        $payload = json_decode($response->getContent(), true);
        $data = $payload['data'];

        $expectedKeys = [
            'id',
            'report_type',
            'generated_by',
            'parameters',
            'download_url',
            'tamper_hash_sha256',
            'generated_at',
        ];

        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $data, sprintf('Expected key "%s" in response data.', $key));
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function loginAsComplianceOfficer(): string
    {
        static $counter = 0;
        $counter++;

        $suffix = 'compliance_rpt_' . $counter;

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
}
