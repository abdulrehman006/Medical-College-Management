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
		'mcm_dates_migration',
		'mcm_job_installed',
		'mcm_staff_type_installed',
		'mcm_department_page',
		'mcm_site_chrome',
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	delete_transient( 'mcm_notice_feed' );
	delete_transient( 'mcm_health_alert' );
	delete_transient( 'mcm_dates_migration_notice' );
	delete_transient( 'mcm_hf_no_body_open' );

	delete_transient( 'mcm_hf_err_header' );
	delete_transient( 'mcm_hf_err_footer' );

	// Menu assignments to the plugin's header/footer locations, for every
	// installed theme (the menus themselves are content and stay).
	foreach ( array_keys( wp_get_themes() ) as $stylesheet ) {
		$mods = get_option( 'theme_mods_' . $stylesheet );
		if ( ! is_array( $mods ) || empty( $mods['nav_menu_locations'] ) || ! is_array( $mods['nav_menu_locations'] ) ) {
			continue;
		}
		foreach ( array( 'mcm-college-nav', 'mcm-college-quick', 'mcm-college-links-a', 'mcm-college-links-b', 'mcm-college-legal' ) as $location ) {
			unset( $mods['nav_menu_locations'][ $location ] );
		}
		update_option( 'theme_mods_' . $stylesheet, $mods );
	}

	// Cached logo lookups (mcm_hf_att_*).
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_mcm\_hf\_att\_%' OR option_name LIKE '\_transient\_timeout\_mcm\_hf\_att\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall cleanup of the plugin's own transients.

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
