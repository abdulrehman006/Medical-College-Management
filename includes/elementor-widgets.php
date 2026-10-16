<?php
/**
 * Elementor widget classes. Required only from within the Elementor
 * registration hook, so Elementor's base class is guaranteed to exist.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

	public function get_categories() {
		return array( 'medical-college-manager' );
	}

	public function get_icon() {
		return 'eicon-posts-grid';
	}

	public function get_style_depends() {
		return array( 'mcm-frontend' );
	}

	public function get_script_depends() {
		return array( 'mcm-frontend' );
	}

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
		$V = '{{WRAPPER}}'; // phpcs:ignore

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
				'selectors'  => array( $V => '--mcm-gap: {{SIZE}}{{UNIT}};' ),
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
				'selectors'  => array( $V => '--mcm-pad: {{SIZE}}{{UNIT}};' ),
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
				'selectors'  => array( $V => '--mcm-radius: {{SIZE}}{{UNIT}};' ),
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
				'selectors'  => array( $V => '--mcm-img-h: {{SIZE}}{{UNIT}};' ),
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
					'selectors' => array( $V => $c[1] . ': {{VALUE}};' ),
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

		$title_sel = $V . ' .mcm-card-title, ' . $V . ' .mcm-card-title a, ' . $V . ' .mcm-dir-card-name, '
			. $V . ' .mcm-dir-card-name a, ' . $V . ' .mcm-list-title, ' . $V . ' .mcm-list-title a';

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
				'selector' => $V . ' .mcm-card, ' . $V . ' .mcm-dir-card, ' . $V . ' .mcm-list-item',
			)
		);
		$this->end_controls_section();
	}

	/** Term slug => name options for a taxonomy. */
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

		echo MCM_Shortcodes::render_block( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/*
------------------------------------------------------------------ *
 *  Concrete widgets — one per public content type.
 * ------------------------------------------------------------------ */

class MCM_Widget_Consultants extends MCM_Elementor_Widget_Base {
	public function get_name() {
		return 'mcm_consultants';
	}
	public function get_title() {
		return __( 'Consultants', 'medical-college-manager' );
	}
	public function get_icon() {
		return 'eicon-person';
	}
	public function get_keywords() {
		return array( 'consultant', 'doctor', 'medical', 'faculty', 'medical-college-manager' );
	}
	protected function mcm_type() {
		return 'mcm_consultant';
	}
}

class MCM_Widget_Team extends MCM_Elementor_Widget_Base {
	public function get_name() {
		return 'mcm_team';
	}
	public function get_title() {
		return __( 'Team', 'medical-college-manager' );
	}
	public function get_icon() {
		return 'eicon-person';
	}
	public function get_keywords() {
		return array( 'team', 'staff', 'faculty', 'people', 'medical-college-manager' );
	}
	protected function mcm_type() {
		return 'mcm_team';
	}
}

class MCM_Widget_Programs extends MCM_Elementor_Widget_Base {
	public function get_name() {
		return 'mcm_programs';
	}
	public function get_title() {
		return __( 'Programs', 'medical-college-manager' );
	}
	public function get_icon() {
		return 'eicon-document-file';
	}
	public function get_keywords() {
		return array( 'program', 'degree', 'course', 'medical-college-manager' );
	}
	protected function mcm_type() {
		return 'mcm_program';
	}
}

class MCM_Widget_Courses extends MCM_Elementor_Widget_Base {
	public function get_name() {
		return 'mcm_courses';
	}
	public function get_title() {
		return __( 'Courses', 'medical-college-manager' );
	}
	public function get_icon() {
		return 'eicon-book';
	}
	public function get_keywords() {
		return array( 'course', 'subject', 'class', 'medical-college-manager' );
	}
	protected function mcm_type() {
		return 'mcm_course';
	}
}

class MCM_Widget_Events extends MCM_Elementor_Widget_Base {
	public function get_name() {
		return 'mcm_events';
	}
	public function get_title() {
		return __( 'Events', 'medical-college-manager' );
	}
	public function get_icon() {
		return 'eicon-calendar';
	}
	public function get_keywords() {
		return array( 'event', 'seminar', 'conference', 'medical-college-manager' );
	}
	protected function mcm_type() {
		return 'mcm_event';
	}
}

class MCM_Widget_Timetable extends MCM_Elementor_Widget_Base {
	public function get_name() {
		return 'mcm_timetable';
	}
	public function get_title() {
		return __( 'Timetable', 'medical-college-manager' );
	}
	public function get_icon() {
		return 'eicon-table';
	}
	public function get_keywords() {
		return array( 'timetable', 'schedule', 'class', 'medical-college-manager' );
	}
	protected function mcm_type() {
		return 'mcm_timetable';
	}
	protected function mcm_default_view() {
		return 'table';
	}
}

class MCM_Widget_Admissions extends MCM_Elementor_Widget_Base {
	public function get_name() {
		return 'mcm_admissions';
	}
	public function get_title() {
		return __( 'Admissions', 'medical-college-manager' );
	}
	public function get_icon() {
		return 'eicon-form-horizontal';
	}
	public function get_keywords() {
		return array( 'admission', 'apply', 'intake', 'medical-college-manager' );
	}
	protected function mcm_type() {
		return 'mcm_admission';
	}
}

class MCM_Widget_Tenders extends MCM_Elementor_Widget_Base {
	public function get_name() {
		return 'mcm_tenders';
	}
	public function get_title() {
		return __( 'Tenders', 'medical-college-manager' );
	}
	public function get_icon() {
		return 'eicon-document-file';
	}
	public function get_keywords() {
		return array( 'tender', 'procurement', 'bid', 'notice', 'medical-college-manager' );
	}
	protected function mcm_type() {
		return 'mcm_tender';
	}
}

class MCM_Widget_Scholarships extends MCM_Elementor_Widget_Base {
	public function get_name() {
		return 'mcm_scholarships';
	}
	public function get_title() {
		return __( 'Scholarships', 'medical-college-manager' );
	}
	public function get_icon() {
		return 'eicon-star';
	}
	public function get_keywords() {
		return array( 'scholarship', 'financial aid', 'merit', 'medical-college-manager' );
	}
	protected function mcm_type() {
		return 'mcm_scholarship';
	}
}

class MCM_Widget_Research extends MCM_Elementor_Widget_Base {
	public function get_name() {
		return 'mcm_research';
	}
	public function get_title() {
		return __( 'Research Projects', 'medical-college-manager' );
	}
	public function get_icon() {
		return 'eicon-search-results';
	}
	public function get_keywords() {
		return array( 'research', 'project', 'r&d', 'investigator', 'medical-college-manager' );
	}
	protected function mcm_type() {
		return 'mcm_research';
	}
}

class MCM_Widget_Policies extends MCM_Elementor_Widget_Base {
	public function get_name() {
		return 'mcm_policies';
	}
	public function get_title() {
		return __( 'Policies', 'medical-college-manager' );
	}
	public function get_icon() {
		return 'eicon-document-file';
	}
	public function get_keywords() {
		return array( 'policy', 'policies', 'document', 'download', 'medical-college-manager' );
	}
	protected function mcm_type() {
		return 'mcm_policy';
	}
	protected function mcm_default_view() {
		return 'list';
	}
}

class MCM_Widget_Publications extends MCM_Elementor_Widget_Base {
	public function get_name() {
		return 'mcm_publications';
	}
	public function get_title() {
		return __( 'Publications', 'medical-college-manager' );
	}
	public function get_icon() {
		return 'eicon-document-file';
	}
	public function get_keywords() {
		return array( 'publication', 'research', 'paper', 'journal', 'citation', 'medical-college-manager' );
	}
	protected function mcm_type() {
		return 'mcm_publication';
	}
	protected function mcm_default_view() {
		return 'citation';
	}
}

/**
 * Blog Posts widget — standard WordPress posts in the date-badge layout.
 * Separate from the CPT widgets (different controls + renderer).
 */
class MCM_Widget_Blog extends \Elementor\Widget_Base {

	public function get_name() {
		return 'mcm_blog';
	}
	public function get_title() {
		return __( 'Blog Posts', 'medical-college-manager' );
	}
	public function get_icon() {
		return 'eicon-post-list';
	}
	public function get_categories() {
		return array( 'medical-college-manager' );
	}
	public function get_keywords() {
		return array( 'blog', 'posts', 'news', 'articles', 'medical-college-manager' );
	}
	public function get_style_depends() {
		return array( 'mcm-frontend' );
	}

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

		echo MCM_Blog::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
	}
}

