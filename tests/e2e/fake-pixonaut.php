<?php

/**
 * Plugin Name: Fake Pixonaut for WooCommerce
 * Description: Prints Pixonaut for WooCommerce's documented markup and fires its documented hooks, for the integration and end-to-end suites.
 *
 * Mapped into wp-env's tests site only (.wp-env.json), never into a store or
 * the development site, which runs the real plugin a developer chose.
 * Both suites run on the tests site, so they see what the real plugin prints
 * without the plugin or a Pixonaut account, and
 * the theme's callbacks on the plugin's hooks run in them:
 *
 * - On the product page: its preview, a side's frame with its preview and
 *   views elements and the note under it, in place of
 *   the product gallery block, and its form, with the approval box and its
 *   error, at `woocommerce_before_add_to_cart_button` inside the add-to-cart
 *   form. Submitting the form unticked shows the error, as the plugin's
 *   storefront script does, until the box is ticked.
 * - On a product page whose address has `fake_personalise_button`: the
 *   never-drawn element the button's state hangs on, then the Personalise
 *   button, or its message when personalisation is unavailable, as siblings of
 *   the quantity and WooCommerce's button, which it hides as the plugin's
 *   stylesheet does. The button carries WooCommerce's button classes. When a
 *   design waits, WooCommerce's button shows instead, under the plugin's box.
 *   When a returned design can be kept, a second button stands before it.
 * - In the cart, the checkout and the mini cart: a personalised line's rows,
 *   its side's and then the design's field rows, with the edit link on the
 *   last, through `woocommerce_get_item_data`, filtered through
 *   `pixonaut_wc_cart_item_display`.
 * - On order lines: the design's field rows and the line's state row through
 *   `woocommerce_order_item_get_formatted_meta_data`, filtered through
 *   `pixonaut_wc_fulfilment_line_display`.
 * - For a visitor whose cookie names them: which product attribute applies
 *   only under which values of another, through
 *   `pixonaut_wc_storefront_attribute_dependencies`. The plugin reads them
 *   from the store's variations; a suite names the ones its fixture has.
 *
 * Each only for the two products the suites create for it, and the cart and
 * order lines it marks as personalised when one is added to the cart and
 * checked out, as the plugin keeps them. The markup, row shapes,
 * hook names and arguments are the plugin's documented ones
 * (docs/developer/hooks.md in its repository); the labels are its English
 * defaults. When the plugin changes them, change this in the same change.
 *
 * @package Pixonaut\Shop
 */

declare(strict_types=1);

/** The products the suites create that the fake personalises (tests/Support/ShopFixtures.php): a simple one, and one sold in sizes. */
const PIXONAUT_SHOP_FAKE_PRODUCTS = ['fake-pixonaut-product', 'fake-pixonaut-variable-product'];

/** The real plugin's main class; mu-plugins load before it, so it is looked for at render time. */
const PIXONAUT_SHOP_REAL_PLUGIN = 'Pixonaut\\WooCommerce\\Plugin';

/** The one side the fake product has, by its name as the plugin shows it. */
const PIXONAUT_SHOP_FAKE_SIDE = 'Front';

/** The address's argument that shows the Personalise button, and what it may say. */
const PIXONAUT_SHOP_FAKE_BUTTON_ARG = 'fake_personalise_button';

const PIXONAUT_SHOP_FAKE_BUTTON_ONE_AT_A_TIME = 'one-at-a-time';

const PIXONAUT_SHOP_FAKE_BUTTON_UNAVAILABLE = 'unavailable';

const PIXONAUT_SHOP_FAKE_BUTTON_DESIGNED = 'designed';

const PIXONAUT_SHOP_FAKE_BUTTON_KEEP_DESIGN = 'keep-design';

/** The plugin's rules for the button and what follows it (`assets/src/storefront/storefront.css`). */
const PIXONAUT_SHOP_FAKE_BUTTON_STYLE = '.pixonaut-personalise:not([hidden]) ~ .single_add_to_cart_button:not(.pixonaut-personalise__button),'
    . ' form:has(.pixonaut-personalise:not([hidden])) [type="submit"]:not(.pixonaut-personalise__button) { display: none !important; }'
    . ' .pixonaut-personalise { display: none !important; }'
    . ' .pixonaut-personalise__unavailable { margin: 0 0 1em; }'
    . ' .pixonaut-personalised { box-sizing: border-box; width: 100%; flex: 0 0 100%; grid-column: 1 / -1; }'
    . ' .pixonaut-personalise__button[hidden] { display: none !important; }';

