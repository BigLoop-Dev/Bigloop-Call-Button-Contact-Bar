/**
 * Widget manager.
 */
import { __, sprintf, _n } from '@wordpress/i18n';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { Button, Card, PageHeader, EmptyState, Notice, Skeleton, Badge, ConfirmDialog, Tooltip, useToast, cx } from '@bigloop/ui';
import { Plus, Pencil, Copy, Trash2, Eye, Layers, Search, ArrowUp, ArrowDown, RefreshCw, MousePointer2, Menu as MenuIcon, PanelBottom } from 'lucide-react';
import { get, post, del, parseError } from '../lib/api';
import { linkHandler, urlFor } from '../lib/router';
import { formatNumber } from '../lib/labels';

const PER_PAGE = 20;

export function widgetTypeLabel( type ) {
	return (
		{
			button: __( 'Single button', 'bigloop-call-button' ),
			menu: __( 'Expandable menu', 'bigloop-call-button' ),
			bar: __( 'Contact bar', 'bigloop-call-button' ),
		}[ type ] || type
	);
}

export function WidgetTypeIcon( { type, size = 18 } ) {
	const Icon = { button: MousePointer2, menu: MenuIcon, bar: PanelBottom }[ type ] || Layers;
	return <Icon size={ size } aria-hidden="true" />;
}

function rulesLabel( config ) {
	const items = ( config.rules && config.rules.items ) || [];
	if ( ! items.length ) {
		return __( 'Entire site', 'bigloop-call-button' );
	}
	if ( items.length === 1 ) {
		return __( 'Pages matching 1 condition', 'bigloop-call-button' );
	}
	return config.rules.match === 'any'
		? sprintf( /* translators: %d: number of conditions */ __( 'Pages matching any of %d conditions', 'bigloop-call-button' ), items.length )
		: sprintf( /* translators: %d: number of conditions */ __( 'Pages matching all %d conditions', 'bigloop-call-button' ), items.length );
}

function devicesLabel( devices ) {
	const names = {
		desktop: __( 'Desktop', 'bigloop-call-button' ),
		tablet: __( 'Tablet', 'bigloop-call-button' ),
		mobile: __( 'Mobile', 'bigloop-call-button' ),
	};
	if ( devices.length === 3 ) {
		return __( 'All devices', 'bigloop-call-button' );
	}
	if ( devices.length === 0 ) {
		return __( 'No devices', 'bigloop-call-button' );
	}
	return devices.map( ( d ) => names[ d ] ).join( ', ' );
}

