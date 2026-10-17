<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aspect ratio, object fit and object position for widget images.
 *
 * Added to the SB Image section of widgets with no ratio setting of their own
 * (SB_Elementor_Tweaks::section_end() works that out). Everything is plain
 * Elementor selectors, so it is written into the page's CSS file, shows live
 * in the editor and can differ per device.
 *
 * Nothing is written until a ratio is picked. The ratio rule carries the fit
 * and position too, through two custom properties the fit and position
 * dropdowns set, so leaving those alone gives cover and centre centre, and
 * picking Original anywhere leaves that device's images exactly as Elementor
 * draws them.
 *
 * Custom in either dropdown writes nothing itself (its selectors_dictionary
 * value is empty, which Elementor skips): the value comes from the Custom box
 * below it. That way a custom value typed for desktop still carries down to
 * tablet and mobile like any other setting.
 *
 * The ratio and position dropdowns are frontend_available, which makes
 * Elementor register a real copy per device. The Custom boxes, fit and
 * position show when any device asks for them (SB_Elementor_Tweaks::
 * any_device()), and Elementor's server side condition check reads each of
 * those per device names without checking it exists: with one responsive
 * control and no per device copies, every image widget left at Original
 * logged an undefined array key warning per device on every render.
 *
 * Widgets that draw their picture as a CSS background (the Call to Action;
 * SB_Elementor_Tweaks::background_widgets()) get the ratio on the picture's
 * box instead, with min-height 0 so the ratio is exact (it takes over from the
 * widget's own Height setting, through a selector one class stronger than
 * Elementor's), and the fit and position become the
 * background's size and position. Only cover and contain mean anything for a
 * background, so the fit list is cut down to those.
 */
class SB_Elementor_Image_Ratio {

	public static function boot() {
		SB_Elementor_Tweaks::add_part( 'ratio', [ __CLASS__, 'controls' ], true, 20 );
	}

	public static function controls( $widget, $info ) {
		$background = ! empty( $info['background'] ) && is_array( $info['background'] ) ? $info['background'] : null;
		$ratios     = [ '1/1', '4/3', '3/2', '16/9', '21/9', '3/4', '2/3', '9/16', 'custom' ];

		if ( $background ) {
			$box        = (string) $background['box'];
			$image      = (string) $background['image'];
			$condition  = isset( $background['condition'] ) ? (array) $background['condition'] : [];
			$ratio_css  = [
				// height 100% stops the box being stretched as a flex item
				// (the Call to Action's card is a wrapping row), which would
				// override the ratio. The card has no set height, so the 100%
				// itself works out as auto.
				$box => 'aspect-ratio: {{VALUE}}; height: 100%;',
				// One class stronger than the box, or the widget's own Height
				// rule, merged into the same block after this one, wins.
				str_replace( '{{WRAPPER}}', '{{WRAPPER}}.elementor-widget', $box ) => 'min-height: 0;',
			];
			$fit_css    = [ $image => 'background-size: {{VALUE}}; background-repeat: no-repeat;' ];
			$pos_css    = [ $image => 'background-position: {{VALUE}};' ];
			$fit_list   = [
				''        => esc_html__( 'Cover', 'sb-tweaks' ),
				'contain' => esc_html__( 'Contain', 'sb-tweaks' ),
			];
			$ratio_help = esc_html__( 'Takes over from the Height setting above.', 'sb-tweaks' );
		} else {
			$selector   = ! empty( $info['selector'] ) ? (string) $info['selector'] : '{{WRAPPER}} img';
			$rule       = 'aspect-ratio: {{VALUE}}; object-fit: var(--sb-elementor-fit, cover); object-position: var(--sb-elementor-position, 50% 50%);';
			$condition  = [];
			$ratio_css  = [ $selector => $rule ];
			$fit_css    = [ $selector => '--sb-elementor-fit: {{VALUE}};' ];
			$pos_css    = [ $selector => '--sb-elementor-position: {{VALUE}};' ];
			$fit_list   = [
				''           => esc_html__( 'Cover', 'sb-tweaks' ),
				'contain'    => esc_html__( 'Contain', 'sb-tweaks' ),
				'fill'       => esc_html__( 'Fill', 'sb-tweaks' ),
				'none'       => esc_html__( 'None', 'sb-tweaks' ),
				'scale-down' => esc_html__( 'Scale down', 'sb-tweaks' ),
			];
			$ratio_help = '';
		}

		// Adds the widget's own condition (if any) to a control's settings.
		$when = function ( array $args ) use ( $condition ) {
			if ( $condition ) {
				$args['condition'] = $condition;
			}

			return $args;
		};

		$widget->add_control(
			'sb_elementor_ratio_heading',
			$when(
				[
					'label'     => esc_html__( 'Image shape', 'sb-tweaks' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				]
			)
		);

		$ratio = [
			'label'                => esc_html__( 'Aspect ratio', 'sb-tweaks' ),
			'type'                 => \Elementor\Controls_Manager::SELECT,
			'default'              => '',
			'options'              => [
				''       => esc_html__( 'Original', 'sb-tweaks' ),
				'1/1'    => esc_html__( '1:1 Square', 'sb-tweaks' ),
				'4/3'    => '4:3',
				'3/2'    => '3:2',
				'16/9'   => '16:9',
				'21/9'   => '21:9',
				'3/4'    => esc_html__( '3:4 Portrait', 'sb-tweaks' ),
				'2/3'    => esc_html__( '2:3 Portrait', 'sb-tweaks' ),
				'9/16'   => esc_html__( '9:16 Portrait', 'sb-tweaks' ),
				'custom' => esc_html__( 'Custom', 'sb-tweaks' ),
			],
			'selectors_dictionary' => [ 'custom' => '' ],
			'frontend_available'   => true,
			'selectors'            => $ratio_css,
		];

		if ( $ratio_help !== '' ) {
			$ratio['description'] = $ratio_help;
		}

		$widget->add_responsive_control( 'sb_elementor_ratio', $when( $ratio ) );

		$widget->add_responsive_control(
			'sb_elementor_ratio_custom',
			$when(
				[
					'label'       => esc_html__( 'Custom ratio', 'sb-tweaks' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => '5/4',
					'description' => esc_html__( 'Width / height, like 5/4 or 1.25.', 'sb-tweaks' ),
					'ai'          => [ 'active' => false ],
					'conditions'  => SB_Elementor_Tweaks::any_device( 'sb_elementor_ratio', [ 'custom' ] ),
					'selectors'   => $ratio_css,
				]
			)
		);

		$widget->add_responsive_control(
			'sb_elementor_fit',
			$when(
				[
					'label'      => esc_html__( 'Object fit', 'sb-tweaks' ),
					'type'       => \Elementor\Controls_Manager::SELECT,
					'default'    => '',
					'options'    => $fit_list,
					'conditions' => SB_Elementor_Tweaks::any_device( 'sb_elementor_ratio', $ratios ),
					'selectors'  => $fit_css,
				]
			)
		);

		$widget->add_responsive_control(
			'sb_elementor_position',
			$when(
				[
					'label'                => esc_html__( 'Position', 'sb-tweaks' ),
					'type'                 => \Elementor\Controls_Manager::SELECT,
					'default'              => '',
					'options'              => [
						''              => esc_html__( 'Centre centre', 'sb-tweaks' ),
						'left top'      => esc_html__( 'Top left', 'sb-tweaks' ),
						'center top'    => esc_html__( 'Top centre', 'sb-tweaks' ),
						'right top'     => esc_html__( 'Top right', 'sb-tweaks' ),
						'left center'   => esc_html__( 'Centre left', 'sb-tweaks' ),
						'right center'  => esc_html__( 'Centre right', 'sb-tweaks' ),
						'left bottom'   => esc_html__( 'Bottom left', 'sb-tweaks' ),
						'center bottom' => esc_html__( 'Bottom centre', 'sb-tweaks' ),
						'right bottom'  => esc_html__( 'Bottom right', 'sb-tweaks' ),
						'custom'        => esc_html__( 'Custom', 'sb-tweaks' ),
					],
					'selectors_dictionary' => [ 'custom' => '' ],
					'frontend_available'   => true,
					'conditions'           => SB_Elementor_Tweaks::any_device( 'sb_elementor_ratio', $ratios ),
					'selectors'            => $pos_css,
				]
			)
		);

		$widget->add_responsive_control(
			'sb_elementor_position_custom',
			$when(
				[
					'label'       => esc_html__( 'Custom position', 'sb-tweaks' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => '50% 30%',
					'description' => esc_html__( 'Across then down, like 50% 30% or right 20px top 10px.', 'sb-tweaks' ),
					'ai'          => [ 'active' => false ],
					'conditions'  => SB_Elementor_Tweaks::any_device( 'sb_elementor_position', [ 'custom' ] ),
					'selectors'   => $pos_css,
				]
			)
		);
	}
}
