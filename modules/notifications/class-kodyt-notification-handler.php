<?php
if (! defined('ABSPATH')) exit;

class Kodyt_Notification_Handler
{
  /**
   * Static Trigger: Executed directly from the core checkout logic pipeline.
   * Completely isolated with global try/catch wrappers to guarantee checkout never freezes.
   */
  public static function trigger_whatsapp_order_notification($order_id, $order = null, $address_id = null)
  {
    try {
      if (empty($order_id)) {
        return;
      }

      if (empty($address_id)) {
        return;
      }

      if (! is_a($order, 'WC_Order')) {
        $order = wc_get_order($order_id);
      }

      if (! $order) {
        return;
      }

      // 1. ADMIN ENABLED FILTER: Check if the store owner enabled WhatsApp alerts globally
      if ('yes' !== get_option('kodyt_checkout_enable_whatsapp', 'no')) {
        return;
      }

      // 2. Fetch the store custom layout configuration rule selection
      $routing_strategy = get_option('kodyt_checkout_whatsapp_routing', 'billing');

      // 3. Extract baseline phone properties out of the Order context meta
      $billing_phone  = trim($order->get_billing_phone());
      $shipping_phone = trim($order->get_shipping_phone());
      $profile_phone  = '';
      $customer_id    = $order->get_user_id(); // Gets the WP_User ID linked to this order

      if ($customer_id > 0) {
        // Fetch the clean 'phone_number' string you verified during Step 1
        $profile_phone = trim(get_user_meta($customer_id, 'phone_number', true));
        $profile_dial_code = trim(get_user_meta($customer_id, 'phone_country_dial_code', true));
      }

      // 4. Execute the defensive fallback cascading loop architecture 
      $target_numbers = array($profile_dial_code . $profile_phone);

      // if ('billing' === $routing_strategy) {
      //   $target_numbers[] = ! empty($billing_phone) ? $billing_phone : $shipping_phone;
      // } elseif ('shipping' === $routing_strategy) {
      //   $target_numbers[] = ! empty($shipping_phone) ? $shipping_phone : $billing_phone;
      // } elseif ('profile' === $routing_strategy) {
      //   if (! empty($profile_phone)) {
      //     $target_numbers[] = $profile_dial_code . $profile_phone;
      //   }
      // }

      $target_numbers = array_unique(array_filter($target_numbers));

      if (empty($target_numbers)) {
        return;
      }

      // 5. Build items as a single flattened string
      $items_strings_array = array();
      foreach ($order->get_items() as $item_id => $item) {
        $qty       = $item->get_quantity();
        $prod_name = $item->get_name();
        $items_strings_array[] = $qty . ' X ' . $prod_name;
      }
      $items_flattened_string = implode(', ', $items_strings_array);

      // 6. Format full physical shipping address string representation
      $address_parts = $order->get_address('shipping');
      if (empty($address_parts['address_1'])) {
        $address_parts = $order->get_address('billing');
      }

      $address_lines = array();

      $address_lines['address_2'] = isset($address_parts['address_2']) ? trim($address_parts['address_2']) : '';
      $address_lines['address_1'] = isset($address_parts['address_1']) ? trim($address_parts['address_1']) : '';
      $address_lines['city'] = isset($address_parts['city'])      ? trim($address_parts['city'])      : '';
      $address_lines['state'] = isset($address_parts['state'])      ? trim($address_parts['state'])      : '';
      $address_lines['pincode'] = isset($address_parts['postcode'])   ? trim($address_parts['postcode'])   : '';
      $address_lines['phone'] = ! empty($shipping_phone) ? $shipping_phone : $billing_phone;

      $raw_total = $order->get_total();
      $clean_amount_string = number_format((float)$raw_total, 2, '.', '');

      // 8. Extract remote core API connectivity credential parameters
      $creds = class_exists('Kodyt_Api_Client') ? Kodyt_Api_Client::get_credentials() : array('license_key' => '', 'domain' => '');
      $token = class_exists('Kodyt_Api_Client') ? Kodyt_Api_Client::get_session_token() : '';

      // 9. Loop and dispatch distinct payloads for every unique phone recipient target identified
      foreach ($target_numbers as $phone) {

        // MATCHED SECURELY WITH EXPRESS.JS DESTRUCTURING SCHEMAS
        $payload = array(
          'license_key'      => isset($creds['license_key']) ? $creds['license_key'] : '',
          'domain'           => isset($creds['domain']) ? $creds['domain'] : '',
          'session_token'    => $token,
          'order_id'         => $order_id,
          'store_name'       => html_entity_decode(get_bloginfo('name')),
          'customer_phone'   => $phone,
          'items'            => $items_flattened_string,
          'total_amount'     => $clean_amount_string, // ◄ Passes clean numeric string "1801.00"
          'address_id'       => $address_id, // ◄ Passes crisp, de-duplicated address line
          'isUsingCustomKey' => false
        );
        // Perform non-blocking background network post to keep checkout instant
        wp_remote_post(API_URL . '/v1/orders/create', array(
          'timeout'     => 10,
          'blocking'    => false,
          'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
          'body'        => wp_json_encode($payload),
          'sslverify'   => false
        ));
      }
    } catch (Throwable $e) {
      error_log('Kodyt WhatsApp Notification Handler Crash Error: ' . $e->getMessage());
    }
  }
}
