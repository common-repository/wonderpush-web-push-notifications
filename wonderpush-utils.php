<?php
if (!defined( 'ABSPATH' )) { http_response_code(403); exit(); }

class WonderPushUtils {
  const DEFAULT_CACHE_TTL = 300;

  public static function log($msg) {
    $date = date("c");
    $prefix = ' [WonderPush] ';
    error_log($date . $prefix . $msg);
  }

  public static function is_wonderpush_installed() {
    $settings = WonderPushSettings::getSettings();
    $access_token = $settings->getAccessToken();
    if (!$access_token || $settings->getDisableSnippet()) return false;
    try {
      $app = self::application_from_access_token($access_token);
    } catch (Exception $e) {
      return false;
    }
    if (!$app) return false;
    if (!$app->getWebKey()) return false;
    return true;
  }

  public static function can_modify_settings() {
    return WonderPushUtils::is_admin_user();
  }

  public static function can_send_notifications() {
    return current_user_can('publish_posts') || current_user_can('edit_published_posts');
  }

  public static function is_admin_user() {
    return current_user_can('delete_users');
  }

  /* If >= PHP 5.4, ENT_HTML401 | ENT_QUOTES will correctly decode most entities including both double and single quotes.
   In PHP 5.3, ENT_HTML401 does not exist, so we have to use `str_replace("&apos;","'", $value)` before feeding it to html_entity_decode(). */
  public static function decode_entities($string) {
    if (!$string) return '';
    $HTML_ENTITY_DECODE_FLAGS = ENT_QUOTES;
    if (defined('ENT_HTML401')) {
      // @codingStandardsIgnoreLine
      $HTML_ENTITY_DECODE_FLAGS = ENT_HTML401 | $HTML_ENTITY_DECODE_FLAGS;
    }
    return html_entity_decode(str_replace("&apos;", "'", $string), $HTML_ENTITY_DECODE_FLAGS, 'UTF-8');
  }

  /**
   * Creates a new Management API client
   * @param $access_token
   * @return \WonderPush\WonderPush
   */
  public static function management_api_client($access_token) {
    $client = new \WonderPush\WonderPush($access_token);
    $settings = WonderPushSettings::getSettings();
    if (!$settings->getBypassWordPressHttpClient()) {
      $client->setHttpClient(new WonderPushHttpClient($client));
    } else {
      // Force ipv4
      $client->setHttpClient(new \WonderPush\Net\CurlHttpClient($client, array('ipv4' => true)));
    }
    return $client;
  }

  public static function list_tags($access_token, $expiration = 30, $limit = 100) {
    // Cached value ?
    $cache_key = "WonderPush:list_tags:" . $access_token;
    $cached = get_transient($cache_key);
    if ($cached) {
      return $cached;
    }

    // Check access token with the API
    $wp = self::management_api_client($access_token);
    try {
      $params = new \WonderPush\Params\FrequentFieldValuesParams();
      $params->setKind('installations')
        ->setField('custom.tags');
      $response = $wp->stats()->frequentFieldValues($params);
      $tags = array_map(function($elt) { return $elt->getValue(); }, $response->getData());

      if ($tags && count($tags) > 0) {
        set_transient($cache_key, $tags, $expiration);
        return $tags;
      }
      // Access token not associated with any app
      delete_transient($cache_key);
      return array();
    } catch (Exception $e) {
      delete_transient($cache_key);
      if ($e instanceof \WonderPush\Errors\Server
        && ($e->getResponse()->getStatusCode() == 403 || $e->getCode() === 11003)) {
        // Invalid access token
        return array();
      }
      throw $e;
    }

  }
  public static function list_segments($access_token, $expiration = 30, $fields = array('name', 'id'), $limit = 100) {
    // Cached value ?
    $cache_key = "WonderPush:list_segments:" . $access_token;
    $cached = get_transient($cache_key);
    if ($cached) {
      return $cached;
    }

    // Check access token with the API
    $wp = self::management_api_client($access_token);
    try {
      $segments = $wp->segments()->all(array('fields' => $fields ? implode(',', $fields) : '', 'sort' => '-updateDate', 'limit' => $limit));

      if ($segments && $segments->getCount() > 0) {
        $data = $segments->getData();
        set_transient($cache_key, $data, $expiration);
        return $data;
      }
      // Access token not associated with any app
      delete_transient($cache_key);
      return false;
    } catch (Exception $e) {
      delete_transient($cache_key);
      if ($e instanceof \WonderPush\Errors\Server
        && ($e->getResponse()->getStatusCode() == 403 || $e->getCode() === 11003)) {
        // Invalid access token
        return false;
      }
      throw $e;
    }

  }

