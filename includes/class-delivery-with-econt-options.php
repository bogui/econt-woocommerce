<?php

if (!defined( 'ABSPATH')) {
    die;
}

class Delivery_With_Econt_Options
{
    const SHIPPING_METHOD_NAME = 'Econt';

    const PLUGIN_NAME = 'delivery_with_econt';
    // Econt track url
    const TRACK_URL = 'https://www.econt.com/services/track-shipment/';

    const REAL_URL = 'https://delivery.econt.com/';
    const DEMO_URL = 'http://delivery.demo.econt.com/';

    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {        
        // add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        // add_action( 'admin_init', array( $this, 'page_init' ) );
    }    

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        add_options_page(
            __('Delivery With Econt','deliver-with-econt'), 
            __('Delivery With Econt','deliver-with-econt'), 
            'manage_options', 
            'delivery-with-econt-settings', 
            array( $this, 'create_admin_page' )
        );        
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        ?>
        <div class="wrap">
            <h1><?php _e('Econt Delivery Settings Page', 'delivery-with-econt') ?></h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'delivery_with_econt_settings_group' );
                do_settings_sections( 'delivery-with-econt-settings' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        // dd('a');
        $this->options = get_option( 'delivery_with_econt_settings' );
        register_setting(
            'delivery_with_econt_settings_group', // Option group
            'delivery_with_econt_settings', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'setting_section_id', // ID
            __('Econt Delivery Shop Settings', 'deliver-with-econt'), // Title
            array( $this, 'print_section_info' ), // Callback
            'delivery-with-econt-settings' // Page
        );  

        add_settings_field(
            'store_id', // ID
            __('ID Number', 'deliver-with-econt'), // Title 
            array( $this, 'id_number_callback' ), // Callback
            'delivery-with-econt-settings', // Page
            'setting_section_id' // Section
        );      

        add_settings_field(
            'private_key', 
            __('Private Key', 'deliver-with-econt'), 
            array( $this, 'title_callback' ), 
            'delivery-with-econt-settings', 
            'setting_section_id'
        );    
        
        add_settings_field(
            'demo_service',
            __('Use Econt Demo Service', 'deliver-with-econt'),
            array($this, 'demo_checkbox_callback'),
            'delivery-with-econt-settings',
            'setting_section_id'
        );  
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();
        if( isset( $input['store_id'] ) )
            $new_input['store_id'] = absint( $input['store_id'] );

        if( isset( $input['private_key'] ) )
            $new_input['private_key'] = sanitize_text_field( $input['private_key'] );
        if( isset( $input['demo_service'] ) )
            $new_input['demo_service'] = absint( $input['demo_service'] );
        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        _e('Enter your settings below:', 'deliver-with-econt');
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function id_number_callback()
    {        
        printf(
            '<input type="text" id="store_id" name="delivery_with_econt_settings[store_id]" value="%s" />',
            isset( $this->options['store_id'] ) ? esc_attr( $this->options['store_id']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function title_callback()
    {
        printf(
            '<input type="password" id="private_key" name="delivery_with_econt_settings[private_key]" value="%s" />',
            isset( $this->options['private_key'] ) ? esc_attr( $this->options['private_key']) : ''
        );
    }

    function demo_checkbox_callback()
    {
        printf(
            '<!-- Here we are comparing stored value with 1. Stored value is 1 if user checks the checkbox otherwise empty string. -->
            <input type="checkbox" name="delivery_with_econt_settings[demo_service]" value="1" %s />',
            checked(1, $this->options['demo_service'], false)
        );
    }

    /**
     * Econt tracking service
     * 
     * @return const TRACK_URL
     */
    public function get_track_url()
    {
        return self::TRACK_URL;
    }

    /**
     * The name of the plugin
     * 
     * @return const PLUGIN_NAME
     */
    public static function get_plugin_name()
    {
        return self::PLUGIN_NAME;
    }

    /**
     * The name of the plugin
     * 
     * @return const SHIPPING_METHOD_NAME
     */
    public static function get_shipping_method_name()
    {
        return self::SHIPPING_METHOD_NAME;
    }

    /**
     * undocumented function summary
     *
     * Undocumented function long description
     *
     * @return string const REAL_URL || DEMO_URL
     **/
    public static function get_service_url()
    {
        return self::REAL_URL;
    }

    public static function get_demo_service_url()
    {
        return self::DEMO_URL;
    }
}