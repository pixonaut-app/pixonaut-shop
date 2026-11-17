<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Header;

use ArrayObject;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Assets\BuiltScript;
use Pixonaut\Shop\Blocks\Icons;
use Pixonaut\Shop\Header\HeaderSearch;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(HeaderSearch::class)]
#[CoversClass(BuiltScript::class)]
#[CoversClass(Icons::class)]
final class HeaderSearchTest extends TestCase
{
    /** What core prints for the block, which the header's search replaces. */
    private const CORE_OUTPUT = '<form role="search" class="wp-block-search__button-only wp-block-search"></form>';

    /** A theme checkout whose script has been built, and one whose has not. */
    private const BUILT_THEME = __DIR__ . '/fixtures/built';

    private const UNBUILT_THEME = __DIR__ . '/fixtures/unbuilt';

    private const THEME_URI = 'https://shop.test/wp-content/themes/pixonaut-shop';

    private const SHOP_URL = 'https://shop.test';

    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubEscapeFunctions();
        Functions\stubTranslationFunctions();
        $counter = new ArrayObject();
        Functions\when('wp_unique_id')->alias(static function (string $prefix) use ($counter): string {
            $counter[$prefix] = ($counter[$prefix] ?? 0) + 1;

            return $prefix . $counter[$prefix];
        });
        Functions\when('home_url')->alias(static fn (string $path = ''): string => self::SHOP_URL . $path);
        Functions\when('add_query_arg')->alias(static fn (array $args, string $url): string => $url . '?' . http_build_query($args));
        Functions\when('wp_strip_all_tags')->returnArg();
        // No searches counted, and no products to show, unless a test says otherwise.
        Functions\when('get_option')->justReturn([]);
        Functions\when('wc_get_products')->justReturn([]);
        $this->theme(self::UNBUILT_THEME);
    }

    public function testRegistersOnTheSearchBlocksRender(): void
    {
        (new HeaderSearch())->register();

        self::assertNotFalse(
            has_filter('render_block_core/search', 'Pixonaut\Shop\Header\HeaderSearch->render()')
        );
    }

    public function testLeavesASearchThatShowsItsFieldAsCorePrintsIt(): void
    {
        $search = new HeaderSearch();

        self::assertSame(self::CORE_OUTPUT, $search->render(self::CORE_OUTPUT, self::block('button-outside')));
        self::assertSame(self::CORE_OUTPUT, $search->render(self::CORE_OUTPUT, ['attrs' => []]));
    }

    public function testLeavesAnIconOnlySearchOfOtherContentAsCorePrintsIt(): void
    {
        $posts = ['blockName' => 'core/search', 'attrs' => ['buttonPosition' => 'button-only']];

        self::assertSame(self::CORE_OUTPUT, (new HeaderSearch())->render(self::CORE_OUTPUT, $posts));
    }

    public function testPrintsTheIconAndThePanelItOpens(): void
    {
        $html = (new HeaderSearch())->render(self::CORE_OUTPUT, self::block('button-only'));

        $panel = 'pixonaut-shop-header-search-1-panel';
        self::assertStringContainsString(
            '<button type="button" class="pixonaut-shop-header-search__toggle" popovertarget="' . $panel . '" aria-controls="' . $panel . '" aria-label="Search products">',
            $html
        );
        self::assertStringContainsString('<div id="' . $panel . '" class="pixonaut-shop-header-search__panel" popover', $html);
        self::assertStringContainsString('popovertarget="' . $panel . '" popovertargetaction="hide" aria-label="Close search"', $html);
    }

    public function testThePanelsFormSearchesTheProductsWithoutScript(): void
    {
        $html = (new HeaderSearch())->render(self::CORE_OUTPUT, self::block('button-only'));

        self::assertStringContainsString('<form class="pixonaut-shop-header-search__form" role="search" method="get" action="' . self::SHOP_URL . '/">', $html);
        self::assertStringContainsString('name="s"', $html);
        self::assertStringContainsString('<input type="hidden" name="post_type" value="product">', $html);
        self::assertStringContainsString('<button type="submit"', $html);
    }

    public function testTheFieldIsALabelledComboboxOverItsSuggestions(): void
    {
        $html = (new HeaderSearch())->render(self::CORE_OUTPUT, self::block('button-only'));

        $field = 'pixonaut-shop-header-search-1-field';
        self::assertStringContainsString('<label class="screen-reader-text" for="' . $field . '">Search products</label>', $html);
        self::assertMatchesRegularExpression(
            '/<input id="' . $field . '"[^>]* placeholder="Search products…"[^>]* role="combobox" aria-expanded="false" aria-controls="pixonaut-shop-header-search-1-list" aria-autocomplete="list"/',
            $html
        );
        // Nothing to offer before typing: the list is empty until the shopper types.
        self::assertStringContainsString('<ul id="pixonaut-shop-header-search-1-list" class="pixonaut-shop-header-search__list" role="listbox" aria-label="Suggestions" hidden></ul>', $html);
        self::assertStringContainsString('role="status"', $html);
    }

    public function testBeforeTypingTheListOffersThePopularSearches(): void
    {
        $now = time();
        Functions\when('get_option')->justReturn([
            'lanterns' => ['words' => 'Lanterns', 'count' => 3, 'last' => $now],
            'mugs'     => ['words' => 'mugs', 'count' => 2, 'last' => $now],
            'once'     => ['words' => 'once', 'count' => 1, 'last' => $now],
        ]);

        $html = (new HeaderSearch())->render(self::CORE_OUTPUT, self::block('button-only'));

        self::assertMatchesRegularExpression(
            '/<li class="pixonaut-shop-header-search__group pixonaut-shop-header-search__group--searches" role="group" aria-labelledby="pixonaut-shop-header-search-1-list-searches">'
            . '<span id="pixonaut-shop-header-search-1-list-searches" class="pixonaut-shop-header-search__heading">Popular searches<\/span>/',
            $html
        );
        self::assertMatchesRegularExpression(
            '/<li id="pixonaut-shop-header-search-1-list-search-0" class="[^"]*__search" role="option" aria-selected="false" data-url="https:\/\/shop.test\/\?s=Lanterns&(amp;)?post_type=product">.*?<span>Lanterns<\/span><\/li>'
            . '<li id="pixonaut-shop-header-search-1-list-search-1"[^>]*>.*?<span>mugs<\/span>/',
            $html
        );
        self::assertStringNotContainsString('once', $html);
        // No products to show: no group for them.
        self::assertStringNotContainsString('Popular products', $html);
    }

    public function testTheSuggestionsComeFromTheResultsPagesOwnSearch(): void
    {
        $html = (new HeaderSearch())->render(self::CORE_OUTPUT, self::block('button-only'));

        self::assertStringContainsString('data-suggestions="pixonaut_shop_suggestions"', $html);
    }

    public function testTakesTheBlocksOwnPlaceholder(): void
    {
        $block = self::block('button-only');
        $block['attrs']['placeholder'] = 'Find a gift';

        self::assertStringContainsString('placeholder="Find a gift"', (new HeaderSearch())->render(self::CORE_OUTPUT, $block));
    }

    public function testEachSearchOnAPageHasItsOwnIds(): void
    {
        $search = new HeaderSearch();
        $search->render(self::CORE_OUTPUT, self::block('button-only'));

        self::assertStringContainsString(
            'id="pixonaut-shop-header-search-2-panel"',
            $search->render(self::CORE_OUTPUT, self::block('button-only'))
        );
    }

    public function testLoadsTheBuiltScriptWithItsTranslations(): void
    {
        $this->theme(self::BUILT_THEME);
        $enqueued = $this->record_enqueued_scripts();
        Functions\expect('wp_set_script_translations')
            ->once()
            ->with(HeaderSearch::SCRIPT_HANDLE, 'pixonaut-shop', self::BUILT_THEME . '/languages');

        (new HeaderSearch())->render(self::CORE_OUTPUT, self::block('button-only'));

        $expected = [
            HeaderSearch::SCRIPT_HANDLE,
            self::THEME_URI . '/build/header-search.js',
            [],
            'abc123',
            true,
        ];

        self::assertSame([$expected], $enqueued->getArrayCopy());
    }

    public function testLoadsNothingForASearchThatShowsItsField(): void
    {
        $this->theme(self::BUILT_THEME);
        $enqueued = $this->record_enqueued_scripts();

        (new HeaderSearch())->render(self::CORE_OUTPUT, self::block('button-outside'));

        self::assertCount(0, $enqueued);
    }

    public function testLoadsNothingInACheckoutWhoseScriptIsNotBuilt(): void
    {
        $enqueued = $this->record_enqueued_scripts();
        Functions\expect('wp_set_script_translations')->never();

        $html = (new HeaderSearch())->render(self::CORE_OUTPUT, self::block('button-only'));

        self::assertStringContainsString('pixonaut-shop-header-search__panel', $html);
        self::assertCount(0, $enqueued);
    }

    /**
     * @return array{blockName: string, attrs: array<string, mixed>}
     */
    private static function block(string $button_position): array
    {
        return ['blockName' => 'core/search', 'attrs' => ['buttonPosition' => $button_position, 'query' => ['post_type' => 'product']]];
    }

    /**
     * Every `wp_enqueue_script()` call's arguments, as the test goes on.
     *
     * @return ArrayObject<int, list<mixed>>
     */
    private function record_enqueued_scripts(): ArrayObject
    {
        /** @var ArrayObject<int, list<mixed>> $calls */
        $calls = new ArrayObject();
        Functions\when('wp_enqueue_script')->alias(static function (mixed ...$arguments) use ($calls): void {
            $calls->append(array_values($arguments));
        });

        return $calls;
    }

    private function theme(string $path): void
    {
        Functions\when('get_theme_file_path')->alias(static fn (string $file): string => $path . '/' . $file);
        Functions\when('get_theme_file_uri')->alias(static fn (string $file): string => self::THEME_URI . '/' . $file);
    }
}
