/**
 * Image Management: SB Image Settings, SB Image Cleaner and the sizes panel on
 * each image in the media library.
 *
 * Moved out of Image Management's admin.js. Popups are the framework's own
 * (SBTweaksConfirm, from confirm.js): ask() and confirmed() for a question
 * before a click goes through, tell() for a note with only OK, and open() for
 * anything else, such as the summary at the end of a run.
 */
( function ( $ ) {
	'use strict';

	var popup = window.SBTweaksConfirm;

	function ask( element, options ) {
		return popup.ask( element, options );
	}

	function confirmed( element ) {
		return popup.confirmed( element );
	}

	function tell( options ) {
		return popup.tell( options );
	}

	function openModal( options, onAction ) {
		return popup.open( options, onAction );
	}

	$( function () {
		if ( ! popup ) {
			return;
		}

		// SB Image Settings: the list of widths.
		var $widths = $( '#sb-images-widths' );

		if ( $widths.length ) {
			$( '#sb-images-add-width' ).on( 'click', function () {
				var $row = $widths.find( '.sb-images-width' ).last().clone();

				if ( ! $row.length ) {
					return;
				}

				unlockRow( $row );
				$row.find( 'input[type="number"]' ).val( '' );
				$row.find( '.sb-images-width__value' ).text( '' );
				$row.removeClass( 'is-default is-duplicate' ).find( '.sb-images-width__default, .sb-images-width__clash, .sb-images-width__dupe' ).remove();
				$widths.append( $row );
				$row.find( 'input' ).trigger( 'focus' );
			} );

			$widths.on( 'click', '.sb-images-width__remove', function () {
				$( this ).closest( '.sb-images-width' ).remove();
			} );

			// The standard widths: marked in the list, and added back by Add default sizes.
			var defaults   = ( $widths.data( 'defaults' ) || [] ).map( Number );
			var $addDefaults = $( '#sb-images-add-defaults' );
			var $blank     = $widths.find( '.sb-images-width' ).first().clone();

			unlockRow( $blank );
			$blank.removeClass( 'is-duplicate is-default' ).find( '.sb-images-width__clash, .sb-images-width__dupe, .sb-images-width__default' ).remove();

			function listed() {
				return $widths.find( 'input[type="number"]' ).map( function () {
					return this.value === '' ? null : Number( this.value );
				} ).get();
			}

			function missingDefaults() {
				var have = listed();

				return defaults.filter( function ( width ) {
					return have.indexOf( width ) === -1;
				} );
			}

			function syncDefaults() {
				$addDefaults.prop( 'disabled', ! missingDefaults().length );
			}

			function markDefault( $row, value ) {
				var isDefault = value !== '' && defaults.indexOf( Number( value ) ) !== -1;

				$row.toggleClass( 'is-default', isDefault ).find( '.sb-images-width__default' ).remove();

				if ( isDefault ) {
					$row.find( '.sb-images-width__value' ).after( '<span class="sb-images-width__default">default size</span>' );
				}
			}

			$widths.on( 'click', '.sb-images-width__remove', syncDefaults );

			$widths.on( 'input', 'input[type="number"]', function () {
				markDefault( $( this ).closest( '.sb-images-width' ), this.value );
				syncDefaults();
			} );

			// Adds only the standard widths that are not in the list, each in order.
			// Nothing is saved until Save changes, like Add a width.
			$addDefaults.on( 'click', function () {
				if ( ! $blank.length ) {
					return;
				}

				missingDefaults().forEach( function ( width ) {
					var $row    = $blank.clone();
					var $before = $widths.find( '.sb-images-width' ).filter( function () {
						var value = $( this ).find( 'input[type="number"]' ).val();

						return value !== '' && Number( value ) > width;
					} ).first();

					$row.find( 'input[type="number"]' ).val( width );

					if ( $before.length ) {
						$row.insertBefore( $before );
					} else {
						$widths.append( $row );
					}

					// A real input event, so the name, the marks and the save button all follow.
					$row.find( 'input[type="number"]' )[0].dispatchEvent( new Event( 'input', { bubbles: true } ) );
				} );

				syncDefaults();
			} );

			syncDefaults();


			$widths.on( 'input', 'input[type="number"]', function () {
				var $row  = $( this ).closest( '.sb-images-width' );
				var value = this.value;

				$row.find( '.sb-images-width__value' ).text( value );
				$row.find( '.sb-images-lock input' ).val( 'image-' + value );

				// Flag a width that is already in the list rather than silently merging it.
				var seen = 0;

				$widths.find( 'input[type="number"]' ).each( function () {
					if ( this.value !== '' && this.value === value ) {
						seen++;
					}
				} );

				$row.toggleClass( 'is-duplicate', seen > 1 );
				$row.find( '.sb-images-width__dupe' ).remove();

				if ( seen > 1 ) {
					$row.find( '.sb-images-width__name' ).after( '<span class="sb-images-width__dupe">already in the list</span>' );
				}
			} );
		}

		// SB Image Settings: scan first, then offer only the jobs that are worth doing.
		var $rebuild = $( '#sb-images-rebuild' );

		if ( $rebuild.length ) {
			var $scan   = $( '#sb-images-scan' );
			var $build  = $( '#sb-images-rebuild-run' );
			var $clean  = $( '#sb-images-rebuild-clean' );
			var $orphan = $( '#sb-images-orphans-run' );
			var $force  = $( '#sb-images-force-wrap' );
			var $panel  = $( '#sb-images-report' );
			var $status = $rebuild.find( '.sb-images-rebuild__status' );
			var $progress = $( '#sb-images-progress' );
			var $barWrap  = $( '#sb-images-rebuild-bar' );
			var $bar    = $barWrap.find( 'span' );
			var totals  = { built: 0, removed: 0, files: 0 };
			var lastMissing = 0;
			var lastOrphans = 0;
			var nonce   = $rebuild.data( 'nonce' );

			// Which run is going, for the title of the summary that pops up at the end.
			var jobTitle = '';

			/** Show the progress box for a new run, or for watching another window's. */
			function openJob( title ) {
				jobTitle = title;
				$progress.removeClass( 'is-done is-failed' ).prop( 'hidden', false );
				$( '#sb-images-progress-log' ).empty();
				$bar.css( 'width', '0%' );
			}

			/** Stop where it is, the bar red, with the reason. */
			function failJob( text ) {
				stopClock();
				$progress.addClass( 'is-failed' );
				$status.text( text );
			}

			/**
			 * One token per run. The server holds a site wide lock while a long job
			 * runs and refuses requests from any window without that token,
			 * because every window shares the held image lists.
			 */
			var jobToken = '';

			function newJob() {
				jobToken = Math.random().toString( 36 ).slice( 2 ) + Date.now().toString( 36 );
			}

			function releaseJob() {
				if ( jobToken ) {
					$.post( ajaxurl, { action: 'sb_images_release', nonce: nonce, job: jobToken } );
				}

				jobToken = '';
			}

			/** This window's run was stopped from another window. */
			function stoppedIn( response ) {
				return !! ( response && ! response.success && response.data && response.data.stopped );
			}

			/**
			 * Leaving the page ends the run, so the lock is let go of straight away
			 * instead of shutting out other windows, or this one come back to, until
			 * it lapses. leaving also stops a batch still running on the server from
			 * taking the lock back when it finishes.
			 */
			window.addEventListener( 'pagehide', function () {
				if ( ! jobToken || ! navigator.sendBeacon ) {
					return;
				}

				var data = new FormData();

				data.append( 'action', 'sb_images_release' );
				data.append( 'nonce', nonce );
				data.append( 'job', jobToken );
				data.append( 'leaving', '1' );
				navigator.sendBeacon( ajaxurl, data );
			} );

			/** The job another window is running, when the server refused for that reason. */
			function busyIn( response ) {
				return response && ! response.success && response.data && response.data.busy ? response.data.busy : null;
			}

			function buttons( on ) {
				// Not the progress box's own controls: Cancel has to stay clickable while
				// a run is going, which is the only time it is on screen.
				$rebuild.find( 'button' ).not( '#sb-images-progress-cancel, #sb-images-progress-close, #sb-images-progress-takeover' ).prop( 'disabled', ! on );

				if ( on ) {
					refreshBuild();
				}
			}

			function scan( quiet ) {
				buttons( false );

				if ( ! quiet ) {
					$panel.prop( 'hidden', false ).html( '<p>Looking through the library...</p>' );

					// Asking for a normal scan means the leftovers list is last question's
					// answer. A quiet scan after a deletion keeps it, because that run
					// redraws it with what is left.
					$( '#sb-images-deep-panel' ).prop( 'hidden', true ).empty();
				}

				return $.post( ajaxurl, { action: 'sb_images_report', nonce: nonce, job: jobToken } ).done( function ( response ) {
					var busy = busyIn( response );

					if ( busy ) {
						$panel.prop( 'hidden', true ).empty();
						watch( busy );
						return;
					}

					buttons( true );

					if ( ! response || ! response.success ) {
						$panel.html( '<p>The scan could not be run.</p>' );
						return;
					}

					var data = response.data;

					$panel.prop( 'hidden', false ).html( data.html );
					lastMissing = data.missing;
					$build.prop( 'hidden', false );
					refreshBuild();
					$clean.prop( 'hidden', data.stale < 1 );
					lastOrphans = ( typeof data.deletable === 'number' ) ? data.deletable : data.orphans;
					$orphan.prop( 'hidden', lastOrphans < 1 );
					$force.prop( 'hidden', false );
					$scan.text( 'Scan again' );
				} );
			}

			var retried = false;

			/**
			 * How long the run has been going, and how long each image is taking.
			 * The average is the useful half: it says whether a big library will
			 * take two minutes or twenty.
			 */
			var $timer  = $( '#sb-images-progress-timer' );
			var $cancel = $( '#sb-images-progress-cancel' );
			var $close  = $( '#sb-images-progress-close' );
			var $takeover = $( '#sb-images-progress-takeover' );
			var started = 0;
			var ticking = null;
			var seenNow = 0;
			var stopped = false;

			function spell( seconds ) {
				if ( seconds < 60 ) {
					return seconds + 's';
				}

				return Math.floor( seconds / 60 ) + 'm ' + ( seconds % 60 ) + 's';
			}

			function elapsed() {
				return Math.max( 0, Math.round( ( Date.now() - started ) / 1000 ) );
			}

			function paintTimer() {
				var seconds = elapsed();
				var text    = spell( seconds );

				if ( seenNow > 0 && seconds > 0 ) {
					text += ' | ' + ( Math.round( ( seconds / seenNow ) * 100 ) / 100 ) + 's per image';
				}

				$timer.text( text );
			}

			function startClock() {
				stopped = false;
				started = Date.now();
				seenNow = 0;

				window.clearInterval( ticking );
				paintTimer();
				ticking = window.setInterval( paintTimer, 1000 );

				$cancel.prop( 'hidden', false );
				$close.prop( 'hidden', true );
			}

			/** Called however a run ends: finished, cancelled or failed. */
			function stopClock() {
				window.clearInterval( ticking );
				ticking = null;
				paintTimer();
				$cancel.prop( 'hidden', true );
				$close.prop( 'hidden', false );
			}

			/**
			 * A job running in another window, or for another person. This window
			 * only watches: the counts are read from the server every few seconds
			 * and the buttons stay off until the job has finished. The per image
			 * log lives in the other window and is not repeated here.
			 */
			var watching = null;

			function paintWatch( busy ) {
				var total = busy.total || 0;
				var done  = Math.min( busy.done || 0, total );
				var line  = 'In another window, started by ' + busy.user + '. ';

				$bar.css( 'width', ( total ? Math.round( ( done / total ) * 100 ) : 0 ) + '%' );
				$status.text( line + ( total ? done + ' of ' + total + '. ' + ( busy.current || '' ) : 'Starting...' ) );
				$timer.text( spell( busy.elapsed ) + ( done > 0 && busy.elapsed > 0 ? ' | ' + ( Math.round( ( busy.elapsed / done ) * 100 ) / 100 ) + 's per ' + busy.unit : '' ) );
			}

			function pollWatch() {
				$.post( ajaxurl, { action: 'sb_images_status', nonce: nonce } ).done( function ( response ) {
					var busy = ( response && response.success ) ? response.data.busy : null;

					if ( busy ) {
						paintWatch( busy );
						return;
					}

					window.clearInterval( watching );
					watching = null;
					$bar.css( 'width', '100%' );
					$progress.addClass( 'is-done' );
					$status.text( 'Finished in the other window.' );
					$takeover.prop( 'hidden', true );
					$close.prop( 'hidden', false );
					buttons( true );
				} );
			}

			function watch( busy ) {
				buttons( false );
				openJob( busy.label );
				window.clearInterval( ticking );
				ticking = null;
				$cancel.prop( 'hidden', true );
				$close.prop( 'hidden', true );
				$takeover.prop( { hidden: false, disabled: false } );
				paintWatch( busy );

				if ( ! watching ) {
					watching = window.setInterval( pollWatch, 3000 );
				}
			}

			// Stops the run another window holds, or one left behind by a page that
			// was closed part way. Anything it has done already stays done.
			$takeover.on( 'click', function () {
				if ( ! confirmed( this ) ) {
					ask( this, { title: 'Stop that run', message: 'Stop the run in the other window? Anything it has already done is kept.', action: 'Stop it' } );
					return;
				}

				$takeover.prop( 'disabled', true );

				$.post( ajaxurl, { action: 'sb_images_stop', nonce: nonce } ).done( function ( response ) {
					if ( ! response || ! response.success ) {
						$takeover.prop( 'disabled', false );
						return;
					}

					window.clearInterval( watching );
					watching = null;
					$takeover.prop( 'hidden', true );
					$progress.addClass( 'is-done' );
					$status.text( 'Stopped. Anything it had already done has been kept.' );
					$close.prop( 'hidden', false );
					buttons( true );
				} ).fail( function () {
					$takeover.prop( 'disabled', false );
				} );
			} );

			$cancel.on( 'click', function () {
				// The batch in flight finishes; nothing after it is asked for.
				stopped = true;
				$cancel.prop( 'disabled', true );
				$status.text( 'Stopping after this batch...' );
			} );

			/** How long a run took, as the summary says it: 42 seconds, 3 minutes 5 seconds. */
			function spoken( seconds ) {
				if ( seconds < 60 ) {
					return seconds + ( seconds === 1 ? ' second' : ' seconds' );
				}

				var m = Math.floor( seconds / 60 );
				var s = seconds % 60;

				return m + ( m === 1 ? ' minute ' : ' minutes ' ) + s + ( s === 1 ? ' second' : ' seconds' );
			}

			/**
			 * The end of a run. The progress box keeps its last line, as it always
			 * has, and a summary pops up: Finished or Stopped, the summary in bold
			 * with the time it took, Close, and Scan again for a fresh scan. The X,
			 * Escape and a click outside close it too. extra, when given, goes in
			 * the report under that line: the files a delete left alone.
			 */
			function finished( text, extra ) {
				var parts   = /^(Finished|Stopped)\.\s*(.*)$/.exec( text );
				var seconds = elapsed();
				var rate    = seenNow > 0 && seconds > 0 ? ', ' + ( Math.round( ( seconds / seenNow ) * 100 ) / 100 ) + 's per image' : '';
				var $body   = $( '<div class="sb-images-summary"></div>' );
				var $line   = $( '<p class="sb-tweaks-job__summary"></p>' );

				stopClock();

				if ( ! parts || 'Finished' === parts[1] ) {
					$bar.css( 'width', '100%' );
				}

				$status.text( text + ' Took ' + spell( seconds ) + rate + '.' );
				$cancel.prop( 'disabled', false );

				$line.append( $( '<strong></strong>' ).text( ( parts ? parts[2] : text ).replace( /\.$/, '' ) ), document.createTextNode( ' in ' + spoken( seconds ) + rate + '.' ) );
				$body.append( $( '<p class="sb-images-summary__head"></p>' ).text( parts ? parts[1] : 'Finished' ), $( '<div class="sb-tweaks-job__report"></div>' ).append( $line, extra || '' ) );

				openModal( { title: jobTitle || 'Finished', body: $body, cancel: 'Close', action: 'Scan again' }, function () {
					$progress.prop( 'hidden', true );
					scan( false );
				} );
			}

			function batch( offset, total, force, mode, sizes ) {
				$.post( ajaxurl, { action: 'sb_images_batch', nonce: nonce, job: jobToken, offset: offset, force: force ? 1 : 0, mode: mode, sizes: sizes || [], batch: ( retried || force ) ? 1 : 5 } ).done( function ( response ) {
					if ( ! response || ! response.success ) {
						stopClock();

						if ( stoppedIn( response ) ) {
							jobToken = '';
							$status.text( 'Stopped from another window. Anything already done has been kept.' );
							buttons( true );
							return;
						}

						var busy = busyIn( response );

						if ( busy ) {
							jobToken = '';
							watch( busy );
							return;
						}

						releaseJob();
						failJob( 'Something went wrong. Try again.' );
						buttons( true );
						return;
					}

					var data = response.data;

					totals.built += data.built;
					totals.files += data.files;

					// The server knows how many the run really covers; a plain build
					// skips the images that need nothing.
					if ( data.total ) {
						total = data.total;
					}

					var done = Math.min( data.offset, total );

					seenNow = done;
					paintTimer();

					$bar.css( 'width', ( total ? Math.round( ( done / total ) * 100 ) : 100 ) + '%' );
					$status.text( done + ' of ' + total + ' checked.' );

					if ( data.items && data.items.length ) {
						var $log = $( '#sb-images-progress-log' );

						/**
						 * One image to a row, with its own thumbnail and the sizes built for
						 * it underneath. A batch does several images at once, so a single
						 * thumbnail beside a list of names never matched what you were
						 * reading.
						 */
						data.items.forEach( function ( item ) {
							// Made and skipped in one list, smallest size first.
							var sizes = ( item.rows || [] ).map( function ( row ) {
								if ( row.made ) {
									return '<li><span class="sb-images-tick">&#10003;</span>' + row.name + '</li>';
								}

								return '<li class="is-skipped"><span class="sb-images-tick sb-images-tick--skip">&#10005;</span>' + row.name + ' <em>' + row.why + '</em></li>';
							} ).join( '' );

							var thumb = item.thumb ? '<img class="sb-images-log__thumb" src="' + item.thumb + '" alt="">' : '<span class="sb-images-log__thumb is-empty"></span>';
							var dims  = item.dims ? '<span class="sb-images-log__dims">' + item.dims + '</span>' : '';

							$log.prepend( '<li class="sb-images-log__row">' + thumb + '<div class="sb-images-log__detail"><strong>' + item.name + '</strong>' + dims + '<ul>' + sizes + '</ul></div></li>' );
						} );

						// The whole run stays in the list; the box scrolls.
					}

					if ( data.last && data.last.name ) {
						$status.text( done + ' of ' + total + ' checked. ' + data.last.name );
					}

					if ( data.done || done >= total || stopped ) {
						finished( ( stopped && ! data.done && done < total ? 'Stopped. ' : 'Finished. ' ) + totals.built + ' sizes built, ' + totals.files + ' files removed.' );

						// A forced run is a deliberate act, so it does not stay armed.
						if ( force ) {
							$( '#sb-images-rebuild-force' ).prop( 'checked', false );
						}

						scan( true );
						releaseJob();
						return;
					}

					batch( data.offset, total, force, mode, sizes );
				} ).fail( function () {
					/**
					 * One request failing is usually the host, not the work.
					 *
					 * Building several sizes from a large original can run long enough to
					 * be cut off. Nothing is lost when that happens, because each request
					 * starts where the last one finished, so the same batch is tried again
					 * one image at a time. Only a second failure is worth reporting.
					 */
					if ( ! retried ) {
						retried = true;

						$status.text( 'That took too long. Trying again, one at a time.' );
						batch( offset, total, force, mode, sizes );

						return;
					}

					stopClock();
					releaseJob();
					failJob( 'The server did not answer. Anything already built has been kept, so you can start again from here.' );
					buttons( true );
				} );
			}

			function start( mode, force, sizes ) {
				totals = { built: 0, removed: 0, files: 0 };
				newJob();

				buttons( false );
				openJob( force ? 'Rebuilding all thumbnails' : ( 'clean' === mode ? 'Removing old sizes' : 'Building thumbnails' ) );
				$status.text( 'Working...' );
				startClock();

				$.post( ajaxurl, { action: 'sb_images_count', nonce: nonce, job: jobToken, mode: mode, force: force ? 1 : 0 } ).done( function ( response ) {
					var busy = busyIn( response );

					if ( busy ) {
						stopClock();
						jobToken = '';
						watch( busy );
						return;
					}

					var total = ( response && response.success ) ? response.data.total : 0;

					if ( ! total ) {
						stopClock();
						releaseJob();
						$progress.addClass( 'is-done' );
						$status.text( 'No images found.' );
						buttons( true );
						return;
					}

					batch( 0, total, force, mode, sizes );
				} );
			}

			function refreshBuild() {
				var force = $( '#sb-images-rebuild-force' ).prop( 'checked' );

				// A forced run remakes the saved sizes; a plain build needs something missing.
				$build.prop( 'disabled', force ? savedCount < 1 : lastMissing < 1 );
			}

			$( '#sb-images-rebuild-force' ).on( 'change', refreshBuild );

			/**
			 * The size list on the left is a plain form, so save-state.js handles the
			 * button, the reminder and the warning on leaving. These only tick the
			 * boxes, and fire change so it notices.
			 */
			var $sizes     = $( '#sb-images-cleaner-sizes' );
			var savedCount = $sizes.find( '.sb-images-size-choice:checked' ).length;

			/**
			 * A real DOM event, not jQuery's: save-state.js listens with
			 * addEventListener, and a jQuery trigger never reaches it, so the save
			 * button sat disabled while the ticks plainly changed.
			 */
			function ticked( $boxes, on ) {
				$boxes.prop( 'checked', on );

				if ( $boxes.length ) {
					$boxes[0].dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
			}

			$sizes.on( 'click', '[data-sizes-all], [data-sizes-none]', function () {
				ticked( $( this ).closest( '.sb-images-sizegroup' ).find( '.sb-images-size-choice' ), this.hasAttribute( 'data-sizes-all' ) );
			} );

			$( '#sb-images-sizes-all' ).on( 'click', function () {
				ticked( $sizes.find( '.sb-images-size-choice' ), true );
			} );

			$( '#sb-images-sizes-none' ).on( 'click', function () {
				ticked( $sizes.find( '.sb-images-size-choice' ), false );
			} );

			/**
			 * Deep scan: the files on disk whose dimensions no registered size
			 * would produce. Its own button, its own panel, and nothing is
			 * deleted without ticking a set of dimensions and confirming.
			 */
			var $deep = $( '#sb-images-deep-panel' );

			function deepButtons() {
				$deep.find( '#sb-images-deep-remove' ).prop( 'disabled', $deep.find( '.sb-images-deep-choice:checked' ).length < 1 );
			}

			$( '#sb-images-deep' ).on( 'click', function () {
				var $button = $( this );

				$button.prop( 'disabled', true );
				$deep.prop( 'hidden', false ).html( '<p class="sb-images-deep__working">Looking through the uploads folder...</p>' );

				$.post( ajaxurl, { action: 'sb_images_deep', nonce: nonce, job: jobToken } ).done( function ( response ) {
					var busy = busyIn( response );

					if ( busy ) {
						$deep.prop( 'hidden', true ).empty();
						watch( busy );
						return;
					}

					$button.prop( 'disabled', false );

					if ( response && response.success ) {
						$deep.html( response.data.html );
						deepButtons();

						return;
					}

					$deep.html( '<p class="sb-images-deep__working">Something went wrong. Nothing was changed.</p>' );
				} ).fail( function () {
					$button.prop( 'disabled', false );
					$deep.html( '<p class="sb-images-deep__working">The server did not answer. Nothing was changed.</p>' );
				} );
			} );

			$deep.on( 'click', '.sb-images-deep__close', function () {
				$deep.prop( 'hidden', true ).empty();
			} );

			$deep.on( 'change', '.sb-images-deep-choice', deepButtons );

			$deep.on( 'click', '.sb-images-deep-show', function () {
				var $files = $( this ).closest( 'li' ).find( '.sb-images-deep__files' );
				var shut   = $files.prop( 'hidden' );

				$files.prop( 'hidden', ! shut );
				$( this ).text( shut ? 'Hide files' : 'Show files' );
			} );

			$deep.on( 'click', '#sb-images-deep-all, #sb-images-deep-none', function () {
				$deep.find( '.sb-images-deep-choice' ).prop( 'checked', this.id === 'sb-images-deep-all' );
				deepButtons();
			} );

			$deep.on( 'click', '#sb-images-deep-remove', function () {
				var dims  = [];
				var files = 0;

				$deep.find( '.sb-images-deep-choice:checked' ).each( function () {
					dims.push( this.value );
					files += parseInt( $( this ).closest( 'li' ).find( '.sb-images-deep__row span' ).text(), 10 ) || 0;
				} );

				if ( ! dims.length ) {
					return;
				}

				if ( ! confirmed( this ) ) {
					ask( this, { title: 'Delete leftover thumbnails', message: 'Delete ' + files + ' file' + ( files === 1 ? '' : 's' ) + ' at ' + dims.length + ' size' + ( dims.length === 1 ? '' : 's' ) + '? This cannot be undone.', action: 'Delete' } );
					return;
				}

				var removed = 0;
				var freed   = 0;
				var skipped = [];

				// Fixed from the first answer. The server counts the files still left
				// each time, which shrinks as they go, so measuring the bar against
				// that filled it by about halfway.
				var runTotal = 0;

				// The same progress popup as the rebuild.
				buttons( false );
				openJob( 'Deleting leftover thumbnails' );
				$status.text( 'Deleting...' );
				startClock();

				// The files left alone, in the summary that pops up at the end. A
				// long list scrolls inside the popup.
				function leftAlone() {
					if ( ! skipped.length ) {
						return '';
					}

					var note = '<div class="sb-images-summary__skipped"><p>' + skipped.length + ' file' + ( skipped.length === 1 ? ' was' : 's were' ) + ' left alone:</p><ul>';

					skipped.slice( 0, 100 ).forEach( function ( skip ) {
						// why_html is built and escaped server side, and carries the edit links.
						note += '<li><code>' + $( '<span>' ).text( skip.name ).html() + '</code> <span>' + ( skip.why_html || $( '<span>' ).text( skip.why ).html() ) + '</span></li>';
					} );

					if ( skipped.length > 100 ) {
						note += '<li class="sb-images-deep__more">and ' + ( skipped.length - 100 ) + ' more</li>';
					}

					return note + '</ul></div>';
				}

				/**
				 * One batch per request, so a big clean-up never sits in one call
				 * long enough to hit a time limit. Skipped files come back with
				 * their reasons and are shown once it finishes.
				 */
				function batch( offset ) {
					$.post( ajaxurl, { action: 'sb_images_deep_remove', nonce: nonce, job: jobToken, dims: dims, offset: offset, seen: removed + skipped.length, run_total: runTotal } ).done( function ( response ) {
						var busy = busyIn( response );

						if ( busy ) {
							jobToken = '';
							$deep.prop( 'hidden', true ).empty();
							watch( busy );
							return;
						}

						if ( stoppedIn( response ) ) {
							jobToken = '';
							stopClock();
							$status.text( 'Stopped from another window. ' + removed + ' files had already been removed.' );
							buttons( true );
							return;
						}

						if ( ! response || ! response.success ) {
							releaseJob();
							failJob( 'Something went wrong. ' + removed + ' files had already been removed.' );
							buttons( true );
							return;
						}

						var data = response.data;

						removed += data.removed;
						freed   += data.bytes;
						skipped  = skipped.concat( data.skipped || [] );

						if ( ! runTotal ) {
							runTotal = data.total || files;
						}

						var seen = Math.min( removed + skipped.length, runTotal );

						seenNow = removed;
						paintTimer();
						$bar.css( 'width', ( runTotal ? Math.round( ( seen / runTotal ) * 100 ) : 0 ) + '%' );
						$status.text( seen + ' of ' + runTotal + ' checked, ' + removed + ' removed.' );

						if ( ! data.done && ! stopped ) {
							batch( data.offset );
							return;
						}

						finished( ( data.done ? 'Finished. ' : 'Stopped. ' ) + removed + ' file' + ( removed === 1 ? '' : 's' ) + ' removed, ' + Math.round( freed / 1048576 * 10 ) / 10 + ' MB freed.', leftAlone() );

						if ( data.html ) {
							$deep.html( data.html );
							deepButtons();
						} else {
							$deep.prop( 'hidden', true ).empty();
						}

						scan( true );
						releaseJob();
					} ).fail( function () {
						releaseJob();
						failJob( 'The server did not answer. ' + removed + ' files had already been removed.' );
						buttons( true );
					} );
				}

				newJob();
				batch( 0 );
			} );

			// The Sizes to build figure opens the list of images behind it.
			$panel.on( 'click', '.sb-images-stat.is-openable', function () {
				var $list = $panel.find( '#' + $( this ).data( 'opens' ) );
				var shut  = $list.prop( 'hidden' );

				$list.prop( 'hidden', ! shut );
				$( this ).find( '.sb-images-stat__more' ).text( shut ? 'Hide images' : 'Show images' );
			} );

			/**
			 * Rebuild by an image name does every size it is missing; rebuild by a
			 * size does that one. The row keeps up with what is left, and goes when
			 * there is nothing left to build.
			 */
			$panel.on( 'click', '.sb-images-fix', function () {
				var $button = $( this );
				var $row    = $button.closest( '.sb-images-missing__row' );
				var size    = $button.data( 'size' );

				$row.find( '.sb-images-fix' ).prop( 'disabled', true );

				$.post( ajaxurl, { action: 'sb_images_fix', nonce: nonce, job: jobToken, id: $button.data( 'id' ), sizes: size ? [ size ] : [] } ).done( function ( response ) {
					if ( busyIn( response ) ) {
						watch( busyIn( response ) );
						return;
					}

					if ( ! response || ! response.success ) {
						$row.find( '.sb-images-fix' ).prop( 'disabled', false );

						return;
					}

					var left = response.data.left || [];

					if ( ! left.length ) {
						$row.remove();
						scan( true );

						return;
					}

					$row.find( 'li[data-size]' ).each( function () {
						if ( left.indexOf( $( this ).data( 'size' ) ) === -1 ) {
							$( this ).remove();
						}
					} );

					$row.find( '.sb-images-fix' ).prop( 'disabled', false );
					scan( true );
				} ).fail( function () {
					$row.find( '.sb-images-fix' ).prop( 'disabled', false );
				} );
			} );

			// Leftover sizes from other sources: tick or untick the whole list.
			$panel.on( 'click', '[data-sb-images-foreign]', function () {
				var $boxes = $( this ).closest( 'form' ).find( 'input[name="sb_images_adopt[]"]' );

				$boxes.prop( 'checked', this.getAttribute( 'data-sb-images-foreign' ) === 'all' );

				if ( $boxes.length ) {
					$boxes[0].dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
			} );

			$panel.on( 'click', '#sb-images-show-all', function () {
				$panel.find( 'tr.is-extra' ).removeClass( 'is-extra' );
				$( this ).closest( 'tr' ).remove();
			} );

			$panel.on( 'click', '.sb-images-report__keep', function () {
				var $button = $( this );

				$button.prop( 'disabled', true );

				$.post( ajaxurl, { action: 'sb_images_keep', nonce: nonce, file: $button.data( 'file' ), keep: $button.data( 'keep' ) } ).done( function () {
					scan( true );
				} );
			} );

			// An orphaned original goes back in the library where it is. The rescan
			// then drops it, and its thumbnails, from the orphan list.
			$panel.on( 'click', '.sb-images-report__add', function () {
				var $button = $( this );

				$button.prop( 'disabled', true ).text( 'Adding...' );

				$.post( ajaxurl, { action: 'sb_images_orphan_add', nonce: nonce, job: jobToken, file: $button.data( 'file' ) } ).done( function ( response ) {
					if ( busyIn( response ) ) {
						$button.prop( 'disabled', false ).text( 'Add to library' );
						watch( busyIn( response ) );
						return;
					}

					if ( response && response.success ) {
						scan( true );
						return;
					}

					$button.prop( 'disabled', false ).text( 'Add to library' );
					tell( { title: 'Not added', message: ( response && response.data && response.data.message ) || 'That file could not be added.' } );
				} ).fail( function () {
					$button.prop( 'disabled', false ).text( 'Add to library' );
				} );
			} );

			$panel.on( 'click', '.sb-images-report__delete', function () {
				var $button = $( this );
				var file    = $button.data( 'file' );

				if ( ! confirmed( this ) ) {
					ask( this, { title: 'Delete file', message: 'Delete ' + file + '? This cannot be undone.', action: 'Delete' } );
					return;
				}

				$button.prop( 'disabled', true ).text( 'Deleting...' );

				$.post( ajaxurl, { action: 'sb_images_orphan_one', nonce: nonce, job: jobToken, file: file } ).done( function ( response ) {
					if ( busyIn( response ) ) {
						$button.prop( 'disabled', false ).text( 'Delete' );
						watch( busyIn( response ) );
						return;
					}

					if ( response && response.success ) {
						$button.closest( 'tr' ).remove();
						scan( true );
						return;
					}

					$button.prop( 'disabled', false ).text( 'Delete' );
				} );
			} );

			/**
			 * Missing images: library entries with no file on disk. Taken out of the
			 * library a batch at a time in the progress box, so a long list never
			 * sits in one request. The server checks each one again before it goes.
			 */
			function deleteGone( ids, offset, deleted ) {
				var slice = ids.slice( offset, offset + 20 );

				$.post( ajaxurl, { action: 'sb_images_gone_delete', nonce: nonce, job: jobToken, ids: slice, offset: offset, total: ids.length } ).done( function ( response ) {
					if ( ! response || ! response.success ) {
						stopClock();

						if ( stoppedIn( response ) ) {
							jobToken = '';
							$status.text( 'Stopped from another window. Anything already done has been kept.' );
							buttons( true );
							return;
						}

						if ( busyIn( response ) ) {
							jobToken = '';
							watch( busyIn( response ) );
							return;
						}

						releaseJob();
						failJob( 'Something went wrong. ' + deleted + ' had already been deleted.' );
						buttons( true );
						return;
					}

					deleted += response.data.deleted;

					var done = Math.min( offset + slice.length, ids.length );

					seenNow = done;
					paintTimer();

					$bar.css( 'width', Math.round( ( done / ids.length ) * 100 ) + '%' );
					$status.text( done + ' of ' + ids.length + ' checked.' );

					if ( response.data.done || done >= ids.length || stopped ) {
						finished( ( stopped && done < ids.length ? 'Stopped. ' : 'Finished. ' ) + deleted + ' missing image' + ( deleted === 1 ? '' : 's' ) + ' deleted.' );
						scan( true );
						releaseJob();
						return;
					}

					deleteGone( ids, done, deleted );
				} ).fail( function () {
					stopClock();
					releaseJob();
					failJob( 'The server did not answer. ' + deleted + ' had already been deleted.' );
					buttons( true );
				} );
			}

			$panel.on( 'click', '#sb-images-gone-delete', function () {
				var ids = $( this ).data( 'ids' ) || [];

				if ( ! ids.length ) {
					return;
				}

				if ( ! confirmed( this ) ) {
					ask( this, { title: 'Delete missing images', message: 'Delete ' + ids.length + ' missing image' + ( ids.length === 1 ? '' : 's' ) + ' from the Media Library? Pages using them lose them. This cannot be undone.', action: 'Delete' } );
					return;
				}

				buttons( false );
				openJob( 'Deleting missing images' );
				$status.text( 'Deleting...' );
				startClock();
				newJob();

				deleteGone( ids, 0, 0 );
			} );

			$( '#sb-images-progress-close' ).on( 'click', function () {
				$progress.prop( 'hidden', true );
			} );

			$scan.on( 'click', function () {
				$progress.prop( 'hidden', true );
				scan( false );
			} );

			$build.on( 'click', function () {
				var force = $( '#sb-images-rebuild-force' ).prop( 'checked' );

				if ( force && ! confirmed( this ) ) {
					ask( this, { title: 'Rebuild every image', message: 'Rebuild the ' + savedCount + ' saved size' + ( savedCount === 1 ? '' : 's' ) + ' on every image? This replaces files that already exist.', action: 'Rebuild' } );
					return;
				}

				start( 'build', force, [] );
			} );

			$clean.on( 'click', function () {
				if ( ! confirmed( this ) ) {
					ask( this, { title: 'Remove old thumbnails', message: 'Remove the thumbnails left over from sizes you have taken off the list?', action: 'Remove' } );
					return;
				}

				start( 'clean', false );
			} );

			function clearOrphans( offset, cleared, freed ) {
				$.post( ajaxurl, { action: 'sb_images_orphans', nonce: nonce, job: jobToken, offset: offset } ).done( function ( response ) {
					if ( ! response || ! response.success ) {
						stopClock();

						if ( stoppedIn( response ) ) {
							jobToken = '';
							$status.text( 'Stopped from another window. Anything already done has been kept.' );
							buttons( true );
							return;
						}

						if ( busyIn( response ) ) {
							jobToken = '';
							watch( busyIn( response ) );
							return;
						}

						releaseJob();
						failJob( 'Something went wrong. Nothing else was changed.' );
						buttons( true );
						return;
					}

					var data = response.data;

					cleared += data.removed;
					freed += data.bytes;

					var seen = Math.min( data.offset, data.total );
					var pct  = data.total ? Math.round( ( seen / data.total ) * 100 ) : 100;

					seenNow = seen;
					paintTimer();

					$bar.css( 'width', pct + '%' );
					// Files kept or still in use move the bar but are not removed, so the
					// line counts both rather than ending short of the bar.
					$status.text( seen + ' of ' + data.total + ' checked, ' + cleared + ' removed.' );

					if ( data.done || stopped ) {
						$bar.css( 'width', data.done ? '100%' : pct + '%' );
						finished( ( data.done ? 'Finished. ' : 'Stopped. ' ) + cleared + ' files removed, ' + Math.round( freed / 1048576 * 10 ) / 10 + ' MB freed.' );
						scan( true );
						releaseJob();
						return;
					}

					clearOrphans( data.offset, cleared, freed );
				} ).fail( function () {
					stopClock();
					releaseJob();
					failJob( 'The server did not answer. Nothing else was changed, and what was already removed has gone.' );
					buttons( true );
				} );
			}

			$orphan.on( 'click', function () {
				if ( ! confirmed( this ) ) {
					ask( this, { title: 'Delete orphaned images', message: 'Delete ' + lastOrphans + ' orphaned image' + ( lastOrphans === 1 ? '' : 's' ) + '? This cannot be undone.', action: 'Delete' } );
					return;
				}

				buttons( false );
				openJob( 'Deleting orphan images' );
				$status.text( 'Clearing...' );
				startClock();
				newJob();

				clearOrphans( 0, 0, 0 );
			} );

			// Another window, or another person, may already be running a job.
			$.post( ajaxurl, { action: 'sb_images_status', nonce: nonce } ).done( function ( response ) {
				if ( response && response.success && response.data.busy ) {
					watch( response.data.busy );
				}
			} );
		}

		/**
		 * Attachment details: the sizes list is fetched when its pane is on screen.
		 * The media modal builds panes as you click through, so the page is
		 * watched for new ones and each is filled once, the first time it shows.
		 */
		function fillPanels() {
			$( '.sb-images-attachment-sizes[data-lazy]:visible' ).each( function () {
				var $panel = $( this );

				$panel.removeAttr( 'data-lazy' );

				$.post( ajaxurl, { action: 'sb_images_panel', nonce: $panel.data( 'nonce' ), id: $panel.data( 'id' ) } ).done( function ( response ) {
					if ( response && response.success ) {
						$panel.find( '.sb-images-sizes' ).replaceWith( response.data.html );
					}
				} );
			} );
		}

		if ( $( '.sb-images-attachment-sizes' ).length || $( 'body' ).hasClass( 'upload-php' ) || $( 'body' ).hasClass( 'post-php' ) || $( 'body' ).hasClass( 'post-new-php' ) ) {
			var panelTimer = null;
			var watcher    = new MutationObserver( function () {
				window.clearTimeout( panelTimer );
				panelTimer = window.setTimeout( fillPanels, 150 );
			} );

			watcher.observe( document.body, { childList: true, subtree: true } );
			fillPanels();
		}

		// Attachment details: rebuild one image.
		$( document ).on( 'click', '.sb-images-regenerate', function () {
			var $button = $( this );
			var $panel  = $button.closest( '.sb-images-attachment-sizes' );

			$button.prop( 'disabled', true ).text( 'Working...' );

			$.post( ajaxurl, {
				action: 'sb_images_single',
				nonce: $panel.data( 'nonce' ),
				id: $panel.data( 'id' ),
				force: 1
			} ).done( function ( response ) {
				if ( response && response.success ) {
					$panel.find( '.sb-images-sizes' ).replaceWith( response.data.html );
				}

				$button.prop( 'disabled', false ).text( 'Regenerate sizes' );
			} ).fail( function () {
				$button.prop( 'disabled', false ).text( 'Regenerate sizes' );
			} );
		} );

		// SB Image Settings: the sizes list follows its switch, and reset asks first.
		var $sizesOn = $( '#sb-images-sizes-on' );

		if ( $sizesOn.length ) {
			$sizesOn.on( 'change', function () {
				$( '#sb-images-sizes-body' ).prop( 'hidden', ! this.checked );
			} );
		}

		// Reset asks first. When WordPress's own sizes are set differently from
		// its defaults, the popup offers to put those back as well.
		$( '#sb-images-reset-widths' ).on( 'click', function ( e ) {
			if ( confirmed( this ) ) {
				return;
			}

			e.preventDefault();

			var $form  = $( this.form );
			var widths = ( $( '#sb-images-widths' ).data( 'defaults' ) || [] ).join( ', ' ).replace( /, ([^,]*)$/, ' and $1' );
			var drift  = $( this ).data( 'wp-drift' ) || [];

			ask( this, {
				title: 'Reset to default sizes',
				message: 'Put the standard image sizes back (' + widths + '), and switch off medium_large, 1536x1536 and 2048x2048? Any widths you have added or changed will be lost.',
				check: drift.length ? { label: 'Also put the WordPress sizes back to their defaults:', list: drift } : '',
				action: 'Reset',
				before: function ( checked ) {
					$form.find( 'input[name="sb_images_reset_wp"]' ).remove();

					if ( checked ) {
						$form.append( '<input type="hidden" name="sb_images_reset_wp" value="1">' );
					}
				}
			} );
		} );
	} );

	// Padlocks beside each size on SB Image Settings. A locked row cannot be
	// edited, removed or switched off; unlocking asks first. Saving does the rest.
	function setLocked( $row, locked ) {
		$row.toggleClass( 'is-locked', locked );
		$row.find( '.sb-images-lock input' ).prop( 'checked', locked );
		$row.find( '.sb-images-lock .dashicons' ).toggleClass( 'dashicons-lock', locked ).toggleClass( 'dashicons-unlock', ! locked );
		$row.find( 'input[type="number"]' ).prop( 'readOnly', locked );
		$row.find( '.sb-images-width__remove, .sb-images-othersizes__on' ).prop( 'disabled', locked );

		if ( locked ) {
			$row.removeClass( 'is-off' ).find( '.sb-images-othersizes__on' ).prop( 'checked', true );
		}
	}

	function unlockRow( $row ) {
		setLocked( $row, false );
	}

	$( document ).on( 'change', '.sb-images-lock input', function () {
		var box  = this;
		var $row = $( box ).closest( '.sb-images-width, li' );

		if ( box.checked ) {
			setLocked( $row, true );

			return;
		}

		if ( $( box ).data( 'sbUnlocking' ) ) {
			$( box ).removeData( 'sbUnlocking' );
			setLocked( $row, false );

			return;
		}

		box.checked = true;

		popup.open( {
			title: 'Unlock ' + box.value,
			message: 'This size can then be changed or removed, and SB Image Cleaner can delete its files. Anything linking straight to those files, like a sent email, would break.',
			action: 'Unlock'
		}, function () {
			$( box ).data( 'sbUnlocking', true );
			box.checked = false;
			$( box ).trigger( 'change' );
		} );
	} );
} )( jQuery );
