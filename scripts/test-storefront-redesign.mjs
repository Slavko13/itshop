import { spawn } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const baseUrl = process.env.ITSOFT_BASE_URL || 'http://localhost:8080';
const categoryPath = process.env.ITSOFT_CATEGORY_PATH || '/product-category/microsoft-office/office-2021/';
const pagedCategoryPath = process.env.ITSOFT_PAGED_CATEGORY_PATH || '/product-category/microsoft-windows/windows-11/page/1/';
const artifactDir = join(process.cwd(), 'artifacts', 'storefront-redesign');
const profileDir = mkdtempSync(join(tmpdir(), 'itsoft-storefront-'));
const failures = [];

if (!existsSync(chromePath)) {
	throw new Error(`Chrome not found: ${chromePath}`);
}

mkdirSync(artifactDir, { recursive: true });

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

const delay = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

async function waitForPort() {
	const portFile = join(profileDir, 'DevToolsActivePort');
	for (let attempt = 0; attempt < 120; attempt += 1) {
		if (existsSync(portFile)) {
			const [port] = readFileSync(portFile, 'utf8').trim().split(/\r?\n/);
			if (port) return Number(port);
		}
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
				return;
			}
			if (message.method && this.events.has(message.method)) {
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

	close() {
		this.socket.close();
	}
}

function assert(label, result, details = '') {
	if (result) {
		console.log(`PASS ${label}`);
		return;
	}
	failures.push(label);
	console.error(`FAIL ${label}${details ? `: ${details}` : ''}`);
}

let client;

try {
	const port = await waitForPort();
	const response = await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' });
	const target = await response.json();
	client = new Client(target.webSocketDebuggerUrl);
	await client.connect();
	await client.send('Page.enable');
	await client.send('Runtime.enable');

	const evaluate = async (expression) => {
		const result = await client.send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
		if (result.exceptionDetails) throw new Error(result.exceptionDetails.text || 'Browser evaluation failed');
		return result.result.value;
	};

	const navigate = async (url, width, height = 1000) => {
		await client.send('Emulation.setDeviceMetricsOverride', {
			width,
			height,
			deviceScaleFactor: 1,
			mobile: width <= 575,
		});
		const loaded = client.once('Page.loadEventFired');
		await client.send('Page.navigate', { url });
		await loaded;
		await delay(900);
	};

	const capture = async (name) => {
		const result = await client.send('Page.captureScreenshot', {
			format: 'png',
			captureBeyondViewport: true,
			fromSurface: true,
		});
		writeFileSync(join(artifactDir, name), Buffer.from(result.data, 'base64'));
	};

	for (const width of [320, 393, 768, 1280, 1440]) {
		await navigate(new URL('/shop/', baseUrl).toString(), width);
		const state = await evaluate(`(() => {
			const grid = document.querySelector('.catalog-grid__list');
			const cards = [...document.querySelectorAll('.catalog-card')];
			const toggle = document.querySelector('.catalog-sidebar__toggle');
			const filters = document.querySelector('.catalog__filters')?.getBoundingClientRect();
			const sidebarBody = document.querySelector('.catalog-sidebar__body')?.getBoundingClientRect();
			const header = document.querySelector('.header')?.getBoundingClientRect();
			const breadcrumbList = document.querySelector('.catalog__breadcrumbs .breadcrumbs__list')?.getBoundingClientRect();
			const rightmost = Math.max(0, ...cards.map((card) => card.getBoundingClientRect().right));
			const rowHeights = cards.reduce((rows, card) => {
				const rect = card.getBoundingClientRect();
				const row = Math.round(rect.top);
				rows[row] = rows[row] || [];
				rows[row].push(rect.height);
				return rows;
			}, {});
			const rowHeightSpreads = Object.values(rowHeights)
				.filter((heights) => heights.length > 1)
				.map((heights) => Math.max(...heights) - Math.min(...heights));
			const buttonLabelOffsets = cards.map((card) => {
				const button = card.querySelector('.catalog-card__buy');
				const label = button?.querySelector('.catalog-card__buy-label');
				if (!button || !label) return 0;
				const buttonRect = button.getBoundingClientRect();
				const labelRect = label.getBoundingClientRect();
				return Math.abs((buttonRect.top + buttonRect.bottom) / 2 - (labelRect.top + labelRect.bottom) / 2);
			});
			return {
				title: document.querySelector('.catalog__title')?.textContent.trim() || '',
				style: Boolean(document.querySelector('link[href*="storefront-redesign.css"]')),
				overflow: document.documentElement.scrollWidth - window.innerWidth,
				cards: cards.length,
				buttons: document.querySelectorAll('.catalog-card__buy').length,
				columns: grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length : 0,
				rightmost,
				maxRowHeightSpread: Math.max(0, ...rowHeightSpreads),
				maxButtonLabelOffset: Math.max(0, ...buttonLabelOffsets),
				desktopColumnTopOffset: filters && sidebarBody ? Math.abs(filters.top - sidebarBody.top) : 0,
				headerToBreadcrumbGap: header && breadcrumbList ? breadcrumbList.top - header.bottom : 0,
				hasLegacyInnerHero: Boolean(document.querySelector('.header .hero-small')),
				toggleVisible: Boolean(toggle && getComputedStyle(toggle).display !== 'none'),
				nestedInteractive: Boolean(document.querySelector('a button, button a')),
			};
		})()`);

		const expectedColumns = width <= 340 ? 1 : width < 860 ? 2 : 3;
		assert(`${width}px catalog title`, ['Каталог', 'Catalog'].includes(state.title), state.title);
		assert(`${width}px redesign stylesheet`, state.style);
		assert(`${width}px no horizontal overflow`, state.overflow <= 1, `${state.overflow}px`);
		assert(`${width}px product cards render`, state.cards > 0, String(state.cards));
		assert(`${width}px every card has an action`, state.buttons === state.cards, `${state.buttons}/${state.cards}`);
		assert(`${width}px expected grid columns`, state.columns === expectedColumns, `${state.columns}, expected ${expectedColumns}`);
		assert(`${width}px cards fit viewport`, state.rightmost <= width + 1, `${state.rightmost}px`);
		assert(`${width}px cards have equal height within rows`, state.maxRowHeightSpread <= 1, `${state.maxRowHeightSpread}px`);
		assert(`${width}px action labels are vertically centered`, state.maxButtonLabelOffset <= 1, `${state.maxButtonLabelOffset}px`);
		assert(`${width}px category toggle visibility`, state.toggleVisible === (width < 860));
		assert(`${width}px no nested interactive elements`, !state.nestedInteractive);
		assert(`${width}px catalog does not render legacy inner hero`, !state.hasLegacyInnerHero);
		assert(`${width}px compact header-to-content gap`, state.headerToBreadcrumbGap <= 24, `${state.headerToBreadcrumbGap}px`);
		if (width >= 1280) {
			assert(`${width}px search aligns with category panel`, state.desktopColumnTopOffset <= 1, `${state.desktopColumnTopOffset}px`);
		}

		if (width >= 1280) {
			const hoverTarget = await evaluate(`(() => {
				const card = document.querySelector('.catalog-card');
				const rect = card.getBoundingClientRect();
				return { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2, top: rect.top, left: rect.left };
			})()`);
			await client.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: hoverTarget.x, y: hoverTarget.y });
			await delay(300);
			const hoveredPosition = await evaluate(`(() => {
				const rect = document.querySelector('.catalog-card').getBoundingClientRect();
				return { top: rect.top, left: rect.left };
			})()`);
			assert(`${width}px card hover does not shift layout`, Math.abs(hoveredPosition.top - hoverTarget.top) <= 1 && Math.abs(hoveredPosition.left - hoverTarget.left) <= 1, JSON.stringify({ before: hoverTarget, after: hoveredPosition }));
			await client.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 0, y: 0 });
		}

		if (width < 860) {
			await evaluate(`document.querySelector('.catalog-sidebar__toggle').click()`);
			await delay(350);
			const opened = await evaluate(`(() => {
				const sidebar = document.querySelector('[data-catalog-sidebar]');
				const body = sidebar.querySelector('.catalog-sidebar__body');
				return {
					open: sidebar.classList.contains('catalog-sidebar--open'),
					expanded: sidebar.querySelector('.catalog-sidebar__toggle').getAttribute('aria-expanded'),
					height: body.getBoundingClientRect().height,
				};
			})()`);
			assert(`${width}px category accordion opens`, opened.open && opened.expanded === 'true' && opened.height > 40, JSON.stringify(opened));
		}

		if (width === 393) await capture('catalog-393.png');
		if (width === 1280) await capture('catalog-1280.png');
	}

	await navigate(new URL(categoryPath, baseUrl).toString(), 393);
	const category = await evaluate(`(() => {
		const description = document.querySelector('.catalog__description--below');
		const grid = document.querySelector('.catalog-grid');
		return {
			title: document.querySelector('.catalog__title')?.textContent.trim() || '',
			descriptionAfterGrid: Boolean(description && grid && (grid.compareDocumentPosition(description) & Node.DOCUMENT_POSITION_FOLLOWING)),
			overflow: document.documentElement.scrollWidth - window.innerWidth,
		};
	})()`);
	assert('category has its own H1', category.title && category.title !== 'Каталог', category.title);
	assert('category SEO content follows products', category.descriptionAfterGrid);
	assert('category page has no horizontal overflow', category.overflow <= 1, `${category.overflow}px`);

	await navigate(new URL(pagedCategoryPath, baseUrl).toString(), 1440);
	const pagedCategory = await evaluate(`(() => {
		const breadcrumbList = document.querySelector('.catalog__breadcrumbs .breadcrumbs__list');
		const breadcrumbItems = [...(breadcrumbList?.children || [])];
		const title = document.querySelector('.catalog__title');
		const hero = document.querySelector('.catalog__hero')?.getBoundingClientRect();
		const layout = document.querySelector('.catalog__layout')?.getBoundingClientRect();
		return {
			items: breadcrumbItems.map((item) => item.textContent.trim()),
			allItemsAreListItems: breadcrumbItems.every((item) => item.tagName === 'LI'),
			titleFontSize: title ? parseFloat(getComputedStyle(title).fontSize) : 0,
			heroToLayoutGap: hero && layout ? layout.top - hero.bottom : 0,
			overflow: document.documentElement.scrollWidth - window.innerWidth,
		};
	})()`);
	assert(
		'paged category breadcrumb uses a separate item',
		pagedCategory.allItemsAreListItems && ['Страница 1', 'Page 1'].includes(pagedCategory.items.at(-1)),
		pagedCategory.items.join(' / '),
	);
	assert('desktop category title stays compact', pagedCategory.titleFontSize <= 52, `${pagedCategory.titleFontSize}px`);
	assert('desktop category hero gap stays compact', pagedCategory.heroToLayoutGap <= 30, `${pagedCategory.heroToLayoutGap}px`);
	assert('paged category has no horizontal overflow', pagedCategory.overflow <= 1, `${pagedCategory.overflow}px`);
	await capture('category-windows11-page1-1440.png');

	await navigate(new URL('/shop/', baseUrl).toString(), 1280);
	const catalogMenu = await evaluate(`(() => {
		document.querySelector('.catalog-btn')?.click();
		const menu = document.querySelector('.catalog__nav');
		const container = document.querySelector('.header-main__row.grid-container');
		const menuRect = menu?.getBoundingClientRect();
		const containerRect = container?.getBoundingClientRect();
		return {
			open: Boolean(menu?.classList.contains('active')),
			expanded: document.querySelector('.catalog-btn')?.getAttribute('aria-expanded'),
			left: menuRect?.left || 0,
			right: menuRect?.right || 0,
			containerLeft: containerRect?.left || 0,
			containerRight: containerRect?.right || 0,
		};
	})()`);
	assert('desktop catalog menu opens inside header container', catalogMenu.open && catalogMenu.expanded === 'true' && catalogMenu.left >= catalogMenu.containerLeft - 1 && catalogMenu.right <= catalogMenu.containerRight + 1, JSON.stringify(catalogMenu));
	await capture('catalog-menu-1280.png');
	await evaluate(`document.querySelector('.catalog-btn')?.click()`);

	const ajaxButtonExists = await evaluate(`Boolean(document.querySelector('.catalog-card__buy.ajax_add_to_cart'))`);
	assert('simple product has AJAX add button', ajaxButtonExists);
	if (ajaxButtonExists) {
		await evaluate(`document.querySelector('.catalog-card__buy.ajax_add_to_cart').click()`);
		await delay(1800);
		const added = await evaluate(`(() => {
			const button = document.querySelector('.catalog-card__buy.ajax_add_to_cart');
			return {
				added: button.classList.contains('added') || button.classList.contains('catalog-card__buy--added'),
				label: button.querySelector('.catalog-card__buy-label')?.textContent.trim() || '',
			};
		})()`);
		assert('AJAX add-to-cart completes', added.added && added.label === 'В корзине', JSON.stringify(added));
	}

	for (const width of [393, 768, 1280, 1440]) {
		await navigate(new URL('/', baseUrl).toString(), width, 1100);
		await evaluate(`(() => {
			const accept = document.querySelector('#cn-accept-cookie');
			if (accept && getComputedStyle(accept).display !== 'none') accept.click();
		})()`);
		await delay(300);

		const home = await evaluate(`(() => {
			const categoryGrid = document.querySelector('.shop-cats__list');
			const popular = [...document.querySelectorAll('.popular-products:not(.recommend-products) .catalog-card')];
			const recommended = [...document.querySelectorAll('.recommend-products .catalog-card')];
			const popularIds = popular.map((card) => card.dataset.productId).filter(Boolean);
			const recommendedIds = recommended.map((card) => card.dataset.productId).filter(Boolean);
			return {
				overflow: document.documentElement.scrollWidth - window.innerWidth,
				h1: document.querySelectorAll('h1').length,
				nativeHero: Boolean(document.querySelector('.home-hero__title')),
				sliderHero: Boolean(document.querySelector('.hero__container--smartslider .n2-ss-slider')),
				heroTitle: document.querySelector('.home-hero__title')?.textContent.trim() || '',
				actions: document.querySelectorAll('.home-hero__actions a').length,
				benefits: document.querySelectorAll('.home-benefits__item').length,
				categories: document.querySelectorAll('.shop-cats__item').length,
				categoryColumns: categoryGrid ? getComputedStyle(categoryGrid).gridTemplateColumns.split(' ').filter(Boolean).length : 0,
				popular: popular.length,
				recommended: recommended.length,
				duplicates: recommendedIds.filter((id) => popularIds.includes(id)),
				productActions: [...popular, ...recommended].filter((card) => card.querySelector('.catalog-card__buy')).length,
				articleSection: Boolean(document.querySelector('.useful-articles')),
			};
		})()`);

		assert(`${width}px home has no horizontal overflow`, home.overflow <= 1, `${home.overflow}px`);
		assert(`${width}px home has one H1`, home.h1 === 1, String(home.h1));
		assert(`${width}px configured hero renders`, home.sliderHero || (home.nativeHero && home.heroTitle.length > 10), home.heroTitle);
		if (home.nativeHero) {
			assert(`${width}px hero actions render`, home.actions === 2, String(home.actions));
			assert(`${width}px trust benefits render`, home.benefits === 3, String(home.benefits));
		}
		assert(`${width}px selected categories render`, home.categories > 0, String(home.categories));
		assert(`${width}px category grid columns`, home.categoryColumns === (width < 860 ? 2 : 3), String(home.categoryColumns));
		assert(`${width}px commerce sections render`, home.popular > 0 && home.recommended > 0, `${home.popular}/${home.recommended}`);
		assert(`${width}px home products do not repeat`, home.duplicates.length === 0, home.duplicates.join(','));
		assert(`${width}px every home product has an action`, home.productActions === home.popular + home.recommended, `${home.productActions}/${home.popular + home.recommended}`);
		assert(`${width}px article section renders`, home.articleSection);

		if (width === 393) await capture('home-393.png');
		if (width === 1280) await capture('home-1280.png');
	}

	for (const width of [393, 1280]) {
		await navigate(new URL('/?s=', baseUrl).toString(), width);
		const emptySearch = await evaluate(`(() => {
			const head = document.querySelector('.itsoft-search__head')?.getBoundingClientRect();
			const form = document.querySelector('.itsoft-search__form');
			const input = form?.querySelector('[name="s"]');
			if (input) input.value = '   ';
			const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
			const submitAllowed = form ? form.dispatchEvent(submitEvent) : true;
			return {
				title: document.querySelector('.itsoft-search__title')?.textContent.trim() || '',
				empty: Boolean(document.querySelector('.itsoft-search__empty')),
				cards: document.querySelectorAll('.catalog-card').length,
				articles: document.querySelectorAll('.itsoft-search-article').length,
				overflow: document.documentElement.scrollWidth - window.innerWidth,
				headWidth: head?.width || 0,
				submitPrevented: !submitAllowed,
				validationMessage: input?.validationMessage || '',
			};
		})()`);
		assert(
			`${width}px empty search renders intentional state`,
			emptySearch.empty && ['Поиск по каталогу', 'Search the catalog'].includes(emptySearch.title),
			JSON.stringify(emptySearch),
		);
		assert(`${width}px empty search returns no content cards`, emptySearch.cards === 0 && emptySearch.articles === 0, JSON.stringify(emptySearch));
		assert(`${width}px empty search spans the content container`, emptySearch.headWidth >= width - (width < 600 ? 42 : 210), `${emptySearch.headWidth}px`);
		assert(`${width}px empty search has no horizontal overflow`, emptySearch.overflow <= 1, `${emptySearch.overflow}px`);
		assert(`${width}px whitespace-only search is blocked`, emptySearch.submitPrevented && emptySearch.validationMessage.length > 0, JSON.stringify(emptySearch));
	}

	await navigate(new URL('/?s=Windows%2011', baseUrl).toString(), 1280);
	const populatedSearch = await evaluate(`(() => {
		const grid = document.querySelector('.itsoft-search__grid');
		const cards = [...document.querySelectorAll('.catalog-card')];
		return {
			storefrontStyles: Boolean(document.querySelector('link[href*="storefront-redesign.css"]')),
			searchStyles: Boolean(document.querySelector('link[href*="search.css"]')),
			cards: cards.length,
			columns: grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length : 0,
			minCardWidth: Math.min(...cards.map((card) => card.getBoundingClientRect().width)),
			overflow: document.documentElement.scrollWidth - window.innerWidth,
		};
	})()`);
	assert('search loads storefront and search styles', populatedSearch.storefrontStyles && populatedSearch.searchStyles, JSON.stringify(populatedSearch));
	assert('search renders full-size product cards', populatedSearch.cards > 0 && populatedSearch.columns === 3 && populatedSearch.minCardWidth > 250, JSON.stringify(populatedSearch));
	assert('populated search has no horizontal overflow', populatedSearch.overflow <= 1, `${populatedSearch.overflow}px`);

	await client.send('Emulation.setScriptExecutionDisabled', { value: true });
	await navigate(new URL('/shop/', baseUrl).toString(), 393);
	const noScriptCatalog = await evaluate(`(() => {
		const toggle = document.querySelector('.catalog-sidebar__toggle');
		const body = document.querySelector('.catalog-sidebar__body');
		return {
			jsClass: document.documentElement.classList.contains('js'),
			toggleVisible: Boolean(toggle && getComputedStyle(toggle).display !== 'none'),
			bodyHeight: body?.getBoundingClientRect().height || 0,
			links: body?.querySelectorAll('a').length || 0,
			overflow: document.documentElement.scrollWidth - window.innerWidth,
		};
	})()`);
	assert('catalog categories remain available without JavaScript', !noScriptCatalog.jsClass && !noScriptCatalog.toggleVisible && noScriptCatalog.bodyHeight > 80 && noScriptCatalog.links > 0, JSON.stringify(noScriptCatalog));
	assert('no-JavaScript catalog has no horizontal overflow', noScriptCatalog.overflow <= 1, `${noScriptCatalog.overflow}px`);
	await client.send('Emulation.setScriptExecutionDisabled', { value: false });
} catch (error) {
	failures.push('browser harness');
	console.error(`FAIL browser harness: ${error.stack || error.message}`);
} finally {
	if (client) client.close();
	if (chrome.exitCode === null) chrome.kill();
	await delay(250);
	rmSync(profileDir, { recursive: true, force: true });
}

if (failures.length) {
	console.error(`\n${failures.length} storefront check(s) failed.`);
	process.exit(1);
}

console.log('\nAll storefront redesign checks passed.');

