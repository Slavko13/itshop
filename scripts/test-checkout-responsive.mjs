import { spawn } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const baseUrl = process.env.ITSOFT_BASE_URL || 'http://localhost:8080';
const checkoutUrl = new URL('/checkout/?add-to-cart=333', baseUrl).toString();
const receivedUrl = process.argv[2];
const orderEmail = process.env.ITSOFT_ORDER_EMAIL || '';
const captureAll = process.env.ITSOFT_CAPTURE_ALL === '1';
const widths = (process.env.ITSOFT_CHECKOUT_WIDTHS || '393,768,1280')
	.split(',')
	.map((value) => Number.parseInt(value, 10))
	.filter(Number.isFinite);

if (!receivedUrl) {
	throw new Error('Pass an order-received URL as the first argument.');
}

const profileDir = mkdtempSync(join(tmpdir(), 'itsoft-checkout-responsive-'));
const chrome = spawn(chromePath, [
	'--headless=new',
	'--disable-gpu',
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
		const { resolvePromise, rejectPromise } = pending.get(message.id);
		pending.delete(message.id);
		if (message.error) rejectPromise(new Error(message.error.message));
		else resolvePromise(message.result);
		return;
	}
	if (message.method && events.has(message.method)) {
		const listeners = events.get(message.method).splice(0);
		listeners.forEach((listener) => listener(message.params));
	}
});

function command(method, params = {}) {
	const id = ++nextId;
	return new Promise((resolvePromise, rejectPromise) => {
		pending.set(id, { resolvePromise, rejectPromise });
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
	const response = await command('Runtime.evaluate', {
		expression,
		returnByValue: true,
		awaitPromise: true,
	});
	if (response.exceptionDetails) throw new Error(response.exceptionDetails.text);
	return response.result.value;
}

async function navigate(url) {
	const loaded = once('Page.loadEventFired');
	await command('Page.navigate', { url });
	await loaded;
	await delay(3000);
}

async function inspect(label, url, width) {
	await command('Emulation.setDeviceMetricsOverride', {
		width,
		height: 900,
		deviceScaleFactor: 1,
		mobile: width <= 430,
	});
	await navigate(url);
	if (label === 'stage3' && orderEmail) {
		const confirmationSubmitted = await evaluate(`(() => {
			const input = document.querySelector('input[type="email"]');
			const button = input?.closest('form')?.querySelector('button, input[type="submit"]');
			if (!input || !button) return false;
			const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
			setter.call(input, ${JSON.stringify(orderEmail)});
			input.dispatchEvent(new Event('input', { bubbles: true }));
			input.dispatchEvent(new Event('change', { bubbles: true }));
			button.click();
			return true;
		})()`);
		if (confirmationSubmitted) await delay(2000);
	}
	const result = await evaluate(`(() => {
		const root = document.documentElement;
		const content = document.querySelector('.checkout-page__content');
		const main = document.querySelector('.wc-block-checkout__main');
		const sidebar = document.querySelector('.wc-block-checkout__sidebar');
		return {
			label: ${JSON.stringify(label)},
			width: window.innerWidth,
			overflow: root.scrollWidth - window.innerWidth,
			contentWidth: Math.round(content?.getBoundingClientRect().width || 0),
			mainWidth: Math.round(main?.getBoundingClientRect().width || 0),
			sidebarWidth: Math.round(sidebar?.getBoundingClientRect().width || 0),
			stage: document.querySelector('.cart-steps__item--active')?.textContent.trim() || '',
		};
	})()`);

	if (width === 393 || captureAll) {
		const dimensions = await command('Page.getLayoutMetrics');
		const screenshot = await command('Page.captureScreenshot', {
			format: 'png',
			captureBeyondViewport: true,
			clip: {
				x: 0,
				y: 0,
				width: dimensions.cssContentSize.width,
				height: dimensions.cssContentSize.height,
				scale: 1,
			},
		});
		writeFileSync(resolve(`checkout-${label}-${width}.png`), Buffer.from(screenshot.data, 'base64'));
	}

	return result;
}

const results = [];
let exitCode = 0;

try {
	await command('Page.enable');
	await command('Runtime.enable');
	for (const width of widths) {
		results.push(await inspect('stage2', checkoutUrl, width));
		results.push(await inspect('stage3', receivedUrl, width));
	}
	for (const result of results) {
		if (result.overflow > 1) exitCode = 1;
	}
	console.log(JSON.stringify(results, null, 2));
} finally {
	try {
		await command('Browser.close');
	} catch {
		chrome.kill();
	}
	socket.close();
	await delay(300);
	for (let attempt = 0; attempt < 10; attempt += 1) {
		try {
			await rm(profileDir, { recursive: true, force: true });
			break;
		} catch (error) {
			if (attempt === 9) throw error;
			await delay(300);
		}
	}
}

process.exitCode = exitCode;
