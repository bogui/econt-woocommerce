<?php

if (!defined( 'ABSPATH')) {
    die;
}

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Deliver_With_Econt
 * @subpackage Deliver_With_Econt/includes
 * @author     Ryan Hungate <ryan@vextras.com>
 */
class Delivery_With_Econt_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 * 
	 */
	public static function activate() {

		// create the queue tables because we need them for the sync jobs.
		static::create_queue_tables();

		// update the settings so we have them for use.
        $saved_options = get_option('delivery-with-econt', false);

        // if we haven't saved options previously, we will need to create the site id and update base options
        if (empty($saved_options)) {
            update_option('delivery-with-econt', array());
            // only do this if the option has never been set before.
            if (!is_multisite()) {
                add_option('delivery_with_econt_plugin_do_activation_redirect', true);
            }
        }

        // if we haven't saved the store id yet.
        $saved_store_id = get_option('delivery-with-econt-store_id', false);
        if (empty($saved_store_id)) {
            // add a store id flag which will be a random hash
            update_option('delivery-with-econt-store_id', uniqid(), 'yes');
        }
	}

	/**
	 * Create the queue tables in the DB so we can use it for syncing.
	 */
	public static function create_queue_tables()
	{
		// Change 22.07.19 - Customer requirements
		// $admin_path = str_replace( get_bloginfo( 'url' ) . '/', ABSPATH, get_admin_url() );
		$admin_path = get_home_path() . 'wp-admin' . DIRECTORY_SEPARATOR;

		require_once( $admin_path . 'includes/upgrade.php' );

		// set the delivery wuth econt version at the time of install
		update_site_option('delivery_with_econt_version', static::econt_environment_variables()->version);
	}

	/**
	 * @return object
	 */
	public static function econt_environment_variables() {
		global $wp_version;

		$o = get_option('delivery-with-econt', false);

		return (object) array(
			'repo' => 'master',
			'environment' => 'production', // staging or production
			'version' => '2.1.4',
			'php_version' => phpversion(),
			'wp_version' => ( empty( $wp_version ) ? 'Unknown' : $wp_version ),
			'wc_version' => function_exists( 'WC' ) ? WC()->version : null,
			'logging' => ($o && is_array($o) && isset($o['econt_logging'])) ? $o['econt_logging'] : 'debug',
		);
	}
}
