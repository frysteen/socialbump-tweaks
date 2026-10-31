<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which SocialBUMP plugins are installed where. Hub only.
 *
 * Every SocialBUMP plugin carries SocialBUMP_Reporter, which checks in at
 * /wp-json/sb-tweaks/v1/checkin when something changes and once a day. This
 * keeps the latest report per site in one option and shows them on the
 * Installs page: a row per site, a column per plugin, the version highlighted
 * when it is behind the hub's own copy, and when the site last checked in. A
 * site quiet for more than three days is flagged, since a deleted plugin (or
 * a site that is down) cannot say so itself.
 *
 * The check-in only accepts the tracked plugin names and sensible version
 * strings, carries a shared key, and one site can report at most twenty times
 * in ten minutes. The key ships inside the plugins, so it keeps out noise rather than
 * a determined sender; the worst a forged report can do is add a row, which
 * the page can remove.
 */
class SB_Tweaks_Installs {

	const OPTION   = 'sb_tweaks_installs';
	const MAX      = 1000;
	const STALE    = 3 * DAY_IN_SECONDS;
	const LABELS   = [
		'socialbump-tweaks'                => 'Tweaks',
		'socialbump-ai-knowledge-exporter' => 'SEO for AI',
	];

	/** Each plugin's main admin page, so an Active version links straight to it. */
	const PAGES = [
		'socialbump-tweaks'                => 'sb-tweaks',
		'socialbump-ai-knowledge-exporter' => 'sb-ai-knowledge-exporter',
	];

	/**
	 * Each site's icon (Sam, 31 October 2026): host => [ url, ok, at ], read
	 * from the site_icon_url in its public /wp-json/ index. Kept apart from the
	 * reports, which each check-in replaces. Asked again after a week, or a
	 * day when the site didn't answer.
	 */
	const ICONS = 'sb_tweaks_install_icons';

	/** Colours for the first letter circle, picked from the address so a site keeps its colour. */
	const ICON_COLOURS = [ '#3e6803', '#0b6bc4', '#7a3fd1', '#c2410c', '#00807a', '#be185d', '#4b5563', '#a16207' ];

	/** The stored icon for a host, and whether it is due to be asked for again. */
	private static function icon( $host ) {
		$all  = (array) get_option( self::ICONS, [] );
		$icon = $all[ $host ] ?? null;

		if ( ! is_array( $icon ) ) {
			return [ '', true, '' ];
		}

		$wait = ! empty( $icon['ok'] ) ? WEEK_IN_SECONDS : DAY_IN_SECONDS;

		$url = (string) ( $icon['url'] ?? '' );

		// Due again after the wait, or now for an icon stored before its ring colour was worked out.
		$due = time() - (int) ( $icon['at'] ?? 0 ) > $wait || ( $url !== '' && ! array_key_exists( 'ring', $icon ) );

		return [ $url, $due, (string) ( $icon['ring'] ?? '' ) ];
	}

