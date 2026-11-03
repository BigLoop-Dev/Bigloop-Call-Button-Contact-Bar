/**
 * Actions library: reusable contact actions referenced by widgets.
 */
import { __, sprintf, _n } from '@wordpress/i18n';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { Button, Card, PageHeader, EmptyState, Notice, Skeleton, Badge, ConfirmDialog, Tooltip, useToast, cx } from '@bigloop/ui';
import { Plus, Pencil, Copy, Trash2, Zap, Search, RefreshCw } from 'lucide-react';
import { get, post, del, parseError } from '../lib/api';
import { useIcons, LibIcon } from '../lib/icons';
import ActionEditor from '../components/ActionEditor';

export function useActionTypes() {
	const [ types, setTypes ] = useState( null );
	useEffect( () => {
		get( '/action-types' )
			.then( setTypes )
			.catch( () => setTypes( [] ) );
	}, [] );
	return types;
}

export function ActionBadge( { action, types, lib, size = 36 } ) {
	const type = types?.find( ( t ) => t.id === action.type );
	const c = action.config || {};
	const bg = c.colors?.bg || type?.colors.bg || '#4338ca';
	const fg = c.colors?.fg || type?.colors.fg || '#fff';
	return (
		<span className="blcb-action-badge" style={ { background: bg, color: fg, width: size, height: size } } aria-hidden="true">
			<LibIcon lib={ lib } id={ c.icon || type?.icon || 'link' } size={ Math.round( size * 0.5 ) } />
		</span>
	);
}

