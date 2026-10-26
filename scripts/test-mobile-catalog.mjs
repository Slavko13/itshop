import { spawn } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const chromeCandidates = [
  process.env.CHROME_PATH,
  'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
  'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
].filter(Boolean);

const chromePath = chromeCandidates.find(existsSync);
if (!chromePath) {
  console.error('FAIL: Google Chrome was not found. Set CHROME_PATH.');
  process.exit(1);
}

const baseUrl = process.env.ITSOFT_BASE_URL || 'http://localhost:8080';
const categoryPath = process.env.ITSOFT_CATEGORY_PATH || '/product-category/microsoft-office/office-2021/';
const testUrl = new URL(categoryPath, baseUrl).toString();
const articlePath = process.env.ITSOFT_ARTICLE_PATH || '/stati/ustanovka-windows-11-bez-interneta-i-akkaunta-microsoft/';
const articleUrl = new URL(articlePath, baseUrl).toString();
const homeUrl = new URL('/', baseUrl).toString();
const accountUrl = new URL('/my-account/', baseUrl).toString();
const profilePath = mkdtempSync(join(tmpdir(), 'itsoft-mobile-catalog-'));
const chrome = spawn(chromePath, [
  '--headless=new',
  '--disable-gpu',
  '--hide-scrollbars',
  '--no-first-run',
  '--no-default-browser-check',
  '--remote-debugging-port=0',
  '--user-agent=Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36 Chrome/140.0.0.0 Mobile Safari/537.36',
  `--user-data-dir=${profilePath}`,
  'about:blank',
], { stdio: 'ignore', windowsHide: true });

const delay = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

async function waitForDevTools() {
  const activePortFile = join(profilePath, 'DevToolsActivePort');
  for (let attempt = 0; attempt < 100; attempt += 1) {
    if (existsSync(activePortFile)) {
      const [port] = readFileSync(activePortFile, 'utf8').trim().split(/\r?\n/);
      if (port) return Number(port);
    }
    if (chrome.exitCode !== null) throw new Error(`Chrome exited with code ${chrome.exitCode}`);
    await delay(100);
  }
  throw new Error('Chrome DevTools endpoint did not become ready.');
}

class CdpClient {
  constructor(url) {
    this.socket = new WebSocket(url);
    this.nextId = 1;
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
        const { resolve, reject } = this.pending.get(message.id);
        this.pending.delete(message.id);
        if (message.error) reject(new Error(message.error.message));
        else resolve(message.result);
        return;
      }
      if (message.method && this.events.has(message.method)) {
        const listeners = this.events.get(message.method).splice(0);
        listeners.forEach((listener) => listener(message.params));
      }
    });
  }

  send(method, params = {}) {
    const id = this.nextId;
    this.nextId += 1;
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

  close() {
    this.socket.close();
  }
}

async function evaluate(client, expression) {
  const response = await client.send('Runtime.evaluate', {
    expression,
    returnByValue: true,
    awaitPromise: true,
  });
  if (response.exceptionDetails) throw new Error(response.exceptionDetails.text);
  return response.result.value;
}

function assert(checks, label, value, details = '') {
  if (value) {
    console.log(`PASS ${label}`);
    return;
  }
  checks.push(label);
  console.error(`FAIL ${label}${details ? `: ${details}` : ''}`);
}

let client;
const failures = [];

