<?php
if (! defined('ABSPATH')) exit;

class Kodyt_Coupon_Handler
{

  public function __construct()
  {
    add_action('wp_ajax_kodyt_apply_checkout_coupon', array($this, 'apply_coupon'));
    add_action('wp_ajax_nopriv_kodyt_apply_checkout_coupon', array($this, 'apply_coupon'));
    add_action('wp_ajax_kodyt_remove_checkout_coupon', array($this, 'remove_coupon'));
    add_action('wp_ajax_nopriv_kodyt_remove_checkout_coupon', array($this, 'remove_coupon'));
  }

  /**
   * Safe helper method to synchronize and force re-evaluate checkout fees
   * based on the user's active session choices right before calculating totals.
   */
  private function force_sync_payment_method_and_fees()
  {
    if (! function_exists('WC') || ! WC()->cart) return;

    // Check if the current frontend layout sent over a structural payment method
    $chosen_gateway = '';
    if (isset($_POST['payment_method'])) {
      $chosen_gateway = sanitize_text_field($_POST['payment_method']);
      WC()->session->set('chosen_payment_method', $chosen_gateway);
    } else {
      $chosen_gateway = WC()->session ? WC()->session->get('chosen_payment_method') : '';
    }

    // Force run your payment method fee calculators before running general total matrices
    WC()->cart->calculate_fees();
  }

  /**
   * Formats the real-time custom grand total math layout cleanly to ensure
   * it returns the same correct configuration across the entire stack.
   */
  private function calculate_exact_grandtotal_string()
  {
    if (! function_exists('WC') || ! WC()->cart) return '$0.00';

    $kodyt_cart_base  = (float) WC()->cart->get_cart_contents_total();
    $kodyt_fees_total = 0;

    foreach (WC()->cart->get_fees() as $fee) {
      $kodyt_fees_total += (float) $fee->amount;
    }

    $final_calculated_total = $kodyt_cart_base + $kodyt_fees_total;
    if ($final_calculated_total < 0) {
      $final_calculated_total = 0;
    }

    return wc_price($final_calculated_total);
  }

  private function render_rows_html()
  {
    ob_start();
    $applied_coupons = WC()->cart->get_applied_coupons();
    if (! empty($applied_coupons)) {
      foreach ($applied_coupons as $code) {
        $discount_amount = WC()->cart->get_coupon_discount_amount($code);
        echo '<div class="kodyt-total-row coupon-benefit-row" style="color: #10b981; font-weight: 500;">';
        echo '  <span>Promo (' . esc_html($code) . '):</span>';
        echo '  <span>-' . wc_price($discount_amount) . ' <a href="#" class="kodyt-remove-coupon-link" data-coupon="' . esc_attr($code) . '">[Remove]</a></span>';
        echo '</div>';
      }
    }
    return ob_get_clean();
  }

  public function apply_coupon()
  {
    check_ajax_referer('kodyt_checkout_nonce', 'security');
    $coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field($_POST['coupon_code']) : '';

    if (empty($coupon_code)) {
      wp_send_json_error(array('message' => 'Please enter a valid coupon code.'));
    }

    $applied = WC()->cart->apply_coupon($coupon_code);

    // --- INTEGRATION FIX ---
    $this->force_sync_payment_method_and_fees();
    WC()->cart->calculate_totals();

    if (is_wp_error($applied)) wp_send_json_error(array('message' => $applied->get_error_message()));
    if (! WC()->cart->has_discount($coupon_code)) wp_send_json_error(array('message' => 'Coupon could not be applied.'));

    wp_send_json_success(array(
      'message'    => 'Coupon applied successfully!',
      'subtotal'   => WC()->cart->get_cart_subtotal(),
      'grandtotal' => $this->calculate_exact_grandtotal_string(), // Matches your theme's clean custom math framework
      'rows_html'  => $this->render_rows_html()
    ));
  }

  public function remove_coupon()
  {
    check_ajax_referer('kodyt_checkout_nonce', 'security');
    $coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field($_POST['coupon_code']) : '';

    if (! empty($coupon_code)) {
      WC()->cart->remove_coupon($coupon_code);
    }

    // --- INTEGRATION FIX ---
    $this->force_sync_payment_method_and_fees();
    WC()->cart->calculate_totals();

    wp_send_json_success(array(
      'subtotal'   => WC()->cart->get_cart_subtotal(),
      'grandtotal' => $this->calculate_exact_grandtotal_string(), // Matches your theme's clean custom math framework
      'rows_html'  => $this->render_rows_html()
    ));
  }
}
