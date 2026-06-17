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

  public static function get_native_woocommerce_addresses()
  {
    if (! function_exists('WC')) return array();
    $user_id = get_current_user_id();
    if ($user_id <= 0) return array();

    $customer = new WC_Customer($user_id);
    if (! $customer) return array();

    $addresses = array();

    // 1. Core Shipping Address Fields
    if (! empty($customer->get_shipping_address_1())) {
      $addresses['shipping'] = array(
        'type'           => 'Default Shipping',
        'first_name'     => $customer->get_shipping_first_name(),
        'last_name'      => $customer->get_shipping_last_name(),
        'company'        => $customer->get_shipping_company(),
        'address_1'      => $customer->get_shipping_address_1(),
        'address_2'      => $customer->get_shipping_address_2(),
        'city'           => $customer->get_shipping_city(),
        'state'           => $customer->get_shipping_state(),
        'postcode'       => $customer->get_shipping_postcode(),
        'phone'          => get_user_meta($customer->get_id(), 'shipping_phone', true) ?: $customer->get_billing_phone(),
        'email'          => $customer->get_billing_email() // Shipping doesn't have a native email field
      );
    }

    // 2. Core Billing Address Fields
    if (! empty($customer->get_billing_address_1())) {
      $addresses['billing'] = array(
        'type'           => 'Default Billing',
        'first_name'     => $customer->get_billing_first_name(),
        'last_name'      => $customer->get_billing_last_name(),
        'company'        => $customer->get_billing_company(),
        'address_1'      => $customer->get_billing_address_1(),
        'address_2'      => $customer->get_billing_address_2(),
        'state'           => $customer->get_billing_state(),
        'city'           => $customer->get_billing_city(),
        'postcode'       => $customer->get_billing_postcode(),
        'phone'          => $customer->get_billing_phone(),
        'email'          => $customer->get_billing_email()
      );
    }

    return $addresses;
  }
}
