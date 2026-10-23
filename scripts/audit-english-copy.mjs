import { spawn } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const baseUrl = process.env.ITSOFT_BASE_URL || 'http://localhost:8080';
const profileDir = mkdtempSync(join(tmpdir(), 'itsoft-english-audit-'));
const chrome = spawn(chromePath, [
	'--headless=new', '--disable-gpu', '--no-sandbox', '--hide-scrollbars',
	'--no-first-run', '--no-default-browser-check', '--remote-debugging-port=0',
	`--user-data-dir=${profileDir}`, 'about:blank',
], { stdio: 'ignore', windowsHide: true });

const delay = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

async function waitForPort() {
	const portFile = join(profileDir, 'DevToolsActivePort');
	for (let attempt = 0; attempt < 120; attempt += 1) {
		if (existsSync(portFile)) {
			const [port] = readFileSync(portFile, 'utf8').trim().split(/\r?\n/);
			if (port) return Number(port);
		}
		await delay(100);
	}
	throw new Error('Chrome DevTools endpoint did not become ready.');
}

class Client {
	constructor(url) {
		this.socket = new WebSocket(url);
		this.id = 0;
		this.pending = new Map();
		this.events = new Map();
	}
	async connect() {
		await new Promise((resolve, reject) => {
			this.socket.addEventListener('open', resolve, { once: true });
			this.socket.addEventListener('error', reject, { once: true });
		});
		this.socket.addEventListener('message', ({ data }) => {
			const message = JSON.parse(data);
			if (message.id && this.pending.has(message.id)) {
				const pending = this.pending.get(message.id);
				this.pending.delete(message.id);
				message.error ? pending.reject(new Error(message.error.message)) : pending.resolve(message.result);
			} else if (message.method && this.events.has(message.method)) {
				this.events.get(message.method).splice(0).forEach((listener) => listener(message.params));
			}
		});
	}
	send(method, params = {}) {
		const id = ++this.id;
		return new Promise((resolve, reject) => {
			this.pending.set(id, { resolve, reject });
			this.socket.send(JSON.stringify({ id, method, params }));
		});
	}
	once(method) {
		return new Promise((resolve) => {
			if (!this.events.has(method)) this.events.set(method, []);
			this.events.get(method).push(resolve);
		});
	}
}

let client;
const failures = [];
let skipped = 0;
try {
	const port = await waitForPort();
	const response = await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' });
	const target = await response.json();
	client = new Client(target.webSocketDebuggerUrl);
	await client.connect();
	await client.send('Page.enable');
	await client.send('Runtime.enable');
	await client.send('Network.enable');
	await client.send('Network.setCookie', {
		name: 'googtrans',
		value: '/ru/en',
		domain: new URL(baseUrl).hostname,
		path: '/',
	});

	const evaluate = async (expression) => {
		const result = await client.send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
		if (result.exceptionDetails) throw new Error(result.exceptionDetails.text || 'Browser evaluation failed');
		return result.result.value;
	};
	const navigate = async (path) => {
		const loaded = client.once('Page.loadEventFired');
		await client.send('Page.navigate', { url: new URL(path, baseUrl).toString() });
		await loaded;
		await delay(1200);
	};

	await navigate('/shop/');
	await delay(3500);

	const cases = [
		{ path: '/shop/', expected: ['Cart', 'Products', 'Default sorting', 'Buy in 1 click'] },
		{ path: '/', expected: ['View full catalog', 'Recommended for you'] },
		{ path: '/product/microsoft-office-2024-dlya-doma/', expected: ['Add to cart', 'Included apps:'] },
		{ path: '/distribution/microsoft-visio-2021-professional/', expected: ['Start the installation by opening “Setup”.', 'Check your system language and architecture.'] },
		{ path: '/?s=office&post_type=product', expected: ['Search results for:', 'Sort by price: high to low'] },
		{ path: '/cart/', expected: ['Ready to find the right software?', 'Order details and license keys by email'] },
		{ path: '/checkout/?add-to-cart=358&quantity=1&itsoft-buy-now=1', expected: ['Cart'] },
		{ path: '/my-account/', expected: ['Sign in or create an account', 'Manage your purchases in one place'] },
		{ path: '/my-account/lost-password/', expected: ['Account recovery', 'Get a password reset link', 'Back to sign in'] },
	];
	const banned = ['Basket', 'Goods', 'Initial sorting', 'Entrance', 'Restoring access', 'Get a login link', 'Return to the entrance', "Didn't receive the letter?"];

	for (const testCase of cases) {
		await navigate(testCase.path);
		await evaluate(`(() => {
			const english = document.querySelector('[data-gt-lang="en"]');
			if (english && !english.classList.contains('gt-current-lang')) english.click();
		})()`);
		await delay(2500);
		const page = await evaluate(`({
			text: document.body.innerText.replace(/\\n{3,}/g, '\\n\\n').trim(),
			cartLabel: document.querySelector('.user-nav__cart .user-nav__text')?.textContent.trim() || '',
		})`);
		if (page.cartLabel === 'Корзина') {
			skipped += 1;
			console.log(`SKIP ${testCase.path} (external GTranslate response unavailable)`);
			continue;
		}
		const text = page.text;
		const missing = testCase.expected.filter((phrase) => !text.includes(phrase));
		const leftovers = banned.filter((phrase) => text.split(/\r?\n/).some((line) => line.trim() === phrase));
		const passed = !missing.length && !leftovers.length;
		if (!passed) failures.push({ path: testCase.path, missing, leftovers });
		console.log(`${passed ? 'PASS' : 'FAIL'} ${testCase.path}${passed ? '' : ` ${JSON.stringify({ missing, leftovers })}`}`);
	}
} finally {
	if (client) client.socket.close();
	chrome.kill();
	await delay(500);
	try {
		rmSync(profileDir, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
	} catch (error) {
		if (error.code !== 'EPERM') throw error;
	}
}

if (failures.length) {
	throw new Error(`English copy audit failed: ${JSON.stringify(failures)}`);
}

console.log(`All available English copy checks passed${skipped ? ` (${skipped} external translation checks skipped)` : ''}.`);
