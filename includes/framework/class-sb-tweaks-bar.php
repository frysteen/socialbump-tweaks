<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The SocialBUMP item in the admin bar.
 *
 * Ported from the SocialBUMP_Admin_Bar class the standalone plugins share,
 * minus the load-order dance: inside one plugin there is exactly one copy.
 * The unused accent helpers did not make the trip; the bar's own colours are
 * fixed so it reads the same in every admin scheme.
 *
 * The plugin and each enabled module register what they want shown and this
 * draws the result once, at admin_bar_menu priority 200:
 *
 * - One thing registered: it sits on the bar on its own, by name. The plugin
 *   only registers itself when no module is on, or when two or more are, so
 *   a single enabled module gets the bar to itself, which is today's
 *   behaviour preserved.
 * - Two or more: a single SocialBUMP item, each registration a row inside it,
 *   its pages on a flyout from that row.
 *
 * The dot carries the status: green when there is nothing to do, amber when
 * something wants attention. A row that is amber makes the SocialBUMP item
 * amber too, so the top of the bar is the only thing that needs watching.
 */
class SB_Tweaks_Bar {

	const PARENT = 'socialbump';

	/** Everything registered this request, in registration order. */
	private static $plugins = [];

	private static $booted = false;

	/**
	 * Add a row to the menu.
	 *
	 * Expects: id, label, href, items. Optionally attention (bool),
	 * attention_title, current (bool) and actions, which are drawn above the
	 * pages for things like a rebuild button.
	 */
	public static function register( array $plugin ) {
		if ( empty( $plugin['id'] ) || empty( $plugin['label'] ) ) {
			return;
		}

		self::$plugins[ $plugin['id'] ] = wp_parse_args(
			$plugin,
			[
				'href'            => '',
				'items'           => [],
				'actions'         => [],
				'attention'       => false,
				'attention_title' => '',
				'current'         => false,
			]
		);

		self::boot();
	}

	/** Draw once, after everything has had its say. */
	private static function boot() {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		add_action( 'admin_bar_menu', [ __CLASS__, 'render' ], 200 );
		/**
		 * Printed in the footer as well as the head.
		 *
		 * A row registers while the bar is being built, which in the admin is
		 * after the head has already gone out, so a head only style would never
		 * appear. Printing once is guaranteed either way.
		 */
		add_action( 'admin_head', [ __CLASS__, 'styles' ] );
		add_action( 'wp_head', [ __CLASS__, 'styles' ] );
		add_action( 'admin_footer', [ __CLASS__, 'styles' ] );
		add_action( 'wp_footer', [ __CLASS__, 'styles' ] );
	}

	/** A coloured dot for the menu title. */
	private static function dot( $attention ) {
		$colour = $attention ? '#d97706' : '#46b450';

		return '<span class=' . chr( 34 ) . 'sb-bar-dot' . chr( 34 ) . ' style=' . chr( 34 ) . 'display:inline-block;width:9px;height:9px;border-radius:50%;background:' . $colour . ';margin-right:7px;vertical-align:middle;' . chr( 34 ) . '></span>';
	}

	/**
	 * Draw the menu.
	 *
	 * One row goes straight on the bar. More than one share a single item,
	 * with each row inside it and its pages on a flyout from there.
	 */
	public static function render( $bar ) {
		// WordPress only fires this hook when the bar is being drawn.
		if ( ! self::$plugins ) {
			return;
		}

		$plugins = self::$plugins;

		if ( count( $plugins ) === 1 ) {
			$only = reset( $plugins );

			self::add_plugin( $bar, $only, false );

			return;
		}

		// Anything wanting attention colours the top item too.
		$attention = false;
		$waiting   = [];
		$current   = false;

		foreach ( $plugins as $plugin ) {
			if ( ! empty( $plugin['attention'] ) ) {
				$attention = true;
				$waiting[] = $plugin['label'];
			}

			if ( ! empty( $plugin['current'] ) ) {
				$current = true;
			}
		}

		$tip = $waiting ? implode( ', ', $waiting ) . ' ' . __( 'needs attention', 'sb-tweaks' ) : __( 'Nothing needs attention', 'sb-tweaks' );

		// The Modules page is the front door, so the combined item opens it.
		$bar->add_node(
			[
				'id'    => self::PARENT,
				'title' => self::dot( $attention ) . 'SocialBUMP',
				'href'  => admin_url( 'admin.php?page=' . SB_Tweaks_Settings::PAGE_SLUG ),
				'meta'  => [ 'title' => $tip, 'class' => self::class_for( $current ) ],
			]
		);

		foreach ( $plugins as $plugin ) {
			self::add_plugin( $bar, $plugin, true );
		}
	}