	/**
	 * The ring colour for an icon (Sam, 31 October 2026): the average of its
	 * coloured pixels, leaving out see-through ones and white or near white
	 * backgrounds. Greys (a black and white logo), a colour too pale to show,
	 * or an image GD can't read (.ico, .svg) give '', a neutral ring.
	 */
	private static function ring( $url ) {
		if ( $url === '' || ! function_exists( 'imagecreatefromstring' ) ) {
			return '';
		}

		$res = wp_safe_remote_get( $url, [ 'timeout' => 8, 'limit_response_size' => 2 * MB_IN_BYTES ] );

		if ( is_wp_error( $res ) || (int) wp_remote_retrieve_response_code( $res ) !== 200 ) {
			return '';
		}

		$img = @imagecreatefromstring( wp_remote_retrieve_body( $res ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- an unreadable image just means no colour.

		if ( ! $img ) {
			return '';
		}

		$size  = 24;
		$small = imagecreatetruecolor( $size, $size );
		imagealphablending( $small, false );
		imagesavealpha( $small, true );
		imagefill( $small, 0, 0, imagecolorallocatealpha( $small, 0, 0, 0, 127 ) );
		imagecopyresampled( $small, $img, 0, 0, 0, 0, $size, $size, imagesx( $img ), imagesy( $img ) );
		imagedestroy( $img );

		$sum = [ 0, 0, 0, 0 ];

		for ( $x = 0; $x < $size; $x++ ) {
			for ( $y = 0; $y < $size; $y++ ) {
				$c = imagecolorat( $small, $x, $y );

				if ( ( ( $c >> 24 ) & 127 ) > 80 ) {
					continue;
				}

				$r   = ( $c >> 16 ) & 255;
				$g   = ( $c >> 8 ) & 255;
				$b   = $c & 255;
				$max = max( $r, $g, $b );

				// Coloured pixels only: a grey, white or black one is left out.
				if ( $max < 30 || ( $max - min( $r, $g, $b ) ) / $max < 0.25 ) {
					continue;
				}

				$sum[0] += $r;
				$sum[1] += $g;
				$sum[2] += $b;
				$sum[3]++;
			}
		}

		imagedestroy( $small );

		// Too few coloured pixels to say, or a colour too pale to show against the card.
		if ( $sum[3] < 12 ) {
			return '';
		}

		$rgb = [ (int) round( $sum[0] / $sum[3] ), (int) round( $sum[1] / $sum[3] ), (int) round( $sum[2] / $sum[3] ) ];

		if ( ( 0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2] ) / 255 > 0.85 ) {
			return '';
		}

		return sprintf( '#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2] );
	}

	/** The circle: the icon, or the first letter of the name on the site's colour. */
	private static function icon_html( $host, $name, $url, $ring = '' ) {
		$letter = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( trim( $name ), 0, 1 ) ) : strtoupper( substr( trim( $name ), 0, 1 ) );
		$colour = self::ICON_COLOURS[ abs( crc32( $host ) ) % count( self::ICON_COLOURS ) ];
		$out    = '<span class="sb-installs-card__icon' . ( $url !== '' ? ' has-image' : '' ) . '" style="--sb-installs-icon: ' . esc_attr( $colour ) . ( $ring !== '' ? '; --sb-installs-ring: ' . esc_attr( $ring ) : '' ) . '" aria-hidden="true"><span class="sb-installs-card__letter">' . esc_html( $letter ) . '</span>';

		if ( $url !== '' ) {
			$out .= '<img src="' . esc_url( $url ) . '" alt="" loading="lazy" referrerpolicy="no-referrer">';
		}

		return $out . '</span>';
	}

	/** Ask one site for its icon, keep the answer, and hand back the address (empty for none). */
	public static function icon_ajax() {
		check_ajax_referer( 'sb_tweaks_push' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json( [ 'ok' => false ], 403 );
		}

		$host  = isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : '';
		$sites = (array) get_option( self::OPTION, [] );

		if ( ! isset( $sites[ $host ] ) ) {
			wp_send_json( [ 'ok' => false ] );
		}

		$res  = wp_safe_remote_get( trailingslashit( $sites[ $host ]['url'] ) . 'wp-json/', [ 'timeout' => 8, 'redirection' => 2 ] );
		$ok   = ! is_wp_error( $res ) && (int) wp_remote_retrieve_response_code( $res ) === 200;
		$body = $ok ? json_decode( wp_remote_retrieve_body( $res ), true ) : null;
		$ok   = $ok && is_array( $body );
		$url  = $ok ? esc_url_raw( (string) ( $body['site_icon_url'] ?? '' ), [ 'http', 'https' ] ) : '';

		$all          = (array) get_option( self::ICONS, [] );
		$ring = self::ring( $url );

		$all[ $host ] = [ 'url' => $url, 'ok' => $ok, 'ring' => $ring, 'at' => time() ];
		update_option( self::ICONS, array_intersect_key( $all, $sites ), false );

		wp_send_json( [ 'ok' => true, 'url' => $url, 'ring' => $ring ] );
	}

	public static function boot() {
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
		add_action( 'admin_post_sb_tweaks_forget_install', [ __CLASS__, 'forget' ] );
		add_action( 'wp_ajax_sb_tweaks_install_icon', [ __CLASS__, 'icon_ajax' ] );
	}

	public static function routes() {
		register_rest_route(
			'sb-tweaks/v1',
			'/checkin',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'checkin' ],
				'permission_callback' => function ( $request ) {
					return class_exists( 'SocialBUMP_Reporter' ) && hash_equals( SocialBUMP_Reporter::KEY, (string) $request->get_header( 'x_sb_key' ) );
				},
			]
		);
	}

	public static function checkin( WP_REST_Request $request ) {
		// Record it even if the site stops waiting (older reporters did not wait).
		ignore_user_abort( true );

		$payload = json_decode( (string) $request->get_body(), true );

		if ( ! is_array( $payload ) ) {
			return new WP_REST_Response( [ 'ok' => false ], 400 );
		}

		$host = self::host( $payload['url'] ?? '' );

		if ( $host === '' ) {
			return new WP_REST_Response( [ 'ok' => false ], 400 );
		}

		// Up to 20 reports per site in any ten minutes. Updating a few plugins
		// back to back sends a report for each within seconds, and the reporter
		// does not wait to hear whether one was refused, so a tighter limit
		// silently lost the later ones. This still stops a flood.
		$gate  = 'sb_installs_' . md5( $host );
		$count = (int) get_transient( $gate );

		if ( $count >= 20 ) {
			return new WP_REST_Response( [ 'ok' => false, 'wait' => true ], 429 );
		}

		set_transient( $gate, $count + 1, 10 * MINUTE_IN_SECONDS );

		return new WP_REST_Response( [ 'ok' => self::record( $payload ) ], 200 );
	}

	private static function host( $url ) {
		$url = esc_url_raw( (string) $url );

		return $url === '' ? '' : strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	}

	private static function version( $value ) {
		$value = trim( (string) $value );

		return preg_match( '/^[0-9][0-9A-Za-z.+-]{0,23}$/', $value ) ? $value : '';
	}

	/** Store one report. Anything that is not ours or not sensible is dropped. */
	public static function record( array $payload ) {
		$host = self::host( $payload['url'] ?? '' );

		if ( $host === '' ) {
			return false;
		}

		$plugins = [];

		foreach ( (array) ( $payload['plugins'] ?? [] ) as $slug => $info ) {
			if ( ! isset( self::LABELS[ $slug ] ) || ! is_array( $info ) ) {
				continue;
			}

			$version = self::version( $info['version'] ?? '' );

			if ( $version !== '' ) {
				$plugins[ $slug ] = [ 'version' => $version, 'active' => ! empty( $info['active'] ) ];
			}
		}

		$sites = (array) get_option( self::OPTION, [] );

		if ( ! isset( $sites[ $host ] ) && count( $sites ) >= self::MAX ) {
			return false;
		}

		$sites[ $host ] = [
			'url'     => esc_url_raw( (string) $payload['url'] ),
			'name'    => mb_substr( sanitize_text_field( (string) ( $payload['name'] ?? '' ) ), 0, 120 ),
			'wp'      => self::version( $payload['wp'] ?? '' ),
			'php'     => self::version( $payload['php'] ?? '' ),
			'plugins' => $plugins,
			'reporter' => self::version( $payload['reporter'] ?? '' ),
			'seen'    => time(),
		];

		update_option( self::OPTION, $sites, false );

		return true;
	}

	/** Remove a site from the list, for one that has gone for good. */
	public static function forget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'sb-tweaks' ) );
		}

		check_admin_referer( 'sb_tweaks_forget_install' );

		$host  = isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : '';
		$sites = (array) get_option( self::OPTION, [] );

		unset( $sites[ $host ] );
		update_option( self::OPTION, $sites, false );

		wp_safe_redirect( admin_url( 'admin.php?page=' . SB_Tweaks_Settings::PAGE_SLUG . '-installs&forgotten=1' ) );
		exit;
	}

	/** The hub's own copy of each plugin is the latest release. */
	public static function latest() {
		$latest = [];

		foreach ( array_keys( self::LABELS ) as $slug ) {
			$path = WP_PLUGIN_DIR . '/' . $slug . '/' . $slug . '.php';

			if ( file_exists( $path ) ) {
				$latest[ $slug ] = (string) get_file_data( $path, [ 'v' => 'Version' ] )['v'];
			}
		}

		return $latest;
	}

	public static function render() {
		$sites  = (array) get_option( self::OPTION, [] );
		$latest = self::latest();
		// The latest beta of SocialBUMP Tweaks, sent to chosen sites (Sam, 30 October 2026).
		$beta   = class_exists( 'SB_Tweaks_Release' ) ? (string) ( SB_Tweaks_Release::beta()['version'] ?? '' ) : '';

		if ( isset( $_GET['forgotten'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Site removed from the list. It comes back if it checks in again.', 'sb-tweaks' ) . '</p></div>';
		}

		// Standalone plugins a module now replaces, by folder => module name.
		$replaced = [];

		foreach ( class_exists( 'SB_Tweaks_Modules' ) ? SB_Tweaks_Modules::instance()->all() : [] as $def ) {
			if ( ! empty( $def['replaces'] ) ) {
				$replaced[ dirname( $def['replaces'] ) ] = $def['title'];
			}
		}

		// The hub itself isn't a card: its versions are on Publishing (Sam, 31 October 2026).
		unset( $sites[ strtolower( SB_TWEAKS_HUB_HOST ) ] );

		uasort(
			$sites,
			function ( $a, $b ) {
				return strcasecmp( $a['name'] ?: $a['url'], $b['name'] ?: $b['url'] );
			}
		);

		// The sites as a grid of cards (Sam, 31 October 2026): the bar on top
		// (search, filter, select, the bulk updates), then a card per site.
		if ( ! $sites ) {
			echo '<div class="sb-ui-empty"><p>' . esc_html__( 'No sites have checked in yet. Each one appears here once it runs a plugin version with the reporter in it: within a day of updating, or straight away when someone visits its admin.', 'sb-tweaks' ) . '</p></div>';

			return;
		}

		// The Modules popup's choices: every module here on the hub, and the limits those with them have.
		$module_list = [];
		$limit_list  = [];

		foreach ( class_exists( 'SB_Tweaks_Modules' ) ? SB_Tweaks_Modules::instance()->all() : [] as $id => $def ) {
			$module_list[] = [ $id, $def['title'] ];
		}

		foreach ( class_exists( 'SB_Tweaks_Policy' ) ? SB_Tweaks_Policy::LIMITS : [] as $id => $limits ) {
			foreach ( array_keys( $limits ) as $key ) {
				$limit_list[ $id ][] = [ $key, SB_Tweaks_Policy::LIMIT_LABELS[ $key ] ?? $key ];
			}
		}

		$cards  = '';
		$counts = [ 'all' => 0, 'behind' => 0, 'beta' => 0, 'es' => 0 ];
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		foreach ( $sites as $host => $site ) {
			$cards .= self::card( $host, $site, $latest, $beta, $replaced, $format, $counts );
		}

		$x = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';

		$filters = '<div class="sb-ui-seg sb-installs-filter" role="group" aria-label="' . esc_attr__( 'Show', 'sb-tweaks' ) . '">';

		foreach ( [
			'all'    => __( 'All', 'sb-tweaks' ),
			'behind' => __( 'Needs update', 'sb-tweaks' ),
			'beta'   => __( 'On beta', 'sb-tweaks' ),
		] as $key => $label ) {
			$filters .= '<button type="button" data-filter="' . esc_attr( $key ) . '" aria-pressed="' . ( $key === 'all' ? 'true' : 'false' ) . '">' . esc_html( $label ) . ' <span class="sb-installs-filter__n">' . (int) $counts[ $key ] . '</span></button>';
		}

		$filters .= '</div>';

		$search = '<input type="search" class="sb-installs-search" placeholder="' . esc_attr__( 'Search sites', 'sb-tweaks' ) . '" aria-label="' . esc_attr__( 'Search sites', 'sb-tweaks' ) . '">';

		// Selecting (Sam, 31 October 2026): one tick box selects every site
		// showing or clears them all, with the count beside it. Everything you
		// can do goes in the Actions menu: for the selected sites, or for all.
		$select = '<label class="sb-installs-select"><input type="checkbox" class="sb-installs__select-all" aria-label="' . esc_attr__( 'Select all showing, or clear the selection', 'sb-tweaks' ) . '"><span class="sb-installs-select__n">' . esc_html__( 'Select all', 'sb-tweaks' ) . '</span></label>';

		// Modules filter (Sam, 31 October 2026): every module, all ticked; show
		// the sites that allow any ticked one. Only (or Option, Cmd or Ctrl
		// click) leaves just that module ticked.
		$checks = '';

		foreach ( $module_list as $m ) {
			$checks .= '<span class="sb-installs-menu__row"><label><input type="checkbox" class="sb-installs__mod" value="' . esc_attr( $m[0] ) . '" checked><span>' . esc_html( $m[1] ) . '</span></label>';
			/* translators: %s: module name */
			$checks .= '<button type="button" class="sb-installs-menu__only" data-only="' . esc_attr( $m[0] ) . '" aria-label="' . esc_attr( sprintf( __( 'Only %s', 'sb-tweaks' ), $m[1] ) ) . '">' . esc_html__( 'Only', 'sb-tweaks' ) . '</button></span>';
		}

		$modfilter  = '<span class="sb-installs-modfilter"><button type="button" class="button sb-installs__modfilter" aria-haspopup="true" aria-expanded="false"><span class="sb-installs__modfilter-label">' . esc_html__( 'Modules: All', 'sb-tweaks' ) . '</span><span class="sb-installs__actions-arrow">' . $x . '</span></button>';
		$modfilter .= '<span class="sb-installs-menu sb-installs-menu--left" hidden><span class="sb-installs-menu__tools"><button type="button" data-mods="all">' . esc_html__( 'Select all', 'sb-tweaks' ) . '</button><button type="button" data-mods="none">' . esc_html__( 'Select none', 'sb-tweaks' ) . '</button></span>' . $checks . '</span></span>';

		$item = function ( $action, $label, $plugin = '' ) {
			return '<button type="button" role="menuitem" class="sb-installs-menu__item" data-action="' . esc_attr( $action ) . '"' . ( $plugin !== '' ? ' data-plugin="' . esc_attr( $plugin ) . '"' : '' ) . ' aria-disabled="true">' . esc_html( $label ) . '</button>';
		};

		$menu  = '<span class="sb-installs-menu__head">' . esc_html__( 'Selected sites', 'sb-tweaks' ) . '</span>';
		$menu .= $item( 'update', __( 'Update everything waiting', 'sb-tweaks' ) );

		foreach ( self::LABELS as $slug => $label ) {
			/* translators: %s: plugin name */
			$menu .= $item( 'update', sprintf( __( 'Update %s only', 'sb-tweaks' ), $label ), $slug );
		}

		$menu .= $item( 'modules', __( 'Modules', 'sb-tweaks' ) );
		$menu .= '<span class="sb-installs-menu__line" role="separator"></span><span class="sb-installs-menu__head">' . esc_html__( 'All sites', 'sb-tweaks' ) . '</span>';
		$menu .= $item( 'es', __( 'Signature limits', 'sb-tweaks' ) );

		if ( $beta !== '' ) {
			/* translators: %s: beta version */
			$menu .= $item( 'beta', sprintf( __( 'Update beta sites to %s', 'sb-tweaks' ), $beta ) );
		}

		$end  = $select;
		$end .= '<span class="sb-installs-actions"><button type="button" class="button button-primary sb-installs__actions" aria-haspopup="menu" aria-expanded="false"><span class="sb-installs__actions-label">' . esc_html__( 'Actions', 'sb-tweaks' ) . '</span><span class="sb-installs__actions-arrow">' . $x . '</span></button>';
		$end .= '<span class="sb-installs-menu" role="menu" hidden>' . $menu . '</span></span>';

		echo '<div class="sb-installs-sites" data-nonce="' . esc_attr( wp_create_nonce( 'sb_tweaks_push' ) ) . '" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-modules="' . esc_attr( wp_json_encode( $module_list ) ) . '" data-limits="' . esc_attr( wp_json_encode( $limit_list ) ) . '">';

		$bar = [
			[ __( 'Sites', 'sb-tweaks' ), '<span class="sb-ui-count">' . (int) $counts['all'] . '</span>' . $search ],
			[ '', $filters . $modfilter ],
		];

		echo class_exists( 'SB_Tweaks_UI' ) ? SB_Tweaks_UI::bar( $bar, $end, 'sb-installs-bar' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped above.

		echo '<div class="sb-installs-grid">' . $cards . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in card().
		echo '<div class="sb-ui-empty sb-installs-none" hidden><p>' . esc_html__( 'No sites match.', 'sb-tweaks' ) . '</p></div>';
		echo '</div>';

		self::script();
	}

	/**
	 * One site's card: its icon, name and links, a block per installed plugin
	 * (name and Active on the left, version on the right, then its Update and
	 * Push beta buttons, greyed out when there's nothing to do), and Modules,
	 * when it was last seen, Update all and Remove along the foot.
	 */
	private static function card( $host, array $site, array $latest, $beta, array $replaced, $format, array &$counts ) {
		$name  = $site['name'] ?: $host;
		$stale = time() - (int) $site['seen'] > self::STALE;
		$push  = class_exists( 'SB_Tweaks_Push' ) && SB_Tweaks_Push::can_push( $site );
		$can_b = class_exists( 'SB_Tweaks_Push' ) && SB_Tweaks_Push::can_beta( $site );
		$due   = 0;

		foreach ( (array) $site['plugins'] as $s => $i ) {
			if ( isset( $latest[ $s ] ) && version_compare( $i['version'], $latest[ $s ], '<' ) ) {
				$due++;
			}
		}

		$on_beta = strpos( (string) ( $site['plugins']['socialbump-tweaks']['version'] ?? '' ), '-beta' ) !== false;

		list( $icon, $ask, $ring ) = self::icon( $host );

		// What the hub last sent it (or the defaults), for a site that can take it: Tweaks active and a reporter that takes pushes.
		$tweaks = $site['plugins']['socialbump-tweaks'] ?? null;
		$policy = ( $tweaks && $tweaks['active'] && $push && class_exists( 'SB_Tweaks_Policy' ) ) ? SB_Tweaks_Push::policy_for( $host ) : null;

		$counts['es'] += ( $policy && ! empty( $policy['modules']['email_signatures'] ) ) ? 1 : 0;
		$counts['all']++;
		$counts['behind'] += $due > 0 ? 1 : 0;
		$counts['beta']   += $on_beta ? 1 : 0;

		$ext = '<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8"/></svg>';
		$link = function ( $url, $text ) use ( $ext ) {
			return '<a class="sb-installs-card__ext" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $text ) . $ext . '</a>';
		};

		$out  = '<article class="sb-tweaks-card sb-installs-card' . ( $stale ? ' is-stale' : '' ) . '" data-host="' . esc_attr( $host ) . '" data-name="' . esc_attr( $name ) . '" data-search="' . esc_attr( strtolower( $name . ' ' . $host ) ) . '" data-behind="' . ( $due > 0 ? '1' : '' ) . '" data-beta="' . ( $on_beta ? '1' : '' ) . '"' . ( $ask ? ' data-icon-ask="1"' : '' ) . ( $policy ? ' data-policy="' . esc_attr( wp_json_encode( $policy ) ) . '"' : '' ) . '>';
		$out .= '<div class="sb-installs-card__head">';

		// Any site the hub can send to can be ticked: for updates, or for Modules for selected (Sam, 31 October 2026).
		if ( $push && ( $due > 0 || $policy ) ) {
			/* translators: %s: site name */
			$out .= '<input type="checkbox" class="sb-installs__pick" aria-label="' . esc_attr( sprintf( __( 'Select %s', 'sb-tweaks' ), $name ) ) . '">';
		}

		$out .= self::icon_html( $host, $name, $icon, $ring );
		$out .= '<div class="sb-installs-card__who"><h3 class="sb-installs-card__name">' . esc_html( $name ) . '</h3>';
		$out .= '<div class="sb-installs-card__links">' . $link( $site['url'], $host );
		$out .= '<span class="sb-installs-card__admin">' . $link( trailingslashit( $site['url'] ) . 'wp-admin/plugins.php', __( 'Plugins', 'sb-tweaks' ) ) . $link( trailingslashit( $site['url'] ) . 'wp-admin/update-core.php', __( 'Updates', 'sb-tweaks' ) ) . '</span></div></div>';
		$out .= '</div>';

		$out .= '<div class="sb-installs-card__plugins">';

		foreach ( self::LABELS as $slug => $label ) {
			$info = $site['plugins'][ $slug ] ?? null;
			$on   = $info && $info['active'];

			// One line per plugin (Sam, 31 October 2026): a light (on when active,
			// off when deactivated), its name linking to its page on the site, then
			// the version and its buttons on the right.
			$open  = '<div class="sb-installs-card__plugin" data-plugin="' . esc_attr( $slug ) . '" data-label="' . esc_attr( $label ) . '"><div class="sb-installs-card__top"><span class="sb-installs-card__label">';
			// Amber when it needs an update (Sam, 31 October 2026).
			$needs = $info && isset( $latest[ $slug ] ) && version_compare( $info['version'], $latest[ $slug ], '<' );
			$state = $needs ? __( 'Needs update', 'sb-tweaks' ) : ( $on ? __( 'Active', 'sb-tweaks' ) : ( $info ? __( 'Deactivated', 'sb-tweaks' ) : __( 'Not installed', 'sb-tweaks' ) ) );
			$open .= '<span class="sb-installs-card__light' . ( $on ? ' is-on' : '' ) . ( $needs ? ' is-due' : '' ) . '" tabindex="0" data-sb-tip="' . esc_attr( $state ) . '"></span>';
			$open .= $on && isset( self::PAGES[ $slug ] ) ? $link( trailingslashit( $site['url'] ) . 'wp-admin/admin.php?page=' . self::PAGES[ $slug ], $label ) : esc_html( $label );
			$open .= '</span>';

			if ( ! $info ) {
				// Not installed: left off the card (Sam, 31 October 2026), unless
				// SocialBUMP Tweaks can be installed to move a plugin one of its
				// modules replaces into it.
				$moves = $slug === 'socialbump-tweaks' ? array_intersect_key( $replaced, (array) $site['plugins'] ) : [];

				if ( $moves && class_exists( 'SB_Tweaks_Push' ) && SB_Tweaks_Push::can_install( $site ) ) {
					/* translators: %s: module name(s) */
					$out .= $open . '<span class="sb-installs-card__state"><span class="sb-installs__none">' . esc_html__( 'Not installed', 'sb-tweaks' ) . '</span></span>';
					$out .= '<span class="sb-installs-card__buttons"><button type="button" class="button button-small sb-installs__install" data-host="' . esc_attr( $host ) . '" data-plugin="socialbump-tweaks" data-job="install" data-sb-tip="' . esc_attr( sprintf( __( 'Moves %s in', 'sb-tweaks' ), implode( ', ', $moves ) ) ) . '">' . esc_html__( 'Install', 'sb-tweaks' ) . '</button></span></div></div>';
				}

				continue;
			}

			$behind = isset( $latest[ $slug ] ) && version_compare( $info['version'], $latest[ $slug ], '<' );
			$beta_v = strpos( $info['version'], '-beta' ) !== false;
			$class  = 'sb-installs__version' . ( $behind ? ' is-behind' : '' ) . ( $beta_v ? ' is-beta' : '' ) . ( $info['active'] ? '' : ' is-off' );
			/* translators: %s: version number */
			$tip    = $behind ? ' data-sb-tip="' . esc_attr( sprintf( __( 'The hub has %s', 'sb-tweaks' ), $latest[ $slug ] ) ) . '" tabindex="0"' : '';

			$out .= $open . '<span class="sb-installs-card__state"><span class="' . esc_attr( $class ) . '"' . $tip . '>' . esc_html( $info['version'] ) . '</span></span>';

			// The same buttons on every card, greyed out when there's nothing to do (Sam, 31 October 2026).
			$up = $behind && $push;
			/* translators: %s: version number */
			$out .= '<span class="sb-installs-card__buttons"><button type="button" class="button button-small sb-installs__push" data-host="' . esc_attr( $host ) . '" data-plugin="' . esc_attr( $slug ) . '"' . ( $up ? ' data-sb-tip="' . esc_attr( sprintf( __( 'To %s', 'sb-tweaks' ), $latest[ $slug ] ) ) . '"' : ' disabled' ) . '>' . esc_html__( 'Update', 'sb-tweaks' ) . '</button>';

			// Push beta, or Update beta for a site already on one, when the beta is newer than what it has.
			if ( $slug === 'socialbump-tweaks' ) {
				$go = $beta !== '' && $can_b && version_compare( $info['version'], $beta, '<' );
				/* translators: %s: beta version */
				$out .= '<button type="button" class="button button-small sb-installs__beta" data-host="' . esc_attr( $host ) . '" data-plugin="' . esc_attr( $slug ) . '" data-job="beta"' . ( $beta_v ? ' data-on-beta="1"' : '' ) . ( $go ? ' data-sb-tip="' . esc_attr( sprintf( __( 'To %s', 'sb-tweaks' ), $beta ) ) . '"' : ' disabled' ) . '>' . esc_html( $beta_v ? __( 'Update beta', 'sb-tweaks' ) : __( 'Push beta', 'sb-tweaks' ) ) . '</button>';
			}

			$out .= '</span></div>' . ( $slug === 'socialbump-tweaks' ? self::es_line( $policy ) : '' ) . '</div>';
		}

		$out .= '</div>';
		$out .= '<div class="sb-installs-card__foot">';

		// Which modules it may use and its limits (Sam, 31 October 2026), sent signed from a popup.
		if ( $policy ) {
			$allowed = count( array_filter( $policy['modules'] ) );

			/* translators: 1: modules allowed, 2: modules in all */
			$out .= '<button type="button" class="button sb-installs__modules" data-host="' . esc_attr( $host ) . '">' . esc_html( sprintf( __( 'Modules %1$d of %2$d', 'sb-tweaks' ), $allowed, count( $policy['modules'] ) ) ) . '</button>';
		}

		// When it last checked in, with the date and time as its tip.
		/* translators: %s: time since, e.g. 3 hours */
		// Silent for over three days: a red Disconnected pill instead, the last time it was heard from as its tip (Sam, 31 October 2026).
		if ( $stale ) {
			/* translators: 1: date and time, 2: time since, e.g. 5 days */
			$out .= '<span class="sb-installs-chip is-danger sb-installs-card__gone" tabindex="0" data-sb-tip="' . esc_attr( sprintf( __( 'Last heard from %1$s (%2$s ago)', 'sb-tweaks' ), wp_date( $format, (int) $site['seen'] ), human_time_diff( (int) $site['seen'] ) ) ) . '">' . esc_html__( 'Disconnected', 'sb-tweaks' ) . '</span>';
		} else {
			$out .= '<span class="sb-installs-card__seen" tabindex="0" data-sb-tip="' . esc_attr( wp_date( $format, (int) $site['seen'] ) ) . '"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>' . esc_html( sprintf( __( '%s ago', 'sb-tweaks' ), human_time_diff( (int) $site['seen'] ) ) ) . '</span>';
		}

		$out .= '<span class="sb-installs-card__end">';

		if ( $push && $due > 0 ) {
			$out .= '<button type="button" class="button sb-installs__push-all">' . esc_html__( 'Update all', 'sb-tweaks' ) . '</button>';
		}

		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= '<input type="hidden" name="action" value="sb_tweaks_forget_install"><input type="hidden" name="host" value="' . esc_attr( $host ) . '">';
		$out .= wp_nonce_field( 'sb_tweaks_forget_install', '_wpnonce', true, false );
		/* translators: %s: site address */
		$out .= '<button type="submit" class="button sb-tweaks-button--danger sb-tweaks-button--icon sb-installs__forget" aria-label="' . esc_attr__( 'Remove', 'sb-tweaks' ) . '" data-sb-tip="' . esc_attr__( 'Remove from the list', 'sb-tweaks' ) . '" data-sb-tweaks-confirm="' . esc_attr( sprintf( __( 'Remove %s from the list? It comes back if it checks in again.', 'sb-tweaks' ), $host ) ) . '" data-sb-tweaks-confirm-title="' . esc_attr__( 'Remove site', 'sb-tweaks' ) . '" data-sb-tweaks-confirm-action="' . esc_attr__( 'Remove', 'sb-tweaks' ) . '" data-sb-tweaks-confirm-tone="danger"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></form>';
		$out .= '</span></div></article>';

		return $out;
	}

	/**
	 * Under Tweaks on a card: Email Signatures and its limits, when the site
	 * may use it (Sam, 31 October 2026). The script draws the same after a save.
	 */
	private static function es_line( $policy ) {
		if ( ! $policy || empty( $policy['modules']['email_signatures'] ) ) {
			return '';
		}

		// Signatures on the left, the limits on the right split by thin lines, ∞ for no limit (Sam, 31 October 2026).
		// Signatures opens this site's limits on their own (Sam, 31 October 2026).
		$out = '<div class="sb-installs-card__sub"><button type="button" class="sb-installs-card__sub-name sb-installs__es-one-open" data-sb-tip="' . esc_attr__( 'Change limits', 'sb-tweaks' ) . '">' . esc_html__( 'Signatures', 'sb-tweaks' ) . '</button><span class="sb-installs-card__stats">';

		foreach ( SB_Tweaks_Policy::LIMITS['email_signatures'] as $key => $default ) {
			$value = (int) ( $policy['limits']['email_signatures'][ $key ] ?? 0 );
			$out  .= '<span class="sb-installs-card__stat">' . esc_html( SB_Tweaks_Policy::LIMIT_LABELS[ $key ] ?? $key ) . ' ' . ( $value > 0 ? '<strong>' . (int) $value . '</strong>' : '<strong class="is-none" tabindex="0" data-sb-tip="' . esc_attr__( 'No limit', 'sb-tweaks' ) . '" aria-label="' . esc_attr__( 'No limit', 'sb-tweaks' ) . '">&infin;</strong>' ) . '</span>';
		}

		return $out . '</span></div>';
	}

	/** The cards' buttons, the bar's search, filter and bulk updates, and the Modules popup. */
	private static function script() {
		$text = [
			'title'        => __( 'Updating sites', 'sb-tweaks' ),
			/* translators: 1: position, 2: total */
			'step'         => __( 'Updating %1$s of %2$s', 'sb-tweaks' ),
			'complete'     => __( 'Updates complete', 'sb-tweaks' ),
			/* translators: 1: number updated, 2: number failed */
			'mixed'        => __( '%1$s updated, %2$s failed', 'sb-tweaks' ),
			'working'      => __( 'Updating...', 'sb-tweaks' ),
			'again'        => __( 'Try again', 'sb-tweaks' ),
			'failed'       => __( 'The update did not complete.', 'sb-tweaks' ),
			'done'         => __( 'updated to', 'sb-tweaks' ),
			'bad'          => __( 'did not update', 'sb-tweaks' ),
			'select_all'   => __( 'Select all', 'sb-tweaks' ),
			/* translators: %d: number of sites ticked */
			'n_selected'   => __( '%d selected', 'sb-tweaks' ),
			'mods_all'     => __( 'Modules: All', 'sb-tweaks' ),
			'mods_none'    => __( 'Modules: None', 'sb-tweaks' ),
			/* translators: %s: module name */
			'mods_one'     => __( 'Modules: %s', 'sb-tweaks' ),
			/* translators: 1: modules ticked, 2: modules in all */
			'mods_n'       => __( 'Modules: %1$s of %2$s', 'sb-tweaks' ),
			'actions'      => __( 'Actions', 'sb-tweaks' ),
			/* translators: %d: number of sites ticked */
			'actions_n'    => __( 'Actions (%d)', 'sb-tweaks' ),
			'why_select'   => __( 'Select some sites first', 'sb-tweaks' ),
			'why_update'   => __( 'None of the selected sites need this update', 'sb-tweaks' ),
			'why_modules'  => __( 'None of the selected sites can take module changes', 'sb-tweaks' ),
			'why_es'       => __( 'No site has Email Signatures allowed', 'sb-tweaks' ),
			'why_beta'     => __( 'No site on a beta is behind', 'sb-tweaks' ),
			'active'       => __( 'Active', 'sb-tweaks' ),
			'deactivated'  => __( 'Deactivated', 'sb-tweaks' ),
			/* translators: %d: number of sites */
			'modules_for'  => __( 'Modules on %d sites', 'sb-tweaks' ),
			'leave'        => __( 'Leave as is', 'sb-tweaks' ),
			'allow'        => __( 'Allow', 'sb-tweaks' ),
			'hide'         => __( 'Hide', 'sb-tweaks' ),
			'all_on'       => __( 'all allowed', 'sb-tweaks' ),
			'all_off'      => __( 'all hidden', 'sb-tweaks' ),
			'mixed'        => __( 'mixed', 'sb-tweaks' ),
			'es'           => __( 'Email Signatures', 'sb-tweaks' ),
			'sigs'         => __( 'Signatures', 'sb-tweaks' ),
			'change_limits' => __( 'Change limits', 'sb-tweaks' ),
			/* translators: %s: site name */
			'es_one'       => __( 'Email Signatures limits on %s', 'sb-tweaks' ),
			'es_title'     => __( 'Email Signatures limits', 'sb-tweaks' ),
			'site'         => __( 'Site', 'sb-tweaks' ),
			'no_limit'     => __( 'no limit', 'sb-tweaks' ),
			'nothing'      => __( 'Nothing changed', 'sb-tweaks' ),
			/* translators: 1: position, 2: total */
			'saving'       => __( 'Saving %1$s of %2$s', 'sb-tweaks' ),
			/* translators: 1: number saved, 2: number failed */
			'mixed_saved'  => __( '%1$s saved, %2$s failed', 'sb-tweaks' ),
			/* translators: %s: site name */
			'modules'      => __( 'Modules on %s', 'sb-tweaks' ),
			'modules_note' => __( 'Ticked modules can be used there. The rest are hidden.', 'sb-tweaks' ),
			'unlimited'    => __( 'No limit', 'sb-tweaks' ),
			'save'         => __( 'Save', 'sb-tweaks' ),
			'sent'         => __( 'Modules saved on the site', 'sb-tweaks' ),
			'sending'      => __( 'Sending to the site', 'sb-tweaks' ),
			'allowed'      => __( 'Allowed', 'sb-tweaks' ),
			'hidden'       => __( 'Hidden', 'sb-tweaks' ),
			/* translators: 1: modules allowed, 2: modules in all */
			'count'        => __( 'Modules %1$d of %2$d', 'sb-tweaks' ),
		];
		?>
		<style>
		/* Installs page sites as cards (Sam, 31 October 2026), on the SocialBUMP UI tokens. */
		.sb-installs-bar .sb-installs-search { width: 220px; max-width: 100%; }
		.sb-installs-filter__n { color: inherit; font-variant-numeric: tabular-nums; opacity: 0.7; }
		body.sb-ui .sb-tweaks-wrap .sb-installs-filter button[aria-pressed="true"] { background: var(--sbui-btn); color: var(--sbui-on-green); font-weight: 600; }
		/* The select box and its count, then Actions with its menu (Sam, 31 October 2026). */
		/* In a box like a button, the height of Actions and wide enough for "16 selected", so nothing shifts as the count changes. */
		.sb-installs-select { box-sizing: border-box; display: inline-flex; align-self: stretch; align-items: center; gap: 8px; min-width: 140px; margin: 0; padding: 0 12px 0 9px; border: 1px solid var(--sbui-border-strong, #c3c4c7); border-radius: var(--sbui-r, 8px); background: var(--sbui-surface, #fff); color: var(--sbui-text-2, #50575e); font-weight: 600; cursor: pointer; white-space: nowrap; transition: border-color 0.15s; }
		.sb-installs-select:hover { border-color: var(--sbui-text-3, #787c82); }
		.sb-tweaks-wrap .sb-installs-select input[type="checkbox"] { margin: 0 !important; }
		body.sb-ui .sb-tweaks-wrap .sb-installs-select input[type="checkbox"]:indeterminate { border-color: var(--sbui-green); background: var(--sbui-green); }
		body.sb-ui .sb-tweaks-wrap .sb-installs-select input[type="checkbox"]:indeterminate::before { content: ""; width: 8px; height: 2px; margin: 0; border: 0; background: var(--sbui-on-green); transform: none; }
		.sb-installs-actions { position: relative; display: inline-flex; }
		/* Actions: the word on the left, the arrow on the right on a slightly darker strip (Sam, 31 October 2026). */
		body.sb-ui .sb-tweaks-wrap .button.sb-installs__modfilter, .sb-tweaks-wrap .button.sb-installs__modfilter,
		body.sb-ui .sb-tweaks-wrap .button.sb-installs__actions, .sb-tweaks-wrap .button.sb-installs__actions { justify-content: space-between; align-items: stretch; gap: 10px; min-width: 150px; padding: 0 0 0 14px; overflow: hidden; }
		.sb-installs__actions-label { align-self: center; }
		.sb-installs__actions-arrow { display: flex; align-items: center; padding: 0 9px; border-left: 1px solid rgba(22, 23, 29, 0.1); background: rgba(22, 23, 29, 0.08); }
		.sb-installs-menu__head { padding: 6px 10px 2px; color: var(--sbui-text-3, #787c82); font-size: 11px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; }
		.sb-installs-menu__line { height: 1px; margin: 6px 4px; background: var(--sbui-border, #dcdcde); }
		.sb-installs-menu { position: absolute; top: calc(100% + 6px); right: 0; z-index: 60; display: grid; min-width: 240px; padding: 6px; border: 1px solid var(--sbui-border, #dcdcde); border-radius: var(--sbui-r, 8px); background: var(--sbui-surface, #fff); box-shadow: var(--sbui-shadow-2, 0 8px 24px rgba(0, 0, 0, 0.1)); }
		.sb-installs-menu[hidden] { display: none; }
		/* The Modules filter: a button with its arrow strip, a menu of tick boxes opening to the left edge. */
		.sb-installs-modfilter { position: relative; display: inline-flex; margin-left: 4px; }
		body.sb-ui .sb-tweaks-wrap .button.sb-installs__modfilter, .sb-tweaks-wrap .button.sb-installs__modfilter { min-width: 200px; max-width: 240px; }
		.sb-installs__modfilter-label { align-self: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		.sb-installs__modfilter .sb-installs__actions-arrow { border-left-color: var(--sbui-border, #dcdcde); background: var(--sbui-surface-2, #f6f7f7); }
		body.sb-ui .sb-tweaks-wrap .button.sb-installs__modfilter.is-filtering { border-color: var(--sbui-green-ink, #2271b1); color: var(--sbui-green-ink, #2271b1); }
		.sb-installs-menu--left { right: auto; left: 0; }
		.sb-installs-menu__tools { display: flex; gap: 14px; margin-bottom: 4px; padding: 4px 10px 8px; border-bottom: 1px solid var(--sbui-border, #dcdcde); }
		.sb-installs-menu__tools button, .sb-installs-menu__only { padding: 0; border: 0; background: none; color: var(--sbui-green-ink, #2271b1); font: inherit; font-size: 12px; cursor: pointer; }
		.sb-installs-menu__tools button:hover, .sb-installs-menu__only:hover { text-decoration: underline; }
		.sb-installs-menu__row { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 0 10px; border-radius: var(--sbui-r-sm, 6px); }
		.sb-installs-menu__row:hover { background: var(--sbui-surface-3, #f0f0f1); }
		.sb-installs-menu__row label { display: flex; flex: 1; align-items: center; gap: 8px; margin: 0; padding: 7px 0; color: var(--sbui-text, #1d2327); cursor: pointer; white-space: nowrap; }
		.sb-tweaks-wrap .sb-installs-menu__row input[type="checkbox"] { margin: 0 !important; }
		.sb-installs-menu__only { visibility: hidden; }
		.sb-installs-menu__row:hover .sb-installs-menu__only, .sb-installs-menu__only:focus-visible { visibility: visible; }
		.sb-installs-menu__item { padding: 8px 10px; border: 0; border-radius: var(--sbui-r-sm, 6px); background: none; color: var(--sbui-text, #1d2327); font: inherit; text-align: left; white-space: nowrap; cursor: pointer; }
		.sb-installs-menu__item:hover:not([aria-disabled="true"]), .sb-installs-menu__item:focus-visible { background: var(--sbui-surface-3, #f0f0f1); outline: 0; }
		.sb-installs-menu__item[aria-disabled="true"] { color: var(--sbui-text-3, #a7aaad); cursor: not-allowed; }
		.sb-installs-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(380px, 100%), 1fr)); gap: 16px; margin-top: 28px; }
		.sb-tweaks-card.sb-installs-card, body.sb-ui .sb-tweaks-card.sb-installs-card { display: flex; flex-direction: column; padding: 0; overflow: hidden; text-align: left; }
		.sb-installs-card[hidden] { display: none !important; }
		body.sb-ui .sb-tweaks-card.sb-installs-card.is-stale { border-color: var(--sbui-danger-line); }
		.sb-installs-card__head { display: flex; align-items: center; gap: 12px; padding: 16px 18px 14px; }
		.sb-installs-card__head .sb-installs__pick { flex: none; align-self: center; margin: 0 !important; }
		.sb-installs-card__who { flex: 1; min-width: 0; }
		.sb-tweaks-wrap .sb-installs-card__name { margin: 0; overflow: hidden; color: var(--sbui-text, #1d2327); font-size: 15px; font-weight: 600; line-height: 1.3; text-overflow: ellipsis; white-space: nowrap; }
		/* The site's icon in a circle, or its first letter on its colour (Sam, 31 October 2026). */
		.sb-installs-card__icon { position: relative; flex: none; display: grid; place-items: center; box-sizing: border-box; width: 52px; height: 52px; overflow: hidden; border: 3px solid color-mix(in srgb, var(--sb-installs-icon) 70%, #000); border-radius: 50%; background: var(--sb-installs-icon); color: #fff; font-size: 22px; font-weight: 600; line-height: 1; }
		/* A 3px ring in the icon's own colour, or neutral when it has none (Sam, 31 October 2026). */
		/* The ring sits behind the image (no seam where the two meet), a darker shade of the icon's colour. */
		.sb-installs-card__icon.has-image { --sb-installs-ring-c: color-mix(in srgb, var(--sb-installs-ring) 75%, #000); border: 0; background: var(--sb-installs-ring-c); }
		.sb-installs-card__icon.has-image:not([style*="--sb-installs-ring"]) { --sb-installs-ring-c: var(--sbui-border-strong, #c3c4c7); }
		/* An inner ring over the image's own edge, which otherwise shows as a thin light line (Sam, 31 October 2026). */
		.sb-installs-card__icon.has-image::after { content: ""; position: absolute; inset: 2px; z-index: 1; border-radius: 50%; box-shadow: inset 0 0 0 2px var(--sb-installs-ring-c); pointer-events: none; }
		.sb-installs-card__icon.has-image .sb-installs-card__letter { visibility: hidden; }
		.sb-installs-card__icon img { position: absolute; inset: 3px; width: calc(100% - 6px); height: calc(100% - 6px); border-radius: 50%; object-fit: cover; background: #fff; }
		.sb-installs-card__links { display: flex; flex-direction: column; align-items: flex-start; gap: 2px; margin-top: 3px; font-size: 12.5px; }
		.sb-installs-card__admin { display: flex; gap: 14px; }
		/* Links look like links (Sam, 31 October 2026): underlined, an arrow for the ones that open the site. */
		.sb-tweaks-wrap a.sb-installs-card__ext, .sb-tweaks-wrap a.sb-installs-card__active { display: inline-flex; align-items: center; gap: 3px; color: var(--sbui-green-ink, #2271b1); text-decoration: none; text-decoration-thickness: 1px; text-underline-offset: 3px; }
		.sb-tweaks-wrap a.sb-installs-card__ext:hover, .sb-tweaks-wrap a.sb-installs-card__active:hover, .sb-tweaks-wrap a.sb-installs-card__ext:focus-visible { text-decoration: underline; }
		.sb-installs-card__ext svg { flex: none; opacity: 0.75; }
		.sb-installs-card__seen { display: inline-flex; align-items: center; gap: 5px; margin-left: 4px; color: var(--sbui-text-3, #787c82); font-size: 12px; white-space: nowrap; }
		.sb-installs-card__gone { margin-left: 4px; }
		.sb-installs-card__plugins { flex: 1 0 auto; margin: 0 18px; padding-bottom: 6px; border-top: 1px solid var(--sbui-border, #dcdcde); }
		.sb-installs-card__plugin { padding: 10px 0; border-bottom: 1px solid var(--sbui-border, #dcdcde); }
		.sb-installs-card__top { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px; }
		.sb-installs-card__plugin:last-child { border-bottom: 0; }
		.sb-installs-card__label { display: inline-flex; flex: 1 0 auto; align-items: center; gap: 8px; color: var(--sbui-text, #1d2327); font-size: 13px; font-weight: 600; }
		.sb-tweaks-wrap .sb-installs-card__label a.sb-installs-card__ext { font-size: 13px; font-weight: 600; }
		/* The light: lit green when active, off (grey) when deactivated or not installed. */
		.sb-installs-card__light { flex: none; width: 9px; height: 9px; border-radius: 50%; background: var(--sbui-border-strong, #c3c4c7); box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.15); }
		.sb-installs-card__light.is-on { background: var(--sbui-green, #85df07); box-shadow: 0 0 0 3px var(--sbui-green-soft, rgba(133, 223, 7, 0.18)), 0 0 6px var(--sbui-green, #85df07); }
		.sb-installs-card__light.is-due { background: var(--sbui-warn-solid, #ffc94d); box-shadow: 0 0 0 3px var(--sbui-warn-soft, rgba(255, 201, 77, 0.22)), 0 0 6px var(--sbui-warn-solid, #ffc94d); }
		.sb-installs-card__state { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; min-width: 0; font-size: 12px; }
		.sb-installs-card__state small { color: var(--sbui-text-3, #787c82); font-size: 12px; }
		.sb-installs-card__state small.sb-installs__deactivated { color: var(--sbui-danger, #d63638); font-weight: 600; }
		.sb-installs-card__buttons { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
		.sb-installs-card__buttons small { color: var(--sbui-text-3, #787c82); font-size: 12px; }
		.sb-installs-card__plugin .sb-installs__push-error { display: block; margin-top: 6px; color: var(--sbui-danger, #d63638); font-size: 12px; }
		.sb-tweaks-wrap .sb-installs-chip { display: inline-flex; align-items: center; height: 22px; padding: 0 9px; border: 1px solid var(--sbui-border, #dcdcde); border-radius: 999px; background: var(--sbui-surface-2, #f6f7f7); color: var(--sbui-text-2, #50575e); font-size: 12px; box-sizing: border-box; white-space: nowrap; }
		.sb-tweaks-wrap .sb-installs-chip.is-danger { border-color: var(--sbui-danger-line, #ffb8ab); background: var(--sbui-danger-soft, #fcf0f1); color: var(--sbui-danger, #d63638); font-weight: 600; }
		.sb-installs-card__foot { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 12px 14px; border-top: 1px solid var(--sbui-border, #dcdcde); background: var(--sbui-surface-2, #f6f7f7); }
		.sb-installs-card__end { display: flex; align-items: center; gap: 6px; margin-left: auto; }
		.sb-installs-card__end form { display: contents; }
		.sb-installs-bar .sb-ui-bar__end { flex-wrap: wrap; }
		/* Phones: each part of the bar on its own line, the filter wrapping inside it. */
		@media (max-width: 782px) {
			.sb-installs-bar .sb-ui-bar__group, .sb-installs-bar .sb-ui-bar__end { width: 100%; }
			.sb-installs-bar .sb-installs-search { flex: 1; width: auto; }
			body.sb-ui .sb-installs-filter { flex-wrap: wrap; border-radius: 18px; }
		}
		.sb-installs__policy { display: grid; gap: 10px; }
		/* Several sites: each module with Leave as is, Allow or Hide. */
		.sb-installs__bulk-module { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 0; font-weight: 600; }
		.sb-installs__bulk-module select { width: 210px; font-weight: 400; }
		.sb-installs__bulk-module + .sb-installs__policy-limits { margin-left: 0; }
		/* Signature limits: a row per site, a column per limit. */
		.sb-installs__es { display: grid; grid-template-columns: minmax(0, 1fr) repeat(var(--sb-installs-es-cols, 3), 92px); gap: 8px 10px; align-items: center; max-height: 60vh; overflow-y: auto; padding: 3px 6px 3px 3px; margin: -3px -6px -3px -3px; }
		.sb-installs__es-head { color: var(--sbui-text-3, #787c82); font-size: 11.5px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; }
		.sb-installs__es-name { overflow: hidden; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
		.sb-installs__es input { box-sizing: border-box; width: 100%; min-width: 0; }
		/* Under Tweaks on a card: Email Signatures and its limits. */
		.sb-installs-card__sub { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 4px 10px; margin: 8px 0 0 17px; font-size: 12px; }
		.sb-installs-card__sub-name { padding: 0; border: 0; background: none; color: var(--sbui-text-2, #50575e); font: inherit; font-weight: 600; cursor: pointer; }
		.sb-installs-card__sub-name:hover, .sb-installs-card__sub-name:focus-visible { color: var(--sbui-green-ink, #2271b1); text-decoration: underline; text-underline-offset: 3px; outline: 0; }
		.sb-installs__es-one { margin: 0; }
		.sb-installs-card__stats { display: flex; align-items: center; margin-left: auto; }
		.sb-installs-card__stat { padding: 0 9px; border-left: 1px solid var(--sbui-border-strong, #c3c4c7); color: var(--sbui-text-2, #50575e); line-height: 1.2; white-space: nowrap; }
		.sb-installs-card__stat:first-child { padding-left: 0; border-left: 0; }
		.sb-installs-card__stat:last-child { padding-right: 0; }
		.sb-installs-card__stat strong { color: var(--sbui-text, #1d2327); font-weight: 600; }
		.sb-installs-card__stat strong.is-none { font-size: 14px; line-height: 1; }
		.sb-installs-chip strong { margin-left: 3px; color: var(--sbui-text, #1d2327); font-weight: 600; }
		.sb-installs__policy-module .sb-tweaks-dialog__check { margin: 0; }
		.sb-installs__policy-limits { display: grid; grid-template-columns: repeat( 3, minmax( 0, 1fr ) ); gap: 10px; margin: 8px 0 4px 26px; }
		.sb-installs__policy-limits .sb-tweaks-dialog__field { margin: 0; }
		.sb-installs__policy-limits input { width: 100%; }
		</style>
		<script>
		( function () {
			var root = document.querySelector( '.sb-installs-sites' );

			if ( ! root ) {
				return;
			}

			var text     = <?php echo wp_json_encode( $text ); ?>;
			var menu     = root.querySelector( '.sb-installs-actions .sb-installs-menu' );
			var modBtn   = root.querySelector( '.sb-installs__modfilter' );
			var modLabel = root.querySelector( '.sb-installs__modfilter-label' );
			var modMenu  = root.querySelector( '.sb-installs-modfilter .sb-installs-menu' );
			var modBoxes = Array.prototype.slice.call( root.querySelectorAll( '.sb-installs__mod' ) );
			var items    = Array.prototype.slice.call( root.querySelectorAll( '.sb-installs-menu__item' ) );
			var actions  = root.querySelector( '.sb-installs__actions' );
			var actLabel = root.querySelector( '.sb-installs__actions-label' );
			var master   = root.querySelector( '.sb-installs__select-all' );
			var selN     = root.querySelector( '.sb-installs-select__n' );
			var search   = root.querySelector( '.sb-installs-search' );
			var none     = root.querySelector( '.sb-installs-none' );
			var cards    = Array.prototype.slice.call( root.querySelectorAll( '.sb-installs-card' ) );
			var modules  = JSON.parse( root.dataset.modules || '[]' );
			var limits   = JSON.parse( root.dataset.limits || '{}' );
			var filter   = 'all';
			var running  = false;

			function el( tag, cls, txt ) {
				var node = document.createElement( tag );

				if ( cls ) {
					node.className = cls;
				}

				if ( txt !== undefined ) {
					node.textContent = txt;
				}

				return node;
			}

			function cardOf( node ) {
				return node.closest( '.sb-installs-card' );
			}

			// Ticked sites that are showing: a filtered out card doesn't count.
			function picks() {
				return Array.prototype.filter.call( root.querySelectorAll( '.sb-installs__pick:checked' ), function ( box ) { return ! cardOf( box ).hidden; } ).map( cardOf );
			}

			function showing() {
				return Array.prototype.filter.call( root.querySelectorAll( '.sb-installs__pick' ), function ( box ) { return ! cardOf( box ).hidden; } );
			}

			// Why a menu item can't run now, or '' when it can.
			function blocked( item, ticked ) {
				switch ( item.dataset.action ) {
					case 'update':
						if ( ! ticked.length ) {
							return text.why_select;
						}

						return ticked.some( function ( card ) { return card.querySelector( '.sb-installs__push' + ( item.dataset.plugin ? '[data-plugin="' + item.dataset.plugin + '"]' : '' ) + ':not(:disabled)' ); } ) ? '' : text.why_update;
					case 'modules':
						if ( ! ticked.length ) {
							return text.why_select;
						}

						return ticked.some( function ( card ) { return card.dataset.policy; } ) ? '' : text.why_modules;
					case 'es':
						return cards.some( esOn ) ? '' : text.why_es;
					case 'beta':
						return betaJobs().length ? '' : text.why_beta;
				}

				return '';
			}

			// The bar follows the ticks: the select box (ticked, a dash for some,
			// empty for none), the count, Actions (n), and each menu item greyed
			// with its reason as the tip when it can't run.
			function refresh() {
				var ticked = picks();
				var boxes  = showing();

				master.checked       = ticked.length > 0 && ticked.length === boxes.length;
				master.indeterminate = ticked.length > 0 && ticked.length < boxes.length;
				master.disabled      = running || ! boxes.length;
				selN.textContent     = ticked.length ? text.n_selected.replace( '%d', ticked.length ) : text.select_all;
				actLabel.textContent = ticked.length ? text.actions_n.replace( '%d', ticked.length ) : text.actions;
				actions.disabled     = running;

				items.forEach( function ( item ) {
					var why = running ? ' ' : blocked( item, ticked );

					item.setAttribute( 'aria-disabled', why ? 'true' : 'false' );

					if ( why.trim() ) {
						item.setAttribute( 'data-sb-tip', why );
					} else {
						item.removeAttribute( 'data-sb-tip' );
					}
				} );

				if ( running ) {
					closeMenu();
				}
			}

			// A change of filter or search clears the selection, so nothing is
			// ticked out of sight (Sam, 31 October 2026).
			function clearPicks() {
				root.querySelectorAll( '.sb-installs__pick:checked' ).forEach( function ( box ) { box.checked = false; } );
			}

			// Search and filter: a card shows when it matches both.
			function apply() {
				var q     = search.value.trim().toLowerCase();
				var shown = 0;

				cards.forEach( function ( card ) {
					var match = ( ! q || card.dataset.search.indexOf( q ) !== -1 ) && ( filter === 'all' || card.dataset[ filter ] === '1' ) && modMatch( card );

					card.hidden = ! match;
					shown += match ? 1 : 0;
				} );

				none.hidden = shown > 0;
				refresh();
			}

			// The filter counts, after an update moves a site off Behind or on to a beta.
			function recount() {
				root.querySelectorAll( '.sb-installs-filter [data-filter]' ).forEach( function ( button ) {
					var key = button.dataset.filter;

					button.querySelector( '.sb-installs-filter__n' ).textContent = key === 'all' ? cards.length : cards.filter( function ( card ) { return card.dataset[ key ] === '1'; } ).length;
				} );
			}

			function closeMenu() {
				if ( menu && ! menu.hidden ) {
					menu.hidden = true;
					actions.setAttribute( 'aria-expanded', 'false' );
				}
			}

			function closeModMenu() {
				if ( modMenu && ! modMenu.hidden ) {
					modMenu.hidden = true;
					modBtn.setAttribute( 'aria-expanded', 'false' );
				}
			}

			// The modules ticked in the filter; all of them means no filtering.
			function modsTicked() {
				return modBoxes.filter( function ( box ) { return box.checked; } ).map( function ( box ) { return box.value; } );
			}

			// A site shows when it allows any ticked module (what the hub last sent it).
			function modMatch( card ) {
				var ticked = modsTicked();

				if ( ticked.length === modBoxes.length ) {
					return true;
				}

				var allowed = policyOf( card ).modules || {};

				return ticked.some( function ( id ) { return allowed[ id ]; } );
			}

			// The button names what's ticked: All, None, the one, or n of n.
			function modName() {
				var ticked = modBoxes.filter( function ( box ) { return box.checked; } );

				if ( ticked.length === modBoxes.length ) {
					return text.mods_all;
				}

				if ( ! ticked.length ) {
					return text.mods_none;
				}

				if ( ticked.length === 1 ) {
					return text.mods_one.replace( '%s', ticked[0].nextElementSibling.textContent );
				}

				return text.mods_n.replace( '%1$s', ticked.length ).replace( '%2$s', modBoxes.length );
			}

			// Ticks changed: name it, clear the selection and filter again.
			function modChanged() {
				modLabel.textContent = modName();
				modBtn.classList.toggle( 'is-filtering', modsTicked().length !== modBoxes.length );
				clearPicks();
				apply();
			}

			// One plugin on one site. Updates its row and returns the result.
			function push( button ) {
				var row   = button.closest( '.sb-installs-card__plugin' );
				var card  = cardOf( row );
				var state = row.querySelector( '.sb-installs-card__state' );
				var pill  = row.querySelector( '.sb-installs__version' );
				var body  = new URLSearchParams( { action: 'sb_tweaks_push', _ajax_nonce: root.dataset.nonce, host: button.dataset.host, plugin: button.dataset.plugin, job: button.dataset.job || 'update' } );

				var label = button.textContent;

				button.disabled = true;
				button.textContent = text.working;

				if ( window.SBTweaksUI && window.SBTweaksUI.hideTip ) {
					window.SBTweaksUI.hideTip();
				}

				return fetch( root.dataset.ajax, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.catch( function () { return { ok: false, message: text.failed }; } )
					.then( function ( res ) {
						res = res || { ok: false, message: text.failed };

						var note = row.querySelector( '.sb-installs__push-error' );

						if ( res.ok ) {
							// An install starts from Not installed, so make its pill.
							if ( ! pill ) {
								state.textContent = '';
								pill = state.appendChild( el( 'span', 'sb-installs__version' ) );
							}

							pill.textContent = res.version || pill.textContent;
							pill.classList.remove( 'is-behind' );
							pill.removeAttribute( 'data-sb-tip' );
							pill.removeAttribute( 'tabindex' );
							pill.classList.toggle( 'is-beta', /-beta/.test( pill.textContent ) );

							// Done: the button stays, greyed out, so every card keeps its shape.
							// On the beta now, the release it is ahead of has nothing to do either.
							var older = button.classList.contains( 'sb-installs__beta' ) ? row.querySelector( '.sb-installs__push' ) : null;

							[ button, older ].forEach( function ( b ) {
								if ( b ) {
									b.disabled = true;
									b.removeAttribute( 'data-sb-tip' );
								}
							} );

							button.textContent = label;
							note && note.remove();
							var light = row.querySelector( '.sb-installs-card__light' );

							// Installed: on. Updated: no longer amber, still on or off as it was.
							if ( button.dataset.job === 'install' ) {
								light.classList.add( 'is-on' );
							}

							light.classList.remove( 'is-due' );
							light.setAttribute( 'data-sb-tip', light.classList.contains( 'is-on' ) ? text.active : text.deactivated );

							card.dataset.behind = card.querySelector( '.sb-installs__version.is-behind' ) ? '1' : '';
							card.dataset.beta   = /-beta/.test( ( card.querySelector( '[data-plugin="socialbump-tweaks"] .sb-installs__version' ) || {} ).textContent || '' ) ? '1' : '';
							recount();
						} else {
							button.disabled = false;
							button.textContent = text.again;
							note = note || row.appendChild( el( 'small', 'sb-installs__push-error' ) );
							note.textContent = res.message || text.failed;
						}

						tidy( card );

						return res;
					} );
			}

			// A card with nothing left to update loses its tick box and Update all.
			function tidy( card ) {
				// Nothing left to update: no Update all, and no tick box unless it can still take Modules for selected.
				if ( ! card.querySelector( '.sb-installs__push:not(:disabled)' ) ) {
					[ '.sb-installs__push-all', card.dataset.policy ? '' : '.sb-installs__pick' ].forEach( function ( s ) { var n = s && card.querySelector( s ); n && n.remove(); } );
				}

				refresh();
			}

			// Every waiting update on these sites (or one plugin's), one at a
			// time, in the framework's progress popup.
			function run( list, plugin ) {
				var jobs = [];

				list.forEach( function ( card ) {
					card.querySelectorAll( '.sb-installs__push:not(:disabled)' ).forEach( function ( button ) {
						if ( ! plugin || button.dataset.plugin === plugin ) {
							jobs.push( { card: card, button: button } );
						}
					} );
				} );

				go( jobs );
			}

			// Update beta sites: every site already on a beta, to the latest one.
			function betaJobs() {
				return Array.prototype.map.call( root.querySelectorAll( '.sb-installs__beta[data-on-beta]:not(:disabled)' ), function ( button ) {
					return { card: cardOf( button ), button: button };
				} );
			}

			function go( jobs ) {
				if ( running || ! jobs.length || ! window.SBTweaksProgress ) {
					return;
				}

				running = true;
				refresh();

				var job  = window.SBTweaksProgress.open( text.title );
				var good = 0;
				var bad  = 0;

				jobs.reduce( function ( chain, item, i ) {
					return chain.then( function () {
						var what = item.card.dataset.name + ': ' + item.button.closest( '.sb-installs-card__plugin' ).dataset.label;

						job.step( i, jobs.length, what );
						job.status( text.step.replace( '%1$s', i + 1 ).replace( '%2$s', jobs.length ) );

						return push( item.button ).then( function ( res ) {
							if ( res.ok ) {
								good++;
								job.log( what, text.done + ' ' + ( res.version || '' ) );
							} else {
								bad++;
								job.log( what, text.bad + ( res.message ? ': ' + res.message : '' ), true );
							}

							job.step( i + 1, jobs.length );
						} );
					} );
				}, Promise.resolve() ).then( function () {
					running = false;
					refresh();
					job.finish( bad ? text.mixed.replace( '%1$s', good ).replace( '%2$s', bad ) : text.complete );
				} );
			}

			function policyOf( card ) {
				return JSON.parse( card.dataset.policy || '{}' );
			}

			function esOn( card ) {
				return !! ( policyOf( card ).modules || {} ).email_signatures;
			}

			// The line under Tweaks: Email Signatures and its limits, when the site may use it.
			function esLine( card, policy ) {
				var row  = card.querySelector( '.sb-installs-card__plugin[data-plugin="socialbump-tweaks"]' );
				var old  = row && row.querySelector( '.sb-installs-card__sub' );
				var keys = limits.email_signatures || [];

				old && old.remove();

				if ( ! row || ! policy.modules || ! policy.modules.email_signatures ) {
					return;
				}

				var line  = el( 'div', 'sb-installs-card__sub' );
				var stats = el( 'span', 'sb-installs-card__stats' );

				var open = el( 'button', 'sb-installs-card__sub-name sb-installs__es-one-open', text.sigs );

				open.type = 'button';
				open.setAttribute( 'data-sb-tip', text.change_limits );
				line.appendChild( open );

				keys.forEach( function ( l ) {
					var v    = ( ( policy.limits || {} ).email_signatures || {} )[ l[0] ];
					var stat = el( 'span', 'sb-installs-card__stat', l[1] + ' ' );
					var num  = el( 'strong', v > 0 ? '' : 'is-none', v > 0 ? String( v ) : '\u221e' );

					if ( ! ( v > 0 ) ) {
						num.tabIndex = 0;
						num.setAttribute( 'data-sb-tip', text.unlimited );
						num.setAttribute( 'aria-label', text.unlimited );
					}

					stat.appendChild( num );
					stats.appendChild( stat );
				} );

				line.appendChild( stats );
				row.appendChild( line );
			}

			// A site's new policy on its card: the data, Modules n of n and the Email Signatures line.
			function show( card, policy ) {
				var button = card.querySelector( '.sb-installs__modules' );
				var count  = Object.keys( policy.modules ).filter( function ( k ) { return policy.modules[ k ]; } ).length;

				card.dataset.policy = JSON.stringify( policy );

				if ( button ) {
					button.textContent = text.count.replace( '%1$d', count ).replace( '%2$d', Object.keys( policy.modules ).length );
				}

				esLine( card, policy );
			}

			// Send one site its modules (ids allowed) and limits (module => key => number, 0 for none).
			function sendPolicy( item ) {
				var data = new URLSearchParams( { action: 'sb_tweaks_policy', _ajax_nonce: root.dataset.nonce, host: item.card.dataset.host } );

				item.allowed.forEach( function ( id ) { data.append( 'modules[]', id ); } );

				Object.keys( limits ).forEach( function ( id ) {
					limits[ id ].forEach( function ( l ) {
						data.append( 'limits[' + id + '][' + l[0] + ']', String( ( ( item.limits || {} )[ id ] || {} )[ l[0] ] || 0 ) );
					} );
				} );

				return fetch( root.dataset.ajax, { method: 'POST', credentials: 'same-origin', body: data } )
					.then( function ( r ) { return r.json(); } )
					.catch( function () { return { ok: false, message: text.failed }; } )
					.then( function ( res ) {
						res = res || { ok: false, message: text.failed };

						if ( res.ok && res.policy ) {
							show( item.card, res.policy );
						}

						return res;
					} );
			}

			// What a site now has, in one line for the summary.
			function summary( policy ) {
				var count = Object.keys( policy.modules ).filter( function ( k ) { return policy.modules[ k ]; } ).length;
				var line  = text.count.replace( '%1$d', count ).replace( '%2$d', Object.keys( policy.modules ).length );

				if ( policy.modules.email_signatures && limits.email_signatures ) {
					line += '. ' + text.es + ': ' + limits.email_signatures.map( function ( l ) {
						var v = ( ( policy.limits || {} ).email_signatures || {} )[ l[0] ];

						return l[1] + ' ' + ( v > 0 ? v : text.no_limit );
					} ).join( ', ' );
				}

				return line;
			}

			// Send each site its policy, one at a time, in the progress popup (Sam, 31 October 2026).
			// One site shows a line per module; several show a line per site.
			function runPolicies( items, title ) {
				if ( running || ! items.length || ! window.SBTweaksProgress ) {
					return;
				}

				running = true;
				refresh();

				var job  = window.SBTweaksProgress.open( title );
				var good = 0;
				var bad  = 0;
				var last = '';

				items.reduce( function ( chain, item, i ) {
					return chain.then( function () {
						var name = item.card.dataset.name;

						// step() first: busy() then turns a spinner in place of the bar until the answer.
						job.step( i, items.length, name + ': ' + item.card.dataset.host );
						job.status( items.length > 1 ? text.saving.replace( '%1$s', i + 1 ).replace( '%2$s', items.length ) : text.sending );

						if ( items.length === 1 ) {
							job.busy();
						}

						return sendPolicy( item ).then( function ( res ) {
							if ( ! res.ok ) {
								bad++;
								last = res.message || text.failed;
								job.log( name, last, true );
							} else if ( items.length === 1 ) {
								good++;
								modules.forEach( function ( m ) {
									var on   = !! res.policy.modules[ m[0] ];
									var line = on ? text.allowed : text.hidden;

									if ( on && limits[ m[0] ] ) {
										line += ': ' + limits[ m[0] ].map( function ( l ) {
											var v = ( res.policy.limits[ m[0] ] || {} )[ l[0] ];

											return l[1] + ' ' + ( v > 0 ? v : text.no_limit );
										} ).join( ', ' );
									}

									job.log( m[1], line, false );
								} );
							} else {
								good++;
								job.log( name, summary( res.policy ), false );
							}

							job.step( i + 1, items.length );
						} );
					} );
				}, Promise.resolve() ).then( function () {
					running = false;
					apply();

					if ( items.length === 1 && bad ) {
						job.fail( last );

						return;
					}

					job.finish( bad ? text.mixed_saved.replace( '%1$s', good ).replace( '%2$s', bad ) : text.sent, function () {} );
				} );
			}

			// Number boxes for a module's limits: value as given, blank shows the placeholder.
			function limitInputs( id, values, placeholder ) {
				var grid = el( 'div', 'sb-installs__policy-limits' );

				limits[ id ].forEach( function ( l ) {
					var field = el( 'label', 'sb-tweaks-dialog__field' );
					var input = el( 'input' );
					var v     = values ? values[ l[0] ] : 0;

					input.type           = 'number';
					input.min            = '0';
					input.placeholder    = placeholder;
					input.dataset.module = id;
					input.dataset.key    = l[0];
					input.value          = v > 0 ? v : '';
					field.appendChild( el( 'span', '', l[1] ) );
					field.appendChild( input );
					grid.appendChild( field );
				} );

				return grid;
			}

			// Modules: one site ticks what it may use; several get Leave as is,
			// Allow or Hide per module, and limits left blank stay as each site has them.
			function modulesPopup( list ) {
				var one  = list.length === 1;
				var body = el( 'div', 'sb-installs__policy' );

				modules.forEach( function ( m ) {
					var row = el( 'div', 'sb-installs__policy-module' );
					var on  = list.filter( function ( card ) { return !! ( policyOf( card ).modules || {} )[ m[0] ]; } ).length;
					var control;
					var grid;

					if ( one ) {
						var label = el( 'label', 'sb-tweaks-dialog__check' );

						control         = el( 'input' );
						control.type    = 'checkbox';
						control.value   = m[0];
						control.checked = on > 0;
						label.appendChild( control );
						label.appendChild( el( 'span', '', m[1] ) );
						row.appendChild( label );
					} else {
						var head = el( 'label', 'sb-installs__bulk-module' );
						var now  = on === list.length ? text.all_on : ( on === 0 ? text.all_off : text.mixed );

						control = el( 'select' );
						control.dataset.module = m[0];
						[ [ '', text.leave + ' (' + now + ')' ], [ 'allow', text.allow ], [ 'hide', text.hide ] ].forEach( function ( o ) {
							var opt = el( 'option', '', o[1] );

							opt.value = o[0];
							control.appendChild( opt );
						} );
						head.appendChild( el( 'span', '', m[1] ) );
						head.appendChild( control );
						row.appendChild( head );
					}

					if ( limits[ m[0] ] ) {
						grid = limitInputs( m[0], one ? ( policyOf( list[0] ).limits || {} )[ m[0] ] : null, one ? text.unlimited : text.leave );
						row.appendChild( grid );

						var sync = function () { grid.hidden = one ? ! control.checked : control.value === 'hide'; };
						control.addEventListener( 'change', sync );
						sync();
					}

					body.appendChild( row );
				} );

				window.SBTweaksConfirm.open( {
					title: one ? text.modules.replace( '%s', list[0].dataset.name ) : text.modules_for.replace( '%d', list.length ),
					message: one ? text.modules_note : '',
					body: body,
					action: text.save
				}, function () {
					var items = list.map( function ( card ) {
						var policy  = policyOf( card );
						var allowed = {};
						var lim     = JSON.parse( JSON.stringify( policy.limits || {} ) );

						modules.forEach( function ( m ) {
							var choice = one ? null : body.querySelector( 'select[data-module="' + m[0] + '"]' ).value;

							if ( one ) {
								allowed[ m[0] ] = body.querySelector( '.sb-tweaks-dialog__check input[value="' + m[0] + '"]' ).checked;
							} else {
								allowed[ m[0] ] = choice === 'allow' ? true : ( choice === 'hide' ? false : !! ( policy.modules || {} )[ m[0] ] );
							}
						} );

						body.querySelectorAll( 'input[data-key]' ).forEach( function ( input ) {
							// One site: blank is no limit. Several: blank leaves each site's own.
							if ( one || input.value !== '' ) {
								lim[ input.dataset.module ] = lim[ input.dataset.module ] || {};
								lim[ input.dataset.module ][ input.dataset.key ] = input.value === '' ? 0 : parseInt( input.value, 10 ) || 0;
							}
						} );

						return { card: card, allowed: Object.keys( allowed ).filter( function ( id ) { return allowed[ id ]; } ), limits: lim };
					} );

					runPolicies( items, one ? text.modules.replace( '%s', list[0].dataset.name ) : text.modules_for.replace( '%d', list.length ) );
				} );
			}

			// Signature limits: every site with Email Signatures allowed, its limits
			// ready to change; or one site's, from Signatures on its card (Sam, 31 October 2026).
			function esPopup( only ) {
				var list  = only ? [ only ] : cards.filter( esOn );
				var keys  = limits.email_signatures || [];
				var grid  = el( 'div', only ? 'sb-installs__policy-limits sb-installs__es-one' : 'sb-installs__es' );
				var title = only ? text.es_one.replace( '%s', only.dataset.name ) : text.es_title;

				if ( ! list.length ) {
					return;
				}

				if ( ! only ) {
					grid.style.setProperty( '--sb-installs-es-cols', keys.length );
					grid.appendChild( el( 'span', 'sb-installs__es-head', text.site ) );
					keys.forEach( function ( l ) { grid.appendChild( el( 'span', 'sb-installs__es-head', l[1] ) ); } );
				}

				list.forEach( function ( card ) {
					var now = ( policyOf( card ).limits || {} ).email_signatures || {};

					if ( ! only ) {
						grid.appendChild( el( 'span', 'sb-installs__es-name', card.dataset.name ) );
					}

					keys.forEach( function ( l ) {
						var input = el( 'input' );

						input.type          = 'number';
						input.min           = '0';
						input.placeholder   = text.unlimited;
						input.value         = now[ l[0] ] > 0 ? now[ l[0] ] : '';
						input.dataset.host  = card.dataset.host;
						input.dataset.key   = l[0];

						// One site: each box with its label over it. All sites: a row per site under the column heads.
						if ( only ) {
							var field = el( 'label', 'sb-tweaks-dialog__field' );

							field.appendChild( el( 'span', '', l[1] ) );
							field.appendChild( input );
							grid.appendChild( field );
						} else {
							input.setAttribute( 'aria-label', card.dataset.name + ': ' + l[1] );
							grid.appendChild( input );
						}
					} );
				} );

				window.SBTweaksConfirm.open( {
					title: title,
					body: grid,
					action: text.save
				}, function () {
					var items = [];

					list.forEach( function ( card ) {
						var policy  = policyOf( card );
						var now     = ( policy.limits || {} ).email_signatures || {};
						var next    = {};
						var changed = false;

						keys.forEach( function ( l ) {
							var input = grid.querySelector( 'input[data-host="' + card.dataset.host + '"][data-key="' + l[0] + '"]' );
							var v     = input.value === '' ? 0 : parseInt( input.value, 10 ) || 0;

							next[ l[0] ] = v;
							changed      = changed || v !== ( now[ l[0] ] || 0 );
						} );

						if ( changed ) {
							var lim = JSON.parse( JSON.stringify( policy.limits || {} ) );

							lim.email_signatures = next;
							items.push( {
								card: card,
								allowed: Object.keys( policy.modules || {} ).filter( function ( id ) { return policy.modules[ id ]; } ),
								limits: lim
							} );
						}
					} );

					if ( ! items.length ) {
						if ( window.SBTweaksUI && window.SBTweaksUI.toast ) {
							window.SBTweaksUI.toast( text.nothing, 'info' );
						}

						return;
					}

					runPolicies( items, title );
				} );
			}

			root.addEventListener( 'click', function ( event ) {
				var target = event.target;
				var one    = target.closest( '.sb-installs__push, .sb-installs__install, .sb-installs__beta' );
				var item   = target.closest( '.sb-installs-menu__item' );
				var seg    = target.closest( '.sb-installs-filter [data-filter]' );

				if ( one && ! running && ! one.disabled ) {
					push( one );
				} else if ( target.closest( '.sb-installs__push-all' ) ) {
					run( [ cardOf( target ) ] );
				} else if ( target.closest( '.sb-installs__es-one-open' ) && window.SBTweaksConfirm && ! running ) {
					esPopup( cardOf( target ) );
				} else if ( target.closest( '.sb-installs__modules' ) && window.SBTweaksConfirm && ! running ) {
					modulesPopup( [ cardOf( target ) ] );
				} else if ( target.closest( '.sb-installs__actions' ) ) {
					closeModMenu();
					menu.hidden = ! menu.hidden;
					actions.setAttribute( 'aria-expanded', menu.hidden ? 'false' : 'true' );

					if ( ! menu.hidden ) {
						var first = menu.querySelector( '.sb-installs-menu__item[aria-disabled="false"]' );
						first && first.focus();
					}
				} else if ( item && item.getAttribute( 'aria-disabled' ) !== 'true' && ! running ) {
					closeMenu();

					if ( item.dataset.action === 'update' ) {
						run( picks(), item.dataset.plugin );
					} else if ( item.dataset.action === 'modules' && window.SBTweaksConfirm ) {
						modulesPopup( picks().filter( function ( card ) { return card.dataset.policy; } ) );
					} else if ( item.dataset.action === 'es' && window.SBTweaksConfirm ) {
						esPopup();
					} else if ( item.dataset.action === 'beta' ) {
						go( betaJobs() );
					}
				} else if ( target === master ) {
					// Anything ticked: clear the lot. Nothing: tick every site showing.
					var any = root.querySelectorAll( '.sb-installs__pick:checked' ).length > 0;

					clearPicks();

					if ( ! any ) {
						showing().forEach( function ( box ) { box.checked = true; } );
					}

					refresh();
				} else if ( seg ) {
					filter = seg.dataset.filter;
					root.querySelectorAll( '.sb-installs-filter [data-filter]' ).forEach( function ( b ) { b.setAttribute( 'aria-pressed', b === seg ? 'true' : 'false' ); } );
					clearPicks();
					apply();
				}
			} );

			root.addEventListener( 'change', function ( event ) {
				if ( event.target.classList.contains( 'sb-installs__pick' ) ) {
					refresh();
				}

				if ( event.target.classList.contains( 'sb-installs__mod' ) ) {
					modChanged();
				}
			} );

			// The Modules filter: open and close, Select all or none, Only, and
			// Option, Cmd or Ctrl click for only that one.
			root.addEventListener( 'click', function ( event ) {
				var target = event.target;
				var only   = target.closest( '.sb-installs-menu__only' );
				var tool   = target.closest( '[data-mods]' );

				if ( target.closest( '.sb-installs__modfilter' ) ) {
					modMenu.hidden = ! modMenu.hidden;
					modBtn.setAttribute( 'aria-expanded', modMenu.hidden ? 'false' : 'true' );
					closeMenu();
				} else if ( tool ) {
					modBoxes.forEach( function ( box ) { box.checked = tool.dataset.mods === 'all'; } );
					modChanged();
				} else if ( only || ( target.closest( '.sb-installs-menu__row label' ) && ( event.altKey || event.metaKey || event.ctrlKey ) ) ) {
					var id = only ? only.dataset.only : target.closest( 'label' ).querySelector( 'input' ).value;

					if ( ! only ) {
						event.preventDefault();
					}

					modBoxes.forEach( function ( box ) { box.checked = box.value === id; } );
					modChanged();
				}
			} );

			search.addEventListener( 'input', function () {
				clearPicks();
				apply();
			} );

			// An icon that won't load leaves the letter showing.
			root.addEventListener( 'error', function ( event ) {
				if ( event.target.matches && event.target.matches( '.sb-installs-card__icon img' ) ) {
					event.target.parentNode.classList.remove( 'has-image' );
					event.target.remove();
				}
			}, true );

			// Sites whose icon is due: asked one at a time after the page has loaded.
			cards.filter( function ( card ) { return card.dataset.iconAsk; } ).reduce( function ( chain, card ) {
				return chain.then( function () {
					return fetch( root.dataset.ajax, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams( { action: 'sb_tweaks_install_icon', _ajax_nonce: root.dataset.nonce, host: card.dataset.host } ) } )
						.then( function ( r ) { return r.json(); } )
						.then( function ( res ) {
							var circle = card.querySelector( '.sb-installs-card__icon' );
							var img    = circle.querySelector( 'img' );

							if ( res && res.ok && res.url ) {
								img = img || circle.appendChild( el( 'img' ) );
								img.alt = '';
								img.referrerPolicy = 'no-referrer';
								img.src = res.url;
								circle.classList.add( 'has-image' );
								circle.style.setProperty( '--sb-installs-ring', res.ring || '' );
								res.ring || circle.style.removeProperty( '--sb-installs-ring' );
							} else if ( res && res.ok && img ) {
								circle.classList.remove( 'has-image' );
								img.remove();
							}
						} )
						.catch( function () {} );
				} );
			}, Promise.resolve() );

			document.addEventListener( 'click', function ( event ) {
				if ( ! event.target.closest( '.sb-installs-actions' ) ) {
					closeMenu();
				}

				if ( ! event.target.closest( '.sb-installs-modfilter' ) ) {
					closeModMenu();
				}
			} );

			document.addEventListener( 'keydown', function ( event ) {
				if ( event.key === 'Escape' && menu && ! menu.hidden ) {
					closeMenu();
					actions.focus();
				}

				if ( event.key === 'Escape' && modMenu && ! modMenu.hidden ) {
					closeModMenu();
					modBtn.focus();
				}
			} );

			refresh();
		}() );
		</script>
		<?php
	}
}

/** For the reporter, so the hub can record its own report directly. */
function sb_tweaks_installs_record( $payload ) {
	return is_array( $payload ) ? SB_Tweaks_Installs::record( $payload ) : false;
}
