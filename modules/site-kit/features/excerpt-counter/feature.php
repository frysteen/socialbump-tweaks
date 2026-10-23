<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'excerpt-counter',
	'title'       => 'Excerpt Character Counter',
	'description' => 'Counts characters as you write an excerpt, in both the block editor and the classic excerpt box, and warns when it runs long.',
	'section'     => 'content',
	'default'     => true,

	'settings'    => [
		'max' => [
			'type'    => 'number',
			'label'   => 'Ideal length',
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