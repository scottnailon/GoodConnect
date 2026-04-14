<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Jobber API client. Uses OAuth2 access tokens stored on the account row.
 * GraphQL endpoint: https://api.getjobber.com/api/graphql
 * Docs: https://developer.getjobber.com/docs/graphql
 */
class GoodConnect_Jobber_Client {

    const GRAPHQL_URL    = 'https://api.getjobber.com/api/graphql';
    const TOKEN_URL      = 'https://api.getjobber.com/api/oauth/token';
    const API_VERSION    = '2023-11-15';
    const REFRESH_MARGIN = 60;

    private array $account;

    public function __construct( array $account ) {
        $this->account = $account;
    }

    public function get_account_id(): string {
        return (string) ( $this->account['id'] ?? '' );
    }

    public function is_connected(): bool {
        return ! empty( $this->account['jobber_access_token'] )
            && ! empty( $this->account['jobber_refresh_token'] );
    }

    /**
     * Create a client in Jobber.
     *
     * @param array $fields Accepts keys: first_name, last_name, email, phone, address_line1, city, province, postal_code, country.
     * @return array|WP_Error Raw clientCreate payload on success.
     */
    public function create_client( array $fields ) {
        $emails = [];
        if ( ! empty( $fields['email'] ) ) {
            $emails[] = [
                'description' => 'MAIN',
                'primary'     => true,
                'address'     => sanitize_email( $fields['email'] ),
            ];
        }

        $phones = [];
        if ( ! empty( $fields['phone'] ) ) {
            $phones[] = [
                'description' => 'MAIN',
                'primary'     => true,
                'number'      => sanitize_text_field( $fields['phone'] ),
            ];
        }

        $input = [
            'firstName' => sanitize_text_field( $fields['first_name'] ?? '' ),
            'lastName'  => sanitize_text_field( $fields['last_name']  ?? '' ),
        ];
        if ( $emails ) $input['emails'] = $emails;
        if ( $phones ) $input['phones'] = $phones;

        $address = array_filter( [
            'street'     => sanitize_text_field( $fields['address_line1'] ?? '' ),
            'city'       => sanitize_text_field( $fields['city']          ?? '' ),
            'province'   => sanitize_text_field( $fields['province']      ?? '' ),
            'postalCode' => sanitize_text_field( $fields['postal_code']   ?? '' ),
            'country'    => sanitize_text_field( $fields['country']       ?? '' ),
        ], static fn( $v ) => $v !== '' );
        if ( $address ) $input['billingAddress'] = $address;

        $mutation = 'mutation CreateClient($input: ClientCreateInput!) {
            clientCreate(input: $input) {
                client { id firstName lastName }
                userErrors { message path }
            }
        }';

        $result = $this->graphql( $mutation, [ 'input' => $input ] );
        if ( is_wp_error( $result ) ) return $result;

        $payload = $result['data']['clientCreate'] ?? null;
        if ( ! $payload ) {
            return new WP_Error( 'goodconnect_jobber_empty', __( 'Jobber returned an empty response.', 'good-connect' ) );
        }
        if ( ! empty( $payload['userErrors'] ) ) {
            $msg = $payload['userErrors'][0]['message'] ?? 'Jobber rejected the client payload.';
            return new WP_Error( 'goodconnect_jobber_user_error', $msg );
        }
        return $payload;
    }

    private function graphql( string $query, array $variables ) {
        $token = $this->ensure_access_token();
        if ( is_wp_error( $token ) ) return $token;

        $response = wp_remote_post( self::GRAPHQL_URL, [
            'headers' => [
                'Authorization'            => 'Bearer ' . $token,
                'Content-Type'             => 'application/json',
                'X-JOBBER-GRAPHQL-VERSION' => self::API_VERSION,
            ],
            'body'    => wp_json_encode( [ 'query' => $query, 'variables' => $variables ] ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 401 ) {
            // Access token may have just expired between refresh check and call. Force refresh and retry once.
            $refreshed = $this->refresh_access_token();
            if ( is_wp_error( $refreshed ) ) return $refreshed;
            return $this->graphql( $query, $variables );
        }

        if ( $code >= 400 ) {
            $message = $body['errors'][0]['message'] ?? ( 'Jobber API error (' . $code . ')' );
            return new WP_Error( 'goodconnect_jobber_api_error', $message, [ 'status' => $code ] );
        }

        if ( ! empty( $body['errors'] ) ) {
            return new WP_Error( 'goodconnect_jobber_graphql', $body['errors'][0]['message'] ?? 'GraphQL error' );
        }

        return $body ?? [];
    }

    private function ensure_access_token() {
        if ( empty( $this->account['jobber_access_token'] ) ) {
            return new WP_Error( 'goodconnect_jobber_not_connected', __( 'Jobber account is not connected.', 'good-connect' ) );
        }
        $expires = (int) ( $this->account['jobber_token_expires'] ?? 0 );
        if ( $expires && $expires - self::REFRESH_MARGIN <= time() ) {
            $refreshed = $this->refresh_access_token();
            if ( is_wp_error( $refreshed ) ) return $refreshed;
        }
        return $this->account['jobber_access_token'];
    }

    private function refresh_access_token() {
        $refresh_token = $this->account['jobber_refresh_token'] ?? '';
        $client_id     = $this->account['jobber_client_id']     ?? '';
        $client_secret = $this->account['jobber_client_secret'] ?? '';

        if ( ! $refresh_token || ! $client_id || ! $client_secret ) {
            return new WP_Error( 'goodconnect_jobber_missing_refresh', __( 'Missing Jobber refresh credentials. Reconnect the account.', 'good-connect' ) );
        }

        $response = wp_remote_post( self::TOKEN_URL, [
            'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
            'body'    => http_build_query( [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refresh_token,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
            ] ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) return $response;
        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 || empty( $body['access_token'] ) ) {
            $msg = $body['error_description'] ?? ( 'Failed to refresh Jobber token (' . $code . ')' );
            return new WP_Error( 'goodconnect_jobber_refresh_failed', $msg );
        }

        $fields = [
            'jobber_access_token'  => $body['access_token'],
            'jobber_token_expires' => time() + (int) ( $body['expires_in'] ?? 3600 ),
        ];
        if ( ! empty( $body['refresh_token'] ) ) {
            $fields['jobber_refresh_token'] = $body['refresh_token'];
        }

        GoodConnect_Settings::update_account( $this->account['id'], $fields );
        $this->account = array_merge( $this->account, $fields );

        return true;
    }
}
