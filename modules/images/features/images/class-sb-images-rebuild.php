<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Building and removing image sizes.
 *
 * Only sizes this plugin registered are ever deleted. Every name it registers
 * is recorded in an option, so a width removed from the settings is still
 * recognised as ours later. WordPress, Bricks and WooCommerce sizes, and the
 * original file, are never touched.
 *
 * Optimiser plugins write a WebP or AVIF next to each file, either with the
 * extension added (name.ext.webp: WPvivid, EWWW, Imagify) or swapped
 * (name.webp: ShortPixel), so those copies are removed alongside the file
 * they belong to. See copies().
 */
class SB_Images_Rebuild {

	const OWNED_OPTION = 'sb_tweaks_images_owned_image_sizes';

	/**
	 * Sizes registered by something other than Image Management (a snippet, a theme,
	 * another plugin), since removed, that someone chose on the Image Cleaner
	 * page to clear as well. Kept apart from the owned list so it is plain
	 * which sizes were added by hand, and so each can be taken off again.
	 */
	const ADOPTED_OPTION = 'sb_tweaks_images_adopted_image_sizes';

	/**
	 * The sizes registered the last time SB Image Settings or SB Image Cleaner
	 * was opened, or a scan ran, as name => width, height and crop. Some
	 * plugins register sizes in the admin only: LearnDash Visual Customizer
	 * adds course_icon on admin_init, so on feelsoma.com a scan run over REST
	 * listed its 374 images as leftovers from other sources, a Select all away
	 * from deleting thumbnails a live plugin uses. See all_wanted().
	 */
	const SEEN_OPTION = 'sb_tweaks_images_seen_sizes';

	/**
	 * WordPress sizes that can be switched off on SB Image Settings. Thumbnail and
	 * medium always stay: the media library and the editor show them.
	 */
	const OFFABLE = [ 'medium_large', 'large', '1536x1536', '2048x2048' ];

	/**
	 * Switched off on a site that has never saved its SB Image Settings, and by Reset
	 * to default sizes: 1920 covers 1536 and 2048, and medium_large sits too
	 * close to 665 to be worth a file.
	 */
	const DEFAULT_OFF = [ 'medium_large', '1536x1536', '2048x2048' ];

	/**
	 * WordPress sizes switched off on SB Image Settings (images setting wp_off).
	 * They are taken out of WordPress's size list, so nothing makes them, and
	 * their files count as old sizes to clear without being chosen first.
	 */
	public static function wp_off() {
		if ( ! class_exists( 'SB_Images_Module' ) ) {
			return [];
		}

		$off = SB_Images_Module::setting( 'images', 'wp_off' );

		// Never saved: the standard set is off.
		if ( $off === null ) {
			$off = self::DEFAULT_OFF;
		}

		return array_values( array_intersect( (array) $off, self::OFFABLE ) );
	}
	/** Remember the size names we register, so we know what is safe to delete. */
	public static function remember( array $names ) {
		$owned = (array) get_option( self::OWNED_OPTION, [] );
		$merged = array_values( array_unique( array_merge( $owned, $names ) ) );

		if ( $merged !== $owned ) {
			update_option( self::OWNED_OPTION, $merged, false );
		}
	}

	public static function owned() {
		return (array) get_option( self::OWNED_OPTION, [] );
	}

	/** Leftover sizes from other sources chosen for clearing. */
	public static function adopted() {
		return array_values( array_filter( array_map( 'strval', (array) get_option( self::ADOPTED_OPTION, [] ) ) ) );
	}

	/**
	 * Choose leftover sizes from other sources for clearing.
	 *
	 * A size registered right now is never taken, whatever was ticked: it is
	 * live, and its files are in use. Nor is one Image Management already owns. Returns
	 * the names actually added.
	 */
	public static function adopt( array $names ) {
		$names = array_unique( array_filter( array_map( 'strval', $names ) ) );
		$names = array_values( array_diff( $names, array_keys( self::all_wanted() ), self::owned(), class_exists( 'SB_Images' ) ? SB_Images::locked_sizes() : [] ) );

		if ( $names ) {
			update_option( self::ADOPTED_OPTION, array_values( array_unique( array_merge( self::adopted(), $names ) ) ), false );
		}

		return $names;
	}

	/** Stop clearing sizes chosen earlier. */
	public static function unadopt( array $names ) {
		update_option( self::ADOPTED_OPTION, array_values( array_diff( self::adopted(), array_map( 'strval', $names ) ) ), false );
	}

	/**
	 * Take chosen sizes off the list once no image carries them.
	 *
	 * Called by the scan, which reads every image's metadata anyway. Once a
	 * clear has removed a size from the whole library the choice has done its
	 * job, and Stop clearing was the only way to take it off. If something
	 * writes the size back later, it shows under Leftover sizes again, unticked,
	 * for a fresh decision. Returns the names taken off.
	 */
	public static function forget_adopted_unused( array $carried ) {
		$unused = array_values( array_diff( self::adopted(), array_map( 'strval', $carried ) ) );

		if ( $unused ) {
			self::unadopt( $unused );
		}

		return $unused;
	}

	/**
	 * Sizes this attachment carries that nothing registers, Image Management never
	 * owned and nobody has chosen to clear: leftovers from a snippet, theme or
	 * plugin. Listed on SB Image Cleaner, never cleared until chosen.
	 */
	public static function foreign( $id, ?array $meta = null ) {
		$meta = $meta === null ? (array) wp_get_attachment_metadata( $id ) : $meta;
		$have = array_keys( (array) ( $meta['sizes'] ?? [] ) );

		// Never the site icon's own sizes: they were offered here, ticked for
		// clearing, and the favicon lost them. See is_site_icon_size().
		return array_values(
			array_filter(
				array_diff( $have, array_keys( self::wanted_for( $id ) ), self::owned(), self::adopted(), self::wp_off() ),
				function ( $name ) {
					return ! self::is_site_icon_size( $name );
				}
			)
		);
	}

