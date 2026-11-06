# Architecture

## Directory layout

| Directory | Contents |
| --- | --- |
| `inc/` | The theme's PHP, autoloaded under `Pixonaut\Shop\`. |
| `templates/` | Block templates every store renders: `index`, `page`, `404`, and WooCommerce's `single-product`, `archive-product`, `product-search-results`, `page-cart`, `page-checkout`, and `search` for a search of the whole site. |
| `parts/` | The header part, holding its blocks itself; the announcement part above its row, empty until a store fills it; the footer part, a reference to a pattern; and the mini-cart part, the contents of WooCommerce's mini-cart drawer. |
| `patterns/` | PHP patterns holding markup with translated strings or a store's own URLs: the footer, the 404 page, the announcement bar a store inserts, the product grid, its no-products panel, the popular products, what a search without results shows and the promises. |
| `styles/` | Style variations: starting looks a store picks in Styles. Nature today. |
| `theme.json` | The presets and default styles every store starts from. |
| `src/` | The theme's own stylesheet, `app.css` importing one partial per area from `src/styles/`, and its blocks in `src/blocks/<name>/`, compiled by `pnpm build` into `build/` (gitignored). |
| `assets/fonts/` | The default typeface, Inter, as WOFF2 files with its licence, loaded by `theme.json`. |
| `languages/` | The translation catalogue: `pixonaut-shop.pot`, `sv_SE.po`, `sv_SE.mo`. |
| `tests/` | PHPUnit, Playwright and Node tests; `tests/Unit/` mirrors `inc/`. |
| `scripts/` | The Docker helpers, CI's classifier and catalogue check, and the release version scripts. |
| `docs/` | This documentation set. |
| `vendor/` | Composer dependencies (gitignored; the release zip carries the runtime ones). |

## Autoloading and the `functions.php` fallback

Composer's PSR-4 autoloader maps `Pixonaut\Shop\` to `inc/`. `functions.php`
requires `vendor/autoload.php` when it is there; when it isn't — a theme
uploaded without Composer — it falls back to a small `spl_autoload_register`
that resolves the same namespace against `inc/`
(`Support\FallbackAutoloadPath`), so the theme still boots, without the
update checker, whose library comes from Composer. Either way it ends with:

```php
(new \Pixonaut\Shop\Bootstrap())->register();
```

## `Bootstrap`

[`inc/Bootstrap.php`](../inc/Bootstrap.php) is a `final class` with one
public `register()` that calls a private method per area, each constructing
its modules and calling their `register()`:

| Area | Module | What it does |
| --- | --- | --- |
| i18n | `I18n\TextDomain` | Loads `languages/` under the `pixonaut-shop` text domain on `init`. |
| assets | `Assets\AssetManager` | Enqueues `build/app.css` on the front end and in the editor canvas. |
| blocks | `Blocks\BlockStyles` | Registers the block styles templates and content build pages from — Panel, Band (tint 1), Band (tint 2) and Notice on core/group, Pill on core/paragraph, Text link on core/button — with their values as presets, which a store's Styles edits. |
| blocks | `Blocks\AspectRatios` | Adds the print shapes — square, then 4:5, 5:7, 3:4 and 2:3 and their landscape turns — to the theme's aspect-ratio presets, which the Site Editor lists for a picture in place of WordPress's own; a developer changes them through the `pixonaut_shop_aspect_ratios` filter. |
| blocks | `Blocks\BlockLoader` | Registers every block built into `build/blocks/` on `init`. Not in the wordpress.org build. |
| blocks | `Blocks\FreeShippingNote` | Adds the free-shipping note's text to the Store API's cart, where the mini-cart drawer reads it. Not in the wordpress.org build. |
| blocks | `Blocks\ThemeBlocks` | Says whether one of the theme's own blocks is registered, for the patterns that fall back without it. |
| content | `Content\PageExcerpt` | Gives pages an excerpt, which the page template shows as the lede under the title, and prints nothing in its place for a page without one, rather than the page's opening words. |
| archive | `Archive\ProductGridDefaults` | Starts WooCommerce's Product Catalog setting, which sizes the shop's and its archives' pages, at three rows of the grid's four columns when the theme is activated; the store changes it from there. |
| archive | `Archive\ShownWhenEmpty` | Skips the popular products, before their query runs, on an archive or a product search that found products of its own, and a search's no-results notice and categories on a search that found something; an empty archive or search shows them, the popular products unless there is nothing to recommend. |
| archive | `Archive\NoResultsTitle` | Prints a search's no-results title again as it renders, with this request's query, since a template saved in the Site Editor holds the query of the request that saved it. |
| product | `Product\PreviewFrame` | Offers a frame style per print shape on the Product Image Gallery block, and holds the product page's preview, or the gallery, at the one the store picks; without one every side keeps the shape Pixonaut for WooCommerce opens the first side at. |
| dependencies | `Dependencies\WooCommerceNotice` | Tells an administrator who can install plugins that the shop needs WooCommerce, while it is inactive, in a notice they can dismiss for good. |
| pixonaut | `Pixonaut\Integration` | Holds the theme's callbacks on Pixonaut for WooCommerce's documented hooks, and their names; they run only when the plugin fires them. |
| updates | `Updates\Updates` | Starts the update checker. Not in the wordpress.org build. |

