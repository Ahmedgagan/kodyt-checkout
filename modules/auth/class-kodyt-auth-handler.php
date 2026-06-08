<?php
if (! defined('ABSPATH')) exit;

class Kodyt_Auth_Handler
{

  public function __construct()
  {
    add_action('wp_ajax_kodyt_proxy_send_otp', array($this, 'send_otp'));
    add_action('wp_ajax_nopriv_kodyt_proxy_send_otp', array($this, 'send_otp'));
    add_action('wp_ajax_kodyt_proxy_verify_otp', array($this, 'verify_otp'));
    add_action('wp_ajax_nopriv_kodyt_proxy_verify_otp', array($this, 'verify_otp'));
    add_action('wp_ajax_kodyt_account_otp_login', array($this, 'account_otp_login'));
    add_action('wp_ajax_nopriv_kodyt_account_otp_login', array($this, 'account_otp_login'));
    add_action('wp_ajax_kodyt_profile_verify_new_phone', array($this, 'profile_verify_new_phone'));
    add_action('woocommerce_save_account_details_errors', array($this, 'validate_collision_before_save'), 10, 2);
  }

  public function send_otp()
  {
    check_ajax_referer('kodyt_checkout_nonce', 'security');
    $phone        = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $country_code = isset($_POST['country_code']) ? sanitize_text_field($_POST['country_code']) : '';
    $context      = isset($_POST['otp_context']) ? sanitize_text_field($_POST['otp_context']) : 'checkout';

    if (empty($phone)) {
      wp_send_json_error(array('message' => 'No mobile number identified.'));
    }

    if ('profile_change' === $context) {
      $existing = get_users(array('meta_key' => 'phone_number', 'meta_value' => $phone, 'number' => 1));
      if (! empty($existing) && $existing[0]->ID !== get_current_user_id()) {
        wp_send_json_error(array('message' => 'This mobile number is already linked to another profile.'));
      }
    }

    $creds = Kodyt_Api_Client::get_credentials();
    $token = Kodyt_Api_Client::get_session_token();

    $response = wp_remote_post('https://api.kodyt.com/v1/send-otp', array(
      'timeout' => 15,
      'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
      'body'    => wp_json_encode(array(
        'license_key'   => $creds['license_key'],
        'domain'        => $creds['domain'],
        'session_token' => $token,
        'phone_number'  => $phone,
        'country_code'  => $country_code
      ))
    ));

    if (is_wp_error($response)) {
      wp_send_json_error(array('message' => $response->get_error_message()));
    }
    wp_send_json_success(json_decode(wp_remote_retrieve_body($response), true));
  }

