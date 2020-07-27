<?php

// include helper functions
require_once(dirname(__FILE__) . '/utils/ue.php');
require_once(dirname(__FILE__) . '/utils/helper.php');

// initialization function for the gateway
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
			$this->use_accessclient = 'yes' === $this->get_option( 'use_accessclient' );

			$this->root_url = $this->testmode ? 'https://demo.cyclos.org' : 'https://mijn.circuitnederland.nl';
			$this->api_endpoint = $this->root_url . '/api';
			$this->username = $this->testmode ? "ticket" : $this->get_option( 'username' );
			$this->password = $this->testmode ? "1234" : $this->get_option( 'password' );
			$this->accessclient = $this->use_accessclient ? $this->get_option( 'accessclient' ) : NULL;

			// test user credentials if button is clicked
			if(array_key_exists('generateAccesclientButton',$_POST)) {
				if( !empty($_POST['accessClientCode'])) {
					$accesscode = $_POST['accessClientCode'];
					$token = generate_accessclient_token($this->api_endpoint, $accesscode, $this->username, $this->password);
					$this->update_option('accessclient', $token);
					$this->update_option('use_accessclient', 'yes');
				}
			}

			if(array_key_exists('testUserCredentialsButton', $_POST)) {
				test_user_credentials($this->api_endpoint, $this->username, $this->password);
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
					'Content-Transfer-Encoding' 	=> 'application/json',
					'Content-type' 								=> 'application/json;charset=utf-8',
					'Access-Client-Token' 				=> $this->accessclient
				); 
			} else {
				return array(
					'Content-Transfer-Encoding' 	=> 'application/json',
					'Content-type'								=> 'application/json;charset=utf-8',
					'Authorization' 							=> 'Basic '. base64_encode($this->username . ':' . $this->password)
				);
			}
		}

		//Function to generate HTML for accessClient generator in the WP-admin UI
		public function generate_screen_button_html( $key, $data ) {
			$field = $this->plugin_id . $this->id . '_' . $key;
			$defaults = array(
				'class'         => 'button-secondary',
				'desc_tip'      => false,
				'description'   => '',
				'title'         => 'Genereer AccessClient token',
				'button_title'  => 'Genereer token'
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
							<button type="submit" class="<?php echo esc_attr( $data['class'] ); ?>" 
								type="submit" 
								name="generateAccesclientButton" 
								id="generateAccesclientButton" 
							>
								<?php echo wp_kses_post( $data['button_title'] ); ?>
							</button>
							<p class="description">
								Log in op uw Utrechtse Euro account en ga naar:
								<br> Persoonlijk > Instellingen > Webshop koppelingen > toegangscodes > Toevoegen > [Vul een beschrijving in] > Opslaan > Activatiecode > Bevestigen
								<br> Vul de vier-cijferige code hierboven in en klik op <b><?php echo wp_kses_post( $data['button_title'] ); ?></b>.
								<br>
								<br> <u>Als u deze instellingen niet kan vinden in uw U€-account, dan moeten deze instellingen nog geactiveerd worden voor uw.
								<br> Neem daarvoor contact op met de <a href="https://www.utrechtse-euro.nl/" rel="noopener noreferrer" target="_blank">Utrechtse Euro</a>, zij kunnen u verder helpen.</u>
							</p>
						</form>
					</fieldset>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		public function generate_test_credentials_button_html( $key, $data ) {
			$field = $this->plugin_id . $this->id . '_' . $key;
			$defaults = array(
				'class'         => 'button-secondary',
				'desc_tip'      => false,
				'description'   => '',
				'title'         => 'Test inloggegevens',
				'button_title'  => 'Test inloggegevens'
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
						<form method="post" name="testUserCredentialsForm" id="testUserCredentialsForm" action="">
							<input type="hidden" id="testUserCredentials" name="testUserCredentials" value="test">
							<button type="submit" class="<?php echo esc_attr( $data['class'] ); ?>" 
								type="submit" 
								name="testUserCredentialsButton" 
								id="testUserCredentialsButton" 
							>
								<?php echo wp_kses_post( $data['button_title'] ); ?>
							</button>
						</form>
					</fieldset>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}
					
		public function generate_donate_img_html( $key, $data ) {
			$field = $this->plugin_id . $this->id . '_' . $key;
			$defaults = array(
				'desc_tip'      => false,
				'description'   => '',
				'title'         => 'Doneer U€ om bij de dragen aan deze plugin.',
			);
			$data = wp_parse_args( $data, $defaults );

			ob_start();
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
				</th>
				<td>
					<img src="<?php echo plugin_dir_url( __FILE__ ) . 'assets/qr-code.png'?>" alt="doneer middels qr-code" />
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}
						
		public function generate_logo_dev_html( $key, $data ) {
			$field = $this->plugin_id . $this->id . '_' . $key;
			$defaults = array(
				'desc_tip'     => false,
				'description'  => '',
				'title'      	=> 'Deze plugin is mogelijk gemaakt door:',
			);
			$data = wp_parse_args( $data, $defaults );

			ob_start();
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
				</th>
				<td>
					<a href="https://mddd.nl" rel="noopener" target="_blank">
						<img src="<?php echo plugin_dir_url( __FILE__ ) . 'assets/logo_150px.gif'?>" alt="M. D. Design & Development" />
					</a>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		// Plugin options
		public function init_form_fields(){
			$this->form_fields = array(
				'basic_settings_title' 	=> array(
					'title' => __( 'Basis instellingen' ),
					'type'  => 'title',
				),
				'enabled' => array(
					'title'         => 'Activeer/Deactiveer',
					'label'         => 'Activeer U€ Gateway',
					'type'          => 'checkbox',
					'description'   => '',
					'default'       => 'no'
				),
				'testmode' => array(
					'title'         => 'Test mode',
					'label'         => 'Activeer Test Mode',
					'type'          => 'checkbox',
					'description'   => 'gebruikersnaam: demo, wachtwoord: 1234',
					'default'       => 'yes',
					'desc_tip'      => true,
				),
				'username' => array(
					'title'         => 'U€ gebruikersnaam',
					'type'          => 'text',
				),
				'password' => array(
					'title'         => 'U€ wachtwoord',
					'type'          => 'password',
				),
				'testUserCredentials' => array(
					'type'          => 'test_credentials_button',
					'desc_tip'      => 'test uw inloggegevens'
				),
				'use_accessclient' => array(
					'title'         => 'AccessClient',
					'label'         => 'Activeer AccessClient Mode (deze optie wordt geadviseerd, bij het genereren van een token wordt deze optie automatisch geactiveerd.)',
					'type'          => 'checkbox',
					'description'   => 'Gebruik een anoniem token ipv uw gebruikersnaam en wachtwoord als de gebruiker wordt doorgelinkt naar de Utrechtse Euro betalingspagina, deze optie heeft de voorkeur vanwege veiligheidsredenen.',
					'default'       => 'no',
					'desc_tip'      => true,
				),
				'accessClientGenerate' => array(
					'type'          => 'screen_button',
					'desc_tip'      => 'U€ gebruikersnaam, wachtwoord en uw activatie code moeten zijn ingevuld voordat de token gegenereerd kan worden!'
				),
				'accessclient' => array(
					'title'         => 'AccessClient token',
					'type'          => 'password',
					'description'   => 'Hier staat de automatisch gegenereerde anonieme token, hier hoeft u verder niks mee te doen.',
					'desc_tip'      => true,
				),
				'display_settings_title' => array(
					'title'       	=> __( 'Weergave instellingen' ),
					'type'        	=> 'title',
					'description' 	=> 'Pas hier uw weergave instellingen van deze plugin aan. In veel gevallen zijn de standaard waardes voldoende.',
					),
				'title' => array(
					'title'        => 'Titel',
					'type'         => 'text',
					'description'  => 'De titel die de bezoeker tijdens check-out ziet.',
					'default'      => 'U€',
				),
				'description' => array(
					'title'        => 'Beschrijving',
					'type'         => 'textarea',
					'description'  => 'De beschrijving die de bezoeker tijdens check-out ziet.',
					'default'      => 'Betaal met Utrechtse Euro\'s.',
				),
				'donate_title' 	 => array(
					'title'        => __( 'Scan onderstaande QR-code om U€ te doneren om bij te dragen aan de verdere ontwikkeling van deze plugin.' ),
					'type'         => 'title',
				),
				'donate' => array(
					'type'          => 'donate_img',
				),
				'developer' => array(
					'type'          => 'logo_dev',
				)
			);
		}
					
		//Back-end options validation and processing.	
		public function process_admin_options(){
			parent::process_admin_options();
			// if ($_POST['woocommerce_ue_use_accessclient'] == true) {
			// 	if ($_POST['woocommerce_ue_testmode'] == true) {
			// 		WC_Admin_Settings::add_error( 'Error: accessClient niet beschikbaar in testmode.' );
			// 		return false;
			// 	} else {
			// 		if (empty($_POST['woocommerce_ue_accessclient'])){
			// 			WC_Admin_Settings::add_error( 'Error: Uw accessClient is leeg.' );
			// 			return false;
			// 		} else {
			// 			parent::process_admin_options();
			// 			return true;
			// 		}
			// 	}
			// } else {
			// 	if ($_POST['woocommerce_ue_testmode'] == true) {
			// 		parent::process_admin_options();
			// 		return true;
			// 	} else {
			// 		if (empty($_POST['woocommerce_ue_username'])){
			// 			WC_Admin_Settings::add_error( 'Error: Uw gebruikersnaam is niet ingevuld.' );
			// 			return false;
			// 		} else if (empty($_POST['woocommerce_ue_password'])){
			// 			WC_Admin_Settings::add_error( 'Error: Uw wachtwoord is niet ingevuld.' );
			// 			return false;
			// 		} else {
			// 			parent::process_admin_options();
			// 			return true;
			// 		}
			// 	}
			// }
		}
	
		// We're processing the payments here, everything about it is in Step 5
		public function process_payment( $order_id ) {
			global $woocommerce;

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
				wc_add_notice($ticketNumber);
				return false;
			} else {
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
			$ticketNumber = $_GET['ticketNumber'];
			
			try {
				$transactionNumber = process_ticket($this->api_endpoint, $this->headers(), $ticketNumber, $order_id);

				if (!empty($transactionNumber)) {
					$order->payment_complete($transactionNumber);
					$order->reduce_order_stock();
					$note = "Bestelling compleet met transactie-ID: $transactionNumber";
					$order->add_order_note( $note );
				}
			} catch (Exception $e) {
				$order->update_status('Mislukt', sprintf(__('Foutmelding: %1$s'), $e));
				$note = sprintf(__('Foutmelding: %1$s'), $e);
				$order->add_order_note( $note );
			}

			http_response_code(200);

			update_option('webhook_debug', $_GET);
			die();
		} 
 	}
}

?>