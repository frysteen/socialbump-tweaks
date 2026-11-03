/**
 * The Templates page.
 *
 * Add template (data-sb-emailsig-add-template-open) asks for a name in the
 * framework's popup, then sends its form: the template is made on the
 * default design with its default layout, and its Edit styles page opens.
 *
 * Export (data-sb-emailsig-export-open) asks whether to lock the colours
 * first, then sends its form, which downloads the .json file.
 *
 * Used by (data-sb-emailsig-used) filters the cards on the page, no reload
 * (Sam, 3 November 2026): each card lists the companies it's used by
 * (data-sb-emailsig-used-by), and the address keeps ?used= so a reload or a
 * shared link opens filtered the same.
 */
(function () {
	'use strict';

	function popup() {
		return window.SBTweaksConfirm || null;
	}

	document.addEventListener('click', function (e) {
		var add = e.target.closest ? e.target.closest('[data-sb-emailsig-add-template-open]') : null;
		var exp = e.target.closest ? e.target.closest('[data-sb-emailsig-export-open]') : null;

		if (add && popup()) {
			e.preventDefault();
			var form = add.form;
			var field = document.createElement('label');
			var input = document.createElement('input');
			var words = document.createElement('span');

			field.className = 'sb-tweaks-dialog__field';
			words.textContent = 'Name';
			input.type = 'text';
			input.placeholder = 'Such as Reception or Short';
			input.autocomplete = 'off';
			field.appendChild(words);
			field.appendChild(input);

			var send = function () {
				form.elements.sb_emailsig_name.value = input.value.trim();
				add.classList.add('is-loading');
				form.submit();
			};

			popup().open({
				title: 'Add template',
				message: 'It starts with the default layout and styles, ready to change.',
				body: field,
				action: 'Add template'
			}, send);

			input.addEventListener('keydown', function (ev) {
				if (ev.key === 'Enter') {
					ev.preventDefault();
					popup().close();
					send();
				}
			});

			setTimeout(function () { input.focus(); }, 0);
			return;
		}

		if (exp && popup()) {
			e.preventDefault();
			var eform = exp.form;

			popup().open({
				title: 'Export ' + (eform.getAttribute('data-sb-emailsig-export') || 'template'),
				message: 'Downloads a file to import on another site. Lock colours sends the brand and theme colours as they look now, rather than following the other site\'s.',
				check: 'Lock colours',
				action: 'Export'
			}, function (checked) {
				eform.elements.lock.value = checked ? '1' : '';
				eform.submit();
			});
		}
	});

	// Used by (Sam, 1 and 3 November 2026): the templates a company uses, or
	// Not in use, filtered on the page.
	var used = document.querySelector('[data-sb-emailsig-used]');
	if (used) {
		used.addEventListener('change', function () {
			var pick = used.value;
			var shown = 0;

			document.querySelectorAll('[data-sb-emailsig-used-by]').forEach(function (card) {
				var by = (card.getAttribute('data-sb-emailsig-used-by') || '').split(',').filter(Boolean);
				var match = pick === '' || (pick === 'none' ? !by.length : by.indexOf(pick) !== -1);
				card.classList.toggle('is-filtered', !match);
				if (match) shown++;
			});

			var none = document.querySelector('[data-sb-emailsig-used-none]');
			var text = document.querySelector('[data-sb-emailsig-used-none-text]');
			if (none) none.hidden = shown > 0;
			if (text) text.textContent = text.getAttribute(pick === 'none' ? 'data-none' : 'data-company');

			if (window.history && window.history.replaceState) {
				var url = new URL(window.location.href);
				if (pick) url.searchParams.set('used', pick); else url.searchParams.delete('used');
				window.history.replaceState(null, '', url.toString());
			}
		});
	}

	// Another tab changed a company's templates (the popup on the Companies
	// page, brand.js): the cards and the empty message come again from the
	// server, filtered by Used by as they are now (SBTweaksSync, Sam,
	// 3 November 2026).
	if (window.SBTweaksSync && document.querySelector('.sb-emailsig-templates')) {
		window.SBTweaksSync.on('email-signatures/templates', function () {
			window.SBTweaksSync.refresh(['.sb-emailsig-templates', '[data-sb-emailsig-used-none]']);
		});
	}
})();