export default function Actions() {
	const toast = useToast();
	const lib = useIcons();
	const types = useActionTypes();
	const [ items, setItems ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ search, setSearch ] = useState( '' );
	const [ typeFilter, setTypeFilter ] = useState( '' );
	const [ editing, setEditing ] = useState( null ); // null | 'new' | doc
	const [ deleting, setDeleting ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	const load = useCallback( () => {
		setError( null );
		get( '/actions', { orderby: 'title' } )
			.then( ( res ) => setItems( res.items ) )
			.catch( ( e ) => setError( parseError( e ).message ) );
	}, [] );
	useEffect( load, [ load ] );

	const filtered = useMemo( () => {
		if ( ! items ) {
			return [];
		}
		const q = search.trim().toLowerCase();
		return items.filter( ( a ) => ( ! typeFilter || a.type === typeFilter ) && ( ! q || a.title.toLowerCase().includes( q ) || ( a.config.label || '' ).toLowerCase().includes( q ) || a.href.toLowerCase().includes( q ) ) );
	}, [ items, search, typeFilter ] );

	const typeLabel = ( id ) => types?.find( ( t ) => t.id === id )?.label || id;

	const toggle = ( a ) => {
		post( `/actions/${ a.id }/status`, { enabled: ! a.enabled } )
			.then( ( saved ) => {
				setItems( ( list ) => list.map( ( x ) => ( x.id === saved.id ? saved : x ) ) );
				toast.success( saved.enabled ? __( 'Action enabled.', 'bigloop-call-button' ) : __( 'Action disabled. It is hidden from all widgets.', 'bigloop-call-button' ) );
			} )
			.catch( ( e ) => toast.error( parseError( e ).message ) );
	};

	const duplicate = ( a ) => {
		post( `/actions/${ a.id }/duplicate` )
			.then( ( copy ) => {
				setItems( ( list ) => [ ...list, copy ] );
				toast.success( __( 'Action duplicated.', 'bigloop-call-button' ) );
			} )
			.catch( ( e ) => toast.error( parseError( e ).message ) );
	};

	const remove = () => {
		setBusy( true );
		del( `/actions/${ deleting.id }` )
			.then( () => {
				setItems( ( list ) => list.filter( ( x ) => x.id !== deleting.id ) );
				toast.success( __( 'Action deleted.', 'bigloop-call-button' ) );
				setDeleting( null );
			} )
			.catch( ( e ) => toast.error( parseError( e ).message ) )
			.finally( () => setBusy( false ) );
	};

	const onSaved = ( saved ) => {
		setItems( ( list ) => ( list.some( ( x ) => x.id === saved.id ) ? list.map( ( x ) => ( x.id === saved.id ? saved : x ) ) : [ ...list, saved ] ) );
		setEditing( null );
	};

	return (
		<div className="blcb-actions-screen">
			<PageHeader
				title={ __( 'Actions', 'bigloop-call-button' ) }
				description={ __( 'Your reusable contact actions. Add them to any widget; edit an action once and every widget using it updates.', 'bigloop-call-button' ) }
				actions={
					<Button variant="primary" icon={ <Plus /> } onClick={ () => setEditing( 'new' ) } disabled={ ! types }>
						{ __( 'New action', 'bigloop-call-button' ) }
					</Button>
				}
			/>

			{ error && (
				<Notice tone="danger" title={ __( 'Actions could not be loaded.', 'bigloop-call-button' ) } actions={ <Button size="sm" icon={ <RefreshCw /> } onClick={ load }>{ __( 'Try again', 'bigloop-call-button' ) }</Button> }>
					{ error }
				</Notice>
			) }

			<Card padded={ false }>
				{ items && items.length > 0 && (
					<div className="blcb-toolbar">
						<label className="blcb-search">
							<Search size={ 16 } aria-hidden="true" />
							<span className="bl-visually-hidden">{ __( 'Search actions', 'bigloop-call-button' ) }</span>
							<input type="search" className="bl-input" placeholder={ __( 'Search actions…', 'bigloop-call-button' ) } value={ search } onChange={ ( e ) => setSearch( e.target.value ) } />
						</label>
						<label className="blcb-filter">
							<span className="bl-visually-hidden">{ __( 'Filter by type', 'bigloop-call-button' ) }</span>
							<select className="bl-select" value={ typeFilter } onChange={ ( e ) => setTypeFilter( e.target.value ) }>
								<option value="">{ __( 'All types', 'bigloop-call-button' ) }</option>
								{ ( types || [] ).map( ( t ) => (
									<option key={ t.id } value={ t.id }>
										{ t.label }
									</option>
								) ) }
							</select>
						</label>
						<span className="blcb-toolbar__count bl-muted">
							{ sprintf( /* translators: %d: number of actions */ _n( '%d action', '%d actions', filtered.length, 'bigloop-call-button' ), filtered.length ) }
						</span>
					</div>
				) }

				{ ! items && ! error && (
					<div className="blcb-stack" style={ { padding: 24 } }>
						<Skeleton height="44px" />
						<Skeleton height="44px" />
						<Skeleton height="44px" />
					</div>
				) }

				{ items && items.length === 0 && (
					<EmptyState
						icon={ <Zap /> }
						title={ __( 'No contact actions yet', 'bigloop-call-button' ) }
						action={
							<Button variant="primary" icon={ <Plus /> } onClick={ () => setEditing( 'new' ) } disabled={ ! types }>
								{ __( 'Create your first action', 'bigloop-call-button' ) }
							</Button>
						}
					>
						{ __( 'An action is one way to reach you: a phone number, a WhatsApp chat, an email address, your location or a booking link.', 'bigloop-call-button' ) }
					</EmptyState>
				) }

				{ items && items.length > 0 && (
					<ul className="blcb-list" aria-label={ __( 'Actions', 'bigloop-call-button' ) }>
						{ filtered.map( ( a ) => (
							<li key={ a.id } className={ cx( 'blcb-list__row', ! a.enabled && 'is-disabled' ) }>
								<ActionBadge action={ a } types={ types } lib={ lib } />
								<div className="blcb-list__main">
									<button type="button" className="blcb-list__title" onClick={ () => setEditing( a ) }>
										{ a.title }
									</button>
									<span className="blcb-list__meta">
										<Badge tone="neutral">{ typeLabel( a.type ) }</Badge>
										{ a.href ? <code className="blcb-list__href" title={ a.href }>{ a.href }</code> : <Badge tone="warning">{ __( 'Needs attention', 'bigloop-call-button' ) }</Badge> }
									</span>
								</div>
								<div className="blcb-list__usage bl-muted">
									{ a.used_by.length
										? sprintf( /* translators: %d: number of widgets */ _n( 'Used in %d widget', 'Used in %d widgets', a.used_by.length, 'bigloop-call-button' ), a.used_by.length )
										: __( 'Not used yet', 'bigloop-call-button' ) }
								</div>
								<span className="blcb-list__switch">
									<button type="button" role="switch" aria-checked={ a.enabled } className={ cx( 'bl-switch', a.enabled && 'is-on' ) } onClick={ () => toggle( a ) } aria-label={ sprintf( /* translators: %s: action name */ __( 'Enable %s', 'bigloop-call-button' ), a.title ) }>
										<span className="bl-switch__thumb" aria-hidden="true" />
									</button>
								</span>
								<div className="blcb-list__actions">
									<Tooltip text={ __( 'Edit', 'bigloop-call-button' ) }>
										<Button size="sm" variant="ghost" icon={ <Pencil /> } onClick={ () => setEditing( a ) } aria-label={ sprintf( /* translators: %s: action name */ __( 'Edit %s', 'bigloop-call-button' ), a.title ) } />
									</Tooltip>
									<Tooltip text={ __( 'Duplicate', 'bigloop-call-button' ) }>
										<Button size="sm" variant="ghost" icon={ <Copy /> } onClick={ () => duplicate( a ) } aria-label={ sprintf( /* translators: %s: action name */ __( 'Duplicate %s', 'bigloop-call-button' ), a.title ) } />
									</Tooltip>
									<Tooltip text={ __( 'Delete', 'bigloop-call-button' ) }>
										<Button size="sm" variant="ghost" icon={ <Trash2 /> } onClick={ () => setDeleting( a ) } aria-label={ sprintf( /* translators: %s: action name */ __( 'Delete %s', 'bigloop-call-button' ), a.title ) } />
									</Tooltip>
								</div>
							</li>
						) ) }
						{ filtered.length === 0 && <li className="blcb-list__none bl-muted">{ __( 'No actions match your filters.', 'bigloop-call-button' ) }</li> }
					</ul>
				) }
			</Card>

			{ editing && types && <ActionEditor types={ types } lib={ lib } doc={ editing === 'new' ? null : editing } onSaved={ onSaved } onClose={ () => setEditing( null ) } /> }

			{ deleting && (
				<ConfirmDialog
					title={ sprintf( /* translators: %s: action name */ __( 'Delete "%s"?', 'bigloop-call-button' ), deleting.title ) }
					message={
						deleting.used_by.length
							? sprintf(
									/* translators: 1: number of widgets, 2: widget names */
									_n( 'It will be removed from %1$d widget: %2$s. This cannot be undone.', 'It will be removed from %1$d widgets: %2$s. This cannot be undone.', deleting.used_by.length, 'bigloop-call-button' ),
									deleting.used_by.length,
									deleting.used_by.map( ( w ) => w.title ).join( ', ' )
							  )
							: __( 'This cannot be undone.', 'bigloop-call-button' )
					}
					confirmLabel={ __( 'Delete action', 'bigloop-call-button' ) }
					cancelLabel={ __( 'Cancel', 'bigloop-call-button' ) }
					destructive
					busy={ busy }
					onCancel={ () => setDeleting( null ) }
					onConfirm={ remove }
				/>
			) }
		</div>
	);
}
