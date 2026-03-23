<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_DB {

    const TABLE_ACTIVITY_LOG = 'goodconnect_activity_log';
    const DB_VERSION = '1.2.0';

    public static function install() {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE_ACTIVITY_LOG;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            source         VARCHAR(32)         NOT NULL DEFAULT '',
            form_id        VARCHAR(128)        NOT NULL DEFAULT '',
            form_name      VARCHAR(255)        NOT NULL DEFAULT '',
            account_id     VARCHAR(64)         NOT NULL DEFAULT '',
            contact_email  VARCHAR(255)        NOT NULL DEFAULT '',
            action         VARCHAR(64)         NOT NULL DEFAULT '',
            success        TINYINT(1)          NOT NULL DEFAULT 0,
            ghl_contact_id VARCHAR(64)         NOT NULL DEFAULT '',
            error_message  TEXT,
            PRIMARY KEY  (id),
            KEY idx_source_form (source, form_id(64)),
            KEY idx_created_at  (created_at),
            KEY idx_success     (success)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'goodconnect_db_version', self::DB_VERSION );
    }

    public static function maybe_upgrade() {
        $installed = get_option( 'goodconnect_db_version', '0' );
        if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
            self::install();
            self::migrate_gf_configs();
        }
    }

    /**
     * Migrate old flat goodconnect_gf_mappings to new goodconnect_gf_configs shape.
     */
    public static function migrate_gf_configs() {
        $old = get_option( 'goodconnect_gf_mappings' );
        if ( ! $old || get_option( 'goodconnect_gf_configs' ) !== false ) {
            return;
        }
        $new = [];
        foreach ( (array) $old as $form_id => $field_map ) {
            $new[ $form_id ] = [
                'account_id'    => '',
                'field_map'     => (array) $field_map,
                'custom_fields' => [],
                'static_tags'   => [],
                'dynamic_tags'  => [],
            ];
        }
        update_option( 'goodconnect_gf_configs', $new );
        delete_option( 'goodconnect_gf_mappings' );
    }

    public static function log( array $args ) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . self::TABLE_ACTIVITY_LOG,
            [
                'created_at'     => current_time( 'mysql' ),
                'source'         => sanitize_text_field( $args['source']         ?? '' ),
                'form_id'        => sanitize_text_field( $args['form_id']        ?? '' ),
                'form_name'      => sanitize_text_field( $args['form_name']      ?? '' ),
                'account_id'     => sanitize_text_field( $args['account_id']     ?? '' ),
                'contact_email'  => sanitize_email(      $args['contact_email']  ?? '' ),
                'action'         => sanitize_text_field( $args['action']         ?? '' ),
                'success'        => (int) ( $args['success'] ?? 0 ),
                'ghl_contact_id' => sanitize_text_field( $args['ghl_contact_id'] ?? '' ),
                'error_message'  => sanitize_textarea_field( $args['error_message'] ?? '' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
        );
    }

    public static function get_logs( array $args = [] ) {
        global $wpdb;
        $table    = $wpdb->prefix . self::TABLE_ACTIVITY_LOG;
        $per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
        $page     = max( 1, (int) ( $args['page']     ?? 1  ) );
        $offset   = ( $page - 1 ) * $per_page;
        $where    = '1=1';
        $values   = [];

        if ( ! empty( $args['source'] ) ) {
            $where   .= ' AND source = %s';
            $values[] = $args['source'];
        }
        if ( ! empty( $args['success'] ) && $args['success'] !== 'all' ) {
            $where   .= ' AND success = %d';
            $values[] = (int) $args['success'];
        }

        if ( $values ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d", array_merge( $values, [ $per_page, $offset ] ) ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $values ) );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        }

        return [ 'rows' => $rows ?: [], 'total' => $total ];
    }

    public static function clear_log() {
        global $wpdb;
        $wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . self::TABLE_ACTIVITY_LOG ); // phpcs:ignore
    }
}
