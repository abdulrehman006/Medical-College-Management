<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Holds every widget class; see the note below.
/**
 * Elementor widget classes. Required only from within the Elementor
 * registration hook, so Elementor's base class is guaranteed to exist.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
 * -- The widget classes are small declarations sharing two base classes and
 * are loaded together, only from Elementor's widget registration hook, so
 * sites without Elementor never parse them. Kept in one file on purpose.
 */

// Safety: never declare these unless Elementor's base class is loaded.
if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * Shared Style-tab builders, used by every widget so each one offers the same
 * Elementor-standard design options (typography, colours, background,
 * border, radius, padding, shadow, Normal/Hover). Every control is scoped to
 * {{WRAPPER}}: it styles this widget only and overrides the global design.
 */
trait MCM_Elementor_Style {

	/**
	 * Prefix each selector with {{WRAPPER}}, optionally adding a suffix (":hover").
	 *
	 * @param string|string[] $selectors Selectors inside the widget.
	 * @param string          $suffix    Appended to each selector.
	 * @return string
	 */
	protected function mcm_sel( $selectors, $suffix = '' ) {
		$out = array();
		foreach ( (array) $selectors as $s ) {
			$out[] = '{{WRAPPER}} ' . $s . $suffix;
		}
		return implode( ', ', $out );
	}

	/**
	 * Open a Style-tab section.
	 *
	 * @param string $id    Section ID.
	 * @param string $label Section label.
	 * @param array  $extra Extra section arguments (condition, conditions).
	 */
	protected function mcm_style_section( $id, $label, $extra = array() ) {
		$this->start_controls_section(
			$id,
			array_merge(
				array(
					'label' => $label,
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				),
				$extra
			)
		);
	}

