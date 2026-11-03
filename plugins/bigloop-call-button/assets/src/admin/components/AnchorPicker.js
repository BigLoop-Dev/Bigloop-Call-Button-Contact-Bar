/**
 * Screen-position picker: a 3×3 grid of anchors (centre and top-centre are not offered).
 */
import { __ } from '@wordpress/i18n';
import { cx } from '@bigloop/ui';

const CELLS = [
	[ 'top-left', 'top-center', 'top-right' ],
	[ 'middle-left', 'middle-center', 'middle-right' ],
	[ 'bottom-left', 'bottom-center', 'bottom-right' ],
];

const ALLOWED = [ 'bottom-right', 'bottom-left', 'bottom-center', 'top-right', 'top-left', 'middle-right', 'middle-left' ];

export function anchorLabel( a ) {
	return (
		{
			'top-left': __( 'Top left', 'bigloop-call-button' ),
			'top-right': __( 'Top right', 'bigloop-call-button' ),
			'middle-left': __( 'Middle left', 'bigloop-call-button' ),
			'middle-right': __( 'Middle right', 'bigloop-call-button' ),
			'bottom-left': __( 'Bottom left', 'bigloop-call-button' ),
			'bottom-center': __( 'Bottom centre', 'bigloop-call-button' ),
			'bottom-right': __( 'Bottom right', 'bigloop-call-button' ),
		}[ a ] || a
	);
}

export default function AnchorPicker( { label, value, onChange } ) {
	return (
		<fieldset className="blcb-anchor">
			<legend className="bl-field__label">{ label }</legend>
			<div className="blcb-anchor__screen">
				{ CELLS.flat().map( ( cell ) => {
					const allowed = ALLOWED.includes( cell );
					if ( ! allowed ) {
						return <span key={ cell } className="blcb-anchor__cell is-blank" aria-hidden="true" />;
					}
					return (
						<label key={ cell } className={ cx( 'blcb-anchor__cell', value === cell && 'is-selected' ) } title={ anchorLabel( cell ) }>
							<input type="radio" name={ label } value={ cell } checked={ value === cell } onChange={ () => onChange( cell ) } />
							<span className="bl-visually-hidden">{ anchorLabel( cell ) }</span>
							<span className="blcb-anchor__dot" aria-hidden="true" />
						</label>
					);
				} ) }
			</div>
			<span className="bl-field__help">{ anchorLabel( value ) }</span>
		</fieldset>
	);
}