/** Where the fake side's proof is; nothing is served there, and no suite follows the link. */
const PIXONAUT_SHOP_FAKE_PROOF_URL = 'https://proofs.invalid/fake-pixonaut/front.png';

/** The key the plugin gives an order line's state row (`Fulfilment\LineDisplay::STATE_ROW`). */
const PIXONAUT_SHOP_FAKE_STATE_ROW = 'pixonaut_line_state';

/** The state the fake gives every order line: the plugin's `LineState::APPROVED`. */
const PIXONAUT_SHOP_FAKE_LINE_STATE = 'approved';

/** Where the plugin keeps a personalised cart line in the cart item (`Cart\CartLine::KEY`). */
const PIXONAUT_SHOP_FAKE_CART_LINE = 'pixonaut';

/** The order line meta the plugin keeps a personalised line's state in (`Fulfilment\LineState::META_KEY`). */
const PIXONAUT_SHOP_FAKE_LINE_META = '_pixonaut_line';

/** The fake side's key, which names its field rows on an order line. */
const PIXONAUT_SHOP_FAKE_SIDE_KEY = 'front';

/**
 * The fields the customer saw on the fake side when they approved it, in
 * order, as the plugin keeps them on the line (`Cart\FieldValue`): the label
 * the store set, the field's type and the display value. The plugin keeps no
 * field with an empty value, except a picture, which keeps none at all.
 */
const PIXONAUT_SHOP_FAKE_FIELDS = [
    ['label' => 'Name', 'type' => 'text', 'value' => 'Alex'],
    ['label' => 'Greeting', 'type' => 'text', 'value' => 'Happy birthday'],
    ['label' => '', 'type' => 'color', 'value' => '#1e6b52'],
    ['label' => 'Photo', 'type' => 'image', 'value' => ''],
];

/**
 * The plugin's default labels for a field without one, by type
 * (`field_text`, `field_choice`, …); the picture's is also its value.
 */
const PIXONAUT_SHOP_FAKE_FIELD_TYPE_LABELS = [
    'text'   => 'Text',
    'select' => 'Choice',
    'number' => 'Number',
    'color'  => 'Colour',
    'image'  => 'Image',
];

/** The one field type whose value is its type's label, never a URL or file name. */
const PIXONAUT_SHOP_FAKE_PICTURE_FIELD = 'image';

/** The index prefix of a design's field rows among an order line's displayed meta. */
const PIXONAUT_SHOP_FAKE_FIELD_ROW = 'pixonaut_field_';

/** The approval box's field (`Cart\AddToCart::APPROVAL_FIELD`). */
const PIXONAUT_SHOP_FAKE_APPROVAL_FIELD = 'pixonaut_approve';

/** The plugin's default `preview_note` label, under its preview. */
const PIXONAUT_SHOP_FAKE_PREVIEW_NOTE = 'The preview updates as you type';

/** The plugin's default `approval_required` label, shown when the form is sent unapproved. */
const PIXONAUT_SHOP_FAKE_APPROVAL_REQUIRED = 'Please tick "I approve the preview" before adding this product to your cart.';

/**
 * The plugin's storefront behaviour for an unticked approval box: the
 * browser's own message is cancelled, the box is marked invalid and focused,
 * and the error under it shows the label; ticking the box clears all three.
 * The form still does not send, through the box's `required`.
 */
const PIXONAUT_SHOP_FAKE_APPROVAL_SCRIPT = <<<'JS'
document.querySelectorAll('.pixonaut-approval').forEach((approval) => {
    const box = approval.querySelector('.pixonaut-approval__box');
    const error = approval.querySelector('.pixonaut-approval__error');
    box.addEventListener('invalid', (event) => {
        event.preventDefault();
        approval.classList.add('is-invalid');
        box.setAttribute('aria-invalid', 'true');
        box.focus();
        error.textContent = error.dataset.fakeMessage;
        error.hidden = false;
    });
    box.addEventListener('change', () => {
        if (box.checked) {
            approval.classList.remove('is-invalid');
            box.removeAttribute('aria-invalid');
            error.textContent = '';
            error.hidden = true;
        }
    });
});
JS;

/** The plugin's default `line_state` label, which names the state row. */
const PIXONAUT_SHOP_FAKE_STATE_LABEL = 'Status';

/** The plugin's default label for each line state (`line_state_<state>`). */
const PIXONAUT_SHOP_FAKE_STATE_LABELS = [
    'committed'         => 'Design saved',
    'awaiting_customer' => 'Awaiting your approval',
    'approved'          => 'Design approved',
    'printed'           => 'Print file ready',
    'ordered'           => 'In production',
    'shipped'           => 'Shipped',
    'failed'            => 'Could not be produced',
    'cancelled'         => 'Cancelled',
    'needs_attention'   => 'Being reviewed by the store',
];

