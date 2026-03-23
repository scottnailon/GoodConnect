<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_GF {

    public static function init() {
        if ( ! class_exists( 'GFForms' ) ) return;
        add_action( 'gform_after_submission', [ __CLASS__, 'handle_submission' ], 10, 2 );
    }

    public static function handle_submission( $entry, $form ) {
        $config = self::get_form_config( (int) $form['id'] );
        if ( empty( $config['field_map'] ) && empty( $config['custom_fields'] ) ) return;

        // Resolve account.
        $account = ! empty( $config['account_id'] )
            ? GoodConnect_Settings::get_account_by_id( $config['account_id'] )
            : GoodConnect_Settings::get_default_account();

        if ( ! $account ) return;

        // Build standard contact fields.
        $contact = [];
        foreach ( $config['field_map'] as $ghl_field => $gf_field_id ) {
            $value = rgar( $entry, (string) $gf_field_id );
            if ( $value !== '' && $value !== null ) {
                $contact[ $ghl_field ] = sanitize_text_field( $value );
            }
        }

        // Build custom fields.
        if ( ! empty( $config['custom_fields'] ) ) {
            $custom = [];
            foreach ( $config['custom_fields'] as $row ) {
                $key   = sanitize_text_field( $row['ghl_key'] ?? '' );
                $value = rgar( $entry, (string) ( $row['gf_field_id'] ?? '' ) );
                if ( $key && $value !== '' && $value !== null ) {
                    $custom[] = [ 'id' => $key, 'field_value' => sanitize_text_field( $value ) ];
                }
            }
            if ( $custom ) $contact['customFields'] = $custom;
        }

        // Build tags.
        $tags = [];
        foreach ( (array) ( $config['static_tags'] ?? [] ) as $tag ) {
            $tag = sanitize_text_field( $tag );
            if ( $tag ) $tags[] = $tag;
        }
        foreach ( (array) ( $config['dynamic_tags'] ?? [] ) as $row ) {
            $value = sanitize_text_field( rgar( $entry, (string) ( $row['gf_field_id'] ?? '' ) ) );
            if ( $value ) $tags[] = $value;
        }
        if ( $tags ) $contact['tags'] = array_values( array_unique( $tags ) );

        if ( empty( $contact ) ) return;

        $client = new GoodConnect_GHL_Client( $account );
        $result = $client->upsert_contact( $contact );

        $success = ! is_wp_error( $result );
        GoodConnect_DB::log( [
            'source'         => 'gravity-forms',
            'form_id'        => (string) $form['id'],
            'form_name'      => $form['title'] ?? '',
            'account_id'     => $account['id'] ?? '',
            'contact_email'  => $contact['email'] ?? '',
            'action'         => 'upsert_contact',
            'success'        => $success,
            'ghl_contact_id' => $result['contact']['id'] ?? '',
            'error_message'  => $success ? '' : $result->get_error_message(),
        ] );

        if ( ! $success ) {
            error_log( '[GoodConnect] GF submission error (form ' . $form['id'] . '): ' . $result->get_error_message() );
        }
    }

    public static function get_form_config( int $form_id ): array {
        $all = get_option( 'goodconnect_gf_configs', [] );
        return $all[ $form_id ] ?? [
            'account_id'    => '',
            'field_map'     => [],
            'custom_fields' => [],
            'static_tags'   => [],
            'dynamic_tags'  => [],
        ];
    }

    public static function save_form_config( int $form_id, array $config ): void {
        $all               = get_option( 'goodconnect_gf_configs', [] );
        $all[ $form_id ]   = $config;
        update_option( 'goodconnect_gf_configs', $all );
    }
}
