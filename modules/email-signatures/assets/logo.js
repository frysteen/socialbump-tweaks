/**
 * The Small Logo on a company's page is square (SB_EmailSig_Logo, Sam,
 * 30 October 2026). An image picked for it that isn't square opens a crop
 * popup locked to a square; Save crop makes the crop a new Media Library
 * image (logo_crop) and puts it in the field, and Cancel puts back what was
 * there. An image under 128 pixels a side is refused. Under the field: Re-crop
 * (from the image the crop was cut from, or the crop itself once that's
 * gone), and a warning for a logo that isn't square or is under 512 pixels.
 * Reset crop (in the popup, and under the field for a crop whose image is
 * still there) goes back to the biggest square in the middle; on a square
 * image that's all of it, and saving then puts the image itself back with no
 * crop made (Sam, 31 October 2026). Nothing is kept until the company is saved.
 */
(function ($) {
	'use strict';

	var cfg = window.sbEmailsigLogo || {};
	// A company's Small Logo, and Settings' Admin Branding one.
	var KEYS = ['field_sb_emailsig_logo_small', 'field_sb_emailsig_admin_logo_small'];
	var MIN = cfg.min || 128;
	var GOOD = cfg.good || 512;
	var MAX = cfg.max || 512;

	function post(data) {
		data.nonce = cfg.nonce;
		return fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams(data) })
			.then(function (r) { return r.json(); });
	}

	function toast(msg, kind) {
		if (window.SBTweaksUI && window.SBTweaksUI.toast) window.SBTweaksUI.toast(msg, kind);
	}

	function px(n) { return n + ' by ' + n + ' pixels'; }

	function start(field) {
		var $tools = $('<div class="sb-emailsig-logo-tools" hidden></div>');
		var busy = false;            // The value is being set from here.
		var prev = parseInt(field.val(), 10) || 0;
		var asked = 0;               // The latest info asked for.

		field.$('.acf-input').append($tools);

		/** Put a value in the field, without this script reacting to it. */
		function set(value, attachment) {
			busy = true;
			if (attachment) {
				// acf.val first, so the form knows it changed (render() alone sets it silently).
				acf.val(field.$input(), String(value || ''));
				field.render(attachment);
			} else {
				field.val(value ? String(value) : '');
			}
			busy = false;
		}

		/** The line under the field: Re-crop, and what's wrong with the logo. */
		function show(d) {
			$tools.empty().prop('hidden', !d);
			if (!d) return;

			var side = Math.min(d.width, d.height);
			var $btn = $('<button type="button" class="button button-small"></button>')
				.text(d.square ? 'Re-crop' : 'Crop')
				.on('click', function () { crop(d, false); });

			$tools.append($btn);

			// A crop with its image still there: Reset crop, without the popup.
			if (d.source && d.source.id !== d.id) {
				$('<button type="button" class="button button-small">Reset crop</button>')
					.on('click', function () { reset(d, this); })
					.appendTo($tools);
			}

			if (!d.square) {
				$tools.append('<span class="sb-emailsig-chip sb-emailsig-chip--warn">Not square: crop it</span>');
			} else if (side < GOOD) {
				$tools.append($('<span class="sb-emailsig-chip sb-emailsig-chip--warn"></span>').text('Small: ' + px(side) + '. ' + GOOD + ' is best.'));
			}
		}

		/**
		 * Reset crop under the field: a square image goes back in as it is (the
		 * crop goes when the company is saved); any other gets a new crop of its
		 * biggest square in the middle.
		 */
		function reset(d, button) {
			var src = d.source;
			var side = Math.min(src.width, src.height);

			$(button).prop('disabled', true);

			var fail = function (err) {
				$(button).prop('disabled', false);
				toast(err && err.message ? err.message : 'Not reset. Try again.', 'danger');
			};

			if (Math.abs(src.width - src.height) <= 1) {
				var att = wp.media.attachment(src.id);
				att.fetch().then(function () {
					set(src.id, att);
					prev = src.id;
					check(src.id, false);
				}, fail);
				return;
			}

			post({ action: 'sb_emailsig_logo_crop', co: cfg.co || 0, src: src.id, x: Math.round((src.width - side) / 2), y: Math.round((src.height - side) / 2), w: side })
				.then(function (res) {
					if (!res || !res.success) throw new Error((res && res.data) || 'Not reset. Try again.');
					set(res.data.id, res.data);
					prev = res.data.id;
					check(res.data.id, false);
				})
				.catch(fail);
		}

		/** Look at the value: $picked when someone has just chosen it. */
		function check(id, picked) {
			if (!id) {
				prev = 0;
				show(null);
				return;
			}

			var mine = ++asked;

			post({ action: 'sb_emailsig_logo_info', id: id }).then(function (res) {
				if (mine !== asked) return;
				if (!res || !res.success) {
					show(null);
					return;
				}

				var d = res.data;

				if (picked && Math.min(d.width, d.height) < MIN) {
					set(prev);
					check(prev, false);
					toast('Too small: the logo needs to be at least ' + px(MIN) + '.', 'danger');
					return;
				}

				if (picked && !d.square) {
					crop(d, true);
					return;
				}

				prev = id;
				show(d);
			});
		}

		/** The crop popup for logo d. $picked: Cancel puts back the logo before. */
		function crop(d, picked) {
			if (!window.SBTweaksConfirm || !window.Cropper) return;

			var source = d.source;
			var done = false;
			var cropper = null;
			var $body = $('<div class="sb-emailsig-logo-crop"></div>');
			var $img = $('<img alt="">');
			var $info = $('<p class="sb-emailsig-logo-crop__info" aria-live="polite"></p>');
			var $msg = $('<p class="sb-emailsig-logo-crop__msg" role="status" aria-live="polite"></p>');

			if (d.note) {
				$body.append('<p class="sb-emailsig-logo-crop__note">Original image deleted. Cropping the current logo.</p>');
			}

			// Reset crop: back to the biggest square in the middle (the whole image, if it's square).
			var $reset = $('<button type="button" class="button button-small">Reset crop</button>').on('click', function () {
				if (!cropper) return;
				var im = cropper.getImageData();
				var s = Math.min(im.naturalWidth, im.naturalHeight);
				cropper.setData({ x: Math.round((im.naturalWidth - s) / 2), y: Math.round((im.naturalHeight - s) / 2), width: s, height: s });
			});

			$body.append($('<div class="sb-emailsig-logo-crop__stage"></div>').append($img), $('<div class="sb-emailsig-logo-crop__bar"></div>').append($info, $reset), $msg);

			/** A square image, all of it: used as it is, no crop made. */
			function whole(side) {
				if (!cropper) return false;
				var im = cropper.getImageData();
				return Math.abs(im.naturalWidth - im.naturalHeight) <= 1 && side >= Math.min(im.naturalWidth, im.naturalHeight) - 1;
			}

			var $modal = window.SBTweaksConfirm.open({
				title: 'Crop the small logo',
				body: $body,
				cancel: 'Cancel',
				action: 'Save crop'
			});
			var modal = $modal[0];
			var $go = $modal.find('.sb-tweaks-dialog__go');

			$modal.find('.sb-tweaks-dialog').addClass('sb-emailsig-logo-dialog');
			$go.prop('disabled', true);

			// A drag let go of outside the box isn't a click on the dark area.
			var downIn = false;
			modal.addEventListener('mousedown', function (e) { downIn = e.target !== modal; }, true);
			modal.addEventListener('click', function (e) {
				if (e.target === modal && downIn) e.stopImmediatePropagation();
				downIn = false;
			}, true);

			// Closed without saving: a new pick goes back to what was there.
			var watch = new MutationObserver(function () {
				if (document.body.contains(modal)) return;
				watch.disconnect();
				if (cropper) cropper.destroy();
				if (!done && picked) {
					set(prev);
					check(prev, false);
				}
			});
			watch.observe(document.body, { childList: true });

			function info(side) {
				if (whole(side)) side = Math.min(cropper.getImageData().naturalWidth, cropper.getImageData().naturalHeight);
				var warn = side < GOOD;
				$info.toggleClass('is-error', side < MIN).toggleClass('is-warn', side >= MIN && warn);
				if (side < MIN) {
					$info.text(px(side) + '. Too small: at least ' + px(MIN) + '.');
				} else {
					$info.text(px(side) + (whole(side) ? '. The whole image, no crop' : (side > MAX ? ', saved at ' + px(MAX) : '')) + (warn ? '. ' + GOOD + ' is best.' : '.'));
				}
				$go.prop('disabled', side < MIN);
			}

			$img.on('load', function () {
				cropper = new window.Cropper($img[0], {
					aspectRatio: 1,
					viewMode: 1,
					autoCropArea: 1,
					background: false,
					zoomable: false,
					rotatable: false,
					scalable: false,
					movable: false,
					ready: function () {
						if (d.rect) cropper.setData({ x: d.rect[0], y: d.rect[1], width: d.rect[2], height: d.rect[2] });
						info(Math.round(cropper.getData(true).width));
					},
					crop: function (e) { info(Math.round(e.detail.width)); }
				});
			});
			$img.attr('src', source.url);

			// Save crop stays open until it's done (the popup's own button would close it first).
			$go[0].parentNode.addEventListener('click', function (ev) {
				if (!ev.target.closest || !ev.target.closest('.sb-tweaks-dialog__go')) return;
				ev.stopPropagation();
				ev.preventDefault();
				if ($go.prop('disabled') || !cropper) return;

				var box = cropper.getData(true);
				$go.prop('disabled', true).text('Saving…');
				$msg.text('').removeClass('is-error');

				// The whole of a square image: it goes back in as it is, and no crop is made.
				// The crop it replaces goes when the company is saved.
				if (whole(Math.min(box.width, box.height)) && window.wp && wp.media && wp.media.attachment) {
					var att = wp.media.attachment(source.id);
					att.fetch().then(function () {
						done = true;
						set(source.id, att);
						prev = source.id;
						window.SBTweaksConfirm.close();
						check(source.id, false);
					}, function () {
						$msg.text('Not saved. Try again.').addClass('is-error');
						$go.prop('disabled', false).text('Save crop');
					});
					return;
				}

				post({ action: 'sb_emailsig_logo_crop', co: cfg.co || 0, src: source.id, x: box.x, y: box.y, w: Math.min(box.width, box.height) })
					.then(function (res) {
						if (!res || !res.success) throw new Error((res && res.data) || 'Not saved. Try again.');
						done = true;
						set(res.data.id, res.data);
						prev = res.data.id;
						window.SBTweaksConfirm.close();
						check(res.data.id, false);
					})
					.catch(function (err) {
						$msg.text(err && err.message ? err.message : 'Not saved. Try again.').addClass('is-error');
						$go.prop('disabled', false).text('Save crop');
					});
			}, true);
		}

		// A new pick: after ACF has drawn it, so putting the old one back sticks.
		field.$input().on('change', function () {
			if (busy) return;
			var id = parseInt(field.val(), 10) || 0;
			setTimeout(function () { check(id, true); }, 0);
		});

		check(prev, false);
	}

	if (window.acf) {
		KEYS.forEach(function (key) {
			acf.addAction('ready_field/key=' + key, start);
			acf.addAction('append_field/key=' + key, start);
		});
	}
})(jQuery);
