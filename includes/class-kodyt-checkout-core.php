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
    add_action('wp_ajax_kodyt_validate_pincode', array($this, 'ajax_backend_pincode_verification'));
    add_action('wp_ajax_nopriv_kodyt_validate_pincode', array($this, 'ajax_backend_pincode_verification'));
    add_action('wp_ajax_kodyt_save_address', array($this, 'save_address'));
    add_action('wp_ajax_nopriv_kodyt_save_address', array($this, 'save_address'));
    add_action('wp_ajax_kodyt_update_address', array($this, 'update_address'));
    add_action('wp_ajax_nopriv_kodyt_update_address', array($this, 'update_address'));
    add_action('init', array($this, 'register_pending_confirmation_order_status'));
    add_filter('wc_order_statuses', array($this, 'add_pending_confirmation_to_order_statuses'));

    // Hook into the global WordPress footer to render the canvas on all user-facing screens
    add_action('wp_footer', array($this, 'render_global_checkout_popup_markup'));
    add_filter('woocommerce_add_to_cart_fragments', array($this, 'synchronize_popup_checkout_fragments'));
    // Intercept page loading requests right before template headers are sent to the browser
    add_action('template_redirect', array($this, 'redirect_native_checkout_to_popup_flow'));
  }

  public function save_address()
  {
    $current_user_id = get_current_user_id();

    if (!isset($current_user_id)) {
      wp_send_json_error(array('message' => 'User Not Logged In'));
    }

    $auth_phone = get_user_meta($current_user_id, 'phone_number', true);

    if (!isset($auth_phone)) {
      wp_send_json_error(array('message' => 'User Not Logged In'));
    }

    // Verify request payload inputs safely
    if (!isset($_POST['address_1'])) {
      wp_send_json_error(array('message' => 'Missing parameter input configuration.'));
    }

    $payload = array();

    $creds = class_exists('Kodyt_Api_Client') ? Kodyt_Api_Client::get_credentials() : array('license_key' => '', 'domain' => '');

    // Fire safe out-of-band server-to-server request
    $payload['license_key'] = isset($creds['license_key']) ? $creds['license_key'] : '';
    $payload['domain'] = isset($creds['domain']) ? $creds['domain'] : '';
    $payload['mobile_number'] = $auth_phone;
    $payload['pincode'] = sanitize_text_field($_POST['pincode']);
    $payload['first_name'] = sanitize_text_field($_POST['first_name']);
    $payload['last_name'] = sanitize_text_field($_POST['last_name']);
    $payload['email'] = sanitize_text_field($_POST['email']);
    $payload['address_1'] = sanitize_text_field($_POST['address_1']);
    $payload['address_2'] = sanitize_text_field($_POST['address_2']);
    $payload['city'] = sanitize_text_field($_POST['city']);
    $payload['state'] = sanitize_text_field($_POST['state']);

    $response = wp_remote_post(API_URL . '/v1/addresses/add', array(
      'method'    => 'POST',
      'timeout'   => 15,
      'blocking'  => true,
      'headers'   => array('Content-Type' => 'application/json; charset=utf-8'),
      'body'      => wp_json_encode($payload),
      'sslverify' => false
    ));

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body_raw = wp_remote_retrieve_body($response);
    $response_body = json_decode($response_body_raw, true);

    if (200 != $response_code || is_wp_error($response)) {
      wp_send_json_error(array('message' => 'Something Went Wrong! Error Saving Address.'));
    }

    wp_send_json(array('success' => true, 'result' => 'success', 'new_address_id' => $response_body['new_address_id']));
  }

  public function update_address()
  {
    $current_user_id = get_current_user_id();

    if (!isset($current_user_id)) {
      wp_send_json_error(array('message' => 'User Not Logged In'));
    }

    $auth_phone = get_user_meta($current_user_id, 'phone_number', true);

    if (!isset($auth_phone)) {
      wp_send_json_error(array('message' => 'User Not Logged In'));
    }

    // Verify request payload inputs safely
    if (!isset($_POST['address_1'])) {
      wp_send_json_error(array('message' => 'Missing parameter input configuration.'));
    }

    if (!isset($_POST['address_id'])) {
      wp_send_json_error(array('message' => 'Missing parameter input configuration.'));
    }

    $payload = array();

    $creds = class_exists('Kodyt_Api_Client') ? Kodyt_Api_Client::get_credentials() : array('license_key' => '', 'domain' => '');

    // Fire safe out-of-band server-to-server request
    $payload['address_id'] = isset($_POST['address_id']) ? $_POST['address_id'] : '';
    $payload['license_key'] = isset($creds['license_key']) ? $creds['license_key'] : '';
    $payload['domain'] = isset($creds['domain']) ? $creds['domain'] : '';
    $payload['mobile_number'] = $auth_phone;
    $payload['pincode'] = sanitize_text_field($_POST['pincode']);
    $payload['first_name'] = sanitize_text_field($_POST['first_name']);
    $payload['last_name'] = sanitize_text_field($_POST['last_name']);
    $payload['email'] = sanitize_text_field($_POST['email']);
    $payload['address_1'] = sanitize_text_field($_POST['address_1']);
    $payload['address_2'] = sanitize_text_field($_POST['address_2']);
    $payload['city'] = sanitize_text_field($_POST['city']);
    $payload['state'] = sanitize_text_field($_POST['state']);

    $response = wp_remote_post(API_URL . '/v1/addresses/update', array(
      'method'    => 'PUT',
      'timeout'   => 15,
      'blocking'  => true,
      'headers'   => array('Content-Type' => 'application/json; charset=utf-8'),
      'body'      => wp_json_encode($payload),
      'sslverify' => false
    ));

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body_raw = wp_remote_retrieve_body($response);
    $response_body = json_decode($response_body_raw, true);

    if (200 != $response_code || is_wp_error($response)) {
      wp_send_json_error(array('message' => 'Something Went Wrong! Error Saving Address.'));
    }

    wp_send_json(array('success' => true, 'result' => 'success', 'new_address_id' => $response_body['new_address_id']));
  }

  public function redirect_native_checkout_to_popup_flow()
  {
    // 1. Safeguard: Never run redirects inside WP-Admin interfaces or during background core AJAX processing loops
    if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
      return;
    }

    // 2. Detect if the visitor is trying to load the core native WooCommerce Checkout page route explicitly
    if (is_checkout() && !is_order_received_page() && !isset($_GET['wc-ajax'])) {

      // Safeguard: If the cart is empty, let standard WooCommerce redirect logic handle them (usually back to cart page)
      if (function_exists('WC') && WC()->cart && WC()->cart->is_empty()) {
        return;
      }

      // 3. Define the destination URL wrapper where you want the popup checkout to draw (e.g., Shop page or Home page)
      $target_destination_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');

      // 4. Append our premium auto-open parameter tracking token cleanly to the string
      $redirect_url_with_query = add_query_arg('kodyt_switch', '1', $target_destination_url);

      // 5. Execute an instant, high-trust 302 safe redirect route jump
      wp_safe_redirect($redirect_url_with_query);
      exit;
    }
  }

  public function synchronize_popup_checkout_fragments($fragments)
  {
    ob_start();
    // Re-render the internal items looping tracking arrays layout panel block explicitly
?>
    <div id="kodyt-summary-dropdown-panel">
    </div>
  <?php
    $fragments['#kodyt-summary-dropdown-panel'] = ob_get_clean();
    return $fragments;
  }

  public function render_global_checkout_popup_markup()
  {
    // Prevent rendering inside admin layout grids or non-checkout flows if preferred
    if (is_admin()) {
      return;
    }

    // Wrap your unified target templates within a globally accessible display-none background layer
    echo '<div id="kodyt-global-popup-checkout-wrapper" class="kodyt-global-hidden-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 999999997; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);">';
    echo '  <div class="kodyt-popup-modal-dismiss-backdrop-hitbox" style="position: absolute; width: 100%; height: 100%; top: 0; left: 0; z-index: 1;"></div>';
    echo '  <div class="kodyt-popup-sliding-panel-content" style="position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); width: 100%; max-width: 480px; background: #f8fafc; border-top-left-radius: 20px; border-top-right-radius: 20px; box-shadow: 0 -10px 40px rgba(0,0,0,0.15); z-index: 2; height: 90vh; overflow: hidden; display: flex; flex-direction: column;">';

    // Top dismiss pill button control matching app patterns
    echo '     <div class="kodyt-popup-drag-dismiss-handle" style="width: 40px; height: 5px; background: #cbd5e1; border-radius: 10px; margin: 12px auto 6px auto; cursor: pointer; flex-shrink: 0;"></div>';
    echo '     <div class="kodyt-popup-scrollable-body-viewport" style="flex: 1; overflow-y: auto; padding-bottom: 30px;">';

    // Load your updated layout engine partial safely
    include KODYT_CHECKOUT_PATH . 'templates/checkout-view.php';

    echo '     </div>';
    echo '  </div>';
    echo '</div>';
  }

  /**
   * Server-Side Proxy: Validates pincodes via api.kodyt.com to completely bypass browser CORS rules.
   */
  public function ajax_backend_pincode_verification()
  {
    // Verify request payload inputs safely
    if (!isset($_POST['pincode'])) {
      wp_send_json_error(array('message' => 'Missing parameter input configuration.'));
    }

    $creds = class_exists('Kodyt_Api_Client') ? Kodyt_Api_Client::get_credentials() : array('license_key' => '', 'domain' => '');

    $pincode = sanitize_text_field($_POST['pincode']);
    // $license_key = get_option('kodyt_checkout_license_key', 'test-key-1234');
    // $site_domain = wp_parse_url(home_url(), PHP_URL_HOST);

    // Formulate clean destination request URL
    $api_url = sprintf(
      API_URL . '/v1/pincode/%s?license_key=%s&domain=%s',
      urlencode($pincode),
      urlencode($creds['license_key']),
      urlencode($creds['domain'])
    );

    // Fire safe out-of-band server-to-server request
    $response = wp_remote_get($api_url, array('timeout' => 8, 'sslverify' => false));

    if (is_wp_error($response)) {
      wp_send_json_error(array('message' => 'Verification gateway unreachable.'));
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    if (200 !== $response_code || empty($response_body)) {
      wp_send_json_error(array('message' => 'Invalid configuration endpoint parameters returned.'));
    }

    // Return the response data directly to your frontend script
    wp_send_json($response_body);
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
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'kodyt_checkout_nonce')) {
      wp_send_json_error(array('message' => 'Security token expired.'));
    }

    if (! function_exists('WC') || WC()->cart->is_empty()) {
      wp_send_json_error(array('message' => 'Your cart is empty.'));
    }

    parse_str($_POST['form_data'], $posted_data);
    $auth_phone         = isset($posted_data['kodyt_auth_phone']) ? sanitize_text_field($posted_data['kodyt_auth_phone']) : '';
    $user_id            = isset($posted_data['kodyt_in_memory_user_id']) ? intval($posted_data['kodyt_in_memory_user_id']) : 0;
    $address_id            = isset($posted_data['kodyt_address_id']) ? intval($posted_data['kodyt_address_id']) : 0;
    $auth_dial_code     = isset($posted_data['kodyt_country_dial_code']) ? sanitize_text_field($posted_data['kodyt_country_dial_code']) : '';

    $shipping_dial_code = isset($posted_data['kodyt_shipping_country_dial_code']) ? sanitize_text_field($posted_data['kodyt_shipping_country_dial_code']) : '';

    if (empty($auth_phone) || empty($user_id) || empty($address_id)) {
      wp_send_json_error(array('message' => 'Session expired. Execute step 1 verification again.'));
    }

    $raw_shipping_phone = sanitize_text_field($posted_data['kodyt_shipping_phone']);
    $raw_shipping_phone = str_replace(' ', '', $raw_shipping_phone);
    $raw_shipping_phone = substr($raw_shipping_phone, -10);

    // Compile Dynamic Shipping Context Args
    $ship_address_2  = sanitize_text_field($posted_data['kodyt_shipping_address_2']);
    $ship_address_1 = sanitize_text_field($posted_data['kodyt_shipping_address_1']);
    $ship_city    = sanitize_text_field($posted_data['kodyt_shipping_city']);
    $ship_state    = sanitize_text_field($posted_data['kodyt_shipping_state']);
    $ship_address_1  = $this->strip_redundant_address_components($ship_address_1, $ship_city);

    $shipping_args = array(
      'first_name' => sanitize_text_field($posted_data['kodyt_shipping_first_name']),
      'last_name'  => sanitize_text_field($posted_data['kodyt_shipping_last_name']),
      'address_2'  => $ship_address_2,
      'address_1'  => $ship_address_1,
      'phone'      => $raw_shipping_phone,
      'city'       => $ship_city,
      'state'       => $ship_state,
      'postcode'   => sanitize_text_field($posted_data['kodyt_shipping_postcode']),
    );

    try {
      $order = wc_create_order();
      foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
        $order->add_product($values['data'], $values['quantity'], array('variation' => $values['variation']));
      }

      $order->set_address($shipping_args, 'billing');
      $order->set_address($shipping_args, 'shipping');
      $order->set_customer_id($user_id);

      // Sync Database Metadata Caches Securely
      update_user_meta($user_id, 'phone_number', $auth_phone);
      // update_user_meta($user_id, 'shipping_phone', $raw_shipping_phone); // ◄ UPDATED

      // if (! empty($country_code)) update_user_meta($user_id, 'phone_country_dial_code', $country_code);

      // update_user_meta($user_id, 'shipping_first_name', $shipping_args['first_name']);
      // update_user_meta($user_id, 'shipping_last_name', $shipping_args['last_name']);
      // update_user_meta($user_id, 'shipping_address_1', $ship_address_1);
      // update_user_meta($user_id, 'shipping_address_2', $ship_address_2);
      // update_user_meta($user_id, 'shipping_city', $shipping_args['city']);
      // update_user_meta($user_id, 'shipping_state', $shipping_args['state']);
      // update_user_meta($user_id, 'shipping_postcode', $shipping_args['postcode']);

      // if (! empty($auth_dial_code)) update_user_meta($user_id, 'phone_country_dial_code', $auth_dial_code);
      // if (! empty($shipping_dial_code)) update_user_meta($user_id, 'shipping_phone_country_dial_code', $shipping_dial_code); // ◄ NEW

      $order->calculate_totals();
      $payment_method = sanitize_text_field($posted_data['kodyt_payment_method']);
      $gateways       = WC()->payment_gateways->get_available_payment_gateways();
      $order_id = $order->get_id();

      if (isset($gateways[$payment_method])) {
        $order->set_payment_method($gateways[$payment_method]);
      }

      $_POST['billing_first_name'] = $shipping_args['first_name'];
      $_POST['billing_last_name']  = $shipping_args['last_name'];
      $_POST['billing_address_1']  = $shipping_args['address_1'];
      $_POST['billing_address_2']  = $shipping_args['address_2'];
      $_POST['billing_city']       = $shipping_args['city'];
      $_POST['billing_state']      = $shipping_args['state'];
      $_POST['billing_postcode']   = $shipping_args['postcode'];
      $_POST['billing_phone']      = $shipping_args['phone'];
      $_POST['billing_country']    = 'IN';
      $_POST['billing_email']      = 'customer-' . $shipping_args['phone'] . '@kodyt-checkout.local';

      $_POST['shipping_first_name'] = $shipping_args['first_name'];
      $_POST['shipping_last_name']  = $shipping_args['last_name'];
      $_POST['shipping_address_1']  = $shipping_args['address_1'];
      $_POST['shipping_address_2']  = $shipping_args['address_2'];
      $_POST['shipping_city']       = $shipping_args['city'];
      $_POST['shipping_state']      = $shipping_args['state'];
      $_POST['shipping_postcode']   = $shipping_args['postcode'];
      $_POST['shipping_phone']      = $shipping_args['phone'];
      $_POST['shipping_country']    = 'IN';

      $_POST['payment_method']     = $payment_method;
      $_REQUEST = array_merge($_REQUEST, $_POST);

      $order->save();

      $result = $gateways[$payment_method]->process_payment($order_id);

      $is_cod = ('cod' === $payment_method);

      if (isset($result['result']) && $result['result'] === 'success') {
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        if (WC()->session) WC()->session->set_customer_session_cookie(true);
        WC()->cart->empty_cart();

        if ($is_cod) {
          $order->update_status('pending-confirm', __('Order created via custom checkout, awaiting WhatsApp customer validation.', 'kodyt-checkout'));
          $order->save();

          if (! class_exists('Kodyt_Notification_Handler')) {
            require_once KODYT_CHECKOUT_PATH . 'modules/notifications/class-kodyt-notification-handler.php';
          }

          // The notification handler now automatically pulls the fully formatted numbers directly out of the order objects!
          Kodyt_Notification_Handler::trigger_whatsapp_order_notification($order_id, $order, $address_id);
        }

        wp_send_json(array('result' => 'success', 'redirect' => $result['redirect']));
      } else {
        wp_send_json_error(array('message' => 'Payment routing failed.'));
      }
    } catch (Exception $e) {
      wp_send_json_error(array('message' => $e->getMessage()));
    }
  }

  /**
   * Clean up address fields by removing redundant location values.
   */
  private function strip_redundant_address_components($address, $city)
  {
    if (empty($city) || empty($address)) {
      return $address;
    }

    $quoted_city = preg_quote(trim($city), '/');

    // Matches an optional comma/spaces before the city, the city itself, and everything after
    $address = preg_replace('/,?\s*' . $quoted_city . '.*/i', '', $address);

    // Clean up trailing/leading commas or double commas resulting from removals
    $address = preg_replace('/,+/', ',', $address);
    $address = trim($address, ', ');

    return $address;
  }


  public function enqueue_checkout_assets()
  {
    // Prevent execution inside WP Admin control panels
    if (is_admin()) {
      return;
    }

    // =====================================================================
    // 1. GLOBAL BASE STYLING & TRIGGER LOADER
    // =====================================================================
    wp_enqueue_style('kodyt-css-base', KODYT_CHECKOUT_URL . 'assets/css/base-layout.css', array(), '1.3.0');

    wp_enqueue_script(
      'kodyt-global-popup-trigger',
      KODYT_CHECKOUT_URL . 'assets/js/global-popup-trigger.js',
      array('jquery'),
      '1.3.0',
      true
    );

    // =====================================================================
    // 2. DYNAMIC FONTS EXTRACTION & HYDRATION
    // =====================================================================
    $chosen_font = get_option('kodyt_checkout_font_family', 'Inter');
    $formatted_font = str_replace(' ', '+', $chosen_font);

    wp_enqueue_style(
      'user-chosen-font',
      "https://fonts.googleapis.com/css2?family={$formatted_font}:wght@400;700&display=swap",
      array(),
      null
    );

    // =====================================================================
    // 3. THIRD-PARTY DEPENDENCY VECTORS
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
    // 4. MODULAR CSS STYLE HOOKS (Site-Wide Delivery)
    // =====================================================================
    wp_enqueue_style('kodyt-css-steps', KODYT_CHECKOUT_URL . 'assets/css/modules/steps.css', array('kodyt-css-base'), '1.3.0');
    wp_enqueue_style('kodyt-css-summary', KODYT_CHECKOUT_URL . 'assets/css/modules/cart-summary.css', array('kodyt-css-base'), '1.3.0');
    wp_enqueue_style('kodyt-css-components', KODYT_CHECKOUT_URL . 'assets/css/modules/ui-components.css', array('kodyt-css-base', 'intl-tel-input-css'), '1.3.0');
    wp_enqueue_style('kodyt-css-responsive', KODYT_CHECKOUT_URL . 'assets/css/responsive.css', array('kodyt-css-base'), '1.3.0');

    // =====================================================================
    // 5. FEATURE-DRIVEN JAVASCRIPT PIPELINE (Site-Wide Delivery)
    // =====================================================================
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
    // 6. SECURE GLOBAL PARAMETER LOCALIZATION
    // =====================================================================
    $license = get_option('kodyt_checkout_license_key', '');
    $domain  = wp_parse_url(home_url(), PHP_URL_HOST);

    $custom_countries_array = get_option('kodyt_checkout_allowed_phone_countries', array());

    if (is_array($custom_countries_array) && ! empty($custom_countries_array)) {
      $allowed_countries_clean = array_values(array_map('strtolower', $custom_countries_array));
    } else {
      $allowed_countries_raw = function_exists('WC') ? WC()->countries->get_shipping_countries() : array();
      $allowed_countries_clean = array_values(array_map('strtolower', array_keys($allowed_countries_raw)));
    }

    // CRITICAL UPDATE: We bind the parameters array directly to our global script wrapper token 
    // to guarantee localized parameters variables exist no matter what page the popup initializes on.
    wp_localize_script('kodyt-global-popup-trigger', 'kodyt_checkout_params', array(
      'ajax_url'          => admin_url('admin-ajax.php'),
      'checkout_nonce'    => wp_create_nonce('kodyt_checkout_nonce'),
      'license_key'       => esc_js($license),
      'domain'            => esc_js($domain),
      'allowed_countries' => $allowed_countries_clean
    ));
  }

  public function render_unified_inline_otp_login()
  {
    if (is_user_logged_in()) return;
  ?>
    <div class="kodyt-account-inline-otp-wrap" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 32px; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);">
      <h4 style="margin: 0 0 6px 0; font-size: 16px; color: #0f172a; font-weight: 700;"><?php _e('Fast OTP Authentication', 'kodyt-checkout'); ?></h4>
      <p style="margin: 0 0 16px 0; font-size: 13px; color: #64748b; line-height: 1.4;"><?php _e('Bypass passwords. Enter your mobile number below to log in or register an account instantly.', 'kodyt-checkout'); ?></p>

      <div style="display: flex; gap: 12px; align-items: flex-start; flex-wrap: wrap; width: 100%;">
        <div style="flex: 1; max-width: 260px;">
          <input type="tel" maxlength="10" inputmode="numeric" id="kodyt_auth_phone_active" class="input-text" style="width: 100%; height: 42px; border: var(--kodyt-input-border-width) var(--kodyt-border-style) var(--kodyt-input-border-color); border-radius: var(--kodyt-radius);" placeholder="Enter new mobile number" />
        </div>
        <div>
          <button type="button" id="kodyt-btn-send-otp" class="button" style="height: 42px; padding: 0 20px; white-space: nowrap; background-color: var(--kodyt-secondary); color: var(--kodyt-secondary-text); border-radius: var(--kodyt-radius); border: none; font-size: 16px; font-weight: 600;">Verify Code</button>
        </div>
      </div>

      <div class="kodyt-input-group" id="kodyt-otp-verify-block" style="display: none; margin-top: 16px; padding-top: 16px; border-top: 1px dashed #e2e8f0; max-width: 500px;">
        <div style="display: flex; gap: 12px; align-items: flex-start;">
          <div style="flex: 1;">
            <input type="number" inputmode="numeric" id="kodyt_account_otp_input" style="width: 100%; height: 44px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; box-sizing: border-box; text-align: center; font-size: 15px; letter-spacing: 2px;" placeholder="<?php esc_attr_e('Verification Token', 'kodyt-checkout'); ?>" maxlength="6" />
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
