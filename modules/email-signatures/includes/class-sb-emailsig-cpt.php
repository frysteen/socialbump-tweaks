<?php
/**
 * Signature CPT: one post per person. Not public; the signature page is
 * reached by a keyed link instead.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_CPT {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'save_post_' . SB_EMAILSIG_CPT, array( __CLASS__, 'ensure_key' ), 5 );
	}

	public static function register() {
		register_post_type(
			SB_EMAILSIG_CPT,
			array(
				'labels'              => array(
					'name'               => 'People',
					'singular_name'      => 'Email Signature',
					'menu_name'          => 'People',
					'add_new'            => 'Add Person',
					'add_new_item'       => 'Add Person',
					'edit_item'          => 'Edit Signature',
					'new_item'           => 'New Signature',
					'view_item'          => 'View Signature',
					'search_items'       => 'Search People',
					'not_found'          => 'No people yet',
					'not_found_in_trash' => 'No people in the bin',
					'all_items'          => 'People',
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => SB_EmailSig_Module::SLUG,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => false, // Title comes from the Name field.
			)
		);
	}

	/**
	 * Every signature gets a random key. The signature page needs it, so the
	 * link can be shared with staff without the pages being guessable.
	 */
	public static function ensure_key( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! get_post_meta( $post_id, '_sb_emailsig_key', true ) ) {
			update_post_meta( $post_id, '_sb_emailsig_key', wp_generate_password( 20, false, false ) );
		}
	}

	public static function get_key( $post_id ) {
		self::ensure_key( $post_id );
		return (string) get_post_meta( $post_id, '_sb_emailsig_key', true );
	}

	/**
	 * Link staff open to copy their signature.
	 */
	public static function page_url( $post_id ) {
		return add_query_arg(
			array(
				'sb_emailsig' => (int) $post_id,
				'key'         => self::get_key( $post_id ),
			),
			home_url( '/' )
		);
	}
}