	/** The files one size of one attachment has on disk, WebP and AVIF copies included. */
	public static function size_files( array $meta, $name, $folder ) {
		$found = [ 'files' => 0, 'bytes' => 0 ];

		if ( empty( $meta['sizes'][ $name ]['file'] ) || $folder === '' ) {
			return $found;
		}

		$path = self::size_path( $folder, $meta['sizes'][ $name ]['file'] );

		if ( $path === '' ) {
			return $found;
		}

		foreach ( self::copies( $path ) as $candidate ) {
			if ( file_exists( $candidate ) ) {
				$found['files']++;
				$found['bytes'] += (int) filesize( $candidate );
			}
		}

		return $found;
	}

	/**
	 * Whether an attachment's own file is missing from disk. Nothing can be
	 * built from it, so it is left out of Sizes to build and listed under
	 * Missing images instead.
	 */
	public static function original_missing( $id ) {
		$file = get_attached_file( $id );

		return ! $file || ! file_exists( $file );
	}

	/**
	 * Where a size file is on disk.
	 *
	 * WordPress records a size as a bare file name beside the original. Elementor
	 * writes the custom sizes its widgets ask for to uploads/elementor/thumbs/ and
	 * records them with that path, from the uploads folder. Looking beside the
	 * original found nothing there, so the scan counted no files and a clear
	 * dropped the entry but left the file. A name with a folder in it is read from
	 * the uploads folder. Anything that would climb out of it gives an empty path.
	 */
	public static function size_path( $folder, $file ) {
		$file = (string) $file;

		if ( $file === '' || strpos( $file, chr( 0 ) ) !== false || preg_match( '#(^|/)\.\.(/|$)#', $file ) ) {
			return '';
		}

		if ( strpos( $file, '/' ) === false ) {
			return untrailingslashit( (string) $folder ) . '/' . $file;
		}

		$uploads = wp_upload_dir( null, false );

		return trailingslashit( $uploads['basedir'] ) . ltrim( $file, '/' );
	}

	/** The size names the settings ask for right now. */
	/**
	 * The size names the kit currently registers.
	 *
	 * Nothing is registered while the Image sizes switch is off, so nothing is
	 * wanted either, and every image-* file on an attachment becomes a leftover
	 * to clear. Reading the widths without the switch meant turning the feature
	 * off left the files in place with the scan reporting nothing to remove.
	 */
	public static function wanted() {
		if ( ! SB_Images::setting( 'sizes_on', 1 ) ) {
			return [];
		}

		$names = [];

		foreach ( SB_Images::widths() as $width ) {
			$names[] = 'image-' . (int) $width;
		}

		return $names;
	}

	/**
	 * Sizes an attachment should have but does not.
	 *
	 * A size is only expected when the original is wider than it, because
	 * WordPress will not upscale an image to fill a larger size.
	 */
	/**
	 * Sizes this attachment has: the file is named, it is on disk, and it is
	 * still the size the registration asks for.
	 *
	 * That last test matters. Change a theme size from 270 x 400 to 400 x 600
	 * and every image keeps a real file at the old dimensions, so asking only
	 * whether the file exists says nothing needs doing, for ever. The old files
	 * turn up under leftovers while the size itself never rebuilds. Comparing
	 * the recorded dimensions with what the size would produce now makes a
	 * changed size show up as work, which is what a person expects.
	 */
	public static function present( $id, ?array $meta = null ) {
		$meta   = $meta === null ? (array) wp_get_attachment_metadata( $id ) : $meta;
		$file   = get_attached_file( $id );
		$sizes  = self::wanted_for( $id );
		$real   = self::dimensions( $id, $meta );
		$width  = $real[0];
		$height = $real[1];
		$have   = [];

		if ( ! $file ) {
			return $have;
		}

		$folder = trailingslashit( dirname( $file ) );

		foreach ( (array) ( $meta['sizes'] ?? [] ) as $name => $size ) {
			// An empty file is as good as none: the broken AVIF encoder on
			// simplystyleco.com.au blanked working sizes, and Build never saw them.
			if ( empty( $size['file'] ) || ! file_exists( self::size_path( $folder, $size['file'] ) ) || (int) filesize( self::size_path( $folder, $size['file'] ) ) < 1 ) {
				continue;
			}

			// A size nobody registers any more is not our business here; stale()
			// deals with those.
			if ( isset( $sizes[ $name ] ) && $width && $height ) {
				$want = image_resize_dimensions( $width, $height, (int) $sizes[ $name ]['width'], (int) $sizes[ $name ]['height'], ! empty( $sizes[ $name ]['crop'] ) );

				if ( $want && ( (int) $size['width'] !== (int) $want[4] || (int) $size['height'] !== (int) $want[5] ) ) {
					continue;
				}
			}

			$have[] = $name;
		}

		return $have;
	}
	public static function missing( $id, ?array $meta = null ) {
		$meta = $meta === null ? (array) wp_get_attachment_metadata( $id ) : $meta;

		if ( empty( $meta['width'] ) ) {
			return [];
		}

		// Nothing can be made without the original.
		if ( self::original_missing( $id ) ) {
			return [];
		}

		$have    = self::present( $id, $meta );
		$missing = [];

		foreach ( SB_Images::widths() as $width ) {
			$name = 'image-' . (int) $width;

			if ( in_array( $name, $have, true ) ) {
				continue;
			}

			if ( (int) $meta['width'] <= (int) $width ) {
				continue;
			}

			$missing[] = $name;
		}

		return $missing;
	}

