<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The robots.txt WordPress serves.
 *
 * WordPress builds its robots.txt through the robots_txt filter. Rank Math
 * uses it too (priority 0 adds its sitemap line, 10 swaps in whatever is saved
 * in its editor), so this runs at 100 and replaces the whole output: the rules
 * kept here, any extra lines, and a Sitemap line worked out for this site.
 * Other plugins can still add after it; SEO for AI adds its llms.txt lines at
 * 110. A physical robots.txt file in the site root beats all of this, because
 * the web server hands it out without asking WordPress, so the page warns
 * about one. While the site is set to discourage search engines, WordPress's
 * own Disallow: / is left alone.
 */
class SB_Site_Kit_Robots {

	/** The SocialBUMP rules, as reviewed in September 2026. */
	const DEFAULT_RULES = [
		[ 'disallow', '/wp-admin/' ],
		[ 'disallow', '/wp-login.php' ],
		[ 'disallow', '/wp-register.php' ],
		[ 'disallow', '/xmlrpc.php' ],
		[ 'disallow', '/wp-json/' ],
		[ 'disallow', '/cart/' ],
		[ 'disallow', '/checkout/' ],
		[ 'disallow', '/my-account/' ],
		[ 'disallow', '/feed/' ],
		[ 'disallow', '/comments/feed/' ],
		[ 'disallow', '/trackback/' ],
		[ 'disallow', '/*?replytocom=' ],
		[ 'disallow', '/*?attachment_id=' ],
		[ 'disallow', '/*add-to-cart=' ],
		[ 'disallow', '/*?s=' ],
		[ 'disallow', '/search/' ],
		[ 'disallow', '/*?*filter_' ],
		[ 'disallow', '/*?EnrollerID=' ],
		[ 'disallow', '/*?blackhole=' ],
		[ 'disallow', '/*?affiliate=' ],
		[ 'disallow', '/*?rewardId=' ],
		[ 'disallow', '/*?cb=' ],
		[ 'disallow', '/*?sid=' ],
		[ 'disallow', '/*?PHPSESSID=' ],
		[ 'allow', '/wp-admin/admin-ajax.php' ],
		[ 'allow', '/wp-content/uploads/' ],
	];

	public static function boot() {
		add_filter( 'robots_txt', [ __CLASS__, 'filter' ], 100, 2 );
		// Saving is registered in module.php, so it also works while the module is off.
	}

	public static function setting( $key, $fallback = null ) {
		$value = SB_Site_Kit::setting( 'robots-txt', $key );

		return $value === null ? $fallback : $value;
	}

	/** The default rules as rows. */
	public static function defaults() {
		return array_map(
			function ( $rule ) {
				return [ 'type' => $rule[0], 'path' => $rule[1] ];
			},
			self::DEFAULT_RULES
		);
	}

	/** The saved rules, or the defaults when none have been saved. */
	public static function rules() {
		$saved = self::setting( 'rules' );

		if ( ! is_array( $saved ) ) {
			return self::defaults();
		}

		$rules = [];

		foreach ( $saved as $rule ) {
			$type = isset( $rule['type'] ) && $rule['type'] === 'allow' ? 'allow' : 'disallow';
			$path = self::clean_path( $rule['path'] ?? '' );

			if ( $path !== '' ) {
				$rules[] = [ 'type' => $type, 'path' => $path ];
			}
		}

		return $rules;
	}

	/**
	 * A path as robots.txt expects it: starting with a slash or a star, no
	 * spaces or line breaks, nothing that could close a tag on the page.
	 */
	public static function clean_path( $path ) {
		$path = preg_replace( '/[\s<>"\']+/', '', (string) $path );

		if ( $path === '' ) {
			return '';
		}

		if ( $path[0] !== '/' && $path[0] !== '*' ) {
			$path = '/' . $path;
		}

		return substr( $path, 0, 255 );
	}

	/** The extra lines, one per line, tags and control characters out. */
	public static function extra() {
		return self::clean_extra( (string) self::setting( 'extra', '' ) );
	}

	private static function clean_extra( $text ) {
		$lines = preg_split( '/\r\n|\r|\n/', wp_strip_all_tags( (string) $text ) );
		$lines = array_map(
			function ( $line ) {
				return trim( preg_replace( '/[\x00-\x1F\x7F]/', '', $line ) );
			},
			$lines
		);

		return trim( implode( "\n", $lines ) );
	}

