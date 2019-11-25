<?php

// If this file is called directly, abort.
if ( !defined( 'WPINC' ) ) {
    die;
}

$delivery_with_econt_spl_autoloader = true;

spl_autoload_register( function( $class ) {
    $classes = array(
        // includes root
        'Delivery_With_Econt' => 'includes/class-delivery-with-econt.php',
        'Delivery_With_Econt_Options' => 'includes/class-delivery-with-econt-options.php',
        'Delivery_With_Econt_Shipping' => 'includes/class-delivery-with-econt-shipping.php',
        'Delivery_With_Econt_Activator' => 'includes/class-delivery-with-econt-activator.php',
        
        // includes admin/
        'Delivery_With_Econt_Admin' => 'includes/admin/class-delivery-with-econt-admin.php',   

        // Helper functions
        'Delivery_With_Econt_Helper' => 'helpers.php',
        
    );

    // if the file exists, require it
    $path = plugin_dir_path( __FILE__ );
    if ( array_key_exists( $class, $classes ) && file_exists( $path.$classes[$class] ) ) {
        require $path.$classes[$class];
    }
});

/**
 * Returns the main instance of DWEH.
 *
 * @since  1.0
 * @return Delivery_With_Econt_Helper
 */
function DWEH() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return Delivery_With_Econt_Helper::instance();
}

function add_econt_service_url_meta_tag() {
    $service_url ='<meta name="econt-service-url" content="' . DWEH()->get_service_url() . '" >';
    echo $service_url;

    $secret_key ='<meta name="econt-private-key" content="' . DWEH()->get_private_key( true ) . '" >';
    echo $secret_key;
}

add_action( 'admin_head', 'add_econt_service_url_meta_tag' );

add_action('update_option_delivery_with_econt_settings', function( $old_value, $new_value, $option_name ) {
    $status = DWEH()->check_econt_configuration( $new_value );

    if( is_array( $status ) ) {
        $error_message = $status['message'] . "\r\n Are you using demo service?";
        add_settings_error( 'econt_settings_error', 'error', $error_message );
    }
}, 10, 3);

// Woocommerce stuff

/**
 * Add Econt as delivery method
 * 
 * @param array $methods All shipping methods
 * 
 * @return array $methods All shipping methods including Econt
 */
function add_econt_shipping_method( $methods )
{
    $methods['delivery_with_econt'] = Delivery_With_Econt_Shipping::class;
    
    return $methods;
}

add_filter( 'woocommerce_shipping_methods', 'add_econt_shipping_method' );

/**
 * Initialize the shipping method
 * 
 * @return object Delivery_With_Econt_Shipping
 */
function econt_shipping_method_init()
{
    return new Delivery_With_Econt_Shipping();
}

add_action( 'woocommerce_shipping_init', 'econt_shipping_method_init' );

/**
 * Force woocommerce to recalculate the shipping
 * 
 */
function update_order_review( $array )
{        
    $packages = WC()->cart->get_shipping_packages();

    foreach ($packages as $key => $value) {
        $shipping_session = "shipping_for_package_$key";
        unset(WC()->session->$shipping_session);
    }

    WC()->cart->calculate_shipping();
    return;
}

add_action( 'woocommerce_checkout_update_order_review', 'update_order_review', 1, 2 );

// Ajax   


/** @todo Remove */
// /**
//  * retrieve and save those extra pieces of information
//  * 
//  * @param string $order_id
//  * @param array $posted
//  */
// function delivery_with_econt_save_extra_checkout_fields( $order_id, $posted ){
//     // don't forget appropriate sanitization if you are using a different field type
//     if( isset( $_COOKIE['econt_customer_info_id'] ) ) {
//         update_post_meta( $order_id, '_customer_info_id', $_COOKIE['econt_customer_info_id'] );
//     }
// }

// add_action( 'woocommerce_checkout_update_order_meta', 'delivery_with_econt_save_extra_checkout_fields', 10, 2 );
/** end todo */

/**
 * Generate order iframe frontend checkout
 */
function delivery_with_econt_get_order_info() {    
    if ( ! check_ajax_referer( 'delivery-with-econt-security-nonce', 'security' ) ) {
        wp_send_json_error( 'Invalid security token sent.' );
        wp_die();
    }    
    Delivery_With_Econt_Shipping::get_order_info();    
}

add_action( 'wp_ajax_woocommerce_delivery_with_econt_get_orderinfo', 'delivery_with_econt_get_order_info', 10 );
add_action( 'wp_ajax_nopriv_woocommerce_delivery_with_econt_get_orderinfo', 'delivery_with_econt_get_order_info', 10 );

// end

/**
 * Delivery with Econt checkout form button renderer
 */
add_action( 'woocommerce_after_shipping_rate', 'delivery_with_econt_render_form_button' );

function delivery_with_econt_render_form_button( $checkout )
{    
    Delivery_With_Econt_Shipping::render_form_button( $checkout );
}

/**
 * Delivery with Econt checkout form modal renderer
 */
add_action( 'woocommerce_after_checkout_form', 'delivery_with_econt_render_form_modal' );

function delivery_with_econt_render_form_modal( $checkout )
{    
    Delivery_With_Econt_Shipping::render_form_modal( $checkout );
}

add_action('woocommerce_before_checkout_form', 'delivery_with_econt_enque_scripts_and_styles');

