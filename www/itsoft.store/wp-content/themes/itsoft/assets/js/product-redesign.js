( () => {
	'use strict';

	function initStickyPurchase() {
		const page = document.querySelector( '.product-page' );
		const sticky = page?.querySelector( '[data-product-sticky]' );
		const mainButton = page?.querySelector( '[data-cart-action="add"]' );
		const mainPrice = page?.querySelector( '[data-price-field="current"]' );
		const stickyButton = sticky?.querySelector( '[data-product-sticky-action]' );
		const stickyPrice = sticky?.querySelector( '[data-sticky-price]' );

		if ( ! sticky || ! mainButton || ! stickyButton || ! stickyPrice ) {
			return;
		}

		const mobileQuery = window.matchMedia( '(max-width: 767px)' );
		let purchasePassed = false;

		const sync = () => {
			stickyPrice.textContent = mainPrice?.textContent.trim() || '';
			stickyButton.disabled = mainButton.disabled;
			stickyButton.textContent = mainButton.disabled
				? mainButton.textContent.trim() || 'Недоступно'
				: mainButton.classList.contains( 'cart__btn_in-cart' )
					? 'Перейти в корзину'
					: 'В корзину';
			sticky.hidden = ! mobileQuery.matches || ! purchasePassed;
		};

		stickyButton.addEventListener( 'click', () => {
			if ( mainButton.disabled ) {
				return;
			}

			if ( mainButton.classList.contains( 'cart__btn_in-cart' ) ) {
				mainButton.click();
				return;
			}

			mainButton.scrollIntoView( { block: 'center', behavior: 'smooth' } );
			window.setTimeout( () => mainButton.click(), 280 );
		} );

		new MutationObserver( sync ).observe( mainButton, {
			attributes: true,
			childList: true,
			subtree: true,
			characterData: true,
		} );

		if ( mainPrice ) {
			new MutationObserver( sync ).observe( mainPrice, { childList: true, subtree: true, characterData: true } );
		}

		const updatePosition = () => {
			purchasePassed = mainButton.getBoundingClientRect().bottom < 0;
			sync();
		};

		new IntersectionObserver( updatePosition, { threshold: [ 0, .1, 1 ] } ).observe( mainButton );
		window.addEventListener( 'scroll', updatePosition, { passive: true } );
		window.addEventListener( 'resize', updatePosition );

		mobileQuery.addEventListener?.( 'change', sync );
		updatePosition();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initStickyPurchase, { once: true } );
	} else {
		initStickyPurchase();
	}
} )();