	/**
	 * The sitemap index for this site: Rank Math's when its sitemap is on,
	 * then Yoast's, then WordPress's own. Empty when the site has none.
	 */
	public static function sitemap_url() {
		$url = '';

		if ( class_exists( '\RankMath\Helper' ) && class_exists( '\RankMath\Sitemap\Router' ) && \RankMath\Helper::is_module_active( 'sitemap' ) ) {
			$url = \RankMath\Sitemap\Router::get_base_url( 'sitemap_index.xml' );
		} elseif ( class_exists( 'WPSEO_Sitemaps_Router' ) && class_exists( 'WPSEO_Options' ) && WPSEO_Options::get( 'enable_xml_sitemap' ) ) {
			$url = WPSEO_Sitemaps_Router::get_base_url( 'sitemap_index.xml' );
		} elseif ( function_exists( 'get_sitemap_url' ) ) {
			$url = (string) get_sitemap_url( 'index' );
		}

		return (string) apply_filters( 'sb_site_kit/robots_txt/sitemap_url', $url );
	}

	/** The whole file. */
	public static function build() {
		$out = "User-agent: *\n";

		foreach ( self::rules() as $rule ) {
			$out .= ( $rule['type'] === 'allow' ? 'Allow: ' : 'Disallow: ' ) . $rule['path'] . "\n";
		}

		$extra = self::extra();

		if ( $extra !== '' ) {
			$out .= "\n" . $extra . "\n";
		}

		$sitemap = self::sitemap_url();

		if ( $sitemap !== '' ) {
			$out .= "\nSitemap: " . esc_url_raw( $sitemap ) . "\n";
		}

		return (string) apply_filters( 'sb_site_kit/robots_txt', $out );
	}

