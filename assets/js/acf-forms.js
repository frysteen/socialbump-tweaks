/**
 * ACF forms on SocialBUMP Tweaks pages (Sam, 2 November 2026).
 *
 * - ACF's own "leave this page?" warning is switched off (acf.unload): it
 *   comes on with the first change and never notices a change put back.
 *   save-state.js warns instead, by comparing the form with how it started.
 * - An editor (TinyMCE) tidies its text when it starts, so it is saved into
 *   its box then and the form told (sb:baseline), so its starting point is
 *   the tidied text and putting text back counts as no change.
 * - A save ACF stops (a required field left empty, a value it won't take)
 *   gets the framework's popup naming each field with its tab and row, and
 *   Show me goes to it. A tab holding one gets a red dot until it's fixed.
 *   The form is told (sb:save-failed), so the reminder and the leave
 *   warning come back. ACF's Validation failed banner becomes a toast.
 * - The browser's own checks are off on these forms (novalidate). With them
 *   on, an empty required field on a tab you can't see stopped the save
 *   with no message at all (the browser can't point at a hidden field), and
 *   ACF never got to check. ACF checks the same things (required, a real
 *   web address) when you save.
 */
( function ( $ ) {
	'use strict';

	var DOT  = 'sb-acf-tab-error';
	// Kept on a field until it has a value: ACF's own error goes as soon as
	// the field is clicked into, before anything is fixed.
	var MARK = 'sb-acf-problem';

	/** A field's label: its own, or its column heading in a table layout repeater. */
	function labelOf( $field ) {
		var text = $field.children( '.acf-label' ).find( 'label' ).first().text();

		if ( ! text ) {
			var key = $field.attr( 'data-key' );
			text    = key ? $field.closest( 'table' ).children( 'thead' ).find( 'th[data-key="' + key + '"]' ).first().text() : '';
		}

		return $.trim( String( text ).replace( /\*\s*$/, '' ) );
	}

	/** The tab a field sits under: its button's list item, or an empty set. */
	function tabOf( $field ) {
		var $li = $();

		$field.parents( '.acf-field' ).addBack().each( function () {
			var $tab = $( this ).prevAll( '.acf-field-tab' ).first();

			if ( ! $li.length && $tab.length ) {
				$li = $field.closest( 'form' ).find( '.acf-tab-button[data-key="' + $tab.attr( 'data-key' ) + '"]' ).first().closest( 'li' );
			}
		} );

		return $li;
	}

	/** Where a field is: "Additional Buttons, row 2, Link". */
	function placeOf( $field ) {
		var chain = $field.parents( '.acf-field' ).addBack().not( '.acf-field-tab' ).get();
		var parts = [];

		chain.forEach( function ( el, i ) {
			var label = labelOf( $( el ) );

			if ( label ) {
				parts.push( label );
			}

			// A row of this field (a repeater) that holds the next one in the chain.
			var next = chain[ i + 1 ];
			var $row = next ? $( next ).closest( '.acf-row' ) : $();

			if ( $row.length && $.contains( el, $row[0] ) ) {
				parts.push( 'row ' + ( $row.parent().children( '.acf-row' ).not( '.acf-clone' ).index( $row ) + 1 ) );
			}
		} );

		return parts.join( ', ' );
	}

	/** The fields showing an error, innermost only. */
	function problems( $form ) {
		return $form.find( '.acf-field.acf-error' ).filter( function () {
			return ! $( this ).find( '.acf-field.acf-error' ).length;
		} );
	}

	/** True once a field has something in it. */
	function filled( $field ) {
		return $field.find( 'input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select, textarea' ).filter( function () {
			return $.trim( $( this ).val() || '' ) !== '';
		} ).length > 0;
	}

	/** A red dot on each tab that still holds a field to fix. */
	function dots( $form ) {
		$form.find( '.acf-tab-group li.' + DOT ).removeClass( DOT );

		$form.find( '.acf-field.' + MARK ).each( function () {
			tabOf( $( this ) ).addClass( DOT );
		} );
	}

	/**
	 * Open a field's tab and bring it into view. Not focused: ACF takes its
	 * message away the moment the field is focused.
	 */
	function show( $field ) {
		var $li = tabOf( $field );

		if ( $li.length && ! $li.hasClass( 'active' ) ) {
			$li.find( '.acf-tab-button' ).trigger( 'click' );
		}

		setTimeout( function () {
			$field[0].scrollIntoView( { block: 'center', behavior: 'smooth' } );
		}, 60 );
	}

	function failed( $form, validator ) {
		var form = $form && $form[0];

		if ( ! form ) {
			return;
		}

		form.dispatchEvent( new CustomEvent( 'sb:save-failed' ) );

		// ACF shows its errors just after telling us.
		setTimeout( function () {
			var $found = problems( $form );

			$form.find( '.acf-field.' + MARK ).removeClass( MARK );
			$found.addClass( MARK );
			dots( $form );

			// ACF's Validation failed banner across the top becomes a toast that
			// goes by itself (Sam, 2 November 2026).
			var $note = $form.children( '.acf-notice' );
			var words = $.trim( $note.find( 'p' ).first().text() || $note.text() );

			if ( $note.length && window.SBTweaksUI && window.SBTweaksUI.toast ) {
				$note.remove();

				if ( validator && validator.set ) {
					validator.set( 'notice', false );
				}

				window.SBTweaksUI.toast( words || 'Not saved: some fields need fixing.', 'danger' );
			}

			if ( ! $found.length || ! window.SBTweaksConfirm ) {
				return;
			}

			var $list = $( '<ul class="sb-acf-problems"></ul>' );

			$found.each( function () {
				var $field = $( this );
				var $tab   = tabOf( $field );
				// No tabs (a person's page): the card it sits in.
				var where  = $tab.length ? $.trim( $tab.text() ) : $.trim( $field.closest( '.sb-tweaks-card' ).find( '.sb-tweaks-card__head h3' ).first().text() );
				var why    = $.trim( $field.find( '.acf-notice' ).first().text() );
				var $item  = $( '<li><button type="button" class="button-link"></button></li>' );

				$item.children( 'button' )
					.append( where ? $( '<strong></strong>' ).text( where + ': ' ) : '', document.createTextNode( placeOf( $field ) ) )
					.on( 'click', function () {
						window.SBTweaksConfirm.close();
						show( $field );
					} );

				if ( why ) {
					$item.append( $( '<span></span>' ).text( why ) );
				}

				$list.append( $item );
			} );

			window.SBTweaksConfirm.open(
				{
					title: 'Can’t save yet',
					message: $found.length === 1 ? 'This field needs fixing first.' : 'These ' + $found.length + ' fields need fixing first.',
					body: $list,
					cancel: 'Close',
					action: 'Show me'
				},
				function () {
					show( $found.first() );
				}
			);
		}, 0 );
	}

	function start() {
		var acf = window.acf;

		if ( ! acf || ! acf.addAction ) {
			return;
		}

		var quiet = function () {
			if ( acf.unload && acf.unload.disable ) {
				acf.unload.disable();
			}
		};

		quiet();
		acf.addAction( 'ready', quiet );
		acf.addAction( 'load', quiet );

		acf.addAction( 'wysiwyg_tinymce_init', function ( ed ) {
			setTimeout( function () {
				var box = ed && ed.getElement ? ed.getElement() : null;

				if ( ! box ) {
					return;
				}

				ed.save();

				if ( box.form ) {
					box.form.dispatchEvent( new CustomEvent( 'sb:baseline' ) );
				}
			}, 0 );
		} );

		acf.addAction( 'validation_failure', failed );

		// ACF checks instead of the browser (see above).
		var noBrowserChecks = function () {
			$( 'form' ).has( '.acf-field' ).attr( 'novalidate', 'novalidate' );
		};

		$( noBrowserChecks );
		acf.addAction( 'ready', noBrowserChecks );

		// A field filled in loses its outline, and its tab's dot when it was the last.
		$( document ).on( 'input change', 'form .acf-field.' + MARK, function ( e ) {
			var $field = $( this );
			var $form  = $field.closest( 'form' );

			// The innermost marked field only, not a repeater around it.
			if ( $( e.target ).closest( '.acf-field.' + MARK )[0] !== this ) {
				return;
			}

			setTimeout( function () {
				if ( filled( $field ) ) {
					$field.removeClass( MARK );
					dots( $form );
				}
			}, 0 );
		} );

		// Once ACF has finished setting the page up, that is the starting point
		// (editors that started before this script are tidied into their boxes too).
		$( window ).on( 'load', function () {
			setTimeout( function () {
				var eds = window.tinymce && window.tinymce.get ? window.tinymce.get() : [];

				Array.prototype.forEach.call( eds || [], function ( ed ) {
					var box = ed.getElement ? ed.getElement() : null;

					if ( box && box.form && box.form.hasAttribute( 'data-sb-dirty' ) ) {
						ed.save();
					}
				} );

				$( 'form[data-sb-dirty]' ).each( function () {
					this.dispatchEvent( new CustomEvent( 'sb:baseline' ) );
				} );
			}, 50 );
		} );
	}

	// ACF's ready runs editors' set up, so listen before it when ACF is here already.
	if ( window.acf ) {
		start();
	} else {
		$( start );
	}
}( jQuery ) );
