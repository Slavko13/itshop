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

document.addEventListener('DOMContentLoaded', () => {
	const sidebar = document.querySelector('[data-catalog-sidebar]');
	if (sidebar) {
		new CatalogSidebarToggle(sidebar).init();
	}

	const grid = document.querySelector('[data-catalog-grid]');
	if (grid) {
		new CatalogGridLoader(grid, window.ITSOFT_CATALOG_PAGE || {}).init();
	}

	const searchForm = document.querySelector('.catalog-filters__search');
	if (searchForm) {
		new CatalogSearch(searchForm).init();
	}


	new CatalogPurchaseState().init();
});

