( function ( $ ) {
	'use strict';

	let updateTimer = null;

	function number( value, fallback ) {
		const parsed = parseFloat( value );
		return Number.isFinite( parsed ) ? parsed : fallback;
	}

	function refreshControls( root ) {
		const input = root.querySelector( 'input.qty' );
		if ( ! input ) {
			return;
		}
		const value = number( input.value, 0 );
		const min = number( input.min || root.dataset.min, 0 );
		const max = number( input.max || root.dataset.max, -1 );
		const decrement = root.querySelector( '[data-quantity-action="decrement"]' );
		const increment = root.querySelector( '[data-quantity-action="increment"]' );
		decrement.disabled = value <= min;
		increment.disabled = max > -1 && value >= max;
	}

	function refreshAll() {
		document.querySelectorAll( '[data-itsoft-cart-quantity]' ).forEach( refreshControls );
	}

	function scheduleUpdate() {
		window.clearTimeout( updateTimer );
		updateTimer = window.setTimeout( function () {
			const button = document.querySelector( '.woocommerce-cart-form [name="update_cart"]' );
			if ( button ) {
				button.disabled = false;
				button.click();
			}
		}, 350 );
	}

	$( document ).on( 'click', '[data-itsoft-cart-quantity] [data-quantity-action]', function () {
		const root = this.closest( '[data-itsoft-cart-quantity]' );
		const input = root ? root.querySelector( 'input.qty' ) : null;
		if ( ! input ) {
			return;
		}

		const step = Math.max( number( input.step, 1 ), 0.01 );
		const min = number( input.min || root.dataset.min, 0 );
		const max = number( input.max || root.dataset.max, -1 );
		const delta = this.dataset.quantityAction === 'increment' ? step : -step;
		let next = Math.max( min, number( input.value, min ) + delta );
		if ( max > -1 ) {
			next = Math.min( max, next );
		}
		input.value = String( next );
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		refreshControls( root );
		scheduleUpdate();
	} );

	$( document ).on( 'input change', '.woocommerce-cart-form input.qty', function () {
		const root = this.closest( '[data-itsoft-cart-quantity]' );
		if ( root ) {
			refreshControls( root );
			scheduleUpdate();
		}
	} );

	$( document.body ).on( 'updated_wc_div', refreshAll );
	$( refreshAll );
} )( jQuery );
