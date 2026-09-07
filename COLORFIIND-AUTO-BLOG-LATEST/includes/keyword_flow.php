<?php
/**
 * Google Keyword Planner is the only keyword source.
 * AI may write titles/headings; it must not invent volume or difficulty.
 */

function keywordPlannerLocationAndLanguage($country = 'India', $language = 'en') {
    $locationMap = ['india' => 2356, 'united states' => 2840, 'usa' => 2840, 'united kingdom' => 2826, 'canada' => 2124, 'australia' => 2036, 'uae' => 2784, 'germany' => 2276, 'france' => 2250, 'brazil' => 2070, 'japan' => 2392];
    $langMap = ['en' => '1000', 'hi' => '1001', 'es' => '1003', 'fr' => '1002', 'de' => '1004', 'pt' => '1006', 'ja' => '1005', 'ar' => '1019'];
    return [
        $locationMap[strtolower((string)$country)] ?? 2356,
        $langMap[$language] ?? '1000',
    ];
}

function customTopicsCsvPath() {
    $cands = [
        dirname(__DIR__) . '/data/custom_topics.csv',
        dirname(__DIR__, 2) . '/data/custom_topics.csv',
    ];
    foreach ($cands as $p) {
        if (file_exists($p)) return $p;
    }
    $dir = dirname(__DIR__) . '/data';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . '/custom_topics.csv';
}

function readCustomTopicsList() {
    $csvPath = customTopicsCsvPath();
    $topics = [];
    if (!file_exists($csvPath)) return $topics;
    $fp = @fopen($csvPath, 'r');
    if (!$fp) return $topics;
    fgetcsv($fp);
    while (($row = fgetcsv($fp)) !== false) {
        if (!empty($row[0]) && trim($row[0]) !== '') $topics[] = trim($row[0]);
    }
    fclose($fp);
    return $topics;
}

function writeCustomTopicsList($topics) {
    $csvPath = customTopicsCsvPath();
    $dir = dirname($csvPath);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fp = @fopen($csvPath, 'w');
    if (!$fp) return false;
    fputcsv($fp, ['Topic']);
    foreach ($topics as $t) {
        $t = trim((string)$t);
        if ($t !== '') fputcsv($fp, [$t]);
    }
    fclose($fp);
    return true;
}

function takeNextCustomTopic() {
    $topics = readCustomTopicsList();
    if (empty($topics)) return '';
    $next = array_shift($topics);
    writeCustomTopicsList($topics);
    return $next;
}

function limitPlanKeywords($keywords, $max = 5) {
    $rows = array_values(array_filter((array)$keywords, fn($x) => !empty($x['keyword'])));
    $rows = array_slice($rows, 0, $max);
    foreach ($rows as $i => &$r) {
        $r['role'] = $i === 0 ? 'primary' : 'secondary';
        $r['source'] = 'Google Keyword Planner';
        if (!isset($r['volume']) || $r['volume'] === '' || $r['volume'] === null) $r['volume'] = 0;
        if (is_string($r['volume']) && !is_numeric($r['volume'])) $r['volume'] = 0;
        $r['volume'] = intval($r['volume']);
        if (empty($r['difficulty'])) $r['difficulty'] = 'Unknown';
    }
    unset($r);
    return $rows;
}

function plannerRowsToKeywordData($rows) {
    $out = [];
    foreach ((array)$rows as $i => $row) {
        $out[] = [
            'keyword' => $row['keyword'] ?? '',
            'volume' => intval($row['volume'] ?? 0),
            'difficulty' => $row['difficulty'] ?? 'Unknown',
            'intent' => $row['intent'] ?? 'Informational',
            'source' => 'Google Keyword Planner',
            'cpc' => $row['cpc'] ?? null,
            'competition_index' => $row['competition_index'] ?? null,
            'role' => $row['role'] ?? ($i === 0 ? 'primary' : 'secondary'),
        ];
    }
    return $out;
}

