<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which modules a site may use, set from the hub (Sam, 31 October 2026).
 *
 * The hub's Installs page sends each site a signed list: the modules it is
 * allowed, and limits for the modules that have them (Email Signatures:
 * companies, people and templates). A module the site is not allowed does not
 * show there at all: no Modules page card, no menu, nothing running. One it
 * is allowed, the site can still switch on and off itself.
 *
 * Until the hub sends one, every module is allowed but those in
 * OFF_BY_DEFAULT, with the limits in LIMITS. The hub itself is never limited.
 *
 * The instruction is checked as a pushed update is (SocialBUMP_Reporter):
 * signed with the hub's key, for this site, not expired, used once.
 */
class SB_Tweaks_Policy {

	const OPTION = 'sb_tweaks_policy';

	/** Modules a site doesn't get until the hub allows them. */
	const OFF_BY_DEFAULT = [ 'email_signatures' ];

	/** Each module's limits until the hub sets them: key => number, 0 for no limit. */
	const LIMITS = [
		'email_signatures' => [
			'companies' => 1,
			'people'    => 5,
			'templates' => 2,
		],
	];

	/** Names for the limits, for the hub's popup. */
	const LIMIT_LABELS = [
		'companies' => 'Companies',
		'people'    => 'People',
		'templates' => 'Templates',
	];

	public static function boot() {
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
	}

	/** What the hub last sent: [ modules => id => bool, limits => id => key => int, set => time ]. */
	public static function stored() {
		$policy = get_option( self::OPTION );

		return is_array( $policy ) ? $policy : [];
	}

	private static function on_hub() {
		return function_exists( 'sb_tweaks_is_hub' ) && sb_tweaks_is_hub();
	}

	/** Whether this site may use a module. */
	public static function allows( $id ) {
		if ( self::on_hub() ) {
			return true;
		}

		$policy = self::stored();

		if ( isset( $policy['modules'] ) && is_array( $policy['modules'] ) && array_key_exists( $id, $policy['modules'] ) ) {
			return (bool) $policy['modules'][ $id ];
		}

		return ! in_array( $id, self::OFF_BY_DEFAULT, true );
	}

	/** A module's limits on this site: key => number, 0 for no limit. Empty for a module without any. */
	public static function limits( $id ) {
		$limits = self::LIMITS[ $id ] ?? [];

		if ( self::on_hub() ) {
			return array_fill_keys( array_keys( $limits ), 0 );
		}

		$set = self::stored()['limits'][ $id ] ?? [];

		foreach ( $limits as $key => $value ) {
			if ( is_array( $set ) && isset( $set[ $key ] ) ) {
				$limits[ $key ] = max( 0, (int) $set[ $key ] );
			}
		}

		return $limits;
	}

	/** One limit: a number, or 0 for none. */
	public static function limit( $id, $key ) {
		return (int) ( self::limits( $id )[ $key ] ?? 0 );
	}

	/**
	 * A policy as the hub sends it and the site keeps it: module ids to on or
	 * off, and whole-number limits for the modules in LIMITS.
	 */
	public static function clean( $policy ) {
		$policy = is_array( $policy ) ? $policy : [];
		$out    = [
			'modules' => [],
			'limits'  => [],
		];

		foreach ( (array) ( $policy['modules'] ?? [] ) as $id => $on ) {
			$out['modules'][ sanitize_key( $id ) ] = (bool) $on;
		}

		foreach ( self::LIMITS as $id => $limits ) {
			foreach ( $limits as $key => $default ) {
				$value = $policy['limits'][ $id ][ $key ] ?? $default;

				$out['limits'][ $id ][ $key ] = max( 0, min( 100000, (int) $value ) );
			}
		}

		return $out;
	}

	public static function routes() {
		register_rest_route(
			'socialbump/v1',
			'/policy',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'receive' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	private static function refuse( $message, $code = 400 ) {
		return new WP_REST_Response( [ 'ok' => false, 'message' => $message ], $code );
	}

	/** The hub's signed policy: checked, kept, and the modules' own rules applied. */
	public static function receive( $request ) {
		$body = json_decode( (string) $request->get_body(), true );
		$raw  = is_array( $body ) ? base64_decode( (string) ( $body['payload'] ?? '' ), true ) : false;
		$sig  = is_array( $body ) ? base64_decode( (string) ( $body['sig'] ?? '' ), true ) : false;

		if ( $raw === false || $sig === false || strlen( $sig ) !== 64 || ! function_exists( 'sodium_crypto_sign_verify_detached' ) || ! class_exists( 'SocialBUMP_Reporter' ) || ! defined( 'SocialBUMP_Reporter::PUSH_KEY' ) ) {
			return self::refuse( 'Not a valid instruction.' );
		}

		try {
			$signed = sodium_crypto_sign_verify_detached( $sig, $raw, base64_decode( SocialBUMP_Reporter::PUSH_KEY ) );
		} catch ( \Throwable $e ) {
			$signed = false;
		}

		if ( ! $signed ) {
			return self::refuse( 'The signature did not match the SocialBUMP hub.', 403 );
		}

		$job  = json_decode( $raw, true );
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		if ( ! is_array( $job ) || ( $job['host'] ?? '' ) !== $host || ( $job['action'] ?? '' ) !== 'policy' ) {
			return self::refuse( 'This instruction was meant for another site.', 403 );
		}

		$expires = (int) ( $job['expires'] ?? 0 );

		if ( $expires < time() || $expires > time() + 600 ) {
			return self::refuse( 'This instruction has expired.', 403 );
		}

		$nonce = (string) ( $job['nonce'] ?? '' );

		if ( ! preg_match( '/^[a-f0-9]{32}$/', $nonce ) || get_transient( 'socialbump_push_' . $nonce ) ) {
			return self::refuse( 'This instruction has already been used.', 403 );
		}

		set_transient( 'socialbump_push_' . $nonce, 1, 15 * MINUTE_IN_SECONDS );

		$policy        = self::clean( $job['policy'] ?? [] );
		$policy['set'] = time();

		update_option( self::OPTION, $policy, false );

		/**
		 * A module with limits applies them now (Email Signatures switches
		 * off what is over them), whether or not it is running this request.
		 */
		do_action( 'sb_tweaks/policy', $policy );

		return new WP_REST_Response(
			[
				'ok'      => true,
				'message' => 'Saved.',
			],
			200
		);
	}
}
