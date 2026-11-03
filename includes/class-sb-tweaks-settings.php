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
		add_action( 'admin_bar_menu', [ $this, 'admin_bar' ], 100 );
		add_action( 'admin_post_sb_tweaks_save_modules', [ $this, 'save_modules' ] );

		// Drag to reorder and collapse on the Modules page, kept per user.
		SB_Tweaks_Cards::register( SB_TWEAKS_OPTION, self::PAGE_SLUG );
	}

	/** The pages this plugin owns, in menu order. */
	public function pages() {
		$items = [
			self::PAGE_SLUG              => __( 'Modules', 'sb-tweaks' ),
			self::PAGE_SLUG . '-updates' => __( 'Updates', 'sb-tweaks' ),
		];

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

		// Every site: the version, updates, and moving settings between sites.
		add_submenu_page(
			self::PAGE_SLUG,
			esc_html__( 'Updates', 'sb-tweaks' ),
			esc_html__( 'Updates', 'sb-tweaks' ),
			'manage_options',
			self::PAGE_SLUG . '-updates',
			[ $this, 'render_updates_page' ]
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

		$form = 'sb-tweaks-modules-form';

		echo '<form id="' . esc_attr( $form ) . '" method="post" autocomplete="off" data-sb-dirty action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="sb_tweaks_save_modules">';
		wp_nonce_field( 'sb_tweaks_save_modules' );

		// By name until the user drags them; then in their order.
		$titles = [];

		foreach ( $modules as $id => $def ) {
			$titles[ $id ] = $def['title'];
		}

		$ordered = SB_Tweaks_Cards::sort( $titles, SB_TWEAKS_OPTION );
		$failed  = SB_Tweaks_Modules::instance()->failed();
		$shown   = 0;
		$lit     = 0;

		foreach ( $ordered as $id ) {
			if ( ! sb_tweaks_is_hub() && SB_Tweaks_Modules::instance()->missing( $id ) ) {
				continue;
			}

			$shown++;
			$lit += ( SB_Tweaks_Modules::instance()->is_enabled( $id ) && ! SB_Tweaks_Modules::instance()->missing( $id ) && ! isset( $failed[ $id ] ) ) ? 1 : 0;
		}

		// The bar: how many are on with Enable all and Disable all, the
		// collapse buttons, then Save changes. The cards sit below it and drag
		// by their handles (Sam, 1 November 2026).
		echo SB_Tweaks_UI::bar( // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
			[
				[ __( 'Modules', 'sb-tweaks' ), SB_Tweaks_Cards::switches( SB_TWEAKS_OPTION, $lit, $shown ) ],
				[ __( 'Cards', 'sb-tweaks' ), SB_Tweaks_Cards::toolbar( SB_TWEAKS_OPTION ) ],
			],
			SB_Tweaks_UI::save_button( $form )
		);

		echo '<div class="sb-tweaks-grid"' . SB_Tweaks_Cards::container_attributes( SB_TWEAKS_OPTION ) . '>';

		foreach ( $ordered as $id ) {
			/**
			 * On a client site, a module the site cannot run (no Bricks, no
			 * WooCommerce) stays off the page, with nothing said about it.
			 * save_modules() keeps its saved switch as it was, and the card comes
			 * back by itself once the plugin it needs is active. The hub shows
			 * every module, greyed out with what it needs. A module that failed to
			 * load always shows, with its error.
			 */
			if ( ! sb_tweaks_is_hub() && SB_Tweaks_Modules::instance()->missing( $id ) ) {
				continue;
			}

			$this->render_module_card( $id, $modules[ $id ], isset( $failed[ $id ] ) ? $failed[ $id ] : '' );
		}

		echo '</div>';
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

		/**
		 * The module's groups (Sam, 3 November 2026). A module with a Features
		 * page lists them as small switches, saved with the module switches. A
		 * module of pages (Email Signatures, or Image Management under Media)
		 * lists its pages as links.
		 */
		if ( $features ) {
			$q        = chr( 34 );
			$sections = $features->groups();
			$screen   = SB_Tweaks_Modules::instance()->screen( $id );
			$switches = $screen && $screen->parent() === '' && ! $screen->pages_only();
			$states   = ( $screen && ! $switches ) ? $screen->ordered_groups() : $features->group_states();

			if ( $switches ) {
				echo '<input type=' . $q . 'hidden' . $q . ' name=' . $q . 'sb_tweaks_module_groups_shown[' . esc_attr( $id ) . ']' . $q . ' value=' . $q . '1' . $q . '>';
			}

			echo '<ul class=' . $q . 'sb-tweaks-features' . $q . '>';

			foreach ( $states as $group => $group_on ) {
				$title = isset( $sections[ $group ]['title'] ) ? $sections[ $group ]['title'] : $group;
				$has   = (bool) $features->in_group( $group );

				if ( $switches ) {
					// The Features page leaves out a group with nothing in it, and so does this.
					if ( ! $has ) {
						continue;
					}

					$needs = $features->group_needs( $group );
					/* translators: %s: plugin name(s) */
					$tip   = $needs ? sprintf( __( 'Needs %s installed and active.', 'sb-tweaks' ), implode( ' and ', $needs ) ) : '';

					echo SB_Tweaks_Cards::toggle_row( 'sb_tweaks_module_groups[' . $id . '][' . $group . ']', $title, $group_on && ! $needs, (bool) $needs, $tip ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by SB_Tweaks_Cards.

					continue;
				}

				$url = ( $screen && $on && ! $failed && $group_on && $has ) ? $screen->page_url( $screen->group_page_slug( $group ) ) : '';

				echo SB_Tweaks_Cards::link_row( $url, $title, $group_on ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by SB_Tweaks_Cards.
			}

			echo '</ul>';
		}
		if ( $on && ! $failed ) {
			// Its first page: the Features page, or the first page of a module
			// that sits under another menu (Image Management under Media).
			$screen = SB_Tweaks_Modules::instance()->screen( $id );

			if ( $screen ) {
				$open = $screen->page_url( $screen->front_door() );
			} elseif ( ! empty( $def['menu']['parent'] ) && $def['groups'] ) {
				$open = admin_url( $def['menu']['parent'] . '?page=' . $def['menu']['slug'] . '-' . sanitize_key( (string) array_key_first( $def['groups'] ) ) );
			} else {
				$open = admin_url( 'admin.php?page=' . $def['menu']['slug'] );
			}

			echo '<p class="sb-tweaks-card__link"><a href="' . esc_url( $open ) . '">' . esc_html__( 'Open', 'sb-tweaks' ) . '</a></p>';
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
		// A module the hub doesn't allow here isn't on the page: its saved switch stays as it was.
		$states = $saved;

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

		// Group switches from the module cards (Sam, 3 November 2026), only for
		// the modules whose card showed them.
		$shown  = isset( $_POST['sb_tweaks_module_groups_shown'] ) ? (array) wp_unslash( $_POST['sb_tweaks_module_groups_shown'] ) : [];
		$groups = isset( $_POST['sb_tweaks_module_groups'] ) ? (array) wp_unslash( $_POST['sb_tweaks_module_groups'] ) : [];

		foreach ( array_keys( $shown ) as $module ) {
			$features = SB_Tweaks_Modules::instance()->features( sanitize_key( $module ) );

			if ( $features ) {
				SB_Tweaks_Save::store_groups( $features, isset( $groups[ $module ] ) ? (array) $groups[ $module ] : [] );
			}
		}
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

		// Always registered: alone on the bar with no module on, otherwise the
		// last row of the SocialBUMP item. SB_Tweaks_Bar decides which.

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
				'label'           => __( 'SB Tweaks', 'sb-tweaks' ),
				'href'            => admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
				'items'           => $pages,
				'attention'       => $pending,
				'attention_title' => $pending ? __( 'An update is ready.', 'sb-tweaks' ) : '',
				'current'         => $current !== '',
			]
		);
	}

	/** Updates sub page: every site. */
	public function render_updates_page() {
		echo '<div class="wrap sb-tweaks-wrap">';
		$this->render_header( __( 'Updates', 'sb-tweaks' ), __( 'Where this plugin gets its updates, and the settings you can carry across to another site.', 'sb-tweaks' ) );

		if ( class_exists( 'SB_Tweaks_Updates' ) ) {
			SB_Tweaks_Updates::render();
		}

		echo '</div>';
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
	 * The SocialBUMP banner on SocialBUMP Tweaks' own pages
	 * (SB_Tweaks_UI::header()): Tweaks, linked to Modules, the page you're
	 * on, the version badge (to Updates, where the version check and the
	 * settings export and import live) and the pages along the bottom, hidden
	 * while there's only one to go to, which is how a client site sees it
	 * until a module is switched on. Publishing carries how many changes are
	 * waiting to go out.
	 */
	private function render_header( $title, $intro = '' ) {
		$page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$notes = count( (array) get_option( 'sb_tweaks_pending_changes', [] ) );
		$nav   = [];

		foreach ( $this->pages() as $slug => $name ) {
			$nav[] = [ admin_url( 'admin.php?page=' . $slug ), $name, $slug === $page, $slug === self::PAGE_SLUG . '-publishing' ? $notes : 0 ];
		}

		echo SB_Tweaks_UI::header( // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in header().
			[
				'name'     => __( 'Tweaks', 'sb-tweaks' ),
				'name_url' => admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
				'page'     => $title,
				'intro'    => $intro,
				'version'  => [ 'v' . SB_TWEAKS_VERSION, admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-updates' ) ],
				'nav'      => $nav,
			]
		);
	}
	public function styles( $hook ) {
		if ( strpos( (string) $hook, self::PAGE_SLUG ) === false ) {
			return;
		}

		$css = SB_TWEAKS_PATH . 'assets/css/admin.css';
		$ver = file_exists( $css ) ? SB_TWEAKS_VERSION . '.' . filemtime( $css ) : SB_TWEAKS_VERSION;

		wp_enqueue_style( 'sb-tweaks-admin', SB_TWEAKS_URL . 'assets/css/admin.css', [ 'sb-tweaks-colour' ], $ver );

		// The shared SocialBUMP look, dark mode included, on all four pages.
		if ( class_exists( 'SB_Tweaks_UI' ) ) {
			SB_Tweaks_UI::enqueue( true );
		}

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
			wp_enqueue_script( 'sb-tweaks-framework', SB_TWEAKS_URL . 'assets/js/framework.js', [ 'jquery', 'sb-tweaks-colour' ], SB_TWEAKS_VERSION . '.' . filemtime( $js ), true );

			// The progress popup, for anything that takes more than a moment.
			if ( file_exists( SB_TWEAKS_PATH . 'assets/js/progress.js' ) ) {
				wp_enqueue_script( 'sb-tweaks-progress', SB_TWEAKS_URL . 'assets/js/progress.js', [], SB_TWEAKS_VERSION . '.' . filemtime( SB_TWEAKS_PATH . 'assets/js/progress.js' ), true );
			}

			// The confirm popup, for anything that asks before it acts.
			if ( file_exists( SB_TWEAKS_PATH . 'assets/js/confirm.js' ) ) {
				wp_enqueue_script( 'sb-tweaks-confirm', SB_TWEAKS_URL . 'assets/js/confirm.js', [ 'jquery' ], SB_TWEAKS_VERSION . '.' . filemtime( SB_TWEAKS_PATH . 'assets/js/confirm.js' ), true );
			}
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

	/** The admin colour scheme accent, from the framework's shared copy. */
	private function accent_colour() {
		return SB_Tweaks_Screen::accent_colour();
	}
}