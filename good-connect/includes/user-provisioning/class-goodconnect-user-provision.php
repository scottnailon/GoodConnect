<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_User_Provision {

    public static function provision( array $payload, array $rule_config = [] ): int|WP_Error {
        $email      = sanitize_email( $payload['email'] ?? $payload['contact']['email'] ?? '' );
        $first_name = sanitize_text_field( $payload['firstName'] ?? $payload['contact']['firstName'] ?? $payload['first_name'] ?? '' );
        $last_name  = sanitize_text_field( $payload['lastName']  ?? $payload['contact']['lastName']  ?? $payload['last_name']  ?? '' );
        $phone      = sanitize_text_field( $payload['phone']     ?? $payload['contact']['phone']     ?? '' );
        $contact_id = sanitize_text_field( $payload['contact']['id'] ?? $payload['contactId'] ?? '' );

        if ( ! is_email( $email ) ) {
            return new WP_Error( 'goodconnect_invalid_email', __( 'Invalid email address in webhook payload.', 'good-connect' ) );
        }

        $existing_user = get_user_by( 'email', $email );
        $on_exists     = sanitize_key( $rule_config['on_exists'] ?? 'skip' );

        if ( $existing_user ) {
            if ( $on_exists === 'update' ) {
                $update_data = [ 'ID' => $existing_user->ID ];
                if ( $first_name ) $update_data['first_name'] = $first_name;
                if ( $last_name )  $update_data['last_name']  = $last_name;
                wp_update_user( $update_data );
                if ( $contact_id ) update_user_meta( $existing_user->ID, '_gc_contact_id', $contact_id );
                self::log( $existing_user->ID, $email, $contact_id, 'updated_existing_user' );
                return $existing_user->ID;
            }
            // 'skip' — return existing user ID without changes.
            self::log( $existing_user->ID, $email, $contact_id, 'skipped_existing_user' );
            return $existing_user->ID;
        }

        // Generate a unique login.
        $login_base = sanitize_user( strtolower( $email ), true );
        $login      = $login_base;
        $suffix     = 1;
        while ( username_exists( $login ) ) {
            $login = $login_base . '_' . $suffix;
            $suffix++;
        }

        $password = wp_generate_password( 16, true, true );
        $role     = sanitize_key( $rule_config['role'] ?? 'subscriber' );

        // Validate role.
        $allowed_roles = (array) get_option( 'goodconnect_allowed_roles', [ 'subscriber', 'customer' ] );
        if ( ! in_array( $role, $allowed_roles, true ) ) {
            $role = 'subscriber';
        }

        $user_id = wp_insert_user( [
            'user_login' => $login,
            'user_email' => $email,
            'user_pass'  => $password,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'role'       => $role,
        ] );

        if ( is_wp_error( $user_id ) ) {
            GoodConnect_DB::log( [
                'source'        => 'webhook',
                'form_name'     => 'User Provision',
                'contact_email' => $email,
                'action'        => 'create_wp_user',
                'success'       => 0,
                'ghl_contact_id'=> $contact_id,
                'error_message' => $user_id->get_error_message(),
            ] );
            return $user_id;
        }

        if ( $contact_id ) update_user_meta( $user_id, '_gc_contact_id', $contact_id );
        if ( $phone )      update_user_meta( $user_id, 'billing_phone', $phone );

        // Send welcome email.
        $send_email = ! empty( $rule_config['send_welcome_email'] );
        if ( $send_email ) {
            self::send_welcome_email( $email, $first_name, $password, $rule_config );
        } else {
            // Send the default WP new user email.
            wp_new_user_notification( $user_id, null, 'user' );
        }

        // Push WP user ID back to GHL.
        $ghl_user_id_field = sanitize_text_field( $rule_config['ghl_wp_user_id_field'] ?? '' );
        if ( $contact_id && $ghl_user_id_field ) {
            $account = GoodConnect_Settings::get_default_account();
            if ( $account ) {
                $client = new GoodConnect_GHL_Client( $account );
                $client->upsert_contact( [
                    'email'        => $email,
                    'customFields' => [ [ 'id' => $ghl_user_id_field, 'field_value' => (string) $user_id ] ],
                ] );
            }
        }

        self::log( $user_id, $email, $contact_id, 'create_wp_user' );
        return $user_id;
    }

    private static function send_welcome_email( string $email, string $first_name, string $password, array $config ): void {
        $subject_tpl = get_option( 'goodconnect_welcome_email_subject', __( 'Your new account at {site_name}', 'good-connect' ) );
        $body_tpl    = get_option( 'goodconnect_welcome_email_body',
            __( "Hi {first_name},\n\nYour account has been created.\n\nEmail: {email}\nPassword: {password}\n\nLog in at: {login_url}", 'good-connect' )
        );

        $replacements = [
            '{first_name}' => $first_name,
            '{email}'      => $email,
            '{password}'   => $password,
            '{site_name}'  => get_bloginfo( 'name' ),
            '{login_url}'  => wp_login_url(),
        ];

        $subject = str_replace( array_keys( $replacements ), array_values( $replacements ), $subject_tpl );
        $body    = str_replace( array_keys( $replacements ), array_values( $replacements ), $body_tpl );

        wp_mail( $email, $subject, $body );
    }

    private static function log( int $user_id, string $email, string $contact_id, string $action ): void {
        GoodConnect_DB::log( [
            'source'         => 'webhook',
            'form_name'      => 'User Provision',
            'contact_email'  => $email,
            'action'         => $action,
            'success'        => 1,
            'ghl_contact_id' => $contact_id,
        ] );
    }
}
