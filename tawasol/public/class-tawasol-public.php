<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @package           Tawasol
 * @subpackage        Tawasol/public
 */

class Tawasol_Public {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( dirname( __FILE__ ) ) . 'modules/ui/styles/tawasol-ui.css', array(), $this->version, 'all' );
	}

	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( dirname( __FILE__ ) ) . 'modules/ui/scripts/tawasol-ui.js', array( 'jquery' ), $this->version, false );

        // Localize script for AJAX/REST
        wp_localize_script( $this->plugin_name, 'tawasolVars', array(
            'restUrl' => esc_url_raw( rest_url( 'tawasol/v1' ) ),
            'homeUrl' => esc_url( home_url() ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'userId'  => get_current_user_id(),
            'i18n'    => array(
                'search'          => __( 'Search chats...', 'tawasol' ),
                'typeMessage'     => __( 'Type a message...', 'tawasol' ),
                'send'            => __( 'Send', 'tawasol' ),
                'welcome'         => __( 'Welcome to Tawasol', 'tawasol' ),
                'login'           => __( 'Login', 'tawasol' ),
                'register'        => __( 'Register', 'tawasol' ),
                'continue'        => __( 'Continue', 'tawasol' ),
                'username'        => __( 'Username', 'tawasol' ),
                'email'           => __( 'Email', 'tawasol' ),
                'phone'           => __( 'Phone', 'tawasol' ),
                'pin'             => __( '6-digit PIN', 'tawasol' ),
                'selectConv'      => __( 'Select a conversation', 'tawasol' ),
                'welcomeBack'     => __( 'Welcome back, %s', 'tawasol' ),
                'usernameTaken'   => __( 'Taken. Suggestions: %s', 'tawasol' ),
                'authFailed'      => __( 'Invalid username or PIN.', 'tawasol' ),
                'otpRequired'     => __( 'OTP verification required.', 'tawasol' ),
                'otpSent'         => __( 'OTP sent to your registered device.', 'tawasol' ),
                'retry'           => __( 'Retrying...', 'tawasol' ),
            ),
            'iconUrl' => plugins_url( 'modules/ui/assets/icon.png', dirname( __FILE__ ) ),
        ) );
	}

    /**
     * Override page template for Tawasol pages
     *
     * @param string $template
     * @return string
     */
    public function override_template( $template ) {
        if ( is_page( 'tawasol-login' ) || is_page( 'tawasol-chat' ) ) {
            $new_template = plugin_dir_path( dirname( __FILE__ ) ) . 'modules/ui/templates/tawasol-full-screen-template.php';
            if ( file_exists( $new_template ) ) {
                return $new_template;
            }
        }
        return $template;
    }

    /**
     * Handle redirections between login and chat pages
     */
    public function handle_redirects() {
        if ( is_page( 'tawasol-login' ) && is_user_logged_in() ) {
            wp_redirect( get_permalink( get_page_by_path( 'tawasol-chat' ) ) );
            exit;
        }

        if ( is_page( 'tawasol-chat' ) && ! is_user_logged_in() ) {
            wp_redirect( get_permalink( get_page_by_path( 'tawasol-login' ) ) );
            exit;
        }
    }

}
