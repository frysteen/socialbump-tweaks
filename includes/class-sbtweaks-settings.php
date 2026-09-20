<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin screens for SocialBUMP Tweaks.
 *
 * This is the front door: one top level menu with the Modules page behind it,
 * and Publishing beside it on the hub. A module switched on registers its own
 * top level menu with its own pages, the way each of these was a plugin of its
 * own before.
 *
 * Nothing here knows about any particular module. The Modules page is empty
 * until the first one is migrated in.
 */
class SBTWEAKS_Settings {

	private static $instance = null;

	const PAGE_SLUG = 'sb-tweaks';

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot() {
		add_action( 'admin_menu', [ $this, 'add_menu' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $this, 'styles' ] );
		add_action( 'admin_head', [ $this, 'icon_styles' ] );
	}

	/** The pages this plugin owns, in menu order. */
	public function pages() {
		$items = [ self::PAGE_SLUG => __( 'Modules', 'sb-tweaks' ) ];

		if ( function_exists( 'sbtweaks_is_hub' ) && sbtweaks_is_hub() ) {
			$items[ self::PAGE_SLUG . '-publishing' ] = __( 'Publishing', 'sb-tweaks' );
		}

		return $items;
	}

	public function add_menu() {
		add_menu_page(
			esc_html__( 'SocialBUMP Tweaks', 'sb-tweaks' ),
			esc_html__( 'SocialBUMP Tweaks', 'sb-tweaks' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_modules' ],
			$this->menu_icon(),
			58.9
		);

		// The first submenu entry repeats the parent, so name it properly.
		add_submenu_page(
			self::PAGE_SLUG,
			esc_html__( 'Modules', 'sb-tweaks' ),
			esc_html__( 'Modules', 'sb-tweaks' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_modules' ]
		);

		if ( function_exists( 'sbtweaks_is_hub' ) && sbtweaks_is_hub() ) {
			add_submenu_page(
				self::PAGE_SLUG,
				esc_html__( 'Publishing', 'sb-tweaks' ),
				esc_html__( 'Publishing', 'sb-tweaks' ),
				'manage_options',
				self::PAGE_SLUG . '-publishing',
				[ $this, 'render_publishing_page' ]
			);
		}
	}
	/**
	 * The Modules page.
	 *
	 * Empty for now, deliberately: the repo and the publishing route come first,
	 * then the modules are migrated in one at a time.
	 */
	public function render_modules() {
		$q = chr( 34 );

		echo '<div class=' . $q . 'wrap sbtweaks-wrap' . $q . '>';
		$this->render_header( __( 'Modules', 'sb-tweaks' ), __( 'Switch on the parts this site needs. Each one adds its own menu with its own pages.', 'sb-tweaks' ) );

		echo '<section class=' . $q . 'sbtweaks-section' . $q . '><div class=' . $q . 'sbtweaks-section__head' . $q . '><h2>' . esc_html__( 'Modules', 'sb-tweaks' ) . '</h2>';
		echo '<p>' . esc_html__( 'Each one switched on adds its own page to the menu.', 'sb-tweaks' ) . '</p></div>';
		echo '<div class=' . $q . 'sbtweaks-section__body' . $q . '><p>';
		echo esc_html__( 'No modules yet. They are being moved across one at a time, and each one will appear here with its own switch.', 'sb-tweaks' );
		echo '</p></div></section></div>';
	}

	/** Publishing sub page. Only registered on the hub. */
	public function render_publishing_page() {
		$q = chr( 34 );

		echo '<div class=' . $q . 'wrap sbtweaks-wrap' . $q . '>';
		$this->render_header( __( 'Publishing', 'sb-tweaks' ), __( 'Push a new version to GitHub, from here on the hub. Sites pick it up as a normal plugin update.', 'sb-tweaks' ) );
		do_action( 'sbtweaks_settings_after' );
		echo '</div>';
	}
	/**
	 * The row of links under the banner.
	 *
	 * Hidden while there is only one page to go to, which is how a client site
	 * sees it until a module is switched on.
	 */
	private function render_nav() {
		$items = $this->pages();

		if ( count( $items ) < 2 ) {
			return;
		}

		$q     = chr( 34 );
		$page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$notes = count( (array) get_option( 'sbtweaks_pending_changes', [] ) );

		echo '<nav class=' . $q . 'sbtweaks-header__nav' . $q . '>';

		foreach ( $items as $slug => $title ) {
			$badge = '';

			if ( $slug === self::PAGE_SLUG . '-publishing' && $notes > 0 ) {
				$badge = '<span class=' . $q . 'sbtweaks-header__badge' . $q . '>' . esc_html( number_format_i18n( $notes ) ) . '</span>';
			}

			echo '<a class=' . $q . 'sbtweaks-header__link' . ( $slug === $page ? ' is-current' : '' ) . $q . ' href=' . $q . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . $q . '>' . esc_html( $title ) . $badge . '</a>';
		}

		echo '</nav>';
	}
	/**
	 * The dark banner every page opens with.
	 *
	 * The version badge is plain text rather than a link while there is no
	 * Updates page. That page arrives with the first module, because the export
	 * and import live on it.
	 */
	private function render_header( $title, $intro = '' ) {
		$q    = chr( 34 );
		$name = __( 'Tweaks', 'sb-tweaks' );
		$home = esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		$logo = esc_url( SBTWEAKS_URL . 'assets/img/socialbump-logo-light.svg' );

		echo '<div class=' . $q . 'sbtweaks-header' . $q . '><div class=' . $q . 'sbtweaks-header__brand' . $q . '>';
		echo '<a class=' . $q . 'sbtweaks-header__home' . $q . ' href=' . $q . $home . $q . '>';
		echo '<img class=' . $q . 'sbtweaks-header__logo' . $q . ' src=' . $q . $logo . $q . ' alt=' . $q . 'SocialBUMP' . $q . ' width=' . $q . '203' . $q . ' height=' . $q . '28' . $q . '></a>';
		echo '<h1 class=' . $q . 'sbtweaks-header__title' . $q . '>' . esc_html( $name );

		if ( $title !== $name ) {
			echo ' <span class=' . $q . 'sbtweaks-header__page' . $q . '>' . esc_html( $title ) . '</span>';
		}

		echo '</h1>';
		echo '<span class=' . $q . 'sbtweaks-header__version' . $q . '>v' . esc_html( SBTWEAKS_VERSION ) . '</span>';
		echo '</div>';

		if ( $intro !== '' ) {
			echo '<p class=' . $q . 'sbtweaks-header__intro' . $q . '>' . esc_html( $intro ) . '</p>';
		}

		$this->render_nav();

		echo '</div><hr class=' . $q . 'wp-header-end' . $q . '>';
	}
	public function styles( $hook ) {
		if ( strpos( (string) $hook, self::PAGE_SLUG ) === false ) {
			return;
		}

		$css = SBTWEAKS_PATH . 'assets/css/admin.css';
		$ver = file_exists( $css ) ? SBTWEAKS_VERSION . '.' . filemtime( $css ) : SBTWEAKS_VERSION;

		wp_enqueue_style( 'sbtweaks-admin', SBTWEAKS_URL . 'assets/css/admin.css', [], $ver );

		// Match the accent to whichever admin colour scheme the user has chosen.
		wp_add_inline_style( 'sbtweaks-admin', ':root{--sbtweaks-accent:' . $this->accent_colour() . ';}' );

		$save = SBTWEAKS_PATH . 'assets/js/save-state.js';

		if ( file_exists( $save ) ) {
			wp_enqueue_script( 'sbtweaks-save-state', SBTWEAKS_URL . 'assets/js/save-state.js', [], SBTWEAKS_VERSION . '.' . filemtime( $save ), true );
		}
	}

	/**
	 * The SocialBUMP mark, as the menu icon.
	 *
	 * WordPress only recolours Dashicons, which are a font. An SVG given as a
	 * menu icon becomes a background image and keeps whatever colour is baked
	 * into it, so this is white and the dimming is done in CSS. Built by
	 * concatenation with chr( 34 ): a quote mangled in the middle of it produces
	 * markup that silently draws nothing.
	 */
	private function menu_icon() {
		$q = chr( 34 );

		// Tall and narrow, so it is scaled to the height of the box and centred.
		$path = 'M10.94,30.2c1.24,1.24,1.86,2.75,1.86,4.54s-.62,3.3-1.86,4.54-2.75,1.86-4.54,1.86-3.3-.62-4.54-1.86-1.86-2.75-1.86-4.54.62-3.3,1.86-4.54,2.75-1.86,4.54-1.86,3.3.62,4.54,1.86ZM1.22,24.27L.13,1.4C.09.64.7,0,1.46,0h9.88c.76,0,1.37.64,1.34,1.4l-1.09,22.87c-.03.71-.62,1.27-1.34,1.27H2.56c-.71,0-1.3-.56-1.34-1.27Z';

		$svg  = '<svg xmlns=' . $q . 'http://www.w3.org/2000/svg' . $q . ' viewBox=' . $q . '0 0 20 20' . $q . '>';
		$svg .= '<g transform=' . $q . 'translate(7.2 1) scale(0.4376)' . $q . ' fill=' . $q . '#ffffff' . $q . '>';
		$svg .= '<path d=' . $q . $path . $q . '/></g></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/** Make the mark dim and brighten like the Dashicons around it. */
	public function icon_styles() {
		$item = '#adminmenu #toplevel_page_' . self::PAGE_SLUG;

		echo '<style>' . $item . ' div.wp-menu-image.svg{opacity:0.6;transition:opacity .1s ease-in-out;}';
		echo $item . ':hover div.wp-menu-image.svg,' . $item . '.wp-has-current-submenu div.wp-menu-image.svg,' . $item . '.current div.wp-menu-image.svg{opacity:1;}</style>';
	}
	/** How strongly coloured a hex value is, from 0 (grey) to 1. */
	private static function saturation( $hex ) {
		$raw = ltrim( (string) $hex, '#' );

		if ( strlen( $raw ) === 3 ) {
			$raw = $raw[0] . $raw[0] . $raw[1] . $raw[1] . $raw[2] . $raw[2];
		}

		if ( strlen( $raw ) !== 6 ) {
			return 0;
		}

		$rgb = [ hexdec( substr( $raw, 0, 2 ) ), hexdec( substr( $raw, 2, 2 ) ), hexdec( substr( $raw, 4, 2 ) ) ];
		$max = max( $rgb );

		return $max > 0 ? ( $max - min( $rgb ) ) / $max : 0;
	}

	/**
	 * The current admin colour scheme's accent.
	 *
	 * WordPress does not expose this directly: each scheme registers four swatch
	 * colours and the accent is not always in the same slot. The last two are the
	 * candidates, so this takes the more saturated of them, which matches what
	 * the scheme actually paints the current menu item with.
	 */
	private function accent_colour() {
		global $_wp_admin_css_colors;

		$scheme = get_user_option( 'admin_color' );
		$colors = ( $scheme && isset( $_wp_admin_css_colors[ $scheme ]->colors ) ) ? (array) $_wp_admin_css_colors[ $scheme ]->colors : [];
		$colors = array_values(
			array_filter(
				$colors,
				function ( $hex ) {
					return (bool) sanitize_hex_color( $hex );
				}
			)
		);

		// A scheme with a strongly coloured focus colour is naming its accent directly.
		if ( $scheme && ! empty( $_wp_admin_css_colors[ $scheme ]->icon_colors['focus'] ) ) {
			$focus = sanitize_hex_color( $_wp_admin_css_colors[ $scheme ]->icon_colors['focus'] );

			if ( $focus && self::saturation( $focus ) >= 0.6 ) {
				return $focus;
			}
		}

		if ( count( $colors ) < 2 ) {
			return '#2271b1';
		}

		$pair      = array_slice( $colors, -2 );
		$highlight = $pair[0];
		$notice    = $pair[1];

		return self::saturation( $notice ) > self::saturation( $highlight ) + 0.15 ? $notice : $highlight;
	}
}