function keywordsLookLikeAiEstimates($keywords) {
    foreach ((array)$keywords as $row) {
        $vol = strtolower((string)($row['volume'] ?? ''));
        $src = strtolower((string)($row['source'] ?? ''));
        if (strpos($vol, 'ai') !== false || strpos($vol, 'estimate') !== false || strpos($vol, 'custom') !== false) return true;
        if ($src === 'ai' || strpos($src, 'ai ') !== false) return true;
    }
    return false;
}

function cleanOAuthValue($value) {
    $value = trim((string)$value);
    $value = trim($value, " \t\n\r\0\x0B\"'");
    $value = preg_replace('/\s+/', '', $value);
    return $value;
}

function resolveKeywordPlannerOAuth($userId, $input = []) {
    $gkw = SecurityVault::getApiCredentials($userId, 'google_keyword_planner') ?: [];
    $blog = SecurityVault::getApiCredentials($userId, 'blogger_api') ?: [];
    $clientId = cleanOAuthValue($input['client_id'] ?? '') ?: cleanOAuthValue($gkw['client_id'] ?? '') ?: cleanOAuthValue($blog['client_id'] ?? '');
    $clientSecret = cleanOAuthValue($input['client_secret'] ?? '') ?: cleanOAuthValue($gkw['client_secret'] ?? '') ?: cleanOAuthValue($blog['client_secret'] ?? '');
    $refreshToken = cleanOAuthValue($input['refresh_token'] ?? '') ?: cleanOAuthValue($gkw['refresh_token'] ?? '') ?: cleanOAuthValue($blog['refresh_token'] ?? '');
    return [$clientId, $clientSecret, $refreshToken];
}

function fetchKeywordPlannerKeywords($userId, $seedKeywords, $country = 'India', $language = 'en') {
    $gkw = SecurityVault::getApiCredentials($userId, 'google_keyword_planner') ?: [];
    $developerToken = trim($gkw['developer_token'] ?? '');
    $customerId = trim($gkw['customer_id'] ?? '');
    $loginCustomerId = trim($gkw['login_customer_id'] ?? $customerId);
    if ($developerToken === '' || $customerId === '') {
        return ['success' => false, 'error' => 'Keyword Planner is not saved. Save Developer Token + TEST Customer ID in API Vault first.'];
    }
    list($clientId, $clientSecret, $refreshToken) = resolveKeywordPlannerOAuth($userId);
    if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
        return ['success' => false, 'error' => 'Keyword Planner OAuth is missing. Save Client ID, Client Secret, and Refresh Token in the Keyword Planner vault.'];
    }
    $tokenResult = GoogleKeywordPlanner::getAccessToken($clientId, $clientSecret, $refreshToken);
    if (empty($tokenResult['success'])) {
        return ['success' => false, 'error' => 'Keyword Planner OAuth failed: ' . ($tokenResult['error'] ?? 'unknown')];
    }
    list($locationId, $languageCode) = keywordPlannerLocationAndLanguage($country, $language);
    $seeds = array_values(array_filter(array_map('trim', (array)$seedKeywords)));
    if (empty($seeds)) $seeds = ['digital marketing'];
    $topic = $seeds[0];
    $seeds = GoogleKeywordPlanner::expandSeedKeywords($topic, $seeds[0]);
    $result = GoogleKeywordPlanner::generateKeywordIdeas(
        $developerToken, $customerId, $loginCustomerId, $tokenResult['access_token'],
        $seeds, $languageCode, $locationId
    );
    if (!empty($result['success'])) {
        $result['keywords'] = GoogleKeywordPlanner::selectBestTargetKeywords($result['keywords'] ?? [], $topic);
        $result['count'] = count($result['keywords']);
        $result['primary_keyword'] = $result['keywords'][0]['keyword'] ?? $topic;
    }
    return $result;
}

