<?php
/**
 * Staff pages: for sites with a page per team member.
 *
 * A company's Company tab, Team Pages, Team Member Post Type: which post type
 * its staff pages are ('' for none, the default). Each company has its own
 * (Sam, 30 October 2026: was one for the site, in Settings; move_type()
 * copies that to every company once). Then each of its people has a Staff
 * Page field (sb_emailsig_staff_page, a post of that type) and:
 *
 * - their photo and name in the signature link to that page, through the
 *   short link with key p (SB_EmailSig_Tracking), while it's published;
 * - picking the page offers its featured image as their photo: used straight
 *   away if they have none, else a Use this photo button (the same button
 *   later loads a new featured image). The photo is copied once and kept,
 *   so its crop stays as set;
 * - the page's fields can be used in Name, Job Title, Email and Mobile as
 *   tokens: {title} (the page's title) and {field_name} for each of its ACF
 *   fields holding a single value (text, email, URL, number, select and the
 *   like). A panel under the Staff Page field lists those with a value; a
 *   click puts one in the field last typed in. Tokens are filled in when the
 *   field is read (acf/format_value), so everywhere shows the staff page's
 *   current value, and the signature changes when the page does. Signatures
 *   already pasted into Mail don't: Changed since copied shows.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Staff {

	/** The person fields tokens work in. */
	const TOKEN_FIELDS = array(
		'field_sb_emailsig_first_name',
		'field_sb_emailsig_last_name',
		'field_sb_emailsig_name',
		'field_sb_emailsig_qualifications',
		'field_sb_emailsig_job_title',
		'field_sb_emailsig_email',
		'field_sb_emailsig_mobile',
	);

	/** Set once Settings' Team Member Post Type has been copied to every company. */
	const MOVED = 'sb_emailsig_staff_type_moved';

	/** ACF field types with one value worth putting in a signature. */
	const TYPES = array( 'text', 'email', 'url', 'number', 'textarea', 'select', 'radio', 'button_group', 'date_picker', 'range' );

	public static function init() {
		add_filter( 'acf/load_field/key=field_sb_emailsig_staff_type', array( __CLASS__, 'type_choices' ) );
		add_filter( 'acf/load_field/key=field_sb_emailsig_staff_fields', array( __CLASS__, 'field_choices' ) );
		add_filter( 'acf/load_value/key=field_sb_emailsig_staff_fields', array( __CLASS__, 'fields_value' ), 10, 2 );
		add_filter( 'acf/load_field/key=field_sb_emailsig_staff_page', array( __CLASS__, 'page_field' ) );
		add_filter( 'acf/prepare_field/key=field_sb_emailsig_staff_page', array( __CLASS__, 'hide_without_type' ) );
		add_filter( 'acf/validate_value/key=field_sb_emailsig_email', array( __CLASS__, 'validate_email' ), 10, 2 );
		add_action( 'wp_ajax_sb_emailsig_staff', array( __CLASS__, 'ajax' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'move_type' ) );
		add_filter( 'acf/fields/post_object/query/key=field_sb_emailsig_staff_page', array( __CLASS__, 'page_query' ), 10, 3 );

		foreach ( self::TOKEN_FIELDS as $key ) {
			add_filter( 'acf/format_value/key=' . $key, array( __CLASS__, 'format' ), 20, 2 );
		}
	}

	/**
	 * A company's staff pages' post type, or ''. $co null: the company being
	 * drawn (else the default); 0 with no companies: Settings' old one.
	 */
	public static function type( $co = null ) {
		if ( null === $co ) {
			$co = SB_EmailSig_Company::current();
		}

		$type = $co ? (string) get_post_meta( (int) $co, 'sb_emailsig_staff_type', true ) : (string) get_option( 'options_sb_emailsig_staff_type', '' );

		return $type !== '' && post_type_exists( $type ) ? $type : '';
	}

	/** A person's company's staff pages' post type, or '' ($post_id 0: a new person, so the default company's). */
	public static function person_type( $post_id ) {
		return self::type( SB_EmailSig_Company::of_person( (int) $post_id ) );
	}

	/** Every company with staff pages: [ company id => post type ]. */
	public static function types() {
		$out = array();
		$all = SB_EmailSig_Company::all();

		foreach ( $all ? $all : array( 0 ) as $co ) {
			$type = self::type( $co );
			if ( $type !== '' ) {
				$out[ $co ] = $type;
			}
		}

		return $out;
	}

	/**
	 * Once, after companies: Settings' Team Member Post Type to every company
	 * that has none of its own, so nothing changes for anyone.
	 */
	public static function move_type() {
		if ( get_option( self::MOVED ) || ! get_option( SB_EmailSig_Company::MIGRATED ) ) {
			return;
		}

		$old = (string) get_option( 'options_sb_emailsig_staff_type', '' );

		if ( $old !== '' ) {
			foreach ( SB_EmailSig_Company::all( array( 'publish', 'draft', 'private' ) ) as $co ) {
				if ( (string) get_post_meta( $co, 'sb_emailsig_staff_type', true ) === '' ) {
					update_post_meta( $co, 'sb_emailsig_staff_type', $old );
					update_post_meta( $co, '_sb_emailsig_staff_type', 'field_sb_emailsig_staff_type' );
				}
			}
		}

		update_option( self::MOVED, 1, true );
	}

	/**
	 * Each post type's ACF fields that can be merge tags: [ type => [ name =>
	 * label ] ], for the types Team Member Post Type offers.
	 */
	public static function field_map() {
		$out = array();

		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return $out;
		}

		foreach ( array_keys( self::type_choices( array() )['choices'] ) as $type ) {
			if ( $type === '' ) {
				continue;
			}

			foreach ( acf_get_field_groups( array( 'post_type' => $type ) ) as $group ) {
				foreach ( (array) acf_get_fields( $group ) as $field ) {
					$name = (string) ( $field['name'] ?? '' );
					if ( $name !== '' && preg_match( '/^[a-z0-9_]+$/i', $name ) && in_array( $field['type'] ?? '', self::TYPES, true ) ) {
						$out[ $type ][ $name ] = (string) ( $field['label'] ?: $name );
					}
				}
			}
		}

		return $out;
	}

	/** Merge Tag Fields: every type's fields (settings.js shows the chosen type's). */
	public static function field_choices( $field ) {
		$field['choices'] = array();

		foreach ( self::field_map() as $fields ) {
			foreach ( $fields as $name => $label ) {
				$field['choices'][ $name ] = $label . ' {' . $name . '}';
			}
		}

		return $field;
	}

	/**
	 * Merge Tag Fields never saved: every field of the company's type ticked,
	 * as all of them work until it's saved (allowed()), so saving the page
	 * doesn't switch any off.
	 */
	public static function fields_value( $value, $post_id ) {
		if ( ! is_numeric( $post_id ) || ! SB_EmailSig_Company::valid( (int) $post_id ) || metadata_exists( 'post', (int) $post_id, 'sb_emailsig_staff_fields' ) ) {
			return $value;
		}

		$map = self::field_map();

		return array_keys( $map[ self::type( (int) $post_id ) ] ?? array() );
	}

	/**
	 * The staff page fields a company's people can use as merge tags, or null
	 * for all of them (a company that has never saved Merge Tag Fields, as
	 * before Sam's 30 October 2026 change). {title} is always allowed.
	 */
	public static function allowed( $co ) {
		$co = (int) $co;

		if ( ! $co || ! metadata_exists( 'post', $co, 'sb_emailsig_staff_fields' ) ) {
			return null;
		}

		$names = get_post_meta( $co, 'sb_emailsig_staff_fields', true );

		return array_values( array_filter( array_map( 'strval', is_array( $names ) ? $names : array() ) ) );
	}

	/** A person's staff page id, if it's there and of their company's type. */
	public static function page_id( $post_id ) {
		$id   = (int) get_post_meta( (int) $post_id, 'sb_emailsig_staff_page', true );
		$type = self::person_type( $post_id );

		return ( $id && $type !== '' && get_post_type( $id ) === $type ) ? $id : 0;
	}

	/** The staff page's address, while it's published. */
	public static function page_url( $post_id ) {
		$id = self::page_id( $post_id );

		return ( $id && get_post_status( $id ) === 'publish' ) ? (string) get_permalink( $id ) : '';
	}

	/** Settings: the site's public post types to pick from. */
	public static function type_choices( $field ) {
		$choices = array( '' => 'None' );

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( in_array( $type->name, array( 'attachment', SB_EMAILSIG_CPT ), true ) ) {
				continue;
			}
			$choices[ $type->name ] = $type->labels->name . ' (' . $type->name . ')';
		}

		$field['choices'] = $choices;

		return $field;
	}

	/** The Staff Page field: posts of any company's type (page_query() narrows it to the person's company's). */
	public static function page_field( $field ) {
		$types = array_values( array_unique( self::types() ) );

		$field['post_type'] = $types ? $types : array( 'post' );

		return $field;
	}

	/** Shown while any company has staff pages; staff.js hides it for a company without. */
	public static function hide_without_type( $field ) {
		return self::types() ? $field : false;
	}

	/**
	 * The Staff Page search: the company picked on the page (staff.js sends
	 * sb_company), else the person's own company's type.
	 */
	public static function page_query( $args, $field, $post_id ) {
		$co   = isset( $_POST['sb_company'] ) ? absint( $_POST['sb_company'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- ACF checked its own nonce.
		$co   = SB_EmailSig_Company::valid( $co ) ? $co : SB_EmailSig_Company::of_person( (int) $post_id );
		$type = self::type( $co );

		$args['post_type'] = $type !== '' ? array( $type ) : array( 'sb_emailsig_none' );

		return $args;
	}

	/** Email: an address, or tokens from the staff page. */
	public static function validate_email( $valid, $value ) {
		if ( $valid !== true ) {
			return $valid;
		}

		$value = trim( (string) $value );

		if ( $value === '' || is_email( $value ) || preg_match( '/\{[a-z0-9_]+\}/i', $value ) ) {
			return true;
		}

		return 'Enter an email address, or a token from the staff page such as {email}.';
	}

	/**
	 * Every token a staff page offers: [ token => [ label, value ] ], value ''
	 * when empty. $co: only the fields that company allows (null: all).
	 */
	public static function tokens( $staff_id, $co = null ) {
		$staff_id = (int) $staff_id;
		$allow    = null === $co ? null : self::allowed( $co );
		$out      = array(
			'title' => array( 'Title', (string) get_the_title( $staff_id ) ),
		);

		if ( ! function_exists( 'get_field_objects' ) ) {
			return $out;
		}

		foreach ( (array) get_field_objects( $staff_id ) as $name => $field ) {
			if ( ! is_array( $field ) || ! in_array( $field['type'] ?? '', self::TYPES, true ) || ! preg_match( '/^[a-z0-9_]+$/i', (string) $name ) ) {
				continue;
			}
			if ( null !== $allow && ! in_array( (string) $name, $allow, true ) ) {
				continue;
			}

			$value = $field['value'] ?? '';
			if ( is_array( $value ) ) {
				$value = isset( $value['label'] ) ? $value['label'] : ( count( $value ) === 1 ? reset( $value ) : '' );
			}
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$out[ $name ] = array( (string) ( $field['label'] ?: $name ), trim( wp_strip_all_tags( (string) $value ) ) );
		}

		return $out;
	}

	/** Fills {tokens} with the person's staff page values; unknown or empty ones go. */
	public static function fill( $value, $post_id ) {
		$value = (string) $value;

		if ( strpos( $value, '{' ) === false ) {
			return $value;
		}

		$staff  = self::page_id( $post_id );
		$tokens = $staff ? self::tokens( $staff, SB_EmailSig_Company::of_person( (int) $post_id ) ) : array();

		$value = preg_replace_callback(
			'/\{([a-z0-9_]+)\}/i',
			function ( $m ) use ( $tokens ) {
				return isset( $tokens[ $m[1] ] ) ? $tokens[ $m[1] ][1] : '';
			},
			$value
		);

		return trim( preg_replace( '/\s{2,}/', ' ', $value ) );
	}

	/** acf/format_value: tokens filled wherever the field is read. */
	public static function format( $value, $post_id ) {
		return is_string( $value ) ? self::fill( $value, $post_id ) : $value;
	}

	/** Tokens with a value missing from what the person's fields use: [ field label => [ tokens ] ]. */
	public static function missing( $post_id ) {
		$staff  = self::page_id( $post_id );
		$tokens = $staff ? self::tokens( $staff, SB_EmailSig_Company::of_person( (int) $post_id ) ) : array();
		$out    = array();

		foreach ( self::TOKEN_FIELDS as $key ) {
			// The full name is built from the first and last: they say it.
			if ( $key === SB_EmailSig_Names::FULL ) {
				continue;
			}

			$name = str_replace( 'field_', '', $key );
			$raw  = (string) get_post_meta( (int) $post_id, $name, true );

			if ( preg_match_all( '/\{([a-z0-9_]+)\}/i', $raw, $m ) ) {
				foreach ( $m[1] as $token ) {
					if ( ! isset( $tokens[ $token ] ) || $tokens[ $token ][1] === '' ) {
						$out[ $key ][] = '{' . $token . '}';
					}
				}
			}
		}

		return $out;
	}

	/** The edit screen's panel asks for a staff page's tokens and photo. */
	public static function ajax() {
		check_ajax_referer( 'sb_emailsig_staff', 'nonce' );

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		// The company picked on the page (staff.js), else the default.
		$co = isset( $_POST['company'] ) ? absint( $_POST['company'] ) : 0;
		$co = SB_EmailSig_Company::valid( $co ) ? $co : SB_EmailSig_Company::default_id();

		if ( ! current_user_can( 'edit_posts' ) || ! $id || ! in_array( get_post_type( $id ), self::types(), true ) || ! current_user_can( 'read_post', $id ) ) {
			wp_send_json_error( null, 403 );
		}

		// Every one the company allows, empty ones too (Sam, 30 October 2026:
		// so it's clear what's on offer before the staff page is filled in).
		$tokens = array();
		foreach ( self::tokens( $id, $co ) as $token => $t ) {
			$tokens[] = array(
				'token' => '{' . $token . '}',
				'label' => $t[0],
				'value' => $t[1],
			);
		}

		$thumb = (int) get_post_thumbnail_id( $id );
		$src   = $thumb ? wp_get_attachment_image_src( $thumb, 'thumbnail' ) : false;

		wp_send_json_success(
			array(
				'tokens' => $tokens,
				'photo'  => $src ? array(
					'id'    => $thumb,
					'url'   => $src[0],
					'large' => (string) wp_get_attachment_image_url( $thumb, 'large' ),
				) : null,
				'link'   => get_post_status( $id ) === 'publish' ? get_permalink( $id ) : '',
			)
		);
	}

	public static function assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		$post   = $screen && $screen->post_type === SB_EMAILSIG_CPT && $screen->base === 'post';

		$types = self::types();

		if ( ( ! $post && ! SB_EmailSig_Person::is_screen() ) || ! $types ) {
			return;
		}

		wp_enqueue_script( 'sb-emailsig-staff', SB_EMAILSIG_URL . 'assets/staff.js', array( 'jquery', 'acf-input' ), SB_EmailSig_Module::asset_version( 'assets/staff.js' ), true );
		wp_localize_script(
			'sb-emailsig-staff',
			'sbEmailsigStaff',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'sb_emailsig_staff' ),
				// The companies with staff pages, and whose a new person is.
				'types' => (object) array_map( '__return_true', $types ),
				'def'   => SB_EmailSig_Company::default_id(),
			)
		);
	}
}
