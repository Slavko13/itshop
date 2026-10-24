( function () {
	'use strict';

	const corrections = {
		'gamil.com': 'gmail.com',
		'gmial.com': 'gmail.com',
		'gmai.com': 'gmail.com',
		'gmail.ru': 'gmail.com',
		'mail.ry': 'mail.ru',
		'mial.ru': 'mail.ru',
		'mailru': 'mail.ru',
		'yandex.ry': 'yandex.ru',
		'yadnex.ru': 'yandex.ru',
		'yndex.ru': 'yandex.ru',
		'ya.ruu': 'ya.ru',
		'outlok.com': 'outlook.com'
	};

	function isPrimaryEmailInput( input ) {
		const marker = ( ( input.name || '' ) + ' ' + ( input.id || '' ) ).toLowerCase();
		if ( marker.includes( 'confirm' ) || marker.includes( 'repeat' ) ) {
			return false;
		}
		return input.type === 'email' || marker.includes( 'billing_email' ) || input.autocomplete === 'email';
	}

	function setInputValue( input, value ) {
		if ( input.value === value ) {
			return;
		}

		const descriptor = Object.getOwnPropertyDescriptor( HTMLInputElement.prototype, 'value' );
		if ( descriptor && descriptor.set ) {
			descriptor.set.call( input, value );
		} else {
			input.value = value;
		}

		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	function lockAccountEmail( input ) {
		const checkout = window.ITSOFT_CHECKOUT || {};
		const accountEmail = String( checkout.accountEmail || '' ).trim().toLowerCase();
		if ( ! checkout.loggedIn || ! accountEmail ) {
			return false;
		}

		setInputValue( input, accountEmail );
		input.readOnly = true;
		input.setAttribute( 'aria-readonly', 'true' );
		input.setAttribute( 'title', 'E-mail подтверждён в личном кабинете' );
		input.closest( '.wc-block-components-text-input, .form-row' )?.classList.add( 'itsoft-email-locked' );
		return true;
	}

	function suggestionFor( value ) {
		const normalized = String( value || '' ).trim().toLowerCase();
		const separator = normalized.lastIndexOf( '@' );
		if ( separator < 1 ) {
			return '';
		}
		const domain = normalized.slice( separator + 1 );
		return corrections[ domain ] ? normalized.slice( 0, separator + 1 ) + corrections[ domain ] : '';
	}

	function attach( input ) {
		if ( ! isPrimaryEmailInput( input ) ) {
			return;
		}

		if ( lockAccountEmail( input ) ) {
			input.dataset.itsoftEmailSuggestion = 'locked';
			return;
		}

		if ( input.dataset.itsoftEmailSuggestion === 'ready' ) {
			return;
		}
		input.dataset.itsoftEmailSuggestion = 'ready';
		const note = document.createElement( 'div' );
		note.className = 'itsoft-email-suggestion';
		note.hidden = true;
		note.setAttribute( 'role', 'status' );
		note.setAttribute( 'aria-live', 'polite' );
		input.insertAdjacentElement( 'afterend', note );

		function refresh() {
			const suggestion = suggestionFor( input.value );
			if ( ! suggestion ) {
				note.hidden = true;
				note.replaceChildren();
				return;
			}
			const text = document.createTextNode( 'Возможно, вы имели в виду ' );
			const button = document.createElement( 'button' );
			button.type = 'button';
			button.textContent = suggestion;
			button.addEventListener( 'click', function () {
				input.value = suggestion;
				input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				input.focus();
				note.hidden = true;
			} );
			note.replaceChildren( text, button, document.createTextNode( '?' ) );
			note.hidden = false;
		}

		input.addEventListener( 'input', refresh );
		input.addEventListener( 'blur', refresh );
		refresh();
	}

	function normalizeDigitalCheckoutLabels() {
		if ( ! window.ITSOFT_CHECKOUT || ! window.ITSOFT_CHECKOUT.digitalOnly ) {
			return;
		}

		const isEnglish = document.documentElement.lang.toLowerCase().startsWith( 'en' );
		const buyerLabel = isEnglish ? 'Customer details' : 'Данные покупателя';
		const billingLabels = [ 'платёжный адрес', 'платежный адрес', 'billing address' ];
		const shippingLabels = [ 'адрес доставки', 'shipping address', 'delivery address' ];

		document.querySelectorAll( '.wc-block-components-title, h1, h2, h3, h4, h5, h6, [role="heading"], font' ).forEach( function ( title ) {
			const label = title.textContent.trim().toLowerCase();
			if ( billingLabels.includes( label ) ) {
				title.textContent = buyerLabel;
				return;
			}
			if ( shippingLabels.includes( label ) ) {
				const step = title.closest( '.wc-block-components-checkout-step, .wp-block-woocommerce-checkout-shipping-address-block' );
				if ( step ) {
					step.hidden = true;
					step.setAttribute( 'aria-hidden', 'true' );
				} else {
					title.hidden = true;
					title.setAttribute( 'aria-hidden', 'true' );
				}
			}
		} );
	}

	function scan() {
		document.querySelectorAll( 'input' ).forEach( attach );
		normalizeDigitalCheckoutLabels();
	}

	document.addEventListener( 'DOMContentLoaded', scan );
	new MutationObserver( scan ).observe( document.documentElement, { childList: true, characterData: true, subtree: true } );
} )();
