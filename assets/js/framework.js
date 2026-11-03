/**
 * SocialBUMP Tweaks framework: field behaviours shared by every module screen.
 *
 * Colour fields are their own component: assets/js/colour.js (SBTweaksColour).
 * Fields marked data-sb-tweaks-hide-when hide while their checkbox is ticked.
 * Select all and Select none set each tick with a real DOM change event,
 * because save-state.js listens with addEventListener and a jQuery trigger
 * never reaches it. A dismissible saved notice (Settings saved and the like)
 * goes by itself after a few seconds; errors and warnings stay.
 */
( function ( $ ) {
	$( function () {
		// Fields that hide while another checkbox is ticked.
		$( '[data-sb-tweaks-hide-when]' ).each( function () {
			var $field = $( this );
			var $box   = $( '#' + $field.data( 'sb-tweaks-hide-when' ) );

			if ( ! $box.length ) {
				return;
			}

			var sync = function () {
				$field.toggle( ! $box.prop( 'checked' ) );
			};

			$box.on( 'change', sync );
			sync();
		} );

		// A card lights up and dims as its switch is flipped. Not the small
		// switches in its list: those light their own row (Sam, 3 November 2026).
		$( document ).on( 'change', '.sb-tweaks-card .sb-tweaks-switch:not(.sb-tweaks-switch--small) input', function () {
			$( this ).closest( '.sb-tweaks-card' ).toggleClass( 'is-on', this.checked );
		} );

		$( document ).on( 'change', '.sb-tweaks-switch--small input', function () {
			var $row = $( this ).closest( 'li' );

			$row.toggleClass( 'is-on', this.checked ).toggleClass( 'is-off', ! this.checked );

			// A feature's parts, listed under it, dim with it.
			$row.nextUntil( 'li:not(.is-part)' ).toggleClass( 'is-muted', ! this.checked );
		} );

		// Select all and Select none on a long checklist.
		$( document ).on( 'click', '[data-sb-tweaks-check]', function () {
			var wanted = $( this ).data( 'sb-tweaks-check' ) === 'all';
			var $field = $( this ).closest( '.sb-tweaks-field' );

			$field.find( '.sb-tweaks-checklist input[type="checkbox"]' ).each( function () {
				if ( this.disabled || this.checked === wanted ) {
					return;
				}

				this.checked = wanted;
				this.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
		} );

		// Saved notices go by themselves, and the address loses its updated
		// flag so a reload doesn't show the notice again.
		var $saved = $( '#wpbody-content .notice-success.is-dismissible' );

		if ( $saved.length ) {
			window.setTimeout( function () {
				$saved.fadeOut( 400, function () {
					$( this ).remove();
				} );
			}, 4000 );
		}

		if ( window.history && window.history.replaceState && window.URL && /[?&]updated=/.test( window.location.search ) ) {
			var url = new URL( window.location.href );

			url.searchParams.delete( 'updated' );
			window.history.replaceState( null, '', url.toString() );
		}
	} );
} )( jQuery );

/**
 * SBTweaksFields: fields built in a script, with the same markup as
 * SB_Tweaks_Fields in PHP, so a popup's checklist looks and works like a
 * page's (Sam, 3 November 2026).
 *
 *   SBTweaksFields.checkTools( text )         Select all | Select none
 *   SBTweaksFields.checklist( items, opts )   a whole checklist field
 *
 * items: [ { value, label (text or a node), checked } ].
 * opts:  name (the boxes' name), label (read out for the group), list (one
 *        column, ruled off and scrolling: .sb-tweaks-checklist--list),
 *        selectAll (true unless false), count ([ one, many ], such as
 *        [ 'person', 'people' ]: "2 of 3 people" beside Select all and
 *        none, kept up to date), tools ('text': Select all and none as
 *        small text links, .sb-toggles--text, for a popup), className
 *        (added to the field).
 *
 * Select all and Select none are the [data-sb-tweaks-check] handler above,
 * so they need nothing more. Their look is core: ui.css's joined pair, in a
 * page or a popup.
 */
( function () {
	'use strict';

	function el( tag, className, text ) {
		var node = document.createElement( tag );

		if ( className ) {
			node.className = className;
		}

		if ( text !== undefined ) {
			node.textContent = text;
		}

		return node;
	}

	function checkTools( text ) {
		var tools = el( 'span', 'sb-tweaks-checklist__tools sb-toggles' + ( text ? ' sb-toggles--text' : '' ) );

		[ [ 'all', 'Select all' ], [ 'none', 'Select none' ] ].forEach( function ( pick, i ) {
			if ( i ) {
				var bar = el( 'span', '', '|' );

				bar.setAttribute( 'aria-hidden', 'true' );
				tools.appendChild( bar );
			}

			var button = el( 'button', 'button-link sb-toggle', pick[ 1 ] );

			button.type = 'button';
			button.setAttribute( 'data-sb-always-on', '' );
			button.setAttribute( 'data-sb-tweaks-check', pick[ 0 ] );
			tools.appendChild( button );
		} );

		return tools;
	}

	function checklist( items, opts ) {
		opts = opts || {};

		var field = el( 'div', 'sb-tweaks-field' + ( opts.className ? ' ' + opts.className : '' ) );
		var list  = el( 'div', 'sb-tweaks-checklist' + ( opts.list ? ' sb-tweaks-checklist--list' : '' ) );
		var count = null;

		list.setAttribute( 'role', 'group' );

		if ( opts.label ) {
			list.setAttribute( 'aria-label', opts.label );
		}

		( items || [] ).forEach( function ( item ) {
			var label = el( 'label' );
			var box   = el( 'input' );
			var text  = item.label;

			box.type    = 'checkbox';
			box.value   = String( item.value );
			box.checked = !! item.checked;

			if ( opts.name ) {
				box.name = opts.name;
			}

			label.appendChild( box );
			label.appendChild( text && text.nodeType ? text : el( 'span', '', String( text === undefined ? '' : text ) ) );
			list.appendChild( label );
		} );

		if ( opts.selectAll !== false || opts.count ) {
			var head = el( 'div', 'sb-tweaks-checklist__head' );

			if ( opts.selectAll !== false ) {
				head.appendChild( checkTools( opts.tools === 'text' ) );
			}

			if ( opts.count ) {
				count = el( 'span', 'sb-tweaks-checklist__count' );
				count.setAttribute( 'aria-live', 'polite' );
				head.appendChild( count );
			}

			field.appendChild( head );
		}

		field.appendChild( list );

		if ( count ) {
			var total = list.querySelectorAll( 'input[type="checkbox"]' ).length;
			var show  = function () {
				var on = list.querySelectorAll( 'input[type="checkbox"]:checked' ).length;

				count.textContent = on + ' of ' + total + ' ' + ( total === 1 ? opts.count[ 0 ] : opts.count[ 1 ] );
			};

			list.addEventListener( 'change', show );
			show();
		}

		return field;
	}

	window.SBTweaksFields = { checkTools: checkTools, checklist: checklist };
} )();
