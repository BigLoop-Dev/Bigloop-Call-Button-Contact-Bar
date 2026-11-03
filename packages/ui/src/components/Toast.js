/**
 * Toast notifications rendered in a polite live region.
 *
 * Usage: wrap the app in <ToastProvider>, then `const toast = useToast(); toast.success( 'Saved' );`
 */
import { createContext, useCallback, useContext, useMemo, useRef, useState } from '@wordpress/element';
import { cx } from '../utils';

const ToastContext = createContext( null );

export function ToastProvider( { children, dismissLabel = 'Dismiss notification' } ) {
	const [ toasts, setToasts ] = useState( [] );
	const counter = useRef( 0 );

	const dismiss = useCallback( ( id ) => setToasts( ( list ) => list.filter( ( t ) => t.id !== id ) ), [] );

	const push = useCallback(
		( tone, message, timeout = 4500 ) => {
			counter.current += 1;
			const id = counter.current;
			setToasts( ( list ) => [ ...list.slice( -3 ), { id, tone, message } ] );
			if ( timeout ) {
				setTimeout( () => dismiss( id ), timeout );
			}
			return id;
		},
		[ dismiss ]
	);

	const api = useMemo(
		() => ( {
			success: ( m, t ) => push( 'success', m, t ),
			error: ( m, t = 8000 ) => push( 'danger', m, t ),
			info: ( m, t ) => push( 'info', m, t ),
			dismiss,
		} ),
		[ push, dismiss ]
	);

	return (
		<ToastContext.Provider value={ api }>
			{ children }
			<div className="bl-toast-region" aria-live="polite" aria-atomic="false">
				{ toasts.map( ( t ) => (
					<div key={ t.id } className={ cx( 'bl-toast', `bl-toast--${ t.tone }` ) } role={ t.tone === 'danger' ? 'alert' : 'status' }>
						<span className="bl-toast__message">{ t.message }</span>
						<button type="button" className="bl-toast__dismiss" onClick={ () => dismiss( t.id ) } aria-label={ dismissLabel }>
							<span aria-hidden="true">×</span>
						</button>
					</div>
				) ) }
			</div>
		</ToastContext.Provider>
	);
}

export function useToast() {
	const ctx = useContext( ToastContext );
	if ( ! ctx ) {
		throw new Error( 'useToast() must be used inside <ToastProvider>.' );
	}
	return ctx;
}
