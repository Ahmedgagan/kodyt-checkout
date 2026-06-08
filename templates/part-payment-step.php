<?php
if (! defined('ABSPATH')) exit;
?>

<div class="kodyt-payment-gateways-list">
  <?php
  $gateways = WC()->payment_gateways->get_available_payment_gateways();
  if (! empty($gateways)) {
    $counter = 0;
    foreach ($gateways as $gateway) {
      $checked = ($counter === 0) ? 'checked' : '';
      echo '<label class="kodyt-gateway-row">';
      echo '  <input type="radio" name="kodyt_payment_method" value="' . esc_attr($gateway->id) . '" ' . $checked . ' />';
      echo '  <span>' . esc_html($gateway->get_title()) . '</span>';
      echo '</label>';
      $counter++;
    }
  } else {
    echo '<p class="kodyt-no-gateways-msg">No payment methods currently available. Please setup options or contact store administration.</p>';
  }
  ?>
</div>

<button type="submit" id="kodyt-btn-place-order" style="margin-top: 20px; width: 100%;">Complete Secure Checkout</button>