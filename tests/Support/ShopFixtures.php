<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Support;

use RuntimeException;
use WC_Customer;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_Block_Patterns_Registry;
use WP_Error;
use WP_Term;

/**
 * What the end-to-end suite needs on wp-env's tests site, called through
 * WP-CLI (`tests/e2e/support/wp-cli.ts`) by its global setup. Idempotent:
 * every run calls it, and it changes nothing a previous run already did.
 */
final class ShopFixtures
{
    /** The product tests/e2e/fake-pixonaut.php personalises. */
    public const PRODUCT_SLUG = 'fake-pixonaut-product';

    private const PRODUCT_NAME = 'Personalised tee';

    /** The description the product page's description panel shows. */
    public const PRODUCT_DESCRIPTION = 'A soft tee with your own print on the chest.';

    /**
     * A product the fake does not personalise, in the same category, so the
     * product page has a related product and a page without the plugin.
     */
    public const PLAIN_PRODUCT_SLUG = 'plain-product';

    private const PLAIN_PRODUCT_NAME = 'Plain tee';

    /** A variable product the fake personalises, sold in sizes. */
    public const VARIABLE_PRODUCT_SLUG = 'fake-pixonaut-variable-product';

    private const VARIABLE_PRODUCT_NAME = 'Personalised tee in sizes';

    /**
     * A variable product the fake does not personalise, with its own
     * template, whose add-to-cart form has the Use swatches style, as a
     * store gives it in the Site Editor.
     */
    public const SWATCHES_PRODUCT_SLUG = 'swatches-product';

    private const SWATCHES_PRODUCT_NAME = 'Tee in swatches';

    /** The variable products' category, so the other products' pages and lists keep theirs. */
    private const VARIABLE_CATEGORY_SLUG = 'garments-in-sizes';

    private const VARIABLE_CATEGORY_NAME = 'Garments in sizes';

    /** The variable products' attribute, a product's own rather than a global one, and its values. */
    private const SIZE_ATTRIBUTE = 'Size';

    private const SIZES = ['S', 'M', 'L'];

    /**
     * The swatches product's second attribute and its values, so that
     * choosing one attribute narrows the other's options.
     */
    private const COLOUR_ATTRIBUTE = 'Colour';

    private const COLOURS = ['Black', 'White'];

    /** The pair of the swatches product's values it is not sold in together. */
    private const UNSOLD_SWATCHES_PAIR = [self::SIZE_ATTRIBUTE => 'L', self::COLOUR_ATTRIBUTE => 'Black'];

    /** The theme template the swatches product's template starts from, as a store's copy in the Site Editor does. */
    private const PRODUCT_TEMPLATE = 'single-product';

    private const ADD_TO_CART_FORM_BLOCK = '<!-- wp:woocommerce/add-to-cart-form /-->';

    private const ADD_TO_CART_FORM_WITH_SWATCHES = '<!-- wp:woocommerce/add-to-cart-form {"className":"is-style-swatches"} /-->';

    /** The category the product is in, which the shop spec opens. */
    public const CATEGORY_SLUG = 'personalised-garments';

    private const CATEGORY_NAME = 'Personalised garments';

    /** A category with no product, for the shop's no-products state. */
    public const EMPTY_CATEGORY_SLUG = 'empty-category';

    private const EMPTY_CATEGORY_NAME = 'Empty category';

    private const CATEGORY_TAXONOMY = 'product_cat';

    private const PRODUCT_PRICE = '249';

    /** The customer My account's specs sign in as: wp-env's local tests site only. */
    public const CUSTOMER_LOGIN = 'account-customer';

    public const CUSTOMER_PASSWORD = 'account-customer-password';

    public const CUSTOMER_EMAIL = 'account-customer@example.com';

    /** The customer's first billing line, which the dashboard's addresses shortcut shows. */
    public const CUSTOMER_STREET = '1 Example Street';

    /** WooCommerce's option that offers registration beside the login on My account. */
    /** A personalised order's lines' states unless a caller names others; WP-CLI's eval takes no quoted arguments. */
    private const DEFAULT_LINE_STATES = ['ordered', 'approved'];

