# Conventions

This document covers only tooling that is actually installed and wired up.
If something here stops being true, fix this document in the same change,
not later.

## PHP style: PSR-12, not WordPress style

The theme's PHP layout under `inc/`, `tests/` and `functions.php` follows
**PSR-12** — 4-space indentation, no space inside parentheses, opening brace
on its own line — not the WordPress coding standard's layout.
`php-cs-fixer` (`.php-cs-fixer.dist.php`) enforces `@PSR12` and
`@PSR12:risky` and owns every layout decision there.

PHPCS (`phpcs.xml.dist`) still runs, restricted to what php-cs-fixer doesn't
do: security and correctness sniffs (escaping, nonces, capabilities), i18n
(the `WordPress.WP.I18n` text-domain check, which accepts `pixonaut-shop`
only), global prefixes (`pixonaut_shop`, `Pixonaut\Shop`) and naming. To make
the split exact, `phpcs.xml.dist` excludes WordPress-Extra's layout sniff
groups. Without that, the two tools fight: each one's autofixer undoes the
other's. If a sniff starts flagging layout again, add it to the exclusion
list; don't change the code's formatting.

`patterns/` is PHP that echoes into block markup, written in WordPress's own
style, and checked by PHPCS only. Escape at the call site with a WordPress
escaper: `esc_html__()`, `esc_url()`.

```bash
pnpm fmt:php        # php-cs-fixer, applies layout fixes
pnpm fmt:php:check  # php-cs-fixer, dry-run — what CI runs
pnpm lint:php       # phpcs, semantics only
pnpm lint:php:fix   # phpcbf, autofixes what it can
pnpm stan           # PHPStan at level max, for PHP 8.2
```

PHPStan analyses for `phpVersion: 80200`, the oldest PHP a store may run
(style.css's `Requires PHP`), although the tools themselves run on PHP 8.5.

## Naming

Methods are **snake_case**, boolean getters included (`is_active()`,
`configured_token()`), enforced by WPCS on OOP methods: the theme calls and is
called by WordPress's snake_case API, and mixing casings at the same call
sites reads worse. Class names are PascalCase. The namespace is
`Pixonaut\Shop`, mapped to `inc/`.

## `final class`, one `register()`

Every module is a `final class` with a single public `register(): void` that
holds every one of its `add_action`/`add_filter` calls, and is constructed
from `Bootstrap`. No file outside a `register()` method attaches a hook, so
the theme's whole hook surface is greppable
(`grep -rn "add_action\|add_filter" inc/`). The update checker's own hooks
are the library's.

## Hooks use array callables, not first-class callables

Register a hook as `add_action( 'init', [ $this, 'method' ] )`, never as
`add_action( 'init', $this->method(...) )`. Brain Monkey records a
first-class callable as the literal `"function ()"`, so a unit test cannot
tell one from another; array callables serialise as `"Class->method()"`, which
`has_action()` in a unit test and the integration suite's hook inspection both
rely on.

## No magic literals

Every non-obvious numeric or string literal gets a named constant whose name
states what the value *is*, with a comment where the name alone can't carry
the reasoning — `GitHubUpdater::CHECK_PERIOD_HOURS`,
`WooCommerceNotice::REQUIRED_CAPABILITY`. A value obvious in context needs no
ceremony. When the same literal is needed in more than one place, share one
constant rather than naming it twice.

`theme.json` can't carry comments, so its deliberate literals are recorded
here: the default palette's hex values, Inter and its system fallback stack,
the `card` shadow and the `custom.radius` steps are the theme's neutral
starting point, overridden by a store's Styles; the
button's `border.radius` is a component value, the small radius repeated
because Styles edits it as a length, and its 2px border, like the outline
button's, is the design's; the font faces' `unicodeRange` values
are the latin and latin-ext subsets the files were cut to; the spacing
scale's slugs (`20`–`80`) are what templates refer to, so a step's size may
change but its slug may not. `src/styles/_fonts.css`'s percentages are the
measures of the typeface each fallback face stands in for.

## Nothing store-specific

Templates,
parts and patterns refer to presets by slug and hold no colour, image or
address. The stylesheet (`src/app.css` and its partials in `src/styles/`,
one per area) uses only presets' and `settings.custom` values' custom
properties, never a literal colour, font or radius, but for the
fallback faces in `_fonts.css`.

## Commits: Conventional Commits

`type(scope): subject` — `feat`, `fix`, `docs`, `refactor`, `chore`, `test`,
`build`, `ci`, `perf`, `style`. Releases and the changelog are made from these
(`docs/DEPLOY.md`), and `!` after the type marks a breaking change.

## Admin copy: describe behaviour, not mechanism

Any text in the WordPress admin — notices, labels, help — says what will
happen, never how it's implemented: no hook names, option keys, constants or
format jargon. "Product pages, the shop, the cart and the checkout need
WooCommerce" is copy; "class WooCommerce not found" is a bug report.
