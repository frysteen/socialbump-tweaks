/**
 * Zoom controls on the native gallery and image blocks.
 *
 * Adds two attributes to core/gallery and core/image and a Zoom sidebar panel:
 * a toggle and the size the popup opens. The size on the page is each block's
 * own Resolution setting. An image inside a gallery shows no panel, since the
 * gallery's setting covers all its images. The size list is handed over by
 * SB_Images_Zoom::editor_assets().
 */
( function ( wp ) {
	'use strict';

	var cfg = window.sbImagesGalleryZoom || { sizes: {} };
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var ToggleControl = wp.components.ToggleControl;
	var SelectControl = wp.components.SelectControl;
	var useSelect = wp.data.useSelect;
	var __ = wp.i18n.__;
	var blocks = [ 'core/gallery', 'core/image' ];

	wp.hooks.addFilter( 'blocks.registerBlockType', 'sb-images/zoom-attributes', function ( settings, name ) {
		if ( blocks.indexOf( name ) === -1 ) {
			return settings;
		}

		settings.attributes = Object.assign( {}, settings.attributes, {
			sbskZoom: { type: 'boolean', default: false },
			sbskZoomSize: { type: 'string', default: 'full' }
		} );

		return settings;
	} );

	function sizes() {
		var list = [ { value: 'full', label: __( 'Full size', 'sb-tweaks' ) } ];

		Object.keys( cfg.sizes ).forEach( function ( name ) {
			list.push( { value: name, label: cfg.sizes[ name ] } );
		} );

		return list;
	}

	function Panel( props ) {
		var attrs = props.attributes;
		var inGallery = useSelect( function ( select ) {
			if ( props.name !== 'core/image' ) {
				return false;
			}

			return select( 'core/block-editor' ).getBlockParentsByBlockName( props.clientId, 'core/gallery' ).length > 0;
		}, [ props.clientId, props.name ] );

		if ( inGallery ) {
			return null;
		}

		var controls = [
			el( ToggleControl, {
				key: 'toggle',
				label: __( 'Enable zoom', 'sb-tweaks' ),
				help: props.name === 'core/gallery' ? __( 'Clicking a picture opens it larger in a popup, with arrows through the gallery.', 'sb-tweaks' ) : __( 'Clicking the picture opens it larger in a popup.', 'sb-tweaks' ),
				checked: !! attrs.sbskZoom,
				onChange: function ( value ) {
					props.setAttributes( { sbskZoom: !! value } );
				}
			} )
		];

		if ( attrs.sbskZoom ) {
			controls.push( el( SelectControl, {
				key: 'size',
				label: __( 'Size in the popup', 'sb-tweaks' ),
				help: __( 'The size on the page is the Resolution setting above.', 'sb-tweaks' ),
				value: attrs.sbskZoomSize || 'full',
				options: sizes(),
				onChange: function ( value ) {
					props.setAttributes( { sbskZoomSize: value } );
				}
			} ) );
		}

		return el( InspectorControls, {},
			el( PanelBody, { title: __( 'Zoom', 'sb-tweaks' ), initialOpen: true }, controls )
		);
	}

	wp.hooks.addFilter( 'editor.BlockEdit', 'sb-images/zoom-controls', wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			if ( blocks.indexOf( props.name ) === -1 ) {
				return el( BlockEdit, props );
			}

			return el( Fragment, {}, el( BlockEdit, props ), el( Panel, props ) );
		};
	}, 'sbskZoom' ) );
}( window.wp ) );
