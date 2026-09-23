<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Publishing from the Installs page. Hub only.
 *
 * Runs each plugin's own publish handler, the one its Publishing page posts to,
 * so there is still exactly one publish routine per plugin. The version is the
 * next patch number and the notes are the changes queued for it, the same
 * defaults the Publishing page fills in. The handler finishes by redirecting to
 * its Publishing page; here that redirect is caught, the plugin's own result
 * notice is read back, and the answer goes to the Installs page instead.
 */
class SB_Tweaks_Hub_Publish {

	/** Plugin folder => [ release class, option and field prefix ]. */
	const PLUGINS = [
		'socialbump-site-kit'              => [ 'SBSK_Release', 'sbsk' ],
		'socialbump-bricks-tweaks'         => [ 'SBBT_Release', 'sbbt' ],
		'socialbump-ai-knowledge-exporter' => [ 'SBAIKE_Release', 'sbaike' ],
		'socialbump-tweaks'                => [ 'SB_Tweaks_Release', 'sb_tweaks' ],
	];

	public static function boot() {
		add_action( 'wp_ajax_sb_tweaks_hub_publish', [ __CLASS__, 'ajax' ] );
	}

	/** The changes queued for a plugin's next release. */
	public static function pending( $slug ) {
		$class = self::PLUGINS[ $slug ][0] ?? '';

		return class_exists( $class ) ? array_values( (array) $class::pending_changes() ) : [];
	}

	/** The next patch version after the hub's own copy. */
	public static function next_version( $slug ) {
		$file = WP_PLUGIN_DIR . '/' . $slug . '/' . $slug . '.php';
		$now  = file_exists( $file ) ? (string) get_file_data( $file, [ 'v' => 'Version' ] )['v'] : '';

		if ( ! preg_match( '/^(\d+)\.(\d+)\.(\d+)$/', $now, $m ) ) {
			return '';
		}

		return $m[1] . '.' . $m[2] . '.' . ( (int) $m[3] + 1 );
	}

	public static function ajax() {
		check_ajax_referer( 'sb_tweaks_hub_publish' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json( [ 'ok' => false, 'message' => __( 'You do not have permission to do that.', 'sb-tweaks' ) ], 403 );
		}

		$slug = isset( $_POST['plugin'] ) ? sanitize_key( wp_unslash( $_POST['plugin'] ) ) : '';

		wp_send_json( self::publish( $slug ) );
	}

	/** Publish one plugin. Returns [ ok, message, version ]. */
	public static function publish( $slug ) {
		if ( ! isset( self::PLUGINS[ $slug ] ) ) {
			return [ 'ok' => false, 'message' => __( 'Unknown plugin.', 'sb-tweaks' ) ];
		}

		list( $class, $prefix ) = self::PLUGINS[ $slug ];

		if ( ! class_exists( $class ) || ! method_exists( $class, 'instance' ) ) {
			return [ 'ok' => false, 'message' => __( 'That plugin\'s publishing is not running on the hub.', 'sb-tweaks' ) ];
		}

		$version = self::next_version( $slug );

		if ( $version === '' ) {
			return [ 'ok' => false, 'message' => __( 'Could not work out the next version number.', 'sb-tweaks' ) ];
		}

		@set_time_limit( 300 );

		// Exactly what the Publishing page form sends.
		$nonce                         = wp_create_nonce( $prefix . '_publish' );
		$_POST[ $prefix . '_version' ] = $version;
		$_POST[ $prefix . '_notes' ]   = (string) $class::changes_text();
		$_POST['_wpnonce']             = $nonce;
		$_REQUEST['_wpnonce']          = $nonce;
		$_REQUEST['_wp_http_referer']  = wp_get_referer();

		// The handler ends by redirecting to its Publishing page. Stop there
		// instead, and take the outcome from the notice it leaves behind.
		$stop = function ( $location ) {
			throw new SB_Tweaks_Hub_Publish_Done( (string) $location );
		};

		add_filter( 'wp_redirect', $stop, PHP_INT_MAX );

		try {
			$class::instance()->publish();
		} catch ( SB_Tweaks_Hub_Publish_Done $done ) {
			// Expected: the handler finished and tried to redirect.
		} catch ( \Throwable $e ) {
			remove_filter( 'wp_redirect', $stop, PHP_INT_MAX );

			return [ 'ok' => false, 'message' => $e->getMessage() ];
		}

		remove_filter( 'wp_redirect', $stop, PHP_INT_MAX );

		$key    = constant( $class . '::NOTICE_PREFIX' ) . get_current_user_id();
		$notice = get_transient( $key );

		delete_transient( $key );

		$ok = is_array( $notice ) && ( $notice['type'] ?? '' ) === 'success';

		return [
			'ok'      => $ok,
			'message' => is_array( $notice ) ? trim( wp_strip_all_tags( (string) $notice['message'] ) ) : __( 'The publish finished without saying how it went. Check the plugin\'s Publishing page.', 'sb-tweaks' ),
			'version' => $ok ? $version : '',
		];
	}
}

/** Thrown to stop a publish handler at its closing redirect. */
class SB_Tweaks_Hub_Publish_Done extends \Exception {}
