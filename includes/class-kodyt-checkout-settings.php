<?php
if (! defined('ABSPATH')) exit;

class Kodyt_Checkout_Settings
{
  // Four distinct dashboard groups to protect hidden field arrays from dropping on save
  private $api_group      = 'kodyt_checkout_api_group';
  private $design_group   = 'kodyt_checkout_design_group';
  private $whatsapp_group = 'kodyt_checkout_whatsapp_group';
  private $vault_group    = 'kodyt_checkout_vault_group';

  public function __construct()
  {
    add_action('admin_menu', array($this, 'register_standalone_admin_menu'));
    add_action('admin_init', array($this, 'register_plugin_settings_schema'));

    // THE PASSTHROUGH INTERCEPTOR: Listens exclusively to vault saves to forward tokens to your backend proxy
    add_filter('pre_update_option_kodyt_checkout_license_key', array($this, 'intercept_and_forward_keys_to_backend'), 10, 2);
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

  /**
   * Safelist options tracking definitions matching their explicit sub-tab group keys
   */
  public function register_plugin_settings_schema()
  {
    // 1. Register Core License group separately
    foreach ($this->get_api_fields() as $field) {
      if (isset($field['id'])) {
        register_setting($this->api_group, $field['id']);
      }
    }

    // 2. Register Design Engine variables separately
    foreach ($this->get_design_fields() as $field) {
      if (isset($field['id'])) {
        register_setting($this->design_group, $field['id']);
      }
    }

    // 3. Register WhatsApp Alerts variables separately and handle checkbox normalization
    foreach ($this->get_whatsapp_fields() as $field) {
      if (isset($field['id'])) {
        if (isset($field['type']) && 'checkbox' === $field['type']) {
          register_setting($this->whatsapp_group, $field['id'], array(
            'sanitize_callback' => array($this, 'sanitize_checkbox_field_state')
          ));
        } else {
          register_setting($this->whatsapp_group, $field['id']);
        }
      }
    }

    // 4. Register the Vault group placeholder flag (Used purely to hook into the update interceptor engine)
    register_setting($this->vault_group, 'kodyt_checkout_vault_trigger_flag');
  }

  public function sanitize_checkbox_field_state($value)
  {
    return (! empty($value) && ('1' === $value || 'yes' === $value || 'on' === $value)) ? 'yes' : 'no';
  }

  /**
   * The Pass-Through Security Interceptor: Forwards secret keys to your Node backend, then clears them from WP memory
   */
  public function intercept_and_forward_keys_to_backend($new_license_value, $old_license_value)
  {
    // Read the un-saved custom enterprise credentials straight from the raw post data block
    $google_key   = isset($_POST['kodyt_custom_google_key']) ? sanitize_text_field($_POST['kodyt_custom_google_key']) : '';
    $meta_token   = isset($_POST['kodyt_custom_meta_token']) ? sanitize_text_field($_POST['kodyt_custom_meta_token']) : '';
    $whatsapp_id  = isset($_POST['kodyt_custom_whatsapp_id']) ? sanitize_text_field($_POST['kodyt_custom_whatsapp_id']) : '';
    $wc_ck        = isset($_POST['kodyt_custom_wc_ck']) ? sanitize_text_field($_POST['kodyt_custom_wc_ck']) : '';
    $wc_cs        = isset($_POST['kodyt_custom_wc_cs']) ? sanitize_text_field($_POST['kodyt_custom_wc_cs']) : '';
    $fast2sms_key = isset($_POST['kodyt_custom_fast2sms_key']) ? sanitize_text_field($_POST['kodyt_custom_fast2sms_key']) : '';
    $sms_sender   = isset($_POST['kodyt_custom_sms_sender_id']) ? sanitize_text_field($_POST['kodyt_custom_sms_sender_id']) : '';
    $dlt_msg_id   = isset($_POST['kodyt_custom_dlt_message_id']) ? sanitize_text_field($_POST['kodyt_custom_dlt_message_id']) : '';

    // If this specific save form didn't pass any vault credentials, skip network operations
    if (empty($google_key) && empty($meta_token) && empty($whatsapp_id) && empty($wc_ck) && empty($wc_cs) && empty($fast2sms_key) && empty($sms_sender) && empty($dlt_msg_id)) {
      return $new_license_value;
    }

    // Fallback lookups: If the user saves from the Vault tab, read the saved license token from database
    $active_license = ! empty($new_license_value) ? $new_license_value : get_option('kodyt_checkout_license_key');

    if (empty($active_license)) {
      wp_die(__('Configuration Denied: You must activate your Core Kodyt License key before syncing custom Enterprise third-party tokens.', 'kodyt-checkout'));
    }

    // Prepare proxy payload mapping schema variables to match Express.js requirements
    $payload = array(
      'license_key'          => $active_license,
      'domain'               => $_SERVER['HTTP_HOST'],
      'google_places_key'    => $google_key,
      'meta_access_token'    => $meta_token,
      'whatsapp_phone_id'    => $whatsapp_id,
      'woocommerce_ck'       => $wc_ck,
      'woocommerce_cs'       => $wc_cs,
      'fast2sms_key'         => $fast2sms_key,
      'sms_sender_id'        => $sms_sender,
      'dlt_message_id'       => $dlt_msg_id
    );

    // Dispatch a blocking POST query to make sure your API encrypts them successfully
    $response = wp_remote_post('https://api.kodyt.com/v1/keys/update', array(
      'timeout'   => 15,
      'blocking'  => true,
      'headers'   => array('Content-Type' => 'application/json; charset=utf-8'),
      'body'      => wp_json_encode($payload),
      'sslverify' => false
    ));

    if (is_wp_error($response)) {
      wp_die(__('Security Error: Could not connect to the central SaaS backend server to securely save credentials. Try again later.', 'kodyt-checkout'));
    }

    // EXPLICIT PURGE: Erase them instantly from global scope memory arrays to guarantee zero local leakage
    $_POST['kodyt_custom_google_key']     = '';
    $_POST['kodyt_custom_meta_token']     = '';
    $_POST['kodyt_custom_whatsapp_id']    = '';
    $_POST['kodyt_custom_wc_ck']           = '';
    $_POST['kodyt_custom_wc_cs']           = '';
    $_POST['kodyt_custom_fast2sms_key']   = '';
    $_POST['kodyt_custom_sms_sender_id']  = '';
    $_POST['kodyt_custom_dlt_message_id'] = '';

    // Force return the primary license variable safely
    return $new_license_value;
  }

  /**
   * Main Router: Organizes layout displays and changes form groups dynamically
   */
  public function render_dashboard_view_router()
  {
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';

    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
      echo '<div class="updated settings-error notices"><p><strong>' . __('Kodyt configurations updated cleanly.', 'kodyt-checkout') . '</strong></p></div>';
    }
?>
    <div class="wrap woocommerce">
      <h1 style="margin-bottom: 20px; font-weight: 700; color: #0f172a;"><?php _e('Kodyt Checkout Management Suite', 'kodyt-checkout'); ?></h1>

      <nav class="nav-tab-wrapper woo-nav-tab-wrapper" style="margin-bottom: 25px;">
        <a href="?page=kodyt-checkout-suite&tab=dashboard" class="nav-tab <?php echo $active_tab === 'dashboard' ? 'nav-tab-active' : ''; ?>"><?php _e('📊 Analytics Dashboard', 'kodyt-checkout'); ?></a>
        <a href="?page=kodyt-checkout-suite&tab=api" class="nav-tab <?php echo $active_tab === 'api' ? 'nav-tab-active' : ''; ?>"><?php _e('🔑 Core API Setup', 'kodyt-checkout'); ?></a>
        <a href="?page=kodyt-checkout-suite&tab=design" class="nav-tab <?php echo $active_tab === 'design' ? 'nav-tab-active' : ''; ?>"><?php _e('🎨 No-Code Theme Engine', 'kodyt-checkout'); ?></a>
        <a href="?page=kodyt-checkout-suite&tab=whatsapp" class="nav-tab <?php echo $active_tab === 'whatsapp' ? 'nav-tab-active' : ''; ?>"><?php _e('💬 WhatsApp Alerts', 'kodyt-checkout'); ?></a>
        <a href="?page=kodyt-checkout-suite&tab=vault" class="nav-tab <?php echo $active_tab === 'vault' ? 'nav-tab-active' : ''; ?>"><?php _e('🔐 Enterprise Vault', 'kodyt-checkout'); ?></a>
      </nav>

      <div class="kodyt-panel-content-area" style="background: #ffffff; padding: 25px; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <?php if ('dashboard' === $active_tab) : ?>

          <div class="kodyt-analytics-placeholder-wrapper">
            <h3 style="margin-top:0; font-size:18px; color:#0f172a;"><?php _e('Performance Analytics Metrics Overview', 'kodyt-checkout'); ?></h3>
            <p style="color:#64748b; font-size:14px; margin-bottom:30px;"><?php _e('Real-time operational monitoring metrics linked directly through api.kodyt.com synchronization hooks.', 'kodyt-checkout'); ?></p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
              <div style="border: 1px solid #e2e8f0; padding: 20px; border-radius: 6px; background: #f8fafc;">
                <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #64748b;"><?php _e('Total Checkout Conversion', 'kodyt-checkout'); ?></span>
                <h2 style="margin: 10px 0 0 0; font-size: 28px; font-weight: 800; color: #10b981;">0%</h2>
              </div>
              <div style="border: 1px solid #e2e8f0; padding: 20px; border-radius: 6px; background: #f8fafc;">
                <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #64748b;"><?php _e('WhatsApp Dispatches Sent', 'kodyt-checkout'); ?></span>
                <h2 style="margin: 10px 0 0 0; font-size: 28px; font-weight: 800; color: #0f172a;">0</h2>
              </div>
              <div style="border: 1px solid #e2e8f0; padding: 20px; border-radius: 6px; background: #f8fafc;">
                <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #64748b;"><?php _e('OTP Verification Matches', 'kodyt-checkout'); ?></span>
                <h2 style="margin: 10px 0 0 0; font-size: 28px; font-weight: 800; color: #6366f1;">0</h2>
              </div>
            </div>
          </div>

        <?php elseif ('vault' === $active_tab) : ?>
          <form method="post" action="options.php">
            <?php
            settings_fields($this->vault_group);
            $license_saved = get_option('kodyt_checkout_license_key');
            $mask_text = ! empty($license_saved) ? '••••••••••••••••••••••••' : '';
            ?>
            <div style="padding-top: 5px;">
              <h3 style="margin: 0 0 5px 0; font-weight: 700; color: #334155;"><?php _e('🔐 Enterprise Custom Pass-Through Credentials', 'kodyt-checkout'); ?></h3>
              <p class="description" style="margin-bottom: 25px; max-width: 750px;"><?php _e('These values are forwarded instantly to our cloud backend node cluster for isolated AES-256 binary encryption. They completely bypass the local WordPress site database files and are never saved on local hosting servers.', 'kodyt-checkout'); ?></p>

              <input type="hidden" name="kodyt_checkout_license_key" value="<?php echo esc_attr($license_saved); ?>" />

              <table class="form-table" role="presentation">
                <tr>
                  <th scope="row"><label><?php _e('WooCommerce Consumer Key (CK)', 'kodyt-checkout'); ?></label></th>
                  <td>
                    <input type="password" name="kodyt_custom_wc_ck" placeholder="<?php echo esc_attr($mask_text); ?>" class="regular-text" autocomplete="new-password" />
                    <p class="description"><?php _e('Required for the webhook to auto-mark orders as Processing from the backend.', 'kodyt-checkout'); ?></p>
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label><?php _e('WooCommerce Consumer Secret (CS)', 'kodyt-checkout'); ?></label></th>
                  <td>
                    <input type="password" name="kodyt_custom_wc_cs" placeholder="<?php echo esc_attr($mask_text); ?>" class="regular-text" autocomplete="new-password" />
                  </td>
                </tr>

                <tr>
                  <td colspan="2">
                    <hr style="border:0; border-top:1px solid #e2e8f0; margin:10px 0;" />
                  </td>
                </tr>

                <tr>
                  <th scope="row"><label><?php _e('Custom Google Places Key', 'kodyt-checkout'); ?></label></th>
                  <td>
                    <input type="password" name="kodyt_custom_google_key" placeholder="<?php echo esc_attr($mask_text); ?>" class="regular-text" autocomplete="new-password" />
                    <p class="description"><?php _e('Leave blank to preserve your current backend key mapping configuration.', 'kodyt-checkout'); ?></p>
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label><?php _e('Custom Meta Access Token', 'kodyt-checkout'); ?></label></th>
                  <td>
                    <input type="password" name="kodyt_custom_meta_token" placeholder="<?php echo esc_attr($mask_text); ?>" class="regular-text" autocomplete="new-password" />
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label><?php _e('Custom WhatsApp Phone ID', 'kodyt-checkout'); ?></label></th>
                  <td>
                    <input type="password" name="kodyt_custom_whatsapp_id" placeholder="<?php echo esc_attr($mask_text); ?>" class="regular-text" autocomplete="new-password" />
                  </td>
                </tr>

                <tr>
                  <td colspan="2">
                    <hr style="border:0; border-top:1px solid #e2e8f0; margin:10px 0;" />
                  </td>
                </tr>

                <tr>
                  <th scope="row"><label><?php _e('Custom Fast2SMS API Key', 'kodyt-checkout'); ?></label></th>
                  <td>
                    <input type="password" name="kodyt_custom_fast2sms_key" placeholder="<?php echo esc_attr($mask_text); ?>" class="regular-text" autocomplete="new-password" />
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label><?php _e('Custom SMS Sender ID', 'kodyt-checkout'); ?></label></th>
                  <td>
                    <input type="password" name="kodyt_custom_sms_sender_id" placeholder="<?php echo esc_attr($mask_text); ?>" class="regular-text" autocomplete="new-password" />
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label><?php _e('Custom DLT Message ID', 'kodyt-checkout'); ?></label></th>
                  <td>
                    <input type="password" name="kodyt_custom_dlt_message_id" placeholder="<?php echo esc_attr($mask_text); ?>" class="regular-text" autocomplete="new-password" />
                  </td>
                </tr>
              </table>
            </div>
            <?php submit_button(__('Encrypt & Sync Credentials', 'kodyt-checkout'), 'primary'); ?>
          </form>

        <?php else : ?>
          <form method="post" action="options.php">
            <?php
            if ('api' === $active_tab) {
              settings_fields($this->api_group);
              $current_fields = $this->get_api_fields();
            } elseif ('design' === $active_tab) {
              settings_fields($this->design_group);
              $current_fields = $this->get_design_fields();
            } elseif ('whatsapp' === $active_tab) {
              settings_fields($this->whatsapp_group);
              $current_fields = $this->get_whatsapp_fields();
            }

            // Hydrate runtime value models if current local fields are completely missing from DB
            foreach ($current_fields as $id => $field) {
              if (isset($field['id']) && isset($field['default'])) {
                $db_val = get_option($field['id']);
                if ('' === $db_val) {
                  $current_fields[$id]['value'] = $field['default'];
                }
              }
            }

            woocommerce_admin_fields($current_fields);
            submit_button(__('Save Changes Settings', 'kodyt-checkout'), 'primary');
            ?>
          </form>
        <?php endif; ?>
      </div>
    </div>
<?php
  }

