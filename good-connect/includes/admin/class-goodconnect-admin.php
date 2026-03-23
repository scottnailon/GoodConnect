<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
    }

    public static function register_menu() {
        add_options_page(
            __( 'GoodConnect', 'good-connect' ),
            __( 'GoodConnect', 'good-connect' ),
            'manage_options',
            'good-connect',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    public static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'GoodConnect — GoHighLevel Integration', 'good-connect' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'goodconnect' );
                $options = get_option( GoodConnect_Settings::OPTION_KEY, [] );
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="gc_api_key"><?php esc_html_e( 'GHL API Key', 'good-connect' ); ?></label></th>
                        <td>
                            <input type="password" id="gc_api_key" name="<?php echo esc_attr( GoodConnect_Settings::OPTION_KEY ); ?>[api_key]"
                                value="<?php echo esc_attr( $options['api_key'] ?? '' ); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'Your GoHighLevel private integration API key.', 'good-connect' ); ?> <a href="https://goodhost.com.au/docs/goodconnect" target="_blank"><?php esc_html_e( 'Get help', 'good-connect' ); ?></a></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gc_location_id"><?php esc_html_e( 'Location ID', 'good-connect' ); ?></label></th>
                        <td>
                            <input type="text" id="gc_location_id" name="<?php echo esc_attr( GoodConnect_Settings::OPTION_KEY ); ?>[location_id]"
                                value="<?php echo esc_attr( $options['location_id'] ?? '' ); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'The GHL Location (sub-account) ID to send data to.', 'good-connect' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
