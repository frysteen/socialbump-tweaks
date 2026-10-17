<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image sizes and tidy-up on upload.
 *
 * Sizes come from a saved list of widths, each registered as image-<width> and
 * uncropped, so the height follows the original. They are also offered in the
 * editor size dropdown.
 *
 * Changing the list only affects images uploaded afterwards. Existing images
 * keep the files they already have until they are regenerated.
 */
class SB_Images {

	/**
	 * The standard set, for a 1330 wide site: thirds and quarters (450), halves
	 * (665), two thirds (890), full width (1330) and full width on high density
	 * screens (1920). Width only, so a tall image is as wide as a wide one,
	 * which WordPress's large (a 1024 box) is not. With WordPress's 300 they
	 * make an even srcset ladder. Sites that had the old set keep it: see
	 * sb_images_keep_old_image_defaults().
	 */
	const DEFAULT_WIDTHS = [ 450, 665, 890, 1330, 1920 ];

	/** WordPress's own size settings as it ships them, for Reset to default sizes. */
	const WP_DEFAULTS = [
		'thumbnail_size_w'    => 150,
		'thumbnail_size_h'    => 150,
		'thumbnail_crop'      => 1,
		'medium_size_w'       => 300,
		'medium_size_h'       => 300,
		'medium_large_size_w' => 768,
		'medium_large_size_h' => 0,
		'large_size_w'        => 1024,
		'large_size_h'        => 1024,
	];

	/**
	 * The WordPress sizes set differently from WordPress's defaults, each as
	 * "medium 480 x 480 to 300 x 300", for the tick box in the Reset popup.
	 */
	public static function wp_size_drift() {
		$out = [];

		foreach ( [ 'thumbnail', 'medium', 'medium_large', 'large' ] as $name ) {
			$dw   = self::WP_DEFAULTS[ $name . '_size_w' ];
			$dh   = self::WP_DEFAULTS[ $name . '_size_h' ];
			$w    = (int) get_option( $name . '_size_w', $dw );
			$h    = (int) get_option( $name . '_size_h', $dh );
			$crop = 'thumbnail' === $name && ! get_option( 'thumbnail_crop', 1 );

			if ( $w === $dw && $h === $dh && ! $crop ) {
				continue;
			}

			// medium_large has no height to speak of; its height is 0.
			$from  = 'medium_large' === $name ? (string) $w : $w . ' x ' . $h;
			$to    = 'medium_large' === $name ? (string) $dw : $dw . ' x ' . $dh;
			$out[] = $name . ' ' . $from . ' to ' . $to . ( $crop ? ', cropped' : '' );
		}

		return $out;
	}

	public static function boot() {
		add_action( 'after_setup_theme', [ __CLASS__, 'register_sizes' ] );
		add_filter( 'image_size_names_choose', [ __CLASS__, 'size_names' ] );
		add_filter( 'intermediate_image_sizes', [ __CLASS__, 'drop_off_sizes' ] );
		add_action( 'add_attachment', [ __CLASS__, 'on_upload' ] );
		add_action( 'admin_post_sb_images_save', [ __CLASS__, 'save' ] );
		require_once __DIR__ . '/class-sb-images-rebuild.php';
		require_once __DIR__ . '/class-sb-images-zoom.php';
		require_once __DIR__ . '/class-sb-images-media-grid.php';
		require_once __DIR__ . '/class-sb-images-media-search.php';

		SB_Images_Zoom::boot();
		SB_Images_Media_Grid::boot();
		SB_Images_Media_Search::boot();
	}

	public static function setting( $key, $fallback = null ) {
		$value = SB_Images_Module::setting( 'images', $key );

		return $value === null ? $fallback : $value;
	}

	/**
	 * Locked sizes, for files that emails or other sites link to straight
	 * (Driving Events' newsletters use image-480). A locked size stays
	 * registered and on the page, cannot be changed, switched off, removed or
	 * reset, and SB Image Cleaner never deletes its files:
	 * SB_Images_Rebuild::stale() leaves it out, and
	 * SB_Images_Orphans::is_protected() keeps any leftover file of its
	 * dimensions. Saved as locked_sizes, a list of size names.
	 */
	public static function locked_sizes() {
		$saved = self::setting( 'locked_sizes', [] );

		return is_array( $saved ) ? array_values( array_unique( array_filter( array_map( 'sanitize_key', $saved ) ) ) ) : [];
	}

