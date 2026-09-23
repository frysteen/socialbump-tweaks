<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds and boots the modules: what used to be whole plugins.
 *
 * A module lives in modules/<slug>/ with a module.php returning an array.
 * Nothing else in the plugin needs editing to add one.
 *
 *   'id'          Unique slug in snake_case. It is the option infix, so the
 *                 module reads sb_tweaks_<id>_features, _settings and
 *                 _groups, which is how bricks and site_kit inherit the data
 *                 every site already holds.
 *   'title'       Name on the Modules page, e.g. 'Bricks Tweaks'.
 *   'description' One or two sentences for the Modules page card.
 *   'heading'     Short name in the banner and admin bar. Defaults to title.
 *   'default'     'auto' (the default) switches the module on where its
 *                 sb_tweaks_<id>_features option already exists, so a
 *                 converted site keeps working the moment the module lands,
 *                 and a fresh site starts with it off. true or false forces
 *                 one or the other.
 *   'requires'    Keys of plugins the whole module needs, e.g. [ 'bricks' ].
 *                 A module missing one hides itself entirely.
 *   'groups'      The pages inside the module: slug => title, description,
 *                 optional default false.
 *   'menu'        [ 'slug' => ..., 'label' => ..., 'page_title' => ...,
 *                 'after' => menu slug to sit below ]. The slug defaults to
 *                 sb-<id> with underscores hyphenated; bricks and site_kit
 *                 override it to keep the slugs ASE and bookmarks know.
 *   'bar'         [ 'id' => ..., 'label' => ... ] for the admin bar row.
 *                 Defaults to the menu slug and the heading.
 *   'prompt'      The "starting a chat on this module" prompt for its
 *                 Documentation page. A string or a callable returning one.
 *   'replaces'    The old standalone plugin's basename, e.g.
 *                 'socialbump-bricks-tweaks/socialbump-bricks-tweaks.php'.
 *                 It is deactivated on every admin load while this module
 *                 exists on disk, and so cannot be switched back on: two
 *                 copies reading the same options, registering the same
 *                 Bricks element names and the same menu slugs must never run
 *                 together. Every admin load rather than an activation hook,
 *                 because an activation hook does not fire when a plugin is
 *                 updated.
 *   'boot'        Optional callable( $context ), where $context holds def,
 *                 features (SB_Tweaks_Features) and screen (SB_Tweaks_Screen).
 */
class SB_Tweaks_Modules {

	private static $instance = null;

	/** All discovered module declarations, keyed by id. */
	private $modules = [];

	/** SB_Tweaks_Features per booted module. */
	private $features = [];

	/** SB_Tweaks_Screen per booted module. */
	private $screens = [];

	/** Modules that threw while booting, keyed by id. */
	private $failed = [];

	/** The old plugins deactivated this load, for the notice. */
	private $retired = [];

	/** Plugins that were active when this request started. */
	private $running = [];

