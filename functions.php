<?php

/**
 * iro functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package iro
 */

include_once('inc/classes/IpLocation.php');

define('IRO_VERSION', wp_get_theme()->get('Version'));
define('BUILD_VERSION', '3');
define('INT_VERSION', '20.0.11');
define('SSU_URL', 'https://api.fuukei.org/update/ssu.json');
define('SAKURAIRO_VISION_BASE_URL', 'https://cdn.gloxina.com/sakurairo_vision/@3.0/');

function check_php_version($preset_version)
{
    $current_version = phpversion();
    return version_compare($current_version, $preset_version, '>=') ? true : false;
}

//Option-Framework

require get_template_directory() . '/opt/option-framework.php';

if (!function_exists('iro_opt')) {
    $GLOBALS['iro_options'] = get_option('iro_options');
    function iro_opt($option = '', $default = null)
    {
        if ( is_customize_preview() ) {
            $theme_mod = get_theme_mod('iro_options',[]);
            if (isset( $theme_mod[$option])) {
                return $theme_mod[$option]; //预览模式优先使用预览值
            } else {
                return $GLOBALS['iro_options'][$option] ?? $default;
            }
        } else {
            return $GLOBALS['iro_options'][$option] ?? $default;
        }
    }
}
if (!function_exists('iro_opt_update')) {
    function iro_opt_update($option = '', $value = null)
    {
        $options = get_option('iro_options'); // 当数据库没有指定项时，WordPress会返回false
        if ($options) {
            $options[$option] = $value;
        } else {
            $options = array($option => $value);
        }
        update_option('iro_options', $options);
    }
}

if (!function_exists('sakurairo_local_asset_url')) {
    function sakurairo_local_asset_url($path = '')
    {
        return trailingslashit(get_template_directory_uri()) . ltrim($path, '/');
    }
}

if (!function_exists('sakurairo_local_asset_version')) {
    function sakurairo_local_asset_version($paths)
    {
        $latest_mtime = 0;
        foreach ((array) $paths as $path) {
            $file = trailingslashit(get_template_directory()) . ltrim($path, '/');
            if (is_file($file)) {
                $latest_mtime = max($latest_mtime, (int) filemtime($file));
            }
        }

        return IRO_VERSION . ($latest_mtime ? '-' . $latest_mtime : '');
    }
}

if (!function_exists('sakurairo_rebase_vision_option_urls')) {
    function sakurairo_rebase_vision_option_urls($value, $old_basepath, $new_basepath)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = sakurairo_rebase_vision_option_urls($item, $old_basepath, $new_basepath);
            }
            return $value;
        }

        if (is_string($value) && strpos($value, $old_basepath) === 0) {
            return $new_basepath . substr($value, strlen($old_basepath));
        }

        return $value;
    }
}

if (!function_exists('sakurairo_rebase_vision_options')) {
    function sakurairo_rebase_vision_options($new_options, $old_options)
    {
        if (!is_array($new_options)) {
            return $new_options;
        }

        $default_basepath = SAKURAIRO_VISION_BASE_URL;
        $old_basepath = trailingslashit($old_options['vision_resource_basepath'] ?? $default_basepath);
        $new_basepath = trailingslashit($new_options['vision_resource_basepath'] ?? $default_basepath);

        if ($old_basepath !== $new_basepath) {
            $new_options = sakurairo_rebase_vision_option_urls($new_options, $old_basepath, $new_basepath);
            $new_options['vision_resource_basepath'] = $new_basepath;
        }

        return $new_options;
    }
}
add_filter('pre_update_option_iro_options', 'sakurairo_rebase_vision_options', 10, 2);

if (!function_exists('sakurairo_migrate_legacy_vision_basepath')) {
    function sakurairo_migrate_legacy_vision_basepath()
    {
        $legacy_basepath = 'https://s.nmxc.ltd/sakurairo_vision/@3.0/';
        $options = get_option('iro_options');

        if (!is_array($options)) {
            return;
        }

        $current_basepath = trailingslashit($options['vision_resource_basepath'] ?? $legacy_basepath);
        if ($current_basepath !== $legacy_basepath) {
            return;
        }

        $options = sakurairo_rebase_vision_option_urls($options, $legacy_basepath, SAKURAIRO_VISION_BASE_URL);
        $options['vision_resource_basepath'] = SAKURAIRO_VISION_BASE_URL;
        update_option('iro_options', $options);
        $GLOBALS['iro_options'] = $options;
    }
}
add_action('after_setup_theme', 'sakurairo_migrate_legacy_vision_basepath', 1);

// Theme and bundled third-party assets are always served from this installation.
$shared_lib_basepath = get_template_directory_uri();
$core_lib_basepath = get_template_directory_uri();

// 屏蔽php日志信息
if (iro_opt('php_notice_filter') != 'inner') {

    if (iro_opt('php_notice_filter','normal') == 'normal') { //仅显示严重错误
        error_reporting(E_ALL & ~E_DEPRECATED);
    }
    if (iro_opt('php_notice_filter') == 'all') { //屏蔽大部分错误
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        ini_set('display_errors', '0');
    }
}

/**
 * This customized fork is maintained independently and must not receive
 * update packages for the upstream Sakurairo theme.
 */
function sakurairo_disable_upstream_theme_updates($updates)
{
    if (is_object($updates) && isset($updates->response[get_template()])) {
        unset($updates->response[get_template()]);
    }

    return $updates;
}
add_filter('site_transient_update_themes', 'sakurairo_disable_upstream_theme_updates');

/**
 * Remove schedules and state left behind by the retired update module.
 */
function sakurairo_cleanup_legacy_update_state()
{
    if (get_option('sakurairo_update_module_cleanup_complete')) {
        return;
    }

    wp_clear_scheduled_hook('daily_event');
    wp_clear_scheduled_hook('puc_cron_check_updates_theme-Sakurairo');
    delete_site_option('puc_external_updates_theme-Sakurairo');
    add_option('sakurairo_update_module_cleanup_complete', '1', '', false);
}
add_action('init', 'sakurairo_cleanup_legacy_update_state', 1);

/**
 * Remove schedules, cached data, and credentials left by the retired tracking
 * and Bilibili favourites module.
 */
function sakurairo_cleanup_legacy_tracking_state()
{
    if (get_option('sakurairo_tracking_module_cleanup_complete')) {
        return;
    }

    wp_clear_scheduled_hook('bilibili_favlist_update_cron');

    $transients = array(
        'bangumi_cache',
        'bangumi_cache_expire',
        'bangumi_cache_duration',
        'bilibili_favlist_folders',
        'bilibili_favlist_folders_expire',
    );

    foreach ($transients as $transient) {
        delete_transient($transient);
    }

    // Folder and page identifiers were embedded in transient names, so remove
    // every matching database entry rather than relying on a fixed key list.
    global $wpdb;
    $transient_prefixes = array(
        '_transient_bilibili_favlist_',
        '_transient_timeout_bilibili_favlist_',
    );

    foreach ($transient_prefixes as $prefix) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like($prefix) . '%'
            )
        );
    }

    $retired_options = array(
        'bangumi_source',
        'my_anime_list_username',
        'my_anime_list_sort',
        'bilibili_id',
        'bilibili_cookie',
        'bangumi_id',
        'bangumi_cache',
    );

    $options = get_option('iro_options');
    if (is_array($options)) {
        $original_options = $options;
        foreach ($retired_options as $option) {
            unset($options[$option]);
        }
        if ($options !== $original_options) {
            update_option('iro_options', $options);
        }
    }

    $theme_mod_options = get_theme_mod('iro_options', array());
    if (is_array($theme_mod_options)) {
        $original_theme_mod_options = $theme_mod_options;
        foreach ($retired_options as $option) {
            unset($theme_mod_options[$option]);
        }
        if ($theme_mod_options !== $original_theme_mod_options) {
            set_theme_mod('iro_options', $theme_mod_options);
        }
    }

    add_option('sakurairo_tracking_module_cleanup_complete', '1', '', false);
}
add_action('init', 'sakurairo_cleanup_legacy_tracking_state', 1);

/**
 * Remove schedules, caches, and options left by the retired QQ avatar,
 * custom emoticon, and friend-link modules. Existing comments and bookmarks
 * are intentionally preserved.
 */
function sakurairo_cleanup_removed_comment_extras_and_friend_links()
{
    if (get_option('sakurairo_comment_friend_cleanup_complete')) {
        return;
    }

    if (function_exists('wp_unschedule_hook')) {
        wp_unschedule_hook('sakurairo_weekly_link_check');
        wp_unschedule_hook('sakurairo_check_links_batch');
    } else {
        wp_clear_scheduled_hook('sakurairo_weekly_link_check');
        wp_clear_scheduled_hook('sakurairo_check_links_batch');
    }

    delete_option('sakurairo_link_check_last_batch');
    delete_option('sakurairo_link_check_last_time');
    delete_option('sakurairo_link_check_started');
    delete_transient('custom_smilies_list');

    $retired_options = array(
        'smilies_list',
        'smilies_name',
        'smilies_dir',
        'smilies_proxy',
        'qq_avatar_link',
        'friend_link_align',
        'friend_link_form',
        'friend_link_sorting_mode',
        'friend_link_order',
    );

    $option_sets = array(
        'option' => get_option('iro_options'),
        'theme_mod' => get_theme_mod('iro_options', array()),
    );

    foreach ($option_sets as $storage => $options) {
        if (!is_array($options)) {
            continue;
        }

        $original_options = $options;
        foreach ($retired_options as $option) {
            unset($options[$option]);
        }

        if ($options === $original_options) {
            continue;
        }

        if ($storage === 'option') {
            update_option('iro_options', $options);
            $GLOBALS['iro_options'] = $options;
        } else {
            set_theme_mod('iro_options', $options);
        }
    }

    add_option('sakurairo_comment_friend_cleanup_complete', '1', '', false);
}
add_action('init', 'sakurairo_cleanup_removed_comment_extras_and_friend_links', 1);

/**
 * Remove cached data and settings left by the retired homepage exhibition.
 */
function sakurairo_cleanup_removed_exhibition_module()
{
    if (get_option('sakurairo_exhibition_cleanup_complete')) {
        return;
    }

    delete_transient('sakurairo_site_stats');

    $retired_options = array(
        'exhibition',
        'exhibition_area_icon',
        'exhibition_area_title',
        'capsule_components',
        'show_medal_capsules',
        'stat_announcement_text',
    );

    $option_sets = array(
        'option' => get_option('iro_options'),
        'theme_mod' => get_theme_mod('iro_options', array()),
    );

    foreach ($option_sets as $storage => $options) {
        if (!is_array($options)) {
            continue;
        }

        $original_options = $options;
        foreach ($retired_options as $option) {
            unset($options[$option]);
        }

        if (isset($options['homepage_components']) && is_array($options['homepage_components'])) {
            $options['homepage_components'] = array_values(array_diff(
                $options['homepage_components'],
                array('exhibition')
            ));
        }

        if ($options === $original_options) {
            continue;
        }

        if ($storage === 'option') {
            update_option('iro_options', $options);
            $GLOBALS['iro_options'] = $options;
        } else {
            set_theme_mod('iro_options', $options);
        }
    }

    add_option('sakurairo_exhibition_cleanup_complete', '1', '', false);
}
add_action('init', 'sakurairo_cleanup_removed_exhibition_module', 1);

add_action('init', 'set_user_locale');
function set_user_locale() {
    if (is_user_logged_in()) {
        $user_locale = get_user_locale();
        switch_to_locale($user_locale);
    }
}

if (!function_exists('akina_setup')) {
    function akina_setup()
    {
        /*
         * Make theme available for translation.
         * Translations can be filed in the /languages/ directory.
         * If you're building a theme based on Akina, use a find and replace
         * to change 'akina' to the name of your theme in all the template files.
         */
        load_theme_textdomain('sakurairo', get_template_directory() . '/languages');

        /*
         * Enable support for Post Thumbnails on posts and pages.
         *
         * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
         */
        add_theme_support('post-thumbnails');
        set_post_thumbnail_size(150, 150, true);

        // This theme uses wp_nav_menu() in one location.
        register_nav_menus(
            array(
                'primary' => __('Nav Menus', 'sakurairo'), //导航菜单
            )
        );

        /*
         * Switch default core markup for search form, comment form, and comments
         * to output valid HTML5.
         */
        add_theme_support(
            'html5',
            array(
                'search-form',
                'comment-form',
                'comment-list',
                'gallery',
                'caption',
            )
        );

        /*
         * Enable support for Post Formats.
         * See https://developer.wordpress.org/themes/functionality/post-formats/
         */
        add_theme_support(
            'post-formats',
            array(
                'aside',
                'image',
                'status',
            )
        );

        // 注册小工具支持
        add_theme_support('widgets');

        /**
         * 废弃过时的wp_title
         * @seealso https://make.wordpress.org/core/2015/10/20/document-title-in-4-4/
         */
        add_theme_support('title-tag');

        // 优化代码
        //去除头部冗余代码
        remove_action('wp_head', 'feed_links_extra', 3);
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'index_rel_link');
        remove_action('wp_head', 'start_post_rel_link', 10);
        remove_action('wp_head', 'wp_generator');
        remove_filter('the_content', 'wptexturize'); 
        remove_action('template_redirect', 'rest_output_link_header', 11);

        if (!function_exists('disable_emojis')) {
            /**
             * Disable the emoji's
             * @see https://wordpress.org/plugins/disable-emojis/
             */
            function disable_emojis()
            {
                remove_action('wp_head', 'print_emoji_detection_script', 7);
                remove_action('admin_print_scripts', 'print_emoji_detection_script');
                remove_action('wp_print_styles', 'print_emoji_styles');
                remove_action('admin_print_styles', 'print_emoji_styles');
                remove_filter('the_content_feed', 'wp_staticize_emoji');
                remove_filter('comment_text_rss', 'wp_staticize_emoji');
                remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
                add_filter('tiny_mce_plugins', 'disable_emojis_tinymce');
            }
            add_action('init', 'disable_emojis');
        }
        if (!function_exists('disable_emojis_tinymce')) {
            /**
             * Filter function used to remove the tinymce emoji plugin.
             *
             * @param    array  $plugins
             * @return   array             Difference betwen the two arrays
             */
            function disable_emojis_tinymce($plugins)
            {
                if (is_array($plugins)) {
                    return array_diff($plugins, array('wpemoji'));
                } else {
                    return array();
                }
            }
        }
        // 移除菜单冗余代码
        add_filter('nav_menu_css_class', 'my_css_attributes_filter', 100, 1);
        add_filter('nav_menu_item_id', 'my_css_attributes_filter', 100, 1);
        add_filter('page_css_class', 'my_css_attributes_filter', 100, 1);
        function my_css_attributes_filter($var)
        {
            return is_array($var) ? array_intersect($var, array('current-menu-item', 'current-post-ancestor', 'current-menu-ancestor', 'current-menu-parent')) : '';
        }
    }
}
;
add_action('after_setup_theme', 'akina_setup');

function i18n_templates_name ($translated_name, $original_name) {
    $lang = get_user_locale();

    $template_names = array(
        'Archive Template' => array(
            'zh_CN' => '归档模板',
            'zh_TW' => '歸檔模板',
            'ja'    => 'アーカイブページテンプレート',
        ),
    );
    
    if ( isset( $template_names[ $original_name ] ) && isset( $template_names[ $original_name ][ $lang ] ) ) {
        return $template_names[ $original_name ][ $lang ];
    }
    // 英语/无翻译，返回gettext处理后的文本，防止原生翻译丢失
    return $translated_name;
}

