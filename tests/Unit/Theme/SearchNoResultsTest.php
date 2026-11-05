<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Theme;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversNothing;
use Pixonaut\Shop\Tests\TestCase;

/**
 * `patterns/search-no-results.php`, which prints what a customer searched
 * for back to them: as text, whatever they typed. `Archive\NoResultsTitle`
 * prints it again as it renders.
 */
#[CoversNothing]
final class SearchNoResultsTest extends TestCase
{
    private const PATTERN = __DIR__ . '/../../../patterns/search-no-results.php';

    /** A query that would run as a script if it were printed as markup. */
    private const QUERY = '<script>alert("x")</script>';

    public function testNamesTheQueryAsText(): void
    {
        Functions\stubEscapeFunctions();
        Functions\stubTranslationFunctions();
        // The unescaped query, so the pattern escapes it once, for HTML.
        Functions\expect('get_search_query')->once()->with(false)->andReturn(self::QUERY);

        $markup = $this->render();

        self::assertStringNotContainsString(self::QUERY, $markup);
        self::assertStringContainsString(
            'No results for “' . htmlspecialchars(self::QUERY, \ENT_QUOTES) . '”',
            $markup
        );
    }

    private function render(): string
    {
        ob_start();
        require self::PATTERN;

        return (string) ob_get_clean();
    }
}
