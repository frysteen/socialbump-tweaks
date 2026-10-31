/**
 * The confirm popup, for anything that asks before it acts.
 *
 * One look for every SocialBUMP Tweaks page and module, the same box as the
 * Installs page and SEO for AI: white over a dark overlay, the title top left,
 * an X in the top right, Cancel on the left and the action on the right.
 * Escape, the X, Cancel and a click outside all close it. Nothing here uses
 * the browser's own confirm().
 *
 * In markup, a button or link asks first when it carries the question:
 *
 *   <button data-sb-tweaks-confirm="Publish version %s to every site?"
 *           data-sb-tweaks-confirm-field="sb_tweaks_version"
 *           data-sb-tweaks-confirm-title="Publish release"
 *           data-sb-tweaks-confirm-action="Publish">
 *
 * Add data-sb-tweaks-confirm-tone="danger" for a delete or reset. A button
 * whose class has --danger in it (sb-tweaks-button--danger) gets it anyway.
 *
 * %s stands for the value of the form field named in -field. On the action the
 * element is clicked again with a one shot pass, so a form submits, and any
 * submit handler on it runs, exactly as if nothing had asked.
 *
 * From a script:
 *
 *   if ( SBTweaksConfirm.confirmed( this ) ) { ...carry on... }
 *   SBTweaksConfirm.ask( this, { title, message, action, check, before } );
 *   SBTweaksConfirm.tell( { title, message } );     // OK only
 *   SBTweaksConfirm.open( options, onAction );      // anything else, a summary say
 *
 * options: title, message, tone ('danger' for anything that deletes or resets:
 * a warning icon by the title and a solid red action), body (more content, such as a run's summary), check
 * (a tick box: its words, or { label, list } with each line's first word in
 * bold), cancel and action (the two buttons' words), alert (no Cancel), and
 * before( checked ) run just ahead of the second click. onAction( checked ) is
 * told whether the tick box was ticked.
 *
 * For fields to fill in, pass them in body, each in a
 * <label class="sb-tweaks-dialog__field"><span>Label</span><input></label>.
 * The popup carries wp-core-ui itself, so it looks the same on a front-end
 * page that loads this stylesheet and WordPress's buttons.css.
 */
( function ( $ ) {
	'use strict';

	var $current = null;

	function close() {
		if ( $current ) {
			$current.remove();
			$current = null;
		}

		$( document ).off( 'keydown.sbTweaksConfirm' );
	}

	function open( options, onAction ) {
		close();

		options = options || {};

		var $modal  = $( '<div class="sb-tweaks-modal wp-core-ui"><div class="sb-tweaks-dialog" role="dialog" aria-modal="true"></div></div>' );
		var $dialog = $modal.children();
		var $row    = $( '<p class="sb-tweaks-dialog__buttons"></p>' );
		var danger  = options.tone === 'danger';
		var $go     = $( '<button type="button" class="button sb-tweaks-dialog__go"></button>' ).addClass( danger ? 'sb-tweaks-button--danger-solid' : 'button-primary' ).text( options.action || 'OK' );
		var $box    = null;

		$dialog.append( '<button type="button" class="sb-tweaks-dialog__x" aria-label="Close">&times;</button>' );

		// A delete or reset: the warning icon beside the title, as in the UI Kit.
		if ( danger ) {
			$dialog.addClass( 'sb-tweaks-dialog--danger' ).append(
				$( '<div class="sb-tweaks-dialog__head"></div>' ).append(
					'<span class="sb-tweaks-dialog__icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg></span>',
					$( '<h2></h2>' ).text( options.title || '' )
				)
			);
		} else {
			$dialog.append( $( '<h2></h2>' ).text( options.title || '' ) );
		}

		if ( options.message ) {
			$dialog.append( $( '<p class="sb-tweaks-dialog__message"></p>' ).text( options.message ) );
		}

		if ( options.body ) {
			$dialog.append( options.body );
		}

		if ( options.check ) {
			var check  = 'string' === typeof options.check ? { label: options.check } : options.check;
			var $label = $( '<label class="sb-tweaks-dialog__check"><input type="checkbox"><span></span></label>' );
			var $words = $label.find( 'span' );

			$words.text( check.label || '' );

			if ( check.list && check.list.length ) {
				var $list = $( '<ul class="sb-tweaks-dialog__list"></ul>' );

				check.list.forEach( function ( line ) {
					var text  = String( line );
					var at    = text.indexOf( ' ' );
					var $item = $( '<li></li>' );

					if ( at > 0 ) {
						$item.append( $( '<strong></strong>' ).text( text.slice( 0, at ) ), document.createTextNode( text.slice( at ) ) );
					} else {
						$item.text( text );
					}

					$list.append( $item );
				} );

				$words.append( $list );
			}

			$box = $label.find( 'input' );
			$dialog.append( $label );
		}

		if ( ! options.alert ) {
			$row.append( $( '<button type="button" class="button sb-tweaks-dialog__cancel"></button>' ).text( options.cancel || 'Cancel' ) );
		}

		$row.append( $go );
		$dialog.append( $row );

		// The dark area, the X, Cancel and Escape all close it.
		$modal.on( 'click', function ( e ) {
			if ( e.target === this ) {
				close();
			}
		} );

		$dialog.on( 'click', '.sb-tweaks-dialog__x, .sb-tweaks-dialog__cancel', close );

		$go.on( 'click', function () {
			var checked = $box ? $box.prop( 'checked' ) : false;

			close();

			if ( onAction ) {
				onAction( checked );
			}
		} );

		$( document ).on( 'keydown.sbTweaksConfirm', function ( e ) {
			if ( e.key === 'Escape' ) {
				close();
			}
		} );

		$( document.body ).append( $modal );
		$current = $modal;
		$go.trigger( 'focus' );

		return $modal;
	}

	/** Spends the one shot pass ask() leaves on an element. */
	function confirmed( element ) {
		if ( element && element.sbTweaksPass ) {
			element.sbTweaksPass = false;

			return true;
		}

		return false;
	}

	/**
	 * Ask, and on the action click the element again with a pass. A native
	 * click, so a link follows its address and a submit button submits its
	 * form, which a jQuery trigger does not do for links.
	 */
	function ask( element, options ) {
		open( options, function ( checked ) {
			if ( options.before ) {
				options.before( checked );
			}

			element.sbTweaksPass = true;
			element.click();
		} );
	}

	function tell( options ) {
		return open( $.extend( { action: 'OK', alert: true }, options ) );
	}

	window.SBTweaksConfirm = { open: open, close: close, ask: ask, confirmed: confirmed, tell: tell };

	$( document ).on( 'click', '[data-sb-tweaks-confirm]', function ( e ) {
		if ( confirmed( this ) ) {
			return;
		}

		e.preventDefault();

		var $el   = $( this );
		var form  = this.form || $el.closest( 'form' )[0];
		var field = $el.attr( 'data-sb-tweaks-confirm-field' );
		var value = field && form && form.elements[ field ] ? form.elements[ field ].value : '';
		var words = $.trim( $el.text() );

		ask( this, {
			title: $el.attr( 'data-sb-tweaks-confirm-title' ) || words,
			message: String( $el.attr( 'data-sb-tweaks-confirm' ) ).split( '%s' ).join( value ),
			action: $el.attr( 'data-sb-tweaks-confirm-action' ) || words,
			tone: $el.attr( 'data-sb-tweaks-confirm-tone' ) || ( /--danger\b/.test( this.className ) ? 'danger' : '' )
		} );
	} );
}( jQuery ) );
