<?php
if (! defined('ABSPATH')) exit;
?>

<div class="kodyt-payment-gateways-list">
  <?php
  $gateways = WC()->payment_gateways->get_available_payment_gateways();
  if (! empty($gateways)) {

    // 1. Fetch the active user choice saved on the server session engine
    $active_session_gateway = WC()->session ? WC()->session->get('chosen_payment_method') : '';

    // Fallback default: If session is completely unassigned, seed the first available gateway ID key
    if (empty($active_session_gateway)) {
      $first_gateway_element = reset($gateways);
      $active_session_gateway = $first_gateway_element ? $first_gateway_element->id : '';

      // Seed the user session baseline immediately so fees register accurately on the initial drawing frame
      if (WC()->session) {
        WC()->session->set('chosen_payment_method', $active_session_gateway);
      }
    }

    foreach ($gateways as $gateway) {
      // 2. CRITICAL MATCH VERIFICATION: Explicitly check the row if it matches the live operational state token
      $checked = ($gateway->id === $active_session_gateway) ? 'checked' : '';

      echo '<label class="kodyt-gateway-row">';
      echo '  <input type="radio" name="kodyt_payment_method" value="' . esc_attr($gateway->id) . '" ' . $checked . ' />';
      echo '  <span>' . esc_html($gateway->get_title()) . '</span>';
      echo '</label>';
    }
  } else {
    echo '<p class="kodyt-no-gateways-msg">No payment methods currently available. Please setup options or contact store administration.</p>';
  }
  ?>
</div>

<button type="submit" id="kodyt-btn-place-order" style="margin-top: 20px; width: 100%;">Complete Secure Checkout</button>