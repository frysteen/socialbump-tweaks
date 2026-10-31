<?php
/**
 * A company's logos in the Media Library (Sam, 30 October 2026).
 *
 * The Small Logo is always square: a picked image that isn't is cropped in a
 * popup (assets/logo.js), and the crop is saved as a new Media Library image
 * (at most 512 pixels a side). The image it was cut from stays as it was,
 * and the crop remembers it, so Re-crop starts from it again. With that one
 * deleted, Re-crop works from the crop itself, which then becomes the one to
 * re-crop from.
 *
 * An image in use as a logo is tagged (TAG, one row per use: 'small:<company>'
 * or 'main:<company>'), and only tagged images get the module's own sizes:
 * squares of 64 to 256 for the small logo, widths of 200 to 800 for the main
 * one, so a signature never loads a 2,000 pixel file into a 300 pixel space
 * (SB_EmailSig_Data::src()). None of these sizes is registered with
 * WordPress, so no other upload gets them. The Images module asks for them
 * on tagged images only (sb_images/wanted_for), lists them under Email
 * Signatures (sb_images/attachment_size_groups), and with this module off
 * finds them unclaimed and offers them as leftovers to clear.
 *
 * A logo cleared or replaced on a company's page is untagged when the
 * company is saved: its sizes go, and a crop this module made goes with
 * them. The image it came from stays.
 *
 * Settings' Admin Branding logos (Sam, 31 October 2026) work the same way,
 * with 'site' in place of the company's id: 'small:site', and a crop's
 * CROP is 'site'. The small one heads the every-company team page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Logo {

	/** On an image, one row per company using it: 'small:<id>' or 'main:<id>'. */
	const TAG = '_sb_emailsig_logo';

	/** On a crop this module made: the company it was made for (0 for one not saved yet). */
	const CROP = '_sb_emailsig_crop';

	/** On a crop: the image it was cut from, or 0 to re-crop from itself. */
	const CROP_OF = '_sb_emailsig_crop_of';

	/** On a crop: the square it was cut at in that image, [ x, y, side ] in pixels. */
	const CROP_RECT = '_sb_emailsig_crop_rect';

	/** On a crop not yet saved to its company. */
	const PENDING = '_sb_emailsig_crop_pending';

	const SQUARE = array( 64, 128, 192, 256 );
	const WIDTHS = array( 200, 400, 600, 800 );
	const MAX    = 512; // A crop's largest side.
	const MIN    = 128; // Smaller than this can't be used.
	const GOOD   = 512; // Smaller than this is warned about.

	const FIELDS = array(
		'small' => 'sb_emailsig_logo_small',
		'main'  => 'sb_emailsig_logo',
	);

	/** Settings, Admin Branding: the site's own logos. */
	const SITE_FIELDS = array(
		'small' => 'sb_emailsig_admin_logo_small',
		'main'  => 'sb_emailsig_admin_logo',
	);

	/** Whose logos: a company's id, or 'site' for Settings' Admin Branding. */
	private static function who( $owner ) {
		return 'site' === $owner ? 'site' : (string) (int) $owner;
	}

	/** An owner's logo field value: a company's post meta, or the options. */
	private static function value( $owner, $field ) {
		return 'site' === $owner ? (int) get_option( 'options_' . $field ) : (int) get_post_meta( (int) $owner, $field, true );
	}

	/** The site's small logo (Admin Branding), as SB_EmailSig_Data::image() gives it, or null. */
	public static function site_small() {
		$id = (int) get_option( 'options_' . self::SITE_FIELDS['small'] );

		return $id ? SB_EmailSig_Data::image( $id ) : null;
	}

	public static function init() {
		add_filter( 'sb_images/wanted_for', array( __CLASS__, 'wanted_for' ), 10, 2 );
		add_filter( 'sb_images/attachment_size_groups', array( __CLASS__, 'groups' ) );
		add_action( 'acf/save_post', array( __CLASS__, 'saved' ), 30 );
		add_action( 'before_delete_post', array( __CLASS__, 'deleting' ) );
		add_action( 'admin_init', array( __CLASS__, 'backfill' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_sb_emailsig_logo_info', array( __CLASS__, 'ajax_info' ) );
		add_action( 'wp_ajax_sb_emailsig_logo_crop', array( __CLASS__, 'ajax_crop' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Sizes
	 * ------------------------------------------------------------------ */

	/** A kind's sizes: name => width, height, crop. */
	public static function specs( $kind ) {
		$out = array();

		if ( 'small' === $kind ) {
			foreach ( self::SQUARE as $px ) {
				$out[ 'sb_emailsig_logo_square-' . $px ] = array( 'width' => $px, 'height' => $px, 'crop' => true );
			}
		} elseif ( 'main' === $kind ) {
			foreach ( self::WIDTHS as $px ) {
				$out[ 'sb_emailsig_logo-' . $px ] = array( 'width' => $px, 'height' => 0, 'crop' => false );
			}
		}

		return $out;
	}

	/** The uses tagged on an image: 'small', 'main' or both. */
	public static function kinds( $id ) {
		$kinds = array();

		foreach ( (array) get_post_meta( (int) $id, self::TAG ) as $row ) {
			$kinds[ strtok( (string) $row, ':' ) ] = true;
		}

		return array_values( array_intersect( array( 'small', 'main' ), array_keys( $kinds ) ) );
	}

	/** An image's own width and height, from its metadata. */
	public static function dims( $id ) {
		$meta = wp_get_attachment_metadata( (int) $id );

		return array( (int) ( $meta['width'] ?? 0 ), (int) ( $meta['height'] ?? 0 ) );
	}

	public static function is_square( $w, $h ) {
		return $w > 0 && abs( $w - $h ) <= 1;
	}

	/** The uses tagged on an image by companies switched on (SB_EmailSig_Switch), and Admin Branding. */
	public static function kinds_on( $id ) {
		$kinds = array();

		foreach ( (array) get_post_meta( (int) $id, self::TAG ) as $row ) {
			list( $kind, $who ) = array_pad( explode( ':', (string) $row, 2 ), 2, '' );

			if ( 'site' === $who || ! SB_EmailSig_Switch::is_off( (int) $who ) ) {
				$kinds[ $kind ] = true;
			}
		}

		return array_values( array_intersect( array( 'small', 'main' ), array_keys( $kinds ) ) );
	}

	/**
	 * The sizes an image should have from this module: the small logo's only
	 * on a square image. Only for companies switched on: a company switched
	 * off has them taken away (park()), and nothing makes them again.
	 */
	public static function own( $id ) {
		$out = array();

		foreach ( self::kinds_on( $id ) as $kind ) {
			if ( 'small' === $kind ) {
				list( $w, $h ) = self::dims( $id );

				if ( ! self::is_square( $w, $h ) ) {
					continue;
				}
			}

			$out += self::specs( $kind );
		}

		return $out;
	}

	/** The Images module: what an image should have, ours on tagged images. */
	public static function wanted_for( $wanted, $id ) {
		return (array) $wanted + self::own( $id );
	}

	/** The Images module: our sizes, as their own group. */
	public static function groups( $groups ) {
		$groups = (array) $groups;

		$groups['sb-emailsig'] = array(
			'title' => 'Email Signatures',
			'note'  => 'Made only for company logos.',
			'sizes' => self::specs( 'small' ) + self::specs( 'main' ),
		);

		return $groups;
	}

	/** Make the sizes a tagged image is missing. None bigger than the image itself. */
	public static function make( $id ) {
		$id    = (int) $id;
		$file  = get_attached_file( $id );
		$meta  = wp_get_attachment_metadata( $id );
		$specs = self::own( $id );

		if ( ! $specs || ! $file || ! file_exists( $file ) || ! is_array( $meta ) || empty( $meta['width'] ) ) {
			return array();
		}

		$folder = trailingslashit( dirname( $file ) );
		$todo   = array();

		foreach ( $specs as $name => $spec ) {
			if ( ! empty( $meta['sizes'][ $name ]['file'] ) && file_exists( $folder . $meta['sizes'][ $name ]['file'] ) ) {
				continue;
			}

			if ( $spec['width'] >= (int) $meta['width'] ) {
				continue;
			}

			$todo[ $name ] = $spec;
		}

		if ( ! $todo ) {
			return array();
		}

		$editor = wp_get_image_editor( $file );

		if ( is_wp_error( $editor ) ) {
			return array();
		}

		$made = (array) $editor->multi_resize( $todo );

		if ( $made ) {
			$meta['sizes'] = array_merge( (array) ( $meta['sizes'] ?? array() ), $made );
			wp_update_attachment_metadata( $id, $meta );
		}

		return array_keys( $made );
	}

	/** Take a kind's sizes off an image: the files too, unless another size uses the same one. */
	public static function strip( $id, $kind ) {
		$id   = (int) $id;
		$file = get_attached_file( $id );
		$meta = wp_get_attachment_metadata( $id );

		if ( ! is_array( $meta ) || empty( $meta['sizes'] ) ) {
			return;
		}

		$names  = array_keys( self::specs( $kind ) );
		$folder = $file ? trailingslashit( dirname( $file ) ) : '';
		$change = false;

		foreach ( $names as $name ) {
			if ( ! isset( $meta['sizes'][ $name ] ) ) {
				continue;
			}

			$one = (string) ( $meta['sizes'][ $name ]['file'] ?? '' );
			unset( $meta['sizes'][ $name ] );
			$change = true;

			$shared = false;
			foreach ( $meta['sizes'] as $other ) {
				if ( (string) ( $other['file'] ?? '' ) === $one ) {
					$shared = true;
					break;
				}
			}

			if ( $one !== '' && $folder !== '' && ! $shared && file_exists( $folder . $one ) ) {
				wp_delete_file( $folder . $one );
			}
		}

		if ( $change ) {
			wp_update_attachment_metadata( $id, $meta );
		}
	}

	/* ------------------------------------------------------------------ *
	 * In use, or not
	 * ------------------------------------------------------------------ */

	/** The images tagged as a company's logo of one kind. */
	private static function tagged( $kind, $co ) {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_key'       => self::TAG, // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'     => $kind . ':' . self::who( $co ), // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			)
		);
	}

	private static function tag( $id, $kind, $co ) {
		if ( ! in_array( $kind . ':' . self::who( $co ), array_map( 'strval', (array) get_post_meta( (int) $id, self::TAG ) ), true ) ) {
			add_post_meta( (int) $id, self::TAG, $kind . ':' . self::who( $co ) );
		}
	}

	/** No longer a company's logo: its sizes go once nothing uses it that way, and a crop of ours goes once nothing uses it at all. */
	private static function untag( $id, $kind, $co ) {
		delete_post_meta( (int) $id, self::TAG, $kind . ':' . self::who( $co ) );

		if ( ! in_array( $kind, self::kinds( $id ), true ) ) {
			self::strip( $id, $kind );
		}

		self::maybe_delete_crop( $id );
	}

	/** Delete a crop this module made, if nothing uses it. */
	private static function maybe_delete_crop( $id ) {
		if ( metadata_exists( 'post', (int) $id, self::CROP ) && ! get_post_meta( (int) $id, self::TAG ) ) {
			wp_delete_attachment( (int) $id, true );
		}
	}

	/** Bring a company's tags (or, for 'site', Admin Branding's) in line with its saved logos. */
	public static function sync( $co ) {
		$co = 'site' === $co ? 'site' : (int) $co;

		foreach ( ( 'site' === $co ? self::SITE_FIELDS : self::FIELDS ) as $kind => $field ) {
			$now = self::value( $co, $field );

			foreach ( self::tagged( $kind, $co ) as $id ) {
				if ( $id !== $now ) {
					self::untag( $id, $kind, $co );
				}
			}

			if ( $now && wp_attachment_is_image( $now ) ) {
				self::tag( $now, $kind, $co );

				// A crop now in use is this company's, saved.
				if ( metadata_exists( 'post', $now, self::CROP ) ) {
					update_post_meta( $now, self::CROP, $co );
					delete_post_meta( $now, self::PENDING );
					self::file( $now );
				}

				self::make( $now );
			}
		}

		self::clear_pending( $co );
	}

	/** Crops made on a company's page and never saved to it. With $keep, all but that one. */
	private static function clear_pending( $co, $keep = 0 ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array( 'key' => self::PENDING, 'compare' => 'EXISTS' ),
				array( 'key' => self::CROP, 'value' => self::who( $co ) ),
			),
		);

		// A new company's crops (0) are only this person's.
		if ( '0' === self::who( $co ) ) {
			$args['author'] = get_current_user_id();
		}

		foreach ( get_posts( $args ) as $id ) {
			if ( (int) $id !== (int) $keep ) {
				self::maybe_delete_crop( (int) $id );
			}
		}
	}

	/** A company saved on its page, or Settings (Admin Branding). */
	public static function saved( $post_id ) {
		if ( 'options' === $post_id || 'option' === $post_id ) {
			self::sync( 'site' );
			self::clear_pending( 0 );

			return;
		}

		if ( ! is_numeric( $post_id ) || get_post_type( (int) $post_id ) !== SB_EmailSig_Company::CPT ) {
			return;
		}

		self::sync( (int) $post_id );
		self::clear_pending( 0 );
	}

	/**
	 * Company $co switched off (SB_EmailSig_Switch): its logos' sizes go,
	 * unless a company still on (or Admin Branding) uses the image that way,
	 * so signatures already pasted lose them. The image itself stays.
	 */
	public static function park( $co ) {
		foreach ( array_keys( self::FIELDS ) as $kind ) {
			foreach ( self::tagged( $kind, (int) $co ) as $id ) {
				if ( ! in_array( $kind, self::kinds_on( $id ), true ) ) {
					self::strip( $id, $kind );
				}
			}
		}
	}

	/** Company $co switched on again: its logos' sizes made again, with the same names as before. */
	public static function unpark( $co ) {
		foreach ( array_keys( self::FIELDS ) as $kind ) {
			foreach ( self::tagged( $kind, (int) $co ) as $id ) {
				self::make( $id );
			}
		}
	}

	/** A company deleted for good: its logos are no longer its. */
	public static function deleting( $post_id ) {
		if ( get_post_type( $post_id ) !== SB_EmailSig_Company::CPT ) {
			return;
		}

		foreach ( array_keys( self::FIELDS ) as $kind ) {
			foreach ( self::tagged( $kind, (int) $post_id ) as $id ) {
				self::untag( $id, $kind, (int) $post_id );
			}
		}

		self::clear_pending( (int) $post_id );
	}

	/** Once: tag the logos companies already have, and make their sizes. */
	public static function backfill() {
		if ( get_option( 'sb_emailsig_logo_tags_v1' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		update_option( 'sb_emailsig_logo_tags_v1', 1, false );

		foreach ( SB_EmailSig_Company::all( array( 'publish', 'draft', 'private' ) ) as $co ) {
			self::sync( $co );
		}

		self::sync( 'site' );
	}

	/**
	 * With HappyFiles, a crop goes in its Email Signature Images folder (Sam,
	 * 31 October 2026), made the first time it's needed.
	 */
	public static function file( $id ) {
		$tax = defined( 'HAPPYFILES_TAXONOMY' ) ? HAPPYFILES_TAXONOMY : 'happyfiles_category';

		if ( ! taxonomy_exists( $tax ) ) {
			return;
		}

		$term = get_term_by( 'name', 'Email Signature Images', $tax );

		if ( ! $term ) {
			$made = wp_insert_term( 'Email Signature Images', $tax );

			if ( is_wp_error( $made ) ) {
				return;
			}

			$term = get_term( (int) $made['term_id'], $tax );
		}

		if ( $term && ! is_wp_error( $term ) ) {
			wp_set_object_terms( (int) $id, array( (int) $term->term_id ), $tax, true );
		}
	}

	/* ------------------------------------------------------------------ *
	 * The company's page
	 * ------------------------------------------------------------------ */

	public static function assets() {
		$co = SB_EmailSig_Companies::editing();

		// Settings: Admin Branding's small logo.
		if ( null === $co && SB_EmailSig_Settings::is_settings_request() && current_user_can( 'manage_options' ) ) {
			$co = 'site';
		}

		if ( null === $co ) {
			return;
		}

		SB_EmailSig_Notify::register_confirm();
		wp_enqueue_style( 'sb-emailsig-cropper', SB_EMAILSIG_URL . 'assets/vendor/cropper/cropper.min.css', array(), '1.6.2' );
		wp_enqueue_script( 'sb-emailsig-cropper', SB_EMAILSIG_URL . 'assets/vendor/cropper/cropper.min.js', array(), '1.6.2', true );
		wp_enqueue_script( 'sb-emailsig-logo', SB_EMAILSIG_URL . 'assets/logo.js', array( 'jquery', 'acf-input', 'sb-tweaks-confirm', 'sb-emailsig-cropper' ), SB_EmailSig_Module::asset_version( 'assets/logo.js' ), true );
		wp_localize_script(
			'sb-emailsig-logo',
			'sbEmailsigLogo',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'sb_emailsig_logo' ),
				'co'    => 'site' === $co ? 'site' : (int) $co,
				'min'   => self::MIN,
				'good'  => self::GOOD,
				'max'   => self::MAX,
			)
		);
	}

	/** An image as the crop popup needs it. */
	private static function image( $id ) {
		list( $w, $h ) = self::dims( $id );

		return array(
			'id'     => (int) $id,
			'url'    => (string) wp_get_attachment_url( (int) $id ),
			'width'  => $w,
			'height' => $h,
		);
	}

	private static function usable( $id ) {
		$file = $id ? get_attached_file( (int) $id ) : '';

		return $id && wp_attachment_is_image( (int) $id ) && $file && file_exists( $file );
	}

	/** A logo: its size, whether it's square, and what Re-crop starts from (with the last crop's square). */
	public static function ajax_info() {
		check_ajax_referer( 'sb_emailsig_logo', 'nonce' );

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( ! current_user_can( 'upload_files' ) || ! self::usable( $id ) ) {
			wp_send_json_error( 'That image can\'t be found.' );
		}

		$out    = self::image( $id );
		$crop   = metadata_exists( 'post', $id, self::CROP );
		$of     = (int) get_post_meta( $id, self::CROP_OF, true );
		$source = $crop && $of && self::usable( $of ) ? $of : $id;
		$rect   = $source !== $id ? get_post_meta( $id, self::CROP_RECT, true ) : '';

		$out['square'] = self::is_square( $out['width'], $out['height'] );
		$out['source'] = self::image( $source );
		$out['rect']   = is_array( $rect ) && count( $rect ) === 3 ? array_map( 'intval', array_values( $rect ) ) : null;

		// A crop whose image is gone: Re-crop can only work from the crop.
		$out['note'] = $crop && $source === $id;

		wp_send_json_success( $out );
	}

	/** Cut a square from an image and save it as a new one, for the company's page to use. */
	public static function ajax_crop() {
		check_ajax_referer( 'sb_emailsig_logo', 'nonce' );

		$site = isset( $_POST['co'] ) && 'site' === $_POST['co'];
		$co   = $site ? 'site' : ( isset( $_POST['co'] ) ? (int) $_POST['co'] : 0 );
		$src  = isset( $_POST['src'] ) ? (int) $_POST['src'] : 0;

		if ( ! current_user_can( 'upload_files' ) || ( $site && ! current_user_can( 'manage_options' ) ) || ( ! $site && $co && ( ! current_user_can( 'edit_post', $co ) || get_post_type( $co ) !== SB_EmailSig_Company::CPT ) ) ) {
			wp_send_json_error( 'Not allowed.' );
		}

		if ( ! self::usable( $src ) ) {
			wp_send_json_error( 'That image can\'t be found.' );
		}

		list( $w, $h ) = self::dims( $src );

		$side = min( (int) ( $_POST['w'] ?? 0 ), $w, $h );
		$x    = max( 0, min( (int) ( $_POST['x'] ?? 0 ), $w - $side ) );
		$y    = max( 0, min( (int) ( $_POST['y'] ?? 0 ), $h - $side ) );

		if ( $side < self::MIN ) {
			wp_send_json_error( 'Too small: the square needs to be at least ' . self::MIN . ' by ' . self::MIN . ' pixels.' );
		}

		$editor = wp_get_image_editor( get_attached_file( $src ) );

		if ( is_wp_error( $editor ) ) {
			wp_send_json_error( 'The image couldn\'t be opened.' );
		}

		$out = min( $side, self::MAX );
		$ok  = $editor->crop( $x, $y, $side, $side, $out, $out );

		if ( is_wp_error( $ok ) ) {
			wp_send_json_error( 'The image couldn\'t be cropped.' );
		}

		$name  = $site ? 'Admin' : ( $co ? SB_EmailSig_Company::name( $co ) : '' );
		$ext   = strtolower( pathinfo( get_attached_file( $src ), PATHINFO_EXTENSION ) );
		$up    = wp_upload_dir();

		if ( ! empty( $up['error'] ) ) {
			wp_send_json_error( 'The uploads folder can\'t be written to.' );
		}

		// A name of its own each time: a new crop given the name of one just deleted
		// showed the old picture from the browser's cache.
		$file  = wp_unique_filename( $up['path'], sanitize_file_name( 'email-signature-logo' . ( $name !== '' ? '-' . sanitize_title( $name ) : '' ) . '-' . base_convert( (string) time(), 10, 36 ) . '.' . ( $ext !== '' ? $ext : 'png' ) ) );
		$saved = $editor->save( trailingslashit( $up['path'] ) . $file );

		if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
			wp_send_json_error( 'The crop couldn\'t be saved.' );
		}

		$id = wp_insert_attachment(
			array(
				'post_title'     => 'Email signature logo' . ( $name !== '' ? ': ' . $name : '' ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_mime_type' => $saved['mime-type'],
				'guid'           => trailingslashit( $up['url'] ) . $saved['file'],
			),
			$saved['path'],
			0,
			true
		);

		if ( is_wp_error( $id ) || ! $id ) {
			wp_delete_file( $saved['path'] );
			wp_send_json_error( 'The crop couldn\'t be added to the Media Library.' );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $saved['path'] ) );

		$alt = trim( (string) get_post_meta( $src, '_wp_attachment_image_alt', true ) );
		update_post_meta( $id, '_wp_attachment_image_alt', $alt !== '' ? $alt : $name );

		// Cut from another crop of ours: this one is the one to re-crop from now.
		$from_crop = metadata_exists( 'post', $src, self::CROP );

		update_post_meta( $id, self::CROP, self::who( $co ) );
		update_post_meta( $id, self::CROP_OF, $from_crop ? 0 : $src );
		update_post_meta( $id, self::CROP_RECT, $from_crop ? '' : array( $x, $y, $side ) );
		update_post_meta( $id, self::PENDING, 1 );

		self::file( $id );

		// An earlier crop made here and not saved is no use now.
		self::clear_pending( $co, $id );

		wp_send_json_success( wp_prepare_attachment_for_js( $id ) );
	}
}
