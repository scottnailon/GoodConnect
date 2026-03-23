<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'goodconnect_activity_log' );

delete_option( 'goodconnect_settings' );
delete_option( 'goodconnect_accounts' );
delete_option( 'goodconnect_gf_configs' );
delete_option( 'goodconnect_gf_mappings' );
delete_option( 'goodconnect_elementor_configs' );
delete_option( 'goodconnect_elementor_mappings' );
delete_option( 'goodconnect_db_version' );
