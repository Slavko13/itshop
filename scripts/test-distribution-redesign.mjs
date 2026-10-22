import { spawn } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const baseUrl = process.env.ITSOFT_BASE_URL || 'http://localhost:8080';
const artifactDir = join(process.cwd(), 'artifacts', 'distribution-redesign');
const profileDir = mkdtempSync(join(tmpdir(), 'itsoft-distribution-'));
const failures = [];

if (!existsSync(chromePath)) throw new Error(`Chrome not found: ${chromePath}`);
mkdirSync(artifactDir, { recursive: true });

const chrome = spawn(chromePath, [
    '--headless=new', '--disable-gpu', '--no-sandbox', '--hide-scrollbars', '--no-first-run',
    '--no-default-browser-check', '--remote-debugging-port=0', `--user-data-dir=${profileDir}`, 'about:blank',
], { stdio: 'ignore', windowsHide: true });

const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function waitForPort() {
    const portFile = join(profileDir, 'DevToolsActivePort');
    for (let i = 0; i < 120; i += 1) {
        if (existsSync(portFile)) return Number(readFileSync(portFile, 'utf8').trim().split(/\r?\n/)[0]);
        if (chrome.exitCode !== null) throw new Error(`Chrome exited with ${chrome.exitCode}`);
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
                this.events.get(message.method).splice(0).forEach((handler) => handler(message.params));
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
    close() { this.socket.close(); }
}

function assert(label, result, details = '') {
    if (result) {
        console.log(`PASS ${label}`);
    } else {
        failures.push(label);
        console.error(`FAIL ${label}${details ? `: ${details}` : ''}`);
    }
}

let client;

try {
    const port = await waitForPort();
    const targetResponse = await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' });
    const target = await targetResponse.json();
    client = new Client(target.webSocketDebuggerUrl);
    await client.connect();
    await client.send('Page.enable');
    await client.send('Runtime.enable');
    await client.send('Network.enable');
    await client.send('Network.setCacheDisabled', { cacheDisabled: true });

    const evaluate = async (expression) => {
        const result = await client.send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (result.exceptionDetails) throw new Error(result.exceptionDetails.text || 'Browser evaluation failed.');
        return result.result.value;
    };

    const navigate = async (path, width, height = 1000) => {
        await client.send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 600 });
        const loaded = client.once('Page.loadEventFired');
        await client.send('Page.navigate', { url: `${baseUrl}${path}` });
        await loaded;
        await delay(650);
        return evaluate(`(() => ({
            statusTitle: document.title,
            h1: document.querySelectorAll('main h1').length,
            style: Boolean(document.querySelector('#itsoft-distribution-redesign-css')),
            legacyHero: Boolean(document.querySelector('.hero-small')),
            overflow: Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth),
            mainTop: Math.round(document.querySelector('main')?.getBoundingClientRect().top || 0),
            headerBottom: Math.round(document.querySelector('.header')?.getBoundingClientRect().bottom || 0),
            cards: document.querySelectorAll('[data-distribution-card]').length,
            nestedInteractive: document.querySelectorAll('a a, a button, button a').length,
            bodyText: document.body.innerText.slice(0, 800),
        }))()`);
    };

    const capture = async (name) => {
        const metrics = await client.send('Page.getLayoutMetrics');
        const width = Math.ceil(metrics.cssContentSize.width);
        const height = Math.min(5000, Math.ceil(metrics.cssContentSize.height));
        const result = await client.send('Page.captureScreenshot', {
            format: 'png', fromSurface: true, captureBeyondViewport: true,
            clip: { x: 0, y: 0, width, height, scale: 1 },
        });
        writeFileSync(join(artifactDir, `${name}.png`), Buffer.from(result.data, 'base64'));
    };

    for (const width of [320, 393, 768, 1280, 1440]) {
        const state = await navigate('/download/', width);
        assert(`hub ${width}px has one H1`, state.h1 === 1, JSON.stringify(state));
        assert(`hub ${width}px has no overflow`, state.overflow <= 1, JSON.stringify(state));
        assert(`hub ${width}px loads redesign`, state.style && !state.legacyHero, JSON.stringify(state));
        assert(`hub ${width}px content follows header`, state.mainTop - state.headerBottom < 40, JSON.stringify(state));
        if (width === 393 || width === 1280) await capture(`hub-${width}`);
    }

    for (const width of [393, 1280]) {
        const state = await navigate('/download/microsoft-office/', width);
        assert(`taxonomy ${width}px renders cards`, state.cards > 0, JSON.stringify(state));
        assert(`taxonomy ${width}px has no overflow`, state.overflow <= 1, JSON.stringify(state));
        assert(`taxonomy ${width}px semantic links`, state.nestedInteractive === 0, JSON.stringify(state));

        const filtering = await evaluate(`(async () => {
            const input = document.querySelector('[data-distribution-search]');
            input.value = '__not_a_distribution__';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            await new Promise((resolve) => setTimeout(resolve, 30));
            const noneVisible = [...document.querySelectorAll('[data-distribution-card]')].every((card) => card.hidden);
            const emptyVisible = !document.querySelector('[data-distribution-empty]')?.hidden;
            input.value = '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            return { noneVisible, emptyVisible, restored: [...document.querySelectorAll('[data-distribution-card]')].some((card) => !card.hidden) };
        })()`);
        assert(`taxonomy ${width}px search empty/reset`, filtering.noneVisible && filtering.emptyVisible && filtering.restored, JSON.stringify(filtering));
        await capture(`taxonomy-${width}`);
    }

    for (const width of [320, 393, 768, 1280, 1440]) {
        const state = await navigate('/distribution/microsoft-visio-2021-professional/', width);
        const detail = await evaluate(`(() => ({
            files: document.querySelectorAll('.distribution-file').length,
            instructions: Boolean(document.querySelector('.distribution-instructions')),
            aside: Boolean(document.querySelector('.distribution-single__aside')),
            externalExposed: [...document.querySelectorAll('.distribution-file__download')].some((a) => !a.href.includes('/get-distribution/')),
        }))()`);
        assert(`single ${width}px has no overflow`, state.overflow <= 1, JSON.stringify(state));
        assert(`single ${width}px has file and instructions`, detail.files > 0 && detail.instructions && detail.aside, JSON.stringify(detail));
        assert(`single ${width}px hides final URLs`, !detail.externalExposed, JSON.stringify(detail));
        if (width === 393 || width === 1280) await capture(`single-${width}`);
    }

    if (failures.length) throw new Error(`${failures.length} distribution checks failed: ${failures.join(', ')}`);
    console.log('All distribution responsive checks passed.');
} finally {
    try { await client?.send('Browser.close'); } catch { chrome.kill(); }
    client?.close();
    await delay(400);
    rmSync(profileDir, { recursive: true, force: true });
}
