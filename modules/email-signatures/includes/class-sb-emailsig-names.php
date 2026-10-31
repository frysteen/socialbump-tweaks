<?php
/**
 * First and last names (Sam, 31 October 2026).
 *
 * A person has a First Name and a Last Name. The full name
 * (sb_emailsig_name) is still kept, built from them on every save, so
 * everything that shows a name goes on as it was; its field is hidden. The
 * names are kept as typed, so a staff page token such as {title} in one of
 * them is filled in when the name is read, as before.
 *
 * Once, people from before have their name split: the first word is the
 * first name, the rest the last name.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Names {

	const FIRST = 'field_sb_emailsig_first_name';
	const LAST  = 'field_sb_emailsig_last_name';
	const FULL  = 'field_sb_emailsig_name';

	/** Set once everyone's name has been split. */
	const SPLIT = 'sb_emailsig_names_split';

	/** Ways the Name and job title block can show a name: key => label. */
	public static function formats() {
		return array(
			'full'          => 'Full name (Sam Frysteen)',
			'first'         => 'First name (Sam)',
			'initial_last'  => 'Initial and last name (S. Frysteen)',
			'first_initial' => 'First name and initial (Sam F.)',
			'last_first'    => 'Last name, first name (Frysteen, Sam)',
		);
	}

	public static function init() {
		// After ACF has saved the fields (10), before the title follows the name (Photo, 20).
		add_action( 'acf/save_post', array( __CLASS__, 'saved' ), 15 );
		add_filter( 'acf/prepare_field/key=' . self::FULL, '__return_false' );
		add_filter( 'acf/load_field/key=' . self::FULL, array( __CLASS__, 'not_required' ) );
		add_action( 'admin_init', array( __CLASS__, 'split_all' ) );
	}

	/** The full name isn't filled in on the form any more, so it can't be required there. */
	public static function not_required( $field ) {
		$field['required'] = 0;

		return $field;
	}

	/** A name split once: [ first, last ], the first word and the rest. */
	public static function split( $name ) {
		$name = trim( preg_replace( '/\s+/', ' ', (string) $name ) );

		if ( $name === '' ) {
			return array( '', '' );
		}

		$bits = explode( ' ', $name, 2 );

		return array( $bits[0], $bits[1] ?? '' );
	}

	/** First and last as typed, joined. */
	public static function join( $first, $last ) {
		return trim( trim( (string) $first ) . ' ' . trim( (string) $last ) );
	}

	/** The full name from the first and last names as saved (tokens and all). */
	public static function sync( $post_id ) {
		$first = (string) get_post_meta( (int) $post_id, 'sb_emailsig_first_name', true );
		$last  = (string) get_post_meta( (int) $post_id, 'sb_emailsig_last_name', true );
		$full  = self::join( $first, $last );

		if ( $full !== '' && $full !== (string) get_post_meta( (int) $post_id, 'sb_emailsig_name', true ) ) {
			update_field( self::FULL, $full, (int) $post_id );
		}
	}

	/** A person saved with ACF (the edit screen, the person page): their full name follows. */
	public static function saved( $post_id ) {
		if ( ! is_numeric( $post_id ) || get_post_type( (int) $post_id ) !== SB_EMAILSIG_CPT ) {
			return;
		}

		self::sync( (int) $post_id );
	}

	/** Once: everyone with a name and no first name gets it split. */
	public static function split_all() {
		if ( get_option( self::SPLIT ) || ! function_exists( 'update_field' ) ) {
			return;
		}

		$ids = get_posts(
			array(
				'post_type'      => SB_EMAILSIG_CPT,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $ids as $id ) {
			if ( trim( (string) get_post_meta( $id, 'sb_emailsig_first_name', true ) ) !== '' ) {
				continue;
			}

			list( $first, $last ) = self::split( get_post_meta( $id, 'sb_emailsig_name', true ) );

			if ( $first !== '' ) {
				update_field( self::FIRST, $first, $id );
				update_field( self::LAST, $last, $id );
			}
		}

		update_option( self::SPLIT, time(), false );
	}

	/** A name shown in a format (formats()): from first and last, else the full name as it is. */
	public static function show( $format, $first, $last, $full ) {
		$first = trim( (string) $first );
		$last  = trim( (string) $last );

		if ( $first === '' && $last === '' ) {
			return trim( (string) $full );
		}

		$initial = function ( $s ) {
			if ( strpos( $s, '{' ) === 0 ) {
				return $s; // A placeholder such as {First Name}, in previews.
			}
			$c = function_exists( 'mb_substr' ) ? mb_substr( $s, 0, 1 ) : substr( $s, 0, 1 );
			return $c !== '' ? ( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $c ) : strtoupper( $c ) ) . '.' : '';
		};

		switch ( $format ) {
			case 'first':
				return $first !== '' ? $first : $last;
			case 'initial_last':
				return $last !== '' && $first !== '' ? $initial( $first ) . ' ' . $last : self::join( $first, $last );
			case 'first_initial':
				return $last !== '' && $first !== '' ? $first . ' ' . $initial( $last ) : self::join( $first, $last );
			case 'last_first':
				return $last !== '' && $first !== '' ? $last . ', ' . $first : self::join( $first, $last );
		}

		return self::join( $first, $last );
	}
}