	public static function is_locked( $name ) {
		return in_array( (string) $name, self::locked_sizes(), true );
	}

	/** The widths of our own sizes that are locked: image-480 gives 480. */
	public static function locked_widths() {
		$out = [];

		foreach ( self::locked_sizes() as $name ) {
			if ( preg_match( '/^image-(\d+)$/', $name, $m ) ) {
				$out[] = (int) $m[1];
			}
		}

		return $out;
	}

	/**
	 * Each locked size's width, height and crop, for spotting its files by
	 * name. Ours are uncropped widths; the rest come from what is registered,
	 * or from their options when switched off.
	 */
	public static function locked_dimensions() {
		$subs = wp_get_registered_image_subsizes();
		$out  = [];

		foreach ( self::locked_sizes() as $name ) {
			if ( preg_match( '/^image-(\d+)$/', $name, $m ) ) {
				$out[] = [ 'width' => (int) $m[1], 'height' => 0, 'crop' => false ];
			} elseif ( isset( $subs[ $name ] ) ) {
				$out[] = [ 'width' => (int) $subs[ $name ]['width'], 'height' => (int) $subs[ $name ]['height'], 'crop' => ! empty( $subs[ $name ]['crop'] ) ];
			} elseif ( get_option( $name . '_size_w' ) ) {
				$out[] = [ 'width' => (int) get_option( $name . '_size_w' ), 'height' => (int) get_option( $name . '_size_h' ), 'crop' => (bool) get_option( $name . '_crop' ) ];
			}
		}

		return $out;
	}

	public static function widths() {
		$saved = self::setting( 'sizes' );

		$widths = ( is_array( $saved ) && $saved ) ? $saved : self::DEFAULT_WIDTHS;
		$widths = array_values( array_unique( array_map( 'intval', array_merge( $widths, self::locked_widths() ) ) ) );
		sort( $widths );

		return $widths;
	}

	public static function register_sizes() {
		add_theme_support( 'post-thumbnails' );

		// Switched off, only the locked sizes are still made: something links to them.
		$on = (bool) self::setting( 'sizes_on', 1 );

		foreach ( $on ? self::widths() : self::locked_widths() as $width ) {
			add_image_size( 'image-' . (int) $width, (int) $width, 9999 );
		}

		if ( ! $on ) {
			return;
		}

		// Remember what we registered, so cleanup knows what is ours to delete.
		if ( class_exists( 'SB_Images_Rebuild' ) ) {
			SB_Images_Rebuild::remember( SB_Images_Rebuild::wanted() );
		}
	}

	/**
	 * Take the WordPress sizes switched off on this page out of WordPress's own
	 * list. That list is what decides which sizes an upload makes, so they stop
	 * being made, and Image Management no longer counts them as registered.
	 */
	public static function drop_off_sizes( $sizes ) {
		$off = SB_Images_Rebuild::wp_off();

		return $off ? array_values( array_diff( (array) $sizes, $off ) ) : $sizes;
	}

	public static function size_names( $names ) {
		foreach ( SB_Images_Rebuild::wp_off() as $off ) {
			unset( $names[ $off ] );
		}

		if ( ! self::setting( 'sizes_on', 1 ) ) {
			return $names;
		}

		foreach ( self::widths() as $width ) {
			$key           = 'image-' . (int) $width;
			$names[ $key ] = $key;
		}

		return $names;
	}

