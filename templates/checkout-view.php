<?php if (! defined('ABSPATH')) exit; ?>

<div class="kodyt-checkout-container kodyt-checkout-canvas-shell">

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

  $cart_item_count = WC()->cart->get_cart_contents_count();
  $total_discount  = WC()->cart->get_discount_total();
  ?>

  <input type="hidden" id="kodyt_in_memory_user_id" value="<?php echo esc_attr($in_memory_user_id); ?>" />
  <input type="hidden" id="kodyt_auth_phone" value="<?php echo esc_attr($pre_filled_phone); ?>" />

  <form id="kodyt-custom-checkout-form" method="POST">

    <div class="kodyt-checkout-sticky-navbar-strip">
      <div id="kodyt-summary-toggle-bar">
        <div class="kodyt-nav-left-branding">
          <button type="button" class="kodyt-nav-back-button">❮</button>
          <div class="kodyt-nav-merchant-identity">
            <strong><?php echo esc_html(strtoupper(get_bloginfo('name'))); ?></strong>
          </div>
        </div>

        <div class="kodyt-nav-right-metrics-trigger">
          <div class="kodyt-nav-items-count-text">
            <?php echo $cart_item_count; ?> items
          </div>
          <div class="kodyt-nav-pricing-cluster">
            <strong id="kodyt-toggle-bar-grandtotal"><?php echo WC()->cart->get_cart_total(); ?></strong>
          </div>
          <span class="kodyt-summary-arrow">▼</span>
        </div>
      </div>

      <div id="kodyt-summary-dropdown-panel" style="display: none;">
        <div class="kodyt-checkout-summary-dropdown-card">
          <div class="kodyt-summary-items-list">
            <?php
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
              $_product = $cart_item['data'];
              if ($_product && $_product->exists() && $cart_item['quantity'] > 0) {
                echo '<div class="kodyt-summary-item-row">';
                echo '  <div class="kodyt-summary-item-thumb">' . $_product->get_image() . '<span class="kodyt-summary-item-qty-badge">' . $cart_item['quantity'] . '</span></div>';
                echo '  <div class="kodyt-summary-item-details">';
                echo '    <span class="kodyt-summary-item-title">' . esc_html($_product->get_name()) . '</span>';
                echo '  </div>';
                echo '  <div class="kodyt-summary-item-price-wrap"><span>' . WC()->cart->get_product_subtotal($_product, $cart_item['quantity']) . '</span></div>';
                echo '</div>';
              }
            }
            ?>
          </div>

          <hr class="kodyt-divider" />

          <div class="kodyt-totals-list">
            <div class="kodyt-total-row"><span>MRP Total</span><span><?php echo WC()->cart->get_cart_subtotal(); ?></span></div>
            <div id="kodyt-applied-coupons-rows-wrap">
              <?php
              $applied_coupons = WC()->cart->get_applied_coupons();
              if (! empty($applied_coupons)) {
                foreach ($applied_coupons as $coupon_code) {
                  $discount_amount = WC()->cart->get_coupon_discount_amount($coupon_code);
                  echo '<div class="kodyt-total-row coupon-benefit-row" style="color: #00b074; font-weight: 600;">';
                  echo '  <span>Discount on MRP (' . esc_html($coupon_code) . ')</span>';
                  echo '  <span>-' . wc_price($discount_amount) . '</span>';
                  echo '</div>';
                }
              }
              ?>
            </div>
            <div class="kodyt-total-row"><span>Shipping</span><span style="color: #00b074; font-weight: 600;">FREE</span></div>
            <div class="kodyt-total-row final-total"><span>To Pay</span><span id="kodyt-calc-grandtotal"><strong><?php echo WC()->cart->get_cart_total(); ?></strong></span></div>
          </div>
        </div>
      </div>
    </div>

    <div id="kodyt-flow-screen-one-auth" style="<?php echo $is_phone_verified ? 'display: none;' : 'display: block;'; ?>">
      <div class="kodyt-checkout-white-card kodyt-auth-box-wrapper">
        <div class="kodyt-auth-box-prompt-header">
          <div class="kodyt-profile-text-header-block">
            <strong>Login to continue</strong>
            <p>Enter mobile number to enable passwordless checkouts</p>
          </div>
        </div>

        <div id="kodyt-checkout-phone-interactive-slot">
          <?php include KODYT_CHECKOUT_PATH . 'templates/part-auth-step.php'; ?>
        </div>

        <button type="button" id="kodyt-checkout-btn-auth-continue" class="kodyt-checkout-primary-cta-button" style="margin-top: 20px;">Continue</button>
      </div>
    </div>

    <div id="kodyt-flow-screen-two-workspace" style="<?php echo $is_phone_verified ? 'display: block;' : 'display: none;'; ?>">

      <div class="kodyt-checkout-white-card">
        <div class="kodyt-section-label-heading-gray">OFFERS & REWARDS</div>
        <div class="kodyt-coupon-integration-area">
          <div class="kodyt-coupon-input-group">
            <input type="text" id="kodyt-coupon-code-input" placeholder="Enter coupon code" />
            <button type="button" id="kodyt-btn-apply-coupon">Apply</button>
          </div>
          <div id="kodyt-coupon-feedback-msg" style="display: none;"></div>
        </div>
      </div>

      <div class="kodyt-checkout-white-card" id="kodyt-workspace-address-summary-node">
        <div class="kodyt-section-label-heading-gray">DELIVERY DETAILS</div>
        <div class="kodyt-address-summary-display-flex">
          <div class="kodyt-pin-marker-icon">📍</div>
          <div class="kodyt-address-summary-string-lines">
            <strong id="kodyt-summary-hydrate-fullname">Add Address</strong>
            <p id="kodyt-summary-hydrate-addresslines">Please configure shipping coordinates below...</p>
            <span id="kodyt-summary-hydrate-phonenumber"></span>
          </div>
          <button type="button" id="kodyt-checkout-trigger-change-address" class="kodyt-checkout-secondary-mini-action-button">Change</button>
        </div>
      </div>

      <div class="kodyt-checkout-white-card" id="kodyt-payment-methods-card-node" style="margin-bottom: 20px;">
        <div class="kodyt-section-label-heading-gray">PAYMENT OPTIONS</div>
        <?php include KODYT_CHECKOUT_PATH . 'templates/part-payment-step.php'; ?>
      </div>

    </div>

    <div id="kodyt-modal-overlay-otp" class="kodyt-checkout-modal-overlay" style="display:none;">
      <div class="kodyt-checkout-bottom-sheet-card animate-bottom-sheet">
        <button type="button" class="kodyt-modal-close-trigger-circle">&times;</button>
        <div class="kodyt-modal-graphic-lock-header-shield">🔒</div>
        <h4 class="kodyt-modal-h4-title">Verify number securely</h4>
        <p class="kodyt-modal-p-subtitle">Your details are safe with us ⛨</p>

        <div class="kodyt-modal-otp-sub-prompt-instructions">
          Verify mobile number<br>
          <span>Enter OTP sent to <strong id="kodyt-target-otp-display-string">+91-XXXXXXXXXX</strong></span>
          <button type="button" id="kodyt-back-to-input-phone" style="background:none; border:none; color:#4f46e5; padding:0; font-size:12px; font-weight:700; cursor:pointer; text-decoration:underline;">Edit</button>
        </div>

        <div class="kodyt-digit-otp-inputs-grid-wrap">
          <input type="text" class="kodyt-otp-digit-cell" maxlength="1" inputmode="numeric" data-index="1" />
          <input type="text" class="kodyt-otp-digit-cell" maxlength="1" inputmode="numeric" data-index="2" />
          <input type="text" class="kodyt-otp-digit-cell" maxlength="1" inputmode="numeric" data-index="3" />
          <input type="text" class="kodyt-otp-digit-cell" maxlength="1" inputmode="numeric" data-index="4" />
          <input type="text" class="kodyt-otp-digit-cell" maxlength="1" inputmode="numeric" data-index="5" />
          <input type="text" class="kodyt-otp-digit-cell" maxlength="1" inputmode="numeric" data-index="6" />
        </div>

        <div id="kodyt-modal-otp-countdown-ticker" class="kodyt-modal-resend-countdown-string">Resend OTP in 00:60</div>
      </div>
    </div>

    <div id="kodyt-modal-overlay-address-drawer" class="kodyt-checkout-modal-overlay" style="display:none;">
      <div class="kodyt-checkout-bottom-sheet-card animate-bottom-sheet">
        <button type="button" class="kodyt-modal-close-trigger-circle">&times;</button>

        <div class="kodyt-drawer-header-row-flex">
          <h4>Select Delivery Address</h4>
          <button type="button" id="kodyt-checkout-btn-new-address-toggle" class="kodyt-checkout-add-new-address-action-button">+ Add New Address</button>
        </div>

        <div id="kodyt-modal-address-drawer-target-stack" style="margin-top:20px; max-height:340px; overflow-y:auto;">
        </div>
      </div>
    </div>

    <div id="kodyt-modal-overlay-address-editor-pane" class="kodyt-checkout-modal-overlay" style="display:none;">
      <div class="kodyt-checkout-bottom-sheet-card animate-bottom-sheet" style="max-height: 85vh; overflow-y: auto;">
        <button type="button" class="kodyt-modal-close-trigger-circle">&times;</button>

        <div class="kodyt-drawer-header-row-flex" style="margin-bottom: 20px;">
          <h4 id="kodyt-address-drawer-action-headline-title">Add Shipping Address</h4>
        </div>

        <div class="kodyt-drawer-form-fields-wrapper-context">
          <?php include KODYT_CHECKOUT_PATH . 'templates/part-delivery-step.php'; ?>
        </div>
      </div>
    </div>

    <div class="kodyt-checkout-footer-compliance-wrapper">
      <div class="kodyt-footer-powered-by-row">
        <span>Powered By</span>
        <strong class="kodyt-footer-brand-text">kodyt checkout</strong>
      </div>
      <div class="kodyt-footer-badge-compliance-row-flex">
        <div class="kodyt-compliance-badge-item">✓ PCI DSS Certified</div>
        <div class="kodyt-compliance-badge-item">🔒 Secured Payments</div>
        <div class="kodyt-compliance-badge-item">✓ Verified Merchant</div>
      </div>
    </div>

  </form>
</div>