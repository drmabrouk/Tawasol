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

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 */
class Tawasol {

	/** @var Tawasol_Loader $loader Orchestrates the hooks of the plugin. */
	protected $loader;

	/** @var string $plugin_name The unique identifier of this plugin. */
	protected $plugin_name;

	/** @var string $version The current version of the plugin. */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 */
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
		$this->define_shortcode_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {
		$plugin_dir = plugin_dir_path( dirname( __FILE__ ) );

		// Core
		require_once $plugin_dir . 'includes/class-tawasol-loader.php';
		require_once $plugin_dir . 'includes/class-tawasol-i18n.php';
		require_once $plugin_dir . 'includes/class-tawasol-shortcodes.php';

		// Modules
		require_once $plugin_dir . 'modules/auth/class-tawasol-auth.php';
		require_once $plugin_dir . 'modules/utils/class-tawasol-encryption.php';
		require_once $plugin_dir . 'modules/utils/class-tawasol-queue.php';
		require_once $plugin_dir . 'modules/chat/class-tawasol-chat-engine.php';

		// Database
		require_once $plugin_dir . 'database/queries/class-tawasol-db-queries.php';
		require_once $plugin_dir . 'database/transactions/class-tawasol-db-transactions.php';

		// UI / API
		require_once $plugin_dir . 'admin/class-tawasol-admin.php';
		require_once $plugin_dir . 'public/class-tawasol-public.php';
		require_once $plugin_dir . 'api/v1/class-tawasol-api-v1.php';

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
		$this->loader->add_filter( 'template_include', $plugin_public, 'override_template' );
		$this->loader->add_action( 'template_redirect', $plugin_public, 'handle_redirects' );
	}

	private function define_api_hooks() {
		$plugin_api = new Tawasol_API_V1( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'rest_api_init', $plugin_api, 'register_routes' );

		$queue = new Tawasol_Queue();
		$this->loader->add_action( 'tawasol_process_queue', $queue, 'process_batch' );

		if ( ! wp_next_scheduled( 'tawasol_process_queue' ) ) {
			wp_schedule_event( time(), 'every_minute', 'tawasol_process_queue' );
		}
	}

	private function define_shortcode_hooks() {
		$plugin_shortcodes = new Tawasol_Shortcodes();
		$this->loader->add_action( 'init', $plugin_shortcodes, 'register_shortcodes' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
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
