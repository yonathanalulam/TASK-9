<?php

declare(strict_types=1);

namespace App\Service\Export;

class ExportFileNamer
{
    /**
     * Generate a standardized export file name.
     *
     * Format: {dataset}_{username}_{MM-DD-YYYY_hh-mm-AMPM}.{ext}
     */
    public function generate(string $dataset, string $username, string $extension): string
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $timestamp = $now->format('m-d-Y_h-i-A');

        return sprintf(
            '%s_%s_%s.%s',
            $this->sanitize($dataset),
            $this->sanitize($username),
            $timestamp,
            ltrim($extension, '.'),
        );
    }

    /**
     * Sanitize a string for safe use in file names.
     */
    private function sanitize(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $value);
    }
}
