<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_Webhook_Events {

    /**
     * Dispatch an inbound webhook event to the appropriate handler.
     *
     * @param string $event_type  The event type string from the payload.
     * @param array  $payload     The full decoded payload.
     * @return array  Response data to return to GHL (can be empty).
     */
    public static function dispatch( string $event_type, array $payload ): array {
        $rules = get_option( 'goodconnect_webhook_rules', [] );

        $response = [];
        $handled  = false;

        foreach ( (array) $rules as $rule ) {
            if ( ( $rule['event_type'] ?? '' ) !== $event_type ) continue;

            $action_type  = $rule['action_type']  ?? '';
            $extra_config = is_array( $rule['extra_config'] ?? null )
                ? $rule['extra_config']
                : ( json_decode( $rule['extra_config'] ?? '{}', true ) ?: [] );

            switch ( $action_type ) {
                case 'generate_magic_link':
                    $email  = sanitize_email( $payload['email'] ?? $payload['contact']['email'] ?? '' );
                    $result = GoodConnect_Magic_Link::generate( $email, $payload['contact']['id'] ?? '' );
                    if ( ! is_wp_error( $result ) ) {
                        $response['magic_link_url'] = $result;
                    }
                    break;

                case 'create_wp_user':
                    GoodConnect_User_Provision::provision( $payload, $extra_config );
                    break;

                case 'update_user_meta':
                    $email    = sanitize_email( $payload['email'] ?? $payload['contact']['email'] ?? '' );
                    $user     = get_user_by( 'email', $email );
                    $meta_key = sanitize_key( $extra_config['meta_key'] ?? '' );
                    $field    = sanitize_text_field( $extra_config['payload_field'] ?? '' );
                    if ( $user && $meta_key && $field ) {
                        update_user_meta( $user->ID, $meta_key, sanitize_text_field( $payload[ $field ] ?? '' ) );
                    }
                    break;

                case 'add_user_role':
                    $email = sanitize_email( $payload['email'] ?? $payload['contact']['email'] ?? '' );
                    $user  = get_user_by( 'email', $email );
                    $role  = sanitize_key( $extra_config['role'] ?? '' );
                    $allowed_roles = (array) get_option( 'goodconnect_allowed_roles', [ 'subscriber', 'customer' ] );
                    if ( $user && $role && in_array( $role, $allowed_roles, true ) ) {
                        $user->add_role( $role );
                    }
                    break;

                case 'remove_user_role':
                    $email = sanitize_email( $payload['email'] ?? $payload['contact']['email'] ?? '' );
                    $user  = get_user_by( 'email', $email );
                    $role  = sanitize_key( $extra_config['role'] ?? '' );
                    $allowed_roles = (array) get_option( 'goodconnect_allowed_roles', [ 'subscriber', 'customer' ] );
                    if ( $user && $role && in_array( $role, $allowed_roles, true ) ) {
                        $user->remove_role( $role );
                    }
                    break;
            }

            $handled = true;
        }

        if ( ! $handled ) {
            // Allow hook-based extension.
            do_action( 'goodconnect_webhook_event', $event_type, $payload );
        }

        return $response;
    }
}
