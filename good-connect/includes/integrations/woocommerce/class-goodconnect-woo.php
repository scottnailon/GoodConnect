<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WooCommerce → GHL integration.
 * Pushes customer data to GHL as a contact when an order is placed.
 */
class GoodConnect_Woo {

    public static function init() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }
        add_action( 'woocommerce_checkout_order_created', [ __CLASS__, 'handle_order' ], 10, 1 );
    }

    /**
     * Called when a new WooCommerce order is created.
     *
     * @param \WC_Order $order
     */
    public static function handle_order( $order ) {
        if ( ! GoodConnect_Settings::get( 'woo_enabled' ) ) {
            return;
        }

        $contact = [
            'firstName' => $order->get_billing_first_name(),
            'lastName'  => $order->get_billing_last_name(),
            'email'     => $order->get_billing_email(),
            'phone'     => $order->get_billing_phone(),
            'address1'  => $order->get_billing_address_1(),
            'city'      => $order->get_billing_city(),
            'state'     => $order->get_billing_state(),
            'postalCode'=> $order->get_billing_postcode(),
            'country'   => $order->get_billing_country(),
            'source'    => 'WooCommerce',
            'tags'      => [ 'woocommerce-customer' ],
        ];

        // Remove empty values.
        $contact = array_filter( $contact );

        $client = new GoodConnect_GHL_Client();
        $result = $client->upsert_contact( $contact );

        if ( is_wp_error( $result ) ) {
            error_log( '[GoodConnect] WooCommerce order error: ' . $result->get_error_message() );
        }
    }
}
