<?php
if (! defined('ABSPATH')) exit;

class Kodyt_Abandoned_Cart_Coupon_API
{
  private static $route_namespace = 'kodyt-checkout/v1';
  private static $route_path      = '/generate-recovery-coupon';

  public static function init()
  {
    add_action('rest_api_init', array(__CLASS__, 'register_rest_route'));
    add_filter('woocommerce_coupon_is_valid', array(__CLASS__, 'restrict_coupon_to_specific_user'), 10, 3);
  }

  public static function register_rest_route()
  {
    register_rest_route(self::$route_namespace, self::$route_path, array(
      'methods'             => 'POST',
      'callback'            => array(__CLASS__, 'handle_coupon_generation_request'),
      'permission_callback' => array(__CLASS__, 'validate_custom_wc_auth'),
    ));
  }

  /**
   * Safe Custom Authenticator: Cryptographically matches WooCommerce keys
   * even on local HTTP/MAMP environments without SSL.
   */
  public static function validate_custom_wc_auth(WP_REST_Request $request)
  {
    global $wpdb;

    $consumer_key    = '';
    $consumer_secret = '';

    // 1. Try pulling credentials from Query Parameters (?consumer_key=...&consumer_secret=...)
    if ($request->get_param('consumer_key') && $request->get_param('consumer_secret')) {
      $consumer_key    = $request->get_param('consumer_key');
      $consumer_secret = $request->get_param('consumer_secret');
    }
    // 2. Try pulling credentials from the Basic Auth Headers / Server globals
    else if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
      $consumer_key    = $_SERVER['PHP_AUTH_USER'];
      $consumer_secret = $_SERVER['PHP_AUTH_PW'];
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
      if (preg_match('/Basic\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
        $credentials = explode(':', base64_decode($matches[1]), 2);
        if (count($credentials) === 2) {
          $consumer_key    = $credentials[0];
          $consumer_secret = $credentials[1];
        }
      }
    }

    // If no keys were detected anywhere, reject the request immediately
    if (empty($consumer_key) || empty($consumer_secret)) {
      return new WP_Error('rest_forbidden', __('Missing API credentials.', 'kodyt-checkout'), array('status' => 401));
    }

    $hashed_key = function_exists('wc_api_hash') ? wc_api_hash($consumer_key) : hash('sha256', $consumer_key);
    error_log($hashed_key);
    // 3. Lookup the key data in the native WooCommerce keys table
    $api_key = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM {$wpdb->prefix}woocommerce_api_keys WHERE consumer_key = %s",
      $hashed_key
    ));

    if (! $api_key) {
      return new WP_Error('rest_forbidden', __('Invalid Consumer Key.', 'kodyt-checkout'), array('status' => 401));
    }

    // 4. Verify Permissions (Must have Write access for POST routes)
    if ('read' === $api_key->permissions) {
      return new WP_Error('rest_forbidden', __('Your API key permissions are set to Read-only.', 'kodyt-checkout'), array('status' => 403));
    }

    // 5. Cryptographically match the consumer secret hash
    if (! hash_equals($api_key->consumer_secret, $consumer_secret)) {
      return new WP_Error('rest_forbidden', __('Invalid Consumer Secret mismatch.', 'kodyt-checkout'), array('status' => 401));
    }

    // 6. Login the user context matching this specific key token
    wp_set_current_user($api_key->user_id);
    return true;
  }

  public static function handle_coupon_generation_request(WP_REST_Request $request)
  {
    $mobile_number = sanitize_text_field($request->get_param('mobile'));

    if (empty($mobile_number)) {
      return new WP_Error('kodyt_missing_mobile', __('A target customer mobile number is required.', 'kodyt-checkout'), array('status' => 400));
    }

    // Lookup user by phone
    $user_query = new WP_User_Query(array(
      'meta_query' => array(
        array(
          'key'     => 'phone_number',
          'value'   => $mobile_number,
          'compare' => '='
        )
      ),
      'number' => 1
    ));

    $users = $user_query->get_results();

    if (empty($users)) {
      return new WP_Error('kodyt_user_not_found', __('No user record discovered matching the provided mobile number.', 'kodyt-checkout'), array('status' => 404));
    }

    $user_id = intval($users[0]->ID);

    $discount_type  = get_option('kodyt_cart_recovery_coupon_type', 'percent');
    $discount_value = (float) get_option('kodyt_cart_recovery_coupon_value', 10);

    $expiry_hours = intval(get_option('kodyt_cart_recovery_coupon_expiry_hours', 6));
    $expiry_seconds   = max(1, $expiry_hours) * 3600;
    $expiry_timestamp = current_time('timestamp', true) + $expiry_seconds;

    $random_suffix = strtoupper(wp_generate_password(5, false));
    $coupon_code   = strtoupper('IN' . $user_id . 'X' . $random_suffix);

    try {
      $coupon = new WC_Coupon();
      $coupon->set_code($coupon_code);
      $coupon->set_discount_type($discount_type);
      $coupon->set_amount($discount_value);
      $coupon->set_date_expires($expiry_timestamp);
      $coupon->set_usage_limit(1);
      $coupon->set_individual_use(true);

      $coupon->update_meta_data('_kodyt_restricted_user_id', $user_id);
      $coupon->save();

      return new WP_REST_Response(array(
        'success'       => true,
        'user_id'       => $user_id,
        'coupon_code'   => $coupon_code,
        'discount_type' => $discount_type,
        'amount'        => $discount_value,
        'expiry_hours'  => $expiry_hours,
        'expires_at'    => date('Y-m-d H:i:s', $expiry_timestamp)
      ), 201);
    } catch (Exception $e) {
      error_log($e->getMessage());
      return new WP_Error('kodyt_coupon_creation_failed', __('Unable to generate WooCommerce coupon engine sequence.', 'kodyt-checkout'), array('status' => 500));
    }
  }

  public static function restrict_coupon_to_specific_user($is_valid, $coupon, $discount)
  {
    $allowed_user_id = $coupon->get_meta('_kodyt_restricted_user_id', true);

    if (! empty($allowed_user_id)) {
      $current_user_id = get_current_user_id();

      if (empty($current_user_id) || intval($current_user_id) !== intval($allowed_user_id)) {
        return false;
      }
    }

    return $is_valid;
  }
}

Kodyt_Abandoned_Cart_Coupon_API::init();
