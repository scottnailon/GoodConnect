<?php
/**
 * Plugin Name: GoodConnect
 * Plugin URI:  https://goodhost.com.au
 * Description: GoHighLevel integration for Gravity Forms, Elementor, and WooCommerce.
 * Version:     1.1.0
 * Author:      GoodHost
 * Author URI:  https://goodhost.com.au
 * License:     GPL-2.0+
 * Text Domain: good-connect
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'GOODCONNECT_VERSION', '1.1.0' );
define( 'GOODCONNECT_PLUGIN_FILE', __FILE__ );
define( 'GOODCONNECT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GOODCONNECT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once GOODCONNECT_PLUGIN_DIR . 'includes/core/class-goodconnect-db.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/core/class-goodconnect-loader.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/core/class-goodconnect-settings.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/ghl/class-goodconnect-ghl-client.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/admin/class-goodconnect-log-table.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/admin/class-goodconnect-admin.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/integrations/gravity-forms/class-goodconnect-gf.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/integrations/elementor/class-goodconnect-elementor.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/integrations/woocommerce/class-goodconnect-woo.php';

function goodconnect_init() {
    load_plugin_textdomain( 'good-connect', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    $loader = new GoodConnect_Loader();
    $loader->run();
}
add_action( 'plugins_loaded', 'goodconnect_init' );

register_activation_hook( __FILE__, 'goodconnect_activate' );
function goodconnect_activate() {
    GoodConnect_DB::install();
}
