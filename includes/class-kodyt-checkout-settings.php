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
      )
    );
  }
}
