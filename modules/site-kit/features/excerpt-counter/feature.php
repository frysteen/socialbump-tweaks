<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'excerpt-counter',
	'title'       => __( 'Excerpt Character Counter', 'sb-tweaks' ),
	'description' => __( 'Counts characters as you write an excerpt, in both the block editor and the classic excerpt box, and warns when it runs long.', 'sb-tweaks' ),
	'section'     => 'content',
	'default'     => true,

	'settings'    => [
		'max' => [
			'type'    => 'number',
			'label'   => __( 'Ideal length', 'sb-tweaks' ),
			'default' => 160,
			'min'     => 20,
			'max'     => 500,
		],
	],

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-site-kit-excerpt-counter.php';
		SB_Site_Kit_Excerpt_Counter::boot( $module );
	},
];