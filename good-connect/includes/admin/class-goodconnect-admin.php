<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_ajax_goodconnect_save_accounts',         [ __CLASS__, 'ajax_save_accounts' ] );
        add_action( 'wp_ajax_goodconnect_save_gf_config',        [ __CLASS__, 'ajax_save_gf_config' ] );
        add_action( 'wp_ajax_goodconnect_save_elementor_config', [ __CLASS__, 'ajax_save_elementor_config' ] );
        add_action( 'wp_ajax_goodconnect_save_woo_settings',     [ __CLASS__, 'ajax_save_woo_settings' ] );
        add_action( 'wp_ajax_goodconnect_save_cf7_config',       [ __CLASS__, 'ajax_save_cf7_config' ] );
        add_action( 'wp_ajax_goodconnect_fetch_ghl_custom_fields', [ __CLASS__, 'ajax_fetch_ghl_custom_fields' ] );
        add_action( 'wp_ajax_goodconnect_bulk_sync_start',       [ __CLASS__, 'ajax_bulk_sync_start' ] );
        add_action( 'wp_ajax_goodconnect_bulk_sync_cancel',      [ __CLASS__, 'ajax_bulk_sync_cancel' ] );
        add_action( 'wp_ajax_goodconnect_bulk_sync_progress',    [ __CLASS__, 'ajax_bulk_sync_progress' ] );
        add_action( 'wp_ajax_goodconnect_clear_log',             [ __CLASS__, 'ajax_clear_log' ] );
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
        if ( $hook !== 'toplevel_page_good-connect' ) return;

        wp_enqueue_style( 'goodconnect-admin', GOODCONNECT_PLUGIN_URL . 'assets/css/admin.css', [], GOODCONNECT_VERSION );
        wp_enqueue_script( 'goodconnect-admin', GOODCONNECT_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery' ], GOODCONNECT_VERSION, true );

        wp_localize_script( 'goodconnect-admin', 'GoodConnect', [
            'nonce'    => wp_create_nonce( 'goodconnect_admin' ),
            'ajaxurl'  => admin_url( 'admin-ajax.php' ),
            'accounts' => GoodConnect_Settings::get_accounts(),
            'strings'  => [
                'saved'          => __( 'Saved!', 'good-connect' ),
                'saving'         => __( 'Saving…', 'good-connect' ),
                'error'          => __( 'Error saving. Please try again.', 'good-connect' ),
                'confirmDelete'  => __( 'Delete this account?', 'good-connect' ),
                'confirmClear'   => __( 'Clear all log entries? This cannot be undone.', 'good-connect' ),
                'addTag'         => __( 'Add Tag', 'good-connect' ),
                'addCustomField' => __( 'Add Custom Field', 'good-connect' ),
            ],
        ] );

        // Pass GF form data + configs to JS.
        if ( class_exists( 'GFForms' ) && class_exists( 'GFAPI' ) ) {
            $forms      = GFFormsModel::get_forms( true );
            $forms_data = [];
            foreach ( $forms as $form ) {
                $form_obj = GFAPI::get_form( $form->id );
                $fields   = [];
                foreach ( $form_obj['fields'] as $field ) {
                    // Expand name fields into subfields.
                    if ( $field->type === 'name' && ! empty( $field->inputs ) ) {
                        foreach ( $field->inputs as $input ) {
                            if ( ! empty( $input['isHidden'] ) ) continue;
                            $fields[] = [
                                'id'    => (string) $input['id'],
                                'label' => esc_html( $field->label ) . ' — ' . esc_html( $input['label'] ),
                            ];
                        }
                    } else {
                        $fields[] = [
                            'id'    => (string) $field->id,
                            'label' => $field->label ?: 'Field ' . $field->id,
                        ];
                    }
                }
                $config     = GoodConnect_GF::get_form_config( (int) $form->id );
                $forms_data[] = [
                    'id'     => (int) $form->id,
                    'title'  => $form->title,
                    'fields' => $fields,
                    'config' => $config,
                ];
            }
            wp_localize_script( 'goodconnect-admin', 'GoodConnectGF', [
                'forms'     => $forms_data,
                'ghlFields' => self::ghl_contact_fields(),
            ] );
        }
    }

    // -------------------------------------------------------------------------
    // Page render
    // -------------------------------------------------------------------------

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die(
                esc_html__( 'You do not have permission to access this page.', 'good-connect' ),
                esc_html__( 'Access Denied', 'good-connect' ),
                [ 'response' => 403 ]
            );
        }
        $active_tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'settings' ) );
        $tabs = [
            'settings'      => __( 'Settings', 'good-connect' ),
            'gravity-forms' => __( 'Gravity Forms', 'good-connect' ),
            'elementor'     => __( 'Elementor', 'good-connect' ),
            'woocommerce'   => __( 'WooCommerce', 'good-connect' ),
            'contact-form-7'=> __( 'Contact Form 7', 'good-connect' ),
            'bulk-sync'     => __( 'Bulk Sync', 'good-connect' ),
            'webhooks'      => __( 'Webhooks', 'good-connect' ),
            'log'           => __( 'Activity Log', 'good-connect' ),
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
                       class="nav-tab <?php echo esc_attr( $active_tab === $slug ? 'nav-tab-active' : '' ); ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="goodconnect-tab-content">
                <?php
                switch ( $active_tab ) {
                    case 'gravity-forms':  self::render_gf_tab();        break;
                    case 'elementor':      self::render_elementor_tab();  break;
                    case 'woocommerce':    self::render_woo_tab();        break;
                    case 'contact-form-7': self::render_cf7_tab();        break;
                    case 'bulk-sync':      self::render_bulk_sync_tab();  break;
                    case 'webhooks':       GoodConnect_Webhook_Admin::render_webhooks_tab(); break;
                    case 'log':            self::render_log_tab();        break;
                    default:               self::render_settings_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Settings tab — Accounts manager
    // -------------------------------------------------------------------------

    private static function render_settings_tab() {
        $accounts = GoodConnect_Settings::get_accounts();
        ?>
        <div class="goodconnect-card">
            <h3 class="goodconnect-card-title"><?php esc_html_e( 'GoHighLevel Accounts', 'good-connect' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Add one or more GHL sub-accounts. Each form can be configured to send to a specific account.', 'good-connect' ); ?></p>

            <div id="goodconnect-accounts-list">
                <?php foreach ( $accounts as $account ) : ?>
                    <div class="goodconnect-account-row" data-id="<?php echo esc_attr( $account['id'] ); ?>">
                        <input type="text" class="gc-account-label" placeholder="<?php esc_attr_e( 'Account label', 'good-connect' ); ?>" value="<?php echo esc_attr( $account['label'] ); ?>" />
                        <input type="password" class="gc-account-apikey" placeholder="<?php esc_attr_e( 'API Key', 'good-connect' ); ?>" value="<?php echo esc_attr( $account['api_key'] ); ?>" autocomplete="new-password" />
                        <input type="text" class="gc-account-locationid" placeholder="<?php esc_attr_e( 'Location ID', 'good-connect' ); ?>" value="<?php echo esc_attr( $account['location_id'] ); ?>" />
                        <label class="gc-account-default-label">
                            <input type="radio" name="gc_default_account" class="gc-account-default" value="<?php echo esc_attr( $account['id'] ); ?>" <?php checked( ! empty( $account['is_default'] ) ); ?> />
                            <?php esc_html_e( 'Default', 'good-connect' ); ?>
                        </label>
                        <button type="button" class="button goodconnect-remove-account"><?php esc_html_e( 'Remove', 'good-connect' ); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="goodconnect-accounts-actions">
                <button type="button" class="button" id="goodconnect-add-account">+ <?php esc_html_e( 'Add Account', 'good-connect' ); ?></button>
            </div>

            <div class="goodconnect-card-footer" style="margin-top:16px;">
                <button type="button" class="button button-primary" id="goodconnect-save-accounts"><?php esc_html_e( 'Save Accounts', 'good-connect' ); ?></button>
                <span class="goodconnect-save-status"></span>
            </div>
        </div>

        <div class="goodconnect-card">
            <h3 class="goodconnect-card-title"><?php esc_html_e( 'How to create your API key', 'good-connect' ); ?></h3>
            <ol style="margin:6px 0 0 18px;line-height:1.9">
                <li><?php esc_html_e( 'In GoHighLevel, go to', 'good-connect' ); ?> <strong><?php esc_html_e( 'Settings → Private Integrations', 'good-connect' ); ?></strong></li>
                <li><?php esc_html_e( 'Click', 'good-connect' ); ?> <strong><?php esc_html_e( 'Create private integration', 'good-connect' ); ?></strong></li>
                <li><?php esc_html_e( 'Write a name and description based on what you wish to connect', 'good-connect' ); ?></li>
                <li><?php esc_html_e( 'Enable scopes:', 'good-connect' ); ?>
                    <ul style="margin:4px 0 4px 18px;list-style:disc">
                        <li><code>contacts.readonly</code> &mdash; <?php esc_html_e( 'look up existing contacts', 'good-connect' ); ?></li>
                        <li><code>contacts.write</code> &mdash; <?php esc_html_e( 'create and update contacts', 'good-connect' ); ?></li>
                        <li><code>locations/customFields.readonly</code> &mdash; <?php esc_html_e( 'load custom fields via "Load from GHL"', 'good-connect' ); ?></li>
                        <li><code>opportunities.readonly</code> &mdash; <?php esc_html_e( 'look up opportunities (optional)', 'good-connect' ); ?></li>
                        <li><code>opportunities.write</code> &mdash; <?php esc_html_e( 'create opportunities (optional)', 'good-connect' ); ?></li>
                    </ul>
                </li>
                <li><?php esc_html_e( 'Click Create then copy the key and paste it above.', 'good-connect' ); ?></li>
            </ol>
            <p class="description" style="margin-top:10px;">
                <?php esc_html_e( 'To find your Location ID: go to', 'good-connect' ); ?>
                <strong><?php esc_html_e( 'Settings → Business Profile', 'good-connect' ); ?></strong>
                <?php esc_html_e( '— the Location ID is shown at the bottom of the page.', 'good-connect' ); ?>
            </p>
        </div>

        <template id="goodconnect-account-row-template">
            <div class="goodconnect-account-row" data-id="">
                <input type="text" class="gc-account-label" placeholder="<?php esc_attr_e( 'Account label', 'good-connect' ); ?>" value="" />
                <input type="password" class="gc-account-apikey" placeholder="<?php esc_attr_e( 'API Key', 'good-connect' ); ?>" value="" autocomplete="new-password" />
                <input type="text" class="gc-account-locationid" placeholder="<?php esc_attr_e( 'Location ID', 'good-connect' ); ?>" value="" />
                <label class="gc-account-default-label">
                    <input type="radio" name="gc_default_account" class="gc-account-default" value="" />
                    <?php esc_html_e( 'Default', 'good-connect' ); ?>
                </label>
                <button type="button" class="button goodconnect-remove-account"><?php esc_html_e( 'Remove', 'good-connect' ); ?></button>
            </div>
        </template>
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
        ?>
        <div class="goodconnect-gf-selector-row">
            <label for="goodconnect-gf-form-select"><strong><?php esc_html_e( 'Select a Form', 'good-connect' ); ?></strong></label>
            <select id="goodconnect-gf-form-select">
                <option value=""><?php esc_html_e( '— Choose a form —', 'good-connect' ); ?></option>
                <?php foreach ( $forms as $form ) : ?>
                    <option value="<?php echo esc_attr( $form->id ); ?>"><?php echo esc_html( $form->title ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div id="goodconnect-gf-mapper-wrap" style="display:none;">
            <div class="goodconnect-card">
                <h3 class="goodconnect-card-title" id="goodconnect-gf-form-title"></h3>

                <div class="goodconnect-section">
                    <label class="goodconnect-section-label"><?php esc_html_e( 'GHL Account', 'good-connect' ); ?></label>
                    <select id="goodconnect-gf-account">
                        <option value=""><?php esc_html_e( '— Use default account —', 'good-connect' ); ?></option>
                    </select>
                </div>

                <div class="goodconnect-mapper" id="goodconnect-gf-mapper">
                    <div class="goodconnect-mapper-header">
                        <span><?php esc_html_e( 'GHL Field', 'good-connect' ); ?></span>
                        <span><?php esc_html_e( 'Gravity Forms Field', 'good-connect' ); ?></span>
                    </div>
                </div>

                <div class="goodconnect-section" id="goodconnect-gf-custom-fields-wrap">
                    <label class="goodconnect-section-label"><?php esc_html_e( 'Custom Fields', 'good-connect' ); ?></label>
                    <p class="description"><?php esc_html_e( 'Map GHL custom fields to form fields. Use "Load from GHL" to populate the dropdown with your account\'s custom fields.', 'good-connect' ); ?></p>
                    <div id="goodconnect-gf-custom-fields"></div>
                    <div style="display:flex;gap:8px;margin-top:8px;align-items:center;">
                        <button type="button" class="button goodconnect-add-custom-field">+ <?php esc_html_e( 'Add Custom Field', 'good-connect' ); ?></button>
                        <button type="button" class="button goodconnect-load-ghl-fields" data-target="gf"><?php esc_html_e( 'Load from GHL', 'good-connect' ); ?></button>
                        <span class="goodconnect-ghl-fields-status" style="font-size:13px;color:#666;"></span>
                    </div>
                </div>

                <div class="goodconnect-section">
                    <label class="goodconnect-section-label"><?php esc_html_e( 'Static Tags', 'good-connect' ); ?></label>
                    <p class="description"><?php esc_html_e( 'These tags are added to every GHL contact from this form. Comma-separated.', 'good-connect' ); ?></p>
                    <input type="text" id="goodconnect-gf-static-tags" class="regular-text" placeholder="e.g. webinar-lead, Q1-2026" />
                </div>

                <div class="goodconnect-section">
                    <label class="goodconnect-section-label"><?php esc_html_e( 'Dynamic Tags', 'good-connect' ); ?></label>
                    <p class="description"><?php esc_html_e( 'The value of the selected field will be added as a tag on the GHL contact.', 'good-connect' ); ?></p>
                    <div id="goodconnect-gf-dynamic-tags"></div>
                    <button type="button" class="button goodconnect-add-dynamic-tag" style="margin-top:8px;">+ <?php esc_html_e( 'Add Dynamic Tag Field', 'good-connect' ); ?></button>
                </div>

                <div class="goodconnect-card-footer">
                    <button type="button" class="button button-primary" id="goodconnect-save-gf"><?php esc_html_e( 'Save Mapping', 'good-connect' ); ?></button>
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
        $all_configs = get_option( 'goodconnect_elementor_configs', [] );
        $ghl_fields  = self::ghl_contact_fields();
        ?>
        <p class="description"><?php esc_html_e( 'Enter the Elementor form name and map each field ID to a GHL contact field.', 'good-connect' ); ?></p>
        <div id="goodconnect-elementor-forms">
            <?php foreach ( $all_configs as $form_name => $config ) :
                self::render_elementor_form_card( $form_name, $config, $ghl_fields );
            endforeach; ?>
        </div>
        <button type="button" class="button goodconnect-add-elementor-form" style="margin-top:12px;">+ <?php esc_html_e( 'Add Form', 'good-connect' ); ?></button>
        <template id="goodconnect-elementor-card-template">
            <?php self::render_elementor_form_card( '', [], $ghl_fields ); ?>
        </template>
        <?php
    }

    private static function render_elementor_form_card( string $form_name, array $config, array $ghl_fields ) {
        $field_map     = $config['field_map']    ?? [];
        $custom_fields = $config['custom_fields'] ?? [];
        $static_tags   = implode( ', ', (array) ( $config['static_tags'] ?? [] ) );
        $account_id    = $config['account_id']   ?? '';
        ?>
        <div class="goodconnect-card goodconnect-elementor-card">
            <div class="goodconnect-card-title-row">
                <input type="text" class="goodconnect-elementor-form-name regular-text"
                       placeholder="<?php esc_attr_e( 'Form name (must match exactly)', 'good-connect' ); ?>"
                       value="<?php echo esc_attr( $form_name ); ?>" />
                <button type="button" class="button goodconnect-remove-elementor-form"><?php esc_html_e( 'Remove', 'good-connect' ); ?></button>
            </div>

            <div class="goodconnect-section">
                <label class="goodconnect-section-label"><?php esc_html_e( 'GHL Account', 'good-connect' ); ?></label>
                <select class="goodconnect-elementor-account">
                    <option value=""><?php esc_html_e( '— Use default account —', 'good-connect' ); ?></option>
                    <?php foreach ( GoodConnect_Settings::get_accounts() as $account ) : ?>
                        <option value="<?php echo esc_attr( $account['id'] ); ?>" <?php selected( $account_id, $account['id'] ); ?>>
                            <?php echo esc_html( $account['label'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="goodconnect-mapper">
                <div class="goodconnect-mapper-header">
                    <span><?php esc_html_e( 'GHL Field', 'good-connect' ); ?></span>
                    <span><?php esc_html_e( 'Elementor Field ID', 'good-connect' ); ?></span>
                </div>
                <?php foreach ( $ghl_fields as $ghl_key => $ghl_label ) : ?>
                    <div class="goodconnect-mapper-row">
                        <label><?php echo esc_html( $ghl_label ); ?></label>
                        <input type="text" class="goodconnect-elementor-field-id"
                               data-ghl-field="<?php echo esc_attr( $ghl_key ); ?>"
                               placeholder="<?php esc_attr_e( 'e.g. email', 'good-connect' ); ?>"
                               value="<?php echo esc_attr( $field_map[ $ghl_key ] ?? '' ); ?>" />
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="goodconnect-section goodconnect-elementor-custom-fields-wrap">
                <label class="goodconnect-section-label"><?php esc_html_e( 'Custom Fields', 'good-connect' ); ?></label>
                <p class="description"><?php esc_html_e( 'Map GHL custom fields to Elementor field IDs.', 'good-connect' ); ?></p>
                <div class="goodconnect-elementor-custom-fields">
                    <?php foreach ( $custom_fields as $row ) : ?>
                        <div class="goodconnect-custom-field-row goodconnect-elementor-custom-field-row">
                            <select class="gc-custom-ghl-key-select">
                                <option value="<?php echo esc_attr( $row['ghl_key'] ?? '' ); ?>"><?php echo esc_html( $row['ghl_key'] ?? '' ); ?></option>
                            </select>
                            <input type="text" class="gc-custom-elementor-field"
                                   placeholder="<?php esc_attr_e( 'Elementor field ID', 'good-connect' ); ?>"
                                   value="<?php echo esc_attr( $row['elementor_field'] ?? $row['gf_field_id'] ?? '' ); ?>" />
                            <button type="button" class="button goodconnect-remove-custom-field">&times;</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="display:flex;gap:8px;margin-top:8px;align-items:center;">
                    <button type="button" class="button goodconnect-add-elementor-custom-field">+ <?php esc_html_e( 'Add Custom Field', 'good-connect' ); ?></button>
                    <button type="button" class="button goodconnect-load-ghl-fields" data-target="elementor"><?php esc_html_e( 'Load from GHL', 'good-connect' ); ?></button>
                    <span class="goodconnect-ghl-fields-status" style="font-size:13px;color:#666;"></span>
                </div>
            </div>

            <div class="goodconnect-section">
                <label class="goodconnect-section-label"><?php esc_html_e( 'Static Tags', 'good-connect' ); ?></label>
                <input type="text" class="goodconnect-elementor-static-tags regular-text"
                       placeholder="e.g. webinar-lead, Q1-2026"
                       value="<?php echo esc_attr( $static_tags ); ?>" />
            </div>

            <div class="goodconnect-card-footer">
                <button type="button" class="button button-primary goodconnect-save-elementor"><?php esc_html_e( 'Save Mapping', 'good-connect' ); ?></button>
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
        $enabled    = GoodConnect_Settings::get( 'woo_enabled' );
        $account_id = GoodConnect_Settings::get( 'woo_account_id' );
        ?>
        <div class="goodconnect-card">
            <h3 class="goodconnect-card-title"><?php esc_html_e( 'Order → GHL Contact', 'good-connect' ); ?></h3>
            <p class="description"><?php esc_html_e( 'When enabled, a GHL contact will be created or updated every time a new WooCommerce order is placed.', 'good-connect' ); ?></p>

            <label class="goodconnect-toggle">
                <input type="checkbox" id="gc_woo_enabled" <?php checked( $enabled, '1' ); ?> value="1" />
                <?php esc_html_e( 'Enable WooCommerce → GHL sync', 'good-connect' ); ?>
            </label>

            <div class="goodconnect-section" style="margin-top:16px;">
                <label class="goodconnect-section-label"><?php esc_html_e( 'GHL Account', 'good-connect' ); ?></label>
                <select id="gc_woo_account_id">
                    <option value=""><?php esc_html_e( '— Use default account —', 'good-connect' ); ?></option>
                    <?php foreach ( GoodConnect_Settings::get_accounts() as $account ) : ?>
                        <option value="<?php echo esc_attr( $account['id'] ); ?>" <?php selected( $account_id, $account['id'] ); ?>>
                            <?php echo esc_html( $account['label'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <ul class="goodconnect-field-list">
                <li><?php esc_html_e( 'Fields sent: firstName, lastName, email, phone, address1, city, state, postalCode, country', 'good-connect' ); ?></li>
                <li><?php esc_html_e( 'Tag:', 'good-connect' ); ?> <code>woocommerce-customer</code></li>
            </ul>

            <div class="goodconnect-card-footer">
                <button type="button" class="button button-primary" id="goodconnect-save-woo"><?php esc_html_e( 'Save', 'good-connect' ); ?></button>
                <span class="goodconnect-save-status"></span>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Contact Form 7 tab
    // -------------------------------------------------------------------------

    private static function render_cf7_tab() {
        if ( ! class_exists( 'WPCF7' ) ) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Contact Form 7 is not active.', 'good-connect' ) . '</p></div>';
            return;
        }

        $forms = get_posts( [
            'post_type'      => 'wpcf7_contact_form',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        if ( empty( $forms ) ) {
            echo '<p>' . esc_html__( 'No Contact Form 7 forms found.', 'good-connect' ) . '</p>';
            return;
        }

        $ghl_fields = self::ghl_contact_fields();
        ?>
        <p class="description"><?php esc_html_e( 'Map Contact Form 7 field names to GHL contact fields. Use the field name set in the CF7 form tag (e.g. your-email).', 'good-connect' ); ?></p>

        <?php foreach ( $forms as $form ) :
            $form_id       = $form->ID;
            $config        = GoodConnect_CF7::get_form_config( $form_id );
            $field_map     = $config['field_map']    ?? [];
            $custom_fields = $config['custom_fields'] ?? [];
            $static_tags = implode( ', ', (array) ( $config['static_tags'] ?? [] ) );
            $account_id  = $config['account_id']  ?? '';
        ?>
        <div class="goodconnect-card goodconnect-cf7-card" data-form-id="<?php echo esc_attr( $form_id ); ?>">
            <div class="goodconnect-card-title">
                <?php echo esc_html( $form->post_title ); ?>
                <span class="goodconnect-form-id">(ID: <?php echo esc_html( $form_id ); ?>)</span>
            </div>

            <div class="goodconnect-section">
                <label class="goodconnect-section-label"><?php esc_html_e( 'GHL Account', 'good-connect' ); ?></label>
                <select class="goodconnect-cf7-account">
                    <option value=""><?php esc_html_e( '— Use default account —', 'good-connect' ); ?></option>
                    <?php foreach ( GoodConnect_Settings::get_accounts() as $account ) : ?>
                        <option value="<?php echo esc_attr( $account['id'] ); ?>" <?php selected( $account_id, $account['id'] ); ?>>
                            <?php echo esc_html( $account['label'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="goodconnect-mapper">
                <div class="goodconnect-mapper-header">
                    <span><?php esc_html_e( 'GHL Field', 'good-connect' ); ?></span>
                    <span><?php esc_html_e( 'CF7 Field Name', 'good-connect' ); ?></span>
                </div>
                <?php foreach ( $ghl_fields as $ghl_key => $ghl_label ) : ?>
                    <div class="goodconnect-mapper-row">
                        <label><?php echo esc_html( $ghl_label ); ?></label>
                        <input type="text" class="goodconnect-cf7-field-id"
                               data-ghl-field="<?php echo esc_attr( $ghl_key ); ?>"
                               placeholder="<?php esc_attr_e( 'e.g. your-email', 'good-connect' ); ?>"
                               value="<?php echo esc_attr( $field_map[ $ghl_key ] ?? '' ); ?>" />
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="goodconnect-section goodconnect-cf7-custom-fields-wrap">
                <label class="goodconnect-section-label"><?php esc_html_e( 'Custom Fields', 'good-connect' ); ?></label>
                <p class="description"><?php esc_html_e( 'Map GHL custom fields to CF7 field names.', 'good-connect' ); ?></p>
                <div class="goodconnect-cf7-custom-fields">
                    <?php foreach ( $custom_fields as $row ) : ?>
                        <div class="goodconnect-custom-field-row goodconnect-cf7-custom-field-row">
                            <select class="gc-custom-ghl-key-select">
                                <option value="<?php echo esc_attr( $row['ghl_key'] ?? '' ); ?>"><?php echo esc_html( $row['ghl_key'] ?? '' ); ?></option>
                            </select>
                            <input type="text" class="gc-custom-cf7-field"
                                   placeholder="<?php esc_attr_e( 'CF7 field name', 'good-connect' ); ?>"
                                   value="<?php echo esc_attr( $row['cf7_field'] ?? '' ); ?>" />
                            <button type="button" class="button goodconnect-remove-custom-field">&times;</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="display:flex;gap:8px;margin-top:8px;align-items:center;">
                    <button type="button" class="button goodconnect-add-cf7-custom-field">+ <?php esc_html_e( 'Add Custom Field', 'good-connect' ); ?></button>
                    <button type="button" class="button goodconnect-load-ghl-fields" data-target="cf7"><?php esc_html_e( 'Load from GHL', 'good-connect' ); ?></button>
                    <span class="goodconnect-ghl-fields-status" style="font-size:13px;color:#666;"></span>
                </div>
            </div>

            <div class="goodconnect-section">
                <label class="goodconnect-section-label"><?php esc_html_e( 'Static Tags', 'good-connect' ); ?></label>
                <input type="text" class="goodconnect-cf7-static-tags regular-text"
                       placeholder="e.g. webinar-lead, Q1-2026"
                       value="<?php echo esc_attr( $static_tags ); ?>" />
            </div>

            <div class="goodconnect-card-footer">
                <button type="button" class="button button-primary goodconnect-save-cf7"><?php esc_html_e( 'Save Mapping', 'good-connect' ); ?></button>
                <span class="goodconnect-save-status"></span>
            </div>
        </div>
        <?php endforeach; ?>
        <?php
    }

    // -------------------------------------------------------------------------
    // Bulk Sync tab
    // -------------------------------------------------------------------------

    private static function render_bulk_sync_tab() {
        $progress = GoodConnect_BulkSync::get_progress();
        $last_run = get_option( GoodConnect_BulkSync::LOG_KEY, [] );
        ?>
        <div class="goodconnect-card">
            <h3 class="goodconnect-card-title"><?php esc_html_e( 'Bulk Sync WordPress Users → GHL', 'good-connect' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Sync all WordPress users to GHL as contacts. Runs in batches of 20 via WP-Cron.', 'good-connect' ); ?></p>

            <div class="goodconnect-section">
                <label class="goodconnect-section-label"><?php esc_html_e( 'GHL Account', 'good-connect' ); ?></label>
                <select id="gc-bulk-sync-account">
                    <option value=""><?php esc_html_e( '— Use default account —', 'good-connect' ); ?></option>
                    <?php foreach ( GoodConnect_Settings::get_accounts() as $account ) : ?>
                        <option value="<?php echo esc_attr( $account['id'] ); ?>"><?php echo esc_html( $account['label'] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="gc-bulk-sync-progress-wrap" style="<?php echo esc_attr( $progress ? '' : 'display:none' ); ?>">
                <div style="margin:12px 0;">
                    <strong><?php esc_html_e( 'Status:', 'good-connect' ); ?></strong>
                    <span id="gc-bulk-sync-status"><?php echo esc_html( $progress['status'] ?? '' ); ?></span>
                    &nbsp;|&nbsp;
                    <strong><?php esc_html_e( 'Processed:', 'good-connect' ); ?></strong>
                    <span id="gc-bulk-sync-processed"><?php echo (int) ( $progress['processed'] ?? 0 ); ?></span>
                    /
                    <span id="gc-bulk-sync-total"><?php echo (int) ( $progress['total'] ?? 0 ); ?></span>
                    &nbsp;|&nbsp;
                    <strong><?php esc_html_e( 'Errors:', 'good-connect' ); ?></strong>
                    <span id="gc-bulk-sync-errors"><?php echo (int) ( $progress['errors'] ?? 0 ); ?></span>
                </div>
            </div>

            <?php if ( ! empty( $last_run ) ) : ?>
                <p class="description">
                    <?php
                    printf(
                        esc_html__( 'Last run completed %s — %d synced, %d errors.', 'good-connect' ),
                        esc_html( $last_run['completed_at'] ?? '' ),
                        (int) ( $last_run['processed'] ?? 0 ),
                        (int) ( $last_run['errors'] ?? 0 )
                    );
                    ?>
                </p>
            <?php endif; ?>

            <div class="goodconnect-card-footer" style="margin-top:16px;">
                <button type="button" class="button button-primary" id="gc-bulk-sync-start"
                    <?php echo ( $progress && $progress['status'] === 'running' ) ? 'disabled' : ''; ?>>
                    <?php esc_html_e( 'Start Bulk Sync', 'good-connect' ); ?>
                </button>
                <button type="button" class="button" id="gc-bulk-sync-cancel" style="<?php echo ( $progress && $progress['status'] === 'running' ) ? '' : 'display:none'; ?>">
                    <?php esc_html_e( 'Cancel', 'good-connect' ); ?>
                </button>
                <span class="goodconnect-save-status" id="gc-bulk-sync-msg"></span>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Activity Log tab
    // -------------------------------------------------------------------------

    private static function render_log_tab() {
        $source  = sanitize_key( wp_unslash( $_GET['gc_source'] ?? '' ) );
        $success = isset( $_GET['gc_success'] ) ? sanitize_key( wp_unslash( $_GET['gc_success'] ) ) : 'all';
        ?>
        <div class="goodconnect-log-filters">
            <form method="get">
                <input type="hidden" name="page" value="good-connect" />
                <input type="hidden" name="tab" value="log" />
                <select name="gc_source">
                    <option value=""><?php esc_html_e( 'All Sources', 'good-connect' ); ?></option>
                    <option value="gravity-forms"   <?php selected( $source, 'gravity-forms' ); ?>><?php esc_html_e( 'Gravity Forms', 'good-connect' ); ?></option>
                    <option value="elementor"      <?php selected( $source, 'elementor' ); ?>><?php esc_html_e( 'Elementor', 'good-connect' ); ?></option>
                    <option value="woocommerce"    <?php selected( $source, 'woocommerce' ); ?>><?php esc_html_e( 'WooCommerce', 'good-connect' ); ?></option>
                    <option value="contact-form-7" <?php selected( $source, 'contact-form-7' ); ?>><?php esc_html_e( 'Contact Form 7', 'good-connect' ); ?></option>
                    <option value="webhook"        <?php selected( $source, 'webhook' ); ?>><?php esc_html_e( 'Webhook', 'good-connect' ); ?></option>
                    <option value="bulk-sync"      <?php selected( $source, 'bulk-sync' ); ?>><?php esc_html_e( 'Bulk Sync', 'good-connect' ); ?></option>
                </select>
                <select name="gc_success">
                    <option value="all" <?php selected( $success, 'all' ); ?>><?php esc_html_e( 'All Statuses', 'good-connect' ); ?></option>
                    <option value="1"   <?php selected( $success, '1' ); ?>><?php esc_html_e( 'Success', 'good-connect' ); ?></option>
                    <option value="0"   <?php selected( $success, '0' ); ?>><?php esc_html_e( 'Failed', 'good-connect' ); ?></option>
                </select>
                <button type="submit" class="button"><?php esc_html_e( 'Filter', 'good-connect' ); ?></button>
                <button type="button" class="button" id="goodconnect-clear-log" style="float:right;color:#b32d2e;border-color:#b32d2e;"><?php esc_html_e( 'Clear Log', 'good-connect' ); ?></button>
            </form>
        </div>

        <?php
        $table = new GoodConnect_Log_Table();
        $table->prepare_items();
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="good-connect" />';
        echo '<input type="hidden" name="tab" value="log" />';
        $table->display();
        echo '</form>';
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    public static function ajax_save_accounts() {
        check_ajax_referer( 'goodconnect_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised', 403 );

        $raw      = wp_unslash( $_POST['accounts'] ?? [] );
        $accounts = [];
        $has_default = false;

        foreach ( (array) $raw as $row ) {
            $id = sanitize_text_field( $row['id'] ?? '' );
            if ( ! $id ) $id = 'account_' . substr( md5( uniqid( '', true ) ), 0, 8 );

            $is_default = ! empty( $row['is_default'] ) && ! $has_default;
            if ( $is_default ) $has_default = true;

            $accounts[] = [
                'id'          => $id,
                'label'       => sanitize_text_field( $row['label']       ?? 'Account' ),
                'api_key'     => sanitize_text_field( $row['api_key']     ?? '' ),
                'location_id' => sanitize_text_field( $row['location_id'] ?? '' ),
                'is_default'  => $is_default,
            ];
        }

        // Ensure at least one default.
        if ( $accounts && ! $has_default ) $accounts[0]['is_default'] = true;

        GoodConnect_Settings::save_accounts( $accounts );
        wp_send_json_success( [ 'accounts' => $accounts ] );
    }

    public static function ajax_save_gf_config() {
        check_ajax_referer( 'goodconnect_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised', 403 );

        $form_id = absint( wp_unslash( $_POST['form_id'] ?? 0 ) );
        if ( ! $form_id ) wp_send_json_error( 'Invalid form ID' );

        $raw_map = (array) wp_unslash( $_POST['field_map'] ?? [] );
        $field_map = [];
        foreach ( $raw_map as $ghl_field => $gf_field_id ) {
            $ghl_field  = sanitize_text_field( $ghl_field );
            $gf_field_id = sanitize_text_field( $gf_field_id );
            if ( $ghl_field && $gf_field_id !== '' ) $field_map[ $ghl_field ] = $gf_field_id;
        }

        $raw_custom = (array) wp_unslash( $_POST['custom_fields'] ?? [] );
        $custom_fields = [];
        foreach ( $raw_custom as $row ) {
            $key      = sanitize_text_field( $row['ghl_key']     ?? '' );
            $field_id = sanitize_text_field( $row['gf_field_id'] ?? '' );
            if ( $key && $field_id ) $custom_fields[] = [ 'ghl_key' => $key, 'gf_field_id' => $field_id ];
        }

        $raw_static = sanitize_text_field( wp_unslash( $_POST['static_tags'] ?? '' ) );
        $static_tags = array_values( array_filter( array_map( 'trim', explode( ',', $raw_static ) ) ) );

        $raw_dynamic = (array) wp_unslash( $_POST['dynamic_tags'] ?? [] );
        $dynamic_tags = [];
        foreach ( $raw_dynamic as $row ) {
            $field_id = sanitize_text_field( $row['gf_field_id'] ?? '' );
            if ( $field_id ) $dynamic_tags[] = [ 'gf_field_id' => $field_id ];
        }

        $config = [
            'account_id'    => sanitize_text_field( wp_unslash( $_POST['account_id'] ?? '' ) ),
            'field_map'     => $field_map,
            'custom_fields' => $custom_fields,
            'static_tags'   => $static_tags,
            'dynamic_tags'  => $dynamic_tags,
        ];

        GoodConnect_GF::save_form_config( $form_id, $config );
        wp_send_json_success();
    }

    public static function ajax_save_elementor_config() {
        check_ajax_referer( 'goodconnect_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised', 403 );

        $form_name = sanitize_text_field( wp_unslash( $_POST['form_name'] ?? '' ) );
        if ( ! $form_name ) wp_send_json_error( 'Invalid form name' );

        $raw_map = (array) wp_unslash( $_POST['field_map'] ?? [] );
        $field_map = [];
        foreach ( $raw_map as $ghl_field => $elementor_field_id ) {
            $ghl_field        = sanitize_text_field( $ghl_field );
            $elementor_field_id = sanitize_text_field( $elementor_field_id );
            if ( $ghl_field && $elementor_field_id !== '' ) $field_map[ $ghl_field ] = $elementor_field_id;
        }

        $raw_custom = (array) wp_unslash( $_POST['custom_fields'] ?? [] );
        $custom_fields = [];
        foreach ( $raw_custom as $row ) {
            $key   = sanitize_text_field( $row['ghl_key']        ?? '' );
            $field = sanitize_text_field( $row['elementor_field'] ?? '' );
            if ( $key && $field ) $custom_fields[] = [ 'ghl_key' => $key, 'elementor_field' => $field ];
        }

        $raw_static = sanitize_text_field( wp_unslash( $_POST['static_tags'] ?? '' ) );
        $static_tags = array_values( array_filter( array_map( 'trim', explode( ',', $raw_static ) ) ) );

        $config = [
            'account_id'    => sanitize_text_field( wp_unslash( $_POST['account_id'] ?? '' ) ),
            'field_map'     => $field_map,
            'custom_fields' => $custom_fields,
            'static_tags'   => $static_tags,
            'dynamic_tags'  => [],
        ];

        GoodConnect_Elementor::save_form_config( $form_name, $config );
        wp_send_json_success();
    }

    public static function ajax_save_woo_settings() {
        check_ajax_referer( 'goodconnect_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised', 403 );

        $options                 = get_option( GoodConnect_Settings::OPTION_KEY, [] );
        $options['woo_enabled']  = ! empty( $_POST['woo_enabled'] ) ? '1' : '0'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- boolean coerce
        $options['woo_account_id'] = sanitize_text_field( wp_unslash( $_POST['woo_account_id'] ?? '' ) );
        update_option( GoodConnect_Settings::OPTION_KEY, $options );
        wp_send_json_success();
    }

    public static function ajax_clear_log() {
        check_ajax_referer( 'goodconnect_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised', 403 );
        GoodConnect_DB::clear_log();
        wp_send_json_success();
    }

    public static function ajax_fetch_ghl_custom_fields() {
        check_ajax_referer( 'goodconnect_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised', 403 );

        $account_id = sanitize_text_field( wp_unslash( $_POST['account_id'] ?? '' ) );
        $account    = $account_id
            ? GoodConnect_Settings::get_account_by_id( $account_id )
            : GoodConnect_Settings::get_default_account();

        if ( ! $account ) {
            wp_send_json_error( __( 'No GHL account found. Please save your account settings first.', 'good-connect' ) );
        }

        $client = new GoodConnect_GHL_Client( $account );
        $result = $client->get_custom_fields();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        // Normalise — GHL returns varying shapes depending on API version.
        $fields = [];
        foreach ( (array) $result as $field ) {
            $id   = $field['id']       ?? $field['fieldKey'] ?? '';
            $name = $field['name']     ?? $field['label']    ?? $id;
            $key  = $field['fieldKey'] ?? $field['id']       ?? '';
            if ( $id ) {
                $fields[] = [
                    'id'       => $id,
                    'name'     => $name,
                    'fieldKey' => $key,
                ];
            }
        }

        // Sort alphabetically by name.
        usort( $fields, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );

        wp_send_json_success( $fields );
    }

    public static function ajax_save_cf7_config() {
        check_ajax_referer( 'goodconnect_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised', 403 );

        $form_id = absint( wp_unslash( $_POST['form_id'] ?? 0 ) );
        if ( ! $form_id ) wp_send_json_error( 'Invalid form ID' );

        $raw_map = (array) wp_unslash( $_POST['field_map'] ?? [] );
        $field_map = [];
        foreach ( $raw_map as $ghl_field => $cf7_field ) {
            $ghl_field = sanitize_text_field( $ghl_field );
            $cf7_field = sanitize_text_field( $cf7_field );
            if ( $ghl_field && $cf7_field !== '' ) $field_map[ $ghl_field ] = $cf7_field;
        }

        $raw_custom  = (array) wp_unslash( $_POST['custom_fields'] ?? [] );
        $custom_fields = [];
        foreach ( $raw_custom as $row ) {
            $key   = sanitize_text_field( $row['ghl_key']   ?? '' );
            $field = sanitize_text_field( $row['cf7_field'] ?? '' );
            if ( $key && $field ) $custom_fields[] = [ 'ghl_key' => $key, 'cf7_field' => $field ];
        }

        $raw_static  = sanitize_text_field( wp_unslash( $_POST['static_tags'] ?? '' ) );
        $static_tags = array_values( array_filter( array_map( 'trim', explode( ',', $raw_static ) ) ) );

        $config = [
            'account_id'    => sanitize_text_field( wp_unslash( $_POST['account_id'] ?? '' ) ),
            'field_map'     => $field_map,
            'custom_fields' => $custom_fields,
            'static_tags'   => $static_tags,
            'dynamic_tags'  => [],
            'conditions'    => [ 'enabled' => false, 'operator' => 'AND', 'rules' => [] ],
        ];

        GoodConnect_CF7::save_form_config( $form_id, $config );
        wp_send_json_success();
    }

    public static function ajax_bulk_sync_start() {
        check_ajax_referer( 'goodconnect_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised', 403 );
        $account_id = sanitize_text_field( wp_unslash( $_POST['account_id'] ?? '' ) );
        $started    = GoodConnect_BulkSync::start( $account_id );
        if ( $started ) {
            wp_send_json_success( GoodConnect_BulkSync::get_progress() );
        } else {
            wp_send_json_error( 'Sync already running or no users found.' );
        }
    }

    public static function ajax_bulk_sync_cancel() {
        check_ajax_referer( 'goodconnect_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised', 403 );
        GoodConnect_BulkSync::cancel();
        wp_send_json_success();
    }

    public static function ajax_bulk_sync_progress() {
        check_ajax_referer( 'goodconnect_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised', 403 );
        wp_send_json_success( GoodConnect_BulkSync::get_progress() );
    }

    // -------------------------------------------------------------------------
    // GHL contact field registry
    // -------------------------------------------------------------------------

    public static function ghl_contact_fields(): array {
        return [
            'firstName'   => __( 'First Name', 'good-connect' ),
            'lastName'    => __( 'Last Name', 'good-connect' ),
            'email'       => __( 'Email', 'good-connect' ),
            'phone'       => __( 'Phone', 'good-connect' ),
            'address1'    => __( 'Address', 'good-connect' ),
            'city'        => __( 'City', 'good-connect' ),
            'state'       => __( 'State', 'good-connect' ),
            'postalCode'  => __( 'Postal Code', 'good-connect' ),
            'country'     => __( 'Country', 'good-connect' ),
            'companyName' => __( 'Company Name', 'good-connect' ),
            'website'     => __( 'Website', 'good-connect' ),
            'source'      => __( 'Source', 'good-connect' ),
        ];
    }
}
