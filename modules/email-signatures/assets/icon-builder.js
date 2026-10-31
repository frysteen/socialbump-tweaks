/**
 * Draws an SVG icon to a PNG data URL in a given colour, with an optional
 * rounded background. Shared by the Settings and Edit styles pages.
 *
 * SBEmailSigIcons.png( svgSource, { size, scale, colour, bg, radius, glyph } )
 *   size   display size in px; the PNG is size * scale
 *   bg     background colour, or '' for none (the icon then fills the box)
 *   glyph  icon size relative to the box when there is a background
 * Resolves to a data URL, or '' when the SVG can't be read.
 */
(function () {
	'use strict';

	function recolour(el, colour) {
		['fill', 'stroke'].forEach(function (attr) {
			var v = el.getAttribute(attr);
			if (v && v !== 'none' && v.indexOf('url(') !== 0) {
				el.setAttribute(attr, colour);
			}
		});
		var style = el.getAttribute('style');
		if (style) {
			el.setAttribute('style', style.replace(/(fill|stroke)\s*:\s*(?!none)[^;]+/gi, '$1:' + colour));
		}
		for (var i = 0; i < el.children.length; i++) {
			recolour(el.children[i], colour);
		}
	}

	function buildSvg(source, o) {
		var doc = new DOMParser().parseFromString(source, 'image/svg+xml');
		var root = doc.documentElement;
		if (!root || root.nodeName.toLowerCase() !== 'svg' || doc.getElementsByTagName('parsererror').length) {
			return null;
		}
		['script', 'foreignObject', 'title', 'desc'].forEach(function (tag) {
			var found = root.getElementsByTagName(tag);
			for (var i = found.length - 1; i >= 0; i--) {
				found[i].parentNode.removeChild(found[i]);
			}
		});

		if (!root.getAttribute('viewBox')) {
			var w = parseFloat(root.getAttribute('width')) || 24;
			var h = parseFloat(root.getAttribute('height')) || 24;
			root.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
		}

		recolour(root, o.colour);
		root.setAttribute('color', o.colour);
		if (!root.getAttribute('fill')) {
			root.setAttribute('fill', o.colour);
		}

		var box = o.size * o.scale;
		var glyph = o.bg ? Math.round(box * o.glyph) : box;
		var off = (box - glyph) / 2;
		root.setAttribute('x', off);
		root.setAttribute('y', off);
		root.setAttribute('width', glyph);
		root.setAttribute('height', glyph);
		root.removeAttribute('style');
		root.removeAttribute('class');

		var rx = Math.min(o.radius * o.scale, box / 2);
		var rect = o.bg ? '<rect width="' + box + '" height="' + box + '" rx="' + rx + '" ry="' + rx + '" fill="' + o.bg + '"/>' : '';

		return '<svg xmlns="http://www.w3.org/2000/svg" width="' + box + '" height="' + box + '" viewBox="0 0 ' + box + ' ' + box + '">' +
			rect + new XMLSerializer().serializeToString(root) + '</svg>';
	}

	function png(source, opts) {
		var o = {
			size: opts.size || 26,
			scale: opts.scale || 3,
			colour: opts.colour || '#000000',
			bg: opts.bg || '',
			radius: opts.radius || 0,
			glyph: opts.glyph || 0.58
		};
		if (!source) {
			return Promise.resolve('');
		}
		var svg = buildSvg(source, o);
		if (!svg) {
			return Promise.resolve('');
		}
		var box = o.size * o.scale;

		return new Promise(function (resolve) {
			var img = new Image();
			img.onload = function () {
				try {
					var c = document.createElement('canvas');
					c.width = box;
					c.height = box;
					c.getContext('2d').drawImage(img, 0, 0, box, box);
					resolve(c.toDataURL('image/png'));
				} catch (e) {
					resolve('');
				}
			};
			img.onerror = function () { resolve(''); };
			img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
		});
	}

	window.SBEmailSigIcons = { png: png };
})();
