<?php

/**
 * Plugin Name: Kodyt Checkout
 * Description: A premium, ultra-sleek multi-step layout for WooCommerce checkout utilizing native WC_Customer data layers and Kodyt Edge APIs.
 * Version: 1.3.0
 * Author: Kodyt Team
 */

if (! defined('ABSPATH')) exit;

define('KODYT_CHECKOUT_PATH', plugin_dir_path(__FILE__));
define('KODYT_CHECKOUT_URL', plugin_dir_url(__FILE__));
// define('API_URL', 'https://api.kodyt.com');
define('API_URL', 'http://localhost:3000');

// Autoload Core Infrastructure Dependencies
require_once KODYT_CHECKOUT_PATH . 'includes/class-kodyt-checkout-core.php';
require_once KODYT_CHECKOUT_PATH . 'includes/class-kodyt-checkout-settings.php';
require_once KODYT_CHECKOUT_PATH . 'includes/class-kodyt-api-client.php';
require_once KODYT_CHECKOUT_PATH . 'includes/class-kodyt-user-bridge.php';

// Autoload Modular Feature Feature Modules
require_once KODYT_CHECKOUT_PATH . 'modules/auth/class-kodyt-auth-handler.php';
require_once KODYT_CHECKOUT_PATH . 'modules/location/class-kodyt-location-handler.php';
require_once KODYT_CHECKOUT_PATH . 'modules/marketing/class-kodyt-coupon-handler.php';

require_once KODYT_CHECKOUT_PATH . 'modules/notifications/class-kodyt-notification-handler.php';

function run_kodyt_checkout_engine()
{
  new Kodyt_Checkout_Core();
  new Kodyt_Checkout_Settings();

  // Core Engine Sub-Feature Orchestrators
  new Kodyt_Auth_Handler();
  new Kodyt_Location_Handler();
  new Kodyt_Coupon_Handler();
  // NEW: Fire Up WhatsApp Notification Module Hook Listeners
  new Kodyt_Notification_Handler();
}
add_action('plugins_loaded', 'run_kodyt_checkout_engine');
