<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Jobber OAuth2 authorisation flow handler.
 *
 * Flow:
 *   1. Admin clicks "Connect to Jobber" on the settings tab.
 *      Browser is sent to Jobber's authorize URL with state = nonce|account_id.
 *   2. Jobber redirects back to wp-admin?page=good-connect&code=...&state=...
 *   3. We exchange code for access+refresh tokens and store them on the account row.
 *
 * Note: Jobber strips custom query params from the redirect URI, so callback
 * detection uses page + code/error presence instead of a marker param.
 */
class GoodConnect_Jobber_OAuth {

    const AUTHORIZE_URL = 'https://api.getjobber.com/api/oauth/authorize';
    const TOKEN_URL     = 'https://api.getjobber.com/api/oauth/token';
    const SCOPE         = 'read_clients write_clients read_requests write_requests';
    const STATE_OPTION  = 'goodconnect_jobber_oauth_state';

    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'maybe_handle_callback' ] );
        add_action( 'admin_post_goodconnect_jobber_connect', [ __CLASS__, 'handle_connect' ] );
    }

    public static function redirect_uri(): string {
        return admin_url( 'admin.php?page=good-connect' );
    }

    /**
     * Handle "Connect to Jobber" button. Redirects the admin to Jobber's authorize endpoint.
     */
    public static function handle_connect() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorised', 403 );
        check_admin_referer( 'goodconnect_jobber_connect' );

        $account_id = sanitize_text_field( wp_unslash( $_GET['account_id'] ?? '' ) );
        $account    = $account_id ? GoodConnect_Settings::get_account_by_id( $account_id ) : null;

        if ( ! $account || ( $account['provider'] ?? 'ghl' ) !== 'jobber' ) {
            wp_die( esc_html__( 'Invalid Jobber account.', 'good-connect' ), '', [ 'response' => 400 ] );
        }
        if ( empty( $account['jobber_client_id'] ) || empty( $account['jobber_client_secret'] ) ) {
            wp_die( esc_html__( 'Save the Jobber Client ID and Client Secret before connecting.', 'good-connect' ), '', [ 'response' => 400 ] );
        }

        $state = wp_generate_password( 32, false );
        set_transient( self::STATE_OPTION . '_' . $state, $account_id, 15 * MINUTE_IN_SECONDS );

        $authorize = add_query_arg( [
            'client_id'     => $account['jobber_client_id'],
            'redirect_uri'  => self::redirect_uri(),
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'state'         => $state,
        ], self::AUTHORIZE_URL );

        wp_redirect( $authorize );
        exit;
    }

    /**
     * Intercept the Jobber OAuth callback.
     *
     * Jobber strips custom query params from the redirect URI and only appends
     * code + state. Detect the callback by checking for those params on the
     * good-connect admin page.
     */
    public static function maybe_handle_callback() {
        $page = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );
        if ( $page !== 'good-connect' || ( empty( $_GET['code'] ) && empty( $_GET['error'] ) ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) return;

        $state = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) );
        $code  = sanitize_text_field( wp_unslash( $_GET['code']  ?? '' ) );
        $err   = sanitize_text_field( wp_unslash( $_GET['error'] ?? '' ) );

        if ( $err ) {
            self::redirect_with_notice( 'error', sprintf(
                /* translators: %s is the error code returned by Jobber */
                __( 'Jobber returned an error: %s', 'good-connect' ),
                $err
            ) );
        }

        if ( ! $state || ! $code ) {
            self::redirect_with_notice( 'error', __( 'Missing code or state in Jobber callback.', 'good-connect' ) );
        }

        $transient_key = self::STATE_OPTION . '_' . $state;
        $account_id    = get_transient( $transient_key );
        delete_transient( $transient_key );

        if ( ! $account_id ) {
            self::redirect_with_notice( 'error', __( 'Jobber OAuth state expired. Try connecting again.', 'good-connect' ) );
        }

        $account = GoodConnect_Settings::get_account_by_id( $account_id );
        if ( ! $account ) {
            self::redirect_with_notice( 'error', __( 'Jobber account no longer exists.', 'good-connect' ) );
        }

        $response = wp_remote_post( self::TOKEN_URL, [
            'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
            'body'    => http_build_query( [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => self::redirect_uri(),
                'client_id'     => $account['jobber_client_id']     ?? '',
                'client_secret' => $account['jobber_client_secret'] ?? '',
            ] ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            self::redirect_with_notice( 'error', $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $code_http = wp_remote_retrieve_response_code( $response );

        if ( $code_http >= 400 || empty( $body['access_token'] ) ) {
            $msg = $body['error_description'] ?? ( 'Jobber token exchange failed (' . $code_http . ')' );
            self::redirect_with_notice( 'error', $msg );
        }

        GoodConnect_Settings::update_account( $account_id, [
            'jobber_access_token'  => $body['access_token'],
            'jobber_refresh_token' => $body['refresh_token']  ?? '',
            'jobber_token_expires' => time() + (int) ( $body['expires_in'] ?? 3600 ),
        ] );

        self::redirect_with_notice( 'success', __( 'Jobber account connected.', 'good-connect' ) );
    }

    /**
     * Redirect back to the settings tab with a notice query arg, then exit.
     *
     * @param string $type 'success' or 'error'
     * @param string $message
     */
    private static function redirect_with_notice( string $type, string $message ): void {
        $url = add_query_arg( [
            'page'                => 'good-connect',
            'tab'                 => 'settings',
            'gc_jobber_notice'    => $type,
            'gc_jobber_msg'       => rawurlencode( $message ),
        ], admin_url( 'admin.php' ) );
        wp_safe_redirect( $url );
        exit;
    }
}
