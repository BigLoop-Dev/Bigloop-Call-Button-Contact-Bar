/**
 * Bigloop Contact admin app entry.
 */
import { createRoot } from '@wordpress/element';
import '@bigloop/ui/styles.scss';
import './admin.scss';
import './screens.scss';
import App from './App';

function mount() {
	const el = document.getElementById( 'blcb-admin-root' );
	if ( ! el || ! window.blcbAdmin ) {
		return;
	}
	createRoot( el ).render( <App boot={ window.blcbAdmin } /> );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
