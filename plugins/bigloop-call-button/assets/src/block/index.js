/**
 * Block editor UI for bigloop/contact-widget (server-rendered).
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, Placeholder, Spinner, Notice } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import metadata from '../../../blocks/contact-widget/block.json';

function useWidgets() {
	const [ state, setState ] = useState( { loading: true, items: [], error: null } );
	useEffect( () => {
		apiFetch( { path: '/bigloop-call-button/v1/widgets?per_page=100&orderby=title' } )
			.then( ( res ) => setState( { loading: false, items: res.items || [], error: null } ) )
			.catch( ( e ) => setState( { loading: false, items: [], error: e.message || 'Error' } ) );
	}, [] );
	return state;
}

function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const { loading, items, error } = useWidgets();
	const { widgetId, display, align } = attributes;
	const selected = items.find( ( w ) => w.id === widgetId );

	const options = [
		{ value: 0, label: __( 'Choose a widget…', 'bigloop-call-button' ) },
		...items.map( ( w ) => ( {
			value: w.id,
			label: w.enabled ? w.title : `${ w.title } (${ __( 'disabled', 'bigloop-call-button' ) })`,
		} ) ),
	];

	const picker = (
		<SelectControl
			label={ __( 'Contact widget', 'bigloop-call-button' ) }
			value={ widgetId }
			options={ options }
			onChange={ ( v ) => setAttributes( { widgetId: Number( v ) } ) }
			__nextHasNoMarginBottom
		/>
	);

	const inspector = (
		<InspectorControls>
			<PanelBody title={ __( 'Widget', 'bigloop-call-button' ) }>
				{ picker }
				<SelectControl
					label={ __( 'Display', 'bigloop-call-button' ) }
					value={ display }
					options={ [
						{ value: 'inline', label: __( 'Buttons in the content', 'bigloop-call-button' ) },
						{ value: 'floating', label: __( 'Floating widget on this page', 'bigloop-call-button' ) },
					] }
					onChange={ ( v ) => setAttributes( { display: v } ) }
					__nextHasNoMarginBottom
				/>
				{ display === 'inline' && (
					<SelectControl
						label={ __( 'Alignment', 'bigloop-call-button' ) }
						value={ align }
						options={ [
							{ value: 'left', label: __( 'Left', 'bigloop-call-button' ) },
							{ value: 'center', label: __( 'Center', 'bigloop-call-button' ) },
							{ value: 'right', label: __( 'Right', 'bigloop-call-button' ) },
						] }
						onChange={ ( v ) => setAttributes( { align: v } ) }
						__nextHasNoMarginBottom
					/>
				) }
			</PanelBody>
		</InspectorControls>
	);

	let body;
	if ( loading ) {
		body = <Placeholder label={ metadata.title }><Spinner /></Placeholder>;
	} else if ( error ) {
		body = (
			<Placeholder label={ metadata.title }>
				<Notice status="error" isDismissible={ false }>{ error }</Notice>
			</Placeholder>
		);
	} else if ( ! widgetId || ! selected ) {
		body = (
			<Placeholder icon="phone" label={ metadata.title } instructions={ items.length ? __( 'Choose which contact widget to show.', 'bigloop-call-button' ) : __( 'You have no contact widgets yet. Create one under Bigloop Contact → Create Widget.', 'bigloop-call-button' ) }>
				{ items.length > 0 && picker }
			</Placeholder>
		);
	} else if ( display === 'floating' ) {
		body = (
			<Placeholder icon="phone" label={ selected.title } instructions={ __( 'This widget will float on the page when viewed on the site.', 'bigloop-call-button' ) } />
		);
	} else if ( ! selected.enabled ) {
		body = (
			<Placeholder icon="phone" label={ selected.title } instructions={ __( 'This widget is disabled, so nothing will show on the site. Enable it under Bigloop Contact → Contact Widgets.', 'bigloop-call-button' ) } />
		);
	} else {
		body = <ServerSideRender block={ metadata.name } attributes={ attributes } />;
	}

	return (
		<div { ...blockProps }>
			{ inspector }
			{ body }
		</div>
	);
}

registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	save: () => null,
} );
