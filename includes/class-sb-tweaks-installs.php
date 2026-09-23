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
		echo '<p>' . esc_html__( 'Every site that has checked in, with each SocialBUMP plugin it has. A version in amber is behind the one here on the hub. Sites report when a plugin changes and once a day.', 'sb-tweaks' ) . '</p>';
		echo '</div>';
		echo '<div class="sb-tweaks-section__body">';

		if ( ! $sites ) {
			echo '<p>' . esc_html__( 'No sites have checked in yet. Each one appears here once it runs a plugin version with the reporter in it: within a day of updating, or straight away when someone visits its admin.', 'sb-tweaks' ) . '</p>';
			echo '</div></section>';

			return;
		}

		echo '<div class="sb-installs__scroll"><table class="widefat striped sb-installs" data-nonce="' . esc_attr( wp_create_nonce( 'sb_tweaks_push' ) ) . '" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '"><thead><tr>';
		echo '<th class="sb-installs__check"><input type="checkbox" class="sb-installs__all" aria-label="' . esc_attr__( 'Select every site with updates waiting', 'sb-tweaks' ) . '"></th>';
		echo '<th>' . esc_html__( 'Site', 'sb-tweaks' ) . '</th>';

		foreach ( self::LABELS as $slug => $label ) {
			echo '<th>' . esc_html( $label ) . ( isset( $latest[ $slug ] ) ? '<br><small>' . esc_html( $latest[ $slug ] ) . '</small>' : '' ) . '</th>';
		}

		echo '<th>WordPress</th><th>PHP</th><th>' . esc_html__( 'Last seen', 'sb-tweaks' ) . '</th><th class="sb-installs__action"></th><th></th></tr></thead><tbody>';

		foreach ( $sites as $host => $site ) {
			$stale = time() - (int) $site['seen'] > self::STALE;
			$push  = class_exists( 'SB_Tweaks_Push' ) && SB_Tweaks_Push::can_push( $site );
			$due   = 0;

			foreach ( (array) $site['plugins'] as $s => $i ) {
				if ( isset( $latest[ $s ] ) && version_compare( $i['version'], $latest[ $s ], '<' ) ) {
					$due++;
				}
			}

			echo '<tr data-host="' . esc_attr( $host ) . '" data-name="' . esc_attr( $site['name'] ?: $host ) . '"' . ( $stale ? ' class="is-stale"' : '' ) . '>';
			echo '<td class="sb-installs__check">' . ( $push && $due > 0 ? '<input type="checkbox" class="sb-installs__pick" aria-label="' . esc_attr( $site['name'] ?: $host ) . '">' : '' ) . '</td>';
			echo '<td><strong>' . esc_html( $site['name'] ?: $host ) . '</strong><br>';
			echo '<a href="' . esc_url( $site['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $host ) . '</a> &middot; ';
			echo '<a href="' . esc_url( trailingslashit( $site['url'] ) . 'wp-admin/plugins.php' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Plugins', 'sb-tweaks' ) . '</a> &middot; ';
			echo '<a href="' . esc_url( trailingslashit( $site['url'] ) . 'wp-admin/update-core.php' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Updates', 'sb-tweaks' ) . '</a>';


			echo '</td>';

			foreach ( array_keys( self::LABELS ) as $slug ) {
				$info = $site['plugins'][ $slug ] ?? null;

				if ( ! $info ) {
					// SocialBUMP Tweaks missing on a site with a plugin one of its
					// modules replaces: offer to install it, which moves that in.
					$moves = $slug === 'socialbump-tweaks' ? array_intersect_key( $replaced, (array) $site['plugins'] ) : [];

					if ( $moves && class_exists( 'SB_Tweaks_Push' ) && SB_Tweaks_Push::can_install( $site ) ) {
						echo '<td><button type="button" class="button button-small sb-installs__install" data-host="' . esc_attr( $host ) . '" data-plugin="socialbump-tweaks" data-job="install">' . esc_html__( 'Install', 'sb-tweaks' ) . '</button>';
						/* translators: %s: module name(s) */
						echo '<br><small>' . esc_html( sprintf( __( 'Moves %s in', 'sb-tweaks' ), implode( ', ', $moves ) ) ) . '</small></td>';
					} else {
						echo '<td class="sb-installs__none">&ndash;</td>';
					}

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

			echo '</td><td class="sb-installs__action">';

			if ( $push && $due > 0 ) {
				echo '<button type="button" class="button button-small sb-installs__push-all">' . esc_html__( 'Update all', 'sb-tweaks' ) . '</button>';
			}

			echo '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Remove this site from the list?', 'sb-tweaks' ) ) . '\');">';
			echo '<input type="hidden" name="action" value="sb_tweaks_forget_install"><input type="hidden" name="host" value="' . esc_attr( $host ) . '">';
			wp_nonce_field( 'sb_tweaks_forget_install' );
			echo '<button type="submit" class="button-link sb-installs__forget" aria-label="' . esc_attr__( 'Remove', 'sb-tweaks' ) . '">&#10005;</button></form></td>';
			echo '</tr>';
		}

		echo '</tbody>';

		// Footer: everything on the ticked sites, or one plugin on them.
		echo '<tfoot><tr><td class="sb-installs__check"></td><td><button type="button" class="button button-small sb-installs__push-selected" disabled>' . esc_html__( 'Update all', 'sb-tweaks' ) . '</button></td>';

		foreach ( array_keys( self::LABELS ) as $slug ) {
			echo '<td><button type="button" class="button button-small sb-installs__push-column" data-plugin="' . esc_attr( $slug ) . '" disabled>' . esc_html__( 'Update', 'sb-tweaks' ) . '</button></td>';
		}

		echo '<td></td><td></td><td></td><td class="sb-installs__action"></td><td></td></tr></tfoot></table></div>';
		echo '</div></section>';

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
		echo '<div class="sb-installs__scroll"><table class="widefat striped sb-installs sb-installs--hub" data-publish-nonce="' . esc_attr( wp_create_nonce( 'sb_tweaks_hub_publish' ) ) . '" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '"><thead><tr>';
		echo '<th class="sb-hub__check"><input type="checkbox" class="sb-hub__all" aria-label="' . esc_attr__( 'Select every plugin with changes waiting', 'sb-tweaks' ) . '"></th>';
		echo '<th>' . esc_html__( 'Plugin', 'sb-tweaks' ) . '</th><th>' . esc_html__( 'On the hub', 'sb-tweaks' ) . '</th><th>' . esc_html__( 'On GitHub', 'sb-tweaks' ) . '</th><th>' . esc_html__( 'Waiting', 'sb-tweaks' ) . '</th><th></th></tr></thead><tbody>';

		foreach ( self::LABELS as $slug => $label ) {
			if ( empty( $latest[ $slug ] ) ) {
				continue;
			}

			$class   = self::RELEASES[ $slug ][0];
			$page    = admin_url( 'admin.php?page=' . self::RELEASES[ $slug ][1] . '-publishing' );
			$live    = '';
			$changes = class_exists( 'SB_Tweaks_Hub_Publish' ) ? SB_Tweaks_Hub_Publish::pending( $slug ) : [];
			$notes   = count( $changes );
			$next    = class_exists( 'SB_Tweaks_Hub_Publish' ) ? SB_Tweaks_Hub_Publish::next_version( $slug ) : '';

			if ( class_exists( $class ) && method_exists( $class, 'instance' ) ) {
				$live = self::live_version( $class::instance(), $slug );
			}

			echo '<tr data-plugin="' . esc_attr( $slug ) . '" data-label="' . esc_attr( $label ) . '" data-next="' . esc_attr( $next ) . '" data-changes="' . esc_attr( wp_json_encode( $changes ) ) . '">';
			echo '<td class="sb-hub__check">' . ( $notes > 0 ? '<input type="checkbox" class="sb-hub__pick" aria-label="' . esc_attr( $label ) . '">' : '' ) . '</td>';
			echo '<td><strong>' . esc_html( $label ) . '</strong></td>';
			echo '<td><span class="sb-installs__version sb-hub__hub">' . esc_html( $latest[ $slug ] ) . '</span></td>';

			if ( $live === '' ) {
				echo '<td><span class="sb-installs__version is-off sb-hub__live">?</span><br><small>' . esc_html__( 'Could not reach GitHub', 'sb-tweaks' ) . '</small></td>';
			} else {
				$behind = version_compare( $live, $latest[ $slug ], '<' );
				echo '<td><span class="sb-installs__version sb-hub__live' . ( $behind ? ' is-behind' : '' ) . '">' . esc_html( $live ) . '</span>';
				echo $behind ? '<br><small class="sb-installs__deactivated">' . esc_html__( 'Not published yet', 'sb-tweaks' ) . '</small>' : '';
				echo '</td>';
			}

			echo '<td class="sb-hub__waiting">';

			if ( $notes > 0 ) {
				/* translators: %d: number of queued release notes */
				echo '<button type="button" class="button-link sb-installs__version is-behind sb-hub__changes">' . esc_html( sprintf( _n( '%d change waiting', '%d changes waiting', $notes, 'sb-tweaks' ), $notes ) ) . '</button>';
				echo '<br><small><a href="' . esc_url( $page ) . '">' . esc_html__( 'Review', 'sb-tweaks' ) . '</a></small>';
			} else {
				echo '<span class="sb-installs__version">' . esc_html__( 'All published', 'sb-tweaks' ) . '</span>';
				echo '<br><small><a href="' . esc_url( $page ) . '">' . esc_html__( 'Publishing', 'sb-tweaks' ) . '</a></small>';
			}

			echo '</td><td class="sb-hub__action">';

			if ( $notes > 0 ) {
				/* translators: %s: version number */
				echo '<button type="button" class="button button-small sb-hub__publish">' . esc_html( sprintf( __( 'Publish %s', 'sb-tweaks' ), $next ) ) . '</button>';
			}

			echo '</td></tr>';
		}

		echo '</tbody></table></div>';
		echo '<p class="sb-hub__bulk"><button type="button" class="button sb-hub__publish-selected" disabled>' . esc_html__( 'Publish selected', 'sb-tweaks' ) . '</button></p>';

		// The waiting changes, opened from the count.
		echo '<div class="sb-hub__modal sb-hub__modal--changes" hidden><div class="sb-hub__dialog" role="dialog" aria-modal="true" aria-labelledby="sb-hub-changes-title">';
		echo '<button type="button" class="sb-hub__x sb-hub__modal-cancel" aria-label="' . esc_attr__( 'Close', 'sb-tweaks' ) . '">&times;</button>';
		echo '<h2 id="sb-hub-changes-title"></h2><p class="sb-hub__modal-next"></p><ul class="sb-hub__modal-list"></ul>';
		echo '<p class="sb-hub__modal-buttons"><button type="button" class="button sb-hub__modal-cancel">' . esc_html__( 'Cancel', 'sb-tweaks' ) . '</button><button type="button" class="button button-primary sb-hub__modal-publish">' . esc_html__( 'Publish', 'sb-tweaks' ) . '</button></p>';
		echo '</div></div>';

		echo '</section>';

		self::hub_script();
	}

	/** The hub table: the changes popup, Publish, Publish selected and the progress popup. */
	private static function hub_script() {
		$text = [
			'publishing' => __( 'Publishing', 'sb-tweaks' ),
			/* translators: 1: position, 2: total */
			'step'       => __( 'Publishing %1$s of %2$s', 'sb-tweaks' ),
			/* translators: %s: version number */
			'building'   => __( 'building, pushing to GitHub and publishing %s', 'sb-tweaks' ),
			'went'       => __( 'published', 'sb-tweaks' ),
			'complete'   => __( 'Publishing complete', 'sb-tweaks' ),
			/* translators: 1: number published, 2: number failed */
			'mixed'      => __( '%1$s published, %2$s failed', 'sb-tweaks' ),
			'wait'       => __( 'Please wait. Each release is built, pushed to GitHub and published.', 'sb-tweaks' ),
			/* translators: 1: plugin name, 2: version, 3: position, 4: total */
			'now'        => __( 'Publishing %1$s %2$s (%3$s of %4$s)...', 'sb-tweaks' ),
			'live'       => __( 'is live', 'sb-tweaks' ),
			'failed'     => __( 'did not publish', 'sb-tweaks' ),
			'finished'   => __( 'Finished', 'sb-tweaks' ),
			/* translators: 1: number published, 2: number failed */
			'summary'    => __( '%1$s published, %2$s failed.', 'sb-tweaks' ),
			/* translators: %s: time taken */
			'took'       => __( 'Took %s', 'sb-tweaks' ),
			'published'  => __( 'All published', 'sb-tweaks' ),
			/* translators: %s: version number */
			'next'       => __( 'Goes out as version %s with these release notes:', 'sb-tweaks' ),
			/* translators: %s: plugin name */
			'title'      => __( '%s: changes waiting', 'sb-tweaks' ),
		];
		?>
		<script>
		( function () {
			var table = document.querySelector( '.sb-installs--hub' );

			if ( ! table ) {
				return;
			}

			var text     = <?php echo wp_json_encode( $text ); ?>;
			var changes  = document.querySelector( '.sb-hub__modal--changes' );
			var bulk     = document.querySelector( '.sb-hub__publish-selected' );
			var all      = table.querySelector( '.sb-hub__all' );
			var open     = null;
			var running  = false;

			function picks() {
				return Array.prototype.slice.call( table.querySelectorAll( '.sb-hub__pick:checked' ) );
			}

			function refresh() {
				bulk.disabled = running || picks().length === 0;
			}

			function clock( seconds ) {
				var m = Math.floor( seconds / 60 );
				var s = seconds % 60;

				return m > 0 ? m + 'm ' + s + 's' : s + 's';
			}

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

			// One plugin: the request, then its row in the table.
			function publish( row ) {
				var body = new URLSearchParams( { action: 'sb_tweaks_hub_publish', _ajax_nonce: table.dataset.publishNonce, plugin: row.dataset.plugin } );

				return fetch( table.dataset.ajax, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.catch( function () { return { ok: false, message: text.failed }; } )
					.then( function ( res ) {
						if ( res && res.ok ) {
							var status = row.querySelector( '.sb-hub__waiting' );

							row.querySelector( '.sb-hub__hub' ).textContent = res.version;
							row.querySelector( '.sb-hub__live' ).textContent = res.version;
							row.querySelector( '.sb-hub__live' ).classList.remove( 'is-behind', 'is-off' );
							status.innerHTML = '';
							status.appendChild( el( 'span', 'sb-installs__version', text.published ) );
							[ '.sb-hub__publish', '.sb-hub__pick' ].forEach( function ( s ) { var n = row.querySelector( s ); n && n.remove(); } );
						}

						return res || { ok: false, message: text.failed };
					} );
			}

			// One after another, in the framework's progress popup.
			function run( rows ) {
				if ( running || ! rows.length || ! window.SBTweaksProgress ) {
					return;
				}

				running = true;
				refresh();

				var job  = window.SBTweaksProgress.open( text.publishing );
				var good = 0;
				var bad  = 0;

				rows.reduce( function ( chain, row, i ) {
					return chain.then( function () {
						var what = row.dataset.label + ' ' + row.dataset.next;

						job.step( i, rows.length, row.dataset.label + ': ' + text.building.replace( '%s', row.dataset.next ) );
						job.status( text.step.replace( '%1$s', i + 1 ).replace( '%2$s', rows.length ) );

						return publish( row ).then( function ( res ) {
							if ( res.ok ) {
								good++;
								job.log( what, text.went );
							} else {
								bad++;
								job.log( what, text.failed + ( res.message ? ': ' + res.message : '' ), true );
							}

							job.step( i + 1, rows.length );
						} );
					} );
				}, Promise.resolve() ).then( function () {
					running = false;
					job.finish( bad ? text.mixed.replace( '%1$s', good ).replace( '%2$s', bad ) : text.complete );
				} );
			}
			function close_changes() {
				changes.hidden = true;
				open = null;
			}

			table.addEventListener( 'click', function ( event ) {
				var row = event.target.closest( 'tr[data-plugin]' );

				if ( event.target.closest( '.sb-hub__publish' ) ) {
					run( [ row ] );
				}

				if ( event.target.closest( '.sb-hub__changes' ) ) {
					open = row;
					changes.querySelector( 'h2' ).textContent = text.title.replace( '%s', row.dataset.label );
					changes.querySelector( '.sb-hub__modal-next' ).textContent = text.next.replace( '%s', row.dataset.next );

					var list = changes.querySelector( '.sb-hub__modal-list' );
					list.innerHTML = '';
					JSON.parse( row.dataset.changes || '[]' ).forEach( function ( line ) {
						list.appendChild( el( 'li', '', line ) );
					} );

					changes.hidden = false;
					changes.querySelector( '.sb-hub__modal-publish' ).focus();
				}
			} );

			table.addEventListener( 'change', function ( event ) {
				if ( event.target === all ) {
					table.querySelectorAll( '.sb-hub__pick' ).forEach( function ( box ) { box.checked = all.checked; } );
				}

				refresh();
			} );

			changes.addEventListener( 'click', function ( event ) {
				if ( event.target === changes || event.target.closest( '.sb-hub__modal-cancel' ) ) {
					close_changes();
				}

				if ( event.target.closest( '.sb-hub__modal-publish' ) && open ) {
					var row = open;
					close_changes();
					run( [ row ] );
				}
			} );

			document.addEventListener( 'keydown', function ( event ) {
				if ( event.key !== 'Escape' ) {
					return;
				}

				if ( ! changes.hidden ) {
					close_changes();
				}
			} );

			bulk.addEventListener( 'click', function () {
				run( picks().map( function ( box ) { return box.closest( 'tr' ); } ) );
			} );
		}() );
		</script>
		<?php
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
		// Keyed to the hub's own version too, so publishing a new one shows at once.
		$cache  = 'sb_installs_live_' . md5( $slug . '|' . ( self::latest()[ $slug ] ?? '' ) );
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
		$text = [
			'updating'  => __( 'Updating', 'sb-tweaks' ),
			'title'     => __( 'Updating sites', 'sb-tweaks' ),
			/* translators: 1: position, 2: total */
			'step'      => __( 'Updating %1$s of %2$s', 'sb-tweaks' ),
			'complete'  => __( 'Updates complete', 'sb-tweaks' ),
			/* translators: 1: number updated, 2: number failed */
			'mixed'     => __( '%1$s updated, %2$s failed', 'sb-tweaks' ),
			'working'   => __( 'Updating...', 'sb-tweaks' ),
			'updated'   => __( 'Updated', 'sb-tweaks' ),
			'again'     => __( 'Try again', 'sb-tweaks' ),
			'failed'    => __( 'The update did not complete.', 'sb-tweaks' ),
			/* translators: 1: site, 2: plugin, 3: position, 4: total */
			'now'       => __( 'Updating %2$s on %1$s (%3$s of %4$s)...', 'sb-tweaks' ),
			'wait'      => __( 'Please wait. Each site installs its update and reports back.', 'sb-tweaks' ),
			'done'      => __( 'updated to', 'sb-tweaks' ),
			'bad'       => __( 'did not update', 'sb-tweaks' ),
			'finished'  => __( 'Finished', 'sb-tweaks' ),
			/* translators: 1: number updated, 2: number failed */
			'summary'   => __( '%1$s updated, %2$s failed.', 'sb-tweaks' ),
			/* translators: %s: time taken */
			'took'      => __( 'Took %s', 'sb-tweaks' ),
		];
		?>
		<script>
		( function () {
			// The sites table, the one carrying the nonce; the hub table sits above it.
			var table = document.querySelector( '.sb-installs[data-nonce]' );

			if ( ! table ) {
				return;
			}

			var text     = <?php echo wp_json_encode( $text ); ?>;
			var bulk     = table.querySelector( '.sb-installs__push-selected' );
			var columns  = table.querySelectorAll( '.sb-installs__push-column' );
			var all      = table.querySelector( '.sb-installs__all' );
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

			function clock( seconds ) {
				var m = Math.floor( seconds / 60 );

				return m > 0 ? m + 'm ' + ( seconds % 60 ) + 's' : seconds + 's';
			}

			function picks() {
				return Array.prototype.slice.call( table.querySelectorAll( '.sb-installs__pick:checked' ) );
			}

			// Footer buttons: Update selected needs a tick; a column's Update also
			// needs a ticked site that is behind on that plugin.
			function refresh() {
				var rows = picks().map( function ( box ) { return box.closest( 'tr' ); } );

				bulk.disabled = running || rows.length === 0;

				columns.forEach( function ( button ) {
					button.disabled = running || ! rows.some( function ( row ) { return row.querySelector( '.sb-installs__push[data-plugin="' + button.dataset.plugin + '"]' ); } );
				} );
			}

			// One plugin on one site. Updates its cell and returns the result.
			function push( button ) {
				var cell = button.closest( 'td' );
				var pill = cell.querySelector( '.sb-installs__version' );
				var body = new URLSearchParams( { action: 'sb_tweaks_push', _ajax_nonce: table.dataset.nonce, host: button.dataset.host, plugin: button.dataset.plugin, job: button.dataset.job || 'update' } );

				button.disabled = true;
				button.textContent = text.working;

				return fetch( table.dataset.ajax, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.catch( function () { return { ok: false, message: text.failed }; } )
					.then( function ( res ) {
						res = res || { ok: false, message: text.failed };

						if ( res.ok ) {
							// An install starts from an empty cell, so make its pill.
							if ( ! pill ) {
								pill = cell.insertBefore( el( 'span', 'sb-installs__version' ), cell.firstChild );
								cell.querySelector( 'small' ) && cell.querySelector( 'small' ).remove();
							}

							pill.textContent = res.version || pill.textContent;
							pill.classList.remove( 'is-behind' );
							button.remove();
						} else {
							button.disabled = false;
							button.textContent = text.again;
							button.title = res.message || '';
							var note = cell.querySelector( '.sb-installs__push-error' ) || cell.appendChild( el( 'small', 'sb-installs__push-error' ) );
							note.textContent = res.message || text.failed;
						}

						tidy( cell.closest( 'tr' ) );

						return res;
					} );
			}

			// A row with nothing left to update loses its tick box and Update all.
			function tidy( row ) {
				if ( row.querySelector( '.sb-installs__push' ) ) {
					return;
				}

				[ '.sb-installs__pick', '.sb-installs__push-all' ].forEach( function ( s ) { var n = row.querySelector( s ); n && n.remove(); } );
				refresh();
			}

			// Every waiting update in these rows (or one plugin's), one at a time,
			// in the framework's progress popup.
			function run( rows, only ) {
				var jobs = [];

				rows.forEach( function ( row ) {
					row.querySelectorAll( '.sb-installs__push' ).forEach( function ( button ) {
						if ( ! only || button.dataset.plugin === only ) {
							jobs.push( { row: row, button: button } );
						}
					} );
				} );

				if ( running || ! jobs.length || ! window.SBTweaksProgress ) {
					return;
				}

				running = true;
				refresh();

				var headings = Array.prototype.map.call( table.querySelectorAll( 'thead th' ), function ( th ) { return th.childNodes[0] ? th.childNodes[0].textContent.trim() : ''; } );
				var job      = window.SBTweaksProgress.open( text.title );
				var good     = 0;
				var bad      = 0;

				jobs.reduce( function ( chain, item, i ) {
					return chain.then( function () {
						var plugin = headings[ item.button.closest( 'td' ).cellIndex ] || item.button.dataset.plugin;
						var what   = item.row.dataset.name + ': ' + plugin;

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
					job.finish( bad ? text.mixed.replace( '%1$s', good ).replace( '%2$s', bad ) : text.complete );
				} );
			}
			table.addEventListener( 'click', function ( event ) {
				var one = event.target.closest( '.sb-installs__push, .sb-installs__install' );
				var row = event.target.closest( '.sb-installs__push-all' );

				if ( one && ! running ) {
					push( one );
				}

				if ( row ) {
					run( [ row.closest( 'tr' ) ] );
				}

				var ticked = picks().map( function ( box ) { return box.closest( 'tr' ); } );

				if ( event.target.closest( '.sb-installs__push-selected' ) ) {
					run( ticked );
				}

				var column = event.target.closest( '.sb-installs__push-column' );

				if ( column ) {
					run( ticked, column.dataset.plugin );
				}
			} );

			table.addEventListener( 'change', function ( event ) {
				if ( event.target === all ) {
					table.querySelectorAll( '.sb-installs__pick' ).forEach( function ( box ) { box.checked = all.checked; } );
				}

				refresh();
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
