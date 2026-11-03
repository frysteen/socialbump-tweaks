/**
 * Export People (Sam, 3 November 2026): one popup for every Export People
 * button, on a company's bar on the Signatures page and on the Export People
 * card on the Import/Export page. It lists the company's people, everyone
 * ticked to start, as the framework's checklist (SBTweaksFields.checklist():
 * Select all, Select none and a count), then
 * Cancel and Export. Export downloads a CSV of the ones ticked.
 *
 * A button:
 *
 *   <button data-sb-emailsig-export-people="{company id}">
 *
 * SB_EmailSig_Transfer::export_people_button() draws it. On the
 * Import/Export page the Company menu ([data-sb-emailsig-people-co]) changes
 * which company it exports.
 *
 * The list comes from sb_emailsig_people_list as the popup opens, so it's
 * never out of date. Export posts sb_emailsig_people_export with the ids.
 */
(function ($) {
	'use strict';

	var cfg = window.sbEmailsigPeopleExport || {};

	function toast(msg, kind) {
		if (window.SBTweaksUI && window.SBTweaksUI.toast) window.SBTweaksUI.toast(msg, kind);
	}

	function noun(n) {
		return n === 1 ? 'person' : 'people';
	}

	/** The list: the framework's checklist (SBTweaksFields), one column, with Select all and none and a count. */
	function body(people) {
		var items = people.map(function (p) {
			var label = document.createDocumentFragment();
			var name = document.createElement('span');
			name.className = 'sb-emailsig-people-pick__name';
			name.textContent = p.name;
			label.appendChild(name);
			if (p.role) {
				var role = document.createElement('span');
				role.className = 'sb-emailsig-people-pick__role';
				role.textContent = p.role;
				label.appendChild(role);
			}
			if (!p.active) {
				var tag = document.createElement('span');
				tag.className = 'sb-emailsig-people-pick__tag';
				tag.textContent = 'Inactive';
				label.appendChild(tag);
			}
			return { value: p.id, label: label, checked: true };
		});

		return $(window.SBTweaksFields.checklist(items, { list: true, tools: 'text', label: 'People', count: ['person', 'people'], className: 'sb-emailsig-people-pick' }));
	}

	function picked($pop) {
		return $pop.find('.sb-tweaks-checklist input:checked').map(function () { return this.value; }).get();
	}

	/** The ticked people's CSV: a form posted out of sight, so the page stays put. */
	function send(co, ids) {
		var form = document.createElement('form');
		var add = function (name, value) {
			var input = document.createElement('input');
			input.type = 'hidden';
			input.name = name;
			input.value = value;
			form.appendChild(input);
		};

		form.method = 'post';
		form.action = cfg.post;
		form.hidden = true;
		add('action', 'sb_emailsig_people_export');
		add('_wpnonce', cfg.nonce);
		add('co', co);
		ids.forEach(function (id) { add('people[]', id); });

		document.body.appendChild(form);
		form.submit();
		setTimeout(function () { form.parentNode.removeChild(form); }, 2000);

		toast('Exporting ' + ids.length + ' ' + noun(ids.length), 'ok');
	}

	function open(btn) {
		var co = btn.getAttribute('data-sb-emailsig-export-people');
		if (!co || btn.classList.contains('is-loading') || !window.SBTweaksConfirm || !window.SBTweaksFields) return;

		btn.classList.add('is-loading');

		fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ action: 'sb_emailsig_people_list', nonce: cfg.nonce, co: co }) })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res || !res.success) throw new Error((res && res.data) || 'The people didn\'t load. Try again.');

				var d = res.data;
				if (!d.people.length) {
					toast(d.company + ' has no people to export yet.', 'warn');
					return;
				}

				var $pop = body(d.people);
				var $modal = window.SBTweaksConfirm.open({
					title: 'Export people',
					message: d.company + '\'s people as a spreadsheet file (CSV) for Excel, Numbers or Google Sheets. It imports back on the Import/Export page.',
					body: $pop,
					cancel: 'Cancel',
					action: 'Export'
				}, function () {
					send(co, picked($pop));
				});

				// Export only with someone ticked (the count beside Select all is the checklist's own).
				var $go = $modal.find('.sb-tweaks-dialog__go');
				var ready = function () { $go.prop('disabled', !picked($pop).length); };
				$pop.on('change', 'input[type="checkbox"]', ready);
				ready();
			})
			.catch(function (err) {
				toast(err && err.message ? err.message : 'The people didn\'t load. Try again.', 'danger');
			})
			.then(function () {
				btn.classList.remove('is-loading');
			});
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('[data-sb-emailsig-export-people]') : null;
		if (!btn) return;
		e.preventDefault();
		open(btn);
	});

	// Import/Export page: the Company menu says whose people Export lists.
	document.addEventListener('change', function (e) {
		if (!e.target.matches || !e.target.matches('[data-sb-emailsig-people-co]')) return;
		var card = e.target.closest('.sb-emailsig-transfer') || document;
		card.querySelectorAll('[data-sb-emailsig-export-people]').forEach(function (b) {
			b.setAttribute('data-sb-emailsig-export-people', e.target.value);
		});
	});
})(jQuery);