    private const REGISTRATION_OPTION = 'woocommerce_enable_myaccount_registration';

    /** WooCommerce's option for its "coming soon" mode, on for every new store. */
    private const COMING_SOON_OPTION = 'woocommerce_coming_soon';

    /**
     * The option that turns WooCommerce's block hooks on, which inserts its
     * blocks into a theme's templates. WooCommerce sets it when a new store
     * installs it, but not on wp-env's tests site.
     */
    private const BLOCK_HOOKS_OPTION = 'woocommerce_hooked_blocks_version';

    /** WooCommerce's settings for its cheque gateway, which takes an order without a payment service. */
    private const CHEQUE_SETTINGS_OPTION = 'woocommerce_cheque_settings';

    /**
     * WooCommerce's option for where it ships; `disabled` turns shipping off,
     * so the checkout asks for a billing address only and needs no shipping
     * zone.
     */
    private const SHIP_TO_COUNTRIES_OPTION = 'woocommerce_ship_to_countries';

    /** The page the store made from the front page pattern and set as its homepage. */
    public const FRONT_PAGE_SLUG = 'front-page';

    private const FRONT_PAGE_TITLE = 'Home';

    private const FRONT_PAGE_PATTERN = 'pixonaut-shop/front-page';

    /** The pattern the store applies to its Checkout page, with step numbers and a terms checkbox. */
    private const CHECKOUT_PATTERN = 'pixonaut-shop/checkout';

    /**
     * A page with the checkout pattern's content and step numbers off, as in
     * WooCommerce's default Checkout block, so the checkout's look is checked
     * without them too and the real Checkout page stays as the other specs
     * expect it.
     */
    public const CHECKOUT_WITHOUT_STEP_NUMBERS_SLUG = 'checkout-without-step-numbers';

    private const CHECKOUT_WITHOUT_STEP_NUMBERS_TITLE = 'Checkout without step numbers';

    /** The checkout pattern's Checkout block attributes that turn its step numbers on. */
    private const STEP_NUMBERS_ON = ' {"showFormStepNumbers":true}';

    /** The transient that sends the first admin page view to WooCommerce's setup wizard. */
    private const ACTIVATION_REDIRECT = '_wc_activation_redirect';

    public static function prepare(): void
    {
        // A logged-out visitor would see the coming-soon page instead of the shop.
        update_option(self::COMING_SOON_OPTION, 'no');
        // My account offers registration beside the login, as the stores do.
        self::offer_registration(true);
        // The Site Editor tests would land in the setup wizard instead.
        delete_transient(self::ACTIVATION_REDIRECT);

        self::enable_block_hooks();
        self::category(self::EMPTY_CATEGORY_NAME, self::EMPTY_CATEGORY_SLUG);
        $category = self::category(self::CATEGORY_NAME, self::CATEGORY_SLUG);
        self::product(self::PRODUCT_SLUG, self::PRODUCT_NAME, $category);
        self::product(self::PLAIN_PRODUCT_SLUG, self::PLAIN_PRODUCT_NAME, $category);
        $sized = self::category(self::VARIABLE_CATEGORY_NAME, self::VARIABLE_CATEGORY_SLUG);
        self::variable_product(
            self::VARIABLE_PRODUCT_SLUG,
            self::VARIABLE_PRODUCT_NAME,
            $sized,
            [self::SIZE_ATTRIBUTE => self::SIZES],
        );
        self::variable_product(
            self::SWATCHES_PRODUCT_SLUG,
            self::SWATCHES_PRODUCT_NAME,
            $sized,
            [self::SIZE_ATTRIBUTE => self::SIZES, self::COLOUR_ATTRIBUTE => self::COLOURS],
            [self::UNSOLD_SWATCHES_PAIR],
        );
        self::swatches_template();
        self::customer();
        self::checkout();
        self::front_page();
    }

