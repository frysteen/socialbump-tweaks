/**
 * Add and Edit person (SB_EmailSig_Person): the preview beside the cards
 * redrawn as the form changes (name, job title and the other simple fields,
 * the photo and its crop, and the templates picked), a moment after typing
 * stops, and their own Info List rows as they're added, removed, moved or
 * typed in. Their templates show as tabs above it when there's more than
 * one, and what the template shown leaves out is listed under it (Sam, 30
 * October 2026). The signature is scaled down to fit the column when it's
 * wider (zoom), as on Edit styles.
 */
(function () {
	'use strict';

	var cfg = window.sbEmailsigPerson || {};
	var form = document.getElementById('sb-emailsig-person-form');
	var box = document.getElementById('sb-emailsig-person-preview');
	var tabs = document.getElementById('sb-emailsig-person-tpls');
	var off = document.getElementById('sb-emailsig-person-off');

	if (!form || !box || !cfg.id) return;

	function fit() {
		var wrap = box.parentNode;
		var cs = window.getComputedStyle(wrap);
		var room = wrap.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
		box.style.zoom = '';
		var wide = box.scrollWidth;
		box.style.zoom = wide > room && room > 0 ? String(Math.max(0.4, room / wide)) : '';
	}

	window.addEventListener('resize', fit);
	box.addEventListener('load', fit, true);
	fit();

	var status = form.querySelector('.sb-emailsig-editor__status');
	var live = (cfg.live || []).concat(['field_sb_emailsig_photo']);
	var EXTRA = 'field_sb_emailsig_person_templates_extra';
	var INFO = 'acf[field_sb_emailsig_person_info]';
	var CROP = ['sb_emailsig_crop', 'sb_emailsig_ratio', 'sb_emailsig_ratio_w', 'sb_emailsig_ratio_h', 'sb_emailsig_shape', 'sb_emailsig_radius', 'sb_emailsig_border', 'sb_emailsig_border_w', 'sb_emailsig_border_c', 'sb_emailsig_border_g'];
	var shown = '';
	var timer = null;
	var last = '';
	var asking = 0;

	if (tabs) {
		var on = tabs.querySelector('[aria-current="page"]');
		shown = on ? on.getAttribute('data-sb-emailsig-tpl') : '';
	}

	/** What the preview is drawn from: the form's live fields, the crop box and the template tab. */
	function values() {
		var out = new URLSearchParams();
		var extras = form.querySelector('[name="acf[' + EXTRA + '][]"]');

		new FormData(form).forEach(function (value, name) {
			if (typeof value !== 'string') return;
			var m = /^acf\[(field_[a-z0-9_]+)\]$/.exec(name);
			if (m && live.indexOf(m[1]) !== -1) {
				out.set(name, value);
			} else if (name.indexOf(INFO) === 0 && name.indexOf('[acfcloneindex]') === -1) {
				out.append(name, value);
			} else if (name === 'acf[' + EXTRA + '][]') {
				out.append('sb_extras[]', value);
			} else if (CROP.indexOf(name) !== -1) {
				out.set(name, value);
			}
		});

		// None ticked still counts: an empty pick, not the saved ones.
		if (extras) out.append('sb_extras[]', '');
		out.set('template', shown);

		return out;
	}

	function update() {
		var data = values();
		var key = data.toString();
		if (key === last) return;
		last = key;

		data.set('action', 'sb_emailsig_person_preview');
		data.set('nonce', cfg.nonce || '');
		data.set('id', String(cfg.id));

		var mine = ++asking;
		if (status) status.textContent = 'Updating';

		fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: data })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (mine !== asking) return;
				if (!res || !res.success) throw new Error('no preview');
				var d = res.data || {};
				if (window.SBTweaksUI && window.SBTweaksUI.hideTip) window.SBTweaksUI.hideTip();
				box.innerHTML = d.html || '';
				if (tabs) tabs.innerHTML = d.switcher || '';
				if (off) off.innerHTML = d.off || '';
				shown = d.template || '';
				last = values().toString();
				fit();
				if (status) status.textContent = '';
			})
			.catch(function () {
				if (mine !== asking) return;
				last = '';
				if (status) status.textContent = 'Preview not updated';
			});
	}

	function soon() {
		clearTimeout(timer);
		timer = setTimeout(update, 400);
	}

	// A template tab: straight away.
	if (tabs) {
		tabs.addEventListener('click', function (e) {
			var tab = e.target.closest ? e.target.closest('[data-sb-emailsig-tpl]') : null;
			if (!tab || tab.getAttribute('aria-current') === 'page') return;
			shown = tab.getAttribute('data-sb-emailsig-tpl') || '';
			Array.prototype.forEach.call(tabs.querySelectorAll('[data-sb-emailsig-tpl]'), function (t) {
				if (t === tab) t.setAttribute('aria-current', 'page');
				else t.removeAttribute('aria-current');
			});
			clearTimeout(timer);
			update();
		});
	}

	form.addEventListener('input', soon);
	form.addEventListener('change', soon);

	// ACF adds, removes and sorts Info List rows without a change event: say
	// so from the repeater, once the row has gone, so the preview and the
	// card's Unsaved chip both follow.
	function rows($el, wait) {
		var el = $el && $el[0] ? $el[0] : null;
		var field = el && el.closest ? el.closest('.acf-field-repeater') : null;
		if (!field || !form.contains(field)) return;
		setTimeout(function () {
			field.dispatchEvent(new Event('change', { bubbles: true }));
		}, wait);
	}

	if (window.acf && window.acf.addAction) {
		window.acf.addAction('append', function ($el) { rows($el, 0); });
		window.acf.addAction('remove', function ($el) { rows($el, 500); });
		window.acf.addAction('sortstop', function ($el) { rows($el, 0); });
	}
	last = values().toString();
})();
