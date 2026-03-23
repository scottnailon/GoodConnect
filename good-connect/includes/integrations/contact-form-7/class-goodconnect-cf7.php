<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_CF7 {

    public static function init() {
        if ( ! class_exists( 'WPCF7' ) ) return;
        add_action( 'wpcf7_mail_sent', [ __CLASS__, 'handle_submission' ] );
    }

    public static function handle_submission( $contact_form ) {
        $form_id = $contact_form->id();
        $config  = self::get_form_config( $form_id );
        if ( empty( $config['field_map'] ) && empty( $config['custom_fields'] ) ) return;

        $submission = WPCF7_Submission::get_instance();
        if ( ! $submission ) return;
        $data = $submission->get_posted_data();

        // Conditions check.
        if ( ! GoodConnect_Conditions::passes(
            $config['conditions'] ?? [],
            fn( $id ) => $data[ $id ] ?? ''
        ) ) {
            GoodConnect_DB::log( [
                'source'   => 'contact-form-7',
                'form_id'  => (string) $form_id,
                'form_name'=> $contact_form->title(),
                'action'   => 'skipped_conditions',
                'success'  => 1,
            ] );
            return;
        }

        // Account.
        $account = ! empty( $config['account_id'] )
            ? GoodConnect_Settings::get_account_by_id( $config['account_id'] )
            : GoodConnect_Settings::get_default_account();
        if ( ! $account ) return;

        // Build contact.
        $contact = [];
        foreach ( $config['field_map'] as $ghl_field => $cf7_field ) {
            $value = $data[ $cf7_field ] ?? '';
            if ( is_array( $value ) ) $value = implode( ', ', $value );
            if ( (string) $value !== '' ) $contact[ $ghl_field ] = sanitize_text_field( $value );
        }

        if ( ! empty( $config['custom_fields'] ) ) {
            $custom = [];
            foreach ( $config['custom_fields'] as $row ) {
                $key   = sanitize_text_field( $row['ghl_key'] ?? '' );
                $value = $data[ $row['cf7_field'] ?? '' ] ?? '';
                if ( is_array( $value ) ) $value = implode( ', ', $value );
                if ( $key && (string) $value !== '' ) {
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
            $value = $data[ $row['cf7_field'] ?? '' ] ?? '';
            if ( is_array( $value ) ) $value = implode( ', ', $value );
            $value = sanitize_text_field( $value );
            if ( $value ) $tags[] = $value;
        }
        if ( $tags ) $contact['tags'] = array_values( array_unique( $tags ) );

        if ( empty( $contact ) ) return;

        $client = new GoodConnect_GHL_Client( $account );
        $result = $client->upsert_contact( $contact );
        $success = ! is_wp_error( $result );

        GoodConnect_DB::log( [
            'source'         => 'contact-form-7',
            'form_id'        => (string) $form_id,
            'form_name'      => $contact_form->title(),
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
            $contact_id    = $result['contact']['id'];
            $resolved_title = self::resolve_merge_tags( $opp['title'] ?? '', $data );
            $opp_value      = ! empty( $opp['value_type'] ) && $opp['value_type'] === 'field'
                ? ( $data[ $opp['value'] ?? '' ] ?? 0 )
                : ( $opp['value'] ?? 0 );

            $opp_result = $client->create_opportunity( [
                'pipelineId'      => $opp['pipeline_id'] ?? '',
                'pipelineStageId' => $opp['stage_id']    ?? '',
                'name'            => $resolved_title ?: $contact_form->title(),
                'monetaryValue'   => is_numeric( $opp_value ) ? (float) $opp_value : 0,
                'contactId'       => $contact_id,
            ] );

            GoodConnect_DB::log( [
                'source'         => 'contact-form-7',
                'form_id'        => (string) $form_id,
                'form_name'      => $contact_form->title(),
                'account_id'     => $account['id'] ?? '',
                'contact_email'  => $contact['email'] ?? '',
                'action'         => 'create_opportunity',
                'success'        => ! is_wp_error( $opp_result ),
                'ghl_contact_id' => $contact_id,
                'error_message'  => is_wp_error( $opp_result ) ? $opp_result->get_error_message() : '',
            ] );
        }

        if ( ! $success ) {
            error_log( '[GoodConnect] CF7 submission error (form ' . $form_id . '): ' . $result->get_error_message() );
        }
    }

    private static function resolve_merge_tags( string $template, array $data ): string {
        return preg_replace_callback( '/\{([^}]+)\}/', function ( $m ) use ( $data ) {
            return $data[ $m[1] ] ?? '';
        }, $template );
    }

    public static function get_form_config( int $form_id ): array {
        $all = get_option( 'goodconnect_cf7_configs', [] );
        return $all[ $form_id ] ?? [
            'account_id'    => '',
            'field_map'     => [],
            'custom_fields' => [],
            'static_tags'   => [],
            'dynamic_tags'  => [],
            'conditions'    => [ 'enabled' => false, 'operator' => 'AND', 'rules' => [] ],
            'opportunity'   => [ 'enabled' => false, 'pipeline_id' => '', 'stage_id' => '', 'title' => '', 'value_type' => 'static', 'value' => '' ],
        ];
    }

    public static function save_form_config( int $form_id, array $config ): void {
        $all             = get_option( 'goodconnect_cf7_configs', [] );
        $all[ $form_id ] = $config;
        update_option( 'goodconnect_cf7_configs', $all );
    }
}
