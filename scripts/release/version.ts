/**
 * The theme's version, which style.css's `Version` header holds and
 * package.json repeats; release-please sets both. A release tag `vX.Y.Z` must equal it: the update
 * checker reads the version a store is offered from style.css at the tag,
 * not from the tag's name.
 */

/** A release version, `X.Y.Z`: the only shape release.yml tags. */
const RELEASE_VERSION = /^\d+\.\d+\.\d+$/;

/** style.css's `Version` header line. */
const VERSION_HEADER = /^Version:[ \t]*(.*)$/m;

function isReleaseVersion( version: string ): boolean {
	return RELEASE_VERSION.test( version );
}

/** The version style.css's header holds, or `null` when it has none. */
export function stylesheetVersion( stylesheet: string ): string | null {
	const match = VERSION_HEADER.exec( stylesheet );

	return match === null ? null : match[ 1 ].trim();
}

/** The version a release tag names: `v1.2.3` is `1.2.3`; anything else is `null`. */
export function tagVersion( tag: string ): string | null {
	const version = tag.startsWith( 'v' ) ? tag.slice( 1 ) : '';

	return isReleaseVersion( version ) ? version : null;
}
