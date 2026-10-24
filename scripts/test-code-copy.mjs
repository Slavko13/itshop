import { spawn } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync } from 'node:fs';
import { rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const baseUrl = process.env.ITSOFT_BASE_URL || 'http://localhost:8080';
const articleUrl = new URL('/stati/kak-aktivirovat-windows-10-11-po-czifrovomu-klyuchu/', baseUrl).toString();
const profileDir = mkdtempSync(join(tmpdir(), 'itsoft-code-copy-'));
const chrome = spawn(chromePath, [
	'--headless=new',
	'--disable-gpu',
	'--no-first-run',
	'--no-default-browser-check',
	'--remote-debugging-port=0',
	`--user-data-dir=${profileDir}`,
	'about:blank',
], { stdio: 'ignore', windowsHide: true });
const delay = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

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

await new Promise((resolve, reject) => {
	socket.addEventListener('open', resolve, { once: true });
	socket.addEventListener('error', reject, { once: true });
});
socket.addEventListener('message', ({ data }) => {
	const message = JSON.parse(data);
	if (message.id && pending.has(message.id)) {
		const callback = pending.get(message.id);
		pending.delete(message.id);
		callback(message.result);
		return;
	}
	if (message.method && events.has(message.method)) {
		events.get(message.method).splice(0).forEach((listener) => listener(message.params));
	}
});
const command = (method, params = {}) => new Promise((resolve) => {
	const id = ++nextId;
	pending.set(id, resolve);
	socket.send(JSON.stringify({ id, method, params }));
});
const once = (method) => new Promise((resolve) => {
	if (!events.has(method)) events.set(method, []);
	events.get(method).push(resolve);
});

let exitCode = 0;
try {
	await command('Page.enable');
	await command('Runtime.enable');
	await command('Emulation.setDeviceMetricsOverride', {
		width: 393,
		height: 1000,
		deviceScaleFactor: 1,
		mobile: true,
	});
	const loaded = once('Page.loadEventFired');
	await command('Page.navigate', { url: articleUrl });
	await loaded;
	await delay(1500);

	const setupResponse = await command('Runtime.evaluate', {
		expression: `(() => {
			const code = document.querySelector('code:not([data-license-value])');
			const button = code?.querySelector('.code__copy');
			if (!code || !button) return { found: false };

			Object.defineProperty(navigator, 'clipboard', { value: undefined, configurable: true });
			code.dataset.copyTestExpected = code.textContent.trim();
			button.scrollIntoView({ block: 'center', inline: 'center' });
			const rect = button.getBoundingClientRect();
			return {
				found: true,
				x: rect.left + rect.width / 2,
				y: rect.top + rect.height / 2,
			};
		})()`,
		returnByValue: true,
	});
	const setup = setupResponse.result.value;
	if (setup?.found) {
		await command('Input.dispatchMouseEvent', { type: 'mousePressed', x: setup.x, y: setup.y, button: 'left', clickCount: 1 });
		await command('Input.dispatchMouseEvent', { type: 'mouseReleased', x: setup.x, y: setup.y, button: 'left', clickCount: 1 });
		await delay(250);
	}

	const response = await command('Runtime.evaluate', {
		expression: `(() => {
			const code = document.querySelector('code:not([data-license-value])');
			const button = code?.querySelector('.code__copy');
			const product = document.querySelector('.plate-products__item');
			const productTitle = product?.querySelector('.plate-products__link');
			const productPrice = product?.querySelector('.plate-products__price');
			const productButton = product?.querySelector('.plate-products__buy');
			const titleRange = productTitle ? document.createRange() : null;
			if (titleRange) titleRange.selectNodeContents(productTitle);
			const titleLines = titleRange ? [...titleRange.getClientRects()] : [];
			if (!code || !button) return { found: false };
			return {
				found: true,
				buttonStillPresent: button.isConnected && code.contains(button),
				copiedState: button.classList.contains('is-copied'),
				errorState: button.classList.contains('is-error'),
				valueUnchanged: code.textContent.trim() === code.dataset.copyTestExpected,
				pageFitsViewport: document.documentElement.scrollWidth <= window.innerWidth + 1,
				productFitsViewport: Boolean(product) && product.scrollWidth <= product.clientWidth + 1,
				productUsesMobileGrid: Boolean(product) && getComputedStyle(product).display === 'grid',
				titleHasUsefulLastLine: titleLines.length > 0 && titleLines.at(-1).width >= 30,
				priceStaysVisible: Boolean(productPrice) && productPrice.getBoundingClientRect().right <= product.getBoundingClientRect().right + 1,
				buyButtonUsesFullRow: Boolean(productButton) && productButton.getBoundingClientRect().width >= product.getBoundingClientRect().width - 40,
			};
		})()`,
		returnByValue: true,
	});
	const result = response.result.value;
	console.log(JSON.stringify(result, null, 2));
	if (!result?.found || !result.buttonStillPresent || !result.copiedState || result.errorState || !result.valueUnchanged
		|| !result.pageFitsViewport || !result.productFitsViewport || !result.productUsesMobileGrid
		|| !result.titleHasUsefulLastLine || !result.priceStaysVisible || !result.buyButtonUsesFullRow) {
		exitCode = 1;
	}
} finally {
	try { await command('Browser.close'); } catch { chrome.kill(); }
	socket.close();
	await delay(300);
	await rm(profileDir, { recursive: true, force: true });
}

process.exitCode = exitCode;
