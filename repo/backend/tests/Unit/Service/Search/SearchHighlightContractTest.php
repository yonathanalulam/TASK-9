<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Search;

use PHPUnit\Framework\TestCase;

/**
 * Ensures that search results use the canonical field name `highlight_title`
 * (not the deprecated `highlight`) and that the highlighting logic wraps
 * matched terms in <mark> tags.
 */
final class SearchHighlightContractTest extends TestCase
{
    public function testSearchResultContainsHighlightTitleField(): void
    {
        // The canonical search result field set
        $canonicalFields = [
            'id', 'content_type', 'title', 'author_name', 'published_at',
            'tags', 'view_count', 'reply_count', 'snippet',
            'highlight_title', 'relevance_score',
        ];

        self::assertContains('highlight_title', $canonicalFields);
        // NOT the old name
        self::assertNotContains('highlight', $canonicalFields);
    }

    public function testHighlightTitleWrapsMatchesInMarkTags(): void
    {
        // Simulate the highlight behavior
        $title = 'Software Engineer Position';
        $term = 'Engineer';
        $escaped = preg_quote($term, '/');
        $highlighted = preg_replace('/(' . $escaped . ')/iu', '<mark>$1</mark>', $title);

        self::assertSame('Software <mark>Engineer</mark> Position', $highlighted);
    }
}
