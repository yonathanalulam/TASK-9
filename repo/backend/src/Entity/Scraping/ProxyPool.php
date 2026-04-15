<?php

declare(strict_types=1);

namespace App\Entity\Scraping;

use App\Repository\Scraping\ProxyPoolRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProxyPoolRepository::class)]
#[ORM\Table(name: 'scrape_proxy_pools')]
class ProxyPool
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 512)]
    private string $proxyUrl;

    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $proxyType;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'ACTIVE'])]
    private string $status = 'ACTIVE';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $banCount = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $cooldownUntil = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProxyUrl(): string
    {
        return $this->proxyUrl;
    }

    public function setProxyUrl(string $proxyUrl): static
    {
        $this->proxyUrl = $proxyUrl;

        return $this;
    }

    public function getProxyType(): string
    {
        return $this->proxyType;
    }

    public function setProxyType(string $proxyType): static
    {
        $this->proxyType = $proxyType;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;

        return $this;
    }

    public function getBanCount(): int
    {
        return $this->banCount;
    }

    public function setBanCount(int $banCount): static
    {
        $this->banCount = $banCount;

        return $this;
    }

    public function getCooldownUntil(): ?\DateTimeImmutable
    {
        return $this->cooldownUntil;
    }

    public function setCooldownUntil(?\DateTimeImmutable $cooldownUntil): static
    {
        $this->cooldownUntil = $cooldownUntil;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
