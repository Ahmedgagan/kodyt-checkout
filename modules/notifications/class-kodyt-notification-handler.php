<?php
if (! defined('ABSPATH')) exit;

class Kodyt_Notification_Handler
{
  /**
   * Static Trigger: Executed directly from the core checkout logic pipeline.
   * Completely isolated with global try/catch wrappers to guarantee checkout never freezes.
   */
  public static function trigger_whatsapp_order_notification($order_id, $order = null)
  {
    try {
      if (empty($order_id)) {
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

      // 4. Execute the defensive fallback cascading loop architecture 
      $target_numbers = array();

      if ('billing' === $routing_strategy) {
        $target_numbers[] = ! empty($billing_phone) ? $billing_phone : $shipping_phone;
      } elseif ('shipping' === $routing_strategy) {
        $target_numbers[] = ! empty($shipping_phone) ? $shipping_phone : $billing_phone;
      } elseif ('both' === $routing_strategy) {
        if (! empty($billing_phone)) {
          $target_numbers[] = $billing_phone;
        }
        if (! empty($shipping_phone)) {
          $target_numbers[] = $shipping_phone;
        }
      }

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

      $addr_1 = isset($address_parts['address_1']) ? trim($address_parts['address_1']) : '';
      $addr_2 = isset($address_parts['address_2']) ? trim($address_parts['address_2']) : '';
      $city   = isset($address_parts['city'])      ? trim($address_parts['city'])      : '';
      $state  = isset($address_parts['state'])     ? trim($address_parts['state'])     : '';
      $zip    = isset($address_parts['postcode'])  ? trim($address_parts['postcode'])  : '';
      $cc     = isset($address_parts['country'])   ? trim($address_parts['country'])   : '';

      $address_lines = array();

      // Standard checkout behavior checks
      // If address_1 already contains the city or postcode (from a Google Map fill), 
      // we avoid manually double-appending them to keep the string pristine.
      $address_lines[] = $addr_1;
      if (! empty($addr_2)) {
        $address_lines[] = $addr_2;
      }

      if (! empty($city) && stripos($addr_1, $city) === false) {
        $address_lines[] = $city;
      }
      if (! empty($state) && stripos($addr_1, $state) === false) {
        $address_lines[] = $state;
      }
      if (! empty($zip) && stripos($addr_1, $zip) === false) {
        $address_lines[] = $zip;
      }
      if (! empty($cc) && stripos($addr_1, $cc) === false) {
        $address_lines[] = $cc;
      }

      $shipping_address_string = implode(', ', array_filter($address_lines));
      $shipping_address_string = wp_strip_all_tags(html_entity_decode($shipping_address_string));

      // 7. Extract Total Amount
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
          'store_name'       => html_entity_decode(get_bloginfo('name')),
          'customer_phone'   => $phone,
          'items'            => $items_flattened_string,
          'amount'           => $clean_amount_string, // ◄ Passes clean numeric string "1801.00"
          'shipping_address' => $shipping_address_string, // ◄ Passes crisp, de-duplicated address line
          'isUsingCustomKey' => false
        );

        // Perform non-blocking background network post to keep checkout instant
        wp_remote_post('https://api.kodyt.com/v1/orders/create', array(
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
