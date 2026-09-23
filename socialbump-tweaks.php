<?php
/**
 * Plugin Name: SocialBUMP Tweaks
 * Plugin URI:  https://socialbump.com.au
 * Description: SocialBUMP site tweaks in switchable modules. Turn on only the parts a site needs.
 * Version:     0.1.6
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

// Tells the SocialBUMP hub this site has the plugin, its version and whether it is active.
require_once __DIR__ . '/includes/class-socialbump-reporter.php';

define( 'SB_TWEAKS_VERSION', '0.1.6' );
define( 'SB_TWEAKS_FILE', __FILE__ );
define( 'SB_TWEAKS_PATH', plugin_dir_path( __FILE__ ) );
define( 'SB_TWEAKS_URL', plugin_dir_url( __FILE__ ) );
define( 'SB_TWEAKS_OPTION', 'sb_tweaks_modules' );
define( 'SB_TWEAKS_SLUG', 'socialbump-tweaks' );
define( 'SB_TWEAKS_GITHUB_REPO', 'frysteen/socialbump-tweaks' );
define( 'SB_TWEAKS_HUB_HOST', 'plugins.socialbump.com.au' );

/**
 * Updates come from GitHub Releases. A release only counts as an update
 * when it has socialbump-tweaks.zip attached.
 */
function sb_tweaks_updater() {
	$loader = SB_TWEAKS_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';

	if ( ! is_readable( $loader ) ) {
		return;
	}

	require_once $loader;

	if ( ! class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
		return;
	}

	try {
		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/' . SB_TWEAKS_GITHUB_REPO . '/',
			SB_TWEAKS_FILE,
			SB_TWEAKS_SLUG
		);

		// 2 = Api::REQUIRE_RELEASE_ASSETS. Releases without the zip are ignored.
		$checker->getVcsApi()->enableReleaseAssets( '/^socialbump-tweaks\.zip$/', 2 );

		$GLOBALS['sb_tweaks_update_checker'] = $checker;

		/**
		 * Some plugins hook plugins_api at the default priority and return false for
		 * every request, not just their own, which wipes out the plugin details this
		 * updater supplies. Re-add the details callback later so View details still works.
		 */
		remove_filter( 'plugins_api', [ $checker, 'injectInfo' ], 20 );
		add_filter( 'plugins_api', [ $checker, 'injectInfo' ], 999, 3 );

		// Plugins outside the WordPress directory have no icon unless the update data supplies one.
		add_filter(
			'puc_request_info_result-' . SB_TWEAKS_SLUG,
			function ( $info ) {
				if ( is_object( $info ) ) {
					$info->icons = [
						'1x'      => SB_TWEAKS_URL . 'assets/img/icon-128x128.png',
						'2x'      => SB_TWEAKS_URL . 'assets/img/icon-256x256.png',
						'default' => SB_TWEAKS_URL . 'assets/img/icon-256x256.png',
					];
				}

				return $info;
			}
		);
	} catch ( \Throwable $e ) {
		// Never let the updater take a site down.
	}
}
sb_tweaks_updater();

/**
 * True only on the hub site, where releases are built and published.
 * Define SB_TWEAKS_IS_HUB in wp-config.php to override.
 */
function sb_tweaks_is_hub() {
	if ( defined( 'SB_TWEAKS_IS_HUB' ) ) {
		return (bool) SB_TWEAKS_IS_HUB;
	}

	return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === SB_TWEAKS_HUB_HOST;
}
/**
 * Note a change for the next release.
 *
 * Anything logged here fills in the notes box on the Publishing page, and the
 * list is emptied once a release goes out.
 */
function sb_tweaks_log_change( $text ) {
	$text = trim( wp_strip_all_tags( (string) $text ) );

	if ( $text === '' ) {
		return;
	}

	$list = (array) get_option( 'sb_tweaks_pending_changes', [] );

	if ( in_array( $text, $list, true ) ) {
		return;
	}

	$list[] = $text;

	update_option( 'sb_tweaks_pending_changes', array_slice( $list, -50 ), false );
}

/**
 * A module's features loader, for code that lives outside the module.
 *
 * The Bricks module's elements, for instance, ask it whether a feature is on
 * before they register. Null until the modules have booted, and null for a
 * module that is switched off or not installed.
 */
function sb_tweaks_module( $id ) {
	return class_exists( 'SB_Tweaks_Modules' ) ? SB_Tweaks_Modules::instance()->features( $id ) : null;
}

/**
 * Load the plugin.
 *
 * The framework classes load first, then the Settings page, then whatever
 * modules sit in the modules folder. Each module registers itself through
 * SB_Tweaks_Modules; nothing here needs editing when one lands.
 */
