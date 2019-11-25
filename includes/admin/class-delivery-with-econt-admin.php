<?php

if (!defined( 'ABSPATH')) {
    die;
}

class Delivery_With_Econt_Admin
{
    /**
     * Add column to admin page orders view table
     */
    public function add_waybill_column( $columns )
    {
        $new_columns = array();

        foreach ( $columns as $column_name => $column_info ) {

            $new_columns[ $column_name ] = $column_info;

            if ( 'order_date' === $column_name ) {
                $new_columns['generate_waybill column-primary'] = __( 'Waybill', 'deliver-with-econt' );
            }
        }
    
        return $new_columns;
    }

    /**
     * Populate the "Generate Waybill" column with relevent data
     */
    public function add_waybill_column_content( $column )
    {
        global $post;

        if ( 'generate_waybill column-primary' === $column ) {

			$order    = wc_get_order( $post->ID );
			$waybill_id = $order->get_meta('_order_waybill_id');
			if( reset( $order->get_items( 'shipping' ) )->get_method_id() === 'delivery_with_econt' && static::check_status( $order->get_status() ) ) {
				if( WC()->version < '3.2.0' ) {
					?>
						<style>
							.dashicons-update-alt:before {
								content: "\f113";
							}
						</style>
					<?php
				}
				// echo '<a href="#" class="order-preview2" data-order-id="' . $order->get_id() . '" title="' . esc_attr( __( 'Generate weybill', 'delivery-with-econt' ) ) . '">' . esc_html( __( 'Generate weybill', 'delivery-with-econt' ) ) . '</a>';
				?>
				<a href="#!"
					id="action-waybill-<?php echo $order->get_id(); ?>"				
					class="button button-primary order-preview2 delivery-with-econt-generate-waybill-button" 
					data-order-id="<?php echo $order->get_id(); ?>"
					data-waybill-id="<?php echo $waybill_id; ?>"
					data-econt-currency="<?php echo $order->get_currency()?>"
				>
					<?php echo $waybill_id != '' ? __('Print', 'deliver-with-econt') : __('Generate', 'deliver-with-econt'); ?>					
				</a>
				<a href="#!" 
					id="refresh-waybill-<?php echo $order->get_id(); ?>"
					class="button button-primary order-preview2 delivery-with-econt-check-waybill-status"
					data-order-id="<?php echo $order->get_id(); ?>"
					data-waybill-id="<?php echo $waybill_id; ?>"					
				>
					<span class="dashicons dashicons-update-alt"></span>
					<div class="spinner" id="spiner-order-<?php echo $order->get_id(); ?>"></div>
				</a>
			<?php } else if ( reset( $order->get_items( 'shipping' ) )->get_method_id() === 'delivery_with_econt' && ! static::check_status( $order->get_status() )  && $waybill_id) {
				?>
					<a href="<?php echo DWEH()->get_tracking_url($waybill_id) ?>" target="_blank"><?php echo $waybill_id?></a>
				<?php 
			}

        }
		static::render_modal_window();
		static::render_modal_scripts();
    }

	/**
	 * Add our button in order preview page
	 */
	public function add_custom_html_to_order_details( $product_id )
	{
		$prod = new WC_Order_Item_Product( $product_id );
		$shipping = $prod->get_order()->get_items( 'shipping' );
		foreach ($shipping as $key => $value) {
			if( $value->get_method_id() != 'delivery_with_econt' ) return false;
		}
		
		if( count( $prod->get_meta_data() ) ) { ?>
            <div class="delivery-with-econt-generate-waybill">
			<?php 
				$waybill_id = $prod->get_order()->get_meta('_order_waybill_id'); 
				$order_id = $prod->get_order()->get_id();
				$currency = $prod->get_order()->get_currency();
				if( WC()->version < '3.2.0' ) {
					?>
						<style>
							.dashicons-update-alt:before {
								content: "\f113";
							}
						</style>
					<?php
				}
				?>
					<div class="econt-tracking-info">
						<?php
						if ($waybill_id != '') { ?>
							<h5><?php _e( 'Tracking info', 'deliver_with_econt' ); ?></h5>
							<a href="<?php echo DWEH()->get_tracking_url($waybill_id) ?>" target="_blank"><?php echo $waybill_id?></a>
						<?php } ?>
					</div>
					<div class="econt-action-buttons">
						<a href="#!" 
							id="action-waybill-<?php echo $order_id; ?>"
							class="button button-primary order-preview2 delivery-with-econt-generate-waybill-button" 
							data-order-id="<?php echo $order_id; ?>"
							data-waybill-id="<?php echo $waybill_id; ?>"
							data-econt-currency="<?php echo $currency?>"
						><?php echo $waybill_id ? __('Print', 'deliver-with-econt') : __('Generate', 'deliver-with-econt'); ?></a>

						<a href="#!" 
							id="refresh-waybill-<?php echo $order_id; ?>"
							class="button button-primary order-preview2 delivery-with-econt-check-waybill-status"
							data-order-id="<?php echo $order_id; ?>"
							data-waybill-id="<?php echo $waybill_id; ?>"
						>
							<span class="dashicons dashicons-update-alt"></span>
							<div class="spinner" id="spiner-order-<?php echo $order_id; ?>"></div>
						</a>
					</div>
			</div>
			<?php
			
			static::render_modal_window();
			static::render_modal_scripts();
		}
	}	

	// Modal window template
	public static function render_modal_window()
	{		
		?>
		<script type="text/template" id="tmpl-dwe-modal">
			<div class="wc-backbone-modal wc-order-preview">
				<div class="wc-backbone-modal-content">
					<section class="wc-backbone-modal-main" role="main">
						<header class="wc-backbone-modal-header">
							<h1><?php echo esc_html( sprintf( __( 'Order #%s', 'woocommerce' ), '{{ data.order_number }}' ) ); ?></h1>
							<button class="modal-close modal-close-link dashicons dashicons-no-alt">
								<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'woocommerce' ); ?></span>
							</button>
						</header>
						<article style="padding: 0">
							<div class="wc-order-preview-addresses">
								<iframe src="<?php echo DWEH()->get_service_url(); ?>create_label.php?order_number={{{data.order_number}}}&token=<?php echo DWEH()->get_private_key();?>" 
									frameborder="0" 
									width="100%" 
									height="350px"
								></iframe>
								<div class="wc-order-preview-address">
								</div>
							</div>
						</article>
					</section>
				</div>
			</div>
			<div class="wc-backbone-modal-backdrop modal-close"></div>
		</script>
		<?php 		
	}

	// Scripte and styles
	public static function render_modal_scripts()
	{
		wp_enqueue_style( 'delivery_with_econt_admin', plugin_dir_url(__FILE__) . '../../public/css/admin/delivery-with-econt-admin.css', [], false );
		wp_enqueue_script( 'delivery_with_econt_admin', plugin_dir_url(__FILE__) . '../../public/js/admin/delivery-with-econt-admin.js', ['jquery', 'underscore', 'backbone'], false, true );
		wp_localize_script( 'delivery_with_econt_admin', 'delivery_with_econt_admin_object', array('ajax_url' => admin_url('admin-ajax.php'), 'security'  => wp_create_nonce( 'woocommerce-preview-order' )));
	}

	// Check order status
	public static function check_status( $status )
	{
		$bad_statuses = array(
			'cancelled',
			'completed'
		);

		return ! in_array( $status, $bad_statuses );
	}
}