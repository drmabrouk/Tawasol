<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @package           Tawasol
 * @subpackage        Tawasol/includes
 */

class Tawasol {

	protected $loader;
	protected $plugin_name;
	protected $version;

	public function __construct() {
		if ( defined( 'TAWASOL_VERSION' ) ) {
			$this->version = TAWASOL_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'tawasol';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_api_hooks();
	}

	private function load_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-tawasol-loader.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-tawasol-i18n.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-tawasol-auth.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-tawasol-encryption.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-tawasol-admin.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-tawasol-public.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'api/class-tawasol-api.php';

		$this->loader = new Tawasol_Loader();
	}

	private function set_locale() {
		$plugin_i18n = new Tawasol_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	private function define_admin_hooks() {
		$plugin_admin = new Tawasol_Admin( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_menu_page' );
	}

	private function define_public_hooks() {
		$plugin_public = new Tawasol_Public( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
	}

	private function define_api_hooks() {
		$plugin_api = new Tawasol_API( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'rest_api_init', $plugin_api, 'register_routes' );
	}

	public function run() {
		$this->loader->run();
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_loader() {
		return $this->loader;
	}

	public function get_version() {
		return $this->version;
	}

}
