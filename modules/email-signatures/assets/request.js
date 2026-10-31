/**
 * Submit New Team Member, on a company's team page (SB_EmailSig_Request): opens
 * the popup and fetches its signed token, crops a chosen photo with
 * Cropper.js at the template's ratio (in its shape, round for a circle), then
 * sends the details, the photo and its crop (fractions of the photo, as the
 * edit screen keeps it) in one go, so nothing is uploaded unless it's sent.
 */
(function () {
	'use strict';

	var cfg = window.sbEmailsigRequest || {};
	var dlg = document.getElementById('sb-emailsig-request');
	if (!dlg || !window.fetch || !window.FormData) return;

	var form = dlg.querySelector('form');
	var done = dlg.querySelector('.add__done');
	var status = dlg.querySelector('.add__status');
	var cropBox = dlg.querySelector('.add__crop');
	var img = dlg.querySelector('.add__stage img');
	var preview = dlg.querySelector('.add__preview');
	var file = dlg.querySelector('[data-request-file]');
	var remove = dlg.querySelector('[data-request-remove]');
	var save = dlg.querySelector('[data-request-save]');
	var cropper = null;
	var chosen = null;
	var objectUrl = '';
	var token = '';
	var busy = false;

	if (window.sbEmailsigCrop) window.sbEmailsigCrop.attach(img);

	function post(fd) {
		return fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: fd }).then(function (r) { return r.json(); });
	}

	function say(msg, isError) {
		status.textContent = msg || '';
		status.classList.toggle('is-error', !!isError);
	}

	/** A fresh token each time the popup opens. */
	function fetchToken() {
		token = '';
		var fd = new FormData();
		fd.append('action', 'sb_emailsig_request_token');
		fd.append('co', cfg.co);
		return post(fd).then(function (res) {
			if (res && res.success) token = res.data;
		}).catch(function () {});
	}

	/* Open and close. */
	document.addEventListener('click', function (e) {
		if (!e.target.closest || !e.target.closest('[data-sb-emailsig-request-open]')) return;
		e.preventDefault();
		form.hidden = false;
		done.hidden = true;
		say('');
		fetchToken();
		if (dlg.showModal) dlg.showModal(); else dlg.setAttribute('open', '');
		var first = form.elements.first;
		if (first) first.focus();
	});
	dlg.querySelectorAll('[data-request-close]').forEach(function (b) {
		b.addEventListener('click', function () { dlg.close ? dlg.close() : dlg.removeAttribute('open'); });
	});

	/* The template's ratio, width over height. */
	function ratio() {
		return cfg.ratio || 1;
	}

	function shapePreview() {
		var w = 120;
		preview.style.width = w + 'px';
		preview.style.height = Math.round(w / ratio()) + 'px';
		preview.style.borderRadius = cfg.shape === 'circle' ? '50%' : (cfg.shape === 'rounded' ? Math.round((cfg.radius || 0) * w) + 'px' : '0');
	}

	/* The photo: shown in the crop box straight from the device. */
	function setPhoto(f) {
		chosen = f || null;
		if (cropper) { cropper.destroy(); cropper = null; }
		if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = ''; }
		remove.hidden = !chosen;
		cropBox.hidden = !chosen;
		if (!chosen) return;
		shapePreview();
		objectUrl = URL.createObjectURL(chosen);
		img.onload = function () {
			cropper = new Cropper(img, Object.assign({}, window.sbEmailsigCrop ? window.sbEmailsigCrop.options : {}, {
				aspectRatio: ratio(),
				viewMode: 1,
				autoCropArea: 1,
				background: false,
				preview: preview
			}));
		};
		img.src = objectUrl;
	}

	dlg.querySelector('[data-request-upload]').addEventListener('click', function () { file.click(); });
	remove.addEventListener('click', function () { setPhoto(null); });
	file.addEventListener('change', function () {
		var f = file.files[0];
		file.value = '';
		if (!f) return;
		if (['image/jpeg', 'image/png', 'image/webp'].indexOf(f.type) === -1) { say('Use a JPG, PNG or WebP photo.', true); return; }
		if (cfg.maxBytes && f.size > cfg.maxBytes) { say('That photo is too big. Use one under 10MB.', true); return; }
		say('');
		setPhoto(f);
	});

	/* Send it. */
	form.addEventListener('submit', function (e) {
		e.preventDefault();
		if (busy) return;
		var f = form.elements;
		if (!f.first.value.trim()) { say('Add their first name.', true); f.first.focus(); return; }
		if (!f.email.value.trim()) { say('Add their email address.', true); f.email.focus(); return; }

		var fd = new FormData();
		fd.append('action', 'sb_emailsig_request');
		fd.append('co', cfg.co);
		fd.append('token', token);
		['first', 'last', 'quals', 'job', 'email', 'mobile', 'sb_hp'].forEach(function (n) { fd.append(n, f[n].value); });

		if (chosen && cropper) {
			var d = cropper.getData(true);
			var im = cropper.getImageData();
			fd.append('photo', chosen, chosen.name);
			if (im.naturalWidth && im.naturalHeight) {
				fd.append('crop_x', d.x / im.naturalWidth);
				fd.append('crop_y', d.y / im.naturalHeight);
				fd.append('crop_w', d.width / im.naturalWidth);
				fd.append('crop_h', d.height / im.naturalHeight);
			}
		}

		busy = true;
		save.disabled = true;
		say(chosen ? 'Sending the details and photo…' : 'Sending…');

		var finish = function (msg) {
			say(msg, true);
			busy = false;
			save.disabled = false;
		};

		post(fd).then(function (res) {
			if (res && res.success) {
				form.reset();
				setPhoto(null);
				say('');
				busy = false;
				save.disabled = false;
				form.hidden = true;
				done.hidden = false;
				return;
			}
			finish((res && typeof res.data === 'string' && res.data) || 'Not sent. Try again.');
		}).catch(function () { finish('Not sent. Try again.'); });
	});
})();
