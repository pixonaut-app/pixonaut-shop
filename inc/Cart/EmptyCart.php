<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Cart;

use Pixonaut\Shop\Archive\ProductGridDefaults;
use stdClass;
use WP_Block_Patterns_Registry;

/**
 * WooCommerce's own empty cart, as a store's Cart page holds it, drawn as
 * the theme's: its message in a panel, and its newest products as the
 * product grid's cards.
 *
 * WooCommerce saves the empty cart's blocks into the Cart page: its heading
 * and the way back to the shop (in place, or behind a pattern reference),
 * a separator or spacer, a "New in store" heading and the legacy Newest
 * Products block, whose cards are not the theme's. As the empty cart
 * renders, the heading and the paragraph or buttons after it go into a
 * group with the Panel style, as in `patterns/cart.php`; a separator or
 * spacer after them is left out, since the panel's edge divides; and each
 * Newest Products block becomes a product collection of the newest
 * products holding the product template of `patterns/product-grid.php`.
 * An empty cart that does not start with WooCommerce's heading, such as
 * the cart pattern's, is left as it is.
 *
 * @phpstan-type ParsedBlock array{blockName: string|null, attrs: array<array-key, mixed>, innerBlocks: list<array<array-key, mixed>>, innerHTML: string, innerContent: list<string|null>}
 */
final class EmptyCart
{
    public const BLOCK = 'woocommerce/empty-cart-block';

    /** The class of WooCommerce's empty-cart heading. */
    public const TITLE_CLASS = 'wc-block-cart__empty-cart__title';

    /** The classes of the panel around the message, as `patterns/cart.php` sets them. */
    public const PANEL_CLASSES = 'is-style-panel pixonaut-shop-empty-cart';

    /** WooCommerce's legacy Newest Products block. */
    public const NEWEST_PRODUCTS = 'woocommerce/product-new';

    /** The pattern whose product template draws the archive's cards. */
    public const CARD_PATTERN = 'pixonaut-shop/product-grid';

    /** Every stock status, as the Newest Products block lists them. */
    private const STOCK_STATUSES = ['instock', 'outofstock', 'onbackorder'];

    /** WooCommerce's collection of the newest products, which the editor names it by. */
    private const NEW_ARRIVALS = 'woocommerce/product-collection/new-arrivals';

    /** The Newest Products block's rows when it sets none, as WooCommerce defaults it. */
    private const DEFAULT_ROWS = 1;

    /**
     * The collection's query id, apart from the archive grid's (0) and the
     * popular products' (1), so a page holding them pages each on its own.
     */
    private const QUERY_ID = 2;

    /** Blocks that belong to the message when they follow its heading. */
    private const MESSAGE_BLOCKS = ['core/paragraph', 'core/buttons'];

    /** Blocks between the message and what follows it, which the panel's edge replaces. */
    private const DIVIDERS = ['core/separator', 'core/spacer'];

    private const PATTERN = 'core/pattern';

    private const HEADING = 'core/heading';

    private const COLLECTION = 'woocommerce/product-collection';

    private const PRODUCT_TEMPLATE = 'woocommerce/product-template';

    /** The empty cart's own wrapper, for a parsed block that lost its markup. */
    private const WRAPPER_OPEN = '<div class="wp-block-woocommerce-empty-cart-block">';

    private const WRAPPER_CLOSE = '</div>';

    public function register(): void
    {
        add_filter('render_block_data', [$this, 'render_data']);
    }

    /**
     * The empty cart's parsed block with the theme's blocks in place of
     * WooCommerce's; any other block as it is.
     *
     * @param array<array-key, mixed> $parsed_block
     * @return array<array-key, mixed>
     */
    public function render_data(array $parsed_block): array
    {
        if (self::BLOCK !== ($parsed_block['blockName'] ?? null)) {
            return $parsed_block;
        }

        $block = self::block($parsed_block);

        return (null !== $block ? self::rewrite($block) : null) ?? $parsed_block;
    }

