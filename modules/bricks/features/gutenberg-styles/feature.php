<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'gutenberg-styles',
	'title'       => __( 'Gutenberg Block Styles', 'sb-tweaks' ),
	'description' => __( 'Bricks strips the block editor styles from every page it renders, so Gutenberg content shown through a Bricks template loses its gallery columns, image cropping and gaps. This puts back only the styles the page content actually uses, and switches on the gallery gap setting in the editor.', 'sb-tweaks' ),
	'type'        => 'tweak',
	'section'     => 'extras',
	'default'     => true,

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-bricks-gutenberg-styles.php';
		SB_Bricks_Gutenberg_Styles::boot();
	},
];
