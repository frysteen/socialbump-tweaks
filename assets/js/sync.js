/**
 * Keeping open tabs in step (Sam, 3 November 2026): a change saved in one
 * admin tab shows in this site's other open tabs without a reload.
 *
 *   SBTweaksSync.send( topic, data )   tell the other tabs something changed
 *   SBTweaksSync.on( topic, fn )       run fn( data ) when another tab sends it
 *   SBTweaksSync.refresh( selectors )  load this page again in the background
 *                                      and swap in the fresh copy of each part
 *
 * Topics are module/thing, such as email-signatures/templates. A tab never
 * hears its own send(). refresh() keeps the address as it is (filters in the
 * query string come back the same) and resolves with the parts it swapped;
 * a part whose count changed is left alone. After a swap the document gets a
 * sb-tweaks:refreshed event (detail: the parts), so scripts that set things
 * up on load can do it again for the new markup. Click handlers on document
 * need nothing.
 *
 * BroadcastChannel where the browser has it, a storage event where it doesn't.
 */
(function () {
	'use strict';

	var NAME = 'sb-tweaks-sync';
	var handlers = {};
	var channel = null;

	function receive(msg) {
		if (!msg || !msg.topic || !handlers[msg.topic]) return;
		handlers[msg.topic].forEach(function (fn) {
			try { fn(msg.data); } catch (err) { if (window.console) window.console.error(err); }
		});
	}

	if ('BroadcastChannel' in window) {
		channel = new window.BroadcastChannel(NAME);
		channel.onmessage = function (e) { receive(e.data); };
	} else {
		window.addEventListener('storage', function (e) {
			if (e.key !== NAME || !e.newValue) return;
			try { receive(JSON.parse(e.newValue)); } catch (err) { /* not ours */ }
		});
	}

	function send(topic, data) {
		var msg = { topic: String(topic), data: data === undefined ? null : data, at: Date.now() };

		if (channel) {
			channel.postMessage(msg);
			return;
		}

		try {
			window.localStorage.setItem(NAME, JSON.stringify(msg));
			window.localStorage.removeItem(NAME);
		} catch (err) { /* storage off: nothing to tell */ }
	}

	function on(topic, fn) {
		(handlers[topic] = handlers[topic] || []).push(fn);
	}

	function load(selectors) {
		return window.fetch(window.location.href, { credentials: 'same-origin', cache: 'no-store' })
			.then(function (res) {
				if (!res.ok) throw new Error('HTTP ' + res.status);
				return res.text();
			})
			.then(function (html) {
				var doc = new window.DOMParser().parseFromString(html, 'text/html');
				var swapped = [];

				selectors.forEach(function (sel) {
					var now = document.querySelectorAll(sel);
					var fresh = doc.querySelectorAll(sel);
					if (!now.length || now.length !== fresh.length) return;

					Array.prototype.forEach.call(now, function (el, i) {
						var part = document.importNode(fresh[i], true);
						el.parentNode.replaceChild(part, el);
						swapped.push(part);
					});
				});

				if (swapped.length) {
					document.dispatchEvent(new window.CustomEvent('sb-tweaks:refreshed', { detail: swapped }));
				}

				return swapped;
			});
	}

	// One load at a time: changes that land while one is going get one more
	// load after it, not one each.
	var busy = null;
	var again = null;

	function refresh(selectors) {
		selectors = [].concat(selectors);

		if (busy) {
			again = again || busy.then(null, function () {}).then(function () {
				again = null;
				return refresh(selectors);
			});
			return again;
		}

		busy = load(selectors);
		busy.then(function () { busy = null; }, function () { busy = null; });

		return busy;
	}

	window.SBTweaksSync = { send: send, on: on, refresh: refresh };
})();
