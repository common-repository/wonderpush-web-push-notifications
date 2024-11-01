<?php
if (!defined( 'ABSPATH' )) { http_response_code(403); exit(); }

class WonderPushPublic {
  const INSTALLATION_ID_COOKIE_NAME = 'WonderPushInstallationId';
  static function init() {
    add_action('wp_head', array(__CLASS__, 'wonderpush_snippet'), 10);
    add_filter( 'the_content', array(__CLASS__, 'the_content'), 10);
    if (WonderPushUtils::get_woocommerce()
        && WonderPushSettings::getSettings()->getEnableCartReminder()) {
      add_action('wp_head', array(__CLASS__, 'wonderpush_cookie'), 10);
    }
    // AMP support through official plugin (https://amp-wp.org/):
    // https://wordpress.org/plugins/amp/
    add_filter('amp_post_template_file', array(__CLASS__, 'amp_post_template_file'), 10, 3);
    add_filter('amp_post_article_header_meta', array(__CLASS__, 'amp_post_article_header_meta'), 10, 3);
    add_filter('amp_post_article_footer_meta', array(__CLASS__, 'amp_post_article_footer_meta'), 10, 3);
//    add_filter('amp_post_template_data', array(__CLASS__, 'amp_post_template_data'), 10, 3 );

    // AMP support through alternative plugin (AMP for WP, https://ampforwp.com/):
    // https://wordpress.org/plugins/accelerated-mobile-pages/
    add_filter('ampforwp_after_header', array(__CLASS__, 'ampforwp_after_header'), 10, 3);
    add_filter('amp_post_template_above_footer', array(__CLASS__, 'amp_post_template_above_footer'), 10, 3);

    // AMP support, common to both plugins
    add_filter('amp_post_template_head', array(__CLASS__, 'amp_post_template_head'), 10, 3);
    add_action('amp_post_template_css', array(__CLASS__, 'amp_post_template_css'));
  }

