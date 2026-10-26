class CatalogSidebarToggle {
	constructor(root) {
		this.root = root;
		this.button = root.querySelector('.catalog-sidebar__toggle');
		this.label = root.querySelector('.catalog-sidebar__toggle-label');
		this.settings = window.ITSOFT_CATALOG_PAGE || {};
	}

	init() {
		if (!this.button) {
			return;
		}

		this.button.addEventListener('click', () => this.toggle());
		document.addEventListener('keydown', (event) => {
			if (event.key === 'Escape') {
				this.toggle(false);
			}
		});
	}

	toggle(force) {
		const isExpanded = this.button.getAttribute('aria-expanded') === 'true';
		const nextState = typeof force === 'boolean' ? force : !isExpanded;

		this.button.setAttribute('aria-expanded', String(nextState));
		this.root.classList.toggle('catalog-sidebar--open', nextState);

		if (this.label) {
			const i18n = this.settings.i18n || {};
			this.label.textContent = nextState
				? (i18n.closeCategories || 'Скрыть категории')
				: (i18n.openCategories || 'Открыть категории');
		}
	}
}

class CatalogCategoryTree {
	constructor(root) {
		this.root = root;
		this.toggles = [...root.querySelectorAll('.catalog-tree__toggle')];
	}

	init() {
		this.toggles.forEach((toggle) => {
			toggle.addEventListener('click', (event) => {
				event.preventDefault();
				event.stopPropagation();

				const item = toggle.closest('.catalog-tree__item--has-children');
				if (!item) return;

				const nextState = item.getAttribute('aria-expanded') !== 'true';
				this.closeSiblings(item);
				this.setExpanded(item, toggle, nextState);
			});
		});
	}

	closeSiblings(item) {
		const list = item.parentElement;
		if (!list) return;

		[...list.children].forEach((sibling) => {
			if (sibling === item || !sibling.classList.contains('catalog-tree__item--has-children')) return;
			const toggle = sibling.querySelector(':scope > .catalog-tree__row > .catalog-tree__toggle');
			this.setExpanded(sibling, toggle, false);
		});
	}

	setExpanded(item, toggle, expanded) {
		item.setAttribute('aria-expanded', String(expanded));
		if (toggle) toggle.setAttribute('aria-expanded', String(expanded));
	}
}

class CatalogGridLoader {
	constructor(root, settings = {}) {
		this.root = root;
		this.list = root.querySelector('.catalog-grid__list');
		this.button = root.querySelector('.catalog-grid__more');
		this.status = root.querySelector('.catalog-grid__status');
		this.sentinel = root.querySelector('.catalog-grid__sentinel');

		const defaults = {
			currentPage: 1,
			maxPages: 1,
			i18n: {
				loading: 'Loading products…',
				error: 'Unable to load more products. Please try again.',
				completed: 'You have viewed all products.',
				loadMore: 'Load more',
			},
		};

		this.settings = {
			...defaults,
			...settings,
			i18n: {
				...defaults.i18n,
				...(settings.i18n || {}),
			},
		};

		this.currentPage = parseInt(root.dataset.currentPage || this.settings.currentPage, 10) || 1;
		this.maxPages = parseInt(root.dataset.maxPages || this.settings.maxPages, 10) || 1;
		this.isLoading = false;
		this.manualMode = false;
		this.observer = null;
	}

	init() {
		if (!this.list) {
			return;
		}

		if (this.button) {
			this.button.addEventListener('click', () => this.fetchNextPage());
			this.button.textContent = this.settings.i18n.loadMore;
		}

		if (this.currentPage >= this.maxPages) {
			this.finish(false);
			return;
		}

		if ('IntersectionObserver' in window && this.sentinel) {
			this.observer = new IntersectionObserver(
				(entries) => {
					entries.forEach((entry) => {
						if (entry.isIntersecting) {
							this.fetchNextPage();
						}
					});
				},
				{
					rootMargin: '300px 0px',
				}
			);

			this.observer.observe(this.sentinel);
		} else {
			this.enableManualMode();
		}
	}

	enableManualMode() {
		if (this.manualMode) {
			return;
		}

		this.manualMode = true;
		this.root.classList.add('catalog-grid--manual');

		if (this.button) {
			this.button.hidden = false;
			this.button.disabled = false;
		}
	}

	setStatus(message = '', isError = false) {
		if (!this.status) {
			return;
		}

		this.status.textContent = message;
		this.status.dataset.state = message ? (isError ? 'error' : 'info') : '';
	}

	setLoadingState(state) {
		this.isLoading = state;
		this.root.classList.toggle('catalog-grid--loading', state);

		if (this.button) {
			if (this.manualMode) {
				this.button.disabled = state;
			} else if (!state) {
				this.button.hidden = true;
			}
		}
	}