	/** One row, either on the bar itself or inside the shared item. */
	private static function add_plugin( $bar, $plugin, $nested ) {
		$id    = 'sb-bar-' . sanitize_key( $plugin['id'] );
		$title = self::dot( ! empty( $plugin['attention'] ) ) . $plugin['label'];
		$tip   = ! empty( $plugin['attention'] ) && $plugin['attention_title'] !== '' ? $plugin['attention_title'] : '';

		$node = [
			'id'    => $id,
			'title' => $title,
			'href'  => $plugin['href'],
			'meta'  => [ 'class' => self::class_for( ! empty( $plugin['current'] ) ) ],
		];

		if ( $tip !== '' ) {
			$node['meta']['title'] = $tip;
		}

		if ( $nested ) {
			$node['parent'] = self::PARENT;
		}

		$bar->add_node( $node );

		// Actions first, since they are the things you came to press.
		foreach ( (array) $plugin['actions'] as $i => $action ) {
			$bar->add_node(
				[
					'id'     => $id . '-action-' . $i,
					'parent' => $id,
					'title'  => $action['title'],
					'href'   => isset( $action['href'] ) ? $action['href'] : false,
					'meta'   => isset( $action['meta'] ) ? (array) $action['meta'] : [],
				]
			);
		}

		foreach ( (array) $plugin['items'] as $i => $item ) {
			$bar->add_node(
				[
					'id'     => $id . '-page-' . $i,
					'parent' => $id,
					'title'  => esc_html( $item['title'] ) . self::count( $item ),
					'href'   => $item['href'],
					'meta'   => [ 'class' => trim( self::class_for( ! empty( $item['current'] ) ) . ( ! empty( $item['attention'] ) ? ' sb-bar-pending' : '' ) ) ],
				]
			);
		}
	}

	/** A small count beside a page that is waiting on you. */
	private static function count( $item ) {
		$number = isset( $item['count'] ) ? (int) $item['count'] : 0;

		if ( $number < 1 ) {
			return '';
		}

		return '<span class=' . chr( 34 ) . 'sb-bar-count' . chr( 34 ) . '>' . esc_html( number_format_i18n( $number ) ) . '</span>';
	}

	private static function class_for( $current ) {
		return $current ? 'sb-bar-current' : '';
	}

	/**
	 * The highlight for the page you are on, and the flyout for nested pages.
	 *
	 * Printed rather than enqueued, because the bar shows on the front end too.
	 */
	public static function styles() {
		static $printed = false;

		if ( $printed || ! is_admin_bar_showing() ) {
			return;
		}

		$printed = true;

		// Bold rather than coloured: an admin colour scheme accent can read badly
		// against the dark bar, and this has to look right in all of them.
		$css = '#wpadminbar .sb-bar-current > .ab-item{color:#fff !important;font-weight:600;}';

		// A row inside the shared item opens its own pages to the side.
		$css .= '#wpadminbar #wp-admin-bar-' . self::PARENT . ' .ab-submenu .menupop{position:relative;}';
		$css .= '#wpadminbar #wp-admin-bar-' . self::PARENT . ' .ab-submenu .menupop > .ab-sub-wrapper{left:100%;top:0;margin:0;}';

		// An action with nothing to do reads as such; one with work waiting stands out.
		// No link means WordPress draws an empty item, and it colours both on hover.
		$css .= '#wpadminbar .sb-bar-action.is-idle > .ab-item,#wpadminbar .sb-bar-action.is-idle > .ab-empty-item,#wpadminbar .sb-bar-action.is-idle:hover > .ab-item,#wpadminbar .sb-bar-action.is-idle:hover > .ab-empty-item,#wpadminbar .sb-bar-action.is-idle > .ab-item:focus{color:#787c82 !important;opacity:0.65;cursor:default;pointer-events:none;}';
		$css .= '#wpadminbar .sb-bar-action:not(.is-idle) > .ab-item,#wpadminbar .sb-bar-action:not(.is-idle):hover > .ab-item,#wpadminbar .sb-bar-action:not(.is-idle) > .ab-item:focus{color:#f0b849 !important;font-weight:600;}';

		// A page with something waiting on it, such as changes to publish.
		$css .= '#wpadminbar .sb-bar-pending > .ab-item,#wpadminbar .sb-bar-pending:hover > .ab-item,#wpadminbar .sb-bar-pending > .ab-item:focus{color:#f0b849 !important;font-weight:600;}';
		$css .= '#wpadminbar .sb-bar-count{display:inline-block;min-width:17px;height:17px;margin-left:7px;padding:0 4px;border-radius:9px;background:#f0b849;color:#1d2327;font-size:11px;font-weight:700;line-height:17px;text-align:center;vertical-align:1px;}';

		echo '<style>' . $css . '</style>';
	}
}
