<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ConsentRecordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ConsentRecordRepository::class)]
#[ORM\Table(name: 'consent_records')]
#[ORM\Index(columns: ['user_id', 'consent_type'], name: 'idx_consent_records_user_type')]
class ConsentRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $consentType;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $consentScope;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $granted;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $ipAddress;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $userAgent;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $user,
        string $consentType,
        string $consentScope,
        bool $granted,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ) {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->consentType = $consentType;
        $this->consentScope = $consentScope;
        $this->granted = $granted;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getConsentType(): string
    {
        return $this->consentType;
    }

    public function getConsentScope(): string
    {
        return $this->consentScope;
    }

    public function isGranted(): bool
    {
        return $this->granted;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