  public function verify_otp()
  {
    check_ajax_referer('kodyt_checkout_nonce', 'security');
    $phone        = sanitize_text_field($_POST['phone']);
    $otp          = sanitize_text_field($_POST['otp']);
    $country_code = isset($_POST['country_code']) ? sanitize_text_field($_POST['country_code']) : '';

    $creds = Kodyt_Api_Client::get_credentials();
    $token = Kodyt_Api_Client::get_session_token();

    $response = wp_remote_post('https://api.kodyt.com/v1/verify-otp', array(
      'timeout' => 15,
      'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
      'body'    => wp_json_encode(array('license_key' => $creds['license_key'], 'domain' => $creds['domain'], 'session_token' => $token, 'phone_number' => $phone, 'otp' => $otp))
    ));

    if (is_wp_error($response)) {
      wp_send_json_error(array('message' => $response->get_error_message()));
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($code === 200 && isset($body['verified']) && $body['verified'] === true) {
      $user_id = Kodyt_User_Bridge::resolve_identity($phone, $country_code);
      $body['user_id'] = $user_id;
      $body['addresses'] = array();

      if ($user_id > 0) {
        $shipping_address = get_user_meta($user_id, 'shipping_address_1', true);
        if (! empty($shipping_address)) {
          $body['addresses']['shipping'] = array(
            'type'           => 'Saved Address',
            'first_name'     => get_user_meta($user_id, 'shipping_first_name', true),
            'last_name'      => get_user_meta($user_id, 'shipping_last_name', true),
            'email'          => get_user_meta($user_id, 'billing_email', true),
            'shipping_phone' => get_user_meta($user_id, 'shipping_phone', true) ?: $phone,
            'address_1'      => $shipping_address,
            'house_number'   => get_user_meta($user_id, 'shipping_house_number', true),
            'city'           => get_user_meta($user_id, 'shipping_city', true),
            'postcode'       => get_user_meta($user_id, 'shipping_postcode', true),
            'country'        => get_user_meta($user_id, 'shipping_country', true)
          );
        }
      }
      wp_send_json_success($body);
    }
    wp_send_json_error(array('message' => 'Token rejected.'));
  }

  public function account_otp_login()
  {
    check_ajax_referer('kodyt_checkout_nonce', 'security');
    $phone        = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $otp          = isset($_POST['otp']) ? sanitize_text_field($_POST['otp']) : '';
    $country_code = isset($_POST['country_code']) ? sanitize_text_field($_POST['country_code']) : '';

    $creds = Kodyt_Api_Client::get_credentials();
    $token = Kodyt_Api_Client::get_session_token();

    $response = wp_remote_post('https://api.kodyt.com/v1/verify-otp', array(
      'timeout' => 15,
      'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
      'body'    => wp_json_encode(array('license_key' => $creds['license_key'], 'domain' => $creds['domain'], 'session_token' => $token, 'phone_number' => $phone, 'otp' => $otp))
    ));

    if (is_wp_error($response)) wp_send_json_error(array('message' => $response->get_error_message()));

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    error_log(json_encode($body));
    if ($code === 200 && isset($body['verified']) && $body['verified'] === true) {
      $user_id = Kodyt_User_Bridge::resolve_identity($phone, $country_code);

      if ($user_id && ! is_wp_error($user_id)) {
        wp_clear_auth_cookie();
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        if (function_exists('WC') && WC()->session) {
          WC()->session->set_customer_session_cookie(true);
        }
        wp_send_json_success(array('redirect_url' => wc_get_page_permalink('myaccount')));
      }
    }
    wp_send_json_error(array('message' => 'Validation error token parameters.'));
  }

  public function profile_verify_new_phone()
  {
    check_ajax_referer('kodyt_checkout_nonce', 'security');
    $phone        = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $otp          = isset($_POST['otp']) ? sanitize_text_field($_POST['otp']) : '';
    $country_code = isset($_POST['country_code']) ? sanitize_text_field($_POST['country_code']) : '';

    $existing = get_users(array('meta_key' => 'phone_number', 'meta_value' => $phone, 'number' => 1));
    if (! empty($existing) && $existing[0]->ID !== get_current_user_id()) {
      wp_send_json_error(array('message' => 'Mobile number already linked to an active profile.'));
    }

    $creds = Kodyt_Api_Client::get_credentials();
    $token = Kodyt_Api_Client::get_session_token();

    $response = wp_remote_post('https://api.kodyt.com/v1/verify-otp', array(
      'timeout' => 15,
      'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
      'body'    => wp_json_encode(array('license_key' => $creds['license_key'], 'domain' => $creds['domain'], 'session_token' => $token, 'phone_number' => $phone, 'otp' => $otp))
    ));

    if (is_wp_error($response)) wp_send_json_error(array('message' => $response->get_error_message()));

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($code === 200 && isset($body['verified']) && $body['verified'] === true) {
      $uid = get_current_user_id();
      update_user_meta($uid, 'phone_number', $phone);
      update_user_meta($uid, 'billing_phone', $phone);
      update_user_meta($uid, 'shipping_phone', $phone);
      if (! empty($country_code)) update_user_meta($uid, 'phone_country_dial_code', $country_code);
      wp_send_json_success(array('message' => 'Number validated cleanly.'));
    }
    wp_send_json_error(array('message' => 'Validation token expired.'));
  }

  public function validate_collision_before_save(&$errors, $user)
  {
    if (! isset($_POST['kodyt_profile_phone'])) return;
    $submitted_phone = sanitize_text_field($_POST['kodyt_profile_phone']);
    $current_user_id = get_current_user_id();
    $saved_phone     = get_user_meta($current_user_id, 'phone_number', true);

    if ($submitted_phone !== $saved_phone && ! empty($submitted_phone)) {
      $check = get_users(array('meta_key' => 'phone_number', 'meta_value' => $submitted_phone, 'number' => 1));
      if (! empty($check) && $check[0]->ID !== $current_user_id) {
        $errors->add('phone_number_collision', 'This mobile number is already linked to another active profile.');
        return;
      }
      update_user_meta($current_user_id, 'phone_number', $submitted_phone);
      update_user_meta($current_user_id, 'billing_phone', $submitted_phone);
      update_user_meta($current_user_id, 'shipping_phone', $submitted_phone);
    }
  }
}
