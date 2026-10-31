<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Settings page: the site's own ACF fields (team pages, tracking codes,
 * emails) in a framework page (banner, card, framework Save button) through
 * acf_form(), rather than ACF's own options screen. Stored as ACF options,
 * so get_field( 'sb_emailsig_*', 'option' ) reads them. A company's fields
 * are on its own page (SB_EmailSig_Companies), which shares the assets here.
 */
class SB_EmailSig_Settings {

	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'form_head' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'assets' ] );
		add_action( 'wp_ajax_sb_emailsig_svg', [ __CLASS__, 'svg' ] );
	}

	/** The code of an SVG picked in the Custom SVG field, for the preview strip. */
	public static function svg() {
		check_ajax_referer( 'sb_emailsig_svg', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		$svg = SB_EmailSig_Icons::file_svg( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0 );

		$svg !== '' ? wp_send_json_success( $svg ) : wp_send_json_error();
	}

	/** The saved Custom SVGs, by attachment id, so the preview needn't ask for them. */
	private static function saved_svgs() {
		$out = [];

		foreach ( (array) get_field( 'sb_emailsig_social_media', SB_EmailSig_Company::source() ) as $row ) {
			$id = (int) ( $row['social_media_icon_file'] ?? 0 );

			if ( $id && ( $row['social_media_icon'] ?? '' ) === 'custom' ) {
				$out[ $id ] = SB_EmailSig_Icons::file_svg( $id );
			}
		}

		return $out;
	}

	public static function slug() {
		return SB_EmailSig_Module::SETTINGS_SLUG;
	}

	public static function url() {
		return admin_url( 'admin.php?page=' . self::slug() );
	}

	/** True on the Settings page, including the form posting back to it. */
	public static function is_settings_request() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		return is_admin() && $page === self::slug();
	}

	/** acf_form() saves through acf_form_head(), which has to run before any output. A company's page too. */
	public static function form_head() {
		if ( ( self::is_settings_request() || SB_EmailSig_Companies::is_edit() ) && function_exists( 'acf_form_head' ) ) {
			acf_form_head();
		}
	}

	/**
	 * Settings, and a company's page, where the icons are drawn from its
	 * Social Media rows and brand colours (settings.js finds no rows on
	 * Settings, so draws none there). In the company's context, so its main
	 * template and brand colours are the ones given.
	 */
	public static function assets( $hook ) {
		$co = SB_EmailSig_Companies::editing();

		if ( ! self::is_settings_request() && $co === null ) {
			return;
		}

		SB_EmailSig_Company::enter( (int) $co );

		wp_enqueue_script( 'sb-emailsig-icon-builder', SB_EMAILSIG_URL . 'assets/icon-builder.js', [], SB_EmailSig_Module::asset_version( 'assets/icon-builder.js' ), true );
		wp_enqueue_script( 'sb-emailsig-settings', SB_EMAILSIG_URL . 'assets/settings.js', [ 'jquery', 'acf-input', 'sb-emailsig-icon-builder' ], SB_EmailSig_Module::asset_version( 'assets/settings.js' ), true );
		wp_localize_script(
			'sb-emailsig-settings',
			'sbEmailsigSettings',
			[
				'builtin'   => SB_EmailSig_Icons::builtin_svgs(),
				'templates' => SB_EmailSig_Styles::social_styles(),
				'current'   => SB_EmailSig_Templates::current(),
				'nonce'     => wp_create_nonce( 'sb_emailsig_icons' ),
				'ajax'      => admin_url( 'admin-ajax.php' ),
				'svgNonce'  => wp_create_nonce( 'sb_emailsig_svg' ),
				'svgs'      => (object) self::saved_svgs(),
				// Branding tab: the theme's colours for the swatch menus, and the
				// brand defaults for blank boxes.
				'palette'   => SB_EmailSig_Styles::palette(),
				'colours'   => (object) SB_EmailSig_Styles::colours(),
				'brand'     => (object) wp_list_pluck( SB_EmailSig_Styles::BRAND, 1 ),
				// Company tab, Merge Tag Fields: each post type's fields.
				'staffFields' => (object) array_map( 'array_keys', SB_EmailSig_Staff::field_map() ),
			]
		);

		SB_EmailSig_Company::leave();
	}

	public static function render_page() {
		if ( ! function_exists( 'acf_form' ) ) {
			echo '<p>The settings need ACF Pro.</p>';

			return;
		}

		// Settings saved: the framework's group page shows it (the page this
		// sits in), so not again here.

		echo '<section class="sb-tweaks-section sb-emailsig-settings"><div class="sb-tweaks-section__body">';

		acf_form(
			[
				'id'                    => 'sb-emailsig-settings-form',
				'post_id'               => 'options',
				'field_groups'          => [ 'group_sb_emailsig_settings' ],
				'form_attributes'       => [ 'data-sb-dirty' => '' ],
				'return'                => add_query_arg( 'updated', '1', self::url() ),
				'updated_message'       => false,
				'label_placement'       => 'top',
				'instruction_placement' => 'field',
				'submit_value'          => 'Save changes',
				'html_submit_button'    => '<input type="submit" class="button button-primary sb-save--clean" value="%s" />',
				'html_submit_spinner'   => '<span class="acf-spinner"></span>',
			]
		);

		echo '</div></section>';
	}
}
