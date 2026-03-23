<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_Elementor {

    public static function init() {
        if ( ! did_action( 'elementor_pro/init' ) && ! class_exists( '\ElementorPro\Plugin' ) ) {
            add_action( 'elementor_pro/init', [ __CLASS__, 'register_hooks' ] );
            return;
        }
        self::register_hooks();
    }

    public static function register_hooks() {
        add_action( 'elementor_pro/forms/new_record', [ __CLASS__, 'handle_submission' ], 10, 2 );
    }

    public static function handle_submission( $record, $handler ) {
        $form_name  = $record->get_form_settings( 'form_name' );
        $config     = self::get_form_config( $form_name );
        if ( empty( $config['field_map'] ) && empty( $config['custom_fields'] ) ) return;

        $raw_fields = $record->get( 'fields' );

        // Conditions check.
        if ( ! GoodConnect_Conditions::passes(
            $config['conditions'] ?? [],
            fn( $id ) => $raw_fields[ $id ]['value'] ?? ''
        ) ) {
            GoodConnect_DB::log( [
                'source'   => 'elementor',
                'form_id'  => $form_name,
                'form_name'=> $form_name,
                'action'   => 'skipped_conditions',
                'success'  => 1,
            ] );
            return;
        }

        $account = ! empty( $config['account_id'] )
            ? GoodConnect_Settings::get_account_by_id( $config['account_id'] )
            : GoodConnect_Settings::get_default_account();
        if ( ! $account ) return;

        $contact = [];

        foreach ( $config['field_map'] as $ghl_field => $elementor_field_id ) {
            $value = $raw_fields[ $elementor_field_id ]['value'] ?? '';
            if ( $value !== '' ) $contact[ $ghl_field ] = sanitize_text_field( $value );
        }

        if ( ! empty( $config['custom_fields'] ) ) {
            $custom = [];
            foreach ( $config['custom_fields'] as $row ) {
                $key   = sanitize_text_field( $row['ghl_key'] ?? '' );
                $field_id = $row['elementor_field'] ?? $row['elementor_field_id'] ?? $row['gf_field_id'] ?? '';
                $value = $raw_fields[ $field_id ]['value'] ?? '';
                if ( $key && $value !== '' ) {
                    $custom[] = [ 'id' => $key, 'field_value' => sanitize_text_field( $value ) ];
                }
            }
            if ( $custom ) $contact['customFields'] = $custom;
        }

        $tags = [];
        foreach ( (array) ( $config['static_tags'] ?? [] ) as $tag ) {
            $tag = sanitize_text_field( $tag );
            if ( $tag ) $tags[] = $tag;
        }
        foreach ( (array) ( $config['dynamic_tags'] ?? [] ) as $row ) {
            $value = sanitize_text_field( $raw_fields[ $row['elementor_field_id'] ?? '' ]['value'] ?? '' );
            if ( $value ) $tags[] = $value;
        }
        if ( $tags ) $contact['tags'] = array_values( array_unique( $tags ) );

        if ( empty( $contact ) ) return;

        $client = new GoodConnect_GHL_Client( $account );
        $result = $client->upsert_contact( $contact );

        $success = ! is_wp_error( $result );
        GoodConnect_DB::log( [
            'source'         => 'elementor',
            'form_id'        => $form_name,
            'form_name'      => $form_name,
            'account_id'     => $account['id'] ?? '',
            'contact_email'  => $contact['email'] ?? '',
            'action'         => 'upsert_contact',
            'success'        => $success,
            'ghl_contact_id' => $result['contact']['id'] ?? '',
            'error_message'  => $success ? '' : $result->get_error_message(),
        ] );

        // Opportunity.
        $opp = $config['opportunity'] ?? [];
        if ( $success && ! empty( $opp['enabled'] ) && ! empty( $result['contact']['id'] ) ) {
            $contact_id     = $result['contact']['id'];
            $resolved_title = self::resolve_merge_tags( $opp['title'] ?? '', $raw_fields );
            $opp_value      = ! empty( $opp['value_type'] ) && $opp['value_type'] === 'field'
                ? ( $raw_fields[ $opp['value'] ?? '' ]['value'] ?? 0 )
                : ( $opp['value'] ?? 0 );

            $opp_result = $client->create_opportunity( [
                'pipelineId'      => $opp['pipeline_id'] ?? '',
                'pipelineStageId' => $opp['stage_id']    ?? '',
                'name'            => $resolved_title ?: $form_name,
                'monetaryValue'   => is_numeric( $opp_value ) ? (float) $opp_value : 0,
                'contactId'       => $contact_id,
            ] );

            GoodConnect_DB::log( [
                'source'         => 'elementor',
                'form_id'        => $form_name,
                'form_name'      => $form_name,
                'account_id'     => $account['id'] ?? '',
                'contact_email'  => $contact['email'] ?? '',
                'action'         => 'create_opportunity',
                'success'        => ! is_wp_error( $opp_result ),
                'ghl_contact_id' => $contact_id,
                'error_message'  => is_wp_error( $opp_result ) ? $opp_result->get_error_message() : '',
            ] );
        }

        if ( ! $success ) {
            error_log( '[GoodConnect] Elementor submission error (' . $form_name . '): ' . $result->get_error_message() );
        }
    }

    private static function resolve_merge_tags( string $template, array $raw_fields ): string {
        return preg_replace_callback( '/\{([^}]+)\}/', function ( $m ) use ( $raw_fields ) {
            return $raw_fields[ $m[1] ]['value'] ?? '';
        }, $template );
    }

    public static function get_form_config( string $form_name ): array {
        $all = get_option( 'goodconnect_elementor_configs', [] );
        return $all[ $form_name ] ?? [
            'account_id'    => '',
            'field_map'     => [],
            'custom_fields' => [],
            'static_tags'   => [],
            'dynamic_tags'  => [],
            'conditions'    => [ 'enabled' => false, 'operator' => 'AND', 'rules' => [] ],
            'opportunity'   => [ 'enabled' => false, 'pipeline_id' => '', 'stage_id' => '', 'title' => '', 'value_type' => 'static', 'value' => '' ],
        ];
    }

    public static function save_form_config( string $form_name, array $config ): void {
        $all               = get_option( 'goodconnect_elementor_configs', [] );
        $all[ $form_name ] = $config;
        update_option( 'goodconnect_elementor_configs', $all );
    }
}
