# Site Editor

Rules for `templates/`, `parts/`, `patterns/`, `theme.json` and `styles/`,
and where a store's own design goes.

## A store's look goes in its Styles

Every store shares these files; a store's own colours, fonts, logo, copy and
images never go into them. Setting up a
store is Site Editor work:

- **Appearance → Editor → Styles** — start from the default look (Inter,
  white and greys, one blue) or the Nature variation, then change colours,
  typography and spacing. WordPress saves them for this site and this theme
  slug, and a theme update keeps them.
- **Typography → Fonts** (the Font Library) — install the store's own
  typefaces; they are served from the store. The theme ships only its
  default, Inter.
  Nature is drawn in Figtree but ships no file: install Figtree from the
  Font Library's Google Fonts collection, or the variation falls back to the
  system font. Inter and Figtree each keep their room while they load, through
  a fallback face drawn to their measures;
  a store's own typeface falls back to the system font unless its Styles add
  one the same way.
- **Site Identity / the Site Logo block** — the logo, site title, tagline
  and site icon.
- **Navigation** — the header's and footer's menus. A menu saved under the
  name `pixonaut-shop-header` fills the header, ones saved as
  `pixonaut-shop-footer` and `pixonaut-shop-service` the footer's two menus
  side by side, and one saved as `pixonaut-shop-legal` the legal links after
  the copyright line. Pick a menu for a block in the Site Editor to show
  another one there. The two footer menus side by side are headed by the
  menu's name as it is set under Navigation. Until a block names a menu and
  no menu is saved under its name, core shows its fallback menu there,
  without a heading.
  The Site Editor's preview shows core's fallback menu in a block until a
  menu is picked for it there, while the live site shows the named menu.
- **The announcement part** (Patterns → Template parts → Announcement) —
  empty, so no bar shows. Insert the Announcement pattern into it and write
  the text; empty it again to remove the bar.
- **Setting up the front page** — under **Pages → Add New Page**, pick the
  **Front page** pattern the editor offers, write over its placeholders
  (the hero's pill, heading, sentence and buttons, the sections' headings
  and links), choose the hero's picture and publish. Then set that page as
  the homepage in **Settings → Reading → Your homepage displays → A static
  page**. The front page template shows the page's content full width, with
  no title, above the store's promises. Each section is locked to its
  content, so the text, pictures and links change but the layout stays;
  with **Your latest posts** as the homepage, the posts list shows instead.
- **The Cart page** — WooCommerce's own Cart page works as it is: the theme
  styles its lines, totals and empty cart. For the designed empty cart, with
  WooCommerce's message in a panel and the popular products below it, open
  the Cart page, select all its content, delete it, and insert the **Cart**
  pattern (Patterns → WooCommerce) in its place. A made-to-order notice or
  any other text under the cart is the store's own: add a group with the
  Notice style to the page.
- **Pages** — the about and terms pages are the store's own content. A
  page's excerpt (the Excerpt panel in the page's settings) shows as the
  lede under its title; a page without one shows no lede.
- **The Checkout page** — WooCommerce's own Checkout page works as it is:
  the theme draws each step in a panel and the summary beside it. For the
  designed checkout, select the Checkout block on the page and turn on
  **Show form step numbers** in its settings, so each step's number shows in
  its badge; or select all the page's content, delete it, and insert the
  **Checkout** pattern (Patterns → WooCommerce), which turns the numbers on,
  makes the terms a box the customer ticks and adds a "Secure payment" line
  under the place-order button (remove that paragraph if the store does not
  want it). A made-to-order sentence is the store's own: write it in the
  Terms and Conditions block's text, or add a paragraph after the terms in
  the Checkout block.

To change what every store gets, change `theme.json` or the templates here;
to change one store, change its Styles.

## The shop's grid and the product page's frame

