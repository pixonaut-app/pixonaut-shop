# Testing

## Red-green-refactor is the default

Write the failing test first, make it pass with the smallest change that does
so, then refactor with the test as a safety net. A bug fix starts with a test
that reproduces it.

## The three tiers

| Tier | Command | What it covers |
| --- | --- | --- |
| Unit | `pnpm test:unit` | Pure logic and the theme's files: modules with Brain Monkey in place of WordPress, and the templates, patterns, `theme.json`, style variations, style.css header and Swedish catalogue read as files. No WordPress, no network. |
| Integration | `pnpm test:integration` | Real WordPress, WooCommerce and MySQL inside wp-env's `tests-cli` container: the theme active under its fixed slug, its templates, parts, patterns and variations found, Swedish translations loaded, the theme's callbacks on the hooks the fake plugin fires, and the update checker against a fake GitHub. |
| End-to-end | `pnpm test:e2e` | A real browser (Playwright) against wp-env's tests site: every template opens in the Site Editor without invalid blocks, the header's menu collapsing at its breakpoint, the 404 page and its categories, and the product page leaving room for Pixonaut for WooCommerce's designer. |
| TypeScript | `pnpm test:ts` | The tooling's own logic under Node's test runner, which strips the types itself: CI's classifier (`scripts/ci/affected.ts`), the catalogue comparison (`scripts/ci/pot.ts`) and the release version (`scripts/release/version.ts`). `pnpm typecheck` checks these, the e2e specs and `playwright.config.ts`. |

`pnpm test` runs unit and integration together; e2e is separate because it
needs a browser and a built theme. Install the browser once with
`pnpm test:e2e:install`.

### Unit — the TDD loop

Runs in a `php:8.5-cli` container with no WordPress, in well under a second:
the loop for every red-green-refactor cycle. Everything that can be pure
logic belongs here rather than in integration.

Tests mirror `inc/`: `tests/Unit/Updates/GitHubUpdaterTest.php` tests
`inc/Updates/GitHubUpdater.php`, and carries `#[CoversClass(...)]`. Tests of
the theme's files rather than a class live in `tests/Unit/Theme/` and
`tests/Unit/I18n/` and carry `#[CoversNothing]`:

- **`TemplatesTest`** — every template and part a store renders exists; the
  single-product template keeps the gallery and add-to-cart form blocks
  Pixonaut for WooCommerce prints into, puts the product details
  in a panel below them, and has no standalone price block; no template, part or
  pattern holds an HTML comment that is not a block delimiter, a hex colour,
  an image or an address; the `promises`, `announcement`,
  `order-next-steps` and `order-help` parts ship empty; the order received
  page keeps WooCommerce's order-confirmation blocks and the pill's marker;
  the mini-cart part holds the drawer's filled and empty views in the
  design's order, with the free-shipping note in the footer; and every
  pattern translates in the theme's text domain.
- **`ThemeJsonTest`** — every preset slug and neutral value, and the
  primary and outline buttons.
- **`StyleVariationsTest`** — each variation has a title and the theme's
  schema, and sets exactly the theme's colour and font slugs or none.
- **`StylesheetHeaderTest`** — style.css names the fixed text domain and the
  repository updates come from, and its version is a release version that
  package.json repeats.
- **`SwedishCatalogueTest`** — every string in `languages/sv_SE.po` is
  translated, and none is fuzzy.

Every unit test extends `tests/TestCase.php`, which sets Brain Monkey up and
tears it down around each test. Don't copy that pair into a test.

### Integration — the WordPress boundary

Runs inside wp-env's tests site. Use it only for what needs a live
WordPress: real hooks, real template resolution, real translation loading.

**No request leaves the suite.** `tests/Doubles/NoNetwork.php` answers every
outbound HTTP request that no test double answered with an error, so nothing
reaches GitHub, WordPress.org or anything WooCommerce calls home to.
`tests/Doubles/FakeGitHub.php` answers the update checker's calls for this
repository from WordPress's own `pre_http_request` filter — the release list
and style.css at a tag — and records each request, so `UpdatesTest` checks
which release a store is offered and that the token travels in a header,
with a dummy token.

### End-to-end — what a visitor or a store owner sees

Runs against wp-env's **tests site**, at `WP_ENV_TESTS_PORT`, never the
development site, which runs whatever build of the real plugin a developer
chose.
The global setup signs in
as wp-env's admin and runs `tests/Support/ShopFixtures.php` through WP-CLI:
WooCommerce out of coming-soon mode and its setup wizard, its block hooks on,
registration offered on My account, cheque payments on and shipping off
(so a checkout needs a billing address only), the product
`fake-pixonaut-product`, and a customer with an order, whom
`account.spec.ts` signs in as. The one case that turns registration off
(`ShopFixtures::offer_registration()`) turns it back on, and
`account.spec.ts` runs its cases in order so no other case sees it off.

**No Pixonaut, real or plugin.** The tests site maps in
`tests/e2e/fake-pixonaut.php`, which prints Pixonaut for WooCommerce's
documented markup, and fires its documented hooks with the arguments the
plugin documents, for that one product and the cart and order lines made
from it (marked, as the plugin marks them, when it is added to the cart and
checked out):