add_filter('gettext', 'i18n_templates_name', 10, 2);

function register_shuoshuo_post_type() {
    $labels = array(
        'name'               => _x('Shuoshuo', 'post type general name', 'sakurairo'),
        'singular_name'      => _x('Shuoshuo', 'post type singular name', 'sakurairo'),
        'menu_name'          => _x('Shuoshuo', 'admin menu', 'sakurairo'),
        'name_admin_bar'     => _x('Shuoshuo', 'add new on admin bar', 'sakurairo'),
        'add_new'            => _x('Add New', 'shuoshuo', 'sakurairo'),
        'add_new_item'       => __('Add New Shuoshuo', 'sakurairo'),
        'new_item'           => __('New Shuoshuo', 'sakurairo'),
        'edit_item'          => __('Edit Shuoshuo', 'sakurairo'),
        'view_item'          => __('View Shuoshuo', 'sakurairo'),
        'all_items'          => __('All Shuoshuo', 'sakurairo'),
        'search_items'       => __('Search Shuoshuo', 'sakurairo'),
        'parent_item_colon'  => __('Parent Shuoshuo:', 'sakurairo'),
        'not_found'          => __('No shuoshuo found.', 'sakurairo'),
        'not_found_in_trash' => __('No shuoshuo found in Trash.', 'sakurairo')
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true,
        'query_var'          => true,
        'rewrite'            => array('slug' => 'shuoshuo'),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array('title', 'editor', 'author', 'thumbnail', 'custom-fields', 'comments'),
        'taxonomies'         => array('category') 
    );

    register_post_type('shuoshuo', $args);
}
add_action('init', 'register_shuoshuo_post_type');

function register_emotion_meta_boxes() {
    register_meta('post', 'emotion', array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
    register_meta('post', 'emotion_color', array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
}
add_action('init', 'register_emotion_meta_boxes');

function add_emotion_meta_box() {
    add_meta_box(
        'emotion_meta_box_id',
        __('Emotion Meta Box', 'sakurairo'),
        'render_emotion_meta_box',
        'shuoshuo', // 仅在shuoshuo内容类型中显示
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'add_emotion_meta_box');

function render_emotion_meta_box($post) {
    $emotion_value = get_post_meta($post->ID, 'emotion', true);
    $emotion_color_value = get_post_meta($post->ID, 'emotion_color', true);
    wp_nonce_field('emotion_meta_box_nonce', 'emotion_meta_box_nonce_field');
    echo '<label for="emotion">' . __('Emotion', 'sakurairo') . '</label>';
    echo '<input type="text" id="emotion" name="emotion" value="' . esc_attr($emotion_value) . '" />';
    echo '<br><br>';
    echo '<label for="emotion_color">' . __('Emotion Color', 'sakurairo') . '</label>';
    echo '<input type="text" id="emotion_color" name="emotion_color" value="' . esc_attr($emotion_color_value) . '" />';
    echo '<br><br>';
    echo '<p>' . __('For the Emotion, please fill in the Unicode value of the Fontawesome icon, and for the Emotion Color, please fill in the RGBA or hexadecimal color.', 'sakurairo') . '</p>';
}

function save_emotion_meta_box($post_id) {
    if (!isset($_POST['emotion_meta_box_nonce_field']) || !wp_verify_nonce($_POST['emotion_meta_box_nonce_field'], 'emotion_meta_box_nonce')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    if (isset($_POST['emotion'])) {
        update_post_meta($post_id, 'emotion', sanitize_text_field($_POST['emotion']));
    }
    if (isset($_POST['emotion_color'])) {
        update_post_meta($post_id, 'emotion_color', sanitize_text_field($_POST['emotion_color']));
    }
}
add_action('save_post', 'save_emotion_meta_box');

function register_custom_meta_boxes() {
    register_meta('post', 'title_style', array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
    register_meta('post', 'license', array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
}
add_action('init', 'register_custom_meta_boxes');

function add_custom_meta_box() {
    add_meta_box(
        'custom_meta_box_id',
        __('Custom Meta Box', 'sakurairo'),
        'render_custom_meta_box',
        'post', // 仅在post内容类型中显示
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'add_custom_meta_box');

function render_custom_meta_box($post) {
    $title_style_value = get_post_meta($post->ID, 'title_style', true);
    $license_value = get_post_meta($post->ID, 'license', true);
    wp_nonce_field('custom_meta_box_nonce', 'custom_meta_box_nonce_field');
    echo '<label for="title_style">' . __('Title Style', 'sakurairo') . '</label>';
    echo '<input type="text" id="title_style" name="title_style" value="' . esc_attr($title_style_value) . '" />';
    echo '<br><br>';
    echo '<label for="license">' . __('License', 'sakurairo') . '</label>';
    echo '<input type="text" id="license" name="license" value="' . esc_attr($license_value) . '" />';
    echo '<br><br>';
    echo '<p>' . __('For the Title Style, Please fill in the css style, part of the style need to add !important effective, and for the License, please go to Theme Options to learn how to set it up.', 'sakurairo') . '</p>';
}

function save_custom_meta_box($post_id) {
    if (!isset($_POST['custom_meta_box_nonce_field']) || !wp_verify_nonce($_POST['custom_meta_box_nonce_field'], 'custom_meta_box_nonce')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    if (isset($_POST['title_style'])) {
        update_post_meta($post_id, 'title_style', sanitize_text_field($_POST['title_style']));
    }
    if (isset($_POST['license'])) {
        update_post_meta($post_id, 'license', sanitize_text_field($_POST['license']));
    }
}
add_action('save_post', 'save_custom_meta_box');


// 载入区块编辑器修改
include_once('inc/blocks/iro_blocks.php');

function sakurairo_get_search_available_post_types()
{
    $post_types = array('post');

    if (iro_opt('search_for_shuoshuo', true)) {
        $post_types[] = 'shuoshuo';
    }

    $pages_enabled = iro_opt('search_for_pages', true);
    $pages_restricted = iro_opt('only_admin_can_search_pages', true);
    if ($pages_enabled && (!$pages_restricted || current_user_can('manage_options'))) {
        $post_types[] = 'page';
    }

    return array_values(array_unique($post_types));
}

function sakurairo_get_search_post_types()
{
    $available_post_types = sakurairo_get_search_available_post_types();
    if (!isset($_GET['content_type'])) {
        return $available_post_types;
    }

    $raw_content_types = wp_unslash($_GET['content_type']);
    $raw_content_types = is_array($raw_content_types) ? $raw_content_types : array($raw_content_types);
    $requested_post_types = array();

    foreach ($raw_content_types as $raw_content_type) {
        foreach (explode(',', (string) $raw_content_type) as $post_type) {
            $post_type = sanitize_key($post_type);
            if ($post_type !== '') {
                $requested_post_types[] = $post_type;
            }
        }
    }

    $selected_post_types = array_values(array_intersect($available_post_types, array_unique($requested_post_types)));
    return $selected_post_types ?: $available_post_types;
}

function sakurairo_get_search_excluded_post_ids()
{
    $raw_ids = (string) iro_opt('custom_exclude_search_results', '');
    if ($raw_ids === '') {
        return array();
    }

    return array_values(array_filter(array_map('absint', preg_split('/[\s,]+/', $raw_ids))));
}

// Configure the main query once so the search template does not load every
// matching post into PHP and paginate the result a second time.
function customize_query_functions($query)
{
    if (!$query->is_main_query() || is_admin()) {
        return;
    }

    // 主页可以显示文章和说说
    if ($query->is_home()) {
        // index引用content-thumb，其中根据设置项决定是否在主页排除说说
        $query->set('post_type', array('post', 'shuoshuo'));
    } elseif ($query->is_archive() || $query->is_category() || $query->is_author()) {
        $query->set('post_type', array('post', 'shuoshuo'));
    }

    if ($query->is_search()) {
        $query->set('post_type', sakurairo_get_search_post_types());
        $query->set('post__not_in', sakurairo_get_search_excluded_post_ids());
        $query->set('posts_per_page', 10);
        $query->set('ignore_sticky_posts', true);
        $query->set('sakurairo_prioritize_sticky', (bool) iro_opt('sticky_pinned_content', true));
    }
}

add_action('pre_get_posts', 'customize_query_functions');

function sakurairo_prioritize_sticky_search_results($orderby, $query)
{
    if (is_admin() || !$query->is_main_query() || !$query->is_search() || !$query->get('sakurairo_prioritize_sticky')) {
        return $orderby;
    }

    $sticky_post_ids = array_values(array_filter(array_map('absint', (array) get_option('sticky_posts', array()))));
    $sticky_post_ids = array_values(array_diff($sticky_post_ids, sakurairo_get_search_excluded_post_ids()));
    if (!$sticky_post_ids) {
        return $orderby;
    }

    global $wpdb;
    $sticky_orderby = 'CASE WHEN ' . $wpdb->posts . '.ID IN (' . implode(',', $sticky_post_ids) . ') THEN 0 ELSE 1 END ASC';
    return $orderby ? $sticky_orderby . ', ' . $orderby : $sticky_orderby;
}

add_filter('posts_orderby', 'sakurairo_prioritize_sticky_search_results', 10, 2);

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function akina_content_width()
{
    $GLOBALS['content_width'] = apply_filters('akina_content_width', 640);
}
add_action('after_setup_theme', 'akina_content_width', 0);

/**
 * Enqueue scripts and styles.
 */
function sakura_scripts()
{
    global $core_lib_basepath;
    global $shared_lib_basepath;

    // 预加载主要样式文件
    if(iro_opt('dev_mode',false) == false) { // 压缩并缓存主题样式
        
        function add_cache_control_header() { // 添加缓存策略
            if ( ! is_user_logged_in() ) {
                header( 'Cache-Control: public, max-age=86400, s-maxage=86400' );
            }
        }
        add_action( 'send_headers', 'add_cache_control_header' );

        $sakura_header = (iro_opt('choice_of_nav_style') == 'sakura' ? 'sakura_header' : 'iro_header');
        $wave = (iro_opt('wave_effects', 'false') == true ? 'wave' : 'no_wave');
        $content_style = (iro_opt('entry_content_style') == 'sakurairo' ? 'sakura' : 'github');
        $index = '';
        if (strpos(get_option('permalink_structure'), 'index.php') !== false) {
            $index = 'index.php';
        }
        $css_files = array(
            'css/index.php',
            'style.css',
            'css/shortcodes.css',
            'css/dark.css',
            'css/responsive.css',
            'css/animation.css',
            'css/templates.css',
            'css/content-style/' . $content_style . '.css',
        );
        if ($wave === 'wave') {
            $css_files[] = 'css/wave.css';
        }
        if ($sakura_header === 'sakura_header') {
            $css_files[] = 'css/sakura_header.css';
        }
        $css_version = sakurairo_local_asset_version($css_files);
        $iro_css = $core_lib_basepath . '/css/' . $index . '?' . $sakura_header . '&' . $content_style . '&' . $wave . '&minify&ver=' . rawurlencode($css_version);
        // A stylesheet discovered in the head already receives high priority.
        // Enqueue it once instead of turning a preload into a second stylesheet.
        wp_enqueue_style('iro-css', $iro_css, array(), null);

    } else {        
        wp_enqueue_style('iro-css', $core_lib_basepath . '/style.css', array(), sakurairo_local_asset_version('style.css'));
        wp_enqueue_style('iro-codes', $core_lib_basepath . '/css/shortcodes.css', array(), sakurairo_local_asset_version('css/shortcodes.css'));
        wp_enqueue_style('iro-dark', $core_lib_basepath . '/css/dark.css', array('iro-css'), sakurairo_local_asset_version('css/dark.css'));
        wp_enqueue_style('iro-responsive', $core_lib_basepath . '/css/responsive.css', array('iro-css'), sakurairo_local_asset_version('css/responsive.css'));
        wp_enqueue_style('iro-animation', $core_lib_basepath . '/css/animation.css', array('iro-css'), sakurairo_local_asset_version('css/animation.css'));
        wp_enqueue_style('iro-templates', $core_lib_basepath . '/css/templates.css', array('iro-css'), sakurairo_local_asset_version('css/templates.css'));

        $content_style = (iro_opt('entry_content_style') == 'sakurairo' ? 'sakura' : 'github');
        wp_enqueue_style(
            'entry-content',
            $core_lib_basepath . '/css/content-style/' . $content_style . '.css',
            array(),
            sakurairo_local_asset_version('css/content-style/' . $content_style . '.css')
        );
        if (iro_opt('wave_effects', 'false')){
            wp_enqueue_style('wave', $core_lib_basepath . '/css/wave.css', array(), sakurairo_local_asset_version('css/wave.css'));
        }
        if(iro_opt('choice_of_nav_style') == 'sakura'){
            wp_enqueue_style('sakura_header', $core_lib_basepath . '/css/sakura_header.css', array(), sakurairo_local_asset_version('css/sakura_header.css'));
        }
    }

    if(!is_404()){
        wp_enqueue_script('app', $core_lib_basepath . '/js/app.js', array('polyfills'), sakurairo_local_asset_version('js/app.js'), true);
        if (!is_home()) {
            //非主页的资源
            wp_enqueue_script('app-page', $core_lib_basepath . '/js/page.js', array('app', 'polyfills'), sakurairo_local_asset_version('js/page.js'), true);
        }
    }
    wp_enqueue_script('polyfills', $core_lib_basepath . '/js/polyfill.js', array(), sakurairo_local_asset_version('js/polyfill.js'), true);
    // defer加载
    add_filter('script_loader_tag', function($tag, $handle) {
        if ('polyfills' === $handle) {
            return str_replace('src', 'defer src', $tag);
        }
        return $tag;
    }, 10, 2);
    
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
    
    //前端脚本本地化
    if (get_user_locale() != 'zh_CN') {
        wp_localize_script(
            'app',
            '_sakurairoi18n',
            array(
                '复制成功！' => __("Copied!", 'sakurairo'),
                '拷贝代码' => __("Copy Code", 'sakurairo'),
                '你的封面API好像不支持跨域调用,这种情况下缓存是不会生效的哦' => __("Your cover API seems to not support Cross Origin Access. In this case, Cover Cache won't take effect.", 'sakurairo'),
                '提交中....' => __('Commiting....', 'sakurairo'),
                '提交成功' => __('Succeed', 'sakurairo'),
                '每次上传上限为10张' => __('10 files max per request', 'sakurairo'),
                "图片上传大小限制为5 MB\n\n「{0}」\n\n这张图太大啦~请重新上传噢！" => __("5 MB max per file.\n\n「{0}」\n\nThis image is too large~Please reupload!", 'sakurairo'),
                '上传中...' => __('Uploading...', 'sakurairo'),
                '图片上传成功~' => __('Uploaded successfully~', 'sakurairo'),
                "上传失败！\n文件名=> {0}\ncode=> {1}\n{2}" => __("Upload failed!\nFile Name=> {0}\ncode=> {1}\n{2}", 'sakurairo'),
                '上传失败，请重试.' => __('Upload failed, please retry.', 'sakurairo'),
                '页面加载出错了 HTTP {0}' => __("Page Load failed. HTTP {0}", 'sakurairo'),
                '很高兴你翻到这里，但是真的没有了...' => __("Glad you come, but we've got nothing left.", 'sakurairo'),
                "文章" => __("Post", 'sakurairo'),
                "标签" => __("Tag", 'sakurairo'),
                "分类" => __("Category", 'sakurairo'),
                "页面" => __("Page", 'sakurairo'),
                "评论" => __("Comment", 'sakurairo'),
                "已暂停..." => __("Paused...", 'sakurairo'),
                "正在载入视频 ..." => __("Loading Video...", 'sakurairo'),
                "将从网络加载字体，流量请注意" => __("Downloading fonts, be aware of your data usage.", 'sakurairo'),
                "您确定要切换私密状态吗？" => __("Are you sure you want to toggle private status?", 'sakurairo')
            )
        );
    }
    
    // 平滑滚动脚本优化为延迟加载
    if (iro_opt('smoothscroll_option', true)) {
        wp_enqueue_script('SmoothScroll', $shared_lib_basepath . '/js/smoothscroll.js', array(), IRO_VERSION . iro_opt('cookie_version', ''), true);
    }
}
add_action('wp_enqueue_scripts', 'sakura_scripts');

function sakurairo_configure_block_asset_loading()
{
    if (iro_opt('poi_pjax', true) == true) {
        // 禁用wp6.9按需加载
        add_filter( 'wp_should_load_separate_core_block_assets', '__return_false' );
        add_filter( 'should_load_separate_core_block_assets', '__return_false', 1 );
        add_filter( 'should_load_block_assets_on_demand', '__return_false', 1 );
        add_filter( 'enqueue_empty_block_content_assets', '__return_true' );
    }
}
add_action('after_setup_theme', 'sakurairo_configure_block_asset_loading');

function sakurairo_enqueue_core_block_styles()
{
    if (iro_opt('poi_pjax', true) == true) {
        // 分离加载已关闭，组合样式已包含评论和小工具等核心区块。
        wp_enqueue_style( 'wp-block-library' );
        wp_enqueue_style( 'wp-block-library-theme' );
    }
}
add_action('wp_enqueue_scripts', 'sakurairo_enqueue_core_block_styles', 20);

/**
 * load .php.
 */
require get_template_directory() . '/inc/decorate.php';
require get_template_directory() . '/inc/swicher.php';
require get_template_directory() . '/inc/api.php';

/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Customizer功能
 * 仅在Customizer预览框架中和Customizer编辑器载入时加载
 */
add_action( 'customize_register', function () {
    require_once get_template_directory() . '/inc/customizer.php';
} );
if ( is_customize_preview() ) {
    require_once get_template_directory() . '/inc/customizer.php';
}
function update_customize_to_iro_options() { //从key映射表中重组并保存设置项至iro_options中
    $theme_mod_options = get_theme_mod( 'iro_options', [] );
    $mapping = get_theme_mod( 'iro_options_map', [] );
	$iro_options = get_option('iro_options');
    
    foreach ( $mapping as $setting_id => $map ) {
        $preview_value = get_theme_mod( $setting_id, null );
        if ( null !== $preview_value ) {
            $iro_key = isset( $map['iro_key'] ) ? $map['iro_key'] : $setting_id;
            $iro_subkey = isset( $map['iro_subkey'] ) ? $map['iro_subkey'] : '';
            if ( $iro_subkey ) {
                if ( ! isset( $theme_mod_options[ $iro_key ] ) || ! is_array( $theme_mod_options[ $iro_key ] ) ) {
                    $theme_mod_options[ $iro_key ] = [];
                }
                $theme_mod_options[ $iro_key ][ $iro_subkey ] = $preview_value;
            } else {
                $theme_mod_options[ $iro_key ] = $preview_value;
            }
            // 移除已保存的值，确保下次还能同步
            remove_theme_mod( $setting_id );
        }
    }
	$theme_mod_options = array_merge($iro_options,$theme_mod_options);
    update_option( 'iro_options', $theme_mod_options );
}
add_action( 'customize_save_after', 'update_customize_to_iro_options' );

/**
 * function update
 */
require get_template_directory() . '/inc/theme-plus.php';
require get_template_directory() . '/inc/categories-images.php';

if (!function_exists('akina_comment_format')) {
    function akina_comment_format($comment, $args, $depth)
    {
        $GLOBALS['comment'] = $comment;
        ?>
        <li <?php comment_class(); ?> id="comment-<?php echo esc_attr(comment_ID()); ?>">
            <div class="contents">
                <div class="comment-arrow">
                    <div class="main shadow">
                        <div class="profile">
                            <a href="<?php comment_author_url(); ?>" target="_blank" rel="nofollow"><?php echo str_replace('src=', 'src="' . iro_opt('load_in_svg') . '" onerror="imgError(this,1)" data-src=', get_avatar($comment->comment_author_email, 80, '', get_comment_author(), array('class' => array('lazyload')))); ?></a>
                        </div>
                        <div class="commentinfo">
                            <section class="commeta">
                                <div class="left">
                                    <h4 class="author">
                                        <a href="<?php comment_author_url(); ?>" target="_blank" rel="nofollow"><?php echo get_avatar($comment->comment_author_email, 24, '', get_comment_author()); ?>
                                            <span class="bb-comment isauthor" title="<?php _e('Author', 'sakurairo'); ?>"><?php _e('Blogger', 'sakurairo'); /*博主*/?></span>
                                            <?php comment_author(); ?><?php echo get_author_class($comment->comment_author_email, $comment->user_id); ?>
                                        </a>
                                    </h4>
                                </div>
                                <?php comment_reply_link(array_merge($args, array('depth' => $depth, 'max_depth' => $args['max_depth']))); ?>
                                <div class="right">
                                    <div class="info"><time datetime="<?php comment_date('Y-m-d'); ?>"><?php echo poi_time_since(strtotime($comment->comment_date), true); //comment_date(get_option('date_format'));  
                                                ?></time><?= siren_get_useragent($comment->comment_agent); ?><?php echo mobile_get_useragent_icon($comment->comment_agent); ?>&nbsp;<?php if (iro_opt('comment_location')) {
                                                        _e('Location', 'sakurairo'); /*来自*/?>: <?php echo \Sakura\API\IpLocationParse::getIpLocationByCommentId($comment->comment_ID);
                                                    } ?>
                                    <?php if (current_user_can('manage_options') and (wp_is_mobile() == false)) {
                                        $comment_ID = $comment->comment_ID;
                                        $i_private = get_comment_meta($comment_ID, '_private', true);
                                        $flag = null;
                                        $flag .= ' <i class="fa-regular fa-snowflake"></i> <a href="javascript:;" data-actionp="set_private" data-idp="' . get_comment_id() . '" data-noncep="' . wp_create_nonce('siren_private_' . $comment_ID) . '" id="sp" class="sm">' . __("Private", "sakurairo") . ': <span class="has_set_private">';
                                        if (!empty($i_private)) {
                                            $flag .= __("Yes", "sakurairo") . ' <i class="fa-solid fa-lock"></i>';
                                        } else {
                                            $flag .= __("No", "sakurairo") . ' <i class="fa-solid fa-lock-open"></i>';
                                        }
                                        $flag .= '</span></a>';
                                        $flag .= edit_comment_link('<i class="fa-solid fa-pen-to-square"></i> ' . __("Edit", "mashiro"), ' <span style="color:rgba(0,0,0,.35)">', '</span>');
                                        echo $flag;
                                    } ?></div>
                                </div>
                            </section>
                        </div>
                        <div class="body">
                            <?php comment_text(); ?>
                        </div>
                    </div>
                    <div class="arrow-left"></div>
                </div>
            </div>
            <hr>
        <?php
    }
}

/**
 * 获取访客VIP样式
 */
function get_author_class($comment_author_email, $user_id)
{
    global $wpdb;
    // 安全修复：用 prepare 参数化查询防止 SQL 注入
    // 性能优化：用 COUNT(*) + get_var 替代 get_results + count（语义完全等价）
    $author_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $wpdb->comments WHERE comment_author_email = %s",
            $comment_author_email
        )
    );
    # 等级梯度
    $lv_array = [0, 5, 10, 20, 40, 80, 160];
    $Lv = 0;
    foreach ($lv_array as $key => $value) {
        if ($value >= $author_count)
            break;
        $Lv = $key;
        if (user_can($user_id, 'administrator')) {
            $Lv = 6;
        }
    }

    // $Lv = $author_count < 5 ? 0 : ($author_count < 10 ? 1 : ($author_count < 20 ? 2 : ($author_count < 40 ? 3 : ($author_count < 80 ? 4 : ($author_count < 160 ? 5 : 6)))));
    echo "<span class=\"showGrade{$Lv}\" title=\"Lv{$Lv}\"><img alt=\"level_img\" src=\"" . iro_opt('vision_resource_basepath', SAKURAIRO_VISION_BASE_URL) . "comment_level/level_{$Lv}.svg\" style=\"height: 1.5em; max-height: 1.5em; display: inline-block;\"></span>";
}

/**
 * post views
 */
function restyle_text($input)
{
    // 类型修复
    if (is_numeric($input)) {
        $number = (float)$input;
    } elseif (is_string($input)) {
        if (preg_match('/[-+]?[0-9]*\.?[0-9]+/', $input, $matches)) {
            $number = (float)$matches[0];
        } else {
            $number = 0;
        }
    } else {
        $number = 0;
    }

    switch (iro_opt('statistics_format')) {
        case "type_2": //23,333 次访问
            return number_format($number);
        case "type_3": //23 333 次访问
            return number_format($number, 0, '.', ' ');
        case "type_4": //23k 次访问
            if ($number >= 1000) {
                return round($number / 1000, 2) . 'k';
            }
            return $number;
        default:
            return $number;
    }
}

/**
 * Return the unformatted view count for calculations and pluralization.
 */
function get_post_views_raw($post_id)
{
    $post_id = absint($post_id);
    if (!$post_id) {
        return 0;
    }

    if (function_exists('wp_statistics_pages') && iro_opt('statistics_api') === 'wp_statistics') {
        return max(0, (int) wp_statistics_pages('total', 'uri', $post_id));
    }

    return max(0, (int) get_post_meta($post_id, 'views', true));
}

/**
 * Atomically increment the built-in counter to avoid lost updates under load.
 */
function sakurairo_increment_post_views($post_id)
{
    global $wpdb;

    $post_id = absint($post_id);
    if (!$post_id) {
        return false;
    }

    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->postmeta}
         SET meta_value = CAST(meta_value AS UNSIGNED) + 1
         WHERE post_id = %d AND meta_key = %s
         LIMIT 1",
        $post_id,
        'views'
    ));

    if ($updated === false) {
        return false;
    }

    if ($updated === 0 && !add_post_meta($post_id, 'views', 1, true)) {
        // Another request may have created the row between UPDATE and INSERT.
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->postmeta}
             SET meta_value = CAST(meta_value AS UNSIGNED) + 1
             WHERE post_id = %d AND meta_key = %s
             LIMIT 1",
            $post_id,
            'views'
        ));
        if ($updated === false) {
            return false;
        }
    }

    wp_cache_delete($post_id, 'post_meta');
    return true;
}

