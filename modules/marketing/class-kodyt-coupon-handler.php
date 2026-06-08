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
    WC()->cart->calculate_totals();

    if (is_wp_error($applied)) wp_send_json_error(array('message' => $applied->get_error_message()));
    if (! WC()->cart->has_discount($coupon_code)) wp_send_json_error(array('message' => 'Coupon could not be applied.'));

    wp_send_json_success(array(
      'message'    => 'Coupon applied successfully!',
      'subtotal'   => WC()->cart->get_cart_subtotal(),
      'grandtotal' => WC()->cart->get_cart_total(),
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
    WC()->cart->calculate_totals();

    wp_send_json_success(array(
      'subtotal'   => WC()->cart->get_cart_subtotal(),
      'grandtotal' => WC()->cart->get_cart_total(),
      'rows_html'  => $this->render_rows_html()
    ));
  }
}
