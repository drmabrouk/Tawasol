<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @package           Tawasol
 * @subpackage        Tawasol/admin
 */

class Tawasol_Admin {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/tawasol-admin.css', array(), $this->version, 'all' );
	}

	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/tawasol-admin.js', array( 'jquery' ), $this->version, false );

        // Enqueue public scripts/styles for the chat overlay in admin
        wp_enqueue_style( $this->plugin_name . '-public', plugin_dir_url( dirname( __FILE__ ) ) . 'public/css/tawasol-public.css', array(), $this->version, 'all' );
        wp_enqueue_script( $this->plugin_name . '-public', plugin_dir_url( dirname( __FILE__ ) ) . 'public/js/tawasol-public.js', array( 'jquery' ), $this->version, false );

        wp_localize_script( $this->plugin_name . '-public', 'tawasolVars', array(
            'restUrl' => esc_url_raw( rest_url( 'tawasol/v1' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'userId'  => get_current_user_id(),
        ) );
	}

	public function add_menu_page() {
		add_menu_page(
			'Tawasol Chat',
			'Tawasol',
			'manage_tawasol',
			'tawasol',
			array( $this, 'display_plugin_admin_page' ),
			'dashicons-format-chat',
			25
		);
	}

	public function display_plugin_admin_page() {
		require_once plugin_dir_path( __FILE__ ) . 'partials/tawasol-admin-display.php';
	}

}