    /**
     * The empty cart with the theme's blocks in place of WooCommerce's, or
     * null when it does not start with WooCommerce's heading.
     *
     * @param ParsedBlock $empty_cart
     * @return ParsedBlock|null
     */
    public static function rewrite(array $empty_cart): ?array
    {
        $inner = self::expand_patterns(self::blocks($empty_cart['innerBlocks']));

        if ([] === $inner || ! self::is_title($inner[0])) {
            return null;
        }

        $message = [array_shift($inner)];

        while ([] !== $inner && \in_array($inner[0]['blockName'], self::MESSAGE_BLOCKS, true)) {
            $message[] = array_shift($inner);
        }

        while ([] !== $inner && \in_array($inner[0]['blockName'], self::DIVIDERS, true)) {
            array_shift($inner);
        }

        $blocks = [self::panel($message)];

        foreach ($inner as $block) {
            $blocks[] = self::NEWEST_PRODUCTS === $block['blockName'] ? self::newest_products($block) ?? $block : $block;
        }

        $empty_cart['innerBlocks']  = $blocks;
        $empty_cart['innerContent'] = self::inner_content($empty_cart['innerContent'], \count($blocks));

        return $empty_cart;
    }

    /**
     * The blocks with each pattern reference in place of the blocks it
     * holds, as WooCommerce's Cart page refers to its empty-cart message by
     * pattern. A pattern that is not registered stays a reference.
     *
     * @param list<ParsedBlock> $blocks
     * @return list<ParsedBlock>
     */
    private static function expand_patterns(array $blocks): array
    {
        $expanded = [];

        foreach ($blocks as $block) {
            $slug    = self::PATTERN === $block['blockName'] ? ($block['attrs']['slug'] ?? null) : null;
            $content = \is_string($slug) ? self::pattern_content($slug) : null;

            if (null === $content) {
                $expanded[] = $block;
                continue;
            }

            array_push($expanded, ...self::parse($content));
        }

        return $expanded;
    }

    /**
     * @param ParsedBlock $block
     */
    private static function is_title(array $block): bool
    {
        $class_name = $block['attrs']['className'] ?? '';

        return self::HEADING === $block['blockName']
            && \is_string($class_name)
            && \in_array(self::TITLE_CLASS, explode(' ', $class_name), true);
    }

    /**
     * The message's blocks in a group with the Panel style.
     *
     * @param non-empty-list<ParsedBlock> $message
     * @return ParsedBlock
     */
    private static function panel(array $message): array
    {
        $attributes = ['className' => self::PANEL_CLASSES, 'layout' => ['type' => 'constrained']];

        return self::parse_one(
            '<!-- wp:group ' . wp_json_encode($attributes) . ' -->'
            . '<div class="wp-block-group ' . esc_attr(self::PANEL_CLASSES) . '">'
            . serialize_blocks($message)
            . '</div><!-- /wp:group -->'
        ) ?? $message[0];
    }

    /**
     * A product collection of the newest products, as many and in as many
     * columns as the Newest Products block showed, with the product grid's
     * cards; null when the theme's product grid has no product template.
     *
     * @param ParsedBlock $newest
     * @return ParsedBlock|null
     */
    private static function newest_products(array $newest): ?array
    {
        $card = self::card();

        if (null === $card) {
            return null;
        }

        $attributes = $newest['attrs'];
        $columns    = self::positive_int($attributes['columns'] ?? null, ProductGridDefaults::COLUMNS);
        $rows       = self::positive_int($attributes['rows'] ?? null, self::DEFAULT_ROWS);
        $categories = \is_array($attributes['categories'] ?? null)
            ? array_values(array_filter(array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $attributes['categories'])))
            : [];

        $collection = [
            'queryId'              => self::QUERY_ID,
            'query'                => [
                'perPage'                       => $columns * $rows,
                'pages'                         => 1,
                'offset'                        => 0,
                'postType'                      => 'product',
                'order'                         => 'desc',
                'orderBy'                       => 'date',
                'search'                        => '',
                'exclude'                       => [],
                'inherit'                       => false,
                'taxQuery'                      => [] === $categories ? new stdClass() : ['product_cat' => $categories],
                'isProductCollectionBlock'      => true,
                'woocommerceStockStatus'        => self::STOCK_STATUSES,
                'woocommerceAttributes'         => [],
                'woocommerceHandPickedProducts' => [],
            ],
            'tagName'              => 'div',
            'displayLayout'        => ['type' => 'flex', 'columns' => $columns, 'shrinkColumns' => false],
            'dimensions'           => ['widthType' => 'fill'],
            'collection'           => self::NEW_ARRIVALS,
            'hideControls'         => ['inherit'],
            'queryContextIncludes' => ['collection'],
            'align'                => 'wide',
        ];