function applyPlannerKeywordsToPlan(&$plan, $userId, $country, $language, $keepTitle = false) {
    $topic = $plan['title'] ?? $plan['primary_keyword'] ?? '';
    $seed = $plan['primary_keyword'] ?? $topic;
    $kwRes = fetchKeywordPlannerKeywords($userId, [$seed, $topic], $country, $language);
    if (empty($kwRes['success'])) {
        return $kwRes;
    }
    $rows = array_slice($kwRes['keywords'] ?? [], 0, 5);
    if (empty($rows)) {
        return ['success' => false, 'error' => 'Keyword Planner returned no keyword ideas for: ' . $topic];
    }
    $plan['keywords'] = limitPlanKeywords(plannerRowsToKeywordData($rows), 5);
    $primary = $rows[0]['keyword'] ?? $seed;
    $plan['primary_keyword'] = $primary;
    if (!$keepTitle) {
        $title = $plan['title'] ?? $topic;
        if ($primary && stripos($title, $primary) === false) {
            $plan['title'] = ucwords($primary);
            $heads = $plan['headings'] ?? [];
            if (!is_array($heads)) $heads = [];
            $heads['H1'] = ucwords($primary);
            $plan['headings'] = $heads;
        }
    }
    return ['success' => true, 'primary_keyword' => $primary, 'count' => count($rows), 'keywords' => $plan['keywords']];
}

function requirePlannerKeywordsOnPlan(&$plan, $userId, $country, $language, $keepTitle = true) {
    $res = applyPlannerKeywordsToPlan($plan, $userId, $country, $language, $keepTitle);
    if (empty($res['success'])) {
        return $res;
    }
    if (keywordsLookLikeAiEstimates($plan['keywords'] ?? [])) {
        return ['success' => false, 'error' => 'Refusing AI keyword estimates. Keyword Planner did not return real volumes.'];
    }
    return $res;
}

function formatKeywordSummary($keywordData, $primary = '') {
    $rows = is_string($keywordData) ? (json_decode($keywordData, true) ?: []) : (array)$keywordData;
    if (empty($rows) && $primary !== '') {
        return escapeHtml($primary);
    }
    $bits = [];
    foreach (array_slice($rows, 0, 5) as $i => $r) {
        $kw = $r['keyword'] ?? '';
        if ($kw === '') continue;
        $role = $i === 0 ? 'P' : 'S';
        $vol = isset($r['volume']) && $r['volume'] !== '' ? intval($r['volume']) : 0;
        $diff = $r['difficulty'] ?? '';
        $bits[] = $role . ': ' . $kw . ' (vol ' . $vol . ($diff ? ', ' . $diff : '') . ')';
    }
    return implode(' · ', $bits);
}

function saveAutoBlogJob($userId, $slot, $campaignId, $domain, $country, $language, $days, $perDay, $startDate, $postingTimes, $platform, $keywordSource, $endDate = null, $noEnd = 1, $enabled = 1) {
    $db = getDB();
    $now = function_exists('nowString') ? nowString() : date('Y-m-d H:i:s');
    $db->prepare('INSERT INTO auto_blog_jobs (user_id, slot_number, enabled, campaign_id, domain_url, country, language_code, days, posts_per_day, start_date, end_date, no_end, posting_times, target_platform, keyword_source, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT(user_id, slot_number) DO UPDATE SET enabled=excluded.enabled, campaign_id=excluded.campaign_id, domain_url=excluded.domain_url, country=excluded.country, language_code=excluded.language_code, days=excluded.days, posts_per_day=excluded.posts_per_day, start_date=excluded.start_date, end_date=excluded.end_date, no_end=excluded.no_end, posting_times=excluded.posting_times, target_platform=excluded.target_platform, keyword_source=excluded.keyword_source, last_error=NULL')->execute([
        $userId, $slot, intval($enabled), $campaignId, $domain, $country, $language, $days, $perDay, $startDate, $endDate, intval($noEnd), json_encode($postingTimes), $platform, 'planner', $now
    ]);
}
