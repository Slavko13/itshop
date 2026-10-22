(() => {
    'use strict';

    const normalize = (value) => String(value || '')
        .toLocaleLowerCase('ru-RU')
        .replace(/\s+/g, ' ')
        .trim();

    const initTools = (tools) => {
        const grid = document.querySelector('[data-distribution-grid]');
        if (!grid) {
            return;
        }

        const cards = Array.from(grid.querySelectorAll('[data-distribution-card]'));
        const search = tools.querySelector('[data-distribution-search]');
        const languageSelect = tools.querySelector('[data-distribution-language]');
        const architectureSelect = tools.querySelector('[data-distribution-architecture]');
        const sortSelect = tools.querySelector('[data-distribution-sort]');
        const buttons = Array.from(tools.querySelectorAll('[data-distribution-filter]'));
        const counter = tools.querySelector('[data-distribution-count]');
        const empty = document.querySelector('[data-distribution-empty]');
        let category = 'all';

        const populateSelect = (select, attribute) => {
            if (!select) {
                return;
            }
            const values = new Set();
            cards.forEach((card) => {
                String(card.dataset[attribute] || '').split('|').filter(Boolean).forEach((value) => values.add(value));
            });
            Array.from(values).sort((a, b) => a.localeCompare(b, 'ru')).forEach((value) => {
                const option = document.createElement('option');
                option.value = normalize(value);
                option.textContent = value.toUpperCase() === value ? value : value;
                select.append(option);
            });
            if (values.size === 0) {
                select.hidden = true;
            }
        };

        populateSelect(languageSelect, 'language');
        populateSelect(architectureSelect, 'architecture');

        const update = () => {
            const query = normalize(search ? search.value : '');
            const language = languageSelect ? languageSelect.value : 'all';
            const architecture = architectureSelect ? architectureSelect.value : 'all';
            let visible = 0;

            if (sortSelect) {
                const sorted = cards.slice().sort((a, b) => {
                    if (sortSelect.value === 'title') {
                        return normalize(a.dataset.title).localeCompare(normalize(b.dataset.title), 'ru');
                    }
                    return Number(b.dataset.modified || 0) - Number(a.dataset.modified || 0);
                });
                sorted.forEach((card) => grid.append(card));
            }

            cards.forEach((card) => {
                const haystack = normalize(card.dataset.search || card.textContent);
                const categories = String(card.dataset.category || '').split(/\s+/);
                const languages = normalize(card.dataset.language).split('|');
                const architectures = normalize(card.dataset.architecture).split('|');
                const matchesSearch = !query || haystack.includes(query);
                const matchesCategory = category === 'all' || categories.includes(category);
                const matchesLanguage = language === 'all' || languages.includes(language);
                const matchesArchitecture = architecture === 'all' || architectures.includes(architecture);
                const show = matchesSearch && matchesCategory && matchesLanguage && matchesArchitecture;

                card.hidden = !show;
                if (show) {
                    visible += 1;
                }
            });

            if (counter) {
                counter.textContent = `Показано: ${visible} из ${cards.length}`;
            }
            if (empty) {
                empty.hidden = visible !== 0;
            }
        };

        if (search) {
            search.addEventListener('input', update);
            search.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && search.value) {
                    search.value = '';
                    update();
                }
            });
        }

        [languageSelect, architectureSelect, sortSelect].filter(Boolean).forEach((select) => {
            select.addEventListener('change', update);
        });

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                category = button.dataset.distributionFilter || 'all';
                buttons.forEach((item) => {
                    const active = item === button;
                    item.classList.toggle('is-active', active);
                    item.setAttribute('aria-pressed', String(active));
                });
                update();
            });
        });

        update();
    };

    document.querySelectorAll('[data-distribution-tools]').forEach(initTools);
})();
