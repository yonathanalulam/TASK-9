<?php

declare(strict_types=1);

namespace App\Service\Export;

class TamperDetectionService
{
    /**
     * Compute the SHA-256 hash of a file's content.
     */
    public function computeFileHash(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException('File not found: ' . $filePath);
        }

        $hash = hash_file('sha256', $filePath);

        if ($hash === false) {
            throw new \RuntimeException('Failed to compute hash for file: ' . $filePath);
        }

        return $hash;
    }

    /**
     * Compute a chained hash for compliance report integrity.
     *
     * Combines the current file hash with the previous report's hash
     * to form an immutable chain.
     */
    public function computeReportChainHash(string $fileHash, ?string $previousReportHash): string
    {
        if ($previousReportHash === null) {
            return $fileHash;
        }

        return hash('sha256', $previousReportHash . $fileHash);
    }
}