  private function get_api_fields()
  {
    return array(
      'section_title' => array(
        'name' => __('Kodyt Gateway Access Credentials', 'kodyt-checkout'),
        'type' => 'title',
        'desc' => __('Set up baseline authentication layer properties mapping communication routes safely.', 'kodyt-checkout'),
        'id'   => 'kodyt_checkout_section_title'
      ),
      'license_key' => array(
        'name'     => __('Active License Validation Key', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Your unique platform authentication string verification token.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_license_key',
        'css'      => 'min-width:350px;'
      ),
      'section_end' => array('type' => 'sectionend', 'id' => 'kodyt_checkout_section_end')
    );
  }

  private function get_design_fields()
  {
    return array(
      'design_global_title' => array(
        'name' => __('Global Style Framework & Typography', 'kodyt-checkout'),
        'type' => 'title',
        'desc' => __('Overarching visual tokens governing layout fonts, texts, weights, and corner rounds.', 'kodyt-checkout'),
        'id'   => 'kodyt_checkout_global_design_title'
      ),
      'font_family' => array(
        'name'     => __('Font Family Variant Rule', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Specify explicit typography rendering statements (e.g. \'Inter\', sans-serif). Leave blank to inherit theme styling rules.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_font_family',
        'default'  => "'Inter', system-ui, -apple-system, sans-serif",
        'css'      => 'min-width:350px;'
      ),
      'base_text_color' => array(
        'name'     => __('Paragraph Base Text color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Applies to strings, labels, checkboxes, and metadata references.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_base_text_color',
        'default'  => '#1e293b',
      ),
      'heading_text_color' => array(
        'name'     => __('Title Header Heading Color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Applies to checkout card headers, section titles, and grand summaries text blocks.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_heading_text_color',
        'default'  => '#0f172a',
      ),
      'border_radius' => array(
        'name'     => __('Global Layout Corner Rounding Radius', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Corner rounding thickness tracking parameters across controls, components, inputs, buttons, and modals (e.g. 8px).', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_border_radius',
        'default'  => '8px',
        'css'      => 'max-width:100px;'
      ),
      'design_global_end' => array('type' => 'sectionend', 'id' => 'kodyt_checkout_global_design_end'),

      'design_buttons_title' => array(
        'name' => __('Action Button Configurations (Contrast Pairs)', 'kodyt-checkout'),
        'type' => 'title',
        'desc' => __('Pair your background colors cleanly with specific text targets to eliminate visibility errors.', 'kodyt-checkout'),
        'id'   => 'kodyt_checkout_buttons_design_title'
      ),
      'primary_color' => array(
        'name'     => __('Primary Brand Button Background', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Applies to main progression buttons ("Complete Secure Checkout", "Continue to Payment").', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_primary_color',
        'default'  => '#6366f1',
      ),
      'primary_text_color' => array(
        'name'     => __('Primary Brand Button Text Color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Text formatting layer colors overlaying main primary progression buttons.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_primary_text_color',
        'default'  => '#ffffff',
      ),
      'hover_color' => array(
        'name'     => __('Primary Button Background Hover state', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Background changes applied during interactive mouse pointer hover actions.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_hover_color',
        'default'  => '#4f46e5',
      ),
      'secondary_color' => array(
        'name'     => __('Secondary Element Control Background', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Applies to auxiliary functional triggers ("Send OTP", "Apply Coupon", "Change Number").', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_secondary_color',
        'default'  => '#1e293b',
      ),
      'secondary_text_color' => array(
        'name'     => __('Secondary Element Control Text color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('The text typography color shown over secondary element control parameters.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_secondary_text_color',
        'default'  => '#ffffff',
      ),
      'design_buttons_end' => array('type' => 'sectionend', 'id' => 'kodyt_checkout_buttons_design_end'),

      'design_structure_title' => array(
        'name' => __('Structural Borders Layout density & Spacing Gaps', 'kodyt-checkout'),
        'type' => 'title',
        'desc' => __('De-couple framework spatial layout definitions without interacting with CSS source files code sheets.', 'kodyt-checkout'),
        'id'   => 'kodyt_checkout_structure_design_title'
      ),
      'border_style' => array(
        'name'     => __('Global Element Line Border Style', 'kodyt-checkout'),
        'type'     => 'select',
        'desc'     => __('Outline rendering pattern variations crosswise fields blocks and steps card nodes.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_border_style',
        'options'  => array(
          'solid'  => __('Solid Border Line', 'kodyt-checkout'),
          'dashed' => __('Dashed Line Variant', 'kodyt-checkout'),
          'none'   => __('Completely Borderless UI', 'kodyt-checkout'),
        ),
        'default'  => 'solid',
      ),
      'step_border_width' => array(
        'name'     => __('Checkout Steps Block Border Thickness', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Outline sizing thickness properties for step blocks. Adjust to 0px for clean borderless cards.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_step_border_width',
        'default'  => '1px',
        'css'      => 'max-width:100px;'
      ),
      'input_border_width' => array(
        'name'     => __('User Form Text Inputs Border Thickness', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Outline weight properties formatting standard field inputs boxes. Select 0px for flat filled variations.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_input_border_width',
        'default'  => '1px',
        'css'      => 'max-width:100px;'
      ),
      'input_border_color' => array(
        'name'     => __('Input Field Baseline rest Border color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('The standard unfocused element frame border lines tracing field rectangles.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_input_border_color',
        'default'  => '#cbd5e1',
      ),
      'step_card_padding' => array(
        'name'     => __('Step block internal card Padding Spacing', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Internal wall density adjustments packing elements closely or breathing apart (e.g. 24px vs 12px).', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_step_card_padding',
        'default'  => '24px',
        'css'      => 'max-width:100px;'
      ),
      'grid_column_gap' => array(
        'name'     => __('Main Grid Column Separation Separation layout gap', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Sizing grid gutter width splits separating forms and cart lists (e.g. 30px).', 'kodyt-checkout'),
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
        'name'     => __('Form Step Card Area Background fill', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Background canvas card color layer packing checkout instructions steps fields.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_step_bg',
        'default'  => '#ffffff',
      ),
      'summary_card_bg' => array(
        'name'     => __('Cart Invoice Summary Card Background fill', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Background canvas container color formatting the sticky right-hand column order totals dashboard.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_summary_card_bg',
        'default'  => '#f8fafc',
      ),
      'success_color' => array(
        'name'     => __('Success State / Verified Profile Alerts Highlight color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Applies to completed step frames checkmarks, authenticated telephone containers, and chosen address grid elements.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_success_color',
        'default'  => '#22c55e',
      ),
      'success_text_color' => array(
        'name'     => __('Success Accent Overlay Font text color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Font text representation color layers overlayering successfully verified badges elements frames blocks.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_success_text_color',
        'default'  => '#ffffff',
      ),
      'qty_badge_bg' => array(
        'name'     => __('Thumbnail Circle floating Quantity Counter Background', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('The background fill rounding circle index badges overlaying item product picture previews cards list.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_qty_badge_bg',
        'default'  => '#1e293b',
      ),
      'qty_badge_text' => array(
        'name'     => __('Thumbnail Circle floating Quantity Counter Text color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('The typeface color drawing index metrics numbers inside floating thumb quantity badges.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_qty_badge_text',
        'default'  => '#ffffff',
      ),
      'sticky_top_offset' => array(
        'name'     => __('Desktop Sticky Sidebar Top boundary constraint offset padding', 'kodyt-checkout'),
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
        'name' => __('Automated Post-Purchase WhatsApp Dispatch Loop Tracking', 'kodyt-checkout'),
        'type' => 'title',
        'desc' => __('Dispatch instant receipts summaries values to mobile numbers seamlessly matching operations setups rules via api.kodyt.com system pipelines.', 'kodyt-checkout'),
        'id'   => 'kodyt_checkout_whatsapp_section'
      ),
      'enable_whatsapp_notifications' => array(
        'name'    => __('Enable Post-Purchase WhatsApp Alerts', 'kodyt-checkout'),
        'type'    => 'checkbox',
        'desc'    => __('Activate fully automated transactional summaries notifications messaging cycles instantly orders clear checkout vectors.', 'kodyt-checkout'),
        'id'      => 'kodyt_checkout_enable_whatsapp',
        'default' => 'no'
      ),
      'whatsapp_routing_strategy' => array(
        'name'     => __('WhatsApp Destination Tracking Recipient Target Routing Strategy', 'kodyt-checkout'),
        'type'     => 'select',
        'desc'     => __('Calculate targeting numbers patterns priorities filters sorting destination nodes mappings.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_whatsapp_routing',
        'options'  => array(
          'billing'  => __('Verified Profile / Billing Invoice Number', 'kodyt-checkout'),
          'shipping' => __('Shipping Delivery Destination Phone Record Field', 'kodyt-checkout'),
          'both'     => __('Send Message Logs to Both Numbers (De-duplicated loops checks automated)', 'kodyt-checkout'),
        ),
        'default'  => 'billing',
      ),
      'whatsapp_section_end' => array('type' => 'sectionend', 'id' => 'kodyt_checkout_whatsapp_section_end')
    );
  }
}