function delivery_with_econt_enque_scripts_and_styles()
{
    wp_enqueue_style( 'delivery_with_econt_calculate_shipping', plugin_dir_url(__FILE__) . 'public/css/delivery-with-econt-checkout.css', [], false );
    wp_enqueue_script( 'delivery_with_econt_calculate_shipping', plugin_dir_url(__FILE__) . 'public/js/delivery-with-econt-checkout.js', ['jquery'], false, true );
    wp_localize_script( 'delivery_with_econt_calculate_shipping', 'delivery_with_econt_calculate_shipping_object', array('ajax_url' => admin_url('admin-ajax.php'), 'security'  => wp_create_nonce( 'delivery-with-econt-security-nonce' ))); 
}

// End Woocommerce stuff

// displays the page content for the Settings submenu
function dwe_settings_page() {
    $ops = new Delivery_With_Econt_Options();
    
    $ops->create_admin_page();
}

// Hook for adding admin menus
add_action( 'admin_menu', 'delivery_with_econt_add_pages' );

function delivery_with_econt_add_pages() {
    // Add a new submenu under Settings:
    add_options_page(
        __( 'Econt Delivery','deliver-with-econt' ), 
        __( 'Econt Delivery','deliver-with-econt' ), 
        'manage_options', 
        'delivery-with-econt-settings', 
        'dwe_settings_page'
    );
}

add_action( 'admin_init', function() {
    $ops = new Delivery_With_Econt_Options();
    $ops->page_init();
} );

/**
 * @return bool
 */
function delivery_with_econt_check_woocommerce_plugin_status()
{
    // if you are using a custom folder name other than woocommerce just define the constant to TRUE
    if ( defined( "RUNNING_CUSTOM_WOOCOMMERCE" ) && RUNNING_CUSTOM_WOOCOMMERCE === true ) {
        return true;
    }
    // it the plugin is active, we're good.
    if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        return true;
    }
    if ( ! is_multisite() ) return false;
    $plugins = get_site_option( 'active_sitewide_plugins' );
    return isset( $plugins['woocommerce/woocommerce.php'] );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-mailchimp-woocommerce-activator.php
 */
function activate_delivery_with_econt() {
    // if we don't have woocommerce we need to display a horrible error message before the plugin is installed.
    if ( ! delivery_with_econt_check_woocommerce_plugin_status() ) {
        // Deactivate the plugin
        deactivate_plugins( __FILE__ );
        $error_message = __( 'The Delivery with Econt plugin requires the <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a> plugin to be active!', 'woocommerce' );
        wp_die( $error_message );
    }
    Delivery_With_Econt_Activator::activate();
}

/**
 * After pressing the Place Order button
 *   
 * Sync the shop order with Econt
 */

function delivery_with_econt_generate_order_service( $order_id )
{
    DWEH()->sync_order( $order_id );
}

add_action( 'woocommerce_checkout_order_processed', 'delivery_with_econt_generate_order_service',  1, 1  );

/**
 * Hook for adding column to the order list table
 */
function delivery_with_econt_add_waybill_column( $columns )
{    
    return Delivery_With_Econt_Admin::add_waybill_column( $columns );
}
add_filter( 'manage_edit-shop_order_columns', 'delivery_with_econt_add_waybill_column', 20 );

/**
 * Hook to fill the newly added column with data
 */
function delivery_with_econt_add_waybill_column_content( $column )
{
    return Delivery_With_Econt_Admin::add_waybill_column_content( $column );
}
add_action( 'manage_shop_order_posts_custom_column', 'delivery_with_econt_add_waybill_column_content' );

/**
 * Hook to update Econt service and recalculate the shipping
 */
function delivery_with_econt_update_order()
{
    check_ajax_referer( 'calc-totals', 'security' );

    if ( ! current_user_can( 'edit_shop_orders' ) || ! isset( $_POST['order_id'], $_POST['items'] ) ) {
      wp_die( -1 );
    }

    Delivery_With_Econt::update_order();
}

add_action( 'wp_ajax_woocommerce_calc_line_taxes', 'delivery_with_econt_update_order', 10 );

function delivery_with_econt_save_waybill_id()
{
    check_ajax_referer( 'woocommerce-preview-order', 'security' );

    if ( ! current_user_can( 'edit_shop_orders' ) ) {
      wp_die( -1 );
    }
    
    Delivery_With_Econt::save_waybill_id();
}
add_action( 'wp_ajax_delivery_with_econt_save_waybill_id', 'delivery_with_econt_save_waybill_id' );

function delivery_with_econt_get_order_details()
{
    DWEH()->econt_get_order_details();
}

add_action( 'wp_ajax_delivery_with_econt_get_order_details', 'delivery_with_econt_get_order_details' );

// Sync the Econt services with local values 
function delivery_with_econt_sync_order( $order_id ) 
{ 
    DWEH()->sync_order( $order_id );
}; 

add_action( 'woocommerce_process_shop_order_meta', 'delivery_with_econt_sync_order' ); 

function econt_sync_error() 
{
    $post_id = get_the_ID(); 
    $order = wc_get_order( $post_id );
    if ( ! $order ) return;
    $error = $order->get_meta( '_sync_error' );

    if ( $error != '' ) {        
        ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo $error; ?></p>
            </div>
        <?php
        delete_post_meta( $post_id, '_sync_error' );
    }
};

add_action( 'admin_notices', 'econt_sync_error' );

// Add section to display eather the button or the value of the waybill
add_action( 'woocommerce_after_order_itemmeta', 'delivery_with_econt_add_custom_html_to_order_details', 5, 1 );
 
function delivery_with_econt_add_custom_html_to_order_details( $product_id )
{
    Delivery_With_Econt_Admin::add_custom_html_to_order_details( $product_id );
}

function econt_delivery_load_plugin_textdomain() {
    load_plugin_textdomain( 'deliver-with-econt', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'econt_delivery_load_plugin_textdomain' );
