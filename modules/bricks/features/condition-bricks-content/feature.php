<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'condition-bricks-content',
	'title'       => 'Bricks Content',
	'description' => 'Show or hide an element depending on whether the post was built with Bricks. Handy for falling back to normal WordPress content.',
	'type'        => 'condition',
	'section'     => 'conditions',
	'default'     => false,

	'boot' => function ( $module ) {
		require_once SB_BRICKS_PATH . 'includes/class-sb-bricks-conditions.php';
		SB_Bricks_Conditions::register( include $module['path'] . 'condition.php' );
	},
];
