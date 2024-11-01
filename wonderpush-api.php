<?php
if (!defined( 'ABSPATH' )) { http_response_code(403); exit(); }

class WonderPushAPI {

  const NONCE_ACTION = 'wpnonce';
  const ADMIN_ACCESS = 'admin';
  const EDITOR_ACCESS = 'editor';
  private static $nonce = null;

  static function init() {
    add_action('wp_ajax_wonderpush_get_configuration', array(__CLASS__, 'wonderpush_get_configuration'));
    add_action('wp_ajax_wonderpush_update_configuration', array(__CLASS__, 'wonderpush_update_configuration'));
    add_action('wp_ajax_wonderpush_set_access_token', array(__CLASS__, 'wonderpush_set_access_token'));
    add_action('wp_ajax_wonderpush_get_post_metadata', array(__CLASS__, 'wonderpush_get_post_metadata'));
  }
  public static function get_nonce() {
    if (self::$nonce === null) self::$nonce = wp_create_nonce(self::NONCE_ACTION);
    return self::$nonce;
  }

  private static function verify_nonce() {
    $nonce = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $nonce = $_POST['nonce'];
    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      $nonce = $_GET['nonce'];
    }
    if (!$nonce || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
      wp_die('Forbidden', 403);
    }
  }

  private static function verify_access($access_type = WonderPushAPI::ADMIN_ACCESS) {
    self::verify_nonce();
    if ($access_type === WonderPushAPI::EDITOR_ACCESS) {
      if (!WonderPushUtils::can_send_notifications()) {
        wp_die('Forbidden', 403);
      }
    } else {
      if (!WonderPushUtils::can_modify_settings()) {
        wp_die('Forbidden', 403);
      }
    }
  }

  private static function returnResult($result) {
    header('Content-Type: application/json');
    $json = json_encode($result);
    echo $json;
    wp_die();
  }

  private static function returnError($msg, $statusCode) {
    header('Content-Type: application/json');
    wp_die(json_encode(array(
      'error' => array(
        'message' => $msg,
        'code' => $statusCode,
      ),
    )), $statusCode);
  }

  public static function wonderpush_get_configuration() {
    self::verify_access();
    $settings = WonderPushSettings::getSettings();
    $access_token = $settings->getAccessToken();
    $app = null;
    if ($access_token) {
      $app = WonderPushUtils::application_from_access_token($access_token);
    }
    $urlParameters = $app ? (object)array_merge($settings->getUtmParameters(), (array)$app->getUrlParameters()) : (object)array();
    self::returnResult((object)array(
      'accessToken' => $access_token ? $access_token : null,
      'applicationId' => $app && $app->getId() ? $app->getId() : null,
      'bypassWordPressHttpClient' => $settings->getBypassWordPressHttpClient(),
      'deliveryTimeSeconds' => $settings->getDeliveryTimeSeconds(),
      'notificationTitle' => $settings->getNotificationTitle(),
      'defaultTargetSegmentId' => $settings->getDefaultTargetSegmentId(),
      'additionalCustomPostTypes' => $settings->getAdditionalCustomPostTypes(),
      'disableUserIdInSnippet' => $settings->getDisableUserIdInSnippet(),
      'emailAsUserId' => $settings->getEmailAsUserId(),
      'disableSnippet' => $settings->getDisableSnippet(),
      'disableSendOnPublish' => $settings->getDisableSendOnPublish(),
      'disableSendByDefaultOnPublish' => $settings->getDisableSendByDefaultOnPublish(),
      'sendOnThirdPartyPublish' => $settings->getSendOnThirdPartyPublish(),
      'disableFeedbackOnPublish' => $settings->getDisableFeedbackOnPublish(),
      'disableUsePostImageForNotification' => $settings->getDisableUsePostImageForNotification(),
      'preferLargeImageForNotification' => $settings->getPreferLargeImageForNotification(),
      'enableCartReminder' => $settings->getEnableCartReminder(),
      'enableUserSegmentation' => $settings->getEnableUserSegmentation(),
      'enableOrderCompleteNotifications' => $settings->getEnableOrderCompleteNotifications(),
      'orderCompleteNotificationsMessage' => $settings->getOrderCompleteNotificationsMessage(),
      'enableOrderProcessingNotifications' => $settings->getEnableOrderProcessingNotifications(),
      'orderProcessingNotificationsMessage' => $settings->getOrderProcessingNotificationsMessage(),
      'cartReminderStrategy' => $settings->getCartReminderStrategy(),
      'cartReminderDestination' => $settings->getCartReminderDestination(),
      'cartReminderMessage' => $settings->getCartReminderMessage(),
      'disableCartReminderImage' => $settings->getDisableCartReminderImage(),
      'disableThankYouEvent' => $settings->getDisableThankYouEvent(),
      'thankYouEventName' => $settings->getThankYouEventName(),
      'disableAmpUnsubscribe' => $settings->getDisableAmpUnsubscribe(),
      'ampSubscribeButtonLabel' => $settings->getAmpSubscribeButtonLabel(),
      'ampUnsubscribeButtonLabel' => $settings->getAmpUnsubscribeButtonLabel(),
      'disableAmpBottomSubscribeButton' => $settings->getDisableAmpBottomSubscribeButton(),
      'disableAmpTopSubscribeButton' => $settings->getDisableAmpTopSubscribeButton(),
      'ampButtonWidth' => (int)$settings->getAmpButtonWidth(),
      'ampButtonHeight' => (int)$settings->getAmpButtonHeight(),
      'utmSource' => property_exists($urlParameters, 'utm_source') ? $urlParameters->utm_source : null,
      'utmCampaign' => property_exists($urlParameters, 'utm_campaign') ? $urlParameters->utm_campaign : null,
      'utmMedium' => property_exists($urlParameters, 'utm_medium') ? $urlParameters->utm_medium : null,
      'utmTerm' => property_exists($urlParameters, 'utm_term') ? $urlParameters->utm_term : null,
      'utmContent' => property_exists($urlParameters, 'utm_content') ? $urlParameters->utm_content : null,
      'additionalInitOptionsJson' => $settings->getAdditionalInitOptionsJson(),

    ));
  }

  public static function wonderpush_update_configuration() {
    self::verify_access();
    $settings = WonderPushSettings::getSettings();
    $save = false;
    // Boolean props
    foreach (array(
               'bypassWordPressHttpClient',
               'disableSnippet',
               'disableUserIdInSnippet',
               'emailAsUserId',
               'disableSendOnPublish',
               'disableSendByDefaultOnPublish',
               'sendOnThirdPartyPublish',
               'disableFeedbackOnPublish',
               'disableUsePostImageForNotification',
               'preferLargeImageForNotification',
               'enableCartReminder',
               'enableUserSegmentation',
               'enableOrderCompleteNotifications',
               'enableOrderProcessingNotifications',
               'disableCartReminderImage',
               'disableAmpUnsubscribe',
               'disableAmpBottomSubscribeButton',
               'disableAmpTopSubscribeButton',
               'disableThankYouEvent',
             ) as $key) {
      if (array_key_exists($key, $_POST)) {
        $settings->{"set" . ucfirst($key)}($_POST[$key] === 'true');
        $save = true;
      }
    }
    // Notification title
    if (array_key_exists('notificationTitle', $_POST)) {

      // Sanitize user input
      $value = $_POST['notificationTitle']
        ? stripslashes(trim(sanitize_text_field($_POST['notificationTitle']))) : null;

      // Validate user input
      $value = $value && strlen($value) > 1024 ? substr($value, 0, 1024) : $value;
      $value = $value && strlen($value) ? $value : null;

      $settings->setNotificationTitle($value);
      $save = true;
    }
    // Default target segment ID
    if (array_key_exists('defaultTargetSegmentId', $_POST)) {

      // Sanitize user input
      $value = $_POST['defaultTargetSegmentId']
        ? stripslashes(trim(sanitize_text_field($_POST['defaultTargetSegmentId']))) : null;

      // Validate user input
      $value = $value && strlen($value) > 1024 ? substr($value, 0, 1024) : $value;
      $value = $value && strlen($value) ? $value : null;

      $settings->setDefaultTargetSegmentId($value);
      $save = true;
    }
    // Additional custom post types
    if (array_key_exists('additionalCustomPostTypes', $_POST)) {

      // Sanitize user input
      $value = $_POST['additionalCustomPostTypes']
        ? stripslashes(trim(sanitize_text_field($_POST['additionalCustomPostTypes']))) : null;

      // Validate user input
      $value = $value && strlen($value) > 1024 ? substr($value, 0, 1024) : $value;
      $value = $value && strlen($value) ? $value : null;

      $settings->setAdditionalCustomPostTypes($value);
      $save = true;
    }
    // Cart reminder strategy
    if (array_key_exists('cartReminderStrategy', $_POST)) {

      // Sanitize user input
      $value = $_POST['cartReminderStrategy']
        ? trim(sanitize_text_field($_POST['cartReminderStrategy'])) : null;

      // Validate user input
      if (false !== array_search($value, WonderPushWooCommerce::cart_reminder_strategies())) {
        $settings->setCartReminderStrategy($value);
        $save = true;
      }
    }
    // Cart reminder destination
    if (array_key_exists('cartReminderDestination', $_POST)) {

      // Sanitize user input
      $value = $_POST['cartReminderDestination']
        ? trim(sanitize_text_field($_POST['cartReminderDestination'])) : null;

      // Validate user input
      if (false !== array_search($value, WonderPushWooCommerce::cart_reminder_destinations())) {
        $settings->setCartReminderDestination($value);
        $save = true;
      }
    }
    // Cart reminder message
    if (array_key_exists('cartReminderMessage', $_POST)) {

      // Sanitize user input
      $value = $_POST['cartReminderMessage']
        ? stripslashes(trim(sanitize_text_field($_POST['cartReminderMessage']))) : null;

      // Validate user input
      $value = $value && strlen($value) > 1024 ? substr($value, 0, 1024) : $value;
      $value = $value && strlen($value) ? $value : null;

      $settings->setCartReminderMessage($value);
      $save = true;
    }
    // Order confirmation notifications message
    if (array_key_exists('orderCompleteNotificationsMessage', $_POST)) {

      // Sanitize user input
      $value = $_POST['orderCompleteNotificationsMessage']
        ? stripslashes(trim(sanitize_text_field($_POST['orderCompleteNotificationsMessage']))) : null;

      // Validate user input
      $value = $value && strlen($value) > 1024 ? substr($value, 0, 1024) : $value;
      $value = $value && strlen($value) ? $value : null;

      $settings->setOrderCompleteNotificationsMessage($value);
      $save = true;
    }
    // Order confirmation notifications message
    if (array_key_exists('orderProcessingNotificationsMessage', $_POST)) {
      // Sanitize user input
      $value = $_POST['orderProcessingNotificationsMessage']
        ? stripslashes(trim(sanitize_text_field($_POST['orderProcessingNotificationsMessage']))) : null;

      // Validate user input
      $value = $value && strlen($value) > 1024 ? substr($value, 0, 1024) : $value;
      $value = $value && strlen($value) ? $value : null;

      $settings->setOrderProcessingNotificationsMessage($value);
      $save = true;
    }

    // Thank you event name
    if (array_key_exists('thankYouEventName', $_POST)) {
        // Sanitize user input
        $value = $_POST['thankYouEventName']
            ? trim(sanitize_text_field($_POST['thankYouEventName'])) : null;

        // Validate user input
        $value = $value && strlen($value) > 256 ? substr($value, 0, 256) : $value;
        $value = $value && strlen($value) ? $value : null;

        $settings->setThankYouEventName($value);
        $save = true;
    }

    // utm parameters
    $urlParameters = $settings->getUtmParameters(); // They are deprecated
    foreach (array('Source', 'Medium', 'Campaign', 'Term', 'Content') as $utm) {
      $key = "utm$utm";
      if (array_key_exists($key, $_POST)) {

        // Sanitize user input
        $value = $_POST[$key]
          ? trim(sanitize_text_field($_POST[$key])) : null;

        // Validate user input
        $value = $value && strlen($value) > 256 ? substr($value, 0, 256) : $value;
        $value = $value && strlen($value) ? $value : null;

        $urlParameters["utm_" . lcfirst($utm)] = $value;
      }
    }
    WonderPushUtils::patch_application_url_parameters($settings->getAccessToken(), $urlParameters);
    $settings->clearUtmParameters(); // They are deprecated

    // Additional init options
    if (array_key_exists('additionalInitOptionsJson', $_POST)) {
      // Sanitize user input
      $value = $_POST['additionalInitOptionsJson']
        ? stripslashes(trim(sanitize_text_field($_POST['additionalInitOptionsJson']))) : null;

      // Validate user input
      if ($value && strlen($value) > 2048) {
        self::returnError(__('Additional init options JSON cannot be larger than 2048 bytes.'), 400);
        return;
      }
      // Validate JSON
      if ($value) {
        $jsonValue = json_decode($value);
        if ($jsonValue === null) {
          self::returnError(__('Additional init options JSON must be valid JSON.'), 400);
          return;
        }
        if (!is_object($jsonValue)) {
          self::returnError(__('Additional init options JSON must be an object.'), 400);
          return;
        }
      }
      $value = $value ?: null;

      $settings->setAdditionalInitOptionsJson($value);
      $save = true;
    }
    // AMP Subscribe button label
    if (array_key_exists('ampSubscribeButtonLabel', $_POST)) {
      // Sanitize user input
      $value = $_POST['ampSubscribeButtonLabel']
        ? trim(sanitize_text_field($_POST['ampSubscribeButtonLabel'])) : null;

      // Validate user input
      $value = $value && strlen($value) > 1024 ? substr($value, 0, 1024) : $value;
      $value = $value && strlen($value) ? $value : null;

      $settings->setAmpSubscribeButtonLabel($value);
      $save = true;
    }
    // AMP Unsubscribe button label
    if (array_key_exists('ampUnsubscribeButtonLabel', $_POST)) {
      // Sanitize user input
      $value = $_POST['ampUnsubscribeButtonLabel']
        ? trim(sanitize_text_field($_POST['ampUnsubscribeButtonLabel'])) : null;

      // Validate user input
      $value = $value && strlen($value) > 1024 ? substr($value, 0, 1024) : $value;
      $value = $value && strlen($value) ? $value : null;

      $settings->setAmpUnsubscribeButtonLabel($value);
      $save = true;
    }
    // AMP Button width
    if (array_key_exists('ampButtonWidth', $_POST)) {

      // Sanitize
      $value = $_POST['ampButtonWidth']
        ? (int)trim(sanitize_text_field($_POST['ampButtonWidth'])) : null;

      // Validate
      $value = is_int($value) ? $value : null;

      $settings->setAmpButtonWidth($value);
      $save = true;
    }
    // AMP Button height
    if (array_key_exists('ampButtonHeight', $_POST)) {

      // Sanitize
      $value = $_POST['ampButtonHeight']
        ? (int)trim(sanitize_text_field($_POST['ampButtonHeight'])) : null;

      // Validate
      $value = is_int($value) ? $value : null;

      $settings->setAmpButtonHeight($value);
      $save = true;
    }
    // Delivery time seconds
    if (array_key_exists('deliveryTimeSeconds', $_POST)) {

      // Sanitize
      $value = $_POST['deliveryTimeSeconds']
        ? (int)trim(sanitize_text_field($_POST['deliveryTimeSeconds'])) : null;

      // Validate
      $value = is_int($value) ? $value : null;

      $settings->setDeliveryTimeSeconds($value);
      $save = true;
    }
    if ($save) $settings->save();
    return self::wonderpush_get_configuration();
  }
  public static function wonderpush_set_access_token() {
    self::verify_access();
    $access_token = array_key_exists('accessToken', $_POST) ?  $_POST['accessToken'] : null;
    // trim
    $access_token = $access_token ? trim(sanitize_text_field($access_token)) : null;
    // only [a-zA-Z0-9]
    $access_token = $access_token ? preg_replace('/[^a-zA-Z0-9]*/', '', $access_token) : null;

    $settings = WonderPushSettings::getSettings();
    if (!$access_token) {
      $settings->setAccessToken(null);
      $settings->save();
      self::returnResult((object)array());
      return;
    }
    // Check access token with the API
    try {
      $app = WonderPushUtils::application_from_access_token($access_token);
      if ($app === false) {
        self::returnError('Invalid access token', 400);
        return;
      }
    } catch (Exception $e) {
      $exc = $e->getMessage();
      self::returnError($exc ?: '', 500);
      return;
    }
    $settings->setAccessToken($access_token);
    $settings->save();
    return self::wonderpush_get_configuration();
  }

  public static function wonderpush_get_post_metadata() {
    self::verify_access(WonderPushAPI::EDITOR_ACCESS);
    $post_id = intval($_GET['post_id']);

    if(is_null($post_id)){
      self::returnError('Provide post_id query paramter', 400);
      return;
   }

    $info = get_post_meta($post_id, WonderPushAdmin::POST_META_INFO_MESSAGE);
    if(is_array($info)){
      $info = $info ? $info[0] : null;
    }

    $error = get_post_meta($post_id, WonderPushAdmin::POST_META_ERROR_MESSAGE);
    if(is_array($error)){
      $error = $error ? $error[0] : null;
    }

    // reset meta
    delete_post_meta($post_id, WonderPushAdmin::POST_META_INFO_MESSAGE);
    delete_post_meta($post_id, WonderPushAdmin::POST_META_ERROR_MESSAGE);

    self::returnResult((object)array('error_message' => $error, 'info_message' => $info));
  }
}