function sakurairo_is_crawler_request()
{
    $user_agent = isset($_SERVER['HTTP_USER_AGENT'])
        ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
        : '';

    if ($user_agent === '') {
        return true;
    }

    return (bool) preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|mediapartners|preview/i', $user_agent);
}

function sakurairo_get_recently_viewed_post_ids()
{
    if (empty($_COOKIE['sakurairo_viewed_posts'])) {
        return [];
    }

    $raw_ids = sanitize_text_field(wp_unslash($_COOKIE['sakurairo_viewed_posts']));
    return array_slice(wp_parse_id_list(explode(',', $raw_ids)), 0, 24);
}

function sakurairo_remember_post_view($post_id)
{
    if (headers_sent()) {
        return;
    }

    $post_id = absint($post_id);
    $post_ids = array_values(array_diff(sakurairo_get_recently_viewed_post_ids(), [$post_id]));
    array_unshift($post_ids, $post_id);
    $post_ids = array_slice($post_ids, 0, 24);

    $cookie_name = 'sakurairo_viewed_posts';
    $cookie_value = implode(',', $post_ids);
    $lifetime = max(HOUR_IN_SECONDS, (int) apply_filters('sakurairo_post_view_cookie_lifetime', 12 * HOUR_IN_SECONDS));
    $options = [
        'expires' => time() + $lifetime,
        'path' => defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/',
        'secure' => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (defined('COOKIE_DOMAIN') && COOKIE_DOMAIN) {
        $options['domain'] = COOKIE_DOMAIN;
    }

    setcookie($cookie_name, $cookie_value, $options);
    $_COOKIE[$cookie_name] = $cookie_value;
}

function set_post_views()
{
    $purpose = strtolower((string) ($_SERVER['HTTP_SEC_PURPOSE'] ?? $_SERVER['HTTP_PURPOSE'] ?? ''));
    if (iro_opt('statistics_api', 'theme_build_in') !== 'theme_build_in'
        || !is_singular(['post', 'shuoshuo'])
        || is_preview()
        || is_feed()
        || strpos($purpose, 'prefetch') !== false
        || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }

    $post_id = get_queried_object_id();
    if (!$post_id || get_post_status($post_id) !== 'publish' || sakurairo_is_crawler_request()) {
        return;
    }

    if (is_user_logged_in() && !apply_filters('sakurairo_count_logged_in_post_views', false, $post_id)) {
        return;
    }

    if (in_array($post_id, sakurairo_get_recently_viewed_post_ids(), true)) {
        return;
    }

    if (sakurairo_increment_post_views($post_id)) {
        sakurairo_remember_post_view($post_id);
    }
}

add_action('template_redirect', 'set_post_views', 20);

function get_post_views($post_id)
{
    return restyle_text(get_post_views_raw($post_id));
}

// 引入post_metas方法
require_once get_template_directory() . "/inc/post_metas.php";

/*
 * Gravatar头像使用中国服务器
 */
function gravatar_cn(string $url): string
{
    $gravatar_url = array('0.gravatar.com/avatar', '1.gravatar.com/avatar', '2.gravatar.com/avatar', 'secure.gravatar.com/avatar');
    if (iro_opt('gravatar_proxy') == 'custom_proxy_address_of_gravatar') {
        return str_replace($gravatar_url, iro_opt('custom_proxy_address_of_gravatar'), $url);
    } else {
        return str_replace($gravatar_url, iro_opt('gravatar_proxy'), $url);
    }
}
if (iro_opt('gravatar_proxy')) {
    add_filter('get_avatar_url', 'gravatar_cn', 4);
}

/*
 * 检查主题版本号，并在更新主题后执行设置选项值的更新
 */
function visual_resource_updates($specified_version, $option_name, $new_value)
{
    $theme = wp_get_theme();
    $current_version = $theme->get('Version');

    // Check if the function has already been triggered
    $function_triggered = get_transient('visual_resource_updates_triggered20');
    if ($function_triggered) {
        return; // Function has already been triggered, do nothing
    }

    if (version_compare($current_version, $specified_version, '>')) {
        $option_value = iro_opt($option_name);
        if (empty($option_value)) {
            $option_value = SAKURAIRO_VISION_BASE_URL;
        } else if (strpos($option_value, '@') === false || substr($option_value, strpos($option_value, '@') + 1) !== $new_value) {
            $option_value = preg_replace('/@.*/', '@' . $new_value, $option_value);
        }
        iro_opt_update($option_name, $option_value);

        // Set transient to indicate that the function has been triggered
        set_transient('visual_resource_updates_triggered20', true);
    }
}

visual_resource_updates('2.5.6', 'vision_resource_basepath', '3.0/');

function unlisted_avatar_updates() {
    $theme = wp_get_theme();
    $current_version = $theme->get('Version');

    // Check if the function has already been triggered
    $function_triggered = get_transient('unlisted_avatar_updates_triggered20');
    if ($function_triggered) {
        return; // Function has already been triggered, do nothing
    }

    if (version_compare($current_version, '2.5.6', '>')) {
        $option_value = iro_opt('unlisted_avatar');
        $old_values = array(
            'https://s.nmxc.ltd/sakurairo_vision/@2.7/basic/topavatar.png',
            'https://s.nmxc.ltd/sakurairo_vision/@2.6/basic/topavatar.png',  
            'https://s.nmxc.ltd/sakurairo_vision/@2.5/basic/topavatar.png'
        );
        
        if (in_array($option_value, $old_values)) {
            iro_opt_update('unlisted_avatar', '');
        }

        // Set transient to indicate that the function has been triggered
        set_transient('unlisted_avatar_updates_triggered20', true);
    }
}

unlisted_avatar_updates();

/*
 * 阻止站内文章互相Pingback
 */
function theme_noself_ping(&$links)
{
    $home = get_option('home');
    foreach ($links as $l => $link) {
        if (0 === strpos($link, $home)) {
            unset($links[$l]);
        }
    }
}
add_action('pre_ping', 'theme_noself_ping');

/*
 * 订制body类
 */
function akina_body_classes($classes)
{
    // Adds a class of group-blog to blogs with more than 1 published author.
    if (is_multi_author()) {
        $classes[] = 'group-blog';
    }
    // Adds a class of hfeed to non-singular pages.
    if (!is_singular()) {
        $classes[] = 'hfeed';
    }
    // 定制中文字体class
    $classes[] = 'chinese-font';
    /*if(!wp_is_mobile()) {
    $classes[] = 'serif';
    }*/
    if (isset($_COOKIE['dark' . iro_opt('cookie_version', '')])) {
        $classes[] = $_COOKIE['dark' . iro_opt('cookie_version', '')] == '1' ? 'dark' : ' ';
    } else {
        $classes[] = ' ';
    }
    return $classes;
}
add_filter('body_class', 'akina_body_classes');

/*
 * 图片CDN
 */
add_filter('upload_dir', 'wpjam_custom_upload_dir');
function wpjam_custom_upload_dir($uploads)
{
    /*     $upload_path = '';
     */$upload_url_path = iro_opt('image_cdn');

    $uploads['path'] = $uploads['basedir'] . $uploads['subdir'];

    if ($upload_url_path) {
        $uploads['baseurl'] = $upload_url_path;
        $uploads['url'] = $uploads['baseurl'] . $uploads['subdir'];
    }
    return $uploads;
}

/*
 * 删除自带小工具
 */
function unregister_default_widgets()
{
    unregister_widget('WP_Widget_Pages');
    unregister_widget('WP_Widget_Calendar');
    unregister_widget('WP_Widget_Archives');
    unregister_widget('WP_Widget_Links');
    unregister_widget('WP_Widget_Meta');
    unregister_widget('WP_Widget_Search');
    unregister_widget('WP_Widget_Categories');
    unregister_widget('WP_Widget_Recent_Posts');
    unregister_widget('WP_Nav_Menu_Widget');
}
add_action("widgets_init", "unregister_default_widgets", 11);

/**
 * Jetpack setup function.
 *
 * See: https://jetpack.com/support/infinite-scroll/
 * See: https://jetpack.com/support/responsive-videos/
 */
function akina_jetpack_setup()
{
    // Add theme support for Infinite Scroll.
    add_theme_support(
        'infinite-scroll',
        array(
            'container' => 'main',
            'render' => 'akina_infinite_scroll_render',
            'footer' => 'page',
        )
    );

    // Add theme support for Responsive Videos.
    add_theme_support('jetpack-responsive-videos');
}
add_action('after_setup_theme', 'akina_jetpack_setup');

/**
 * Custom render function for Infinite Scroll.
 */
function akina_infinite_scroll_render()
{
    while (have_posts()) {
        the_post();
        get_template_part('tpl/content', is_search() ? 'search' : get_post_format());
    }
}

/*
 * 编辑器增强
 */
function enable_more_buttons($buttons)
{
    $buttons[] = 'hr';
    $buttons[] = 'del';
    $buttons[] = 'sub';
    $buttons[] = 'sup';
    $buttons[] = 'fontselect';
    $buttons[] = 'fontsizeselect';
    $buttons[] = 'cleanup';
    $buttons[] = 'styleselect';
    $buttons[] = 'wp_page';
    $buttons[] = 'anchor';
    $buttons[] = 'backcolor';
    return $buttons;
}
add_filter("mce_buttons_3", "enable_more_buttons");

/*
 * 后台登录页
 */
$custom_login_switch = iro_opt('custom_login_switch');
if ($custom_login_switch) {
    // Add custom login styles
    function custom_login() {
        ?>
        <style type="text/css">body.login{background-image:url('<?php echo DEFAULT_FEATURE_IMAGE(); ?>');background-size:cover;background-position:center;background-repeat:no-repeat;background-attachment:fixed;}.login h1 a{background-image:url('<?php echo iro_opt('login_logo_img') ?: get_site_icon_url(); ?>') !important;background-size:contain;width:100%;max-height:100px;}.login form{box-shadow:0 1px 30px -4px #e8e8e880;border:1px solid #FFFFFF;background:rgba(255,255,255,0.8);-webkit-backdrop-filter:saturate(180%) blur(10px);backdrop-filter:saturate(180%) blur(10px);border-radius:10px;}.login form input[type=checkbox],.login input[type=password],.login input[type=text],.login input[type=email]{background:rgba(255,255,255,0.7);box-shadow:0 1px 30px -4px #e8e8e880;border:1px solid #FFFFFF;-webkit-backdrop-filter:saturate(180%) blur(10px);backdrop-filter:saturate(180%) blur(10px);font-size:15px;padding:0.6rem;border-radius:8px;}.login form input[type=checkbox]:checked{background:<?php echo iro_opt('theme_skin') ?: '#FF69B4'; ?>;border-color:<?php echo iro_opt('theme_skin') ?: '#FF69B4'; ?>}.wp-core-ui .button-primary,#wp-webauthn{background:<?php echo iro_opt('theme_skin') ?: '#FF69B4'; ?>;border-color:transparent;border-radius:6px;padding:1px 18px !important;transition:all 0.3s ease;}.wp-core-ui .button-primary:hover,#wp-webauthn:hover{background:<?php echo iro_opt('theme_skin_matching') ?: '#FF69B4'; ?>;border-color:transparent;transition:all 0.3s ease;}.vaptchaContainer{margin:5px 0 20px;}.login form .forgetmenot{margin-top: 6px;}.login .button.wp-hide-pw .dashicons{color:<?php echo iro_opt('theme_skin') ?: '#FF69B4'; ?>;}#language-switcher{color:<?php echo iro_opt('theme_skin') ?: '#FF69B4'; ?>;backdrop-filter:none;-webkit-backdrop-filter:none;}.login #nav{font-size:12px;padding:8px 12px;background:rgba(255,255,255,0.7);box-shadow:0 1px 30px -4px #e8e8e8;border:1px solid #FFFFFF;-webkit-backdrop-filter:saturate(180%) blur(10px);backdrop-filter:saturate(180%) blur(10px);width:fit-content;border-radius:8px;margin:auto;margin-top:-13%;}.login #backtoblog{display:none;}.captcha{display:flex !important;align-items:center;margin-bottom:20px !important;margin-top:10px;gap:10px;}.login form input[name=yzm]{margin:0;}.login label{margin-bottom:5px;}.wp-webauthn-notice{height: 40px !important;margin-bottom: 15px;}#wp-webauthn span{color:#fff;}.vp-dark-btn.vp-basic-btn{border-radius: 8px !important;}</style>
        <?php
    }
    add_action('login_head', 'custom_login');

    // Login Page Title
    function custom_headertitle($title) {
        return get_bloginfo('name');
    }
    add_filter('login_headertext', 'custom_headertitle');

    // Login Page Link
    function custom_loginlogo_url($url) {
        return esc_url(home_url('/'));
    }
    add_filter('login_headerurl', 'custom_loginlogo_url');
}

//Login message
//* Add custom message to WordPress login page
function smallenvelop_login_message($message)
{
    return empty($message) ? '<p class="message"><strong>You may try 3 times for every 5 minutes!</strong></p>' : $message;
}

//Fix password reset bug </>
function resetpassword_message_fix($message)
{
    return str_replace(['>', '<'], '', $message);
}
add_filter('retrieve_password_message', 'resetpassword_message_fix');

//Fix register email bug </>
function new_user_message_fix($message)
{
    $show_register_ip = '注册IP | Registration IP: ' . get_the_user_ip() . ' (' . \Sakura\API\IpLocationParse::getIpLocationByIp(get_the_user_ip()) . ")\r\n\r\n如非本人操作请忽略此邮件 | Please ignore this email if this was not your operation.\r\n\r\n";
    $message = str_replace('To set your password, visit the following address:', $show_register_ip . '在此设置密码 | To set your password, visit the following address:', $message);
    $message = str_replace('<', '', $message);
    $message = str_replace('>', "\r\n\r\n设置密码后在此登录 | Login here after setting password: ", $message);
    return $message;
}
add_filter('wp_new_user_notification_email', 'new_user_message_fix');

/*
 * 评论邮件回复
 */
function comment_mail_notify($comment_id)
{
    $mail_user_name = iro_opt('mail_user_name') ? iro_opt('mail_user_name') : 'no-reply';
    $comment = get_comment($comment_id);
    $parent_id = $comment->comment_parent ?: '';
    
    // 获取评论的审核状态，如果评论无需审核则直接发送
    $comment_approved = $comment->comment_approved;
    $mail_notify = iro_opt('mail_notify') ? get_comment_meta($parent_id, 'mail_notify', false) : false;
    $admin_notify = iro_opt('admin_notify') ? '1' : ((isset(get_comment($parent_id)->comment_author_email) && get_comment($parent_id)->comment_author_email) != get_bloginfo('admin_email') ? '1' : '0');
    
    if (($parent_id != '') &&($comment_approved === '1' || $comment_approved === 1) && ($admin_notify != '0') && (!$mail_notify)) {
        $wp_email = $mail_user_name . '@' . preg_replace('#^www\.#', '', strtolower($_SERVER['SERVER_NAME']));
        $to = trim(get_comment($parent_id)->comment_author_email);
        
        // 主题主色调
        $theme_color = iro_opt('theme_skin_matching') ?: '#FE9600';
        
        // 获取用户语言环境
        $comment_author_locale = get_comment_meta($parent_id, 'comment_author_locale', true);
        $locale = $comment_author_locale ?: get_locale();
        
        // 多语言支持
        switch ($locale) {
            case 'zh_TW':
                $subject = '你在 [' . get_option("blogname") . '] 的留言有了回應';
                $notification_title = '評論回覆通知';
                $dear = '親愛的';
                $new_reply = '您有一條來自';
                $new_reply_2 = '的回覆';
                $your_comment = '您在文章《';
                $your_comment_2 = '》上發表的評論：';
                $reply_to_you = '給您的回覆：';
                $view_complete = '查看完整對話';
                $auto_notify = '此郵件由系統自動發送，請勿直接回覆';
                break;
            case 'ja':
            case 'ja_JP':
                $subject = '[' . get_option("blogname") . '] のコメントに返信がありました';
                $notification_title = 'コメント返信通知';
                $dear = '尊敬する';
                $new_reply = 'からの新しい返信があります';
                $new_reply_2 = '';
                $your_comment = '記事「';
                $your_comment_2 = '」へのあなたのコメント：';
                $reply_to_you = 'さんからの返信：';
                $view_complete = '完全な会話を見る';
                $auto_notify = 'このメールはシステムによって自動的に送信されたものです。直接返信しないでください';
                break;
            case 'en_US':
            case 'en_GB':
                $subject = 'New Reply to Your Comment on [' . get_option("blogname") . ']';
                $notification_title = 'Comment Reply Notification';
                $dear = 'Dear';
                $new_reply = 'You have a new reply from';
                $new_reply_2 = '';
                $your_comment = 'Your comment on the article "';
                $your_comment_2 = '":';
                $reply_to_you = '\'s reply to you:';
                $view_complete = 'View Complete Conversation';
                $auto_notify = 'This email was automatically sent by the system, please do not reply directly';
                break;
            default: // 默认中文
                $subject = '你在 [' . get_option("blogname") . '] 的留言有了回应';
                $notification_title = '评论回复通知';
                $dear = '尊敬的';
                $new_reply = '您有一条来自';
                $new_reply_2 = '的回复';
                $your_comment = '您在文章《';
                $your_comment_2 = '》上发表的评论：';
                $reply_to_you = '给您的回复：';
                $view_complete = '查看完整对话';
                $auto_notify = '此邮件由系统自动发送，请勿直接回复';
                break;
        }
        
        // 现代化邮件模板
        $message = '
        <!DOCTYPE html>
        <html lang="' . str_replace('_', '-', $locale) . '">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . $notification_title . '</title>
        </head>
        <body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; color: #333; background-color: #f5f5f5;">
            <div style="max-width: 600px; margin: 20px auto; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden;">
                <!-- 头部 -->
                <div style="background-color: ' . $theme_color . '; padding: 20px; text-align: center;">
                    <h1 style="color: #fff; margin: 0; font-size: 22px;">' . $notification_title . '</h1>
                </div>
                
                <!-- 内容区 -->
                <div style="padding: 20px;">
                    <p style="font-size: 16px; margin-top: 0;">' . $dear . ' <strong>' . trim(get_comment($parent_id)->comment_author) . '</strong>：</p>
                    <p style="font-size: 16px;">' . $new_reply . ' <a href="' . home_url() . '" style="color: ' . $theme_color . '; text-decoration: none; font-weight: bold;">' . get_option("blogname") . '</a> ' . $new_reply_2 . '</p>
                    
                    <!-- 您的评论 -->
                    <p style="font-size: 14px;">' . $your_comment . '<a href="' . get_permalink($comment->comment_post_ID) . '" style="color: ' . $theme_color . '; text-decoration: none; font-weight: bold;">' . get_the_title($comment->comment_post_ID) . '</a>' . $your_comment_2 . '</p>
                    <div style="margin: 15px 0; padding: 15px; background-color: #f9f9f9; border-left: 4px solid #ddd; border-radius: 4px;">
                        <p style="margin: 0; font-size: 15px; color: #666;">' . trim(get_comment($parent_id)->comment_content) . '</p>
                    </div>
                    
                    <!-- 回复评论 -->
                    <p style="font-size: 14px;"><strong style="color: ' . $theme_color . ';">' . trim($comment->comment_author) . '</strong>' . $reply_to_you . '</p>
                    <div style="margin: 15px 0; padding: 15px; background-color: #f0f8ff; border-left: 4px solid ' . $theme_color . '; border-radius: 4px;">
                        <p style="margin: 0; font-size: 15px;">' . trim($comment->comment_content) . '</p>
                    </div>
                    
                    <!-- 查看回复按钮 -->
                    <div style="text-align: center; margin: 25px 0 15px;">
                        <a href="' . htmlspecialchars(get_comment_link($parent_id)) . '" style="background-color: ' . $theme_color . '; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 4px; font-weight: bold; display: inline-block;">' . $view_complete . '</a>
                    </div>
                </div>
                
                <!-- 页脚 -->
                <div style="background-color: #f5f5f5; padding: 15px; text-align: center; font-size: 13px; color: #999;">
                    <p style="margin: 5px 0;">' . $auto_notify . '</p>
                    <p style="margin: 5px 0;">&copy; ' . date('Y') . ' <a href="' . home_url() . '" style="color: ' . $theme_color . '; text-decoration: none;">' . get_option("blogname") . '</a></p>
                </div>
            </div>
        </body>
        </html>';
        
        // 处理 WordPress 原生表情符号
        $message = convert_smilies($message);
        
        // 处理图片
        $message = str_replace('{UPLOAD}', 'https://i.loli.net/', $message);
        $message = str_replace('[/img][img]', '[/img^img]', $message);
        $message = str_replace('[img]', '<img src="', $message);
        $message = str_replace('[/img]', '" style="max-width: 100%; height: auto; margin: 10px 0; border-radius: 4px;">', $message);
        $message = str_replace('[/img^img]', '" style="max-width: 100%; height: auto; margin: 10px 0; border-radius: 4px;"><img src="', $message);
        
        $from = 'From: "' . get_option('blogname') . "\" <$wp_email>";
        $headers = "$from\nContent-Type: text/html; charset=" . get_option('blog_charset') . "\n";
        wp_mail($to, $subject, $message, $headers);
    }
}
add_action('comment_post', 'comment_mail_notify');

/*
 * 评论通过审核时发送通知
 */
add_action('wp_set_comment_status', 'comment_status_changed_notify', 10, 2);

function comment_status_changed_notify($comment_id, $comment_status) {
    if ($comment_status === 'approve') {
        comment_mail_notify($comment_id);
    }
}

/*
 * 链接新窗口打开
 */
function rt_add_link_target($content)
{
    $content = str_replace('<a', '<a rel="nofollow"', $content);
    // use the <a> tag to split into segments
    $bits = explode('<a ', $content);
    // loop though the segments
    foreach ($bits as $key => $bit) {
        // fix the target="_blank" bug after the link
        if (strpos($bit, 'href') === false) {
            continue;
        }

        // fix the target="_blank" bug in the codeblock
        if (strpos(preg_replace('/code([\s\S]*?)\/code[\s]*/m', 'temp', $content), $bit) === false) {
            continue;
        }

        // find the end of each link
        $pos = strpos($bit, '>');
        // check if there is an end (only fails with malformed markup)
        if ($pos !== false) {
            // get a string with just the link's attibutes
            $part = substr($bit, 0, $pos);
            // for comparison, get the current site/network url
            $siteurl = network_site_url();
            // if the site url is in the attributes, assume it's in the href and skip, also if a target is present
            if (strpos($part, $siteurl) === false && strpos($part, 'target=') === false) {
                // add the target attribute
                $bits[$key] = 'target="_blank" ' . $bits[$key];
            }
        }
    }
    // re-assemble the content, and return it
    return implode('<a ', $bits);
}
add_filter('comment_text', 'rt_add_link_target');

// 评论通过BBCode插入图片
function comment_picture_support($content)
{
    $content = str_replace('http://', 'https://', $content); // 干掉任何可能的 http
    $content = str_replace('{UPLOAD}', 'https://i.loli.net/', $content);
    $content = str_replace('[/img][img]', '[/img^img]', $content);
    $content = str_replace('[img]', '<br><img src="' . iro_opt('load_in_svg') . '" data-src="', $content);
    $content = str_replace('[/img]', '" class="lazyload comment_inline_img" onerror="imgError(this)"><br>', $content);
    $content = str_replace('[/img^img]', '" class="lazyload comment_inline_img" onerror="imgError(this)"><img src="' . iro_opt('load_in_svg') . '" data-src="', $content);
    return $content;
}
add_filter('comment_text', 'comment_picture_support');

function comment_picture_support_rss($content)
{
    $content = str_replace('[img]', '<img src="', $content);
    $content = str_replace('[/img]', '" style="display: block;margin-left: auto;margin-right: auto;">', $content);
    return $content;
}
add_filter('comment_text_rss', 'comment_picture_support_rss');

function featuredtoRSS($content)
{
    global $post;
    if (has_post_thumbnail($post->ID)) {
        $content = '<div>' . get_the_post_thumbnail($post->ID, 'medium', array('style' => 'margin-bottom: 15px;')) . '</div>' . $content;
    }
    return $content;
}
add_filter('the_excerpt_rss', 'featuredtoRSS');
add_filter('the_content_feed', 'featuredtoRSS');


function toc_support($content)
{
    $content = str_replace('[toc]', '<div class="has-toc have-toc"></div>', $content); // TOC 支持
    $content = str_replace('[begin]', '<span class="begin">', $content); // 首字格式支持
    $content = str_replace('[/begin]', '</span>', $content); // 首字格式支持
    return $content;
}
add_filter('the_content', 'toc_support');
add_filter('the_excerpt_rss', 'toc_support');
add_filter('the_content_feed', 'toc_support');

function check_title_tags($content)
{
    if (!empty($content)) {
        $dom = new DOMDocument();
        @$dom->loadHTML($content);
        $headings = $dom->getElementsByTagName('h1');
        for ($i = 1; $i <= 6; $i++) {
            $headings = $dom->getElementsByTagName('h' . $i);
            foreach ($headings as $heading) {
                if (trim($heading->nodeValue) != '') {
                    return true;
                }
            }
        }
    }
    return false;
}

/*私密评论*/
add_action('wp_ajax_siren_private', 'siren_private');
function siren_private()
{
    $comment_id = isset($_POST["p_id"]) ? absint($_POST["p_id"]) : 0;
    $action = isset($_POST["p_action"]) ? sanitize_key($_POST["p_action"]) : '';
    if (!current_user_can('manage_options') || empty($comment_id) || !get_comment($comment_id)) {
        die;
    }
    check_ajax_referer('siren_private_' . $comment_id);
    if ($action == 'set_private') {
        $i_private = get_comment_meta($comment_id, '_private', true);
        if (!empty($i_private)) {
            delete_comment_meta($comment_id, '_private');
            echo __("No", "sakurairo") . ' <i class="fa-solid fa-lock-open"></i>';
        } else {
            update_comment_meta($comment_id, '_private', 'true');
            echo __("Yes", "sakurairo") . ' <i class="fa-solid fa-lock"></i>';
        }
    }
    die;
}

require_once __DIR__ . '/inc/word-stat.php';
/**
 * 字数、词数统计
 */
function count_post_words($post_ID)
{
    $post = get_post($post_ID);
    if (!in_array($post->post_type, ['post', 'shuoshuo'])) {
        return;
    }
    $content = $post->post_content;
    $content = strip_tags($content);
    $count = word_stat($content);
    update_post_meta($post_ID, 'post_words_count', $count);
    return $count;
}

add_action('save_post', 'count_post_words');

/*
 * 隐藏 Dashboard
 */
/* Remove the "Dashboard" from the admin menu for non-admin users */
function remove_dashboard()
{
    global $current_user, $menu, $submenu;
    wp_get_current_user();

    if (!in_array('administrator', $current_user->roles)) {
        reset($menu);
        $page = key($menu);
        while ((__('Dashboard') != $menu[$page][0]) && next($menu)) {
            $page = key($menu);
        }
        if (__('Dashboard') == $menu[$page][0]) {
            unset($menu[$page]);
        }
        reset($menu);
        $page = key($menu);
        while (!$current_user->has_cap($menu[$page][1]) && next($menu)) {
            $page = key($menu);
        }
        if (
            preg_match('#wp-admin/?(index.php)?$#', $_SERVER['REQUEST_URI']) &&
            ('index.php' != $menu[$page][2])
        ) {
            wp_safe_redirect(admin_url('profile.php'));
        }
    }
}
add_action('admin_menu', 'remove_dashboard');

/**
 * Filter the except length to 20 words. 限制摘要长度
 *
 * @param int $length Excerpt length.
 * @return int (Maybe) modified excerpt length.
 */

function GBsubstr($string, $start, $length)
{
    if (strlen($string) > $length) {
        $str = null;
        $len = 0;
        $i = $start;
        while ($len < $length) {
            if (ord(substr($string, $i, 1)) > 0xc0) {
                $str .= substr($string, $i, 3);
                $i += 3;
            } elseif (ord(substr($string, $i, 1)) > 0xa0) {
                $str .= substr($string, $i, 2);
                $i += 2;
            } else {
                $str .= substr($string, $i, 1);
                $i++;
            }
            $len++;
        }
        return $str;
    } else {
        return $string;
    }
}

function excerpt_length($exp)
{
    if (!function_exists('mb_substr')) {
        $exp = GBsubstr($exp, 0, 110);
    } else {
        /*
         * To use mb_substr() function, you should uncomment "extension=php_mbstring.dll" in php.ini
         */
        $exp = mb_substr($exp, 0, 110);
    }
    return $exp;
}
add_filter('the_excerpt', 'excerpt_length', 11);

// 主动resize触发wp_scripts后台排版修正，防止左侧导航栏飞出
add_action('admin_footer',function() {
    ?><script>
        document.addEventListener('DOMContentLoaded',function() {
            window.dispatchEvent(new Event("resize"));
        })
    </script>
    <?php
});

//dashboard scheme
function dash_scheme($key, $name, $col1, $col2, $col3, $base, $focus, $current, $rules = "") {
    $hash = 'rules=' . urlencode($rules);
    if ($col1) {
        $hash .= '&color_1=' . str_replace("#", "", $col1); 
    }
    if ($col2) {
        $hash .= '&color_2=' . str_replace("#", "", $col2);
    }
    if ($col3) {
        $hash .= '&color_3=' . str_replace("#", "", $col3); 
    }

    wp_admin_css_color(
        $key,
        $name,
        get_template_directory_uri() . "/inc/dash-scheme.php?" . $hash,
        array($col1, $col2, $col3),
        array('base' => $base, 'focus' => $focus, 'current' => $current)
    );
}

//Sakurairo
dash_scheme(
    $key = "sakurairo",
    $name = "Sakurairo🌸",
    $col1 = iro_opt('admin_second_class_color'),
    $col2 = iro_opt('admin_first_class_color'), 
    $col3 = iro_opt('admin_emphasize_color'),
    $base = "#FFF",
    $focus = "#FFF",
    $current = "#FFF",
    $rules = 'body{background-image:url(' . iro_opt('admin_background') . ');background-attachment:fixed;background-size:cover;}'
);

// WordPress Custom style @ Admin
function custom_admin_open_sans_style()
{
    require get_template_directory() . '/inc/option-scheme.php';
}
add_action('admin_head', 'custom_admin_open_sans_style');

// 自动为页面添加description标签
if (iro_opt('iro_seo','on') != 'off') {

    if (iro_opt('iro_seo','on') == 'auto') {
        add_action('wp_head', function () {
            ob_start();
        }, 0);

        add_action('wp_head', function () {
            $head_content = ob_get_clean();

            // 检查seo部分
            $has_description = preg_match('/<meta\s+name=["\']description["\']/i', $head_content);
            $has_keywords    = preg_match('/<meta\s+name=["\']keywords["\']/i', $head_content);

            echo $head_content;
            // 选择性补充
            if (!$has_description) {echo iro_get_description();}
            if (!$has_keywords) {echo iro_get_keywords();}
        }, 99);
    } else {
        // 始终添加
        add_action('wp_head', function () {
            echo iro_get_description();
            echo iro_get_keywords();
        }, 99);
    }
}

function iro_get_keywords(){
    global $post;
    $keywords = '';

    if ( is_singular() ) {
        $tags = get_the_tags();
        if ( $tags ) {
            $keywords = implode(',', array_column($tags, 'name'));
        }
    } elseif ( is_category() ) {
        $cats = get_the_category();
        if ( $cats ) {
            $keywords = implode(',', array_column($cats, 'name'));
        }
    }

    if ( empty($keywords) ) {
        $keywords = iro_opt('iro_meta_keywords');
    }

    if ( empty($keywords) ) {
        $keywords = get_bloginfo('name');
    }

    if ( ! empty($keywords) ) {
        return '<meta name="keywords" content="' . esc_attr($keywords) . '">' . "\n";
    }
    return '';
}

function iro_get_description(){
    global $post;
    $description = '';

    if (is_singular() && !empty($post->post_content)) {
        $description = trim(mb_strimwidth(preg_replace('/\s+/', ' ', strip_tags($post->post_content)), 0, 240, '…'));
    }
    
    if (empty($description) && is_category()) {
        $description = trim(category_description());
    }

    if (empty($description)) {
        $description = iro_opt('iro_meta_description');
    }

    if (empty($description)) {
        $description = get_bloginfo('description');
    }

    if (!empty($description)) {
        return '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    }
    
    return '';
}

function array_html_props(array $props)
{
    $props_string = '';
    foreach ($props as $key => $value) {
        $props_string .= ' ' . $key . '="' . $value . '"';
    }
    return $props_string;
}
/**
 * 渲染一个懒加载的<img>
 * @author KotoriK
 */
function lazyload_img(string $src, string $class = '', array $otherParam = array())
{
    $noscriptParam = $otherParam;
    if ($class)
        $noscriptParam['class'] = $class;
    $noscriptParam['src'] = $src;
    $otherParam['class'] = 'lazyload' . ($class ? ' ' . $class : '');
    $otherParam['data-src'] = $src;
    $otherParam['onerror'] = 'imgError(this)';
    $otherParam['src'] = iro_opt('page_lazyload_spinner');
    $noscriptProps = '';
    $props = array_html_props($otherParam);
    $noscriptProps = array_html_props($noscriptParam);
    return "<img$props/><noscript><img$noscriptProps/></noscript>";
}

// html 标签处理器
function html_tag_parser($content)
{
    if (!is_feed()) {
        //图片懒加载标签替换
        if (iro_opt('page_lazyload') && iro_opt('page_lazyload_spinner')) {
            $img_elements = array();
            $is_matched = preg_match_all('/<img[^<]*>/i', $content, $img_elements);
            if ($is_matched) {
                array_walk($img_elements[0], function ($img) use (&$content) {
                    $class_found = 0;
                    $new_img = preg_replace('/class=[\'"]([^\'"]+)[\'"]/i', 'class="$1 lazyload"', $img, -1, $class_found);
                    if ($class_found == 0) {
                        $new_img = str_replace('<img ', '<img class="lazyload"', $new_img);
                    }
                    $new_img = preg_replace('/srcset=[\'"]([^\'"]+)[\'"]/i', 'data-srcset="$1"', $new_img);
                    $new_img = preg_replace('/src=[\'"]([^\'"]+)[\'"]/i', 'data-src="$1" src="' . iro_opt('page_lazyload_spinner') . '" onerror="imgError(this)"', $new_img);
                    $content = str_replace($img, $new_img . '<noscript>' . $img . '</noscript>', $content);
                });
            }
        }

        //Fancybox
        /* Markdown Regex Pattern for Matching URLs:
         * https://daringfireball.net/2010/07/improved_regex_for_matching_urls
         */
        $url_regex = '((?:https?:\/\/|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}\/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))';

        //With Thumbnail: !{alt}(url)[th_url]
        if (preg_match_all('/\!\{.*?\)\[.*?\]/i', $content, $matches)) {
            foreach ($matches as $result) {
                $content = str_replace(
                    $result,
                    preg_replace(
                        '/!\{([^\{\}]+)*\}\(' . $url_regex . '\)\[' . $url_regex . '\]/i',
                        '<a data-fancybox="gallery"
                        data-caption="$1"
                        class="fancybox"
                        href="$2"
                        alt="$1"
                        title="$1"><img src="$7" target="_blank" rel="nofollow" class="fancybox"></a>',
                        $result
                    ),
                    $content
                );
            }
        }

        //Without Thumbnail :!{alt}(url)
        $content = preg_replace(
            '/!\{([^\{\}]+)*\}\(' . $url_regex . '\)/i',
            '<a data-fancybox="gallery"
                data-caption="$1"
                class="fancybox"
                href="$2"
                alt="$1"
                title="$1"><img src="$2" target="_blank" rel="nofollow" class="fancybox"></a>',
            $content
        );
    }
    //html tag parser for rss
    if (is_feed()) {
        //Fancybox
        $url_regex = '((?:https?:\/\/|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}\/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))';
        if (preg_match_all('/\!\{.*?\)\[.*?\]/i', $content, $matches)) {
            foreach ($matches as $result) {
                $content = str_replace(
                    $result,
                    preg_replace('/!\{([^\{\}]+)*\}\(' . $url_regex . '\)\[' . $url_regex . '\]/i', '<a href="$2"><img src="$7" alt="$1" title="$1"></a>', $result),
                    $content
                );
            }
        }
        $content = preg_replace('/!\{([^\{\}]+)*\}\(' . $url_regex . '\)/i', '<a href="$2"><img src="$2" alt="$1" title="$1"></a>', $content);
    }
    return $content;
}
add_filter('the_content', 'html_tag_parser'); //替换文章关键词


//生成随机链接，防止浏览器缓存策略
function get_random_url(string $url): string
{
    $array = parse_url($url);
    if (!isset($array['query'])) {
        // 无参数
        $url .= '?';
    } else {
        // 有参数
        $url .= '&';
    }
    return $url . random_int(1, 100);
}

// default feature image
function DEFAULT_FEATURE_IMAGE()
{
    //使用独立外部api
    if (iro_opt('post_cover_options') == 'type_2') {
        $url = iro_opt('post_cover');
        return $url ? get_random_url($url) : '';
    }
    //使用内建
    if (iro_opt('random_graphs_options') == 'gallery') {
        $url = rest_url('sakura/v1/gallery') . '?img=w';
        return get_random_url($url);
    }
    //使用封面外部
    if (iro_opt('random_graphs_options') == 'external_api') {
        $url = iro_opt('random_graphs_link');
        return $url ? get_random_url($url) : '';
    }
    //意外情况
    $url = iro_opt('random_graphs_link');
    return $url ? get_random_url($url) : '';
}

//评论回复
function sakura_comment_notify($comment_id)
{
    if (!isset($_POST['mail-notify'])) {
        update_comment_meta($comment_id, 'mail_notify', 'false');
    }
}
add_action('comment_post', 'sakura_comment_notify');

//侧栏小工具
if (iro_opt('sakura_widget')) {
    if (function_exists('register_sidebar')) {
        register_sidebar(
            array(
                'name' => __('Sidebar'), //侧栏
                'id' => 'sakura_widget',
                'before_widget' => '<div class="widget %2$s">',
                'after_widget' => '</div>',
                'before_title' => '<div class="title"><h2>',
                'after_title' => '</h2></div>',
            )
        );
    }
}


/**
 * 安全解析 WordPress 评论中的 Markdown 内容。
 * 此函数应挂载到 `preprocess_comment` 过滤器。
 *
 * @param array $incoming_comment 评论数据数组。
 * @return array 修改后的评论数据数组。
 */
function markdown_parser($incoming_comment)
{
    global $wpdb, $comment_markdown_content;
    global $allowedtags;

    /** 
     * 检查是否启用了 Markdown（假设前端评论表单中有 enable_markdown 字段）
     * Check if Markdown is enabled (assuming there is an enable_markdown field in the frontend comment form)
     */ 
    $enable_markdown = isset($_POST['enable_markdown']) ? (bool) $_POST['enable_markdown'] : false;
    
    /**
     * 初步安全检查
     * Initial security checks
     */
    $may_script = array(
        '/<script\b[^>]*>(.*?)<\/script>/is', // 阻止 <script> 标签
        '/<[^>]+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/is', // 阻止 HTML 属性中的 onxxx 事件
        '/\s(?:href|src)\s*=\s*(?:"(?:javascript|data):[^"]*"|\'(?:javascript|data):[^\']*\'|(?:javascript|data):[^\s>]+)/is', // 阻止带引号的 javascript: 或 data: 协议的 href/src 属性
    );
    
    foreach ($may_script as $pattern) {
        if (preg_match($pattern, $incoming_comment['comment_content'])) {
            siren_ajax_comment_err(__("For security reasons, JavaScript is not allowed in comments.")); //恶意内容警告
            return ($incoming_comment);
        }
    }
    
    /**
     * 启用 Markdown（如果启用）
     * Enable Markdown (if enabled)
     * 这里使用 wp_kses 来过滤 HTML 标签，允许的标签在 $allowedtags 中定义。
     * Here, we use wp_kses to filter HTML tags, and the allowed tags are defined in $allowedtags.
     */
    if ($enable_markdown) {
        include 'inc/Parsedown.php';
        $Parsedown = new Parsedown();
        // 核心安全
        // Set safe mode to true to prevent unsafe HTML tags
        $Parsedown->setSafeMode(true);

        // 禁用自动链接
        // Disable automatic linking of URLs
        $Parsedown->setUrlsLinked(false); 

        $incoming_comment['comment_content'] = $Parsedown->text($incoming_comment['comment_content']);
        /**
         * 使用 wp_kses 过滤 HTML 标签
         * Use wp_kses to filter HTML tags
         * 使用全局的 $allowedtags 变量来定义允许的 HTML 标签。
         * Use the global $allowedtags variable to define allowed HTML tags.
         */
        // kses 过滤
        // Use wp_kses to filter the comment content
        $incoming_comment['comment_content'] = wp_kses($incoming_comment['comment_content'], $allowedtags); // 自行调用kses
    } else {
        $incoming_comment['comment_content'] = htmlspecialchars($incoming_comment['comment_content'], ENT_QUOTES, 'UTF-8'); //未启用markdown直接转义
    }

    // $column_names = $wpdb->get_row("SELECT * FROM information_schema.columns where 
    // table_name='$wpdb->comments' and column_name = 'comment_markdown' LIMIT 1");
    // //Add column if not present.
    // if (!isset($column_names)) {
    //     $wpdb->query("ALTER TABLE $wpdb->comments ADD comment_markdown text");
    // }
    $comment_markdown_content = $incoming_comment['comment_content'];

    return $incoming_comment;
}
add_filter('preprocess_comment', 'markdown_parser');
remove_filter('comment_text', 'make_clickable', 9);

// //保存Markdown评论
// function save_markdown_comment($comment_ID, $comment_approved)
// {
//     global $wpdb, $comment_markdown_content;
//     $comment = get_comment($comment_ID);
//     $comment_content = $comment_markdown_content;
//     //store markdow content
//     $wpdb->query("UPDATE $wpdb->comments SET comment_markdown='" . $comment_content . "' WHERE comment_ID='" . $comment_ID . "';");
// }
// add_action('comment_post', 'save_markdown_comment', 10, 2);

//打开评论HTML标签限制
function allow_more_tag_in_comment()
{
    global $allowedtags;
    $allowedtags['img'] = [
        'src' => [],
        'alt' => [],
        'width' => [],
        'height' => [],
        'title' => [],
    ];
    $allowedtags['a'] = [
        'href' => [],
        'title' => [],
        'target' => [],
        'rel' => [],
    ];
    $allowedtags['b'] = array('class' => array());
    $allowedtags['br'] = array('class' => array());
    $allowedtags['blockquote'] = array('class' => array());
    $allowedtags['p'] = array('class' => array());
    $allowedtags['pre'] = array('class' => array());
    $allowedtags['code'] = array('class' => array());
    $allowedtags['h1'] = array('class' => array());
    $allowedtags['h2'] = array('class' => array());
    $allowedtags['h3'] = array('class' => array());
    $allowedtags['h4'] = array('class' => array());
    $allowedtags['h5'] = array('class' => array());
    $allowedtags['ul'] = array('class' => array());
    $allowedtags['ol'] = array('class' => array());
    $allowedtags['li'] = array('class' => array());
    $allowedtags['td'] = array('class' => array());
    $allowedtags['th'] = array('class' => array());
    $allowedtags['tr'] = array('class' => array());
    $allowedtags['table'] = array('class' => array());
    $allowedtags['thead'] = array('class' => array());
    $allowedtags['tbody'] = array('class' => array());
    $allowedtags['span'] = array('class' => array());
}
add_action('init', 'allow_more_tag_in_comment');
// 移除wp核心内置的两阶段评论过滤
remove_filter('pre_comment_content', 'wp_filter_kses');
remove_filter('comment_save_pre', 'wp_filter_kses');

/**
 * 检查数据库是否支持MyISAM引擎
 */
function check_myisam_support()
{
    global $wpdb;
    $results = $wpdb->get_results("SHOW ENGINES");
    if (!$results)
        return false;
    foreach ($results as $result) {
        if ($result->Engine == "MyISAM") {
            return $result->Support == "YES";
        }
    }
    return false;
}

//rest api支持
function permalink_tip()
{
    if (!get_option('permalink_structure')) {
        $msg = __('<b> For a better experience, please do not set <a href="/wp-admin/options-permalink.php"> permalink </a> as plain. To do this, you may need to configure <a href="https://www.wpdaxue.com/wordpress-rewriterule.html" target="_blank"> pseudo-static </a>. </ b>', 'sakurairo'); /*<b>为了更好的使用体验，请不要将<a href="/wp-admin/options-permalink.php">固定链接</a>设置为朴素。为此，您可能需要配置<a href="https://www.wpdaxue.com/wordpress-rewriterule.html" target="_blank">伪静态</a>。</b>*/
        echo '<div class="notice notice-success is-dismissible" id="scheme-tip"><p><b>' . $msg . '</b></p></div>';
    }
}
add_action('admin_notices', 'permalink_tip');
//code end

//解析短代码  
function register_shortcodes() {
    add_shortcode('task', function($attr, $content = '') {
        return '<div class="task shortcodestyle"><i class="fa-solid fa-clipboard-list"></i>' . $content . '</div>';
    });

    add_shortcode('warning', function($attr, $content = '') {
        return '<div class="warning shortcodestyle"><i class="fa-solid fa-triangle-exclamation"></i>' . $content . '</div>';
    });

    add_shortcode('noway', function($attr, $content = '') {
        return '<div class="noway shortcodestyle"><i class="fa-solid fa-square-xmark"></i>' . $content . '</div>';
    });

    add_shortcode('buy', function($attr, $content = '') {
        return '<div class="buy shortcodestyle"><i class="fa-solid fa-square-check"></i>' . $content . '</div>';
    });

    if (!function_exists('sakurairo_github_card_cache_key')) {
        function sakurairo_github_card_cache_key($path)
        {
            return 'sakurairo_ghcard_' . md5(strtolower($path));
        }

        function sakurairo_schedule_github_card_refresh($path)
        {
            $lock_key = sakurairo_github_card_cache_key($path) . '_lock';
            if (get_transient($lock_key)) {
                return;
            }

            $event_args = array($path);
            if (!wp_next_scheduled('sakurairo_refresh_github_card', $event_args)) {
                wp_schedule_single_event(time() + 1, 'sakurairo_refresh_github_card', $event_args);
            }
            set_transient($lock_key, '1', 10 * MINUTE_IN_SECONDS);
        }

        function sakurairo_refresh_github_card($path)
        {
            if (!is_string($path) || !preg_match('/^[a-zA-Z0-9_-]+\/[a-zA-Z0-9_.-]+$/', $path)) {
                return;
            }

            $cache_key = sakurairo_github_card_cache_key($path);
            $lock_key = $cache_key . '_lock';
            list($username, $repo) = explode('/', $path, 2);
            $api_url = sprintf('https://api.github.com/repos/%s/%s', rawurlencode($username), rawurlencode($repo));
            $response = wp_remote_get($api_url, array(
                'headers' => array(
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'WordPress-Sakurairo-GitHubCard',
                ),
                'redirection' => 3,
                'timeout' => 5,
            ));

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                set_transient($cache_key, array('status' => 'error'), 15 * MINUTE_IN_SECONDS);
                delete_transient($lock_key);
                return;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($data) || empty($data['full_name']) || empty($data['html_url'])) {
                set_transient($cache_key, array('status' => 'error'), 15 * MINUTE_IN_SECONDS);
                delete_transient($lock_key);
                return;
            }

            set_transient($cache_key, array(
                'status' => 'success',
                'full_name' => (string) $data['full_name'],
                'description' => (string) ($data['description'] ?? ''),
                'language' => (string) ($data['language'] ?? ''),
                'stargazers_count' => (int) ($data['stargazers_count'] ?? 0),
                'html_url' => (string) $data['html_url'],
            ), 12 * HOUR_IN_SECONDS);
            delete_transient($lock_key);
        }

        add_action('sakurairo_refresh_github_card', 'sakurairo_refresh_github_card');
    }

    add_shortcode('ghcard', function($attr, $content = '') {
        //获取内容
        $atts = shortcode_atts(array("path" => "mirai-mamori/Sakurairo"), $attr);

        $path = trim($atts['path']);

        if (strpos($path, 'https://github.com/') === 0) {
            $path = str_replace('https://github.com/', '', $path);
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+\/[a-zA-Z0-9_.-]+$/', $path)) {
            return '<p>Invalid GitHub repository path: ' . esc_html($path) . '</p>';
        }
    
        $cache_key = sakurairo_github_card_cache_key($path);
        $data = get_transient($cache_key);
        if (!is_array($data)) {
            sakurairo_schedule_github_card_refresh($path);
            $data = array('status' => 'pending');
        }

        if (($data['status'] ?? '') !== 'success') {
            return sprintf(
                '<div class="ghcard" style="border:1px solid #ddd; border-radius:10px; padding:16px; max-width:300px; background:#fff;"><a href="%s" target="_blank" rel="noopener noreferrer">%s</a><div style="margin-top:8px; color:#666; font-size:14px;">%s</div></div>',
                esc_url('https://github.com/' . $path),
                esc_html($path),
                esc_html__('Repository details are being refreshed.', 'sakurairo')
            );
        }

        // 获取数据
        $full_name = $data['full_name'];
        $description = $data['description'];
        $language = $data['language'];
        $stars = $data['stargazers_count'];
        $html_url = $data['html_url'];

        // 仓库图标 + 默认颜色点
        $lang_color = "green"; // 可拓展为语言颜色映射
        $description = esc_html($description);
        $language = esc_html($language);
        return sprintf(
            '<div class="ghcard" style="border:1px solid #ddd; border-radius:10px; padding:16px; max-width:300px; box-shadow:0 2px 6px rgba(0,0,0,0.1); background:#fff;">
                <div style="display:flex; align-items:center; margin-bottom:10px;">
                    <i class="fas fa-book" style="margin-right:8px; color:#555;"></i>
                    <a href="%s" target="_blank" style="color:#1a73e8; font-weight:bold; text-decoration:none;">%s</a>
                </div>
                <div style="font-size:14px; color:#444; margin-bottom:12px;">%s</div>
                <div style="display:flex; align-items:center; gap:16px; font-size:14px; color:#666;">
                    <div style="display:flex; align-items:center;">
                        <span style="width:10px; height:10px; background-color:%s; border-radius:50%%; display:inline-block; margin-right:6px;"></span>
                        %s
                    </div>
                    <div style="display:flex; align-items:center;">
                        <i class="far fa-star" style="margin-right:4px;"></i>
                        %d
                    </div>
                </div>
            </div>',
            esc_url($html_url),
            esc_html($full_name),
            $description,
            $lang_color,
            $language,
            intval($stars)
        );
    });

    add_shortcode('showcard', function($attr, $content = '') {
        $atts = shortcode_atts(array("icon" => "", "title" => "", "img" => "", "color" => ""), $attr);
        return sprintf(
            '<div class="showcard">
                <div class="img" alt="Show-Card" style="background:url(%s);background-size:cover;background-position: center;">
                    <a href="%s"><button class="showcard-button" style="color:%s !important;"><i class="fa-solid fa-angle-right"></i></button></a>
                </div>
                <div class="icon-title">
                    <i class="%s" style="color:%s !important;"></i>
                    <span class="title">%s</span>
                </div>
            </div>',
            $atts['img'],
            $content,
            esc_attr($atts['color']),
            esc_attr($atts['icon']),
            esc_attr($atts['color']),
            $atts['title'],
        );
    });

    add_shortcode('conversations', function($attr, $content = '') {
        $atts = shortcode_atts(array("avatar" => "", "direction" => "", "username" => ""), $attr);
        if (empty($atts['avatar']) && !empty($atts['username'])) {
            $user = get_user_by('login', $atts['username']);
            if ($user) {
                $atts['avatar'] = get_avatar_url($user->ID, 40);
            }
        }
        $speaker_alt = $atts['username'] ? '<span class="screen-reader-text">' . sprintf(__("%s says: ", "sakurairo"), esc_html($atts['username'])) . '</span>' : "";
        return sprintf(
            '<div class="conversations-code" style="flex-direction: %s;">
                <img src="%s">
                <div class="conversations-code-text">%s%s</div>
            </div>',
            esc_attr($atts['direction']),
            $atts['avatar'],
            $speaker_alt,
            $content
        );
    });

    add_shortcode('collapse', function($atts, $content = null) {
        $atts = shortcode_atts(array("title" => ""), $atts);
        ob_start();
        ?>
        <a href="javascript:void(0)" class="collapseButton">
            <div class="collapse shortcodestyle">
                <i class="fa-solid fa-angle-down"></i>
                <span class="xTitle"><?= $atts['title'] ?></span>
                <span class="ecbutton"><?php _e('Expand / Collapse', 'sakurairo'); ?></span>
            </div>
        </a>
        <div class="xContent" style="display: none;"><?= do_shortcode($content) ?></div>
        <?php
        return ob_get_clean();
    });

    add_shortcode('vbilibili', function ($atts, $content = null) {
        preg_match_all('/av([0-9]+)/', $content, $av_matches);
        preg_match_all('/BV([a-zA-Z0-9]+)/', $content, $bv_matches);
        $iframes = '';

        // av号
        if (!empty($av_matches[1])) {
            foreach ($av_matches[1] as $av) {
                $av = intval($av);
             
                $iframe_url = 'https://player.bilibili.com/player.html?avid=' . $av . '&page=1&autoplay=0&danmaku=0';
                $iframe = '<div style="position: relative; padding: 30% 45%;"><iframe src="' . $iframe_url . '" frameborder="no" scrolling="no" sandbox="allow-top-navigation allow-same-origin allow-forms allow-scripts" allowfullscreen="allowfullscreen" style="position: absolute; width: 100%; height: 100%; left: 0; top: 0;"> </iframe></div><br>';
                $iframes .= $iframe;
            }
        }
        // bv号
        if (!empty($bv_matches[1])) {
            foreach ($bv_matches[1] as $bv) {
                 
                $iframe_url = 'https://player.bilibili.com/player.html?bvid=' . $bv . '&page=1&autoplay=0&danmaku=0';
                $iframe = '<div style="position: relative; padding: 30% 45%;"><iframe src="' . $iframe_url . '" frameborder="no" scrolling="no" sandbox="allow-top-navigation allow-same-origin allow-forms allow-scripts" allowfullscreen="allowfullscreen" style="position: absolute; width: 100%; height: 100%; left: 0; top: 0;"> </iframe></div><br>';
                $iframes .= $iframe;
            }
        }
        return $iframes;
     });

    add_shortcode('checkbox', function ($attr, $content = null) {
        $attr = shortcode_atts(array("checked" => "", "inline" => ""), $attr);
        return sprintf('
        <div class="checkbox-code %s">
            <input type="checkbox" %s>
			<span> %s </span>
        </div>
        ',
        $attr['inline'] == 'true' ? "inline" : "shortcodestyle",
        $attr['checked'] == 'true' ? 'checked' : '',
        $content
        );
    });
    add_shortcode('label', function ($attr, $content = null) {
        $attr = shortcode_atts(array("color" => "", "shape" => ""), $attr);
        $color = $attr['color'];
        switch($color){
            case 'warning':
                $color = 'badge-warning';
                break;
            case 'severe':
                $color = 'badge-severe';
                break;
            default:
                $color = 'badge-info';
                break;
        }
        
        return sprintf('
        <span class="badge %s %s"> %s </span>
        ',
        $color,
        $attr['shape'] == 'round' ? 'bagde-round' : '',
        $content
        );
    });
    add_shortcode('progressbar',function ($attr,$content=null){
        $attr = shortcode_atts(array("color" => "", "progress" => ""), $attr);
        $progress = $attr['progress'];
        $color = $attr['color'];
        if($progress==''){
            $progress=100;
        }
        if($color==''){
            $color='bg-default';
        }
        $color = isset($attr['color']) ? $attr['color'] : 'indigo';

        switch ($color) {
            case 'red':
                $color = 'bg-danger';
                break;
            case 'orange':
                $color = 'bg-warning';
                break;
            case 'green':
                $color = 'bg-info';
                break;
            default:
                $color = 'bg-default';
            break;
        }

        return sprintf(
            "<div class='progress-wrapper'>
                <div class='progress-info'>%s
                    <div class='progress-percentage'><span>%d%%</span></div>
                </div>
                <div class='progress'>
                    <div class='progress-bar %s' style='width: %d%%;'></div>
                </div>
            </div>",
            $content != "" ? sprintf("<div class='progress-label'><span>%s</span></div>", $content) : "",
            $progress,
            $color,
            $progress
        );
    });
    /*add_shortcode('timeline',function ($attr,$content=null){
        $content = trim(strip_tags($content));
        $entries = explode("\n", $content);

        $out = "<div class='timeline-code'>";
        foreach ($entries as $entry) {
            $parts = explode("|", $entry);
            $time = str_replace("/", "</br>", $parts[0]);
            $title = isset($parts[1]) ? $parts[1] : '';
            
            $content_html = "";
            for ($i = 2; $i < count($parts); $i++) {
                $content_html .= ($i > 2 ? "</br>" : "") . $parts[$i];
            }

            $out .= sprintf(
                "<div class='timeline-node'>
                    <div class='timeline-time'>%s</div>
                    <div class='timeline-card card bg-gradient-secondary shadow-sm'>
                        %s
                        <div class='timeline-content'>%s</div>
                    </div>
                </div>",
                $time,
                $title !== '' ? sprintf("<div class='timeline-title'>%s</div>", $title) : '',
                $content_html
            );
        }
        $out .= "</div>";
        return $out;
    });*/
    add_shortcode('hidden',function ($attr, $content = null) {
        $attr = shortcode_atts(array("tip" => "", "type" => ""), $attr);
        $tip=''; $type='blur';
        if($attr['tip']!=""){
            $tip=$attr['tip'];
        }
        if($attr['type']!=""){
            $type = $attr['type'];
        }
    
        $class = ($type == 'background') ? 'hidden-text-background' : 'hidden-text-blur';
    
        return sprintf(
            "<span class='hidden-text %s'%s>%s</span>",
            $class,
            $tip !== '' ? sprintf(" title='%s'", $tip) : '',
            $content
        );
    });

    add_shortcode('post_time',function ($attr,$content=null){
        $attr = shortcode_atts(array("format" => ""), $attr);
        $format = ( $attr['format'] !='') ? $attr['format'] : 'Y-n-d G:i:s';
        return get_the_time($format);
    });

    add_shortcode('post_modified_time',function ($attr,$content=null){
        $attr = shortcode_atts(array("format" => ""), $attr);
        $format = ( $attr['format'] !='') ? $attr['format'] : 'Y-n-d G:i:s';
        return get_the_modified_time($format);
    });

    add_shortcode('noshortcode',function ($attr,$content=null){
        return $content;
    });

    
}
add_action('init', 'register_shortcodes');
//code end

//WEBP支持
function mimvp_filter_mime_types($array)
{
    $array['webp'] = 'image/webp';
    return $array;
}
add_filter('mime_types', 'mimvp_filter_mime_types', 10, 1);
function mimvp_file_is_displayable_image($result, $path)
{
    $info = @getimagesize($path);
    // if ($info['mime'] == 'image/webp') {
    //     $result = true;
    // }
    // return $result;
    return (bool) ($info); // 根据文档这里需要返回一个bool
}
add_filter('file_is_displayable_image', 'mimvp_file_is_displayable_image', 10, 2);

//code end

if (!iro_opt('login_language_opt') == '1') {
    add_filter('login_display_language_dropdown', '__return_false');
}

if (iro_opt('captcha_select') === 'iro_captcha') {
    function login_CAPTCHA()
    {
        include_once('inc/classes/Captcha.php');
        $img = new Sakura\API\Captcha;
        $test = $img->create_captcha_img();
        echo '<p><label for="captcha" class="captcha"><img id="captchaimg" width="120" height="40" style="border-radius: 8px;" src="', $test['data'], '"><input type="text" name="yzm" id="yzm" class="input" value="" size="20" tabindex="4" placeholder="请输入验证码"><input type="hidden" name="timestamp" value="', $test['time'], '"><input type="hidden" name="id" value="', $test['id'], '">'
            . "</label></p>";
    }
    add_action('login_form', 'login_CAPTCHA');
    add_action('register_form', 'login_CAPTCHA');
    add_action('lostpassword_form', 'login_CAPTCHA');

    /**
     * 登录界面验证码验证
     */
    function CAPTCHA_CHECK($user, $username, $password)
    {
        // Skip captcha check if it's a passwordless login
        if (isset($_POST['skip_captcha_check']) && $_POST['skip_captcha_check'] == '1') {
            return $user;
        }
        
        if (empty($_POST)) {
            return new WP_Error();
        }
        if (!(isset($_POST['yzm']) && !empty(trim($_POST['yzm'])))) {
            return new WP_Error('prooffail', '<strong>错误</strong>：验证码为空！');
        }
        if (!isset($_POST['timestamp']) || !isset($_POST['id']) || !preg_match('/^[\w$.\/]+$/', $_POST['id']) || !ctype_digit($_POST['timestamp'])) {
            return new WP_Error('prooffail', '<strong>错误</strong>：非法数据');
        }
        include_once('inc/classes/Captcha.php');
        $img = new Sakura\API\Captcha;
        $check = $img->check_captcha($_POST['yzm'], $_POST['timestamp'], $_POST['id']);
        if ($check['code'] == 5) {
            return $user;
        }
        return new WP_Error('prooffail', '<strong>错误</strong>：' . $check['msg']);
    }
    add_filter('authenticate', 'CAPTCHA_CHECK', 20, 3);
    
    // Add JavaScript to check for password field and toggle captcha visibility
    function add_captcha_check_script() {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var loginForm = document.getElementById('loginform');
            if (!loginForm) return;
            
            // Add hidden field for skipping captcha check
            var hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = 'skip_captcha_check';
            hiddenField.id = 'skip_captcha_check';
            hiddenField.value = '0';
            loginForm.appendChild(hiddenField);
            
            // Get elements once at initialization
            var passwordField = document.getElementById('user_pass');
            var captchaImg = document.getElementById('captchaimg');
            var yzmField = document.getElementById('yzm');
            
            // Find the captcha container (the parent element that contains the captcha)
            var captchaContainer = null;
            if (yzmField) {
                // Try to find the parent paragraph or label
                captchaContainer = yzmField.closest('p') || yzmField.closest('label');
                if (!captchaContainer && yzmField.parentNode) {
                    captchaContainer = yzmField.parentNode;
                }
            }
            
            function checkPasswordField() {
                // Check if password field is hidden or not present
                var isPasswordVisible = passwordField && 
                                        passwordField.style.display !== 'none' && 
                                        passwordField.offsetParent !== null;
                
                if (!isPasswordVisible) {
                    // Hide captcha elements
                    if (captchaContainer) {
                        captchaContainer.style.display = 'none';
                    }
                    
                    hiddenField.value = '1';
                } else {
                    // Show captcha elements
                    if (captchaContainer) {
                        captchaContainer.style.display = '';
                    }
                    
                    hiddenField.value = '0';
                }
            }
            
            // Initial check
            checkPasswordField();
            
            // Set up a less frequent interval to reduce performance impact
            var checkInterval = setInterval(checkPasswordField, 500);
            
            // Use MutationObserver for efficiency
            if (typeof MutationObserver !== 'undefined') {
                var observer = new MutationObserver(checkPasswordField);
                
                observer.observe(loginForm, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['style', 'class', 'display']
                });
            }
            
            // Add event listener for form submission
            loginForm.addEventListener('submit', checkPasswordField);
        });
        </script>
        <?php
    }
    add_action('login_footer', 'add_captcha_check_script');
    /**
     * 忘记密码界面验证码验证
     */
    function lostpassword_CHECK($errors)
    {
        if (empty($_POST)) {
            return false;
        }
        if (isset($_POST['yzm']) && !empty(trim($_POST['yzm']))) {
            if (!isset($_POST['timestamp']) || !isset($_POST['id']) || !preg_match('/^[\w$.\/]+$/', $_POST['id']) || !ctype_digit($_POST['timestamp'])) {
                return new WP_Error('prooffail', '<strong>错误</strong>：非法数据');
            }
            include_once('inc/classes/Captcha.php');
            $img = new Sakura\API\Captcha;
            $check = $img->check_captcha($_POST['yzm'], $_POST['timestamp'], $_POST['id']);
            if ($check['code'] != 5) {
                return $errors->add('invalid_department ', '<strong>错误</strong>：' . $check['msg']);
            }
        } else {
            return $errors->add('invalid_department', '<strong>错误</strong>：验证码为空！');
        }
    }

    add_action('lostpassword_post', 'lostpassword_CHECK');
    /** 
     *   注册界面验证码验证
     */
    function registration_CAPTCHA_CHECK($errors, $sanitized_user_login, $user_email)
    {
        if (empty($_POST)) {
            return new WP_Error();
        }
        if (!(isset($_POST['yzm']) && !empty(trim($_POST['yzm'])))) {
            return new WP_Error('prooffail', '<strong>错误</strong>：验证码为空！');
        }
        if (!isset($_POST['timestamp']) || !isset($_POST['id']) || !preg_match('/^[\w$.\/]+$/', $_POST['id']) || !ctype_digit($_POST['timestamp'])) {
            return new WP_Error('prooffail', '<strong>错误</strong>：非法数据');
        }
        include_once('inc/classes/Captcha.php');
        $img = new Sakura\API\Captcha;
        $check = $img->check_captcha($_POST['yzm'], $_POST['timestamp'], $_POST['id']);
        if ($check['code'] == 5)
            return $errors;

        return new WP_Error('prooffail', '<strong>错误</strong>：' . $check['msg']);

    }
    add_filter('registration_errors', 'registration_CAPTCHA_CHECK', 2, 3);
} elseif ((iro_opt('captcha_select') === 'vaptcha') && (!empty(iro_opt("vaptcha_vid")) && !empty(iro_opt("vaptcha_key")))) {
    function vaptchaInit()
    {
        include_once('inc/classes/Vaptcha.php');
        $vaptcha = new Sakura\API\Vaptcha;
        echo $vaptcha->html();
        echo $vaptcha->script();
    }
    add_action('login_form', 'vaptchaInit');

    function checkVaptchaAction($user)
    {
        if (empty($_POST)) {
            return new WP_Error();
        }
        if (!(isset($_POST['vaptcha_server']) && isset($_POST['vaptcha_token']))) {
            return new WP_Error('prooffail', '<strong>错误</strong>：请先进行人机验证');

        }
        if (!preg_match('/^https:\/\/([\w-]+\.)+[\w-]*([^<>=?\"\'])*$/', $_POST['vaptcha_server']) || !preg_match('/^[\w\-\$]+$/', $_POST['vaptcha_token'])) {
            return new WP_Error('prooffail', '<strong>错误</strong>：非法数据');
        }
        include_once('inc/classes/Vaptcha.php');
        $url = $_POST['vaptcha_server'];
        $token = $_POST['vaptcha_token'];
        $ip = get_the_user_ip();
        $vaptcha = new Sakura\API\Vaptcha;
        $response = $vaptcha->checkVaptcha($url, $token, $ip);
        if ($response->msg && $response->success && $response->score) {
            if ($response->success === 1 && $response->score >= 70) {
                return $user;
            }
            if ($response->success === 0) {
                $errorcode = $response->msg;
                return new WP_Error('prooffail', '<strong>错误</strong>：' . $errorcode);
            }
            return new WP_Error('prooffail', '<strong>错误</strong>：人机验证失败');

        } else if (is_string($response)) {
            return new WP_Error('prooffail', '<strong>错误</strong>：' . $response);
        }
        return new WP_Error('prooffail', '<strong>错误</strong>：未知错误');


    }
    add_filter('authenticate', 'checkVaptchaAction', 20, 3);
} else if ((iro_opt('captcha_select') === 'turnstile') && (!empty(iro_opt("turnstile_site_key")) && !empty(iro_opt("turnstile_secret_key")))) {
    function turnstile_init() {
        include_once('inc/classes/Turnstile.php');
        $turnstile = new Sakura\API\Turnstile;
        echo $turnstile->html();
        echo $turnstile->script();
    }
    add_action('login_form', 'turnstile_init');
    add_action('register_form', 'turnstile_init');
    add_action('lostpassword_form', 'turnstile_init');

    function verify_turnstile($user, $username = '', $password = '') {
        // Skip captcha check if it's a passwordless login
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $user;
        }
        if (isset($_POST['skip_captcha_check']) && $_POST['skip_captcha_check'] == '1') {
            return $user;
        }
        
        if (empty($_POST['cf-turnstile-response'])) {
            return new WP_Error('invalid_turnstile', '<strong>错误</strong>: 请完成人机验证', 'sakurairo');
        }

        $secret_key = iro_opt('turnstile_secret_key');
        $token = sanitize_text_field($_POST['cf-turnstile-response']);
        $ip = get_the_user_ip();
        include_once('inc/classes/Turnstile.php');
        $turnstile = new Sakura\API\Turnstile;

        $response = $turnstile->verify($token, $ip);
        if ($response['success'] === false) {
            return new WP_Error('turnstile_error', '<strong>错误</strong>: 无法验证人机验证，请稍后再试', 'sakurairo');
        }

        if (!$response['success']) {
            return new WP_Error('invalid_turnstile','<strong>错误</strong>: 人机验证失败', 'sakurairo');
        }

        return $user;
    }
    add_filter('authenticate', 'verify_turnstile', 20, 3);

    function turnstile_lostpassword_check($errors) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $errors;
        }
        if (empty($_POST['cf-turnstile-response'])) {
            $errors->add('invalid_turnstile', '<strong>错误</strong>: 请完成人机验证', 'sakurairo');
            return $errors;
        }

        $secret_key = iro_opt('turnstile_secret_key');
        $token = sanitize_text_field($_POST['cf-turnstile-response']);
        $ip = get_the_user_ip();

        include_once('inc/classes/Turnstile.php');
        $turnstile = new Sakura\API\Turnstile;
        $response = $turnstile->verify($token, $ip);

        if ($response['success'] === false) {
            $errors->add('turnstile_error', '<strong>错误</strong>: 无法验证人机验证，请稍后再试', 'sakurairo');
            return $errors;
        }

        if (!$response['success']) {
            $errors->add('invalid_turnstile', '<strong>错误</strong>: 人机验证失败', 'sakurairo');
        }

        return $errors;
    }
    add_action('lostpassword_post', 'turnstile_lostpassword_check');

    function turnstile_registration_check($errors, $sanitized_user_login, $user_email) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $errors;
        }
        if (empty($_POST['cf-turnstile-response'])) {
            $errors->add('invalid_turnstile', '<strong>错误</strong>: 请完成人机验证', 'sakurairo');
            return $errors;
        }

        include_once('inc/classes/Turnstile.php');
        $turnstile = new Sakura\API\Turnstile;
        $secret_key = iro_opt('turnstile_secret_key');
        $token = sanitize_text_field($_POST['cf-turnstile-response']);
        $ip = get_the_user_ip();

        $response = $turnstile->verify($token, $ip);

        if ($response['success'] === false) {
            $errors->add('turnstile_error', '<strong>错误</strong>: 无法验证人机验证，请稍后再试', 'sakurairo');
            return $errors;
        }

        if (!$response['success']) {
            $errors->add('invalid_turnstile', '<strong>错误</strong>: 人机验证失败', 'sakurairo');
        }

        return $errors;
    }
    add_filter('registration_errors', 'turnstile_registration_check', 10, 3);

    function add_captcha_check_script() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var loginForm = document.getElementById('loginform');
        if (!loginForm) return;
        
        // Add hidden field for skipping captcha check
        var hiddenField = document.createElement('input');
        hiddenField.type = 'hidden';
        hiddenField.name = 'skip_captcha_check';
        hiddenField.id = 'skip_captcha_check';
        hiddenField.value = '0';
        loginForm.appendChild(hiddenField);
        
        // Get elements once at initialization
        var passwordField = document.getElementById('user_pass');
        var captchaImg = document.getElementById('captchaimg');
        var yzmField = document.getElementById('yzm');
        var turnstileWidget = document.querySelector('.cf-turnstile');
        
        // Find the captcha container (the parent element that contains the captcha)
        var captchaContainer = null;
        if (yzmField) {
            // Try to find the parent paragraph or label
            captchaContainer = yzmField.closest('p') || yzmField.closest('label');
            if (!captchaContainer && yzmField.parentNode) {
                captchaContainer = yzmField.parentNode;
            }
        } else if (turnstileWidget) {
            captchaContainer = turnstileWidget.parentNode;
        }
        
        function checkPasswordField() {
            // Check if password field is hidden or not present
            var isPasswordVisible = passwordField && 
                                    passwordField.style.display !== 'none' && 
                                    passwordField.offsetParent !== null;
            
            if (!isPasswordVisible) {
                // Hide captcha elements
                if (captchaContainer) {
                    captchaContainer.style.display = 'none';
                }
                
                hiddenField.value = '1';
            } else {
                // Show captcha elements
                if (captchaContainer) {
                    captchaContainer.style.display = '';
                }
                
                hiddenField.value = '0';
            }
        }
        
        // Initial check
        checkPasswordField();
        
        // Set up a less frequent interval to reduce performance impact
        var checkInterval = setInterval(checkPasswordField, 500);
        
        // Use MutationObserver for efficiency
        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(checkPasswordField);
            
            observer.observe(loginForm, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['style', 'class', 'display']
            });
        }
        
        // Add event listener for form submission
        loginForm.addEventListener('submit', checkPasswordField);
    });
    </script>
    <?php
}
}

