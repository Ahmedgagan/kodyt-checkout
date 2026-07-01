<?php
if (! defined('ABSPATH')) exit;

class Kodyt_Abandoned_Cart_Coupon_API
{
  private static $route_namespace = 'kodyt-checkout/v1';
  private static $route_path      = '/generate-recovery-coupon';

  public static function init()
  {
    add_action('rest_api_init', array(__CLASS__, 'register_rest_route'));

    // Hook into the native WooCommerce coupon validation engine
    add_filter('woocommerce_coupon_is_valid', array(__CLASS__, 'restrict_coupon_to_specific_user'), 10, 3);
  }

  public static function register_rest_route()
  {
    register_rest_route(self::$route_namespace, self::$route_path, array(
      'methods'             => 'POST',
      'callback'            => array(__CLASS__, 'handle_coupon_generation_request'),
      // 'permission_callback' => '__return_true',
      'permission_callback' => array(__CLASS__, 'validate_native_wc_auth'),
    ));
  }

  public static function validate_native_wc_auth()
  {
    // If Basic Auth failed or no keys were sent, current_user_id() will return 0
    if (! get_current_user_id()) {
      return new WP_Error(
        'rest_forbidden',
        __('Invalid or missing API credentials.', 'text-domain'),
        array('status' => 401)
      );
    }

    // Check if the authenticated API key user has permission to manage WooCommerce
    if (! current_user_can('manage_woocommerce')) {
      return new WP_Error(
        'rest_forbidden',
        __('Your API key does not have write permissions.', 'text-domain'),
        array('status' => 403)
      );
    }

    return true; // Authentication and authorization passed!
  }


  /**
   * Core Controller: Receives a mobile number, looks up the User ID, and builds the coupon.
   */
  public static function handle_coupon_generation_request(WP_REST_Request $request)
  {
    $mobile_number = sanitize_text_field($request->get_param('mobile'));

    if (empty($mobile_number)) {
      return new WP_Error('kodyt_missing_mobile', __('A target customer mobile number is required.', 'kodyt-checkout'), array('status' => 400));
    }

    // --- LOOKUP USER ID BY MOBILE PHONE NUMBER ---
    // Searches standard billing phone records or user account profile meta keys
    $user_query = new WP_User_Query(array(
      'meta_query' => array(
        array(
          'key'     => 'phone_number', // Adjust if you use a custom OTP tracking key
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

    // Fetch baseline configuration overrides or defaults
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

      // Save the clean restriction variable inside metadata instead of email arrays
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

  /**
   * WooCommerce Interceptor Hook: Validates that only the matched User ID can check out using this coupon code.
   */
  public static function restrict_coupon_to_specific_user($is_valid, $coupon, $discount)
  {
    // Pull the protected restriction data if it exists on the item
    $allowed_user_id = $coupon->get_meta('_kodyt_restricted_user_id', true);

    if (! empty($allowed_user_id)) {
      $current_user_id = get_current_user_id();

      // Invalidate the coupon entirely if the user is a guest or IDs don't match
      if (empty($current_user_id) || intval($current_user_id) !== intval($allowed_user_id)) {
        return false;
      }
    }

    return $is_valid;
  }
}

Kodyt_Abandoned_Cart_Coupon_API::init();