	/** Sizes on this attachment that we own but no longer want. */
	/**
	 * Sizes this attachment still carries that the kit no longer registers.
	 *
	 * $only narrows the building work to the sizes ticked on the Image Cleaner
	 * page, and appears on the build methods below. It deliberately does not
	 * apply here: a size that has been removed is no longer registered, so it can
	 * never appear in that list, and filtering by it meant a removed size could
	 * never be found or cleared at all.
	 */
	public static function stale( $id, ?array $meta = null ) {
		$meta = $meta === null ? (array) wp_get_attachment_metadata( $id ) : $meta;
		$have = array_keys( (array) ( $meta['sizes'] ?? [] ) );

		// Measured against everything registered right now, not just our own list.
		// A site can register image-480 from a snippet while this module is switched
		// off, and those files are live: comparing with our list alone called 1,234
		// working thumbnails old sizes to clear on feelsoma.com. A size anyone
		// registers is never stale, whoever made the files.
		//
		// Only sizes Image Management registered itself, plus leftovers from other sources
		// someone chose on SB Image Cleaner (adopted). Checked against what
		// is registered at the moment of asking, so a chosen size that is
		// registered again is never stale, and clean() asks again per image.
		//
		// A locked size (SB_Images::locked_sizes()) is never stale, so neither
		// Remove old sizes nor a single image rebuild deletes its files.
		$stale = array_intersect( array_diff( $have, array_keys( self::wanted_for( $id ) ) ), array_merge( self::owned(), self::adopted(), self::wp_off() ) );

		// Nor the site icon's own sizes, even where an older version let them be
		// chosen for clearing.
		$stale = array_filter(
			$stale,
			function ( $name ) {
				return ! self::is_site_icon_size( $name );
			}
		);

		return array_values( class_exists( 'SB_Images' ) ? array_diff( $stale, SB_Images::locked_sizes() ) : $stale );
	}

	/** Remove a generated file and any WebP or AVIF written beside it. */
	/**
	 * Whether a size file is still used by another size of this attachment, or
	 * by any other attachment.
	 *
	 * Two sizes with the same dimensions share one file (medium at 480 and
	 * image-480, say), and a duplicate upload or a migration can leave two
	 * attachments pointing at the same file. Deleting it for one would break the
	 * other, so the file is left and only the metadata entry goes.
	 */
	public static function file_has_other_owner( $id, array $meta, $file ) {
		foreach ( (array) ( $meta['sizes'] ?? [] ) as $size ) {
			if ( ! empty( $size['file'] ) && $size['file'] === $file ) {
				return true;
			}
		}

		if ( ! empty( $meta['file'] ) && basename( $meta['file'] ) === $file ) {
			return true;
		}

		$owners = self::file_owners();
		$id     = (int) $id;

		if ( isset( $owners['shared'][ $file ] ) ) {
			foreach ( array_keys( $owners['shared'][ $file ] ) as $owner ) {
				if ( (int) $owner !== $id ) {
					return true;
				}
			}

			return false;
		}

		return isset( $owners['first'][ $file ] ) && $owners['first'][ $file ] !== $id;
	}

	/** File names in attachment metadata, read once per request by file_owners(). */
	private static $owners = null;

	/**
	 * Which attachments name each file in their metadata.
	 *
	 * file_has_other_owner() used to run one LIKE over every attachment's
	 * metadata for each file it was about to delete. On villarealestate.com.au
	 * (4,997 attachments, 14.5 MB of metadata) one of those took 150 ms, so
	 * clearing twelve old sizes cost about 1.4 seconds an image. Reading all the
	 * metadata once takes about 0.2 seconds and every check after that is an
	 * array lookup.
	 *
	 * Built once per request, from what is stored when it is first asked for.
	 * Clearing later in the same request only takes names out of the metadata,
	 * so a name still listed here can only keep a file, never delete one that
	 * is in use.
	 *
	 * The match is the same as the old query's: a serialised string value that
	 * is exactly the file name, folder included where an entry carries one (an
	 * Elementor custom size is stored as elementor/thumbs/<name>). Only values
	 * ending in a file extension are kept, which leaves out mime types and the
	 * rest of the metadata.
	 *
	 * @return array{first: array<string,int>, shared: array<string,array<int,bool>>}
	 */
	public static function file_owners() {
		if ( self::$owners !== null ) {
			return self::$owners;
		}

		global $wpdb;

		$first  = [];
		$shared = [];
		$last   = 0;

		do {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attachment_metadata' AND meta_id > %d ORDER BY meta_id LIMIT 500",
					$last
				)
			);

