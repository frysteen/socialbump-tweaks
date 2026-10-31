/**
 * Live updating (SB_EmailSig_Live): asks the site whether anything has
 * changed, every 5 seconds while the page is being used, every 30 once it
 * has sat for 3 minutes, never while its tab is hidden, and straight away
 * when it's shown again or touched after sitting. Changed cards are drawn
 * again where they belong (or taken away), with the counts following; a
 * person's page reloads. Tabs in this browser tell each other the moment
 * this one does something, so they ask at once.
 */
(function () {
	'use strict';

	var cfg = window.sbEmailsigLive;
	if (!cfg || !window.fetch || !window.FormData) return;

	var FAST = 5000;
	var SLOW = 30000;
	var IDLE = 180000;

	var send = window.fetch.bind(window);
	var rev = cfg.rev || 0;
	var busy = false;
	var again = false;
	var timer = null;
	var touched = Date.now();
	var bc = null;
	try { bc = window.BroadcastChannel ? new BroadcastChannel('sb-emailsig-live') : null; } catch (e) { bc = null; }

	function idle() {
		return Date.now() - touched > IDLE;
	}

	function schedule() {
		clearTimeout(timer);
		if (document.hidden) return;
		timer = setTimeout(poll, idle() ? SLOW : FAST);
	}

	function shown() {
		return [].map.call(document.querySelectorAll('[data-sb-emailsig-id]'), function (el) {
			return el.getAttribute('data-sb-emailsig-id');
		}).join(',');
	}

	function poll() {
		clearTimeout(timer);
		if (busy) { again = true; return; }
		busy = true;

		var fd = new FormData();
		fd.append('action', 'sb_emailsig_live');
		fd.append('since', rev);
		Object.keys(cfg.ctx || {}).forEach(function (k) { fd.append(k, cfg.ctx[k]); });
		if (cfg.ctx && cfg.ctx.where !== 'person') fd.append('ids', shown());

		send(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res || !res.success || !res.data) return;
				var d = res.data;
				if (d.reload) {
					window.location.reload();
					return;
				}
				if (d.cards && d.cards.length) apply(d.cards);
				rev = d.rev || rev;
			})
			.catch(function () { /* offline for a moment: try again next time */ })
			.then(function () {
				busy = false;
				if (again) { again = false; poll(); return; }
				schedule();
			});
	}

	/** Put each card where it belongs now, or take it away. */
	function apply(cards) {
		cards.forEach(function (c) {
			var old = document.querySelector('[data-sb-emailsig-id="' + c.id + '"]');
			var grid = null;

			if (c.section !== 'gone') {
				// Within its company on a page with each company's people under its own bar.
				var groups = document.querySelector('[data-sb-emailsig-group]');
				var scope = groups ? document.querySelector('[data-sb-emailsig-group="' + c.co + '"]') : document;
				var sec = scope ? scope.querySelector('[data-sb-emailsig-section="' + c.section + '"]') : null;
				grid = sec ? sec.querySelector('.sb-emailsig-people') : null;
			}

			if (!grid) {
				if (old) old.remove();
				return;
			}

			var tpl = document.createElement('template');
			tpl.innerHTML = (c.html || '').trim();
			var card = tpl.content.firstElementChild;
			if (!card) return;

			// Kept hidden if the search or company filter hides it.
			if (old && old.parentNode === grid) {
				old.replaceWith(card);
				return;
			}

			if (old) old.remove();
			var key = card.getAttribute('data-sb-emailsig-sort') || '';
			var next = null;
			Array.prototype.some.call(grid.children, function (el) {
				if ((el.getAttribute('data-sb-emailsig-sort') || '') > key) { next = el; return true; }
				return false;
			});
			grid.insertBefore(card, next);
			card.classList.add('is-arriving');
			setTimeout(function () { card.classList.remove('is-arriving'); }, 400);
		});

		var list = window.sbEmailsigListApi;
		if (list) {
			list.recount();
			list.filter();
		}
	}

	/** This page did something: ask now, and tell the other tabs to. */
	function poke() {
		setTimeout(function () {
			poll();
			if (bc) bc.postMessage('poke');
		}, 300);
	}

	function ours(body) {
		if (!body) return false;
		if (typeof body === 'string') return body.indexOf('sb_emailsig_live') !== -1;
		if (body instanceof FormData || body instanceof URLSearchParams) return body.get('action') === 'sb_emailsig_live';
		return false;
	}

	// Any other request this page makes to the site may have changed something.
	window.fetch = function (url, opts) {
		var p = send(url, opts);
		if (String(url).indexOf('admin-ajax.php') !== -1 && opts && opts.method && opts.method.toUpperCase() === 'POST' && !ours(opts.body)) {
			p.then(poke, function () {});
		}
		return p;
	};
	if (window.jQuery) {
		window.jQuery(document).ajaxComplete(function (e, xhr, s) {
			if (s && s.url && s.url.indexOf('admin-ajax.php') !== -1 && !ours(s.data)) poke();
		});
	}

	if (bc) bc.onmessage = function () { if (!document.hidden) poll(); };

	document.addEventListener('visibilitychange', function () {
		if (document.hidden) {
			clearTimeout(timer);
		} else {
			touched = Date.now();
			poll();
		}
	});

	['mousemove', 'keydown', 'scroll', 'touchstart', 'click'].forEach(function (ev) {
		window.addEventListener(ev, function () {
			var was = idle();
			touched = Date.now();
			if (was) poll();
		}, { passive: true });
	});

	// Just arrived after saving something on another page: the other tabs ask now.
	if (bc) bc.postMessage('poke');
	schedule();
})();
