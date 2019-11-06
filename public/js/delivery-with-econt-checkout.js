jQuery( document ).ready( function (dwe) {  
  let global_shippment_price_cod
  let global_shippment_price_no_cod
  let global_info_message
  let use_shipping = false

  function resetCookies() {
    document.cookie = "econt_shippment_price=0; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT;";
    document.cookie = "econt_customer_info_id=0; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT;";
  
    global_shippment_price_cod = undefined
    global_shippment_price_no_cod = undefined
    global_info_message = undefined
  }

  /**
   * First we disable the "Enter" key 
   * because we need the "click" event in order to manage the flow
   */
  dwe("form[name='checkout']").on('keypress', function (e) {      
    var key = e.which || e.keyCode;
    if (key === 13) { // 13 is enter
      e.preventDefault();
      e.stopPropagation();     
    }
  });     
  
  resetCookies();
  
  /**
   * We need this, because on update some fields, wordpress rerenders the html and breaks the listeners
   */
  dwe( document.body ).on( 'updated_checkout', function() {    
    /**
     * The actual "click" event listener. It does:
     * 
     * 1. check if the selected method is our method and if it's not - submits the form.
     * WooCommerce is responsable to check all the required fields and manage the errors
     * 2. if the selected method is "Econt" makes an ajax call to obtain fresh URI from our plugin entry point
     * with the data provided by the user needed to display the Iframe
     */

     // Let's now identify the payment method and it's visualisation
    let payment_input = dwe( 'input[name^="payment_method"]' )

    payment_input.each((key, field) => {    
      dwe('#'+field.id).change( function() {        
        if (this.value == 'cod' && selected_shipping_method === 'delivery_with_econt') {
          document.cookie = "econt_shippment_price=" + global_shippment_price_cod + "; path=/";
          dwe( '#econt_detailed_shipping' ).css('display', 'block')     
        } else if ( selected_shipping_method === 'delivery_with_econt' ) {
          document.cookie = "econt_shippment_price=" + global_shippment_price_no_cod + "; path=/";
          dwe( '#econt_detailed_shipping' ).css('display', 'none')
        }
        dwe( 'body' ).trigger( 'update_checkout' );        
      });      
    })


    // define the selected shipping method var
    let selected_shipping_method 
    // get the shipping method input field
    let input_type = dwe( 'input[name^="shipping_method"]' )[0]
    // check what type of field do we have and take corresponding action
    if ( input_type!= undefined && input_type.type === 'radio' ) {
      selected_shipping_method = dwe( 'input[name^="shipping_method"]:checked' ).val()
    } else if ( input_type!= undefined && input_type.type  === 'hidden' ) {
      selected_shipping_method = input_type.value
    }

    if ( selected_shipping_method === 'delivery_with_econt' ) {
      dwe('input[value="delivery_with_econt"]').parent().css({position: 'relative', height: '50px'})
      toggleCalculationButtons()

      dwe("#delivery_with_econt_calculate_shipping").css( 'display', 'grid');
    }
    
    dwe( '#place_order' ).on( 'click', function( e ){                
      if( checkIfShippingMethodIsEcont() && (global_shippment_price_cod === undefined || global_shippment_price_no_cod === undefined )){
        e.preventDefault()
        e.stopPropagation()   
        dwe(' input[type=radio][value=delivery_with_econt] ').prop('checked', false);
        dwe( 'body' ).trigger( 'update_checkout' ); 
        alert('Моля калкулирайте цена за доставка!');
      }
    });

    dwe( "button[name='apply_coupon']" ).on( 'click', resetCookies );

    dwe( "a.woocommerce-remove-coupon" ).on( 'click', resetCookies );

    dwe( '#copy_shipping_data_button' ).on( 'click', function() {
      if( dwe( '#ship-to-different-address-checkbox:checkbox:checked' )[0] ) {
        use_shipping = true
      } else {
        use_shipping = false
      }  
      if ( selected_shipping_method === 'delivery_with_econt' && checkForm(use_shipping) ) {
        getDataFromForm(use_shipping)
      } else {
        dwe( '[name="checkout"]' ).submit()
      }
      
    });

    dwe( '#calculate_shipping_button' ).on( 'click', function( e ){       
      if( dwe( '#ship-to-different-address-checkbox:checkbox:checked' )[0] ) {
        use_shipping = true
      } else {
        use_shipping = false
      }              
      
      getDataFromForm(use_shipping, true)
    });

    showPriceInfo(global_info_message);
  });

  /**
   * Event listener for the iframe window.
   * Handles the message sent back to us from Econt servers
   */
  window.addEventListener( 'message', function( message ) {
    // Данни връщани от формата за доставка:
    // id: уникално ID на адреса. Това поле трябва да бъде поставено в скритото customerInfo[id]
    // id_country: ID на държавата
    // zip: зип код на населеното място
    // post_code: пощенски код на населеното място
    // city_name: населено място
    // office_code: код на офиса на Еконт ако бъде избран такъв
    // address: адрес
    // name: име / фирма
    // face: лице
    // phone: телефон
    // email: имейл
    // shipping_price: цена на пратката без НП
    // shipping_price_cod: цена на пратката с НП
    // shipping_price_currency: валута на калкулираната цена
    // shipment_error: поясняващ текст ако е възникнала грешка

    /**
     * check if this "message" comes from econt delivery system
     */
    if(message.origin.indexOf("//delivery") < 0 ){
		  return;
	  }

    let data = message['data']
    // Boolean stoper 
    let updateCart = false
    
    /**
     * възможно е да възникнат грешки 
     * при неправилно конфигурирани настройки на електронния магазин които пречат за калкулацията.
     * Here we print as a alert message any error returned from Econt.
     *  */
    if ( data['shipment_error'] && data['shipment_error'] !== '' ) {      
      dwe( '#econt_display_error_message' ).empty();
      // append the generated iframe in the div
      dwe( '#econt_display_error_message' ).append(data['shipment_error']);
      
      dwe('.econt-alert').addClass('active');
      dwe('html,body').animate({scrollTop:dwe( '#delivery_with_econt_calculate_shipping' ).offset().top - 50}, 750);
      setTimeout( function() {
        dwe('.econt-alert').removeClass('active');
      }, 3500);
      
      return false;
    }

    // Елемент от кода, където е указано дали стоката ще се заплаща с НП или не
    let codInput = document.getElementById('payment_method_cod');          

    // формата за калкулация връща цена с НП и такава без
    // спрямо избора на клиента в "Заплащане чрез НП" показваме правилната цена
    let shippmentPrice  
    global_shippment_price_cod = data['shipping_price_cod']
    global_shippment_price_no_cod = data['shipping_price']
    global_info_message = data['shipping_price'] + ' ' + data['shipping_price_currency_sign'] + ' за доставка и ' + ( Math.round( (data['shipping_price_cod'] - data['shipping_price']) * 100 ) / 100 ) + ' ' + data['shipping_price_currency_sign'] + ' наложен платеж.'      
    
    if ( codInput.checked ) {      
      shippmentPrice = data['shipping_price_cod']
    } else  {
      shippmentPrice = data['shipping_price']
    }  

    document.cookie = "econt_shippment_price=" + shippmentPrice + "; path=/";
    
    updateCart = true;    
    
    dwe('#delivery_with_econt_calculation_container').addClass('econt-loader');
    dwe('#place_iframe_here').css('z-index', '-1');          
    if ( updateCart ) {
      
      /**
       * here we must:
       * 1. Update all fields if necesary;
       * 2. Populate all relevant hidden fields;
       * 3. Check if any other thing has to be done;
       * 4. Submit the form
       */

      /**
       * Set billing form fields
       */
      let full_name = []
      let company = ''
    
      if ( data['face'] != null ) {
        full_name = data['face'].split( ' ' );
        company = data['name'];
      } else {
        full_name = data['name'].split( ' ' );
      }
      if ( document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_first_name' ) )
        document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_first_name' ).value = full_name[0];
      if ( document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_last_name' ) )
        document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_last_name' ).value = full_name[1];
      if ( document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_company' ) )
        document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_company' ).value = company;
      if ( document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_address_1' ) )
        document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_address_1' ).value = data['address'] != '' ? data['address'] : data['office_name'];
      if ( document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_city' ) )
        document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_city' ).value = data['city_name'];
      if ( document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_postcode' ) )
        document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_postcode' ).value = data['post_code'];
      if ( document.getElementById( 'billing_phone' ) )
        document.getElementById( 'billing_phone' ).value = data['phone'];
      if ( document.getElementById( 'billing_email' ) )
        document.getElementById( 'billing_email' ).value = data['email'];
        
      document.cookie = "econt_customer_info_id=" + data['id'] + "; path=/";

      // Triger WooCommerce update in order to populate the shipping price, the updated address field and if any other
      dwe( 'body' ).trigger( 'update_checkout' );         
    }

    dwe('#delivery_with_econt_calculation_container').removeClass('econt-loader');
    dwe('#place_iframe_here').css('z-index', '1');
  
  }, false);    
});


/**
 * when press the big black "Place Order" button, this code will do:
 * 1. prevent the default behaviour;
 * 2. stop the propagation of the event;
 * 3. check the form for the required fields and display all the errors if any;
 * 4. open modal window with "Delivery with Econt" iframe, filled with the data
 */
function checkForm(use_shipping) {  
  let fields = [
    '#' + ( use_shipping ? 'shipping' : 'billing' ) + '_first_name',
    '#' + ( use_shipping ? 'shipping' : 'billing' ) + '_last_name',
    '#' + ( use_shipping ? 'shipping' : 'billing' ) + '_country',
    '#' + ( use_shipping ? 'shipping' : 'billing' ) + '_address_1',
    '#' + ( use_shipping ? 'shipping' : 'billing' ) + '_city',
    '#' + ( use_shipping ? 'shipping' : 'billing' ) + '_state',
    '#' + ( use_shipping ? 'shipping' : 'billing' ) + '_postcode',
    '#billing_phone',
    '#billing_email'
  ]      
  let showModal = true;

  fields.forEach( function( field ) {
    if( jQuery( field ).val() === '' ) { // check if field contains data
      showModal = false;
    }
  })

  return showModal;
}

/**
 * Render the actual iframe, nased on the provided user info
 *  
 * @param {data} data 
 */
function showIframe(data, toggle = false)
{ 
  let iframe
  let iframeContainer
  // toggle the buttons
  if ( toggle )
    toggleCalculationButtons()
  iframeContainer = jQuery( '#place_iframe_here' )
  
  iframe = '<iframe src="' + data.split( '"' ).join( '' ) + '" scrolling="yes" id="delivery_with_econt_iframe" name="econt_iframe_form"></iframe>'
  
  // empty the div if any oter instances of the iframe were generated
  iframeContainer.empty();
  // append the generated iframe in the div
  iframeContainer.append(iframe);       
  stopLoader();
}

function toggleCalculationButtons()
{
  let toHide = document.getElementById('calculate_shipping_button');
  let toShow = document.getElementById('copy_shipping_data_button');
  
  if (toHide.style.display === 'block') {
    toHide.style.display = 'none';
  } else {
    toHide.style.display = 'block';
  }

  if (toShow.style.display === 'none') {
    toShow.style.display = 'block';
  } else {
    toShow.style.display = 'none';
  }
}

async function getDataFromForm(use_shipping, toggle = false)
{
  let post_data = {
    action: 'woocommerce_delivery_with_econt_get_orderinfo',
    security: delivery_with_econt_calculate_shipping_object.security,
  }
  let params = {};
  let fName = '';

  startLoader();

  if ( document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_first_name' ) ) 
    fName = document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_first_name' ).value;
  let lName = '';
  if ( document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_last_name' ) ) 
    lName = document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_last_name' ).value 
  params.customer_name = fName + ' ' + lName;
  if ( document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_company' ) )
    params.customer_company = document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_company' ).value;
  if ( document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_address_1' ) )
    params.customer_address = document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_address_1' ).value;
  if ( document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_city' ) )
    params.customer_city_name = document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_city' ).value;
  if ( document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_postcode' ) )
    params.customer_post_code = document.getElementById( ( use_shipping ? 'shipping' : 'billing' ) + '_postcode' ).value;
  if ( document.getElementById( 'billing_phone' ) )
    params.customer_phone = document.getElementById( 'billing_phone' ).value;
  if ( document.getElementById( 'billing_email' ) )
    params.customer_email = document.getElementById( 'billing_email' ).value

  post_data.params = params

  await jQuery.ajax({
    type: 'POST',
    url: delivery_with_econt_calculate_shipping_object.ajax_url + '',
    data: post_data,
    success: function ( response ) {
      jQuery('#delivery_with_econt_calculate_shipping').removeClass('height-30')
      showIframe( response, toggle );      
    },
    dataType: 'html'
  });  
}

function startLoader()
{
  jQuery('#delivery_with_econt_calculation_container').addClass('econt-loader');
  jQuery('#place_iframe_here').css({'z-index': '-1', display: 'none'});
  jQuery('input[value="delivery_with_econt"]').parent().css({"height": "950px", "width": "220px"})
}

function stopLoader()
{
  setTimeout( function() {
    jQuery('#delivery_with_econt_calculation_container').removeClass('econt-loader');
    jQuery('#place_iframe_here').css({'z-index': '1', "display": "block"});
  }, 1000 )
}

function showPriceInfo(gm)
{    
  let im = jQuery( '#econt_detailed_shipping' )
  if ( checkIfShippingMethodIsEcont() && checkIfPaymentMethodIsCod() && gm != undefined ) {
    im.append(gm)
    im.css('display', 'block')
    jQuery('input[value="delivery_with_econt"]').parent().css({"height": "120px", "width": "220px"})    
  } else {
    im.css('display', 'none')    
  }
  
}

function checkIfShippingMethodIsEcont()
{
  let sh = jQuery(' [value=delivery_with_econt] ');
  if( sh.prop("type") === 'radio' && sh.prop('checked') ) {
    return true;
  } else if ( sh.prop("type") === 'hidden' ) {
    return true
  }

  return false
}

function checkIfPaymentMethodIsCod()
{
  let del = jQuery('#payment_method_cod');

  if ( del.prop('type') === 'radio' && del.prop("checked") ) {
    return true
  } else if (  del.prop("type") === 'hidden' ) {
    return true;
  } 
  
  return false;
  
}
