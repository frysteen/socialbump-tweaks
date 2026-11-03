/**
 * The colour field: one component for every colour picker in SocialBUMP
 * Tweaks (Sam, 3 November 2026). A swatch to pick with, a box for the value
 * (a hex, rgb() or hsl(), or a variable such as var(--primary)) and the
 * Theme colours button, whose menu lists the site's colours.
 *
 * Markup (SB_Tweaks_Fields::colour() draws it in PHP, field() here):
 *
 *   span.sb-tweaks-colour[data-sb-colour]
 *     input[type=color].sb-tweaks-colour__swatch
 *     input[type=text].sb-tweaks-colour__value
 *     button.sb-tweaks-colour__theme
 *
 * Every [data-sb-colour] on the page is attached when it loads, with the
 * site's colours (SB_Tweaks_Colours::print_data()). A page that needs more
 * calls attach() with options, before or after, and they merge:
 *
 *   palette  the menu: [ { name, value, hex, group } ], or a function
 *            returning one (a page that switches company switches lists).
 *   hex      function ( value, field ) returning the hex a value comes out
 *            as, or ''. Replaces the default lookup (the page's colours,
 *            then anything the browser can work out) for a page that only
 *            accepts some kinds of colour.
 *   fallback the hex a blank field shows, dashed: a string, or a function
 *            ( field ) returning one.
 *
 * A field shows .is-inherited while it has no colour of its own and
 * .is-unknown while it has a value nothing can work out. Picking with the
 * swatch or the menu writes the value with real input and change events, so
 * the page and save-state.js hear it. After changing something a fallback
 * depends on, call paint( root ).
 */
