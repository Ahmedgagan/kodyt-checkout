<?php if (! defined('ABSPATH')) exit; ?>

<div class="kodyt-checkout-container">

  <?php
  $is_phone_verified = false;
  $pre_filled_phone  = '';
  $country_code      = '';
  $in_memory_user_id = 0;

  if (is_user_logged_in()) {
    $in_memory_user_id = get_current_user_id();
    $saved_phone       = get_user_meta($in_memory_user_id, 'phone_number', true);
    $saved_country     = get_user_meta($in_memory_user_id, 'phone_country_dial_code', true);

    if (! empty($saved_phone)) {
      $pre_filled_phone  = $saved_phone;
      $country_code      = $saved_country;
      $is_phone_verified = true;
    } else {
      $pre_filled_phone = get_user_meta($in_memory_user_id, 'billing_phone', true);
    }
  }
  ?>

  <input type="hidden" id="kodyt_in_memory_user_id" value="<?php echo esc_attr($in_memory_user_id); ?>" />

  <form id="kodyt-custom-checkout-form" method="POST">
    <div class="kodyt-grid">

      <div class="kodyt-steps-column">

        <div class="kodyt-step <?php echo $is_phone_verified ? 'completed' : 'active'; ?>" id="kodyt-step-auth">
          <div class="kodyt-step-header">
            <h5>1. Mobile Verification</h5>
          </div>
          <div class="kodyt-step-body">
            <input type="hidden" id="kodyt_auth_phone" value="<?php echo esc_attr($pre_filled_phone); ?>">
            <div id="kodyt-checkout-phone-interactive-slot">
              <?php
              // Contextually pass attributes down to inner scope components
              include KODYT_CHECKOUT_PATH . 'templates/part-auth-step.php';
              ?>
            </div>
            <div class="kodyt-input-group" id="kodyt-otp-verify-block" style="display:none; margin-top:15px;">
              <input type="number" inputmode="numeric" id="kodyt_otp_code_input" placeholder="Enter OTP" maxlength="6" />
              <button type="button" id="kodyt-btn-verify-otp">Verify OTP</button>
            </div>
          </div>
        </div>

        <div class="kodyt-step <?php echo $is_phone_verified ? 'active' : 'locked'; ?>" id="kodyt-step-shipping">
          <div class="kodyt-step-header">
            <h5>2. Delivery Details</h5>
          </div>
          <div class="kodyt-step-body">
            <?php include KODYT_CHECKOUT_PATH . 'templates/part-delivery-step.php'; ?>
          </div>
        </div>

        <div class="kodyt-step locked" id="kodyt-step-payment">
          <div class="kodyt-step-header">
            <h5>3. Payment Methods</h5>
          </div>
          <div class="kodyt-step-body">
            <?php include KODYT_CHECKOUT_PATH . 'templates/part-payment-step.php'; ?>
          </div>
        </div>

      </div>

      <div class="kodyt-summary-column">
        <div class="kodyt-summary-card">
          <h4>Order Summary</h4>
          <hr class="kodyt-divider" />

          <div class="kodyt-summary-items-list">
            <?php
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
              $_product = $cart_item['data'];
              if ($_product && $_product->exists() && $cart_item['quantity'] > 0) {
                echo '<div class="kodyt-summary-item-row">';
                echo '  <div class="kodyt-summary-item-thumb">' . $_product->get_image() . '<span class="kodyt-summary-item-qty-badge">' . $cart_item['quantity'] . '</span></div>';
                echo '  <div class="kodyt-summary-item-details"><span class="kodyt-summary-item-title">' . esc_html($_product->get_name()) . '</span></div>';
                echo '  <div class="kodyt-summary-item-price-wrap"><span>' . WC()->cart->get_product_subtotal($_product, $cart_item['quantity']) . '</span></div>';
                echo '</div>';
              }
            }
            ?>
          </div>

          <div class="kodyt-coupon-integration-area">
            <div id="kodyt-toggle-coupon-field">
              <span>Add coupons</span>
              <span class="kodyt-coupon-arrow">▼</span>
            </div>
            <div id="kodyt-coupon-slide-container" style="display: none;">
              <div class="kodyt-coupon-input-group">
                <input type="text" id="kodyt-coupon-code-input" placeholder="Promo code" />
                <button type="button" id="kodyt-btn-apply-coupon">Apply</button>
              </div>
              <div id="kodyt-coupon-feedback-msg" style="display: none;"></div>
            </div>
          </div>

          <hr class="kodyt-divider" />
          <div class="kodyt-totals-list">
            <div class="kodyt-total-row"><span>Subtotal:</span><span id="kodyt-calc-subtotal"><?php echo WC()->cart->get_cart_subtotal(); ?></span></div>

            <div id="kodyt-applied-coupons-rows-wrap">
              <?php
              $applied_coupons = WC()->cart->get_applied_coupons();
              if (! empty($applied_coupons)) {
                foreach ($applied_coupons as $coupon_code) {
                  $discount_amount = WC()->cart->get_coupon_discount_amount($coupon_code);
                  echo '<div class="kodyt-total-row coupon-benefit-row" style="color: #10b981; font-weight: 500;">';
                  echo '  <span>Promo (' . esc_html($coupon_code) . '):</span>';
                  echo '  <span>-' . wc_price($discount_amount) . ' <a href="#" class="kodyt-remove-coupon-link" data-coupon="' . esc_attr($coupon_code) . '">[Remove]</a></span>';
                  echo '</div>';
                }
              }
              ?>
            </div>

            <div class="kodyt-total-row final-total"><span>Total:</span><span id="kodyt-calc-grandtotal"><strong><?php echo WC()->cart->get_cart_total(); ?></strong></span></div>
          </div>
        </div>
      </div>

    </div>
  </form>
</div>