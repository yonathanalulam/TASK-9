<?php

declare(strict_types=1);

namespace App\Tests\Api\Behavior;

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

/**
 * Real runtime behavior tests for the export API.
 *
 * Tests the full export lifecycle:
 *  - request creation (exact 201 with job shape)
 *  - field validation (422 with details)
 *  - retrieval by ID (exact shape)
 *  - download gating — only SUCCEEDED jobs can be downloaded
 *  - authorization — only privileged users can request exports
 */
final class ExportBehaviorTest extends WebTestCase
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
    // Export request — exact 201 behavior
    // -----------------------------------------------------------------------

    public function testCreateExportReturns201WithJobShape(): void
    {
        $token = $this->loginAsAdmin();

        $this->request('POST', '/api/v1/exports', $token, [
            'dataset' => 'content_items',
            'format' => 'CSV',
        ]);

        $response = $this->client->getResponse();
        self::assertSame(201, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertNull($body['error']);
        self::assertArrayHasKey('id', $body['data']);
        self::assertSame('content_items', $body['data']['dataset']);
        self::assertSame('CSV', $body['data']['format']);
        self::assertArrayHasKey('status', $body['data']);
        self::assertContains($body['data']['status'], ['PENDING', 'PROCESSING', 'SUCCEEDED']);
        self::assertArrayHasKey('requested_at', $body['data']);
        self::assertNotEmpty($body['data']['id']);
    }

    public function testCreateExportWithInvalidDatasetReturns422(): void
    {
        $token = $this->loginAsAdmin();

        $this->request('POST', '/api/v1/exports', $token, [
            'dataset' => 'non_existent_dataset',
            'format' => 'CSV',
        ]);

        $response = $this->client->getResponse();
        self::assertSame(422, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertSame('VALIDATION_ERROR', $body['error']['code']);
        self::assertArrayHasKey('dataset', $body['error']['details']);
    }

    public function testCreateExportWithMissingDatasetReturns422(): void
    {
        $token = $this->loginAsAdmin();

        $this->request('POST', '/api/v1/exports', $token, [
            'format' => 'CSV',
            // 'dataset' omitted
        ]);

        $response = $this->client->getResponse();
        self::assertSame(422, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        self::assertSame('VALIDATION_ERROR', $body['error']['code']);
    }

    // -----------------------------------------------------------------------
    // Export retrieval — exact response shape
    // -----------------------------------------------------------------------

    public function testGetExportByIdReturns200WithCorrectData(): void
    {
        $token = $this->loginAsAdmin();

        // Create
        $this->request('POST', '/api/v1/exports', $token, [
            'dataset' => 'content_items',
            'format' => 'CSV',
        ]);
        $created = json_decode($this->client->getResponse()->getContent(), true);
        $jobId = $created['data']['id'];

        // Retrieve
        $this->request('GET', "/api/v1/exports/{$jobId}", $token);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertNull($body['error']);
        self::assertSame($jobId, $body['data']['id']);
        self::assertSame('content_items', $body['data']['dataset']);
        self::assertArrayHasKey('status', $body['data']);
        self::assertArrayHasKey('watermark_text', $body['data']);
        self::assertArrayHasKey('requested_at', $body['data']);
    }

    public function testGetNonExistentExportReturns404(): void
    {
        $token = $this->loginAsAdmin();

        $this->request('GET', '/api/v1/exports/00000000-0000-0000-0000-000000000001', $token);

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Export list — pagination and filtering
    // -----------------------------------------------------------------------

    public function testListExportsReturnsPaginatedEnvelope(): void
    {
        $token = $this->loginAsAdmin();

        // Ensure at least one export exists
        $this->request('POST', '/api/v1/exports', $token, [
            'dataset' => 'content_items',
            'format' => 'CSV',
        ]);

        $this->request('GET', '/api/v1/exports', $token);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertNull($body['error']);
        self::assertIsArray($body['data']);
        self::assertArrayHasKey('pagination', $body['meta']);
        self::assertArrayHasKey('total', $body['meta']['pagination']);
        self::assertGreaterThanOrEqual(1, $body['meta']['pagination']['total']);
    }

    // -----------------------------------------------------------------------
    // Download gating — business rule enforcement
    // -----------------------------------------------------------------------

    public function testDownloadNonSucceededExportReturns422(): void
    {
        // Insert an ExportJob directly into the DB with status REQUESTED,
        // bypassing any async processing. This guarantees a non-SUCCEEDED state
        // regardless of whether Messenger uses a sync or async transport in test env.
        //
        // The old approach created the export via the API and then skipped if it was
        // already SUCCEEDED — which happened whenever the sync transport processed it
        // immediately. That skip hid the download-gating business rule completely.
        $token = $this->loginAsAdmin();

        // Reuse the user from loginAsAdmin as the requester — admin has EXPORT_AUTHORIZE,
        // so the ownership check passes and we reach the status check.
        $suffix = 'dng_' . bin2hex(random_bytes(3));
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $requester = new User();
        $requester->setUsername($suffix);
        $requester->setDisplayName('Download Gate Test Requester');
        $requester->setStatus('ACTIVE');
        $requester->setPasswordHash($hasher->hashPassword($requester, 'TempPass1!'));
        $this->em->persist($requester);

        $job = new ExportJob();
        $job->setDataset('content_items');
        $job->setFormat('CSV');
        $job->setStatus('REQUESTED');
        $job->setRequestedBy($requester);
        $this->em->persist($job);
        $this->em->flush();

        $jobId = $job->getId()->toRfc4122();

        // Admin has EXPORT_AUTHORIZE → $isAdmin = true → passes ownership check.
        // Status is REQUESTED ≠ SUCCEEDED → must return 422.
        $this->request('GET', "/api/v1/exports/{$jobId}/download", $token);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('VALIDATION_ERROR', $body['error']['code']);
        self::assertStringContainsString('REQUESTED', $body['error']['message']);
    }

    // -----------------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------------

    public function testUnauthenticatedRequestReturns401(): void
    {
        $this->request('GET', '/api/v1/exports');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testOperationsAnalystCannotRequestExport(): void
    {
        // Analyst does not have EXPORT_REQUEST permission
        $token = $this->loginAsRole(RoleName::OPERATIONS_ANALYST);

        $this->request('POST', '/api/v1/exports', $token, [
            'dataset' => 'content_items',
            'format' => 'CSV',
        ]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // POST /api/v1/exports/{id}/authorize — exact behavior
    // -----------------------------------------------------------------------

    public function testAuthorizeExportReturns200WithAuthorizedShape(): void
    {
        // Create an ExportJob in REQUESTED status directly via Doctrine.
        // authorizeExport() requires REQUESTED status to proceed.
        $token = $this->loginAsAdmin();

        $suffix = 'auth_req_' . bin2hex(random_bytes(3));
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $requester = new User();
        $requester->setUsername('req_' . $suffix);
        $requester->setDisplayName('Authorize Test Requester');
        $requester->setStatus('ACTIVE');
        $requester->setPasswordHash($hasher->hashPassword($requester, 'TempPass1!'));
        $this->em->persist($requester);

        $job = new ExportJob();
        $job->setDataset('content_items');
        $job->setFormat('CSV');
        $job->setStatus('REQUESTED');
        $job->setRequestedBy($requester);
        $this->em->persist($job);
        $this->em->flush();

        $jobId = $job->getId()->toRfc4122();

        $this->request('POST', "/api/v1/exports/{$jobId}/authorize", $token);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertNull($body['error']);
        self::assertSame($jobId, $body['data']['id']);
        self::assertNotNull(
            $body['data']['authorized_by'],
            'authorized_by must be set after authorization',
        );
        self::assertNotNull(
            $body['data']['authorized_at'],
            'authorized_at must be set after authorization',
        );
        // The job transitions out of REQUESTED after authorization — status is now
        // AUTHORIZED, RUNNING, SUCCEEDED, or FAILED (depending on whether generate succeeded)
        self::assertNotSame(
            'REQUESTED',
            $body['data']['status'],
            'Export must no longer be REQUESTED after the authorize action',
        );
    }

    public function testAuthorizeNonExistentExportReturns404(): void
    {
        $token = $this->loginAsAdmin();

        $this->request('POST', '/api/v1/exports/00000000-0000-0000-0000-000000000001/authorize', $token);

        $response = $this->client->getResponse();
        self::assertSame(404, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertSame('NOT_FOUND', $body['error']['code']);
    }

    public function testAuthorizeAlreadyAuthorizedExportReturns422(): void
    {
        // Export that is already AUTHORIZED cannot be re-authorized.
        // ExportService::authorizeExport() throws if status != REQUESTED.
        $token = $this->loginAsAdmin();

        $suffix = 'auth_dup_' . bin2hex(random_bytes(3));
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $requester = new User();
        $requester->setUsername('req_dup_' . $suffix);
        $requester->setDisplayName('Already Authorized Test Requester');
        $requester->setStatus('ACTIVE');
        $requester->setPasswordHash($hasher->hashPassword($requester, 'TempPass1!'));
        $this->em->persist($requester);

        $job = new ExportJob();
        $job->setDataset('audit_events');
        $job->setFormat('CSV');
        $job->setStatus('AUTHORIZED'); // Already authorized — not REQUESTED
        $job->setRequestedBy($requester);
        $this->em->persist($job);
        $this->em->flush();

        $jobId = $job->getId()->toRfc4122();

        $this->request('POST', "/api/v1/exports/{$jobId}/authorize", $token);

        $response = $this->client->getResponse();
        self::assertSame(422, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        self::assertSame('VALIDATION_ERROR', $body['error']['code']);
        self::assertStringContainsString('AUTHORIZED', $body['error']['message'],
            'Error message must identify the current status that prevents authorization');
    }

    public function testOperationsAnalystCannotAuthorizeExport(): void
    {
        // ExportVoter::canAuthorize() allows only COMPLIANCE_OFFICER and ADMINISTRATOR.
        $token = $this->loginAsRole(RoleName::OPERATIONS_ANALYST);

        $this->request('POST', '/api/v1/exports/00000000-0000-0000-0000-000000000001/authorize', $token);

        // Authorization check happens before entity lookup — must return 403, not 404.
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testUnauthenticatedCannotAuthorizeExport(): void
    {
        $this->request('POST', '/api/v1/exports/00000000-0000-0000-0000-000000000001/authorize');

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
        $suffix = 'beh_exp_' . (++self::$seq) . '_' . bin2hex(random_bytes(3));
        $password = 'V@lid1Password!';

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername($suffix);
        $user->setDisplayName('Behavior Export Test User');
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
}