function sb_tweaks_boot() {
	require_once SB_TWEAKS_PATH . 'includes/class-sb-tweaks-settings.php';
	require_once SB_TWEAKS_PATH . 'includes/class-sb-tweaks-credit.php';

	// The framework the modules are built on. The only order that matters is
	// that these all exist before SB_Tweaks_Modules starts reading modules.
	require_once SB_TWEAKS_PATH . 'includes/framework/class-sb-tweaks-cards.php';
	require_once SB_TWEAKS_PATH . 'includes/framework/class-sb-tweaks-bar.php';
	require_once SB_TWEAKS_PATH . 'includes/framework/class-sb-tweaks-fields.php';
	require_once SB_TWEAKS_PATH . 'includes/framework/class-sb-tweaks-save.php';
	require_once SB_TWEAKS_PATH . 'includes/framework/class-sb-tweaks-features.php';
	require_once SB_TWEAKS_PATH . 'includes/framework/class-sb-tweaks-screen.php';
	require_once SB_TWEAKS_PATH . 'includes/framework/class-sb-tweaks-modules.php';

	SB_Tweaks_Settings::instance()->boot();
	SB_Tweaks_Save::boot();
	SB_Tweaks_Modules::instance()->boot();

	// Part of the plugin, not a module: if this is running, the admin footer
	// says so, whatever is switched on.
	SB_Tweaks_Credit::boot();

	// The Updates page: every site, not just the hub.
	require_once SB_TWEAKS_PATH . 'includes/class-sb-tweaks-updates.php';
	SB_Tweaks_Updates::boot();

	if ( sb_tweaks_is_hub() ) {
		require_once SB_TWEAKS_PATH . 'includes/class-sb-tweaks-release.php';
		SB_Tweaks_Release::instance()->boot();

		require_once SB_TWEAKS_PATH . 'includes/class-sb-tweaks-docs.php';
		SB_Tweaks_Docs::boot();

		// Which SocialBUMP plugins are installed where, reported by each site.
		require_once SB_TWEAKS_PATH . 'includes/class-sb-tweaks-installs.php';
		SB_Tweaks_Installs::boot();

		// Updates pushed to sites from the Installs page.
		require_once SB_TWEAKS_PATH . 'includes/class-sb-tweaks-push.php';
		SB_Tweaks_Push::boot();
	}
}
add_action( 'plugins_loaded', 'sb_tweaks_boot' );

/**
 * Make sure the new files are the ones that run.
 *
 * Updating a plugin swaps its files out mid request. If you were on one of its
 * own pages at the time, the page you land on afterwards can still be running
 * the old code, so its menus never register and the plugin appears to vanish
 * until you go somewhere else. Clearing the compiled copies as soon as the
 * update finishes means the next request reads what is actually on disk.
 */
function sb_tweaks_forget_compiled( $upgrader, $extra ) {
	if ( ! function_exists( 'opcache_invalidate' ) ) {
		return;
	}

	$ours = plugin_basename( SB_TWEAKS_FILE );
	$mine = isset( $extra['plugins'] ) && in_array( $ours, (array) $extra['plugins'], true );

	// A single update reports the plugin on its own rather than in a list.
	if ( ! $mine && isset( $extra['plugin'] ) && $extra['plugin'] === $ours ) {
		$mine = true;
	}

	if ( ! $mine ) {
		return;
	}

	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( SB_TWEAKS_PATH, FilesystemIterator::SKIP_DOTS ) );

	foreach ( $files as $file ) {
		if ( $file->getExtension() === 'php' ) {
			@opcache_invalidate( $file->getPathname(), true );
		}
	}
}
add_action( 'upgrader_process_complete', 'sb_tweaks_forget_compiled', 10, 2 );

/**
 * Nothing about publishing belongs on a site that is not the hub.
 *
 * The hub is the blueprint new sites are built from, so whatever sits in its
 * database travels with every copy. A GitHub token has no business on a client
 * site, and the release notes waiting to be published are only noise there.
 */
function sb_tweaks_tidy_away_hub_data() {
	if ( sb_tweaks_is_hub() ) {
		return;
	}

	foreach ( [ 'sb_tweaks_github_token', 'sb_tweaks_pending_changes', 'sb_tweaks_latest_release' ] as $option ) {
		if ( get_option( $option ) !== false ) {
			delete_option( $option );
		}
	}
}
add_action( 'admin_init', 'sb_tweaks_tidy_away_hub_data' );

/** A Settings link on the plugins screen, like the other SocialBUMP plugins. */
function sb_tweaks_action_links( $links ) {
	$url = admin_url( 'admin.php?page=' . SB_Tweaks_Settings::PAGE_SLUG );

	array_unshift( $links, '<a href=' . chr( 34 ) . esc_url( $url ) . chr( 34 ) . '>' . esc_html__( 'Settings', 'sb-tweaks' ) . '</a>' );

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'sb_tweaks_action_links' );