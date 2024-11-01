<?php
if (!defined( 'ABSPATH' )) { http_response_code(403); exit(); }

class WonderPushAdmin {
  const RESOURCES_VERSION = '1.11.5';
  const MENU_SLUG = 'wonderpush';
  const META_BOX_ID = 'wonderpush_meta_box';
  const SAVE_POST_NONCE_ACTION = 'wonderpush_save_post_nonce_action';
  const SAVE_POST_NONCE_KEY = 'wonderpush_save_post_nonce_key';
  const POST_META_ERROR_MESSAGE = 'wonderpush_error_message';
  const POST_META_INFO_MESSAGE = 'wonderpush_info_message';
  const POST_META_LAST_NOTIFICATION_CONTENT = 'wonderpush_last_notification_content';
  const POST_META_LAST_NOTIFICATION_TIMESTAMP = 'wonderpush_last_notification_timestamp';
  const API_RATE_LIMIT_SECONDS = 3;
  const DEDUPLICATION_SECONDS = 60;
  const MAX_NOTIFICATION_DELAY_HOURS = 24;
  const METADATA_MULTIVALUE_SEPARATOR = '<+>';

  static function init() {
    if (WonderPushUtils::can_modify_settings()) {
      add_action('admin_menu', array(__CLASS__, 'add_admin_page'));
    }
    if (WonderPushUtils::can_send_notifications()) {
      add_action('admin_init', array( __CLASS__, 'add_post_options' ));
    }

    add_action( 'save_post', array(__CLASS__, 'on_save_post'), 1, 3 );
    add_action( 'transition_post_status', array( __CLASS__, 'on_transition_post_status' ), 10, 3 );

    add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_wide_scripts_and_styles' ) );
  }

