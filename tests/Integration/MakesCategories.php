<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use WC_Product_Simple;

/**
 * Product categories, their products and pictures made for one test and
 * deleted after it, with the categories that were there before left out of
 * every category query, such as the shop fixtures other suites add, so each
 * test sees only the categories it makes.
 *
 * A test case calls `start_making_categories()` in `setUp()` and
 * `stop_making_categories()` in `tearDown()`.
 */
trait MakesCategories
{
    private const CATEGORY_TAXONOMY = 'product_cat';

    /** @var list<int> */
    private array $term_ids = [];

    /** @var list<int> */
    private array $post_ids = [];

    /** @var callable(array<string, mixed>, list<string>): array<string, mixed> */
    private $only_new_categories;

    private function start_making_categories(): void
    {
        $existing = get_terms(['taxonomy' => self::CATEGORY_TAXONOMY, 'hide_empty' => false, 'fields' => 'ids']);
        self::assertIsArray($existing);

        $this->only_new_categories = static function (array $args, array $taxonomies) use ($existing): array {
            if ([self::CATEGORY_TAXONOMY] === $taxonomies) {
                $args['exclude'] = array_merge((array) ($args['exclude'] ?? []), $existing);
            }

            return $args;
        };
        add_filter('get_terms_args', $this->only_new_categories, 10, 2);
    }

    private function stop_making_categories(): void
    {
        remove_filter('get_terms_args', $this->only_new_categories);

        foreach ($this->post_ids as $id) {
            wp_delete_post($id, true);
        }

        foreach (array_reverse($this->term_ids) as $id) {
            wp_delete_term($id, self::CATEGORY_TAXONOMY);
        }
    }

    /**
     * A category with this many published products of its own, shown in the
     * catalogue as `$visibility` says.
     */
    private function category(string $name, int $products, int $parent_id = 0, string $visibility = 'visible'): int
    {
        $term = wp_insert_term($name, self::CATEGORY_TAXONOMY, ['parent' => $parent_id]);
        self::assertIsArray($term);
        $this->term_ids[] = $term['term_id'];

        for ($i = 0; $i < $products; ++$i) {
            $product = new WC_Product_Simple();
            $product->set_name("{$name} product {$i}");
            $product->set_status('publish');
            $product->set_category_ids([$term['term_id']]);
            $product->set_catalog_visibility($visibility);
            $this->post_ids[] = $product->save();
        }

        return $term['term_id'];
    }

    /** Gives the category a picture from the media library; its file is never read for the markup. */
    private function picture(int $term_id): int
    {
        $id = wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => "Picture {$term_id}"], "picture-{$term_id}.png");
        self::assertIsInt($id);
        $this->post_ids[] = $id;
        update_term_meta($term_id, 'thumbnail_id', $id);

        return $id;
    }

    /**
     * The block's markup as a page renders it.
     *
     * @param array<string, mixed> $attributes
     */
    private function render_block(string $name, array $attributes = []): string
    {
        $comment_attributes = [] === $attributes ? '' : wp_json_encode($attributes) . ' ';

        return do_blocks('<!-- wp:' . $name . ' ' . $comment_attributes . '/-->');
    }
}
