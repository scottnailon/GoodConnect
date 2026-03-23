<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Renders the Webhooks admin tab and handles AJAX saves.
 * Registered by GoodConnect_Admin — this class provides the render method only.
 */
class GoodConnect_Webhook_Admin {

    public static function init() {
        add_action( 'wp_ajax_goodconnect_save_webhook_rules',  [ __CLASS__, 'ajax_save_rules' ] );
        add_action( 'wp_ajax_goodconnect_regenerate_webhook_secret', [ __CLASS__, 'ajax_regenerate_secret' ] );
        add_action( 'wp_ajax_goodconnect_save_protection_settings', [ __CLASS__, 'ajax_save_protection_settings' ] );
    }

    public static function render_webhooks_tab() {
        $webhook_url = GoodConnect_Webhook_Receiver::get_webhook_url();
        $rules       = get_option( 'goodconnect_webhook_rules', [] );
        $accounts    = GoodConnect_Settings::get_accounts();

        $event_types = [
            'generate_magic_link' => __( 'Generate Magic Link', 'good-connect' ),
            'create_wp_user'      => __( 'Create WordPress User', 'good-connect' ),
            'update_user_meta'    => __( 'Update User Meta', 'good-connect' ),
            'add_user_role'       => __( 'Add User Role', 'good-connect' ),
            'remove_user_role'    => __( 'Remove User Role', 'good-connect' ),
        ];
        ?>
        <div class="goodconnect-card">
            <h3 class="goodconnect-card-title"><?php esc_html_e( 'Inbound Webhook URL', 'good-connect' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Copy this URL into GoHighLevel → Automations → Webhook action to send events to WordPress.', 'good-connect' ); ?></p>
            <?php if ( ! is_ssl() ) : ?>
                <div class="notice notice-warning inline" style="margin:0 0 12px;">
                    <p><?php esc_html_e( 'Your site is not using HTTPS. Webhook tokens in URLs can leak over HTTP. Enable SSL before using webhooks in production.', 'good-connect' ); ?></p>
                </div>
            <?php endif; ?>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="text" id="gc-webhook-url" value="<?php echo esc_attr( $webhook_url ); ?>"
                       class="regular-text" readonly style="flex:1;font-family:monospace;font-size:12px;" />
                <button type="button" class="button" id="gc-webhook-url-copy">
                    <?php esc_html_e( 'Copy', 'good-connect' ); ?>
                </button>
                <button type="button" class="button goodconnect-regenerate-secret">
                    <?php esc_html_e( 'Regenerate Secret', 'good-connect' ); ?>
                </button>
            </div>
        </div>

        <div class="goodconnect-card">
            <h3 class="goodconnect-card-title"><?php esc_html_e( 'Event Rules', 'good-connect' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Configure what happens when GoHighLevel sends each event type.', 'good-connect' ); ?></p>

            <div id="goodconnect-webhook-rules">
                <?php foreach ( (array) $rules as $i => $rule ) :
                    self::render_rule_row( $i, $rule, $event_types, $accounts );
                endforeach; ?>
            </div>

            <button type="button" class="button goodconnect-add-webhook-rule" style="margin-top:12px;">
                + <?php esc_html_e( 'Add Rule', 'good-connect' ); ?>
            </button>

            <div class="goodconnect-card-footer">
                <button type="button" class="button button-primary goodconnect-save-webhook-rules">
                    <?php esc_html_e( 'Save Rules', 'good-connect' ); ?>
                </button>
                <span class="goodconnect-save-status"></span>
            </div>
        </div>

        <template id="goodconnect-webhook-rule-template">
            <?php self::render_rule_row( '__IDX__', [], $event_types, $accounts ); ?>
        </template>

        <div class="goodconnect-card">
            <h3 class="goodconnect-card-title"><?php esc_html_e( 'Allowed Roles', 'good-connect' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Roles that can be assigned or removed via webhook. Never trust role names from external payloads — only these roles are permitted.', 'good-connect' ); ?></p>
            <?php
            $allowed_roles = (array) get_option( 'goodconnect_allowed_roles', [ 'subscriber', 'customer' ] );
            $wp_roles      = wp_roles()->get_names();
            foreach ( $wp_roles as $role_key => $role_name ) :
                if ( in_array( $role_key, [ 'administrator', 'editor' ], true ) ) continue; // Never allow these.
            ?>
                <label style="display:inline-flex;align-items:center;gap:6px;margin-right:16px;">
                    <input type="checkbox" name="gc_allowed_roles[]"
                           value="<?php echo esc_attr( $role_key ); ?>"
                           <?php checked( in_array( $role_key, $allowed_roles, true ) ); ?> />
                    <?php echo esc_html( $role_name ); ?>
                </label>
            <?php endforeach; ?>
            <div class="goodconnect-card-footer" style="margin-top:12px;">
                <button type="button" class="button button-primary goodconnect-save-allowed-roles">
                    <?php esc_html_e( 'Save', 'good-connect' ); ?>
                </button>
                <span class="goodconnect-save-status"></span>
            </div>
        </div>

        <?php
    }

    private static function render_rule_row( $index, array $rule, array $event_types, array $accounts ) {
        $event  = $rule['event_type']  ?? '';
        $action = $rule['action_type'] ?? '';
        $extra  = is_array( $rule['extra_config'] ?? null )
            ? wp_json_encode( $rule['extra_config'] )
            : ( $rule['extra_config'] ?? '' );
        ?>
        <div class="goodconnect-webhook-rule-row goodconnect-card" style="margin-bottom:12px;">
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start;">
                <div>
                    <label class="goodconnect-section-label"><?php esc_html_e( 'When GHL sends event', 'good-connect' ); ?></label>
                    <input type="text" class="gc-rule-event regular-text"
                           placeholder="e.g. ContactCreated"
                           value="<?php echo esc_attr( $event ); ?>" />
                </div>
                <div>
                    <label class="goodconnect-section-label"><?php esc_html_e( 'Do this action', 'good-connect' ); ?></label>
                    <select class="gc-rule-action">
                        <?php foreach ( $event_types as $key => $label ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $action, $key ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1;min-width:200px;">
                    <label class="goodconnect-section-label"><?php esc_html_e( 'Extra config (JSON)', 'good-connect' ); ?></label>
                    <textarea class="gc-rule-extra" rows="2" style="width:100%;font-family:monospace;font-size:12px;"
                              placeholder='{"role":"subscriber"}'><?php echo esc_textarea( $extra ); ?></textarea>
                </div>
                <div style="padding-top:22px;">
                    <button type="button" class="button goodconnect-remove-webhook-rule"><?php esc_html_e( 'Remove', 'good-connect' ); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    public static function ajax_save_rules() {
        check_ajax_referer( 'goodconnect_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised', 403 );

        $raw   = (array) wp_unslash( $_POST['rules'] ?? [] );
        $rules = [];
        foreach ( $raw as $row ) {
            $event  = sanitize_text_field( $row['event_type']  ?? '' );
            $action = sanitize_key(        $row['action_type'] ?? '' );
            $extra  = sanitize_textarea_field( $row['extra_config'] ?? '' );
            if ( $event && $action ) {
                $rules[] = [
                    'event_type'   => $event,
                    'action_type'  => $action,
                    'extra_config' => $extra,
                ];
            }
        }
        update_option( 'goodconnect_webhook_rules', $rules );
        wp_send_json_success();
    }

    public static function ajax_regenerate_secret() {
        check_ajax_referer( 'goodconnect_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised', 403 );
        GoodConnect_Webhook_Receiver::regenerate_secret();
        wp_send_json_success( [ 'url' => GoodConnect_Webhook_Receiver::get_webhook_url() ] );
    }

    public static function ajax_save_protection_settings() {
        check_ajax_referer( 'goodconnect_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised', 403 );

        // Allowed roles — only permit non-admin roles.
        $forbidden = [ 'administrator', 'editor' ];
        $raw_roles = (array) wp_unslash( $_POST['allowed_roles'] ?? [] );
        $clean     = array_values( array_filter( array_map( 'sanitize_key', $raw_roles ), function( $r ) use ( $forbidden ) {
            return ! in_array( $r, $forbidden, true );
        } ) );
        update_option( 'goodconnect_allowed_roles', $clean );

        // Denied page.
        if ( isset( $_POST['denied_page_id'] ) ) {
            update_option( 'goodconnect_protection_denied_page', absint( wp_unslash( $_POST['denied_page_id'] ) ) );
        }

        wp_send_json_success();
    }
}
