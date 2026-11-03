/**
 * A colour field outside the Edit styles page (a person's photo border,
 * Sam, 30 October 2026): the framework's colour field (SBTweaksColour), with
 * the brand and theme colours menu in the person's company's colours, and a
 * blank one showing data-fallback-hex. Colours: sbEmailsigColours (palette,
 * colours, brand), from SB_EmailSig_Photo::assets().
 */
(function () {
	'use strict';

	var cfg = window.sbEmailsigColours || {};

	/** A saved colour as hex, the way the server works it out, or ''. */
	function hex(value) {
		return SBTweaksColour.lookup(value, { colours: cfg.colours, brand: cfg.brand, palette: cfg.palette });
	}

	function init() {
		document.querySelectorAll('.sb-emailsig-colour[data-sb-colour]').forEach(function (wrap) {
			SBTweaksColour.attach(wrap, {
				palette: cfg.palette || [],
				hex: hex,
				fallback: function (f) { return f.wrap.getAttribute('data-fallback-hex') || ''; }
			});
		});
	}

	if (!window.SBTweaksColour) return;
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
