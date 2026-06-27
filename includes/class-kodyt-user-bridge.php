<?php
if (! defined('ABSPATH')) exit;

class Kodyt_User_Bridge
{

  public static function resolve_identity($phone, $country_code = '')
  {
    $current_user_id = get_current_user_id();

    $existing_users = get_users(array(
      'meta_key'   => 'phone_number',
      'meta_value' => $phone,
      'number'     => 1
    ));
    $matching_user_id = $existing_users ? $existing_users[0]->ID : 0;

    if ($current_user_id && $matching_user_id && ($current_user_id !== $matching_user_id)) {
      $customer_orders = wc_get_orders(array('customer' => $matching_user_id, 'limit' => -1));
      foreach ($customer_orders as $order) {
        $order->set_customer_id($current_user_id);
        $order->save();
      }
      if (! user_can($matching_user_id, 'manage_options')) {
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($matching_user_id, $current_user_id);
      }
      return $current_user_id;
    }

    if (! $current_user_id && $matching_user_id) {
      if (! empty($country_code)) {
        update_user_meta($matching_user_id, 'phone_country_dial_code', $country_code);
      }
      return $matching_user_id;
    }

    if (! $current_user_id && ! $matching_user_id) {
      $user_id = wp_create_user('user_' . time() . rand(10, 99), wp_generate_password(), $phone . '@otp.local');
      if (! is_wp_error($user_id)) {
        update_user_meta($user_id, 'phone_number', $phone);
        if (! empty($country_code)) {
          update_user_meta($user_id, 'phone_country_dial_code', $country_code);
        }
        return $user_id;
      }
    }
    return $current_user_id ?: 0;
  }

  public static function get_native_woocommerce_addresses($user_id = null)
  {
    if (! function_exists('WC')) return array();

    if (!$user_id) $user_id = get_current_user_id();

    if ($user_id <= 0) return array();

    $customer = new WC_Customer($user_id);
    if (! $customer) return array();

    $creds = class_exists('Kodyt_Api_Client') ? Kodyt_Api_Client::get_credentials() : array('license_key' => '', 'domain' => '');

    $auth_phone = get_user_meta($user_id, 'phone_number', true);

    if (! $auth_phone) return array();
    // $license_key = get_option('kodyt_checkout_license_key', 'test-key-1234');
    // $site_domain = wp_parse_url(home_url(), PHP_URL_HOST);

    // Formulate clean destination request URL
    $api_url = sprintf(
      API_URL . '/v1/addresses?mobile_number=%s&license_key=%s&domain=%s',
      urlencode($auth_phone),
      urlencode($creds['license_key']),
      urlencode($creds['domain'])
    );

    $response = wp_remote_get($api_url, array('timeout' => 8, 'sslverify' => false));

    if (is_wp_error($response)) return array();

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    if (200 !== $response_code || empty($response_body)) return array();

    $addresses = array();

    $addresses['shipping'] = $response_body['addresses'];

    return $addresses;
  }
}
