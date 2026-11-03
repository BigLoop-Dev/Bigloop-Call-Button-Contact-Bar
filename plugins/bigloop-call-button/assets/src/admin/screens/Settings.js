/**
 * Settings screen. Only settings whose behaviour is implemented are shown; sections are added as features ship.
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useState, useCallback } from '@wordpress/element';
import { Button, Card, Toggle, Tabs, Notice, PageHeader, Skeleton, ConfirmDialog, SelectField, TextField, NumberField, useToast } from '@bigloop/ui';
import { get, put, parseError } from '../lib/api';
import { settingsDiff, isDirty } from '../lib/settings';

export default function Settings() {
	const toast = useToast();
	const [ saved, setSaved ] = useState( null );
	const [ draft, setDraft ] = useState( null );
	const [ loadError, setLoadError ] = useState( null );
	const [ fieldErrors, setFieldErrors ] = useState( {} );
	const [ saving, setSaving ] = useState( false );
	const [ tab, setTab ] = useState( 'general' );
	const [ confirmDelete, setConfirmDelete ] = useState( false );

	const load = useCallback( () => {
		setLoadError( null );
		get( '/settings' )
			.then( ( res ) => {
				setSaved( res.values );
				setDraft( res.values );
			} )
			.catch( ( e ) => setLoadError( parseError( e ).message ) );
	}, [] );

	useEffect( load, [ load ] );

	const dirty = isDirty( saved, draft );

	// Warn before leaving with unsaved changes.
	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}
		const onBeforeUnload = ( e ) => {
			e.preventDefault();
			e.returnValue = '';
		};
		window.addEventListener( 'beforeunload', onBeforeUnload );
		return () => window.removeEventListener( 'beforeunload', onBeforeUnload );
	}, [ dirty ] );

	const set = ( section, key, value ) => {
		setDraft( ( d ) => ( { ...d, [ section ]: { ...d[ section ], [ key ]: value } } ) );
		setFieldErrors( ( errs ) => {
			const next = { ...errs };
			delete next[ `${ section }.${ key }` ];
			return next;
		} );
	};

	const save = () => {
		setSaving( true );
		put( '/settings', { values: settingsDiff( saved, draft ) } )
			.then( ( res ) => {
				setSaved( res.values );
				setDraft( res.values );
				setFieldErrors( {} );
				toast.success( __( 'Settings saved.', 'bigloop-call-button' ) );
			} )
			.catch( ( e ) => {
				const { message, fieldErrors: errs } = parseError( e );
				setFieldErrors( errs );
				toast.error( message );
			} )
			.finally( () => setSaving( false ) );
	};

	if ( loadError ) {
		return (
			<>
				<PageHeader title={ __( 'Settings', 'bigloop-call-button' ) } />
				<Notice tone="danger" title={ __( 'Settings could not be loaded.', 'bigloop-call-button' ) } actions={ <Button size="sm" onClick={ load }>{ __( 'Try again', 'bigloop-call-button' ) }</Button> }>
					{ loadError }
				</Notice>
			</>
		);
	}

	const tabs = [
		{ id: 'general', label: __( 'General', 'bigloop-call-button' ) },
		{ id: 'analytics', label: __( 'Analytics', 'bigloop-call-button' ) },
		{ id: 'access', label: __( 'Access', 'bigloop-call-button' ) },
		{ id: 'privacy', label: __( 'Privacy & location', 'bigloop-call-button' ) },
	];

	return (
		<div className="blcb-settings">
			<PageHeader title={ __( 'Settings', 'bigloop-call-button' ) } description={ __( 'Site-wide options for Bigloop Contact. Widget appearance and display rules are set per widget.', 'bigloop-call-button' ) } />

			{ ! draft ? (
				<Card>
					<div className="blcb-stack">
						<Skeleton height="22px" width="40%" />
						<Skeleton height="16px" width="70%" />
						<Skeleton height="22px" width="40%" />
					</div>
				</Card>
			) : (
				<Card>
					<Tabs tabs={ tabs } selected={ tab } onSelect={ setTab } label={ __( 'Settings sections', 'bigloop-call-button' ) }>
						{ ( current ) => (
							<div className="blcb-form">
								{ current === 'general' && (
									<Toggle
										label={ __( 'Remove all data when the plugin is deleted', 'bigloop-call-button' ) }
										help={ __( 'When enabled, deleting the plugin from the Plugins screen permanently removes all widgets, actions, schedules, presets, statistics and settings. Deactivating never removes data.', 'bigloop-call-button' ) }
										checked={ draft.general.delete_data_on_uninstall }
										error={ fieldErrors[ 'general.delete_data_on_uninstall' ] }
										onChange={ ( v ) => ( v ? setConfirmDelete( true ) : set( 'general', 'delete_data_on_uninstall', false ) ) }
									/>
								) }
								{ current === 'analytics' && (
									<>
										<Toggle
											label={ __( 'Record clicks', 'bigloop-call-button' ) }
											help={ __( 'Counts clicks on your contact actions and stores them on this site. No cookies, IP addresses or personal data are stored.', 'bigloop-call-button' ) }
											checked={ draft.analytics.enabled }
											error={ fieldErrors[ 'analytics.enabled' ] }
											onChange={ ( v ) => set( 'analytics', 'enabled', v ) }
										/>
										{ draft.analytics.enabled && (
											<>
												<Toggle
													label={ __( 'Don\'t count my own clicks', 'bigloop-call-button' ) }
													help={ __( 'Clicks by logged-in users who can manage Bigloop Contact are ignored.', 'bigloop-call-button' ) }
													checked={ draft.analytics.exclude_admins }
													onChange={ ( v ) => set( 'analytics', 'exclude_admins', v ) }
												/>
												<NumberField
													label={ __( 'Keep detailed click records for', 'bigloop-call-button' ) }
													help={ __( 'Daily totals are kept indefinitely. Detailed records (used for the top pages list) are deleted after this many days.', 'bigloop-call-button' ) }
													suffix={ __( 'days', 'bigloop-call-button' ) }
													min={ 7 }
													max={ 3650 }
													value={ draft.analytics.retention_days }
													error={ fieldErrors[ 'analytics.retention_days' ] }
													onChange={ ( v ) => set( 'analytics', 'retention_days', v ) }
												/>
											</>
										) }
										<Toggle
											label={ __( 'Send click events to Google Analytics', 'bigloop-call-button' ) }
											help={ __( 'When your site already uses Google Analytics 4 or Google Tag Manager, each click is also sent as an event. This plugin never loads Google scripts itself.', 'bigloop-call-button' ) }
											checked={ draft.analytics.ga_events }
											onChange={ ( v ) => set( 'analytics', 'ga_events', v ) }
										/>
										{ draft.analytics.ga_events && (
											<TextField
												label={ __( 'Event name', 'bigloop-call-button' ) }
												help={ __( 'Parameters sent with the event: widget_id, action_id, action_type and link_text.', 'bigloop-call-button' ) }
												value={ draft.analytics.ga_event_name }
												placeholder="bigloop_contact_click"
												error={ fieldErrors[ 'analytics.ga_event_name' ] }
												onChange={ ( v ) => set( 'analytics', 'ga_event_name', v ) }
											/>
										) }
									</>
								) }
								{ current === 'privacy' && (
									<>
										<Toggle
											label={ __( 'Enable country targeting', 'bigloop-call-button' ) }
											help={ __( 'Lets display rules show or hide widgets by the visitor\'s country. The country is read from a header your host or CDN already adds (such as Cloudflare\'s). No external service is contacted and nothing is stored about visitors.', 'bigloop-call-button' ) }
											checked={ draft.geo.enabled }
											error={ fieldErrors[ 'geo.enabled' ] }
											onChange={ ( v ) => set( 'geo', 'enabled', v ) }
										/>
										{ draft.geo.enabled && (
											<>
												<SelectField
													label={ __( 'Country source', 'bigloop-call-button' ) }
													value={ draft.geo.source }
													error={ fieldErrors[ 'geo.source' ] }
													onChange={ ( v ) => set( 'geo', 'source', v ) }
													options={ [
														{ value: 'auto', label: __( 'Automatic (Cloudflare, then server header)', 'bigloop-call-button' ) },
														{ value: 'cloudflare', label: __( 'Cloudflare (CF-IPCountry)', 'bigloop-call-button' ) },
														{ value: 'header', label: __( 'Server variable set by my host', 'bigloop-call-button' ) },
													] }
												/>
												{ draft.geo.source !== 'cloudflare' && (
													<TextField
														label={ __( 'Server variable name', 'bigloop-call-button' ) }
														help={ __( 'For example GEOIP_COUNTRY_CODE or HTTP_X_COUNTRY_CODE. The name must contain GEO or COUNTRY.', 'bigloop-call-button' ) }
														value={ draft.geo.header }
														error={ fieldErrors[ 'geo.header' ] }
														onChange={ ( v ) => set( 'geo', 'header', v ) }
													/>
												) }
												<Notice tone="info">{ __( 'Visitors whose country can\'t be determined never match "is one of" rules and are never excluded by "is not one of" rules.', 'bigloop-call-button' ) }</Notice>
											</>
										) }
									</>
								) }
								{ current === 'access' && (
									<Toggle
										label={ __( 'Allow Editors to manage contact widgets', 'bigloop-call-button' ) }
										help={ __( 'Administrators always have access. When enabled, users with the Editor role can also manage widgets, settings and analytics.', 'bigloop-call-button' ) }
										checked={ draft.access.allow_editors }
										error={ fieldErrors[ 'access.allow_editors' ] }
										onChange={ ( v ) => set( 'access', 'allow_editors', v ) }
									/>
								) }
							</div>
						) }
					</Tabs>
				</Card>
			) }

			{ draft && (
				<div className={ `blcb-savebar${ dirty ? ' is-dirty' : '' }` } role="region" aria-label={ __( 'Save settings', 'bigloop-call-button' ) }>
					<span className="blcb-savebar__status" aria-live="polite">
						{ dirty ? __( 'You have unsaved changes.', 'bigloop-call-button' ) : __( 'All changes saved.', 'bigloop-call-button' ) }
					</span>
					<div className="blcb-savebar__actions">
						<Button variant="ghost" disabled={ ! dirty || saving } onClick={ () => { setDraft( saved ); setFieldErrors( {} ); } }>
							{ __( 'Discard', 'bigloop-call-button' ) }
						</Button>
						<Button variant="primary" disabled={ ! dirty } busy={ saving } onClick={ save }>
							{ __( 'Save changes', 'bigloop-call-button' ) }
						</Button>
					</div>
				</div>
			) }

			{ confirmDelete && (
				<ConfirmDialog
					title={ __( 'Delete data on uninstall?', 'bigloop-call-button' ) }
					message={ __( 'If you later delete the plugin, all widgets, statistics and settings will be permanently removed. This cannot be undone. You can turn this off again at any time before deleting.', 'bigloop-call-button' ) }
					confirmLabel={ __( 'Yes, enable', 'bigloop-call-button' ) }
					cancelLabel={ __( 'Cancel', 'bigloop-call-button' ) }
					destructive
					onCancel={ () => setConfirmDelete( false ) }
					onConfirm={ () => {
						set( 'general', 'delete_data_on_uninstall', true );
						setConfirmDelete( false );
					} }
				/>
			) }
		</div>
	);
}
