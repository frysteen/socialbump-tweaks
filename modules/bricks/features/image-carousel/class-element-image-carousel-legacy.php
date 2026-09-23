<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The carousel under the name it used to have.
 *
 * Bricks saves an element by its name, so every carousel placed before the
 * rename says sb-image-carousel. An unregistered name renders nothing at all,
 * so the old name stays registered until every site has been converted.
 *
 * deprecated = true is Bricks' own way of keeping an element working while
 * hiding it from the panel, so nobody can place a new one under the old name.
 */
class SB_Bricks_Element_Image_Carousel_Legacy extends SB_Bricks_Element_Image_Carousel {
	public $name       = 'sb-image-carousel';
	public $deprecated = true;
}