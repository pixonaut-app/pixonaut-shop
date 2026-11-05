<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Blocks;

use ArrayObject;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Assets\BuiltScript;
use Pixonaut\Shop\Blocks\MenuHeading;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(MenuHeading::class)]
#[CoversClass(BuiltScript::class)]
final class MenuHeadingTest extends TestCase
{
    /** A wp_navigation post's ID, as a navigation block's `ref` holds it. */
    private const MENU_ID = 42;

    private const MENU = '<nav class="wp-block-navigation pixonaut-shop-headed-menu" aria-label="Help">'
        . '<ul class="wp-block-navigation__container"><li>Delivery</li></ul></nav>';

    /** A theme checkout whose script has been built, and one whose has not. */
    private const BUILT_THEME = __DIR__ . '/fixtures/built';

    private const UNBUILT_THEME = __DIR__ . '/fixtures/unbuilt';

    private const THEME_URI = 'https://shop.test/wp-content/themes/pixonaut-shop';

    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubEscapeFunctions();
    }

    public function testRegistersOnTheNavigationBlocksRender(): void
    {
        (new MenuHeading())->register();

        self::assertNotFalse(
            has_filter('render_block_core/navigation', 'Pixonaut\Shop\Blocks\MenuHeading->add_heading()')
        );
    }

    public function testRegistersTheEditorScriptForTheBlockEditor(): void
    {
        (new MenuHeading())->register();

        self::assertNotFalse(
            has_action('enqueue_block_editor_assets', 'Pixonaut\Shop\Blocks\MenuHeading->enqueue_editor_script()')
        );
    }

    public function testEnqueuesTheBuiltEditorScriptWithItsDependenciesAndVersion(): void
    {
        $this->theme(self::BUILT_THEME);
        Functions\when('wp_add_inline_script')->justReturn(true);
        $enqueued = $this->record_enqueued_scripts();

        (new MenuHeading())->enqueue_editor_script();

        $expected = [
            MenuHeading::EDITOR_SCRIPT_HANDLE,
            self::THEME_URI . '/build/menu-heading.js',
            ['wp-compose', 'wp-core-data', 'wp-hooks'],
            'abc123',
            true,
        ];

        self::assertSame([$expected], $enqueued->getArrayCopy());
    }

    public function testHandsTheEditorScriptTheClassNamesBeforeItRuns(): void
    {
        $this->theme(self::BUILT_THEME);
        Functions\when('wp_enqueue_script')->justReturn();
        Functions\when('wp_json_encode')->alias('json_encode');
        $inline = null;
        Functions\expect('wp_add_inline_script')->once()->andReturnUsing(
            static function (string $handle, string $data, string $position) use (&$inline): bool {
                self::assertSame(MenuHeading::EDITOR_SCRIPT_HANDLE, $handle);
                self::assertSame('before', $position);
                $inline = $data;

                return true;
            }
        );

        (new MenuHeading())->enqueue_editor_script();

        self::assertIsString($inline);
        self::assertMatchesRegularExpression('/^window\.pixonautShopMenuHeading = (\{.*\});$/', $inline);
        $config = json_decode((string) preg_replace('/^[^=]+= |;$/', '', $inline), true);
        self::assertSame([
            'className' => MenuHeading::CLASS_NAME,
            'headingClassName' => MenuHeading::HEADING_CLASS,
            'previewClassName' => MenuHeading::PREVIEW_CLASS,
        ], $config);
    }

    public function testEnqueuesNothingInACheckoutWhoseScriptIsNotBuilt(): void
    {
        $this->theme(self::UNBUILT_THEME);
        Functions\expect('wp_add_inline_script')->never();
        $enqueued = $this->record_enqueued_scripts();

        (new MenuHeading())->enqueue_editor_script();

        self::assertCount(0, $enqueued);
    }

    public function testHeadsAMarkedMenuWithItsNameInsideTheNavBeforeTheList(): void
    {
        $this->menu('Help');

        $rendered = (new MenuHeading())->add_heading(self::MENU, self::block(MenuHeading::CLASS_NAME . ' is-footer'));

        self::assertStringStartsWith(
            '<nav class="wp-block-navigation pixonaut-shop-headed-menu" aria-label="Help">'
            . '<h2 class="' . MenuHeading::HEADING_CLASS . '">Help</h2><ul ',
            $rendered
        );
    }

    public function testEscapesTheMenusName(): void
    {
        $this->menu('Shop & <b>$1</b>');

        $rendered = (new MenuHeading())->add_heading(self::MENU, self::block(MenuHeading::CLASS_NAME));

        self::assertStringContainsString('>Shop &amp; &lt;b&gt;$1&lt;/b&gt;</h2>', $rendered);
    }

    public function testLeavesAMenuWithoutTheClassAsItIs(): void
    {
        $this->menu('Help');

        self::assertSame(self::MENU, (new MenuHeading())->add_heading(self::MENU, self::block('is-footer')));
    }

    public function testLeavesAMenuThatNamesNoSavedMenuAsItIs(): void
    {
        $block = self::block(MenuHeading::CLASS_NAME);
        unset($block['attrs']['ref']);

        self::assertSame(self::MENU, (new MenuHeading())->add_heading(self::MENU, $block));
    }

    public function testLeavesAMenuWhoseSavedMenuIsNotPublishedAsItIs(): void
    {
        $this->menu('Help', 'draft');

        self::assertSame(self::MENU, (new MenuHeading())->add_heading(self::MENU, self::block(MenuHeading::CLASS_NAME)));
    }

    public function testLeavesAMenuWithAnEmptyNameAsItIs(): void
    {
        $this->menu('');

        self::assertSame(self::MENU, (new MenuHeading())->add_heading(self::MENU, self::block(MenuHeading::CLASS_NAME)));
    }

    /**
     * @return array{blockName: string, attrs: array<string, mixed>}
     */
    private static function block(string $class_name): array
    {
        return ['blockName' => 'core/navigation', 'attrs' => ['ref' => self::MENU_ID, 'className' => $class_name]];
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

    private function menu(string $name, string $status = 'publish'): void
    {
        Functions\when('get_post_status')->alias(
            static fn (mixed $id): string|false => self::MENU_ID === $id ? $status : false
        );
        Functions\when('get_post_field')->alias(
            static fn (string $field, mixed $id): string => 'post_title' === $field && self::MENU_ID === $id ? $name : ''
        );
    }
}
