<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

if (! defined('ABSPATH')) {
  exit;
}

if (!class_exists('WC_Facebookcommerce_Utils')) {
  include_once 'includes/fbutils.php';
}

if (! class_exists('WC_Facebookcommerce_Info_Banner')) :

/**
 * FB Info Banner class
 */
class WC_Facebookcommerce_Info_Banner {

  const FB_DEFAULT_TIP_DISMISS_TIME_CAP = 60;
  const FB_NO_TIP_EXISTS = 'No Tip Exist!';
  const DEFAULT_TIP_TITLE = 'Facebook for WooCommerce';
  const DEFAULT_TIP_BODY = 'Create ads that are designed
                for getting online sales and revenue.';
  const DEFAULT_TIP_ACTION = 'Create Ads';
  const DEFAULT_TIP_ACTION_LINK = 'https://www.facebook.com/ads/dia/redirect/?settings_id=';
  const DEFAULT_TIP_IMG_URL_PREFIX = 'https://www.facebook.com';
  const DEFAULT_TIP_IMG_URL = 'https://www.facebook.com/images/ads/growth/aymt/glyph-shopping-cart_page-megaphone.png';
  const CHANNEL_ID = 2087541767986590;

  /** @var object Class Instance */
  private static $instance;

  /** @var string If the banner has been dismissed */
  private $default_tip_pass_cap;
  private $external_merchant_settings_id;
  private $fbgraph;
  private $should_query_tip;

  /**
   * Get the class instance
   */
  public static function get_instance(
    $external_merchant_settings_id,
    $fbgraph,
    $should_query_tip = false,
    $default_tip_pass_cap = false) {
    return null === self::$instance
      ? (self::$instance = new self(
        $external_merchant_settings_id,
        $fbgraph,
        $should_query_tip,
        $default_tip_pass_cap))
      : self::$instance;
  }

  /**
   * Constructor
   */
  public function __construct(
    $external_merchant_settings_id,
    $fbgraph,
    $should_query_tip = false,
    $default_tip_pass_cap = false) {
    $this->should_query_tip = $should_query_tip;
    $this->default_tip_pass_cap = $default_tip_pass_cap;
    $this->external_merchant_settings_id = $external_merchant_settings_id;
    $this->fbgraph = $fbgraph;
    add_action('wp_ajax_ajax_woo_infobanner_post_click', array($this, 'ajax_woo_infobanner_post_click'));
    add_action('wp_ajax_ajax_woo_infobanner_post_xout', array($this, 'ajax_woo_infobanner_post_xout'));
    add_action('admin_notices', array($this, 'banner'));
    add_action('admin_init', array($this, 'dismiss_banner'));
  }

  /**
   * Post click event when hit primary button.
   */
   function ajax_woo_infobanner_post_click() {
     WC_Facebookcommerce_Utils::check_woo_ajax_permissions(
       'post tip click event',
       true);
    $tip_info = WC_Facebookcommerce_Utils::get_cached_best_tip();
    $tip_id = isset($tip_info->tip_id)
      ? $tip_info->tip_id
      : null;
     if ($tip_id == null) {
       WC_Facebookcommerce_Utils::fblog(
         'Do not have tip id maybe
         rendering static one',
         array(),
         true);
     } else {
       WC_Facebookcommerce_Utils::tip_events_log(
         $tip_id,
         self::CHANNEL_ID,
         'click');
     }
   }

  /**
   * Post xout event when hit dismiss button.
   */
   function ajax_woo_infobanner_post_xout() {
     WC_Facebookcommerce_Utils::check_woo_ajax_permissions(
       'post tip xout event',
       true);
     $tip_info = WC_Facebookcommerce_Utils::get_cached_best_tip();
     $tip_id = isset($tip_info->tip_id)
       ? $tip_info->tip_id
       : null;
     if ($tip_id == null) {
       WC_Facebookcommerce_Utils::fblog(
         'Do not have tip id maybe
         rendering static one',
         array(),
         true);
     } else {
       WC_Facebookcommerce_Utils::tip_events_log(
         $tip_id,
         self::CHANNEL_ID,
         'xout');
     }
   }

