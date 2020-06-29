<?php
/*
 * Plugin Name: Utrechtse Euro betalingen voor WooCommerce
 * Plugin URI: https://mddd.nl
 * Description: Ontvang U€ betalingen in je webwinkel
 * Author: M. D. Leguijt
 * Author URI: https://mddd.nl
 * Version: 1.2.3
 * WC requires at least: 3.0.0
 * WC tested up to: 4.2.2
 * Copyright: (c) 2020
 */

// Make sure Wordpress is running
defined( 'ABSPATH' ) or exit;
// Make sure WooCommerce is active
if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' )))) return;

// include actual gateway
require_once(dirname(__FILE__) . '/class/ue_wc_gateway_class.php');

// add link to settings on plugin page
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

// load the gateway class we included at the top
add_action( 'plugins_loaded', 'ue_wc_gateway_init' )

?>