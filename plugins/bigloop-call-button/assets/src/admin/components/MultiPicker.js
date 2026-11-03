/**
 * Multi-select with search. Options come from a static list or an async loader (with id → label resolution).
 * Selected values show as removable chips.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { cx, uniqueId } from '@bigloop/ui';
import { X } from 'lucide-react';

/**
 * @param {Object}   props
 * @param {Array}    props.value     Selected values.
 * @param {Function} props.onChange  New values.
 * @param {Array}    [props.options] Static options [{value,label}].
 * @param {Function} [props.search]  async (query) => options.
 * @param {Function} [props.resolve] async (values) => options (labels for selected values).
 */
export default function MultiPicker( { label, value = [], onChange, options, search, resolve, placeholder, error } ) {
	const [ id ] = useState( () => uniqueId( 'blcb-mp' ) );
	const [ query, setQuery ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ known, setKnown ] = useState( {} );
	const [ open, setOpen ] = useState( false );

	// Labels for selected values.
	useEffect( () => {
		if ( options ) {
			return;
		}
		const missing = value.filter( ( v ) => ! known[ v ] );
		if ( missing.length && resolve ) {
			resolve( missing ).then( ( list ) => setKnown( ( k ) => ( { ...k, ...Object.fromEntries( list.map( ( o ) => [ o.value, o.label ] ) ) } ) ) );
		}
	}, [ value, options ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Results.
	useEffect( () => {
		if ( options ) {
			const q = query.trim().toLowerCase();
			setResults( options.filter( ( o ) => ! q || o.label.toLowerCase().includes( q ) || String( o.value ).toLowerCase() === q ).slice( 0, 60 ) );
			return undefined;
		}
		if ( ! open || ! search ) {
			return undefined;
		}
		const t = setTimeout( () => search( query.trim() ).then( ( list ) => {
			setResults( list );
			setKnown( ( k ) => ( { ...k, ...Object.fromEntries( list.map( ( o ) => [ o.value, o.label ] ) ) } ) );
		} ), 250 );
		return () => clearTimeout( t );
	}, [ query, open, options, search ] );

	const labelOf = useMemo( () => {
		const map = { ...known };
		( options || [] ).forEach( ( o ) => ( map[ o.value ] = o.label ) );
		return ( v ) => map[ v ] || String( v );
	}, [ known, options ] );

	const toggle = ( v ) => onChange( value.includes( v ) ? value.filter( ( x ) => x !== v ) : [ ...value, v ] );

	return (
		<div className={ cx( 'bl-field', 'blcb-mp', error && 'has-error' ) }>
			{ label && <label className="bl-field__label" htmlFor={ id }>{ label }</label> }
			{ value.length > 0 && (
				<ul className="blcb-mp__chips" aria-label={ __( 'Selected', 'bigloop-call-button' ) }>
					{ value.map( ( v ) => (
						<li key={ v } className="blcb-mp__chip">
							{ labelOf( v ) }
							<button type="button" onClick={ () => toggle( v ) } aria-label={ sprintf( /* translators: %s: item */ __( 'Remove %s', 'bigloop-call-button' ), labelOf( v ) ) }>
								<X size={ 12 } aria-hidden="true" />
							</button>
						</li>
					) ) }
				</ul>
			) }
			<input
				id={ id }
				type="search"
				role="combobox"
				aria-autocomplete="list"
				className="bl-input"
				placeholder={ placeholder || __( 'Search…', 'bigloop-call-button' ) }
				value={ query }
				onFocus={ () => setOpen( true ) }
				onChange={ ( e ) => {
					setQuery( e.target.value );
					setOpen( true );
				} }
				aria-controls={ `${ id }-list` }
				aria-expanded={ open }
				autoComplete="off"
			/>
			{ open && (
				<ul className="blcb-mp__results" id={ `${ id }-list` } role="listbox" aria-multiselectable="true" aria-label={ label }>
					{ results.map( ( o ) => (
						<li key={ o.value } role="option" aria-selected={ value.includes( o.value ) }>
							<button type="button" className={ cx( 'blcb-mp__option', value.includes( o.value ) && 'is-selected' ) } onClick={ () => toggle( o.value ) }>
								<span className="blcb-mp__check" aria-hidden="true">{ value.includes( o.value ) ? '✓' : '' }</span>
								{ o.label }
							</button>
						</li>
					) ) }
					{ results.length === 0 && <li className="blcb-mp__none bl-muted">{ search && ! query ? __( 'Type to search…', 'bigloop-call-button' ) : __( 'No matches', 'bigloop-call-button' ) }</li> }
				</ul>
			) }
			{ error && <p className="bl-field__error" role="alert">{ error }</p> }
		</div>
	);
}
