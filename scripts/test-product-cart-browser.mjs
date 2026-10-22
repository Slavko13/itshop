import { spawn } from 'node:child_process';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const port = 9333;
const productUrl = process.argv[ 2 ] || 'http://localhost:8080/product/windows-11-iot-enterprise-2024-ltsc-korporativnaya/';
const expectedProductText = process.argv[ 3 ] || 'Windows 11 IoT Enterprise';
const profileDir = await mkdtemp( join( tmpdir(), 'itsoft-cart-browser-' ) );
const chrome = spawn(
	chromePath,
	[
		'--headless=new',
		'--disable-gpu',
		'--no-first-run',
		'--no-default-browser-check',
		`--remote-debugging-port=${ port }`,
		`--user-data-dir=${ profileDir }`,
		productUrl,
	],
	{ stdio: 'ignore' }
);

const sleep = ( milliseconds ) => new Promise( ( resolve ) => setTimeout( resolve, milliseconds ) );

async function getPageTarget() {
	for ( let attempt = 0; attempt < 50; attempt += 1 ) {
		try {
			const response = await fetch( `http://127.0.0.1:${ port }/json/list` );
			const targets = await response.json();
			const page = targets.find( ( target ) => target.type === 'page' );

			if ( page ) {
				return page;
			}
		} catch ( error ) {
			// Chrome can need a moment before the debugging endpoint is ready.
		}

		await sleep( 200 );
	}

	throw new Error( 'Chrome DevTools endpoint did not become ready.' );
}

const target = await getPageTarget();
const socket = new WebSocket( target.webSocketDebuggerUrl );
const pending = new Map();
let nextId = 0;

await new Promise( ( resolve, reject ) => {
	socket.addEventListener( 'open', resolve, { once: true } );
	socket.addEventListener( 'error', reject, { once: true } );
} );

socket.addEventListener( 'message', ( event ) => {
	const message = JSON.parse( event.data );

	if ( ! message.id || ! pending.has( message.id ) ) {
		return;
	}

	const { resolve, reject } = pending.get( message.id );
	pending.delete( message.id );

	if ( message.error ) {
		reject( new Error( message.error.message ) );
		return;
	}

	resolve( message.result );
} );

function command( method, params = {} ) {
	const id = ++nextId;

	return new Promise( ( resolve, reject ) => {
		pending.set( id, { resolve, reject } );
		socket.send( JSON.stringify( { id, method, params } ) );
	} );
}

async function evaluate( expression ) {
	const result = await command( 'Runtime.evaluate', {
		expression,
		awaitPromise: true,
		returnByValue: true,
	} );

	if ( result.exceptionDetails ) {
		throw new Error( result.exceptionDetails.text || 'Browser evaluation failed.' );
	}

	return result.result.value;
}

async function waitFor( expression, message, attempts = 50 ) {
	for ( let attempt = 0; attempt < attempts; attempt += 1 ) {
		if ( await evaluate( expression ) ) {
			return;
		}

		await sleep( 200 );
	}

	throw new Error( message );
}

