<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_Settings {

    const OPTION_KEY = 'goodconnect_settings';

    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
    }

    public static function register_settings() {
        register_setting( 'goodconnect', self::OPTION_KEY, [ __CLASS__, 'sanitize' ] );
    }

    public static function get( $key, $default = '' ) {
        $options = get_option( self::OPTION_KEY, [] );
        return isset( $options[ $key ] ) ? $options[ $key ] : $default;
    }

    public static function sanitize( $input ) {
        $clean = [];
        $clean['api_key']     = sanitize_text_field( $input['api_key'] ?? '' );
        $clean['location_id'] = sanitize_text_field( $input['location_id'] ?? '' );
        return $clean;
    }
}
