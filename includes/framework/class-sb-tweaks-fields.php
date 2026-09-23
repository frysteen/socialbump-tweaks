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
 * multicheck (options, invert, select_all), text. Any field can carry a
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
					// Text box takes hex, rgb()/hsl() or var(--name). The swatch fills in a hex.
					$swatch = preg_match( '/^#[0-9a-f]{6}$/i', (string) $value ) ? (string) $value : '#ffffff';

					echo '<span class=' . $q . 'sb-tweaks-colour' . $q . '>'
						. '<input type=' . $q . 'color' . $q . ' class=' . $q . 'sb-tweaks-colour__swatch' . $q . ' value=' . $q . esc_attr( $swatch ) . $q . ' aria-label=' . $q . esc_attr( $label ) . $q . '>'
						. '<input type=' . $q . 'text' . $q . ' class=' . $q . 'sb-tweaks-colour__value code' . $q . ' id=' . $q . esc_attr( $field_id ) . $q . ' name=' . $q . esc_attr( $name ) . $q . ' value=' . $q . esc_attr( (string) $value ) . $q . ' placeholder=' . $q . esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ) . $q . ' spellcheck=' . $q . 'false' . $q . ' autocomplete=' . $q . 'off' . $q . '>'
						. '</span>';
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
					if ( ! empty( $field['select_all'] ) ) {
						echo '<span class=' . $q . 'sb-tweaks-checklist__tools sb-toggles' . $q . '>'
							. '<button type=' . $q . 'button' . $q . ' class=' . $q . 'button-link sb-toggle' . $q . ' data-sb-always-on data-sb-tweaks-check=' . $q . 'all' . $q . '>' . esc_html__( 'Select all', 'sb-tweaks' ) . '</button>'
							. '<span aria-hidden=' . $q . 'true' . $q . '>|</span>'
							. '<button type=' . $q . 'button' . $q . ' class=' . $q . 'button-link sb-toggle' . $q . ' data-sb-always-on data-sb-tweaks-check=' . $q . 'none' . $q . '>' . esc_html__( 'Select none', 'sb-tweaks' ) . '</button>'
							. '</span>';
					}

					echo '<span class=' . $q . 'sb-tweaks-checklist' . $q . '>';

					foreach ( self::options( $field ) as $option => $option_label ) {
						$listed = in_array( (string) $option, $chosen, true );

						echo '<label><input type=' . $q . 'checkbox' . $q . ' name=' . $q . esc_attr( $name ) . '[]' . $q . ' value=' . $q . esc_attr( $option ) . $q . ' ' . checked( $invert ? ! $listed : $listed, true, false ) . '> ' . esc_html( $option_label ) . '</label>';
					}

					echo '</span>';
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
