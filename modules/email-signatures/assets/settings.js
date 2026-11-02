/**
 * Settings page: draws the social icons for every template from the rows as
 * they stand, in each template's icon style, and sends them with the form.
 * The strip above the rows shows them in the template the site uses.
 */
(function ($) {
	'use strict';

	var cfg = window.sbEmailsigSettings || {};
	var templates = cfg.templates || {};

	// Custom SVGs by attachment id: the saved ones, then any picked here
	// (fetched once each). null while one is being fetched.
	var svgs = cfg.svgs || {};

	function customSvg(id) {
		if (!id) return '';
		if (Object.prototype.hasOwnProperty.call(svgs, id)) return svgs[id];

		svgs[id] = null;
		$.post(cfg.ajax, { action: 'sb_emailsig_svg', nonce: cfg.svgNonce, id: id }).always(function (res) {
			svgs[id] = res && res.success ? res.data : '';
			lastState = '';
		});
		return null;
	}

	var $wrap, $preview, $status, $input;
	var lastState = '';
	var renderId = 0;

	function field(key) {
		return $('.acf-field[data-key="field_sb_emailsig_' + key + '"]').first();
	}

	function readRows() {
		var rows = [];
		field('social_media').find('.acf-row').not('.acf-clone').each(function () {
			var $row = $(this);
			var icon = $row.find('[data-key="field_sb_emailsig_social_media_icon"] select').val() || '';
			var file = $row.find('[data-key="field_sb_emailsig_social_media_icon_file"] input[type="hidden"]').first().val() || '';
			var name = $row.find('[data-key="field_sb_emailsig_social_media_name"] input').val() || '';
			rows.push({
				name: name,
				custom: icon === 'custom',
				svg: icon === 'custom' ? customSvg(file) : (cfg.builtin && cfg.builtin[icon]) || ''
			});
		});
		return rows;
	}

	/** ACF shows a file icon for an SVG: show the SVG itself. */
	function showSvgs() {
		field('social_media').find('[data-key="field_sb_emailsig_social_media_icon_file"] .acf-file-uploader').each(function () {
			var $img = $(this).find('img[data-name="icon"]');
			var url = $(this).find('a[data-name="filename"]').attr('href') || '';
			if ($img.length && /\.svg(\?|$)/i.test(url) && $img.attr('src') !== url) {
				$img.attr('src', url).addClass('sb-emailsig-svg-thumb');
			}
		});
	}

	/** A colour box's value as hex: a hex, or a theme colour looked up; '' if neither. */
	function colourHex(value) {
		value = (value || '').trim().toLowerCase();
		if (/^--[a-z0-9_-]+$/.test(value)) value = 'var(' + value + ')';
		if (/^#[0-9a-f]{6}$/.test(value)) return value;
		if (/^#[0-9a-f]{3}$/.test(value)) return '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3];
		var all = cfg.colours || {};
		return Object.prototype.hasOwnProperty.call(all, value) ? all[value] : '';
	}

	/** The brand colours as the Branding tab has them now (blank: the default; Accent: the primary). */
	function readBrand() {
		var out = {};
		Object.keys(cfg.brand || {}).forEach(function (key) {
			var box = field('brand_' + key).find('input[type="text"]').val() || '';
			out[key] = colourHex(box) || cfg.brand[key] || '';
		});
		if (!out.accent) out.accent = out.primary;
		// Brand Primary's and Accent's shades, as SB_EmailSig_Styles::brand_all() makes them.
		['primary', 'accent'].forEach(function (key) {
			Object.keys(SHADES).forEach(function (s) {
				out[key + '_' + s] = shade(out[key], SHADES[s][0], SHADES[s][1]);
			});
		});
		return out;
	}

	/** SB_EmailSig_Styles::SHADES: mixed with white or black, by how much. */
	var SHADES = { lighter: ['#ffffff', 0.6], light: ['#ffffff', 0.3], dark: ['#000000', 0.2], darker: ['#000000', 0.4] };

	/** $hex mixed with $to by t, as SB_EmailSig_Styles::mix() does. */
	function shade(hex, to, t) {
		if (!/^#[0-9a-f]{6}$/i.test(hex || '')) return hex || '';
		var out = '#';
		for (var i = 1; i < 7; i += 2) {
			var x = parseInt(hex.substr(i, 2), 16);
			var y = parseInt(to.substr(i, 2), 16);
			var c = Math.round(x + (y - x) * t);
			out += (c < 16 ? '0' : '') + c.toString(16);
		}
		return out;
	}

	function render() {
		showSvgs();
		var rows = readRows();
		var brand = readBrand();
		var state = JSON.stringify([rows, brand]);
		if (state === lastState) return Promise.resolve();
		lastState = state;

		var id = ++renderId;
		var ids = Object.keys(templates);
		// A colour that comes from a brand colour takes the one in the form now.
		var pick = function (src, saved) { return src && brand[src] ? brand[src] : saved; };

		return Promise.all(ids.map(function (tpl) {
			var st = templates[tpl];
			var src = st.src || {};
			var social = st.size ? Promise.all(rows.map(function (row) {
				if (row.svg === null) return Promise.resolve(null);
				return SBEmailSigIcons.png(row.svg, { size: st.size, colour: pick(src.colour, st.colour), bg: st.bg ? pick(src.bg, st.bg) : '', radius: st.radius });
			})) : Promise.resolve([]);
			var c = st.contact;
			var names = c ? Object.keys(c.svgs || {}) : [];
			var contact = Promise.all(names.map(function (n) {
				return SBEmailSigIcons.png(c.svgs[n], { size: c.size, colour: pick(c.src, c.colour), bg: c.bg ? pick(c.bgsrc, c.bg) : '', radius: c.radius || 0 });
			}));
			return Promise.all([social, contact]).then(function (res) {
				var set = { social: res[0] };
				if (names.length) {
					set.contact = {};
					names.forEach(function (n, i) { set.contact[n] = res[1][i]; });
				}
				return set;
			});
		})).then(function (done) {
			if (id !== renderId) return;

			var payload = {};
			var sets = [];
			ids.forEach(function (tpl, i) { payload[tpl] = done[i]; sets[i] = done[i].social; });
			$input.val(JSON.stringify(payload));

			// The strip shows the site's template.
			var shown = sets[ids.indexOf(cfg.current)] || sets[0] || [];
			var st = templates[cfg.current] || templates[ids[0]] || { size: 26, gap: 6 };
			var problems = [];

			$preview.empty();
			shown.forEach(function (png, i) {
				if (png) {
					$('<img alt="">').attr({ src: png, width: st.size, height: st.size, 'data-sb-tip': rows[i].name })
						.css({ width: st.size + 'px', height: st.size + 'px', marginRight: st.gap + 'px' })
						.appendTo($preview);
				} else if (rows[i].svg !== null) {
					problems.push('row ' + (i + 1) + (rows[i].name ? ' (' + rows[i].name + ')' : '') + (rows[i].custom ? ': choose an SVG file' : ''));
				}
			});
			$status.text(problems.length ? 'No icon for ' + problems.join(', ') + '.' : '')
				.toggleClass('is-error', problems.length > 0);
		});
	}

	$(function () {
		$wrap = field('social_preview').find('.acf-input');
		if (!$wrap.length || !window.SBEmailSigIcons) return;

		var form = $wrap.closest('form')[0];

		// No names until the form is sent, so the framework's unsaved changes
		// check (save-state.js) doesn't count them as a change.
		$preview = $('<div class="sb-emailsig-social-preview"></div>').appendTo($wrap);
		$status = $('<p class="sb-emailsig-social-status"></p>').appendTo($wrap);
		$input = $('<input type="hidden" value="">').appendTo($wrap);
		var $nonce = $('<input type="hidden">').val(cfg.nonce || '').appendTo($wrap);

		if (form) {
			form.addEventListener('submit', function () {
				$input.attr('name', 'sb_emailsig_icons');
				$nonce.attr('name', 'sb_emailsig_icons_nonce');
			}, true);

			// A save ACF stopped (a field to fix): nameless again, or they
			// count as a change that putting things back never undoes.
			form.addEventListener('sb:save-failed', function () {
				$input.removeAttr('name');
				$nonce.removeAttr('name');
			});
		}

		// Anything the user does, as against what ACF and this script do on load.
		var touched = false;
		if (form) {
			['input', 'change'].forEach(function (type) {
				form.addEventListener(type, function (e) {
					if (e.isTrusted) touched = true;
				}, true);
			});
		}

		// Drag sorting and adding or removing rows don't all fire change
		// events, so check for changes on a short interval.
		render().then(function () {
			// The page has settled: mark it saved, unless the user has started.
			if (form && !touched) form.dispatchEvent(new Event('sb:saved'));
		});
		setInterval(render, 500);
	});
})(jQuery);

/* Emails tab: click a merge tag to copy it (SB_EmailSig_Notify::render_tags()). */
(function () {
	'use strict';

	function copy(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			return navigator.clipboard.writeText(text);
		}
		var t = document.createElement('textarea');
		t.value = text;
		t.style.position = 'fixed';
		t.style.opacity = '0';
		document.body.appendChild(t);
		t.select();
		document.execCommand('copy');
		document.body.removeChild(t);
		return Promise.resolve();
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('[data-sb-emailsig-tag]') : null;
		if (!btn || btn.disabled) return;
		e.preventDefault();

		var tag = btn.getAttribute('data-sb-emailsig-tag');

		// Copied shows in its tip, so the tag keeps its width (no layout shift).
		copy(tag).then(function () {
			var tip = btn.getAttribute('data-sb-tip');
			btn.setAttribute('data-sb-tip', 'Copied');
			btn.classList.add('is-copied');
			setTimeout(function () {
				if (tip) btn.setAttribute('data-sb-tip', tip); else btn.removeAttribute('data-sb-tip');
				btn.classList.remove('is-copied');
			}, 1200);
		});
	});
})();

/* Tracking tab: Reset Google Analytics Settings puts the fields back to the
   defaults in the form, shown only while they differ (Tracking::render_reset()). */
(function () {
	'use strict';

	function input(key) {
		return document.querySelector('.acf-field[data-key="field_sb_emailsig_' + key + '"] .acf-input input:not([type="hidden"])');
	}

	function fire(el) {
		el.dispatchEvent(new Event('input', { bubbles: true }));
		el.dispatchEvent(new Event('change', { bubbles: true }));
	}

	document.addEventListener('DOMContentLoaded', function () {
		var btn = document.querySelector('[data-sb-emailsig-utm-reset]');
		if (!btn) return;

		var field = btn.closest('.acf-field');
		var defaults = {};
		try { defaults = JSON.parse(btn.getAttribute('data-sb-emailsig-utm-reset')) || {}; } catch (e) { return; }

		var differs = function () {
			return Object.keys(defaults).some(function (key) {
				var el = input(key);
				if (!el) return false;
				return el.type === 'checkbox' ? el.checked !== !!defaults[key] : el.value.trim() !== String(defaults[key]);
			});
		};

		var show = function () {
			var on = differs();
			btn.hidden = !on;
			if (field) field.classList.toggle('is-default', !on);
		};

		btn.addEventListener('click', function (e) {
			e.preventDefault();
			Object.keys(defaults).forEach(function (key) {
				var el = input(key);
				if (!el) return;
				if (el.type === 'checkbox') {
					if (el.checked !== !!defaults[key]) { el.checked = !!defaults[key]; fire(el); }
				} else if (el.value !== String(defaults[key])) {
					el.value = String(defaults[key]);
					fire(el);
				}
			});
			show();
		});

		var form = btn.closest('form') || document;
		form.addEventListener('input', show, true);
		form.addEventListener('change', show, true);
		show();
	});
})();

/* Company tab: the Default Template is always one people can use, so its box
   in Templates is ticked and locked (Sam, 30 October 2026). Ticked without
   an event, so the page doesn't count it as a change; it stays ticked when
   it stops being the default one. */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var select = document.querySelector('.acf-field[data-key="field_sb_emailsig_main_template"] select');
		var list = document.querySelector('.acf-field[data-key="field_sb_emailsig_templates"]');
		if (!select || !list) return;

		var lock = function () {
			list.querySelectorAll('input[type="checkbox"]').forEach(function (box) {
				var main = box.value === select.value;
				var label = box.closest('label');
				if (main) box.checked = true;
				box.setAttribute('aria-disabled', main ? 'true' : 'false');
				if (label) {
					label.classList.toggle('is-locked', main);
					if (main) label.setAttribute('data-sb-tip', 'The Default Template, always available'); else label.removeAttribute('data-sb-tip');
				}
			});
		};

		// Not disabled, so it's still sent with the form: a click just does nothing.
		list.addEventListener('click', function (e) {
			var box = e.target.closest('label') ? e.target.closest('label').querySelector('input[type="checkbox"]') : null;
			if (box && box.value === select.value) e.preventDefault();
		}, true);

		// jQuery's too, as ACF's select2 sends its change through jQuery.
		if (window.jQuery) window.jQuery(select).on('change', lock); else select.addEventListener('change', lock);
		lock();
	});
})();

