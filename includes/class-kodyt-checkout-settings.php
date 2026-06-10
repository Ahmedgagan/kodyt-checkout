<?php
if (! defined('ABSPATH')) exit;

class Kodyt_Checkout_Settings
{
  private $api_group      = 'kodyt_checkout_api_group';
  private $design_group   = 'kodyt_checkout_design_group';
  private $whatsapp_group = 'kodyt_checkout_whatsapp_group';

  public function __construct()
  {
    add_action('admin_menu', array($this, 'register_standalone_admin_menu'));
    add_action('admin_init', array($this, 'register_plugin_settings_schema'));

    // NATIVE, SINGLE-POINT HOOK: Fires strictly and exclusively when our custom form is submitted
    add_action('admin_post_kodyt_save_vault_credentials', array($this, 'process_vault_pass_through_sync'));
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
  }

  public function sanitize_checkbox_field_state($value)
  {
    return (! empty($value) && ('1' === $value || 'yes' === $value || 'on' === $value)) ? 'yes' : 'no';
  }

  /**
   * PROD-READY ISOLATED INTERCEPTOR: Routes multi-field inputs straight to your Node server
   */
  public function process_vault_pass_through_sync()
  {
    // Verify user authorization rules and cross-site scripting safety nonces instantly
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
        }
      }
    }

    // Pull down active validation details out of regional options table data
    $active_license = get_option('kodyt_checkout_license_key');
    if (empty($active_license)) {
      wp_die(__('Configuration Denied: You must configure and save an active License Key under the API Setup tab before linking custom credentials.', 'kodyt-checkout'));
    }

    $creds = class_exists('Kodyt_Api_Client') ? Kodyt_Api_Client::get_credentials() : array('license_key' => '', 'domain' => '');

    // Execute backend proxy sync if modifications exist
    if (! empty($payload)) {
      $payload['license_key'] = isset($creds['license_key']) ? $creds['license_key'] : '';
      $payload['domain'] = isset($creds['domain']) ? $creds['domain'] : '';

      error_log(json_encode($payload));
      $response = wp_remote_post('https://api.kodyt.com/v1/keys/update', array(
        'method'    => 'PATCH',
        'timeout'   => 15,
        'blocking'  => true,
        'headers'   => array('Content-Type' => 'application/json; charset=utf-8'),
        'body'      => wp_json_encode($payload),
        'sslverify' => false
      ));
      error_log(json_encode($response));
      if (is_wp_error($response)) {
        wp_die(__('Network Failure: Unable to sync credentials with central encryption gateway.', 'kodyt-checkout'));
      }
    }

    // Safely redirect back to your dashboard page tab context with an explicit success flag parameter
    $redirect_url = add_query_arg(
      array('page' => 'kodyt-checkout-suite', 'tab' => 'vault', 'vault-updated' => 'true'),
      admin_url('admin.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
  }

  public function render_dashboard_view_router()
  {
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';

    if (isset($_GET['vault-updated']) && $_GET['vault-updated'] == 'true') {
      echo '<div class="updated notice is-dismissible"><p><strong>' . __('Enterprise vault options successfully encrypted and synced.', 'kodyt-checkout') . '</strong></p></div>';
    }
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
      echo '<div class="updated notice is-dismissible"><p><strong>' . __('Kodyt configurations updated cleanly.', 'kodyt-checkout') . '</strong></p></div>';
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
            <h3><?php _e('Performance Analytics Metrics Overview', 'kodyt-checkout'); ?></h3>
            <p><?php _e('Real-time operational monitoring metrics linked directly through api.kodyt.com synchronization hooks.', 'kodyt-checkout'); ?></p>
          </div>

        <?php elseif ('vault' === $active_tab) : ?>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">

            <input type="hidden" name="action" value="kodyt_save_vault_credentials" />
            <?php wp_nonce_field('kodyt_vault_security_action', 'kodyt_vault_nonce'); ?>

            <h3 style="margin: 0 0 5px 0; font-weight: 700; color: #334155;"><?php _e('🔐 Enterprise Custom Pass-Through Credentials', 'kodyt-checkout'); ?></h3>
            <p class="description" style="margin-bottom: 25px; max-width: 750px;"><?php _e('Modify any combination of fields below. Untouched credentials are safely bypassed and left unmodified on your infrastructure tables.', 'kodyt-checkout'); ?></p>

            <table class="form-table" role="presentation">
              <tr>
                <th scope="row"><label><?php _e('WooCommerce Consumer Key (CK)', 'kodyt-checkout'); ?></label></th>
                <td><input type="password" name="kodyt_custom_wc_ck" value="••••••••••••••••••••••••" class="regular-text" autocomplete="new-password" /></td>
              </tr>
              <tr>
                <th scope="row"><label><?php _e('WooCommerce Consumer Secret (CS)', 'kodyt-checkout'); ?></label></th>
                <td><input type="password" name="kodyt_custom_wc_cs" value="••••••••••••••••••••••••" class="regular-text" autocomplete="new-password" /></td>
              </tr>
              <tr>
                <td colspan="2">
                  <hr style="border:0; border-top:1px solid #e2e8f0; margin:10px 0;" />
                </td>
              </tr>
              <tr>
                <th scope="row"><label><?php _e('Custom Google Places Key', 'kodyt-checkout'); ?></label></th>
                <td><input type="password" name="kodyt_custom_google_key" value="••••••••••••••••••••••••" class="regular-text" autocomplete="new-password" /></td>
              </tr>
              <tr>
                <th scope="row"><label><?php _e('Custom Meta Access Token', 'kodyt-checkout'); ?></label></th>
                <td><input type="password" name="kodyt_custom_meta_token" value="••••••••••••••••••••••••" class="regular-text" autocomplete="new-password" /></td>
              </tr>
              <tr>
                <th scope="row"><label><?php _e('Custom WhatsApp Phone ID', 'kodyt-checkout'); ?></label></th>
                <td><input type="password" name="kodyt_custom_whatsapp_id" value="••••••••••••••••••••••••" class="regular-text" autocomplete="new-password" /></td>
              </tr>
              <tr>
                <td colspan="2">
                  <hr style="border:0; border-top:1px solid #e2e8f0; margin:10px 0;" />
                </td>
              </tr>
              <tr>
                <th scope="row"><label><?php _e('Custom Fast2SMS API Key', 'kodyt-checkout'); ?></label></th>
                <td><input type="password" name="kodyt_custom_fast2sms_key" value="••••••••••••••••••••••••" class="regular-text" autocomplete="new-password" /></td>
              </tr>
              <tr>
                <th scope="row"><label><?php _e('Custom SMS Sender ID', 'kodyt-checkout'); ?></label></th>
                <td><input type="password" name="kodyt_custom_sms_sender_id" value="••••••••••••••••••••••••" class="regular-text" autocomplete="new-password" /></td>
              </tr>
              <tr>
                <th scope="row"><label><?php _e('Custom DLT Message ID', 'kodyt-checkout'); ?></label></th>
                <td><input type="password" name="kodyt_custom_dlt_message_id" value="••••••••••••••••••••••••" class="regular-text" autocomplete="new-password" /></td>
              </tr>
            </table>

            <?php submit_button(__('Encrypt & Sync Vault', 'kodyt-checkout'), 'primary', 'save_kodyt_vault'); ?>
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
      'section_title' => array('name' => __('Kodyt Access Credentials', 'kodyt-checkout'), 'type' => 'title', 'id' => 'kodyt_checkout_section_title'),
      'license_key'   => array('name' => __('Active License Validation Key', 'kodyt-checkout'), 'type' => 'text', 'id' => 'kodyt_checkout_license_key', 'css' => 'min-width:350px;'),
      'section_end'   => array('type' => 'sectionend', 'id' => 'kodyt_checkout_section_end')
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
