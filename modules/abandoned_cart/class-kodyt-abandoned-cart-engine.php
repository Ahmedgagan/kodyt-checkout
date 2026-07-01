<?php
if (! defined('ABSPATH')) exit;

class Kodyt_Abandoned_Cart_Engine
{
  public static function init()
  {
    // 1. Save a real-time modification timestamp whenever a logged-in user changes their cart
    add_action('woocommerce_persistent_cart_stored', array(__CLASS__, 'track_cart_modification_time'), 10, 1);

    // 2. Set up the Twice-Daily Cron Schedule pinned to Peak Indian Shopping Hours
    add_filter('cron_schedules', array(__CLASS__, 'add_custom_cron_interval'));

    // 3. Automation Event Hook
    add_action('process_persistent_carts_event', array(__CLASS__, 'sync_recent_carts_and_generate_coupons'));
  }

  public static function track_cart_modification_time($user_id)
  {
    if (! $user_id) return;
    update_user_meta($user_id, '_last_cart_updated', time());
  }

  public static function add_custom_cron_interval($schedules)
  {
    $schedules['twice_daily_twelve_hours'] = array(
      'interval' => 43200, // Exactly 12 hours in seconds
      'display'  => __('Twice Daily (Every 12 Hours)', 'kodyt-checkout')
    );
    return $schedules;
  }

  public static function sync_recent_carts_and_generate_coupons()
  {
    global $wpdb;
    $current_time = time();
    $twelve_hours_ago = $current_time - 43200;
    $blog_id = get_current_blog_id();

    // --- FETCH DYNAMIC DISCOUNT RULES ---
    $discount_type  = get_option('kodyt_cart_recovery_coupon_type', 'percent');
    $discount_value = (float) get_option('kodyt_cart_recovery_coupon_value', 10);

    // Query users who updated their persistent carts within the execution interval
    $recent_cart_users = $wpdb->get_results($wpdb->prepare(
      "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = '_last_cart_updated' 
             AND meta_value >= %d",
      $twelve_hours_ago
    ));

    if (empty($recent_cart_users)) return;

    foreach ($recent_cart_users as $user) {
      $user_id = intval($user->user_id);
      $meta_key = '_woocommerce_persistent_cart_' . $blog_id;
      $saved_cart = get_user_meta($user_id, $meta_key, true);

      if (! isset($saved_cart['cart']) || empty($saved_cart['cart'])) {
        continue;
      }

      $cart_items = $saved_cart['cart'];
      $formatted_products = array();
      $cart_subtotal = 0;

      foreach ($cart_items as $key => $item) {
        $product_id = intval($item['product_id']);
        $quantity   = intval($item['quantity']);

        $target_id  = ! empty($item['variation_id']) ? intval($item['variation_id']) : $product_id;
        $product    = wc_get_product($target_id);

        if ($product) {
          $price = floatval($product->get_price());
          $line_subtotal = $price * $quantity;
          $cart_subtotal += $line_subtotal;

          $formatted_products[] = array(
            'product_id'   => $product_id,
            'variation_id' => intval($item['variation_id']),
            'quantity'     => $quantity,
            'name'         => $product->get_name(),
            'unit_price'   => $price,
            'line_total'   => $line_subtotal
          );
        }
      }

      if (empty($formatted_products)) continue;

      $user_phone = get_user_meta($user_id, 'phone_number', true);

      // 5. Send payload structures out to external remote framework endpoint
      $api_url = API_URL . "";

      wp_remote_post($api_url, array(
        'method'    => 'POST',
        'timeout'   => 15,
        'headers'   => array('Content-Type' => 'application/json; charset=utf-8'),
        'body'      => wp_json_encode(array(
          'user_id'         => $user_id,
          'user_phone'      => $user_phone,
          'store_name'      => html_entity_decode(get_bloginfo('name')),
          'total_amount'   => $cart_subtotal,
          'items'        => $formatted_products
        )),
        'sslverify' => false
      ));
    }
  }
}

// Fire the automation framework
Kodyt_Abandoned_Cart_Engine::init();
