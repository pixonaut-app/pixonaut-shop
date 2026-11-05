# Pixonaut Shop

A WooCommerce block theme for niche stores that sell personalised garments
and prints through Pixonaut for WooCommerce. Every store shares the theme's
templates — the product page, the shop, the cart, the checkout, pages, the
404, the header and the footer. A store's colours, fonts and logo live in its
own Site Editor → Styles, and its copy and images in its own content, so one
theme update reaches every store without touching its look.

## Stack

WordPress 6.7 or later (block theme, no classic PHP templates), WooCommerce,
PHP 8.2 or later on a store; development on WordPress 7.1 and PHP 8.5 in
wp-env. PSR-12 layout via php-cs-fixer with PHPCS restricted to semantics,
PHPUnit 13 with Brain Monkey for unit tests, wp-env for a real WordPress and
WooCommerce in integration tests, Playwright for end-to-end. TypeScript and
Biome for the tooling, `@wordpress/scripts` for the build. Node 24, pnpm. No
PHP or Composer on the host — everything PHP runs in a container. Updates come
from this repository's GitHub releases through plugin-update-checker.

## Quick start

```bash
pnpm install
pnpm composer install
pnpm build
pnpm env:start
pnpm env:activate
```

See [`docs/SETUP.md`](docs/SETUP.md) for prerequisites, ports, adding Pixonaut
for WooCommerce locally, and why wp-env's containers outlive the command that
starts them.

## Documentation

| Doc | Covers |
| --- | --- |
| [`docs/SETUP.md`](docs/SETUP.md) | Prerequisites, first run, ports, the container commands behind the pnpm wrappers, choosing the build of Pixonaut for WooCommerce the development site runs. |
| [`docs/TESTING.md`](docs/TESTING.md) | The three test tiers, the TDD loop, the fakes, and what CI runs. |
| [`docs/I18N.md`](docs/I18N.md) | Swedish and English: the catalogue workflow and what is not translated. |
| [`docs/CONVENTIONS.md`](docs/CONVENTIONS.md) | Code style, naming, the php-cs-fixer/PHPCS split, commits, admin copy. |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | Directory layout, `Bootstrap`, modules, templates and the update checker. |
| [`docs/SITE-EDITOR.md`](docs/SITE-EDITOR.md) | Markup rules, where a store's look lives, and two silent-staleness traps. |
| [`docs/DEPLOY.md`](docs/DEPLOY.md) | Versions, releases, the zip, and turning on updates on a store. |
| [`CHANGELOG.md`](CHANGELOG.md) | What each release changed. |

## Licence

GPL-2.0-or-later: see [`LICENSE`](LICENSE). Inter keeps its own licence:
`assets/fonts/inter/OFL.txt`.
