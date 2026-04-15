<?php

declare(strict_types=1);

namespace App\Entity\Warehouse;

use App\Repository\Warehouse\DimChannelRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DimChannelRepository::class)]
#[ORM\Table(name: 'wh_dim_channel')]
class DimChannel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $channelKey = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $channelCode;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $channelName;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $channelType;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isCurrent = true;

    public function getChannelKey(): ?int
    {
        return $this->channelKey;
    }

    public function getChannelCode(): string
    {
        return $this->channelCode;
    }

    public function setChannelCode(string $channelCode): static
    {
        $this->channelCode = $channelCode;

        return $this;
    }

    public function getChannelName(): string
    {
        return $this->channelName;
    }

    public function setChannelName(string $channelName): static
    {
        $this->channelName = $channelName;

        return $this;
    }

    public function getChannelType(): string
    {
        return $this->channelType;
    }

    public function setChannelType(string $channelType): static
    {
        $this->channelType = $channelType;

        return $this;
    }

    public function isCurrent(): bool
    {
        return $this->isCurrent;
    }

    public function setIsCurrent(bool $isCurrent): static
    {
        $this->isCurrent = $isCurrent;

        return $this;
    }
}
