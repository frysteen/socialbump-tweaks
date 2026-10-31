/**
 * Image fields on the module's pages (a person's Photo, a company's logos):
 * the pencil on a chosen image picks a different one from the Media Library
 * instead of opening the image's details (Sam, 30 October 2026: nothing here
 * needs editing, and changing a photo took Remove, then Add). Shown as
 * Replace, with ACF's swap icon.
 */
(function () {
	'use strict';

	var EDIT = '.acf-field-image .acf-actions a[data-name="edit"]';

	function mark(root) {
		(root && root.querySelectorAll ? root : document).querySelectorAll(EDIT).forEach(function (a) {
			if (a.getAttribute('data-sb-replace')) return;
			a.setAttribute('data-sb-replace', '1');
			a.classList.remove('-pencil');
			a.classList.add('-sync');
			a.setAttribute('title', 'Replace');
			a.setAttribute('aria-label', 'Replace image');
			a.setAttribute('data-sb-tip', 'Replace');
		});
	}

	// Before ACF's own handler (capture), which would open the details.
	document.addEventListener('click', function (e) {
		var a = e.target.closest ? e.target.closest(EDIT) : null;
		if (!a || !window.acf || !window.acf.getField || !window.jQuery) return;
		var field = window.acf.getField(window.jQuery(a.closest('.acf-field-image')));
		if (!field || typeof field.selectAttachment !== 'function') return;
		e.preventDefault();
		e.stopPropagation();
		e.stopImmediatePropagation();
		field.selectAttachment();
	}, true);

	function start() {
		mark(document);
		if (window.acf && window.acf.addAction) {
			window.acf.addAction('append', function ($el) { mark($el && $el[0]); });
		}
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
	else start();
})();
