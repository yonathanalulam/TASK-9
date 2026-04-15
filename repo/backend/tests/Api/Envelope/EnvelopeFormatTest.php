<?php

declare(strict_types=1);

namespace App\Tests\Api\Envelope;

use App\Entity\MdmRegion;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRoleAssignment;
use App\Enum\RoleName;
use App\Enum\ScopeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class EnvelopeFormatTest extends WebTestCase
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

    public function testSuccessfulResponseHasCorrectEnvelopeFormat(): void
    {
        $this->createTestUser('envelope_ok', 'V@lid1Password!');

        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => 'envelope_ok',
            'password' => 'V@lid1Password!',
        ]));

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);

        // Verify envelope structure: {data, meta: {request_id, timestamp}, error: null}
        self::assertArrayHasKey('data', $body);
        self::assertNotNull($body['data']);
        self::assertArrayHasKey('meta', $body);
        self::assertArrayHasKey('request_id', $body['meta']);
        self::assertArrayHasKey('timestamp', $body['meta']);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $body['meta']['request_id'],
            'request_id should be a UUID.',
        );
        self::assertArrayHasKey('error', $body);
        self::assertNull($body['error']);
    }

    public function testErrorResponseHasCorrectEnvelopeFormat(): void
    {
        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => 'nonexistent_envelope',
            'password' => 'SomePassword123!',
        ]));

        $response = $this->client->getResponse();
        self::assertSame(401, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);

        // Verify envelope structure: {data: null, meta: {request_id, timestamp}, error: {code, message, details}}
        self::assertArrayHasKey('data', $body);
        self::assertNull($body['data']);
        self::assertArrayHasKey('meta', $body);
        self::assertArrayHasKey('request_id', $body['meta']);
        self::assertArrayHasKey('timestamp', $body['meta']);
        self::assertArrayHasKey('error', $body);
        self::assertIsArray($body['error']);
        self::assertArrayHasKey('code', $body['error']);
        self::assertArrayHasKey('message', $body['error']);
        self::assertArrayHasKey('details', $body['error']);
        self::assertIsString($body['error']['code']);
        self::assertIsString($body['error']['message']);
    }

    public function testValidationErrorIncludesDetails(): void
    {
        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $response = $this->client->getResponse();
        self::assertSame(422, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);

        self::assertNull($body['data']);
        self::assertSame('VALIDATION_ERROR', $body['error']['code']);
        self::assertNotEmpty($body['error']['details']);
    }

    public function testPaginationResponseIncludesMetaPagination(): void
    {
        $token = $this->loginAsAdmin();

        // Create a region so the list is not empty.
        $this->createRegion('ENVPG');

        $this->client->request('GET', '/api/v1/regions?per_page=5&page=1', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);

        // Verify paginated envelope.
        self::assertArrayHasKey('data', $body);
        self::assertIsArray($body['data']);
        self::assertNull($body['error']);
        self::assertArrayHasKey('meta', $body);
        self::assertArrayHasKey('pagination', $body['meta']);

        $pagination = $body['meta']['pagination'];
        self::assertArrayHasKey('page', $pagination);
        self::assertArrayHasKey('per_page', $pagination);
        self::assertArrayHasKey('total', $pagination);
        self::assertArrayHasKey('total_pages', $pagination);
        self::assertSame(1, $pagination['page']);
        self::assertSame(5, $pagination['per_page']);
        self::assertIsInt($pagination['total']);
        self::assertIsInt($pagination['total_pages']);
    }

    // -----------------------------------------------------------------------
    // Helper methods
    // -----------------------------------------------------------------------

    private function loginAsAdmin(): string
    {
        static $counter = 0;
        $counter++;

        $suffix = 'envelope_admin_' . $counter;

        $user = $this->createTestUser($suffix, 'V@lid1Password!');
        $role = $this->getOrCreateRole(RoleName::ADMINISTRATOR);
        $this->createAssignment($user, $role, ScopeType::GLOBAL, null, $user);

        return $this->loginAndGetToken($suffix, 'V@lid1Password!');
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

    private function createTestUser(string $username, string $plainPassword = 'V@lid1Password!'): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setDisplayName('Test ' . $username);
        $user->setStatus('ACTIVE');

        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPasswordHash($hashedPassword);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createRegion(string $code): MdmRegion
    {
        $region = new MdmRegion();
        $region->setCode($code);
        $region->setName('Region ' . $code);
        $region->setEffectiveFrom(new \DateTimeImmutable('-30 days'));
        $region->setIsActive(true);

        $this->em->persist($region);
        $this->em->flush();

        return $region;
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
}
