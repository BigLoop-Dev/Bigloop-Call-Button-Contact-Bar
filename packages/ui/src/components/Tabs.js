/**
 * Accessible tabs (WAI-ARIA tabs pattern: roving tabindex, arrow/Home/End keys, manual panels).
 */
import { useRef, useState } from '@wordpress/element';
import { cx, uniqueId } from '../utils';

/**
 * @param {Object}                                        props
 * @param {Array<{id:string,label:string,badge?:any}>}    props.tabs     Tabs.
 * @param {string}                                        props.selected Selected tab id.
 * @param {Function}                                      props.onSelect Called with tab id.
 * @param {Function}                                      props.children Render prop receiving the selected id.
 * @param {string}                                        [props.label]  Accessible name for the tab list.
 */
export function Tabs( { tabs, selected, onSelect, children, label, orientation = 'horizontal' } ) {
	const [ base ] = useState( () => uniqueId( 'bl-tabs' ) );
	const refs = useRef( {} );
	const index = Math.max( 0, tabs.findIndex( ( t ) => t.id === selected ) );

	const focusTab = ( i ) => {
		const tab = tabs[ ( i + tabs.length ) % tabs.length ];
		onSelect( tab.id );
		const el = refs.current[ tab.id ];
		if ( el ) {
			el.focus();
		}
	};

	const onKeyDown = ( e ) => {
		const next = orientation === 'vertical' ? 'ArrowDown' : 'ArrowRight';
		const prev = orientation === 'vertical' ? 'ArrowUp' : 'ArrowLeft';
		if ( e.key === next ) {
			e.preventDefault();
			focusTab( index + 1 );
		} else if ( e.key === prev ) {
			e.preventDefault();
			focusTab( index - 1 );
		} else if ( e.key === 'Home' ) {
			e.preventDefault();
			focusTab( 0 );
		} else if ( e.key === 'End' ) {
			e.preventDefault();
			focusTab( tabs.length - 1 );
		}
	};

	const current = tabs[ index ];

	return (
		<div className={ cx( 'bl-tabs', `bl-tabs--${ orientation }` ) }>
			<div className="bl-tabs__list" role="tablist" aria-label={ label } aria-orientation={ orientation } onKeyDown={ onKeyDown }>
				{ tabs.map( ( tab ) => {
					const active = tab.id === current.id;
					return (
						<button
							key={ tab.id }
							ref={ ( el ) => ( refs.current[ tab.id ] = el ) }
							type="button"
							role="tab"
							id={ `${ base }-tab-${ tab.id }` }
							aria-selected={ active }
							aria-controls={ `${ base }-panel-${ tab.id }` }
							tabIndex={ active ? 0 : -1 }
							className={ cx( 'bl-tab', active && 'is-active' ) }
							onClick={ () => onSelect( tab.id ) }
						>
							{ tab.label }
							{ tab.badge }
						</button>
					);
				} ) }
			</div>
			<div className="bl-tabs__panel" role="tabpanel" id={ `${ base }-panel-${ current.id }` } aria-labelledby={ `${ base }-tab-${ current.id }` } tabIndex={ 0 }>
				{ children( current.id ) }
			</div>
		</div>
	);
}
