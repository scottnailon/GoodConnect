<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_GHL_Client {

    const API_BASE = 'https://services.leadconnectorhq.com';

    private string $api_key;
    private string $location_id;
    private string $account_id;

    public function __construct( array $account = [] ) {
        if ( empty( $account ) ) {
            $account = GoodConnect_Settings::get_default_account() ?? [];
        }
        $this->api_key     = $account['api_key']     ?? '';
        $this->location_id = $account['location_id'] ?? '';
        $this->account_id  = $account['id']          ?? '';
    }

    public function get_account_id(): string {
        return $this->account_id;
    }

    public function upsert_contact( array $data ) {
        $data['locationId'] = $this->location_id;
        return $this->request( 'POST', '/contacts/upsert', $data );
    }

    public function create_opportunity( array $data ) {
        $data['locationId'] = $this->location_id;
        return $this->request( 'POST', '/opportunities/', $data );
    }

    /**
     * Get a contact by ID from GHL.
     *
     * @param string $contact_id
     * @return array|WP_Error
     */
    public function get_contact( string $contact_id ) {
        if ( empty( $contact_id ) ) {
            return new WP_Error( 'goodconnect_invalid_contact_id', __( 'Contact ID is required.', 'good-connect' ) );
        }
        return $this->request( 'GET', '/contacts/' . rawurlencode( $contact_id ) );
    }

    /**
     * Get all custom fields for the location.
     * Returns array of [ 'id' => ..., 'name' => ..., 'fieldKey' => ..., 'dataType' => ... ]
     *
     * @return array|WP_Error
     */
    public function get_custom_fields() {
        if ( empty( $this->location_id ) ) {
            return new WP_Error( 'goodconnect_no_location', __( 'GoodConnect: No Location ID configured.', 'good-connect' ) );
        }
        $result = $this->request( 'GET', '/locations/' . rawurlencode( $this->location_id ) . '/customFields' );
        if ( is_wp_error( $result ) ) return $result;
        return $result['customFields'] ?? $result['fields'] ?? [];
    }

    public function trigger_webhook( string $webhook_url, array $data ) {
        $parsed        = wp_parse_url( $webhook_url );
        $allowed_hosts = [ 'services.leadconnectorhq.com', 'backend.leadconnectorhq.com', 'hooks.zapier.com' ];
        if (
            empty( $parsed['scheme'] ) || $parsed['scheme'] !== 'https' ||
            empty( $parsed['host'] )   || ! in_array( $parsed['host'], $allowed_hosts, true )
        ) {
            return new WP_Error( 'goodconnect_invalid_webhook', __( 'GoodConnect: Webhook URL must be an HTTPS GoHighLevel webhook URL.', 'good-connect' ) );
        }

        $response = wp_remote_post( $webhook_url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $data ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) return $response;
        return json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
    }

    private function request( string $method, string $endpoint, array $body = [] ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'goodconnect_no_api_key', __( 'GoodConnect: No API key configured.', 'good-connect' ) );
        }

        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
                'Version'       => '2021-07-28',
            ],
            'timeout' => 15,
        ];

        if ( $method !== 'GET' && ! empty( $body ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( self::API_BASE . $endpoint, $args );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            $message = $data['message'] ?? 'GHL API error';
            return new WP_Error( 'goodconnect_api_error', $message, [ 'status' => $code ] );
        }

        return $data ?? [];
    }
}
