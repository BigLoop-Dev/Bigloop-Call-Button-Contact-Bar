/**
 * Review / coffee notice shown inside the plugin's own screens only. One at a time; snooze or dismiss per user.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Button } from '@bigloop/ui';
import { Star, Coffee, X } from 'lucide-react';
import { post } from '../lib/api';
import { formatNumber } from '../lib/labels';

export default function PluginNotice( { notice } ) {
	const [ hidden, setHidden ] = useState( false );
	if ( ! notice || hidden ) {
		return null;
	}
	const dismiss = ( mode ) => {
		setHidden( true );
		post( `/notices/${ notice.id }`, { mode } ).catch( () => {} );
	};
	const isReview = notice.id === 'review';

	return (
		<aside className="blcb-plugin-notice" aria-label={ isReview ? __( 'Review request', 'bigloop-call-button' ) : __( 'Support the plugin', 'bigloop-call-button' ) }>
			<span className="blcb-plugin-notice__icon" aria-hidden="true">
				{ isReview ? <Star size={ 18 } /> : <Coffee size={ 18 } /> }
			</span>
			<div className="blcb-plugin-notice__body">
				<strong>
					{ isReview
						? sprintf( /* translators: %s: number of clicks */ __( 'Your contact buttons have been used %s times.', 'bigloop-call-button' ), formatNumber( notice.meta?.clicks ) )
						: __( 'Enjoying Bigloop Contact?', 'bigloop-call-button' ) }
				</strong>
				<span>
					{ isReview
						? __( 'If the plugin helps your business, a short review on WordPress.org helps others find it. It only takes a minute.', 'bigloop-call-button' )
						: __( 'The free version is built and maintained by a small team. If it saves you time, you can buy us a coffee.', 'bigloop-call-button' ) }
				</span>
			</div>
			<div className="blcb-plugin-notice__actions">
				<Button size="sm" variant="primary" href={ notice.url } target="_blank" rel="noopener noreferrer" onClick={ () => dismiss( 'done' ) }>
					{ isReview ? __( 'Leave a review', 'bigloop-call-button' ) : __( 'Buy us a coffee', 'bigloop-call-button' ) }
				</Button>
				<Button size="sm" variant="ghost" onClick={ () => dismiss( 'later' ) }>
					{ __( 'Maybe later', 'bigloop-call-button' ) }
				</Button>
				<button type="button" className="blcb-plugin-notice__close" onClick={ () => dismiss( 'never' ) } aria-label={ __( 'Don\'t show this again', 'bigloop-call-button' ) } title={ __( 'Don\'t show this again', 'bigloop-call-button' ) }>
					<X size={ 16 } aria-hidden="true" />
				</button>
			</div>
		</aside>
	);
}
