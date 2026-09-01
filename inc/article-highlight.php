<?php
class ColorAnalyzer {
    /**
     * 将 RGB 转换为 HSL
     * 返回数组 [h, s, l]，其中 h 为角度（0~360），s 和 l 为百分比（0~100）
     */
    public static function rgbToHsl($r, $g, $b) {
        $r /= 255;
        $g /= 255;
        $b /= 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $h = 0;
        $s = 0;
        $l = ($max + $min) / 2;

        if ($max == $min) {
            $h = 0;
            $s = 0;
        } else {
            $delta = $max - $min;
            $s = $l > 0.5 ? $delta / (2 - $max - $min) : $delta / ($max + $min);
            if ($max == $r) {
                $h = (($g - $b) / $delta) + ($g < $b ? 6 : 0);
            } elseif ($max == $g) {
                $h = (($b - $r) / $delta) + 2;
            } else {
                $h = (($r - $g) / $delta) + 4;
            }
            $h *= 60;
        }
        return [$h, $s * 100, $l * 100];
    }

    /**
     * 将 HSL 转换为 RGB
     * 输入：h（0~360），s、l（0~100），返回 [r, g, b]（0~255）
     */
    public static function hslToRgb($h, $s, $l) {
        $s /= 100;
        $l /= 100;
        if ($s == 0) {
            $r = $g = $b = $l;
        } else {
            $h /= 360;
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = self::hue2rgb($p, $q, $h + 1/3);
            $g = self::hue2rgb($p, $q, $h);
            $b = self::hue2rgb($p, $q, $h - 1/3);
        }
        return [round($r * 255), round($g * 255), round($b * 255)];
    }

    private static function hue2rgb($p, $q, $t) {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1/2) return $q;
        if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
        return $p;
    }

    // 辅助函数：将值限制在 $min ~ $max 之间
    public static function clamp($value, $min, $max) {
        return max($min, min($value, $max));
    }

    /**
     * 分析图片数据，返回符合约束条件的主题色，格式为 rgba
     *
     * 约束条件：
     * 1. 统计量化后的颜色（容差 16），并仅考虑亮度（l）在 20～80 的颜色。
     * 2. 若所有符合亮度约束的颜色总像素占比低于 10%，则选取全图最常见颜色，并将其亮度调整为 65。
     * 3. 饱和度调整为区间 [30, 65]（低于 30设为30，高于65设为65）。
     */
    public static function getThemeColor($image_data) {
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }

        $im = imagecreatefromstring($image_data);
        if (!$im) {
            return false;
        }

        $width = imagesx($im);
        $height = imagesy($im);

        // Dominant color does not need the original resolution. Keeping the
        // sample below 64x64 changes millions of pixel reads into at most 4096.
        $sample_size = 64;
        if ($width > $sample_size || $height > $sample_size) {
            $scale = min($sample_size / $width, $sample_size / $height);
            $sample_width = max(1, (int) round($width * $scale));
            $sample_height = max(1, (int) round($height * $scale));
            $sample = imagecreatetruecolor($sample_width, $sample_height);

            if ($sample) {
                imagealphablending($sample, false);
                imagesavealpha($sample, true);
                $transparent = imagecolorallocatealpha($sample, 0, 0, 0, 127);
                imagefill($sample, 0, 0, $transparent);
                imagecopyresampled($sample, $im, 0, 0, 0, 0, $sample_width, $sample_height, $width, $height);
                imagedestroy($im);
                $im = $sample;
                $width = $sample_width;
                $height = $sample_height;
            }
        }

        $color_counts = [];
        $color_hsl = [];  // 存储每个量化颜色对应的 HSL 值
        $total_alpha = 0;
        $pixel_count = 0;
        $tolerance = 16;
        $qualifying_pixel_count = 0;  // 亮度在 [20,80] 的像素数

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $color_index = imagecolorat($im, $x, $y);
                $rgba = imagecolorsforindex($im, $color_index);
                $r = $rgba['red'];
                $g = $rgba['green'];
                $b = $rgba['blue'];
                $alpha = isset($rgba['alpha']) ? $rgba['alpha'] : 0;

                // Fully transparent pixels do not contribute a visible color.
                if ($alpha >= 120) {
                    continue;
                }

                // 根据容差量化颜色
                $r_quant = floor($r / $tolerance) * $tolerance;
                $g_quant = floor($g / $tolerance) * $tolerance;
                $b_quant = floor($b / $tolerance) * $tolerance;
                $key = "$r_quant,$g_quant,$b_quant";

                // 如果还未转换该颜色，则计算其 HSL 值
                if (!isset($color_hsl[$key])) {
                    list($h, $s, $l) = self::rgbToHsl($r_quant, $g_quant, $b_quant);
                    $color_hsl[$key] = ['h' => $h, 's' => $s, 'l' => $l];
                }
                $hsl = $color_hsl[$key];

                // 判断该颜色的亮度是否符合要求（20～80）
                $qualify = ($hsl['l'] >= 20 && $hsl['l'] <= 80);

                if (!isset($color_counts[$key])) {
                    $color_counts[$key] = 0;
                }
                $color_counts[$key]++;

                if ($qualify) {
                    $qualifying_pixel_count++;
                }

                $total_alpha += $alpha;
                $pixel_count++;
            }
        }
        imagedestroy($im);

        if ($pixel_count === 0) {
            return false;
        }

        // 判断符合条件的颜色总像素是否占比 >= 10%
        $qualifying_ratio = $qualifying_pixel_count / $pixel_count;
        $fallback = false;  // 是否需要回退

        if ($qualifying_ratio >= 0.10) {
            // 从符合亮度约束的颜色中选取出现次数最多的颜色
            $max_count = 0;
            $dominant_key = null;
            foreach ($color_counts as $key => $count) {
                $hsl = $color_hsl[$key];
                if ($hsl['l'] >= 20 && $hsl['l'] <= 80) {
                    if ($count > $max_count) {
                        $max_count = $count;
                        $dominant_key = $key;
                    }
                }
            }
            // 若没有找到，则回退
            if ($dominant_key === null) {
                $dominant_key = array_keys($color_counts, max($color_counts))[0];
                $fallback = true;
            }
        } else {
            // 若符合条件的像素总面积不足 10%，则取全图最常见的颜色，
            // 并在后续将其亮度调整为 65
            $dominant_key = array_keys($color_counts, max($color_counts))[0];
            $fallback = true;
        }

        list($dom_r, $dom_g, $dom_b) = explode(',', $dominant_key);
        list($h, $s, $l) = self::rgbToHsl($dom_r, $dom_g, $dom_b);

        // 保存原始亮度值，用于后续判断
        $original_l = $l;

        if ($fallback) {
            // 回退时，将亮度设置为 65
            $l = 65;
        } else {
            // 否则确保亮度在 20～80 之间
            $l = self::clamp($l, 20, 80);
        }
        
        // 新增：根据原始颜色调整亮度
        // 趋近于黑色时增加亮度
        if ($original_l < 30) {
            $l = max($l, 50); // 确保亮度至少为40
        }
        // 趋近于白色时降低亮度
        elseif ($original_l > 70) {
            $l = min($l, 60); // 确保亮度最高为65
        }
        
        // 饱和度调整为 30～65 之间（取高值约束，即不足30则设为30，超过65则设为65）
        $s = self::clamp($s, 40, 65);

        // 转换回 RGB
        list($final_r, $final_g, $final_b) = self::hslToRgb($h, $s, $l);

        // Convert GD's 0..127 alpha range to the CSS 0..1 range.
        $avg_alpha = round($total_alpha / $pixel_count);
        $alpha_converted = round((127 - $avg_alpha) / 127, 3);

        return "rgba($final_r, $final_g, $final_b, $alpha_converted)";
    }
}

