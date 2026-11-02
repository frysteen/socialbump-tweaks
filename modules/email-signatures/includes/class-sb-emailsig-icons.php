<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Icon PNGs per template. You work in SVG; emails get PNG.
 *
 * Gmail and Outlook for Windows show no SVG, so icons are drawn in the
 * browser (assets/icon-builder.js) in each template's colours and sizes, sent
 * with the form as PNG data, checked here and saved as files:
 *
 * - contact icons from the Edit styles page, from the template's icons/*.svg;
 * - social icons from the Edit styles page (that template) and from a
 *   company's page (every template, in that company's colours).
 *
 * Kept in the option sb_emailsig_icons:
 *   [ template id => [ 'contact' => [ name => file ], 'social' => [ row => file ] ] ]
 * (a duplicated template starts with a copy of its original's; the entries
 * under folder names from before template records are kept for going back).
 * A contact icon not built yet falls back to the PNG shipped in the template's
 * icons/ folder, drawn in its default colour.
 */
class SB_EmailSig_Icons {

	const OPTION = 'sb_emailsig_icons';
	const MAX_PX = 400;

	/**
	 * Raised when a contact icon's drawing changes (2: LinkedIn became the
	 * plain "in", Sam, 2 November 2026). A company's set drawn before it is
	 * stale(), so the next admin visit draws it again.
	 */
	const CONTACT_REV = 2;

	/** The Custom SVG field's key: ACF sends it with uploads made from that field. */
	const SVG_FIELD = 'field_sb_emailsig_social_media_icon_file';

	/** The Info List items' Custom SVG fields (2 November 2026), the company's and a person's. */
	const SVG_FIELDS = [ self::SVG_FIELD, 'field_sb_emailsig_info_icon_file', 'field_sb_emailsig_person_info_icon_file', 'field_sb_emailsig_button_icon_file', 'field_sb_emailsig_line_phone_icon_file', 'field_sb_emailsig_line_mobile_icon_file', 'field_sb_emailsig_line_email_icon_file', 'field_sb_emailsig_line_website_icon_file', 'field_sb_emailsig_line_address_icon_file', 'field_sb_emailsig_line_vcard_icon_file' ];

	public static function init() {
		add_action( 'acf/save_post', [ __CLASS__, 'settings_saved' ], 20 );
		add_action( 'wp_ajax_sb_emailsig_button_icons', [ __CLASS__, 'button_icons_ajax' ] );

		// SVG uploads from the Custom SVG field, for sites that don't allow
		// them already (Bricks does), cleaned of anything that can run.
		add_filter( 'upload_mimes', [ __CLASS__, 'upload_mimes' ], 99 );
		add_filter( 'wp_check_filetype_and_ext', [ __CLASS__, 'check_svg' ], 99, 4 );
		add_filter( 'wp_handle_upload_prefilter', [ __CLASS__, 'clean_svg_upload' ], 5 );
	}

	/* ------------------------------------------------------------------ *
	 * Custom SVGs from the media library
	 * ------------------------------------------------------------------ */

