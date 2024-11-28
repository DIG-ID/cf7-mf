<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://dig.id
 * @since             1.0.0
 * @package           Cf7_Mf
 *
 * @wordpress-plugin
 * Plugin Name:       CF7 Multifile
 * Plugin URI:        https://#
 * Description:       Extend the Contact Form 7 to be able to handle Multifile input field.
 * Version:           1.0.0
 * Author:            dig.id
 * Author URI:        https://dig.id/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       cf7-mf
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
define( 'CF7_MF_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-cf7-mf-activator.php
 */
function activate_cf7_mf() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-cf7-mf-activator.php';
	Cf7_Mf_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-cf7-mf-deactivator.php
 */
function deactivate_cf7_mf() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-cf7-mf-deactivator.php';
	Cf7_Mf_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_cf7_mf' );
register_deactivation_hook( __FILE__, 'deactivate_cf7_mf' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-cf7-mf.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_cf7_mf() {

	$plugin = new Cf7_Mf();
	$plugin->run();

}
run_cf7_mf();
