<?php

/**
 * Define the internationalization functionality
 *
 * @package           Tawasol
 * @subpackage        Tawasol/includes
 */

class Tawasol_i18n {

	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'tawasol',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}

}
