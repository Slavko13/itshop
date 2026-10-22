(() => {
	const config = window.ITSOFT_PRODUCT_REVIEWS;

	if (typeof config === 'undefined') {
		return;
	}

	const STATE = new WeakMap();
	const getPhrase = (key, fallback = '') => {
		if (config && config.i18n && config.i18n[key]) {
			return config.i18n[key];
		}

		return fallback;
	};

	document.addEventListener('DOMContentLoaded', () => {
		document.querySelectorAll('[data-product-reviews]').forEach((container) => {
			initReviewFormToggle(container);

			const list = container.querySelector('[data-reviews-list]');
			const button = container.querySelector('[data-reviews-more]');
			const feedback = container.querySelector('[data-reviews-feedback]');

			if (!list || !button) {
				return;
			}

			const state = {
				container,
				list,
				button,
				feedback,
				productId: Number(container.dataset.productId),
				total: Number(container.dataset.total) || 0,
				loaded: Number(container.dataset.loaded) || 0,
				perPage: Number(container.dataset.perPage) || config.perPage || 10,
				isLoading: false,
			};

			STATE.set(container, state);

			button.addEventListener('click', () => handleLoadMore(container));

			updateButtonVisibility(state);
		});
	});

	const setFeedback = (state, message = '') => {
		if (!state.feedback) {
			return;
		}

		state.feedback.textContent = message;
		state.feedback.classList.toggle('is-visible', Boolean(message));
	};

	const setButtonLoading = (state, isLoading) => {
		if (!state.button) {
			return;
		}

		if (isLoading) {
			state.button.disabled = true;
			if (!state.button.dataset.defaultLabel) {
				state.button.dataset.defaultLabel = state.button.textContent.trim();
			}
			state.button.textContent = getPhrase('loading', 'Loading...');
		} else {
			state.button.disabled = false;
			const label = state.button.dataset.defaultLabel || getPhrase('loadMore');
			if (label) {
				state.button.textContent = label;
			}
		}
	};

	const handleLoadMore = async (container) => {
		const state = STATE.get(container);

		if (!state || state.isLoading) {
			return;
		}

		state.isLoading = true;
		setButtonLoading(state, true);
		setFeedback(state, getPhrase('loading'));

		try {
			const payload = await requestReviews({
				productId: state.productId,
				offset: state.loaded,
			});

			if (!payload.success) {
				throw new Error(payload.data?.message || 'Request failed');
			}

			const { data } = payload;

			if (data?.html) {
				state.list.insertAdjacentHTML('beforeend', data.html);
			}

			state.loaded = typeof data?.loaded === 'number' ? data.loaded : state.loaded + state.perPage;
			state.total = typeof data?.total === 'number' ? data.total : state.total;

			updateButtonVisibility(state);

			setFeedback(state, (data && data.message) || '');
		} catch (error) {
			console.error('ITSoft: reviews load failed', error);
			setFeedback(state, getPhrase('error', 'Ошибка загрузки'));
		} finally {
			state.isLoading = false;
			setButtonLoading(state, false);
		}
	};

	const updateButtonVisibility = (state) => {
		if (!state.button) {
			return;
		}

		if (state.loaded >= state.total || state.total === 0) {
			state.button.setAttribute('hidden', '');
		} else {
			state.button.removeAttribute('hidden');
		}
	};

	const requestReviews = async (params) => {
		const formData = new FormData();
		formData.append('action', config.action);
		formData.append('nonce', config.nonce);

		Object.entries(params).forEach(([key, value]) => {
			formData.append(key, value);
		});

		const response = await fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		});

		if (!response.ok) {
			throw new Error(`Network error: ${response.status}`);
		}

		return response.json();
	};

	const initReviewFormToggle = (container) => {
		const toggle = container.querySelector('[data-review-form-toggle]');
		const formWrapper = container.querySelector('[data-review-form]');

		if (!toggle || !formWrapper) {
			return;
		}

		const updateAriaState = () => {
			toggle.setAttribute('aria-expanded', formWrapper.hasAttribute('hidden') ? 'false' : 'true');
		};

		updateAriaState();

		const scrollToForm = () => {
			const target = formWrapper.querySelector('#review_form') || formWrapper;

			if (!target) {
				return;
			}

			if (typeof target.scrollIntoView === 'function') {
				target.scrollIntoView({ behavior: 'smooth', block: 'start' });
				return;
			}

			if (typeof window.scrollTo === 'function') {
				const targetTop = window.scrollY + target.getBoundingClientRect().top;

				try {
					window.scrollTo({
						top: targetTop,
						left: 0,
						behavior: 'smooth',
					});
				} catch (error) {
					window.scrollTo(0, targetTop);
				}
			}
		};

		const revealForm = (event) => {
			if (event) {
				event.preventDefault();
			}

			if (formWrapper.hasAttribute('hidden')) {
				formWrapper.removeAttribute('hidden');
				formWrapper.classList.add('is-visible');
				updateAriaState();
			}

			if (typeof window.requestAnimationFrame === 'function') {
				window.requestAnimationFrame(scrollToForm);
			} else {
				scrollToForm();
			}
		};

		toggle.addEventListener('click', revealForm);
	};
})();

