/**
 * The search block that is only its button (`inc/Header/HeaderSearch.php`).
 *
 * Core's script opens the field from the button, and pressing the button
 * again submits the form. Its field is required, so an empty one would get
 * the browser's validation message: the form skips the browser's check, and
 * an empty search keeps the field open and focused instead of submitting.
 */

const FORMS = 'form.wp-block-search__button-only';

const FIELD = 'input[type="search"]';

function keepEmptySearchOpen( form: HTMLFormElement ): void {
	const field = form.querySelector< HTMLInputElement >( FIELD );

	if ( ! field ) {
		return;
	}

	form.noValidate = true;
	form.addEventListener( 'submit', ( event ) => {
		if ( field.value.trim() === '' ) {
			event.preventDefault();
			field.focus();
		}
	} );
}

for ( const form of document.querySelectorAll< HTMLFormElement >( FORMS ) ) {
	keepEmptySearchOpen( form );
}