// 获取访客 IP
function get_the_user_ip()
{
    // CDN/反向代理场景：从 X-Forwarded-For 取最右侧（最接近源站）的 IP
    // CDN 会将真实访客 IP 追加到链尾，取最右侧可抵抗在链首插入伪造 IP 的攻击
    // 不信任 HTTP_CLIENT_IP（非标准头，纯伪造向量）
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    // 仅在站点位于反向代理/CDN 之后时才应信任 X-Forwarded-For；
    // 直接对外暴露的站点可通过该过滤器返回 false 以彻底拒绝 XFF 伪造
    $trust_forwarded = apply_filters('sakura_trust_x_forwarded_for', true);
    if ($trust_forwarded && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded_chain = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidate = trim(end($forwarded_chain));
        // 验证为合法 IPv4/IPv6，否则回退到 REMOTE_ADDR
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            $ip = $candidate;
        }
    }

    return apply_filters('wpb_get_ip', $ip);
}

// 生成归档页数据。缓存读写统一由 sakurairo_get_cached_archive_info() 管理。
function get_archive_info() {
    $args = [
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
        'post_type' => array('post', 'shuoshuo'),
        'post_status'    => 'publish',
        'suppress_filters' => false,
    ];
    $posts = get_posts($args);
    $years = [];

    foreach ($posts as $post) {
        $views = get_post_views_raw($post->ID);
        $words = get_meta_words_count($post->ID);
        $comments = (int) $post->comment_count;
        
        if ($post->post_type == 'post') {
            $post_type = 'article';
        } else {
            $post_type = 'shuoshuo';
        }

        $year = date('Y', strtotime($post->post_date));
        $month = date('n', strtotime($post->post_date));
        if ($post->post_password != ''){
            $post->post_title = __("It's a secret",'sakurairo'); // 隐藏受密码保护文章的标题
        }

        $category_ids = wp_get_post_categories($post->ID) ?: [];

        $post = [ //仅保存需要的数据（归档、展示区）
            'post_title'    => $post->post_title,
            'post_author'     => $post->post_author,
            'post_date'     => $post->post_date,
            'post_modified'     => $post->post_modified,
            'comment_count' => $comments,
            'link'          => get_the_permalink( $post->ID ),
            'categories'    => $category_ids,
            'meta' => [
                'views' => $views,
                'words' => $words,
                'type' => $post_type
            ]
        ];
        
        if (!isset($years[$year])) $years[$year] = [];
        if (!isset($years[$year][$month])) $years[$year][$month] = [];
        $years[$year][$month][] = $post;
    }

    return $years;
}

