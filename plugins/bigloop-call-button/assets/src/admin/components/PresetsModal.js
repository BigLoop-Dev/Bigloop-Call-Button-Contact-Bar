/**
 * Design presets: apply a built-in or saved style, save the current style, duplicate or delete saved presets.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { Modal, Button, TextField, Notice, Skeleton, Badge, useToast } from '@bigloop/ui';
import { Copy, Trash2, Check } from 'lucide-react';
import { get, post, del, parseError } from '../lib/api';

export const STYLE_SECTIONS = [ 'appearance', 'typography', 'effects', 'bar' ];

/**
 * Deep-merges a preset's partial style into a widget config (other sections untouched).
 *
 * @param {Object} config Widget config.
 * @param {Object} style  Partial style.
 * @return {Object} New config.
 */
export function applyPreset( config, style ) {
	const out = { ...config };
	STYLE_SECTIONS.forEach( ( section ) => {
		if ( style && style[ section ] ) {
			out[ section ] = { ...( config[ section ] || {} ), ...style[ section ] };
		}
	} );
	return out;
}

function Swatch( { style } ) {
	const a = style.appearance || {};
	const radius = { circle: '999px', pill: '999px', square: '0', rounded: '12px', squircle: '30%', custom: `${ a.radius || 16 }px` }[ a.shape || 'circle' ];
	const bg = a.style === 'gradient' && a.gradient_to ? `linear-gradient(${ a.gradient_angle || 135 }deg, ${ a.bg }, ${ a.gradient_to })` : a.style === 'minimal' || a.style === 'outline' ? '#ffffff' : a.bg || '#4338ca';
	const fg = a.style === 'minimal' || a.style === 'outline' ? a.bg || '#4338ca' : a.fg || '#ffffff';
	const bar = style.bar || {};
	return (
		<div className="blcb-preset__swatch" aria-hidden="true" style={ { background: bar.bg || '#f1f5f9' } }>
			<span className="blcb-preset__btn" style={ { background: bg, color: fg, borderRadius: radius, boxShadow: a.border_width ? `inset 0 0 0 ${ a.border_width }px ${ a.border_color || fg }` : undefined, fontFamily: ( style.typography || {} ).font === 'serif' ? 'Georgia, serif' : undefined } }>
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
					<path d="M13.8 16.6a1 1 0 0 0 1.2-.3l.4-.5A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.5.3a1 1 0 0 0-.3 1.2 14 14 0 0 0 6.4 6.5" />
				</svg>
			</span>
			{ a.label_bg && (
				<span className="blcb-preset__label" style={ { background: a.label_bg, color: a.label_fg || '#0f172a' } }>
					Aa
				</span>
			) }
		</div>
	);
}