function get_image_theme_color($input) {
    $image_data = false;
    $parsed_url = wp_parse_url($input);

    if ($parsed_url && !empty($parsed_url['scheme']) && !empty($parsed_url['host'])) {
        $remote_response = wp_safe_remote_get(esc_url_raw($input), [
            'timeout' => 3,
            'redirection' => 3,
            'limit_response_size' => 5 * MB_IN_BYTES,
        ]);
        if (is_wp_error($remote_response) || wp_remote_retrieve_response_code($remote_response) !== 200) {
            return false;
        }
        $image_data = wp_remote_retrieve_body($remote_response);
    } elseif (is_string($input) && is_readable($input)) {
        $image_data = file_get_contents($input);
    }

    if (!$image_data) {
        return false;
    }

    return ColorAnalyzer::getThemeColor($image_data);
}

function sakurairo_get_attachment_theme_color_source($attachment_id) {
    $attachment_id = absint($attachment_id);
    $original_path = get_attached_file($attachment_id);

    if ($original_path && is_readable($original_path)) {
        $metadata = wp_get_attachment_metadata($attachment_id);
        $smallest_path = '';
        $smallest_area = PHP_INT_MAX;

        if (is_array($metadata) && !empty($metadata['sizes'])) {
            // "medium" normally preserves the complete composition, unlike a
            // cropped square thumbnail, while remaining inexpensive to decode.
            foreach (['medium', 'thumbnail'] as $preferred_size) {
                if (empty($metadata['sizes'][$preferred_size]['file'])) {
                    continue;
                }
                $preferred_path = path_join(dirname($original_path), $metadata['sizes'][$preferred_size]['file']);
                if (is_readable($preferred_path)) {
                    return $preferred_path;
                }
            }

            foreach ($metadata['sizes'] as $size) {
                if (empty($size['file'])) {
                    continue;
                }

                $candidate = path_join(dirname($original_path), $size['file']);
                $area = max(1, (int) ($size['width'] ?? 0)) * max(1, (int) ($size['height'] ?? 0));
                if (is_readable($candidate) && $area < $smallest_area) {
                    $smallest_path = $candidate;
                    $smallest_area = $area;
                }
            }
        }

        return $smallest_path ?: $original_path;
    }

    // Media-offload plugins may intentionally remove the local file.
    return wp_get_attachment_image_url($attachment_id, 'thumbnail') ?: '';
}

