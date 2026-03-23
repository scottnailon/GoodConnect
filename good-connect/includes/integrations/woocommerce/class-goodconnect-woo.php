<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_Woo {

    public static function init() {
        if ( ! class_exists( 'WooCommerce' ) ) return;
        add_action( 'woocommerce_order_status_changed', [ __CLASS__, 'handle_status_change' ], 10, 4 );
    }

    public static function handle_status_change( $order_id, $from, $to, $order ) {
        if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) return;
        if ( ! GoodConnect_Settings::get( 'woo_enabled' ) ) return;

        $triggers = GoodConnect_Settings::get( 'woo_trigger_statuses', ['processing'] );
        if ( ! in_array( $to, (array) $triggers, true ) ) return;

        // Dedup guard — skip if already synced for this status.
        $meta_key = '_goodconnect_synced_' . sanitize_key( $to );
        if ( get_post_meta( $order_id, $meta_key, true ) ) return;

        $account_id = GoodConnect_Settings::get( 'woo_account_id' );
        $account    = $account_id
            ? GoodConnect_Settings::get_account_by_id( $account_id )
            : GoodConnect_Settings::get_default_account();
        if ( ! $account ) return;

        // Per-product tags.
        $product_tags    = [];
        $all_product_cfg = get_option( 'goodconnect_woo_product_tags', [] );
        foreach ( $order->get_items() as $item ) {
            $pid = $item->get_product_id();
            if ( ! empty( $all_product_cfg[ $pid ] ) ) {
                $product_tags = array_merge( $product_tags, (array) $all_product_cfg[ $pid ] );
            }
        }

        $tags = array_values( array_unique( array_filter( array_merge(
            [ 'woocommerce-customer', 'woo-' . $to ],
            $product_tags
        ) ) ) );

        $contact = array_filter( [
            'firstName'  => $order->get_billing_first_name(),
            'lastName'   => $order->get_billing_last_name(),
            'email'      => $order->get_billing_email(),
            'phone'      => $order->get_billing_phone(),
            'address1'   => $order->get_billing_address_1(),
            'city'       => $order->get_billing_city(),
            'state'      => $order->get_billing_state(),
            'postalCode' => $order->get_billing_postcode(),
            'country'    => $order->get_billing_country(),
            'source'     => 'WooCommerce',
            'tags'       => $tags,
        ] );

        $client = new GoodConnect_GHL_Client( $account );
        $result = $client->upsert_contact( $contact );
        $success = ! is_wp_error( $result );

        if ( $success ) {
            update_post_meta( $order_id, $meta_key, current_time( 'mysql' ) );
        }

        GoodConnect_DB::log( [
            'source'         => 'woocommerce',
            'form_id'        => (string) $order_id,
            'form_name'      => 'WooCommerce Order #' . $order->get_order_number() . ' (' . $to . ')',
            'account_id'     => $account['id'] ?? '',
            'contact_email'  => $order->get_billing_email(),
            'action'         => 'upsert_contact',
            'success'        => $success,
            'ghl_contact_id' => $result['contact']['id'] ?? '',
            'error_message'  => $success ? '' : $result->get_error_message(),
        ] );

        if ( ! $success ) {
            error_log( '[GoodConnect] WooCommerce error (order ' . $order_id . ', status ' . $to . '): ' . $result->get_error_message() );
        }
    }
}
