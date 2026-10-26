(function () {
	'use strict';

	function initAuthTabs(root) {
		var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-auth-tab]'));
		var panels = Array.prototype.slice.call(root.querySelectorAll('[data-auth-panel]'));

		if (!tabs.length || !panels.length) {
			return;
		}

		function activate(name, moveFocus) {
			tabs.forEach(function (tab) {
				var active = tab.getAttribute('data-auth-tab') === name;
				tab.classList.toggle('is-active', active);
				tab.setAttribute('aria-selected', active ? 'true' : 'false');
				tab.setAttribute('tabindex', active ? '0' : '-1');
			});

			panels.forEach(function (panel) {
				var active = panel.getAttribute('data-auth-panel') === name;
				panel.classList.toggle('is-active', active);
				panel.hidden = !active;

				if (active && moveFocus) {
					var field = panel.querySelector('input:not([type="hidden"])');
					if (field) {
						field.focus();
					}
				}
			});
		}

		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () {
				var name = tab.getAttribute('data-auth-tab');
				activate(name, true);
				if (window.history && window.history.replaceState) {
					window.history.replaceState(null, '', name === 'register' ? '#register' : window.location.pathname + window.location.search);
				}
			});

			tab.addEventListener('keydown', function (event) {
				if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
					return;
				}

				event.preventDefault();
				var index = tabs.indexOf(tab);
				var next = event.key === 'ArrowRight' ? index + 1 : index - 1;
				var target = tabs[(next + tabs.length) % tabs.length];
				activate(target.getAttribute('data-auth-tab'), false);
				target.focus();
			});
		});

		root.setAttribute('data-account-tabs-ready', 'true');
		activate(window.location.hash === '#register' ? 'register' : (root.getAttribute('data-initial-tab') || 'login'), false);
	}

	function initPasswordToggles() {
		document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
			button.addEventListener('click', function () {
				var field = document.getElementById(button.getAttribute('data-password-toggle'));
				if (!field) {
					return;
				}

				var reveal = field.type === 'password';
				field.type = reveal ? 'text' : 'password';
				button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
				button.setAttribute('aria-label', reveal ? 'Скрыть пароль' : 'Показать пароль');
			});
		});
	}

	function showRequiredFieldsNotice(form, fields) {
		var english = document.cookie.split(';').some(function (item) {
			return item.trim().indexOf('googtrans=/ru/en') === 0;
		}) || document.documentElement.classList.contains('translated-ltr') || document.querySelector('[data-gt-lang="en"].gt-current-lang');
		var englishLabels = {
			username: 'email or username',
			password: 'password',
			reg_username: 'username',
			reg_email: 'email',
			reg_password: 'password',
			user_login: 'email or username'
		};
		var labels = fields.map(function (field) {
			var label = form.querySelector('label[for="' + field.id + '"]');
			if (english && englishLabels[field.id]) return englishLabels[field.id];
			if (!label) return '';
			var cleanLabel = label.cloneNode(true);
			cleanLabel.querySelectorAll('.required, .screen-reader-text').forEach(function (node) { node.remove(); });
			return cleanLabel.textContent.trim().toLowerCase();
		}).filter(Boolean);
		var message = english
			? (labels.length ? 'Please complete the required fields: ' + labels.join(' and ') + '.' : 'Please complete the required fields.')
			: (labels.length ? 'Заполните обязательные поля: ' + labels.join(' и ') + '.' : 'Заполните обязательные поля.');
		var scope = form.closest('.woocommerce') || form.parentElement;
		var wrapper = scope ? scope.querySelector('.woocommerce-notices-wrapper') : null;

		if (!wrapper && scope) {
			wrapper = document.createElement('div');
			wrapper.className = 'woocommerce-notices-wrapper';
			scope.insertBefore(wrapper, scope.firstChild);
		}

		if (wrapper) {
			wrapper.innerHTML = '';
			var list = document.createElement('ul');
			list.className = 'woocommerce-error';
			list.setAttribute('role', 'alert');
			var item = document.createElement('li');
			item.textContent = message;
			list.appendChild(item);
			wrapper.appendChild(list);
			wrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}

		fields.forEach(function (field) {
			field.setAttribute('aria-invalid', 'true');
			field.addEventListener('input', function clearInvalid() {
				if (field.value.trim()) field.removeAttribute('aria-invalid');
			}, { once: true });
		});

		fields[0].focus();
	}

	function initRequiredFieldValidation() {
		document.querySelectorAll('.account-form').forEach(function (form) {
			form.addEventListener('submit', function (event) {
				var empty = Array.prototype.slice.call(form.querySelectorAll('[required]')).filter(function (field) {
					return !String(field.value || '').trim();
				});

				if (!empty.length) return;

				event.preventDefault();
				showRequiredFieldsNotice(form, empty);
			});
		});
	}

	function initAccountNavigation() {
		var select = document.querySelector('[data-account-nav-select]');
		if (!select) {
			return;
		}
		select.dataset.accountCurrentValue = select.value;

		select.addEventListener('change', function () {
			var selectedOption = select.options[select.selectedIndex];
			if (selectedOption && selectedOption.hasAttribute('data-account-logout-option')) {
				return;
			}
			if (select.value) {
				window.location.assign(select.value);
			}
		});
	}

	function initLogoutConfirmation() {
		var modal = document.querySelector('[data-account-logout-dialog]');
		if (!modal) {
			return;
		}

		var dialog = modal.querySelector('.account-logout-modal__dialog');
		var confirm = modal.querySelector('[data-account-logout-confirm]');
		var cancelButtons = Array.prototype.slice.call(modal.querySelectorAll('[data-account-logout-cancel]'));
		var logoutLinks = Array.prototype.slice.call(document.querySelectorAll('[data-account-logout]'));
		var select = document.querySelector('[data-account-nav-select]');
		var lastFocused = null;

		// The account navigation lives inside a sidebar that creates its own
		// stacking context. Move the dialog to the document root so its fixed
		// backdrop reliably covers the header, notices and account cards.
		if (modal.parentElement !== document.body) {
			document.body.appendChild(modal);
		}

		function openModal(url, trigger) {
			if (!url || !confirm) {
				return;
			}

			lastFocused = trigger || document.activeElement;
			confirm.href = url;
			modal.hidden = false;
			document.body.classList.add('account-logout-open');
			window.requestAnimationFrame(function () {
				modal.classList.add('is-open');
				var stayButton = modal.querySelector('.account-logout-modal__stay');
				(stayButton || dialog).focus();
			});
		}

		function closeModal() {
			modal.classList.remove('is-open');
			modal.hidden = true;
			document.body.classList.remove('account-logout-open');
			if (select && select.dataset.accountCurrentValue) {
				select.value = select.dataset.accountCurrentValue;
			}
			if (lastFocused && typeof lastFocused.focus === 'function') {
				lastFocused.focus();
			}
		}

		logoutLinks.forEach(function (link) {
			link.addEventListener('click', function (event) {
				event.preventDefault();
				openModal(link.href, link);
			});
		});

		if (select) {
			select.addEventListener('change', function () {
				var option = select.options[select.selectedIndex];
				if (option && option.hasAttribute('data-account-logout-option')) {
					openModal(option.value, select);
				}
			});
		}

		cancelButtons.forEach(function (button) {
			button.addEventListener('click', closeModal);
		});

		confirm.addEventListener('click', function () {
			confirm.setAttribute('aria-busy', 'true');
		});

		document.addEventListener('keydown', function (event) {
			if (modal.hidden || event.key !== 'Escape') {
				return;
			}
			event.preventDefault();
			closeModal();
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-account-auth]').forEach(initAuthTabs);
		initPasswordToggles();
		initRequiredFieldValidation();
		initAccountNavigation();
		initLogoutConfirmation();
	});
}());
