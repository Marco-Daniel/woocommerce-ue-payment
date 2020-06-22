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

defined( 'ABSPATH' ) or exit;
// Make sure WooCommerce is active
if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' )))) return;

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
                $GLOBALS['GeneratedAccessclientCode'] = $token;
            }
            
            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            //Webhook for when payment is complete.
            add_action( 'woocommerce_api_ue_payment_completed', array( $this, 'webhook' ) );
        }

        public function headers() {
            if ($this->use_accessclient) {
                return array(
                    'Content-Transfer-Encoding' => 'application/json',
                    'Access-Client-Token' => $this->accessclient
                ); 
            } else {
                return array(
                    'Content-Transfer-Encoding' => 'application/json',
                    'Authorization' => 'Basic '. base64_encode("{$this->username}:{$this->password}")
                );
            }
        }

        //Function to generate HTML for accessClient generator
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
						<form method="get" name="accessClientForm" id="accessClientForm" action="">
							<input type="text" id="accessClientCode" name="accessClientCode" placeholder="AccessClient Activatie Code">
							<button type="submit" class="<?php echo esc_attr( $data['class'] ); ?>" type="submit" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php echo $this->get_custom_attribute_html( $data ); ?>>Genereer AccessClient</button>
							<p id="accessClientKey"class="description" style="color:red;"><?php echo $GLOBALS['GeneratedAccessClientToken']; ?></p>
							<p class="description">
                                <u>Vul uw activatiecode in en klik op Genereer AccessCode </u> 
                                <br> Log in op uw Utrechtse Euro account en ga naar:
                                <br> Persoonlijk > Instellingen > Webshop koppelingen > toegangscodes > Toevoegen > [Vul een beschrijving in] > Opslaan > Activatiecode > Bevestigen
                                <br> Vul de vier-cijferige code hierboven in.
                                <br> Als u deze optie niet heeft in uw U€-account, neemt u dan contact op met de Utrechtse Euro.
                            </p>
							<?php echo $this->get_description_html( $data ); ?>
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
                    'desc_tip'      => true,
                ),
                'username' => array(
                    'title'         => 'Utrechtse Euro gebruikersnaam',
                    'type'          => 'text',
                ),
                'password' => array(
                    'title'         => 'Utrechtse Euro wachtwoord',
                    'type'          => 'password',
                ),
                'use_accessclient' => array(
                    'title'         => 'Accessclient',
                    'label'         => 'Activeer Accessclient Mode (deze optie wordt geadviseerd)',
                    'type'          => 'checkbox',
                    'description'   => 'Gebruik een accesscode ipv gebruikersnaam en wachtwoord als de gebruiker wordt doorgelinkt naar de Utrechtse Euro betalingspagina, deze optie wordt aangeraden boven een gebruikersnaam en wachtwoord vanwege veiligheidsredenen.',
                    'default'       => 'no',
                ),
                'accessClientGenerate' => array(
                    'type'          => 'screen_button',
                    'desc_tip'   => 'Utrechtse Euro gebruikersnaam en wachtwoord moeten zijn ingevuld voordat de token gegenereerd kan worden!'
                ),
                'accessclient' => array(
                    'title'         => 'Accessclient code',
                    'type'          => 'text',
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
         
         //Admin options validation and processing.	
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
            //$paymentURL = "";
            $shop_title = get_bloginfo('name');
            $order = wc_get_order( $order_id );
            $amount = $order->get_total();
            $description = "Betaling van $amount aan $shop_title";
            $type = "handelsrekening.handels_transactie";

            //Ticket urls
            $successUrl = $order->get_checkout_order_received_url();
            $successWebhookUrl = get_home_url(NULL, "/wc-api/cyclos_payment_completed?orderId=$order_id?ticketNumber=$ticketNumber");
            $cancelUrl = $order->get_cancel_order_url();

            // //create request headers
            // $headers = array('Content-Transfer-Encoding' => 'application/json');

            // if ($use_accessclient == true) {
            //     $headers['Access-Client-Token'] = $accessclient;
            // } else {
            //     $headers['Authorization'] = 'Basic '. base64_encode("{$this->username}:{$this->password}");
            // }

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
                $body['type'] = $type;
            }

            $ticketNumber = generate_ticket_number($this->api_endpoint, headers(), $body);

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
 
        public function webhook() { 
            echo "OK";
			$order_id = $_GET['orderId'];
			$order = wc_get_order( $order_id );
			$ticketNumber = $_GET['ticketNumber'];
			echo "<br>OrderID: $order_id";
		 
			try {
			    $transactionNumber = process_ticket($this->api_endpoint, headers(), $ticketNumber, $order_id);
			    if (!empty($transactionNumber)) {

			    	//Complete order when ticket is processed
			        $order->payment_complete($transactionNumber);

					$order->reduce_order_stock();
					echo "<br>TransactionNumber: $transactionNumber";
					$note = "Bestelling compleet met transactie-ID: $transactionNumber";
					$order->add_order_note( $note );
			    }
			} catch (Exception $e) {
			    // Error when processing the ticket
			    echo "<br>Error: - $e";
				$order->update_status('Mislukt', sprintf(__('Foutmelding: %1$s'), $e));
				$note = "Order is mislukt, de foutmelding staat hieronder.";
				$order->add_order_note( $note );
			}

			// Regardless the result return OK to U€ api
			http_response_code(200);

			update_option('webhook_debug', $_GET);
			die();
         }

        // This function is not needed since most of the action occurs on the payment gateway website
        //
		// public function payment_fields() {
		// }
 
        // We don't need custom JS for this plugin
        //
	 	// public function payment_scripts() {
	 	// }
 
        // This function is not needed since most of the action occurs on the payment gateway website
        //
		// public function validate_fields() {
		// }
 
 	}
}

