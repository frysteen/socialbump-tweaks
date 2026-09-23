<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One module's features: discovery, state, dependency checks and settings.
 *
 * This is the merged SBSK_Modules / SBBT_Modules loader, one level down from
 * the modules themselves. One instance exists per enabled module, built by
 * SB_Tweaks_Modules from the module's declaration, and it reads that module's
 * own option set: sb_tweaks_<id>_features, _settings and _groups, which every
 * site already holds from the standalone renames.
 *
 * The merge took the union of the two copies: always-on features, the
 * unavailable() check in is_enabled() and group_needs(), failed() tracking
 * and multicheck fields from Site Kit; the per feature assets callable, the
 * element type shorthand and the switch field from Bricks Tweaks. Bricks
 * element registration did not come across: it is the one genuinely Bricks
 * only piece, so it lives in the Bricks module itself.
 *
 * A feature lives in <module>/features/<slug>/feature.php returning an array,
 * the same shape module.php returned in the standalone plugins:
 *
 *   'id'          Unique slug, also the key its on/off state is saved under.
 *   'title'       Name on the settings pages.
 *   'description' One or two sentences.
 *   'section'     Which group it appears in. Defaults from 'type': an element
 *                 lands in elements, everything else in extras.
 *   'default'     Whether it is on when a site first installs the plugin.
 *   'always'      true for features with no switch, loaded on every site.
 *   'requires'    Keys of plugins it needs, e.g. [ 'acf' ].
 *   'unavailable' Optional callable returning a sentence when the feature
 *                 cannot be used on this site. Never call is_enabled() inside
 *                 one: is_enabled() asks unavailable(), so that goes round in
 *                 a circle. Read get_states() instead.
 *   'boot'        callable( $feature ). Runs when the feature loads.
 *   'assets'      Optional callable( $feature ), called on both enqueue hooks.
 *   'settings'    Optional small options shown on the feature's card.
 *   'admin_page'  Optional [ 'title' => '', 'render' => callable ] sub page.
 *   'features'    Optional callable listing the feature's own switches for
 *                 the dots on the Features page.
 *   'wide'        Full width card, drawn after the others in its group.
 */
class SB_Tweaks_Features {

	/** The module's declaration, as module.php returned it. */
	private $def;

	/** All discovered features, keyed by id. */
	private $modules = [];

	/** Features that are switched on and have booted, keyed by id. */
	private $active = [];

	/** Features that threw while loading, keyed by id. */
	private $failed = [];

	public function __construct( array $def ) {
		$this->def = $def;
	}

	public function module_id() {
		return $this->def['id'];
	}

	public function features_option() {
		return 'sb_tweaks_' . $this->def['id'] . '_features';
	}

	public function settings_option() {
		return 'sb_tweaks_' . $this->def['id'] . '_settings';
	}

	public function groups_option() {
		return 'sb_tweaks_' . $this->def['id'] . '_groups';
	}

	/** The module's groups, filterable per module. */
	public function groups() {
		return (array) apply_filters( 'sb_tweaks/' . $this->def['id'] . '/groups', (array) $this->def['groups'] );
	}

