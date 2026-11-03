/**
 * Unsaved changes on a settings page.
 *
 * A save button that is always live gives no clue whether anything has
 * actually changed, so this watches the form and says so: the button sits
 * disabled and reading Nothing to save until something is edited, and a
 * reminder follows you down the page while changes are pending. Put a change
 * back the way it was and everything goes quiet again.
 *
 * Any form marked data-sb-dirty is watched. A button marked data-sb-save is
 * treated as a save button even when it is not a submit, and one marked
 * data-sb-always-on is left alone. Shared by the SocialBUMP plugins.
 *
 * Cmd+S (Ctrl+S off a Mac) saves too, as it does in Bricks (Sam, 3 November
 * 2026): the form you're working in, else the first with changes. With
 * nothing to save it says so, instead of the browser offering to save the
 * page as a file. It works from inside a text editor (TinyMCE) box as well,
 * and does nothing while a popup is open.
 */
( function () {
	'use strict';

	var LABEL_CLEAN = 'Nothing to save';
	var WARNING     = 'You have unsaved changes.';
	var pending     = 0;

	// Each watched form: { form, dirty(), saving(), save() }.
	var watched     = [];

	function snapshot( form ) {
		var data = [];

		Array.prototype.forEach.call( form.elements, function ( field ) {
			// ACF's own bookkeeping (_acf_changed and the like) isn't something you edited.
			if ( ! field.name || field.type === 'submit' || field.type === 'button' || field.type === 'file' || field.name.indexOf( '_acf_' ) === 0 ) {
				return;
			}

			if ( field.type === 'checkbox' || field.type === 'radio' ) {
				data.push( field.name + '=' + ( field.checked ? '1' : '0' ) );

				return;
			}

			// An editor (TinyMCE) box: compared without the paragraph and line
			// break tags and spaces it adds, so text put back is no change.
			if ( field.classList && field.classList.contains( 'wp-editor-area' ) ) {
				data.push( field.name + '=' + field.value.replace( /<\/?p[^>]*>|<br\s*\/?>|&nbsp;/gi, ' ' ).replace( /\s+/g, ' ' ).trim() );

				return;
			}

			data.push( field.name + '=' + field.value );
		} );

		// Fields with names of their own are compared by name, not by where they
		// sit, so moving a card (module-cards.js) is not a change. A list
		// (name[]) keeps its order, which can matter.
		var listed = function ( entry ) {
			return entry.split( '=' )[0].slice( -2 ) === '[]';
		};

		return data.filter( function ( entry ) {
			return ! listed( entry );
		} ).sort().concat( data.filter( listed ) ).join( '&' );
	}

	/** The buttons that should follow the state of the form. */
	function buttons( form ) {
		var found = Array.prototype.slice.call( form.querySelectorAll( 'input[type=submit], button[type=submit], [data-sb-save]' ) );

		// A save button can sit outside the form it belongs to.
		if ( form.id ) {
			Array.prototype.forEach.call( document.querySelectorAll( '[form=' + form.id + '], [data-sb-save]' ), function ( button ) {
				if ( found.indexOf( button ) === -1 ) {
					found.push( button );
				}
			} );
		}

		return found.filter( function ( button ) {
			return ! button.hasAttribute( 'data-sb-always-on' );
		} );
	}

	/** Where a button keeps its wording. */
	function wording( button ) {
		return button.querySelector( '[data-sb-label]' ) || button;
	}

	function label( button, text ) {
		var target = wording( button );

		if ( target === button && button.tagName === 'INPUT' ) {
			button.value = text;

			return;
		}

		target.textContent = text;
	}

	/**
	 * What a button says when there is something to save.
	 *
	 * A button that starts out saying there is nothing to save cannot use its own
	 * wording for the other state, so it carries the active wording in an
	 * attribute instead.
	 */
	function active( button ) {
		return button.getAttribute( 'data-sb-label-dirty' ) || original( button );
	}

	function original( button ) {
		var target = wording( button );

		if ( target === button && button.tagName === 'INPUT' ) {
			return button.value;
		}

		return target.textContent.trim();
	}

	/** The reminder that follows you down the page. */
	function reminder( form ) {
		var note = document.createElement( 'button' );

		note.type = 'button';
		note.className = 'sb-unsaved';
		note.setAttribute( 'hidden', 'hidden' );
		note.innerHTML = '<span class=' + String.fromCharCode( 34 ) + 'sb-unsaved__dot' + String.fromCharCode( 34 ) + '></span><span>Unsaved changes, click to save</span>';

		note.addEventListener( 'click', function () {
			submit( form );
		} );

		document.body.appendChild( note );

		return note;
	}

	/**
	 * Save the form, using the button that actually saves it.
	 *
	 * A form can hold more than one submit, and not all of them save: the image
	 * sizes form has Reset to defaults sitting above Save changes. Submitting with
	 * whichever came first would send the wrong one, which is exactly what it did.
	 * So the save button is looked for by name, then by being the primary one, and
	 * only then does it fall back to the first submit in the form.
	 */
	function submit( form ) {
		var live = function ( list ) {
			return Array.prototype.filter.call( list, function ( button ) {
				return ! button.disabled;
			} )[0];
		};

		var first = live( form.querySelectorAll( '[data-sb-save]' ) )
			|| live( form.querySelectorAll( 'input[type=submit].button-primary, button[type=submit].button-primary' ) )
			|| live( form.querySelectorAll( 'input[type=submit], button[type=submit]' ) );

		if ( typeof form.requestSubmit === 'function' ) {
			// requestSubmit only accepts a real submit button as the one that sent
			// the form. Hand it a button that only looks like one, type=button with
			// data-sb-save, and the browser throws and nothing is saved, while the
			// button and the reminder both look exactly as they should.
			if ( first && first.type === 'submit' ) {
				form.requestSubmit( first );
			} else {
				form.requestSubmit();
			}

			return;
		}

		form.submit();
	}

	function watch( form ) {
		var saved = snapshot( form );
		var keys  = buttons( form );

		if ( ! keys.length ) {
			return;
		}

		var names  = keys.map( active );

		// Buttons that do work rather than save, and can sit idle when there is none.
		var idle = Array.prototype.slice.call( document.querySelectorAll( '[data-sb-idle]' ) );
		var note   = reminder( form );
		var dirty  = null;
		var saving = false;

		// Until someone first touches the page, anything that changes is the
		// page settling (ACF setting up its editors, menus and rows), not an
		// edit, so it becomes the starting point (Sam, 2 November 2026).
		var touched = false;

		[ 'pointerdown', 'keydown', 'paste', 'drop' ].forEach( function ( type ) {
			document.addEventListener( type, function () {
				touched = true;
			}, true );
		} );

		// A save button that only looks like one still needs to save.
		keys.forEach( function ( button ) {
			if ( button.type !== 'button' ) {
				return;
			}

			button.addEventListener( 'click', function () {
				if ( ! button.disabled ) {
					submit( form );
				}
			} );
		} );

		function paint() {
			if ( ! touched ) {
				saved = snapshot( form );
			}

			var changed = snapshot( form ) !== saved;

			if ( changed === dirty ) {
				return;
			}

			pending += changed ? 1 : ( dirty === null ? 0 : -1 );
			dirty    = changed;

			idle.forEach( function ( button ) {
				// Nothing changed and nothing outstanding, so there is nothing to run.
				button.disabled = ! changed && button.getAttribute( 'data-sb-idle' ) === '1';
			} );

			keys.forEach( function ( button, i ) {
				button.disabled = ! changed;
				button.classList.toggle( 'sb-save--clean', ! changed );
				button.classList.toggle( 'sb-save--dirty', changed );
				label( button, changed ? names[ i ] : LABEL_CLEAN );
			} );

			if ( changed ) {
				note.removeAttribute( 'hidden' );

				return;
			}

			note.setAttribute( 'hidden', 'hidden' );
		}

		watched.push( {
			form: form,
			dirty: function () {
				paint();

				return !! dirty && ! saving;
			},
			saving: function () {
				return saving;
			},
			save: function () {
				submit( form );
			}
		} );

		form.addEventListener( 'input', paint );
		form.addEventListener( 'change', paint );

		/**
		 * Saved by something other than the form itself.
		 *
		 * A page can save these settings as part of another action, and then the
		 * form is no longer dirty even though nobody pressed Save. Dispatch
		 * sb:saved on the form to say so, and the button and the reminder settle
		 * down as if it had been.
		 */
		form.addEventListener( 'sb:saved', function () {
			saved = snapshot( form );
			paint();
		} );

		// The page settled again before anyone touched it (acf-forms.js).
		form.addEventListener( 'sb:baseline', paint );

		// A save stopped before it left (ACF found a field to fix): still unsaved,
		// so the reminder and the leave warning come back.
		form.addEventListener( 'sb:save-failed', function () {
			saving = false;

			if ( dirty ) {
				pending++;
				note.removeAttribute( 'hidden' );
			}
		} );

		// Leaving to save should not warn, or flash the reminder on the way out.
		form.addEventListener( 'submit', function () {
			saving = true;
			pending = 0;
			note.setAttribute( 'hidden', 'hidden' );
		} );

		// Sorting and other scripted changes do not fire input events.
		if ( window.MutationObserver ) {
			new MutationObserver( paint ).observe( form, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'value', 'checked' ] } );
		}

		window.addEventListener( 'beforeunload', function ( event ) {
			if ( ! pending || saving ) {
				return;
			}

			event.preventDefault();
			event.returnValue = WARNING;

			return WARNING;
		} );

		paint();

		// One width whichever it says (Nothing to save, Save changes), so nothing
		// beside it moves. Measured once the fonts are in.
		( document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve() ).then( function () {
			keys.forEach( function ( button, i ) {
				steady( button, [ [ true, LABEL_CLEAN ], [ false, names[ i ] ] ] );
			} );
		} );
	}

	/** Fix a button's width to the widest of its states: [ disabled, wording ]. */
	function steady( button, states ) {
		if ( ! button.offsetWidth ) {
			return;
		}

		var was   = { disabled: button.disabled, cls: button.className, words: original( button ) };
		var width = 0;

		states.forEach( function ( s ) {
			button.disabled = s[0];
			button.classList.toggle( 'sb-save--clean', s[0] );
			button.classList.toggle( 'sb-save--dirty', ! s[0] );
			label( button, s[1] );
			width = Math.max( width, button.getBoundingClientRect().width );
		} );

		button.disabled  = was.disabled;
		button.className = was.cls;
		label( button, was.words );
		button.style.minWidth = Math.ceil( width ) + 'px';
	}

	/** Cmd+S or Ctrl+S: save what needs saving, and never the browser's Save Page. */
	function shortcut( event ) {
		if ( ! ( event.metaKey || event.ctrlKey ) || event.altKey || event.shiftKey ) {
			return;
		}

		if ( String( event.key || '' ).toLowerCase() !== 's' && event.keyCode !== 83 ) {
			return;
		}

		event.preventDefault();

		// Held down, or a popup has the page's attention: nothing more.
		if ( event.repeat || document.querySelector( '.sb-tweaks-modal' ) ) {
			return;
		}

		// What's typed in an editor box reaches its form field first.
		if ( window.tinymce && typeof window.tinymce.triggerSave === 'function' ) {
			window.tinymce.triggerSave();
		}

		var dirty = watched.filter( function ( w ) {
			return w.dirty();
		} );

		if ( ! dirty.length ) {
			// Already on its way: the page is about to change.
			if ( watched.some( function ( w ) { return w.saving(); } ) ) {
				return;
			}

			if ( window.SBTweaksUI && window.SBTweaksUI.toast ) {
				window.SBTweaksUI.toast( LABEL_CLEAN, 'info' );
			}

			return;
		}

		var here = document.activeElement;

		( dirty.filter( function ( w ) {
			return w.form.contains( here );
		} )[0] || dirty[0] ).save();
	}

	/**
	 * Editor boxes are pages of their own, so their keys need listening to
	 * there too. Run at the start and again once the page has loaded, as the
	 * editor script can arrive after this one.
	 */
	var editorsHooked = false;

	function editors() {
		var mce = window.tinymce;

		if ( editorsHooked || ! mce || typeof mce.on !== 'function' ) {
			return;
		}

		editorsHooked = true;

		var hook = function ( ed ) {
			if ( ed && typeof ed.on === 'function' && ! ed.sbSaveKey ) {
				ed.sbSaveKey = true;
				ed.on( 'keydown', shortcut );
			}
		};

		Array.prototype.forEach.call( ( mce.get && mce.get() ) || [], hook );

		mce.on( 'AddEditor', function ( e ) {
			hook( e.editor );
		} );
	}

	function start() {
		Array.prototype.forEach.call( document.querySelectorAll( 'form[data-sb-dirty]' ), watch );
		document.addEventListener( 'keydown', shortcut, true );
		editors();
		window.addEventListener( 'load', editors );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