/** Whether the real plugin, mapped in through .wp-env.override.json, is there to take precedence. */
function pixonaut_shop_fake_is_standing_in(): bool
{
    return ! class_exists(PIXONAUT_SHOP_REAL_PLUGIN);
}

/** Whether a product is the one the fake personalises. */
function pixonaut_shop_fake_personalises(mixed $product): bool
{
    return pixonaut_shop_fake_is_standing_in()
        && $product instanceof WC_Product
        && in_array($product->get_slug(), PIXONAUT_SHOP_FAKE_PRODUCTS, true);
}

/**
 * The state stored on a personalised order line, or `null` for a line the
 * plugin does not track. Read from the line, as the plugin does, so a line
 * keeps it after its product is renamed or deleted.
 */
function pixonaut_shop_fake_line_state(WC_Order_Item $item): ?string
{
    if (! pixonaut_shop_fake_is_standing_in() || ! $item instanceof WC_Order_Item_Product) {
        return null;
    }

    $line = json_decode((string) $item->get_meta(PIXONAUT_SHOP_FAKE_LINE_META), true);

    return is_array($line) && is_string($line['state'] ?? null) ? $line['state'] : null;
}

/**
 * A stored field as the plugin shows it: its label, or its type's label when
 * it has none, and its value, or the picture label for a picture.
 *
 * @param array<string, mixed> $field
 * @return array{string, string}
 */
function pixonaut_shop_fake_field_label_and_value(array $field): array
{
    $type = (string) ($field['type'] ?? '');
    $type_label = PIXONAUT_SHOP_FAKE_FIELD_TYPE_LABELS[$type] ?? '';
    $label = '' === (string) ($field['label'] ?? '') ? $type_label : (string) $field['label'];
    $value = PIXONAUT_SHOP_FAKE_PICTURE_FIELD === $type ? $type_label : (string) ($field['value'] ?? '');

    return [$label, $value];
}

/** A field's value as escaped HTML, its line breaks kept. */
function pixonaut_shop_fake_field_display(string $value): string
{
    return nl2br(esc_html($value), false);
}

/**
 * A personalised order line's field rows, keyed as the plugin keys them
 * (`pixonaut_field_<side>_<n>`, `<n>` the field's place among its side's
 * stored fields), with the label and value escaped for display.
 *
 * @return array<string, stdClass>
 */
function pixonaut_shop_fake_field_rows(WC_Order_Item $item): array
{
    $line = json_decode((string) $item->get_meta(PIXONAUT_SHOP_FAKE_LINE_META), true);
    $sides = is_array($line) && is_array($line['fields'] ?? null) ? $line['fields'] : [];
    $rows = [];

    foreach ($sides as $side => $fields) {
        foreach (array_values(array_filter((array) $fields, 'is_array')) as $position => $field) {
            [$label, $value] = pixonaut_shop_fake_field_label_and_value($field);

            $rows[PIXONAUT_SHOP_FAKE_FIELD_ROW . $side . '_' . $position] = (object) [
                'key'           => $label,
                'value'         => $value,
                'display_key'   => esc_html($label),
                'display_value' => pixonaut_shop_fake_field_display($value),
            ];
        }
    }

    return $rows;
}

/** Whether this request is the suite's product page. */
function pixonaut_shop_fake_is_personalised(): bool
{
    return pixonaut_shop_fake_is_standing_in()
        && function_exists('is_product')
        && is_product()
        && in_array(get_post_field('post_name', get_queried_object_id()), PIXONAUT_SHOP_FAKE_PRODUCTS, true);
}

add_filter(
    'render_block_woocommerce/product-image-gallery',
    static fn (string $content): string => pixonaut_shop_fake_is_personalised()
        ? '<div class="pixonaut-preview" data-fake-pixonaut="preview">'
            . '<div class="pixonaut-preview__frame" data-pixonaut-side="front">'
            . '<div class="pixonaut-preview__stage">'
            . '<div id="pixonaut-preview-front" class="pixonaut-preview__side" data-pixonaut-side="front"></div>'
            . '</div>'
            . '<div id="pixonaut-views-front" class="pixonaut-views" data-pixonaut-side="front"></div>'
            . '</div>'
            . '<p class="pixonaut-preview__note">' . esc_html(PIXONAUT_SHOP_FAKE_PREVIEW_NOTE) . '</p>'
            . '</div>'
        : $content
);

