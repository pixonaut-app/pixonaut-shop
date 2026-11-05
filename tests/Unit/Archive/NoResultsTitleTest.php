<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Archive;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Archive\NoResultsTitle;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(NoResultsTitle::class)]
final class NoResultsTitleTest extends TestCase
{
    /** A query that would run as a script if it were printed as markup. */
    private const QUERY = '<script>alert("x")</script>';

    /** The heading as a template saved in the Site Editor holds it: with the editor's empty query. */
    private const SAVED_HEADING = '<h2 class="wp-block-heading ' . NoResultsTitle::CLASS_NAME . '">No results for “”</h2>';

    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubEscapeFunctions();
        Functions\stubTranslationFunctions();
        // The unescaped query, so the title is escaped once, for HTML.
        Functions\expect('get_search_query')->with(false)->andReturn(self::QUERY);
    }

    public function testRegistersAsAHeadingRenders(): void
    {
        (new NoResultsTitle())->register();

        self::assertNotFalse(has_filter('render_block_core/heading', 'Pixonaut\Shop\Archive\NoResultsTitle->render()'));
    }

    public function testNamesThisRequestsQueryAsText(): void
    {
        $heading = (new NoResultsTitle())->render(self::SAVED_HEADING, $this->title_block());

        self::assertSame(
            '<h2 class="wp-block-heading ' . NoResultsTitle::CLASS_NAME . '">No results for “'
            . htmlspecialchars(self::QUERY, \ENT_QUOTES) . '”</h2>',
            $heading
        );
    }

    public function testLeavesEveryOtherHeadingAlone(): void
    {
        $heading = '<h2 class="wp-block-heading">Popular right now</h2>';

        self::assertSame($heading, (new NoResultsTitle())->render($heading, ['attrs' => ['className' => 'is-style-panel']]));
        self::assertSame($heading, (new NoResultsTitle())->render($heading, ['attrs' => []]));
    }

    /**
     * @return array{attrs: array{className: string}}
     */
    private function title_block(): array
    {
        return ['attrs' => ['className' => NoResultsTitle::CLASS_NAME]];
    }
}
