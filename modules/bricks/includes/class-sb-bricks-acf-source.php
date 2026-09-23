<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which copy of ACF is actually running.
 *
 * Advanced Themer ships its own copy of ACF Pro and loads it whenever the
 * standalone plugin is switched off. Everything keeps working, but it is
 * usually an older version than the one installed on the site, so it is worth
 * pointing out rather than leaving it to be found later.
 */
class SB_Bricks_Acf_Source {

	/** True when ACF comes from Advanced Themer rather than a plugin of its own. */
	public static function is_bundled() {
		return defined( 'ACF_PATH' ) && strpos( ACF_PATH, 'bricks-advanced-themer' ) !== false;
	}

	/** The standalone copy in the plugins folder, whether active or not. */
	public static function installed_plugin() {
		foreach ( [ 'advanced-custom-fields-pro/acf.php', 'advanced-custom-fields/acf.php' ] as $file ) {
			if ( ! file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
				continue;
			}

			$data = get_file_data( WP_PLUGIN_DIR . '/' . $file, [ 'Version' => 'Version', 'Name' => 'Plugin Name' ] );

			return trim( $data['Name'] . ' ' . $data['Version'] );
		}

		return '';
	}
	/** Shown on the settings page when the bundled copy is the one running. */
	public static function notice() {
		if ( ! self::is_bundled() ) {
			return;
		}

		$running = defined( 'ACF_VERSION' ) ? ACF_VERSION : '';
		$own     = self::installed_plugin();

		$text = sprintf(
			esc_html__( 'ACF is running from the copy bundled with Advanced Themer, version %s, rather than from a plugin of its own.', 'sb-tweaks' ),
			esc_html( $running )
		);

		if ( $own !== '' ) {
			$text .= ' ' . sprintf(
				esc_html__( '%s is installed but switched off. Activate it under Plugins to use that one instead.', 'sb-tweaks' ),
				esc_html( $own )
			);
		}

		echo '<div class="notice notice-warning"><p>' . $text . '</p></div>';
	}
}