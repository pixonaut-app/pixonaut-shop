<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Archive;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Archive\ProductGridDefaults;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(ProductGridDefaults::class)]
final class ProductGridDefaultsTest extends TestCase
{
    public function testRegistersWhenTheThemeIsSetUp(): void
    {
        (new ProductGridDefaults())->register();

        self::assertNotFalse(has_action('after_setup_theme', 'Pixonaut\Shop\Archive\ProductGridDefaults->add_support()'));
    }

    public function testLeavesProductsPerPageToWooCommercesSetting(): void
    {
        (new ProductGridDefaults())->register();

        self::assertFalse(has_filter('loop_shop_per_page'));
        self::assertFalse(has_filter('loop_shop_columns'));
    }

    public function testStartsWooCommercesSettingAtThreeRowsOfTheGridsFourColumns(): void
    {
        Functions\expect('add_theme_support')
            ->once()
            ->with('woocommerce', ['product_grid' => ['default_rows' => 3, 'default_columns' => 4]]);

        // Monkey\tearDown() verifies the expectation above.
        $this->expectNotToPerformAssertions();

        (new ProductGridDefaults())->add_support();
    }

    public function testTheGridShowsAsManyColumnsAsWooCommerceStartsAt(): void
    {
        $pattern = (string) file_get_contents(\dirname(__DIR__, 3) . '/patterns/product-grid.php');

        self::assertStringContainsString('"columns":' . ProductGridDefaults::COLUMNS . ',', $pattern);
        self::assertStringContainsString(
            '"perPage":' . ProductGridDefaults::COLUMNS * ProductGridDefaults::ROWS . ',',
            $pattern
        );
    }
}
