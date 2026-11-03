/**
 * Searchable, categorised icon picker (modal).
 */
import { __ } from '@wordpress/i18n';
import { useMemo, useState } from '@wordpress/element';
import { Modal, Button, cx } from '@bigloop/ui';
import { LibIcon } from '../lib/icons';

export default function IconPicker( { lib, value, defaultId, onChange, onClose } ) {
	const [ query, setQuery ] = useState( '' );
	const [ category, setCategory ] = useState( 'all' );

	const list = useMemo( () => {
		if ( ! lib ) {
			return [];
		}
		const q = query.trim().toLowerCase();
		return Object.entries( lib.icons )
			.filter( ( [ id, icon ] ) => category === 'all' || icon.category === category )
			.filter( ( [ id, icon ] ) => ! q || id.includes( q ) || icon.label.toLowerCase().includes( q ) )
			.map( ( [ id, icon ] ) => ( { id, label: icon.label } ) );
	}, [ lib, query, category ] );

	const categories = lib ? [ { id: 'all', label: __( 'All', 'bigloop-call-button' ) }, ...lib.categories.filter( ( c ) => c.id !== 'ui' ) ] : [];

	return (
		<Modal title={ __( 'Choose an icon', 'bigloop-call-button' ) } onClose={ onClose } size="lg" closeLabel={ __( 'Close', 'bigloop-call-button' ) }>
			<div className="blcb-iconpicker">
				<div className="blcb-iconpicker__toolbar">
					<input
						type="search"
						className="bl-input"
						placeholder={ __( 'Search icons…', 'bigloop-call-button' ) }
						aria-label={ __( 'Search icons', 'bigloop-call-button' ) }
						value={ query }
						onChange={ ( e ) => setQuery( e.target.value ) }
						data-autofocus
					/>
					{ defaultId && (
						<Button
							variant={ value === '' ? 'primary' : 'secondary' }
							onClick={ () => {
								onChange( '' );
								onClose();
							} }
						>
							{ __( 'Use default icon', 'bigloop-call-button' ) }
						</Button>
					) }
				</div>
				<div className="blcb-iconpicker__cats" role="group" aria-label={ __( 'Categories', 'bigloop-call-button' ) }>
					{ categories.map( ( c ) => (
						<button key={ c.id } type="button" className={ cx( 'blcb-chip', category === c.id && 'is-active' ) } aria-pressed={ category === c.id } onClick={ () => setCategory( c.id ) }>
							{ c.label }
						</button>
					) ) }
				</div>
				<div className="blcb-iconpicker__grid" role="listbox" aria-label={ __( 'Icons', 'bigloop-call-button' ) }>
					{ list.map( ( icon ) => {
						const selected = value === icon.id || ( value === '' && defaultId === icon.id );
						return (
							<button
								key={ icon.id }
								type="button"
								role="option"
								aria-selected={ selected }
								className={ cx( 'blcb-iconpicker__item', selected && 'is-selected' ) }
								onClick={ () => {
									onChange( icon.id );
									onClose();
								} }
								title={ icon.label }
							>
								<LibIcon lib={ lib } id={ icon.id } size={ 24 } />
								<span>{ icon.label }</span>
							</button>
						);
					} ) }
					{ lib && list.length === 0 && <p className="bl-muted">{ __( 'No icons match your search.', 'bigloop-call-button' ) }</p> }
				</div>
			</div>
		</Modal>
	);
}
