/**
 * Analytics dashboard: local, privacy-friendly click statistics.
 */
import { __, sprintf, _n } from '@wordpress/i18n';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { Button, Card, PageHeader, EmptyState, Notice, Skeleton, StatCard, Segmented, ConfirmDialog, useToast, cx } from '@bigloop/ui';
import { BarChart3, MousePointer2, TrendingUp, Smartphone, RefreshCw, Trash2, ShieldCheck } from 'lucide-react';
import { get, del, parseError } from '../lib/api';
import { actionTypeLabel, formatNumber } from '../lib/labels';
import { linkHandler } from '../lib/router';

const RANGES = [ 7, 30, 90, 365 ];

function isoDay( date ) {
	const y = date.getFullYear();
	const m = String( date.getMonth() + 1 ).padStart( 2, '0' );
	const d = String( date.getDate() ).padStart( 2, '0' );
	return `${ y }-${ m }-${ d }`;
}

function parseDay( day ) {
	const [ y, m, d ] = day.split( '-' ).map( Number );
	return new Date( y, m - 1, d );
}

function shortDay( day ) {
	return parseDay( day ).toLocaleDateString( undefined, { month: 'short', day: 'numeric' } );
}

export function percentChange( current, previous ) {
	if ( ! previous ) {
		return current ? null : 0;
	}
	return Math.round( ( ( current - previous ) / previous ) * 100 );
}

/**
 * Groups a long daily series into weeks so bars stay readable.
 *
 * @param {Array<{day:string,clicks:number}>} series Daily points.
 * @return {Array<{day:string,label:string,clicks:number}>} Bars.
 */
export function bucketSeries( series ) {
	if ( series.length <= 31 ) {
		return series.map( ( p ) => ( { ...p, label: shortDay( p.day ) } ) );
	}
	const out = [];
	for ( let i = 0; i < series.length; i += 7 ) {
		const chunk = series.slice( i, i + 7 );
		out.push( {
			day: chunk[ 0 ].day,
			label: sprintf( /* translators: 1: first day, 2: last day */ __( '%1$s – %2$s', 'bigloop-call-button' ), shortDay( chunk[ 0 ].day ), shortDay( chunk[ chunk.length - 1 ].day ) ),
			clicks: chunk.reduce( ( s, p ) => s + p.clicks, 0 ),
		} );
	}
	return out;
}

