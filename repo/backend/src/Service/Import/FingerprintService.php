<?php

declare(strict_types=1);

namespace App\Service\Import;

class FingerprintService
{
    /**
     * Compute a SHA-256 fingerprint from normalized fields.
     *
     * Concatenates fields with pipe separator, truncates body to first 200 chars.
     */
    public function computeFingerprint(
        string $normalizedTitle,
        ?string $normalizedCompany,
        ?string $normalizedLocation,
        ?string $normalizedBody,
    ): string {
        $bodyTruncated = $normalizedBody !== null
            ? mb_substr($normalizedBody, 0, 200, 'UTF-8')
            : '';

        $payload = implode('|', [
            $normalizedTitle,
            $normalizedCompany ?? '',
            $normalizedLocation ?? '',
            $bodyTruncated,
        ]);

        return hash('sha256', $payload);
    }
}
