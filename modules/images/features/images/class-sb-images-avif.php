<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AVIF on servers whose ImageMagick can't write it (Sam, 10 November 2026).
 *
 * Found on simplystyleco.com.au: ImageMagick 7.1.2-35 says it supports AVIF,
 * so WordPress picks Imagick for AVIF images, but every write throws "AOM
 * encoder error: Unspecified internal error - Invalid control ID" and leaves
 * an empty file. GD's imageavif() on the same server writes AVIF fine. The hub
 * has the same ImageMagick and the same fault.
 *
 * status() test-writes a small AVIF with each, once, and keeps the answer in
 * OPTION, keyed on the ImageMagick version and the PHP version (GD comes with
 * PHP), so it tests again only when either changes. When Imagick fails and GD
 * works, editors() swaps WP_Image_Editor_Imagick for SB_Images_Imagick_No_Avif,
 * which turns AVIF down, so WordPress falls back to GD for AVIF and keeps
 * Imagick for every other format. When neither can, the editors are left
 * alone and SB Image Cleaner says so (label()).
 */
class SB_Images_Avif {

	const OPTION = 'sb_images_avif';

	public static function boot() {
		add_filter( 'wp_image_editors', [ __CLASS__, 'editors' ], 99 );
	}

	/** What the answer depends on: the ImageMagick version, and PHP's for GD. */
	private static function key() {
		$magick = 'none';

		if ( class_exists( 'Imagick' ) ) {
			try {
				$version = Imagick::getVersion();
				$magick  = (string) ( $version['versionString'] ?? 'unknown' );
			} catch ( Throwable $e ) {
				$magick = 'unknown';
			}
		}

		return $magick . ' | PHP ' . PHP_VERSION;
	}

	/**
	 * [ imagick, gd, key, checked ]. imagick: true when it writes AVIF, false
	 * when it claims AVIF but can't write it, null when it isn't here or doesn't
	 * claim AVIF (WordPress won't pick it for AVIF then anyway). gd: true when
	 * it writes AVIF. $fresh tests again whatever is saved.
	 */
	public static function status( $fresh = false ) {
		static $now = null;

		$key = self::key();

		if ( ! $fresh && $now !== null && $now['key'] === $key ) {
			return $now;
		}

		$saved = get_option( self::OPTION );

		if ( ! $fresh && is_array( $saved ) && ( $saved['key'] ?? '' ) === $key && array_key_exists( 'imagick', $saved ) ) {
			$now = $saved;

			return $now;
		}

		$now = [
			'key'     => $key,
			'imagick' => self::imagick_writes(),
			'gd'      => self::gd_writes(),
			'checked' => time(),
		];

		update_option( self::OPTION, $now, true );

		return $now;
	}

	/** Imagick claims AVIF but can't write it, and GD can: AVIF goes to GD. */
	public static function use_gd() {
		$status = self::status();

		return $status['imagick'] === false && $status['gd'];
	}

	/** Neither editor can write AVIF on this server. */
	public static function none() {
		$status = self::status();

		return $status['imagick'] !== true && ! $status['gd'];
	}

	/**
	 * What SB Image Cleaner says about AVIF: '' when there's nothing to say
	 * (Imagick writes it, or Imagick doesn't claim it and GD does, which is
	 * WordPress's own choice anyway).
	 */
	public static function label() {
		if ( self::use_gd() ) {
			return __( 'AVIF: GD (Imagick\'s AVIF encoder is broken on this server, so AVIF images are made with GD and everything else still with Imagick).', 'sb-tweaks' );
		}

		if ( self::none() ) {
			return __( 'This server can\'t write AVIF images, so AVIF uploads won\'t get their sizes. Convert them to JPG or WebP.', 'sb-tweaks' );
		}

		return '';
	}

	/** The editors WordPress tries, with Imagick's AVIF taken out when GD should do it. */
	public static function editors( $editors ) {
		$editors = (array) $editors;

		if ( ! in_array( 'WP_Image_Editor_Imagick', $editors, true ) || ! class_exists( 'WP_Image_Editor_Imagick', false ) || ! self::use_gd() ) {
			return $editors;
		}

		// Declared here, once WordPress has loaded the class it extends.
		require_once __DIR__ . '/class-sb-images-imagick-no-avif.php';

		return array_map(
			function ( $editor ) {
				return $editor === 'WP_Image_Editor_Imagick' ? 'SB_Images_Imagick_No_Avif' : $editor;
			},
			$editors
		);
	}

	private static function imagick_writes() {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			return null;
		}

		try {
			if ( ! Imagick::queryFormats( 'AVIF' ) ) {
				return null;
			}
		} catch ( Throwable $e ) {
			return null;
		}

		$path = self::temp();
		$ok   = false;

		try {
			$image = new Imagick();
			$image->newImage( 16, 16, new ImagickPixel( '#85df07' ) );
			$image->setImageFormat( 'avif' );
			$image->writeImage( $path );
			$image->clear();
			$ok = self::good( $path );
		} catch ( Throwable $e ) {
			$ok = false;
		}

		@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions

		return $ok;
	}

	private static function gd_writes() {
		if ( ! function_exists( 'imageavif' ) || ! function_exists( 'imagecreatetruecolor' ) ) {
			return false;
		}

		$path  = self::temp();
		$image = @imagecreatetruecolor( 16, 16 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$ok    = $image && @imageavif( $image, $path, 50, 10 ) && self::good( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions

		return (bool) $ok;
	}

	private static function temp() {
		return trailingslashit( get_temp_dir() ) . 'sb-images-avif-' . uniqid() . '.avif';
	}

	/** A file that is there, not empty, and reads as an image. */
	private static function good( $path ) {
		clearstatcache( true, $path );

		if ( ! is_file( $path ) || (int) filesize( $path ) < 1 ) {
			return false;
		}

		$info = function_exists( 'wp_getimagesize' ) ? wp_getimagesize( $path ) : @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		return is_array( $info ) && ! empty( $info[0] );
	}
}
