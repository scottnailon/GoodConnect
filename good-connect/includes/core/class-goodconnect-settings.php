<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_Settings {

    const OPTION_KEY      = 'goodconnect_settings';
    const ACCOUNTS_KEY    = 'goodconnect_accounts';

    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
    }

    public static function register_settings() {
        register_setting( 'goodconnect', self::OPTION_KEY, [ __CLASS__, 'sanitize' ] );
    }

    /** Get a value from the legacy single-account settings (used for WooCommerce compat). */
    public static function get( $key, $default = '' ) {
        // Try to serve from the default account first.
        $account = self::get_default_account();
        if ( $account ) {
            if ( $key === 'api_key' )     return $account['api_key'];
            if ( $key === 'location_id' ) return $account['location_id'];
        }
        $options = get_option( self::OPTION_KEY, [] );
        return $options[ $key ] ?? $default;
    }

    public static function sanitize( $input ) {
        $clean = [];
        $clean['woo_enabled'] = ! empty( $input['woo_enabled'] ) ? '1' : '0';
        $clean['woo_account_id'] = sanitize_text_field( $input['woo_account_id'] ?? '' );
        return $clean;
    }

    // -------------------------------------------------------------------------
    // Accounts
    // -------------------------------------------------------------------------

    public static function get_accounts(): array {
        $accounts = get_option( self::ACCOUNTS_KEY, null );

        // First-run migration: seed from legacy single-account settings.
        if ( $accounts === null ) {
            $legacy = get_option( self::OPTION_KEY, [] );
            if ( ! empty( $legacy['api_key'] ) ) {
                $accounts = [ [
                    'id'          => 'account_' . substr( md5( $legacy['api_key'] ), 0, 8 ),
                    'label'       => 'Default',
                    'provider'    => 'ghl',
                    'api_key'     => $legacy['api_key'],
                    'location_id' => $legacy['location_id'] ?? '',
                    'is_default'  => true,
                ] ];
            } else {
                $accounts = [];
            }
            update_option( self::ACCOUNTS_KEY, $accounts );
        }

        // Backfill provider on any legacy accounts that pre-date the Jobber integration.
        $changed = false;
        foreach ( $accounts as &$account ) {
            if ( empty( $account['provider'] ) ) {
                $account['provider'] = 'ghl';
                $changed = true;
            }
        }
        unset( $account );
        if ( $changed ) update_option( self::ACCOUNTS_KEY, $accounts );

        return (array) $accounts;
    }

    public static function save_accounts( array $accounts ): void {
        update_option( self::ACCOUNTS_KEY, $accounts );
    }

    /**
     * Update a single account in place by ID. Merges provided fields over existing.
     * Used by the Jobber OAuth callback to persist tokens without disturbing other fields.
     */
    public static function update_account( string $id, array $fields ): bool {
        $accounts = self::get_accounts();
        $found = false;
        foreach ( $accounts as &$account ) {
            if ( $account['id'] === $id ) {
                $account = array_merge( $account, $fields );
                $found = true;
                break;
            }
        }
        unset( $account );
        if ( $found ) self::save_accounts( $accounts );
        return $found;
    }

    public static function get_default_account(): ?array {
        $accounts = self::get_accounts();
        foreach ( $accounts as $account ) {
            if ( ! empty( $account['is_default'] ) ) return $account;
        }
        return $accounts[0] ?? null;
    }

    public static function get_account_by_id( string $id ): ?array {
        foreach ( self::get_accounts() as $account ) {
            if ( $account['id'] === $id ) return $account;
        }
        return null;
    }
}