/* Company tab: Merge Tag Fields lists only the chosen Team Member Post
   Type's fields (SB_EmailSig_Staff::field_map()); changing the type unticks
   the other type's. */
(function ($) {
	'use strict';

	$(function () {
		var map = (window.sbEmailsigSettings || {}).staffFields || {};
		var $type = $('.acf-field[data-key="field_sb_emailsig_staff_type"] select');
		var $list = $('.acf-field[data-key="field_sb_emailsig_staff_fields"]');
		if (!$type.length || !$list.length) return;

		function fit(changed) {
			var names = map[$type.val()] || [];
			var any = false;
			$list.find('input[type="checkbox"]').each(function () {
				var mine = names.indexOf(this.value) !== -1;
				$(this).closest('li').toggle(mine);
				if (!mine && changed && this.checked) { this.checked = false; $(this).trigger('change'); }
				if (mine) any = true;
			});
			$list.toggleClass('sb-emailsig-no-fields', !any);
		}

		$type.on('change', function () { fit(true); });
		fit(false);
	});
})(jQuery);

/* Branding tab: each brand colour box gets the framework's swatch and the
   theme colour menu, as on Edit styles (SB_EmailSig_Styles::palette()).
   Additional Buttons' Colour boxes get the same (Sam, 2 November 2026),
   their menu with the company's brand colours too (buttonPalette), a blank
   one showing Brand Primary dashed: blank is the template's colour. */
