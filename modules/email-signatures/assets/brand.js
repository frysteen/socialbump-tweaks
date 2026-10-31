/**
 * Brand colours (SB_EmailSig_Companies::swatches()), on the Companies list
 * and the Signatures page, for administrators: a click on a company's
 * swatches opens a popup with its four brand colours, each a colour picker,
 * a box for a hex or theme colour and the theme colour menu. Nothing changes
 * until Save (Sam, 30 October 2026): then brand_save keeps them, the
 * company's icons are redrawn in the new colours for every template (as
 * Save on its page does, settings.js) and brand_icons keeps those, and the
 * company's swatches on the page show the new colours.
 */
(function ($) {
	'use strict';

	var cfg = window.sbEmailsigBrand || {};
	var palette = cfg.palette || [];
	var colours = cfg.colours || {};

	function post(data) {
		return fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams(data) })
			.then(function (r) { return r.json(); });
	}

	function toast(msg, kind) {
		if (window.SBTweaksUI && window.SBTweaksUI.toast) window.SBTweaksUI.toast(msg, kind);
	}

	/** A box's value as hex: a hex, or a theme colour looked up; '' if neither. */
	function hexOf(value) {
		value = (value || '').trim().toLowerCase();
		if (/^--[a-z0-9_-]+$/.test(value)) value = 'var(' + value + ')';
		if (/^#[0-9a-f]{6}$/.test(value)) return value;
		if (/^#[0-9a-f]{3}$/.test(value)) return '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3];
		return Object.prototype.hasOwnProperty.call(colours, value) ? colours[value] : '';
	}

	/** Every template's social and contact icons for the company, as PNGs. */
	function draw(d) {
		var ids = Object.keys(d.templates || {});
		var brand = d.brand || {};
		var pick = function (src, saved) { return src && brand[src] ? brand[src] : saved; };

		return Promise.all(ids.map(function (tpl) {
			var st = d.templates[tpl];
			var src = st.src || {};
			var social = st.size ? Promise.all((d.rows || []).map(function (svg) {
				if (!svg) return Promise.resolve(null);
				return window.SBEmailSigIcons.png(svg, { size: st.size, colour: pick(src.colour, st.colour), bg: st.bg ? pick(src.bg, st.bg) : '', radius: st.radius });
			})) : Promise.resolve([]);
			var c = st.contact;
			var names = c ? Object.keys(c.svgs || {}) : [];
			var contact = Promise.all(names.map(function (n) {
				return window.SBEmailSigIcons.png(c.svgs[n], { size: c.size, colour: pick(c.src, c.colour), bg: c.bg ? pick(c.bgsrc, c.bg) : '', radius: c.radius || 0 });
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
			var out = {};
			ids.forEach(function (tpl, i) { out[tpl] = done[i]; });
			return out;
		});
	}

	/* The theme colour menu, under a field's button (as on the Branding tab). */
	var $menu = null;

	function closeMenu() {
		if ($menu) { $menu.remove(); $menu = null; }
	}

	function openMenu(button, set) {
		var rect = button.getBoundingClientRect();
		closeMenu();
		$menu = $('<div class="sb-emailsig-palette" role="listbox"></div>');
		palette.forEach(function (c) {
			$('<button type="button" role="option"></button>')
				.append($('<i></i>').css('background', c.hex))
				.append($('<span></span>').text(c.name))
				.append($('<code></code>').text(c.hex))
				.on('click', function () { set(c.value); closeMenu(); })
				.appendTo($menu);
		});
		$menu.appendTo(document.body).css({
			top: rect.bottom + window.scrollY + 4,
			left: Math.max(8, Math.min(rect.left + window.scrollX, window.scrollX + document.documentElement.clientWidth - 268))
		});
	}

	$(document).on('mousedown', function (e) {
		if ($menu && !$(e.target).closest('.sb-emailsig-palette, .sb-emailsig-colour__theme').length) closeMenu();
	});

	/** One brand colour's row in the popup: picker, box and theme menu. */
	function field(key, b) {
		var $row = $('<div class="sb-tweaks-dialog__field sb-emailsig-brand-pop__field"></div>');
		var $wrap = $('<span class="sb-tweaks-colour sb-emailsig-colour"></span>');
		var $swatch = $('<input type="color" class="sb-tweaks-colour__swatch">').attr('aria-label', b.name);
		var $box = $('<input type="text" class="sb-tweaks-colour__value code" spellcheck="false" autocomplete="off">')
			.attr('placeholder', key === 'accent' ? 'Brand Primary' : (b['default'] || ''))
			.val(b.raw || '');

		// The swatch shows what the box comes out as; blank, the default (dashed).
		function paint() {
			var own = hexOf($box.val());
			var shown = own || (key === 'accent' ? (hexOf($row.closest('.sb-emailsig-brand-pop').find('[data-key="primary"] .sb-tweaks-colour__value').val()) || b.hex) : (b['default'] || b.hex));
			$wrap.toggleClass('is-inherited', !own);
			$wrap.toggleClass('is-unknown', $box.val().trim() !== '' && !own);
			if ($swatch.val() !== shown) $swatch.val(shown);
		}

		$swatch.on('input change', function () { $box.val(this.value); paint(); });
		$box.on('input', paint);

		$wrap.append($swatch, $box);
		if (palette.length) {
			$('<button type="button" class="sb-emailsig-colour__theme" aria-label="Theme colours" title="Theme colours"><span class="dashicons dashicons-art" aria-hidden="true"></span></button>')
				.on('click', function (e) {
					e.preventDefault();
					if ($menu) { closeMenu(); return; }
					openMenu(this, function (v) { $box.val(v); paint(); });
				})
				.appendTo($wrap);
		}

		$row.attr('data-key', key).append($('<span></span>').text(b.name), $wrap);
		$row.data('paint', paint);
		return $row;
	}

	function save($pop, co, btn) {
		var values = {};
		$pop.find('.sb-emailsig-brand-pop__field').each(function () {
			values[$(this).attr('data-key')] = $(this).find('.sb-tweaks-colour__value').val().trim();
		});

		var $go = $pop.closest('.sb-tweaks-dialog').find('.sb-tweaks-dialog__go');
		var $msg = $pop.find('.sb-emailsig-brand-pop__msg');
		$go.prop('disabled', true).text('Saving…');
		$msg.text('').removeClass('is-error');

		post({ action: 'sb_emailsig_brand_save', nonce: cfg.nonce, co: co, colours: JSON.stringify(values) })
			.then(function (res) {
				if (!res || !res.success) throw new Error((res && res.data) || 'Not saved. Try again.');
				// The company's swatches everywhere on the page.
				document.querySelectorAll('[data-sb-emailsig-brand="' + co + '"]').forEach(function (el) { el.outerHTML = res.data.swatches; });
				if (!window.SBEmailSigIcons) return null;
				$go.text('Drawing icons…');
				return draw(res.data.draw).then(function (icons) {
					return post({ action: 'sb_emailsig_brand_icons', nonce: cfg.nonce, co: co, icons: JSON.stringify(icons) });
				});
			})
			.then(function () {
				closeMenu();
				if (window.SBTweaksConfirm) window.SBTweaksConfirm.close();
				toast('Brand colours saved', 'ok');
			})
			.catch(function (err) {
				$msg.text(err && err.message ? err.message : 'Not saved. Try again.').addClass('is-error');
				$go.prop('disabled', false).text('Save');
			});
	}

	$(document).on('click', '[data-sb-emailsig-brand]', function (e) {
		e.preventDefault();
		if (!window.SBTweaksConfirm) return;

		var btn = this;
		var co = btn.getAttribute('data-sb-emailsig-brand');
		var data = {};
		try { data = JSON.parse(btn.getAttribute('data-sb-emailsig-brand-data') || '{}'); } catch (err) { data = {}; }

		var $pop = $('<div class="sb-emailsig-brand-pop"></div>');
		Object.keys(data).forEach(function (key) { $pop.append(field(key, data[key])); });
		$pop.append('<p class="sb-emailsig-brand-pop__msg" role="status" aria-live="polite"></p>');

		var $modal = window.SBTweaksConfirm.open({
			title: (btn.getAttribute('data-sb-emailsig-brand-name') || 'Company') + ' brand colours',
			body: $pop,
			cancel: 'Cancel',
			action: 'Save'
		});

		$pop.find('.sb-emailsig-brand-pop__field').each(function () { $(this).data('paint')(); });
		$pop.on('input', '[data-key="primary"] .sb-tweaks-colour__value', function () {
			var p = $pop.find('[data-key="accent"]').data('paint');
			if (p) p();
		});

		// Save stays open until it's done (the popup's own button would close it first).
		var go = $modal.find('.sb-tweaks-dialog__go')[0];
		if (go) {
			go.parentNode.addEventListener('click', function (ev) {
				if (!ev.target.closest || !ev.target.closest('.sb-tweaks-dialog__go')) return;
				ev.stopPropagation();
				ev.preventDefault();
				if (!go.disabled) save($pop, co, btn);
			}, true);
		}
		$modal.on('click', function (ev) { if (ev.target === this) closeMenu(); });
	});
})(jQuery);
