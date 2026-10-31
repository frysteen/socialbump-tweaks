/**
 * SocialBUMP UI: what the shared look does.
 *
 * - The Auto, Light and Dark switch in the banner. It changes the page at
 *   once and saves the choice to the user, so it follows them around.
 * - Toasts. SBTweaksUI.toast( message, kind, more ) floats a message in at the
 *   top right; kind is ok, info, warn or danger. Errors stay until dismissed,
 *   the rest go after a few seconds. The plain saved, error and info notices
 *   our own pages print across the top are turned into toasts, so the page
 *   never jumps. Notices that belong to one part of a page (.inline) and
 *   other plugins' notices are left where they are.
 * - Cards on a settings form show an Unsaved chip and an amber ring while
 *   something in them differs from what was saved. Clicking the chip saves.
 * - Tooltips: data-sb-tip="Copy link" on an icon button, in place of title.
 * - Character counts: data-sb-count-for="{field id}" on a span.
 */
( function () {
	'use strict';

	var cfg  = window.sbTweaksUI || {};
	var body = document.body;

	var ICONS = {
		ok: '<path d="M20 6L9 17l-5-5"/>',
		info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
		warn: '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
		danger: '<circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/>'
	};

	/* ---------- Toasts ---------- */

	var stack = null;

	function holder() {
		if ( ! stack ) {
			stack = document.createElement( 'div' );
			stack.className = 'sb-ui-toasts';
			stack.setAttribute( 'aria-live', 'polite' );
			body.appendChild( stack );
		}

		return stack;
	}

	function leave( el ) {
		if ( ! el.parentNode ) {
			return;
		}

		el.classList.add( 'is-leaving' );
		window.setTimeout( function () {
			if ( el.parentNode ) {
				el.parentNode.removeChild( el );
			}
		}, 200 );
	}

	function toast( message, kind, more ) {
		kind = ICONS[ kind ] ? kind : 'ok';

		var el   = document.createElement( 'div' );
		var life = kind === 'danger' ? 0 : ( kind === 'warn' ? 6000 : 4000 );

		el.className = 'sb-ui-toast sb-ui-toast--' + kind;
		el.setAttribute( 'role', kind === 'danger' ? 'alert' : 'status' );
		el.style.setProperty( '--sbui-life', ( life / 1000 ) + 's' );
		el.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICONS[ kind ] + '</svg><div><b></b></div><button type="button">Dismiss</button>';
		el.querySelector( 'b' ).textContent = message;

		if ( more ) {
			var small = document.createElement( 'small' );

			if ( more.nodeType ) {
				small.appendChild( more );
			} else {
				small.textContent = more;
			}

			el.querySelector( 'div' ).appendChild( small );
		}

		el.querySelector( 'button' ).addEventListener( 'click', function () {
			leave( el );
		} );

		var box = holder();
		box.insertBefore( el, box.firstChild );

		if ( life ) {
			var timer = window.setTimeout( function () {
				leave( el );
			}, life );

			// Hovering keeps it, so it can be read.
			el.addEventListener( 'mouseenter', function () {
				window.clearTimeout( timer );
			} );
			el.addEventListener( 'mouseleave', function () {
				timer = window.setTimeout( function () {
					leave( el );
				}, 1500 );
			} );
		}

		return el;
	}

	/**
	 * Our own notices across the top become toasts. Only the plain ones: a
	 * notice with extra classes or an id usually belongs to another plugin
	 * that remembers its own dismissal, so those stay put.
	 */
	var PLAIN = [ 'notice', 'notice-success', 'notice-error', 'notice-info', 'notice-warning', 'is-dismissible', 'updated', 'error' ];

	function noticesToToasts() {
		var wrap = document.querySelector( '.sb-tweaks-wrap' );

		if ( ! wrap ) {
			return;
		}

		Array.prototype.forEach.call( wrap.querySelectorAll( '.notice' ), function ( n ) {
			if ( n.id || n.classList.contains( 'inline' ) || ! n.classList.contains( 'is-dismissible' ) ) {
				return;
			}

			for ( var i = 0; i < n.classList.length; i++ ) {
				if ( PLAIN.indexOf( n.classList[ i ] ) === -1 ) {
					return;
				}
			}

			var kind = n.classList.contains( 'notice-error' ) ? 'danger' : ( n.classList.contains( 'notice-warning' ) ? 'warn' : ( n.classList.contains( 'notice-info' ) ? 'info' : 'ok' ) );
			var p    = n.querySelector( 'p' );
			var text = ( p ? p.textContent : n.textContent ).replace( /\s+/g, ' ' ).trim();

			// WordPress adds a Dismiss this notice. button inside.
			text = text.replace( /Dismiss this notice\.?$/, '' ).trim();

			if ( text ) {
				toast( text, kind );
			}

			n.parentNode.removeChild( n );
		} );
	}

	/* ---------- Auto, Light and Dark ---------- */

	function setMode( mode ) {
		var lightOnly = body.classList.contains( 'sb-ui-light-only' );

		document.querySelectorAll( '[data-sb-ui-mode]' ).forEach( function ( b ) {
			b.setAttribute( 'aria-pressed', b.getAttribute( 'data-sb-ui-mode' ) === mode ? 'true' : 'false' );
		} );

		if ( ! lightOnly ) {
			body.classList.remove( 'sb-ui-mode-auto', 'sb-ui-mode-light', 'sb-ui-mode-dark' );
			body.classList.add( 'sb-ui-mode-' + mode );
		} else if ( mode !== 'light' ) {
			toast( 'Saved for the pages that have it', 'info', 'This page stays light until it has been moved to the new look.' );
		}

		if ( ! cfg.ajax ) {
			return;
		}

		var data = new FormData();

		data.append( 'action', cfg.action );
		data.append( 'nonce', cfg.nonce );
		data.append( 'mode', mode );

		fetch( cfg.ajax, { method: 'POST', body: data, credentials: 'same-origin' } ).then( function ( r ) {
			if ( ! r.ok ) {
				throw new Error( r.status );
			}
		} ).catch( function () {
			toast( 'That mode wasn\'t saved', 'danger', 'It applies to this page until you reload. Try again in a moment.' );
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest ? e.target.closest( '[data-sb-ui-mode]' ) : null;

		if ( b && b.getAttribute( 'aria-pressed' ) !== 'true' ) {
			setMode( b.getAttribute( 'data-sb-ui-mode' ) );
		}
	} );

	/* ---------- Tooltips ---------- */

	/*
	 * Anything with data-sb-tip="Copy link" gets a small label that drops down
	 * under it on hover or keyboard focus (above it when there's no room
	 * below). Use it on icon buttons in place of title, which looks like the
	 * browser's. The text can change while it shows (Copied). An icon button
	 * with no label of its own gets the tip as its aria-label.
	 */
	var tip = null;
	var tipFor = null;
	var tipTimer = null;

	function tipEl() {
		if ( ! tip ) {
			tip = document.createElement( 'div' );
			tip.className = 'sb-ui-tip';
			tip.setAttribute( 'role', 'tooltip' );
			tip.id = 'sb-ui-tip';
			body.appendChild( tip );
		}

		return tip;
	}

	function placeTip() {
		if ( ! tipFor || ! tip ) {
			return;
		}

		var r   = tipFor.getBoundingClientRect();
		var w   = tip.offsetWidth;
		var h   = tip.offsetHeight;
		var gap = 8;
		var x   = r.left + r.width / 2 - w / 2;
		var up  = r.bottom + gap + h > window.innerHeight - 8 && r.top - gap - h > 8;

		x = Math.max( 8, Math.min( x, window.innerWidth - w - 8 ) );

		tip.classList.toggle( 'is-up', up );
		tip.style.left = Math.round( x ) + 'px';
		tip.style.top = Math.round( up ? r.top - gap - h : r.bottom + gap ) + 'px';
		tip.style.setProperty( '--sbui-tip-arrow', Math.round( r.left + r.width / 2 - x ) + 'px' );
	}

	function showTip( el ) {
		var text = el.getAttribute( 'data-sb-tip' );

		if ( ! text ) {
			return;
		}

		tipFor = el;
		el.removeAttribute( 'title' );
		tipEl().textContent = text;
		tip.classList.remove( 'is-on' );
		placeTip();
		el.setAttribute( 'aria-describedby', 'sb-ui-tip' );

		window.clearTimeout( tipTimer );
		tipTimer = window.setTimeout( function () {
			tip.classList.add( 'is-on' );
		}, 120 );
	}

	function hideTip() {
		window.clearTimeout( tipTimer );

		if ( tipFor ) {
			tipFor.removeAttribute( 'aria-describedby' );
		}

		tipFor = null;

		if ( tip ) {
			tip.classList.remove( 'is-on' );
		}
	}

	function tipTarget( e ) {
		return e.target.closest ? e.target.closest( '[data-sb-tip]' ) : null;
	}

	document.addEventListener( 'mouseover', function ( e ) {
		var el = tipTarget( e );

		if ( el && el !== tipFor ) {
			showTip( el );
		} else if ( ! el && tipFor && ! tipFor.contains( e.target ) ) {
			hideTip();
		}
	} );

	document.addEventListener( 'focusin', function ( e ) {
		var el = tipTarget( e );

		if ( el && el.matches( ':focus-visible' ) ) {
			showTip( el );
		}
	} );

	document.addEventListener( 'focusout', hideTip );
	window.addEventListener( 'scroll', hideTip, true );
	window.addEventListener( 'resize', hideTip );

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' ) {
			hideTip();
		}
	} );

	// A changed tip (Copied) shows straight away, in place.
	new MutationObserver( function ( list ) {
		list.forEach( function ( m ) {
			if ( m.target === tipFor && tip ) {
				tip.textContent = tipFor.getAttribute( 'data-sb-tip' ) || '';
				placeTip();
			}
		} );
	} ).observe( body, { subtree: true, attributes: true, attributeFilter: [ 'data-sb-tip' ] } );

	function labelTips() {
		document.querySelectorAll( '[data-sb-tip]' ).forEach( function ( el ) {
			if ( ! el.getAttribute( 'aria-label' ) && ! el.textContent.trim() ) {
				el.setAttribute( 'aria-label', el.getAttribute( 'data-sb-tip' ) );
			}

			el.removeAttribute( 'title' );
		} );
	}

	/* ---------- Character counts ---------- */

	/*
	 * <span class="sb-ui-chars" data-sb-count-for="{field id}"></span> shows
	 * 42 / 160 for a field with a maxlength, amber in its last 10.
	 */
	function counts() {
		document.querySelectorAll( '[data-sb-count-for]' ).forEach( function ( out ) {
			var field = document.getElementById( out.getAttribute( 'data-sb-count-for' ) );
			var max   = field ? parseInt( field.getAttribute( 'maxlength' ), 10 ) : 0;

			if ( ! field || ! max ) {
				return;
			}

			var show = function () {
				var n = Array.from( field.value ).length;

				out.textContent = n + ' / ' + max;
				out.classList.toggle( 'is-near', n > max - 10 );
			};

			field.addEventListener( 'input', show );
			show();
		} );
	}

	/* ---------- Edited cards ---------- */

	function cardState( card ) {
		var out = [];

		card.querySelectorAll( 'input, select, textarea' ).forEach( function ( f ) {
			// Hidden inputs only when they ask (data-sb-watch): ACF keeps plenty.
			if ( ! f.name || ( f.type === 'hidden' && ! f.hasAttribute( 'data-sb-watch' ) ) || f.type === 'submit' || f.type === 'button' || f.type === 'file' ) {
				return;
			}

			if ( f.type === 'checkbox' || f.type === 'radio' ) {
				out.push( f.name + '=' + f.value + ':' + ( f.checked ? 1 : 0 ) );
			} else {
				out.push( f.name + '=' + f.value );
			}
		} );

		return out.join( '&' );
	}

	/**
	 * Save a form as its own Save button would: the one marked data-sb-save,
	 * else its primary submit, else its first, wherever it sits (form=).
	 */
	function save( form ) {
		if ( ! form ) {
			return;
		}

		var all = Array.prototype.slice.call( form.querySelectorAll( 'button[type=submit], input[type=submit], [data-sb-save]' ) );

		if ( form.id ) {
			all = all.concat( Array.prototype.slice.call( document.querySelectorAll( '[form="' + form.id + '"][type=submit]' ) ) );
		}

		all = all.filter( function ( b ) {
			return ! b.disabled;
		} );

		var pick = all.filter( function ( b ) {
			return b.hasAttribute( 'data-sb-save' );
		} )[0] || all.filter( function ( b ) {
			return b.classList.contains( 'button-primary' );
		} )[0] || all[0];

		hideTip();

		if ( pick && pick.type === 'submit' && typeof form.requestSubmit === 'function' ) {
			form.requestSubmit( pick );
		} else if ( pick ) {
			pick.click();
		} else if ( typeof form.requestSubmit === 'function' ) {
			form.requestSubmit();
		} else {
			form.submit();
		}
	}

	function watchCards() {
		document.querySelectorAll( 'form[data-sb-dirty] .sb-tweaks-card' ).forEach( function ( card ) {
			var saved = cardState( card );
			var head  = card.querySelector( '.sb-tweaks-card__head h3, .sb-tweaks-card__head h2' );
			var chip  = null;

			function check() {
				var edited = cardState( card ) !== saved;

				card.classList.toggle( 'is-edited', edited );

				if ( edited && ! chip && head ) {
					chip = document.createElement( 'button' );
					chip.type = 'button';
					chip.className = 'sb-ui-unsaved';
					chip.textContent = 'Unsaved';
					chip.setAttribute( 'data-sb-tip', 'Click to save' );
					chip.addEventListener( 'click', function () {
						save( card.closest( 'form' ) );
					} );
					head.appendChild( chip );
				} else if ( ! edited && chip ) {
					chip.parentNode.removeChild( chip );
					chip = null;
				}
			}

			card.addEventListener( 'input', check );
			card.addEventListener( 'change', check );
		} );
	}

	function ready() {
		// After WordPress has moved notices below the banner.
		window.setTimeout( noticesToToasts, 0 );
		watchCards();
		labelTips();
		counts();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}

	window.SBTweaksUI = { toast: toast, mode: setMode, hideTip: hideTip };
} )();
