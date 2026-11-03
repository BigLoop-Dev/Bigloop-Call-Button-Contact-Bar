/**
 * Additional inputs: ColorField, NumberField, Segmented, TextareaField.
 */
import { useState, useEffect } from '@wordpress/element';
import { cx, uniqueId } from '../utils';
import { Field } from './forms';

function useIds( id ) {
	const [ base ] = useState( () => id || uniqueId( 'bl-field' ) );
	return { input: base, help: `${ base }-help`, error: `${ base }-error` };
}
const described = ( ids, help, error ) => [ help && ids.help, error && ids.error ].filter( Boolean ).join( ' ' ) || undefined;

const HEX = /^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i;

/**
 * Hex colour with swatch picker, text entry and optional reset.
 *
 * @param {Object}   props
 * @param {string}   props.value       '#rrggbb' or '' (= default).
 * @param {string}   [props.fallback]  Colour shown when value is '' (the inherited default).
 * @param {Function} props.onChange    Receives the new value ('' when reset).
 * @param {string}   [props.resetLabel]
 */
export function ColorField( { id, label, help, error, value, fallback = '', onChange, resetLabel = 'Reset' } ) {
	const ids = useIds( id );
	const [ text, setText ] = useState( value || '' );
	useEffect( () => setText( value || '' ), [ value ] );
	const shown = HEX.test( value ) ? value : fallback || '#ffffff';
	const swatch = shown.length === 4 ? '#' + shown.slice( 1 ).split( '' ).map( ( c ) => c + c ).join( '' ) : shown.slice( 0, 7 );

	const commit = ( v ) => {
		const t = v.trim();
		if ( t === '' || HEX.test( t ) ) {
			onChange( t.toLowerCase() );
		}
	};

	return (
		<Field label={ label } help={ help } error={ error } ids={ ids }>
			<div className="bl-color">
				<label className="bl-color__swatch" style={ { background: shown } }>
					<span className="bl-visually-hidden">{ label }</span>
					<input type="color" value={ swatch } onChange={ ( e ) => onChange( e.target.value ) } aria-describedby={ described( ids, help, error ) } />
				</label>
				<input
					id={ ids.input }
					className="bl-input bl-color__text"
					value={ text }
					placeholder={ fallback || '#000000' }
					onChange={ ( e ) => {
						setText( e.target.value );
						if ( HEX.test( e.target.value.trim() ) ) {
							commit( e.target.value );
						}
					} }
					onBlur={ () => {
						if ( text.trim() !== '' && ! HEX.test( text.trim() ) ) {
							setText( value || '' );
						} else {
							commit( text );
						}
					} }
					aria-invalid={ error ? true : undefined }
					aria-describedby={ described( ids, help, error ) }
					spellCheck={ false }
					maxLength={ 9 }
				/>
				{ value !== '' && fallback !== undefined && (
					<button type="button" className="bl-color__reset" onClick={ () => onChange( '' ) }>
						{ resetLabel }
					</button>
				) }
			</div>
		</Field>
	);
}

/**
 * Number input with unit suffix and min/max.
 */
export function NumberField( { id, label, help, error, value, onChange, min, max, step = 1, suffix } ) {
	const ids = useIds( id );
	return (
		<Field label={ label } help={ help } error={ error } ids={ ids }>
			<div className={ cx( 'bl-input-wrap', suffix && 'has-suffix' ) }>
				<input
					id={ ids.input }
					className="bl-input"
					type="number"
					inputMode="numeric"
					value={ value ?? '' }
					min={ min }
					max={ max }
					step={ step }
					onChange={ ( e ) => onChange( e.target.value === '' ? '' : Number( e.target.value ) ) }
					aria-invalid={ error ? true : undefined }
					aria-describedby={ described( ids, help, error ) }
				/>
				{ suffix && <span className="bl-input-suffix">{ suffix }</span> }
			</div>
		</Field>
	);
}

/**
 * Multi-line text.
 */
export function TextareaField( { id, label, help, error, value, onChange, rows = 3, ...rest } ) {
	const ids = useIds( id );
	return (
		<Field label={ label } help={ help } error={ error } ids={ ids }>
			<textarea
				id={ ids.input }
				className="bl-input bl-textarea"
				rows={ rows }
				value={ value ?? '' }
				onChange={ ( e ) => onChange( e.target.value ) }
				aria-invalid={ error ? true : undefined }
				aria-describedby={ described( ids, help, error ) }
				{ ...rest }
			/>
		</Field>
	);
}

/**
 * Segmented single choice (radio group). Options may include an `icon` or `preview` element.
 *
 * @param {Object}                                              props
 * @param {Array<{value:string,label:string,icon?:any,preview?:any}>} props.options
 */
export function Segmented( { label, hideLabel = false, help, error, value, onChange, options, variant = 'pills', name } ) {
	const [ group ] = useState( () => name || uniqueId( 'bl-seg' ) );
	return (
		<fieldset className={ cx( 'bl-field', 'bl-segmented', `bl-segmented--${ variant }`, error && 'has-error' ) }>
			{ label && <legend className={ cx( 'bl-field__label', hideLabel && 'bl-visually-hidden' ) }>{ label }</legend> }
			<div className="bl-segmented__options">
				{ options.map( ( o ) => (
					<label key={ o.value } className={ cx( 'bl-segmented__option', value === o.value && 'is-selected' ) }>
						<input type="radio" name={ group } value={ o.value } checked={ value === o.value } onChange={ () => onChange( o.value ) } />
						{ o.preview && <span className="bl-segmented__preview" aria-hidden="true">{ o.preview }</span> }
						{ o.icon && <span className="bl-segmented__icon" aria-hidden="true">{ o.icon }</span> }
						<span className="bl-segmented__label">{ o.label }</span>
						{ o.description && <span className="bl-segmented__desc">{ o.description }</span> }
					</label>
				) ) }
			</div>
			{ help && <p className="bl-field__help">{ help }</p> }
			{ error && <p className="bl-field__error" role="alert">{ error }</p> }
		</fieldset>
	);
}