function sakurairo_get_cached_archive_info() {
    $years = get_transient('time_archive');
    if ($years !== false) {
        return $years;
    }

    $years = get_archive_info();
    $expiration = max(MINUTE_IN_SECONDS, (int) apply_filters('sakurairo_archive_cache_expiration', HOUR_IN_SECONDS));
    set_transient('time_archive', $years, $expiration);
    return $years;
}

function sakurairo_invalidate_archive_cache() {
    delete_transient('time_archive');
}

function sakurairo_invalidate_archive_cache_on_save($post_id, $post) {
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
        return;
    }

    if (in_array($post->post_type, ['post', 'shuoshuo'], true)) {
        sakurairo_invalidate_archive_cache();
    }
}

add_action('save_post_post', 'sakurairo_invalidate_archive_cache_on_save', 20, 2);
add_action('save_post_shuoshuo', 'sakurairo_invalidate_archive_cache_on_save', 20, 2);

function sakurairo_invalidate_archive_cache_for_deleted_post($post_id) {
    if (in_array(get_post_type($post_id), ['post', 'shuoshuo'], true)) {
        sakurairo_invalidate_archive_cache();
    }
}

add_action('before_delete_post', 'sakurairo_invalidate_archive_cache_for_deleted_post');
add_action('trashed_post', 'sakurairo_invalidate_archive_cache_for_deleted_post');
add_action('untrashed_post', 'sakurairo_invalidate_archive_cache_for_deleted_post');