  public static function add_post_options() {
    $settings = WonderPushSettings::getSettings();
    if ($settings->getDisableSendOnPublish()) return;

    // Add meta box for the "post" post type (default)
    add_meta_box(self::META_BOX_ID,
      'WonderPush Push Notifications',
      array( __CLASS__, 'add_post_html' ),
      'post',
      'normal',
      'high');

    // Add meta box for all other post types that are public but not built in to WordPress
    $post_types = get_post_types(array('public'   => true, '_builtin' => false), 'names', 'and' );
    foreach ( $post_types  as $post_type ) {
      add_meta_box(
        self::META_BOX_ID,
        'WonderPush Push Notifications',
        array( __CLASS__, 'add_post_html' ),
        $post_type,
        'side',
        'high'
      );
    }
  }
  public static function add_post_html($post) {
    $post_type = $post->post_type;
    $settings = WonderPushSettings::getSettings();
    $access_token = $settings->getAccessToken();
    try {
      $app = $access_token ? WonderPushUtils::application_from_access_token($access_token) : null;
    } catch (Exception $e) {
      $app = null;
      WonderPushUtils::log('Could not get application: ' . $e->getMessage());
    }

    // Add an nonce field so we can check for it later.
    wp_nonce_field(self::SAVE_POST_NONCE_ACTION, self::SAVE_POST_NONCE_KEY, true);

    // Our plugin config setting "Automatically send a push notification when I publish a post from the WordPress editor"
    $disable_send_by_default = $settings->getDisableSendByDefaultOnPublish();

    /* This is a scheduled post and the user checked "Send a notification on post publish/update". */
    $send_notification_checked = (get_post_meta($post->ID, 'wonderpush_send_notification', true) == true);
    // User explicitely unchecked notification and saved post
    $send_notification_unchecked = get_post_meta($post->ID, 'wonderpush_send_notification', true) === '0';
    $send_notification_delay_seconds = get_post_meta($post->ID, 'wonderpush_send_notification_delay_seconds', true);
    if ($send_notification_delay_seconds === null || $send_notification_delay_seconds === '') {
        $send_notification_delay_seconds = $settings->getDeliveryTimeSeconds();
    }
    $send_notification_delay_seconds = (int)$send_notification_delay_seconds;

    // Segment
    $default_target_segment_id = $settings->getDefaultTargetSegmentId() ?: '';
    // New segment IDs meta
    $target_segment_ids = get_post_meta($post->ID, 'wonderpush_target_segment_ids', true) ?: '';
    // Legacy meta
    $target_segment_id = get_post_meta($post->ID, 'wonderpush_target_segment_id', true) ?: '';
    if ($target_segment_id && !$target_segment_ids) {
      $target_segment_ids = array($target_segment_id);
    } else {
      $target_segment_ids = array_filter(explode(self::METADATA_MULTIVALUE_SEPARATOR, $target_segment_ids ?: $default_target_segment_id));
    }
    $target_tags = get_post_meta($post->ID, 'wonderpush_target_tags', true) ?: '';
    $target_tags = array_filter(explode(self::METADATA_MULTIVALUE_SEPARATOR, $target_tags));
    // All segments with a name
    try {
      $all_segments = $access_token ? WonderPushUtils::list_segments($access_token) : null;
      $all_segments = is_array($all_segments) ? array_filter($all_segments, function ($elt) { return (bool)$elt->getName(); })
        : null;
    } catch (Exception $e) {
      $all_segments = array();
      WonderPushUtils::log('Could not get segment list: ' . $e->getMessage());
    }

    try {
      $all_tags = $access_token
        ? WonderPushUtils::list_tags($access_token)
        : array();

    } catch (Exception $e) {
      $all_tags = array();
      WonderPushUtils::log('Could not get tags: ' . $e->getMessage());
    }

    // UTM params
    $utm_params = array();
    $url_parameters = $app ? (array)$app->getUrlParameters() : array();
    foreach (WonderPushUtils::utm_parameters() as $utm_parameter) {
      $value = get_post_meta($post->ID, "wonderpush_$utm_parameter", true);
      $value = $value ?: (array_key_exists($utm_parameter, $url_parameters) ? $url_parameters[$utm_parameter] : null);
      if ($value) $utm_params[$utm_parameter] = $value;
    }
    $hours = array();
    for ($i = 1; $i <= self::MAX_NOTIFICATION_DELAY_HOURS; $i++) {
        $hours []= $i;
    }
    $minutes = array(5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55);

    // We check the checkbox if: setting is enabled on Config page, post type is ONLY "post", and the post has not been published (new posts are status "auto-draft")
    $send_notification = (!$send_notification_unchecked
        && !$disable_send_by_default
        && $post->post_type == "post"
        &&  in_array($post->post_status, array("future", "draft", "auto-draft", "pending")))
      || $send_notification_checked;

    $notification_already_sent = !!(get_post_meta($post->ID, self::POST_META_LAST_NOTIFICATION_CONTENT, true));
    $wonderpush_audience = 'all';
    if (count($target_tags)) {
      $wonderpush_audience = 'tags';
    } else if (count($target_segment_ids) > 1) {
      $wonderpush_audience = 'segments';
    } else if (count($target_segment_ids) > 0 && $target_segment_ids[0] !== '@ALL') {
      $wonderpush_audience = 'segments';
    }
    ?>
      <div id="wonderpush_editor">
        <input type="hidden" name="wonderpush_meta_box_present" value="true"/>
        <?php if ($notification_already_sent) : ?>
          <input type="hidden" name="wonderpush_notification_already_sent" value="true"/>
        <?php endif; ?>
        <label>
          <input type="checkbox" name="send_wonderpush_notification" value="true" <?php if ($send_notification) {
            echo "checked";
          } ?> />
          <strong>
            <?php if ($post->post_status == "publish") {
              echo "Send notification on " . $post_type . " update";
            } else {
              echo "Send notification on " . $post_type . " publish";
            }
            ?>
          </strong>
        </label>
        <div class="wonderpush_audience">
          <h3 style="margin-bottom: 3px;">Target audience</h3>
          <label>
            <input
              type="radio"
              name="wonderpush_audience"
              value="all"
              <?php echo $wonderpush_audience === 'all' ? 'checked' : '' ?>
            />
            Everybody
          </label>
          <div class="wonderpush_all">
            <div class="wonderpush_target">
              <input type="hidden" name="wonderpush_target_segment_ids[]" value="@ALL" />
            </div>
          </div>
          <label>
            <input
              type="radio"
              name="wonderpush_audience"
              value="segments"
              <?php echo $wonderpush_audience === 'segments' ? 'checked' : '' ?>
            />
            Users in segment(s)
          </label>
          <div class="wonderpush_segments">
            <div class="wonderpush_target">
              <label for="wonderpush_target_segment_ids">We'll notify users that match at least one of these segments:</label>
              <select name="wonderpush_target_segment_ids[]" multiple
                      id="wonderpush_target_segment_ids"
                      class="wonderpush_target_segment_id wonderpush_select2">
                <option value="@ALL">Everyone</option>
                <?php
                foreach ($all_segments as $segment) {
                  ?>
                  <option
                  <?php echo array_search($segment->getId(), $target_segment_ids) !== false ? 'selected="selected"' : '' ?>
                  value="<?php echo $segment->getId() ?>"><?php echo $segment->getName() ?></option><?php
                }
                ?>
              </select>
            </div>
          </div>
          <label>
            <input
              type="radio"
              name="wonderpush_audience"
              value="tags"
              <?php echo $wonderpush_audience === 'tags' ? 'checked' : '' ?>
            />
            Users with tag(s)
          </label>
          <div class="wonderpush_tags">
            <div class="wonderpush_target">
              <label for="wonderpush_target_tags">We'll notify users that match at least one of these tags:</label>
              <select name="wonderpush_target_tags[]" multiple
                      id="wonderpush_target_tags"
                      class="wonderpush_target_tags wonderpush_select2">
                <?php
                foreach ($all_tags as $tag) {
                  ?>
                  <option
                  <?php echo array_search($tag, $target_tags) !== false ? 'selected="selected"' : '' ?>
                  value="<?php echo $tag ?>"><?php echo $tag ?></option><?php
                }
                ?>
              </select>
            </div>
          </div>
        </div>
        <?php if ($all_segments) : ?>
        <?php endif; ?>
        <h3 style="margin-bottom: 3px;">Send later</h3>
        <small>Delay the notification after this post gets published:</small>
        <br/>
        <select name="wonderpush_send_notification_delay_seconds">
          <option value="0">No delay</option>
          <?php foreach ($minutes as $minute): ?>
            <option
              <?php echo (($minute * 60) === $send_notification_delay_seconds ? 'selected="selected"' : '') ?>
              value="<?php echo $minute * 60 ?>">
              <?php echo $minute ?> minutes
            </option>
          <?php endforeach; ?>
          <?php foreach ($hours as $hour): ?>
            <option
            <?php echo (($hour * 3600) === $send_notification_delay_seconds ? 'selected="selected"' : '') ?>
            value="<?php echo $hour * 3600 ?>">
              <?php echo $hour ?> hour<?php echo ($hour > 1 ? 's' : '') ?>
            </option>
          <?php endforeach; ?>
        </select>
        <h3 style="margin-bottom: 3px;">Google campaign parameters</h3>
        <small>Campaign params help you see push notification traffic in Google Analytics. Learn <a target="_blank" href="https://support.google.com/analytics/answer/1033863#parameters">more</a>.</small>
        <div class="wonderpush_utm_parameters">
          <?php foreach (WonderPushUtils::utm_parameters() as $utm_parameter): ?>
            <?php $id = 'wonderpush_'. $utm_parameter; ?>
            <div class="wonderpush_utm">
              <label for="<?php echo $id; ?>"><?php echo $utm_parameter; ?>:</label>
              <input type="text"
                     id="<?php echo $id; ?>" name="<?php echo $id; ?>"
                     value="<?php echo esc_attr(array_key_exists($utm_parameter, $utm_params) ? $utm_params[$utm_parameter] : '') ?>"/>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php
  }
  public static function on_save_post($post_id) {
    // Check nonce
    if (!isset( $_POST[self::SAVE_POST_NONCE_KEY] ) ) {
      return $post_id;
    }

    $nonce = $_POST[self::SAVE_POST_NONCE_KEY];

    // Verify nonce
    if (!wp_verify_nonce($nonce, self::SAVE_POST_NONCE_ACTION)) {
      return $post_id;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      return $post_id;
    }

    $wonderpush_meta_box_present = array_key_exists('wonderpush_meta_box_present', $_POST);
    update_post_meta($post_id, 'wonderpush_meta_box_present', $wonderpush_meta_box_present ? true : false);

    if (array_key_exists('send_wonderpush_notification', $_POST)) {
      $notification_already_sent = !!(get_post_meta($post_id, self::POST_META_LAST_NOTIFICATION_CONTENT, true));
      if (
        !$notification_already_sent // Notification wasn't sent
        || ($notification_already_sent && array_key_exists('wonderpush_notification_already_sent', $_POST)) // Notification was sent and the UI reflected this
      ) {
        update_post_meta($post_id, 'wonderpush_send_notification', true);
      }
    } else {
      // If meta box present, user explicitely unchecked
      $wonderpush_send_notification = $wonderpush_meta_box_present ? '0' : false;
      update_post_meta($post_id, 'wonderpush_send_notification', $wonderpush_send_notification);
    }

    $settings = WonderPushSettings::getSettings();
    if (array_key_exists('wonderpush_send_notification_delay_seconds', $_POST)) {
      $meta_value = trim(sanitize_text_field($_POST['wonderpush_send_notification_delay_seconds']));
      if (WonderPushUtils::is_int_string($meta_value) && (int)$meta_value <= self::MAX_NOTIFICATION_DELAY_HOURS * 3600) {
        update_post_meta($post_id, 'wonderpush_send_notification_delay_seconds', (int)$meta_value);
      }
    } else {
      update_post_meta($post_id, 'wonderpush_send_notification_delay_seconds', null);
    }

    if (array_key_exists('wonderpush_target_tags', $_POST)) {
      $meta_values = array_filter(array_map(function($elt) {
        return trim(sanitize_text_field($elt));
      }, $_POST['wonderpush_target_tags']));
      update_post_meta($post_id, 'wonderpush_target_tags', count($meta_values) ? implode(self::METADATA_MULTIVALUE_SEPARATOR, $meta_values) : null);
    } else {
      update_post_meta($post_id, 'wonderpush_target_tags', null);
    }

    if (array_key_exists('wonderpush_target_segment_ids', $_POST)) {
      $meta_values = array_filter(array_map(function ($elt) {
        return trim(sanitize_text_field($elt));
      }, $_POST['wonderpush_target_segment_ids']));
      update_post_meta($post_id, 'wonderpush_target_segment_ids', count($meta_values) ? implode(self::METADATA_MULTIVALUE_SEPARATOR, $meta_values) : null);
    } else {
      update_post_meta($post_id, 'wonderpush_target_segment_ids', null);
    }

    foreach (WonderPushUtils::utm_parameters() as $utm_parameter) {
      $key = "wonderpush_$utm_parameter";
      if (array_key_exists($key, $_POST)) {
        $meta_value = trim(sanitize_text_field($_POST[$key]));
        update_post_meta($post_id, $key, $meta_value && strlen($meta_value) ? $meta_value : null);
      }
    }
  }
  public static function on_transition_post_status( $new_status, $old_status, $post ) {
    if ($old_status === 'trash' && $new_status === 'publish') {
      return;
    }
    if (!empty($post)
      && $new_status === "publish"
      && get_post_status($post->ID) === "publish"
      && $post->post_type !== 'page') {
      self::send_notification_on_post($new_status, $old_status, $post);
    }
  }