        return self::parse_one(
            '<!-- wp:' . self::COLLECTION . ' ' . wp_json_encode($collection) . ' -->'
            . '<div class="wp-block-woocommerce-product-collection alignwide">'
            . serialize_block($card)
            . '</div><!-- /wp:' . self::COLLECTION . ' -->'
        );
    }

    /**
     * The product template of the theme's product grid: the archive's card.
     *
     * @return ParsedBlock|null
     */
    private static function card(): ?array
    {
        foreach (self::parse(self::pattern_content(self::CARD_PATTERN) ?? '') as $block) {
            if (self::COLLECTION !== $block['blockName']) {
                continue;
            }

            foreach (self::blocks($block['innerBlocks']) as $inner) {
                if (self::PRODUCT_TEMPLATE === $inner['blockName']) {
                    return $inner;
                }
            }
        }

        return null;
    }

    private static function pattern_content(string $slug): ?string
    {
        $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered($slug);
        $content = \is_array($pattern) ? ($pattern['content'] ?? null) : null;

        return \is_string($content) ? $content : null;
    }

    private static function positive_int(mixed $value, int $fallback): int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : $fallback;
    }

    /**
     * The blocks a piece of markup holds, without the whitespace between them.
     *
     * @return list<ParsedBlock>
     */
    private static function parse(string $markup): array
    {
        return self::blocks(parse_blocks($markup));
    }

    /**
     * The one block a piece of markup holds.
     *
     * @return ParsedBlock|null
     */
    private static function parse_one(string $markup): ?array
    {
        return self::parse($markup)[0] ?? null;
    }

    /**
     * The named blocks of a list, each with every key a parsed block has.
     *
     * @param array<array-key, mixed> $blocks
     * @return list<ParsedBlock>
     */
    private static function blocks(array $blocks): array
    {
        $named = [];

        foreach ($blocks as $block) {
            $parsed = self::block($block);

            if (null !== $parsed && null !== $parsed['blockName']) {
                $named[] = $parsed;
            }
        }

        return $named;
    }

    /**
     * A parsed block with every key it has, or null when it is not one.
     *
     * @return ParsedBlock|null
     */
    private static function block(mixed $block): ?array
    {
        if (! \is_array($block)) {
            return null;
        }

        $name          = $block['blockName'] ?? null;
        $attributes    = $block['attrs'] ?? [];
        $inner_blocks  = $block['innerBlocks'] ?? [];
        $inner_html    = $block['innerHTML'] ?? '';
        $inner_content = $block['innerContent'] ?? [];

        return [
            'blockName'    => \is_string($name) ? $name : null,
            'attrs'        => \is_array($attributes) ? $attributes : [],
            'innerBlocks'  => \is_array($inner_blocks) ? array_values(array_filter($inner_blocks, 'is_array')) : [],
            'innerHTML'    => \is_string($inner_html) ? $inner_html : '',
            'innerContent' => \is_array($inner_content)
                ? array_values(array_filter($inner_content, static fn (mixed $chunk): bool => null === $chunk || \is_string($chunk)))
                : [],
        ];
    }

    /**
     * The empty cart's own opening and closing markup around this many
     * inner blocks.
     *
     * @param list<string|null> $chunks
     * @return list<string|null>
     */
    private static function inner_content(array $chunks, int $count): array
    {
        $first   = $chunks[0] ?? null;
        $last    = [] === $chunks ? null : $chunks[array_key_last($chunks)];
        $wrapped = \count($chunks) > 1 && \is_string($first) && \is_string($last);

        return [
            $wrapped ? $first : self::WRAPPER_OPEN,
            ...array_fill(0, $count, null),
            $wrapped ? $last : self::WRAPPER_CLOSE,
        ];
    }
}
