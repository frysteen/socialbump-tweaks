/**
 * A person's Staff Page field (SB_EmailSig_Staff): under it, the page's
 * featured image with Use this photo, and its fields as tokens, empty ones
 * greyed. A token click copies it, as merge tags do elsewhere (Sam, 30
 * October 2026: was put in the field last typed in).
 *
 * Picking a page: its featured image becomes the photo if there's none yet;
 * a different photo stays until Use this photo is pressed.
 */
(function ($) {
	'use strict';

	var cfg = window.sbEmailsigStaff || {};

	if (typeof acf === 'undefined') return;

	/* Each company has its own staff pages (or none): the person's company
	   as picked on the page, else the default (one company: no picker). */
	function company() {
		var $sel = $('[data-key="field_sb_emailsig_person_company"] select');
		return String($sel.length ? $sel.val() : (cfg.def || ''));
	}

	function hasPages(co) {
		return !!(cfg.types && cfg.types[co]);
	}

	// The Staff Page search lists that company's pages (Staff::page_query()).
	acf.addFilter('select2_ajax_data', function (data, args, $input) {
		if ($input && $input.closest('[data-key="field_sb_emailsig_staff_page"]').length) data.sb_company = company();
		return data;
	});

	// A token click copies it (as on Settings' Emails tab).
	$(document).on('click', '.sb-emailsig-staff [data-sb-emailsig-tag]', function (e) {
		e.preventDefault();
		var btn = this;
		var tag = btn.getAttribute('data-sb-emailsig-tag');
		var code = btn.querySelector('code');
		var done = function () {
			code.textContent = 'Copied';
			btn.classList.add('is-copied');
			setTimeout(function () {
				code.textContent = tag;
				btn.classList.remove('is-copied');
			}, 1200);
		};
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(tag).then(done);
			return;
		}
		var t = document.createElement('textarea');
		t.value = tag;
		t.style.position = 'fixed';
		t.style.opacity = '0';
		document.body.appendChild(t);
		t.select();
		document.execCommand('copy');
		document.body.removeChild(t);
		done();
	});

	acf.addAction('ready', function () {
		var staff = acf.getField('field_sb_emailsig_staff_page');
		var photo = acf.getField('field_sb_emailsig_photo');
		if (!staff || !staff.$el || !staff.$el.length) return;

		var $panel = $('<div class="sb-emailsig-staff" hidden></div>').appendTo(staff.$el.find('.acf-input').first());
		var current = null;

		function photoId() {
			return photo ? parseInt(photo.val(), 10) || 0 : 0;
		}

		function usePhoto(id) {
			if (!photo || !id || !window.wp || !wp.media) return;
			var att = wp.media.attachment(id);
			att.fetch().then(function () {
				// As ACF's own picker does: set the value out loud (render() sets
				// it silently since ACF 6.8), so the crop box and preview follow.
				acf.val(photo.$input(), String(id));
				photo.render(att);
				show();
			});
		}

		/* Under Name, Qualifications, Job Title, Email and Mobile: a note for
		   each merge tag that comes out empty (Sam, 30 October 2026). Email is
		   required, so its note is red. Only once the staff page has loaded. */
		var FIELDS = ['first_name', 'last_name', 'qualifications', 'job_title', 'email', 'mobile'];
		var ready = false;

		function notes() {
			if (!ready) return;
			var have = {};
			((current && current.tokens) || []).forEach(function (t) { if (t.value) have[t.token] = true; });
			FIELDS.forEach(function (f) {
				var $f = $('[data-key="field_sb_emailsig_' + f + '"]');
				var $in = $f.find('.acf-input input').first();
				if (!$in.length) return;
				var empty = ($in.val().match(/\{[a-z0-9_]+\}/gi) || []).filter(function (tk, i, all) {
					return !have[tk] && all.indexOf(tk) === i;
				});
				var $n = $f.find('.sb-emailsig-token-note');
				if (!empty.length) { $n.remove(); return; }
				if (!$n.length) $n = $('<p class="sb-emailsig-token-note"></p>').appendTo($f.find('.acf-input').first());
				$n.toggleClass('is-danger', f === 'email')
					.text(empty.join(', ') + (current ? (empty.length > 1 ? ' are' : ' is') + ' empty on the staff page.' : (empty.length > 1 ? ' need' : ' needs') + ' a staff page.'));
			});
		}
		$(document).on('input change', FIELDS.map(function (f) { return '[data-key="field_sb_emailsig_' + f + '"] input'; }).join(','), notes);

		function show() {
			notes();
			$panel.empty();
			// The field and its panel as one box while a page is picked.
			staff.$el.toggleClass('has-staff', !!current);
			if (!current) {
				$panel.prop('hidden', true);
				return;
			}

			if (current.photo) {
				var same = current.photo.id === photoId();
				var $p = $('<div class="sb-emailsig-staff__photo"></div>');
				$('<img alt="">').attr('src', current.photo.url).appendTo($p);
				if (same) {
					$('<span class="sb-emailsig-staff__note"></span>').text('Using the staff page photo.').appendTo($p);
				} else {
					$('<span class="sb-emailsig-staff__note"></span>').text(photoId() ? 'The staff page has a different photo.' : 'The staff page photo.').appendTo($p);
					$('<button type="button" class="button button-small"></button>').text('Use this photo').on('click', function () {
						usePhoto(current.photo.id);
					}).appendTo($p);
				}
				$panel.append($p);
			}

			if (current.tokens && current.tokens.length) {
				var $t = $('<div class="sb-emailsig-staff__tokens"></div>');
				$('<p class="sb-emailsig-staff__note"></p>').text('From the staff page. Click one to copy it, then paste it into a field below.').appendTo($t);
				current.tokens.forEach(function (t) {
					$('<button type="button" class="sb-emailsig-token"></button>')
						.toggleClass('is-empty', !t.value)
						.attr({ title: t.label + (t.value ? '' : ': empty on the staff page'), 'data-sb-emailsig-tag': t.token })
						.append($('<code></code>').text(t.token))
						.append($('<span></span>').text(t.value || 'empty'))
						.appendTo($t);
				});
				$panel.append($t);
			}

			$panel.prop('hidden', false);
		}

		function load(picked) {
			var id = parseInt(staff.val(), 10) || 0;
			if (!id) {
				current = null;
				ready = true;
				show();
				return;
			}
			$.post(cfg.ajax, { action: 'sb_emailsig_staff', nonce: cfg.nonce, id: id, company: company() }).done(function (res) {
				current = res && res.success ? res.data : null;
				ready = true;
				// Just picked, and no photo yet: theirs from the staff page.
				if (picked && current && current.photo && !photoId()) {
					usePhoto(current.photo.id);
					return;
				}
				show();
			});
		}

		// Shown only for a company with staff pages; another company's page is
		// no use, so changing company clears it.
		function fit(changed) {
			var on = hasPages(company());
			staff.$el.toggle(on);
			if (changed) staff.$el.find('select').val(null).trigger('change');
			if (!changed) return;
			current = null;
			show();
		}
		$(document).on('change', '[data-key="field_sb_emailsig_person_company"] select', function () { fit(true); });
		fit(false);

		staff.on('change', 'select, input', function () { load(true); });
		if (photo) photo.on('change', 'input[type="hidden"]', show);
		load(false);
	});
})(jQuery);
