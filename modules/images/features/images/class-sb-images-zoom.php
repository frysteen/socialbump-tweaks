<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zoom for the native Gutenberg gallery and image blocks.
 *
 * Both blocks get a Zoom panel in the editor sidebar: an Enable zoom toggle and
 * the size the popup opens. The size on the page is the block's own Resolution
 * setting, so it is not repeated here. An image inside a gallery hides the
 * panel, because the gallery's setting governs every image in it.
 *
 * On the front end each zoomable image carries data-sb-images-zoom with the popup
 * size URL and data-sb-images-zoom-group with its Lightbox ID, and a small
 * lightbox opens it: arrows and arrow keys move through its group, Esc or a
 * click outside closes, the caption comes along.
 *
 * Lightbox ID (sbskZoomGroup, Sam, 10 November 2026), like Bricks' Lightbox ID:
 * images with the same ID page through together, across blocks, in page order.
 * Blank: every standalone image on the page with no ID is one group, and a
 * gallery with no ID is a group of its own. A gallery's ID covers all its
 * images. One image on its own: give it an ID nothing else has. Blocks saved
 * before this have no ID, so they count as blank.
 * WordPress's own Expand on click always opens the full-size original, which
 * is the thing this exists to avoid.
 *
 * Everything sits behind the Gutenberg image zoom switch on SB Image Settings.
 * The hooks are registered regardless and step aside while it is off, so the
 * block attributes simply sit unused in the saved markup.
 */
class SB_Images_Zoom {

	public static function boot() {
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'editor_assets' ] );
		add_filter( 'render_block_core/gallery', [ __CLASS__, 'maybe_render' ], 10, 2 );
		add_filter( 'render_block_core/image', [ __CLASS__, 'maybe_render' ], 10, 2 );
	}

	/** Whether SB Image Settings switch is on. */
	public static function on() {
		return (bool) SB_Images::setting( 'gallery_zoom', 0 );
	}

	/**
	 * Every registered size, name and width, for the popup size dropdown.
	 */
	public static function size_choices() {
		global $_wp_additional_image_sizes;

		$choices = [];

		foreach ( get_intermediate_image_sizes() as $name ) {
			if ( isset( $_wp_additional_image_sizes[ $name ] ) ) {
				$width = (int) $_wp_additional_image_sizes[ $name ]['width'];
			} else {
				$width = (int) get_option( $name . '_size_w' );
			}

			$choices[ $name ] = ( $width > 0 && $width < 9999 ) ? sprintf( '%s (%dpx)', $name, $width ) : $name;
		}

		return $choices;
	}

	/** The editor controls: a toggle and the popup size. */
	public static function editor_assets() {
		if ( ! self::on() ) {
			return;
		}

		$js = SB_IMAGES_PATH . 'assets/js/gallery-zoom-editor.js';

		if ( ! file_exists( $js ) ) {
			return;
		}

		wp_enqueue_script(
			'sb-images-gallery-zoom-editor',
			SB_IMAGES_URL . 'assets/js/gallery-zoom-editor.js',
			[ 'wp-hooks', 'wp-compose', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-i18n' ],
			SB_TWEAKS_VERSION . '.' . filemtime( $js ),
			true
		);

		wp_add_inline_script(
			'sb-images-gallery-zoom-editor',
			'window.sbImagesGalleryZoom = ' . wp_json_encode( [ 'sizes' => self::size_choices() ] ) . ';',
			'before'
		);
	}

	/** The front end, only when the switch and the block toggle are both on. */
	public static function maybe_render( $content, $block ) {
		if ( ! self::on() || empty( $block['attrs']['sbskZoom'] ) ) {
			return $content;
		}

		return self::render( $content, (array) $block['attrs'] );
	}

	/**
	 * Mark each attachment image in the block for the popup.
	 *
	 * Works the same for a gallery (every image in it) and a single image.
	 * Kept separate from the switch check so it can be exercised directly.
	 * Images without a wp-image-<id> class are not attachments and are left
	 * alone, as is the whole block when nothing in it could be marked.
	 */
	public static function render( $content, array $attrs ) {
		$zoom  = self::valid_size( $attrs['sbskZoomSize'] ?? '' );
		$group = self::group( $attrs['sbskZoomGroup'] ?? '' );

		$tags  = new WP_HTML_Tag_Processor( $content );
		$found = false;

		while ( $tags->next_tag( 'img' ) ) {
			$class = (string) $tags->get_attribute( 'class' );

			if ( ! preg_match( '/wp-image-(\d+)/', $class, $m ) ) {
				continue;
			}

			$popup = wp_get_attachment_image_src( (int) $m[1], $zoom );

			if ( ! $popup ) {
				continue;
			}

			$tags->set_attribute( 'data-sb-images-zoom', esc_url( $popup[0] ) );
			$tags->set_attribute( 'data-sb-images-zoom-group', $group ); // '' is the default group.
			$tags->add_class( 'sb-images-zoom__img' );

			$found = true;
		}

		if ( ! $found ) {
			return $content;
		}

		self::front_assets();

		return $tags->get_updated_html();
	}

	/**
	 * A Lightbox ID as a simple slug: lower case letters, numbers and hyphens.
	 * '' is the default group.
	 */
	public static function group( $id ) {
		$id = preg_replace( '/[^a-z0-9-]+/', '-', strtolower( (string) $id ) );

		return trim( (string) preg_replace( '/-+/', '-', $id ), '-' );
	}

	/** A size name that is really registered, or full. */
	private static function valid_size( $size ) {
		$size = sanitize_text_field( (string) $size );

		return in_array( $size, get_intermediate_image_sizes(), true ) ? $size : 'full';
	}

	/** The popup script and styles, once, from whichever block rendered first. */
	private static function front_assets() {
		static $done = false;

		if ( $done ) {
			return;
		}

		$done = true;

		$js  = SB_IMAGES_PATH . 'assets/js/gallery-zoom.js';
		$css = SB_IMAGES_PATH . 'assets/css/gallery-zoom.css';

		if ( file_exists( $css ) ) {
			wp_enqueue_style( 'sb-images-gallery-zoom', SB_IMAGES_URL . 'assets/css/gallery-zoom.css', [], SB_TWEAKS_VERSION . '.' . filemtime( $css ) );
		}

		if ( file_exists( $js ) ) {
			wp_enqueue_script( 'sb-images-gallery-zoom', SB_IMAGES_URL . 'assets/js/gallery-zoom.js', [], SB_TWEAKS_VERSION . '.' . filemtime( $js ), true );
		}
	}
}
