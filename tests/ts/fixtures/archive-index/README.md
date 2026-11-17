# Archive index fixtures

Copied from Pixonaut for WooCommerce's `tests/fixtures/archive-index/`, which
its integration suite writes from the server-rendered archive. Each case is an
index (`<case>.index.json`) and, per address, the cards, links, pictures and
counts the server shows for it (`<case>.expected.json`).
`tests/ts/archive-index-fixtures.test.ts` checks that the archive filtered in
the browser shows the same. Copy them again when the plugin's change.
