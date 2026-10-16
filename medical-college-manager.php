<?php
/**
 * Plugin Name:       Medical College Manager
 * Description:       Twelve content types for a medical college - Timetable, Programs, Courses, Events, Consultants, Team, Admissions, Tenders, Scholarships, Publications, Notices and Students - with shared connectors, CSV import/export, filterable archives, Elementor widgets and letterheaded printing.
 * Version:           1.30.1
 * Author:            Abdul R
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Text Domain:       medical-college-manager
 * Domain Path:       /languages
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

/*
 * Bail politely on an unsupported PHP version instead of fataling half-way
 * through loading. The readme claims 7.2; nothing enforced it before.
 */
if ( version_compare( PHP_VERSION, '7.2', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html(
				sprintf(
					/* translators: %s: current PHP version. */
					__( 'Medical College Manager requires PHP 7.2 or newer. This server runs PHP %s, so the plugin has not been loaded.', 'medical-college-manager' ),
					PHP_VERSION
				)
			);
			echo '</p></div>';
		}
	);
	return;
}

/* ------------------------------------------------------------------ *
 *  Constants
 * ------------------------------------------------------------------ */
define( 'MCM_VERSION', '1.30.1' );
define( 'MCM_FILE', __FILE__ );
define( 'MCM_PATH', plugin_dir_path( __FILE__ ) );
define( 'MCM_URL', plugin_dir_url( __FILE__ ) );
define( 'MCM_BASENAME', plugin_basename( __FILE__ ) );

/* ------------------------------------------------------------------ *
 *  Includes
 * ------------------------------------------------------------------ */
require_once MCM_PATH . 'includes/class-mcm-cpt.php';
require_once MCM_PATH . 'includes/class-mcm-meta.php';
require_once MCM_PATH . 'includes/class-mcm-settings.php';
require_once MCM_PATH . 'includes/class-mcm-assets.php';
require_once MCM_PATH . 'includes/class-mcm-query.php';
require_once MCM_PATH . 'includes/class-mcm-shortcodes.php';
require_once MCM_PATH . 'includes/class-mcm-ajax.php';
require_once MCM_PATH . 'includes/class-mcm-import.php';
require_once MCM_PATH . 'includes/class-mcm-templates.php';
require_once MCM_PATH . 'includes/class-mcm-relations.php';
require_once MCM_PATH . 'includes/class-mcm-social.php';
require_once MCM_PATH . 'includes/class-mcm-blog.php';
require_once MCM_PATH . 'includes/class-mcm-notice.php';
require_once MCM_PATH . 'includes/class-mcm-tender.php';
require_once MCM_PATH . 'includes/class-mcm-publication.php';
require_once MCM_PATH . 'includes/class-mcm-program.php';
require_once MCM_PATH . 'includes/class-mcm-caps.php';
require_once MCM_PATH . 'includes/class-mcm-termmeta.php';
require_once MCM_PATH . 'includes/class-mcm-privacy.php';
require_once MCM_PATH . 'includes/class-mcm-schema.php';
require_once MCM_PATH . 'includes/class-mcm-elementor.php';

/**
 * Main plugin class — boots all components.
 */
final class Medical_College_Manager {

	/** @var Medical_College_Manager */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		new MCM_CPT();
		new MCM_Meta();
		new MCM_Settings();
		new MCM_Assets();
		new MCM_Shortcodes();
		new MCM_Ajax();
		new MCM_Import();
		new MCM_Templates();
		new MCM_Relations();
		new MCM_Social();
		new MCM_Blog();
		new MCM_Notice();
		new MCM_Tender();
		new MCM_Publication();
		new MCM_Program();
		new MCM_Privacy();
		new MCM_TermMeta();
		new MCM_Schema();
		new MCM_Elementor();

		// Meta-aware search (students by ID / roll / email / phone).
		MCM_Query::register_hooks();

		add_action( 'init', array( $this, 'load_textdomain' ) );
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
		flush_rewrite_rules();
		// Capability grants must also run on UPGRADE, not just activation, or an
		// existing site would keep the old post-capability behaviour for students.
		MCM_Caps::grant();
		MCM_Program::migrate();
		update_option( 'mcm_version', MCM_VERSION );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'medical-college-manager', false, dirname( MCM_BASENAME ) . '/languages' );
	}
}

/**
 * Activation: register CPTs/taxonomies then flush rewrite rules.
 */
function mcm_activate() {
	require_once MCM_PATH . 'includes/class-mcm-cpt.php';
	require_once MCM_PATH . 'includes/class-mcm-caps.php';
require_once MCM_PATH . 'includes/class-mcm-termmeta.php';
	$cpt = new MCM_CPT();
	$cpt->register_post_types();
	$cpt->register_taxonomies();

	// Store default design options if not present.
	if ( false === get_option( 'mcm_settings' ) ) {
		add_option( 'mcm_settings', MCM_Settings::default_settings() );
	}

	MCM_Caps::grant();
	require_once MCM_PATH . 'includes/class-mcm-program.php';
	MCM_Program::migrate();

	update_option( 'mcm_version', MCM_VERSION );
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'mcm_activate' );

/**
 * Deactivation: flush rewrite rules.
 */
function mcm_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'mcm_deactivate' );

// Boot.
Medical_College_Manager::instance();