  /**
   * Display a info banner on Woocommerce pages.
   */
  public function banner() {
    $screen = get_current_screen();
    if (!in_array($screen->base, array('woocommerce_page_wc-reports',
      'woocommerce_page_wc-settings', 'woocommerce_page_wc-status')) ||
      $screen->is_network || $screen->action) {
      return;
    }

    $tip_info = null;
    if (!$this->should_query_tip) {
      // If last query is less than 1 day, either has last best tip or default
      // tip pass time cap.
      $tip_info = WC_Facebookcommerce_Utils::get_cached_best_tip();
    } else {
      $tip_info = $this->fbgraph->get_tip_info(
        $this->external_merchant_settings_id);
      update_option('fb_info_banner_last_query_time', current_time('mysql'));
    }
    $is_default = !$tip_info || ($tip_info === self::FB_NO_TIP_EXISTS);

    // Will not show tip if tip is default and has not over 60 days after last dismissed
    if ($is_default) {
      // Delete cached tip if should query and get no tip.
      delete_option('fb_info_banner_last_best_tip');
      if (!$this->default_tip_pass_cap) {
        return;
      }
    }

    // Get default tip creatives
    $tip_title = self::DEFAULT_TIP_TITLE;
    $tip_body = self::DEFAULT_TIP_BODY;
    $tip_action = self::DEFAULT_TIP_ACTION;
    $tip_action_link = esc_url(self::DEFAULT_TIP_ACTION_LINK .
      $this->external_merchant_settings_id.'&entry_point=aymt');
    $tip_img_url = self::DEFAULT_TIP_IMG_URL;

    // Get tip creatives via API
    if (!$is_default) {
      $tip_title = isset($tip_info->tip_title->__html)
        ? $tip_info->tip_title->__html
        : self::DEFAULT_TIP_TITLE;

      $tip_body = isset($tip_info->tip_body->__html)
        ? $tip_info->tip_body->__html
        : self::DEFAULT_TIP_BODY;

      $tip_action_link = isset($tip_info->tip_action_link)
        ? $tip_info->tip_action_link
        : esc_url(self::DEFAULT_TIP_ACTION_LINK.
          $this->external_merchant_settings_id);

      $tip_action = isset($tip_info->tip_action->__html)
        ? $tip_info->tip_action->__html
        : self::DEFAULT_TIP_ACTION;

      $tip_img_url = isset($tip_info->tip_img_url)
        ? self::DEFAULT_TIP_IMG_URL_PREFIX . $tip_info->tip_img_url
        : self::DEFAULT_TIP_IMG_URL;

      update_option('fb_info_banner_last_best_tip',
        is_object($tip_info) || is_array($tip_info)
        ? json_encode($tip_info) : $tip_info);
    }

    $dismiss_url = $this->dismiss_url();
    echo '<div class="updated fade"><div id="fbinfobanner"><div><img src="'. $tip_img_url .
    '" class="iconDetails"></div><p class = "tipTitle">' .
    __('<strong>' . $tip_title . '</strong>', 'facebook-for-woocommerce') . "\n";
    echo '<p class = "tipContent">'.
      __($tip_body, 'facebook-for-woocommerce') . '</p>';
    echo '<p class = "tipButton"><a href="' . $tip_action_link . '" class = "btn" onclick="fb_woo_infobanner_post_click(); return true;" title="' .
      __('Click and redirect.', 'facebook-for-woocommerce').
      '"> ' . __($tip_action, 'facebook-for-woocommerce') . '</a>' .
      '<a href="' . esc_url($dismiss_url). '" class = "btn dismiss grey" onclick="fb_woo_infobanner_post_xout(); return true;" title="' .
      __('Dismiss this notice.', 'facebook-for-woocommerce').
      '"> ' . __('Dismiss', 'facebook-for-woocommerce') . '</a></p></div></div>';
  }

  /**
   * Returns the url that the user clicks to remove the info banner
   * @return (string)
   */
  private function dismiss_url() {
    $url = admin_url('admin.php');

    $url = add_query_arg(array(
      'page'      => 'wc-settings',
      'tab'       => 'integration',
      'wc-notice' => 'dismiss-fb-info-banner',
    ), $url);

    return wp_nonce_url($url, 'woocommerce_info_banner_dismiss');
  }

  /**
   * Handles the dismiss action so that the banner can be permanently hidden
   * during time threshold
   */
  public function dismiss_banner() {
    if (!isset($_GET['wc-notice'])) {
      return;
    }

    if ('dismiss-fb-info-banner' !== $_GET['wc-notice']) {
      return;
    }

    if (!check_admin_referer('woocommerce_info_banner_dismiss')) {
      return;
    }
    // Not to show default tip 30 days.
    if (!WC_Facebookcommerce_Utils::get_cached_best_tip()) {
      update_option('fb_info_banner_last_dismiss_time', current_time('mysql'));
    }
    delete_option('fb_info_banner_last_best_tip');
    if (wp_get_referer()) {
      wp_safe_redirect(wp_get_referer());
    } else {
      wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=integration'));
    }
  }
}

endif;
