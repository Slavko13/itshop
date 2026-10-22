/* global ITSOFT_PRODUCT_PAGE */
( () => {
	'use strict';

	const settings = window.ITSOFT_PRODUCT_PAGE || null;
	const hasStoreApi = Boolean( settings && settings.storeApi && settings.storeApi.root );

	const onReady = ( callback ) => {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', callback );
			return;
		}

		callback();
	};

	const debounce = ( fn, delay = 300 ) => {
		let timer;
		return ( ...args ) => {
			clearTimeout( timer );
			timer = setTimeout( () => fn( ...args ), delay );
		};
	};

	const dispatchCartUpdateEvent = ( cart ) => {
		if ( ! cart ) {
			return;
		}

		document.dispatchEvent(
			new CustomEvent( 'itsoft/cart-updated', {
				detail: { cart },
			} )
		);
	};

	class StoreApiClient {
		constructor( { root, nonce, wpNonce, wcAjaxUrl = '', wcAjaxNonce = '' } ) {
			this.root = ( root || '' ).replace( /\/$/, '' );
			this.nonce = nonce || '';
			this.wpNonce = wpNonce || '';
			this.wcAjaxUrl = wcAjaxUrl || '';
			this.wcAjaxNonce = wcAjaxNonce || '';
		}

		async request( endpoint, { method = 'GET', body } = {} ) {
			const urlObject = new URL(
				`${ this.root }/${ endpoint.replace( /^\//, '' ) }`
			);
			if ( method !== 'GET' && this.wpNonce ) {
				urlObject.searchParams.set( '_wpnonce', this.wpNonce );
			}

			const url = urlObject.toString();
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

			const response = await fetch( url, options );
			let data = null;

			try {
				data = await response.json();
			} catch ( error ) {
				// Response can legitimately be empty (204), so ignore JSON parse errors.
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

		async addItem( payload, context = {} ) {
			if ( ! this.wcAjaxUrl ) {
				return this.request( 'cart/add-item', { method: 'POST', body: payload } );
			}

			const body = new URLSearchParams();
			const isVariable = context.productType === 'variable';

			body.set( 'action', 'itsoft_add_to_cart' );
			body.set( 'nonce', this.wcAjaxNonce );
			body.set( 'product_id', String( payload.id ) );
			body.set( 'quantity', String( payload.quantity || 1 ) );

			if ( isVariable ) {
				body.set( 'variation_id', String( payload.id ) );

				( payload.variation || [] ).forEach( ( attribute ) => {
					if ( attribute.attribute ) {
						body.set(
							`variation[${ attribute.attribute }]`,
							attribute.value || ''
						);
					}
				} );
			}

			const response = await fetch( this.wcAjaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With': 'XMLHttpRequest',
				},
				credentials: 'same-origin',
				body: body.toString(),
			} );

			let result = null;

			try {
				result = await response.json();
			} catch ( error ) {
				result = null;
			}

			if ( ! response.ok || ! result || result.success !== true || ! result.data || ! result.data.cart ) {
				const message = result && result.data && result.data.message
					? result.data.message
					: 'Не удалось добавить товар в корзину.';
				throw new Error( message );
			}

			return result.data.cart;
		}

		updateItem( key, quantity ) {
			return this.request( 'cart/update-item', {
				method: 'POST',
				body: {
					key,
					quantity,
				},
			} );
		}

		removeItem( key ) {
			return this.request( 'cart/remove-item', {
				method: 'POST',
				body: {
					key,
				},
			} );
		}
	}

	class VariationSelector {
		constructor( root ) {
			this.root = root;
			this.options = Array.from( root.querySelectorAll( '[data-variation-id]' ) );
		this.activeOption = this.options.find( ( option ) => option.classList.contains( 'option_active' ) ) || this.options[0] || null;
			this.listeners = new Set();

			this.handleClick = this.handleClick.bind( this );
			this.handleKeyDown = this.handleKeyDown.bind( this );
		}

		init() {
			if ( ! this.root || ! this.options.length ) {
				return;
			}

			this.root.addEventListener( 'click', this.handleClick );
			this.root.addEventListener( 'keydown', this.handleKeyDown );
			this.emitChange( this.activeOption );
		}

		onChange( callback ) {
			if ( typeof callback === 'function' ) {
				this.listeners.add( callback );
			}
		}

		handleClick( event ) {
		const linkedTarget = event.target.closest( '[data-linked-url]' );

		if ( linkedTarget && this.root.contains( linkedTarget ) ) {
			return;
		}

			const target = event.target.closest( '[data-variation-id]' );

			if ( ! target || ! this.root.contains( target ) ) {
				return;
			}

			this.setActiveOption( target );
		}

		handleKeyDown( event ) {
			if ( event.key !== 'Enter' && event.key !== ' ' ) {
				return;
			}

		const linkedTarget = event.target.closest( '[data-linked-url]' );

		if ( linkedTarget && this.root.contains( linkedTarget ) ) {
			event.preventDefault();
			window.location.href = linkedTarget.getAttribute( 'data-linked-url' );
			return;
		}

			const target = event.target.closest( '[data-variation-id]' );

			if ( ! target || ! this.root.contains( target ) ) {
				return;
			}

			event.preventDefault();
			this.setActiveOption( target );
		}

		setActiveOption( optionNode ) {
			if ( ! optionNode || this.activeOption === optionNode ) {
				return;
			}

			if ( this.activeOption ) {
				this.activeOption.classList.remove( 'option_active' );
				this.activeOption.dataset.selected = 'false';
				this.activeOption.setAttribute( 'aria-pressed', 'false' );
			}

			optionNode.classList.add( 'option_active' );
			optionNode.dataset.selected = 'true';
			optionNode.setAttribute( 'aria-pressed', 'true' );
			this.activeOption = optionNode;
			this.emitChange( optionNode );
		}

		extractAttributes( optionNode ) {
			const attrs = {};
			const display = {};

			Array.from( optionNode.attributes ).forEach( ( attr ) => {
				if ( attr.name.startsWith( 'data-attribute-display-' ) ) {
					const slug = attr.name.replace( 'data-attribute-display-', '' );
					display[slug] = attr.value;
					return;
				}

				if ( attr.name.startsWith( 'data-attribute-' ) ) {
					const slug = attr.name.replace( 'data-attribute-', '' );
					attrs[slug] = attr.value;
				}
			} );

			return { attrs, display };
		}

		extractPrices( dataset ) {
			const toNumber = ( value ) => {
				const parsed = parseFloat( value );
				return Number.isNaN( parsed ) ? null : parsed;
			};

			const toBoolean = ( value ) => {
				if ( typeof value === 'undefined' ) {
					return null;
				}

				if ( typeof value === 'boolean' ) {
					return value;
				}

				return value === '1' || value === 'true';
			};

			const parsedPercent = parseInt( dataset.discountPercent || '0', 10 );
			const discountPercent = Number.isNaN( parsedPercent ) ? 0 : parsedPercent;
			const hasDiscountAttr = toBoolean( dataset.hasDiscount );
			const hasDiscount = hasDiscountAttr === null ? discountPercent > 0 : hasDiscountAttr;

			return {
				current: dataset.price || '',
				regular: dataset.regularPrice || '',
				sale: dataset.salePrice || '',
				raw: {
					current: toNumber( dataset.rawPrice ),
					regular: toNumber( dataset.rawRegularPrice ),
					sale: toNumber( dataset.rawSalePrice ),
				},
				discountPercent,
				hasDiscount,
			};
		}

		emitChange( optionNode ) {
			const extracted = optionNode ? this.extractAttributes( optionNode ) : { attrs: {}, display: {} };

			const payload = optionNode
				? {
						id: parseInt( optionNode.dataset.variationId, 10 ),
						prices: this.extractPrices( optionNode.dataset ),
						attributes: extracted.attrs,
						attributesDisplay: extracted.display,
				  }
				: null;

			this.listeners.forEach( ( callback ) => {
				callback( payload );
			} );
		}
	}

	class PriceDisplay {
		constructor( root ) {
			this.root = root;
			this.hiddenClassName = 'is-hidden';
			this.currentRegularClass = 'prices__current--regular';
			this.currentEl = root ? root.querySelector( '[data-price-field="current"]' ) : null;
			this.regularEl = root ? root.querySelector( '[data-price-field="regular"]' ) : null;
			this.discountEl = root ? root.querySelector( '[data-price-field="discount"]' ) : null;
			this.initial = {
				current: this.currentEl ? this.currentEl.textContent.trim() : '',
				regular: this.regularEl ? this.regularEl.textContent.trim() : '',
				discount: this.discountEl ? this.discountEl.textContent.trim() : '',
				regularHidden: this.isElementHidden( this.regularEl ),
				discountHidden: this.isElementHidden( this.discountEl ),
				currentRegular: this.isElementRegular( this.currentEl ),
			};
		}

		isElementHidden( element ) {
			if ( ! element ) {
				return false;
			}

			return element.classList.contains( this.hiddenClassName );
		}

		setElementHidden( element, shouldHide ) {
			if ( ! element ) {
				return;
			}

			element.classList.toggle( this.hiddenClassName, Boolean( shouldHide ) );
		}

		isElementRegular( element ) {
			if ( ! element ) {
				return false;
			}

			return element.classList.contains( this.currentRegularClass );
		}

		setCurrentRegularState( isRegularPrice ) {
			if ( ! this.currentEl ) {
				return;
			}

			this.currentEl.classList.toggle( this.currentRegularClass, Boolean( isRegularPrice ) );
		}

		update( pricing ) {
			if ( ! this.currentEl ) {
				return;
			}

			if ( ! pricing ) {
				this.reset();
				return;
			}

			this.currentEl.textContent = pricing.current || this.initial.current || '';

			const regularText = pricing.regular || '';
			const discountState = this.getDiscountState( pricing );
			const shouldShowDiscount = discountState.hasDiscount && discountState.percent > 0 && !! regularText;
			const shouldShowRegularState = ! shouldShowDiscount;

			this.toggleRegular( shouldShowDiscount ? regularText : '', ! shouldShowDiscount );
			this.toggleDiscount( discountState.percent, ! shouldShowDiscount );
			this.setCurrentRegularState( shouldShowRegularState );
		}

		reset() {
			if ( ! this.currentEl ) {
				return;
			}

			this.currentEl.textContent = this.initial.current;
			this.setCurrentRegularState( this.initial.currentRegular );
			this.toggleRegular( this.initial.regular, this.initial.regularHidden );
			this.toggleDiscountText( this.initial.discount, this.initial.discountHidden );
		}

		toggleRegular( value, forceHidden = null ) {
			if ( ! this.regularEl ) {
				return;
			}

			const shouldHide = forceHidden !== null ? forceHidden : ! value;
			this.setElementHidden( this.regularEl, shouldHide );
			this.regularEl.textContent = value || '';
		}

		toggleDiscount( percent, forceHidden = null ) {
			if ( ! this.discountEl ) {
				return;
			}

			const hasPercent = typeof percent === 'number' && percent > 0;
			const shouldHide = forceHidden !== null ? forceHidden : ! hasPercent;

			this.setElementHidden( this.discountEl, shouldHide );

			if ( shouldHide ) {
				this.discountEl.textContent = '';
				return;
			}

			this.discountEl.textContent = `-${ percent }%`;
		}

		toggleDiscountText( text, forceHidden = null ) {
			if ( ! this.discountEl ) {
				return;
			}

			if ( forceHidden !== null ) {
				this.setElementHidden( this.discountEl, forceHidden );
			} else {
				this.setElementHidden( this.discountEl, ! text );
			}

			this.discountEl.textContent = text || '';
		}

		getDiscountState( pricing ) {
			const raw = pricing.raw || {};
			const parsedPercent = parseInt( pricing.discountPercent, 10 );
			let percent = Number.isNaN( parsedPercent ) ? 0 : parsedPercent;
			let hasDiscount = false;

			if ( typeof pricing.hasDiscount !== 'undefined' ) {
				hasDiscount =
					pricing.hasDiscount === true ||
					pricing.hasDiscount === 'true' ||
					pricing.hasDiscount === '1';
			}

			if ( ! hasDiscount && typeof raw.regular === 'number' && typeof raw.current === 'number' ) {
				if ( raw.regular > 0 && raw.current > 0 && raw.current < raw.regular ) {
					hasDiscount = true;
				}
			}

			if (
				hasDiscount &&
				percent <= 0 &&
				typeof raw.regular === 'number' &&
				raw.regular > 0 &&
				typeof raw.current === 'number'
			) {
				const calculated = Math.round( ( ( raw.regular - raw.current ) / raw.regular ) * 100 );

				if ( Number.isFinite( calculated ) && calculated > 0 ) {
					percent = calculated;
				}
			}

			return {
				hasDiscount,
				percent: hasDiscount && percent > 0 ? percent : 0,
			};
		}
	}

	class QuantityControls {
		constructor( wrapper ) {
			this.wrapper = wrapper;
			this.input = wrapper ? wrapper.querySelector( '[data-quantity-input]' ) : null;
			this.btnDec = wrapper ? wrapper.querySelector( '[data-quantity-btn="decrement"]' ) : null;
			this.btnInc = wrapper ? wrapper.querySelector( '[data-quantity-btn="increment"]' ) : null;
			this.listeners = new Set();
			this.removeListeners = new Set();
			this.busyClassName = 'product__quantity--busy';

			const dataset = this.input ? this.input.dataset : {};
			const parsedMin = parseFloat( dataset.min );
			const parsedStep = parseFloat( dataset.step );
			const parsedMax = dataset.max === undefined || dataset.max === '' ? null : parseFloat( dataset.max );

			this.min = Number.isNaN( parsedMin ) ? 1 : parsedMin;
			this.step = Number.isNaN( parsedStep ) || parsedStep <= 0 ? 1 : parsedStep;
			this.max = parsedMax === null || Number.isNaN( parsedMax ) ? null : parsedMax;
			this.isSoldIndividually = wrapper ? wrapper.dataset.soldIndividually === '1' || ( this.input && this.input.dataset.soldIndividually === '1' ) : false;

			if ( this.isSoldIndividually ) {
				this.min = 1;
				this.max = 1;
				this.step = 1;
			}

			this.handleInputChange = this.handleInputChange.bind( this );
			this.handleIncrement = this.handleIncrement.bind( this );
			this.handleDecrement = this.handleDecrement.bind( this );
		}

		init() {
			if ( ! this.wrapper || ! this.input ) {
				return;
			}

			this.btnInc && this.btnInc.addEventListener( 'click', this.handleIncrement );
			this.btnDec && this.btnDec.addEventListener( 'click', this.handleDecrement );
			this.input.addEventListener( 'change', this.handleInputChange );
			this.input.addEventListener( 'blur', this.handleInputChange );

			if ( this.isSoldIndividually && this.btnInc ) {
				this.btnInc.disabled = true;
				this.btnInc.setAttribute( 'aria-disabled', 'true' );
			}
		}

		onChange( callback ) {
			if ( typeof callback === 'function' ) {
				this.listeners.add( callback );
			}
		}

		handleIncrement() {
			this.setValue( this.getValue() + this.step );
		}

		handleDecrement() {
			const currentValue = this.getValue();
			const nextValue = currentValue - this.step;

			if ( nextValue < this.min ) {
				this.emitRemoveRequest();
				return;
			}

			this.setValue( nextValue );
		}

		handleInputChange() {
			this.setValue( this.input.value );
		}

		getValue() {
			return this.normalize( this.input ? this.input.value : this.min );
		}

		getMin() {
			return this.min;
		}

		isSoldIndividuallyProduct() {
			return this.isSoldIndividually;
		}

		setValue( value, { silent = false } = {} ) {
			if ( ! this.input ) {
				return;
			}

			const normalized = this.normalize( value );
			this.input.value = normalized;

			if ( ! silent ) {
				this.emitChange( normalized );
			}
		}

		normalize( value ) {
			if ( this.isSoldIndividually ) {
				return 1;
			}

			let numeric = parseFloat( value );

			if ( Number.isNaN( numeric ) ) {
				numeric = this.min;
			}

			const step = this.step > 0 ? this.step : 1;
			const precision = Math.max( ( step.toString().split( '.' )[1] || '' ).length, 0 );
			numeric = Math.round( numeric / step ) * step;
			numeric = Number( numeric.toFixed( precision ) );

			if ( numeric < this.min ) {
				numeric = this.min;
			}

			if ( this.max !== null && numeric > this.max ) {
				numeric = this.max;
			}

			return numeric;
		}

		emitChange( value ) {
			this.listeners.forEach( ( callback ) => callback( value ) );
		}

		onRemoveRequest( callback ) {
			if ( typeof callback === 'function' ) {
				this.removeListeners.add( callback );
			}
		}

		emitRemoveRequest() {
			this.removeListeners.forEach( ( callback ) => callback() );
		}

		show() {
			if ( ! this.wrapper ) {
				return;
			}

			this.wrapper.dataset.quantityVisible = 'true';
			this.wrapper.classList.add( 'active' );
		}

		hide() {
			if ( ! this.wrapper ) {
				return;
			}

			this.wrapper.dataset.quantityVisible = 'false';
			this.wrapper.classList.remove( 'active' );
		}

		setBusyState( isBusy ) {
			if ( ! this.wrapper ) {
				return;
			}

			const busy = Boolean( isBusy );

			this.wrapper.classList.toggle( this.busyClassName, busy );

			if ( busy ) {
				this.wrapper.setAttribute( 'aria-busy', 'true' );
			} else {
				this.wrapper.removeAttribute( 'aria-busy' );
			}

			this.toggleButtonBusyState( this.btnInc, busy );
			this.toggleButtonBusyState( this.btnDec, busy );
			this.toggleInputBusyState( this.input, busy );
		}

		toggleButtonBusyState( button, busy ) {
			if ( ! button ) {
				return;
			}

			if ( busy ) {
				button.dataset.quantityBusyPrevDisabled = button.disabled ? 'true' : 'false';
				button.disabled = true;
				button.setAttribute( 'aria-disabled', 'true' );
				return;
			}

			if ( Object.prototype.hasOwnProperty.call( button.dataset, 'quantityBusyPrevDisabled' ) ) {
				const wasDisabled = button.dataset.quantityBusyPrevDisabled === 'true';
				button.disabled = wasDisabled;

				if ( wasDisabled ) {
					button.setAttribute( 'aria-disabled', 'true' );
				} else {
					button.removeAttribute( 'aria-disabled' );
				}

				delete button.dataset.quantityBusyPrevDisabled;
			}
		}

		toggleInputBusyState( input, busy ) {
			if ( ! input ) {
				return;
			}

			if ( busy ) {
				input.dataset.quantityBusyPrevDisabled = input.disabled ? 'true' : 'false';
				input.disabled = true;
				input.setAttribute( 'aria-disabled', 'true' );
				input.blur();
				return;
			}

			if ( Object.prototype.hasOwnProperty.call( input.dataset, 'quantityBusyPrevDisabled' ) ) {
				const wasDisabled = input.dataset.quantityBusyPrevDisabled === 'true';
				input.disabled = wasDisabled;

				if ( wasDisabled ) {
					input.setAttribute( 'aria-disabled', 'true' );
				} else {
					input.removeAttribute( 'aria-disabled' );
				}

				delete input.dataset.quantityBusyPrevDisabled;
			}
		}

	}

	class AddToCartFormSync {
		constructor( form ) {
			this.form = form;
			this.variationIdInput = form ? form.querySelector( '[data-form-field="variation-id"]' ) : null;
			this.attributeInputs = new Map();
			this.attributeDefaults = new Map();
			this.variationDefault = this.variationIdInput ? this.variationIdInput.dataset.formDefaultValue || '' : '';

			if ( form ) {
				const inputs = form.querySelectorAll( '[data-form-attribute]' );
				inputs.forEach( ( input ) => {
					const slug = input.dataset.formAttribute;

					if ( ! slug ) {
						return;
					}

					this.attributeInputs.set( slug, input );
					this.attributeDefaults.set( slug, input.dataset.formDefaultValue || '' );
				} );
			}
		}

		updateVariation( variation ) {
			if ( this.variationIdInput ) {
				const value = variation && variation.id ? variation.id : this.variationDefault;
				this.variationIdInput.value = value || '';
			}

			this.attributeInputs.forEach( ( input, slug ) => {
				let value = '';

				if ( variation && variation.attributes && variation.attributes[ slug ] ) {
					value = variation.attributes[ slug ];
				} else if ( this.attributeDefaults.has( slug ) ) {
					value = this.attributeDefaults.get( slug );
				}

				input.value = value;
			} );
		}
	}

	class CartManager {
		constructor( { root, button, form, quantityControls, api, productId, productType, cartUrl = '', messages = {} } ) {
			this.root = root;
			this.button = button;
			this.form = form;
			this.quantityControls = quantityControls;
			this.api = api;
			this.productId = productId;
			this.productType = productType || 'simple';
			this.cartUrl = cartUrl;
			this.messages = messages;
			this.noticeEl = this.findNoticeElement( root, productId );
			this.noticeTimer = null;
			this.noticeHideTimer = null;

			this.buttonDefaultView = button ? button.querySelector( '[data-cart-button-default-view]' ) : null;
			this.buttonInCartView = button ? button.querySelector( '[data-cart-button-in-cart-view]' ) : null;
			this.buttonInCartTopEl = button ? button.querySelector( '.cart__btn-top' ) : null;
			this.buttonInCartBottomEl = button ? button.querySelector( '.cart__btn-bottom' ) : null;
			this.buttonLabelEl = button ? button.querySelector( '.btn__text' ) : null;
			this.defaultButtonLabel = ( button && button.dataset.cartButtonDefault ) || ( this.buttonLabelEl ? this.buttonLabelEl.textContent.trim() : '' );
			this.inCartButtonTopLabel = ( button && button.dataset.cartButtonInCartTop ) || '';
			this.inCartButtonBottomLabel = ( button && button.dataset.cartButtonInCartBottom ) || '';

			this.cartItems = new Map();
			this.currentVariation = null;
			this.activeCartKey = null;
			this.isProcessing = false;
			this.serverDisabled = this.button ? this.button.hasAttribute( 'disabled' ) : false;

			this.handleButtonClick = this.handleButtonClick.bind( this );
			this.handleQuantityChange = this.handleQuantityChange.bind( this );
			this.scheduleQuantityUpdate = debounce( ( value ) => this.updateQuantity( value ), 400 );
			this.handleFormSubmit = this.handleFormSubmit.bind( this );
			this.handleQuantityRemoveRequest = this.handleQuantityRemoveRequest.bind( this );
		}

		init() {
			if ( this.button ) {
				this.button.addEventListener( 'click', this.handleButtonClick );
			}

			if ( this.form ) {
				this.form.addEventListener( 'submit', this.handleFormSubmit );
			}

			if ( this.quantityControls ) {
				this.quantityControls.onChange( this.handleQuantityChange );
				this.quantityControls.onRemoveRequest( this.handleQuantityRemoveRequest );
			}
		}

		setVariation( variation ) {
			this.currentVariation = variation;
			this.refreshActiveItem();
		}

		syncCart( cart, { broadcast = false } = {} ) {
			if ( ! cart || ! Array.isArray( cart.items ) ) {
				return;
			}

			this.cartItems.clear();
			cart.items.forEach( ( item ) => {
				if ( typeof item.id === 'undefined' ) {
					return;
				}

				const mapKey = String( item.id );
				this.cartItems.set( mapKey, {
					key: item.key,
					quantity: item.quantity,
				} );
			} );

			this.refreshActiveItem();

			if ( broadcast ) {
				dispatchCartUpdateEvent( cart );
			}
		}

		refreshActiveItem() {
			const targetId = this.getCurrentProductId();

			if ( ! targetId ) {
				this.activeCartKey = null;
				this.quantityControls && this.quantityControls.hide();
				this.setButtonState( 'idle' );
				return;
			}

			const existing = this.cartItems.get( String( targetId ) );
			this.activeCartKey = existing ? existing.key : null;

			if ( existing && this.quantityControls ) {
				this.quantityControls.setValue( existing.quantity, { silent: true } );
				this.quantityControls.show();
				this.setButtonState( 'in-cart' );
			} else if ( this.quantityControls ) {
				this.quantityControls.setValue( this.quantityControls.getMin(), { silent: true } );
				this.quantityControls.hide();
				this.setButtonState( 'idle' );
			}
		}

		getCurrentProductId() {
			if ( this.productType === 'variable' ) {
				return this.currentVariation && this.currentVariation.id ? this.currentVariation.id : null;
			}

			return this.productId;
		}

		handleButtonClick( event ) {
			event.preventDefault();

			if ( this.isProcessing || this.serverDisabled ) {
				return;
			}

			if ( this.productType === 'variable' && ( ! this.currentVariation || ! this.currentVariation.id ) ) {
				this.pushNotice( this.messages.selectVariation || '' );
				this.highlightButtonError();
				return;
			}

			if ( this.activeCartKey ) {
				if ( this.navigateToCart() ) {
					return;
				}

				this.updateQuantity( this.quantityControls ? this.quantityControls.getValue() : 1 );
				return;
			}

			this.addToCart();
		}

		handleFormSubmit( event ) {
			event.preventDefault();
			this.handleButtonClick( event );
		}

		handleQuantityChange( value ) {
			if ( ! this.activeCartKey || this.isProcessing ) {
				return;
			}

			this.scheduleQuantityUpdate( value );
		}

		handleQuantityRemoveRequest() {
			if ( ! this.activeCartKey || this.isProcessing ) {
				return;
			}

			this.updateQuantity( 0 );
		}

		async addToCart() {
			if ( this.isProcessing || this.serverDisabled ) {
				return;
			}

			const payload = this.buildPayload();

			if ( ! payload ) {
				return;
			}

			this.setProcessingState( true );
			this.setButtonState( 'loading' );

			try {
				const cart = await this.api.addItem( payload, {
					productType: this.productType,
				} );
				this.syncCart( cart, { broadcast: true } );
				this.quantityControls && this.quantityControls.show();
				this.setButtonState( 'in-cart' );
				this.pushNotice( this.messages.added || '' );
			} catch ( error ) {
				console.error( '[ProductPage] add to cart failed', error );
				this.setButtonState( 'error' );
				this.pushNotice( error.message || this.messages.updateError || '' );
			} finally {
				this.setProcessingState( false );
			}
		}

		async updateQuantity( quantity ) {
			if ( ! this.activeCartKey || this.isProcessing ) {
				return;
			}

			this.setProcessingState( true );
			this.setButtonState( 'loading' );

			try {
				const cart =
					quantity <= 0
						? await this.api.removeItem( this.activeCartKey )
						: await this.api.updateItem( this.activeCartKey, quantity );

				this.syncCart( cart, { broadcast: true } );
				if ( quantity <= 0 ) {
					this.setButtonState( 'idle' );
					this.quantityControls && this.quantityControls.hide();
					this.pushNotice( this.messages.removed || '' );
				} else {
					this.setButtonState( 'in-cart' );
				}
			} catch ( error ) {
				console.error( '[ProductPage] cart update failed', error );
				this.setButtonState( 'error' );
				this.pushNotice( error.message || this.messages.updateError || '' );
			} finally {
				this.setProcessingState( false );
			}
		}

		buildPayload() {
			const targetId = this.getCurrentProductId();

			if ( ! targetId ) {
				return null;
			}

			const quantity = this.quantityControls ? this.quantityControls.getValue() : 1;
			const payload = {
				id: targetId,
				quantity,
			};

			if ( this.productType === 'variable' && this.currentVariation ) {
				const variationAttributes = this.buildVariationAttributes( this.currentVariation.attributes || {} );

				if ( variationAttributes.length ) {
					payload.variation = variationAttributes;
				}
			}

			return payload;
		}

		buildVariationAttributes( attributes ) {
			return Object.entries( attributes ).map( ( [ slug, value ] ) => ( {
				attribute: `attribute_${ slug }`,
				value,
			} ) );
		}

		setButtonState( state ) {
			if ( ! this.button ) {
				return;
			}

			const isLoading = state === 'loading';
			this.button.dataset.cartState = state;

			const shouldDisable = isLoading || this.serverDisabled;
			this.button.disabled = shouldDisable;

			if ( this.serverDisabled ) {
				this.button.setAttribute( 'aria-disabled', 'true' );
			} else {
				this.button.removeAttribute( 'aria-disabled' );
			}

			if ( isLoading ) {
				this.button.setAttribute( 'aria-busy', 'true' );
				this.button.classList.add( 'is-loading' );
			} else {
				this.button.removeAttribute( 'aria-busy' );
				this.button.classList.remove( 'is-loading' );
			}

			if ( isLoading ) {
				return;
			}

			this.toggleButtonView( state === 'in-cart' );
		}

		setProcessingState( isProcessing ) {
			this.isProcessing = isProcessing;
			if ( this.quantityControls ) {
				this.quantityControls.setBusyState( isProcessing );
			}
		}

		updateButtonLabel( text ) {
			if ( this.buttonLabelEl ) {
				this.buttonLabelEl.textContent = text;
			}
		}

		updateInCartLabels() {
			if ( this.buttonInCartTopEl && this.inCartButtonTopLabel ) {
				this.buttonInCartTopEl.textContent = this.inCartButtonTopLabel;
			}

			if ( this.buttonInCartBottomEl && this.inCartButtonBottomLabel ) {
				this.buttonInCartBottomEl.textContent = this.inCartButtonBottomLabel;
			}
		}

		toggleButtonView( isInCart ) {
			if ( ! this.button ) {
				return;
			}

			if ( isInCart ) {
				this.updateInCartLabels();
				this.button.classList.add( 'cart__btn_in-cart' );
			} else {
				this.button.classList.remove( 'cart__btn_in-cart' );
				this.updateButtonLabel( this.defaultButtonLabel );
			}
		}

		navigateToCart() {
			if ( ! this.cartUrl ) {
				return false;
			}

			window.location.href = this.cartUrl;
			return true;
		}

		pushNotice( message ) {
			if ( ! this.noticeEl || ! message ) {
				return;
			}

			this.noticeEl.textContent = message;
			this.noticeEl.dataset.state = 'visible';

			clearTimeout( this.noticeTimer );
			clearTimeout( this.noticeHideTimer );

			this.noticeTimer = setTimeout( () => {
				this.hideNotice();
			}, 4000 );
		}

		highlightButtonError() {
			if ( ! this.button ) {
				return;
			}

			this.button.classList.add( 'is-shaking' );
			setTimeout( () => this.button.classList.remove( 'is-shaking' ), 600 );
		}

		hideNotice( immediate = false ) {
			if ( ! this.noticeEl ) {
				return;
			}

			clearTimeout( this.noticeHideTimer );

			if ( immediate ) {
				this.noticeEl.textContent = '';
				this.noticeEl.dataset.state = 'hidden';
				return;
			}

			this.noticeEl.dataset.state = 'hiding';
			this.noticeHideTimer = setTimeout( () => {
				if ( this.noticeEl ) {
					this.noticeEl.textContent = '';
					this.noticeEl.dataset.state = 'hidden';
				}
			}, 350 );
		}

		findNoticeElement( root, productId ) {
			const baseSelector = '[data-cart-notice]';

			if ( productId ) {
				const scopedSelector = `${ baseSelector }[data-cart-notice-for="${ productId }"]`;
				const scoped = document.querySelector( scopedSelector );
				if ( scoped ) {
					return scoped;
				}
			}

			if ( root ) {
				const nested = root.querySelector( baseSelector );
				if ( nested ) {
					return nested;
				}

				const sibling = root.nextElementSibling;
				if ( sibling && sibling.matches( baseSelector ) ) {
					return sibling;
				}
			}

			return null;
		}
	}

	class ProductAttributes {
		constructor( root ) {
			this.root = root;
			this.elements = new Map();

			if ( root ) {
				const attrs = root.querySelectorAll( '[data-attribute-slug]' );
				attrs.forEach( ( el ) => {
					const slug = el.getAttribute( 'data-attribute-slug' );
					const valueEl = el.querySelector( '.product-extra__attr-value' );
					if ( slug && valueEl ) {
						this.elements.set( slug, valueEl );
					}
				} );
			}
		}

		update( attributesDisplay ) {
			if ( ! attributesDisplay ) {
				return;
			}

			this.elements.forEach( ( el, slug ) => {
				if ( Object.prototype.hasOwnProperty.call( attributesDisplay, slug ) ) {
					el.textContent = attributesDisplay[ slug ];
				}
			} );
		}
	}

	class ProductPageController {
		constructor( root, config ) {
			this.root = root;
			this.config = config;
			this.api = new StoreApiClient( {
				...config.storeApi,
				wcAjaxUrl: config.wcAjaxUrl || '',
				wcAjaxNonce: config.wcAjaxNonce || '',
			} );
			this.cartPollTimer = null;
			this.cartVersion = null;

			const variationsNode = root.querySelector( '[data-product-variations]' );
			const priceNode = root.querySelector( '[data-product-prices]' );
			const cartNode = root.querySelector( '[data-product-cart]' );
			const extraNode = root.querySelector( '.product-extra__info' );
			const quantityNode = cartNode ? cartNode.querySelector( '[data-quantity-controls]' ) : null;
			const buttonNode = cartNode ? cartNode.querySelector( '[data-cart-action="add"]' ) : null;
			const formNode = cartNode ? cartNode.querySelector( '[data-cart-form]' ) : null;

			this.variationSelector = variationsNode ? new VariationSelector( variationsNode ) : null;
			this.priceDisplay = priceNode ? new PriceDisplay( priceNode ) : null;
			this.productAttributes = extraNode ? new ProductAttributes( extraNode ) : null;
			this.quantityControls = quantityNode ? new QuantityControls( quantityNode ) : null;
			this.formSync = formNode ? new AddToCartFormSync( formNode ) : null;
			this.cartManager = cartNode
				? new CartManager( {
						root: cartNode,
						button: buttonNode,
						form: formNode,
						quantityControls: this.quantityControls,
						api: this.api,
						productId: config.productId,
						productType: cartNode.dataset.productType || 'simple',
						cartUrl: cartNode.dataset.cartUrl || '',
						messages: config.i18n || {},
				  } )
				: null;
		}

		init() {
			if ( this.quantityControls ) {
				this.quantityControls.init();
			}

			if ( this.cartManager ) {
				this.cartManager.init();
			}

			if ( this.variationSelector ) {
				this.variationSelector.onChange( ( variation ) => {
					this.priceDisplay && this.priceDisplay.update( variation ? variation.prices : null );
					this.cartManager && this.cartManager.setVariation( variation );
					this.formSync && this.formSync.updateVariation( variation );
					this.productAttributes && this.productAttributes.update( variation ? variation.attributesDisplay : null );
				} );

				this.variationSelector.init();
			} else if ( this.cartManager ) {
				this.cartManager.setVariation( null );
				this.formSync && this.formSync.updateVariation( null );
			}

			this.bootstrapCart();
			this.observeCartUpdates();
		}

		async bootstrapCart() {
			if ( ! this.cartManager ) {
				return;
			}

			try {
				const cart = await this.api.getCart();
				this.cartManager.syncCart( cart );
				this.cartVersion = cart && cart.totals ? cart.totals.hash : null;
			} catch ( error ) {
				console.error( '[ProductPage] unable to bootstrap cart state', error );
			}
		}

		observeCartUpdates() {
			if ( ! this.cartManager ) {
				return;
			}

			const handleStoreEvent = ( event ) => {
				if ( ! event || ! event.detail || ! event.detail.cart ) {
					return;
				}

				this.cartManager.syncCart( event.detail.cart );
				this.cartVersion = event.detail.cart && event.detail.cart.totals ? event.detail.cart.totals.hash : null;
			};

			document.addEventListener( 'wc-store-cart-updated', handleStoreEvent );
			document.addEventListener( 'wc-blocks/cart-update', handleStoreEvent );

			const poll = async () => {
				try {
					const cart = await this.api.getCart();
					const nextVersion = cart && cart.totals ? cart.totals.hash : null;

					if ( nextVersion && nextVersion === this.cartVersion ) {
						return;
					}

					this.cartManager.syncCart( cart );
					this.cartVersion = nextVersion;
				} catch ( error ) {
					console.warn( '[ProductPage] cart poll failed', error );
				}
			};

			this.cartPollTimer = setInterval( poll, 15000 );
		}
	}

	class VariationsDiffPopup {
		constructor() {
			this.activeClass = 'active';
			this.handleClick = this.handleClick.bind( this );
		}

		init() {
			document.addEventListener( 'click', this.handleClick );
		}

		handleClick( event ) {
			const tab = event.target.closest( '.variations-diff-popup__tab' );
			if ( ! tab ) {
				return;
			}

			const wrapper = tab.closest( '.variations-diff-popup' );
			if ( ! wrapper ) {
				return;
			}

			const targetId = tab.dataset.tabTarget;
			if ( ! targetId ) {
				return;
			}

			// Deactivate all tabs
			wrapper.querySelectorAll( '.variations-diff-popup__tab' ).forEach( ( t ) => {
				t.classList.remove( this.activeClass );
			} );

			// Deactivate all panes
			wrapper.querySelectorAll( '.variations-diff-popup__pane' ).forEach( ( pane ) => {
				pane.classList.remove( this.activeClass );
			} );

			// Activate clicked tab
			tab.classList.add( this.activeClass );

			// Activate target pane
			const targetPane = wrapper.querySelector( `[data-tab-content="${ targetId }"]` );
			if ( targetPane ) {
				targetPane.classList.add( this.activeClass );
			}
		}
	}

	onReady( () => {
		const root = document.querySelector( '.product-page' );

		if ( root && hasStoreApi ) {
			const controller = new ProductPageController( root, settings );
			controller.init();
		}

		new VariationsDiffPopup().init();

		if ( typeof Fancybox !== 'undefined' ) {
			Fancybox.bind( '[data-fancybox]' );
		}
	} );
} )();

