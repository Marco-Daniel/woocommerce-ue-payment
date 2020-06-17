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

// add link to settings
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'ue_add_plugin_page_settings_link');
function ue_add_plugin_page_settings_link( $links ) {
    $links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=UE') . '">' . __('Instellingen') . '</a>';
    return $links;
}

// This action hook registers our PHP class as a WooCommerce payment gateway
add_filter( 'woocommerce_payment_gateways', 'ue_add_gateway_class' );
function ue_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Gateway_UE'; 
	return $gateways;
}

add_action( 'plugins_loaded', 'ue_wc_gateway_init_class' );
function ue_wc_gateway_init_class() {
 
	class WC_Gateway_UE extends WC_Payment_Gateway {
 
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
            $this->title = $this->get_option( 'title' );
	        $this->description = $this->get_option( 'description' );
	        $this->enabled = $this->get_option( 'enabled' );
	        $this->testmode = 'yes' === $this->get_option( 'testmode' );
	        $this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
	        $this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
 

            // This action hook saves the settings
	        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
 
         }
 
        // Plugin options
 		public function init_form_fields(){
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Activeer/Deactiveer',
                    'label'       => 'Activeer UE Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Titel',
                    'type'        => 'text',
                    'description' => 'De titel die de bezoeker tijdens check-out ziet.',
                    'default'     => 'Utrechtse Euro',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Beschrijving',
                    'type'        => 'textarea',
                    'description' => 'De beschrijving die de bezoeker tijdens check-out ziet.',
                    'default'     => 'Betaal met Utrechtse Euro\'s.',
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'test_publishable_key' => array(
                    'title'       => 'Test Publishable Key',
                    'type'        => 'text'
                ),
                'test_private_key' => array(
                    'title'       => 'Test Private Key',
                    'type'        => 'password',
                ),
                'publishable_key' => array(
                    'title'       => 'Live Publishable Key',
                    'type'        => 'text'
                ),
                'private_key' => array(
                    'title'       => 'Live Private Key',
                    'type'        => 'password'
                )
            );
	 	}
 
        // You will need it if you want your custom credit card form, Step 4 is about it
		public function payment_fields() {
		}
 
        // Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
	 	public function payment_scripts() {
	 	}
 
        // Fields validation, more in Step 5
		public function validate_fields() {
		}
 
        // We're processing the payments here, everything about it is in Step 5
		public function process_payment( $order_id ) {
	 	}
 
        // In case you need a webhook, like PayPal IPN etc
		public function webhook() { 
	 	}
 	}
}

?>