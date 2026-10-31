<?php
/**
 * Live updating (Sam, 31 October 2026): the Signatures page, the team pages
 * and a person's page keep up with changes made anywhere, in any browser.
 *
 * Each open page asks every 5 seconds while it's being used (every 30 once
 * it has sat for 3 minutes, not at all while its tab is hidden, and straight
 * away when it's shown again) whether the site's change number
 * (SB_EmailSig_Log::REV, the activity log's latest row) has moved on from
 * the one it has. Nothing new is one option read. When it has, the log rows
 * since say what changed: a person's card is drawn again, as this page draws
 * it, and put in its section (or taken out); a change to a company, template
 * or Settings draws every card on the page again; a person's page reloads
 * when they or their look changed. Tabs in the same browser tell each other
 * straight away (assets/live.js, BroadcastChannel).
 *
 * A page only hears what it could show: the Signatures page and everyone's
 * team page need someone who manages signatures; a company's team page its
 * key (inactive and deleted people only for those who manage); a person's
 * page their key.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Live {

	/** More rows than this since a page last asked: it reloads instead. */
	const MAX_ROWS = 300;

	public static function init() {
		add_action( 'wp_ajax_sb_emailsig_live', array( __CLASS__, 'poll' ) );
		add_action( 'wp_ajax_nopriv_sb_emailsig_live', array( __CLASS__, 'poll' ) );
	}

	/** The page's settings for assets/live.js: $ctx says which page it is. */
	public static function config( array $ctx ) {
		return array(
			'ajax' => admin_url( 'admin-ajax.php' ),
			'rev'  => (int) get_option( SB_EmailSig_Log::REV, 0 ),
			'ctx'  => $ctx,
		);
	}

	/** On the front pages (no wp_enqueue): its settings and script. */
	public static function script( array $ctx ) {
		echo '<script>var sbEmailsigLive = ' . wp_json_encode( self::config( $ctx ) ) . ';</script>' . "\n";
		echo '<script src="' . esc_url( SB_EMAILSIG_URL . 'assets/live.js?ver=' . SB_EmailSig_Module::asset_version( 'assets/live.js' ) ) . '"></script>' . "\n";
	}

	/** What changed since the page's number, for that page. */
	public static function poll() {
		// phpcs:disable WordPress.Security.NonceVerification -- read only; each page's own key or the user's rights are the check.
		$since = isset( $_POST['since'] ) ? absint( $_POST['since'] ) : 0;
		$where = isset( $_POST['where'] ) ? sanitize_key( wp_unslash( $_POST['where'] ) ) : '';
		$key   = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		$pid   = isset( $_POST['person'] ) ? absint( $_POST['person'] ) : 0;
		$ids   = isset( $_POST['ids'] ) ? array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['ids'] ) ) ) ) ) : array();
		// phpcs:enable

		$manage = SB_EmailSig_Admin::can_manage();
		$front  = true;
		$co     = 0;

		if ( $where === 'admin' ) {
			if ( ! $manage ) {
				wp_send_json_error( null, 403 );
			}
			$front = false;
		} elseif ( $where === 'team' ) {
			$co = SB_EmailSig_Page::team_company( $key );
			if ( $co < 0 ) {
				wp_send_json_error( null, 403 );
			}
		} elseif ( $where === 'person' ) {
			if ( ! $pid || get_post_type( $pid ) !== SB_EMAILSIG_CPT || $key === '' || ! hash_equals( SB_EmailSig_CPT::get_key( $pid ), $key ) ) {
				wp_send_json_error( null, 403 );
			}
		} else {
			wp_send_json_error( null, 400 );
		}

		$rev = (int) get_option( SB_EmailSig_Log::REV, 0 );

		if ( $since >= $rev ) {
			wp_send_json_success( array( 'rev' => $rev ) );
		}

		$rows = SB_EmailSig_Log::since( $since, self::MAX_ROWS + 1 );

		if ( count( $rows ) > self::MAX_ROWS ) {
			wp_send_json_success(
				array(
					'rev'    => $rev,
					'reload' => true,
				)
			);
		}

		$changed = array();
		$looks   = array(); // A person's page reloads for these, not for a copy or an email (their own copy would reload it).
		$all     = false;
		$reload  = false; // A company switched on or off (SB_EmailSig_Switch): its bar, notes and every card change.

		foreach ( $rows as $row ) {
			if ( $row['type'] === 'company' && in_array( $row['action'], array( 'enabled', 'disabled' ), true ) ) {
				$reload = true;
			}

			if ( $row['type'] === 'person' ) {
				$changed[] = (int) $row['object_id'];
				if ( ! in_array( $row['action'], array( 'copied', 'emailed' ), true ) ) {
					$looks[] = (int) $row['object_id'];
				}
			} else {
				$all = true;
			}
			$rev = max( $rev, (int) $row['id'] );
		}

		$changed = array_values( array_unique( $changed ) );

		if ( $reload && $where !== 'person' ) {
			wp_send_json_success(
				array(
					'rev'    => $rev,
					'reload' => true,
				)
			);
		}

		if ( $where === 'person' ) {
			wp_send_json_success(
				array(
					'rev'    => $rev,
					'reload' => $all || in_array( $pid, $looks, true ),
				)
			);
		}

		$cards = array();
		foreach ( array_unique( array_merge( $changed, $all ? $ids : array() ) ) as $id ) {
			$cards[] = self::card( (int) $id, $front, $manage, $co );
		}

		wp_send_json_success(
			array(
				'rev'   => $rev,
				'cards' => $cards,
			)
		);
	}

	/** A person's card as this page draws it, and its section; 'gone' when it doesn't show here. */
	private static function card( $id, $front, $manage, $co ) {
		$gone = array(
			'id'      => $id,
			'section' => 'gone',
		);

		if ( get_post_type( $id ) !== SB_EMAILSIG_CPT || trim( (string) get_field( 'sb_emailsig_name', $id ) ) === '' ) {
			return $gone;
		}

		$sections = array(
			'publish' => 'active',
			'draft'   => 'inactive',
			'trash'   => 'deleted',
		);
		$section  = $sections[ get_post_status( $id ) ] ?? 'gone';
		$pco      = (int) SB_EmailSig_Company::of_person( $id );

		if ( $section === 'gone' || ( ! $manage && ( $section !== 'active' || SB_EmailSig_Switch::is_off( $pco ) ) ) || ( $co && $pco !== $co ) ) {
			return $gone;
		}

		ob_start();
		if ( $section === 'deleted' ) {
			SB_EmailSig_Admin::deleted_card( $id, $front );
		} else {
			SB_EmailSig_Admin::card( $id, $front );
		}

		return array(
			'id'      => $id,
			'section' => $section,
			'co'      => $pco,
			'html'    => ob_get_clean(),
		);
	}
}
