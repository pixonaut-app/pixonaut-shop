<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Product;

use ArrayObject;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Assets\BuiltScript;
use Pixonaut\Shop\Product\VariationSwatches;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(VariationSwatches::class)]
#[CoversClass(BuiltScript::class)]
final class VariationSwatchesTest extends TestCase
{
    private const FORM = '<div class="wp-block-add-to-cart-form"><form class="variations_form cart"></form></div>';

    /** A theme checkout whose script has been built, and one whose has not. */
    private const BUILT_THEME = __DIR__ . '/fixtures/built';

    private const UNBUILT_THEME = __DIR__ . '/fixtures/unbuilt';

    private const THEME_URI = 'https://shop.test/wp-content/themes/pixonaut-shop';

    public function testRegistersOnTheAddToCartFormBlocksRender(): void
    {
        (new VariationSwatches())->register();

        self::assertNotFalse(
            has_filter('render_block_woocommerce/add-to-cart-form', 'Pixonaut\Shop\Product\VariationSwatches->load_script()')
        );
    }

    public function testLoadsTheBuiltScriptForAFormWithTheSwatchesStyle(): void
    {
        $this->theme(self::BUILT_THEME);
        $enqueued = $this->record_enqueued_scripts();

        $output = (new VariationSwatches())->load_script(self::FORM, self::block('is-style-swatches'));

        self::assertSame(self::FORM, $output);
        $expected = [
            VariationSwatches::SCRIPT_HANDLE,
            self::THEME_URI . '/build/swatches.js',
            ['jquery'],
            'def456',
            true,
        ];

        self::assertSame([$expected], $enqueued->getArrayCopy());
    }

    public function testFindsTheStyleAmongTheBlocksOtherClasses(): void
    {
        $this->theme(self::BUILT_THEME);
        $enqueued = $this->record_enqueued_scripts();

        (new VariationSwatches())->load_script(self::FORM, self::block('has-small-font-size is-style-swatches'));

        self::assertCount(1, $enqueued);
    }

    public function testLoadsNothingForAFormWithoutTheStyle(): void
    {
        $this->theme(self::BUILT_THEME);
        $enqueued = $this->record_enqueued_scripts();

        $swatches = new VariationSwatches();
        $swatches->load_script(self::FORM, self::block('is-style-swatches-later'));
        $swatches->load_script(self::FORM, ['blockName' => 'woocommerce/add-to-cart-form', 'attrs' => []]);

        self::assertCount(0, $enqueued);
    }

    public function testLoadsNothingInACheckoutWhoseScriptIsNotBuilt(): void
    {
        $this->theme(self::UNBUILT_THEME);
        $enqueued = $this->record_enqueued_scripts();

        $output = (new VariationSwatches())->load_script(self::FORM, self::block('is-style-swatches'));

        self::assertSame(self::FORM, $output);
        self::assertCount(0, $enqueued);
    }

    /**
     * @return array{blockName: string, attrs: array<string, mixed>}
     */
    private static function block(string $class_name): array
    {
        return ['blockName' => 'woocommerce/add-to-cart-form', 'attrs' => ['className' => $class_name]];
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
