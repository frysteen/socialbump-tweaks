<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the Woo Tweaks features ask of their module (a card setting, whether a
 * feature is on, a page address), the one off copy of Site Kit's WooCommerce
 * settings, and the redirect from Site Kit's old WooCommerce page.
 */
class SB_Woo_Tweaks {

	const FEATURES_OPTION = 'sb_tweaks_woo_features';

	const SETTINGS_OPTION = 'sb_tweaks_woo_settings';

	const GROUPS_OPTION = 'sb_tweaks_woo_groups';

	/** Set once the copy from Site Kit has run, so it never runs twice. */
	const MIGRATED = 'sb_tweaks_woo_migrated';

	/** The module's menu slug, which every page address starts with. */
	const SLUG = 'sb-woo-tweaks';

	/** The features that came from Site Kit, under the ids Site Kit saved. */
	const FEATURE_IDS = [
		'woo-analytics-csv',
		'woo-cart-both-prices',
		'woo-default-category-title',
		'woo-from-price',
		'woo-gallery-zoom',
		'woo-require-login-checkout',
		'woo-trim-zeros',
	];

	public static function boot() {
		add_action( 'admin_page_access_denied', [ __CLASS__, 'old_address' ] );
	}

	/** The booted features loader, or null while the module is off. */
	public static function features() {
		return function_exists( 'sb_tweaks_module' ) ? sb_tweaks_module( 'woo' ) : null;
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

	/** A group's page: sb-woo-tweaks-<group>. */
	public static function page_slug( $name ) {
		return self::SLUG . '-' . sanitize_key( $name );
	}

	/**
	 * Site Kit's WooCommerce page, bookmarked or linked from somewhere, opens
	 * Shop and Checkout here instead. WordPress asks this just before refusing
	 * a page it does not know.
	 */
	public static function old_address() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( $page !== 'sb-site-kit-woocommerce' || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::page_slug( 'shop' ) ) );
		exit;
	}

	/**
	 * Copy the WooCommerce switches and settings across from Site Kit, once.
	 *
	 * Runs on every request until it has run (the framework calls it before it
	 * asks whether the module is on) and never overwrites anything this module
	 * already holds. Site Kit's own options are left exactly as they are.
	 *
	 * The module is switched on (its features option created, which is what
	 * 'auto' looks for) only where the store could have been using these: Site
	 * Kit's WooCommerce group was on and WooCommerce is active, or a WooCommerce
	 * switch had been saved on. A site without a store gets nothing.
	 */
	public static function migrate() {
		if ( get_option( self::MIGRATED ) ) {
			return;
		}

		$kit_features = get_option( 'sb_tweaks_site_kit_features' );

		// A site that never had Site Kit starts fresh, with the module off.
		if ( $kit_features === false ) {
			update_option( self::MIGRATED, 1, false );

			return;
		}

		$kit_features = (array) $kit_features;
		$kit_settings = (array) get_option( 'sb_tweaks_site_kit_settings', [] );
		$kit_groups   = (array) get_option( 'sb_tweaks_site_kit_groups', [] );

		$switches = [];
		$settings = [];
		$any_on   = false;

		foreach ( self::FEATURE_IDS as $id ) {
			if ( array_key_exists( $id, $kit_features ) ) {
				$switches[ $id ] = (int) ! empty( $kit_features[ $id ] );
				$any_on          = $any_on || ! empty( $kit_features[ $id ] );
			}

			if ( isset( $kit_settings[ $id ] ) && is_array( $kit_settings[ $id ] ) ) {
				$settings[ $id ] = $kit_settings[ $id ];
			}
		}

		$group_on = ! array_key_exists( 'woocommerce', $kit_groups ) || ! empty( $kit_groups['woocommerce'] );

		if ( ( $group_on && class_exists( 'WooCommerce' ) ) || $any_on ) {
			if ( get_option( self::FEATURES_OPTION ) === false ) {
				update_option( self::FEATURES_OPTION, $switches );
			}

			if ( $settings && get_option( self::SETTINGS_OPTION ) === false ) {
				update_option( self::SETTINGS_OPTION, $settings );
			}

			// Site Kit had one switch for the lot; both pages here follow it.
			if ( array_key_exists( 'woocommerce', $kit_groups ) && get_option( self::GROUPS_OPTION ) === false ) {
				update_option(
					self::GROUPS_OPTION,
					[
						'shop'  => (int) $group_on,
						'admin' => (int) $group_on,
					]
				);
			}
		}

		update_option( self::MIGRATED, 1, false );
	}
}
