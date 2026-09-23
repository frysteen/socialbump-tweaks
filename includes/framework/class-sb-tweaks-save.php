<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one save handler behind every module's Features and group pages.
 *
 * Built into the framework once, because the partial save bug has now been
 * written into three separate plugins: a group page submits only its own
 * features, so the save must start from what is already stored and touch only
 * the features on that page. Both rules live here and nowhere else.
 *
 * A form posts sb_tweaks_module naming its module, and sb_tweaks_group when it
 * is a group page, so the save knows which option set to touch and where to
 * send the browser back to.
 */
class SB_Tweaks_Save {

	public static function boot() {
		add_action( 'admin_post_sb_tweaks_save', [ __CLASS__, 'save' ] );
		add_action( 'admin_post_sb_tweaks_save_groups', [ __CLASS__, 'save_groups' ] );
	}

	/** The module a posted form belongs to: [ features, screen ], or null. */
	private static function posted_module() {
		$id = isset( $_POST['sb_tweaks_module'] ) ? sanitize_key( wp_unslash( $_POST['sb_tweaks_module'] ) ) : '';

		if ( $id === '' ) {
			return null;
		}

		$features = SB_Tweaks_Modules::instance()->features( $id );
		$screen   = SB_Tweaks_Modules::instance()->screen( $id );

		return ( $features && $screen ) ? [ $features, $screen ] : null;
	}

	/** Feature switches and card settings from a Features or group page. */
	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'sb-tweaks' ) );
		}

		check_admin_referer( 'sb_tweaks_save' );

		$module = self::posted_module();

		if ( ! $module ) {
			wp_die( esc_html__( 'Unknown module.', 'sb-tweaks' ) );
		}

		list( $features, $screen ) = $module;

		$group     = isset( $_POST['sb_tweaks_group'] ) ? sanitize_key( wp_unslash( $_POST['sb_tweaks_group'] ) ) : '';
		$list      = $group !== '' ? $features->in_group( $group ) : $features->switchable();
		$saved     = (array) get_option( $features->features_option(), [] );
		$submitted = isset( $_POST['sb_tweaks_features'] ) ? (array) wp_unslash( $_POST['sb_tweaks_features'] ) : [];

		// One group's page only submits its own features, so start from
		// everything already saved. Otherwise saving one page would wipe every
		// other group.
		$states = $saved;

		foreach ( $list as $id => $feature ) {
			// A feature with no switch has no state to save; its settings still do.
			if ( ! empty( $feature['always'] ) ) {
				continue;
			}

			// A greyed out feature can't be changed here, so keep what it had.
			if ( $features->missing( $id ) || $features->unavailable( $id ) ) {
				$states[ $id ] = array_key_exists( $id, $saved ) ? (int) (bool) $saved[ $id ] : (int) (bool) $feature['default'];
				continue;
			}

			$states[ $id ] = ! empty( $submitted[ $id ] ) ? 1 : 0;
		}

		update_option( $features->features_option(), $states );

		// Card settings.
		$posted_settings = isset( $_POST['sb_tweaks_settings'] ) ? (array) wp_unslash( $_POST['sb_tweaks_settings'] ) : [];
		$saved_settings  = (array) get_option( $features->settings_option(), [] );

		foreach ( $list as $id => $feature ) {
			// Greyed out features don't show their settings, so keep what they had.
			if ( empty( $feature['settings'] ) || $features->missing( $id ) ) {
				continue;
			}

			foreach ( $feature['settings'] as $key => $field ) {
				$raw = isset( $posted_settings[ $id ][ $key ] ) ? $posted_settings[ $id ][ $key ] : null;

				$saved_settings[ $id ][ $key ] = SB_Tweaks_Fields::sanitize( $field, $raw );
			}
		}

		update_option( $features->settings_option(), $saved_settings );

		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => $group !== '' ? $screen->group_page_slug( $group ) : $screen->slug(),
					'updated' => 'true',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/** The group switches from a module's Features page. */
	public static function save_groups() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'sb-tweaks' ) );
		}

		check_admin_referer( 'sb_tweaks_save_groups' );

		$module = self::posted_module();

		if ( ! $module ) {
			wp_die( esc_html__( 'Unknown module.', 'sb-tweaks' ) );
		}

		list( $features, $screen ) = $module;

		$posted = isset( $_POST['sb_tweaks_groups'] ) ? (array) wp_unslash( $_POST['sb_tweaks_groups'] ) : [];
		$saved  = (array) get_option( $features->groups_option(), [] );
		$states = [];

		foreach ( array_keys( $features->groups() ) as $group ) {
			// A greyed out group has no switch to submit, so keep whatever it was set to.
			if ( $features->group_needs( $group ) ) {
				$states[ $group ] = array_key_exists( $group, $saved ) ? (int) (bool) $saved[ $group ] : 1;
				continue;
			}

			$states[ $group ] = empty( $posted[ $group ] ) ? 0 : 1;
		}

		update_option( $features->groups_option(), $states );

		wp_safe_redirect( add_query_arg( [ 'page' => $screen->slug(), 'updated' => 'true' ], admin_url( 'admin.php' ) ) );
		exit;
	}
}
