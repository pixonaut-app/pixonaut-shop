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
| `pixonaut-shop/group-tiles` | `Blocks\GroupTiles` | A heading, then the subcategories with products of the category chosen for it (`parent`, a slug), in the store's category order, as picture tiles (picture, name). The heading is the category's name unless one is chosen. Nothing renders without a chosen category, for a slug the store does not have, or without a subcategory that has products, so no heading stands alone. |
| `pixonaut-shop/category-carousel` | `Blocks\CategoryCarousel` | An optional heading, then one row of round category pictures with their names, scrolling sideways: the subcategories of the chosen category, or the top-level categories without one. Its view script shows the arrows (below). Nothing renders without a category to show. |
| `pixonaut-shop/icon` | `Blocks\Icons` | One line icon from a fixed set, in `currentColor`, optionally in a circle. A name is saved in the block, so it never changes. |
| `pixonaut-shop/free-shipping-note` | `Blocks\FreeShippingNote` | How much more the cart needs for free shipping, or that shipping is free, from the free-shipping method of the zone the cart ships to; nothing without one that has a minimum. Placed only inside the mini-cart. |

The three category blocks share `Blocks\CategoryList`: the children of a
category (or the top-level ones) in the store's own order (WooCommerce's
`menu_order`), without WooCommerce's default category, and only those whose
catalogue count (the `product_count_product_cat` term meta, subcategories'
products included) is above zero, as WooCommerce's own `hide_empty` would
leave out; `get_terms()` loads their meta in one query and
`Archive\CategoryImage::prime()` their pictures in another before any
`<img>` is printed. The group tiles and the carousel also share
`Blocks\CategoryLinks` for their attributes, heading and links. Names and
headings are escaped as text, links as URLs; pictures are the category's
`thumbnail_id` at `woocommerce_thumbnail`, lazy, with an empty `alt` (the
name is beside them) and a `sizes` that matches the block's stylesheet. A
category is chosen by slug, not ID, so a page written for one store means
the same in another, and the editor lists the store's categories, each
under its parent, through the REST API's `product_cat` route.

The carousel's row is a list that scrolls by itself (`overflow-x: auto`,
`scroll-snap-type: x mandatory`), so touch, a trackpad and Tab work without
script, and the browser scrolls a focused category into view. Its previous
and next buttons are printed with `hidden`, `aria-controls` naming the row,
and an `aria-label` ("Previous categories", "Next categories"); the block's
`viewScript` (`src/blocks/category-carousel/view.ts`) shows each while the
row can scroll its way, scrolls by most of the row's width on a press
(smoothly, or at once under `prefers-reduced-motion: reduce`), moves focus
to the other button when the one pressed hides at an end, and hides the
row's scrollbar once it runs. The row's ID comes from `wp_unique_id()`.

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

### The lowest price beside a reduction