    /**
     * The customer My account's specs sign in as, with an order, so the
     * dashboard has one to show. Returns the customer's id.
     */
    public static function customer(): int
    {
        $existing = get_user_by('login', self::CUSTOMER_LOGIN);

        if (false !== $existing) {
            return $existing->ID;
        }

        $customer = new WC_Customer();
        $customer->set_username(self::CUSTOMER_LOGIN);
        $customer->set_password(self::CUSTOMER_PASSWORD);
        $customer->set_email(self::CUSTOMER_EMAIL);
        $customer->set_billing_address_1(self::CUSTOMER_STREET);
        $customer_id = $customer->save();

        $order = wc_create_order(['customer_id' => $customer_id]);
        $order->add_product(
            self::product(self::PRODUCT_SLUG, self::PRODUCT_NAME, self::category(self::CATEGORY_NAME, self::CATEGORY_SLUG))
        );
        $order->calculate_totals();
        $order->set_status('processing');
        $order->save();

        return $customer_id;
    }

    /**
     * An order of the customer's, processing, with a personalised line in
     * each of the given states, made as the fake plugin
     * (`tests/e2e/fake-pixonaut.php`) makes one at checkout and keeps its
     * state. Returns its ID; `delete_order()` removes it again.
     *
     * @param list<string> $states The plugin's line states, such as `approved`;
     *                             by default one line in production and one
     *                             approved, which is the one furthest behind.
     */
    public static function personalised_order(array $states = self::DEFAULT_LINE_STATES): int
    {
        $product = self::product(self::PRODUCT_SLUG, self::PRODUCT_NAME, self::category(self::CATEGORY_NAME, self::CATEGORY_SLUG));
        $order = wc_create_order(['customer_id' => self::customer()]);

        if (! $order instanceof WC_Order) {
            throw new RuntimeException('WooCommerce did not create the order.');
        }

        foreach ($states as $state) {
            $cart_item = apply_filters('woocommerce_add_cart_item_data', [], $product->get_id(), 0, 1);
            $item = new WC_Order_Item_Product();
            $item->set_product($product);
            $item->set_quantity(1);
            $item->set_subtotal($product->get_price());
            $item->set_total($product->get_price());
            do_action('woocommerce_checkout_create_order_line_item', $item, 'cart-item', (array) $cart_item + ['data' => $product], $order);

            $line = json_decode((string) $item->get_meta(\PIXONAUT_SHOP_FAKE_LINE_META), true);
            $item->update_meta_data(\PIXONAUT_SHOP_FAKE_LINE_META, (string) wp_json_encode(['state' => $state] + (array) $line));
            $order->add_item($item);
        }

        $order->calculate_totals();
        $order->set_status('processing');

        return $order->save();
    }

    public static function delete_order(int $order_id): void
    {
        $order = wc_get_order($order_id);

        if ($order instanceof WC_Order) {
            $order->delete(true);
        }
    }

    /**
     * Offers registration beside the login on My account, or the login alone,
     * as WooCommerce's own default does.
     */
    public static function offer_registration(bool $offered): void
    {
        update_option(self::REGISTRATION_OPTION, $offered ? 'yes' : 'no');
    }

    /**
     * Makes the site insert WooCommerce's blocks as a new store does. Call it
     * before anything renders: WooCommerce reads the option once per request.
     */
    public static function enable_block_hooks(): void
    {
        if (\function_exists('WC')) {
            update_option(self::BLOCK_HOOKS_OPTION, WC()->version);
        }
    }

