<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The SB Image Cleaner: scan, build, clear old sizes, and orphans.
 *
 * Split out of the Images module so a site that only wants the size list does
 * not pay for the tools. With this module off nothing here loads at all, which
 * matters most in the media library: the sizes panel used to be built for every
 * attachment on the page whether or not anyone opened the details pane.
 *
 * The size engine itself stays with the Images module, because registering a
 * size is what records it as ours, and that has to keep happening whether or
 * not the cleaner is switched on.
 */
class SB_Images_Cleaner {

	public static function boot() {
		// The size engine and the width list live with the Images module, but the
		// cleaner does not need that module switched on: loading the two classes
		// registers nothing, it only makes the size names and widths readable.
		require_once SB_IMAGES_PATH . 'features/images/class-sb-images.php';
		require_once SB_IMAGES_PATH . 'features/images/class-sb-images-rebuild.php';
		require_once __DIR__ . '/class-sb-images-tools.php';

		SB_Images_Tools::boot();

		add_action( 'admin_post_sb_images_save_cleaner_sizes', [ __CLASS__, 'save_sizes' ] );
		add_action( 'admin_post_sb_images_adopt_sizes', [ __CLASS__, 'adopt_sizes' ] );
		add_action( 'admin_post_sb_images_unadopt_sizes', [ __CLASS__, 'unadopt_sizes' ] );
	}

	const CHOICE_META = 'sb_tweaks_images_cleaner_sizes';

	/**
	 * Every registered size, grouped for the list on the page: ours, one group
	 * per plugin, theme or snippet that registers the rest, Unknown source, and
	 * the ones WordPress makes.
	 *
	 * WordPress does not record who registered a size, so SB_Images_Sources
	 * reads the code for each name. A name built up in code or added through a
	 * filter cannot be found that way and goes under Unknown source rather than
	 * a wrong group. That miss is why this was dropped once; Sam asked again in
	 * October 2026 after feelsoma.com's list mixed a WPCodeBox snippet, Modern
	 * Events Calendar, WooCommerce and LearnDash in one width order, and with
	 * Unknown source as the fallback it is honest about what it cannot place.
	 * WooCommerce's group used to go by the woocommerce_ name; it now comes
	 * from the search like any other plugin.
	 */
	public static function size_groups() {
		require_once __DIR__ . '/class-sb-images-sources.php';

		$core = [ 'thumbnail', 'medium', 'medium_large', 'large', '1536x1536', '2048x2048' ];

		// Image Management's own: the widths SB Image Settings registers right now. An
		// image- name from a snippet, or one Image Management used to register, is not
		// one of ours just because of how it is spelt.
		$ours = [];

		if ( SB_Images_Module::is_enabled( 'images' ) && SB_Images::setting( 'sizes_on', 1 ) ) {
			foreach ( SB_Images::widths() as $width ) {
				$ours[] = 'image-' . (int) $width;
			}
		}

		$all     = SB_Images_Rebuild::all_wanted();
		$names   = array_map( 'strval', array_keys( $all ) );
		$sources = SB_Images_Sources::of( array_values( array_diff( $names, $ours, $core ) ) );
		$found   = [];
		$unknown = [];
		$wp      = [];
		$mine    = [];

		foreach ( $all as $name => $size ) {
			$name = (string) $name;

			if ( in_array( $name, $ours, true ) ) {
				$mine[ $name ] = $size;
			} elseif ( in_array( $name, $core, true ) ) {
				$wp[ $name ] = $size;
			} elseif ( isset( $sources[ $name ] ) ) {
				$key = 'source-' . md5( (string) $sources[ $name ]['key'] );

				if ( ! isset( $found[ $key ] ) ) {
					$found[ $key ] = [ 'title' => (string) $sources[ $name ]['label'], 'sizes' => [] ];
				}

				$found[ $key ]['sizes'][ $name ] = $size;
			} else {
				$unknown[ $name ] = $size;
			}
		}

		uasort(
			$found,
			function ( $a, $b ) {
				return strnatcasecmp( $a['title'], $b['title'] );
			}
		);

		$groups = [ 'ours' => [ 'title' => __( 'Image Sizes', 'sb-tweaks' ), 'sizes' => $mine ] ]
			+ $found
			+ [
				'unknown'   => [ 'title' => __( 'Unknown source', 'sb-tweaks' ), 'sizes' => $unknown ],
				'wordpress' => [ 'title' => __( 'WordPress', 'sb-tweaks' ), 'sizes' => $wp ],
			];

		foreach ( $groups as $key => $group ) {
			if ( ! $group['sizes'] ) {
				unset( $groups[ $key ] );

				continue;
			}

			uasort(
				$groups[ $key ]['sizes'],
				function ( $a, $b ) {
					return (int) $a['width'] <=> (int) $b['width'];
				}
			);
		}

		return $groups;
	}

