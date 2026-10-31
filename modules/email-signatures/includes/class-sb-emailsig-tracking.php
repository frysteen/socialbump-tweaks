<?php
/**
 * Tracking for the signature's links, set on the Tracking tab in
 * Settings.
 *
 * Click tracking (on unless switched off): every web link in a signature is
 * a short link on this site, /?sbgo={person}-{link}-t{template}-{check}
 * (the template the signature was copied from, since 29 October 2026; links
 * made before have no t part and their clicks no template). A click is
 * counted and sent on with a 302 to wherever that link goes now, looked up
 * at the click, so changing a link in Settings changes it in signatures
 * already pasted into Mail. {link} is a letter and a reference: w website,
 * l logo, m map, s social, b button, n banner (s, b and n by the row's fixed
 * id, SB_EmailSig_Fields::ROW_IDS), c campaign banner and p their staff
 * page (photo and name). {check} is 8 characters of an HMAC with the module's own secret, so
 * made up links aren't counted. tel: and mailto: links stay as they are.
 *
 * UTM codes: links to the site's own website (the Website setting or this
 * site, and their subdomains) carry utm_source, _medium, _campaign and
 * _content, {person} being the person's name as a slug and {link} the
 * link's name (logo, website, map, a social's name, button-{label},
 * banner-{n}). With click tracking they're added on the way through.
 * "Other websites too" tags the rest as well. Links already carrying utm_
 * codes are left alone.
 *
 * Clicks are kept in {prefix}sb_emailsig_clicks: when, whose, which link,
 * its label then, the template (0 when the link didn't say), the device
 * type and whether it looked like an email
 * scanner. No IP addresses. HEAD requests, prefetches and logged in editors
 * aren't counted; known scanners and three or more different links of one
 * signature within 10 seconds are marked as scanners and left out of the
 * report. Old clicks are cleared after Keep Report Logs For.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Tracking {

	/** The short link's query string. */
	const QUERY = 'sbgo';

	/** The UTM codes, as set in Settings when never saved. */
	const DEFAULTS = array(
		'source'   => 'email-signature',
		'medium'   => 'email',
		'campaign' => '{person}',
		'content'  => '{link}',
	);

	/** Months clicks are kept for when never saved. */
	const KEEP = 24;

	const DB_VERSION = '2'; // 2: template_id (29 October 2026).
	const DB_OPTION  = 'sb_emailsig_clicks_db';
	const SECRET     = 'sb_emailsig_link_secret';

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_go' ), 0 );
		add_action( 'admin_init', array( __CLASS__, 'install' ) );
		add_action( 'acf/render_field/key=field_sb_emailsig_utm_codes', array( __CLASS__, 'render_codes' ) );
		add_action( 'acf/render_field/key=field_sb_emailsig_utm_reset', array( __CLASS__, 'render_reset' ) );
	}

	/**
	 * The codes the UTM boxes can hold: [ code => what it becomes ]. Each
	 * comes out as a slug (Sam, 30 October 2026: list them all).
	 */
	public static function codes() {
		return array(
			'{person}'     => 'The person\'s name, e.g. jane-smith',
			'{first_name}' => 'First word of the person\'s name, e.g. jane',
			'{link}'       => 'The link clicked: logo, website, button-book-online, banner-1 and so on',
			'{company}'    => 'Their company\'s Company Name, e.g. the-cosmetic-studio',
			'{template}'   => 'The signature template\'s name, e.g. main-signature',
		);
	}

	/** Settings, Tracking tab: every code, click to copy (assets/settings.js, as the Emails tab's merge tags). */
	public static function render_codes() {
		echo '<div class="sb-emailsig-tags">';

		foreach ( self::codes() as $code => $note ) {
			echo '<div class="sb-emailsig-tags__row">';
			echo '<button type="button" class="sb-emailsig-token" data-sb-emailsig-tag="' . esc_attr( $code ) . '" title="Copy ' . esc_attr( $code ) . '"><code>' . esc_html( $code ) . '</code></button>';
			echo '<span class="sb-emailsig-tags__note">' . esc_html( $note ) . '</span>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Settings, Tracking tab: puts the Google Analytics fields back to the
	 * defaults in the form (saved with Save). settings.js shows it only
	 * while they differ.
	 */
	public static function render_reset() {
		$defaults = array( 'utm_on' => 1, 'utm_all' => 0 );
		foreach ( self::DEFAULTS as $key => $value ) {
			$defaults[ 'utm_' . $key ] = $value;
		}

		echo '<button type="button" class="button sb-emailsig-utm-reset" data-sb-emailsig-utm-reset="' . esc_attr( wp_json_encode( $defaults ) ) . '" hidden>' . SB_EmailSig_Admin::icon( 'restore' ) . 'Reset Google Analytics Settings</button>';
	}

	/** The settings, with defaults where never saved. */
	public static function settings() {
		$on     = get_field( 'sb_emailsig_utm_on', 'option' );
		$clicks = get_field( 'sb_emailsig_track_clicks', 'option' );
		$keep   = (int) get_field( 'sb_emailsig_keep_clicks', 'option' );
		$out    = array(
			'on'     => ( null === $on ) ? true : (bool) $on,
			'all'    => (bool) get_field( 'sb_emailsig_utm_all', 'option' ),
			'clicks' => ( null === $clicks ) ? true : (bool) $clicks,
			'keep'   => $keep > 0 ? $keep : self::KEEP,
		);

		foreach ( self::DEFAULTS as $key => $default ) {
			$value       = get_field( 'sb_emailsig_utm_' . $key, 'option' );
			$out[ $key ] = ( null === $value ) ? $default : trim( (string) $value );
		}

		return $out;
	}

	/**
	 * A function that makes a person's links: ( $key, $url, $name ) to the
	 * short link, or $url with UTM codes, or $url as it was. $key as in
	 * SB_EmailSig_Data::targets(), $name the link's name for {link}.
	 */
	public static function linker( $person_id, $person, $tpl_id = '' ) {
		$set = self::settings();

		return function ( $key, $url, $name ) use ( $person_id, $person, $set, $tpl_id ) {
			$url = trim( (string) $url );

			if ( $url === '' ) {
				return '';
			}

			if ( $set['clicks'] && $person_id > 0 && preg_match( '#^https?://#i', $url ) ) {
				return self::go_url( $person_id, $key, $tpl_id );
			}

			return self::tag( $url, $name, $person, $set, $person_id, $tpl_id );
		};
	}

	/** The short link for a person's link, in a template ('' for none). */
	public static function go_url( $person_id, $key, $tpl_id = '' ) {
		$tpl = ctype_digit( (string) $tpl_id ) ? (int) $tpl_id : 0;

		return add_query_arg( self::QUERY, (int) $person_id . '-' . $key . ( $tpl ? '-t' . $tpl : '' ) . '-' . self::check( $person_id, $key, $tpl ), home_url( '/' ) );
	}

	/** A link's check: person and key, and the template when it has one (links made before templates had none). */
	private static function check( $person_id, $key, $tpl = 0 ) {
		return substr( hash_hmac( 'sha256', (int) $person_id . '|' . $key . ( $tpl ? '|' . (int) $tpl : '' ), self::secret() ), 0, 8 );
	}

	/** 8 characters of an HMAC of $data with the module's secret, for other fixed addresses (a person's photo). */
	public static function sign( $data ) {
		return substr( hash_hmac( 'sha256', (string) $data, self::secret() ), 0, 8 );
	}

	/** The module's own secret: wp_salt() can be changed by security plugins, which would break every link. */
	private static function secret() {
		$secret = (string) get_option( self::SECRET, '' );

		if ( $secret === '' ) {
			$secret = wp_generate_password( 40, false, false );
			update_option( self::SECRET, $secret, true );
		}

		return $secret;
	}

	/** A short link's code: [ person, key, template, url, name, label ], or null if it isn't one of ours. */
	public static function resolve( $code ) {
		if ( ! preg_match( '/^(\d+)-([a-z][A-Za-z0-9]*)(?:-t(\d+))?-([a-f0-9]{8})$/', (string) $code, $m ) ) {
			return null;
		}

		$person = (int) $m[1];
		$key    = $m[2];
		$tpl    = (int) $m[3];

		if ( ! hash_equals( self::check( $person, $key, $tpl ), $m[4] ) ) {
			return null;
		}

		// The person's company's links (one short link a request).
		SB_EmailSig_Company::enter( SB_EmailSig_Company::of_person( $person ) );

		$targets = SB_EmailSig_Data::targets();
		$target  = $targets[ $key ] ?? null;

		// Their staff page: the person's own, looked up now.
		if ( $key === 'p' ) {
			$target = array(
				'kind'  => 'p',
				'url'   => SB_EmailSig_Staff::page_url( $person ),
				'name'  => 'profile',
				'label' => 'Staff page',
			);
		}

		// The person's own Additional Info List Item, looked up now.
		if ( ! $target && $key[0] === 'q' ) {
			$target = SB_EmailSig_Data::person_targets( $person )[ $key ] ?? null;
		}

		// A row that had no id yet when the link was made: by its place.
		if ( ! $target && preg_match( '/^([sbn])i(\d+)$/', $key, $k ) ) {
			$same = array_values(
				array_filter(
					$targets,
					function ( $t ) use ( $k ) {
						return $t['kind'] === $k[1];
					}
				)
			);
			$target = $same[ (int) $k[2] ] ?? null;
		}

		return array(
			'person'   => $person,
			'key'      => $key,
			'template' => $tpl,
			'url'      => $target ? $target['url'] : '',
			'name'   => $target ? $target['name'] : '',
			'label'  => $target ? $target['label'] : '',
		);
	}

	/** A short link was opened: count it and send them on. */
	public static function maybe_go() {
		if ( ! isset( $_GET[ self::QUERY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$hit = self::resolve( sanitize_text_field( wp_unslash( $_GET[ self::QUERY ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$url = $hit ? $hit['url'] : '';

		// Their company switched off (SB_EmailSig_Switch): the link goes nowhere.
		if ( $hit && SB_EmailSig_Switch::person_off( $hit['person'] ) ) {
			nocache_headers();
			header( 'X-Robots-Tag: noindex, nofollow', true );
			status_header( 404 );
			echo 'Link not found.';
			exit;
		}

		$person = ( $hit && get_post_type( $hit['person'] ) === SB_EMAILSIG_CPT ) ? trim( (string) get_field( 'sb_emailsig_name', $hit['person'] ) ) : '';

		if ( $url !== '' && $person !== '' ) {
			$url = self::tag( $url, $hit['name'], $person, null, $hit['person'], $hit['template'] );
			self::record( $hit['person'], $hit['key'], $hit['label'], $hit['template'] );
		}

		// A link gone from Settings, or not one of ours: the website.
		if ( $url === '' ) {
			$url = trim( (string) get_field( 'sb_emailsig_website', SB_EmailSig_Company::source() ) ) ?: home_url( '/' );
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'X-LiteSpeed-Cache-Control: no-cache', true );

		wp_redirect( $url, 302, 'SB Email Signatures' ); // phpcs:ignore WordPress.Security.SafeRedirect -- only addresses saved in Settings.
		exit;
	}

	/**
	 * Count a click (or a vCard download, key v) unless click tracking is off,
	 * it isn't a real visit, or it's someone who edits the site.
	 */
	public static function record( $person_id, $key, $label, $tpl_id = 0 ) {
		$set = self::settings();

		if ( ! $set['clicks'] ) {
			return;
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput
		$method  = strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
		$ua      = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
		$purpose = strtolower( (string) ( $_SERVER['HTTP_SEC_PURPOSE'] ?? ( $_SERVER['HTTP_PURPOSE'] ?? ( $_SERVER['HTTP_X_PURPOSE'] ?? '' ) ) ) );
		// phpcs:enable

		if ( $method !== 'GET' || strpos( $purpose, 'prefetch' ) !== false || strpos( $purpose, 'preview' ) !== false ) {
			return;
		}

		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			return;
		}

		self::install();

		global $wpdb;
		$table = self::table();
		$bot   = self::is_scanner( $ua ) ? 1 : 0;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'clicked_at' => current_time( 'mysql', true ),
				'person_id'  => (int) $person_id,
				'link'       => substr( (string) $key, 0, 40 ),
				'label'       => mb_substr( (string) $label, 0, 190 ),
				'template_id' => (int) $tpl_id,
				'device'      => self::device( $ua ),
				'bot'         => $bot,
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s', '%d' )
		);

		// A scanner opens every link of an email at once: three or more
		// different links of one signature within 10 seconds.
		if ( ! $bot ) {
			$since = gmdate( 'Y-m-d H:i:s', time() - 10 );
			// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$links = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT link) FROM {$table} WHERE person_id = %d AND clicked_at >= %s", $person_id, $since ) );

			if ( $links >= 3 ) {
				$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET bot = 1 WHERE person_id = %d AND clicked_at >= %s", $person_id, $since ) );
			}
			// phpcs:enable
		}

		if ( wp_rand( 1, 50 ) === 1 ) {
			self::prune();
		}
	}

	/** Email scanners, link previews and scripts, by their user agent. */
	public static function is_scanner( $ua ) {
		if ( trim( $ua ) === '' ) {
			return true;
		}

		return (bool) preg_match( '/bot\b|bot\/|crawl|spider|slurp|preview|scan|python|curl|wget|httpclient|http-client|go-http|java\/|okhttp|axios|node-fetch|headless|phantom|mimecast|proofpoint|barracuda|safelinks|forcepoint|symantec|trendmicro|trend micro|sophos|fireeye|ironport|zscaler|facebookexternalhit|whatsapp|slack|discord|telegram|skype|linkedinbot|twitterbot/i', $ua );
	}

	public static function device( $ua ) {
		if ( preg_match( '/iPad|Tablet|Kindle|Silk|Android(?!.*Mobile)/i', $ua ) ) {
			return 'tablet';
		}

		return preg_match( '/Mobi|iPhone|iPod|Android|Windows Phone/i', $ua ) ? 'mobile' : 'desktop';
	}

	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'sb_emailsig_clicks';
	}

	/** Makes the clicks table, once per version. */
	public static function install() {
		if ( get_option( self::DB_OPTION ) === self::DB_VERSION ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  clicked_at datetime NOT NULL,
  person_id bigint(20) unsigned NOT NULL,
  link varchar(40) NOT NULL,
  label varchar(190) NOT NULL DEFAULT '',
  template_id bigint(20) unsigned NOT NULL DEFAULT 0,
  device varchar(10) NOT NULL DEFAULT '',
  bot tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY clicked_at (clicked_at),
  KEY person_time (person_id,clicked_at)
) {$charset};"
		);

		update_option( self::DB_OPTION, self::DB_VERSION, true );
	}

	/** Clears clicks older than Keep Clicks For. */
	public static function prune() {
		global $wpdb;

		$months = self::settings()['keep'];
		$before = gmdate( 'Y-m-d H:i:s', strtotime( '-' . (int) $months . ' months' ) );
		$table  = self::table();

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE clicked_at < %s", $before ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * $url with the UTM codes added, or as it was if it isn't one to tag.
	 * $link names the link ({link}), $person is the person's name ({person},
	 * {first_name}); $person_id and $tpl_id give {template} ('' or 0: their
	 * main). {company} is the company being drawn. See codes().
	 */
	public static function tag( $url, $link, $person = '', $set = null, $person_id = 0, $tpl_id = '' ) {
		$url = trim( (string) $url );
		$set = $set ?: self::settings();

		if ( $url === '' || ! $set['on'] ) {
			return $url;
		}

		$parts  = wp_parse_url( $url );
		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );

		if ( ! $parts || empty( $parts['host'] ) || ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return $url;
		}

		if ( ! $set['all'] && ! self::own( $parts['host'] ) ) {
			return $url;
		}

		if ( isset( $parts['query'] ) && preg_match( '/(^|&)utm_[a-z]+=/i', $parts['query'] ) ) {
			return $url;
		}

		$first = strtok( trim( (string) $person ), ' ' );
		$swap  = array(
			'{person}'     => sanitize_title( $person ),
			'{first_name}' => sanitize_title( false === $first ? '' : $first ),
			'{link}'       => sanitize_title( $link ),
		);

		// Company and template only when a box uses them.
		$used = '';
		foreach ( array_keys( self::DEFAULTS ) as $key ) {
			$used .= (string) $set[ $key ];
		}
		if ( strpos( $used, '{company}' ) !== false ) {
			$o                 = class_exists( 'SB_EmailSig_Company' ) ? SB_EmailSig_Company::source() : 'option';
			$swap['{company}'] = sanitize_title( (string) get_field( 'sb_emailsig_company_name', $o ) );
		}
		if ( strpos( $used, '{template}' ) !== false ) {
			$tpl                = ( (string) $tpl_id === '' || (string) $tpl_id === '0' ) ? ( $person_id ? SB_EmailSig_Templates::for_person( (int) $person_id ) : SB_EmailSig_Templates::current() ) : (string) $tpl_id;
			$found              = SB_EmailSig_Templates::get( $tpl );
			$swap['{template}'] = sanitize_title( $found ? (string) $found['name'] : '' );
		}

		$codes = array();
		foreach ( array_keys( self::DEFAULTS ) as $key ) {
			$value = trim( strtr( (string) $set[ $key ], $swap ), " \t-_" );
			if ( $value !== '' ) {
				$codes[ 'utm_' . $key ] = $value;
			}
		}

		if ( ! $codes ) {
			return $url;
		}

		$hash = '';
		$at   = strpos( $url, '#' );
		if ( false !== $at ) {
			$hash = substr( $url, $at );
			$url  = substr( $url, 0, $at );
		}

		if ( strpos( $url, '?' ) === false ) {
			$join = empty( $parts['path'] ) ? '/?' : '?';
		} else {
			$join = in_array( substr( $url, -1 ), array( '?', '&' ), true ) ? '' : '&';
		}

		return $url . $join . http_build_query( $codes, '', '&', PHP_QUERY_RFC3986 ) . $hash;
	}

	/** True for the Website setting's host, this site's, or a subdomain of either. */
	public static function own( $host ) {
		$host = self::bare( $host );

		foreach ( array( (string) get_field( 'sb_emailsig_website', SB_EmailSig_Company::source() ), home_url() ) as $site ) {
			$mine = self::bare( (string) wp_parse_url( trim( $site ), PHP_URL_HOST ) );

			if ( $mine !== '' && ( $host === $mine || substr( $host, -strlen( '.' . $mine ) ) === '.' . $mine ) ) {
				return true;
			}
		}

		return false;
	}

	private static function bare( $host ) {
		return preg_replace( '/^www\./', '', strtolower( trim( (string) $host, '. ' ) ) );
	}
}