  /**
   * @param string $access_token
   * @param array $url_parameters
   * @throws Exception
   */
  public static function patch_application_url_parameters($access_token, $url_parameters) {
    if (!$access_token) return;
    $app = WonderPushUtils::application_from_access_token($access_token, 300, true);
    if (!$app) return;
    $merged_url_parameters = $app ? (array)$app->getUrlParameters() : array();
    $urlParametersUpdated = false;
    foreach ($url_parameters as $utm => $value) {
      if ($value && (!array_key_exists($utm, $merged_url_parameters) || $merged_url_parameters[$utm] !== $value)) {
        $merged_url_parameters[$utm] = $value;
        $urlParametersUpdated = true;
      } else if (!$value && array_key_exists($utm, $merged_url_parameters) && $merged_url_parameters[$utm]) {
        $merged_url_parameters[$utm] = null;
        $urlParametersUpdated = true;
      }
    }
    if ($urlParametersUpdated) {
      $wp = new WonderPush\WonderPush($access_token);
      $updatedApp = $wp->applications()->patch($app->getId(), array('urlParameters' => (object)$merged_url_parameters));
      $cache_key = "WonderPush:Application:" . $access_token;
      set_transient($cache_key, $updatedApp, self::DEFAULT_CACHE_TTL);
    }
  }
  /**
   * Returns the first application associated with the provided access token,
   * or false if the access token is not valid.
   * This method uses the WordPress transient API to cache the application and avoid network calls if possible.
   * Throws exception is validity could not be determined (network error for instance).
   * @throws Exception
   * @param string $access_token
   * @param int $expiration How long to cache the result
   * @return false|\WonderPush\Obj\Application
   */
  public static function application_from_access_token($access_token, $expiration = self::DEFAULT_CACHE_TTL, $forceFetch = false) {
    // Cached value ?
    $cache_key = "WonderPush:Application:" . $access_token;
    $cached = $forceFetch ? null : get_transient($cache_key);
    if ($cached) {
      return $cached;
    }

    // Check access token with the API
    $wp = self::management_api_client($access_token);
    try {
      $applications = $wp->applications()->all();
      if ($applications && $applications->getCount() > 0) {
        $data = $applications->getData();
        $app = $data[0];
        set_transient($cache_key, $app, $expiration);
        return $app;
      }
      // Access token not associated with any app
      delete_transient($cache_key);
      return false;
    } catch (Exception $e) {
      delete_transient($cache_key);
      if ($e instanceof \WonderPush\Errors\Server
        && ($e->getResponse()->getStatusCode() == 403 || $e->getCode() === 11003)) {
        // Invalid access token
        return false;
      }
      throw $e;
    }
  }

  /**
   * Tracks an event by calling the WonderPush API
   * @param string $access_token
   * @param string $event_type
   * @param array|object|null $payload
   * @param string $user_id
   * @return boolean Success
   * @throws \WonderPush\Errors\Base
   */
  public static function track_event($access_token, $event_type, $payload, $user_id = null) {
    if ($user_id === null) $user_id = WonderPushUtils::get_user_id();
    if (!$access_token) {
      return false;
    }
    $wp = self::management_api_client($access_token);
    $installation_id = self::get_installation_id();
    if (!$installation_id) {
      return false;
    }
    $params = new \WonderPush\Params\TrackEventParams($event_type, $installation_id, strval($user_id));
    $params->setCustom($payload);
    try {
      $result = $wp->events()->track($params);
      return $result->isSuccess();
    } catch (Exception $e) {
      WonderPushUtils::log("Error tracking event: " . $e);
      return false;
    }
  }

