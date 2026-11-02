/**
 * Icon pickers in repeater rows fill the row's name (Sam, 2 November 2026):
 * a company's Social Media rows (Icon, then Name) and the company's and a
 * person's Additional Info List Items (Icon, then Label), and Additional
 * Buttons (Icon, then Label). Picking an icon
 * puts its name in an empty box. A name filled this way follows the icon
 * when it changes; one typed by hand is left alone. None and Custom SVG fill
 * nothing.
 */
(function () {
	'use strict';

	var PAIRS = {
		social_media_icon: 'social_media_name',
		info_icon: 'info_label',
		button_icon: 'button_label' // Additional Buttons (2 November 2026)
	};
	var SKIP = { '': 1, custom: 1 };

	function input(row, name) {
		var field = row.querySelector('.acf-field[data-name="' + name + '"]');
		return field ? field.querySelector('input[type="text"], input:not([type])') : null;
	}

	document.addEventListener('change', function (e) {
		var select = e.target;
		if (!select || select.tagName !== 'SELECT') return;

		var field = select.closest('.acf-field');
		var name = field ? field.getAttribute('data-name') : '';
		var row = field ? field.closest('.acf-row') : null;
		if (!row || !PAIRS[name]) return;

		var box = input(row, PAIRS[name]);
		if (!box) return;

		var auto = box.getAttribute('data-sb-auto');
		var typed = box.value.trim();

		// Only an empty box, or one this script filled and nobody has changed since.
		if (typed !== '' && (auto === null || typed !== auto)) return;

		var option = select.options[select.selectedIndex];
		var text = SKIP[select.value] || !option ? '' : option.text.trim();

		if (text === '' && auto === null) return;

		box.value = text;
		if (text === '') {
			box.removeAttribute('data-sb-auto');
		} else {
			box.setAttribute('data-sb-auto', text);
		}
		// So Unsaved changes and the live preview notice it.
		box.dispatchEvent(new Event('input', { bubbles: true }));
		box.dispatchEvent(new Event('change', { bubbles: true }));
	});

	// Typing in the box makes the name the person's own.
	document.addEventListener('input', function (e) {
		var box = e.target;
		if (!box || !box.hasAttribute || !box.hasAttribute('data-sb-auto') || !e.isTrusted) return;
		if (box.value.trim() !== box.getAttribute('data-sb-auto')) box.removeAttribute('data-sb-auto');
	});
})();