Swedish law (prisinformationslagen 7 a §, from the EU's Omnibus directive)
requires an announced price reduction to show the lowest price the product
had in the 30 days before the reduction. `inc/Product/LowestPrice.php` adds
that line, "Lowest price in the last 30 days: {price}" ("Lägsta pris senaste
30 dagarna: {price}" in Swedish), under every reduced price a shopper sees,
and only while the product is on sale:

- every price WooCommerce prints through `get_price_html()`, through
  `woocommerce_get_price_html`: the product page's price row, the
  `woocommerce/product-price` block on product cards and grids, and a
  chosen variation's price;
- a reduced line in the Cart block, the mini cart and the checkout's
  summary, as one of the line's detail rows (`woocommerce_get_item_data`),
  which those blocks print as text, with or without tax as the cart shows
  its prices (`woocommerce_tax_display_cart`);
- the campaign band, after its text, when the campaign links to a reduced
  product.

The admin's product list shows the price without it.

WooCommerce keeps no past prices, so the theme keeps them in the product's
`_pixonaut_shop_price_history` meta: a list of `{ price, from }`, oldest
first, where `price` is the active price (what a customer pays, as
WooCommerce stores it) and `from` the ISO 8601 moment it took effect. An
entry is added whenever that price changes: when WooCommerce creates or
updates a product or a variation (`woocommerce_new_product`,
`woocommerce_update_product` and their `_variation` twins, which the admin,
the REST API, WP-CLI and imports all reach), and after a scheduled sale
starts or ends (`wc_product_start_scheduled_sale`,
`wc_product_end_scheduled_sale`, and the daily run's
`wc_after_products_starting_sales` and `wc_after_products_ending_sales`). A
save that leaves the price records nothing. Entries that ended more than 30
days before the newest one are dropped; the one in effect when that window
opens stays. A duplicated product starts a history of its own. WooCommerce's
own prices are never written.

The reference price is the lowest active price in the 30 days before the
current price took effect, so a reduction that has run for weeks still looks
back from its start. A history shorter than 30 days uses the lowest price it
has. A product first seen already on sale, with no price before its sale
price (one saved on sale before the theme was active, or created on sale),
shows its regular price. A price that changed without a save the theme saw
counts as taking effect now. A variation has its own history and line; a
variable product's price range shows the lowest reference among its reduced
variations: those in the range WooCommerce shows (hidden and out-of-stock
ones left out) whose sale price is below their regular price, read from the
range's cached prices (`get_variation_prices( true )`) and primed in one
query, so no other variation is loaded. A reference price worked out once is
kept for the rest of the request, since a grid prints a price several
times; any product save forgets them. One `LowestPrice` serves the prices
and the campaign band. The price is shown as WooCommerce shows prices
(`wc_get_price_to_display()`, `wc_price()`), with tax as the shop displays
it. A price changed by writing `_price` straight to the database, without
WooCommerce's save, is not seen until the product is next saved.

A scheduled sale starts when WooCommerce's scheduled job for it runs, which
WP-Cron can start minutes after the sale's start date. Between the two,
WooCommerce already counts the product as on sale while its stored price is
still the regular one, so the line shows beside the regular price until the
job applies the sale price and the history records it.

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

Each of the theme's menus is found by name. A navigation block that names no
menu shows core's fallback, the newest menu, so every menu would show the same
links. `inc/Header/NamedMenus.php` points the block carrying
`pixonaut-shop-header__menu`, `pixonaut-shop-footer__links`,
`pixonaut-shop-footer__service` or `pixonaut-shop-footer__legal` at the
published menu saved under `pixonaut-shop-header`, `pixonaut-shop-footer`,
`pixonaut-shop-service` or `pixonaut-shop-legal`, before it renders. It looks
each name up once per request, and again after a menu is saved or deleted. A
block that already names a menu keeps it, and one whose menu is missing keeps
core's fallback.

### The mega header

With the `pixonaut_shop_header` option at `mega`, the header's row is the
menu button, the logo and title, then search, account and the mini-cart.
`inc/Header/MegaMenu.php` prints the button and its panel in place of the
header's navigation block, on `pre_render_block`, so core never renders the
block there. The panel is built on the server from the same menu the simple
header shows (the one `NamedMenus` finds, or the one picked for the block in
the Site Editor): each top link or submenu of the menu is a group. A group's
own links and submenus are its items; a submenu among them is one item, and
what it holds is left out. Items picked as product categories (a navigation
link of kind `taxonomy` and type `product_cat`) link to the category and show
its picture, the term's `thumbnail_id` at `woocommerce_thumbnail`
(`inc/Archive/CategoryImage.php`, shared with the product categories block),
lazy and with its width, height and `sizes`. A group with any category among
its items shows them all as tiles, a tile without a picture keeping an empty
square; a group with none lists its items as links. A top item with nothing
under it is a plain link among the groups, and a group whose own address is
not `#` links to it as "All of …". Labels are printed as plain text. Without
a menu, or with one holding no links, the block renders as core's navigation,
as in the simple header.

The panel's lookups are batched: the categories' terms and their meta are
loaded together (`_prime_term_caches()`, `update_termmeta_cache()`), then
their pictures' attachments (`_prime_post_caches()`), before any item reads
them. Its IDs come from `wp_unique_id()`, so `aria-controls` and
`popovertarget` stay right if the header renders twice on a page.

The menu is a `<nav>` named "Main menu". The panel is a popover
(`popover` on the panel, `popovertarget` on the button and on the drawer's
close button), so the browser opens it, closes it on Escape or a click
outside, and puts it in the top layer over a scrim (`::backdrop`), with no
script. Without the script every group's items show under their group's
button. CSS anchor positioning hangs the panel under the header's row
(`anchor-name` on the row); a browser without it gets the top the script
measures, or the window's top without the script.

`src/header/mega-menu.ts` (built as `header-mega-menu`, loaded only with the
panel) adds the rest and marks the menu `is-enhanced`. It keeps the button's
`aria-expanded` in step with the popover's `toggle` event, moves focus to the
first group on opening, keeps Tab within the open panel, and returns focus
to the button when it closes. The menu is a disclosure, not a dialog: the
panel has no dialog role and the page around it stays as it is. Each group
is a button with `aria-expanded` over its items' `role="group"`. In the
panel one group's items show at a time, switched on hover with a mouse, on
focus and on click; the arrow keys, Home and End move between the groups.
Below WordPress's 782px breakpoint the panel is a drawer from the start edge,
where a tap or Enter opens a group's items under it and closes the others',
and the page under it does not scroll; the desktop panel keeps the page's
scrollbar, so the row never moves. The stylesheet (`src/styles/_mega-menu.css`)
tells the script which of the two the panel is through
`--pixonaut-shop-mega-drawer`, so the breakpoint is written in one file. The
panel fades in only without `prefers-reduced-motion`. The simple header
prints none of this.

The Site Editor renders the header's navigation block in the browser, so it
shows the menu as the simple header does in either variant.

## Options other tools write

Two options are a contract with the tools that set up a store, which write
them; the theme only reads them, and a store's own edits never write them.
Their names and shapes stay as they are.

| Option | Shape | Read by | Effect |
| --- | --- | --- | --- |
| `pixonaut_shop_hero` | An array: `heading`, `text`, `button_label` and `button_url`, each a string, and `image_id`, an int, an image attachment's ID. | `inc/FrontPage/Hero.php` | The `pixonaut-shop/hero` pattern shows that heading, sentence, one button and picture, with no pill, in place of its placeholders. |
| `pixonaut_shop_campaign` | An array: `text` and `url`, strings, and `starts` and `ends`, ISO 8601 dates in the site's time zone (`2026-11-27` or `2026-11-27T08:00:00`). | `inc/Campaign/CampaignBand.php` | While `starts <= now < ends`, a band with the text, linked to the address, takes the announcement part's place on every page. An address that is a reduced product's adds its lowest price of the last 30 days. |
| `pixonaut_shop_header` | `simple` or `mega`; unset is `simple`. | `inc/Header/HeaderVariant.php` | Which header the store shows: `simple`, the menu in the row, or `mega`, the menu button that opens the mega menu's panel. |

The campaign band never shows without dates: a campaign missing either date,
or holding one that is not a real date, or ending before it starts, is
hidden, so one prepared ahead stays hidden until its owner sets its dates.
`CampaignBand` filters the announcement `core/template-part` block's output,
reads WordPress's clock in the site's time zone (`current_datetime()`) and
prints the same group the announcement pattern holds, with the text escaped
for HTML and the address as a URL (an address WordPress rejects leaves the
text without a link). Outside the dates, the part shows what the store wrote
in it, if anything. A page cache keeps serving a page as it rendered it, so
it can show the band after its end until the cache is purged or expires.

The hero is read where the pattern is: `patterns/hero.php` prints the
option's hero as the same blocks its placeholders are (a core/media-text on
a Band (tint 1) group, locked to its content), so a page holding a reference
to the pattern shows it on the site, and the editor inserts it as blocks the
store edits like any other. A page the store has saved from the editor holds
its own blocks, and a later change to the option leaves it as it is. The
picture is printed as core/media-text saves it, with its `wp-image-<id>`
class and the attachment's alt text, so the editor finds the block valid;
WordPress adds its sizes, `srcset` and lazy loading as the page renders. The
text is escaped for HTML and the button's address as a URL. An option that
is not an array with all five keys of these types, whose heading or button
label is empty or only spaces, whose button address is one WordPress will
not link to (empty, or `javascript:`), or whose `image_id` is not an image
in the media library, is not a hero: the pattern keeps its
placeholders, as it does while the option is unset.

## The update checker

`inc/Updates/` follows Pixonaut for WooCommerce's `src/Updates/`:

| Class | Role |
| --- | --- |
| `Updates` | Reads the optional `PIXONAUT_SHOP_UPDATE_TOKEN` from `wp-config.php`; without the library, does nothing. |
| `GitHubUpdater` | Builds plugin-update-checker's `ThemeUpdateChecker` for the repository in style.css's `Update URI`: releases only, stable `vX.Y.Z` only, only with `pixonaut-shop-X.Y.Z.zip` attached, a token, when set, as an `Authorization` header, its state in the `pixonaut_shop_update_state` option, a check every 12 hours. |
| `GitHubReleases` | The library's GitHub client, with the release list sorted by version rather than publication date. |

The checker reads the offered version from style.css at the release's tag
(`docs/DEPLOY.md`).
