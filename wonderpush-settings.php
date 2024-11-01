<?php
if (!defined( 'ABSPATH' )) { http_response_code(403); exit(); }

class WonderPushSettings {
  /** @var WonderPushSettings */
  static $instance;
  static $defaults = array(
  );
  /** @var array */
  private $settings;

  private function __construct($settings) {
    if (is_array($settings)) $this->settings = $settings;
    else $this->settings = array();
  }

  private function get($name) {
    if (isset($this->settings[$name])) return $this->settings[$name];
    return array_key_exists($name, self::$defaults) ? self::$defaults[$name] : null;
  }

  private function has($name) {
    return isset($this->settings[$name]);
  }

  private function set($name, $value) {
    if ($value === null) unset($this->settings[$name]);
    else $this->settings[$name] = $value;
    return $this;
  }

  public function getAccessToken() {
    return $this->get('accessToken');
  }

  public function setAccessToken($value) {
    return $this->set('accessToken', $value);
  }

  public function getDeliveryTimeSeconds() {
    return $this->get('deliveryTimeSeconds') ?: 0;
  }

  public function setDeliveryTimeSeconds($value) {
    return $this->set('deliveryTimeSeconds', is_int($value) ? $value : 0);
  }

  public function getBypassWordPressHttpClient() {
    $storedValue = $this->get('bypassWordPressHttpClient');
    if ($storedValue === null) return true; // Bypass by default
    return $storedValue ? true : false;
  }

  public function setBypassWordPressHttpClient($value) {
    return $this->set('bypassWordPressHttpClient', (bool)$value);
  }

  public function getDefaultTargetSegmentId() {
    return $this->get('defaultTargetSegmentId');
  }
  public function setDefaultTargetSegmentId($value) {
    return $this->set('defaultTargetSegmentId', $value);
  }
  public function getAdditionalCustomPostTypes() {
    return $this->get('additionalCustomPostTypes');
  }
  public function setAdditionalCustomPostTypes($value) {
    return $this->set('additionalCustomPostTypes', $value);
  }
  public function getDisableUserIdInSnippet() {
    return $this->get('disableUserIdInSnippet') ? true : false;
  }

  public function getEmailAsUserId() {
    return $this->get('emailAsUserId') ? true : false;
  }

  public function setDisableUserIdInSnippet($value) {
    return $this->set('disableUserIdInSnippet', $value ? true : false);
  }

  public function setEmailAsUserId($value) {
    return $this->set('emailAsUserId', $value ? true : false);
  }

  public function getDisableSnippet() {
    return $this->get('disableSnippet') ? true : false;
  }

  public function setDisableSnippet($value) {
    return $this->set('disableSnippet', $value ? true : false);
  }

  public function getDisableSendOnPublish() {
    return $this->get('disableSendOnPublish') ? true : false;
  }

  public function setDisableSendOnPublish($value) {
    return $this->set('disableSendOnPublish', $value ? true : false);
  }

  public function getDisableSendByDefaultOnPublish() {
    return $this->get('disableSendByDefaultOnPublish') ? true : false;
  }

  public function getSendOnThirdPartyPublish() {
    return $this->get('sendOnThirdPartyPublish') ? true : false;
  }

  public function setDisableSendByDefaultOnPublish($value) {
    return $this->set('disableSendByDefaultOnPublish', $value ? true : false);
  }

  public function setSendOnThirdPartyPublish($value) {
    return $this->set('sendOnThirdPartyPublish', $value ? true : false);
  }

  public function getDisableFeedbackOnPublish() {
    return $this->get('disableFeedbackOnPublish') ? true : false;
  }

  public function setDisableFeedbackOnPublish($value) {
    return $this->set('disableFeedbackOnPublish', $value ? true : false);
  }

