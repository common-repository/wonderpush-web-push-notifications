<?php
if (!defined( 'ABSPATH' )) { http_response_code(403); exit(); }

/*
Plugin Name: WonderPush Web Push Notifications
Plugin URI: https://www.wonderpush.com/
Description: Web push notifications, GDPR compliant.
Author: WonderPush
Author URI: https://www.wonderpush.com/
Version: 1.11.5
License: GPLv2 or later
*/

define( 'WONDERPUSH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WONDERPUSH_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
require_once( plugin_dir_path( __FILE__ ) . 'http-build-url.php' );
require_once( plugin_dir_path( __FILE__ ) . 'init.php' );
require_once( plugin_dir_path( __FILE__ ) . 'wonderpush-http-client.php' );
require_once( plugin_dir_path( __FILE__ ) . 'wonderpush-utils.php' );
require_once( plugin_dir_path( __FILE__ ) . 'wonderpush-settings.php' );
require_once( plugin_dir_path( __FILE__ ) . 'wonderpush-api.php' );
require_once( plugin_dir_path( __FILE__ ) . 'wonderpush-admin.php' );
require_once( plugin_dir_path( __FILE__ ) . 'wonderpush-public.php' );
require_once( plugin_dir_path( __FILE__ ) . 'wonderpush-woocommerce.php' );
//require_once( plugin_dir_path( __FILE__ ) . 'wonderpush-asgaros.php' );
add_action( 'init', array( 'WonderPushPublic', 'init' ) );
add_action( 'init', array( 'WonderPushAdmin', 'init' ) );
add_action( 'init', array( 'WonderPushAPI', 'init' ) );
add_action( 'init', array( 'WonderPushWooCommerce', 'init' ) );
//add_action( 'init', array( 'WonderPushAsgarosForum', 'init' ) );

add_filter( 'plugin_action_links_wonderpush/wonderpush.php', 'wonderpush_add_settings_link' );

function wonderpush_add_settings_link() {
  $links[] = '<a href="' .
    admin_url( 'admin.php?page=' . WonderPushAdmin::MENU_SLUG ) .
    '">' . __('Settings') . '</a>';
  return $links;
}

if (!function_exists('get_wonderpush_client')) {
  function get_wonderpush_client() {
    $settings = WonderPushSettings::getSettings();
    $access_token = $settings->getAccessToken();
    return  WonderPushUtils::management_api_client($access_token);
  }
}
