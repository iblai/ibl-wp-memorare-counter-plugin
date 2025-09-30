<?php
/**
 * Plugin Name: IBL WP Memorare Counter
 * Description: Post visit counter with strict security measures.
 * Version: 0.4
 * Author: ibl.ai
 * Text Domain: ibl-wp-memorare-counter
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

final class IBL_WP_Memorare_Counter {
    private static $instance = null;
    private $meta_key = '_iblmemorare_views';
    private $cookie_prefix = 'iblmemorare_viewed_';

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
    }
    
    public function enqueue_scripts() {
        if (!is_singular('post')) return;
        
        $api_url = rest_url('iblmemorare/v1/track');
        
        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', sprintf('
            jQuery(document).ready(function($) {
                var postId = %d;
                var apiUrl = "%s";
                var cookieName = "%s";
                var nonce = "%s";
                
                console.log("IBL Counter - Post ID:", postId);
                console.log("IBL Counter - API URL:", apiUrl);
                
                // Check if already viewed
                if (document.cookie.indexOf(cookieName) !== -1) {
                    console.log("IBL Counter - Already viewed");
                    return;
                }
                
                // Track view
                $.ajax({
                    url: apiUrl,
                    method: "POST",
                    data: { post_id: postId },
                    headers: {
                        "X-WP-Nonce": nonce
                    },
                    success: function(response) {
                        console.log("IBL Counter - Response:", response);
                        if (response.success) {
                            // Set cookie for 12 hours
                            var expires = new Date();
                            expires.setTime(expires.getTime() + (12 * 60 * 60 * 1000));
                            document.cookie = cookieName + "=1; expires=" + expires.toUTCString() + "; path=/";
                            console.log("IBL Counter - Counted:", response.views);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log("IBL Counter - Error:", error);
                    }
                });
            });
        ', get_the_ID(), esc_js($api_url), esc_js($this->cookie_prefix . get_the_ID()), wp_create_nonce('wp_rest')));
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
            return new WP_REST_Response(array('error' => 'Invalid nonce'), 401);
        }

        $post_id = intval($request->get_param('post_id'));
        if ($post_id <= 0) {
            return new WP_REST_Response(array('error' => 'Invalid post ID'), 400);
        }

        if (!get_post_status($post_id)) {
            return new WP_REST_Response(array('error' => 'Post does not exist'), 404);
        }

        // Simple bot detection
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
            if (strpos($ua, 'bot') !== false || strpos($ua, 'crawl') !== false) {
                return new WP_REST_Response(array('success' => false, 'reason' => 'bot'), 200);
            }
        }

        // Simple rate limiting
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $transient_key = 'iblmem_rl_' . md5($ip . $post_id);
        if (get_transient($transient_key)) {
            return new WP_REST_Response(array('success' => false, 'reason' => 'rate_limited'), 200);
        }

        // Check cookie
        $cookie_name = $this->cookie_prefix . $post_id;
        if (isset($_COOKIE[$cookie_name])) {
            return new WP_REST_Response(array('success' => false, 'reason' => 'cookie_present'), 200);
        }

        // Count view
        $views = intval(get_post_meta($post_id, $this->meta_key, true));
        $views++;
        update_post_meta($post_id, $this->meta_key, $views);

        // Set rate limit
        set_transient($transient_key, 1, 30 * MINUTE_IN_SECONDS);

        return new WP_REST_Response(array('success' => true, 'views' => $views), 200);
    }


    public function add_views_column($columns) {
        $columns['iblmemorare_views'] = 'Count';
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
