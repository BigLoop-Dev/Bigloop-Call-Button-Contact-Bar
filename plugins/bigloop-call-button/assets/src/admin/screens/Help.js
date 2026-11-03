/**
 * Help & Support.
 */
import { __ } from '@wordpress/i18n';
import { Card, PageHeader } from '@bigloop/ui';
import { LifeBuoy, Star, Code2, ShieldCheck } from 'lucide-react';

export default function Help( { boot } ) {
	return (
		<div className="blcb-help">
			<PageHeader title={ __( 'Help & Support', 'bigloop-call-button' ) } description={ __( 'Answers, support and developer resources.', 'bigloop-call-button' ) } />

			<div className="blcb-cards-3">
				<Card title={ __( 'Get support', 'bigloop-call-button' ) }>
					<p className="bl-muted">{ __( 'Questions or problems? Ask in the support forum. Please include your WordPress and PHP versions from the Overview → System card.', 'bigloop-call-button' ) }</p>
					<p className="blcb-help__link">
						<a href={ boot.links.support } target="_blank" rel="noopener noreferrer">
							<LifeBuoy size={ 16 } aria-hidden="true" /> { __( 'Open the support forum', 'bigloop-call-button' ) }
							<span className="bl-visually-hidden">{ __( '(opens in a new tab)', 'bigloop-call-button' ) }</span>
						</a>
					</p>
				</Card>
				<Card title={ __( 'Your privacy', 'bigloop-call-button' ) }>
					<p className="bl-muted">{ __( 'This plugin makes no requests to external servers, sends nothing to Bigloop, and sets no cookies. Statistics are stored in your own database.', 'bigloop-call-button' ) }</p>
					<p className="blcb-help__link">
						<ShieldCheck size={ 16 } aria-hidden="true" /> { __( 'No account required', 'bigloop-call-button' ) }
					</p>
				</Card>
				<Card title={ __( 'Enjoying the plugin?', 'bigloop-call-button' ) }>
					<p className="bl-muted">{ __( 'An honest review on WordPress.org helps other site owners find the plugin and helps us improve it.', 'bigloop-call-button' ) }</p>
					<p className="blcb-help__link">
						<a href={ boot.links.review } target="_blank" rel="noopener noreferrer">
							<Star size={ 16 } aria-hidden="true" /> { __( 'Leave a review', 'bigloop-call-button' ) }
							<span className="bl-visually-hidden">{ __( '(opens in a new tab)', 'bigloop-call-button' ) }</span>
						</a>
					</p>
				</Card>
			</div>

			<Card title={ __( 'For developers', 'bigloop-call-button' ) } description={ __( 'Extend the plugin through its versioned public API. Avoid calling internal classes directly.', 'bigloop-call-button' ) }>
				<div className="blcb-dev">
					<Code2 size={ 18 } aria-hidden="true" className="blcb-dev__icon" />
					<pre className="blcb-code">
						<code>{ `add_action( 'bigloop_cb/loaded', function ( $api ) {
	if ( version_compare( $api->version(), '1.0.0', '<' ) ) {
		return;
	}
	// Your integration here.
} );` }</code>
					</pre>
				</div>
				<p className="bl-muted" style={ { marginTop: 12 } }>
					{ __( 'Public API version:', 'bigloop-call-button' ) } <code>{ boot.apiVersion }</code> · { __( 'REST namespace:', 'bigloop-call-button' ) } <code>{ boot.restNamespace }</code>
				</p>
				<p className="bl-muted" style={ { marginTop: 8 } }>
					{ __( 'Your own scripts can listen for the "bigloop:contact-click" event on document. All hooks, filters and REST routes are listed in docs/developers.md inside the plugin folder.', 'bigloop-call-button' ) }
				</p>
			</Card>
		</div>
	);
}