Hooks are added with explicit priorities inside each module where a priority
matters; the call order in `Bootstrap` is documentation, not a dependency
graph.

## Adding a module

1. Add a `final class` under `inc/<Area>/` with a single public
   `register(): void` holding every `add_action`/`add_filter` it needs
   ([`docs/CONVENTIONS.md`](CONVENTIONS.md)).
2. Construct it and call `register()` from `Bootstrap`, in its area's private
   method or a new one.
3. Add a mirrored test under `tests/Unit/<Area>/<Class>Test.php`, extending
   `tests/TestCase.php` and carrying `#[CoversClass(...)]` — written first.
4. Give a new `inc/` directory an entry in `E2E_OWNERS` in
   `scripts/ci/affected.ts`; `tests/ts/ci-affected.test.ts` fails until it
   has one.

## The theme's blocks

Each block lives in `src/blocks/<name>/`: `block.json` (its name, title,
attributes and `"textdomain": "pixonaut-shop"`), `index.tsx` and
`edit.tsx` (the editor script, which shows the block through
`ServerSideRender`), `render.php` (its front-end markup) and `style.css`
(its own stylesheet, preset values only). `pnpm build` finds every
`block.json` under `src/blocks/` and builds each block, with its
`render.php`, into `build/blocks/<name>/`; `Blocks\BlockLoader` registers
whatever is there, so a new block needs no PHP of its own to load.
`render.php` stays thin and calls a class in `inc/Blocks/`, which the unit
suite tests without WordPress.

| Block | Class | What it shows |
| --- | --- | --- |
| `pixonaut-shop/product-categories` | `Blocks\ProductCategories` | The store's top-level product categories with products, by product count or the store's category order, as tiles (picture, name, count) or outline buttons. WooCommerce's default category is left out; nothing renders without a category. |
| `pixonaut-shop/icon` | `Blocks\Icons` | One line icon from a fixed set, in `currentColor`, optionally in a circle. A name is saved in the block, so it never changes. |
| `pixonaut-shop/free-shipping-note` | `Blocks\FreeShippingNote` | How much more the cart needs for free shipping, or that shipping is free, from the free-shipping method of the zone the cart ships to; nothing without one that has a minimum. Placed only inside the mini-cart. |

The wordpress.org theme directory leaves blocks to plugins, so the build
uploaded there ships none of these (`docs/DEPLOY.md`). Every pattern that
holds one asks `Blocks\ThemeBlocks` whether it is registered and falls back
without it: the categories to WooCommerce's product categories block, which
`src/styles/_categories.css` styles as the same tiles or buttons; the icons
and the free-shipping note to nothing. The mini-cart part reaches its note
and its empty-cart icon through the `free-shipping-note` and
`mini-cart-empty-icon` patterns for that. The check is by block name, so the
blocks could move into a plugin without the patterns changing.

## Templates and WooCommerce

The WooCommerce templates start from WooCommerce's own block templates, with
the theme's header and footer parts. WooCommerce uses a theme's
`single-product.html`, `archive-product.html`,
`product-search-results.html`, `page-cart.html` and `page-checkout.html` in
place of its own; the category, tag and attribute archives fall back to
`archive-product`. A product search (`?s=…&post_type=product`) renders
`product-search-results`, and any other search core's `search`.

`single-product.html` is shared with Pixonaut for WooCommerce, which prints
its designer into it: its preview replaces the
`woocommerce/product-image-gallery` block's output, and its form is printed at
`woocommerce_before_add_to_cart_button` inside the
`woocommerce/add-to-cart-form` block. Both blocks stay; the theme adds nothing
of the designer's own.

