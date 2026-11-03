/**
 * Display-rule builder: match all/any + rows of { condition, operator, value }.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { Button, Segmented, TextField, Notice, cx } from '@bigloop/ui';
import { Plus, X } from 'lucide-react';
import { get } from '../lib/api';
import { countries } from '../lib/countries';
import MultiPicker from './MultiPicker';

let metaPromise = null;
export function useConditionsMeta() {
	const [ meta, setMeta ] = useState( null );
	useEffect( () => {
		if ( ! metaPromise ) {
			metaPromise = get( '/conditions' ).catch( ( e ) => {
				metaPromise = null;
				throw e;
			} );
		}
		metaPromise.then( setMeta ).catch( () => setMeta( { conditions: [], post_types: [], taxonomies: [] } ) );
	}, [] );
	return meta;
}

const CHOICE_LABELS = () => ( {
	front: __( 'Front page', 'bigloop-call-button' ),
	blog: __( 'Blog / posts page', 'bigloop-call-button' ),
	search: __( 'Search results', 'bigloop-call-button' ),
	404: __( '404 not found', 'bigloop-call-button' ),
	archive: __( 'Any archive', 'bigloop-call-button' ),
	logged_in: __( 'logged in', 'bigloop-call-button' ),
	logged_out: __( 'logged out', 'bigloop-call-button' ),
	shop: __( 'Shop', 'bigloop-call-button' ),
	product: __( 'Any product page', 'bigloop-call-button' ),
	cart: __( 'Cart', 'bigloop-call-button' ),
	checkout: __( 'Checkout', 'bigloop-call-button' ),
	account: __( 'My account', 'bigloop-call-button' ),
} );

function emptyValue( kind ) {
	if ( kind.startsWith( 'ids' ) || kind.startsWith( 'strings' ) ) {
		return [];
	}
	if ( kind === 'terms' ) {
		return { taxonomy: '', terms: [] };
	}
	if ( kind === 'kv' ) {
		return { key: '', value: '' };
	}
	if ( kind.startsWith( 'choice:' ) ) {
		return kind.slice( 7 ).split( '|' )[ 0 ];
	}
	return '';
}

function ValueInput( { cond, rule, meta, onChange } ) {
	const kind = cond.value;
	const labels = CHOICE_LABELS();
	if ( kind.startsWith( 'ids:' ) ) {
		const type = kind.slice( 4 );
		return (
			<MultiPicker
				value={ rule.value || [] }
				onChange={ onChange }
				placeholder={ __( 'Search by title…', 'bigloop-call-button' ) }
				search={ ( q ) => get( '/lookup/posts', { type, search: q } ) }
				resolve={ ( ids ) => get( '/lookup/posts', { type, include: ids.join( ',' ) } ) }
			/>
		);
	}
	if ( kind === 'strings:post_types' ) {
		return <MultiPicker value={ rule.value || [] } onChange={ onChange } options={ meta.post_types } placeholder={ __( 'Choose post types…', 'bigloop-call-button' ) } />;
	}
	if ( kind === 'strings:countries' ) {
		return <MultiPicker value={ rule.value || [] } onChange={ onChange } options={ countries() } placeholder={ __( 'Search countries…', 'bigloop-call-button' ) } />;
	}
	if ( kind === 'terms' ) {
		const fixed = cond.id === 'woo_category' ? 'product_cat' : '';
		const tax = fixed || ( rule.value && rule.value.taxonomy ) || '';
		return (
			<div className="blcb-rule__terms">
				{ ! fixed && (
					<select className="bl-select" value={ tax } aria-label={ __( 'Taxonomy', 'bigloop-call-button' ) } onChange={ ( e ) => onChange( { taxonomy: e.target.value, terms: [] } ) }>
						<option value="">{ __( 'Choose…', 'bigloop-call-button' ) }</option>
						{ meta.taxonomies.map( ( t ) => (
							<option key={ t.value } value={ t.value }>
								{ t.label }
							</option>
						) ) }
					</select>
				) }
				{ tax && (
					<MultiPicker
						key={ tax }
						value={ ( rule.value && rule.value.terms ) || [] }
						onChange={ ( terms ) => onChange( { taxonomy: tax, terms } ) }
						placeholder={ __( 'Search terms…', 'bigloop-call-button' ) }
						search={ ( q ) => get( '/lookup/terms', { taxonomy: tax, search: q } ) }
						resolve={ ( ids ) => get( '/lookup/terms', { taxonomy: tax, include: ids.join( ',' ) } ) }
					/>
				) }
			</div>
		);
	}
	if ( kind === 'kv' ) {
		const v = rule.value || { key: '', value: '' };
		return (
			<div className="blcb-row">
				<TextField label={ __( 'Parameter', 'bigloop-call-button' ) } placeholder="utm_source" value={ v.key } onChange={ ( key ) => onChange( { ...v, key } ) } />
				{ rule.op === 'equals' && <TextField label={ __( 'Value', 'bigloop-call-button' ) } placeholder="google" value={ v.value } onChange={ ( value ) => onChange( { ...v, value } ) } /> }
			</div>
		);
	}
	if ( kind.startsWith( 'choice:' ) ) {
		return (
			<select className="bl-select" value={ rule.value } aria-label={ cond.label } onChange={ ( e ) => onChange( e.target.value ) }>
				{ kind
					.slice( 7 )
					.split( '|' )
					.map( ( c ) => (
						<option key={ c } value={ c }>
							{ labels[ c ] || c }
						</option>
					) ) }
			</select>
		);
	}
	if ( kind === 'text' ) {
		return <input className="bl-input" value={ rule.value || '' } placeholder="/services/" aria-label={ cond.label } onChange={ ( e ) => onChange( e.target.value ) } />;
	}
	return null;
}

export default function RuleBuilder( { rules, onChange, errors = {} } ) {
	const meta = useConditionsMeta();
	if ( ! meta ) {
		return <p className="bl-muted">{ __( 'Loading conditions…', 'bigloop-call-button' ) }</p>;
	}
	const items = rules.items || [];
	const byId = Object.fromEntries( meta.conditions.map( ( c ) => [ c.id, c ] ) );
	const groups = [ ...new Set( meta.conditions.map( ( c ) => c.group ) ) ];

	const setItem = ( i, patch ) => onChange( { ...rules, items: items.map( ( r, j ) => ( j === i ? { ...r, ...patch } : r ) ) } );
	const add = () => {
		const c = meta.conditions.find( ( x ) => x.available ) || meta.conditions[ 0 ];
		onChange( { ...rules, items: [ ...items, { type: c.id, op: Object.keys( c.operators )[ 0 ], value: emptyValue( c.value ) } ] } );
	};

	return (
		<div className="blcb-rules">
			{ items.length === 0 ? (
				<p className="blcb-rules__everywhere">{ __( 'Shown on every page of your site.', 'bigloop-call-button' ) }</p>
			) : (
				<Segmented
					label={ __( 'Show this widget when', 'bigloop-call-button' ) }
					value={ rules.match || 'all' }
					onChange={ ( match ) => onChange( { ...rules, match } ) }
					options={ [
						{ value: 'all', label: __( 'all conditions match', 'bigloop-call-button' ) },
						{ value: 'any', label: __( 'any condition matches', 'bigloop-call-button' ) },
					] }
				/>
			) }
			<ol className="blcb-rules__list">
				{ items.map( ( rule, i ) => {
					const cond = byId[ rule.type ];
					const error = errors[ `rules.items.${ i }` ];
					return (
						<li key={ i } className={ cx( 'blcb-rule', error && 'has-error' ) }>
							{ i > 0 && <span className="blcb-rule__join">{ rules.match === 'any' ? __( 'or', 'bigloop-call-button' ) : __( 'and', 'bigloop-call-button' ) }</span> }
							<div className="blcb-rule__row">
								<select
									className="bl-select"
									value={ rule.type }
									aria-label={ sprintf( /* translators: %d: rule number */ __( 'Condition %d', 'bigloop-call-button' ), i + 1 ) }
									onChange={ ( e ) => {
										const c = byId[ e.target.value ];
										setItem( i, { type: c.id, op: Object.keys( c.operators )[ 0 ], value: emptyValue( c.value ) } );
									} }
								>
									{ groups.map( ( g ) => (
										<optgroup key={ g } label={ g }>
											{ meta.conditions
												.filter( ( c ) => c.group === g )
												.map( ( c ) => (
													<option key={ c.id } value={ c.id } disabled={ ! c.available && c.id !== rule.type }>
														{ c.label }
														{ c.available ? '' : ` (${ __( 'unavailable', 'bigloop-call-button' ) })` }
													</option>
												) ) }
										</optgroup>
									) ) }
								</select>
								{ cond && (
									<select className="bl-select blcb-rule__op" value={ rule.op } aria-label={ __( 'Operator', 'bigloop-call-button' ) } onChange={ ( e ) => setItem( i, { op: e.target.value } ) }>
										{ Object.entries( cond.operators ).map( ( [ op, label ] ) => (
											<option key={ op } value={ op }>
												{ label }
											</option>
										) ) }
									</select>
								) }
								<Button size="sm" variant="ghost" icon={ <X /> } onClick={ () => onChange( { ...rules, items: items.filter( ( r, j ) => j !== i ) } ) } aria-label={ sprintf( /* translators: %d: rule number */ __( 'Remove condition %d', 'bigloop-call-button' ), i + 1 ) } />
							</div>
							{ cond && (
								<div className="blcb-rule__value">
									<ValueInput cond={ cond } rule={ rule } meta={ meta } onChange={ ( value ) => setItem( i, { value } ) } />
									{ cond.note && <p className="bl-field__help">{ cond.note }</p> }
									{ ! cond.available && <Notice tone="warning">{ cond.note || __( 'This condition is not available on this site.', 'bigloop-call-button' ) }</Notice> }
								</div>
							) }
							{ error && <p className="bl-field__error">{ error }</p> }
						</li>
					) ;
				} ) }
			</ol>
			<Button icon={ <Plus /> } onClick={ add }>
				{ items.length ? __( 'Add condition', 'bigloop-call-button' ) : __( 'Only show on certain pages…', 'bigloop-call-button' ) }
			</Button>
		</div>
	);
}
