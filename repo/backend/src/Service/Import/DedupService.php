<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\ContentFingerprint;
use App\Entity\ImportItem;
use App\Repository\ContentFingerprintRepository;

class DedupService
{
    private const float THRESHOLD_AUTO_MERGE = 0.92;
    private const float THRESHOLD_REVIEW = 0.80;

    public function __construct(
        private readonly ContentFingerprintRepository $fingerprintRepository,
    ) {
    }

    /**
     * Find matching content fingerprints for the given import item.
     *
     * Returns an array of matches, each containing:
     *   - fingerprint: ContentFingerprint entity
     *   - similarity: float score (0.0 to 1.0)
     *   - action: 'AUTO_MERGE' | 'REVIEW_NEEDED' | 'NO_MATCH'
     *
     * @return array<int, array{fingerprint: ContentFingerprint, similarity: float, action: string}>
     */
    public function findMatches(ImportItem $item): array
    {
        $itemFingerprint = $item->getDedupFingerprint();

        // Exact match check first
        $exactMatches = $this->fingerprintRepository->findBy(['fingerprint' => $itemFingerprint]);

        if (\count($exactMatches) > 0) {
            return array_map(
                static fn (ContentFingerprint $fp) => [
                    'fingerprint' => $fp,
                    'similarity' => 1.0,
                    'action' => 'AUTO_MERGE',
                ],
                $exactMatches,
            );
        }

        // Trigram similarity against all existing fingerprints
        $allFingerprints = $this->fingerprintRepository->findAll();
        $itemTrigrams = $this->computeTrigrams($itemFingerprint);
        $matches = [];

        foreach ($allFingerprints as $fp) {
            $existingTrigrams = $this->computeTrigrams($fp->getFingerprint());
            $similarity = $this->jaccardSimilarity($itemTrigrams, $existingTrigrams);

            if ($similarity >= self::THRESHOLD_REVIEW) {
                $action = $similarity >= self::THRESHOLD_AUTO_MERGE
                    ? 'AUTO_MERGE'
                    : 'REVIEW_NEEDED';

                $matches[] = [
                    'fingerprint' => $fp,
                    'similarity' => $similarity,
                    'action' => $action,
                ];
            }
        }

        // Sort by similarity descending
        usort($matches, static fn (array $a, array $b) => $b['similarity'] <=> $a['similarity']);

        return $matches;
    }

    /**
     * Compute trigrams (3-character substrings) from a string.
     *
     * @return array<string, true>
     */
    private function computeTrigrams(string $text): array
    {
        $trigrams = [];
        $length = strlen($text);

        for ($i = 0; $i <= $length - 3; $i++) {
            $trigrams[substr($text, $i, 3)] = true;
        }

        return $trigrams;
    }

    /**
     * Compute Jaccard similarity between two trigram sets.
     *
     * @param array<string, true> $setA
     * @param array<string, true> $setB
     */
    private function jaccardSimilarity(array $setA, array $setB): float
    {
        if (\count($setA) === 0 && \count($setB) === 0) {
            return 1.0;
        }

        $intersectionCount = \count(array_intersect_key($setA, $setB));
        $unionCount = \count($setA) + \count($setB) - $intersectionCount;

        if ($unionCount === 0) {
            return 0.0;
        }

        return $intersectionCount / $unionCount;
    }
}