function sakurairo_get_attachment_theme_color_fingerprint($attachment_id, $source) {
    $attachment = get_post($attachment_id);
    $metadata = wp_get_attachment_metadata($attachment_id);
    $modified = $attachment ? $attachment->post_modified_gmt : '';
    $file_modified = is_string($source) && is_file($source) ? (int) filemtime($source) : 0;

    return hash('sha256', wp_json_encode([
        'version' => 2,
        'attachment_id' => (int) $attachment_id,
        'modified' => $modified,
        'file_modified' => $file_modified,
        'file' => is_array($metadata) ? ($metadata['file'] ?? '') : '',
        'sizes' => is_array($metadata) ? ($metadata['sizes'] ?? []) : [],
    ]));
}

function sakurairo_update_post_theme_color($post_id, $force = false) {
    $post_id = absint($post_id);
    if (!$post_id || !in_array(get_post_type($post_id), ['post', 'shuoshuo'], true) || wp_is_post_revision($post_id)) {
        return;
    }

    $thumbnail_id = get_post_thumbnail_id($post_id);
    if (!$thumbnail_id) {
        update_post_meta($post_id, 'post_theme_color_meta', [
            'algorithm_version' => 2,
            'thumbnail_id' => 0,
            'fingerprint' => '',
            'theme_color' => 'false',
        ]);
        return;
    }

    $source = sakurairo_get_attachment_theme_color_source($thumbnail_id);
    $fingerprint = sakurairo_get_attachment_theme_color_fingerprint($thumbnail_id, $source);
    $current = get_post_meta($post_id, 'post_theme_color_meta', true);
    $current = is_array($current) ? $current : [];

    if (!$force
        && (int) ($current['algorithm_version'] ?? 0) === 2
        && (int) ($current['thumbnail_id'] ?? 0) === (int) $thumbnail_id
        && ($current['fingerprint'] ?? '') === $fingerprint
        && array_key_exists('theme_color', $current)) {
        return;
    }

    $theme_color = $source ? get_image_theme_color($source) : false;
    update_post_meta($post_id, 'post_theme_color_meta', [
        'algorithm_version' => 2,
        'thumbnail_id' => (int) $thumbnail_id,
        'fingerprint' => $fingerprint,
        'theme_color' => $theme_color === false ? 'false' : $theme_color,
    ]);
}

function sakurairo_refresh_post_theme_color_on_save($post_id, $post) {
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
        return;
    }

    sakurairo_update_post_theme_color($post_id);
}

add_action('save_post_post', 'sakurairo_refresh_post_theme_color_on_save', 20, 2);
add_action('save_post_shuoshuo', 'sakurairo_refresh_post_theme_color_on_save', 20, 2);

function sakurairo_refresh_post_theme_color_on_thumbnail_change($meta_id, $object_id, $meta_key) {
    if ($meta_key === '_thumbnail_id') {
        sakurairo_update_post_theme_color($object_id, true);
    }
}

add_action('added_post_meta', 'sakurairo_refresh_post_theme_color_on_thumbnail_change', 10, 3);
add_action('updated_post_meta', 'sakurairo_refresh_post_theme_color_on_thumbnail_change', 10, 3);
add_action('deleted_post_meta', 'sakurairo_refresh_post_theme_color_on_thumbnail_change', 10, 3);

function sakurairo_schedule_post_theme_color($post_id) {
    $post_id = absint($post_id);
    $args = [$post_id];
    if ($post_id && !wp_next_scheduled('sakurairo_generate_post_theme_color', $args)) {
        wp_schedule_single_event(time() + MINUTE_IN_SECONDS, 'sakurairo_generate_post_theme_color', $args);
    }
}

function sakurairo_generate_scheduled_post_theme_color($post_id) {
    sakurairo_update_post_theme_color($post_id, true);
}

add_action('sakurairo_generate_post_theme_color', 'sakurairo_generate_scheduled_post_theme_color');

function get_post_theme_color($post_id) {
    $meta = get_post_meta($post_id, 'post_theme_color_meta', true);
    if (!is_array($meta) || empty($meta['theme_color'])) {
        if (get_post_thumbnail_id($post_id)) {
            // Existing posts are backfilled outside the public request. The
            // current response uses the normal theme color until cron finishes.
            sakurairo_schedule_post_theme_color($post_id);
        }
        return 'false';
    }

    return $meta['theme_color'];
}
?>
