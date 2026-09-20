<?php
/**
 * Plugin Name: SocialBUMP Tweaks
 * Plugin URI:  https://socialbump.com.au
 * Description: SocialBUMP site tweaks in switchable modules. Turn on only the parts a site needs.
 * Version:     0.1.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author:      SocialBUMP
 * Author URI:  https://socialbump.com.au
 * License:     GPL-2.0-or-later
 * Text Domain: sb-tweaks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SBTWEAKS_VERSION', '0.1.1' );
define( 'SBTWEAKS_FILE', __FILE__ );
define( 'SBTWEAKS_PATH', plugin_dir_path( __FILE__ ) );
define( 'SBTWEAKS_URL', plugin_dir_url( __FILE__ ) );
define( 'SBTWEAKS_OPTION', 'sbtweaks_modules' );
define( 'SBTWEAKS_SLUG', 'socialbump-tweaks' );
define( 'SBTWEAKS_GITHUB_REPO', 'frysteen/socialbump-tweaks' );
define( 'SBTWEAKS_HUB_HOST', 'bricks.socialbump.com.au' );

/**
 * Updates come from GitHub Releases. A release only counts as an update
 * when it has socialbump-tweaks.zip attached.
 */
function sbtweaks_updater() {
	$loader = SBTWEAKS_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';

	if ( ! is_readable( $loader ) ) {
		return;
	}

	require_once $loader;

	if ( ! class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
		return;
	}

	try {
		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/' . SBTWEAKS_GITHUB_REPO . '/',
			SBTWEAKS_FILE,
			SBTWEAKS_SLUG
		);

		// 2 = Api::REQUIRE_RELEASE_ASSETS. Releases without the zip are ignored.
		$checker->getVcsApi()->enableReleaseAssets( '/^socialbump-tweaks\.zip$/', 2 );

		$GLOBALS['sbtweaks_update_checker'] = $checker;

		/**
		 * Some plugins hook plugins_api at the default priority and return false for
		 * every request, not just their own, which wipes out the plugin details this
		 * updater supplies. Re-add the details callback later so View details still works.
		 */
		remove_filter( 'plugins_api', [ $checker, 'injectInfo' ], 20 );
		add_filter( 'plugins_api', [ $checker, 'injectInfo' ], 999, 3 );

		// Plugins outside the WordPress directory have no icon unless the update data supplies one.
		add_filter(
			'puc_request_info_result-' . SBTWEAKS_SLUG,
			function ( $info ) {
				if ( is_object( $info ) ) {
					$info->icons = [
						'1x'      => SBTWEAKS_URL . 'assets/img/icon-128x128.png',
						'2x'      => SBTWEAKS_URL . 'assets/img/icon-256x256.png',
						'default' => SBTWEAKS_URL . 'assets/img/icon-256x256.png',
					];
				}

				return $info;
			}
		);
	} catch ( \Throwable $e ) {
		// Never let the updater take a site down.
	}
}
sbtweaks_updater();

/**
 * True only on the hub site, where releases are built and published.
 * Define SBTWEAKS_IS_HUB in wp-config.php to override.
 */
function sbtweaks_is_hub() {
	if ( defined( 'SBTWEAKS_IS_HUB' ) ) {
		return (bool) SBTWEAKS_IS_HUB;
	}

	return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === SBTWEAKS_HUB_HOST;
}
/**
 * Note a change for the next release.
 *
 * Anything logged here fills in the notes box on the Publishing page, and the
 * list is emptied once a release goes out.
 */
function sbtweaks_log_change( $text ) {
	$text = trim( wp_strip_all_tags( (string) $text ) );

	if ( $text === '' ) {
		return;
	}

	$list = (array) get_option( 'sbtweaks_pending_changes', [] );

	if ( in_array( $text, $list, true ) ) {
		return;
	}

	$list[] = $text;

	update_option( 'sbtweaks_pending_changes', array_slice( $list, -50 ), false );
}

/**
 * Load the plugin.
 *
 * Nothing but the front door so far: the Modules page and, on the hub,
 * Publishing. Modules are migrated in one at a time and each will register
 * itself here.
 */
function sbtweaks_boot() {
	require_once SBTWEAKS_PATH . 'includes/class-sbtweaks-settings.php';

	SBTWEAKS_Settings::instance()->boot();

	if ( sbtweaks_is_hub() ) {
		require_once SBTWEAKS_PATH . 'includes/class-sbtweaks-release.php';
		SBTWEAKS_Release::instance()->boot();

		require_once SBTWEAKS_PATH . 'includes/class-sbtweaks-docs.php';
		SBTWEAKS_Docs::boot();
	}
}
add_action( 'plugins_loaded', 'sbtweaks_boot' );

/**
 * Make sure the new files are the ones that run.
 *
 * Updating a plugin swaps its files out mid request. If you were on one of its
 * own pages at the time, the page you land on afterwards can still be running
 * the old code, so its menus never register and the plugin appears to vanish
 * until you go somewhere else. Clearing the compiled copies as soon as the
 * update finishes means the next request reads what is actually on disk.
 */
function sbtweaks_forget_compiled( $upgrader, $extra ) {
	if ( ! function_exists( 'opcache_invalidate' ) ) {
		return;
	}

	$ours = plugin_basename( SBTWEAKS_FILE );
	$mine = isset( $extra['plugins'] ) && in_array( $ours, (array) $extra['plugins'], true );

	// A single update reports the plugin on its own rather than in a list.
	if ( ! $mine && isset( $extra['plugin'] ) && $extra['plugin'] === $ours ) {
		$mine = true;
	}

	if ( ! $mine ) {
		return;
	}

	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( SBTWEAKS_PATH, FilesystemIterator::SKIP_DOTS ) );

	foreach ( $files as $file ) {
		if ( $file->getExtension() === 'php' ) {
			@opcache_invalidate( $file->getPathname(), true );
		}
	}
}
add_action( 'upgrader_process_complete', 'sbtweaks_forget_compiled', 10, 2 );

/**
 * Nothing about publishing belongs on a site that is not the hub.
 *
 * The hub is the blueprint new sites are built from, so whatever sits in its
 * database travels with every copy. A GitHub token has no business on a client
 * site, and the release notes waiting to be published are only noise there.
 */
function sbtweaks_tidy_away_hub_data() {
	if ( sbtweaks_is_hub() ) {
		return;
	}

	foreach ( [ 'sbtweaks_github_token', 'sbtweaks_pending_changes', 'sbtweaks_latest_release' ] as $option ) {
		if ( get_option( $option ) !== false ) {
			delete_option( $option );
		}
	}
}
add_action( 'admin_init', 'sbtweaks_tidy_away_hub_data' );

/** A Settings link on the plugins screen, like the other SocialBUMP plugins. */
function sbtweaks_action_links( $links ) {
	$url = admin_url( 'admin.php?page=' . SBTWEAKS_Settings::PAGE_SLUG );

	array_unshift( $links, '<a href=' . chr( 34 ) . esc_url( $url ) . chr( 34 ) . '>' . esc_html__( 'Settings', 'sb-tweaks' ) . '</a>' );

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'sbtweaks_action_links' );