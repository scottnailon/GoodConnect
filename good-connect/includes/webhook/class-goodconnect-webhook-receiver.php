<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_Webhook_Receiver {

    const NAMESPACE = 'goodconnect/v1';
    const ROUTE     = '/webhook';
    const SECRET_OPTION = 'goodconnect_webhook_secret';

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes() {
        register_rest_route( self::NAMESPACE, self::ROUTE, [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'handle_request' ],
            // Authentication is performed inside handle_request() via secret token (hash_equals).
            // __return_true is required here because the endpoint must be publicly reachable
            // from GoHighLevel servers which cannot obtain a WordPress nonce.
            'permission_callback' => '__return_true',
        ] );
    }

    public static function handle_request( WP_REST_Request $request ) {
        // Validate secret before touching the payload.
        $secret   = get_option( self::SECRET_OPTION, '' );
        $provided = $request->get_param( 'secret' ) ?? '';

        if ( empty( $secret ) || ! hash_equals( $secret, $provided ) ) {
            return new WP_REST_Response( [ 'error' => 'Unauthorised' ], 401 );
        }

        $raw = $request->get_body();
        if ( strlen( $raw ) > 1048576 ) { // 1MB cap
            return new WP_REST_Response( [ 'error' => 'Payload too large' ], 413 );
        }

        $payload = json_decode( $raw, true );
        if ( ! is_array( $payload ) ) {
            return new WP_REST_Response( [ 'error' => 'Invalid JSON' ], 400 );
        }

        // Determine event type.
        $event_type = sanitize_text_field(
            $payload['type'] ?? $payload['event'] ?? $payload['eventType'] ?? 'unknown'
        );

        GoodConnect_DB::log( [
            'source'        => 'webhook',
            'form_id'       => $event_type,
            'form_name'     => 'Inbound Webhook',
            'contact_email' => sanitize_email( $payload['email'] ?? $payload['contact']['email'] ?? '' ),
            'action'        => 'received',
            'success'       => 1,
        ] );

        $response_data = GoodConnect_Webhook_Events::dispatch( $event_type, $payload );

        return new WP_REST_Response( $response_data ?: [ 'status' => 'ok' ], 200 );
    }

    public static function get_webhook_url(): string {
        $secret = get_option( self::SECRET_OPTION, '' );
        if ( ! $secret ) {
            $secret = wp_generate_password( 32, false );
            update_option( self::SECRET_OPTION, $secret );
        }
        return add_query_arg(
            'secret',
            $secret,
            rest_url( self::NAMESPACE . self::ROUTE )
        );
    }

    public static function regenerate_secret(): string {
        $secret = wp_generate_password( 32, false );
        update_option( self::SECRET_OPTION, $secret );
        return $secret;
    }
}