function BarChart( { series } ) {
	const bars = bucketSeries( series );
	const max = Math.max( 1, ...bars.map( ( b ) => b.clicks ) );
	const nice = max <= 5 ? max : Math.ceil( max / 5 ) * 5;
	const box = useRef();
	const [ W, setW ] = useState( 720 );
	useEffect( () => {
		const el = box.current;
		if ( ! el || typeof window.ResizeObserver !== 'function' ) {
			return undefined;
		}
		const ro = new window.ResizeObserver( ( entries ) => setW( Math.max( 280, Math.round( entries[ 0 ].contentRect.width ) ) ) );
		ro.observe( el );
		return () => ro.disconnect();
	}, [] );
	const H = 220;
	const pad = { l: 36, r: 8, t: 10, b: 26 };
	const iw = W - pad.l - pad.r;
	const ih = H - pad.t - pad.b;
	const bw = iw / bars.length;
	const ticks = [ 0, nice / 2, nice ].filter( ( v, i, a ) => Number.isInteger( v ) && a.indexOf( v ) === i );
	const labelEvery = Math.ceil( bars.length / Math.max( 3, Math.floor( W / 90 ) ) );
	const [ active, setActive ] = useState( null );

	return (
		<div className="blcb-chart" ref={ box }>
			<svg viewBox={ `0 0 ${ W } ${ H }` } width={ W } height={ H } role="img" aria-label={ __( 'Clicks over time. The table below lists the same numbers.', 'bigloop-call-button' ) }>
				{ ticks.map( ( t ) => {
					const y = pad.t + ih - ( t / nice ) * ih;
					return (
						<g key={ t } className="blcb-chart__grid">
							<line x1={ pad.l } x2={ W - pad.r } y1={ y } y2={ y } />
							<text x={ pad.l - 6 } y={ y + 4 } textAnchor="end">
								{ formatNumber( t ) }
							</text>
						</g>
					);
				} ) }
				{ bars.map( ( b, i ) => {
					const h = ( b.clicks / nice ) * ih;
					const x = pad.l + i * bw;
					return (
						<g key={ b.day } onMouseEnter={ () => setActive( i ) } onMouseLeave={ () => setActive( null ) }>
							<rect className="blcb-chart__hit" x={ x } y={ pad.t } width={ bw } height={ ih } />
							<rect className={ cx( 'blcb-chart__bar', active === i && 'is-active' ) } x={ x + bw * 0.15 } y={ pad.t + ih - h } width={ Math.max( 1, bw * 0.7 ) } height={ Math.max( b.clicks ? 2 : 0, h ) } rx={ Math.min( 4, bw * 0.2 ) } />
							{ i % labelEvery === 0 && (
								<text className="blcb-chart__label" x={ x + bw / 2 } y={ H - 8 } textAnchor="middle">
									{ bars.length > 31 ? shortDay( b.day ) : b.label }
								</text>
							) }
						</g>
					);
				} ) }
			</svg>
			<p className="blcb-chart__readout" aria-live="polite">
				{ active !== null ? sprintf( /* translators: 1: date or week, 2: number of clicks */ _n( '%1$s: %2$s click', '%1$s: %2$s clicks', bars[ active ].clicks, 'bigloop-call-button' ), bars[ active ].label, formatNumber( bars[ active ].clicks ) ) : ' ' }
			</p>
			<details className="blcb-chart__table">
				<summary>{ __( 'Show as a table', 'bigloop-call-button' ) }</summary>
				<table className="blcb-table">
					<thead>
						<tr>
							<th scope="col">{ bars.length === series.length ? __( 'Day', 'bigloop-call-button' ) : __( 'Week', 'bigloop-call-button' ) }</th>
							<th scope="col" className="is-num">{ __( 'Clicks', 'bigloop-call-button' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ bars.map( ( b ) => (
							<tr key={ b.day }>
								<td>{ b.label }</td>
								<td className="is-num">{ formatNumber( b.clicks ) }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</details>
		</div>
	);
}

function Breakdown( { rows, total, empty } ) {
	if ( ! rows.length ) {
		return <p className="bl-muted">{ empty }</p>;
	}
	return (
		<ul className="blcb-breakdown">
			{ rows.map( ( r ) => {
				const pct = total ? Math.round( ( r.clicks / total ) * 100 ) : 0;
				return (
					<li key={ r.key }>
						<div className="blcb-breakdown__head">
							<span className="blcb-breakdown__label">{ r.label }</span>
							<span className="blcb-breakdown__value">
								{ formatNumber( r.clicks ) } <span className="bl-muted">· { pct }%</span>
							</span>
						</div>
						<span className="blcb-breakdown__bar" aria-hidden="true">
							<span style={ { width: `${ pct }%` } } />
						</span>
					</li>
				);
			} ) }
		</ul>
	);
}

export default function Analytics( { boot, navigate } ) {
	const toast = useToast();
	const [ range, setRange ] = useState( 30 );
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ confirmPurge, setConfirmPurge ] = useState( false );

	const load = useCallback( () => {
		setLoading( true );
		setError( null );
		const to = new Date();
		const from = new Date();
		from.setDate( to.getDate() - ( range - 1 ) );
		get( '/analytics', { from: isoDay( from ), to: isoDay( to ) } )
			.then( setData )
			.catch( ( e ) => setError( parseError( e ).message ) )
			.finally( () => setLoading( false ) );
	}, [ range ] );
	useEffect( load, [ load ] );

	const change = data ? percentChange( data.total, data.previous ) : 0;
	const devices = data ? data.by_device : {};
	const deviceTotal = data ? ( devices.desktop || 0 ) + ( devices.tablet || 0 ) + ( devices.mobile || 0 ) : 0;
	const top = data && data.by_type[ 0 ];
	const settingsLink = boot.pages.settings ? { href: boot.pages.settings.url, onClick: linkHandler( navigate, 'settings' ) } : {};

	let changeHint = null;
	if ( data ) {
		if ( change === null ) {
			changeHint = __( 'No clicks in the previous period', 'bigloop-call-button' );
		} else {
			changeHint = sprintf(
				/* translators: 1: percentage change with sign, 2: number of days */
				__( '%1$s vs the previous %2$d days', 'bigloop-call-button' ),
				( change > 0 ? '+' : '' ) + change + '%',
				data.days
			);
		}
	}

	return (
		<div className="blcb-analytics">
			<PageHeader
				title={ __( 'Analytics', 'bigloop-call-button' ) }
				description={ __( 'How visitors use your contact buttons. Statistics are stored on your own site, without cookies or personal data.', 'bigloop-call-button' ) }
				actions={
					<div className="blcb-analytics__controls">
						<Segmented
							label={ __( 'Period', 'bigloop-call-button' ) }
							hideLabel
							value={ String( range ) }
							onChange={ ( v ) => setRange( Number( v ) ) }
							options={ RANGES.map( ( r ) => ( { value: String( r ), label: r === 365 ? __( '12 months', 'bigloop-call-button' ) : sprintf( /* translators: %d: number of days */ __( '%d days', 'bigloop-call-button' ), r ) } ) ) }
						/>
						<Button variant="ghost" icon={ <RefreshCw /> } onClick={ load } aria-label={ __( 'Refresh', 'bigloop-call-button' ) } />
					</div>
				}
			/>

			{ data && ! data.tracking && (
				<Notice tone="warning" title={ __( 'Click tracking is turned off', 'bigloop-call-button' ) } actions={ <Button size="sm" { ...settingsLink }>{ __( 'Open settings', 'bigloop-call-button' ) }</Button> }>
					{ __( 'New clicks are not being recorded. Earlier statistics are still shown below.', 'bigloop-call-button' ) }
				</Notice>
			) }
			{ error && (
				<Notice tone="danger" actions={ <Button size="sm" onClick={ load }>{ __( 'Try again', 'bigloop-call-button' ) }</Button> }>
					{ error }
				</Notice>
			) }

			<div className="blcb-stats">
				<StatCard loading={ loading && ! data } icon={ <MousePointer2 /> } label={ __( 'Clicks', 'bigloop-call-button' ) } value={ formatNumber( data?.total ) } hint={ changeHint } />
				<StatCard loading={ loading && ! data } tone="info" icon={ <TrendingUp /> } label={ __( 'Daily average', 'bigloop-call-button' ) } value={ data ? new Intl.NumberFormat( undefined, { maximumFractionDigits: 1 } ).format( data.total / data.days ) : '' } hint={ data ? sprintf( /* translators: %d: number of days */ __( 'Over %d days', 'bigloop-call-button' ), data.days ) : null } />
				<StatCard loading={ loading && ! data } tone="success" icon={ <BarChart3 /> } label={ __( 'Most used action', 'bigloop-call-button' ) } value={ top ? actionTypeLabel( top.action_type ) : '—' } hint={ top ? sprintf( /* translators: %s: number */ __( '%s clicks', 'bigloop-call-button' ), formatNumber( top.clicks ) ) : __( 'No clicks yet', 'bigloop-call-button' ) } />
				<StatCard loading={ loading && ! data } tone="warning" icon={ <Smartphone /> } label={ __( 'From mobile', 'bigloop-call-button' ) } value={ deviceTotal ? Math.round( ( ( devices.mobile || 0 ) / deviceTotal ) * 100 ) + '%' : '—' } hint={ __( 'Share of clicks on phones', 'bigloop-call-button' ) } />
			</div>

			<Card title={ __( 'Clicks over time', 'bigloop-call-button' ) } description={ data ? sprintf( /* translators: %s: timezone */ __( 'Days follow your site timezone (%s).', 'bigloop-call-button' ), data.timezone ) : null }>
				{ ! data && loading && <Skeleton height="220px" /> }
				{ data && data.total === 0 && (
					<EmptyState compact icon={ <BarChart3 /> } title={ __( 'No clicks in this period', 'bigloop-call-button' ) }>
						{ __( 'Clicks appear here as soon as visitors use your contact buttons. Your own clicks are not counted while you are logged in as an administrator.', 'bigloop-call-button' ) }
					</EmptyState>
				) }
				{ data && data.total > 0 && <BarChart series={ data.series } /> }
			</Card>

			{ data && data.total > 0 && (
				<div className="blcb-analytics__grid">
					<Card title={ __( 'By action type', 'bigloop-call-button' ) }>
						<Breakdown total={ data.total } empty="" rows={ data.by_type.map( ( r ) => ( { key: r.action_type, label: actionTypeLabel( r.action_type ), clicks: r.clicks } ) ) } />
					</Card>
					<Card title={ __( 'By device', 'bigloop-call-button' ) }>
						<Breakdown
							total={ deviceTotal }
							empty=""
							rows={ [
								{ key: 'mobile', label: __( 'Mobile', 'bigloop-call-button' ), clicks: devices.mobile || 0 },
								{ key: 'tablet', label: __( 'Tablet', 'bigloop-call-button' ), clicks: devices.tablet || 0 },
								{ key: 'desktop', label: __( 'Desktop', 'bigloop-call-button' ), clicks: devices.desktop || 0 },
							] }
						/>
					</Card>
					<Card title={ __( 'By widget', 'bigloop-call-button' ) }>
						<Breakdown total={ data.total } empty="" rows={ data.by_widget.map( ( r ) => ( { key: r.id, label: r.deleted ? sprintf( /* translators: %d: id */ __( 'Deleted widget #%d', 'bigloop-call-button' ), r.id ) : r.title, clicks: r.clicks } ) ) } />
					</Card>
					<Card title={ __( 'Top actions', 'bigloop-call-button' ) }>
						<Breakdown total={ data.total } empty="" rows={ data.by_action.map( ( r ) => ( { key: r.id, label: `${ r.deleted ? sprintf( /* translators: %d: id */ __( 'Deleted action #%d', 'bigloop-call-button' ), r.id ) : r.title } · ${ actionTypeLabel( r.action_type ) }`, clicks: r.clicks } ) ) } />
					</Card>
					<Card
						title={ __( 'Top pages', 'bigloop-call-button' ) }
						description={ data.pages.complete_since && data.pages.complete_since > data.from ? sprintf( /* translators: %s: date */ __( 'Page details are kept for the retention period, so this list covers clicks since %s.', 'bigloop-call-button' ), shortDay( data.pages.complete_since ) ) : null }
					>
						{ data.pages.items.length ? (
							<ol className="blcb-pages">
								{ data.pages.items.map( ( p ) => (
									<li key={ p.id }>
										{ p.url ? (
											<a href={ p.url } target="_blank" rel="noreferrer">
												{ p.title || sprintf( /* translators: %d: id */ __( 'Page #%d', 'bigloop-call-button' ), p.id ) }
											</a>
										) : (
											<span>{ sprintf( /* translators: %d: id */ __( 'Deleted page #%d', 'bigloop-call-button' ), p.id ) }</span>
										) }
										<span className="bl-muted">{ formatNumber( p.clicks ) }</span>
									</li>
								) ) }
							</ol>
						) : (
							<p className="bl-muted">{ __( 'Clicks on archive pages and the home page of a blog are not linked to a page.', 'bigloop-call-button' ) }</p>
						) }
					</Card>
				</div>
			) }

			<Card className="blcb-analytics__privacy">
				<div className="blcb-analytics__privacy-row">
					<ShieldCheck size={ 20 } aria-hidden="true" />
					<p>{ __( 'Each click stores the widget, the action, the device type (desktop, tablet or mobile), the page and the time. No IP addresses, cookies, browser details or personal data are stored, so no consent banner is needed for these statistics.', 'bigloop-call-button' ) }</p>
					<Button variant="ghost" icon={ <Trash2 /> } onClick={ () => setConfirmPurge( true ) }>
						{ __( 'Delete all statistics', 'bigloop-call-button' ) }
					</Button>
				</div>
			</Card>

			{ confirmPurge && (
				<ConfirmDialog
					title={ __( 'Delete all statistics?', 'bigloop-call-button' ) }
					message={ __( 'All recorded clicks will be permanently deleted. Widgets and settings are not affected.', 'bigloop-call-button' ) }
					confirmLabel={ __( 'Delete statistics', 'bigloop-call-button' ) }
					cancelLabel={ __( 'Cancel', 'bigloop-call-button' ) }
					destructive
					onCancel={ () => setConfirmPurge( false ) }
					onConfirm={ () =>
						del( '/analytics' )
							.then( () => {
								setConfirmPurge( false );
								toast.success( __( 'Statistics deleted.', 'bigloop-call-button' ) );
								load();
							} )
							.catch( ( e ) => toast.error( parseError( e ).message ) )
					}
				/>
			) }
		</div>
	);
}
