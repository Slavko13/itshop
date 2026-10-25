import { spawn } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const baseUrl = process.env.ITSOFT_BASE_URL || 'http://localhost:8080';
const capture = process.env.ITSOFT_CAPTURE_CART === '1';
const profileDir = await mkdtemp(join(tmpdir(), 'itsoft-empty-cart-'));
const chrome = spawn(chromePath, [
	'--headless=new',
	'--disable-gpu',
	'--disable-software-rasterizer',
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

async function checkViewport(width, height) {
	await command('Emulation.setDeviceMetricsOverride', {
		width,
		height,
		deviceScaleFactor: 1,
		mobile: width < 600,
	});
	const loaded = once('Page.loadEventFired');
	await command('Page.navigate', { url: `${baseUrl}/cart/` });
	await loaded;
	await delay(400);

	const result = await evaluate(`(() => {
		const state = document.querySelector('.cart-empty-state');
		const logo = document.querySelector('.header-main__logo')?.getBoundingClientRect();
		const catalog = document.querySelector('.header-main__catalog');
		const catalogRect = catalog?.getBoundingClientRect();
		const search = document.querySelector('.header-main__search')?.getBoundingClientRect();
		const user = document.querySelector('.header-main__user')?.getBoundingClientRect();
		const stateRect = state?.getBoundingClientRect();
		const buttonRect = state?.querySelector('.cart-empty-state__button')?.getBoundingClientRect();
		const benefits = [...(state?.querySelectorAll('.cart-empty-state__benefits li') || [])].map((item) => {
			const itemRect = item.getBoundingClientRect();
			const iconRect = item.querySelector('.cart-empty-state__benefit-icon')?.getBoundingClientRect();
			const textRect = item.querySelector('.cart-empty-state__benefit-text')?.getBoundingClientRect();
			return {
				itemLeft: Math.round(itemRect.left),
				iconLeft: Math.round(iconRect?.left || 0),
				textLeft: Math.round(textRect?.left || 0),
				textAlign: getComputedStyle(item).textAlign,
			};
		});
		const cartStylesheet = document.querySelector('#itsoft-cart-css')?.href || '';
		return {
			title: state?.querySelector('h2')?.textContent.trim() || '',
			button: state?.querySelector('.cart-empty-state__button')?.textContent.trim() || '',
			oldMessage: document.body.innerText.includes('Ваша корзина пока пуста'),
			stepsVisible: Boolean(document.querySelector('.cart-steps')),
			overflow: Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth),
			stateWidth: Math.round(state?.getBoundingClientRect().width || 0),
			buttonInside: Boolean(stateRect && buttonRect) && buttonRect.left >= stateRect.left && buttonRect.right <= stateRect.right,
			benefits,
			catalogVisible: catalog ? getComputedStyle(catalog).display !== 'none' : false,
			cacheBustedStylesheet: cartStylesheet.includes('itsoft_v='),
			headerOrder: logo && catalogRect && search && user
				? [logo.x, catalogRect.x, search.x, user.x].every((value, index, values) => index === 0 || value > values[index - 1])
				: false,
		};
	})()`);

	if (!result.title || !result.button || result.oldMessage || result.stepsVisible || result.overflow > 0 || !result.cacheBustedStylesheet) {
		throw new Error(`${width}px empty-cart check failed: ${JSON.stringify(result)}`);
	}
	if (width >= 1025 && (!result.catalogVisible || !result.headerOrder)) {
		throw new Error(`${width}px desktop header order failed: ${JSON.stringify(result)}`);
	}
	if (width < 600 && result.catalogVisible) {
		throw new Error(`${width}px mobile catalog button should be hidden: ${JSON.stringify(result)}`);
	}
	if (width < 600) {
		const alignedBenefits = result.benefits.length === 3
			&& new Set(result.benefits.map((item) => item.iconLeft)).size === 1
			&& new Set(result.benefits.map((item) => item.textLeft)).size === 1
			&& result.benefits.every((item) => item.textAlign === 'left');
		if (!result.buttonInside || !alignedBenefits) {
			throw new Error(`${width}px empty-cart mobile alignment failed: ${JSON.stringify(result)}`);
		}
	}

	if (capture) {
		const screenshot = await command('Page.captureScreenshot', { format: 'png', fromSurface: true });
		writeFileSync(resolve(`empty-cart-redesign-${width}.png`), Buffer.from(screenshot.data, 'base64'));
	}

	console.log(`${width}px PASS ${JSON.stringify(result)}`);
}

try {
	await command('Page.enable');
	await command('Runtime.enable');
	await command('Network.enable');
	await command('Network.setCacheDisabled', { cacheDisabled: true });
	await checkViewport(393, 900);
	await checkViewport(1280, 900);
	console.log('All empty-cart responsive checks passed.');
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
