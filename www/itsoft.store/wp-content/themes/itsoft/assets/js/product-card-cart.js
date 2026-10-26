/* global jQuery, ITSOFT_PRODUCT_CARD_CART */
( () => {
	'use strict';

	const config = window.ITSOFT_PRODUCT_CARD_CART || {};
	const i18n = config.i18n || {};
	const selector = '.catalog-card__buy';

	const getButton = ( target ) => target?.closest?.( selector ) || null;
	const getLabel = ( button ) => button?.querySelector( '.catalog-card__buy-label' ) || null;
	const isInCart = ( button ) => Boolean(
		button
		&& (
			button.dataset.cartState === 'in-cart'
			|| button.classList.contains( 'catalog-card__buy--added' )
			|| button.classList.contains( 'added' )
		)
	);

	const getCartUrl = ( button ) => (
		button?.dataset.cartUrl
		|| config.cartUrl
		|| document.querySelector( '.user-nav__cart' )?.href
		|| '/cart/'
	);

	const markInCart = ( button ) => {
		if ( ! button ) return;

		const label = getLabel( button );
		const cartUrl = getCartUrl( button );

		button.classList.remove( 'catalog-card__buy--loading' );
		button.classList.add( 'catalog-card__buy--added', 'added' );
		button.dataset.cartState = 'in-cart';
		button.dataset.cartUrl = cartUrl;
		button.removeAttribute( 'aria-busy' );
		button.setAttribute( 'href', cartUrl );

		if ( label ) {
			label.textContent = i18n.added || 'В корзине';
		}
	};

	/*
	 * WooCommerce listens for add-to-cart on the bubbling phase. Capture the
	 * click first so a card which is already in the cart always navigates and
	 * can never start another add request.
	 */
	document.addEventListener( 'click', ( event ) => {
		const button = getButton( event.target );
		if ( ! isInCart( button ) ) return;

		event.preventDefault();
		event.stopImmediatePropagation();
		window.location.assign( getCartUrl( button ) );
	}, true );

	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( `${ selector }[data-cart-state="in-cart"], ${ selector }.catalog-card__buy--added, ${ selector }.added` )
			.forEach( markInCart );
	} );

	if ( typeof jQuery === 'undefined' ) return;

	jQuery( document.body ).on( 'adding_to_cart', ( event, button ) => {
		const element = button && button[ 0 ];
		const label = getLabel( element );

		if ( ! element || ! label || isInCart( element ) ) return;

		if ( ! element.dataset.originalLabel ) {
			element.dataset.originalLabel = label.textContent.trim();
		}

		element.classList.add( 'catalog-card__buy--loading' );
		element.setAttribute( 'aria-busy', 'true' );
		label.textContent = i18n.adding || 'Добавляем…';
	} );

	jQuery( document.body ).on( 'added_to_cart', ( event, fragments, cartHash, button ) => {
		markInCart( button && button[ 0 ] );
	} );
} )();
