(() => {
	'use strict';

	const config = window.ITSOFT_METRIKA;
	if (!config || !Number(config.counterId)) return;

	const counterId = Number(config.counterId);
	const dataLayer = window.dataLayer = window.dataLayer || [];
	let initialized = false;
	let lastRemovedProduct = null;

	const log = (...args) => {
		if (config.debug && window.console) console.info('[ITSoft Metrika]', ...args);
	};

	const cookieValue = (name) => document.cookie
		.split(';')
		.map((part) => part.trim())
		.find((part) => part.startsWith(`${name}=`))
		?.slice(name.length + 1) || '';

	const hasConsent = () => {
		if (!config.requireConsent) return true;
		const accepted = decodeURIComponent(cookieValue('cookie_notice_accepted')).toLowerCase();
		return ['true', '1', 'yes', 'accepted'].includes(accepted);
	};

	const clearCookie = (name) => {
		if (!name) return;
		document.cookie = `${name}=; Max-Age=0; path=/; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
	};

	const cleanProduct = (product, quantity) => {
		if (!product || (!product.id && !product.name)) return null;
		const result = { ...product };
		if (quantity) result.quantity = Number(quantity) || 1;
		['price', 'quantity', 'position'].forEach((key) => {
			if (result[key] !== undefined) result[key] = Number(result[key]);
		});
		return result;
	};

	const productFromElement = (element) => {
		if (!element) return null;
		const owner = element.closest('[data-metrika-product]');
		if (owner?.dataset.metrikaProduct) {
			try { return cleanProduct(JSON.parse(owner.dataset.metrikaProduct)); } catch (error) { log('Invalid product data', error); }
		}
		const id = element.dataset.product_id || element.value || element.getAttribute('data-product-id');
		return id ? { id: String(id), quantity: Number(element.dataset.quantity || 1) } : null;
	};

	const ecommerce = (type, products, actionField = null) => {
		if (!initialized) return;
		const normalized = (Array.isArray(products) ? products : [products]).map((item) => cleanProduct(item)).filter(Boolean);
		if (!normalized.length) return;
		const action = { products: normalized };
		if (actionField) action.actionField = actionField;
		dataLayer.push({ ecommerce: { currencyCode: config.currency || 'RUB', [type]: action } });
		log('ecommerce', type, action);
	};

	const goal = (name, params = {}) => {
		if (!initialized || typeof window.ym !== 'function') return;
		window.ym(counterId, 'reachGoal', name, params);
		log('goal', name, params);
	};

	const goalBeforeNavigation = (event, link, name, params = {}) => {
		const shouldWait = event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey && (!link.target || link.target === '_self');
		if (!shouldWait || !initialized || typeof window.ym !== 'function') {
			goal(name, params);
			return;
		}
		event.preventDefault();
		let navigated = false;
		const proceed = () => {
			if (navigated) return;
			navigated = true;
			window.location.assign(link.href);
		};
		window.ym(counterId, 'reachGoal', name, params, proceed);
		log('goal', name, params);
		window.setTimeout(proceed, 450);
	};

	const trackPayment = (input) => {
		if (!input) return;
		const value = String(input.value || 'selected').slice(0, 80);
		const key = `itsoft_ym_payment_${counterId}_${value}`;
		try {
			if (sessionStorage.getItem(key)) return;
			sessionStorage.setItem(key, '1');
		} catch (error) { log('sessionStorage unavailable', error); }
		goal('ym-add-payment-info', { payment_type: value });
	};

	const purchaseOnce = () => {
		const purchase = config.purchase;
		if (!purchase?.orderId) return;
		const storageKey = `itsoft_ym_purchase_${counterId}_${purchase.orderId}`;
		try {
			if (localStorage.getItem(storageKey)) return;
		} catch (error) { log('localStorage unavailable', error); }

		ecommerce('purchase', purchase.products || [], {
			id: String(purchase.orderId),
			revenue: Number(purchase.revenue || 0),
			coupon: purchase.coupon || '',
		});
		goal('ym-purchase', {
			order_price: Number(purchase.revenue || 0),
			currency: purchase.currency || config.currency || 'RUB',
		});
		try { localStorage.setItem(storageKey, String(Date.now())); } catch (error) { log('Could not persist purchase marker', error); }
	};

	const initialEvents = () => {
		(config.initialEvents || []).forEach((event) => ecommerce(event.type, event.products || []));
		if (config.page?.isProduct) goal('ym-product-view');

		if (config.pendingAdd) {
			ecommerce('add', config.pendingAdd);
			goal('ym-add-to-cart');
			clearCookie(config.cookieNames?.add);
		}
		if (config.pendingRemove) {
			ecommerce('remove', config.pendingRemove);
			clearCookie(config.cookieNames?.remove);
		}
		(config.pendingGoals || []).forEach((name) => goal(name));
		if ((config.pendingGoals || []).length) clearCookie(config.cookieNames?.goals);

		if (config.search) {
			goal('ym-site-search', {
				query_length: Number(config.search.queryLength || 0),
				result_count: Number(config.search.resultCount || 0),
			});
		}

		if (config.page?.isCheckout) {
			const key = `itsoft_ym_checkout_${counterId}`;
			let alreadySent = false;
			try { alreadySent = sessionStorage.getItem(key) === '1'; } catch (error) { log('sessionStorage unavailable', error); }
			if (!alreadySent) {
				goal('ym-begin-checkout');
				try { sessionStorage.setItem(key, '1'); } catch (error) { log('Could not persist checkout marker', error); }
			}
		}

		purchaseOnce();
	};

	const load = () => {
		if (initialized || !hasConsent()) return;
		initialized = true;
		window.ym = window.ym || function () { (window.ym.a = window.ym.a || []).push(arguments); };
		window.ym.l = Date.now();

		const script = document.createElement('script');
		script.async = true;
		script.src = 'https://mc.yandex.ru/metrika/tag.js';
		document.head.appendChild(script);

		window.ym(counterId, 'init', {
			clickmap: true,
			trackLinks: true,
			accurateTrackBounce: true,
			webvisor: true,
			ecommerce: 'dataLayer',
		});
		window.ym(counterId, 'params', { itsoft_user_type: config.visitorType || 'guest' });
		log('initialized', counterId, config.environment);
		initialEvents();
	};

	const onConsent = () => window.setTimeout(load, 50);
	document.addEventListener('setCookieNotice', onConsent);
	document.addEventListener('click', (event) => {
		if (event.target.closest('#cn-accept-cookie, .cn-set-cookie[data-cookie-set="accept"]')) onConsent();
	});

	document.addEventListener('click', (event) => {
		const productLink = event.target.closest('.catalog-card__image-link, .catalog-card__link, .search-suggestions__product');
		if (productLink) {
			const product = productFromElement(productLink);
			if (product) ecommerce('click', product, { list: product.list || 'Каталог' });
		}

		const download = event.target.closest('.distribution-file__download');
		if (download) goalBeforeNavigation(event, download, 'itsoft-distribution-download', { distribution_id: Number(config.page?.distributionId || 0) });

		const placeOrder = event.target.closest('#place_order, .wc-block-components-checkout-place-order-button');
		if (placeOrder) {
			trackPayment(document.querySelector('input[name="payment_method"]:checked, input[name^="radio-control-wc-payment-method-options"]:checked'));
		}

		const remove = event.target.closest('.remove, .remove_from_cart_button, [data-product_id][data-cart_item_key]');
		if (remove) lastRemovedProduct = productFromElement(remove);
	});

	document.addEventListener('itsoft:license-copied', () => goal('itsoft-license-copy'));
	document.addEventListener('itsoft:search-suggestions-shown', (event) => {
		ecommerce('impressions', event.detail?.products || []);
	});

	document.addEventListener('change', (event) => {
		const input = event.target.closest('input[name="payment_method"], input[name^="radio-control-wc-payment-method-options"]');
		if (!input || !input.checked) return;
		trackPayment(input);
	});

	const attachJqueryEvents = () => {
		if (!window.jQuery) return;
		const $body = window.jQuery(document.body);
		$body.on('added_to_cart', (event, fragments, cartHash, $button) => {
			const element = $button?.get ? $button.get(0) : null;
			const product = productFromElement(element);
			if (product) ecommerce('add', product);
			goal('ym-add-to-cart');
			clearCookie(config.cookieNames?.add);
		});
		$body.on('removed_from_cart', () => {
			if (lastRemovedProduct) ecommerce('remove', lastRemovedProduct);
			lastRemovedProduct = null;
			clearCookie(config.cookieNames?.remove);
		});
		$body.on('checkout_error', () => goal('itsoft-checkout-error'));
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => { load(); attachJqueryEvents(); });
	} else {
		load();
		attachJqueryEvents();
	}
})();
