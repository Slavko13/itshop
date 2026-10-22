( function () {
	'use strict';

	const settings = window.wc.wcSettings.getSetting( 'itsoft_lf_mock_data', {} );
	const createElement = window.wp.element.createElement;
	const decodeEntities = window.wp.htmlEntities.decodeEntities;
	const title = decodeEntities( settings.title || 'Тестовая оплата (без денег)' );
	const description = decodeEntities( settings.description || 'Только локальная проверка: платёж подтверждается сразу.' );
	const Content = function () {
		return createElement( 'div', { className: 'itsoft-lf-mock-payment-description' }, description );
	};

	window.wc.wcBlocksRegistry.registerPaymentMethod( {
		name: 'itsoft_lf_mock',
		label: createElement( 'span', null, title ),
		content: createElement( Content, null ),
		edit: createElement( Content, null ),
		canMakePayment: function () { return true; },
		ariaLabel: title,
		supports: { features: settings.supports || [ 'products' ] }
	} );
} )();
