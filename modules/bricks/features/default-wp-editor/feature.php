<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'default-wp-editor',
	'title'       => __( 'Default To WP Editor', 'sb-tweaks' ),
	'description' => __( 'Opens the WordPress editor instead of the Bricks tab on posts with no Bricks content of their own. The Bricks tab is still one click away.', 'sb-tweaks' ),
	'type'        => 'tweak',
	'section'     => 'extras',
	'default'     => false,

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-bricks-default-wp-editor.php';
		SB_Bricks_Default_WP_Editor::boot();
	},
];