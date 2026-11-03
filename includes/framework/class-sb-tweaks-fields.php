<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every field type a feature card can carry, rendered and cleaned in one place.
 *
 * Both standalone plugins carried their own copy of this, one field type apart
 * and drifting. Adding a field type is now one edit here.
 *
 * Types: checkbox, switch, color, number (min, max, step), select (options),
 * multicheck (options, invert, select_all: true for buttons above, 'text' for
 * text links under the boxes), text. Any field can carry a
 * description, and hide_when names a checkbox key in the same feature that
 * hides this field while ticked. options may be an array or a callable.
 */
class SB_Tweaks_Fields {

	/** A field's options, which may be given as an array or a callable. */
	public static function options( $field ) {
		$options = isset( $field['options'] ) ? $field['options'] : [];

		return is_callable( $options ) ? (array) call_user_func( $options ) : (array) $options;
	}

	/**
	 * Render one card setting field. $value is the saved value or default, as
	 * SB_Tweaks_Features::setting() returns it.
	 */
	public static function render( $module_id, $feature_id, $key, $field, $value ) {
		$q     = chr( 34 );
		$type  = isset( $field['type'] ) ? $field['type'] : 'text';
		$label = isset( $field['label'] ) ? $field['label'] : $key;
		$name  = 'sb_tweaks_settings[' . $feature_id . '][' . $key . ']';

		$field_id = 'sb-tweaks-' . sanitize_key( $module_id ) . '-' . sanitize_key( $feature_id ) . '-' . sanitize_key( $key );

		// A field can be hidden while another checkbox in the same feature is ticked.
		$hide = ! empty( $field['hide_when'] ) ? 'sb-tweaks-' . sanitize_key( $module_id ) . '-' . sanitize_key( $feature_id ) . '-' . sanitize_key( $field['hide_when'] ) : '';

		echo '<div class=' . $q . 'sb-tweaks-field sb-tweaks-field--' . esc_attr( $type ) . $q . ( $hide !== '' ? ' data-sb-tweaks-hide-when=' . $q . esc_attr( $hide ) . $q : '' ) . '>';

		if ( $type === 'checkbox' ) {
			echo '<label for=' . $q . esc_attr( $field_id ) . $q . '><input type=' . $q . 'checkbox' . $q . ' id=' . $q . esc_attr( $field_id ) . $q . ' name=' . $q . esc_attr( $name ) . $q . ' value=' . $q . '1' . $q . ' ' . checked( ! empty( $value ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		} else {
			if ( $type !== 'switch' ) {
				echo '<label class=' . $q . 'sb-tweaks-field__label' . $q . ' for=' . $q . esc_attr( $field_id ) . $q . '>' . esc_html( $label ) . '</label>';
			}

			switch ( $type ) {
				case 'color':
					// The one colour field (colour()): hex, rgb()/hsl() or var(--name), with the Theme colours menu.
					echo self::colour( // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
						[
							'name'        => $name,
							'id'          => $field_id,
							'value'       => (string) $value,
							'label'       => $label,
							'placeholder' => isset( $field['placeholder'] ) ? $field['placeholder'] : '',
						]
					);
					break;

				case 'switch':
					echo '<label class=' . $q . 'sb-tweaks-switch sb-tweaks-switch--inline' . $q . '><input type=' . $q . 'checkbox' . $q . ' id=' . $q . esc_attr( $field_id ) . $q . ' name=' . $q . esc_attr( $name ) . $q . ' value=' . $q . '1' . $q . ' ' . checked( ! empty( $value ), true, false ) . '><span class=' . $q . 'sb-tweaks-switch__track' . $q . '><span class=' . $q . 'sb-tweaks-switch__dot' . $q . '></span></span><span class=' . $q . 'sb-tweaks-switch__label' . $q . '>' . esc_html( $label ) . '</span></label>';
					break;

				case 'multicheck':
					$chosen = array_map( 'strval', (array) $value );
					$invert = ! empty( $field['invert'] );

					// An inverted list stores what is NOT ticked, so anything added
					// to the site later arrives ticked without anyone editing this
					// page. A long list is tedious to clear by hand, so it can carry
					// its own all and none. Both are buttons rather than submits,
					// marked always on so the unsaved changes reminder never
					// mistakes one for the save button.
					// 'select_all' => true: joined buttons above the boxes; 'text':
					// small text links under them (Sam, 3 November 2026).
					$tools_text = 'text' === ( $field['select_all'] ?? '' );

					if ( ! empty( $field['select_all'] ) && ! $tools_text ) {
						echo self::check_tools(); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup.
					}

					echo '<span class=' . $q . 'sb-tweaks-checklist' . $q . '>';

					foreach ( self::options( $field ) as $option => $option_label ) {
						$listed = in_array( (string) $option, $chosen, true );

						echo '<label><input type=' . $q . 'checkbox' . $q . ' name=' . $q . esc_attr( $name ) . '[]' . $q . ' value=' . $q . esc_attr( $option ) . $q . ' ' . checked( $invert ? ! $listed : $listed, true, false ) . '> ' . esc_html( $option_label ) . '</label>';
					}

					echo '</span>';

					if ( $tools_text ) {
						echo self::check_tools( true ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup.
					}
					break;

				case 'select':
					echo '<select id=' . $q . esc_attr( $field_id ) . $q . ' name=' . $q . esc_attr( $name ) . $q . '>';

					foreach ( self::options( $field ) as $option => $option_label ) {
						echo '<option value=' . $q . esc_attr( $option ) . $q . ' ' . selected( (string) $value, (string) $option, false ) . '>' . esc_html( $option_label ) . '</option>';
					}

					echo '</select>';
					break;

				case 'number':
					echo '<input type=' . $q . 'number' . $q . ' class=' . $q . 'small-text' . $q . ' id=' . $q . esc_attr( $field_id ) . $q . ' name=' . $q . esc_attr( $name ) . $q . ' value=' . $q . esc_attr( (string) $value ) . $q
						. ( isset( $field['min'] ) ? ' min=' . $q . esc_attr( $field['min'] ) . $q : '' )
						. ( isset( $field['max'] ) ? ' max=' . $q . esc_attr( $field['max'] ) . $q : '' )
						. ( isset( $field['step'] ) ? ' step=' . $q . esc_attr( $field['step'] ) . $q : '' )
						. '>';
					break;

				default:
					echo '<input type=' . $q . 'text' . $q . ' class=' . $q . 'regular-text' . $q . ' id=' . $q . esc_attr( $field_id ) . $q . ' name=' . $q . esc_attr( $name ) . $q . ' value=' . $q . esc_attr( (string) $value ) . $q . '>';
			}
		}

		if ( ! empty( $field['description'] ) ) {
			echo '<p class=' . $q . 'sb-tweaks-field__desc' . $q . '>' . esc_html( $field['description'] ) . '</p>';
		}

		echo '</div>';
	}

	/** Clean a submitted card setting so only valid values are ever saved. */
	public static function sanitize( $field, $value ) {
		$type    = isset( $field['type'] ) ? $field['type'] : 'text';
		$default = isset( $field['default'] ) ? $field['default'] : '';

		switch ( $type ) {
			case 'color':
				$value = is_string( $value ) ? trim( $value ) : '';

				if ( $value === '' ) {
					return '';
				}

				$clean = self::sanitize_css_colour( $value );

				return $clean !== '' ? $clean : $default;

			case 'switch':
			case 'checkbox':
				return empty( $value ) ? 0 : 1;

			case 'number':
				if ( ! is_numeric( $value ) ) {
					return $default;
				}

				$number = $value + 0;

				if ( isset( $field['min'] ) ) {
					$number = max( $field['min'], $number );
				}

				if ( isset( $field['max'] ) ) {
					$number = min( $field['max'], $number );
				}

				return $number;

			case 'multicheck':
				$allowed = array_keys( self::options( $field ) );
				$ticked  = array_values( array_intersect( array_map( 'sanitize_key', (array) $value ), $allowed ) );

				// An inverted list keeps the ones left unticked, so a post type
				// added to the site next month is offered without anyone going
				// looking for it. Storing what IS ticked would leave it quietly
				// missing.
				if ( ! empty( $field['invert'] ) ) {
					return array_values( array_diff( $allowed, $ticked ) );
				}

				return $ticked;

			case 'select':
				$value   = is_scalar( $value ) ? (string) $value : '';
				$options = self::options( $field );

				return isset( $options[ $value ] ) ? $value : $default;

			default:
				return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : $default;
		}
	}

	/**
	 * Select all | Select none above a checklist (Sam, 3 November 2026): one
	 * markup for PHP and scripts, as SBTweaksFields.checkTools() in
	 * framework.js draws the same. Buttons rather than submits, marked always
	 * on so the unsaved changes reminder never takes one for the save button.
	 * framework.js's [data-sb-tweaks-check] handler does the ticking, and
	 * ui.css draws them as a joined pair, on a page or in a popup; $text
	 * draws them as small text links instead (.sb-toggles--text), where
	 * buttons would be too heavy. A checklist field asks for that with
	 * 'select_all' => 'text'.
	 */
	public static function check_tools( $text = false ) {
		$q = chr( 34 );

		return '<span class=' . $q . 'sb-tweaks-checklist__tools sb-toggles' . ( $text ? ' sb-toggles--text' : '' ) . $q . '>'
			. '<button type=' . $q . 'button' . $q . ' class=' . $q . 'button-link sb-toggle' . $q . ' data-sb-always-on data-sb-tweaks-check=' . $q . 'all' . $q . '>' . esc_html__( 'Select all', 'sb-tweaks' ) . '</button>'
			. '<span aria-hidden=' . $q . 'true' . $q . '>|</span>'
			. '<button type=' . $q . 'button' . $q . ' class=' . $q . 'button-link sb-toggle' . $q . ' data-sb-always-on data-sb-tweaks-check=' . $q . 'none' . $q . '>' . esc_html__( 'Select none', 'sb-tweaks' ) . '</button>'
			. '</span>';
	}

	/**
	 * The colour field: one component for every colour picker in SocialBUMP
	 * Tweaks (Sam, 3 November 2026). A swatch, a box for the value and the
	 * Theme colours button, which assets/js/colour.js attaches to by itself
	 * (every [data-sb-colour]) with the site's colours, left on the page here
	 * once. A page that needs more (its own menu, what a blank one shows, a
	 * stricter lookup) attaches with options in its own script: see colour.js.
	 * The page loads the sb-tweaks-colour script and stylesheet.
	 *
	 * $a: name, id, value, label (the swatch's), placeholder, class (more
	 * classes on the wrapper), attrs (more wrapper attributes, such as
	 * data-fallback), input_attrs (more box attributes, such as data-default),
	 * swatch (the hex it shows before the script runs).
	 */
	public static function colour( array $a ) {
		$q      = chr( 34 );
		$value  = isset( $a['value'] ) ? (string) $a['value'] : '';
		$hex6   = '/^#[0-9a-f]{6}$/i';
		$swatch = ( ! empty( $a['swatch'] ) && preg_match( $hex6, (string) $a['swatch'] ) ) ? (string) $a['swatch'] : ( preg_match( $hex6, $value ) ? $value : '#ffffff' );
		$wrap   = '';
		$box    = '';

		foreach ( (array) ( $a['attrs'] ?? [] ) as $key => $val ) {
			$wrap .= ' ' . esc_attr( $key ) . '=' . $q . esc_attr( (string) $val ) . $q;
		}

		foreach ( (array) ( $a['input_attrs'] ?? [] ) as $key => $val ) {
			$box .= ' ' . esc_attr( $key ) . '=' . $q . esc_attr( (string) $val ) . $q;
		}

		return '<span class=' . $q . 'sb-tweaks-colour' . ( ! empty( $a['class'] ) ? ' ' . esc_attr( $a['class'] ) : '' ) . $q . ' data-sb-colour' . $wrap . '>'
			. '<input type=' . $q . 'color' . $q . ' class=' . $q . 'sb-tweaks-colour__swatch' . $q . ' value=' . $q . esc_attr( $swatch ) . $q . ' aria-label=' . $q . esc_attr( (string) ( $a['label'] ?? '' ) ) . $q . '>'
			. '<input type=' . $q . 'text' . $q . ' class=' . $q . 'sb-tweaks-colour__value code' . $q
			. ( ! empty( $a['id'] ) ? ' id=' . $q . esc_attr( $a['id'] ) . $q : '' )
			. ( ! empty( $a['name'] ) ? ' name=' . $q . esc_attr( $a['name'] ) . $q : '' )
			. ' value=' . $q . esc_attr( $value ) . $q
			. ' placeholder=' . $q . esc_attr( (string) ( $a['placeholder'] ?? '' ) ) . $q
			. ' spellcheck=' . $q . 'false' . $q . ' autocomplete=' . $q . 'off' . $q . $box . '>'
			. '<button type=' . $q . 'button' . $q . ' class=' . $q . 'sb-tweaks-colour__theme' . $q . ' aria-label=' . $q . esc_attr__( 'Theme colours', 'sb-tweaks' ) . $q . ' data-sb-tip=' . $q . esc_attr__( 'Theme colours', 'sb-tweaks' ) . $q . ' aria-expanded=' . $q . 'false' . $q . ' hidden>'
			. '<span class=' . $q . 'dashicons dashicons-art' . $q . ' aria-hidden=' . $q . 'true' . $q . '></span></button>'
			. '</span>'
			. ( class_exists( 'SB_Tweaks_Colours' ) ? SB_Tweaks_Colours::data_tag() : '' );
	}
	/**
	 * A safe CSS colour: hex, rgb()/hsl(), or a variable like var(--primary)
	 * or var(--primary, #fff). Returns '' for anything else, so nothing
	 * unexpected reaches a style rule.
	 */
	public static function sanitize_css_colour( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( $value === '' ) {
			return '';
		}

		$hex  = '#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})';
		$func = '(?:rgba?|hsla?)\(\s*[0-9.%,\s\/deg-]+\)';
		$var  = 'var\(\s*--[A-Za-z0-9_-]+\s*\)';

		if ( preg_match( '/^' . $hex . '$/', $value ) ) {
			return strtolower( $value );
		}

		if ( preg_match( '/^' . $func . '$/i', $value ) ) {
			return $value;
		}

		if ( preg_match( '/^var\(\s*--[A-Za-z0-9_-]+\s*(?:,\s*(?:' . $hex . '|' . $func . '|' . $var . '|[a-zA-Z]+)\s*)?\)$/i', $value ) ) {
			return preg_replace( '/\s+/', ' ', $value );
		}

		return '';
	}
}
