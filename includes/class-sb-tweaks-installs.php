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
		'socialbump-site-kit'              => 'Site Kit',
		'socialbump-bricks-tweaks'         => 'Bricks Tweaks',
		'socialbump-ai-knowledge-exporter' => 'SEO for AI',
		'socialbump-tweaks'                => 'Tweaks',
	];

	/** Each plugin's main admin page, so an Active version links straight to it. */
	const PAGES = [
		'socialbump-site-kit'              => 'sb-site-kit',
		'socialbump-bricks-tweaks'         => 'sb-bricks-tweaks',
		'socialbump-ai-knowledge-exporter' => 'sb-ai-knowledge-exporter',
		'socialbump-tweaks'                => 'sb-tweaks',
	];

	public static function boot() {
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
		add_action( 'admin_post_sb_tweaks_forget_install', [ __CLASS__, 'forget' ] );
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

		if ( isset( $_GET['forgotten'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Site removed from the list. It comes back if it checks in again.', 'sb-tweaks' ) . '</p></div>';
		}

		// The hub gets its own table above: it runs every release before the sites do.
		self::render_hub( $latest );
		unset( $sites[ strtolower( SB_TWEAKS_HUB_HOST ) ] );

		uasort(
			$sites,
			function ( $a, $b ) {
				return strcasecmp( $a['name'] ?: $a['url'], $b['name'] ?: $b['url'] );
			}
		);

		echo '<section class="sb-tweaks-section"><div class="sb-tweaks-section__head"><h2>' . esc_html__( 'Sites', 'sb-tweaks' ) . '</h2>';
		echo '<p>' . esc_html__( 'Every site that has checked in, with each SocialBUMP plugin it has. A version in amber is behind the one here on the hub. Sites report when a plugin changes and once a day.', 'sb-tweaks' ) . '</p></div>';
		echo '<div class="sb-tweaks-section__body">';

		if ( ! $sites ) {
			echo '<p>' . esc_html__( 'No sites have checked in yet. Each one appears here once it runs a plugin version with the reporter in it: within a day of updating, or straight away when someone visits its admin.', 'sb-tweaks' ) . '</p>';
			echo '</div></section>';

			return;
		}

		echo '<div class="sb-installs__scroll"><table class="widefat striped sb-installs" data-nonce="' . esc_attr( wp_create_nonce( 'sb_tweaks_push' ) ) . '" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '"><thead><tr>';
		echo '<th>' . esc_html__( 'Site', 'sb-tweaks' ) . '</th>';

		foreach ( self::LABELS as $slug => $label ) {
			echo '<th>' . esc_html( $label ) . ( isset( $latest[ $slug ] ) ? '<br><small>' . esc_html( $latest[ $slug ] ) . '</small>' : '' ) . '</th>';
		}

		echo '<th>WordPress</th><th>PHP</th><th>' . esc_html__( 'Last seen', 'sb-tweaks' ) . '</th><th></th></tr></thead><tbody>';

		foreach ( $sites as $host => $site ) {
			$stale = time() - (int) $site['seen'] > self::STALE;
			$push  = class_exists( 'SB_Tweaks_Push' ) && SB_Tweaks_Push::can_push( $site );
			$due   = 0;

			foreach ( (array) $site['plugins'] as $s => $i ) {
				if ( isset( $latest[ $s ] ) && version_compare( $i['version'], $latest[ $s ], '<' ) ) {
					$due++;
				}
			}

			echo '<tr' . ( $stale ? ' class="is-stale"' : '' ) . '>';
			echo '<td><strong>' . esc_html( $site['name'] ?: $host ) . '</strong><br>';
			echo '<a href="' . esc_url( $site['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $host ) . '</a> &middot; ';
			echo '<a href="' . esc_url( trailingslashit( $site['url'] ) . 'wp-admin/plugins.php' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Plugins', 'sb-tweaks' ) . '</a> &middot; ';
			echo '<a href="' . esc_url( trailingslashit( $site['url'] ) . 'wp-admin/update-core.php' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Updates', 'sb-tweaks' ) . '</a>';

			if ( $push && $due > 1 ) {
				echo '<br><button type="button" class="button button-small sb-installs__push-all">' . esc_html__( 'Update all', 'sb-tweaks' ) . '</button>';
			}

			echo '</td>';

			foreach ( array_keys( self::LABELS ) as $slug ) {
				$info = $site['plugins'][ $slug ] ?? null;

				if ( ! $info ) {
					echo '<td class="sb-installs__none">&ndash;</td>';
					continue;
				}

				$behind = isset( $latest[ $slug ] ) && version_compare( $info['version'], $latest[ $slug ], '<' );
				$class  = 'sb-installs__version' . ( $behind ? ' is-behind' : '' ) . ( $info['active'] ? '' : ' is-off' );

				echo '<td><span class="' . esc_attr( $class ) . '">' . esc_html( $info['version'] ) . '</span>';

				if ( $behind && $push ) {
					echo '<br><button type="button" class="button button-small sb-installs__push" data-host="' . esc_attr( $host ) . '" data-plugin="' . esc_attr( $slug ) . '">' . esc_html__( 'Update', 'sb-tweaks' ) . '</button>';
				}

				// Active links to the plugin's own page on that site; a deactivated one has no page to go to.
				if ( $info['active'] && isset( self::PAGES[ $slug ] ) ) {
					$link = trailingslashit( $site['url'] ) . 'wp-admin/admin.php?page=' . self::PAGES[ $slug ];
					echo '<br><small><a href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . esc_html__( 'Active', 'sb-tweaks' ) . '</a></small></td>';
				} else {
					echo $info['active'] ? '<br><small>' . esc_html__( 'Active', 'sb-tweaks' ) . '</small></td>' : '<br><small class="sb-installs__deactivated">' . esc_html__( 'Deactivated', 'sb-tweaks' ) . '</small></td>';
				}
			}

			echo '<td>' . esc_html( $site['wp'] ) . '</td><td>' . esc_html( $site['php'] ) . '</td>';

			/* translators: %s: time since, e.g. 3 hours */
			echo '<td>' . esc_html( sprintf( __( '%s ago', 'sb-tweaks' ), human_time_diff( (int) $site['seen'] ) ) );

			if ( $stale ) {
				echo '<br><small class="sb-installs__stale">' . esc_html__( 'Not heard from in over three days', 'sb-tweaks' ) . '</small>';
			}

			echo '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Remove this site from the list?', 'sb-tweaks' ) ) . '\');">';
			echo '<input type="hidden" name="action" value="sb_tweaks_forget_install"><input type="hidden" name="host" value="' . esc_attr( $host ) . '">';
			wp_nonce_field( 'sb_tweaks_forget_install' );
			echo '<button type="submit" class="button-link sb-installs__forget" aria-label="' . esc_attr__( 'Remove', 'sb-tweaks' ) . '">&#10005;</button></form></td>';
			echo '</tr>';
		}

		echo '</tbody></table></div></div></section>';

		self::script();
	}

	/** Each plugin's release class and admin page, for the hub table. */
	const RELEASES = [
		'socialbump-site-kit'              => [ 'SBSK_Release', 'sb-site-kit' ],
		'socialbump-bricks-tweaks'         => [ 'SBBT_Release', 'sb-bricks-tweaks' ],
		'socialbump-ai-knowledge-exporter' => [ 'SBAIKE_Release', 'sb-ai-knowledge-exporter' ],
		'socialbump-tweaks'                => [ 'SB_Tweaks_Release', 'sb-tweaks' ],
	];

	/**
	 * The hub on its own: its copy of each plugin, what is live on GitHub, and
	 * the notes waiting for the next release.
	 *
	 * The hub's copy is what the sites are compared against, so this is where
	 * to see whether something built here has actually gone out. The GitHub
	 * version comes from each plugin's own release class (cached five minutes),
	 * the same figure its Publishing page shows.
	 */
	private static function render_hub( array $latest ) {
		echo '<section class="sb-tweaks-section"><div class="sb-tweaks-section__head"><h2>' . esc_html__( 'Hub', 'sb-tweaks' ) . '</h2>';
		/* translators: %s: hub address */
		echo '<p>' . sprintf( esc_html__( '%s builds and publishes every release, so its copies are the versions the sites below are measured against.', 'sb-tweaks' ), '<strong>' . esc_html( SB_TWEAKS_HUB_HOST ) . '</strong>' ) . '</p></div>';
		echo '<div class="sb-installs__scroll"><table class="widefat striped sb-installs sb-installs--hub"><thead><tr>';
		echo '<th>' . esc_html__( 'Plugin', 'sb-tweaks' ) . '</th><th>' . esc_html__( 'On the hub', 'sb-tweaks' ) . '</th><th>' . esc_html__( 'On GitHub', 'sb-tweaks' ) . '</th><th>' . esc_html__( 'Publishing', 'sb-tweaks' ) . '</th></tr></thead><tbody>';

		foreach ( self::LABELS as $slug => $label ) {
			if ( empty( $latest[ $slug ] ) ) {
				continue;
			}

			$class = self::RELEASES[ $slug ][0];
			$page  = admin_url( 'admin.php?page=' . self::RELEASES[ $slug ][1] . '-publishing' );
			$live  = '';
			$notes = 0;

			if ( class_exists( $class ) && method_exists( $class, 'instance' ) ) {
				$release = $class::instance();

				$live = self::live_version( $release, $slug );

				$notes = method_exists( $release, 'pending_changes' ) ? count( (array) $release->pending_changes() ) : 0;
			}

			echo '<tr><td><strong>' . esc_html( $label ) . '</strong></td>';
			echo '<td><span class="sb-installs__version">' . esc_html( $latest[ $slug ] ) . '</span></td>';

			if ( $live === '' ) {
				echo '<td><span class="sb-installs__version is-off">?</span><br><small>' . esc_html__( 'Could not reach GitHub', 'sb-tweaks' ) . '</small></td>';
			} else {
				$behind = version_compare( $live, $latest[ $slug ], '<' );
				echo '<td><span class="sb-installs__version' . ( $behind ? ' is-behind' : '' ) . '">' . esc_html( $live ) . '</span>';
				echo $behind ? '<br><small class="sb-installs__deactivated">' . esc_html__( 'Not published yet', 'sb-tweaks' ) . '</small>' : '';
				echo '</td>';
			}

			if ( $notes > 0 ) {
				/* translators: %d: number of queued release notes */
				echo '<td><span class="sb-installs__version is-behind">' . esc_html( sprintf( _n( '%d change waiting', '%d changes waiting', $notes, 'sb-tweaks' ), $notes ) ) . '</span>';
				echo '<br><small><a href="' . esc_url( $page ) . '">' . esc_html__( 'Publish', 'sb-tweaks' ) . '</a></small></td>';
			} else {
				echo '<td><span class="sb-installs__version">' . esc_html__( 'All published', 'sb-tweaks' ) . '</span>';
				echo '<br><small><a href="' . esc_url( $page ) . '">' . esc_html__( 'Publishing', 'sb-tweaks' ) . '</a></small></td>';
			}

			echo '</tr>';
		}

		echo '</tbody></table></div></section>';
	}

	/**
	 * The latest release on GitHub, asked with the plugin's own token.
	 *
	 * Without a token GitHub allows 60 checks an hour per server, and the hub
	 * shares its server with other sites, so an unauthenticated check often
	 * comes back refused. With the plugin's publishing token it is 5,000 an
	 * hour for that token, separate from the shared allowance. Cached for five
	 * minutes. Empty when there is no token or GitHub cannot be reached.
	 */
	private static function live_version( $release, $slug ) {
		$cache  = 'sb_installs_live_' . md5( $slug );
		$cached = get_transient( $cache );

		if ( $cached !== false ) {
			return (string) $cached;
		}

		$token = '';

		try {
			$method = new ReflectionMethod( $release, 'get_token' );
			$method->setAccessible( true );
			$token = (string) $method->invoke( $release );
		} catch ( \Throwable $e ) {
			$token = '';
		}

		if ( $token === '' ) {
			return '';
		}

		$res = wp_remote_get(
			'https://api.github.com/repos/' . SB_Tweaks_Push::OWNER . '/' . $slug . '/releases/latest',
			[
				'timeout' => 10,
				'headers' => [
					'Accept'        => 'application/vnd.github+json',
					'Authorization' => 'Bearer ' . $token,
					'User-Agent'    => 'SocialBUMP-Hub',
				],
			]
		);

		if ( is_wp_error( $res ) || (int) wp_remote_retrieve_response_code( $res ) !== 200 ) {
			return '';
		}

		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		$live = isset( $data['tag_name'] ) ? ltrim( (string) $data['tag_name'], 'v' ) : '';

		set_transient( $cache, $live, 5 * MINUTE_IN_SECONDS );

		return $live;
	}

	/** The Update buttons: one request per plugin, so each cell shows its own result. */
	private static function script() {
		?>
		<script>
		( function () {
			var table = document.querySelector( '.sb-installs' );

			if ( ! table ) {
				return;
			}

			function push( button ) {
				var cell = button.closest( 'td' );
				var pill = cell.querySelector( '.sb-installs__version' );
				var body = new URLSearchParams( { action: 'sb_tweaks_push', _ajax_nonce: table.dataset.nonce, host: button.dataset.host, plugin: button.dataset.plugin } );

				button.disabled = true;
				button.textContent = <?php echo wp_json_encode( __( 'Updating...', 'sb-tweaks' ) ); ?>;

				return fetch( table.dataset.ajax, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						if ( res && res.ok ) {
							pill.textContent = res.version || pill.textContent;
							pill.classList.remove( 'is-behind' );
							button.replaceWith( Object.assign( document.createElement( 'small' ), { className: 'sb-installs__pushed', textContent: <?php echo wp_json_encode( __( 'Updated', 'sb-tweaks' ) ); ?> } ) );
						} else {
							button.disabled = false;
							button.textContent = <?php echo wp_json_encode( __( 'Try again', 'sb-tweaks' ) ); ?>;
							button.title = ( res && res.message ) || '';
							var note = cell.querySelector( '.sb-installs__push-error' ) || cell.appendChild( Object.assign( document.createElement( 'small' ), { className: 'sb-installs__push-error' } ) );
							note.textContent = ( res && res.message ) || <?php echo wp_json_encode( __( 'The update did not complete.', 'sb-tweaks' ) ); ?>;
						}
					} )
					.catch( function () {
						button.disabled = false;
						button.textContent = <?php echo wp_json_encode( __( 'Try again', 'sb-tweaks' ) ); ?>;
					} );
			}

			table.addEventListener( 'click', function ( event ) {
				var one = event.target.closest( '.sb-installs__push' );
				var all = event.target.closest( '.sb-installs__push-all' );

				if ( one ) {
					push( one );
				}

				if ( all ) {
					all.disabled = true;

					// One after another, so the site is never updating two plugins at once.
					var queue = Array.prototype.slice.call( all.closest( 'tr' ).querySelectorAll( '.sb-installs__push' ) );

					queue.reduce( function ( chain, button ) {
						return chain.then( function () { return push( button ); } );
					}, Promise.resolve() ).then( function () { all.remove(); } );
				}
			} );
		}() );
		</script>
		<?php
	}
}

/** For the reporter, so the hub can record its own report directly. */
function sb_tweaks_installs_record( $payload ) {
	return is_array( $payload ) ? SB_Tweaks_Installs::record( $payload ) : false;
}
