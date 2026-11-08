<?php
/**
 * Access (core): limited user roles that modules declare.
 *
 * A module ships modules/<module>/access.php returning an array of roles,
 * keyed by role slug. Core reads every module's file, creates a role while
 * its module is on, and when the module is off (or the plugin deactivated)
 * turns that role's users into Subscribers and removes the role.
 *
 * For each role core then enforces:
 * - Pages: only the admin pages named in 'pages', Media (if 'media'), the
 *   Profile page and the post types in 'types' and 'edit_only'. Anything
 *   else redirects to 'home'. The side menu keeps only those, the top bar
 *   only the account menu, and the front of the site shows no bar.
 * - Permissions: the role gets ordinary post and page rights, then every
 *   check on a post is narrowed to the declared post types. 'edit_only'
 *   types can be added and edited but never deleted. Media 'own': use any
 *   file, edit or delete only your own.
 * - 'admin_caps': inside the module's pages and its ajax and admin-post
 *   actions ('actions' prefixes), checks for these (manage_options, say)
 *   pass, so the module's own pages work as they do for an admin.
 * - 'admin_only_fields': ACF field keys an administrator can see and change
 *   but this role can't.
 *
 * Declaration keys (all optional but 'label' and 'home'):
 *   'label'             Role name shown in WordPress.
 *   'module'            Module id that must be on (SB_Tweaks_Modules).
 *   'home'              admin.php?page= slug they land on.
 *   'pages'             admin.php?page= slug prefixes they can open.
 *   'actions'           ajax and admin-post action prefixes for the module.
 *   'admin_caps'        Caps passed inside the module, e.g. manage_options.
 *   'types'             Post types with full rights (array or callable).
 *   'edit_only'         Post types with every right but delete (array or callable).
 *   'media'             'own' (use all, change own), 'all', or false.
 *   'admin_only_fields' ACF field keys kept for administrators.
 *
 * Sam, 5 November 2026. Signatures Manager (Email Signatures) is the first.
 */

defined( 'ABSPATH' ) || exit;

class SB_Tweaks_Access {

