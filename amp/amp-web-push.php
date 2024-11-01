<?php
if (!defined( 'ABSPATH' )) { http_response_code(403); exit(); }


$web_key = null;
if (WonderPushUtils::is_wonderpush_installed()) {
  $settings = WonderPushSettings::getSettings();
  $access_token = $settings->getAccessToken();
  try {
    $app = WonderPushUtils::application_from_access_token($access_token);
    if ($app) $web_key = $app->getWebKey();
  } catch (Exception $e) {
  }
}

if ($web_key) {
  ?>
  <amp-web-push
    id="amp-web-push"
    layout="nodisplay"
    helper-iframe-url="<?php echo WONDERPUSH_PLUGIN_URL ?>assets/sdk/wp.html?wonderpushWebKey=<?php echo $web_key ?>&amp=frame"
    permission-dialog-url="<?php echo WONDERPUSH_PLUGIN_URL ?>assets/sdk/wp.html?wonderpushWebKey=<?php echo $web_key ?>&amp=dialog"
    service-worker-url="<?php echo WONDERPUSH_PLUGIN_URL ?>assets/sdk/wonderpush-worker-loader.min.js?webKey=<?php echo $web_key ?>"
  ></amp-web-push>

  <?php
}
