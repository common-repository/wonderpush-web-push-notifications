<?php
if (!defined( 'ABSPATH' )) { http_response_code(403); exit(); }


class WonderPushWooCommerce {
  const CART_REMINDER_STRATEGY_LATEST = 'latest';
  const CART_REMINDER_STRATEGY_MOST_EXPENSIVE = 'most-expensive';
  const CART_REMINDER_STRATEGY_LEAST_EXPENSIVE = 'least-expensive';
  public static function cart_reminder_strategies() {
    return array(
      self::CART_REMINDER_STRATEGY_LATEST,
      self::CART_REMINDER_STRATEGY_MOST_EXPENSIVE,
      self::CART_REMINDER_STRATEGY_LEAST_EXPENSIVE,
    );
  }

  const CART_REMINDER_DESTINATION_CART = 'cart';
  const CART_REMINDER_DESTINATION_CHECKOUT = 'checkout';
  const CART_REMINDER_DESTINATION_HOMEPAGE = 'homepage';
  public static function cart_reminder_destinations() {
    return array(
      self::CART_REMINDER_DESTINATION_CART,
      self::CART_REMINDER_DESTINATION_CHECKOUT,
      self::CART_REMINDER_DESTINATION_HOMEPAGE,
    );
  }

  /** @var WooCommerce */
  static $woocommerce;
  static function init() {
    self::$woocommerce = WonderPushUtils::get_woocommerce();
    if (!self::$woocommerce) return;
    $cart_change_hooks = array(
      'woocommerce_add_to_cart',
      'woocommerce_cart_item_removed',
      'woocommerce_cart_item_restored',
      'woocommerce_cart_item_set_quantity',
      'woocommerce_cart_emptied',
      'woocommerce_thankyou',
    );
    // Sets the legacy cart reminder properties string_cartReminderProductName, etc.
    foreach ($cart_change_hooks as $hook) {
      add_action($hook, array(__CLASS__, 'cart_did_change'));
    }

    // Exit event on single product page
    add_action('woocommerce_before_single_product', array(__CLASS__, 'before_single_product'));

    // Send GOAL_1 on thankyou
    add_action('wp_head', array(__CLASS__, 'send_thankyou_event'), 10, 4);

    // Individual hooks used to fire standard WonderPush E-commerce events
    add_action('woocommerce_add_to_cart', array(__CLASS__, 'add_to_cart'));
    add_action('woocommerce_remove_cart_item', array(__CLASS__, 'remove_from_cart'));
    add_action('woocommerce_cart_item_restored', array(__CLASS__, 'add_to_cart'));
    add_action('woocommerce_thankyou', array(__CLASS__, 'purchase'));

    // Order status changes to send confirmation and shipping notifications
    add_action('woocommerce_order_status_changed', array(__CLASS__, 'order_status_changed'), 10, 4);
  }

  public static function add_to_cart($cart_item_key) {
    self::send_cart_event('AddToCart', $cart_item_key);
  }

  public static function remove_from_cart($cart_item_key) {
    self::send_cart_event('RemoveFromCart', $cart_item_key);
  }

  protected static function send_cart_event($event_type, $cart_item_key) {
    if (!self::$woocommerce || !self::$woocommerce->cart) return;
    $cart = self::$woocommerce->cart->get_cart();
    if (!$cart) return;
    $item = $cart[$cart_item_key];
    if (!$item) return;
    $product = $item['data'];
    $settings = WonderPushSettings::getSettings();
    $access_token = $settings->getAccessToken();
    if (!$access_token) return;
    global $wp;
    $payload = array(
        'object_product' => self::event_payload_from_product($product),
        'string_url' => \WonderPush\Util\ArrayUtil::getIfSet($_SERVER, 'HTTP_REFERER') ?: ($wp ? home_url($wp->request) : null),
    );
    try {
        WonderPushUtils::track_event($access_token, $event_type, $payload);
    } catch (Exception $e) {
        WonderPushUtils::log("Could not track event: " . $e);
    }
  }

