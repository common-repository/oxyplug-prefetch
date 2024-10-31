<?php
/**
 * Plugin Name: OxyPlug - Prefetch & Prerender
 * Plugin URI: https://www.oxyplug.com/products/oxy-prefetch/
 * Description: Faster loading next pages by prerendering/prefetching all links a user hovers or addresses you prefer. It improves UX and Core Web Vitals score.
 * Version: 2.1.2
 * Author: OxyPlug
 * Author URI: https://www.oxyplug.com
 * Text Domain: oxy-prefetch
 * Domain Path: /lang/
 * Requires at least: 5.3
 * Requires PHP: 7.4
 * Tested up to: 6.7
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Copyright 2024 OxyPlug
 */

class OxyPrefetch
{
  public $wpdb;

  public array $already_added = array(
    'prefetch' => array(),
    'prerender' => array()
  );
  public array $script_immediate = array();
  public array $script_moderate = array();

  const OXY_PREFETCH_VERSION = '2.1.2';

  const OXY_PREFETCHES_NUMBER_DEFAULT = 4;
  const OXY_PREFETCHES_NUMBER_MAX = 50;
  const OXY_PREFETCHES_NUMBER_MIN = 1;

  const OXY_PREFETCH_PRERENDER_NUMBER_DEFAULT = 3;
  const OXY_PREFETCH_PRERENDER_NUMBER_MAX = 10;
  const OXY_PREFETCH_PRERENDER_NUMBER_MIN = 1;

  const OXY_PREFETCHES_NUMBER_STATUS_DEFAULT = 'true';
  const OXY_PREFETCHES_HOVER_STATUS_DEFAULT = 'true';

  const OXY_PREFETCH_PRERENDER_NUMBER_STATUS_DEFAULT = 'false';
  const OXY_PREFETCH_PRERENDER_HOVER_STATUS_DEFAULT = 'false';
  const OXY_PREFETCH_PRERENDER_HREF_EXCLUSION_STATUS_DEFAULT = 'false';
  const OXY_PREFETCH_PRERENDER_SELECTOR_EXCLUSION_STATUS_DEFAULT = 'false';

  const OXY_PREFETCH_PRERENDER_EXCLUSION_DEFAULT = array(
    'href' => array(''),
    'selector' => array('')
  );

