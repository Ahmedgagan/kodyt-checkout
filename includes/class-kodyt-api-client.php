<?php
if (! defined('ABSPATH')) exit;

class Kodyt_Api_Client
{

  public static function get_credentials()
  {
    return array(
      'license_key' => sanitize_text_field(get_option('kodyt_checkout_license_key', '')),
      'domain'      => sanitize_text_field(wp_parse_url(home_url(), PHP_URL_HOST) ?: $_SERVER['HTTP_HOST'])
    );
  }

  public static function get_session_token()
  {
    $creds = self::get_credentials();
    if (empty($creds['license_key'])) {
      wp_send_json_error(array('message' => 'License Key missing.'));
    }

    $session_url = add_query_arg(array(
      'license_key' => urlencode($creds['license_key']),
      'domain'      => urlencode($creds['domain'])
    ), API_URL . '/v1/start-session');

    $response = wp_remote_get($session_url, array('timeout' => 15, 'sslverify' => false));
    if (is_wp_error($response)) {
      wp_send_json_error(array('message' => $response->get_error_message()));
    }

    $raw_body = trim(wp_remote_retrieve_body($response));
    $body = json_decode($raw_body, true);
    $token = isset($body['session_token']) ? $body['session_token'] : '';

    if (empty($token) && preg_match('/[0-9a-f]{8}-([0-9a-f]{4}-){3}[0-9a-f]{12}/i', $raw_body, $matches)) {
      $token = $matches[0];
    }

    return sanitize_text_field($token);
  }
}
