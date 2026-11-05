# Releases and updates

A release attaches the installable theme zip to a GitHub release; every store
is offered it within twelve hours.

## style.css holds the version

style.css's `Version` header is the theme's version; package.json repeats it
for the tooling, and `StylesheetHeaderTest` fails when the two differ.
`Support\ThemeVersion` reads it at runtime. The update checker reads the
version a store is offered from **style.css at the release's tag**, not from
the tag's name, so the two must agree: the release workflow refuses a tag
that is not style.css's version.

## Cutting a release

Releases are cut by [release-please](https://github.com/googleapis/release-please)
from the Conventional Commits on `main`. The first release is 1.0.0; after it,
`fix` is a patch, `feat` a minor and a `!` or a `BREAKING CHANGE:` footer a major.

1. Run the whole suite on `main`, and wait for it to pass:

   ```bash
   gh workflow run ci.yml --ref main
   gh run watch
   ```

2. Start the `Release` workflow. It opens or updates one release pull request,
   which sets the version in style.css and package.json and writes
   `CHANGELOG.md`:

   ```bash
   gh workflow run Release --ref main
   ```

3. Merge that pull request, then start the workflow again. It tags the version,
   creates the GitHub release with the changelog as its notes, and builds and
   attaches the zip.

The repository must allow GitHub Actions to create pull requests. To build a ref
as an artifact without publishing anything, pass it as `build_ref`.

## What the release builds

1. Checks the tag equals style.css's version
   (`scripts/release/check-tag-cli.ts`).
2. `pnpm build`, then `composer install --no-dev --optimize-autoloader
   --classmap-authoritative`: PHPUnit, PHPStan, PHPCS and the rest stay out;
   plugin-update-checker stays in.
3. Compiles `languages/sv_SE.mo` from the PO.
4. Assembles `pixonaut-shop/` from an **allowlist** — `style.css`,
   `theme.json`, `functions.php`, `index.php`, `assets/`, `inc/`, `parts/`,
   `patterns/`, `templates/`, `styles/`, `languages/`, `vendor/`, `build/` (without source
   maps) and `screenshot.png` when there is one. Anything not named —
   `tests/`, `src/`, `docs/`, tooling configs — is left out by construction.
   Then strips tests, docs and tooling files from `vendor/`.
5. Zips it as `pixonaut-shop-X.Y.Z.zip`, whose top directory is the fixed
   slug.
6. **Smoke-tests the zip itself** on a fresh WordPress with WooCommerce in
   wp-env: activates it, requires the front page and the shop to answer 200
   with no PHP error or warning in `debug.log`. A runtime file the allowlist
   forgot fails here, not on a store.
7. Uploads the zip and its `.sha256` as an artifact and, for a release,
   attaches them to the GitHub release. Stores are offered the release from
   the moment its zip is attached.

A new file or directory the theme loads at runtime needs adding to the
allowlist in the same change.

## Updates on a store

A store needs nothing set up: the repository is public, so the theme checks its
releases without a token. It checks twice a day, and within a minute of someone opening
**Dashboard → Updates**. The update installs like any theme update, from
**Dashboard → Updates** or **Appearance → Themes**; a store's Styles and
customised templates are kept.

**What is offered:** only GitHub releases, never tags or branches; only
`vX.Y.Z` releases not marked pre-release; only with
`pixonaut-shop-X.Y.Z.zip` attached (a release still uploading is skipped);
the highest version among the 20 most recently published releases. The
checker's state is kept in the `pixonaut_shop_update_state` option.

## An optional token

GitHub limits anonymous API requests per address, so a store on a busy shared
address may miss a check. To authenticate the checks, create a fine-grained
personal access token with public read access only, and add it to the store's
`wp-config.php`, above `/* That's all, stop editing! */`:

```php
define( 'PIXONAUT_SHOP_UPDATE_TOKEN', 'github_pat_…' );
```

The theme sends it to GitHub only, as an `Authorization` header, never in a URL,
and never saves it in the database or a log.

## Installing by hand

Download `pixonaut-shop-X.Y.Z.zip` from the release and upload it under
**Appearance → Themes → Add New → Upload Theme**, replacing the installed
version. Never install it under another directory name: a store's Styles
belong to the `pixonaut-shop` slug.
