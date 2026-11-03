/**
 * App shell: brand header, section navigation and routed screens.
 */
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { ToastProvider, Badge, cx } from '@bigloop/ui';
import { useRouter, linkHandler } from './lib/router';
import Overview from './screens/Overview';
import Settings from './screens/Settings';
import Help from './screens/Help';
import Pro from './screens/Pro';
import Planned from './screens/Planned';
import Widgets from './screens/Widgets';
import WidgetEditor from './screens/WidgetEditor';
import Actions from './screens/Actions';
import Schedules from './screens/Schedules';
import Analytics from './screens/Analytics';
import PluginNotice from './components/PluginNotice';

const NAV = [ 'overview', 'widgets', 'actions', 'schedules', 'analytics', 'settings', 'help' ];

function BrandMark() {
	return (
		<svg className="blcb-brand__mark" viewBox="0 0 32 32" aria-hidden="true" focusable="false">
			<rect width="32" height="32" rx="9" fill="currentColor" />
			<path
				fill="#fff"
				d="M21.6 18.9l-2.2-1a1.3 1.3 0 00-1.5.4l-.9 1.1a9.4 9.4 0 01-4.4-4.4l1.1-.9a1.3 1.3 0 00.4-1.5l-1-2.2a1.3 1.3 0 00-1.5-.7l-2 .5A1.3 1.3 0 008.6 11.5 12 12 0 0020.5 23.4a1.3 1.3 0 001.3-1l.5-2a1.3 1.3 0 00-.7-1.5z"
			/>
		</svg>
	);
}

export default function App( { boot } ) {
	const { route, params, navigate } = useRouter( boot.pages, boot.route );
	const ctx = { boot, navigate, params };

	let screen;
	switch ( route ) {
		case 'overview':
			screen = <Overview { ...ctx } />;
			break;
		case 'settings':
			screen = <Settings { ...ctx } />;
			break;
		case 'help':
			screen = <Help { ...ctx } />;
			break;
		case 'pro':
			screen = applyFilters( 'bigloop.admin.screen', null, route, ctx ) || <Pro { ...ctx } />;
			break;
		case 'widgets':
			screen = <Widgets { ...ctx } />;
			break;
		case 'new':
			screen = <WidgetEditor key={ params.widget || 'new' } { ...ctx } />;
			break;
		case 'actions':
			screen = <Actions { ...ctx } />;
			break;
		case 'schedules':
			screen = <Schedules { ...ctx } />;
			break;
		case 'analytics':
			screen = <Analytics { ...ctx } />;
			break;
		default: {
			/**
			 * Extensions (Pro) render their own screens for routes they added with the
			 * `bigloop_cb/admin_pages` PHP filter. Return a React element or null.
			 */
			const ext = applyFilters( 'bigloop.admin.screen', null, route, ctx );
			screen = ext || <Planned route={ route } { ...ctx } />;
		}
	}

	return (
		<ToastProvider dismissLabel={ __( 'Dismiss notification', 'bigloop-call-button' ) }>
			<div className="blcb-shell">
				<header className="blcb-topbar">
					<div className="blcb-brand">
						<BrandMark />
						<span className="blcb-brand__name">{ __( 'Bigloop Contact', 'bigloop-call-button' ) }</span>
						<Badge tone="neutral">v{ boot.version }</Badge>
					</div>
					<nav className="blcb-nav" aria-label={ __( 'Bigloop Contact sections', 'bigloop-call-button' ) }>
						{ applyFilters( 'bigloop.admin.nav', NAV, boot ).filter( ( r ) => boot.pages[ r ] ).map( ( r ) => (
							<a
								key={ r }
								href={ boot.pages[ r ].url }
								className={ cx( 'blcb-nav__link', ( route === r || ( r === 'widgets' && route === 'new' ) ) && 'is-active' ) }
								aria-current={ route === r ? 'page' : undefined }
								onClick={ linkHandler( navigate, r ) }
							>
								{ boot.pages[ r ].title }
							</a>
						) ) }
					</nav>
					<div className="blcb-topbar__end">
						{ boot.pages.pro && (
							<a href={ boot.pages.pro.url } className={ cx( 'blcb-pro-link', route === 'pro' && 'is-active' ) } onClick={ linkHandler( navigate, 'pro' ) }>
								{ boot.pages.pro.nav || __( 'Pro', 'bigloop-call-button' ) }
							</a>
						) }
					</div>
				</header>
				<main className="blcb-main" id="blcb-main" tabIndex={ -1 }>
					{ [ 'overview', 'widgets', 'analytics', 'help' ].includes( route ) && <PluginNotice notice={ ( boot.notices || [] )[ 0 ] } /> }
					{ screen }
				</main>
			</div>
		</ToastProvider>
	);
}
