<?php

declare(strict_types=1);

namespace App\Service\Warehouse;

use App\Entity\Warehouse\DimTime;
use Doctrine\ORM\EntityManagerInterface;

class TimeDimensionSeeder
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Seed the wh_dim_time table for every day in the given range (inclusive).
     *
     * @return int Number of rows inserted
     */
    public function seed(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $conn = $this->em->getConnection();
        $existing = $conn->fetchFirstColumn('SELECT time_key FROM wh_dim_time');
        $existingSet = array_flip($existing);

        $current = $from;
        $inserted = 0;
        $batchSize = 500;

        while ($current <= $to) {
            $timeKey = (int) $current->format('Ymd');

            if (!isset($existingSet[$timeKey])) {
                $dim = new DimTime();
                $dim->setTimeKey($timeKey);
                $dim->setFullDate($current);
                $dim->setDayOfWeek((int) $current->format('N'));
                $dim->setDayName($current->format('l'));
                $dim->setDayOfMonth((int) $current->format('j'));
                $dim->setDayOfYear((int) $current->format('z') + 1);
                $dim->setWeekOfYear((int) $current->format('W'));
                $dim->setMonthNumber((int) $current->format('n'));
                $dim->setMonthName($current->format('F'));
                $dim->setQuarter((int) ceil((int) $current->format('n') / 3));
                $dim->setYear((int) $current->format('Y'));
                $dim->setIsWeekend(in_array((int) $current->format('N'), [6, 7], true));

                $this->em->persist($dim);
                $inserted++;

                if ($inserted % $batchSize === 0) {
                    $this->em->flush();
                    $this->em->clear(DimTime::class);
                }
            }

            $current = $current->modify('+1 day');
        }

        if ($inserted % $batchSize !== 0) {
            $this->em->flush();
            $this->em->clear(DimTime::class);
        }

        return $inserted;
    }
}
