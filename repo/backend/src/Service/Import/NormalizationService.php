<?php

declare(strict_types=1);

namespace App\Service\Import;

class NormalizationService
{
    /**
     * Normalize text: lowercase, trim, collapse repeated whitespace,
     * remove punctuation except alphanumeric separators (hyphens, underscores).
     */
    public function normalize(string $text): string
    {
        // Lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // Trim leading/trailing whitespace
        $text = trim($text);

        // Remove punctuation except hyphens, underscores, and alphanumeric characters
        $text = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $text);

        // Collapse repeated whitespace into a single space
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
