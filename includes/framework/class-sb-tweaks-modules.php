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

		// The old standalone plugins must stop running once their module is here.
		add_action( 'admin_init', [ $this, 'retire_old_plugins' ] );
		add_action( 'admin_notices', [ $this, 'retire_notice' ] );

		foreach ( $this->modules as $id => $def ) {
			if ( ! $this->is_enabled( $id ) ) {
				continue;
			}

			/**
			 * A broken module should never take the site down with it, so a
			 * fatal while booting one is caught, logged and skipped.
			 */
			try {
				$features = new SB_Tweaks_Features( $def );
				$screen   = new SB_Tweaks_Screen( $def, $features );

				$features->boot();
				$screen->boot();

				$this->features[ $id ] = $features;
				$this->screens[ $id ]  = $screen;

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
					'active' => function () {
						return defined( 'BRICKS_VERSION' );
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
	 * Deactivate the standalone plugin a module replaces, on every admin load.
	 *
	 * The danger is not clashing class names, which cannot collide across
	 * prefixes. Both would read and write the same sb_tweaks_<module>_
	 * options, register the same Bricks element and condition names, and
	 * register menus with the same slugs.
	 */
	public function retire_old_plugins() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( $this->modules as $def ) {
			if ( $def['replaces'] === '' || ! is_plugin_active( $def['replaces'] ) ) {
				continue;
			}

			deactivate_plugins( $def['replaces'] );

			$this->retired[] = $def['title'];
		}
	}

	/** Say what happened, once, so a deactivated plugin is not a mystery. */
	public function retire_notice() {
		if ( ! $this->retired ) {
			return;
		}

		$q = chr( 34 );

		echo '<div class=' . $q . 'notice notice-warning' . $q . '><p>';
		printf(
			/* translators: %s: module name(s) */
			esc_html__( 'SocialBUMP Tweaks switched off the old standalone plugin for %s: the module here replaces it, and the two must not run together.', 'sb-tweaks' ),
			esc_html( implode( ', ', $this->retired ) )
		);
		echo '</p></div>';
	}
}