function sakurairo_invalidate_archive_cache_for_terms($object_id, $terms, $tt_ids, $taxonomy) {
    if ($taxonomy === 'category' && in_array(get_post_type($object_id), ['post', 'shuoshuo'], true)) {
        sakurairo_invalidate_archive_cache();
    }
}

add_action('set_object_terms', 'sakurairo_invalidate_archive_cache_for_terms', 10, 4);

function sakurairo_invalidate_archive_cache_for_comment($comment_id, $comment = null) {
    $comment = $comment instanceof WP_Comment ? $comment : get_comment($comment_id);
    if ($comment && in_array(get_post_type($comment->comment_post_ID), ['post', 'shuoshuo'], true)) {
        sakurairo_invalidate_archive_cache();
    }
}

add_action('wp_insert_comment', 'sakurairo_invalidate_archive_cache_for_comment', 10, 2);
add_action('edit_comment', 'sakurairo_invalidate_archive_cache_for_comment', 10, 2);
add_action('delete_comment', 'sakurairo_invalidate_archive_cache_for_comment', 10, 2);

function sakurairo_invalidate_archive_cache_for_comment_status($new_status, $old_status, $comment) {
    if ($new_status !== $old_status && $comment instanceof WP_Comment) {
        sakurairo_invalidate_archive_cache_for_comment($comment->comment_ID, $comment);
    }
}

