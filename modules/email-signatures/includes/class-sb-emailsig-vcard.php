<?php
/**
 * "Save my contact details" link: serves a vCard (.vcf) for a person.
 * Tapping it on an iPhone opens the add contact sheet; on a Mac it opens
 * Contacts. Uses a query string so no rewrite rules or server config needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_VCard {

	const QUERY = 'sb_vcard';

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ), 0 );
	}

	/** The link, with the template it's in (t) for the click reports. */
	public static function url( $post_id, $tpl_id = '' ) {
		$args = array( self::QUERY => (int) $post_id );

		if ( ctype_digit( (string) $tpl_id ) && (int) $tpl_id ) {
			$args['t'] = (int) $tpl_id;
		}

		return add_query_arg( $args, home_url( '/' ) );
	}

	public static function maybe_serve() {
		if ( ! isset( $_GET[ self::QUERY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$post_id = absint( $_GET[ self::QUERY ] ); // phpcs:ignore WordPress.Security.NonceVerification
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || SB_EMAILSIG_CPT !== $post->post_type || 'publish' !== $post->post_status || SB_EmailSig_Switch::person_off( $post_id ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		// Counted with the link clicks, as v.
		SB_EmailSig_Tracking::record( $post_id, 'v', 'Save contact', isset( $_GET['t'] ) ? absint( $_GET['t'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification

		$d        = SB_EmailSig_Data::get( $post_id );
		$filename = sanitize_file_name( ( $d['name'] ? $d['name'] : 'contact' ) . '.vcf' );

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Content-Type: text/vcard; charset=utf-8' );
		header( 'Content-Disposition: inline; filename="' . $filename . '"' );

		echo self::build( $d ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public static function build( array $d ) {
		$name  = $d['name'];
		// Their own first and last names (Sam, 31 October 2026), else the name split.
		$first = (string) ( $d['first_name'] ?? '' );
		$last  = (string) ( $d['last_name'] ?? '' );
		if ( $first === '' && $last === '' ) {
			$parts = preg_split( '/\s+/', $name );
			$last  = count( $parts ) > 1 ? array_pop( $parts ) : '';
			$first = implode( ' ', $parts );
		}

		$lines   = array();
		$lines[] = 'BEGIN:VCARD';
		$lines[] = 'VERSION:3.0';
		$lines[] = 'N:' . self::esc( $last ) . ';' . self::esc( $first ) . ';;;';
		$lines[] = 'FN:' . self::esc( $name );
		if ( $d['company'] ) {
			$lines[] = 'ORG:' . self::esc( $d['company'] );
		}
		if ( $d['job_title'] ) {
			$lines[] = 'TITLE:' . self::esc( $d['job_title'] );
		}
		if ( $d['mobile_link'] ) {
			$lines[] = 'TEL;TYPE=CELL,VOICE:' . substr( $d['mobile_link'], 4 );
		}
		if ( $d['phone_link'] ) {
			$lines[] = 'TEL;TYPE=WORK,VOICE:' . substr( $d['phone_link'], 4 );
		}
		if ( $d['email'] ) {
			$lines[] = 'EMAIL;TYPE=INTERNET,WORK:' . self::esc( $d['email'] );
		}
		if ( $d['website'] ) {
			$lines[] = 'URL;TYPE=WORK:' . $d['website'];
		}
		if ( $d['address_lines'] ) {
			// The address is free text, so it all goes in the street part, one line each.
			$lines[] = 'ADR;TYPE=WORK:;;' . self::esc( implode( "\n", $d['address_lines'] ) ) . ';;;;';
		}
		$jpg = SB_EmailSig_Photo::jpg_path( $d['id'] );
		if ( $jpg ) {
			$lines[] = 'PHOTO;ENCODING=b;TYPE=JPEG:' . base64_encode( file_get_contents( $jpg ) ); // phpcs:ignore
		}
		$lines[] = 'END:VCARD';

		return implode( "\r\n", array_map( array( __CLASS__, 'fold' ), $lines ) ) . "\r\n";
	}

	private static function esc( $value ) {
		return str_replace( array( '\\', ',', ';', "\r\n", "\n" ), array( '\\\\', '\,', '\;', '\n', '\n' ), (string) $value );
	}

	/** Fold long lines at 75 octets as the vCard spec requires. */
	private static function fold( $line ) {
		if ( strlen( $line ) <= 75 ) {
			return $line;
		}
		// mb_strcut keeps multibyte characters whole.
		$chunk = mb_strcut( $line, 0, 75, 'UTF-8' );
		$out   = $chunk;
		$line  = (string) substr( $line, strlen( $chunk ) );
		while ( '' !== $line ) {
			$chunk = mb_strcut( $line, 0, 74, 'UTF-8' );
			$out  .= "\r\n " . $chunk;
			$line  = (string) substr( $line, strlen( $chunk ) );
		}
		return $out;
	}
}
