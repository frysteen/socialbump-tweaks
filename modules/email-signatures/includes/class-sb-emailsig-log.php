<?php
/**
 * The activity log (Sam, 31 October 2026): who did what, and when, to
 * people, companies, templates and Settings. One row per action in its own
 * table (TABLE), kept for KEEP_DAYS, shown on the Activity page (render()).
 *
 * Most of it is caught from WordPress itself, so every way in is covered:
 * a person, company or template added, deleted or restored, and a person
 * made Active or Inactive (transition_post_status); deleted for good
 * (before_delete_post); edited on its page, and Settings saved
 * (acf/save_post). The rest is logged where it happens: styles and layout
 * (SB_EmailSig_Styles), brand colours (SB_EmailSig_Companies), emails sent
 * and signatures copied (SB_EmailSig_Notify), and requests from the team
 * page (SB_EmailSig_Request sets $via).
 *
 * Each row's id is also the site's change number (REV, autoloaded):
 * SB_EmailSig_Live tells open pages to catch up when it moves on.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Log {

	/** The table, after $wpdb->prefix. */
	const TABLE = 'sb_emailsig_log';

	/** Its version: a new one runs dbDelta(). */
	const DB_VERSION = 1;
	const DB_OPTION  = 'sb_emailsig_log_db';

	/** The latest row's id: what open pages compare against (SB_EmailSig_Live). */
	const REV = 'sb_emailsig_rev';

	/** How long rows are kept. */
	const KEEP_DAYS = 365;

	/** The daily tidy up. */
	const CRON = 'sb_emailsig_log_prune';

	/** Rows a page of the Activity page. */
	const PER_PAGE = 50;

	/** How the next person added came in: '' (WordPress), 'request' (the team page form). */
	public static $via = '';

	/** People added in this request: their save that follows isn't an edit too. */
	private static $added = array();

	public static function init() {
		// The module may boot during init: then straight away.
		if ( did_action( 'init' ) ) {
			self::install();
		} else {
			add_action( 'init', array( __CLASS__, 'install' ), 5 );
		}
		add_action( 'transition_post_status', array( __CLASS__, 'transition' ), 20, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'deleting' ), 5, 2 );
		add_action( 'acf/save_post', array( __CLASS__, 'acf_saved' ), 99 );
		add_action( self::CRON, array( __CLASS__, 'prune' ) );
	}

	public static function table() {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/** The table (once per version), and the daily tidy up. */
	public static function install() {
		if ( (int) get_option( self::DB_OPTION, 0 ) !== self::DB_VERSION ) {
			global $wpdb;

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			dbDelta(
				'CREATE TABLE ' . self::table() . " (
					id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					at datetime NOT NULL,
					user_id bigint(20) unsigned NOT NULL DEFAULT 0,
					who varchar(190) NOT NULL DEFAULT '',
					action varchar(40) NOT NULL DEFAULT '',
					type varchar(20) NOT NULL DEFAULT '',
					object_id bigint(20) unsigned NOT NULL DEFAULT 0,
					name varchar(190) NOT NULL DEFAULT '',
					company_id bigint(20) unsigned NOT NULL DEFAULT 0,
					detail varchar(190) NOT NULL DEFAULT '',
					PRIMARY KEY  (id),
					KEY at (at),
					KEY type (type),
					KEY company_id (company_id)
				) " . $wpdb->get_charset_collate() . ';'
			);

			update_option( self::DB_OPTION, self::DB_VERSION, true );
		}

		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * Log an action: $type person, company, template or settings; $id the
	 * post (0 for settings); $name what it was called then (looked up when
	 * blank); $detail anything more; $who, someone other than the user
	 * logged in (a person copying their own signature).
	 */
	public static function add( $action, $type, $id = 0, $name = '', $detail = '', $who = '' ) {
		global $wpdb;

		$id   = (int) $id;
		$user = wp_get_current_user();

		if ( $name === '' && $id ) {
			$name = self::name_of( $type, $id );
		}

		if ( $who === '' ) {
			$who = $user && $user->exists() ? (string) $user->display_name : 'Visitor';
		}

		$co = 0;
		if ( $type === 'person' && $id ) {
			$co = (int) SB_EmailSig_Company::of_person( $id );
		} elseif ( $type === 'company' ) {
			$co = $id;
		}

		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- our own table.
			self::table(),
			array(
				'at'         => current_time( 'mysql', true ),
				'user_id'    => $user ? (int) $user->ID : 0,
				'who'        => mb_substr( $who, 0, 190 ),
				'action'     => $action,
				'type'       => $type,
				'object_id'  => $id,
				'name'       => mb_substr( (string) $name, 0, 190 ),
				'company_id' => $co,
				'detail'     => mb_substr( (string) $detail, 0, 190 ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s' )
		);

		if ( $ok ) {
			update_option( self::REV, (int) $wpdb->insert_id, true );
		}
	}

	/** What something is called now. */
	private static function name_of( $type, $id ) {
		if ( $type === 'person' ) {
			$name = trim( (string) get_field( 'sb_emailsig_name', $id ) );

			return $name !== '' ? $name : get_the_title( $id );
		}

		if ( $type === 'company' ) {
			return SB_EmailSig_Company::name( $id );
		}

		if ( $type === 'template' ) {
			$tpl = SB_EmailSig_Templates::get( (string) $id );

			return $tpl ? $tpl['name'] : get_the_title( $id );
		}

		return '';
	}

	/** Which of ours a post type is: person, company, template, or ''. */
	private static function type_of( $post_type ) {
		$types = array(
			SB_EMAILSIG_CPT                => 'person',
			SB_EmailSig_Company::CPT       => 'company',
			SB_EmailSig_Templates::CPT     => 'template',
		);

		return $types[ $post_type ] ?? '';
	}

	/** Added, deleted, restored, and a person made Active or Inactive. */
	public static function transition( $new, $old, $post ) {
		if ( ! $post instanceof WP_Post || $new === $old || $new === 'auto-draft' || $new === 'inherit' ) {
			return;
		}

		$type = self::type_of( $post->post_type );

		if ( $type === '' ) {
			return;
		}

		if ( $old === 'new' || $old === 'auto-draft' ) {
			self::$added[] = (int) $post->ID;
			self::add( $type === 'person' && self::$via === 'request' ? 'submitted' : 'added', $type, $post->ID, $post->post_title );

			return;
		}

		if ( $new === 'trash' ) {
			self::add( 'deleted', $type, $post->ID );
		} elseif ( $old === 'trash' ) {
			self::add( 'restored', $type, $post->ID );
		} elseif ( $type === 'person' && $new === 'publish' ) {
			self::add( 'activated', $type, $post->ID );
		} elseif ( $type === 'person' && $old === 'publish' ) {
			self::add( 'deactivated', $type, $post->ID );
		}
	}

	/** Deleted for good (a person), or deleted without the Trash (a company or template). */
	public static function deleting( $post_id, $post = null ) {
		$post = $post instanceof WP_Post ? $post : get_post( $post_id );
		$type = $post ? self::type_of( $post->post_type ) : '';

		if ( $type === '' || $post->post_status === 'auto-draft' ) {
			return;
		}

		if ( $type === 'person' ) {
			self::add( 'purged', $type, $post->ID );
		} elseif ( $post->post_status !== 'trash' ) {
			self::add( 'deleted', $type, $post->ID );
		}
	}

	/** A person or company edited on its page, or Settings saved. */
	public static function acf_saved( $post_id ) {
		if ( $post_id === 'options' || $post_id === 'option' ) {
			if ( class_exists( 'SB_EmailSig_Settings' ) && SB_EmailSig_Settings::is_settings_request() ) {
				self::add( 'edited', 'settings', 0, 'Settings' );
			}

			return;
		}

		$post_id = (int) $post_id;
		$type    = $post_id ? self::type_of( get_post_type( $post_id ) ) : '';

		if ( ( $type === 'person' || $type === 'company' ) && ! in_array( $post_id, self::$added, true ) ) {
			self::add( 'edited', $type, $post_id );
		}
	}

	/** Rows older than KEEP_DAYS go. */
	public static function prune() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE at < %s', gmdate( 'Y-m-d H:i:s', time() - self::KEEP_DAYS * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB -- our own table.
	}

	/** Rows after $since (an id), oldest first, at most $limit. */
	public static function since( $since, $limit = 200 ) {
		global $wpdb;

		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, type, object_id, action FROM ' . self::table() . ' WHERE id > %d ORDER BY id ASC LIMIT %d', (int) $since, (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB -- our own table.
	}

	/* ------------------------------------------------------------------ *
	 * The Activity page
	 * ------------------------------------------------------------------ */

	/** Types for the filter, as the page names them. */
	const TYPES = array(
		'person'   => 'People',
		'company'  => 'Companies',
		'template' => 'Templates',
		'settings' => 'Settings',
	);

	/** A row in words: what was done. */
	public static function sentence( array $row ) {
		$n = $row['name'] !== '' ? $row['name'] : 'someone';

		switch ( $row['type'] . '.' . $row['action'] ) {
			case 'person.added':
				return 'Added ' . $n;
			case 'person.submitted':
				return 'Submitted ' . $n . ' for review';
			case 'person.edited':
				return 'Edited ' . $n;
			case 'person.activated':
				return 'Made ' . $n . ' Active';
			case 'person.deactivated':
				return 'Made ' . $n . ' Inactive';
			case 'person.deleted':
				return 'Deleted ' . $n;
			case 'person.restored':
				return 'Restored ' . $n;
			case 'person.purged':
				return 'Deleted ' . $n . ' for good';
			case 'person.emailed':
				return $row['detail'] === 'updated' ? 'Emailed ' . $n . ' that their signature changed' : 'Emailed ' . $n . ' their signature link';
			case 'person.copied':
				return 'Copied ' . ( $row['who'] === $row['name'] ? 'their' : $n . '\'s' ) . ' signature' . ( $row['detail'] !== '' ? ' (' . $row['detail'] . ')' : '' );
			case 'company.added':
				return 'Added company ' . $n;
			case 'company.edited':
				return 'Edited company ' . $n;
			case 'company.branding':
				return 'Changed ' . $n . '\'s brand colours';
			case 'company.deleted':
				return 'Deleted company ' . $n;
			case 'company.restored':
				return 'Restored company ' . $n;
			case 'company.disabled':
				return 'Switched company ' . $n . ' off';
			case 'company.enabled':
				return 'Switched company ' . $n . ' on';
			case 'template.added':
				return 'Added template ' . $n;
			case 'template.styles':
				return 'Edited ' . $n . '\'s styles';
			case 'template.layout':
				return 'Edited ' . $n . '\'s layout';
			case 'template.deleted':
				return 'Deleted template ' . $n;
			case 'template.restored':
				return 'Restored template ' . $n;
			case 'settings.edited':
				return 'Saved Settings';
		}

		return ucfirst( $row['action'] ) . ' ' . $n;
	}

	/** The Activity page: filters, then the rows, newest first. */
	public static function render() {
		global $wpdb;

		// phpcs:disable WordPress.Security.NonceVerification -- filters only.
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		$co   = isset( $_GET['company'] ) ? absint( $_GET['company'] ) : 0;
		$q    = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$pg   = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1;
		// phpcs:enable

		$type = isset( self::TYPES[ $type ] ) ? $type : '';

		$where = array( '1=1' );
		$args  = array();
		if ( $type !== '' ) {
			$where[] = 'type = %s';
			$args[]  = $type;
		}
		if ( $co ) {
			$where[] = 'company_id = %d';
			$args[]  = $co;
		}
		if ( $q !== '' ) {
			$like    = '%' . $wpdb->esc_like( $q ) . '%';
			$where[] = '(name LIKE %s OR who LIKE %s)';
			$args[]  = $like;
			$args[]  = $like;
		}

		$sql   = 'FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $where );
		$total = (int) $wpdb->get_var( $args ? $wpdb->prepare( 'SELECT COUNT(*) ' . $sql, $args ) : 'SELECT COUNT(*) ' . $sql ); // phpcs:ignore WordPress.DB -- our own table, prepared.
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * ' . $sql . ' ORDER BY id DESC LIMIT %d OFFSET %d', array_merge( $args, array( self::PER_PAGE, ( $pg - 1 ) * self::PER_PAGE ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB -- our own table, prepared.
		$base  = admin_url( 'admin.php?page=' . SB_EmailSig_Module::SLUG . '-activity' );

		echo '<section class="sb-tweaks-section sb-emailsig-report sb-emailsig-activity">';

		echo '<form method="get" class="sb-emailsig-report__filters" data-sb-emailsig-activity>';
		echo '<input type="hidden" name="page" value="' . esc_attr( SB_EmailSig_Module::SLUG . '-activity' ) . '">';
		echo '<input type="search" name="q" value="' . esc_attr( $q ) . '" placeholder="Find a name" aria-label="Find a name">';

		echo '<select name="type" aria-label="Type"><option value="">Everything</option>';
		foreach ( self::TYPES as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $type, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		$companies = SB_EmailSig_Company::all();
		if ( count( $companies ) > 1 ) {
			echo '<select name="company" aria-label="Company"><option value="0">Every company</option>';
			foreach ( $companies as $id ) {
				echo '<option value="' . (int) $id . '"' . selected( $co, $id, false ) . '>' . esc_html( SB_EmailSig_Company::name( $id ) ) . '</option>';
			}
			echo '</select>';
		}

		if ( $type !== '' || $co || $q !== '' ) {
			echo '<a class="button sb-emailsig-report__clear" href="' . esc_url( $base ) . '">Clear filters</a>';
		}
		echo '</form>';
		echo '<script>document.addEventListener("change",function(e){var f=e.target.closest("[data-sb-emailsig-activity]");if(f&&e.target.tagName==="SELECT")f.submit();});</script>';

		if ( ! $rows ) {
			echo '<div class="sb-tweaks-card sb-emailsig-report__empty"><p>' . esc_html( $total || $type !== '' || $co || $q !== '' ? 'Nothing for these filters.' : 'Nothing yet. Changes show here as they happen.' ) . '</p></div></section>';

			return;
		}

		echo '<div class="sb-tweaks-card"><table class="widefat sb-emailsig-activity__table"><thead><tr><th>When</th><th>Who</th><th>What</th>' . ( count( $companies ) > 1 ? '<th>Company</th>' : '' ) . '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$at = strtotime( $row['at'] . ' UTC' );

			echo '<tr><td class="sb-emailsig-activity__when" title="' . esc_attr( wp_date( 'j M Y, g:ia', $at ) ) . '">' . esc_html( human_time_diff( $at ) . ' ago' ) . '<span>' . esc_html( wp_date( 'j M Y, g:ia', $at ) ) . '</span></td>';
			echo '<td>' . esc_html( $row['who'] ) . '</td>';
			echo '<td>' . esc_html( self::sentence( $row ) ) . '</td>';

			if ( count( $companies ) > 1 ) {
				echo '<td>' . esc_html( (int) $row['company_id'] ? SB_EmailSig_Company::name( (int) $row['company_id'] ) : '' ) . '</td>';
			}

			echo '</tr>';
		}

		echo '</tbody></table></div>';

		$pages = (int) ceil( $total / self::PER_PAGE );
		if ( $pages > 1 ) {
			$keep = array_filter(
				array(
					'type'    => $type,
					'company' => $co,
					'q'       => $q,
				)
			);

			echo '<p class="sb-emailsig-activity__pages">';
			if ( $pg > 1 ) {
				echo '<a class="button" href="' . esc_url( add_query_arg( $keep + array( 'pg' => $pg - 1 ), $base ) ) . '">Newer</a> ';
			}
			echo '<span>Page ' . (int) $pg . ' of ' . (int) $pages . '</span>';
			if ( $pg < $pages ) {
				echo ' <a class="button" href="' . esc_url( add_query_arg( $keep + array( 'pg' => $pg + 1 ), $base ) ) . '">Older</a>';
			}
			echo '</p>';
		}

		echo '<p class="sb-emailsig-report__note">Kept for 12 months.</p>';
		echo '</section>';
	}
}
