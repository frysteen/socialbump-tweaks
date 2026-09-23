<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bricks Content: whether the post was built with Bricks.
 *
 * Reads as Bricks Content / Is / Built with Bricks, matching how the built in
 * conditions are worded.
 *
 * Conditions saved before this had no value and used has_content or
 * has_no_content as the comparison. Those are still understood, so existing
 * setups keep working.
 */
return [
	'key'     => 'sb_bricks_bricks_content',
	'was'     => [ 'socialbump_bricks_content' ],
	'label'   => 'Bricks Content',
	'compare' => [
		'==' => 'Is',
		'!=' => 'Is not',
	],
	'value'   => [
		'placeholder' => 'Select',
		'options'     => [
			'1' => 'Built with Bricks',
			'0' => 'Not built with Bricks',
		],
	],
	'check'   => function ( $compare, $value, $post_id ) {
		$meta_key = defined( 'BRICKS_DB_PAGE_CONTENT' ) ? BRICKS_DB_PAGE_CONTENT : '_bricks_page_content_2';
		$content  = get_post_meta( $post_id, $meta_key, true );
		$has      = is_array( $content ) ? ! empty( $content ) : trim( (string) $content ) !== '';

		// The older wording, kept so saved conditions still work.
		if ( $compare === 'has_content' ) {
			return $has;
		}

		if ( $compare === 'has_no_content' ) {
			return ! $has;
		}

		$wants = (string) $value === '1';

		return $compare === '!=' ? $has !== $wants : $has === $wants;
	},
];