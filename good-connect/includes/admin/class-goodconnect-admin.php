<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_ajax_goodconnect_save_gf_mapping', [ __CLASS__, 'ajax_save_gf_mapping' ] );
        add_action( 'wp_ajax_goodconnect_save_elementor_mapping', [ __CLASS__, 'ajax_save_elementor_mapping' ] );
        add_action( 'wp_ajax_goodconnect_save_woo_settings', [ __CLASS__, 'ajax_save_woo_settings' ] );
    }

    public static function register_menu() {
        add_menu_page(
            __( 'GoodConnect', 'good-connect' ),
            __( 'GoodConnect', 'good-connect' ),
            'manage_options',
            'good-connect',
            [ __CLASS__, 'render_page' ],
            'dashicons-networking',
            81
        );
    }

    public static function enqueue_assets( $hook ) {
        if ( $hook !== 'toplevel_page_good-connect' ) {
            return;
        }
        wp_enqueue_style(
            'goodconnect-admin',
            GOODCONNECT_PLUGIN_URL . 'assets/css/admin.css',
            [],
            GOODCONNECT_VERSION
        );
        wp_enqueue_script(
            'goodconnect-admin',
            GOODCONNECT_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            GOODCONNECT_VERSION,
            true
        );
        wp_localize_script( 'goodconnect-admin', 'GoodConnect', [
            'nonce'   => wp_create_nonce( 'goodconnect_admin' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'strings' => [
                'saved'   => __( 'Saved!', 'good-connect' ),
                'saving'  => __( 'Saving…', 'good-connect' ),
                'error'   => __( 'Error saving. Please try again.', 'good-connect' ),
            ],
        ] );
    }

    // -------------------------------------------------------------------------
    // Page render
    // -------------------------------------------------------------------------

    public static function render_page() {
        $active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
        $tabs = [
            'settings'     => __( 'Settings', 'good-connect' ),
            'gravity-forms'=> __( 'Gravity Forms', 'good-connect' ),
            'elementor'    => __( 'Elementor', 'good-connect' ),
            'woocommerce'  => __( 'WooCommerce', 'good-connect' ),
        ];
        ?>
        <div class="wrap goodconnect-wrap">
            <h1 class="goodconnect-title">
                <span class="goodconnect-logo">&#9654;</span>
                <?php esc_html_e( 'GoodConnect', 'good-connect' ); ?>
                <span class="goodconnect-tagline"><?php esc_html_e( 'GoHighLevel Integration', 'good-connect' ); ?></span>
            </h1>

            <nav class="nav-tab-wrapper goodconnect-tabs">
                <?php foreach ( $tabs as $slug => $label ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=good-connect&tab=' . $slug ) ); ?>"
                       class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="goodconnect-tab-content">
                <?php
                switch ( $active_tab ) {
                    case 'gravity-forms':
                        self::render_gf_tab();
                        break;
                    case 'elementor':
                        self::render_elementor_tab();
                        break;
                    case 'woocommerce':
                        self::render_woo_tab();
                        break;
                    default:
                        self::render_settings_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Settings tab
    // -------------------------------------------------------------------------

    private static function render_settings_tab() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'goodconnect' );
            $options = get_option( GoodConnect_Settings::OPTION_KEY, [] );
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="gc_api_key"><?php esc_html_e( 'GHL API Key', 'good-connect' ); ?></label></th>
                    <td>
                        <input type="password" id="gc_api_key"
                               name="<?php echo esc_attr( GoodConnect_Settings::OPTION_KEY ); ?>[api_key]"
                               value="<?php echo esc_attr( $options['api_key'] ?? '' ); ?>"
                               class="regular-text" autocomplete="new-password" />
                        <p class="description">
                            <?php esc_html_e( 'Your GoHighLevel private integration API key.', 'good-connect' ); ?>
                            <a href="https://goodhost.com.au/docs/goodconnect" target="_blank"><?php esc_html_e( 'Get help', 'good-connect' ); ?></a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gc_location_id"><?php esc_html_e( 'Location ID', 'good-connect' ); ?></label></th>
                    <td>
                        <input type="text" id="gc_location_id"
                               name="<?php echo esc_attr( GoodConnect_Settings::OPTION_KEY ); ?>[location_id]"
                               value="<?php echo esc_attr( $options['location_id'] ?? '' ); ?>"
                               class="regular-text" />
                        <p class="description"><?php esc_html_e( 'The GHL Location (sub-account) ID to send data to.', 'good-connect' ); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    // -------------------------------------------------------------------------
    // Gravity Forms tab
    // -------------------------------------------------------------------------

    private static function render_gf_tab() {
        if ( ! class_exists( 'GFForms' ) ) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Gravity Forms is not active.', 'good-connect' ) . '</p></div>';
            return;
        }

        $forms = GFFormsModel::get_forms( true ); // active forms only
        if ( empty( $forms ) ) {
            echo '<p>' . esc_html__( 'No Gravity Forms found.', 'good-connect' ) . '</p>';
            return;
        }

        $ghl_fields = self::ghl_contact_fields();

        foreach ( $forms as $form ) {
            $form_data = GFAPI::get_form( $form->id );
            $mapping   = GoodConnect_GF::get_field_mapping( (int) $form->id );
            ?>
            <div class="goodconnect-card" data-form-id="<?php echo esc_attr( $form->id ); ?>">
                <h3 class="goodconnect-card-title">
                    <?php echo esc_html( $form->title ); ?>
                    <span class="goodconnect-form-id"><?php echo esc_html( sprintf( __( 'Form ID: %d', 'good-connect' ), $form->id ) ); ?></span>
                </h3>

                <div class="goodconnect-mapper">
                    <div class="goodconnect-mapper-header">
                        <span><?php esc_html_e( 'GHL Field', 'good-connect' ); ?></span>
                        <span><?php esc_html_e( 'Gravity Forms Field', 'good-connect' ); ?></span>
                    </div>
                    <?php foreach ( $ghl_fields as $ghl_key => $ghl_label ) :
                        $selected_gf_field = $mapping[ $ghl_key ] ?? '';
                        ?>
                        <div class="goodconnect-mapper-row">
                            <label><?php echo esc_html( $ghl_label ); ?></label>
                            <select class="goodconnect-gf-field-select"
                                    data-ghl-field="<?php echo esc_attr( $ghl_key ); ?>">
                                <option value=""><?php esc_html_e( '— Not mapped —', 'good-connect' ); ?></option>
                                <?php foreach ( $form_data['fields'] as $field ) :
                                    $field_id    = $field->id;
                                    $field_label = $field->label ?: sprintf( __( 'Field %s', 'good-connect' ), $field_id );
                                    ?>
                                    <option value="<?php echo esc_attr( $field_id ); ?>"
                                        <?php selected( (string) $selected_gf_field, (string) $field_id ); ?>>
                                        <?php echo esc_html( $field_label . ' (ID: ' . $field_id . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="goodconnect-card-footer">
                    <button type="button" class="button button-primary goodconnect-save-gf"
                            data-form-id="<?php echo esc_attr( $form->id ); ?>">
                        <?php esc_html_e( 'Save Mapping', 'good-connect' ); ?>
                    </button>
                    <span class="goodconnect-save-status"></span>
                </div>
            </div>
            <?php
        }
    }

    // -------------------------------------------------------------------------
    // Elementor tab
    // -------------------------------------------------------------------------

    private static function render_elementor_tab() {
        $elementor_active = did_action( 'elementor_pro/init' ) || class_exists( '\ElementorPro\Plugin' );

        if ( ! $elementor_active ) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Elementor Pro is not active.', 'good-connect' ) . '</p></div>';
            return;
        }

        $all_mappings = get_option( 'goodconnect_elementor_mappings', [] );
        $ghl_fields   = self::ghl_contact_fields();
        ?>
        <p class="description">
            <?php esc_html_e( 'Enter the Elementor form name and map each field ID (as set in the Elementor form widget) to a GHL contact field.', 'good-connect' ); ?>
        </p>

        <div id="goodconnect-elementor-forms">
            <?php foreach ( $all_mappings as $form_name => $mapping ) :
                self::render_elementor_form_card( $form_name, $mapping, $ghl_fields );
            endforeach; ?>
        </div>

        <button type="button" class="button goodconnect-add-elementor-form" style="margin-top:12px;">
            + <?php esc_html_e( 'Add Form', 'good-connect' ); ?>
        </button>

        <template id="goodconnect-elementor-card-template">
            <?php self::render_elementor_form_card( '', [], $ghl_fields, true ); ?>
        </template>
        <?php
    }

    private static function render_elementor_form_card( string $form_name, array $mapping, array $ghl_fields, bool $is_template = false ) {
        ?>
        <div class="goodconnect-card goodconnect-elementor-card">
            <div class="goodconnect-card-title-row">
                <input type="text"
                       class="goodconnect-elementor-form-name regular-text"
                       placeholder="<?php esc_attr_e( 'Form name (must match exactly)', 'good-connect' ); ?>"
                       value="<?php echo esc_attr( $form_name ); ?>" />
                <button type="button" class="button goodconnect-remove-elementor-form"><?php esc_html_e( 'Remove', 'good-connect' ); ?></button>
            </div>

            <div class="goodconnect-mapper">
                <div class="goodconnect-mapper-header">
                    <span><?php esc_html_e( 'GHL Field', 'good-connect' ); ?></span>
                    <span><?php esc_html_e( 'Elementor Field ID', 'good-connect' ); ?></span>
                </div>
                <?php foreach ( $ghl_fields as $ghl_key => $ghl_label ) :
                    $value = $mapping[ $ghl_key ] ?? '';
                    ?>
                    <div class="goodconnect-mapper-row">
                        <label><?php echo esc_html( $ghl_label ); ?></label>
                        <input type="text"
                               class="goodconnect-elementor-field-id"
                               data-ghl-field="<?php echo esc_attr( $ghl_key ); ?>"
                               placeholder="<?php esc_attr_e( 'e.g. email', 'good-connect' ); ?>"
                               value="<?php echo esc_attr( $value ); ?>" />
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="goodconnect-card-footer">
                <button type="button" class="button button-primary goodconnect-save-elementor">
                    <?php esc_html_e( 'Save Mapping', 'good-connect' ); ?>
                </button>
                <span class="goodconnect-save-status"></span>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // WooCommerce tab
    // -------------------------------------------------------------------------

    private static function render_woo_tab() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'WooCommerce is not active.', 'good-connect' ) . '</p></div>';
            return;
        }

        $enabled = GoodConnect_Settings::get( 'woo_enabled' );
        ?>
        <div class="goodconnect-card">
            <h3 class="goodconnect-card-title"><?php esc_html_e( 'Order → GHL Contact', 'good-connect' ); ?></h3>
            <p class="description">
                <?php esc_html_e( 'When enabled, a GHL contact will be created or updated every time a new WooCommerce order is placed. The following fields are sent automatically:', 'good-connect' ); ?>
            </p>
            <ul class="goodconnect-field-list">
                <li><strong>firstName</strong>, <strong>lastName</strong>, <strong>email</strong>, <strong>phone</strong></li>
                <li><strong>address1</strong>, <strong>city</strong>, <strong>state</strong>, <strong>postalCode</strong>, <strong>country</strong></li>
                <li><?php esc_html_e( 'Tag:', 'good-connect' ); ?> <code>woocommerce-customer</code></li>
            </ul>

            <label class="goodconnect-toggle">
                <input type="checkbox" id="gc_woo_enabled" <?php checked( $enabled, '1' ); ?> value="1" />
                <?php esc_html_e( 'Enable WooCommerce → GHL sync', 'good-connect' ); ?>
            </label>

            <div class="goodconnect-card-footer">
                <button type="button" class="button button-primary" id="goodconnect-save-woo">
                    <?php esc_html_e( 'Save', 'good-connect' ); ?>
                </button>
                <span class="goodconnect-save-status"></span>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    public static function ajax_save_gf_mapping() {
        check_ajax_referer( 'goodconnect_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorised', 403 );
        }

        $form_id = absint( $_POST['form_id'] ?? 0 );
        $mapping = $_POST['mapping'] ?? [];

        if ( ! $form_id ) {
            wp_send_json_error( 'Invalid form ID' );
        }

        // Sanitize: both keys (GHL fields) and values (GF field IDs) are plain strings.
        $clean = [];
        foreach ( (array) $mapping as $ghl_field => $gf_field_id ) {
            $ghl_field  = sanitize_key( $ghl_field );
            $gf_field_id = sanitize_text_field( $gf_field_id );
            if ( $ghl_field && $gf_field_id !== '' ) {
                $clean[ $ghl_field ] = $gf_field_id;
            }
        }

        GoodConnect_GF::save_field_mapping( $form_id, $clean );
        wp_send_json_success();
    }

    public static function ajax_save_elementor_mapping() {
        check_ajax_referer( 'goodconnect_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorised', 403 );
        }

        $form_name = sanitize_text_field( $_POST['form_name'] ?? '' );
        $mapping   = $_POST['mapping'] ?? [];

        if ( ! $form_name ) {
            wp_send_json_error( 'Invalid form name' );
        }

        $clean = [];
        foreach ( (array) $mapping as $ghl_field => $elementor_field_id ) {
            $ghl_field        = sanitize_key( $ghl_field );
            $elementor_field_id = sanitize_text_field( $elementor_field_id );
            if ( $ghl_field && $elementor_field_id !== '' ) {
                $clean[ $ghl_field ] = $elementor_field_id;
            }
        }

        GoodConnect_Elementor::save_field_mapping( $form_name, $clean );
        wp_send_json_success();
    }

    public static function ajax_save_woo_settings() {
        check_ajax_referer( 'goodconnect_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorised', 403 );
        }

        $enabled = ! empty( $_POST['woo_enabled'] ) ? '1' : '0';

        $options              = get_option( GoodConnect_Settings::OPTION_KEY, [] );
        $options['woo_enabled'] = $enabled;
        update_option( GoodConnect_Settings::OPTION_KEY, $options );

        wp_send_json_success();
    }

    // -------------------------------------------------------------------------
    // GHL contact field registry
    // -------------------------------------------------------------------------

    public static function ghl_contact_fields(): array {
        return [
            'firstName'  => __( 'First Name', 'good-connect' ),
            'lastName'   => __( 'Last Name', 'good-connect' ),
            'email'      => __( 'Email', 'good-connect' ),
            'phone'      => __( 'Phone', 'good-connect' ),
            'address1'   => __( 'Address', 'good-connect' ),
            'city'       => __( 'City', 'good-connect' ),
            'state'      => __( 'State', 'good-connect' ),
            'postalCode' => __( 'Postal Code', 'good-connect' ),
            'country'    => __( 'Country', 'good-connect' ),
            'companyName'=> __( 'Company Name', 'good-connect' ),
            'website'    => __( 'Website', 'good-connect' ),
            'source'     => __( 'Source', 'good-connect' ),
        ];
    }
}
