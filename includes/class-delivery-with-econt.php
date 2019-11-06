<?php

if (!defined( 'WPINC')) {
    die;
}

class Delivery_With_Econt
{
        
    public static function update_order()
    {
        $order_id           = absint( $_POST['order_id'] );
        // Grab the order
        $order              = wc_get_order( $order_id );
        $calculate_tax_args = array(
        'country'  => isset( $_POST['country'] ) ? wc_strtoupper( wc_clean( wp_unslash( $_POST['country'] ) ) ) : '',
        'state'    => isset( $_POST['state'] ) ? wc_strtoupper( wc_clean( wp_unslash( $_POST['state'] ) ) ) : '',
        'postcode' => isset( $_POST['postcode'] ) ? wc_strtoupper( wc_clean( wp_unslash( $_POST['postcode'] ) ) ) : '',
        'city'     => isset( $_POST['city'] ) ? wc_strtoupper( wc_clean( wp_unslash( $_POST['city'] ) ) ) : '',
        );

        // Parse the jQuery serialized items.
        $items = array();
        parse_str( wp_unslash( $_POST['items'] ), $items ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        
        // Get the new shipping price from Econt and synnc the items
        $sync = DWEH()->sync_order( $order, $items, true );
        if ( gettype( $sync) === 'array' ) {
            ?>
                <div class="notice notice-<?php echo $sync['type']; ?>">
                    <?php 
                        wc_print_notice( $sync['text'], $sync['type'] );
                    ?>
                </div>
            <?php
            // wp_die();
        } else {
            $new_shipping_price = number_format( $sync, 2, '.', '' );

            $shipping_keys = array_keys( $items['shipping_cost'] );
            foreach( $shipping_keys as $key ) {
                $items['shipping_cost'][$key] = $new_shipping_price;
            }
            // Save order items first.
            wc_save_order_items( $order_id, $items );

            // recalculate taxes.    
            $order->calculate_taxes( $calculate_tax_args );
            $order->calculate_totals( true );
        }
        
        include ( plugin_dir_path( __FILE__ ) . '../../woocommerce/includes/admin/meta-boxes/views/html-order-items.php' );
        wp_die();
    }

    public static function save_waybill_id()
    {
        $data = json_decode( wp_unslash( $_POST['message'] ), true );    
        if ( $data['shipmentStatus']['shipmentNumber'] === null ){
            delete_post_meta( $data['orderData']['num'], '_order_waybill_id', sanitize_text_field( $data['shipmentStatus']['shipmentNumber'] ) );
        } else {
            update_post_meta( absint( $data['orderData']['num'] ), '_order_waybill_id', sanitize_text_field( $data['shipmentStatus']['shipmentNumber'] ) );
        }
        exit;
    }
}