	/**
	 * The sizes this user has ticked: everything until they save a choice, and
	 * never a name that is no longer registered. The scan, Build and Remove old
	 * sizes all work within this list.
	 *
	 * What is saved is the sizes left unticked ( [ 'off' => [...] ] ), so a size
	 * added on SB Image Settings, or a WordPress size switched back on, starts
	 * ticked. Site Kit saved the ticked ones instead, which left every new size
	 * unticked until someone noticed; that list is turned round the first time
	 * it is read, against the sizes registered then.
	 */
	public static function chosen() {
		$all   = array_keys( SB_Images_Rebuild::all_wanted() );
		$saved = get_user_meta( get_current_user_id(), self::CHOICE_META, true );

		if ( ! is_array( $saved ) ) {
			return $all;
		}

		if ( ! array_key_exists( 'off', $saved ) ) {
			$ticked = array_map( 'strval', $saved );
			$saved  = [ 'off' => array_values( array_diff( $all, $ticked ) ) ];

			update_user_meta( get_current_user_id(), self::CHOICE_META, $saved );
		}

		return array_values( array_diff( $all, array_map( 'strval', (array) $saved['off'] ) ) );
	}

	/**
	 * A plain form save rather than AJAX, so the page behaves like the others:
	 * the button stays quiet until something is ticked, the reminder follows you
	 * down the page, and leaving with changes pending warns first.
	 */
	/** Choose leftover sizes from other sources for clearing. Admins only. */
	public static function adopt_sizes() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'sb-tweaks' ) );
		}

		check_admin_referer( 'sb_images_adopt_sizes' );

		$asked = isset( $_POST['sb_images_adopt'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['sb_images_adopt'] ) ) : [];
		$added = SB_Images_Rebuild::adopt( $asked );

		wp_safe_redirect( add_query_arg( [ 'sb_images_adopted' => count( $added ), 'sb_images_refused' => count( array_unique( $asked ) ) - count( $added ) ], SB_Images_Module::page_url( 'cleaner' ) ) );
		exit;
	}

	/** Stop clearing a size chosen earlier. Admins only. */
	public static function unadopt_sizes() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'sb-tweaks' ) );
		}

		check_admin_referer( 'sb_images_unadopt_sizes' );

		$name = isset( $_POST['sb_images_unadopt'] ) ? sanitize_text_field( wp_unslash( $_POST['sb_images_unadopt'] ) ) : '';

		if ( $name !== '' ) {
			SB_Images_Rebuild::unadopt( [ $name ] );
		}

		wp_safe_redirect( add_query_arg( 'sb_images_unadopted', 1, SB_Images_Module::page_url( 'cleaner' ) ) );
		exit;
	}

	public static function save_sizes() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'sb-tweaks' ) );
		}

		check_admin_referer( 'sb_images_save_cleaner_sizes' );

		$asked = isset( $_POST['sb_images_sizes'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['sb_images_sizes'] ) ) : [];
		$off   = array_values( array_diff( array_keys( SB_Images_Rebuild::all_wanted() ), $asked ) );

		// The unticked ones, so a size added later starts ticked. See chosen().
		update_user_meta( get_current_user_id(), self::CHOICE_META, [ 'off' => $off ] );

		wp_safe_redirect( SB_Images_Module::page_url( 'cleaner', [ 'updated' => 'true' ] ) );
		exit;
	}

	/** The size list, with a tick per size, all or none per group, and Save. */
	/**
	 * Hub only: the prompt to start a new chat with when moving a site onto the
	 * standard sizes, read from docs/size-migration-prompt.md, with a copy button.
	 */
	private static function render_migration_prompt() {
		if ( ! function_exists( 'sb_tweaks_is_hub' ) || ! sb_tweaks_is_hub() ) {
			return;
		}

		$file = SB_IMAGES_PATH . 'docs/size-migration-prompt.md';

		if ( ! is_readable( $file ) ) {
			return;
		}

		echo '<section class="sb-tweaks-section sb-images-cleaner__prompt" id="sb-images-migration-prompt">';
		echo '<div class="sb-tweaks-section__head"><div><h2>' . esc_html__( 'Size migration prompt', 'sb-tweaks' ) . '</h2>';
		echo '<p>' . esc_html__( 'For a new chat on a site moving onto the standard sizes. Hub only.', 'sb-tweaks' ) . '</p></div></div>';
		echo '<textarea class="sb-images-cleaner__prompt-text" id="sb-images-migration-prompt-text" rows="14" readonly>' . esc_textarea( (string) file_get_contents( $file ) ) . '</textarea>';
		echo '<p class="sb-images-cleaner__prompt-actions"><button type="button" class="button" id="sb-images-migration-prompt-copy">' . esc_html__( 'Copy prompt', 'sb-tweaks' ) . '</button></p>';
		echo '</section>';
		?>
		<script>
		( function () {
			var button = document.getElementById( 'sb-images-migration-prompt-copy' );
			var text   = document.getElementById( 'sb-images-migration-prompt-text' );

			if ( ! button || ! text ) {
				return;
			}

			var label = button.textContent;

			function copied() {
				button.textContent = 'Copied';
				setTimeout( function () {
					button.textContent = label;
				}, 2000 );
			}

			function fallback() {
				text.focus();
				text.select();
				document.execCommand( 'copy' );
				copied();
			}

			button.addEventListener( 'click', function () {
				if ( navigator.clipboard && window.isSecureContext ) {
					navigator.clipboard.writeText( text.value ).then( copied, fallback );
				} else {
					fallback();
				}
			} );
		}() );
		</script>
		<?php
	}

	private static function render_sizes() {
		$chosen = self::chosen();

		echo '<form method="post" autocomplete="off" data-sb-dirty action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="sb_images_save_cleaner_sizes">';
		wp_nonce_field( 'sb_images_save_cleaner_sizes' );
		echo '<section class="sb-tweaks-section sb-images-cleaner__sizes" id="sb-images-cleaner-sizes">';
		echo '<div class="sb-tweaks-section__head"><div class="sb-images-section__row"><h2>' . esc_html__( 'Sizes', 'sb-tweaks' ) . '</h2>';
		echo '</div>';
		echo '<p>' . esc_html__( 'Tick the sizes the scan, Build and Remove old sizes should work on. Saved for you, not the site.', 'sb-tweaks' ) . '</p></div>';

		foreach ( self::size_groups() as $key => $group ) {
			echo '<div class="sb-images-sizegroup" data-sizegroup="' . esc_attr( $key ) . '">';
			echo '<div class="sb-images-sizegroup__head"><span class="sb-images-sizegroup__title"><strong>' . esc_html( $group['title'] ) . '</strong>';

			// Only Image Management's own widths can be changed, on SB Image Settings, which
			// exists only while Images is switched on.
			if ( 'ours' === $key && SB_Images_Module::is_enabled( 'images' ) ) {
				echo '<a class="sb-images-sizegroup__edit" href="' . esc_url( SB_Images_Module::page_url( 'settings' ) ) . '">' . esc_html__( 'Edit Sizes', 'sb-tweaks' ) . '</a>';
			}

			echo '</span>';
			echo '<span><button type="button" class="button-link sb-toggle" data-sb-always-on data-sizes-all>' . esc_html__( 'all', 'sb-tweaks' ) . '</button> | <button type="button" class="button-link sb-toggle" data-sb-always-on data-sizes-none>' . esc_html__( 'none', 'sb-tweaks' ) . '</button></span></div>';

			if ( 'unknown' === $key ) {
				echo '<p class="sb-images-sizegroup__note">' . esc_html__( 'Not found in any plugin, theme or snippet: the name may be built up in code or added through a filter.', 'sb-tweaks' ) . '</p>';
			}

			foreach ( $group['sizes'] as $name => $size ) {
				$dims = $size['width'] ? number_format_i18n( $size['width'] ) : '';

				if ( $size['height'] && $size['height'] < 9999 ) {
					$dims .= ' x ' . number_format_i18n( $size['height'] );
				}

				echo '<label class="sb-images-sizegroup__item"><input type="checkbox" class="sb-images-size-choice" name="sb_images_sizes[]" value="' . esc_attr( $name ) . '" ' . checked( in_array( $name, $chosen, true ), true, false ) . '>';
				echo '<code>' . esc_html( $name ) . '</code><span>' . esc_html( $dims ) . ( $dims ? ' px' : '' ) . ( ! empty( $size['crop'] ) ? ', ' . esc_html__( 'cropped', 'sb-tweaks' ) : '' ) . '</span></label>';
			}

			echo '</div>';
		}

		echo '<div class="sb-images-cleaner__sizes-foot">';
		echo '<span><button type="button" class="button-link sb-toggle" data-sb-always-on id="sb-images-sizes-all">' . esc_html__( 'Select all', 'sb-tweaks' ) . '</button> | <button type="button" class="button-link sb-toggle" data-sb-always-on id="sb-images-sizes-none">' . esc_html__( 'Select none', 'sb-tweaks' ) . '</button></span>';
		echo '<button type="submit" class="button sb-save--clean" id="sb-images-sizes-save" data-sb-label-dirty="' . esc_attr__( 'Save sizes', 'sb-tweaks' ) . '" disabled>' . esc_html__( 'Save sizes', 'sb-tweaks' ) . '</button>';
		echo '</div>';
		echo '</section>';
		echo '</form>';
	}

	/**
	 * The page. Counts are loaded after it, and the work runs in batches, so a
	 * large library cannot stall the request.
	 */
	public static function render_page() {
		if ( isset( $_GET['sb_images_adopted'] ) ) {
			$added   = (int) $_GET['sb_images_adopted'];
			$refused = (int) ( $_GET['sb_images_refused'] ?? 0 );
			/* translators: %s: number of sizes */
			$message = sprintf( _n( '%s size added to Old thumbnails to clear. Scan again to see it there.', '%s sizes added to Old thumbnails to clear. Scan again to see them there.', $added, 'sb-tweaks' ), number_format_i18n( $added ) );

			if ( $refused ) {
				/* translators: %s: number of sizes */
				$message .= ' ' . sprintf( _n( '%s was left out because it is registered again.', '%s were left out because they are registered again.', $refused, 'sb-tweaks' ), number_format_i18n( $refused ) );
			}

			echo '<div class="notice notice-success inline"><p>' . esc_html( $message ) . '</p></div>';
		}

		if ( isset( $_GET['sb_images_unadopted'] ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'That size will no longer be cleared.', 'sb-tweaks' ) . '</p></div>';
		}

		// Locked sizes (padlocks on SB Image Settings) are never removed: say which.
		$locked = SB_Images::locked_sizes();

		if ( $locked ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html(
				sprintf(
					/* translators: %s: size names */
					_n( 'Locked size, never removed: %s.', 'Locked sizes, never removed: %s.', count( $locked ), 'sb-tweaks' ),
					implode( ', ', $locked )
				)
			) . ' <a href="' . esc_url( SB_Images_Module::page_url( 'settings' ) ) . '">' . esc_html__( 'SB Image Settings', 'sb-tweaks' ) . '</a></p></div>';
		}

		// Notices go above the grid. Inside it a notice takes a grid cell and pushes
		// the Sizes and Rebuild panels out of place.
		echo '<div class="sb-images-cleaner">';

		// The first column: Sizes, and on the hub the size migration prompt under it.
		echo '<div class="sb-images-cleaner__side">';
		self::render_sizes();
		self::render_migration_prompt();
		echo '</div>';

		echo '<section class="sb-tweaks-section sb-images-rebuild" id="sb-images-rebuild" data-nonce="' . esc_attr( wp_create_nonce( 'sb_images' ) ) . '">';
		echo '<div class="sb-tweaks-section__head"><div><h2>' . esc_html__( 'Rebuild Thumbnails', 'sb-tweaks' ) . '</h2>';
		echo '<p>' . esc_html__( 'Scan the library to see what is missing, what is left over from sizes you have removed, and what is sitting in the uploads folder unaccounted for.', 'sb-tweaks' ) . '</p></div></div>';
		echo '<div class="sb-images-rebuild-body" id="sb-images-rebuild-body">';

		// The buttons sit at the top, under the instructions, so the report, the
		// progress and the lists open below them rather than pushing them down.
		echo '<div class="sb-images-rebuild__actions">';
		echo '<button type="button" class="button button-primary" id="sb-images-scan">' . esc_html__( 'Scan images', 'sb-tweaks' ) . '</button>';
		// Next to Scan, not at the end: the buttons between them only appear after a
		// scan, and the two that start a scan belong together.
		echo '<button type="button" class="button sb-tweaks-button--quiet" id="sb-images-deep">' . esc_html__( 'Find leftover thumbnails', 'sb-tweaks' ) . '</button>';
		echo '<button type="button" class="button sb-tweaks-button--go" id="sb-images-rebuild-run" hidden disabled>' . esc_html__( 'Build Thumbnails', 'sb-tweaks' ) . '</button>';
		echo '<button type="button" class="button sb-tweaks-button--danger" id="sb-images-rebuild-clean" hidden>' . esc_html__( 'Remove old sizes', 'sb-tweaks' ) . '</button>';
		echo '<button type="button" class="button sb-tweaks-button--danger" id="sb-images-orphans-run" hidden>' . esc_html__( 'Delete orphan images', 'sb-tweaks' ) . '</button>';
		echo '<label class="sb-images-rebuild__force" id="sb-images-force-wrap" hidden><input type="checkbox" id="sb-images-rebuild-force"> ' . esc_html__( 'Force rebuild all thumbnails', 'sb-tweaks' ) . '</label>';
		echo '</div>';

		// The button names are bold, so the line reads as two instructions.
		echo '<p class="sb-images-rebuild__help">' . wp_kses( __( '<strong>Scan images</strong> to see what is missing or left over in the library. <strong>Find leftover thumbnails</strong> looks at the files themselves, for thumbnails nothing registers any more.', 'sb-tweaks' ), [ 'strong' => [] ] ) . '</p>';

		// Everything below is filled in once the scan has run.
		echo '<div class="sb-images-report__panel" id="sb-images-report" hidden></div>';
		// No thumbnail column: each row in the log carries its own image, and the
		// empty column only left a gap down the left of everything.
		echo '<div class="sb-images-progress" id="sb-images-progress" hidden>';
		echo '<button type="button" class="sb-images-progress__close" id="sb-images-progress-close" aria-label="' . esc_attr__( 'Hide progress', 'sb-tweaks' ) . '">&times;</button>';
		echo '<button type="button" class="sb-images-progress__cancel" id="sb-images-progress-cancel" hidden>' . esc_html__( 'Cancel', 'sb-tweaks' ) . '</button>';
		echo '<button type="button" class="sb-images-progress__cancel" id="sb-images-progress-takeover" hidden>' . esc_html__( 'Stop that run', 'sb-tweaks' ) . '</button>';
		echo '<div class="sb-images-rebuild__bar" id="sb-images-rebuild-bar"><span></span></div>';
		echo '<div class="sb-images-progress__line">';
		echo '<p class="sb-images-rebuild__status" role="status"></p>';
		echo '<p class="sb-images-progress__timer" id="sb-images-progress-timer"></p>';
		echo '</div>';
		echo '<ul class="sb-images-progress__log" id="sb-images-progress-log"></ul>';
		echo '</div>';

		// Filled in by the deep scan, which is a separate and slower job.
		echo '<div class="sb-images-deep" id="sb-images-deep-panel" hidden></div>';

		// The same buttons again under a long list (orphaned images, leftover
		// thumbnails, the images still to build), so they are to hand without
		// scrolling back up. Each copies its button at the top and passes the
		// click to it, so the two rows always match. Shown only while a list is open.
		echo '<div class="sb-images-rebuild__actions sb-images-rebuild__actions--foot" id="sb-images-actions-foot" hidden>';

		foreach ( [ 'sb-images-scan', 'sb-images-deep', 'sb-images-rebuild-run', 'sb-images-rebuild-clean', 'sb-images-orphans-run' ] as $source ) {
			echo '<button type="button" class="button" data-sb-images-mirror="' . esc_attr( $source ) . '" hidden></button>';
		}

		echo '</div>';

		echo '</div></section>';
		echo '</div>';
	}

}
