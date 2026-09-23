<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Updates page: the version running, a check for a newer one, and moving a
 * site's whole setup to another site.
 *
 * Every site gets it, not just the hub. The check asks GitHub straight away
 * rather than waiting for WordPress's twice daily look. Update now uses the
 * bulk path the Dashboard's Updates screen uses, which swaps the files under
 * maintenance mode and never deactivates the plugin; the single plugin path
 * deactivates first and reactivates silently, and when that silent step fails
 * the plugin is simply left off. Site Kit found that out the hard way.
 *
 * Export and import cover the Modules switches and every module's own options,
 * found by the framework's naming (sb_tweaks_<module>_...), so a module added
 * later travels without any extra work. Hub-only data never travels: GitHub
 * tokens, queued release notes, the latest release record, the Installs list,
 * the push signing keys, and the backups taken during migrations.
 */
class SB_Tweaks_Updates {

	const NOTICE = 'sb_tweaks_updates_notice_';

	/** Largest import accepted, in bytes. */
	const MAX_IMPORT = 2097152;

	public static function boot() {
		add_action( 'admin_post_sb_tweaks_check_updates', [ __CLASS__, 'check' ] );
		add_action( 'admin_post_sb_tweaks_export_settings', [ __CLASS__, 'export' ] );
		add_action( 'admin_post_sb_tweaks_import_settings', [ __CLASS__, 'import' ] );
	}

	private static function page_url() {
		return admin_url( 'admin.php?page=' . SB_Tweaks_Settings::PAGE_SLUG . '-updates' );
	}

	private static function back( $type, $message ) {
		set_transient( self::NOTICE . get_current_user_id(), [ 'type' => $type, 'message' => $message ], 5 * MINUTE_IN_SECONDS );

		wp_safe_redirect( self::page_url() );
		exit;
	}