try {
	await command( 'Network.enable' );
	await command( 'Network.setCacheDisabled', { cacheDisabled: true } );
	await waitFor(
		'document.readyState === "complete" && Boolean(document.querySelector("[data-cart-action=add]"))',
		'Product add-to-cart button was not rendered.'
	);
	await sleep( 500 );

	const addStartedAt = Date.now();
	await evaluate( 'document.querySelector("[data-cart-action=add]").click(); true' );
	await waitFor(
		'Number(document.querySelector("[data-cart-count]")?.textContent || 0) > 0',
		'Cart badge did not update after adding the product.'
	);
	const addDurationMs = Date.now() - addStartedAt;
	const preNavigationCart = await evaluate( `(async () => {
		const response = await fetch('/wp-json/wc/store/v1/cart', { credentials: 'same-origin' });
		const cart = await response.json();
		const cartPageResponse = await fetch('/cart/', { credentials: 'same-origin' });
		const cartPageHtml = await cartPageResponse.text();
		return {
			status: response.status,
			items: (cart.items || []).map((item) => ({ id: item.id, name: item.name, quantity: item.quantity })),
			itemsCount: cart.items_count,
			cartPageHasProduct: cartPageHtml.includes(${ JSON.stringify( expectedProductText ) }),
			cartPageHasForm: cartPageHtml.includes('woocommerce-cart-form'),
		};
	})()` );

	await command( 'Page.navigate', { url: 'http://localhost:8080/cart/' } );
	try {
		await waitFor(
			`document.readyState === "complete" && document.body.innerText.includes(${ JSON.stringify( expectedProductText ) })`,
			'Classic WooCommerce cart did not contain the added product.'
		);
	} catch ( error ) {
		const diagnostic = await evaluate( `({
			url: location.href,
			count: document.querySelector('[data-cart-count]')?.textContent || '',
			bodyText: document.body.innerText.slice(0, 1400),
		})` );
		diagnostic.preNavigationCart = preNavigationCart;
		throw new Error( `${ error.message } Diagnostic: ${ JSON.stringify( diagnostic ) }` );
	}
	await waitFor(
		'Number(document.querySelector("[data-cart-count]")?.textContent || 0) > 0',
		'Cart badge did not retain the item count on the cart page.'
	);

	const result = await evaluate( `({
		url: location.href,
		count: document.querySelector('[data-cart-count]')?.textContent || '',
		empty: document.body.innerText.includes('Ваша корзина пока пуста'),
		productVisible: document.body.innerText.includes(${ JSON.stringify( expectedProductText ) })
	})` );
	result.addDurationMs = addDurationMs;
	result.preNavigationCart = preNavigationCart;

	const removeControl = await evaluate( `(() => {
		const control = document.querySelector('.product-remove .remove');
		return { exists: Boolean(control), href: control?.href || '', className: control?.className || '' };
	})()` );

	if ( ! removeControl.exists ) {
		throw new Error( `Cart remove control was not found: ${ JSON.stringify( removeControl ) }` );
	}

	await evaluate( 'document.querySelector(".product-remove .remove").click(); true' );

	try {
		await waitFor(
			'Boolean(document.querySelector(".cart-empty-state")) && document.querySelector(".cart-page")?.classList.contains("cart-page--empty")',
			'Branded empty-cart state did not render after removing the final product.'
		);
	} catch ( error ) {
		const diagnostic = await evaluate( `({
			url: location.href,
			readyState: document.readyState,
			wooRoot: Boolean(document.querySelector('.cart-page__woocommerce > .woocommerce')),
			cartForm: Boolean(document.querySelector('.woocommerce-cart-form')),
			cartEmpty: Boolean(document.querySelector('.cart-empty')),
			brandedState: Boolean(document.querySelector('.cart-empty-state')),
			pageEmptyClass: document.querySelector('.cart-page')?.classList.contains('cart-page--empty') || false,
			bodyText: document.body.innerText.slice(0, 800),
		})` );
		throw new Error( `${ error.message } Diagnostic: ${ JSON.stringify( diagnostic ) }` );
	}
	await sleep( 300 );

	result.afterFinalRemoval = await evaluate( `(() => {
		const steps = document.querySelector('.cart-steps');
		const benefits = document.querySelector('.cart-benefits');
		return {
			brandedState: Boolean(document.querySelector('.cart-empty-state')),
			emptyClass: document.querySelector('.cart-page')?.classList.contains('cart-page--empty') || false,
			stepsHidden: !steps || getComputedStyle(steps).display === 'none',
			benefitsHidden: !benefits || getComputedStyle(benefits).display === 'none',
			legacyMessage: document.body.innerText.includes('Ваша корзина пока пуста'),
		};
	})()` );

	if (
		! result.afterFinalRemoval.brandedState
		|| ! result.afterFinalRemoval.emptyClass
		|| ! result.afterFinalRemoval.stepsHidden
		|| ! result.afterFinalRemoval.benefitsHidden
		|| result.afterFinalRemoval.legacyMessage
	) {
		throw new Error( `Dynamic empty-cart state failed: ${ JSON.stringify( result.afterFinalRemoval ) }` );
	}

	console.log( JSON.stringify( result ) );
} finally {
	try {
		await command( 'Browser.close' );
	} catch ( error ) {
		chrome.kill();
	}

	socket.close();
	await sleep( 500 );
	for ( let attempt = 0; attempt < 10; attempt += 1 ) {
		try {
			await rm( profileDir, { recursive: true, force: true } );
			break;
		} catch ( error ) {
			if ( attempt === 9 ) {
				throw error;
			}

			await sleep( 300 );
		}
	}
}
