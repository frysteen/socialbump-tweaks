/**
 * Icon pickers that show the icons (Sam, 2 November 2026).
 *
 * The Icon fields (a company's Social Media rows, the company's and a
 * person's Additional Info List Items, Additional Buttons) are ACF selects,
 * which can't show pictures. Each gets a button showing the chosen icon and
 * its name, opening a menu of every icon under the select's group headings,
 * with a search box. The select stays in the form underneath, hidden: a pick
 * sets its value and sends a real change event, so saving, Unsaved changes,
 * the name auto-fill (autolabel.js) and ACF's conditional fields (Custom SVG)
 * all work as before. Rows added later (ACF's append) get one too; ACF's
 * hidden row to copy (.acf-clone) is left alone, so copies start clean.
 *
 * Icons come from sbEmailsigIconPicker.svgs (the template's icons/*.svg,
 * SB_EmailSig_Admin), drawn in the text colour.
 *
 * The Branding tab's Info List Icons (sbEmailsigIconPicker.defaults, field
 * name => default icon) get a reset button beside the picker, there but
 * hidden while the icon is the default, so nothing moves (Sam, 2 November
 * 2026).
 */
(function () {
	'use strict';

	var cfg = window.sbEmailsigIconPicker || {};
	var svgs = cfg.svgs || {};
	var defaults = cfg.defaults || {};
	var FIELDS = ['social_media_icon', 'info_icon', 'button_icon'].concat(Object.keys(defaults));
	// Lucide's rotate-ccw.
	var RESET = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>';
	var SEL = FIELDS.map(function (n) { return '.acf-field[data-name="' + n + '"] select'; }).join(', ');

	// Custom SVG: Lucide's upload. The xmlns matters: parsed as XML, an svg without it isn't drawn.
	var CUSTOM = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m17 8-5-5-5 5"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/></svg>';

	var open = null; // { menu, select, button }

	/** An icon's picture as an element: the SVG in the text colour, an empty box for None. */
	function picture(value) {
		var box = document.createElement('i');
		box.className = 'sb-icon-pick__img';
		box.setAttribute('aria-hidden', 'true');

		var src = value === 'custom' ? CUSTOM : (svgs[value] || '');
		if (!src) {
			box.classList.add('is-none');
			return box;
		}

		var doc = new DOMParser().parseFromString(src, 'image/svg+xml');
		var svg = doc.documentElement;
		if (!svg || svg.nodeName.toLowerCase() !== 'svg' || doc.getElementsByTagName('parsererror').length) {
			box.classList.add('is-none');
			return box;
		}
		if (!svg.getAttribute('viewBox')) svg.setAttribute('viewBox', '0 0 24 24');
		// Brand icons are filled and carry no colour of their own.
		if (!svg.getAttribute('fill')) svg.setAttribute('fill', 'currentColor');
		svg.removeAttribute('width');
		svg.removeAttribute('height');
		svg.removeAttribute('class');
		box.appendChild(document.importNode(svg, true));
		return box;
	}

	function label(select) {
		var o = select.options[select.selectedIndex];
		return o ? o.text.trim() : '';
	}

	/** Show the select's choice on its button. */
	function paint(select) {
		var button = select._sbIconPick;
		if (!button) return;
		var words = button.querySelector('.sb-icon-pick__text');
		var old = button.querySelector('.sb-icon-pick__img');
		button.replaceChild(picture(select.value), old);
		words.textContent = label(select);
		if (select._sbIconReset) select._sbIconReset.classList.toggle('is-default', select.value === select._sbIconDefault);
	}

	/** The field's default icon, for the Info List Icons; undefined for the rest. */
	function defaultOf(select) {
		var field = select.closest('.acf-field');
		var name = field ? field.getAttribute('data-name') : '';
		return Object.prototype.hasOwnProperty.call(defaults, name) ? defaults[name] : undefined;
	}

	function close(focus) {
		if (!open) return;
		var was = open;
		open.menu.remove();
		open.button.setAttribute('aria-expanded', 'false');
		open = null;
		document.removeEventListener('mousedown', outside, true);
		if (focus) was.button.focus();
	}

	function outside(e) {
		if (open && !open.menu.contains(e.target) && !open.button.contains(e.target)) close(false);
	}

	function pick(select, value) {
		if (select.value !== value) {
			select.value = value;
			select.dispatchEvent(new Event('input', { bubbles: true }));
			select.dispatchEvent(new Event('change', { bubbles: true }));
		}
		paint(select);
		close(true);
	}

	/** The options in the menu, under the select's group headings, filtered by the search. */
	function fill(list, select, words) {
		var q = (words || '').trim().toLowerCase();
		list.innerHTML = '';

		var add = function (o) {
			if (q && o.text.toLowerCase().indexOf(q) === -1) return null;
			var b = document.createElement('button');
			b.type = 'button';
			b.setAttribute('role', 'option');
			b.className = 'sb-icon-menu__opt' + (o.value === select.value ? ' is-current' : '');
			b.setAttribute('aria-selected', o.value === select.value ? 'true' : 'false');
			b.appendChild(picture(o.value));
			var t = document.createElement('span');
			t.textContent = o.text;
			b.appendChild(t);
			b.addEventListener('click', function () { pick(select, o.value); });
			list.appendChild(b);
			return b;
		};

		Array.prototype.forEach.call(select.children, function (child) {
			if (child.tagName === 'OPTGROUP') {
				var head = document.createElement('div');
				head.className = 'sb-icon-menu__head';
				head.textContent = child.label;
				list.appendChild(head);
				var any = false;
				Array.prototype.forEach.call(child.children, function (o) { if (add(o)) any = true; });
				if (!any) head.remove();
			} else if (child.tagName === 'OPTION') {
				add(child);
			}
		});

		if (!list.children.length) {
			var none = document.createElement('p');
			none.className = 'sb-icon-menu__empty';
			none.textContent = 'No icon called that.';
			list.appendChild(none);
		}
	}

	function show(select, button) {
		close(false);

		var menu = document.createElement('div');
		menu.className = 'sb-icon-menu';
		var search = document.createElement('input');
		search.type = 'search';
		search.className = 'sb-icon-menu__search';
		search.placeholder = 'Search icons';
		search.setAttribute('aria-label', 'Search icons');
		var list = document.createElement('div');
		list.className = 'sb-icon-menu__list';
		list.setAttribute('role', 'listbox');
		menu.appendChild(search);
		menu.appendChild(list);
		fill(list, select, '');

		document.body.appendChild(menu);
		var r = button.getBoundingClientRect();
		var width = Math.max(240, r.width);
		menu.style.width = width + 'px';
		menu.style.left = Math.max(8, Math.min(r.left + window.scrollX, window.scrollX + document.documentElement.clientWidth - width - 8)) + 'px';
		// Below the button, or above it when there's no room below.
		var below = window.innerHeight - r.bottom;
		var h = menu.offsetHeight;
		menu.style.top = (below < h + 8 && r.top > below ? r.top + window.scrollY - h - 4 : r.bottom + window.scrollY + 4) + 'px';

		open = { menu: menu, select: select, button: button };
		button.setAttribute('aria-expanded', 'true');
		document.addEventListener('mousedown', outside, true);

		var current = list.querySelector('.is-current');
		if (current) list.scrollTop = Math.max(0, current.offsetTop - list.clientHeight / 2);
		search.focus();

		search.addEventListener('input', function () { fill(list, select, search.value); });

		// Arrows move through the icons, Enter picks, Escape closes.
		menu.addEventListener('keydown', function (e) {
			var opts = Array.prototype.slice.call(list.querySelectorAll('.sb-icon-menu__opt'));
			var at = opts.indexOf(document.activeElement);
			if (e.key === 'Escape') {
				e.preventDefault();
				close(true);
			} else if (e.key === 'ArrowDown') {
				e.preventDefault();
				(opts[at + 1] || opts[0] || search).focus();
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				(at > 0 ? opts[at - 1] : search).focus();
			} else if (e.key === 'Enter' && document.activeElement === search && opts.length) {
				e.preventDefault();
				opts[0].click();
			} else if (e.key === 'Tab') {
				close(false);
			}
		});
	}

	function enhance(select) {
		if (select._sbIconPick || select.closest('.acf-clone') || select.multiple) return;

		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'sb-icon-pick';
		button.setAttribute('aria-haspopup', 'listbox');
		button.setAttribute('aria-expanded', 'false');
		var text = document.createElement('span');
		text.className = 'sb-icon-pick__text';
		button.appendChild(picture(''));
		button.appendChild(text);

		// The same height as the select it covers, so nothing moves.
		var h = select.offsetHeight;
		if (h) button.style.minHeight = h + 'px';

		select.classList.add('sb-icon-pick__native');
		select.setAttribute('tabindex', '-1');
		select.setAttribute('aria-hidden', 'true');
		select._sbIconPick = button;

		var def = defaultOf(select);
		if (def === undefined) {
			select.parentNode.insertBefore(button, select);
		} else {
			// The picker and its reset side by side.
			var row = document.createElement('span');
			row.className = 'sb-icon-pick-row';
			var reset = document.createElement('button');
			reset.type = 'button';
			reset.className = 'sb-icon-pick__reset';
			reset.setAttribute('aria-label', 'Back to the default icon');
			reset.setAttribute('data-sb-tip', 'Reset');
			reset.innerHTML = RESET;
			reset.addEventListener('click', function () {
				if (select.value !== def) {
					select.value = def;
					select.dispatchEvent(new Event('input', { bubbles: true }));
					select.dispatchEvent(new Event('change', { bubbles: true }));
				}
				paint(select);
				button.focus();
			});
			row.appendChild(button);
			row.appendChild(reset);
			select.parentNode.insertBefore(row, select);
			select._sbIconReset = reset;
			select._sbIconDefault = def;
		}

		button.addEventListener('click', function () {
			if (open && open.select === select) close(true);
			else show(select, button);
		});
		button.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				e.preventDefault();
				show(select, button);
			}
		});
		// Changed some other way (a reset, ACF): follow it.
		select.addEventListener('change', function () { paint(select); });

		paint(select);
	}

	function scan(root) {
		Array.prototype.forEach.call((root || document).querySelectorAll(SEL), enhance);
	}

	function start() {
		scan(document);
		if (window.acf && window.acf.addAction) {
			window.acf.addAction('append', function ($el) { scan($el && $el[0] ? $el[0] : document); });
		}
	}

	// The page scrolling moves the button away from the menu: close it (not the menu's own list scrolling).
	window.addEventListener('scroll', function (e) {
		if (open && !(e.target && e.target.nodeType === 1 && open.menu.contains(e.target))) close(false);
	}, true);
	window.addEventListener('resize', function () { if (open) close(false); });

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
	else start();
})();