( function () {
	'use strict';

	var pageData = null;
	var menu     = null;
	var menuFor  = null;

	/* ---------- The site's colours ---------- */

	/** { palette, colours } from SB_Tweaks_Colours::print_data(), or {}. */
	function data() {
		if ( pageData === null ) {
			var node = document.getElementById( 'sb-tweaks-palette' );

			try {
				pageData = node ? JSON.parse( node.textContent ) : {};
			} catch ( e ) {
				pageData = {};
			}
		}

		return pageData;
	}

	function has( map, key ) {
		return !! map && Object.prototype.hasOwnProperty.call( map, key );
	}

	/**
	 * A value as #rrggbb from what is known, nothing guessed: a hex, then
	 * sources.colours ( { 'var(--name)': hex } ), then a brand colour
	 * (var(--sb-brand-primary) from sources.brand, { primary: hex }), then
	 * sources.palette. '' when none of them has it.
	 */
	function lookup( value, sources ) {
		var src = sources || {};
		var v   = String( value || '' ).trim().toLowerCase();
		var b;
		var i;

		if ( /^--[a-z0-9_-]+$/.test( v ) ) {
			v = 'var(' + v + ')';
		}

		if ( /^#[0-9a-f]{6}$/.test( v ) ) {
			return v;
		}

		if ( /^#[0-9a-f]{3}$/.test( v ) ) {
			return '#' + v[1] + v[1] + v[2] + v[2] + v[3] + v[3];
		}

		if ( has( src.colours, v ) ) {
			return src.colours[ v ];
		}

		b = /^var\(--sb-brand-([a-z-]+)\)$/.exec( v );

		if ( b && src.brand ) {
			return src.brand[ b[1].replace( /-/g, '_' ) ] || '';
		}

		for ( i = 0; i < ( src.palette || [] ).length; i++ ) {
			if ( src.palette[ i ].value === v ) {
				return src.palette[ i ].hex;
			}
		}

		return '';
	}

	/**
	 * Whatever the browser makes of a colour on this page (rgb(), hsl(), a
	 * name, a variable this page defines) as #rrggbb, or ''. A variable the
	 * page doesn't define inherits the probe's own colour, so it's caught.
	 */
	function computed( value ) {
		var holder = document.createElement( 'span' );
		var probe  = document.createElement( 'span' );
		var m;

		holder.style.color   = 'rgb(1, 2, 3)';
		holder.style.display = 'none';
		holder.appendChild( probe );
		document.body.appendChild( holder );
		probe.style.color = value;
		m = probe.style.color !== '' ? window.getComputedStyle( probe ).color.match( /rgba?\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)/ ) : null;
		holder.parentNode.removeChild( holder );

		if ( ! m || ( m[1] === '1' && m[2] === '2' && m[3] === '3' ) ) {
			return '';
		}

		return '#' + [ m[1], m[2], m[3] ].map( function ( n ) {
			return ( '0' + parseInt( n, 10 ).toString( 16 ) ).slice( -2 );
		} ).join( '' );
	}

	/** The default lookup: what the site's colours say, else what the browser can work out. */
	function hex( value, list ) {
		var d = data();

		if ( String( value || '' ).trim() === '' ) {
			return '';
		}

		return lookup( value, { colours: d.colours, palette: list || d.palette } ) || computed( value );
	}

	/** Set a box's value with real events: save-state.js listens with addEventListener. */
	function set( input, value ) {
		input.value = value;
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	/* ---------- The menu ---------- */

	function close() {
		if ( menu ) {
			menu.parentNode.removeChild( menu );
			menu = null;
		}

		if ( menuFor ) {
			menuFor.button.setAttribute( 'aria-expanded', 'false' );
			menuFor = null;
		}
	}

	function open( f ) {
		var list = f.list();
		var rect = f.button.getBoundingClientRect();
		var first;

		close();

		menu = document.createElement( 'div' );
		menu.className = 'sb-tweaks-palette';
		menu.setAttribute( 'role', 'listbox' );

		list.forEach( function ( c, i ) {
			var item;
			var dot;
			var name;
			var code;
			var head;

			// A heading where the colours' source changes (Brand colours, Bricks: Theme Colours, ...).
			if ( c.group && ( i === 0 || list[ i - 1 ].group !== c.group ) ) {
				head = document.createElement( 'div' );
				head.className   = 'sb-tweaks-palette__head';
				head.textContent = c.group;
				menu.appendChild( head );
			}

			item = document.createElement( 'button' );
			dot  = document.createElement( 'i' );
			name = document.createElement( 'span' );
			code = document.createElement( 'code' );

			item.type = 'button';
			item.setAttribute( 'role', 'option' );
			dot.style.background = c.hex;
			name.textContent     = c.name;
			code.textContent     = c.hex;
			item.appendChild( dot );
			item.appendChild( name );
			item.appendChild( code );

			item.addEventListener( 'click', function () {
				set( f.input, c.value );
				close();
				// preventScroll: Safari otherwise scrolls the page to put the box
				// in the middle of the screen, even when it's in plain view.
				f.input.focus( { preventScroll: true } );
			} );

			menu.appendChild( item );
		} );

		document.body.appendChild( menu );
		menu.style.top  = ( rect.bottom + window.scrollY + 4 ) + 'px';
		menu.style.left = Math.max( 8, Math.min( rect.left + window.scrollX, window.scrollX + document.documentElement.clientWidth - 268 ) ) + 'px';

		menuFor = f;
		f.button.setAttribute( 'aria-expanded', 'true' );

		first = menu.querySelector( 'button' );

		if ( first ) {
			first.focus( { preventScroll: true } );
		}
	}

	document.addEventListener( 'mousedown', function ( event ) {
		if ( menu && ! ( event.target.closest && event.target.closest( '.sb-tweaks-palette, .sb-tweaks-colour__theme' ) ) ) {
			close();
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		var back;

		if ( event.key === 'Escape' && menu ) {
			back = menuFor;
			close();

			if ( back ) {
				back.button.focus( { preventScroll: true } );
			}
		}
	} );

	window.addEventListener( 'resize', close );

	// The page scrolling moves the field away, so the menu goes. Scrolling the
	// menu's own list doesn't.
	window.addEventListener( 'scroll', function ( event ) {
		if ( menu && ! ( event.target instanceof Node && menu.contains( event.target ) ) ) {
			close();
		}
	}, true );

	/* ---------- A field ---------- */

	function merge( into, from ) {
		Object.keys( from || {} ).forEach( function ( key ) {
			into[ key ] = from[ key ];
		} );

		return into;
	}

	/** Attach to a field's wrapper, or update its options and repaint when it is attached already. */
	function attach( wrap, opts ) {
		var f;
		var input;
		var swatch;

		if ( ! wrap ) {
			return null;
		}

		if ( wrap._sbColour ) {
			if ( opts ) {
				merge( wrap._sbColour.opts, opts );
				wrap._sbColour.refresh();
			}

			return wrap._sbColour;
		}

		input  = wrap.querySelector( '.sb-tweaks-colour__value' );
		swatch = wrap.querySelector( '.sb-tweaks-colour__swatch' );

		if ( ! input || ! swatch ) {
			return null;
		}

		f = {
			wrap: wrap,
			input: input,
			swatch: swatch,
			button: wrap.querySelector( '.sb-tweaks-colour__theme' ),
			opts: merge( {}, opts )
		};

		/** The menu's colours: the field's own list, else the site's. */
		f.list = function () {
			var list = typeof f.opts.palette === 'function' ? f.opts.palette( f ) : f.opts.palette;

			return list || data().palette || [];
		};

		/** What a value comes out as, the field's way. */
		f.hex = function ( value ) {
			return typeof f.opts.hex === 'function' ? ( f.opts.hex( value, f ) || '' ) : hex( value, f.list() );
		};

		/** The swatch shows the box's colour, or the fallback dashed. */
		f.paint = function () {
			var value = f.input.value.trim();
			var own   = value === '' ? '' : f.hex( value );
			var fall  = typeof f.opts.fallback === 'function' ? f.opts.fallback( f ) : f.opts.fallback;
			var shown = own || fall || '#ffffff';

			f.wrap.classList.toggle( 'is-inherited', ! own );
			f.wrap.classList.toggle( 'is-unknown', value !== '' && ! own );

			if ( f.swatch.value !== shown ) {
				f.swatch.value = shown;
			}
		};

		/** After the options change: the button follows the menu, and the swatch the value. */
		f.refresh = function () {
			if ( f.button ) {
				f.button.hidden = ! f.list().length;
			}

			f.paint();
		};

		swatch.addEventListener( 'input', function () {
			if ( input.value !== swatch.value ) {
				set( input, swatch.value );
			}
		} );

		// jQuery when it's there, so a value set with .trigger( 'input' ) repaints too.
		if ( window.jQuery ) {
			window.jQuery( input ).on( 'input', f.paint );
		} else {
			input.addEventListener( 'input', f.paint );
		}

		if ( f.button ) {
			f.button.setAttribute( 'aria-expanded', 'false' );
			f.button.addEventListener( 'click', function ( event ) {
				event.preventDefault();

				if ( menuFor === f ) {
					close();
				} else {
					open( f );
				}
			} );
		}

		wrap._sbColour = f;
		f.refresh();

		return f;
	}

	/** The button, ready to go in a field. */
	function themeButton() {
		var button = document.createElement( 'button' );
		var icon   = document.createElement( 'span' );

		button.type      = 'button';
		button.className = 'sb-tweaks-colour__theme';
		button.hidden    = true;
		button.setAttribute( 'aria-label', 'Theme colours' );
		button.setAttribute( 'data-sb-tip', 'Theme colours' );
		icon.className = 'dashicons dashicons-art';
		icon.setAttribute( 'aria-hidden', 'true' );
		button.appendChild( icon );

		return button;
	}

	/**
	 * A new field, attached, for a page that builds its fields in script:
	 * args { label, value, placeholder, name, id, className }. Returns the
	 * wrapper; field.input is wrapper._sbColour.input.
	 */
	function field( args, opts ) {
		var a      = args || {};
		var wrap   = document.createElement( 'span' );
		var swatch = document.createElement( 'input' );
		var input  = document.createElement( 'input' );

		wrap.className = 'sb-tweaks-colour' + ( a.className ? ' ' + a.className : '' );
		wrap.setAttribute( 'data-sb-colour', '' );

		swatch.type      = 'color';
		swatch.className = 'sb-tweaks-colour__swatch';
		swatch.value     = '#ffffff';
		swatch.setAttribute( 'aria-label', a.label || 'Pick a colour' );

		input.type      = 'text';
		input.className = 'sb-tweaks-colour__value code';
		input.value     = a.value || '';
		input.setAttribute( 'spellcheck', 'false' );
		input.setAttribute( 'autocomplete', 'off' );

		if ( a.placeholder ) {
			input.setAttribute( 'placeholder', a.placeholder );
		}

		if ( a.name ) {
			input.name = a.name;
		}

		if ( a.id ) {
			input.id = a.id;
		}

		wrap.appendChild( swatch );
		wrap.appendChild( input );
		wrap.appendChild( themeButton() );
		attach( wrap, opts );

		return wrap;
	}

	/**
	 * Make a text box someone else drew (an ACF field, say) into a colour
	 * field: the swatch before it and the button after, in a wrapper with
	 * className. Returns the field.
	 */
	function enhance( input, opts, className ) {
		var wrap;
		var swatch;

		if ( ! input ) {
			return null;
		}

		if ( input.parentNode && input.parentNode._sbColour ) {
			return attach( input.parentNode, opts );
		}

		wrap   = document.createElement( 'span' );
		swatch = document.createElement( 'input' );

		wrap.className = 'sb-tweaks-colour' + ( className ? ' ' + className : '' );
		wrap.setAttribute( 'data-sb-colour', '' );
		swatch.type      = 'color';
		swatch.className = 'sb-tweaks-colour__swatch';
		swatch.value     = '#ffffff';
		swatch.setAttribute( 'aria-label', 'Pick a colour' );

		input.classList.add( 'sb-tweaks-colour__value', 'code' );
		input.setAttribute( 'spellcheck', 'false' );
		input.setAttribute( 'autocomplete', 'off' );
		input.parentNode.insertBefore( wrap, input );
		wrap.appendChild( swatch );
		wrap.appendChild( input );
		wrap.appendChild( themeButton() );

		return attach( wrap, opts );
	}

	/** Repaint every attached field inside root (the whole page by default). */
	function paint( root ) {
		Array.prototype.forEach.call( ( root || document ).querySelectorAll( '.sb-tweaks-colour' ), function ( wrap ) {
			if ( wrap._sbColour ) {
				wrap._sbColour.paint();
			}
		} );
	}

	function start() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-sb-colour]' ), function ( wrap ) {
			attach( wrap );
		} );
	}

	window.SBTweaksColour = {
		attach: attach,
		field: field,
		enhance: enhance,
		paint: paint,
		lookup: lookup,
		hex: hex,
		set: set,
		close: close,
		data: data
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
