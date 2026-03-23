<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * GHL API v2 client.
 * Handles authenticated requests to the GoHighLevel REST API.
 */
class GoodConnect_GHL_Client {

    const API_BASE = 'https://services.leadconnectorhq.com';

    private string $api_key;
    private string $location_id;

    public function __construct() {
        $this->api_key     = GoodConnect_Settings::get( 'api_key' );
        $this->location_id = GoodConnect_Settings::get( 'location_id' );
    }

    /**
     * Create or update a contact in GHL.
     *
     * @param array $data  Associative array of contact fields.
     * @return array|WP_Error  GHL API response body or WP_Error on failure.
     */
    public function upsert_contact( array $data ) {
        $data['locationId'] = $this->location_id;

        return $this->request( 'POST', '/contacts/upsert', $data );
    }

    /**
     * Create an opportunity in GHL.
     *
     * @param array $data
     * @return array|WP_Error
     */
    public function create_opportunity( array $data ) {
        $data['locationId'] = $this->location_id;

        return $this->request( 'POST', '/opportunities/', $data );
    }

    /**
     * Trigger a GHL workflow via webhook URL.
     *
     * @param string $webhook_url
     * @param array  $data
     * @return array|WP_Error
     */
    public function trigger_webhook( string $webhook_url, array $data ) {
        $response = wp_remote_post( $webhook_url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $data ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
    }

    /**
     * Internal HTTP request helper.
     */
    private function request( string $method, string $endpoint, array $body = [] ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'goodconnect_no_api_key', __( 'GoodConnect: No API key configured.', 'good-connect' ) );
        }

        $response = wp_remote_request( self::API_BASE . $endpoint, [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
                'Version'       => '2021-07-28',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            $message = $data['message'] ?? 'GHL API error';
            return new WP_Error( 'goodconnect_api_error', $message, [ 'status' => $code ] );
        }

        return $data ?? [];
    }
}