export default function Widgets( { boot, navigate } ) {
	const toast = useToast();
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ query, setQuery ] = useState( { search: '', type: '', status: '', orderby: 'order', order: 'asc', page: 1 } );
	const [ searchText, setSearchText ] = useState( '' );
	const [ deleting, setDeleting ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	const load = useCallback( () => {
		setError( null );
		get( '/widgets', { ...query, per_page: PER_PAGE } )
			.then( setData )
			.catch( ( e ) => setError( parseError( e ).message ) );
	}, [ query ] );
	useEffect( load, [ load ] );

	// Debounced search.
	useEffect( () => {
		const t = setTimeout( () => setQuery( ( q ) => ( q.search === searchText ? q : { ...q, search: searchText, page: 1 } ) ), 300 );
		return () => clearTimeout( t );
	}, [ searchText ] );

	const setFilter = ( key, value ) => setQuery( ( q ) => ( { ...q, [ key ]: value, page: 1 } ) );
	const editLink = ( id ) => ( { href: urlFor( boot.pages, 'new', { widget: id } ), onClick: linkHandler( navigate, 'new', { widget: id } ) } );
	const replaceItem = ( saved ) => setData( ( d ) => ( { ...d, items: d.items.map( ( x ) => ( x.id === saved.id ? saved : x ) ) } ) );

	const toggle = ( w ) => {
		post( `/widgets/${ w.id }/status`, { enabled: ! w.enabled } )
			.then( ( saved ) => {
				replaceItem( saved );
				toast.success( saved.enabled ? __( 'Widget enabled. It is now live on your site.', 'bigloop-call-button' ) : __( 'Widget disabled.', 'bigloop-call-button' ) );
			} )
			.catch( ( e ) => {
				const { message, fieldErrors } = parseError( e );
				toast.error( fieldErrors.actions || message );
			} );
	};

	const duplicate = ( w ) => {
		post( `/widgets/${ w.id }/duplicate` )
			.then( () => {
				toast.success( __( 'Widget duplicated. The copy is disabled until you enable it.', 'bigloop-call-button' ) );
				load();
			} )
			.catch( ( e ) => toast.error( parseError( e ).message ) );
	};

	const remove = () => {
		setBusy( true );
		del( `/widgets/${ deleting.id }` )
			.then( () => {
				toast.success( __( 'Widget deleted.', 'bigloop-call-button' ) );
				setDeleting( null );
				load();
			} )
			.catch( ( e ) => toast.error( parseError( e ).message ) )
			.finally( () => setBusy( false ) );
	};

	const move = ( index, dir ) => {
		const items = [ ...data.items ];
		const target = index + dir;
		if ( target < 0 || target >= items.length ) {
			return;
		}
		[ items[ index ], items[ target ] ] = [ items[ target ], items[ index ] ];
		setData( ( d ) => ( { ...d, items } ) );
		post( '/widgets/reorder', { ids: items.map( ( x ) => x.id ) } ).catch( ( e ) => {
			toast.error( parseError( e ).message );
			load();
		} );
	};

	const filtersActive = query.search || query.type || query.status;
	const total = data ? data.total : 0;
	const pages = Math.max( 1, Math.ceil( total / PER_PAGE ) );
	const canReorder = query.orderby === 'order' && ! filtersActive && pages === 1;
	const sortValue = `${ query.orderby }:${ query.order }`;

	return (
		<div className="blcb-widgets-screen">
			<PageHeader
				title={ __( 'Contact Widgets', 'bigloop-call-button' ) }
				description={ __( 'Floating buttons, expandable menus and contact bars. There is no limit on how many you create.', 'bigloop-call-button' ) }
				actions={
					<Button variant="primary" icon={ <Plus /> } href={ urlFor( boot.pages, 'new' ) } onClick={ linkHandler( navigate, 'new' ) }>
						{ __( 'Create widget', 'bigloop-call-button' ) }
					</Button>
				}
			/>

			{ error && (
				<Notice tone="danger" title={ __( 'Widgets could not be loaded.', 'bigloop-call-button' ) } actions={ <Button size="sm" icon={ <RefreshCw /> } onClick={ load }>{ __( 'Try again', 'bigloop-call-button' ) }</Button> }>
					{ error }
				</Notice>
			) }

			<Card padded={ false }>
				{ ( ( data && data.total > 0 ) || filtersActive ) && (
					<div className="blcb-toolbar">
						<label className="blcb-search">
							<Search size={ 16 } aria-hidden="true" />
							<span className="bl-visually-hidden">{ __( 'Search widgets', 'bigloop-call-button' ) }</span>
							<input type="search" className="bl-input" placeholder={ __( 'Search widgets…', 'bigloop-call-button' ) } value={ searchText } onChange={ ( e ) => setSearchText( e.target.value ) } />
						</label>
						<label className="blcb-filter">
							<span className="bl-visually-hidden">{ __( 'Filter by type', 'bigloop-call-button' ) }</span>
							<select className="bl-select" value={ query.type } onChange={ ( e ) => setFilter( 'type', e.target.value ) }>
								<option value="">{ __( 'All types', 'bigloop-call-button' ) }</option>
								<option value="button">{ widgetTypeLabel( 'button' ) }</option>
								<option value="menu">{ widgetTypeLabel( 'menu' ) }</option>
								<option value="bar">{ widgetTypeLabel( 'bar' ) }</option>
							</select>
						</label>
						<label className="blcb-filter">
							<span className="bl-visually-hidden">{ __( 'Filter by status', 'bigloop-call-button' ) }</span>
							<select className="bl-select" value={ query.status } onChange={ ( e ) => setFilter( 'status', e.target.value ) }>
								<option value="">{ __( 'Any status', 'bigloop-call-button' ) }</option>
								<option value="enabled">{ __( 'Enabled', 'bigloop-call-button' ) }</option>
								<option value="disabled">{ __( 'Disabled', 'bigloop-call-button' ) }</option>
							</select>
						</label>
						<label className="blcb-filter">
							<span className="bl-visually-hidden">{ __( 'Sort by', 'bigloop-call-button' ) }</span>
							<select
								className="bl-select"
								value={ sortValue }
								onChange={ ( e ) => {
									const [ orderby, order ] = e.target.value.split( ':' );
									setQuery( ( q ) => ( { ...q, orderby, order, page: 1 } ) );
								} }
							>
								<option value="order:asc">{ __( 'Custom order', 'bigloop-call-button' ) }</option>
								<option value="title:asc">{ __( 'Name A–Z', 'bigloop-call-button' ) }</option>
								<option value="date:desc">{ __( 'Newest first', 'bigloop-call-button' ) }</option>
								<option value="date:asc">{ __( 'Oldest first', 'bigloop-call-button' ) }</option>
								<option value="clicks:desc">{ __( 'Most clicks', 'bigloop-call-button' ) }</option>
							</select>
						</label>
					</div>
				) }

				{ ! data && ! error && (
					<div className="blcb-stack" style={ { padding: 24 } }>
						<Skeleton height="52px" />
						<Skeleton height="52px" />
					</div>
				) }

				{ data && data.total === 0 && ! filtersActive && (
					<EmptyState
						icon={ <Layers /> }
						title={ __( 'No contact widgets yet', 'bigloop-call-button' ) }
						action={
							<Button variant="primary" icon={ <Plus /> } href={ urlFor( boot.pages, 'new' ) } onClick={ linkHandler( navigate, 'new' ) }>
								{ __( 'Create your first widget', 'bigloop-call-button' ) }
							</Button>
						}
					>
						{ __( 'Start with a floating call button, a WhatsApp button, a menu of several contact options, or a sticky contact bar for mobile.', 'bigloop-call-button' ) }
					</EmptyState>
				) }

				{ data && ( data.total > 0 || filtersActive ) && (
					<div className="blcb-table-wrap">
						<table className="blcb-table">
							<thead>
								<tr>
									<th scope="col">{ __( 'Widget', 'bigloop-call-button' ) }</th>
									<th scope="col">{ __( 'Actions', 'bigloop-call-button' ) }</th>
									<th scope="col">{ __( 'Shows on', 'bigloop-call-button' ) }</th>
									<th scope="col" className="is-num">{ __( 'Clicks', 'bigloop-call-button' ) }</th>
									<th scope="col">{ __( 'Created', 'bigloop-call-button' ) }</th>
									<th scope="col">{ __( 'Status', 'bigloop-call-button' ) }</th>
									<th scope="col"><span className="bl-visually-hidden">{ __( 'Manage', 'bigloop-call-button' ) }</span></th>
								</tr>
							</thead>
							<tbody>
								{ data.items.map( ( w, i ) => (
									<tr key={ w.id } className={ cx( ! w.enabled && 'is-disabled' ) }>
										<td>
											<div className="blcb-table__name">
												<span className="blcb-table__typeicon">
													<WidgetTypeIcon type={ w.type } />
												</span>
												<div>
													<a className="blcb-table__title" { ...editLink( w.id ) }>
														{ w.title }
													</a>
													<span className="blcb-table__sub">{ widgetTypeLabel( w.type ) }</span>
												</div>
											</div>
										</td>
										<td>{ w.actions_count === 0 ? <Badge tone="warning">{ __( 'None', 'bigloop-call-button' ) }</Badge> : formatNumber( w.actions_count ) }</td>
										<td>
											<span className="blcb-table__sub blcb-table__sub--strong">{ rulesLabel( w.config ) }</span>
											<span className="blcb-table__sub">{ devicesLabel( w.devices ) }</span>
											{ w.config.schedule && w.config.schedule.enabled && (
												<Badge tone="info">{ w.config.schedule.when_closed === 'hide' ? __( 'Opening hours only', 'bigloop-call-button' ) : __( 'Follows a schedule', 'bigloop-call-button' ) }</Badge>
											) }
										</td>
										<td className="is-num">{ formatNumber( w.clicks ) }</td>
										<td>
											<time dateTime={ w.created }>{ new Date( w.created ).toLocaleDateString() }</time>
										</td>
										<td>
											<button
												type="button"
												role="switch"
												aria-checked={ w.enabled }
												className={ cx( 'bl-switch', w.enabled && 'is-on' ) }
												onClick={ () => toggle( w ) }
												aria-label={ sprintf( /* translators: %s: widget name */ __( 'Enable %s', 'bigloop-call-button' ), w.title ) }
											>
												<span className="bl-switch__thumb" aria-hidden="true" />
											</button>
										</td>
										<td>
											<div className="blcb-row-actions">
												{ canReorder && (
													<>
														<Tooltip text={ __( 'Move up', 'bigloop-call-button' ) }>
															<Button size="sm" variant="ghost" icon={ <ArrowUp /> } disabled={ i === 0 } onClick={ () => move( i, -1 ) } aria-label={ sprintf( /* translators: %s: widget name */ __( 'Move %s up', 'bigloop-call-button' ), w.title ) } />
														</Tooltip>
														<Tooltip text={ __( 'Move down', 'bigloop-call-button' ) }>
															<Button size="sm" variant="ghost" icon={ <ArrowDown /> } disabled={ i === data.items.length - 1 } onClick={ () => move( i, 1 ) } aria-label={ sprintf( /* translators: %s: widget name */ __( 'Move %s down', 'bigloop-call-button' ), w.title ) } />
														</Tooltip>
													</>
												) }
												<Tooltip text={ __( 'Edit', 'bigloop-call-button' ) }>
													<Button size="sm" variant="ghost" icon={ <Pencil /> } { ...editLink( w.id ) } aria-label={ sprintf( /* translators: %s: widget name */ __( 'Edit %s', 'bigloop-call-button' ), w.title ) } />
												</Tooltip>
												<Tooltip text={ __( 'Preview on site', 'bigloop-call-button' ) }>
													<Button size="sm" variant="ghost" icon={ <Eye /> } href={ w.preview_url } target="_blank" rel="noopener noreferrer" aria-label={ sprintf( /* translators: %s: widget name */ __( 'Preview %s on your site (opens in a new tab)', 'bigloop-call-button' ), w.title ) } disabled={ w.actions_count === 0 } />
												</Tooltip>
												<Tooltip text={ __( 'Duplicate', 'bigloop-call-button' ) }>
													<Button size="sm" variant="ghost" icon={ <Copy /> } onClick={ () => duplicate( w ) } aria-label={ sprintf( /* translators: %s: widget name */ __( 'Duplicate %s', 'bigloop-call-button' ), w.title ) } />
												</Tooltip>
												<Tooltip text={ __( 'Delete', 'bigloop-call-button' ) }>
													<Button size="sm" variant="ghost" icon={ <Trash2 /> } onClick={ () => setDeleting( w ) } aria-label={ sprintf( /* translators: %s: widget name */ __( 'Delete %s', 'bigloop-call-button' ), w.title ) } />
												</Tooltip>
											</div>
										</td>
									</tr>
								) ) }
								{ data.items.length === 0 && (
									<tr>
										<td colSpan={ 7 } className="blcb-table__none bl-muted">
											{ __( 'No widgets match your filters.', 'bigloop-call-button' ) }
										</td>
									</tr>
								) }
							</tbody>
						</table>
					</div>
				) }

				{ data && pages > 1 && (
					<nav className="blcb-pagination" aria-label={ __( 'Pagination', 'bigloop-call-button' ) }>
						<span className="bl-muted">
							{ sprintf( /* translators: 1: current page, 2: total pages, 3: total widgets */ __( 'Page %1$d of %2$d · %3$d widgets', 'bigloop-call-button' ), query.page, pages, total ) }
						</span>
						<div>
							<Button size="sm" disabled={ query.page <= 1 } onClick={ () => setQuery( ( q ) => ( { ...q, page: q.page - 1 } ) ) }>
								{ __( 'Previous', 'bigloop-call-button' ) }
							</Button>
							<Button size="sm" disabled={ query.page >= pages } onClick={ () => setQuery( ( q ) => ( { ...q, page: q.page + 1 } ) ) }>
								{ __( 'Next', 'bigloop-call-button' ) }
							</Button>
						</div>
					</nav>
				) }
			</Card>

			{ deleting && (
				<ConfirmDialog
					title={ sprintf( /* translators: %s: widget name */ __( 'Delete "%s"?', 'bigloop-call-button' ), deleting.title ) }
					message={ __( 'The widget will be removed from your site. Its actions stay in your library. This cannot be undone.', 'bigloop-call-button' ) }
					confirmLabel={ __( 'Delete widget', 'bigloop-call-button' ) }
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
