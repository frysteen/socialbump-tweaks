<?php
/**
 * Where a long job on the server is up to, for the progress popup to follow
 * (assets/js/progress.js, job.watch(); Sam, 3 November 2026).
 *
 * A job that runs in one request, such as Publish, can't tell the browser
 * anything until it answers. So the page makes up a run id, sends it with
 * the job as sb_tweaks_run, and asks every 0.7 seconds what that run last
 * said. The job says it with set() as it goes:
 *
 *   $run = SB_Tweaks_Progress::run_id();   // the id the page sent, or ''
 *   SB_Tweaks_Progress::set( $run, [
 *       'from'    => 20,                     // where the bar is now (0 to 100)
 *       'to'      => 30,                     // where it gets to by the end of this step
 *       'eta'     => 2,                      // seconds this step should take
 *       'status'  => 'Uploading files: 3 of 9',
 *       'current' => 'File: assets/js/ui.js',
 *   ] );
 *   SB_Tweaks_Progress::clear( $run );      // when the job answers
 *
 * The bar creeps from 'from' to 'to' over 'eta' seconds between answers, so
 * it keeps moving through a slow step without running ahead of it. The page
 * gives job.watch() the ajax address and nonce from client(). Kept for ten
 * minutes, per user, so nobody sees anyone else's run.
 */

defined( 'ABSPATH' ) || exit;

class SB_Tweaks_Progress {

	const ACTION = 'sb_tweaks_progress';
	const NONCE  = 'sb_tweaks_progress';
	const PREFIX = 'sb_tweaks_progress_';

	public static function boot() {
		add_action( 'wp_ajax_' . self::ACTION, [ __CLASS__, 'ajax' ] );
	}

	/** The run id the page sent with the job, or '' when nobody is watching. */
	public static function run_id() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the job checks its own nonce.
		$run = isset( $_POST['sb_tweaks_run'] ) ? sanitize_key( wp_unslash( $_POST['sb_tweaks_run'] ) ) : '';

		return substr( $run, 0, 40 );
	}

	private static function key( $run ) {
		return self::PREFIX . get_current_user_id() . '_' . $run;
	}

	/** Say where the job is up to (see the top of this file). Nothing happens for run ''. */
	public static function set( $run, array $state ) {
		if ( $run === '' ) {
			return;
		}

		$percent = function ( $n ) {
			return round( max( 0, min( 100, (float) $n ) ), 1 );
		};
		$from    = isset( $state['from'] ) ? $state['from'] : 0;

		set_transient(
			self::key( $run ),
			[
				'from'    => $percent( $from ),
				'to'      => $percent( isset( $state['to'] ) ? $state['to'] : $from ),
				'eta'     => round( max( 0, (float) ( isset( $state['eta'] ) ? $state['eta'] : 0 ) ), 1 ),
				'status'  => (string) ( isset( $state['status'] ) ? $state['status'] : '' ),
				'current' => (string) ( isset( $state['current'] ) ? $state['current'] : '' ),
				'at'      => microtime( true ),
			],
			10 * MINUTE_IN_SECONDS
		);
	}

	public static function clear( $run ) {
		if ( $run !== '' ) {
			delete_transient( self::key( $run ) );
		}
	}

	/** What job.watch() needs: the ajax address and a nonce. */
	public static function client() {
		return [
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( self::NONCE ),
		];
	}

	/** job.watch() asking: the run's last word, or null when there's none (yet, or any more). */
	public static function ajax() {
		check_ajax_referer( self::NONCE, 'nonce' );

		$run   = isset( $_POST['run'] ) ? substr( sanitize_key( wp_unslash( $_POST['run'] ) ), 0, 40 ) : '';
		$state = $run !== '' ? get_transient( self::key( $run ) ) : false;

		wp_send_json_success( is_array( $state ) ? $state : null );
	}
}
