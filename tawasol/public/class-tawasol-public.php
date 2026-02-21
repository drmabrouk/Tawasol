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
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/tawasol-public.css', array(), $this->version, 'all' );
	}

	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/tawasol-public.js', array( 'jquery' ), $this->version, false );

        // Localize script for AJAX/REST
        wp_localize_script( $this->plugin_name, 'tawasolVars', array(
            'restUrl' => esc_url_raw( rest_url( 'tawasol/v1' ) ),
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
            )
        ) );
	}

}
