<?php
header('Content-Type: text/css; charset=UTF-8');

$style_files = array(
    '../style.css',
    'shortcodes.css',
    'dark.css',
    'responsive.css',
    'animation.css',
    'templates.css',
);

if (isset($_GET['sakura_header'])) {
    $style_files[] = 'sakura_header.css';
}
if (isset($_GET['wave'])) {
    $style_files[] = 'wave.css';
}
if (isset($_GET['github'])) {
    $style_files[] = 'content-style/github.css';
}
if (isset($_GET['sakura'])) {
    $style_files[] = 'content-style/sakura.css';
}

$minify = isset($_GET['minify']);
$resolved_styles = array();
$latest_modified = 0;
$cache_fingerprint = array(
    $minify ? 'minify' : 'plain',
    __FILE__ . ':' . (int) filemtime(__FILE__),
);

foreach ($style_files as $style) {
    $file_path = realpath(__DIR__ . '/' . $style);
    if ($file_path === false || !is_file($file_path) || !is_readable($file_path)) {
        continue;
    }

    $modified = (int) filemtime($file_path);
    $size = (int) filesize($file_path);
    $latest_modified = max($latest_modified, $modified);
    $cache_fingerprint[] = $file_path . ':' . $modified . ':' . $size;
    $resolved_styles[] = array(
        'path' => $file_path,
        'name' => basename($style),
    );
}

$cache_key = hash('sha256', implode('|', $cache_fingerprint));
$etag = '"' . $cache_key . '"';
$versioned_request = isset($_GET['ver']) && (string) $_GET['ver'] !== '';
$max_age = $versioned_request ? 31536000 : 86400;

header('Cache-Control: public, max-age=' . $max_age . ($versioned_request ? ', immutable' : ''));
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $max_age) . ' GMT');
header('ETag: ' . $etag);
if ($latest_modified > 0) {
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $latest_modified) . ' GMT');
}

$if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) : '';
$normalized_if_none_match = preg_replace('/-gzip(?=")/', '', $if_none_match);
$if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime((string) $_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
if ($normalized_if_none_match === $etag || ($if_none_match === '' && $if_modified_since !== false && $latest_modified > 0 && $if_modified_since >= $latest_modified)) {
    http_response_code(304);
    exit;
}

$cache_directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sakurairo-css-cache';
$cache_file = $cache_directory . DIRECTORY_SEPARATOR . $cache_key . '.css';
if (is_readable($cache_file)) {
    $cache_size = filesize($cache_file);
    if ($cache_size !== false) {
        header('Content-Length: ' . $cache_size);
    }
    readfile($cache_file);
    exit;
}

function sakurairo_compress_css($css)
{
    $css = preg_replace('/\/\*.*?\*\//s', '', $css);
    $css = preg_replace('/\s*([{};:,])\s*/', '$1', $css);
    $css = preg_replace('/;}/', '}', $css);
    return trim($css);
}

$output = '';
foreach ($resolved_styles as $style) {
    $content = file_get_contents($style['path']);
    if ($content === false) {
        continue;
    }

    $output .= "\n/* === " . $style['name'] . " === */\n";
    $output .= $minify ? sakurairo_compress_css($content) : $content;
}

if ((is_dir($cache_directory) || @mkdir($cache_directory, 0755, true)) && is_writable($cache_directory)) {
    $temporary_cache_file = $cache_file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($temporary_cache_file, $output, LOCK_EX) !== false) {
        @rename($temporary_cache_file, $cache_file);
    }
    if (is_file($temporary_cache_file)) {
        @unlink($temporary_cache_file);
    }
}

header('Content-Length: ' . strlen($output));
echo $output;
