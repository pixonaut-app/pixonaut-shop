/**
 * The dependent filters a value unticked in an archive's drawer alone
 * opened: those no other value still ticked in its list opens too
 * (`src/archive/dependent-values.ts`).
 */
export function openedAlone( opens: string[], stillOpened: string[][] ): string[] {
	const others = new Set( stillOpened.flat() );

	return opens.filter( ( dependent ) => ! others.has( dependent ) );
}
