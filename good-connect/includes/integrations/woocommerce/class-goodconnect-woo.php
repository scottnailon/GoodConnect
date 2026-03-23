<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_Woo {

    public static function init() {
        if ( ! class_exists( 'WooCommerce' ) ) return;
        add_action( 'woocommerce_checkout_order_created', [ __CLASS__, 'handle_order' ], 10, 1 );
    }

    public static function handle_order( $order ) {
        if ( ! GoodConnect_Settings::get( 'woo_enabled' ) ) return;

        $account_id = GoodConnect_Settings::get( 'woo_account_id' );
        $account    = $account_id
            ? GoodConnect_Settings::get_account_by_id( $account_id )
            : GoodConnect_Settings::get_default_account();
        if ( ! $account ) return;

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
            'tags'       => [ 'woocommerce-customer' ],
        ] );

        $client = new GoodConnect_GHL_Client( $account );
        $result = $client->upsert_contact( $contact );

        $success = ! is_wp_error( $result );
        GoodConnect_DB::log( [
            'source'         => 'woocommerce',
            'form_id'        => (string) $order->get_id(),
            'form_name'      => 'WooCommerce Order #' . $order->get_order_number(),
            'account_id'     => $account['id'] ?? '',
            'contact_email'  => $order->get_billing_email(),
            'action'         => 'upsert_contact',
            'success'        => $success,
            'ghl_contact_id' => $result['contact']['id'] ?? '',
            'error_message'  => $success ? '' : $result->get_error_message(),
        ] );

        if ( ! $success ) {
            error_log( '[GoodConnect] WooCommerce order error (order ' . $order->get_id() . '): ' . $result->get_error_message() );
        }
    }
}
