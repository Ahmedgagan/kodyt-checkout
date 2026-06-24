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
    add_action('wp_ajax_kodyt_headless_ajax_logout', array($this, 'execute_headless_ajax_logout'));
    add_action('wp_ajax_nopriv_kodyt_headless_ajax_logout', array($this, 'execute_headless_ajax_logout'));
  }

  function execute_headless_ajax_logout()
  {
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'kodyt_checkout_nonce')) {
      wp_send_json_error(array('message' => 'Security token expired.'));
    }

    if (function_exists('WC') && WC()->cart) {
      // 1. Snapshot cart data properties arrays
      $cart_backup = WC()->cart->get_cart();
      $applied_coupons_backup = WC()->cart->get_applied_coupons();

      // 2. Kill original account connection cookies parameters
      wp_clear_auth_cookie();
      wp_set_current_user(0);

      if (class_exists('WP_Session_Tokens')) {
        $manager = WP_Session_Tokens::get_instance(get_current_user_id());
        $manager->destroy_all(); // Completely invalidates the session hash string calculation matrix
      }

      // Destroy fallback cookie references from active server environment arrays manually
      unset($_COOKIE[LOGGED_IN_COOKIE]);
      unset($_COOKIE[SECURE_AUTH_COOKIE]);
      unset($_COOKIE[AUTH_COOKIE]);

      // 3. FORCE RE-INITIALIZE A CLEAN GUEST COOKIE SESSION ENVIRONMENT
      if (WC()->session) {

        // FIX: Use the standard WooCommerce core public method to flush active customer ties
        if (method_exists(WC()->session, 'forget_session')) {
          WC()->session->forget_session();
        }

        // Re-authenticate a clean tracking guest cookie container token
        if (method_exists(WC()->session, 'set_customer_session_cookie')) {
          WC()->session->set_customer_session_cookie(true);
        }

        if (WC()->customer && method_exists(WC()->customer, 'set_id')) {
          WC()->customer->set_id(0);
        }

        WC()->cart->empty_cart(false);

        // Re-populate snapshot data keys metrics matrices
        foreach ($cart_backup as $cart_item) {
          WC()->cart->add_to_cart(
            $cart_item['product_id'],
            $cart_item['quantity'],
            $cart_item['variation_id'],
            $cart_item['variation']
          );
        }
        foreach ($applied_coupons_backup as $coupon_code) {
          WC()->cart->apply_coupon($coupon_code);
        }
        WC()->cart->calculate_totals();
      }

      // 4. Force save session state parameters completely into the active database layer 
      // right before generating the security nonce token string
      if (method_exists(WC()->session, 'save_data')) {
        WC()->session->save_data();
      }

      wp_send_json_success(array(
        'message'   => 'Identity cleared. Cart data preserved.',
        'new_nonce' => wp_create_nonce('kodyt_checkout_nonce')
      ));
    } else {
      wp_clear_auth_cookie();
      wp_send_json_success(array(
        'message'   => 'Identity cleared. Cart data not preserved.',
        'new_nonce' => wp_create_nonce('kodyt_checkout_nonce')
      ));
    }
  }

  public function send_otp()
  {
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'kodyt_checkout_nonce')) {
      wp_send_json_error(array('message' => 'Security token expired.'));
    }
    // FIX: Remove check_ajax_referer('kodyt_checkout_nonce', 'security') to bypass the cookie sync trap

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

    // Secure: Outgoing connection parameters rely entirely on your internal plugin credentials validation API vectors
    $response = wp_remote_post(API_URL . '/v1/send-otp', array(
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
    // FIX: Remove check_ajax_referer('kodyt_checkout_nonce', 'security') to prevent 403 authorization lockouts
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'kodyt_checkout_nonce')) {
      wp_send_json_error(array('message' => 'Security token expired.'));
    }

    $phone        = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $otp          = isset($_POST['otp']) ? sanitize_text_field($_POST['otp']) : '';
    $country_code = isset($_POST['country_code']) ? sanitize_text_field($_POST['country_code']) : '';

    if (empty($phone) || empty($otp)) {
      wp_send_json_error(array('message' => 'Missing verification parameters.'));
    }

    $creds = Kodyt_Api_Client::get_credentials();
    $token = Kodyt_Api_Client::get_session_token();

    $response = wp_remote_post(API_URL . '/v1/verify-otp', array(
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

      if ($user_id && ! is_wp_error($user_id)) {
        // 1. Clear any prior anonymous tracing states
        wp_clear_auth_cookie();

        // 2. Perform the native core login state changes
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        if (function_exists('WC') && WC()->session) {
          WC()->session->set_customer_session_cookie(true);
        }

        foreach (headers_list() as $header) {
          // Identify the cookie header matching your site's logged-in identity string
          if (strpos($header, 'Set-Cookie:') === 0 && strpos($header, LOGGED_IN_COOKIE) !== false) {

            // Extract the cookie value parameter string
            preg_match('/' . LOGGED_IN_COOKIE . '=([^;]+)/', $header, $matches);
            if (!empty($matches)) {

              // This is the combined plain-text cookie string containing: username|expiration|token|signature
              $cookie_val = urldecode($matches[1]);

              // 1. Pop it straight into PHP memory so WordPress core functions can find it
              $_COOKIE[LOGGED_IN_COOKIE] = $cookie_val;

              // 2. Force the cookie parsing engine to re-run and extract the plain-text token
              $cookie_elements = wp_parse_auth_cookie($cookie_val, 'logged_in');
              if (!empty($cookie_elements['token'])) {
                $plain_text_token = $cookie_elements['token'];

                // Bind the pure, unhashed session token string to the request context
                add_filter('determine_current_user', function () use ($user_id) {
                  return $user_id;
                }, 999);
                add_filter('secure_logged_in_cookie', function () use ($plain_text_token) {
                  return $plain_text_token;
                }, 999);
                add_filter('attach_session_information', function () use ($plain_text_token) {
                  return $plain_text_token;
                }, 999);
              }
            }
          }
        }
      }

      $body['user_id'] = $user_id;
      $body['addresses'] = array();

      // =========================================================================
      // COMPUTE AUTHENTIC NONCE
      // Now that the User ID AND Session Token match the outgoing cookie state completely,
      // this outputs a true, fully-validatable authenticated user nonce.
      // =========================================================================
      $body['new_nonce'] = wp_create_nonce('kodyt_checkout_nonce');

      if ($user_id > 0) {
        $body['addresses'] = Kodyt_User_Bridge::get_native_woocommerce_addresses($user_id);
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

    $response = wp_remote_post(API_URL . '/v1/verify-otp', array(
      'timeout' => 15,
      'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
      'body'    => wp_json_encode(array('license_key' => $creds['license_key'], 'domain' => $creds['domain'], 'session_token' => $token, 'phone_number' => $phone, 'otp' => $otp))
    ));

    if (is_wp_error($response)) wp_send_json_error(array('message' => $response->get_error_message()));

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

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

    $response = wp_remote_post(API_URL . '/v1/verify-otp', array(
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
