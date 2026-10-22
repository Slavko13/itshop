import { spawn } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const baseUrl = process.env.ITSOFT_BASE_URL || 'http://localhost:8080';
const username = process.env.ITSOFT_ACCOUNT_USER || '';
const password = process.env.ITSOFT_ACCOUNT_PASSWORD || '';
const capture = process.env.ITSOFT_CAPTURE_ACCOUNT === '1';
const outputDir = resolve(process.cwd());

if (!username || !password) {
	throw new Error('Set ITSOFT_ACCOUNT_USER and ITSOFT_ACCOUNT_PASSWORD.');
}

const profileDir = mkdtempSync(join(tmpdir(), 'itsoft-account-responsive-'));
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
		message.error ? request.rejectPromise(new Error(message.error.message)) : request.resolvePromise(message.result);
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
	const response = await command('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
	if (response.exceptionDetails) throw new Error(response.exceptionDetails.text);
	return response.result.value;
}

async function navigate(path) {
	const loaded = once('Page.loadEventFired');
	await command('Page.navigate', { url: new URL(path, baseUrl).toString() });
	await loaded;
	await delay(450);
}

async function setViewport(width, height = 1200) {
	await command('Emulation.setDeviceMetricsOverride', {
		width,
		height,
		deviceScaleFactor: 1,
		mobile: width < 600,
		screenWidth: width,
		screenHeight: height,
	});
}

async function screenshot(name) {
	const result = await command('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
	writeFileSync(join(outputDir, name), Buffer.from(result.data, 'base64'));
}

async function login() {
	await setViewport(1280, 1000);
	await navigate('/my-account/');
	const loaded = once('Page.loadEventFired');
	const clicked = await evaluate(`(() => {
		const user = document.querySelector('#username');
		const pass = document.querySelector('#password');
		const button = document.querySelector('button[name="login"]');
		if (!user || !pass || !button) return false;
		user.value = ${JSON.stringify(username)};
		pass.value = ${JSON.stringify(password)};
		user.dispatchEvent(new Event('input', { bubbles: true }));
		pass.dispatchEvent(new Event('input', { bubbles: true }));
		button.click();
		return true;
	})()`);
	if (!clicked) throw new Error('Login form was not found.');
	await Promise.race([loaded, delay(5000)]);
	await delay(500);
	const state = await evaluate(`({ loggedIn: document.body.classList.contains('logged-in'), url: location.href, errors: document.querySelector('.woocommerce-error')?.innerText || '' })`);
	if (!state.loggedIn) throw new Error(`Login failed at ${state.url}: ${state.errors}`);
}

await command('Page.enable');
await command('Runtime.enable');

const checks = [];

try {
	for (const width of [393, 768, 1280]) {
		await setViewport(width, width < 600 ? 1500 : 1100);
		await navigate('/my-account/');
		const guest = await evaluate(`(() => {
			const card = document.querySelector('.account-auth');
			const form = document.querySelector('.account-form');
			const cardRect = card?.getBoundingClientRect();
			const formRect = form?.getBoundingClientRect();
			return {
				viewport: innerWidth,
				scrollWidth: document.documentElement.scrollWidth,
				hasAuth: !!card,
				cardLeft: cardRect?.left || 0,
				cardRight: cardRect?.right || 0,
				formRight: formRect?.right || 0
			};
		})()`);
		const overflow = Math.max(0, guest.scrollWidth - guest.viewport);
		const clipped = Math.max(0, Math.max(guest.cardRight, guest.formRight) - guest.viewport);
		const pass = guest.hasAuth && overflow <= 1 && clipped <= 1;
		checks.push({ page: 'guest', width, overflow, clipped, pass });
		console.log(`${pass ? 'PASS' : 'FAIL'} guest ${width}px overflow=${overflow}px clipped=${clipped}px card=${guest.cardLeft}-${guest.cardRight}`);
	}

	await login();

	const cases = [
		{ path: '/my-account/', name: 'dashboard', widths: [393, 768, 1280] },
		{ path: '/my-account/orders/', name: 'orders', widths: [393, 1280] },
		{ path: '/my-account/licenses/', name: 'licenses', widths: [393, 1280] },
		{ path: '/my-account/edit-account/', name: 'profile', widths: [393, 1280] },
		{ path: '/my-account/edit-address/', name: 'addresses', widths: [393, 1280] },
	];

	for (const testCase of cases) {
		for (const width of testCase.widths) {
			await setViewport(width, width < 600 ? 1500 : 1100);
			await navigate(testCase.path);
			const metrics = await evaluate(`({
				url: location.pathname,
				viewport: innerWidth,
				scrollWidth: document.documentElement.scrollWidth,
				hasShell: !!document.querySelector('.account-shell'),
				hasContent: !!document.querySelector('.account-shell__content'),
				hasMobileNav: getComputedStyle(document.querySelector('.account-nav-mobile')).display !== 'none'
			})`);
			const overflow = Math.max(0, metrics.scrollWidth - metrics.viewport);
			const pass = metrics.hasShell && metrics.hasContent && overflow <= 1 && (width > 820 || metrics.hasMobileNav);
			checks.push({ page: testCase.name, width, overflow, pass, url: metrics.url });
			console.log(`${pass ? 'PASS' : 'FAIL'} ${testCase.name} ${width}px overflow=${overflow}px url=${metrics.url}`);
			if (capture && (width === 393 || width === 1280)) await screenshot(`account-${testCase.name}-${width}.png`);
		}
	}
} finally {
	socket.close();
	const chromeExited = new Promise((resolveExit) => chrome.once('exit', resolveExit));
	chrome.kill();
	await Promise.race([chromeExited, delay(2000)]);
	for (let attempt = 0; attempt < 5; attempt += 1) {
		try {
			await rm(profileDir, { recursive: true, force: true });
			break;
		} catch (error) {
			if (attempt === 4 || error.code !== 'EBUSY') throw error;
			await delay(250);
		}
	}
}

if (checks.some((check) => !check.pass)) {
	process.exitCode = 1;
} else {
	console.log('\nAll account responsive checks passed.');
}
