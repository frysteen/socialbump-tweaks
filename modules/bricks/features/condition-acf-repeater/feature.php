<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'condition-acf-repeater',
	'title'       => __( 'ACF Repeater', 'sb-tweaks' ),
	'description' => __( 'Show or hide an element depending on whether an ACF repeater has rows. Pick the repeater from a list.', 'sb-tweaks' ),
	'type'        => 'condition',
	'section'     => 'conditions',
	'default'     => false,
	'requires'    => [ 'acf' ],

	'boot' => function ( $module ) {
		require_once SB_BRICKS_PATH . 'includes/class-sb-bricks-conditions.php';
		SB_Bricks_Conditions::register( include $module['path'] . 'condition.php' );
	},
];
