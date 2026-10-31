<?php
/**
 * The campaign banner: one banner in the signature that can be changed any
 * time, in signatures already pasted into Mail. Settings, Campaign Banner tab.
 *
 * The signature always has the same image address, /?sbbanner=1, and (with
 * Track Clicks on) the same link, the short link with key c. This site
 * decides what they are when the email is opened or the banner clicked:
 * the first campaign running today (start and end dates, either left blank
 * for open ended), else the default banner, else a see-through PNG one
 * pixel tall at the banner's width, so the space all but disappears. The
 * image has a width and no height in the signature, so a banner of any
 * height shows at its own shape.
 *
 * Mail apps keep their own copies of images (Gmail especially), so a change
 * can take a while to show there, and an old email shows the banner of the
 * day it's opened.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Campaign {

	const QUERY = 'sbbanner';

	/** The banner's width when never saved. */
	const WIDTH = 600;

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ), 0 );
		add_filter( 'acf/load_field/key=field_sb_emailsig_campaign_now', array( __CLASS__, 'now_message' ) );
	}

	/** Where the fields are read: the company being drawn (SB_EmailSig_Company). */
	private static function src() {
		return class_exists( 'SB_EmailSig_Company' ) ? SB_EmailSig_Company::source() : 'option';
	}

	public static function enabled() {
		return (bool) get_field( 'sb_emailsig_campaign_show', self::src() );
	}

	/**
	 * Read straight from the option, not get_field(): the width field's
	 * load_field filter (SB_EmailSig_Fields::campaign_help) calls this, and
	 * get_field() loads that field again, which looped until PHP ran out of
	 * stack once the setting was saved (29 October 2026).
	 */
	public static function width() {
		$src = self::src();
		$w   = (int) ( $src === 'option' ? get_option( 'options_sb_emailsig_campaign_width', 0 ) : get_post_meta( (int) $src, 'sb_emailsig_campaign_width', true ) );

		return $w > 0 ? min( 1200, $w ) : self::WIDTH;
	}

	/**
	 * The banner's fixed image address: ?{QUERY}=1 for the default company
	 * (as before companies, so pasted signatures keep it), c{id} for others.
	 */
	public static function image_url() {
		$src = self::src();
		$def = class_exists( 'SB_EmailSig_Company' ) ? SB_EmailSig_Company::default_id() : 0;

		return add_query_arg( self::QUERY, ( $src === 'option' || (int) $src === $def ) ? '1' : 'c' . (int) $src, home_url( '/' ) );
	}

	/**
	 * What shows today: [ image (attachment id), link, name, label, until
	 * (Ymd or '') ], or null for nothing.
	 */
	public static function current() {
		$today = wp_date( 'Ymd' );

		foreach ( (array) get_field( 'sb_emailsig_campaigns', self::src() ) as $row ) {
			$image = (int) ( $row['campaign_image'] ?? 0 );
			$start = preg_replace( '/\D/', '', (string) ( $row['campaign_start'] ?? '' ) );
			$end   = preg_replace( '/\D/', '', (string) ( $row['campaign_end'] ?? '' ) );

			if ( ! $image || ( $start !== '' && $today < $start ) || ( $end !== '' && $today > $end ) ) {
				continue;
			}

			$name = trim( (string) ( $row['campaign_name'] ?? '' ) );

			return array(
				'image' => $image,
				'link'  => trim( (string) ( $row['campaign_link'] ?? '' ) ),
				'name'  => 'campaign-' . ( $name !== '' ? $name : 'banner' ),
				'label' => 'Campaign banner' . ( $name !== '' ? ': ' . $name : '' ),
				'until' => $end,
			);
		}

		$image = (int) get_field( 'sb_emailsig_campaign_default_image', self::src() );

		if ( $image ) {
			return array(
				'image' => $image,
				'link'  => trim( (string) get_field( 'sb_emailsig_campaign_default_link', self::src() ) ),
				'name'  => 'campaign-default',
				'label' => 'Campaign banner: default',
				'until' => '',
			);
		}

		return null;
	}

	/**
	 * The banner for a signature: [ image, width, link ], or null when it's
	 * switched off. $link is the short link (or the current link when
	 * clicks aren't tracked, which can't change later).
	 */
	public static function block( $link ) {
		if ( ! self::enabled() ) {
			return null;
		}

		return array(
			'image' => self::image_url(),
			'width' => self::width(),
			'link'  => (string) $link,
		);
	}

	/** The image: today's banner, or the see-through strip. */
	public static function maybe_serve() {
		if ( ! isset( $_GET[ self::QUERY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'X-LiteSpeed-Cache-Control: no-cache', true );
		// Mail apps may keep it a few minutes; never longer from here.
		header( 'Cache-Control: public, max-age=300', true );

		// Its company: c{id}, else the default.
		$which = sanitize_text_field( wp_unslash( $_GET[ self::QUERY ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( preg_match( '/^c(\d+)$/', $which, $m ) && class_exists( 'SB_EmailSig_Company' ) && SB_EmailSig_Company::valid( (int) $m[1] ) ) {
			SB_EmailSig_Company::enter( (int) $m[1] );
		}

		// Its company switched off (SB_EmailSig_Switch): no banner at all.
		if ( SB_EmailSig_Switch::is_off( SB_EmailSig_Company::current() ) ) {
			status_header( 404 );
			exit;
		}

		$now  = self::current();
		$file = $now ? self::file( $now['image'] ) : '';

		if ( $file !== '' ) {
			$type = wp_check_filetype( $file );
			header( 'Content-Type: ' . ( $type['type'] ?: 'image/png' ) );
			header( 'Content-Length: ' . filesize( $file ) );
			readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			exit;
		}

		$png = self::spacer( self::width() );
		header( 'Content-Type: image/png' );
		header( 'Content-Length: ' . strlen( $png ) );
		echo $png; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/** The image's file at a size sharp enough for twice the banner's width, else the full one. */
	private static function file( $id ) {
		$img = SB_EmailSig_Data::image( $id );

		if ( ! $img ) {
			return '';
		}

		$url  = SB_EmailSig_Data::src( $img, self::width() );
		$up   = wp_get_upload_dir();
		$path = strpos( $url, $up['baseurl'] ) === 0 ? $up['basedir'] . substr( $url, strlen( $up['baseurl'] ) ) : '';

		if ( $path === '' || ! is_readable( $path ) ) {
			$path = (string) get_attached_file( $id );
		}

		return is_readable( $path ) ? $path : '';
	}

	/**
	 * A see-through PNG $width wide and one pixel tall, made by hand so it
	 * needs no GD: shown at the banner's width it is one pixel high.
	 */
	public static function spacer( $width ) {
		$width = max( 1, (int) $width );
		$chunk = function ( $type, $data ) {
			return pack( 'N', strlen( $data ) ) . $type . $data . pack( 'N', crc32( $type . $data ) );
		};

		// 8 bit RGBA; one row: filter byte 0, then every pixel 0,0,0,0.
		$ihdr = pack( 'NNCCCCC', $width, 1, 8, 6, 0, 0, 0 );
		$idat = gzcompress( "\0" . str_repeat( "\0\0\0\0", $width ) );

		return "\x89PNG\r\n\x1a\n" . $chunk( 'IHDR', $ihdr ) . $chunk( 'IDAT', $idat ) . $chunk( 'IEND', '' );
	}

	/** The Campaign Banner tab's note: what shows today. */
	public static function now_message( $field ) {
		if ( ! function_exists( 'get_field' ) || ! is_admin() ) {
			return $field;
		}

		$now = self::current();

		if ( ! $now ) {
			$field['message'] = 'Showing now: nothing (no campaign running and no default banner).';
		} else {
			$until            = $now['until'] !== '' ? ', until ' . wp_date( 'j M Y', strtotime( $now['until'] ) ) : '';
			$field['message'] = 'Showing now: ' . esc_html( preg_replace( '/^Campaign banner: ?/', '', $now['label'] ) ) . esc_html( $until ) . '.';
		}

		return $field;
	}
}
