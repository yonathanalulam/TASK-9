<?php

declare(strict_types=1);

namespace App\Service\Scraping;

/**
 * Returns a random delay in milliseconds for request jitter.
 */
class JitterService
{
    /**
     * Get a random delay between 250ms and 2000ms.
     */
    public function getDelay(): int
    {
        return random_int(250, 2000);
    }
}
