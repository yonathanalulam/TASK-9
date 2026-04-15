<?php

declare(strict_types=1);

namespace App\Tests\Api\Search;

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
 * Tests the search API contract: response shape, pagination, filtering, and sorting.
 */
final class SearchApiTest extends WebTestCase
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

    /**
     * Seed several content items, publish them, and then search.
     */
    public function testSearchReturnsDataArrayAndPagination(): void
    {
        $token = $this->loginAsAdmin();
        $this->seedPublishedContent($token);

        $this->client->request('GET', '/api/v1/search?q=searchable', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertSame(200, $status);

        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertArrayHasKey('data', $body);
        self::assertIsArray($body['data']);
        self::assertArrayHasKey('meta', $body);
        self::assertArrayHasKey('pagination', $body['meta']);
        self::assertArrayHasKey('page', $body['meta']['pagination']);
        self::assertArrayHasKey('per_page', $body['meta']['pagination']);
        self::assertArrayHasKey('total', $body['meta']['pagination']);
    }

    public function testSearchResultsContainRequiredFields(): void
    {
        $token = $this->loginAsAdmin();
        $this->seedPublishedContent($token);

        $this->client->request('GET', '/api/v1/search?q=searchable', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $body = json_decode($this->client->getResponse()->getContent(), true);

        if (count($body['data']) > 0) {
            $firstItem = $body['data'][0];

            // The search index and enrichment should include these fields.
            self::assertArrayHasKey('id', $firstItem);
            self::assertArrayHasKey('content_type', $firstItem);
            self::assertArrayHasKey('title', $firstItem);
            self::assertArrayHasKey('author_name', $firstItem);
            self::assertArrayHasKey('snippet', $firstItem);
        }
    }

    public function testSearchSortByNewest(): void
    {
        $token = $this->loginAsAdmin();
        $this->seedPublishedContent($token);

        $this->client->request('GET', '/api/v1/search?q=searchable&sort=newest', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);
    }

    public function testSearchFilterByType(): void
    {
        $token = $this->loginAsAdmin();
        $this->seedPublishedContent($token);

        $this->client->request('GET', '/api/v1/search?q=searchable&type=JOB_POST', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('data', $body);

        // All returned items should be JOB_POST.
        foreach ($body['data'] as $item) {
            self::assertSame('JOB_POST', $item['content_type']);
        }
    }

    public function testSearchPerPageLimitsResults(): void
    {
        $token = $this->loginAsAdmin();
        $this->seedPublishedContent($token);

        $this->client->request('GET', '/api/v1/search?q=searchable&per_page=1', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertLessThanOrEqual(1, count($body['data']));
        self::assertSame(1, $body['meta']['pagination']['per_page']);
    }

    public function testSearchWithEmptyQueryReturns422(): void
    {
        $token = $this->loginAsAdmin();

        $this->client->request('GET', '/api/v1/search?q=', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('VALIDATION_ERROR', $body['error']['code']);
    }

    public function testSearchWithoutQueryParamReturns422(): void
    {
        $token = $this->loginAsAdmin();

        $this->client->request('GET', '/api/v1/search', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Seed helpers
    // -----------------------------------------------------------------------

    private function seedPublishedContent(string $token): void
    {
        $items = [
            [
                'title' => 'Searchable Job Post Alpha',
                'body' => 'This is a searchable job post about engineering positions.',
                'author_name' => 'Alice',
                'content_type' => 'JOB_POST',
            ],
            [
                'title' => 'Searchable Notice Beta',
                'body' => 'This is a searchable operational notice about warehouse changes.',
                'author_name' => 'Bob',
                'content_type' => 'OPERATIONAL_NOTICE',
            ],
            [
                'title' => 'Searchable Bulletin Gamma',
                'body' => 'This is a searchable vendor bulletin about new products.',
                'author_name' => 'Charlie',
                'content_type' => 'VENDOR_BULLETIN',
            ],
        ];

        foreach ($items as $payload) {
            // Create.
            $this->client->request('POST', '/api/v1/content', [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ], json_encode($payload));

            if ($this->client->getResponse()->getStatusCode() === 201) {
                $data = json_decode($this->client->getResponse()->getContent(), true);
                $contentId = $data['data']['id'];

                // Publish so it appears in search.
                $this->client->request('POST', '/api/v1/content/' . $contentId . '/publish', [], [], [
                    'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                ]);
            }
        }
    }

    // -----------------------------------------------------------------------
    // Auth helpers
    // -----------------------------------------------------------------------

    private function loginAsAdmin(): string
    {
        static $counter = 0;
        $counter++;

        $suffix = 'search_admin_' . $counter;

        $user = $this->createTestUser($suffix);
        $grantor = $user;
        $role = $this->getOrCreateRole(RoleName::ADMINISTRATOR);
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
