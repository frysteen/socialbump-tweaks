<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which plugin, theme or snippet registers each image size, for the groups on
 * SB Image Cleaner.
 *
 * WordPress does not record who called add_image_size(), so this reads the
 * code: every PHP file in the plugins, themes and must-use plugins folders
 * that calls add_image_size(), and the snippets kept by WPCodeBox, Code
 * Snippets and WPCode, looking for the size's name in quotes. When more than
 * one place names it, a source other than SB Tweaks beats SB Tweaks, an active
 * one beats one switched off, and then the one where the name sits nearest an
 * add_image_size() call wins.
 *
 * It cannot see a name built up in code ('image-' . $width) or added through
 * a filter, so those go under Unknown source rather than a wrong group.
 *
 * The answer is kept in an option and worked out again when the plugins, the
 * theme or the snippets change. Names asked about later are searched for then
 * and added to it.
 */
class SB_Images_Sources {

	const OPTION = 'sb_tweaks_images_size_sources';

	/** Raised when the way sources are found changes, so every site searches again. */
	const SCHEME = 3;

	/**
	 * A source's name for the page, with a snippet's own title linking to it
	 * where its plugin can open a snippet from a link.
	 */
	public static function title_html( array $source ) {
		if ( ! empty( $source['url'] ) && isset( $source['name'] ) && (string) $source['name'] !== '' ) {
			return esc_html( (string) ( $source['prefix'] ?? '' ) ) . '<a href="' . esc_url( (string) $source['url'] ) . '">' . esc_html( (string) $source['name'] ) . '</a>' . esc_html( (string) ( $source['suffix'] ?? '' ) );
		}

		return esc_html( (string) ( $source['label'] ?? '' ) );
	}

	/**
	 * Where to edit a snippet, for the plugins known to open one from a link:
	 * WPCodeBox 2 reads ?snippet= on its page (checked in 1.4.1 on feelsoma.com),
	 * and Code Snippets answers through its own get_snippet_edit_url(). WPCode
	 * gets no link.
	 */
	private static function edit_url( $table, $id ) {
		global $wpdb;

		if ( $table === $wpdb->prefix . 'wpcb_snippets' ) {
			return in_array( 'wpcodebox2/wpcodebox2.php', self::active_plugins(), true ) ? admin_url( 'admin.php?page=wpcodebox2&snippet=' . (int) $id ) : '';
		}

		if ( $table === $wpdb->prefix . 'snippets' && function_exists( 'Code_Snippets\code_snippets' ) ) {
			$plugin = call_user_func( 'Code_Snippets\code_snippets' );

			if ( is_object( $plugin ) && method_exists( $plugin, 'get_snippet_edit_url' ) ) {
				return (string) $plugin->get_snippet_edit_url( (int) $id );
			}
		}

		return '';
	}

	/** Search again on the next look. SB Image Cleaner's Scan calls this. */
	public static function forget() {
		delete_option( self::OPTION );
	}

	/**
	 * Where each name is registered, for the names found: name => key and label.
	 * Names found nowhere are left out.
	 */
	public static function of( array $names ) {
		$names = array_values( array_unique( array_filter( array_map( 'strval', $names ), 'strlen' ) ) );

		if ( ! $names ) {
			return [];
		}

		$sig  = self::signature();
		$kept = get_option( self::OPTION, [] );

		if ( ! is_array( $kept ) || ( $kept['sig'] ?? '' ) !== $sig ) {
			$kept = [ 'sig' => $sig, 'searched' => [], 'map' => [] ];
		}

		$new = array_values( array_diff( $names, array_map( 'strval', (array) $kept['searched'] ) ) );

		if ( $new ) {
			$kept['map']      = self::find( $new ) + (array) $kept['map'];
			$kept['searched'] = array_values( array_unique( array_merge( array_map( 'strval', (array) $kept['searched'] ), $new ) ) );
			$kept['time']     = time();

			update_option( self::OPTION, $kept, false );
		}

		$map = [];

		foreach ( $names as $name ) {
			if ( isset( $kept['map'][ $name ] ) ) {
				$map[ $name ] = $kept['map'][ $name ];
			}
		}

		return $map;
	}

