<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load Bricks' colour palette and global variables CSS in wp-admin.
 *
 * Bricks writes these to uploads/bricks/css/ and only loads them on the front
 * end, so var(--primary), var(--text-xs) and the like are undefined in
 * wp-admin. This enqueues the ticked files on admin_enqueue_scripts, each only
 * when it exists and isn't empty, versioned by filemtime() so the browser picks
 * up a file Bricks has just regenerated. The front end and the builder (which
 * runs on the front end) are untouched.
 */
class SB_Bricks_Admin_Variables {

	/** Card setting => the file's name in uploads/bricks/css/ (also its handle). */
	const FILES = [
		'palettes'  => 'color-palettes',
		'variables' => 'global-variables',
	];

	public static function boot() {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
	}

	public static function enqueue() {
		$upload = wp_upload_dir( null, false );

		if ( ! empty( $upload['error'] ) ) {
			return;
		}

		$dir    = trailingslashit( $upload['basedir'] ) . 'bricks/css/';
		$url    = trailingslashit( $upload['baseurl'] ) . 'bricks/css/';
		$module = sb_tweaks_module( 'bricks' );

		foreach ( self::FILES as $key => $name ) {
			if ( ! $module || ! $module->setting( 'admin-variables', $key ) ) {
				continue;
			}

			$file = $name . '.min.css';

			if ( ! is_file( $dir . $file ) || ! filesize( $dir . $file ) ) {
				continue;
			}

			wp_enqueue_style( 'sb-bricks-' . $name, $url . $file, [], (string) filemtime( $dir . $file ) );
		}
	}
}
