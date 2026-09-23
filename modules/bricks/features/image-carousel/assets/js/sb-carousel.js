/**
 * SocialBUMP Image Carousel for Bricks
 */
( function () {
	'use strict';

	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function initCarousel( el ) {
		if ( typeof Splide === 'undefined' ) {
			return;
		}

		/* Tear down a previous instance (builder re-render). */
		if ( el._sbSplide ) {
			try {
				el._sbSplide.destroy( true );
			} catch ( e ) {}
			el._sbSplide = null;
		}

		var config;

		try {
			config = JSON.parse( el.getAttribute( 'data-sb-carousel' ) || '{}' );
		} catch ( e ) {
			return;
		}

		var options = config.splide || {};

		/* Respect the visitor's reduced motion setting. */
		if ( reduceMotion ) {
			options.autoplay = false;
			delete options.autoScroll;
			config.continuous = false;
		}

		var splide = new Splide( el, options );
		var extensions = {};

		if ( config.continuous && window.splide && window.splide.Extensions && window.splide.Extensions.AutoScroll ) {
			extensions.AutoScroll = window.splide.Extensions.AutoScroll;
		}

		/* Progress bar */
		if ( config.progress ) {
			var bar = el.querySelector( '.sb-carousel__progress-bar' );

			if ( bar ) {
				splide.on( 'autoplay:playing', function ( rate ) {
					bar.style.width = String( 100 * rate ) + '%';
				} );
			}
		}

		/**
		 * Native lazy loading is unreliable inside a carousel, because Splide moves
		 * the track with a transform rather than scrolling. Slides arriving during
		 * autoplay can stay blank, so we load them ourselves.
		 */
		function loadSlideImages( slide ) {
			if ( ! slide ) {
				return;
			}

			var images = slide.querySelectorAll( 'img[loading=lazy]' );

			Array.prototype.forEach.call( images, function ( img ) {
				img.setAttribute( 'loading', 'eager' );

				/* Nudge browsers that already skipped the image. */
				if ( ! img.complete && img.getAttribute( 'src' ) ) {
					img.setAttribute( 'src', img.getAttribute( 'src' ) );
				}
			} );
		}

		function loadAllImages() {
			Array.prototype.forEach.call( el.querySelectorAll( '.splide__slide' ), loadSlideImages );
		}

		var loopMode = options.type === 'loop' || config.continuous;

		if ( loopMode ) {
			/* A looping carousel shows every slide sooner or later, so load the lot. */
			splide.on( 'mounted refresh', loadAllImages );
		} else {
			/* Otherwise load each slide just before it comes into view. */
			splide.on( 'mounted refresh move', function () {
				var slides = splide.Components.Elements.slides || [];
				var ahead = Math.ceil( splide.options.perPage || 1 ) + 1;
				var last = Math.min( splide.index + ahead, slides.length - 1 );

				for ( var i = 0; i <= last; i++ ) {
					loadSlideImages( slides[ i ] );
				}
			} );
		}

		/* Keep cloned slides out of the lightbox and out of the accessibility tree. */
		splide.on( 'mounted refresh', function () {
			var clones = el.querySelectorAll( '.splide__slide--clone' );

			Array.prototype.forEach.call( clones, function ( clone ) {
				clone.setAttribute( 'aria-hidden', 'true' );

				var links = clone.querySelectorAll( 'a[data-pswp-src]' );

				Array.prototype.forEach.call( links, function ( link ) {
					link.removeAttribute( 'data-pswp-src' );
					link.setAttribute( 'tabindex', '-1' );
				} );
			} );

			el.classList.add( 'sb-carousel--ready' );
		} );

		/**
		 * Keyboard arrows. Splide only listens while the carousel has focus and
		 * never adds a focus target, so we handle it: arrow keys work while the
		 * pointer is over the carousel, or while anything inside it has focus.
		 */
		if ( config.keyboard === 'on' ) {
			if ( el._sbKeyHandler ) {
				document.removeEventListener( 'keydown', el._sbKeyHandler );
			}

			el._sbHovered = false;

			el.addEventListener( 'mouseenter', function () {
				el._sbHovered = true;
			} );

			el.addEventListener( 'mouseleave', function () {
				el._sbHovered = false;
			} );

			el._sbKeyHandler = function ( event ) {
				var focused = el === document.activeElement || el.contains( document.activeElement );

				if ( ! el._sbHovered && ! focused ) {
					return;
				}

				var target = event.target;

				/* Leave typing alone. */
				if ( target && ( /^(INPUT|TEXTAREA|SELECT)$/.test( target.tagName ) || target.isContentEditable ) ) {
					return;
				}

				var rtl = splide.options.direction === 'rtl';

				if ( event.key === 'ArrowLeft' ) {
					event.preventDefault();
					splide.go( rtl ? '+1' : '-1' );
				} else if ( event.key === 'ArrowRight' ) {
					event.preventDefault();
					splide.go( rtl ? '-1' : '+1' );
				}
			};

			document.addEventListener( 'keydown', el._sbKeyHandler );

			splide.on( 'destroy', function () {
				document.removeEventListener( 'keydown', el._sbKeyHandler );
			} );
		}

		/**
		 * Wheel control, handled here rather than by Splide so a trackpad and a
		 * mouse behave the way people expect.
		 *
		 * A trackpad reports small fractional deltas on both axes, so a sideways
		 * two finger swipe moves the carousel and an up or down swipe scrolls the
		 * page as normal. A mouse wheel only has one axis, and reports large
		 * stepped deltas, so up and down moves the carousel.
		 */
		if ( config.wheel === 'on' ) {
			var lastWheelMove = 0;

			el.addEventListener(
				'wheel',
				function ( event ) {
					var absX = Math.abs( event.deltaX );
					var absY = Math.abs( event.deltaY );
					var horizontal = absX > absY;

					/* Stepped or line based deltas mean a mouse wheel, not a trackpad. */
					var isMouseWheel = ! horizontal &&
						( event.deltaMode !== 0 || ( absY >= 40 && absY % 1 === 0 ) );

					/* A trackpad scrolling up or down belongs to the page. */
					if ( ! horizontal && ! isMouseWheel ) {
						return;
					}

					var delta = horizontal ? event.deltaX : event.deltaY;

					if ( Math.abs( delta ) < 8 ) {
						return;
					}

					var now = Date.now();

					/* One gesture, one slide. */
					if ( now - lastWheelMove < 400 ) {
						if ( horizontal ) {
							event.preventDefault();
						}
						return;
					}

					var forward = delta > 0;
					var atEnd = ! splide.options.type || splide.options.type === 'slide';

					/* Let the page carry on once a non looping carousel runs out. */
					if ( atEnd ) {
						var lastIndex = splide.length - Math.ceil( splide.options.perPage || 1 );

						if ( ( forward && splide.index >= lastIndex ) || ( ! forward && splide.index <= 0 ) ) {
							return;
						}
					}

					lastWheelMove = now;
					event.preventDefault();
					splide.go( forward ? '+1' : '-1' );
				},
				{ passive: false }
			);
		}

		splide.mount( extensions );

		el._sbSplide = splide;
	}

	function run() {
		var nodes = document.querySelectorAll( '.sb-carousel[data-sb-carousel]' );

		Array.prototype.forEach.call( nodes, initCarousel );
	}

	/* Bricks calls this on load and after builder updates. */
	window.bricksSbbtCarousel = run;

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
} )();