add_action(
    'woocommerce_before_add_to_cart_button',
    static function (): void {
        if (pixonaut_shop_fake_is_personalised()) {
            echo '<div class="pixonaut-form" data-fake-pixonaut="form">'
                . '<div class="pixonaut-approval" data-fake-pixonaut="approval">'
                . '<label class="pixonaut-approval__label">'
                . '<input type="checkbox" class="pixonaut-approval__box" name="' . PIXONAUT_SHOP_FAKE_APPROVAL_FIELD . '" value="yes" required /> '
                . 'I approve the preview'
                . '</label>'
                . '<button type="button" class="pixonaut-approval__proof" data-pixonaut-side="front">Open proof</button>'
                . '<p class="pixonaut-approval__error" role="alert" data-fake-message="' . esc_attr(PIXONAUT_SHOP_FAKE_APPROVAL_REQUIRED) . '" hidden></p>'
                . '</div>'
                . '</div>';
        }
    }
);

/** What the address asks of the Personalise button, or `null` when it does not. */
function pixonaut_shop_fake_button(): ?string
{
    if (! pixonaut_shop_fake_is_standing_in() || ! function_exists('is_product') || ! is_product()) {
        return null;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the suites choose the state in the address.
    $state = sanitize_key(wp_unslash($_GET[PIXONAUT_SHOP_FAKE_BUTTON_ARG] ?? ''));

    return '' === $state ? null : $state;
}

add_filter(
    'woocommerce_is_sold_individually',
    static fn (bool $individually): bool => $individually || PIXONAUT_SHOP_FAKE_BUTTON_ONE_AT_A_TIME === pixonaut_shop_fake_button()
);

add_action(
    'woocommerce_after_add_to_cart_quantity',
    static function (): void {
        $state = pixonaut_shop_fake_button();

        if (null === $state) {
            return;
        }

        $designed = PIXONAUT_SHOP_FAKE_BUTTON_DESIGNED === $state;
        $keep = PIXONAUT_SHOP_FAKE_BUTTON_KEEP_DESIGN === $state
            ? '<button type="submit" class="button pixonaut-personalise__button pixonaut-personalise__retry wp-element-button" name="pixonaut_keep_design" value="1">Use my design</button>'
            : '';

        echo '<style>' . PIXONAUT_SHOP_FAKE_BUTTON_STYLE . '</style>'
            . '<span class="pixonaut-personalise"' . ($designed ? ' hidden' : '') . '></span>'
            . $keep
            . (PIXONAUT_SHOP_FAKE_BUTTON_UNAVAILABLE === $state
                ? '<p class="pixonaut-personalise__unavailable" role="alert">Personalisation is unavailable right now. Please try again later.</p>'
                : '<button type="' . ($designed ? 'button' : 'submit') . '" class="single_add_to_cart_button button alt pixonaut-personalise__button wp-element-button" name="pixonaut_open_designer" value="1"' . ($designed ? ' hidden' : '') . '>Personalise</button>');
    }
);

add_action(
    'woocommerce_before_add_to_cart_button',
    static function (): void {
        if (PIXONAUT_SHOP_FAKE_BUTTON_DESIGNED === pixonaut_shop_fake_button()) {
            echo '<div class="woocommerce-info pixonaut-personalised" role="status">'
                . 'You have personalised this product. '
                . '<a class="pixonaut-personalised__change" href="#">Change</a>'
                . '</div>';
        }
    }
);

add_action(
    'wp_footer',
    static function (): void {
        if (pixonaut_shop_fake_is_personalised()) {
            wp_print_inline_script_tag(PIXONAUT_SHOP_FAKE_APPROVAL_SCRIPT);
        }
    }
);

// Adding the product to the cart makes a personalised cart line, and
// checking out turns it into a personalised order line, as the plugin does.
add_filter(
    'woocommerce_add_cart_item_data',
    /**
     * @param array<string, mixed> $cart_item_data
     * @return array<string, mixed>
     */
    static function (array $cart_item_data, int $product_id): array {
        if (pixonaut_shop_fake_personalises(wc_get_product($product_id))) {
            $cart_item_data[PIXONAUT_SHOP_FAKE_CART_LINE] = [
                'sides'  => [PIXONAUT_SHOP_FAKE_SIDE],
                'fields' => [PIXONAUT_SHOP_FAKE_SIDE_KEY => PIXONAUT_SHOP_FAKE_FIELDS],
            ];
        }

        return $cart_item_data;
    },
    10,
    2
);

add_action(
    'woocommerce_checkout_create_order_line_item',
    /**
     * @param array<string, mixed> $cart_item
     */
    static function (WC_Order_Item_Product $item, string $cart_item_key, array $cart_item): void {
        if (pixonaut_shop_fake_is_standing_in() && isset($cart_item[PIXONAUT_SHOP_FAKE_CART_LINE])) {
            $item->update_meta_data(
                PIXONAUT_SHOP_FAKE_LINE_META,
                (string) wp_json_encode([
                    'state'  => PIXONAUT_SHOP_FAKE_LINE_STATE,
                    'fields' => $cart_item[PIXONAUT_SHOP_FAKE_CART_LINE]['fields'] ?? [],
                ])
            );
        }
    },
    10,
    3
);

add_filter(
    'woocommerce_get_item_data',
    /**
     * @param array<int, array<string, mixed>> $item_data
     * @param array<string, mixed>             $cart_item
     * @return array<int, array<string, mixed>>
     */
    static function (array $item_data, array $cart_item): array {
        $product = $cart_item['data'] ?? null;

        if (! pixonaut_shop_fake_is_standing_in() || ! isset($cart_item[PIXONAUT_SHOP_FAKE_CART_LINE]) || ! $product instanceof WC_Product) {
            return $item_data;
        }

        $rows = [
            [
                'key'     => PIXONAUT_SHOP_FAKE_SIDE,
                'value'   => PIXONAUT_SHOP_FAKE_PROOF_URL,
                'display' => '<a class="pixonaut-proof-link" href="' . esc_url(PIXONAUT_SHOP_FAKE_PROOF_URL) . '" target="_blank" rel="noopener">View proof</a>',
            ],
        ];

        foreach ((array) ($cart_item[PIXONAUT_SHOP_FAKE_CART_LINE]['fields'][PIXONAUT_SHOP_FAKE_SIDE_KEY] ?? []) as $field) {
            [$label, $value] = pixonaut_shop_fake_field_label_and_value((array) $field);
            $rows[] = ['key' => $label, 'value' => $value, 'display' => pixonaut_shop_fake_field_display($value)];
        }

        // The side's edit link goes on its last row: its last field row, or
        // the side's own when it lists no values.
        $rows[array_key_last($rows)]['display'] .= ' <a class="pixonaut-edit-design" href="' . esc_url(get_permalink($product->get_id())) . '">Edit design</a>';

        return array_merge($item_data, (array) apply_filters('pixonaut_wc_cart_item_display', $rows, $cart_item));
    },
    10,
    2
);

add_filter(
    'woocommerce_order_item_get_formatted_meta_data',
    /**
     * @param array<int|string, stdClass> $rows
     * @return array<int|string, stdClass>
     */
    static function (array $rows, WC_Order_Item $item): array {
        $state = pixonaut_shop_fake_line_state($item);

        if (null === $state) {
            return $rows;
        }

        $rows += pixonaut_shop_fake_field_rows($item);
        $rows[PIXONAUT_SHOP_FAKE_STATE_ROW] = (object) [
            'key'           => PIXONAUT_SHOP_FAKE_STATE_LABEL,
            'value'         => $state,
            'display_key'   => esc_html(PIXONAUT_SHOP_FAKE_STATE_LABEL),
            'display_value' => esc_html(PIXONAUT_SHOP_FAKE_STATE_LABELS[$state] ?? $state),
        ];

        // The plugin's third argument is its `Fulfilment\LineState`; the
        // theme never reads it, so a stand-in with the state will do.
        $line = (object) ['state' => $state];

        return (array) apply_filters('pixonaut_wc_fulfilment_line_display', $rows, $item, $line);
    },
    10,
    2
);

/** The cookie a suite names the attribute dependencies in, as the filter's JSON. */
const PIXONAUT_SHOP_FAKE_DEPENDENCIES_COOKIE = 'fake_attribute_dependencies';

add_filter(
    'pixonaut_wc_storefront_attribute_dependencies',
    static function (mixed $dependencies): mixed {
        $named = $_COOKIE[PIXONAUT_SHOP_FAKE_DEPENDENCIES_COOKIE] ?? null;

        if (! is_string($named) || ! pixonaut_shop_fake_is_standing_in()) {
            return $dependencies;
        }

        $decoded = json_decode(wp_unslash($named), true);

        return is_array($decoded) ? $decoded : $dependencies;
    }
);

/**
 * The cookie a suite turns the archive's index on with: `1` serves it, `404`
 * answers that the archive has none, `slow` takes longer than the theme waits.
 * Added after a comma: `pictures` gives each framed variation a picture of
 * its own, `missing` gives it one whose file is gone, and `split` lists a
 * product once per framing while that filter is on (one design, a card per
 * framing), as the plugin lists a split product.
 */
const PIXONAUT_SHOP_FAKE_INDEX_COOKIE = 'fake_archive_index';

/** The attribute a split product's cards hold one value each of, by its short name. */
const PIXONAUT_SHOP_FAKE_SPLIT_KEY = 'framing';

/** The framing whose variations get a picture of their own under `pictures` and `missing`. */
const PIXONAUT_SHOP_FAKE_PICTURED_VALUE = 'frame';

/** The size the shop's cards print a picture at: WooCommerce's Product Image block always prints `large`. */
const PIXONAUT_SHOP_FAKE_CARD_IMAGE_SIZE = 'large';

/** A card's picture as the index lists it, at the size the cards print; the placeholder for none. */
function pixonaut_shop_fake_index_picture(int $attachment, string $alt): array
{
    $image = $attachment > 0 ? wp_get_attachment_image_src($attachment, PIXONAUT_SHOP_FAKE_CARD_IMAGE_SIZE) : false;

    if (is_array($image)) {
        return ['src' => $image[0], 'srcset' => (string) wp_get_attachment_image_srcset($attachment, PIXONAUT_SHOP_FAKE_CARD_IMAGE_SIZE), 'width' => (int) $image[1], 'height' => (int) $image[2], 'alt' => $alt];
    }

    $size = wc_get_image_size(PIXONAUT_SHOP_FAKE_CARD_IMAGE_SIZE);

    return ['src' => wc_placeholder_img_src(PIXONAUT_SHOP_FAKE_CARD_IMAGE_SIZE), 'srcset' => '', 'width' => (int) ($size['width'] ?? 0), 'height' => (int) ($size['height'] ?? 0), 'alt' => $alt];
}

const PIXONAUT_SHOP_FAKE_INDEX_ROUTE = 'pixonaut-fake/v1';

/** Longer than the theme waits for an index, in seconds. */
const PIXONAUT_SHOP_FAKE_INDEX_SLOW_SECONDS = 10;

/**
 * The archive an index is for, as the plugin names it: `shop`, or the
 * term's taxonomy and id; null off a product archive.
 */
function pixonaut_shop_fake_index_archive(): ?string
{
    if (is_shop()) {
        return 'shop';
    }

    $term = get_queried_object();

    return (is_product_category() || is_product_tag()) && $term instanceof WP_Term ? $term->taxonomy . '-' . $term->term_id : null;
}

/** The archive names the index it has, as the plugin's archives do (pixonaut-for-woocommerce's archive index). */
add_action(
    'wp_footer',
    static function (): void {
        $mode    = $_COOKIE[PIXONAUT_SHOP_FAKE_INDEX_COOKIE] ?? null;
        $archive = pixonaut_shop_fake_index_archive();

        if (! is_string($mode) || null === $archive || ! pixonaut_shop_fake_is_standing_in()) {
            return;
        }

        $url = add_query_arg(['v' => '1', 'mode' => $mode], rest_url(PIXONAUT_SHOP_FAKE_INDEX_ROUTE . '/archive-index/' . $archive));
        printf('<script type="application/json" id="pixonaut-archive-index">%s</script>', wp_json_encode(['url' => $url, 'version' => '1', 'count' => 0]));
    }
);

/** The index's attribute values each variation of a product holds, by the attribute's short name. */
function pixonaut_shop_fake_index_values(WC_Product $product, WC_Product $offer): array
{
    $values = [];

    foreach ($product->get_attributes() as $attribute) {
        if (! $attribute instanceof WC_Product_Attribute || ! $attribute->is_taxonomy()) {
            continue;
        }

        $taxonomy = $attribute->get_name();
        $key      = wc_attribute_taxonomy_slug($taxonomy);

        if ($attribute->get_variation() && $offer->is_type('variation')) {
            $slug           = $offer->get_attributes()[$taxonomy] ?? '';
            $values[$key]   = '' === $slug ? [] : [$slug];
            continue;
        }

        $values[$key] = array_values(array_filter(array_map(static fn (int $id): string => (string) get_term($id)?->slug, $attribute->get_options())));
    }

    return $values;
}

add_action(
    'rest_api_init',
    static function (): void {
        register_rest_route(PIXONAUT_SHOP_FAKE_INDEX_ROUTE, '/archive-index/(?P<archive>[a-z_]+-?[0-9]*)', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => static function (WP_REST_Request $request): WP_REST_Response {
                $modes = explode(',', (string) $request->get_param('mode'));
                $mode  = $modes[0];
                // A framed variation's own picture: the store's placeholder, or a file that is not there.
                $images = in_array('missing', $modes, true)
                    ? [['src' => content_url('uploads/pixonaut-shop-missing-picture.png'), 'srcset' => '', 'width' => 600, 'height' => 800]]
                    : [array_diff_key(pixonaut_shop_fake_index_picture(0, ''), ['alt' => true])];
                $pictured = in_array('pictures', $modes, true) || in_array('missing', $modes, true);

                if ('404' === $mode) {
                    return new WP_REST_Response(['code' => 'no_index'], 404);
                }

                if ('slow' === $mode) {
                    sleep(PIXONAUT_SHOP_FAKE_INDEX_SLOW_SECONDS);
                }

                $archive = (string) $request['archive'];
                $query   = ['post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids'];

                if ('shop' !== $archive) {
                    $at                  = (int) strrpos($archive, '-');
                    $query['tax_query']  = [['taxonomy' => substr($archive, 0, $at), 'terms' => [(int) substr($archive, $at + 1)]]]; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- a test double's archive.
                }

                $sorts     = [];
                $positions = [];

                $menu = [
                    'menu_order' => 'Default sorting',
                    'popularity' => 'Sort by popularity',
                    'rating' => 'Sort by average rating',
                    'date' => 'Sort by latest',
                    'price' => 'Sort by price: low to high',
                    'price-desc' => 'Sort by price: high to low',
                ];

                foreach ((array) apply_filters('woocommerce_catalog_orderby', $menu) as $key => $label) {
                    $sorts[]  = ['key' => (string) $key, 'label' => (string) $label];
                    $ordering = WC()->query->get_catalog_ordering_args((string) $key);
                    $ordered  = new WP_Query($query + ['orderby' => $ordering['orderby'], 'order' => $ordering['order']] + ('' === ($ordering['meta_key'] ?? '') ? [] : ['meta_key' => $ordering['meta_key']]));

                    foreach ($ordered->posts as $position => $id) {
                        $positions[(int) $id][(string) $key] = $position;
                    }
                }

                $cards = [];
                $keys  = [];

                foreach ((new WP_Query($query + ['orderby' => 'menu_order title', 'order' => 'ASC']))->posts as $id) {
                    $product = wc_get_product($id);

                    if (! $product instanceof WC_Product) {
                        continue;
                    }

                    $rows = [];

                    foreach ($product->is_type('variable') ? $product->get_children() : [$product->get_id()] as $child) {
                        $offer = wc_get_product($child);

                        if (! $offer instanceof WC_Product || '' === $offer->get_price()) {
                            continue;
                        }

                        $values = pixonaut_shop_fake_index_values($product, $offer);
                        $keys  += array_fill_keys(array_keys($values), true);
                        $row    = [
                            'values' => $values,
                            'price' => (float) wc_get_price_to_display($offer),
                            'regularPrice' => (float) wc_get_price_to_display($offer, ['price' => $offer->get_regular_price()]),
                        ];

                        if ($pictured && in_array(PIXONAUT_SHOP_FAKE_PICTURED_VALUE, $values[PIXONAUT_SHOP_FAKE_SPLIT_KEY] ?? [], true)) {
                            $row['image'] = 0;
                        }

                        $rows[] = apply_filters('pixonaut_wc_archive_index_variation', $row, $offer, $product);
                    }

                    $choices = array_values(array_map(
                        static fn (WC_Product_Attribute $attribute): string => wc_attribute_taxonomy_slug($attribute->get_name()),
                        array_filter($product->get_attributes(), static fn (mixed $attribute): bool => $attribute instanceof WC_Product_Attribute && $attribute->is_taxonomy() && $attribute->get_variation())
                    ));
                    $card    = [
                        'id' => (string) $product->get_id(),
                        'productId' => $product->get_id(),
                        'title' => $product->get_name(),
                        'url' => (string) get_permalink($product->get_id()),
                        'summary' => wp_strip_all_tags($product->get_short_description()),
                        'image' => pixonaut_shop_fake_index_picture((int) $product->get_image_id(), $product->get_name()),
                        'sort' => $positions[$product->get_id()] ?? [],
                        'holds' => new stdClass(),
                        'listedBy' => [],
                        'onlyFiltered' => [],
                        'choices' => $choices,
                        'variations' => $rows,
                    ];
                    $framings = array_values(array_unique(array_merge(...array_map(static fn (array $row): array => $row['values'][PIXONAUT_SHOP_FAKE_SPLIT_KEY] ?? [], $rows))));

                    if (! in_array('split', $modes, true) || count($framings) < 2) {
                        $cards[] = apply_filters('pixonaut_wc_archive_index_card', $card, $product);
                        continue;
                    }

                    // The product as itself while no framing is chosen, and a card per framing while one is.
                    $cards[] = apply_filters('pixonaut_wc_archive_index_card', ['listedBy' => [PIXONAUT_SHOP_FAKE_SPLIT_KEY]] + $card, $product);

                    foreach ($framings as $place => $framing) {
                        $term    = get_term_by('slug', $framing, wc_attribute_taxonomy_name(PIXONAUT_SHOP_FAKE_SPLIT_KEY));
                        $cards[] = apply_filters('pixonaut_wc_archive_index_card', [
                            'id' => $product->get_id() . '-' . $framing,
                            'title' => $product->get_name() . ', ' . ($term instanceof WP_Term ? $term->name : $framing),
                            'url' => add_query_arg(PIXONAUT_SHOP_FAKE_SPLIT_KEY, $framing, $card['url']),
                            // Every other card shows the placeholder, so the design's cards differ in picture.
                            'image' => 0 === $place % 2 ? $card['image'] : pixonaut_shop_fake_index_picture(0, $product->get_name()),
                            'holds' => [PIXONAUT_SHOP_FAKE_SPLIT_KEY => [$framing]],
                            'listedBy' => [PIXONAUT_SHOP_FAKE_SPLIT_KEY],
                            'onlyFiltered' => [PIXONAUT_SHOP_FAKE_SPLIT_KEY],
                            'variations' => array_values(array_filter($rows, static fn (array $row): bool => in_array($framing, $row['values'][PIXONAUT_SHOP_FAKE_SPLIT_KEY] ?? [], true))),
                        ] + $card, $product);
                    }
                }

                $dependencies = (array) apply_filters('pixonaut_wc_storefront_attribute_dependencies', []);
                $filters      = [];

                foreach (array_keys($keys) as $key) {
                    $taxonomy = wc_attribute_taxonomy_name($key);
                    $terms    = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
                    $filter   = [
                        'key' => $key,
                        'label' => wc_attribute_label($taxonomy),
                        'type' => 'attribute',
                        'values' => array_map(static fn (WP_Term $term): array => ['id' => $term->slug, 'label' => $term->name, 'order' => (int) get_term_meta($term->term_id, 'order', true)], is_array($terms) ? $terms : []),
                    ];

                    foreach ((array) ($dependencies[$taxonomy] ?? []) as $parent => $under) {
                        $filter['parent'] = ['key' => wc_attribute_taxonomy_slug((string) $parent), 'values' => array_values((array) $under)];
                    }

                    $filters[] = $filter;
                }

                $filters[] = ['key' => 'price', 'label' => 'Price', 'type' => 'price'];

                return new WP_REST_Response([
                    'version' => '1',
                    'currency' => [
                        'code' => get_woocommerce_currency(),
                        'symbol' => html_entity_decode(get_woocommerce_currency_symbol()),
                        'position' => (string) get_option('woocommerce_currency_pos'),
                        'decimals' => wc_get_price_decimals(),
                        'decimalSeparator' => wc_get_price_decimal_separator(),
                        'thousandSeparator' => wc_get_price_thousand_separator(),
                    ],
                    'filters' => $filters,
                    'sorts' => $sorts,
                    'cards' => $cards,
                    'images' => $pictured ? $images : [],
                ]);
            },
        ]);
    }
);

/** The cookie a suite names a card's swatches in, as JSON by product name: what the plugin names for a design shown in several values. */
const PIXONAUT_SHOP_FAKE_SWATCHES_COOKIE = 'fake_card_swatches';

add_filter(
    'pixonaut_wc_storefront_card_swatches',
    static function (mixed $swatches, mixed $product): mixed {
        $named = $_COOKIE[PIXONAUT_SHOP_FAKE_SWATCHES_COOKIE] ?? null;

        if (! is_string($named) || ! $product instanceof WC_Product || ! pixonaut_shop_fake_is_standing_in()) {
            return $swatches;
        }

        $decoded = json_decode(wp_unslash($named), true);

        return is_array($decoded) && is_array($decoded[$product->get_name()] ?? null) ? $decoded[$product->get_name()] : $swatches;
    },
    10,
    2
);
