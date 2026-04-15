<?php

declare(strict_types=1);

namespace App\Entity\Warehouse;

use App\Repository\Warehouse\DimTimeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DimTimeRepository::class)]
#[ORM\Table(name: 'wh_dim_time')]
class DimTime
{
    /** Primary key in YYYYMMDD format. */
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    private int $timeKey;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, unique: true)]
    private \DateTimeImmutable $fullDate;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $dayOfWeek;

    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $dayName;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $dayOfMonth;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $dayOfYear;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $weekOfYear;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $monthNumber;

    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $monthName;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $quarter;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $year;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isWeekend;

    public function getTimeKey(): int
    {
        return $this->timeKey;
    }

    public function setTimeKey(int $timeKey): static
    {
        $this->timeKey = $timeKey;

        return $this;
    }

    public function getFullDate(): \DateTimeImmutable
    {
        return $this->fullDate;
    }

    public function setFullDate(\DateTimeImmutable $fullDate): static
    {
        $this->fullDate = $fullDate;

        return $this;
    }

    public function getDayOfWeek(): int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(int $dayOfWeek): static
    {
        $this->dayOfWeek = $dayOfWeek;

        return $this;
    }

    public function getDayName(): string
    {
        return $this->dayName;
    }

    public function setDayName(string $dayName): static
    {
        $this->dayName = $dayName;

        return $this;
    }

    public function getDayOfMonth(): int
    {
        return $this->dayOfMonth;
    }

    public function setDayOfMonth(int $dayOfMonth): static
    {
        $this->dayOfMonth = $dayOfMonth;

        return $this;
    }

    public function getDayOfYear(): int
    {
        return $this->dayOfYear;
    }

    public function setDayOfYear(int $dayOfYear): static
    {
        $this->dayOfYear = $dayOfYear;

        return $this;
    }

    public function getWeekOfYear(): int
    {
        return $this->weekOfYear;
    }

    public function setWeekOfYear(int $weekOfYear): static
    {
        $this->weekOfYear = $weekOfYear;

        return $this;
    }

    public function getMonthNumber(): int
    {
        return $this->monthNumber;
    }

    public function setMonthNumber(int $monthNumber): static
    {
        $this->monthNumber = $monthNumber;

        return $this;
    }

    public function getMonthName(): string
    {
        return $this->monthName;
    }

    public function setMonthName(string $monthName): static
    {
        $this->monthName = $monthName;

        return $this;
    }

    public function getQuarter(): int
    {
        return $this->quarter;
    }

    public function setQuarter(int $quarter): static
    {
        $this->quarter = $quarter;

        return $this;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function isWeekend(): bool
    {
        return $this->isWeekend;
    }

    public function setIsWeekend(bool $isWeekend): static
    {
        $this->isWeekend = $isWeekend;

        return $this;
    }
}
