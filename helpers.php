<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Delivery_With_Econt_Helper
{

    /**
	 * The single instance of the class.
	 *
	 * @var DWEH
	 * @since 1.0
	 */
    protected static $_instance = null;
    
    /**
	 * Main Instance.
	 *
	 * Ensures only one instance is loaded or can be loaded.
	 *
	 * @since 1.0
	 * @static
	 * @see WC()
	 * @return Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

    /**
     * If there is an order in Econt syste, it will be updated.
     * If not - will be created.
     * 
     * @param int $local_order If there is a order in our system, the order_id will be used.
     * @param array $items If array of item ids is passed to the function, will loop trought them.
     * Other way $order->get_items() will be used.
     * @param bool $get_new_price If this is set to true, will send another request to Econt service
     * in order to fetch the order price. This is used in admin dashboard to recalculate shipping
     * 
     * @return string - the new price
     * @return bool - false - to finish the execution
     */
    public function sync_order( $local_order = null, $items = [], $get_new_price = false )
    {
        if ( ! $local_order ) return false;
        if( $local_order instanceof WC_Order ) {
            $order = $local_order;
        } else {
            $order = wc_get_order($local_order);
        }
        if(reset( $order->get_items( 'shipping' ) )->get_method_id() != Delivery_With_Econt_Options::get_plugin_name()) return false;
        
        $count = 0;
        $order_id = $order->get_id();
        $id = '';
        
        if ( array_key_exists( 'econt_customer_info_id', $_COOKIE ) ) {
            $id = $_COOKIE['econt_customer_info_id'];
            update_post_meta( $order_id, '_customer_info_id', $id );
            setcookie("econt_customer_info_id",$id,time()-1);
            setcookie("econt_shippment_price",'1000',time()-1);
        } else {
            $id = $order->get_meta('_customer_info_id');
        }        

        $data = array(
            'id' => '', 
            'orderNumber' => $order_id,
            'status' => $order->get_status(),
            'orderTime' => '',
            'cod' => $order->get_payment_method() === 'cod' ? true : '',
            'partialDelivery' => '',
            'currency' => get_woocommerce_currency(),
            'shipmentDescription' => '',
            'shipmentNumber' => '',
            'customerInfo' => array( 
                'id' => $id,
                'name' => '',
                'face' => '',
                'phone' => '',
                'email' => '',
                'countryCode' => '',
                'cityName' => '',
                'postCode' => '',
                'officeCode' => '',
                'zipCode' => '',
                'address' => '',
                'priorityFrom' => '',
                'priorityTo' => ''
            ),        
            'items' => array(
                
            )
        );

        foreach (count($items) ? $items['order_item_id'] : $order->get_items( 'line_item' ) as $_item) {
            if (count($items)) {
                $item = new WC_Order_Item_Product(intval($_item));
            } else {
                $item = $_item;
            }

            $product = $item->get_product();
            // $price  = $product->get_price();
            $price = $item->get_total();
            $count  = $item->get_quantity();
            $weight = floatval($product->get_weight());
            $quantity = intval($item->get_quantity());

            array_push($data['items'], array( 
                'name' => $product->get_name(),
                'SKU' => $product->get_sku(),
                'URL' => '',
                'count' => $quantity,
                'hideCount' => '',
                // 'totalPrice' => $price * $quantity,
                'totalPrice' => $price,
                'totalWeight' => $weight * $quantity
            ));
            $count += 1;
        }

        if( $count > 1 && $data['cod'] ) $data['partialDelivery'] = true;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->get_service_url() . 'services/OrdersService.updateOrder.json');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: ' . $this->get_private_key()
        ]);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        // Изпращане на заявката
        $response = curl_exec($curl);

        $parsed_error = json_decode($response, true);
        if( $parsed_error['type'] != '' ) {
            $message =[];            
            $message['text'] = $parsed_error['message'];
            $message['type'] = "error";
            // if we recieve error message from econt, we save it in the database for display it later
            update_post_meta( $order->get_id(), '_sync_error', sanitize_text_field( $message['text'] ) );
        }
        if ( $get_new_price ) {
            curl_setopt($curl, CURLOPT_URL, $this->get_service_url() . 'services/OrdersService.getPrice.json');
            $price = curl_exec($curl);

            return json_decode($price, true)['receiverDueAmount'];
        }

        if ( function_exists( 'wc_st_add_tracking_number' ) && isset( $response['shippingMethod'] ) ) {
            wc_st_add_tracking_number( 
                $order->get_id(), 
                $response['shippingNumber'], 
                Delivery_With_Econt_Options::get_shipping_method_name(), 
                date("Y-m-d H:i:s"), 
                $this->get_tracking_url( $response['shippingNumber'] ) 
            );
        }
        
        return false;
    }

    /**
     * Check if we using Demo service
     * 
     * @return bool
     */
    public function is_demo()
    {
        $options = get_option( 'delivery_with_econt_settings' );

        return isset($options['demo_service']);
    }

    /**
     * Based on the demo setting returns the appropiate url
     * 
     * @return string URL
     */
    public function get_service_url( $demo = false )
    {
        $options = get_option( 'delivery_with_econt_settings' );
        $url = '';
        
        if ( $demo || isset( $options['demo_service'] ) ) {
            $url = Delivery_With_Econt_Options::get_demo_service_url();
        } else {
            $url = Delivery_With_Econt_Options::get_service_url();
        }

        // return ( is_ssl() ? 'https:' : 'http:' ) . $url;
        return $url;
    }

    /**
     * Retrieve the stored in database setting
     * 
     * @param bool $encrypt Encrypt the string or not
     * 
     * @return string
     */
    public function get_private_key( $encrypt = false )
    {
        $options = get_option( 'delivery_with_econt_settings' );
        
        return $encrypt ? base64_encode( $options['private_key'] ) : $options['private_key'];
    }

    /**
     * The tracking url
     * 
     * @return string
     */
    public function get_tracking_url( $code )
    {
        return Delivery_With_Econt_Options::get_track_url() . $code;
    }

    /**
     * check stored configuration
     *
     * Check stored shop_id, private_key and demo_service options with Econt via curl request
     *
     * @param array $new_settings The settings entered by the user
     * @return array 
     **/
    public function check_econt_configuration( $new_settings = array() )
    {
        $endpoint = $this->get_service_url( array_key_exists( 'demo_service', $new_settings ) );
        $secret = $new_settings['private_key'];

        $curl = curl_init();
        curl_setopt( $curl, CURLOPT_URL, $endpoint . "services/OrdersService.getTrace.json" );
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $curl, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt( $curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: " . $secret
        ] );
        curl_setopt( $curl, CURLOPT_POST, true );
        curl_setopt( $curl, CURLOPT_POSTFIELDS, json_encode( array(
            'orderNumber' => 4812384
        ) ) );
        curl_setopt( $curl, CURLOPT_TIMEOUT, 6 );
        $res = curl_exec( $curl );
        $response = json_decode( $res, true );

        curl_close( $curl );

        if( is_array( $response ) && $response['type'] == 'ExAccessDenied' ) {
            return $response;
  
        } 

        return;
    }

    public function econt_calculate_cart_price( $cart )
    {
        $price = 0;
        foreach ($cart as $key => $item) {
            $price += $item['line_total'];
        }

        return $price;
    }

    /**
	 * Get order details.
	 */
	public function econt_get_order_details() {
		check_admin_referer( 'woocommerce-preview-order', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) || ! isset( $_GET['order_id'] ) ) {
			wp_die( -1 );
		}

		$order = wc_get_order( absint( $_GET['order_id'] ) ); // WPCS: sanitization ok.

		if ( $order ) {            
			wp_send_json_success( 
                array(
                    'data'                       => $order->get_data(),
                    'order_number'               => $order->get_order_number(),
                    'ship_to_billing'            => wc_ship_to_billing_address_only(),
                    'needs_shipping'             => $order->needs_shipping_address(),
                    'formatted_billing_address'  => $billing_address ? $billing_address : __( 'N/A', 'woocommerce' ),
                    'formatted_shipping_address' => $shipping_address ? $shipping_address : __( 'N/A', 'woocommerce' ),
                    'shipping_address_map_url'   => $order->get_shipping_address_map_url(),
                    'payment_via'                => $payment_via,
                    'shipping_via'               => $order->get_shipping_method(),
                    'status'                     => $order->get_status(),
                    'status_name'                => wc_get_order_status_name( $order->get_status() ),
                )
             );
		}
		wp_die();
	}
}