export default function PresetsModal( { config, onApply, onClose } ) {
	const toast = useToast();
	const [ presets, setPresets ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ name, setName ] = useState( '' );
	const [ nameError, setNameError ] = useState( '' );
	const [ saving, setSaving ] = useState( false );

	const load = () =>
		get( '/presets' )
			.then( setPresets )
			.catch( ( e ) => setError( parseError( e ).message ) );
	useEffect( () => {
		load();
	}, [] );

	const saveCurrent = () => {
		setSaving( true );
		const style = {};
		STYLE_SECTIONS.forEach( ( s ) => ( style[ s ] = config[ s ] ) );
		post( '/presets', { name, description: '', config: style } )
			.then( ( p ) => {
				setPresets( ( list ) => [ ...( list || [] ), p ] );
				setName( '' );
				setNameError( '' );
				toast.success( __( 'Preset saved.', 'bigloop-call-button' ) );
			} )
			.catch( ( e ) => {
				const { message, fieldErrors } = parseError( e );
				setNameError( fieldErrors.name || '' );
				if ( ! fieldErrors.name ) {
					toast.error( message );
				}
			} )
			.finally( () => setSaving( false ) );
	};

	const duplicate = ( p ) =>
		post( `/presets/${ p.key }/duplicate` )
			.then( ( copy ) => {
				setPresets( ( list ) => [ ...list, copy ] );
				toast.success( __( 'Preset duplicated.', 'bigloop-call-button' ) );
			} )
			.catch( ( e ) => toast.error( parseError( e ).message ) );

	const remove = ( p ) =>
		del( `/presets/${ p.id }` )
			.then( () => {
				setPresets( ( list ) => list.filter( ( x ) => x.key !== p.key ) );
				toast.success( __( 'Preset deleted.', 'bigloop-call-button' ) );
			} )
			.catch( ( e ) => toast.error( parseError( e ).message ) );

	const groups = presets ? [ [ __( 'Built-in presets', 'bigloop-call-button' ), presets.filter( ( p ) => p.builtin ) ], [ __( 'Your presets', 'bigloop-call-button' ), presets.filter( ( p ) => ! p.builtin ) ] ] : [];

	return (
		<Modal title={ __( 'Design presets', 'bigloop-call-button' ) } description={ __( 'Apply a ready-made style, then fine-tune anything. Presets change colours, shape, type and effects, never your actions or positions.', 'bigloop-call-button' ) } onClose={ onClose } size="lg" closeLabel={ __( 'Close', 'bigloop-call-button' ) }>
			{ error && <Notice tone="danger">{ error }</Notice> }
			{ ! presets && ! error && <Skeleton height="160px" /> }
			{ groups.map( ( [ title, list ] ) => (
				<div key={ title } className="blcb-presetgroup">
					<h3 className="blcb-typegroup__title">{ title }</h3>
					{ list.length === 0 ? (
						<p className="bl-muted">{ __( 'Save the current widget style below to reuse it on other widgets.', 'bigloop-call-button' ) }</p>
					) : (
						<div className="blcb-presetgrid">
							{ list.map( ( p ) => (
								<div key={ p.key } className="blcb-preset">
									<Swatch style={ p.config } />
									<div className="blcb-preset__body">
										<strong>{ p.name }</strong>
										{ p.description && <span className="bl-muted">{ p.description }</span> }
									</div>
									<div className="blcb-preset__actions">
										<Button
											size="sm"
											variant="primary"
											icon={ <Check /> }
											onClick={ () => {
												onApply( p.config );
												toast.success( sprintf( /* translators: %s: preset name */ __( '"%s" applied. Remember to save the widget.', 'bigloop-call-button' ), p.name ) );
												onClose();
											} }
										>
											{ __( 'Apply', 'bigloop-call-button' ) }
										</Button>
										<Button size="sm" variant="ghost" icon={ <Copy /> } onClick={ () => duplicate( p ) } aria-label={ sprintf( /* translators: %s: preset name */ __( 'Duplicate %s', 'bigloop-call-button' ), p.name ) } />
										{ ! p.builtin && <Button size="sm" variant="ghost" icon={ <Trash2 /> } onClick={ () => remove( p ) } aria-label={ sprintf( /* translators: %s: preset name */ __( 'Delete %s', 'bigloop-call-button' ), p.name ) } /> }
									</div>
									{ p.builtin && <Badge tone="neutral" className="blcb-preset__badge">{ __( 'Built-in', 'bigloop-call-button' ) }</Badge> }
								</div>
							) ) }
						</div>
					) }
				</div>
			) ) }
			<div className="blcb-savepreset">
				<TextField label={ __( 'Save this widget\'s current style as a preset', 'bigloop-call-button' ) } placeholder={ __( 'Preset name', 'bigloop-call-button' ) } value={ name } onChange={ setName } error={ nameError } maxLength={ 60 } />
				<Button onClick={ saveCurrent } busy={ saving } disabled={ ! name.trim() }>
					{ __( 'Save preset', 'bigloop-call-button' ) }
				</Button>
			</div>
		</Modal>
	);
}
