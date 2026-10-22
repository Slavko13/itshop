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

	function initAccountNavigation() {
		var select = document.querySelector('[data-account-nav-select]');
		if (!select) {
			return;
		}

		select.addEventListener('change', function () {
			if (select.value) {
				window.location.assign(select.value);
			}
		});
	}

	function copyText(value) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(value);
		}

		return new Promise(function (resolve, reject) {
			var textarea = document.createElement('textarea');
			textarea.value = value;
			textarea.setAttribute('readonly', '');
			textarea.style.position = 'fixed';
			textarea.style.opacity = '0';
			document.body.appendChild(textarea);
			textarea.select();

			try {
				document.execCommand('copy') ? resolve() : reject(new Error('copy failed'));
			} catch (error) {
				reject(error);
			} finally {
				textarea.remove();
			}
		});
	}

	function initLicenseControls() {
		document.querySelectorAll('[data-license-control]').forEach(function (control) {
			var output = control.querySelector('[data-license-value]');
			var reveal = control.querySelector('[data-license-reveal]');
			var copy = control.querySelector('[data-license-copy]');
			var feedback = control.querySelector('[data-license-feedback]');

			if (!output) {
				return;
			}

			if (reveal) {
				reveal.addEventListener('click', function () {
					var isOpen = reveal.getAttribute('aria-pressed') === 'true';
					output.textContent = isOpen ? output.getAttribute('data-masked') : output.getAttribute('data-value');
					reveal.setAttribute('aria-pressed', isOpen ? 'false' : 'true');
					reveal.textContent = isOpen ? 'Показать' : 'Скрыть';
				});
			}

			if (copy) {
				copy.addEventListener('click', function () {
					copyText(output.getAttribute('data-value')).then(function () {
						if (feedback) feedback.textContent = 'Ключ скопирован';
						copy.textContent = 'Скопировано';
						window.setTimeout(function () {
							copy.textContent = 'Скопировать';
							if (feedback) feedback.textContent = '';
						}, 1800);
					}).catch(function () {
						if (feedback) feedback.textContent = 'Не удалось скопировать';
					});
				});
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-account-auth]').forEach(initAuthTabs);
		initPasswordToggles();
		initAccountNavigation();
		initLicenseControls();
	});
}());
