<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Support;

use Pixonaut\Shop\Product\ThemeProducts;
use RuntimeException;
use WC_Product;
use WC_Product_Simple;
use WP_Error;
use WP_Term;

/**
 * Products in a theme, for the e2e suite's "More in the theme" section
 * (`theme-products.spec.ts`): a tag three products share across two
 * categories, and a product in the first category without it, which the
 * related products show instead. Idempotent; `forget()` removes them.
 */
final class ThemeFixtures
{
    public const TAG_SLUG = 'fixture-theme';

    public const TAG_NAME = 'Fixture theme';

    /** The product whose page the spec opens. */
    public const PRODUCT_SLUG = 'theme-sister-tee';

    /** Its other products in the theme, in the other category. */
    public const OTHER_SLUGS = ['theme-mum-tee', 'theme-dad-tee'];

    /** A product in the first product's category without the tag. */
    public const UNTAGGED_SLUG = 'theme-untagged-brother-tee';

    private const PRODUCTS = [
        self::PRODUCT_SLUG  => ['Theme sister tee', self::SIBLINGS_SLUG, true],
        'theme-mum-tee'     => ['Theme mum tee', self::PARENTS_SLUG, true],
        'theme-dad-tee'     => ['Theme dad tee', self::PARENTS_SLUG, true],
        self::UNTAGGED_SLUG => ['Theme untagged brother tee', self::SIBLINGS_SLUG, false],
    ];

    private const SIBLINGS_SLUG = 'theme-siblings';

    private const PARENTS_SLUG = 'theme-parents';

    private const CATEGORIES = [
        self::SIBLINGS_SLUG => 'Theme siblings',
        self::PARENTS_SLUG  => 'Theme parents',
    ];

    private const CATEGORY_TAXONOMY = 'product_cat';

    private const PRICE = '249';

    public static function make(): void
    {
        $tag = self::term(self::TAG_NAME, self::TAG_SLUG, ThemeProducts::TAXONOMY);

        foreach (self::PRODUCTS as $slug => [$name, $category, $tagged]) {
            $existing = get_page_by_path($slug, OBJECT, 'product');
            $product = null === $existing ? new WC_Product_Simple() : wc_get_product($existing->ID);

            if (! $product instanceof WC_Product) {
                throw new RuntimeException('The fixture product is not a WooCommerce product.');
            }

            $product->set_name($name);
            $product->set_slug($slug);
            $product->set_regular_price(self::PRICE);
            $product->set_category_ids([self::term(self::CATEGORIES[$category], $category, self::CATEGORY_TAXONOMY)]);
            $product->set_tag_ids($tagged ? [$tag] : []);
            $product->set_status('publish');
            $product->save();
        }
    }

    /** The products and their terms gone again, so no other spec's lists show them. */
    public static function forget(): void
    {
        foreach (array_keys(self::PRODUCTS) as $slug) {
            $existing = get_page_by_path($slug, OBJECT, 'product');

            if (null !== $existing) {
                wp_delete_post($existing->ID, true);
            }
        }

        $terms = [self::TAG_SLUG => ThemeProducts::TAXONOMY] + array_fill_keys(array_keys(self::CATEGORIES), self::CATEGORY_TAXONOMY);

        foreach ($terms as $slug => $taxonomy) {
            $term = get_term_by('slug', $slug, $taxonomy);

            if ($term instanceof WP_Term) {
                wp_delete_term($term->term_id, $taxonomy);
            }
        }
    }

    private static function term(string $name, string $slug, string $taxonomy): int
    {
        $term = get_term_by('slug', $slug, $taxonomy);

        if ($term instanceof WP_Term) {
            return $term->term_id;
        }

        $term = wp_insert_term($name, $taxonomy, ['slug' => $slug]);

        if ($term instanceof WP_Error) {
            throw new RuntimeException($term->get_error_message());
        }

        return (int) $term['term_id'];
    }
}
