<?php

/**
 * Handle plugin shortcodes
 *
 * @package           Tawasol
 * @subpackage        Tawasol/includes
 */

class Tawasol_Shortcodes {

    /**
     * Register all shortcodes
     */
    public function register_shortcodes() {
        add_shortcode( 'tawasol_login', array( $this, 'render_login_page' ) );
        add_shortcode( 'tawasol_chat', array( $this, 'render_chat_page' ) );
    }

    /**
     * Render the login page content
     */
    public function render_login_page() {
        return '<div id="tawasol-login-page-trigger" class="tawasol-page-container">
                    <p>' . __( 'Redirecting to secure login...', 'tawasol' ) . '</p>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            if (typeof window.tawasolOpenChat === "function") {
                                window.tawasolOpenChat();
                            }
                        });
                    </script>
                </div>';
    }

    /**
     * Render the chat page content
     */
    public function render_chat_page() {
        if ( ! is_user_logged_in() ) {
            $login_url = get_permalink( get_page_by_path( 'tawasol-login' ) );
            return '<script>window.location.href = "' . esc_url( $login_url ) . '";</script>';
        }

        return '<div id="tawasol-chat-page-trigger" class="tawasol-page-container">
                    <p>' . __( 'Opening your professional chat...', 'tawasol' ) . '</p>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            if (typeof window.tawasolOpenChat === "function") {
                                window.tawasolOpenChat();
                            }
                        });
                    </script>
                </div>';
    }
}
