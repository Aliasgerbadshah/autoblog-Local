<?php
/**
 * AutoBlog SaaS - Helper Functions
 */

function slugify($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^\w\s-]/', '', $text);
    $text = preg_replace('/[-\s]+/', '-', $text);
    return trim($text, '-');
}

function escapeHtml($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function generateToken($length = 24) {
    return bin2hex(random_bytes($length));
}

function generateOtp() {
    return strval(random_int(100000, 999999));
}

function nowString() {
    return date('Y-m-d H:i:s');
}

function curlPost($url, $payload, $headers = [], $timeout = 12) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => is_array($payload) ? json_encode($payload) : $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => $error, 'http_code' => $httpCode];
    }

    $data = json_decode($response, true);
    return ['success' => true, 'data' => $data, 'http_code' => $httpCode, 'raw' => $response];
}

function curlGet($url, $headers = [], $timeout = 10) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => $error, 'http_code' => $httpCode];
    }

    return ['success' => true, 'data' => $response, 'http_code' => $httpCode];
}

function curlPostForm($url, $payload, $headers = [], $timeout = 12) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => $error, 'http_code' => $httpCode];
    }

    $data = json_decode($response, true);
    return ['success' => true, 'data' => $data ?: [], 'http_code' => $httpCode, 'raw' => $response];
}

function ensureDir($path) {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

/**
 * Validate that an image URL is accessible and returns an image.
 * Returns true if URL responds with 200 and image content-type.
 */
function validateImageUrl($url, $timeout = 5) {
    // Skip data: URIs (always valid if present)
    if (str_starts_with($url, 'data:image/')) return true;
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_NOBODY => true,  // HEAD request only
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/115.0.0.0',
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    if ($httpCode !== 200) return false;
    // Accept any image content type or unknown
    if ($contentType && !preg_match('/image\//i', $contentType) && !preg_match('/octet-stream/i', $contentType)) return false;
    return true;
}