  public function getDisableUsePostImageForNotification() {
    // Migration: people who haven't set disableUsePostImageForNotification yet
    // and had disabled images entirely will get images disabled entirely with the new setting
    // until they change it.
    if (!$this->has('disableUsePostImageForNotification')
      && $this->getDisableUsePostImageForIcon()
      && $this->getDisableUsePostImageForLargeImage())  {
      return true;
    }
    return $this->get('disableUsePostImageForNotification') ? true : false;
  }

  public function setDisableUsePostImageForNotification($value) {
    return $this->set('disableUsePostImageForNotification', $value ? true : false);
  }

  public function getPreferLargeImageForNotification() {
    return $this->get('preferLargeImageForNotification') ? true : false;
  }

  public function setPreferLargeImageForNotification($value) {
    return $this->set('preferLargeImageForNotification', $value ? true : false);
  }

  /**
   * @deprecated
   * @return bool
   */
  public function getDisableUsePostImageForIcon() {
    return $this->get('disableUsePostImageForIcon') ? true : false;
  }

  /**
   * @deprecated
   * @return bool
   */
  public function setDisableUsePostImageForIcon($value) {
    return $this->set('disableUsePostImageForIcon', $value ? true : false);
  }

  /**
   * @deprecated
   * @return bool
   */
  public function getDisableUsePostImageForLargeImage() {
    return $this->get('disableUsePostImageForLargeImage') ? true : false;
  }

  /**
   * @deprecated
   * @return bool
   */
  public function setDisableUsePostImageForLargeImage($value) {
    return $this->set('disableUsePostImageForLargeImage', $value ? true : false);
  }

  public function getNotificationTitle() {
    return $this->get('notificationTitle');
  }

  public function setNotificationTitle($value) {
    return $this->set('notificationTitle', $value);
  }

  public function getEnableOrderCompleteNotifications() {
    return $this->get('enableOrderCompleteNotifications') ? true : false;
  }

  public function setEnableOrderCompleteNotifications($value) {
    return $this->set('enableOrderCompleteNotifications', $value ? true : false);
  }

  public function getOrderCompleteNotificationsMessage() {
    return $this->get('orderCompleteNotificationsMessage');
  }

  public function setOrderCompleteNotificationsMessage($value) {
    $this->set('orderCompleteNotificationsMessage', $value);
    return $this;
  }

  public function getEnableOrderProcessingNotifications() {
    return $this->get('enableOrderProcessingNotifications') ? true : false;
  }

  public function setEnableOrderProcessingNotifications($value) {
    return $this->set('enableOrderProcessingNotifications', $value ? true : false);
  }

  public function getOrderProcessingNotificationsMessage() {
    return $this->get('orderProcessingNotificationsMessage');
  }

  public function setOrderProcessingNotificationsMessage($value) {
    $this->set('orderProcessingNotificationsMessage', $value);
    return $this;
  }

  public function getEnableCartReminder() {
    return $this->get('enableCartReminder') ? true : false;
  }

  public function setEnableCartReminder($value) {
    return $this->set('enableCartReminder', $value ? true : false);
  }

  public function getEnableUserSegmentation() {
    return $this->get('enableUserSegmentation') ? true : false;
  }

  public function setEnableUserSegmentation($value) {
    return $this->set('enableUserSegmentation', $value ? true : false);
  }

  public function getCartReminderStrategy() {
    $value = $this->get('cartReminderStrategy');
    return $value ? $value : WonderPushWooCommerce::CART_REMINDER_STRATEGY_LATEST;
  }

  public function setCartReminderStrategy($value) {
    return array_search($value, WonderPushWooCommerce::cart_reminder_strategies()) !== false
      ? $this->set('cartReminderStrategy', $value) : $this;
  }

  public function getCartReminderDestination() {
    $value = $this->get('cartReminderDestination');
    return $value ? $value : WonderPushWooCommerce::CART_REMINDER_DESTINATION_HOMEPAGE;
  }

  public function setCartReminderDestination($value) {
    return array_search($value, WonderPushWooCommerce::cart_reminder_destinations()) !== false
      ? $this->set('cartReminderDestination', $value) : $this;
  }

