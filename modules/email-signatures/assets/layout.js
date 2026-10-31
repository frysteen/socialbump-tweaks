/**
 * A template's Layout page: the layout editor (29 October 2026; its own page,
 * opened from Edit Layout on Edit styles, since that afternoon).
 *
 * The layout lives in one hidden field, s[layout], as JSON in the exact shape
 * SB_EmailSig_Layout::sanitize() gives (canon() below writes the same keys in
 * the same order), so the preview, save, the card's Reset and the unsaved
 * changes check treat it like any other field. Nothing drawn here has a name,
 * so only that field counts as a change.
 *
 * Rows (Sam, 31 October 2026), each with its own columns (1 to 5), box and
 * default align, stacked with a gap (and any line) between them, moved by
 * their grip; a removed row's blocks go where its popup says. Columns and
 * the holding area are lists of blocks dragged with SortableJS. Each block's settings, a column's and a gap's open in the
 * framework's popup (SBTweaksConfirm): the preview follows as they change,
 * Save keeps the change, and Cancel, the X, Escape or a click outside put
 * it back. The page draws its own preview (wp_ajax_sb_emailsig_preview with
 * scope layout: the template's saved styles with this page's own).
 *
 * Layout Settings (Sam, 29 October 2026): the Primary, Text, Lines and Font
 * fields are ordinary named fields (PHP draws them, this script gives them
 * their swatches and theme colours); the Default Spacing, Line Width and
 * Line Style are the layout's. A new Primary redraws the contact and social
 * icons in it (icon-builder.js), sent with the form like Edit styles does.
 *
 * Undo and Redo (Sam, 29 October 2026) step through the page's changes: the
 * layout and the Layout Settings fields, one step per change (a popup is one
 * step, recorded as it closes; typing settles before it counts). The X on a
 * block asks first, and Container Settings and Footer Settings each have a
 * Reset for their own part of the layout. Reset Template (Sam, 29 October
 * 2026) puts everything on this page back to the design after asking; it is
 * one step for Undo and saves only with Save changes. It is only active once
 * something differs from the design, and its popup's tick resets Edit styles
 * too, which saves straight away (with the icons drawn as the design has
 * them).
 */
