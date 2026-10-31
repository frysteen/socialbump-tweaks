<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the module's classes when it is switched on, puts the signature
 * screens (people, settings) under the module's menu in a sensible order, and
 * gives those screens the SocialBUMP banner.
 *
 * module.php is read on every request, switched on or not, so this file only
 * declares the class. Nothing loads until boot().
 */
class SB_EmailSig_Module {

	/** The module's menu slug, which every page address starts with. */
	const SLUG = 'sb-email-signatures';

	/** The Settings page: the module's settings group page. */
	const SETTINGS_SLUG = 'sb-email-signatures-settings';

	/** Which template the site uses. */
	const TEMPLATE_OPTION = 'sb_tweaks_email_signatures_template';

	/** The CPT. */
	const CPT = 'sb_emailsig';

	/** The module's Screen, for the banner. */
	private static $screen = null;

	public static function boot( $context ) {
		if ( ! defined( 'SB_EMAILSIG_CPT' ) ) {
			define( 'SB_EMAILSIG_CPT', self::CPT );
		}

		self::$screen = isset( $context['screen'] ) ? $context['screen'] : null;

		foreach ( [ 'company', 'companies', 'templates', 'styles', 'layout', 'icons', 'settings', 'cpt', 'fields', 'photo', 'logo', 'tracking', 'campaign', 'data', 'render', 'vcard', 'page', 'notify', 'report', 'staff', 'adminmail', 'request', 'person', 'admin', 'transfer', 'log', 'live', 'switch', 'names', 'limits' ] as $part ) {
			require_once SB_EMAILSIG_DIR . 'includes/class-sb-emailsig-' . $part . '.php';
		}

		SB_EmailSig_Company::init();
		SB_EmailSig_Companies::init();
		SB_EmailSig_Templates::init();
		SB_EmailSig_Styles::init();
		SB_EmailSig_Icons::init();
		SB_EmailSig_Settings::init();
		SB_EmailSig_CPT::init();
		SB_EmailSig_Fields::init();
		SB_EmailSig_Photo::init();
		SB_EmailSig_Logo::init();
		SB_EmailSig_Tracking::init();
		SB_EmailSig_Campaign::init();
		SB_EmailSig_VCard::init();
		SB_EmailSig_Page::init();
		SB_EmailSig_Notify::init();
		SB_EmailSig_Staff::init();
		SB_EmailSig_Request::init();
		SB_EmailSig_Log::init();
		SB_EmailSig_Live::init();
		SB_EmailSig_Switch::init();
		SB_EmailSig_Names::init();
		SB_EmailSig_Limits::init();
		SB_EmailSig_Person::init();
		SB_EmailSig_Admin::init();
		SB_EmailSig_Transfer::init();

		add_action( 'admin_menu', [ __CLASS__, 'order_menu' ], 999 );
		add_action( 'load-edit.php', [ __CLASS__, 'list_to_signatures' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'styles' ] );
		add_action( 'all_admin_notices', [ __CLASS__, 'banner' ], 1 );
	}

	/** Version for asset URLs: the plugin's. */
	public static function version() {
		return defined( 'SB_TWEAKS_VERSION' ) ? SB_TWEAKS_VERSION : '1';
	}

	/**
	 * Version for one of the module's own files: the plugin's plus the file's
	 * time, so a changed file is fetched fresh rather than from the browser's
	 * cache. The plugin's version alone stays the same between releases.
	 */
	public static function asset_version( $rel ) {
		$file = SB_EMAILSIG_DIR . ltrim( $rel, '/' );

		return self::version() . ( file_exists( $file ) ? '.' . filemtime( $file ) : '' );
	}