add_action('transition_comment_status', 'sakurairo_invalidate_archive_cache_for_comment_status', 10, 3);

/**
 * 返回是否应当显示文章标题。
 * 
 */
function should_show_title(): bool
{
    $id = get_the_ID();
    $use_as_thumb = get_post_meta($id, 'use_as_thumb', true); //'true','only',(default)
    return !iro_opt('patternimg')
        || !get_post_thumbnail_id($id)
        && $use_as_thumb != 'true' && !get_post_meta($id, 'video_cover', true);
}

/**
 * 修复 WordPress 搜索结果为空，返回为 200 的问题。
 * @author ivampiresp <im@ivampiresp.com>
 */
function search_404_fix_template_redirect()
{
    if (is_search()) {
        global $wp_query;

        if ($wp_query->found_posts == 0) {
            status_header(404);
        }
    }
}

add_action('template_redirect', 'search_404_fix_template_redirect');

// 给上传图片增加时间戳
add_filter('wp_handle_upload_prefilter', function ($file) {
    $file['name'] = time() . '-' . $file['name'];
    return $file;
});

/**
 * 在后台评论列表中添加IP地理位置信息列
 *
 * @param string[] $columns 列表标题的标签
 * @return void
 */
function iro_add_location_to_comments_columns($columns)
{
    $columns['iro_ip_location'] = __('Location', 'sakurairo');
    return $columns;
}

