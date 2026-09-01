<?php
/**
 * @Author: fuukei
 * @Date:   2022-03-13 18:16:15
 * @Last Modified by: nicocatxzc
 * @Last Modified time: 2025-01-15 11:25:30
 */


/**
 * Classes
 */
include_once('classes/Cache.php');
include_once('classes/Images.php');
include_once('classes/gallery.php');
include_once('classes/Captcha.php');
use Sakura\API\Cache;
use Sakura\API\Captcha;

/**
 * Router
 */
add_action('rest_api_init', function () {
    register_rest_route('sakura/v1', '/image/upload', array(
        'methods' => 'POST',
        'callback' => 'upload_image',
        'permission_callback' => '__return_true'
    )
    );
    register_rest_route('sakura/v1', '/cache_search/json', array(
        'methods' => 'GET',
        'callback' => 'cache_search_json',
        'permission_callback' => '__return_true'
    )
    );
    register_rest_route('sakura/v1', '/gallery', array(
        'methods' => 'GET',
        'callback' => [new \Sakura\API\gallery(), 'get_image'],
        'permission_callback' => '__return_true'
    )
    );
    // register_rest_route('sakura/v1', '/database/update', array(
    //     'methods' => 'GET',
    //     'callback' => 'update_database',
    //     'permission_callback'=>'__return_true'
    // ));
    register_rest_route('sakura/v1', '/captcha/create', array(
        'methods' => 'GET',
        'callback' => 'create_CAPTCHA',
        'permission_callback' => '__return_true'
    )
    ); 
    // 归档页信息
    register_rest_route('sakura/v1', '/archive_info', array(
        'methods' => 'GET',
        'callback' => function (){
            return sakurairo_get_cached_archive_info();
        },
        'permission_callback' => '__return_true'
    )
    ); 
});

function sakura_get_rest_request_nonce(WP_REST_Request $request)
{
    $nonce = $request->get_header('X-WP-Nonce');
    if (!$nonce) {
        $nonce = $request->get_param('_wpnonce');
    }

    return $nonce ? sanitize_text_field(wp_unslash($nonce)) : '';
}

function sakura_verify_rest_request_nonce(WP_REST_Request $request)
{
    $nonce = sakura_get_rest_request_nonce($request);
    return $nonce && wp_verify_nonce($nonce, 'wp_rest');
}

/**
 * Image uploader response
 */
function upload_image(WP_REST_Request $request)
{
    // see: https://developer.wordpress.org/rest-api/requests/

    // handle file params $file === $_FILES
    /**
     * curl \
     *   -F "filecomment=This is an img file" \
     *   -F "cmt_img_file=@screenshot.jpg" \
     *   https://dev.2heng.xin/wp-json/sakura/v1/image/upload
     */
    // $file = $request->get_file_params();
    if (!sakura_verify_rest_request_nonce($request)) {
        $output = array(
            'status' => 403,
            'success' => false,
            'message' => 'Unauthorized client.'
        );
        $result = new WP_REST_Response($output, 403);
        $result->set_headers(array('Content-Type' => 'application/json'));
        return $result;
    }
    $images = new \Sakura\API\Images();
    $files = $request->get_file_params();
    // 验证上传文件存在且可读
    if (empty($files['cmt_img_file']['tmp_name']) || !is_readable($files['cmt_img_file']['tmp_name'])) {
        return new WP_REST_Response(array(
            'status' => 400,
            'success' => false,
            'message' => 'Missing or invalid upload file.'
        ), 400);
    }
    switch (iro_opt("img_upload_api")) {
        case 'imgur':
            $image = file_get_contents($files["cmt_img_file"]["tmp_name"]);
            $API_Request = $images->Imgur_API($image);
            break;
        case 'smms':
            $image = $files;
            $API_Request = $images->SMMS_API($image);
            break;
        case 'chevereto':
            $image = file_get_contents($files["cmt_img_file"]["tmp_name"]);
            $API_Request = $images->Chevereto_API($image);
            break;
        case 'lsky':
            $image = $files;
            $API_Request = $images->LSKY_API($image);
            break;
    }

    $result = new WP_REST_Response($API_Request, $API_Request['status']);
    $result->set_headers(array('Content-Type' => 'application/json'));
    return $result;
}

/*
 * update database rest api
 * @rest api接口路径：https://sakura.2heng.xin/wp-json/sakura/v1/database/update
 */
// function update_database() {
//     if (iro_opt('random_graphs_options') == "webp_optimization") {
//         $output = Cache::update_database();
//         $result = new WP_REST_Response($output, 200);
//         return $result;
//     } else {
//         return new WP_REST_Response("Invalid access", 200);
//     }
// }

/*
 * 定制实时搜索 rest api
 * @rest api接口路径：https://sakura.2heng.xin/wp-json/sakura/v1/cache_search/json
 * @可在cache_search_json()函数末尾通过设置 HTTP header 控制 json 缓存时间
 */
function sakurairo_live_search_cache_key($include_comments = null)
{
    if ($include_comments === null) {
        $include_comments = (bool) iro_opt('live_search_comment', false);
    }

    return 'sakurairo_live_search_' . md5(get_locale() . '|' . ($include_comments ? 'comments' : 'content'));
}

function sakurairo_clear_live_search_cache()
{
    delete_transient(sakurairo_live_search_cache_key(false));
    delete_transient(sakurairo_live_search_cache_key(true));
}

function sakurairo_clear_live_search_cache_for_post($post_id)
{
    if (wp_is_post_revision($post_id)) {
        return;
    }

    if (in_array(get_post_type($post_id), array('post', 'page'), true)) {
        sakurairo_clear_live_search_cache();
    }
}

add_action('save_post', 'sakurairo_clear_live_search_cache_for_post', 30);
add_action('before_delete_post', 'sakurairo_clear_live_search_cache_for_post', 30);
add_action('comment_post', 'sakurairo_clear_live_search_cache');
add_action('edit_comment', 'sakurairo_clear_live_search_cache');
add_action('deleted_comment', 'sakurairo_clear_live_search_cache');
add_action('update_option_iro_options', 'sakurairo_clear_live_search_cache', 10, 0);

function cache_search_json(WP_REST_Request $request)
{
    if (!sakura_verify_rest_request_nonce($request)) {
        $output = array(
            'status' => 403,
            'success' => false,
            'message' => 'Unauthorized client.'
        );
        $result = new WP_REST_Response($output, 403);
    } else {
        $cache_key = sakurairo_live_search_cache_key();
        $output = get_transient($cache_key);
        if (!is_array($output)) {
            $output = Cache::search_json();
            set_transient($cache_key, $output, HOUR_IN_SECONDS);
        }
        $result = new WP_REST_Response($output, 200);
    }
    $result->set_headers(
        array(
            'Content-Type' => 'application/json',
            'Cache-Control' => 'public, max-age=3600, stale-while-revalidate=60',
        )
    );
    return $result;
}

function create_CAPTCHA()
{
    $CAPTCHA = new Captcha();
    $response = new WP_REST_Response($CAPTCHA->create_captcha_img());
    $response->set_status(200);
    $response->set_headers(array('Content-Type' => 'application/json'));
    return $response;
}
