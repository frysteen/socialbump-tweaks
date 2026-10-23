<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'gallery-loop',
	'title'       => 'ACF Gallery Loop',
	'description' => 'Adds each ACF gallery field to the query loop Type list, so you can loop over its images and build the markup yourself. Works with any return format, and comes with ordering options.',
	'type'        => 'tweak',
	'section'     => 'extras',
	'default'     => false,
	'requires'    => [ 'acf' ],

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-bricks-gallery-loop.php';
		SB_Bricks_Gallery_Loop::boot();
	},
];