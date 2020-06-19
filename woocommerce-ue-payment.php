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

            $this->root_url = $this->testmode ? 'https://demo.cyclos.org' : "https://mijn.circuitnederland.nl";
            $this->username = $this->testmode ? "ticket" : $this->get_option( 'username' );
            $this->password = $this->testmode ? "1234" : $this->get_option( 'password' );
            $this->accessclient = $this->use_accessclient ? $this->get_option( 'accessclient' ) : NULL;
 
            // if accessclient is retrieved.
            if( !empty($_POST['accessClientCode'])) {
                $accesscode = $_POST['accessClientCode'];
                $token = generateAccessclientToken("{$this->root_url}/api", $accesscode, $this->username, $this->password);
                $this->update_option('accessclient', $token);
                $this->update_option('use_accessclient', 'yes');
                $GLOBALS['GeneratedAccessclientCode'] = $token;
            }
            
            // make settings global
            // $GLOBALS['ue_config'] = [
            //     'root'          => $this->root_url,
            //     'api_root'      => "{$this->root_url}/api",
            //     'accessClient'  => $this->accessclient,
            //     'user'          => $this->username,
            //     'password'      => $this->password,
            // ];
            // $GLOBALS['payment_title'] = $this->title;

            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
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

// helper functions
function generateAccessclientToken( $base_url, $accesscode, $username, $password ) {

    $url = "{$base_url}/clients/activate?code={$accesscode}";

    $headers = array(
        'Content-Transfer-Encoding' => 'application/json',
        'Authorization' => 'Basic '. base64_encode("{$username}:{$password}")
    );
    
    $response = wp_remote_request( $url, array(
        'method'      => 'POST',
        'timeout'     => 45,
        'headers'     => $headers,
        'sslverify'   => false,
        'body'        => array(),
        'httpversion' => '1.0',
        'blocking'    => true,
        'redirection' => 15,
        )
    );

    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        WC_Admin_Settings::add_error("Er ging iets mis: $error_message");
    } else {
        WC_Admin_Settings::add_message("AccessClient is met succes geactiveerd.");
        $response_body = wp_remote_retrieve_body($response);
        $json = json_decode($response_body);

        return $json->token;
    }
}

function includeHeaders($req) {
    $headers = array('Content-Type: application/json');

    if (!empty($config['accessClient'])) {
        array_push(
            $headers, 
            "Access-Client-Token: {$ue_config['accessClient']}"
        );
    } else {
        curl_setopt($req, CURLOPT_USERPWD, "{$ue_config['user']}: {$ue_config['password']}");
    }

    curl_setopt($req, CURLOPT_HTTPHEADER, $headers);
}

// function validateResponse($req, $res) {
//     if ($res === false) {
//         $info = curl_getinfo($req);
//         curl_close($req);
//         echo 'Er is een foutmelding opgetreden: ';
//         echo '<pre>' . print_r($info) . '</pre>';
//         die();
//     }
// }

function debugConsole($output) {    
    echo "<script>console.log(" . json_encode($output, JSON_HEX_TAG) . ");</script>";
}

?>