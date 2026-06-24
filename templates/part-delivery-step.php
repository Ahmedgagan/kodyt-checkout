<?php
if (! defined('ABSPATH')) exit;

// Fetch verified status checks natively across imports
$is_verified = isset($is_phone_verified) && $is_phone_verified;
$uid = isset($in_memory_user_id) ? $in_memory_user_id : 0;
$shipping_phone = $is_verified ? WC()->customer->get_shipping_phone() : null;

if (!isset($shipping_phone)) {
  if (isset($pre_filled_phone)) {
    $shipping_phone = "+" . $country_code . $pre_filled_phone;
  } else {
    $shipping_phone = '';
  }
} else {
  $shipping_phone = "+" . $shipping_phone;
}
?>

<div id="kodyt-saved-addresses-target" style="display:none; visibility: hidden; height:0; overflow:hidden;">
  <?php
  if ($is_verified && $uid > 0) {
    $native_addresses = Kodyt_User_Bridge::get_native_woocommerce_addresses();

    if (! empty($native_addresses['shipping'])) {
      echo '<div class="kodyt-addresses-vertical-drawer-stack">';
      $is_first = true;

      foreach ($native_addresses['shipping'] as $addr) {
        $formatted_phone = $addr['phone'];

        $row_class = $is_first ? 'kodyt-drawer-address-row-card selected-row-default' : 'kodyt-drawer-address-row-card';
        $checked_attr = $is_first ? 'checked' : '';
        $type_badge_label = (!empty($addr['type']) && strpos(strtolower($addr['type']), 'shipping') !== false) ? 'Home' : 'Office';

        echo '<div class="' . $row_class . '"
            data-fname="' . esc_attr($addr['first_name']) . '"
            data-lname="' . esc_attr($addr['last_name']) . '"
            data-email="' . esc_attr($addr['email']) . '"
            data-sphone="' . esc_attr($formatted_phone) . '"
            data-addr1="' . esc_attr($addr['address_1']) . '"
            data-addr2="' . esc_attr($addr['address_2']) . '"
            data-city="' . esc_attr($addr['city']) . '"
            data-state="' . esc_attr($addr['state']) . '"
            data-postcode="' . esc_attr($addr['postcode']) . '">';

        echo '  <div class="kodyt-row-card-right-details" style="position: relative;">';
        echo '     <div class="kodyt-card-name-row">';
        echo '         <strong>' . esc_html($addr['first_name'] . ' ' . $addr['last_name']) . '</strong>';
        echo '         <span class="badge-type-home">' . esc_html($type_badge_label) . '</span>';
        echo '         <button type="button" class="kodyt-checkout-edit-address-trigger" title="Edit Address" style="position: absolute; right: 0; top: -2px; background: none !important; border: none !important; color: #64748b !important; font-size: 16px !important; cursor: pointer !important; padding: 0 !important; width: auto !important; height: auto !important; font-weight: bold !important;">⋮</button>';
        echo '     </div>';

        $full_lines_address = array_filter(array($addr['address_2'], $addr['address_1'], $addr['city'], $addr['state']));
        echo '     <p>' . esc_html(implode(', ', $full_lines_address)) . ' - ' . esc_html($addr['postcode']) . '</p>';
        echo '     <button type="button" class="kodyt-btn-deliver-here-action-trigger" style="margin-top:10px;">Deliver Here</button>';
        echo '  </div>';
        echo '</div>';

        $is_first = false;
      }

      echo '</div>';
    }
  }
  ?>
</div>

<div class="kodyt-form-row">
  <input type="text" name="kodyt_shipping_first_name" id="kodyt_shipping_first_name" value="<?php echo esc_attr($is_verified ? WC()->customer->get_shipping_first_name() : ''); ?>" placeholder="First Name" required />
  <input type="text" name="kodyt_shipping_last_name" id="kodyt_shipping_last_name" value="<?php echo esc_attr($is_verified ? WC()->customer->get_shipping_last_name() : ''); ?>" placeholder="Last Name" required />
</div>

<div class="kodyt-form-row" style="margin-top:15px;">
  <input type="text" name="kodyt_shipping_address_2" id="kodyt_shipping_address_2" value="<?php echo esc_attr($is_verified ? WC()->customer->get_shipping_address_2() : ''); ?>" placeholder="House / Flat / Office No. *" required />
  <div id="kodyt_shipping_postcode_container">
    <input type="text" name="kodyt_shipping_postcode" id="kodyt_shipping_postcode" value="<?php echo esc_attr($is_verified ? WC()->customer->get_shipping_postcode() : ''); ?>" placeholder="Postal Code" required />
  </div>
</div>

<div class="kodyt-autocomplete-wrapper" style="margin-top:15px;">
  <input type="text" id="kodyt_shipping_autocomplete" name="kodyt_shipping_address_1" value="<?php echo esc_attr($is_verified ? WC()->customer->get_shipping_address_1() : ''); ?>" placeholder="Type to search shipping address..." autocomplete="off" required />
  <div id="kodyt_shipping_suggestions" class="kodyt-suggestions-box"></div>
</div>

<div class="kodyt-form-row" style="margin-top:15px;">
  <input type="text" name="kodyt_shipping_city" id="kodyt_shipping_city" value="<?php echo esc_attr($is_verified ? WC()->customer->get_shipping_city() : ''); ?>" placeholder="City" required />
  <input type="text" name="kodyt_shipping_state" id="kodyt_shipping_state" value="<?php echo esc_attr($is_verified ? WC()->customer->get_shipping_state() : ''); ?>" placeholder="State" required />
</div>

<button type="button" id="kodyt-btn-checkout-save-drawer-address" class="kodyt-checkout-primary-cta-button" style="margin-top:25px; width:100%;">Save and Deliver Here</button>