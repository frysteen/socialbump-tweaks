/**
 * Edit styles page: colour fields with the theme's colours, a live preview of
 * the unsaved styles scaled to fit its column, and the contact and social
 * icons drawn in those styles, sent with the form so they are saved as images.
 *
 * The framework's save-state.js watches the form for unsaved changes. Nothing
 * here may look like a change to it: the icons field has no name until the
 * form is sent, and once the page has settled the form is marked as saved
 * (sb:saved), which is the framework's own way of saying so.
 */
(function ($) {
	'use strict';

	var cfg = window.sbEmailsigStyles || {};
	var palette = cfg.palette || [];
	// Each company's brand colours, colours menu and social icons (Sam, 30
	// October 2026): the preview is in the previewed person's company, and a
	// save draws the icons for every company.
	var companies = cfg.companies || [];
	var shownCompany = 0;
	// Every theme colour, shades too (var(--primary-d-4)), as hex.
	var colours = cfg.colours || {};
	var form, $form, $preview, $fit, $status, $scale, iconsField;
	var lastState = '';
	var renderId = 0;
	var timer = null;
	var icons = null;
	var sending = false;

	/* ------------------------------------------------------------------ *
	 * Colours
	 * ------------------------------------------------------------------ */

	/** A saved colour as hex, the way the server works it out, or ''. */
	function hex(value) {
		value = (value || '').trim().toLowerCase();
		if (/^--[a-z0-9_-]+$/.test(value)) value = 'var(' + value + ')';
		if (/^#[0-9a-f]{6}$/.test(value)) return value;
		if (/^#[0-9a-f]{3}$/.test(value)) return '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3];
		if (Object.prototype.hasOwnProperty.call(colours, value)) return colours[value];
		var b = /^var\(--sb-brand-([a-z-]+)\)$/.exec(value);
		if (b) return (cfg.brand || {})[b[1].replace(/-/g, '_')] || '';
		for (var i = 0; i < palette.length; i++) {
			if (palette[i].value === value) return palette[i].hex;
		}
		return '';
	}

	/** Draw in company $id's colours and social icons. */
	function useCompany(id) {
		id = parseInt(id, 10) || 0;
		for (var i = 0; i < companies.length; i++) {
			if (companies[i].id === id) {
				cfg.brand = companies[i].brand;
				cfg.social = companies[i].social;
				palette = companies[i].palette || palette;
				return true;
			}
		}
		return false;
	}

	/** The company of the person picked for the preview. */
	function personCompany() {
		var opt = $('.sb-emailsig-editor__person option:selected');
		return opt.length && opt.attr('data-company') ? parseInt(opt.attr('data-company'), 10) : shownCompany;
	}

	/**
	 * The icons for every company, the preview's last so it is the one left
	 * in place: { companies: { id: set } }. One set when there are no companies.
	 */
	function buildAll() {
		if (!companies.length) return buildIcons();
		var out = {};
		var back = shownCompany;
		var chain = Promise.resolve();
		companies.forEach(function (c) {
			chain = chain.then(function () {
				useCompany(c.id);
				return buildIcons().then(function (set) { out[c.id] = set; });
			});
		});
		return chain.then(function () {
			useCompany(back);
			return { companies: out };
		}, function (err) {
			useCompany(back);
			throw err;
		});
	}

	function val(name) {
		var el = form.querySelector('[name="' + name + '"]');
		if (!el) return '';
		if (el.type === 'checkbox') return el.checked;
		return (el.value || '').trim();
	}

	function defaults() {
		return cfg.defaults || {};
	}

	/** A brand colour (Settings, Branding) as it is saved now. */
	function brand(key) {
		return (cfg.brand || {})[key] || '#000000';
	}

	/**
	 * A colour from the form, or as saved when the form hasn't got it: a
	 * design with a layout keeps Primary and Text on the Layout page (Layout
	 * Settings, 29 October 2026).
	 */
	function own(key) {
		return form.querySelector('[name="s[' + key + ']"]') ? val('s[' + key + ']') : ((cfg.saved || {})[key] || '');
	}

	function primary() {
		return hex(own('primary')) || brand('primary');
	}

	function textColour() {
		return hex(own('text')) || brand('text');
	}

	/** What a blank colour field comes out as, the way the server works it out. */
	function fallbackColour(fallback) {
		if (fallback === 'brand_primary') return brand('primary');
		if (fallback === 'brand_text') return brand('text');
		if (fallback && fallback.indexOf('brand_') === 0) return brand(fallback.slice(6));
		if (fallback === 'primary') return primary();
		if (fallback === 'text') return textColour();
		if (fallback === 'white') return '#ffffff';
		if (fallback === 'button') return hex(val('s[buttons][bg]')) || primary();
		if (fallback === 'icon') return val('s[icons][background]') ? '#ffffff' : primary();
		return '';
	}

	/** Show what each colour field comes out as; a blank one shows what it falls back to. */
	function paintSwatches() {
		$form.find('.sb-emailsig-colour').each(function () {
			var $wrap = $(this);
			var input = $wrap.find('.sb-tweaks-colour__value')[0];
			var swatch = $wrap.find('.sb-tweaks-colour__swatch')[0];
			var own = hex(input.value);
			var fallback = $wrap.attr('data-fallback');
			var shown = own || fallbackColour(fallback);

			$wrap.toggleClass('is-inherited', !own);
			$wrap.toggleClass('is-unknown', input.value.trim() !== '' && !own);
			if (shown && swatch.value !== shown) swatch.value = shown;
		});
	}

	function setValue(input, value) {
		input.value = value;
		// A real DOM event: save-state.js listens with addEventListener.
		input.dispatchEvent(new Event('input', { bubbles: true }));
		input.dispatchEvent(new Event('change', { bubbles: true }));
	}

	var $menu = null;
	var menuFor = null;

	function closeMenu() {
		if ($menu) {
			$menu.remove();
			$menu = null;
		}
		if (menuFor) {
			menuFor.setAttribute('aria-expanded', 'false');
			menuFor = null;
		}
	}

	function openMenu(button) {
		var input = $(button).closest('.sb-emailsig-colour').find('.sb-tweaks-colour__value')[0];
		var rect = button.getBoundingClientRect();

		closeMenu();
		$menu = $('<div class="sb-emailsig-palette" role="listbox"></div>');

		palette.forEach(function (c, i) {
			// A line between the brand colours and the theme's.
			if (i > 0 && palette[i - 1].group === 'brand' && c.group !== 'brand') {
				$('<hr class="sb-emailsig-palette__sep">').appendTo($menu);
			}
			$('<button type="button" role="option"></button>')
				.append($('<i></i>').css('background', c.hex))
				.append($('<span></span>').text(c.name))
				.append($('<code></code>').text(c.hex))
				.on('click', function () {
					setValue(input, c.value);
					closeMenu();
					input.focus();
				})
				.appendTo($menu);
		});

		$menu.appendTo(document.body).css({
			top: rect.bottom + window.scrollY + 4,
			left: Math.max(8, Math.min(rect.left + window.scrollX, window.scrollX + document.documentElement.clientWidth - 268))
		});
		menuFor = button;
		button.setAttribute('aria-expanded', 'true');
		$menu.find('button').first().trigger('focus');
	}

	function initColours() {
		$form.find('.sb-emailsig-colour').each(function () {
			var $wrap = $(this);
			var input = $wrap.find('.sb-tweaks-colour__value')[0];
			var swatch = $wrap.find('.sb-tweaks-colour__swatch')[0];
			var theme = $wrap.find('.sb-emailsig-colour__theme')[0];

			// Picking with the swatch writes its hex into the box.
			swatch.addEventListener('input', function () {
				if (input.value !== swatch.value) setValue(input, swatch.value);
			});

			if (palette.length && theme) {
				theme.hidden = false;
				theme.setAttribute('aria-expanded', 'false');
				theme.addEventListener('click', function (e) {
					e.preventDefault();
					if (menuFor === theme) {
						closeMenu();
					} else {
						openMenu(theme);
					}
				});
			}
		});

		$(document).on('mousedown', function (e) {
			if ($menu && !$(e.target).closest('.sb-emailsig-palette, .sb-emailsig-colour__theme').length) closeMenu();
		});
		$(document).on('keydown', function (e) {
			if (e.key === 'Escape' && $menu) {
				var back = menuFor;
				closeMenu();
				if (back) back.focus();
			}
		});
		$(window).on('resize scroll', closeMenu);
	}

	/* ------------------------------------------------------------------ *
	 * Icons and preview
	 * ------------------------------------------------------------------ */

	function styleFields() {
		return $form.serializeArray().filter(function (f) { return f.name.indexOf('s[') === 0; });
	}

	/** The icon styles as the form now has them, worked out like the server does. */
	function iconStyles() {
		var d = defaults();
		var p = primary();
		var social = d.social ? {
			size: parseInt(val('s[social][size]'), 10) || d.social.size,
			colour: hex(val('s[social][colour]')) || hex(d.social.colour) || '#ffffff',
			radius: parseInt(val('s[social][radius]'), 10) || 0,
			bg: val('s[social][background]') ? (hex(val('s[social][bg]')) || p) : ''
		} : null;

		return {
			contact: {
				size: parseInt(val('s[icons][size]'), 10) || (d.icons && d.icons.size) || 18,
				colour: hex(val('s[icons][colour]')) || (val('s[icons][background]') ? '#ffffff' : p),
				bg: val('s[icons][background]') ? (hex(val('s[icons][bg]')) || p) : '',
				radius: parseInt(val('s[icons][radius]'), 10) || 0
			},
			social: social
		};
	}

	function buildIcons() {
		var st = iconStyles();
		var names = Object.keys(cfg.contact || {});
		var social = cfg.social || [];

		var contact = Promise.all(names.map(function (n) {
			return SBEmailSigIcons.png(cfg.contact[n], { size: st.contact.size, colour: st.contact.colour, bg: st.contact.bg, radius: st.contact.radius });
		}));
		var socials = st.social ? Promise.all(social.map(function (svg) {
			return SBEmailSigIcons.png(svg, { size: st.social.size, colour: st.social.colour, bg: st.social.bg, radius: st.social.radius });
		})) : Promise.resolve([]);

		return Promise.all([contact, socials]).then(function (res) {
			var out = { contact: {}, social: res[1] };
			names.forEach(function (n, i) { out.contact[n] = res[0][i]; });
			return out;
		});
	}

	function fetchPreview() {
		var data = styleFields();
		data.push({ name: 'action', value: 'sb_emailsig_preview' });
		data.push({ name: 'nonce', value: cfg.nonce });
		data.push({ name: 'template', value: cfg.template });
		var $person = $('.sb-emailsig-editor__person');
		if ($person.length) data.push({ name: 'person', value: $person.val() });

		return Promise.resolve($.post(cfg.ajax, $.param(data))).then(function (res) {
			return res && res.success ? res.data : null;
		}, function () { return null; });
	}

	/** Scale the signature down to fit its column, never up. */
	function fit() {
		if (!$fit.length) return;
		var el = $fit[0];
		el.style.zoom = 1;
		var natural = el.scrollWidth;
		var room = $preview[0].clientWidth - 40;
		var scale = natural > room && room > 0 ? room / natural : 1;
		el.style.zoom = scale;
		$scale.text(scale < 1 ? 'shown at ' + Math.round(scale * 100) + '%' : '');
	}

	function update() {
		var id = ++renderId;
		$status.text('Updating...');

		return Promise.all([fetchPreview(), buildIcons()]).then(function (res) {
			if (id !== renderId) return;

			var html = res[0];
			icons = res[1];

			if (html !== null) {
				$fit.html(html);
			}

			// Show the icons in their unsaved colours.
			$fit.find('img[data-sbes-icon]').each(function () {
				var png = icons.contact[$(this).attr('data-sbes-icon')];
				if (png) this.src = png;
			});
			$fit.find('img[data-sbes-social]').each(function () {
				var png = icons.social[parseInt($(this).attr('data-sbes-social'), 10)];
				if (png) this.src = png;
			});

			// Filed under the preview's company, so a save that can't draw the
			// rest still puts these where they belong.
			var set = {};
			set[shownCompany] = icons;
			iconsField.value = JSON.stringify(companies.length ? { companies: set } : icons);
			fit();
			$status.text(html === null ? 'Preview not updated' : '');
		});
	}

	/* ------------------------------------------------------------------ *
	 * Edited cards: anything that differs from the template's default
	 * marks its card and shows the card's Reset.
	 * ------------------------------------------------------------------ */

	function isEdited(el) {
		var def = el.getAttribute('data-default') || '';
		if (el.type === 'checkbox') return el.checked !== (def === '1');
		return (el.value || '').trim().toLowerCase() !== def.trim().toLowerCase();
	}

	function markCards() {
		$form.find('.sb-emailsig-editor__card').each(function () {
			var edited = false;
			$(this).find('[data-default]').each(function () {
				if (isEdited(this)) {
					edited = true;
					return false;
				}
			});
			$(this).toggleClass('is-edited', edited);
			$(this).find('.sb-emailsig-card-reset').prop('disabled', !edited);
		});
	}

	/** Fields that only count for one value of another (Corner radius for Rounded corners). */
	function applyWhen() {
		$form.find('.sb-emailsig-when').each(function () {
			// One value, or several split by |.
			var want = String($(this).attr('data-when-value')).split('|');
			this.hidden = want.indexOf(String(val($(this).attr('data-when-name')))) === -1;
		});
	}

	function watch() {
		markCards();
		applyWhen();
		var state = $.param(styleFields());
		if (state === lastState) return;
		lastState = state;
		paintSwatches();
		clearTimeout(timer);
		timer = setTimeout(update, 250);
	}

	/** Put every field back to the template's default, or just those in $scope. */
	function resetToDefaults($scope) {
		$scope = $scope && $scope.jquery ? $scope : $form;
		$scope.find('[data-default]').each(function () {
			if (!isEdited(this)) return;
			if (this.type === 'checkbox') {
				this.checked = this.getAttribute('data-default') === '1';
				this.dispatchEvent(new Event('change', { bubbles: true }));
			} else {
				setValue(this, this.getAttribute('data-default'));
			}
		});
		watch();
	}

	/* ------------------------------------------------------------------ *
	 * Saving
	 * ------------------------------------------------------------------ */

	function send() {
		// The icons go with the form only now, under their real name.
		iconsField.name = 'sb_emailsig_icons';
		sending = true;
		// The form's own submit: a field named "submit" would hide it, and this
		// skips the submit handlers, which have already run.
		HTMLFormElement.prototype.submit.call(form);
	}

	$(function () {
		form = document.getElementById('sb-emailsig-styles-form');
		if (!form || !window.SBEmailSigIcons) return;

		$form = $(form);
		$preview = $form.find('.sb-emailsig-editor__preview');
		$fit = $form.find('.sb-emailsig-editor__fit');
		$status = $form.find('.sb-emailsig-editor__status');
		$scale = $form.find('.sb-emailsig-editor__scale');
		iconsField = form.querySelector('[data-sb-emailsig-icons]');

		shownCompany = $('.sb-emailsig-editor__person option:selected').attr('data-company') ? personCompany() : (parseInt(cfg.company, 10) || 0);
		useCompany(shownCompany);
		initColours();
		paintSwatches();
		fit();
		$(window).on('resize', fit);
		// Reset to defaults asks first (the framework's popup), then saves, which
		// reloads the page. The cards' own Reset buttons don't ask or save.
		// For a design with a layout, a tick resets the Layout page too (Sam,
		// 29 October 2026): its Primary and Text as the design has them for the
		// icons, and the save told to use the design's layout page styles.
		var otherField = form.querySelector('[data-sb-emailsig-reset-other]');
		$form.on('click', '.sb-emailsig-editor__reset', function () {
			if (!window.SBTweaksConfirm) return;
			var options = {
				title: 'Reset to defaults',
				message: 'Put every style on this template back to its default and save it. Signatures using this template change straight away.',
				action: 'Reset and save',
				tone: 'danger'
			};
			if (otherField) {
				options.check = 'Reset the Layout page too (layout, Layout Settings, Container Settings and Footer Settings).';
			}
			window.SBTweaksConfirm.open(options, function (checked) {
				if (checked && otherField) {
					otherField.name = 'sb_emailsig_reset_other';
					cfg.saved = { primary: defaults().primary || '', text: defaults().text || '' };
				}
				resetToDefaults();
				if (form.requestSubmit) {
					form.requestSubmit();
				} else {
					form.dispatchEvent(new Event('submit', { cancelable: true }));
				}
			});
		});
		$form.on('click', '.sb-emailsig-card-reset', function () {
			resetToDefaults($(this).closest('.sb-emailsig-editor__card'));
		});
		markCards();
		applyWhen();
		$form.on('change', '.sb-emailsig-editor__person', function () {
			clearTimeout(timer);
			shownCompany = personCompany();
			useCompany(shownCompany);
			closeMenu();
			paintSwatches();
			update();
		});

		// Save once the icons are drawn in the current styles.
		form.addEventListener('submit', function (e) {
			if (sending) return;
			e.preventDefault();
			clearTimeout(timer);
			$status.text('Saving...');
			update().then(buildAll).then(function (all) {
				iconsField.value = JSON.stringify(all);
				send();
			}, send);
		});

		// Anything the user does, as against what this script does on load.
		var touched = false;
		['input', 'change'].forEach(function (type) {
			form.addEventListener(type, function (e) {
				if (e.isTrusted) touched = true;
			}, true);
		});

		lastState = $.param(styleFields());
		update().then(function () {
			// The page has settled: what changed so far was this script, not the user.
			if (!touched) form.dispatchEvent(new Event('sb:saved'));
		});
		setInterval(watch, 300);
	});
})(jQuery);
