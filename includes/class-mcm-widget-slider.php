<?php
/**
 * Elementor "Slider" widget: image and video slides (YouTube, Vimeo,
 * self-hosted), custom / full-screen / aspect-ratio height, full-width
 * stretch, several slides per view, fade or slide, autoplay, arrows,
 * bullets / fraction / progress, swipe, keyboard, Ken Burns.
 *
 * Loaded by MCM_Elementor::register() after elementor-widgets.php, so the
 * MCM_Elementor_Style trait is available.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image / video slider.
 */
class MCM_Widget_Slider extends \Elementor\Widget_Base {

	use MCM_Elementor_Style;

	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_slider';
	}

	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Slider (Images & Videos)', 'medical-college-manager' );
	}

	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-slides';
	}

	/**
	 * Elementor panel category.
	 */
	public function get_categories() {
		return array( 'medical-college-manager' );
	}

	/**
	 * Search keywords for the Elementor panel.
	 */
	public function get_keywords() {
		return array( 'slider', 'slides', 'carousel', 'hero', 'banner', 'video', 'gallery', 'image', 'medical-college-manager' );
	}

	/**
	 * Stylesheets this widget needs.
	 */
	public function get_style_depends() {
		return array( 'mcm-slider' );
	}

	/**
	 * Scripts this widget needs.
	 */
	public function get_script_depends() {
		return array( 'mcm-slider' );
	}

	/**
	 * Registered image sizes for the size picker.
	 *
	 * @return array
	 */
	private function size_options() {
		$out = array();
		foreach ( get_intermediate_image_sizes() as $size ) {
			$out[ $size ] = ucwords( str_replace( array( '_', '-' ), ' ', $size ) );
		}
		$out['full'] = __( 'Full (original)', 'medical-college-manager' );
		return $out;
	}

	/**
	 * Register the widget controls.
	 */
	protected function register_controls() {
		$this->register_slides_controls();
		$this->register_layout_controls();
		$this->register_behaviour_controls();
		$this->register_style_slides();
		$this->register_style_text();
		$this->register_style_navigation();
	}

	/**
	 * Content tab: the slides repeater.
	 */
	private function register_slides_controls() {
		$this->start_controls_section( 'mcm_sl_slides', array( 'label' => __( 'Slides', 'medical-college-manager' ) ) );

		$rep = new \Elementor\Repeater();
		$rep->start_controls_tabs( 'slide_tabs' );

		/* -- Media -- */
		$rep->start_controls_tab( 'slide_tab_media', array( 'label' => __( 'Media', 'medical-college-manager' ) ) );
		$rep->add_control(
			'type',
			array(
				'label'   => __( 'Slide type', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'toggle'  => false,
				'default' => 'image',
				'options' => array(
					'image' => array(
						'title' => __( 'Image', 'medical-college-manager' ),
						'icon'  => 'eicon-image',
					),
					'video' => array(
						'title' => __( 'Video', 'medical-college-manager' ),
						'icon'  => 'eicon-video-camera',
					),
				),
			)
		);
		$rep->add_control(
			'image',
			array(
				'label'     => __( 'Image', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::MEDIA,
				'dynamic'   => array( 'active' => true ),
				'default'   => array( 'url' => \Elementor\Utils::get_placeholder_image_src() ),
				'condition' => array( 'type' => 'image' ),
			)
		);
		$rep->add_control(
			'video_source',
			array(
				'label'     => __( 'Video source', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'youtube',
				'options'   => array(
					'youtube' => 'YouTube',
					'vimeo'   => 'Vimeo',
					'hosted'  => __( 'Self-hosted (MP4 / WebM)', 'medical-college-manager' ),
				),
				'condition' => array( 'type' => 'video' ),
			)
		);
		$rep->add_control(
			'video_url',
			array(
				'label'       => __( 'Video link', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
				'placeholder' => 'https://www.youtube.com/watch?v=…',
				'condition'   => array(
					'type'         => 'video',
					'video_source' => array( 'youtube', 'vimeo' ),
				),
			)
		);
		$rep->add_control(
			'video_file',
			array(
				'label'       => __( 'Video file', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::MEDIA,
				'media_types' => array( 'video' ),
				'condition'   => array(
					'type'         => 'video',
					'video_source' => 'hosted',
				),
			)
		);
		$rep->add_control(
			'video_play',
			array(
				'label'       => __( 'Video playback', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'background',
				'options'     => array(
					'background' => __( 'Background (muted loop, no controls)', 'medical-college-manager' ),
					'click'      => __( 'Play button (with sound and controls)', 'medical-college-manager' ),
				),
				'description' => __( 'Background videos start when the slide is shown and stop when it leaves.', 'medical-college-manager' ),
				'condition'   => array( 'type' => 'video' ),
			)
		);
		$rep->add_control(
			'video_thumb',
			array(
				'label'       => __( 'Thumbnail', 'medical-college-manager' ),
				'description' => __( 'Shown while the video loads, and when it is paused or has ended. Leave empty to use the video\'s own thumbnail (YouTube and Vimeo). For a self-hosted video, upload one.', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::MEDIA,
				'dynamic'     => array( 'active' => true ),
				'separator'   => 'before',
				'condition'   => array( 'type' => 'video' ),
			)
		);
		$rep->add_control(
			'thumb_paused',
			array(
				'label'        => __( 'Show thumbnail when paused', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'type' => 'video' ),
			)
		);
		$rep->add_control(
			'thumb_ended',
			array(
				'label'        => __( 'Show thumbnail when the video ends', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array(
					'type'       => 'video',
					'video_play' => 'click',
				),
			)
		);
		$rep->add_control(
			'bg_color',
			array(
				'label'     => __( 'Background colour', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} {{CURRENT_ITEM}} .mcm-sl-media' => 'background-color: {{VALUE}};' ),
			)
		);
		$rep->end_controls_tab();

		/* -- Content -- */
		$rep->start_controls_tab( 'slide_tab_content', array( 'label' => __( 'Content', 'medical-college-manager' ) ) );
		$rep->add_control(
			'heading',
			array(
				'label'       => __( 'Heading', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
			)
		);
		$rep->add_control(
			'description',
			array(
				'label'   => __( 'Description', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::TEXTAREA,
				'rows'    => 4,
				'dynamic' => array( 'active' => true ),
			)
		);
		$rep->add_control(
			'button_text',
			array(
				'label'   => __( 'Button text', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'dynamic' => array( 'active' => true ),
			)
		);
		$rep->add_control(
			'link',
			array(
				'label'       => __( 'Link', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'dynamic'     => array( 'active' => true ),
				'placeholder' => 'https://',
			)
		);
		$rep->end_controls_tab();

		/* -- Style (this slide only) -- */
		$rep->start_controls_tab( 'slide_tab_style', array( 'label' => __( 'Style', 'medical-college-manager' ) ) );
		$rep->add_control(
			'custom_style',
			array(
				'label'        => __( 'Custom style for this slide', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
			)
		);
		$rep->add_control(
			'slide_overlay',
			array(
				'label'     => __( 'Overlay colour', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} {{CURRENT_ITEM}} .mcm-sl-overlay' => 'background-color: {{VALUE}};' ),
				'condition' => array( 'custom_style' => 'yes' ),
			)
		);
		$rep->add_control(
			'slide_hpos',
			array(
				'label'                => __( 'Horizontal position', 'medical-college-manager' ),
				'type'                 => \Elementor\Controls_Manager::CHOOSE,
				'options'              => $this->hpos_options(),
				'selectors_dictionary' => $this->hpos_dictionary(),
				'selectors'            => array( '{{WRAPPER}} {{CURRENT_ITEM}} .mcm-sl-content' => 'justify-content: {{VALUE}};' ),
				'condition'            => array( 'custom_style' => 'yes' ),
			)
		);
		$rep->add_control(
			'slide_vpos',
			array(
				'label'                => __( 'Vertical position', 'medical-college-manager' ),
				'type'                 => \Elementor\Controls_Manager::CHOOSE,
				'options'              => $this->vpos_options(),
				'selectors_dictionary' => $this->vpos_dictionary(),
				'selectors'            => array( '{{WRAPPER}} {{CURRENT_ITEM}} .mcm-sl-content' => 'align-items: {{VALUE}};' ),
				'condition'            => array( 'custom_style' => 'yes' ),
			)
		);
		$rep->add_control(
			'slide_align',
			array(
				'label'     => __( 'Text alignment', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => $this->mcm_align_options(),
				'selectors' => array( '{{WRAPPER}} {{CURRENT_ITEM}} .mcm-sl-inner' => 'text-align: {{VALUE}};' ),
				'condition' => array( 'custom_style' => 'yes' ),
			)
		);
		$rep->add_control(
			'slide_text_color',
			array(
				'label'     => __( 'Text colour', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} {{CURRENT_ITEM}} .mcm-sl-heading, {{WRAPPER}} {{CURRENT_ITEM}} .mcm-sl-desc' => 'color: {{VALUE}};' ),
				'condition' => array( 'custom_style' => 'yes' ),
			)
		);
		$rep->end_controls_tab();
		$rep->end_controls_tabs();

		$placeholder = array( 'url' => \Elementor\Utils::get_placeholder_image_src() );
		$this->add_control(
			'slides',
			array(
				'label'       => __( 'Slides', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'title_field' => '{{{ heading || ( "video" === type ? "' . esc_js( __( 'Video slide', 'medical-college-manager' ) ) . '" : "' . esc_js( __( 'Image slide', 'medical-college-manager' ) ) . '" ) }}}',
				'default'     => array(
					array(
						'type'        => 'image',
						'image'       => $placeholder,
						'heading'     => __( 'Welcome to our college', 'medical-college-manager' ),
						'description' => __( 'Write a short line about this slide.', 'medical-college-manager' ),
						'button_text' => __( 'Learn more', 'medical-college-manager' ),
					),
					array(
						'type'        => 'image',
						'image'       => $placeholder,
						'heading'     => __( 'Admissions are open', 'medical-college-manager' ),
						'description' => __( 'Write a short line about this slide.', 'medical-college-manager' ),
						'button_text' => __( 'Apply now', 'medical-college-manager' ),
					),
					array(
						'type'    => 'image',
						'image'   => $placeholder,
						'heading' => __( 'Campus life', 'medical-college-manager' ),
					),
				),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Content tab: size and layout.
	 */
	private function register_layout_controls() {
		$this->start_controls_section( 'mcm_sl_layout', array( 'label' => __( 'Layout & Size', 'medical-college-manager' ) ) );

		$this->add_control(
			'height_mode',
			array(
				'label'   => __( 'Height', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'custom',
				'options' => array(
					'custom' => __( 'Custom height', 'medical-college-manager' ),
					'screen' => __( 'Fit to screen (full screen)', 'medical-college-manager' ),
					'ratio'  => __( 'Aspect ratio', 'medical-college-manager' ),
				),
			)
		);
		$this->add_responsive_control(
			'height',
			array(
				'label'          => __( 'Custom height', 'medical-college-manager' ),
				'type'           => \Elementor\Controls_Manager::SLIDER,
				'size_units'     => array( 'px', 'vh', 'em', 'rem' ),
				'range'          => array(
					'px' => array(
						'min' => 120,
						'max' => 1400,
					),
					'vh' => array(
						'min' => 10,
						'max' => 100,
					),
				),
				'default'        => array(
					'size' => 560,
					'unit' => 'px',
				),
				'tablet_default' => array(
					'size' => 440,
					'unit' => 'px',
				),
				'mobile_default' => array(
					'size' => 340,
					'unit' => 'px',
				),
				'selectors'      => array( '{{WRAPPER}} .mcm-sl' => '--mcm-sl-h: {{SIZE}}{{UNIT}};' ),
				'condition'      => array( 'height_mode' => 'custom' ),
			)
		);
		$this->add_responsive_control(
			'screen_offset',
			array(
				'label'       => __( 'Subtract from screen height', 'medical-college-manager' ),
				'description' => __( 'E.g. the height of a sticky header, so the slider fills exactly what is left.', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'size_units'  => array( 'px' ),
				'range'       => array(
					'px' => array(
						'min' => 0,
						'max' => 400,
					),
				),
				'selectors'   => array( '{{WRAPPER}} .mcm-sl' => '--mcm-sl-offset: {{SIZE}}{{UNIT}};' ),
				'condition'   => array( 'height_mode' => 'screen' ),
			)
		);
		$this->add_responsive_control(
			'ratio',
			array(
				'label'     => __( 'Aspect ratio', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => '16/9',
				'options'   => array(
					'21/9' => '21:9',
					'16/9' => '16:9',
					'3/2'  => '3:2',
					'4/3'  => '4:3',
					'1/1'  => '1:1',
					'3/4'  => '3:4',
					'9/16' => '9:16',
				),
				'selectors' => array( '{{WRAPPER}} .mcm-sl' => '--mcm-sl-ratio: {{VALUE}};' ),
				'condition' => array( 'height_mode' => 'ratio' ),
			)
		);

		$this->add_control(
			'stretch',
			array(
				'label'        => __( 'Stretch to full screen width', 'medical-college-manager' ),
				'description'  => __( 'Edge to edge, even inside a boxed section.', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'separator'    => 'before',
			)
		);
		$this->add_responsive_control(
			'width',
			array(
				'label'      => __( 'Custom width', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'vw' ),
				'range'      => array(
					'px' => array(
						'min' => 200,
						'max' => 2400,
					),
					'%'  => array(
						'min' => 10,
						'max' => 100,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .mcm-sl' => 'max-width: {{SIZE}}{{UNIT}};' ),
				'condition'  => array( 'stretch!' => 'yes' ),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'label'                => __( 'Alignment', 'medical-college-manager' ),
				'type'                 => \Elementor\Controls_Manager::CHOOSE,
				'options'              => $this->mcm_align_options(),
				'selectors_dictionary' => array(
					'left'   => 'margin-left: 0; margin-right: auto;',
					'center' => 'margin-left: auto; margin-right: auto;',
					'right'  => 'margin-left: auto; margin-right: 0;',
				),
				'selectors'            => array( '{{WRAPPER}} .mcm-sl' => '{{VALUE}}' ),
				'condition'            => array( 'stretch!' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'per_view',
			array(
				'label'          => __( 'Slides per view', 'medical-college-manager' ),
				'type'           => \Elementor\Controls_Manager::SELECT,
				'default'        => '1',
				'tablet_default' => '1',
				'mobile_default' => '1',
				'options'        => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				),
				'selectors'      => array( '{{WRAPPER}} .mcm-sl' => '--mcm-sl-per: {{VALUE}};' ),
				'separator'      => 'before',
			)
		);
		$this->add_control(
			'scroll',
			array(
				'label'   => __( 'Slides to move', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 6,
				'default' => 1,
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'label'      => __( 'Space between slides', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 80,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .mcm-sl' => '--mcm-sl-gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'image_size',
			array(
				'label'     => __( 'Image size', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'full',
				'options'   => $this->size_options(),
				'separator' => 'before',
			)
		);
		$this->add_responsive_control(
			'image_fit',
			array(
				'label'     => __( 'Image fit', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'cover',
				'options'   => array(
					'cover'   => __( 'Fill (crop to fit)', 'medical-college-manager' ),
					'contain' => __( 'Whole image (no crop)', 'medical-college-manager' ),
				),
				'selectors' => array( '{{WRAPPER}} .mcm-sl-img' => 'object-fit: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'image_pos',
			array(
				'label'     => __( 'Image focus', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'center center',
				'options'   => array(
					'center center' => __( 'Centre', 'medical-college-manager' ),
					'center top'    => __( 'Top', 'medical-college-manager' ),
					'center bottom' => __( 'Bottom', 'medical-college-manager' ),
					'left center'   => __( 'Left', 'medical-college-manager' ),
					'right center'  => __( 'Right', 'medical-college-manager' ),
				),
				'selectors' => array( '{{WRAPPER}} .mcm-sl-img' => 'object-position: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'heading_tag',
			array(
				'label'   => __( 'Heading HTML tag', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'h2',
				'options' => array(
					'h1'  => 'H1',
					'h2'  => 'H2',
					'h3'  => 'H3',
					'h4'  => 'H4',
					'div' => 'div',
					'p'   => 'p',
				),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Content tab: motion and navigation.
	 */
	private function register_behaviour_controls() {
		$this->start_controls_section( 'mcm_sl_behaviour', array( 'label' => __( 'Slider Options', 'medical-college-manager' ) ) );

		$this->add_control(
			'effect',
			array(
				'label'       => __( 'Transition', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'slide',
				'options'     => array(
					'slide' => __( 'Slide', 'medical-college-manager' ),
					'fade'  => __( 'Fade', 'medical-college-manager' ),
				),
				'description' => __( 'Fade is used when one slide is shown at a time.', 'medical-college-manager' ),
			)
		);
		$this->add_control(
			'speed',
			array(
				'label'   => __( 'Transition speed (ms)', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 0,
				'max'     => 5000,
				'step'    => 50,
				'default' => 600,
			)
		);
		$this->add_control(
			'autoplay',
			array(
				'label'        => __( 'Autoplay', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'separator'    => 'before',
			)
		);
		$this->add_control(
			'delay',
			array(
				'label'     => __( 'Time on each slide (ms)', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'min'       => 1000,
				'max'       => 30000,
				'step'      => 500,
				'default'   => 5000,
				'condition' => array( 'autoplay' => 'yes' ),
			)
		);
		$this->add_control(
			'pause_hover',
			array(
				'label'        => __( 'Pause on hover', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'autoplay' => 'yes' ),
			)
		);
		$this->add_control(
			'pause_interaction',
			array(
				'label'        => __( 'Stop after the visitor uses the slider', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'autoplay' => 'yes' ),
			)
		);
		$this->add_control(
			'pause_button',
			array(
				'label'        => __( 'Show pause / play button', 'medical-college-manager' ),
				'description'  => __( 'Recommended for accessibility: visitors must be able to stop moving content.', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'autoplay' => 'yes' ),
			)
		);
		$this->add_control(
			'loop',
			array(
				'label'        => __( 'Loop (back to the first slide)', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'navigation',
			array(
				'label'     => __( 'Navigation', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'both',
				'options'   => array(
					'both'   => __( 'Arrows and pagination', 'medical-college-manager' ),
					'arrows' => __( 'Arrows', 'medical-college-manager' ),
					'dots'   => __( 'Pagination', 'medical-college-manager' ),
					'none'   => __( 'None', 'medical-college-manager' ),
				),
				'separator' => 'before',
			)
		);
		$this->add_control(
			'pagination',
			array(
				'label'     => __( 'Pagination type', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'bullets',
				'options'   => array(
					'bullets'  => __( 'Dots', 'medical-college-manager' ),
					'fraction' => __( 'Numbers (2 / 5)', 'medical-college-manager' ),
					'progress' => __( 'Progress bar', 'medical-college-manager' ),
				),
				'condition' => array( 'navigation' => array( 'both', 'dots' ) ),
			)
		);
		$this->add_control(
			'swipe',
			array(
				'label'        => __( 'Swipe / drag', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'keyboard',
			array(
				'label'        => __( 'Keyboard arrows', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'kenburns',
			array(
				'label'        => __( 'Ken Burns effect (slow zoom)', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'separator'    => 'before',
			)
		);
		$this->add_control(
			'kenburns_dir',
			array(
				'label'     => __( 'Zoom direction', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'in',
				'options'   => array(
					'in'  => __( 'Zoom in', 'medical-college-manager' ),
					'out' => __( 'Zoom out', 'medical-college-manager' ),
				),
				'condition' => array( 'kenburns' => 'yes' ),
			)
		);
		$this->add_control(
			'content_anim',
			array(
				'label'   => __( 'Content animation', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'fade-up',
				'options' => array(
					'none'      => __( 'None', 'medical-college-manager' ),
					'fade'      => __( 'Fade in', 'medical-college-manager' ),
					'fade-up'   => __( 'Fade in up', 'medical-college-manager' ),
					'fade-down' => __( 'Fade in down', 'medical-college-manager' ),
					'zoom'      => __( 'Zoom in', 'medical-college-manager' ),
				),
			)
		);
		$this->add_control(
			'link_click',
			array(
				'label'   => __( 'Link applies to', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'button',
				'options' => array(
					'button' => __( 'Button only', 'medical-college-manager' ),
					'slide'  => __( 'Whole slide', 'medical-college-manager' ),
				),
			)
		);
		$this->add_control(
			'lightbox',
			array(
				'label'        => __( 'Open images in a lightbox', 'medical-college-manager' ),
				'description'  => __( 'Image slides without a link open full size when clicked.', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
			)
		);
		$this->add_control(
			'bg_video_mobile',
			array(
				'label'        => __( 'Play background videos on phones', 'medical-college-manager' ),
				'description'  => __( 'Off: phones show the cover image instead (saves mobile data).', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'aria_label',
			array(
				'label'       => __( 'Name for screen readers', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Slider', 'medical-college-manager' ),
				'placeholder' => __( 'e.g. Campus highlights', 'medical-college-manager' ),
				'separator'   => 'before',
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Style tab: slides, overlay and content box.
	 */
	private function register_style_slides() {
		$this->mcm_style_section( 'mcm_sl_style_slides', __( 'Slides', 'medical-college-manager' ) );
		$this->add_control(
			'overlay',
			array(
				'label'     => __( 'Overlay colour', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => 'rgba(0,0,0,0.35)',
				'selectors' => array( '{{WRAPPER}} .mcm-sl-overlay' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'slide_bg',
			array(
				'label'     => __( 'Slide background colour', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mcm-sl-media' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'content_hpos',
			array(
				'label'                => __( 'Content: horizontal position', 'medical-college-manager' ),
				'type'                 => \Elementor\Controls_Manager::CHOOSE,
				'options'              => $this->hpos_options(),
				'selectors_dictionary' => $this->hpos_dictionary(),
				'default'              => 'center',
				'selectors'            => array( '{{WRAPPER}} .mcm-sl-content' => 'justify-content: {{VALUE}};' ),
				'separator'            => 'before',
			)
		);
		$this->add_responsive_control(
			'content_vpos',
			array(
				'label'                => __( 'Content: vertical position', 'medical-college-manager' ),
				'type'                 => \Elementor\Controls_Manager::CHOOSE,
				'options'              => $this->vpos_options(),
				'selectors_dictionary' => $this->vpos_dictionary(),
				'default'              => 'middle',
				'selectors'            => array( '{{WRAPPER}} .mcm-sl-content' => 'align-items: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'content_align',
			array(
				'label'     => __( 'Text alignment', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => $this->mcm_align_options(),
				'default'   => 'center',
				'selectors' => array( '{{WRAPPER}} .mcm-sl-inner' => 'text-align: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'content_width',
			array(
				'label'      => __( 'Content width', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array(
						'min' => 120,
						'max' => 1600,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .mcm-sl-inner' => 'max-width: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->mcm_dimensions( 'content_padding', __( 'Content padding', 'medical-college-manager' ), '{{WRAPPER}} .mcm-sl-content', 'padding' );
		$this->mcm_heading( 'sl_frame_heading', __( 'Slider frame', 'medical-college-manager' ) );
		$this->mcm_box_style(
			'sl_frame',
			'.mcm-sl-slide',
			array(
				'hover'   => false,
				'padding' => false,
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Style tab: heading, description, button.
	 */
	private function register_style_text() {
		$this->mcm_style_section( 'mcm_sl_style_heading', __( 'Heading', 'medical-college-manager' ) );
		$this->mcm_text_style( 'sl_h', '.mcm-sl-heading', array( 'spacing' => true ) );
		$this->end_controls_section();

		$this->mcm_style_section( 'mcm_sl_style_desc', __( 'Description', 'medical-college-manager' ) );
		$this->mcm_text_style( 'sl_d', '.mcm-sl-desc', array( 'spacing' => true ) );
		$this->end_controls_section();

		$this->mcm_style_section( 'mcm_sl_style_btn', __( 'Button', 'medical-college-manager' ) );
		$this->mcm_button_style( 'sl_btn', '.mcm-sl-btn' );
		$this->end_controls_section();
	}

	/**
	 * Style tab: arrows, pagination, pause button.
	 */
	private function register_style_navigation() {
		$this->mcm_style_section( 'mcm_sl_style_nav', __( 'Navigation', 'medical-college-manager' ) );

		$this->mcm_heading( 'sl_arrows_heading', __( 'Arrows', 'medical-college-manager' ) );
		$this->add_responsive_control(
			'arrows_show',
			array(
				'label'                => __( 'Show arrows', 'medical-college-manager' ),
				'type'                 => \Elementor\Controls_Manager::SELECT,
				'options'              => array(
					''     => __( 'Default', 'medical-college-manager' ),
					'show' => __( 'Show', 'medical-college-manager' ),
					'hide' => __( 'Hide', 'medical-college-manager' ),
				),
				'selectors_dictionary' => array(
					'show' => 'display: flex;',
					'hide' => 'display: none;',
				),
				'selectors'            => array( '{{WRAPPER}} .mcm-sl-arrow' => '{{VALUE}}' ),
			)
		);
		$this->add_control(
			'arrows_pos',
			array(
				'label'   => __( 'Arrows position', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'inside',
				'options' => array(
					'inside'  => __( 'Inside', 'medical-college-manager' ),
					'outside' => __( 'Outside', 'medical-college-manager' ),
				),
			)
		);
		$this->add_responsive_control(
			'arrows_size',
			array(
				'label'      => __( 'Button size', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 24,
						'max' => 96,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .mcm-sl' => '--mcm-sl-arrow: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'arrows_offset',
			array(
				'label'      => __( 'Distance from edge', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => -80,
						'max' => 120,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .mcm-sl' => '--mcm-sl-arrow-x: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->start_controls_tabs( 'arrows_tabs' );
		foreach ( array(
			'normal' => array( __( 'Normal', 'medical-college-manager' ), '{{WRAPPER}} .mcm-sl-arrow' ),
			'hover'  => array( __( 'Hover', 'medical-college-manager' ), '{{WRAPPER}} .mcm-sl-arrow:hover, {{WRAPPER}} .mcm-sl-arrow:focus-visible' ),
		) as $state => $cfg ) {
			$this->start_controls_tab( 'arrows_tab_' . $state, array( 'label' => $cfg[0] ) );
			$this->add_control(
				'arrows_color_' . $state,
				array(
					'label'     => __( 'Icon colour', 'medical-college-manager' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $cfg[1] => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'arrows_bg_' . $state,
				array(
					'label'     => __( 'Background colour', 'medical-college-manager' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $cfg[1] => 'background-color: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
		$this->add_responsive_control(
			'arrows_radius',
			array(
				'label'      => __( 'Border radius', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .mcm-sl-arrow' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->mcm_heading( 'sl_pager_heading', __( 'Pagination', 'medical-college-manager' ) );
		$this->add_responsive_control(
			'pager_show',
			array(
				'label'                => __( 'Show pagination', 'medical-college-manager' ),
				'type'                 => \Elementor\Controls_Manager::SELECT,
				'options'              => array(
					''     => __( 'Default', 'medical-college-manager' ),
					'show' => __( 'Show', 'medical-college-manager' ),
					'hide' => __( 'Hide', 'medical-college-manager' ),
				),
				'selectors_dictionary' => array(
					'show' => 'display: flex;',
					'hide' => 'display: none;',
				),
				'selectors'            => array( '{{WRAPPER}} .mcm-sl-pager' => '{{VALUE}}' ),
			)
		);
		$this->add_control(
			'pager_pos',
			array(
				'label'   => __( 'Pagination position', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'inside',
				'options' => array(
					'inside'  => __( 'Inside (over the slide)', 'medical-college-manager' ),
					'outside' => __( 'Below the slider', 'medical-college-manager' ),
				),
			)
		);
		$this->add_responsive_control(
			'dot_size',
			array(
				'label'      => __( 'Dot size', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 4,
						'max' => 30,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .mcm-sl' => '--mcm-sl-dot: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'dot_color',
			array(
				'label'     => __( 'Colour', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mcm-sl' => '--mcm-sl-dot-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'dot_active',
			array(
				'label'     => __( 'Active colour', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mcm-sl' => '--mcm-sl-dot-active: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'      => 'fraction_typo',
				'label'     => __( 'Numbers typography', 'medical-college-manager' ),
				'selector'  => '{{WRAPPER}} .mcm-sl-fraction',
				'condition' => array( 'pagination' => 'fraction' ),
			)
		);

		$this->mcm_heading( 'sl_pause_heading', __( 'Pause button', 'medical-college-manager' ) );
		$this->add_control(
			'pause_color',
			array(
				'label'     => __( 'Icon colour', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mcm-sl-pause' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'pause_bg',
			array(
				'label'     => __( 'Background colour', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mcm-sl-pause' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Left / centre / right as flex positions.
	 *
	 * @return array
	 */
	private function hpos_options() {
		return array(
			'left'   => array(
				'title' => __( 'Left', 'medical-college-manager' ),
				'icon'  => 'eicon-h-align-left',
			),
			'center' => array(
				'title' => __( 'Centre', 'medical-college-manager' ),
				'icon'  => 'eicon-h-align-center',
			),
			'right'  => array(
				'title' => __( 'Right', 'medical-college-manager' ),
				'icon'  => 'eicon-h-align-right',
			),
		);
	}

	/**
	 * CSS values for hpos_options().
	 *
	 * @return array
	 */
	private function hpos_dictionary() {
		return array(
			'left'   => 'flex-start',
			'center' => 'center',
			'right'  => 'flex-end',
		);
	}

	/**
	 * Top / middle / bottom choices.
	 *
	 * @return array
	 */
	private function vpos_options() {
		return array(
			'top'    => array(
				'title' => __( 'Top', 'medical-college-manager' ),
				'icon'  => 'eicon-v-align-top',
			),
			'middle' => array(
				'title' => __( 'Middle', 'medical-college-manager' ),
				'icon'  => 'eicon-v-align-middle',
			),
			'bottom' => array(
				'title' => __( 'Bottom', 'medical-college-manager' ),
				'icon'  => 'eicon-v-align-bottom',
			),
		);
	}

	/**
	 * CSS values for vpos_options().
	 *
	 * @return array
	 */
	private function vpos_dictionary() {
		return array(
			'top'    => 'flex-start',
			'middle' => 'center',
			'bottom' => 'flex-end',
		);
	}

	/**
	 * Parse a slide's video settings into a safe descriptor.
	 *
	 * Only an ID (YouTube / Vimeo) or a vetted URL (self-hosted) leaves this
	 * function; the browser builds the embed URL from the ID, never from the
	 * raw text an editor typed.
	 *
	 * @param array $slide Repeater item.
	 * @return array|null { type, id|src } or null when nothing usable.
	 */
	public static function video_info( $slide ) {
		$source = isset( $slide['video_source'] ) ? $slide['video_source'] : 'youtube';
		if ( 'hosted' === $source ) {
			$src = '';
			if ( ! empty( $slide['video_file']['id'] ) ) {
				$src = wp_get_attachment_url( (int) $slide['video_file']['id'] );
			}
			if ( ! $src && ! empty( $slide['video_file']['url'] ) ) {
				$src = $slide['video_file']['url'];
			}
			$src = $src ? esc_url_raw( $src, array( 'http', 'https' ) ) : '';
			return $src ? array(
				'type' => 'hosted',
				'src'  => $src,
			) : null;
		}
		$url = isset( $slide['video_url'] ) ? trim( (string) $slide['video_url'] ) : '';
		if ( '' === $url ) {
			return null;
		}
		if ( 'vimeo' === $source ) {
			if ( preg_match( '~vimeo\.com/(?:.*?/)?(?:video/)?(\d{5,12})~i', $url, $m ) || preg_match( '~^(\d{5,12})$~', $url, $m ) ) {
				return array(
					'type' => 'vimeo',
					'id'   => $m[1],
				);
			}
			return null;
		}
		if ( preg_match( '~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})~i', $url, $m ) || preg_match( '~^([A-Za-z0-9_-]{11})$~', $url, $m ) ) {
			return array(
				'type' => 'youtube',
				'id'   => $m[1],
			);
		}
		return null;
	}

	/**
	 * A media control value that holds a real image (not Elementor's
	 * placeholder).
	 *
	 * @param mixed $media Media control value.
	 * @return array|null { id, url } or null.
	 */
	private static function real_media( $media ) {
		if ( ! is_array( $media ) ) {
			return null;
		}
		$id  = ! empty( $media['id'] ) ? (int) $media['id'] : 0;
		$url = ! empty( $media['url'] ) ? (string) $media['url'] : '';
		if ( ! $id && ( '' === $url || \Elementor\Utils::get_placeholder_image_src() === $url ) ) {
			return null;
		}
		return array(
			'id'  => $id,
			'url' => $url,
		);
	}

	/**
	 * Vimeo's own thumbnail, asked for once by the server (not the visitor's
	 * browser) and cached for a week; a failed lookup is retried after an hour.
	 *
	 * @param string $id Vimeo video ID.
	 * @return string Thumbnail URL or ''.
	 */
	public static function vimeo_thumb( $id ) {
		$id = preg_replace( '/\D/', '', (string) $id );
		if ( '' === $id ) {
			return '';
		}
		$key    = 'mcm_vimeo_thumb_' . $id;
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return (string) $cached;
		}
		$thumb = '';
		$res   = wp_safe_remote_get(
			'https://vimeo.com/api/oembed.json?width=1280&url=' . rawurlencode( 'https://vimeo.com/' . $id ),
			array( 'timeout' => 5 )
		);
		if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
			$data = json_decode( wp_remote_retrieve_body( $res ), true );
			$url  = is_array( $data ) && ! empty( $data['thumbnail_url'] ) ? (string) $data['thumbnail_url'] : '';
			$host = (string) wp_parse_url( $url, PHP_URL_HOST );
			if ( 0 === strpos( $url, 'https://' ) && preg_match( '/(^|\.)vimeocdn\.com$/', $host ) ) {
				$thumb = esc_url_raw( $url );
			}
		}
		set_transient( $key, $thumb, $thumb ? WEEK_IN_SECONDS : HOUR_IN_SECONDS );
		return $thumb;
	}

	/**
	 * The thumbnail for a video slide: the uploaded Thumbnail, else an image
	 * set on the slide before it was a video, else the provider's own.
	 *
	 * @param array      $slide Repeater item.
	 * @param array|null $video Video descriptor (null when the link is unusable).
	 * @return array|null { id, url, fallback } (id 0 for a remote image).
	 */
	public static function video_thumb( $slide, $video ) {
		$own = self::real_media( isset( $slide['video_thumb'] ) ? $slide['video_thumb'] : null );
		if ( ! $own ) {
			$own = self::real_media( isset( $slide['legacy_image'] ) ? $slide['legacy_image'] : null );
		}
		if ( $own ) {
			return array_merge( $own, array( 'fallback' => '' ) );
		}
		if ( ! $video ) {
			return null; // No usable video link: only an uploaded thumbnail can show.
		}
		if ( 'youtube' === $video['type'] ) {
			$base = 'https://i.ytimg.com/vi/' . rawurlencode( $video['id'] ) . '/';
			// Full HD where YouTube has one; the script swaps in hqdefault when not.
			return array(
				'id'       => 0,
				'url'      => $base . 'maxresdefault.jpg',
				'fallback' => $base . 'hqdefault.jpg',
			);
		}
		if ( 'vimeo' === $video['type'] ) {
			$url = self::vimeo_thumb( $video['id'] );
			return $url ? array(
				'id'       => 0,
				'url'      => $url,
				'fallback' => '',
			) : null;
		}
		return null;
	}

	/**
	 * The image (or video thumbnail) for a slide.
	 *
	 * @param array      $slide Repeater item.
	 * @param string     $size  Image size.
	 * @param bool       $first Is this the first slide (load eagerly).
	 * @param array|null $thumb For a video slide: video_thumb() result.
	 * @param bool       $video Is this a video slide.
	 * @return string HTML.
	 */
	private function slide_image( $slide, $size, $first, $thumb, $video ) {
		$attrs = array(
			'class'    => 'mcm-sl-img' . ( $video ? ' mcm-sl-poster' : '' ),
			'loading'  => $first ? 'eager' : 'lazy',
			'decoding' => 'async',
		);
		if ( $first ) {
			$attrs['fetchpriority'] = 'high';
		}
		if ( $video ) {
			if ( ! $thumb ) {
				return '';
			}
			$attrs['alt'] = ''; // Decorative: the play button and slide label name the video.
			$id           = $thumb['id'];
			$url          = $thumb['url'];
			if ( '' !== $thumb['fallback'] ) {
				$attrs['data-fallback'] = $thumb['fallback'];
			}
		} else {
			$img = self::real_media( isset( $slide['image'] ) ? $slide['image'] : null );
			$id  = $img ? $img['id'] : 0;
			$url = isset( $slide['image']['url'] ) ? (string) $slide['image']['url'] : '';
		}
		if ( $id && wp_attachment_is_image( $id ) ) {
			return wp_get_attachment_image( $id, $size, false, $attrs );
		}
		if ( '' === $url ) {
			return '';
		}
		$html = '<img src="' . esc_url( $url ) . '" alt=""';
		unset( $attrs['alt'] );
		foreach ( $attrs as $k => $v ) {
			$html .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
		return $html . ' />';
	}

	/**
	 * Inline SVG icons (static markup).
	 *
	 * @param string $name prev | next | play | pause | resume.
	 * @return string
	 */
	private static function icon( $name ) {
		$paths = array(
			'prev'   => '<path d="M15 18l-6-6 6-6"/>',
			'next'   => '<path d="M9 18l6-6-6-6"/>',
			'play'   => '<path d="M8 5.5v13l11-6.5z" fill="currentColor" stroke="none"/>',
			'pause'  => '<path d="M9 5v14M15 5v14"/>',
			'resume' => '<path d="M8 5.5v13l11-6.5z" fill="currentColor" stroke="none"/>',
		);
		return '<svg class="mcm-sl-ico mcm-sl-ico--' . $name . '" aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">' . $paths[ $name ] . '</svg>';
	}

	/**
	 * Render the slider.
	 */
	protected function render() {
		$s      = $this->get_settings_for_display();
		$slides = isset( $s['slides'] ) && is_array( $s['slides'] ) ? $s['slides'] : array();
		if ( ! $slides ) {
			if ( $this->mcm_in_editor() ) {
				echo '<p class="mcm-sl-empty">' . esc_html__( 'Add slides in the Slides section.', 'medical-college-manager' ) . '</p>';
			}
			return;
		}

		$yes      = function ( $key, $fallback = '' ) use ( $s ) {
			$v = isset( $s[ $key ] ) ? $s[ $key ] : $fallback;
			return 'yes' === $v;
		};
		$pick     = function ( $key, $allowed, $fallback ) use ( $s ) {
			$v = isset( $s[ $key ] ) ? (string) $s[ $key ] : $fallback;
			return in_array( $v, $allowed, true ) ? $v : $fallback;
		};
		$num      = function ( $key, $min, $max, $fallback ) use ( $s ) {
			$v = isset( $s[ $key ] ) && '' !== $s[ $key ] ? (int) $s[ $key ] : $fallback;
			return max( $min, min( $max, $v ) );
		};
		$total    = count( $slides );
		$height   = $pick( 'height_mode', array( 'custom', 'screen', 'ratio' ), 'custom' );
		$nav      = $pick( 'navigation', array( 'both', 'arrows', 'dots', 'none' ), 'both' );
		$pager    = $pick( 'pagination', array( 'bullets', 'fraction', 'progress' ), 'bullets' );
		$effect   = $pick( 'effect', array( 'slide', 'fade' ), 'slide' );
		$anim     = $pick( 'content_anim', array( 'none', 'fade', 'fade-up', 'fade-down', 'zoom' ), 'fade-up' );
		$tag      = $pick( 'heading_tag', array( 'h1', 'h2', 'h3', 'h4', 'div', 'p' ), 'h2' );
		$size     = isset( $s['image_size'] ) ? sanitize_key( $s['image_size'] ) : 'full';
		$whole    = 'slide' === $pick( 'link_click', array( 'button', 'slide' ), 'button' );
		$autoplay = $yes( 'autoplay' ) && $total > 1;
		$arrows   = $total > 1 && in_array( $nav, array( 'both', 'arrows' ), true );
		$dots     = $total > 1 && in_array( $nav, array( 'both', 'dots' ), true );
		$label    = isset( $s['aria_label'] ) && '' !== trim( (string) $s['aria_label'] ) ? (string) $s['aria_label'] : __( 'Slider', 'medical-college-manager' );

		$classes = array(
			'mcm-sl',
			'mcm-sl--h-' . $height,
			'mcm-sl--fx-' . $effect,
			'mcm-sl--anim-' . $anim,
			'mcm-sl--arrows-' . $pick( 'arrows_pos', array( 'inside', 'outside' ), 'inside' ),
			'mcm-sl--pager-' . $pick( 'pager_pos', array( 'inside', 'outside' ), 'inside' ),
			'mcm-sl--pager-' . $pager,
		);
		if ( $yes( 'stretch' ) ) {
			$classes[] = 'mcm-sl--stretch';
		}
		if ( $yes( 'kenburns' ) ) {
			$classes[] = 'mcm-sl--kb mcm-sl--kb-' . $pick( 'kenburns_dir', array( 'in', 'out' ), 'in' );
		}

		$config = array(
			'effect'     => $effect,
			'speed'      => $num( 'speed', 0, 5000, 600 ),
			'autoplay'   => $autoplay,
			'delay'      => $num( 'delay', 1000, 30000, 5000 ),
			'pauseHover' => $yes( 'pause_hover', 'yes' ),
			'pauseOnUse' => $yes( 'pause_interaction', 'yes' ),
			'loop'       => $yes( 'loop', 'yes' ),
			'scroll'     => $num( 'scroll', 1, 6, 1 ),
			'pager'      => $dots ? $pager : '',
			'swipe'      => $yes( 'swipe', 'yes' ),
			'keyboard'   => $yes( 'keyboard', 'yes' ),
			'bgMobile'   => $yes( 'bg_video_mobile', 'yes' ),
			'stretch'    => $yes( 'stretch' ),
			'i18n'       => array(
				/* translators: %d: slide number. */
				'goTo'    => __( 'Go to slide %d', 'medical-college-manager' ),
				/* translators: 1: slide number, 2: number of slides. */
				'page'    => __( 'Slide %1$d of %2$d', 'medical-college-manager' ),
				'pause'   => __( 'Pause slider', 'medical-college-manager' ),
				'play'    => __( 'Play slider', 'medical-college-manager' ),
				'video'   => __( 'Video', 'medical-college-manager' ),
				'playing' => __( 'Playing video', 'medical-college-manager' ),
			),
		);

		$out  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" role="region" aria-roledescription="' . esc_attr__( 'carousel', 'medical-college-manager' ) . '" aria-label="' . esc_attr( $label ) . '" data-mcm-slider="' . esc_attr( wp_json_encode( $config ) ) . '">';
		$out .= '<div class="mcm-sl-viewport" aria-live="' . ( $autoplay ? 'off' : 'polite' ) . '">';
		$out .= '<div class="mcm-sl-track">';

		// A video slide may still hold the cover image set before it had its own
		// Thumbnail. Elementor drops hidden controls from the display settings,
		// so take that image from the raw settings.
		$raw = array();
		foreach ( (array) $this->get_settings( 'slides' ) as $item ) {
			if ( isset( $item['_id'], $item['image'] ) ) {
				$raw[ $item['_id'] ] = $item['image'];
			}
		}
		foreach ( $slides as $i => $slide ) {
			if ( isset( $slide['type'], $slide['_id'] ) && 'video' === $slide['type'] && isset( $raw[ $slide['_id'] ] ) ) {
				$slide['legacy_image'] = $raw[ $slide['_id'] ];
			}
			$out .= $this->render_slide( $slide, $i, $total, $size, $tag, $whole, $yes( 'lightbox' ) );
		}
		$out .= '</div></div>';

		if ( $arrows ) {
			$out .= '<button type="button" class="mcm-sl-arrow mcm-sl-prev" aria-label="' . esc_attr__( 'Previous slide', 'medical-college-manager' ) . '">' . self::icon( 'prev' ) . '</button>';
			$out .= '<button type="button" class="mcm-sl-arrow mcm-sl-next" aria-label="' . esc_attr__( 'Next slide', 'medical-college-manager' ) . '">' . self::icon( 'next' ) . '</button>';
		}
		if ( $dots || ( $autoplay && $yes( 'pause_button', 'yes' ) ) ) {
			$out .= '<div class="mcm-sl-controls">';
			if ( $autoplay && $yes( 'pause_button', 'yes' ) ) {
				$out .= '<button type="button" class="mcm-sl-pause" aria-label="' . esc_attr__( 'Pause slider', 'medical-college-manager' ) . '">' . self::icon( 'pause' ) . self::icon( 'resume' ) . '</button>';
			}
			if ( $dots ) {
				$out .= '<div class="mcm-sl-pager mcm-sl-pager--' . esc_attr( $pager ) . '"></div>';
			}
			$out .= '</div>';
		}
		$out .= '</div>';

		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
	}

	/**
	 * One slide.
	 *
	 * @param array  $slide    Repeater item.
	 * @param int    $i        Index.
	 * @param int    $total    Number of slides.
	 * @param string $size     Image size.
	 * @param string $tag      Heading tag.
	 * @param bool   $whole    Link the whole slide.
	 * @param bool   $lightbox Lightbox for unlinked image slides.
	 * @return string
	 */
	private function render_slide( $slide, $i, $total, $size, $tag, $whole, $lightbox ) {
		$is_video = isset( $slide['type'] ) && 'video' === $slide['type'];
		$video    = $is_video ? self::video_info( $slide ) : null;
		$mode     = isset( $slide['video_play'] ) && 'click' === $slide['video_play'] ? 'click' : 'background';
		$heading  = isset( $slide['heading'] ) ? trim( (string) $slide['heading'] ) : '';
		$desc     = isset( $slide['description'] ) ? trim( (string) $slide['description'] ) : '';
		$btn      = isset( $slide['button_text'] ) ? trim( (string) $slide['button_text'] ) : '';
		$link     = isset( $slide['link'] ) && is_array( $slide['link'] ) && ! empty( $slide['link']['url'] ) ? $slide['link'] : null;
		$item_id  = isset( $slide['_id'] ) ? sanitize_html_class( $slide['_id'] ) : (string) $i;

		$cls = 'mcm-sl-slide elementor-repeater-item-' . $item_id . ' mcm-sl-slide--' . ( $video ? 'video mcm-sl-slide--video-' . $mode : 'image' );
		if ( 0 === $i ) {
			$cls .= ' is-active';
		}
		/* translators: 1: slide number, 2: number of slides. */
		$aria = sprintf( __( 'Slide %1$d of %2$d', 'medical-college-manager' ), $i + 1, $total );
		$html = '<div class="' . esc_attr( $cls ) . '" role="group" aria-roledescription="' . esc_attr__( 'slide', 'medical-college-manager' ) . '" aria-label="' . esc_attr( $aria ) . '">';

		$thumb = $is_video ? self::video_thumb( $slide, $video ) : null;
		$html .= '<div class="mcm-sl-media">' . $this->slide_image( $slide, $size, 0 === $i, $thumb, $is_video );
		if ( $video ) {
			$poster = '';
			if ( $thumb ) {
				$poster = $thumb['id'] ? (string) wp_get_attachment_image_url( $thumb['id'], $size ) : $thumb['url'];
			}
			$data  = array_merge(
				$video,
				array(
					'mode'        => $mode,
					'poster'      => $poster ? esc_url_raw( $poster ) : '',
					'thumbPaused' => ! isset( $slide['thumb_paused'] ) || 'yes' === $slide['thumb_paused'],
					'thumbEnded'  => ! isset( $slide['thumb_ended'] ) || 'yes' === $slide['thumb_ended'],
				)
			);
			$html .= '<div class="mcm-sl-video" data-video="' . esc_attr( wp_json_encode( $data ) ) . '"></div>';
			$html .= '<span class="mcm-sl-spinner" aria-hidden="true"></span>';
		} elseif ( $is_video && $this->mcm_in_editor() ) {
			$html .= '<p class="mcm-sl-empty">' . esc_html__( 'Add a YouTube or Vimeo link, or choose a video file.', 'medical-college-manager' ) . '</p>';
		}
		$html .= '</div><div class="mcm-sl-overlay"></div>';

		$link_attrs = '';
		if ( $link ) {
			$link_attrs = ' href="' . esc_url( $link['url'] ) . '"';
			if ( ! empty( $link['is_external'] ) ) {
				$link_attrs .= ' target="_blank"';
			}
			$rel = array();
			if ( ! empty( $link['nofollow'] ) ) {
				$rel[] = 'nofollow';
			}
			if ( ! empty( $link['is_external'] ) ) {
				$rel[] = 'noopener';
			}
			if ( $rel ) {
				$link_attrs .= ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"';
			}
		}

		if ( '' !== $heading || '' !== $desc || '' !== $btn ) {
			$html .= '<div class="mcm-sl-content"><div class="mcm-sl-inner">';
			if ( '' !== $heading ) {
				$html .= '<' . $tag . ' class="mcm-sl-heading">' . esc_html( $heading ) . '</' . $tag . '>';
			}
			if ( '' !== $desc ) {
				$html .= '<div class="mcm-sl-desc">' . nl2br( esc_html( $desc ) ) . '</div>';
			}
			if ( '' !== $btn ) {
				if ( $link && ! $whole ) {
					$html .= '<a class="mcm-sl-btn"' . $link_attrs . '>' . esc_html( $btn ) . '</a>';
				} else {
					$html .= '<span class="mcm-sl-btn">' . esc_html( $btn ) . '</span>';
				}
			}
			$html .= '</div></div>';
		}

		if ( $video && 'click' === $mode ) {
			/* translators: %s: slide heading or "Video". */
			$play  = sprintf( __( 'Play video: %s', 'medical-college-manager' ), '' !== $heading ? $heading : __( 'Video', 'medical-college-manager' ) );
			$html .= '<button type="button" class="mcm-sl-play" aria-label="' . esc_attr( $play ) . '">' . self::icon( 'play' ) . '</button>';
		} elseif ( $link && ( $whole || '' === $btn ) ) {
			$name  = '' !== $heading ? $heading : $btn;
			$html .= '<a class="mcm-sl-link"' . $link_attrs . ( '' !== $name ? ' aria-label="' . esc_attr( $name ) . '"' : '' ) . '></a>';
		} elseif ( ! $is_video && ! $link && $lightbox && self::real_media( isset( $slide['image'] ) ? $slide['image'] : null ) ) {
			$full  = ! empty( $slide['image']['id'] ) ? wp_get_attachment_image_url( (int) $slide['image']['id'], 'full' ) : $slide['image']['url'];
			$name  = '' !== $heading ? $heading : __( 'Open image', 'medical-college-manager' );
			$html .= '<a class="mcm-sl-link" href="' . esc_url( $full ) . '" data-elementor-open-lightbox="yes" data-elementor-lightbox-slideshow="' . esc_attr( 'mcm-sl-' . $this->get_id() ) . '" aria-label="' . esc_attr( $name ) . '"></a>';
		}

		return $html . '</div>';
	}
}
