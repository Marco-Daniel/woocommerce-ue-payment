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

            $this->root_url = $this->testmode ? 'https://demo.cyclos.org/' : "https://mijn.circuitnederland.nl/";
            $this->username = $this->testmode ? "ticket" : $this->get_option( 'api_username' );
            $this->password = $this->testmode ? "1234" : $this->get_option( 'api_password' );
            $this->accessclient = $this->use_accessclient ? $this->get_option( 'accessclient' ) : NULL;
 

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
							<button onclick="$('#accessClientForm').submit();" class="<?php echo esc_attr( $data['class'] ); ?>" type="submit" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php echo $this->get_custom_attribute_html( $data ); ?>>Genereer AccessClient</button>
							<p id="accessClientKey"class="description" style="color:red;"><?php echo $GLOBALS['GeneratedAccessClientToken']; ?></p>
							<p class="description">
                                <u>Vul uw activatiecode in en klik op Genereer Accesscode </u> 
                                <br> Log in op uw Utrechtse Euro account en ga naar:
                                <br> Persoonlijk > Webshop > toegangscodes > Toevoegen > [Vul een beschrijving in] > Opslaan > Activatiecode > Bevestigen
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
                    'desc_tip'      => true,
                ),
                'accessClientGenerate' => array(
                    'type'          => 'screen_button',
                    'desc_tip'   => 'Utrechtse Euro gebruikersnaam en wachtwoord moeten zijn ingevuld voordat de token gegenereerd kan worden. Vergeet deze niet op te slaan na het generenen.'
                ),
                'accessclient' => array(
                    'title'         => 'Accessclient code',
                    'type'          => 'text',
                    'description'   => 'Vergeet niet na het genereren van uw code om deze op te slaan door onderaan de pagina op Opslaan te klikken!',
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