  public static function before_single_product() {
      $product_id = get_the_ID();
      if (!$product_id) return;
      $product = wc_get_product( $product_id );
      if (!($product instanceof WC_Product)) return;
      $product_array = self::event_payload_from_product($product);
      if (!$product_array) return;
      $json_options = 0;
      if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $json_options |= JSON_INVALID_UTF8_SUBSTITUTE;
      else if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) $json_options |= JSON_PARTIAL_OUTPUT_ON_ERROR;
      $product_json = json_encode($product_array, $json_options);
      $json_last_error = json_last_error();
      if ($json_last_error !== JSON_ERROR_NONE) {
          if (function_exists('json_last_error_msg')) {
              $msg = json_last_error_msg();
          } else {
              $msg = '';
          }
          WonderPushUtils::log("Could not json_encode product array. code:" . $json_last_error . " msg:" . $msg . " product_array:" . print_r($product_array, true));
      }
      if ($product_json === false) return;
      ?>
    <script type="text/javascript">
        var lastExitEventDate;
        var lastExitEventUrl;
        document.addEventListener('mouseout', function(e) {
            if (!e.toElement && !e.relatedTarget) {
                if (lastExitEventUrl === window.location.href
                    && lastExitEventDate
                    && (+new Date() - lastExitEventDate.getTime()) < 5 * 60000) {
                    return;
                }
                lastExitEventDate = new Date();
                lastExitEventUrl = window.location.href;
                window.WonderPush = window.WonderPush || [];
                window.WonderPush.push(function() {
                   window.WonderPush.trackEvent('Exit', {
                       object_product: <?php echo $product_json; ?>,
                       string_url: window.location.href,
                   });
                });
            }
        });
    </script>
    <?php
  }

  public static function purchase($order_id) {
    $order = wc_get_order( $order_id );
    if (!($order instanceof WC_Order)) return;
    $settings = WonderPushSettings::getSettings();
    $accessToken = $settings ? $settings->getAccessToken() : null;
    if (!$accessToken) return;
    try {
      WonderPushUtils::track_event($accessToken, 'Purchase', array(
        'float_totalAmount' => (float)$order->get_total(),
      ));
    } catch (Exception $e) {
      WonderPushUtils::log("Could not track event: " . $e);
    }
  }

  public static function send_thankyou_event() {
      if( !is_wc_endpoint_url( 'order-received' ) ) return;
      $settings = WonderPushSettings::getSettings();
      if ($settings->getDisableThankYouEvent()) return;
      $eventName = $settings->getThankYouEventName() ?: 'GOAL_1';
      $args = array('trackEvent', $eventName);
      ?><script>WonderPush = window.WonderPush || []; WonderPush.push(<?php echo json_encode($args) ?>)</script><?php
  }

  public static function order_status_changed($order_id, $from_status, $to_status, $order) {
    $settings = WonderPushSettings::getSettings();
    $access_token = $settings->getAccessToken();
    if (!$access_token) return;

    // Order complete notifications
    if ($to_status === 'completed' && $from_status === 'processing') {
      try {
        if (!$settings->getEnableOrderCompleteNotifications()) return;

        $customer_id = !empty($order) ? $order->get_user_id() : null;
        if (!$customer_id) return;

        // Did we send a notification already?
        $meta_name = "order_status_complete_order_${order_id}";
        $meta_value = get_user_meta($customer_id, $meta_name, true);
        if ($meta_value) {
          if (defined('WP_DEBUG') && WP_DEBUG) {
            WonderPushUtils::log('Discarding duplicate shipping notification');
          }
          return;
        }

        $message = $settings->getOrderCompleteNotificationsMessage() ?: 'We\'ve just shipped your order.';
        $success = self::send_order_notification($order, $message);
        if ($success) {
          // Avoid sending twice
          update_user_meta($customer_id, $meta_name, true);
        }
      } catch (Exception $e) {
        WonderPushUtils::log('Caught Exception:' . $e->getMessage());
      }
    }

    if (($to_status === 'processing' && $from_status === 'pending') // Most goods
      || ($to_status === 'completed' && $from_status === 'pending')) { // Digital goods

      try {
        if (!$settings->getEnableOrderProcessingNotifications()) return;

        $customer_id = !empty($order) ? $order->get_user_id() : null;
        if (!$customer_id) return;

        // Did we send a notification already?
        $meta_name = "order_status_processing_order_${order_id}";
        $meta_value = get_user_meta($customer_id, $meta_name, true);
        if ($meta_value) {
          if (defined('WP_DEBUG') && WP_DEBUG) {
            WonderPushUtils::log('Discarding duplicate order confirmation notification');
          }
          return;
        }

        $message = $settings->getOrderProcessingNotificationsMessage() ?: 'We\'re preparing your order.';
        $success = self::send_order_notification($order, $message);
        if ($success) {
          // Avoid sending twice
          update_user_meta($customer_id, $meta_name, true);
        }
      } catch (Exception $e) {
        WonderPushUtils::log('Caught Exception:' . $e->getMessage());
      }
    }
  }

  /**
   * Sends a notification to the customer behind the order with a link to the order page and the image of a product.
   * @param $order
   * @param $message
   * @return bool True on success
   */
  private static function send_order_notification($order, $message) {
    try {
      $settings = WonderPushSettings::getSettings();
      $access_token = $settings->getAccessToken();
      if (!$access_token) return false;

      $customer_id = !empty($order) ? $order->get_user_id() : null;
      if (!$customer_id) return false;

      // Find a product image
      $product_icon_url = self::get_order_icon($order);
      $product_image_url = self::get_order_image($order);

      $utm_params = $settings->getUtmParameters();
      $site_title = WonderPushUtils::decode_entities($settings->getNotificationTitle() ?: get_bloginfo('name'));

      $notification = new \WonderPush\Obj\Notification();
      $alert = new \WonderPush\Obj\NotificationAlert();
      $notification->setAlert($alert);
      $order_view_url = $order->get_view_order_url();
      $target_url = WonderPushUtils::inject_query_string_params($order_view_url, $utm_params);
      $alert->setTargetUrl($target_url);
      $alert->setTitle($site_title);
      $alert->setText($message);

      $ios = new \WonderPush\Obj\NotificationAlertIos();
      $ios->setSound('default');
      $alert->setIos($ios);
      $web = new \WonderPush\Obj\NotificationAlertWeb();
      $alert->setWeb($web);
      if ($product_icon_url) $web->setIcon($product_icon_url);
      if ($product_image_url) $web->setImage($product_image_url);
      $params = new \WonderPush\Params\DeliveriesCreateParams();
      $params->setInheritUrlParameters(true);
      $params->setNotification($notification);
      $params->setTargetUserIds(strval($customer_id));
      $params->setDeliveryDate((time() + 10) * 1000);

      // Send the notification
      if (defined('WP_DEBUG') && WP_DEBUG) {
        WonderPushUtils::log('Sending WonderPush notification: ' . json_encode((object)$params->toArray()));
      }
      $wonderPushClient = new \WonderPush\WonderPush($access_token);
      $response = $wonderPushClient->deliveries()->create($params);

      if ($response->isSuccess()) {
        return true;
      } else {
        WonderPushUtils::log('Could not send WonderPush order confirmation notification.');
        return false;
      }
    } catch (Exception $e) {
      WonderPushUtils::log('Caught Exception:' . $e->getMessage());
      return false;
    }
  }

  private static function get_order_assets($order) {
    $result = array();
    foreach ($order->get_items() as $item) {
      if ($item->is_type('line_item')) {
        $product = $item->get_product();
        $image_id = null;
        if ($product->get_image_id()) $image_id = $product->get_image_id();
        else if ($product->get_parent_id()) {
          $parent = wc_get_product($product->get_parent_id());
          if ($parent) {
            $image_id = $parent->get_image_id();
          }
        }

        if ($image_id) {
          // Higher resolution (2x retina, + a little more) for the notification small icon
          $thumbnail_sized_images_array = wp_get_attachment_image_src($image_id, array(192, 192), true);
          // Much higher resolution for the notification large image
          $large_sized_images_array = wp_get_attachment_image_src($image_id, 'large', true);
          if (!empty($thumbnail_sized_images_array)) $result['product_icon_url'] = $thumbnail_sized_images_array[0];
          if (!empty($large_sized_images_array)) $result['product_image_url'] = $large_sized_images_array[0];
        }

        // We want at least an icon
        if (isset($result['product_icon_url'])) break;
      }
    }
    return $result;
  }

  protected static function get_order_icon($order) {
    $assets = self::get_order_assets($order);
    return isset($assets['product_icon_url']) ? $assets['product_icon_url'] : null;
  }

  protected static function get_order_image($order) {
    $assets = self::get_order_assets($order);
    return isset($assets['product_image_url']) ? $assets['product_image_url'] : null;
  }

  public static function cart_did_change() {
    if (!self::$woocommerce) return;
    $settings = WonderPushSettings::getSettings();
    $access_token = $settings->getAccessToken();

    // We need an access token
    if (!$access_token) {
      return;
    }

    // Is the cart reminder enabled?
    if (!$settings->getEnableCartReminder()) {
      return;
    }

    // Choose a cart reminder strategy
    $strategy = $settings->getCartReminderStrategy();
    if (!$strategy) $strategy = self::CART_REMINDER_STRATEGY_LATEST;

    // Select a cart item
    $selected_cart_item = null;
    $selected_cart_item_total = 0;
    $cart = self::$woocommerce->cart;

    if ($cart) {
      foreach($cart->get_cart() as $cart_item_key => $cart_item) {
        // Cart items are iterated in the order they were added
        // So the "latest" strategy picks the last elt of the array
        if ($strategy === self::CART_REMINDER_STRATEGY_LATEST) {
          $selected_cart_item = $cart_item;
          continue;
        }

        $product = $cart_item['data'];
        $quantity = $cart_item['quantity'];
        $cart_item_total = (is_numeric($product->get_price()) ? $product->get_price() : 0)
          * (is_numeric($quantity) ? $quantity : 0);

        // We must select at least a product
        if ($selected_cart_item === null) {
          $selected_cart_item = $cart_item;
          $selected_cart_item_total = $cart_item_total;
          continue;
        }
        switch($strategy) {
          case self::CART_REMINDER_STRATEGY_MOST_EXPENSIVE:
            if ($cart_item_total > $selected_cart_item_total) {
              $selected_cart_item = $cart_item;
              $selected_cart_item_total = $cart_item_total;
            }
            break;
          case self::CART_REMINDER_STRATEGY_LEAST_EXPENSIVE:
            if ($cart_item_total < $selected_cart_item_total) {
              $selected_cart_item = $cart_item;
              $selected_cart_item_total = $cart_item_total;
            }
            break;
        }
      }
    }

    $destination = $settings->getCartReminderDestination();
    if (!$destination) $destination = WonderPushWooCommerce::CART_REMINDER_DESTINATION_HOMEPAGE;

    $message = $settings->getCartReminderMessage();
    if (!$message) $message = null;

    $url = get_bloginfo('wpurl');
    switch ($destination) {
      case WonderPushWooCommerce::CART_REMINDER_DESTINATION_CHECKOUT:
        $url = wc_get_checkout_url();
        break;
      case WonderPushWooCommerce::CART_REMINDER_DESTINATION_CART:
        $url = wc_get_cart_url();
        break;
    }
    $product = $selected_cart_item ? $selected_cart_item['data'] : null;
    $pictureUrl = null;
    if ($product && !$settings->getDisableCartReminderImage()) {
      if ( $product->get_image_id() ) {
        $pictureUrl = wp_get_attachment_url($product->get_image_id());
      } elseif ( $product->get_parent_id() ) {
        $parent_product = wc_get_product( $product->get_parent_id() );
        if ( $parent_product && $parent_product->get_image_id() ) {
          $pictureUrl = wp_get_attachment_url($parent_product->get_image_id());
        }
      }
    }
    try {
      WonderPushUtils::put_current_installation_properties($access_token, (object)array(
        'string_cartReminderProductName' => $product ? $product->get_name() : null,
        'string_cartReminderUrl' => $url,
        'string_cartReminderMessage' => $message,
        'string_cartReminderPictureUrl' => $pictureUrl ? $pictureUrl : null,
      ), WonderPushUtils::get_user_id());
    } catch (Exception $e) {
      WonderPushUtils::log('Could not set cart reminder properties: ' . $e->getMessage());
    }
  }

  protected static function sanitize($str) {
      if (!is_string($str)) return null;
      if (!$str) return $str;
      $html_entity_decode_flags = ENT_QUOTES;
      if (defined('ENT_HTML5')) $html_entity_decode_flags |= ENT_HTML5; // Whether to decode &apos;
      $stripped = html_entity_decode(strip_tags($str), $html_entity_decode_flags);
      $stripped = preg_replace('/\s+/', ' ', $stripped);
      return strlen($stripped) > 120 ? substr($stripped, 0, 119) . '…' : $stripped;
  }

  protected static function event_payload_from_product($product) {
    if (!($product instanceof WC_Product)) return null;
    $settings = WonderPushSettings::getSettings();
    $pictureUrl = null;
    if (!$settings->getDisableCartReminderImage()) {
      if ( $product->get_image_id() ) {
        $pictureUrl = wp_get_attachment_url($product->get_image_id());
      } elseif ( $product->get_parent_id() ) {
        $parent_product = wc_get_product( $product->get_parent_id() );
        if ( $parent_product && $parent_product->get_image_id() ) {
          $pictureUrl = wp_get_attachment_url($parent_product->get_image_id());
        }
      }
    }
    $availability = null;
    if (is_array($product->get_availability())) {
      $availabilityArray = $product->get_availability();
      switch ( $availabilityArray['class'] ) {
        case 'out-of-stock':
          $availability = 'OutOfStock';
          break;
        case 'in-stock':
          $availability = 'InStock';
          break;
        case 'available-on-backorder':
          $availability = 'BackOrder';
          break;
      }
    }

    $currency = null;
    if (function_exists('get_woocommerce_currency')) {
      $currency = get_woocommerce_currency();
    }
    return array(
        'string_type' => 'Product',
        'string_image' => $pictureUrl && is_string($pictureUrl) ? $pictureUrl : null,
        'string_name' => $product->get_name() ? self::sanitize($product->get_name()): null,
        'string_description' => $product->get_description() ? self::sanitize($product->get_description()) : null,
        'string_sku' => $product->get_sku() && is_string($product->get_sku()) ? $product->get_sku() : null,
        'object_offers' => array(
            'string_type' => 'Offer',
            'float_price' => (float)$product->get_price(),
            'string_priceCurrency' => $currency && is_string($currency) ? $currency : null,
            'string_url' => $product->get_permalink() && is_string($product->get_permalink()) ? $product->get_permalink() : null,
            'string_availability' => $availability,
        )
    );
  }
}
