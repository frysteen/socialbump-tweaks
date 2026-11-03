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
 *   job.busy();                                  // a spinner in place of the bar
 *   job.step( 1, 3, 'Bricks Tweaks: 1.1.7' );    // bar, "1 of 3", current item
 *   job.status( 'Building the zip' );            // replace the bold line
 *   job.log( 'Bricks Tweaks 1.1.7', 'published' );              // a report line
 *   job.log( 'SEO for AI 1.1.6', 'did not publish: ...', true ); // a failure
 *   job.finish( 'Publishing complete' );         // report and Close
 *   job.fail( 'GitHub could not be reached' );   // stop, bar red, Close
 *
 * A job that runs on the server in one request (Publish, say) can still show
 * where it is up to (Sam, 3 November 2026). The page makes a run id, sends it
 * with the job as sb_tweaks_run, and watches it; the job reports with
 * SB_Tweaks_Progress::set() as it goes, and the bar follows:
 *
 *   var run = SBTweaksProgress.id();
 *   job.watch( { ajax: ajaxurl, nonce: nonce, run: run } ); // asks every 0.7s
 *   job.progress( 40, 'Uploading', 'File: a.php', 55, 3 );   // what watch() does
 *
 * progress( percent, status, current, to, eta ) puts the bar at percent and
 * lets it creep on to to over eta seconds while the step runs, never past it
 * and never back. Watching stops by itself at finish() or fail().
 *
 * busy() is for one step with no telling how long it will take, such as
 * Publish release: a spinner turns in place of the bar until step(),
 * finish() or fail().
 *
 * The current item is written as "Label: detail"; the label is shown in bold.
 * finish() takes an optional second argument, a function to run on Close in
 * place of the reload.
 *
 * Stop (Sam, 1 November 2026): open( title, { stop: true } ) shows a Stop
 * button while it runs. Pressing it says the run will stop after the step
 * in hand, and job.stopping() turns true: check it before each step, and
 * finish() when it is. stop can also be a function, called once when
 * pressed (to tell a server to stop, say).
 *
 *   var job = SBTweaksProgress.open( 'Updating sites', { stop: true } );
 *   if ( job.stopping() ) { job.finish( 'Stopped after 3 of 9' ); }
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
			'<span class=' + q( 'sb-tweaks-job__spin' ) + ' aria-hidden=' + q( 'true' ) + '></span>' +
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

	function open( title, options ) {
		build();

		options = options || {};

		var started  = Date.now();
		var lines    = [];
		var failed   = 0;
		var stopping = false;
		var stopper  = null;
		var watching = null;
		var watched  = false;
		var timer   = part( 'timer' );
		var ticking = window.setInterval( function () {
			timer.textContent = clock( Math.floor( ( Date.now() - started ) / 1000 ) );
		}, 1000 );

		part( 'title' ).textContent = title;
		part( 'bar' ).style.width = '0%';
		part( 'count' ).textContent = '';
		part( 'current' ).textContent = '';
		timer.textContent = '0s';
		panel.classList.remove( 'is-done', 'is-failed', 'is-busy' );
		Array.prototype.forEach.call( panel.querySelectorAll( '.sb-tweaks-job__report, .sb-tweaks-job__close, .sb-tweaks-job__stop, .sb-tweaks-job__note' ), function ( node ) {
			node.parentNode.removeChild( node );
		} );
		panel.removeAttribute( 'hidden' );

		// Stop, while it runs: the step in hand finishes, nothing after it starts.
		// Pressed, it gives its place to a line saying so (the same height, so
		// nothing moves).
		var note = null;

		if ( options.stop ) {
			stopper = document.createElement( 'button' );
			stopper.type = 'button';
			stopper.className = 'button sb-tweaks-button--danger sb-tweaks-job__stop';
			stopper.textContent = 'Stop';

			note = document.createElement( 'p' );
			note.className = 'sb-tweaks-job__note';
			note.textContent = 'Stopping after this step. Anything already done is kept.';

			stopper.addEventListener( 'click', function () {
				if ( stopping ) {
					return;
				}

				stopping = true;
				stopper.parentNode.replaceChild( note, stopper );

				if ( typeof options.stop === 'function' ) {
					options.stop();
				}
			} );
			part( 'box' ).appendChild( stopper );
		}

		function stop() {
			[ stopper, note ].forEach( function ( node ) {
				if ( node && node.parentNode ) {
					node.parentNode.removeChild( node );
				}
			} );

			// No more asking the server, and the bar stays where it is on screen
			// (it may have been creeping on towards the end of a step).
			if ( watching !== null ) {
				window.clearTimeout( watching );
				watching = null;
			}

			var bar   = part( 'bar' );
			var track = part( 'track' ).getBoundingClientRect().width;

			if ( track ) {
				bar.style.transition = 'none';
				bar.style.width = ( bar.getBoundingClientRect().width / track * 100 ) + '%';
				void bar.offsetWidth;
			}

			bar.style.transition = '';

			panel.classList.remove( 'is-busy' );
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

		var api = {
			/** True once Stop has been pressed: start nothing more, and finish(). */
			stopping: function () {
				return stopping;
			},

			/** No count to show: a spinner in place of the bar until step(), finish() or fail(). */
			busy: function () {
				panel.classList.add( 'is-busy' );
			},

			/** Move the bar and say where it is up to, and what is being done now. */
			step: function ( done, total, current ) {
				var percent = total > 0 ? Math.round( ( done / total ) * 100 ) : 100;

				panel.classList.remove( 'is-busy' );

				part( 'bar' ).style.width = percent + '%';
				part( 'count' ).textContent = done + ' of ' + total;

				if ( current !== undefined ) {
					labelled( part( 'current' ), current );
				}
			},

			/**
			 * Put the bar at percent, with the bold status line and the grey
			 * current line (either can be left out). With to and eta the bar creeps
			 * on to to over eta seconds while the step runs, never past it. It
			 * never goes back.
			 */
			progress: function ( percent, status, current, to, eta ) {
				var bar  = part( 'bar' );
				var from = Math.max( parseFloat( bar.style.width ) || 0, Math.min( 100, Number( percent ) || 0 ) );

				panel.classList.remove( 'is-busy' );
				bar.style.transition = 'none';
				bar.style.width = from + '%';
				void bar.offsetWidth;

				if ( Number( to ) > from && Number( eta ) > 0 ) {
					bar.style.transition = 'width ' + Number( eta ) + 's linear';
					bar.style.width = Math.min( 100, Number( to ) ) + '%';
				} else {
					bar.style.transition = '';
				}

				if ( status !== undefined && status !== null ) {
					part( 'count' ).textContent = status;
				}

				if ( current !== undefined && current !== null ) {
					labelled( part( 'current' ), current );
				}
			},

			/**
			 * Follow a job on the server (SB_Tweaks_Progress): ask o.ajax every
			 * o.every milliseconds (700) what run o.run last said, and show it
			 * with progress(). o.nonce is SB_Tweaks_Progress::client()'s.
			 */
			watch: function ( o ) {
				var seen = '';

				if ( ! o || ! o.ajax || ! o.run || ! window.fetch || ! window.FormData ) {
					return;
				}

				function ask() {
					var body = new FormData();

					body.append( 'action', o.action || 'sb_tweaks_progress' );
					body.append( 'nonce', o.nonce || '' );
					body.append( 'run', o.run );

					window.fetch( o.ajax, { method: 'POST', body: body, credentials: 'same-origin' } )
						.then( function ( response ) {
							return response.json();
						} )
						.then( function ( res ) {
							var d   = res && res.success ? res.data : null;
							var key = d ? [ d.at, d.status, d.current ].join( '|' ) : '';

							if ( watching === null || ! d || key === seen ) {
								return;
							}

							seen = key;
							api.progress( d.from, d.status, d.current, d.to, d.eta );
						} )
						.catch( function () {} )
						.then( function () {
							if ( watching !== null ) {
								watching = window.setTimeout( ask, o.every || 700 );
							}
						} );
				}

				watched  = true;
				watching = window.setTimeout( ask, 300 );
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

				// Stopped short, or a job on the server (watch()) that failed part way:
				// the bar stays where it got to.
				var cut = ( stopping || ( watched && failed > 0 ) ) && part( 'bar' ).style.width !== '100%';

				if ( ! cut ) {
					part( 'bar' ).style.width = '100%';
				}

				part( 'count' ).textContent = cut ? 'Stopped' : 'Finished';
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

		return api;
	}

	/** A run id for watch(): letters and numbers, different every time. */
	function id() {
		return Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 10 );
	}

	window.SBTweaksProgress = { open: open, id: id };
}() );
