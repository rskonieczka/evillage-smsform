/**
 * Blok „SMS Kupon” – podgląd renderowany po stronie serwera, ustawienia w panelu bocznym.
 */
(function (blocks, element, blockEditor, components, ServerSideRender) {
	'use strict';

	var el = element.createElement;

	function textControl(props, key, label) {
		return el(components.TextControl, {
			label: label,
			help: 'Puste = treść z Ustawienia → SMS Kupon → Komunikaty.',
			value: props.attributes[key],
			onChange: function (value) {
				var attrs = {};
				attrs[key] = value;
				props.setAttributes(attrs);
			}
		});
	}

	blocks.registerBlockType('evillage/smsform', {
		apiVersion: 3,
		title: 'SMS Kupon',
		description: 'Formularz pobrania kuponu rabatowego SMS-em.',
		icon: 'tickets-alt',
		category: 'widgets',
		keywords: ['sms', 'kupon', 'rabat', 'voucher'],
		supports: { html: false },
		attributes: {
			title: { type: 'string', default: '' },
			subtitle: { type: 'string', default: '' },
			button: { type: 'string', default: '' },
			color: { type: 'string', default: '' }
		},

		edit: function (props) {
			return el(
				'div',
				blockEditor.useBlockProps(),
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: 'Treści' },
						textControl(props, 'title', 'Tytuł'),
						textControl(props, 'subtitle', 'Podtytuł'),
						textControl(props, 'button', 'Treść przycisku')
					),
					el(
						components.PanelBody,
						{ title: 'Kolor przycisku', initialOpen: false },
						el('p', { className: 'components-base-control__help' }, 'Puste = kolor z zakładki Wygląd.'),
						el(components.ColorPalette, {
							value: props.attributes.color,
							onChange: function (value) {
								props.setAttributes({ color: value || '' });
							}
						})
					)
				),
				el(components.Disabled, null, el(ServerSideRender, {
					block: 'evillage/smsform',
					attributes: props.attributes
				}))
			);
		},

		save: function () {
			return null;
		}
	});
})(wp.blocks, wp.element, wp.blockEditor, wp.components, wp.serverSideRender);