  /**
   * Puts the given properties on the current installation by calling WonderPush Management API
   * @param string $access_token
   * @param array|object $properties
   * @return boolean Success
   * @throws \WonderPush\Errors\Base
   */
  public static function put_current_installation_properties($access_token, $properties, $user_id = '') {
    if (!$access_token) {
      return false;
    }
    $wp = self::management_api_client($access_token);
    $installation_id = self::get_installation_id();
    if (!$installation_id) {
      return false;
    }
    $params = new \WonderPush\Params\PatchInstallationParams($installation_id, strval($user_id));
    $params->setProperties($properties);
    $result = $wp->installations()->patch($params);
    return $result->isSuccess();
  }

  /**
   * Returns the WonderPush installation ID for the current request
   * by looking at the cookie named `WonderPushPublic::INSTALLATION_ID_COOKIE_NAME`
   * @return string|null
   */
  public static function get_installation_id() {
    if (!array_key_exists(WonderPushPublic::INSTALLATION_ID_COOKIE_NAME, $_COOKIE)) {
      return null;
    }
    $installation_id = $_COOKIE[WonderPushPublic::INSTALLATION_ID_COOKIE_NAME];
    return $installation_id ? $installation_id : null;
  }

  /**
   * @return WooCommerce | null
   */
  public static function get_woocommerce() {
    if (!function_exists('WC')) return null;
    return WC();
  }

  public static function is_amp_installed() {
    if (defined('AMP__FILE__')) return true;
    if (defined('AMPFORWP_VERSION')) return true;
    return false;
  }

  public static function is_amp_request() {
    if (function_exists('amp_is_request')) {
      return amp_is_request();
    }
    return false;
  }


  /**
   * Converts a DateTime object to a string representing its date.
   * @param DateTime $date_time
   * @return string
   */
  public static function datetime_to_date_string($date_time) {
    return $date_time->format('Ymd');
  }

  /**
   * Converts a date string obtained with `datetime_to_date_string` to a DateTime object.
   * @param string $date_string A date_string in the form YYYYMMDD (8 digits exactly)
   * @return DateTime|null
   */
  public static function date_string_to_datetime($date_string) {
    if (!self::is_valid_date_string($date_string)) return null;
    $year = substr($date_string, 0, 4);
    $month = substr($date_string, 4, 2);
    $day = substr($date_string, 6, 2);
    $result = new DateTime();
    $result->setDate($year, $month, $day);
    $result->setTime(0, 0, 0, 0);
    return $result;
  }

  /**
   * Returns true when provided date string is exactly 8 digits.
   * @param $date_string
   * @return bool
   */
  public static function is_valid_date_string($date_string) {
    return preg_match('/\d{8}/', $date_string) ? true : false;
  }

  /**
   * Returns true when provided string only has digits.
   * @param $str
   * @return bool
   */
  public static function is_int_string($str) {
    return (bool)preg_match('/^[0-9]+$/', $str);
  }

  /**
   * Returns true when provided time string is an integer between 0 and 23 inclusive.
   * @param $time_string
   * @return bool
   */
  public static function is_valid_time_string($time_string) {
    return preg_match('/^[0-2]?[0-9]$/', $time_string) && (int)$time_string < 24 ? true : false;
  }

  public static function utm_parameters() {
      return array('utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content');
  }

  public static function user_segmentation_keys() {
    return array('first_name', 'last_name', 'user_login', 'display_name');
  }

  public static function inject_query_string_params($url, $params) {
    $parsed_url = parse_url($url);
    if ($parsed_url === false) return $url;
    return http_build_url($parsed_url, array('query' => http_build_query($params)), HTTP_URL_JOIN_QUERY);
  }

  public static function is_curl_installed() {
    return function_exists('curl_init');
  }

  public static function get_user_id() {
    $settings = WonderPushSettings::getSettings();
    return $settings->getDisableUserIdInSnippet() ? '' : get_current_user_id();
  }
}
