<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'refresh-on-save',
	'title'       => __( 'Force Gutenberg Page Refresh on Save', 'sb-tweaks' ),
	'description' => __( 'Gutenberg saves in the background and leaves you on the same page, so anything the server changed on save is not in front of you until you reload. This refreshes the page once the save has finished. The classic editor already does this.', 'sb-tweaks' ),
	'section'     => 'content',
	'default'     => false,

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-site-kit-refresh-on-save.php';
		SB_Site_Kit_Refresh_On_Save::boot( $module );
	},
];
