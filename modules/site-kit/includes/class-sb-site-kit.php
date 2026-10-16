<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the Site Kit features ask of their module: a card setting, whether a
 * feature is on, and a page address. SBSK_Modules and SBSK_Settings answered
 * these in the standalone plugin; here the framework's features loader does,
 * through sb_tweaks_module( 'site_kit' ).
 *
 * The option names are the ones every site already holds, so nothing moves.
 */
class SB_Site_Kit {

	const FEATURES_OPTION = 'sb_tweaks_site_kit_features';

	const SETTINGS_OPTION = 'sb_tweaks_site_kit_settings';

	const GROUPS_OPTION = 'sb_tweaks_site_kit_groups';

	/** The module's menu slug, which every page address starts with. */
	const SLUG = 'sb-site-kit';

	/** The booted features loader, or null while the module is off. */
	public static function features() {
		return function_exists( 'sb_tweaks_module' ) ? sb_tweaks_module( 'site_kit' ) : null;
	}

	/** A feature's card setting: the saved value, or its default. */
	public static function setting( $id, $key ) {
		$features = self::features();

		if ( $features ) {
			return $features->setting( $id, $key );
		}

		$saved = (array) get_option( self::SETTINGS_OPTION, [] );

		return isset( $saved[ $id ] ) && is_array( $saved[ $id ] ) && array_key_exists( $key, $saved[ $id ] ) ? $saved[ $id ][ $key ] : null;
	}

	public static function is_enabled( $id ) {
		$features = self::features();

		return $features ? $features->is_enabled( $id ) : false;
	}

	/** A group's page, or a feature's own: sb-site-kit-<name>, as in the standalone plugin. */
	public static function page_slug( $name ) {
		return self::SLUG . '-' . sanitize_key( $name );
	}
}
