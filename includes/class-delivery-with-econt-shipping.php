<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Delivery_With_Econt_Shipping extends WC_Shipping_Method
{
    // Shipping method title
    const TITLE = 'Econt';

    // Shipping method description
    const DESCRIPTION = 'Econt Shipping Method';

    /**Wordpress shipping-related variables */
    public $supports;
    public $id;
    public $method_title;
    public $method_description;
    public $enabled;
    public $title;

    /**
     * Constructor for your shipping class
     *
     * @access public
     * @return void
     */
    public function __construct( $instance_id = 0 )
    {
        $this->id                 = Delivery_With_Econt_Options::get_plugin_name();
        $this->instance_id        = absint( $instance_id );
        $this->title              = __(self::TITLE, 'delivery-with-econt');
        $this->method_title       = __(self::TITLE, 'delivery-with-econt');
        $this->method_description = __(self::DESCRIPTION, 'delivery-with-econt');
        $this->enabled            = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
        $this->supports           = array(
			'shipping-zones',
			'instance-settings',
        );
        
        $this->init();
    }
    /**
    * Load the settings API
    */
    function init()
    {
        $this->init_form_fields();
        $this->init_settings();                
        // Save settings in admin if you have any defined
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );        
    }    
    
    public function calculate_shipping( $package = [] )
    {       
        // dd($_COOKIE);
        // if ( $_POST['delivery_with_econt_customer_info_id'] === '' ) return;
        $cost = 0;
        
        if ( array_key_exists('econt_shippment_price', $_COOKIE ) ) {
            $cost = $_COOKIE["econt_shippment_price"];
            // setcookie("econt_shippment_price",$cost,time()-1);
        }

        $rate = array(
            'id' => $this->id,
            'label' => __('Econt Delivery', 'deliver-with-econt'),
            'cost' =>  $cost
        );
        $this->add_rate( $rate );
    }    
    
    /**
     * Тук можем да проверяваме кой е избраният метод за доставка и ако е Еконт, да проверяваме дали има свързаност със сърварите им.
     * Ако върне статус 500 - проблема е при тях.
     * Ако върне статус 400 - проблема е при нас.
     * Ако върне 200 - всичко е ОК и продължаваме.
     * 
     */

    public function validate_service()
    {
        return false;
    }

    public static function get_order_info()
    {
        $url = DWEH()->get_service_url();
        // dd( WC()->cart->get_discount_total());
        $options = get_option( 'delivery_with_econt_settings' );
        $params = $_POST['params'];
        $order = array();
        $econt_cart = WC()->cart->get_cart();
        // $order['total'] = round(floatval( preg_replace( '#[^\d.]#', '', WC()->cart->get_subtotal() ) ), 2);
        $order['order_total'] = DWEH()->econt_calculate_cart_price( $econt_cart );
        // $order['order_total'] = floatval(WC()->cart->get_subtotal()) - WC()->cart->get_discount_total();
        $order['order_weight'] = WC()->cart->get_cart_contents_weight();
        $order['order_currency'] = get_woocommerce_currency();
        $order['id_shop'] = $options['store_id'];
        foreach ($params as $key => $value) {
            $order[$key] = $value;
        }
        
        $order['confirm_txt'] = 'Потвърди'; // текст за потвърждаващия бутон
        $order['ignore_history'] = 1; // изключване на автоматично попълване на полетата от историята
        wp_send_json($url . 'customer_info.php?' . http_build_query($order, null, '&'));
    }

    public static function render_form_button($checkout)
    {               
        if ( !is_checkout() || $checkout->id != 'delivery_with_econt' ) {
            return;
        }        

        ?>
        <span id="econt_detailed_shipping"></span>
        <!-- Buttons -->
        <div id="econt_delivery_calculate_buttons">
            <button type="button" id="calculate_shipping_button" class="econt-button"><?= __('Calculate price', 'deliver-with-econt')?></button>
            <button type="button" id="copy_shipping_data_button" class="econt-button"><?= __('Copy delivery details', 'deliver-with-econt')?></button>
        </div>
        <?php
    }

    public static function render_form_modal($checkout) {
        if ( !is_checkout() ) {
            return;
        }        

        ?>
        <!-- Error messages -->
        <div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout econt-alert" style="display: none">
            <ul class="woocommerce-error" role="alert" style="margin-bottom: 5px;">
                <li id="econt_display_error_message"></li>                    
            </ul>
        </div>

        <!-- <input type="hidden" class="input-hidden" name="delivery_with_econt_customer_id" id="delivery_with_econt_customer_id" value="<?php //echo WC()->checkout->get_value('delivery_with_econt_customer_id'); ?>">         -->
        <div id="delivery_with_econt_calculate_shipping">           
                        
            <!-- Modal -->
            <div id="myModal" class="modal">
                <!-- Modal content -->
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <!-- ФОРМА ЗА ДОСТАВКА -->
                    <div id="delivery_with_econt_calculation_container">                            
                        <div class="modal-body" id="place_iframe_here"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php                       
    }    

}
