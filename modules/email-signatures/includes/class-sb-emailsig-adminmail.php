<?php
/**
 * Admin Notifications (Sam, 31 October 2026): emails to the site's admins
 * about things that need them, set up on Settings, Admin Notifications.
 * Each one in NOTICES gets its own section there (fields()): a card whose
 * header row has its name and an Enabled switch, then, when enabled, its
 * own Send To (blank: the tab's Default Send To, else the site admin email),
 * Subject and Message with merge tags. A new one is a new entry in
 * NOTICES and a send() call where it happens.
 *
 * Kept as an ACF group per notice (sb_emailsig_notice_{key}), read by the
 * group's key so a never-saved one still gets its defaults (by name, a value
 * never saved has no field to find).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_AdminMail {

	/**
	 * The notices: key => label, the merge tags it fills in, and its default
	 * subject and message. A line of a message whose merge tag comes out
	 * empty ("Mobile: ") is left out.
	 */
	const NOTICES = array(
		'request' => array(
			'label'   => 'New Team Member Submitted',
			'tags'    => array( 'name', 'company', 'qualifications', 'job_title', 'email', 'mobile', 'photo', 'edit_link' ),
			'subject' => 'New team member submitted: {name}',
			'message' => "{name} was submitted as a new team member for {company}.\n\nQualifications: {qualifications}\nJob title: {job_title}\nEmail: {email}\nMobile: {mobile}\nPhoto: {photo}\n\nThey're under Inactive until you make them Active:\n{edit_link}",
		),
	);

	/** The Admin Notifications tab's fields: Default Send To, then a section per notice. */
	public static function fields() {
		$fields = array(
			array(
				'key'   => 'field_sb_emailsig_tab_notifications',
				'label' => 'Admin Notifications',
				'type'  => 'tab',
			),
			array(
				'key'          => 'field_sb_emailsig_notify_email',
				'label'        => 'Default Send To',
				'name'         => 'sb_emailsig_notify_email',
				'type'         => 'text',
				'placeholder'  => get_option( 'admin_email' ),
				'instructions' => 'Blank sends to the site admin email. Separate more than one with commas.',
			),
		);

		foreach ( self::NOTICES as $key => $n ) {
			$on       = 'field_sb_emailsig_notice_' . $key . '_on';
			$when_on  = array( array( array( 'field' => $on, 'operator' => '==', 'value' => '1' ) ) );
			$fields[] = array(
				'key'        => 'field_sb_emailsig_notice_' . $key,
				'label'      => $n['label'],
				'name'       => 'sb_emailsig_notice_' . $key,
				'type'       => 'group',
				'layout'     => 'block',
				'wrapper'    => array( 'class' => 'sb-emailsig-card-field sb-emailsig-notice' ),
				'sub_fields' => array(
					array(
						'key'           => $on,
						'label'         => 'Send',
						'name'          => 'on',
						'type'          => 'true_false',
						'ui'            => 1,
						'ui_on_text'    => 'Enabled',
						'ui_off_text'   => 'Disabled',
						'default_value' => 1,
						// In the section's header row, on the right (admin.css).
						'wrapper'       => array( 'class' => 'sb-emailsig-notice__switch' ),
					),
					array(
						'key'               => 'field_sb_emailsig_notice_' . $key . '_to',
						'label'             => 'Send To',
						'name'              => 'to',
						'type'              => 'text',
						'instructions'      => 'Blank uses the Default Send To.',
						'conditional_logic' => $when_on,
					),
					array(
						'key'               => 'field_sb_emailsig_notice_' . $key . '_subject',
						'label'             => 'Subject',
						'name'              => 'subject',
						'type'              => 'text',
						'default_value'     => $n['subject'],
						'conditional_logic' => $when_on,
					),
					array(
						'key'               => 'field_sb_emailsig_notice_' . $key . '_message',
						'label'             => 'Message',
						'name'              => 'message',
						'type'              => 'textarea',
						'rows'              => 10,
						'new_lines'         => '',
						'default_value'     => $n['message'],
						'instructions'      => 'Merge tags: {' . implode( '}, {', $n['tags'] ) . '}',
						'conditional_logic' => $when_on,
						'wrapper'           => array( 'class' => 'sb-emailsig-card-field__last' ),
					),
				),
			);
		}

		return $fields;
	}

	/** A notice's settings: on, to (addresses), subject and message. */
	public static function settings( $key ) {
		$group = get_field( 'field_sb_emailsig_notice_' . $key, 'option' );
		$group = is_array( $group ) ? $group : array();
		$n     = self::NOTICES[ $key ];

		$split = function ( $list ) {
			return array_values( array_filter( array_map( 'trim', explode( ',', (string) $list ) ), 'is_email' ) );
		};

		$to = $split( $group['to'] ?? '' );
		if ( ! $to ) {
			$to = $split( get_field( 'field_sb_emailsig_notify_email', 'option' ) );
		}
		if ( ! $to ) {
			$to = array( (string) get_option( 'admin_email' ) );
		}

		$subject = trim( (string) ( $group['subject'] ?? '' ) );
		$message = trim( (string) ( $group['message'] ?? '' ) );

		return array(
			'on'      => ! array_key_exists( 'on', $group ) || ! empty( $group['on'] ),
			'to'      => $to,
			'subject' => $subject !== '' ? $subject : $n['subject'],
			'message' => $message !== '' ? $message : $n['message'],
		);
	}

	/** Send notice $key with merge tags $tags (name => value), when it's switched on. */
	public static function send( $key, array $tags ) {
		if ( ! isset( self::NOTICES[ $key ] ) ) {
			return false;
		}

		$s = self::settings( $key );

		if ( ! $s['on'] ) {
			return false;
		}

		$fill = function ( $text ) use ( $tags ) {
			foreach ( $tags as $tag => $value ) {
				$text = str_replace( '{' . $tag . '}', (string) $value, $text );
			}

			return $text;
		};

		// A line whose merge tags all come out empty ("Mobile: ") goes.
		$lines = array();
		foreach ( explode( "\n", str_replace( "\r", '', $s['message'] ) ) as $line ) {
			if ( preg_match_all( '/\{([a-z_]+)\}/', $line, $m ) ) {
				$known = array_intersect( $m[1], array_keys( $tags ) );
				$empty = array_filter(
					$known,
					function ( $tag ) use ( $tags ) {
						return trim( (string) $tags[ $tag ] ) === '';
					}
				);
				if ( $known && count( $empty ) === count( $known ) ) {
					continue;
				}
			}
			$lines[] = $fill( $line );
		}

		$message = implode( "\n", $lines );
		$subject = wp_strip_all_tags( $fill( $s['subject'] ) );

		return wp_mail( $s['to'], $subject, $message );
	}
}