	/** Cached result of each dependency check. */
	private static $dependency_state = [];

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot() {
		$this->discover();

		// What was running when this request started. The old standalone plugins
		// are switched off below, but one that was already loaded has run its
		// code for this request, so its module sits this one out.
		$this->running = (array) get_option( 'active_plugins', [] );

		// The old standalone plugins must stop running once their module is here:
		// switched off straight away, front end included, and kept off.
		$this->retire_old_plugins();
		add_filter( 'pre_update_option_active_plugins', [ $this, 'keep_retired_off' ] );
		add_filter( 'plugin_action_links', [ $this, 'retired_action_links' ], 20, 2 );
		add_action( 'admin_notices', [ $this, 'retire_notice' ] );

		// A standalone plugin a module replaces sits in the SocialBUMP admin bar
		// item like its module would (the hub runs them, to publish them).
		add_filter( 'socialbump/admin_bar/claim', [ $this, 'claim_bar_item' ], 10, 2 );

		foreach ( $this->modules as $id => $def ) {
			if ( ! $this->is_enabled( $id ) || $this->replaced_running( $id ) ) {
				continue;
			}

			/**
			 * A broken module should never take the site down with it, so a
			 * fatal while booting one is caught, logged and skipped.
			 */
			try {
				$features = new SB_Tweaks_Features( $def );
				$screen   = new SB_Tweaks_Screen( $def, $features );

				// Registered before booting, so a feature can read its module's
				// settings through sb_tweaks_module() while it starts up.
				$this->features[ $id ] = $features;
				$this->screens[ $id ]  = $screen;

				$features->boot();
				$screen->boot();

				if ( is_callable( $def['boot'] ) ) {
					call_user_func(
						$def['boot'],
						[
							'def'      => $def,
							'features' => $features,
							'screen'   => $screen,
						]
					);
				}
			} catch ( \Throwable $e ) {
				unset( $this->features[ $id ], $this->screens[ $id ] );

				$this->failed[ $id ] = $e->getMessage();

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'SB Tweaks: module ' . $id . ' failed to boot. ' . $e->getMessage() );
				}
			}
		}
	}

	private function discover() {
		foreach ( (array) glob( SB_TWEAKS_PATH . 'modules/*/module.php' ) as $file ) {
			$def = include $file;

			if ( ! is_array( $def ) || empty( $def['id'] ) ) {
				continue;
			}

			$def = array_merge(
				[
					'title'       => $def['id'],
					'description' => '',
					'heading'     => '',
					'default'     => 'auto',
					'requires'    => [],
					'groups'      => [],
					'menu'        => [],
					'bar'         => [],
					'prompt'      => '',
					'replaces'    => '',
					'boot'        => null,
				],
				$def
			);

			if ( $def['heading'] === '' ) {
				$def['heading'] = $def['title'];
			}

			$def['menu'] = array_merge(
				[
					'slug'       => 'sb-' . str_replace( '_', '-', sanitize_key( $def['id'] ) ),
					'label'      => $def['title'],
					'page_title' => 'SocialBUMP ' . $def['title'],
					'after'      => '',
				],
				(array) $def['menu']
			);

			$def['bar'] = array_merge(
				[
					'id'    => $def['menu']['slug'],
					'label' => $def['heading'],
				],
				(array) $def['bar']
			);

			$def['path'] = trailingslashit( dirname( $file ) );
			$def['url']  = SB_TWEAKS_URL . 'modules/' . basename( dirname( $file ) ) . '/';

			$this->modules[ $def['id'] ] = $def;
		}

		uasort(
			$this->modules,
			function ( $a, $b ) {
				return strcasecmp( $a['title'], $b['title'] );
			}
		);
	}

	/** Every module found on disk. */
	public function all() {
		return $this->modules;
	}

	public function failed() {
		return $this->failed;
	}

	/** The booted Features loader for one module, or null. */
	public function features( $id ) {
		return isset( $this->features[ $id ] ) ? $this->features[ $id ] : null;
	}

	/** The booted Screen for one module, or null. */
	public function screen( $id ) {
		return isset( $this->screens[ $id ] ) ? $this->screens[ $id ] : null;
	}

	/**
	 * Saved on/off state, falling back to each module's default.
	 *
	 * 'auto' means on where the module's features option already exists: a
	 * site converted while the plugins were standalone keeps working the
	 * moment its module lands, and a fresh site starts with everything off.
	 */
	public function get_states() {
		$saved  = (array) get_option( SB_TWEAKS_OPTION, [] );
		$states = [];

		foreach ( $this->modules as $id => $def ) {
			if ( array_key_exists( $id, $saved ) ) {
				$states[ $id ] = (bool) $saved[ $id ];
				continue;
			}

			if ( $def['default'] === 'auto' ) {
				$states[ $id ] = get_option( 'sb_tweaks_' . $id . '_features' ) !== false;
				continue;
			}

			$states[ $id ] = (bool) $def['default'];
		}

		return $states;
	}

	public function is_enabled( $id ) {
		if ( ! isset( $this->modules[ $id ] ) || $this->missing( $id ) ) {
			return false;
		}

		$states = $this->get_states();

		return ! empty( $states[ $id ] );
	}

	/** Names of anything this module needs that is missing on this site. */
	public function missing( $id ) {
		if ( empty( $this->modules[ $id ]['requires'] ) ) {
			return [];
		}

		return self::dependency_missing( (array) $this->modules[ $id ]['requires'] );
	}

	/**
	 * Plugins a module or feature can depend on. Filterable, so a module can
	 * add its own or tighten a check.
	 */
	public static function dependencies() {
		return (array) apply_filters(
			'sb_tweaks/dependencies',
			[
				'acf'         => [
					'label'  => 'Advanced Custom Fields',
					'active' => function () {
						if ( ! class_exists( 'ACF' ) ) {
							return false;
						}

						// A copy bundled inside another plugin hides the ACF
						// menu, so it does not count.
						return ! ( defined( 'ACF_PATH' ) && strpos( ACF_PATH, 'bricks-advanced-themer' ) !== false );
					},
				],
				'bricks'      => [
					'label'  => 'Bricks',
					// Bricks is a theme, and themes load after plugins, so while the
					// modules boot BRICKS_VERSION is not defined yet. The active theme
					// (a Bricks child theme included) is known already.
					'active' => function () {
						return defined( 'BRICKS_VERSION' ) || get_template() === 'bricks';
					},
				],
				'woocommerce' => [
					'label'  => 'WooCommerce',
					'active' => function () {
						return class_exists( 'WooCommerce' );
					},
				],
			]
		);
	}

	/**
	 * The labels of whatever in $requires is missing on this site. Shared by
	 * the module level and every Features loader, so one check, one cache.
	 */
	public static function dependency_missing( array $requires ) {
		$dependencies = self::dependencies();
		$missing      = [];

		foreach ( $requires as $key ) {
			if ( ! isset( $dependencies[ $key ] ) ) {
				continue;
			}

			if ( ! isset( self::$dependency_state[ $key ] ) ) {
				self::$dependency_state[ $key ] = (bool) call_user_func( $dependencies[ $key ]['active'] );
			}

			if ( ! self::$dependency_state[ $key ] ) {
				$missing[] = $dependencies[ $key ]['label'];
			}
		}

		return $missing;
	}

	/**
	 * The old standalone plugin a module replaces must never run beside it.
	 *
	 * Both would read and write the same sb_tweaks_<module>_ options, register
	 * the same Bricks element and condition names, and register menus with the
	 * same slugs. So, whenever SocialBUMP Tweaks loads, front end included, the
	 * old plugin is switched off; WordPress's list of active plugins refuses to
	 * take it back (which covers the Activate link, bulk actions, and anything
	 * else that tries); its Activate link is replaced by a note; and the module
	 * itself waits out any request in which the old plugin had already loaded.
	 *
	 * The hub is the exception: it publishes the standalone plugins, and needs
	 * each one running until its last release has gone out. There the old
	 * plugin stays on and its module waits instead.
	 * This applies whenever a module for the plugin is on disk, whether or not
	 * the module is switched on: a module existing is what retires the plugin.
	 */
	public function retire_old_plugins() {
		if ( function_exists( 'sb_tweaks_is_hub' ) && sb_tweaks_is_hub() ) {
			return;
		}

		foreach ( $this->modules as $def ) {
			if ( $def['replaces'] === '' || ! in_array( $def['replaces'], (array) get_option( 'active_plugins', [] ), true ) ) {
				continue;
			}

			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			deactivate_plugins( $def['replaces'] );

			$this->retired[] = $def['title'];
		}

		// Switched off on a front end request: say so on the next admin page.
		if ( $this->retired && ! is_admin() ) {
			set_transient( 'sb_tweaks_retired', $this->retired, DAY_IN_SECONDS );
		}
	}

	/**
	 * Take a standalone plugin's admin bar entry into the SocialBUMP item.
	 *
	 * The standalone plugins register with their shared bar under their folder
	 * name without the socialbump- prefix (bricks-tweaks, site-kit). Any that a
	 * module here replaces is taken off the bar by itself: while its module is
	 * switched on it is registered with SB_Tweaks_Bar, drawn as a row of the
	 * SocialBUMP item with its pages on a flyout; while the module is off it is
	 * not shown at all.
	 */
	public function claim_bar_item( $claimed, $plugin ) {
		if ( $claimed || ! is_array( $plugin ) || empty( $plugin['id'] ) || ! class_exists( 'SB_Tweaks_Bar' ) ) {
			return $claimed;
		}

		foreach ( $this->modules as $id => $def ) {
			if ( $def['replaces'] !== '' && preg_replace( '/^socialbump-/', '', dirname( $def['replaces'] ) ) === $plugin['id'] ) {
				// It follows its module's switch on the Modules page: in the
				// SocialBUMP item while that is on, and nowhere on the bar while it
				// is off, whatever the standalone plugin is doing on the hub.
				if ( $this->is_enabled( $id ) ) {
					SB_Tweaks_Bar::register( array_merge( $plugin, [ 'module' => $id ] ) );
				}

				return true;
			}
		}

		return $claimed;
	}

	/** True when the plugin this module replaces had loaded for this request. */
	public function replaced_running( $id ) {
		$replaces = $this->modules[ $id ]['replaces'] ?? '';

		return $replaces !== '' && in_array( $replaces, $this->running, true );
	}

	/** The basenames every module on disk replaces. */
	private function replaced() {
		return array_values( array_filter( wp_list_pluck( $this->modules, 'replaces' ) ) );
	}

	/** Nothing may add a replaced plugin back to the active list. Not on the hub. */
	public function keep_retired_off( $value ) {
		if ( ! is_array( $value ) || ( function_exists( 'sb_tweaks_is_hub' ) && sb_tweaks_is_hub() ) ) {
			return $value;
		}

		return array_values( array_diff( $value, $this->replaced() ) );
	}

	/** On the Plugins screen, a replaced plugin shows a note instead of Activate. */
	public function retired_action_links( $links, $file ) {
		if ( ! in_array( $file, $this->replaced(), true ) || ( function_exists( 'sb_tweaks_is_hub' ) && sb_tweaks_is_hub() ) ) {
			return $links;
		}

		unset( $links['activate'] );
		$links['sb_tweaks_replaced'] = '<span class="description">' . esc_html__( 'Replaced by SocialBUMP Tweaks', 'sb-tweaks' ) . '</span>';

		return $links;
	}

	/** Say what happened, once, so a switched off plugin is not a mystery. */
	public function retire_notice() {
		$retired = $this->retired;
		$earlier = get_transient( 'sb_tweaks_retired' );

		if ( is_array( $earlier ) ) {
			delete_transient( 'sb_tweaks_retired' );
			$retired = array_unique( array_merge( $retired, $earlier ) );
		}

		if ( $retired ) {
			echo '<div class="notice notice-warning"><p>';
			printf(
				/* translators: %s: module name(s) */
				esc_html__( 'SocialBUMP Tweaks switched off the old standalone plugin for %s: the module here replaces it, and the two must not run together.', 'sb-tweaks' ),
				esc_html( implode( ', ', $retired ) )
			);
			echo '</p></div>';
		}
	}
}