    /**
     * Lets a visitor place an order: cheque payments on, and shipping off.
     * The Checkout page holds the checkout pattern's content, as a store
     * that applied it does, again on every run so a changed pattern reaches
     * it.
     */
    private static function checkout(): void
    {
        $cheque = get_option(self::CHEQUE_SETTINGS_OPTION);
        update_option(self::CHEQUE_SETTINGS_OPTION, ['enabled' => 'yes'] + (\is_array($cheque) ? $cheque : []));
        update_option(self::SHIP_TO_COUNTRIES_OPTION, 'disabled');

        $page = wp_update_post([
            'ID'           => wc_get_page_id('checkout'),
            'post_content' => wp_slash(self::pattern_content(self::CHECKOUT_PATTERN)),
        ], true);

        if ($page instanceof WP_Error) {
            throw new RuntimeException($page->get_error_message());
        }

        $existing = get_page_by_path(self::CHECKOUT_WITHOUT_STEP_NUMBERS_SLUG);
        $page     = wp_insert_post([
            'ID'           => null === $existing ? 0 : $existing->ID,
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_name'    => self::CHECKOUT_WITHOUT_STEP_NUMBERS_SLUG,
            'post_title'   => self::CHECKOUT_WITHOUT_STEP_NUMBERS_TITLE,
            'post_content' => wp_slash(str_replace(
                '<!-- wp:woocommerce/checkout' . self::STEP_NUMBERS_ON . ' -->',
                '<!-- wp:woocommerce/checkout -->',
                self::pattern_content(self::CHECKOUT_PATTERN)
            )),
        ], true);

        if ($page instanceof WP_Error) {
            throw new RuntimeException($page->get_error_message());
        }
    }

    /** A registered pattern's content, as the editor inserts it. */
    private static function pattern_content(string $slug): string
    {
        $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered($slug);

        if (! \is_array($pattern)) {
            throw new RuntimeException($slug . ' is not registered.');
        }

        return $pattern['content'];
    }

    /** The category's term ID, made the first time. */
    private static function category(string $name, string $slug): int
    {
        $term = get_term_by('slug', $slug, self::CATEGORY_TAXONOMY);

        if ($term instanceof WP_Term) {
            return $term->term_id;
        }

        $term = wp_insert_term($name, self::CATEGORY_TAXONOMY, ['slug' => $slug]);

        if ($term instanceof WP_Error) {
            throw new RuntimeException($term->get_error_message());
        }

        return (int) $term['term_id'];
    }

    /**
     * A page with the front page pattern's content, as the editor inserts
     * it, set as the homepage in Settings > Reading. Its content is the
     * pattern's again on every run, so a changed pattern reaches it.
     */
    private static function front_page(): void
    {
        $existing = get_page_by_path(self::FRONT_PAGE_SLUG);
        $page     = wp_insert_post([
            'ID'           => null === $existing ? 0 : $existing->ID,
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_name'    => self::FRONT_PAGE_SLUG,
            'post_title'   => self::FRONT_PAGE_TITLE,
            'post_content' => wp_slash(self::pattern_content(self::FRONT_PAGE_PATTERN)),
        ], true);

        if ($page instanceof WP_Error) {
            throw new RuntimeException($page->get_error_message());
        }

        update_option('show_on_front', 'page');
        update_option('page_on_front', $page);
    }

    private static function product(string $slug, string $name, int $category): WC_Product
    {
        $existing = get_page_by_path($slug, OBJECT, 'product');
        $product  = null === $existing ? new WC_Product_Simple() : wc_get_product($existing->ID);

        if (! $product instanceof WC_Product) {
            throw new RuntimeException('The fixture product is not a WooCommerce product.');
        }

        $product->set_name($name);
        $product->set_slug($slug);
        $product->set_description(self::PRODUCT_DESCRIPTION);
        $product->set_regular_price(self::PRODUCT_PRICE);
        $product->set_category_ids([$category]);
        $product->set_status('publish');
        $product->save();

        return $product;
    }

    /**
     * A variable product sold in every combination of its attributes' values
     * but the unsold ones, at the product price. A variation of an earlier run
     * that is no longer one of them is deleted.
     *
     * @param array<string, list<string>> $attributes Each attribute's name and its values.
     * @param list<array<string, string>> $unsold     The combinations it is not sold in, each value by its attribute's name.
     */
    private static function variable_product(string $slug, string $name, int $category, array $attributes, array $unsold = []): void
    {
        $existing = get_page_by_path($slug, OBJECT, 'product');
        $product  = null === $existing ? new WC_Product_Variable() : wc_get_product($existing->ID);

        if (! $product instanceof WC_Product_Variable) {
            throw new RuntimeException('The variable fixture product is not a variable WooCommerce product.');
        }

        $product_attributes = [];

        foreach ($attributes as $attribute_name => $values) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_name($attribute_name);
            $attribute->set_options($values);
            $attribute->set_visible(true);
            $attribute->set_variation(true);
            $product_attributes[] = $attribute;
        }

