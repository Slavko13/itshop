import { spawn } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const baseUrl = process.env.ITSOFT_BASE_URL || 'http://localhost:8080';
const capture = process.env.ITSOFT_CAPTURE_PRODUCT === '1';
const profileDir = await mkdtemp(join(tmpdir(), 'itsoft-product-redesign-'));
const chrome = spawn(chromePath, [
	'--headless=new',
	'--disable-gpu',
	'--no-sandbox',
	'--hide-scrollbars',
	'--no-first-run',
	'--no-default-browser-check',
	'--remote-debugging-port=0',
	`--user-data-dir=${profileDir}`,
	'about:blank',
], { stdio: 'ignore', windowsHide: true });

const delay = (milliseconds) => new Promise((resolveDelay) => setTimeout(resolveDelay, milliseconds));

async function getTarget() {
	const portFile = join(profileDir, 'DevToolsActivePort');
	for (let attempt = 0; attempt < 100; attempt += 1) {
		if (existsSync(portFile)) {
			const [port] = readFileSync(portFile, 'utf8').trim().split(/\r?\n/);
			const response = await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' });
			if (response.ok) return response.json();
		}
		await delay(100);
	}
	throw new Error('Chrome DevTools endpoint did not become ready.');
}

const target = await getTarget();
const socket = new WebSocket(target.webSocketDebuggerUrl);
const pending = new Map();
const events = new Map();
let nextId = 0;

await new Promise((resolveOpen, reject) => {
	socket.addEventListener('open', resolveOpen, { once: true });
	socket.addEventListener('error', reject, { once: true });
});

socket.addEventListener('message', ({ data }) => {
	const message = JSON.parse(data);
	if (message.id && pending.has(message.id)) {
		const request = pending.get(message.id);
		pending.delete(message.id);
		message.error ? request.reject(new Error(message.error.message)) : request.resolve(message.result);
		return;
	}
	if (message.method && events.has(message.method)) {
		events.get(message.method).splice(0).forEach((listener) => listener(message.params));
	}
});

function command(method, params = {}) {
	const id = ++nextId;
	return new Promise((resolvePromise, rejectPromise) => {
		pending.set(id, { resolve: resolvePromise, reject: rejectPromise });
		socket.send(JSON.stringify({ id, method, params }));
	});
}

function once(method) {
	return new Promise((resolveEvent) => {
		if (!events.has(method)) events.set(method, []);
		events.get(method).push(resolveEvent);
	});
}

