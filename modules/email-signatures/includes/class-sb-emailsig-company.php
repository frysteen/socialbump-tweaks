<?php
/**
 * Companies (Sam, 30 October 2026). One site can make signatures for several
 * businesses: each company is a post of its own type carrying what Settings
 * used to hold for the one company (Company, Branding, Social Media, Footer
 * and Campaign Banner, the same ACF fields), and its templates: the ones its
 * people can use and its main one. Every person belongs to one company.
 * Settings keeps what is the site's own (admin branding, notifications,
 * signature emails, tracking).
 *
 * The company being drawn is a context: SB_EmailSig_Data::get() enters the
 * person's company while it reads, and everything that reads company fields
 * asks source() for where to read them (the company's post id). With no
 * company yet (before migrate() has run) source() is 'option', as before.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Company {

	const CPT            = 'sb_emailsig_company';
	const LEGACY_OPTION  = 'sb_emailsig_default_company'; // The company Settings became: legacy_id().
	const PERSON_META    = 'sb_emailsig_company';
	const MIGRATED       = 'sb_emailsig_companies_v1';
	const TEAM_KEY       = 'sb_emailsig_team_key';

	/** Every status a person can have, deleted (Trash) included: 'any' leaves Trash out. */
	const STATUSES = array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' );

	/** The ACF field groups a company is edited with. */
	const GROUP = 'group_sb_emailsig_company';

	/** The company fields' names: what migrate() copies from the options. */
	const FIELDS = array(
		'sb_emailsig_company_name',
		'sb_emailsig_phone',
		'sb_emailsig_address',
		'sb_emailsig_website',
		'sb_emailsig_maps_link',
		'sb_emailsig_info',
		'sb_emailsig_main_template',
		'sb_emailsig_templates',
		'sb_emailsig_logo',
		'sb_emailsig_logo_small',
		'sb_emailsig_brand_primary',
		'sb_emailsig_brand_accent',
		'sb_emailsig_brand_text',
		'sb_emailsig_brand_text_light',
		'sb_emailsig_line_phone_icon',
		'sb_emailsig_line_phone_icon_file',
		'sb_emailsig_line_mobile_icon',
		'sb_emailsig_line_mobile_icon_file',
		'sb_emailsig_line_email_icon',
		'sb_emailsig_line_email_icon_file',
		'sb_emailsig_line_website_icon',
		'sb_emailsig_line_website_icon_file',
		'sb_emailsig_line_address_icon',
		'sb_emailsig_line_address_icon_file',
		'sb_emailsig_line_vcard_icon',
		'sb_emailsig_line_vcard_icon_file',
		'sb_emailsig_social_heading',
		'sb_emailsig_social_media',
		'sb_emailsig_signoff',
		'sb_emailsig_vcard_show',
		'sb_emailsig_vcard_label',
		'sb_emailsig_buttons',
		'sb_emailsig_banners',
		'sb_emailsig_fine_print',
		'sb_emailsig_campaign_show',
		'sb_emailsig_campaign_width',
		'sb_emailsig_campaign_default_image',
		'sb_emailsig_campaign_default_link',
		'sb_emailsig_campaigns',
	);

	/** Companies being drawn, innermost last (enter(), leave()). */
	private static $stack = array();

	/** People shown in a company not yet saved (the person page's preview): person id => company id. */
	private static $force = array();

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'migrate' ), 30 );
		add_action( 'acf/save_post', array( __CLASS__, 'saved' ), 20 );
		add_action( 'before_delete_post', array( __CLASS__, 'deleting' ) );
		add_action( 'save_post_' . self::CPT, array( __CLASS__, 'forget' ) );
		// Nobody without a company (Sam, 31 October 2026): with one company
		// its field isn't shown, so a person can't be put in it by hand.
		add_action( 'save_post_' . SB_EMAILSIG_CPT, array( __CLASS__, 'adopt_one' ), 30 );
		add_action( 'admin_init', array( __CLASS__, 'adopt_all' ) );
	}

	/** A person saved with no company, or one that's gone: they join the first company (first_id()). */
	public static function adopt_one( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || get_post_status( $post_id ) === 'auto-draft' || self::valid( get_post_meta( (int) $post_id, self::PERSON_META, true ) ) ) {
			return;
		}

		$def = self::first_id();

		if ( $def ) {
			update_post_meta( (int) $post_id, self::PERSON_META, $def );
		}
	}

	/** Anyone left without a company (added before there were companies, say) joins the first company; checked twice a day. */
	public static function adopt_all() {
		if ( get_transient( 'sb_emailsig_adopted' ) || ! get_option( self::MIGRATED ) ) {
			return;
		}

		set_transient( 'sb_emailsig_adopted', 1, 12 * HOUR_IN_SECONDS );

		foreach ( get_posts( array( 'post_type' => SB_EMAILSIG_CPT, 'post_status' => self::STATUSES, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $person ) {
			self::adopt_one( $person );
		}
	}

	public static function register() {
		register_post_type(
			self::CPT,
			array(
				'labels'          => array(
					'name'          => 'Companies',
					'singular_name' => 'Company',
				),
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	/* ------------------------------------------------------------------ *
	 * Which companies
	 * ------------------------------------------------------------------ */

	/** Every company's id, by name. $statuses: publish (the default), or with trash. */
	public static function all( array $statuses = array( 'publish' ) ) {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => self::CPT,
					'post_status'    => $statuses,
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'fields'         => 'ids',
				)
			)
		);
	}

	public static function valid( $id ) {
		$id = (int) $id;

		return $id > 0 && get_post_type( $id ) === self::CPT && get_post_status( $id ) === 'publish';
	}

	/** first_id()'s answer for this request; forget() clears it. */
	private static $first = null;

	/**
	 * The company to fall back on where one is needed and none is set (a
	 * person saved without one, a page drawn outside any company): the oldest
	 * there is, but $except, or 0 for none. No company is special (Sam,
	 * 3 November 2026): any can be deleted while there's another.
	 */
	public static function first_id( $except = 0 ) {
		$except = (int) $except;

		if ( ! $except && null !== self::$first ) {
			return self::$first;
		}

		$args = array(
			'post_type'        => self::CPT,
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'orderby'          => array(
				'date' => 'ASC',
				'ID'   => 'ASC',
			),
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		);

		if ( $except ) {
			$args['post__not_in'] = array( $except );
		}

		$ids = get_posts( $args );
		$id  = $ids ? (int) $ids[0] : 0;

		if ( ! $except ) {
			self::$first = $id;
		}

		return $id;
	}

	/** A company added or deleted: first_id() asks again. */
	public static function forget() {
		self::$first = null;
	}

	/**
	 * The company Settings became (migrate()) while it's still here, else 0.
	 * Only for addresses made before there were companies, so they keep
	 * working: its icons' slots without a company (SB_EmailSig_Icons::slot())
	 * and its banner image at ?{QUERY}=1 (SB_EmailSig_Campaign).
	 */
	public static function legacy_id() {
		$id = (int) get_option( self::LEGACY_OPTION, 0 );

		return self::valid( $id ) ? $id : 0;
	}

	/** A person's company: theirs (or the one force() gives them), else the first company. */
	public static function of_person( $post_id ) {
		$id = $post_id ? ( self::$force[ (int) $post_id ] ?? (int) get_post_meta( (int) $post_id, self::PERSON_META, true ) ) : 0;

		return self::valid( $id ) ? $id : self::first_id();
	}

	/** A company's name. */
	public static function name( $id ) {
		$name = $id ? trim( (string) get_post_meta( (int) $id, 'sb_emailsig_company_name', true ) ) : '';

		return $name !== '' ? $name : ( $id ? get_the_title( $id ) : '' );
	}

	/**
	 * A company's team page key (SB_EmailSig_Page::list_url( $id )): its own,
	 * so its link shows only its people and can't be turned into another's.
	 */
	public static function team_key( $id ) {
		$key = (string) get_post_meta( (int) $id, self::TEAM_KEY, true );

		if ( $key === '' ) {
			$key = wp_generate_password( 20, false, false );
			update_post_meta( (int) $id, self::TEAM_KEY, $key );
		}

		return $key;
	}

	/** The company a team page key belongs to, or 0. */
	public static function by_team_key( $key ) {
		if ( ! is_string( $key ) || strlen( $key ) < 20 ) {
			return 0;
		}

		$ids = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::TEAM_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);

		return $ids && hash_equals( (string) get_post_meta( (int) $ids[0], self::TEAM_KEY, true ), $key ) ? (int) $ids[0] : 0;
	}

	/** Treat $person as in company $co until force( $person, null ). */
	public static function force( $person, $co ) {
		if ( null === $co ) {
			unset( self::$force[ (int) $person ] );
		} else {
			self::$force[ (int) $person ] = (int) $co;
		}
	}

	/* ------------------------------------------------------------------ *
	 * The company being drawn
	 * ------------------------------------------------------------------ */

	public static function enter( $id ) {
		self::$stack[] = (int) $id;
	}

	public static function leave() {
		array_pop( self::$stack );
	}

	/** $fn run with $id as the company being drawn. */
	public static function with( $id, callable $fn ) {
		self::enter( $id );

		try {
			return $fn();
		} finally {
			self::leave();
		}
	}

	/** The company being drawn: the innermost entered, else the first company (0 for none). */
	public static function current() {
		$id = self::$stack ? (int) end( self::$stack ) : 0;

		return self::valid( $id ) ? $id : self::first_id();
	}

	/** A company's Email when it is a real address, else ''. Null for the company being drawn. */
	public static function email( $id = null ) {
		$v = trim( (string) get_field( 'sb_emailsig_company_email', $id === null ? self::source() : ( self::valid( (int) $id ) ? (int) $id : self::source() ) ) );

		return is_email( $v ) ? $v : '';
	}

	/** Where the company fields are read: the company's post id, or 'option' before there are companies. */
	public static function source() {
		$id = self::current();

		return $id ? $id : 'option';
	}

	/* ------------------------------------------------------------------ *
	 * Saving
	 * ------------------------------------------------------------------ */

	/** A company saved: its title follows its Company Name, and its rows get their fixed ids. */
	public static function saved( $post_id ) {
		if ( ! is_numeric( $post_id ) || get_post_type( (int) $post_id ) !== self::CPT ) {
			return;
		}

		$name = trim( (string) get_post_meta( (int) $post_id, 'sb_emailsig_company_name', true ) );

		if ( $name !== '' && get_the_title( (int) $post_id ) !== $name ) {
			remove_action( 'acf/save_post', array( __CLASS__, 'saved' ), 20 );
			wp_update_post(
				array(
					'ID'         => (int) $post_id,
					'post_title' => $name,
				)
			);
			add_action( 'acf/save_post', array( __CLASS__, 'saved' ), 20 );
		}
	}

	/**
	 * A company deleted for good: its people (deleted ones too) move to the
	 * first company left, unless Delete has moved them already, to the one
	 * picked (SB_EmailSig_Companies::delete()). Deleting the company Settings
	 * became ends its addresses from before companies (legacy_id()).
	 */
	public static function deleting( $post_id ) {
		if ( get_post_type( $post_id ) !== self::CPT ) {
			return;
		}

		self::forget();

		$to = self::first_id( (int) $post_id );

		if ( (int) get_option( self::LEGACY_OPTION, 0 ) === (int) $post_id ) {
			delete_option( self::LEGACY_OPTION );
		}

		foreach ( get_posts( array( 'post_type' => SB_EMAILSIG_CPT, 'post_status' => self::STATUSES, 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => self::PERSON_META, 'meta_value' => (int) $post_id ) ) as $person ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			if ( $to ) {
				update_post_meta( $person, self::PERSON_META, $to );
			} else {
				delete_post_meta( $person, self::PERSON_META );
			}
		}
	}

	/* ------------------------------------------------------------------ *
	 * Moving Settings into the first company (once)
	 * ------------------------------------------------------------------ */

	/**
	 * Settings become the first company: every company field's options
	 * (options_{name}, its repeater rows options_{name}_{i}_{sub} and ACF's
	 * _options_ references) copied to the company's post meta, the main
	 * template and the templates its people use added to its set, and every
	 * person put in it. The options stay as they were, to go back to.
	 */
	public static function migrate() {
		global $wpdb;

		if ( get_option( self::MIGRATED ) || ! function_exists( 'get_field' ) ) {
			return;
		}

		// One request at a time.
		if ( get_transient( 'sb_emailsig_company_lock' ) ) {
			return;
		}
		set_transient( 'sb_emailsig_company_lock', 1, 60 );

		$id = self::first_id();

		if ( ! $id ) {
			$name = trim( (string) get_option( 'options_sb_emailsig_company_name', '' ) );
			$id   = wp_insert_post(
				array(
					'post_type'   => self::CPT,
					'post_status' => 'publish',
					'post_title'  => $name !== '' ? $name : get_bloginfo( 'name' ),
				),
				true
			);

			if ( is_wp_error( $id ) || ! $id ) {
				delete_transient( 'sb_emailsig_company_lock' );

				return;
			}

			// Every company field, as the options have it.
			$rows = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'options\\_sb\\_emailsig\\_%' OR option_name LIKE '\\_options\\_sb\\_emailsig\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			foreach ( $rows as $row ) {
				$ref  = strpos( $row->option_name, '_options_' ) === 0;
				$name = substr( $row->option_name, $ref ? 9 : 8 );

				if ( self::is_field( $name ) ) {
					update_post_meta( $id, ( $ref ? '_' : '' ) . $name, maybe_unserialize( $row->option_value ) );
				}
			}

			// The main template (kept in its own option until now).
			$main = (string) get_option( SB_EmailSig_Module::TEMPLATE_OPTION, '' );
			if ( $main !== '' ) {
				update_post_meta( $id, 'sb_emailsig_main_template', $main );
				update_post_meta( $id, '_sb_emailsig_main_template', 'field_sb_emailsig_main_template' );
			}

			// Its set: what it had, and every template one of its people uses, so no one loses one.
			$set = (array) get_option( 'options_' . SB_EmailSig_Templates::COMPANY_SET, array() );

			foreach ( get_posts( array( 'post_type' => SB_EMAILSIG_CPT, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $person ) {
				$own = (string) get_post_meta( $person, SB_EmailSig_Templates::PERSON_MAIN, true );

				if ( $own !== '' ) {
					$set[] = $own;
				}

				foreach ( (array) get_post_meta( $person, SB_EmailSig_Templates::PERSON_EXTRA, true ) as $extra ) {
					$set[] = (string) $extra;
				}
			}

			update_post_meta( $id, SB_EmailSig_Templates::COMPANY_SET, array_values( array_unique( array_filter( array_map( 'strval', $set ) ) ) ) );
			update_post_meta( $id, '_' . SB_EmailSig_Templates::COMPANY_SET, 'field_sb_emailsig_templates' );
		}

		update_option( self::LEGACY_OPTION, (int) $id, true );

		// Everyone without a company joins it.
		foreach ( get_posts( array( 'post_type' => SB_EMAILSIG_CPT, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $person ) {
			if ( ! self::valid( get_post_meta( $person, self::PERSON_META, true ) ) ) {
				update_post_meta( $person, self::PERSON_META, (int) $id );
			}
		}

		update_option( self::MIGRATED, 1, true );
		delete_transient( 'sb_emailsig_company_lock' );
	}

	/** True for a company field's meta name: one of FIELDS, or a row of one of its repeaters. */
	private static function is_field( $name ) {
		foreach ( self::FIELDS as $field ) {
			if ( $name === $field || preg_match( '/^' . preg_quote( $field, '/' ) . '_\d+_/', $name ) ) {
				return true;
			}
		}

		return false;
	}
}