	/**
	 * Turn a raw filename into readable words.
	 *
	 * A name that already has spaces was typed by a person, so it is kept
	 * exactly as written: only the extension comes off and stray whitespace is
	 * tidied. Dashes, capitals and punctuation all stay, so
	 * "Epoxy Flooring Warranties - UV Stability" is left alone.
	 *
	 * A name with no spaces is slug or camera style, so its joiners become
	 * spaces and each word gets a capital first letter without the rest being
	 * lowered: IMG_2025-final.copy(1).JPG becomes IMG 2025 Final Copy 1, and
	 * sdi-UV-test becomes Sdi UV Test.
	 */
	public static function clean_title( $text ) {
		// Any HTML entity back to its character first, so &#8211; is a dash, not numbers.
		$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = trim( preg_replace( '/\.[a-z0-9]{2,4}$/i', '', $text ) );

		if ( $text === '' ) {
			return '';
		}

		if ( preg_match( '/\s/u', $text ) ) {
			return trim( preg_replace( '/\s+/u', ' ', $text ) );
		}

		$text = str_replace( [ '-', '_', '.' ], ' ', $text );
		$text = preg_replace( '/[^\p{L}\p{N} ]+/u', ' ', $text );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );

		if ( $text === '' ) {
			return '';
		}

		$words = explode( ' ', $text );
		$mb    = function_exists( 'mb_substr' ) && function_exists( 'mb_strtoupper' );

		foreach ( $words as $key => $word ) {
			$words[ $key ] = $mb ? mb_strtoupper( mb_substr( $word, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $word, 1, null, 'UTF-8' ) : ucfirst( $word );
		}

		return implode( ' ', $words );
	}

	/**
	 * Tidy the title and fill in alt text when an image is uploaded.
	 * Alt text written by hand or set during an import is left alone, and the
	 * file on disk is never renamed.
	 */
	public static function on_upload( $post_id ) {
		if ( ! wp_attachment_is_image( $post_id ) ) {
			return;
		}

		// The stored title, not get_the_title(): that runs the typography filter,
		// which turns " - " into &#8211; and the entity ended up in the alt text.
		$raw   = html_entity_decode( (string) get_post_field( 'post_title', $post_id, 'raw' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$clean = self::clean_title( $raw );

		if ( self::setting( 'clean_titles', 1 ) && $clean !== '' && $clean !== $raw ) {
			wp_update_post(
				[
					'ID'         => $post_id,
					'post_title' => $clean,
				]
			);
		}

		if ( ! self::setting( 'auto_alt', 1 ) ) {
			return;
		}

		if ( trim( (string) get_post_meta( $post_id, '_wp_attachment_image_alt', true ) ) !== '' ) {
			return;
		}

		$alt = $clean !== '' ? $clean : $raw;

		if ( trim( $alt ) === '' ) {
			return;
		}

		update_post_meta( $post_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'sb-tweaks' ) );
		}

		check_admin_referer( 'sb_images_save' );

		// The sizes ticked as locked, as the padlocks on the page left them.
		$locked = self::posted_locks();

		// Reset puts the standard set back, whatever is in the boxes.
		// Locked sizes stay as they are.
		if ( ! empty( $_POST['sb_images_reset_sizes'] ) ) {
			$widths = array_values( array_unique( array_merge( self::DEFAULT_WIDTHS, self::locked_widths() ) ) );
			sort( $widths );

			$settings                     = (array) get_option( SB_Images_Module::SETTINGS_OPTION, [] );
			$settings['images']['sizes']  = $widths;
			$settings['images']['wp_off'] = array_values( array_diff( SB_Images_Rebuild::DEFAULT_OFF, self::locked_sizes() ) );

			update_option( SB_Images_Module::SETTINGS_OPTION, $settings );

			// Ticked in the Reset popup: WordPress's own sizes back to its defaults too.
			if ( ! empty( $_POST['sb_images_reset_wp'] ) ) {
				foreach ( self::WP_DEFAULTS as $option => $value ) {
					if ( self::is_locked( preg_replace( '/_(size_w|size_h|crop)$/', '', $option ) ) ) {
						continue;
					}

					update_option( $option, $value );
				}
			}

			wp_safe_redirect( SB_Images_Module::page_url( 'settings', [ 'updated' => 'true' ] ) );
			exit;
		}

		list( $clean, $sizes_on, $wp_off ) = self::posted_sizes( $locked );

		$settings           = (array) get_option( SB_Images_Module::SETTINGS_OPTION, [] );
		$settings['images'] = [
			'sizes_on'     => $sizes_on,
			'sizes'        => $clean ? $clean : self::DEFAULT_WIDTHS,
			'clean_titles' => empty( $_POST['sb_images_clean_titles'] ) ? 0 : 1,
			'auto_alt'     => empty( $_POST['sb_images_auto_alt'] ) ? 0 : 1,
			'gallery_zoom' => empty( $_POST['sb_images_gallery_zoom'] ) ? 0 : 1,
			'media_grid'   => empty( $_POST['sb_images_media_grid'] ) ? 0 : 1,
			'media_search' => empty( $_POST['sb_images_media_search'] ) ? 0 : 1,
			'wp_off'       => $wp_off,
			'locked_sizes' => $locked,
		];

		update_option( SB_Images_Module::SETTINGS_OPTION, $settings );

		wp_safe_redirect( SB_Images_Module::page_url( 'settings', [ 'updated' => 'true' ] ) );
		exit;
	}

