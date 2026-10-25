document.addEventListener('DOMContentLoaded', () => {
	document.querySelectorAll('form[role="search"]').forEach((form) => {
		const field = form.querySelector('[name="s"]');
		if (!field) return;

		field.addEventListener('input', () => field.setCustomValidity(''));
		form.addEventListener('submit', (event) => {
			field.value = field.value.trim();
			if (field.value) return;

			event.preventDefault();
			field.setCustomValidity('Введите название программы или лицензии');
			field.reportValidity();
			field.focus();
		});
	});

	const copyTextFallback = (value) => new Promise((resolve, reject) => {
		const textarea = document.createElement('textarea');
		textarea.value = value;
		textarea.setAttribute('readonly', '');
		textarea.style.position = 'fixed';
		textarea.style.left = '0';
		textarea.style.top = '0';
		textarea.style.opacity = '0';
		document.body.appendChild(textarea);
		textarea.focus();
		textarea.select();
		textarea.setSelectionRange(0, textarea.value.length);

		try {
			document.execCommand('copy') ? resolve() : reject(new Error('copy failed'));
		} catch (error) {
			reject(error);
		} finally {
			textarea.remove();
		}
	});

	const copyText = (value) => {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(value).catch(() => copyTextFallback(value));
		}

		return copyTextFallback(value);
	};

	document.querySelectorAll('code').forEach((codeEl) => {
		if (codeEl.closest('[data-license-control]') || codeEl.querySelector('.code__copy')) return;

		const button = document.createElement('button');
		button.className = 'code__copy';
		button.type = 'button';
		button.setAttribute('aria-label', 'Скопировать код');
		button.setAttribute('title', 'Скопировать код');

		button.addEventListener('click', () => {
			const value = codeEl.dataset.copyValue || codeEl.textContent.trim();
			if (!value || button.disabled) return;

			button.disabled = true;
			button.classList.remove('is-copied', 'is-error');

			copyText(value).then(() => {
				button.classList.add('is-copied');
				button.setAttribute('aria-label', 'Скопировано');
				button.setAttribute('title', 'Скопировано');
			}).catch(() => {
				button.classList.add('is-error');
				button.setAttribute('aria-label', 'Не удалось скопировать');
				button.setAttribute('title', 'Не удалось скопировать');
			}).finally(() => {
				button.disabled = false;
				window.setTimeout(() => {
					button.classList.remove('is-copied', 'is-error');
					button.setAttribute('aria-label', 'Скопировать код');
					button.setAttribute('title', 'Скопировать код');
				}, 1800);
			});
		});

		codeEl.appendChild(button);
	});

	const licenseLabels = () => {
		const isEnglish = document.documentElement.lang.toLowerCase().startsWith('en')
			|| document.documentElement.classList.contains('translated-ltr')
			|| document.cookie.split(';').some((item) => item.trim().startsWith('googtrans=/ru/en'));

		return isEnglish
			? { show: 'Show', hide: 'Hide', copy: 'Copy', copied: 'Copied', success: 'License key copied', error: 'Could not copy the key' }
			: { show: 'Показать', hide: 'Скрыть', copy: 'Скопировать', copied: 'Скопировано', success: 'Ключ скопирован', error: 'Не удалось скопировать' };
	};

	document.querySelectorAll('[data-license-control]').forEach((control) => {
		if (control.dataset.licenseControlReady === 'true') return;

		const output = control.querySelector('[data-license-value]');
		const reveal = control.querySelector('[data-license-reveal]');
		const copy = control.querySelector('[data-license-copy]');
		const feedback = control.querySelector('[data-license-feedback]');
		if (!output) return;

		control.dataset.licenseControlReady = 'true';

		if (reveal) {
			reveal.addEventListener('click', () => {
				const labels = licenseLabels();
				const isOpen = reveal.getAttribute('aria-pressed') === 'true';
				output.textContent = isOpen ? output.dataset.masked : output.dataset.value;
				reveal.setAttribute('aria-pressed', isOpen ? 'false' : 'true');
				reveal.textContent = isOpen ? labels.show : labels.hide;
			});
		}

		if (copy) {
			copy.addEventListener('click', () => {
				const labels = licenseLabels();
				copyText(output.dataset.value).then(() => {
					if (feedback) feedback.textContent = labels.success;
					copy.textContent = labels.copied;
					window.setTimeout(() => {
						copy.textContent = licenseLabels().copy;
						if (feedback) feedback.textContent = '';
					}, 1800);
				}).catch(() => {
					if (feedback) feedback.textContent = labels.error;
				});
			});
		}
	});

    (() => {
        const buttons = [...document.querySelectorAll('.mobile-nav__burger')];
        const menu  = document.querySelector('.catalog__nav');
        const body  = document.querySelector('body');

        if (buttons.length && menu) {
            buttons.forEach(button =>
                button.addEventListener('click', () => {
                    const isOpen = menu.classList.toggle('active');
                    body.classList.toggle('no-scroll', isOpen);
                    button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    button.setAttribute('aria-label', isOpen ? 'Закрыть каталог' : 'Открыть каталог');
                })
            );
        }

    })();

    (() => {
        // Main Header Search
        const headerSearch = document.querySelector('.header-main__search');
        if (headerSearch) {
            const openBtns = document.querySelectorAll('.header-main__row .search__mobile-button'); // Only buttons in main header
            const closeBtn = headerSearch.querySelector('.search__mobile-close');
    
            if (openBtns.length) {
                openBtns.forEach(btn => {
                    btn.addEventListener('click', e => {
                        e.stopPropagation();
                        headerSearch.classList.add('active');
						btn.setAttribute('aria-expanded', 'true');
						window.requestAnimationFrame(() => headerSearch.querySelector('.search__input')?.focus());
                    });
                });
            }

			const closeSearch = () => {
				headerSearch.classList.remove('active');
				openBtns.forEach(btn => btn.setAttribute('aria-expanded', 'false'));
				headerSearch.querySelector('.search__input')?.blur();
			};

            if (closeBtn) {
                closeBtn.addEventListener('click', e => {
                    e.stopPropagation();
					closeSearch();
                });
            }

			headerSearch.addEventListener('keydown', event => {
				if (event.key !== 'Escape' || !headerSearch.classList.contains('active')) return;

				event.preventDefault();
				closeSearch();
				openBtns[0]?.focus();
			});
        }

        // Catalog Nav Mobile Search
        const catalogSearch = document.querySelector('.catalog-nav__search');
        if (catalogSearch) {
            const openBtn = document.querySelector('.catalog-nav__mobile-search-btn');
            const closeBtn = document.querySelector('.catalog-nav__search-close');

            if (openBtn) {
                openBtn.addEventListener('click', e => {
                    e.stopPropagation();
                    catalogSearch.classList.add('active');
                });
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', e => {
                    e.stopPropagation();
                    catalogSearch.classList.remove('active');
                });
            }
        }
    })();

    // Catalog button functionality
    const catalogBtn = document.querySelector('.catalog-btn');
    const popNav     = document.querySelector('.catalog__nav');
    const positionCatalogMenu = () => {
        if (!catalogBtn || !popNav) return;

        if (window.innerWidth <= 1024) {
            popNav.style.removeProperty('left');
            popNav.style.removeProperty('right');
            popNav.style.removeProperty('width');
            return;
        }

        const container = document.querySelector('.header-main__row.grid-container');
        if (!container) return;

        const containerRect = container.getBoundingClientRect();
        const btnRect = catalogBtn.getBoundingClientRect();
        const menuWidth = Math.min(800, containerRect.width);
        const desiredLeft = btnRect.left - containerRect.left;
        const maxLeft = Math.max(0, containerRect.width - menuWidth);
        const menuLeft = Math.min(Math.max(0, desiredLeft), maxLeft);

        popNav.style.left = menuLeft + 'px';
        popNav.style.right = 'auto';
        popNav.style.width = menuWidth + 'px';
    };

    if (catalogBtn && popNav) {
		const closeCatalog = () => {
			popNav.classList.remove('active');
			catalogBtn.classList.remove('active');
			catalogBtn.setAttribute('aria-expanded', 'false');
			document.querySelectorAll('.mobile-nav__burger').forEach((button) => {
				button.setAttribute('aria-expanded', 'false');
				button.setAttribute('aria-label', 'Открыть каталог');
			});
			document.body.classList.remove('no-scroll');
			document.body.style.overflowY = '';
		};

        catalogBtn.addEventListener('click', function() {
            const isOpen = popNav.classList.toggle('active');
            this.classList.toggle('active', isOpen);
            this.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

            if (isOpen) positionCatalogMenu();
        });

		popNav.querySelectorAll('[data-catalog-close]').forEach((button) => {
			button.addEventListener('click', closeCatalog);
		});

		document.addEventListener('keydown', (event) => {
			if ('Escape' === event.key && popNav.classList.contains('active')) {
				closeCatalog();
				catalogBtn.focus();
			}
		});

		document.addEventListener('pointerdown', (event) => {
			if (!popNav.classList.contains('active')) return;
			if (popNav.contains(event.target) || catalogBtn.contains(event.target)) return;
			if (event.target.closest('.mobile-nav__burger')) return;

			closeCatalog();
		});

        window.addEventListener('resize', () => {
            if (popNav.classList.contains('active')) positionCatalogMenu();
        });
    }

});

(function($) {
    'use strict';

    /* DOM ready */
    $(function() {
        $('.item-posts__title').matchHeight({
            byRow: true,
            property: 'height'
        });
    });

})(jQuery);