  public static function send_notification_on_post($new_status, $old_status, $post) {
    try {
      if (!WonderPushUtils::is_curl_installed()) {
        return;
      }
      $settings = WonderPushSettings::getSettings();
      $access_token = $settings->getAccessToken();
      if (!$access_token || $settings->getDisableSendOnPublish()) return;

      // quirk of Gutenberg editor leads to two passes if meta box is added
      // conditional removes first pass
      if( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        return;
      }

      // Returns true if there is POST data
      $was_posted = !empty($_POST);

      // When this post was created or updated, the meta box in the WordPress post editor screen was visible
      $wonderpush_meta_box_present = $was_posted && array_key_exists('wonderpush_meta_box_present', $_POST) && $_POST['wonderpush_meta_box_present'] === 'true';

      // The checkbox "Send notification on post publish/update" on the meta box is checked
      $wonderpush_meta_box_send_notification_checked = $was_posted && array_key_exists('send_wonderpush_notification', $_POST) && $_POST['send_wonderpush_notification'] === 'true';

      // The notification date was filled
      $wonderpush_meta_send_notification_delay_seconds = null;
      if ($was_posted && array_key_exists('wonderpush_send_notification_delay_seconds', $_POST)) {
        $meta_value = trim(sanitize_text_field($_POST['wonderpush_send_notification_delay_seconds']));
        if (WonderPushUtils::is_int_string($meta_value) && (int)$meta_value < self::MAX_NOTIFICATION_DELAY_HOURS * 3600) {
          $wonderpush_meta_send_notification_delay_seconds = (int)$meta_value;
        }
      }

      // Target segment IDs
      $target_segment_ids = array();

      if ($was_posted && array_key_exists('wonderpush_target_segment_ids', $_POST)) {
        $target_segment_ids = array_filter(array_map(function($elt) {
          return trim(sanitize_text_field($elt));
        }, $_POST['wonderpush_target_segment_ids']));
      } else {
        $meta_value = get_post_meta($post->ID, 'wonderpush_target_segment_ids', true) ?: '';
        $target_segment_ids = array_filter(explode(self::METADATA_MULTIVALUE_SEPARATOR, $meta_value));
      }

      // Target tags
      $target_tags = array();
      if ($was_posted && array_key_exists('wonderpush_target_tags', $_POST)) {
        $target_tags = array_filter(array_map(function($elt) {
          return trim(sanitize_text_field($elt));
        }, $_POST['wonderpush_target_tags']));
      } else {
        $meta_value = get_post_meta($post->ID, 'wonderpush_target_tags', true) ?: '';
        $target_tags = array_filter(explode(self::METADATA_MULTIVALUE_SEPARATOR, $meta_value));
      }

      // utm parameters
      $utm_params = array();
      foreach (WonderPushUtils::utm_parameters() as $utm_parameter) {
        $value = null;
        $key = "wonderpush_$utm_parameter";
        if ($was_posted) {
          if (array_key_exists($key, $_POST)) {
            $value = $_POST[$key];
          }
        } else {
          $value = get_post_meta($post->ID, $key, true);
        }
        $value = $value ? trim(sanitize_text_field($value)) : $value;
        $value = $value && strlen($value) > 256 ? substr($value, 0, 256) : $value;
        $value = $value && strlen($value) ? $value : null;
        if ($value) $utm_params[$utm_parameter] = $value;
      }
      // This is a scheduled post and the meta box was present.
      $post_metadata_was_wonderpush_meta_box_present = (get_post_meta($post->ID, 'wonderpush_meta_box_present', true) == true);

      // This is a scheduled post and the user checked "Send a notification on post publish/update".
      $post_metadata_was_send_notification_checked = (get_post_meta($post->ID, 'wonderpush_send_notification', true) == true);

      // This is a scheduled post and the user filled notification delay
      $post_metadata_send_notification_delay_seconds = get_post_meta($post->ID, 'wonderpush_send_notification_delay_seconds', true);
      if ($post_metadata_send_notification_delay_seconds === null
        || $post_metadata_send_notification_delay_seconds === '') {
        // Backwards compat: set this to the settings value for those who saved the post with a previous version of the plugin
        // The current plugin version always sets a $post_metadata_send_notification_delay_seconds
        $post_metadata_send_notification_delay_seconds = $settings->getDeliveryTimeSeconds();
      }
      $post_metadata_send_notification_delay_seconds = (int)$post_metadata_send_notification_delay_seconds;

      // Either we were just posted from the WordPress post editor form, or this is a scheduled notification and it was previously submitted from the post editor form
      $posted_from_wordpress_editor = $wonderpush_meta_box_present || $post_metadata_was_wonderpush_meta_box_present;

      $last_sent_title = get_post_meta($post->ID, self::POST_META_LAST_NOTIFICATION_CONTENT, true);

      $send_notification_delay_seconds = null;

      $settings_send_notification_on_non_editor_post_publish = $settings->getSendOnThirdPartyPublish();
      $additional_custom_post_types_string = str_replace(' ', '', $settings->getAdditionalCustomPostTypes());
      $additional_custom_post_types_array = array_filter(explode(',', $additional_custom_post_types_string));
      $non_editor_post_publish_do_send_notification = $settings_send_notification_on_non_editor_post_publish &&
        ($post->post_type === 'post' || in_array($post->post_type, $additional_custom_post_types_array, true)) &&
        $old_status !== 'publish';

      if ($posted_from_wordpress_editor) {
        $do_send_notification = ($was_posted && $wonderpush_meta_box_send_notification_checked) ||
          (!$was_posted && $post_metadata_was_send_notification_checked);

        if ($was_posted) {
          // When posting and the notification has already been sent, make sure the 'wonderpush_notification_already_sent' key was sent along
          // Otherwise, this may be a page that wasn't refreshed as the post was published in the background.
          if ($last_sent_title && !array_key_exists('wonderpush_notification_already_sent', $_POST)) {
              $do_send_notification = false;
          }

          $send_notification_delay_seconds = $wonderpush_meta_send_notification_delay_seconds;
        } else {
          $send_notification_delay_seconds = $post_metadata_send_notification_delay_seconds;
        }
      } else {
        // This was not submitted via the WordPress editor
        $do_send_notification = $non_editor_post_publish_do_send_notification;
      }

      if (!$do_send_notification) return;

      // Create WonderPush client
      $wonderPushClient = WonderPushUtils::management_api_client($access_token);
      $default_target_segment_id = $settings->getDefaultTargetSegmentId();

      update_post_meta($post->ID, 'wonderpush_meta_box_present', false);
      update_post_meta($post->ID, 'wonderpush_send_notification', false);

      // Some WordPress environments seem to be inconsistent about whether on_save_post is called before transition_post_status
      // This sets the metadata back to true, and will cause a post to be sent even if the checkbox is not checked the next time
      // We remove all related $_POST data to prevent this
      if ($was_posted) {
        if (array_key_exists('wonderpush_meta_box_present', $_POST)) {
          unset($_POST['wonderpush_meta_box_present']);
        }
        if (array_key_exists('send_wonderpush_notification', $_POST)) {
          unset($_POST['send_wonderpush_notification']);
        }
      }

      $title = WonderPushUtils::decode_entities(get_the_title($post->ID));

      $site_title = "";
      if ($settings->getNotificationTitle()) {
        $site_title = WonderPushUtils::decode_entities($settings->getNotificationTitle());
      } else {
        $site_title = WonderPushUtils::decode_entities(get_bloginfo('name'));
      }

      $icon_image = null;
      $big_picture = null;
      if (has_post_thumbnail($post->ID)) {

        $post_thumbnail_id = get_post_thumbnail_id($post->ID);

        // Higher resolution (2x retina, + a little more) for the notification small icon
        $thumbnail_sized_images_array = wp_get_attachment_image_src($post_thumbnail_id, 'medium', false);
        $thumbnail_image = $thumbnail_sized_images_array && count($thumbnail_sized_images_array) > 0 ? $thumbnail_sized_images_array[0] : null;

        // Much higher resolution for the notification large image
        $large_sized_images_array = wp_get_attachment_image_src($post_thumbnail_id, 'large', false);
        $large_image = $large_sized_images_array && count($large_sized_images_array) > 0 ? $large_sized_images_array[0] : null;

        $config_use_featured_image_as_icon = !($settings->getDisableUsePostImageForNotification());
        $config_use_featured_image_as_image = !($settings->getDisableUsePostImageForNotification());
        $use_large_image = $settings->getPreferLargeImageForNotification();

        // Use the same image in any case
        $image = $use_large_image ? ($large_image ?: $thumbnail_image) : ($thumbnail_image ?: $large_image);

        // WPRocket support
        if ( function_exists( 'get_rocket_cdn_url' ) && $image ) {
            try {
              $rocket_url = get_rocket_cdn_url($image);
              if ($rocket_url) {
                $image = $rocket_url;
              }
            } catch (Exception $e) {
              WonderPushUtils::log('Rocket cdn function get_rocket_cdn_url threw:' . $e->getMessage());
            }
        }

        if ($config_use_featured_image_as_icon) {
          $icon_image = $image;
        }
        if ($config_use_featured_image_as_image) {
          $big_picture = $image;
        }
      }

      // Send the notification
      $notification = new \WonderPush\Obj\Notification();
      $alert = new \WonderPush\Obj\NotificationAlert();
      $notification->setAlert($alert);
      $permalink = get_permalink($post->ID);
      $target_url = WonderPushUtils::inject_query_string_params($permalink, $utm_params);
      $alert->setTargetUrl($target_url);
      $alert->setTitle($site_title);
      $alert->setText($title);

      // Android
      $android = new \WonderPush\Obj\NotificationAlertAndroid();
      $alert->setAndroid($android);
      if ($big_picture) {
        $android->setBigPicture($big_picture);
        $android->setType('bigPicture');
      }
      $ios = new \WonderPush\Obj\NotificationAlertIos();
      $alert->setIos($ios);
      if ($big_picture) {
        $attachment = new \WonderPush\Obj\NotificationAlertIosAttachment();
        $attachment->setUrl($big_picture);
        $attachment->setType('image/png'); // Valid for all image types
        $ios->setAttachments(array($attachment));
      }
      $ios->setSound('default');
      $web = new \WonderPush\Obj\NotificationAlertWeb();
      $alert->setWeb($web);
      if ($icon_image) $web->setIcon($icon_image);
      if ($big_picture) $web->setImage($big_picture);
      $params = new \WonderPush\Params\DeliveriesCreateParams();
      $params->setInheritUrlParameters(true);
      $params->setNotification($notification);
      $segmentIds = array();
      if (count($target_tags)) {
        $params->setTargetTags($target_tags);
      } else if (count($target_segment_ids)) {
        $segmentIds = $target_segment_ids;
        $params->setTargetSegmentIds($segmentIds);
      } else if ($default_target_segment_id) {
        $segmentIds = array($default_target_segment_id);
        $params->setTargetSegmentIds($segmentIds);
      } else {
        $segmentIds = array('@ALL');
        $params->setTargetSegmentIds($segmentIds);
      }
      if ($send_notification_delay_seconds !== null && $send_notification_delay_seconds > 0) {
        $params->setDeliveryTime('' . $send_notification_delay_seconds . 's');
      }
      // Deduplicate notifications
      $last_sent_timestamp = get_post_meta($post->ID, self::POST_META_LAST_NOTIFICATION_TIMESTAMP, true);
      $elapsed = current_time('timestamp') - ($last_sent_timestamp ? $last_sent_timestamp : 0);
      if ($elapsed < self::DEDUPLICATION_SECONDS && $last_sent_title === $title) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
          WonderPushUtils::log('Discarding duplicate notification: ' . json_encode((object)$params->toArray()));
        }
        return;
      }

