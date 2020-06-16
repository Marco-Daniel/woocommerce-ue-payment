<?php

/*
 * Plugin Name: WooCommerce Utrechtse Euro Payment Gateway
 * Plugin URI: https://mddd.nl
 * Description: Take Utrechtse Euro payments on your store.
 * Author: M. D. Leguijt
 * Author URI: https://mddd.nl
 * Version: 0.0.1
 * WC requires at least: 3.0.0
 * WC tested up to: 3.6.2
 * License: GPL3
 */

// Make sure WooCommerce is active
if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' )))) return;

// This action hook registers our PHP class as a WooCommerce payment gateway
add_filter( 'woocommerce_payment_gateways', 'ue_add_gateway_class' );
function ue_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_UE_Gateway'; 
	return $gateways;
}
 
// The class itself, please note that it is inside plugins_loaded action hook
add_action( 'plugins_loaded', 'wc_ue_gateway_init', 11 );
function misha_init_gateway_class() {
 
	class WC_Gateway_UE extends WC_Payment_Gateway {
 
 		// Class constructor, more about it in Step 3
 		public function __construct() {
            $this->id = "ue";
            $this->icon = "";
            $this->has_fields = false;
            $this->method_title = "Utrechtse Euro";
            $this->method_description = "Activeer betalingen met de Utrechtse Euro";

            $this->supports = array('products');
            $this->init_form_fields();
            $this->init_settings();

            // settings
         }
 
        // Plugin options, we deal with it in Step 3 too
 		public function init_form_fields(){
		    return;
	 	}
 
        // You will need it if you want your custom credit card form, Step 4 is about it
		public function payment_fields() {
		    return;
		}
 
        // Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
	 	public function payment_scripts() {
		    return;
	 	}
 
        // Fields validation, more in Step 5
		public function validate_fields() {
		    return;
		}
 
        // We're processing the payments here, everything about it is in Step 5
		public function process_payment( $order_id ) {
		    return;
	 	}
 
        // In case you need a webhook, like PayPal IPN etc
		public function webhook() { 
		    return;
	 	}
 	}
}

?>