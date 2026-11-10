/**
 * The gallery popup.
 *
 * Any image carrying data-sb-images-zoom opens larger in an overlay when clicked.
 * The arrows and the left and right keys move through its group, in page order
 * (groupOf(), Sam, 10 November 2026): images with the same Lightbox ID
 * (data-sb-images-zoom-group) across the page, galleries included; with none,
 * every image on the page with none, galleries' too (Sam, 10 November 2026).
 * Esc or a click on the dark area closes. Built once, reused.
 */
( function () {
	'use strict';

	var overlay = null;
	var picture = null;
	var caption = null;
	var prev = null;
	var next = null;
	var group = [];
	var at = 0;

	function build() {
		if ( overlay ) {
			return;
		}

		overlay = document.createElement( 'div' );
		overlay.className = 'sb-images-zoom';
		overlay.setAttribute( 'hidden', 'hidden' );
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );

		picture = document.createElement( 'img' );
		picture.className = 'sb-images-zoom__picture';

		caption = document.createElement( 'p' );
		caption.className = 'sb-images-zoom__caption';

		var close = button( 'sb-images-zoom__close', 'Close', '\u00d7' );

		prev = button( 'sb-images-zoom__prev', 'Previous image', '\u2039' );
		next = button( 'sb-images-zoom__next', 'Next image', '\u203a' );

		close.addEventListener( 'click', hide );
		prev.addEventListener( 'click', function () {
			move( -1 );
		} );
		next.addEventListener( 'click', function () {
			move( 1 );
		} );

		overlay.appendChild( picture );
		overlay.appendChild( caption );
		overlay.appendChild( close );
		overlay.appendChild( prev );
		overlay.appendChild( next );

		overlay.addEventListener( 'click', function ( event ) {
			if ( event.target === overlay ) {
				hide();
			}
		} );

		document.body.appendChild( overlay );

		document.addEventListener( 'keydown', function ( event ) {
			if ( overlay.hasAttribute( 'hidden' ) ) {
				return;
			}

			if ( event.key === 'Escape' ) {
				hide();
			} else if ( event.key === 'ArrowLeft' ) {
				move( -1 );
			} else if ( event.key === 'ArrowRight' ) {
				move( 1 );
			}
		} );
	}

	function button( className, label, text ) {
		var node = document.createElement( 'button' );

		node.type = 'button';
		node.className = 'sb-images-zoom__button ' + className;
		node.setAttribute( 'aria-label', label );
		node.textContent = text;

		return node;
	}

	function show( images, index ) {
		build();

		group = images;
		at = index;

		overlay.removeAttribute( 'hidden' );
		document.body.classList.add( 'sb-images-zoom-open' );
		paint();
	}

	function paint() {
		var img = group[ at ];

		picture.src = img.getAttribute( 'data-sb-images-zoom' );
		picture.alt = img.alt || '';

		var fig = img.closest ? img.closest( 'figure' ) : null;
		var text = fig ? fig.querySelector( 'figcaption' ) : null;

		caption.textContent = text ? text.textContent : '';

		var many = group.length > 1;

		prev.style.display = many ? '' : 'none';
		next.style.display = many ? '' : 'none';
	}

	function move( step ) {
		if ( group.length < 2 ) {
			return;
		}

		at = ( at + step + group.length ) % group.length;
		paint();
	}

	function hide() {
		overlay.setAttribute( 'hidden', 'hidden' );
		document.body.classList.remove( 'sb-images-zoom-open' );
		picture.src = '';
	}

	function idOf( img ) {
		return img.getAttribute( 'data-sb-images-zoom-group' ) || '';
	}

	/**
	 * The images that page through with target: every one on the page with the
	 * same Lightbox ID, or with none when it has none, galleries' included. In
	 * page order.
	 */
	function groupOf( target ) {
		var id = idOf( target );
		var all = Array.prototype.slice.call( document.querySelectorAll( 'img[data-sb-images-zoom]' ) );

		return all.filter( function ( img ) {
			return idOf( img ) === id;
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;

		if ( ! target || ! target.getAttribute || ! target.getAttribute( 'data-sb-images-zoom' ) ) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		var images = groupOf( target );

		show( images, Math.max( 0, images.indexOf( target ) ) );
	}, true );
}() );
