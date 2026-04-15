<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MdmRegionVersionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MdmRegionVersionRepository::class)]
#[ORM\Table(name: 'mdm_region_versions')]
#[ORM\UniqueConstraint(name: 'uq_mdm_region_version', columns: ['region_id', 'version_number'])]
class MdmRegionVersion
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: MdmRegion::class)]
    #[ORM\JoinColumn(name: 'region_id', referencedColumnName: 'id', nullable: false)]
    private MdmRegion $region;

    #[ORM\Column(type: Types::INTEGER)]
    private int $versionNumber;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $changeType;

    #[ORM\Column(type: Types::JSON)]
    private array $snapshot = [];

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'changed_by', referencedColumnName: 'id', nullable: false)]
    private User $changedBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $changedAt;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $changeReason = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->changedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRegion(): MdmRegion
    {
        return $this->region;
    }

    public function setRegion(MdmRegion $region): static
    {
        $this->region = $region;

        return $this;
    }

    public function getVersionNumber(): int
    {
        return $this->versionNumber;
    }

    public function setVersionNumber(int $versionNumber): static
    {
        $this->versionNumber = $versionNumber;

        return $this;
    }

    public function getChangeType(): string
    {
        return $this->changeType;
    }

    public function setChangeType(string $changeType): static
    {
        $this->changeType = $changeType;

        return $this;
    }

    public function getSnapshot(): array
    {
        return $this->snapshot;
    }

    public function setSnapshot(array $snapshot): static
    {
        $this->snapshot = $snapshot;

        return $this;
    }

    public function getChangedBy(): User
    {
        return $this->changedBy;
    }

    public function setChangedBy(User $changedBy): static
    {
        $this->changedBy = $changedBy;

        return $this;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function setChangedAt(\DateTimeImmutable $changedAt): static
    {
        $this->changedAt = $changedAt;

        return $this;
    }

    public function getChangeReason(): ?string
    {
        return $this->changeReason;
    }

    public function setChangeReason(?string $changeReason): static
    {
        $this->changeReason = $changeReason;

        return $this;
    }
}
