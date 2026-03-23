<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_ajax_goodconnect_save_gf_mapping', [ __CLASS__, 'ajax_save_gf_mapping' ] );
        add_action( 'wp_ajax_goodconnect_save_elementor_mapping', [ __CLASS__, 'ajax_save_elementor_mapping' ] );
        add_action( 'wp_ajax_goodconnect_save_woo_settings', [ __CLASS__, 'ajax_save_woo_settings' ] );
        add_action( 'wp_ajax_goodconnect_get_gf_form', [ __CLASS__, 'ajax_get_gf_form' ] );
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
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'good-connect' ) );
        }

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
                            <?php esc_html_e( 'Your GoHighLevel Private Integration API key.', 'good-connect' ); ?><br><br>
                            <strong><?php esc_html_e( 'How to create your API key:', 'good-connect' ); ?></strong>
                            <ol style="margin:6px 0 0 18px;line-height:1.9">
                                <li><?php esc_html_e( 'In GoHighLevel, go to', 'good-connect' ); ?> <strong><?php esc_html_e( 'Settings → Integrations → API Keys', 'good-connect' ); ?></strong></li>
                                <li><?php esc_html_e( 'Click', 'good-connect' ); ?> <strong><?php esc_html_e( '+ Add Key', 'good-connect' ); ?></strong> <?php esc_html_e( 'and choose', 'good-connect' ); ?> <strong><?php esc_html_e( 'Private Integration Key', 'good-connect' ); ?></strong></li>
                                <li><?php esc_html_e( 'Under Scopes, enable the following:', 'good-connect' ); ?>
                                    <ul style="margin:4px 0 4px 18px;list-style:disc">
                                        <li><code>contacts.read</code> &mdash; <?php esc_html_e( 'look up existing contacts', 'good-connect' ); ?></li>
                                        <li><code>contacts.write</code> &mdash; <?php esc_html_e( 'create and update contacts', 'good-connect' ); ?></li>
                                        <li><code>opportunities.read</code> &mdash; <?php esc_html_e( 'look up opportunities (optional)', 'good-connect' ); ?></li>
                                        <li><code>opportunities.write</code> &mdash; <?php esc_html_e( 'create opportunities (optional)', 'good-connect' ); ?></li>
                                    </ul>
                                </li>
                                <li><?php esc_html_e( 'Click', 'good-connect' ); ?> <strong><?php esc_html_e( 'Save', 'good-connect' ); ?></strong> <?php esc_html_e( 'then copy the key and paste it here.', 'good-connect' ); ?></li>
                            </ol>
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
                        <p class="description">
                            <?php esc_html_e( 'The GHL Location (sub-account) ID to send data to.', 'good-connect' ); ?><br>
                            <?php esc_html_e( 'To find this: in GoHighLevel go to', 'good-connect' ); ?>
                            <strong><?php esc_html_e( 'Settings → Business Profile', 'good-connect' ); ?></strong>
                            <?php esc_html_e( '— the Location ID is shown at the bottom of the page.', 'good-connect' ); ?>
                        </p>
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

        $forms = GFFormsModel::get_forms( true );
        if ( empty( $forms ) ) {
            echo '<p>' . esc_html__( 'No Gravity Forms found.', 'good-connect' ) . '</p>';
            return;
        }

        // Pass all form data + existing mappings to JS so it can render without extra AJAX.
        $forms_data = [];
        $ghl_fields = self::ghl_contact_fields();

        foreach ( $forms as $form ) {
            $form_obj  = GFAPI::get_form( $form->id );
            $fields    = [];
            foreach ( $form_obj['fields'] as $field ) {
                $fields[] = [
                    'id'    => (string) $field->id,
                    'label' => $field->label ?: sprintf( __( 'Field %s', 'good-connect' ), $field->id ),
                ];
            }
            $forms_data[] = [
                'id'      => (int) $form->id,
                'title'   => $form->title,
                'fields'  => $fields,
                'mapping' => GoodConnect_GF::get_field_mapping( (int) $form->id ),
            ];
        }

        wp_localize_script( 'goodconnect-admin', 'GoodConnectGF', [
            'forms'     => $forms_data,
            'ghlFields' => $ghl_fields,
        ] );
        ?>
        <div class="goodconnect-gf-selector-row">
            <label for="goodconnect-gf-form-select"><strong><?php esc_html_e( 'Select a Form', 'good-connect' ); ?></strong></label>
            <select id="goodconnect-gf-form-select">
                <option value=""><?php esc_html_e( '— Choose a form —', 'good-connect' ); ?></option>
                <?php foreach ( $forms as $form ) : ?>
                    <option value="<?php echo esc_attr( $form->id ); ?>">
                        <?php echo esc_html( $form->title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="goodconnect-gf-mapper-wrap" style="display:none;">
            <div class="goodconnect-card">
                <h3 class="goodconnect-card-title" id="goodconnect-gf-form-title"></h3>

                <div class="goodconnect-mapper" id="goodconnect-gf-mapper">
                    <div class="goodconnect-mapper-header">
                        <span><?php esc_html_e( 'GHL Field', 'good-connect' ); ?></span>
                        <span><?php esc_html_e( 'Gravity Forms Field', 'good-connect' ); ?></span>
                    </div>
                    <!-- Rows injected by JS -->
                </div>

                <div class="goodconnect-card-footer">
                    <button type="button" class="button button-primary" id="goodconnect-save-gf">
                        <?php esc_html_e( 'Save Mapping', 'good-connect' ); ?>
                    </button>
                    <span class="goodconnect-save-status"></span>
                </div>
            </div>
        </div>
        <?php
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

        $options                = get_option( GoodConnect_Settings::OPTION_KEY, [] );
        $options['woo_enabled'] = $enabled;
        update_option( GoodConnect_Settings::OPTION_KEY, $options );

        wp_send_json_success();
    }

    public static function ajax_get_gf_form() {
        check_ajax_referer( 'goodconnect_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorised', 403 );
        }

        if ( ! class_exists( 'GFAPI' ) ) {
            wp_send_json_error( 'Gravity Forms not active' );
        }

        $form_id  = absint( $_POST['form_id'] ?? 0 );
        $form_obj = GFAPI::get_form( $form_id );

        if ( ! $form_obj ) {
            wp_send_json_error( 'Form not found' );
        }

        $fields = [];
        foreach ( $form_obj['fields'] as $field ) {
            $fields[] = [
                'id'    => (string) $field->id,
                'label' => $field->label ?: sprintf( 'Field %s', $field->id ),
            ];
        }

        wp_send_json_success( [
            'title'   => $form_obj['title'],
            'fields'  => $fields,
            'mapping' => GoodConnect_GF::get_field_mapping( $form_id ),
        ] );
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
