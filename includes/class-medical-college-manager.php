<?php
/**
 * The plugin bootstrap class.
 *
 * Loaded from mcm_bootstrap() only after the PHP and WordPress requirements
 * have been checked.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class — boots all components.
 */
final class Medical_College_Manager {

	/**
	 * The single instance.
	 *
	 * @var Medical_College_Manager|null
	 */
	private static $instance = null;

	/**
	 * Booted modules, keyed by class name.
	 *
	 * @var object[]
	 */
	private $modules = array();

	/**
	 * The single instance, created on first use.
	 *
	 * @return Medical_College_Manager
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Load and boot every module, each behind its own error boundary.
	 */
	private function __construct() {
		MCM_Health::init();

		// Translations first (priority 0), so post type and taxonomy labels
		// registered on init are translated.
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );

		// Each file and each module is loaded behind its own boundary: one
		// broken module is switched off and reported, the others keep working.
		foreach ( mcm_modules() as $file => $class_name ) {
			if ( ! MCM_Safe::load( MCM_PATH . 'includes/' . $file, $file ) ) {
				continue;
			}
			if ( '' !== $class_name ) {
				$module = MCM_Safe::boot( $class_name );
				if ( $module ) {
					$this->modules[ $class_name ] = $module;
				}
			}
		}

		// Meta-aware search (students by ID / roll / PMDC No.).
		if ( class_exists( 'MCM_Query' ) ) {
			MCM_Safe::run( array( 'MCM_Query', 'register_hooks' ), 'query hooks' );
		}

		// Flush rewrite rules once after an update changes CPT registration.
		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 99 );

		// Late on init, after every module registered its shortcodes: give any
		// plugin shortcode whose module was switched off a silent stand-in, so
		// visitors never see raw "[mcm_blog]" text on the page.
		add_action( 'init', array( $this, 'guard_shortcodes' ), 999 );
	}

	/**
	 * Every shortcode the plugin provides.
	 *
	 * @return string[]
	 */
	public static function shortcode_tags() {
		return array(
			'mcm_consultants',
			'mcm_team',
			'mcm_teams',
			'mcm_programs',
			'mcm_courses',
			'mcm_events',
			'mcm_timetable',
			'mcm_admissions',
			'mcm_tenders',
			'mcm_scholarships',
			'mcm_publications',
			'mcm_research',
			'mcm_policies',
			'mcm_jobs',
			'mcm_vacancies',
			'mcm_students',
			'mcm_student',
			'mcm_grid',
			'mcm_list',
			'mcm_table',
			'mcm_single',
			'mcm_notices',
			'mcm_notice_ticker',
			'mcm_notice_board',
			'mcm_blog',
			'mcm_social_bar',
			'mcm_department_hero',
			'mcm_department_message',
			'mcm_department_intro',
			'mcm_department_sections',
		);
	}

	/**
	 * Register a stand-in for every plugin shortcode that no module registered
	 * (its module failed to start).
	 */
	public function guard_shortcodes() {
		foreach ( self::shortcode_tags() as $tag ) {
			if ( ! shortcode_exists( $tag ) ) {
				add_shortcode( $tag, array( $this, 'unavailable_shortcode' ) );
			}
		}
	}

	/**
	 * Stand-in for a shortcode whose module is switched off: nothing for
	 * visitors, a short notice for administrators.
	 *
	 * @param array|string $atts    Shortcode attributes (unused).
	 * @param string       $content Enclosed content (unused).
	 * @param string       $tag     Shortcode tag.
	 * @return string
	 */
	public function unavailable_shortcode( $atts, $content = '', $tag = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- Shortcode callback signature.
		return MCM_Safe::admin_box( 'shortcode ' . MCM_Safe::str( $tag ) );
	}

	/**
	 * Flush rewrite rules a single time when the stored version differs from
	 * the running version (covers plugin updates, where the activation hook
	 * does not fire).
	 */
	public function maybe_flush_rewrites() {
		if ( get_option( 'mcm_version' ) === MCM_VERSION ) {
			return;
		}
		MCM_Safe::run( 'flush_rewrite_rules', 'upgrade: flush rewrite rules' );
		// Capability grants must also run on UPGRADE, not just activation, or an
		// existing site would keep the old post-capability behaviour for students.
		if ( class_exists( 'MCM_Caps' ) ) {
			MCM_Safe::run( array( 'MCM_Caps', 'grant' ), 'upgrade: capabilities' );
		}
		if ( class_exists( 'MCM_Program' ) ) {
			MCM_Safe::run( array( 'MCM_Program', 'migrate' ), 'upgrade: program migration' );
		}
		// 1.31.0: seed job categories, make jobs printable (once).
		if ( class_exists( 'MCM_Job' ) ) {
			MCM_Safe::run( array( 'MCM_Job', 'install' ), 'upgrade: jobs install' );
		}
		// 1.32.0: default staff types (once).
		if ( class_exists( 'MCM_CPT' ) ) {
			MCM_Safe::run( array( 'MCM_CPT', 'install_staff_types' ), 'upgrade: staff types' );
		}
		// 1.30.2: normalise dates/times saved before validation existed (once).
		if ( class_exists( 'MCM_Upgrade' ) ) {
			MCM_Safe::run( array( 'MCM_Upgrade', 'maybe_migrate_dates' ), 'upgrade: date normalisation' );
		}
		update_option( 'mcm_version', MCM_VERSION );
	}

	/**
	 * Load the plugin's translations.
	 */
	public function load_textdomain() {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Distributed outside WordPress.org: the translations bundled in /languages must be loaded explicitly.
		load_plugin_textdomain( 'medical-college-manager', false, dirname( MCM_BASENAME ) . '/languages' );
	}
}
