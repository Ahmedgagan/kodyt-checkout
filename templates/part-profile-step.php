<?php
if (! defined('ABSPATH')) exit;

$user_id = get_current_user_id();
$current_phone = get_user_meta($user_id, 'phone_number', true);
$country_code = get_user_meta($user_id, 'phone_country_dial_code', true);
$has_phone = ! empty($current_phone);
?>
<fieldset class="kodyt-profile-phone-fieldset" style="margin-top: 30px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; background: #ffffff;">
  <legend style="padding: 0 10px; font-weight: 700; color: #1e293b; font-size: 15px;">
    <?php echo $has_phone ? __('Verified Mobile Number', 'kodyt-checkout') : __('Link Mobile Profile', 'kodyt-checkout'); ?>
  </legend>

  <p style="margin: 0 0 15px 0; font-size: 13px; color: #64748b; line-height: 1.4;">
    <?php echo $has_phone
      ? __('Your account profile is linked to a verified phone record below.', 'kodyt-checkout')
      : __('Add a mobile number to enable frictionless passwordless authentication down the road.', 'kodyt-checkout'); ?>
  </p>

  <input type="hidden" name="kodyt_profile_phone" id="kodyt_profile_phone_hidden" value="<?php echo esc_attr($current_phone); ?>">

  <div id="kodyt-profile-phone-interactive-slot" style="max-width: 500px;">
    <?php if ($has_phone) : ?>
      <div style="display: flex; align-items: center; justify-content: space-between; background: #f8fafc; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 6px;">
        <span style="font-weight: 600; color: #0f172a; font-size: 14px;" id="kodyt-profile-phone-display-string">
          <?php echo esc_html("+" . $country_code . " " . $current_phone); ?>
        </span>
        <button type="button" id="kodyt-profile-swap-to-input" class="button" style="height: 42px; line-height: 30px; padding: 0 12px; font-size: 16px; background: #000000; color: #fff;">
          <?php _e('Change Number', 'kodyt-checkout'); ?>
        </button>
      </div>
    <?php else : ?>
      <button type="button" id="kodyt-profile-swap-to-input" class="button" style="height: 42px; padding: 0 20px; font-weight: 600; background-color: #1e293b; color: #ffffff;">
        <?php _e('Add Mobile Number', 'kodyt-checkout'); ?>
      </button>
    <?php endif; ?>
  </div>
</fieldset>

<div id="kodyt-profile-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); z-index: 99999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
  <div class="kodyt-profile-modal-card" style="background: #ffffff; padding: 30px; border-radius: 12px; max-width: 400px; width: 90%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15); border: 1px solid #e2e8f0; position: relative;">
    <span id="kodyt-profile-modal-close" style="position: absolute; top: 15px; right: 15px; font-size: 20px; color: #94a3b8; cursor: pointer; font-weight: 700; line-height: 1;">&times;</span>

    <h4 style="margin: 0 0 10px 0; font-size: 18px; font-weight: 700; color: #0f172a;"><?php _e('Verify Account Access', 'kodyt-checkout'); ?></h4>
    <p style="margin: 0 0 20px 0; font-size: 13px; color: #64748b; line-height: 1.4;">
      <?php _e('We sent a verification code token to your new destination number. Enter it below to unlock database mutations.', 'kodyt-checkout'); ?>
    </p>

    <div style="margin-bottom: 15px;">
      <input type="text" id="kodyt_profile_modal_otp" class="input-text" style="width: 100%; height: 44px; text-align: center; font-size: 16px; letter-spacing: 4px; font-weight: 700; border: 1px solid #cbd5e1; border-radius: 6px;" placeholder="000000" maxlength="6">
    </div>

    <div style="display: flex; gap: 10px; justify-content: flex-end;">
      <button type="button" id="kodyt-profile-modal-btn-cancel" class="button" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 20px; font-weight: 600;"><?php _e('Cancel', 'kodyt-checkout'); ?></button>
      <button type="button" id="kodyt-profile-modal-btn-verify" class="button" style="background: #10b981; color: #fff; border: none; font-weight: 600; padding: 20px;"><?php _e('Confirm & Save', 'kodyt-checkout'); ?></button>
    </div>
  </div>
</div>