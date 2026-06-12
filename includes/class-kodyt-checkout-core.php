<?php
if (! defined('ABSPATH')) exit;

class Kodyt_Checkout_Core
{

  public function __construct()
  {
    add_shortcode('kodyt_checkout', array($this, 'render_custom_checkout'));
    add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_assets'));
    add_action('woocommerce_before_customer_login_form', array($this, 'render_unified_inline_otp_login'));
    add_action('woocommerce_after_edit_account_form', array($this, 'render_profile_phone_number_section'));
    add_action('wp_head', array($this, 'inject_global_design_variables'));
    add_action('wp_ajax_kodyt_process_checkout', array($this, 'process_final_checkout'));
    add_action('wp_ajax_nopriv_kodyt_process_checkout', array($this, 'process_final_checkout'));
    add_action('init', array($this, 'register_pending_confirmation_order_status'));
    add_filter('wc_order_statuses', array($this, 'add_pending_confirmation_to_order_statuses'));
  }

  public function register_pending_confirmation_order_status()
  {
    register_post_status('wc-pending-confirm', array(
      'label'                     => _x('Pending Confirmation', 'Order status', 'kodyt-checkout'),
      'public'                    => true,
      'exclude_from_search'       => false,
      'show_in_admin_all_list'    => true,
      'show_in_admin_status_list' => true,
      'label_count'               => _n_noop('Pending Confirmation <span class="count">(%s)</span>', 'Pending Confirmation <span class="count">(%s)</span>', 'kodyt-checkout')
    ));
  }

  /**
   * 2. Inject the custom status into the core WooCommerce order management dropdown views
   */
  public function add_pending_confirmation_to_order_statuses($order_statuses)
  {
    $new_statuses = array();

    // We strategically inject it right after the default 'Pending Payment' status for layout consistency
    foreach ($order_statuses as $key => $status) {
      $new_statuses[$key] = $status;
      if ('wc-pending' === $key) {
        $new_statuses['wc-pending-confirm'] = _x('Pending Confirmation', 'Order status', 'kodyt-checkout');
      }
    }

    return $new_statuses;
  }

  public function inject_global_design_variables()
  {
    if (is_admin()) return;

    // We pass explicit fallback strings directly behind our get_option trackers
    $font          = get_option('kodyt_checkout_font_family') ?: "'Inter', system-ui, -apple-system, sans-serif";
    $base_text     = get_option('kodyt_checkout_base_text_color') ?: '#1e293b';
    $heading_text  = get_option('kodyt_checkout_heading_text_color') ?: '#0f172a';
    $radius        = get_option('kodyt_checkout_border_radius') ?: '8px';

    $primary       = get_option('kodyt_checkout_primary_color') ?: '#6366f1';
    $primary_txt   = get_option('kodyt_checkout_primary_text_color') ?: '#ffffff';
    $hover         = get_option('kodyt_checkout_hover_color') ?: '#4f46e5';

    $secondary     = get_option('kodyt_checkout_secondary_color') ?: '#1e293b';
    $secondary_txt = get_option('kodyt_checkout_secondary_text_color') ?: '#ffffff';

    $b_style       = get_option('kodyt_checkout_border_style') ?: 'solid';
    $step_b_w      = get_option('kodyt_checkout_step_border_width') ?: '1px';
    $input_b_w     = get_option('kodyt_checkout_input_border_width') ?: '1px';
    $input_b_c     = get_option('kodyt_checkout_input_border_color') ?: '#cbd5e1';
    $step_pad      = get_option('kodyt_checkout_step_card_padding') ?: '24px';
    $grid_gap      = get_option('kodyt_checkout_grid_column_gap') ?: '30px';

    $step_bg       = get_option('kodyt_checkout_step_bg') ?: '#ffffff';
    $summary_bg    = get_option('kodyt_checkout_summary_card_bg') ?: '#f8fafc';
    $success       = get_option('kodyt_checkout_success_color') ?: '#22c55e';
    $success_txt   = get_option('kodyt_checkout_success_text_color') ?: '#ffffff';
    $qty_bg        = get_option('kodyt_checkout_qty_badge_bg') ?: '#1e293b';
    $qty_txt       = get_option('kodyt_checkout_qty_badge_text') ?: '#ffffff';
    $sticky_top    = get_option('kodyt_checkout_sticky_top_offset') ?: '20px';

?>
    <style id="kodyt-checkout-design-tokens">
      :root {
        --kodyt-font: <?php echo wp_kses_post($font); ?>;
        --kodyt-text-base: <?php echo esc_html($base_text); ?>;
        --kodyt-text-heading: <?php echo esc_html($heading_text); ?>;
        --kodyt-radius: <?php echo esc_html($radius); ?>;
        --kodyt-primary: <?php echo esc_html($primary); ?>;
        --kodyt-primary-text: <?php echo esc_html($primary_txt); ?>;
        --kodyt-primary-hover: <?php echo esc_html($hover); ?>;
        --kodyt-secondary: <?php echo esc_html($secondary); ?>;
        --kodyt-secondary-text: <?php echo esc_html($secondary_txt); ?>;
        --kodyt-border-style: <?php echo esc_html($b_style); ?>;
        --kodyt-step-border-width: <?php echo esc_html($step_b_w); ?>;
        --kodyt-input-border-width: <?php echo esc_html($input_b_w); ?>;
        --kodyt-input-border-color: <?php echo esc_html($input_b_c); ?>;
        --kodyt-step-padding: <?php echo esc_html($step_pad); ?>;
        --kodyt-grid-gap: <?php echo esc_html($grid_gap); ?>;
        --kodyt-step-bg: <?php echo esc_html($step_bg); ?>;
        --kodyt-summary-bg: <?php echo esc_html($summary_bg); ?>;
        --kodyt-success: <?php echo esc_html($success); ?>;
        --kodyt-success-text: <?php echo esc_html($success_txt); ?>;
        --kodyt-qty-badge-bg: <?php echo esc_html($qty_bg); ?>;
        --kodyt-qty-badge-text: <?php echo esc_html($qty_txt); ?>;
        --kodyt-sticky-top: <?php echo esc_html($sticky_top); ?>;
      }
    </style>
  <?php
  }

