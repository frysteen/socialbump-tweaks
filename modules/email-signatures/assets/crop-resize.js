/**
 * Shared by the photo crop on the edit screen (crop.js) and in Request to be
 * added on a team page (request.js).
 *
 * sbEmailsigCrop.options: the photo stays put (no zoom by mouse wheel or
 * pinch, no dragging it about); only the crop box moves and resizes.
 *
 * sbEmailsigCrop.attach(img): hold Alt (Option on a Mac) while dragging a
 * handle and the crop box resizes from its middle, as in Photoshop: drag a
 * corner and the opposite corner moves too, drag a side and the other side
 * does. Once Alt is pressed during a drag this script resizes the box until
 * the drag ends (Cropper.js stops tracking the pointer while its cropmove
 * event is cancelled, so handing back mid-drag would make the box jump);
 * letting go of Alt goes back to resizing from the opposite side.
 */
(function () {
	'use strict';

	var MIN = 20;

	function point(e) {
		var t = e.touches && e.touches[0] ? e.touches[0] : (e.changedTouches && e.changedTouches[0] ? e.changedTouches[0] : e);
		return { x: t.pageX, y: t.pageY };
	}

	function attach(img) {
		var drag = null;
		var last = null; // Where the pointer was when the box was last changed.

		img.addEventListener('cropstart', function (ev) {
			drag = null;
			last = ev.detail && ev.detail.originalEvent ? point(ev.detail.originalEvent) : null;
		});

		img.addEventListener('cropend', function () {
			drag = null;
			last = null;
		});

		img.addEventListener('cropmove', function (ev) {
			var c = img.cropper;
			var action = ev.detail && ev.detail.action;
			var oe = ev.detail && ev.detail.originalEvent;

			if (!c || !oe || !/^(n|s|e|w|ne|nw|se|sw)$/.test(action || '')) return;

			var p = point(oe);
			var alt = !!oe.altKey;

			if (!drag && !alt) {
				last = p; // Cropper.js resizes this one.
				return;
			}

			// Start, or start again when Alt changes, from the box as it is now and
			// the pointer position it matches, so the box never jumps.
			if (!drag || drag.alt !== alt) {
				var from = last || p;
				drag = { alt: alt, x: from.x, y: from.y, box: c.getCropBoxData() };
			}

			ev.preventDefault();

			var b = drag.box;
			var cv = c.getCanvasData();
			var r = c.options.aspectRatio;
			var fixed = r > 0;
			var sx = /e/.test(action) ? 1 : (/w/.test(action) ? -1 : 0);
			var sy = /s/.test(action) ? 1 : (/n/.test(action) ? -1 : 0);
			var dw = sx * (p.x - drag.x);
			var dh = sy * (p.y - drag.y);
			var k = alt ? 2 : 1;
			var w, h;

			if (fixed) {
				if (sx && sy) {
					w = Math.abs(dw) >= Math.abs(dh * r) ? b.width + k * dw : (b.height + k * dh) * r;
				} else if (sx) {
					w = b.width + k * dw;
				} else {
					w = (b.height + k * dh) * r;
				}
				w = Math.max(w, MIN, MIN * r);
				h = w / r;
			} else {
				w = Math.max(MIN, sx ? b.width + k * dw : b.width);
				h = Math.max(MIN, sy ? b.height + k * dh : b.height);
			}

			// Which edge stays put: the opposite one, or none (the middle) with Alt
			// or along a side the handle doesn't pull.
			var cx = b.left + b.width / 2;
			var cy = b.top + b.height / 2;
			var right = cv.left + cv.width;
			var bottom = cv.top + cv.height;
			var hMode = alt || !sx ? 0 : sx;
			var vMode = alt || !sy ? 0 : sy;
			var maxW = hMode > 0 ? right - b.left : (hMode < 0 ? b.left + b.width - cv.left : 2 * Math.min(cx - cv.left, right - cx));
			var maxH = vMode > 0 ? bottom - b.top : (vMode < 0 ? b.top + b.height - cv.top : 2 * Math.min(cy - cv.top, bottom - cy));

			if (fixed) {
				var f = Math.min(1, maxW / w, maxH / h);
				w *= f;
				h *= f;
			} else {
				w = Math.min(w, maxW);
				h = Math.min(h, maxH);
			}

			c.setCropBoxData({
				left: hMode > 0 ? b.left : (hMode < 0 ? b.left + b.width - w : cx - w / 2),
				top: vMode > 0 ? b.top : (vMode < 0 ? b.top + b.height - h : cy - h / 2),
				width: w,
				height: h
			});
			last = p;
		});
	}

	window.sbEmailsigCrop = {
		options: {
			dragMode: 'none',
			movable: false,
			zoomable: false,
			zoomOnWheel: false,
			zoomOnTouch: false
		},
		attach: attach
	};
})();
