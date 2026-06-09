<?php
if (! defined('ABSPATH')) exit;

class Kodyt_Checkout_Settings
{

  public function __construct()
  {
    add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50);
    add_action('woocommerce_settings_tabs_kodyt_checkout_tab', array($this, 'render_settings_fields'));
    add_action('woocommerce_update_options_kodyt_checkout_tab', array($this, 'update_settings_fields'));
  }

  public function add_settings_tab($settings_tabs)
  {
    $settings_tabs['kodyt_checkout_tab'] = __('Kodyt Checkout', 'kodyt-checkout');
    return $settings_tabs;
  }

  public function render_settings_fields()
  {
    woocommerce_admin_fields($this->get_settings_schema());
  }

  public function update_settings_fields()
  {
    woocommerce_update_options($this->get_settings_schema());
  }

  public function get_settings_schema()
  {
    return array(
      // =====================================================================
      // SECTION 1: CORE CREDENTIALS API CONFIGURATIONS
      // =====================================================================
      'section_title' => array(
        'name'     => __('Kodyt Gateway Configurations', 'kodyt-checkout'),
        'type'     => 'title',
        'desc'     => __('Configure connection policies matching your api.kodyt.com service distribution layers.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_section_title'
      ),
      'license_key' => array(
        'name'     => __('License Key', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Your active commercial API platform authentication string.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_license_key',
        'css'      => 'min-width:350px;'
      ),
      'section_end' => array(
        'type'     => 'sectionend',
        'id'       => 'kodyt_checkout_section_end'
      ),

      // =====================================================================
      // SECTION 2: GLOBAL LOOK & TYPOGRAPHY
      // =====================================================================
      'design_global_title' => array(
        'name'     => __('Global Layout & Typography', 'kodyt-checkout'),
        'type'     => 'title',
        'desc'     => __('Control overarching typography, text baseline colors, and rounded structural elements.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_global_design_title'
      ),
      'font_family' => array(
        'name'     => __('Font Family Override', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Specify custom typography rules (e.g., \'Inter\', sans-serif). Leave empty to inherit main theme fonts.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_font_family',
        'default'  => "'Inter', system-ui, -apple-system, sans-serif",
        'css'      => 'min-width:350px;'
      ),
      'base_text_color' => array(
        'name'     => __('Base Text Color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Applies to paragraphs, general descriptive labels, and checkout strings.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_base_text_color',
        'default'  => '#1e293b',
      ),
      'heading_text_color' => array(
        'name'     => __('Heading Text Color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Applies to checkout card step headers and main section titles.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_heading_text_color',
        'default'  => '#0f172a',
      ),
      'border_radius' => array(
        'name'     => __('Global Corner Radius', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Rounding applied to inputs, action buttons, utility cards, and modals (e.g., 8px, 12px, 0px).', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_border_radius',
        'default'  => '8px',
        'css'      => 'max-width:100px;'
      ),
      'design_global_end' => array(
        'type'     => 'sectionend',
        'id'       => 'kodyt_checkout_global_design_end'
      ),

      // =====================================================================
      // SECTION 3: BUTTON BRAND CONTROLS (PRIMARY VS SECONDARY)
      // =====================================================================
      'design_buttons_title' => array(
        'name'     => __('Action Button Customisations', 'kodyt-checkout'),
        'type'     => 'title',
        'desc'     => __('Configure paired background and text layouts for primary and secondary action layers.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_buttons_design_title'
      ),
      'primary_color' => array(
        'name'     => __('Primary Button Background', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Applies to critical checkout progression paths (e.g., "Complete Secure Checkout", "Continue to Payment").', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_primary_color',
        'default'  => '#6366f1',
      ),
      'primary_text_color' => array(
        'name'     => __('Primary Button Text Color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('The text color displayed inside primary brand buttons.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_primary_text_color',
        'default'  => '#ffffff',
      ),
      'hover_color' => array(
        'name'     => __('Primary Button Hover Background', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('The hover state background color for your main progression paths.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_hover_color',
        'default'  => '#4f46e5',
      ),
      'secondary_color' => array(
        'name'     => __('Secondary Button Background', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Applies to standalone interactive controls (e.g., "Send OTP", "Apply Coupon", "Change Number").', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_secondary_color',
        'default'  => '#1e293b',
      ),
      'secondary_text_color' => array(
        'name'     => __('Secondary Button Text Color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('The text color displayed inside auxiliary/secondary interaction links.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_secondary_text_color',
        'default'  => '#ffffff',
      ),
      'design_buttons_end' => array(
        'type'     => 'sectionend',
        'id'       => 'kodyt_checkout_buttons_design_end'
      ),

      // =====================================================================
      // SECTION 4: STRUCTURAL BORDERS & PADDING DENSITY
      // =====================================================================
      'design_structure_title' => array(
        'name'     => __('Structural Borders & Spacing Layouts', 'kodyt-checkout'),
        'type'     => 'title',
        'desc'     => __('Customise spatial layout structures, spacing parameters, and border thickness attributes.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_structure_design_title'
      ),
      'border_style' => array(
        'name'     => __('Global Layout Border Style', 'kodyt-checkout'),
        'type'     => 'select',
        'desc'     => __('Specify the outline design framework style across fields and checkout steps cards.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_border_style',
        'options'  => array(
          'solid'  => __('Solid Outline', 'kodyt-checkout'),
          'dashed' => __('Dashed Line', 'kodyt-checkout'),
          'none'   => __('No Border Bounds', 'kodyt-checkout'),
        ),
        'default'  => 'solid',
      ),
      'step_border_width' => array(
        'name'     => __('Step Cards Border Width', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Outline width for step blocks. Set to 0px to make step container backgrounds borderless.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_step_border_width',
        'default'  => '1px',
        'css'      => 'max-width:100px;'
      ),
      'input_border_width' => array(
        'name'     => __('Form Inputs Border Width', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Thickness for user text fields. Adjust to 0px to create borderless filled inputs.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_input_border_width',
        'default'  => '1px',
        'css'      => 'max-width:100px;'
      ),
      'input_border_color' => array(
        'name'     => __('Input Baseline Border Color', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('The rest/unfocused color wrapper outline of text fields.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_input_border_color',
        'default'  => '#cbd5e1',
      ),
      'step_card_padding' => array(
        'name'     => __('Step Container Internal Padding', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Spacing inside each distinct step area block container (e.g., 24px for roomy layouts, 12px for high density).', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_step_card_padding',
        'default'  => '24px',
        'css'      => 'max-width:100px;'
      ),
      'grid_column_gap' => array(
        'name'     => __('Grid Column Separation Separation', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Spacing between steps and order summary columns (e.g., 30px).', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_grid_column_gap',
        'default'  => '30px',
        'css'      => 'max-width:100px;'
      ),
      'design_structure_end' => array(
        'type'     => 'sectionend',
        'id'       => 'kodyt_checkout_structure_design_end'
      ),

      // =====================================================================
      // SECTION 5: CARD MODULE CONTAINERS & EXTENDED VISUAL ACCENTS
      // =====================================================================
      'design_accents_title' => array(
        'name'     => __('Containers, Cards & Extended Visual Accents', 'kodyt-checkout'),
        'type'     => 'title',
        'desc'     => __('Define status accent elements, sticky container options, shopping summary badges, and search dropdown lists.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_accents_design_title'
      ),
      'step_bg' => array(
        'name'     => __('Step Block Card Background', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Background fill inside your primary form steps blocks.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_step_bg',
        'default'  => '#ffffff',
      ),
      'summary_card_bg' => array(
        'name'     => __('Order Summary Background', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Background fill inside the right-hand cart summary card block column.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_summary_card_bg',
        'default'  => '#f8fafc',
      ),
      'success_color' => array(
        'name'     => __('Success State / Verified Highlights', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Controls validated profile alerts, completed step check borders, and chosen address grids.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_success_color',
        'default'  => '#22c55e',
      ),
      'success_text_color' => array(
        'name'     => __('Success Accent Overlayer Text', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('Text color displayed over successful accent blocks to guarantee accessible contrast.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_success_text_color',
        'default'  => '#ffffff',
      ),
      'qty_badge_bg' => array(
        'name'     => __('Quantity Thumbnail Circle Badge Background', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('The background fill inside the floating quantity counters displayed over item thumbnail boxes.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_qty_badge_bg',
        'default'  => '#1e293b',
      ),
      'qty_badge_text' => array(
        'name'     => __('Quantity Thumbnail Circle Badge Text', 'kodyt-checkout'),
        'type'     => 'color',
        'desc'     => __('The text color inside the item thumbnail floating quantity counters.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_qty_badge_text',
        'default'  => '#ffffff',
      ),
      'sticky_top_offset' => array(
        'name'     => __('Sticky Cart Summary Top Offset Height', 'kodyt-checkout'),
        'type'     => 'text',
        'desc'     => __('Distance from the top of the viewport when scrolling desktop views (e.g., 20px). Increase this if it clips beneath your header.', 'kodyt-checkout'),
        'id'       => 'kodyt_checkout_sticky_top_offset',
        'default'  => '20px',
        'css'      => 'max-width:100px;'
      ),
      'design_accents_end' => array(
        'type'     => 'sectionend',
        'id'       => 'kodyt_checkout_accents_design_end'
      )
    );
  }
}
