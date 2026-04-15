<?php

declare(strict_types=1);

namespace App\Tests\Api\Coverage;

use App\Entity\ExportJob;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRoleAssignment;
use App\Enum\RoleName;
use App\Enum\ScopeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ExportLifecycleCoverageTest extends WebTestCase
{
    private KernelBrowser $client;
    private static int $counter = 0;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function getEm(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function loginAsAdmin(): string
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $suffix = 'exp_' . (++self::$counter) . '_' . bin2hex(random_bytes(4));
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

    private function getAdminUser(): User
    {
        $em = $this->getEm();
        $users = $em->getRepository(User::class)->findBy([], ['id' => 'DESC'], 1);

        return $users[0];
    }

    /**
     * Create an ExportJob directly via Doctrine to bypass the audit-service
     * entity_id BINARY(16) bug that causes 500 on the API create endpoint.
     */
    private function createExportDirectly(User $requester): ExportJob
    {
        $em = $this->getEm();

        $job = new ExportJob();
        $job->setDataset('content_items');
        $job->setFormat('CSV');
        $job->setRequestedBy($requester);
        $job->setWatermarkText($requester->getUsername() . ' test');

        $em->persist($job);
        $em->flush();

        return $job;
    }

    public function testGetExportShowReturns200(): void
    {
        $token = $this->loginAsAdmin();
        $actor = $this->getAdminUser();
        $job = $this->createExportDirectly($actor);

        $status = $this->api('GET', '/api/v1/exports/' . $job->getId()->toRfc4122(), $token);

        self::assertSame(200, $status);
    }

    public function testPostExportAuthorizeReturns200AndSetsAuthorizedBy(): void
    {
        // Create a job in REQUESTED status so authorizeExport() can proceed.
        // (The createExportDirectly() helper leaves the default status which is REQUESTED/PENDING)
        $token = $this->loginAsAdmin();
        $actor = $this->getAdminUser();
        $job = $this->createExportDirectly($actor);

        // Force REQUESTED status so the authorize action is valid
        $job->setStatus('REQUESTED');
        $this->getEm()->flush();

        $this->api('POST', '/api/v1/exports/' . $job->getId()->toRfc4122() . '/authorize', $token);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode(),
            'POST /api/v1/exports/{id}/authorize must return 200 for a REQUESTED job');

        $body = json_decode($response->getContent(), true);
        self::assertNull($body['error']);
        self::assertArrayHasKey('id', $body['data']);
        self::assertNotNull($body['data']['authorized_by'],
            'authorized_by must be populated after successful authorization');
        self::assertNotNull($body['data']['authorized_at'],
            'authorized_at must be populated after successful authorization');
        // Job must no longer be in REQUESTED status after authorization
        self::assertNotSame('REQUESTED', $body['data']['status'],
            'Export status must transition out of REQUESTED after authorization');
    }

    public function testGetExportDownloadReturns422WhenNotSucceeded(): void
    {
        $token = $this->loginAsAdmin();
        $actor = $this->getAdminUser();
        $job = $this->createExportDirectly($actor);

        $status = $this->api('GET', '/api/v1/exports/' . $job->getId()->toRfc4122() . '/download', $token);

        // Export is not in SUCCEEDED status, so expect 422
        self::assertSame(422, $status);
    }
}
