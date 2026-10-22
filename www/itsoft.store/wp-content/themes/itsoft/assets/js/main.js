document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('code').forEach(codeEl => {
        const btn = document.createElement('div');
        btn.className = 'code__copy';
        btn.style.cursor = 'pointer';

        btn.addEventListener('click', () => {
                btn.remove();
                const text = codeEl.innerText;
                navigator.clipboard.writeText(text).catch(() => {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    ta.remove();
                });
            codeEl.appendChild(btn);
        });

        codeEl.appendChild(btn);
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
                    });
                });
            }
        
            if (closeBtn) {
                closeBtn.addEventListener('click', e => {
                    e.stopPropagation();
                    headerSearch.classList.remove('active');
                });
            }
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
        catalogBtn.addEventListener('click', function() {
            const isOpen = popNav.classList.toggle('active');
            this.classList.toggle('active', isOpen);
            this.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

            if (isOpen) positionCatalogMenu();
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