	/**
	 * The locked sizes from the posted padlocks: our image- names (whose width
	 * is kept in the list whatever else was posted) and any other size the
	 * page lists. Anything else is dropped.
	 */
	private static function posted_locks() {
		$posted = isset( $_POST['sb_images_locked'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['sb_images_locked'] ) ) : [];
		$known  = array_keys( self::other_sizes() );
		$out    = [];

		foreach ( $posted as $name ) {
			if ( preg_match( '/^image-(\d+)$/', $name, $m ) ? ( (int) $m[1] >= 16 && (int) $m[1] <= 5000 ) : in_array( $name, $known, true ) ) {
				$out[] = $name;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * The size settings from the posted form: our widths, sizes on or off, and
	 * the WordPress sizes switched off. WordPress's and WooCommerce's own
	 * widths are saved straight to their options on the way. A locked size
	 * keeps its width and stays on, whatever was posted.
	 *
	 * @param array $locked Size names locked by this save.
	 * @return array [ widths, sizes_on, wp_off ]
	 */
	private static function posted_sizes( array $locked ) {
		$widths = isset( $_POST['sb_images_widths'] ) ? (array) wp_unslash( $_POST['sb_images_widths'] ) : [];
		$clean  = [];

		foreach ( $widths as $width ) {
			$width = (int) $width;

			if ( $width >= 16 && $width <= 5000 ) {
				$clean[] = $width;
			}
		}

		foreach ( $locked as $name ) {
			if ( preg_match( '/^image-(\d+)$/', $name, $m ) ) {
				$clean[] = (int) $m[1];
			}
		}

		$clean = array_values( array_unique( $clean ) );

		// A name another plugin registered is not ours to take over.
		$taken = [];

		foreach ( $clean as $key => $width ) {
			$name = 'image-' . $width;

			if ( ! in_array( $name, get_intermediate_image_sizes(), true ) || in_array( $name, SB_Images_Rebuild::owned(), true ) ) {
				continue;
			}

			$taken[] = $name;

			unset( $clean[ $key ] );
		}

		$clean = array_values( $clean );

		if ( $taken ) {
			set_transient( 'sb_images_taken_' . get_current_user_id(), $taken, 60 );
		}
		sort( $clean );

		// The sizes that are really WordPress or WooCommerce settings.
		foreach ( self::editable_sizes() as $name => $size ) {
			$field = 'sb_images_size_' . $name;

			if ( ! isset( $_POST[ $field ] ) || in_array( $name, $locked, true ) ) {
				continue;
			}

			$width = (int) wp_unslash( $_POST[ $field ] );

			if ( $width < 0 || $width > 5000 ) {
				continue;
			}

			update_option( $size['option'], $width );

			// The core sizes keep a height as well, matched to the width.
			$heights = [ 'thumbnail' => 'thumbnail_size_h', 'medium' => 'medium_size_h', 'large' => 'large_size_h' ];

			if ( isset( $heights[ $name ] ) ) {
				update_option( $heights[ $name ], $width );
			}
		}

		// WordPress sizes switched off: any offered and left unticked. A save that
		// did not carry the list keeps what was there.
		if ( isset( $_POST['sb_images_wp_list'] ) ) {
			$wp_on  = isset( $_POST['sb_images_wp_on'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['sb_images_wp_on'] ) ) : [];
			$wp_off = array_values( array_diff( SB_Images_Rebuild::OFFABLE, $wp_on, $locked ) );
		} else {
			$wp_off = array_values( array_diff( SB_Images_Rebuild::wp_off(), $locked ) );
		}

		return [ $clean, empty( $_POST['sb_images_sizes_on'] ) ? 0 : 1, $wp_off ];
	}
	/**
	 * The Images settings page.
	 */
	public static function render_page() {
		$post_url = esc_url( admin_url( 'admin-post.php' ) );


		echo '<form method="post" autocomplete="off" data-sb-dirty action="' . $post_url . '">';
		echo '<input type="hidden" name="action" value="sb_images_save">';
		wp_nonce_field( 'sb_images_save' );

		// Sizes
		echo '<div class="sb-images-settings">';

		$sizes_on = (bool) self::setting( 'sizes_on', 1 );

		$taken = get_transient( 'sb_images_taken_' . get_current_user_id() );

		if ( $taken ) {
			delete_transient( 'sb_images_taken_' . get_current_user_id() );

			$message = __( 'These widths were left out, because another plugin already registers a size with the same name:', 'sb-tweaks' );
			$message .= ' ' . implode( ', ', (array) $taken );

			echo '<div class="notice notice-warning"><p>' . esc_html( $message ) . '</p></div>';
		}

		echo '<section class="sb-tweaks-section">';
		echo '<div class="sb-tweaks-section__head sb-images-section__head--switch">';
		echo '<div><h2>' . esc_html__( 'Image sizes', 'sb-tweaks' ) . '</h2>';
		echo '<p>' . esc_html__( 'Each width becomes a size named after it, uncropped, so the height follows the original. A new width only applies to images uploaded afterwards.', 'sb-tweaks' ) . '</p></div>';
		echo '<label class="sb-tweaks-switch"><input type="checkbox" id="sb-images-sizes-on" name="sb_images_sizes_on" value="1" ' . checked( $sizes_on, true, false ) . '>';
		echo '<span class="sb-tweaks-switch__track"><span class="sb-tweaks-switch__dot"></span></span>';
		echo '<span class="screen-reader-text">' . esc_html__( 'Add these image sizes', 'sb-tweaks' ) . '</span></label>';
		echo '</div>';

		echo '<div class="sb-images-sizes-body" id="sb-images-sizes-body"' . ( $sizes_on ? '' : ' hidden' ) . '>';
		echo '<div class="sb-images-widths" id="sb-images-widths" data-defaults="' . esc_attr( wp_json_encode( self::DEFAULT_WIDTHS ) ) . '">';

		foreach ( self::widths() as $width ) {
			echo self::width_row( (int) $width );
		}

		echo '</div>';
		echo '<div class="sb-images-width-actions">';
		echo '<span class="sb-images-width-actions__add">';
		echo '<button type="button" class="button" id="sb-images-add-width">' . esc_html__( 'Add a width', 'sb-tweaks' ) . '</button>';
		echo '<button type="button" class="button" id="sb-images-add-defaults">' . esc_html__( 'Add default sizes', 'sb-tweaks' ) . '</button>';
		echo '</span>';
		echo '<button type="submit" class="button sb-tweaks-button--danger" name="sb_images_reset_sizes" value="1" data-sb-always-on id="sb-images-reset-widths" data-wp-drift="' . esc_attr( wp_json_encode( self::wp_size_drift() ) ) . '">' . esc_html__( 'Reset to default sizes', 'sb-tweaks' ) . '</button>';
		echo '</div>';
		echo '</div>';

		self::render_other_sizes();

		echo '</section>';

		// On upload and Image extras share the right-hand column.
		echo '<div class="sb-images-settings__side">';

		// On upload
		echo '<section class="sb-tweaks-section"><div class="sb-tweaks-section__head"><h2>' . esc_html__( 'On upload', 'sb-tweaks' ) . '</h2></div><div class="sb-tweaks-grid">';
		echo self::checkbox( 'sb_images_clean_titles', (bool) self::setting( 'clean_titles', 1 ), __( 'Clean up the image file name on upload', 'sb-tweaks' ), __( 'Turns the media title into readable words. The file on disk is not renamed.', 'sb-tweaks' ) );
		echo self::checkbox( 'sb_images_auto_alt', (bool) self::setting( 'auto_alt', 1 ), __( 'Add ALT text on upload', 'sb-tweaks' ), __( 'Only when the image has none. Existing alt text is never changed.', 'sb-tweaks' ) );
		echo '</div></section>';

		// Image extras
		echo '<section class="sb-tweaks-section"><div class="sb-tweaks-section__head"><h2>' . esc_html__( 'Image extras', 'sb-tweaks' ) . '</h2></div><div class="sb-tweaks-grid">';
		echo self::checkbox( 'sb_images_gallery_zoom', (bool) self::setting( 'gallery_zoom', 0 ), __( 'Gutenberg image zoom', 'sb-tweaks' ), __( 'The gallery and image blocks get an Enable zoom option and a popup size. The size on the page stays the block\'s own Resolution setting.', 'sb-tweaks' ) );
		echo self::checkbox( 'sb_images_media_grid', (bool) self::setting( 'media_grid', 1 ), __( 'Media Browser Enhancements', 'sb-tweaks' ), __( 'Thumbnail size (Standard, Large or X-Large) and Cropped squares or Whole image, in the Media Library grid and in the media pop-up wherever it opens. Each person\'s choice is remembered, and the pop-up follows the Media Library. With HappyFiles active, the pop-up opened from a post list (such as Admin Columns inline edit) also gets a folder dropdown, since HappyFiles leaves its folder panel out there.', 'sb-tweaks' ) );
		echo self::checkbox( 'sb_images_media_search', (bool) self::setting( 'media_search', 1 ), __( 'Better media search', 'sb-tweaks' ), __( 'Media searches also look at alt text and the file name, including the name it was uploaded with. A pasted image URL finds its image.', 'sb-tweaks' ) );
		echo '</div></section>';

		echo '</div>';

		echo '</div>';

		submit_button( esc_html__( 'Save changes', 'sb-tweaks' ), 'primary sb-save--clean' );
		echo '</form>';
	}


	/**
	 * Sizes registered by anything other than us, with their dimensions.
	 *
	 * Used to point out where a width would produce much the same file as a size
	 * that already exists, and to refuse a name another plugin has taken.
	 */
	public static function other_sizes() {
		global $_wp_additional_image_sizes;

		$sizes = [];

		foreach ( get_intermediate_image_sizes() as $name ) {
			if ( strpos( $name, 'image-' ) === 0 ) {
				continue;
			}

			if ( isset( $_wp_additional_image_sizes[ $name ] ) ) {
				$sizes[ $name ] = [
					'width' => (int) $_wp_additional_image_sizes[ $name ]['width'],
					'crop'  => ! empty( $_wp_additional_image_sizes[ $name ]['crop'] ),
				];

				continue;
			}

			$sizes[ $name ] = [
				'width' => (int) get_option( $name . '_size_w' ),
				'crop'  => (bool) get_option( $name . '_crop' ),
			];
		}

		// A switched off size is no longer in WordPress's list, so it is added back
		// here to stay on the page, unticked, ready to switch on again.
		foreach ( SB_Images_Rebuild::wp_off() as $name ) {
			if ( isset( $sizes[ $name ] ) ) {
				continue;
			}

			$sizes[ $name ] = isset( $_wp_additional_image_sizes[ $name ] )
				? [ 'width' => (int) $_wp_additional_image_sizes[ $name ]['width'], 'crop' => ! empty( $_wp_additional_image_sizes[ $name ]['crop'] ) ]
				: [ 'width' => (int) get_option( $name . '_size_w' ), 'crop' => (bool) get_option( $name . '_crop' ) ];
		}

		return $sizes;
	}

	/** The name of a size someone else already makes at this width. */
	public static function clashing_size( $width ) {
		foreach ( self::other_sizes() as $name => $size ) {
			if ( ! $size['crop'] && (int) $size['width'] === (int) $width ) {
				return $name;
			}
		}

		return '';
	}



	/**
	 * Sizes that are settings rather than code, so they can be changed here.
	 *
	 * WordPress keeps three of them under Settings and hides medium_large
	 * altogether. WooCommerce keeps its own three in its customiser. All of them
	 * are just options underneath.
	 */
	public static function editable_sizes() {
		$sizes = [
			'thumbnail'    => [ 'option' => 'thumbnail_size_w' ],
			'medium'       => [ 'option' => 'medium_size_w' ],
			'medium_large' => [ 'option' => 'medium_large_size_w' ],
			'large'        => [ 'option' => 'large_size_w' ],
		];

		if ( class_exists( 'WooCommerce' ) ) {
			$sizes['woocommerce_thumbnail']         = [ 'option' => 'woocommerce_thumbnail_image_width' ];
			$sizes['woocommerce_single']            = [ 'option' => 'woocommerce_single_image_width' ];
			$sizes['woocommerce_gallery_thumbnail'] = [ 'option' => 'woocommerce_gallery_thumbnail_image_width' ];
		}

		return (array) apply_filters( 'sb_images/editable_sizes', $sizes );
	}
	/**
	 * The sizes WordPress, the theme and other plugins make.
	 *
	 * Shown whether or not our own sizes are switched on, because these are made
	 * on every upload regardless and count towards what a rebuild has to do.
	 */
	private static function render_other_sizes() {
		$sizes = self::other_sizes();

		if ( ! $sizes ) {
			return;
		}

		$editable = self::editable_sizes();

		// The ones that can be changed first, each group smallest first.
		uksort(
			$sizes,
			function ( $a, $b ) use ( $sizes, $editable ) {
				$one = isset( $editable[ $a ] ) ? 0 : 1;
				$two = isset( $editable[ $b ] ) ? 0 : 1;

				if ( $one !== $two ) {
					return $one <=> $two;
				}

				return (int) $sizes[ $a ]['width'] <=> (int) $sizes[ $b ]['width'];
			}
		);

		echo '<div class="sb-images-othersizes">';
		echo '<h3>' . esc_html__( 'Additional thumbnail sizes', 'sb-tweaks' ) . '</h3>';
		echo '<p class="sb-images-report__note">' . esc_html__( 'Made on every upload by WordPress, the theme or another plugin. The ones with a box can be changed here. A rebuild covers them all.', 'sb-tweaks' ) . '</p>';
		echo '<input type="hidden" name="sb_images_wp_list" value="1">';
		echo '<ul class="sb-images-othersizes__list">';

		foreach ( $sizes as $name => $size ) {
			$offable = in_array( $name, SB_Images_Rebuild::OFFABLE, true );
			$is_off  = in_array( $name, SB_Images_Rebuild::wp_off(), true );
			$locked  = self::is_locked( $name );
			$classes = trim( ( $is_off ? 'is-off' : '' ) . ( $locked ? ' is-locked' : '' ) );

			echo '<li' . ( $classes !== '' ? ' class="' . esc_attr( $classes ) . '"' : '' ) . '>';

			// WordPress's larger sizes can be switched off. Thumbnail and medium
			// stay on, because the media library and the editor show them.
			if ( $offable ) {
				echo '<input type="checkbox" class="sb-images-othersizes__on" name="sb_images_wp_on[]" value="' . esc_attr( $name ) . '" title="' . esc_attr__( 'Make this size', 'sb-tweaks' ) . '"' . checked( ! $is_off, true, false ) . disabled( $locked, true, false ) . '>';
			} else {
				echo '<span class="sb-images-othersizes__on"></span>';
			}

			echo '<code>' . esc_html( $name ) . '</code>';

			if ( isset( $editable[ $name ] ) ) {
				$field = 'sb_images_size_' . $name;

				printf(
					'<span class="sb-images-othersizes__edit"><input type="number" name="%1$s" value="%2$s" min="0" max="5000" step="1" class="small-text"%3$s> <span>px</span></span>',
					esc_attr( $field ),
					esc_attr( (int) $size['width'] ),
					$locked ? ' readonly' : ''
				);

				// A size can carry a note, and most do not. Reading the key blind
				// warned on every size on the page.
				if ( ! empty( $editable[ $name ]['note'] ) ) {
					echo '<em>' . esc_html( $editable[ $name ]['note'] ) . '</em>';
				}
			} else {
				$label = $size['width'] ? number_format_i18n( $size['width'] ) . ' px' : '';

				echo '<span class="sb-images-othersizes__fixed">' . esc_html( $label ) . '</span>';
			}

			echo self::lock_toggle( $name, $locked );
			echo '</li>';
		}

		echo '</ul>';
		echo '<p class="sb-images-othersizes__save">';
		echo '<a href="' . esc_url( admin_url( 'options-media.php' ) ) . '">' . esc_html__( 'Settings', 'sb-tweaks' ) . '</a>';
		echo '</p>';
		echo '</div>';
	}
	/** One row in the width list. */
	private static function width_row( $width ) {
		$clash  = self::clashing_size( $width );
		$locked = self::is_locked( 'image-' . (int) $width );

		// One of the standard widths, as Add default sizes and Reset would put it.
		$default = in_array( (int) $width, self::DEFAULT_WIDTHS, true ) ? '<span class="sb-images-width__default">' . esc_html__( 'default size', 'sb-tweaks' ) . '</span>' : '';

		$note  = $clash === '' ? '' : sprintf(
			'<span class="sb-images-width__clash">%s</span>',
			/* translators: %s: the name of another image size */
			esc_html( sprintf( __( 'same width as %s', 'sb-tweaks' ), $clash ) )
		);

		// The note sits inside the grey box under the name, so it never squeezes the row.
		return sprintf(
			'<div class="sb-images-width%5$s"><input type="number" name="sb_images_widths[]" value="%1$s" min="16" max="5000" step="1" class="small-text"%6$s><code class="sb-images-width__name">image-<span class="sb-images-width__value">%1$s</span>%4$s%3$s</code>%7$s<button type="button" class="button-link sb-images-width__remove" aria-label="%2$s"%8$s>&#10005;</button></div>',
			esc_attr( $width ),
			esc_attr__( 'Remove', 'sb-tweaks' ),
			$note,
			$default,
			( $default === '' ? '' : ' is-default' ) . ( $locked ? ' is-locked' : '' ),
			$locked ? ' readonly' : '',
			self::lock_toggle( 'image-' . (int) $width, $locked ),
			$locked ? ' disabled' : ''
		);
	}

	/**
	 * The padlock beside a size. Ticked, the size is locked (see
	 * locked_sizes()); images.js asks before unlocking, and keeps an image-
	 * row's value in step with its width.
	 */
	private static function lock_toggle( $name, $locked ) {
		return sprintf(
			'<label class="sb-images-lock" title="%3$s"><input type="checkbox" name="sb_images_locked[]" value="%1$s"%2$s><span class="dashicons dashicons-%4$s" aria-hidden="true"></span><span class="screen-reader-text">%3$s</span></label>',
			esc_attr( $name ),
			checked( $locked, true, false ),
			esc_attr__( 'Lock this size, so it cannot be changed or removed and its files are never deleted', 'sb-tweaks' ),
			$locked ? 'lock' : 'unlock'
		);
	}

	/** One upload option, styled like the switches on the Features page. */
	private static function checkbox( $name, $checked, $label, $note ) {
		$card  = '<div class="sb-tweaks-card%1$s">';
		$card .= '<div class="sb-tweaks-card__head"><h3>%2$s</h3>';
		$card .= '<label class="sb-tweaks-switch"><input type="checkbox" name="%3$s" value="1" %4$s>';
		$card .= '<span class="sb-tweaks-switch__track"><span class="sb-tweaks-switch__dot"></span></span>';
		$card .= '<span class="screen-reader-text">%2$s</span></label></div>';
		$card .= '<p class="sb-tweaks-card__desc">%5$s</p></div>';

		return sprintf(
			$card,
			$checked ? ' is-on' : '',
			esc_html( $label ),
			esc_attr( $name ),
			checked( $checked, true, false ),
			esc_html( $note )
		);
	}
}
