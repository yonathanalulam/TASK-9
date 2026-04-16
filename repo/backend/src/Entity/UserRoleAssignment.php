<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ScopeType;
use App\Repository\UserRoleAssignmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRoleAssignmentRepository::class)]
#[ORM\Table(name: 'user_role_assignments')]
#[ORM\Index(columns: ['user_id'])]
#[ORM\Index(columns: ['role_id'])]
#[ORM\Index(columns: ['scope_type', 'scope_id'])]
#[ORM\Index(columns: ['effective_from', 'effective_until'])]
class UserRoleAssignment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Role $role;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ScopeType::class)]
    private ScopeType $scopeType;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $scopeId = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $effectiveFrom;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $effectiveUntil = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $grantedBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $grantedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $revokedBy = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->grantedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getScopeType(): ScopeType
    {
        return $this->scopeType;
    }

    public function setScopeType(ScopeType $scopeType): static
    {
        $this->scopeType = $scopeType;

        return $this;
    }

    public function getScopeId(): ?string
    {
        return $this->scopeId;
    }

    public function setScopeId(?string $scopeId): static
    {
        $this->scopeId = $scopeId;

        return $this;
    }

    public function getEffectiveFrom(): \DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function setEffectiveFrom(\DateTimeImmutable $effectiveFrom): static
    {
        $this->effectiveFrom = $effectiveFrom;

        return $this;
    }

    public function getEffectiveUntil(): ?\DateTimeImmutable
    {
        return $this->effectiveUntil;
    }

    public function setEffectiveUntil(?\DateTimeImmutable $effectiveUntil): static
    {
        $this->effectiveUntil = $effectiveUntil;

        return $this;
    }

    public function getGrantedBy(): User
    {
        return $this->grantedBy;
    }

    public function setGrantedBy(User $grantedBy): static
    {
        $this->grantedBy = $grantedBy;

        return $this;
    }

    public function getGrantedAt(): \DateTimeImmutable
    {
        return $this->grantedAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?\DateTimeImmutable $revokedAt): static
    {
        $this->revokedAt = $revokedAt;

        return $this;
    }

    public function getRevokedBy(): ?User
    {
        return $this->revokedBy;
    }

    public function setRevokedBy(?User $revokedBy): static
    {
        $this->revokedBy = $revokedBy;

        return $this;
    }
}
