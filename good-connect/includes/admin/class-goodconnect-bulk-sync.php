<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_BulkSync {

    const CRON_HOOK     = 'goodconnect_bulk_sync_batch';
    const PROGRESS_KEY  = 'goodconnect_bulk_sync_progress';
    const LOG_KEY       = 'goodconnect_bulk_sync_log';
    const BATCH_SIZE    = 20;

    public static function init() {
        add_action( self::CRON_HOOK, [ __CLASS__, 'process_batch' ] );
        add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_interval' ] );
    }

    public static function add_cron_interval( $schedules ) {
        $schedules['goodconnect_1min'] = [
            'interval' => 60,
            'display'  => __( 'Every Minute (GoodConnect)', 'good-connect' ),
        ];
        return $schedules;
    }

    public static function start( string $account_id = '' ): bool {
        // Prevent double-start.
        if ( self::get_progress() ) return false;

        $total = self::count_users();

        $progress = [
            'status'     => 'running',
            'account_id' => $account_id,
            'total'      => $total,
            'processed'  => 0,
            'errors'     => 0,
            'started_at' => time(),
            'offset'     => 0,
        ];
        set_transient( self::PROGRESS_KEY, $progress, 4 * HOUR_IN_SECONDS );

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'goodconnect_1min', self::CRON_HOOK );
        }

        return true;
    }

    public static function cancel(): void {
        $progress = self::get_progress();
        if ( $progress ) {
            $progress['status'] = 'cancelled';
            set_transient( self::PROGRESS_KEY, $progress, 5 * MINUTE_IN_SECONDS );
        }
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    public static function get_progress(): ?array {
        $p = get_transient( self::PROGRESS_KEY );
        return $p ?: null;
    }

    public static function process_batch(): void {
        $progress = self::get_progress();
        if ( ! $progress || $progress['status'] !== 'running' ) {
            wp_clear_scheduled_hook( self::CRON_HOOK );
            return;
        }

        $account = ! empty( $progress['account_id'] )
            ? GoodConnect_Settings::get_account_by_id( $progress['account_id'] )
            : GoodConnect_Settings::get_default_account();

        if ( ! $account ) {
            $progress['status'] = 'error';
            set_transient( self::PROGRESS_KEY, $progress, 5 * MINUTE_IN_SECONDS );
            wp_clear_scheduled_hook( self::CRON_HOOK );
            return;
        }

        $users = get_users( [
            'number' => self::BATCH_SIZE,
            'offset' => $progress['offset'],
            'fields' => [ 'ID', 'user_email', 'display_name', 'first_name', 'last_name' ],
        ] );

        if ( empty( $users ) ) {
            // Done.
            $progress['status'] = 'done';
            set_transient( self::PROGRESS_KEY, $progress, 5 * MINUTE_IN_SECONDS );
            wp_clear_scheduled_hook( self::CRON_HOOK );

            update_option( self::LOG_KEY, [
                'completed_at' => current_time( 'mysql' ),
                'total'        => $progress['total'],
                'processed'    => $progress['processed'],
                'errors'       => $progress['errors'],
            ] );

            GoodConnect_DB::log( [
                'source'    => 'bulk-sync',
                'form_name' => 'Bulk User Sync',
                'action'    => 'bulk_sync_complete',
                'success'   => 1,
                'error_message' => sprintf( '%d synced, %d errors', $progress['processed'], $progress['errors'] ),
            ] );
            return;
        }

        $client = new GoodConnect_GHL_Client( $account );
        $errors = 0;

        foreach ( $users as $user ) {
            $contact = array_filter( [
                'firstName' => $user->first_name  ?: '',
                'lastName'  => $user->last_name   ?: '',
                'email'     => $user->user_email,
                'tags'      => [ 'wordpress-user' ],
                'source'    => 'WordPress Bulk Sync',
            ] );

            $result = $client->upsert_contact( $contact );
            if ( is_wp_error( $result ) ) {
                $errors++;
                error_log( '[GoodConnect] Bulk sync error (user ' . $user->ID . '): ' . $result->get_error_message() );
            }

            // Respect GHL rate limits — sleep 100ms between calls.
            usleep( 100000 );
        }

        $progress['processed'] += count( $users );
        $progress['errors']    += $errors;
        $progress['offset']    += self::BATCH_SIZE;

        set_transient( self::PROGRESS_KEY, $progress, 4 * HOUR_IN_SECONDS );
    }

    private static function count_users(): int {
        $count = count_users();
        return (int) $count['total_users'];
    }
}
