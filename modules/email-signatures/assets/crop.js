/**
 * Photo crop box. Loads the ACF photo into Cropper.js at the aspect ratio the
 * photo is made at (the person's own, else the site's template's) and stores
 * the crop as fractions of the image in a hidden input.
 *
 * With their own ratio, a person can have their own shape and corner radius
 * too (the controls show beside the ratio). Reset to Default puts the
 * template's ratio and border back.
 *
 * A crop saved for another aspect ratio is fitted around its middle, the same
 * way the server fits it, and a note asks for it to be checked.
 */
(function ($) {
	'use strict';

	$(function () {
		var $box = $('.sb-emailsig-crop');
		if (!$box.length || typeof Cropper === 'undefined') return;

		var $wrap = $box.find('.sb-emailsig-crop__wrap');
		var $empty = $box.find('.sb-emailsig-crop__empty');
		var $check = $box.find('.sb-emailsig-crop__check');
		var $input = $box.find('input[name="sb_emailsig_crop"]');
		var $ratio = $box.find('select[name="sb_emailsig_ratio"]');
		var $custom = $box.find('.sb-emailsig-crop__custom');
		var $preview = $box.find('.sb-emailsig-crop__preview');
		var img = $box.find('.sb-emailsig-crop__stage img')[0];
		if (window.sbEmailsigCrop) window.sbEmailsigCrop.attach(img);
		var cropper = null;
		var attachmentId = parseInt($box.data('attachment'), 10) || 0;
		var templateRatio = parseFloat($box.data('template-ratio')) || 1;
		var shape = String($box.data('shape') || 'circle');
		var radius = parseFloat($box.data('radius')) || 0; // As a fraction of the width.
		var tplWidth = parseInt($box.data('width'), 10) || 130;
		// The person's own shape and corner radius, shown while their ratio isn't Default.
		var $own = $box.find('.sb-emailsig-crop__own');
		var $shape = $box.find('select[name="sb_emailsig_shape"]');
		var $radius = $box.find('input[name="sb_emailsig_radius"]');

		function own() {
			return ($ratio.val() || '') !== '' && $shape.length > 0;
		}

		function currentShape() {
			return own() ? String($shape.val() || shape) : shape;
		}

		function currentRadius() {
			return own() ? (parseInt($radius.val(), 10) || 0) / tplWidth : radius;
		}

		/** Width over height: the person's own ratio, else the template's. */
		function ratio() {
			var v = $ratio.val() || '';
			if (v === '') return templateRatio;
			if (v === 'custom') {
				var w = parseInt($custom.find('[name="sb_emailsig_ratio_w"]').val(), 10) || 0;
				var h = parseInt($custom.find('[name="sb_emailsig_ratio_h"]').val(), 10) || 0;
				return w > 0 && h > 0 ? w / h : templateRatio;
			}
			var m = /^(\d+):(\d+)$/.exec(v);
			return m && +m[1] > 0 && +m[2] > 0 ? m[1] / m[2] : templateRatio;
		}

		/** The preview beside the crop: the photo's shape at 130px wide. */
		function shapePreview() {
			var r = ratio();
			var w = 130;
			var h = Math.round(w / r);
			var sh = currentShape();
			$preview.css({
				width: w + 'px',
				height: h + 'px',
				borderRadius: sh === 'circle' ? '50%' : (sh === 'rounded' ? Math.round(currentRadius() * w) + 'px' : '0')
			});
			$box.toggleClass('is-circle', sh === 'circle');
			$own.prop('hidden', !own());
			$box.find('.sb-emailsig-crop__radius').prop('hidden', currentShape() !== 'rounded');
		}

		function saved() {
			try {
				var c = JSON.parse($input.val() || 'null');
				return c && parseInt(c.id, 10) === attachmentId ? c : null;
			} catch (e) {
				return null;
			}
		}

		/** A box of the same area at ratio r, around the same middle, inside the image. */
		function fit(box, r, iw, ih) {
			var cx = box.x + box.width / 2;
			var cy = box.y + box.height / 2;
			var nw = Math.sqrt(Math.max(1, box.width * box.height) * r);
			var nh = nw / r;
			if (nw > iw) { nh *= iw / nw; nw = iw; }
			if (nh > ih) { nw *= ih / nh; nh = ih; }
			return {
				x: Math.max(0, Math.min(cx - nw / 2, iw - nw)),
				y: Math.max(0, Math.min(cy - nh / 2, ih - nh)),
				width: nw,
				height: nh
			};
		}

		/**
		 * Tell the page the crop changed (a native change on the hidden input),
		 * so the person page's preview and its Unsaved changes notice follow it.
		 */
		function told() {
			var el = $input[0];
			if (el && typeof Event === 'function') el.dispatchEvent(new Event('change', { bubbles: true }));
		}

		/** Store the crop, and tell the page when it was moved ($say). */
		function store(say) {
			if (!cropper) return;
			var d = cropper.getData(true);
			var im = cropper.getImageData();
			if (!im.naturalWidth || !im.naturalHeight) return;
			$input.val(JSON.stringify({
				id: attachmentId,
				x: d.x / im.naturalWidth,
				y: d.y / im.naturalHeight,
				w: d.width / im.naturalWidth,
				h: d.height / im.naturalHeight
			}));
			if (say === true) told();
		}

		function load(src, useSaved) {
			if (cropper) {
				cropper.destroy();
				cropper = null;
			}
			$check.prop('hidden', true);
			if (!src) {
				$wrap.prop('hidden', true);
				$empty.prop('hidden', false);
				return;
			}
			$empty.prop('hidden', true);
			$wrap.prop('hidden', false);

			var start = useSaved ? saved() : null;
			img.onload = function () {
				cropper = new Cropper(img, Object.assign({}, window.sbEmailsigCrop ? window.sbEmailsigCrop.options : {}, {
					aspectRatio: ratio(),
					viewMode: 1,
					autoCropArea: 1,
					background: false,
					preview: $preview[0],
					ready: function () {
						var refit = false;
						if (start) {
							var im = cropper.getImageData();
							var box = {
								x: start.x * im.naturalWidth,
								y: start.y * im.naturalHeight,
								width: start.w * im.naturalWidth,
								height: start.h * im.naturalHeight
							};
							var r = ratio();
							if (box.height > 0 && Math.abs(box.width / box.height - r) / r > 0.02) {
								box = fit(box, r, im.naturalWidth, im.naturalHeight);
								$check.prop('hidden', false);
								refit = true;
							}
							cropper.setData(box);
						}
						// The saved crop opening keeps its saved value, so the
						// Photo card reads as unchanged; Save stores it again. A
						// new photo, or a crop fitted to a new shape, is a change.
						if (!start || refit) store(!useSaved || refit);
					},
					cropend: function () { store(true); }
				}));
			};
			img.src = src;
		}

		/** The ratio changed: same middle, new shape. */
		function reshape() {
			$custom.prop('hidden', $ratio.val() !== 'custom');
			shapePreview();
			if (!cropper) return;
			var im = cropper.getImageData();
			var box = fit(cropper.getData(), ratio(), im.naturalWidth, im.naturalHeight);
			cropper.setAspectRatio(ratio());
			cropper.setData(box);
			$check.prop('hidden', true);
			store(true);
		}

		// Border: their own width, colour and gap only for Their own.
		$box.on('change', 'select[name="sb_emailsig_border"]', function () {
			$box.find('.sb-emailsig-crop__border-own').prop('hidden', this.value !== 'own');
		});

		// Reset to Default: the template's ratio (so its shape), and its border.
		// Shown while either is their own. Saved with the rest.
		var $border = $box.find('select[name="sb_emailsig_border"]');
		var $defaults = $box.find('.sb-emailsig-crop__defaults');

		function customised() {
			$defaults.prop('hidden', ($ratio.val() || '') === '' && ($border.val() || '') === '');
		}

		$ratio.on('change', customised);
		$border.on('change', customised);

		$box.on('click', '.sb-emailsig-crop__defaults-btn', function () {
			$ratio.val('').trigger('change');
			$border.val('').trigger('change');
		});

		$ratio.on('change', reshape);
		$custom.on('input change', 'input', reshape);
		$shape.on('change', shapePreview);
		$radius.on('input change', shapePreview);

		$box.on('click', '.sb-emailsig-crop__reset', function () {
			if (!cropper) return;
			cropper.reset();
			$check.prop('hidden', true);
			store(true);
		});

		// Keep the stored crop current after keyboard changes.
		// The post screen's form, or the Add and Edit person page's.
		$box.closest('form').on('submit', function () { store(); });

		// ACF photo field changed: load the new image with a fresh crop.
		$(document).on('change', '[data-key="field_sb_emailsig_photo"] input[type="hidden"]', function () {
			var id = parseInt($(this).val(), 10) || 0;
			attachmentId = id;
			$input.val('');
			if (!id) {
				load('', false);
				told();
				return;
			}
			var att = wp.media.attachment(id);
			att.fetch().then(function () {
				load(att.get('url'), false);
			});
		});

		shapePreview();
		load($box.data('src'), true);
	});
})(jQuery);
