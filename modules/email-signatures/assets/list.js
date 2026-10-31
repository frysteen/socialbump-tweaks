/**
 * The Signatures page.
 *
 * The Active switch on each card (here, and on the team page for
 * administrators and editors) publishes the person or puts them back to
 * draft, straight away. The card dims when they are off and, on the
 * Signatures page, moves between Active and Inactive in name order, the
 * counts following (Sam, 30 October 2026).
 *
 * The search (data-sb-emailsig-find: name, job, email or mobile) and the
 * Company filter (data-sb-emailsig-pick), on the Signatures page and the
 * team pages, hide the cards, sections and companies that don't match;
 * Clear filters shows once one is set (SB_EmailSig_Admin::filters()).
 *
 * Preview (data-sb-emailsig-preview="{signature id}") opens the framework's
 * popup (SBTweaksConfirm) with the person's signature as it will paste, on
 * white like an email, and Edit to go to the person. Its top is a header bar
 * (Sam, 31 October 2026): the name, and at the far right a person's template
 * tabs, or on a template's Preview a dropdown of Layout View and everyone, to
 * see it as one of them (data-sb-emailsig-people, wp_ajax_sb_emailsig_tpl_sig).
 */
(function () {
	'use strict';

	var cfg = window.sbEmailsigList || {};

	function tell(card, msg) {
		if (window.SBTweaksUI) {
			window.SBTweaksUI.toast(msg, 'danger');
			return;
		}
		var status = card && card.querySelector('[role="status"]');
		if (status) {
			status.textContent = msg;
			status.classList.add('is-error');
		}
	}

	/** Each section's count, Active's note when it's empty, and the others hidden when empty. */
	function recount() {
		document.querySelectorAll('[data-sb-emailsig-section]').forEach(function (sec) {
			var grid = sec.querySelector('.sb-emailsig-people');
			var n = grid ? grid.children.length : 0;
			var count = sec.querySelector('[data-sb-emailsig-count]');
			var none = sec.querySelector('.sb-emailsig-people-none');
			if (count) count.textContent = String(n);
			if (none) none.hidden = n > 0;
			if (sec.getAttribute('data-sb-emailsig-section') !== 'active') sec.hidden = n === 0;
		});
		// Each company's bar: its Active, Inactive and Deleted.
		document.querySelectorAll('[data-sb-emailsig-group]').forEach(function (g) {
			g.querySelectorAll('[data-sb-emailsig-stat]').forEach(function (b) {
				var grid = g.querySelector('[data-sb-emailsig-section="' + b.getAttribute('data-sb-emailsig-stat') + '"] .sb-emailsig-people');
				b.textContent = String(grid ? grid.children.length : 0);
			});
		});
	}

	/* A company's name folds its people away (and back); remembered in this
	   browser (Sam, 30 October 2026). Searching shows them all the same. */
	var FOLD = 'sbEmailsigFolded';
	function folded() {
		try { return JSON.parse(window.localStorage.getItem(FOLD) || '[]') || []; } catch (e) { return []; }
	}
	function fold(g, shut) {
		g.classList.toggle('is-folded', shut);
		var b = g.querySelector('[data-sb-emailsig-fold]');
		if (b) b.setAttribute('aria-expanded', shut ? 'false' : 'true');
	}
	(function () {
		var shut = folded();
		document.querySelectorAll('[data-sb-emailsig-group]').forEach(function (g) {
			if (shut.indexOf(g.getAttribute('data-sb-emailsig-group')) !== -1) fold(g, true);
		});
	})();
	document.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('[data-sb-emailsig-fold]') : null;
		if (!b) return;
		var g = b.closest('[data-sb-emailsig-group]');
		if (!g) return;
		var shut = !g.classList.contains('is-folded');
		fold(g, shut);
		var id = g.getAttribute('data-sb-emailsig-group');
		var list = folded().filter(function (x) { return x !== id; });
		if (shut) list.push(id);
		try { window.localStorage.setItem(FOLD, JSON.stringify(list)); } catch (err) { /* private browsing */ }
	});

	/** A card switched on or off: out of its section and into the other, in name order. */
	function move(card, active) {
		// Within its own company on the team page's everyone view.
		var scope = (card && card.closest('[data-sb-emailsig-group]')) || document;
		var to = scope.querySelector('[data-sb-emailsig-section="' + (active ? 'active' : 'inactive') + '"]');
		var from = card ? card.closest('[data-sb-emailsig-section]') : null;
		var grid = to ? to.querySelector('.sb-emailsig-people') : null;
		if (!from || !grid || to === from) return;
		if (window.SBTweaksUI && window.SBTweaksUI.hideTip) window.SBTweaksUI.hideTip();
		card.classList.add('is-leaving');
		setTimeout(function () {
			var key = card.getAttribute('data-sb-emailsig-sort') || '';
			var next = null;
			Array.prototype.some.call(grid.children, function (c) {
				if ((c.getAttribute('data-sb-emailsig-sort') || '') > key) { next = c; return true; }
				return false;
			});
			grid.insertBefore(card, next);
			card.classList.remove('is-leaving');
			card.classList.add('is-arriving');
			setTimeout(function () { card.classList.remove('is-arriving'); }, 400);
			recount();
			filter();
			if (window.SBTweaksUI) window.SBTweaksUI.toast('Moved to ' + (active ? 'Active' : 'Inactive'), 'ok');
		}, 180);
	}

	// Just restored (?restored=id): bring the card into view, highlighted.
	var restored = document.querySelector('.sb-emailsig-person.is-restored');
	if (restored && restored.closest('.is-folded')) fold(restored.closest('[data-sb-emailsig-group]'), false);
	if (restored && restored.scrollIntoView) {
		setTimeout(function () { restored.scrollIntoView({ block: 'center', behavior: 'smooth' }); }, 150);
		setTimeout(function () { restored.classList.remove('is-restored'); }, 2600);
	}

	document.addEventListener('change', function (e) {
		var box = e.target;
		if (!box.matches || !box.matches('[data-sb-emailsig-active]')) return;

		var card = box.closest('[data-sb-emailsig-card]');
		var on = box.checked;
		var body = new URLSearchParams({
			action: 'sb_emailsig_active',
			nonce: cfg.nonce || '',
			id: box.getAttribute('data-sb-emailsig-active'),
			active: on ? '1' : ''
		});

		box.disabled = true;
		if (card) card.classList.toggle('is-on', on);

		fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				// A reason from the site (its limit, its company off) is shown as it is.
				if (!res || !res.success) throw new Error(res && typeof res.data === 'string' ? res.data : '');
				var active = res.data === 'publish';
				box.checked = active;
				if (card) card.classList.toggle('is-on', active);
				move(card, active);
			})
			.catch(function (err) {
				// Put it back as it was and say so.
				box.checked = !on;
				if (card) card.classList.toggle('is-on', !on);
				tell(card, (err && err.message) || 'Not saved. Reload the page and try again.');
			})
			.then(function () { box.disabled = false; });
	});

	// A company's switch (SB_EmailSig_Switch): off asks first, then the page
	// reloads, as nearly everything on it changes.
	document.addEventListener('change', function (e) {
		var box = e.target;
		if (!box.matches || !box.matches('[data-sb-emailsig-company-switch]')) return;

		var co = cfg.company || {};
		var on = box.checked;
		var name = box.getAttribute('data-sb-emailsig-company-name') || 'this company';
		var bar = box.closest('.sb-ui-bar');

		function save() {
			box.disabled = true;
			fetch(co.ajax, {
				method: 'POST',
				credentials: 'same-origin',
				body: new URLSearchParams({ action: 'sb_emailsig_company_switch', nonce: co.nonce || '', company: box.getAttribute('data-sb-emailsig-company-switch'), on: on ? '1' : '' })
			})
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res || !res.success) throw new Error(res && typeof res.data === 'string' ? res.data : '');
					window.location.reload();
				})
				.catch(function (err) {
					box.checked = !on;
					box.disabled = false;
					tell(bar, (err && err.message) || 'Not saved. Reload the page and try again.');
				});
		}

		if (on) {
			save();
			return;
		}

		// Stays on until they say so.
		box.checked = true;

		if (!window.SBTweaksConfirm) {
			tell(bar, 'The popup didn\'t load. Reload the page and try again.');
			return;
		}

		window.SBTweaksConfirm.open({
			title: 'Switch ' + name + ' off',
			message: 'Its team page and people\'s pages close, and the photos, icons, logos, banner and tracked links in their signatures stop working. Nothing is deleted: switch it back on to bring it all back.',
			action: 'Switch off'
		}, function () {
			box.checked = false;
			on = false;
			save();
		});
	});

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('[data-sb-emailsig-preview]') : null;
		if (!btn) return;
		e.preventDefault();

		var id = btn.getAttribute('data-sb-emailsig-preview');
		var src = document.getElementById(id + '-html');
		var html = '';
		try { html = JSON.parse(src.textContent); } catch (err) { html = ''; }

		if (!html || !window.SBTweaksConfirm) {
			tell(btn.closest('[data-sb-emailsig-card]'), 'The preview didn\'t open. Reload the page and try again.');
			return;
		}

		var wrap = document.createElement('div');
		var sig = document.createElement('div');
		wrap.className = 'sb-emailsig-preview-pop';
		sig.className = 'sb-emailsig-preview-pop__sig';
		sig.innerHTML = html;
		wrap.appendChild(sig);

		// Their templates as tabs in the header bar, when they have more than
		// one (Sam, 30 October 2026). The first is the card's own; the rest
		// are fetched once each.
		var body = wrap;
		var pick = null;
		var tpls = null;
		try { tpls = JSON.parse(btn.getAttribute('data-sb-emailsig-tpls') || 'null'); } catch (err) { tpls = null; }
		if (tpls && tpls.list && tpls.list.length > 1) {
			var made = {};
			made[tpls.list[0][0]] = html;
			var seg = document.createElement('div');
			seg.className = 'sb-ui-seg sb-emailsig-preview-pop__tabs';
			seg.setAttribute('role', 'group');
			seg.setAttribute('aria-label', 'Template');
			tpls.list.forEach(function (t, i) {
				var b = document.createElement('button');
				b.type = 'button';
				b.textContent = t[1];
				if (i === 0) b.setAttribute('aria-current', 'page');
				b.addEventListener('click', function () {
					if (b.getAttribute('aria-current') === 'page') return;
					seg.querySelectorAll('button').forEach(function (o) { o.removeAttribute('aria-current'); });
					b.setAttribute('aria-current', 'page');
					if (made[t[0]] !== undefined) {
						sig.innerHTML = made[t[0]];
						return;
					}
					wrap.classList.add('is-loading');
					var data = new URLSearchParams({ action: 'sb_emailsig_card_sig', id: tpls.id, key: tpls.key, template: t[0] });
					fetch(tpls.ajax, { method: 'POST', credentials: 'same-origin', body: data })
						.then(function (r) { return r.json(); })
						.then(function (res) {
							if (!res || !res.success) throw new Error('no');
							made[t[0]] = res.data;
							if (b.getAttribute('aria-current') === 'page') sig.innerHTML = res.data;
						})
						.catch(function () {
							tell(null, 'That template didn\'t load. Try again.');
						})
						.then(function () { wrap.classList.remove('is-loading'); });
				});
				seg.appendChild(b);
			});
			pick = seg;
		}

		// A template's Preview: Layout View (its placeholders), or anyone in
		// it, fetched once each (Sam, 31 October 2026).
		var people = null;
		try { people = JSON.parse(btn.getAttribute('data-sb-emailsig-people') || 'null'); } catch (err) { people = null; }
		if (!pick && people && people.list && people.list.length) {
			var shown = { '': html };
			var sel = document.createElement('select');
			sel.className = 'sb-emailsig-preview-pop__person';
			sel.setAttribute('aria-label', 'Show as');
			sel.appendChild(new Option('Layout View', ''));
			// Under their companies when there's more than one, as the person page's menu.
			var group = null;
			people.list.forEach(function (p) {
				if (p[2] && (!group || group.label !== p[2])) {
					group = document.createElement('optgroup');
					group.label = p[2];
					sel.appendChild(group);
				}
				(p[2] ? group : sel).appendChild(new Option(p[1], String(p[0])));
			});
			sel.addEventListener('change', function () {
				var who = sel.value;
				if (shown[who] !== undefined) {
					sig.innerHTML = shown[who];
					return;
				}
				wrap.classList.add('is-loading');
				var data = new URLSearchParams({ action: 'sb_emailsig_tpl_sig', nonce: people.nonce, id: who, template: people.template });
				fetch(people.ajax, { method: 'POST', credentials: 'same-origin', body: data })
					.then(function (r) { return r.json(); })
					.then(function (res) {
						if (!res || !res.success) throw new Error('no');
						shown[who] = res.data;
						if (sel.value === who) sig.innerHTML = res.data;
					})
					.catch(function () {
						tell(null, 'That preview didn\'t load. Try again.');
					})
					.then(function () { wrap.classList.remove('is-loading'); });
			});
			pick = sel;
		}

		var edit = btn.getAttribute('data-sb-emailsig-edit');
		var modal = window.SBTweaksConfirm.open({
			title: btn.getAttribute('data-sb-emailsig-name') || 'Signature',
			body: body,
			cancel: 'Close',
			action: btn.getAttribute('data-sb-emailsig-action') || 'Edit'
		}, function () {
			if (edit) window.location.href = edit;
		});

		if (modal && modal.addClass) modal.addClass('sb-emailsig-preview-modal');

		// The top as a header bar: the name, and the tabs or dropdown at the far right.
		var title = modal && modal[0] ? modal[0].querySelector('.sb-tweaks-dialog > h2') : null;
		if (title) {
			var head = document.createElement('div');
			head.className = 'sb-emailsig-preview-pop__head';
			title.parentNode.insertBefore(head, title);
			head.appendChild(title);
			if (pick) {
				var end = document.createElement('div');
				end.className = 'sb-emailsig-preview-pop__pick';
				end.appendChild(pick);
				head.appendChild(end);
			}
		}

		// A person's preview: their card's link and email buttons on the left
		// of the footer (Sam, 30 October 2026). Copies, so copying, opening and
		// emailing work as they do on the card.
		var card = btn.closest('.sb-emailsig-person[data-sb-emailsig-card]');
		var foot = modal && modal[0] ? modal[0].querySelector('.sb-tweaks-dialog__buttons') : null;
		if (card && foot) {
			var tools = document.createElement('span');
			tools.className = 'sb-emailsig-preview-pop__tools';
			card.querySelectorAll('.sb-emailsig-person__actions > [data-sb-emailsig-copy-text], .sb-emailsig-person__actions > a[target="_blank"], .sb-emailsig-person__actions > [data-sb-emailsig-send]').forEach(function (b) {
				var c = b.cloneNode(true);
				c.classList.remove('button-small', 'is-done', 'is-loading');
				c.disabled = false;
				if (c.classList.contains('sb-emailsig-icon')) c.classList.add('sb-tweaks-button--icon');
				tools.appendChild(c);
			});
			if (tools.children.length) foot.insertBefore(tools, foot.firstChild);
		}

		// A template's Preview (Sam, 31 October 2026): Edit styles and Edit
		// Layout as plain buttons with their icons on the left, and only
		// Close, with its icon, on the right.
		var links = null;
		try { links = JSON.parse(btn.getAttribute('data-sb-emailsig-foot') || 'null'); } catch (err) { links = null; }
		if (links && links.length && foot) {
			var left = document.createElement('span');
			left.className = 'sb-emailsig-preview-pop__tools';
			links.forEach(function (l) {
				var a = document.createElement('a');
				a.className = 'button';
				a.href = l[0];
				a.innerHTML = l[2];
				a.appendChild(document.createTextNode(l[1]));
				left.appendChild(a);
			});
			foot.insertBefore(left, foot.firstChild);
			var go = foot.querySelector('.sb-tweaks-dialog__go');
			if (go) go.remove();
			var shut = foot.querySelector('.sb-tweaks-dialog__cancel');
			var x = btn.getAttribute('data-sb-emailsig-close-icon');
			if (shut && x) shut.insertAdjacentHTML('afterbegin', x);
		}
	});

	/* Import: pick the CSV, then straight to its preview (nothing is saved there until Import). */
	document.addEventListener('click', function (e) {
		var pick = e.target.closest ? e.target.closest('[data-sb-emailsig-import-pick]') : null;
		if (!pick) return;
		var input = pick.form && pick.form.querySelector('input[type="file"]');
		if (input) input.click();
	});

	document.addEventListener('change', function (e) {
		var form = e.target.closest ? e.target.closest('form[data-sb-emailsig-import]') : null;
		if (!form || e.target.type !== 'file' || !e.target.files.length) return;
		var pick = form.querySelector('[data-sb-emailsig-import-pick]');
		if (pick) pick.classList.add('is-loading');
		form.submit();
	});
	/* The team page: find someone, and pick a company (everyone's page). */
	var find = document.querySelector('[data-sb-emailsig-find]');
	var pick = document.querySelector('[data-sb-emailsig-pick]');
	var none = document.querySelector('.sb-emailsig-find-none');
	var clear = document.querySelector('.sb-emailsig-clear');

	function filter() {
		if (!find && !pick) return;
		var q = find ? find.value.trim().toLowerCase() : '';
		var co = pick ? pick.value : '';
		var on = q !== '' || co !== '';
		var shown = 0;

		if (clear) clear.hidden = !on;
		document.documentElement.classList.toggle('sb-emailsig-searching', q !== '');

		document.querySelectorAll('[data-sb-emailsig-group]').forEach(function (g) {
			g.classList.toggle('is-filtered', co !== '' && g.getAttribute('data-sb-emailsig-group') !== co);
		});

		document.querySelectorAll('[data-sb-emailsig-text]').forEach(function (card) {
			var group = card.closest('[data-sb-emailsig-group]');
			var hit = (q === '' || (card.getAttribute('data-sb-emailsig-text') || '').indexOf(q) !== -1)
				&& (co === '' || group || card.getAttribute('data-sb-emailsig-company') === co);
			card.classList.toggle('is-filtered', !hit);
			if (hit && !(group && group.classList.contains('is-filtered'))) shown++;
		});

		// While filtering, sections and companies with no one matching step aside.
		document.querySelectorAll('[data-sb-emailsig-section]').forEach(function (sec) {
			var left = sec.querySelectorAll('[data-sb-emailsig-text]:not(.is-filtered)').length;
			var count = sec.querySelector('[data-sb-emailsig-count]');
			if (count) count.textContent = String(left);
			sec.classList.toggle('is-filtered', on && left === 0);
			if (q !== '' && left && sec.tagName === 'DETAILS') sec.open = true;
		});

		document.querySelectorAll('[data-sb-emailsig-group]').forEach(function (g) {
			if (q !== '' && !g.querySelector('[data-sb-emailsig-text]:not(.is-filtered)')) g.classList.add('is-filtered');
		});

		if (none) none.hidden = !on || shown > 0;
	}

	// For live updating (live.js): the counts and filters after cards change.
	window.sbEmailsigListApi = { recount: recount, filter: filter };

	if (find) find.addEventListener('input', filter);
	if (pick) pick.addEventListener('change', filter);
	document.addEventListener('click', function (e) {
		if (!e.target.closest || !e.target.closest('[data-sb-emailsig-clear]')) return;
		if (find) find.value = '';
		if (pick) pick.value = '';
		filter();
		if (find) find.focus();
	});
})();
