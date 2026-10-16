<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Image Management module itself: its settings, its page addresses, its
 * scripts and styles, and the one off copy of the settings it took over from
 * Site Kit.
 *
 * The image code was Site Kit's Images and Image Cleaner pages. It became its
 * own module so a site that only wants the image tools can have them alone,
 * and so it could one day be a plugin of its own again. Its options are
 * sb_tweaks_images_features, _settings and _groups, plus the few below.
 */
class SB_Images_Module {

	const FEATURES_OPTION = 'sb_tweaks_images_features';

	const SETTINGS_OPTION = 'sb_tweaks_images_settings';

	const GROUPS_OPTION = 'sb_tweaks_images_groups';

	/** Set once the Site Kit settings have been looked at, whether or not there were any. */
	const MIGRATED = 'sb_tweaks_images_migrated';

	/** The standalone plugin whose image code this is. */
	const SITE_KIT = 'socialbump-site-kit/socialbump-site-kit.php';

	/** The module's menu slug: its pages are sb-image-settings and sb-image-cleaner. */
	const SLUG = 'sb-image';

	/** The booted features loader, or null while the module is off. */
	public static function features() {
		return function_exists( 'sb_tweaks_module' ) ? sb_tweaks_module( 'images' ) : null;
	}

	/** A feature's setting: the saved value, or its default. */
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

	/** One of the two pages, settings or cleaner, in the Media menu. */
	public static function page_url( $group, array $args = [] ) {
		return add_query_arg( array_merge( [ 'page' => self::SLUG . '-' . sanitize_key( $group ) ], $args ), admin_url( 'upload.php' ) );
	}

