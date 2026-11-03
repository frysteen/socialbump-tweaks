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
		return SBTweaksColour.lookup(value, { colours: colours });
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

	/**
	 * One brand colour's row in the popup: the framework's colour field
	 * (SBTweaksColour) with the theme colours menu. Blank shows the default,
	 * dashed; a blank Accent shows the Primary.
	 */
	function field(key, b) {
		var $row = $('<div class="sb-tweaks-dialog__field sb-emailsig-brand-pop__field"></div>');
		var wrap = SBTweaksColour.field({ label: b.name, value: b.raw || '', placeholder: key === 'accent' ? 'Brand Primary' : (b['default'] || ''), className: 'sb-emailsig-colour' }, {
			palette: palette,
			hex: hexOf,
			fallback: function () {
				if (key !== 'accent') return b['default'] || b.hex;
				return hexOf($row.closest('.sb-emailsig-brand-pop').find('[data-key="primary"] .sb-tweaks-colour__value').val()) || b.hex;
			}
		});

		$row.attr('data-key', key).append($('<span></span>').text(b.name), wrap);
		$row.data('paint', function () { wrap._sbColour.paint(); });
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

		var saved = false;
		post({ action: 'sb_emailsig_brand_save', nonce: cfg.nonce, co: co, colours: JSON.stringify(values) })
			.then(function (res) {
				if (!res || !res.success) throw new Error((res && res.data) || 'Not saved. Try again.');
				saved = true;
				// The company's swatches everywhere on the page.
				document.querySelectorAll('[data-sb-emailsig-brand="' + co + '"]').forEach(function (el) { el.outerHTML = res.data.swatches; });
				if (!window.SBEmailSigIcons) return null;
				$go.text('Drawing icons…');
				return draw(res.data.draw).then(function (icons) {
					return post({ action: 'sb_emailsig_brand_icons', nonce: cfg.nonce, co: co, icons: JSON.stringify(icons) });
				}).then(function (r) {
					if (!r || !r.success) throw new Error('icons');
				});
			})
			.then(function () {
				SBTweaksColour.close();
				if (window.SBTweaksConfirm) window.SBTweaksConfirm.close();
				toast('Brand colours saved', 'ok');
			})
			.catch(function (err) {
				// The colours are kept even when the icons couldn't be drawn: Save again redraws them.
				var says = saved ? 'Colours saved, but the icons weren\'t redrawn. Save again to redraw them.' : (err && err.message ? err.message : 'Not saved. Try again.');
				$msg.text(says).addClass('is-error');
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
		$modal.on('click', function (ev) { if (ev.target === this) SBTweaksColour.close(); });
	});
	/* ------------------------------------------------------------------ *
	 * Templates (Sam, 3 November 2026): the Default Template's name on a
	 * company's card opens a popup with the Default Template (a menu) above
	 * the ones its people can pick (ticks), as on Edit company's Company tab:
	 * the default's tick is on and locked, and stays on when it stops being
	 * the default. Save keeps both (templates_save) and updates the card.
	 * ------------------------------------------------------------------ */

	function templatesBody(d) {
		var $pop = $('<div class="sb-emailsig-tpl-pop"></div>');
		var $main = $('<select></select>');
		var $list = $('<div class="sb-emailsig-tpl-pop__list" role="group" aria-label="Templates"></div>');

		d.all.forEach(function (t) {
			$('<option></option>').val(t[0]).text(t[1]).prop('selected', t[0] === d.main).appendTo($main);
			$('<label class="sb-emailsig-tpl-pop__item"></label>')
				.append($('<input type="checkbox">').val(t[0]).prop('checked', (d.set || []).indexOf(t[0]) !== -1), $('<span></span>').text(t[1]))
				.appendTo($list);
		});

		$pop.append(
			$('<label class="sb-tweaks-dialog__field"></label>').append($('<span></span>').text('Default Template'), $main, $('<p class="description"></p>').text('Used for everyone in this company.')),
			$('<div class="sb-tweaks-dialog__field"></div>').append($('<span></span>').text('Templates'), $list, $('<p class="description"></p>').text('The ones people can pick on their signature page. The Default Template always is.')),
			'<p class="sb-emailsig-brand-pop__msg" role="status" aria-live="polite"></p>'
		);

		// The default's box: ticked and locked.
		function lock() {
			$list.find('label').each(function () {
				var box = this.querySelector('input');
				var main = box.value === $main.val();
				if (main) box.checked = true;
				box.setAttribute('aria-disabled', main ? 'true' : 'false');
				this.classList.toggle('is-locked', main);
				if (main) this.setAttribute('data-sb-tip', 'The Default Template, always available'); else this.removeAttribute('data-sb-tip');
			});
		}

		// Not disabled, so it's still sent: a click on the locked one just does nothing.
		$list[0].addEventListener('click', function (e) {
			var label = e.target.closest('label');
			if (label && label.classList.contains('is-locked')) e.preventDefault();
		}, true);
		$main.on('change', lock);
		lock();

		return $pop;
	}

	function saveTemplates($pop, co) {
		var $go = $pop.closest('.sb-tweaks-dialog').find('.sb-tweaks-dialog__go');
		var $msg = $pop.find('.sb-emailsig-brand-pop__msg');
		var main = $pop.find('select').val();
		var set = $pop.find('.sb-emailsig-tpl-pop__list input:checked').map(function () { return this.value; }).get();

		$go.prop('disabled', true).text('Saving…');
		$msg.text('').removeClass('is-error');

		post({ action: 'sb_emailsig_templates_save', nonce: cfg.nonce, co: co, main: main, templates: JSON.stringify(set) })
			.then(function (res) {
				if (!res || !res.success) throw new Error((res && res.data) || 'Not saved. Try again.');
				document.querySelectorAll('[data-sb-emailsig-tpls="' + co + '"]').forEach(function (el) {
					el.textContent = res.data.name;
					el.setAttribute('data-sb-emailsig-tpls-data', JSON.stringify(res.data.data));
				});
				document.querySelectorAll('[data-sb-emailsig-tpl-count="' + co + '"]').forEach(function (el) {
					el.textContent = res.data.count;
					if (el.nextSibling) el.nextSibling.textContent = ' ' + res.data.noun;
				});
				if (window.SBTweaksConfirm) window.SBTweaksConfirm.close();
				toast('Templates saved', 'ok');
				// Open Templates pages in other tabs show it too (SBTweaksSync).
				if (window.SBTweaksSync) window.SBTweaksSync.send('email-signatures/templates', { company: co });
			})
			.catch(function (err) {
				$msg.text(err && err.message ? err.message : 'Not saved. Try again.').addClass('is-error');
				$go.prop('disabled', false).text('Save');
			});
	}

	$(document).on('click', '[data-sb-emailsig-tpls]', function (e) {
		e.preventDefault();
		if (!window.SBTweaksConfirm) return;

		var btn = this;
		var co = btn.getAttribute('data-sb-emailsig-tpls');
		var d = {};
		try { d = JSON.parse(btn.getAttribute('data-sb-emailsig-tpls-data') || '{}'); } catch (err) { d = {}; }
		if (!d.all || !d.all.length) return;

		var $pop = templatesBody(d);
		var $modal = window.SBTweaksConfirm.open({
			title: (btn.getAttribute('data-sb-emailsig-tpls-name') || 'Company') + ' templates',
			body: $pop,
			cancel: 'Cancel',
			action: 'Save'
		});

		// Save stays open until it's done (the popup's own button would close it first).
		var go = $modal.find('.sb-tweaks-dialog__go')[0];
		if (go) {
			go.parentNode.addEventListener('click', function (ev) {
				if (!ev.target.closest || !ev.target.closest('.sb-tweaks-dialog__go')) return;
				ev.stopPropagation();
				ev.preventDefault();
				if (!go.disabled) saveTemplates($pop, co);
			}, true);
		}
	});
})(jQuery);
