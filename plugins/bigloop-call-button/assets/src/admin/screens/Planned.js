/**
 * Screens whose features ship in later development phases. They say so plainly instead of showing
 * controls that don't work yet. Each route is replaced by its real screen when its phase lands.
 */
import { __ } from '@wordpress/i18n';
import { Card, EmptyState, PageHeader, Badge, Button } from '@bigloop/ui';
import { Layers } from 'lucide-react';
import { linkHandler } from '../lib/router';

function content( route ) {
	switch ( route ) {
		default:
			return { icon: <Layers />, title: route, lead: '' };
	}
}

export default function Planned( { route, boot, navigate } ) {
	const c = content( route );
	return (
		<>
			<PageHeader title={ c.title } />
			<Card>
				<EmptyState
					icon={ c.icon }
					title={ __( 'This section is being built', 'bigloop-call-button' ) }
					action={
						<Button variant="secondary" href={ boot.pages.overview.url } onClick={ linkHandler( navigate, 'overview' ) }>
							{ __( 'Back to Overview', 'bigloop-call-button' ) }
						</Button>
					}
				>
					<p>{ c.lead }</p>
					<p style={ { marginTop: 12 } }>
						<Badge tone="info">{ __( 'In development', 'bigloop-call-button' ) }</Badge>
					</p>
				</EmptyState>
			</Card>
		</>
	);
}
