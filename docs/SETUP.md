# Setup

## Prerequisites

- **Docker.** There is deliberately no PHP or Composer on the host — every
  PHP command runs inside a container. See
  [the container commands](#the-container-commands-behind-the-pnpm-wrappers).
- **Node 26** (`.nvmrc` pins it; `package.json`'s `engines` enforces it, and its
  `devEngines.runtime` has pnpm download Node 26 for its scripts when `node` is older).
- **pnpm**, pinned through `packageManager` in `package.json`
  (`corepack enable` picks it up).

## First run

```bash
pnpm install           # JS dependencies, and the lefthook pre-commit hook
pnpm composer install  # PHP dependencies, through the composer:2 container
pnpm build             # the theme's stylesheet, into build/
pnpm env:start         # WordPress, WooCommerce and MySQL, through wp-env
pnpm env:activate      # this theme on the development site
pnpm env:activate-tests  # and on the tests site, for the integration suite
```

`pnpm composer install` installs the runtime dependency (plugin-update-checker)
and everything under `require-dev` (PHPUnit, PHPStan, PHPCS, php-cs-fixer,
Brain Monkey), so it is enough for the whole quality gate.

wp-env installs WooCommerce from wordpress.org on both sites. A fresh
WooCommerce store starts in "coming soon" mode and sends the first admin
page view to its setup wizard; the end-to-end suite switches both off for
itself (`tests/Support/ShopFixtures.php`), and you can do the same by hand:

```bash
pnpm env:cli option update woocommerce_coming_soon no
```

The development site answers at `http://localhost:<WP_ENV_PORT>` (see
[Ports](#ports)), with wp-env's `admin` / `password`, and the tests site at
`WP_ENV_TESTS_PORT`.

## The slug is `pixonaut-shop` in every checkout

`.wp-env.json` maps the checkout to `wp-content/themes/pixonaut-shop` rather
than listing it under `themes`, which would name the theme after the
checkout's directory. A worktree's site therefore uses the same slug as the
main checkout's and as every store, and so does everything that names it:
`pnpm env:activate`, the integration suite's working directory, the Site
Editor's template ids (`pixonaut-shop//single-product`).

## Ports

wp-env reads its ports from `WP_ENV_PORT` and `WP_ENV_TESTS_PORT`, falling
back to `.wp-env.json`'s `port` and `testsPort` (8851 and 8852). Set them to run
several checkouts at once. `playwright.config.ts` reads `WP_ENV_TESTS_PORT` for
the same reason; do the same in anything you write.

`WP_ENV_HOME=./.wp-env-home`, which every `env:*` script sets, keeps each
checkout's WordPress, database and containers its own.

## wp-env's containers outlive the command

`pnpm env:start` returns once the site is up, and its containers keep running
— and keep this checkout's ports — after the terminal closes. The next
checkout to start then finds its ports taken. Release them when you're done:

```bash
pnpm env:stop
```

## The container commands behind the pnpm wrappers

Every `pnpm` script that needs PHP resolves to one of these. They're here for
when a wrapper itself needs debugging; day to day, use the `pnpm` scripts.

**Composer and the PHP tools** (`pnpm composer`, `pnpm php`, `pnpm fmt:php`,
`pnpm lint:php`, `pnpm stan`, `pnpm test:unit`) run in a throwaway container
that mounts the checkout:

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(scripts/docker/host-user.sh)" \
  -e COMPOSER_HOME=/tmp/composer composer:2 <command>

docker run --rm -v "$PWD":/app -w /app -u "$(scripts/docker/host-user.sh)" \
  php:8.5-cli php <command>
```

**WP-CLI without a site** (`pnpm wp-cli`, which `pnpm i18n:pot` uses) runs
the `wordpress:cli` image the same way. `make-pot` needs no WordPress
install, so the catalogue can be regenerated without wp-env running.

**The integration suite** (`pnpm test:integration`) runs inside wp-env's
`tests-cli` container, which has PHP, a real WordPress with WooCommerce and a
real MySQL:

```bash
wp-env run tests-cli --env-cwd=wp-content/themes/pixonaut-shop \
  vendor/bin/phpunit -c phpunit-integration.xml.dist
```

Arguments after `pnpm test:integration` (`--filter UpdatesTest`) reach
PHPUnit.

### Rootless Docker

`scripts/docker/host-user.sh` prints the user a container must run as for
the files it writes into the checkout to belong to you: your own `uid:gid`
with Docker running as root, and `0:0` under rootless Docker, which maps the
container's root to you. The gettext scripts chown what they write to the same
user, and the wp-cli image is allowed to run as its root
(`WP_CLI_ALLOW_ROOT`).

Under rootless Docker, wp-env's WordPress containers also run as a user that
cannot write the `wp-content` directories wp-env creates on the host, nor the
WordPress root where the `.htaccess` for pretty permalinks and the REST API
goes. `.wp-env.json` runs `scripts/docker/wp-env-after-start.sh` after every
start to open those directories and write the files again. With Docker
running as root it does nothing.

## Pixonaut for WooCommerce on the development site

wp-env's two sites have two jobs:

- The **development site** (`WP_ENV_PORT`) runs the real Pixonaut for
  WooCommerce, in the build you choose, or none.
- The **tests site** (`WP_ENV_TESTS_PORT`) never does. It runs a **fake**
  of the plugin (`tests/e2e/fake-pixonaut.php`, mapped in as a mu-plugin)
  that prints a marker where the plugin prints its designer, and both test
  suites run there. Whatever you choose below, the tests are unaffected.

Choose the build with one command, then restart wp-env with `pnpm env:start`:

```bash
pnpm plugin:use main                    # the plugin's main checkout
pnpm plugin:use ../pixonaut-for-woocommerce-wt   # any checkout or worktree, by path
pnpm plugin:use v1.2.3                  # a release, downloaded from GitHub
pnpm plugin:use latest                  # the latest release
pnpm plugin:use fake                    # no plugin on the development site
```

It writes the development site's `plugins` into `.wp-env.override.json`:
`.wp-env.json`'s plugins plus the chosen build. A checkout in a directory
with another name (a worktree) is mounted as
`wp-content/plugins/pixonaut-for-woocommerce` under the development site's
`mappings` instead, so it keeps the plugin's slug; wp-env does not activate
a mapping, so `pnpm env:activate` does.

That file is gitignored, and keeps anything else you put in it. The command refuses to run while the file's top-level
`plugins` lists a build of the plugin: wp-env loads those on both sites, so the
tests site would run it beside its fake. Move the entry out, or delete it, and run it again.

**`main`** is the checkout `PIXONAUT_WC_CHECKOUT` names, or, without it,
`pixonaut-for-woocommerce` beside this repository's main checkout. Set the
variable in your shell.

**A checkout must be built.** The command refuses one without
`vendor/autoload.php`, or with asset sources in `assets/src/` but no built
assets in `assets/build/`, and says which. In the plugin's checkout:

```bash
pnpm install
pnpm composer install   # vendor/, in a Composer container
pnpm build              # assets/src/ to assets/build/
```

The checkout is bind-mounted into the development site. Never
`wp plugin uninstall` it there: uninstall deletes the checkout's files.

**A release** is downloaded with your own `gh` sign-in
(`gh auth status`; you need read access to
`pixonaut-app/pixonaut-for-woocommerce`): the release's
`pixonaut-for-woocommerce-X.Y.Z.zip`, checked against its `.sha256`, is
unpacked under `.wp-env-plugins/<tag>/` (gitignored) and reused next time.
While the repository has no releases, `latest` says so and points you to a
checkout; a missing tag, or a release whose zip is still uploading, fails
the same way.

### Which Pixonaut the plugin talks to

Out of the box the plugin talks to Pixonaut itself, at
`https://api.pixonaut.app`. To point the development site at another one:

```bash
pnpm plugin:api http://localhost:8797 http://localhost:3210   # API, then studio
pnpm plugin:api https://api.staging.example https://studio.staging.example
pnpm plugin:api default                                       # back to the plugin's own
```

The
command sets the plugin's constants for the development site only:
`PIXONAUT_WC_API_BASE` (the API as the browser reaches it),
`PIXONAUT_WC_SERVER_API_BASE` (as WordPress reaches it from inside Docker:
`localhost` becomes `host.docker.internal`) and `PIXONAUT_WC_STUDIO_BASE`.
Restart wp-env afterwards. The plugin's developer tools show the addresses in
use. A store still has to be connected to that Pixonaut in the plugin's
settings before its designer appears.

No test talks to any Pixonaut: the tests site has none of these constants
and runs the fake plugin.

## Where to go next

- [`docs/TESTING.md`](TESTING.md) — the three test tiers and the TDD loop.
- [`docs/I18N.md`](I18N.md) — the translation catalogue workflow.
- [`docs/SITE-EDITOR.md`](SITE-EDITOR.md) — templates, Styles and their traps.
- [`docs/ARCHITECTURE.md`](ARCHITECTURE.md) — the directory layout and how to add a module.
- [`docs/DEPLOY.md`](DEPLOY.md) — releases and updates.