The theme ships defaults for how products are shown and no PHP that fixes
them; a store changes each where WordPress and WooCommerce keep it, without
a child theme. A developer changes the shapes offered through the
`pixonaut_shop_aspect_ratios` filter, and the products per page through
WooCommerce's own `loop_shop_per_page`.

- **Columns** — Appearance → Editor → Templates → Product Catalog, select
  the product grid (the Product Collection block), Settings → Columns. The
  category and tag archives use the same template unless the store adds
  their own; the Product Search Results template has its own grid.
- **Products per page** — Appearance → Customize → WooCommerce → Product
  Catalog: Products per row times Rows per page. The grid follows the
  archive's own query, and WooCommerce offers no products-per-page control
  for such a grid in the Site Editor. The theme starts the setting at 4 × 3
  when it is activated; set Products per row to the grid's columns so every
  page ends on a full row. A store that had the theme before it stopped
  fixing twelve a page shows what that setting holds (WooCommerce's own
  default is 4 × 4) until it is set.
- **Pagination** — the Pagination block under the grid, in the same
  template: its previous and next links and page numbers.
- **A card's shape** — in the same template, select the Product Image block
  inside the grid's product template, Styles → Dimensions → Aspect ratio.
  The list holds the print shapes: Square - 1:1, Portrait - 4:5, 5:7, 3:4
  and 2:3, and their landscape turns. The theme's cards start with none,
  which shows each product image whole in its own shape, so a 5:7 product
  is neither cropped nor banded; a grid of differently shaped photos then
  has cards of different heights, and picking one shape evens them.
- **A card's scale** — Settings → Image size → Scale on the same block:
  Cover fills the shape and crops what is outside it, Contain shows the
  whole picture with bands. WooCommerce shows Scale only once a Height is
  set there: set one, pick the scale, then clear the height again; the scale
  stays. Height and Width there set the picture's size.
- **Cards elsewhere** — the popular products (in the Product Catalog and
  Product Search Results templates, and on the Cart page made from the Cart
  pattern), the related products (Single Product template) and the front
  page's best sellers (the store's own front page) each have their own
  Product Image block, set the same way.
- **Thumbnail cropping** — Appearance → Customize → WooCommerce → Product
  Images → Thumbnail cropping. A Product Image block set to WooCommerce's
  cropped thumbnail (a card inserted from an older WooCommerce pattern)
  takes its shape from this setting, square unless it is changed; the
  theme's cards do not, and follow their own Aspect ratio.
- **The preview frame** — Appearance → Editor → Templates → Single
  Product, select the Product Image Gallery block, Styles: one style per
  shape, which the editor's placeholder gallery takes too. Default keeps
  the shape Pixonaut for WooCommerce opens the first side's preview at, the
  design's or its first photo's, for every side; a shape picked here holds every
  product's preview at it, and the gallery's picture too on a product
  without the designer, so a shop that sells only 5:7 signs picks
  Portrait - 5:7. For some products only, add a template for them (Add
  New Template → Single item: Product, or Products by Category) and pick
  the shape there.

A template a store has saved in the Site Editor keeps the cards and grid it
was saved with; the Site Editor's Reset brings the theme's back.

## Markup rules

- **Refer to presets by slug.** `"backgroundColor":"surface"`,
  `"fontSize":"large"`, `var:preset|spacing|60` — never a hex value, a pixel
  font size or a font name. A variation changes what the slug means.
- **No colour, image or address in a template, part or pattern.**
  `TemplatesTest` fails on a hex colour, an `<img>` or an `http(s)://`
  address.
- **No HTML comments between block markers.** The block parser can mistake
  one for a delimiter. Explain a pattern with a PHP comment in its header.
- **`parts/*.html` hold block markers only.** A part with a translated
  string is one `<!-- wp:pattern … /-->` reference and its markup lives in
  `patterns/*.php` (the footer).
- **The header part holds its blocks itself**, not through a pattern.
  WooCommerce inserts its account and mini-cart blocks after the navigation
  of any header part whose own content lacks them; behind a pattern
  reference it cannot see the header's own, and the header shows them twice.
  `tests/Integration/HeaderTest.php` checks each renders once.
