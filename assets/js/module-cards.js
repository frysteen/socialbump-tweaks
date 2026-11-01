/**
 * Cards that collapse to their title, drag to a new place, and switch on or
 * off together.
 *
 * Shared by the SocialBUMP plugins. Works on any element carrying
 * data-sb-cards, whose children carry data-sb-card. A card's first child is
 * its head (the title and the switch): a chevron goes at its start, a drag
 * handle at its end, and the head is all that shows while it is collapsed.
 *
 * Drag a card by its handle to move it (Sam, 1 November 2026, in place of the
 * Reorder dialogue). The order is saved as soon as it is dropped, per user, to
 * the plugin's own endpoint, and the menu and admin bar follow it from the
 * next page. The arrow keys move a focused handle too.
 *
 * Enable all and Disable all flip every card's switch that can be changed,
 * as clicking each would, so Save changes lights up and saves them. The count
 * pill in the toolbar follows the switches.
 */
( function () {
	'use strict';

	function post( action, data ) {
		var body = new FormData();

		body.append( 'action', action );

		Object.keys( data ).forEach( function ( key ) {
			if ( Array.isArray( data[ key ] ) ) {
				data[ key ].forEach( function ( value ) {
					body.append( key + '[]', value );
				} );

				return;
			}

			body.append( key, data[ key ] );
		} );

		return window.fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } );
	}

	function el( tag, className, text ) {
		var node = document.createElement( tag );

		if ( className ) {
			node.className = className;
		}

		if ( text ) {
			node.textContent = text;
		}

		return node;
	}

	function toast( message, kind ) {
		if ( window.SBTweaksUI && window.SBTweaksUI.toast ) {
			window.SBTweaksUI.toast( message, kind );
		}
	}

	function setUp( grid ) {
		var key       = grid.getAttribute( 'data-sb-cards' );
		var action    = grid.getAttribute( 'data-sb-cards-action' );
		var nonce     = grid.getAttribute( 'data-sb-cards-nonce' );
		var collapsed = ( grid.getAttribute( 'data-sb-cards-collapsed' ) || '' ).split( ',' ).filter( Boolean );
		var mine      = '[data-sb-cards-tools="' + key + '"] ';

		function cards() {
			return Array.prototype.slice.call( grid.children ).filter( function ( node ) {
				return node.hasAttribute( 'data-sb-card' );
			} );
		}

		function order() {
			return cards().map( function ( card ) {
				return card.getAttribute( 'data-sb-card' );
			} ).join( ',' );
		}

		function save() {
			var shut = cards().filter( function ( card ) {
				return card.classList.contains( 'is-collapsed' );
			} ).map( function ( card ) {
				return card.getAttribute( 'data-sb-card' );
			} );

			return post( action, { key: key, nonce: nonce, order: order().split( ',' ), collapsed: shut } );
		}

		/** Save a new order, saying so. */
		function saveOrder() {
			save().then( function ( response ) {
				toast( response.ok ? 'Order saved' : 'The order was not saved. Try again.', response.ok ? 'ok' : 'danger' );
			} ).catch( function () {
				toast( 'The order was not saved. Try again.', 'danger' );
			} );
		}

		function setCollapsed( card, shut ) {
			var toggle = card.querySelector( '.sb-card__toggle' );

			card.classList.toggle( 'is-collapsed', shut );

			if ( toggle ) {
				toggle.setAttribute( 'aria-expanded', shut ? 'false' : 'true' );
				toggle.setAttribute( 'aria-label', shut ? 'Expand' : 'Collapse' );
			}
		}

		function title( card ) {
			var heading = card.querySelector( 'h1, h2, h3, h4' );

			return heading ? heading.textContent.trim() : card.getAttribute( 'data-sb-card' );
		}

		/** A card's own switch, in its head. */
		function switchOf( card ) {
			return card.firstElementChild ? card.firstElementChild.querySelector( '.sb-tweaks-switch input[type="checkbox"]' ) : null;
		}

		/* ---------- Count, Enable all and Disable all ---------- */

		function count() {
			var number = document.querySelector( '[data-sb-cards-count="' + key + '"] b' );

			if ( ! number ) {
				return;
			}

			number.textContent = String( cards().filter( function ( card ) {
				var box = switchOf( card );

				return box && box.checked && ! box.disabled && ! card.classList.contains( 'is-unavailable' );
			} ).length );
		}

		function setAll( on ) {
			cards().forEach( function ( card ) {
				var box = switchOf( card );

				if ( box && ! box.disabled && box.checked !== on ) {
					box.checked = on;
					box.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
			} );

			count();
		}

		grid.addEventListener( 'change', function ( event ) {
			if ( event.target.matches && event.target.matches( '.sb-tweaks-switch input' ) ) {
				count();
			}
		} );

		/* ---------- Dragging ---------- */

		var moving = null;
		var ghost  = null;
		var offX   = 0;
		var offY   = 0;
		var before = '';

		function onMove( event ) {
			if ( ! moving ) {
				return;
			}

			event.preventDefault();

			ghost.style.left = ( event.clientX - offX ) + 'px';
			ghost.style.top  = ( event.clientY - offY ) + 'px';

			// Near the top or bottom of the window, the page scrolls.
			if ( event.clientY < 60 ) {
				window.scrollBy( 0, -14 );
			} else if ( event.clientY > window.innerHeight - 60 ) {
				window.scrollBy( 0, 14 );
			}

			// The ghost lets the pointer through, so this is the card under it.
			var under = document.elementFromPoint( event.clientX, event.clientY );
			var over  = under && under.closest ? under.closest( '[data-sb-card]' ) : null;

			if ( ! over || over === moving || over.parentNode !== grid ) {
				return;
			}

			var rect  = over.getBoundingClientRect();
			var after = event.clientX > rect.left + rect.width / 2;

			// One card to a row (a narrow screen): above or below instead.
			if ( rect.width > grid.getBoundingClientRect().width * 0.8 ) {
				after = event.clientY > rect.top + rect.height / 2;
			}

			if ( after && over.nextElementSibling !== moving ) {
				grid.insertBefore( moving, over.nextElementSibling );
			} else if ( ! after && over.previousElementSibling !== moving ) {
				grid.insertBefore( moving, over );
			}
		}

		function onUp() {
			document.removeEventListener( 'pointermove', onMove );
			document.removeEventListener( 'pointerup', onUp );
			document.removeEventListener( 'pointercancel', onUp );
			document.body.classList.remove( 'sb-cards-dragging' );

			if ( ghost && ghost.parentNode ) {
				ghost.parentNode.removeChild( ghost );
			}

			ghost = null;

			if ( ! moving ) {
				return;
			}

			moving.classList.remove( 'is-moving' );
			moving = null;

			if ( order() !== before ) {
				saveOrder();
			}
		}

		grid.addEventListener( 'pointerdown', function ( event ) {
			var grip = event.target.closest ? event.target.closest( '.sb-card__grip' ) : null;
			var card = grip ? grip.closest( '[data-sb-card]' ) : null;

			if ( ! card || card.parentNode !== grid || event.button !== 0 ) {
				return;
			}

			event.preventDefault();

			var rect = card.getBoundingClientRect();

			offX   = event.clientX - rect.left;
			offY   = event.clientY - rect.top;
			before = order();

			// A copy follows the pointer. Its fields lose their names, so the
			// form never counts them.
			ghost = card.cloneNode( true );
			ghost.classList.add( 'sb-card-ghost' );
			ghost.removeAttribute( 'data-sb-card' );
			Array.prototype.forEach.call( ghost.querySelectorAll( '[name], [id]' ), function ( node ) {
				node.removeAttribute( 'name' );
				node.removeAttribute( 'id' );
			} );
			ghost.style.width  = rect.width + 'px';
			ghost.style.height = rect.height + 'px';
			ghost.style.left   = rect.left + 'px';
			ghost.style.top    = rect.top + 'px';
			grid.parentNode.appendChild( ghost );

			// The card itself stays in the grid as the gap it will drop into.
			moving = card;
			card.classList.add( 'is-moving' );
			document.body.classList.add( 'sb-cards-dragging' );

			document.addEventListener( 'pointermove', onMove );
			document.addEventListener( 'pointerup', onUp );
			document.addEventListener( 'pointercancel', onUp );
		} );

		// The arrow keys on a handle move its card one place.
		grid.addEventListener( 'keydown', function ( event ) {
			var grip = event.target.closest ? event.target.closest( '.sb-card__grip' ) : null;
			var card = grip ? grip.closest( '[data-sb-card]' ) : null;
			var back = event.key === 'ArrowUp' || event.key === 'ArrowLeft';
			var on   = event.key === 'ArrowDown' || event.key === 'ArrowRight';

			if ( ! card || ( ! back && ! on ) ) {
				return;
			}

			event.preventDefault();

			var list = cards();
			var at   = list.indexOf( card );

			if ( back && at > 0 ) {
				grid.insertBefore( card, list[ at - 1 ] );
			} else if ( on && at < list.length - 1 ) {
				grid.insertBefore( card, list[ at + 1 ].nextElementSibling );
			} else {
				return;
			}

			grip.focus();
			saveOrder();
		} );

		/* ---------- Each card: collapse and its handle ---------- */

		cards().forEach( function ( card ) {
			var head   = card.firstElementChild;
			var toggle = el( 'button', 'sb-card__toggle' );
			var grip   = el( 'button', 'sb-card__grip' );

			if ( ! head ) {
				return;
			}

			toggle.type = 'button';
			head.insertBefore( toggle, head.firstChild );

			grip.type = 'button';
			grip.setAttribute( 'aria-label', 'Move ' + title( card ) + ' (arrow keys)' );
			grip.setAttribute( 'data-sb-tip', 'Drag to move' );
			head.appendChild( grip );

			// The title is a bigger target than the chevron, so it toggles too.
			var heading = head.querySelector( 'h1, h2, h3, h4' );

			if ( heading ) {
				heading.classList.add( 'sb-card__title' );
				heading.addEventListener( 'click', function () {
					setCollapsed( card, ! card.classList.contains( 'is-collapsed' ) );
					save();
				} );
			}

			setCollapsed( card, collapsed.indexOf( card.getAttribute( 'data-sb-card' ) ) !== -1 );

			toggle.addEventListener( 'click', function () {
				setCollapsed( card, ! card.classList.contains( 'is-collapsed' ) );
				save();
			} );
		} );

		/* ---------- The toolbar ---------- */

		var shutAll = document.querySelector( mine + '[data-sb-cards-collapse]' );
		var openAll = document.querySelector( mine + '[data-sb-cards-expand]' );
		var shutOff = document.querySelector( mine + '[data-sb-cards-collapse-off]' );
		var onAll   = document.querySelector( mine + '[data-sb-cards-enable]' );
		var offAll  = document.querySelector( mine + '[data-sb-cards-disable]' );

		/** A card whose switch in the head is off. */
		function disabled( card ) {
			var box = switchOf( card );

			return !! ( box && ! box.checked );
		}

		if ( shutOff ) {
			shutOff.addEventListener( 'click', function () {
				cards().filter( disabled ).forEach( function ( card ) {
					setCollapsed( card, true );
				} );
				save();
			} );
		}

		if ( shutAll ) {
			shutAll.addEventListener( 'click', function () {
				cards().forEach( function ( card ) {
					setCollapsed( card, true );
				} );
				save();
			} );
		}

		if ( openAll ) {
			openAll.addEventListener( 'click', function () {
				cards().forEach( function ( card ) {
					setCollapsed( card, false );
				} );
				save();
			} );
		}

		if ( onAll ) {
			onAll.addEventListener( 'click', function () {
				setAll( true );
			} );
		}

		if ( offAll ) {
			offAll.addEventListener( 'click', function () {
				setAll( false );
			} );
		}
	}

	function start() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-sb-cards]' ), setUp );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