	/** True while an administrator uploads through the Custom SVG field. */
	private static function svg_field_upload() {
		$field = isset( $_POST['_acfuploader'] ) ? sanitize_text_field( wp_unslash( $_POST['_acfuploader'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- checked by WordPress's upload handler.

		return in_array( $field, self::SVG_FIELDS, true ) && current_user_can( 'manage_options' );
	}

	public static function upload_mimes( $mimes ) {
		if ( self::svg_field_upload() ) {
			$mimes['svg'] = 'image/svg+xml';
		}

		return $mimes;
	}

	/** WordPress can't tell an SVG's type from its contents, so it is named here. */
	public static function check_svg( $data, $file, $filename, $mimes ) {
		if ( empty( $data['type'] ) && self::svg_field_upload() && strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) ) === 'svg' ) {
			$data['ext']  = 'svg';
			$data['type'] = 'image/svg+xml';
		}

		return $data;
	}

	public static function clean_svg_upload( $file ) {
		if ( ! self::svg_field_upload() || strtolower( pathinfo( (string) ( $file['name'] ?? '' ), PATHINFO_EXTENSION ) ) !== 'svg' ) {
			return $file;
		}

		$svg = self::clean_svg( (string) file_get_contents( $file['tmp_name'] ) ); // phpcs:ignore

		if ( $svg === '' ) {
			$file['error'] = 'That file isn\'t an SVG that can be used as an icon.';

			return $file;
		}

		file_put_contents( $file['tmp_name'], $svg ); // phpcs:ignore

		return $file;
	}

	/**
	 * An SVG with anything that can run taken out: scripts, embedded HTML,
	 * event attributes and javascript: links. '' if it isn't an SVG.
	 */
	public static function clean_svg( $svg ) {
		if ( stripos( $svg, '<svg' ) === false || ! class_exists( 'DOMDocument' ) ) {
			return '';
		}

		$doc  = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$ok   = $doc->loadXML( $svg, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		if ( ! $ok || ! $doc->documentElement || strtolower( $doc->documentElement->localName ) !== 'svg' ) {
			return '';
		}

		$xpath = new DOMXPath( $doc );

		foreach ( iterator_to_array( $xpath->query( '//*' ) ) as $el ) {
			if ( in_array( strtolower( $el->localName ), [ 'script', 'foreignobject', 'iframe', 'object', 'embed' ], true ) ) {
				$el->parentNode->removeChild( $el );
				continue;
			}

			foreach ( iterator_to_array( $el->attributes ) as $attr ) {
				$name  = strtolower( $attr->nodeName );
				$value = strtolower( preg_replace( '/\s+/', '', $attr->nodeValue ) );

				if ( strpos( $name, 'on' ) === 0 || ( substr( $name, -4 ) === 'href' && strpos( $value, 'javascript:' ) === 0 ) ) {
					$el->removeAttribute( $attr->nodeName );
				}
			}
		}

		return (string) $doc->saveXML( $doc->documentElement );
	}

	/** A media library SVG's code, cleaned, or ''. */
	public static function file_svg( $id ) {
		$id   = (int) $id;
		$path = $id ? get_attached_file( $id ) : '';

		if ( ! $path || get_post_mime_type( $id ) !== 'image/svg+xml' || ! file_exists( $path ) ) {
			return '';
		}

		return self::clean_svg( (string) file_get_contents( $path ) ); // phpcs:ignore
	}

	private static function dir() {
		$up = wp_upload_dir();

		return [
			'path' => trailingslashit( $up['basedir'] ) . SB_EmailSig_Photo::UPLOAD . '/icons',
			'url'  => trailingslashit( $up['baseurl'] ) . SB_EmailSig_Photo::UPLOAD . '/icons',
		];
	}

	/* ------------------------------------------------------------------ *
	 * Getters
	 * ------------------------------------------------------------------ */

	/**
	 * Where a template's icons are kept for the company being drawn
	 * (SB_EmailSig_Company): the template's id for the default company (as
	 * before companies), {id}@c{company} for the others, whose brand colours
	 * and social rows can differ. Made when the company is saved.
	 */
	public static function slot( $tpl_id, $co = null ) {
		$co  = null === $co ? SB_EmailSig_Company::current() : (int) $co;
		$def = SB_EmailSig_Company::default_id();

		return ( ! $co || $co === $def ) ? (string) $tpl_id : $tpl_id . '@c' . $co;
	}

	/** A template's icons for the company being drawn, else the default company's. */
	private static function mine( array $all, $tpl_id, $kind ) {
		$slot = self::slot( $tpl_id );

		return isset( $all[ $slot ][ $kind ] ) ? (array) $all[ $slot ][ $kind ] : (array) ( $all[ $tpl_id ][ $kind ] ?? [] );
	}

	public static function contact_url( array $tpl, $name ) {
		$all  = (array) get_option( self::OPTION, [] );
		$file = self::mine( $all, $tpl['id'], 'contact' )[ $name ] ?? '';
		$dir  = self::dir();

		if ( $file && file_exists( $dir['path'] . '/' . $file ) ) {
			return $dir['url'] . '/' . $file;
		}

		// A Custom SVG not drawn yet has no shipped PNG to fall back on.
		if ( isset( self::custom_names()[ $name ] ) ) {
			return '';
		}

		return SB_EmailSig_Templates::asset_url( $tpl, 'icons/' . $name . '.png' );
	}

	/**
	 * Icons in a row's or block's own colour (Sam, 31 October 2026), drawn
	 * on the Layout page: [ variant key ][ 'contact' ][ name ] => file, and
	 * [ 'social' ][ row ] => [ file, svg ] (the svg's md5 when drawn, so a
	 * social row changed since falls back to the usual icon). The key names
	 * what it was drawn as (SB_EmailSig_Layout::variant_key()), so a change
	 * of size or colour misses it and the usual icon shows instead.
	 */
	private static function variant( array $all, $tpl_id, $key, $kind ) {
		$slot = self::slot( $tpl_id );

		return $all[ $slot ]['variants'][ $key ][ $kind ] ?? ( $all[ $tpl_id ]['variants'][ $key ][ $kind ] ?? [] );
	}

	/** A contact icon in a variant's colour, or '' when there is none (yet). */
	public static function contact_variant_url( array $tpl, $name, $key ) {
		$all  = (array) get_option( self::OPTION, [] );
		$file = self::variant( $all, $tpl['id'], $key, 'contact' )[ $name ] ?? '';
		$dir  = self::dir();

		return ( $file && file_exists( $dir['path'] . '/' . $file ) ) ? $dir['url'] . '/' . $file : '';
	}

	/** A social icon in a variant's colour, or '' (none, or its row has a different icon now). */
	public static function social_variant_url( $tpl_id, $row, $key ) {
		static $svgs = [];

		$all  = (array) get_option( self::OPTION, [] );
		$item = self::variant( $all, $tpl_id, $key, 'social' )[ $row ] ?? null;
		$dir  = self::dir();

		if ( ! is_array( $item ) || empty( $item['file'] ) || ! file_exists( $dir['path'] . '/' . $item['file'] ) ) {
			return '';
		}

		$co = SB_EmailSig_Company::current();
		if ( ! isset( $svgs[ $co ] ) ) {
			$svgs[ $co ] = array_map( 'md5', self::social_svgs() );
		}

		return ( $svgs[ $co ][ $row ] ?? '' ) === ( $item['svg'] ?? '' ) ? $dir['url'] . '/' . $item['file'] : '';
	}

	/**
	 * Save one template's variant icons for the company being drawn ($co:
	 * another): [ key => [ 'contact' => [ name => data URL ], 'social' =>
	 * [ data URL per row ] ] ]. They replace the ones before.
	 */
	public static function save_variants( $tpl_id, array $variants, $co = null ) {
		$tpl = SB_EmailSig_Templates::get( $tpl_id );

		if ( ! $tpl ) {
			return;
		}

		$owner = null === $co ? SB_EmailSig_Company::current() : (int) $co;
		if ( SB_EmailSig_Switch::is_off( $owner ? $owner : SB_EmailSig_Company::default_id() ) ) {
			return;
		}

		$slot  = self::slot( $tpl_id, $co );
		$all   = (array) get_option( self::OPTION, [] );
		$names = (array) ( SB_EmailSig_Styles::defaults( $tpl )['icons']['names'] ?? [] );
		$svgs  = SB_EmailSig_Company::with(
			$owner ? $owner : SB_EmailSig_Company::default_id(),
			function () {
				return array_map( 'md5', self::social_svgs() );
			}
		);
		$out   = [];

		foreach ( array_slice( $variants, 0, 40, true ) as $key => $set ) {
			$key = (string) $key;

			if ( ! preg_match( '/^[cs][0-9]{1,3}-[0-9a-f]{6}-(x|[0-9a-f]{6})-[0-9]{1,3}$/', $key ) || ! is_array( $set ) ) {
				continue;
			}

			$entry = [];

			if ( $key[0] === 'c' && isset( $set['contact'] ) && is_array( $set['contact'] ) ) {
				foreach ( $names as $name ) {
					$bin = self::png( $set['contact'][ $name ] ?? '' );
					if ( $bin !== '' ) {
						$entry['contact'][ $name ] = self::write( $slot, 'contact', $key . '-' . $name, $bin );
					}
				}
			}

			if ( $key[0] === 's' && isset( $set['social'] ) && is_array( $set['social'] ) ) {
				foreach ( array_slice( array_values( $set['social'] ), 0, 30 ) as $i => $data_url ) {
					$bin = self::png( $data_url );
					if ( $bin !== '' ) {
						$entry['social'][ $i ] = [
							'file' => self::write( $slot, 'social', $key . '-' . $i, $bin ),
							'svg'  => $svgs[ $i ] ?? '',
						];
					}
				}
			}

			if ( $entry ) {
				$out[ $key ] = $entry;
			}
		}

		$all[ $slot ]             = isset( $all[ $slot ] ) && is_array( $all[ $slot ] ) ? $all[ $slot ] : [];
		$all[ $slot ]['variants'] = $out;
		update_option( self::OPTION, $all, false );

		self::tidy( $all );
	}

	public static function social_url( $tpl_id, $row ) {
		$all  = (array) get_option( self::OPTION, [] );
		$file = self::mine( $all, $tpl_id, 'social' )[ $row ] ?? '';
		$dir  = self::dir();

		return ( $file && file_exists( $dir['path'] . '/' . $file ) ) ? $dir['url'] . '/' . $file : '';
	}

	/** The SVG for each saved social row, in row order ('' where there is none). */
	public static function social_svgs() {
		$rows = get_field( 'sb_emailsig_social_media', SB_EmailSig_Company::source() );
		$out  = [];

		foreach ( ( is_array( $rows ) ? array_values( $rows ) : [] ) as $row ) {
			$icon  = (string) ( $row['social_media_icon'] ?? '' );
			$out[] = $icon === 'custom' ? self::file_svg( $row['social_media_icon_file'] ?? 0 ) : self::builtin_svg( $icon );
		}

		return $out;
	}

	/**
	 * A built-in icon's SVG for a Social Media row: the brand ones from
	 * assets/social, the rest (phone, mail ...) from the first template's
	 * icons (Sam, 2 November 2026).
	 */
	public static function builtin_svg( $key ) {
		$key  = sanitize_key( $key );
		$file = SB_EMAILSIG_DIR . 'assets/social/' . $key . '.svg';

		if ( ! file_exists( $file ) ) {
			$tpls = SB_EmailSig_Templates::all();
			$tpl  = $tpls ? reset( $tpls ) : null;
			$file = $tpl ? $tpl['path'] . 'icons/' . $key . '.svg' : '';
		}

		return $file && file_exists( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore
	}

	/**
	 * Custom SVGs on Info List items (Sam, 2 November 2026), the company's
	 * and every person's: [ 'u<attachment id>' => attachment id ], for rows
	 * whose icon is Custom SVG and whose file is an SVG that can be used.
	 * They join the template's contact icon names (Styles::defaults()), so
	 * they're drawn and kept like the others, and Icons::stale() has a
	 * new one drawn on the next admin visit.
	 */
	public static function custom_names() {
		static $out = null;

		if ( $out !== null ) {
			return $out;
		}

		global $wpdb;
		$ids = [];

		// The Additional Info List Items' Custom SVGs, and the Branding tab's
		// Info List Icons' (sb_emailsig_line_*_icon_file, Sam, 2 November 2026).
		foreach ( [ [ 'sb\\_emailsig\\_%', '%' . $wpdb->esc_like( 'info_icon_file' ) ], [ 'sb\\_emailsig\\_line\\_%', '%' . $wpdb->esc_like( '_icon_file' ) ] ] as $q ) {
			$ids = array_merge(
				$ids,
				(array) $wpdb->get_col( $wpdb->prepare( "SELECT f.meta_value FROM {$wpdb->postmeta} f INNER JOIN {$wpdb->postmeta} i ON i.post_id = f.post_id AND i.meta_key = LEFT( f.meta_key, CHAR_LENGTH( f.meta_key ) - 5 ) WHERE f.meta_key LIKE %s AND f.meta_key LIKE %s AND i.meta_value = 'custom' AND f.meta_value <> ''", $q[0], $q[1] ) ),
				(array) $wpdb->get_col( $wpdb->prepare( "SELECT f.option_value FROM {$wpdb->options} f INNER JOIN {$wpdb->options} i ON i.option_name = LEFT( f.option_name, CHAR_LENGTH( f.option_name ) - 5 ) WHERE f.option_name LIKE %s AND f.option_name LIKE %s AND i.option_value = 'custom' AND f.option_value <> ''", 'options\\_' . $q[0], $q[1] ) )
			);
		}

		$out = [];

		foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
			if ( $id && get_post_mime_type( $id ) === 'image/svg+xml' ) {
				$out[ 'u' . $id ] = $id;
			}
		}

		ksort( $out );

		return $out;
	}

	/** A Custom SVG's icon name (u<attachment id>), or '' for none or one that isn't an SVG. */
	public static function custom_name( $id ) {
		$id = absint( $id );

		return $id && get_post_mime_type( $id ) === 'image/svg+xml' ? 'u' . $id : '';
	}

	/**
	 * Companies whose drawn contact icons miss some their templates have now
	 * (icons added in an update), which would show in the shipped colour.
	 * The Email Signatures pages draw those for administrators
	 * (assets/icons-fill.js, Sam, 1 November 2026).
	 */
	public static function stale() {
		$all  = (array) get_option( self::OPTION, [] );
		$tpls = SB_EmailSig_Templates::all();
		$out  = [];

		foreach ( SB_EmailSig_Company::all() as $co ) {
			if ( SB_EmailSig_Switch::is_off( $co ) ) {
				continue;
			}

			foreach ( $tpls as $id => $tpl ) {
				$slot = self::slot( $id, $co );

				if ( ! isset( $all[ $slot ]['contact'] ) || ! is_array( $all[ $slot ]['contact'] ) ) {
					continue;
				}

				$names = (array) ( SB_EmailSig_Styles::defaults( $tpl )['icons']['names'] ?? [] );

				if ( (int) ( $all[ $slot ]['contact_rev'] ?? 1 ) < self::CONTACT_REV || array_diff( $names, array_keys( $all[ $slot ]['contact'] ) ) ) {
					$out[] = $co;
					break;
				}
			}
		}

		return $out;
	}

	/** What icons-fill.js needs to draw company $co's contact icons for every template. */
	public static function fill_data( $co ) {
		return SB_EmailSig_Company::with(
			$co,
			function () {
				$tpls = [];

				foreach ( SB_EmailSig_Styles::social_styles() as $id => $st ) {
					if ( ! empty( $st['contact'] ) ) {
						$tpls[ $id ] = $st['contact'];
					}
				}

				return [
					'templates' => (object) $tpls,
					'brand'     => (object) wp_list_pluck( SB_EmailSig_Styles::brand_all(), 'hex' ),
				];
			}
		);
	}

	public static function builtin_svgs() {
		$out = [];

		foreach ( array_keys( SB_EmailSig_Fields::social_icons() ) as $key ) {
			if ( $key !== 'custom' ) {
				$out[ $key ] = self::builtin_svg( $key );
			}
		}

		return $out;
	}

	/* ------------------------------------------------------------------ *
	 * Saving
	 * ------------------------------------------------------------------ */

	/** A data URL holding a real PNG of a sensible size, as bytes, or ''. */
	private static function png( $data_url ) {
		if ( ! is_string( $data_url ) || strpos( $data_url, 'data:image/png;base64,' ) !== 0 ) {
			return '';
		}

		$bin  = base64_decode( substr( $data_url, 22 ), true ); // phpcs:ignore
		$info = $bin ? @getimagesizefromstring( $bin ) : false; // phpcs:ignore

		return ( $info && IMAGETYPE_PNG === $info[2] && $info[0] <= self::MAX_PX && $info[1] <= self::MAX_PX ) ? $bin : '';
	}

	private static function write( $tpl_id, $kind, $key, $bin ) {
		$dir  = self::dir();
		$name = sanitize_key( $tpl_id ) . '-' . $kind . '-' . sanitize_key( (string) $key ) . '-' . substr( md5( $bin ), 0, 10 ) . '.png';

		wp_mkdir_p( $dir['path'] );

		if ( ! file_exists( $dir['path'] . '/' . $name ) ) {
			file_put_contents( $dir['path'] . '/' . $name, $bin ); // phpcs:ignore
		}

		return $name;
	}

	/**
	 * Save one template's icons. $payload: [ 'contact' => [ name => data URL ],
	 * 'social' => [ data URL per row ] ]; $kinds says which to replace. For
	 * the company being drawn ($co: another), in its slot().
	 */
	public static function save( $tpl_id, array $payload, array $kinds, $co = null ) {
		$tpl = SB_EmailSig_Templates::get( $tpl_id );

		if ( ! $tpl ) {
			return;
		}

		// Not for a company switched off: its icons are put aside (SB_EmailSig_Switch).
		$owner = null === $co ? SB_EmailSig_Company::current() : (int) $co;
		if ( SB_EmailSig_Switch::is_off( $owner ? $owner : SB_EmailSig_Company::default_id() ) ) {
			return;
		}

		$slot = self::slot( $tpl_id, $co );
		$all  = (array) get_option( self::OPTION, [] );
		$mine = isset( $all[ $slot ] ) && is_array( $all[ $slot ] ) ? $all[ $slot ] : [];

		if ( in_array( 'contact', $kinds, true ) && isset( $payload['contact'] ) && is_array( $payload['contact'] ) ) {
			$names   = (array) ( SB_EmailSig_Styles::defaults( $tpl )['icons']['names'] ?? [] );
			$contact = [];

			foreach ( $names as $name ) {
				$bin = self::png( $payload['contact'][ $name ] ?? '' );

				if ( $bin !== '' ) {
					$contact[ $name ] = self::write( $slot, 'contact', $name, $bin );
				}
			}

			$mine['contact']     = $contact;
			$mine['contact_rev'] = self::CONTACT_REV;
		}

		if ( in_array( 'social', $kinds, true ) && isset( $payload['social'] ) && is_array( $payload['social'] ) ) {
			$social = [];

			foreach ( array_slice( array_values( $payload['social'] ), 0, 30 ) as $i => $data_url ) {
				$bin          = self::png( $data_url );
				$social[ $i ] = $bin !== '' ? self::write( $slot, 'social', $i, $bin ) : '';
			}

			$mine['social'] = $social;
		}

		$all[ $slot ] = $mine;
		update_option( self::OPTION, $all, false );

		self::tidy( $all );
	}

	/** A template deleted: its icons go, and their files unless another uses them. */
	public static function forget( $tpl_id ) {
		$all  = (array) get_option( self::OPTION, [] );
		$gone = false;

		foreach ( array_keys( $all ) as $key ) {
			if ( (string) $key === (string) $tpl_id || strpos( (string) $key, $tpl_id . '@c' ) === 0 ) {
				unset( $all[ $key ] );
				$gone = true;
			}
		}

		if ( $gone ) {
			update_option( self::OPTION, $all, false );
			self::tidy( $all );
		}
	}

	/** Remove icon files no template uses any more. */
	private static function tidy( array $all ) {
		$used = [];

		foreach ( $all as $tpl ) {
			foreach ( [ 'contact', 'social' ] as $kind ) {
				foreach ( (array) ( $tpl[ $kind ] ?? [] ) as $file ) {
					if ( $file ) {
						$used[ $file ] = true;
					}
				}
			}

			// Variant icons (a row's or block's own colour).
			foreach ( (array) ( $tpl['variants'] ?? [] ) as $set ) {
				foreach ( (array) ( $set['contact'] ?? [] ) as $file ) {
					$used[ (string) $file ] = true;
				}
				foreach ( (array) ( $set['social'] ?? [] ) as $item ) {
					$used[ (string) ( $item['file'] ?? '' ) ] = true;
				}
			}
		}

		$dir = self::dir();

		foreach ( (array) glob( $dir['path'] . '/*.png' ) as $file ) {
			if ( ! isset( $used[ basename( $file ) ] ) ) {
				wp_delete_file( $file );
			}
		}
	}

	/** A company saved on its page (SB_EmailSig_Companies): its icons, from the form's builder. */
	public static function settings_saved( $post_id ) {
		if ( ! is_numeric( $post_id ) || get_post_type( (int) $post_id ) !== SB_EmailSig_Company::CPT || ! SB_EmailSig_Companies::is_request() ) {
			return;
		}

		if ( ! isset( $_POST['sb_emailsig_icons_nonce'], $_POST['sb_emailsig_icons'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sb_emailsig_icons_nonce'] ) ), 'sb_emailsig_icons' ) ) {
			return;
		}

		$payload = json_decode( wp_unslash( $_POST['sb_emailsig_icons'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( ! is_array( $payload ) ) {
			return; // The script did not run: keep the icons there are.
		}

		foreach ( $payload as $tpl_id => $icons ) {
			if ( is_array( $icons ) ) {
				// Contact icons too: their colour can come from the brand colours.
				self::save( sanitize_key( (string) $tpl_id ), $icons, [ 'social', 'contact' ], (int) $post_id );
			}
		}
	}

	/* ------------------------------------------------------------------ *
	 * Button icons (Sam, 2 November 2026)
	 *
	 * An Additional Button's icon is one of the Info List's, drawn in the
	 * button's text colour (solid or outline) at the button's line height.
	 * That depends only on the icon, size and colour, so they are kept once
	 * for the site: [ button_key() => file ] in BUTTON_OPTION, files in
	 * icons/buttons (out of tidy()'s way). The icons the companies' buttons
	 * need (button_needed()) and aren't drawn yet are drawn by an
	 * administrator's browser on any Email Signatures page
	 * (assets/button-icons.js) and kept through button_icons_ajax(), which
	 * also drops ones no button needs any more.
	 * ------------------------------------------------------------------ */

	const BUTTON_OPTION = 'sb_emailsig_button_icons';

	/** What a button icon is drawn as: its name, size and colour, such as file-text-16-ffffff. */
	public static function button_key( $name, $size, $hex ) {
		return sanitize_key( (string) $name ) . '-' . (int) $size . '-' . strtolower( ltrim( (string) $hex, '#' ) );
	}

	/** A button's icon size: its text line's height, so an icon button is as tall as one without. */
	public static function button_size( array $btn ) {
		return max( 10, (int) ( $btn['line'] ?? 16 ) );
	}

	private static function button_dir() {
		$dir = self::dir();

		return [
			'path' => $dir['path'] . '/buttons',
			'url'  => $dir['url'] . '/buttons',
		];
	}

	/** A button icon's address, '' when it isn't drawn yet. */
	public static function button_url( $key ) {
		$file = ( (array) get_option( self::BUTTON_OPTION, [] ) )[ (string) $key ] ?? '';
		$dir  = self::button_dir();

		return ( $file && file_exists( $dir['path'] . '/' . $file ) ) ? $dir['url'] . '/' . $file : '';
	}

	/**
	 * Every button icon the companies' buttons need now, for every template:
	 * [ key => [ name, size, colour ] ]. Companies switched off are skipped.
	 */
	public static function button_needed() {
		$out = [];
		$cos = SB_EmailSig_Company::all();

		foreach ( $cos ? $cos : [ 0 ] as $co ) {
			if ( $co && SB_EmailSig_Switch::is_off( $co ) ) {
				continue;
			}

			SB_EmailSig_Company::with(
				$co,
				function () use ( &$out ) {
					$rows = get_field( 'sb_emailsig_buttons', SB_EmailSig_Company::source() );
					$used = [];

					foreach ( is_array( $rows ) ? $rows : [] as $row ) {
						// A built-in icon or a Custom SVG's u<id> (Data::button_icon() checks both).
						$icon = SB_EmailSig_Data::button_icon( $row );
						$look = empty( $row['button_outline'] ) ? 'solid' : 'outline';
						// The button's own colour can change its text's, which the icon is drawn in.
						$own = SB_EmailSig_Styles::hex( SB_EmailSig_Styles::clean_colour( $row['button_colour'] ?? '' ) );

						if ( $icon !== '' ) {
							$used[ $icon . '|' . $look . '|' . $own ] = [ $icon, $look, $own ];
						}
					}

					if ( ! $used ) {
						return;
					}

					foreach ( SB_EmailSig_Templates::all() as $tpl ) {
						$btn = SB_EmailSig_Styles::resolve( $tpl )['buttons'] ?? null;

						if ( ! $btn ) {
							continue;
						}

						$size = self::button_size( $btn );

						foreach ( $used as $u ) {
							$hex = (string) ( SB_EmailSig_Styles::button_look( $btn, $u[1] === 'outline', $u[2] )['text'] ?? '' );

							if ( $hex !== '' ) {
								$out[ self::button_key( $u[0], $size, $hex ) ] = [
									'name'   => $u[0],
									'size'   => $size,
									'colour' => $hex,
								];
							}
						}
					}
				}
			);
		}

		return $out;
	}

	/** What assets/button-icons.js needs on this page, or [] when there are no button icons. */
	public static function button_config() {
		$needed = self::button_needed();
		$have   = (array) get_option( self::BUTTON_OPTION, [] );
		$admin  = current_user_can( 'manage_options' );
		$prune  = (bool) array_diff_key( $have, $needed );
		$miss   = [];

		foreach ( $needed as $key => $n ) {
			if ( self::button_url( $key ) === '' ) {
				$miss[ $key ] = $n;
			}
		}

		if ( ! $needed && ! ( $admin && $prune ) ) {
			return [];
		}

		$tpls = SB_EmailSig_Templates::all();
		$tpl  = $tpls ? reset( $tpls ) : null;
		$svgs = [];

		foreach ( $needed as $n ) {
			// A Custom SVG (u<attachment id>): the uploaded file.
			if ( ! isset( $svgs[ $n['name'] ] ) && preg_match( '/^u(\d+)$/', $n['name'], $m ) ) {
				$svgs[ $n['name'] ] = self::file_svg( (int) $m[1] );
				continue;
			}

			$file = $tpl ? $tpl['path'] . 'icons/' . $n['name'] . '.svg' : '';

			if ( ! isset( $svgs[ $n['name'] ] ) && $file && file_exists( $file ) ) {
				$svgs[ $n['name'] ] = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}

		return [
			'svgs'    => (object) $svgs,
			'missing' => (object) ( $admin ? $miss : [] ),
			'save'    => $admin && ( $miss || $prune ),
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'nonce'   => $admin ? wp_create_nonce( 'sb_emailsig_button_icons' ) : '',
		];
	}

	/** Button icons drawn by button-icons.js: [ key => data URL ]. Only needed ones are kept. */
	public static function button_icons_ajax() {
		check_ajax_referer( 'sb_emailsig_button_icons', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		$icons = json_decode( wp_unslash( (string) ( $_POST['icons'] ?? '' ) ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- each one is checked as a PNG.

		self::save_buttons( is_array( $icons ) ? array_slice( $icons, 0, 200, true ) : [] );
		wp_send_json_success();
	}

	/** Keep the needed ones of $icons ([ key => data URL ]), and drop icons no button needs. */
	public static function save_buttons( array $icons ) {
		$needed = self::button_needed();
		$keep   = array_intersect_key( (array) get_option( self::BUTTON_OPTION, [] ), $needed );
		$dir    = self::button_dir();

		foreach ( $icons as $key => $data_url ) {
			$key = (string) $key;
			$bin = isset( $needed[ $key ] ) ? self::png( $data_url ) : '';

			if ( $bin === '' ) {
				continue;
			}

			$name = sanitize_key( $key ) . '-' . substr( md5( $bin ), 0, 10 ) . '.png';

			wp_mkdir_p( $dir['path'] );

			if ( ! file_exists( $dir['path'] . '/' . $name ) ) {
				file_put_contents( $dir['path'] . '/' . $name, $bin ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}

			$keep[ $key ] = $name;
		}

		update_option( self::BUTTON_OPTION, $keep, false );

		$used = array_flip( $keep );

		foreach ( (array) glob( $dir['path'] . '/*.png' ) as $file ) {
			if ( ! isset( $used[ basename( $file ) ] ) ) {
				wp_delete_file( $file );
			}
		}
	}
}
