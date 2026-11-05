<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Header;

use ArrayObject;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Assets\BuiltScript;
use Pixonaut\Shop\Header\HeaderSearch;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(HeaderSearch::class)]
#[CoversClass(BuiltScript::class)]
final class HeaderSearchTest extends TestCase
{
    private const FORM = '<form role="search" class="wp-block-search__button-only wp-block-search"></form>';

    /** A theme checkout whose script has been built, and one whose has not. */
    private const BUILT_THEME = __DIR__ . '/fixtures/built';

    private const UNBUILT_THEME = __DIR__ . '/fixtures/unbuilt';

    private const THEME_URI = 'https://shop.test/wp-content/themes/pixonaut-shop';

    public function testRegistersOnTheSearchBlocksRender(): void
    {
        (new HeaderSearch())->register();

        self::assertNotFalse(
            has_filter('render_block_core/search', 'Pixonaut\Shop\Header\HeaderSearch->load_script()')
        );
    }

    public function testLoadsTheBuiltScriptForASearchThatIsOnlyItsButton(): void
    {
        $this->theme(self::BUILT_THEME);
        $enqueued = $this->record_enqueued_scripts();

        $output = (new HeaderSearch())->load_script(self::FORM, self::block('button-only'));

        self::assertSame(self::FORM, $output);
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

        $search = new HeaderSearch();
        $search->load_script(self::FORM, self::block('button-outside'));
        $search->load_script(self::FORM, ['blockName' => 'core/search', 'attrs' => []]);

        self::assertCount(0, $enqueued);
    }

    public function testLoadsNothingInACheckoutWhoseScriptIsNotBuilt(): void
    {
        $this->theme(self::UNBUILT_THEME);
        $enqueued = $this->record_enqueued_scripts();

        $output = (new HeaderSearch())->load_script(self::FORM, self::block('button-only'));

        self::assertSame(self::FORM, $output);
        self::assertCount(0, $enqueued);
    }

    /**
     * @return array{blockName: string, attrs: array<string, mixed>}
     */
    private static function block(string $button_position): array
    {
        return ['blockName' => 'core/search', 'attrs' => ['buttonPosition' => $button_position]];
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