- **No string in an `.html` file.** Text there cannot be translated; put it
  in a pattern (`docs/I18N.md`).
- **One `<main>` per template**, on the static group that wraps the content.
- **Keep the product page's insertion points.**
  `templates/single-product.html` keeps `woocommerce/product-image-gallery`
  and `woocommerce/add-to-cart-form`, and has no
  `woocommerce/product-price` block: the price prints in the form's last
  row (`inc/Product/PriceRow.php`).

## Building blocks

- **Panels and bands are block styles**, not classes: a core/group with the
  Panel style is a white panel (`base`, `radius-large`, the `card` shadow);
  Band (tint 1) and Band (tint 2) colour a full-width group (`align: full`)
  `tint-1` or `tint-2`. Notice and Pill (on a paragraph) are for a store's
  own content. `inc/Blocks/BlockStyles.php` holds their names and values;
  what theme.json cannot say is in `src/styles/_components.css`. A style's
  name is saved in every block that uses it, so it never changes.
- **The product grid is the `pixonaut-shop/product-grid` pattern**: a
  product collection that inherits the template's query, with cards and no
  add-to-cart button. A template places it with
  `<!-- wp:pattern {"slug":"pixonaut-shop/product-grid"} /-->`. Without a
  product to show it shows `pixonaut-shop/no-products`: a panel with the
  product search and a button back to the shop.
- **The popular products are the `pixonaut-shop/popular-products` pattern**:
  a heading, a "View all" link to the shop and the four best sellers in
  stock, with
  the grid's cards (`TemplatesTest` keeps the two the same). Never put it,
  or any product collection, inside another collection's no-results block:
  WooCommerce keeps one render state for every collection on a page, and the
  inner one makes the outer render nothing even when it has products. The
  shop template places it beside the grid, in a group with the
  `pixonaut-shop-archive-popular` class, which `Archive\ShownWhenEmpty`
  shows only on an archive without products; the product search template
  does the same.
- **A search without results says so in its panel**: the
  `pixonaut-shop/search-no-results` pattern, below the search field, names
  the query in a notice and offers the store's categories as buttons.
  `Archive\ShownWhenEmpty` renders it only when the search found nothing,
  and `Archive\NoResultsTitle` prints its title as it renders, so a search
  template saved in the Site Editor still names each visitor's query. The heading above the field
  is core's query title, which prints "Search results" in the store's
  language.
- **The `promises` part ships empty** for each store to fill in the Site
  Editor with its own promises; an empty part renders nothing. Templates
  place it inside a Band (tint 1) group with the
  `pixonaut-shop-promises-band` class, which hides the band while the part
  is empty. To fill it, open **Patterns →
  Promises** in the Site Editor, insert the **Promises** pattern
  (`pixonaut-shop/promises`: four columns, each an icon in a circle, a
  heading and a sentence), and replace the placeholders and icons with the
  store's own.
- **The store's categories are the Product categories block**
  (`pixonaut-shop/product-categories`), never `woocommerce/featured-category`,
  which needs one category chosen per block. It shows whichever top-level
  categories the store has with products, as tiles or buttons, and renders
  nothing without any. `<!-- wp:pixonaut-shop/product-categories /-->`
  places four tiles; `{"layout":"buttons"}` six buttons.
- **The front page is the store's own page**, started from the
  `pixonaut-shop/front-page` pattern (`Post Types: page`, `Block Types:
  core/post-content`, so the editor offers it for a new page): the
  `pixonaut-shop/hero` pattern, then a section of the store's categories and
  one of its eight best sellers, each a group with `templateLock:
  contentOnly` and a `pixonaut-shop-section-head` row (heading, link on the
  right). The hero is a core/media-text on a Band (tint 1) group, its
  picture left empty for the store, stacked text first on phones. Once a
  store has made its page, changing the patterns changes no store's front
  page: the content is the store's.
