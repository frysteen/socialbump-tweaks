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
 * - social icons from the Edit styles page (that template) and from the
 *   Settings page (every template, since the rows changed).
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
	const SCALE  = 3;
	const MAX_PX = 400;

	/** The Custom SVG field's key: ACF sends it with uploads made from that field. */
	const SVG_FIELD = 'field_sb_emailsig_social_media_icon_file';

	public static function init() {
		add_action( 'acf/save_post', [ __CLASS__, 'settings_saved' ], 20 );

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

		return $field === self::SVG_FIELD && current_user_can( 'manage_options' );
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

	public static function builtin_svg( $key ) {
		$file = SB_EMAILSIG_DIR . 'assets/social/' . sanitize_key( $key ) . '.svg';

		return file_exists( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore
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

			$mine['contact'] = $contact;
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

	/** The Settings page sends every template's social and contact icons, drawn from the rows and brand colours as they now are. */
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
}