(function ($) {
	'use strict';

	var cfg = window.sbEmailsigSettings || {};
	var palette = cfg.palette || [];
	var buttonPalette = cfg.buttonPalette || palette;
	var all = cfg.colours || {};
	var $menu = null;
	var menuFor = null;

	function hexOf(value) {
		value = (value || '').trim().toLowerCase();
		if (/^--[a-z0-9_-]+$/.test(value)) value = 'var(' + value + ')';
		if (/^#[0-9a-f]{6}$/.test(value)) return value;
		if (/^#[0-9a-f]{3}$/.test(value)) return '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3];
		if (Object.prototype.hasOwnProperty.call(all, value)) return all[value];
		for (var i = 0; i < buttonPalette.length; i++) {
			if (buttonPalette[i].value === value) return buttonPalette[i].hex;
		}
		return '';
	}

	/** A colour box: the field's text input with a swatch before it and the colour menu button after. */
	function box(input, list) {
		var $wrap = $('<span class="sb-tweaks-colour sb-emailsig-colour"></span>');
		var swatch = $('<input type="color" class="sb-tweaks-colour__swatch" aria-label="Pick a colour">')[0];
		var button = $('<button type="button" class="sb-emailsig-colour__theme" aria-label="Theme colours" data-sb-tip="Theme colours" aria-expanded="false"><span class="dashicons dashicons-art" aria-hidden="true"></span></button>')[0];

		$(input).addClass('sb-tweaks-colour__value code').attr({ spellcheck: 'false', autocomplete: 'off' });
		$(input).before($wrap);
		$wrap.append(swatch, input);
		if (list.length) $wrap.append(button);

		swatch.addEventListener('input', function () {
			if (input.value !== swatch.value) setValue(input, swatch.value);
		});
		button.addEventListener('click', function (e) {
			e.preventDefault();
			if (menuFor === button) closeMenu(); else openMenu(button, input, list);
		});

		return { input: input, swatch: swatch, $wrap: $wrap };
	}

	/** A box's swatch shows its own colour, or the fallback dashed. */
	function show(b, fallback) {
		var own = hexOf(b.input.value);
		var shown = own || fallback;
		b.$wrap.toggleClass('is-inherited', !own);
		b.$wrap.toggleClass('is-unknown', b.input.value.trim() !== '' && !own);
		if (b.swatch.value !== shown) b.swatch.value = shown;
	}

	/** Additional Buttons' Colour boxes, including rows added later (not ACF's .acf-clone row). */
	function buttonBoxes(root) {
		$(root).find('.sb-emailsig-button-colour .acf-input input[type="text"]').each(function () {
			if (this._sbColour || $(this).closest('.acf-clone').length) return;
			var b = box(this, buttonPalette);
			this._sbColour = b;
			var paint = function () { show(b, cfg.buttonDefault || '#000000'); };
			this.addEventListener('input', paint);
			paint();
		});
	}

	function setValue(input, value) {
		input.value = value;
		input.dispatchEvent(new Event('input', { bubbles: true }));
		input.dispatchEvent(new Event('change', { bubbles: true }));
	}

	function closeMenu() {
		if ($menu) { $menu.remove(); $menu = null; }
		if (menuFor) { menuFor.setAttribute('aria-expanded', 'false'); menuFor = null; }
	}

	function openMenu(button, input, list) {
		var rect = button.getBoundingClientRect();
		list = list || palette;
		closeMenu();
		$menu = $('<div class="sb-emailsig-palette" role="listbox"></div>');
		list.forEach(function (c, i) {
			if (c.group && (i === 0 || list[i - 1].group !== c.group)) $('<div class="sb-emailsig-palette__head"></div>').text(c.group).appendTo($menu);
			$('<button type="button" role="option"></button>')
				.append($('<i></i>').css('background', c.hex))
				.append($('<span></span>').text(c.name))
				.append($('<code></code>').text(c.hex))
				.on('click', function () {
					setValue(input, c.value);
					closeMenu();
					input.focus();
				})
				.appendTo($menu);
		});
		$menu.appendTo(document.body).css({
			top: rect.bottom + window.scrollY + 4,
			left: Math.max(8, Math.min(rect.left + window.scrollX, window.scrollX + document.documentElement.clientWidth - 268))
		});
		menuFor = button;
		button.setAttribute('aria-expanded', 'true');
		$menu.find('button').first().trigger('focus');
	}

	$(function () {
		var boxes = [];

		// Menus close on a click elsewhere, Escape, a resize or a scroll.
		$(document).on('mousedown', function (e) {
			if ($menu && !$(e.target).closest('.sb-emailsig-palette, .sb-emailsig-colour__theme').length) closeMenu();
		});
		$(document).on('keydown', function (e) {
			if (e.key === 'Escape' && $menu) {
				var back = menuFor;
				closeMenu();
				if (back) back.focus();
			}
		});
		$(window).on('resize scroll', closeMenu);

		buttonBoxes(document);
		if (window.acf && window.acf.addAction) {
			window.acf.addAction('append', function ($el) { buttonBoxes($el); });
		}

		$('.sb-emailsig-brand-colour').each(function () {
			var input = $(this).find('.acf-input input[type="text"]')[0];
			if (!input) return;

			var b = box(input, palette);
			b.key = ($(this).attr('data-name') || '').replace('sb_emailsig_brand_', '');
			boxes.push(b);
		});

		if (!boxes.length) return;

		// Each swatch shows what its box comes out as: blank is the default
		// (Accent: the primary), shown dashed like Edit styles' defaults.
		var paint = function () {
			var primary = '';
			boxes.forEach(function (b) {
				if (b.key === 'primary') primary = hexOf(b.input.value) || (cfg.brand || {}).primary || '#000000';
			});
			boxes.forEach(function (b) {
				show(b, b.key === 'accent' ? primary : ((cfg.brand || {})[b.key] || '#000000'));
			});
		};

		boxes.forEach(function (b) { b.input.addEventListener('input', paint); });
		paint();
	});
})(jQuery);

/* Open a tab, and a field on it, from the address (Sam, 1 November 2026):
   ?sb_tab=company&sb_field=company_email, from Person Email's note in an
   Info List on the Layout page. */
(function ($) {
	'use strict';

	if (!window.acf) return;

	acf.addAction('ready', function () {
		var q = new URLSearchParams(window.location.search);
		var tab = (q.get('sb_tab') || '').replace(/[^a-z_]/g, '');
		var field = (q.get('sb_field') || '').replace(/[^a-z_]/g, '');

		if (tab) $('.acf-tab-button[data-key="field_sb_emailsig_tab_' + tab + '"]').first().trigger('click');
		if (!field) return;

		var $f = $('.acf-field[data-key="field_sb_emailsig_' + field + '"]').first();
		if (!$f.length) return;
		setTimeout(function () {
			$f[0].scrollIntoView({ block: 'center' });
			$f.find('input, textarea, select').first().trigger('focus');
		}, 60);
	});
})(jQuery);

/* Footer Text (Sam, 2 November 2026): Bold and Link only (the SB Footer
   toolbar), nothing else kept when pasted, a short box, and typing counts
   as a change straight away (ACF's editor only passes it on when it loses
   focus, and as a jQuery event the unsaved changes check doesn't hear). */
(function () {
	'use strict';

	var KEY = 'field_sb_emailsig_fine_print';

	if (!window.acf || !window.acf.addFilter) return;

	function isFooter(field) {
		return !!(field && field.get && field.get('key') === KEY);
	}

	window.acf.addFilter('wysiwyg_tinymce_settings', function (init, id, field) {
		if (!isFooter(field)) return init;
		init.valid_elements = 'p,br,strong/b,em/i,a[href]';
		// Only the SB Footer toolbar's buttons (Bold, Italic, Link, Unlink), whatever other plugins add.
		init.toolbar1 = 'bold,italic,link,unlink';
		init.toolbar2 = init.toolbar3 = init.toolbar4 = '';
		init.height = 150;
		return init;
	});

	window.acf.addAction('wysiwyg_tinymce_init', function (ed, id, init, field) {
		if (!isFooter(field)) return;
		ed.on('input change undo redo', function () {
			ed.save();
			var box = document.getElementById(id);
			if (box) box.dispatchEvent(new Event('input', { bubbles: true }));
		});
	});
})();