	/**
	 * Responsive four-side dimensions control (padding, margin, radius).
	 *
	 * @param string $id        Control ID.
	 * @param string $label     Label.
	 * @param string $selector  Full selector (already wrapped).
	 * @param string $property  CSS property.
	 */
	protected function mcm_dimensions( $id, $label, $selector, $property ) {
		$this->add_responsive_control(
			$id,
			array(
				'label'      => $label,
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( $selector => $property . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
	}

	/**
	 * Small heading inside a section.
	 *
	 * @param string $id    Control ID.
	 * @param string $label Heading text.
	 */
	protected function mcm_heading( $id, $label ) {
		$this->add_control(
			$id,
			array(
				'label'     => $label,
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);
	}

	/**
	 * Typography, colour and (optionally) hover colour, alignment and bottom
	 * spacing for one text element.
	 *
	 * @param string          $id        Control ID prefix.
	 * @param string|string[] $selectors Element selectors.
	 * @param array           $opts      hover (selectors), align, spacing, heading.
	 */
	protected function mcm_text_style( $id, $selectors, $opts = array() ) {
		$opts = wp_parse_args(
			$opts,
			array(
				'heading' => '',
				'hover'   => array(),
				'align'   => false,
				'spacing' => false,
				'color'   => true,
			)
		);
		$sel  = $this->mcm_sel( $selectors );
		if ( '' !== $opts['heading'] ) {
			$this->mcm_heading( $id . '_heading', $opts['heading'] );
		}
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => $id . '_typo',
				'selector' => $sel,
			)
		);
		if ( $opts['color'] ) {
			$this->add_control(
				$id . '_color',
				array(
					'label'     => __( 'Colour', 'medical-college-manager' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $sel => 'color: {{VALUE}};' ),
				)
			);
		}
		if ( $opts['hover'] ) {
			$this->add_control(
				$id . '_hover',
				array(
					'label'     => __( 'Hover colour', 'medical-college-manager' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $this->mcm_sel( $opts['hover'] ) => 'color: {{VALUE}};' ),
				)
			);
		}
		if ( $opts['align'] ) {
			$this->add_responsive_control(
				$id . '_align',
				array(
					'label'     => __( 'Alignment', 'medical-college-manager' ),
					'type'      => \Elementor\Controls_Manager::CHOOSE,
					'options'   => $this->mcm_align_options(),
					'selectors' => array( $sel => 'text-align: {{VALUE}};' ),
				)
			);
		}
		if ( $opts['spacing'] ) {
			$this->add_responsive_control(
				$id . '_space',
				array(
					'label'      => __( 'Spacing below', 'medical-college-manager' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 60,
						),
					),
					'selectors'  => array( $sel => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
				)
			);
		}
	}

	/**
	 * Left / centre / right choices for a CHOOSE control.
	 *
	 * @return array
	 */
	protected function mcm_align_options() {
		return array(
			'left'   => array(
				'title' => __( 'Left', 'medical-college-manager' ),
				'icon'  => 'eicon-text-align-left',
			),
			'center' => array(
				'title' => __( 'Centre', 'medical-college-manager' ),
				'icon'  => 'eicon-text-align-center',
			),
			'right'  => array(
				'title' => __( 'Right', 'medical-college-manager' ),
				'icon'  => 'eicon-text-align-right',
			),
		);
	}

	/**
	 * A box: Normal/Hover background, border, shadow; radius and padding.
	 *
	 * @param string          $id        Control ID prefix.
	 * @param string|string[] $selectors Box selectors.
	 * @param array           $opts      hover (bool), lift (bool), padding (bool), shadow_name.
	 */
	protected function mcm_box_style( $id, $selectors, $opts = array() ) {
		$opts = wp_parse_args(
			$opts,
			array(
				'hover'       => true,
				'lift'        => false,
				'padding'     => true,
				'shadow_name' => $id . '_shadow',
			)
		);
		$sel  = $this->mcm_sel( $selectors );
		$hsel = $this->mcm_sel( $selectors, ':hover' );

		if ( $opts['hover'] ) {
			$this->start_controls_tabs( $id . '_tabs' );
			$this->start_controls_tab( $id . '_tab_normal', array( 'label' => __( 'Normal', 'medical-college-manager' ) ) );
		}
		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => $id . '_bg',
				'types'    => array( 'classic', 'gradient' ),
				'exclude'  => array( 'image' ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Elementor control option (hides the image type), not a query.
				'selector' => $sel,
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => $id . '_border',
				'selector' => $sel,
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => $opts['shadow_name'],
				'selector' => $sel,
			)
		);
		if ( $opts['hover'] ) {
			$this->end_controls_tab();
			$this->start_controls_tab( $id . '_tab_hover', array( 'label' => __( 'Hover', 'medical-college-manager' ) ) );
			$this->add_group_control(
				\Elementor\Group_Control_Background::get_type(),
				array(
					'name'     => $id . '_bg_hover',
					'types'    => array( 'classic', 'gradient' ),
					'exclude'  => array( 'image' ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Elementor control option (hides the image type), not a query.
					'selector' => $hsel,
				)
			);
			$this->add_control(
				$id . '_border_hover',
				array(
					'label'     => __( 'Border colour', 'medical-college-manager' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $hsel => 'border-color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => $id . '_shadow_hover',
					'selector' => $hsel,
				)
			);
			if ( $opts['lift'] ) {
				$this->add_control(
					$id . '_lift',
					array(
						'label'      => __( 'Lift on hover', 'medical-college-manager' ),
						'type'       => \Elementor\Controls_Manager::SLIDER,
						'size_units' => array( 'px' ),
						'range'      => array(
							'px' => array(
								'min' => 0,
								'max' => 20,
							),
						),
						'selectors'  => array( $sel => '--mcm-lift: translateY(-{{SIZE}}{{UNIT}});' ),
					)
				);
			}
			$this->add_control(
				$id . '_duration',
				array(
					'label'     => __( 'Transition (ms)', 'medical-college-manager' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => array(
						'px' => array(
							'min' => 0,
							'max' => 1000,
						),
					),
					'selectors' => array( $sel => 'transition-duration: {{SIZE}}ms;' ),
				)
			);
			$this->end_controls_tab();
			$this->end_controls_tabs();
		}
		$this->add_responsive_control(
			$id . '_radius',
			array(
				'label'      => __( 'Border radius', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'separator'  => 'before',
				'selectors'  => array( $sel => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;' ),
			)
		);
		if ( $opts['padding'] ) {
			$this->mcm_dimensions( $id . '_padding', __( 'Padding', 'medical-college-manager' ), $sel, 'padding' );
		}
	}

	/**
	 * A button or link styled as one: typography, padding, radius, border and
	 * Normal/Hover text, background and border colours.
	 *
	 * @param string          $id        Control ID prefix.
	 * @param string|string[] $selectors Button selectors.
	 */
	protected function mcm_button_style( $id, $selectors ) {
		$sel  = $this->mcm_sel( $selectors );
		$hsel = $this->mcm_sel( $selectors, ':hover' ) . ', ' . $this->mcm_sel( $selectors, ':focus-visible' );
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => $id . '_typo',
				'selector' => $sel,
			)
		);
		$this->start_controls_tabs( $id . '_tabs' );
		foreach ( array(
			'normal' => array( __( 'Normal', 'medical-college-manager' ), $sel ),
			'hover'  => array( __( 'Hover', 'medical-college-manager' ), $hsel ),
		) as $state => $cfg ) {
			$this->start_controls_tab( $id . '_tab_' . $state, array( 'label' => $cfg[0] ) );
			$this->add_control(
				$id . '_color_' . $state,
				array(
					'label'     => __( 'Text colour', 'medical-college-manager' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $cfg[1] => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				$id . '_bg_' . $state,
				array(
					'label'     => __( 'Background colour', 'medical-college-manager' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $cfg[1] => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				$id . '_bc_' . $state,
				array(
					'label'     => __( 'Border colour', 'medical-college-manager' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $cfg[1] => 'border-color: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'      => $id . '_border',
				'selector'  => $sel,
				'separator' => 'before',
			)
		);
		$this->add_responsive_control(
			$id . '_radius',
			array(
				'label'      => __( 'Border radius', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( $sel => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->mcm_dimensions( $id . '_padding', __( 'Padding', 'medical-college-manager' ), $sel, 'padding' );
	}

	/**
	 * A badge or label: typography, text, background and border colour,
	 * radius and padding.
	 *
	 * @param string          $id        Control ID prefix.
	 * @param string|string[] $selectors Badge selectors.
	 * @param string          $heading   Optional heading above the controls.
	 */
	protected function mcm_badge_style( $id, $selectors, $heading = '' ) {
		$sel = $this->mcm_sel( $selectors );
		if ( '' !== $heading ) {
			$this->mcm_heading( $id . '_heading', $heading );
		}
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => $id . '_typo',
				'selector' => $sel,
			)
		);
		foreach ( array(
			'color' => array( __( 'Text colour', 'medical-college-manager' ), 'color' ),
			'bg'    => array( __( 'Background colour', 'medical-college-manager' ), 'background-color' ),
			'bc'    => array( __( 'Border colour', 'medical-college-manager' ), 'border-color' ),
		) as $key => $cfg ) {
			$this->add_control(
				$id . '_' . $key,
				array(
					'label'     => $cfg[0],
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $sel => $cfg[1] . ': {{VALUE}};' ),
				)
			);
		}
		$this->add_responsive_control(
			$id . '_radius',
			array(
				'label'      => __( 'Border radius', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( $sel => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->mcm_dimensions( $id . '_padding', __( 'Padding', 'medical-college-manager' ), $sel, 'padding' );
	}

	/**
	 * Form fields (search box, dropdowns): typography, colours, focus colour,
	 * border, radius and padding.
	 *
	 * @param string          $id        Control ID prefix.
	 * @param string|string[] $selectors Field selectors.
	 */
	protected function mcm_field_style( $id, $selectors ) {
		$sel = $this->mcm_sel( $selectors );
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => $id . '_typo',
				'selector' => $sel,
			)
		);
		foreach ( array(
			'color' => array( __( 'Text colour', 'medical-college-manager' ), $sel, 'color' ),
			'ph'    => array( __( 'Placeholder colour', 'medical-college-manager' ), $this->mcm_sel( $selectors, '::placeholder' ), 'color' ),
			'bg'    => array( __( 'Background colour', 'medical-college-manager' ), $sel, 'background-color' ),
			'bc'    => array( __( 'Border colour', 'medical-college-manager' ), $sel, 'border-color' ),
			'focus' => array( __( 'Focus border colour', 'medical-college-manager' ), $this->mcm_sel( $selectors, ':focus' ), 'border-color' ),
		) as $key => $cfg ) {
			$this->add_control(
				$id . '_' . $key,
				array(
					'label'     => $cfg[0],
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $cfg[1] => $cfg[2] . ': {{VALUE}};' ),
				)
			);
		}
		$this->add_responsive_control(
			$id . '_bw',
			array(
				'label'      => __( 'Border width', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 6,
					),
				),
				'selectors'  => array( $sel => 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;' ),
			)
		);
		$this->add_responsive_control(
			$id . '_radius',
			array(
				'label'      => __( 'Border radius', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( $sel => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->mcm_dimensions( $id . '_padding', __( 'Padding', 'medical-college-manager' ), $sel, 'padding' );
	}

	/**
	 * Image: height, fit, radius, CSS filters (Normal/Hover), hover zoom.
	 *
	 * @param string          $id         Control ID prefix.
	 * @param string|string[] $images     Image selectors.
	 * @param string|string[] $hover_imgs Image selectors when the card is hovered.
	 */
	protected function mcm_image_style( $id, $images, $hover_imgs ) {
		$sel  = $this->mcm_sel( $images );
		$hsel = $this->mcm_sel( $hover_imgs );
		$this->add_control(
			$id . '_fit',
			array(
				'label'     => __( 'Image fit', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => '',
				'options'   => array(
					''        => __( 'Default', 'medical-college-manager' ),
					'cover'   => __( 'Cover (crop to fill)', 'medical-college-manager' ),
					'contain' => __( 'Contain (whole image)', 'medical-college-manager' ),
					'fill'    => __( 'Stretch', 'medical-college-manager' ),
				),
				'selectors' => array( $sel => 'object-fit: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			$id . '_radius',
			array(
				'label'      => __( 'Border radius', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( $sel => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->start_controls_tabs( $id . '_tabs' );
		$this->start_controls_tab( $id . '_tab_normal', array( 'label' => __( 'Normal', 'medical-college-manager' ) ) );
		$this->add_group_control(
			\Elementor\Group_Control_Css_Filter::get_type(),
			array(
				'name'     => $id . '_filters',
				'selector' => $sel,
			)
		);
		$this->add_control(
			$id . '_opacity',
			array(
				'label'     => __( 'Opacity', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array(
						'min'  => 0.1,
						'max'  => 1,
						'step' => 0.05,
					),
				),
				'selectors' => array( $sel => 'opacity: {{SIZE}};' ),
			)
		);
		$this->end_controls_tab();
		$this->start_controls_tab( $id . '_tab_hover', array( 'label' => __( 'Hover', 'medical-college-manager' ) ) );
		$this->add_group_control(
			\Elementor\Group_Control_Css_Filter::get_type(),
			array(
				'name'     => $id . '_filters_hover',
				'selector' => $hsel,
			)
		);
		$this->add_control(
			$id . '_opacity_hover',
			array(
				'label'     => __( 'Opacity', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array(
						'min'  => 0.1,
						'max'  => 1,
						'step' => 0.05,
					),
				),
				'selectors' => array( $hsel => 'opacity: {{SIZE}};' ),
			)
		);
		$this->add_control(
			$id . '_zoom',
			array(
				'label'     => __( 'Zoom on hover', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array(
						'min'  => 1,
						'max'  => 1.3,
						'step' => 0.01,
					),
				),
				'selectors' => array( $hsel => 'transform: scale({{SIZE}});' ),
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();
	}

	/**
	 * Typography and colour of the "nothing found" message.
	 *
	 * @param array $extra Section arguments (condition).
	 */
	protected function mcm_empty_style( $extra = array() ) {
		$this->mcm_style_section( 'mcm_style_empty', __( 'Empty Message', 'medical-college-manager' ), $extra );
		$this->mcm_text_style( 'st_empty', '.mcm-empty', array( 'align' => true ) );
		$this->end_controls_section();
	}

	/**
	 * True while the Elementor editor (or its AJAX) builds the panel. Option
	 * lists that need database reads are only built then, never on the front end.
	 *
	 * @return bool
	 */
	protected function mcm_in_editor() {
		return is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST );
	}

	/**
	 * Term slug => name options for a taxonomy.
	 *
	 * @param string $tax Taxonomy name.
	 * @return array<string,string>
	 */
	protected function mcm_term_options( $tax ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $tax,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		$out = array();
		foreach ( $terms as $t ) {
			$out[ $t->slug ] = $t->name;
		}
		return $out;
	}

	/**
	 * Post ID => title options for an item picker (editor only, newest 300).
	 *
	 * @param string $type Post type.
	 * @return array<int,string>
	 */
	protected function mcm_post_options( $type ) {
		if ( ! $this->mcm_in_editor() ) {
			return array();
		}
		$ids = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => 300, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Editor-only item picker, IDs only.
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			$title      = get_the_title( $id );
			$out[ $id ] = '' !== $title ? $title : '#' . $id;
		}
		return $out;
	}
}

/**
 * Shared base: builds the control panel and renders through the same engine
 * the shortcodes use, so views, AJAX filtering and connectors all work.
 */
abstract class MCM_Elementor_Widget_Base extends \Elementor\Widget_Base {

	use MCM_Elementor_Style;

	/** The CPT this widget lists (e.g. mcm_consultant). */
	abstract protected function mcm_type();

	/** Default layout for this type. */
	protected function mcm_default_view() {
		return 'grid';
	}

	/**
	 * Extra controls for the Content section of one widget (none by default).
	 */
	protected function mcm_extra_controls() {
	}

	/**
	 * Extra listing attributes from one widget's settings (none by default).
	 *
	 * @param array $atts     Listing attributes built so far.
	 * @param array $settings Widget settings.
	 * @return array
	 */
	protected function mcm_extra_atts( $atts, $settings ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Extension point; overridden by widgets that need settings.
		return $atts;
	}

	/**
	 * Elementor panel category.
	 */
	public function get_categories() {
		return array( 'medical-college-manager' );
	}

	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-posts-grid';
	}

	/**
	 * Stylesheets this widget needs.
	 */
	public function get_style_depends() {
		return array( 'mcm-frontend' );
	}

	/**
	 * Scripts this widget needs.
	 */
	public function get_script_depends() {
		return array( 'mcm-frontend' );
	}

	/**
	 * Fields of this type that a list can be ordered by (meta key => label).
	 *
	 * @return array<string,string>
	 */
	private function mcm_field_options() {
		$fields = MCM_Meta::fields();
		$type   = $this->mcm_type();
		$out    = array();
		foreach ( MCM_Query::sortable_meta_keys( $type ) as $key ) {
			$out[ $key ] = isset( $fields[ $type ]['fields'][ $key ]['label'] ) ? $fields[ $type ]['fields'][ $key ]['label'] : $key;
		}
		return $out;
	}

	/**
	 * Show a control when the filter bar or the directory sidebar is on.
	 *
	 * @return array Elementor "conditions".
	 */
	private function mcm_filters_on() {
		return array(
			'relation' => 'or',
			'terms'    => array(
				array(
					'name'     => 'filters',
					'operator' => '===',
					'value'    => 'yes',
				),
				array(
					'name'     => 'view',
					'operator' => '===',
					'value'    => 'directory',
				),
			),
		);
	}

	/**
	 * Register the widget controls.
	 */
	protected function register_controls() {
		$type   = $this->mcm_type();
		$map    = MCM_Query::tax_map();
		$taxes  = isset( $map[ $type ] ) ? $map[ $type ] : array();
		$fields = $this->mcm_field_options();

		/* ---- Content ---- */
		$this->start_controls_section( 'mcm_query', array( 'label' => __( 'Content', 'medical-college-manager' ) ) );

		$this->add_control(
			'view',
			array(
				'label'   => __( 'Layout', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => $this->mcm_default_view(),
				'options' => array(
					'grid'      => __( 'Grid', 'medical-college-manager' ),
					'list'      => __( 'List', 'medical-college-manager' ),
					'table'     => __( 'Table', 'medical-college-manager' ),
					'directory' => __( 'Directory (left filters)', 'medical-college-manager' ),
					'citation'  => __( 'Citations (publications)', 'medical-college-manager' ),
				),
			)
		);

		// Responsive: separate column counts for desktop / tablet / phone.
		// Targets the grid element directly so it beats the inline fallback.
		$this->add_responsive_control(
			'columns',
			array(
				'label'          => __( 'Columns', 'medical-college-manager' ),
				'type'           => \Elementor\Controls_Manager::SELECT,
				'default'        => '3',
				'tablet_default' => '2',
				'mobile_default' => '1',
				'options'        => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				),
				'condition'      => array( 'view' => array( 'grid', 'directory' ) ),
				'selectors'      => array(
					'{{WRAPPER}} .mcm-grid, {{WRAPPER}} .mcm-dir-grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
				),
			)
		);

		$this->add_control(
			'count',
			array(
				'label'   => __( 'Number of items', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 100,
				'default' => 12,
			)
		);

		$status = MCM_Query::date_status_options( $type );
		if ( $status ) {
			$this->add_control(
				'status',
				array(
					'label'       => __( 'Show', 'medical-college-manager' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => '',
					'options'     => $status,
					'description' => 'mcm_event' === $type
						? __( 'Events without a date are listed under All only.', 'medical-college-manager' )
						: __( 'Items without a closing date count as open.', 'medical-college-manager' ),
				)
			);
		}

		$orderby = array(
			'date'       => __( 'Date published', 'medical-college-manager' ),
			'modified'   => __( 'Last updated', 'medical-college-manager' ),
			'title'      => __( 'Title', 'medical-college-manager' ),
			'menu_order' => __( 'Menu order', 'medical-college-manager' ),
			'rand'       => __( 'Random', 'medical-college-manager' ),
			'post__in'   => __( 'Order of the chosen items', 'medical-college-manager' ),
		);
		if ( $fields ) {
			$orderby['field'] = __( 'A field (e.g. a date)', 'medical-college-manager' );
		}
		$this->add_control(
			'orderby',
			array(
				'label'   => __( 'Order by', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'date',
				'options' => $orderby,
			)
		);
		if ( $fields ) {
			$this->add_control(
				'order_field',
				array(
					'label'       => __( 'Field', 'medical-college-manager' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => (string) key( $fields ),
					'options'     => $fields,
					'description' => __( 'Items with this field empty are listed last.', 'medical-college-manager' ),
					'condition'   => array( 'orderby' => 'field' ),
				)
			);
		}

		$this->add_control(
			'order',
			array(
				'label'     => __( 'Order', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'DESC',
				'options'   => array(
					'DESC' => __( 'Descending', 'medical-college-manager' ),
					'ASC'  => __( 'Ascending', 'medical-college-manager' ),
				),
				'condition' => array( 'orderby!' => array( 'rand', 'post__in' ) ),
			)
		);

		$this->add_control(
			'offset',
			array(
				'label'       => __( 'Skip the first', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 100,
				'default'     => 0,
				'description' => __( 'Number of items to leave out at the start (e.g. ones already shown above).', 'medical-college-manager' ),
			)
		);

		$this->add_control(
			'paginate',
			array(
				'label'        => __( 'Pagination', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Numbered pages below the list; "Number of items" is the page size.', 'medical-college-manager' ),
			)
		);

		$this->mcm_extra_controls();

		$this->add_control(
			'empty',
			array(
				'label'       => __( 'Message when nothing is found', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'No results found.', 'medical-college-manager' ),
				'label_block' => true,
				'separator'   => 'before',
			)
		);

		$this->end_controls_section();

		/* ---- Filters & Tools ---- */
		$this->start_controls_section( 'mcm_tools', array( 'label' => __( 'Filters & Tools', 'medical-college-manager' ) ) );

		$this->add_control(
			'filters',
			array(
				'label'        => __( 'Filter bar (search + dropdowns)', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'On', 'medical-college-manager' ),
				'label_off'    => __( 'Off', 'medical-college-manager' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'search_box',
			array(
				'label'        => __( 'Search box', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'conditions'   => $this->mcm_filters_on(),
			)
		);
		$this->add_control(
			'placeholder',
			array(
				'label'       => __( 'Search placeholder', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'Search by name, keyword…', 'medical-college-manager' ),
				'label_block' => true,
				'conditions'  => $this->mcm_filters_on(),
			)
		);
		if ( $taxes ) {
			$facets = array();
			foreach ( $taxes as $tax ) {
				$tobj           = get_taxonomy( $tax );
				$facets[ $tax ] = $tobj ? $tobj->labels->name : $tax;
			}
			$this->add_control(
				'facets',
				array(
					'label'       => __( 'Filters to show', 'medical-college-manager' ),
					'type'        => \Elementor\Controls_Manager::SELECT2,
					'multiple'    => true,
					'label_block' => true,
					'options'     => $facets,
					'default'     => array(),
					'description' => __( 'Leave empty to show every filter.', 'medical-college-manager' ),
					'conditions'  => $this->mcm_filters_on(),
				)
			);
		}
		$this->add_control(
			'reset',
			array(
				'label'        => __( 'Reset button', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'conditions'   => $this->mcm_filters_on(),
			)
		);
		$this->add_control(
			'search',
			array(
				'label'       => __( 'Start with search keyword', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
				'description' => __( 'Optional. Lists only items matching this word; with the search box off it stays fixed.', 'medical-college-manager' ),
				'separator'   => 'before',
			)
		);

		$this->add_control(
			'print',
			array(
				'label'        => __( 'Print button', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
			)
		);
		$this->add_control(
			'print_title',
			array(
				'label'       => __( 'Printed page heading', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
				'condition'   => array( 'print' => 'yes' ),
			)
		);

		$this->end_controls_section();

		/* ---- Include / Exclude ---- */
		$this->start_controls_section( 'mcm_scope', array( 'label' => __( 'Include / Exclude', 'medical-college-manager' ) ) );

		$this->add_control(
			'mcm_scope_note',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => __( 'Limit this widget to specific terms. With one term the list is fixed to it; with several, visitors can narrow within them using the filter bar.', 'medical-college-manager' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		foreach ( $taxes as $tax ) {
			$options = $this->mcm_term_options( $tax );
			if ( empty( $options ) ) {
				continue;
			}
			$tobj  = get_taxonomy( $tax );
			$label = $tobj ? $tobj->labels->singular_name : $tax;
			$this->add_control(
				'tax_' . $tax,
				array(
					'label'       => $label,
					'type'        => \Elementor\Controls_Manager::SELECT2,
					'options'     => $options,
					'multiple'    => true,
					'label_block' => true,
					'default'     => array(),
				)
			);
			$this->add_control(
				'ex_' . $tax,
				array(
					/* translators: %s: taxonomy name, e.g. Department. */
					'label'       => sprintf( __( 'Exclude %s', 'medical-college-manager' ), $label ),
					'type'        => \Elementor\Controls_Manager::SELECT2,
					'options'     => $options,
					'multiple'    => true,
					'label_block' => true,
					'default'     => array(),
				)
			);
		}

		$items = $this->mcm_post_options( $type );
		$this->add_control(
			'ids',
			array(
				'label'       => __( 'Only these items', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => $items,
				'multiple'    => true,
				'label_block' => true,
				'default'     => array(),
				'separator'   => 'before',
			)
		);
		$this->add_control(
			'exclude_ids',
			array(
				'label'       => __( 'Leave out these items', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => $items,
				'multiple'    => true,
				'label_block' => true,
				'default'     => array(),
			)
		);

		$this->end_controls_section();

		$this->register_style_controls();
	}

	/**
	 * Style tab. The original controls write the plugin's CSS variables onto
	 * {{WRAPPER}} (so they cascade to everything inside); the rest style one
	 * part each. Control IDs from earlier versions are kept, so saved pages
	 * keep their design.
	 */
	protected function register_style_controls() {
		$wrapper  = '{{WRAPPER}}';
		$cards    = array( '.mcm-card', '.mcm-dir-card', '.mcm-list-item' );
		$on_cards = array( 'view' => array( 'grid', 'list', 'directory' ) );

		/* ---- Style: Layout & spacing ---- */
		$this->mcm_style_section( 'mcm_style_layout', __( 'Layout & Spacing', 'medical-college-manager' ) );

		$this->add_responsive_control(
			'st_gap',
			array(
				'label'      => __( 'Gap between cards', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 80,
					),
				),
				'selectors'  => array( $wrapper => '--mcm-gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'st_pad',
			array(
				'label'      => __( 'Card padding', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 60,
					),
				),
				'selectors'  => array( $wrapper => '--mcm-pad: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'st_radius',
			array(
				'label'      => __( 'Corner radius', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 40,
					),
				),
				'selectors'  => array( $wrapper => '--mcm-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'st_imgh',
			array(
				'label'      => __( 'Image height', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 100,
						'max' => 500,
					),
				),
				'selectors'  => array( $wrapper => '--mcm-img-h: {{SIZE}}{{UNIT}};' ),
				'condition'  => array( 'view' => array( 'grid', 'directory' ) ),
			)
		);
		$this->add_responsive_control(
			'st_align',
			array(
				'label'     => __( 'Card text alignment', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => $this->mcm_align_options(),
				'selectors' => array( $this->mcm_sel( array( '.mcm-card-body', '.mcm-list-body', '.mcm-dir-card' ) ) => 'text-align: {{VALUE}};' ),
				'condition' => $on_cards,
			)
		);

		$this->end_controls_section();

		/* ---- Style: Colours ---- */
		$this->mcm_style_section( 'mcm_style_colors', __( 'Colours', 'medical-college-manager' ) );

		$colors = array(
			'st_primary' => array( __( 'Primary (buttons, links)', 'medical-college-manager' ), '--mcm-primary' ),
			'st_accent'  => array( __( 'Accent (hover)', 'medical-college-manager' ), '--mcm-accent' ),
			'st_text'    => array( __( 'Heading / title text', 'medical-college-manager' ), '--mcm-text' ),
			'st_muted'   => array( __( 'Secondary / meta text', 'medical-college-manager' ), '--mcm-muted' ),
			'st_cardbg'  => array( __( 'Card background', 'medical-college-manager' ), '--mcm-card-bg' ),
			'st_border'  => array( __( 'Borders', 'medical-college-manager' ), '--mcm-border' ),
			'st_panelbg' => array( __( 'Filter / panel background', 'medical-college-manager' ), '--mcm-page-bg' ),
		);
		foreach ( $colors as $id => $c ) {
			$this->add_control(
				$id,
				array(
					'label'     => $c[0],
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $wrapper => $c[1] . ': {{VALUE}};' ),
				)
			);
		}

		$this->end_controls_section();

		/* ---- Style: Cards ---- */
		$this->mcm_style_section( 'mcm_style_card', __( 'Cards', 'medical-college-manager' ), array( 'condition' => $on_cards ) );
		$this->mcm_box_style(
			'st_card',
			$cards,
			array(
				'lift'        => true,
				'padding'     => false,
				'shadow_name' => 'st_card_shadow',
			)
		);
		$this->mcm_dimensions( 'st_card_body_pad', __( 'Content padding', 'medical-college-manager' ), $this->mcm_sel( array( '.mcm-card-body', '.mcm-list-item', '.mcm-jobcard-body' ) ), 'padding' );
		$this->end_controls_section();

		/* ---- Style: Images ---- */
		$this->mcm_style_section( 'mcm_style_image', __( 'Images', 'medical-college-manager' ), array( 'condition' => $on_cards ) );
		$this->mcm_image_style(
			'st_img',
			array( '.mcm-card-media img', '.mcm-dir-card-media img', '.mcm-list-media img' ),
			array( '.mcm-card:hover .mcm-card-media img', '.mcm-dir-card:hover .mcm-dir-card-media img', '.mcm-list-item:hover .mcm-list-media img' )
		);
		$this->end_controls_section();

		/* ---- Style: Titles (real typography group — loads fonts properly) ---- */
		$this->mcm_style_section( 'mcm_style_type', __( 'Titles', 'medical-college-manager' ) );

		$title_sel = $wrapper . ' .mcm-card-title, ' . $wrapper . ' .mcm-card-title a, ' . $wrapper . ' .mcm-dir-card-name, '
			. $wrapper . ' .mcm-dir-card-name a, ' . $wrapper . ' .mcm-list-title, ' . $wrapper . ' .mcm-list-title a';

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'st_title_typo',
				'label'    => __( 'Title typography', 'medical-college-manager' ),
				'selector' => $title_sel,
			)
		);
		$this->add_control(
			'st_title_color',
			array(
				'label'     => __( 'Title colour', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $title_sel => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'st_title_hover',
			array(
				'label'     => __( 'Title hover colour', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $this->mcm_sel( array( '.mcm-card-title a', '.mcm-dir-card-name a', '.mcm-list-title a', '.mcm-pub-title' ), ':hover' ) => 'color: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'st_title_space',
			array(
				'label'      => __( 'Spacing below', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 40,
					),
				),
				'selectors'  => array( $this->mcm_sel( array( '.mcm-card-title', '.mcm-dir-card-name', '.mcm-list-title' ) ) => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();

		/* ---- Style: Details text ---- */
		$this->mcm_style_section( 'mcm_style_text', __( 'Details Text', 'medical-college-manager' ) );
		$this->mcm_text_style(
			'st_meta',
			array( '.mcm-meta', '.mcm-meta-value', '.mcm-card-excerpt', '.mcm-dir-card-desig', '.mcm-dir-card-dept', '.mcm-jobcard-dept', '.mcm-pub-meta' ),
			array( 'heading' => __( 'Details', 'medical-college-manager' ) )
		);
		$this->mcm_text_style( 'st_meta_label', array( '.mcm-meta-label', '.mcm-jobcard-label' ), array( 'heading' => __( 'Field labels', 'medical-college-manager' ) ) );
		$this->mcm_text_style( 'st_spec', array( '.mcm-dir-card-spec', '.mcm-jobcard-cat' ), array( 'heading' => __( 'Specialty / category line', 'medical-college-manager' ) ) );
		$this->end_controls_section();

		/* ---- Style: Badges ---- */
		$this->mcm_style_section( 'mcm_style_badge', __( 'Status Badges', 'medical-college-manager' ) );
		$this->mcm_badge_style( 'st_badge', array( '.mcm-badge', '.mcm-pub-type', '.mcm-pub-oa' ) );
		foreach ( array(
			'open'     => __( 'Open / Upcoming event', 'medical-college-manager' ),
			'closing'  => __( 'Closing soon / Today', 'medical-college-manager' ),
			'upcoming' => __( 'Opening later', 'medical-college-manager' ),
			'closed'   => __( 'Closed / Past', 'medical-college-manager' ),
		) as $state => $label ) {
			$badge = $this->mcm_sel( '.mcm-badge--' . $state );
			$this->mcm_heading( 'st_badge_' . $state . '_heading', $label );
			$this->add_control(
				'st_badge_' . $state,
				array(
					'label'     => __( 'Background colour', 'medical-college-manager' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $badge => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'st_badge_' . $state . '_text',
				array(
					'label'     => __( 'Text colour', 'medical-college-manager' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $badge => 'color: {{VALUE}};' ),
				)
			);
		}
		$this->end_controls_section();

		/* ---- Style: Buttons ---- */
		$this->mcm_style_section( 'mcm_style_button', __( 'Buttons & Links', 'medical-college-manager' ) );
		$this->mcm_button_style( 'st_btn', array( '.mcm-card-link', '.mcm-list-link', '.mcm-dir-card-btn', '.mcm-jobcard-btn', '.mcm-pub-link', '.mcm-table-link' ) );
		$this->end_controls_section();

		/* ---- Style: Filter bar ---- */
		$filters_on = array( 'conditions' => $this->mcm_filters_on() );
		$this->mcm_style_section( 'mcm_style_filters', __( 'Filter Bar', 'medical-college-manager' ), $filters_on );
		$this->mcm_heading( 'st_fbar_heading', __( 'Bar', 'medical-college-manager' ) );
		$this->mcm_box_style( 'st_fbar', array( '.mcm-filters', '.mcm-dir-aside' ), array( 'hover' => false ) );
		$this->add_responsive_control(
			'st_fbar_gap',
			array(
				'label'      => __( 'Gap between fields', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 40,
					),
				),
				'selectors'  => array( $this->mcm_sel( '.mcm-filters' ) => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->mcm_heading( 'st_field_heading', __( 'Search box & dropdowns', 'medical-college-manager' ) );
		$this->mcm_field_style( 'st_field', array( '.mcm-search', '.mcm-filter-tax' ) );
		$this->mcm_heading( 'st_reset_heading', __( 'Reset & search buttons', 'medical-college-manager' ) );
		$this->mcm_button_style( 'st_reset', array( 'button.mcm-reset', 'button.mcm-search-btn' ) );
		$this->end_controls_section();

		/* ---- Style: Directory sidebar ---- */
		$this->mcm_style_section( 'mcm_style_sidebar', __( 'Directory Sidebar', 'medical-college-manager' ), array( 'condition' => array( 'view' => 'directory' ) ) );
		$this->add_responsive_control(
			'st_side_w',
			array(
				'label'      => __( 'Sidebar width', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array(
						'min' => 160,
						'max' => 480,
					),
					'%'  => array(
						'min' => 15,
						'max' => 50,
					),
				),
				'selectors'  => array( $this->mcm_sel( '.mcm-directory' ) => '--mcm-side-w: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->mcm_text_style( 'st_facet_title', '.mcm-dir-facet-title', array( 'heading' => __( 'Filter headings', 'medical-college-manager' ) ) );
		$this->mcm_text_style( 'st_facet_label', '.mcm-dir-checks label', array( 'heading' => __( 'Options', 'medical-college-manager' ) ) );
		$this->add_control(
			'st_facet_check',
			array(
				'label'     => __( 'Checkbox colour', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $this->mcm_sel( '.mcm-dir-checks input' ) => 'accent-color: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();

		/* ---- Style: Table ---- */
		$this->mcm_style_section( 'mcm_style_table', __( 'Table', 'medical-college-manager' ), array( 'condition' => array( 'view' => array( 'table', 'directory' ) ) ) );
		$this->mcm_text_style( 'st_th', '.mcm-table thead th', array( 'heading' => __( 'Header', 'medical-college-manager' ) ) );
		$this->add_control(
			'st_th_bg',
			array(
				'label'     => __( 'Header background', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $this->mcm_sel( '.mcm-table thead th' ) => 'background-color: {{VALUE}};' ),
			)
		);
		$this->mcm_text_style( 'st_td', '.mcm-table tbody td', array( 'heading' => __( 'Rows', 'medical-college-manager' ) ) );
		foreach ( array(
			'st_tr_bg'     => array( __( 'Row background', 'medical-college-manager' ), '.mcm-table tbody tr', 'background-color' ),
			'st_tr_stripe' => array( __( 'Striped row background', 'medical-college-manager' ), '.mcm-table tbody tr:nth-child(even)', 'background-color' ),
			'st_tr_hover'  => array( __( 'Row hover background', 'medical-college-manager' ), '.mcm-table tbody tr:hover', 'background-color' ),
			'st_td_border' => array( __( 'Line colour', 'medical-college-manager' ), '.mcm-table tbody td', 'border-color' ),
		) as $id => $cfg ) {
			$this->add_control(
				$id,
				array(
					'label'     => $cfg[0],
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $this->mcm_sel( $cfg[1] ) => $cfg[2] . ': {{VALUE}};' ),
				)
			);
		}
		$this->mcm_dimensions( 'st_cell_pad', __( 'Cell padding', 'medical-college-manager' ), $this->mcm_sel( array( '.mcm-table thead th', '.mcm-table tbody td' ) ), 'padding' );
		$this->mcm_heading( 'st_table_box', __( 'Table frame', 'medical-college-manager' ) );
		$this->mcm_box_style( 'st_tablewrap', '.mcm-table-wrap', array( 'hover' => false ) );
		$this->end_controls_section();

		/* ---- Style: Citations ---- */
		$this->mcm_style_section( 'mcm_style_cite', __( 'Citations', 'medical-college-manager' ), array( 'condition' => array( 'view' => array( 'citation', 'directory' ) ) ) );
		$this->mcm_text_style(
			'st_cite_title',
			'.mcm-pub-title',
			array(
				'heading' => __( 'Title', 'medical-college-manager' ),
				'hover'   => array( '.mcm-pub-title:hover' ),
			)
		);
		$this->mcm_text_style( 'st_cite_text', '.mcm-pub-cite', array( 'heading' => __( 'Citation', 'medical-college-manager' ) ) );
		$this->mcm_text_style( 'st_cite_year', '.mcm-pub-year', array( 'heading' => __( 'Year', 'medical-college-manager' ) ) );
		$this->mcm_heading( 'st_cite_items', __( 'Items', 'medical-college-manager' ) );
		$this->add_control(
			'st_cite_num',
			array(
				'label'     => __( 'Number colour', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $this->mcm_sel( '.mcm-pub-item::before' ) => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'st_cite_line',
			array(
				'label'     => __( 'Divider colour', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $this->mcm_sel( '.mcm-pub-item' ) => 'border-color: {{VALUE}};' ),
			)
		);
		$this->mcm_dimensions( 'st_cite_pad', __( 'Item padding', 'medical-college-manager' ), $this->mcm_sel( '.mcm-pub-item' ), 'padding' );
		$this->end_controls_section();

		/* ---- Style: Pagination ---- */
		$this->mcm_style_section( 'mcm_style_pagination', __( 'Pagination', 'medical-college-manager' ), array( 'condition' => array( 'paginate' => 'yes' ) ) );
		$this->add_responsive_control(
			'st_pg_align',
			array(
				'label'     => __( 'Alignment', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => __( 'Left', 'medical-college-manager' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'     => array(
						'title' => __( 'Centre', 'medical-college-manager' ),
						'icon'  => 'eicon-text-align-center',
					),
					'flex-end'   => array(
						'title' => __( 'Right', 'medical-college-manager' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array( $this->mcm_sel( '.mcm-pagination .page-numbers' ) => 'justify-content: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'st_pg_space',
			array(
				'label'      => __( 'Space above', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 80,
					),
				),
				'selectors'  => array( $this->mcm_sel( '.mcm-pagination' ) => 'margin-top: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->mcm_button_style( 'st_pg', array( '.mcm-pagination a.page-numbers', '.mcm-pagination span.page-numbers' ) );
		$this->mcm_heading( 'st_pg_current', __( 'Current page', 'medical-college-manager' ) );
		foreach ( array(
			'st_pg_cur_color' => array( __( 'Text colour', 'medical-college-manager' ), 'color' ),
			'st_pg_cur_bg'    => array( __( 'Background colour', 'medical-college-manager' ), 'background-color' ),
			'st_pg_cur_bc'    => array( __( 'Border colour', 'medical-college-manager' ), 'border-color' ),
		) as $id => $cfg ) {
			$this->add_control(
				$id,
				array(
					'label'     => $cfg[0],
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $this->mcm_sel( '.mcm-pagination .page-numbers.current' ) => $cfg[1] . ': {{VALUE}};' ),
				)
			);
		}
		$this->end_controls_section();

		/* ---- Style: Print button ---- */
		$this->mcm_style_section( 'mcm_style_print', __( 'Print Button', 'medical-college-manager' ), array( 'condition' => array( 'print' => 'yes' ) ) );
		$this->mcm_button_style( 'st_print', 'button.mcm-print-btn' );
		$this->end_controls_section();

		$this->mcm_empty_style();
	}

	/**
	 * Render boundary: a failing widget renders nothing for visitors (and a
	 * short notice for administrators) instead of breaking the page.
	 */
	protected function render() {
		echo MCM_Safe::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped by mcm_output().
			function () {
				ob_start();
				$this->mcm_output();
				return ob_get_clean();
			},
			'Elementor widget ' . $this->get_name()
		);
	}

	/** Unguarded widget output; see render(). */
	protected function mcm_output() {
		$s    = $this->get_settings_for_display();
		$s    = is_array( $s ) ? $s : array();
		$type = $this->mcm_type();
		$view = in_array( isset( $s['view'] ) ? $s['view'] : '', array( 'grid', 'list', 'table', 'directory', 'citation' ), true ) ? $s['view'] : $this->mcm_default_view();
		$yes  = function ( $key, $fallback ) use ( $s ) {
			if ( ! isset( $s[ $key ] ) ) {
				return $fallback;
			}
			return 'yes' === $s[ $key ] ? 'yes' : 'no';
		};
		$text = function ( $key ) use ( $s ) {
			return isset( $s[ $key ] ) ? sanitize_text_field( MCM_Safe::str( $s[ $key ] ) ) : '';
		};

		$orderby = ! empty( $s['orderby'] ) ? sanitize_key( MCM_Safe::str( $s['orderby'] ) ) : 'date';
		$atts    = array(
			'type'        => $type,
			'view'        => $view,
			'columns'     => max( 1, min( 6, (int) ( ! empty( $s['columns'] ) ? $s['columns'] : 3 ) ) ),
			'count'       => max( 1, (int) ( ! empty( $s['count'] ) ? $s['count'] : 12 ) ),
			'filters'     => $yes( 'filters', 'no' ),
			'print'       => $yes( 'print', 'no' ),
			'orderby'     => $orderby,
			'order'       => ( isset( $s['order'] ) && 'ASC' === $s['order'] ) ? 'ASC' : 'DESC',
			'paginate'    => $yes( 'paginate', 'no' ),
			'offset'      => isset( $s['offset'] ) ? absint( $s['offset'] ) : 0,
			'search_box'  => $yes( 'search_box', 'yes' ),
			'reset'       => $yes( 'reset', 'yes' ),
			'placeholder' => $text( 'placeholder' ),
			'search'      => $text( 'search' ),
			'print_title' => $text( 'print_title' ),
			'empty'       => $text( 'empty' ),
			'facets'      => isset( $s['facets'] ) && is_array( $s['facets'] ) ? implode( ',', array_map( 'sanitize_key', $s['facets'] ) ) : '',
			'ids'         => isset( $s['ids'] ) ? implode( ',', MCM_Query::id_list( $s['ids'] ) ) : '',
			'exclude_ids' => isset( $s['exclude_ids'] ) ? implode( ',', MCM_Query::id_list( $s['exclude_ids'] ) ) : '',
		);
		if ( 'field' === $orderby && ! empty( $s['order_field'] ) ) {
			$atts['meta_key'] = sanitize_key( MCM_Safe::str( $s['order_field'] ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Listing attribute (whitelisted per type in MCM_Query), not a query argument.
		}
		if ( MCM_Query::date_status_options( $type ) && ! empty( $s['status'] ) ) {
			$atts['status'] = sanitize_key( MCM_Safe::str( $s['status'] ) );
		}

		$map   = MCM_Query::tax_map();
		$taxes = isset( $map[ $type ] ) ? $map[ $type ] : array();
		// Pre-filters are the AUTHOR's scope (locked; visitors narrow within
		// it), not a visitor filter. A control saved before 1.30.2 holds a
		// single slug string; newer ones hold an array — both are accepted.
		$prefilter = array();
		$exclude   = array();
		foreach ( $taxes as $tax ) {
			if ( ! empty( $s[ 'tax_' . $tax ] ) ) {
				$prefilter[ $tax ] = MCM_Query::normalize_slugs( $s[ 'tax_' . $tax ] );
			}
			if ( ! empty( $s[ 'ex_' . $tax ] ) ) {
				$exclude[ $tax ] = MCM_Query::normalize_slugs( $s[ 'ex_' . $tax ] );
			}
		}
		if ( $prefilter ) {
			$atts['prefilter'] = $prefilter;
		}
		if ( $exclude ) {
			$atts['exclude_terms'] = $exclude;
		}
		$atts = $this->mcm_extra_atts( $atts, $s );

		echo MCM_Shortcodes::render_block( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/*
 * ----------------------------------------------------------------
 * Concrete widgets — one per public content type.
 * ----------------------------------------------------------------
 */

/**
 * Elementor widget: Consultants.
 */
class MCM_Widget_Consultants extends MCM_Elementor_Widget_Base {
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_consultants';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Consultants', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-person';
	}
	/**
	 * Search keywords for the Elementor panel.
	 */
	public function get_keywords() {
		return array( 'consultant', 'doctor', 'medical', 'faculty', 'medical-college-manager' );
	}
	/**
	 * Post type this widget lists.
	 */
	protected function mcm_type() {
		return 'mcm_consultant';
	}
}

/**
 * Elementor widget: Team.
 */
class MCM_Widget_Team extends MCM_Elementor_Widget_Base {
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_team';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Team', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-person';
	}
	/**
	 * Search keywords for the Elementor panel.
	 */
	public function get_keywords() {
		return array( 'team', 'staff', 'faculty', 'people', 'medical-college-manager' );
	}
	/**
	 * Post type this widget lists.
	 */
	protected function mcm_type() {
		return 'mcm_team';
	}
}

/**
 * Elementor widget: Programs.
 */
class MCM_Widget_Programs extends MCM_Elementor_Widget_Base {
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_programs';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Programs', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-document-file';
	}
	/**
	 * Search keywords for the Elementor panel.
	 */
	public function get_keywords() {
		return array( 'program', 'degree', 'course', 'medical-college-manager' );
	}
	/**
	 * Post type this widget lists.
	 */
	protected function mcm_type() {
		return 'mcm_program';
	}
}

/**
 * Elementor widget: Courses.
 */
class MCM_Widget_Courses extends MCM_Elementor_Widget_Base {
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_courses';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Courses', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-book';
	}
	/**
	 * Search keywords for the Elementor panel.
	 */
	public function get_keywords() {
		return array( 'course', 'subject', 'class', 'medical-college-manager' );
	}
	/**
	 * Post type this widget lists.
	 */
	protected function mcm_type() {
		return 'mcm_course';
	}
}

/**
 * Elementor widget: Events.
 */
class MCM_Widget_Events extends MCM_Elementor_Widget_Base {
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_events';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Events', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-calendar';
	}
	/**
	 * Search keywords for the Elementor panel.
	 */
	public function get_keywords() {
		return array( 'event', 'seminar', 'conference', 'medical-college-manager' );
	}
	/**
	 * Post type this widget lists.
	 */
	protected function mcm_type() {
		return 'mcm_event';
	}
}

/**
 * Elementor widget: Timetable.
 */
class MCM_Widget_Timetable extends MCM_Elementor_Widget_Base {
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_timetable';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Timetable', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-table';
	}
	/**
	 * Search keywords for the Elementor panel.
	 */
	public function get_keywords() {
		return array( 'timetable', 'schedule', 'class', 'medical-college-manager' );
	}
	/**
	 * Post type this widget lists.
	 */
	protected function mcm_type() {
		return 'mcm_timetable';
	}
	/**
	 * Default layout for this widget.
	 */
	protected function mcm_default_view() {
		return 'table';
	}
}

/**
 * Elementor widget: Admissions.
 */
class MCM_Widget_Admissions extends MCM_Elementor_Widget_Base {
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_admissions';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Admissions', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-form-horizontal';
	}
	/**
	 * Search keywords for the Elementor panel.
	 */
	public function get_keywords() {
		return array( 'admission', 'apply', 'intake', 'medical-college-manager' );
	}
	/**
	 * Post type this widget lists.
	 */
	protected function mcm_type() {
		return 'mcm_admission';
	}
}

/**
 * Elementor widget: Tenders.
 */
class MCM_Widget_Tenders extends MCM_Elementor_Widget_Base {
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_tenders';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Tenders', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-document-file';
	}
	/**
	 * Search keywords for the Elementor panel.
	 */
	public function get_keywords() {
		return array( 'tender', 'procurement', 'bid', 'notice', 'medical-college-manager' );
	}
	/**
	 * Post type this widget lists.
	 */
	protected function mcm_type() {
		return 'mcm_tender';
	}
}

/**
 * Elementor widget: Scholarships.
 */
class MCM_Widget_Scholarships extends MCM_Elementor_Widget_Base {
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_scholarships';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Scholarships', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-star';
	}
	/**
	 * Search keywords for the Elementor panel.
	 */
	public function get_keywords() {
		return array( 'scholarship', 'financial aid', 'merit', 'medical-college-manager' );
	}
	/**
	 * Post type this widget lists.
	 */
	protected function mcm_type() {
		return 'mcm_scholarship';
	}
}

/**
 * Elementor widget: Research.
 */
class MCM_Widget_Research extends MCM_Elementor_Widget_Base {
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_research';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Research Projects', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-search-results';
	}
	/**
	 * Search keywords for the Elementor panel.
	 */
	public function get_keywords() {
		return array( 'research', 'project', 'r&d', 'investigator', 'medical-college-manager' );
	}
	/**
	 * Post type this widget lists.
	 */
	protected function mcm_type() {
		return 'mcm_research';
	}
}

/**
 * Elementor widget: Policies.
 */
class MCM_Widget_Policies extends MCM_Elementor_Widget_Base {
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_policies';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Policies', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-document-file';
	}
	/**
	 * Search keywords for the Elementor panel.
	 */
	public function get_keywords() {
		return array( 'policy', 'policies', 'document', 'download', 'medical-college-manager' );
	}
	/**
	 * Post type this widget lists.
	 */
	protected function mcm_type() {
		return 'mcm_policy';
	}
	/**
	 * Default layout for this widget.
	 */
	protected function mcm_default_view() {
		return 'list';
	}
}

/**
 * Elementor widget: Jobs / vacancies.
 */
class MCM_Widget_Jobs extends MCM_Elementor_Widget_Base {
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_jobs';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Jobs / Vacancies', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-person';
	}
	/**
	 * Search keywords for the Elementor panel.
	 */
	public function get_keywords() {
		return array( 'jobs', 'vacancy', 'vacancies', 'careers', 'hiring', 'medical-college-manager' );
	}
	/**
	 * Post type this widget lists.
	 */
	protected function mcm_type() {
		return 'mcm_job';
	}
	/**
	 * Status control: which jobs to list.
	 */
	protected function mcm_extra_controls() {
		$this->add_control(
			'status',
			array(
				'label'   => __( 'Status', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'open',
				'options' => array(
					'open'   => __( 'Open', 'medical-college-manager' ),
					'closed' => __( 'Closed', 'medical-college-manager' ),
					'all'    => __( 'All', 'medical-college-manager' ),
				),
			)
		);
	}
	/**
	 * Pass the Status control to the listing.
	 *
	 * @param array $atts     Listing attributes built so far.
	 * @param array $settings Widget settings.
	 * @return array
	 */
	protected function mcm_extra_atts( $atts, $settings ) {
		$atts['status'] = MCM_Job::status_attr( isset( $settings['status'] ) ? $settings['status'] : 'open' );
		return $atts;
	}
}

/**
 * Elementor widget: Publications.
 */
class MCM_Widget_Publications extends MCM_Elementor_Widget_Base {
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_publications';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Publications', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-document-file';
	}
	/**
	 * Search keywords for the Elementor panel.
	 */
	public function get_keywords() {
		return array( 'publication', 'research', 'paper', 'journal', 'citation', 'medical-college-manager' );
	}
	/**
	 * Post type this widget lists.
	 */
	protected function mcm_type() {
		return 'mcm_publication';
	}
	/**
	 * Default layout for this widget.
	 */
	protected function mcm_default_view() {
		return 'citation';
	}
}

/**
 * Blog Posts widget — standard WordPress posts in the date-badge layout.
 * Separate from the CPT widgets (different controls + renderer).
 */
class MCM_Widget_Blog extends \Elementor\Widget_Base {

	use MCM_Elementor_Style;

	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_blog';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Blog Posts', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-post-list';
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
		return array( 'blog', 'posts', 'news', 'articles', 'medical-college-manager' );
	}
	/**
	 * Stylesheets this widget needs.
	 */
	public function get_style_depends() {
		return array( 'mcm-frontend' );
	}

	/**
	 * Register the widget controls.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'mcm_blog', array( 'label' => __( 'Blog Posts', 'medical-college-manager' ) ) );

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'list',
				'options' => array(
					'list' => __( 'List (date badge rows)', 'medical-college-manager' ),
					'grid' => __( 'Grid (cards)', 'medical-college-manager' ),
				),
			)
		);
		$this->add_responsive_control(
			'columns',
			array(
				'label'          => __( 'Columns', 'medical-college-manager' ),
				'type'           => \Elementor\Controls_Manager::SELECT,
				'default'        => '3',
				'tablet_default' => '2',
				'mobile_default' => '1',
				'options'        => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				),
				'condition'      => array( 'layout' => 'grid' ),
				'selectors'      => array( '{{WRAPPER}} .mcm-blog--grid' => '--mcm-blog-cols: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'intro',
			array(
				'label' => __( 'Intro text (optional)', 'medical-college-manager' ),
				'type'  => \Elementor\Controls_Manager::TEXTAREA,
				'rows'  => 2,
			)
		);

		$this->add_control(
			'count',
			array(
				'label'   => __( 'Number of posts', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 50,
				'default' => 6,
			)
		);

		$this->add_control(
			'orderby',
			array(
				'label'   => __( 'Order by', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'date',
				'options' => array(
					'date'          => __( 'Date', 'medical-college-manager' ),
					'modified'      => __( 'Last updated', 'medical-college-manager' ),
					'title'         => __( 'Title', 'medical-college-manager' ),
					'comment_count' => __( 'Most commented', 'medical-college-manager' ),
					'menu_order'    => __( 'Menu order', 'medical-college-manager' ),
					'rand'          => __( 'Random', 'medical-college-manager' ),
				),
			)
		);

		$this->add_control(
			'order',
			array(
				'label'     => __( 'Order', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'DESC',
				'options'   => array(
					'DESC' => __( 'Newest first', 'medical-college-manager' ),
					'ASC'  => __( 'Oldest first', 'medical-college-manager' ),
				),
				'condition' => array( 'orderby!' => 'rand' ),
			)
		);

		$this->add_control(
			'show_thumb',
			array(
				'label'        => __( 'Show image', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'separator'    => 'before',
			)
		);
		$this->add_control(
			'show_date',
			array(
				'label'        => __( 'Show date', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'excerpt',
			array(
				'label'   => __( 'Excerpt words (0 to hide)', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 0,
				'max'     => 100,
				'default' => 30,
			)
		);

		$this->add_control(
			'show_more',
			array(
				'label'        => __( 'Show read-more link', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'read_more',
			array(
				'label'       => __( 'Read-more label', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'Read More', 'medical-college-manager' ),
				'condition'   => array( 'show_more' => 'yes' ),
			)
		);

		$this->add_control(
			'empty',
			array(
				'label'       => __( 'Message when there are no posts', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'No posts found.', 'medical-college-manager' ),
				'label_block' => true,
				'separator'   => 'before',
			)
		);

		$this->end_controls_section();

		/* ---- Include / Exclude ---- */
		$this->start_controls_section( 'mcm_blog_query', array( 'label' => __( 'Include / Exclude', 'medical-college-manager' ) ) );
		$categories = $this->mcm_term_options( 'category' );
		$this->add_control(
			'category',
			array(
				'label'       => __( 'Categories', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $categories,
			)
		);
		$this->add_control(
			'exclude_category',
			array(
				'label'       => __( 'Exclude categories', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $categories,
			)
		);
		$this->add_control(
			'tag',
			array(
				'label'       => __( 'Tags', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->mcm_term_options( 'post_tag' ),
			)
		);
		$this->add_control(
			'author',
			array(
				'label'   => __( 'Author', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array( '' => __( 'Any', 'medical-college-manager' ) ) + $this->mcm_author_options(),
			)
		);
		$this->add_control(
			'offset',
			array(
				'label'       => __( 'Skip the first', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 50,
				'default'     => 0,
				'description' => __( 'Number of posts to leave out at the start.', 'medical-college-manager' ),
			)
		);
		$this->end_controls_section();

		$this->register_style_controls();
	}

	/**
	 * Style tab.
	 */
	protected function register_style_controls() {
		$grid = array( 'layout' => 'grid' );

		$this->mcm_style_section( 'mcm_blog_style_layout', __( 'Layout & Spacing', 'medical-college-manager' ) );
		$this->add_responsive_control(
			'st_gap',
			array(
				'label'      => __( 'Gap between posts', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 80,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .mcm-blog--grid' => '--mcm-blog-gap: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .mcm-blog:not(.mcm-blog--grid) .mcm-post' => 'margin-bottom: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);
		$this->add_control(
			'st_divider',
			array(
				'label'     => __( 'Divider colour', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mcm-blog' => '--mcm-blog-line: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();

		$this->mcm_style_section( 'mcm_blog_style_card', __( 'Cards', 'medical-college-manager' ), array( 'condition' => $grid ) );
		$this->mcm_box_style(
			'st_card',
			'.mcm-blog--grid .mcm-post',
			array(
				'lift'    => true,
				'padding' => false,
			)
		);
		$this->mcm_dimensions( 'st_card_pad', __( 'Content padding', 'medical-college-manager' ), $this->mcm_sel( array( '.mcm-blog--grid .mcm-post-body', '.mcm-blog--grid .mcm-post-meta' ) ), 'padding' );
		$this->end_controls_section();

		$this->mcm_style_section( 'mcm_blog_style_image', __( 'Image', 'medical-college-manager' ), array( 'condition' => array( 'show_thumb' => 'yes' ) ) );
		$this->add_responsive_control(
			'st_img_h',
			array(
				'label'      => __( 'Image height', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 80,
						'max' => 600,
					),
				),
				'selectors'  => array( $this->mcm_sel( array( '.mcm-post-thumb img', '.mcm-post-thumb-ph' ) ) => 'height: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->mcm_image_style( 'st_img', '.mcm-post-thumb img', '.mcm-post:hover .mcm-post-thumb img' );
		$this->end_controls_section();

		$this->mcm_style_section( 'mcm_blog_style_date', __( 'Date Badge', 'medical-college-manager' ), array( 'condition' => array( 'show_date' => 'yes' ) ) );
		$this->mcm_text_style( 'st_day', '.mcm-date-badge .mcm-day', array( 'heading' => __( 'Day', 'medical-college-manager' ) ) );
		$this->mcm_text_style( 'st_ym', '.mcm-date-badge .mcm-ym', array( 'heading' => __( 'Month & year', 'medical-college-manager' ) ) );
		$this->mcm_heading( 'st_badge_box', __( 'Badge', 'medical-college-manager' ) );
		$this->mcm_box_style( 'st_badge', '.mcm-date-badge', array( 'hover' => false ) );
		$this->add_control(
			'st_pen_hide',
			array(
				'label'        => __( 'Hide pen tile', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
				'selectors'    => array( '{{WRAPPER}} .mcm-edit-tile' => 'display: none;' ),
			)
		);
		$this->add_control(
			'st_pen_bg',
			array(
				'label'     => __( 'Pen tile background', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mcm-edit-tile' => 'background-color: {{VALUE}};' ),
				'condition' => array( 'st_pen_hide!' => 'yes' ),
			)
		);
		$this->add_control(
			'st_pen_color',
			array(
				'label'     => __( 'Pen colour', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mcm-edit-tile' => 'color: {{VALUE}};' ),
				'condition' => array( 'st_pen_hide!' => 'yes' ),
			)
		);
		$this->end_controls_section();

		$this->mcm_style_section( 'mcm_blog_style_title', __( 'Title', 'medical-college-manager' ) );
		$this->mcm_text_style(
			'st_title',
			array( '.mcm-post-title', '.mcm-post-title a' ),
			array(
				'hover'   => array( '.mcm-post-title a:hover' ),
				'align'   => true,
				'spacing' => true,
			)
		);
		$this->end_controls_section();

		$this->mcm_style_section( 'mcm_blog_style_excerpt', __( 'Excerpt', 'medical-college-manager' ) );
		$this->mcm_text_style(
			'st_excerpt',
			'.mcm-post-excerpt',
			array( 'align' => true )
		);
		$this->end_controls_section();

		$this->mcm_style_section( 'mcm_blog_style_more', __( 'Read More', 'medical-college-manager' ), array( 'condition' => array( 'show_more' => 'yes' ) ) );
		$this->mcm_button_style( 'st_more', 'a.mcm-read-more' );
		$this->end_controls_section();

		$this->mcm_style_section( 'mcm_blog_style_intro', __( 'Intro Text', 'medical-college-manager' ) );
		$this->mcm_text_style(
			'st_intro',
			'.mcm-blog-intro',
			array(
				'align'   => true,
				'spacing' => true,
			)
		);
		$this->end_controls_section();

		$this->mcm_empty_style();
	}

	/**
	 * User ID => name options for authors with published posts (editor only).
	 *
	 * @return array<int,string>
	 */
	private function mcm_author_options() {
		if ( ! $this->mcm_in_editor() ) {
			return array();
		}
		$out = array();
		foreach ( get_users(
			array(
				'has_published_posts' => array( 'post' ),
				'fields'              => array( 'ID', 'display_name' ),
				'number'              => 200,
			)
		) as $u ) {
			$out[ (int) $u->ID ] = $u->display_name;
		}
		return $out;
	}

	/**
	 * Render boundary: a failing widget renders nothing for visitors (and a
	 * short notice for administrators) instead of breaking the page.
	 */
	protected function render() {
		echo MCM_Safe::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped by mcm_output().
			function () {
				ob_start();
				$this->mcm_output();
				return ob_get_clean();
			},
			'Elementor widget ' . $this->get_name()
		);
	}

	/** Unguarded widget output; see render(). */
	protected function mcm_output() {
		$s     = $this->get_settings_for_display();
		$s     = is_array( $s ) ? $s : array();
		$slugs = function ( $key ) use ( $s ) {
			return isset( $s[ $key ] ) ? implode( ',', MCM_Query::normalize_slugs( $s[ $key ] ) ) : '';
		};
		$yes   = function ( $key ) use ( $s ) {
			return ( ! isset( $s[ $key ] ) || 'yes' === $s[ $key ] ) ? 'yes' : 'no';
		};

		$html = MCM_Blog::render(
			array(
				'count'            => ! empty( $s['count'] ) ? (int) $s['count'] : 6,
				'orderby'          => ! empty( $s['orderby'] ) ? $s['orderby'] : 'date',
				'order'            => ( isset( $s['order'] ) && 'ASC' === $s['order'] ) ? 'ASC' : 'DESC',
				'category'         => $slugs( 'category' ),
				'exclude_category' => $slugs( 'exclude_category' ),
				'tag'              => $slugs( 'tag' ),
				'author'           => ! empty( $s['author'] ) ? (string) absint( $s['author'] ) : '',
				'offset'           => isset( $s['offset'] ) ? absint( $s['offset'] ) : 0,
				'excerpt'          => isset( $s['excerpt'] ) ? (int) $s['excerpt'] : 30,
				'intro'            => ! empty( $s['intro'] ) ? $s['intro'] : '',
				'read_more'        => ! empty( $s['read_more'] ) ? $s['read_more'] : '',
				'layout'           => ( isset( $s['layout'] ) && 'grid' === $s['layout'] ) ? 'grid' : 'list',
				'columns'          => ! empty( $s['columns'] ) ? (int) $s['columns'] : 3,
				'show_thumb'       => $yes( 'show_thumb' ),
				'show_date'        => $yes( 'show_date' ),
				'show_more'        => $yes( 'show_more' ),
				'empty'            => isset( $s['empty'] ) ? sanitize_text_field( MCM_Safe::str( $s['empty'] ) ) : '',
			)
		);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- MCM_Blog::render() escapes everything it outputs.
	}
}

/**
 * Base for the three notice widgets — shared controls + settings mapping.
 */
abstract class MCM_Widget_Notice_Base extends \Elementor\Widget_Base {

	use MCM_Elementor_Style;

	/**
	 * Department options (slug => name) for the department control.
	 *
	 * @return array<string,string>
	 */
	protected static function mcm_department_options() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'mcm_department',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		$out = array();
		foreach ( $terms as $t ) {
			$out[ $t->slug ] = $t->name;
		}
		return $out;
	}

	/**
	 * Notice Category options (slug => name) for the category control.
	 *
	 * @return array<string,string>
	 */
	protected static function mcm_notice_cat_options() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'mcm_notice_cat',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		$out = array();
		foreach ( $terms as $t ) {
			$out[ $t->slug ] = $t->name;
		}
		return $out;
	}

	/**
	 * Elementor panel category.
	 */
	public function get_categories() {
		return array( 'medical-college-manager' );
	}
	/**
	 * Stylesheets this widget needs.
	 */
	public function get_style_depends() {
		return array( 'mcm-frontend' );
	}
	/**
	 * Scripts this widget needs.
	 */
	public function get_script_depends() {
		return array( 'mcm-frontend' );
	}

	/**
	 * Controls shared by the three notice widgets.
	 */
	protected function mcm_common_controls() {
		$this->add_control(
			'count',
			array(
				'label'   => __( 'Number of notices', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 50,
				'default' => 10,
			)
		);
		$this->add_control(
			'category',
			array(
				'label'       => __( 'Categories', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => self::mcm_notice_cat_options(),
			)
		);
		$this->add_control(
			'priority',
			array(
				'label'   => __( 'Priority', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''          => __( 'Any', 'medical-college-manager' ),
					'Urgent'    => __( 'Urgent', 'medical-college-manager' ),
					'Important' => __( 'Important', 'medical-college-manager' ),
					'Normal'    => __( 'Normal', 'medical-college-manager' ),
				),
			)
		);
		$this->add_control(
			'include',
			array(
				'label'       => __( 'Include', 'medical-college-manager' ),
				'description' => __( 'Leave empty to use Settings → Notice board sources. Category and priority apply to notices only.', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'default'     => array(),
				'options'     => array(
					'notice' => __( 'Notices', 'medical-college-manager' ),
					'job'    => __( 'Open jobs', 'medical-college-manager' ),
					'tender' => __( 'Open tenders', 'medical-college-manager' ),
				),
			)
		);
		$this->add_control(
			'department',
			array(
				'label'   => __( 'Department', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array( '' => __( 'Any', 'medical-college-manager' ) ) + self::mcm_department_options(),
			)
		);
		$this->add_control(
			'days',
			array(
				'label'       => __( 'Only from the last … days', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 365,
				'default'     => 0,
				'description' => __( '0 shows all current notices.', 'medical-college-manager' ),
			)
		);
		$this->add_control(
			'show_date',
			array(
				'label'        => __( 'Show date', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'show_badge',
			array(
				'label'        => __( 'Show category badge', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
	}

	/**
	 * Style tab for one notice widget.
	 *
	 * @param string $kind ticker | board | list.
	 */
	protected function mcm_notice_style( $kind ) {
		if ( 'ticker' === $kind ) {
			$this->mcm_style_section( 'mcm_ns_box', __( 'Bar', 'medical-college-manager' ) );
			$this->mcm_box_style( 'st_box', '.mcm-ticker', array( 'hover' => false ) );
			$this->add_responsive_control(
				'st_bar_h',
				array(
					'label'      => __( 'Height', 'medical-college-manager' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 28,
							'max' => 90,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .mcm-ticker-item' => 'height: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->end_controls_section();

			$this->mcm_style_section( 'mcm_ns_label', __( 'Label', 'medical-college-manager' ) );
			$this->mcm_text_style( 'st_label', '.mcm-ticker-label' );
			$this->add_control(
				'st_label_bg',
				array(
					'label'     => __( 'Background colour', 'medical-college-manager' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .mcm-ticker-label' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->mcm_dimensions( 'st_label_pad', __( 'Padding', 'medical-college-manager' ), '{{WRAPPER}} .mcm-ticker-label', 'padding' );
			$this->end_controls_section();

			$this->mcm_style_section( 'mcm_ns_items', __( 'Notices', 'medical-college-manager' ) );
			$this->mcm_text_style( 'st_item', '.mcm-ticker-text', array( 'hover' => array( '.mcm-ticker-item:hover .mcm-ticker-text', '.mcm-ticker-item:focus-visible .mcm-ticker-text' ) ) );
			$this->mcm_dimensions( 'st_item_pad', __( 'Padding', 'medical-college-manager' ), '{{WRAPPER}} .mcm-ticker-item', 'padding' );
			$this->end_controls_section();
		} elseif ( 'board' === $kind ) {
			$this->mcm_style_section( 'mcm_ns_box', __( 'Box', 'medical-college-manager' ) );
			$this->mcm_box_style( 'st_box', '.mcm-board', array( 'hover' => false ) );
			$this->end_controls_section();

			$this->mcm_style_section( 'mcm_ns_head', __( 'Heading Bar', 'medical-college-manager' ) );
			$this->mcm_text_style( 'st_head', '.mcm-board-title' );
			$this->add_control(
				'st_head_bg',
				array(
					'label'     => __( 'Background colour', 'medical-college-manager' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .mcm-board-head' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'st_head_icon',
				array(
					'label'     => __( 'Pause button colour', 'medical-college-manager' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .mcm-board-head .mcm-scroll-pause' => 'color: {{VALUE}}; border-color: {{VALUE}};' ),
				)
			);
			$this->mcm_dimensions( 'st_head_pad', __( 'Padding', 'medical-college-manager' ), '{{WRAPPER}} .mcm-board-head', 'padding' );
			$this->end_controls_section();

			$this->mcm_style_section( 'mcm_ns_items', __( 'Notices', 'medical-college-manager' ) );
			$this->mcm_text_style( 'st_item', '.mcm-board-item-title', array( 'hover' => array( '.mcm-board-item-title:hover', '.mcm-board-item-title:focus-visible' ) ) );
			$this->mcm_notice_rows( '.mcm-board-item' );
			$this->end_controls_section();

			$this->mcm_style_section( 'mcm_ns_more', __( 'View All Link', 'medical-college-manager' ), array( 'condition' => array( 'more' => 'yes' ) ) );
			$this->mcm_button_style( 'st_more', 'a.mcm-board-more' );
			$this->end_controls_section();
		} else {
			$this->mcm_style_section( 'mcm_ns_box', __( 'Box', 'medical-college-manager' ) );
			$this->mcm_box_style( 'st_box', '.mcm-notice-list', array( 'hover' => false ) );
			$this->end_controls_section();

			$this->mcm_style_section( 'mcm_ns_items', __( 'Notices', 'medical-college-manager' ) );
			$this->mcm_text_style( 'st_item', '.mcm-notice-link', array( 'hover' => array( '.mcm-notice-link:hover', '.mcm-notice-link:focus-visible' ) ) );
			$this->mcm_notice_rows( '.mcm-notice-row' );
			$this->add_control(
				'st_row_stripe',
				array(
					'label'     => __( 'Striped row background', 'medical-college-manager' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .mcm-notice-row:nth-child(even)' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->end_controls_section();
		}

		$this->mcm_style_section( 'mcm_ns_date', __( 'Date', 'medical-college-manager' ), array( 'condition' => array( 'show_date' => 'yes' ) ) );
		$this->mcm_text_style( 'st_date', '.mcm-notice-date' );
		$this->end_controls_section();

		$this->mcm_style_section( 'mcm_ns_badges', __( 'Badges', 'medical-college-manager' ) );
		$this->mcm_badge_style( 'st_badge', '.mcm-notice-badge', __( 'Category badge', 'medical-college-manager' ) );
		$this->mcm_badge_style( 'st_new', '.mcm-notice-new', __( '"New" tag', 'medical-college-manager' ) );
		$this->mcm_text_style( 'st_deadline', '.mcm-notice-deadline', array( 'heading' => __( 'Deadline text', 'medical-college-manager' ) ) );
		$this->add_control(
			'st_urgent',
			array(
				'label'     => __( 'Urgent colour', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}} .mcm-notice--urgent' => 'border-left-color: {{VALUE}};',
					'{{WRAPPER}} .mcm-notice--urgent .mcm-notice-badge' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->end_controls_section();

		$this->mcm_empty_style();
	}

	/**
	 * Row padding, divider and hover background for board and list rows.
	 *
	 * @param string $row Row selector.
	 */
	private function mcm_notice_rows( $row ) {
		$this->mcm_dimensions( 'st_row_pad', __( 'Row padding', 'medical-college-manager' ), '{{WRAPPER}} ' . $row, 'padding' );
		$this->add_control(
			'st_row_line',
			array(
				'label'     => __( 'Divider colour', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} ' . $row => 'border-bottom-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'st_row_hover',
			array(
				'label'     => __( 'Row hover background', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} ' . $row . ':hover' => 'background-color: {{VALUE}};' ),
			)
		);
	}

	/**
	 * Map the widget settings to notice shortcode attributes.
	 */
	protected function mcm_atts() {
		$s   = $this->get_settings_for_display();
		$cat = isset( $s['category'] ) ? $s['category'] : '';
		if ( is_array( $cat ) ) {
			$cat = implode( ',', $cat );
		}
		$include = isset( $s['include'] ) ? $s['include'] : array();
		$include = is_array( $include ) ? implode( ',', array_map( 'sanitize_key', $include ) ) : sanitize_key( MCM_Safe::str( $include ) );
		return MCM_Notice::atts(
			array(
				'count'      => ! empty( $s['count'] ) ? (int) $s['count'] : 10,
				'category'   => $cat,
				'include'    => $include,
				'department' => isset( $s['department'] ) ? sanitize_title( MCM_Safe::str( $s['department'] ) ) : '',
				'priority'   => ! empty( $s['priority'] ) ? $s['priority'] : '',
				'days'       => ! empty( $s['days'] ) ? absint( $s['days'] ) : 0,
				'speed'      => ! empty( $s['speed'] ) ? (int) $s['speed'] : 5,
				'label'      => ! empty( $s['label'] ) ? $s['label'] : '',
				'title'      => ! empty( $s['title'] ) ? $s['title'] : '',
				'height'     => ! empty( $s['height'] ) ? (int) $s['height'] : 340,
				'more'       => ( ! isset( $s['more'] ) || 'yes' === $s['more'] ) ? 'yes' : 'no',
				'show_date'  => ( ! isset( $s['show_date'] ) || 'yes' === $s['show_date'] ) ? 'yes' : 'no',
				'show_badge' => ( ! isset( $s['show_badge'] ) || 'yes' === $s['show_badge'] ) ? 'yes' : 'no',
			)
		);
	}
}

/**
 * Elementor widget: Notice Ticker.
 */
class MCM_Widget_Notice_Ticker extends MCM_Widget_Notice_Base {
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_notice_ticker';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Notice Ticker', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-slider-push';
	}
	/**
	 * Search keywords for the Elementor panel.
	 */
	public function get_keywords() {
		return array( 'notice', 'ticker', 'marquee', 'news', 'medical-college-manager' );
	}
	/**
	 * Register the widget controls.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'mcm_notice', array( 'label' => __( 'Notice Ticker', 'medical-college-manager' ) ) );
		$this->add_control(
			'label',
			array(
				'label'       => __( 'Label', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'Latest', 'medical-college-manager' ),
			)
		);
		$this->mcm_common_controls();
		$this->add_control(
			'speed',
			array(
				'label'   => __( 'Speed (1 slow - 10 fast)', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 10,
				'default' => 5,
			)
		);
		$this->end_controls_section();
		$this->mcm_notice_style( 'ticker' );
	}
	/**
	 * Render boundary: a failing widget renders nothing for visitors (and a
	 * short notice for administrators) instead of breaking the page.
	 */
	protected function render() {
		echo MCM_Safe::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped by mcm_output().
			function () {
				ob_start();
				$this->mcm_output();
				return ob_get_clean();
			},
			'Elementor widget ' . $this->get_name()
		);
	}

	/** Unguarded widget output; see render(). */
	protected function mcm_output() {
		echo MCM_Notice::render_ticker( $this->mcm_atts() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Elementor widget: Notice Board.
 */
class MCM_Widget_Notice_Board extends MCM_Widget_Notice_Base {
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_notice_board';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Notice Board', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-post-list';
	}
	/**
	 * Search keywords for the Elementor panel.
	 */
	public function get_keywords() {
		return array( 'notice', 'board', 'announcements', 'scroll', 'medical-college-manager' );
	}
	/**
	 * Register the widget controls.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'mcm_notice', array( 'label' => __( 'Notice Board', 'medical-college-manager' ) ) );
		$this->add_control(
			'title',
			array(
				'label'       => __( 'Heading', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'Notice Board', 'medical-college-manager' ),
			)
		);
		$this->mcm_common_controls();
		$this->add_control(
			'height',
			array(
				'label'   => __( 'Height (px)', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 160,
				'max'     => 800,
				'default' => 340,
			)
		);
		$this->add_control(
			'speed',
			array(
				'label'   => __( 'Scroll speed (1 slow - 10 fast)', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 10,
				'default' => 5,
			)
		);
		$this->add_control(
			'more',
			array(
				'label'        => __( 'Show "View all" link', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->end_controls_section();
		$this->mcm_notice_style( 'board' );
	}
	/**
	 * Render boundary: a failing widget renders nothing for visitors (and a
	 * short notice for administrators) instead of breaking the page.
	 */
	protected function render() {
		echo MCM_Safe::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped by mcm_output().
			function () {
				ob_start();
				$this->mcm_output();
				return ob_get_clean();
			},
			'Elementor widget ' . $this->get_name()
		);
	}

	/** Unguarded widget output; see render(). */
	protected function mcm_output() {
		echo MCM_Notice::render_board( $this->mcm_atts() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Elementor widget: Notices.
 */
class MCM_Widget_Notices extends MCM_Widget_Notice_Base {
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_notices';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Notices List', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-bullet-list';
	}
	/**
	 * Search keywords for the Elementor panel.
	 */
	public function get_keywords() {
		return array( 'notice', 'list', 'announcements', 'medical-college-manager' );
	}
	/**
	 * Register the widget controls.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'mcm_notice', array( 'label' => __( 'Notices List', 'medical-college-manager' ) ) );
		$this->mcm_common_controls();
		$this->end_controls_section();
		$this->mcm_notice_style( 'list' );
	}
	/**
	 * Render boundary: a failing widget renders nothing for visitors (and a
	 * short notice for administrators) instead of breaking the page.
	 */
	protected function render() {
		echo MCM_Safe::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped by mcm_output().
			function () {
				ob_start();
				$this->mcm_output();
				return ob_get_clean();
			},
			'Elementor widget ' . $this->get_name()
		);
	}

	/** Unguarded widget output; see render(). */
	protected function mcm_output() {
		echo MCM_Notice::render_list( $this->mcm_atts() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Elementor widget: Testimonial / Message — a heading, a message, a photo and
 * the person's name, designation and organisation (e.g. "Message from the
 * Head of Department"). The photo sits left, right or on top, per device.
 */
class MCM_Widget_Testimonial extends \Elementor\Widget_Base {

	use MCM_Elementor_Style;

	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_testimonial';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Testimonial / Message', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-testimonial';
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
		return array( 'testimonial', 'message', 'quote', 'head of department', 'hod', 'chairperson', 'principal', 'medical-college-manager' );
	}
	/**
	 * Stylesheets this widget needs.
	 */
	public function get_style_depends() {
		return array( 'mcm-frontend' );
	}

	/**
	 * Register the widget controls.
	 */
	protected function register_controls() {
		/* ---- Content ---- */
		$this->start_controls_section( 'mcm_testi_content', array( 'label' => __( 'Message', 'medical-college-manager' ) ) );

		$this->add_control(
			'title',
			array(
				'label'       => __( 'Heading', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Message from Head of Department', 'medical-college-manager' ),
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
			)
		);
		$this->add_control(
			'title_tag',
			array(
				'label'     => __( 'Heading HTML tag', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'h2',
				'options'   => array(
					'h1'  => 'H1',
					'h2'  => 'H2',
					'h3'  => 'H3',
					'h4'  => 'H4',
					'h5'  => 'H5',
					'h6'  => 'H6',
					'div' => 'div',
					'p'   => 'p',
				),
				'condition' => array( 'title!' => '' ),
			)
		);
		$this->add_control(
			'title_full',
			array(
				'label'        => __( 'Heading across the full width', 'medical-college-manager' ),
				'description'  => __( 'Off: the heading sits beside the photo, above the message.', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'title!' => '' ),
			)
		);

		$this->add_control(
			'message',
			array(
				'label'     => __( 'Message', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::WYSIWYG,
				'default'   => '<p>' . __( 'Welcome to our department. Write the message here.', 'medical-college-manager' ) . '</p>',
				'dynamic'   => array( 'active' => true ),
				'separator' => 'before',
			)
		);
		$this->add_control(
			'quote',
			array(
				'label'        => __( 'Quotation mark', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'image',
			array(
				'label'     => __( 'Photo', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::MEDIA,
				'default'   => array( 'url' => '' ),
				'dynamic'   => array( 'active' => true ),
				'separator' => 'before',
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Image_Size::get_type(),
			array(
				'name'      => 'image',
				'default'   => 'medium_large',
				'condition' => array( 'image[url]!' => '' ),
			)
		);

		$this->add_control(
			'name',
			array(
				'label'       => __( 'Name', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'Prof. Dr. Name', 'medical-college-manager' ),
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
				'separator'   => 'before',
			)
		);
		$this->add_control(
			'designation',
			array(
				'label'       => __( 'Designation', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'Chairperson, Department of Pathology', 'medical-college-manager' ),
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
			)
		);
		$this->add_control(
			'organization',
			array(
				'label'       => __( 'Organisation / place', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'Gomal Medical College, Dera Ismail Khan', 'medical-college-manager' ),
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
			)
		);
		$this->add_control(
			'link',
			array(
				'label'       => __( 'Link (name and photo)', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => __( 'https://… e.g. the profile page', 'medical-college-manager' ),
				'dynamic'     => array( 'active' => true ),
			)
		);
		$this->end_controls_section();

		/* ---- Layout ---- */
		$this->start_controls_section( 'mcm_testi_layout', array( 'label' => __( 'Layout', 'medical-college-manager' ) ) );
		$this->add_responsive_control(
			'image_position',
			array(
				'label'          => __( 'Photo position', 'medical-college-manager' ),
				'type'           => \Elementor\Controls_Manager::CHOOSE,
				'default'        => 'right',
				'tablet_default' => 'right',
				'mobile_default' => 'top',
				'options'        => array(
					'left'  => array(
						'title' => __( 'Left', 'medical-college-manager' ),
						'icon'  => 'eicon-h-align-left',
					),
					'top'   => array(
						'title' => __( 'Top', 'medical-college-manager' ),
						'icon'  => 'eicon-v-align-top',
					),
					'right' => array(
						'title' => __( 'Right', 'medical-college-manager' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'prefix_class'   => 'mcm-testi-pos%s-',
				'toggle'         => false,
			)
		);
		$this->add_responsive_control(
			'image_valign',
			array(
				'label'     => __( 'Vertical alignment (photo left/right)', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => __( 'Top', 'medical-college-manager' ),
						'icon'  => 'eicon-v-align-top',
					),
					'center'     => array(
						'title' => __( 'Middle', 'medical-college-manager' ),
						'icon'  => 'eicon-v-align-middle',
					),
					'flex-end'   => array(
						'title' => __( 'Bottom', 'medical-college-manager' ),
						'icon'  => 'eicon-v-align-bottom',
					),
				),
				'selectors' => array( '{{WRAPPER}} .mcm-testi' => '--mcm-testi-valign: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'image_halign',
			array(
				'label'     => __( 'Photo alignment (photo on top)', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => __( 'Left', 'medical-college-manager' ),
						'icon'  => 'eicon-h-align-left',
					),
					'center'     => array(
						'title' => __( 'Centre', 'medical-college-manager' ),
						'icon'  => 'eicon-h-align-center',
					),
					'flex-end'   => array(
						'title' => __( 'Right', 'medical-college-manager' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'selectors' => array( '{{WRAPPER}} .mcm-testi' => '--mcm-testi-halign: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'image_width',
			array(
				'label'          => __( 'Photo width', 'medical-college-manager' ),
				'type'           => \Elementor\Controls_Manager::SLIDER,
				'size_units'     => array( '%', 'px' ),
				'range'          => array(
					'%'  => array(
						'min' => 10,
						'max' => 100,
					),
					'px' => array(
						'min' => 60,
						'max' => 800,
					),
				),
				'default'        => array(
					'unit' => '%',
					'size' => 28,
				),
				'mobile_default' => array(
					'unit' => '%',
					'size' => 70,
				),
				'selectors'      => array( '{{WRAPPER}} .mcm-testi' => '--mcm-testi-img-w: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'label'      => __( 'Space between photo and text', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 120,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .mcm-testi' => '--mcm-testi-gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();

		$this->register_style_controls();
	}

	/**
	 * Style tab.
	 */
	protected function register_style_controls() {
		$this->mcm_style_section( 'mcm_testi_style_box', __( 'Box', 'medical-college-manager' ) );
		$this->mcm_box_style( 'st_box', '.mcm-testi' );
		$this->end_controls_section();

		$this->mcm_style_section( 'mcm_testi_style_title', __( 'Heading', 'medical-college-manager' ), array( 'condition' => array( 'title!' => '' ) ) );
		$this->mcm_text_style(
			'st_title',
			'.mcm-testi-title',
			array(
				'align'   => true,
				'spacing' => true,
			)
		);
		$this->end_controls_section();

		$this->mcm_style_section( 'mcm_testi_style_message', __( 'Message', 'medical-college-manager' ) );
		$this->mcm_text_style( 'st_msg', '.mcm-testi-message', array( 'spacing' => true ) );
		$this->add_responsive_control(
			'st_msg_align',
			array(
				'label'     => __( 'Alignment', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => $this->mcm_align_options() + array(
					'justify' => array(
						'title' => __( 'Justified', 'medical-college-manager' ),
						'icon'  => 'eicon-text-align-justify',
					),
				),
				'selectors' => array( '{{WRAPPER}} .mcm-testi-message' => 'text-align: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'st_msg_para',
			array(
				'label'      => __( 'Space between paragraphs', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 60,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .mcm-testi-message p' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'st_link_color',
			array(
				'label'     => __( 'Link colour in the message', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mcm-testi-message a' => 'color: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();

		$this->mcm_style_section( 'mcm_testi_style_quote', __( 'Quotation Mark', 'medical-college-manager' ), array( 'condition' => array( 'quote' => 'yes' ) ) );
		$this->add_control(
			'st_quote_color',
			array(
				'label'     => __( 'Colour', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mcm-testi-quote' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'st_quote_size',
			array(
				'label'      => __( 'Size', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 20,
						'max' => 200,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .mcm-testi-quote' => 'font-size: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();

		$this->mcm_style_section( 'mcm_testi_style_image', __( 'Photo', 'medical-college-manager' ), array( 'condition' => array( 'image[url]!' => '' ) ) );
		$this->add_responsive_control(
			'st_img_h',
			array(
				'label'      => __( 'Height (empty = natural)', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'vh' ),
				'range'      => array(
					'px' => array(
						'min' => 60,
						'max' => 800,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .mcm-testi-media img' => 'height: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->mcm_image_style( 'st_img', '.mcm-testi-media img', '.mcm-testi-media:hover img' );
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'      => 'st_img_border',
				'selector'  => '{{WRAPPER}} .mcm-testi-media img',
				'separator' => 'before',
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'st_img_shadow',
				'selector' => '{{WRAPPER}} .mcm-testi-media img',
			)
		);
		$this->end_controls_section();

		$this->mcm_style_section( 'mcm_testi_style_author', __( 'Name & Designation', 'medical-college-manager' ) );
		$this->add_responsive_control(
			'st_author_align',
			array(
				'label'     => __( 'Alignment', 'medical-college-manager' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => $this->mcm_align_options(),
				'selectors' => array( '{{WRAPPER}} .mcm-testi-author' => 'text-align: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'st_author_space',
			array(
				'label'      => __( 'Space above', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 80,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .mcm-testi-author' => 'margin-top: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->mcm_text_style(
			'st_name',
			'.mcm-testi-name',
			array(
				'heading' => __( 'Name', 'medical-college-manager' ),
				'hover'   => array( '.mcm-testi-name a:hover', '.mcm-testi-name a:focus-visible' ),
			)
		);
		$this->mcm_text_style( 'st_desig', '.mcm-testi-designation', array( 'heading' => __( 'Designation', 'medical-college-manager' ) ) );
		$this->mcm_text_style( 'st_org', '.mcm-testi-org', array( 'heading' => __( 'Organisation', 'medical-college-manager' ) ) );
		$this->end_controls_section();
	}

	/**
	 * Render boundary: a failing widget renders nothing for visitors (and a
	 * short notice for administrators) instead of breaking the page.
	 */
	protected function render() {
		echo MCM_Safe::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped by mcm_output().
			function () {
				ob_start();
				$this->mcm_output();
				return ob_get_clean();
			},
			'Elementor widget ' . $this->get_name()
		);
	}

	/** Unguarded widget output; see render(). */
	protected function mcm_output() {
		$s     = $this->get_settings_for_display();
		$s     = is_array( $s ) ? $s : array();
		$text  = function ( $key ) use ( $s ) {
			return isset( $s[ $key ] ) ? trim( wp_strip_all_tags( MCM_Safe::str( $s[ $key ] ) ) ) : '';
		};
		$title = $text( 'title' );
		$name  = $text( 'name' );
		$tag   = isset( $s['title_tag'] ) && in_array( $s['title_tag'], array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p' ), true ) ? $s['title_tag'] : 'h2';
		$full  = ! isset( $s['title_full'] ) || 'yes' === $s['title_full'];
		$msg   = isset( $s['message'] ) ? MCM_Safe::str( $s['message'] ) : '';
		$url   = ( isset( $s['link']['url'] ) && '' !== $s['link']['url'] ) ? $s['link'] : null;
		$uid   = 'mcm-testi-' . $this->get_id();

		// The photo: chosen size with srcset; alt text from the Media Library,
		// else the person's name.
		$img = '';
		if ( ! empty( $s['image']['url'] ) ) {
			$img = \Elementor\Group_Control_Image_Size::get_attachment_image_html( $s, 'image', 'image' );
			if ( '' !== $name && false !== strpos( $img, 'alt=""' ) ) {
				$img = str_replace( 'alt=""', 'alt="' . esc_attr( $name ) . '"', $img );
			}
		}
		if ( $url ) {
			$this->add_link_attributes( 'mcm_link', $url );
		}
		$link = function ( $inner, $extra = '' ) use ( $url ) {
			return $url ? '<a ' . $this->get_render_attribute_string( 'mcm_link' ) . $extra . '>' . $inner . '</a>' : $inner;
		};

		$heading = '' !== $title ? sprintf( '<%1$s class="mcm-testi-title" id="%3$s">%2$s</%1$s>', $tag, esc_html( $title ), esc_attr( $uid ) ) : '';

		$out  = '<section class="mcm-testi"' . ( '' !== $heading ? ' aria-labelledby="' . esc_attr( $uid ) . '"' : '' ) . '>';
		$out .= $full ? $heading : '';
		$out .= '<div class="mcm-testi-inner">';
		if ( '' !== $img ) {
			// The linked photo repeats the name link: hidden from tab order and screen readers.
			$out .= '<div class="mcm-testi-media">' . $link( $img, ' tabindex="-1" aria-hidden="true"' ) . '</div>';
		}
		$out .= '<div class="mcm-testi-content">';
		$out .= $full ? '' : $heading;
		if ( isset( $s['quote'] ) && 'yes' === $s['quote'] ) {
			$out .= '<span class="mcm-testi-quote" aria-hidden="true">&ldquo;</span>';
		}
		if ( '' !== trim( wp_strip_all_tags( $msg ) ) ) {
			$out .= '<blockquote class="mcm-testi-message">' . wp_kses_post( wpautop( $msg ) ) . '</blockquote>';
		}
		$lines = array(
			'name'        => '' !== $name ? $link( esc_html( $name ) ) : '',
			'designation' => esc_html( $text( 'designation' ) ),
			'org'         => esc_html( $text( 'organization' ) ),
		);
		$lines = array_filter( $lines );
		if ( $lines ) {
			$out .= '<div class="mcm-testi-author">';
			foreach ( $lines as $key => $html ) {
				$out .= '<p class="mcm-testi-' . $key . '">' . $html . '</p>';
			}
			$out .= '</div>';
		}
		$out .= '</div></div></section>';

		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every part escaped above (esc_html, wp_kses_post, Elementor's image and link helpers).
	}
}
