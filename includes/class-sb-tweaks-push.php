<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Push plugin updates from the Installs page. Hub only.
 *
 * Each click sends one site a signed instruction: update this plugin to this
 * version, from its release file on GitHub. The hub signs with its private key
 * (sb_tweaks_push_secret, encrypted like the GitHub tokens); every plugin's
 * reporter carries the matching public key and checks the signature, the site,
 * the expiry, one-time use, the plugin name and the exact download address
 * before running WordPress's own updater. The version and address come from the
 * hub's own copy of the plugin, so the site never has to ask GitHub's API what
 * the latest release is, which is the part limited to 60 checks an hour.
 */
class SB_Tweaks_Push {

	const OWNER = 'frysteen';

	/** Reporter version that first understood pushed updates. */
	const NEEDS = '1.1.0';

	/** Reporter version that first understood being told to install SocialBUMP Tweaks. */
	const NEEDS_INSTALL = '1.2.0';

	/** Reporter version that first took a beta (0.5.0-beta.2) of SocialBUMP Tweaks. */
	const NEEDS_BETA = '1.3.0';

	public static function boot() {
		add_action( 'wp_ajax_sb_tweaks_push', [ __CLASS__, 'ajax' ] );
		add_action( 'wp_ajax_sb_tweaks_policy', [ __CLASS__, 'policy_ajax' ] );
	}

	/** Where the hub keeps what it last sent each site: host => policy (SB_Tweaks_Policy::clean()). */
	const POLICIES = 'sb_tweaks_policies';

	/** What a site is allowed, as the hub last sent it, else the defaults. */
	public static function policy_for( $host ) {
		$all = (array) get_option( self::POLICIES, [] );

		if ( isset( $all[ $host ] ) && is_array( $all[ $host ] ) ) {
			return SB_Tweaks_Policy::clean( $all[ $host ] ) + [ 'sent' => (int) ( $all[ $host ]['sent'] ?? 0 ) ];
		}

		$modules = [];

		foreach ( array_keys( SB_Tweaks_Modules::instance()->all() ) as $id ) {
			$modules[ $id ] = ! in_array( $id, SB_Tweaks_Policy::OFF_BY_DEFAULT, true );
		}

		return SB_Tweaks_Policy::clean( [ 'modules' => $modules ] ) + [ 'sent' => 0 ];
	}

