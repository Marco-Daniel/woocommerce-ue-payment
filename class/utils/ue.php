<?php

//
// This file contains http requests specific to the U€ api
//

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
      'body'        => json_encode($body),
    )
  );

  if ( is_wp_error($response) ) {
      return 'Error: ' . $response->get_error_message();
  } else {
    $response_body = wp_remote_retrieve_body($response);
    $json = json_decode($response_body);

    $response_code = wp_remote_retrieve_response_code($response);

    switch ($response_code) {
        case 201:
        // succes
        return $json->ticketNumber;
        default:
        // handle error
        break;
    }
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

?>