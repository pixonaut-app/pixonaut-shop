<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Product\VariablePrice;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_Block_Patterns_Registry;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

/**
 * A variable product's price before a variation is chosen, as WooCommerce
 * renders it with the theme's templates: "from" its lowest price by
 * default, on its page and in the product grid, or the range or the short
 * form when the store picks them, in WooCommerce's product settings, through
 * their REST API, or in the Customizer, before the choice was an option too.
 */
final class VariablePriceTest extends TestCase
{
    /** The prices of the variable product's two sizes. */
    private const SIZE_PRICES = ['Small' => '299', 'Large' => '749'];

    private const GRID_PATTERN = 'pixonaut-shop/product-grid';

    /** The choice in WooCommerce's settings REST API, in the group of its product settings. */
    private const SETTINGS_ROUTE = '/wc/v3/settings/products/' . VariablePrice::SETTING;

    private const BAD_REQUEST = 400;

    private const OK = 200;

    /** A shop manager, who may change WooCommerce's settings; made once and kept, as the suite's other fixtures. */
    private const SHOP_MANAGER = 'variable-price-manager';

    /** @var list<int> */
    private array $product_ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        delete_option(VariablePrice::SETTING);
        remove_theme_mod(VariablePrice::SETTING);
    }

    protected function tearDown(): void
    {
        foreach ($this->product_ids as $id) {
            wp_delete_post($id, true);
        }

        delete_option(VariablePrice::SETTING);
        remove_theme_mod(VariablePrice::SETTING);
        wp_set_current_user(0);
        $GLOBALS['wp_query']->init();
        wp_reset_postdata();
        unset($GLOBALS['product']);
        parent::tearDown();
    }

    public function testShowsFromTheLowestPriceByDefault(): void
    {
        $product = $this->variable_product();

        self::assertMatchesRegularExpression('/^<span class="from">From <\/span><span class="woocommerce-Price-amount amount"><bdi>(?:(?!<\/bdi>).)*299/s', $product->get_price_html());
        self::assertStringNotContainsString(max(self::SIZE_PRICES), $product->get_price_html());
        self::assertMatchesRegularExpression('/data-pixonaut-price><span class="from">From <\/span>.*299/s', $this->product_page($product->get_id()));
        self::assertMatchesRegularExpression('/Sized tee.*wc-block-components-product-price.*From .*299/s', $this->grid());
    }

    public function testShowsTheShortFormWhenTheStorePicksIt(): void
    {
        update_option(VariablePrice::SETTING, VariablePrice::FROM_SHORT);

        self::assertMatchesRegularExpression('/^<span class="from">Fr\. <\/span>.*299/s', $this->variable_product()->get_price_html());
    }

    public function testShowsTheRangeWhenTheStorePicksIt(): void
    {
        update_option(VariablePrice::SETTING, VariablePrice::RANGE);
        $product = $this->variable_product();

        self::assertStringNotContainsString('From', $product->get_price_html());
        self::assertMatchesRegularExpression('/299.*749/s', $product->get_price_html());
    }

    public function testKeepsTheChoiceTheStoreMadeInTheCustomizerBefore(): void
    {
        set_theme_mod(VariablePrice::SETTING, VariablePrice::RANGE);

        (new VariablePrice())->move_customizer_choice();

        self::assertSame(VariablePrice::RANGE, VariablePrice::current());
        self::assertFalse(get_theme_mod(VariablePrice::SETTING));

        // Saving the same choice again keeps the option, as any other save.
        update_option(VariablePrice::SETTING, VariablePrice::RANGE);
        self::assertSame(VariablePrice::RANGE, get_option(VariablePrice::SETTING));
    }

    public function testSavesOnlyAChoiceItKnows(): void
    {
        update_option(VariablePrice::SETTING, 'cheapest');

        self::assertSame(VariablePrice::DEFAULT, get_option(VariablePrice::SETTING));
    }

    public function testWooCommercesSettingsApiReadsAndChangesTheChoice(): void
    {
        wp_set_current_user($this->shop_manager());

        $read = $this->settings_api('GET');
        self::assertSame(self::OK, $read->get_status());
        $setting = $read->get_data();
        self::assertIsArray($setting);
        self::assertSame(VariablePrice::DEFAULT, $setting['value']);
        self::assertSame([VariablePrice::FROM, VariablePrice::FROM_SHORT, VariablePrice::RANGE], array_keys($setting['options']));

        self::assertSame(self::OK, $this->settings_api('PUT', VariablePrice::RANGE)->get_status());
        self::assertSame(VariablePrice::RANGE, VariablePrice::current());
        self::assertMatchesRegularExpression('/299.*749/s', $this->variable_product()->get_price_html());

        self::assertSame(self::BAD_REQUEST, $this->settings_api('PUT', 'cheapest')->get_status());
        self::assertSame(VariablePrice::RANGE, VariablePrice::current());
    }

    public function testAChosenVariationShowsItsOwnPrice(): void
    {
        $product = $this->variable_product();
        $large = null;

        foreach ($product->get_children() as $id) {
            $variation = wc_get_product($id);

            if ($variation instanceof WC_Product && self::SIZE_PRICES['Large'] === $variation->get_price()) {
                $large = $variation;
            }
        }

        self::assertInstanceOf(WC_Product::class, $large);
        self::assertStringNotContainsString('From', $large->get_price_html());
        self::assertStringContainsString(self::SIZE_PRICES['Large'], $large->get_price_html());
    }

    private function settings_api(string $method, ?string $value = null): WP_REST_Response
    {
        $request = new WP_REST_Request($method, self::SETTINGS_ROUTE);

        if (null !== $value) {
            $request->set_body_params(['value' => $value]);
        }

        return rest_ensure_response(rest_do_request($request));
    }

    private function shop_manager(): int
    {
        $existing = get_user_by('login', self::SHOP_MANAGER);

        if (false !== $existing) {
            return $existing->ID;
        }

        $id = wp_insert_user(['user_login' => self::SHOP_MANAGER, 'user_pass' => wp_generate_password(), 'role' => 'shop_manager']);
        self::assertIsInt($id);

        return $id;
    }

    private function variable_product(): WC_Product
    {
        $size = new WC_Product_Attribute();
        $size->set_name('Size');
        $size->set_options(array_keys(self::SIZE_PRICES));
        $size->set_visible(true);
        $size->set_variation(true);

        $product = new WC_Product_Variable();
        $product->set_name('Sized tee');
        $product->set_attributes([$size]);
        $product->set_status('publish');
        $id = $product->save();
        $this->product_ids[] = $id;

        foreach (self::SIZE_PRICES as $option => $price) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($id);
            $variation->set_attributes(['size' => $option]);
            $variation->set_regular_price($price);
            $variation->set_status('publish');
            $variation->save();
        }

        WC_Product_Variable::sync($id);
        $variable = wc_get_product($id);
        self::assertInstanceOf(WC_Product::class, $variable);

        return $variable;
    }

    private function product_page(int $product_id): string
    {
        $query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $query);
        $query->query(['post_type' => 'product', 'p' => $product_id]);
        $query->the_post();

        $template = get_block_template(get_stylesheet() . '//single-product');
        self::assertNotNull($template);

        return do_blocks($template->content);
    }

    private function grid(): string
    {
        $query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $query);
        $query->query(['post_type' => 'product', 'post__in' => $this->product_ids]);

        $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered(self::GRID_PATTERN);
        self::assertIsArray($pattern);

        return do_blocks($pattern['content']);
    }
}