      // Rate limit
      $wait_time = self::get_sending_rate_limit_wait_time();
      if ($wait_time) {
        update_post_meta($post->ID, self::POST_META_ERROR_MESSAGE, 'You must wait ' . $wait_time . 's before sending another notification');
        return;
      }

      // Remember last notification content and timestamp
      update_post_meta($post->ID, self::POST_META_LAST_NOTIFICATION_CONTENT, $title);
      update_post_meta($post->ID, self::POST_META_LAST_NOTIFICATION_TIMESTAMP, current_time('timestamp'));

      // Send the notification
      if (defined('WP_DEBUG') && WP_DEBUG) {
        WonderPushUtils::log('Sending WonderPush notification: ' . json_encode((object)$params->toArray()));
      }
      self::update_last_sent_timestamp();
      $response = $wonderPushClient->deliveries()->create($params);

      // Handle success/failure
      if ($response->isSuccess()) {
        if ($settings->getDisableFeedbackOnPublish()) {
          update_post_meta($post->ID, self::POST_META_INFO_MESSAGE, 'WonderPush notification sent.');
        } else {
          // Fetch the number of subscribers
          $countResponse = $wonderPushClient->installations()->all(array(
            'limit' => 0,
            'reachability' => 'optIn',
            'segmentIds' => $segmentIds,
            'tags' => $target_tags,
          ));
          $count = $countResponse->getCount();
          if ($count) {
            if ($send_notification_delay_seconds) {
              $dt = new DateTime();
              $dt->setTimestamp($send_notification_delay_seconds + $dt->getTimestamp());
              $formatted_date = $dt->format(DateTime::RFC850);
              update_post_meta($post->ID, self::POST_META_INFO_MESSAGE, "WonderPush will send a notification to {$count} subscribers on {$formatted_date}.");
            } else {
              update_post_meta($post->ID, self::POST_META_INFO_MESSAGE, "WonderPush notification sent to {$count} subscribers.");
            }
          } else {
            update_post_meta($post->ID, self::POST_META_ERROR_MESSAGE, "WonderPush notification sent but the target audience is empty.");
          }
        }
      } else {
        update_post_meta($post->ID, self::POST_META_ERROR_MESSAGE, "WonderPush notification could not be sent.");
      }
    } catch (\WonderPush\Errors\Base $e) {
      switch ($e->getCode()) {
        default:
          update_post_meta($post->ID, self::POST_META_ERROR_MESSAGE, $e->getMessage());
          break;
      }
    } catch (Exception $e) {
      WonderPushUtils::log('Caught Exception:' . $e->getMessage());
    }
  }

  public static function admin_wide_scripts_and_styles() {
    wp_register_script('typesafe-actions', WONDERPUSH_PLUGIN_URL . 'assets/js/typesafe-actions.js', false, '4.4.0');
    wp_register_script('redux-observable', WONDERPUSH_PLUGIN_URL . 'assets/js/redux-observable.js', false, '1.1.0');
    wp_register_script('qs', WONDERPUSH_PLUGIN_URL . 'assets/js/qs.js', false, '6.7.0');
    wp_register_script('class-transformer', WONDERPUSH_PLUGIN_URL . 'assets/js/class-transformer.js', false, '0.2.3');

    wp_register_script('axios', WONDERPUSH_PLUGIN_URL . 'assets/js/axios.js', false, '0.19.0');
    wp_register_script('react-redux', WONDERPUSH_PLUGIN_URL . 'assets/js/react-redux.js', array('react','react-dom','redux'), '6.0.1');
    wp_register_script('redux', WONDERPUSH_PLUGIN_URL . 'assets/js/redux.js', false, '4.0.1');
    wp_register_script('reflect-metadata', WONDERPUSH_PLUGIN_URL . 'assets/js/reflect-metadata.js', false, '0.1.13');
    wp_register_script('rxjs', WONDERPUSH_PLUGIN_URL . 'assets/js/rxjs.js', false, '6.5.2');
    wp_register_script('sprintf-js', WONDERPUSH_PLUGIN_URL . 'assets/js/sprintf-js.js', false, '1.1.2');
    wp_enqueue_style('wonderpush-admin-styles', WONDERPUSH_PLUGIN_URL . 'assets/wonderpush-admin.css', false, self::RESOURCES_VERSION);
    wp_enqueue_script('wonderpush-notice', WONDERPUSH_PLUGIN_URL . 'wonderpush-notice.js', false, self::RESOURCES_VERSION);
    wp_localize_script('wonderpush-notice', 'WonderPushNotice', array(
      'nonce' => WonderPushAPI::get_nonce()
    ));

    if (function_exists('wp_enqueue_script') && function_exists('get_current_screen') && 'post' === get_current_screen()->base) {
      wp_enqueue_script('select2', WONDERPUSH_PLUGIN_URL . 'assets/js/select2.full.min.js', array('jquery'), '4.0.13');
      wp_enqueue_style('select2', WONDERPUSH_PLUGIN_URL . 'assets/select2.min.css', false, self::RESOURCES_VERSION);
    }
  }

  public static function admin_scripts() {
    wp_enqueue_script('wonderpush-admin-page', WONDERPUSH_PLUGIN_URL . 'wonderpush-admin-page.js', array('react-redux', 'axios', 'rxjs', 'reflect-metadata', 'sprintf-js', 'class-transformer', 'qs', 'redux-observable', 'typesafe-actions'), self::RESOURCES_VERSION);
    wp_localize_script('wonderpush-admin-page', 'WonderPushWordPress', array(
      'pluginUrl' => WONDERPUSH_PLUGIN_URL,
      'ajaxUrl' => admin_url("admin-ajax.php"),
      'siteName' => WonderPushUtils::decode_entities(get_bloginfo('name')),
      'siteUrl' => WonderPushUtils::decode_entities(get_bloginfo('url')),
      'wordPressUrl' => WonderPushUtils::decode_entities(get_bloginfo('wpurl')),
      'wooCommerce' => WonderPushUtils::get_woocommerce() ? true : false,
      'amp' => WonderPushUtils::is_amp_installed() ? true : false,
      'nonce' => WonderPushAPI::get_nonce()
    ));
    wp_enqueue_style('bootstrap', WONDERPUSH_PLUGIN_URL . 'assets/bootstrap.min.css', false, self::RESOURCES_VERSION);
    wp_enqueue_style('wonderpush-admin-page', WONDERPUSH_PLUGIN_URL . 'wonderpush-admin-page.css', false, self::RESOURCES_VERSION);
  }

  public static function add_admin_page() {
    $menu = add_menu_page('WonderPush',
      'WonderPush',
      'manage_options',
      self::MENU_SLUG,
      array(__CLASS__, 'admin_page')
    );
    add_action('load-' . $menu, array(__CLASS__, 'admin_page_load'));
  }

  private static function show_page($page) {
      if (!WonderPushUtils::is_curl_installed()):
        ?>
          <div class="error notice">
              <p>
                  <?php echo __('<a href="https://www.php.net/manual/en/book.curl.php" target="_blank">cURL</a> is not available in this PHP installation, WonderPush cannot operate properly without cURL installed.') ?>
              </p>
          </div>
      <?php
      endif;
    ?>
      <div class="wonderpush-page" id="wonderpush-<?php echo $page ?>"></div>
      <script>
        (function () {
          window.WonderPushShowPage('<?php echo $page ?>', document.getElementById('wonderpush-<?php echo $page ?>'));
        })();
      </script><?php
  }

  public static function admin_page() {
    self::show_page('admin-page');
  }

  public static function admin_page_load() {
    // admin styles
    add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_scripts'));
  }

  public static function get_sending_rate_limit_wait_time() {
    $last_send_time = get_option('wonderpush.last_send_time');
    if ($last_send_time) {
      $current_time = current_time('timestamp');
      $time_elapsed_since_last_send = self::API_RATE_LIMIT_SECONDS - ($current_time - intval($last_send_time));
      if ($time_elapsed_since_last_send > 0) {
        return $time_elapsed_since_last_send;
      }
    }
    return false;
  }

  /**
   * Updates the last sent timestamp, used in rate limiting notifications sent more than 1 per minute.
   */
  public static function update_last_sent_timestamp() {
    $current_time = current_time('timestamp');
    update_option('wonderpush.last_send_time', $current_time);
  }

}
