/**
 * Overview dashboard.
 */
import { __, sprintf, _n } from '@wordpress/i18n';
import { useEffect, useState, useCallback } from '@wordpress/element';
import { Button, Card, StatCard, Badge, EmptyState, Notice, Skeleton } from '@bigloop/ui';
import { LayoutGrid, Zap, MousePointerClick, Trophy, Plus, BookOpen, LifeBuoy, Globe, Activity, Layers, Sparkles, Settings as SettingsIcon, RefreshCw } from 'lucide-react';
import { get, parseError } from '../lib/api';
import { linkHandler } from '../lib/router';
import { actionTypeLabel, deviceLabel, relativeTime, formatNumber } from '../lib/labels';

export default function Overview( { boot, navigate } ) {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const load = useCallback( () => {
		setLoading( true );
		setError( null );
		get( '/overview' )
			.then( setData )
			.catch( ( e ) => setError( parseError( e ).message ) )
			.finally( () => setLoading( false ) );
	}, [] );

	useEffect( load, [ load ] );

	const link = ( route ) => ( { href: boot.pages[ route ]?.url, onClick: linkHandler( navigate, route ) } );
	const top = data?.clicks?.top_action;
	const hasWidgets = ( data?.widgets?.total || 0 ) > 0;

	return (
		<div className="blcb-overview">
			<section className="blcb-hero" aria-labelledby="blcb-hero-title">
				<div className="blcb-hero__text">
					<span className="blcb-hero__eyebrow">{ __( 'Overview', 'bigloop-call-button' ) }</span>
					<h1 id="blcb-hero-title" className="blcb-hero__title">
						{ boot.user?.displayName
							? sprintf( /* translators: %s: user display name */ __( 'Welcome, %s', 'bigloop-call-button' ), boot.user.displayName )
							: __( 'Welcome', 'bigloop-call-button' ) }
					</h1>
					<p className="blcb-hero__lead">
						{ __( 'Help visitors reach you in one tap. Build floating buttons, expandable contact menus and sticky contact bars, with no account, no limits, and your data staying on your site.', 'bigloop-call-button' ) }
					</p>
				</div>
				<div className="blcb-hero__actions">
					<Button variant="primary" icon={ <Plus /> } { ...link( 'new' ) }>
						{ __( 'Create widget', 'bigloop-call-button' ) }
					</Button>
					<Button variant="secondary" icon={ <Layers /> } { ...link( 'widgets' ) }>
						{ __( 'Manage widgets', 'bigloop-call-button' ) }
					</Button>
					<Button variant="ghost" icon={ <SettingsIcon /> } { ...link( 'settings' ) }>
						{ __( 'Settings', 'bigloop-call-button' ) }
					</Button>
				</div>
			</section>

			{ error && (
				<Notice
					tone="danger"
					title={ __( 'The dashboard could not load.', 'bigloop-call-button' ) }
					actions={
						<Button size="sm" icon={ <RefreshCw /> } onClick={ load }>
							{ __( 'Try again', 'bigloop-call-button' ) }
						</Button>
					}
				>
					{ error }
				</Notice>
			) }

			{ data?.system?.db_error && (
				<Notice tone="warning" title={ __( 'Database update needs attention', 'bigloop-call-button' ) }>
					{ sprintf(
						/* translators: 1: schema version, 2: database error */
						__( 'Updating the database to schema version %1$d failed: %2$s. Deactivate and reactivate the plugin to retry, or contact your host if the problem continues.', 'bigloop-call-button' ),
						data.system.db_error.version,
						data.system.db_error.error
					) }
				</Notice>
			) }

			<div className="blcb-stats" role="list">
				<div role="listitem">
					<StatCard
						loading={ loading }
						icon={ <LayoutGrid /> }
						label={ __( 'Active widgets', 'bigloop-call-button' ) }
						value={ formatNumber( data?.widgets?.enabled ) }
						hint={ data ? sprintf( /* translators: %d: number of widgets */ _n( '%d widget in total', '%d widgets in total', data.widgets.total, 'bigloop-call-button' ), data.widgets.total ) : null }
					/>
				</div>
				<div role="listitem">
					<StatCard loading={ loading } tone="info" icon={ <Zap /> } label={ __( 'Contact actions', 'bigloop-call-button' ) } value={ formatNumber( data?.actions?.total ) } hint={ __( 'Phone, WhatsApp, email and more', 'bigloop-call-button' ) } />
				</div>
				<div role="listitem">
					<StatCard
						loading={ loading }
						tone="success"
						icon={ <MousePointerClick /> }
						label={ __( 'Total clicks', 'bigloop-call-button' ) }
						value={ formatNumber( data?.clicks?.total ) }
						hint={ data ? sprintf( /* translators: %s: number of clicks */ __( '%s in the last 30 days', 'bigloop-call-button' ), formatNumber( data.clicks.last_30 ) ) : null }
					/>
				</div>
				<div role="listitem">
					<StatCard
						loading={ loading }
						tone="warning"
						icon={ <Trophy /> }
						label={ __( 'Most popular action', 'bigloop-call-button' ) }
						value={ top ? actionTypeLabel( top.action_type ) : '-' }
						hint={ top ? sprintf( /* translators: %s: number of clicks */ __( '%s clicks · last 30 days', 'bigloop-call-button' ), formatNumber( top.clicks ) ) : __( 'No clicks yet', 'bigloop-call-button' ) }
					/>
				</div>
			</div>

			<div className="blcb-grid">
				<div className="blcb-grid__main">
					<Card
						title={ __( 'Widget status', 'bigloop-call-button' ) }
						description={ __( 'Floating buttons, menus and contact bars on your site.', 'bigloop-call-button' ) }
						actions={
							hasWidgets && (
								<Button size="sm" variant="ghost" { ...link( 'widgets' ) }>
									{ __( 'View all', 'bigloop-call-button' ) }
								</Button>
							)
						}
					>
						{ loading && <Skeleton height="64px" /> }
						{ ! loading && data && ! hasWidgets && (
							<EmptyState
								compact
								icon={ <Layers /> }
								title={ __( 'No contact widgets yet', 'bigloop-call-button' ) }
								action={
									<Button variant="primary" icon={ <Plus /> } { ...link( 'new' ) }>
										{ __( 'Create your first widget', 'bigloop-call-button' ) }
									</Button>
								}
							>
								{ __( 'Create a call button, a WhatsApp button or a full contact bar. Each widget can have as many actions as you like.', 'bigloop-call-button' ) }
							</EmptyState>
						) }
						{ ! loading && data && hasWidgets && (
							<div className="blcb-status-row">
								<div className="blcb-status-row__item">
									<Badge tone="success" dot>
										{ __( 'Enabled', 'bigloop-call-button' ) }
									</Badge>
									<strong>{ formatNumber( data.widgets.enabled ) }</strong>
								</div>
								<div className="blcb-status-row__item">
									<Badge tone="neutral" dot>
										{ __( 'Disabled', 'bigloop-call-button' ) }
									</Badge>
									<strong>{ formatNumber( data.widgets.disabled ) }</strong>
								</div>
							</div>
						) }
					</Card>

					<Card title={ __( 'Recent activity', 'bigloop-call-button' ) } description={ __( 'The latest visitor clicks on your contact actions.', 'bigloop-call-button' ) }>
						{ loading && (
							<div className="blcb-stack">
								<Skeleton height="18px" />
								<Skeleton height="18px" width="80%" />
								<Skeleton height="18px" width="60%" />
							</div>
						) }
						{ ! loading && data && data.recent.length === 0 && (
							<EmptyState compact icon={ <Activity /> } title={ __( 'No clicks recorded yet', 'bigloop-call-button' ) }>
								{ __( 'Clicks appear here once a widget is live. Analytics are stored on your own site only.', 'bigloop-call-button' ) }
							</EmptyState>
						) }
						{ ! loading && data && data.recent.length > 0 && (
							<ul className="blcb-activity">
								{ data.recent.map( ( e, i ) => (
									<li key={ i } className="blcb-activity__item">
										<span className="blcb-activity__what">
											<strong>{ actionTypeLabel( e.action_type ) }</strong>
											{ e.widget_title && <span className="bl-muted"> · { e.widget_title }</span> }
										</span>
										<span className="blcb-activity__meta bl-muted">
											{ deviceLabel( e.device ) } · <time dateTime={ e.created_at.replace( ' ', 'T' ) + 'Z' }>{ relativeTime( e.created_at ) }</time>
										</span>
									</li>
								) ) }
							</ul>
						) }
					</Card>
				</div>

				<aside className="blcb-grid__side">
					<Card title={ __( 'Your edition', 'bigloop-call-button' ) }>
						<div className="blcb-edition">
							<div className="blcb-edition__row">
								<span>{ __( 'Bigloop Call Button & Contact Bar', 'bigloop-call-button' ) }</span>
								<Badge tone="success">{ __( 'Free · Active', 'bigloop-call-button' ) }</Badge>
							</div>
							<div className="blcb-edition__row">
								<span>{ __( 'Pro add-on', 'bigloop-call-button' ) }</span>
								{ data?.pro?.active ? <Badge tone="accent">{ __( 'Active', 'bigloop-call-button' ) }</Badge> : <Badge tone="neutral">{ __( 'Not installed', 'bigloop-call-button' ) }</Badge> }
							</div>
							<p className="bl-muted blcb-edition__note">{ __( 'Every Free feature works without an account or licence key.', 'bigloop-call-button' ) }</p>
							{ ! data?.pro?.active && (
								<a className="blcb-edition__more" { ...link( 'pro' ) }>
									<Sparkles size={ 14 } aria-hidden="true" /> { __( 'What does Pro add?', 'bigloop-call-button' ) }
								</a>
							) }
						</div>
					</Card>

					<Card title={ __( 'Resources', 'bigloop-call-button' ) }>
						<ul className="blcb-links">
							<li>
								<a { ...link( 'help' ) }>
									<BookOpen size={ 16 } aria-hidden="true" /> { __( 'Help & getting started', 'bigloop-call-button' ) }
								</a>
							</li>
							<li>
								<a href={ boot.links.support } target="_blank" rel="noopener noreferrer">
									<LifeBuoy size={ 16 } aria-hidden="true" /> { __( 'Support forum', 'bigloop-call-button' ) }
									<span className="bl-visually-hidden">{ __( '(opens in a new tab)', 'bigloop-call-button' ) }</span>
								</a>
							</li>
							<li>
								<a href={ boot.links.website } target="_blank" rel="noopener noreferrer">
									<Globe size={ 16 } aria-hidden="true" /> bigloop.dev
									<span className="bl-visually-hidden">{ __( '(opens in a new tab)', 'bigloop-call-button' ) }</span>
								</a>
							</li>
						</ul>
					</Card>

					{ data?.system && (
						<Card title={ __( 'System', 'bigloop-call-button' ) }>
							<dl className="blcb-system">
								<dt>{ __( 'Plugin', 'bigloop-call-button' ) }</dt>
								<dd>{ data.system.plugin_version }</dd>
								<dt>{ __( 'Database schema', 'bigloop-call-button' ) }</dt>
								<dd>
									{ data.system.db_version } / { data.system.db_latest }{ ' ' }
									{ data.system.db_version >= data.system.db_latest ? <Badge tone="success">{ __( 'Up to date', 'bigloop-call-button' ) }</Badge> : <Badge tone="warning">{ __( 'Update pending', 'bigloop-call-button' ) }</Badge> }
								</dd>
								<dt>WordPress</dt>
								<dd>{ data.system.wp_version }</dd>
								<dt>PHP</dt>
								<dd>{ data.system.php_version }</dd>
							</dl>
						</Card>
					) }
				</aside>
			</div>
		</div>
	);
}
