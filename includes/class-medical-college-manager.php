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
