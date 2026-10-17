<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Alt text set per widget, for every image the widget shows.
 *
 * The settings sit in the SB Image section (SB_Elementor_Tweaks::section_end()).
 * The widget's finished HTML is then read tag by tag with WordPress's HTML
 * tag processor, and each image's alt is set properly, rather than searching
 * the HTML for the alt text the image already had. So it works whatever the
 * widget printed, an empty alt and a missing one included, and on galleries
 * and carousels as well as single images.
 *
 * Widgets that draw an image as a background with role="img" (the Media
 * Carousel, for one) get the text as their aria-label instead, which is what
 * screen readers read there.
 */
class SB_Elementor_Image_Alt {

	public static function boot() {
		SB_Elementor_Tweaks::add_part( 'alt', [ __CLASS__, 'controls' ], false, 10 );

		add_filter( 'elementor/widget/render_content', [ __CLASS__, 'render' ], 10, 2 );
	}

	public static function controls( $widget, $info ) {
		$widget->add_control(
			'sb_elementor_alt',
			[
				'label'   => esc_html__( 'Alt text', 'sb-tweaks' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					''       => esc_html__( 'Image\'s own', 'sb-tweaks' ),
					'custom' => esc_html__( 'Custom', 'sb-tweaks' ),
					'none'   => esc_html__( 'None (decorative)', 'sb-tweaks' ),
				],
			]
		);

		$widget->add_control(
			'sb_elementor_alt_text',
			[
				'label'       => esc_html__( 'Custom alt text', 'sb-tweaks' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
				'dynamic'     => [ 'active' => true ],
				'ai'          => [ 'active' => false ],
				'condition'   => [ 'sb_elementor_alt' => 'custom' ],
			]
		);

		$widget->add_control(
			'sb_elementor_alt_fill',
			[
				'label'       => esc_html__( 'Only where empty', 'sb-tweaks' ),
				'type'        => \Elementor\Controls_Manager::SWITCHER,
				'description' => esc_html__( 'Keep the alt text images already have, and use this only for images with none.', 'sb-tweaks' ),
				'condition'   => [ 'sb_elementor_alt' => 'custom' ],
			]
		);
	}

	/** Set the alt text on every image in the widget's HTML. */
	public static function render( $content, $widget ) {
		if ( ! is_string( $content ) || $content === '' || ! is_object( $widget ) || ! method_exists( $widget, 'get_settings' ) ) {
			return $content;
		}

		$mode = $widget->get_settings( 'sb_elementor_alt' );

		if ( $mode !== 'custom' && $mode !== 'none' ) {
			return $content;
		}

		if ( stripos( $content, '<img' ) === false && strpos( $content, 'role="img"' ) === false ) {
			return $content;
		}

		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $content;
		}

		$alt  = '';
		$fill = false;

		if ( $mode === 'custom' ) {
			$alt = (string) $widget->get_settings_for_display( 'sb_elementor_alt_text' );

			// A dynamic tag can hand back HTML or entities (a post title with
			// an ampersand, say). The tag processor escapes the value itself.
			$alt = trim( html_entity_decode( wp_strip_all_tags( $alt ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

			// Nothing to put there: leave the images as they are.
			if ( $alt === '' ) {
				return $content;
			}

			$fill = $widget->get_settings( 'sb_elementor_alt_fill' ) === 'yes';
		}

		// A widget that draws its picture as a background has one place for
		// the text, its role="img" element. An img in it is something else
		// (the Call to Action's small graphic image), so it is left alone.
		$background_only = SB_Elementor_Tweaks::is_background_widget( $widget->get_name() );

		$html = new WP_HTML_Tag_Processor( $content );

		while ( $html->next_tag() ) {
			if ( $html->get_tag() === 'IMG' ) {
				if ( $background_only ) {
					continue;
				}

				$attribute = 'alt';
			} elseif ( $html->get_attribute( 'role' ) === 'img' ) {
				$attribute = 'aria-label';
			} else {
				continue;
			}

			if ( $fill ) {
				$current = $html->get_attribute( $attribute );

				if ( is_string( $current ) && trim( $current ) !== '' ) {
					continue;
				}
			}

			// A decorative background image is hidden from screen readers.
			if ( $mode === 'none' && $attribute === 'aria-label' ) {
				$html->remove_attribute( 'aria-label' );
				$html->set_attribute( 'role', 'presentation' );

				continue;
			}

			$html->set_attribute( $attribute, $alt );
		}

		return $html->get_updated_html();
	}
}