	public static function filter( $output, $public ) {
		// Discouraging search engines: WordPress's own Disallow: / stands.
		if ( (string) $public === '0' ) {
			return $output;
		}

		return self::build();
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'sb-tweaks' ) );
		}

		check_admin_referer( 'sb_site_kit_save_robots' );

		$settings = (array) get_option( SB_Site_Kit::SETTINGS_OPTION, [] );
		$current  = isset( $settings['robots-txt'] ) && is_array( $settings['robots-txt'] ) ? $settings['robots-txt'] : [];

		if ( ! empty( $_POST['sb_site_kit_reset_robots'] ) ) {
			// Reset puts the default rules back; the extra lines are kept.
			$current['rules'] = self::defaults();
		} else {
			$types = isset( $_POST['sb_site_kit_robots_type'] ) ? (array) wp_unslash( $_POST['sb_site_kit_robots_type'] ) : [];
			$paths = isset( $_POST['sb_site_kit_robots_path'] ) ? (array) wp_unslash( $_POST['sb_site_kit_robots_path'] ) : [];
			$rules = [];
			$seen  = [];

			foreach ( $paths as $key => $path ) {
				$path = self::clean_path( $path );
				$type = isset( $types[ $key ] ) && $types[ $key ] === 'allow' ? 'allow' : 'disallow';

				if ( $path === '' || isset( $seen[ $type . $path ] ) ) {
					continue;
				}

				$seen[ $type . $path ] = true;
				$rules[]               = [ 'type' => $type, 'path' => $path ];
			}

			$current['rules'] = $rules;
			$current['extra'] = self::clean_extra( isset( $_POST['sb_site_kit_robots_extra'] ) ? wp_unslash( $_POST['sb_site_kit_robots_extra'] ) : '' );
		}

		$settings['robots-txt'] = $current;
		update_option( SB_Site_Kit::SETTINGS_OPTION, $settings );

		// The switch on this page is the module's own switch, the same one the
		// Features page shows; switching on also makes sure the SEO group is on.
		$on     = ! empty( $_POST['sb_site_kit_robots_on'] );
		$states = (array) get_option( SB_Site_Kit::FEATURES_OPTION, [] );

		$states['robots-txt'] = $on ? 1 : 0;
		update_option( SB_Site_Kit::FEATURES_OPTION, $states );

		if ( $on ) {
			$groups        = (array) get_option( SB_Site_Kit::GROUPS_OPTION, [] );
			$groups['seo'] = 1;
			update_option( SB_Site_Kit::GROUPS_OPTION, $groups );
		}

		// Pages are named after the section (SEO), not the module.
		wp_safe_redirect( admin_url( 'admin.php?page=' . SB_Site_Kit::page_slug( 'seo' ) . '&updated=true' ) );
		exit;
	}

	/** What the site actually serves at /robots.txt right now. */
	public static function live() {
		$public = (string) get_option( 'blog_public' );
		$output = "User-agent: *\n";

		if ( $public === '0' ) {
			$output .= "Disallow: /\n";
		} else {
			$output .= "Disallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";
		}

		return (string) apply_filters( 'robots_txt', $output, $public );
	}

	/** Things that stop this file from being the one served, or confuse it. */
	private static function warnings() {
		$notes = [];

		if ( file_exists( ABSPATH . 'robots.txt' ) ) {
			$notes[] = [ 'error', __( 'There is a robots.txt file in the site root. The web server sends that file and never asks WordPress, so nothing on this page is live until it is deleted.', 'sb-tweaks' ) ];
		}

		if ( (string) get_option( 'blog_public' ) === '0' ) {
			$notes[] = [ 'warning', __( 'This site is set to discourage search engines (Settings, Reading), so WordPress serves Disallow: / and these rules step aside until that is turned off.', 'sb-tweaks' ) ];
		}

		$rank_math = (array) get_option( 'rank-math-options-general', [] );

		if ( ! empty( $rank_math['robots_txt_content'] ) && trim( (string) $rank_math['robots_txt_content'] ) !== '' ) {
			$notes[] = [ 'info', __( 'Rank Math\'s robots.txt editor still has content in it. It is no longer served, since Site Kit takes over, but it is worth emptying (Rank Math, General Settings, Edit robots.txt) so nobody edits the wrong one.', 'sb-tweaks' ) ];
		}

		return $notes;
	}

	private static function rule_row( $type, $path ) {
		return sprintf(
			'<div class="sb-site-kit-rule"><select name="sb_site_kit_robots_type[]" class="sb-site-kit-rule__type"><option value="disallow"%1$s>Disallow</option><option value="allow"%2$s>Allow</option></select><input type="text" name="sb_site_kit_robots_path[]" value="%3$s" class="sb-site-kit-rule__path" spellcheck="false" placeholder="/path/"><button type="button" class="button-link sb-site-kit-rule__remove" aria-label="%4$s">&#10005;</button></div>',
			selected( $type, 'disallow', false ),
			selected( $type, 'allow', false ),
			esc_attr( $path ),
			esc_attr__( 'Remove', 'sb-tweaks' )
		);
	}

	public static function render_page() {
		foreach ( self::warnings() as $note ) {
			printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr( $note[0] ), esc_html( $note[1] ) );
		}

		echo '<form method="post" autocomplete="off" data-sb-dirty action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="sb_site_kit_save_robots">';
		wp_nonce_field( 'sb_site_kit_save_robots' );

		echo '<div class="sb-site-kit-robots">';

		// Rules
		$on = SB_Site_Kit::is_enabled( 'robots-txt' );

		echo '<section class="sb-tweaks-section">';
		echo '<div class="sb-tweaks-section__head sb-site-kit-section__head--switch"><div><h2>' . esc_html__( 'Rules', 'sb-tweaks' ) . '</h2>';
		echo '<p>' . esc_html__( 'Every rule applies to all crawlers (User-agent: *). A path starts with a slash; * matches anything.', 'sb-tweaks' ) . '</p></div>';
		echo '<label class="sb-tweaks-switch"><input type="checkbox" name="sb_site_kit_robots_on" value="1" ' . checked( $on, true, false ) . '>';
		echo '<span class="sb-tweaks-switch__track"><span class="sb-tweaks-switch__dot"></span></span>';
		echo '<span class="screen-reader-text">' . esc_html__( 'Serve this robots.txt', 'sb-tweaks' ) . '</span></label>';
		echo '</div>';

		if ( ! $on ) {
			echo '<p class="sb-site-kit-robots__off">' . esc_html__( 'Switched off: these rules are saved but not served. Switch on and save to make them live.', 'sb-tweaks' ) . '</p>';
		}
		echo '<div class="sb-site-kit-rules" id="sb-site-kit-rules">';

		foreach ( self::rules() as $rule ) {
			echo self::rule_row( $rule['type'], $rule['path'] ); // phpcs:ignore WordPress.Security.EscapeOutput
		}

		echo '</div>';
		echo '<template id="sb-site-kit-rule-template">' . self::rule_row( 'disallow', '' ) . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="sb-site-kit-width-actions">';
		echo '<button type="button" class="button" id="sb-site-kit-add-rule">' . esc_html__( 'Add a rule', 'sb-tweaks' ) . '</button>';
		echo '<button type="submit" class="button sb-tweaks-button--danger" id="sb-site-kit-reset-rules" name="sb_site_kit_reset_robots" value="1" data-sb-always-on' . ( self::rules() === self::defaults() ? ' disabled' : '' ) . '>' . esc_html__( 'Reset to default rules', 'sb-tweaks' ) . '</button>';
		echo '<script>window.sbSiteKitRobotsDefaults = ' . wp_json_encode( self::defaults() ) . ';</script>';
		echo '</div>';
		echo '</section>';

		echo '<div class="sb-site-kit-robots__side">';

		// Extra lines
		echo '<section class="sb-tweaks-section">';
		echo '<div class="sb-tweaks-section__head"><div><h2>' . esc_html__( 'Extra lines', 'sb-tweaks' ) . '</h2>';
		echo '<p>' . esc_html__( 'Added as written, after the rules. For anything a rule row cannot say, such as a group for one crawler. Lines starting with # are comments.', 'sb-tweaks' ) . '</p></div></div>';
		echo '<textarea name="sb_site_kit_robots_extra" rows="6" class="large-text code" spellcheck="false" placeholder="User-agent: SomeBot&#10;Disallow: /">' . esc_textarea( self::extra() ) . '</textarea>';

		$sitemap = self::sitemap_url();
		echo '<p class="description">' . ( $sitemap !== ''
			/* translators: %s: sitemap address, linked */
			? sprintf( esc_html__( 'Robots.txt Sitemap URL: %s', 'sb-tweaks' ), '<a href="' . esc_url( $sitemap ) . '" target="_blank" rel="noopener"><code>' . esc_html( $sitemap ) . '</code></a>' )
			: esc_html__( 'No sitemap was found on this site, so no Sitemap line is added.', 'sb-tweaks' ) ) . '</p>';
		echo '</section>';

		// What is live
		echo '<section class="sb-tweaks-section">';
		echo '<div class="sb-tweaks-section__head"><div><h2>' . esc_html__( 'Live robots.txt', 'sb-tweaks' ) . '</h2>';
		/* translators: %s: link to the robots.txt address */
		echo '<p>' . sprintf( esc_html__( 'What %s serves now, including lines other plugins add. Save to update it.', 'sb-tweaks' ), '<a href="' . esc_url( home_url( '/robots.txt' ) ) . '" target="_blank" rel="noopener">/robots.txt</a>' ) . '</p></div></div>';
		echo '<pre class="sb-site-kit-robots__live">' . esc_html( self::live() ) . '</pre>';
		echo '</section>';

		echo '</div>';
		echo '</div>';

		submit_button( esc_html__( 'Save changes', 'sb-tweaks' ), 'primary sb-save--clean' );
		echo '</form>';
		?>
		<script>
		( function () {
			var list = document.getElementById( 'sb-site-kit-rules' );
			var tpl  = document.getElementById( 'sb-site-kit-rule-template' );
			var add  = document.getElementById( 'sb-site-kit-add-rule' );

			if ( ! list || ! tpl || ! add ) {
				return;
			}

			var reset    = document.getElementById( 'sb-site-kit-reset-rules' );
			var defaults = window.sbSiteKitRobotsDefaults || [];

			// Reset only makes sense when the rows differ from the defaults.
			var sameAsDefaults = function () {
				var rows = list.querySelectorAll( '.sb-site-kit-rule' );
				var kept = [];

				rows.forEach( function ( row ) {
					var path = row.querySelector( '.sb-site-kit-rule__path' ).value.trim();

					if ( path !== '' ) {
						kept.push( row.querySelector( '.sb-site-kit-rule__type' ).value + ' ' + path );
					}
				} );

				return kept.length === defaults.length && defaults.every( function ( rule, i ) {
					return kept[ i ] === rule.type + ' ' + rule.path;
				} );
			};

			var check = function () {
				if ( reset ) {
					reset.disabled = sameAsDefaults();
				}
			};

			list.addEventListener( 'input', check );
			list.addEventListener( 'change', check );

			var changed = function () {
				list.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			};

			add.addEventListener( 'click', function () {
				var row = tpl.content.firstElementChild.cloneNode( true );

				list.appendChild( row );
				row.querySelector( 'input' ).focus();
				changed();
			} );

			list.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( '.sb-site-kit-rule__remove' );

				if ( button ) {
					button.closest( '.sb-site-kit-rule' ).remove();
					changed();
				}
			} );
		}() );
		</script>
		<?php
	}
}
