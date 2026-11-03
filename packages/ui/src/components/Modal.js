/**
 * Modal dialog and confirmation dialog.
 * Focus is trapped inside, Escape closes, focus returns to the opener, background scroll is locked.
 */
import { createPortal, useEffect, useRef, useState } from '@wordpress/element';
import { cx, uniqueId } from '../utils';
import { Button } from './basic';

// Open dialogs, top-most last. Escape and focus recovery apply only to the top-most one.
const stack = [];

const FOCUSABLE = 'a[href],button:not([disabled]),textarea:not([disabled]),input:not([disabled]),select:not([disabled]),[tabindex]:not([tabindex="-1"])';

export function Modal( { title, description, onClose, children, footer, size = 'md', closeLabel = 'Close', role = 'dialog' } ) {
	const [ ids ] = useState( () => {
		const base = uniqueId( 'bl-modal' );
		return { title: `${ base }-title`, desc: `${ base }-desc` };
	} );
	const dialogRef = useRef();
	const onCloseRef = useRef( onClose );
	onCloseRef.current = onClose;
	const openerRef = useRef( typeof document !== 'undefined' ? document.activeElement : null );

	useEffect( () => {
		const dialog = dialogRef.current;
		const first = dialog && dialog.querySelector( '[data-autofocus]' );
		const focusables = dialog ? dialog.querySelectorAll( FOCUSABLE ) : [];
		( first || focusables[ 0 ] || dialog )?.focus();

		const prevOverflow = document.body.style.overflow;
		document.body.style.overflow = 'hidden';
		const opener = openerRef.current;
		stack.push( dialog );

		const isTop = () => stack[ stack.length - 1 ] === dialog;
		// Escape works even when focus has fallen outside the dialog (e.g. a busy button became disabled).
		const onDocKey = ( e ) => {
			if ( e.key === 'Escape' && isTop() && ! dialog.contains( document.activeElement ) ) {
				e.preventDefault();
				onCloseRef.current();
			}
		};
		// Keep focus inside the top-most dialog.
		const onFocusIn = ( e ) => {
			if ( isTop() && ! dialog.contains( e.target ) && ! ( e.target.closest && e.target.closest( '.bl-modal-root' ) ) ) {
				dialog.focus();
			}
		};
		document.addEventListener( 'keydown', onDocKey );
		document.addEventListener( 'focusin', onFocusIn );

		return () => {
			document.removeEventListener( 'keydown', onDocKey );
			document.removeEventListener( 'focusin', onFocusIn );
			const i = stack.indexOf( dialog );
			if ( i > -1 ) {
				stack.splice( i, 1 );
			}
			document.body.style.overflow = prevOverflow;
			if ( opener && typeof opener.focus === 'function' ) {
				opener.focus();
			}
		};
	}, [] );

	const onKeyDown = ( e ) => {
		if ( e.key === 'Escape' ) {
			e.stopPropagation();
			onClose();
			return;
		}
		if ( e.key !== 'Tab' ) {
			return;
		}
		const nodes = Array.from( dialogRef.current.querySelectorAll( FOCUSABLE ) );
		if ( ! nodes.length ) {
			e.preventDefault();
			return;
		}
		const first = nodes[ 0 ];
		const last = nodes[ nodes.length - 1 ];
		if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	};

	return createPortal(
		<div className="blcb-app bl-modal-root">
			<div className="bl-modal-backdrop" onMouseDown={ onClose } aria-hidden="true" />
			<div
				ref={ dialogRef }
				className={ cx( 'bl-modal', `bl-modal--${ size }` ) }
				role={ role }
				aria-modal="true"
				aria-labelledby={ ids.title }
				aria-describedby={ description ? ids.desc : undefined }
				tabIndex={ -1 }
				onKeyDown={ onKeyDown }
			>
				<header className="bl-modal__header">
					<h2 id={ ids.title } className="bl-modal__title">
						{ title }
					</h2>
					<button type="button" className="bl-modal__close" onClick={ onClose } aria-label={ closeLabel }>
						<span aria-hidden="true">×</span>
					</button>
				</header>
				<div className="bl-modal__body">
					{ description && (
						<p id={ ids.desc } className="bl-modal__description">
							{ description }
						</p>
					) }
					{ children }
				</div>
				{ footer && <footer className="bl-modal__footer">{ footer }</footer> }
			</div>
		</div>,
		document.body
	);
}

/**
 * Confirmation dialog (role="alertdialog"). The safe choice (Cancel) receives initial focus.
 */
export function ConfirmDialog( { title, message, confirmLabel = 'Confirm', cancelLabel = 'Cancel', destructive = false, busy = false, onConfirm, onCancel } ) {
	return (
		<Modal
			title={ title }
			description={ message }
			onClose={ onCancel }
			size="sm"
			role="alertdialog"
			footer={
				<>
					<Button variant="secondary" onClick={ onCancel } data-autofocus>
						{ cancelLabel }
					</Button>
					<Button variant={ destructive ? 'danger' : 'primary' } onClick={ onConfirm } busy={ busy }>
						{ confirmLabel }
					</Button>
				</>
			}
		/>
	);
}
