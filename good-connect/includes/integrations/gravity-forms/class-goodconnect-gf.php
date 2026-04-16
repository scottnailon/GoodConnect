<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_GF {

    public static function init() {
        if ( ! class_exists( 'GFForms' ) ) return;
        add_action( 'gform_after_submission', [ __CLASS__, 'handle_submission' ], 10, 2 );
        add_filter( 'gform_entry_meta', [ __CLASS__, 'register_entry_meta' ], 10, 2 );
    }

    /**
     * Register Jobber ID fields so they appear in GF entry detail and exports.
     */
    public static function register_entry_meta( $entry_meta, $form_id ) {
        $entry_meta['goodconnect_jobber_client_id'] = [
            'label'             => __( 'Jobber Client ID', 'good-connect' ),
            'is_numeric'        => false,
            'is_default_column' => false,
        ];
        $entry_meta['goodconnect_jobber_request_id'] = [
            'label'             => __( 'Jobber Request ID', 'good-connect' ),
            'is_numeric'        => false,
            'is_default_column' => false,
        ];
        return $entry_meta;
    }

    public static function handle_submission( $entry, $form ) {
        $form_id = (int) $form['id'];
        $config  = self::get_form_config( $form_id );
        if ( empty( $config['field_map'] ) && empty( $config['custom_fields'] ) ) return;

        // Conditions check.
        if ( ! GoodConnect_Conditions::passes(
            $config['conditions'] ?? [],
            fn( $id ) => rgar( $entry, (string) $id )
        ) ) {
            GoodConnect_DB::log( [
                'source'   => 'gravity-forms',
                'form_id'  => (string) $form_id,
                'form_name'=> $form['title'] ?? '',
                'action'   => 'skipped_conditions',
                'success'  => 1,
            ] );
            return;
        }

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

        // Route to Jobber when the configured account uses the Jobber provider.
        if ( ( $account['provider'] ?? 'ghl' ) === 'jobber' ) {
            self::send_to_jobber( $account, $contact, $form_id, $form, $entry );
            return;
        }

        $client = new GoodConnect_GHL_Client( $account );
        $result = $client->upsert_contact( $contact );

        $success = ! is_wp_error( $result );
        GoodConnect_DB::log( [
            'source'         => 'gravity-forms',
            'form_id'        => (string) $form_id,
            'form_name'      => $form['title'] ?? '',
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
            $resolved_title = self::resolve_merge_tags( $opp['title'] ?? '', $entry );
            $opp_value      = ! empty( $opp['value_type'] ) && $opp['value_type'] === 'field'
                ? rgar( $entry, (string) ( $opp['value'] ?? '' ) )
                : ( $opp['value'] ?? 0 );

            $opp_result = $client->create_opportunity( [
                'pipelineId'      => $opp['pipeline_id'] ?? '',
                'pipelineStageId' => $opp['stage_id']    ?? '',
                'name'            => $resolved_title ?: ( $form['title'] ?? '' ),
                'monetaryValue'   => is_numeric( $opp_value ) ? (float) $opp_value : 0,
                'contactId'       => $contact_id,
            ] );

            GoodConnect_DB::log( [
                'source'         => 'gravity-forms',
                'form_id'        => (string) $form_id,
                'form_name'      => $form['title'] ?? '',
                'account_id'     => $account['id'] ?? '',
                'contact_email'  => $contact['email'] ?? '',
                'action'         => 'create_opportunity',
                'success'        => ! is_wp_error( $opp_result ),
                'ghl_contact_id' => $contact_id,
                'error_message'  => is_wp_error( $opp_result ) ? $opp_result->get_error_message() : '',
            ] );
        }

        // Errors are captured in the activity log above.
    }

    /**
     * Dispatch a form submission to Jobber's clientCreate mutation.
     *
     * Reuses the GHL field-map keys so users don't have to re-map for Jobber.
     * Mapping: firstName/lastName/email/phone/address1/city/state/postalCode/country.
     */
    /**
     * Dispatch a form submission to Jobber: create client, then create request.
     *
     * Reuses the GHL field-map keys so users don't have to re-map for Jobber.
     * Mapping: firstName/lastName/email/phone/address1/city/state/postalCode/country.
     */
    private static function send_to_jobber( array $account, array $contact, int $form_id, array $form, array $entry ): void {
        $jobber_fields = [
            'first_name'    => $contact['firstName']  ?? '',
            'last_name'     => $contact['lastName']   ?? '',
            'email'         => $contact['email']      ?? '',
            'phone'         => $contact['phone']      ?? '',
            'address_line1' => $contact['address1']   ?? '',
            'city'          => $contact['city']        ?? '',
            'province'      => $contact['state']       ?? '',
            'postal_code'   => $contact['postalCode']  ?? '',
            'country'       => $contact['country']     ?? '',
            'source_url'       => $entry['source_url']    ?? '',
            'format_phone_au'  => ! empty( $account['jobber_format_phone_au'] ),
            'track_source_url' => ! empty( $account['jobber_track_source_url'] ),
        ];

        $client = new GoodConnect_Jobber_Client( $account );

        // 1. Search for existing client by email first (avoids duplicates and lead status).
        $search_existing = $account['jobber_search_existing'] ?? true;
        $existing = ( $search_existing && ! empty( $jobber_fields['email'] ) )
            ? $client->find_client_by_email( $jobber_fields['email'] )
            : null;

        if ( $existing && ! empty( $existing['id'] ) ) {
            // Use the existing client — no new lead created.
            $result  = [ 'client' => $existing ];
            $success = true;

            GoodConnect_DB::log( [
                'source'         => 'gravity-forms',
                'form_id'        => (string) $form_id,
                'form_name'      => $form['title'] ?? '',
                'account_id'     => $account['id'] ?? '',
                'contact_email'  => $contact['email'] ?? '',
                'action'         => 'found_existing_jobber_client',
                'success'        => 1,
                'ghl_contact_id' => $existing['id'],
                'error_message'  => '',
            ] );

            // Check if the submitted address is new — add as a property if so.
            $add_property = $account['jobber_add_property'] ?? true;
            $submitted_street = sanitize_text_field( $jobber_fields['address_line1'] ?? '' );
            if ( $add_property && $submitted_street !== '' ) {
                $properties = $client->get_client_properties( $existing['id'] );
                $address_exists = false;
                foreach ( $properties as $prop ) {
                    $existing_street = $prop['address']['street1'] ?? '';
                    if ( strcasecmp( trim( $existing_street ), trim( $submitted_street ) ) === 0 ) {
                        $address_exists = true;
                        // Use this property for the request.
                        $result['client']['properties'] = [ $prop ];
                        break;
                    }
                }
                if ( ! $address_exists ) {
                    $new_address = array_filter( [
                        'street1'    => $submitted_street,
                        'city'       => sanitize_text_field( $jobber_fields['city'] ?? '' ),
                        'province'   => sanitize_text_field( $jobber_fields['province'] ?? '' ),
                        'postalCode' => sanitize_text_field( $jobber_fields['postal_code'] ?? '' ),
                        'country'    => sanitize_text_field( $jobber_fields['country'] ?? '' ),
                    ], static fn( $v ) => $v !== '' );

                    $prop_result = $client->add_property( $existing['id'], $new_address );
                    if ( ! is_wp_error( $prop_result ) ) {
                        // Use the latest property (the one we just added) for the request.
                        $all_props = $prop_result['client']['properties'] ?? [];
                        $result['client']['properties'] = $all_props ? [ end( $all_props ) ] : [];

                        GoodConnect_DB::log( [
                            'source'         => 'gravity-forms',
                            'form_id'        => (string) $form_id,
                            'form_name'      => $form['title'] ?? '',
                            'account_id'     => $account['id'] ?? '',
                            'contact_email'  => $contact['email'] ?? '',
                            'action'         => 'add_jobber_property',
                            'success'        => 1,
                            'ghl_contact_id' => $existing['id'],
                            'error_message'  => '',
                        ] );
                    }
                }
            }
        } else {
            // No existing client — create a new one (will be a lead).
            $result = $client->create_client( $jobber_fields );
            $success = ! is_wp_error( $result );

            GoodConnect_DB::log( [
                'source'         => 'gravity-forms',
                'form_id'        => (string) $form_id,
                'form_name'      => $form['title'] ?? '',
                'account_id'     => $account['id'] ?? '',
                'contact_email'  => $contact['email'] ?? '',
                'action'         => 'create_jobber_client',
                'success'        => $success,
                'ghl_contact_id' => $success ? ( $result['client']['id'] ?? '' ) : '',
                'error_message'  => $success ? '' : $result->get_error_message(),
            ] );
        }

        // 2. Create request linked to the new client (if enabled).
        $create_request = ! empty( $account['jobber_create_request'] );
        if ( $create_request && $success && ! empty( $result['client']['id'] ) ) {
            $client_id   = $result['client']['id'];
            $property_id = $result['client']['properties'][0]['id'] ?? '';
            $title       = sprintf( '%s — %s', $form['title'] ?? 'Web Enquiry', trim( ( $contact['firstName'] ?? '' ) . ' ' . ( $contact['lastName'] ?? '' ) ) );
            $details     = $contact['message'] ?? $contact['notes'] ?? '';

            $req_result  = $client->create_request( $client_id, $title, $details, $property_id );
            $req_success = ! is_wp_error( $req_result );

            GoodConnect_DB::log( [
                'source'         => 'gravity-forms',
                'form_id'        => (string) $form_id,
                'form_name'      => $form['title'] ?? '',
                'account_id'     => $account['id'] ?? '',
                'contact_email'  => $contact['email'] ?? '',
                'action'         => 'create_jobber_request',
                'success'        => $req_success,
                'ghl_contact_id' => $client_id,
                'error_message'  => $req_success ? '' : $req_result->get_error_message(),
            ] );

            // Write Jobber IDs back to the Gravity Forms entry.
            $entry_id = (int) ( $entry['id'] ?? 0 );
            if ( $entry_id ) {
                gform_update_meta( $entry_id, 'goodconnect_jobber_client_id', $client_id );
                if ( $req_success && ! empty( $req_result['request']['id'] ) ) {
                    gform_update_meta( $entry_id, 'goodconnect_jobber_request_id', $req_result['request']['id'] );
                }
            }
        }
    }

    private static function resolve_merge_tags( string $template, $entry ): string {
        return preg_replace_callback( '/\{(\d+(?:\.\d+)?)\}/', function ( $m ) use ( $entry ) {
            return rgar( $entry, $m[1] ) ?: '';
        }, $template );
    }

    public static function get_form_config( int $form_id ): array {
        $all = get_option( 'goodconnect_gf_configs', [] );
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
        $all             = get_option( 'goodconnect_gf_configs', [] );
        $all[ $form_id ] = $config;
        update_option( 'goodconnect_gf_configs', $all );
    }
}
