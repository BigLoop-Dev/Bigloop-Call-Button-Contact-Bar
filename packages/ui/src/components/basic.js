/**
 * Basic presentational components.
 */
import { forwardRef, useState } from '@wordpress/element';
import { cx, uniqueId } from '../utils';

/**
 * Button. Renders an <a> when `href` is given.
 *
 * @param {Object}  props
 * @param {string}  [props.variant] primary | secondary | ghost | danger
 * @param {string}  [props.size]    md | sm
 * @param {Element} [props.icon]    Leading icon element.
 * @param {boolean} [props.busy]    Shows a spinner and disables the button.
 */
export const Button = forwardRef( function Button(
	{ variant = 'secondary', size = 'md', icon, busy = false, href, className, children, disabled, type = 'button', ...rest },
	ref
) {
	const classes = cx( 'bl-btn', `bl-btn--${ variant }`, size === 'sm' && 'bl-btn--sm', ! children && 'bl-btn--icon-only', className );
	const inner = (
		<>
			{ busy ? <Spinner size="sm" /> : icon && <span className="bl-btn__icon" aria-hidden="true">{ icon }</span> }
			{ children && <span className="bl-btn__label">{ children }</span> }
		</>
	);
	if ( href && ! disabled ) {
		return (
			<a ref={ ref } href={ href } className={ classes } { ...rest }>
				{ inner }
			</a>
		);
	}
	return (
		<button ref={ ref } type={ type } className={ classes } disabled={ disabled || busy } aria-busy={ busy || undefined } { ...rest }>
			{ inner }
		</button>
	);
} );

/**
 * Card container.
 */
export function Card( { title, description, actions, footer, className, children, padded = true, as: Tag = 'section', ...rest } ) {
	return (
		<Tag className={ cx( 'bl-card', className ) } { ...rest }>
			{ ( title || actions ) && (
				<header className="bl-card__header">
					<div>
						{ title && <h2 className="bl-card__title">{ title }</h2> }
						{ description && <p className="bl-card__description">{ description }</p> }
					</div>
					{ actions && <div className="bl-card__actions">{ actions }</div> }
				</header>
			) }
			<div className={ cx( 'bl-card__body', ! padded && 'bl-card__body--flush' ) }>{ children }</div>
			{ footer && <footer className="bl-card__footer">{ footer }</footer> }
		</Tag>
	);
}

/**
 * Key metric tile.
 */
export function StatCard( { label, value, hint, icon, tone = 'accent', loading = false } ) {
	return (
		<div className="bl-stat">
			{ icon && <span className={ cx( 'bl-stat__icon', `bl-stat__icon--${ tone }` ) } aria-hidden="true">{ icon }</span> }
			<div className="bl-stat__content">
				<span className="bl-stat__label">{ label }</span>
				{ loading ? <Skeleton width="56px" height="28px" /> : <span className="bl-stat__value">{ value }</span> }
				{ hint && <span className="bl-stat__hint">{ hint }</span> }
			</div>
		</div>
	);
}

/**
 * Status badge.
 *
 * @param {Object} props
 * @param {string} [props.tone] neutral | accent | success | warning | danger | info
 */
export function Badge( { tone = 'neutral', dot = false, children, className } ) {
	return (
		<span className={ cx( 'bl-badge', `bl-badge--${ tone }`, className ) }>
			{ dot && <span className="bl-badge__dot" aria-hidden="true" /> }
			{ children }
		</span>
	);
}

/**
 * Loading spinner.
 */
export function Spinner( { size = 'md', label } ) {
	return (
		<span className={ cx( 'bl-spinner', `bl-spinner--${ size }` ) } role={ label ? 'status' : undefined }>
			{ label && <span className="bl-visually-hidden">{ label }</span> }
		</span>
	);
}

/**
 * Skeleton placeholder for loading content.
 */
export function Skeleton( { width = '100%', height = '14px', radius, className } ) {
	return <span className={ cx( 'bl-skeleton', className ) } style={ { width, height, borderRadius: radius } } aria-hidden="true" />;
}

/**
 * Empty state with optional call to action.
 */
export function EmptyState( { icon, title, children, action, compact = false } ) {
	return (
		<div className={ cx( 'bl-empty', compact && 'bl-empty--compact' ) }>
			{ icon && <div className="bl-empty__icon" aria-hidden="true">{ icon }</div> }
			<h3 className="bl-empty__title">{ title }</h3>
			{ children && <div className="bl-empty__text">{ children }</div> }
			{ action && <div className="bl-empty__action">{ action }</div> }
		</div>
	);
}

/**
 * Inline notice.
 *
 * @param {Object} props
 * @param {string} [props.tone] info | success | warning | danger
 */
export function Notice( { tone = 'info', title, children, onDismiss, dismissLabel = 'Dismiss', actions } ) {
	return (
		<div className={ cx( 'bl-notice', `bl-notice--${ tone }` ) } role={ tone === 'danger' ? 'alert' : 'status' }>
			<div className="bl-notice__body">
				{ title && <strong className="bl-notice__title">{ title }</strong> }
				{ children && <div className="bl-notice__text">{ children }</div> }
				{ actions && <div className="bl-notice__actions">{ actions }</div> }
			</div>
			{ onDismiss && (
				<button type="button" className="bl-notice__dismiss" onClick={ onDismiss } aria-label={ dismissLabel }>
					<span aria-hidden="true">×</span>
				</button>
			) }
		</div>
	);
}

/**
 * Page header with title, description and actions.
 */
export function PageHeader( { title, description, actions, eyebrow } ) {
	return (
		<div className="bl-page-header">
			<div className="bl-page-header__text">
				{ eyebrow && <span className="bl-page-header__eyebrow">{ eyebrow }</span> }
				<h1 className="bl-page-header__title">{ title }</h1>
				{ description && <p className="bl-page-header__description">{ description }</p> }
			</div>
			{ actions && <div className="bl-page-header__actions">{ actions }</div> }
		</div>
	);
}

/**
 * CSS-only tooltip wrapper. The tip is also exposed to assistive tech via aria-describedby.
 */
export function Tooltip( { text, children, position = 'top' } ) {
	const [ id ] = useState( () => uniqueId( 'bl-tip' ) );
	return (
		<span className={ cx( 'bl-tooltip', `bl-tooltip--${ position }` ) }>
			<span aria-describedby={ id } className="bl-tooltip__trigger">{ children }</span>
			<span role="tooltip" id={ id } className="bl-tooltip__bubble">{ text }</span>
		</span>
	);
}
