/**
 * Email link buttons (data-sb-emailsig-send="{person id}"): opens the
 * framework's popup (SBTweaksConfirm, assets/js/confirm.js in SocialBUMP
 * Tweaks) with the email as Settings words it, filled in for the person, to
 * change before Send. To starts as their Email, else their company's, else
 * blank, and can be changed (Sam, 1 November 2026). Email picks New or
 * Updated Signature (Sam, 31 October 2026), Updated first for someone sent
 * their link before; each keeps its own changes while the popup is open.
 * Cancel, the X, Escape or a click outside close it.
 * The answer shows in the card's status line, or the one named by
 * data-sb-emailsig-status.
 */
(function () {
	'use strict';

	var cfg = window.sbEmailsigNotify || {};

	function post(data) {
		data.nonce = cfg.nonce || '';
		return fetch(cfg.ajax, {
			method: 'POST',
			credentials: 'same-origin',
			body: new URLSearchParams(data)
		}).then(function (r) { return r.json(); });
	}

	function field(label, el) {
		var wrap = document.createElement('label');
		var span = document.createElement('span');
		wrap.className = 'sb-tweaks-dialog__field';
		span.textContent = label;
		wrap.appendChild(span);
		wrap.appendChild(el);
		return wrap;
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('[data-sb-emailsig-send]') : null;
		if (!btn || btn.disabled) return;
		e.preventDefault();

		var id = btn.getAttribute('data-sb-emailsig-send');
		var card = btn.closest('[data-sb-emailsig-card]');
		var status = document.getElementById(btn.getAttribute('data-sb-emailsig-status') || '') ||
			(card && card.querySelector('[role="status"]'));
		var label = btn.innerHTML;
		var icon = !!btn.querySelector('svg');

		// A toast where the page has them (SocialBUMP UI), else the status line.
		function say(msg, isError) {
			if (msg && window.SBTweaksUI) {
				window.SBTweaksUI.toast(msg, isError ? 'danger' : 'ok');
				return;
			}
			if (!status) return;
			status.textContent = msg;
			status.classList.toggle('is-error', !!isError);
		}

		// Buttons with an icon keep it and spin; the rest say what's happening.
		function busy(text) {
			btn.disabled = !!text;
			if (icon) {
				btn.classList.toggle('is-loading', !!text);
				if (!text) btn.innerHTML = label;
				return;
			}
			if (text) btn.textContent = text; else btn.innerHTML = label;
		}

		say('');
		busy('Opening…');

		post({ action: 'sb_emailsig_draft', id: id })
			.then(function (res) {
				busy('');

				if (!res || !res.success) {
					say((res && typeof res.data === 'string' && res.data) || 'Not opened. Reload the page and try again.', true);
					return;
				}

				var d = res.data;
				var to = document.createElement('input');
				var subject = document.createElement('input');
				var message = document.createElement('textarea');
				var body = document.createElement('div');

				var drafts = d.drafts || {};
				var kind = d.kind;
				var pick = document.createElement('select');
				Object.keys(drafts).forEach(function (k) {
					var o = document.createElement('option');
					o.value = k;
					o.textContent = drafts[k].label;
					pick.appendChild(o);
				});
				pick.value = kind;
				pick.addEventListener('change', function () {
					if (drafts[kind]) {
						drafts[kind].subject = subject.value;
						drafts[kind].message = message.value;
					}
					kind = pick.value;
					subject.value = drafts[kind].subject;
					message.value = drafts[kind].message;
				});

				to.type = 'email';
				to.value = d.to || '';
				to.autocomplete = 'off';
				to.placeholder = 'name@example.com';
				subject.type = 'text';
				subject.value = d.subject;
				message.rows = 10;
				message.value = d.message;
				body.appendChild(field('To', to));
				if (pick.options.length > 1) body.appendChild(field('Email', pick));
				body.appendChild(field('Subject', subject));
				body.appendChild(field('Message', message));

				if (!window.SBTweaksConfirm) {
					say('The popup didn\'t load. Reload the page and try again.', true);
					return;
				}

				var $modal = window.SBTweaksConfirm.open({
					title: 'Email ' + (d.name || 'them') + ' their signature link',
					body: body,
					action: 'Send'
				}, function () {
					busy('Sending…');

					post({ action: 'sb_emailsig_send', id: id, kind: kind, to: to.value.trim(), subject: subject.value, message: message.value })
						.then(function (res2) {
							if (res2 && res2.success) {
								say(res2.data || 'Sent.', false);
							} else {
								say((res2 && typeof res2.data === 'string' && res2.data) || 'Not sent. Reload the page and try again.', true);
							}
						})
						.catch(function () { say('Not sent. Reload the page and try again.', true); })
						.then(function () { busy(''); });
				});

				// Send waits for an address in To.
				var $go = $modal && $modal.find ? $modal.find('.sb-tweaks-dialog__go') : null;
				function ready() {
					if ($go) $go.prop('disabled', !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(to.value.trim()));
				}
				to.addEventListener('input', ready);
				ready();
				if (!to.value) to.focus();
			})
			.catch(function () {
				busy('');
				say('Not opened. Reload the page and try again.', true);
			});
	});
})();
