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
 * Shared base: builds the control panel and renders through the same engine
 * the shortcodes use, so views, AJAX filtering and connectors all work.
 */
abstract class MCM_Elementor_Widget_Base extends \Elementor\Widget_Base {

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
	 * Register the widget controls.
	 */
	protected function register_controls() {
		$type = $this->mcm_type();

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

		$this->add_control(
			'orderby',
			array(
				'label'   => __( 'Order by', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'date',
				'options' => array(
					'date'       => __( 'Date', 'medical-college-manager' ),
					'title'      => __( 'Title', 'medical-college-manager' ),
					'menu_order' => __( 'Menu order', 'medical-college-manager' ),
					'rand'       => __( 'Random', 'medical-college-manager' ),
				),
			)
		);

		$this->add_control(
			'order',
			array(
				'label'   => __( 'Order', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'DESC',
				'options' => array(
					'DESC' => __( 'Descending', 'medical-college-manager' ),
					'ASC'  => __( 'Ascending', 'medical-college-manager' ),
				),
			)
		);

		$this->mcm_extra_controls();

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
			'print',
			array(
				'label'        => __( 'Print button', 'medical-college-manager' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->end_controls_section();

		/* ---- Optional pre-filter scope ---- */
		$map   = MCM_Query::tax_map();
		$taxes = isset( $map[ $type ] ) ? $map[ $type ] : array();
		if ( $taxes ) {
			$this->start_controls_section( 'mcm_scope', array( 'label' => __( 'Pre-filter (optional)', 'medical-college-manager' ) ) );

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
				$tobj = get_taxonomy( $tax );
				$this->add_control(
					'tax_' . $tax,
					array(
						'label'       => $tobj ? $tobj->labels->singular_name : $tax,
						'type'        => \Elementor\Controls_Manager::SELECT2,
						'options'     => $options,
						'multiple'    => true,
						'label_block' => true,
						'default'     => array(),
					)
				);
			}

			$this->end_controls_section();
		}

		$this->register_style_controls();
	}

	/**
	 * Style tab — every control writes one of the plugin's CSS variables onto
	 * {{WRAPPER}}, so it cascades to the cards, filters and headings inside and
	 * overrides the global design just for this widget. Fully responsive where
	 * it matters (spacing, image height).
	 */
	protected function register_style_controls() {
		$wrapper = '{{WRAPPER}}';

		/* ---- Style: Layout & spacing ---- */
		$this->start_controls_section(
			'mcm_style_layout',
			array(
				'label' => __( 'Layout & Spacing', 'medical-college-manager' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

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

		$this->end_controls_section();

		/* ---- Style: Colours ---- */
		$this->start_controls_section(
			'mcm_style_colors',
			array(
				'label' => __( 'Colours', 'medical-college-manager' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

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

		/* ---- Style: Titles (real typography group — loads fonts properly) ---- */
		$this->start_controls_section(
			'mcm_style_type',
			array(
				'label' => __( 'Titles', 'medical-college-manager' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

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

		$this->end_controls_section();

		/* ---- Style: Cards ---- */
		$this->start_controls_section(
			'mcm_style_card',
			array(
				'label' => __( 'Cards', 'medical-college-manager' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'st_card_shadow',
				'label'    => __( 'Card shadow', 'medical-college-manager' ),
				'selector' => $wrapper . ' .mcm-card, ' . $wrapper . ' .mcm-dir-card, ' . $wrapper . ' .mcm-list-item',
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Term slug => name options for a taxonomy.
	 *
	 * @param string $tax Taxonomy name.
	 */
	private function mcm_term_options( $tax ) {
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
		$type = $this->mcm_type();
		$view = in_array( isset( $s['view'] ) ? $s['view'] : '', array( 'grid', 'list', 'table', 'directory', 'citation' ), true ) ? $s['view'] : $this->mcm_default_view();

		$atts = array(
			'type'    => $type,
			'view'    => $view,
			'columns' => max( 1, min( 6, (int) ( ! empty( $s['columns'] ) ? $s['columns'] : 3 ) ) ),
			'count'   => max( 1, (int) ( ! empty( $s['count'] ) ? $s['count'] : 12 ) ),
			'filters' => ( isset( $s['filters'] ) && 'yes' === $s['filters'] ) ? 'yes' : 'no',
			'print'   => ( isset( $s['print'] ) && 'yes' === $s['print'] ) ? 'yes' : 'no',
			'orderby' => ! empty( $s['orderby'] ) ? $s['orderby'] : 'date',
			'order'   => ( isset( $s['order'] ) && 'ASC' === $s['order'] ) ? 'ASC' : 'DESC',
		);

		$map   = MCM_Query::tax_map();
		$taxes = isset( $map[ $type ] ) ? $map[ $type ] : array();
		// Pre-filters are the AUTHOR's scope (locked; visitors narrow within
		// it), not a visitor filter. A control saved before 1.30.2 holds a
		// single slug string; newer ones hold an array — both are accepted.
		$prefilter = array();
		foreach ( $taxes as $tax ) {
			$key = 'tax_' . $tax;
			if ( ! empty( $s[ $key ] ) ) {
				$prefilter[ $tax ] = MCM_Query::normalize_slugs( $s[ $key ] );
			}
		}
		if ( $prefilter ) {
			$atts['prefilter'] = $prefilter;
		}
		$atts = $this->mcm_extra_atts( $atts, is_array( $s ) ? $s : array() );

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
			'category',
			array(
				'label'       => __( 'Categories', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->mcm_category_options(),
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
					'title'         => __( 'Title', 'medical-college-manager' ),
					'comment_count' => __( 'Most commented', 'medical-college-manager' ),
					'rand'          => __( 'Random', 'medical-college-manager' ),
				),
			)
		);

		$this->add_control(
			'order',
			array(
				'label'   => __( 'Order', 'medical-college-manager' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'DESC',
				'options' => array(
					'DESC' => __( 'Newest first', 'medical-college-manager' ),
					'ASC'  => __( 'Oldest first', 'medical-college-manager' ),
				),
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
			'read_more',
			array(
				'label'       => __( 'Read-more label', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'Read More', 'medical-college-manager' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Blog category options (slug => name).
	 */
	private function mcm_category_options() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
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
		$s   = $this->get_settings_for_display();
		$cat = isset( $s['category'] ) ? $s['category'] : '';
		if ( is_array( $cat ) ) {
			$cat = implode( ',', $cat );
		}

		$html = MCM_Blog::render(
			array(
				'count'     => ! empty( $s['count'] ) ? (int) $s['count'] : 6,
				'orderby'   => ! empty( $s['orderby'] ) ? $s['orderby'] : 'date',
				'order'     => ( isset( $s['order'] ) && 'ASC' === $s['order'] ) ? 'ASC' : 'DESC',
				'category'  => $cat,
				'excerpt'   => isset( $s['excerpt'] ) ? (int) $s['excerpt'] : 30,
				'intro'     => ! empty( $s['intro'] ) ? $s['intro'] : '',
				'read_more' => ! empty( $s['read_more'] ) ? $s['read_more'] : '',
			)
		);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- MCM_Blog::render() escapes everything it outputs.
	}
}

/**
 * Base for the three notice widgets — shared controls + settings mapping.
 */
abstract class MCM_Widget_Notice_Base extends \Elementor\Widget_Base {

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

/*
 * ----------------------------------------------------------------
 * Department page sections (1.32) — one widget per fixed section.
 * ----------------------------------------------------------------
 */

/**
 * Shared base for the department section widgets: pick the department (or
 * use the department being viewed) and restyle the colours.
 */
abstract class MCM_Widget_Department_Base extends \Elementor\Widget_Base {

	/** Section key (see MCM_Department_Page::SECTIONS). */
	abstract protected function mcm_section();

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
	 * Search keywords for the Elementor panel.
	 */
	public function get_keywords() {
		return array( 'department', 'hod', 'faculty', 'medical-college-manager' );
	}

	/**
	 * Register the widget controls.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'mcm_dept', array( 'label' => __( 'Department', 'medical-college-manager' ) ) );
		$options = array( '' => __( '— The department being viewed —', 'medical-college-manager' ) );
		$terms   = get_terms(
			array(
				'taxonomy'   => 'mcm_department',
				'hide_empty' => false,
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$options[ $t->slug ] = $t->name;
			}
		}
		$this->add_control(
			'department',
			array(
				'label'       => __( 'Department', 'medical-college-manager' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => '',
				'options'     => $options,
				'description' => __( 'Content comes from Departments → Edit. Section titles, the empty-section rule and the faculty layout come from College Manager → Department Page.', 'medical-college-manager' ),
			)
		);
		if ( 'hero' === $this->mcm_section() ) {
			$this->add_control(
				'title_tag',
				array(
					'label'   => __( 'Name tag', 'medical-college-manager' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'h1',
					'options' => array(
						'h1'  => 'H1',
						'h2'  => 'H2',
						'div' => 'div',
					),
				)
			);
		}
		$this->end_controls_section();

		$this->start_controls_section(
			'mcm_dept_style',
			array(
				'label' => __( 'Colours & Text', 'medical-college-manager' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		// The colour settings are written inline on .mcm-dept as custom
		// properties, so the widget overrides them one level down, where the
		// sections inherit them from. Text colour is set directly.
		$controls = array(
			'heading' => array( __( 'Heading colour', 'medical-college-manager' ), '{{WRAPPER}} .mcm-dept > *', '--mcm-dept-heading: {{VALUE}};', '' ),
			'accent'  => array( __( 'Accent colour', 'medical-college-manager' ), '{{WRAPPER}} .mcm-dept > *', '--mcm-dept-accent: {{VALUE}}; --mcm-dept-accent-text: {{VALUE}};', __( 'Also used for small text (the HOD designation): pick a shade dark enough to read.', 'medical-college-manager' ) ),
			'card'    => array( __( 'Card background', 'medical-college-manager' ), '{{WRAPPER}} .mcm-dept > *', '--mcm-dept-card: {{VALUE}};', '' ),
			'text'    => array( __( 'Text colour', 'medical-college-manager' ), '{{WRAPPER}} .mcm-dept', 'color: {{VALUE}};', '' ),
		);
		foreach ( $controls as $key => $c ) {
			$this->add_control(
				'mcm_' . $key,
				array(
					'label'       => $c[0],
					'type'        => \Elementor\Controls_Manager::COLOR,
					'description' => $c[3],
					'selectors'   => array( $c[1] => $c[2] ),
				)
			);
		}
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'mcm_heading_type',
				'label'    => __( 'Heading typography', 'medical-college-manager' ),
				'selector' => '{{WRAPPER}} .mcm-dept-h, {{WRAPPER}} .mcm-dept-title',
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'mcm_body_type',
				'label'    => __( 'Text typography', 'medical-college-manager' ),
				'selector' => '{{WRAPPER}} .mcm-dept',
			)
		);
		$this->add_responsive_control(
			'mcm_padding',
			array(
				'label'      => __( 'Section padding', 'medical-college-manager' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array( '{{WRAPPER}} .mcm-dept-section' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Is this the Elementor editor preview?
	 *
	 * @return bool
	 */
	private function mcm_editing() {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$el = \Elementor\Plugin::$instance;
		// The editor renders widgets over AJAX (edit mode) and in the preview
		// frame (preview mode); either counts.
		$edit    = isset( $el->editor ) && is_object( $el->editor ) && method_exists( $el->editor, 'is_edit_mode' ) && $el->editor->is_edit_mode();
		$preview = isset( $el->preview ) && is_object( $el->preview ) && method_exists( $el->preview, 'is_preview_mode' ) && $el->preview->is_preview_mode();
		return $edit || $preview;
	}

	/**
	 * Render boundary: a failing widget renders nothing for visitors (and a
	 * short notice for administrators) instead of breaking the page.
	 */
	protected function render() {
		echo MCM_Safe::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped by MCM_Department_Page::part().
			function () {
				$s    = $this->get_settings_for_display();
				$term = MCM_TermMeta::resolve_term( array( 'department' => isset( $s['department'] ) ? (string) $s['department'] : '' ) );
				$html = $term ? MCM_Department_Page::part( $this->mcm_section(), $term, isset( $s['title_tag'] ) ? (string) $s['title_tag'] : 'h1' ) : '';
				if ( '' === $html && $this->mcm_editing() ) {
					// Nothing to show visitors; tell the editor why.
					$html = '<p class="mcm-empty">' . esc_html( $term ? __( 'This department has nothing for this section yet (Departments → Edit).', 'medical-college-manager' ) : __( 'Choose a department, or place this widget in a department template.', 'medical-college-manager' ) ) . '</p>';
				}
				return $html;
			},
			'Elementor widget ' . $this->get_name()
		);
	}
}

/**
 * Elementor widget: Department Hero.
 */
class MCM_Widget_Department_Hero extends MCM_Widget_Department_Base {
	/**
	 * Section key.
	 */
	protected function mcm_section() {
		return 'hero';
	}
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_department_hero';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Department Hero', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-header';
	}
}

/**
 * Elementor widget: HOD Message.
 */
class MCM_Widget_Department_Hod extends MCM_Widget_Department_Base {
	/**
	 * Section key.
	 */
	protected function mcm_section() {
		return 'hod';
	}
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_department_hod';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'HOD Message', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-testimonial';
	}
}

/**
 * Elementor widget: Department Introduction.
 */
class MCM_Widget_Department_Introduction extends MCM_Widget_Department_Base {
	/**
	 * Section key.
	 */
	protected function mcm_section() {
		return 'introduction';
	}
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_department_introduction';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Department Introduction', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-text';
	}
}

/**
 * Elementor widget: Vision & Mission.
 */
class MCM_Widget_Department_Vision_Mission extends MCM_Widget_Department_Base {
	/**
	 * Section key.
	 */
	protected function mcm_section() {
		return 'vision_mission';
	}
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_department_vision_mission';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Vision & Mission', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-info-box';
	}
}

/**
 * Elementor widget: Department Services.
 */
class MCM_Widget_Department_Services extends MCM_Widget_Department_Base {
	/**
	 * Section key.
	 */
	protected function mcm_section() {
		return 'services';
	}
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_department_services';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Department Services', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-gallery-grid';
	}
}

/**
 * Elementor widget: Department Faculty.
 */
class MCM_Widget_Department_Faculty extends MCM_Widget_Department_Base {
	/**
	 * Section key.
	 */
	protected function mcm_section() {
		return 'faculty';
	}
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_department_faculty';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Department Faculty', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-person';
	}
}

/**
 * Elementor widget: Department Publications.
 */
class MCM_Widget_Department_Publications extends MCM_Widget_Department_Base {
	/**
	 * Section key.
	 */
	protected function mcm_section() {
		return 'publications';
	}
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_department_publications';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Department Publications', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-bullet-list';
	}
}

/**
 * Elementor widget: Department Contact.
 */
class MCM_Widget_Department_Contact extends MCM_Widget_Department_Base {
	/**
	 * Section key.
	 */
	protected function mcm_section() {
		return 'contact';
	}
	/**
	 * Widget machine name.
	 */
	public function get_name() {
		return 'mcm_department_contact';
	}
	/**
	 * Widget title shown in the Elementor panel.
	 */
	public function get_title() {
		return __( 'Department Contact', 'medical-college-manager' );
	}
	/**
	 * Icon shown in the Elementor panel.
	 */
	public function get_icon() {
		return 'eicon-map-pin';
	}
}
