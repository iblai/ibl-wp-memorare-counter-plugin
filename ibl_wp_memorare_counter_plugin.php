<?php
/**
 * Plugin Name: IBL WP Memorare Counter
 * Description: Post visit counter with strict security measures.
 * Version: 1.2
 * Author: Your Name
 * Text Domain: ibl-wp-memorare-counter
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

final class IBL_WP_Memorare_Counter {
    private static $instance = null;
    private $meta_key = '_iblmemorare_views';
    private $cookie_prefix = 'iblmemorare_viewed_';
    private $cookie_hours = 12; // Cookie valid for 12 hours

    private function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        load_plugin_textdomain('ibl-wp-memorare-counter', false, dirname(plugin_basename(__FILE__)) . '/languages');

        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        add_filter('manage_post_posts_columns', array($this, 'add_views_column'));
        add_action('manage_post_posts_custom_column', array($this, 'render_views_column'), 10, 2);

        add_shortcode('iblmemorare_counter', array($this, 'shortcode_views'));
        add_filter('the_content', array($this, 'maybe_append_views'));
    }

    public function activate() {}
    public function deactivate() {}

    public function register_rest_routes() {
        register_rest_route('iblmemorare/v1', '/track', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_track_view'),
            'permission_callback' => '__return_true',
        ));
    }

    public function rest_track_view($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_REST_Response(array('error' => __('Invalid nonce', 'ibl-wp-memorare-counter')), 401);
        }

        $post_id = intval($request->get_param('post_id'));
        if ($post_id <= 0) {
            return new WP_REST_Response(array('error' => __('Invalid post ID', 'ibl-wp-memorare-counter')), 400);
        }

        if (!get_post_status($post_id)) {
            return new WP_REST_Response(array('error' => __('Post does not exist', 'ibl-wp-memorare-counter')), 404);
        }

        if ($this->is_bot()) {
            return new WP_REST_Response(array('success' => false, 'reason' => 'bot_detected'), 200);
        }

        $ip_hash = $this->get_ip_hash();
        if (!$ip_hash) {
            return new WP_REST_Response(array('error' => __('Could not determine IP', 'ibl-wp-memorare-counter')), 400);
        }

        $transient_key = sprintf('iblmem_rl_%s_%d', $ip_hash, $post_id);
        if (get_transient($transient_key)) {
            return new WP_REST_Response(array('success' => false, 'reason' => 'rate_limited'), 200);
        }

        $cookie_name = $this->cookie_prefix . $post_id;
        if (isset($_COOKIE[$cookie_name])) {
            return new WP_REST_Response(array('success' => false, 'reason' => 'cookie_present'), 200);
        }

        $views = intval(get_post_meta($post_id, $this->meta_key, true));
        $views++;
        update_post_meta($post_id, $this->meta_key, $views);

        set_transient($transient_key, 1, 30 * MINUTE_IN_SECONDS);

        return new WP_REST_Response(array('success' => true, 'views' => $views), 200);
    }

    private function is_bot() {
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = strtolower(wp_unslash($_SERVER['HTTP_USER_AGENT']));
            $bot_signatures = array('bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'bingpreview', 'mediapartners-google');
            foreach ($bot_signatures as $sig) {
                if (strpos($ua, $sig) !== false) return true;
            }
        }
        return false;
    }

    private function get_ip_hash() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim(reset($parts));
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        if (empty($ip)) return false;
        return substr(sha1($ip . wp_salt('auth')), 0, 16);
    }

    public function add_views_column($columns) {
        $columns['iblmemorare_views'] = __('Views', 'ibl-wp-memorare-counter');
        return $columns;
    }

    public function render_views_column($column, $post_id) {
        if ($column !== 'iblmemorare_views') return;
        if (!current_user_can('edit_posts')) {
            echo '—';
            return;
        }
        $views = intval(get_post_meta($post_id, $this->meta_key, true));
        echo esc_html($views);
    }

    public function shortcode_views($atts) {
        global $post;
        if (empty($post) || empty($post->ID)) return '';
        $views = intval(get_post_meta($post->ID, $this->meta_key, true));
        return '<span class="iblmemorare-views">' . esc_html($views) . ' ' . esc_html__('views', 'ibl-wp-memorare-counter') . '</span>';
    }

    public function maybe_append_views($content) {
        if (!is_singular('post') || !in_the_loop() || !is_main_query()) return $content;
        $views = intval(get_post_meta(get_the_ID(), $this->meta_key, true));
        $html = '<div class="iblmemorare-views-wrapper" aria-hidden="true">' . esc_html(sprintf(__('%d views', 'ibl-wp-memorare-counter'), $views)) . '</div>';
        return $content . $html;
    }
}

IBL_WP_Memorare_Counter::instance();
