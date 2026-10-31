/**
 * Copy signature to clipboard as rich HTML.
 * Button: data-sb-emailsig-copy="{preview id}", data-sb-emailsig-status="{status id}".
 * The exact HTML sits in <script type="application/json" id="{preview id}-html">.
 */
(function () {
	'use strict';

	function setStatus(el, msg, isError) {
		if (!el) return;
		el.textContent = msg;
		el.classList.toggle('is-error', !!isError);
	}

	// Fallback: select the rendered signature and copy the selection.
	function copySelection(node) {
		var sel = window.getSelection();
		var range = document.createRange();
		range.selectNodeContents(node);
		sel.removeAllRanges();
		sel.addRange(range);
		var ok = false;
		try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
		sel.removeAllRanges();
		return ok;
	}

	function copy(html, node) {
		var plain = node.innerText || node.textContent || '';

		// Preferred: put our exact HTML on the clipboard, no page styles mixed in.
		if (window.ClipboardItem && navigator.clipboard && navigator.clipboard.write) {
			try {
				var item = new ClipboardItem({
					'text/html': new Blob([html], { type: 'text/html' }),
					'text/plain': new Blob([plain], { type: 'text/plain' })
				});
				return navigator.clipboard.write([item]).then(
					function () { return true; },
					function () { return copySelection(node); }
				);
			} catch (e) { /* fall through */ }
		}
		return Promise.resolve(copySelection(node));
	}

	// Plain text copy, used for the signature page link in the admin.
	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('[data-sb-emailsig-copy-text]') : null;
		if (!btn) return;
		e.preventDefault();
		var text = btn.getAttribute('data-sb-emailsig-copy-text');
		var label = btn.textContent;
		var done = function () {
			// An icon button has no words to swap: a toast says it instead,
			// where the page has them (SocialBUMP UI).
			// With a tooltip (SocialBUMP UI), the tooltip says Copied.
			if (btn.querySelector('svg')) {
				var tip = btn.getAttribute('data-sb-tip');
				if (tip) {
					btn.setAttribute('data-sb-tip', 'Copied');
					setTimeout(function () { btn.setAttribute('data-sb-tip', tip); }, 1500);
				} else if (window.SBTweaksUI) {
					window.SBTweaksUI.toast('Link copied', 'ok');
				}
				btn.classList.add('is-done');
				setTimeout(function () { btn.classList.remove('is-done'); }, 1500);
				return;
			}
			btn.textContent = 'Copied';
			setTimeout(function () { btn.textContent = label; }, 1500);
		};
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done);
		} else {
			var ta = document.createElement('textarea');
			ta.value = text;
			document.body.appendChild(ta);
			ta.select();
			try { document.execCommand('copy'); done(); } catch (err) { /* ignore */ }
			document.body.removeChild(ta);
		}
	});

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('[data-sb-emailsig-copy]') : null;
		if (!btn) return;
		e.preventDefault();

		var id = btn.getAttribute('data-sb-emailsig-copy');
		var node = document.getElementById(id);
		var src = document.getElementById(id + '-html');
		var status = document.getElementById(btn.getAttribute('data-sb-emailsig-status'));
		if (!node) return;

		var html = node.innerHTML;
		if (src) {
			try { html = JSON.parse(src.textContent); } catch (err) { /* use rendered HTML */ }
		}

		copy(html, node).then(function (ok) {
			setStatus(status, ok ? 'Copied. Now paste it into your signature settings.' : 'Copy failed. Select the signature and copy it manually.', !ok);
			if (ok) mark(btn);
		});
	});

	// Copy the HTML itself as plain text, for apps that take code (Thunderbird).
	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('[data-sb-emailsig-copy-code]') : null;
		if (!btn) return;
		e.preventDefault();

		var id = btn.getAttribute('data-sb-emailsig-copy-code');
		var src = document.getElementById(id + '-html');
		var status = document.getElementById(btn.getAttribute('data-sb-emailsig-status'));
		var html = '';
		try { html = JSON.parse(src.textContent); } catch (err) { html = ''; }
		if (!html) return;

		var finish = function (ok) {
			setStatus(status, ok ? 'Copied the HTML code. Now paste it in.' : 'Copy failed. Try again in another browser.', !ok);
			if (ok) mark(btn);
		};
		var fallback = function () {
			var ta = document.createElement('textarea');
			ta.value = html;
			ta.setAttribute('readonly', '');
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild(ta);
			ta.select();
			var ok = false;
			try { ok = document.execCommand('copy'); } catch (err) { ok = false; }
			document.body.removeChild(ta);
			finish(ok);
		};
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(html).then(function () { finish(true); }, fallback);
		} else {
			fallback();
		}
	});

	// Mail app picker (the install guides): a button showing the chosen app,
	// and a list of apps with their icons. Choosing one shows its steps.
	document.querySelectorAll('[data-sb-emailsig-picker]').forEach(function (picker) {
		var button = picker.querySelector('.picker__button');
		var list = picker.querySelector('[role="listbox"]');
		var options = Array.prototype.slice.call(list.querySelectorAll('[role="option"]'));
		var active = 0;

		var highlight = function (i) {
			active = (i + options.length) % options.length;
			options.forEach(function (o, n) { o.classList.toggle('is-active', n === active); });
			list.setAttribute('aria-activedescendant', options[active].id);
			options[active].scrollIntoView({ block: 'nearest' });
		};
		var open = function () {
			list.hidden = false;
			button.setAttribute('aria-expanded', 'true');
			highlight(options.findIndex(function (o) { return o.getAttribute('aria-selected') === 'true'; }));
			list.focus();
		};
		var close = function (focus) {
			list.hidden = true;
			button.setAttribute('aria-expanded', 'false');
			if (focus) button.focus();
		};
		var choose = function (i) {
			options.forEach(function (o, n) {
				var on = n === i;
				o.setAttribute('aria-selected', on ? 'true' : 'false');
				var panel = document.getElementById(o.getAttribute('data-panel'));
				if (panel) panel.hidden = !on;
			});
			button.querySelector('img').src = options[i].querySelector('img').src;
			button.querySelector('span').textContent = options[i].textContent;
			close(true);
		};

		button.addEventListener('click', function () {
			if (list.hidden) { open(); } else { close(true); }
		});
		button.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); open(); }
		});
		options.forEach(function (o, i) {
			o.addEventListener('click', function () { choose(i); });
			o.addEventListener('mousemove', function () { if (active !== i) highlight(i); });
		});
		list.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowDown') { e.preventDefault(); highlight(active + 1); }
			else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(active - 1); }
			else if (e.key === 'Home') { e.preventDefault(); highlight(0); }
			else if (e.key === 'End') { e.preventDefault(); highlight(options.length - 1); }
			else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); choose(active); }
			else if (e.key === 'Escape') { e.preventDefault(); close(true); }
			else if (e.key === 'Tab') { close(false); }
		});
		document.addEventListener('click', function (e) {
			if (!list.hidden && !picker.contains(e.target)) close(false);
		});
	});

	// Signature style (the person's page, with more than one template): swap
	// the signature, the HTML its copy buttons use, their record of what was
	// copied, and the HTML code box. The templates sit in {preview id}-styles.
	// Tabs (data-sb-emailsig-style-tabs="{preview id}", a button per template)
	// or the older select (data-sb-emailsig-style="{preview id}").
	document.addEventListener('click', function (e) {
		var tab = e.target.closest ? e.target.closest('[data-sb-emailsig-style-tab]') : null;
		var group = tab ? tab.closest('[data-sb-emailsig-style-tabs]') : null;
		if (!group || tab.getAttribute('aria-current') === 'page') return;
		group.querySelectorAll('[data-sb-emailsig-style-tab]').forEach(function (t) {
			if (t === tab) t.setAttribute('aria-current', 'page');
			else t.removeAttribute('aria-current');
		});
		style(group.getAttribute('data-sb-emailsig-style-tabs'), tab.getAttribute('data-sb-emailsig-style-tab'));
	});

	document.addEventListener('change', function (e) {
		var select = e.target;
		if (!select.matches || !select.matches('[data-sb-emailsig-style]')) return;
		style(select.getAttribute('data-sb-emailsig-style'), select.value);
	});

	function style(id, key) {
		var styles = null;
		try { styles = JSON.parse(document.getElementById(id + '-styles').textContent); } catch (err) { styles = null; }
		var sig = styles && styles[key];
		var node = document.getElementById(id);
		var src = document.getElementById(id + '-html');
		if (!sig || !node) return;

		node.innerHTML = sig.html;
		if (src) src.textContent = JSON.stringify(sig.html);

		var mark = JSON.stringify(sig.mark);
		document.querySelectorAll('[data-sb-emailsig-copy="' + id + '"], [data-sb-emailsig-copy-code="' + id + '"]').forEach(function (btn) {
			if (btn.hasAttribute('data-sb-emailsig-mark')) btn.setAttribute('data-sb-emailsig-mark', mark);
			setStatus(document.getElementById(btn.getAttribute('data-sb-emailsig-status')), '', false);
		});
		document.querySelectorAll('textarea.code').forEach(function (ta) { ta.value = sig.html; });
	}

	// Tell the site which version was copied (SB_EmailSig_Notify), so it can
	// say when the signature changes after. Quietly: copying worked anyway.
	function mark(btn) {
		var data;
		try { data = JSON.parse(btn.getAttribute('data-sb-emailsig-mark') || 'null'); } catch (e) { data = null; }
		if (!data || !data.ajax || !window.fetch) return;

		fetch(data.ajax, {
			method: 'POST',
			credentials: 'same-origin',
			body: new URLSearchParams({ action: 'sb_emailsig_copied', id: data.id, key: data.key, fp: data.fp, t: data.t || '' })
		}).then(function (r) { return r.json(); }).then(function (res) {
			if (!res || res.data !== 'recorded') return;
			var card = btn.closest('[data-sb-emailsig-card]');
			var state = card && card.querySelector('.sb-emailsig-state');
			if (state) state.remove();
			var send = card && card.querySelector('[data-sb-emailsig-send]');
			if (send && send.textContent === 'Resend') {
				send.textContent = 'Email link';
				send.classList.remove('sb-emailsig-resend', 'btn--resend');
			}
		}).catch(function () { /* not recorded: no harm */ });
	}
})();
