/**
 * Create / edit a library action in a modal. Step 1 (new only): choose the type. Step 2: details.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';
import { Modal, Button, TextField, TextareaField, Toggle, ColorField, Notice, SelectField, cx, useToast } from '@bigloop/ui';
import { ExternalLink, ArrowLeft } from 'lucide-react';
import { post, put, parseError } from '../lib/api';
import { LibIcon } from '../lib/icons';
import IconPicker from './IconPicker';

const CATEGORY_LABELS = () => ( {
	contact: __( 'Call, text & email', 'bigloop-call-button' ),
	messaging: __( 'Messaging apps', 'bigloop-call-button' ),
	location: __( 'Location', 'bigloop-call-button' ),
	web: __( 'Links & booking', 'bigloop-call-button' ),
} );

export function emptyAction( type ) {
	return {
		title: '',
		enabled: true,
		type: type ? type.id : '',
		label: type ? type.label : '',
		tooltip: '',
		aria_label: '',
		icon: '',
		colors: { bg: '', fg: '' },
		new_tab: null,
		value: {},
	};
}

export function fromDoc( doc ) {
	const c = doc.config || {};
	return {
		title: doc.title,
		enabled: doc.enabled,
		type: c.type,
		label: c.label || '',
		tooltip: c.tooltip || '',
		aria_label: c.aria_label || '',
		icon: c.icon || '',
		colors: { bg: c.colors?.bg || '', fg: c.colors?.fg || '' },
		new_tab: c.new_tab ?? null,
		value: { ...( c.value || {} ) },
	};
}

export default function ActionEditor( { types, lib, doc, onSaved, onClose } ) {
	const toast = useToast();
	const isNew = ! doc;
	const [ draft, setDraft ] = useState( () => ( doc ? fromDoc( doc ) : null ) );
	const [ errors, setErrors ] = useState( {} );
	const [ saving, setSaving ] = useState( false );
	const [ picking, setPicking ] = useState( false );
	const [ href, setHref ] = useState( doc ? doc.href : '' );
	const timer = useRef();

	const type = draft ? types.find( ( t ) => t.id === draft.type ) : null;

	// Live link check (debounced dry-run validation on the server, the single source of truth).
	useEffect( () => {
		if ( ! draft || ! type ) {
			return undefined;
		}
		clearTimeout( timer.current );
		timer.current = setTimeout( () => {
			post( '/actions/validate', draft )
				.then( ( res ) => setHref( res.href || '' ) )
				.catch( () => setHref( '' ) );
		}, 350 );
		return () => clearTimeout( timer.current );
	}, [ draft, type ] );

	const set = ( key, value ) => {
		setDraft( ( d ) => ( { ...d, [ key ]: value } ) );
		setErrors( ( e ) => {
			const n = { ...e };
			delete n[ key ];
			return n;
		} );
	};
	const setValue = ( key, value ) => {
		setDraft( ( d ) => ( { ...d, value: { ...d.value, [ key ]: value } } ) );
		setErrors( ( e ) => {
			const n = { ...e };
			delete n[ 'value.' + key ];
			return n;
		} );
	};
	const setColor = ( key, value ) => {
		setDraft( ( d ) => ( { ...d, colors: { ...d.colors, [ key ]: value } } ) );
		setErrors( ( e ) => {
			const n = { ...e };
			delete n[ 'colors.' + key ];
			return n;
		} );
	};

	const save = () => {
		setSaving( true );
		( isNew ? post( '/actions', draft ) : put( `/actions/${ doc.id }`, draft ) )
			.then( ( saved ) => {
				toast.success( isNew ? __( 'Action created.', 'bigloop-call-button' ) : __( 'Action saved.', 'bigloop-call-button' ) );
				onSaved( saved );
			} )
			.catch( ( e ) => {
				const { message, fieldErrors } = parseError( e );
				setErrors( fieldErrors );
				toast.error( message );
			} )
			.finally( () => setSaving( false ) );
	};

	// Step 1: type selection.
	if ( ! draft ) {
		const groups = CATEGORY_LABELS();
		return (
			<Modal title={ __( 'New contact action', 'bigloop-call-button' ) } description={ __( 'What should happen when a visitor taps this action?', 'bigloop-call-button' ) } onClose={ onClose } size="lg" closeLabel={ __( 'Close', 'bigloop-call-button' ) }>
				{ Object.keys( groups ).map( ( cat ) => {
					const list = types.filter( ( t ) => t.category === cat );
					if ( ! list.length ) {
						return null;
					}
					return (
						<div key={ cat } className="blcb-typegroup">
							<h3 className="blcb-typegroup__title">{ groups[ cat ] }</h3>
							<div className="blcb-typegrid">
								{ list.map( ( t ) => (
									<button key={ t.id } type="button" className="blcb-typecard" onClick={ () => setDraft( emptyAction( t ) ) }>
										<span className="blcb-typecard__icon" style={ { background: t.colors.bg, color: t.colors.fg } }>
											<LibIcon lib={ lib } id={ t.icon } size={ 20 } />
										</span>
										<span className="blcb-typecard__text">
											<strong>{ t.label }</strong>
											<span>{ t.description }</span>
										</span>
									</button>
								) ) }
							</div>
						</div>
					);
				} ) }
			</Modal>
		);
	}

	const errorCount = Object.keys( errors ).length;
	const iconId = draft.icon || ( type ? type.icon : '' );
	const bg = draft.colors.bg || ( type ? type.colors.bg : '#4338ca' );
	const fg = draft.colors.fg || ( type ? type.colors.fg : '#ffffff' );

	return (
		<Modal
			title={ isNew ? sprintf( /* translators: %s: action type */ __( 'New %s action', 'bigloop-call-button' ), type ? type.label : '' ) : __( 'Edit action', 'bigloop-call-button' ) }
			onClose={ onClose }
			size="lg"
			closeLabel={ __( 'Close', 'bigloop-call-button' ) }
			footer={
				<>
					{ isNew && (
						<Button variant="ghost" icon={ <ArrowLeft /> } onClick={ () => setDraft( null ) } className="blcb-modal-back">
							{ __( 'Change type', 'bigloop-call-button' ) }
						</Button>
					) }
					<Button variant="secondary" onClick={ onClose }>
						{ __( 'Cancel', 'bigloop-call-button' ) }
					</Button>
					<Button variant="primary" onClick={ save } busy={ saving }>
						{ isNew ? __( 'Create action', 'bigloop-call-button' ) : __( 'Save action', 'bigloop-call-button' ) }
					</Button>
				</>
			}
		>
			{ errorCount > 0 && (
				<div style={ { marginBottom: 16 } }>
					<Notice tone="danger" title={ __( 'Please fix the highlighted fields.', 'bigloop-call-button' ) } />
				</div>
			) }

			<div className="blcb-action-editor">
				<div className="blcb-action-editor__main">
					<h3 className="blcb-section-title">{ __( 'Destination', 'bigloop-call-button' ) }</h3>
					{ type &&
						type.fields.map( ( f ) => {
							const common = {
								label: f.label,
								help: f.help || undefined,
								error: errors[ 'value.' + f.key ],
								value: draft.value[ f.key ] ?? '',
								onChange: ( v ) => setValue( f.key, v ),
								placeholder: f.placeholder || undefined,
								required: f.required || undefined,
								maxLength: f.max_length || undefined,
							};
							if ( f.kind === 'textarea' ) {
								return <TextareaField key={ f.key } { ...common } rows={ 3 } />;
							}
							const inputType = { email: 'email', url: 'url', phone: 'tel', intl_phone: 'tel' }[ f.kind ] || 'text';
							return <TextField key={ f.key } { ...common } type={ inputType } autoComplete="off" />;
						} ) }

					<div className={ cx( 'blcb-linkcheck', href ? 'is-ok' : 'is-empty' ) } aria-live="polite">
						<span className="blcb-linkcheck__label">{ __( 'Link visitors will open', 'bigloop-call-button' ) }</span>
						{ href ? (
							<span className="blcb-linkcheck__row">
								<code className="blcb-linkcheck__href">{ href }</code>
								{ /^https?:/.test( href ) && (
									<a href={ href } target="_blank" rel="noopener noreferrer" className="blcb-linkcheck__test">
										<ExternalLink size={ 14 } aria-hidden="true" /> { __( 'Test', 'bigloop-call-button' ) }
										<span className="bl-visually-hidden">{ __( '(opens in a new tab)', 'bigloop-call-button' ) }</span>
									</a>
								) }
							</span>
						) : (
							<span className="bl-muted">{ __( 'Fill in the required fields to see the link.', 'bigloop-call-button' ) }</span>
						) }
					</div>

					<h3 className="blcb-section-title">{ __( 'Text', 'bigloop-call-button' ) }</h3>
					<TextField label={ __( 'Button label', 'bigloop-call-button' ) } help={ __( 'Shown next to the icon in menus and contact bars.', 'bigloop-call-button' ) } value={ draft.label } onChange={ ( v ) => set( 'label', v ) } error={ errors.label } maxLength={ 60 } placeholder={ type ? type.label : '' } />
					<TextField label={ __( 'Tooltip (optional)', 'bigloop-call-button' ) } help={ __( 'Appears on hover on desktop, e.g. "Mon–Fri, 9am–5pm".', 'bigloop-call-button' ) } value={ draft.tooltip } onChange={ ( v ) => set( 'tooltip', v ) } error={ errors.tooltip } maxLength={ 100 } />
					<TextField label={ __( 'Screen reader label (optional)', 'bigloop-call-button' ) } help={ __( 'Read aloud instead of the label, e.g. "Call our office on 555 123 4567".', 'bigloop-call-button' ) } value={ draft.aria_label } onChange={ ( v ) => set( 'aria_label', v ) } error={ errors.aria_label } maxLength={ 150 } />
					<TextField label={ __( 'Internal name', 'bigloop-call-button' ) } help={ __( 'Only shown in the dashboard, to tell similar actions apart.', 'bigloop-call-button' ) } value={ draft.title } onChange={ ( v ) => set( 'title', v ) } error={ errors.title } maxLength={ 100 } placeholder={ draft.label || ( type ? type.label : '' ) } />
				</div>

				<aside className="blcb-action-editor__side">
					<h3 className="blcb-section-title">{ __( 'Look', 'bigloop-call-button' ) }</h3>
					<div className="blcb-action-preview" aria-hidden="true">
						<span className="blcb-action-preview__icon" style={ { background: bg, color: fg } }>
							<LibIcon lib={ lib } id={ iconId } size={ 24 } />
						</span>
						<span className="blcb-action-preview__label">{ draft.label || ( type ? type.label : '' ) }</span>
					</div>
					<div className="bl-field">
						<span className="bl-field__label">{ __( 'Icon', 'bigloop-call-button' ) }</span>
						<Button onClick={ () => setPicking( true ) } icon={ <LibIcon lib={ lib } id={ iconId } size={ 16 } /> }>
							{ draft.icon ? lib?.icons[ draft.icon ]?.label || draft.icon : __( 'Default icon', 'bigloop-call-button' ) }
						</Button>
						{ errors.icon && <p className="bl-field__error">{ errors.icon }</p> }
					</div>
					<ColorField label={ __( 'Background colour', 'bigloop-call-button' ) } value={ draft.colors.bg } fallback={ type ? type.colors.bg : '' } onChange={ ( v ) => setColor( 'bg', v ) } error={ errors[ 'colors.bg' ] } resetLabel={ __( 'Reset', 'bigloop-call-button' ) } />
					<ColorField label={ __( 'Icon & text colour', 'bigloop-call-button' ) } value={ draft.colors.fg } fallback={ type ? type.colors.fg : '' } onChange={ ( v ) => setColor( 'fg', v ) } error={ errors[ 'colors.fg' ] } resetLabel={ __( 'Reset', 'bigloop-call-button' ) } />
					<SelectField
						label={ __( 'Open in a new tab', 'bigloop-call-button' ) }
						help={ __( 'Phone, SMS and email links always open in the visitor\'s app.', 'bigloop-call-button' ) }
						value={ draft.new_tab === null ? 'default' : draft.new_tab ? 'yes' : 'no' }
						onChange={ ( v ) => set( 'new_tab', v === 'default' ? null : v === 'yes' ) }
						options={ [
							{ value: 'default', label: type && type.new_tab ? __( 'Default (yes)', 'bigloop-call-button' ) : __( 'Default (no)', 'bigloop-call-button' ) },
							{ value: 'yes', label: __( 'Yes', 'bigloop-call-button' ) },
							{ value: 'no', label: __( 'No', 'bigloop-call-button' ) },
						] }
					/>
					<Toggle label={ __( 'Enabled', 'bigloop-call-button' ) } help={ __( 'Disabled actions are hidden from every widget.', 'bigloop-call-button' ) } checked={ draft.enabled } onChange={ ( v ) => set( 'enabled', v ) } />
				</aside>
			</div>

			{ picking && <IconPicker lib={ lib } value={ draft.icon } defaultId={ type ? type.icon : '' } onChange={ ( v ) => set( 'icon', v ) } onClose={ () => setPicking( false ) } /> }
		</Modal>
	);
}