try {
  const port = await waitForDevTools();
  const targetResponse = await fetch(`http://127.0.0.1:${port}/json/new?${encodeURIComponent(testUrl)}`, { method: 'PUT' });
  if (!targetResponse.ok) throw new Error(`Could not create Chrome target: HTTP ${targetResponse.status}`);
  const target = await targetResponse.json();
  client = new CdpClient(target.webSocketDebuggerUrl);
  await client.connect();
  await client.send('Page.enable');
  await client.send('Runtime.enable');

  for (const width of [320, 360, 393, 768, 1024]) {
    await client.send('Emulation.setDeviceMetricsOverride', {
      width,
      height: 900,
      deviceScaleFactor: 1,
      mobile: width <= 393,
    });
    const loaded = client.once('Page.loadEventFired');
    await client.send('Page.navigate', { url: testUrl });
    await loaded;
    await delay(250);

    const initial = await evaluate(client, `(() => {
      const root = document.documentElement;
      const burger = document.querySelector('.mobile-nav__burger');
      const description = document.querySelector('.catalog__description--below');
      const grid = document.querySelector('.catalog-grid');
      return {
        status: document.readyState,
        overflow: root.scrollWidth - window.innerWidth,
        burgerTag: burger?.tagName || '',
        burgerVisible: burger ? getComputedStyle(burger).display !== 'none' : false,
        nestedInteractive: Boolean(document.querySelector('a button, button a')),
        descriptionAfterGrid: Boolean(description && grid && (grid.compareDocumentPosition(description) & Node.DOCUMENT_POSITION_FOLLOWING)),
      };
    })()`);

    assert(failures, `${width}px document loaded`, initial.status === 'complete', initial.status);
    assert(failures, `${width}px no horizontal scroll`, initial.overflow <= 1, `${initial.overflow}px overflow`);
    assert(failures, `${width}px burger is a visible button`, initial.burgerTag === 'BUTTON' && initial.burgerVisible);
    assert(failures, `${width}px no nested interactive controls`, !initial.nestedInteractive);
    assert(failures, `${width}px SEO description follows product grid`, initial.descriptionAfterGrid);

    await evaluate(client, `document.querySelector('.mobile-nav__burger').click()`);
    const opened = await evaluate(client, `({
      menu: document.querySelector('.catalog__nav').classList.contains('active'),
      body: document.body.classList.contains('no-scroll'),
      expanded: document.querySelector('.mobile-nav__burger').getAttribute('aria-expanded'),
    })`);
    assert(failures, `${width}px menu opens on first click`, opened.menu && opened.body && opened.expanded === 'true');

    const toggleExists = await evaluate(client, `Boolean(document.querySelector('.catalog-nav__toggle-btn'))`);
    if (toggleExists) {
      const initiallyExpanded = await evaluate(client, `document.querySelector('.catalog-nav__toggle-btn').getAttribute('aria-expanded')`);
      if (initiallyExpanded === 'true') {
        await evaluate(client, `document.querySelector('.catalog-nav__toggle-btn').click()`);
      }
      await evaluate(client, `document.querySelector('.catalog-nav__toggle-btn').click()`);
      const toggleExpanded = await evaluate(client, `document.querySelector('.catalog-nav__toggle-btn').getAttribute('aria-expanded')`);
      assert(failures, `${width}px category toggle expands`, toggleExpanded === 'true');
    }

    await evaluate(client, `document.querySelector('.catalog-nav__mobile-close').click()`);
    const closed = await evaluate(client, `({
      menu: document.querySelector('.catalog__nav').classList.contains('active'),
      body: document.body.classList.contains('no-scroll'),
      expanded: document.querySelector('.mobile-nav__burger').getAttribute('aria-expanded'),
    })`);
    assert(failures, `${width}px close restores page scroll`, !closed.menu && !closed.body && closed.expanded === 'false');
  }

  for (const width of [320, 393, 768, 1024]) {
    await client.send('Emulation.setDeviceMetricsOverride', {
      width,
      height: 900,
      deviceScaleFactor: 1,
      mobile: width <= 393,
    });
    const loaded = client.once('Page.loadEventFired');
    await client.send('Page.navigate', { url: articleUrl });
    await loaded;
    await delay(250);

    const article = await evaluate(client, `(() => {
      const root = document.documentElement;
      const title = document.querySelector('.article-head__title');
      const titleRect = title?.getBoundingClientRect();
      const oversizedImages = [...document.querySelectorAll('.article img')].filter((image) => {
        const rect = image.getBoundingClientRect();
        return rect.left < -1 || rect.right > window.innerWidth + 1;
      }).length;
      const internallyOverflowing = [...document.querySelectorAll('.article-head__title, .article, .article p, .article li, .article strong')].filter((element) => (
        element.scrollWidth > element.clientWidth + 1
      )).length;
      const metaText = document.querySelector('.article-head__meta')?.textContent || '';
      return {
        overflow: root.scrollWidth - window.innerWidth,
        oversizedImages,
        internallyOverflowing,
        titleInsideViewport: Boolean(titleRect && titleRect.left >= -1 && titleRect.right <= window.innerWidth + 1),
        hasFakeCounters: metaText.includes('1 245') || /(?:^|\\s)10(?:\\s|$)/.test(metaText),
      };
    })()`);

    assert(failures, `${width}px article has no horizontal scroll`, article.overflow <= 1, `${article.overflow}px overflow`);
    assert(failures, `${width}px article images fit viewport`, article.oversizedImages === 0, `${article.oversizedImages} oversized image(s)`);
    assert(failures, `${width}px article title fits viewport`, article.titleInsideViewport);
    assert(failures, `${width}px article text does not clip`, article.internallyOverflowing === 0, `${article.internallyOverflowing} overflowing element(s)`);
    assert(failures, `${width}px article has no hardcoded counters`, !article.hasFakeCounters);
  }

  for (const width of [320, 393, 768]) {
    await client.send('Emulation.setDeviceMetricsOverride', {
      width,
      height: 900,
      deviceScaleFactor: 1,
      mobile: width <= 393,
    });
    const loaded = client.once('Page.loadEventFired');
    await client.send('Page.navigate', { url: accountUrl });
    await loaded;
    await delay(250);

    const account = await evaluate(client, `(() => {
      const fields = [...document.querySelectorAll('.woocommerce-account form input, .woocommerce-account form button')];
      const outside = fields.filter((field) => {
        const rect = field.getBoundingClientRect();
        return rect.left < -1 || rect.right > window.innerWidth + 1;
      }).length;
      return {
        overflow: document.documentElement.scrollWidth - window.innerWidth,
        login: Boolean(document.querySelector('form.woocommerce-form-login')),
        registration: Boolean(document.querySelector('form.woocommerce-form-register')),
        resetLink: Boolean(document.querySelector('.woocommerce-LostPassword a')),
        outside,
      };
    })()`);
    assert(failures, `${width}px account has no horizontal scroll`, account.overflow <= 1, `${account.overflow}px overflow`);
    assert(failures, `${width}px login form is available`, account.login);
    assert(failures, `${width}px registration form is available`, account.registration);
    assert(failures, `${width}px password reset link is available`, account.resetLink);
    assert(failures, `${width}px account controls fit viewport`, account.outside === 0, `${account.outside} outside control(s)`);
  }

  await client.send('Emulation.setDeviceMetricsOverride', {
    width: 393,
    height: 873,
    deviceScaleFactor: 1,
    mobile: true,
  });
  const homeLoaded = client.once('Page.loadEventFired');
  await client.send('Page.navigate', { url: homeUrl });
  await homeLoaded;
  await delay(500);

  const cookieBanner = await evaluate(client, `(() => {
    const banner = document.querySelector('#cookie-notice');
    const accept = document.querySelector('#cn-accept-cookie');
    const privacy = document.querySelector('#cn-more-info');
    const acceptRect = accept?.getBoundingClientRect();
    const privacyRect = privacy?.getBoundingClientRect();
    return {
      exists: Boolean(banner),
      visible: Boolean(banner && getComputedStyle(banner).display !== 'none' && banner.getBoundingClientRect().height > 0),
      acceptInside: Boolean(acceptRect && acceptRect.left >= 0 && acceptRect.right <= window.innerWidth && acceptRect.bottom <= window.innerHeight),
      privacyInside: Boolean(privacyRect && privacyRect.left >= 0 && privacyRect.right <= window.innerWidth && privacyRect.bottom <= window.innerHeight),
    };
  })()`);
  assert(failures, '393px cookie banner is rendered and visible', cookieBanner.exists && cookieBanner.visible);
  assert(failures, '393px cookie actions stay inside viewport', cookieBanner.acceptInside && cookieBanner.privacyInside);

  await evaluate(client, `document.querySelector('#cn-accept-cookie').click()`);
  await delay(1200);
  const cookieAccepted = await evaluate(client, `({
    stored: document.cookie.includes('cookie_notice_accepted=true'),
    cookie: document.cookie,
    className: document.querySelector('#cookie-notice').className,
    display: getComputedStyle(document.querySelector('#cookie-notice')).display,
    visibility: getComputedStyle(document.querySelector('#cookie-notice')).visibility,
    opacity: getComputedStyle(document.querySelector('#cookie-notice')).opacity,
    hidden: document.querySelector('#cookie-notice').getBoundingClientRect().height === 0
      || getComputedStyle(document.querySelector('#cookie-notice')).display === 'none'
      || getComputedStyle(document.querySelector('#cookie-notice')).visibility === 'hidden'
      || getComputedStyle(document.querySelector('#cookie-notice')).opacity === '0',
  })`);
  assert(
    failures,
    'cookie consent can be accepted',
    cookieAccepted.stored && cookieAccepted.hidden,
    JSON.stringify(cookieAccepted),
  );
} catch (error) {
  failures.push('browser harness');
  console.error(`FAIL browser harness: ${error.message}`);
} finally {
  if (client) client.close();
  if (chrome.exitCode === null) chrome.kill();
  await delay(250);
  rmSync(profilePath, { recursive: true, force: true });
}

if (failures.length) {
  console.error(`\n${failures.length} check(s) failed.`);
  process.exit(1);
}

console.log('\nAll mobile catalog and article checks passed.');
