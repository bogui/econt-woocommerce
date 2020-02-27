jQuery( document ).ready( function (dwe) {    

  let locale  
  let global_waybill_id
  let global_order_id
  // let params
  let update = false

  locale = getCurrentLocale()

  dwe( ".delivery-with-econt-generate-waybill-button" ).click( onPreview );
  dwe( ".delivery-with-econt-check-waybill-status" ).click( refresh );
  
  // bind the r event listener to check status async function
  function refresh () {    
    // this.preventDefault();
    let s = dwe( this )
    let order_id = s.data( "order-id" )
    let waybill_id = global_waybill_id ? global_waybill_id : s.data( "waybill-id" )
    let i = '#spiner-order-' + order_id
    let a = '#action-waybill-' + order_id
    let econt_service_url = dwe( 'meta[name="econt-service-url"]' )[0]
    let private_key = dwe( 'meta[name="econt-private-key"]' )[0]
    let currency = s.data( "econt-currency" )
    
    private_key = atob( private_key.content )

    s.css('visibility', 'hidden')
    dwe(i).addClass('is-active')


    // send ajax POST request to Econt servers
    r = request(econt_service_url, order_id, waybill_id, private_key, currency, s, i)
    r.then( function( response ) {
      // setTimeout( () => {
        s.css('visibility', 'visible')        
        dwe(i).removeClass('is-active')        
        
        // console.log(params);
        
        if( response && response.shipmentNumber ) {
          dwe(a).text('Print')
          data = {
            "orderData": {
              "num": order_id
            },
            "shipmentStatus": {
              "shipmentNumber": response.shipmentNumber
            }
          }
          update_waybill( JSON.stringify( data ) )
          update = true
        } else {
          dwe(a).text( locale === 'bg' ? 'Генерирай' : 'Generate' )
          dwe( '.econt-tracking-info' ).css( 'display', 'none' )
          data = {
            "order_id": order_id,
            "orderData": {
              "num": order_id
            },
            "shipmentStatus": {
              "shipmentNumber": null
            }
          }
          u = update_waybill( JSON.stringify( data ) )
          u.then( () => { 
            update = false
          })
        }
      // }, 800 )
            
      global_waybill_id = waybill_id
    })
  }

  // bind the event listener to asyncronous generate/print function
  function onPreview () {   
    // set some vars
    let t = dwe( this )
    let order_id = t.data( "order-id" )
    // let pdf_url = ''
    let waybill_id
    let econt_service_url = dwe( 'meta[name="econt-service-url"]' )[0];
    let private_key = dwe( 'meta[name="econt-private-key"]' )[0]
    let currency = t.data( "econt-currency" )
    
    private_key = atob( private_key.content )
    global_order_id = order_id

    if (update && global_waybill_id) waybill_id = global_waybill_id
    else waybill_id = t.data( "waybill-id" )

    // send ajax POST request to Econt servers
    r = request(econt_service_url, order_id, waybill_id, private_key, currency)
    // console.log(r, params, waybill_id);
    
    r.then( (response) => {
      // setTimeout( () => {
        // if we have response and response waybill_id equals local - print the waybill
        // if ( params && Number( waybill_id ) === Number( params.shipmentNumber ) ) {
        if ( response && response.shipmentNumber ) {
          data = {
            "order_id": order_id,
            "orderData": {
              "num": order_id
            },
            "shipmentStatus": {
              "shipmentNumber": response.shipmentNumber
            }
          }
          u = update_waybill( JSON.stringify( data ) )
          u.then( () => {                        
            dwe( t ).text( locale === 'bg' ? 'Принтирай' : 'Print' )
            window.open( response.pdfURL, '_blank' )
          })
        } else {
          // else sync the order with Econt, because there may be some differences
          // console.log(t.data('order-data'));
          
          // update = true
          return t.data( "order-data" ) ? dwe( this ).WCBackboneModal({
            template: "dwe-modal",
            variable: t.data( "order-data" )
          }) : ( t.addClass( "disabled" )
          ,
            dwe.ajax({
              url:delivery_with_econt_admin_object.ajax_url,
              data:{
                order_id: order_id,
                action:"delivery_with_econt_get_order_details",
                security:delivery_with_econt_admin_object.security
              },
              type:"GET",
              success:function( e ){                               
                dwe( ".order-preview2" ).removeClass( "disabled" ),
                e.success&&(
                  t.data( "order-data", e.data ),
                  dwe( this ).WCBackboneModal({
                    template:"dwe-modal",
                    variable:e.data
                  })
                )
              }
            })
          ), !1
        }
      // }, 300 )
    })        
  }
  // new e
  
  // Update the local waybill_id when recieved
  window.addEventListener( 'message', function( message ) {
    /**
     * check if this "message" comes from econt delivery system
     */
    if(message.origin.indexOf("//delivery") < 0 ){
		  return;
	  }
    if(message.data.event === 'cancel' ) {
      jQuery( '.modal-close' ).click()
    }else if( message.data.event === 'confirm' ) {
      u = update_waybill( JSON.stringify( message.data ) )
      u.then( () => {     
        if (u) {        
          dwe( "#action-waybill-" + global_order_id ).text( 'Print' )
          jQuery( '.modal-close' ).click()
          if( message.data.printPdf ) {
            window.open( message.data.shipmentStatus.pdfURL, '_blank' )
          }
        }
      })
    }    
  })

  function update_waybill(message) {
    return dwe.ajax({
      url:delivery_with_econt_admin_object.ajax_url,
      data:{
        "message": message,
        "action": "delivery_with_econt_save_waybill_id",
        "security": delivery_with_econt_admin_object.security
      },
      type:"POST",
      dataType: 'json',
      success:function(){
        global_waybill_id = message.shipmentNumber
        return true
      },
      error (e) {
        console.log(e);        
      }
    })
  }

  function request(econt_service_url, order_id, waybill_id, private_key, currency = 'BGN', s = null, i = null) {
    return dwe.ajax({
      url: econt_service_url.content + 'services/OrdersService.getTrace.json',
      data:JSON.stringify({
        "id": "",
        "orderNumber": order_id,
        "status": "",
        "orderTime": "",
        "cod": "",
        "partialDelivery": "",
        // "currency": wcSettings.currency.code,
        "currency": currency,
        "shipmentDescription": "",
        "shipmentNumber": waybill_id,
      }),
      type: "POST",
      dataType: 'json',
      contentType: 'application/json',
      beforeSend: function ( xhr ) {
        xhr.setRequestHeader ( "Authorization", private_key );
      },
      success:function( e ){       
        // set local param with response data 
        // params = e
        return e
      },
      // handle error (alert the error)
      error: function ( error ) {
        alert( 'Отговор от Еконт: ' + error.responseJSON.message + "\r\nСървърен код - " + error.status + ' ( ' + error.statusText + ' ).')
        if ( s && i ) {
          s.css('visibility', 'visible')        
          dwe(i).removeClass('is-active')        
        }
      }
    })
  }

  function getCurrentLocale() {
    return dwe('html').get(0).lang.split('-')[0];
  }
});