  public function getCartReminderMessage() {
    return $this->get('cartReminderMessage');
  }

  public function setCartReminderMessage($value) {
    $this->set('cartReminderMessage', $value);
    return $this;
  }

  public function getDisableCartReminderImage() {
    return $this->get('disableCartReminderImage') ? true : false;
  }

  public function setDisableCartReminderImage($value) {
    return $this->set('disableCartReminderImage', $value ? true : false);
  }

  public function getDisableThankYouEvent() {
      return $this->get('disableThankYouEvent') ? true : false;
  }

  public function setDisableThankYouEvent($value) {
      return $this->set('disableThankYouEvent', $value ? true : false);
  }

  public function getThankYouEventName() {
      return $this->get('thankYouEventName');
  }

  public function setThankYouEventName($value) {
      return $this->set('thankYouEventName', $value);
  }

  public function getDisableAmpUnsubscribe() {
    return $this->get('disableAmpUnsubscribe') ? true : false;
  }

  public function setDisableAmpUnsubscribe($value) {
    return $this->set('disableAmpUnsubscribe', $value ? true : false);
  }

  public function getAmpSubscribeButtonLabel() {
    return $this->get('ampSubscribeButtonLabel');
  }

  public function setAmpSubscribeButtonLabel($value) {
    return $this->set('ampSubscribeButtonLabel', $value ? $value : null);
  }

  public function getAmpUnsubscribeButtonLabel() {
    return $this->get('ampUnsubscribeButtonLabel');
  }

  public function setAmpUnsubscribeButtonLabel($value) {
    return $this->set('ampUnsubscribeButtonLabel', $value ? $value : null);
  }

  public function getDisableAmpTopSubscribeButton() {
    return $this->get('disableAmpTopSubscribeButton') ? true : false;
  }

  public function setDisableAmpTopSubscribeButton($value) {
    return $this->set('disableAmpTopSubscribeButton', $value ? true : false);
  }

  public function getDisableAmpBottomSubscribeButton() {
    return $this->get('disableAmpBottomSubscribeButton') ? true : false;
  }

  public function setDisableAmpBottomSubscribeButton($value) {
    return $this->set('disableAmpBottomSubscribeButton', $value ? true : false);
  }

  public function getAmpButtonWidth() {
    return $this->get('ampButtonWidth');
  }

  public function setAmpButtonWidth($value) {
    return $this->set('ampButtonWidth', is_int($value) ? $value : null);
  }

  public function getAmpButtonHeight() {
    return $this->get('ampButtonHeight');
  }

  public function setAmpButtonHeight($value) {
    return $this->set('ampButtonHeight', is_int($value) ? $value : null);
  }

  /**
   * @deprecated Use Application::getUrlParameters instead
   */
  public function getUtmParameters() {
    $utm_params = array();
    foreach (WonderPushUtils::utm_parameters() as $utm_parameter) {
      $parameter = str_replace('utm_', '', $utm_parameter);
      $parameter = "utm" . ucfirst($parameter);
      $utm_params[$utm_parameter] = $this->get($parameter);
    }
    return $utm_params;
  }

  public function clearUtmParameters() {
    foreach (WonderPushUtils::utm_parameters() as $utm_parameter) {
      $parameter = str_replace('utm_', '', $utm_parameter);
      $parameter = "utm" . ucfirst($parameter);
      $this->set($parameter, null);
    }
  }

  public function getAdditionalInitOptionsJson() {
    return $this->get('additionalInitOptionsJson');
  }

  public function setAdditionalInitOptionsJson($value) {
    $this->set('additionalInitOptionsJson', $value);
    return $this;
  }

  public function save() {
    update_option('WonderPushSettings', $this->settings);
  }

  /**
   * @return WonderPushSettings
   */
  public static function getSettings() {
    if (!self::$instance) self::$instance = new WonderPushSettings(get_option('WonderPushSettings'));
    return self::$instance;
  }
}
