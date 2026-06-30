<?php
if (! defined('ABSPATH')) exit;
?>

<div class="kodyt-payment-gateways-list" style="display: flex; flex-direction: column; gap: 12px; margin-top: 15px;">
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

    // --- FETCH DYNAMIC ADMIN ADJUSTMENT VALUES ---
    $cod_fee_amount      = (float) get_option('kodyt_settings_cod_fee', 100);
    $prepaid_disc_amount = (float) get_option('kodyt_settings_prepaid_discount', 100);

    foreach ($gateways as $gateway) {
      $is_active = ($gateway->id === $active_session_gateway);
      $checked   = $is_active ? 'checked' : '';

      // Modern card styling states based on active selection
      $card_border = $is_active ? '2px solid #6366f1' : '1px solid #e2e8f0';
      $card_bg     = $is_active ? '#f8fafc' : '#ffffff';

      // --- COMPUTE PREMIUM PILL TAGS ---
      $badge_html = '';
      if ('cod' === $gateway->id) {
        if ($cod_fee_amount > 0) {
          $badge_html = '<span class="kodyt-kiwi-badge" style="background: #fef2f2; color: #ef4444; border: 1px solid #fee2e2; padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 600; letter-spacing: -0.2px;">+' . strip_tags(wc_price($cod_fee_amount)) . ' Fee</span>';
        }
      } else {
        if ($prepaid_disc_amount > 0) {
          $badge_html = '<span class="kodyt-kiwi-badge" style="background: #f0fdf4; color: #16a34a; border: 1px solid #dcfce7; padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 600; letter-spacing: -0.2px;">Save ' . strip_tags(wc_price($prepaid_disc_amount)) . '</span>';
        }
      }

      // Output modern selectable card layout
      echo '<label class="kodyt-gateway-card" style="display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border: ' . $card_border . '; background-color: ' . $card_bg . '; border-radius: 12px; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">';

      echo '  <div style="display: flex; align-items: center; gap: 14px;">';
      // Native radio container hidden or custom styled via Kiwi guidelines
      echo '    <input type="radio" name="kodyt_payment_method" value="' . esc_attr($gateway->id) . '" ' . $checked . ' style="accent-color: #6366f1; width: 18px; height: 18px; cursor: pointer; margin: 0;" />';
      echo '    <span style="font-size: 15px; font-weight: 600; color: #0f172a; letter-spacing: -0.3px;">' . esc_html($gateway->get_title()) . '</span>';
      echo '  </div>';

      echo    $badge_html;

      echo '</label>';
    }
  } else {
    echo '<p class="kodyt-no-gateways-msg" style="padding: 16px; text-align: center; color: #64748b; font-size: 14px; background: #f1f5f9; border-radius: 8px;">No payment methods currently available. Please setup options or contact store administration.</p>';
  }
  ?>
</div>

<button type="submit" id="kodyt-btn-place-order" style="margin-top: 24px; width: 100%; padding: 16px; background-color: #6366f1; color: #ffffff; border: none; border-radius: 12px; font-size: 16px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2); transition: background-color 0.2s ease;">Complete Secure Checkout</button>