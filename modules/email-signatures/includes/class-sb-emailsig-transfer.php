<?php
/**
 * People export and import, as a CSV file (one row per person) for Excel,
 * Numbers or Google Sheets. On the Signatures page, for administrators.
 *
 * Columns: Name, Qualifications, Job Title, Email, Mobile, Active (Yes or
 * No), Main Template and Extra Templates (template names, extras split by
 * "; "), Hide Company Lines (Yes or No), Photo (its address), Photo Crop
 * (x, y, w, h as fractions of the photo), then Info 1 Icon, Info 1 Label,
 * Info 1 Text, Info 1 Link and so on, as many as anyone has. Left out, as
 * they belong to one site: the Staff Page, the page key, and the copy and
 * click history. Name, Job Title and the rest are written as they show
 * (staff page tokens filled in). A cell starting = + - or @ gets a leading
 * apostrophe so spreadsheets don't run it as a formula; import takes it off.
 *
 * Import reads the file into a preview first (kept for an hour for the user
 * who sent it): each row is new, matches people already here by email, or
 * is skipped (no valid email, or no name for a new person). A match can be
 * updated, skipped or added as a new person, picked per row: one match
 * starts on Update, more than one (staff sharing an email) on Skip. Only
 * then is anything saved. An update changes only the cells that have
 * something in them and differ from what shows now (so a token stays a
 * token), the Info lines only when the row has some (a line the same as
 * one already there keeps its id, and so its click history). A photo at an
 * address on this site is used as it is, crop and all; one from elsewhere
 * is downloaded into the media library, its crop applied when given. Import
 * never deletes anyone.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Transfer {

	/** The fixed columns, by key. */
	const COLS = array(
		'first_name'     => 'First Name',
		'last_name'      => 'Last Name',
		'qualifications' => 'Qualifications',
		'job_title'      => 'Job Title',
		'email'          => 'Email',
		'mobile'         => 'Mobile',
		'active'         => 'Active',
		'template'       => 'Main Template',
		'extra'          => 'Extra Templates',
		'hide'           => 'Hide Company Lines',
		'photo'          => 'Photo',
		'crop'           => 'Photo Crop',
	);

	/** The text fields, by column key: their ACF field keys. */
	const TEXT_FIELDS = array(
		'first_name'     => 'field_sb_emailsig_first_name',
		'last_name'      => 'field_sb_emailsig_last_name',
		'qualifications' => 'field_sb_emailsig_qualifications',
		'job_title'      => 'field_sb_emailsig_job_title',
		'email'          => 'field_sb_emailsig_email',
		'mobile'         => 'field_sb_emailsig_mobile',
	);

	/** A preview, kept per user: rows read from the file. */
	const PREVIEW = 'sb_emailsig_people_import_';

	/** What the last import did, shown once on the Signatures page. */
	const DONE = 'sb_emailsig_people_imported_';

	const MAX_BYTES = 2000000;
	const MAX_ROWS  = 500;

	public static function init() {
		add_action( 'admin_post_sb_emailsig_people_export', array( __CLASS__, 'export' ) );
		add_action( 'admin_post_sb_emailsig_people_read', array( __CLASS__, 'read' ) );
		add_action( 'admin_post_sb_emailsig_people_import', array( __CLASS__, 'import' ) );
		add_action( 'admin_post_sb_emailsig_people_cancel', array( __CLASS__, 'cancel' ) );
	}

	private static function allowed() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to do that.' );
		}
	}

	private static function page_url( array $args = array() ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . SB_EmailSig_Module::SLUG . '-signatures' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Cells
	 * ------------------------------------------------------------------ */

	/** A cell safe to open in a spreadsheet: a leading = + - @ isn't run as a formula. */
	private static function cell( $v ) {
		$v = (string) $v;

		return ( $v !== '' && strpos( '=+-@', $v[0] ) !== false ) ? "'" . $v : $v;
	}

	/** A cell read back: trimmed, the apostrophe cell() added taken off. */
	private static function uncell( $v ) {
		$v = trim( (string) $v );

		if ( strlen( $v ) > 1 && $v[0] === "'" && strpos( '=+-@', $v[1] ) !== false ) {
			$v = substr( $v, 1 );
		}

		return $v;
	}

	/** Yes, No, or '' when the cell is blank or says neither. */
	private static function yes_no( $v ) {
		$v = strtolower( trim( (string) $v ) );

		if ( in_array( $v, array( 'yes', 'y', '1', 'true' ), true ) ) {
			return 'yes';
		}

		return in_array( $v, array( 'no', 'n', '0', 'false' ), true ) ? 'no' : '';
	}

	/** Template ids by lower case name. */
	private static function template_ids() {
		$out = array();

		foreach ( SB_EmailSig_Templates::all() as $tpl ) {
			$out[ strtolower( $tpl['name'] ) ] = $tpl['id'];
		}

		return $out;
	}

	/** A person's Additional Info List Items, as saved. */
	private static function info_lines( $post_id ) {
		$rows = get_field( 'sb_emailsig_person_info', $post_id );
		$out  = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$out[] = array(
				'icon'  => (string) ( $row['info_icon'] ?? '' ),
				'label' => (string) ( $row['info_label'] ?? '' ),
				'text'  => (string) ( $row['info_text'] ?? '' ),
				'link'  => (string) ( $row['info_link'] ?? '' ),
				'id'    => (string) ( $row['info_id'] ?? '' ),
			);
		}

		return $out;
	}

	/* ------------------------------------------------------------------ *
	 * Export
	 * ------------------------------------------------------------------ */

	public static function export_url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=sb_emailsig_people_export' ), 'sb_emailsig_people_export' );
	}

	public static function export() {
		self::allowed();
		check_admin_referer( 'sb_emailsig_people_export' );

		$people = SB_EmailSig_Admin::people( array( 'publish', 'draft' ) );
		$names  = wp_list_pluck( SB_EmailSig_Templates::all(), 'name' );
		$rows   = array();
		$most   = 0;

		foreach ( $people as $id ) {
			$row = array();

			foreach ( self::TEXT_FIELDS as $col => $key ) {
				$row[ $col ] = trim( (string) get_field( 'sb_emailsig_' . $col, $id ) );
			}

			$main   = (string) get_post_meta( $id, SB_EmailSig_Templates::PERSON_MAIN, true );
			$extras = get_post_meta( $id, SB_EmailSig_Templates::PERSON_EXTRA, true );
			$att    = (int) get_field( 'sb_emailsig_photo', $id, false );
			$crop   = json_decode( (string) get_post_meta( $id, '_sb_emailsig_crop', true ), true );

			$row['active']   = get_post_status( $id ) === 'publish' ? 'Yes' : 'No';
			$row['template'] = $names[ $main ] ?? '';
			$row['extra']    = implode( '; ', array_filter( array_map( function ( $t ) use ( $names ) {
				return $names[ (string) $t ] ?? '';
			}, is_array( $extras ) ? $extras : array() ) ) );
			$row['hide']     = get_field( 'sb_emailsig_person_info_hide', $id ) ? 'Yes' : 'No';
			$row['photo']    = $att ? (string) wp_get_attachment_url( $att ) : '';
			$row['crop']     = ( $att && is_array( $crop ) && (int) ( $crop['id'] ?? 0 ) === $att )
				? implode( ', ', array_map( function ( $k ) use ( $crop ) {
					return round( (float) $crop[ $k ], 4 );
				}, array( 'x', 'y', 'w', 'h' ) ) )
				: '';
			$row['info']     = self::info_lines( $id );

			$most   = max( $most, count( $row['info'] ) );
			$rows[] = $row;
		}

		$head = array_values( self::COLS );

		for ( $n = 1; $n <= $most; $n++ ) {
			array_push( $head, "Info $n Icon", "Info $n Label", "Info $n Text", "Info $n Link" );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="email-signature-people-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // So Excel reads it as UTF-8. phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $out, $head );

		foreach ( $rows as $row ) {
			$line = array();

			foreach ( array_keys( self::COLS ) as $col ) {
				$line[] = self::cell( $row[ $col ] );
			}

			for ( $n = 0; $n < $most; $n++ ) {
				$info = $row['info'][ $n ] ?? array();
				array_push( $line, self::cell( $info['icon'] ?? '' ), self::cell( $info['label'] ?? '' ), self::cell( $info['text'] ?? '' ), self::cell( $info['link'] ?? '' ) );
			}

			fputcsv( $out, $line );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Import: read into a preview
	 * ------------------------------------------------------------------ */

	public static function read() {
		self::allowed();
		check_admin_referer( 'sb_emailsig_people_read' );

		$fail = function ( $why ) {
			wp_safe_redirect( self::page_url( array( 'people' => $why ) ) );
			exit;
		};
		$up   = isset( $_FILES['sb_emailsig_people'] ) && is_array( $_FILES['sb_emailsig_people'] ) ? $_FILES['sb_emailsig_people'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- read as CSV and checked cell by cell.

		if ( ! $up || ( $up['error'] ?? 1 ) !== UPLOAD_ERR_OK || ! is_uploaded_file( $up['tmp_name'] ) || (int) $up['size'] > self::MAX_BYTES ) {
			$fail( 'badfile' );
		}

		$rows = self::parse( $up['tmp_name'] );

		if ( $rows === null ) {
			$fail( 'badfile' );
		}

		if ( ! $rows ) {
			$fail( 'empty' );
		}

		set_transient( self::PREVIEW . get_current_user_id(), $rows, HOUR_IN_SECONDS );

		wp_safe_redirect( self::page_url( array( 'people' => 'preview' ) ) );
		exit;
	}

	/**
	 * The file's rows, each checked: [ cells (by column key), info (lines),
	 * matches (ids with that email), status (new, match or skip), why ].
	 * null when it isn't a people file (no Email column).
	 */
	private static function parse( $path ) {
		$fh = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( ! $fh ) {
			return null;
		}

		$head = fgetcsv( $fh );

		if ( ! is_array( $head ) ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return null;
		}

		// Columns by heading, any case; the first may carry Excel's BOM.
		$head[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $head[0] );
		$cols    = array();
		$info    = array();
		$labels  = array_flip( array_map( 'strtolower', self::COLS ) );
		// A file from before first and last names has one Name column.
		$labels['name'] = 'name';

		foreach ( $head as $i => $h ) {
			$h = strtolower( trim( (string) $h ) );

			if ( isset( $labels[ $h ] ) ) {
				$cols[ $labels[ $h ] ] = $i;
			} elseif ( preg_match( '/^info (\d{1,2}) (icon|label|text|link)$/', $h, $m ) ) {
				$info[ (int) $m[1] ][ $m[2] ] = $i;
			}
		}

		if ( ! isset( $cols['email'] ) ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return null;
		}

		ksort( $info );

		// Everyone here, by email.
		$by_email = array();
		foreach ( SB_EmailSig_Admin::people( array( 'publish', 'draft' ) ) as $id ) {
			$e = strtolower( trim( (string) get_field( 'sb_emailsig_email', $id ) ) );
			if ( $e !== '' ) {
				$by_email[ $e ][] = (int) $id;
			}
		}

		$icons = SB_EmailSig_Fields::info_icons();
		$rows  = array();
		$n     = 1;

		while ( ( $line = fgetcsv( $fh ) ) !== false && count( $rows ) < self::MAX_ROWS ) {
			$n++;

			if ( ! is_array( $line ) || ! array_filter( array_map( 'trim', array_map( 'strval', $line ) ) ) ) {
				continue; // A blank line.
			}

			$cells = array();
			foreach ( $cols as $key => $i ) {
				$cells[ $key ] = sanitize_text_field( self::uncell( $line[ $i ] ?? '' ) );
			}

			// The full name from the first and last; one Name split when that's all there is.
			if ( ( $cells['first_name'] ?? '' ) === '' && ( $cells['last_name'] ?? '' ) === '' && ( $cells['name'] ?? '' ) !== '' ) {
				list( $cells['first_name'], $cells['last_name'] ) = SB_EmailSig_Names::split( $cells['name'] );
			}
			$cells['name'] = SB_EmailSig_Names::join( $cells['first_name'] ?? '', $cells['last_name'] ?? '' );

			$lines = array();
			foreach ( $info as $parts ) {
				$l = array();
				foreach ( array( 'icon', 'label', 'text', 'link' ) as $p ) {
					$l[ $p ] = isset( $parts[ $p ] ) ? sanitize_text_field( self::uncell( $line[ $parts[ $p ] ] ?? '' ) ) : '';
				}
				if ( $l['text'] === '' ) {
					continue;
				}
				$l['icon']  = isset( $icons[ $l['icon'] ] ) ? $l['icon'] : '';
				$l['label'] = $l['label'] !== '' ? $l['label'] : $l['text'];
				$l['link']  = SB_EmailSig_Fields::info_link_valid( true, $l['link'] ) === true ? $l['link'] : '';
				$lines[]    = $l;
			}

			$email   = strtolower( $cells['email'] ?? '' );
			$matches = $by_email[ $email ] ?? array();
			$status  = $matches ? 'match' : 'new';
			$why     = '';

			if ( ! is_email( $email ) ) {
				$status = 'skip';
				$why    = 'No valid email.';
			} elseif ( ! $matches && ( $cells['name'] ?? '' ) === '' ) {
				$status = 'skip';
				$why    = 'No name.';
			}

			$rows[] = array(
				'line'    => $n,
				'cells'   => $cells,
				'info'    => $lines,
				'matches' => $matches,
				'status'  => $status,
				'why'     => $why,
				'notes'   => self::notes( $cells ),
			);
		}

		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		return $rows;
	}

	/** Things worth knowing about a row before it's imported. */
	private static function notes( array $cells ) {
		$notes = array();
		$ids   = self::template_ids();

		foreach ( array( 'template' => 'Main Template', 'extra' => 'Extra Templates' ) as $col => $label ) {
			foreach ( array_filter( array_map( 'trim', explode( ';', $cells[ $col ] ?? '' ) ) ) as $name ) {
				if ( ! isset( $ids[ strtolower( $name ) ] ) ) {
					$notes[] = 'No template called ' . $name . ' here.';
				}
			}
		}

		$photo = $cells['photo'] ?? '';
		if ( $photo !== '' ) {
			if ( ! wp_http_validate_url( $photo ) ) {
				$notes[] = 'The photo address isn\'t a web address.';
			} elseif ( ! attachment_url_to_postid( $photo ) ) {
				$notes[] = 'The photo will be downloaded.';
			}
		}

		return $notes;
	}

	/* ------------------------------------------------------------------ *
	 * Preview (the Signatures page, ?people=preview)
	 * ------------------------------------------------------------------ */

	public static function previewing() {
		return isset( $_GET['people'] ) && $_GET['people'] === 'preview' && current_user_can( 'manage_options' ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/** The Export and Import row at the top of the Signatures page, with any notice. */
	public static function render_bar() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$state = isset( $_GET['people'] ) ? sanitize_key( wp_unslash( $_GET['people'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$fails = array(
			'badfile' => 'That file isn\'t a people CSV. It needs an Email column.',
			'empty'   => 'That file has no people in it.',
			'expired' => 'That preview has expired. Import the file again.',
		);

		if ( isset( $fails[ $state ] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $fails[ $state ] ) . '</p></div>';
		} elseif ( $state === 'done' ) {
			$done = get_transient( self::DONE . get_current_user_id() );
			delete_transient( self::DONE . get_current_user_id() );

			if ( is_array( $done ) ) {
				$msg = sprintf( 'Imported: %d added, %d updated, %d skipped.', $done['added'], $done['updated'], $done['skipped'] );
				if ( ! empty( $done['photos'] ) ) {
					$msg .= ' ' . sprintf( _n( '%d photo couldn\'t be downloaded.', '%d photos couldn\'t be downloaded.', $done['photos'] ), $done['photos'] );
				}
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
			}
		}

		// Import picks the file and goes straight to the preview: nothing is
		// saved until Import is pressed there (list.js).
		echo '<div class="sb-ui-bar__group sb-emailsig-transfer"><span class="sb-ui-bar__label">People file</span>';
		echo '<a class="button button-small" href="' . esc_url( self::export_url() ) . '" data-sb-tip="Download everyone as a CSV">' . SB_EmailSig_Admin::icon( 'down' ) . 'Export</a>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-sb-emailsig-import>';
		echo '<input type="hidden" name="action" value="sb_emailsig_people_read">';
		wp_nonce_field( 'sb_emailsig_people_read' );
		echo '<input type="file" name="sb_emailsig_people" accept=".csv,text/csv" required aria-label="People CSV file" class="screen-reader-text" tabindex="-1">';
		echo '<button type="button" class="button button-small" data-sb-emailsig-import-pick data-sb-tip="Check a CSV before adding anyone">' . SB_EmailSig_Admin::icon( 'up' ) . 'Import</button>';
		echo '</form></div>';
	}

	public static function render_preview() {
		$rows = get_transient( self::PREVIEW . get_current_user_id() );

		if ( ! is_array( $rows ) ) {
			echo '<div class="notice notice-error"><p>That preview has expired. Import the file again.</p></div>';
			echo '<p><a class="button" href="' . esc_url( self::page_url() ) . '">Back to Signatures</a></p>';

			return;
		}

		$count = array( 'new' => 0, 'match' => 0, 'skip' => 0 );
		foreach ( $rows as $row ) {
			$count[ $row['status'] ]++;
		}

		echo '<section class="sb-tweaks-section sb-emailsig-import-preview"><div class="sb-tweaks-section__head"><h2>Import people</h2>';
		echo '<p>' . esc_html( sprintf( '%d new, %d already here, %d skipped. Nothing is saved until you press Import.', $count['new'], $count['match'], $count['skip'] ) ) . '</p></div>';

		if ( $count['match'] ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( _n( '%d row has an email already used here.', '%d rows have an email already used here.', $count['match'] ), $count['match'] ) . ' Choose what happens to each. Staff can share an email, so check before updating.' ) . '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="sb_emailsig_people_import">';
		wp_nonce_field( 'sb_emailsig_people_import' );

		echo '<table class="widefat striped sb-emailsig-import-table"><thead><tr><th>Line</th><th>Name</th><th>Email</th><th>What happens</th><th>Notes</th></tr></thead><tbody>';

		foreach ( $rows as $i => $row ) {
			echo '<tr><td>' . (int) $row['line'] . '</td>';
			echo '<td>' . esc_html( $row['cells']['name'] ?? '' ) . '</td>';
			echo '<td>' . esc_html( $row['cells']['email'] ?? '' ) . '</td><td>';

			if ( $row['status'] === 'skip' ) {
				echo '<strong>Skipped.</strong> ' . esc_html( $row['why'] );
			} elseif ( $row['status'] === 'new' ) {
				echo 'Added as a new person';
			} else {
				$one = count( $row['matches'] ) === 1;
				echo '<select name="choice[' . (int) $i . ']" aria-label="What happens to line ' . (int) $row['line'] . '">';
				foreach ( $row['matches'] as $id ) {
					$who = trim( (string) get_field( 'sb_emailsig_name', $id ) ) . ( get_post_status( $id ) === 'publish' ? '' : ' (not active)' );
					echo '<option value="update:' . (int) $id . '"' . selected( $one, true, false ) . '>Update ' . esc_html( $who ) . '</option>';
				}
				echo '<option value="skip"' . selected( $one, false, false ) . '>Skip</option>';
				echo '<option value="new">Add as a new person</option>';
				echo '</select>';
			}

			echo '</td><td>' . esc_html( implode( ' ', $row['notes'] ) ) . '</td></tr>';
		}

		echo '</tbody></table>';
		echo '<p class="sb-emailsig-import-actions"><button type="submit" class="button button-primary">Import</button> ';
		echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sb_emailsig_people_cancel' ), 'sb_emailsig_people_cancel' ) ) . '">Cancel</a></p>';
		echo '</form></section>';
	}

	public static function cancel() {
		self::allowed();
		check_admin_referer( 'sb_emailsig_people_cancel' );
		delete_transient( self::PREVIEW . get_current_user_id() );

		wp_safe_redirect( self::page_url() );
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Import: save
	 * ------------------------------------------------------------------ */

	public static function import() {
		self::allowed();
		check_admin_referer( 'sb_emailsig_people_import' );

		$rows = get_transient( self::PREVIEW . get_current_user_id() );

		if ( ! is_array( $rows ) ) {
			wp_safe_redirect( self::page_url( array( 'people' => 'expired' ) ) );
			exit;
		}

		$choice = isset( $_POST['choice'] ) && is_array( $_POST['choice'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['choice'] ) ) : array();
		$done   = array( 'added' => 0, 'updated' => 0, 'skipped' => 0, 'photos' => 0 );

		foreach ( $rows as $i => $row ) {
			$do = $row['status'];

			if ( $do === 'match' ) {
				$c  = (string) ( $choice[ $i ] ?? 'skip' );
				$do = 'skip';

				if ( $c === 'new' && ( $row['cells']['name'] ?? '' ) !== '' ) {
					$do = 'new';
				} elseif ( preg_match( '/^update:(\d+)$/', $c, $m ) && in_array( (int) $m[1], $row['matches'], true ) ) {
					$do = 'update';
					$id = (int) $m[1];
				}
			}

			if ( $do === 'new' ) {
				$id = self::add( $row );
				if ( ! $id ) {
					$done['skipped']++;
					continue;
				}
				$done['added']++;
			} elseif ( $do === 'update' && get_post_type( $id ) === SB_EMAILSIG_CPT ) {
				$done['updated']++;
			} else {
				$done['skipped']++;
				continue;
			}

			if ( ! self::fill( $id, $row, $do === 'new' ) ) {
				$done['photos']++;
			}
		}

		delete_transient( self::PREVIEW . get_current_user_id() );
		set_transient( self::DONE . get_current_user_id(), $done, 10 * MINUTE_IN_SECONDS );

		wp_safe_redirect( self::page_url( array( 'people' => 'done' ) ) );
		exit;
	}

	/** A new person (active unless the row says No). Their id, or 0. */
	private static function add( array $row ) {
		$id = wp_insert_post(
			array(
				'post_type'   => SB_EMAILSIG_CPT,
				'post_status' => self::yes_no( $row['cells']['active'] ?? '' ) === 'no' ? 'draft' : 'publish',
				'post_title'  => $row['cells']['name'] ?? '',
			),
			true
		);

		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}

		SB_EmailSig_CPT::ensure_key( $id );

		return (int) $id;
	}

	/**
	 * A row's cells into a person: all of them for someone new, only those
	 * with something in them that differs from what shows now for an
	 * update. False when the photo couldn't be had.
	 */
	private static function fill( $id, array $row, $is_new ) {
		$cells = $row['cells'];

		foreach ( self::TEXT_FIELDS as $col => $key ) {
			$v = $cells[ $col ] ?? '';
			if ( ( $v !== '' || $is_new ) && $v !== trim( (string) get_field( 'sb_emailsig_' . $col, $id ) ) ) {
				update_field( $key, $v, $id );
			}
		}

		// The full name from the first and last, then the title from it, as the edit screen does.
		SB_EmailSig_Names::sync( $id );
		$title = trim( (string) get_field( 'sb_emailsig_name', $id ) );
		if ( $title !== '' && $title !== get_post_field( 'post_title', $id, 'raw' ) ) {
			wp_update_post(
				array(
					'ID'         => $id,
					'post_title' => $title,
					'post_name'  => sanitize_title( $title ),
				)
			);
		}

		$active = self::yes_no( $cells['active'] ?? '' );
		if ( ! $is_new && $active !== '' ) {
			$status = $active === 'yes' ? 'publish' : 'draft';
			if ( get_post_status( $id ) !== $status ) {
				wp_update_post( array( 'ID' => $id, 'post_status' => $status ) );
			}
		}

		$hide = self::yes_no( $cells['hide'] ?? '' );
		if ( $hide !== '' ) {
			update_field( 'field_sb_emailsig_person_info_hide', $hide === 'yes' ? 1 : 0, $id );
		}

		// Templates by name; unknown names are left out.
		$ids = self::template_ids();
		if ( ( $cells['template'] ?? '' ) !== '' && isset( $ids[ strtolower( $cells['template'] ) ] ) ) {
			update_field( 'field_sb_emailsig_person_template', $ids[ strtolower( $cells['template'] ) ], $id );
		}
		if ( ( $cells['extra'] ?? '' ) !== '' ) {
			$extra = array();
			foreach ( array_filter( array_map( 'trim', explode( ';', $cells['extra'] ) ) ) as $name ) {
				if ( isset( $ids[ strtolower( $name ) ] ) ) {
					$extra[] = $ids[ strtolower( $name ) ];
				}
			}
			update_field( 'field_sb_emailsig_person_templates_extra', array_values( array_unique( $extra ) ), $id );
		}

		self::fill_info( $id, $row['info'] );

		$ok = self::fill_photo( $id, $cells['photo'] ?? '', $cells['crop'] ?? '' );

		SB_EmailSig_Photo::generate( $id );

		return $ok;
	}

	/** The row's Info lines, when it has any. A line already there keeps its id. */
	private static function fill_info( $id, array $lines ) {
		if ( ! $lines ) {
			return;
		}

		$now  = self::info_lines( $id );
		$same = function ( $a, $b ) {
			return $a['icon'] === $b['icon'] && $a['label'] === $b['label'] && $a['text'] === $b['text'] && $a['link'] === $b['link'];
		};

		if ( count( $now ) === count( $lines ) && ! in_array( false, array_map( $same, $now, $lines ), true ) ) {
			return; // Nothing changed.
		}

		$rows = array();
		foreach ( $lines as $l ) {
			$keep = '';
			foreach ( $now as $n ) {
				if ( $n['label'] === $l['label'] && $n['text'] === $l['text'] ) {
					$keep = $n['id'];
					break;
				}
			}
			$rows[] = array(
				'field_sb_emailsig_person_info_icon'  => $l['icon'],
				'field_sb_emailsig_person_info_label' => $l['label'],
				'field_sb_emailsig_person_info_text'  => $l['text'],
				'field_sb_emailsig_person_info_link'  => $l['link'],
				'field_sb_emailsig_person_info_id'    => $keep,
			);
		}

		update_field( 'field_sb_emailsig_person_info', $rows, $id );
	}

	/**
	 * The photo: one already in this site's media library as it is, else
	 * downloaded. Its crop (x, y, w, h) when given. False only when a photo
	 * was asked for and couldn't be had.
	 */
	private static function fill_photo( $id, $url, $crop ) {
		if ( $url === '' ) {
			return true;
		}

		$att = (int) attachment_url_to_postid( $url );

		if ( ! $att ) {
			if ( ! wp_http_validate_url( $url ) ) {
				return false;
			}

			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$att = media_sideload_image( $url, 0, trim( (string) get_field( 'sb_emailsig_name', $id ) ), 'id' );

			if ( is_wp_error( $att ) ) {
				return false;
			}
		}

		if ( ! in_array( get_post_mime_type( $att ), SB_EmailSig_Request::MIMES, true ) ) {
			return false;
		}

		if ( (int) get_field( 'sb_emailsig_photo', $id, false ) !== (int) $att ) {
			update_field( 'field_sb_emailsig_photo', $att, $id );
		}

		$f = array_map( 'floatval', array_map( 'trim', explode( ',', (string) $crop ) ) );
		if ( count( $f ) === 4 && $f[2] > 0 && $f[3] > 0 ) {
			SB_EmailSig_Photo::set_crop( $id, $att, $f[0], $f[1], $f[2], $f[3] );
		}

		return true;
	}
}
