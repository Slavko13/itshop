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
		if ( input.dataset.itsoftEmailSuggestion === 'ready' || ! isPrimaryEmailInput( input ) ) {
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

	function scan() {
		document.querySelectorAll( 'input' ).forEach( attach );
		if ( window.ITSOFT_CHECKOUT && window.ITSOFT_CHECKOUT.digitalOnly ) {
			document.querySelectorAll( '.wc-block-components-title' ).forEach( function ( title ) {
				if ( title.textContent.trim() === 'Платёжный адрес' || title.textContent.trim() === 'Billing address' ) {
					title.textContent = 'Данные покупателя';
				}
			} );
		}
	}

	document.addEventListener( 'DOMContentLoaded', scan );
	new MutationObserver( scan ).observe( document.documentElement, { childList: true, subtree: true } );
} )();
