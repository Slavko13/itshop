class ItsoftProductSuggestions {
	constructor(form, products, aliases) {
		this.form = form;
		this.input = form.querySelector('input[name="s"]');
		this.products = products;
		this.aliases = aliases;
		this.timer = null;
		this.activeIndex = -1;
		this.matches = [];
		this.listbox = null;
	}

	init() {
		if (!this.input || this.input.dataset.suggestionsReady === 'true') return;

		this.input.dataset.suggestionsReady = 'true';
		this.listbox = document.createElement('div');
		this.listbox.className = 'search-suggestions search-suggestions--products';
		this.listbox.id = `search-suggestions-${ItsoftProductSuggestions.counter++}`;
		this.listbox.setAttribute('role', 'listbox');
		this.listbox.setAttribute('aria-label', this.settings().productsLabel || 'Товары');
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

		this.timer = window.setTimeout(() => this.render(query), 160);
	}

	normalize(value) {
		return String(value || '')
			.toLocaleLowerCase()
			.replace(/ё/g, 'е')
			.replace(/([\p{L}])(\p{N})|(\p{N})([\p{L}])/gu, '$1$3 $2$4')
			.replace(/[^\p{L}\p{N}]+/gu, ' ')
			.trim();
	}

	keyboardSwap(value, from, to) {
		const target = Array.from(to);
		const map = new Map(Array.from(from).map((character, index) => [character, target[index]]));
		return Array.from(String(value || '').toLocaleLowerCase()).map((character) => map.get(character) || character).join('');
	}

	queryVariants(query) {
		const variants = [
			query,
			this.keyboardSwap(query, 'йцукенгшщзхъфывапролджэячсмитьбю', "qwertyuiop[]asdfghjkl;'zxcvbnm,."),
			this.keyboardSwap(query, "qwertyuiop[]asdfghjkl;'zxcvbnm,.", 'йцукенгшщзхъфывапролджэячсмитьбю'),
		];

		return [...new Set(variants.map((variant) => this.tokens(variant).join('|')).filter(Boolean))]
			.map((variant) => variant.split('|'));
	}

	tokens(value) {
		const stopTokens = new Set([
			'ключ', 'ключи', 'ключа', 'лицензия', 'лицензии', 'лицензионный',
			'активация', 'активации', 'активировать', 'купить', 'для', 'электронный',
		]);

		return this.normalize(value)
			.split(/\s+/u)
			.filter(Boolean)
			.map((token) => this.resolveAlias(token))
			.filter((token) => !stopTokens.has(token));
	}

	resolveAlias(token) {
		if (this.aliases[token]) return this.aliases[token];
		if (Array.from(token).length < 4) return token;

		const tokenIsCyrillic = /\p{Script=Cyrillic}/u.test(token);
		const limit = Array.from(token).length >= 8 ? 2 : 1;
		let bestDistance = limit + 1;
		let bestValue = token;

		Object.entries(this.aliases).forEach(([alias, canonical]) => {
			if (/\p{Script=Cyrillic}/u.test(alias) !== tokenIsCyrillic) return;
			if (Math.abs(Array.from(alias).length - Array.from(token).length) > limit) return;

			const distance = this.levenshtein(token, alias);
			if (distance < bestDistance) {
				bestDistance = distance;
				bestValue = canonical;
			}
		});

		return bestDistance <= limit ? bestValue : token;
	}

	levenshtein(left, right) {
		const leftChars = Array.from(left);
		const rightChars = Array.from(right);
		let previous = Array.from({ length: rightChars.length + 1 }, (_, index) => index);

		leftChars.forEach((leftChar, leftIndex) => {
			const current = [leftIndex + 1];
			rightChars.forEach((rightChar, rightIndex) => {
				current.push(Math.min(
					current[rightIndex] + 1,
					previous[rightIndex + 1] + 1,
					previous[rightIndex] + (leftChar === rightChar ? 0 : 1),
				));
			});
			previous = current;
		});

		return previous[rightChars.length];
	}

	scoreToken(needle, haystack) {
		if (haystack.includes(needle)) return 1200;
		if (needle.length >= 3 && haystack.some((token) => token.startsWith(needle) || (token.length >= 4 && needle.startsWith(token)))) return 560;
		if (needle.length < 4) return 0;

		const limit = needle.length >= 8 ? 2 : 1;
		return haystack.some((token) => Math.abs(token.length - needle.length) <= limit && this.levenshtein(needle, token) <= limit) ? 240 : 0;
	}

	scoreProduct(product, query) {
		const haystack = [...new Set(this.tokens(product.search || product.name))];
		const title = this.tokens(product.name);
		let best = 0;

		this.queryVariants(query).forEach((queryTokens) => {
			let score = 0;
			for (const token of queryTokens) {
				if (/^\d+$/.test(token) && !haystack.includes(token)) return;
				const tokenScore = this.scoreToken(token, haystack);
				if (!tokenScore) return;
				score += tokenScore;
			}

			const fullQuery = queryTokens.join(' ');
			const fullTitle = title.join(' ');
			if (fullTitle === fullQuery) score += 8000;
			else if (fullTitle.startsWith(fullQuery)) score += 4200;
			else if (` ${fullTitle} `.includes(` ${fullQuery} `)) score += 2600;
			best = Math.max(best, score);
		});

		return best;
	}

	render(query) {
		this.matches = this.products
			.map((product) => ({ ...product, score: this.scoreProduct(product, query) }))
			.filter((product) => product.score > 0)
			.sort((left, right) => right.score - left.score || left.name.localeCompare(right.name))
			.slice(0, 6);
		this.activeIndex = -1;
		this.listbox.replaceChildren();

		const label = document.createElement('div');
		label.className = 'search-suggestions__label';
		label.textContent = this.settings().productsLabel || 'Товары';
		this.listbox.appendChild(label);

		if (this.matches.length) {
			this.matches.forEach((product, index) => this.listbox.appendChild(this.createProductOption(product, index)));
			document.dispatchEvent(new CustomEvent('itsoft:search-suggestions-shown', {
				detail: {
					products: this.matches.map((product, index) => ({
						id: String(product.id),
						name: product.name,
						category: product.category || '',
						brand: 'Microsoft',
						list: 'Живой поиск',
						position: index + 1,
					})),
				},
			}));
		} else {
			const empty = document.createElement('div');
			empty.className = 'search-suggestions__empty';
			empty.textContent = this.settings().noResults || 'Подходящие товары не найдены';
			this.listbox.appendChild(empty);
		}

		const allResults = document.createElement('a');
		allResults.className = 'search-suggestions__all';
		allResults.href = this.resultsUrl(query);
		allResults.textContent = this.settings().allResults || 'Показать все результаты';
		allResults.insertAdjacentHTML('beforeend', '<span aria-hidden="true">→</span>');
		this.listbox.appendChild(allResults);

		this.listbox.classList.add('search-suggestions--open');
		this.input.setAttribute('aria-expanded', 'true');
	}

	createProductOption(product, index) {
		const option = document.createElement('a');
		option.className = 'search-suggestions__product';
		option.href = product.url;
		option.id = `${this.listbox.id}-option-${index}`;
		option.setAttribute('role', 'option');
		option.setAttribute('aria-selected', 'false');
		option.setAttribute('aria-label', `${this.settings().openProduct || 'Открыть товар'}: ${product.name}`);
		option.dataset.metrikaProduct = JSON.stringify({
			id: String(product.id),
			name: product.name,
			category: product.category || '',
			brand: 'Microsoft',
			list: 'Живой поиск',
			position: index + 1,
		});

		const media = document.createElement('span');
		media.className = 'search-suggestions__media';
		const image = document.createElement('img');
		image.src = product.image;
		image.alt = '';
		image.loading = 'lazy';
		image.decoding = 'async';
		media.appendChild(image);

		const content = document.createElement('span');
		content.className = 'search-suggestions__content';
		const title = document.createElement('strong');
		title.className = 'search-suggestions__title';
		title.textContent = product.name;
		content.appendChild(title);

		if (product.category) {
			const category = document.createElement('span');
			category.className = 'search-suggestions__category';
			category.textContent = product.category;
			content.appendChild(category);
		}

		if (product.priceHtml) {
			const price = document.createElement('span');
			price.className = 'search-suggestions__price';
			price.innerHTML = product.priceHtml;
			content.appendChild(price);
		}

		const arrow = document.createElement('span');
		arrow.className = 'search-suggestions__arrow';
		arrow.setAttribute('aria-hidden', 'true');
		arrow.textContent = '→';

		option.append(media, content, arrow);
		return option;
	}

	resultsUrl(query) {
		const url = new URL(this.form.action || window.location.href, window.location.origin);
		url.searchParams.set('s', query);
		return url.toString();
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

ItsoftProductSuggestions.counter = 1;

document.addEventListener('DOMContentLoaded', () => {
	const products = window.ITSOFT_SEARCH_SUGGESTIONS?.products || [];
	const aliases = window.ITSOFT_SEARCH_SUGGESTIONS?.aliases || {};
	if (!products.length) return;

	document.querySelectorAll('form[role="search"], .catalog-nav__search').forEach((form) => {
		new ItsoftProductSuggestions(form, products, aliases).init();
	});
});