	/**
	 * Send one site which modules it may use and its limits (Sam, 31 October
	 * 2026). Signed as an update push is; the site keeps it and hides the rest.
	 * Returns [ ok, message ].
	 */
	public static function send_policy( $host, array $policy ) {
		$sites = (array) get_option( SB_Tweaks_Installs::OPTION, [] );

		if ( ! isset( $sites[ $host ] ) ) {
			return [ 'ok' => false, 'message' => __( 'Unknown site.', 'sb-tweaks' ) ];
		}

		$secret = self::secret();

		if ( $secret === '' || ! function_exists( 'sodium_crypto_sign_detached' ) ) {
			return [ 'ok' => false, 'message' => __( 'The hub has no signing key.', 'sb-tweaks' ) ];
		}

		// Every module the hub has, each on or off, so nothing is left to the site's defaults.
		$known  = array_keys( SB_Tweaks_Modules::instance()->all() );
		$policy = SB_Tweaks_Policy::clean( $policy );
		$policy['modules'] = array_intersect_key( $policy['modules'], array_flip( $known ) ) + array_fill_keys( $known, false );

		$job = [
			'action'  => 'policy',
			'host'    => $host,
			'policy'  => $policy,
			'expires' => time() + 300,
			'nonce'   => bin2hex( random_bytes( 16 ) ),
		];
		$raw = wp_json_encode( $job );
		$sig = sodium_crypto_sign_detached( $raw, $secret );

		$response = wp_remote_post(
			add_query_arg( 'rest_route', '/socialbump/v1/policy', trailingslashit( $sites[ $host ]['url'] ) ),
			[
				'timeout' => 30,
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode( [ 'payload' => base64_encode( $raw ), 'sig' => base64_encode( $sig ) ] ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return [ 'ok' => false, 'message' => $response->get_error_message() ];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code === 404 && is_array( $body ) && ( $body['code'] ?? '' ) === 'rest_no_route' ) {
			return [ 'ok' => false, 'message' => __( 'This site needs a newer SocialBUMP Tweaks first (the next beta or release).', 'sb-tweaks' ) ];
		}

		if ( ! is_array( $body ) || ! array_key_exists( 'ok', $body ) ) {
			/* translators: %d: HTTP status code */
			return [ 'ok' => false, 'message' => sprintf( __( 'The site answered with an error (%d). A security plugin may be blocking outside REST API requests.', 'sb-tweaks' ), $code ) ];
		}

		if ( ! empty( $body['ok'] ) ) {
			$all          = (array) get_option( self::POLICIES, [] );
			$all[ $host ] = $policy + [ 'sent' => time() ];
			update_option( self::POLICIES, $all, false );
		}

		return [
			'ok'      => ! empty( $body['ok'] ),
			'message' => sanitize_text_field( (string) ( $body['message'] ?? '' ) ),
		];
	}

	/** The Installs page's Modules popup: the ticked modules and the limits, for one site. */
	public static function policy_ajax() {
		check_ajax_referer( 'sb_tweaks_push' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json( [ 'ok' => false, 'message' => __( 'You do not have permission to do that.', 'sb-tweaks' ) ], 403 );
		}

		$host    = isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : '';
		$allowed = isset( $_POST['modules'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['modules'] ) ) : [];
		$limits  = isset( $_POST['limits'] ) && is_array( $_POST['limits'] ) ? map_deep( wp_unslash( $_POST['limits'] ), 'sanitize_text_field' ) : [];

		$result = self::send_policy(
			$host,
			[
				'modules' => array_fill_keys( $allowed, true ),
				'limits'  => $limits,
			]
		);

		if ( $result['ok'] ) {
			$result['policy'] = self::policy_for( $host );
		}

		wp_send_json( $result );
	}

	private static function secret() {
		$stored = (string) get_option( 'sb_tweaks_push_secret', '' );

		if ( strpos( $stored, 'enc:' ) !== 0 ) {
			return '';
		}

		$data  = (string) base64_decode( substr( $stored, 4 ) );
		$key   = hash( 'sha256', wp_salt( 'auth' ) . 'sb-tweaks-push', true );
		$plain = openssl_decrypt( substr( $data, 16 ), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, substr( $data, 0, 16 ) );

		return $plain === false ? '' : $plain;
	}

	/** Whether a site's reporter can receive pushed updates. */
	public static function can_push( array $site ) {
		return ! empty( $site['reporter'] ) && version_compare( $site['reporter'], self::NEEDS, '>=' );
	}

	/** Whether a site's reporter can take a beta. */
	public static function can_beta( array $site ) {
		return ! empty( $site['reporter'] ) && version_compare( $site['reporter'], self::NEEDS_BETA, '>=' );
	}

	/** Whether a site's reporter can be told to install SocialBUMP Tweaks. */
	public static function can_install( array $site ) {
		return ! empty( $site['reporter'] ) && version_compare( $site['reporter'], self::NEEDS_INSTALL, '>=' );
	}

	/**
	 * Push one plugin to one site. Returns [ ok, message, version ].
	 *
	 * $action is update (a plugin the site has), install (SocialBUMP Tweaks,
	 * on a site that does not have it yet, which then takes over from the
	 * standalone plugins it has modules for) or beta (SocialBUMP Tweaks' latest
	 * beta, a pre-release on GitHub, sent to the site as an update).
	 */
	public static function push( $host, $slug, $action = 'update' ) {
		$sites  = (array) get_option( SB_Tweaks_Installs::OPTION, [] );
		$latest = SB_Tweaks_Installs::latest();

		if ( ! isset( $sites[ $host ] ) || ! isset( SB_Tweaks_Installs::LABELS[ $slug ] ) || empty( $latest[ $slug ] ) ) {
			return [ 'ok' => false, 'message' => __( 'Unknown site or plugin.', 'sb-tweaks' ) ];
		}

		$secret = self::secret();

		if ( $secret === '' || ! function_exists( 'sodium_crypto_sign_detached' ) ) {
			return [ 'ok' => false, 'message' => __( 'The hub has no signing key.', 'sb-tweaks' ) ];
		}

		$version = $latest[ $slug ];

		if ( $action === 'beta' ) {
			$beta    = class_exists( 'SB_Tweaks_Release' ) ? SB_Tweaks_Release::beta() : [];
			$version = (string) ( $beta['version'] ?? '' );

			if ( $slug !== 'socialbump-tweaks' || $version === '' ) {
				return [ 'ok' => false, 'message' => __( 'There is no beta to send. Release one from the Publishing page first.', 'sb-tweaks' ) ];
			}

			if ( ! self::can_beta( $sites[ $host ] ) ) {
				return [ 'ok' => false, 'message' => __( 'This site cannot take a beta until it has a release with the newer reporter.', 'sb-tweaks' ) ];
			}
		}

		$job     = [
			'action'  => $action === 'install' ? 'install' : 'update',
			'host'    => $host,
			'plugin'  => $slug,
			'version' => $version,
			'url'     => 'https://github.com/' . self::OWNER . '/' . $slug . '/releases/download/v' . $version . '/' . $slug . '.zip',
			'expires' => time() + 300,
			'nonce'   => bin2hex( random_bytes( 16 ) ),
		];
		$raw     = wp_json_encode( $job );
		$sig     = sodium_crypto_sign_detached( $raw, $secret );

		// rest_route works whatever the site's permalink setting.
		$endpoint = add_query_arg( 'rest_route', '/socialbump/v1/update', trailingslashit( $sites[ $host ]['url'] ) );
		$response = wp_remote_post(
			$endpoint,
			[
				'timeout' => 120,
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode( [ 'payload' => base64_encode( $raw ), 'sig' => base64_encode( $sig ) ] ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return [ 'ok' => false, 'message' => $response->get_error_message() ];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code === 404 && is_array( $body ) && ( $body['code'] ?? '' ) === 'rest_no_route' ) {
			return [ 'ok' => false, 'message' => __( 'This site does not have the push receiver yet. Update it once from its own Updates screen.', 'sb-tweaks' ) ];
		}

		if ( ! is_array( $body ) || ! array_key_exists( 'ok', $body ) ) {
			/* translators: %d: HTTP status code */
			return [ 'ok' => false, 'message' => sprintf( __( 'The site answered with an error (%d). A security plugin may be blocking outside REST API requests.', 'sb-tweaks' ), $code ) ];
		}

		if ( ! empty( $body['ok'] ) && ! empty( $body['version'] ) ) {
			// Show it straight away; the site's own report follows.
			$sites[ $host ]['plugins'][ $slug ]['version'] = sanitize_text_field( $body['version'] );

			if ( $action === 'install' ) {
				$sites[ $host ]['plugins'][ $slug ]['active'] = true;

				foreach ( array_map( 'sanitize_key', (array) ( $body['retired'] ?? [] ) ) as $gone ) {
					if ( isset( $sites[ $host ]['plugins'][ $gone ] ) ) {
						$sites[ $host ]['plugins'][ $gone ]['active'] = false;
					}
				}
			}
			update_option( SB_Tweaks_Installs::OPTION, $sites, false );

			// Reporter 1.2.1 and later send their full report back with the
			// answer, the reliable way to learn the new state after a push.
			if ( ! empty( $body['report'] ) && is_array( $body['report'] ) && strtolower( (string) wp_parse_url( (string) ( $body['report']['url'] ?? '' ), PHP_URL_HOST ) ) === $host ) {
				SB_Tweaks_Installs::record( $body['report'] );
			}
		}

		return [
			'ok'      => ! empty( $body['ok'] ),
			'message' => sanitize_text_field( (string) ( $body['message'] ?? '' ) ),
			'version' => sanitize_text_field( (string) ( $body['version'] ?? '' ) ),
		];
	}

	public static function ajax() {
		check_ajax_referer( 'sb_tweaks_push' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json( [ 'ok' => false, 'message' => __( 'You do not have permission to do that.', 'sb-tweaks' ) ], 403 );
		}

		@set_time_limit( 180 );

		$host = isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : '';
		$slug = isset( $_POST['plugin'] ) ? sanitize_key( wp_unslash( $_POST['plugin'] ) ) : '';

		$job  = isset( $_POST['job'] ) ? sanitize_key( wp_unslash( $_POST['job'] ) ) : 'update';
		$job  = in_array( $job, [ 'install', 'beta' ], true ) ? $job : 'update';

		wp_send_json( self::push( $host, $slug, $job ) );
	}
}
