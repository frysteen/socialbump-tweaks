/**
 * A colour field outside the Edit styles page (a person's photo border,
 * Sam, 30 October 2026): the swatch, the value (hex, a brand colour or a
 * theme variable) and the theme colours menu, as on Edit styles
 * (styles.js). Fields: .sb-emailsig-colour[data-sb-colour]; a blank one
 * shows data-fallback-hex. Colours: sbEmailsigColours (palette, colours,
 * brand), from SB_EmailSig_Photo::assets().
 */
(function () {
	'use strict';

	var cfg = window.sbEmailsigColours || {};
	var palette = cfg.palette || [];
	var colours = cfg.colours || {};
	var menu = null;
	var menuFor = null;

	/** A saved colour as hex, the way the server works it out, or ''. */
	function hex(value) {
		value = (value || '').trim().toLowerCase();
		if (/^--[a-z0-9_-]+$/.test(value)) value = 'var(' + value + ')';
		if (/^#[0-9a-f]{6}$/.test(value)) return value;
		if (/^#[0-9a-f]{3}$/.test(value)) return '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3];
		if (Object.prototype.hasOwnProperty.call(colours, value)) return colours[value];
		var b = /^var\(--sb-brand-([a-z-]+)\)$/.exec(value);
		if (b) return (cfg.brand || {})[b[1].replace(/-/g, '_')] || '';
		for (var i = 0; i < palette.length; i++) {
			if (palette[i].value === value) return palette[i].hex;
		}
		return '';
	}

	function paint(wrap) {
		var input = wrap.querySelector('.sb-tweaks-colour__value');
		var swatch = wrap.querySelector('.sb-tweaks-colour__swatch');
		var own = hex(input.value);
		var shown = own || wrap.getAttribute('data-fallback-hex') || '';
		wrap.classList.toggle('is-inherited', !own);
		wrap.classList.toggle('is-unknown', input.value.trim() !== '' && !own);
		if (shown && swatch.value !== shown) swatch.value = shown;
	}

	function setValue(input, value) {
		input.value = value;
		input.dispatchEvent(new Event('input', { bubbles: true }));
		input.dispatchEvent(new Event('change', { bubbles: true }));
	}

	function closeMenu() {
		if (menu) {
			menu.remove();
			menu = null;
		}
		if (menuFor) {
			menuFor.setAttribute('aria-expanded', 'false');
			menuFor = null;
		}
	}

	function openMenu(button) {
		var input = button.closest('.sb-emailsig-colour').querySelector('.sb-tweaks-colour__value');
		var rect = button.getBoundingClientRect();

		closeMenu();
		menu = document.createElement('div');
		menu.className = 'sb-emailsig-palette';
		menu.setAttribute('role', 'listbox');

		palette.forEach(function (c, i) {
			// A line between the brand colours and the theme's.
			if (i > 0 && palette[i - 1].group === 'brand' && c.group !== 'brand') {
				var hr = document.createElement('hr');
				hr.className = 'sb-emailsig-palette__sep';
				menu.appendChild(hr);
			}
			var b = document.createElement('button');
			var dot = document.createElement('i');
			var name = document.createElement('span');
			var code = document.createElement('code');
			b.type = 'button';
			b.setAttribute('role', 'option');
			dot.style.background = c.hex;
			name.textContent = c.name;
			code.textContent = c.hex;
			b.appendChild(dot);
			b.appendChild(name);
			b.appendChild(code);
			b.addEventListener('click', function () {
				setValue(input, c.value);
				closeMenu();
				input.focus();
			});
			menu.appendChild(b);
		});

		document.body.appendChild(menu);
		menu.style.top = (rect.bottom + window.scrollY + 4) + 'px';
		menu.style.left = Math.max(8, Math.min(rect.left + window.scrollX, window.scrollX + document.documentElement.clientWidth - 268)) + 'px';
		menuFor = button;
		button.setAttribute('aria-expanded', 'true');
		var first = menu.querySelector('button');
		if (first) first.focus();
	}

	function init() {
		document.querySelectorAll('.sb-emailsig-colour[data-sb-colour]').forEach(function (wrap) {
			var input = wrap.querySelector('.sb-tweaks-colour__value');
			var swatch = wrap.querySelector('.sb-tweaks-colour__swatch');
			var theme = wrap.querySelector('.sb-emailsig-colour__theme');

			// Picking with the swatch writes its hex into the box.
			swatch.addEventListener('input', function () {
				if (input.value !== swatch.value) setValue(input, swatch.value);
			});
			input.addEventListener('input', function () { paint(wrap); });
			paint(wrap);

			if (palette.length && theme) {
				theme.hidden = false;
				theme.setAttribute('aria-expanded', 'false');
				theme.addEventListener('click', function (e) {
					e.preventDefault();
					if (menuFor === theme) closeMenu();
					else openMenu(theme);
				});
			}
		});

		document.addEventListener('mousedown', function (e) {
			if (menu && !e.target.closest('.sb-emailsig-palette, .sb-emailsig-colour__theme')) closeMenu();
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && menu) {
				var back = menuFor;
				closeMenu();
				if (back) back.focus();
			}
		});
		window.addEventListener('resize', closeMenu);
		window.addEventListener('scroll', closeMenu, true);
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