	/**
	 * What the answer depends on: which plugins are there and on (their main
	 * files change when they update), the theme, and the snippets.
	 */
	private static function signature() {
		global $wpdb;

		$active = self::active_plugins();
		$parts  = [ self::SCHEME, $active, get_stylesheet(), get_template(), wp_get_theme()->get( 'Version' ) ];

		// A plugin folder added or removed changes the folder's own time.
		$parts[] = @filemtime( WP_PLUGIN_DIR ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$parts[] = is_dir( WPMU_PLUGIN_DIR ) ? @filemtime( WPMU_PLUGIN_DIR ) : 0; // phpcs:ignore WordPress.PHP.NoSilencedErrors

		foreach ( $active as $file ) {
			$parts[] = @filemtime( WP_PLUGIN_DIR . '/' . $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		foreach ( self::snippet_tables() as $table => $c ) {
			$parts[] = $wpdb->get_row( "SELECT COUNT(*) AS n, MAX(`{$c['stamp']}`) AS m FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB
		}

		$parts[] = $wpdb->get_row( "SELECT COUNT(*) AS n, MAX(post_modified_gmt) AS m FROM {$wpdb->posts} WHERE post_type = 'wpcode'", ARRAY_A ); // phpcs:ignore WordPress.DB

		return md5( (string) wp_json_encode( $parts ) );
	}

	private static function active_plugins() {
		$active = (array) get_option( 'active_plugins', [] );

		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
		}

		$active = array_values( array_unique( array_map( 'strval', $active ) ) );
		sort( $active );

		return $active;
	}

	/** Search the code for each name. */
	private static function find( array $names ) {
		$best = [];

		foreach ( self::code_files() as $path => $source ) {
			$text = @file_get_contents( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions

			if ( is_string( $text ) ) {
				self::weigh( $text, $source, $names, $best );
			}
		}

		foreach ( self::snippets() as $source ) {
			self::weigh( $source['text'], $source, $names, $best );
		}

		// Elementor names a size after the width and height typed into a widget's
		// Custom image size and makes it on the spot. It is never registered, so
		// no code names it.
		foreach ( $names as $name ) {
			if ( ! isset( $best[ $name ] ) && strpos( $name, 'elementor_custom_' ) === 0 ) {
				$best[ $name ] = [ 'key' => 'elementor-custom', 'label' => __( 'Made by an Elementor widget set to a Custom image size', 'sb-tweaks' ), 'score' => [] ];
			}
		}

		$map = [];

		foreach ( $best as $name => $found ) {
			$map[ (string) $name ] = [
				'key'    => $found['key'],
				'label'  => $found['label'],
				'prefix' => $found['prefix'] ?? '',
				'name'   => $found['name'] ?? '',
				'suffix' => $found['suffix'] ?? '',
				'url'    => $found['url'] ?? '',
			];
		}

		return $map;
	}

	/** Note each name this piece of code mentions, if it beats what was found before. */
	private static function weigh( $text, array $source, array $names, array &$best ) {
		if ( stripos( $text, 'add_image_size' ) === false || ! preg_match_all( '/add_image_size/i', $text, $calls, PREG_OFFSET_CAPTURE ) ) {
			return;
		}

		$at = [];

		foreach ( $calls[0] as $call ) {
			$at[] = (int) $call[1];
		}

		foreach ( $names as $name ) {
			if ( ! preg_match_all( '/[\'"]' . preg_quote( $name, '/' ) . '[\'"]/', $text, $hits, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}

			$near = PHP_INT_MAX;

			foreach ( $hits[0] as $hit ) {
				foreach ( $at as $pos ) {
					$near = min( $near, abs( (int) $hit[1] - $pos ) );
				}
			}

			$score = [ $source['ours'] ? 1 : 0, $source['active'] ? 0 : 1, $near ];

			if ( ! isset( $best[ $name ] ) || self::beats( $score, $best[ $name ]['score'] ) ) {
				$best[ $name ] = [
					'key'    => $source['key'],
					'label'  => $source['label'],
					'prefix' => $source['prefix'] ?? '',
					'name'   => $source['name'] ?? '',
					'suffix' => $source['suffix'] ?? '',
					'url'    => $source['url'] ?? '',
					'score'  => $score,
				];
			}
		}
	}

	private static function beats( array $a, array $b ) {
		foreach ( $a as $i => $value ) {
			if ( $value !== $b[ $i ] ) {
				return $value < $b[ $i ];
			}
		}

		return false;
	}

	/** Every PHP file that calls add_image_size(), with the plugin or theme it belongs to. */
	private static function code_files() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active  = self::active_plugins();
		$ours    = basename( dirname( untrailingslashit( SB_IMAGES_PATH ), 2 ) );
		$off     = __( '(switched off)', 'sb-tweaks' );
		$folders = [];
		$files   = [];

		foreach ( get_plugins() as $file => $data ) {
			$folder = strpos( $file, '/' ) !== false ? dirname( $file ) : $file;
			$on     = in_array( $file, $active, true ) || ! empty( $folders[ $folder ]['active'] );

			$folders[ $folder ] = [
				'name'   => ! empty( $data['Name'] ) ? $data['Name'] : $folder,
				'active' => $on,
			];
		}

		foreach ( self::grep( WP_PLUGIN_DIR ) as $path ) {
			$top = strtok( self::relative( $path, WP_PLUGIN_DIR ), '/' );

			if ( $top === false || ! isset( $folders[ $top ] ) ) {
				continue;
			}

			$files[ $path ] = [
				'key'    => 'plugin:' . $top,
				'label'  => $folders[ $top ]['name'] . ( $folders[ $top ]['active'] ? '' : ' ' . $off ),
				'active' => $folders[ $top ]['active'],
				'ours'   => $top === $ours,
			];
		}

		$root = get_theme_root();

		foreach ( self::grep( $root ) as $path ) {
			$slug  = strtok( self::relative( $path, $root ), '/' );
			$theme = $slug !== false ? wp_get_theme( $slug ) : null;

			if ( ! $theme || ! $theme->exists() ) {
				continue;
			}

			$on = in_array( $slug, [ get_stylesheet(), get_template() ], true );

			$files[ $path ] = [
				'key'    => 'theme:' . $slug,
				/* translators: %s: theme name */
				'label'  => sprintf( __( 'Theme: %s', 'sb-tweaks' ), $theme->get( 'Name' ) ) . ( $on ? '' : ' ' . $off ),
				'active' => $on,
				'ours'   => false,
			];
		}

		foreach ( self::grep( WPMU_PLUGIN_DIR ) as $path ) {
			$top = strtok( self::relative( $path, WPMU_PLUGIN_DIR ), '/' );

			$files[ $path ] = [
				'key'    => 'mu:' . $top,
				/* translators: %s: file or folder name */
				'label'  => sprintf( __( 'Must-use plugin: %s', 'sb-tweaks' ), $top ),
				'active' => true,
				'ours'   => false,
			];
		}

		return $files;
	}

	private static function relative( $path, $root ) {
		return ltrim( substr( wp_normalize_path( $path ), strlen( untrailingslashit( wp_normalize_path( $root ) ) ) ), '/' );
	}

	/** The PHP files under a folder that mention add_image_size(). */
	private static function grep( $dir ) {
		if ( ! $dir || ! is_dir( $dir ) ) {
			return [];
		}

		if ( self::can_exec() ) {
			$out = [];
			$rc  = 0;

			exec( 'grep -rlI --include=*.php add_image_size ' . escapeshellarg( $dir ) . ' 2>/dev/null', $out, $rc ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions

			// 0 found some, 1 found none. Anything else, try without grep.
			if ( $rc === 0 || $rc === 1 ) {
				return $out;
			}
		}

		$found = [];
		$walk  = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
				function ( $file ) {
					return ! ( $file->isDir() && in_array( $file->getFilename(), [ 'node_modules', '.git' ], true ) );
				}
			)
		);

		foreach ( $walk as $file ) {
			if ( substr( $file->getFilename(), -4 ) !== '.php' || $file->getSize() > 2000000 ) {
				continue;
			}

			$text = @file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions

			if ( is_string( $text ) && stripos( $text, 'add_image_size' ) !== false ) {
				$found[] = $file->getPathname();
			}
		}

		return $found;
	}

	private static function can_exec() {
		$off = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );

		return function_exists( 'exec' ) && ! in_array( 'exec', $off, true );
	}

	/** The snippet plugins' tables this site has, with the columns needed. */
	private static function snippet_tables() {
		static $found = null;

		if ( $found !== null ) {
			return $found;
		}

		global $wpdb;

		$found = [];
		$known = [
			'wpcb_snippets' => [ 'id' => 'id', 'title' => 'title', 'code' => 'code', 'on' => 'enabled', 'stamp' => 'lastModified', 'label' => __( 'WPCodeBox snippet', 'sb-tweaks' ) ],
			'snippets'      => [ 'id' => 'id', 'title' => 'name', 'code' => 'code', 'on' => 'active', 'stamp' => 'modified', 'label' => __( 'Code Snippets', 'sb-tweaks' ) ],
		];

		foreach ( $known as $suffix => $c ) {
			$table = $wpdb->prefix . $suffix;

			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
				continue;
			}

			$have = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" ); // phpcs:ignore WordPress.DB

			if ( array_diff( [ $c['id'], $c['title'], $c['code'], $c['on'] ], $have ) ) {
				continue;
			}

			if ( ! in_array( $c['stamp'], $have, true ) ) {
				$c['stamp'] = $c['id'];
			}

			$found[ $table ] = $c;
		}

		return $found;
	}

	/** Snippets that call add_image_size(), switched on or not. */
	private static function snippets() {
		global $wpdb;

		$off  = __( '(switched off)', 'sb-tweaks' );
		$list = [];

		foreach ( self::snippet_tables() as $table => $c ) {
			$rows = $wpdb->get_results( "SELECT `{$c['id']}` AS id, `{$c['title']}` AS title, `{$c['on']}` AS active, `{$c['code']}` AS code FROM `{$table}` WHERE `{$c['code']}` LIKE '%add\\_image\\_size%'", ARRAY_A ); // phpcs:ignore WordPress.DB

			foreach ( (array) $rows as $row ) {
				$on = (bool) (int) $row['active'];

				$list[] = [
					'key'    => 'snippet:' . $table . ':' . $row['id'],
					/* translators: 1: snippet plugin, 2: snippet id, 3: snippet title */
					'label'  => sprintf( __( '%1$s #%2$s: %3$s', 'sb-tweaks' ), $c['label'], $row['id'], $row['title'] ) . ( $on ? '' : ' ' . $off ),
					/* translators: 1: snippet plugin, 2: snippet id */
					'prefix' => sprintf( __( '%1$s #%2$s:', 'sb-tweaks' ), $c['label'], $row['id'] ) . ' ',
					'name'   => (string) $row['title'],
					'suffix' => $on ? '' : ' ' . $off,
					'url'    => self::edit_url( $table, (int) $row['id'] ),
					'active' => $on,
					'ours'   => false,
					'text'   => (string) $row['code'],
				];
			}
		}

		$posts = $wpdb->get_results( "SELECT ID, post_title, post_status, post_content FROM {$wpdb->posts} WHERE post_type = 'wpcode' AND post_status IN ( 'publish', 'draft' ) AND post_content LIKE '%add\\_image\\_size%'", ARRAY_A ); // phpcs:ignore WordPress.DB

		foreach ( (array) $posts as $row ) {
			$on = $row['post_status'] === 'publish';

			$list[] = [
				'key'    => 'snippet:wpcode:' . $row['ID'],
				/* translators: 1: snippet plugin, 2: snippet id, 3: snippet title */
				'label'  => sprintf( __( '%1$s #%2$s: %3$s', 'sb-tweaks' ), __( 'WPCode snippet', 'sb-tweaks' ), $row['ID'], $row['post_title'] ) . ( $on ? '' : ' ' . $off ),
				'active' => $on,
				'ours'   => false,
				'text'   => (string) $row['post_content'],
			];
		}

		return $list;
	}
}
