/**
 * Pro & Licensing (informational in Free). When Pro is active it replaces this screen with its licence UI.
 */
import { __ } from '@wordpress/i18n';
import { Card, PageHeader, Badge } from '@bigloop/ui';
import { Check } from 'lucide-react';

export default function Pro( { boot } ) {
	const free = [
		__( 'Unlimited widgets and actions', 'bigloop-call-button' ),
		__( 'Floating buttons, expandable menus, contact bars', 'bigloop-call-button' ),
		__( 'All shapes, including squircle, plus full styling', 'bigloop-call-button' ),
		__( 'Scheduling, holidays and display rules', 'bigloop-call-button' ),
		__( 'Local click analytics', 'bigloop-call-button' ),
	];
	const pro = [
		__( 'Callback forms and a lead inbox', 'bigloop-call-button' ),
		__( 'A/B testing of designs, labels and positions', 'bigloop-call-button' ),
		__( 'Conversion and campaign (UTM) attribution, exports', 'bigloop-call-button' ),
		__( 'CRM, Slack and webhook integrations', 'bigloop-call-button' ),
		__( 'Multiple locations, dynamic numbers, white-label', 'bigloop-call-button' ),
	];
	return (
		<div className="blcb-pro">
			<PageHeader
				title={ __( 'Pro & Licensing', 'bigloop-call-button' ) }
				description={ __( 'The free plugin is complete on its own, with no account and no limits. Pro is an optional add-on for lead generation and marketing teams.', 'bigloop-call-button' ) }
			/>
			<div className="blcb-cards-2">
				<Card title={ __( 'Free', 'bigloop-call-button' ) } actions={ <Badge tone="success">{ __( 'Active', 'bigloop-call-button' ) }</Badge> }>
					<ul className="blcb-checklist">
						{ free.map( ( f ) => (
							<li key={ f }>
								<Check size={ 16 } aria-hidden="true" /> { f }
							</li>
						) ) }
					</ul>
				</Card>
				<Card title={ __( 'Pro add-on', 'bigloop-call-button' ) } actions={ <Badge tone="neutral">{ __( 'Coming soon', 'bigloop-call-button' ) }</Badge> }>
					<ul className="blcb-checklist">
						{ pro.map( ( f ) => (
							<li key={ f }>
								<Check size={ 16 } aria-hidden="true" /> { f }
							</li>
						) ) }
					</ul>
					<p className="bl-muted" style={ { marginTop: 16 } }>
						{ __( 'Learn more at', 'bigloop-call-button' ) }{ ' ' }
						<a href={ boot.links.website } target="_blank" rel="noopener noreferrer">
							bigloop.dev<span className="bl-visually-hidden"> { __( '(opens in a new tab)', 'bigloop-call-button' ) }</span>
						</a>
					</p>
				</Card>
			</div>
		</div>
	);
}
