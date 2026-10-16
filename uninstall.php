<?php
/**
 * Uninstall handler — runs only when the plugin is deleted from wp-admin.
 *
 * Removes the plugin's own settings option. Content (posts, meta and taxonomy
 * terms) is intentionally left intact so deleting the plugin never destroys a
 * college's records; remove it manually if a full wipe is required.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'mcm_settings' );
delete_option( 'mcm_version' );

// Remove the student capabilities from every role so none are orphaned.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-mcm-caps.php';
if ( class_exists( 'MCM_Caps' ) ) {
	MCM_Caps::revoke_all();
}

// Multisite: clear the options on every site in the network.
if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( (array) $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		delete_option( 'mcm_settings' );
		delete_option( 'mcm_version' );
		restore_current_blog();
	}
}
