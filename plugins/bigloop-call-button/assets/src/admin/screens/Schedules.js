/**
 * Schedules library + editor.
 */
import { __, sprintf, _n } from '@wordpress/i18n';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { Button, Card, PageHeader, EmptyState, Notice, Skeleton, Badge, ConfirmDialog, Modal, TextField, Tooltip, useToast, cx } from '@bigloop/ui';
import { Plus, Pencil, Copy, Trash2, CalendarClock, X, RefreshCw } from 'lucide-react';
import { get, post, put, del, parseError } from '../lib/api';

const DAYS = () => [
	[ 'mon', __( 'Monday', 'bigloop-call-button' ) ],
	[ 'tue', __( 'Tuesday', 'bigloop-call-button' ) ],
	[ 'wed', __( 'Wednesday', 'bigloop-call-button' ) ],
	[ 'thu', __( 'Thursday', 'bigloop-call-button' ) ],
	[ 'fri', __( 'Friday', 'bigloop-call-button' ) ],
	[ 'sat', __( 'Saturday', 'bigloop-call-button' ) ],
	[ 'sun', __( 'Sunday', 'bigloop-call-button' ) ],
];

export function defaultSchedule() {
	const work = [ [ '09:00', '17:00' ] ];
	return { timezone: '', weekly: { mon: work, tue: work, wed: work, thu: work, fri: work, sat: [], sun: [] }, date_range: { from: '', to: '' }, holidays: [], special: [] };
}

export function formatIntervals( intervals ) {
	return intervals.length ? intervals.map( ( [ a, b ] ) => `${ a }–${ b }` ).join( ', ' ) : __( 'Closed', 'bigloop-call-button' );
}

function Intervals( { value, onChange, errorPrefix, errors } ) {
	return (
		<div className="blcb-intervals">
			{ value.map( ( [ a, b ], i ) => (
				<span key={ i } className={ cx( 'blcb-interval', errors[ `${ errorPrefix }.${ i }` ] && 'has-error' ) }>
					<input type="time" className="bl-input" value={ a } aria-label={ __( 'Opens', 'bigloop-call-button' ) } onChange={ ( e ) => onChange( value.map( ( p, j ) => ( j === i ? [ e.target.value, p[ 1 ] ] : p ) ) ) } />
					<span aria-hidden="true">–</span>
					<input type="time" className="bl-input" value={ b === '24:00' ? '23:59' : b } aria-label={ __( 'Closes', 'bigloop-call-button' ) } onChange={ ( e ) => onChange( value.map( ( p, j ) => ( j === i ? [ p[ 0 ], e.target.value === '23:59' ? '24:00' : e.target.value ] : p ) ) ) } />
					<button type="button" className="blcb-interval__remove" onClick={ () => onChange( value.filter( ( p, j ) => j !== i ) ) } aria-label={ __( 'Remove these hours', 'bigloop-call-button' ) }>
						<X size={ 14 } aria-hidden="true" />
					</button>
				</span>
			) ) }
			<button type="button" className="blcb-linkbtn" onClick={ () => onChange( [ ...value, value.length ? [ '13:00', '17:00' ] : [ '09:00', '17:00' ] ] ) }>
				<Plus size={ 14 } aria-hidden="true" /> { value.length ? __( 'Add hours', 'bigloop-call-button' ) : __( 'Open this day', 'bigloop-call-button' ) }
			</button>
		</div>
	);
}

