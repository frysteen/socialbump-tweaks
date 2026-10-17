<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the Elementor Tweaks features share: the module's options, the SB Image
 * section both image features add their settings to, and the one off move
 * over from the Custom Alt Text for Elementor Images plugin.
 *
 * The SB Image section
 *
 * Elementor has no hook that fires once a widget's own settings are all
 * registered, so the section is added straight after the first section of a
 * widget that holds an image setting (elementor/element/after_section_end).
 * That also puts it right below the image in the editor panel.
 *
 * An image setting is a Media control that takes images, a Gallery control,
 * or the custom size box Elementor adds beside every image size dropdown
 * (image_dimensions), so widgets that pick an image from a field, like the
 * Dynamic Content ACF Gallery, count too. Media in a repeater counts (the
 * Testimonial Carousel's slides). Backgrounds, overlays, masks, video
 * fallbacks, map markers, buttons and the shared Advanced tab do not: they
 * are not the widget's own pictures.
 *
 * Elementor strips labels and tabs from controls on the front end, so the
 * test goes by control type and name only, which reads the same in the editor
 * and on the front end. That matters: settings a widget does not register on
 * the front end are dropped before rendering, and dynamic tags in them are
 * never filled in.
 */
class SB_Elementor_Tweaks {

	const FEATURES_OPTION = 'sb_tweaks_elementor_features';

	const SETTINGS_OPTION = 'sb_tweaks_elementor_settings';

	const GROUPS_OPTION = 'sb_tweaks_elementor_groups';

	/** Set once the move over from the old alt text plugin has run. */
	const MIGRATED = 'sb_tweaks_elementor_migrated';

	/** The module's menu slug, which every page address starts with. */
	const SLUG = 'sb-elementor-tweaks';

	/** Custom Alt Text for Elementor Images (MIGHTYminnow), which Image Alt Text replaces. */
	const OLD_ALT_PLUGIN = 'custom-alt-text-for-elementor-images/custom-alt-text-for-elementor-images.php';

	/** The id of the section both image features add their settings to. */
	const SECTION = 'sb_elementor_image';

	/**
	 * What each feature adds to the SB Image section, in order: callable(
	 * $widget, $info ) plus whether it only applies where the widget has no
	 * ratio setting of its own.
	 */
	private static $parts = [];

	public static function boot() {
		add_action( 'elementor/element/after_section_end', [ __CLASS__, 'section_end' ], 10, 3 );
	}

	/** The booted features loader, or null while the module is off. */
	public static function features() {
		return function_exists( 'sb_tweaks_module' ) ? sb_tweaks_module( 'elementor' ) : null;
	}

	public static function is_enabled( $id ) {
		$features = self::features();

		return $features ? $features->is_enabled( $id ) : false;
	}

	/**
	 * A feature's settings for the SB Image section.
	 *
	 * @param string   $id         Short name, for ordering and replacing.
	 * @param callable $controls   callable( $widget, $info ) adding its controls.
	 * @param bool     $ratio_only True when it is only wanted where the widget has no ratio setting of its own.
	 * @param int      $order      Lower runs first.
	 */
	public static function add_part( $id, callable $controls, $ratio_only = false, $order = 10 ) {
		self::$parts[ $id ] = [
			'controls'   => $controls,
			'ratio_only' => (bool) $ratio_only,
			'order'      => (int) $order,
		];

		uasort(
			self::$parts,
			function ( $a, $b ) {
				return $a['order'] - $b['order'];
			}
		);
	}

	/**
	 * Add the SB Image section after the first section of a widget that holds
	 * an image setting.
	 */
	public static function section_end( $element, $section_id, $args ) {
		if ( ! self::$parts || $section_id === self::SECTION || ! $element instanceof \Elementor\Widget_Base ) {
			return;
		}

		// The Advanced tab every widget shares is a widget of its own in Elementor.
		if ( $element instanceof \Elementor\Widget_Common_Base || strpos( (string) $element->get_name(), 'common' ) === 0 ) {
			return;
		}

		// Read the stack directly: asking the widget for its controls here
		// would pull in the shared Advanced tab, and it is still being built.
		$stack = \Elementor\Plugin::$instance->controls_manager->get_element_stack( $element );

		if ( ! is_array( $stack ) || empty( $stack['controls'] ) || isset( $stack['controls'][ self::SECTION ] ) ) {
			return;
		}

		$has_image = false;
		$own_ratio = false;

		foreach ( $stack['controls'] as $id => $control ) {
			if ( self::is_ratio_control( (string) $id ) ) {
				$own_ratio = true;
			}

			if ( ! $has_image && isset( $control['section'] ) && $control['section'] === $section_id && self::is_image_control( (string) $id, (array) $control ) ) {
				$has_image = true;
			}
		}

		if ( ! $has_image ) {
			return;
		}

		$name = (string) $element->get_name();

		if ( in_array( $name, self::own_ratio_widgets(), true ) ) {
			$own_ratio = true;
		}

		/**
		 * What the image features need to know about this widget. Return false
		 * to leave the widget alone.
		 *
		 * 'ratio'      false where the widget has a ratio setting of its own.
		 * 'selector'   The images the ratio, fit and position apply to.
		 * 'background' For a widget that draws its picture as a CSS background
		 *              (see background_widgets()), else null.
		 */
		$backgrounds = self::background_widgets();

		$info = apply_filters(
			'sb_tweaks/elementor/image_widget',
			[
				'widget'     => $name,
				'ratio'      => ! $own_ratio,
				'selector'   => '{{WRAPPER}} img',
				'background' => isset( $backgrounds[ $name ] ) && is_array( $backgrounds[ $name ] ) ? $backgrounds[ $name ] : null,
			],
			$element
		);

		if ( ! is_array( $info ) ) {
			return;
		}

		$parts = array_filter(
			self::$parts,
			function ( $part ) use ( $info ) {
				return ! $part['ratio_only'] || ! empty( $info['ratio'] );
			}
		);

		if ( ! $parts ) {
			return;
		}

		$element->start_controls_section(
			self::SECTION,
			[
				'label' => esc_html__( 'SB Image', 'sb-tweaks' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		foreach ( $parts as $part ) {
			call_user_func( $part['controls'], $element, $info );
		}

		$element->end_controls_section();
	}

	/** Whether a control is one of the widget's own pictures (see the class notes). */
	public static function is_image_control( $id, array $control ) {
		if ( $id === '' || strpos( $id, '_' ) === 0 || strpos( $id, 'sb_elementor_' ) === 0 ) {
			return false;
		}

		if ( preg_match( '/background|overlay|fallback|mask|slideshow|marker|button|poster|hosted_url|icon|video/i', $id ) ) {
			return false;
		}

		$type = isset( $control['type'] ) ? (string) $control['type'] : '';

		if ( $type === 'image_dimensions' || $type === 'gallery' ) {
			return true;
		}

		if ( $type === 'media' ) {
			$types = isset( $control['media_types'] ) ? (array) $control['media_types'] : [ 'image' ];

			return in_array( 'image', $types, true );
		}

		if ( $type === 'repeater' && ! empty( $control['fields'] ) ) {
			foreach ( (array) $control['fields'] as $key => $field ) {
				$field_id = isset( $field['name'] ) ? (string) $field['name'] : (string) $key;

				if ( $field_id !== $id && self::is_image_control( $field_id, (array) $field ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * A ratio setting of the widget's own: aspect_ratio (Gallery, Video),
	 * item_ratio (Posts), thumbs_ratio (Media Carousel), and their per device
	 * copies. Names like freeModeMomentumRatio are not ratios of an image.
	 */
	public static function is_ratio_control( $id ) {
		return strpos( $id, 'sb_elementor_' ) !== 0 && (bool) preg_match( '/(^|_)ratio($|_)/', $id );
	}

	/** Widgets whose ratio setting is registered after their image, so the scan cannot see it yet. */
	public static function own_ratio_widgets() {
		return (array) apply_filters(
			'sb_tweaks/elementor/own_ratio',
			[ 'gallery', 'media-carousel', 'video', 'posts', 'archive-posts', 'loop-grid', 'loop-carousel' ]
		);
	}

	/**
	 * Widgets that draw their picture as a CSS background on a div (with
	 * role="img") rather than as an img, so the shape is set on the box and the
	 * fit and position on the background.
	 *
	 * 'box'       The element the aspect ratio goes on.
	 * 'image'     The element holding the background image.
	 * 'condition' When the shape settings apply. The Call to Action's Cover
	 *             skin fills the whole card, and with the image left or right
	 *             its box is as tall as the text beside it.
	 */
	public static function background_widgets() {
		return (array) apply_filters(
			'sb_tweaks/elementor/background_widgets',
			[
				'call-to-action' => [
					'box'       => '{{WRAPPER}} .elementor-cta__bg-wrapper',
					'image'     => '{{WRAPPER}} .elementor-cta__bg',
					'condition' => [
						'skin'    => 'classic',
						'layout!' => [ 'left', 'right' ],
					],
				],
			]
		);
	}

	/** Whether a widget draws its picture as a background (see background_widgets()). */
	public static function is_background_widget( $name ) {
		$backgrounds = self::background_widgets();

		return isset( $backgrounds[ (string) $name ] );
	}

	/**
	 * Conditions true when any device's value of a responsive setting is one
	 * of $values. Elementor's own condition only reads the desktop value, so a
	 * Custom picked on mobile alone would hide its box.
	 */
	public static function any_device( $name, array $values ) {
		$terms = [
			[
				'name'     => $name,
				'operator' => 'in',
				'value'    => $values,
			],
		];

		if ( isset( \Elementor\Plugin::$instance->breakpoints ) ) {
			foreach ( array_keys( (array) \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints() ) as $device ) {
				$terms[] = [
					'name'     => $name . '_' . $device,
					'operator' => 'in',
					'value'    => $values,
				];
			}
		}

		return [
			'relation' => 'or',
			'terms'    => $terms,
		];
	}

	/**
	 * Move over from Custom Alt Text for Elementor Images, once.
	 *
	 * Runs on every request until it has run (the framework calls it before it
	 * asks whether the module is on). It waits until Elementor is active here.
	 * The plugin's two settings in saved pages are renamed to this module's
	 * (convert_old_alt()), and where the plugin was on or had been used the
	 * module is switched on with Image Alt Text on, so nothing changes on the
	 * front end. The plugin itself is switched off by the framework, through
	 * 'replaces'. A site that never had it starts with the module off.
	 */
	public static function migrate() {
		if ( get_option( self::MIGRATED ) || ! defined( 'ELEMENTOR_VERSION' ) ) {
			return;
		}

		$was_on    = in_array( self::OLD_ALT_PLUGIN, (array) get_option( 'active_plugins', [] ), true );
		$converted = self::convert_old_alt();

		if ( ( $was_on || $converted ) && get_option( self::FEATURES_OPTION ) === false ) {
			update_option( self::FEATURES_OPTION, [ 'elementor-image-alt' => 1 ] );
		}

		update_option( self::MIGRATED, 1 );
	}

	/**
	 * Rename the old alt text plugin's settings in saved Elementor data.
	 *
	 * On Image widgets only (the only widget it added them to):
	 *   alt_text_type 'custom' or 'none'  becomes  sb_elementor_alt
	 *   alt_text_type 'attachment'        is dropped (the default here)
	 *   custom_alt_text                   becomes  sb_elementor_alt_text
	 *   __dynamic__ custom_alt_text       becomes  __dynamic__ sb_elementor_alt_text
	 *
	 * Revisions are included, so restoring one keeps its alt text. The data is
	 * decoded to objects, not arrays, so empty settings stay {} and the JSON
	 * comes back out the way Elementor writes it (wp_json_encode, no slashes).
	 * Each changed post's cached widget output is cleared.
	 *
	 * @param bool $dry_run True to report what would change without saving.
	 * @return array Post ids changed (or that would be).
	 */
	public static function convert_old_alt( $dry_run = false ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND ( meta_value LIKE '%alt_text_type%' OR meta_value LIKE '%custom_alt_text%' )"
		);

		$changed_posts = [];

		foreach ( (array) $rows as $row ) {
			$data = json_decode( $row->meta_value );

			if ( ! is_array( $data ) ) {
				continue;
			}

			$changed = false;

			self::rename_alt_keys( $data, $changed );

			if ( ! $changed ) {
				continue;
			}

			$changed_posts[] = (int) $row->post_id;

			if ( $dry_run ) {
				continue;
			}

			$json = wp_json_encode( $data );

			if ( ! is_string( $json ) || $json === '' ) {
				continue;
			}

			$wpdb->update( $wpdb->postmeta, [ 'meta_value' => $json ], [ 'meta_id' => (int) $row->meta_id ] );

			wp_cache_delete( (int) $row->post_id, 'post_meta' );
			delete_post_meta( (int) $row->post_id, '_elementor_element_cache' );
		}

		return array_values( array_unique( $changed_posts ) );
	}

	/** Walk an Elementor element tree, renaming the old plugin's settings in place. */
	private static function rename_alt_keys( $elements, &$changed ) {
		foreach ( (array) $elements as $element ) {
			if ( ! is_object( $element ) ) {
				continue;
			}

			$is_image = isset( $element->elType, $element->widgetType ) && $element->elType === 'widget' && $element->widgetType === 'image';

			if ( $is_image && isset( $element->settings ) && is_object( $element->settings ) ) {
				$settings = $element->settings;

				if ( property_exists( $settings, 'alt_text_type' ) ) {
					$type = (string) $settings->alt_text_type;

					if ( $type === 'custom' || $type === 'none' ) {
						$settings->sb_elementor_alt = $type;
					}

					unset( $settings->alt_text_type );
					$changed = true;
				}

				if ( property_exists( $settings, 'custom_alt_text' ) ) {
					$settings->sb_elementor_alt_text = $settings->custom_alt_text;

					unset( $settings->custom_alt_text );
					$changed = true;
				}

				if ( isset( $settings->__dynamic__ ) && is_object( $settings->__dynamic__ ) && property_exists( $settings->__dynamic__, 'custom_alt_text' ) ) {
					$settings->__dynamic__->sb_elementor_alt_text = $settings->__dynamic__->custom_alt_text;

					unset( $settings->__dynamic__->custom_alt_text );
					$changed = true;
				}
			}

			if ( ! empty( $element->elements ) ) {
				self::rename_alt_keys( $element->elements, $changed );
			}
		}
	}
}
