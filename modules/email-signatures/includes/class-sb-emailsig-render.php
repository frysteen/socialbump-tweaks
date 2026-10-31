<?php
/**
 * Renders a signature with the site's template, or a named one (the
 * Templates page previews each), in that template's styles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Render {

	/** A person's signature. $template is a template id, or '' for their main one. */
	public static function html( $post_id, $template = '' ) {
		$template = $template !== '' ? $template : SB_EmailSig_Templates::for_person( $post_id );

		return self::html_from_data( SB_EmailSig_Data::get( $post_id, $template ), $template );
	}

	/**
	 * A signature from prepared data (SB_EmailSig_Data::get(), ::sample() or
	 * SB_EmailSig_Templates::preview_data()). $values are unsaved style
	 * values, for the live preview; null uses the saved ones.
	 */
	public static function html_from_data( array $d, $template = '', $values = null ) {
		// In the data's company (SB_EmailSig_Data::get()): its brand colours and icons.
		if ( ! empty( $d['company_id'] ) && class_exists( 'SB_EmailSig_Company' ) ) {
			return SB_EmailSig_Company::with(
				(int) $d['company_id'],
				function () use ( $d, $template, $values ) {
					unset( $d['company_id'] );

					return self::html_from_data( $d, $template, $values );
				}
			);
		}

		$tpl = SB_EmailSig_Templates::get( $template !== '' ? $template : SB_EmailSig_Templates::current() );

		if ( ! $tpl || ! file_exists( $tpl['file'] ) ) {
			return '';
		}

		// The template draws with $d, $s (its resolved styles) and $tpl.
		$s = SB_EmailSig_Styles::resolve( $tpl, $values );

		// The person's photo, made for this template's photo settings (unsaved
		// ones kept apart in the Edit styles preview).
		if ( empty( $d['photo'] ) ) {
			$d['photo'] = ! empty( $d['id'] ) ? SB_EmailSig_Photo::image( (int) $d['id'], $tpl, $s, $values !== null ) : null;

			// Outside previews the photo goes by its fixed address, so a new
			// photo or crop shows in signatures already pasted into Mail.
			if ( $d['photo'] && $values === null && empty( $d['preview'] ) ) {
				$d['photo']['url'] = SB_EmailSig_Photo::fixed_url( (int) $d['id'], $tpl['id'] );
			}
		}

		ob_start();
		include $tpl['file'];
		$html = (string) ob_get_clean();

		// Collapse whitespace and drop it around tags, so mail apps don't add
		// stray gaps. Templates must not rely on spaces between inline tags.
		$html = preg_replace( '/\s+/', ' ', $html );
		$html = preg_replace( '/\s*(<[^>]+>)\s*/', '$1', $html );

		return trim( $html );
	}
}
