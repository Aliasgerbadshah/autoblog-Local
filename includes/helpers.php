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
    // Clean any stray output (PHP warnings/HTML) that would corrupt JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
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

    // Return raw response in 'data' (for HTML pages like ResearchAgent crawl),
    // and also provide JSON-decoded version in 'json' (for API responses like Blogger test)
    $json = json_decode($response, true);
    return ['success' => true, 'data' => $response, 'json' => $json ?: [], 'http_code' => $httpCode];
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

function getTopicsCsvPath() {
    return dirname(__DIR__) . '/data/used_topics.csv';
}

function addTopicToCsv($topic, $keyword, $domain, $status, $campaignId, $date = null) {
    if (!$date) $date = date('Y-m-d H:i:s');
    $csvPath = getTopicsCsvPath();
    $writeHeader = !file_exists($csvPath) || @filesize($csvPath) === 0;
    $fp = @fopen($csvPath, 'a');
    if (!$fp) return;
    if ($writeHeader) fputcsv($fp, ['S.No', 'Topic (Blog Title)', 'Primary Keyword', 'Domain/Website', 'Status', 'Campaign ID', 'Created Date']);
    $sno = 1;
    if (file_exists($csvPath)) { $lines = file($csvPath); $sno = count($lines); }
    fputcsv($fp, [$sno, $topic, $keyword, $domain, $status, $campaignId, $date]);
    fclose($fp);
}

function syncTopicsCsv() {
    $csvPath = getTopicsCsvPath();
    $db = getDB();
    $allTopics = [];
    // Source 1: JSON file
    $topicFilePath = dirname(__DIR__) . '/data/used_topics.json';
    if (file_exists($topicFilePath)) {
        $topicFile = json_decode(file_get_contents($topicFilePath), true);
        if (!empty($topicFile['topics'])) {
            foreach ($topicFile['topics'] as $t) {
                $key = strtolower(trim($t['topic'] ?? '')) . '|' . strtolower(trim($t['keyword'] ?? ''));
                $allTopics[$key] = ['topic' => $t['topic'] ?? '', 'keyword' => $t['keyword'] ?? '', 'domain' => $t['domain'] ?? '', 'status' => $t['status'] ?? 'unknown', 'campaign_id' => $t['campaign_id'] ?? '', 'date' => $t['date'] ?? ''];
            }
        }
    }
    // Source 2: DB created_blog_topics
    try {
        $stmt = $db->query('SELECT cbt.title, cbt.primary_keyword, cbt.domain_url, cbt.campaign_id, cbt.created_at, ci.article_status FROM created_blog_topics cbt LEFT JOIN campaign_items ci ON ci.campaign_id = cbt.campaign_id AND ci.title = cbt.title ORDER BY cbt.created_at ASC');
        foreach ($stmt->fetchAll() as $row) {
            $key = strtolower(trim($row['title'])) . '|' . strtolower(trim($row['primary_keyword']));
            $status = $row['article_status'] ?? 'unknown';
            if ($status === 'Published') $status = 'published'; elseif ($status === 'Final Article Approved' || $status === 'HTML Ready') $status = 'approved'; elseif ($status === 'Scheduled') $status = 'scheduled'; elseif (strpos($status, 'Reject') !== false) $status = 'rejected'; elseif ($status === 'Not Created' || $status === '') $status = 'pending';
            $allTopics[$key] = ['topic' => $row['title'], 'keyword' => $row['primary_keyword'], 'domain' => $row['domain_url'] ?? '', 'status' => $status, 'campaign_id' => $row['campaign_id'] ?? '', 'date' => $row['created_at'] ?? ''];
        }
    } catch (Exception $e) {}
    // Source 3: campaign_items
    try {
        $stmt = $db->query('SELECT ci.title, ci.primary_keyword, ci.article_status, ci.campaign_id, c.domain_url, c.created_at as camp_created FROM campaign_items ci JOIN campaigns c ON c.id = ci.campaign_id ORDER BY ci.id ASC');
        foreach ($stmt->fetchAll() as $row) {
            $key = strtolower(trim($row['title'])) . '|' . strtolower(trim($row['primary_keyword']));
            if (isset($allTopics[$key])) continue;
            $status = $row['article_status'] ?? 'unknown';
            if ($status === 'Published') $status = 'published'; elseif ($status === 'Final Article Approved' || $status === 'HTML Ready') $status = 'approved'; elseif ($status === 'Scheduled') $status = 'scheduled'; elseif (strpos($status, 'Reject') !== false) $status = 'rejected'; elseif ($status === 'Not Created' || $status === '') $status = 'pending';
            $allTopics[$key] = ['topic' => $row['title'], 'keyword' => $row['primary_keyword'], 'domain' => $row['domain_url'] ?? '', 'status' => $status, 'campaign_id' => $row['campaign_id'] ?? '', 'date' => $row['camp_created'] ?? ''];
        }
    } catch (Exception $e) {}
    uasort($allTopics, function($a, $b) { return strcmp($a['date'] ?? '', $b['date'] ?? ''); });
    $fp = @fopen($csvPath, 'w');
    if (!$fp) return count($allTopics);
    fputcsv($fp, ['S.No', 'Topic (Blog Title)', 'Primary Keyword', 'Domain/Website', 'Status', 'Campaign ID', 'Created Date']);
    $sno = 1;
    foreach ($allTopics as $t) { fputcsv($fp, [$sno++, $t['topic'], $t['keyword'], $t['domain'], $t['status'], $t['campaign_id'], $t['date']]); }
    fclose($fp);
    return count($allTopics);
}

