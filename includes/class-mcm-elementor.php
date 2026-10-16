<?php
/**
 * Elementor integration loader. Registers a widget category and one drag-and-
 * drop widget per content type.
 *
 * Soft, version-gated dependency: nothing here touches Elementor unless it has
 * loaded and is at least MCM_MIN_ELEMENTOR (3.5, the release that introduced
 * the widget API used below). With Elementor missing or too old the widgets
 * are simply skipped — every shortcode keeps working — and administrators get
 * a notice explaining what to update.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor integration: widget category and widgets, gated on the Elementor version.
 */
class MCM_Elementor {

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		add_action( 'elementor/elements/categories_registered', array( $this, 'category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register' ) );
		add_action( 'admin_notices', array( $this, 'version_notice' ) );
	}

	/** Minimum supported Elementor version. */
	private static function min_version() {
		return defined( 'MCM_MIN_ELEMENTOR' ) ? MCM_MIN_ELEMENTOR : '3.5.0';
	}

	/**
	 * Is a compatible Elementor loaded?
	 *
	 * @return bool
	 */
	public static function compatible() {
		return did_action( 'elementor/loaded' )
			&& defined( 'ELEMENTOR_VERSION' )
			&& version_compare( ELEMENTOR_VERSION, self::min_version(), '>=' )
			&& class_exists( '\Elementor\Widget_Base' );
	}

	/**
	 * Add the "Medical College" category to the Elementor panel.
	 *
	 * @param object $elements_manager Elementor elements manager.
	 */
	public function category( $elements_manager ) {
		if ( ! self::compatible() || ! is_object( $elements_manager ) || ! method_exists( $elements_manager, 'add_category' ) ) {
			return;
		}
		$elements_manager->add_category(
			'medical-college-manager',
			array(
				'title' => __( 'Medical College', 'medical-college-manager' ),
				'icon'  => 'eicon-graduation-cap',
			)
		);
	}

	/**
	 * Register every widget. Each widget is registered behind its own
	 * boundary, so one failing widget never takes the others (or the editor)
	 * down with it.
	 *
	 * @param object $widgets_manager Elementor widgets manager.
	 */
	public function register( $widgets_manager ) {
		if ( ! self::compatible() || ! is_object( $widgets_manager ) || ! method_exists( $widgets_manager, 'register' ) ) {
			return;
		}
		if ( ! MCM_Safe::load( MCM_PATH . 'includes/elementor-widgets.php', 'elementor-widgets.php' ) ) {
			return;
		}

		foreach ( self::widget_classes() as $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}
			MCM_Safe::run(
				function () use ( $widgets_manager, $class ) {
					$widgets_manager->register( new $class() );
				},
				'Elementor widget ' . $class
			);
		}
	}

	/** Tell administrators why the widgets are missing on an old Elementor. */
	public function version_notice() {
		if ( ! defined( 'ELEMENTOR_VERSION' ) || version_compare( ELEMENTOR_VERSION, self::min_version(), '>=' ) ) {
			return;
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Medical College Manager', 'medical-college-manager' ) . ':</strong> ';
		echo esc_html(
			sprintf(
				/* translators: 1: installed Elementor version, 2: required version. */
				__( 'the Medical College Elementor widgets need Elementor %2$s or newer (this site has %1$s), so they have been switched off. All shortcodes keep working. Update Elementor to use the widgets.', 'medical-college-manager' ),
				ELEMENTOR_VERSION,
				self::min_version()
			)
		);
		echo '</p></div>';
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
