<?php
if (! defined('ABSPATH')) exit;

// Shared partial: Handles verified badge vs interactive input picker rendering
if (isset($is_phone_verified) && $is_phone_verified) : ?>
  <div class="kodyt-verified-phone-container-card">
    <div class="kodyt-verified-text-details">
      <span class="kodyt-meta-eyebrow">Verified Mobile Number</span>
      <span class="kodyt-confirmed-phone-number">
        <?php echo esc_html(!empty($country_code) ? "+" . $country_code . " " . $pre_filled_phone : $pre_filled_phone); ?>
      </span>
    </div>
  </div>
<?php else : ?>
  <div class="kodyt-input-group">
    <input type="tel" inputmode="numeric" inputmode="numeric" id="kodyt_auth_phone_active" maxlength="10" placeholder="Mobile Number" required />
    <button type="button" id="kodyt-btn-send-otp">Send OTP</button>
  </div>
<?php endif; ?>