The product's price is not a block of the template: `inc/Product/PriceRow.php`
prints it in the add-to-cart form's last row, beside the quantity and the
button, for simple and variable products. The row opens at
`woocommerce_before_add_to_cart_quantity` and closes at
`woocommerce_after_add_to_cart_button`, so the plugin's form and approval box
stay above it. The price's wrapper,
`<div class="pixonaut-shop-price" data-pixonaut-price>`, is the target the
plugin rewrites when the chosen size changes the price; the plugin finds it
by `data-pixonaut-price` only, so that attribute stays. Below the purchase
panel, the `pixonaut-shop/product-description` pattern puts
`woocommerce/product-details` in a panel, and
`pixonaut-shop/related-products` shows four related products as the product
grid draws them.

My account is WooCommerce's classic account page, printed by its shortcode
in the store's My account page and styled by `src/styles/_account.css`; the
theme copies none of its templates into `woocommerce/`, since they change
with WooCommerce's versions. `inc/Account/` adds to it through
WooCommerce's hooks: `Dashboard` puts the shortcuts and the recent orders,
in WooCommerce's own orders table, under the greeting
(`woocommerce_account_dashboard`), and `OrderStatus` marks each order's
status in that table so it reads as a pill. Where an order's lines carry
Pixonaut for WooCommerce's state row, the pill shows the state of the line
furthest behind, in the plugin's words, read from the lines' displayed meta
(`get_formatted_meta_data()`), never from the plugin. `ViewOrder` adds to a
single order's view: breadcrumbs back through the orders before
WooCommerce's page, the order's number, date, total and status as an
overview under WooCommerce's status sentence, a panel around WooCommerce's
order and customer details, and the store's `order-help` part after them.

What the plugin prints, the theme may reshape through the plugin's
documented hooks and style through its documented classes, from
`inc/Pixonaut/`; it never calls the plugin's code or copies its
templates. On order lines it wraps the line's state and tracking values in
the theme's classes (`pixonaut-shop-line-state`, `pixonaut-shop-line-tracking`)
so the order pages can show the state as a pill; a design's field rows
(`pixonaut_field_<side>_<n>`) are ordinary item meta rows, styled as the
line's details.

## The mini-cart drawer

The header's bag opens WooCommerce's mini-cart drawer, which WooCommerce
renders on the server and keeps up to date with the Interactivity API, its
cart in the `woocommerce` store's `state.cart`, refreshed from the Store
API's cart whenever the cart changes. The drawer's contents are the theme's
`parts/mini-cart.html`, WooCommerce's own blocks in the design's order: the
title, the lines, and the footer with the subtotal, the free-shipping note
and the checkout and cart buttons; empty, the bag in a circle, WooCommerce's
empty-cart heading and its button back to the shop.

The free-shipping note works its text out on the server, in
`Blocks\FreeShippingNote`, as WooCommerce's free-shipping method decides
whether shipping is free, and formats the amount with WooCommerce's price
formatting. It adds that text to the Store API's cart under
`extensions.pixonaut-shop.free_shipping_note`, and the block's directives
read it from `woocommerce::state.cart`, so the note follows every change to
the cart in the drawer without a script of its own.

## The header and footer

The header is one row: the Site Logo and the site title, the menu, product
search, the account link and the mini-cart. Above it, the `announcement`
part, which the theme ships empty; a store that wants a bar inserts the
`pixonaut-shop/announcement` pattern there and writes its text. The menu
collapses into the menu button at 1280px and below
(`src/styles/_header.css`), where a store's menu would otherwise crowd the
row, not only below core's 600px.

The footer's two menus are each headed by the menu's own name, which core
prints only as the `<nav>`'s accessible name: `inc/Blocks/MenuHeading.php`
adds the heading to any navigation block carrying the
`pixonaut-shop-headed-menu` class that names a published menu. The Site
Editor renders the block in the browser, past that filter, so the editor
script `src/editor/menu-heading.js` heads it there the same way.

## The update checker

`inc/Updates/` follows Pixonaut for WooCommerce's `src/Updates/`:

| Class | Role |
| --- | --- |
| `Updates` | Reads the optional `PIXONAUT_SHOP_UPDATE_TOKEN` from `wp-config.php`; without the library, does nothing. |
| `GitHubUpdater` | Builds plugin-update-checker's `ThemeUpdateChecker` for the repository in style.css's `Update URI`: releases only, stable `vX.Y.Z` only, only with `pixonaut-shop-X.Y.Z.zip` attached, a token, when set, as an `Authorization` header, its state in the `pixonaut_shop_update_state` option, a check every 12 hours. |
| `GitHubReleases` | The library's GitHub client, with the release list sorted by version rather than publication date. |

The checker reads the offered version from style.css at the release's tag
(`docs/DEPLOY.md`).
