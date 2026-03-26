<?php
/**
 * Plugin Name:       GoodConnect
 * Plugin URI:        https://goodhost.com.au
 * Description:       GoHighLevel integration for WordPress — Gravity Forms, Elementor, Contact Form 7, and WooCommerce.
 * Version:           1.2.3
 * Author:            GoodHost
 * Author URI:        https://goodhost.com.au
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       good-connect
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Tested up to:      6.7
 * Requires PHP:      8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'GOODCONNECT_VERSION', '1.2.3' );
define( 'GOODCONNECT_PLUGIN_FILE', __FILE__ );
define( 'GOODCONNECT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GOODCONNECT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Core
require_once GOODCONNECT_PLUGIN_DIR . 'includes/core/class-goodconnect-db.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/core/class-goodconnect-settings.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/core/class-goodconnect-conditions.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/core/class-goodconnect-loader.php';

// GHL API client
require_once GOODCONNECT_PLUGIN_DIR . 'includes/ghl/class-goodconnect-ghl-client.php';

// Admin
require_once GOODCONNECT_PLUGIN_DIR . 'includes/admin/class-goodconnect-log-table.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/admin/class-goodconnect-admin.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/admin/class-goodconnect-bulk-sync.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/admin/class-goodconnect-webhook-admin.php';

// Integrations
require_once GOODCONNECT_PLUGIN_DIR . 'includes/integrations/gravity-forms/class-goodconnect-gf.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/integrations/elementor/class-goodconnect-elementor.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/integrations/woocommerce/class-goodconnect-woo.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/integrations/contact-form-7/class-goodconnect-cf7.php';

// Webhooks
require_once GOODCONNECT_PLUGIN_DIR . 'includes/webhook/class-goodconnect-webhook-receiver.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/webhook/class-goodconnect-webhook-events.php';

// Magic links & user provisioning
require_once GOODCONNECT_PLUGIN_DIR . 'includes/magic-link/class-goodconnect-magic-link.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/user-provisioning/class-goodconnect-user-provision.php';

// Content protection
require_once GOODCONNECT_PLUGIN_DIR . 'includes/protection/class-goodconnect-tag-protection.php';
require_once GOODCONNECT_PLUGIN_DIR . 'includes/protection/class-goodconnect-protection-meta.php';

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
