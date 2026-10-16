<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Only published posts come out of ACF relationship and post object fields on
 * the front end.
 *
 * ACF hands back every post a field holds, whatever its status, so a related
 * post picked while still a draft showed in a Bricks relationship loop straight
 * away. Filtering the formatted value covers everything that reads the field
 * that way: Bricks query loops, dynamic data, conditions and get_field().
 *
 * Taken from snippet 97 on thecosmeticstudionoosa.com.au. The wp-admin screens
 * are left alone, so a draft can still be picked and stays saved in the field;
 * it simply appears once it is published. Attachments are kept whatever their
 * parent's status, because media is never published as such.
 */
class SB_Bricks_Published_Only {

	public static function boot() {
		// The front end only, as the snippet ran. Bricks' own loop requests
		// (load more, filters) go through the REST API, which counts as front end.
		if ( is_admin() ) {
			return;
		}

		// After ACF's own formatting (10), so this sees the posts it returns.
		add_filter( 'acf/format_value/type=relationship', [ __CLASS__, 'many' ], 20 );
		add_filter( 'acf/format_value/type=post_object', [ __CLASS__, 'one_or_many' ], 20 );
	}

	/** A list of posts or IDs, with the unpublished ones taken out. */
	public static function many( $value ) {
		if ( empty( $value ) || ! is_array( $value ) ) {
			return $value;
		}

		return array_values( array_filter( $value, [ __CLASS__, 'shows' ] ) );
	}

	/** A post object field holds one post, or a list when it allows several. */
	public static function one_or_many( $value ) {
		if ( is_array( $value ) ) {
			return self::many( $value );
		}

		if ( empty( $value ) ) {
			return $value;
		}

		return self::shows( $value ) ? $value : null;
	}

	/** Whether a post, or a post ID, is one to show. */
	public static function shows( $post ) {
		$id = is_object( $post ) ? (int) $post->ID : (int) $post;

		if ( ! $id ) {
			return false;
		}

		if ( get_post_type( $id ) === 'attachment' ) {
			return true;
		}

		return get_post_status( $id ) === 'publish';
	}
}