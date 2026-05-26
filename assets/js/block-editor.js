/* global wp, wpNimenhuutoData */
( function ( blocks, element, serverSideRender, components, blockEditor ) {
	'use strict';

	var el                = element.createElement;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody         = components.PanelBody;
	var SelectControl     = components.SelectControl;
	var Placeholder       = components.Placeholder;

	blocks.registerBlockType( 'wp-nimenhuuto/next-session', {
		title:       'Next Session (Nimenhuuto)',
		icon:        'calendar-alt',
		category:    'widgets',
		description: 'Display the next upcoming session from a Nimenhuuto account.',

		edit: function ( props ) {
			var accounts = ( window.wpNimenhuutoData && window.wpNimenhuutoData.accounts ) || [];

			var options = [ { label: '— All accounts —', value: '' } ].concat(
				accounts.map( function ( account ) {
					return {
						label: account.sport + ' (' + account.label + ')',
						value: account.id,
					};
				} )
			);

			var inspector = el(
				InspectorControls,
				{ key: 'inspector' },
				el(
					PanelBody,
					{ title: 'Settings', initialOpen: true },
					el( SelectControl, {
						label:    'Account',
						value:    props.attributes.accountId,
						options:  options,
						onChange: function ( value ) {
							props.setAttributes( { accountId: value } );
						},
					} )
				)
			);

			var preview;

			if ( accounts.length === 0 ) {
				preview = el(
					Placeholder,
					{ key: 'placeholder', icon: 'calendar-alt', label: 'Next Session (Nimenhuuto)' },
					'No Nimenhuuto accounts configured. Go to Settings → Nimenhuuto to add one.'
				);
			} else {
				preview = el( serverSideRender, {
					key:        'preview',
					block:      'wp-nimenhuuto/next-session',
					attributes: props.attributes,
				} );
			}

			return [ inspector, preview ];
		},

		save: function () {
			// Server-side rendered — save returns null.
			return null;
		},
	} );
} )(
	wp.blocks,
	wp.element,
	wp.serverSideRender,
	wp.components,
	wp.blockEditor
);
