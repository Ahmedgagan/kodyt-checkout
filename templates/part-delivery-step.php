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

<div id="kodyt-saved-addresses-target" style="<?php echo $is_verified ? 'display:block;' : 'display:none;'; ?> margin-bottom:20px;">
  <?php
  if ($is_verified && $uid > 0) {
    // Calling our infrastructure processor method layer securely
    $native_addresses = Kodyt_User_Bridge::get_native_woocommerce_addresses();
    if (! empty($native_addresses)) {
      echo '<div class="kodyt-saved-addresses-wrapper">';
      echo '<p class="kodyt-section-label">Use your saved address records:</p>';
      echo '<div class="kodyt-addresses-grid">';
      foreach ($native_addresses as $addr) {

        echo '<div class="kodyt-address-card selected"
                data-fname="' . esc_attr($addr['first_name']) . '"
                data-lname="' . esc_attr($addr['last_name']) . '"
                data-email="' . esc_attr($addr['email']) . '"
                data-sphone="' . esc_attr("+" . $addr['shipping_phone']) . '"
                data-addr1="' . esc_attr($addr['address_1']) . '"
                data-hnumber="' . esc_attr($addr['house_number']) . '"
                data-city="' . esc_attr($addr['city']) . '"
                data-postcode="' . esc_attr($addr['postcode']) . '"
                data-country="' . esc_attr($addr['country']) . '">';
        echo '<span class="kodyt-address-type">' . esc_html($addr['type']) . '</span>';
        echo '<strong>' . esc_html($addr['first_name'] . ' ' . $addr['last_name']) . '</strong>';
        echo '<p>' . esc_html($addr['house_number'] . ', ' . $addr['address_1'] . ', ' . $addr['city']) . '</p>';
        echo '<span class="kodyt-badge">Selected</span>';
        echo '</div>';
      }
      echo '</div></div>';
    }
  }
  ?>
</div>

<div class="kodyt-address-section-header">
  <h6>Shipping Address</h6>
</div>

<div class="kodyt-form-row">
  <input type="text" name="kodyt_shipping_first_name" id="kodyt_shipping_first_name" value="<?php echo esc_attr($is_verified ? WC()->customer->get_shipping_first_name() : ''); ?>" placeholder="First Name" required />
  <input type="text" name="kodyt_shipping_last_name" id="kodyt_shipping_last_name" value="<?php echo esc_attr($is_verified ? WC()->customer->get_shipping_last_name() : ''); ?>" placeholder="Last Name" required />
</div>

<div class="kodyt-form-row" style="margin-top:15px;">
  <input type="email" name="kodyt_shipping_email" id="kodyt_shipping_email" value="<?php echo esc_attr($is_verified ? WC()->customer->get_billing_email() : ''); ?>" placeholder="Email Address" required />
  <input type="tel" name="kodyt_shipping_phone" id="kodyt_shipping_phone" value="<?php echo esc_attr(isset($shipping_phone) ? $shipping_phone : ''); ?>" placeholder="Shipping Mobile Number (Whatsapp)" required />
</div>

<div class="kodyt-form-row" style="margin-top:15px;">
  <input type="text" name="kodyt_shipping_house_number" id="kodyt_shipping_house_number" value="<?php echo esc_attr($is_verified ? get_user_meta(WC()->customer->get_id(), 'shipping_house_number', true) : ''); ?>" placeholder="House / Flat / Office No. *" required />
</div>

<div class="kodyt-autocomplete-wrapper" style="margin-top:15px;">
  <input type="text" id="kodyt_shipping_autocomplete" name="kodyt_shipping_address_1" value="<?php echo esc_attr($is_verified ? WC()->customer->get_shipping_address_1() : ''); ?>" placeholder="Type to search shipping address..." autocomplete="off" required />
  <div id="kodyt_shipping_suggestions" class="kodyt-suggestions-box"></div>
</div>

<div class="kodyt-form-row" style="margin-top:15px;">
  <input type="text" name="kodyt_shipping_city" id="kodyt_shipping_city" value="<?php echo esc_attr($is_verified ? WC()->customer->get_shipping_city() : ''); ?>" placeholder="City" required />
  <input type="text" name="kodyt_shipping_postcode" id="kodyt_shipping_postcode" value="<?php echo esc_attr($is_verified ? WC()->customer->get_shipping_postcode() : ''); ?>" placeholder="Postal Code" required />
  <input type="text" name="kodyt_shipping_country" id="kodyt_shipping_country" value="<?php echo esc_attr($is_verified ? WC()->customer->get_shipping_country() : ''); ?>" placeholder="Country Code (e.g. IN, US)" required />
</div>

<div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #f1f5f9;">
  <label class="kodyt-checkbox-label">
    <input type="checkbox" id="kodyt_different_billing" name="kodyt_different_billing" value="1" />
    <span>My billing address is different from shipping details</span>
  </label>
</div>

<div id="kodyt-billing-address-block" style="display: none; margin-top: 20px; padding: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
  <div class="kodyt-address-section-header">
    <h6>Billing Address</h6>
  </div>

  <div class="kodyt-autocomplete-wrapper">
    <input type="text" id="kodyt_billing_autocomplete" name="kodyt_billing_address_1" placeholder="Type to search billing location address..." autocomplete="off" />
    <div id="kodyt_billing_suggestions" class="kodyt-suggestions-box"></div>
  </div>

  <div class="kodyt-form-row" style="margin-top: 15px;">
    <input type="text" name="kodyt_billing_house_number" id="kodyt_billing_house_number" placeholder="House / Unit No." />
    <input type="text" name="kodyt_billing_city" id="kodyt_billing_city" placeholder="City" />
  </div>

  <div class="kodyt-form-row" style="margin-top: 15px;">
    <input type="email" name="kodyt_billing_email" id="kodyt_billing_email" placeholder="Billing Email Address" />
    <input type="tel" name="kodyt_billing_phone" id="kodyt_billing_phone" placeholder="Billing Mobile Number" />
  </div>

  <div class="kodyt-form-row" style="margin-top: 15px;">
    <input type="text" name="kodyt_billing_postcode" id="kodyt_billing_postcode" placeholder="Postcode" />
    <input type="text" name="kodyt_billing_country" id="kodyt_billing_country" placeholder="Country" />
  </div>
</div>

<button type="button" id="kodyt-btn-shipping-mock" style="margin-top:25px;">Continue to Payment</button>