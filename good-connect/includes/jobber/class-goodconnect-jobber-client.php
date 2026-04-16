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
    const API_VERSION    = '2026-03-10';
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
            $formatted = ! empty( $fields['format_phone_au'] )
                ? self::format_au_phone( $fields['phone'] )
                : sanitize_text_field( $fields['phone'] );
            $phones[] = [
                'description' => 'MAIN',
                'primary'     => true,
                'number'      => $formatted,
            ];
        }

        $input = [
            'firstName' => sanitize_text_field( $fields['first_name'] ?? '' ),
            'lastName'  => sanitize_text_field( $fields['last_name']  ?? '' ),
        ];
        if ( $emails ) $input['emails'] = $emails;
        if ( $phones ) $input['phones'] = $phones;

        $address = array_filter( [
            'street1'    => sanitize_text_field( $fields['address_line1'] ?? '' ),
            'city'       => sanitize_text_field( $fields['city']          ?? '' ),
            'province'   => sanitize_text_field( $fields['province']      ?? '' ),
            'postalCode' => sanitize_text_field( $fields['postal_code']   ?? '' ),
            'country'    => sanitize_text_field( $fields['country']       ?? '' ),
        ], static fn( $v ) => $v !== '' );
        if ( $address ) {
            $input['billingAddress'] = $address;
            $input['properties']    = [ [ 'address' => $address ] ];
        }

        if ( ! empty( $fields['track_source_url'] ) ) {
            $source_url = sanitize_url( $fields['source_url'] ?? '' );
            if ( $source_url !== '' ) {
                $input['sourceAttribution'] = [ 'sourceText' => $source_url ];
            }
        }

        $mutation = 'mutation CreateClient($input: ClientCreateInput!) {
            clientCreate(input: $input) {
                client { id firstName lastName properties { id } }
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

    /**
     * Create a request (service inquiry) in Jobber, linked to a client.
     *
     * @param string $client_id Jobber client node ID (from clientCreate).
     * @param string $title     Short title for the request.
     * @param string $details   Full description / message from the form.
     * @return array|WP_Error Raw requestCreate payload on success.
     */
    public function create_request( string $client_id, string $title, string $details = '', string $property_id = '' ) {
        $input = [
            'clientId' => $client_id,
        ];
        if ( $property_id !== '' ) {
            $input['propertyId'] = $property_id;
        }
        if ( $details !== '' ) {
            $input['assessment'] = [
                'instructions' => sanitize_textarea_field( $details ),
            ];
        }

        $mutation = 'mutation CreateRequest($input: RequestCreateInput!) {
            requestCreate(input: $input) {
                request { id }
                userErrors { message path }
            }
        }';

        $result = $this->graphql( $mutation, [ 'input' => $input ] );
        if ( is_wp_error( $result ) ) return $result;

        $payload = $result['data']['requestCreate'] ?? null;
        if ( ! $payload ) {
            return new WP_Error( 'goodconnect_jobber_empty', __( 'Jobber returned an empty response for request.', 'good-connect' ) );
        }
        if ( ! empty( $payload['userErrors'] ) ) {
            $msg = $payload['userErrors'][0]['message'] ?? 'Jobber rejected the request payload.';
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

    /**
     * Get all properties for a client.
     *
     * @param string $client_id Jobber client node ID.
     * @return array List of properties with 'id' and 'address' keys.
     */
    public function get_client_properties( string $client_id ): array {
        $query = 'query GetProperties($id: EncodedId!) {
            client(id: $id) {
                properties { id address { street1 city province postalCode } }
            }
        }';

        $result = $this->graphql( $query, [ 'id' => $client_id ] );
        if ( is_wp_error( $result ) ) return [];

        return $result['data']['client']['properties'] ?? [];
    }

    /**
     * Add a new property to an existing client.
     *
     * @param string $client_id Jobber client node ID.
     * @param array  $address   Address fields: street1, city, province, postalCode, country.
     * @return array|WP_Error   The updated client with properties on success.
     */
    public function add_property( string $client_id, array $address ) {
        $mutation = 'mutation AddProperty($clientId: EncodedId!, $input: ClientEditInput!) {
            clientEdit(clientId: $clientId, input: $input) {
                client { id properties { id address { street1 city province postalCode } } }
                userErrors { message path }
            }
        }';

        $input = [
            'propertiesToAdd' => [ [ 'address' => $address ] ],
        ];

        $result = $this->graphql( $mutation, [ 'clientId' => $client_id, 'input' => $input ] );
        if ( is_wp_error( $result ) ) return $result;

        $payload = $result['data']['clientEdit'] ?? null;
        if ( ! empty( $payload['userErrors'] ) ) {
            return new WP_Error( 'goodconnect_jobber_user_error', $payload['userErrors'][0]['message'] ?? 'Failed to add property.' );
        }
        return $payload;
    }

    /**
     * Search Jobber for an existing client by email address.
     *
     * @param string $email Email to search for.
     * @return array|null  Client array with 'id', 'firstName', 'lastName', 'isLead' — or null if not found.
     */
    public function find_client_by_email( string $email ) {
        if ( empty( $email ) ) return null;

        $query = 'query FindClient($searchTerm: String!) {
            clients(searchTerm: $searchTerm, first: 5) {
                nodes { id firstName lastName isLead }
            }
        }';

        $result = $this->graphql( $query, [ 'searchTerm' => sanitize_email( $email ) ] );
        if ( is_wp_error( $result ) ) return null;

        $nodes = $result['data']['clients']['nodes'] ?? [];
        if ( empty( $nodes ) ) return null;

        // Return the first match (Jobber's searchTerm matches on email).
        return $nodes[0];
    }

    /**
     * Format an Australian phone number as XXXX XXX XXX.
     * Strips non-digits, prepends 0 if starting with 4, then inserts spaces.
     */
    private static function format_au_phone( string $raw ): string {
        $digits = preg_replace( '/\D/', '', $raw );

        // Handle +61 prefix (e.g. +61412345678 → 0412345678).
        if ( str_starts_with( $digits, '61' ) && strlen( $digits ) >= 11 ) {
            $digits = '0' . substr( $digits, 2 );
        }

        // If 9 digits starting with 4, prepend 0 (e.g. 412345678 → 0412345678).
        if ( strlen( $digits ) === 9 && str_starts_with( $digits, '4' ) ) {
            $digits = '0' . $digits;
        }

        // Format 10-digit numbers as XXXX XXX XXX.
        if ( strlen( $digits ) === 10 ) {
            return substr( $digits, 0, 4 ) . ' ' . substr( $digits, 4, 3 ) . ' ' . substr( $digits, 7, 3 );
        }

        // Fallback: return cleaned digits if not a standard AU number.
        return $digits;
    }
}
