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
class SB_Tweaks_Settings {

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
		add_action( 'admin_bar_menu', [ $this, 'admin_bar' ], 100 );
		add_action( 'admin_post_sb_tweaks_save_modules', [ $this, 'save_modules' ] );

		// Drag to reorder and collapse on the Modules page, kept per user.
		SB_Tweaks_Cards::register( SB_TWEAKS_OPTION, self::PAGE_SLUG );
	}

	/** The pages this plugin owns, in menu order. */
	public function pages() {
		$items = [ self::PAGE_SLUG => __( 'Modules', 'sb-tweaks' ) ];

		if ( function_exists( 'sb_tweaks_is_hub' ) && sb_tweaks_is_hub() ) {
			$items[ self::PAGE_SLUG . '-installs' ]   = __( 'Installs', 'sb-tweaks' );
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

		if ( function_exists( 'sb_tweaks_is_hub' ) && sb_tweaks_is_hub() ) {
			add_submenu_page(
				self::PAGE_SLUG,
				esc_html__( 'Installs', 'sb-tweaks' ),
				esc_html__( 'Installs', 'sb-tweaks' ),
				'manage_options',
				self::PAGE_SLUG . '-installs',
				[ $this, 'render_installs_page' ]
			);

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
	 * The Modules page: one switch per module.
	 *
	 * A module switched on registers its own top level menu with its own pages,
	 * the way each of these was a plugin of its own before. The dots on each
	 * card are the module's groups, lit while the module is running and the
	 * group is on.
	 */
	public function render_modules() {
		echo '<div class="wrap sb-tweaks-wrap">';
		$this->render_header( __( 'Modules', 'sb-tweaks' ), __( 'Switch on the parts this site needs. Each one adds its own menu with its own pages.', 'sb-tweaks' ) );

		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'sb-tweaks' ) . '</p></div>';
		}

		$modules = class_exists( 'SB_Tweaks_Modules' ) ? SB_Tweaks_Modules::instance()->all() : [];

		if ( ! $modules ) {
			echo '<section class="sb-tweaks-section"><div class="sb-tweaks-section__head"><h2>' . esc_html__( 'Modules', 'sb-tweaks' ) . '</h2>';
			echo '<p>' . esc_html__( 'Each one switched on adds its own menu with its own pages.', 'sb-tweaks' ) . '</p></div>';
			echo '<div class="sb-tweaks-section__body"><p>';
			echo esc_html__( 'No modules yet. They are being moved across one at a time, and each one will appear here with its own switch.', 'sb-tweaks' );
			echo '</p></div></section></div>';

			return;
		}

		echo '<form method="post" autocomplete="off" data-sb-dirty action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="sb_tweaks_save_modules">';
		wp_nonce_field( 'sb_tweaks_save_modules' );

		// Reorder sits up here with the heading, away from Save changes.
		echo '<section class="sb-tweaks-section"><div class="sb-tweaks-section__head sb-tweaks-section__head--tools"><div><h2>' . esc_html__( 'Modules', 'sb-tweaks' ) . '</h2><p>' . esc_html__( 'Each one switched on adds its own menu with its own pages. Reorder puts them in the order you want, and each one collapses to its title.', 'sb-tweaks' ) . '</p></div>';
		echo SB_Tweaks_Cards::toolbar( SB_TWEAKS_OPTION, 'reorder' );
		echo '</div>';

		// By name until the user drags them; then in their order.
		$titles = [];

		foreach ( $modules as $id => $def ) {
			$titles[ $id ] = $def['title'];
		}

		$ordered = SB_Tweaks_Cards::sort( $titles, SB_TWEAKS_OPTION );
		$failed  = SB_Tweaks_Modules::instance()->failed();

		echo SB_Tweaks_Cards::toolbar( SB_TWEAKS_OPTION, 'links' );
		echo '<div class="sb-tweaks-grid"' . SB_Tweaks_Cards::container_attributes( SB_TWEAKS_OPTION ) . '>';

		foreach ( $ordered as $id ) {
			$this->render_module_card( $id, $modules[ $id ], isset( $failed[ $id ] ) ? $failed[ $id ] : '' );
		}

		echo '</div></section>';
		submit_button( esc_html__( 'Save changes', 'sb-tweaks' ), 'primary sb-save--clean' );
		echo '</form></div>';
	}

	/** One module's card: its switch, its notes and the state of its groups. */
	private function render_module_card( $id, $def, $failed = '' ) {
		$missing  = SB_Tweaks_Modules::instance()->missing( $id );
		$on       = SB_Tweaks_Modules::instance()->is_enabled( $id );
		$features = SB_Tweaks_Modules::instance()->features( $id );

		echo '<div class="sb-tweaks-card' . ( $on && ! $missing && ! $failed ? ' is-on' : '' ) . ( ( $missing || $failed ) ? ' is-unavailable' : '' ) . '"' . SB_Tweaks_Cards::card_attribute( $id ) . '>';
		echo '<div class="sb-tweaks-card__head"><h3>' . esc_html( $def['title'] ) . '</h3>';
		echo '<label class="sb-tweaks-switch"><input type="checkbox" name="sb_tweaks_module_states[' . esc_attr( $id ) . ']" value="1" ' . checked( $on, true, false ) . ' ' . disabled( (bool) $missing, true, false ) . '>';
		echo '<span class="sb-tweaks-switch__track"><span class="sb-tweaks-switch__dot"></span></span>';
		echo '<span class="screen-reader-text">' . esc_html( $def['title'] ) . '</span></label></div>';
		echo '<p class="sb-tweaks-card__desc">' . esc_html( $def['description'] ) . '</p>';

		if ( $missing ) {
			/* translators: %s: plugin name(s) */
			echo '<p class="sb-tweaks-card__needs">' . sprintf( esc_html__( 'Needs %s installed and active.', 'sb-tweaks' ), esc_html( implode( ' and ', $missing ) ) ) . '</p>';
		}

		if ( $failed ) {
			/* translators: %s: error message */
			echo '<p class="sb-tweaks-card__needs">' . sprintf( esc_html__( 'Failed to load: %s', 'sb-tweaks' ), esc_html( $failed ) ) . '</p>';
		}

		// The module's groups, lit while the module is running and the group is on.
		if ( $features ) {
			$sections = $features->groups();

			echo '<ul class="sb-tweaks-features">';

			foreach ( $features->group_states() as $group => $group_on ) {
				echo '<li class="' . ( $group_on ? 'is-on' : 'is-off' ) . '"><span class="sb-tweaks-dot"></span>' . esc_html( isset( $sections[ $group ]['title'] ) ? $sections[ $group ]['title'] : $group ) . '</li>';
			}

			echo '</ul>';
		}

		if ( $on && ! $failed ) {
			echo '<p class="sb-tweaks-card__link"><a href="' . esc_url( admin_url( 'admin.php?page=' . $def['menu']['slug'] ) ) . '">' . esc_html__( 'Open', 'sb-tweaks' ) . '</a></p>';
		}

		echo '</div>';
	}

	/** Save which modules are on, from the Modules page. */
	public function save_modules() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'sb-tweaks' ) );
		}

		check_admin_referer( 'sb_tweaks_save_modules' );

		$posted = isset( $_POST['sb_tweaks_module_states'] ) ? (array) wp_unslash( $_POST['sb_tweaks_module_states'] ) : [];
		$saved  = (array) get_option( SB_TWEAKS_OPTION, [] );
		$states = [];

		foreach ( SB_Tweaks_Modules::instance()->all() as $id => $def ) {
			/**
			 * A switch disabled for a missing dependency does not post, and
			 * writing it off behind someone's back would lose their choice, so
			 * the saved state stands until the module can actually run. With
			 * nothing saved, nothing is written and the default keeps deciding.
			 */
			if ( SB_Tweaks_Modules::instance()->missing( $id ) ) {
				if ( isset( $saved[ $id ] ) ) {
					$states[ $id ] = (bool) $saved[ $id ];
				}

				continue;
			}

			$states[ $id ] = ! empty( $posted[ $id ] );
		}

		update_option( SB_TWEAKS_OPTION, $states );

		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => self::PAGE_SLUG,
					'updated' => 'true',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * The plugin's own row for the shared SocialBUMP menu in the admin bar.
	 *
	 * With no modules on it stands alone as Tweaks; with exactly one on, that
	 * module's menu stands alone instead and this stays out of the way; with
	 * more than one, everything shares the combined SocialBUMP menu and this
	 * is the Tweaks row inside it.
	 */
	public function admin_bar() {
		if ( ! current_user_can( 'manage_options' ) || ! class_exists( 'SB_Tweaks_Bar' ) ) {
			return;
		}

		$enabled = 0;

		if ( class_exists( 'SB_Tweaks_Modules' ) ) {
			foreach ( SB_Tweaks_Modules::instance()->all() as $id => $def ) {
				if ( SB_Tweaks_Modules::instance()->is_enabled( $id ) ) {
					$enabled++;
				}
			}
		}

		if ( $enabled === 1 ) {
			return;
		}

		$items   = $this->pages();
		$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$current = isset( $items[ $page ] ) ? $page : '';
		$notes   = count( (array) get_option( 'sb_tweaks_pending_changes', [] ) );

		$state   = get_site_transient( 'update_plugins' );
		$file    = plugin_basename( SB_TWEAKS_FILE );
		$pending = $state && ! empty( $state->response[ $file ]->new_version );

		$pages = [];

		foreach ( $items as $slug => $title ) {
			$row = [
				'title'   => $title,
				'href'    => admin_url( 'admin.php?page=' . $slug ),
				'current' => $slug === $current,
			];

			if ( $slug === self::PAGE_SLUG . '-publishing' && $notes > 0 ) {
				$row['count'] = $notes;
			}

			$pages[] = $row;
		}

		SB_Tweaks_Bar::register(
			[
				'id'              => 'tweaks',
				'label'           => __( 'Tweaks', 'sb-tweaks' ),
				'href'            => admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
				'items'           => $pages,
				'attention'       => $pending,
				'attention_title' => $pending ? __( 'An update is ready.', 'sb-tweaks' ) : '',
				'current'         => $current !== '',
			]
		);
	}

	/** Installs sub page: which SocialBUMP plugins are on which sites. Hub only. */
	public function render_installs_page() {
		echo '<div class="wrap sb-tweaks-wrap">';
		$this->render_header( __( 'Installs', 'sb-tweaks' ), __( 'Which SocialBUMP plugins are installed on which sites, their versions, and whether they are active.', 'sb-tweaks' ) );

		if ( class_exists( 'SB_Tweaks_Installs' ) ) {
			SB_Tweaks_Installs::render();
		}

		echo '</div>';
	}

	/** Publishing sub page. Only registered on the hub. */
	public function render_publishing_page() {
		$q = chr( 34 );

		echo '<div class=' . $q . 'wrap sb-tweaks-wrap' . $q . '>';
		$this->render_header( __( 'Publishing', 'sb-tweaks' ), __( 'Push a new version to GitHub, from here on the hub. Sites pick it up as a normal plugin update.', 'sb-tweaks' ) );
		do_action( 'sb_tweaks_settings_after' );
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
		$notes = count( (array) get_option( 'sb_tweaks_pending_changes', [] ) );

		echo '<nav class=' . $q . 'sb-tweaks-header__nav' . $q . '>';

		foreach ( $items as $slug => $title ) {
			$badge = '';

			if ( $slug === self::PAGE_SLUG . '-publishing' && $notes > 0 ) {
				$badge = '<span class=' . $q . 'sb-tweaks-header__badge' . $q . '>' . esc_html( number_format_i18n( $notes ) ) . '</span>';
			}

			echo '<a class=' . $q . 'sb-tweaks-header__link' . ( $slug === $page ? ' is-current' : '' ) . $q . ' href=' . $q . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . $q . '>' . esc_html( $title ) . $badge . '</a>';
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
		$logo = esc_url( SB_TWEAKS_URL . 'assets/img/socialbump-logo-light.svg' );

		echo '<div class=' . $q . 'sb-tweaks-header' . $q . '><div class=' . $q . 'sb-tweaks-header__brand' . $q . '>';
		echo '<a class=' . $q . 'sb-tweaks-header__home' . $q . ' href=' . $q . $home . $q . '>';
		echo '<img class=' . $q . 'sb-tweaks-header__logo' . $q . ' src=' . $q . $logo . $q . ' alt=' . $q . 'SocialBUMP' . $q . ' width=' . $q . '203' . $q . ' height=' . $q . '28' . $q . '></a>';
		echo '<h1 class=' . $q . 'sb-tweaks-header__title' . $q . '>' . esc_html( $name );

		if ( $title !== $name ) {
			echo ' <span class=' . $q . 'sb-tweaks-header__page' . $q . '>' . esc_html( $title ) . '</span>';
		}

		echo '</h1>';
		echo '<span class=' . $q . 'sb-tweaks-header__version' . $q . '>v' . esc_html( SB_TWEAKS_VERSION ) . '</span>';
		echo '</div>';

		if ( $intro !== '' ) {
			echo '<p class=' . $q . 'sb-tweaks-header__intro' . $q . '>' . esc_html( $intro ) . '</p>';
		}

		$this->render_nav();

		echo '</div><hr class=' . $q . 'wp-header-end' . $q . '>';
	}
	public function styles( $hook ) {
		if ( strpos( (string) $hook, self::PAGE_SLUG ) === false ) {
			return;
		}

		$css = SB_TWEAKS_PATH . 'assets/css/admin.css';
		$ver = file_exists( $css ) ? SB_TWEAKS_VERSION . '.' . filemtime( $css ) : SB_TWEAKS_VERSION;

		wp_enqueue_style( 'sb-tweaks-admin', SB_TWEAKS_URL . 'assets/css/admin.css', [], $ver );

		// Match the accent to whichever admin colour scheme the user has chosen.
		wp_add_inline_style( 'sb-tweaks-admin', ':root{--sb-tweaks-accent:' . $this->accent_colour() . ';}' );

		$save = SB_TWEAKS_PATH . 'assets/js/save-state.js';

		if ( file_exists( $save ) ) {
			wp_enqueue_script( 'sb-tweaks-save-state', SB_TWEAKS_URL . 'assets/js/save-state.js', [], SB_TWEAKS_VERSION . '.' . filemtime( $save ), true );
		}

		// Card switch styling and the select all and none links, shared with
		// the module screens.
		$js = SB_TWEAKS_PATH . 'assets/js/framework.js';

		if ( file_exists( $js ) ) {
			wp_enqueue_script( 'sb-tweaks-framework', SB_TWEAKS_URL . 'assets/js/framework.js', [ 'jquery' ], SB_TWEAKS_VERSION . '.' . filemtime( $js ), true );
		}

		// The cards that drag to reorder and collapse.
		$cards = SB_TWEAKS_PATH . 'assets/js/module-cards.js';

		if ( file_exists( $cards ) ) {
			wp_enqueue_script( 'sb-module-cards', SB_TWEAKS_URL . 'assets/js/module-cards.js', [], SB_TWEAKS_VERSION . '.' . filemtime( $cards ), true );
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

	/** The admin colour scheme accent, from the framework's shared copy. */
	private function accent_colour() {
		return SB_Tweaks_Screen::accent_colour();
	}
}