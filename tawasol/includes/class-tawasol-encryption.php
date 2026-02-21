<?php

/**
 * Handle content encryption and decryption
 *
 * @package           Tawasol
 * @subpackage        Tawasol/includes
 */

class Tawasol_Encryption {

    /**
     * Encrypt content
     *
     * @param string $content
     * @return string
     */
    public static function encrypt( $content ) {
        if ( ! extension_loaded( 'openssl' ) ) {
            return $content; // Fallback if openssl not available
        }

        $key = self::get_key();
        $iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
        $iv = openssl_random_pseudo_bytes( $iv_length );

        $encrypted = openssl_encrypt( $content, 'aes-256-cbc', $key, 0, $iv );

        return base64_encode( $iv . $encrypted );
    }

    /**
     * Decrypt content
     *
     * @param string $encrypted_content
     * @return string
     */
    public static function decrypt( $encrypted_content ) {
        if ( ! extension_loaded( 'openssl' ) ) {
            return $encrypted_content;
        }

        $key = self::get_key();
        $data = base64_decode( $encrypted_content );
        $iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );

        $iv = substr( $data, 0, $iv_length );
        $encrypted = substr( $data, $iv_length );

        return openssl_decrypt( $encrypted, 'aes-256-cbc', $key, 0, $iv );
    }

    /**
     * Get encryption key
     *
     * @return string
     */
    private static function get_key() {
        if ( defined( 'AUTH_KEY' ) ) {
            return substr( hash( 'sha256', AUTH_KEY ), 0, 32 );
        }
        return substr( hash( 'sha256', 'tawasol-default-key' ), 0, 32 );
    }
}
