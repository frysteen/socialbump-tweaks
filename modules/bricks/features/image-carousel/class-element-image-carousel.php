<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_Bricks_Element_Image_Carousel extends \Bricks\Element {
	public $category = 'media';
	public $name     = 'sb-bricks-image-carousel';
	public $icon     = 'ti-layout-slider';
	public $scripts  = [ 'bricksSbbtCarousel' ];

	public function get_label() {
		return esc_html__( 'Image Carousel (SB)', 'sb-tweaks' );
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'bricks-splide' );
		wp_enqueue_style( 'bricks-splide' );
		wp_enqueue_style( 'sb-bricks-carousel' );

		if ( isset( $this->settings['autoplayMode'] ) && $this->settings['autoplayMode'] === 'continuous' ) {
			wp_enqueue_script( 'sb-bricks-splide-auto-scroll' );
		}

		wp_enqueue_script( 'sb-bricks-carousel' );

		if ( ! empty( $this->settings['lightbox'] ) ) {
			wp_enqueue_script( 'bricks-photoswipe' );
			wp_enqueue_script( 'bricks-photoswipe-lightbox' );
			wp_enqueue_style( 'bricks-photoswipe' );
		}
	}

	public function set_control_groups() {
		$this->control_groups['layout']   = [ 'title' => esc_html__( 'Layout', 'sb-tweaks' ), 'tab' => 'content' ];
		$this->control_groups['carousel'] = [ 'title' => esc_html__( 'Carousel', 'sb-tweaks' ), 'tab' => 'content' ];
		$this->control_groups['autoplay'] = [ 'title' => esc_html__( 'Autoplay', 'sb-tweaks' ), 'tab' => 'content' ];
		$this->control_groups['arrows']   = [ 'title' => esc_html__( 'Arrows', 'sb-tweaks' ), 'tab' => 'content' ];
		$this->control_groups['dots']     = [ 'title' => esc_html__( 'Pagination', 'sb-tweaks' ), 'tab' => 'content' ];
		$this->control_groups['lightbox'] = [ 'title' => esc_html__( 'Lightbox', 'sb-tweaks' ), 'tab' => 'content' ];
		$this->control_groups['seo']      = [ 'title' => esc_html__( 'Alt text', 'sb-tweaks' ), 'tab' => 'content' ];
	}

	public function set_controls() {
		/* ---------------- Source ---------------- */
		$this->controls['items'] = [
			'tab'   => 'content',
			'type'  => 'image-gallery',
			'label' => esc_html__( 'Images', 'sb-tweaks' ),
		];

		$this->controls['order'] = [
			'tab'         => 'content',
			'label'       => esc_html__( 'Order', 'sb-tweaks' ),
			'type'        => 'select',
			'options'     => [
				'as-is'  => esc_html__( 'As arranged', 'sb-tweaks' ),
				'random' => esc_html__( 'Random each page load', 'sb-tweaks' ),
			],
			'inline'      => true,
			'default'     => 'as-is',
			'description' => esc_html__( 'Page caching will freeze a random order until the cache clears.', 'sb-tweaks' ),
		];

		/* ---------------- Layout ---------------- */
		$this->controls['aspectRatio'] = [
			'tab'         => 'content',
			'group'       => 'layout',
			'label'       => esc_html__( 'Aspect ratio', 'sb-tweaks' ),
			'type'        => 'text',
			'inline'      => true,
			'placeholder' => '3/2',
			'breakpoints' => true,
			'description' => esc_html__( 'For example 1/1, 4/3, 16/9. Leave empty to keep the natural image ratio.', 'sb-tweaks' ),
		];

		$this->controls['objectFit'] = [
			'tab'      => 'content',
			'group'    => 'layout',
			'label'    => esc_html__( 'Object fit', 'sb-tweaks' ),
			'type'     => 'select',
			'options'  => [
				'cover'   => 'cover',
				'contain' => 'contain',
				'fill'    => 'fill',
			],
			'inline'   => true,
			'default'  => 'cover',
			'required' => [ 'aspectRatio', '!=', '' ],
		];

		$this->controls['objectPosition'] = [
			'tab'         => 'content',
			'group'       => 'layout',
			'label'       => esc_html__( 'Object position', 'sb-tweaks' ),
			'type'        => 'text',
			'inline'      => true,
			'placeholder' => 'center center',
			'required'    => [ 'objectFit', '=', 'cover' ],
		];

		$this->controls['radius'] = [
			'tab'         => 'content',
			'group'       => 'layout',
			'label'       => esc_html__( 'Slide border radius', 'sb-tweaks' ),
			'type'        => 'number',
			'units'       => true,
			'inline'      => true,
			'placeholder' => '0px',
		];

		$this->controls['caption'] = [
			'tab'         => 'content',
			'group'       => 'layout',
			'label'       => esc_html__( 'Caption', 'sb-tweaks' ),
			'type'        => 'select',
			'options'     => [
				''        => esc_html__( 'None', 'sb-tweaks' ),
				'below'   => esc_html__( 'Below image', 'sb-tweaks' ),
				'overlay' => esc_html__( 'Overlay', 'sb-tweaks' ),
			],
			'inline'      => true,
			'description' => esc_html__( 'Uses the caption set on the media item.', 'sb-tweaks' ),
		];

		$this->controls['captionTypography'] = [
			'tab'      => 'content',
			'group'    => 'layout',
			'label'    => esc_html__( 'Caption typography', 'sb-tweaks' ),
			'type'     => 'typography',
			'css'      => [ [ 'property' => 'font', 'selector' => '.sb-carousel__caption' ] ],
			'required' => [ 'caption', '!=', '' ],
		];

		$this->controls['fadeEdges'] = [
			'tab'         => 'content',
			'group'       => 'layout',
			'label'       => esc_html__( 'Fade edges', 'sb-tweaks' ),
			'type'        => 'checkbox',
			'description' => esc_html__( 'Slides fade out at the edges of the carousel, whatever sits behind them.', 'sb-tweaks' ),
		];

		$this->controls['fadeSides'] = [
			'tab'      => 'content',
			'group'    => 'layout',
			'label'    => esc_html__( 'Fade which sides', 'sb-tweaks' ),
			'type'     => 'select',
			'options'  => [
				'both'  => esc_html__( 'Both', 'sb-tweaks' ),
				'left'  => esc_html__( 'Left only', 'sb-tweaks' ),
				'right' => esc_html__( 'Right only', 'sb-tweaks' ),
			],
			'inline'   => true,
			'default'  => 'both',
			'required' => [ 'fadeEdges', '=', true ],
		];

		$this->controls['fadeWidth'] = [
			'tab'         => 'content',
			'group'       => 'layout',
			'label'       => esc_html__( 'Fade width', 'sb-tweaks' ),
			'type'        => 'number',
			'units'       => true,
			'inline'      => true,
			'default'     => '80px',
			'breakpoints' => true,
			'required'    => [ 'fadeEdges', '=', true ],
			'description' => esc_html__( 'Percentages work too, for example 10%.', 'sb-tweaks' ),
		];
		/* ---------------- Carousel ---------------- */
		$this->controls['perPage'] = [
			'tab'         => 'content',
			'group'       => 'carousel',
			'label'       => esc_html__( 'Slides to show', 'sb-tweaks' ),
			'type'        => 'number',
			'min'         => 1,
			'step'        => 0.5,
			'inline'      => true,
			'default'     => 3,
			'breakpoints' => true,
			'description' => esc_html__( 'Decimals such as 1.5 show part of the next slide.', 'sb-tweaks' ),
		];

		$this->controls['perMove'] = [
			'tab'         => 'content',
			'group'       => 'carousel',
			'label'       => esc_html__( 'Slides to move', 'sb-tweaks' ),
			'type'        => 'number',
			'min'         => 1,
			'inline'      => true,
			'default'     => 1,
			'breakpoints' => true,
		];

		$this->controls['gap'] = [
			'tab'         => 'content',
			'group'       => 'carousel',
			'label'       => esc_html__( 'Gap', 'sb-tweaks' ),
			'type'        => 'number',
			'units'       => true,
			'inline'      => true,
			'default'     => '20px',
			'breakpoints' => true,
		];

		$this->controls['peek'] = [
			'tab'         => 'content',
			'group'       => 'carousel',
			'label'       => esc_html__( 'Edge peek', 'sb-tweaks' ),
			'type'        => 'number',
			'units'       => true,
			'inline'      => true,
			'breakpoints' => true,
			'description' => esc_html__( 'Padding on the left and right of the track, so the neighbouring slides peek in.', 'sb-tweaks' ),
		];

		$this->controls['type'] = [
			'tab'     => 'content',
			'group'   => 'carousel',
			'label'   => esc_html__( 'Transition', 'sb-tweaks' ),
			'type'    => 'select',
			'options' => [
				'loop'  => esc_html__( 'Loop', 'sb-tweaks' ),
				'slide' => esc_html__( 'Slide (no loop)', 'sb-tweaks' ),
				'fade'  => esc_html__( 'Fade', 'sb-tweaks' ),
			],
			'inline'  => true,
			'default' => 'loop',
		];

		$this->controls['fadeInfo'] = [
			'tab'      => 'content',
			'group'    => 'carousel',
			'type'     => 'info',
			'content'  => esc_html__( 'Fade forces one slide at a time.', 'sb-tweaks' ),
			'required' => [ 'type', '=', 'fade' ],
		];

		$this->controls['speed'] = [
			'tab'     => 'content',
			'group'   => 'carousel',
			'label'   => esc_html__( 'Transition speed (ms)', 'sb-tweaks' ),
			'type'    => 'number',
			'min'     => 0,
			'inline'  => true,
			'default' => 600,
		];

		$this->controls['easing'] = [
			'tab'         => 'content',
			'group'       => 'carousel',
			'label'       => esc_html__( 'Easing', 'sb-tweaks' ),
			'type'        => 'text',
			'inline'      => true,
			'placeholder' => 'cubic-bezier(0.25, 1, 0.5, 1)',
		];

		$this->controls['drag'] = [
			'tab'     => 'content',
			'group'   => 'carousel',
			'label'   => esc_html__( 'Drag', 'sb-tweaks' ),
			'type'    => 'select',
			'options' => [
				'true'  => esc_html__( 'On', 'sb-tweaks' ),
				'free'  => esc_html__( 'Free drag', 'sb-tweaks' ),
				'false' => esc_html__( 'Off', 'sb-tweaks' ),
			],
			'inline'  => true,
			'default' => 'true',
		];

		$this->controls['wheel'] = [
			'tab'   => 'content',
			'group' => 'carousel',
			'label' => esc_html__( 'Mouse wheel control', 'sb-tweaks' ),
			'type'  => 'checkbox',
		];

		$this->controls['keyboard'] = [
			'tab'         => 'content',
			'group'       => 'carousel',
			'label'       => esc_html__( 'Keyboard arrows', 'sb-tweaks' ),
			'type'        => 'checkbox',
			'default'     => true,
			'description' => esc_html__( 'Arrow keys move the carousel while the pointer is over it, or while it has focus.', 'sb-tweaks' ),
		];

		$this->controls['startIndex'] = [
			'tab'     => 'content',
			'group'   => 'carousel',
			'label'   => esc_html__( 'Start at slide', 'sb-tweaks' ),
			'type'    => 'number',
			'min'     => 1,
			'inline'  => true,
			'default' => 1,
		];

		$this->controls['rewind'] = [
			'tab'      => 'content',
			'group'    => 'carousel',
			'label'    => esc_html__( 'Rewind to start at the end', 'sb-tweaks' ),
			'type'     => 'checkbox',
			'required' => [ 'type', '!=', 'loop' ],
		];

		$this->controls['rtl'] = [
			'tab'   => 'content',
			'group' => 'carousel',
			'label' => esc_html__( 'Right to left', 'sb-tweaks' ),
			'type'  => 'checkbox',
		];

		/* ---------------- Autoplay ---------------- */
		$this->controls['autoplayMode'] = [
			'tab'     => 'content',
			'group'   => 'autoplay',
			'label'   => esc_html__( 'Mode', 'sb-tweaks' ),
			'type'    => 'select',
			'options' => [
				'off'        => esc_html__( 'Off', 'sb-tweaks' ),
				'slide'      => esc_html__( 'Slide by slide', 'sb-tweaks' ),
				'continuous' => esc_html__( 'Continuous scroll', 'sb-tweaks' ),
			],
			'inline'  => true,
			'default' => 'off',
		];

		$this->controls['continuousInfo'] = [
			'tab'      => 'content',
			'group'    => 'autoplay',
			'type'     => 'info',
			'content'  => esc_html__( 'Continuous scroll never stops and never jumps. It forces looping, and slides are repeated behind the scenes when there are too few to fill the loop.', 'sb-tweaks' ),
			'required' => [ 'autoplayMode', '=', 'continuous' ],
		];

		$this->controls['autoplayInterval'] = [
			'tab'      => 'content',
			'group'    => 'autoplay',
			'label'    => esc_html__( 'Interval (ms)', 'sb-tweaks' ),
			'type'     => 'number',
			'min'      => 100,
			'inline'   => true,
			'default'  => 4000,
			'required' => [ 'autoplayMode', '=', 'slide' ],
		];

		$this->controls['autoScrollSpeed'] = [
			'tab'      => 'content',
			'group'    => 'autoplay',
			'label'    => esc_html__( 'Scroll speed', 'sb-tweaks' ),
			'type'     => 'number',
			'min'      => 0.1,
			'step'     => 0.1,
			'inline'   => true,
			'default'  => 1,
			'required' => [ 'autoplayMode', '=', 'continuous' ],
		];

		$this->controls['autoScrollReverse'] = [
			'tab'      => 'content',
			'group'    => 'autoplay',
			'label'    => esc_html__( 'Reverse direction', 'sb-tweaks' ),
			'type'     => 'checkbox',
			'required' => [ 'autoplayMode', '=', 'continuous' ],
		];

		$this->controls['pauseOnHover'] = [
			'tab'      => 'content',
			'group'    => 'autoplay',
			'label'    => esc_html__( 'Pause on hover', 'sb-tweaks' ),
			'type'     => 'checkbox',
			'default'  => true,
			'required' => [ 'autoplayMode', '!=', 'off' ],
		];

		$this->controls['pauseOnFocus'] = [
			'tab'      => 'content',
			'group'    => 'autoplay',
			'label'    => esc_html__( 'Pause on focus', 'sb-tweaks' ),
			'type'     => 'checkbox',
			'default'  => true,
			'required' => [ 'autoplayMode', '!=', 'off' ],
		];

		$this->controls['progressBar'] = [
			'tab'      => 'content',
			'group'    => 'autoplay',
			'label'    => esc_html__( 'Show progress bar', 'sb-tweaks' ),
			'type'     => 'checkbox',
			'required' => [ 'autoplayMode', '=', 'slide' ],
		];

		$this->controls['progressBarColor'] = [
			'tab'      => 'content',
			'group'    => 'autoplay',
			'label'    => esc_html__( 'Progress bar colour', 'sb-tweaks' ),
			'type'     => 'color',
			'required' => [ 'progressBar', '=', true ],
		];

		/* ---------------- Arrows ---------------- */
		$this->controls['showArrows'] = [
			'tab'     => 'content',
			'group'   => 'arrows',
			'label'   => esc_html__( 'Show arrows', 'sb-tweaks' ),
			'type'    => 'checkbox',
			'default' => true,
		];

		$this->controls['arrowPosition'] = [
			'tab'      => 'content',
			'group'    => 'arrows',
			'label'    => esc_html__( 'Position', 'sb-tweaks' ),
			'type'     => 'select',
			'options'  => [
				'inside'       => esc_html__( 'Inside edges', 'sb-tweaks' ),
				'outside'      => esc_html__( 'Outside edges', 'sb-tweaks' ),
				'above-left'   => esc_html__( 'Above left', 'sb-tweaks' ),
				'above-right'  => esc_html__( 'Above right', 'sb-tweaks' ),
				'below-left'   => esc_html__( 'Below left', 'sb-tweaks' ),
				'below-center' => esc_html__( 'Below centre', 'sb-tweaks' ),
				'below-right'  => esc_html__( 'Below right', 'sb-tweaks' ),
			],
			'inline'   => true,
			'default'  => 'inside',
			'required' => [ 'showArrows', '=', true ],
		];

		$this->controls['prevIcon'] = [
			'tab'      => 'content',
			'group'    => 'arrows',
			'label'    => esc_html__( 'Previous icon', 'sb-tweaks' ),
			'type'     => 'icon',
			'required' => [ 'showArrows', '=', true ],
		];

		$this->controls['nextIcon'] = [
			'tab'      => 'content',
			'group'    => 'arrows',
			'label'    => esc_html__( 'Next icon', 'sb-tweaks' ),
			'type'     => 'icon',
			'required' => [ 'showArrows', '=', true ],
		];

		$this->controls['arrowSize'] = [
			'tab'      => 'content',
			'group'    => 'arrows',
			'label'    => esc_html__( 'Button size', 'sb-tweaks' ),
			'type'     => 'number',
			'units'    => true,
			'inline'   => true,
			'default'  => '44px',
			'required' => [ 'showArrows', '=', true ],
		];

		$this->controls['arrowIconSize'] = [
			'tab'      => 'content',
			'group'    => 'arrows',
			'label'    => esc_html__( 'Icon size', 'sb-tweaks' ),
			'type'     => 'number',
			'units'    => true,
			'inline'   => true,
			'default'  => '18px',
			'required' => [ 'showArrows', '=', true ],
		];

		$this->controls['arrowOffset'] = [
			'tab'      => 'content',
			'group'    => 'arrows',
			'label'    => esc_html__( 'Offset', 'sb-tweaks' ),
			'type'     => 'number',
			'units'    => true,
			'inline'   => true,
			'default'  => '12px',
			'required' => [ 'showArrows', '=', true ],
		];

		$this->controls['arrowColor'] = [
			'tab'      => 'content',
			'group'    => 'arrows',
			'label'    => esc_html__( 'Icon colour', 'sb-tweaks' ),
			'type'     => 'color',
			'required' => [ 'showArrows', '=', true ],
		];

		$this->controls['arrowBg'] = [
			'tab'      => 'content',
			'group'    => 'arrows',
			'label'    => esc_html__( 'Background', 'sb-tweaks' ),
			'type'     => 'color',
			'required' => [ 'showArrows', '=', true ],
		];

		$this->controls['arrowRadius'] = [
			'tab'      => 'content',
			'group'    => 'arrows',
			'label'    => esc_html__( 'Button radius', 'sb-tweaks' ),
			'type'     => 'number',
			'units'    => true,
			'inline'   => true,
			'default'  => '50%',
			'required' => [ 'showArrows', '=', true ],
		];

		$this->controls['arrowBorder'] = [
			'tab'      => 'content',
			'group'    => 'arrows',
			'label'    => esc_html__( 'Border', 'sb-tweaks' ),
			'type'     => 'border',
			'css'      => [ [ 'property' => 'border', 'selector' => '.splide__arrow' ] ],
			'required' => [ 'showArrows', '=', true ],
		];

		/* ---------------- Pagination ---------------- */
		$this->controls['showDots'] = [
			'tab'   => 'content',
			'group' => 'dots',
			'label' => esc_html__( 'Show pagination', 'sb-tweaks' ),
			'type'  => 'checkbox',
		];

		$this->controls['dotsStyle'] = [
			'tab'      => 'content',
			'group'    => 'dots',
			'label'    => esc_html__( 'Style', 'sb-tweaks' ),
			'type'     => 'select',
			'options'  => [
				'dot' => esc_html__( 'Dots', 'sb-tweaks' ),
				'bar' => esc_html__( 'Bars', 'sb-tweaks' ),
			],
			'inline'   => true,
			'default'  => 'dot',
			'required' => [ 'showDots', '=', true ],
		];

		$this->controls['dotsPosition'] = [
			'tab'      => 'content',
			'group'    => 'dots',
			'label'    => esc_html__( 'Position', 'sb-tweaks' ),
			'type'     => 'select',
			'options'  => [
				'below'   => esc_html__( 'Below the carousel', 'sb-tweaks' ),
				'overlay' => esc_html__( 'Over the carousel', 'sb-tweaks' ),
			],
			'inline'   => true,
			'default'  => 'below',
			'required' => [ 'showDots', '=', true ],
		];

		$this->controls['dotsAlign'] = [
			'tab'      => 'content',
			'group'    => 'dots',
			'label'    => esc_html__( 'Alignment', 'sb-tweaks' ),
			'type'     => 'select',
			'options'  => [
				'flex-start' => esc_html__( 'Left', 'sb-tweaks' ),
				'center'     => esc_html__( 'Centre', 'sb-tweaks' ),
				'flex-end'   => esc_html__( 'Right', 'sb-tweaks' ),
			],
			'inline'   => true,
			'default'  => 'center',
			'required' => [ 'showDots', '=', true ],
		];

		$this->controls['dotsOffset'] = [
			'tab'      => 'content',
			'group'    => 'dots',
			'label'    => esc_html__( 'Offset', 'sb-tweaks' ),
			'type'     => 'number',
			'units'    => true,
			'inline'   => true,
			'default'  => '16px',
			'required' => [ 'showDots', '=', true ],
		];

		$this->controls['dotSize'] = [
			'tab'      => 'content',
			'group'    => 'dots',
			'label'    => esc_html__( 'Size', 'sb-tweaks' ),
			'type'     => 'number',
			'units'    => true,
			'inline'   => true,
			'default'  => '10px',
			'required' => [ 'showDots', '=', true ],
		];

		$this->controls['dotGap'] = [
			'tab'      => 'content',
			'group'    => 'dots',
			'label'    => esc_html__( 'Gap', 'sb-tweaks' ),
			'type'     => 'number',
			'units'    => true,
			'inline'   => true,
			'default'  => '8px',
			'required' => [ 'showDots', '=', true ],
		];

		$this->controls['dotColor'] = [
			'tab'      => 'content',
			'group'    => 'dots',
			'label'    => esc_html__( 'Colour', 'sb-tweaks' ),
			'type'     => 'color',
			'required' => [ 'showDots', '=', true ],
		];

		$this->controls['dotActiveColor'] = [
			'tab'      => 'content',
			'group'    => 'dots',
			'label'    => esc_html__( 'Active colour', 'sb-tweaks' ),
			'type'     => 'color',
			'required' => [ 'showDots', '=', true ],
		];

		/* ---------------- Lightbox ---------------- */
		$this->controls['lightbox'] = [
			'tab'   => 'content',
			'group' => 'lightbox',
			'label' => esc_html__( 'Open images in a lightbox', 'sb-tweaks' ),
			'type'  => 'checkbox',
		];

		$this->controls['lightboxImageSize'] = [
			'tab'      => 'content',
			'group'    => 'lightbox',
			'label'    => esc_html__( 'Lightbox image size', 'sb-tweaks' ),
			'type'     => 'select',
			'options'  => \Bricks\Setup::get_image_sizes_options(),
			'inline'   => true,
			'default'  => 'full',
			'required' => [ 'lightbox', '=', true ],
		];

		$this->controls['lightboxCaption'] = [
			'tab'      => 'content',
			'group'    => 'lightbox',
			'label'    => esc_html__( 'Show caption in lightbox', 'sb-tweaks' ),
			'type'     => 'checkbox',
			'required' => [ 'lightbox', '=', true ],
		];

		$this->controls['lightboxId'] = [
			'tab'         => 'content',
			'group'       => 'lightbox',
			'label'       => esc_html__( 'Lightbox ID', 'sb-tweaks' ),
			'type'        => 'text',
			'inline'      => true,
			'description' => esc_html__( 'Galleries sharing an ID are grouped together.', 'sb-tweaks' ),
			'required'    => [ 'lightbox', '=', true ],
		];

		/* ---------------- Alt text ---------------- */
		$this->controls['altInfo'] = [
			'tab'     => 'content',
			'group'   => 'seo',
			'type'    => 'info',
			'content' => esc_html__( 'By default each image uses the alt text set on the media item. Use the field below as a fallback for images that have none.', 'sb-tweaks' ),
		];

		$this->controls['altText'] = [
			'tab'         => 'content',
			'group'       => 'seo',
			'label'       => esc_html__( 'Custom alt text', 'sb-tweaks' ),
			'type'        => 'text',
			'hasDynamicData' => 'text',
			'description' => esc_html__( 'Add {index} to number the images, for example: Kitchen renovation Brisbane {index}', 'sb-tweaks' ),
		];

		$this->controls['altOverride'] = [
			'tab'         => 'content',
			'group'       => 'seo',
			'label'       => esc_html__( 'Override existing alt text', 'sb-tweaks' ),
			'type'        => 'checkbox',
			'description' => esc_html__( 'Forces every image to use the custom alt text, even when the media item already has one.', 'sb-tweaks' ),
		];
	}

	/**
	 * Collect a breakpoint aware control value.
	 */
	private function get_responsive( $key ) {
		$settings = $this->settings;
		$base     = null;
		$per_bp   = [];

		foreach ( \Bricks\Breakpoints::$breakpoints as $bp ) {
			$is_base = ! empty( $bp['base'] );
			$lookup  = $is_base ? $key : $key . ':' . $bp['key'];

			if ( ! isset( $settings[ $lookup ] ) || $settings[ $lookup ] === '' ) {
				continue;
			}

			if ( $is_base ) {
				$base = $settings[ $lookup ];
			} else {
				$per_bp[ (int) $bp['width'] ] = $settings[ $lookup ];
			}
		}

		return [ 'base' => $base, 'breakpoints' => $per_bp ];
	}

	private function color_value( $key ) {
		$color = $this->settings[ $key ] ?? [];

		if ( ! is_array( $color ) ) {
			return '';
		}

		if ( ! empty( $color['raw'] ) ) {
			return $color['raw'];
		}

		if ( ! empty( $color['hex'] ) ) {
			return $color['hex'];
		}

		if ( ! empty( $color['rgb'] ) ) {
			return $color['rgb'];
		}

		return '';
	}

	public function render() {
		$settings   = $this->settings;
		$normalized = \Bricks\Helpers::get_normalized_image_settings( $this, $settings );
		$images     = $normalized['items']['images'] ?? [];
		$size       = $normalized['items']['size'] ?? BRICKS_DEFAULT_IMAGE_SIZE;

		$images = array_values( array_filter( (array) $images ) );

		if ( empty( $images ) ) {
			return $this->render_element_placeholder(
				[ 'title' => esc_html__( 'Select images, or bind a gallery field.', 'sb-tweaks' ) ]
			);
		}

		$is_builder = bricks_is_builder() || \Bricks\Helpers::is_bricks_preview();

		/* Random order, frontend only so the builder stays stable. */
		if ( ( $settings['order'] ?? 'as-is' ) === 'random' && ! $is_builder ) {
			shuffle( $images );
		}

		$mode       = $settings['autoplayMode'] ?? 'off';
		$continuous = $mode === 'continuous';
		$type       = $settings['type'] ?? 'loop';

		if ( $continuous ) {
			$type = 'loop';
		}

		$per_page_data = $this->get_responsive( 'perPage' );
		$per_page      = $per_page_data['base'] !== null ? (float) $per_page_data['base'] : 3;
		$max_per_page  = $per_page;

		foreach ( $per_page_data['breakpoints'] as $value ) {
			$max_per_page = max( $max_per_page, (float) $value );
		}

		/* Continuous scroll needs enough slides for a gapless loop. */
		$real_count = count( $images );

		if ( $continuous ) {
			$needed = max( 6, (int) ceil( $max_per_page ) * 3 );
			$source = $images;
			$i      = 0;

			while ( count( $images ) < $needed ) {
				$duplicate             = $source[ $i % $real_count ];
				$duplicate['sb_bricks_dupe']  = true;
				$images[]              = $duplicate;
				$i++;
			}
		}

		/* ---------------- Splide options ---------------- */
		$drag = $settings['drag'] ?? 'true';

		/* Keyboard is handled in our own JS so it works on hover and on focus. */
		$keyboard = true;

		if ( array_key_exists( 'keyboard', $settings ) ) {
			$keyboard = ! empty( $settings['keyboard'] ) && $settings['keyboard'] !== 'off';
		}

		/* Carried over from an earlier version of this element. */
		if ( ! empty( $settings['keyboardOff'] ) ) {
			$keyboard = false;
		}

		$options = [
			'type'         => $type,
			'perPage'      => $type === 'fade' ? 1 : $per_page,
			'speed'        => isset( $settings['speed'] ) ? (int) $settings['speed'] : 600,
			'arrows'       => ! empty( $settings['showArrows'] ),
			'pagination'   => ! empty( $settings['showDots'] ),
			'drag'         => $drag === 'free' ? 'free' : ( $drag === 'false' ? false : true ),
			'keyboard'     => false,
			'wheel'        => false,
			'direction'    => ! empty( $settings['rtl'] ) ? 'rtl' : 'ltr',
			'start'        => max( 0, (int) ( $settings['startIndex'] ?? 1 ) - 1 ),
			'rewind'       => ! empty( $settings['rewind'] ) && $type !== 'loop',
			'autoplay'     => $mode === 'slide',
			'interval'     => isset( $settings['autoplayInterval'] ) ? (int) $settings['autoplayInterval'] : 4000,
			'pauseOnHover' => ! empty( $settings['pauseOnHover'] ),
			'pauseOnFocus' => ! empty( $settings['pauseOnFocus'] ),
			'mediaQuery'   => 'max',
			'breakpoints'  => [],
		];

		if ( ! empty( $settings['easing'] ) ) {
			$options['easing'] = $settings['easing'];
		}

		if ( $type === 'fade' ) {
			$options['perMove'] = 1;
		}

		/* Responsive options */
		$responsive_map = [
			'perPage' => 'perPage',
			'perMove' => 'perMove',
			'gap'     => 'gap',
			'peek'    => 'padding',
		];

		foreach ( $responsive_map as $control => $option ) {
			$data = $this->get_responsive( $control );

			if ( $data['base'] !== null ) {
				$value = in_array( $option, [ 'perPage', 'perMove' ], true ) ? (float) $data['base'] : $data['base'];

				if ( $option === 'padding' ) {
					$value = [ 'left' => $data['base'], 'right' => $data['base'] ];
				}

				if ( ! ( $option === 'perPage' && $type === 'fade' ) ) {
					$options[ $option ] = $value;
				}
			}

			foreach ( $data['breakpoints'] as $width => $value ) {
				if ( $option === 'padding' ) {
					$options['breakpoints'][ $width ][ $option ] = [ 'left' => $value, 'right' => $value ];
				} elseif ( in_array( $option, [ 'perPage', 'perMove' ], true ) ) {
					$options['breakpoints'][ $width ][ $option ] = (float) $value;
				} else {
					$options['breakpoints'][ $width ][ $option ] = $value;
				}
			}
		}

		if ( ! isset( $options['gap'] ) ) {
			$options['gap'] = '20px';
		}

		$config = [
			'splide'     => $options,
			'continuous' => false,
			'progress'   => ! empty( $settings['progressBar'] ) && $mode === 'slide',
			'lightbox'   => ! empty( $settings['lightbox'] ),
			'keyboard'   => $keyboard ? 'on' : 'off',
			'wheel'      => ! empty( $settings['wheel'] ) ? 'on' : 'off',
		];

		if ( $continuous ) {
			$speed = isset( $settings['autoScrollSpeed'] ) ? (float) $settings['autoScrollSpeed'] : 1;

			if ( ! empty( $settings['autoScrollReverse'] ) ) {
				$speed = $speed * -1;
			}

			$config['continuous']          = true;
			$config['splide']['autoplay']  = false;
			$config['splide']['autoScroll'] = [
				'speed'        => $speed,
				'pauseOnHover' => ! empty( $settings['pauseOnHover'] ),
				'pauseOnFocus' => ! empty( $settings['pauseOnFocus'] ),
				'rewind'       => false,
			];
		}

		/* ---------------- Root attributes ---------------- */
		$classes = [ 'splide', 'sb-carousel' ];

		$classes[] = 'sb-carousel--arrows-' . ( $settings['arrowPosition'] ?? 'inside' );
		$classes[] = 'sb-carousel--dots-' . ( $settings['dotsPosition'] ?? 'below' );
		$classes[] = 'sb-carousel--dots-' . ( $settings['dotsStyle'] ?? 'dot' );

		if ( ! empty( $settings['fadeEdges'] ) ) {
			$classes[] = 'sb-carousel--fade';
			$classes[] = 'sb-carousel--fade-' . ( $settings['fadeSides'] ?? 'both' );
		}

		if ( ! empty( $settings['caption'] ) ) {
			$classes[] = 'sb-carousel--caption-' . $settings['caption'];
		}

		if ( ! empty( $settings['lightbox'] ) ) {
			$classes[] = 'bricks-lightbox';
		}

		foreach ( $classes as $class ) {
			$this->set_attribute( '_root', 'class', $class );
		}

		$this->set_attribute( '_root', 'data-sb-carousel', wp_json_encode( $config ) );

		/* Focus mode needs the carousel to be reachable by tab. */
		if ( $keyboard ) {
			$this->set_attribute( '_root', 'tabindex', '0' );
			$this->set_attribute( '_root', 'role', 'region' );
			$this->set_attribute( '_root', 'aria-roledescription', esc_attr__( 'carousel', 'sb-tweaks' ) );
			$this->set_attribute( '_root', 'aria-label', esc_attr__( 'Image carousel. Use the left and right arrow keys to move between slides.', 'sb-tweaks' ) );
		}

		if ( ! empty( $settings['lightboxId'] ) ) {
			$this->set_attribute( '_root', 'data-lightbox-id', esc_attr( $settings['lightboxId'] ) );
		}

		/* CSS custom properties */
		$vars = [
			'--sb-fit'          => $settings['objectFit'] ?? 'cover',
			'--sb-pos'          => $settings['objectPosition'] ?? '',
			'--sb-radius'       => $settings['radius'] ?? '',
			'--sb-arrow-size'   => $settings['arrowSize'] ?? '',
			'--sb-arrow-icon'   => $settings['arrowIconSize'] ?? '',
			'--sb-arrow-offset' => $settings['arrowOffset'] ?? '',
			'--sb-arrow-color'  => $this->color_value( 'arrowColor' ),
			'--sb-arrow-bg'     => $this->color_value( 'arrowBg' ),
			'--sb-arrow-radius' => $settings['arrowRadius'] ?? '',
			'--sb-dot-size'     => $settings['dotSize'] ?? '',
			'--sb-dot-gap'      => $settings['dotGap'] ?? '',
			'--sb-dot-color'    => $this->color_value( 'dotColor' ),
			'--sb-dot-active'   => $this->color_value( 'dotActiveColor' ),
			'--sb-dots-offset'  => $settings['dotsOffset'] ?? '',
			'--sb-dots-align'   => $settings['dotsAlign'] ?? '',
			'--sb-progress'     => $this->color_value( 'progressBarColor' ),
			'--sb-fade'         => $settings['fadeWidth'] ?? '',
		];

		/* Controls that can differ per breakpoint and are driven by a CSS variable. */
		$responsive_vars = [
			'aspectRatio' => '--sb-ar',
			'fadeWidth'   => '--sb-fade',
		];

		$responsive_values = [];

		foreach ( $responsive_vars as $control => $property ) {
			$data = $this->get_responsive( $control );

			$responsive_values[ $property ] = $data['breakpoints'];

			if ( $data['base'] !== null ) {
				$vars[ $property ] = $data['base'];
			}
		}

		$style = '';

		foreach ( $vars as $property => $value ) {
			if ( $value === '' || $value === null ) {
				continue;
			}
			$style .= $property . ':' . $value . ';';
		}

		if ( $style ) {
			$this->set_attribute( '_root', 'style', $style );
		}

		/* Responsive aspect ratio needs real media queries. */
		$ratio_css = '';
		$selector  = '#brxe-' . $this->id;
		$by_width  = [];

		foreach ( $responsive_values as $property => $breakpoint_values ) {
			foreach ( $breakpoint_values as $width => $value ) {
				$by_width[ (int) $width ][ $property ] = $value;
			}
		}

		krsort( $by_width );

		foreach ( $by_width as $width => $declarations ) {
			$css = '';

			foreach ( $declarations as $property => $value ) {
				$css .= $property . ':' . esc_attr( $value ) . ';';
			}

			$ratio_css .= '@media (max-width:' . $width . 'px){' . $selector . '{' . $css . '}}';
		}

		/* ---------------- Markup ---------------- */
		$lightbox      = ! empty( $settings['lightbox'] );
		$lb_size       = $settings['lightboxImageSize'] ?? 'full';
		$caption_mode  = $settings['caption'] ?? '';
		$alt_custom    = trim( (string) ( $settings['altText'] ?? '' ) );
		$alt_override  = ! empty( $settings['altOverride'] );

		if ( $alt_custom !== '' ) {
			$alt_custom = $this->render_dynamic_data( $alt_custom );
		}

		$prev_icon = ! empty( $settings['prevIcon'] ) ? self::render_icon( $settings['prevIcon'] ) : '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15 4l-8 8 8 8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		$next_icon = ! empty( $settings['nextIcon'] ) ? self::render_icon( $settings['nextIcon'] ) : '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 4l8 8-8 8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

		echo "<div {$this->render_attributes( '_root' )}>";

		if ( $ratio_css ) {
			echo '<style>' . $ratio_css . '</style>';
		}

		echo '<div class="splide__track">';
		echo '<ul class="splide__list">';

		$position = 0;

		foreach ( $images as $index => $image ) {
			$image_id = ! empty( $image['id'] ) ? (int) $image['id'] : 0;
			$is_dupe  = ! empty( $image['sb_bricks_dupe'] );

			if ( ! $is_dupe ) {
				$position++;
			}

			/* Alt text */
			$alt = $image_id ? (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : '';

			if ( $alt_custom !== '' && ( $alt_override || $alt === '' ) ) {
				$alt = str_replace( '{index}', (string) $position, $alt_custom );
			}

			if ( $is_dupe ) {
				$alt = '';
			}

			$lcp = $index === 0 && ! $is_dupe;

			/*
			 * Splide moves the track with a transform rather than scrolling, and browsers
			 * do not reliably notice lazy images arriving that way. A looping or scrolling
			 * carousel shows every slide anyway, so load them all up front.
			 */
			$eager = $lcp || $type === 'loop' || $continuous;

			$img_attrs = [
				'class'    => 'sb-carousel__img',
				'alt'      => $alt,
				'loading'  => $eager ? 'eager' : 'lazy',
				'decoding' => 'async',
			];

			if ( $lcp ) {
				$img_attrs['fetchpriority'] = 'high';
			}

			if ( $image_id ) {
				$img_html = wp_get_attachment_image( $image_id, $size, false, $img_attrs );
			} else {
				$url = $image['url'] ?? ( $image['full'] ?? '' );

				if ( ! $url ) {
					continue;
				}

				$img_html = '<img src="' . esc_url( $url ) . '" class="sb-carousel__img" alt="' . esc_attr( $alt ) . '" loading="' . ( $eager ? 'eager' : 'lazy' ) . '" decoding="async">';
			}

			$li_class = 'splide__slide sb-carousel__slide';

			echo '<li class="' . esc_attr( $li_class ) . '"' . ( $is_dupe ? ' aria-hidden="true" data-sb-dupe="1"' : '' ) . '>';
			echo '<figure class="sb-carousel__figure">';

			$caption_text = ( $caption_mode && $image_id ) ? wp_get_attachment_caption( $image_id ) : '';

			if ( $lightbox && ! $is_dupe ) {
				$lb  = $image_id ? wp_get_attachment_image_src( $image_id, $lb_size ) : false;
				$lb  = is_array( $lb ) && ! empty( $lb ) ? $lb : [ $image['full'] ?? ( $image['url'] ?? '' ), 1600, 1200 ];

				$a_attrs = 'href="' . esc_url( $lb[0] ) . '" data-pswp-src="' . esc_url( $lb[0] ) . '" data-pswp-width="' . esc_attr( $lb[1] ) . '" data-pswp-height="' . esc_attr( $lb[2] ) . '"';

				if ( ! empty( $settings['lightboxId'] ) ) {
					$a_attrs .= ' data-pswp-id="' . esc_attr( $settings['lightboxId'] ) . '"';
				}

				if ( ! empty( $settings['lightboxCaption'] ) && $image_id ) {
					$lb_caption = wp_get_attachment_caption( $image_id );

					if ( $lb_caption ) {
						$a_attrs .= ' data-lightbox-caption="' . esc_attr( $lb_caption ) . '"';
					}
				}

				echo '<a class="sb-carousel__link" ' . $a_attrs . '>' . $img_html . '</a>';
			} else {
				echo $img_html;
			}

			if ( $caption_text && ! $is_dupe ) {
				echo '<figcaption class="sb-carousel__caption">' . esc_html( $caption_text ) . '</figcaption>';
			}

			echo '</figure>';
			echo '</li>';
		}

		echo '</ul>';
		echo '</div>';

		if ( ! empty( $settings['showArrows'] ) ) {
			echo '<div class="splide__arrows sb-carousel__arrows">';
			echo '<button class="splide__arrow splide__arrow--prev" type="button" aria-label="' . esc_attr__( 'Previous slide', 'sb-tweaks' ) . '">' . $prev_icon . '</button>';
			echo '<button class="splide__arrow splide__arrow--next" type="button" aria-label="' . esc_attr__( 'Next slide', 'sb-tweaks' ) . '">' . $next_icon . '</button>';
			echo '</div>';
		}

		if ( ! empty( $settings['progressBar'] ) && $mode === 'slide' ) {
			echo '<div class="sb-carousel__progress"><div class="sb-carousel__progress-bar"></div></div>';
		}

		echo '</div>';
	}
}