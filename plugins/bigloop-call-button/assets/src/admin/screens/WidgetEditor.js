/**
 * Create / edit a contact widget.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import {
	Button,
	Card,
	Notice,
	Skeleton,
	Tabs,
	TextField,
	Toggle,
	ColorField,
	NumberField,
	Segmented,
	SelectField,
	ConfirmDialog,
	Badge,
	EmptyState,
	useToast,
	cx,
} from '@bigloop/ui';
import { ArrowLeft, ArrowUp, ArrowDown, X, Plus, Eye, Zap, Monitor, Tablet, Smartphone, Palette, CalendarClock } from 'lucide-react';
import { get, post, put, parseError } from '../lib/api';
import { linkHandler, urlFor } from '../lib/router';
import { useIcons, LibIcon } from '../lib/icons';
import { useActionTypes, ActionBadge } from './Actions';
import { widgetTypeLabel, WidgetTypeIcon } from './Widgets';
import ActionEditor from '../components/ActionEditor';
import RuleBuilder from '../components/RuleBuilder';
import { ScheduleEditor, formatIntervals } from './Schedules';
import AnchorPicker from '../components/AnchorPicker';
import IconPicker from '../components/IconPicker';
import PreviewPanel, { DEVICES } from '../components/PreviewPanel';
import ResponsiveNumber from '../components/Responsive';
import PresetsModal, { applyPreset } from '../components/PresetsModal';

/**
 * Sets a nested value immutably by dotted path.
 *
 * @param {Object} obj   Source.
 * @param {string} path  "a.b.c".
 * @param {*}      value Value.
 * @return {Object} Copy.
 */
export function setPath( obj, path, value ) {
	const [ head, ...rest ] = path.split( '.' );
	return { ...obj, [ head ]: rest.length ? setPath( obj[ head ] || {}, rest.join( '.' ), value ) : value };
}

const SHAPES = () => [
	{ value: 'circle', label: __( 'Circle', 'bigloop-call-button' ), preview: <span className="blcb-shape-swatch" style={ { borderRadius: '999px' } } /> },
	{ value: 'squircle', label: __( 'Squircle', 'bigloop-call-button' ), preview: <span className="blcb-shape-swatch blcb-shape-swatch--squircle" /> },
	{ value: 'rounded', label: __( 'Rounded', 'bigloop-call-button' ), preview: <span className="blcb-shape-swatch" style={ { borderRadius: '8px' } } /> },
	{ value: 'square', label: __( 'Square', 'bigloop-call-button' ), preview: <span className="blcb-shape-swatch" style={ { borderRadius: 0 } } /> },
	{ value: 'pill', label: __( 'Pill', 'bigloop-call-button' ), preview: <span className="blcb-shape-swatch blcb-shape-swatch--pill" /> },
	{ value: 'custom', label: __( 'Custom', 'bigloop-call-button' ), preview: <span className="blcb-shape-swatch" style={ { borderRadius: '4px 14px' } } /> },
];

