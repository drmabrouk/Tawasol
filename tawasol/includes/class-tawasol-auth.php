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
        // Find user strictly by username
        $user = get_user_by( 'login', $identifier );

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
     * Log in user and record session
     *
     * @param WP_User $user
     */
    public static function login_user( $user ) {
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID );

        // Record session
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'tawasol_sessions';
        $wpdb->insert( $table_sessions, array(
            'user_id'       => $user->ID,
            'session_token' => wp_generate_password( 64, false ),
            'ip_address'    => $_SERVER['REMOTE_ADDR'],
            'user_agent'    => $_SERVER['HTTP_USER_AGENT'],
        ) );

        do_action( 'wp_login', $user->user_login, $user );
    }
}
