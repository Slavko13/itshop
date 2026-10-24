class ItsoftCategorySuggestions {
	constructor(form, categories) {
		this.form = form;
		this.input = form.querySelector('input[name="s"]');
		this.categories = categories;
		this.timer = null;
		this.activeIndex = -1;
		this.matches = [];
		this.listbox = null;
	}

	init() {
		if (!this.input || this.input.dataset.suggestionsReady === 'true') return;

		this.input.dataset.suggestionsReady = 'true';
		this.listbox = document.createElement('div');
		this.listbox.className = 'search-suggestions';
		this.listbox.id = `search-suggestions-${ItsoftCategorySuggestions.counter++}`;
		this.listbox.setAttribute('role', 'listbox');
		this.listbox.setAttribute('aria-label', this.settings().categoryLabel || 'Категории');
		this.input.setAttribute('autocomplete', 'off');
		this.input.setAttribute('aria-autocomplete', 'list');
		this.input.setAttribute('aria-controls', this.listbox.id);
		this.input.setAttribute('aria-expanded', 'false');
		this.form.appendChild(this.listbox);

		this.input.addEventListener('input', () => this.schedule());
		this.input.addEventListener('keydown', (event) => this.handleKeydown(event));
		this.input.addEventListener('focus', () => {
			if (this.input.value.trim().length >= 2) this.schedule();
		});
		this.listbox.addEventListener('pointerdown', (event) => event.preventDefault());
		document.addEventListener('pointerdown', (event) => {
			if (!this.form.contains(event.target)) this.close();
		});
	}

	settings() {
		return window.ITSOFT_SEARCH_SUGGESTIONS?.i18n || {};
	}

	schedule() {
		window.clearTimeout(this.timer);
		const query = this.input.value.trim();

		if (query.length < 2) {
			this.close();
			return;
		}

		this.timer = window.setTimeout(() => this.render(query), 250);
	}

	normalize(value) {
		return value.toLocaleLowerCase().replace(/ё/g, 'е');
	}

	render(query) {
		const needle = this.normalize(query);
		this.matches = this.categories
			.filter((category) => this.normalize(category.search || category.name).includes(needle))
			.slice(0, 8);
		this.activeIndex = -1;
		this.listbox.replaceChildren();

		if (!this.matches.length) {
			this.close();
			return;
		}

		const label = document.createElement('div');
		label.className = 'search-suggestions__label';
		label.textContent = this.settings().categoryLabel || 'Категории';
		this.listbox.appendChild(label);

		this.matches.forEach((category, index) => {
			const option = document.createElement('a');
			option.className = 'search-suggestions__option';
			option.href = category.url;
			option.id = `${this.listbox.id}-option-${index}`;
			option.setAttribute('role', 'option');
			option.setAttribute('aria-selected', 'false');
			option.textContent = category.name;
			this.listbox.appendChild(option);
		});

		this.listbox.classList.add('search-suggestions--open');
		this.input.setAttribute('aria-expanded', 'true');
	}

	handleKeydown(event) {
		if (!this.listbox.classList.contains('search-suggestions--open')) return;

		if (event.key === 'Escape') {
			event.preventDefault();
			this.close();
			return;
		}

		if (!['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return;

		if (event.key === 'Enter') {
			if (this.activeIndex < 0) return;
			event.preventDefault();
			window.location.assign(this.matches[this.activeIndex].url);
			return;
		}

		event.preventDefault();
		const direction = event.key === 'ArrowDown' ? 1 : -1;
		this.activeIndex = (this.activeIndex + direction + this.matches.length) % this.matches.length;
		this.updateSelection();
	}

	updateSelection() {
		this.listbox.querySelectorAll('[role="option"]').forEach((option, index) => {
			const isActive = index === this.activeIndex;
			option.setAttribute('aria-selected', String(isActive));
			if (isActive) {
				this.input.setAttribute('aria-activedescendant', option.id);
				option.scrollIntoView({ block: 'nearest' });
			}
		});
	}

	close() {
		window.clearTimeout(this.timer);
		this.activeIndex = -1;
		if (!this.listbox) return;
		this.listbox.classList.remove('search-suggestions--open');
		this.input.setAttribute('aria-expanded', 'false');
		this.input.removeAttribute('aria-activedescendant');
	}
}

ItsoftCategorySuggestions.counter = 1;

document.addEventListener('DOMContentLoaded', () => {
	const categories = window.ITSOFT_SEARCH_SUGGESTIONS?.categories || [];
	if (!categories.length) return;

	document.querySelectorAll('form[role="search"], .catalog-nav__search').forEach((form) => {
		new ItsoftCategorySuggestions(form, categories).init();
	});
});
