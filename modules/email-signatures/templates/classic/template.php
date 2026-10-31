<?php
/**
 * Template Name: Classic
 * Description: Starts with a round photo, then name, qualifications, job title and company, a dividing line, then the logo, Info List and social icons. A line across the bottom, then any buttons, footer text and banners. Arrange it all on Edit styles, Layout.
 *
 * Drawn by the layout editor's renderer (SB_EmailSig_Layout::html()), from
 * the layout kept with the template's styles; this design's starting layout
 * and style defaults are in styles.php. Before the layout editor (29 October
 * 2026) this file drew Classic itself; the layout's default reproduces it.
 *
 * Available: $d (SB_EmailSig_Data), $tpl (this template) and $s (its
 * resolved styles, SB_EmailSig_Styles::resolve(), with $s['layout']).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo SB_EmailSig_Layout::html( $d, $s, $tpl ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped as it is built.
