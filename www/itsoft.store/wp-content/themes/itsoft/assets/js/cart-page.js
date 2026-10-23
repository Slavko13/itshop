/* global jQuery */
( () => {
	'use strict';

	const mountEmptyState = ( page ) => {
		const host = page.querySelector( '.cart-page__woocommerce' );
		const template = page.querySelector( '#itsoft-cart-empty-template' );

		if ( ! host || ! template ) {
			return false;
		}

		const wooRoot = document.createElement( 'div' );
		wooRoot.className = 'woocommerce';
		wooRoot.appendChild( template.content.cloneNode( true ) );
		host.replaceChildren( wooRoot );

		return true;
	};

	const syncCartPageState = ( forceEmpty = false ) => {
		const page = document.querySelector( '.cart-page' );

		if ( ! page ) {
			return;
		}

		// Some WooCommerce fragment refreshes emit wc_cart_emptied while a
		// variation is still present in the server cart. Never replace a valid
		// rendered cart when the authoritative header count is non-zero.
		const headerCount = Number.parseInt(
			document.querySelector( '[data-cart-count]' )?.textContent || '0',
			10
		);
		if ( forceEmpty && Number.isFinite( headerCount ) && headerCount > 0 ) {
			forceEmpty = false;
		}

		let emptyState = page.querySelector( '.cart-empty-state' );
		const cartForm = page.querySelector( '.woocommerce-cart-form' );
		const wooRoot = page.querySelector( '.cart-page__woocommerce > .woocommerce' );

		if ( ! emptyState && ! cartForm && ( forceEmpty || ! wooRoot ) ) {
			mountEmptyState( page );
			emptyState = page.querySelector( '.cart-empty-state' );
		}

		const isEmpty = Boolean( emptyState );
		const lead = page.querySelector( '.cart-page__lead' );
		const hero = page.querySelector( '.cart-page__hero' );

		page.classList.toggle( 'cart-page--empty', isEmpty );

		if ( hero ) {
			hero.hidden = isEmpty;
		}

		if ( lead ) {
			const nextLead = isEmpty ? page.dataset.emptyLead : page.dataset.filledLead;

			if ( nextLead ) {
				lead.textContent = nextLead;
			}
		}
	};

	const onReady = () => {
		syncCartPageState();

		if ( window.jQuery && document.body ) {
			jQuery( document.body )
				.on( 'wc_cart_emptied', () => {
					window.requestAnimationFrame( () => syncCartPageState( true ) );
				} )
				.on( 'updated_wc_div', () => {
					window.requestAnimationFrame( () => syncCartPageState() );
				} );
		}

		const cartContent = document.querySelector( '.cart-page__woocommerce' );

		if ( cartContent && 'MutationObserver' in window ) {
			new MutationObserver( syncCartPageState ).observe( cartContent, {
				childList: true,
				subtree: true,
			} );
		}
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', onReady );
	} else {
		onReady();
	}
} )();