- **Icons are the Icon block** (`pixonaut-shop/icon`), one of a fixed set of
  line icons drawn in the text's colour; `{"circle":true}` puts it on a
  `tint-1` circle, or on `base` inside a band.
- **The cart page** (`page-cart`) renders the store's Cart page under a
  title row (`pixonaut-shop/cart-title`: the page's title and "Continue
  shopping"). The Cart block's look is in `src/styles/_cart.css`; a line's
  detail rows, Pixonaut for WooCommerce's personalisation among them, are
  found by position, never by the class WooCommerce makes from a row's
  label.
- **The checkout page** (`page-checkout`) renders the store's Checkout page
  under a title row (`pixonaut-shop/checkout-title`: the page's title and
  "Return to Cart"). The Checkout block's look is in
  `src/styles/_checkout.css`. Its two columns follow WooCommerce's own size
  (`is-large` on its layout); below that WooCommerce shows the summary
  collapsed above the form and prints the whole summary in the place-order
  area, which the theme moves below the form. Never hide WooCommerce's
  empty first child of that layout: it measures the block's width, and
  hiding it makes the layout switch sizes without end.
- **The order received page** (`order-confirmation`) is WooCommerce's
  order-confirmation blocks in the design's band, panel and action row; their
  words are WooCommerce's, and a store replaces the thank-you text in the
  status block. The pill above the heading is an empty Pill paragraph marked
  `pixonaut-shop-order-number`, which the theme fills with the order's number
  when the page renders (`inc/Orders/OrderReceivedBand.php`); it stays empty
  in the Site Editor, and must stay in the same group as WooCommerce's
  summary. For a failed, cancelled or refunded order the band shows neither
  the check badge nor the pill.
- **The `order-next-steps` part ships empty** for each store to say what
  happens after an order, in its own words. The
  `pixonaut-shop/order-next-steps` pattern (a heading and three numbered
  steps with placeholders) is the starting point to insert into it.
- **The `order-help` part ships empty** for each store to say how a
  customer asks about an order; My account prints it under an order's
  details (`inc/Account/ViewOrder.php`), and leaves it out while it is
  empty. The `pixonaut-shop/order-help` pattern (an outline button and a
  line beside it, with placeholders) is the starting point; the store links
  the button to its own contact page.

## Style variations

`styles/*.json` are theme.json files with a `title`. A variation may change
the values of the theme's presets and the styles that use them; each preset
list it sets — colours, font families, font sizes, spacing, shadows — holds
exactly the theme's slugs (`StyleVariationsTest`), and it ships no font file.
Adding a preset means adding it to `theme.json` and to every variation that
sets that list.

`styles/natur.json` ("Nature", Swedish "Natur") is the only variation. A look
that belongs to one store is never shipped here: it lives in that store's
saved Styles.

## Two silent-staleness traps

### The pattern registry caches by theme version

WordPress caches the list of a theme's patterns and templates. A new or
renamed file in `patterns/` or `templates/` has no effect on a running site:
a `wp:pattern` reference to it renders as nothing, with no error. Switch away
from the theme and back:

```bash
pnpm env:cli theme activate twentytwentyfive
pnpm env:activate
```

or bump style.css's `Version`, which changes the cache key. Before debugging
the markup, check the slug is in
`WP_Block_Patterns_Registry::get_instance()->get_all_registered()`.

### Database-stored templates shadow file templates

A template or part customised in the Site Editor is saved as a
`wp_template`/`wp_template_part` post and wins over the file. That is how a
store keeps its own changes, but during development an edit to a file can
seem to do nothing. Find and remove the shadowing copy:

```bash
pnpm env:cli post list --post_type=wp_template,wp_template_part
pnpm env:cli post delete <id> --force
```

On a store, the same applies to its owner's customisations: a theme update
changes a template only where the store has not customised it. The Site
Editor's "Reset" brings the theme's version back.
