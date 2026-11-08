/**
 * Opening and closing the mega header's panel (`src/header/mega-menu-panel.ts`): through the
 * browser's popover where it has one, and by a class and `aria-expanded` where it has not.
 * Run with Node's own test runner: `pnpm test:ts`.
 */

import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import {
	controlPanel,
	FALLBACK_OPEN_CLASS,
	type PanelElements,
	supportsPopover,
} from '../../src/header/mega-menu-panel.ts';

type Listener = ( event: { newState?: string } ) => void;

/** An element that records its listeners, attributes and classes, and the selectors it is asked to match. */
class FakeElement {
	readonly listeners = new Map< string, Listener[] >();
	readonly attributes = new Map< string, string >();
	readonly classes = new Set< string >();
	readonly matched: string[] = [];
	hidden = 0;

	readonly classList = {
		contains: ( name: string ) => this.classes.has( name ),
		toggle: ( name: string, force: boolean ) => {
			if ( force ) {
				this.classes.add( name );
			} else {
				this.classes.delete( name );
			}
			return force;
		},
	};

	addEventListener( type: string, listener: Listener ): void {
		this.listeners.set( type, [ ...( this.listeners.get( type ) ?? [] ), listener ] );
	}

	setAttribute( name: string, value: string ): void {
		this.attributes.set( name, value );
	}

	matches( selector: string ): boolean {
		this.matched.push( selector );
		return false;
	}

	hidePopover(): void {
		this.hidden++;
	}

	fire( type: string, event: { newState?: string } = {} ): void {
		for ( const listener of this.listeners.get( type ) ?? [] ) {
			listener( event );
		}
	}
}

function elements(): { panel: FakeElement; toggle: FakeElement; close: FakeElement } {
	return { panel: new FakeElement(), toggle: new FakeElement(), close: new FakeElement() };
}

function control( parts: ReturnType< typeof elements >, supported: boolean, changes: boolean[] ) {
	return controlPanel(
		{
			panel: parts.panel,
			toggle: parts.toggle,
			closers: [ parts.close ],
		} as unknown as PanelElements,
		supported,
		( opened ) => changes.push( opened ),
	);
}

describe( 'supportsPopover', () => {
	it( 'is true for a prototype with the popover property, false without it', () => {
		assert.equal( supportsPopover( { popover: null } ), true );
		assert.equal( supportsPopover( {} ), false );
	} );
} );

describe( 'controlPanel without the Popover API', () => {
	it( 'opens and closes the panel from its button, with a class and aria-expanded', () => {
		const parts = elements();
		const changes: boolean[] = [];
		const panel = control( parts, false, changes );

		assert.equal( panel.isOpen(), false );

		parts.toggle.fire( 'click' );
		assert.equal( panel.isOpen(), true );
		assert.ok( parts.panel.classes.has( FALLBACK_OPEN_CLASS ) );
		assert.equal( parts.toggle.attributes.get( 'aria-expanded' ), 'true' );

		parts.toggle.fire( 'click' );
		assert.equal( panel.isOpen(), false );
		assert.ok( ! parts.panel.classes.has( FALLBACK_OPEN_CLASS ) );
		assert.equal( parts.toggle.attributes.get( 'aria-expanded' ), 'false' );
		assert.deepEqual( changes, [ true, false ] );
	} );

	it( 'closes from the close button and from close(), and only once', () => {
		const parts = elements();
		const changes: boolean[] = [];
		const panel = control( parts, false, changes );

		parts.toggle.fire( 'click' );
		parts.close.fire( 'click' );
		assert.equal( panel.isOpen(), false );

		parts.toggle.fire( 'click' );
		panel.close();
		panel.close();
		assert.deepEqual( changes, [ true, false, true, false ] );
	} );

	it( 'never asks the browser about :popover-open, which it would throw on', () => {
		const parts = elements();
		const panel = control( parts, false, [] );

		parts.toggle.fire( 'click' );
		panel.isOpen();
		panel.close();

		assert.deepEqual( parts.panel.matched, [] );
		assert.equal( parts.panel.hidden, 0 );
	} );
} );

describe( 'controlPanel with the Popover API', () => {
	it( "follows the popover's toggle event and leaves the button to the browser", () => {
		const parts = elements();
		const changes: boolean[] = [];
		const panel = control( parts, true, changes );

		parts.toggle.fire( 'click' );
		assert.deepEqual( changes, [] );

		parts.panel.fire( 'toggle', { newState: 'open' } );
		parts.panel.fire( 'toggle', { newState: 'closed' } );
		assert.deepEqual( changes, [ true, false ] );

		panel.isOpen();
		assert.deepEqual( parts.panel.matched, [ ':popover-open' ] );
		panel.close();
		assert.equal( parts.panel.hidden, 1 );
	} );
} );
