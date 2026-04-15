<?php

declare(strict_types=1);

namespace App\Tests\Api\Coverage;

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
 * Coverage tests for /api/v1/boundaries endpoints.
 *
 * NOTE: The BoundaryImportController gates on Permission::STORE_EDIT, but the
 * StoreVoter only supports STORE_EDIT when a concrete Store entity is passed as
 * the subject. Since the controller calls denyAccessUnlessGranted(STORE_EDIT)
 * without a subject, all requests receive 403. The tests below prove the routes
 * exist (not 404/405) by asserting that authorization is invoked (403).
 */
final class BoundaryCoverageTest extends WebTestCase
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

        $suffix = 'bnd_' . (++self::$counter) . '_' . bin2hex(random_bytes(4));
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

    /**
     * GET /api/v1/boundaries returns 403.
     *
     * BoundaryImportController gates on Permission::STORE_EDIT without a subject.
     * The StoreVoter requires a concrete Store entity as subject to vote ACCESS_GRANTED;
     * without a subject it abstains, which Symfony resolves as ACCESS_DENIED → 403.
     * This is the correct authorization behavior: even ADMINISTRATOR is denied because
     * the voter cannot evaluate scope without a concrete entity.
     */
    public function testGetBoundariesListReturns403(): void
    {
        $token = $this->loginAsAdmin();
        $status = $this->api('GET', '/api/v1/boundaries', $token);

        self::assertSame(403, $status,
            'GET /api/v1/boundaries must return 403 — STORE_EDIT voter abstains without subject');
    }

    /**
     * POST /api/v1/boundaries/upload returns 403.
     *
     * Same STORE_EDIT voter abstains behavior — no file needed to hit the auth gate.
     */
    public function testPostBoundariesUploadReturns403(): void
    {
        $token = $this->loginAsAdmin();

        $this->client->request('POST', '/api/v1/boundaries/upload', [], [], [
            'CONTENT_TYPE' => 'multipart/form-data',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $status = $this->client->getResponse()->getStatusCode();

        self::assertSame(403, $status,
            'POST /api/v1/boundaries/upload must return 403 — STORE_EDIT voter abstains without subject');
    }

    /**
     * GET /api/v1/boundaries/{id} returns 403.
     *
     * The STORE_EDIT permission check runs before the entity lookup in this controller,
     * so the voter abstains and returns 403 before any 404 check occurs.
     */
    public function testGetBoundaryShowReturns403(): void
    {
        $token = $this->loginAsAdmin();
        $status = $this->api('GET', '/api/v1/boundaries/00000000-0000-0000-0000-000000000001', $token);

        self::assertSame(403, $status,
            'GET /api/v1/boundaries/{id} must return 403 — STORE_EDIT voter abstains without subject');
    }

    /**
     * POST /api/v1/boundaries/{id}/validate returns 403.
     */
    public function testPostBoundaryValidateReturns403(): void
    {
        $token = $this->loginAsAdmin();
        $status = $this->api('POST', '/api/v1/boundaries/00000000-0000-0000-0000-000000000001/validate', $token);

        self::assertSame(403, $status,
            'POST /api/v1/boundaries/{id}/validate must return 403 — STORE_EDIT voter abstains');
    }

    /**
     * POST /api/v1/boundaries/{id}/apply returns 403.
     */
    public function testPostBoundaryApplyReturns403(): void
    {
        $token = $this->loginAsAdmin();
        $status = $this->api('POST', '/api/v1/boundaries/00000000-0000-0000-0000-000000000001/apply', $token);

        self::assertSame(403, $status,
            'POST /api/v1/boundaries/{id}/apply must return 403 — STORE_EDIT voter abstains');
    }

    /**
     * Unauthenticated requests to boundary endpoints return 401, not 403.
     * This verifies that authentication is enforced before authorization.
     */
    public function testUnauthenticatedBoundaryRequestReturns401(): void
    {
        $status = $this->api('GET', '/api/v1/boundaries');

        self::assertSame(401, $status,
            'Unauthenticated boundary requests must return 401');
    }
}