function getUsedTopicsFromCsv() {
    $csvPath = getTopicsCsvPath();
    if (!file_exists($csvPath)) return [];
    $topics = [];
    $fp = @fopen($csvPath, 'r');
    if (!$fp) return [];
    while (($row = fgetcsv($fp)) !== false) {
        if (count($row) >= 3) $topics[] = ['topic' => $row[1] ?? '', 'keyword' => $row[2] ?? '', 'domain' => $row[3] ?? '', 'status' => $row[4] ?? '', 'campaign_id' => $row[5] ?? '', 'date' => $row[6] ?? ''];
    }
    fclose($fp);
    return $topics;
}

/**
 * Get ALL used topics from ALL sources: JSON file, DB created_blog_topics, 
 * campaign_items, and CSV. Returns array of ['topic' => ..., 'keyword' => ...].
 */
function getAllUsedTopics($db, $userId = null) {
    $allTopics = [];
    
    // Source 1: JSON file (persistent, survives redeployment)
    $topicFilePath = dirname(__DIR__) . '/data/used_topics.json';
    if (file_exists($topicFilePath)) {
        $topicFile = json_decode(file_get_contents($topicFilePath), true);
        if (!empty($topicFile['topics'])) {
            foreach ($topicFile['topics'] as $t) {
                $key = strtolower(trim($t['topic'] ?? ''));
                if (!empty($key)) $allTopics[$key] = ['topic' => $t['topic'] ?? '', 'keyword' => $t['keyword'] ?? ''];
            }
        }
    }
    
    // Source 2: campaign_items table (most reliable)
    try {
        $sql = 'SELECT title, primary_keyword FROM campaign_items';
        if ($userId) $sql .= ' ci JOIN campaigns c ON c.id = ci.campaign_id WHERE c.user_id = ' . intval($userId);
        $stmt = $db->query($sql);
        foreach ($stmt->fetchAll() as $row) {
            $key = strtolower(trim($row['title'] ?? ''));
            if (!empty($key)) $allTopics[$key] = ['topic' => $row['title'], 'keyword' => $row['primary_keyword'] ?? ''];
        }
    } catch (Exception $e) {}
    
    // Source 3: created_blog_topics table
    try {
        $sql = 'SELECT title, primary_keyword FROM created_blog_topics';
        if ($userId) $sql .= ' WHERE user_id = ' . intval($userId);
        $stmt = $db->query($sql);
        foreach ($stmt->fetchAll() as $row) {
            $key = strtolower(trim($row['title'] ?? ''));
            if (!empty($key)) $allTopics[$key] = ['topic' => $row['title'], 'keyword' => $row['primary_keyword'] ?? ''];
        }
    } catch (Exception $e) {}
    
    // Source 4: CSV file
    $csvTopics = getUsedTopicsFromCsv();
    foreach ($csvTopics as $t) {
        $key = strtolower(trim($t['topic'] ?? ''));
        if (!empty($key)) $allTopics[$key] = ['topic' => $t['topic'], 'keyword' => $t['keyword'] ?? ''];
    }
    
    return array_values($allTopics);
}

