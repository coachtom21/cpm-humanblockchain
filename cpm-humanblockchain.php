<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://https://codepixelzmedia.com/
 * @since             1.0.0
 * @package           Cpm_Humanblockchain
 *
 * @wordpress-plugin
 * Plugin Name:       CPM Humanblockchain
 * Plugin URI:        https://https://codepixelzmedia.com/
 * Description:       The NWP scan is not the transaction itself. It is the onboarding gateway that prepares a person to participate in the YAM JAM game.
The YAM-is-ON universal QR remains the separate buyer/seller pledge-confirmation tool for the actual $30 pledge and the $10.30 community value allocation.
So the rule is:
NWP = onboarding, identity, role readiness, print rights
YAM-is-ON = delivery confirmation, pledge allocation, ledger event

 * Version:           1.0.5
 * Author:            Codepixelz Media
 * Author URI:        https://https://codepixelzmedia.com//
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       cpm-humanblockchain
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'CPM_HUMANBLOCKCHAIN_VERSION', '1.0.5' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-cpm-humanblockchain-activator.php
 */
function activate_cpm_humanblockchain() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-cpm-humanblockchain-activator.php';
	Cpm_Humanblockchain_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-cpm-humanblockchain-deactivator.php
 */
function deactivate_cpm_humanblockchain() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-cpm-humanblockchain-deactivator.php';
	Cpm_Humanblockchain_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_cpm_humanblockchain' );
register_deactivation_hook( __FILE__, 'deactivate_cpm_humanblockchain' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-cpm-humanblockchain.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_cpm_humanblockchain() {

	$plugin = new Cpm_Humanblockchain();
	$plugin->run();

}
run_cpm_humanblockchain();