			foreach ( (array) $rows as $row ) {
				$last  = (int) $row->meta_id;
				$owner = (int) $row->post_id;

				if ( ! preg_match_all( '/s:\d+:"([^"]*\.[A-Za-z0-9]{2,5})";/', (string) $row->meta_value, $found ) ) {
					continue;
				}

				foreach ( array_unique( $found[1] ) as $name ) {
					if ( ! isset( $first[ $name ] ) ) {
						$first[ $name ] = $owner;
					} elseif ( $first[ $name ] !== $owner ) {
						$shared[ $name ][ $first[ $name ] ] = true;
						$shared[ $name ][ $owner ]          = true;
					}
				}
			}
		} while ( is_array( $rows ) && count( $rows ) === 500 );

		self::$owners = [ 'first' => $first, 'shared' => $shared ];

		return self::$owners;
	}

	public static function delete_file( $path ) {
		$removed = 0;

		foreach ( self::copies( $path ) as $file ) {
			if ( $file && file_exists( $file ) && is_writable( $file ) ) {
				wp_delete_file( $file );
				$removed++;
			}
		}

		return $removed;
	}

	/**
	 * Work on one attachment: build what is missing, remove what is stale.
	 */
	public static function process( $id, $force = false ) {
		$result = [
			'built'   => [],
			'removed' => [],
			'files'   => 0,
		];

		if ( ! wp_attachment_is_image( $id ) ) {
			return $result;
		}

		$file = get_attached_file( $id );

		if ( ! $file || ! file_exists( $file ) ) {
			return $result;
		}

		$meta = (array) wp_get_attachment_metadata( $id );
		$dir  = dirname( $file );

		// Remove sizes we own that are no longer wanted: not a file another
		// size or another image still uses (as clean() checks, 1 November 2026).
		foreach ( self::stale( $id, $meta ) as $name ) {
			$stale_file = (string) ( $meta['sizes'][ $name ]['file'] ?? '' );

			unset( $meta['sizes'][ $name ] );
			$result['removed'][] = $name;

			if ( $stale_file !== '' && ! self::file_has_other_owner( $id, $meta, $stale_file ) ) {
				$result['files'] += self::delete_file( self::size_path( $dir, $stale_file ) );
			}
		}

		if ( $result['removed'] ) {
			wp_update_attachment_metadata( $id, $meta );
		}

		// When forcing, make our own sizes the image has again, through
		// rebuild(): each new file only replaces the old one once it checks out,
		// so a failed encode leaves what was there (Sam, 10 November 2026). They
		// used to be deleted first, and a broken AVIF encoder then left nothing.
		if ( $force ) {
			$have = array_values(
				array_filter(
					self::wanted(),
					function ( $name ) use ( $meta ) {
						return ! empty( $meta['sizes'][ $name ] );
					}
				)
			);

			$result['built'] = self::rebuild( $id, $have );
			$meta            = (array) wp_get_attachment_metadata( $id );
		}

		if ( ! self::missing( $id, $meta ) ) {
			return $result;
		}

		$result['built'] = array_merge( $result['built'], self::build( $id, true ) );

		return $result;
	}
	/**
	 * Build the sizes this attachment is missing. Nothing else is touched.
	 *
	 * Each size is made straight from the file rather than by asking WordPress to
	 * rebuild everything, so existing thumbnails keep their names and are not
	 * written over.
	 */
	/** Sizes that failed this request: attachment id => [ size name => why ]. */
	private static $failed = [];

	/** The name every working copy starts with (make_sizes()). */
	const WORK = 'sb-images-work-';

	/** One of make_sizes()' working copies, or a file the editor made from one. */
	public static function is_work_file( $path ) {
		return strpos( basename( (string) $path ), self::WORK ) === 0;
	}

	/**
	 * Working copies a request left behind when it died part way (a timeout, or
	 * running out of memory), deleted once they are an hour old, so one still
	 * in use is never touched. Once a request, from make_sizes() (Sam, 10
	 * November 2026). Returns how many went.
	 */
	public static function clear_stale_work() {
		static $done = false;

		if ( $done ) {
			return 0;
		}

		$done = true;
		$base = trailingslashit( wp_upload_dir( null, false )['basedir'] );
		$gone = 0;

		foreach ( array_merge( (array) glob( $base . self::WORK . '*' ), (array) glob( $base . '[0-9][0-9][0-9][0-9]/[0-9][0-9]/' . self::WORK . '*' ) ) as $file ) {
			if ( is_file( $file ) && (int) filemtime( $file ) < time() - HOUR_IN_SECONDS && @unlink( $file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
				$gone++;
			}
		}

		return $gone;
	}

	/** Why a size failed, for Build's summary and the log (Sam, 10 November 2026). */
	private static function why( $code ) {
		$why = [
			'empty'      => __( 'the image editor wrote empty files', 'sb-tweaks' ),
			'unreadable' => __( 'the image editor wrote files that are not readable images', 'sb-tweaks' ),
			'size'       => __( 'the image editor wrote files at the wrong size', 'sb-tweaks' ),
			'none'       => __( 'the image editor did not make it', 'sb-tweaks' ),
			'copy'       => __( 'the original could not be copied to work from', 'sb-tweaks' ),
			'move'       => __( 'the new file could not be put in place', 'sb-tweaks' ),
		];

		return $why[ $code ] ?? (string) $code;
	}

	private static function fail( $id, $name, $code ) {
		self::$failed[ (int) $id ][ $name ] = self::why( $code );
	}

	/** The sizes that failed for $id this request: name => why. */
	public static function failed( $id ) {
		return self::$failed[ (int) $id ] ?? [];
	}

	/** "3 sizes failed on x.avif: the image editor wrote empty files", or '' when none failed. */
	public static function failure_line( $id ) {
		$failed = self::failed( $id );

		if ( ! $failed ) {
			return '';
		}

		$count = count( $failed );

		return sprintf(
			/* translators: 1: number of sizes, 2: file name, 3: why */
			_n( '%1$s size failed on %2$s: %3$s', '%1$s sizes failed on %2$s: %3$s', $count, 'sb-tweaks' ),
			number_format_i18n( $count ),
			basename( (string) get_attached_file( $id ) ),
			implode( '; ', array_unique( array_values( $failed ) ) )
		);
	}

	/**
	 * Why a made file can't be kept: 'empty', 'unreadable' or 'size', or ''
	 * when it is a readable image at $width x $height.
	 */
	public static function bad_file( $path, $width, $height ) {
		clearstatcache( true, $path );

		if ( ! is_file( $path ) || (int) filesize( $path ) < 1 ) {
			return 'empty';
		}

		// wp_getimagesize() reads AVIF on PHP older than 8.2 too.
		$info = function_exists( 'wp_getimagesize' ) ? wp_getimagesize( $path ) : @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		if ( ! is_array( $info ) || empty( $info[0] ) ) {
			return 'unreadable';
		}

		return ( (int) $info[0] === (int) $width && (int) $info[1] === (int) $height ) ? '' : 'size';
	}

	/**
	 * Make a set of sizes from one image, decoding it once.
	 *
	 * Every size used to get its own wp_get_image_editor() call, which loads and
	 * decodes the original each time: thirteen decodes of a 5 MB photo for one
	 * rebuild. multi_resize() works from the one decoded copy, and on both GD and
	 * Imagick makes each size from the original pixels, not from the last size.
	 *
	 * Never keeps or records a bad file, and never destroys a good one (Sam, 10
	 * November 2026). On simplystyleco.com.au a broken AVIF encoder wrote empty
	 * files for every size, and because the editor names files after the file it
	 * opened and writes over whatever is there, it emptied the image's working
	 * 300x300 and 600x600 too. So the editor works from a copy under a name of
	 * its own (sb-images-work-...), and nothing it writes can land on a file the
	 * image already has. Each file it makes is checked (bad_file(): there, not
	 * empty, and a readable image at the size it says), and only a good one is
	 * moved to the base name the rest of the set uses. A good file already there
	 * stays, unless $replace (a rebuild) asks for it to be replaced, and then
	 * only by a good one. A size that fails is left out and noted with why
	 * (failed(), failure_line()). The copy and anything left under its name are
	 * deleted at the end.
	 *
	 * Returns name => metadata entry for the sizes that were made.
	 */
	private static function make_sizes( $id, $file, array $meta, array $specs, $replace = false ) {
		$made = [];

		if ( ! $specs ) {
			return $made;
		}

		$folder = trailingslashit( dirname( $file ) );
		$base   = self::base_name( $id, $meta );
		$ext    = pathinfo( $file, PATHINFO_EXTENSION );
		self::clear_stale_work();

		$work   = $folder . wp_unique_filename( $folder, self::WORK . strtolower( wp_generate_password( 8, false ) ) . ( $ext !== '' ? '.' . $ext : '' ) );
		$stem   = pathinfo( $work, PATHINFO_FILENAME );

		if ( ! @copy( $file, $work ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			foreach ( array_keys( $specs ) as $name ) {
				self::fail( $id, $name, 'copy' );
			}

			self::clear_work( $folder, $stem );

			return $made;
		}

		$editor  = wp_get_image_editor( $work );
		$results = is_wp_error( $editor ) ? $editor : $editor->multi_resize( $specs );

		if ( is_wp_error( $results ) || ! is_array( $results ) ) {
			foreach ( array_keys( $specs ) as $name ) {
				self::fail( $id, $name, is_wp_error( $results ) ? $results->get_error_message() : 'none' );
			}

			self::clear_work( $folder, $stem );

			return $made;
		}

		$real  = self::dimensions( $id, $meta );
		$moved = []; // File the editor made => the name it went to. Two sizes of the same dimensions share one.

		foreach ( $specs as $name => $spec ) {
			$entry = $results[ $name ] ?? null;

			if ( ! is_array( $entry ) || empty( $entry['file'] ) ) {
				$dims = image_resize_dimensions( (int) $real[0], (int) $real[1], (int) $spec['width'], (int) $spec['height'], ! empty( $spec['crop'] ) );

				// Nothing to make: the original is no bigger than the size. Not a failure.
				if ( ! $dims ) {
					continue;
				}

				// multi_resize() drops the editor's error, but a broken encoder leaves
				// its file behind, often empty (Imagick's AVIF on the hub): say what it wrote.
				$left = $folder . $stem . '-' . (int) $dims[4] . 'x' . (int) $dims[5] . ( $ext !== '' ? '.' . $ext : '' );

				if ( is_wp_error( $entry ) ) {
					self::fail( $id, $name, $entry->get_error_message() );
				} elseif ( is_file( $left ) ) {
					self::fail( $id, $name, self::bad_file( $left, (int) $dims[4], (int) $dims[5] ) ?: 'none' );
				} else {
					self::fail( $id, $name, 'none' );
				}

				continue;
			}

			$width  = (int) $entry['width'];
			$height = (int) $entry['height'];

			if ( ! isset( $moved[ $entry['file'] ] ) ) {
				$wanted = $base . '-' . $width . 'x' . $height . '.' . pathinfo( $entry['file'], PATHINFO_EXTENSION );
				$why    = self::bad_file( $folder . $entry['file'], $width, $height );

				if ( $why !== '' ) {
					self::fail( $id, $name, $why );

					continue;
				}

				$had = is_file( $folder . $wanted );
				$put = $replace || self::bad_file( $folder . $wanted, $width, $height ) !== '';

				if ( $put && ! @rename( $folder . $entry['file'], $folder . $wanted ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
					self::fail( $id, $name, 'move' );

					continue;
				}

				// An optimiser's WebP or AVIF of the file just replaced shows the old one.
				if ( $put && $had ) {
					self::refresh_copies( $folder . $wanted, $width, $height );
				}

				$moved[ $entry['file'] ] = $wanted;
			}

			$made[ $name ] = [
				'file'      => $moved[ $entry['file'] ],
				'width'     => $width,
				'height'    => $height,
				'mime-type' => $entry['mime-type'],
			];
		}

		self::clear_work( $folder, $stem );

		return $made;
	}

	/**
	 * Remake the WebP and AVIF copies an optimiser wrote for a size file that
	 * has just been replaced.
	 *
	 * WPvivid, EWWW and the like write photo-480x320.jpg.webp beside each size,
	 * and the server hands that to any browser that takes WebP. A forced Rebuild
	 * replaced the JPG and left the copy, so most visitors kept seeing the old
	 * picture (found on simplystyleco.com.au, 10 November 2026, where asking for
	 * a .jpg returns image/webp). Each copy that exists is made again from the
	 * new file, checked like any size, then moved over the old one. Deleting it
	 * instead could leave the optimiser sure it was done, or a rewrite rule
	 * pointing at nothing; remaking it keeps the file there either way. When
	 * the new copy can't be made or doesn't check out, the old one stays.
	 */
	private static function refresh_copies( $path, $width, $height ) {
		foreach ( array_slice( self::copies( $path ), 1 ) as $copy ) {
			if ( ! is_file( $copy ) ) {
				continue;
			}

			$type   = substr( $copy, -5 ) === '.avif' ? 'avif' : 'webp';
			$editor = wp_get_image_editor( $path, [ 'mime_type' => 'image/' . $type ] );

			if ( is_wp_error( $editor ) || ! $editor->supports_mime_type( 'image/' . $type ) ) {
				continue;
			}

			$temp  = trailingslashit( dirname( $copy ) ) . self::WORK . strtolower( wp_generate_password( 8, false ) ) . '.' . $type;
			$saved = $editor->save( $temp, 'image/' . $type );
			$made  = is_array( $saved ) && ! empty( $saved['path'] ) ? $saved['path'] : $temp;

			if ( is_wp_error( $saved ) || self::bad_file( $made, $width, $height ) !== '' || ! @rename( $made, $copy ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				if ( is_file( $made ) ) {
					@unlink( $made ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
				}
			}
		}
	}

	/** The working copy, and anything the editor left under its name. */
	private static function clear_work( $folder, $stem ) {
		foreach ( (array) glob( $folder . $stem . '*' ) as $leftover ) {
			if ( is_file( $leftover ) ) {
				@unlink( $leftover ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			}
		}
	}

	public static function build( $id, $all = false, ?array $only = null ) {
		$built = [];

		if ( ! wp_attachment_is_image( $id ) ) {
			return $built;
		}

		$file = get_attached_file( $id );

		if ( ! $file || ! file_exists( $file ) ) {
			return $built;
		}

		$meta    = (array) wp_get_attachment_metadata( $id );
		$wanted  = $all ? self::missing_all( $id, $meta, $only ) : self::missing( $id, $meta );
		$sizes   = self::wanted_for( $id );

		if ( $only !== null ) {
			$wanted = array_values(
				array_filter(
					$wanted,
					function ( $name ) use ( $only ) {
						return in_array( $name, $only, true ) || self::is_attachment_size( $name );
					}
				)
			);
		}

		if ( ! $wanted ) {
			return $built;
		}

		$specs = [];

		foreach ( $wanted as $name ) {
			$spec   = isset( $sizes[ $name ] ) ? $sizes[ $name ] : null;
			$width  = $spec ? (int) $spec['width'] : (int) str_replace( 'image-', '', $name );
			$height = $spec ? (int) $spec['height'] : 9999;

			if ( $width < 1 && $height < 1 ) {
				continue;
			}

			$specs[ $name ] = [ 'width' => $width, 'height' => $height, 'crop' => $spec ? (bool) $spec['crop'] : false ];
		}

		foreach ( self::make_sizes( $id, $file, $meta, $specs ) as $name => $entry ) {
			$meta['sizes'][ $name ] = $entry;
			$built[]                = $name;
		}

		if ( $built ) {
			wp_update_attachment_metadata( $id, $meta );
		}

		return $built;
	}
	/** Remove sizes we own that are no longer wanted. Nothing is built. */
	public static function clean( $id ) {
		$result = [ 'removed' => [], 'files' => 0 ];

		if ( ! wp_attachment_is_image( $id ) ) {
			return $result;
		}

		$file = get_attached_file( $id );
		$meta = (array) wp_get_attachment_metadata( $id );

		if ( ! $file ) {
			return $result;
		}

		$folder = dirname( $file );

		foreach ( self::stale( $id, $meta ) as $name ) {
			$stale_file = ! empty( $meta['sizes'][ $name ]['file'] ) ? $meta['sizes'][ $name ]['file'] : '';

			// The entry goes either way; the file only goes if nothing else uses it.
			unset( $meta['sizes'][ $name ] );
			$result['removed'][] = $name;

			if ( $stale_file && ! self::file_has_other_owner( $id, $meta, $stale_file ) ) {
				$result['files'] += self::delete_file( self::size_path( $folder, $stale_file ) );
			}
		}

		if ( $result['removed'] ) {
			wp_update_attachment_metadata( $id, $meta );
		}

		return $result;
	}

	/**
	 * The files clearing would delete for one attachment and how much space
	 * they take, for the Old thumbnails to clear figure. The scan checks each
	 * file is there anyway, so its size costs one more look at the same file.
	 */
	public static function stale_usage( $id, ?array $meta = null ) {
		$meta  = $meta === null ? (array) wp_get_attachment_metadata( $id ) : $meta;
		$file  = get_attached_file( $id );
		$count = 0;
		$bytes = 0;

		if ( ! $file ) {
			return [ 'files' => 0, 'bytes' => 0 ];
		}

		$folder = dirname( $file );

		foreach ( self::stale( $id, $meta ) as $name ) {
			if ( empty( $meta['sizes'][ $name ]['file'] ) ) {
				continue;
			}

			$path = self::size_path( $folder, $meta['sizes'][ $name ]['file'] );

			if ( $path === '' ) {
				continue;
			}

			foreach ( self::copies( $path ) as $candidate ) {
				if ( file_exists( $candidate ) ) {
					$count++;
					$bytes += (int) filesize( $candidate );
				}
			}
		}

		return [ 'files' => $count, 'bytes' => $bytes ];
	}
	/**
	 * The name the existing thumbnails were built from.
	 *
	 * WordPress names a size after whichever file it was made from, and for a
	 * large upload that is the original rather than the scaled copy it keeps as the
	 * attachment. Taking the name from a size that already exists keeps anything we
	 * add in step with what is already there.
	 */
	public static function base_name( $id, array $meta ) {
		$counts = [];

		foreach ( (array) ( $meta['sizes'] ?? [] ) as $size ) {
			if ( empty( $size['file'] ) ) {
				continue;
			}

			$name = pathinfo( $size['file'], PATHINFO_FILENAME );
			$name = preg_replace( '/-\d+x\d+$/', '', $name );

			if ( $name === '' ) {
				continue;
			}

			$counts[ $name ] = isset( $counts[ $name ] ) ? $counts[ $name ] + 1 : 1;
		}

		// Whichever naming most of the thumbnails already use.
		if ( $counts ) {
			arsort( $counts );

			return (string) array_key_first( $counts );
		}

		// Nothing to copy, so fall back to the original upload if there is one.
		$original = function_exists( 'wp_get_original_image_path' ) ? wp_get_original_image_path( $id ) : '';
		$source   = $original ? $original : get_attached_file( $id );

		return pathinfo( (string) $source, PATHINFO_FILENAME );
	}
	/**
	 * Every size an image should have, as name => width, height and crop.
	 *
	 * Ours plus everything WordPress, the theme and other plugins register, since
	 * a missing thumbnail is a missing thumbnail whoever asked for it.
	 */
	public static function all_wanted() {
		$wanted = self::registered();
		$skip   = array_merge( self::owned(), self::wp_off() );

		// Sizes seen registered on the module's pages count as wanted in every
		// request, so one that a plugin only registers in the admin is never a
		// leftover. Ours and the WordPress sizes switched off are left out: their
		// state is known in every request.
		foreach ( self::seen() as $name => $size ) {
			if ( isset( $wanted[ $name ] ) || in_array( $name, $skip, true ) || ! is_array( $size ) ) {
				continue;
			}

			$wanted[ $name ] = [
				'width'  => (int) ( $size['width'] ?? 0 ),
				'height' => (int) ( $size['height'] ?? 0 ),
				'crop'   => ! empty( $size['crop'] ),
			];
		}

		return array_filter(
			$wanted,
			function ( $size ) {
				return $size['width'] > 0 || $size['height'] > 0;
			}
		);
	}

	/** The sizes remembered by note_registered(). */
	public static function seen() {
		$seen = get_option( self::SEEN_OPTION, [] );

		return is_array( $seen ) ? $seen : [];
	}

	/**
	 * Remember the sizes registered in this request. Run on loads of SB Image
	 * Settings and SB Image Cleaner and by the scan, where plugins that only
	 * register in the admin have done so. The list is replaced, not added to,
	 * so a size whose plugin has gone drops off at the next visit and shows as
	 * a leftover then. A size registered here is also taken off the sizes
	 * chosen for clearing, in case one was chosen from a scan run elsewhere.
	 */
	public static function note_registered() {
		$now = self::registered();

		ksort( $now );

		if ( $now !== self::seen() ) {
			update_option( self::SEEN_OPTION, $now, false );
		}

		$live = array_values( array_intersect( self::adopted(), array_keys( $now ) ) );

		if ( $live ) {
			self::unadopt( $live );
		}
	}

	/**
	 * Every size registered in this request, as name => width, height and crop.
	 * all_wanted() adds the ones remembered from the admin.
	 */
	public static function registered() {
		global $_wp_additional_image_sizes;

		$wanted = [];

		foreach ( get_intermediate_image_sizes() as $name ) {
			if ( isset( $_wp_additional_image_sizes[ $name ] ) ) {
				$wanted[ $name ] = [
					'width'  => (int) $_wp_additional_image_sizes[ $name ]['width'],
					'height' => (int) $_wp_additional_image_sizes[ $name ]['height'],
					'crop'   => (bool) $_wp_additional_image_sizes[ $name ]['crop'],
				];

				continue;
			}

			$wanted[ $name ] = [
				'width'  => (int) get_option( $name . '_size_w' ),
				'height' => (int) get_option( $name . '_size_h' ),
				'crop'   => (bool) get_option( $name . '_crop' ),
			];
		}

		return array_filter(
			$wanted,
			function ( $size ) {
				return $size['width'] > 0 || $size['height'] > 0;
			}
		);
	}

	/**
	 * A size file and the WebP or AVIF copies an optimiser may have written for
	 * it. Most add the extension (photo-480x320.jpg.webp); ShortPixel swaps it
	 * (photo-480x320.webp). Only the added kind was ever counted, so Remove old
	 * sizes left ShortPixel's copies behind: 379 files on jetskihirenoosa.com.au
	 * and 356 on noosariverboathire.com.au. A swapped name is only taken as a
	 * copy when no attachment names it as a file of its own, so an image
	 * uploaded as .webp with the same name is never mistaken for one.
	 */
	public static function copies( $path ) {
		$list = [ $path, $path . '.webp', $path . '.avif' ];
		$ext  = strtolower( pathinfo( (string) $path, PATHINFO_EXTENSION ) );

		if ( $ext === '' || in_array( $ext, [ 'webp', 'avif' ], true ) ) {
			return $list;
		}

		$stem   = substr( $path, 0, -( strlen( $ext ) + 1 ) );
		$owners = self::file_owners();

		foreach ( [ '.webp', '.avif' ] as $swap ) {
			if ( ! isset( $owners['first'][ basename( $stem . $swap ) ] ) ) {
				$list[] = $stem . $swap;
			}
		}

		return $list;
	}

	/**
	 * Everything registered, plus the sizes only the site icon has.
	 *
	 * WordPress registers site_icon-32, -180, -192 and -270 only while a site
	 * icon is being cropped, so on every other request they look like leftovers
	 * from another source. Cleared that way on jetskihirenoosa.com.au, the
	 * favicon fell back to the 150 and 300 pixel thumbnails. For the attachment
	 * that is the site icon they count as wanted: never cleared, never listed
	 * as leftovers, and made again by Build Thumbnails when missing.
	 */
	public static function wanted_for( $id ) {
		$wanted = self::all_wanted();
		$id     = (int) $id;

		if ( $id > 0 && $id === (int) get_option( 'site_icon' ) ) {
			foreach ( self::site_icon_sizes() as $px ) {
				$wanted[ 'site_icon-' . $px ] = [ 'width' => $px, 'height' => $px, 'crop' => true ];
			}
		}

		// Sizes another module makes only for some images (Email Signatures'
		// logos, Sam, 30 October 2026): wanted on those images alone, so never
		// leftovers there, never asked of any other image, and made again by
		// Build Thumbnails when missing. attachment_size_groups() lists them.
		return (array) apply_filters( 'sb_images/wanted_for', $wanted, $id );
	}

	/**
	 * Whether a size name is one of the site icon's (site_icon-32 and so on).
	 * These are never listed as leftovers from another source and never
	 * cleared, on any attachment.
	 */
	public static function is_site_icon_size( $name ) {
		return (bool) preg_match( '/^site_icon-\d+$/', (string) $name );
	}

	/** The site icon's own sizes, as WP_Site_Icon makes them. */
	public static function site_icon_sizes() {
		$sizes = (array) apply_filters( 'site_icon_image_sizes', [ 270, 192, 180, 32 ] );

		return array_values( array_filter( array_map( 'intval', $sizes ) ) );
	}

	/**
	 * Sizes only some images have, by group: any another module adds through
	 * the sb_images/attachment_size_groups filter (key => [ title, note,
	 * sizes => name => width, height, crop ]), then the site icon's. None is
	 * registered, so none is in the ticked list on SB Image Cleaner, and Build
	 * makes them whatever is ticked. Which images have them: wanted_for().
	 */
	public static function attachment_size_groups() {
		$icon = [];

		foreach ( self::site_icon_sizes() as $px ) {
			$icon[ 'site_icon-' . $px ] = [ 'width' => $px, 'height' => $px, 'crop' => true ];
		}

		$groups = array_filter( (array) apply_filters( 'sb_images/attachment_size_groups', [] ), 'is_array' );

		return $groups + [ 'site-icon' => [ 'title' => __( 'Site Icon', 'sb-tweaks' ), 'sizes' => $icon ] ];
	}

	/** Whether a size is one of attachment_size_groups(): made only for some images. */
	public static function is_attachment_size( $name ) {
		if ( self::is_site_icon_size( $name ) ) {
			return true;
		}

		foreach ( (array) apply_filters( 'sb_images/attachment_size_groups', [] ) as $group ) {
			if ( isset( $group['sizes'][ (string) $name ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sizes an attachment is missing, counting everything registered.
	 *
	 * A size is only expected when the original is big enough for it, since
	 * WordPress will not stretch an image to fill a larger size.
	 */
	/**
	 * Whether a size can be made from an original of these dimensions.
	 *
	 * WordPress's own sizing function is the authority, so this asks it rather
	 * than working it out again. Hand rolled rules got the edge cases wrong: a
	 * 600 x 400 original and a 600 x 400 crop looked makeable, but WordPress
	 * makes no file when the result would be the original, so those images sat
	 * in Sizes to build for ever and no rebuild could satisfy them.
	 */
	/**
	 * The original's real dimensions, read from the file rather than taken from
	 * the metadata.
	 *
	 * An optimiser can resize the file and leave the metadata saying what it used
	 * to be. On feelsoma.com eleven images did, one claiming 2560 x 1922 for a
	 * file that is 1920 x 1442: every size between those numbers was reported as
	 * missing and could never be built, because the resize the metadata implies
	 * is impossible. Reading the header costs about a tenth of a millisecond and
	 * it is the only number that can be acted on.
	 */
	public static function dimensions( $id, ?array $meta = null ) {
		static $seen = [];

		$id = (int) $id;

		if ( isset( $seen[ $id ] ) ) {
			return $seen[ $id ];
		}

		$meta = $meta === null ? (array) wp_get_attachment_metadata( $id ) : $meta;
		$size = [ isset( $meta['width'] ) ? (int) $meta['width'] : 0, isset( $meta['height'] ) ? (int) $meta['height'] : 0 ];
		$file = get_attached_file( $id );

		if ( $file && file_exists( $file ) ) {
			$real = @getimagesize( $file );

			if ( $real && (int) $real[0] > 0 ) {
				$size = [ (int) $real[0], (int) $real[1] ];
			}
		}

		$seen[ $id ] = $size;

		return $size;
	}

	public static function can_make( $width, $height, array $size ) {
		$width  = (int) $width;
		$height = (int) $height;

		if ( ! $width || ! $height ) {
			return false;
		}

		return (bool) image_resize_dimensions( $width, $height, (int) $size['width'], (int) $size['height'], ! empty( $size['crop'] ) );
	}

	public static function missing_all( $id, ?array $meta = null, ?array $only = null ) {
		$meta = $meta === null ? (array) wp_get_attachment_metadata( $id ) : $meta;

		if ( empty( $meta['width'] ) ) {
			return [];
		}

		// Nothing can be made without the original.
		if ( self::original_missing( $id ) ) {
			return [];
		}

		$have    = self::present( $id, $meta );
		$real    = self::dimensions( $id, $meta );
		$missing = [];

		foreach ( self::wanted_for( $id ) as $name => $size ) {
			// The site icon's sizes, and the others only some images have, are
			// never in the ticked list (they are not registered), so they are
			// built whatever is ticked. attachment_size_groups().
			if ( in_array( $name, $have, true ) || ( $only !== null && ! in_array( $name, $only, true ) && ! self::is_attachment_size( $name ) ) ) {
				continue;
			}

			if ( self::can_make( $real[0], $real[1], $size ) ) {
				$missing[] = $name;
			}
		}

		return $missing;
	}
	/**
	 * Make the named sizes again, whether or not they already exist.
	 *
	 * Used by the force rebuild, where the point is to replace what is there. Any
	 * size too large for the original is skipped rather than upscaled.
	 */
	public static function rebuild( $id, array $names ) {
		$done = [];

		if ( ! $names || ! wp_attachment_is_image( $id ) ) {
			return $done;
		}

		$file = get_attached_file( $id );

		if ( ! $file || ! file_exists( $file ) ) {
			return $done;
		}

		$meta  = (array) wp_get_attachment_metadata( $id );
		$sizes = self::wanted_for( $id );
		$real  = self::dimensions( $id, $meta );
		$specs = [];

		foreach ( $names as $name ) {
			if ( empty( $sizes[ $name ] ) ) {
				continue;
			}

			$spec = $sizes[ $name ];

			// The same rule a plain build uses, so forcing is never a smaller job.
			if ( ! self::can_make( $real[0], $real[1], $spec ) ) {
				continue;
			}

			$specs[ $name ] = [ 'width' => (int) $spec['width'], 'height' => (int) $spec['height'], 'crop' => (bool) $spec['crop'] ];
		}

		// Replacing is the point here, but only with a file that checks out.
		$old = [];

		foreach ( self::make_sizes( $id, $file, $meta, $specs, true ) as $name => $entry ) {
			$was = (string) ( $meta['sizes'][ $name ]['file'] ?? '' );

			if ( $was !== '' && $was !== $entry['file'] ) {
				$old[] = $was;
			}

			$meta['sizes'][ $name ] = $entry;
			$done[]                 = $name;
		}

		if ( $done ) {
			wp_update_attachment_metadata( $id, $meta );

			// A size that came out under another name leaves its old file behind:
			// cleared, unless something still uses it.
			foreach ( array_unique( $old ) as $was ) {
				if ( ! self::file_has_other_owner( $id, $meta, $was ) ) {
					self::delete_file( self::size_path( dirname( $file ), $was ) );
				}
			}
		}

		return $done;
	}
}