async function evaluate(expression) {
	const response = await command('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
	if (response.exceptionDetails) throw new Error(response.exceptionDetails.text || 'Browser evaluation failed.');
	return response.result.value;
}

async function openProduct(path, width, height, name) {
	await command('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 600 });
	const loaded = once('Page.loadEventFired');
	await command('Page.navigate', { url: `${baseUrl}${path}` });
	await loaded;
	await delay(650);

	const result = await evaluate(`(() => {
		const page = document.querySelector('.product-page');
		const button = page?.querySelector('[data-cart-action="add"]');
		const price = page?.querySelector('[data-price-field="current"]');
		const widest = [...document.querySelectorAll('body *')]
			.map((element) => ({ element, right: element.getBoundingClientRect().right }))
			.sort((a, b) => b.right - a.right)[0];
		const layoutSelectors = '.product-images, .product-purchase, .product-benefit, .product-extra__info, .product-extra__about, .product-steps, .product-extra__resources, .product-upsells-grid, .product-upsells .catalog-grid__item';
		const layoutOverflow = [...document.querySelectorAll(layoutSelectors)]
			.map((element) => {
				const rect = element.getBoundingClientRect();
				return { className: element.className || '', left: Math.round(rect.left), right: Math.round(rect.right) };
			})
			.filter((rect) => rect.left < -1 || rect.right > document.documentElement.clientWidth + 1);
		const upsellCards = [...document.querySelectorAll('.product-upsells .catalog-card')];
		const upsellButtons = [...document.querySelectorAll('.product-upsells .catalog-card__buy')];
		return {
			title: page?.querySelector('.product-title')?.textContent.trim() || '',
			h1Count: page?.querySelectorAll('h1').length || 0,
			hasHero: Boolean(document.querySelector('.hero-small')),
			hasGallery: Boolean(page?.querySelector('.product-gallery__main img')),
			fulfilment: page?.querySelector('.product-purchase__type')?.textContent.trim() || '',
			stock: page?.querySelector('.product-stock')?.textContent.trim() || '',
			price: price?.textContent.trim() || '',
			button: button?.textContent.trim() || '',
			buttonDisabled: Boolean(button?.disabled),
			overflow: Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth),
			viewport: document.documentElement.clientWidth,
			widest: widest ? { tag: widest.element.tagName, className: widest.element.className || '', right: Math.round(widest.right) } : null,
			layoutOverflow,
			upsells: {
				count: upsellCards.length,
				cardHeights: upsellCards.map((card) => Math.round(card.getBoundingClientRect().height)),
				bottomGaps: upsellCards.map((card) => {
					const cardRect = card.getBoundingClientRect();
					const actionsRect = card.querySelector('.catalog-card__actions')?.getBoundingClientRect();
					return actionsRect ? Math.round(cardRect.bottom - actionsRect.bottom) : 0;
				}),
				buttonHeights: upsellButtons.map((upsellButton) => Math.round(upsellButton.getBoundingClientRect().height)),
				buttonColors: upsellButtons.map((upsellButton) => getComputedStyle(upsellButton).backgroundColor),
				buttonTransforms: upsellButtons.map((upsellButton) => getComputedStyle(upsellButton).transform),
			},
			rects: Object.fromEntries(['.product-page', '.breadcrumbs', '.breadcrumbs__list', '.product-top', '.product-purchase', '.product-title'].map((selector) => {
				const rect = document.querySelector(selector)?.getBoundingClientRect();
				return [selector, rect ? { x: Math.round(rect.x), width: Math.round(rect.width), right: Math.round(rect.right) } : null];
			})),
			stylesheet: document.querySelector('#itsoft-product-redesign-css')?.href || '',
		};
	})()`);

	if (!result.title || result.h1Count !== 1 || result.hasHero || !result.hasGallery || !result.fulfilment || !result.price || !result.button || !result.stylesheet) {
		throw new Error(`${name} structure failed: ${JSON.stringify(result)}`);
	}
	if (result.overflow > 0 || result.layoutOverflow.length > 0) {
		throw new Error(`${name} horizontal overflow: ${JSON.stringify(result)}`);
	}
	if (result.upsells.count) {
		const buttonsAreStable = result.upsells.buttonHeights.every((height) => height === 46)
			&& result.upsells.buttonColors.every((color) => color === 'rgb(108, 76, 255)')
			&& result.upsells.buttonTransforms.every((transform) => transform === 'none');
		if (!buttonsAreStable) {
			throw new Error(`${name} upsell button styles failed: ${JSON.stringify(result.upsells)}`);
		}
		if (width >= 1024 && new Set(result.upsells.cardHeights).size !== 1) {
			throw new Error(`${name} upsell cards have unequal heights: ${JSON.stringify(result.upsells.cardHeights)}`);
		}
		if (result.upsells.bottomGaps.some((gap) => gap > 17)) {
			throw new Error(`${name} upsell cards reserve empty space after actions: ${JSON.stringify(result.upsells.bottomGaps)}`);
		}
	}

	if (capture) {
		const screenshot = await command('Page.captureScreenshot', { format: 'png', fromSurface: true });
		writeFileSync(resolve(`product-redesign-${name}.png`), Buffer.from(screenshot.data, 'base64'));
	}

	console.log(`${name} PASS ${JSON.stringify(result)}`);
	return result;
}

try {
	await command('Page.enable');
	await command('Runtime.enable');
	await command('Network.enable');
	await command('Network.setCacheDisabled', { cacheDisabled: true });

	await openProduct('/product/windows-11-iot-enterprise-2024-ltsc-korporativnaya/', 320, 900, 'digital-320');
	await openProduct('/product/windows-11-iot-enterprise-2024-ltsc-korporativnaya/', 393, 900, 'digital-393');
	await evaluate('window.scrollTo(0, document.documentElement.scrollHeight); true');
	await delay(350);
	const stickyState = await evaluate(`(() => {
		const sticky = document.querySelector('[data-product-sticky]');
		return {
			visible: Boolean(sticky) && !sticky.hidden && getComputedStyle(sticky).display !== 'none',
			price: sticky?.querySelector('[data-sticky-price]')?.textContent.trim() || '',
			button: sticky?.querySelector('[data-product-sticky-action]')?.textContent.trim() || '',
		};
	})()`);
	if (!stickyState.visible || !stickyState.price || !stickyState.button) {
		throw new Error(`Mobile sticky purchase failed: ${JSON.stringify(stickyState)}`);
	}
	console.log(`mobile-sticky PASS ${JSON.stringify(stickyState)}`);
	await openProduct('/product/windows-11-iot-enterprise-2024-ltsc-korporativnaya/', 768, 900, 'digital-768');
	await openProduct('/product/windows-11-iot-enterprise-2024-ltsc-korporativnaya/', 1280, 900, 'digital-1280');
	await openProduct('/product/windows-11-iot-enterprise-2024-ltsc-korporativnaya/', 1440, 900, 'digital-1440');

	const importedElectronic = await openProduct('/product/microsoft-office-2021-professional-plus/', 1280, 900, 'imported-electronic-variable');
	if (!/(электрон|electronic)/i.test(importedElectronic.fulfilment) || /(товар с доставкой|physical delivery)/i.test(importedElectronic.fulfilment)) {
		throw new Error(`Imported product did not use electronic fulfilment: ${JSON.stringify(importedElectronic)}`);
	}

	const variationResult = await evaluate(`(() => {
		const options = [...document.querySelectorAll('[data-variation-option]')];
		const before = document.querySelector('[data-price-field="current"]')?.textContent.trim() || '';
		options[1]?.click();
		const after = document.querySelector('[data-price-field="current"]')?.textContent.trim() || '';
		return { count: options.length, before, after, id: document.querySelector('[data-form-field="variation-id"]')?.value || '' };
	})()`);
	if (variationResult.count < 2 || variationResult.before === variationResult.after || !variationResult.id) {
		throw new Error(`Variation switching failed: ${JSON.stringify(variationResult)}`);
	}
	console.log(`variation PASS ${JSON.stringify(variationResult)}`);

	const unavailable = await openProduct('/product/windows-11-pro-professionalnaya/', 1280, 900, 'unavailable');
	if (!unavailable.buttonDisabled || !/(нет в наличии|out of stock)/i.test(unavailable.button)) {
		throw new Error(`Unavailable state failed: ${JSON.stringify(unavailable)}`);
	}

	console.log('All product redesign responsive checks passed.');
} finally {
	try {
		await command('Browser.close');
	} catch {
		chrome.kill();
	}
	socket.close();
	await delay(500);
	for (let attempt = 0; attempt < 10; attempt += 1) {
		try {
			await rm(profileDir, { recursive: true, force: true });
			break;
		} catch (error) {
			if (attempt === 9) throw error;
			await delay(250);
		}
	}
}