	fetchNextPage() {
		if (this.isLoading || this.currentPage >= this.maxPages) {
			if (this.currentPage >= this.maxPages) {
				this.finish(true);
			}

			return;
		}

		if (!this.manualMode && this.button) {
			this.button.hidden = true;
		}

		this.setLoadingState(true);
		this.setStatus(this.settings.i18n.loading);

		const nextPage = this.currentPage + 1;
		const url = new URL(window.location.href);
		url.searchParams.set('paged', String(nextPage));

		fetch(url.toString(), {
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
			},
		})
			.then((response) => {
				if (!response.ok) {
					throw new Error('Network error');
				}
				return response.text();
			})
			.then((html) => {
				this.appendProducts(html);
			})
			.catch(() => {
				this.handleError();
			})
			.finally(() => {
				this.setLoadingState(false);
			});
	}

	appendProducts(html) {
		const parser = new DOMParser();
		const doc = parser.parseFromString(html, 'text/html');
		const nextList = doc.querySelector('.catalog-grid__list');

		if (!nextList || !nextList.children.length) {
			this.finish(true);
			return;
		}

		const fragment = document.createDocumentFragment();

		Array.from(nextList.children).forEach((child) => {
			fragment.appendChild(child);
		});

		this.list.appendChild(fragment);

		this.currentPage += 1;
		this.root.dataset.currentPage = String(this.currentPage);
		this.setStatus('');

		if (this.currentPage >= this.maxPages) {
			this.finish(true);
		}
	}

	handleError() {
		this.setStatus(this.settings.i18n.error, true);
		this.enableManualMode();
	}

	finish(showMessage = false) {
		if (this.observer && this.sentinel) {
			this.observer.unobserve(this.sentinel);
		}

		if (this.sentinel) {
			this.sentinel.remove();
		}

		if (this.button) {
			if (this.manualMode) {
				this.button.disabled = true;
			} else {
				this.button.hidden = true;
			}
		}

		if (showMessage) {
			this.setStatus(this.settings.i18n.completed);
		} else {
			this.setStatus('');
		}
	}
}

class CatalogPurchaseState {
	init() {
		if (typeof jQuery === 'undefined') {
			return;
		}

		const settings = window.ITSOFT_CATALOG_PAGE || {};
		const i18n = settings.i18n || {};
		const getLabel = (button) => button && button.querySelector('.catalog-card__buy-label');

		jQuery(document.body).on('adding_to_cart', (event, button) => {
			const element = button && button[0];
			const label = getLabel(element);

			if (!element || !label) {
				return;
			}

			if (!element.dataset.originalLabel) {
				element.dataset.originalLabel = label.textContent.trim();
			}

			element.classList.add('catalog-card__buy--loading');
			element.setAttribute('aria-busy', 'true');
			label.textContent = i18n.adding || 'Добавляем…';
		});

		jQuery(document.body).on('added_to_cart', (event, fragments, cartHash, button) => {
			const element = button && button[0];
			const label = getLabel(element);

			if (!element || !label) {
				return;
			}

			element.classList.remove('catalog-card__buy--loading');
			element.classList.add('catalog-card__buy--added');
			element.removeAttribute('aria-busy');
			label.textContent = i18n.added || 'В корзине';
		});
	}
}

class CatalogSearch {
	constructor(form) {
		this.form = form;
		this.input = form.querySelector('.catalog-filters__input');
	}

	init() {
		if (!this.form || !this.input) {
			return;
		}

		this.form.addEventListener('submit', (e) => this.handleSubmit(e));
	}

	handleSubmit(e) {
		if (this.input.value.trim() === '') {
			this.input.disabled = true;
			setTimeout(() => {
				this.input.disabled = false;
			}, 100);
		}
	}
}

class CatalogOrdering {
	constructor(root) {
		this.root = root;
		this.form = root.querySelector('.woocommerce-ordering');
		this.select = root.querySelector('select.orderby');
		this.button = null;
		this.label = null;
		this.menu = null;
		this.items = [];
		this.activeIndex = 0;
	}

	init() {
		if (!this.form || !this.select || this.select.dataset.catalogSortReady === 'true') {
			return;
		}

		this.select.dataset.catalogSortReady = 'true';
		this.root.classList.add('catalog-ordering--enhanced');
		this.build();
		this.sync();

		this.button.addEventListener('click', () => this.toggle());
		this.button.addEventListener('keydown', (event) => this.handleButtonKeydown(event));
		this.menu.addEventListener('keydown', (event) => this.handleMenuKeydown(event));
		this.select.addEventListener('change', () => this.sync());

		document.addEventListener('click', (event) => {
			if (!this.root.contains(event.target)) {
				this.close();
			}
		});

		document.addEventListener('keydown', (event) => {
			if (event.key === 'Escape' && this.isOpen()) {
				event.preventDefault();
				this.close(true);
			}
		});
	}