	public function boot() {
		$this->discover();

		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ], 5 );
		add_action( 'admin_enqueue_scripts', [ $this, 'register_assets' ], 5 );

		foreach ( $this->modules as $id => $feature ) {
			if ( ! $this->is_enabled( $id ) ) {
				continue;
			}

			$this->active[ $id ] = $feature;

			if ( ! is_callable( $feature['boot'] ) ) {
				continue;
			}

			/**
			 * A broken feature should never take the site down with it, so a
			 * fatal inside one is caught, logged and skipped. Everything else
			 * carries on.
			 */
			try {
				call_user_func( $feature['boot'], $feature );
			} catch ( \Throwable $e ) {
				unset( $this->active[ $id ] );

				$this->failed[ $id ] = $e->getMessage();

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'SB Tweaks: ' . $this->def['id'] . ' feature ' . $id . ' failed to load. ' . $e->getMessage() );
				}
			}
		}
	}

	private function discover() {
		foreach ( (array) glob( $this->def['path'] . 'features/*/feature.php' ) as $file ) {
			$feature = include $file;

			if ( ! is_array( $feature ) || empty( $feature['id'] ) ) {
				continue;
			}

			$feature = array_merge(
				[
					'title'       => $feature['id'],
					'description' => '',
					'type'        => '',
					'section'     => '',
					'default'     => false,
					'always'      => false,
					'requires'    => [],
					'unavailable' => null,
					'settings'    => [],
					'admin_page'  => null,
					'boot'        => null,
					'assets'      => null,
					'features'    => null,
					'wide'        => false,
				],
				$feature
			);

			// The Bricks Tweaks shorthand: an element goes to the elements group.
			if ( $feature['section'] === '' ) {
				$feature['section'] = $feature['type'] === 'element' ? 'elements' : 'extras';
			}

			$feature['path'] = trailingslashit( dirname( $file ) );
			$feature['url']  = $this->def['url'] . 'features/' . basename( dirname( $file ) ) . '/';

			$this->modules[ $feature['id'] ] = $feature;
		}

		uasort(
			$this->modules,
			function ( $a, $b ) {
				return strcasecmp( $a['title'], $b['title'] );
			}
		);
	}

	public function all() {
		return $this->modules;
	}

	/** Features with a switch, so the ones a whole page save covers. */
	public function switchable() {
		return array_filter(
			$this->modules,
			function ( $feature ) {
				return empty( $feature['always'] );
			}
		);
	}

	/** Features that are switched on and have booted. */
	public function enabled() {
		return $this->active;
	}

	public function failed() {
		return $this->failed;
	}

	public function get_states() {
		$saved  = (array) get_option( $this->features_option(), [] );
		$states = [];

		foreach ( $this->modules as $id => $feature ) {
			$states[ $id ] = array_key_exists( $id, $saved ) ? (bool) $saved[ $id ] : (bool) $feature['default'];
		}

		return $states;
	}

	public function is_enabled( $id ) {
		if ( ! isset( $this->modules[ $id ] ) ) {
			return false;
		}

		if ( ! $this->group_enabled( $this->modules[ $id ]['section'] ) ) {
			return false;
		}

		if ( $this->missing( $id ) || $this->unavailable( $id ) ) {
			return false;
		}

		// Always-on features have no switch.
		if ( ! empty( $this->modules[ $id ]['always'] ) ) {
			return true;
		}

		$states = $this->get_states();

		return ! empty( $states[ $id ] );
	}

	/**
	 * Which groups are switched on.
	 *
	 * A group is on until it is switched off, unless its declaration says
	 * otherwise, and a group whose every feature is held back is off and
	 * cannot be turned on.
	 */
	public function group_states() {
		$saved  = (array) get_option( $this->groups_option(), [] );
		$states = [];

		foreach ( $this->groups() as $group => $section ) {
			$default = ! ( isset( $section['default'] ) && $section['default'] === false );

			$states[ $group ] = $this->group_needs( $group ) ? false : ( array_key_exists( $group, $saved ) ? (bool) $saved[ $group ] : $default );
		}

		return $states;
	}

	public function group_enabled( $group ) {
		$states = $this->group_states();

		return $group === '' || ! isset( $states[ $group ] ) || $states[ $group ];
	}

	/**
	 * The features that belong to one group, including always-on ones: a
	 * feature with no switch can still have settings, and they have to appear
	 * somewhere.
	 */
	public function in_group( $group ) {
		return array_filter(
			$this->modules,
			function ( $feature ) use ( $group ) {
				return $feature['section'] === $group;
			}
		);
	}

	/**
	 * What a whole group needs, when not one thing in it can run on this site.
	 *
	 * A single usable feature is enough for the group to be worth having, so
	 * this only returns something when every feature in it is held back. A
	 * group in that state counts as off, whatever its saved switch says, so
	 * its page stays away and nothing inside it loads.
	 */
	public function group_needs( $group ) {
		$features = $this->in_group( $group );

		if ( ! $features ) {
			return [];
		}

		$needs = [];

		foreach ( $features as $id => $feature ) {
			$missing = $this->missing( $id );

			if ( ! $missing && ! $this->unavailable( $id ) ) {
				return [];
			}

			$needs = array_merge( $needs, $missing );
		}

		return array_values( array_unique( $needs ) );
	}

	/** Names of anything this feature needs that is missing on this site. */
	public function missing( $id ) {
		if ( empty( $this->modules[ $id ]['requires'] ) ) {
			return [];
		}

		return SB_Tweaks_Modules::dependency_missing( (array) $this->modules[ $id ]['requires'] );
	}

	/** Why this feature cannot be used here, or '' when it can. */
	public function unavailable( $id ) {
		if ( empty( $this->modules[ $id ]['unavailable'] ) || ! is_callable( $this->modules[ $id ]['unavailable'] ) ) {
			return '';
		}

		return (string) call_user_func( $this->modules[ $id ]['unavailable'] );
	}

	/** A feature's card setting: the saved value, or its default. */
	public function setting( $id, $key ) {
		$saved = (array) get_option( $this->settings_option(), [] );

		if ( isset( $saved[ $id ] ) && is_array( $saved[ $id ] ) && array_key_exists( $key, $saved[ $id ] ) ) {
			return $saved[ $id ][ $key ];
		}

		return isset( $this->modules[ $id ]['settings'][ $key ]['default'] ) ? $this->modules[ $id ]['settings'][ $key ]['default'] : null;
	}

	/** Let each enabled feature register its own scripts and styles. */
	public function register_assets() {
		foreach ( $this->active as $feature ) {
			if ( empty( $feature['assets'] ) || ! is_callable( $feature['assets'] ) ) {
				continue;
			}

			call_user_func( $feature['assets'], $feature );
		}
	}
}
