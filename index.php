<?php
/*
Plugin Name: WooCommerce Conekta Payment
Plugin URI: 
Description: 
Version: 0.1
Author: Ziny Apps
Author URI:
*/

// requerir libreria conekta
require_once('conekta/lib/Conekta.php');

// agregar librerias js
function add_js_libs() {
	if(!is_admin()) {

		// wp_enqueue_script('conekta', get_bloginfo('template_url') . '/js/theme.js', array('jquery'), '1.0', true);
		wp_enqueue_script('conekta', 'https://conektaapi.s3.amazonaws.com/v0.3.1/js/conekta.js', array('jquery'), '0.3.1', true);
		wp_enqueue_script('main', plugins_url( '/js/main.js', __FILE__ ), array('jquery'), '0.1', true);
	}
}
add_action('init', 'add_js_libs');


// agregar payment
add_action('plugins_loaded', 'woocommerce_conekta_payment_init', 0);


function woocommerce_conekta_payment_init(){
  
	if(!class_exists('WC_Payment_Gateway')) return;

 
	class WC_Conekta_Payment extends WC_Payment_Gateway{
    
	    public function __construct(){
	    	$this->id = 'conekta';
	    	$this->method_title = 'Conekta Payment';
	    	$this->has_fields = true;

	    	$this->supports[] = 'default_credit_card_form';


	    	$this->init_form_fields();
			$this->init_settings();

			$this->title = $this->get_option( 'title' );
			$this->private_key = $this->get_option( 'private_key' );
			$this->public_key = $this->get_option( 'public_key' );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	      
	   	}

	    function init_form_fields(){
	 		$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'woocommerce' ),
					'type' => 'checkbox',
					'label' => __( 'Habilitar Conekta Payment', 'woocommerce' ),
					'default' => 'yes'
				),
				'title' => array(
					'title' => __( 'Title', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default' => __( 'Conekta Payment', 'woocommerce' ),
					'desc_tip'      => true
				),
				'private_key' => array(
					'title' => __( 'Private key', 'woocommerce' ),
					'type' => 'text',
					'default' => ''
				),
				'public_key' => array(
					'title' => __( 'Public key', 'woocommerce' ),
					'type' => 'text',
					'default' => ''
				)
			);
	    }

	    // function payment_fields() {
            
     	//       woocommerce_form_field( 'n_tarjeta', array(
     	//           'type'          => 'text',
     	//           'label'         => 'Numero Tarjeta'
     	//       ));
	    // }
	 

		function validate_fields() {
            if ($_POST['conektaTokenError']) {
                global $woocommerce;

                $woocommerce->add_error( _($_POST['conektaTokenError']) );

                return false;
            }

            return true;
        }
	    
	    function process_payment($order_id)
	    {
	        global $woocommerce;

			$order = new WC_Order( $order_id );

			

			if ($_POST['conektaTokenId'])
			{
				Conekta::setApiKey($this->private_key);
				
				// monto de orden en centavos
				$amount = $order->get_total() * 100;

				$data = array('card' => $_POST['conektaTokenId'], 'description' => 'Pago con tarjeta orden #'.$order_id, 'amount' => $amount, 'currency' => 'MXN');
				//$woocommerce->add_error( _($amount) );

				try
				{
					$charge = Conekta_Charge::create($data);
					if ($charge->status == 'paid')
					{
						// Mark as on-hold (we're awaiting the cheque)
						$order->update_status('on-hold', __( 'Awaiting the conekta payment', 'woocommerce' ));

						// Reduce stock levels
						$order->reduce_order_stock();

						// Remove cart
						$woocommerce->cart->empty_cart();

						// Return thankyou redirect
						return array('result' => 'success', 'redirect' => $this->get_return_url( $order ));
					}
				}
				catch (Exception $e) 
				{
					// Catch all exceptions including validation errors.
					$woocommerce->add_error( $e->getMessage() );
					$order->update_status('pending', __( 'Awaiting the conekta payment', 'woocommerce' ));
				}

			}

	    }

	}

	function set_fields_form(){
		global $woocommerce;
		
		$con = new WC_Conekta_Payment;
		$p_key = $con->public_key;

		$default_fields = array(
			'public_key-field' => '<p class="form-row form-row-wide">
                 <input id="conekta-public-key" class="input-text" type="hidden" maxlength="60" autocomplete="off" name="public_key" value="'.esc_attr($p_key).'"/>
             </p>',
			'name-field' => '<p class="form-row form-row-wide">
                 <label for="conekta-titular-name">Nombre Titular<span class="required">*</span></label>
                 <input id="conekta-titular-name" class="input-text" type="text" maxlength="60" autocomplete="off" placeholder="Nombre del titular como se muestra en la tarjeta" data-conekta="card[name]" />
             </p>',
             'card-number-field' => '<p class="form-row form-row-wide">
                 <label for="conekta-card-number">' . __( "Card Number", 'woocommerce' ) . ' <span class="required">*</span></label>
                 <input id="conekta-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="off" placeholder="•••• •••• •••• ••••" data-conekta="card[number]" />
             </p>',
             'card-expiry-field' => '<p class="form-row form-row-first">
                 <label for="conekta-card-expiry">' . __( "Expiry (MM/YY)", 'woocommerce' ) . ' <span class="required">*</span></label>
                 <input id="conekta-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="MM / YY" />
             </p>',
             'card-cvc-field' => '<p class="form-row form-row-last">
                 <label for="conekta-card-cvc">' . __( "Card Code", 'woocommerce' ) . ' <span class="required">*</span></label>
                 <input id="conekta-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="text" autocomplete="off" placeholder="CVC" data-conekta="card[cvc]" />
             </p>'             
         );

		return $default_fields;
	}	

	add_filter('woocommerce_credit_card_form_fields', 'set_fields_form');

	function set_args_form(){
		$arg = array(
					'fields_have_names' => false
				);

		return $args;
	}

	add_filter('woocommerce_credit_card_form_args', 'set_args_form');

	function woocommerce_add_conekta_payment($methods) {
        $methods[] = 'WC_Conekta_Payment'; 
		return $methods;
    }
 
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_conekta_payment' ); 
}

?>