/**
 * Shared helper: Notice Category term options for the notice widgets.
 */
function mcm_notice_cat_options() {
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
 * Base for the three notice widgets — shared controls + settings mapping.
 */
abstract class MCM_Widget_Notice_Base extends \Elementor\Widget_Base {

	public function get_categories() {
		return array( 'medical-college-manager' );
	}
	public function get_style_depends() {
		return array( 'mcm-frontend' );
	}
	public function get_script_depends() {
		return array( 'mcm-frontend' );
	}

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
				'options'     => mcm_notice_cat_options(),
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

	protected function mcm_atts() {
		$s   = $this->get_settings_for_display();
		$cat = isset( $s['category'] ) ? $s['category'] : '';
		if ( is_array( $cat ) ) {
			$cat = implode( ',', $cat );
		}
		return MCM_Notice::atts(
			array(
				'count'      => ! empty( $s['count'] ) ? (int) $s['count'] : 10,
				'category'   => $cat,
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

class MCM_Widget_Notice_Ticker extends MCM_Widget_Notice_Base {
	public function get_name() {
		return 'mcm_notice_ticker';
	}
	public function get_title() {
		return __( 'Notice Ticker', 'medical-college-manager' );
	}
	public function get_icon() {
		return 'eicon-slider-push';
	}
	public function get_keywords() {
		return array( 'notice', 'ticker', 'marquee', 'news', 'medical-college-manager' );
	}
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

class MCM_Widget_Notice_Board extends MCM_Widget_Notice_Base {
	public function get_name() {
		return 'mcm_notice_board';
	}
	public function get_title() {
		return __( 'Notice Board', 'medical-college-manager' );
	}
	public function get_icon() {
		return 'eicon-post-list';
	}
	public function get_keywords() {
		return array( 'notice', 'board', 'announcements', 'scroll', 'medical-college-manager' );
	}
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

class MCM_Widget_Notices extends MCM_Widget_Notice_Base {
	public function get_name() {
		return 'mcm_notices';
	}
	public function get_title() {
		return __( 'Notices List', 'medical-college-manager' );
	}
	public function get_icon() {
		return 'eicon-bullet-list';
	}
	public function get_keywords() {
		return array( 'notice', 'list', 'announcements', 'medical-college-manager' );
	}
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