	/**
	 * Signatures, Reports, Templates, Settings, Documentation. The people
	 * are on the Signatures page, with Add person there, so WordPress's
	 * People list and Add New are left out of the menu (Sam, 29 October
	 * 2026). WordPress adds People before the framework adds the module's
	 * pages, so the rest is put in order here too.
	 */
	public static function order_menu() {
		global $submenu;

		if ( empty( $submenu[ self::SLUG ] ) || ! is_array( $submenu[ self::SLUG ] ) ) {
			return;
		}

		$wanted = [
			self::SLUG . '-signatures',
			self::SLUG . '-companies',
			self::SLUG . '-templates',
			self::SLUG . '-reports',
			self::SLUG . '-activity',
			self::SETTINGS_SLUG,
			self::SLUG . '-documentation',
		];
		$hidden = [ 'edit.php?post_type=' . self::CPT, 'post-new.php?post_type=' . self::CPT ];

		$items = array_values(
			array_filter(
				$submenu[ self::SLUG ],
				function ( $item ) use ( $hidden ) {
					return ! in_array( $item[2], $hidden, true );
				}
			)
		);

		usort(
			$items,
			function ( $a, $b ) use ( $wanted ) {
				$ia = array_search( $a[2], $wanted, true );
				$ib = array_search( $b[2], $wanted, true );
				$ia = ( $ia === false ) ? 50 : $ia;
				$ib = ( $ib === false ) ? 50 : $ib;

				return $ia <=> $ib;
			}
		);

		$submenu[ self::SLUG ] = $items; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	}

	/**
	 * WordPress's People list sends people to the Signatures page, where
	 * they are. Its Trash (post_status=trash) still opens, to restore
	 * someone. After a person is moved to the Trash from their edit screen,
	 * the Signatures page says so.
	 */
	public static function list_to_signatures() {
		global $typenow;

		if ( $typenow !== self::CPT || ! empty( $_GET['post_status'] ) || ! empty( $_REQUEST['action'] ) || ! empty( $_REQUEST['action2'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$args = [ 'page' => self::SLUG . '-signatures' ];

		if ( ! empty( $_GET['trashed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$args['trashed'] = absint( $_GET['trashed'] ); // phpcs:ignore WordPress.Security.NonceVerification
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Printed by the Signatures page (SB_EmailSig_Admin::render_list()); ui.js makes it a toast. */
	public static function trashed_notice() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::SLUG . '-signatures' ) {
			return;
		}

		$n = isset( $_GET['trashed'] ) ? absint( $_GET['trashed'] ) : 0;

		if ( $n ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( '%d person moved to Deleted, at the bottom of the page. Restore them from there.', '%d people moved to Deleted, at the bottom of the page. Restore them from there.', $n ), $n ) ) . '</p></div>';
		}

		$back = isset( $_GET['restored'] ) ? absint( $_GET['restored'] ) : 0;

		if ( $back && get_post_type( $back ) === self::CPT ) {
			$on = get_post_status( $back ) === 'publish';
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( get_the_title( $back ) . ' restored to ' . ( $on ? 'Active.' : 'Inactive.' ) ) . '</p></div>';
		}

		if ( ! empty( $_GET['purged'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Deleted for good.</p></div>';
		}
		// phpcs:enable
	}

	/** True on the people list or a person's edit screen: WordPress's screens, not the framework's. */
	public static function is_own_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && $screen->post_type === self::CPT && in_array( $screen->base, [ 'edit', 'post' ], true );
	}

	/**
	 * The framework only styles pages whose hook names the module's slug. The
	 * people screens are WordPress's own, so they get the stylesheet here.
	 */
	public static function styles() {
		if ( ! self::is_own_screen() || ! defined( 'SB_TWEAKS_PATH' ) ) {
			return;
		}

		$css = SB_TWEAKS_PATH . 'assets/css/admin.css';
		$ver = file_exists( $css ) ? self::version() . '.' . filemtime( $css ) : self::version();

		wp_enqueue_style( 'sb-tweaks-admin', SB_TWEAKS_URL . 'assets/css/admin.css', [], $ver );
	}

	/** The SocialBUMP banner above the people screens, like the module's own pages. */
	public static function banner() {
		if ( ! self::$screen || ! self::is_own_screen() ) {
			return;
		}

		$screen = get_current_screen();

		if ( $screen->base === 'post' ) {
			$title = $screen->action === 'add' ? 'Add Person' : 'Edit Person';
		} else {
			$title = 'People';
		}

		echo '<div class="sb-tweaks-wrap sb-emailsig-banner">';
		self::$screen->render_header( $title );
		echo '</div>';
	}
}
