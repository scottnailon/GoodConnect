<?php
// Only run when WordPress is uninstalling the plugin.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'goodconnect_settings' );
delete_option( 'goodconnect_gf_mappings' );
delete_option( 'goodconnect_elementor_mappings' );
delete_option( 'goodconnect_db_version' );
