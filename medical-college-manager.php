<?php
/**
 * Plugin Name:       Medical College Manager
 * Description:       Content types for a medical college - Timetable, Programs, Courses, Events, Consultants, Team, Admissions, Notices, Tenders, Scholarships, Publications, Research Projects, Policies and Students - with shared connectors, CSV import/export, filterable archives, Elementor widgets and letterheaded printing.
 * Version:           1.30.2
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
 * This file is parsed by whatever PHP the server runs, so it must stay free
 * of any syntax newer than PHP 5.6 until the version check below has passed.
 */
if ( version_compare( PHP_VERSION, '7.2', '<' ) ) {
	add_action( 'admin_notices', 'mcm_php_version_notice' );
	return;
}

/**
 * Admin notice for an unsupported PHP version (plugin not loaded).
 */
function mcm_php_version_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>';
	echo esc_html(
		sprintf(
			/* translators: %s: current PHP version. */
			__( 'Medical College Manager requires PHP 7.2 or newer. This server runs PHP %s, so the plugin has not been loaded. Ask your host to upgrade PHP.', 'medical-college-manager' ),
			PHP_VERSION
		)
	);
	echo '</p></div>';
}

/*
------------------------------------------------------------------ *
 *  Constants
 * ------------------------------------------------------------------ */
define( 'MCM_VERSION', '1.30.2' );
define( 'MCM_FILE', __FILE__ );
define( 'MCM_PATH', plugin_dir_path( __FILE__ ) );
define( 'MCM_URL', plugin_dir_url( __FILE__ ) );
define( 'MCM_BASENAME', plugin_basename( __FILE__ ) );
define( 'MCM_MIN_PHP', '7.2' );
define( 'MCM_MIN_WP', '5.8' );
define( 'MCM_MIN_ELEMENTOR', '3.5.0' );

// The safety net loads first and unguarded: everything else reports to it.
require_once MCM_PATH . 'includes/class-mcm-safe.php';
require_once MCM_PATH . 'includes/class-mcm-health.php';

/**
 * Plugin modules: file => class, in boot order.
 *
 * @return array<string,string>
 */
function mcm_modules() {
	return array(
		'class-mcm-cpt.php'         => 'MCM_CPT',
		'class-mcm-meta.php'        => 'MCM_Meta',
		'class-mcm-settings.php'    => 'MCM_Settings',
		'class-mcm-assets.php'      => 'MCM_Assets',
		'class-mcm-query.php'       => '',
		'class-mcm-shortcodes.php'  => 'MCM_Shortcodes',
		'class-mcm-ajax.php'        => 'MCM_Ajax',
		'class-mcm-import.php'      => 'MCM_Import',
		'class-mcm-templates.php'   => 'MCM_Templates',
		'class-mcm-relations.php'   => 'MCM_Relations',
		'class-mcm-social.php'      => 'MCM_Social',
		'class-mcm-blog.php'        => 'MCM_Blog',
		'class-mcm-notice.php'      => 'MCM_Notice',
		'class-mcm-tender.php'      => 'MCM_Tender',
		'class-mcm-publication.php' => 'MCM_Publication',
		'class-mcm-program.php'     => 'MCM_Program',
		'class-mcm-caps.php'        => '',
		'class-mcm-privacy.php'     => 'MCM_Privacy',
		'class-mcm-termmeta.php'    => 'MCM_TermMeta',
		'class-mcm-schema.php'      => 'MCM_Schema',
		'class-mcm-elementor.php'   => 'MCM_Elementor',
	);
}

/**
 * Requirements that must hold before anything else loads.
 *
 * @return string[] Human-readable problems; empty when all is well.
 */
function mcm_requirement_errors() {
	$errors = array();
	$wp     = isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '0';
	if ( version_compare( $wp, MCM_MIN_WP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: required WordPress version, 2: installed version. */
			__( 'WordPress %1$s or newer is required; this site runs %2$s. Update WordPress (Dashboard → Updates).', 'medical-college-manager' ),
			MCM_MIN_WP,
			$wp
		);
	}
	return $errors;
}

/**
 * Main plugin class — boots all components.
 */
final class Medical_College_Manager {

	/** @var Medical_College_Manager|null */
	private static $instance = null;

	/** @var object[] Booted modules, keyed by class. */
	private $modules = array();

	/** @return Medical_College_Manager */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		MCM_Health::init();

		// Translations first (priority 0), so post type and taxonomy labels
		// registered on init are translated.
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );

		// Each file and each module is loaded behind its own boundary: one
		// broken module is switched off and reported, the others keep working.
		foreach ( mcm_modules() as $file => $class ) {
			if ( ! MCM_Safe::load( MCM_PATH . 'includes/' . $file, $file ) ) {
				continue;
			}
			if ( '' !== $class ) {
				$module = MCM_Safe::boot( $class );
				if ( $module ) {
					$this->modules[ $class ] = $module;
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

	public function load_textdomain() {
		load_plugin_textdomain( 'medical-college-manager', false, dirname( MCM_BASENAME ) . '/languages' );
	}
}

/**
 * Boot on plugins_loaded once the requirements are known to hold. When they
 * do not, nothing but an explanatory admin notice is loaded.
 */
function mcm_bootstrap() {
	$errors = mcm_requirement_errors();
	if ( $errors ) {
		add_action(
			'admin_notices',
			function () use ( $errors ) {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Medical College Manager has not been loaded.', 'medical-college-manager' ) . '</strong></p><ul>';
				foreach ( $errors as $error ) {
					echo '<li>' . esc_html( $error ) . '</li>';
				}
				echo '</ul></div>';
			}
		);
		return;
	}
	Medical_College_Manager::instance();
}
add_action( 'plugins_loaded', 'mcm_bootstrap' );

/**
 * Activation: register CPTs/taxonomies then flush rewrite rules.
 */
function mcm_activate() {
	if ( mcm_requirement_errors() ) {
		return; // The admin notice explains why; nothing is registered.
	}
	try {
		require_once MCM_PATH . 'includes/class-mcm-meta.php';
		require_once MCM_PATH . 'includes/class-mcm-settings.php';
		require_once MCM_PATH . 'includes/class-mcm-cpt.php';
		require_once MCM_PATH . 'includes/class-mcm-caps.php';
		require_once MCM_PATH . 'includes/class-mcm-program.php';

		$cpt = new MCM_CPT();
		$cpt->register_post_types();
		$cpt->register_taxonomies();

		// Store default design options if not present.
		if ( false === get_option( 'mcm_settings' ) ) {
			add_option( 'mcm_settings', MCM_Settings::default_settings() );
		}

		MCM_Caps::grant();
		MCM_Program::migrate();

		update_option( 'mcm_version', MCM_VERSION );
		flush_rewrite_rules();
	} catch ( \Throwable $e ) {
		// Activation must not fatal; the next page load retries via the
		// version check in maybe_flush_rewrites().
		MCM_Health::report( 'activation', $e );
	}
}
register_activation_hook( __FILE__, 'mcm_activate' );

/**
 * Deactivation: flush rewrite rules.
 */
function mcm_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'mcm_deactivate' );
