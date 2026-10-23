<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'acf-published-only',
	'title'       => 'Hide Unpublished ACF Posts',
	'description' => 'Leaves drafts, scheduled, private and binned posts out of ACF relationship and post object fields on the front end, so related posts can be picked ahead of time and only appear once they are published. The edit screens still show them.',
	'type'        => 'tweak',
	'section'     => 'extras',
	'default'     => false,
	'requires'    => [ 'acf' ],

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-bricks-published-only.php';
		SB_Bricks_Published_Only::boot();
	},
];