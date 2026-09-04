<?php
/**
 * AutoBacklink - Helpers (HTTP, text, files, logging)
 */
require_once __DIR__ . '/config.php';

function nowString() {
    return date('Y-m-d H:i:s');
}

function escapeHtml($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function slugify($text) {
    $text = strtolower(trim((string)$text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'item';
}

function bkLog($msg) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    error_log($line);
    try {
        $dir = dirname(LOG_FILE);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents(LOG_FILE, $line, FILE_APPEND);
    } catch (Exception $e) {}
}

function addRunLog($message) {
    try {
        $db = getDB();
        $st = $db->prepare('INSERT INTO run_log (run_date, message, created_at) VALUES (date(\'now\'), ?, datetime(\'now\'))');
        $st->execute([$message]);
    } catch (Exception $e) {}
    bkLog($message);
}

/**
 * HTTP GET/POST. Uses cURL on real servers (Hostinger).
 * In sandbox preview (php-wasm, no cURL ext) returns a soft failure so the
 * app degrades gracefully; all core flows work without outbound HTTP.
 */
function bkHttp($method, $url, $headers = [], $body = null, $timeout = 30) {
    if (SANDBOX_MODE) {
        return ['success' => false, 'http_code' => 0, 'data' => null, 'raw' => '', 'content_type' => '', 'error' => 'Outbound HTTP is disabled in the live preview sandbox. It works normally once deployed to your subdomain.'];
    }
    if (!function_exists('curl_init')) {
        // Fallback to stream context (some hosts)
        $opts = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'timeout' => $timeout,
                'ignore_errors' => true,
            ]
        ];
        if ($body !== null) $opts['http']['content'] = $body;
        $ctx = stream_context_create($opts);
        $raw = @file_get_contents($url, false, $ctx);
        $code = 0;
        if (!empty($http_response_header[0] ?? null)) {
            sscanf($http_response_header[0], 'HTTP/%*[^ ] %d', $code);
        }
        return ['success' => $code >= 200 && $code < 300, 'http_code' => $code, 'data' => json_decode($raw, true), 'raw' => $raw, 'content_type' => '', 'error' => $raw === false ? 'Request failed' : ''];
    }

    $ch = curl_init($url);
    $headerLines = [];
    foreach ($headers as $h) $headerLines[] = $h;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headerLines,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36',
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ct = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $err = curl_error($ch);
    curl_close($ch);
    $data = json_decode((string)$raw, true);
    return [
        'success' => $code >= 200 && $code < 300,
        'http_code' => $code,
        'data' => $data,
        'raw' => (string)$raw,
        'content_type' => $ct,
        'error' => $raw === false ? $err : '',
    ];
}

function curlGet($url, $headers = [], $timeout = 15) {
    return bkHttp('GET', $url, $headers, null, $timeout);
}

function curlPost($url, $payload, $headers = ['Content-Type: application/json'], $timeout = 30) {
    return bkHttp('POST', $url, $headers, is_string($payload) ? $payload : json_encode($payload), $timeout);
}

/**
 * Save an image (data-URI or http URL) to a local file. Returns relative path or ''.
 */
function bkSaveImage($source, $destFile) {
    try {
        if (str_starts_with($source, 'data:')) {
            if (preg_match('#^data:([a-z/+-]+);base64,(.+)$#is', $source, $m)) {
                $bin = base64_decode($m[2]);
                if ($bin === false) return '';
                if (!is_dir(dirname($destFile))) mkdir(dirname($destFile), 0755, true);
                file_put_contents($destFile, $bin);
                return $destFile;
            }
            return '';
        }
        // Remote URL — needs outbound HTTP (real server)
        if (SANDBOX_MODE) return '';
        $res = bkHttp('GET', $source, [], null, 60);
        if ($res['success'] && strlen((string)$res['raw']) > 500) {
            if (!is_dir(dirname($destFile))) mkdir(dirname($destFile), 0755, true);
            file_put_contents($destFile, $res['raw']);
            return $destFile;
        }
        return '';
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Fallback placeholder image (base64 PNG gradient) used when no Image API is
 * configured or the API fails. Generated with GD if available, else embedded.
 */
function bkPlaceholderImage($topic, $destFile) {
    $safe = slugify($topic);
    try {
        if (!is_dir(dirname($destFile))) mkdir(dirname($destFile), 0755, true);
        if (function_exists('imagecreatetruecolor')) {
            $w = 1200; $h = 675;
            $im = imagecreatetruecolor($w, $h);
            $c1 = imagecolorallocate($im, 30, 41, 59);
            $c2 = imagecolorallocate($im, 79, 70, 229);
            for ($y = 0; $y < $h; $y++) {
                $t = $y / $h;
                $r = (int)(30 + (79 - 30) * $t);
                $g = (int)(41 + (70 - 41) * $t);
                $b = (int)(59 + (229 - 59) * $t);
                $c = imagecolorallocate($im, $r, $g, $b);
                imageline($im, 0, $y, $w, $y, $c);
            }
            imagepng($im, $destFile, 6);
            imagedestroy($im);
            return $destFile;
        }
        // No GD: copy the bundled gradient image
        $src = __DIR__ . '/fallback_img.png';
        if (file_exists($src)) {
            copy($src, $destFile);
            return file_exists($destFile) ? $destFile : '';
        }
        return '';
    } catch (Exception $e) {
        return '';
    }
}
