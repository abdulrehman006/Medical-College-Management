<?php
/**
 * Elementor integration loader. Registers a widget category and one drag-and-
 * drop widget per content type. Soft dependency: if Elementor is not active,
 * none of these hooks fire and the plugin keeps working via shortcodes.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCM_Elementor {

	public function __construct() {
		add_action( 'elementor/elements/categories_registered', array( $this, 'category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register' ) );
	}

	/** Add the "Medical College" category to the Elementor panel. */
	public function category( $elements_manager ) {
		$elements_manager->add_category(
			'medical-college-manager',
			array(
				'title' => __( 'Medical College', 'medical-college-manager' ),
				'icon'  => 'eicon-graduation-cap',
			)
		);
	}

	/** Register every widget with Elementor's widgets manager. */
	public function register( $widgets_manager ) {
		require_once MCM_PATH . 'includes/elementor-widgets.php';

		foreach ( self::widget_classes() as $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}
			$widget = new $class();
			if ( $widgets_manager && method_exists( $widgets_manager, 'register' ) ) {
				$widgets_manager->register( $widget );
			} elseif ( class_exists( '\Elementor\Plugin' ) ) {
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( $widget );
			}
		}
	}

	/** Concrete widget class names. */
	public static function widget_classes() {
		return array(
			'MCM_Widget_Consultants',
			'MCM_Widget_Team',
			'MCM_Widget_Programs',
			'MCM_Widget_Courses',
			'MCM_Widget_Events',
			'MCM_Widget_Timetable',
			'MCM_Widget_Admissions',
			'MCM_Widget_Tenders',
			'MCM_Widget_Scholarships',
			'MCM_Widget_Publications',
			'MCM_Widget_Research',
			'MCM_Widget_Policies',
			'MCM_Widget_Blog',
			'MCM_Widget_Notice_Ticker',
			'MCM_Widget_Notice_Board',
			'MCM_Widget_Notices',
		);
	}
}