  public function __construct()
  {
    global $wpdb;
    $this->wpdb = $wpdb;

    // Lang
    add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));

    // Activation
    register_activation_hook(__FILE__, array($this, 'oxy_prefetch_activate'));

    // Menu
    add_action('admin_menu', array($this, 'add_menu'));

    // Assets
    add_action('admin_enqueue_scripts', array($this, 'add_admin_assets'));
    add_action('enqueue_block_editor_assets', array($this, 'add_gutenberg_assets'));

    // Prefetch
    add_action('wp_ajax_save_prefetch_settings', array($this, 'save_prefetch_settings'));
    add_action('save_post', array($this, 'save_prefetch_static_links'), 10, 1);
    add_action('rest_api_init', array($this, 'add_rest_api'));

    // Prerender
    add_action('wp_ajax_dismiss_prerender_notice', array($this, 'dismiss_prerender_notice'));

    // Add Prefetch & Prerender
    add_action('wp_footer', array($this, 'add_speculation_rules_script'));

    // Metabox
    add_action('add_meta_boxes', array($this, 'create_metabox'), 10);

    // Notices
    add_action('admin_notices', array($this, 'admin_notices'));
    add_action('wp_ajax_oxy_prefetch_admin_notices', array($this, 'oxy_prefetch_admin_notices'));
    add_filter('post_updated_messages', array($this, 'post_published'));

    // Settings
    add_filter('plugin_action_links', array($this, 'add_settings'), 10, 3);
  }

  /**
   * @return void
   */
  public function load_plugin_textdomain()
  {
    load_plugin_textdomain('oxy-prefetch', false, basename(dirname(__FILE__)) . '/lang/');
  }

  /**
   * @return void
   */
  public function oxy_prefetch_activate()
  {
    if (self::OXY_PREFETCH_VERSION != get_option('oxy_prefetch_version')) {
      update_option('oxy_prefetch_version', self::OXY_PREFETCH_VERSION, false);
    }
  }

  /**
   * @return void
   */
  public function add_menu()
  {
    add_submenu_page(
      'tools.php',
      'Oxyplug Prefetch & Prerender',
      'Oxyplug Prefetch & Prerender',
      'administrator',
      'oxy-prefetch-settings',
      array($this, 'oxy_prefetch_settings')
    );
  }

  /**
   * @return void
   */
  public function oxy_prefetch_settings()
  {
    $oxy_prefetches_number_status = get_option('oxy_prefetches_number_status', self::OXY_PREFETCHES_NUMBER_STATUS_DEFAULT) == 'true';
    $oxy_prefetches_number = (int)(get_option('oxy_prefetches_number', self::OXY_PREFETCHES_NUMBER_DEFAULT));
    $oxy_prefetch_hover_status = get_option('oxy_prefetch_hover_status', self::OXY_PREFETCHES_HOVER_STATUS_DEFAULT) == 'true';
    $oxy_prefetch_prerender_number_status = get_option('oxy_prefetch_prerender_number_status', self::OXY_PREFETCH_PRERENDER_NUMBER_STATUS_DEFAULT) == 'true';
    $oxy_prefetch_prerender_number = (int)(get_option('oxy_prefetch_prerender_number', self::OXY_PREFETCH_PRERENDER_NUMBER_DEFAULT));
    $oxy_prefetch_prerender_hover_status = get_option('oxy_prefetch_prerender_hover_status', self::OXY_PREFETCH_PRERENDER_HOVER_STATUS_DEFAULT) == 'true';
    $oxy_prefetch_prerender_href_exclusion_status = get_option('oxy_prefetch_prerender_href_exclusion_status', self::OXY_PREFETCH_PRERENDER_HREF_EXCLUSION_STATUS_DEFAULT) == 'true';
    $oxy_prefetch_prerender_selector_exclusion_status = get_option('oxy_prefetch_prerender_selector_exclusion_status', self::OXY_PREFETCH_PRERENDER_SELECTOR_EXCLUSION_STATUS_DEFAULT) == 'true';
    $oxy_prefetch_prerender_match = get_option('oxy_prefetch_prerender_match', self::OXY_PREFETCH_PRERENDER_EXCLUSION_DEFAULT);
    ?>

    <form action="" method="post">
      <h1 class="oxy-prefetch-head-title"><?php _e('Oxyplug Prefetch & Prerender | Settings', 'oxy-prefetch'); ?></h1>
      <div class="oxy-prefetch-each-section">
        <div class="each-part">
          <h2>
            <?php _e('Prefetch', 'oxy-prefetch') ?>
            <i class="dashicons dashicons-editor-help oxy-prefetch-has-tooltip"
               data-tooltip="<?php esc_attr_e('Prefetches only the document of the next page.', 'oxy-prefetch') ?>"
               data-href="https://www.oxyplug.com/docs/oxy-prefetch/settings/?utm_source=plugin-settings&utm_medium=wordpress&utm_campaign=oxy-prefetch#prefetch-settings"
               data-href-text="<?php esc_attr_e('Learn More', 'oxy-prefetch'); ?>"></i>
          </h2>
          <div class="oxy-d-inline-block has-subtext">
            <input class="oxy-d-inline-block"
                   type="checkbox"
                   id="oxy-prefetches-number-status"
                   name="oxy_prefetches_number_status"
                   autocomplete="off"
              <?php if ($oxy_prefetches_number_status) echo esc_attr('checked') ?>>
            <h3 class="oxy-d-inline-block"><?php _e('Number of prefetches (immediate)', 'oxy-prefetch') ?></h3>
            <span
                class="oxy-d-block"><?php _e('The number of links to be prefetched on a category page', 'oxy-prefetch') ?></span>
          </div>
          <input class="oxy-d-block oxy-prefetch-validity"
                 type="number"
                 name="oxy_prefetches_number"
                 value="<?php echo esc_attr($oxy_prefetches_number) ?>"
                 min="1"
                 max="50"
                 autocomplete="off"
            <?php if (!$oxy_prefetches_number_status) echo esc_attr('disabled') ?>>
        </div>

        <div class="each-part">
          <div class="has-subtext oxy-d-inline-block">
            <input class="oxy-d-inline-block"
                   type="checkbox"
                   id="oxy-prefetch-hover-status"
                   name="oxy_prefetch_hover_status"
                   autocomplete="off"
              <?php if ($oxy_prefetch_hover_status) echo esc_attr('checked') ?>>
            <h3 class="oxy-d-inline-block"><?php _e('Prefetch links on mouse hover (moderate)', 'oxy-prefetch') ?></h3>
          </div>
        </div>
      </div>

      <div class="oxy-prefetch-each-section">
        <div class="each-part">
          <h2>
            <?php _e('Prerender', 'oxy-prefetch') ?>
            <i class="dashicons dashicons-editor-help oxy-prefetch-has-tooltip"
               data-tooltip="<?php esc_attr_e('Prerenders the next page completely like an invisible tab.', 'oxy-prefetch') ?>"
               data-href="https://www.oxyplug.com/docs/oxy-prefetch/settings/?utm_source=plugin-settings&utm_medium=wordpress&utm_campaign=oxy-prefetch#prerender-settings"
               data-href-text="<?php esc_attr_e('Learn More', 'oxy-prefetch'); ?>"></i>
          </h2>
          <div class="oxy-d-inline-block has-subtext">
            <input class="oxy-d-inline-block"
                   type="checkbox"
                   id="oxy-prefetch-prerender-status"
                   name="oxy_prefetch_prerender_number_status"
                   autocomplete="off"
              <?php if ($oxy_prefetch_prerender_number_status) echo esc_attr('checked') ?>>
            <h3 class="oxy-d-inline-block"><?php _e('Number of prerenders (immediate)', 'oxy-prefetch') ?></h3>
            <span
                class="oxy-d-block"><?php _e('The number of links to be prerendered on a category page', 'oxy-prefetch') ?></span>
          </div>
          <input class="oxy-d-block oxy-prefetch-validity"
                 type="number"
                 name="oxy_prefetch_prerender_number"
                 value="<?php echo esc_attr($oxy_prefetch_prerender_number) ?>"
                 min="1"
                 max="10"
                 autocomplete="off"
            <?php if (!$oxy_prefetch_prerender_number_status) echo esc_attr('disabled') ?>>
        </div>

        <div class="each-part">
          <div class="has-subtext oxy-d-inline-block">
            <input class="oxy-d-inline-block"
                   type="checkbox"
                   id="oxy-prefetch-prerender-hover-status"
                   name="oxy_prefetch_prerender_hover_status"
                   autocomplete="off"
              <?php if ($oxy_prefetch_prerender_hover_status) echo esc_attr('checked') ?>>
            <h3 class="oxy-d-inline-block"><?php _e('Prerender links on mouse hover (moderate)', 'oxy-prefetch') ?></h3>
          </div>
        </div>

        <input type="hidden" name="oxy_prefetch_settings_nonce"
               value="<?php echo esc_attr(wp_create_nonce('save_prefetch_settings')); ?>"/>
      </div>

      <div class="oxy-prefetch-each-section">
        <div class="each-part">
          <h2>
            <?php _e('Exclusion', 'oxy-prefetch') ?>
            <small class="oxy-prefetch-smaller"><?php _e('(Only for moderate)', 'oxy-prefetch') ?></small>
            <i class="dashicons dashicons-editor-help oxy-prefetch-has-tooltip"
               data-tooltip="<?php esc_attr_e('Prerenders the next page completely like an invisible tab.', 'oxy-prefetch') ?>"
               data-href="https://www.oxyplug.com/docs/oxy-prefetch/settings/?utm_source=plugin-settings&utm_medium=wordpress&utm_campaign=oxy-prefetch#exclusion-settings"
               data-href-text="<?php esc_attr_e('Learn More', 'oxy-prefetch'); ?>"></i>
          </h2>
          <div class="oxy-d-block has-subtext">
            <input class="oxy-d-inline-block"
                   type="checkbox"
                   id="oxy-prefetch-prerender-href-exclusion-status"
                   name="oxy_prefetch_prerender_href_exclusion_status"
                   autocomplete="off"
              <?php if ($oxy_prefetch_prerender_href_exclusion_status) echo esc_attr('checked') ?>>
            <h3 class="oxy-d-inline-block"><?php _e('href exclusion (RegEx allowed)', 'oxy-prefetch') ?></h3>
          </div>

          <div id="oxy-prefetch-prerender-href-exclusion-warp">
            <div id="oxy-prefetch-prerender-href-exclusion-inputs">

              <?php foreach ($oxy_prefetch_prerender_match['href'] as $index => $href): ?>
                <div class="oxy-d-block">
                  <input class="oxy-d-inline-block"
                         type="text"
                         name="oxy_prefetch_prerender_matches[href][]"
                         value="<?php echo esc_attr($href) ?>"
                         autocomplete="off"
                         placeholder="<?php echo esc_attr('/logout'); ?>"
                    <?php if (!$oxy_prefetch_prerender_href_exclusion_status) echo esc_attr('disabled'); ?>>

                  <?php if ($index > 0): ?>
                    <button class="button oxy-prefetch-button-danger-outline oxy-prefetch-remove-exclusion"
                      <?php if (!$oxy_prefetch_prerender_href_exclusion_status) echo esc_attr('disabled'); ?>>
                      <i class="dashicons dashicons-trash"></i>
                    </button>
                  <?php endif; ?>

                </div>
              <?php endforeach; ?>
            </div>

            <button class="button button-large oxy-prefetch-add-exclusion"
              <?php if (!$oxy_prefetch_prerender_href_exclusion_status) echo esc_attr('disabled'); ?>>
              <i class="dashicons dashicons-plus"></i>
              <?php esc_html_e('Add More', 'oxy-prefetch'); ?>
            </button>
          </div>

        </div>

        <div class="each-part">
          <div class="oxy-d-block has-subtext">
            <input class="oxy-d-inline-block"
                   type="checkbox"
                   id="oxy-prefetch-prerender-selector-exclusion-status"
                   name="oxy_prefetch_prerender_selector_exclusion_status"
                   autocomplete="off"
              <?php if ($oxy_prefetch_prerender_selector_exclusion_status) echo esc_attr('checked') ?>>
            <h3 class="oxy-d-inline-block"><?php _e('Selector exclusion', 'oxy-prefetch') ?></h3>
          </div>

          <div id="oxy-prefetch-prerender-selector-exclusion-warp">
            <div id="oxy-prefetch-prerender-selector-exclusion-inputs">
              <?php foreach ($oxy_prefetch_prerender_match['selector'] as $index => $selector): ?>
                <div class="oxy-d-block">
                  <input class="oxy-d-inline-block"
                         type="text"
                         name="oxy_prefetch_prerender_matches[selector][]"
                         value="<?php echo esc_attr($selector) ?>"
                         autocomplete="off"
                         placeholder="<?php echo esc_attr('.no-prerender'); ?>"
                    <?php if (!$oxy_prefetch_prerender_selector_exclusion_status) echo esc_attr('disabled'); ?>>

                  <?php if ($index > 0): ?>
                    <button class="button oxy-prefetch-button-danger-outline oxy-prefetch-remove-exclusion"
                      <?php if (!$oxy_prefetch_prerender_selector_exclusion_status) echo esc_attr('disabled'); ?>>
                      <i class="dashicons dashicons-trash"></i>
                    </button>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>

            <button class="button button-large oxy-prefetch-add-exclusion"
              <?php if (!$oxy_prefetch_prerender_selector_exclusion_status) echo esc_attr('disabled'); ?>>
              <i class="dashicons dashicons-plus"></i>
              <?php esc_html_e('Add More', 'oxy-prefetch'); ?>
            </button>
          </div>

          <div id="oxy-prefetch-selector-exclusion-explanation">
            <small
                class="oxy-d-block"><?php _e("Exclude links within a section by entering id, class name or any other selectors.", 'oxy-prefetch') ?></small>
            <small class="oxy-d-block"><?php _e("Example: .no-prerender", 'oxy-prefetch') ?></small>
          </div>
        </div>
      </div>

      <button id="oxy-prefetch-settings-save"
              class="button button-primary button-large"><?php _e('Save', 'oxy-prefetch'); ?></button>
    </form>
    <?php
  }

  /**
   * @return void
   */
  public function add_admin_assets()
  {
    wp_register_script('oxy-prefetch-admin-script', plugins_url('assets/js/admin-script.js', __FILE__), array('jquery'), self::OXY_PREFETCH_VERSION);
    wp_enqueue_script('oxy-prefetch-admin-script');

    wp_register_style('oxy-prefetch-admin-style', plugins_url('assets/css/admin-style.css', __FILE__), array(), self::OXY_PREFETCH_VERSION);
    wp_enqueue_style('oxy-prefetch-admin-style');
  }

  /**
   * @return void
   */
  public function add_gutenberg_assets()
  {
    wp_enqueue_script(
      'oxy-prefetch-gutenberg-script',
      plugins_url('assets/js/gutenberg-script.js', __FILE__),
      array('wp-hooks', 'wp-blocks', 'wp-element', 'wp-editor', 'wp-data'),
      filemtime(plugin_dir_path(__FILE__) . 'assets/js/gutenberg-script.js'),
      true
    );
  }

  /**
   * @param $pre_count
   * @param $type
   * @return void
   */
  private function make_speculation_rules($pre_count, $type)
  {
    global $wp;
    global $wp_query;

    for ($p = 0; $p < $pre_count; $p++) {
      if (isset($wp_query->posts[$p]->ID)) {
        $permalink = get_permalink($wp_query->posts[$p]->ID);
        if ($permalink != home_url($wp->request) . '/') {
          if (!isset($this->already_added[$type][$permalink])) {
            if (!$this->is_excluded_by_default($permalink)) {
              $this->script_immediate[$type][] = $permalink;
            }
            $this->already_added[$type][$permalink] = 1;
          }
        }
      }
    }
  }

  /**
   * @return void
   */
  public function add_speculation_rules_script()
  {
    $this->add_prefetch_script_immediate();
    $this->add_prerender_script_immediate();
    $this->add_prefetch_script_moderate();
    $this->add_prerender_script_moderate();
    $this->add_to_page();
  }

  /**
   * @return void
   */
  private function add_to_page()
  {
    /**
     * Prefetch and Prerender immediately
     */
    if (count($this->script_immediate)) {

      // Prerender
      if (isset($this->script_immediate['prerender'])) {
        $this->script_immediate['prerender'] = array(
          array(
            'urls' => $this->script_immediate['prerender'],
            'eagerness' => 'immediate'
          )
        );
      }

      // Prefetch
      if (isset($this->script_immediate['prefetch'])) {
        $this->script_immediate['prefetch'] = array(
          array(
            'urls' => $this->script_immediate['prefetch'],
            'eagerness' => 'immediate'
          )
        );
      }

      echo
        '<script type="speculationrules">'
        . wp_json_encode($this->script_immediate, JSON_UNESCAPED_SLASHES) .
        '</script>';
    }

    /**
     * Prefetch and Prerender on mouse hover
     */
    if (count($this->script_moderate)) {
      echo
        '<script type="speculationrules">'
        . wp_json_encode($this->script_moderate, JSON_UNESCAPED_SLASHES) .
        '</script>';
    }
  }

  /**
   * @return void
   */
  private function add_prefetch_script_immediate()
  {
    global $wp;
    // Prefetch depending on the oxy_prefetches_number
    $oxy_prefetches_number_status = get_option('oxy_prefetches_number_status', self::OXY_PREFETCHES_NUMBER_STATUS_DEFAULT) == 'true';
    if ($oxy_prefetches_number_status) {
      global $wp_query;
      if ($wp_query && $wp_query->posts) {
        $oxy_prefetches_number = get_option('oxy_prefetches_number', self::OXY_PREFETCHES_NUMBER_DEFAULT);
        $posts_count = count($wp_query->posts);
        if ($posts_count < $oxy_prefetches_number) {
          $oxy_prefetches_number = $posts_count;
        }
        $this->make_speculation_rules($oxy_prefetches_number, 'prefetch');
      }
    }

    // Prefetch depending on the oxy_static_prefetch
    $current_url = home_url($wp->request);
    $post_id = url_to_postid($current_url);
    $prefetch_status = get_post_meta($post_id, '_oxy_prefetch_status', true);

    if ($prefetch_status) {
      $prefetch_urls = get_post_meta($post_id, '_oxy_prefetch_url');
      foreach ($prefetch_urls as $prefetch_url) {
        if (!isset($this->already_added['prefetch'][$prefetch_url])) {
          if (!$this->is_excluded_by_default($prefetch_url)) {
            $this->script_immediate['prefetch'][] = $prefetch_url;
          }
          $this->already_added['prefetch'][$prefetch_url] = 1;
        }
      }
    }
  }

  /**
   * @return void
   */
  private function add_prerender_script_immediate()
  {
    global $wp;
    /**
     * Prerender depending on the oxy_prefetch_prerender_number
     */
    $oxy_prefetch_prerender_number_status = get_option('oxy_prefetch_prerender_number_status', self::OXY_PREFETCH_PRERENDER_NUMBER_STATUS_DEFAULT) == 'true';
    if ($oxy_prefetch_prerender_number_status) {
      global $wp_query;
      if ($wp_query && $wp_query->posts) {
        $oxy_prefetch_prerender_number = get_option('oxy_prefetch_prerender_number', self::OXY_PREFETCH_PRERENDER_NUMBER_DEFAULT);
        $posts_count = count($wp_query->posts);
        if ($posts_count < $oxy_prefetch_prerender_number) {
          $oxy_prefetch_prerender_number = $posts_count;
        }
        $this->make_speculation_rules($oxy_prefetch_prerender_number, 'prerender');
      }
    }

    /**
     * Prerender depending on the oxy_static_prerender
     */
    $current_url = home_url($wp->request);
    $post_id = url_to_postid($current_url);
    $prerender_status = get_post_meta($post_id, '_oxy_prefetch_prerender_status', true);

    if ($prerender_status) {
      $prerender_urls = get_post_meta($post_id, '_oxy_prefetch_prerender_url');
      foreach ($prerender_urls as $prerender_url) {
        if (!isset($this->already_added['prerender'][$prerender_url])) {
          if (!$this->is_excluded_by_default($prerender_url)) {
            $this->script_immediate['prerender'][] = $prerender_url;
          }
          $this->already_added['prerender'][$prerender_url] = 1;
        }
      }
    }
  }

  /**
   * @return void
   */
  private function add_prefetch_script_moderate()
  {
    $oxy_prefetch_hover_status = get_option('oxy_prefetch_hover_status', self::OXY_PREFETCHES_HOVER_STATUS_DEFAULT) == 'true';
    if ($oxy_prefetch_hover_status) {
      $this->script_moderate['prefetch'] = array(
        array(
          'where' => array(),
          'eagerness' => 'moderate'
        )
      );

      // Default Exclusions
      foreach ($this->get_url_exclusions_default() as $exclusion) {
        $this->script_moderate['prefetch'][0]['where']['and'][] = array('not' => array('href_matches' => $exclusion));
      }

      // User Exclusions
      $oxy_prefetch_match = get_option('oxy_prefetch_prerender_match', true);

      // Href
      $oxy_prefetch_prerender_href_exclusion_status = get_option('oxy_prefetch_prerender_href_exclusion_status');
      if ($oxy_prefetch_prerender_href_exclusion_status == 'true') {
        if (!empty($oxy_prefetch_match['href'][0])) {
          foreach ($oxy_prefetch_match['href'] as $href) {
            $this->script_moderate['prefetch'][0]['where']['and'][] = array('not' => array('href_matches' => $href));
          }
        }
      }

      // Selector
      $oxy_prefetch_prerender_selector_exclusion_status = get_option('oxy_prefetch_prerender_selector_exclusion_status');
      if ($oxy_prefetch_prerender_selector_exclusion_status == 'true') {
        if (!empty($oxy_prefetch_match['selector'][0])) {
          foreach ($oxy_prefetch_match['selector'] as $selector) {
            $this->script_moderate['prefetch'][0]['where']['and'][] = array('not' => array('selector_matches' => $selector));
          }
        }
      }

      // Add to "and" if there is any exclusion
      if (empty($this->script_moderate['prefetch'][0]['where']['and'])) {
        $this->script_moderate['prefetch'][0]['where'] = array('href_matches' => '/*');
      } else {
        array_unshift(
          $this->script_moderate['prefetch'][0]['where']['and'],
          array('href_matches' => '/*')
        );
      }
    }
  }

  /**
   * Prerender on mouse hover
   * @return void
   */
  private function add_prerender_script_moderate()
  {
    $oxy_prefetch_prerender_hover_status = get_option('oxy_prefetch_prerender_hover_status', self::OXY_PREFETCH_PRERENDER_HOVER_STATUS_DEFAULT) == 'true';
    if ($oxy_prefetch_prerender_hover_status) {
      $this->script_moderate['prerender'] = array(
        array(
          'where' => array(),
          'eagerness' => 'moderate'
        )
      );

      // Default Exclusions
      foreach ($this->get_url_exclusions_default() as $exclusion) {
        $this->script_moderate['prerender'][0]['where']['and'][] = array('not' => array('href_matches' => $exclusion));
      }

      // User Exclusions
      $oxy_prefetch_prerender_match = get_option('oxy_prefetch_prerender_match', true);

      // Href
      $oxy_prefetch_prerender_href_exclusion_status = get_option('oxy_prefetch_prerender_href_exclusion_status');
      if ($oxy_prefetch_prerender_href_exclusion_status == 'true') {
        if (!empty($oxy_prefetch_prerender_match['href'][0])) {
          foreach ($oxy_prefetch_prerender_match['href'] as $href) {
            $this->script_moderate['prerender'][0]['where']['and'][] = array('not' => array('href_matches' => $href));
          }
        }
      }

      // Selector
      $oxy_prefetch_prerender_selector_exclusion_status = get_option('oxy_prefetch_prerender_selector_exclusion_status');
      if ($oxy_prefetch_prerender_selector_exclusion_status == 'true') {
        if (!empty($oxy_prefetch_prerender_match['selector'][0])) {
          foreach ($oxy_prefetch_prerender_match['selector'] as $selector) {
            $this->script_moderate['prerender'][0]['where']['and'][] = array('not' => array('selector_matches' => $selector));
          }
        }
      }

      // Add to "and" if there is any exclusion
      if (empty($this->script_moderate['prerender'][0]['where']['and'])) {
        $this->script_moderate['prerender'][0]['where'] = array('href_matches' => '/*');
      } else {
        array_unshift(
          $this->script_moderate['prerender'][0]['where']['and'],
          array('href_matches' => '/*')
        );
      }
    }
  }

  /**
   * @return void
   */
  public function dismiss_prerender_notice()
  {
    try {
      if (is_admin() && isset($_POST['oxy_prefetch_dismiss_prerender_notice_nonce'])) {
        if (wp_verify_nonce($_POST['oxy_prefetch_dismiss_prerender_notice_nonce'], 'dismiss_prerender_notice')) {
          update_option('oxy_prefetch_prerender_notice_dismissed', 'true', false);
          wp_send_json_success(array('message' => __('Saved.', 'oxy-prefetch')), 200);
        }

        wp_send_json_error(array('message' => __('Wrong Nonce! Refresh the page.', 'oxy-prefetch')), 500);
      }
    } catch (Exception $e) {
      wp_send_json_error(array('message' => __($e->getMessage(), 'oxy-prefetch')), 500);
    }

    wp_send_json_error(array('message' => __('Error!', 'oxy-prefetch')), 500);
  }

  /**
   * @return void
   */
  public function save_prefetch_settings()
  {
    try {
      if (is_admin() && isset($_POST['oxy_prefetch_settings_nonce'])) {
        if (wp_verify_nonce($_POST['oxy_prefetch_settings_nonce'], 'save_prefetch_settings')) {

          // Prefetches Number Status
          $oxy_prefetches_number_status = isset($_POST['oxy_prefetches_number_status']) ? 'true' : 'false';

          // Prefetches Number
          if ($oxy_prefetches_number_status == 'true') {
            $oxy_prefetches_number = (int)($_POST['oxy_prefetches_number']);

            if ($oxy_prefetches_number > self::OXY_PREFETCHES_NUMBER_MAX) {
              $message = esc_html(sprintf(__('Max number of prefetches is %d.', 'oxy-prefetch'), self::OXY_PREFETCHES_NUMBER_MAX));
              $this->set_errors($message);
              wp_send_json_error(array('message' => $message), 422);
            }

            if ($oxy_prefetches_number < self::OXY_PREFETCHES_NUMBER_MIN) {
              $message = esc_html(sprintf(__('Min number of prefetches is %d.', 'oxy-prefetch'), self::OXY_PREFETCHES_NUMBER_MIN));
              $this->set_errors($message);
              wp_send_json_error(array('message' => $message), 422);
            }
          } else {
            $oxy_prefetches_number = (int)(get_option('oxy_prefetches_number', self::OXY_PREFETCHES_NUMBER_DEFAULT));
          }

          // Prefetch Hover Status
          $oxy_prefetch_hover_status = isset($_POST['oxy_prefetch_hover_status']) ? 'true' : 'false';

          // Prerender Number Status
          $oxy_prefetch_prerender_number_status = isset($_POST['oxy_prefetch_prerender_number_status']) ? 'true' : 'false';

          // Prerender Number
          if ($oxy_prefetch_prerender_number_status == 'true') {
            $oxy_prefetch_prerender_number = (int)($_POST['oxy_prefetch_prerender_number']);

            if ($oxy_prefetch_prerender_number > self::OXY_PREFETCH_PRERENDER_NUMBER_MAX) {
              $message = esc_html(sprintf(__('Max number of prerenders is %d.', 'oxy-prefetch'), self::OXY_PREFETCH_PRERENDER_NUMBER_MAX));
              $this->set_errors($message);
              wp_send_json_error(array('message' => $message), 422);
            }

            if ($oxy_prefetch_prerender_number < self::OXY_PREFETCH_PRERENDER_NUMBER_MIN) {
              $message = esc_html(sprintf(__('Min number of prerenders is %d.', 'oxy-prefetch'), self::OXY_PREFETCH_PRERENDER_NUMBER_MIN));
              $this->set_errors($message);
              wp_send_json_error(array('message' => $message), 422);
            }
          } else {
            $oxy_prefetch_prerender_number = (int)(get_option('oxy_prefetch_prerender_number', self::OXY_PREFETCH_PRERENDER_NUMBER_DEFAULT));
          }

          // Prerender Hover Status
          $oxy_prefetch_prerender_hover_status = isset($_POST['oxy_prefetch_prerender_hover_status']) ? 'true' : 'false';

          // Prerender Exclusion Status (Href)
          $oxy_prefetch_prerender_href_exclusion_status = isset($_POST['oxy_prefetch_prerender_href_exclusion_status']) ? 'true' : 'false';
          $user_href_exclusions = (array)($_POST['oxy_prefetch_prerender_matches']['href'] ?? array());
          $href_exclusions = array();
          foreach ($user_href_exclusions as $href_exclusion) {
            $href_exclusion = sanitize_text_field($href_exclusion);
            $href_exclusion = parse_url($href_exclusion);
            if (!empty($href_exclusion['path'])) {
              if (strpos($href_exclusion['path'], '/') !== 0) {
                $href_exclusion['path'] = '/' . $href_exclusion['path'];
              }
              $href_exclusions[] = $href_exclusion['path'];
            }
          }

          if (count($href_exclusions)) {
            $oxy_prefetch_prerender_match['href'] = $href_exclusions;
          } else {
            $oxy_prefetch_prerender_match['href'] = self::OXY_PREFETCH_PRERENDER_EXCLUSION_DEFAULT['href'];
          }

          // Prerender Exclusion Status (Selector)
          $oxy_prefetch_prerender_selector_exclusion_status = isset($_POST['oxy_prefetch_prerender_selector_exclusion_status']) ? 'true' : 'false';
          $user_selector_exclusions = (array)($_POST['oxy_prefetch_prerender_matches']['selector'] ?? array());
          $selector_exclusions = array();
          foreach ($user_selector_exclusions as $selector_exclusion) {
            $selector_exclusion = sanitize_text_field($selector_exclusion);
            if (!empty($selector_exclusion)) {
              $selector_exclusions[] = $selector_exclusion;
            }
          }
          if (count($selector_exclusions)) {
            $oxy_prefetch_prerender_match['selector'] = $selector_exclusions;
          } else {
            $oxy_prefetch_prerender_match['selector'] = self::OXY_PREFETCH_PRERENDER_EXCLUSION_DEFAULT['selector'];
          }

          update_option('oxy_prefetches_number_status', $oxy_prefetches_number_status, false);
          update_option('oxy_prefetches_number', $oxy_prefetches_number, false);
          update_option('oxy_prefetch_hover_status', $oxy_prefetch_hover_status, false);
          update_option('oxy_prefetch_prerender_number_status', $oxy_prefetch_prerender_number_status, false);
          update_option('oxy_prefetch_prerender_number', $oxy_prefetch_prerender_number, false);
          update_option('oxy_prefetch_prerender_hover_status', $oxy_prefetch_prerender_hover_status, false);
          update_option('oxy_prefetch_prerender_href_exclusion_status', $oxy_prefetch_prerender_href_exclusion_status, false);
          update_option('oxy_prefetch_prerender_selector_exclusion_status', $oxy_prefetch_prerender_selector_exclusion_status, false);
          if (!empty($oxy_prefetch_prerender_match)) {
            update_option('oxy_prefetch_prerender_match', $oxy_prefetch_prerender_match, false);
          }

          $message = __('Saved.', 'oxy-prefetch');
          $this->set_success_messages($message);
          wp_send_json_success(array('message' => $message), 200);
        }

        $message = __('Wrong Nonce! Refresh the page.', 'oxy-prefetch');
      }
    } catch (Exception $e) {
      $message = __($e->getMessage(), 'oxy-prefetch');
    }

    $message = $message ?? __('Error!', 'oxy-prefetch');
    $this->set_errors($message);
    wp_send_json_error(array('message' => $message), 500);
  }

  /**
   * @param $message
   * @return void
   */
  private function set_errors($message = null)
  {
    $message_handler = new WP_Error();
    $message = $message ?? __('Error!', 'oxy-prefetch');
    $message_handler->add('oxy_prefetch_errors', $message);
    set_transient('oxy_prefetch_errors', $message_handler);
  }

  /**
   * @param $message
   * @return void
   */
  private function set_success_messages($message = null)
  {
    $message_handler = new WP_Error();
    $message_handler->add('oxy_prefetch_success_messages', $message);
    set_transient('oxy_prefetch_success_messages', $message_handler);
  }

  /**
   * @param $post_id
   * @return void
   */
  public function save_prefetch_static_links($post_id)
  {
    if (
      (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
      isset($_REQUEST['bulk_edit']) ||
      (!current_user_can('edit_post', $post_id)) ||
      !isset($_POST['oxyplug_prefetch_nonce']) ||
      !wp_verify_nonce($_POST['oxyplug_prefetch_nonce'], basename(__FILE__)) ||
      wp_is_json_request()
    ) {
      return;
    }

    $this->save_links_into_db($post_id);
  }

  /**
   * @return void
   */
  public function add_rest_api()
  {
    register_rest_route('oxy-prefetch/v1', '/save-links/', array(
      'methods' => 'POST',
      'callback' => array($this, 'save_links'),
      'permission_callback' => function () {
        return current_user_can('edit_posts');
      },
    ));
  }

  /**
   * @param WP_REST_Request $request
   * @return void
   */
  public function save_links(WP_REST_Request $request)
  {
    $post_id = intval($request->get_param('post_id'));
    if (!empty($post_id)) {
      $data = $request->get_json_params();
      if (!empty($data['data'])) {
        $_POST = $data['data'];
        if (
          (!(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) &&
          !isset($_REQUEST['bulk_edit']) &&
          (current_user_can('edit_post', $post_id)) &&
          isset($_POST['oxyplug_prefetch_nonce']) &&
          wp_verify_nonce($_POST['oxyplug_prefetch_nonce'], basename(__FILE__))
        ) {
          $this->save_links_into_db($post_id, true);
        }
      }
    }

    wp_send_json_success(null, 204);
  }

  /**
   * @param $post_id
   * @param bool $ajax
   * @return void
   */
  private function save_links_into_db($post_id, bool $ajax = false)
  {
    $errors = new WP_Error('oxy_prefetch_errors', esc_html__('Oops..something went wrong:', 'oxy-prefetch'));

    // Prefetch
    if (isset($_POST['oxy_prefetch_status'])) {
      if (isset($_POST['oxy_static_prefetch']) && is_array($_POST['oxy_static_prefetch'])) {
        $prefetch_urls = array();
        foreach ($_POST['oxy_static_prefetch'] as $oxy_static_prefetch) {
          if (isset($oxy_static_prefetch['prefetch_these'])) {
            $prefetch_these = trim($oxy_static_prefetch['prefetch_these']);

            if (!empty($prefetch_these)) {
              $url_parsed = parse_url($prefetch_these);
              if (!isset($url_parsed['scheme']) || !isset($url_parsed['host'])) {
                $message = esc_html__('The prefetch url is invalid.', 'oxy-prefetch');
                if ($ajax) {
                  wp_send_json_error(array('message' => $message), 422);
                }
                $errors->add('oxy_prefetch_errors', $message);
                break;
              }

              if (!$this->is_url_correct($url_parsed)) {
                $message = esc_html__('The prefetch url must be in your host.', 'oxy-prefetch');
                if ($ajax) {
                  wp_send_json_error(array('message' => $message), 422);
                }
                $errors->add('oxy_prefetch_errors', $message);
                break;
              }

              if (!isset($prefetch_urls[$prefetch_these])) {
                $prefetch_urls[$prefetch_these] = rtrim($prefetch_these, '/') . '/';
              }

            }
          }
        }

        if (count($errors->get_error_messages()) > 1) {
          $this->wpdb->update($this->wpdb->posts, array('post_status' => 'pending'), array('ID' => $post_id));
          set_transient('oxy_prefetch_errors', $errors);
        } elseif (count($prefetch_urls)) {
          update_post_meta($post_id, '_oxy_prefetch_status', 1);
          delete_post_meta($post_id, '_oxy_prefetch_url');
          foreach ($prefetch_urls as $prefetch_url) {
            add_post_meta($post_id, '_oxy_prefetch_url', $prefetch_url);
          }
        }
      }
    } else {
      update_post_meta($post_id, '_oxy_prefetch_status', 0);
    }

    // Prerender
    if (isset($_POST['oxy_prefetch_prerender_status'])) {
      if (isset($_POST['oxy_static_prerender']) && is_array($_POST['oxy_static_prerender'])) {
        $prerender_urls = array();
        foreach ($_POST['oxy_static_prerender'] as $oxy_static_prerender) {
          if (isset($oxy_static_prerender['prerender_these'])) {
            $prerender_these = trim($oxy_static_prerender['prerender_these']);

            if (!empty($prerender_these)) {
              $url_parsed = parse_url($prerender_these);
              if (!isset($url_parsed['scheme']) || !isset($url_parsed['host'])) {
                $message = esc_html__('The prerender url is invalid.', 'oxy-prefetch');
                if ($ajax) {
                  wp_send_json_error(array('message' => $message), 422);
                }
                $errors->add('oxy_prefetch_errors', $message);
                break;
              }

              if (!$this->is_url_correct($url_parsed)) {
                $message = esc_html__('The prerender url must be in your host.', 'oxy-prefetch');
                if ($ajax) {
                  wp_send_json_error(array('message' => $message), 422);
                }
                $errors->add('oxy_prefetch_errors', $message);
                break;
              }

              if (!isset($prerender_urls[$prerender_these])) {
                $prerender_urls[$prerender_these] = rtrim($prerender_these, '/') . '/';
              }
            }
          }
        }

        if (count($errors->get_error_messages()) > 1) {
          $this->wpdb->update($this->wpdb->posts, array('post_status' => 'pending'), array('ID' => $post_id));
          set_transient('oxy_prefetch_errors', $errors);
        } elseif (count($prerender_urls)) {
          update_post_meta($post_id, '_oxy_prefetch_prerender_status', 1);
          delete_post_meta($post_id, '_oxy_prefetch_prerender_url');
          foreach ($prerender_urls as $prefetch_url) {
            add_post_meta($post_id, '_oxy_prefetch_prerender_url', $prefetch_url);
          }
        }
      }
    } else {
      update_post_meta($post_id, '_oxy_prefetch_prerender_status', 0);
    }

    if ($ajax) {
      wp_send_json_success(array('message' => esc_html__('Successfully updated.', 'oxy-prefetch')), 200);
    }
  }

  /**
   * @return void
   */
  public function create_metabox()
  {
    add_meta_box(
      'oxy_prefetch_metabox',
      __('Oxyplug Prefetch & Prerender', 'oxy-prefetch'),
      array($this, 'render_metabox'),
      array('post', 'product', 'page'),
      'normal', // normal: main column | side: sidebar
      'default' // high | core | default | low
    );
  }

  /**
   * @param $post
   * @return void
   */
  public function render_metabox($post)
  {
    wp_nonce_field(basename(__FILE__), 'oxyplug_prefetch_nonce');
    $prefetch_status = get_post_meta($post->ID, '_oxy_prefetch_status', true);
    $prefetch_urls = get_post_meta($post->ID, '_oxy_prefetch_url');
    $prerender_status = get_post_meta($post->ID, '_oxy_prefetch_prerender_status', true);
    $prerender_urls = get_post_meta($post->ID, '_oxy_prefetch_prerender_url');
    $on = __('ON', 'oxy-prefetch');
    $off = __('OFF', 'oxy-prefetch');
    $off_on = is_rtl() ? array($on, $off) : array($off, $on);
    ?>

    <div class="oxy-prefetch-statics-wrap">
      <strong><?php _e("Prerender", 'oxy-prefetch') ?></strong>
      <i class="dashicons dashicons-editor-help oxy-prefetch-has-tooltip"
         data-tooltip="<?php esc_attr_e('Prerenders the next page completely like an invisible tab.', 'oxy-prefetch') ?>"
         data-href="https://www.oxyplug.com/docs/oxy-prefetch/settings/?utm_source=post-settings&utm_medium=wordpress&utm_campaign=oxy-prefetch#prerender-prefetch-edit-page"
         data-href-text="<?php esc_attr_e('Learn More', 'oxy-prefetch'); ?>"></i>
      <div class="oxy-prefetch-switcher">
        <span><?php _e($off_on[0], 'oxy-prefetch') ?></span>
        <label class="switch">
          <input type="checkbox"
                 autocomplete="off"
                 name="oxy_prefetch_prerender_status"
            <?php echo ($prerender_status ? 'checked' : ''); ?>>
          <span class="slider round"></span>
        </label>
        <span><?php _e($off_on[1], 'oxy-prefetch') ?></span>
      </div>
      <h3>
        <?php _e("Prerender the link(s) below on:", 'oxy-prefetch') ?>
        <strong><?php _e(urldecode(get_permalink($post))) ?></strong>
      </h3>
      <?php if (count($prerender_urls)): $oxy_i = 0; ?>
        <?php foreach ($prerender_urls as $prerender_url): ?>
          <div>
            <input type="text"
                   name="<?php echo esc_attr('oxy_static_prerender[' . $oxy_i++ . '][prerender_these]') ?>"
                   value="<?php _e($prerender_url) ?>"
                   placeholder="<?php echo esc_attr(sprintf(__("e.g. %s", 'oxy-prefetch'), home_url('post-x'))) ?>"
                   autocomplete="off"
              <?php echo ($prerender_status ? '' : 'disabled'); ?>>
            <button class="oxy-delete-parent button oxy-prefetch-button-danger-outline"
                    type="button"
              <?php if ($oxy_i == 1) echo "style=display:none" ?>
              <?php echo ($prerender_status ? '' : 'disabled'); ?>>
              <i class="dashicons dashicons-trash"></i>
            </button>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div>
          <input type="text"
                 name="<?php echo esc_attr('oxy_static_prerender[0][prerender_these]') ?>"
                 placeholder="<?php echo esc_attr(sprintf(__("e.g. %s", 'oxy-prefetch'), home_url('post-x'))) ?>"
                 autocomplete="off"
            <?php echo ($prerender_status ? '' : 'disabled'); ?>>
          <button class="oxy-delete-parent button oxy-prefetch-button-danger-outline" type="button"
                  style="display:none">
            <i class="dashicons dashicons-trash"></i>
          </button>
        </div>
      <?php endif; ?>
      <button class="button button-large oxy-add-more-static-urls"
        <?php echo ($prerender_status ? '' : 'disabled'); ?>>
        <i class="dashicons dashicons-plus"></i>
        <?php _e('Add More', 'oxy-prefetch') ?>
      </button>
    </div>

    <div class="oxy-prefetch-statics-wrap">
      <strong><?php _e("Prefetch", 'oxy-prefetch') ?></strong>
      <i class="dashicons dashicons-editor-help oxy-prefetch-has-tooltip"
         data-tooltip="<?php esc_attr_e('Prefetches only the document of the next page.', 'oxy-prefetch') ?>"
         data-href="https://www.oxyplug.com/docs/oxy-prefetch/settings/?utm_source=post-settings&utm_medium=wordpress&utm_campaign=oxy-prefetch#prerender-prefetch-edit-page"
         data-href-text="<?php esc_attr_e('Learn More', 'oxy-prefetch'); ?>"></i>
      <div class="oxy-prefetch-switcher">
        <span><?php _e($off_on[0], 'oxy-prefetch') ?></span>
        <label class="switch">
          <input type="checkbox"
                 autocomplete="off"
                 name="oxy_prefetch_status"
            <?php echo ($prefetch_status ? 'checked' : ''); ?>>
          <span class="slider round"></span>
        </label>
        <span><?php _e($off_on[1], 'oxy-prefetch') ?></span>
      </div>
      <h3>
        <?php _e("Prefetch the link(s) below on:", 'oxy-prefetch') ?>
        <strong><?php _e(urldecode(get_permalink($post))) ?></strong>
      </h3>
      <?php if (count($prefetch_urls)): $oxy_i = 0; ?>
        <?php foreach ($prefetch_urls as $prefetch_url): ?>
          <div>
            <input type="text"
                   name="<?php echo esc_attr('oxy_static_prefetch[' . $oxy_i++ . '][prefetch_these]') ?>"
                   value="<?php _e($prefetch_url) ?>"
                   placeholder="<?php echo esc_attr(sprintf(__("e.g. %s", 'oxy-prefetch'), home_url('post-x'))) ?>"
                   autocomplete="off"
              <?php echo ($prefetch_status ? '' : 'disabled'); ?>>
            <button class="oxy-delete-parent button oxy-prefetch-button-danger-outline"
                    type="button"
              <?php if ($oxy_i == 1) echo "style=display:none" ?>
              <?php echo ($prefetch_status ? '' : 'disabled'); ?>>
              <i class="dashicons dashicons-trash"></i>
            </button>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div>
          <input type="text"
                 name="<?php echo esc_attr('oxy_static_prefetch[0][prefetch_these]') ?>"
                 placeholder="<?php echo esc_attr(sprintf(__("e.g. %s", 'oxy-prefetch'), home_url('post-x'))) ?>"
                 autocomplete="off"
            <?php echo ($prefetch_status ? '' : 'disabled'); ?>>
          <button class="oxy-delete-parent button oxy-prefetch-button-danger-outline" type="button"
                  style="display:none">
            <i class="dashicons dashicons-trash"></i>
          </button>
        </div>
      <?php endif; ?>
      <button class="button button-large oxy-add-more-static-urls"
        <?php echo ($prefetch_status ? '' : 'disabled'); ?>>
        <i class="dashicons dashicons-plus"></i>
        <?php _e('Add More', 'oxy-prefetch') ?>
      </button>
    </div>

    <?php
  }

  /**
   * @return void
   */
  public function admin_notices()
  {
    if ($errors = get_transient('oxy_prefetch_errors')) { ?>
      <div class="notice error is-dismissible oxy-prefetch-notice">
      <?php foreach ($errors->get_error_messages() as $error): ?>
        <p><?php _e($error) ?></p>
      <?php endforeach; ?>
      </div><?php

      delete_transient('oxy_prefetch_errors');
    }

    if ($success_messages = get_transient('oxy_prefetch_success_messages')) { ?>
      <div class="notice notice-success is-dismissible oxy-prefetch-notice">
      <?php foreach ($success_messages->get_error_messages() as $success_message): ?>
        <p><?php _e($success_message) ?></p>
      <?php endforeach; ?>
      </div><?php

      delete_transient('oxy_prefetch_success_messages');
    }

    $oxy_prefetch_prerender_number_status = get_option('oxy_prefetch_prerender_number_status', self::OXY_PREFETCH_PRERENDER_NUMBER_STATUS_DEFAULT) == 'true';
    $oxy_prefetch_prerender_hover_status = get_option('oxy_prefetch_prerender_hover_status', self::OXY_PREFETCH_PRERENDER_HOVER_STATUS_DEFAULT) == 'true';
    $oxy_prefetch_prerender_notice_dismissed = get_option('oxy_prefetch_prerender_notice_dismissed') == 'true';
    if (
      !$oxy_prefetch_prerender_number_status &&
      !$oxy_prefetch_prerender_hover_status &&
      !$oxy_prefetch_prerender_notice_dismissed
    ) { ?>
      <div class="notice notice-warning is-dismissible oxy-prefetch-notice">
        <p>
          <?php
          $settings_page = admin_url('tools.php?page=oxy-prefetch-settings');
          printf(__('You have not enabled prerender in Oxyplug Prefetch & Prerender %s.', 'oxy-prefetch'), '<a href="' . $settings_page . '">' . __('settings', 'oxy-prefetch') . '</a>');
          ?>
          <br><br>
          <button id="oxy-prefetch-dismiss-prerender-notice"
                  data-nonce="<?php echo esc_attr(wp_create_nonce('dismiss_prerender_notice')); ?>"
                  class="button"><?php esc_html_e('Do not show this message again', 'oxy-prefetch') ?></button>
        </p>
      </div>
    <?php }
  }

  /**
   * @return void
   */
  public function oxy_prefetch_admin_notices()
  {
    if ($errors = get_transient('oxy_prefetch_errors')) {
      $error_messages = array();
      foreach ($errors->get_error_messages() as $error) {
        $error_messages[] = $error;
      }

      delete_transient('oxy_prefetch_errors');

      wp_send_json_error($error_messages, 422);
    }

    wp_send_json_success(null, 204);
  }

  /**
   * @param $messages
   * @return array|mixed
   */
  public function post_published($messages)
  {
    if (get_transient('oxy_prefetch_errors')) {
      return array();
    }

    return $messages;
  }

  /**
   * @param $actions
   * @param $plugin_file
   * @param $plugin_data
   * @return mixed
   */
  public function add_settings($actions, $plugin_file, $plugin_data)
  {
    if (isset($plugin_data['slug']) && $plugin_data['slug'] == 'oxyplug-prefetch') {
      $href = admin_url('tools.php?page=oxy-prefetch-settings');

      $actions['Settings'] = '<a href="' . $href . '">' . __('Settings', 'oxy-prefetch') . '</a>';
    }

    return $actions;
  }

  /**
   * @param $url_parsed
   * @return bool
   */
  private function is_url_correct($url_parsed): bool
  {
    $home_parsed = parse_url(get_home_url());
    if ($url_parsed['host'] != $home_parsed['host']) {
      $home_host = $this->get_domain($home_parsed['host']);
      $url_host = $this->get_domain($url_parsed['host']);

      return $home_host == $url_host;
    }

    return true;
  }

  /**
   * @param $url
   * @return string
   */
  private function get_domain($url): string
  {
    $url = explode('.', $url);
    unset($url[0]);
    return implode('.', $url);
  }

  /**
   * @return array
   */
  private function get_url_exclusions_default(): array
  {
    $oxy_prefetch_default_exclusions = array();
    if (class_exists('WooCommerce')) {
      $cart_url = rtrim(wc_get_cart_url(), '/');
      $oxy_prefetch_default_exclusions = array($cart_url);
    }

    return $oxy_prefetch_default_exclusions;
  }

  /**
   * @return string[]
   */
  private function get_query_string_exclusions_default(): array
  {
    return array('add-to-cart');
  }

  /**
   * @param $permalink
   * @return bool
   */
  private function is_excluded_by_default($permalink): bool
  {
    // URLs excluded
    foreach ($this->get_url_exclusions_default() as $exclusion) {
      if (str_starts_with($permalink, $exclusion)) {
        return true;
      }
    }

    // Query strings excluded
    $url_parsed = parse_url($permalink);
    if (isset($url_parsed['query'])) {
      mb_parse_str($url_parsed['query'], $string_parsed);
      foreach ($this->get_query_string_exclusions_default() as $exclusion) {
        if (isset($string_parsed[$exclusion])) {
          return true;
        }
      }
    }

    return false;
  }
}

new OxyPrefetch();
