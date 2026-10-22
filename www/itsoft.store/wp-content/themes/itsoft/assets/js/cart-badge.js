/* global ITSOFT_CART_BADGE */
( () => {
	'use strict';

	const settings = window.ITSOFT_CART_BADGE || {};
	const hasStoreApi = Boolean( settings.storeApi && settings.storeApi.root );

	const onReady = ( callback ) => {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', callback );
			return;
		}

		callback();
	};

	class StoreApiClient {
		constructor( { root, nonce, wpNonce } ) {
			this.root = ( root || '' ).replace( /\/$/, '' );
			this.nonce = nonce || '';
			this.wpNonce = wpNonce || '';
		}

		async request( endpoint, { method = 'GET', body } = {} ) {
			const urlObject = new URL(
				`${ this.root }/${ endpoint.replace( /^\//, '' ) }`
			);

			if ( method !== 'GET' && this.wpNonce ) {
				urlObject.searchParams.set( '_wpnonce', this.wpNonce );
			}

			const options = {
				method,
				headers: {
					'Content-Type': 'application/json',
				},
				credentials: 'same-origin',
			};

			if ( this.nonce ) {
				options.headers.Nonce = this.nonce;
				options.headers['X-WC-Store-API-Nonce'] = this.nonce;
			}

			if ( this.wpNonce ) {
				options.headers['X-WP-Nonce'] = this.wpNonce;
			}

			if ( body ) {
				options.body = JSON.stringify( body );
			}

			const response = await fetch( urlObject.toString(), options );
			let data = null;

			try {
				data = await response.json();
			} catch ( error ) {
				data = null;
			}

			if ( ! response.ok ) {
				const message = data && data.message ? data.message : response.statusText || 'Request failed';
				const error = new Error( message );
				error.code = data && data.code ? data.code : response.status;
				throw error;
			}

			return data;
		}

		getCart() {
			return this.request( 'cart' );
		}
	}

	class CartBadgeController {
		constructor( elements, options = {} ) {
			this.elements = Array.isArray( elements ) ? elements.filter( Boolean ) : [];
			this.count = Number.isFinite( options.initialCount ) ? options.initialCount : 0;
			this.api = hasStoreApi ? new StoreApiClient( settings.storeApi ) : null;
			this.refreshTimer = null;
			this.isFetching = false;

			this.handleCartEvent = this.handleCartEvent.bind( this );
			this.handleWooEvent = this.handleWooEvent.bind( this );
		}

		init() {
			if ( ! this.elements.length ) {
				return;
			}

			this.render( this.count );
			this.subscribe();

			if ( this.api ) {
				this.refreshCart();
			}
		}

		subscribe() {
			document.addEventListener( 'wc-store-cart-updated', this.handleCartEvent );
			document.addEventListener( 'wc-blocks/cart-update', this.handleCartEvent );
			document.addEventListener( 'itsoft/cart-updated', this.handleCartEvent );

			if ( window.jQuery && document.body ) {
				const $body = window.jQuery( document.body );
				const wooEvents = 'added_to_cart removed_from_cart cart_page_refreshed updated_wc_div wc_fragment_refresh wc_fragments_refreshed';
				$body.on( wooEvents, this.handleWooEvent );
			}
		}

		handleCartEvent( event ) {
			if ( ! event || ! event.detail || ! event.detail.cart ) {
				return;
			}

			this.applyCart( event.detail.cart );
		}

		handleWooEvent() {
			this.queueRefresh();
		}

		queueRefresh() {
			if ( ! this.api ) {
				return;
			}

			clearTimeout( this.refreshTimer );
			this.refreshTimer = setTimeout( () => this.refreshCart(), 200 );
		}

		async refreshCart() {
			if ( ! this.api || this.isFetching ) {
				return;
			}

			this.isFetching = true;

			try {
				const cart = await this.api.getCart();
				this.applyCart( cart );
			} catch ( error ) {
				console.warn( '[CartBadge] unable to refresh cart', error );
			} finally {
				this.isFetching = false;
			}
		}

		applyCart( cart ) {
			const nextCount = this.extractCount( cart );

			if ( nextCount === this.count ) {
				return;
			}

			this.count = nextCount;
			this.render( nextCount );
		}

		extractCount( cart ) {
			if ( cart && typeof cart.items_count === 'number' ) {
				return cart.items_count;
			}

			if ( cart && Array.isArray( cart.items ) ) {
				return cart.items.reduce(
					( total, item ) => total + ( parseInt( item.quantity, 10 ) || 0 ),
					0
				);
			}

			return 0;
		}

		render( count ) {
			const safeCount = Number.isFinite( count ) ? Math.max( count, 0 ) : 0;
			const text = safeCount > 99 ? '99+' : String( safeCount );

			this.elements.forEach( ( el ) => {
				if ( ! el ) {
					return;
				}

				if ( safeCount <= 0 ) {
					el.hidden = true;
					el.textContent = '';
					el.setAttribute( 'aria-hidden', 'true' );
					return;
				}

				el.hidden = false;
				el.textContent = text;
				el.setAttribute( 'aria-hidden', 'false' );
			} );
		}
	}

	onReady( () => {
		const elements = Array.from( document.querySelectorAll( '[data-cart-count]' ) );

		if ( ! elements.length ) {
			return;
		}

		const controller = new CartBadgeController( elements, {
			initialCount: settings.initialCount,
		} );

		controller.init();
	} );
} )();

