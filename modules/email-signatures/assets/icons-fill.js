/**
 * Icon sets drawn before icons were added (in an update) miss them, and
 * those show in the shipped colour. On the Email Signatures pages, for
 * administrators, this draws each such company's contact icons for every
 * template in its colours and keeps them, as a brand colour Save does
 * (brand.js, Companies::brand_icons()), one company at a time so the saves
 * don't overwrite each other (SB_EmailSig_Icons::stale(), Sam, 1 November 2026).
 */
(function () {
	'use strict';

	var cfg = window.sbEmailsigIconsFill || {};

	if (!window.SBEmailSigIcons || !window.fetch || !cfg.companies || !cfg.companies.length) return;

	function draw(d) {
		var brand = d.brand || {};
		var pick = function (src, saved) { return src && brand[src] ? brand[src] : saved; };
		var ids = Object.keys(d.templates || {});

		return Promise.all(ids.map(function (tpl) {
			var c = d.templates[tpl];
			var names = Object.keys(c.svgs || {});

			return Promise.all(names.map(function (n) {
				return window.SBEmailSigIcons.png(c.svgs[n], { size: c.size, colour: pick(c.src, c.colour), bg: c.bg ? pick(c.bgsrc, c.bg) : '', radius: c.radius || 0 });
			})).then(function (pngs) {
				var set = {};
				names.forEach(function (n, i) { set[n] = pngs[i]; });
				return set;
			});
		})).then(function (sets) {
			var out = {};
			ids.forEach(function (tpl, i) { out[tpl] = { contact: sets[i] }; });
			return out;
		});
	}

	cfg.companies.reduce(function (chain, one) {
		return chain.then(function () {
			return draw(one.draw).then(function (icons) {
				return fetch(cfg.ajax, {
					method: 'POST',
					credentials: 'same-origin',
					body: new URLSearchParams({ action: 'sb_emailsig_brand_icons', nonce: cfg.nonce, co: one.co, icons: JSON.stringify(icons) })
				});
			});
		});
	}, Promise.resolve()).catch(function () {});
})();