	private static function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'sb-tweaks' ) );
		}

		check_admin_referer( $action );
	}

	/** Whether an option belongs in an export: ours, and not hub-only or a backup. */
	public static function travels( $name ) {
		$name = (string) $name;

		if ( strpos( $name, 'sb_tweaks_' ) !== 0 ) {
			return false;
		}

		return ! preg_match( '/github_token|pending_changes|latest_release|^sb_tweaks_installs$|^sb_tweaks_push_|_backup$/', $name );
	}

	/** Every option that makes up this site's SocialBUMP Tweaks setup. */
	public static function options() {
		global $wpdb;

		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name", $wpdb->esc_like( 'sb_tweaks_' ) . '%' ) );

		return array_values( array_filter( (array) $names, [ __CLASS__, 'travels' ] ) );
	}

	public static function check() {
		self::guard( 'sb_tweaks_check_updates' );

		$checker = $GLOBALS['sb_tweaks_update_checker'] ?? null;

		if ( ! $checker ) {
			self::back( 'error', __( 'The update checker is not running on this site.', 'sb-tweaks' ) );
		}

		delete_transient( 'sb_tweaks_latest_release' );

		try {
			$update = $checker->checkForUpdates();
		} catch ( \Throwable $e ) {
			self::back( 'error', __( 'Could not reach GitHub. Try again shortly.', 'sb-tweaks' ) );
		}

		// Make WordPress's own list agree, so Update now appears straight away.
		wp_update_plugins();

		if ( $update && ! empty( $update->version ) && version_compare( $update->version, SB_TWEAKS_VERSION, '>' ) ) {
			/* translators: %s: version number */
			self::back( 'success', sprintf( __( 'Version %s is available.', 'sb-tweaks' ), $update->version ) );
		}

		self::back( 'success', __( 'You are running the latest version.', 'sb-tweaks' ) );
	}

	public static function export() {
		self::guard( 'sb_tweaks_export_settings' );

		$payload = [
			'plugin'  => 'socialbump-tweaks',
			'version' => SB_TWEAKS_VERSION,
			'site'    => home_url(),
			'date'    => gmdate( 'c' ),
			'options' => [],
		];

		foreach ( self::options() as $name ) {
			$payload['options'][ $name ] = get_option( $name );
		}

		$host = str_replace( '.', '-', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$name = 'socialbump-tweaks-settings-' . $host . '-' . gmdate( 'Y-m-d' ) . '.json';
		$json = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . strlen( $json ) );

		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public static function import() {
		self::guard( 'sb_tweaks_import_settings' );

		$file = $_FILES['sb_tweaks_settings_file'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( ! is_array( $file ) || (int) ( $file['error'] ?? 1 ) !== UPLOAD_ERR_OK || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			self::back( 'error', __( 'Choose a settings file to import.', 'sb-tweaks' ) );
		}

		if ( (int) $file['size'] > self::MAX_IMPORT ) {
			self::back( 'error', __( 'That file is too large to be a settings export.', 'sb-tweaks' ) );
		}

		$data = json_decode( (string) file_get_contents( $file['tmp_name'] ), true );

		if ( ! is_array( $data ) || ( $data['plugin'] ?? '' ) !== 'socialbump-tweaks' || ! is_array( $data['options'] ?? null ) ) {
			self::back( 'error', __( 'That is not a SocialBUMP Tweaks settings export.', 'sb-tweaks' ) );
		}

		$count = 0;

		foreach ( $data['options'] as $name => $value ) {
			if ( ! self::travels( $name ) || ! preg_match( '/^[a-z0-9_]+$/', (string) $name ) ) {
				continue;
			}

			update_option( $name, $value );
			$count++;
		}

		/* translators: 1: number of settings, 2: site the export came from */
		self::back( 'success', sprintf( __( 'Imported %1$d settings from %2$s.', 'sb-tweaks' ), $count, esc_url_raw( (string) ( $data['site'] ?? '' ) ) ) );
	}

	public static function render() {
		$file    = plugin_basename( SB_TWEAKS_FILE );
		$state   = get_site_transient( 'update_plugins' );
		$pending = ( $state && ! empty( $state->response[ $file ]->new_version ) ) ? $state->response[ $file ]->new_version : '';
		$checked = ( $state && ! empty( $state->last_checked ) ) ? (int) $state->last_checked : 0;
		$notice  = get_transient( self::NOTICE . get_current_user_id() );
		$post    = esc_url( admin_url( 'admin-post.php' ) );

		if ( $notice ) {
			delete_transient( self::NOTICE . get_current_user_id() );
		}

		// Laid out exactly like Site Kit's and Bricks Tweaks' Updates pages.
		echo '<section class="sb-tweaks-section" id="sb-tweaks-section-updates">';
		echo '<div class="sb-tweaks-section__head"><h2>' . esc_html__( 'Updates', 'sb-tweaks' ) . '</h2>';
		echo '<p>' . esc_html__( 'Delivered from the hub site through GitHub releases. WordPress checks twice a day on its own.', 'sb-tweaks' ) . '</p></div>';

		if ( is_array( $notice ) ) {
			echo '<div class="notice notice-' . ( $notice['type'] === 'success' ? 'success' : 'error' ) . ' inline"><p>' . esc_html( $notice['message'] ) . '</p></div>';
		}

		echo '<div class="sb-tweaks-updates">';
		echo '<p class="sb-tweaks-updates__status">';

		if ( $pending ) {
			echo '<span class="sb-tweaks-updates__badge is-available">' . esc_html( 'v' . $pending . ' ' . __( 'available', 'sb-tweaks' ) ) . '</span>';
		} else {
			echo '<span class="sb-tweaks-updates__badge is-current">' . esc_html__( 'Up to date', 'sb-tweaks' ) . '</span>';
		}

		echo '<span class="sb-tweaks-updates__meta">';
		/* translators: %s: version number */
		printf( esc_html__( 'Running v%s.', 'sb-tweaks' ), esc_html( SB_TWEAKS_VERSION ) );

		if ( $checked ) {
			echo ' ';
			/* translators: %s: time since the last check, e.g. 3 hours */
			printf( esc_html__( 'Checked %s ago.', 'sb-tweaks' ), esc_html( human_time_diff( $checked ) ) );
		}

		echo '</span></p>';

		echo '<div class="sb-tweaks-updates__actions">';
		echo '<form method="post" action="' . $post . '"><input type="hidden" name="action" value="sb_tweaks_check_updates">';
		wp_nonce_field( 'sb_tweaks_check_updates' );
		echo '<button type="submit" class="button">' . esc_html__( 'Check for updates', 'sb-tweaks' ) . '</button></form>';

		if ( $pending && current_user_can( 'update_plugins' ) ) {
			echo '<a class="button button-primary" href="' . esc_url( wp_nonce_url( self_admin_url( 'update-core.php?action=do-plugin-upgrade&plugins=' . rawurlencode( $file ) ), 'upgrade-core' ) ) . '">' . esc_html__( 'Update now', 'sb-tweaks' ) . '</a>';
		}

		echo '<a class="sb-tweaks-updates__link" href="' . esc_url( 'https://github.com/' . SB_TWEAKS_GITHUB_REPO . '/releases' ) . '" target="_blank" rel="noopener">' . esc_html__( 'All releases', 'sb-tweaks' ) . '</a>';
		echo '</div></div></section>';

		echo '<section class="sb-tweaks-section">';
		echo '<div class="sb-tweaks-section__head"><h2>' . esc_html__( 'Settings', 'sb-tweaks' ) . '</h2>';
		echo '<p>' . esc_html__( 'Take this site setup to another site. Only SocialBUMP Tweaks settings are included.', 'sb-tweaks' ) . '</p></div>';

		echo '<div class="sb-tweaks-grid">';

		echo '<div class="sb-tweaks-card"><div class="sb-tweaks-card__head"><h3>' . esc_html__( 'Export', 'sb-tweaks' ) . '</h3></div>';
		echo '<p class="sb-tweaks-card__desc">' . esc_html__( 'Download the Modules switches and every module setting as a JSON file.', 'sb-tweaks' ) . '</p>';
		echo '<form method="post" action="' . $post . '"><input type="hidden" name="action" value="sb_tweaks_export_settings">';
		wp_nonce_field( 'sb_tweaks_export_settings' );
		echo '<p><button type="submit" class="button" data-sb-always-on>' . esc_html__( 'Download settings', 'sb-tweaks' ) . '</button></p></form></div>';

		echo '<div class="sb-tweaks-card"><div class="sb-tweaks-card__head"><h3>' . esc_html__( 'Import', 'sb-tweaks' ) . '</h3></div>';
		echo '<p class="sb-tweaks-card__desc">' . esc_html__( 'Replaces the settings on this site with the ones in the file. There is no undo.', 'sb-tweaks' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . $post . '"><input type="hidden" name="action" value="sb_tweaks_import_settings">';
		wp_nonce_field( 'sb_tweaks_import_settings' );
		echo '<p><input type="file" name="sb_tweaks_settings_file" accept="application/json,.json" required></p>';
		echo '<p><button type="submit" class="button" data-sb-always-on onclick="return confirm(' . esc_attr( wp_json_encode( __( 'Replace the SocialBUMP Tweaks settings on this site?', 'sb-tweaks' ) ) ) . ');">' . esc_html__( 'Import settings', 'sb-tweaks' ) . '</button></p></form></div>';

		echo '</div></section>';
	}
}
