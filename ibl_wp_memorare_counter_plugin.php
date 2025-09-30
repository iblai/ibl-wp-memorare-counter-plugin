<?php
/**
 * Plugin Name: IBL WP Memorare Counter
 * Description: Post visit counter for WordPress.
 * Version: 1.0-beta
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
        
        register_rest_route('iblmemorare/v1', '/posts/ibl-mostread', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_mostread_posts'),
            'permission_callback' => '__return_true',
            'args' => array(
                'limit' => array(
                    'default' => 10,
                    'sanitize_callback' => 'absint',
                ),
                'days' => array(
                    'default' => 30,
                    'sanitize_callback' => 'absint',
                ),
                'post_type' => array(
                    'default' => 'post',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'category' => array(
                    'default' => '',
                    'sanitize_callback' => 'absint',
                ),
            ),
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

    public function rest_get_mostread_posts($request) {
        $limit = $request->get_param('limit');
        $days = $request->get_param('days');
        $post_type = $request->get_param('post_type');
        $category = $request->get_param('category');
        
        // Validate parameters
        if ($limit < 1 || $limit > 100) {
            $limit = 10;
        }
        
        if ($days < 1 || $days > 365) {
            $days = 30;
        }
        
        // Calculate date range
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Query for most read posts - first get posts with views
        $args_with_views = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_key' => $this->meta_key,
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'date_query' => array(
                array(
                    'after' => $date_from,
                    'inclusive' => true,
                ),
            ),
            'meta_query' => array(
                array(
                    'key' => $this->meta_key,
                    'value' => 0,
                    'compare' => '>',
                ),
            ),
        );
        
        // Add category filter if specified
        if (!empty($category) && $category > 0) {
            $args_with_views['cat'] = $category;
        }
        
        $query_with_views = new WP_Query($args_with_views);
        $posts_with_views = array();
        $post_ids_with_views = array();
        
        if ($query_with_views->have_posts()) {
            while ($query_with_views->have_posts()) {
                $query_with_views->the_post();
                $post_id = get_the_ID();
                $post_ids_with_views[] = $post_id;
                $views = intval(get_post_meta($post_id, $this->meta_key, true));
                
                $thumbnail_id = get_post_thumbnail_id($post_id);
                $thumbnail_url = '';
                if ($thumbnail_id) {
                    $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'medium');
                }
                
                $posts_with_views[] = array(
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'excerpt' => get_the_excerpt(),
                    'date' => get_the_date('Y-m-d'),
                    'url' => get_permalink(),
                    'views' => $views,
                    'thumbnail' => $thumbnail_url,
                );
            }
            wp_reset_postdata();
        }
        
        // If we need more posts, get posts without views
        $remaining_limit = $limit - count($posts_with_views);
        $posts_without_views = array();
        
        if ($remaining_limit > 0) {
            $args_without_views = array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => $remaining_limit,
                'orderby' => 'date',
                'order' => 'DESC',
                'date_query' => array(
                    array(
                        'after' => $date_from,
                        'inclusive' => true,
                    ),
                ),
                'meta_query' => array(
                    array(
                        'key' => $this->meta_key,
                        'compare' => 'NOT EXISTS',
                    ),
                ),
            );
            
            // Add category filter if specified
            if (!empty($category) && $category > 0) {
                $args_without_views['cat'] = $category;
            }
            
            // Exclude posts that already have views
            if (!empty($post_ids_with_views)) {
                $args_without_views['post__not_in'] = $post_ids_with_views;
            }
            
            $query_without_views = new WP_Query($args_without_views);
            
            if ($query_without_views->have_posts()) {
                while ($query_without_views->have_posts()) {
                    $query_without_views->the_post();
                    $post_id = get_the_ID();
                    
                    $thumbnail_id = get_post_thumbnail_id($post_id);
                    $thumbnail_url = '';
                    if ($thumbnail_id) {
                        $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'medium');
                    }
                    
                    $posts_without_views[] = array(
                        'id' => $post_id,
                        'title' => get_the_title(),
                        'excerpt' => get_the_excerpt(),
                        'date' => get_the_date('Y-m-d'),
                        'url' => get_permalink(),
                        'views' => 0,
                        'thumbnail' => $thumbnail_url,
                    );
                }
                wp_reset_postdata();
            }
        }
        
        // Combine both arrays
        $posts = array_merge($posts_with_views, $posts_without_views);
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => $posts,
            'total' => count($posts),
            'limit' => $limit,
            'days' => $days,
            'category' => $category,
        ), 200);
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
