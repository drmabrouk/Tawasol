<?php
/**
 * Plugin Name:       Tawasol
 * Plugin URI:        https://example.com/tawasol
 * Description:       A high-level professional chat system with a full-screen interface, fully functional in Arabic with complete English support.
 * Version:           1.0.0
 * Author:            Jules
 * Author URI:        https://example.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       tawasol
 * Domain Path:       /languages
 *
 * @package           Tawasol
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 */
define( 'TAWASOL_VERSION', '1.0.0' );
define( 'TAWASOL_PATH', plugin_dir_path( __FILE__ ) );
define( 'TAWASOL_URL', plugin_dir_url( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-tawasol-activator.php
 */
function activate_tawasol() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-tawasol-activator.php';
	Tawasol_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-tawasol-deactivator.php
 */
function deactivate_tawasol() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-tawasol-deactivator.php';
	Tawasol_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_tawasol' );
register_deactivation_hook( __FILE__, 'deactivate_tawasol' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-tawasol.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file will
 * register the hooks with WordPress.
 *
 * @since    1.0.0
 */
function run_tawasol() {

	$plugin = new Tawasol();
	$plugin->run();

}
run_tawasol();