	build() {
		const wrapper = document.createElement('div');
		wrapper.className = 'catalog-sort';

		this.button = document.createElement('button');
		this.button.className = 'catalog-sort__button';
		this.button.type = 'button';
		this.button.setAttribute('aria-haspopup', 'listbox');
		this.button.setAttribute('aria-expanded', 'false');

		this.label = document.createElement('span');
		this.label.className = 'catalog-sort__label';
		this.button.appendChild(this.label);

		const chevron = document.createElement('span');
		chevron.className = 'catalog-sort__chevron';
		chevron.setAttribute('aria-hidden', 'true');
		chevron.innerHTML = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.5 6.25L8 9.75L11.5 6.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>';
		this.button.appendChild(chevron);

		this.menu = document.createElement('div');
		this.menu.className = 'catalog-sort__menu';
		this.menu.id = `catalog-sort-options-${CatalogOrdering.instanceCount += 1}`;
		this.menu.setAttribute('role', 'listbox');
		this.menu.setAttribute('aria-label', this.select.getAttribute('aria-label') || 'Сортировка');
		this.menu.hidden = true;
		this.button.setAttribute('aria-controls', this.menu.id);

		Array.from(this.select.options).forEach((option, index) => {
			const item = document.createElement('button');
			item.className = 'catalog-sort__option';
			item.type = 'button';
			item.tabIndex = -1;
			item.dataset.value = option.value;
			item.setAttribute('role', 'option');
			item.setAttribute('aria-selected', 'false');

			const text = document.createElement('span');
			text.textContent = option.textContent.trim();
			item.appendChild(text);

			const check = document.createElement('span');
			check.className = 'catalog-sort__check';
			check.setAttribute('aria-hidden', 'true');
			check.innerHTML = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.5 8.2L6.5 11L12.5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>';
			item.appendChild(check);

			item.addEventListener('click', () => this.choose(index));
			this.menu.appendChild(item);
			this.items.push(item);
		});

		wrapper.append(this.button, this.menu);
		this.form.appendChild(wrapper);
	}

	isOpen() {
		return this.button && this.button.getAttribute('aria-expanded') === 'true';
	}

	open() {
		this.menu.hidden = false;
		this.button.setAttribute('aria-expanded', 'true');
		this.root.classList.add('is-open');
		window.requestAnimationFrame(() => this.items[this.activeIndex]?.focus());
	}

	close(returnFocus = false) {
		if (!this.button || !this.menu) {
			return;
		}
		this.menu.hidden = true;
		this.button.setAttribute('aria-expanded', 'false');
		this.root.classList.remove('is-open');
		if (returnFocus) {
			this.button.focus();
		}
	}

	toggle() {
		if (this.isOpen()) {
			this.close();
		} else {
			this.open();
		}
	}

	sync() {
		const selectedIndex = Math.max(0, this.select.selectedIndex);
		const selectedOption = this.select.options[selectedIndex];
		this.activeIndex = selectedIndex;
		this.label.textContent = selectedOption ? selectedOption.textContent.trim() : '';
		this.items.forEach((item, index) => {
			item.setAttribute('aria-selected', String(index === selectedIndex));
		});
	}

	choose(index) {
		const item = this.items[index];
		if (!item) {
			return;
		}

		this.select.value = item.dataset.value;
		this.sync();
		this.close();
		this.select.dispatchEvent(new Event('change', { bubbles: true }));
	}

	focusItem(index) {
		this.activeIndex = (index + this.items.length) % this.items.length;
		this.items[this.activeIndex]?.focus();
	}

	handleButtonKeydown(event) {
		if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
			return;
		}

		event.preventDefault();
		this.open();
		if (event.key === 'Home') this.focusItem(0);
		if (event.key === 'End') this.focusItem(this.items.length - 1);
		if (event.key === 'ArrowUp') this.focusItem(this.activeIndex - 1);
		if (event.key === 'ArrowDown') this.focusItem(this.activeIndex);
	}

	handleMenuKeydown(event) {
		const focusedIndex = this.items.indexOf(document.activeElement);
		if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
			event.preventDefault();
			this.focusItem(focusedIndex + (event.key === 'ArrowDown' ? 1 : -1));
		}
		if (event.key === 'Home' || event.key === 'End') {
			event.preventDefault();
			this.focusItem(event.key === 'Home' ? 0 : this.items.length - 1);
		}
	}
}

CatalogOrdering.instanceCount = 0;

document.addEventListener('DOMContentLoaded', () => {
	const sidebar = document.querySelector('[data-catalog-sidebar]');
	if (sidebar) {
		new CatalogSidebarToggle(sidebar).init();
		const categoryTree = sidebar.querySelector('.catalog-tree');
		if (categoryTree) new CatalogCategoryTree(categoryTree).init();
	}

	const grid = document.querySelector('[data-catalog-grid]');
	if (grid) {
		new CatalogGridLoader(grid, window.ITSOFT_CATALOG_PAGE || {}).init();
	}

	const searchForm = document.querySelector('.catalog-filters__search');
	if (searchForm) {
		new CatalogSearch(searchForm).init();
	}

	document.querySelectorAll('.catalog-filters__ordering').forEach((ordering) => {
		new CatalogOrdering(ordering).init();
	});

	new CatalogPurchaseState().init();
});

