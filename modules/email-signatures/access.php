<?php
/**
 * Email Signatures: the roles it gives SB Tweaks core (SB_Tweaks_Access).
 *
 * Signatures Manager (Sam, 5 November 2026): everything an admin can do in Email
 * Signatures except change a company's staff post type; Media (use any file,
 * edit or delete only their own); the staff post type a company is connected
 * to, every right but delete. Nothing else in wp-admin.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'sb_signatures_manager' => array(
		'label'             => 'Signatures Manager',
		'module'            => 'email_signatures',
		'home'              => 'sb-email-signatures',
		'pages'             => array( 'sb-email-signatures' ),
		'actions'           => array( 'sb_emailsig' ),
		'admin_caps'        => array( 'manage_options' ),
		'types'             => array( 'sb_emailsig', 'sb_emailsig_company', 'sb_emailsig_tpl' ),
		'edit_only'         => function () {
			return class_exists( 'SB_EmailSig_Staff' ) ? array_filter( (array) SB_EmailSig_Staff::types(), 'post_type_exists' ) : array();
		},
		'media'             => 'own',
		'admin_only_fields' => array( 'field_sb_emailsig_staff_type' ),
	),
);