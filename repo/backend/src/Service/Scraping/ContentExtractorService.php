<?php

declare(strict_types=1);

namespace App\Service\Scraping;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Extracts structured data from HTML using CSS selectors via Symfony DomCrawler.
 */
class ContentExtractorService
{
    /**
     * Parse HTML and extract data using the given CSS selectors.
     *
     * @param string $html Raw HTML content
     * @param array<string, string> $selectors Map of field name to CSS selector
     * @return array<string, list<string>> Extracted values keyed by field name
     */
    public function extract(string $html, array $selectors): array
    {
        $crawler = new Crawler($html);
        $result = [];

        foreach ($selectors as $field => $selector) {
            try {
                $nodes = $crawler->filter($selector);
                $values = [];

                $nodes->each(static function (Crawler $node) use (&$values): void {
                    $text = trim($node->text(''));
                    if ($text !== '') {
                        $values[] = $text;
                    }
                });

                $result[$field] = $values;
            } catch (\Throwable) {
                $result[$field] = [];
            }
        }

        return $result;
    }
}