	public static function boot() {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'assets' ] );
		add_action( 'admin_page_access_denied', [ __CLASS__, 'old_addresses' ] );
	}

	/**
	 * The pages' own script and styles, and on the media screens the ones the
	 * sizes panel on each image needs. The framework loads its admin.css and
	 * popups on the two pages; the media screens get the popup script here,
	 * since images.js asks through it.
	 */
	public static function assets( $hook ) {
		$ours  = strpos( (string) $hook, self::SLUG ) !== false;
		$media = in_array( $hook, [ 'upload.php', 'post.php', 'post-new.php' ], true );

		if ( ! $ours && ! $media ) {
			return;
		}

		$css = SB_IMAGES_PATH . 'assets/css/images.css';
		$js  = SB_IMAGES_PATH . 'assets/js/images.js';

		wp_enqueue_style( 'sb-images', SB_IMAGES_URL . 'assets/css/images.css', [], SB_TWEAKS_VERSION . '.' . ( file_exists( $css ) ? filemtime( $css ) : 0 ) );

		$confirm = SB_TWEAKS_PATH . 'assets/js/confirm.js';

		wp_enqueue_script( 'sb-tweaks-confirm', SB_TWEAKS_URL . 'assets/js/confirm.js', [ 'jquery' ], SB_TWEAKS_VERSION . '.' . ( file_exists( $confirm ) ? filemtime( $confirm ) : 0 ), true );
		wp_enqueue_script( 'sb-images', SB_IMAGES_URL . 'assets/js/images.js', [ 'jquery', 'sb-tweaks-confirm' ], SB_TWEAKS_VERSION . '.' . ( file_exists( $js ) ? filemtime( $js ) : 0 ), true );
	}

	/**
	 * Site Kit's Images and Image Cleaner pages, bookmarked or linked from
	 * somewhere, open their new homes in the Media menu. WordPress asks this just
	 * before refusing a page it does not know.
	 */
	public static function old_addresses() {
		$old = [
			'sb-site-kit-images'        => 'settings',
			'sb-site-kit-image-cleaner' => 'cleaner',
		];

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( ! isset( $old[ $page ] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_safe_redirect( self::page_url( $old[ $page ] ) );
		exit;
	}

	/**
	 * Copy the image settings across from Site Kit, once.
	 *
	 * Runs on every request until it has run (the framework calls it before it
	 * asks whether the module is on), and never overwrites anything this module
	 * already holds. Site Kit's own options are left exactly as they are, so the
	 * standalone plugin still has them if a site ever goes back to it.
	 *
	 * On the hub, standalone Site Kit stays on until it is switched off by hand,
	 * and its image pages could still change what is copied, so the copy waits
	 * until it is off. Anywhere else the plugin is retired on the same request
	 * this runs on, with its settings as it left them.
	 */
	public static function migrate() {
		if ( get_option( self::MIGRATED ) ) {
			return;
		}

		$hub = function_exists( 'sb_tweaks_is_hub' ) && sb_tweaks_is_hub();

		if ( $hub && in_array( self::SITE_KIT, (array) get_option( 'active_plugins', [] ), true ) ) {
			return;
		}

		// A site that never had Site Kit starts fresh, with the module off.
		if ( get_option( 'sb_tweaks_site_kit_features' ) === false ) {
			update_option( self::MIGRATED, 1, false );

			return;
		}

		$kit = (array) get_option( 'sb_tweaks_site_kit_settings', [] );

		// Site Kit 1.2.1 kept the old standard widths for a site that had never
		// saved its Images page (sbsk_keep_old_image_defaults). A site that never
		// ran a version with that check gets the same treatment here.
		if ( (int) get_option( 'sb_tweaks_site_kit_image_defaults' ) < 1 && ( isset( $kit['images'] ) || get_option( 'sb_tweaks_site_kit_owned_image_sizes' ) !== false ) ) {
			$images = isset( $kit['images'] ) && is_array( $kit['images'] ) ? $kit['images'] : [];

			if ( empty( $images['sizes'] ) || ! is_array( $images['sizes'] ) ) {
				$images['sizes'] = [ 240, 480, 640, 720, 960, 1200, 1920 ];
			}

			if ( ! array_key_exists( 'wp_off', $images ) ) {
				$images['wp_off'] = [];
			}

			$kit['images'] = $images;
		}

		$settings = (array) get_option( self::SETTINGS_OPTION, [] );

		foreach ( [ 'images', 'image-cleaner' ] as $id ) {
			if ( isset( $kit[ $id ] ) && is_array( $kit[ $id ] ) && ! isset( $settings[ $id ] ) ) {
				$settings[ $id ] = $kit[ $id ];
			}
		}

		// The cleaner's old switch inside the Images page has been a page of its own since Site Kit 1.2.
		$rebuild_on = ! isset( $settings['images']['rebuild_on'] ) || ! empty( $settings['images']['rebuild_on'] );

		unset( $settings['images']['rebuild_on'] );

		if ( isset( $settings['images'] ) && ! $settings['images'] ) {
			unset( $settings['images'] );
		}

		if ( $settings ) {
			update_option( self::SETTINGS_OPTION, $settings );
		}

		foreach ( [ 'kept_orphans', 'owned_image_sizes', 'adopted_image_sizes' ] as $key ) {
			$old = get_option( 'sb_tweaks_site_kit_' . $key );

			if ( $old !== false && get_option( 'sb_tweaks_images_' . $key ) === false ) {
				update_option( 'sb_tweaks_images_' . $key, $old );
			}
		}

		// Each person's ticked sizes on the cleaner, and their media grid choices.
		global $wpdb;

		foreach ( [ 'cleaner_sizes', 'media_grid' ] as $key ) {
			$users = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s", 'sb_tweaks_site_kit_' . $key ) );

			foreach ( $users as $user_id ) {
				if ( metadata_exists( 'user', (int) $user_id, 'sb_tweaks_images_' . $key ) ) {
					continue;
				}

				update_user_meta( (int) $user_id, 'sb_tweaks_images_' . $key, get_user_meta( (int) $user_id, 'sb_tweaks_site_kit_' . $key, true ) );
			}
		}

		// On where Site Kit had either image page on. Images was on unless
		// switched off; Image Cleaner was off unless switched on, and before it
		// had a switch of its own it followed the old setting inside Images.
		$groups   = (array) get_option( 'sb_tweaks_site_kit_groups', [] );
		$switches = (array) get_option( 'sb_tweaks_site_kit_features', [] );

		$images_on = ( ! array_key_exists( 'images', $groups ) || ! empty( $groups['images'] ) )
			&& ( ! array_key_exists( 'images', $switches ) || ! empty( $switches['images'] ) );

		if ( array_key_exists( 'image-cleaner', $groups ) ) {
			$cleaner_on = ! empty( $groups['image-cleaner'] );
		} else {
			$cleaner_on = (int) get_option( 'sb_tweaks_site_kit_image_split' ) < 2 && $rebuild_on;
		}

		$cleaner_on = $cleaner_on && ( ! array_key_exists( 'image-cleaner', $switches ) || ! empty( $switches['image-cleaner'] ) );

		// The module's 'auto' default switches it on where this option exists.
		if ( ( $images_on || $cleaner_on ) && get_option( self::FEATURES_OPTION ) === false ) {
			update_option( self::FEATURES_OPTION, [] );
		}

		update_option( self::MIGRATED, 1, false );
	}
}