/**
 * Check if a topic is a duplicate of any existing topic.
 * Uses both exact match and fuzzy matching (levenshtein/similar_text).
 * Returns true if the topic is a duplicate.
 */
function isTopicDuplicate($newTitle, $newKeyword, $existingTopics, $threshold = 0.78) {
    $newTitleLower = strtolower(trim($newTitle));
    $newKeywordLower = strtolower(trim($newKeyword));
    
    foreach ($existingTopics as $existing) {
        $existingTitleLower = strtolower(trim($existing['topic'] ?? ''));
        $existingKeywordLower = strtolower(trim($existing['keyword'] ?? ''));
        
        // Exact title match
        if ($newTitleLower === $existingTitleLower) return true;
        
        // Exact keyword match
        if (!empty($newKeywordLower) && !empty($existingKeywordLower) && $newKeywordLower === $existingKeywordLower) return true;
        
        // Fuzzy title match using similar_text
        if (!empty($newTitleLower) && !empty($existingTitleLower)) {
            similar_text($newTitleLower, $existingTitleLower, $percent);
            if ($percent >= ($threshold * 100)) return true;
        }
        
        // Fuzzy keyword match
        if (!empty($newKeywordLower) && !empty($existingKeywordLower)) {
            similar_text($newKeywordLower, $existingKeywordLower, $kwPercent);
            if ($kwPercent >= 85) return true; // Keywords must be very similar to be duplicate
        }
        
        // Levenshtein check for near-duplicates (edit distance)
        if (!empty($newTitleLower) && !empty($existingTitleLower)) {
            $maxLen = max(strlen($newTitleLower), strlen($existingTitleLower));
            if ($maxLen > 0) {
                $levDist = levenshtein($newTitleLower, $existingTitleLower);
                $similarity = 1 - ($levDist / $maxLen);
                if ($similarity >= $threshold) return true;
            }
        }
    }
    
    return false;
}

/**
 * Add a topic to the persistent JSON file (survives redeployment).
 */
function addTopicToJsonFile($topic, $keyword, $domain, $status = 'pending', $campaignId = '') {
    $topicFilePath = dirname(__DIR__) . '/data/used_topics.json';
    $data = ['_instructions' => '', 'topics' => []];
    if (file_exists($topicFilePath)) {
        $data = json_decode(file_get_contents($topicFilePath), true) ?: ['topics' => []];
    }
    $data['_last_updated'] = date('Y-m-d');
    $data['topics'][] = [
        'topic' => $topic,
        'keyword' => $keyword,
        'domain' => $domain!=='published_posts'? $domain : '',
        'status' => $status,
        'campaign_id' => $campaignId,
        'date' => date('Y-m-d')
    ];
    @file_put_contents($topicFilePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Sync all topics to both JSON file and CSV (auto-sync on campaign creation).
 */
function syncAllTopics($db, $userId = null) {
    $csvCount = syncTopicsCsv();
    return $csvCount;
}
