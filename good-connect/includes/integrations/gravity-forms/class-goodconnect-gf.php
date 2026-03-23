<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gravity Forms → GHL integration.
 * Hooks into gform_after_submission to push contact data to GHL.
 */
class GoodConnect_GF {

    public static function init() {
        if ( ! class_exists( 'GFForms' ) ) {
            return;
        }
        add_action( 'gform_after_submission', [ __CLASS__, 'handle_submission' ], 10, 2 );
    }

    /**
     * Called after a Gravity Forms submission.
     *
     * @param array $entry  GF entry object.
     * @param array $form   GF form object.
     */
    public static function handle_submission( $entry, $form ) {
        $mapping = self::get_field_mapping( $form['id'] );
        if ( empty( $mapping ) ) {
            return;
        }

        $contact = [];
        foreach ( $mapping as $ghl_field => $gf_field_id ) {
            $value = rgar( $entry, $gf_field_id );
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
            error_log( '[GoodConnect] GF submission error: ' . $result->get_error_message() );
        }
    }

    /**
     * Get the saved field mapping for a form.
     * Returns an array like: [ 'email' => '1', 'firstName' => '2', ... ]
     *
     * @param int $form_id
     * @return array
     */
    public static function get_field_mapping( int $form_id ): array {
        $all_mappings = get_option( 'goodconnect_gf_mappings', [] );
        return $all_mappings[ $form_id ] ?? [];
    }

    /**
     * Save the field mapping for a form.
     *
     * @param int   $form_id
     * @param array $mapping
     */
    public static function save_field_mapping( int $form_id, array $mapping ): void {
        $all_mappings = get_option( 'goodconnect_gf_mappings', [] );
        $all_mappings[ $form_id ] = $mapping;
        update_option( 'goodconnect_gf_mappings', $all_mappings );
    }
}
