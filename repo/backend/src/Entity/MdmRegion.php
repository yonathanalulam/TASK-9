<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MdmRegionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MdmRegionRepository::class)]
#[ORM\Table(name: 'mdm_regions')]
#[ORM\Index(columns: ['parent_id'], name: 'idx_mdm_regions_parent_id')]
#[ORM\Index(columns: ['is_active'], name: 'idx_mdm_regions_is_active')]
class MdmRegion
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 5, unique: true)]
    private string $code;

    #[ORM\Column(type: Types::STRING, length: 150)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: MdmRegion::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true)]
    private ?MdmRegion $parent = null;

    /** @var Collection<int, MdmRegion> */
    #[ORM\OneToMany(targetEntity: MdmRegion::class, mappedBy: 'parent')]
    private Collection $children;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $hierarchyLevel = 0;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $effectiveFrom;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $effectiveUntil = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $version = 1;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->children = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getParent(): ?MdmRegion
    {
        return $this->parent;
    }

    public function setParent(?MdmRegion $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /** @return Collection<int, MdmRegion> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(MdmRegion $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(MdmRegion $child): static
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    public function getHierarchyLevel(): int
    {
        return $this->hierarchyLevel;
    }

    public function setHierarchyLevel(int $hierarchyLevel): static
    {
        $this->hierarchyLevel = $hierarchyLevel;

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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
