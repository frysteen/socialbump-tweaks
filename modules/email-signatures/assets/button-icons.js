/**
 * Additional Button icons (Sam, 2 November 2026; SB_EmailSig_Icons button
 * section). On the Email Signatures pages:
 *
 * - previews: each img[data-sbes-btn-icon] not drawn in its colour yet (the
 *   clear stand-in, or a kept one for another colour, such as unsaved
 *   styles) is drawn here, in the button's text colour and size;
 * - for administrators, the icons the companies' buttons need and that
 *   aren't drawn yet are drawn and kept (sb_emailsig_button_icons), so they
 *   show in signatures. The same call drops ones no button needs.
 */
(function () {
	'use strict';

	var cfg = window.sbEmailsigButtonIcons || {};
	var svgs = cfg.svgs || {};
	var cache = {};

	if (!window.SBEmailSigIcons) return;

	function draw(name, size, colour) {
		var k = name + '|' + size + '|' + colour;
		if (!cache[k]) {
			cache[k] = svgs[name] ? window.SBEmailSigIcons.png(svgs[name], { size: size, colour: colour }) : Promise.resolve('');
		}
		return cache[k];
	}

	function fill(root) {
		if (!root || !root.querySelectorAll) return;
		var imgs = root.querySelectorAll('img[data-sbes-btn-icon]');
		Array.prototype.forEach.call(imgs, function (img) {
			var src = img.getAttribute('src') || '';
			var key = img.getAttribute('data-sbes-btn-key') || '';
			// Kept files are named after their key: one that matches is right already.
			if (src.indexOf('data:image/png') === 0 || (key && src.indexOf('/' + key + '-') !== -1)) return;
			draw(img.getAttribute('data-sbes-btn-icon'), parseInt(img.getAttribute('data-sbes-btn-size'), 10) || 16, img.getAttribute('data-sbes-btn-colour') || '#ffffff').then(function (url) {
				if (url) img.setAttribute('src', url);
			});
		});
	}

	fill(document);

	// Previews redrawn after a change (Edit styles, Layout, popups).
	if (window.MutationObserver && document.body) {
		new MutationObserver(function (list) {
			list.forEach(function (m) {
				Array.prototype.forEach.call(m.addedNodes, function (n) {
					if (n.nodeType === 1) fill(n.parentNode || n);
				});
			});
		}).observe(document.body, { childList: true, subtree: true });
	}

	if (!cfg.save || !cfg.nonce || !window.fetch) return;

	var keys = Object.keys(cfg.missing || {});

	Promise.all(keys.map(function (k) {
		var m = cfg.missing[k];
		return draw(m.name, parseInt(m.size, 10) || 16, m.colour);
	})).then(function (urls) {
		var icons = {};
		keys.forEach(function (k, i) {
			if (urls[i]) icons[k] = urls[i];
		});
		return fetch(cfg.ajax, {
			method: 'POST',
			credentials: 'same-origin',
			body: new URLSearchParams({ action: 'sb_emailsig_button_icons', nonce: cfg.nonce, icons: JSON.stringify(icons) })
		});
	}).catch(function () {});
})();
