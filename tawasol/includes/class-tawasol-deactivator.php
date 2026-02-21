<?php

/**
 * Fired during plugin deactivation
 *
 * @package           Tawasol
 * @subpackage        Tawasol/includes
 */

class Tawasol_Deactivator {

	public static function deactivate() {
        // Typically we don't drop tables on deactivation to avoid data loss.
        // But we could remove capabilities if needed.
	}

}
