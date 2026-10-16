<?php
/**
 * Shortcodes — one per CPT plus generic grid/list/table/single.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCM_Shortcodes {

	public function __construct() {
		add_shortcode( 'mcm_consultants', array( $this, 'sc_consultants' ) );
		add_shortcode( 'mcm_team', array( $this, 'sc_team' ) );
		add_shortcode( 'mcm_teams', array( $this, 'sc_team' ) ); // alias.
		add_shortcode( 'mcm_programs', array( $this, 'sc_programs' ) );
		add_shortcode( 'mcm_courses', array( $this, 'sc_courses' ) );
		add_shortcode( 'mcm_events', array( $this, 'sc_events' ) );
		add_shortcode( 'mcm_timetable', array( $this, 'sc_timetable' ) );
		add_shortcode( 'mcm_admissions', array( $this, 'sc_admissions' ) );
		add_shortcode( 'mcm_tenders', array( $this, 'sc_tenders' ) );
		add_shortcode( 'mcm_scholarships', array( $this, 'sc_scholarships' ) );
		add_shortcode( 'mcm_publications', array( $this, 'sc_publications' ) );
		add_shortcode( 'mcm_research', array( $this, 'sc_research' ) );
		add_shortcode( 'mcm_policies', array( $this, 'sc_policies' ) );
		add_shortcode( 'mcm_students', array( $this, 'sc_students' ) );
		add_shortcode( 'mcm_student', array( $this, 'sc_students' ) ); // alias.

		// Generic.
		add_shortcode( 'mcm_grid', array( $this, 'sc_grid' ) );
		add_shortcode( 'mcm_list', array( $this, 'sc_list' ) );
		add_shortcode( 'mcm_table', array( $this, 'sc_table' ) );
		add_shortcode( 'mcm_single', array( $this, 'sc_single' ) );
	}

	/**
	 * WordPress passes '' (empty string) when a shortcode has no attributes.
	 */
	private function normalize( $atts ) {
		return is_array( $atts ) ? $atts : array();
	}

	private static function truthy( $val ) {
		return in_array( strtolower( (string) $val ), array( 'yes', '1', 'true', 'on' ), true );
	}

	/**
	 * Shared renderer: outputs a wrapper that AJAX can target. Instance method
	 * kept for the shortcode callbacks; delegates to the reusable static below.
	 */
	private function render( $atts ) {
		return self::render_block( $atts );
	}

	/**
	 * Build one self-contained, AJAX-filterable block for the given attributes.
	 * Public + static so archive templates can render blocks directly.
	 */
	public static function render_block( $atts ) {
		// Backstop: whatever rendered this block (shortcode, Elementor widget,
		// archive template) now needs the CSS/JS, even if detection missed it.
		MCM_Assets::need();

		$a   = MCM_Query::parse_atts( is_array( $atts ) ? $atts : array() );
		$uid = 'mcm-' . wp_generate_password( 6, false, false );

		$data = array(
			'type'      => $a['type'],
			'view'      => $a['view'],
			'columns'   => (int) $a['columns'],
			'count'     => (int) $a['count'],
			'orderby'   => $a['orderby'],
			'order'     => $a['order'],
			'category'  => $a['category'],
			'meta_key'  => $a['meta_key'],
			'meta_type' => $a['meta_type'],
			'lock_tax'  => $a['lock_tax'],
			'lock_term' => $a['lock_term'],
			// Without these a filtered AJAX response silently loses pagination.
			'paginate'  => $a['paginate'],
			'paged'     => (int) $a['paged'],
		);

		$show_filters = self::truthy( $a['filters'] );

		/*
		 * Printable types come from the settings screen (Timetable and Students
		 * by default); the filter stays as the developer extension point. The
		 * type gate always applies — a print="yes" on a type that was never
		 * enabled does not conjure a button. Left unset, the shortcode simply
		 * follows the setting, so enabling a type there is the only step
		 * needed; print="no" still suppresses it on one instance.
		 */
		$print_types = apply_filters( 'mcm_print_types', MCM_Settings::print_types() );
		$print_attr  = trim( (string) $a['print'] );
		$show_print  = in_array( $a['type'], $print_types, true )
			&& ( '' === $print_attr ? true : self::truthy( $print_attr ) );

		ob_start();

		// Directory layout: filters live in a left sidebar, results on the right.
		if ( 'directory' === $a['view'] ) {
			echo '<div class="mcm-wrap mcm-directory ' . esc_attr( $a['class_css'] ) . '" id="' . esc_attr( $uid ) . '" data-config="' . esc_attr( wp_json_encode( $data ) ) . '">';
			echo '<aside class="mcm-dir-aside">';
			echo MCM_Query::render_sidebar( $a ); // phpcs:ignore
			echo '</aside>';
			echo '<div class="mcm-dir-main">';
			if ( $show_print ) {
				echo '<div class="mcm-toolbar"><button type="button" class="mcm-print-btn" aria-label="' . esc_attr__( 'Print', 'medical-college-manager' ) . '">&#128424; ' . esc_html__( 'Print', 'medical-college-manager' ) . '</button></div>';
				echo MCM_Query::print_header( $a ); // phpcs:ignore
				echo MCM_Query::print_footer(); // phpcs:ignore
			}
			// aria-live: filtering swaps these results without a page load, so
			// assistive tech is told the list changed.
			echo '<div class="mcm-results" aria-live="polite" aria-busy="false">';
			echo MCM_Query::render_results( $a ); // phpcs:ignore
			echo '</div>';
			echo '</div>';
			echo '</div>';
			return ob_get_clean();
		}

		echo '<div class="mcm-wrap ' . esc_attr( $a['class_css'] ) . '" id="' . esc_attr( $uid ) . '" data-config="' . esc_attr( wp_json_encode( $data ) ) . '">';

		if ( $show_filters || $show_print ) {
			echo '<div class="mcm-toolbar">';
			if ( $show_filters ) {
				echo MCM_Query::render_filters( $a ); // phpcs:ignore
			}
			if ( $show_print ) {
				echo '<button type="button" class="mcm-print-btn" aria-label="' . esc_attr__( 'Print', 'medical-college-manager' ) . '">&#128424; ' . esc_html__( 'Print', 'medical-college-manager' ) . '</button>';
			}
			echo '</div>';
		}

		if ( $show_print ) {
			echo MCM_Query::print_header( $a ); // phpcs:ignore
			echo MCM_Query::print_footer(); // phpcs:ignore
		}

		echo '<div class="mcm-results" aria-live="polite" aria-busy="false">';
		echo MCM_Query::render_results( $a ); // phpcs:ignore
		echo '</div>';
		echo '</div>';
		return ob_get_clean();
	}

	public function sc_consultants( $atts ) {
		$atts = $this->normalize( $atts );
		$atts['type'] = 'mcm_consultant';
		return $this->render( $atts );
	}

	public function sc_team( $atts ) {
		$atts = $this->normalize( $atts );
		$atts['type'] = 'mcm_team';
		return $this->render( $atts );
	}

	public function sc_programs( $atts ) {
		$atts = $this->normalize( $atts );
		$atts['type'] = 'mcm_program';
		return $this->render( $atts );
	}

	public function sc_courses( $atts ) {
		$atts = $this->normalize( $atts );
		$atts['type'] = 'mcm_course';
		return $this->render( $atts );
	}

	public function sc_events( $atts ) {
		$atts = $this->normalize( $atts );
		$atts['type'] = 'mcm_event';
		return $this->render( $atts );
	}

	public function sc_timetable( $atts ) {
		$atts = $this->normalize( $atts );
		$atts['type'] = 'mcm_timetable';
		if ( empty( $atts['view'] ) ) {
			$atts['view'] = 'table';
		}
		return $this->render( $atts );
	}

	public function sc_admissions( $atts ) {
		$atts = $this->normalize( $atts );
		$atts['type'] = 'mcm_admission';
		return $this->render( $atts );
	}

	public function sc_tenders( $atts ) {
		$atts = $this->normalize( $atts );
		$atts['type'] = 'mcm_tender';
		return $this->render( $atts );
	}

	public function sc_scholarships( $atts ) {
		$atts = $this->normalize( $atts );
		$atts['type'] = 'mcm_scholarship';
		return $this->render( $atts );
	}

	public function sc_publications( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['type'] = 'mcm_publication';
		if ( empty( $atts['view'] ) ) {
			$atts['view'] = 'citation';
		}
		if ( empty( $atts['count'] ) ) {
			$atts['count'] = 20;
		}
		return $this->render( $atts );
	}

	public function sc_research( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['type'] = 'mcm_research';
		return $this->render( $atts );
	}

	public function sc_policies( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['type'] = 'mcm_policy';
		if ( empty( $atts['view'] ) ) {
			$atts['view'] = 'list';
		}
		return $this->render( $atts );
	}

	public function sc_students( $atts ) {
		$atts = $this->normalize( $atts );
		$atts['type'] = 'mcm_student';
		if ( empty( $atts['view'] ) ) {
			$atts['view'] = 'table';
		}
		if ( empty( $atts['count'] ) ) {
			$atts['count'] = 50; // directory default — show more per page.
		}
		return $this->render( $atts );
	}

	public function sc_grid( $atts ) {
		$atts = $this->normalize( $atts );
		$atts['view'] = 'grid';
		return $this->render( $atts );
	}

	public function sc_list( $atts ) {
		$atts = $this->normalize( $atts );
		$atts['view'] = 'list';
		return $this->render( $atts );
	}

	public function sc_table( $atts ) {
		$atts = $this->normalize( $atts );
		$atts['view'] = 'table';
		return $this->render( $atts );
	}

	public function sc_single( $atts ) {
		$a = shortcode_atts(
			array(
				'id'   => 0,
				'type' => '',
			),
			$atts,
			'mcm_single'
		);
		$id = absint( $a['id'] );
		if ( ! $id ) {
			return '';
		}
		MCM_Assets::need();
		return '<div class="mcm-wrap">' . MCM_Query::single( $id ) . '</div>';
	}
}
