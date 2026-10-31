/**
 * The Templates page.
 *
 * Add template (data-sb-emailsig-add-template-open) asks for a name in the
 * framework's popup, then sends its form: the template is made on the
 * default design with its default layout, and its Edit styles page opens.
 *
 * Export (data-sb-emailsig-export-open) asks whether to lock the colours
 * first, then sends its form, which downloads the .json file.
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
})();
