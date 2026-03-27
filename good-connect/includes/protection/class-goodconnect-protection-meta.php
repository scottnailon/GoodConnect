<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_Protection_Meta {

    public static function init() {
        add_action( 'add_meta_boxes', [ __CLASS__, 'register_meta_box' ] );
        add_action( 'save_post',      [ __CLASS__, 'save_meta' ] );
    }

    public static function register_meta_box() {
        $post_types = array_merge(
            [ 'post', 'page' ],
            (array) get_option( 'goodconnect_protection_cpt_list', [] )
        );
        foreach ( $post_types as $cpt ) {
            add_meta_box(
                'goodconnect_protection',
                __( 'GoodConnect — Content Protection', 'good-connect' ),
                [ __CLASS__, 'render_meta_box' ],
                $cpt,
                'side',
                'default'
            );
        }
    }

    public static function render_meta_box( $post ) {
        wp_nonce_field( 'goodconnect_protection_meta', 'goodconnect_protection_nonce' );

        $required_tags  = get_post_meta( $post->ID, '_gc_required_tags', true );
        $denied_action  = get_post_meta( $post->ID, '_gc_denied_action',  true ) ?: 'redirect';
        $denied_message = get_post_meta( $post->ID, '_gc_denied_message', true );
        ?>
        <p>
            <label for="gc_required_tags" style="font-weight:600;display:block;margin-bottom:4px;">
                <?php esc_html_e( 'Required GHL Tags', 'good-connect' ); ?>
            </label>
            <input type="text" id="gc_required_tags" name="gc_required_tags"
                   value="<?php echo esc_attr( $required_tags ); ?>"
                   placeholder="<?php esc_attr_e( 'e.g. member, vip', 'good-connect' ); ?>"
                   style="width:100%" />
            <small><?php esc_html_e( 'Comma-separated. Leave blank for no restriction.', 'good-connect' ); ?></small>
        </p>
        <p>
            <label for="gc_denied_action" style="font-weight:600;display:block;margin-bottom:4px;">
                <?php esc_html_e( 'If access denied', 'good-connect' ); ?>
            </label>
            <select id="gc_denied_action" name="gc_denied_action" style="width:100%">
                <option value="redirect" <?php selected( $denied_action, 'redirect' ); ?>><?php esc_html_e( 'Redirect to page', 'good-connect' ); ?></option>
                <option value="message"  <?php selected( $denied_action, 'message' ); ?>><?php esc_html_e( 'Show message', 'good-connect' ); ?></option>
                <option value="hide"     <?php selected( $denied_action, 'hide' ); ?>><?php esc_html_e( 'Show 403 error', 'good-connect' ); ?></option>
            </select>
        </p>
        <p id="gc_denied_message_wrap" style="<?php echo esc_attr( $denied_action !== 'message' ? 'display:none' : '' ); ?>">
            <label for="gc_denied_message" style="font-weight:600;display:block;margin-bottom:4px;">
                <?php esc_html_e( 'Access denied message', 'good-connect' ); ?>
            </label>
            <textarea id="gc_denied_message" name="gc_denied_message" rows="3" style="width:100%"><?php echo esc_textarea( $denied_message ); ?></textarea>
        </p>
        <?php
    }

    public static function save_meta( $post_id ) {
        if ( ! isset( $_POST['goodconnect_protection_nonce'] ) ) return;
        if ( ! wp_verify_nonce( wp_unslash( $_POST['goodconnect_protection_nonce'] ), 'goodconnect_protection_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $tags    = sanitize_text_field( wp_unslash( $_POST['gc_required_tags']  ?? '' ) );
        $action  = sanitize_key(        wp_unslash( $_POST['gc_denied_action']  ?? 'redirect' ) );
        $message = sanitize_textarea_field( wp_unslash( $_POST['gc_denied_message'] ?? '' ) );

        update_post_meta( $post_id, '_gc_required_tags',  $tags );
        update_post_meta( $post_id, '_gc_denied_action',  $action );
        update_post_meta( $post_id, '_gc_denied_message', $message );
    }
}