// helper functions
function generate_accessclient_token( $base_url, $accesscode, $username, $password ) {

    $url = "{$base_url}/clients/activate?code={$accesscode}";
    $headers = array(
        'Content-Transfer-Encoding' => 'application/json',
        'Authorization' => 'Basic '. base64_encode("{$username}:{$password}")
    );
    
    $response = wp_remote_request( $url, array(
        'method'      => 'POST',
        'httpversion' => '1.0',
        'timeout'     => 45,
        'redirection' => 15,
        'sslverify'   => false,
        'blocking'    => true,
        'headers'     => $headers,
        'body'        => array(),
        )
    );

    if ( is_wp_error($response) ) {
        $error = $response->get_error_message();
        WC_Admin_Settings::add_error("Er ging iets mis: $error");
    } else {
        WC_Admin_Settings::add_message("AccessClient is met succes geactiveerd.");
        $response_body = wp_remote_retrieve_body($response);
        $json = json_decode($response_body);

        return $json->token;
    }
}

function generate_ticket_number($base_url, $headers, $body) {
    $url = "{$base_url}/tickets";
    $response = wp_remote_request( $url, array(
        'method'      => 'POST',
        'httpversion' => '1.0',
        'timeout'     => 45,
        'redirection' => 15,
        'sslverify'   => false,
        'blocking'    => true,
        'headers'     => $headers,
        'body'        => $body,
        )
    );

    if ( is_wp_error($response) ) {
        return 'Error: ' . $response->get_error_message();
    } else {
        $response_body = wp_remote_retrieve_body($response);
        $json = json_decode($response_body);
        return $json->ticketNumber;
    }
}

function process_ticket($base_url, $headers, $ticketNumber, $orderId) {
    $url = "$base_url/tickets/$ticketNumber/process?orderId=$orderId";
    $response = wp_remote_request( $url, array(
        'method'      => 'POST',
        'httpversion' => '1.0',
        'timeout'     => 45,
        'redirection' => 15,
        'sslverify'   => false,
        'blocking'    => true,
        'headers'     => $headers,
        'body'        => array(),
        )
    );

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $json = json_decode($response_body);
    $error = null;
    
    switch ($response_code) {
        case 200:
            if ($json->actuallyProcessed) {
                $tx = $json->transaction;
                // Using the transaction number is preferred.
                // But in case it is disabled in Cyclos, return the internal identifier.
                return empty($tx->transactionNumber) ? $tx->id : $tx->transactionNumber; 
            }
            return NULL;
        case 401:
            $error = "Geen inloggegevens";
            break;
        case 403:
            $error = "Toegang geweigerd";
            break;
        case 404:
            $error = "Ticket niet gevonden";
            break;
        case 422:
            $error = "Ongeldig ticket";
            break;
        case 500:
            // An error has occurred generating the payment
            if ($json->code == 'insufficientBalance') {
                $error = 'Niet genoeg saldo.';
                break;
            } else if ($json->code == 'destinationUpperLimitReached') {
                $error = 'Maximale kredietlimiet bereikt.';
                break;
            } else {
                // There are more error codes but for now only these two
                // Log a detailed error
                error_log("An unexpected error has occurred processing the ticket (type = {$json->exceptionType}, message = {$json->exceptionMessage})");
            }
        default:
            $error = "Er is een onbekende fout opgetreden: ($response_code)";
            break;
    }
    
    // There was an error
    throw new Exception($error);
}


function console_log($output) {    
    echo "<script>console.log(" . json_encode($output, JSON_HEX_TAG) . ");</script>";
}

?>