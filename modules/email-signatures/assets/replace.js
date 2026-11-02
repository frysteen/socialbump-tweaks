/**
 * Image fields on the module's pages (a person's Photo, a company's logos):
 * the pencil on a chosen image picks a different one from the Media Library
 * instead of opening the image's details (Sam, 30 October 2026: nothing here
 * needs editing, and changing a photo took Remove, then Add). Shown as
 * Replace, with ACF's swap icon.
 *
 * Custom SVG fields (Social Media rows, the company's and a person's
 * Additional Info List Items; their keys end in _icon_file) get the same
 * Replace, and show the SVG itself rather than ACF's file icon (Sam,
 * 2 November 2026). admin.css shows just the icon and the two buttons.
 */
(function () {
	'use strict';

	var SVG = '.acf-field-file[data-key^="field_sb_emailsig_"][data-key$="_icon_file"]';
	var EDIT = '.acf-field-image .acf-actions a[data-name="edit"], ' + SVG + ' .acf-actions a[data-name="edit"]';

	function mark(root) {
		(root && root.querySelectorAll ? root : document).querySelectorAll(EDIT).forEach(function (a) {
			if (a.getAttribute('data-sb-replace')) return;
			var svg = !!a.closest(SVG);
			a.setAttribute('data-sb-replace', '1');
			a.classList.remove('-pencil');
			a.classList.add('-sync');
			a.removeAttribute('title');
			a.setAttribute('aria-label', svg ? 'Replace icon' : 'Replace image');
			a.setAttribute('data-sb-tip', 'Replace');
		});
	}

	/** A Custom SVG field shows the SVG (its file's address) in place of ACF's file icon. */
	function thumbs(root) {
		(root && root.querySelectorAll ? root : document).querySelectorAll(SVG + ' .acf-file-uploader').forEach(function (box) {
			var img = box.querySelector('img[data-name="icon"]');
			var link = box.querySelector('a[data-name="filename"]');
			var url = link ? link.getAttribute('href') || '' : '';
			if (!img) return;
			if (/\.svg(\?|$)/i.test(url)) {
				if (img.getAttribute('src') !== url) img.setAttribute('src', url);
				img.classList.add('sb-emailsig-svg-thumb');
			}
			var remove = box.querySelector('.acf-actions a[data-name="remove"]');
			if (remove && !remove.getAttribute('data-sb-tip')) {
				remove.removeAttribute('title');
				remove.setAttribute('aria-label', 'Remove icon');
				remove.setAttribute('data-sb-tip', 'Remove');
			}
		});
	}

	// Before ACF's own handler (capture), which would open the details.
	document.addEventListener('click', function (e) {
		var a = e.target.closest ? e.target.closest(EDIT) : null;
		if (!a || !window.acf || !window.acf.getField || !window.jQuery) return;
		var field = window.acf.getField(window.jQuery(a.closest('.acf-field-image, .acf-field-file')));
		if (!field || typeof field.selectAttachment !== 'function') return;
		e.preventDefault();
		e.stopPropagation();
		e.stopImmediatePropagation();
		field.selectAttachment();
	}, true);

	function start() {
		mark(document);
		thumbs(document);
		if (window.acf && window.acf.addAction) {
			window.acf.addAction('append', function ($el) { mark($el && $el[0]); thumbs($el && $el[0]); });
		}
		// A file chosen or removed: ACF sets the value last, with jQuery's change.
		if (window.jQuery) {
			window.jQuery(document).on('change', SVG + ' .acf-file-uploader > input[type="hidden"]', function () {
				thumbs(this.closest('.acf-file-uploader').parentNode);
			});
		}
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
	else start();
})();
