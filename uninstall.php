<?php
/**
 * Uninstall handler — runs only when the plugin is deleted from wp-admin.
 *
 * Removes the plugin's own settings, caches and capabilities, on every site
 * of a multisite network. Content (posts, meta and taxonomy terms) is
 * intentionally left intact so deleting the plugin never destroys a college's
 * records; remove it manually if a full wipe is required.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-mcm-caps.php';

/**
 * Remove everything the plugin stored for the current site.
 */
function mcm_uninstall_site() {
	$options = array(
		'mcm_settings',
		'mcm_social',
		'mcm_version',
		'mcm_program_migrated',
		'mcm_error_log',
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	delete_transient( 'mcm_notice_feed' );
	delete_transient( 'mcm_health_alert' );

	// Remove the student capabilities from every role so none are orphaned.
	if ( class_exists( 'MCM_Caps' ) ) {
		MCM_Caps::revoke_all();
	}
}

if ( is_multisite() ) {
	$mcm_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( (array) $mcm_site_ids as $mcm_site_id ) {
		switch_to_blog( (int) $mcm_site_id );
		mcm_uninstall_site();
		restore_current_blog();
	}
} else {
	mcm_uninstall_site();
}
