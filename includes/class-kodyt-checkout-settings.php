<?php
if (! defined('ABSPATH')) exit;

class Kodyt_Checkout_Settings
{
  private $api_group      = 'kodyt_checkout_api_group';
  private $design_group   = 'kodyt_checkout_design_group';
  private $whatsapp_group = 'kodyt_checkout_whatsapp_group';
  private $fees_group     = 'kodyt_checkout_fees_group';
  private $cart_group     = 'kodyt_checkout_cart_group';

  public function __construct()
  {
    add_action('admin_menu', array($this, 'register_standalone_admin_menu'));
    add_action('admin_init', array($this, 'register_plugin_settings_schema'));
    add_action('admin_post_kodyt_save_vault_credentials', array($this, 'process_vault_pass_through_sync'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_settings_enhanced_select'));
  }

  public function enqueue_settings_enhanced_select($hook)
  {
    if (isset($_GET['page']) && $_GET['page'] === 'kodyt-checkout-suite') {
      if (function_exists('WC') || class_exists('WooCommerce')) {
        wp_enqueue_script('wc-enhanced-select');
        wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), WC_VERSION);
      }
    }
  }

  public function register_standalone_admin_menu()
  {
    add_menu_page(
      __('Kodyt Checkout Suite', 'kodyt-checkout'),
      __('Kodyt Checkout', 'kodyt-checkout'),
      'manage_options',
      'kodyt-checkout-suite',
      array($this, 'render_dashboard_view_router'),
      'dashicons-cart',
      55
    );
  }

  public function register_plugin_settings_schema()
  {
    register_setting($this->api_group, 'kodyt_checkout_license_key');

    register_setting($this->api_group, 'kodyt_checkout_allowed_phone_countries', array(
      'type'              => 'array',
      'sanitize_callback' => array($this, 'sanitize_multi_country_array')
    ));

    foreach ($this->get_design_fields() as $field) {
      if (isset($field['id'])) register_setting($this->design_group, $field['id']);
    }

    foreach ($this->get_whatsapp_fields() as $field) {
      if (isset($field['id'])) {
        if (isset($field['type']) && 'checkbox' === $field['type']) {
          register_setting($this->whatsapp_group, $field['id'], array('sanitize_callback' => array($this, 'sanitize_checkbox_field_state')));
        } else {
          register_setting($this->whatsapp_group, $field['id']);
        }
      }
    }

    foreach ($this->get_fees_fields() as $field) {
      if (isset($field['id'])) register_setting($this->fees_group, $field['id']);
    }

    foreach ($this->get_cart_fields() as $field) {
      if (isset($field['id'])) register_setting($this->cart_group, $field['id']);
    }
  }

  public function sanitize_multi_country_array($input)
  {
    if (! is_array($input)) {
      return array();
    }
    return array_filter(array_map('sanitize_key', $input));
  }

  public function sanitize_checkbox_field_state($value)
  {
    return (! empty($value) && ('1' === $value || 'yes' === $value || 'on' === $value)) ? 'yes' : 'no';
  }

