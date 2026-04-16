<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRoleAssignment;
use App\Enum\RoleName;
use App\Enum\ScopeType;
use App\Service\Authorization\RbacService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RbacServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RbacService $rbacService;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->rbacService = $container->get(RbacService::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
    }

    public function testGetEffectiveAssignmentsReturnsCorrectAssignments(): void
    {
        $user = $this->createTestUser('rbac_user_1');
        $grantor = $this->createTestUser('rbac_grantor_1');
        $role = $this->createRole(RoleName::ADMINISTRATOR);

        $assignment = $this->createAssignment($user, $role, ScopeType::GLOBAL, null, $grantor);

        $assignments = $this->rbacService->getEffectiveAssignments($user);

        self::assertCount(1, $assignments);
        self::assertSame($role->getName(), $assignments[0]->getRole()->getName());
    }

    public function testHasRoleReturnsTrueForMatchingRole(): void
    {
        $user = $this->createTestUser('rbac_user_2');
        $grantor = $this->createTestUser('rbac_grantor_2');
        $role = $this->createRole(RoleName::STORE_MANAGER);

        $this->createAssignment($user, $role, ScopeType::GLOBAL, null, $grantor);

        self::assertTrue($this->rbacService->hasRole($user, RoleName::STORE_MANAGER));
    }

    public function testHasRoleReturnsFalseForNonMatchingRole(): void
    {
        $user = $this->createTestUser('rbac_user_3');
        $grantor = $this->createTestUser('rbac_grantor_3');
        $role = $this->createRole(RoleName::DISPATCHER);

        $this->createAssignment($user, $role, ScopeType::GLOBAL, null, $grantor);

        self::assertFalse($this->rbacService->hasRole($user, RoleName::ADMINISTRATOR));
    }

    public function testGlobalScopeSatisfiesAnyScopeCheck(): void
    {
        $user = $this->createTestUser('rbac_user_4');
        $grantor = $this->createTestUser('rbac_grantor_4');
        $role = $this->createRole(RoleName::ADMINISTRATOR);

        $this->createAssignment($user, $role, ScopeType::GLOBAL, null, $grantor);

        // A GLOBAL-scoped assignment should satisfy a REGION scope check.
        $fakeScopeId = hex2bin('0192e4a0b6d07e9b8e3f0a1b2c3d4e5f');
        self::assertTrue($this->rbacService->hasRole($user, RoleName::ADMINISTRATOR, ScopeType::REGION, $fakeScopeId));
    }

    public function testRegionScopeMatchesCorrectRegion(): void
    {
        $user = $this->createTestUser('rbac_user_5');
        $grantor = $this->createTestUser('rbac_grantor_5');
        $role = $this->createRole(RoleName::STORE_MANAGER);

        // scope_id is now VARCHAR(36) — use RFC4122 UUID string format, not binary.
        $regionScopeId = '0192e4a0-b6d0-7e9b-8e3f-0a1b2c3d4e5f';
        $this->createAssignment($user, $role, ScopeType::REGION, $regionScopeId, $grantor);

        self::assertTrue(
            $this->rbacService->hasRole($user, RoleName::STORE_MANAGER, ScopeType::REGION, $regionScopeId),
        );

        // A different region ID should not match.
        $otherRegionId = '0192e4a0-b6d0-7e9b-8e3f-0a1b2c3d4e60';
        self::assertFalse(
            $this->rbacService->hasRole($user, RoleName::STORE_MANAGER, ScopeType::REGION, $otherRegionId),
        );
    }

    public function testExpiredAssignmentIsNotReturned(): void
    {
        $user = $this->createTestUser('rbac_user_6');
        $grantor = $this->createTestUser('rbac_grantor_6');
        $role = $this->createRole(RoleName::DISPATCHER);

        $assignment = $this->createAssignment($user, $role, ScopeType::GLOBAL, null, $grantor);
        // Set effectiveUntil in the past.
        $assignment->setEffectiveUntil(new \DateTimeImmutable('-1 day'));
        $this->em->flush();

        $assignments = $this->rbacService->getEffectiveAssignments($user);

        self::assertCount(0, $assignments);
    }

    public function testRevokedAssignmentIsNotReturned(): void
    {
        $user = $this->createTestUser('rbac_user_7');
        $grantor = $this->createTestUser('rbac_grantor_7');
        $role = $this->createRole(RoleName::OPERATIONS_ANALYST);

        $assignment = $this->createAssignment($user, $role, ScopeType::GLOBAL, null, $grantor);
        // Revoke the assignment.
        $assignment->setRevokedAt(new \DateTimeImmutable());
        $assignment->setRevokedBy($grantor);
        $this->em->flush();

        $assignments = $this->rbacService->getEffectiveAssignments($user);

        self::assertCount(0, $assignments);
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

    private function createRole(RoleName $roleName): Role
    {
        // Check if the role already exists to avoid unique constraint violations.
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
