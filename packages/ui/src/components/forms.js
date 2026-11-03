/**
 * Form controls. Every control renders a real <label>, help text and error message wired via ARIA.
 */
import { useState } from '@wordpress/element';
import { cx, uniqueId } from '../utils';

function useFieldIds( id ) {
	const [ generated ] = useState( () => uniqueId( 'bl-field' ) );
	const base = id || generated;
	return { input: base, help: `${ base }-help`, error: `${ base }-error` };
}

function describedBy( ids, help, error ) {
	return [ help && ids.help, error && ids.error ].filter( Boolean ).join( ' ' ) || undefined;
}

/**
 * Field wrapper (label + help + error) for custom controls.
 */
export function Field( { label, help, error, ids, children, inline = false, className } ) {
	return (
		<div className={ cx( 'bl-field', inline && 'bl-field--inline', error && 'has-error', className ) }>
			{ label && (
				<label className="bl-field__label" htmlFor={ ids.input }>
					{ label }
				</label>
			) }
			<div className="bl-field__control">{ children }</div>
			{ help && (
				<p className="bl-field__help" id={ ids.help }>
					{ help }
				</p>
			) }
			{ error && (
				<p className="bl-field__error" id={ ids.error } role="alert">
					{ error }
				</p>
			) }
		</div>
	);
}

/**
 * Text / number / url input.
 */
export function TextField( { id, label, help, error, value, onChange, type = 'text', suffix, className, ...rest } ) {
	const ids = useFieldIds( id );
	return (
		<Field label={ label } help={ help } error={ error } ids={ ids } className={ className }>
			<div className={ cx( 'bl-input-wrap', suffix && 'has-suffix' ) }>
				<input
					id={ ids.input }
					className="bl-input"
					type={ type }
					value={ value ?? '' }
					onChange={ ( e ) => onChange( e.target.value ) }
					aria-invalid={ error ? true : undefined }
					aria-describedby={ describedBy( ids, help, error ) }
					{ ...rest }
				/>
				{ suffix && <span className="bl-input-suffix">{ suffix }</span> }
			</div>
		</Field>
	);
}

/**
 * Native select (accessible by default).
 *
 * @param {Object}                               props
 * @param {Array<{value:string,label:string}>}   props.options Options.
 */
export function SelectField( { id, label, help, error, value, onChange, options = [], className, ...rest } ) {
	const ids = useFieldIds( id );
	return (
		<Field label={ label } help={ help } error={ error } ids={ ids } className={ className }>
			<select
				id={ ids.input }
				className="bl-select"
				value={ value }
				onChange={ ( e ) => onChange( e.target.value ) }
				aria-invalid={ error ? true : undefined }
				aria-describedby={ describedBy( ids, help, error ) }
				{ ...rest }
			>
				{ options.map( ( o ) => (
					<option key={ o.value } value={ o.value } disabled={ o.disabled }>
						{ o.label }
					</option>
				) ) }
			</select>
		</Field>
	);
}

/**
 * On/off switch (button role="switch").
 */
export function Toggle( { id, label, help, error, checked, onChange, disabled } ) {
	const ids = useFieldIds( id );
	return (
		<div className={ cx( 'bl-toggle', disabled && 'is-disabled', error && 'has-error' ) }>
			<button
				id={ ids.input }
				type="button"
				role="switch"
				aria-checked={ !! checked }
				aria-describedby={ describedBy( ids, help, error ) }
				className={ cx( 'bl-switch', checked && 'is-on' ) }
				onClick={ () => ! disabled && onChange( ! checked ) }
				disabled={ disabled }
			>
				<span className="bl-switch__thumb" aria-hidden="true" />
			</button>
			<div className="bl-toggle__text">
				<label className="bl-toggle__label" htmlFor={ ids.input }>
					{ label }
				</label>
				{ help && (
					<p className="bl-field__help" id={ ids.help }>
						{ help }
					</p>
				) }
				{ error && (
					<p className="bl-field__error" id={ ids.error } role="alert">
						{ error }
					</p>
				) }
			</div>
		</div>
	);
}
