import { spawn } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync } from 'node:fs';
import { rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const baseUrl = process.env.ITSOFT_BASE_URL || 'http://localhost:8080';
const profileDir = mkdtempSync(join(tmpdir(), 'itsoft-interface-system-'));
const widths = [320, 393, 768, 1280, 1440];
const routes = [
	['home', '/'],
	['catalog', '/shop/'],
	['category', '/product-category/microsoft-office/office-2021/'],
	['tag', '/product-tag/outlook/'],
	['product', '/product/windows-11-iot-enterprise-2024-ltsc-korporativnaya/'],
	['cart', '/cart/'],
	['checkout', '/checkout/?add-to-cart=327'],
	['account', '/my-account/'],
	['faq', '/faq/?faq_style_test=1'],
	['reviews', '/reviews/'],
	['downloads', '/download/'],
	['distribution', '/distribution/microsoft-visio-2021-professional/'],
	['article', '/stati/kak-aktivirovat-windows-10-11-po-czifrovomu-klyuchu/'],
	['search', '/?s=Windows%2011'],
];

if (!existsSync(chromePath)) throw new Error('Chrome not found: ' + chromePath);

const chrome = spawn(chromePath, [
	'--headless=new',
	'--disable-gpu',
	'--no-sandbox',
	'--hide-scrollbars',
	'--no-first-run',
	'--no-default-browser-check',
	'--remote-debugging-port=0',
	'--user-data-dir=' + profileDir,
	'about:blank',
], { stdio: 'ignore', windowsHide: true });

const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function devtoolsTarget() {
	const portFile = join(profileDir, 'DevToolsActivePort');
	for (let attempt = 0; attempt < 120; attempt += 1) {
		if (existsSync(portFile)) {
			const [port] = readFileSync(portFile, 'utf8').trim().split(/\r?\n/);
			const response = await fetch('http://127.0.0.1:' + port + '/json/new?about:blank', { method: 'PUT' });
			if (response.ok) return response.json();
		}
		if (chrome.exitCode !== null) throw new Error('Chrome exited with ' + chrome.exitCode);
		await delay(100);
	}
	throw new Error('Chrome DevTools endpoint did not become ready.');
}

const target = await devtoolsTarget();
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
		const request = pending.get(message.id);
		pending.delete(message.id);
		message.error ? request.reject(new Error(message.error.message)) : request.resolve(message.result);
		return;
	}
	if (message.method && events.has(message.method)) {
		const listeners = events.get(message.method).splice(0);
		listeners.forEach((listener) => listener(message.params));
	}
});

function command(method, params = {}) {
	const id = ++nextId;
	return new Promise((resolve, reject) => {
		pending.set(id, { resolve, reject });
		socket.send(JSON.stringify({ id, method, params }));
	});
}

function once(method) {
	return new Promise((resolve) => {
		if (!events.has(method)) events.set(method, []);
		events.get(method).push(resolve);
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

const failures = [];
let checks = 0;

try {
	await command('Page.enable');
	await command('Runtime.enable');
	await command('Network.enable');
	await command('Network.setCacheDisabled', { cacheDisabled: true });

	for (const width of widths) {
		await command('Emulation.setDeviceMetricsOverride', {
			width,
			height: width < 600 ? 1100 : 900,
			deviceScaleFactor: 1,
			mobile: width < 600,
		});

		for (const [name, path] of routes) {
			const loaded = once('Page.loadEventFired');
			await command('Page.navigate', { url: new URL(path, baseUrl).toString() });
			await loaded;
			await delay(180);

			const state = await evaluate('(() => {' +
				'const visible = (element) => {' +
					'if (!element) return false;' +
					'const style = getComputedStyle(element);' +
					'const rect = element.getBoundingClientRect();' +
					'return style.display !== "none" && style.visibility !== "hidden" && rect.width > 0 && rect.height > 0;' +
				'};' +
				'const textInputs = [...document.querySelectorAll("input, select, textarea")]' +
					'.filter((element) => visible(element) && !["hidden", "checkbox", "radio", "submit", "button"].includes(element.type));' +
				'const undersizedTextInputs = textInputs' +
					'.filter((element) => innerWidth < 600 && parseFloat(getComputedStyle(element).fontSize) < 16)' +
					'.map((element) => element.id || element.name || element.className || element.tagName);' +
				'const primaryActions = [...document.querySelectorAll(' +
					'".catalog-card__buy, .product-purchase__button, .checkout-button, #place_order, .account-form__submit, .home-hero__button--primary"' +
				')].filter(visible);' +
				'const undersizedActions = primaryActions' +
					'.filter((element) => element.getBoundingClientRect().height < 44)' +
					'.map((element) => element.className || element.id || element.tagName);' +
				'return {' +
					'ready: document.readyState,' +
					'path: location.pathname,' +
					'bodyClass: document.body.classList.contains("itsoft-ui"),' +
					'stylesheet: Boolean(document.querySelector("link[id=itsoft-interface-system-css]")),' +
					'font: getComputedStyle(document.body).fontFamily,' +
					'overflow: Math.max(0, document.documentElement.scrollWidth - innerWidth),' +
					'undersizedTextInputs,' +
					'undersizedActions,' +
					'brokenImages: [...document.images]' +
						'.filter((image) => visible(image) && image.complete && image.naturalWidth === 0).length,' +
				'};' +
			'})()');

			const pass = state.ready === 'complete'
				&& state.bodyClass
				&& state.stylesheet
				&& state.font.includes('Inter')
				&& state.overflow <= 1
				&& state.undersizedTextInputs.length === 0
				&& state.undersizedActions.length === 0
				&& state.brokenImages === 0;
			checks += 1;
			if (!pass) failures.push({ width, name, path, state });
			console.log((pass ? 'PASS' : 'FAIL') + ' ' + width + 'px ' + name + (pass ? '' : ' ' + JSON.stringify(state)));
		}
	}
} finally {
	try {
		await command('Browser.close');
	} catch {
		chrome.kill();
	}
	socket.close();
	await delay(400);
	for (let attempt = 0; attempt < 6; attempt += 1) {
		try {
			await rm(profileDir, { recursive: true, force: true });
			break;
		} catch (error) {
			if (attempt === 5 || error.code !== 'EBUSY') throw error;
			await delay(250);
		}
	}
}

if (failures.length) {
	throw new Error(failures.length + '/' + checks + ' interface checks failed:\n' + JSON.stringify(failures, null, 2));
}

console.log('All ' + checks + ' cross-page interface checks passed.');
