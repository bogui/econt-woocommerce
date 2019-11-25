<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://kdconsult.eu
 * @since             1.0.0
 * @package           Deliver_With_Econt
 *
 * @wordpress-plugin
 * Plugin Name:       Econt Delivery
 * Plugin URI:        https://econt.com/developers/
 * Description:       Econt Shipping Module
 * Version:           2.2.5
 * Author:            Econt Express LTD.
 * Author URI:        https://econt.com/developers/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       deliver-with-econt
 * Domain Path:       /languages
 * Requires at least: 4.7
 * Tested up to: 5.8
 */

// If this file is called directly, abort.
if (!defined( 'WPINC')) {
    die;
}

if (!function_exists('dd')) {
    function dd($data)
    {
        ini_set("highlight.comment", "#969896; font-style: italic");
        ini_set("highlight.default", "#FFFFFF");
        ini_set("highlight.html", "#D16568");
        ini_set("highlight.keyword", "#7FA3BC; font-weight: bold");
        ini_set("highlight.string", "#F2C47E");
        $output = highlight_string("<?php\n\n" . var_export($data, true), true);
        echo "<div style=\"background-color: #1C1E21; padding: 1rem\">{$output}</div>";
        die();
    }
}

// Bootstrap the plugin
if (!isset($delivery_with_econt_spl_autoloader) || $delivery_with_econt_spl_autoloader === false) {
    include_once "bootstrap.php";
}

add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'econt_add_plugin_page_settings_link');
function econt_add_plugin_page_settings_link( $links ) {
	$links[] = '<a href="' .
		admin_url( 'options-general.php?page=delivery-with-econt-settings' ) .
		'">' . __('Settings') . '</a>';
	return $links;
}

register_activation_hook( __FILE__, 'activate_delivery_with_econt');
