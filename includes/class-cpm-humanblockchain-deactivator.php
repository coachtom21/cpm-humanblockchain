<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://https://codepixelzmedia.com/
 * @since      1.0.0
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/includes
 * @author     Codepixelz Media <dev@codepixelzmedia.com.np>
 */
class Cpm_Humanblockchain_Deactivator {

	/**
	 * Fired during plugin deactivation.
	 * Tables (e.g. wp_nwp_devices) are NOT removed — data is preserved.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		// Intentionally empty: preserve device registration and other data.
	}

}
