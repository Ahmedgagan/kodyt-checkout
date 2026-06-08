<?php
if (! defined('ABSPATH')) exit;

class Kodyt_Location_Handler
{

  public function __construct()
  {
    add_action('wp_ajax_kodyt_proxy_autocomplete', array($this, 'proxy_autocomplete'));
    add_action('wp_ajax_nopriv_kodyt_proxy_autocomplete', array($this, 'proxy_autocomplete'));
    add_action('wp_ajax_kodyt_proxy_validate_address', array($this, 'proxy_validate_address'));
    add_action('wp_ajax_nopriv_kodyt_proxy_validate_address', array($this, 'proxy_validate_address'));
  }

  public function proxy_autocomplete()
  {
    check_ajax_referer('kodyt_checkout_nonce', 'security');
    $query = sanitize_text_field($_POST['q']);

    $creds = Kodyt_Api_Client::get_credentials();
    $token = Kodyt_Api_Client::get_session_token();

    $url = add_query_arg(array(
      'license_key'   => $creds['license_key'],
      'domain'        => $creds['domain'],
      'session_token' => $token,
      'input'         => urlencode($query)
    ), 'https://api.kodyt.com/v1/autocomplete');

    $response = wp_remote_get($url, array('timeout' => 15));
    if (ob_get_length()) ob_clean();

    if (is_wp_error($response)) wp_send_json_error(array('message' => $response->get_error_message()));

    $raw_body = wp_remote_retrieve_body($response);
    $outer_array = json_decode($raw_body, true);
    $suggestions_data = array();

    if (is_array($outer_array) && isset($outer_array['body'])) {
      $suggestions_data = is_string($outer_array['body']) ? json_decode($outer_array['body'], true) : $outer_array['body'];
    } else {
      $suggestions_data = json_decode($raw_body, true);
    }

    wp_send_json_success(array('suggestions' => is_array($suggestions_data) ? $suggestions_data : array()));
  }

  public function proxy_validate_address()
  {
    check_ajax_referer('kodyt_checkout_nonce', 'security');
    $place_id = isset($_POST['place_id']) ? sanitize_text_field($_POST['place_id']) : '';
    $text     = isset($_POST['text']) ? sanitize_text_field($_POST['text']) : '';

    $creds = Kodyt_Api_Client::get_credentials();
    $token = Kodyt_Api_Client::get_session_token();

    $response = wp_remote_post('https://api.kodyt.com/v1/validate', array(
      'timeout' => 15,
      'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
      'body'    => wp_json_encode(array('license_key' => $creds['license_key'], 'domain' => $creds['domain'], 'session_token' => $token, 'place_id' => $place_id, 'text' => $text))
    ));

    if (ob_get_length()) ob_clean();
    if (is_wp_error($response)) wp_send_json_error(array('message' => $response->get_error_message()));

    $body = json_decode(wp_remote_retrieve_body($response), true);
    wp_send_json_success(array('address' => isset($body['address']) ? $body['address'] : $body));
  }
}