(function ($) {
	'use strict';

	var cfg = window.sbEmailsigLayout || {};
	var blocks = cfg.blocks || {};
	var multi = cfg.multi || [];
	// Columns a layout can have (up to 5, Sam, 30 October 2026).
	var MAX_COLS = cfg.maxCols || 12;
	/** Gaps kept for a row: one between each column, and never fewer than 4 (as before columns could be added). */
	var GAP_PAD = 4;
	function gapCount(n) { return Math.max(GAP_PAD, n - 1); }
	var defs = cfg.block || {};
	var field, root, state;
	var uid = 0;
	var sortables = [];
	/* The Container border's lock: one width for all four sides (null: worked out when it's drawn). */
	var writing = false;

	var SHORT = { info_person: 'Person items', vcard: 'Save contact' };
	/* Footer parts with their own spacing (Footer Settings), in the order they draw. */
	var FOOT_PARTS = [['buttons', 'Buttons'], ['text', 'Footer Text'], ['campaign', 'Campaign Banner'], ['banners', 'Banners']];
	/* Footer parts with a Space between two (Sam, 1 November 2026). */
	var FOOT_GAPS = { buttons: true, banners: true };
	var HIDES = [['buttons', 'Hide Buttons'], ['banners', 'Hide Banners'], ['campaign', 'Hide Campaign Banner'], ['text', 'Hide Footer Text']];
	var texts = cfg.texts || {};
	var VALIGNS = { top: 'Top', middle: 'Middle', bottom: 'Bottom' };
	var ALIGNS = ['left', 'center', 'right'];
	var ALIGN_NAMES = { left: 'Left', center: 'Centre', right: 'Right' };

	/* ------------------------------------------------------------------ *
	 * The layout's exact shape
	 * ------------------------------------------------------------------ */

	function int(v, d) {
		var n = parseInt(v, 10);
		return isNaN(n) ? d : n;
	}

	function clamp(v, min, max, d) {
		return Math.max(min, Math.min(max, int(v, d)));
	}

	/** An Info List's items (Layout::clean_items()); company: the Company Additional Info List Items block's, the company's only, new ones ticked (clean_company_items()). */
	function canonItems(list, company) {
		var out = [];
		var got = {};
		var mine = function (k) { return !!cfg.items[k] && (!company || k.indexOf('c:') === 0); };
		(list || []).forEach(function (it) {
			if (it && mine(it.key) && !got[it.key]) {
				got[it.key] = true;
				out.push({ key: it.key, on: it.on ? 1 : 0 });
			}
		});
		Object.keys(cfg.items).forEach(function (k) {
			if (!got[k] && mine(k)) out.push({ key: k, on: company ? 1 : 0 });
		});
		return out;
	}

	/** Blocks drawn as an Info List: its layout, spacing and icon display (Sam, 31 October 2026). */
	var LISTS = ['info', 'company_info', 'person_info'];

	/* Spacing steps (parts of the Default Spacing) and lines (the Default Line Width or px). */
	/* Layout::STEPS, on an 8px Default Spacing (Sam, 31 October 2026): offered smallest first; Eighth only where saved. */
	var STEPS = { quarter: 0.25, half: 0.5, 'default': 1, one_half: 1.5, double: 2, triple: 3, quadruple: 4, eighth: 0.125 };
	var STEP_NAMES = { quarter: 'Quarter', half: 'Half', 'default': 'Default', one_half: 'One and a half', double: 'Double', triple: 'Triple', quadruple: 'Quadruple' };

	/** A space as the server keeps it: a step, or px (0 for none). Blank is d; -1 (before steps) the default. */
	function spaceVal(v, d) {
		if (v === undefined || v === null || v === '') return d;
		if (typeof v === 'string' && STEPS.hasOwnProperty(v)) return v;
		var n = parseInt(v, 10);
		if (isNaN(n)) return d;
		if (n === -1) return 'default';
		return Math.max(0, Math.min(80, n));
	}

	/* The thickest line: Outlook for Windows draws no border thicker. */
	var MAX_LINE = cfg.maxLine || 8;
	var STYLES = cfg.styles || { solid: 'Solid', double: 'Double' };
	/* The thinnest a double line is (Layout::DOUBLE_MIN). */
	var DOUBLE_MIN = cfg.doubleMin || 3;

	/** A line as the server keeps it: 'default' or px (at least min). Blank is d. */
	function lineVal(v, d, min) {
		if (v === undefined || v === null || v === '') return d;
		if (v === 'default') return 'default';
		var n = parseInt(v, 10);
		return isNaN(n) ? d : Math.max(min || 0, Math.min(MAX_LINE, n));
	}

	/** A line's style as the server keeps it: 'default' or a style (dashed and dotted, gone, read as solid). */
	function styleVal(v) {
		if (v === 'dashed' || v === 'dotted') return 'solid';
		return STYLES.hasOwnProperty(v) ? v : 'default';
	}

	/** A space in px now. */
	function px(v) {
		if (typeof v === 'string' && STEPS.hasOwnProperty(v)) return Math.round(int(state.block_gap, 8) * STEPS[v]);
		return Math.max(0, int(v, 0));
	}

	/** A line's width in px now. */
	function lpx(v) {
		return v === 'default' ? int(state.line_width, 1) : Math.max(0, int(v, 0));
	}

	function canonBlock(b) {
		var d = defs[b.type] || {};
		var out = { type: b.type };
		if (multi.indexOf(b.type) !== -1) out.id = int(b.id, 1);
		out.align = ALIGNS.indexOf(b.align) !== -1 ? b.align : 'left';
		out.space = spaceVal(b.space, 'double');
		Object.keys(d).forEach(function (k) {
			if (k === 'type' || k === 'id' || k === 'align' || k === 'space') return;
			var v = b[k] === undefined ? d[k] : b[k];
			if (k === 'spacing') {
				out[k] = spaceVal(v, d[k]);
			} else if (k === 'thickness') {
				out[k] = lineVal(v, d[k], 1);
			} else if (k === 'colour') {
				out[k] = colour(v);
			} else if (k === 'line_style') {
				out[k] = styleVal(v);
			} else if (k === 'items') {
				out[k] = canonItems(v, b.type === 'company_info');
			} else if (k === 'colours') {
				out[k] = canonColours(v, d[k]);
			} else if (/^(title|text)_size$/.test(k)) {
				out[k] = textSize(v);
			} else if (/^(title|text)_line$/.test(k)) {
				out[k] = lhVal(v);
			} else if (k === 'gap') {
				out[k] = spaceVal(v, d[k]);
			} else if (typeof d[k] === 'number') {
				out[k] = int(v, d[k]);
			} else {
				out[k] = String(v);
			}
		});
		return out;
	}

	/** A Text block part's size: 0 (the template's) or 8 to 48 px (Layout::text_style()). */
	function textSize(v) {
		var n = int(v, 0);
		return n <= 0 ? 0 : Math.max(8, Math.min(48, n));
	}

	/** A line height as the server keeps it (Styles::lh()): '' or 1 to 3, to one place. */
	function lhVal(v) {
		v = String(v === undefined || v === null ? '' : v).trim().replace(',', '.');
		if (v === '' || isNaN(+v) || +v <= 0) return '';
		return String(Math.max(1, Math.min(3, Math.round(+v * 10) / 10)));
	}

	/** A row's colours of its own (Layout::COLOURS['row']), each '' to follow the template. */
	var ROW_COLOURS = { text: '', accent: '', icons: '' };

	/** A set of colours as the server keeps it: keys's keys, in order, each a colour or ''. */
	function canonColours(v, keys) {
		var out = {};
		v = v || {};
		Object.keys(keys || {}).forEach(function (k) { out[k] = colour(v[k]); });
		return out;
	}

	/** A colour as the server keeps it: '#rrggbb', '#rgb', var(--name) or ''. */
	function colour(v) {
		v = String(v || '').replace(/\s+/g, '').toLowerCase();
		if (/^--[a-z0-9_-]+$/.test(v)) v = 'var(' + v + ')';
		return /^(#[0-9a-f]{3}|#[0-9a-f]{6}|var\(--[a-z0-9_-]+\))$/.test(v) ? v : '';
	}

	/** A gap as the server keeps it (between columns, or rows): d when it has no space. */
	function canonGap(g, d, ld) {
		g = g || {};
		var first = spaceVal(g.space, d);
		// The other side of a line (Sam, 31 October 2026): missing is the same as space.
		return { space: first, space_after: spaceVal(g.space_after, first), line: lineVal(g.line, ld === undefined ? 0 : ld), line_style: styleVal(g.line_style), colour: colour(g.colour) };
	}

	var SIDES4 = ['top', 'right', 'bottom', 'left'];

	/** Padding on each side as the server keeps it (Layout::clean_pad()); one value is all four. */
	function canonPad(p) {
		var out = {};
		SIDES4.forEach(function (sd) { out[sd] = spaceVal(p && typeof p === 'object' ? p[sd] : p, 0); });
		return out;
	}

	/** A column's padding: its own, else Space at the top and bottom from before. */
	function colPad(c) {
		return c.padding && typeof c.padding === 'object' ? canonPad(c.padding) : canonPad({ top: c.top, bottom: c.bottom });
	}

	/** A box's border as the server keeps it (the Container's, a row's). */
	function canonBorder(b) {
		b = b || {};
		return {
			top: clamp(b.top, 0, MAX_LINE, 0),
			right: clamp(b.right, 0, MAX_LINE, 0),
			bottom: clamp(b.bottom, 0, MAX_LINE, 0),
			left: clamp(b.left, 0, MAX_LINE, 0),
			colour: colour(b.colour),
			line_style: styleVal(b.line_style)
		};
	}

	function canon(s) {
		return {
			// Rows (Sam, 31 October 2026): each its columns, the gaps between
			// them, their default align and its own box.
			rows: s.rows.map(function (r) {
				var gaps = [];
				for (var gi = 0; gi < gapCount(Math.max(1, r.columns.length)); gi++) gaps.push(canonGap((r.gaps || [])[gi], 'triple', 'default'));
				return {
					columns: (r.columns.length ? r.columns : [newColumn()]).map(function (c) {
						return {
							width: clamp(c.width, 0, 600, 0),
							valign: VALIGNS[c.valign] ? c.valign : '',
							padding: colPad(c),
							border: canonBorder(c.border),
							background: colour(c.background),
							colours: canonColours(c.colours, ROW_COLOURS),
							blocks: c.blocks.map(canonBlock)
						};
					}),
					gaps: gaps,
					valign: VALIGNS[r.valign] ? r.valign : 'top',
					padding: canonPad(r.padding),
					border: canonBorder(r.border),
					background: colour(r.background),
					colours: canonColours(r.colours, ROW_COLOURS)
				};
			}),
			row_gaps: s.rows.slice(1).map(function (r, i) { return canonGap((s.row_gaps || [])[i], 'triple', 'default'); }),
			block_gap: clamp(s.block_gap, 0, 40, 8),
			line_width: clamp(s.line_width, 1, MAX_LINE, 1),
			line_style: STYLES.hasOwnProperty(s.line_style) ? s.line_style : 'solid',
			padding: spaceVal(s.padding, 0),
			border: canonBorder(s.border),
			background: colour(s.background),
			line_below: lineVal(s.line_below, 'default'),
			line_below_style: styleVal(s.line_below_style),
			line_colour: colour(s.line_colour),
			line_space: spaceVal(s.line_space, 'double'),
			line_space_bottom: spaceVal(s.line_space_bottom, 0),
			buttons_top: spaceVal(s.buttons_top, 'double'),
			buttons_bottom: spaceVal(s.buttons_bottom, 0),
			buttons_gap: spaceVal(s.buttons_gap, 'default'),
			// The footer text's own box (Sam, 1 November 2026); text_top and text_bottom before.
			text_padding: canonPad(s.text_padding && typeof s.text_padding === 'object' ? s.text_padding : { top: spaceVal(s.text_top, 'double'), bottom: spaceVal(s.text_bottom, 0) }),
			text_border: canonBorder(s.text_border),
			text_background: colour(s.text_background),
			campaign_top: spaceVal(s.campaign_top, 'double'),
			campaign_bottom: spaceVal(s.campaign_bottom, 0),
			banners_top: spaceVal(s.banners_top, 'double'),
			banners_bottom: spaceVal(s.banners_bottom, 0),
			banners_gap: spaceVal(s.banners_gap, 'double'),
			hide_buttons: s.hide_buttons ? 1 : 0,
			hide_banners: s.hide_banners ? 1 : 0,
			hide_campaign: s.hide_campaign ? 1 : 0,
			hide_text: s.hide_text ? 1 : 0,
			hide_signoff: s.hide_signoff ? 1 : 0,
			hold: s.hold.map(canonBlock)
		};
	}

	/** Every block gets a uid while it's on the page (never saved). */
	function tag(b) {
		b._uid = 'b' + (++uid);
		return b;
	}

	function load(json) {
		var s;
		try {
			s = JSON.parse(json);
		} catch (e) {
			s = null;
		}
		if (!s) return;
		// From before rows: its columns are Row 1.
		if (!s.rows && s.columns) {
			s.rows = [{ columns: s.columns, gaps: s.gaps, valign: s.valign }];
			delete s.columns;
			delete s.gaps;
			delete s.valign;
		}
		if (!s.rows || !s.rows.length) return;
		s.rows.forEach(function (r) {
			// A gap after every column there can be (two before 5 columns).
			r.gaps = r.gaps || [];
			while (r.gaps.length < gapCount((r.columns || []).length)) r.gaps.push(newGap());
			r.valign = r.valign || 'top';
			r.padding = canonPad(r.padding);
			r.border = canonBorder(r.border);
			r.background = r.background || '';
			r.colours = canonColours(r.colours, ROW_COLOURS);
			r.columns.forEach(function (c) {
				// Space at the top and bottom (before) are its padding now.
				c.padding = colPad(c);
				delete c.top;
				delete c.bottom;
				c.border = canonBorder(c.border);
				c.background = c.background || '';
				c.colours = canonColours(c.colours, ROW_COLOURS);
				c.blocks.forEach(tag);
			});
		});
		s.row_gaps = s.row_gaps || [];
		while (s.row_gaps.length < s.rows.length - 1) s.row_gaps.push(newRowGap());
		s.hold.forEach(tag);
		state = s;
		// Footer settings a layout saved before them hasn't got: as canon() reads them.
		var full = canon(state);
		['line_space_bottom', 'buttons_top', 'buttons_bottom', 'buttons_gap', 'text_padding', 'text_border', 'text_background', 'campaign_top', 'campaign_bottom', 'banners_top', 'banners_bottom', 'banners_gap'].forEach(function (k) {
			if (state[k] === undefined || state[k] === null) state[k] = full[k];
		});
	}

	function newColumn() {
		// Double top and bottom, as the design's columns (Sam, 31 October 2026).
		return { width: 0, valign: '', padding: canonPad({ top: 'triple', bottom: 'triple' }), border: canonBorder({}), background: '', colours: canonColours({}, ROW_COLOURS), blocks: [] };
	}

	/** A gap's px for the canvas: one number, or both sides of its line when they differ. */
	function gapText(g) {
		var a = px(g.space);
		var b = px(g.space_after === undefined ? g.space : g.space_after);
		return lpx(g.line) > 0 && a !== b ? a + '/' + b : String(a);
	}

	function newRowGap() {
		return { space: 'triple', space_after: 'triple', line: 'default', line_style: 'default', colour: '' }; // Triple (24px), with the Default Line Width, unless set (Sam, 31 October 2026).
	}

	function newRow() {
		var gaps = [];
		for (var gi = 0; gi < GAP_PAD; gi++) gaps.push(newGap());
		return { columns: [newColumn()], gaps: gaps, valign: 'top', padding: canonPad(0), border: canonBorder({}), background: '', colours: canonColours({}, ROW_COLOURS) };
	}

	/** Every column in every row, in order. */
	function allCols() {
		var out = [];
		state.rows.forEach(function (r) { out = out.concat(r.columns); });
		return out;
	}

	/** Write the layout to its field as the server would, so the preview and save follow. */
	function write() {
		var json = JSON.stringify(canon(state));
		if (json === field.value) return;
		writing = true;
		field.value = json;
		field.dispatchEvent(new Event('input', { bubbles: true }));
		field.dispatchEvent(new Event('change', { bubbles: true }));
		writing = false;
	}

	/* ------------------------------------------------------------------ *
	 * Words
	 * ------------------------------------------------------------------ */

	function name(b) {
		var label = blocks[b.type] || b.type;
		return multi.indexOf(b.type) !== -1 ? label + ' ' + b.id : label;
	}

	function summary(b) {
		var bits = [];
		if (LISTS.indexOf(b.type) !== -1) {
			if (b.style === 'row') bits.push('One row');
			if (b.type !== 'person_info' && !(b.items || []).some(function (it) { return it.on; })) bits.push('Empty');
		} else if (b.type === 'name') {
			if (b.format && b.format !== 'full' && cfg.nameFormats && cfg.nameFormats[b.format]) bits.push(String(cfg.nameFormats[b.format]).replace(/^.*\((.*)\)$/, '$1'));
			bits.push(b.style === 'row' ? 'One row' : 'Stacked');
		} else if (b.type === 'social') {
			bits.push(b.direction === 'column' ? 'Column' : 'Row');
			if (!b.heading) bits.push('no heading');
		} else if (b.type === 'divider') {
			bits.push(Math.max(1, lpx(b.thickness)) + 'px');
		} else if (b.type === 'text') {
			// Its title, else the start of its text (Sam, 31 October 2026).
			var words = String(b.title || b.text || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
			bits.push(words ? (words.length > 40 ? words.slice(0, 40) + '…' : words) : 'Empty');
		}
		return bits.join(' · ');
	}

	function allBlocks() {
		var out = [];
		allCols().forEach(function (c) { out = out.concat(c.blocks); });
		return out.concat(state.hold);
	}

	function find(id) {
		var all = allBlocks();
		for (var i = 0; i < all.length; i++) {
			if (all[i]._uid === id) return all[i];
		}
		return null;
	}

	/* ------------------------------------------------------------------ *
	 * Popups
	 * ------------------------------------------------------------------ */

	/**
	 * A settings popup for obj. apply() copies the popup's fields onto obj; it
	 * runs on every change, so the preview follows. Save keeps it; closing
	 * any other way puts obj back as it was.
	 */
	function popup(title, $body, obj, apply, reset) {
		if (!window.SBTweaksConfirm) return;
		// Reopened by Reset: Cancel still puts back what it was before Reset.
		var before = carried || $.extend(true, {}, obj);
		carried = null;
		var saved = false;
		var handed = false;
		popupOpen = true;
		var $modal = window.SBTweaksConfirm.open({ title: title, body: $body, action: 'Save' }, function () {
			saved = true;
			apply();
			changed();
		});

		// Reset, bottom left (Sam, 31 October 2026): the design's settings for
		// this one, only while it differs. No asking: nothing keeps until Save.
		var $reset = $();
		if (reset) {
			var def = reset.def;
			var same = function () { return JSON.stringify(reset.pick(obj)) === JSON.stringify(def); };
			$reset = $('<button type="button" class="button sb-tweaks-button--danger sb-emailsig-layout__reset">Reset</button>').prop('disabled', same());
			$reset.on('click', function () {
				Object.keys(def).forEach(function (k) { obj[k] = $.isPlainObject(def[k]) || Array.isArray(def[k]) ? $.extend(true, Array.isArray(def[k]) ? [] : {}, def[k]) : def[k]; });
				handed = true;
				carried = before;
				window.SBTweaksConfirm.close();
				write();
				render();
				reset.reopen();
			});
			$modal.find('.sb-tweaks-dialog__buttons').prepend($reset);
		}

		$body.on('input change', function () {
			apply();
			write();
			if (reset) $reset.prop('disabled', JSON.stringify(reset.pick(obj)) === JSON.stringify(reset.def));
		});
		dock($modal);

		var watch = new MutationObserver(function () {
			if ($modal && document.body.contains($modal[0])) return;
			watch.disconnect();
			if (handed) return;
			popupOpen = false;
			if (saved) {
				record();
				return;
			}
			Object.keys(obj).forEach(function (k) { if (k !== '_uid') delete obj[k]; });
			$.extend(true, obj, before);
			write();
			render();
		});
		watch.observe(document.body, { childList: true });
	}

	/** What a popup reopened by Reset puts back on Cancel. */
	var carried = null;

	/** The design's layout (what Reset Template goes back to), in rows. */
	function designLayout() {
		var d = designDefaults();
		if (!d.rows && d.columns) d.rows = [{ columns: d.columns, gaps: d.gaps, valign: d.valign }];
		d.rows = d.rows || [];
		d.row_gaps = d.row_gaps || [];
		d.hold = d.hold || [];
		return d;
	}

	/** A block's settings as its popup has them (all but what it is). */
	function blockPick(b) {
		var o = canonBlock(b);
		delete o.type;
		delete o.id;
		return o;
	}

	/** A block's settings in the design (the same type and number), else a new one's. */
	function blockDefault(b) {
		var d = designLayout();
		var found = null;
		var look = function (x) {
			if (!found && x && x.type === b.type && (multi.indexOf(b.type) === -1 || int(x.id, 1) === int(b.id, 1))) found = x;
		};
		d.rows.forEach(function (r) { (r.columns || []).forEach(function (c) { (c.blocks || []).forEach(look); }); });
		d.hold.forEach(look);
		return blockPick($.extend(true, {}, found || defs[b.type] || {}, { type: b.type, id: b.id }));
	}

	function columnPick(c) {
		return { width: clamp(c.width, 0, 600, 0), valign: VALIGNS[c.valign] ? c.valign : '', padding: colPad(c), border: canonBorder(c.border), background: colour(c.background), colours: canonColours(c.colours, ROW_COLOURS) };
	}

	function rowPick(r) {
		return { padding: canonPad(r.padding), background: colour(r.background), border: canonBorder(r.border), colours: canonColours(r.colours, ROW_COLOURS) };
	}

	/**
	 * What a row's or column's settings change from the design's (what its
	 * Reset would put back), named short for under its name (Sam, 31
	 * October 2026): '' when nothing.
	 */
	function custom(now, def) {
		var NAMES = { padding: 'Padding', border: 'Border', background: 'Background', colours: 'Colours' };
		var out = [];
		var diff = function (k) { return JSON.stringify(now[k]) !== JSON.stringify(def[k]); };
		if ('width' in now && diff('width')) out.push(now.width ? now.width + 'px wide' : 'Auto width');
		if ('valign' in now && diff('valign')) out.push(now.valign ? VALIGNS[now.valign] : 'Row align');
		Object.keys(NAMES).forEach(function (k) { if (diff(k)) out.push(NAMES[k]); });
		return out.length ? 'Custom: ' + out.join(' · ') : '';
	}

	/**
	 * With the preview beside the editor, the popup sits over the editor's
	 * column and the preview stays clear, so changes can be watched (Sam,
	 * 29 October 2026). Stacked on a narrow screen, it stays centred.
	 */
	function dock($modal) {
		var main = document.querySelector('.sb-emailsig-editor__main');
		var side = document.querySelector('.sb-emailsig-editor__side');
		if (!$modal || !main || !side) return;
		var m = main.getBoundingClientRect();
		var r = side.getBoundingClientRect();
		if (r.left < m.right) return;
		var edge = Math.round(r.left - 12);
		$modal.addClass('sb-emailsig-layout-modal').css({
			paddingLeft: Math.max(20, Math.round(m.left)) + 'px',
			paddingRight: Math.max(20, window.innerWidth - Math.round(m.right)) + 'px',
			background: 'linear-gradient(to right, rgba(29, 35, 39, 0.45) ' + edge + 'px, transparent ' + edge + 'px)'
		});
	}

	function fieldRow(label, $input) {
		return $('<label class="sb-tweaks-dialog__field"></label>').append($('<span></span>').text(label), $input);
	}

	function numberInput(value, min, max, placeholder) {
		var $i = $('<input type="number" class="small-text">').attr({ min: min, max: max });
		if (placeholder !== undefined) $i.attr('placeholder', placeholder);
		$i.val(value);
		return $i;
	}

	function select(choices, value) {
		var $s = $('<select></select>');
		Object.keys(choices).forEach(function (k) {
			$('<option></option>').val(k).text(choices[k]).appendTo($s);
		});
		$s.val(value);
		return $s;
	}

	function check(label, on) {
		var $l = $('<label class="sb-emailsig-layout__check"><input type="checkbox"> <span></span></label>');
		$l.find('span').text(label);
		$l.find('input').prop('checked', !!on);
		return $l;
	}

	/** Shows $el only while $select has one of the values. */
	function showWhen($el, $select, values) {
		var update = function () { $el.toggle(values.indexOf($select.val()) !== -1); };
		$select.on('change', update);
		update();
	}

	/**
	 * A space: Default, Double, Half, Quarter or Eighth of the Default
	 * Spacing (each with its px now), None, or Custom with a px box.
	 * Returns the field; its get() reads the value back.
	 */
	function spaceControl(label, value, cls) {
		var choices = {};
		var names = $.extend({}, STEP_NAMES);
		if (value === 'eighth') names = $.extend({ eighth: 'Eighth' }, names);
		Object.keys(names).forEach(function (k) {
			choices[k] = names[k] + ' (' + Math.round(int(state.block_gap, 8) * STEPS[k]) + 'px)';
		});
		choices.none = 'None';
		choices.custom = 'Custom';
		var pick = typeof value === 'string' && STEPS.hasOwnProperty(value) ? value : (int(value, 0) === 0 ? 'none' : 'custom');
		return stepField(label, choices, pick, typeof value === 'number' ? value : '', 80, cls, function (sel, num) {
			if (sel === 'custom') return spaceVal(num === '' ? 0 : num, 0);
			return sel === 'none' ? 0 : sel;
		});
	}

	/**
	 * A width dropdown: 1px to MAX_LINE px, and None (0) when $none (Sam,
	 * 31 October 2026: a list, not a number box, so nothing over 8 can be
	 * typed in). $def adds Default (the Default Line Width) at the top.
	 */
	function pxSelect(value, none, def) {
		var $s = $('<select></select>');
		if (def) $('<option value="default"></option>').text('Default (' + int(state.line_width, 1) + 'px)').appendTo($s);
		for (var n = 1; n <= MAX_LINE; n++) $('<option></option>').val(String(n)).text(n + 'px').appendTo($s);
		if (none) $('<option value="0">None</option>').appendTo($s);
		var v = def && value === 'default' ? 'default' : String(Math.max(none ? 0 : 1, Math.min(MAX_LINE, int(value, none ? 0 : 1))));
		$s.val(v);
		return $s;
	}

	/** Takes 1px and 2px off a width dropdown while it's Double, or puts them back. */
	function pxDouble($s, dbl) {
		$s.find('option').each(function () {
			var n = parseInt(this.value, 10);
			if (n > 0 && n < DOUBLE_MIN) this.disabled = dbl;
		});
	}

	/** A line: Default (the Default Line Width), 1px to 8px, and None when it can have none. */
	function lineControl(label, value, none, cls) {
		var $sel = pxSelect(value, none, true);
		var $field = $('<div></div>').addClass(cls || 'sb-tweaks-dialog__field').append(
			$('<span></span>').addClass(cls ? 'sb-emailsig-field__label' : '').text(label),
			$sel
		);
		$field.sel = $sel;
		$field.get = function () {
			var v = $sel.val();
			return v === 'default' ? 'default' : parseInt(v, 10);
		};
		return $field;
	}

	/** A line's style: Default (the Default Line Style) or a style of its own. */
	function styleControl(label, value, cls) {
		var choices = { 'default': 'Default (' + (STYLES[state.line_style] || 'Solid') + ')' };
		Object.keys(STYLES).forEach(function (k) { choices[k] = STYLES[k]; });
		var $sel = select(choices, styleVal(value));
		var $field = $('<div></div>').addClass(cls || 'sb-tweaks-dialog__field').append(
			$('<span></span>').addClass(cls ? 'sb-emailsig-field__label' : '').text(label),
			$sel
		);
		$field.get = function () { return styleVal($sel.val()); };
		return $field;
	}

	/**
	 * Double is at least DOUBLE_MIN px (Sam, 31 October 2026). While a line
	 * comes out Double, 1px and 2px can't be picked, and a thinner width goes
	 * to 3px; but picking Default (thinner than 3) puts the line's style back
	 * to Default instead. $line: a lineControl(); $style: a styleControl().
	 * Returns run(e): true for e changes the dropdowns without telling the
	 * layout (drawing a card), as the server draws it at DOUBLE_MIN anyway.
	 */
	function forceDouble($line, $style) {
		var $ssel = $style.find('select');
		var resolved = function () {
			var st = $style.get();
			return st === 'default' ? state.line_style : st;
		};
		return function (e) {
			var quiet = e === true;
			var dbl = resolved() === 'double';
			var v = $line.sel.val();
			var p = v === 'default' ? int(state.line_width, 1) : parseInt(v, 10);

			if (dbl && v === 'default' && p < DOUBLE_MIN && e && e.target === $line.sel[0]) {
				$ssel.val('default');
				if (!quiet) $ssel.trigger('change');
				dbl = resolved() === 'double';
			}

			pxDouble($line.sel, dbl);
			if (!dbl || v === '0' || p >= DOUBLE_MIN) return;
			$line.sel.val(String(DOUBLE_MIN));
			if (!quiet) $line.sel.trigger('change');
		};
	}

	function stepField(label, choices, pick, number, max, cls, read) {
		var $sel = select(choices, pick);
		var $num = numberInput(number, 0, max);
		// Custom: the number with px beside it (Sam, 31 October 2026).
		var $px = $('<span class="sb-emailsig-field__number sb-emailsig-layout__px-unit"></span>').append($num, '<span>px</span>');
		var $field = $('<div></div>').addClass(cls || 'sb-tweaks-dialog__field').append(
			$('<span></span>').addClass(cls ? 'sb-emailsig-field__label' : '').text(label),
			$('<span class="sb-emailsig-layout__step"></span>').append($sel, $px)
		);
		var show = function () { $px.toggle($sel.val() === 'custom'); };
		$sel.on('change', function () {
			show();
			if ($sel.val() === 'custom' && $num.val() === '') $num.val(0);
		});
		show();
		$field.sel = $sel;
		$field.num = $num;
		$field.get = function () { return read($sel.val(), $num.val()); };
		return $field;
	}

	/** A dropdown from [ value, label ] pairs, in that order (an object puts '400' before ''). */
	function selectList(pairs, value) {
		var $s = $('<select></select>');
		pairs.forEach(function (p) { $('<option></option>').val(p[0]).text(p[1]).appendTo($s); });
		$s.val(value);
		return $s;
	}

	/** The merge tags a Text block fills in (Layout::TAGS). */
	var TEXT_TAGS = ['first_name', 'last_name', 'name', 'job_title', 'qualifications', 'company', 'phone', 'mobile', 'email', 'website'];

	/**
	 * A Text block's popup (Sam, 31 October 2026): the title and text, Link
	 * and Remove link for the text box being typed in, the merge tags, the
	 * space between, and each part's own styles (blank for the template's).
	 */
	/**
	 * An accordion of sections in a popup, one open at a time (Sam, 31
	 * October 2026). sections: [ [ title, $panel ] ]; open: which starts open.
	 * Returns the accordion and calls onOpen(i) as sections open.
	 */
	function accordion(sections, open, onOpen) {
		var $acc = $('<div class="sb-emailsig-acc"></div>');
		sections.forEach(function (sec, i) {
			var $head = $('<button type="button" class="sb-emailsig-acc__head"></button>').append(
				$('<span></span>').text(sec[0]),
				'<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>'
			);
			var $item = $('<div class="sb-emailsig-acc__item"></div>').append($head, sec[1].addClass('sb-emailsig-acc__panel'));
			$head.on('click', function () {
				$acc.children().each(function (j) {
					var on = j === i;
					$(this).toggleClass('is-open', on).children('.sb-emailsig-acc__head').attr('aria-expanded', on ? 'true' : 'false');
				});
				if (onOpen) onOpen(i);
			});
			$acc.append($item);
		});
		$acc.children().each(function (j) {
			$(this).toggleClass('is-open', j === open).children('.sb-emailsig-acc__head').attr('aria-expanded', j === open ? 'true' : 'false');
		});
		return $acc;
	}

	/** The Text popup's open section, kept when Reset reopens it. */
	var textOpen = 0;

	function textPopup(b, $body, get, $alignField, $space) {
		var $title = $('<input type="text" class="sb-emailsig-layout__text-title" autocomplete="off">').val(b.title || '');
		var $text = $('<textarea rows="6" class="sb-emailsig-layout__text-body"></textarea>').val(b.text || '');
		var $last = $text;
		var mark = null;
		$title.add($text).on('focus', function () { $last = $(this); });

		var $note = $('<span class="sb-emailsig-layout__text-note" aria-live="polite"></span>');
		var say = function (msg) {
			$note.text(msg);
			clearTimeout($note.data('t'));
			$note.data('t', setTimeout(function () { $note.text(''); }, 3000));
		};
		var $linkBtn = $('<button type="button" class="button button-small"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span> Link</button>');
		var $unlinkBtn = $('<button type="button" class="button button-small"><span class="dashicons dashicons-editor-unlink" aria-hidden="true"></span> Remove link</button>');
		var $url = $('<input type="text" class="sb-emailsig-layout__text-url" placeholder="https://, mailto: or tel:" autocomplete="off">');
		var $addLink = $('<button type="button" class="button button-small button-primary">Add link</button>');
		var $cancelLink = $('<button type="button" class="button button-small">Cancel</button>');
		var $linkRow = $('<div class="sb-emailsig-layout__linkrow"></div>').append($url, $addLink, $cancelLink).hide();

		$linkBtn.on('click', function () {
			var el = $last[0];
			mark = { el: el, start: el.selectionStart || 0, end: el.selectionEnd || 0 };
			$linkRow.show();
			$url.val('').trigger('focus');
		});
		var addLink = function () {
			var url = $.trim($url.val()).replace(/"/g, '%22');
			if (!mark || !url) {
				$linkRow.hide();
				return;
			}
			var el = mark.el;
			var v = el.value;
			var words = v.slice(mark.start, mark.end) || url.replace(/^(mailto:|tel:)/i, '');
			var tag = '<a href="' + url + '">' + words + '</a>';
			el.value = v.slice(0, mark.start) + tag + v.slice(mark.end);
			$linkRow.hide();
			el.focus();
			el.setSelectionRange(mark.start + tag.length, mark.start + tag.length);
			mark = null;
			$(el).trigger('input');
		};
		$addLink.on('click', addLink);
		$url.on('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				e.stopPropagation();
				addLink();
			}
		});
		$cancelLink.on('click', function () {
			$linkRow.hide();
			mark = null;
		});
		// The link the cursor is in, or the selection touches, back to its words.
		$unlinkBtn.on('click', function () {
			var el = $last[0];
			var v = el.value;
			var a = el.selectionStart || 0;
			var z = el.selectionEnd || a;
			var re = /<a\b[^>]*>([\s\S]*?)<\/a>/gi;
			var m;
			while ((m = re.exec(v))) {
				var end = m.index + m[0].length;
				if (a <= end && z >= m.index) {
					el.value = v.slice(0, m.index) + m[1] + v.slice(end);
					el.focus();
					el.setSelectionRange(m.index, m.index + m[1].length);
					$(el).trigger('input');
					return;
				}
			}
			say('Click inside a link first.');
		});

		var $tags = $('<div class="sb-emailsig-layout__tags"></div>').append('<span class="sb-emailsig-layout__tags-head">Insert</span>');
		TEXT_TAGS.forEach(function (t) {
			$('<button type="button" class="sb-emailsig-layout__tag"></button>').text('{' + t + '}').on('mousedown', function (e) { e.preventDefault(); }).on('click', function () {
				var el = $last[0];
				var a = el.selectionStart || 0;
				var z = el.selectionEnd || a;
				var tag = '{' + t + '}';
				el.value = el.value.slice(0, a) + tag + el.value.slice(z);
				el.focus();
				el.setSelectionRange(a + tag.length, a + tag.length);
				$(el).trigger('input');
			}).appendTo($tags);
		});

		var $textField = $('<div class="sb-tweaks-dialog__field"></div>').append(
			$('<span></span>').text('Text'),
			$('<div class="sb-emailsig-layout__text-tools"></div>').append($linkBtn, $unlinkBtn, $note),
			$linkRow,
			$text
		);
		var $content = $('<div class="sb-emailsig-layout__text-wrap"></div>').append(
			fieldRow('Title (optional)', $title),
			$textField,
			$tags
		);

		// Layout: the two spaces on one line, then Align.
		var $gap = spaceControl('Space between title and text', b.gap);
		var $layout = $('<div class="sb-emailsig-acc__grid"></div>').append($space, $gap, $alignField);
		get.title = function () { return $title.val(); };
		get.text = function () { return $text.val(); };
		get.gap = function () { return $gap.get(); };

		var WEIGHTS = [['', 'Default'], ['400', 'Normal'], ['600', 'Semi bold'], ['700', 'Bold']];
		var STYLES_ = [['', 'Default'], ['normal', 'Normal'], ['italic', 'Italic']];
		var CASES = [['', 'Default'], ['none', 'As typed'], ['uppercase', 'UPPERCASE'], ['lowercase', 'lowercase'], ['capitalize', 'Capitalise Words']];
		var $styles = {};
		[['title', 'Title style'], ['text', 'Text style']].forEach(function (pt) {
			var part = pt[0];
			var $size = numberInput(b[part + '_size'] || '', 8, 48, 'Default');
			var $w = selectList(WEIGHTS, b[part + '_weight'] || '');
			var $st = selectList(STYLES_, b[part + '_style'] || '');
			var $c = selectList(CASES, b[part + '_case'] || '');
			var $lh = numberInput(b[part + '_line'] || '', 1, 3, 'Default').attr({ step: '0.1', inputmode: 'decimal' });
			$styles[part] = $('<div class="sb-emailsig-layout__textstyle"></div>').append(
				fieldRow('Size (px)', $size),
				fieldRow('Weight', $w),
				fieldRow('Style', $st),
				fieldRow('Case', $c),
				fieldRow('Line height', $lh)
			);
			get[part + '_size'] = function () { return textSize($size.val()); };
			get[part + '_weight'] = function () { return $w.val(); };
			get[part + '_style'] = function () { return $st.val(); };
			get[part + '_case'] = function () { return $c.val(); };
			get[part + '_line'] = function () { return lhVal($lh.val()); };
		});

		// Colours go in the last section (blockPopup fills it).
		var $colours = $('<div class="sb-emailsig-acc__grid"></div>');
		// A fresh popup starts on Content; Reset reopens on the section that was open.
		if (!carried) textOpen = 0;
		$body.append(accordion([
			['Content', $content],
			['Spacing and align', $layout],
			['Title style', $styles.title],
			['Text style', $styles.text],
			['Colours', $colours]
		], textOpen, function (i) { textOpen = i; }));
		return $colours;
	}

	/** What each colour of a row's or block's own is called in its popup. */
	var COLOUR_NAMES = { text: 'Text', name: 'Name', qualifications: 'Qualifications', job: 'Job title', accent: 'Labels and separators', icons: 'Icons', heading: 'Heading', bg: 'Icon background', title: 'Title', links: 'Links' };

	/**
	 * A popup's Colours: one field per key of obj's colours, blank to follow
	 * $from (the row's, or the template's). Returns get() for the colours.
	 */
	function coloursSection($body, current, keys, from, noHead) {
		var now = $.extend({}, current || {});
		if (!noHead) $body.append($('<div class="sb-emailsig-layout__pop-sub">Colours</div>'));
		Object.keys(keys).forEach(function (k) {
			$body.append(colourField(COLOUR_NAMES[k] || k, now[k] || '', from, '', function (v) { now[k] = v; }));
		});
		return function () { return canonColours(now, keys); };
	}

	function blockPopup(b) {
		var $body = $('<div class="sb-emailsig-layout__pop"></div>');
		var $align = select(ALIGN_NAMES, b.align);
		var $space = spaceControl('Space above', b.space);
		var get = {};
		var $alignField = fieldRow('Align', $align);

		// A Text block puts these in its own sections (textPopup()).
		if (b.type !== 'text') $body.append($alignField, $space);

		// How the name shows (Sam, 31 October 2026).
		if (b.type === 'name' && cfg.nameFormats) {
			var $fmt = select(cfg.nameFormats, b.format || 'full');
			$body.append(fieldRow('Name format', $fmt));
			get.format = function () { return $fmt.val(); };
		}

		if (b.type === 'name' || LISTS.indexOf(b.type) !== -1) {
			var $style = select({ stack: 'Stacked', row: 'On one row' }, b.style);
			var $sep = select(cfg.seps, b.sep);
			var $sepRow = fieldRow('Separator', $sep);
			var $spacing = spaceControl('Spacing', b.spacing);
			$body.append(fieldRow('Layout', $style), $spacing, $sepRow);
			showWhen($sepRow, $style, ['row']);
			get.style = function () { return $style.val(); };
			get.sep = function () { return $sep.val(); };
			get.spacing = function () { return $spacing.get(); };
		}

		if (LISTS.indexOf(b.type) !== -1) {
			// What starts each line: the Info List Styles card's, or this list's own.
			var $lead = select({ '': 'Default', icon: 'Icon', letter: 'First letter', label: 'Full label', none: 'None' }, b.lead || '');
			$body.append(fieldRow('Icon display', $lead));
			get.lead = function () { return $lead.val(); };
		}

		// The items: an Info List's, or the company's alone in their own block.
		if (b.type === 'info' || b.type === 'company_info') {
			var company = b.type === 'company_info';

			// The items: drag to order, tick to show.
			var $list = $('<ul class="sb-emailsig-layout__items"></ul>');
			var others = {};
			allCols().forEach(function (c) {
				c.blocks.forEach(function (o) {
					if (o._uid === b._uid) return;
					if (o.type === 'person_info') (others.info_person = others.info_person || []).push(name(o));
					if (o.type !== 'info' && o.type !== 'company_info') return;
					(o.items || []).forEach(function (it) {
						if (it.on) (others[it.key] = others[it.key] || []).push(name(o));
					});
				});
			});
			canonItems(b.items, company).forEach(function (it) {
				var $li = $('<li></li>').attr('data-key', it.key);
				$li.append('<span class="sb-emailsig-layout__grip dashicons dashicons-menu" aria-hidden="true"></span>');
				$li.append(check(cfg.items[it.key], it.on));
				if (texts[it.key] && !company) $li.append($('<small class="sb-emailsig-layout__text"></small>').text('Company item: ' + texts[it.key]));
				if (texts[it.key] && company) $li.append($('<small class="sb-emailsig-layout__text"></small>').text(texts[it.key]));
				if (others[it.key]) $li.append($('<small class="sb-emailsig-layout__also"></small>').text('Also in ' + others[it.key].join(', ')));
				$list.append($li);
			});
			var $all = $('<button type="button" class="button button-small">Select all</button>');
			var $none = $('<button type="button" class="button button-small">Select none</button>');
			$all.add($none).on('click', function () {
				$list.find('input').prop('checked', this === $all[0]);
				$list.trigger('change');
			});
			$body.prepend($('<div class="sb-emailsig-layout__items-wrap"></div>').append(
				$('<div class="sb-emailsig-layout__items-head"></div>').append('<strong>Items</strong>', $('<span></span>').append($all, ' ', $none)),
				$list
			));
			// The whole row picks up (Sam), not just the grip. The tick box
			// still ticks, and the click a drag ends with doesn't tick it.
			if (window.Sortable) {
				var dragged = false;
				new window.Sortable($list[0], {
					animation: 150,
					filter: 'input',
					preventOnFilter: false,
					onStart: function () { dragged = true; },
					onEnd: function () {
						setTimeout(function () { dragged = false; }, 0);
						$list.trigger('change');
					}
				});
				$list[0].addEventListener('click', function (e) {
					if (dragged) { e.preventDefault(); e.stopPropagation(); }
				}, true);
			}
			get.items = function () {
				return $list.children().map(function () {
					return { key: $(this).attr('data-key'), on: $(this).find('input').prop('checked') ? 1 : 0 };
				}).get();
			};
		}

		if (b.type === 'social') {
			var $heading = check('Show the heading (Settings, Social Media)', b.heading);
			var $dir = select({ row: 'Row', column: 'Column' }, b.direction);
			var $indent = check('Line up with the Info List text (left aligned, icon style only)', b.indent);
			$body.append(fieldRow('Direction', $dir), $heading, $indent);
			get.heading = function () { return $heading.find('input').prop('checked') ? 1 : 0; };
			get.direction = function () { return $dir.val(); };
			get.indent = function () { return $indent.find('input').prop('checked') ? 1 : 0; };
		}

		if (b.type === 'divider') {
			var $thick = lineControl('Line Width', b.thickness, false);
			var $dash = styleControl('Style', b.line_style);
			var tint = b.colour || '';
			$body.append($thick, $dash, colourField('Colour', tint, 'Lines colour', cfg.lines || '', function (v) { tint = v; }));
			var dforce = forceDouble($thick, $dash);
			$body.on('change', 'select, input', dforce);
			dforce();
			get.thickness = function () { return $thick.get(); };
			get.line_style = function () { return $dash.get(); };
			get.colour = function () { return colour(tint); };
		}

		var $colourPanel = b.type === 'text' ? textPopup(b, $body, get, $alignField, $space) : null;

		// Its own colours (Sam, 31 October 2026), over its row's and the template's.
		if (defs[b.type] && defs[b.type].colours) {
			get.colours = coloursSection($colourPanel || $body, b.colours, defs[b.type].colours, 'Row or template', !!$colourPanel);
		}

		popup(name(b), $body, b, function () {
			b.align = $align.val();
			b.space = $space.get();
			Object.keys(get).forEach(function (k) { b[k] = get[k](); });
		}, { pick: blockPick, def: blockDefault(b), reopen: function () { blockPopup(b); } });
	}

	/**
	 * Padding on each side (Sam, 31 October 2026): Top, Right, Bottom and
	 * Left in a 2 x 2 grid, each a step or Custom. Locked, Top sets all four
	 * (the others show it, greyed); unlocked, each its own.
	 */
	function paddingSection(pad) {
		pad = canonPad(pad);
		var NAMES = { top: 'Top', right: 'Right', bottom: 'Bottom', left: 'Left' };
		var locked = SIDES4.every(function (sd) { return String(pad[sd]) === String(pad.top); });
		var f = {};
		var $grid = $('<div class="sb-emailsig-layout__padgrid"></div>');
		SIDES4.forEach(function (sd) {
			f[sd] = spaceControl(NAMES[sd], pad[sd]);
			$grid.append(f[sd]);
		});
		var $lock = $('<button type="button" class="sb-emailsig-layout__lock"><span class="dashicons" aria-hidden="true"></span></button>');
		var mirror = function () {
			if (!locked) return;
			SIDES4.slice(1).forEach(function (sd) {
				f[sd].num.val(f.top.num.val());
				f[sd].sel.val(f.top.sel.val()).trigger('change');
			});
		};
		var paint = function () {
			var t = locked ? 'Set each side' : 'Same padding all round';
			SIDES4.slice(1).forEach(function (sd) {
				f[sd].sel.prop('disabled', locked);
				f[sd].num.prop('disabled', locked);
				f[sd].toggleClass('is-locked', locked);
			});
			$lock.attr({ 'aria-pressed': locked ? 'true' : 'false', 'aria-label': t, title: t });
			$lock.find('.dashicons').toggleClass('dashicons-lock', locked).toggleClass('dashicons-unlock', !locked);
		};
		f.top.on('input change', 'select, input', mirror);
		var $el = $('<div class="sb-emailsig-layout__padsection"></div>').append(
			$('<div class="sb-emailsig-layout__padhead"></div>').append('<span class="sb-emailsig-layout__pop-sub">Padding</span>', $lock),
			$grid
		);
		$lock.on('click', function () {
			locked = !locked;
			mirror();
			paint();
			$el.trigger('change');
		});
		paint();
		return {
			$el: $el,
			get: function () {
				var out = {};
				SIDES4.forEach(function (sd) { out[sd] = f[locked ? 'top' : sd].get(); });
				return out;
			}
		};
	}

	/**
	 * A box's border (a row's, a column's): Line Width locked for all four
	 * sides or one each, Solid or Double (Double at DOUBLE_MIN or more),
	 * and its colour. Returns the elements, get() and force() for $body.
	 */
	function borderSection(bd) {
		bd = canonBorder(bd);
		var btint = bd.colour || '';
		var SIDES = { top: 'Top', right: 'Right', bottom: 'Bottom', left: 'Left' };
		var $sides = {};
		var locked = bd.top === bd.right && bd.top === bd.bottom && bd.top === bd.left;
		var $all = pxSelect(bd.top, true);
		var $allField = fieldRow('Line Width', $all);
		var $sideFields = $();
		Object.keys(SIDES).forEach(function (side) {
			$sides[side] = pxSelect(bd[side], true);
			$sideFields = $sideFields.add(fieldRow(SIDES[side], $sides[side]));
		});
		var $lock = $('<button type="button" class="sb-emailsig-layout__lock"><span class="dashicons" aria-hidden="true"></span></button>');
		var lockPaint = function () {
			var t = locked ? 'Set each side' : 'Same width all round';
			$allField.toggle(locked);
			$sideFields.toggle(!locked);
			$lock.attr({ 'aria-pressed': locked ? 'true' : 'false', 'aria-label': t, title: t });
			$lock.find('.dashicons').toggleClass('dashicons-lock', locked).toggleClass('dashicons-unlock', !locked);
		};
		$all.on('change', function () {
			Object.keys(SIDES).forEach(function (side) { $sides[side].val($all.val()); });
		});
		var $row = $('<div class="sb-emailsig-layout__borderrow"></div>').append($allField, $sideFields, $('<div class="sb-tweaks-dialog__field sb-emailsig-layout__lockfield"><span aria-hidden="true">&nbsp;</span></div>').append($lock));
		$lock.on('click', function () {
			locked = !locked;
			if (locked) $all.val($sides.top.val()).trigger('change');
			lockPaint();
			$row.trigger('change');
		});
		lockPaint();
		var $bstyle = styleControl('Border style', bd.line_style);
		// A Double border: a side of 1 or 2 goes to DOUBLE_MIN.
		var force = function () {
			var st = $bstyle.get();
			var dbl = (st === 'default' ? state.line_style : st) === 'double';
			$sides.all = $all;
			Object.keys($sides).forEach(function (side) {
				pxDouble($sides[side], dbl);
				var p = int($sides[side].val(), 0);
				if (dbl && p > 0 && p < DOUBLE_MIN) $sides[side].val(String(DOUBLE_MIN));
			});
			delete $sides.all;
		};
		return {
			$el: $().add($('<div class="sb-emailsig-layout__pop-sub">Border</div>')).add($row).add($bstyle).add(colourField('Border colour', btint, 'Lines colour', cfg.lines || '', function (v) { btint = v; })),
			force: force,
			get: function () {
				var out = {};
				Object.keys(SIDES).forEach(function (side) { out[side] = clamp(locked ? $all.val() : $sides[side].val(), 0, MAX_LINE, 0); });
				out.colour = colour(btint);
				out.line_style = $bstyle.get();
				return canonBorder(out);
			}
		};
	}

	/** Colours for a box: its background first, then the colours for what's in it. Returns get() for both. */
	function boxColours($body, box, from) {
		var tint = box.background || '';
		$body.append($('<div class="sb-emailsig-layout__pop-sub">Colours</div>'), colourField('Background colour', tint, 'None', '', function (v) { tint = v; }));
		var rest = coloursSection($body, box.colours, ROW_COLOURS, from, true);
		return { background: function () { return colour(tint); }, colours: rest };
	}

	/** A column's settings (Sam, 31 October 2026): its width and align, and a box of its own as a row has. */
	function columnPopup(r, i) {
		var row = state.rows[r];
		var c = row.columns[i];
		var choices = $.extend({ '': 'Default (' + VALIGNS[row.valign] + ')' }, VALIGNS);
		var $width = numberInput(c.width || '', 0, 600, 'Auto');
		var $valign = select(choices, c.valign);
		var pad = paddingSection(c.padding);
		var border = borderSection(c.border);
		var $body = $('<div class="sb-emailsig-layout__pop"></div>').append(
			// px beside the box, as Custom spaces have (Sam, 31 October 2026).
			fieldRow('Width', $('<span class="sb-emailsig-layout__width"></span>').append($width, '<span>px</span>')),
			fieldRow('Vertical align', $valign),
			pad.$el,
			border.$el
		);
		var cols = boxColours($body, c, 'Row or template');
		$body.on('change', 'select', border.force);
		border.force();

		popup((state.rows.length > 1 ? 'Row ' + (r + 1) + ', ' : '') + 'Column ' + (i + 1), $body, c, function () {
			c.width = $width.val() === '' ? 0 : clamp($width.val(), 0, 600, 0);
			c.valign = $valign.val();
			c.padding = pad.get();
			c.border = border.get();
			c.background = cols.background();
			c.colours = cols.colours();
		}, { pick: columnPick, def: columnPick(((designLayout().rows[r] || {}).columns || [])[i] || newColumn()), reopen: function () { columnPopup(r, i); } });
	}

	/** A gap's popup: def is its design settings, space the default space for its kind, reopen opens it again, rows for between rows. */
	function gapPopup(g, title, def, space, reopen, rows) {
		// Each side of the line (Sam, 31 October 2026): Left and Right
		// between columns, Top and Bottom between rows, locked together
		// while they match. With no line there's one space.
		var SIDE = rows ? ['Top spacing', 'Bottom spacing'] : ['Left spacing', 'Right spacing'];
		var $space = spaceControl(SIDE[0], g.space);
		var $after = spaceControl(SIDE[1], g.space_after === undefined ? g.space : g.space_after);
		var locked = String($space.get()) === String($after.get());
		var $lock = $('<button type="button" class="sb-emailsig-layout__lock"><span class="dashicons" aria-hidden="true"></span></button>');
		var $grid = $('<div class="sb-emailsig-layout__padgrid"></div>').append($space, $after);
		var $spacing = $('<div class="sb-emailsig-layout__padsection"></div>').append(
			$('<div class="sb-emailsig-layout__padhead"></div>').append('<span class="sb-emailsig-layout__pop-sub">Spacing</span>', $lock),
			$grid
		);
		var mirror = function () {
			if (!locked) return;
			$after.num.val($space.num.val());
			$after.sel.val($space.sel.val()).trigger('change');
		};
		var paintLock = function () {
			var t = locked ? 'Set each side' : 'Same both sides';
			$after.sel.prop('disabled', locked);
			$after.num.prop('disabled', locked);
			$after.toggleClass('is-locked', locked);
			$lock.attr({ 'aria-pressed': locked ? 'true' : 'false', 'aria-label': t, title: t });
			$lock.find('.dashicons').toggleClass('dashicons-lock', locked).toggleClass('dashicons-unlock', !locked);
		};
		$space.on('input change', 'select, input', mirror);
		$lock.on('click', function () {
			locked = !locked;
			mirror();
			paintLock();
		});
		paintLock();
		var $line = lineControl('Line Width', g.line, true);
		var $dash = styleControl('Line style', g.line_style);
		// Its own colour, as a Divider has (Sam, 31 October 2026); blank is the lines colour.
		var gtint = g.colour || '';
		var $gcol = colourField('Colour', gtint, 'Lines colour', cfg.lines || '', function (v) { gtint = v; });
		var $body = $('<div class="sb-emailsig-layout__pop"></div>').append($spacing, $line, $dash, $gcol);
		var gforce = forceDouble($line, $dash);
		$body.on('change', 'select, input', gforce);
		gforce();

		// Line style only once there is a line: choosing Solid with Line on
		// None drew nothing (Sam, 30 October 2026).
		var widths = ['default'];
		for (var w = 1; w <= MAX_LINE; w++) widths.push(String(w));
		showWhen($dash, $line.sel, widths);
		showWhen($gcol, $line.sel, widths);

		// No line: one space between them, named for what it is.
		var sides = function () {
			var on = widths.indexOf($line.sel.val()) !== -1;
			$after.toggle(on);
			$spacing.children('.sb-emailsig-layout__padhead').toggle(on);
			$grid.toggleClass('is-one', !on);
			$space.children('span').first().text(on ? SIDE[0] : 'Space between ' + (rows ? 'the rows' : 'the columns'));
		};
		$line.sel.on('change', sides);
		sides();

		popup(title, $body, g, function () {
			g.space = $space.get();
			g.space_after = locked ? g.space : $after.get();
			g.line = $line.get();
			g.line_style = $dash.get();
			g.colour = colour(gtint);
		}, { pick: function (x) { return canonGap(x, space); }, def: def, reopen: reopen });
	}

	/** A row's settings (Sam, 31 October 2026): its own padding, border, background and colours. */
	function rowPopup(r) {
		var row = state.rows[r];
		var pad = paddingSection(row.padding);
		var border = borderSection(row.border);
		var $body = $('<div class="sb-emailsig-layout__pop"></div>').append(pad.$el, border.$el);
		// Its colours for what's in it; a column's or block's own win.
		var cols = boxColours($body, row, 'Template');
		$body.on('change', 'select', border.force);
		border.force();

		popup('Row ' + (r + 1), $body, row, function () {
			row.padding = pad.get();
			row.border = border.get();
			row.background = cols.background();
			row.colours = cols.colours();
		}, { pick: rowPick, def: rowPick(designLayout().rows[r] || newRow()), reopen: function () { rowPopup(r); } });
	}

	/* ------------------------------------------------------------------ *
	 * Drawing the editor
	 * ------------------------------------------------------------------ */

	function tool(icon, label, cls) {
		return $('<button type="button" class="sb-emailsig-layout__tool"></button>')
			.addClass(cls || '')
			.attr({ title: label, 'aria-label': label })
			.append($('<span class="dashicons" aria-hidden="true"></span>').addClass('dashicons-' + icon));
	}

	function chip(b, held) {
		var $b = $('<div class="sb-emailsig-layout__block"></div>').attr('data-uid', b._uid);
		var $name = $('<span class="sb-emailsig-layout__name"></span>').text(name(b));
		var sum = summary(b);

		var $tools = $('<span class="sb-emailsig-layout__tools"></span>');
		$tools.append(tool('editor-align' + b.align, 'Align: ' + ALIGN_NAMES[b.align] + ' (click to change)', 'sb-emailsig-layout__align').attr('data-act', 'align'));
		if (window.SBTweaksConfirm) $tools.append(tool('admin-generic', 'Settings', '').attr('data-act', 'edit'));
		if (multi.indexOf(b.type) !== -1) {
			// Another one with the same settings, straight after it (Sam, 30 October 2026).
			$tools.append(tool('admin-page', 'Duplicate', 'sb-emailsig-layout__dup').attr('data-act', 'duplicate').prop('disabled', count(b.type) >= (cfg.max || 6)));
			$tools.append(tool('no-alt', 'Remove', 'sb-emailsig-layout__remove').attr('data-act', 'remove'));
		} else if (!held) {
			// One of a kind: back to Not in Use, not gone (Sam, 30 October 2026).
			$tools.append(tool('no-alt', 'Move to Not in Use', 'sb-emailsig-layout__remove').attr('data-act', 'unuse'));
		}

		$b.append($name, $tools);
		if (sum) $b.append($('<small class="sb-emailsig-layout__sum"></small>').text(sum).attr('title', sum));
		// An Info List shows what it holds, one per line, so what's on is easy to read.
		// Not in Use: just its name (Sam, 31 October 2026).
		if ((b.type === 'info' || b.type === 'company_info') && !held) {
			var $ul = $('<ul class="sb-emailsig-layout__sum-list"></ul>');
			(b.items || []).forEach(function (it) {
				if (it.on) $ul.append($('<li></li>').text(SHORT[it.key] || cfg.items[it.key] || it.key));
			});
			if ($ul.children().length) $b.append($ul);
		}
		return $b;
	}

	function list(key, items) {
		var $l = $('<div class="sb-emailsig-layout__list"></div>').attr('data-list', key);
		items.forEach(function (b) { $l.append(chip(b, key === 'hold')); });
		return $l;
	}

	function labelled(label, $input) {
		return $('<label class="sb-emailsig-field"></label>').append($('<span class="sb-emailsig-field__label"></span>').text(label), $input);
	}

	/* ------------------------------------------------------------------ *
	 * Colours: the same field as Edit styles (swatch, value, theme colours)
	 * ------------------------------------------------------------------ */

	var palette = cfg.palette || [];
	var $menu = null;
	var menuFor = null;

	/** A saved colour as hex, the way the server works it out, or ''. */
	function hex(v) {
		v = colour(v);
		if (/^#[0-9a-f]{6}$/.test(v)) return v;
		if (/^#[0-9a-f]{3}$/.test(v)) return '#' + v[1] + v[1] + v[2] + v[2] + v[3] + v[3];
		if ((cfg.colours || {})[v]) return cfg.colours[v];
		var b = /^var\(--sb-brand-([a-z-]+)\)$/.exec(v);
		if (b) return (cfg.brand || {})[b[1].replace(/-/g, '_')] || '';
		for (var i = 0; i < palette.length; i++) {
			if (palette[i].value === v) return palette[i].hex;
		}
		return '';
	}

	function closeMenu() {
		if ($menu) $menu.remove();
		$menu = null;
		if (menuFor) menuFor.setAttribute('aria-expanded', 'false');
		menuFor = null;
	}

	function openMenu(button, $text) {
		var rect = button.getBoundingClientRect();
		closeMenu();
		$menu = $('<div class="sb-emailsig-palette" role="listbox"></div>');
		palette.forEach(function (c, i) {
			if (i > 0 && palette[i - 1].group === 'brand' && c.group !== 'brand') $('<hr class="sb-emailsig-palette__sep">').appendTo($menu);
			$('<button type="button" role="option"></button>')
				.append($('<i></i>').css('background', c.hex), $('<span></span>').text(c.name), $('<code></code>').text(c.hex))
				.on('click', function () {
					$text.val(c.value).trigger('input');
					closeMenu();
					$text.trigger('focus');
				})
				.appendTo($menu);
		});
		$menu.appendTo(document.body).css({
			top: rect.bottom + window.scrollY + 4,
			left: Math.max(8, Math.min(rect.left + window.scrollX, window.scrollX + document.documentElement.clientWidth - 268))
		});
		menuFor = button;
		button.setAttribute('aria-expanded', 'true');
	}

	$(document).on('mousedown', function (e) {
		if ($menu && !$(e.target).closest('.sb-emailsig-palette, .sb-emailsig-colour__theme').length) closeMenu();
	});
	$(window).on('resize scroll', closeMenu);

	/**
	 * A colour: the swatch (showing what a blank one falls back to, dashed),
	 * the value (a hex, theme or brand colour) and the theme colours menu.
	 * $fallback is the hex a blank one shows as ('' for none).
	 */
	function colourField(label, value, empty, fallback, set) {
		var $wrap = $('<span class="sb-tweaks-colour sb-emailsig-colour"></span>');
		var $swatch = $('<input type="color" class="sb-tweaks-colour__swatch">').attr('aria-label', label);
		var $text = $('<input type="text" class="sb-tweaks-colour__value code" spellcheck="false" autocomplete="off">').attr('placeholder', empty).val(value || '');
		var $theme = $('<button type="button" class="sb-emailsig-colour__theme" aria-label="Theme colours" title="Theme colours" aria-expanded="false"><span class="dashicons dashicons-art" aria-hidden="true"></span></button>');
		var paint = function () {
			var own = hex($text.val());
			$wrap.toggleClass('is-inherited', !own);
			$swatch.val(own || fallback || '#ffffff');
		};
		$swatch.on('input', function () { $text.val(this.value).trigger('input'); });
		$text.on('input', paint);
		$theme.on('click', function (e) {
			e.preventDefault();
			if (menuFor === this) closeMenu(); else openMenu(this, $text);
		});
		if (!palette.length) $theme.prop('hidden', true);
		live($text, set);
		paint();
		// A div, not a label: a label would open the swatch's picker on any click.
		return $('<div class="sb-emailsig-field sb-emailsig-field--colour"></div>').append($('<span class="sb-emailsig-field__label"></span>').text(label), $wrap.append($swatch, $text, $theme));
	}

	/** A step field on a card: the layout follows as it changes. */
	/**
	 * Padding on four sides in a card (the footer text's box, Sam, 1
	 * November 2026): Padding top, right, bottom and left, with a lock that
	 * makes the other three follow top. Unlocked to start (footLocks).
	 */
	function cardPadRow(id, pad) {
		var NAMES = { top: 'Padding top', right: 'Padding right', bottom: 'Padding bottom', left: 'Padding left' };
		var f = {};
		var $row = $('<div class="sb-emailsig-fields sb-emailsig-layout__box sb-emailsig-layout__padrow"></div>');
		SIDES4.forEach(function (sd) {
			f[sd] = cardStep(spaceControl(NAMES[sd], pad[sd], 'sb-emailsig-field'), function (v) { pad[sd] = v; });
			$row.append(f[sd]);
		});
		var key = 'pad_' + id;
		var $lock = $('<button type="button" class="sb-emailsig-layout__lock"><span class="dashicons" aria-hidden="true"></span></button>');
		var mirror = function () {
			if (!footLocks[key]) return;
			SIDES4.slice(1).forEach(function (sd) {
				f[sd].num.val(f.top.num.val());
				f[sd].sel.val(f.top.sel.val()).trigger('change');
				pad[sd] = pad.top;
			});
			write();
		};
		var paint = function () {
			var on = !!footLocks[key];
			var t = on ? 'Set each side' : 'Same padding all round';
			SIDES4.slice(1).forEach(function (sd) {
				f[sd].sel.prop('disabled', on);
				f[sd].num.prop('disabled', on);
				f[sd].toggleClass('is-locked', on);
			});
			$lock.attr({ 'aria-pressed': on ? 'true' : 'false', 'aria-label': t, title: t });
			$lock.find('.dashicons').toggleClass('dashicons-lock', on).toggleClass('dashicons-unlock', !on);
		};
		f.top.on('input change', 'select, input', mirror);
		$lock.on('click', function () {
			footLocks[key] = !footLocks[key];
			mirror();
			paint();
		});
		paint();
		return $row.append($('<div class="sb-emailsig-field sb-emailsig-layout__lockfield"></div>').append('<span class="sb-emailsig-field__label" aria-hidden="true">&nbsp;</span>', $lock));
	}

	/* Each card border's lock: one Line Width for all four sides, or each its own. */
	var borderLocks = {};

	/**
	 * A border in a card (the Container's, the footer text's): Line Width
	 * for all four sides, or one per side with the lock open, then Style and
	 * Colour. Locked to start when all four match. bd is the border kept.
	 */
	function cardBorderRow(bd, key) {
		var $row2 = $('<div class="sb-emailsig-fields sb-emailsig-layout__box"></div>');
		var SIDES = { top: 'Top', right: 'Right', bottom: 'Bottom', left: 'Left' };
		var $sides = {};
		var $sideFields = $();
		Object.keys(SIDES).forEach(function (side) {
			$sides[side] = live(pxSelect(bd[side], true).addClass('sb-emailsig-layout__px'), function (v) { bd[side] = clamp(v, 0, MAX_LINE, 0); });
			$sideFields = $sideFields.add(labelled(SIDES[side], $sides[side]));
		});
		if (borderLocks[key] === undefined || borderLocks[key] === null) borderLocks[key] = bd.top === bd.right && bd.top === bd.bottom && bd.top === bd.left;
		var $all = live(pxSelect(bd.top, true).addClass('sb-emailsig-layout__px'), function (v) {
			var n = clamp(v, 0, MAX_LINE, 0);
			Object.keys(SIDES).forEach(function (side) {
				bd[side] = n;
				$sides[side].val(n);
			});
		});
		var $allField = labelled(key === 'text' ? 'Border width' : 'Line Width', $all);
		var $lock = $('<button type="button" class="sb-emailsig-layout__lock"><span class="dashicons" aria-hidden="true"></span></button>');
		var lockPaint = function () {
			var on = borderLocks[key];
			var t = on ? 'Set each side' : 'Same width all round';
			$allField.toggle(on);
			$sideFields.toggle(!on);
			$lock.attr({ 'aria-pressed': on ? 'true' : 'false', 'aria-label': t, title: t });
			$lock.find('.dashicons').toggleClass('dashicons-lock', on).toggleClass('dashicons-unlock', !on);
		};
		$lock.on('click', function () {
			borderLocks[key] = !borderLocks[key];
			if (borderLocks[key]) $all.val(int($sides.top.val(), 0)).trigger('change');
			lockPaint();
		});
		lockPaint();
		$row2.append($allField, $sideFields, $('<div class="sb-emailsig-field sb-emailsig-layout__lockfield"></div>').append('<span class="sb-emailsig-field__label" aria-hidden="true">&nbsp;</span>', $lock));
		var $bstyle = cardStep(styleControl(key === 'text' ? 'Border style' : 'Style', bd.line_style, 'sb-emailsig-field'), function (v) { bd.line_style = v; });
		$row2.append($bstyle);
		$row2.append(colourField(key === 'text' ? 'Border colour' : 'Colour', bd.colour, 'Lines colour', cfg.lines || '', function (v) { bd.colour = v; }));
		// A Double border: a side of 1 or 2 goes to DOUBLE_MIN (0 is no border on that side).
		var bforce = function (quiet) {
			var st = $bstyle.get();
			var dbl = (st === 'default' ? state.line_style : st) === 'double';
			pxDouble($all, dbl);
			Object.keys($sides).forEach(function (side) { pxDouble($sides[side], dbl); });
			if (!dbl) return;
			if (borderLocks[key]) {
				var a = int($all.val(), 0);
				if (a > 0 && a < DOUBLE_MIN) {
					$all.val(DOUBLE_MIN);
					if (quiet !== true) $all.trigger('change');
				}
				return;
			}
			Object.keys($sides).forEach(function (side) {
				var p = int($sides[side].val(), 0);
				if (p > 0 && p < DOUBLE_MIN) {
					$sides[side].val(DOUBLE_MIN);
					if (quiet !== true) $sides[side].trigger('change');
				}
			});
		};
		$row2.on('change', 'select, input', bforce);
		bforce(true);
		return $row2;
	}

	/* Footer Settings' top and bottom locks, kept while the page is open (Sam, 31 October 2026: unlocked to start). */
	var footLocks = {};

	/**
	 * Spacing top and bottom for a footer part, with a lock: locked, the
	 * bottom follows the top and is greyed out.
	 */
	function spacePair(id, topKey, bottomKey) {
		// Padding, as the footer text's box calls it (Sam, 1 November 2026: Spacing before).
		var $top = cardStep(spaceControl('Padding top', state[topKey], 'sb-emailsig-field'), function (v) { state[topKey] = v; });
		var $bot = cardStep(spaceControl('Padding bottom', state[bottomKey], 'sb-emailsig-field'), function (v) { state[bottomKey] = v; });
		var $lock = $('<button type="button" class="sb-emailsig-layout__lock"><span class="dashicons" aria-hidden="true"></span></button>');
		var mirror = function () {
			if (!footLocks[id]) return;
			$bot.num.val($top.num.val());
			$bot.sel.val($top.sel.val()).trigger('change');
			state[bottomKey] = state[topKey];
			write();
		};
		var paint = function () {
			var on = !!footLocks[id];
			var t = on ? 'Set top and bottom apart' : 'Same top and bottom';
			$bot.sel.prop('disabled', on);
			$bot.num.prop('disabled', on);
			$bot.toggleClass('is-locked', on);
			$lock.attr({ 'aria-pressed': on ? 'true' : 'false', 'aria-label': t, title: t });
			$lock.find('.dashicons').toggleClass('dashicons-lock', on).toggleClass('dashicons-unlock', !on);
		};
		$top.on('input change', 'select, input', mirror);
		$lock.on('click', function () {
			footLocks[id] = !footLocks[id];
			mirror();
			paint();
		});
		paint();
		return $('<div class="sb-emailsig-fields sb-emailsig-layout__box"></div>').append(
			$top,
			$bot,
			$('<div class="sb-emailsig-field sb-emailsig-layout__lockfield"></div>').append('<span class="sb-emailsig-field__label" aria-hidden="true">&nbsp;</span>', $lock)
		);
	}

	function cardStep($field, set) {
		$field.on('input change', 'select, input', function () {
			set($field.get());
			write();
		});
		return $field;
	}

	/** An input that updates the layout as it is typed in (no redraw, so it keeps focus). */
	function live($input, set) {
		$input.on('input change', function () {
			set(this.type === 'checkbox' ? this.checked : this.value);
			write();
		});
		return $input;
	}

	function render() {
		sortables.forEach(function (s) { s.destroy(); });
		sortables = [];

		var $root = $(root).empty();

		// Rows (Sam, 31 October 2026): each with its columns, their default
		// align and its own settings, the gap between rows, and Add row.
		var $rows = $('<div class="sb-emailsig-layout__rows"></div>');
		var many = state.rows.length > 1;

		state.rows.forEach(function (row, r) {
			if (r > 0) {
				var rg = state.row_gaps[r - 1] || newRowGap();
				$rows.append(
					$('<button type="button" class="sb-emailsig-layout__rowgap"></button>')
						.toggleClass('is-line', lpx(rg.line) > 0)
						.attr({ 'data-rowgap': r - 1, title: 'Space between rows ' + r + ' and ' + (r + 1) + (lpx(rg.line) ? ', with a ' + lpx(rg.line) + 'px line' : '') })
						.append('<span class="sb-emailsig-layout__rowgap-line"></span>', $('<span class="sb-emailsig-layout__gap-px"></span>').text(gapText(rg)))
				);
			}

			var $row = $('<div class="sb-emailsig-layout__row"></div>').attr('data-row', r);
			var $rhead = $('<div class="sb-emailsig-layout__row-head"></div>');
			if (many) $rhead.append($('<span class="sb-emailsig-layout__row-grip dashicons dashicons-move" title="Drag to move this row" aria-hidden="true"></span>'));
			$rhead.append($('<span class="sb-emailsig-layout__row-name"></span>').text('Row ' + (r + 1)));

			// Columns and their default align, for this row.
			var $bar = $('<div class="sb-emailsig-fields sb-emailsig-layout__bar"></div>');
			$bar.append(labelled('Vertical align', live(select(VALIGNS, row.valign), function (v) { row.valign = v; })));
			$rhead.append($bar);
			// What its settings change from the design's, after Vertical align (Sam, 31 October 2026).
			var rsum = custom(rowPick(row), rowPick(designLayout().rows[r] || newRow()));
			if (rsum) $rhead.append($('<span class="sb-emailsig-layout__row-sum"></span>').text(rsum).attr('title', rsum + ' (the row\'s settings; Reset there puts back the template\'s)'));

			var $rtools = $('<span class="sb-emailsig-layout__row-tools"></span>');
			if (window.SBTweaksConfirm) $rtools.append(tool('admin-generic', 'Row ' + (r + 1) + ' settings', '').attr('data-row-edit', r));
			if (many) $rtools.append(tool('no-alt', 'Remove row ' + (r + 1), 'sb-emailsig-layout__remove').attr('data-row-remove', r));
			$rhead.append($rtools);

			// The board: columns with the gaps between them.
			var $board = $('<div class="sb-emailsig-layout__board"></div>').attr('data-board', r);
			var lone = row.columns.length < 2; // A row keeps one column: nowhere to drag it.
			row.columns.forEach(function (c, i) {
				if (i > 0) {
					var g = row.gaps[i - 1];
					$board.append(
						$('<button type="button" class="sb-emailsig-layout__gap"></button>')
							.toggleClass('is-line', lpx(g.line) > 0)
							.attr({ 'data-gap': r + ':' + (i - 1), title: 'Space between columns ' + i + ' and ' + (i + 1) + (lpx(g.line) ? ', with a ' + lpx(g.line) + 'px line' : '') })
							.append('<span class="sb-emailsig-layout__gap-line"></span>', $('<span class="sb-emailsig-layout__gap-px"></span>').text(gapText(g)))
					);
				}
				// Under its name, what its settings change from the design's (Sam, 31 October 2026).
				var csum = custom(columnPick(c), columnPick(((designLayout().rows[r] || {}).columns || [])[i] || newColumn()));
				// Dragged by its grip, along the row or into another (Sam, 31 October 2026).
				var $head = $('<div class="sb-emailsig-layout__col-head"></div>')
					.append(lone ? '' : $('<span class="sb-emailsig-layout__col-grip dashicons dashicons-move" title="Drag to move this column" aria-hidden="true"></span>'))
					.append($('<span class="sb-emailsig-layout__col-name"></span>').text('Column ' + (i + 1)).append(csum ? $('<small></small>').text(csum).attr('title', csum) : ''));
				var $ctools = $('<span class="sb-emailsig-layout__col-tools"></span>');
				if (window.SBTweaksConfirm) $ctools.append(tool('admin-generic', 'Column ' + (i + 1) + ' settings', '').attr('data-col', r + ':' + i));
				if (row.columns.length > 1) $ctools.append(tool('no-alt', 'Remove column ' + (i + 1), 'sb-emailsig-layout__remove').attr('data-col-remove', r + ':' + i));
				$head.append($ctools);
				$board.append($('<div class="sb-emailsig-layout__col"></div>').attr('data-col-at', r + ':' + i).append($head, list('r' + r + 'c' + i, c.blocks)));
			});
			// Add a column, at the end of the row.
			var full = row.columns.length >= MAX_COLS;
			$board.append($('<button type="button" class="sb-emailsig-layout__add-col"></button>')
				.attr({ 'data-col-add': r, title: full ? 'The most a row can have' : 'Add a column', 'aria-label': 'Add a column to row ' + (r + 1) })
				.prop('disabled', full)
				.append('<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>'));

			$rows.append($row.append($rhead, $board));
		});

		$root.append($rows);
		$root.append($('<div class="sb-emailsig-layout__add-row-wrap"></div>').append(
			$('<button type="button" class="button button-small sb-emailsig-layout__add-row"></button>').toggleClass('disabled', state.rows.length >= (cfg.maxRows || 12)).prop('disabled', state.rows.length >= (cfg.maxRows || 12)).append('<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>', ' Add row')
		));

		// The holding area and adding blocks.
		var $hold = $('<div class="sb-emailsig-layout__hold"></div>')
			.append('<div class="sb-emailsig-layout__hold-head">Not in Use <small>drag widgets here to hide them</small></div>', list('hold', state.hold));
		$root.append($hold);

		// New blocks: dragged on to the layout, where they're wanted (Sam, 30 October 2026).
		var $new = $('<div class="sb-emailsig-layout__list sb-emailsig-layout__new"></div>').attr('data-list', 'new');
		multi.forEach(function (type) {
			var full = count(type) >= (cfg.max || 6);
			$('<div class="sb-emailsig-layout__block sb-emailsig-layout__new-block"></div>')
				.attr({ 'data-new': type, title: full ? 'The most there can be' : 'Drag on to the layout' })
				.toggleClass('is-full', full)
				.append($('<span class="sb-emailsig-layout__name"></span>').text('New ' + blocks[type]))
				.appendTo($new);
		});
		$root.append($('<div class="sb-emailsig-layout__add"></div>').append('<span class="sb-emailsig-layout__add-head">Add</span>', $new));

		// Container Settings and Footer Divider: cards of their own (Sam, 29 October 2026).
		// Padding and background on one row, the borders on the next (Sam).
		var $box = $('[data-sb-emailsig-layout-box]').empty();
		var $row1 = $('<div class="sb-emailsig-fields sb-emailsig-layout__box"></div>');
		var $row2 = $('<div class="sb-emailsig-fields sb-emailsig-layout__box"></div>');
		$row1.append(cardStep(spaceControl('Padding', state.padding, 'sb-emailsig-field'), function (v) { state.padding = v; }));
		$row1.append(colourField('Background colour', state.background, 'None', '', function (v) { state.background = v; }));
		// The border's row: Line Width (or each side), its lock, Style and Colour (cardBorderRow()).
		$row2 = cardBorderRow(state.border, 'box');
		// Then Hide Sign-off, for the sign-off above it all (Sam, 30 October 2026).
		var $sign = check('Hide Sign-off', state.hide_signoff);
		$sign.find('input').on('change', function () {
			state.hide_signoff = this.checked ? 1 : 0;
			write();
		});
		$box.append($row1, $('<div class="sb-emailsig-item__name sb-emailsig-layout__sub">Border</div>'), $row2,
			$('<div class="sb-emailsig-item__name sb-emailsig-layout__sub">Sign-off</div>'),
			$('<div class="sb-emailsig-layout__controls"></div>').append($sign));

		// Footer Settings: the Footer Divider, and Footer Controls to leave
		// parts out of this template's signatures (Sam, 29 October 2026).
		var $foot = $('[data-sb-emailsig-layout-footer]').empty();
		var $fwidth = cardStep(lineControl('Line Width', state.line_below, true, 'sb-emailsig-field'), function (v) { state.line_below = v; });
		var $fstyle = cardStep(styleControl('Style', state.line_below_style, 'sb-emailsig-field'), function (v) { state.line_below_style = v; });
		var fforce = forceDouble($fwidth, $fstyle);
		var $controls = $('<div class="sb-emailsig-layout__controls"></div>');
		HIDES.forEach(function (h) {
			var $c = check(h[1], state['hide_' + h[0]]);
			$c.find('input').on('change', function () {
				state['hide_' + h[0]] = this.checked ? 1 : 0;
				write();
			});
			$controls.append($c);
		});
		// Each group in a section of its own with a line between, as the
		// Type card on Edit styles (Sam, 31 October 2026).
		var group = function (name) {
			var $g = $('<div class="sb-emailsig-item"></div>').append($('<div class="sb-emailsig-item__name"></div>').text(name));
			$foot.append($g);
			return $g;
		};
		group('Footer Divider').append(
			$('<div class="sb-emailsig-fields sb-emailsig-layout__box"></div>').append(
				$fwidth,
				$fstyle,
				colourField('Colour', state.line_colour, 'Lines colour', cfg.lines || '', function (v) { state.line_colour = v; })
			).on('change', 'select, input', fforce),
			// Its spacing on a row of its own, as the parts below (Sam, 31 October 2026).
			spacePair('divider', 'line_space', 'line_space_bottom')
		);
		// Each part's space above and below (Sam, 31 October 2026).
		FOOT_PARTS.forEach(function (fp) {
			var $g = group(fp[1]);
			if (fp[0] === 'text') {
				// Its own box: padding on four sides, a background and a border (Sam, 1 November 2026).
				$g.append(
					cardPadRow('text', state.text_padding),
					$('<div class="sb-emailsig-fields sb-emailsig-layout__box"></div>').append(
						colourField('Background colour', state.text_background, 'None', '', function (v) { state.text_background = v; })
					),
					cardBorderRow(state.text_border, 'text')
				);
				return;
			}
			var $pair = spacePair(fp[0], fp[0] + '_top', fp[0] + '_bottom');
			if (FOOT_GAPS[fp[0]]) {
				$pair.find('.sb-emailsig-layout__lockfield').after(cardStep(spaceControl('Space between', state[fp[0] + '_gap'], 'sb-emailsig-field'), function (v) { state[fp[0] + '_gap'] = v; }));
			}
			$g.append($pair);
		});
		group('Footer Controls').append($controls);
		fforce(true);

		if (window.Sortable) {
			$root.find('.sb-emailsig-layout__list').each(function () {
				if ($(this).attr('data-list') === 'new') {
					// Copies of these go on to the layout; the originals stay put.
					sortables.push(new window.Sortable(this, {
						group: { name: 'sbes-layout', pull: 'clone', put: false },
						sort: false,
						animation: 150,
						filter: '.is-full',
						ghostClass: 'is-ghost',
						onEnd: sync
					}));
					return;
				}
				sortables.push(new window.Sortable(this, {
					group: 'sbes-layout',
					animation: 150,
					filter: '.sb-emailsig-layout__tool',
					preventOnFilter: false,
					emptyInsertThreshold: 24,
					ghostClass: 'is-ghost',
					onEnd: sync
				}));
			});

			// Columns move by their grip, along a row or into another; a row
			// keeps one column at least and takes no more than MAX_COLS.
			var colStart = 0;
			$rows.find('.sb-emailsig-layout__board').each(function () {
				sortables.push(new window.Sortable(this, {
					group: 'sbes-cols',
					handle: '.sb-emailsig-layout__col-grip',
					draggable: '.sb-emailsig-layout__col',
					animation: 150,
					ghostClass: 'is-ghost',
					onStart: function (evt) { colStart = $(evt.from).children('.sb-emailsig-layout__col').length; },
					onMove: function (evt) {
						if (evt.to === evt.from) return true;
						if (colStart <= 1) return false;
						return $(evt.to).children('.sb-emailsig-layout__col').not(evt.dragged).length < MAX_COLS;
					},
					onEnd: function (evt) {
						moveColumn(int($(evt.from).attr('data-board'), 0), evt.oldDraggableIndex, int($(evt.to).attr('data-board'), 0), evt.newDraggableIndex);
					}
				}));
			});

			// Rows move by their grip.
			if (state.rows.length > 1) {
				sortables.push(new window.Sortable($rows[0], {
					handle: '.sb-emailsig-layout__row-grip',
					draggable: '.sb-emailsig-layout__row',
					animation: 150,
					ghostClass: 'is-ghost',
					onEnd: function () {
						var order = $rows.children('.sb-emailsig-layout__row').map(function () { return int($(this).attr('data-row'), 0); }).get();
						state.rows = order.map(function (i) { return state.rows[i]; });
						changed();
					}
				}));
			}
		}
	}

	/** After a drag: read the lists back into the layout. */
	function sync() {
		// Look every block up before any list changes, as a block moves between them.
		var byUid = {};
		allBlocks().forEach(function (b) { byUid[b._uid] = b; });
		$(root).find('.sb-emailsig-layout__list').each(function () {
			var key = $(this).attr('data-list');
			if (key === 'new') return;
			var items = $(this).children('.sb-emailsig-layout__block').map(function () {
				var type = $(this).attr('data-new');
				if (type) return count(type) < (cfg.max || 6) ? fresh(type) : null;
				return byUid[$(this).attr('data-uid')] || null;
			}).get();
			var at = /^r(\d+)c(\d+)$/.exec(key);
			if (key === 'hold') {
				state.hold = items;
			} else if (at && state.rows[+at[1]] && state.rows[+at[1]].columns[+at[2]]) {
				state.rows[+at[1]].columns[+at[2]].blocks = items;
			}
		});
		changed();
	}

	function changed() {
		write();
		render();
	}

	/* ------------------------------------------------------------------ *
	 * The preview and the card's Reset
	 * ------------------------------------------------------------------ */

	var timer = null;
	var asked = 0;

	function fit() {
		var $fit = $('.sb-emailsig-editor__fit');
		var $box = $('.sb-emailsig-editor__preview');
		if (!$fit.length || !$box.length) return;
		var el = $fit[0];
		el.style.zoom = 1;
		var room = $box[0].clientWidth - 40;
		var scale = el.scrollWidth > room && room > 0 ? room / el.scrollWidth : 1;
		el.style.zoom = scale;
		$('.sb-emailsig-editor__scale').text(scale < 1 ? 'shown at ' + Math.round(scale * 100) + '%' : '');
	}

	function preview() {
		var id = ++asked;
		var $status = $('.sb-emailsig-editor__status').text('Updating...');
		var data = { action: 'sb_emailsig_preview', nonce: cfg.nonce, template: cfg.tpl, scope: 'layout', 's[layout]': field.value };
		OWN.forEach(function (k) {
			var el = settingsField(k);
			if (el) data['s[' + k + ']'] = el.value;
		});
		var $person = $('.sb-emailsig-editor__person');
		if ($person.length) data.person = $person.val();
		$.post(cfg.ajax, data).done(function (res) {
			if (id !== asked) return;
			if (res && res.success) {
				$('.sb-emailsig-editor__fit').html(res.data);
				fit();
				$status.text('');
				// The icons in a new Primary colour, as they'll be saved.
				if (iconsChanged()) {
					buildIcons().then(function (icons) {
						if (id === asked) swapIcons(icons);
					});
				}
				// Rows' and blocks' own icon colours.
				swapVariants();
			} else {
				$status.text('Preview not updated');
			}
		}).fail(function () {
			if (id === asked) $status.text('Preview not updated');
		});
	}

	function schedule() {
		clearTimeout(timer);
		timer = setTimeout(preview, 200);
	}

	/** The card shows its Reset, and the accent border, once the layout isn't the design's. */
	function mark() {
		var edited = field.value !== (field.getAttribute('data-default') || '');
		$(field).closest('.sb-emailsig-editor__card').toggleClass('is-edited', edited).find('.sb-emailsig-card-reset').prop('disabled', !edited);
	}

	/* ------------------------------------------------------------------ *
	 * Layout Settings: colours and font (named fields from PHP) and the
	 * layout's Default Spacing, Line Width and Line Style
	 * ------------------------------------------------------------------ */

	var OWN = ['primary', 'text', 'lines', 'font'];
	var DEFAULTS = ['block_gap', 'line_width', 'line_style'];

	function settingsField(k) {
		return document.querySelector('[data-sb-emailsig-layout-settings-card] [name="s[' + k + ']"]');
	}

	function primaryHex() {
		var el = settingsField('primary');
		return (el && hex(el.value)) || (cfg.brand || {}).primary || '#000000';
	}

	/** The Lines colour now: its own, or the Primary. */
	function linesHex() {
		var el = settingsField('lines');
		return (el && hex(el.value)) || primaryHex();
	}

	function fallbackHex(fallback) {
		if (fallback === 'brand_primary') return (cfg.brand || {}).primary || '';
		if (fallback === 'brand_text') return (cfg.brand || {}).text || '';
		if (fallback === 'primary') return primaryHex();
		return '';
	}

	/** Swatch and theme colours for a colour field PHP drew. */
	function enhanceColour(wrap) {
		var $wrap = $(wrap);
		var $swatch = $wrap.find('.sb-tweaks-colour__swatch');
		var $text = $wrap.find('.sb-tweaks-colour__value');
		var $theme = $wrap.find('.sb-emailsig-colour__theme');
		$swatch.on('input', function () { $text.val(this.value).trigger('input').trigger('change'); });
		if (palette.length) {
			$theme.prop('hidden', false).on('click', function (e) {
				e.preventDefault();
				if (menuFor === this) closeMenu(); else openMenu(this, $text);
			});
		}
	}

	function paintSettings() {
		$('[data-sb-emailsig-layout-settings-card] .sb-emailsig-colour').each(function () {
			var $wrap = $(this);
			var own = hex($wrap.find('.sb-tweaks-colour__value').val());
			$wrap.toggleClass('is-inherited', !own);
			$wrap.find('.sb-tweaks-colour__swatch').val(own || fallbackHex($wrap.attr('data-fallback')) || '#ffffff');
		});
	}

	/** The layout's defaults as the design has them. */
	function designDefaults() {
		try {
			return JSON.parse(field.getAttribute('data-default') || '{}');
		} catch (e) {
			return {};
		}
	}

	/** Shows the card's Reset, and its accent border, once anything differs from the design. */
	function markSettings() {
		markResetAll();
		var $card = $('[data-sb-emailsig-layout-settings-card]');
		var d = designDefaults();
		var edited = $card.find('[name][data-default]').toArray().some(function (el) {
			return (el.value || '') !== (el.getAttribute('data-default') || '');
		}) || DEFAULTS.some(function (k) { return d.hasOwnProperty(k) && String(state[k]) !== String(d[k]); });
		$card.toggleClass('is-edited', edited).find('.sb-emailsig-card-reset').prop('disabled', !edited);
	}

	function drawDefaults() {
		var $box = $('[data-sb-emailsig-layout-settings]').empty();
		// Every space, line and style left at Default follows these; a change
		// redraws, so the px shown beside each step stay right.
		var $gap = live(numberInput(state.block_gap, 0, 40), function (v) { state.block_gap = clamp(v, 0, 40, 8); });
		var $width = live(pxSelect(state.line_width, false).addClass('sb-emailsig-layout__px'), function (v) { state.line_width = clamp(v, 1, MAX_LINE, 1); });
		var $style = live(select(STYLES, styleVal(state.line_style) === 'double' ? 'double' : 'solid'), function (v) { state.line_style = v; });
		// Double: the Default Line Width is DOUBLE_MIN or more (Sam, 31 October 2026).
		var force = function () {
			var dbl = $style.val() === 'double';
			pxDouble($width, dbl);
			if (dbl && int($width.val(), 1) < DOUBLE_MIN) {
				$width.val(DOUBLE_MIN);
				state.line_width = DOUBLE_MIN;
				write();
			}
		};
		$style.on('change', force);
		$width.on('change', force);
		[$gap, $width, $style].forEach(function ($i) { $i.on('change', function () { render(); markSettings(); }); });
		$box.append($('<div class="sb-emailsig-fields sb-emailsig-layout__box"></div>').append(
			labelled('Default Spacing (px)', $gap),
			labelled('Default Line Width', $width),
			labelled('Default Line Style', $style)
		));
		pxDouble($width, $style.val() === 'double');
	}

	function resetSettings() {
		var d = designDefaults();
		$('[data-sb-emailsig-layout-settings-card] [name][data-default]').each(function () {
			$(this).val(this.getAttribute('data-default') || '');
			this.dispatchEvent(new Event('input', { bubbles: true }));
			this.dispatchEvent(new Event('change', { bubbles: true }));
		});
		DEFAULTS.forEach(function (k) {
			if (d.hasOwnProperty(k)) state[k] = d[k];
		});
		write();
		drawDefaults();
		render();
		paintSettings();
		markSettings();
	}

	/* The icons, redrawn when the Primary colour changes (as styles.js draws them). */

	var icons = cfg.icons || null;
	var sending = false;
	var resetOther = false;
	// Each company's brand colours, colours menu and social icons (Sam, 30
	// October 2026): the preview is in the previewed person's company, and a
	// save draws the icons for every company.
	var companies = (icons && icons.companies) || [];
	var shownCompany = 0;
	var startPrimary = null;

	/** Draw in company $id's colours and social icons. */
	var usedCompany = 0;
	function useCompany(id) {
		id = parseInt(id, 10) || 0;
		usedCompany = id;
		for (var i = 0; i < companies.length; i++) {
			if (companies[i].id === id) {
				cfg.brand = companies[i].brand;
				palette = companies[i].palette || palette;
				if (icons) icons.social = companies[i].social;
				return true;
			}
		}
		return false;
	}

	/** The company of the person picked for the preview. */
	function personCompany() {
		var opt = $('.sb-emailsig-editor__person option:selected');
		return opt.length && opt.attr('data-company') ? parseInt(opt.attr('data-company'), 10) : ((icons && parseInt(icons.company, 10)) || 0);
	}

	function primaryValue() {
		var el = settingsField('primary');
		return el ? (el.value || '').trim().toLowerCase() : '';
	}

	function iconsChanged() {
		if (!icons || !window.SBEmailSigIcons) return false;
		if (companies.length) return startPrimary !== null && primaryValue() !== startPrimary;
		return primaryHex() !== icons.primary;
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

	function buildIcons() {
		var p = primaryHex();
		var st = resetOther && icons.defaults ? icons.defaults : icons.styles;
		var c = st.contact;
		var so = st.social;
		var names = Object.keys(icons.contact || {});
		var contact = Promise.all(names.map(function (n) {
			return window.SBEmailSigIcons.png(icons.contact[n], { size: c.size, colour: c.colour || (c.background ? '#ffffff' : p), bg: c.background ? (c.bg || p) : '', radius: c.radius });
		}));
		var socials = so ? Promise.all((icons.social || []).map(function (svg) {
			return window.SBEmailSigIcons.png(svg, { size: so.size, colour: so.colour, bg: so.background ? (so.bg || p) : '', radius: so.radius });
		})) : Promise.resolve([]);
		return Promise.all([contact, socials, buildVariants()]).then(function (res) {
			var out = { contact: {}, social: res[1], variants: res[2] };
			names.forEach(function (n, i) { out.contact[n] = res[0][i]; });
			return out;
		});
	}

	/** A colour as six lower case hex digits with its #, or '' (Layout::hex6()). */
	function hex6(v) {
		v = String(v || '').toLowerCase();
		var m = /^#([0-9a-f])([0-9a-f])([0-9a-f])$/.exec(v);
		if (m) return '#' + m[1] + m[1] + m[2] + m[2] + m[3] + m[3];
		return /^#[0-9a-f]{6}$/.test(v) ? v : '';
	}

	/** A variant icon set's name, as Layout::variant_key() makes it. */
	function variantKey(kind, size, col, bg, radius) {
		var c = hex6(col).slice(1);
		var g = hex6(bg).slice(1);
		return c ? kind + (parseInt(size, 10) || 0) + '-' + c + '-' + (g || 'x') + '-' + (parseInt(radius, 10) || 0) : '';
	}

	/**
	 * The icon sets rows and blocks with their own icon colours need, for the
	 * company shown: { key: { kind, colour, bg } } (Sam, 31 October 2026).
	 */
	function variantSpecs() {
		var specs = {};
		if (!icons || !icons.styles || !state) return specs;
		var p = primaryHex();
		var c = icons.styles.contact;
		var so = icons.styles.social;
		state.rows.forEach(function (r) {
			r.columns.forEach(function (col) {
				// A column's colours over its row's (Layout::$colours_now).
				var rc = $.extend({}, r.colours || {});
				Object.keys(col.colours || {}).forEach(function (k) { if (col.colours[k]) rc[k] = col.colours[k]; });
				col.blocks.forEach(function (b) {
					var own = b.colours || {};
					var pick = function (k, rk) {
						var v = own[k] || '';
						if (!v && rk) v = rc[rk] || '';
						return v ? hex6(hex(v)) : '';
					};
					if ((LISTS.indexOf(b.type) !== -1 || b.type === 'vcard') && c) {
						var ic = pick('icons', 'icons');
						if (ic) {
							var cbg = c.background ? hex6(c.bg || p) : '';
							var ck = variantKey('c', c.size, ic, cbg, c.radius);
							if (ck) specs[ck] = { kind: 'c', colour: ic, bg: cbg };
						}
					}
					if (b.type === 'social' && so) {
						var sc = pick('icons', 'icons');
						var sb = pick('bg');
						if (sc || sb) {
							var col2 = sc || hex6(so.colour);
							var bg2 = sb || (so.background ? hex6(so.bg || p) : '');
							var sk = variantKey('s', so.size, col2, bg2, so.radius);
							if (sk) specs[sk] = { kind: 's', colour: col2, bg: bg2 };
						}
					}
				});
			});
		});
		return specs;
	}

	/** The variant icon sets for the company shown: { key: { contact } or { social } }. */
	var variantCache = {};
	function buildVariants() {
		var specs = variantSpecs();
		var keys = Object.keys(specs);
		var out = {};
		if (!keys.length || !window.SBEmailSigIcons) return Promise.resolve(out);
		var c = icons.styles.contact;
		var so = icons.styles.social;
		var names = Object.keys(icons.contact || {});
		return Promise.all(keys.map(function (k) {
			var sp = specs[k];
			var ck = usedCompany + ':' + k;
			if (variantCache[ck]) {
				out[k] = variantCache[ck];
				return Promise.resolve();
			}
			if (sp.kind === 'c') {
				return Promise.all(names.map(function (n) {
					return window.SBEmailSigIcons.png(icons.contact[n], { size: c.size, colour: sp.colour, bg: sp.bg, radius: c.radius });
				})).then(function (res) {
					var set = {};
					names.forEach(function (n, i) { set[n] = res[i]; });
					out[k] = variantCache[ck] = { contact: set };
				});
			}
			return Promise.all((icons.social || []).map(function (svg) {
				return window.SBEmailSigIcons.png(svg, { size: so.size, colour: sp.colour, bg: sp.bg, radius: so.radius });
			})).then(function (res) {
				out[k] = variantCache[ck] = { social: res };
			});
		})).then(function () { return out; });
	}

	/** The preview's variant icons, drawn now, as they'll be saved. */
	function swapVariants() {
		var $fit = $('.sb-emailsig-editor__fit');
		if (!$fit.find('img[data-sbes-variant]').length) return;
		var id = asked;
		buildVariants().then(function (sets) {
			if (id !== asked) return;
			$fit.find('img[data-sbes-variant]').each(function () {
				var set = sets[$(this).attr('data-sbes-variant')];
				if (!set) return;
				var png = set.contact ? set.contact[$(this).attr('data-sbes-icon')] : (set.social || [])[parseInt($(this).attr('data-sbes-social'), 10)];
				if (png) this.src = png;
			});
		});
	}

	function swapIcons(set) {
		var $fit = $('.sb-emailsig-editor__fit');
		$fit.find('img[data-sbes-icon]:not([data-sbes-variant])').each(function () {
			var png = set.contact[$(this).attr('data-sbes-icon')];
			if (png) this.src = png;
		});
		$fit.find('img[data-sbes-social]:not([data-sbes-variant])').each(function () {
			var png = set.social[parseInt($(this).attr('data-sbes-social'), 10)];
			if (png) this.src = png;
		});
	}

	/* ------------------------------------------------------------------ *
	 * Container Settings and Footer Settings: their own Reset
	 * ------------------------------------------------------------------ */

	var PARTS = {
		box: ['padding', 'border', 'background', 'hide_signoff'],
		footer: ['line_below', 'line_below_style', 'line_colour', 'line_space', 'line_space_bottom', 'buttons_top', 'buttons_bottom', 'buttons_gap', 'text_padding', 'text_border', 'text_background', 'campaign_top', 'campaign_bottom', 'banners_top', 'banners_bottom', 'banners_gap', 'hide_buttons', 'hide_banners', 'hide_campaign', 'hide_text']
	};

	function partEdited(part, d) {
		return PARTS[part].some(function (k) {
			return d.hasOwnProperty(k) && JSON.stringify(state[k]) !== JSON.stringify(d[k]);
		});
	}

	function markParts() {
		var d = designDefaults();
		Object.keys(PARTS).forEach(function (part) {
			var edited = partEdited(part, d);
			$('[data-sb-emailsig-layout-part="' + part + '"]').toggleClass('is-edited', edited).find('.sb-emailsig-card-reset').prop('disabled', !edited);
		});
	}

	function resetPart(part) {
		var d = designDefaults();
		PARTS[part].forEach(function (k) {
			if (d.hasOwnProperty(k)) state[k] = $.isPlainObject(d[k]) ? $.extend(true, {}, d[k]) : d[k];
		});
		changed();
	}

	/* ------------------------------------------------------------------ *
	 * Undo and Redo: snapshots of the layout and the Layout Settings fields
	 * ------------------------------------------------------------------ */

	var steps = [];
	var at = -1;
	var popupOpen = false;
	var restoring = false;
	var recordTimer = null;

	function snapshot() {
		var own = {};
		OWN.forEach(function (k) {
			var el = settingsField(k);
			if (el) own[k] = el.value;
		});
		return JSON.stringify({ layout: field.value, own: own });
	}

	function historyButtons() {
		$('[data-history="undo"]').prop('disabled', at <= 0);
		$('[data-history="redo"]').prop('disabled', at >= steps.length - 1);
	}

	/** Keeps the page as it is now as a step, once changes settle (not while a popup is open). */
	function record() {
		if (restoring || popupOpen || at < 0) return;
		clearTimeout(recordTimer);
		recordTimer = setTimeout(function () {
			recordTimer = null;
			if (restoring || popupOpen) return;
			var now = snapshot();
			if (now === steps[at]) return;
			steps = steps.slice(0, at + 1);
			steps.push(now);
			if (steps.length > 100) steps.shift();
			at = steps.length - 1;
			historyButtons();
		}, 400);
	}

	function restore(snap) {
		var s = JSON.parse(snap);
		restoring = true;
		OWN.forEach(function (k) {
			var el = settingsField(k);
			if (el && s.own.hasOwnProperty(k) && el.value !== s.own[k]) {
				el.value = s.own[k];
				el.dispatchEvent(new Event('input', { bubbles: true }));
				el.dispatchEvent(new Event('change', { bubbles: true }));
			}
		});
		if (field.value !== s.layout) {
			field.value = s.layout;
			field.dispatchEvent(new Event('input', { bubbles: true }));
			field.dispatchEvent(new Event('change', { bubbles: true }));
		}
		restoring = false;
	}

	function step(by) {
		// Anything still settling counts first, so Undo takes back the latest change.
		if (recordTimer) {
			clearTimeout(recordTimer);
			var now = snapshot();
			if (now !== steps[at]) {
				steps = steps.slice(0, at + 1);
				steps.push(now);
				at = steps.length - 1;
			}
			recordTimer = null;
		}
		var to = at + by;
		if (to < 0 || to >= steps.length) return;
		at = to;
		restore(steps[at]);
		historyButtons();
	}

	/** Whether anything on this page differs from the design (Reset Template is active). */
	function resettable() {
		if (field.value !== (field.getAttribute('data-default') || '')) return true;
		return $('[data-sb-emailsig-layout-settings-card] [name][data-default]').toArray().some(function (el) {
			return (el.value || '') !== (el.getAttribute('data-default') || '');
		});
	}

	function markResetAll() {
		$('.sb-emailsig-layout__reset-all').prop('disabled', !resettable());
	}

	/**
	 * Reset Template's picks (Sam, 1 November 2026): layout (rows, columns,
	 * gaps and blocks as the design has them), settings (each row, column,
	 * gap and block's settings, in place), defaults (Layout Settings), box
	 * (Container Settings) and footer (Footer Settings). Edit styles is the
	 * form's own (resetOther).
	 */
	function resetPicked(pick) {
		var d = designDefaults();
		var dl = designLayout();
		var L = canon(state);
		var copy = function (v) { return JSON.parse(JSON.stringify(v)); };
		if (pick.layout) {
			L.rows = copy(d.rows || L.rows);
			L.row_gaps = copy(d.row_gaps || L.row_gaps);
			L.hold = copy(d.hold || L.hold);
		} else if (pick.settings) {
			L.rows.forEach(function (row, r) {
				var dr = dl.rows[r] || newRow();
				$.extend(row, rowPick(dr), { valign: VALIGNS[dr.valign] ? dr.valign : 'top' });
				row.columns.forEach(function (c, i) {
					$.extend(c, columnPick(((dr.columns || [])[i]) || newColumn()));
					c.blocks = c.blocks.map(function (b) { return $.extend(b, blockDefault(b)); });
				});
				row.gaps = row.gaps.map(function (g, i) { return canonGap(((dr.gaps || [])[i]) || newGap(), 'triple', 'default'); });
			});
			L.row_gaps = L.row_gaps.map(function (g, i) { return canonGap((dl.row_gaps || [])[i] || newRowGap(), 'triple', 'default'); });
			L.hold = L.hold.map(function (b) { return $.extend(b, blockDefault(b)); });
		}
		if (pick.defaults) DEFAULTS.forEach(function (k) { if (d.hasOwnProperty(k)) L[k] = d[k]; });
		['box', 'footer'].forEach(function (part) {
			if (pick[part]) PARTS[part].forEach(function (k) { if (d.hasOwnProperty(k)) L[k] = copy(d[k]); });
		});
		field.value = JSON.stringify(canon(L));
		field.dispatchEvent(new Event('input', { bubbles: true }));
		field.dispatchEvent(new Event('change', { bubbles: true }));
		if (pick.defaults) {
			$('[data-sb-emailsig-layout-settings-card] [name][data-default]').each(function () {
				$(this).val(this.getAttribute('data-default') || '');
				this.dispatchEvent(new Event('input', { bubbles: true }));
				this.dispatchEvent(new Event('change', { bubbles: true }));
			});
		}
		paintSettings();
		markSettings();
		markParts();
	}

	/* Columns (Sam, 31 October 2026): added, removed and moved one at a time,
	 * up to MAX_COLS in a row. Gap i sits between columns i and i + 1. */

	function newGap() {
		return { space: 'triple', space_after: 'triple', line: 'default', line_style: 'default', colour: '' }; // Triple (24px), with the Default Line Width, unless set (Sam, 31 October 2026).
	}

	/** A row's gaps to the count its columns need, padded or trimmed. */
	function fitGaps(row) {
		var n = gapCount(row.columns.length);
		while (row.gaps.length < n) row.gaps.push(newGap());
		if (row.gaps.length > n) row.gaps.length = n;
	}

	/** Column i taken out of a row that had n: the gap after it goes (before it, for the last). */
	function gapOut(row, i, n) {
		if (n > 1) row.gaps.splice(i < n - 1 ? i : i - 1, 1);
		fitGaps(row);
	}

	/** A column put in at i of a row that had n: a new gap after it (the last gap is reused at the end). */
	function gapIn(row, i, n) {
		if (n > 0 && i < n) row.gaps.splice(i, 0, newGap());
		fitGaps(row);
	}

	function addColumn(r) {
		var row = state.rows[r];
		if (!row || row.columns.length >= MAX_COLS) return;
		var n = row.columns.length;
		row.columns.push(newColumn());
		gapIn(row, n, n);
		changed();
	}

	/** Move a column from (row fr, place oi) to (row tr, place ni); the drag has already moved it on screen. */
	function moveColumn(fr, oi, tr, ni) {
		var from = state.rows[fr];
		var to = state.rows[tr];
		if (!from || !to || !from.columns[oi]) {
			render();
			return;
		}
		if (fr === tr) {
			if (oi !== ni) from.columns.splice(ni, 0, from.columns.splice(oi, 1)[0]);
			changed();
			return;
		}
		if (from.columns.length < 2 || to.columns.length >= MAX_COLS) {
			render();
			return;
		}
		var n = from.columns.length;
		var col = from.columns.splice(oi, 1)[0];
		gapOut(from, oi, n);
		var m = to.columns.length;
		ni = Math.max(0, Math.min(m, ni));
		to.columns.splice(ni, 0, col);
		gapIn(to, ni, m);
		changed();
	}

	/**
	 * Remove a column (a row keeps one). With blocks in it, asks where they
	 * go: another column, or Not in Use.
	 */
	function removeColumn(r, i) {
		var row = state.rows[r];
		if (!row || row.columns.length < 2 || !row.columns[i]) return;
		var inside = row.columns[i].blocks;

		var drop = function (to) {
			var at = /^(\d+):(\d+)$/.exec(to || '');
			if (to === 'hold') {
				state.hold = state.hold.concat(inside);
			} else if (at && state.rows[+at[1]] && state.rows[+at[1]].columns[+at[2]]) {
				var target = state.rows[+at[1]].columns[+at[2]];
				target.blocks = target.blocks.concat(inside);
			}
			var n = row.columns.length;
			row.columns.splice(i, 1);
			gapOut(row, i, n);
			changed();
		};

		// Empty: straight out (Undo brings it back).
		if (!inside.length || !window.SBTweaksConfirm) {
			drop(inside.length ? 'hold' : '');
			return;
		}

		var choices = {};
		var pick = '';
		state.rows.forEach(function (x, rr) {
			x.columns.forEach(function (c, ii) {
				if (rr === r && ii === i) return;
				var key = rr + ':' + ii;
				choices[key] = (state.rows.length > 1 ? 'Row ' + (rr + 1) + ', ' : '') + 'Column ' + (ii + 1);
				// The column beside it, before it if there is one.
				if (rr === r && (pick === '' || ii === i - 1)) pick = key;
			});
		});
		choices.hold = 'Not in Use';
		var $to = select(choices, pick || 'hold');
		var $body = $('<div class="sb-emailsig-layout__pop"></div>').append(
			$('<p></p>').text('Column ' + (i + 1) + ' has ' + inside.length + ' ' + (inside.length === 1 ? 'widget' : 'widgets') + ' in it. Where should ' + (inside.length === 1 ? 'it' : 'they') + ' go?'),
			fieldRow('Move to', $to)
		);
		window.SBTweaksConfirm.open({ title: 'Remove Column ' + (i + 1), body: $body, action: 'Remove column', tone: 'danger' }, function () { drop($to.val()); });
	}

	/** A new row at the bottom, one empty column, and the gap above it. */
	function addRow() {
		if (state.rows.length >= (cfg.maxRows || 12)) return;
		state.rows.push(newRow());
		state.row_gaps.push(newRowGap());
		changed();
	}

	/**
	 * Remove a row (Sam, 31 October 2026). With blocks in it, asks where they
	 * go: the end of another row's last column, or Not in Use.
	 */
	function removeRow(r) {
		var row = state.rows[r];
		if (!row || state.rows.length < 2) return;
		var inside = [];
		row.columns.forEach(function (c) { inside = inside.concat(c.blocks); });

		var drop = function (to) {
			if (to === 'hold') {
				state.hold = state.hold.concat(inside);
			} else if (to !== '' && state.rows[+to]) {
				var cols = state.rows[+to].columns;
				cols[cols.length - 1].blocks = cols[cols.length - 1].blocks.concat(inside);
			}
			state.rows.splice(r, 1);
			// The gap above it goes (below it, for the first row).
			state.row_gaps.splice(r > 0 ? r - 1 : 0, 1);
			changed();
		};

		if (!window.SBTweaksConfirm) {
			drop('hold');
			return;
		}

		if (!inside.length) {
			window.SBTweaksConfirm.open({ title: 'Remove Row ' + (r + 1), message: 'Take this empty row out of the layout?', action: 'Remove', tone: 'danger' }, function () { drop(''); });
			return;
		}

		var choices = {};
		state.rows.forEach(function (x, i) { if (i !== r) choices[i] = 'Row ' + (i + 1) + ' (end of its last column)'; });
		choices.hold = 'Not in Use';
		var $to = select(choices, 'hold');
		var $body = $('<div class="sb-emailsig-layout__pop"></div>').append(
			$('<p></p>').text('Row ' + (r + 1) + ' has ' + inside.length + ' ' + (inside.length === 1 ? 'widget' : 'widgets') + ' in it. Where should ' + (inside.length === 1 ? 'it' : 'they') + ' go?'),
			fieldRow('Move to', $to)
		);
		window.SBTweaksConfirm.open({ title: 'Remove Row ' + (r + 1), body: $body, action: 'Remove row', tone: 'danger' }, function () { drop($to.val()); });
	}

	/** How many blocks of a type the layout has, columns and Not in Use. */
	function count(type) {
		return allBlocks().filter(function (b) { return b.type === type; }).length;
	}

	/** The next free number for a block of a type (Info List 2, Divider 3). */
	function nextId(type) {
		var used = allBlocks().filter(function (b) { return b.type === type; }).map(function (b) { return b.id; });
		var id = 1;
		while (used.indexOf(id) !== -1) id++;
		return id;
	}

	/** A new block of a type, with its defaults. */
	function fresh(type) {
		return tag($.extend(true, {}, defs[type] || { type: type, align: 'left', space: 'default' }, { id: nextId(type) }));
	}

	/** A copy of $b, straight after it in the same list. */
	function duplicate(b) {
		if (count(b.type) >= (cfg.max || 6)) return;
		var copy = $.extend(true, {}, b);
		delete copy._uid;
		copy.id = nextId(b.type);
		tag(copy);
		var lists = allCols().map(function (c) { return c.blocks; }).concat([state.hold]);
		lists.some(function (l) {
			var at = l.indexOf(b);
			if (at === -1) return false;
			l.splice(at + 1, 0, copy);
			return true;
		});
		changed();
	}

	$(function () {
		field = document.querySelector('[data-sb-emailsig-layout-field]');
		root = document.querySelector('[data-sb-emailsig-layout]');
		if (!field || !root) return;

		load(field.value);
		if (!state) return;
		render();

		var $root = $(root);
		$root.on('click', '[data-col-add]', function (e) {
			e.preventDefault();
			addColumn(int($(this).attr('data-col-add'), 0));
		});
		$root.on('click', '[data-col-remove]', function (e) {
			e.preventDefault();
			var at = String($(this).attr('data-col-remove')).split(':');
			removeColumn(int(at[0], 0), int(at[1], 0));
		});
		$root.on('click', '.sb-emailsig-layout__add-row', function (e) {
			e.preventDefault();
			addRow();
		});
		$root.on('click', '[data-row-edit]', function () { rowPopup(int($(this).attr('data-row-edit'), 0)); });
		$root.on('click', '[data-row-remove]', function () { removeRow(int($(this).attr('data-row-remove'), 0)); });
		$root.on('click', '[data-rowgap]', function () {
			var i = int($(this).attr('data-rowgap'), 0);
			var open = function () {
				if (state.row_gaps[i]) gapPopup(state.row_gaps[i], 'Between rows ' + (i + 1) + ' and ' + (i + 2), canonGap(designLayout().row_gaps[i], 'triple', 'default'), 'triple', open, true);
			};
			open();
		});
		$root.on('click', '[data-act]', function (e) {
			e.preventDefault();
			var b = find($(this).closest('.sb-emailsig-layout__block').attr('data-uid'));
			if (!b) return;
			var act = $(this).attr('data-act');
			if (act === 'align') {
				b.align = ALIGNS[(ALIGNS.indexOf(b.align) + 1) % ALIGNS.length];
				changed();
			} else if (act === 'edit') {
				blockPopup(b);
			} else if (act === 'duplicate') {
				duplicate(b);
			} else if (act === 'unuse') {
				allCols().forEach(function (c) { c.blocks = c.blocks.filter(function (x) { return x !== b; }); });
				if (state.hold.indexOf(b) === -1) state.hold.push(b);
				changed();
			} else if (act === 'remove') {
				var drop = function () {
					allCols().forEach(function (c) { c.blocks = c.blocks.filter(function (x) { return x !== b; }); });
					state.hold = state.hold.filter(function (x) { return x !== b; });
					changed();
				};
				// Asks first (Sam, 29 October 2026); Undo brings it back too.
				if (!window.SBTweaksConfirm) {
					drop();
					return;
				}
				window.SBTweaksConfirm.open({
					title: 'Remove ' + name(b),
					message: 'Take ' + name(b) + ' out of this layout? Its settings go with it.',
					action: 'Remove',
					tone: 'danger'
				}, drop);
			}
		});
		$root.on('dblclick', '.sb-emailsig-layout__block', function (e) {
			if ($(e.target).closest('.sb-emailsig-layout__tool').length) return;
			var b = find($(this).attr('data-uid'));
			if (b) blockPopup(b);
		});
		$root.on('click', '[data-col]', function () {
			var at = String($(this).attr('data-col')).split(':');
			if (state.rows[int(at[0], 0)]) columnPopup(int(at[0], 0), int(at[1], 0));
		});
		$root.on('click', '[data-gap]', function () {
			var at = String($(this).attr('data-gap')).split(':');
			var r = int(at[0], 0);
			var i = int(at[1], 0);
			var open = function () {
				var row = state.rows[r];
				if (row && row.gaps[i]) gapPopup(row.gaps[i], 'Between columns ' + (i + 1) + ' and ' + (i + 2), canonGap(((designLayout().rows[r] || {}).gaps || [])[i], 'triple', 'default'), 'triple', open);
			};
			open();
		});

		// Every change: the preview follows and the card shows whether it's edited.
		field.addEventListener('change', function () {
			mark();
			markSettings();
			markParts();
			record();
			schedule();
			if (writing) return;
			load(field.value);
			render();
			drawDefaults();
		});

		// Layout Settings.
		var $settings = $('[data-sb-emailsig-layout-settings-card]');
		$settings.find('.sb-emailsig-colour').each(function () { enhanceColour(this); });
		drawDefaults();
		paintSettings();
		$settings.on('input change', '[name]', function (e) {
			paintSettings();
			markSettings();
			record();
			schedule();
			// The other cards' blank colours show the Lines colour.
			if (e.type === 'change') {
				cfg.lines = linesHex();
				render();
			}
		});

		// The cards' Reset: Layout Settings its own fields and defaults, the
		// layout card the design's layout (saved with Save changes).
		$(document).on('click', '.sb-emailsig-card-reset', function () {
			if ($(this).closest('[data-sb-emailsig-layout-settings-card]').length) {
				resetSettings();
				return;
			}
			var part = $(this).closest('[data-sb-emailsig-layout-part]').attr('data-sb-emailsig-layout-part');
			if (part && PARTS[part]) {
				resetPart(part);
				return;
			}
			field.value = field.getAttribute('data-default') || field.value;
			field.dispatchEvent(new Event('input', { bubbles: true }));
			field.dispatchEvent(new Event('change', { bubbles: true }));
		});

		shownCompany = personCompany();
		useCompany(shownCompany);
		startPrimary = primaryValue();
		if (typeof paintSettings === 'function') paintSettings();
		// What a blank Lines colour comes out as, in this company's Primary.
		cfg.lines = linesHex();
		render();

		// Save: with the icons redrawn first when the Primary has changed.
		var form = field.form;
		var iconsField = form ? form.querySelector('[data-sb-emailsig-icons]') : null;
		if (form && iconsField) {
			form.addEventListener('submit', function (e) {
				if (sending || !(iconsChanged() || (icons && window.SBEmailSigIcons && Object.keys(variantSpecs()).length) || (resetOther && icons && window.SBEmailSigIcons))) return;
				e.preventDefault();
				$('.sb-emailsig-editor__status').text('Saving...');
				var send = function (set) {
					if (set) {
						iconsField.value = JSON.stringify(set);
						iconsField.name = 'sb_emailsig_icons';
					}
					sending = true;
					HTMLFormElement.prototype.submit.call(form);
				};
				buildAll().then(send, function () { send(null); });
			});
		}
		$(document).on('change', '.sb-emailsig-editor__person', function () {
			clearTimeout(timer);
			shownCompany = personCompany();
			useCompany(shownCompany);
			closeMenu();
			if (typeof paintSettings === 'function') paintSettings();
			cfg.lines = linesHex();
			render();
			preview();
		});

		mark();
		markSettings();
		markParts();

		// Undo and Redo: the buttons, and Cmd or Ctrl Z (Shift for Redo, or
		// Ctrl Y) outside text boxes, which keep their own undo.
		steps = [snapshot()];
		at = 0;
		historyButtons();
		$(document).on('click', '.sb-emailsig-layout__reset-all', function (e) {
			e.preventDefault();
			if (!window.SBTweaksConfirm) return;
			// Pick what to reset, nothing ticked to start (Sam, 1 November 2026).
			var CHOICES = [
				['layout', 'Layout', 'Rows, columns and where each widget sits, with their settings.'],
				['settings', 'Row, column and widget settings', 'Each one\'s settings only. Extra rows, columns and widgets stay where they are.'],
				['defaults', 'Layout Settings', ''],
				['box', 'Container Settings', ''],
				['footer', 'Footer Settings', ''],
				['styles', 'Edit styles', 'Sizes, photo, text, Info List Styles, social icons and buttons. Saves straight away, with this page.']
			];
			var $body = $('<div class="sb-emailsig-layout__resets"></div>');
			var boxes = {};
			CHOICES.forEach(function (c) {
				var $l = $('<label class="sb-tweaks-dialog__check"><input type="checkbox"><span></span></label>');
				$l.find('span').append($('<strong></strong>').text(c[1]), c[2] ? $('<small></small>').text(c[2]) : '');
				boxes[c[0]] = $l.find('input');
				$body.append($l);
			});
			var $modal = window.SBTweaksConfirm.open({
				title: 'Reset Template',
				message: 'Put back how the design has them. Nothing is saved until you click Save changes, and Undo brings your changes back.',
				body: $body,
				action: 'Reset Template',
				tone: 'danger'
			}, function () {
				var pick = {};
				Object.keys(boxes).forEach(function (k) { pick[k] = boxes[k].prop('checked'); });
				resetPicked(pick);
				var flag = form ? form.querySelector('[data-sb-emailsig-reset-other]') : null;
				if (!pick.styles || !flag) return;
				resetOther = true;
				flag.name = 'sb_emailsig_reset_other';
				if (form.requestSubmit) {
					form.requestSubmit();
				} else {
					form.dispatchEvent(new Event('submit', { cancelable: true }));
				}
			});
			var $go = $modal ? $modal.find('.sb-tweaks-dialog__go') : $();
			var tick = function () {
				// Layout includes every row, column and block's settings.
				var whole = boxes.layout.prop('checked');
				if (whole) boxes.settings.prop('checked', true);
				boxes.settings.prop('disabled', whole);
				$go.prop('disabled', !Object.keys(boxes).some(function (k) { return boxes[k].prop('checked'); }));
			};
			$body.on('change', 'input', tick);
			tick();
		});
		$(document).on('click', '[data-history]', function (e) {
			e.preventDefault();
			step($(this).attr('data-history') === 'undo' ? -1 : 1);
		});
		$(document).on('keydown', function (e) {
			if (!(e.metaKey || e.ctrlKey) || e.altKey) return;
			var key = (e.key || '').toLowerCase();
			if (key !== 'z' && key !== 'y') return;
			if ($('.sb-tweaks-modal').length) return;
			var t = e.target;
			if (t && (t.isContentEditable || /^(input|textarea|select)$/i.test(t.tagName))) return;
			e.preventDefault();
			step(key === 'y' || e.shiftKey ? 1 : -1);
		});

		fit();
		$(window).on('resize', fit);
	});
})(jQuery);
