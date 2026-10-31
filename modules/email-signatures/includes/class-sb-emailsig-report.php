<?php
/**
 * The Reports page: clicks on signature links (SB_EmailSig_Tracking).
 *
 * Filters (all in the address, so a view can be bookmarked): a period (7,
 * 30 or 90 days, 12 months, or from and to dates), a company (with more
 * than one: its people's clicks, by the company each person is in now;
 * Sam, 30 October 2026), a person, a link, a
 * template (the one the signature was copied from; Not recorded for clicks
 * from links made before 29 October 2026) and a device. The selects show the page as soon as they change (Custom dates
 * wait for Filter); Clear filters
 * shows whenever any differs from the default (last 30 days, everyone,
 * every link, every device). Names in the tables filter by that person,
 * link or device too.
 *
 * Tiles: clicks (with the change on the period before, the same length),
 * contact saves, people clicked and the top link. The chart is by hour for
 * a single day, by day up to 45 days, by week (from Monday) up to 200 days,
 * then by month. Hovering or focusing a bar shows its links in a tooltip;
 * clicking a day, week or month bar shows that day, week or month. Then by
 * person, by link, by template and by device, each with a bar for its share;
 * a table filtered by itself keeps its other rows, greyed with 0, to pick
 * instead, and a Reset for that filter. Clicks
 * marked as scanners are left out and counted on their own. Days are the
 * site's time zone.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Report {

	const RANGES = array(
		'7'   => 'Last 7 days',
		'30'  => 'Last 30 days',
		'90'  => 'Last 90 days',
		'365' => 'Last 12 months',
		'c'   => 'Custom',
	);

	const DEVICES = array(
		'mobile'  => 'Mobile',
		'desktop' => 'Desktop',
		'tablet'  => 'Tablet',
	);

	/** The filters shown: range, from, to, company, person, link, template ('', 'none' or an id), device. */
	private static $f = array();

	/** The period asked for: [ range key, from Y-m-d, to Y-m-d ] in the site's time zone. */
	private static function period() {
		// phpcs:disable WordPress.Security.NonceVerification -- a read-only filter.
		$range = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : '30';
		$from  = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		$to    = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
		// phpcs:enable

		$tz    = wp_timezone();
		$today = new DateTimeImmutable( 'today', $tz );
		$range = isset( self::RANGES[ $range ] ) ? $range : '30';

		if ( $range === 'c' ) {
			$f = DateTimeImmutable::createFromFormat( '!Y-m-d', $from, $tz );
			$t = DateTimeImmutable::createFromFormat( '!Y-m-d', $to, $tz );

			if ( $f && $t ) {
				if ( $f > $t ) {
					list( $f, $t ) = array( $t, $f );
				}

				return array( 'c', $f->format( 'Y-m-d' ), $t->format( 'Y-m-d' ) );
			}

			$range = '30';
		}

		return array( $range, $today->modify( '-' . ( (int) $range - 1 ) . ' days' )->format( 'Y-m-d' ), $today->format( 'Y-m-d' ) );
	}

	/** A local day's start or end as a UTC datetime for the table. */
	private static function utc( $day, $end ) {
		$d = new DateTimeImmutable( $day . ( $end ? ' 23:59:59' : ' 00:00:00' ), wp_timezone() );

		return $d->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/** This page with the filters as they are, changed by $set ('' or 0 drops one). */
	private static function url( array $set = array() ) {
		$f    = array_merge( self::$f, $set );
		$args = array( 'page' => SB_EmailSig_Module::SLUG . '-reports' );

		if ( $f['range'] === 'c' ) {
			$args += array(
				'range' => 'c',
				'from'  => $f['from'],
				'to'    => $f['to'],
			);
		} elseif ( $f['range'] !== '30' ) {
			$args['range'] = $f['range'];
		}

		foreach ( array( 'company', 'person', 'link', 'template', 'device' ) as $k ) {
			if ( ! empty( $f[ $k ] ) ) {
				$args[ $k ] = $f[ $k ];
			}
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/** True when any filter differs from the default. */
	private static function filtered() {
		return self::$f['range'] !== '30' || self::$f['company'] || self::$f['person'] || self::$f['link'] !== '' || self::$f['template'] !== '' || self::$f['device'] !== '';
	}

	/** The WHERE for a period with the filters, but for $skip (person, link, template or device). */
	private static function where( $from, $to, $skip = '' ) {
		global $wpdb;

		$where = $wpdb->prepare( 'clicked_at BETWEEN %s AND %s', self::utc( $from, false ), self::utc( $to, true ) ) . self::company_where();

		if ( self::$f['person'] && $skip !== 'person' ) {
			$where .= $wpdb->prepare( ' AND person_id = %d', self::$f['person'] );
		}
		if ( self::$f['link'] !== '' && $skip !== 'link' ) {
			$where .= $wpdb->prepare( ' AND link = %s', self::$f['link'] );
		}
		if ( self::$f['template'] !== '' && $skip !== 'template' ) {
			$where .= $wpdb->prepare( ' AND template_id = %d', self::$f['template'] === 'none' ? 0 : (int) self::$f['template'] );
		}
		if ( self::$f['device'] !== '' && $skip !== 'device' ) {
			$where .= $wpdb->prepare( ' AND device = %s', self::$f['device'] );
		}

		return $where;
	}

	/** The company filter as SQL: its people's clicks (everyone it has had, deleted too). */
	private static function company_where() {
		if ( empty( self::$f['company'] ) ) {
			return '';
		}

		$ids = array_map( 'intval', self::company_people( self::$f['company'] ) );

		return $ids ? ' AND person_id IN (' . implode( ',', $ids ) . ')' : ' AND 1 = 0';
	}

	/** Everyone in company $co now: active, inactive and deleted. */
	private static function company_people( $co ) {
		$out = array();

		foreach ( get_posts( array( 'post_type' => SB_EMAILSIG_CPT, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'trash' ), 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
			if ( SB_EmailSig_Company::of_person( $id ) === (int) $co ) {
				$out[] = (int) $id;
			}
		}

		return $out;
	}

	public static function render() {
		global $wpdb;

		SB_EmailSig_Tracking::install();
		SB_EmailSig_Tracking::prune();

		list( $range, $from, $to ) = self::period();

		// phpcs:disable WordPress.Security.NonceVerification -- a read-only filter.
		$link   = isset( $_GET['link'] ) ? substr( preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET['link'] ) ), 0, 40 ) : '';
		$device = isset( $_GET['device'] ) ? sanitize_key( wp_unslash( $_GET['device'] ) ) : '';
		$tpl    = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : '';
		self::$f = array(
			'range'  => $range,
			'from'   => $from,
			'to'     => $to,
			'company' => isset( $_GET['company'] ) && SB_EmailSig_Company::valid( absint( $_GET['company'] ) ) ? absint( $_GET['company'] ) : 0,
			'person' => isset( $_GET['person'] ) ? absint( $_GET['person'] ) : 0,
			'link'     => $link,
			'template' => ( $tpl === 'none' || ctype_digit( $tpl ) ) ? $tpl : '',
			'device'   => isset( self::DEVICES[ $device ] ) ? $device : '',
		);
		// phpcs:enable

		$table  = SB_EmailSig_Tracking::table();
		$where  = self::where( $from, $to );
		$offset = (int) wp_timezone()->getOffset( new DateTime( 'now', new DateTimeZone( 'UTC' ) ) );
		$single = $from === $to;

		// The period before, the same length, for the change on the Clicks tile.
		$tz     = wp_timezone();
		$days   = ( new DateTimeImmutable( $from, $tz ) )->diff( new DateTimeImmutable( $to, $tz ) )->days + 1;
		$p_to   = ( new DateTimeImmutable( $from, $tz ) )->modify( '-1 day' )->format( 'Y-m-d' );
		$p_from = ( new DateTimeImmutable( $from, $tz ) )->modify( '-' . $days . ' days' )->format( 'Y-m-d' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared in where().
		$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where} AND bot = 0" );
		$before   = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . self::where( $p_from, $p_to ) . ' AND bot = 0' );
		$scanners = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where} AND bot = 1" );
		$saves    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where} AND bot = 0 AND link = 'v'" );
		$bucket   = $single ? 'HOUR(DATE_ADD(clicked_at, INTERVAL %d SECOND))' : 'DATE(DATE_ADD(clicked_at, INTERVAL %d SECOND))';
		$by_time  = $wpdb->get_results( $wpdb->prepare( "SELECT {$bucket} AS t, link, COUNT(*) AS n FROM {$table} WHERE {$where} AND bot = 0 GROUP BY t, link", $offset ), ARRAY_A );
		$by_who   = $wpdb->get_results( "SELECT person_id, COUNT(*) AS n FROM {$table} WHERE {$where} AND bot = 0 GROUP BY person_id ORDER BY n DESC", ARRAY_A );
		$by_link  = $wpdb->get_results( "SELECT link, COUNT(*) AS n, MAX(id) AS last FROM {$table} WHERE {$where} AND bot = 0 GROUP BY link ORDER BY n DESC", ARRAY_A );
		$by_dev   = $wpdb->get_results( "SELECT device, COUNT(*) AS n FROM {$table} WHERE {$where} AND bot = 0 GROUP BY device ORDER BY n DESC", ARRAY_A );
		$by_tpl   = $wpdb->get_results( "SELECT template_id, COUNT(*) AS n FROM {$table} WHERE {$where} AND bot = 0 GROUP BY template_id ORDER BY n DESC", ARRAY_A );
		$top_link = $wpdb->get_results( "SELECT person_id, link, COUNT(*) AS n FROM {$table} WHERE {$where} AND bot = 0 GROUP BY person_id, link ORDER BY n DESC", ARRAY_A );

		// Each table's rows without its own filter, so what it filters out
		// still shows, greyed with 0, to pick instead.
		$cols = array(
			'person'   => 'person_id',
			'link'     => 'link',
			'template' => 'template_id',
			'device'   => 'device',
		);
		$all  = array();
		foreach ( $cols as $dim => $col ) {
			$all[ $dim ] = self::$f[ $dim ] !== '' && self::$f[ $dim ] !== 0
				? $wpdb->get_results( "SELECT {$col} AS k, COUNT(*) AS n, MAX(id) AS last FROM {$table} WHERE " . self::where( $from, $to, $dim ) . " AND bot = 0 GROUP BY {$col} ORDER BY n DESC", ARRAY_A )
				: array();
		}

		// Every link clicked in the period, whatever the link and device
		// filters, for the Link menu: its key and name when last clicked.
		$p_where = $wpdb->prepare( 'clicked_at BETWEEN %s AND %s', self::utc( $from, false ), self::utc( $to, true ) ) . self::company_where() . ( self::$f['person'] ? $wpdb->prepare( ' AND person_id = %d', self::$f['person'] ) : '' );
		$menu    = $wpdb->get_results( "SELECT link, MAX(id) AS last FROM {$table} WHERE {$p_where} AND bot = 0 GROUP BY link", ARRAY_A );
		$labels  = array();
		$lasts   = array_filter( array_map( 'intval', array_merge( wp_list_pluck( $by_link, 'last' ), wp_list_pluck( $menu, 'last' ), wp_list_pluck( $all['link'], 'last' ) ) ) );
		if ( $lasts ) {
			foreach ( $wpdb->get_results( "SELECT link, label FROM {$table} WHERE id IN (" . implode( ',', array_unique( $lasts ) ) . ')', ARRAY_A ) as $row ) {
				$labels[ $row['link'] ] = $row['label'];
			}
		}
		// phpcs:enable

		$links = array();
		foreach ( wp_list_pluck( $menu, 'link' ) as $key ) {
			$links[ $key ] = self::label( $key, $labels );
		}
		if ( self::$f['link'] !== '' && ! isset( $links[ self::$f['link'] ] ) ) {
			$links[ self::$f['link'] ] = self::label( self::$f['link'], $labels );
		}
		asort( $links, SORT_NATURAL | SORT_FLAG_CASE );

		echo '<section class="sb-tweaks-section sb-emailsig-report">';

		self::filters( $links );

		if ( ! SB_EmailSig_Tracking::settings()['clicks'] ) {
			echo '<div class="notice notice-warning inline"><p>Track Clicks is off in <a href="' . esc_url( SB_EmailSig_Settings::url() ) . '">Settings</a>, so no new clicks are counted.</p></div>';
		}

		// The change on the period before.
		$change = '';
		if ( $before ) {
			$pct    = (int) round( 100 * ( $total - $before ) / $before );
			$change = ( $pct >= 0 ? '+' : '' ) . $pct . '% on the ' . ( $days === 1 ? 'day' : $days . ' days' ) . ' before';
		} elseif ( $total ) {
			$change = 'None the ' . ( $days === 1 ? 'day' : $days . ' days' ) . ' before';
		}

		echo '<div class="sb-emailsig-report__tiles">';
		self::tile( 'Clicks', number_format_i18n( $total ), $change, $before ? ( $total < $before ? 'down' : 'up' ) : '' );
		self::tile( 'Contact saves', number_format_i18n( $saves ) );
		self::tile( 'People clicked', number_format_i18n( count( $by_who ) ) );
		self::tile( 'Top link', $by_link ? self::label( $by_link[0]['link'], $labels ) : 'None', $by_link ? number_format_i18n( $by_link[0]['n'] ) . ' clicks' : '', '', true );
		echo '</div>';

		if ( ! $total ) {
			echo '<div class="sb-tweaks-card sb-emailsig-report__empty"><p>No clicks ' . ( self::filtered() ? 'for these filters.' : 'in this period.' ) . '</p></div>';
		} else {
			self::chart( $by_time, $labels );

			$top = array();
			foreach ( $top_link as $row ) {
				if ( ! isset( $top[ $row['person_id'] ] ) ) {
					$top[ $row['person_id'] ] = self::label( $row['link'], $labels );
				}
			}

			echo '<div class="sb-emailsig-report__tables">';
			self::table(
				'person',
				'By person',
				'Person',
				array_column( $by_who, 'n', 'person_id' ),
				$all['person'],
				$total,
				function ( $id ) {
					return get_post_type( (int) $id ) === SB_EMAILSIG_CPT ? trim( (string) get_field( 'sb_emailsig_name', (int) $id ) ) : 'Removed person';
				},
				array( 'Most clicked', $top )
			);
			self::table(
				'link',
				'By link',
				'Link',
				array_column( $by_link, 'n', 'link' ),
				$all['link'],
				$total,
				function ( $key ) use ( $labels ) {
					return self::label( $key, $labels );
				}
			);
			// Template and device stacked in the last column, at Sam's request.
			echo '<div class="sb-emailsig-report__stack">';
			self::table(
				'template',
				'By template',
				'Template',
				array_column( $by_tpl, 'n', 'template_id' ),
				$all['template'],
				$total,
				array( __CLASS__, 'template_name' )
			);
			self::table(
				'device',
				'By device',
				'Device',
				array_column( $by_dev, 'n', 'device' ),
				$all['device'],
				$total,
				function ( $key ) {
					return self::DEVICES[ $key ] ?? 'Unknown';
				}
			);
			echo '</div>';
			echo '</div>';
		}

		if ( $scanners ) {
			/* translators: %s: number of clicks. */
			echo '<p class="sb-emailsig-report__note">' . esc_html( sprintf( _n( '%s click by an email scanner left out.', '%s clicks by email scanners left out.', $scanners, 'socialbump-tweaks' ), number_format_i18n( $scanners ) ) ) . '</p>';
		}

		echo '</section>';
	}

	private static function filters( array $links ) {
		$f = self::$f;

		echo '<form method="get" class="sb-emailsig-report__filters" data-sb-emailsig-report>';
		echo '<input type="hidden" name="page" value="' . esc_attr( SB_EmailSig_Module::SLUG . '-reports' ) . '">';

		echo '<select name="range" aria-label="Period" data-sb-emailsig-range>';
		foreach ( self::RANGES as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $f['range'], (string) $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		echo '<span class="sb-emailsig-report__dates"' . ( $f['range'] === 'c' ? '' : ' hidden' ) . '>';
		echo '<input type="date" name="from" value="' . esc_attr( $f['from'] ) . '" aria-label="From"> to ';
		echo '<input type="date" name="to" value="' . esc_attr( $f['to'] ) . '" aria-label="To">';
		echo '<button type="submit" class="button">Filter</button>';
		echo '</span>';

		// Company (with more than one): the Person menu then lists only its people.
		$companies = SB_EmailSig_Company::all();
		if ( count( $companies ) > 1 ) {
			echo '<select name="company" aria-label="Company" data-sb-emailsig-company><option value="0">Every company</option>';
			foreach ( $companies as $co ) {
				echo '<option value="' . (int) $co . '"' . selected( $f['company'], $co, false ) . '>' . esc_html( SB_EmailSig_Company::name( $co ) ) . '</option>';
			}
			echo '</select>';
		}

		echo '<select name="person" aria-label="Person"><option value="0">Everyone</option>';
		foreach ( SB_EmailSig_Admin::people( array( 'publish', 'draft' ) ) as $id ) {
			if ( $f['company'] && SB_EmailSig_Company::of_person( $id ) !== $f['company'] ) {
				continue;
			}

			echo '<option value="' . (int) $id . '"' . selected( $f['person'], $id, false ) . '>' . esc_html( (string) get_field( 'sb_emailsig_name', $id ) ) . '</option>';
		}
		echo '</select>';

		echo '<select name="link" aria-label="Link"><option value="">Every link</option>';
		foreach ( $links as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $f['link'], (string) $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		echo '<select name="template" aria-label="Template"><option value="">Every template</option>';
		foreach ( SB_EmailSig_Templates::all() as $tpl ) {
			echo '<option value="' . esc_attr( $tpl['id'] ) . '"' . selected( $f['template'], (string) $tpl['id'], false ) . '>' . esc_html( $tpl['name'] ) . '</option>';
		}
		echo '<option value="none"' . selected( $f['template'], 'none', false ) . '>Not recorded</option>';
		echo '</select>';

		echo '<select name="device" aria-label="Device"><option value="">Every device</option>';
		foreach ( self::DEVICES as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $f['device'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		if ( self::filtered() ) {
			echo '<a class="button sb-emailsig-report__clear" href="' . esc_url( admin_url( 'admin.php?page=' . SB_EmailSig_Module::SLUG . '-reports' ) ) . '">Clear filters</a>';
		}

		echo '</form>';

		// Show on change; Custom waits for its dates and Filter.
		echo '<script>document.addEventListener("change",function(e){var f=e.target.closest("[data-sb-emailsig-report]");if(!f)return;if(e.target.matches("[data-sb-emailsig-range]")){var d=f.querySelector(".sb-emailsig-report__dates");d.hidden=e.target.value!=="c";if(e.target.value==="c")return;}if(e.target.type==="date")return;if(e.target.matches("[data-sb-emailsig-company]")){var p=f.querySelector("[name=person]");if(p)p.value="0";}f.submit();});</script>';
	}

	private static function tile( $label, $value, $sub = '', $trend = '', $text = false ) {
		echo '<div class="sb-emailsig-report__tile' . ( $text ? ' is-text' : '' ) . '"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong>';
		if ( $sub !== '' ) {
			echo '<small' . ( $trend !== '' ? ' class="is-' . esc_attr( $trend ) . '"' : '' ) . '>' . esc_html( $sub ) . '</small>';
		}
		echo '</div>';
	}

	/**
	 * Bars over time: by hour for one day, by day up to 45 days, by week up
	 * to 200, else by month. Each bar's links go in its tooltip, and a day,
	 * week or month bar links to that day, week or month.
	 */
	private static function chart( array $by_time, array $labels ) {
		$tz    = wp_timezone();
		$from  = self::$f['from'];
		$to    = self::$f['to'];
		$start = new DateTimeImmutable( $from, $tz );
		$end   = new DateTimeImmutable( $to, $tz );
		$span  = $start->diff( $end )->days + 1;
		$unit  = $from === $to ? 'hour' : ( $span <= 45 ? 'day' : ( $span <= 200 ? 'week' : 'month' ) );
		$bars  = array();

		if ( $unit === 'hour' ) {
			for ( $h = 0; $h < 24; $h++ ) {
				$bars[ (string) $h ] = array(
					'label' => self::hour( $h ),
					'title' => wp_date( 'D j M', $start->getTimestamp() ) . ', ' . self::hour( $h ) . ' to ' . self::hour( ( $h + 1 ) % 24 ),
					'url'   => '',
					'n'     => 0,
					'links' => array(),
				);
			}
			$key_of = function ( $t ) {
				return (string) (int) $t;
			};
		} else {
			for ( $d = $start; $d <= $end; $d = $d->modify( '+1 day' ) ) {
				$key = self::bucket( $d, $unit );

				if ( ! isset( $bars[ $key ] ) ) {
					list( $b_from, $b_to ) = self::bucket_dates( $d, $unit );
					$ts                    = ( new DateTimeImmutable( $b_from, $tz ) )->getTimestamp();

					$bars[ $key ] = array(
						'label' => $unit === 'month' ? wp_date( 'M Y', $ts ) : wp_date( 'j M', $ts ),
						'title' => $unit === 'month' ? wp_date( 'F Y', $ts ) : ( $unit === 'week' ? 'Week of ' . wp_date( 'D j M', $ts ) : wp_date( 'l j M', $ts ) ),
						'url'   => self::url(
							array(
								'range' => 'c',
								'from'  => $b_from,
								'to'    => $b_to,
							)
						),
						'n'     => 0,
						'links' => array(),
					);
				}
			}
			$key_of = function ( $t ) use ( $tz, $unit ) {
				return self::bucket( new DateTimeImmutable( $t, $tz ), $unit );
			};
		}

		foreach ( $by_time as $row ) {
			$key = $key_of( $row['t'] );

			if ( isset( $bars[ $key ] ) ) {
				$bars[ $key ]['n']                   += (int) $row['n'];
				$bars[ $key ]['links'][ $row['link'] ] = ( $bars[ $key ]['links'][ $row['link'] ] ?? 0 ) + (int) $row['n'];
			}
		}

		$max   = max( 1, max( wp_list_pluck( $bars, 'n' ) ) );
		$top   = self::nice( $max );
		$count = count( $bars );
		$every = $unit === 'hour' ? 3 : max( 1, (int) ceil( $count / 8 ) );
		$title = array(
			'hour'  => 'Clicks by hour, ' . wp_date( 'l j F Y', $start->getTimestamp() ),
			'day'   => 'Clicks by day',
			'week'  => 'Clicks by week',
			'month' => 'Clicks by month',
		);

		echo '<div class="sb-tweaks-card sb-emailsig-report__chart"><div class="sb-emailsig-report__chart-head"><h3>' . esc_html( $title[ $unit ] ) . '</h3>';
		if ( $unit !== 'hour' ) {
			echo '<span>Click a bar to see that ' . esc_html( $unit ) . '.</span>';
		}
		echo '</div>';

		echo '<div class="sb-emailsig-report__plot">';
		echo '<div class="sb-emailsig-report__axis" aria-hidden="true"><span>' . esc_html( number_format_i18n( $top ) ) . '</span><span>' . esc_html( number_format_i18n( $top / 2 ) ) . '</span><span>0</span></div>';
		echo '<div class="sb-emailsig-report__bars" role="list" aria-label="' . esc_attr( $title[ $unit ] ) . '">';

		$i = 0;
		foreach ( $bars as $bar ) {
			$h    = $bar['n'] ? max( 1.5, 100 * $bar['n'] / $top ) : 0;
			// Every so many bars, and the last when it isn't crowding the one before.
			$show = ( $i % $every === 0 ) || ( $i === $count - 1 && $i % $every >= $every / 2 );
			$side = $i < $count / 4 ? ' is-left' : ( $i >= $count * 3 / 4 ? ' is-right' : '' );
			$tag  = $bar['url'] !== '' ? 'a' : 'div';
			$open = $tag === 'a' ? '<a href="' . esc_url( $bar['url'] ) . '"' : '<div tabindex="0"';

			arsort( $bar['links'] );

			echo $open . ' class="sb-emailsig-report__bar' . esc_attr( $side ) . ( $bar['n'] ? '' : ' is-empty' ) . '" role="listitem" aria-label="' . esc_attr( $bar['title'] . ': ' . $bar['n'] . ' clicks' ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
			echo '<em>' . ( $show ? esc_html( $bar['label'] ) : '' ) . '</em>';
			echo '<span class="sb-emailsig-report__fill" style="height:' . esc_attr( round( $h, 2 ) ) . '%">';

			// The tooltip, on top of the bar: its links, the most clicked first.
			echo '<span class="sb-emailsig-report__tip" aria-hidden="true"><b>' . esc_html( $bar['title'] ) . '</b><i>' . esc_html( number_format_i18n( $bar['n'] ) . ( $bar['n'] === 1 ? ' click' : ' clicks' ) ) . '</i>';
			$shown = 0;
			foreach ( $bar['links'] as $key => $n ) {
				if ( ++$shown > 8 ) {
					echo '<span class="sb-emailsig-report__tip-row"><span>' . esc_html( ( count( $bar['links'] ) - 8 ) . ' more' ) . '</span></span>';
					break;
				}
				echo '<span class="sb-emailsig-report__tip-row"><span>' . esc_html( self::label( $key, $labels ) ) . '</span><span>' . esc_html( number_format_i18n( $n ) ) . '</span></span>';
			}
			echo '</span></span>';

			echo '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- a or div.
			$i++;
		}

		echo '</div></div></div>';
	}

	/** An hour as 12am, 9am, 12pm, 5pm. */
	private static function hour( $h ) {
		return ( $h % 12 === 0 ? 12 : $h % 12 ) . ( $h < 12 ? 'am' : 'pm' );
	}

	/** The bar a day belongs to: the day, the Monday of its week, or its month. */
	private static function bucket( DateTimeImmutable $d, $unit ) {
		if ( $unit === 'month' ) {
			return $d->format( 'Y-m' );
		}

		if ( $unit === 'week' ) {
			return $d->modify( '-' . ( (int) $d->format( 'N' ) - 1 ) . ' days' )->format( 'Y-m-d' );
		}

		return $d->format( 'Y-m-d' );
	}

	/** A bar's first and last day: the whole week or month, even past the period's ends. */
	private static function bucket_dates( DateTimeImmutable $d, $unit ) {
		if ( $unit === 'month' ) {
			return array( $d->format( 'Y-m-01' ), $d->format( 'Y-m-t' ) );
		}

		if ( $unit === 'week' ) {
			$mon = $d->modify( '-' . ( (int) $d->format( 'N' ) - 1 ) . ' days' );

			return array( $mon->format( 'Y-m-d' ), $mon->modify( '+6 days' )->format( 'Y-m-d' ) );
		}

		return array( $d->format( 'Y-m-d' ), $d->format( 'Y-m-d' ) );
	}

	/** A round number at or above $n for the top of the chart: 5, 10, 20, 25, 50, 100 and so on. */
	private static function nice( $n ) {
		$n    = max( 2, (int) $n );
		$step = pow( 10, floor( log10( $n ) ) );

		foreach ( array( 1, 1.2, 1.6, 2, 3, 4, 5, 6, 8, 10 ) as $m ) {
			if ( $m * $step >= $n && fmod( $m * $step, 2 ) == 0 ) { // phpcs:ignore WordPress.PHP.StrictComparisons -- floats.
				return (int) ( $m * $step );
			}
		}

		return (int) ( 10 * $step );
	}

	/** A table row's count, its share of the total, and a bar sized against the table's top row ($max). */
	private static function share( $n, $total, $max ) {
		$pct = $total ? round( 100 * $n / $total ) : 0;
		$bar = $max ? round( 100 * $n / $max ) : 0;

		return '<td class="num"><span class="sb-emailsig-report__share" aria-hidden="true"><span style="width:' . (int) $bar . '%"></span></span>' . esc_html( number_format_i18n( $n ) ) . ' <small>' . (int) $pct . '%</small></td>';
	}

	/**
	 * One of the tables (person, link, template, device): its rows under
	 * every filter, the most clicked first. When it's filtered itself, the
	 * rows that filter leaves out follow, greyed with 0, each a link to
	 * filter by it instead, and Reset in the corner shows them all again.
	 * $extra: [ heading, values by key ] for a middle column.
	 */
	private static function table( $dim, $title, $head, array $counts, array $others, $total, callable $name, array $extra = array() ) {
		$on   = self::$f[ $dim ] !== '' && self::$f[ $dim ] !== 0;
		$max  = $counts ? max( $counts ) : 0;
		$keys = array_map( 'strval', array_keys( $counts ) );

		foreach ( $others as $row ) {
			if ( ! in_array( (string) $row['k'], $keys, true ) ) {
				$keys[] = (string) $row['k'];
			}
		}

		echo '<div class="sb-tweaks-card"><div class="sb-emailsig-report__table-head"><h3>' . esc_html( $title ) . '</h3>';
		if ( $on ) {
			echo '<a class="sb-emailsig-report__reset" href="' . esc_url( self::url( array( $dim => $dim === 'person' ? 0 : '' ) ) ) . '">Reset</a>';
		}
		echo '</div><table class="widefat"><thead><tr><th>' . esc_html( $head ) . '</th>' . ( $extra ? '<th>' . esc_html( $extra[0] ) . '</th>' : '' ) . '<th class="num">Clicks</th></tr></thead><tbody>';

		foreach ( $keys as $key ) {
			$n     = (int) ( $counts[ $key ] ?? 0 );
			$value = $dim === 'template' && ! (int) $key ? 'none' : $key;
			$label = (string) call_user_func( $name, $key );
			$link  = ! ( $dim === 'device' && ! isset( self::DEVICES[ $key ] ) ) && ! ( $dim === 'person' && get_post_type( (int) $key ) !== SB_EMAILSIG_CPT );

			echo '<tr' . ( $n ? '' : ' class="is-off"' ) . '><td>' . ( $link ? '<a href="' . esc_url( self::url( array( $dim => $value ) ) ) . '">' . esc_html( $label ) . '</a>' : esc_html( $label ) ) . '</td>';
			if ( $extra ) {
				echo '<td>' . esc_html( $n ? ( $extra[1][ $key ] ?? '' ) : '' ) . '</td>';
			}
			echo self::share( $n, $total, $max ) . '</tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in share().
		}
		echo '</tbody></table></div>';
	}

	/** A template's name for the reports: Not recorded (0) or Deleted template when it has gone. */
	public static function template_name( $id ) {
		if ( ! (int) $id ) {
			return 'Not recorded';
		}

		$tpl = SB_EmailSig_Templates::get( (string) (int) $id );

		return $tpl ? $tpl['name'] : 'Deleted template';
	}

	/** A link's name: as it's called in Settings now, else as it was when clicked. */
	private static function label( $key, $labels ) {
		static $targets = null;

		if ( $key === 'v' ) {
			return 'Save contact';
		}

		if ( null === $targets ) {
			$targets = SB_EmailSig_Data::targets();
		}

		if ( isset( $targets[ $key ] ) && $key !== 'c' ) {
			return $targets[ $key ]['label'];
		}

		return $labels[ $key ] ?? $key;
	}
}