- on the product page, its preview in place of the product gallery block,
  and its form inside the add-to-cart form, with the approval box
  (`.pixonaut-approval`, `.pixonaut-approval__box`);
- in the cart, the checkout and the mini cart, a personalised line's rows
  (the side's proof link, then the design's field rows, the edit link on the
  last of them) through `woocommerce_get_item_data`, filtered through
  `pixonaut_wc_cart_item_display`;
- on order lines, the design's field rows (`pixonaut_field_<side>_<n>`, one
  per stored field, a picture's value shown as "Image") and the line's state
  row (`pixonaut_line_state`, labelled as the plugin labels each state)
  through `woocommerce_order_item_get_formatted_meta_data`, filtered through
  `pixonaut_wc_fulfilment_line_display`.

`product-page.spec.ts` checks the preview and the form appear, so a template
change that drops either insertion point fails without the plugin, a
Pixonaut account or a network. The integration suite runs on the same site,
so `PixonautHooksTest` sees the fake fire the hooks the theme's
`inc/Pixonaut/` callbacks are on.
`order-received.spec.ts` places an order as a guest and checks the order
received page's band, overview and the line's details, and
`OrderReceivedTest` renders that template for an order with a personalised
line. `ShopFixtures::personalised_order()` gives the customer an order with
a personalised line per state, as the fake keeps them;
`AccountOrdersTest` renders My account's orders and an order's view with it
and with a plain order, and `account.spec.ts` checks the orders page at both
widths and an order's view. When the plugin changes what it
prints or a hook it fires, change the fake in the same change.

`mini-cart.spec.ts` adds the personalised product to the cart as a guest and
opens the drawer from the header's bag: its line, with the fake's rows, the
subtotal and the two buttons; and, for a guest with an empty cart, the empty
state.

`lowest-price.spec.ts` makes a product reduced from its regular price, with
a price history in which it cost less before the reduction
(`ShopFixtures::reduced_product()`), and checks the lowest price of the 30
days before it under the reduced price on the product page and on its card,
and among its cart line's details; it removes the product afterwards.

`mega-menu.spec.ts` checks the mega header: the menu button opens the panel
at its first group, a group shows its categories as tiles on hover and on
focus, the arrow keys move between the groups, Tab stays in the open panel,
Escape and a click outside close it and focus returns to the button,
opening it moves nothing in the header's row, the menu is a navigation
landmark, and on a phone the drawer opens a group under its button on a tap
or Enter. With JavaScript off, the button still opens the panel, which lists
every group's items, a link in it leads on, and Escape closes it. Its menu is `ShopFixtures::mega_menu()`, saved under a
name of its own. The header variant and the header's menu are each one for
the whole site, so `tests/e2e/mega-menu.php`, mapped into the tests site's
mu-plugins, shows the mega header with that menu only to a visitor carrying
the `pixonaut_shop_tests_mega_menu` cookie, and the spec runs beside the
specs of the simple header. `MegaMenuTest` renders the header with each
variant.

`blocks.spec.ts` inserts the theme's own blocks in a page and checks the
product categories block shows a category made for the spec in the canvas,
and the promises pattern its icons, headings and sentences, without invalid
blocks.

`site-editor.spec.ts` opens every template in the Site Editor and fails on
any block validation error. Two things keep it honest, and both must stay:
`canvas: 'edit'` (without it the Site Editor opens on the templates list and
never parses the markup), and assertions through
`page.frameLocator('iframe[name="editor-canvas"]')` (the canvas is an
iframe, invisible to `page.getByText`). It also fails on the parser's
console message, which is logged for failures the canvas does not show.

The suite finds the theme's interface by its English source strings; the
tests site is in English.

## What CI runs

A pull request runs lint and the unit tests (`pull-request.yml`), with read-only
access and no secrets. The whole suite does not run on pull requests or pushes.
It runs on `main`:

- **By hand, once a batch of merges is in**, and before every release:
  `gh workflow run ci.yml --ref main`; `gh run list --workflow ci.yml --branch main`
  shows the result.
- **Nightly** at 03:00 UTC. A night on a commit that already passed a full
  run runs nothing and says so in its summary.

The **`CI` job** always runs and fails when any job that ran failed or was
cancelled.

Before a merge, a change runs locally the suites it reaches:
`pnpm ci:affected:local` diffs the checkout against `origin/main` (or a base
you pass) and [`scripts/ci/affected.ts`](../scripts/ci/affected.ts) decides
which jobs and specs that needs and lists the commands.

- **A path no rule knows runs everything**, as do the workflows, lockfiles,
  tooling configs, `.wp-env.json` and the e2e global setup. Markdown and
  `docs/` run nothing.
- **`E2E_OWNERS` names the specs each `inc/` area can break**, or `'all'`. A
  spec that no area owns belongs in `SITE_WIDE_SPECS`. Adding an `inc/`
  directory or a spec fails `tests/ts/ci-affected.test.ts` until it has an
  entry.
- **Templates, parts, patterns and translations run `PAGE_SPECS`; styles run
  `LOOK_SPECS`.**
- A suite under ten specs runs in one e2e job; a larger selection of more
  than half the suite runs over two.
