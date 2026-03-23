<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_Tag_Protection {

    const CONTACT_COOKIE  = 'gc_contact_id';
    const CACHE_PREFIX    = 'gc_tags_';
    const CACHE_TTL       = 300; // 5 minutes
    const COOKIE_TTL_OPTION = 'goodconnect_protection_cookie_ttl';
    const DEFAULT_COOKIE_TTL = 2592000; // 30 days

    public static function init() {
        add_action( 'template_redirect', [ __CLASS__, 'check_access' ], 5 );
        add_shortcode( 'goodconnect_protected', [ __CLASS__, 'shortcode_handler' ] );
    }

    /**
     * Set the GHL contact ID cookie after a successful form submission.
     * Called from GF/Elementor submit handlers.
     */
    public static function set_contact_cookie( string $contact_id ): void {
        if ( empty( $contact_id ) ) return;
        $ttl = (int) get_option( self::COOKIE_TTL_OPTION, self::DEFAULT_COOKIE_TTL );
        setcookie(
            self::CONTACT_COOKIE,
            sanitize_text_field( $contact_id ),
            time() + $ttl,
            '/',
            '',
            is_ssl(),
            true // HttpOnly
        );
        // Also set in $_COOKIE so it's available in the same request.
        $_COOKIE[ self::CONTACT_COOKIE ] = $contact_id;
    }

    /**
     * Get the GHL contact ID from the cookie.
     */
    public static function get_contact_id(): string {
        $raw = $_COOKIE[ self::CONTACT_COOKIE ] ?? '';
        // GHL contact IDs are alphanumeric strings.
        return preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', $raw ) ? $raw : '';
    }

    /**
     * Fetch contact tags from GHL, with transient caching.
     */
    public static function get_contact_tags( string $contact_id ): array {
        if ( empty( $contact_id ) ) return [];

        $cache_key = self::CACHE_PREFIX . md5( $contact_id );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return (array) $cached;

        $account = GoodConnect_Settings::get_default_account();
        if ( ! $account ) return [];

        $client = new GoodConnect_GHL_Client( $account );
        $result = $client->get_contact( $contact_id );

        if ( is_wp_error( $result ) ) return [];

        $tags = $result['contact']['tags'] ?? $result['tags'] ?? [];
        set_transient( $cache_key, $tags, self::CACHE_TTL );

        return (array) $tags;
    }

    /**
     * Check if the current visitor has access to the current post/page.
     * Runs on template_redirect.
     */
    public static function check_access(): void {
        if ( ! is_singular() ) return;

        $post_id       = get_queried_object_id();
        $required_tags = self::get_required_tags( $post_id );
        if ( empty( $required_tags ) ) return;

        $contact_id    = self::get_contact_id();
        $contact_tags  = self::get_contact_tags( $contact_id );

        if ( self::has_required_tags( $required_tags, $contact_tags ) ) return;

        // Access denied — determine action.
        $denied_action  = get_post_meta( $post_id, '_gc_denied_action', true ) ?: 'redirect';
        $redirect_page  = (int) get_option( 'goodconnect_protection_denied_page', 0 );
        $custom_message = get_post_meta( $post_id, '_gc_denied_message', true );

        switch ( $denied_action ) {
            case 'redirect':
                $url = $redirect_page ? get_permalink( $redirect_page ) : home_url( '/' );
                wp_redirect( esc_url_raw( $url ) );
                exit;

            case 'message':
                wp_die(
                    wp_kses_post( $custom_message ?: __( 'You do not have access to this content.', 'good-connect' ) ),
                    esc_html__( 'Access Restricted', 'good-connect' ),
                    [ 'response' => 403 ]
                );

            case 'hide':
            default:
                wp_die(
                    esc_html__( 'You do not have access to this content.', 'good-connect' ),
                    esc_html__( 'Access Restricted', 'good-connect' ),
                    [ 'response' => 403 ]
                );
        }
    }

    /**
     * Shortcode: [goodconnect_protected tags="member vip" action="hide"]...[/goodconnect_protected]
     */
    public static function shortcode_handler( $atts, $content = '' ): string {
        $atts = shortcode_atts( [
            'tags'    => '',
            'action'  => 'hide',
            'message' => '',
        ], $atts, 'goodconnect_protected' );

        $required_tags = array_filter( array_map( 'trim', explode( ',', $atts['tags'] ) ) );
        if ( empty( $required_tags ) ) return do_shortcode( $content );

        $contact_id   = self::get_contact_id();
        $contact_tags = self::get_contact_tags( $contact_id );

        if ( self::has_required_tags( $required_tags, $contact_tags ) ) {
            return do_shortcode( $content );
        }

        switch ( $atts['action'] ) {
            case 'message':
                return '<div class="goodconnect-access-denied">' .
                       wp_kses_post( $atts['message'] ?: __( 'This content is restricted.', 'good-connect' ) ) .
                       '</div>';
            case 'hide':
            default:
                return '';
        }
    }

    /**
     * Check if the visitor has all required tags.
     */
    public static function has_required_tags( array $required, array $actual ): bool {
        if ( empty( $required ) ) return true;
        foreach ( $required as $tag ) {
            if ( ! in_array( $tag, $actual, true ) ) return false;
        }
        return true;
    }

    /**
     * Get the required tags for a post.
     */
    public static function get_required_tags( int $post_id ): array {
        $meta = get_post_meta( $post_id, '_gc_required_tags', true );
        if ( ! $meta ) return [];
        return array_filter( array_map( 'trim', explode( ',', $meta ) ) );
    }
}