export function ScheduleEditor( { doc, onSaved, onClose } ) {
	const toast = useToast();
	const [ title, setTitle ] = useState( doc ? doc.title : __( 'Business hours', 'bigloop-call-button' ) );
	const [ config, setConfig ] = useState( doc ? doc.config : defaultSchedule() );
	const [ meta, setMeta ] = useState( null );
	const [ summary, setSummary ] = useState( doc ? doc.summary : null );
	const [ errors, setErrors ] = useState( {} );
	const [ saving, setSaving ] = useState( false );
	const timer = useRef();

	useEffect( () => {
		get( '/schedules/meta' ).then( setMeta ).catch( () => setMeta( { site_timezone: '', timezones: [] } ) );
	}, [] );

	useEffect( () => {
		clearTimeout( timer.current );
		timer.current = setTimeout( () => {
			post( '/schedules/preview', { config } )
				.then( setSummary )
				.catch( () => {} );
		}, 300 );
		return () => clearTimeout( timer.current );
	}, [ config ] );

	const set = ( key, value ) => setConfig( ( c ) => ( { ...c, [ key ]: value } ) );
	const setDay = ( day, value ) => setConfig( ( c ) => ( { ...c, weekly: { ...c.weekly, [ day ]: value } } ) );
	const copyMonday = () => setConfig( ( c ) => ( { ...c, weekly: { ...c.weekly, tue: c.weekly.mon, wed: c.weekly.mon, thu: c.weekly.mon, fri: c.weekly.mon } } ) );

	const save = () => {
		setSaving( true );
		( doc ? put( `/schedules/${ doc.id }`, { title, config } ) : post( '/schedules', { title, config } ) )
			.then( ( saved ) => {
				toast.success( doc ? __( 'Schedule saved.', 'bigloop-call-button' ) : __( 'Schedule created.', 'bigloop-call-button' ) );
				onSaved( saved );
			} )
			.catch( ( e ) => {
				const { message, fieldErrors } = parseError( e );
				setErrors( fieldErrors );
				toast.error( message );
			} )
			.finally( () => setSaving( false ) );
	};

	return (
		<Modal
			title={ doc ? __( 'Edit schedule', 'bigloop-call-button' ) : __( 'New schedule', 'bigloop-call-button' ) }
			onClose={ onClose }
			size="lg"
			closeLabel={ __( 'Close', 'bigloop-call-button' ) }
			footer={
				<>
					<Button variant="secondary" onClick={ onClose }>
						{ __( 'Cancel', 'bigloop-call-button' ) }
					</Button>
					<Button variant="primary" onClick={ save } busy={ saving }>
						{ doc ? __( 'Save schedule', 'bigloop-call-button' ) : __( 'Create schedule', 'bigloop-call-button' ) }
					</Button>
				</>
			}
		>
			<div className="blcb-sched">
				<div className="blcb-sched__main">
					<div className="blcb-row">
						<TextField label={ __( 'Name', 'bigloop-call-button' ) } value={ title } onChange={ setTitle } error={ errors.title } maxLength={ 100 } />
						<div className="bl-field">
							<label className="bl-field__label" htmlFor="blcb-tz">
								{ __( 'Timezone', 'bigloop-call-button' ) }
							</label>
							<select id="blcb-tz" className="bl-select" value={ config.timezone } onChange={ ( e ) => set( 'timezone', e.target.value ) }>
								<option value="">{ sprintf( /* translators: %s: timezone */ __( 'Site timezone (%s)', 'bigloop-call-button' ), meta ? meta.site_timezone : '…' ) }</option>
								{ ( meta ? meta.timezones : [] ).map( ( tz ) => (
									<option key={ tz } value={ tz }>
										{ tz.replace( /_/g, ' ' ) }
									</option>
								) ) }
							</select>
							{ errors.timezone && <p className="bl-field__error">{ errors.timezone }</p> }
						</div>
					</div>

					<h3 className="blcb-section-title">{ __( 'Weekly hours', 'bigloop-call-button' ) }</h3>
					<div className="blcb-week">
						{ DAYS().map( ( [ day, label ] ) => (
							<div key={ day } className="blcb-week__row">
								<span className="blcb-week__day">{ label }</span>
								<Intervals value={ config.weekly[ day ] || [] } onChange={ ( v ) => setDay( day, v ) } errorPrefix={ `weekly.${ day }` } errors={ errors } />
							</div>
						) ) }
					</div>
					<button type="button" className="blcb-linkbtn" onClick={ copyMonday }>
						{ __( 'Copy Monday\'s hours to Tuesday–Friday', 'bigloop-call-button' ) }
					</button>
					<p className="bl-field__help">{ __( 'A closing time earlier than the opening time runs past midnight (e.g. 20:00–02:00).', 'bigloop-call-button' ) }</p>

					<h3 className="blcb-section-title">{ __( 'Holidays & closures', 'bigloop-call-button' ) }</h3>
					{ config.holidays.map( ( h, i ) => (
						<div key={ i } className="blcb-dated">
							<input type="date" className="bl-input" value={ h.from } aria-label={ __( 'First closed day', 'bigloop-call-button' ) } onChange={ ( e ) => set( 'holidays', config.holidays.map( ( x, j ) => ( j === i ? { ...x, from: e.target.value } : x ) ) ) } />
							<span aria-hidden="true">→</span>
							<input type="date" className="bl-input" value={ h.to } aria-label={ __( 'Last closed day', 'bigloop-call-button' ) } onChange={ ( e ) => set( 'holidays', config.holidays.map( ( x, j ) => ( j === i ? { ...x, to: e.target.value } : x ) ) ) } />
							<input className="bl-input" value={ h.label } placeholder={ __( 'Label (optional)', 'bigloop-call-button' ) } aria-label={ __( 'Label', 'bigloop-call-button' ) } maxLength={ 80 } onChange={ ( e ) => set( 'holidays', config.holidays.map( ( x, j ) => ( j === i ? { ...x, label: e.target.value } : x ) ) ) } />
							<Button size="sm" variant="ghost" icon={ <X /> } onClick={ () => set( 'holidays', config.holidays.filter( ( x, j ) => j !== i ) ) } aria-label={ __( 'Remove closure', 'bigloop-call-button' ) } />
							{ ( errors[ `holidays.${ i }.from` ] || errors[ `holidays.${ i }.to` ] ) && <p className="bl-field__error">{ errors[ `holidays.${ i }.from` ] || errors[ `holidays.${ i }.to` ] }</p> }
						</div>
					) ) }
					<button type="button" className="blcb-linkbtn" onClick={ () => set( 'holidays', [ ...config.holidays, { from: '', to: '', label: '' } ] ) }>
						<Plus size={ 14 } aria-hidden="true" /> { __( 'Add closure', 'bigloop-call-button' ) }
					</button>

					<h3 className="blcb-section-title">{ __( 'Special opening hours', 'bigloop-call-button' ) }</h3>
					{ config.special.map( ( sp, i ) => (
						<div key={ i } className="blcb-dated blcb-dated--special">
							<input type="date" className="bl-input" value={ sp.date } aria-label={ __( 'Date', 'bigloop-call-button' ) } onChange={ ( e ) => set( 'special', config.special.map( ( x, j ) => ( j === i ? { ...x, date: e.target.value } : x ) ) ) } />
							<Intervals value={ sp.intervals } onChange={ ( v ) => set( 'special', config.special.map( ( x, j ) => ( j === i ? { ...x, intervals: v } : x ) ) ) } errorPrefix={ `special.${ i }.intervals` } errors={ errors } />
							<Button size="sm" variant="ghost" icon={ <X /> } onClick={ () => set( 'special', config.special.filter( ( x, j ) => j !== i ) ) } aria-label={ __( 'Remove special hours', 'bigloop-call-button' ) } />
							{ errors[ `special.${ i }.date` ] && <p className="bl-field__error">{ errors[ `special.${ i }.date` ] }</p> }
						</div>
					) ) }
					<button type="button" className="blcb-linkbtn" onClick={ () => set( 'special', [ ...config.special, { date: '', intervals: [ [ '10:00', '14:00' ] ], label: '' } ] ) }>
						<Plus size={ 14 } aria-hidden="true" /> { __( 'Add special hours for a date', 'bigloop-call-button' ) }
					</button>

					<h3 className="blcb-section-title">{ __( 'Active period (optional)', 'bigloop-call-button' ) }</h3>
					<div className="blcb-dated">
						<input type="date" className="bl-input" value={ config.date_range.from } aria-label={ __( 'Starts', 'bigloop-call-button' ) } onChange={ ( e ) => set( 'date_range', { ...config.date_range, from: e.target.value } ) } />
						<span aria-hidden="true">→</span>
						<input type="date" className="bl-input" value={ config.date_range.to } aria-label={ __( 'Ends', 'bigloop-call-button' ) } onChange={ ( e ) => set( 'date_range', { ...config.date_range, to: e.target.value } ) } />
					</div>
					<p className="bl-field__help">{ __( 'Outside this period the schedule counts as closed. Leave empty to run indefinitely.', 'bigloop-call-button' ) }</p>
					{ errors[ 'date_range.to' ] && <p className="bl-field__error">{ errors[ 'date_range.to' ] }</p> }
				</div>

				<aside className="blcb-sched__side" aria-live="polite">
					{ summary && (
						<div className="blcb-sched__status">
							<Badge tone={ summary.open_now ? 'success' : 'neutral' } dot>
								{ summary.open_now ? __( 'Open now', 'bigloop-call-button' ) : __( 'Closed now', 'bigloop-call-button' ) }
							</Badge>
							<span className="bl-muted">
								{ summary.local_now } · { summary.timezone }
							</span>
							{ summary.next_change && (
								<span className="bl-muted">
									{ summary.open_now ? sprintf( /* translators: %s: date/time */ __( 'Closes %s', 'bigloop-call-button' ), summary.next_change ) : sprintf( /* translators: %s: date/time */ __( 'Opens %s', 'bigloop-call-button' ), summary.next_change ) }
								</span>
							) }
							<h4>{ __( 'Next 7 days', 'bigloop-call-button' ) }</h4>
							<ul className="blcb-sched__week">
								{ summary.week.map( ( d ) => (
									<li key={ d.date }>
										<span>{ d.weekday }</span>
										<span className={ d.intervals.length ? '' : 'bl-muted' }>{ formatIntervals( d.intervals ) }</span>
									</li>
								) ) }
							</ul>
						</div>
					) }
				</aside>
			</div>
		</Modal>
	);
}

