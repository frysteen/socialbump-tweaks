<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'condition-post-type',
	'title'       => 'Post Type',
	'description' => 'Show or hide an element depending on the post type, so one shared template can handle Pages, Posts and custom post types.',
	'type'        => 'condition',
	'section'     => 'conditions',
	'default'     => false,

	'boot' => function ( $module ) {
		require_once SB_BRICKS_PATH . 'includes/class-sb-bricks-conditions.php';
		SB_Bricks_Conditions::register( include $module['path'] . 'condition.php' );
	},
];