  public function process_vault_pass_through_sync()
  {
    if (! current_user_can('manage_options')) {
      wp_die(__('Unauthorized access.', 'kodyt-checkout'));
    }

    check_admin_referer('kodyt_vault_security_action', 'kodyt_vault_nonce');

    $payload = array();
    $vault_keys_map = array(
      'kodyt_custom_wc_ck'         => 'woocommerce_ck',
      'kodyt_custom_wc_cs'         => 'woocommerce_cs',
      'kodyt_custom_google_key'    => 'google_places_key',
      'kodyt_custom_meta_token'    => 'meta_access_token',
      'kodyt_custom_whatsapp_id'   => 'whatsapp_phone_id',
      'kodyt_custom_fast2sms_key'  => 'fast2sms_key',
      'kodyt_custom_sms_sender_id' => 'sms_sender_id',
      'kodyt_custom_dlt_message_id' => 'dlt_message_id'
    );

    // Filter out values left as the placeholder mask or sent completely blank
    foreach ($vault_keys_map as $post_name => $payload_key) {
      if (isset($_POST[$post_name])) {
        $raw_value = sanitize_text_field($_POST[$post_name]);

        if ('••••••••••••••••••••••••' !== $raw_value && '' !== $raw_value) {
          $payload[$payload_key] = $raw_value;

          // --- NEW: GENERATE SAFE LOCAL SECURITY FINGERPRINT ---
          // Creates a distinct status mask: First 6 chars + hash excerpt + Last 4 chars
          $len = strlen($raw_value);
          if ($len > 10) {
            $fingerprint = substr($raw_value, 0, 6) . '...' . substr(md5($raw_value), 0, 4) . '...' . substr($raw_value, -4);
          } else {
            $fingerprint = 'Linked (' . substr(md5($raw_value), 0, 6) . ')';
          }

          // Save the safe, un-decryptable fingerprint string locally in WordPress options
          update_option('kodyt_fingerprint_' . $payload_key, $fingerprint);
        }
      }
    }

    $active_license = get_option('kodyt_checkout_license_key');
    if (empty($active_license)) {
      wp_die(__('Configuration Denied: You must configure and save an active License Key under the Connection Setup tab before linking custom credentials.', 'kodyt-checkout'));
    }

    $creds = class_exists('Kodyt_Api_Client') ? Kodyt_Api_Client::get_credentials() : array('license_key' => '', 'domain' => '');

    if (! empty($payload)) {
      $payload['license_key'] = isset($creds['license_key']) ? $creds['license_key'] : '';
      $payload['domain'] = isset($creds['domain']) ? $creds['domain'] : '';

      $response = wp_remote_post(API_URL . '/v1/keys/update', array(
        'method'    => 'PATCH',
        'timeout'   => 15,
        'blocking'  => true,
        'headers'   => array('Content-Type' => 'application/json; charset=utf-8'),
        'body'      => wp_json_encode($payload),
        'sslverify' => false
      ));

      if (is_wp_error($response)) {
        wp_die(__('Network Failure: Unable to sync secure environment credentials.', 'kodyt-checkout'));
      }
    }

    $redirect_url = add_query_arg(
      array('page' => 'kodyt-checkout-suite', 'tab' => 'vault', 'vault-updated' => 'true'),
      admin_url('admin.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
  }

  public function render_dashboard_view_router()
  {
    $saved_countries = get_option('kodyt_checkout_allowed_phone_countries', array());
    if (! is_array($saved_countries)) {
      $saved_countries = array();
    }

    $all_wc_countries = function_exists('WC') ? WC()->countries->get_countries() : array();
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';

    if (isset($_GET['vault-updated']) && $_GET['vault-updated'] == 'true') {
      echo '<div class="updated notice is-dismissible"><p><strong>' . __('Secure integration credentials successfully encrypted and synced.', 'kodyt-checkout') . '</strong></p></div>';
    }
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
      echo '<div class="updated notice is-dismissible"><p><strong>' . __('Checkout configurations saved successfully.', 'kodyt-checkout') . '</strong></p></div>';
    }
?>
    <div class="wrap woocommerce">
      <h1 style="margin-bottom: 20px; font-weight: 700; color: #0f172a;"><?php _e('Kodyt Checkout Management Suite', 'kodyt-checkout'); ?></h1>

      <nav class="nav-tab-wrapper woo-nav-tab-wrapper" style="margin-bottom: 25px;">
        <a href="?page=kodyt-checkout-suite&tab=dashboard" class="nav-tab <?php echo $active_tab === 'dashboard' ? 'nav-tab-active' : ''; ?>"><?php _e('📊 Dashboard Overview', 'kodyt-checkout'); ?></a>
        <a href="?page=kodyt-checkout-suite&tab=api" class="nav-tab <?php echo $active_tab === 'api' ? 'nav-tab-active' : ''; ?>"><?php _e('🔌 Connection Setup', 'kodyt-checkout'); ?></a>
        <a href="?page=kodyt-checkout-suite&tab=design" class="nav-tab <?php echo $active_tab === 'design' ? 'nav-tab-active' : ''; ?>"><?php _e('🎨 Brand Customization', 'kodyt-checkout'); ?></a>
        <a href="?page=kodyt-checkout-suite&tab=fees" class="nav-tab <?php echo $active_tab === 'fees' ? 'nav-tab-active' : ''; ?>"><?php _e('💰 Fees & Incentives', 'kodyt-checkout'); ?></a>
        <a href="?page=kodyt-checkout-suite&tab=cart" class="nav-tab <?php echo $active_tab === 'cart' ? 'nav-tab-active' : ''; ?>"><?php _e('🛒 Abandoned Carts', 'kodyt-checkout'); ?></a>
        <a href="?page=kodyt-checkout-suite&tab=whatsapp" class="nav-tab <?php echo $active_tab === 'whatsapp' ? 'nav-tab-active' : ''; ?>"><?php _e('💬 Customer Notifications', 'kodyt-checkout'); ?></a>
        <a href="?page=kodyt-checkout-suite&tab=vault" class="nav-tab <?php echo $active_tab === 'vault' ? 'nav-tab-active' : ''; ?>"><?php _e('🔐 Security & Compliance', 'kodyt-checkout'); ?></a>
      </nav>

      <div class="kodyt-panel-content-area" style="background: #ffffff; padding: 25px; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">

        <?php if ('dashboard' === $active_tab) : ?>
          <div class="kodyt-analytics-placeholder-wrapper">
            <h3><?php _e('Performance Analytics & Conversion Metrics', 'kodyt-checkout'); ?></h3>
            <p><?php _e('Real-time operational monitoring metrics linked directly through api.kodyt.com synchronization hooks.', 'kodyt-checkout'); ?></p>
          </div>

        <?php elseif ('vault' === $active_tab) : ?>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="kodyt_save_vault_credentials" />
            <?php wp_nonce_field('kodyt_vault_security_action', 'kodyt_vault_nonce'); ?>

            <h3 style="margin: 0 0 5px 0; font-weight: 700; color: #334155;"><?php _e('🔐 Protected Environment Access Keys', 'kodyt-checkout'); ?></h3>
            <p class="description" style="margin-bottom: 25px; max-width: 750px;"><?php _e('Modify configuration parameters safely. Plain-text keys are stored inside our decentralized encryption gateway. Only encrypted structural fingerprints are held on your local server database.', 'kodyt-checkout'); ?></p>

            <table class="form-table" role="presentation">
              <?php
              // Explicit visual structural mapping layout configuration array
              $fields_layout_map = array(
                'woocommerce_ck'     => __('WooCommerce Consumer Key (CK)', 'kodyt-checkout'),
                'woocommerce_cs'     => __('WooCommerce Consumer Secret (CS)', 'kodyt-checkout'),
                'google_places_key'  => __('Google Places Integration API Key', 'kodyt-checkout'),
                'meta_access_token'  => __('Meta Graph System Access Token', 'kodyt-checkout'),
                'whatsapp_phone_id'  => __('WhatsApp Business Phone Account ID', 'kodyt-checkout'),
                'fast2sms_key'       => __('Fast2SMS Gateway API Secret Key', 'kodyt-checkout'),
                'sms_sender_id'      => __('Registered SMS Sender Identifier', 'kodyt-checkout'),
                'dlt_message_id'     => __('Telecom Operator DLT Template ID', 'kodyt-checkout')
              );

              foreach ($fields_layout_map as $db_key => $label_title) :
                $fingerprint = get_option('kodyt_fingerprint_' . $db_key, '');

                // 1. DYNAMIC VALUE MASK RULE: Clear the input box if the local fingerprint indicator is missing
                $display_value = ! empty($fingerprint) ? '••••••••••••••••••••••••' : '';

                // Align individual field targets correctly with your background data routing arrays
                $post_input_name = 'kodyt_custom_' . str_replace('key', 'key', str_replace('woocommerce_', 'wc_', $db_key));
                if ($db_key === 'google_places_key')  $post_input_name = 'kodyt_custom_google_key';
                if ($db_key === 'meta_access_token')  $post_input_name = 'kodyt_custom_meta_token';
                if ($db_key === 'whatsapp_phone_id')  $post_input_name = 'kodyt_custom_whatsapp_id';
                if ($db_key === 'fast2sms_key')       $post_input_name = 'kodyt_custom_fast2sms_key';
                if ($db_key === 'sms_sender_id')      $post_input_name = 'kodyt_custom_sms_sender_id';
                if ($db_key === 'dlt_message_id')     $post_input_name = 'kodyt_custom_dlt_message_id';
              ?>
                <tr>
                  <th scope="row"><label><?php echo esc_html($label_title); ?></label></th>
                  <td>
                    <div style="display: flex; align-items: center; gap: 15px;">
                      <!-- Input loads the mask if populated, or leaves it blank if empty -->
                      <input type="password"
                        name="<?php echo esc_attr($post_input_name); ?>"
                        value="<?php echo esc_attr($display_value); ?>"
                        class="regular-text"
                        autocomplete="new-password"
                        placeholder="<?php echo empty($fingerprint) ? 'Enter key value...' : ''; ?>" />

                      <?php if (! empty($fingerprint)) : ?>
                        <span style="background: #e6f7ed; color: #008a3c; border: 1px solid #a3e2bd; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-family: monospace; font-weight: 600;" title="Safe cryptographic signature stored locally.">
                          ✓ <?php echo esc_html($fingerprint); ?>
                        </span>
                      <?php else : ?>
                        <span style="background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 500;">
                          ⚠️ <?php _e('Not Configured', 'kodyt-checkout'); ?>
                        </span>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>

                <?php if ($db_key === 'woocommerce_cs' || $db_key === 'whatsapp_phone_id') : ?>
                  <tr>
                    <td colspan="2">
                      <hr style="border:0; border-top:1px solid #e2e8f0; margin:10px 0;" />
                    </td>
                  </tr>
                <?php endif; ?>

              <?php endforeach; ?>
            </table>

            <?php submit_button(__('Save Secure Credentials', 'kodyt-checkout'), 'primary', 'save_kodyt_vault'); ?>
          </form>

        <?php else : ?>
          <form method="post" action="options.php">
            <?php
            if ('api' === $active_tab) {
              settings_fields($this->api_group);
              $license_key = get_option('kodyt_checkout_license_key', '');
            ?>
              <h2 style="font-size: 18px; font-weight: 700; margin: 0 0 15px 0; color: #334155;">Plugin Activation</h2>

              <table class="form-table" role="presentation">
                <tr valign="top">
                  <th scope="row"><label for="kodyt_checkout_license_key">Active License Key</label></th>
                  <td><input type="text" id="kodyt_checkout_license_key" name="kodyt_checkout_license_key" value="<?php echo esc_attr($license_key); ?>" class="regular-text" style="min-width:350px;" /></td>
                </tr>
                <tr valign="top">
                  <th scope="row"><label for="kodyt_checkout_allowed_phone_countries">Allowed SMS Verification Countries</label></th>
                  <td>
                    <select id="kodyt_checkout_allowed_phone_countries" name="kodyt_checkout_allowed_phone_countries[]" class="wc-enhanced-select" multiple="multiple" style="width: 450px; max-width: 100%;" data-placeholder="Choose countries...">
                      <?php foreach ($all_wc_countries as $code => $name): ?>
                        <option value="<?php echo esc_attr(strtolower($code)); ?>" <?php selected(in_array(strtolower($code), array_map('strtolower', $saved_countries))); ?>>
                          <?php echo esc_html($name) . ' (' . esc_html($code) . ')'; ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <p class="description" style="margin-top: 8px;">Restrict secure phone verification numbers to specific countries. Leave blank to allow purchases worldwide.</p>
                  </td>
                </tr>
              </table>
            <?php
              submit_button(__('Save Configuration', 'kodyt-checkout'), 'primary');
              echo '</form></div></div>';
              return;
            } elseif ('design' === $active_tab) {
              settings_fields($this->design_group);
              $current_fields = $this->get_design_fields();
            } elseif ('whatsapp' === $active_tab) {
              settings_fields($this->whatsapp_group);
              $current_fields = $this->get_whatsapp_fields();
            } elseif ('fees' === $active_tab) {
              settings_fields($this->fees_group);
              $current_fields = $this->get_fees_fields();
            } elseif ('cart' === $active_tab) { // ◄ ADD THIS BLOCK
              settings_fields($this->cart_group);
              $current_fields = $this->get_cart_fields();
            }

            woocommerce_admin_fields($current_fields);
            submit_button(__('Save Settings', 'kodyt-checkout'), 'primary');
            ?>
          </form>
        <?php endif; ?>
      </div>
    </div>
<?php
  }

  // --- ADD THIS METHOD AT THE BOTTOM OF THE CLASS FILE ---
  private function get_cart_fields()
  {
    return array(
      'cart_section_title' => array(
        'name' => __('Abandoned Cart Recovery Rules', 'kodyt-checkout'),
        'type' => 'title',
        'desc' => __('Automatically rescue lost revenue by generating unique, time-restricted recovery coupons and syncing cart snapshots to your tracking dashboard.', 'kodyt-checkout'),
        'id'   => 'kodyt_checkout_cart_section_title'
      ),
      'coupon_type' => array(
        'name'     => __('Recovery Incentive Type', 'kodyt-checkout'),
        'type'     => 'select',
        'desc'     => __('Choose whether the dynamic coupon offers a percentage deduction or a flat rate discount amount.', 'kodyt-checkout'),
        'id'       => 'kodyt_cart_recovery_coupon_type',
        'options'  => array(
          'percent'    => __('Percentage Discount (%)', 'kodyt-checkout'),
          'fixed_cart' => __('Fixed Cart Discount (₹)', 'kodyt-checkout'),
        ),
        'default'  => 'percent',
      ),
      'coupon_value' => array(
        'name'     => __('Recovery Incentive Value', 'kodyt-checkout'),
        'type'     => 'number',
        'desc'     => __('Enter the numerical discount value (e.g., enter 10 for 10% or 200 for ₹200).', 'kodyt-checkout'),
        'id'       => 'kodyt_cart_recovery_coupon_value',
        'default'  => '10',
        'css'      => 'max-width:120px;',
        'custom_attributes' => array('min' => '0', 'step' => '1')
      ),
      'coupon_expiry' => array(
        'name'     => __('Coupon Expiration Window (Hours)', 'kodyt-checkout'),
        'type'     => 'number',
        'desc'     => __('Specify how many hours the recovery coupon remains active once generated (e.g., enter 6 for six hours). Minimum 1 hour.', 'kodyt-checkout'),
        'id'       => 'kodyt_cart_recovery_coupon_expiry_hours',
        'default'  => '6',
        'css'      => 'max-width:120px;',
        'custom_attributes' => array('min' => '1', 'step' => '1')
      ),
      'cart_section_end' => array('type' => 'sectionend', 'id' => 'kodyt_checkout_cart_section_end')
    );
  }

  private function get_fees_fields()
  {
    return array(
      'fees_section_title' => array(
        'name' => __('Payment Mode Fees & Incentives', 'kodyt-checkout'),
        'type' => 'title',
        'desc' => __('Optimize your order profitability and encourage online payments by configuring custom rules for Cash on Delivery (COD) or Prepaid transactions.', 'kodyt-checkout'),
        'id'   => 'kodyt_checkout_fees_section_title'
      ),
      'cod_fee' => array(
        'name'     => __('Cash on Delivery (COD) Handling Charge', 'kodyt-checkout'),
        'type'     => 'number',
        'desc'     => __('Specify a flat extra amount (in ₹) added to orders using COD to offset logistical handling or return risks. Enter 0 to disable.', 'kodyt-checkout'),
        'id'       => 'kodyt_settings_cod_fee',
        'default'  => '100',
        'css'      => 'max-width:120px;',
        'custom_attributes' => array('min' => '0', 'step' => '1')
      ),
      'prepaid_discount' => array(
        'name'     => __('Prepaid Order Discount Incentive', 'kodyt-checkout'),
        'type'     => 'number',
        'desc'     => __('Specify a flat deduction reward (in ₹) subtracted from orders paid online to improve upfront conversion rates. Enter 0 to disable.', 'kodyt-checkout'),
        'id'       => 'kodyt_settings_prepaid_discount',
        'default'  => '100',
        'css'      => 'max-width:120px;',
        'custom_attributes' => array('min' => '0', 'step' => '1')
      ),
      'fees_section_end' => array('type' => 'sectionend', 'id' => 'kodyt_checkout_fees_section_end')
    );
  }

  private function get_design_fields()
  {
    return array(
      'design_global_title' => array(
        'name' => __('Global Layout & Typography', 'kodyt-checkout'),
        'type' => 'title',
        'desc' => __('Manage the overarching visual settings governing text styles, weights, and corner treatments.', 'kodyt-checkout'),
        'id'   => 'kodyt_checkout_global_design_title'
      ),
      'font_family' => array(
        'name'     => __('Font Family Variant', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Specify custom web font configuration rules (e.g. \'Inter\', sans-serif). Leave blank to use your theme font layout.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_font_family',
        'default'  => "'Inter', system-ui, -apple-system, sans-serif",
        'css'      => 'min-width:350px;'
      ),
      'base_text_color' => array(
        'name'     => __('Body Text Color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Applies to general descriptions, text field labels, and standard messages.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_base_text_color',
        'default'  => '#1e293b',
      ),
      'heading_text_color' => array(
        'name'     => __('Heading Text Color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Applies to checkout card headers, checkout section titles, and grand total text headers.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_heading_text_color',
        'default'  => '#0f172a',
      ),
      'border_radius' => array(
        'name'     => __('Global Corner Rounding Radius', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Defines the rounding intensity applied to form controls, product boxes, buttons, and checkout drawers (e.g. 8px).', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_border_radius',
        'default'  => '8px',
        'css'      => 'max-width:100px;'
      ),
      'design_global_end' => array('type' => 'sectionend', 'id' => 'kodyt_checkout_global_design_end'),

      'design_buttons_title' => array(
        'name' => __('Interface Color Matching Options', 'kodyt-checkout'),
        'type' => 'title',
        'desc' => __('Ensure your main buttons stand out effectively by establishing strong visual contrast targets.', 'kodyt-checkout'),
        'id'   => 'kodyt_checkout_buttons_design_title'
      ),
      'primary_color' => array(
        'name'     => __('Primary Button Background Color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Applies to checkout completion triggers ("Complete Secure Checkout", "Continue to Payment").', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_primary_color',
        'default'  => '#6366f1',
      ),
      'primary_text_color' => array(
        'name'     => __('Primary Button Text Color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Font label text color layer overlaying primary action progression buttons.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_primary_text_color',
        'default'  => '#ffffff',
      ),
      'hover_color' => array(
        'name'     => __('Primary Button Hover Background Color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Background highlight color applied during interactive mouse cursor hover states.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_hover_color',
        'default'  => '#4f46e5',
      ),
      'secondary_color' => array(
        'name'     => __('Secondary Element Background Color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Applies to support control tools ("Send OTP", "Apply Coupon", "Change Number").', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_secondary_color',
        'default'  => '#1e293b',
      ),
      'secondary_text_color' => array(
        'name'     => __('Secondary Element Text Color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Font label color formatting shown over secondary configuration triggers.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_secondary_text_color',
        'default'  => '#ffffff',
      ),
      'design_buttons_end' => array('type' => 'sectionend', 'id' => 'kodyt_checkout_buttons_design_end'),

      'design_structure_title' => array(
        'name' => __('Borders Layout Density & Spatial Spacing', 'kodyt-checkout'),
        'type' => 'title',
        'desc' => __('Adjust elements spatial parameters cleanly without modifying system stylesheet configurations.', 'kodyt-checkout'),
        'id'   => 'kodyt_checkout_structure_design_title'
      ),
      'border_style' => array(
        'name'     => __('Global Layout Border Style', 'kodyt-checkout'),
        'type'     => 'select',
        'desc'     => __('Select the profile line outline pattern variant crossing forms blocks and steps panels elements.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_border_style',
        'options'  => array(
          'solid'  => __('Solid Border Outline', 'kodyt-checkout'),
          'dashed' => __('Dashed Line Variant', 'kodyt-checkout'),
          'none'   => __('Completely Borderless UI Layout', 'kodyt-checkout'),
        ),
        'default'  => 'solid',
      ),
      'step_border_width' => array(
        'name'     => __('Checkout Step Card Outline Thickness', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Outline frame border weight tracking structural checkout blocks. Select 0px for flat clean containers.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_step_border_width',
        'default'  => '1px',
        'css'      => 'max-width:100px;'
      ),
      'input_border_width' => array(
        'name'     => __('Text Input Form Fields Border Weight', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Outline line thickness sizing empty input user text entry boxes. Use 0px for filled background layout formats.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_input_border_width',
        'default'  => '1px',
        'css'      => 'max-width:100px;'
      ),
      'input_border_color' => array(
        'name'     => __('Input Field Inactive Rest Border Color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('The standard unfocused element frame line matching text container box vectors.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_input_border_color',
        'default'  => '#cbd5e1',
      ),
      'step_card_padding' => array(
        'name'     => __('Checkout Card Inner Spacing Padding', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Inner density parameters layout separation spacing inside structural fields steps (e.g. 24px vs 12px).', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_step_card_padding',
        'default'  => '24px',
        'css'      => 'max-width:100px;'
      ),
      'grid_column_gap' => array(
        'name'     => __('Main Form Grid Column Separation Gap', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Main spatial gutter grid column separation splitting input checkout panels sheets from invoice views (e.g. 30px).', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_grid_column_gap',
        'default'  => '30px',
        'css'      => 'max-width:100px;'
      ),
      'design_structure_end' => array('type' => 'sectionend', 'id' => 'kodyt_checkout_structure_design_end'),

      'design_accents_title' => array(
        'name' => __('Fills, Modules Backgrounds, Badges & Sticky Offsets', 'kodyt-checkout'),
        'type' => 'title',
        'desc' => __('Fine-tune minor specific accents components blocks.', 'kodyt-checkout'),
        'id'   => 'kodyt_checkout_accents_design_title'
      ),
      'step_bg' => array(
        'name'     => __('Step Block Card Area Background Fill', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Background canvas card color layer packing checkout instructions steps fields.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_step_bg',
        'default'  => '#ffffff',
      ),
      'summary_card_bg' => array(
        'name'     => __('Order Summary Sidebar Background Fill', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Background canvas container color formatting the sticky right-hand column order totals dashboard.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_summary_card_bg',
        'default'  => '#f8fafc',
      ),
      'success_color' => array(
        'name'     => __('Verified State Profile Highlight Color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Applies to completed step frames checkmarks, authenticated telephone containers, and chosen address grid elements.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_success_color',
        'default'  => '#22c55e',
      ),
      'success_text_color' => array(
        'name'     => __('Verified Accent Overlay Text Color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Font text representation color layers overlayering successfully verified badges elements frames blocks.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_success_text_color',
        'default'  => '#ffffff',
      ),
      'qty_badge_bg' => array(
        'name'     => __('Product Image Float Quantity Badge Fill', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('The background fill rounding circle index badges overlaying item product picture previews cards list.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_qty_badge_bg',
        'default'  => '#1e293b',
      ),
      'qty_badge_text' => array(
        'name'     => __('Product Image Float Quantity Badge Text Color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('The typeface color drawing index metrics numbers inside floating thumb quantity badges.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_qty_badge_text',
        'default'  => '#ffffff',
      ),
      'sticky_top_offset' => array(
        'name'     => __('Desktop Sticky Sidebar Top Constraint Padding Offset', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Vertical height constraints padding pinning side cards down views screens (e.g. 20px). Increase if it pins beneath fixed menus.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_sticky_top_offset',
        'default'  => '20px',
        'css'      => 'max-width:100px;'
      ),
      'design_accents_end' => array('type' => 'sectionend', 'id' => 'kodyt_checkout_accents_design_end')
    );
  }

  private function get_whatsapp_fields()
  {
    return array(
      'whatsapp_section_title' => array(
        'name' => __('Automated Order Milestone Alerts', 'kodyt-checkout'),
        'type' => 'title',
        'desc' => __('Enhance post-purchase buyer retention by dispatching transactional receipts and delivery alerts matching real-time fulfillment states.', 'kodyt-checkout'),
        'id'   => 'kodyt_checkout_whatsapp_section'
      ),
      'enable_whatsapp_notifications' => array(
        'name'    => __('Enable Automated Post-Purchase Notifications', 'kodyt-checkout'),
        'type'    => 'checkbox',
        'desc'    => __('Activate fully automated transactional summaries notifications messaging cycles instantly orders clear checkout vectors.', 'kodyt-checkout'),
        'id'      => 'kodyt_checkout_enable_whatsapp',
        'default' => 'no'
      ),
      'whatsapp_routing_strategy' => array(
        'name'     => __('Message Destination Targeting Strategy', 'kodyt-checkout'),
        'type'     => 'select',
        'desc'     => __('Select the priority logic framework governing phone profile targets collection loops.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_whatsapp_routing',
        'options'  => array(
          'profile'  => __('Route Message strictly to SMS Verified Mobile Number Profile Token', 'kodyt-checkout'),
          'billing'  => __('Route Message to Checkout Form Customer Invoice Phone Field', 'kodyt-checkout'),
          'shipping' => __('Route Message to Secondary Recipient Shipping Address Contact Field', 'kodyt-checkout'),
        ),
        'default'  => 'profile',
      ),
      'whatsapp_section_end' => array('type' => 'sectionend', 'id' => 'kodyt_checkout_whatsapp_section_end')
    );
  }
}