export default function Schedules() {
	const toast = useToast();
	const [ items, setItems ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ editing, setEditing ] = useState( null );
	const [ deleting, setDeleting ] = useState( null );

	const load = useCallback( () => {
		setError( null );
		get( '/schedules' )
			.then( ( r ) => setItems( r.items ) )
			.catch( ( e ) => setError( parseError( e ).message ) );
	}, [] );
	useEffect( load, [ load ] );

	const onSaved = ( saved ) => {
		setItems( ( list ) => ( list.some( ( x ) => x.id === saved.id ) ? list.map( ( x ) => ( x.id === saved.id ? saved : x ) ) : [ ...list, saved ] ) );
		setEditing( null );
	};

	return (
		<div>
			<PageHeader
				title={ __( 'Schedules', 'bigloop-call-button' ) }
				description={ __( 'Business hours, holidays and special opening times. Use a schedule to hide a widget after hours, or to swap actions, e.g. show WhatsApp instead of the phone when you\'re closed.', 'bigloop-call-button' ) }
				actions={
					<Button variant="primary" icon={ <Plus /> } onClick={ () => setEditing( 'new' ) }>
						{ __( 'New schedule', 'bigloop-call-button' ) }
					</Button>
				}
			/>
			{ error && <Notice tone="danger" actions={ <Button size="sm" icon={ <RefreshCw /> } onClick={ load }>{ __( 'Try again', 'bigloop-call-button' ) }</Button> }>{ error }</Notice> }
			<Card padded={ false }>
				{ ! items && ! error && (
					<div style={ { padding: 24 } }>
						<Skeleton height="48px" />
					</div>
				) }
				{ items && items.length === 0 && (
					<EmptyState icon={ <CalendarClock /> } title={ __( 'No schedules yet', 'bigloop-call-button' ) } action={ <Button variant="primary" icon={ <Plus /> } onClick={ () => setEditing( 'new' ) }>{ __( 'Create your business hours', 'bigloop-call-button' ) }</Button> }>
						{ __( 'Times follow your site\'s timezone by default, and pages stay correct even when they are cached.', 'bigloop-call-button' ) }
					</EmptyState>
				) }
				{ items && items.length > 0 && (
					<ul className="blcb-list">
						{ items.map( ( s ) => (
							<li key={ s.id } className="blcb-list__row blcb-list__row--sched">
								<span className="blcb-table__typeicon">
									<CalendarClock size={ 18 } aria-hidden="true" />
								</span>
								<div className="blcb-list__main">
									<button type="button" className="blcb-list__title" onClick={ () => setEditing( s ) }>
										{ s.title }
									</button>
									<span className="blcb-list__meta bl-muted">
										{ formatIntervals( s.config.weekly.mon ) } { __( 'Mon', 'bigloop-call-button' ) } · { s.summary.timezone }
									</span>
								</div>
								<Badge tone={ s.summary.open_now ? 'success' : 'neutral' } dot>
									{ s.summary.open_now ? __( 'Open now', 'bigloop-call-button' ) : __( 'Closed now', 'bigloop-call-button' ) }
								</Badge>
								<span className="blcb-list__usage bl-muted">
									{ s.used_by.length ? sprintf( /* translators: %d: number of widgets */ _n( 'Used by %d widget', 'Used by %d widgets', s.used_by.length, 'bigloop-call-button' ), s.used_by.length ) : __( 'Not used yet', 'bigloop-call-button' ) }
								</span>
								<div className="blcb-list__actions">
									<Tooltip text={ __( 'Edit', 'bigloop-call-button' ) }>
										<Button size="sm" variant="ghost" icon={ <Pencil /> } onClick={ () => setEditing( s ) } aria-label={ sprintf( /* translators: %s: name */ __( 'Edit %s', 'bigloop-call-button' ), s.title ) } />
									</Tooltip>
									<Tooltip text={ __( 'Duplicate', 'bigloop-call-button' ) }>
										<Button size="sm" variant="ghost" icon={ <Copy /> } onClick={ () => post( `/schedules/${ s.id }/duplicate` ).then( ( c ) => { setItems( ( l ) => [ ...l, c ] ); toast.success( __( 'Schedule duplicated.', 'bigloop-call-button' ) ); } ).catch( ( e ) => toast.error( parseError( e ).message ) ) } aria-label={ sprintf( /* translators: %s: name */ __( 'Duplicate %s', 'bigloop-call-button' ), s.title ) } />
									</Tooltip>
									<Tooltip text={ __( 'Delete', 'bigloop-call-button' ) }>
										<Button size="sm" variant="ghost" icon={ <Trash2 /> } onClick={ () => setDeleting( s ) } aria-label={ sprintf( /* translators: %s: name */ __( 'Delete %s', 'bigloop-call-button' ), s.title ) } />
									</Tooltip>
								</div>
							</li>
						) ) }
					</ul>
				) }
			</Card>
			{ editing && <ScheduleEditor doc={ editing === 'new' ? null : editing } onSaved={ onSaved } onClose={ () => setEditing( null ) } /> }
			{ deleting && (
				<ConfirmDialog
					title={ sprintf( /* translators: %s: name */ __( 'Delete "%s"?', 'bigloop-call-button' ), deleting.title ) }
					message={ deleting.used_by.length ? sprintf( /* translators: %s: widget names */ __( 'These widgets will stop following a schedule and show at all times: %s.', 'bigloop-call-button' ), deleting.used_by.map( ( w ) => w.title ).join( ', ' ) ) : __( 'This cannot be undone.', 'bigloop-call-button' ) }
					confirmLabel={ __( 'Delete schedule', 'bigloop-call-button' ) }
					cancelLabel={ __( 'Cancel', 'bigloop-call-button' ) }
					destructive
					onCancel={ () => setDeleting( null ) }
					onConfirm={ () =>
						del( `/schedules/${ deleting.id }` )
							.then( () => {
								setItems( ( l ) => l.filter( ( x ) => x.id !== deleting.id ) );
								setDeleting( null );
								toast.success( __( 'Schedule deleted.', 'bigloop-call-button' ) );
							} )
							.catch( ( e ) => toast.error( parseError( e ).message ) )
					}
				/>
			) }
		</div>
	);
}