  public function render_custom_checkout()
  {
    if (is_admin()) return;
    if (! function_exists('WC') || WC()->cart->is_empty()) {
      return '<div class="kodyt-empty-cart">Your shopping cart is currently empty.</div>';
    }
    nocache_headers();
    ob_start();
    include KODYT_CHECKOUT_PATH . 'templates/checkout-view.php';
    return ob_get_clean();
  }

  public function process_final_checkout()
  {
    check_ajax_referer('kodyt_checkout_nonce', 'security');
    if (! function_exists('WC') || WC()->cart->is_empty()) {
      wp_send_json_error(array('message' => 'Your cart is empty.'));
    }

    parse_str($_POST['form_data'], $posted_data);
    $auth_phone         = isset($posted_data['kodyt_auth_phone']) ? sanitize_text_field($posted_data['kodyt_auth_phone']) : '';
    $user_id            = isset($posted_data['kodyt_in_memory_user_id']) ? intval($posted_data['kodyt_in_memory_user_id']) : 0;
    $auth_dial_code     = isset($posted_data['kodyt_country_dial_code']) ? sanitize_text_field($posted_data['kodyt_country_dial_code']) : '';

    $shipping_dial_code = isset($posted_data['kodyt_shipping_country_dial_code']) ? sanitize_text_field($posted_data['kodyt_shipping_country_dial_code']) : '';
    $billing_dial_code  = isset($posted_data['kodyt_billing_country_dial_code']) ? sanitize_text_field($posted_data['kodyt_billing_country_dial_code']) : ''; // ◄ NEW

    if (empty($auth_phone) || empty($user_id)) {
      wp_send_json_error(array('message' => 'Session expired. Execute step 1 verification again.'));
    }

    $raw_shipping_phone = sanitize_text_field($posted_data['kodyt_shipping_phone']);
    $raw_billing_phone  = isset($posted_data['kodyt_billing_phone']) ? sanitize_text_field($posted_data['kodyt_billing_phone']) : ''; // ◄ NEW

    // Formulate Shipping Number format layers
    $formatted_shipping_phone = (!empty($shipping_dial_code) && strpos($raw_shipping_phone, $shipping_dial_code) !== 0) ? $shipping_dial_code . $raw_shipping_phone : $raw_shipping_phone;

    // Compile Dynamic Shipping Context Args
    $ship_house  = sanitize_text_field($posted_data['kodyt_shipping_house_number']);
    $ship_street = sanitize_text_field($posted_data['kodyt_shipping_address_1']);
    $full_shipping_address = (strpos($ship_street, $ship_house) !== false) ? $ship_street : $ship_house . ', ' . $ship_street;

    $shipping_args = array(
      'first_name' => sanitize_text_field($posted_data['kodyt_shipping_first_name']),
      'last_name'  => sanitize_text_field($posted_data['kodyt_shipping_last_name']),
      'address_1'  => $full_shipping_address,
      'phone'      => $formatted_shipping_phone,
      'city'       => sanitize_text_field($posted_data['kodyt_shipping_city']),
      'postcode'   => sanitize_text_field($posted_data['kodyt_shipping_postcode']),
      'country'    => sanitize_text_field($posted_data['kodyt_shipping_country'])
    );

    // ◄ UPDATED: Conditional routing check for standalone custom billing information layers
    $is_different_billing = isset($posted_data['kodyt_different_billing']) && $posted_data['kodyt_different_billing'] == '1';

    if ($is_different_billing) {
      $formatted_billing_phone = (!empty($billing_dial_code) && strpos($raw_billing_phone, $billing_dial_code) !== 0) ? $billing_dial_code . $raw_billing_phone : $raw_billing_phone;

      $bill_house = sanitize_text_field($posted_data['kodyt_billing_house_number']);
      $bill_street = sanitize_text_field($posted_data['kodyt_billing_address_1']);
      $full_billing_address = (strpos($bill_street, $bill_house) !== false) ? $bill_street : $bill_house . ', ' . $bill_street;

      $billing_args = array(
        'first_name' => $shipping_args['first_name'], // Inherits name layers
        'last_name'  => $shipping_args['last_name'],
        'email'      => sanitize_email($posted_data['kodyt_billing_email']), // Explicit separate billing email
        'phone'      => $formatted_billing_phone, // Explicit separate billing phone
        'address_1'  => $full_billing_address,
        'city'       => sanitize_text_field($posted_data['kodyt_billing_city']),
        'postcode'   => sanitize_text_field($posted_data['kodyt_billing_postcode']),
        'country'    => sanitize_text_field($posted_data['kodyt_billing_country'])
      );
    } else {
      // Default Fallback Scenario: Mirror Auth Profiling details perfectly
      $formatted_auth_phone = (!empty($auth_dial_code) && strpos($auth_phone, $auth_dial_code) !== 0) ? $auth_dial_code . $auth_phone : $auth_phone;

      $billing_args = array(
        'first_name' => $shipping_args['first_name'],
        'last_name'  => $shipping_args['last_name'],
        'email'      => sanitize_email($posted_data['kodyt_shipping_email']),
        'phone'      => $formatted_auth_phone,
        'address_1'  => $shipping_args['address_1'],
        'city'       => $shipping_args['city'],
        'postcode'   => $shipping_args['postcode'],
        'country'    => $shipping_args['country']
      );
    }

    try {
      $order = wc_create_order();
      foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
        $order->add_product($values['data'], $values['quantity'], array('variation' => $values['variation']));
      }

      $order->set_address($billing_args, 'billing');
      $order->set_address($shipping_args, 'shipping');
      $order->set_customer_id($user_id);

      // Sync Database Metadata Caches Securely
      update_user_meta($user_id, 'phone_number', $auth_phone);
      update_user_meta($user_id, 'billing_phone', $formatted_auth_phone);
      update_user_meta($user_id, 'shipping_phone', $formatted_shipping_phone); // ◄ UPDATED

      if (! empty($country_code)) update_user_meta($user_id, 'phone_country_dial_code', $country_code);

      update_user_meta($user_id, 'billing_email', $billing_args['email']);
      update_user_meta($user_id, 'shipping_first_name', $shipping_args['first_name']);
      update_user_meta($user_id, 'shipping_last_name', $shipping_args['last_name']);
      update_user_meta($user_id, 'shipping_address_1', $ship_street);
      update_user_meta($user_id, 'shipping_house_number', $ship_house);
      update_user_meta($user_id, 'shipping_city', $shipping_args['city']);
      update_user_meta($user_id, 'shipping_postcode', $shipping_args['postcode']);
      update_user_meta($user_id, 'shipping_country', $shipping_args['country']);

      if (! empty($auth_dial_code)) update_user_meta($user_id, 'phone_country_dial_code', $auth_dial_code);
      if (! empty($shipping_dial_code)) update_user_meta($user_id, 'shipping_phone_country_dial_code', $shipping_dial_code); // ◄ NEW

      $order->calculate_totals();
      $payment_method = sanitize_text_field($posted_data['kodyt_payment_method']);
      $gateways       = WC()->payment_gateways->get_available_payment_gateways();

      if (isset($gateways[$payment_method])) {
        $order->set_payment_method($gateways[$payment_method]);
      }

      $result = $gateways[$payment_method]->process_payment($order->get_id());

      if (isset($result['result']) && $result['result'] === 'success') {
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        if (WC()->session) WC()->session->set_customer_session_cookie(true);
        WC()->cart->empty_cart();

        $order->update_status('pending-confirm', __('Order created via custom checkout, awaiting WhatsApp customer validation.', 'kodyt-checkout'));
        $order->save();

        if (! class_exists('Kodyt_Notification_Handler')) {
          require_once KODYT_CHECKOUT_PATH . 'modules/notifications/class-kodyt-notification-handler.php';
        }

        // The notification handler now automatically pulls the fully formatted numbers directly out of the order objects!
        Kodyt_Notification_Handler::trigger_whatsapp_order_notification($order->get_id(), $order);
        wp_send_json(array('result' => 'success', 'redirect' => $result['redirect']));
      } else {
        wp_send_json_error(array('message' => 'Payment routing failed.'));
      }
    } catch (Exception $e) {
      wp_send_json_error(array('message' => $e->getMessage()));
    }
  }

  public function enqueue_checkout_assets()
  {
    global $post;

    // Isolate asset distribution strictly to checkout interfaces and user dashboards
    $is_checkout_shortcode = (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'kodyt_checkout'));
    $is_my_account_screen  = (function_exists('is_account_page') && is_account_page());

    if ($is_checkout_shortcode || $is_my_account_screen) {

      $chosen_font = get_option('kodyt_checkout_font_family', 'Inter');

      // Format the name for the API URL
      $formatted_font = str_replace(' ', '+', $chosen_font);

      // Enqueue it safely using the WordPress system
      wp_enqueue_style(
        'user-chosen-font',
        "https://fonts.googleapis.com/css2?family={$formatted_font}:wght@400;700&display=swap",
        array(),
        null // Setting version to null stops WP from breaking the query string
      );

      // =====================================================================
      // 1. THIRD-PARTY DEPENDENCY VECTORS
      // =====================================================================
      wp_enqueue_style(
        'intl-tel-input-css',
        'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.min.css',
        array(),
        '18.2.1'
      );

      wp_enqueue_script(
        'intl-tel-input',
        'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js',
        array(),
        '18.2.1',
        true
      );

      // =====================================================================
      // 2. MODULAR CSS STYLE HOOKS (Sequenced Dependency Chain)
      // =====================================================================
      wp_enqueue_style('kodyt-css-base', KODYT_CHECKOUT_URL . 'assets/css/base-layout.css', array(), '1.3.0');
      wp_enqueue_style('kodyt-css-steps', KODYT_CHECKOUT_URL . 'assets/css/modules/steps.css', array('kodyt-css-base'), '1.3.0');
      wp_enqueue_style('kodyt-css-summary', KODYT_CHECKOUT_URL . 'assets/css/modules/cart-summary.css', array('kodyt-css-base'), '1.3.0');
      wp_enqueue_style('kodyt-css-components', KODYT_CHECKOUT_URL . 'assets/css/modules/ui-components.css', array('kodyt-css-base', 'intl-tel-input-css'), '1.3.0');
      wp_enqueue_style('kodyt-css-responsive', KODYT_CHECKOUT_URL . 'assets/css/responsive.css', array('kodyt-css-base'), '1.3.0');

      // =====================================================================
      // 3. FEATURE-DRIVEN JAVASCRIPT PIPELINE (Safe for any Distributed Site)
      // =====================================================================
      // We load them as separate files, explicitly declaring the loading order using WP dependencies.
      // This makes sure your separate feature files work smoothly without needing ES6 import statements in production.
      wp_enqueue_script(
        'kodyt-js-utils',
        KODYT_CHECKOUT_URL . 'assets/js/utils/helpers.js',
        array('jquery'),
        '1.3.0',
        true
      );

      wp_enqueue_script(
        'kodyt-js-marketing',
        KODYT_CHECKOUT_URL . 'assets/js/modules/marketing.js',
        array('jquery', 'kodyt-js-utils'),
        '1.3.0',
        true
      );

      wp_enqueue_script(
        'kodyt-js-location',
        KODYT_CHECKOUT_URL . 'assets/js/modules/location.js',
        array('jquery', 'kodyt-js-utils'),
        '1.3.0',
        true
      );

      wp_enqueue_script(
        'kodyt-js-auth',
        KODYT_CHECKOUT_URL . 'assets/js/modules/auth.js',
        array('jquery', 'intl-tel-input', 'kodyt-js-utils'),
        '1.3.0',
        true
      );

      // The core file triggers last, running orchestrations over all loaded modules
      wp_enqueue_script(
        'kodyt-js-core',
        KODYT_CHECKOUT_URL . 'assets/js/checkout-core.js',
        array('jquery', 'intl-tel-input', 'kodyt-js-utils', 'kodyt-js-auth', 'kodyt-js-location', 'kodyt-js-marketing'),
        '1.3.0',
        true
      );

      // =====================================================================
      // 4. SECURE DATA LAYER PARAMETER LOCALIZATION
      // =====================================================================
      $license = get_option('kodyt_checkout_license_key', '');
      $domain  = wp_parse_url(home_url(), PHP_URL_HOST);

      // Fetch our custom restriction option from the database
      $custom_countries_array = get_option('kodyt_checkout_allowed_phone_countries', array());

      // FIX: Ensure it is a valid array and NOT empty before enforcing it
      if (is_array($custom_countries_array) && ! empty($custom_countries_array)) {
        $allowed_countries_clean = array_values(array_map('strtolower', $custom_countries_array));
      } else {
        // Default fallback layout rule if no choices are checked on your settings tab
        $allowed_countries_raw = function_exists('WC') ? WC()->countries->get_shipping_countries() : array();
        $allowed_countries_clean = array_values(array_map('strtolower', array_keys($allowed_countries_raw)));
      }

      wp_localize_script('kodyt-js-core', 'kodyt_checkout_params', array(
        'ajax_url'          => admin_url('admin-ajax.php'),
        'checkout_nonce'    => wp_create_nonce('kodyt_checkout_nonce'),
        'license_key'       => esc_js($license),
        'domain'            => esc_js($domain),
        'allowed_countries' => $allowed_countries_clean // Passed cleanly down to auth.js!
      ));
    }
  }

  public function render_unified_inline_otp_login()
  {
    if (is_user_logged_in()) return;
  ?>
    <div class="kodyt-account-inline-otp-wrap" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 32px; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);">
      <h4 style="margin: 0 0 6px 0; font-size: 16px; color: #0f172a; font-weight: 700;"><?php _e('Fast OTP Authentication', 'kodyt-checkout'); ?></h4>
      <p style="margin: 0 0 16px 0; font-size: 13px; color: #64748b; line-height: 1.4;"><?php _e('Bypass passwords. Enter your mobile number below to log in or register an account instantly.', 'kodyt-checkout'); ?></p>

      <?php
      // 1. Bring in the untouched, clean phone input layout from your template
      include KODYT_CHECKOUT_PATH . 'templates/part-auth-step.php';
      ?>

      <div class="kodyt-input-group" id="kodyt-otp-verify-block" style="display: none; margin-top: 16px; padding-top: 16px; border-top: 1px dashed #e2e8f0; max-width: 500px;">
        <div style="display: flex; gap: 12px; align-items: flex-start;">
          <div style="flex: 1;">
            <input type="text" id="kodyt_account_otp_input" style="width: 100%; height: 44px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; box-sizing: border-box; text-align: center; font-size: 15px; letter-spacing: 2px;" placeholder="<?php esc_attr_e('Verification Token', 'kodyt-checkout'); ?>" maxlength="6" />
          </div>
          <div>
            <button type="button" id="kodyt-account-btn-verify-otp" style="height: 44px; background: #10b981; color: #fff; border: none; border-radius: 8px; padding: 0 20px; font-weight: 600; cursor: pointer; font-size: 13px; white-space: nowrap;">
              <?php _e('Verify & Access', 'kodyt-checkout'); ?>
            </button>
          </div>
        </div>
      </div>
    </div>
<?php
  }

  public function render_profile_phone_number_section()
  {
    include KODYT_CHECKOUT_PATH . 'templates/part-profile-step.php';
  }
}
