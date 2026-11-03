/**
 * Device-aware number field: desktop is the base value; tablet and mobile may override it or inherit.
 */
import { __, sprintf } from '@wordpress/i18n';
import { NumberField } from '@bigloop/ui';
import { DEVICES } from './PreviewPanel';

/**
 * @param {Object}   props
 * @param {string}   props.device   Current device being edited.
 * @param {Object}   props.group    Settings object holding `${key}`, `${key}_tablet`, `${key}_mobile`.
 * @param {string}   props.keyName  Base key, e.g. "size".
 * @param {Function} props.onChange ( fullKey, value|null ) => void.
 */
export default function ResponsiveNumber( { label, device, group, keyName, onChange, min, max, suffix, error, help } ) {
	const key = device === 'desktop' ? keyName : `${ keyName }_${ device }`;
	const own = group[ key ];
	const inherited =
		device === 'desktop'
			? group[ keyName ]
			: device === 'tablet'
			? group[ keyName ]
			: group[ `${ keyName }_tablet` ] ?? group[ keyName ];
	const overridden = device !== 'desktop' && own !== null && own !== undefined;
	const DeviceIcon = DEVICES[ device ].Icon;
	return (
		<div className={ `blcb-responsive${ overridden ? ' is-overridden' : '' }` }>
			<NumberField
				label={
					<span className="blcb-responsive__label">
						{ label } <DeviceIcon size={ 13 } aria-label={ DEVICES[ device ].label() } />
						{ overridden && <span className="blcb-responsive__dot" title={ __( 'Customised for this device', 'bigloop-call-button' ) } /> }
					</span>
				}
				value={ device === 'desktop' ? own : overridden ? own : '' }
				onChange={ ( v ) => onChange( key, v === '' && device !== 'desktop' ? null : v ) }
				min={ min }
				max={ max }
				suffix={ suffix }
				error={ error }
				help={
					device !== 'desktop' && ! overridden
						? sprintf( /* translators: 1: value, 2: unit */ __( 'Inherits %1$s%2$s from the larger screen. Type to override.', 'bigloop-call-button' ), inherited, suffix || '' )
						: help
				}
			/>
			{ overridden && (
				<button type="button" className="bl-color__reset" onClick={ () => onChange( key, null ) }>
					{ __( 'Use larger-screen value', 'bigloop-call-button' ) }
				</button>
			) }
		</div>
	);
}
