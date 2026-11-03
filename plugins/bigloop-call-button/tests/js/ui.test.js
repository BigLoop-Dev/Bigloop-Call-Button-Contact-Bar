/**
 * Behaviour tests for design-system components (rendered with react-dom in jsdom).
 */
import { createRoot } from '@wordpress/element';
import { act } from 'react';
import { Toggle, Tabs } from '@bigloop/ui';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

function render( el ) {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	const root = createRoot( container );
	act( () => root.render( el ) );
	return { container, root };
}

describe( 'Toggle', () => {
	it( 'is a labelled switch that reports the next value', () => {
		const onChange = jest.fn();
		const { container } = render( <Toggle label="Enable thing" checked={ false } onChange={ onChange } /> );
		const sw = container.querySelector( '[role="switch"]' );
		expect( sw.getAttribute( 'aria-checked' ) ).toBe( 'false' );
		const label = container.querySelector( 'label' );
		expect( label.getAttribute( 'for' ) ).toBe( sw.id );
		act( () => sw.click() );
		expect( onChange ).toHaveBeenCalledWith( true );
	} );
} );

describe( 'Tabs', () => {
	it( 'supports arrow-key navigation with roving tabindex', () => {
		const onSelect = jest.fn();
		const tabs = [ { id: 'a', label: 'A' }, { id: 'b', label: 'B' } ];
		const { container } = render(
			<Tabs tabs={ tabs } selected="a" onSelect={ onSelect } label="Sections">
				{ ( id ) => <p>panel { id }</p> }
			</Tabs>
		);
		const [ a, b ] = container.querySelectorAll( '[role="tab"]' );
		expect( a.getAttribute( 'tabindex' ) ).toBe( '0' );
		expect( b.getAttribute( 'tabindex' ) ).toBe( '-1' );
		act( () => {
			a.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'ArrowRight', bubbles: true } ) );
		} );
		expect( onSelect ).toHaveBeenCalledWith( 'b' );
		expect( container.querySelector( '[role="tabpanel"]' ).textContent ).toBe( 'panel a' );
	} );
} );
