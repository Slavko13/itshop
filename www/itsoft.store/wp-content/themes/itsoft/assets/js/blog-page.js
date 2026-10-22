class BlogGridLoader {
	constructor(root, settings = {}) {
		this.root = root;
		this.list = root.querySelector('.blog-grid__list');
		this.status = root.querySelector('.blog-grid__status');
		this.sentinel = root.querySelector('.blog-grid__sentinel');

		const defaults = {
			currentPage: 1,
			maxPages: 1,
			i18n: {
				loading: 'Loading posts…',
				error: 'Unable to load more posts. Please try again.',
				completed: 'You have viewed all posts.',
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
		this.observer = null;
		this.fallbackHandler = null;
	}

	init() {
		if (!this.list) {
			return;
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
		} else if (this.sentinel) {
			this.enableScrollFallback();
		}
	}

	enableScrollFallback() {
		const onScroll = () => {
			if (this.isLoading || this.currentPage >= this.maxPages || !this.sentinel) {
				return;
			}

			const rect = this.sentinel.getBoundingClientRect();
			if (rect.top - window.innerHeight < 300) {
				this.fetchNextPage();
			}
		};

		this.fallbackHandler = () => window.requestAnimationFrame(onScroll);
		window.addEventListener('scroll', this.fallbackHandler, { passive: true });
		window.addEventListener('resize', this.fallbackHandler);
		this.fallbackHandler();
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
		this.root.classList.toggle('blog-grid--loading', state);
	}

	fetchNextPage() {
		if (this.isLoading || this.currentPage >= this.maxPages) {
			if (this.currentPage >= this.maxPages) {
				this.finish(true);
			}

			return;
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
				this.appendPosts(html);
			})
			.catch(() => {
				this.handleError();
			})
			.finally(() => {
				this.setLoadingState(false);
			});
	}

	appendPosts(html) {
		const parser = new DOMParser();
		const doc = parser.parseFromString(html, 'text/html');
		const nextList = doc.querySelector('.blog-grid__list');

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
	}

	finish(showMessage = false) {
		if (this.observer && this.sentinel) {
			this.observer.unobserve(this.sentinel);
		}

		if (this.sentinel) {
			this.sentinel.remove();
		}

		if (this.fallbackHandler) {
			window.removeEventListener('scroll', this.fallbackHandler);
			window.removeEventListener('resize', this.fallbackHandler);
		}

		if (showMessage) {
			this.setStatus(this.settings.i18n.completed);
		} else {
			this.setStatus('');
		}
	}
}

document.addEventListener('DOMContentLoaded', () => {
	const grid = document.querySelector('[data-blog-grid]');
	if (grid) {
		new BlogGridLoader(grid, window.ITSOFT_BLOG_PAGE || {}).init();
	}
});