  public static function amp_post_template_head() {
      if (WonderPushSettings::getSettings()->getDisableAmpTopSubscribeButton()
        && WonderPushSettings::getSettings()->getDisableAmpBottomSubscribeButton()) {
          return;
      }
      ?><script type='text/javascript' src='https://cdn.ampproject.org/v0/amp-web-push-0.1.js' async custom-element="amp-web-push"></script><?php
  }
  public static function amp_post_template_data($data) {
      if (!isset($data['amp_component_scripts']) || !is_array($data['amp_component_scripts'])) {
          $data['amp_component_scripts'] = array();
      }
      $data['amp_component_scripts']['amp-web-push'] = 'https://cdn.ampproject.org/v0/amp-web-push-0.1.js';
      return $data;
  }
  public static function amp_post_article_header_meta($meta) {
      if (is_array($meta)) {
          if (!WonderPushSettings::getSettings()->getDisableAmpTopSubscribeButton()
            || !WonderPushSettings::getSettings()->getDisableAmpBottomSubscribeButton()) {
            $meta []= 'wonderpush-amp-web-push';
          }
          if (!WonderPushSettings::getSettings()->getDisableAmpTopSubscribeButton()) {
              $meta []= 'wonderpush-amp-web-push-widget';
          }
      }
      return $meta;
  }
  public static function amp_post_article_footer_meta($meta) {
      if (is_array($meta)
        && !WonderPushSettings::getSettings()->getDisableAmpBottomSubscribeButton()) {
          $meta []= 'wonderpush-amp-web-push-widget';
      }
      return $meta;
  }
  public static function amp_post_template_file($file, $type, $post) {
      if (basename($file) === 'wonderpush-amp-web-push.php') {
          return WONDERPUSH_PLUGIN_PATH . '/amp/amp-web-push.php';
      }
      if (basename($file) === 'wonderpush-amp-web-push-widget.php') {
        return WONDERPUSH_PLUGIN_PATH . '/amp/amp-web-push-widget.php';
      }
      return $file;
  }
  public static function amp_post_template_css() {
      include WONDERPUSH_PLUGIN_PATH . '/amp/style.php';
  }
  public static function ampforwp_after_header() {
      if (!WonderPushSettings::getSettings()->getDisableAmpTopSubscribeButton()
          || !WonderPushSettings::getSettings()->getDisableAmpBottomSubscribeButton()) {
          include WONDERPUSH_PLUGIN_PATH . '/amp/amp-web-push.php';
      }
      if (!WonderPushSettings::getSettings()->getDisableAmpTopSubscribeButton()) {
          include WONDERPUSH_PLUGIN_PATH . '/amp/amp-web-push-widget.php';
      }
  }
  public static function amp_post_template_above_footer() {
      if (WonderPushSettings::getSettings()->getDisableAmpBottomSubscribeButton()) {
          return;
      }
      include WONDERPUSH_PLUGIN_PATH . '/amp/amp-web-push-widget.php';
  }
  public static function the_content($content) {
    // Only single post, attachment, page, custom post types
    if (
      is_main_query()
      && in_the_loop()
      && is_singular() // post, attachment, page, custom post types
    ) {
      if (WonderPushUtils::is_amp_request()) {
        $disableTop = WonderPushSettings::getSettings()->getDisableAmpTopSubscribeButton();
        $disableBottom = WonderPushSettings::getSettings()->getDisableAmpBottomSubscribeButton();
        if ($disableBottom && $disableTop) return $content;
        ob_start();
        include WONDERPUSH_PLUGIN_PATH . '/amp/amp-web-push-widget.php';
        $file_content = ob_get_clean();
        return ($disableTop ? '' : $file_content) . $content . ($disableBottom ? '' : $file_content);
      }
    }

    return $content;
  }
  public static function wonderpush_snippet() {
    if (!WonderPushUtils::is_wonderpush_installed()) return;
    if (WonderPushUtils::is_amp_request()) {
      echo '<style>';
      include WONDERPUSH_PLUGIN_PATH . '/amp/style.php';
      echo '</style>';
      include WONDERPUSH_PLUGIN_PATH . '/amp/amp-web-push.php';
      return;
    }
    $settings = WonderPushSettings::getSettings();
    $access_token = $settings->getAccessToken();
    try {
      $app = WonderPushUtils::application_from_access_token($access_token);
    } catch (Exception $e) {
      return;
    }
    if (!$app) return;
    $web_key = $app->getWebKey();
    $by_wonderpush_domain = $app->getWebSdkInitOptions() ? $app->getWebSdkInitOptions()->getByWonderPushDomain() : null;
    if (!$web_key) return;
    $user_data_required = (!$settings->getDisableUserIdInSnippet()) || $settings->getEnableUserSegmentation();
    $user = $user_data_required ? wp_get_current_user() : null;
    $user_id = $user ? ($settings->getEmailAsUserId() ? $user->get('user_email') : $user->get('ID')) : null;
    $additional_init_options_json = $settings->getAdditionalInitOptionsJson();
    ?>
    <script src="https://cdn.by.wonderpush.com/sdk/1.1/wonderpush-loader.min.js" async></script>
    <script>
      window.WonderPush = window.WonderPush || [];
      {
        var initOptions = {
          webKey: "<?php echo $web_key?>",
          userId: <?php echo (($user_id && !$settings->getDisableUserIdInSnippet()) ? json_encode($user_id) : 'null') ?>,
          <?php echo $by_wonderpush_domain ? '' : 'customDomain: "' . WONDERPUSH_PLUGIN_URL . 'assets/sdk/",' . "\n" ?>
          <?php echo $by_wonderpush_domain ? '' : 'frameUrl: "wp.html",' . "\n" ?>
        };
        <?php echo $additional_init_options_json ? 'initOptions = Object.assign({}, initOptions, '.$additional_init_options_json.");\n" : ''  ?>
        WonderPush.push(["init", initOptions]);
      }
      <?php
      // Set user properties
      if ($user && $settings->getEnableUserSegmentation()):
      ?>WonderPush.push(['putProperties', {
        <?php
          foreach (WonderPushUtils::user_segmentation_keys() as $key) {
          $value = $user->get($key);
          if (!isset($value) || !$value) continue;
          ?>string_<?php echo $key ?>:<?php echo json_encode($value) ?>,<?php
        }
        ?>}]);<?php
      endif;
      ?>
    </script>
    <?php
  }
  public static function wonderpush_cookie() {
    if (!WonderPushUtils::is_wonderpush_installed()) return;
    ?>
    <script>
      (function() {
        window.addEventListener('WonderPushEvent', function(event) {
          if (event.detail.name === 'session') {
            window.WonderPush.push(function() {
              window.WonderPush.getInstallationId()
                .then(function(installationId) {
                  document.cookie = '<?php echo self::INSTALLATION_ID_COOKIE_NAME ?>=' + encodeURIComponent(installationId || '') + '; expires=' + new Date(new Date().getTime() + 86400000).toGMTString() + '; path=/';
                });
            });
          }
        });
      })();
    </script>
    <?php
  }
}
