/**
 * The progress popup, for anything that takes more than a moment.
 *
 * One look for every SocialBUMP Tweaks page and module, taken from SEO for AI's
 * rebuild popup: a centred box with the elapsed time in its corner, a title, a
 * progress bar, a bold status line and a grey line saying what is being worked
 * on now. It cannot be closed while it runs. When the work is done it shows a
 * report, a bold summary with the time taken and one line per item, failures
 * in red, and a Close button that reloads the page so everything on it catches
 * up with what just happened.
 *
 *   var job = SBTweaksProgress.open( 'Publishing' );
 *   job.step( 1, 3, 'Bricks Tweaks: 1.1.7' );    // bar, "1 of 3", current item
 *   job.status( 'Building the zip' );            // replace the bold line
 *   job.log( 'Bricks Tweaks 1.1.7', 'published' );              // a report line
 *   job.log( 'SEO for AI 1.1.6', 'did not publish: ...', true ); // a failure
 *   job.finish( 'Publishing complete' );         // report and Close
 *   job.fail( 'GitHub could not be reached' );   // stop, bar red, Close
 *
 * The current item is written as "Label: detail"; the label is shown in bold.
 * finish() takes an optional second argument, a function to run on Close in
 * place of the reload.
 */
( function () {
	'use strict';

	var panel = null;

	function q( text ) {
		return String.fromCharCode( 34 ) + text + String.fromCharCode( 34 );
	}

	function build() {
		if ( panel ) {
			return;
		}

		panel = document.createElement( 'div' );
		panel.className = 'sb-tweaks-job';
		panel.setAttribute( 'hidden', 'hidden' );
		panel.setAttribute( 'role', 'dialog' );
		panel.setAttribute( 'aria-modal', 'true' );
		panel.innerHTML =
			'<div class=' + q( 'sb-tweaks-job__box' ) + '>' +
			'<span class=' + q( 'sb-tweaks-job__timer' ) + '></span>' +
			'<h2 class=' + q( 'sb-tweaks-job__title' ) + '></h2>' +
			'<div class=' + q( 'sb-tweaks-job__track' ) + '><div class=' + q( 'sb-tweaks-job__bar' ) + '></div></div>' +
			'<p class=' + q( 'sb-tweaks-job__count' ) + '></p>' +
			'<p class=' + q( 'sb-tweaks-job__current' ) + '></p>' +
			'</div>';

		document.body.appendChild( panel );
	}

	function part( name ) {
		return panel.querySelector( '.sb-tweaks-job__' + name );
	}

	function clock( seconds ) {
		return seconds < 60 ? seconds + 's' : Math.floor( seconds / 60 ) + 'm ' + ( seconds % 60 ) + 's';
	}

	/** Seconds as the report says it: 2.8 seconds, or 1 minute 12 seconds. */
	function spoken( ms ) {
		var seconds = ms / 1000;

		if ( seconds < 60 ) {
			return ( Math.round( seconds * 10 ) / 10 ) + ' seconds';
		}

		var m = Math.floor( seconds / 60 );
		var s = Math.round( seconds % 60 );

		return m + ( m === 1 ? ' minute ' : ' minutes ' ) + s + ( s === 1 ? ' second' : ' seconds' );
	}

	/** "Label: detail", with the label in bold. */
	function labelled( node, text ) {
		var at = String( text ).indexOf( ': ' );

		node.textContent = '';

		if ( at === -1 ) {
			node.textContent = text;

			return;
		}

		var strong = document.createElement( 'strong' );

		strong.textContent = text.slice( 0, at );
		node.appendChild( strong );
		node.appendChild( document.createTextNode( text.slice( at ) ) );
	}

	function open( title ) {
		build();

		var started = Date.now();
		var lines   = [];
		var failed  = 0;
		var timer   = part( 'timer' );
		var ticking = window.setInterval( function () {
			timer.textContent = clock( Math.floor( ( Date.now() - started ) / 1000 ) );
		}, 1000 );

		part( 'title' ).textContent = title;
		part( 'bar' ).style.width = '0%';
		part( 'count' ).textContent = '';
		part( 'current' ).textContent = '';
		timer.textContent = '0s';
		panel.classList.remove( 'is-done', 'is-failed' );
		Array.prototype.forEach.call( panel.querySelectorAll( '.sb-tweaks-job__report, .sb-tweaks-job__close' ), function ( node ) {
			node.parentNode.removeChild( node );
		} );
		panel.removeAttribute( 'hidden' );

		function stop() {
			window.clearInterval( ticking );
			timer.textContent = clock( Math.floor( ( Date.now() - started ) / 1000 ) );
		}

		function close_button( then ) {
			var button = document.createElement( 'button' );

			button.type = 'button';
			button.className = 'button button-primary sb-tweaks-job__close';
			button.textContent = 'Close';
			button.addEventListener( 'click', function () {
				if ( typeof then === 'function' ) {
					panel.setAttribute( 'hidden', 'hidden' );
					then();

					return;
				}

				window.location.reload();
			} );

			part( 'box' ).appendChild( button );
			button.focus();
		}

		return {
			/** Move the bar and say where it is up to, and what is being done now. */
			step: function ( done, total, current ) {
				var percent = total > 0 ? Math.round( ( done / total ) * 100 ) : 100;

				part( 'bar' ).style.width = percent + '%';
				part( 'count' ).textContent = done + ' of ' + total;

				if ( current !== undefined ) {
					labelled( part( 'current' ), current );
				}
			},

			/** Replace the bold status line. */
			status: function ( text ) {
				part( 'count' ).textContent = text;
			},

			/** Replace the grey current item line. */
			current: function ( text ) {
				labelled( part( 'current' ), text );
			},

			/** A line for the report: what, and what happened to it. */
			log: function ( what, result, isFailure ) {
				lines.push( { what: what, result: result, bad: !! isFailure } );

				if ( isFailure ) {
					failed++;
				}
			},

			/** The report and Close. The bar fills; failures turn it red. */
			finish: function ( summary, then ) {
				stop();

				var report = document.createElement( 'div' );
				var head   = document.createElement( 'p' );
				var strong = document.createElement( 'strong' );
				var list   = document.createElement( 'ul' );

				report.className = 'sb-tweaks-job__report';
				strong.textContent = summary;
				head.className = 'sb-tweaks-job__summary';
				head.appendChild( strong );
				head.appendChild( document.createTextNode( ' in ' + spoken( Date.now() - started ) ) );
				report.appendChild( head );

				lines.forEach( function ( line ) {
					var item = document.createElement( 'li' );
					var what = document.createElement( 'strong' );

					if ( line.bad ) {
						item.className = 'is-failed';
					}

					what.textContent = line.what;
					item.appendChild( what );
					item.appendChild( document.createTextNode( ' ' + line.result ) );
					list.appendChild( item );
				} );

				if ( lines.length ) {
					report.appendChild( list );
				}

				part( 'bar' ).style.width = '100%';
				part( 'count' ).textContent = 'Finished';
				part( 'current' ).textContent = '';
				panel.classList.add( 'is-done' );

				if ( failed ) {
					panel.classList.add( 'is-failed' );
				}

				part( 'box' ).appendChild( report );
				close_button( then );
			},

			/** Stop where it is, with the reason, and let it be closed. */
			fail: function ( message, then ) {
				stop();
				panel.classList.add( 'is-failed' );
				part( 'count' ).textContent = message;
				part( 'current' ).textContent = '';
				close_button( then );
			}
		};
	}

	window.SBTweaksProgress = { open: open };
}() );
