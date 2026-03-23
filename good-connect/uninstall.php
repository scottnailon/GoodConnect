<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

// Drop custom DB tables.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'goodconnect_activity_log' );

// Delete all plugin options.
delete_option( 'goodconnect_settings' );
delete_option( 'goodconnect_accounts' );
delete_option( 'goodconnect_gf_configs' );
delete_option( 'goodconnect_gf_mappings' );
delete_option( 'goodconnect_elementor_configs' );
delete_option( 'goodconnect_elementor_mappings' );
delete_option( 'goodconnect_cf7_configs' );
delete_option( 'goodconnect_webhook_rules' );
delete_option( 'goodconnect_webhook_secret' );
delete_option( 'goodconnect_allowed_roles' );
delete_option( 'goodconnect_protection_denied_page' );
delete_option( 'goodconnect_protection_cpt_list' );
delete_option( 'goodconnect_welcome_email_subject' );
delete_option( 'goodconnect_welcome_email_body' );
delete_option( 'goodconnect_magic_link_ttl' );
delete_option( 'goodconnect_protection_cookie_ttl' );
delete_option( GoodConnect_BulkSync::LOG_KEY );
delete_option( 'goodconnect_db_version' );

// Clear scheduled cron hooks.
wp_clear_scheduled_hook( GoodConnect_BulkSync::CRON_HOOK );
wp_clear_scheduled_hook( 'goodconnect_magic_link_cleanup' );

// Delete bulk sync progress transient.
delete_transient( GoodConnect_BulkSync::PROGRESS_KEY );
