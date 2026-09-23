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
		$count   = count( self::options() );

		if ( $notice ) {
			delete_transient( self::NOTICE . get_current_user_id() );
		}

		if ( is_array( $notice ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', $notice['type'] === 'success' ? 'success' : 'error', esc_html( $notice['message'] ) );
		}
		?>
		<div class="sb-tweaks-updates">
			<section class="sb-tweaks-section">
				<div class="sb-tweaks-section__head">
					<h2><?php esc_html_e( 'Updates', 'sb-tweaks' ); ?></h2>
					<p><?php esc_html_e( 'New versions come from the SocialBUMP hub through GitHub releases. WordPress checks twice a day on its own, or check now.', 'sb-tweaks' ); ?></p>
				</div>

				<p class="sb-tweaks-updates__status">
					<?php if ( $pending ) : ?>
						<span class="sb-tweaks-updates__badge is-available"><?php echo esc_html( 'v' . $pending . ' ' . __( 'available', 'sb-tweaks' ) ); ?></span>
					<?php else : ?>
						<span class="sb-tweaks-updates__badge is-current"><?php esc_html_e( 'Up to date', 'sb-tweaks' ); ?></span>
					<?php endif; ?>
					<span class="sb-tweaks-updates__meta">
						<?php
						/* translators: %s: version number */
						printf( esc_html__( 'Running v%s.', 'sb-tweaks' ), esc_html( SB_TWEAKS_VERSION ) );

						if ( $checked ) {
							echo ' ';
							/* translators: %s: time since the last check, e.g. 3 hours */
							printf( esc_html__( 'Checked %s ago.', 'sb-tweaks' ), esc_html( human_time_diff( $checked ) ) );
						}
						?>
					</span>
				</p>

				<div class="sb-tweaks-updates__actions">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="sb_tweaks_check_updates">
						<?php wp_nonce_field( 'sb_tweaks_check_updates' ); ?>
						<button type="submit" class="button"><?php esc_html_e( 'Check for updates', 'sb-tweaks' ); ?></button>
					</form>

					<?php if ( $pending && current_user_can( 'update_plugins' ) ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'update-core.php?action=do-plugin-upgrade&plugins=' . rawurlencode( $file ) ), 'upgrade-core' ) ); ?>"><?php esc_html_e( 'Update now', 'sb-tweaks' ); ?></a>
					<?php endif; ?>

					<a class="sb-tweaks-updates__link" href="<?php echo esc_url( 'https://github.com/' . SB_TWEAKS_GITHUB_REPO . '/releases' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'All releases', 'sb-tweaks' ); ?></a>
				</div>
			</section>

			<section class="sb-tweaks-section">
				<div class="sb-tweaks-section__head">
					<h2><?php esc_html_e( 'Move settings to another site', 'sb-tweaks' ); ?></h2>
					<p><?php esc_html_e( 'Export saves the Modules switches and every module\'s settings to a file; import on another site sets it up the same way. Nothing else on either site is touched.', 'sb-tweaks' ); ?></p>
				</div>

				<div class="sb-tweaks-updates__actions">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="sb_tweaks_export_settings">
						<?php wp_nonce_field( 'sb_tweaks_export_settings' ); ?>
						<button type="submit" class="button" data-sb-always-on><?php esc_html_e( 'Export settings', 'sb-tweaks' ); ?></button>
					</form>
					<span class="sb-tweaks-updates__meta">
						<?php
						/* translators: %d: number of saved settings */
						printf( esc_html( _n( '%d saved setting on this site.', '%d saved settings on this site.', $count, 'sb-tweaks' ) ), (int) $count );
						?>
					</span>
				</div>

				<form class="sb-tweaks-updates__import" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm(<?php echo esc_attr( wp_json_encode( __( 'Import these settings? They replace the matching settings on this site.', 'sb-tweaks' ) ) ); ?>);">
					<input type="hidden" name="action" value="sb_tweaks_import_settings">
					<?php wp_nonce_field( 'sb_tweaks_import_settings' ); ?>
					<input type="file" name="sb_tweaks_settings_file" accept=".json,application/json" required>
					<button type="submit" class="button" data-sb-always-on><?php esc_html_e( 'Import settings', 'sb-tweaks' ); ?></button>
				</form>
			</section>
		</div>
		<?php
	}
}
