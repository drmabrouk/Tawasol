<?php

/**
 * Handle authentication and PIN logic
 *
 * @package           Tawasol
 * @subpackage        Tawasol/includes
 */

class Tawasol_Auth {

    /**
     * Authenticate user with PIN
     *
     * @param string $identifier Email or Phone
     * @param string $pin 6-digit PIN
     * @return WP_User|WP_Error
     */
    public static function authenticate( $identifier, $pin ) {
        // Find user by email or phone (phone stored in meta)
        $user = get_user_by( 'email', $identifier );

        if ( ! $user ) {
            // Try by phone number
            $users = get_users( array(
                'meta_key'   => 'tawasol_phone',
                'meta_value' => $identifier,
                'number'     => 1,
            ) );
            if ( ! empty( $users ) ) {
                $user = $users[0];
            }
        }

        if ( ! $user ) {
            return new WP_Error( 'invalid_user', __( 'User not found.', 'tawasol' ) );
        }

        // Verify PIN (using a separate meta field to avoid overwriting WP password)
        $hashed_pin = get_user_meta( $user->ID, 'tawasol_pin_hash', true );

        if ( ! $hashed_pin || ! wp_check_password( $pin, $hashed_pin, $user->ID ) ) {
            return new WP_Error( 'invalid_pin', __( 'Invalid PIN.', 'tawasol' ) );
        }

        return $user;
    }

    /**
     * Set user PIN
     *
     * @param int    $user_id
     * @param string $pin 6-digit PIN
     */
    public static function set_pin( $user_id, $pin ) {
        $hashed_pin = wp_hash_password( $pin );
        update_user_meta( $user_id, 'tawasol_pin_hash', $hashed_pin );
    }

    /**
     * Log in user
     *
     * @param WP_User $user
     */
    public static function login_user( $user ) {
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID );
        do_action( 'wp_login', $user->user_login, $user );
    }
}
