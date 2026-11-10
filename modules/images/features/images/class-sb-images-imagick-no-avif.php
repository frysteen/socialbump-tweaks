<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress's Imagick editor without AVIF (SB_Images_Avif::editors()).
 *
 * Loaded only from inside the wp_image_editors filter, once WordPress has
 * loaded WP_Image_Editor_Imagick. Turning AVIF down here makes WordPress pick
 * GD for AVIF images, on a server whose ImageMagick claims AVIF but can't
 * write it (Sam, 10 November 2026). Every other format stays with Imagick.
 */
class SB_Images_Imagick_No_Avif extends WP_Image_Editor_Imagick {

	public static function supports_mime_type( $mime_type ) {
		if ( 'image/avif' === $mime_type ) {
			return false;
		}

		return parent::supports_mime_type( $mime_type );
	}
}