export default function WidgetEditor( { boot, navigate, params } ) {
	const toast = useToast();
	const lib = useIcons();
	const types = useActionTypes();
	const widgetId = Number( params.widget ) || 0;

	const [ saved, setSaved ] = useState( null );
	const [ draft, setDraft ] = useState( null );
	const [ library, setLibrary ] = useState( null );
	const [ errors, setErrors ] = useState( {} );
	const [ loadError, setLoadError ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ tab, setTab ] = useState( 'content' );
	const [ device, setDevice ] = useState( 'desktop' );
	const [ newAction, setNewAction ] = useState( false );
	const [ iconPick, setIconPick ] = useState( null );
	const [ leaving, setLeaving ] = useState( false );
	const [ pendingType, setPendingType ] = useState( null );
	const [ presetsOpen, setPresetsOpen ] = useState( false );
	const [ liveErrors, setLiveErrors ] = useState( {} );
	const [ schedules, setSchedules ] = useState( null );
	const [ newSchedule, setNewSchedule ] = useState( false );

	useEffect( () => {
		get( '/schedules' )
			.then( ( r ) => setSchedules( r.items ) )
			.catch( () => setSchedules( [] ) );
	}, [] );

	const load = useCallback( () => {
		setLoadError( null );
		const widgetReq = widgetId ? get( `/widgets/${ widgetId }` ) : get( '/widgets/defaults', { type: 'menu' } ).then( ( config ) => ( { id: 0, title: '', enabled: false, config } ) );
		Promise.all( [ widgetReq, get( '/actions', { orderby: 'title' } ) ] )
			.then( ( [ w, acts ] ) => {
				const doc = { id: w.id, title: w.title, enabled: w.enabled, config: w.config, preview_url: w.preview_url };
				setSaved( doc );
				setDraft( doc );
				setLibrary( acts.items );
				setErrors( {} );
			} )
			.catch( ( e ) => setLoadError( parseError( e ).message ) );
	}, [ widgetId ] );
	useEffect( load, [ load ] );

	const dirty = useMemo( () => ( saved && draft ? JSON.stringify( saved ) !== JSON.stringify( draft ) : false ), [ saved, draft ] );

	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}
		const h = ( e ) => {
			e.preventDefault();
			e.returnValue = '';
		};
		window.addEventListener( 'beforeunload', h );
		return () => window.removeEventListener( 'beforeunload', h );
	}, [ dirty ] );

	const set = useCallback( ( path, value ) => {
		setDraft( ( d ) => ( path === 'title' || path === 'enabled' ? { ...d, [ path ]: value } : { ...d, config: setPath( d.config, path, value ) } ) );
		const drop = ( e ) => {
			if ( ! e[ path ] ) {
				return e;
			}
			const n = { ...e };
			delete n[ path ];
			return n;
		};
		setErrors( drop );
		setLiveErrors( drop );
	}, [] );

	// Errors from the last save, plus live validation from the preview renderer.
	const err = ( path ) => errors[ path ] || liveErrors[ path ];

	const applyType = ( type ) => {
		get( '/widgets/defaults', { type } ).then( ( defaults ) => {
			setDraft( ( d ) => {
				const base = d.id ? d.config : defaults;
				return { ...d, config: { ...base, type, actions: d.config.actions, visibility: d.id ? d.config.visibility : defaults.visibility } };
			} );
		} );
	};

	const chooseType = ( type ) => {
		if ( type === draft.config.type ) {
			return;
		}
		if ( type === 'button' && draft.config.actions.length > 1 ) {
			setPendingType( type );
			return;
		}
		applyType( type );
	};

	const TAB_OF = { title: 'content', type: 'content', content: 'content', actions: 'actions', appearance: 'colors', typography: 'typography', effects: 'effects', bar: 'layout', position: 'position', visibility: 'visibility', advanced: 'advanced', schedule: 'scheduling', rules: 'visibility', reveal: 'visibility' };

	const save = () => {
		setSaving( true );
		const body = { title: draft.title, enabled: draft.enabled, config: draft.config };
		( draft.id ? put( `/widgets/${ draft.id }`, body ) : post( '/widgets', body ) )
			.then( ( w ) => {
				const doc = { id: w.id, title: w.title, enabled: w.enabled, config: w.config, preview_url: w.preview_url };
				setSaved( doc );
				setDraft( doc );
				setErrors( {} );
				toast.success( draft.id ? __( 'Widget saved.', 'bigloop-call-button' ) : __( 'Widget created.', 'bigloop-call-button' ) );
				if ( ! draft.id ) {
					navigate( 'new', { widget: w.id }, { replace: true } );
				}
			} )
			.catch( ( e ) => {
				const { message, fieldErrors } = parseError( e );
				setErrors( fieldErrors );
				toast.error( message );
				const first = Object.keys( fieldErrors )[ 0 ] || '';
				const target = TAB_OF[ first.split( '.' )[ 0 ] ];
				if ( target ) {
					setTab( target );
				}
			} )
			.finally( () => setSaving( false ) );
	};

	const back = () => ( dirty ? setLeaving( true ) : navigate( 'widgets' ) );

	if ( loadError ) {
		return (
			<Notice tone="danger" title={ __( 'The widget could not be loaded.', 'bigloop-call-button' ) } actions={ <Button size="sm" onClick={ load }>{ __( 'Try again', 'bigloop-call-button' ) }</Button> }>
				{ loadError }
			</Notice>
		);
	}

	if ( ! draft || ! types ) {
		return (
			<Card>
				<div className="blcb-stack">
					<Skeleton height="28px" width="40%" />
					<Skeleton height="120px" />
					<Skeleton height="120px" />
				</div>
			</Card>
		);
	}

	const c = draft.config;
	const a = c.appearance;
	const ty = c.typography;
	const fx = c.effects;
	const isBar = c.type === 'bar';
	const isMenu = c.type === 'menu';
	const refs = c.actions;
	const byId = Object.fromEntries( ( library || [] ).map( ( x ) => [ x.id, x ] ) );
	const unused = ( library || [] ).filter( ( x ) => ! refs.some( ( r ) => r.id === x.id ) );
	const allErrors = { ...liveErrors, ...errors };
	const errorKeys = Object.keys( errors );
	const tabHasError = ( id ) => Object.keys( allErrors ).some( ( k ) => TAB_OF[ k.split( '.' )[ 0 ] ] === id );
	const dot = ( id ) => ( tabHasError( id ) ? <span className="blcb-tab-dot" aria-label={ __( 'has errors', 'bigloop-call-button' ) } /> : null );

	const tabs = [
		[ 'content', __( 'Content', 'bigloop-call-button' ) ],
		[ 'actions', __( 'Actions', 'bigloop-call-button' ) ],
		[ 'layout', __( 'Layout', 'bigloop-call-button' ) ],
		[ 'colors', __( 'Colors', 'bigloop-call-button' ) ],
		[ 'typography', __( 'Typography', 'bigloop-call-button' ) ],
		[ 'icons', __( 'Icons', 'bigloop-call-button' ) ],
		[ 'effects', __( 'Effects', 'bigloop-call-button' ) ],
		...( isBar ? [] : [ [ 'position', __( 'Position', 'bigloop-call-button' ) ] ] ),
		[ 'scheduling', __( 'Scheduling', 'bigloop-call-button' ) ],
		[ 'visibility', __( 'Visibility', 'bigloop-call-button' ) ],
		[ 'advanced', __( 'Advanced', 'bigloop-call-button' ) ],
	].map( ( [ id, label ] ) => ( { id, label, badge: dot( id ) } ) );
	const currentTab = tabs.some( ( t ) => t.id === tab ) ? tab : 'content';

	const moveRef = ( i, dir ) => {
		const list = [ ...refs ];
		const j = i + dir;
		if ( j < 0 || j >= list.length ) {
			return;
		}
		[ list[ i ], list[ j ] ] = [ list[ j ], list[ i ] ];
		set( 'actions', list );
	};

	const color = ( label, path, fallback, help ) => {
		const [ section, key ] = path.split( '.' );
		return <ColorField label={ label } help={ help } value={ c[ section ][ key ] || '' } fallback={ fallback } onChange={ ( v ) => set( path, v ) } error={ err( path ) } resetLabel={ __( 'Reset', 'bigloop-call-button' ) } />;
	};
	const responsive = ( label, section, key, min, max, suffix = 'px' ) => (
		<ResponsiveNumber label={ label } device={ device } group={ c[ section ] } keyName={ key } min={ min } max={ max } suffix={ suffix } error={ err( `${ section }.${ device === 'desktop' ? key : key + '_' + device }` ) } onChange={ ( k, v ) => set( `${ section }.${ k }`, v ) } />
	);
	const deviceNote = device !== 'desktop' && (
		<Notice tone="info">
			{ sprintf( /* translators: %s: device name */ __( 'Editing %s sizes. Fields left empty use the larger screen\'s value. Switch devices in the preview.', 'bigloop-call-button' ), DEVICES[ device ].label() ) }
		</Notice>
	);

	return (
		<div className="blcb-editor blcb-builder">
			<div className="blcb-builder__head">
				<div>
					<a className="blcb-back" href={ urlFor( boot.pages, 'widgets' ) } onClick={ ( e ) => {
						if ( e.button === 0 && ! e.metaKey && ! e.ctrlKey ) {
							e.preventDefault();
							back();
						}
					} }>
						<ArrowLeft size={ 16 } aria-hidden="true" /> { __( 'All widgets', 'bigloop-call-button' ) }
					</a>
					<div className="blcb-editor__titlebar">
						<h1 className="blcb-editor__title">{ draft.id ? draft.title || __( 'Untitled widget', 'bigloop-call-button' ) : __( 'Create widget', 'bigloop-call-button' ) }</h1>
						{ draft.id > 0 && ( saved.enabled ? <Badge tone="success" dot>{ __( 'Live', 'bigloop-call-button' ) }</Badge> : <Badge tone="neutral" dot>{ __( 'Disabled', 'bigloop-call-button' ) }</Badge> ) }
					</div>
				</div>
				<div className="blcb-builder__actions">
					<Button icon={ <Palette /> } onClick={ () => setPresetsOpen( true ) }>
						{ __( 'Presets', 'bigloop-call-button' ) }
					</Button>
					<Button icon={ <Eye /> } href={ draft.id && saved.config.actions.length ? saved.preview_url : undefined } disabled={ ! draft.id || ! saved.config.actions.length || dirty } target="_blank" rel="noopener noreferrer" title={ dirty ? __( 'Save first to preview on your site', 'bigloop-call-button' ) : undefined }>
						{ __( 'View on site', 'bigloop-call-button' ) }
					</Button>
					<span className="blcb-builder__enable">
						<Toggle label={ __( 'Enabled', 'bigloop-call-button' ) } checked={ draft.enabled } onChange={ ( v ) => set( 'enabled', v ) } error={ errors.enabled } />
					</span>
					<Button variant="ghost" onClick={ back }>
						{ __( 'Cancel', 'bigloop-call-button' ) }
					</Button>
					<Button variant="primary" onClick={ save } busy={ saving } disabled={ ! dirty && draft.id > 0 }>
						{ draft.id ? __( 'Save changes', 'bigloop-call-button' ) : __( 'Create widget', 'bigloop-call-button' ) }
					</Button>
				</div>
			</div>

			{ errorKeys.length > 0 && (
				<Notice tone="danger" title={ __( 'Please fix the highlighted fields before saving.', 'bigloop-call-button' ) }>
					<ul className="blcb-errlist">
						{ errorKeys.slice( 0, 6 ).map( ( k ) => (
							<li key={ k }>{ errors[ k ] }</li>
						) ) }
					</ul>
				</Notice>
			) }

			<div className="blcb-builder__grid">
				<Card className="blcb-builder__panel" padded>
					<Tabs tabs={ tabs } selected={ currentTab } onSelect={ setTab } label={ __( 'Widget settings', 'bigloop-call-button' ) } orientation="vertical">
						{ ( t ) => (
							<div className="blcb-form">
								{ t === 'content' && (
									<>
										<TextField label={ __( 'Widget name', 'bigloop-call-button' ) } help={ __( 'For your reference in the dashboard.', 'bigloop-call-button' ) } value={ draft.title } onChange={ ( v ) => set( 'title', v ) } error={ err( 'title' ) } maxLength={ 100 } placeholder={ __( 'e.g. Main contact menu', 'bigloop-call-button' ) } />
										<Segmented
											label={ __( 'Widget type', 'bigloop-call-button' ) }
											variant="cards"
											value={ c.type }
											onChange={ chooseType }
											error={ err( 'type' ) }
											options={ [
												{ value: 'button', label: widgetTypeLabel( 'button' ), icon: <WidgetTypeIcon type="button" />, description: __( 'One floating button for one action, e.g. "Call us".', 'bigloop-call-button' ) },
												{ value: 'menu', label: widgetTypeLabel( 'menu' ), icon: <WidgetTypeIcon type="menu" />, description: __( 'A floating button that opens several contact options.', 'bigloop-call-button' ) },
												{ value: 'bar', label: widgetTypeLabel( 'bar' ), icon: <WidgetTypeIcon type="bar" />, description: __( 'A sticky bar across the top or bottom, ideal on mobile.', 'bigloop-call-button' ) },
											] }
										/>
										{ ! isBar && (
											<>
												<TextField
													label={ isMenu ? __( 'Button text', 'bigloop-call-button' ) : __( 'Button text (optional)', 'bigloop-call-button' ) }
													help={ isMenu ? __( 'Read by screen readers, and shown when "Show text" is on. Defaults to "Contact us".', 'bigloop-call-button' ) : __( 'Defaults to the action\'s label.', 'bigloop-call-button' ) }
													value={ c.content.label }
													onChange={ ( v ) => set( 'content.label', v ) }
													error={ err( 'content.label' ) }
													maxLength={ 40 }
												/>
											</>
										) }
										{ ( isMenu || isBar ) && (
											<TextField label={ __( 'List name for screen readers', 'bigloop-call-button' ) } help={ __( 'Defaults to "Contact options".', 'bigloop-call-button' ) } value={ c.content.menu_label } onChange={ ( v ) => set( 'content.menu_label', v ) } error={ err( 'content.menu_label' ) } maxLength={ 80 } />
										) }
									</>
								) }

								{ t === 'actions' && (
									<>
										<p className="bl-muted">
											{ c.type === 'button'
												? __( 'A single button opens one action.', 'bigloop-call-button' )
												: __( 'Add the contact options to show, in order. Visitors see the first one closest to the button.', 'bigloop-call-button' ) }
										</p>
										{ err( 'actions' ) && <Notice tone="danger">{ err( 'actions' ) }</Notice> }
										{ refs.length === 0 && (
											<EmptyState compact icon={ <Zap /> } title={ __( 'No actions in this widget yet', 'bigloop-call-button' ) }>
												{ library && library.length
													? __( 'Choose from your action library below, or create a new one.', 'bigloop-call-button' )
													: __( 'Create your first action, such as a phone number or WhatsApp chat.', 'bigloop-call-button' ) }
											</EmptyState>
										) }
										{ refs.length > 0 && (
											<ol className="blcb-refs">
												{ refs.map( ( ref, i ) => {
													const a = byId[ ref.id ];
													return (
														<li key={ ref.id } className={ cx( 'blcb-refs__item', a && ! a.enabled && 'is-disabled', err( `actions.${ i }` ) && 'has-error' ) }>
															{ a ? <ActionBadge action={ a } types={ types } lib={ lib } size={ 32 } /> : <span className="blcb-action-badge" /> }
															<div className="blcb-refs__main">
																<strong>{ a ? a.title : __( 'Missing action', 'bigloop-call-button' ) }</strong>
																<span className="bl-muted blcb-refs__href">{ a ? a.href : err( `actions.${ i }` ) }</span>
																{ a && ! a.enabled && <Badge tone="warning">{ __( 'Disabled: hidden from visitors', 'bigloop-call-button' ) }</Badge> }
															</div>
															<div className="blcb-refs__label">
																<input
																	className="bl-input"
																	value={ ref.label || '' }
																	placeholder={ a ? a.config.label || a.title : '' }
																	maxLength={ 60 }
																	aria-label={ sprintf( /* translators: %s: action name */ __( 'Label for %s in this widget', 'bigloop-call-button' ), a ? a.title : '' ) }
																	onChange={ ( e ) => set( 'actions', refs.map( ( r, j ) => ( j === i ? { ...r, label: e.target.value } : r ) ) ) }
																/>
															</div>
															<div className="blcb-row-actions">
																<Button size="sm" variant="ghost" icon={ <ArrowUp /> } disabled={ i === 0 } onClick={ () => moveRef( i, -1 ) } aria-label={ __( 'Move up', 'bigloop-call-button' ) } />
																<Button size="sm" variant="ghost" icon={ <ArrowDown /> } disabled={ i === refs.length - 1 } onClick={ () => moveRef( i, 1 ) } aria-label={ __( 'Move down', 'bigloop-call-button' ) } />
																<Button size="sm" variant="ghost" icon={ <X /> } onClick={ () => set( 'actions', refs.filter( ( r, j ) => j !== i ) ) } aria-label={ sprintf( /* translators: %s: action name */ __( 'Remove %s from this widget', 'bigloop-call-button' ), a ? a.title : '' ) } />
															</div>
														</li>
													);
												} ) }
											</ol>
										) }
										{ ( c.type !== 'button' || refs.length === 0 ) && (
											<div className="blcb-addaction">
												{ unused.length > 0 && (
													<label className="blcb-addaction__select">
														<span className="bl-visually-hidden">{ __( 'Add an action from your library', 'bigloop-call-button' ) }</span>
														<select
															className="bl-select"
															value=""
															onChange={ ( e ) => {
																const id = Number( e.target.value );
																if ( id ) {
																	set( 'actions', [ ...refs, { id, label: '' } ] );
																}
															} }
														>
															<option value="">{ __( 'Add from library…', 'bigloop-call-button' ) }</option>
															{ unused.map( ( a ) => (
																<option key={ a.id } value={ a.id }>
																	{ a.title }
																	{ a.enabled ? '' : ` (${ __( 'disabled', 'bigloop-call-button' ) })` }
																</option>
															) ) }
														</select>
													</label>
												) }
												<Button icon={ <Plus /> } onClick={ () => setNewAction( true ) }>
													{ __( 'Create new action', 'bigloop-call-button' ) }
												</Button>
											</div>
										) }
									</>
								) }


								{ t === 'layout' && (
									<>
										{ deviceNote }
										{ ! isBar && (
											<>
												<Segmented label={ __( 'Shape', 'bigloop-call-button' ) } variant="swatches" value={ a.shape } onChange={ ( v ) => set( 'appearance.shape', v ) } options={ SHAPES() } error={ err( 'appearance.shape' ) } />
												{ a.shape === 'custom' && <NumberField label={ __( 'Corner radius', 'bigloop-call-button' ) } suffix="px" min={ 0 } max={ 100 } value={ a.radius } onChange={ ( v ) => set( 'appearance.radius', v ) } error={ err( 'appearance.radius' ) } /> }
												<div className="blcb-row">
													{ responsive( __( 'Button size', 'bigloop-call-button' ), 'appearance', 'size', 32, 120 ) }
													{ isMenu && <NumberField label={ __( 'Space between options', 'bigloop-call-button' ) } suffix="px" min={ 4 } max={ 32 } value={ a.gap } onChange={ ( v ) => set( 'appearance.gap', v ) } error={ err( 'appearance.gap' ) } /> }
												</div>
												<div className="blcb-sizepresets" role="group" aria-label={ __( 'Size presets', 'bigloop-call-button' ) }>
													{ [ [ 'S', 48, 20 ], [ 'M', 56, 24 ], [ 'L', 64, 28 ], [ 'XL', 72, 32 ] ].map( ( [ n, sz, ic ] ) => (
														<button key={ n } type="button" className={ cx( 'blcb-chip', a.size === sz && 'is-active' ) } onClick={ () => {
															set( 'appearance.size', sz );
															set( 'appearance.icon_size', ic );
														} }>
															{ n } · { sz }px
														</button>
													) ) }
												</div>
												<Toggle label={ __( 'Show text next to the icon', 'bigloop-call-button' ) } checked={ c.content.show_label } onChange={ ( v ) => set( 'content.show_label', v ) } />
												{ isMenu && (
													<Segmented
														label={ __( 'Option labels in the open menu', 'bigloop-call-button' ) }
														value={ a.item_labels }
														onChange={ ( v ) => set( 'appearance.item_labels', v ) }
														options={ [
															{ value: 'always', label: __( 'Show labels', 'bigloop-call-button' ) },
															{ value: 'never', label: __( 'Icons only (tooltip on hover)', 'bigloop-call-button' ) },
														] }
													/>
												) }
											</>
										) }
										{ isBar && (
											<>
												<div className="blcb-row">
													<Segmented label={ __( 'Placement', 'bigloop-call-button' ) } value={ c.bar.placement } onChange={ ( v ) => set( 'bar.placement', v ) } options={ [ { value: 'bottom', label: __( 'Bottom', 'bigloop-call-button' ) }, { value: 'top', label: __( 'Top', 'bigloop-call-button' ) } ] } />
													<Segmented label={ __( 'Width', 'bigloop-call-button' ) } value={ c.bar.width } onChange={ ( v ) => set( 'bar.width', v ) } options={ [ { value: 'full', label: __( 'Full width', 'bigloop-call-button' ) }, { value: 'content', label: __( 'Floating pill', 'bigloop-call-button' ) } ] } />
												</div>
												<Segmented
													label={ __( 'Item layout', 'bigloop-call-button' ) }
													value={ c.bar.layout }
													onChange={ ( v ) => set( 'bar.layout', v ) }
													options={ [
														{ value: 'icon-above', label: __( 'Icon above text', 'bigloop-call-button' ) },
														{ value: 'icon-beside', label: __( 'Icon beside text', 'bigloop-call-button' ) },
														{ value: 'icon-only', label: __( 'Icons only', 'bigloop-call-button' ) },
														{ value: 'text-only', label: __( 'Text only', 'bigloop-call-button' ) },
													] }
												/>
												<div className="blcb-row">
													{ device === 'mobile' ? (
														<NumberField label={ __( 'Bar height on mobile', 'bigloop-call-button' ) } help={ __( 'Empty uses the desktop height.', 'bigloop-call-button' ) } suffix="px" min={ 40 } max={ 120 } value={ c.bar.height_mobile ?? '' } onChange={ ( v ) => set( 'bar.height_mobile', v === '' ? null : v ) } error={ err( 'bar.height_mobile' ) } />
													) : (
														<NumberField label={ __( 'Bar height', 'bigloop-call-button' ) } suffix="px" min={ 40 } max={ 120 } value={ c.bar.height } onChange={ ( v ) => set( 'bar.height', v ) } error={ err( 'bar.height' ) } />
													) }
													<NumberField label={ __( 'Vertical padding', 'bigloop-call-button' ) } suffix="px" min={ 0 } max={ 24 } value={ c.bar.padding } onChange={ ( v ) => set( 'bar.padding', v ) } error={ err( 'bar.padding' ) } />
												</div>
												<Toggle label={ __( 'Equal-width actions', 'bigloop-call-button' ) } help={ __( 'Off: each action is only as wide as its content.', 'bigloop-call-button' ) } checked={ c.bar.equal_width } onChange={ ( v ) => set( 'bar.equal_width', v ) } />
												<Toggle label={ __( 'Dividers between actions', 'bigloop-call-button' ) } checked={ c.bar.dividers } onChange={ ( v ) => set( 'bar.dividers', v ) } />
												<Toggle label={ __( 'Shadow', 'bigloop-call-button' ) } checked={ c.bar.shadow } onChange={ ( v ) => set( 'bar.shadow', v ) } />
												<Toggle label={ __( 'Keep page content clear of the bar', 'bigloop-call-button' ) } help={ __( 'Adds space at the edge of the page so the bar never covers your footer or header.', 'bigloop-call-button' ) } checked={ c.bar.reserve_space } onChange={ ( v ) => set( 'bar.reserve_space', v ) } />
											</>
										) }
									</>
								) }

								{ t === 'colors' && (
									<>
										{ ! isBar && (
											<>
												<Segmented
													label={ __( 'Button style', 'bigloop-call-button' ) }
													value={ a.style }
													onChange={ ( v ) => set( 'appearance.style', v ) }
													options={ [
														{ value: 'solid', label: __( 'Solid', 'bigloop-call-button' ) },
														{ value: 'gradient', label: __( 'Gradient', 'bigloop-call-button' ) },
														{ value: 'outline', label: __( 'Outline', 'bigloop-call-button' ) },
														{ value: 'minimal', label: __( 'Minimal', 'bigloop-call-button' ) },
														{ value: 'transparent', label: __( 'Transparent', 'bigloop-call-button' ) },
													] }
												/>
												<div className="blcb-row">
													{ color( a.style === 'solid' || a.style === 'gradient' ? __( 'Button colour', 'bigloop-call-button' ) : __( 'Accent colour', 'bigloop-call-button' ), 'appearance.bg', isMenu ? '#4338ca' : '', isMenu ? undefined : __( 'Empty uses the action\'s colour.', 'bigloop-call-button' ) ) }
													{ ( a.style === 'solid' || a.style === 'gradient' ) && color( __( 'Icon & text colour', 'bigloop-call-button' ), 'appearance.fg', '#ffffff' ) }
												</div>
												{ a.style === 'gradient' && (
													<div className="blcb-row">
														{ color( __( 'Gradient end colour', 'bigloop-call-button' ), 'appearance.gradient_to', a.bg || '#4338ca' ) }
														<NumberField label={ __( 'Gradient angle', 'bigloop-call-button' ) } suffix="°" min={ 0 } max={ 360 } value={ a.gradient_angle } onChange={ ( v ) => set( 'appearance.gradient_angle', v ) } error={ err( 'appearance.gradient_angle' ) } />
													</div>
												) }
												<h3 className="blcb-section-title">{ __( 'Hover & pressed', 'bigloop-call-button' ) }</h3>
												<div className="blcb-row">
													{ color( __( 'Hover background', 'bigloop-call-button' ), 'appearance.hover_bg', '' , __( 'Empty keeps the normal colour.', 'bigloop-call-button' ) ) }
													{ color( __( 'Hover icon & text', 'bigloop-call-button' ), 'appearance.hover_fg', '' ) }
												</div>
												{ color( __( 'Pressed background', 'bigloop-call-button' ), 'appearance.active_bg', '', __( 'Empty uses the hover colour.', 'bigloop-call-button' ) ) }
												<h3 className="blcb-section-title">{ __( 'Border', 'bigloop-call-button' ) }</h3>
												<div className="blcb-row">
													<NumberField label={ __( 'Border width', 'bigloop-call-button' ) } suffix="px" min={ 0 } max={ 8 } value={ a.border_width } onChange={ ( v ) => set( 'appearance.border_width', v ) } error={ err( 'appearance.border_width' ) } />
													{ color( __( 'Border colour', 'bigloop-call-button' ), 'appearance.border_color', '' ) }
												</div>
												{ isMenu && (
													<>
														<h3 className="blcb-section-title">{ __( 'Menu option labels', 'bigloop-call-button' ) }</h3>
														<div className="blcb-row">
															{ color( __( 'Label background', 'bigloop-call-button' ), 'appearance.label_bg', '#ffffff' ) }
															{ color( __( 'Label text', 'bigloop-call-button' ), 'appearance.label_fg', '#0f172a' ) }
														</div>
													</>
												) }
												<h3 className="blcb-section-title">{ __( 'Tooltips', 'bigloop-call-button' ) }</h3>
												<div className="blcb-row">
													{ color( __( 'Tooltip background', 'bigloop-call-button' ), 'appearance.tooltip_bg', '#0f172a' ) }
													{ color( __( 'Tooltip text', 'bigloop-call-button' ), 'appearance.tooltip_fg', '#ffffff' ) }
												</div>
												<p className="bl-field__help">{ __( 'Each action\'s own icon colour is set in the Actions library.', 'bigloop-call-button' ) }</p>
											</>
										) }
										{ isBar && (
											<>
												<Toggle label={ __( 'Use each action\'s colour as the background', 'bigloop-call-button' ) } checked={ c.bar.use_action_colors } onChange={ ( v ) => set( 'bar.use_action_colors', v ) } />
												{ ! c.bar.use_action_colors && (
													<div className="blcb-row">
														{ color( __( 'Bar background', 'bigloop-call-button' ), 'bar.bg', '#ffffff' ) }
														{ color( __( 'Bar text colour', 'bigloop-call-button' ), 'bar.fg', '#0f172a' ) }
													</div>
												) }
												{ color( __( 'Divider colour', 'bigloop-call-button' ), 'bar.divider_color', '', __( 'Empty uses a faint tint of the text colour.', 'bigloop-call-button' ) ) }
											</>
										) }
									</>
								) }

								{ t === 'typography' && (
									<>
										{ deviceNote }
										<div className="blcb-row">
											<SelectField
												label={ __( 'Font', 'bigloop-call-button' ) }
												value={ ty.font }
												onChange={ ( v ) => set( 'typography.font', v ) }
												options={ [
													{ value: 'system', label: __( 'System UI (fast, crisp)', 'bigloop-call-button' ) },
													{ value: 'inherit', label: __( 'Same as my theme', 'bigloop-call-button' ) },
													{ value: 'rounded', label: __( 'Rounded', 'bigloop-call-button' ) },
													{ value: 'serif', label: __( 'Serif', 'bigloop-call-button' ) },
													{ value: 'mono', label: __( 'Monospace', 'bigloop-call-button' ) },
													{ value: 'custom', label: __( 'Custom font name…', 'bigloop-call-button' ) },
												] }
											/>
											{ ty.font === 'custom' && <TextField label={ __( 'Font family name', 'bigloop-call-button' ) } help={ __( 'A font your theme already loads, e.g. Inter. No external fonts are loaded by this plugin.', 'bigloop-call-button' ) } value={ ty.custom_font } onChange={ ( v ) => set( 'typography.custom_font', v ) } error={ err( 'typography.custom_font' ) } maxLength={ 60 } /> }
										</div>
										<div className="blcb-row">
											{ responsive( __( 'Font size', 'bigloop-call-button' ), 'typography', 'size', 10, 28 ) }
											<SelectField
												label={ __( 'Font weight', 'bigloop-call-button' ) }
												value={ ty.weight }
												onChange={ ( v ) => set( 'typography.weight', v ) }
												options={ [
													{ value: '400', label: __( 'Regular', 'bigloop-call-button' ) },
													{ value: '500', label: __( 'Medium', 'bigloop-call-button' ) },
													{ value: '600', label: __( 'Semibold', 'bigloop-call-button' ) },
													{ value: '700', label: __( 'Bold', 'bigloop-call-button' ) },
													{ value: '800', label: __( 'Extra bold', 'bigloop-call-button' ) },
												] }
											/>
										</div>
										<div className="blcb-row">
											<NumberField label={ __( 'Line height', 'bigloop-call-button' ) } suffix="%" min={ 100 } max={ 200 } value={ ty.line_height } onChange={ ( v ) => set( 'typography.line_height', v ) } error={ err( 'typography.line_height' ) } />
											<NumberField label={ __( 'Letter spacing', 'bigloop-call-button' ) } help={ __( 'In tenths of a pixel, e.g. 5 = 0.5px.', 'bigloop-call-button' ) } min={ -20 } max={ 60 } value={ ty.letter_spacing } onChange={ ( v ) => set( 'typography.letter_spacing', v ) } error={ err( 'typography.letter_spacing' ) } />
										</div>
										<Segmented
											label={ __( 'Text case', 'bigloop-call-button' ) }
											value={ ty.transform }
											onChange={ ( v ) => set( 'typography.transform', v ) }
											options={ [
												{ value: 'none', label: __( 'As typed', 'bigloop-call-button' ) },
												{ value: 'uppercase', label: 'ABC' },
												{ value: 'capitalize', label: 'Abc Def' },
												{ value: 'lowercase', label: 'abc' },
											] }
										/>
										{ isBar && (
											<Segmented
												label={ __( 'Text alignment', 'bigloop-call-button' ) }
												value={ ty.align }
												onChange={ ( v ) => set( 'typography.align', v ) }
												options={ [
													{ value: 'left', label: __( 'Left', 'bigloop-call-button' ) },
													{ value: 'center', label: __( 'Centre', 'bigloop-call-button' ) },
													{ value: 'right', label: __( 'Right', 'bigloop-call-button' ) },
												] }
											/>
										) }
										<p className="bl-field__help">{ __( 'Applies to button text, menu labels and contact bar labels. Labels, tooltips and screen-reader text are edited per action and in the Content tab.', 'bigloop-call-button' ) }</p>
									</>
								) }

								{ t === 'icons' && (
									<>
										{ deviceNote }
										{ ! isBar && (
												<div className="blcb-row">
													<div className="bl-field">
														<span className="bl-field__label">{ isMenu ? __( 'Button icon', 'bigloop-call-button' ) : __( 'Icon', 'bigloop-call-button' ) }</span>
														<Button onClick={ () => setIconPick( 'content.icon' ) } icon={ <LibIcon lib={ lib } id={ c.content.icon || ( isMenu ? 'message-circle' : byId[ refs[ 0 ]?.id ]?.config.icon || types.find( ( x ) => x.id === byId[ refs[ 0 ]?.id ]?.type )?.icon || 'phone' ) } size={ 16 } /> }>
															{ c.content.icon ? lib?.icons[ c.content.icon ]?.label : isMenu ? __( 'Default (chat bubble)', 'bigloop-call-button' ) : __( 'Action\'s icon', 'bigloop-call-button' ) }
														</Button>
														{ err( 'content.icon' ) && <p className="bl-field__error">{ err( 'content.icon' ) }</p> }
													</div>
													{ isMenu && (
														<div className="bl-field">
															<span className="bl-field__label">{ __( 'Close icon', 'bigloop-call-button' ) }</span>
															<Button onClick={ () => setIconPick( 'content.close_icon' ) } icon={ <LibIcon lib={ lib } id={ c.content.close_icon || 'x' } size={ 16 } /> }>
																{ lib?.icons[ c.content.close_icon || 'x' ]?.label }
															</Button>
														</div>
													) }
												</div>
										) }
										<div className="blcb-row">
											{ isBar ? (
												<NumberField label={ __( 'Icon size', 'bigloop-call-button' ) } suffix="px" min={ 14 } max={ 40 } value={ c.bar.icon_size } onChange={ ( v ) => set( 'bar.icon_size', v ) } error={ err( 'bar.icon_size' ) } />
											) : (
												responsive( __( 'Icon size', 'bigloop-call-button' ), 'appearance', 'icon_size', 12, 64 )
											) }
										</div>
										{ isMenu && <Toggle label={ __( 'Rotate the icon when the menu opens', 'bigloop-call-button' ) } checked={ fx.rotate_icon } onChange={ ( v ) => set( 'effects.rotate_icon', v ) } /> }
										<p className="bl-field__help">
											{ __( 'Each action\'s icon and colours are chosen in the Actions library.', 'bigloop-call-button' ) }{ ' ' }
											<a href={ urlFor( boot.pages, 'actions' ) } target="_blank" rel="noopener noreferrer">
												{ __( 'Open Actions', 'bigloop-call-button' ) }
											</a>
										</p>
									</>
								) }

								{ t === 'effects' && (
									<>
										{ ! isBar && (
											<>
												<Segmented
													label={ __( 'Shadow', 'bigloop-call-button' ) }
													value={ a.shadow }
													onChange={ ( v ) => set( 'appearance.shadow', v ) }
													options={ [
														{ value: 'none', label: __( 'None', 'bigloop-call-button' ) },
														{ value: 'sm', label: __( 'Subtle', 'bigloop-call-button' ) },
														{ value: 'md', label: __( 'Medium', 'bigloop-call-button' ) },
														{ value: 'lg', label: __( 'Strong', 'bigloop-call-button' ) },
													] }
												/>
												<Segmented
													label={ __( 'Hover effect', 'bigloop-call-button' ) }
													value={ fx.hover }
													onChange={ ( v ) => set( 'effects.hover', v ) }
													options={ [
														{ value: 'lift', label: __( 'Lift', 'bigloop-call-button' ) },
														{ value: 'grow', label: __( 'Grow', 'bigloop-call-button' ) },
														{ value: 'glow', label: __( 'Glow', 'bigloop-call-button' ) },
														{ value: 'none', label: __( 'None', 'bigloop-call-button' ) },
													] }
												/>
												<Segmented
													label={ __( 'Attention effect', 'bigloop-call-button' ) }
													help={ __( 'Repeats gently every few seconds. Use sparingly.', 'bigloop-call-button' ) }
													value={ fx.attention }
													onChange={ ( v ) => set( 'effects.attention', v ) }
													options={ [
														{ value: 'none', label: __( 'None', 'bigloop-call-button' ) },
														{ value: 'pulse', label: __( 'Pulse', 'bigloop-call-button' ) },
														{ value: 'bounce', label: __( 'Bounce', 'bigloop-call-button' ) },
														{ value: 'shake', label: __( 'Shake', 'bigloop-call-button' ) },
													] }
												/>
											</>
										) }
										<Segmented
											label={ __( 'Entrance animation', 'bigloop-call-button' ) }
											value={ fx.entrance }
											onChange={ ( v ) => set( 'effects.entrance', v ) }
											options={ [
												{ value: 'none', label: __( 'None', 'bigloop-call-button' ) },
												{ value: 'fade', label: __( 'Fade', 'bigloop-call-button' ) },
												{ value: 'slide', label: __( 'Slide up', 'bigloop-call-button' ) },
												{ value: 'zoom', label: __( 'Zoom', 'bigloop-call-button' ) },
												{ value: 'bounce', label: __( 'Bounce', 'bigloop-call-button' ) },
											] }
										/>
										<Toggle label={ __( 'Enable animations', 'bigloop-call-button' ) } help={ __( 'Turn off to remove all motion from this widget. Animations are always off for visitors who ask their device for reduced motion.', 'bigloop-call-button' ) } checked={ fx.animations } onChange={ ( v ) => set( 'effects.animations', v ) } />
									</>
								) }

								{ t === 'position' && ! isBar && (
									<div className="blcb-positions blcb-positions--3">
										{ [
											[ 'desktop', __( 'Desktop', 'bigloop-call-button' ), <Monitor key="i" size={ 16 } aria-hidden="true" /> ],
											[ 'tablet', __( 'Tablet', 'bigloop-call-button' ), <Tablet key="i" size={ 16 } aria-hidden="true" /> ],
											[ 'mobile', __( 'Mobile', 'bigloop-call-button' ), <Smartphone key="i" size={ 16 } aria-hidden="true" /> ],
										].map( ( [ dev, title, icon ] ) => (
											<div key={ dev } className={ cx( 'blcb-positions__col', device === dev && 'is-current' ) }>
												<h3 className="blcb-section-title">
													{ icon } { title }
												</h3>
												{ dev === 'tablet' && <Toggle label={ __( 'Custom tablet position', 'bigloop-call-button' ) } help={ __( 'Off: tablets use the desktop position.', 'bigloop-call-button' ) } checked={ c.position.tablet_custom } onChange={ ( v ) => set( 'position.tablet_custom', v ) } /> }
												{ ( dev !== 'tablet' || c.position.tablet_custom ) && (
													<>
														<AnchorPicker label={ sprintf( /* translators: %s: device */ __( 'Screen position (%s)', 'bigloop-call-button' ), title ) } value={ c.position[ dev ].anchor } onChange={ ( v ) => set( `position.${ dev }.anchor`, v ) } />
														<NumberField label={ __( 'Side offset', 'bigloop-call-button' ) } suffix="px" min={ 0 } max={ 400 } value={ c.position[ dev ].offset_x } onChange={ ( v ) => set( `position.${ dev }.offset_x`, v ) } error={ err( `position.${ dev }.offset_x` ) } />
														<NumberField label={ __( 'Vertical offset', 'bigloop-call-button' ) } suffix="px" min={ 0 } max={ 400 } value={ c.position[ dev ].offset_y } onChange={ ( v ) => set( `position.${ dev }.offset_y`, v ) } error={ err( `position.${ dev }.offset_y` ) } />
													</>
												) }
											</div>
										) ) }
									</div>
								) }

								{ t === 'scheduling' && (
									<>
										<Toggle label={ __( 'Follow a schedule', 'bigloop-call-button' ) } help={ __( 'Change what this widget shows depending on your business hours. Pages stay correct even when they are cached.', 'bigloop-call-button' ) } checked={ c.schedule.enabled } onChange={ ( v ) => set( 'schedule.enabled', v ) } />
										{ c.schedule.enabled && (
											<>
												{ schedules && schedules.length === 0 ? (
													<EmptyState icon={ <CalendarClock /> } title={ __( 'No schedules yet', 'bigloop-call-button' ) } action={ <Button variant="primary" icon={ <Plus /> } onClick={ () => setNewSchedule( true ) }>{ __( 'Create a schedule', 'bigloop-call-button' ) }</Button> }>
														{ __( 'Set your opening hours once and reuse them in any widget.', 'bigloop-call-button' ) }
													</EmptyState>
												) : (
													<div className="blcb-row blcb-row--end">
														<SelectField
															label={ __( 'Schedule', 'bigloop-call-button' ) }
															value={ String( c.schedule.schedule_id || '' ) }
															onChange={ ( v ) => set( 'schedule.schedule_id', Number( v ) || 0 ) }
															error={ err( 'schedule.schedule_id' ) }
															options={ [ { value: '', label: schedules ? __( 'Choose a schedule…', 'bigloop-call-button' ) : __( 'Loading…', 'bigloop-call-button' ) }, ...( schedules || [] ).map( ( x ) => ( { value: String( x.id ), label: `${ x.title } (${ x.summary.open_now ? __( 'open now', 'bigloop-call-button' ) : __( 'closed now', 'bigloop-call-button' ) })` } ) ) ] }
														/>
														<Button icon={ <Plus /> } onClick={ () => setNewSchedule( true ) }>
															{ __( 'New schedule', 'bigloop-call-button' ) }
														</Button>
													</div>
												) }
												{ ( () => {
													const sc = ( schedules || [] ).find( ( x ) => x.id === c.schedule.schedule_id );
													return sc ? (
														<p className="bl-muted blcb-sched-hint">
															{ sc.summary.week.slice( 0, 3 ).map( ( d ) => `${ d.weekday }: ${ formatIntervals( d.intervals ) }` ).join( ' · ' ) }
														</p>
													) : null;
												} )() }
												<Segmented
													label={ __( 'Outside opening hours', 'bigloop-call-button' ) }
													value={ c.schedule.when_closed }
													onChange={ ( v ) => set( 'schedule.when_closed', v ) }
													options={ [
														{ value: 'swap', label: __( 'Show after-hours actions', 'bigloop-call-button' ) },
														{ value: 'hide', label: __( 'Hide the widget', 'bigloop-call-button' ) },
													] }
												/>
												{ c.schedule.when_closed === 'swap' && (
													<div className="blcb-when">
														<h3 className="blcb-section-title">{ __( 'When each action shows', 'bigloop-call-button' ) }</h3>
														{ refs.length === 0 && <p className="bl-muted">{ __( 'Add actions on the Actions tab first.', 'bigloop-call-button' ) }</p> }
														{ refs.map( ( r, i ) => {
															const x = byId[ r.id ];
															return (
																<div key={ r.id } className="blcb-when__row">
																	<span className="blcb-when__name">{ r.label || ( x ? x.title : `#${ r.id }` ) }</span>
																	<select className="bl-select" value={ r.when || 'always' } aria-label={ sprintf( /* translators: %s: action name */ __( 'When to show %s', 'bigloop-call-button' ), r.label || ( x ? x.title : '' ) ) } onChange={ ( e ) => set( 'actions', refs.map( ( y, j ) => ( j === i ? { ...y, when: e.target.value } : y ) ) ) }>
																		<option value="always">{ __( 'Always', 'bigloop-call-button' ) }</option>
																		<option value="open">{ __( 'Only during opening hours', 'bigloop-call-button' ) }</option>
																		<option value="closed">{ __( 'Only outside opening hours', 'bigloop-call-button' ) }</option>
																	</select>
																</div>
															);
														} ) }
														{ refs.length > 0 && ! refs.some( ( r ) => ( r.when || 'always' ) !== 'closed' ) && <Notice tone="warning">{ __( 'No action shows during opening hours, so the widget will be hidden then.', 'bigloop-call-button' ) }</Notice> }
														{ refs.length > 0 && ! refs.some( ( r ) => ( r.when || 'always' ) !== 'open' ) && <Notice tone="info">{ __( 'No action shows outside opening hours, so the widget will be hidden then.', 'bigloop-call-button' ) }</Notice> }
														<p className="bl-field__help">{ __( 'Example: show "Call us" only during opening hours and "WhatsApp" only outside them.', 'bigloop-call-button' ) }</p>
													</div>
												) }
												<p className="bl-field__help">{ __( 'The preview always shows every action.', 'bigloop-call-button' ) }</p>
											</>
										) }
									</>
								) }

								{ t === 'visibility' && (
									<>
										<p className="bl-muted">{ __( 'Choose the devices that show this widget.', 'bigloop-call-button' ) }</p>
										<div className="blcb-devices">
											<Toggle label={ <><Monitor size={ 15 } aria-hidden="true" /> { __( 'Desktop', 'bigloop-call-button' ) }</> } help={ __( 'Screens wider than 1024px', 'bigloop-call-button' ) } checked={ c.visibility.desktop } onChange={ ( v ) => set( 'visibility.desktop', v ) } />
											<Toggle label={ <><Tablet size={ 15 } aria-hidden="true" /> { __( 'Tablet', 'bigloop-call-button' ) }</> } help={ __( '768–1024px', 'bigloop-call-button' ) } checked={ c.visibility.tablet } onChange={ ( v ) => set( 'visibility.tablet', v ) } />
											<Toggle label={ <><Smartphone size={ 15 } aria-hidden="true" /> { __( 'Mobile', 'bigloop-call-button' ) }</> } help={ __( 'Up to 767px', 'bigloop-call-button' ) } checked={ c.visibility.mobile } onChange={ ( v ) => set( 'visibility.mobile', v ) } />
										</div>
										{ ! c.visibility.desktop && ! c.visibility.tablet && ! c.visibility.mobile && <Notice tone="warning">{ __( 'This widget is hidden on all devices.', 'bigloop-call-button' ) }</Notice> }
										{ ! c.visibility[ device ] && <Notice tone="info">{ sprintf( /* translators: %s: device */ __( 'Hidden on %s, so the preview for this device is empty.', 'bigloop-call-button' ), DEVICES[ device ].label() ) }</Notice> }

										<h3 className="blcb-section-title">{ __( 'Pages', 'bigloop-call-button' ) }</h3>
										<RuleBuilder rules={ c.rules } onChange={ ( v ) => set( 'rules', v ) } errors={ allErrors } />

										<h3 className="blcb-section-title">{ __( 'When it appears', 'bigloop-call-button' ) }</h3>
										<div className="blcb-row">
											<NumberField label={ __( 'After scrolling', 'bigloop-call-button' ) } min={ 0 } max={ c.reveal.scroll_unit === '%' ? 100 : 20000 } value={ c.reveal.scroll } onChange={ ( v ) => set( 'reveal.scroll', v ) } error={ err( 'reveal.scroll' ) } />
											<Segmented label={ __( 'Unit', 'bigloop-call-button' ) } value={ c.reveal.scroll_unit } onChange={ ( v ) => set( 'reveal.scroll_unit', v ) } options={ [ { value: 'px', label: 'px' }, { value: '%', label: __( '% of page', 'bigloop-call-button' ) } ] } />
											<NumberField label={ __( 'After a delay', 'bigloop-call-button' ) } suffix={ __( 'sec', 'bigloop-call-button' ) } min={ 0 } max={ 600 } value={ c.reveal.delay } onChange={ ( v ) => set( 'reveal.delay', v ) } error={ err( 'reveal.delay' ) } />
										</div>
										<p className="bl-field__help">{ __( 'Leave both at 0 to show the widget straight away. Once shown, it stays visible.', 'bigloop-call-button' ) }</p>
									</>
								) }

								{ t === 'advanced' && (
									<>
										<NumberField label={ __( 'Stacking order (z-index)', 'bigloop-call-button' ) } help={ __( 'Raise this if another element on your site covers the widget.', 'bigloop-call-button' ) } min={ 1 } max={ 2147483647 } value={ c.advanced.z_index } onChange={ ( v ) => set( 'advanced.z_index', v ) } error={ err( 'advanced.z_index' ) } />
										<TextField label={ __( 'Extra CSS classes', 'bigloop-call-button' ) } help={ __( 'Space-separated, for your own styling.', 'bigloop-call-button' ) } value={ c.advanced.css_class } onChange={ ( v ) => set( 'advanced.css_class', v ) } error={ err( 'advanced.css_class' ) } />
										{ draft.id > 0 && (
											<div className="bl-field">
												<span className="bl-field__label">{ __( 'Embed this widget', 'bigloop-call-button' ) }</span>
												<code className="blcb-embed">[bigloop_contact id="{ draft.id }"]</code>
												<span className="bl-field__help">{ __( 'Shows the actions as buttons inside a page. Add display="floating" to show it floating on that page only. In the block editor, use the "Contact Widget" block.', 'bigloop-call-button' ) }</span>
											</div>
										) }
									</>
								) }
							</div>
						) }
					</Tabs>
				</Card>

				<aside className="blcb-builder__preview">
					<PreviewPanel boot={ boot } draft={ draft } device={ device } onDevice={ setDevice } onErrors={ setLiveErrors } />
				</aside>
			</div>

			{ newAction && (
				<ActionEditor
					types={ types }
					lib={ lib }
					doc={ null }
					onClose={ () => setNewAction( false ) }
					onSaved={ ( x ) => {
						setLibrary( ( l ) => [ ...( l || [] ), x ] );
						set( 'actions', c.type === 'button' ? [ { id: x.id, label: '' } ] : [ ...refs, { id: x.id, label: '' } ] );
						setNewAction( false );
					} }
				/>
			) }

			{ iconPick && (
				<IconPicker
					lib={ lib }
					value={ iconPick === 'content.icon' ? c.content.icon : c.content.close_icon }
					defaultId={ iconPick === 'content.icon' ? ( isMenu ? 'message-circle' : '' ) : 'x' }
					onChange={ ( v ) => set( iconPick, iconPick === 'content.close_icon' && v === '' ? 'x' : v ) }
					onClose={ () => setIconPick( null ) }
				/>
			) }

			{ newSchedule && (
				<ScheduleEditor
					doc={ null }
					onClose={ () => setNewSchedule( false ) }
					onSaved={ ( x ) => {
						setSchedules( ( l ) => [ ...( l || [] ), x ] );
						set( 'schedule.schedule_id', x.id );
						setNewSchedule( false );
					} }
				/>
			) }

			{ presetsOpen && <PresetsModal config={ c } onApply={ ( style ) => setDraft( ( d ) => ( { ...d, config: applyPreset( d.config, style ) } ) ) } onClose={ () => setPresetsOpen( false ) } /> }

			{ leaving && (
				<ConfirmDialog
					title={ __( 'Discard unsaved changes?', 'bigloop-call-button' ) }
					message={ __( 'Your changes to this widget have not been saved.', 'bigloop-call-button' ) }
					confirmLabel={ __( 'Discard changes', 'bigloop-call-button' ) }
					cancelLabel={ __( 'Keep editing', 'bigloop-call-button' ) }
					destructive
					onCancel={ () => setLeaving( false ) }
					onConfirm={ () => {
						setLeaving( false );
						setDraft( saved );
						navigate( 'widgets' );
					} }
				/>
			) }

			{ pendingType && (
				<ConfirmDialog
					title={ __( 'Switch to a single button?', 'bigloop-call-button' ) }
					message={ __( 'A single button opens one action. Only the first action in this widget will be kept.', 'bigloop-call-button' ) }
					confirmLabel={ __( 'Keep first action', 'bigloop-call-button' ) }
					cancelLabel={ __( 'Cancel', 'bigloop-call-button' ) }
					onCancel={ () => setPendingType( null ) }
					onConfirm={ () => {
						set( 'actions', refs.slice( 0, 1 ) );
						applyType( pendingType );
						setPendingType( null );
					} }
				/>
			) }
		</div>
	);
}
