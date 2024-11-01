<?php
if (!defined( 'ABSPATH' )) { http_response_code(403); exit(); }


// FIXME: this class is a draft of Asgaros forum integration.
// It is not included so far and hasn't been thouroughly tested.
// It sends notifications to @ALL upon new topic or reply with hard coded titles.
// To make it production ready:
// 1. Include this file from the main plugin file and call WonderPushAsgarosForum::init()
// 2. Offer settings to:
//   - activate / deactivate feature
//   - customize segment
//   - customize titles
// 3. Detect asgaros presence and adapt admin UI accordingly

class WonderPushAsgarosForum {

  static function init() {
    add_action('asgarosforum_after_add_post_submit', array(__CLASS__, 'after_add_post_submit'), 10, 6);
    add_action('asgarosforum_after_add_topic_submit', array(__CLASS__, 'after_add_topic_submit'), 10, 6);
  }

  public static function after_add_post_submit($post, $topic, $subject, $content, $link, $author) {
    $title = 'New answer';
    self::after_submit($title, $subject, $link);
  }

  public static function after_add_topic_submit($post, $topic, $subject, $content, $link, $author) {
    $title = 'New topic';
    self::after_submit($title, $subject, $link);
  }

  static function after_submit($title, $subject, $link) {
    $settings = WonderPushSettings::getSettings();
    $access_token = $settings->getAccessToken();
    if (!$access_token) return false;
    $client = WonderPushUtils::management_api_client($access_token);
    $client->deliveries()->create(array(
      'notification' => array(
        'alert' => array(
          'title' => $title,
          'text' => $subject,
          'targetUrl' => $link,
        ),
      ),
      'targetSegmentIds' => '@ALL',
    ));
  }

}
