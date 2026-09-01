<?php

namespace Sakura\API;

/**
 * 获取IP地理位置
 */
class IpLocation 
{
    //要查询的IP地址
    private $ip;
    //国家
    private $country;
    //地区
    private $region;
    //城市
    private $city;

    public function __construct(string $ip)
    {
        $this->ip = $ip;
    }
    /**
     * 通过IP-API接口获取IP地理位置
     * 接口文档：https://ip-api.com/docs/api:json
     *
     * @return boolean 成功返回true，失败返回false
     */
    private function getIpLocationByIpApi ()
    {
        if (empty($this->ip)) {
            return false;
        }

        // 检查速率限制
        $isLimit = get_transient('ip_location_rate_limit');
        if ($isLimit) {
            trigger_error('通过IP-API获取IP地理位置：获取失败，超过接口速率限制', E_USER_WARNING);
            return false;
        }

        // IP-API支持的语言
        $languages = array(
            'fr' => 'fr',
            'en' => 'en',
            'zh_CN' => 'zh-CN',
            'de' => 'de',
            'es' => 'es',
            'pt_BR' => 'pt-BR',
            'ja' => 'ja',
            'ru' => 'ru'
        );

        // 获取WordPress语言用于本地化
        $lang = get_locale();
        $lang = isset($languages[$lang])? $languages[$lang] : 'en';
        // 定义需要获取哪些信息，用法见接口文档
        $fields = '49177';
        $url = "http://ip-api.com/json/$this->ip?fields=$fields&lang=$lang";
        $response = wp_remote_get($url, array(
            'redirection' => 2,
            'timeout' => 3,
        ));
        // 检查响应
        if (is_wp_error($response)) {
            $errorMessage = $response->get_error_message();
            trigger_error('通过IP-API获取IP地理位置失败：' . $errorMessage, E_USER_WARNING);
            return false;
        } else {
            $headers = wp_remote_retrieve_headers($response);
            // 请求剩余次数
            $remainingAmount = $headers['X-Rl'];
            // 次数重置剩余时间
            $resetTime = $headers['X-Ttl'];
            // 防止超过速率限制
            if ($remainingAmount <= 2) {
                set_transient('ip_location_rate_limit', 'is_limit', $resetTime);
            }
            // 处理响应数据
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($data)) {
                if ($data['status'] == 'success') {
                    $this->country = $data['country'] ? $data['country'] : '';
                    $this->region = $data['regionName'] ? $data['regionName'] : '';
                    $this->city = $data['city'] ? $data['city'] : '';
                    return true;
                } else {
                    $message = $data['message'];
                    trigger_error("通过IP-API获取IP地理位置失败：$message", E_USER_WARNING);
                    return false;
                }
            } else {
                trigger_error('通过IP-API获取IP地理位置失败：返回的数据不是json格式', E_USER_WARNING);
                return false;
            }
        }
    }

    /**
     * 输出地理位置信息
     *
     * @return array 地理位置信息数组
     */
    private function outputLocation()
    {
        $location = array(
            'country' => $this->country,
           'region' => $this->region,
            'city' => $this->city
        );
        return $location;
    }

    /**
     * 检查IP地理位置信息的字段是否都是完整的
     *
     * @param array $data 地理位置信息数组
     * @return boolean true，完整；false，不完整
     */
    private function checkCompleteness(array $data)
    {
        $dataFilter = array_filter($data);
        if (count($dataFilter) === count($data)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 检查IP地址的合法性（排除空地址、非法地址以及私有地址和保留地址）
     *
     * @param string $ip 待检查的IP地址
     * @return boolean true，合法；false，非法
     */
    public static function checkIpValid(string $ip)
    {
        if (empty($ip)) {
            return false;
        }
        // 检查是否是合法的 IPv4 或 IPv6 地址
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // 检查是否是保留地址
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取IP地址的地理位置信息
     *
     * @return mixed 成功时返回地理位置信息数组array('country' => '国家','region' => '地区（省份）','city' => '城市')；失败时返回false
     */
    public function getLocation()
    {
        // 检查IP地址的合法性
        if (!static::checkIpValid($this->ip)) {
            trigger_error('获取IP地理位置失败：不是有效的IP地址', E_USER_WARNING);
            return false;
        }

        $this->getIpLocationByIpApi();
        
        $data = $this->outputLocation();
        if ($this->checkCompleteness($data)) {
            return $data;
        } else {
            trigger_error('获取IP地理位置失败', E_USER_WARNING);
            return false;
        }
    }
}

/**
 * IP地理位置解析输出
 */
class IpLocationParse
{
    //国家
    public $country;
    //地区
    public $region;
    //城市
    public $city;

    public function __construct(array $data)
    {
        $this->country = $data['country'];
        $this->region = $data['region'];
        $this->city = $data['city'];
    }

    /**
     * 通过HTML格式输出IP地理位置信息
     *
     * @return string HTML格式地理位置信息
     */
    public function getLocationHtml()
    {
        $html = '';
        $html.= '<div class="ip-location">';
        $html.= '<div class="ip-location-country">'.$this->country.'</div>';
        $html.= '<div class="ip-location-region">'.$this->region.'</div>';
        $html.= '<div class="ip-location-city">'.$this->city.'</div>';
        $html.= '</div>';
        return $html;
    }

    /**
     * 获取简洁的IP地址地理信息
     *
     * @return string “国家 地区（省份） 城市”
     */
    public function getLocationConcision()
    {
        return $this->country.' '.$this->region.' '.$this->city;
    }

    /**
     * 通过评论ID获取IP地理位置信息；缓存缺失时安排后台任务获取，避免阻塞页面渲染。
     *
     * @param int $comment_id 评论ID
     * @return string 成功时返回IP地理位置信息：“国家 地区（省份） 城市”；等待时返回“Pending”
     */
    public static function getIpLocationByCommentId(int $commentId)
    {
        $ipLocation = get_comment_meta($commentId, 'iro_ip_location', true);
        if (is_array($ipLocation) && $ipLocation) {
            $location = new IpLocationParse($ipLocation);
            return $location->getLocationConcision();
        }

        $cache_key = 'sakurairo_comment_location_' . $commentId;
        $cached_location = get_transient($cache_key);
        if (is_array($cached_location) && $cached_location) {
            $location = new IpLocationParse($cached_location);
            return $location->getLocationConcision();
        }
        if ($cached_location === 'error') {
            return __('Unknown', 'sakurairo');
        }

        $commentIp = get_comment_author_IP($commentId);
        if (empty($commentIp)) {
            return __('Empty Address', 'sakurairo');
        }
        if (!IPLocation::checkIpValid($commentIp)) {
            return __('Reserved Address', 'sakurairo');
        }

        $lock_key = $cache_key . '_lock';
        $event_args = array($commentId);
        if (!get_transient($lock_key)) {
            if (!wp_next_scheduled('sakurairo_resolve_comment_ip_location', $event_args)) {
                wp_schedule_single_event(time() + 1, 'sakurairo_resolve_comment_ip_location', $event_args);
            }
            set_transient($lock_key, '1', 10 * MINUTE_IN_SECONDS);
        }

        return __('Pending', 'sakurairo');
    }

    public static function resolveCommentIpLocation(int $commentId)
    {
        $cache_key = 'sakurairo_comment_location_' . $commentId;
        $lock_key = $cache_key . '_lock';
        $commentIp = get_comment_author_IP($commentId);

        if (empty($commentIp) || !IPLocation::checkIpValid($commentIp)) {
            set_transient($cache_key, 'error', HOUR_IN_SECONDS);
            delete_transient($lock_key);
            return;
        }

        $ipLocation = new IPLocation($commentIp);
        $location = $ipLocation->getLocation();
        if (!$location) {
            set_transient($cache_key, 'error', HOUR_IN_SECONDS);
            delete_transient($lock_key);
            return;
        }

        if (iro_opt('save_location', true)) {
            update_comment_meta($commentId, 'iro_ip_location', $location);
        } else {
            set_transient($cache_key, $location, DAY_IN_SECONDS);
        }
        delete_transient($lock_key);
    }

    /**
     * 获取单个IP地址地理位置信息
     *
     * @param string $ip IP地址
     * @return string 成功时返回IP地理位置信息：“国家 地区（省份） 城市”；失败时返回“Unknown”或“Reserved Address”或“Empty Address”
     */
    public static function getIpLocationByIp(string $ip)
    {
        if (empty($ip)) {
            return __('Empty Address');
        }
        if (IPLocation::checkIpValid($ip)) {
            $ipLocation = new IPLocation($ip);
            $location = $ipLocation->getLocation();
            if ($location) {
                $locationParse = new IpLocationParse($location);
                return $locationParse->getLocationConcision();
            } else {
                return __('Unknown');
            }
        } else {
            return __('Reserved Address');
        }
    }
}

add_action('sakurairo_resolve_comment_ip_location', array(IpLocationParse::class, 'resolveCommentIpLocation'));
