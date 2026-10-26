import { spawn } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const baseUrl = process.env.ITSOFT_BASE_URL || 'http://localhost:8080';
const categoryPath = process.env.ITSOFT_CATEGORY_PATH || '/product-category/microsoft-office/office-2021/';
const pagedCategoryPath = process.env.ITSOFT_PAGED_CATEGORY_PATH || '/product-category/microsoft-windows/windows-11/page/1/';
const productTagPath = process.env.ITSOFT_PRODUCT_TAG_PATH || '/product-tag/outlook/';
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

	for (const width of [375, 768, 1024, 1440]) {
		await navigate(new URL('/shop/', baseUrl).toString(), width);
		const state = await evaluate(`(() => {
			const grid = document.querySelector('.catalog-grid__list');
			const cards = [...document.querySelectorAll('.catalog-card')];
			const toggle = document.querySelector('.catalog-sidebar__toggle');
			const filters = document.querySelector('.catalog__filters')?.getBoundingClientRect();
			const sidebarBody = document.querySelector('.catalog-sidebar__body')?.getBoundingClientRect();
			const header = document.querySelector('.header')?.getBoundingClientRect();
			const headerMain = document.querySelector('.header-main');
			const headerRow = document.querySelector('.header-main__row');
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
			const buttonHorizontalOffsets = cards.map((card) => {
				const button = card.querySelector('.catalog-card__buy');
				const label = button?.querySelector('.catalog-card__buy-label');
				if (!button || !label) return 0;
				const buttonRect = button.getBoundingClientRect();
				const labelRect = label.getBoundingClientRect();
				return Math.abs((buttonRect.left + buttonRect.right) / 2 - (labelRect.left + labelRect.right) / 2);
			});
			const firstCard = cards[0];
			const firstImage = firstCard?.querySelector('.catalog-card__image-link')?.getBoundingClientRect();
			const firstBadge = firstCard?.querySelector('.catalog-card__badge');
			const firstButton = firstCard?.querySelector('.catalog-card__buy');
			const firstTitle = firstCard?.querySelector('.catalog-card__link');
			const catalogTitle = document.querySelector('.catalog__title');
			const catalogLead = document.querySelector('.catalog__lead');
			const catalogSearch = document.querySelector('.catalog-filters__search');
			const catalogSearchInput = document.querySelector('.catalog-filters__input');
			const catalogSearchIcon = document.querySelector('.catalog-filters__icon');
			const catalogSort = document.querySelector('.catalog-filters__ordering');
			const catalogSortButton = catalogSort?.querySelector('.catalog-sort__button');
			const nativeSort = catalogSort?.querySelector('select.orderby');
			const pageContainer = document.querySelector('.catalog__shell');
			const userIconTops = [...document.querySelectorAll('.header-main__user .user-nav__icon')]
				.filter((icon) => icon.getBoundingClientRect().width > 0 && getComputedStyle(icon).visibility !== 'hidden')
				.map((icon) => Math.round(icon.getBoundingClientRect().top));
			const headerActionCenters = [...document.querySelectorAll('.search__mobile-button, .header-main__user .user-nav__item')]
				.filter((item) => item.getBoundingClientRect().width > 0 && getComputedStyle(item).display !== 'none')
				.map((item) => {
					const rect = item.getBoundingClientRect();
					return rect.top + rect.height / 2;
				});
			return {
				title: document.querySelector('.catalog__title')?.textContent.trim() || '',
				style: Boolean(document.querySelector('link[href*="storefront-redesign.css"]')),
				overflow: document.documentElement.scrollWidth - window.innerWidth,
				cards: cards.length,
				minCardWidth: Math.min(...cards.map((card) => card.getBoundingClientRect().width)),
				buttons: document.querySelectorAll('.catalog-card__buy').length,
				availableCards: cards.filter((card) => !card.querySelector('.catalog-card__stock-note')).length,
				unavailableCards: cards.filter((card) => card.querySelector('.catalog-card__stock-note')).length,
				maxCardBottomGap: Math.max(0, ...cards.map((card) => {
					const cardRect = card.getBoundingClientRect();
					const actionsRect = card.querySelector('.catalog-card__actions')?.getBoundingClientRect();
					return actionsRect ? cardRect.bottom - actionsRect.bottom : 0;
				})),
				quickBuyButtons: document.querySelectorAll('.catalog-card__quick-buy').length,
				validQuickBuyLinks: [...document.querySelectorAll('.catalog-card__quick-buy')].filter((link) => {
					const url = new URL(link.href);
					return url.pathname.includes('/checkout') && url.searchParams.has('add-to-cart');
				}).length,
				columns: grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length : 0,
				rightmost,
				maxRowHeightSpread: Math.max(0, ...rowHeightSpreads),
				maxButtonLabelOffset: Math.max(0, ...buttonLabelOffsets),
				maxButtonHorizontalOffset: Math.max(0, ...buttonHorizontalOffsets),
				imageRatio: firstImage ? firstImage.width / firstImage.height : 0,
				badgeStyle: firstBadge ? {
					height: firstBadge.getBoundingClientRect().height,
					color: getComputedStyle(firstBadge).color,
					background: getComputedStyle(firstBadge).backgroundColor,
				} : null,
				buttonStyle: firstButton ? {
					height: firstButton.getBoundingClientRect().height,
					background: getComputedStyle(firstButton).backgroundColor,
					paddingLeft: getComputedStyle(firstButton).paddingLeft,
					paddingRight: getComputedStyle(firstButton).paddingRight,
				} : null,
				titleClamp: firstTitle ? getComputedStyle(firstTitle).webkitLineClamp : '',
				typography: catalogTitle && catalogLead ? {
					h1Size: parseFloat(getComputedStyle(catalogTitle).fontSize),
					h1Line: parseFloat(getComputedStyle(catalogTitle).lineHeight),
					bodySize: parseFloat(getComputedStyle(catalogLead).fontSize),
					bodyLine: parseFloat(getComputedStyle(catalogLead).lineHeight),
				} : null,
				searchHeight: catalogSearch?.getBoundingClientRect().height || 0,
				searchIconClearance: catalogSearchInput && catalogSearchIcon
					? (catalogSearchInput.getBoundingClientRect().left + parseFloat(getComputedStyle(catalogSearchInput).paddingLeft)) - catalogSearchIcon.getBoundingClientRect().right
					: 0,
				sortEnhanced: Boolean(catalogSort?.classList.contains('catalog-ordering--enhanced')),
				sortButtonHeight: catalogSortButton?.getBoundingClientRect().height || 0,
				sortButtonRadius: catalogSortButton ? parseFloat(getComputedStyle(catalogSortButton).borderRadius) : 0,
				nativeSortClipped: nativeSort ? getComputedStyle(nativeSort).clipPath === 'inset(50%)' : false,
				containerWidth: pageContainer?.getBoundingClientRect().width || 0,
				userIconTopSpread: userIconTops.length ? Math.max(...userIconTops) - Math.min(...userIconTops) : 0,
				headerActionCenterSpread: headerActionCenters.length ? Math.max(...headerActionCenters) - Math.min(...headerActionCenters) : 0,
				desktopColumnTopOffset: filters && sidebarBody ? Math.abs(filters.top - sidebarBody.top) : 0,
				headerToBreadcrumbGap: header && breadcrumbList ? breadcrumbList.top - header.bottom : 0,
				headerMainPaddingTop: headerMain ? parseFloat(getComputedStyle(headerMain).paddingTop) : 0,
				headerRowGap: headerRow ? parseFloat(getComputedStyle(headerRow).columnGap) : 0,
				headerRowPaddingLeft: headerRow ? parseFloat(getComputedStyle(headerRow).paddingLeft) : 0,
				hasLegacyInnerHero: Boolean(document.querySelector('.header .hero-small')),
				toggleVisible: Boolean(toggle && getComputedStyle(toggle).display !== 'none'),
				loadMoreVisible: (() => {
					const control = document.querySelector('.catalog-grid__more');
					return Boolean(control && !control.hidden && getComputedStyle(control).display !== 'none');
				})(),
				nestedInteractive: Boolean(document.querySelector('a button, button a')),
			};
		})()`);

		const expectedColumns = width < 480 ? 1 : 3;
		assert(`${width}px catalog title`, ['Каталог', 'Catalog'].includes(state.title), state.title);
		assert(`${width}px redesign stylesheet`, state.style);
		assert(`${width}px no horizontal overflow`, state.overflow <= 1, `${state.overflow}px`);
		assert(`${width}px product cards render`, state.cards > 0, String(state.cards));
		assert(`${width}px unavailable products are absent from catalog`, state.unavailableCards === 0, JSON.stringify(state));
		assert(`${width}px every card has an action`, state.buttons === state.cards, `${state.buttons}/${state.cards}`);
		assert(`${width}px every available card has quick buy`, state.quickBuyButtons === state.availableCards, `${state.quickBuyButtons}/${state.availableCards}`);
		assert(`${width}px quick-buy links go straight to checkout`, state.validQuickBuyLinks === state.quickBuyButtons, `${state.validQuickBuyLinks}/${state.quickBuyButtons}`);
		assert(`${width}px expected grid columns`, state.columns === expectedColumns, `${state.columns}, expected ${expectedColumns}`);
		assert(`${width}px cards fit viewport`, state.rightmost <= width + 1, `${state.rightmost}px`);
		assert(`${width}px cards use the available column width`, state.minCardWidth >= (width < 480 ? width - 64 : 200), `${state.minCardWidth}px`);
		assert(`${width}px cards have equal height within rows`, state.maxRowHeightSpread <= 1, `${state.maxRowHeightSpread}px`);
		assert(`${width}px cards do not reserve empty space after actions`, state.maxCardBottomGap <= 17, `${state.maxCardBottomGap}px`);
		assert(`${width}px action labels are vertically centered`, state.maxButtonLabelOffset <= 1, `${state.maxButtonLabelOffset}px`);
		assert(`${width}px action labels are horizontally centered`, state.maxButtonHorizontalOffset <= 1, `${state.maxButtonHorizontalOffset}px`);
		assert(`${width}px product image area is square`, Math.abs(state.imageRatio - 1) <= 0.01, String(state.imageRatio));
		assert(`${width}px product title is not line-clamped`, !state.titleClamp || state.titleClamp === 'none', state.titleClamp);
		assert(`${width}px inactive load-more control stays hidden`, !state.loadMoreVisible, JSON.stringify({ loadMoreVisible: state.loadMoreVisible }));
		if (state.badgeStyle) {
			assert(`${width}px badges use shared geometry and palette`, Math.abs(state.badgeStyle.height - 24) <= 1 && state.badgeStyle.color === 'rgb(75, 47, 215)' && state.badgeStyle.background === 'rgb(240, 236, 255)', JSON.stringify(state.badgeStyle));
		}
		assert(`${width}px primary card button uses shared component`, Math.abs(state.buttonStyle.height - 46) <= 1 && state.buttonStyle.background === 'rgb(108, 76, 255)' && state.buttonStyle.paddingLeft === state.buttonStyle.paddingRight, JSON.stringify(state.buttonStyle));
		const expectedH1 = width < 768 ? [28, 36] : width < 1024 ? [32, 40] : [36, 44];
		assert(`${width}px shared page typography`, Math.abs(state.typography.h1Size - expectedH1[0]) <= 0.1 && Math.abs(state.typography.h1Line - expectedH1[1]) <= 0.1 && state.typography.bodySize === 16 && state.typography.bodyLine === 26, JSON.stringify(state.typography));
		assert(`${width}px shared catalog field height`, Math.abs(state.searchHeight - 48) <= 1, `${state.searchHeight}px`);
		assert(`${width}px search icon does not overlap text`, state.searchIconClearance >= 8, `${state.searchIconClearance}px`);
		assert(`${width}px ordering uses branded custom control`, state.sortEnhanced && state.nativeSortClipped && Math.abs(state.sortButtonHeight - 52) <= 1 && state.sortButtonRadius >= 13, JSON.stringify(state));
		assert(`${width}px shared container width`, state.containerWidth <= Math.min(width, 1222) + 1, `${state.containerWidth}px`);
		assert(`${width}px header icons share one baseline`, state.userIconTopSpread <= 1, `${state.userIconTopSpread}px`);
		if (width < 768) {
			assert(`${width}px mobile header actions share one grid line`, state.headerActionCenterSpread <= 1, `${state.headerActionCenterSpread}px`);
		}
		assert(`${width}px category toggle visibility`, state.toggleVisible === (width < 1024));
		assert(`${width}px no nested interactive elements`, !state.nestedInteractive);
		assert(`${width}px catalog does not render legacy inner hero`, !state.hasLegacyInnerHero);
		assert(`${width}px compact header-to-content gap`, state.headerToBreadcrumbGap <= 24, `${state.headerToBreadcrumbGap}px`);
		if (width >= 1024) {
			assert(`${width}px search aligns with category panel`, state.desktopColumnTopOffset <= 1, `${state.desktopColumnTopOffset}px`);
		}
		if (width > 1024) {
			assert(`${width}px desktop header uses roomier spacing`, state.headerMainPaddingTop >= 36 && state.headerRowGap >= 30 && state.headerRowPaddingLeft >= 24, JSON.stringify({ padding: state.headerMainPaddingTop, gap: state.headerRowGap, side: state.headerRowPaddingLeft }));
		}

		await evaluate(`document.querySelector('.catalog-sort__button')?.click()`);
		await delay(100);
		const sortMenu = await evaluate(`(() => {
			const button = document.querySelector('.catalog-sort__button');
			const menu = document.querySelector('.catalog-sort__menu');
			const selected = menu?.querySelector('[aria-selected="true"]');
			const menuStyle = menu ? getComputedStyle(menu) : null;
			const buttonRect = button?.getBoundingClientRect();
			const menuRect = menu?.getBoundingClientRect();
			return {
				open: button?.getAttribute('aria-expanded') === 'true' && menu?.hidden === false,
				options: menu?.querySelectorAll('.catalog-sort__option').length || 0,
				selected: Boolean(selected),
				background: menuStyle?.backgroundColor || '',
				radius: parseFloat(menuStyle?.borderRadius || '0'),
				overflow: document.documentElement.scrollWidth - innerWidth,
				horizontalOffset: buttonRect && menuRect
					? Math.max(Math.abs(buttonRect.left - menuRect.left), Math.abs(buttonRect.right - menuRect.right))
					: 999,
			};
		})()`);
		assert(`${width}px ordering menu opens in brand style`, sortMenu.open && sortMenu.options >= 5 && sortMenu.selected && sortMenu.background === 'rgb(255, 255, 255)' && sortMenu.radius >= 15 && sortMenu.overflow <= 1, JSON.stringify(sortMenu));
		if (width < 768) {
			assert(`${width}px ordering menu aligns with its button`, sortMenu.horizontalOffset <= 1, JSON.stringify(sortMenu));
		}
		await evaluate(`document.querySelector('.catalog-sort__button')?.click()`);

		if (width >= 1024) {
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

		if (width < 1024) {
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

		await capture(`catalog-${width}.png`);
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

	for (const width of [393, 1280]) {
		await navigate(new URL(productTagPath, baseUrl).toString(), width);
		const productTag = await evaluate(`(() => {
			const header = document.querySelector('.header')?.getBoundingClientRect();
			const breadcrumbs = document.querySelector('.catalog__breadcrumbs')?.getBoundingClientRect();
			const searchForm = document.querySelector('.catalog-filters__search');
			const searchTag = searchForm?.querySelector('input[name="product_tag"]');
			const cards = [...document.querySelectorAll('.catalog-card')];
			return {
				title: document.querySelector('.catalog__title')?.textContent.trim() || '',
				style: Boolean(document.querySelector('link[href*="storefront-redesign.css"]')),
				catalogScript: Boolean(document.querySelector('script[src*="catalog-page.js"]')),
				layout: Boolean(document.querySelector('.catalog__layout')),
				cards: cards.length,
				tagValue: searchTag?.value || '',
				hasLegacyInnerHero: Boolean(document.querySelector('.header .hero-small')),
				headerToBreadcrumbGap: header && breadcrumbs ? breadcrumbs.top - header.bottom : 0,
				overflow: document.documentElement.scrollWidth - window.innerWidth,
			};
		})()`);
		assert(`${width}px product tag uses its own H1`, productTag.title.toLowerCase() === 'outlook', productTag.title);
		assert(`${width}px product tag loads catalog assets`, productTag.style && productTag.catalogScript, JSON.stringify(productTag));
		assert(`${width}px product tag uses catalog layout`, productTag.layout && productTag.cards > 0, JSON.stringify(productTag));
		assert(`${width}px product-tag search preserves its scope`, productTag.tagValue === 'outlook', productTag.tagValue);
		assert(`${width}px product tag does not render legacy hero`, !productTag.hasLegacyInnerHero);
		assert(`${width}px product tag has compact header gap`, productTag.headerToBreadcrumbGap <= 24, `${productTag.headerToBreadcrumbGap}px`);
		assert(`${width}px product tag has no horizontal overflow`, productTag.overflow <= 1, `${productTag.overflow}px`);
		if (width === 393) await capture('product-tag-outlook-393.png');
		if (width === 1280) await capture('product-tag-outlook-1280.png');
	}

	await navigate(new URL('/shop/', baseUrl).toString(), 1280);
	const catalogMenu = await evaluate(`(() => {
		document.querySelector('.catalog-btn')?.click();
		const menu = document.querySelector('.catalog__nav');
		const container = document.querySelector('.header-main__row.grid-container');
		const menuRect = menu?.getBoundingClientRect();
		const containerRect = container?.getBoundingClientRect();
		const parentItems = [...(menu?.querySelectorAll('.catalog-nav__parent-item') || [])];
		const parentLabels = parentItems.map((item) => item.querySelector('.catalog-nav__parent-text')?.textContent.trim() || '');
		const childrenFor = (label) => {
			const item = parentItems.find((candidate) => candidate.querySelector('.catalog-nav__parent-text')?.textContent.trim() === label);
			return [...(item?.querySelectorAll('.catalog-nav__child-link') || [])].map((link) => link.textContent.trim());
		};
		const closeButton = menu?.querySelector('.catalog-nav__close');
		const closeRect = closeButton?.getBoundingClientRect();
		return {
			open: Boolean(menu?.classList.contains('active')),
			expanded: document.querySelector('.catalog-btn')?.getAttribute('aria-expanded'),
			parentLabels,
			officeChildren: childrenFor('Microsoft Office'),
			windowsChildren: childrenFor('Microsoft Windows'),
			closeVisible: Boolean(closeButton) && getComputedStyle(closeButton).display !== 'none',
			closeSize: closeRect ? { width: closeRect.width, height: closeRect.height } : null,
			left: menuRect?.left || 0,
			right: menuRect?.right || 0,
			containerLeft: containerRect?.left || 0,
			containerRight: containerRect?.right || 0,
		};
	})()`);
	assert('desktop catalog menu opens inside header container', catalogMenu.open && catalogMenu.expanded === 'true' && catalogMenu.left >= catalogMenu.containerLeft - 1 && catalogMenu.right <= catalogMenu.containerRight + 1, JSON.stringify(catalogMenu));
	assert('catalog starts with Office then Windows', catalogMenu.parentLabels[0] === 'Microsoft Office' && catalogMenu.parentLabels[1] === 'Microsoft Windows', catalogMenu.parentLabels.join(' | '));
	assert('Office releases run oldest to newest', JSON.stringify(catalogMenu.officeChildren.slice(0, 4)) === JSON.stringify(['Office 2016', 'Office 2019', 'Office 2021', 'Office 2024']), catalogMenu.officeChildren.join(' | '));
	assert('Windows releases run oldest to newest', JSON.stringify(catalogMenu.windowsChildren.slice(0, 2)) === JSON.stringify(['Windows 10', 'Windows 11']), catalogMenu.windowsChildren.join(' | '));
	assert('desktop catalog has a visible close button', catalogMenu.closeVisible);
	assert('catalog close button has a 44px target', Math.abs(catalogMenu.closeSize.width - 44) <= 1 && Math.abs(catalogMenu.closeSize.height - 44) <= 1, JSON.stringify(catalogMenu.closeSize));
	const desktopSubcategories = await evaluate(`(() => {
		const button = document.querySelector('.catalog-nav__toggle-btn');
		const item = button?.closest('.catalog-nav__parent-item');
		const panel = item?.querySelector('.catalog-nav__children-panel');
		const beforeUrl = window.location.href;
		if (item?.classList.contains('open')) button?.click();
		button?.click();
		return {
			urlUnchanged: window.location.href === beforeUrl,
			open: Boolean(item?.classList.contains('open')),
			expanded: button?.getAttribute('aria-expanded'),
			panelVisible: Boolean(panel) && getComputedStyle(panel).display !== 'none',
		};
	})()`);
	assert('desktop subcategory toggle opens in place without navigation', desktopSubcategories.urlUnchanged && desktopSubcategories.open && desktopSubcategories.expanded === 'true' && desktopSubcategories.panelVisible, JSON.stringify(desktopSubcategories));
	await capture('catalog-menu-1280.png');
	const catalogClosed = await evaluate(`(() => {
		document.querySelector('.catalog-nav__close')?.click();
		return {
			open: document.querySelector('.catalog__nav')?.classList.contains('active'),
			expanded: document.querySelector('.catalog-btn')?.getAttribute('aria-expanded'),
		};
	})()`);
	assert('catalog close button closes the menu', !catalogClosed.open && catalogClosed.expanded === 'false', JSON.stringify(catalogClosed));

	for (const width of [393, 1280]) {
		await navigate(new URL('/reviews/', baseUrl).toString(), width, 1000);
		const reviewsPage = await evaluate(`(() => {
			const card = document.querySelector('.page-reviews__form-card');
			const form = document.querySelector('.page-reviews__form');
			const submit = document.querySelector('.page-reviews__submit');
			return {
				overflow: document.documentElement.scrollWidth - window.innerWidth,
				card: Boolean(card),
				form: Boolean(form),
				product: Boolean(form?.querySelector('[name="comment_post_ID"]')),
				rating: Boolean(form?.querySelector('[name="rating"]')),
				comment: Boolean(form?.querySelector('[name="comment"]')),
				submitBackground: submit ? getComputedStyle(submit).backgroundColor : '',
			};
		})()`);
		assert(`${width}px reviews form uses the branded layout`, reviewsPage.card && reviewsPage.form && reviewsPage.product && reviewsPage.rating && reviewsPage.comment && reviewsPage.submitBackground === 'rgb(108, 76, 255)', JSON.stringify(reviewsPage));
		assert(`${width}px reviews page has no horizontal overflow`, reviewsPage.overflow <= 1, `${reviewsPage.overflow}px`);
		await capture(`reviews-form-${width}.png`);
	}

	await navigate(new URL('/checkout/?add-to-cart=327', baseUrl).toString(), 1280, 1000);
	await delay(2500);
	await evaluate(`document.querySelector('.wc-block-components-totals-coupon .wc-block-components-panel__button')?.click(); true`);
	await delay(500);
	const checkoutPolish = await evaluate(`(() => {
		const couponForm = document.querySelector('.wc-block-components-totals-coupon__form');
		const couponInput = couponForm?.querySelector('input');
		const couponButton = couponForm?.querySelector('.wc-block-components-totals-coupon__button');
		const notice = document.createElement('ul');
		notice.className = 'woocommerce-error';
		notice.innerHTML = '<li>Вы не можете добавить это количество в корзину.</li>';
		document.querySelector('main')?.prepend(notice);
		const noticeStyle = getComputedStyle(notice);
		const buttonStyle = couponButton ? getComputedStyle(couponButton) : null;
		return {
			couponVisible: Boolean(couponForm) && getComputedStyle(couponForm).display !== 'none',
			couponAligned: Boolean(couponInput && couponButton) && Math.abs(couponInput.getBoundingClientRect().height - couponButton.getBoundingClientRect().height) <= 1,
			couponRadius: buttonStyle?.borderRadius || '',
			couponBackground: buttonStyle?.backgroundColor || '',
			noticeRadius: noticeStyle.borderRadius,
			noticeFont: noticeStyle.fontFamily,
			noticeBackground: noticeStyle.backgroundColor,
		};
	})()`);
	assert('checkout coupon action uses the branded control', checkoutPolish.couponVisible && checkoutPolish.couponAligned && checkoutPolish.couponRadius === '12px' && checkoutPolish.couponBackground !== 'rgb(54, 57, 63)', JSON.stringify(checkoutPolish));
	assert('technical notices use shared brand styles', checkoutPolish.noticeRadius === '14px' && checkoutPolish.noticeFont.toLowerCase().includes('inter') && checkoutPolish.noticeBackground === 'rgb(255, 245, 246)', JSON.stringify(checkoutPolish));
	await capture('checkout-coupon-1280.png');

	const suggestions = await evaluate(`(async () => {
		const input = document.querySelector('#itsoft-header-search');
		input.value = 'Windows';
		input.dispatchEvent(new Event('input', { bubbles: true }));
		await new Promise((resolve) => setTimeout(resolve, 350));
		const options = [...document.querySelectorAll('.header-main__search .search-suggestions__product')];
		input.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
		return {
			open: input.getAttribute('aria-expanded'),
			labels: options.map((option) => option.querySelector('.search-suggestions__title')?.textContent.trim() || ''),
			selected: options.filter((option) => option.getAttribute('aria-selected') === 'true').length,
			unavailableIndexed: (window.ITSOFT_SEARCH_SUGGESTIONS?.products || []).some((product) => String(product.url || '').includes('/windows-11-pro-professionalnaya/')),
		};
	})()`);
	assert('Windows search suggests matching product cards', suggestions.open === 'true' && suggestions.labels.length > 0 && suggestions.labels.every((label) => label.includes('Windows')), JSON.stringify(suggestions));
	assert('search suggestions support keyboard selection', suggestions.selected === 1, JSON.stringify(suggestions));
	assert('unavailable products are absent from search suggestions', !suggestions.unavailableIndexed, JSON.stringify(suggestions));

	await navigate(new URL('/?s=Windows%2011%20Pro', baseUrl).toString(), 1280);
	const unavailableSearch = await evaluate(`(() => ({
		unavailableCards: [...document.querySelectorAll('.catalog-card__link')].filter((link) => link.href.includes('/windows-11-pro-professionalnaya/')).length,
		overflow: document.documentElement.scrollWidth - innerWidth,
	}))()`);
	assert('unavailable products are absent from search results', unavailableSearch.unavailableCards === 0 && unavailableSearch.overflow <= 1, JSON.stringify(unavailableSearch));

	const outsideClose = await evaluate(`(() => {
		document.querySelector('.catalog-btn')?.click();
		document.body.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));
		return !document.querySelector('.catalog__nav')?.classList.contains('active');
	})()`);
	assert('catalog closes after a click outside the panel', outsideClose);

	await navigate(new URL('/shop/', baseUrl).toString(), 1440, 900);
	const dynamicBefore = await evaluate(`(() => ({
		cards: document.querySelectorAll('.catalog-grid__list .catalog-card').length,
		page: Number(document.querySelector('[data-catalog-grid]')?.dataset.currentPage || 1),
		max: Number(document.querySelector('[data-catalog-grid]')?.dataset.maxPages || 1),
	}))()`);
	if (dynamicBefore.max > dynamicBefore.page) {
		await evaluate(`document.querySelector('.catalog-grid__sentinel')?.scrollIntoView({ block: 'center' }); true`);
		await delay(1800);
		const dynamicAfter = await evaluate(`(() => ({
			cards: document.querySelectorAll('.catalog-grid__list .catalog-card').length,
			page: Number(document.querySelector('[data-catalog-grid]')?.dataset.currentPage || 1),
		}))()`);
		assert('dynamic catalog loading appends shared cards', dynamicAfter.page > dynamicBefore.page && dynamicAfter.cards > dynamicBefore.cards, JSON.stringify({ dynamicBefore, dynamicAfter }));
	}

	await navigate(new URL('/shop/page/2/', baseUrl).toString(), 1440);
	const secondPage = await evaluate(`(() => ({
		cards: document.querySelectorAll('.catalog-card').length,
		breadcrumb: [...document.querySelectorAll('.catalog__breadcrumbs .breadcrumbs__item')].map((item) => item.textContent.trim()),
		overflow: document.documentElement.scrollWidth - window.innerWidth,
	}))()`);
	assert('second catalog page renders shared cards', secondPage.cards > 0 && secondPage.overflow <= 1, JSON.stringify(secondPage));
	await capture('catalog-page-2-1440.png');

	await navigate(new URL('/shop/?orderby=price', baseUrl).toString(), 1440);
	const sortedCatalog = await evaluate(`(() => ({
		ordering: document.querySelector('.catalog-filters__ordering select')?.value || '',
		cards: document.querySelectorAll('.catalog-card').length,
		overflow: document.documentElement.scrollWidth - window.innerWidth,
	}))()`);
	assert('catalog sorting preserves shared grid', sortedCatalog.ordering === 'price' && sortedCatalog.cards > 0 && sortedCatalog.overflow <= 1, JSON.stringify(sortedCatalog));
	await capture('catalog-sorted-price-1440.png');

	await navigate(new URL('/?s=codex-no-such-license-92841', baseUrl).toString(), 1440);
	const noResults = await evaluate(`(() => ({
		empty: Boolean(document.querySelector('.itsoft-search__empty')),
		cards: document.querySelectorAll('.catalog-card').length,
		overflow: document.documentElement.scrollWidth - window.innerWidth,
	}))()`);
	assert('no-results search renders the intentional empty state', noResults.empty && noResults.cards === 0 && noResults.overflow <= 1, JSON.stringify(noResults));
	await capture('search-no-results-1440.png');

	await navigate(new URL('/shop/', baseUrl).toString(), 1280);

	const ajaxButtonExists = await evaluate(`Boolean(document.querySelector('.catalog-card__buy.ajax_add_to_cart'))`);
	assert('simple product has AJAX add button', ajaxButtonExists);
	if (ajaxButtonExists) {
		const testedProductId = await evaluate(`document.querySelector('.catalog-card__buy.ajax_add_to_cart')?.dataset.product_id || ''`);
		await evaluate(`document.querySelector('.catalog-card__buy.ajax_add_to_cart').click()`);
		await delay(1800);
		const added = await evaluate(`(() => {
			const button = document.querySelector('.catalog-card__buy[data-product_id="${testedProductId}"]');
			return {
				added: button.classList.contains('added') || button.classList.contains('catalog-card__buy--added'),
				label: button.querySelector('.catalog-card__buy-label')?.textContent.trim() || '',
			};
		})()`);
		assert('AJAX add-to-cart completes', added.added && added.label === 'В корзине', JSON.stringify(added));

		const quantityBeforeSecondClick = await evaluate(`(async () => {
			const response = await fetch('/wp-json/wc/store/v1/cart', { credentials: 'same-origin' });
			const cart = await response.json();
			const item = (cart.items || []).find((entry) => String(entry.id) === ${JSON.stringify(testedProductId)});
			return Number(item?.quantity || 0);
		})()`);
		await evaluate(`document.querySelector('.catalog-card__buy[data-product_id="${testedProductId}"]').click()`);
		await delay(1200);
		const secondClick = await evaluate(`(async () => {
			const response = await fetch('/wp-json/wc/store/v1/cart', { credentials: 'same-origin' });
			const cart = await response.json();
			const item = (cart.items || []).find((entry) => String(entry.id) === ${JSON.stringify(testedProductId)});
			return { path: location.pathname, quantity: Number(item?.quantity || 0) };
		})()`);
		assert(
			'clicking an in-cart card opens cart without adding another item',
			secondClick.path.includes('/cart') && secondClick.quantity === quantityBeforeSecondClick,
			JSON.stringify({ quantityBeforeSecondClick, ...secondClick })
		);
		await navigate(new URL('/shop/', baseUrl).toString(), 1280);
		const persistedInCartState = await evaluate(`(() => {
			const button = document.querySelector('.catalog-card__buy[data-product_id="${testedProductId}"]');
			return {
				state: button?.dataset.cartState || '',
				label: button?.querySelector('.catalog-card__buy-label')?.textContent.trim() || '',
				href: button?.getAttribute('href') || '',
			};
		})()`);
		assert(
			'in-cart card state survives a page reload',
			persistedInCartState.state === 'in-cart'
				&& persistedInCartState.label === 'В корзине'
				&& persistedInCartState.href.includes('/cart'),
			JSON.stringify(persistedInCartState)
		);
	}

	const quickBuyUrl = await evaluate(`document.querySelector('.catalog-card__quick-buy')?.href || ''`);
	assert('catalog exposes a direct quick-buy checkout URL', Boolean(quickBuyUrl), quickBuyUrl);
	if (quickBuyUrl) {
		await navigate(quickBuyUrl, 1280);
		const quickBuyCheckout = await evaluate(`(() => ({
			path: location.pathname,
			checkout: Boolean(document.querySelector('.woocommerce-checkout, .checkout-page')),
			cartCount: Number.parseInt(document.querySelector('[data-cart-count]')?.textContent || '0', 10),
		}))()`);
		assert('quick buy adds the product and opens checkout', quickBuyCheckout.path.includes('/checkout') && quickBuyCheckout.checkout && quickBuyCheckout.cartCount > 0, JSON.stringify(quickBuyCheckout));
	}

	for (const width of [393, 768, 1280, 1440]) {
		await navigate(new URL('/', baseUrl).toString(), width, 1100);
		if (width === 393) {
			const cookieNotice = await evaluate(`(() => {
				const notice = document.querySelector('#cookie-notice');
				const buttons = document.querySelector('.cn-buttons-container');
				return {
					visible: Boolean(notice) && getComputedStyle(notice).display !== 'none',
					overflow: document.documentElement.scrollWidth - window.innerWidth,
					noticeWidth: notice?.getBoundingClientRect().width || 0,
					buttonsRight: buttons?.getBoundingClientRect().right || 0,
				};
			})()`);
			assert('393px cookie notice stays inside the viewport', cookieNotice.overflow <= 1 && cookieNotice.noticeWidth <= 393 && cookieNotice.buttonsRight <= 393, JSON.stringify(cookieNotice));

			const mobileSearch = await evaluate(`(async () => {
				const form = document.querySelector('.header-main__search');
				const open = form?.querySelector('.search__mobile-button');
				const input = form?.querySelector('.search__input');
				const submit = form?.querySelector('.search__submit');
				const close = form?.querySelector('.search__mobile-close');
				const row = document.querySelector('.header-main__row');
				const profile = document.querySelector('.header-main__user .user-nav__login');
				const cart = document.querySelector('.header-main__user .user-nav__cart');
				const orders = document.querySelector('.header-main__user .user-nav__orders');
				const cartText = cart?.querySelector('.user-nav__text');
				const searchIcon = open?.querySelector('svg');
				const visible = (element) => Boolean(element) && getComputedStyle(element).display !== 'none';
				const rowHeightBefore = row?.getBoundingClientRect().height || 0;
				const openRect = open?.getBoundingClientRect();
				const searchIconRect = searchIcon?.getBoundingClientRect();
				const profileRect = profile?.getBoundingClientRect();
				const cartRect = cart?.getBoundingClientRect();
				const before = {
					open: visible(open),
					input: visible(input),
					submit: visible(submit),
					profile: visible(profile),
					cart: visible(cart),
					orders: visible(orders),
					cartText: visible(cartText),
					searchButtonSize: [openRect?.width || 0, openRect?.height || 0],
					searchIconSize: [searchIconRect?.width || 0, searchIconRect?.height || 0],
					cartBeforeProfile: Boolean(cartRect && profileRect) && cartRect.left < profileRect.left,
					actionGapDifference: openRect && cartRect && profileRect
						? Math.abs((cartRect.left - openRect.right) - (profileRect.left - cartRect.right))
						: 999,
				};
				open?.click();
				await new Promise((resolve) => requestAnimationFrame(resolve));
				const rect = form?.getBoundingClientRect();
				const closeRect = close?.getBoundingClientRect();
				const after = {
					active: form?.classList.contains('active') || false,
					open: visible(open),
					input: visible(input),
					submit: visible(submit),
					close: visible(close),
					left: rect?.left || 0,
					right: rect?.right || 0,
					focused: document.activeElement === input,
					closeInside: Boolean(rect && closeRect) && closeRect.left >= rect.left && closeRect.right <= rect.right,
					rowHeight: row?.getBoundingClientRect().height || 0,
				};
				close?.click();
				return { before, after, rowHeightBefore, closed: !form?.classList.contains('active') };
			})()`);
			assert(
				'393px mobile search has one icon before opening and a fitted form after opening',
				mobileSearch.before.open
					&& !mobileSearch.before.input
					&& !mobileSearch.before.submit
					&& mobileSearch.before.profile
					&& mobileSearch.before.cart
					&& !mobileSearch.before.orders
					&& !mobileSearch.before.cartText
					&& mobileSearch.before.searchButtonSize.every((size) => Math.abs(size - 44) <= 1)
					&& mobileSearch.before.searchIconSize.every((size) => Math.abs(size - 22) <= 1)
					&& mobileSearch.before.cartBeforeProfile
					&& mobileSearch.before.actionGapDifference <= 1
					&& mobileSearch.after.active
					&& !mobileSearch.after.open
					&& mobileSearch.after.input
					&& mobileSearch.after.submit
					&& mobileSearch.after.close
					&& mobileSearch.after.left >= 15
					&& mobileSearch.after.right <= 378
					&& mobileSearch.after.focused
					&& mobileSearch.after.closeInside
					&& Math.abs(mobileSearch.after.rowHeight - mobileSearch.rowHeightBefore) <= 1
					&& mobileSearch.closed,
				JSON.stringify(mobileSearch),
			);

			const mobileLanguage = await evaluate(`(async () => {
				const burger = document.querySelector('.mobile-nav__burger');
				burger?.click();
				await new Promise((resolve) => requestAnimationFrame(resolve));
				const panel = document.querySelector('.catalog__nav');
				const language = panel?.querySelector('.catalog-nav__mobile-language');
				const links = [...(language?.querySelectorAll('.glink') || [])];
				const floating = [...document.querySelectorAll('.gt_switcher_wrapper, #gt_float_wrapper')]
					.some((element) => getComputedStyle(element).display !== 'none');
				const result = {
					open: panel?.classList.contains('active') || false,
					visible: Boolean(language) && getComputedStyle(language).display !== 'none',
					labels: links.map((link) => link.textContent.trim().toUpperCase()),
					floating,
				};
				panel?.querySelector('[data-catalog-close]')?.click();
				return result;
			})()`);
			assert(
				'393px mobile menu exposes RU/EN and hides the floating translator',
				mobileLanguage.open
					&& mobileLanguage.visible
					&& mobileLanguage.labels.includes('RU')
					&& mobileLanguage.labels.includes('EN')
					&& !mobileLanguage.floating,
				JSON.stringify(mobileLanguage),
			);
		}
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
				nativeHeroVisible: Boolean(document.querySelector('.home-hero')) && getComputedStyle(document.querySelector('.home-hero')).display !== 'none',
				sliderHeroVisible: Boolean(document.querySelector('.hero')) && getComputedStyle(document.querySelector('.hero')).display !== 'none',
				heroTitle: document.querySelector('.home-hero__title')?.textContent.trim() || '',
				actions: document.querySelectorAll('.home-hero__actions a').length,
				benefits: document.querySelectorAll('.home-benefits__item').length,
				categories: document.querySelectorAll('.shop-cats__item').length,
				categoryColumns: categoryGrid ? getComputedStyle(categoryGrid).gridTemplateColumns.split(' ').filter(Boolean).length : 0,
				popular: popular.length,
				recommended: recommended.length,
				duplicates: recommendedIds.filter((id) => popularIds.includes(id)),
				productActions: [...popular, ...recommended].filter((card) => card.querySelector('.catalog-card__buy')).length,
				catalogButtonInside: (() => {
					const button = document.querySelector('.popular-products:not(.recommend-products) .btn-show-all');
					const header = button?.closest('.products-block__header');
					const buttonRect = button?.getBoundingClientRect();
					const headerRect = header?.getBoundingClientRect();
					return Boolean(buttonRect && headerRect)
						&& buttonRect.left >= headerRect.left - 1
						&& buttonRect.right <= headerRect.right + 1
						&& buttonRect.right <= window.innerWidth;
				})(),
				articleSection: Boolean(document.querySelector('.useful-articles')),
			};
		})()`);

		assert(`${width}px home has no horizontal overflow`, home.overflow <= 1, `${home.overflow}px`);
		assert(`${width}px home has one H1`, home.h1 === 1, String(home.h1));
		assert(`${width}px configured hero renders`, home.sliderHero || (home.nativeHero && home.heroTitle.length > 10), home.heroTitle);
		assert(
			`${width}px home hero follows the responsive visibility rule`,
			width <= 767 ? !home.nativeHeroVisible && !home.sliderHeroVisible : home.nativeHeroVisible || home.sliderHeroVisible,
			JSON.stringify(home),
		);
		if (home.nativeHero) {
			assert(`${width}px single hero action renders`, home.actions === 1, String(home.actions));
			assert(`${width}px removed trust strip stays absent`, home.benefits === 0, String(home.benefits));
		}
		assert(`${width}px selected categories render`, home.categories > 0, String(home.categories));
		assert(`${width}px category grid columns`, home.categoryColumns === (width < 860 ? 2 : 3), String(home.categoryColumns));
		assert(`${width}px commerce sections render`, home.popular > 0 && home.recommended > 0, `${home.popular}/${home.recommended}`);
		assert(`${width}px home products do not repeat`, home.duplicates.length === 0, home.duplicates.join(','));
		assert(`${width}px every home product has an action`, home.productActions === home.popular + home.recommended, `${home.productActions}/${home.popular + home.recommended}`);
		assert(`${width}px full-catalog button stays inside its header`, home.catalogButtonInside, JSON.stringify(home));
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
		const form = document.querySelector('.itsoft-search__form');
		const input = form?.querySelector('.itsoft-search__input');
		const icon = form?.querySelector('.itsoft-search__icon');
		const submit = form?.querySelector('.itsoft-search__submit');
		const formStyles = form ? getComputedStyle(form) : null;
		const inputStyles = input ? getComputedStyle(input) : null;
		const submitStyles = submit ? getComputedStyle(submit) : null;
		const inputRect = input?.getBoundingClientRect();
		const iconRect = icon?.getBoundingClientRect();
		return {
			storefrontStyles: Boolean(document.querySelector('link[href*="storefront-redesign.css"]')),
			searchStyles: Boolean(document.querySelector('link[href*="search.css"]')),
			cards: cards.length,
			columns: grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length : 0,
			minCardWidth: Math.min(...cards.map((card) => card.getBoundingClientRect().width)),
			formHeight: form?.getBoundingClientRect().height || 0,
			formRadius: parseFloat(formStyles?.borderRadius || '0'),
			inputRadius: parseFloat(inputStyles?.borderRadius || '0'),
			inputPaddingLeft: parseFloat(inputStyles?.paddingLeft || '0'),
			buttonHeight: submit?.getBoundingClientRect().height || 0,
			buttonColor: submitStyles?.backgroundColor || '',
			hasSearchIcon: Boolean(icon),
			iconInsideInput: Boolean(inputRect && iconRect) && iconRect.left >= inputRect.left && iconRect.right <= inputRect.right,
			overflow: document.documentElement.scrollWidth - window.innerWidth,
		};
	})()`);
	assert('search loads storefront and search styles', populatedSearch.storefrontStyles && populatedSearch.searchStyles, JSON.stringify(populatedSearch));
	assert('search form uses branded rounded control', populatedSearch.formHeight >= 54 && populatedSearch.inputRadius >= 12, JSON.stringify(populatedSearch));
	assert('search submit uses branded purple button', populatedSearch.buttonHeight >= 42 && populatedSearch.buttonColor === 'rgb(108, 76, 255)', JSON.stringify(populatedSearch));
	assert('search icon has dedicated space before input', populatedSearch.hasSearchIcon && populatedSearch.iconInsideInput && populatedSearch.inputPaddingLeft >= 44, JSON.stringify(populatedSearch));
	assert('search renders the shared four-column product grid', populatedSearch.cards > 0 && populatedSearch.columns === 4 && populatedSearch.minCardWidth > 250, JSON.stringify(populatedSearch));
	assert('populated search has no horizontal overflow', populatedSearch.overflow <= 1, `${populatedSearch.overflow}px`);

	const headerCases = [
		['home', '/'],
		['catalog', '/shop/'],
		['product', '/product/windows-11-iot-enterprise-2024-ltsc-korporativnaya/'],
		['search', '/?s=Windows'],
		['checkout', '/checkout/?add-to-cart=327'],
	];
	let sharedHeaderStructure = '';
	for (const [name, path] of headerCases) {
		await navigate(new URL(path, baseUrl).toString(), 1280);
		const headerState = await evaluate(`(() => {
			const row = document.querySelector('.header-main__row');
			const brandImage = row?.querySelector('.brand-logo__image');
			const main = document.querySelector('.header-main');
			return {
				structure: row ? [...row.children].map((node) => node.className).join('|') : '',
				mainHeight: Math.round(main?.getBoundingClientRect().height || 0),
				rowHeight: Math.round(row?.getBoundingClientRect().height || 0),
				brandImage: Boolean(brandImage),
				brandWidth: Math.round(brandImage?.getBoundingClientRect().width || 0),
				brandAlt: brandImage?.getAttribute('alt')?.trim() || '',
				bodyFont: getComputedStyle(document.body).fontFamily,
				legacyLogo: Boolean(row?.querySelector('.logo__img')),
				catalogButton: Boolean(row?.querySelector('.catalog-btn')),
				search: Boolean(row?.querySelector('.header-main__search')),
				userNav: Boolean(row?.querySelector('.header-main__user')),
			};
		})()`);
		if (!sharedHeaderStructure) sharedHeaderStructure = headerState.structure;
		assert(`${name} uses the shared approved header`,
			headerState.structure === sharedHeaderStructure
			&& headerState.mainHeight > 0
			&& headerState.rowHeight >= 48
			&& headerState.brandImage
			&& headerState.brandWidth > 100
			&& headerState.brandAlt.length > 0
			&& headerState.bodyFont.includes('Inter')
			&& !headerState.legacyLogo
			&& headerState.catalogButton
			&& headerState.search
			&& headerState.userNav,
			JSON.stringify(headerState),
		);
	}

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

