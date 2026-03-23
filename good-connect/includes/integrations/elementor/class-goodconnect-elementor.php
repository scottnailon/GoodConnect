<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Elementor Forms → GHL integration.
 * Hooks into elementor_pro/forms/new_record to push contact data to GHL.
 */
class GoodConnect_Elementor {

    public static function init() {
        if ( ! did_action( 'elementor_pro/init' ) && ! class_exists( '\ElementorPro\Plugin' ) ) {
            add_action( 'elementor_pro/init', [ __CLASS__, 'register_hooks' ] );
            return;
        }
        self::register_hooks();
    }

    public static function register_hooks() {
        add_action( 'elementor_pro/forms/new_record', [ __CLASS__, 'handle_submission' ], 10, 2 );
    }

    /**
     * Called after an Elementor Pro form submission.
     *
     * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record
     * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $handler
     */
    public static function handle_submission( $record, $handler ) {
        $form_name = $record->get_form_settings( 'form_name' );
        $mapping   = self::get_field_mapping( $form_name );

        if ( empty( $mapping ) ) {
            return;
        }

        $raw_fields = $record->get( 'fields' );
        $contact    = [];

        foreach ( $mapping as $ghl_field => $elementor_field_id ) {
            $value = $raw_fields[ $elementor_field_id ]['value'] ?? '';
            if ( ! empty( $value ) ) {
                $contact[ $ghl_field ] = sanitize_text_field( $value );
            }
        }

        if ( empty( $contact ) ) {
            return;
        }

        $client = new GoodConnect_GHL_Client();
        $result = $client->upsert_contact( $contact );

        if ( is_wp_error( $result ) ) {
            error_log( '[GoodConnect] Elementor submission error: ' . $result->get_error_message() );
        }
    }

    /**
     * Get saved field mapping for an Elementor form (keyed by form name).
     *
     * @param string $form_name
     * @return array
     */
    public static function get_field_mapping( string $form_name ): array {
        $all_mappings = get_option( 'goodconnect_elementor_mappings', [] );
        return $all_mappings[ $form_name ] ?? [];
    }

    /**
     * Save field mapping for an Elementor form.
     *
     * @param string $form_name
     * @param array  $mapping
     */
    public static function save_field_mapping( string $form_name, array $mapping ): void {
        $all_mappings = get_option( 'goodconnect_elementor_mappings', [] );
        $all_mappings[ $form_name ] = $mapping;
        update_option( 'goodconnect_elementor_mappings', $all_mappings );
    }
}