	private static $roles = null;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'sync_roles' ), 99 );
		add_filter( 'user_has_cap', array( __CLASS__, 'grant' ), 10, 4 );
		add_filter( 'map_meta_cap', array( __CLASS__, 'limit' ), 10, 4 );
		add_action( 'admin_init', array( __CLASS__, 'gate' ), 1 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), PHP_INT_MAX );
		add_action( 'admin_bar_menu', array( __CLASS__, 'bar' ), PHP_INT_MAX );
		add_filter( 'show_admin_bar', array( __CLASS__, 'front_bar' ), PHP_INT_MAX );
		add_filter( 'login_redirect', array( __CLASS__, 'login' ), PHP_INT_MAX, 3 );
		add_action( 'in_admin_header', array( __CLASS__, 'notices' ), PHP_INT_MAX );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'rest' ), 10, 3 );
		add_filter( 'woocommerce_prevent_admin_access', array( __CLASS__, 'woo' ), PHP_INT_MAX );
		add_filter( 'acf/prepare_field', array( __CLASS__, 'hide_field' ) );
		add_filter( 'acf/update_value', array( __CLASS__, 'keep_field' ), 1, 3 );
	}

	/* ---------- Declarations ---------- */

	/** Every module's declared roles, keyed by role slug. */
	public static function roles() {
		if ( self::$roles !== null ) {
			return self::$roles;
		}

		self::$roles = array();

		foreach ( (array) glob( SB_TWEAKS_PATH . 'modules/*/access.php' ) as $f ) {
			$list = include $f;

			foreach ( is_array( $list ) ? $list : array() as $slug => $def ) {
				if ( is_array( $def ) && ! empty( $def['label'] ) && ! empty( $def['home'] ) ) {
					self::$roles[ sanitize_key( $slug ) ] = wp_parse_args( $def, array(
						'module'            => '',
						'pages'             => array( $def['home'] ),
						'actions'           => array(),
						'admin_caps'        => array(),
						'types'             => array(),
						'edit_only'         => array(),
						'media'             => false,
						'admin_only_fields' => array(),
					) );
				}
			}
		}

		self::$roles = (array) apply_filters( 'sb_tweaks_access_roles', self::$roles );

		return self::$roles;
	}

	private static function module_on( $def ) {
		return $def['module'] === '' || ( class_exists( 'SB_Tweaks_Modules' ) && SB_Tweaks_Modules::instance()->is_enabled( $def['module'] ) );
	}

	/** A declared list: an array, or a callable run once WordPress is set up. */
	private static function list_of( $def, $key ) {
		$v = $def[ $key ];

		if ( is_callable( $v ) ) {
			$v = did_action( 'init' ) ? call_user_func( $v ) : array();
		}

		return array_values( array_unique( array_filter( (array) $v, 'is_string' ) ) );
	}

	/* ---------- Roles on and off ---------- */

	public static function sync_roles() {
		foreach ( self::roles() as $slug => $def ) {
			$role = get_role( $slug );
			$on   = self::module_on( $def );

			if ( $on && ! $role ) {
				$caps = array( 'read' => true, 'manage_categories' => true );

				if ( $def['media'] ) {
					$caps['upload_files'] = true;
				}

				foreach ( array( 'posts', 'pages' ) as $t ) {
					foreach ( array( 'edit_', 'edit_others_', 'edit_published_', 'edit_private_', 'publish_', 'read_private_', 'delete_', 'delete_others_', 'delete_published_', 'delete_private_' ) as $p ) {
						$caps[ $p . $t ] = true;
					}
				}

				add_role( $slug, $def['label'], $caps );
			} elseif ( ! $on && $role ) {
				self::remove_role( $slug );
			}
		}
	}

	private static function remove_role( $slug ) {
		foreach ( get_users( array( 'role' => $slug, 'fields' => 'ID' ) ) as $id ) {
			$u = new WP_User( $id );
			$u->set_role( 'subscriber' );
		}

		remove_role( $slug );
	}

	/** Plugin deactivated: every declared role goes, its users become Subscribers. */
	public static function remove_all() {
		foreach ( array_keys( self::roles() ) as $slug ) {
			if ( get_role( $slug ) ) {
				self::remove_role( $slug );
			}
		}
	}

	/* ---------- Who and where ---------- */

	/** The declaration for this user's limited role, or null (administrators never). */
	private static function def_for( $user = null ) {
		$user = $user ? $user : wp_get_current_user();

		if ( ! $user || empty( $user->roles ) || in_array( 'administrator', (array) $user->roles, true ) ) {
			return null;
		}

		foreach ( self::roles() as $slug => $def ) {
			if ( in_array( $slug, (array) $user->roles, true ) ) {
				return $def;
			}
		}

		return null;
	}

	private static function post_types( $def ) {
		$types = array_merge( self::list_of( $def, 'types' ), self::list_of( $def, 'edit_only' ) );

		if ( $def['media'] ) {
			$types[] = 'attachment';
		}

		return $types;
	}

	private static function page_ok( $def ) {
		$page = isset( $_GET['page'] ) ? (string) $_GET['page'] : ''; // phpcs:ignore WordPress.Security.NonceVerification

		foreach ( (array) $def['pages'] as $p ) {
			if ( $page !== '' && strpos( $page, $p ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	private static function request_type( $def ) {
		$id = isset( $_REQUEST['post'] ) ? (int) $_REQUEST['post'] : ( isset( $_REQUEST['post_ID'] ) ? (int) $_REQUEST['post_ID'] : 0 ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( $id ) {
			return (string) get_post_type( $id );
		}

		if ( isset( $_REQUEST['taxonomy'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$tax = get_taxonomy( sanitize_key( $_REQUEST['taxonomy'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

			return $tax && array_intersect( (array) $tax->object_type, self::post_types( $def ) ) ? 'allowed_tax' : '';
		}

		return isset( $_REQUEST['post_type'] ) ? sanitize_key( $_REQUEST['post_type'] ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/** May this role open this admin request? */
	private static function allowed_here( $def ) {
		global $pagenow;

		switch ( $pagenow ) {
			case 'admin-ajax.php':
			case 'admin-post.php':
			case 'profile.php':
				return true;
			case 'async-upload.php':
			case 'upload.php':
			case 'media-new.php':
				return (bool) $def['media'];
			case 'admin.php':
				return self::page_ok( $def );
			case 'edit.php':
			case 'post.php':
			case 'post-new.php':
				return in_array( self::request_type( $def ), self::post_types( $def ), true );
			case 'edit-tags.php':
			case 'term.php':
				return self::request_type( $def ) === 'allowed_tax';
		}

		return false;
	}

	/** Inside the module: its pages, its ajax and admin-post actions, and allowed screens. */
	private static function in_module( $def ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( wp_doing_ajax() || ( isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] === 'admin-post.php' ) ) {
			$a = isset( $_REQUEST['action'] ) ? (string) $_REQUEST['action'] : ''; // phpcs:ignore WordPress.Security.NonceVerification

			foreach ( array_merge( (array) $def['actions'], array( 'acf/' ) ) as $p ) {
				if ( $a !== '' && strpos( $a, $p ) === 0 ) {
					return true;
				}
			}

			return false;
		}

		return is_admin() && self::allowed_here( $def );
	}

	/* ---------- Permissions ---------- */

	public static function grant( $allcaps, $caps, $args, $user ) {
		$def = self::def_for( $user );

		if ( ! $def || ! $def['admin_caps'] ) {
			return $allcaps;
		}

		$want = array_intersect( (array) $caps, (array) $def['admin_caps'] );

		if ( $want && self::in_module( $def ) ) {
			foreach ( $want as $c ) {
				$allcaps[ $c ] = true;
			}
		}

		return $allcaps;
	}

	public static function limit( $caps, $cap, $user_id, $args ) {
		static $busy = false;

		if ( $busy || empty( $args[0] ) || ! in_array( $cap, array( 'edit_post', 'delete_post', 'publish_post', 'edit_page', 'delete_page' ), true ) ) {
			return $caps;
		}

		$busy = true;
		$user = get_userdata( $user_id );
		$def  = $user ? self::def_for( $user ) : null;
		$busy = false;

		$post = $def ? get_post( (int) $args[0] ) : null;

		if ( ! $post ) {
			return $caps;
		}

		if ( ! in_array( $post->post_type, self::post_types( $def ), true ) ) {
			return array( 'do_not_allow' );
		}

		if ( strpos( $cap, 'delete_' ) === 0 && in_array( $post->post_type, self::list_of( $def, 'edit_only' ), true ) ) {
			return array( 'do_not_allow' );
		}

		if ( $post->post_type === 'attachment' && $def['media'] === 'own' && $cap !== 'publish_post' && (int) $post->post_author !== (int) $user_id ) {
			return array( 'do_not_allow' );
		}

		return $caps;
	}

	/** REST: reading follows the checks above; changes only to Media, their profile and declared types. */
	public static function rest( $result, $server, $request ) {
		$def = self::def_for();

		if ( ! $def || $request->get_method() === 'GET' ) {
			return $result;
		}

		$route = (string) $request->get_route();
		$bases = array( 'users/me' );

		if ( $def['media'] ) {
			$bases[] = 'media';
		}

		foreach ( self::post_types( $def ) as $t ) {
			$o = get_post_type_object( $t );

			if ( $o && $t !== 'attachment' ) {
				$bases[] = $o->rest_base ? $o->rest_base : $t;

				foreach ( get_object_taxonomies( $t, 'objects' ) as $tax ) {
					$bases[] = $tax->rest_base ? $tax->rest_base : $tax->name;
				}
			}
		}

		foreach ( $bases as $b ) {
			if ( strpos( $route, '/wp/v2/' . $b ) === 0 ) {
				return $result;
			}
		}

		return new WP_Error( 'sb_tweaks_access', 'Your role can not change that.', array( 'status' => 403 ) );
	}

	/* ---------- What they see ---------- */

	private static function home_url( $def ) {
		return admin_url( 'admin.php?page=' . $def['home'] );
	}

	public static function gate() {
		$def = self::def_for();

		if ( $def && ! self::allowed_here( $def ) ) {
			wp_safe_redirect( self::home_url( $def ) );
			exit;
		}
	}

	public static function menu() {
		global $menu, $submenu;

		$def = self::def_for();

		if ( ! $def || ! is_array( $menu ) ) {
			return;
		}

		$keep = array_merge( (array) $def['pages'], array( 'profile.php' ) );

		if ( $def['media'] ) {
			$keep[] = 'upload.php';
		}

		foreach ( array_merge( self::list_of( $def, 'types' ), self::list_of( $def, 'edit_only' ) ) as $t ) {
			$keep[] = 'edit.php?post_type=' . $t;
		}

		foreach ( $menu as $i => $item ) {
			if ( ! isset( $item[2] ) || ! in_array( $item[2], $keep, true ) ) {
				unset( $menu[ $i ] );
			}
		}

		if ( is_array( $submenu ) ) {
			foreach ( array_keys( $submenu ) as $parent ) {
				if ( ! in_array( $parent, $keep, true ) ) {
					unset( $submenu[ $parent ] );
				}
			}
		}
	}

	public static function bar( $bar ) {
		if ( ! self::def_for() ) {
			return;
		}

		$keep = array( 'top-secondary', 'my-account', 'user-actions', 'user-info', 'edit-profile', 'logout' );

		foreach ( (array) $bar->get_nodes() as $node ) {
			if ( ! in_array( $node->id, $keep, true ) ) {
				$bar->remove_node( $node->id );
			}
		}
	}

	public static function front_bar( $show ) {
		return ! is_admin() && self::def_for() ? false : $show;
	}

	public static function login( $to, $requested, $user ) {
		$def = ( $user instanceof WP_User ) ? self::def_for( $user ) : null;

		return $def ? self::home_url( $def ) : $to;
	}

	/** Other plugins' notices: hidden outside the module's own pages. */
	public static function notices() {
		$def = self::def_for();

		if ( $def && ! self::page_ok( $def ) ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
		}
	}

	public static function woo( $prevent ) {
		return self::def_for() ? false : $prevent;
	}

	/* ---------- Fields kept for administrators ---------- */

	private static function admin_only( $field ) {
		$def = self::def_for();

		return $def && is_array( $field ) && isset( $field['key'] ) && in_array( $field['key'], (array) $def['admin_only_fields'], true );
	}

	public static function hide_field( $field ) {
		return self::admin_only( $field ) ? false : $field;
	}

	public static function keep_field( $value, $post_id, $field ) {
		return self::admin_only( $field ) ? get_field( $field['key'], $post_id, false ) : $value;
	}
}

SB_Tweaks_Access::init();