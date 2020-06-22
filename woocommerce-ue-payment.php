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
 * Copyright: (c) 2020
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Make sure Wordpress is running
defined( 'ABSPATH' ) or exit;
// Make sure WooCommerce is active
if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' )))) return;

// include helper functions
require_once(dirname(__FILE__) . '/utils/ue.php');
require_once(dirname(__FILE__) . '/utils/helper.php');

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

add_action( 'plugins_loaded', 'ue_wc_gateway_init' );
function ue_wc_gateway_init() {
	class WC_Gateway_UE extends WC_Payment_Gateway {
 
        // Setup basics
 		public function __construct() {
            $this->id = "ue";
            $this->icon = "";
            $this->has_fields = false;
            $this->method_title = "Utrechtse Euro";
            $this->method_description = "Accepteer betalingen met de Utrechtse Euro";

            $this->supports = array('products');
            $this->init_form_fields();
            $this->init_settings();

            // settings
            $this->title = $this->get_option( 'title' );
	        $this->description = $this->get_option( 'description' );
	        $this->enabled = $this->get_option( 'enabled' );
            $this->testmode = 'yes' === $this->get_option( 'testmode' );
            $this->use_accessclient = 'yes' === $this->get_option('use_accessclient');

            $this->root_url = $this->testmode ? 'https://demo.cyclos.org' : 'https://mijn.circuitnederland.nl';
            $this->api_endpoint = $this->root_url . '/api';
            $this->username = $this->testmode ? "ticket" : $this->get_option( 'username' );
            $this->password = $this->testmode ? "1234" : $this->get_option( 'password' );
            $this->accessclient = $this->use_accessclient ? $this->get_option( 'accessclient' ) : NULL;

            // if accessclient is retrieved.
            if( !empty($_POST['accessClientCode'])) {
                $accesscode = $_POST['accessClientCode'];
                $token = generate_accessclient_token($this->api_endpoint, $accesscode, $this->username, $this->password);

                $this->update_option('accessclient', $token);
                $this->update_option('use_accessclient', 'yes');
            }
            
            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            //Webhook for when payment is complete.
            add_action( 'woocommerce_api_ue_payment_completed', array( $this, 'webhook' ) );
        }

        // Generate appropriate headers to make requests
        private function headers() {
            if ($this->use_accessclient) {
                return array(
                    'Content-Transfer-Encoding' => 'application/json',
                    'Content-type' => 'application/json;charset=utf-8',
                    'Access-Client-Token' => $this->accessclient
                ); 
            } else {
                return array(
                    'Content-Transfer-Encoding' => 'application/json',
                    'Content-type' => 'application/json;charset=utf-8',
                    'Authorization' => 'Basic '. base64_encode("{$this->username}:{$this->password}")
                );
            }
        }

        //Function to generate HTML for accessClient generator in the WP-admin UI
		public function generate_screen_button_html( $key, $data ) {
            $field    = $this->plugin_id . $this->id . '_' . $key;
			$defaults = array(
				'class'             => 'button-secondary',
				'css'               => '',
				'custom_attributes' => array(),
				'desc_tip'          => false,
				'description'       => '',
				'title'             => '',
			);

			$data = wp_parse_args( $data, $defaults );

			ob_start();
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
					<?php echo $this->get_tooltip_html( $data ); ?>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
						<form method="post" name="accessClientForm" id="accessClientForm" action="">
							<input type="text" id="accessClientCode" name="accessClientCode" placeholder="AccessClient Activatie Code">
							<button type="submit" class="<?php echo esc_attr( $data['class'] ); ?>" type="submit" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php echo $this->get_custom_attribute_html( $data ); ?>>Genereer AccessClient</button>
							<p class="description">
                                <br> Log in op uw Utrechtse Euro account en ga naar:
                                <br> Persoonlijk > Instellingen > Webshop koppelingen > toegangscodes > Toevoegen > [Vul een beschrijving in] > Opslaan > Activatiecode > Bevestigen
                                <br> Vul de vier-cijferige code hierboven in en klik op Genereer AccessCode.
                                <br> <u>Als u deze optie niet heeft in uw U€-account, neemt u dan contact op met de Utrechtse Euro.</u>
                            </p>
						</form>
					</fieldset>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

        // Plugin options
 		public function init_form_fields(){
            $this->form_fields = array(
                'basic_settings_title' => array(
					'title'       => __( 'Basis instellingen' ),
					'type'        => 'title',
				),
                'enabled' => array(
                    'title'         => 'Activeer/Deactiveer',
                    'label'         => 'Activeer UE Gateway',
                    'type'          => 'checkbox',
                    'description'   => '',
                    'default'       => 'no'
                ),
                'testmode' => array(
                    'title'         => 'Test mode',
                    'label'         => 'Activeer Test Mode',
                    'type'          => 'checkbox',
                    'description'   => 'Plaats de gateway in test mode',
                    'default'       => 'yes',
                    'desc_tip'      => 'gebruikersnaam: demo, wachtwoord: 1234',
                ),
                'username' => array(
                    'title'         => 'U€ gebruikersnaam',
                    'type'          => 'text',
                ),
                'password' => array(
                    'title'         => 'U€ wachtwoord',
                    'type'          => 'password',
                ),
                'use_accessclient' => array(
                    'title'         => 'Accessclient',
                    'label'         => 'Activeer Accessclient Mode (deze optie wordt geadviseerd)',
                    'type'          => 'checkbox',
                    'description'   => 'Gebruik een anoniem token ipv uw gebruikersnaam en wachtwoord als de gebruiker wordt doorgelinkt naar de Utrechtse Euro betalingspagina, deze optie heeft de voorkeur vanwege veiligheidsredenen.',
                    'default'       => 'no',
                    'desc_tip'      => true,
                ),
                'accessClientGenerate' => array(
                    'type'          => 'screen_button',
                    'desc_tip'   => 'U€ gebruikersnaam, wachtwoord en uw activatie code moeten zijn ingevuld voordat de token gegenereerd kan worden!'
                ),
                'accessclient' => array(
                    'title'         => 'Accessclient code',
                    'type'          => 'password',
                    'description'   => 'Hier staat de automatisch gegenereerde accesclient, hier hoeft u verder niks mee te doen.',
                    'desc_tip'      => true,
                ),
                'display_settings_title' => array(
					'title'       => __( 'Weergave instellingen' ),
					'type'        => 'title',
					'description' => 'Pas hier uw weergave instellingen van deze plugin aan. In veel gevallen zijn de standaard waardes voldoende.',
				),
                'title' => array(
                    'title'         => 'Titel',
                    'type'          => 'text',
                    'description'   => 'De titel die de bezoeker tijdens check-out ziet.',
                    'default'       => 'Utrechtse Euro',
                ),
                'description' => array(
                    'title'         => 'Beschrijving',
                    'type'          => 'textarea',
                    'description'   => 'De beschrijving die de bezoeker tijdens check-out ziet.',
                    'default'       => 'Betaal met Utrechtse Euro\'s.',
                ),
            );
        }
         
        //Back-end options validation and processing.	
		public function process_admin_options(){
			if ($_POST['woocommerce_ue_use_accessclient'] == true) {
				if ($_POST['woocommerce_ue_testmode'] == true) {
                    WC_Admin_Settings::add_error( 'Error: accessClient not available in testmode.' );
                    return false;
				} else {
					if (empty($_POST['woocommerce_ue_accessclient'])){
						WC_Admin_Settings::add_error( 'Error: Uw accessClient is niet geldig.' );
						return false;
					} else {
						parent::process_admin_options();
						return true;
					}
				}
			} else {
				if ($_POST['woocommerce_ue_testmode'] == true) {
                    parent::process_admin_options();
                    return true;
				} else {
					if (empty($_POST['woocommerce_ue_username'])){
						WC_Admin_Settings::add_error( 'Error: Uw gebruikersnaam is niet geldig.' );
						return false;
					} else if (empty($_POST['woocommerce_ue_password'])){
						WC_Admin_Settings::add_error( 'Error: Uw wachtwoord is niet geldig.' );
						return false;
					} else {
						parent::process_admin_options();
						return true;
					}
				}
			}
		}
 
        // We're processing the payments here, everything about it is in Step 5
        public function process_payment( $order_id ) {
            global $woocommerce;

            // create ticket number
            $order = wc_get_order( $order_id );
            $amount = $order->get_total();
            $shop_title = get_bloginfo('name');
            $description = "Betaling van $amount aan $shop_title";

            //urls
            $successUrl = $order->get_checkout_order_received_url();
            $successWebhookUrl = get_home_url(NULL, "/wc-api/ue_payment_completed?orderId=$order_id");
            $cancelUrl = $order->get_cancel_order_url();

            //create request body
            $body = array(
                'amount' => $amount,
                'description' => $description,
                'payer' => null,
                'successUrl' => $successUrl,
                'successWebhook' => $successWebhookUrl,
                'cancelUrl' => $cancelUrl,
                'orderId' => $order_id,
                'expiresAfter' => array(
                    'amount' => 1,
                    'field' => 'hours'
                )
            );
            
            if ($this->testmode !== true) {
                $body['type'] = "handelsrekening.handels_transactie";
            }

            $ticketNumber = generate_ticket_number($this->api_endpoint, $this->headers(), $body);;

            if (strpos($ticketNumber, 'Error') !== false) {
                //Add WC Notice with error message
                wc_add_notice($ticketNumber);
                return false;
            } else {
                //Return is succesfull, so redirection is taking place
                return array(
                'result' => 'success',
                'redirect' => "{$this->root_url}/pay/{$ticketNumber}"
                );
            }
        }
 
        // webhook for U€ to let WP know to finalize payment
        public function webhook() { 
			$order_id = $_GET['orderId'];
			$order = wc_get_order( $order_id );
            // $ticketNumber = json_decode($order->get_meta('ticket_number'));
            $ticketNumber = $_GET['ticketNumber'];
		 
			try {
			    $transactionNumber = process_ticket($this->api_endpoint, $this->headers(), $ticketNumber, $order_id);
			    if (!empty($transactionNumber)) {

			    	//Complete order when ticket is processed
			        $order->payment_complete($transactionNumber);
					$order->reduce_order_stock();

					$note = "Bestelling compleet met transactie-ID: $transactionNumber";
					$order->add_order_note( $note );
			    }
			} catch (Exception $e) {
			    // Error when processing the ticket
				$order->update_status('Mislukt', sprintf(__('Foutmelding: %1$s'), $e));
				$note = sprintf(__('Foutmelding: %1$s'), $e);
				$order->add_order_note( $note );
			}

			// Regardless the result return OK to U€ api
			http_response_code(200);

			update_option('webhook_debug', $_GET);
			die();
         } 
 	}
}

?>