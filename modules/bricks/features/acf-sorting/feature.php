<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'acf-sorting',
	'title'       => __( 'ACF Loop Sorting', 'sb-tweaks' ),
	'description' => __( 'Adds an Order setting to ACF query loops, so rows can be shown reversed, sorted or at random. The order saved in ACF never changes.', 'sb-tweaks' ),
	'type'        => 'tweak',
	'section'     => 'extras',
	'default'     => false,
	'requires'    => [ 'acf' ],

	'settings'    => [
		'repeater'     => [
			'type'    => 'switch',
			'label'   => __( 'Repeater loops', 'sb-tweaks' ),
			'default' => 1,
		],
		'relationship' => [
			'type'    => 'switch',
			'label'   => __( 'Relationship loops', 'sb-tweaks' ),
			'default' => 1,
		],
	],

	'boot' => function ( $module ) {
		$settings = sb_tweaks_module( 'bricks' );

		if ( $settings->setting( 'acf-sorting', 'repeater' ) ) {
			require_once $module['path'] . 'class-sb-bricks-repeater-ordering.php';
			SB_Bricks_Repeater_Ordering::boot();
		}

		if ( $settings->setting( 'acf-sorting', 'relationship' ) ) {
			require_once $module['path'] . 'class-sb-bricks-relationship-ordering.php';
			SB_Bricks_Relationship_Ordering::boot();
		}
	},
];