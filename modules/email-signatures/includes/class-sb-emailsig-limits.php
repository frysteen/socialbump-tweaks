<?php
/**
 * This site's limits, set from the hub (Sam, 31 October 2026): how many
 * people (everyone, deleted ones in the Trash too: they delete them for good
 * or buy more slots), companies switched on and templates it can have
 * (SB_Tweaks_Policy::limits(), 0 for no limit; none on the hub itself).
 *
 * Each page's bar says how many there are, "People 1 of 1" (just the count
 * with no limit: count()), and at a limit its Add button is greyed out with
 * a tip saying why (button()), on the team pages too, and nothing more can be
 * added or switched on. Over one (a limit lowered on the hub), the newest go
 * off: people past it Inactive, companies switched off (SB_EmailSig_Switch,
 * never the default one), templates left out of SB_EmailSig_Templates::all()
 * so nobody can use them until the limit goes up. Nothing is deleted.
 * apply() runs once per change of limits.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Limits {

	const MODULE = 'email_signatures';

	/** The limits last applied, so apply() only runs when they change. */
	const APPLIED = 'sb_emailsig_limits_applied';

	public static function init() {
		// After SB_EmailSig_Switch::stay_off() (20).
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'hold_person' ), 21, 2 );
		add_filter( 'wp_insert_post_empty_content', array( __CLASS__, 'stop_new_person' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'apply' ), 30 );
	}

	/** What each limit counts, for the bars: singular, plural. */
	const NOUNS = array(
		'people'    => array( 'person', 'people' ),
		'companies' => array( 'company', 'companies' ),
		'templates' => array( 'template', 'templates' ),
	);

	/** One limit: a number, or 0 for none. */
	public static function limit( $key ) {
		return class_exists( 'SB_Tweaks_Policy' ) ? SB_Tweaks_Policy::limit( self::MODULE, $key ) : 0;
	}

	/** Everyone, deleted ones in the Trash too, oldest first; but $except. */
	public static function people( $except = 0 ) {
		$ids = get_posts(
			array(
				'post_type'      => SB_EMAILSIG_CPT,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
				'no_found_rows'  => true,
			)
		);

		return array_values( array_diff( array_map( 'intval', $ids ), array( (int) $except ) ) );
	}

	/** Companies switched on, the default first, then oldest first; but $except. */
	public static function companies( $except = 0 ) {
		$on = array();

		foreach ( SB_EmailSig_Company::all() as $co ) {
			if ( (int) $co !== (int) $except && ! SB_EmailSig_Switch::is_off( $co ) ) {
				$on[] = (int) $co;
			}
		}

		sort( $on );

		$default = (int) SB_EmailSig_Company::default_id();

		if ( in_array( $default, $on, true ) ) {
			$on = array_merge( array( $default ), array_values( array_diff( $on, array( $default ) ) ) );
		}

		return $on;
	}

	/** Every template record, oldest first, whatever its limit. */
	public static function templates() {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'        => SB_EmailSig_Templates::CPT,
					'post_status'      => 'publish',
					'posts_per_page'   => -1,
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => true,
				)
			)
		);
	}

	/** True when no more can be added or switched on: people, companies or templates. $except one already counted. */
	public static function full( $key, $except = 0 ) {
		$limit = self::limit( $key );

		if ( $limit < 1 ) {
			return false;
		}

		switch ( $key ) {
			case 'people':
				return count( self::people( $except ) ) >= $limit;
			case 'companies':
				return count( self::companies( $except ) ) >= $limit;
			case 'templates':
				return count( self::templates() ) >= $limit;
		}

		return false;
	}

	/** Why it can't: "No people slots left. Ask for more to add another." */
	public static function message( $key ) {
		$noun = self::NOUNS[ $key ][0] ?? $key;

		return 'No ' . $noun . ' slots left. Ask for more to add another.';
	}

	/** How many there are now, as the bar counts them. */
	public static function used( $key ) {
		switch ( $key ) {
			case 'people':
				return count( self::people() );
			case 'companies':
				return self::limit( 'companies' ) > 0 ? count( self::companies() ) : count( SB_EmailSig_Company::all() );
			case 'templates':
				return count( self::templates() );
		}

		return 0;
	}

	/** The bar's count: "People 1 of 1", or "People 2" with no limit. */
	public static function count( $key ) {
		$limit = self::limit( $key );
		$used  = self::used( $key );
		$label = ucfirst( self::NOUNS[ $key ][1] ?? $key );

		// A grey pill; amber with one slot left, red with none (Sam, 31 October 2026).
		$state = $limit > 0 && $used >= $limit ? ' is-full' : ( $limit > 0 && $limit - $used === 1 ? ' is-low' : '' );

		return '<span class="sb-emailsig-limit' . $state . '"><span class="sb-emailsig-limit__label">' . esc_html( $label ) . '</span> <strong>' . (int) $used . '</strong>' . ( $limit > 0 ? ' of ' . (int) $limit : '' ) . '</span>';
	}

	/**
	 * An Add button: $html as it is, or at the limit the same look greyed
	 * out, with the reason as its tip (a disabled button shows no tip, so a
	 * span carries it).
	 */
	public static function button( $key, $html ) {
		if ( ! self::full( $key ) ) {
			return $html;
		}

		$label = trim( wp_strip_all_tags( $html ) );
		$icon  = preg_match( '#<svg.*?</svg>#s', $html, $m ) ? $m[0] : '';

		return '<span class="sb-emailsig-limit-lock" data-sb-tip="' . esc_attr( self::message( $key ) ) . '" tabindex="0"><span class="button button-primary disabled" aria-disabled="true">' . $icon . esc_html( $label ) . '</span></span>';
	}

	/** Templates over the limit, newest: ids SB_EmailSig_Templates::all() leaves out. */
	public static function templates_over() {
		$limit = self::limit( 'templates' );

		return $limit > 0 ? array_slice( self::templates(), $limit ) : array();
	}

	/** However a person is saved (the Active switch, their page, an import): not made active past the limit. */
	public static function hold_person( $data, $postarr ) {
		if ( SB_EMAILSIG_CPT !== ( $data['post_type'] ?? '' ) || 'publish' !== ( $data['post_status'] ?? '' ) ) {
			return $data;
		}

		$id  = (int) ( $postarr['ID'] ?? 0 );
		$was = $id ? get_post_status( $id ) : '';

		if ( 'publish' !== $was && ! self::may_be_active( $id ) ) {
			$data['post_status'] = ( ! $was || in_array( $was, array( 'trash', 'auto-draft', 'new' ), true ) ) ? 'draft' : $was;
		}

		return $data;
	}

	/** Only the oldest people, up to the limit, can be active (one not saved yet isn't counted). */
	public static function may_be_active( $id ) {
		$limit = self::limit( 'people' );

		if ( $limit < 1 ) {
			return true;
		}

		$all = self::people();
		$at  = $id ? array_search( (int) $id, $all, true ) : false;

		return false === $at ? count( $all ) < $limit : $at < $limit;
	}

	/** No new person once every slot is used, however they'd be added (a page, an import, a request). */
	public static function stop_new_person( $stop, $postarr ) {
		if ( ! $stop && SB_EMAILSIG_CPT === ( $postarr['post_type'] ?? '' ) && empty( $postarr['ID'] ) && 'auto-draft' !== ( $postarr['post_status'] ?? '' ) && self::full( 'people' ) ) {
			return true;
		}

		return $stop;
	}

	/**
	 * The limits changed (on the hub): the newest over each go off, people
	 * Inactive and companies switched off. Templates need nothing: all()
	 * leaves them out while they're over.
	 */
	public static function apply() {
		if ( ! class_exists( 'SB_Tweaks_Policy' ) ) {
			return;
		}

		$limits = SB_Tweaks_Policy::limits( self::MODULE );
		$mark   = md5( (string) wp_json_encode( $limits ) );

		if ( get_option( self::APPLIED ) === $mark ) {
			return;
		}

		update_option( self::APPLIED, $mark, true );

		$people = (int) ( $limits['people'] ?? 0 );

		if ( $people > 0 ) {
			foreach ( array_slice( self::people(), $people ) as $id ) {
				if ( get_post_status( $id ) !== 'publish' ) {
					continue;
				}

				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'draft',
					)
				);
			}
		}

		$companies = (int) ( $limits['companies'] ?? 0 );

		if ( $companies > 0 ) {
			foreach ( array_slice( self::companies(), $companies ) as $co ) {
				SB_EmailSig_Switch::set( $co, false );
			}
		}
	}
}
