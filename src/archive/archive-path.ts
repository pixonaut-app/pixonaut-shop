/**
 * An archive's own path, whichever of its pages the address is on: WordPress
 * adds `/page/<number>/` to a later page's path, and WooCommerce sends a
 * newly filtered archive back to its first page.
 */
const LATER_PAGE = /\/page\/\d+\/?$/;

export function archivePath( pathname: string ): string {
	return pathname.replace( LATER_PAGE, '/' );
}
