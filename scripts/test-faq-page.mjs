import { spawn } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync } from 'node:fs';
import { rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const baseUrl = process.env.ITSOFT_BASE_URL || 'http://localhost:8080';
const faqUrl = new URL('/faq/?faq_style_test=1', baseUrl).toString();
const profileDir = mkdtempSync(join(tmpdir(), 'itsoft-faq-page-'));
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
	await command('Page.navigate', { url: faqUrl });
	await loaded;
	await delay(900);

	await command('Runtime.evaluate', {
		expression: `document.querySelectorAll('[data-faq-trigger]')[1]?.click(); true`,
		returnByValue: true,
	});
	await delay(450);

	const response = await command('Runtime.evaluate', {
		expression: `(() => {
			const items = [...document.querySelectorAll('[data-faq-item]')];
			const active = items[1];
			const question = active?.querySelector('[data-faq-trigger]');
			const answer = active?.querySelector('.product-extra__faq-answer-inner');
			const list = document.querySelector('.product-extra__faq-list');
			return {
				stylesheet: Boolean(document.querySelector('link[href*="page-faq.css"]')),
				itemCount: items.length,
				onlySecondOpen: items.filter((item) => item.classList.contains('active')).length === 1 && active?.classList.contains('active'),
				firstCollapsed: items[0]?.querySelector('[data-faq-trigger]')?.getAttribute('aria-expanded') === 'false',
				secondExpanded: question?.getAttribute('aria-expanded') === 'true',
				activeColor: question ? getComputedStyle(question).backgroundColor : '',
				answerColor: answer ? getComputedStyle(answer).backgroundColor : '',
				listGap: list ? getComputedStyle(list).gap : '',
				noOverflow: document.documentElement.scrollWidth <= window.innerWidth + 1,
			};
		})()`,
		returnByValue: true,
	});
	const result = response.result.value;
	console.log(JSON.stringify(result, null, 2));
	if (!result?.stylesheet || result.itemCount < 2 || !result.onlySecondOpen || !result.firstCollapsed
		|| !result.secondExpanded || result.activeColor !== 'rgb(101, 66, 245)'
		|| result.answerColor !== 'rgb(247, 248, 252)' || result.listGap !== '8px' || !result.noOverflow) {
		exitCode = 1;
	}
} finally {
	try { await command('Browser.close'); } catch { chrome.kill(); }
	socket.close();
	await delay(300);
	await rm(profileDir, { recursive: true, force: true });
}

process.exitCode = exitCode;