/**
 * 将IP地理位置信息输出到评论列表中
 *
 * @param string $column_name 列表标题的标签
 * @param int $comment_id 评论ID
 * @return void
 */
function iro_output_ip_location_columns($column_name, $comment_id)
{
    switch ($column_name) {
        case "iro_ip_location":
            echo \Sakura\API\IpLocationParse::getIpLocationByCommentId($comment_id);
            break;
    }
}
if (iro_opt('show_location_in_manage')) {
    add_filter('manage_edit-comments_columns', 'iro_add_location_to_comments_columns');
    add_action('manage_comments_custom_column', 'iro_output_ip_location_columns', 10, 2);
}

function iterator_to_string(Iterator $iterator): string
{
    $content = '';
    foreach ($iterator as $item) {
        $content .= $item;
    }
    return $content;
}

/*GET参数操作*/
function iro_action_operator()
{
    if (!isset($_GET['iro_act']) || empty($_GET['iro_act'])) {
        return;
    }

    if (!is_admin() || !current_user_can('manage_options')) {
        echo __("Access denied.", "sakurairo");
        return;
    }

    $direct_info = sanitize_key($_GET['iro_act']);

    switch($direct_info){
        case 'gallery_init':
            include_once('inc/classes/gallery.php');
            $gallery = new Sakura\API\gallery();
            echo $gallery->init();
            echo 'Done!';
            break;

        case 'gallery_webp':
            include_once('inc/classes/gallery.php');
            $gallery = new Sakura\API\gallery();
            echo $gallery->webp();
            echo 'Done!';
            break;
    }
}
iro_action_operator();