        $product->set_name($name);
        $product->set_slug($slug);
        $product->set_description(self::PRODUCT_DESCRIPTION);
        $product->set_category_ids([$category]);
        $product->set_attributes($product_attributes);
        $product->set_status('publish');
        $product->save();

        $unsold_keys = array_map(self::variation_key(...), array_map(self::variation_attributes(...), $unsold));
        $wanted      = [];

        foreach (self::combinations($attributes) as $combination) {
            $variation_attributes = self::variation_attributes($combination);
            $key                  = self::variation_key($variation_attributes);

            if (! \in_array($key, $unsold_keys, true)) {
                $wanted[$key] = $variation_attributes;
            }
        }

        foreach ($product->get_children() as $child) {
            $variation = wc_get_product($child);

            if (! $variation instanceof WC_Product_Variation) {
                continue;
            }

            $key = self::variation_key($variation->get_attributes());

            if (\array_key_exists($key, $wanted)) {
                unset($wanted[$key]);
            } else {
                $variation->delete(true);
            }
        }

        foreach ($wanted as $variation_attributes) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product->get_id());
            $variation->set_attributes($variation_attributes);
            $variation->set_regular_price(self::PRODUCT_PRICE);
            $variation->set_status('publish');
            $variation->save();
        }

        WC_Product_Variable::sync($product->get_id());
    }

    /**
     * Every combination of the attributes' values, each value by its attribute's name.
     *
     * @param array<string, list<string>> $attributes
     *
     * @return list<array<string, string>>
     */
    private static function combinations(array $attributes): array
    {
        $combinations = [[]];

        foreach ($attributes as $attribute_name => $values) {
            $extended = [];

            foreach ($combinations as $combination) {
                foreach ($values as $value) {
                    $extended[] = $combination + [$attribute_name => $value];
                }
            }

            $combinations = $extended;
        }

        return $combinations;
    }

    /**
     * A combination as a variation holds it: each value by its attribute's slug.
     *
     * @param array<string, string> $combination
     *
     * @return array<string, string>
     */
    private static function variation_attributes(array $combination): array
    {
        $variation_attributes = [];

        foreach ($combination as $attribute_name => $value) {
            $variation_attributes[sanitize_title($attribute_name)] = $value;
        }

        return $variation_attributes;
    }

    /**
     * What tells one variation's combination from another's, whatever the order its attributes are in.
     *
     * @param array<array-key, mixed> $variation_attributes
     */
    private static function variation_key(array $variation_attributes): string
    {
        ksort($variation_attributes);

        return http_build_query($variation_attributes);
    }

    /**
     * The swatches product's own template (`single-product-<slug>`): the
     * theme's product template with the Use swatches style on its
     * add-to-cart form, saved as the Site Editor saves a store's template.
     */
    private static function swatches_template(): void
    {
        $slug     = self::PRODUCT_TEMPLATE . '-' . self::SWATCHES_PRODUCT_SLUG;
        $source   = file_get_contents(get_theme_file_path('templates/' . self::PRODUCT_TEMPLATE . '.html'));
        $existing = get_block_template(get_stylesheet() . '//' . $slug);

        if (false === $source || ! str_contains($source, self::ADD_TO_CART_FORM_BLOCK)) {
            throw new RuntimeException('The product template has no add-to-cart form to style.');
        }

        $template = wp_insert_post([
            'ID'           => null === $existing || null === $existing->wp_id ? 0 : $existing->wp_id,
            'post_type'    => 'wp_template',
            'post_status'  => 'publish',
            'post_name'    => $slug,
            'post_title'   => self::SWATCHES_PRODUCT_NAME,
            'post_content' => wp_slash(str_replace(self::ADD_TO_CART_FORM_BLOCK, self::ADD_TO_CART_FORM_WITH_SWATCHES, $source)),
        ], true);

        if ($template instanceof WP_Error) {
            throw new RuntimeException($template->get_error_message());
        }

        wp_set_object_terms($template, get_stylesheet(), 'wp_theme');
    }
}
