<?php
if (! defined('ABSPATH')) exit;

$user_id = get_current_user_id();
$current_phone = get_user_meta($user_id, 'phone_number', true);
$country_code = get_user_meta($user_id, 'phone_country_dial_code', true);
$has_phone = ! empty($current_phone);
?>
<fieldset class="kodyt-profile-phone-fieldset" style="margin-top: 30px; border: var(--kodyt-input-border-width) var(--kodyt-border-style) var(--kodyt-input-border-color); border-radius: var(--kodyt-radius); padding: var(--kodyt-step-padding); background: var(--kodyt-step-bg);">
  <legend style="padding: 0 10px; font-weight: 700; color: var(--kodyt-text-heading); font-size: 15px;">
    <?php echo $has_phone ? __('Verified Mobile Number', 'kodyt-checkout') : __('Link Mobile Profile', 'kodyt-checkout'); ?>
  </legend>

  <p style="margin: 0 0 15px 0; font-size: 13px; color: var(--kodyt-text-base); line-height: 1.4;">
    <?php echo $has_phone
      ? __('Your account profile is linked to a verified phone record below.', 'kodyt-checkout')
      : __('Add a mobile number to enable frictionless passwordless authentication down the road.', 'kodyt-checkout'); ?>
  </p>

  <input type="hidden" name="kodyt_profile_phone" id="kodyt_profile_phone_hidden" value="<?php echo esc_attr($current_phone); ?>">

  <div id="kodyt-profile-phone-interactive-slot" style="max-width: 500px;">
    <?php if ($has_phone) : ?>
      <div style="display: flex; align-items: center; justify-content: space-between; background: #f8fafc; padding: 12px 16px; border: 1px solid var(--kodyt-input-border-color); border-radius: var(--kodyt-radius);">
        <span style="font-weight: 600; color: var(--kodyt-text-heading); font-size: 14px;" id="kodyt-profile-phone-display-string">
          <?php echo esc_html("+" . $country_code . " " . $current_phone); ?>
        </span>
        <button type="button" id="kodyt-profile-swap-to-input" class="button" style="cursor: pointer; height: 42px; line-height: 30px; padding: 0 12px; font-size: 16px; background: var(--kodyt-secondary); color: var(--kodyt-secondary-text); border-radius: var(--kodyt-radius); border:none;">
          <?php _e('Change Number', 'kodyt-checkout'); ?>
        </button>
      </div>
    <?php else : ?>
      <button type="button" id="kodyt-profile-swap-to-input" class="button" style="cursor: pointer; height: 42px; padding: 0 20px; font-weight: 600; background-color: var(--kodyt-secondary); color: var(--kodyt-secondary-text); border-radius: var(--kodyt-radius); border:none;">
        <?php _e('Add Mobile Number', 'kodyt-checkout'); ?>
      </button>
    <?php endif; ?>
  </div>
</fieldset>

<div id="kodyt-profile-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); z-index: 99999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
  <div class="kodyt-profile-modal-card" style="background: var(--kodyt-step-bg); padding: 30px; border-radius: var(--kodyt-radius); max-width: 400px; width: 90%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15); border: var(--kodyt-step-border-width) var(--kodyt-border-style) var(--kodyt-input-border-color); position: relative;">
    <span id="kodyt-profile-modal-close" style="position: absolute; top: 15px; right: 15px; font-size: 20px; color: #94a3b8; cursor: pointer; font-weight: 700; line-height: 1;">&times;</span>

    <h4 style="margin: 0 0 10px 0; font-size: 18px; font-weight: 700; color: var(--kodyt-text-heading);"><?php _e('Verify Account Access', 'kodyt-checkout'); ?></h4>
    <p style="margin: 0 0 20px 0; font-size: 13px; color: var(--kodyt-text-base); line-height: 1.4;">
      <?php _e('We sent a verification code token to your new destination number. Enter it below to unlock database mutations.', 'kodyt-checkout'); ?>
    </p>

    <div style="margin-bottom: 15px;">
      <input type="text" id="kodyt_profile_modal_otp" class="input-text" style="width: 100%; height: 44px; text-align: center; font-size: 16px; letter-spacing: 4px; font-weight: 700; border: var(--kodyt-input-border-width) var(--kodyt-border-style) var(--kodyt-input-border-color); border-radius: var(--kodyt-radius); color: var(--kodyt-text-base); background-color: #ffffff;" placeholder="000000" maxlength="6">
    </div>

    <div style="display: flex; gap: 10px; justify-content: flex-end;">
      <button type="button" id="kodyt-profile-modal-btn-cancel" class="button" style="background: #f1f5f9; cursor: pointer; color: #475569; border: 1px solid #cbd5e1; border-radius: var(--kodyt-radius); padding: 20px; font-weight: 600;"><?php _e('Cancel', 'kodyt-checkout'); ?></button>
      <button type="button" id="kodyt-profile-modal-btn-verify" class="button" style="background: var(--kodyt-success); cursor: pointer; color: var(--kodyt-success-text); border-radius: var(--kodyt-radius); border: none; font-weight: 600; padding: 20px;"><?php _e('Confirm & Save', 'kodyt-checkout'); ?></button>
    </div>
  </div>
</div>