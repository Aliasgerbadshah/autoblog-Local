<?php
/**
 * Automated daily blogging (no email approval). Isolated from Human Article Writer.
 *
 * Pattern after Start (once):
 *  - Job stays Active until Inactive or end date.
 *  - Each day: create the planned number of topic rows (Custom Topics first).
 *  - 1 hour before each posting time: write Master HTML, schedule it on the
 *    chosen platform for that exact time, then email the Master HTML
 *    (date, time, platform, remaining custom topics). Not a confirmation mail.
 *  - If custom topics run out: research a new topic not related to previous
 *    blogs (same idea as Human Article Writer) and keep going.
 */

function articleHasRealImage($html) {
    if (!preg_match_all('#<img[^>]+src=["\']([^"\']+)["\']#i', (string)$html, $m)) {
        return false;
    }
    foreach ($m[1] as $src) {
        $src = trim($src);
        if ($src === '' || stripos($src, 'placeholder') !== false) continue;
        if (stripos($src, 'http://') === 0 || stripos($src, 'https://') === 0 || stripos($src, 'data:image/') === 0) {
            return true;
        }
    }
    return false;
}

function autoBlogPrepareLeadSeconds() {
    return 3600;
}

function autoBlogLockPath() {
    $dir = dirname(__DIR__) . '/data';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . '/auto_blog.lock';
}

function autoBlogCronSecretPath() {
    $dir = dirname(__DIR__) . '/data';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . '/cron_secret.txt';
}

function getAutoBlogCronSecret() {
    $path = autoBlogCronSecretPath();
    if (is_file($path)) {
        $s = trim((string)@file_get_contents($path));
        if ($s !== '') return $s;
    }
    $s = bin2hex(random_bytes(16));
    @file_put_contents($path, $s);
    return $s;
}

function autoBlogTickUrl() {
    $base = defined('APP_BASE_URL') ? rtrim(APP_BASE_URL, '/') : '';
    return $base . '/cron/tick.php?key=' . urlencode(getAutoBlogCronSecret());
}

function autoBlogAllCronUrls() {
    $base = defined('APP_BASE_URL') ? rtrim(APP_BASE_URL, '/') : '';
    $key = urlencode(getAutoBlogCronSecret());
    return [
        'tick' => $base . '/cron/tick.php?key=' . $key,
        'scheduler' => $base . '/cron/scheduler.php?key=' . $key,
        'approval_timer' => $base . '/cron/approval_timer.php?key=' . $key,
    ];
}

function withAutoBlogLock($callback) {
    $path = autoBlogLockPath();
    $fp = @fopen($path, 'c');
    if (!$fp) return $callback();
    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        fclose($fp);
        return [
            'processed' => 0, 'published' => 0, 'scheduled' => 0, 'html' => 0, 'created' => 0,
            'skipped_ready' => 0, 'waiting_html' => 0, 'waiting_window' => 0, 'emailed' => 0,
            'errors' => [], 'items' => [], 'busy' => true,
            'message' => 'Auto Blog worker is already running. Next tick continues.',
        ];
    }
    try {
        return $callback();
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function recordAutoCronRun($source, $result, $userId = null) {
    $db = getDB();
    $now = function_exists('nowString') ? nowString() : date('Y-m-d H:i:s');
    $details = json_encode($result, JSON_UNESCAPED_UNICODE);
    try {
        $stmt = $db->prepare('INSERT INTO auto_cron_log (user_id, source, ran_at, html_created, published, scheduled, processed, failed, details, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $userId,
            $source,
            $now,
            intval($result['html'] ?? 0),
            intval($result['published'] ?? 0),
            intval($result['scheduled'] ?? 0),
            intval($result['processed'] ?? 0),
            count($result['errors'] ?? []),
            $details,
            $now,
        ]);
    } catch (Throwable $e) {
        error_log('[AutoCron] log write failed: ' . $e->getMessage());
    }
    try {
        $db->prepare('UPDATE auto_blog_jobs SET last_run_at = ?, last_error = ? WHERE enabled = 1')->execute([
            $now,
            empty($result['errors']) ? null : implode(' | ', array_slice($result['errors'], 0, 5)),
        ]);
    } catch (Throwable $e) {}
    $logFile = dirname(__DIR__) . '/data/auto_cron.log';
    @file_put_contents($logFile, '[' . $now . '][' . $source . '] ' . $details . "\n", FILE_APPEND);
}

function getLatestAutoCronStatus($userId = null) {
    $db = getDB();
    try {
        if ($userId) {
            $stmt = $db->prepare('SELECT * FROM auto_cron_log WHERE user_id IS NULL OR user_id = ? ORDER BY id DESC LIMIT 8');
            $stmt->execute([$userId]);
        } else {
            $stmt = $db->query('SELECT * FROM auto_cron_log ORDER BY id DESC LIMIT 8');
        }
        $rows = $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $e) {
        $rows = [];
    }
    $latest = $rows[0] ?? null;
    return [
        'last_run_at' => $latest['ran_at'] ?? null,
        'last_source' => $latest['source'] ?? null,
        'last_html' => intval($latest['html_created'] ?? 0),
        'last_published' => intval($latest['published'] ?? 0),
        'last_scheduled' => intval($latest['scheduled'] ?? 0),
        'last_failed' => intval($latest['failed'] ?? 0),
        'cron_is_working' => $latest ? (strtotime($latest['ran_at']) > time() - 900) : false,
        'tick_url' => function_exists('autoBlogTickUrl') ? autoBlogTickUrl() : '',
        'runs' => $rows,
    ];
}

function inspectAutoItemHtml($item) {
    $htmlPath = resolveAutoHtmlPath($item);
    $htmlOk = function_exists('validateGeneratedArticleFile') ? validateGeneratedArticleFile($htmlPath) : false;
    $content = ($htmlOk && function_exists('loadCampaignArticleContent')) ? loadCampaignArticleContent($item) : ($htmlOk ? (string)@file_get_contents($htmlPath) : '');
    $words = str_word_count(strip_tags($content));
    $hasImg = articleHasRealImage($content);
    $isDraft = function_exists('articleLooksLikeDraftHtml') ? articleLooksLikeDraftHtml($content) : (stripos($content, 'This section covers key practical aspects') !== false);
    $statusOk = in_array($item['article_status'] ?? '', ['HTML Ready', 'Final Article Approved'], true);
    return [
        'html_file' => $htmlPath,
        'html_ok' => $htmlOk,
        'words' => $words,
        'has_image' => $hasImg,
        'ready' => $htmlOk && $words >= 350 && !$isDraft && $statusOk,
    ];
}

function autoJobIsInWindow($job, $today) {
    $start = $job['start_date'] ?: $today;
    if ($today < $start) return false;
    $noEnd = intval($job['no_end'] ?? 1);
    if ($noEnd) return true;
    $end = $job['end_date'] ?? '';
    if ($end === '' || $end === null) return true;
    return $today <= $end;
}

function autoItemScheduleTs($item) {
    $date = trim((string)($item['scheduled_date'] ?? ''));
    $time = trim((string)($item['scheduled_time'] ?? '10:00'));
    if ($date === '') $date = date('Y-m-d');
    if (strlen($time) === 5) $time .= ':00';
    $ts = strtotime($date . ' ' . $time);
    return $ts ?: false;
}

function autoItemIsInPrepareWindow($item, $now = null) {
    $now = $now ?? time();
    $ts = autoItemScheduleTs($item);
    if ($ts === false) return true;
    return $now >= ($ts - autoBlogPrepareLeadSeconds());
}

function countCustomTopicsRemaining() {
    if (!function_exists('readCustomTopicsList')) return 0;
    return count(readCustomTopicsList());
}

function researchFreshAutoTopic($userId, $domain, $country, $language) {
    $db = getDB();
    $existing = function_exists('getAllUsedTopics') ? getAllUsedTopics($db, $userId) : [];
    $usedList = [];
    foreach (array_slice($existing, -40) as $row) {
        $t = trim((string)($row['topic'] ?? ''));
        if ($t !== '') $usedList[] = $t;
    }
    $usedStr = $usedList ? implode('; ', $usedList) : '(none yet)';
    $chat = [];
    try {
        $chat = class_exists('SecurityVault') ? (SecurityVault::getApiCredentials($userId, 'chat_api') ?: []) : [];
    } catch (Throwable $e) {
        $chat = [];
    }
    $candidates = [];
    if (!empty($chat['api_key']) && class_exists('AIProviderClient')) {
        $year = date('Y');
        $prompt = "I need 1 UNIQUE article topic for the website $domain in country $country, language $language, year $year. "
            . "It must NOT be related to any of these previous blog titles: $usedStr. "
            . "Pick a different angle (how-to, comparison, mistakes, tools, case study, FAQ, ROI, workflow, beginner vs advanced). "
            . "Do not invent search volume. Return ONLY JSON: {\"title\":\"...\"}";
        try {
            $res = AIProviderClient::chat($chat, $prompt, 18);
            if (!empty($res['success']) && !empty($res['content'])) {
                $raw = trim(str_replace(['```json', '```'], '', $res['content']));
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && !empty($decoded['title'])) $candidates[] = trim($decoded['title']);
                if (is_string($decoded)) $candidates[] = trim($decoded);
                if (!$candidates && preg_match('/"title"\s*:\s*"([^"]+)"/', $raw, $m)) $candidates[] = trim($m[1]);
            }
        } catch (Throwable $e) {
            error_log('[AutoBlog] topic research chat failed: ' . $e->getMessage());
        }
    }
    $seed = parse_url($domain, PHP_URL_HOST) ?: $domain;
    $seed = preg_replace('/^www\./', '', (string)$seed);
    $angles = [
        "Practical $seed workflow checklist " . date('Y'),
        "Common $seed mistakes teams still make",
        "$seed tools comparison for real projects",
        "How beginners start with $seed without wasting budget",
        "Advanced $seed techniques after the basics",
        "$seed ROI measurement that actually holds up",
        "Case-style $seed decisions for small teams",
        "$seed FAQ people search before they buy",
    ];
    $candidates = array_merge($candidates, $angles);
    foreach ($candidates as $title) {
        $title = trim((string)$title);
        if (strlen($title) < 8) continue;
        if (function_exists('isTopicDuplicate') && isTopicDuplicate($title, $title, $existing)) continue;
        return ['success' => true, 'title' => $title, 'source' => 'research'];
    }
    $fallback = $seed . ' unique angle ' . date('Y-m-d H:i');
    return ['success' => true, 'title' => $fallback, 'source' => 'research'];
}

function createNextAutoBlogDraft($job, $camp) {
    $db = getDB();
    $today = date('Y-m-d');
    $userId = intval($job['user_id']);
    $perDay = max(1, intval($job['posts_per_day'] ?: 1));
    $times = json_decode($job['posting_times'] ?? '["10:00"]', true) ?: ['10:00'];
    $platform = $job['target_platform'] ?? ($camp['target_platform'] ?? 'blogger');
    $domain = $job['domain_url'] ?: ($camp['domain_url'] ?? '');
    $country = $job['country'] ?? ($camp['target_country'] ?? 'India');
    $language = $job['language_code'] ?? ($camp['language_code'] ?? 'en');

    $countStmt = $db->prepare('SELECT COUNT(*) FROM campaign_items WHERE campaign_id = ? AND scheduled_date = ?');
    $countStmt->execute([$camp['id'], $today]);
    $todayCount = intval($countStmt->fetchColumn());
    if ($todayCount >= $perDay) {
        return ['created' => false, 'reason' => 'today_full'];
    }

    $topicSource = 'custom';
    $topic = function_exists('takeNextCustomTopic') ? takeNextCustomTopic() : '';
    if ($topic === '') {
        $fresh = researchFreshAutoTopic($userId, $domain, $country, $language);
        if (empty($fresh['success']) || empty($fresh['title'])) {
            return ['created' => false, 'error' => 'Could not research a new topic. Chat API may be down. Job stays Active and will retry.'];
        }
        $topic = $fresh['title'];
        $topicSource = 'research';
    }

    $plan = [
        'title' => $topic,
        'primary_keyword' => $topic,
        'headings' => ['H1' => $topic, 'H2' => ['Overview and practical context', 'What to know before you start', 'How to apply this', 'Frequently Asked Questions'], 'H3' => ['Key points', 'Common mistakes', 'Next steps']],
        'internal_links' => [['url' => $domain, 'anchor_text' => 'our website']],
        'external_links' => [],
        'image_prompts' => [],
        'keywords' => [],
    ];
    $kwRes = requirePlannerKeywordsOnPlan($plan, $userId, $country, $language, true);
    if (empty($kwRes['success'])) {
        if ($topicSource === 'custom' && function_exists('writeCustomTopicsList') && function_exists('readCustomTopicsList')) {
            $rest = readCustomTopicsList();
            array_unshift($rest, $topic);
            writeCustomTopicsList($rest);
        }
        return ['created' => false, 'error' => 'Keyword Planner failed for "' . $topic . '": ' . ($kwRes['error'] ?? 'unknown')];
    }

    $postNum = $todayCount + 1;
    $schedTime = $times[min($postNum - 1, count($times) - 1)] ?? '10:00';
    $dayNumber = 1;
    try {
        $start = new DateTime($job['start_date'] ?: $today);
        $nowD = new DateTime($today);
        $dayNumber = max(1, intval($start->diff($nowD)->days) + 1);
    } catch (Throwable $e) {}

    $now = function_exists('nowString') ? nowString() : date('Y-m-d H:i:s');
    $stmt = $db->prepare('INSERT INTO campaign_items (campaign_id, day_number, post_number, title, primary_keyword, keyword_data, internal_links, external_links, headings, image_prompts, video_url, plan_status, article_status, scheduled_date, scheduled_time, target_platform, created_at, topic_source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    try {
        $stmt->execute([
            $camp['id'], $dayNumber, $postNum, $plan['title'], $plan['primary_keyword'],
            json_encode($plan['keywords']), json_encode($plan['internal_links']), json_encode($plan['external_links']),
            json_encode($plan['headings']), json_encode($plan['image_prompts']), '',
            'Approved', 'Not Created', $today, $schedTime, $platform, $now, $topicSource
        ]);
    } catch (Throwable $e) {
        $stmt = $db->prepare('INSERT INTO campaign_items (campaign_id, day_number, post_number, title, primary_keyword, keyword_data, internal_links, external_links, headings, image_prompts, video_url, plan_status, article_status, scheduled_date, scheduled_time, target_platform, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $camp['id'], $dayNumber, $postNum, $plan['title'], $plan['primary_keyword'],
            json_encode($plan['keywords']), json_encode($plan['internal_links']), json_encode($plan['external_links']),
            json_encode($plan['headings']), json_encode($plan['image_prompts']), '',
            'Approved', 'Not Created', $today, $schedTime, $platform, $now
        ]);
    }
    $itemId = $db->lastInsertId();
    if (function_exists('addTopicToJsonFile')) {
        addTopicToJsonFile($plan['title'], $plan['primary_keyword'], $domain, 'pending', $camp['id']);
    }
    if (function_exists('addTopicToCsv')) {
        addTopicToCsv($plan['title'], $plan['primary_keyword'], $domain, 'pending', $camp['id'], $now);
    }
    try {
        $db->prepare('INSERT OR IGNORE INTO created_blog_topics (user_id, title, primary_keyword, domain_url, campaign_id, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$userId, $plan['title'], $plan['primary_keyword'], $domain, $camp['id'], $now]);
    } catch (Throwable $e) {}
    return ['created' => true, 'item_id' => $itemId, 'title' => $plan['title'], 'topic_source' => $topicSource, 'scheduled_time' => $schedTime];
}

function ensureTodayAutoDrafts($job, $camp, $maxCreate = 3) {
    $made = [];
    $errors = [];
    for ($i = 0; $i < $maxCreate; $i++) {
        $row = createNextAutoBlogDraft($job, $camp);
        if (!empty($row['created'])) {
            $made[] = $row;
            continue;
        }
        if (($row['reason'] ?? '') === 'today_full') break;
        if (!empty($row['error'])) $errors[] = $row['error'];
        break;
    }
    return ['created' => $made, 'errors' => $errors];
}

function generateHtmlForCampaignItem($item, $userId, $slot, $db, $wantMaster = true) {
    if (!function_exists('generateArticleHtmlReliable')) {
        return ['success' => false, 'error' => 'HTML engine missing'];
    }
    try {
        $htmlResult = generateArticleHtmlReliable($item, $userId, $slot, $db, '', $wantMaster);
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
    if (!empty($htmlResult['html_path'])) {
        $chatOk = !empty($htmlResult['used_chat_api']);
        $st = $chatOk ? 'HTML Ready' : 'Draft HTML';
        $err = $chatOk ? null : ($htmlResult['error'] ?? 'Chat API did not write master HTML.');
        $db->prepare("UPDATE campaign_items SET article_status = ?, html_path = ?, last_error = ? WHERE id = ?")->execute([$st, $htmlResult['html_path'], $err, $item['id']]);
        $htmlResult['success'] = $chatOk;
        $htmlResult['article_status'] = $st;
        return $htmlResult;
    }
    $err = $htmlResult['error'] ?? 'HTML generation failed';
    $db->prepare("UPDATE campaign_items SET last_error = ?, html_retry_count = COALESCE(html_retry_count,0)+1 WHERE id = ?")->execute([$err, $item['id']]);
    return $htmlResult;
}

function buildAutoScheduledBlogEmail($item, $pub, $status, $platform, $scheduledStr, $customLeft) {
    $title = escapeHtml($item['title'] ?? 'Untitled');
    $kw = escapeHtml($item['primary_keyword'] ?? '');
    $plat = strtoupper((string)$platform);
    $url = escapeHtml($pub['url'] ?? '');
    $source = (($item['topic_source'] ?? '') === 'research')
        ? 'Custom topics were exhausted. This topic was researched and is not related to previous blogs.'
        : 'Topic taken from your Custom Topics list.';
    $article = function_exists('loadCampaignArticleContent') ? loadCampaignArticleContent($item) : '';
    if ($article === '') $article = '<p>Master HTML file could not be inlined. Open the View link in Auto Blog Queue.</p>';
    $preview = (defined('APP_BASE_URL') ? rtrim(APP_BASE_URL, '/') : '') . ($item['html_path'] ?? '');
    $leftNote = intval($customLeft) . ' custom topic(s) still available after this post.';
    $when = escapeHtml($scheduledStr);
    $statusLabel = ($status === 'Published') ? 'Published now' : 'Scheduled';

    return '<!DOCTYPE html><html><head><meta charset="utf-8"></head>'
        . '<body style="font-family:Arial,Helvetica,sans-serif;background:#f8fafc;color:#0f172a;margin:0;padding:0;">'
        . '<div style="max-width:760px;margin:0 auto;padding:24px;">'
        . '<h1 style="font-size:1.4rem;margin:0 0 8px 0;">Master HTML is ready and ' . escapeHtml($statusLabel) . '</h1>'
        . '<p style="color:#475569;margin:0 0 16px 0;">This is the blog that will go live — not a confirmation notice.</p>'
        . '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:16px 18px;margin-bottom:18px;font-size:0.92rem;">'
        . '<p style="margin:0 0 6px 0;"><strong>Title:</strong> ' . $title . '</p>'
        . '<p style="margin:0 0 6px 0;"><strong>Date and time:</strong> ' . $when . ' (Asia/Kolkata)</p>'
        . '<p style="margin:0 0 6px 0;"><strong>Platform:</strong> ' . $plat . '</p>'
        . '<p style="margin:0 0 6px 0;"><strong>Primary keyword:</strong> ' . $kw . '</p>'
        . ($url ? '<p style="margin:0 0 6px 0;"><strong>URL:</strong> <a href="' . $url . '">' . $url . '</a></p>' : '')
        . '<p style="margin:0 0 6px 0;"><strong>Custom topics left:</strong> ' . escapeHtml($leftNote) . '</p>'
        . '<p style="margin:0;"><strong>Topic source:</strong> ' . escapeHtml($source) . '</p>'
        . '</div>'
        . ($preview ? '<p style="margin:0 0 16px 0;"><a href="' . escapeHtml($preview) . '" style="color:#1b57f6;font-weight:700;">Open Master HTML preview</a></p>' : '')
        . '<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:20px;">' . $article . '</div>'
        . '</div></body></html>';
}

function sendAutoScheduledBlogMail($userId, $item, $pub, $status, $platform, $scheduledStr) {
    if (!function_exists('sendApprovalEmail')) {
        $mailer = __DIR__ . '/mailer.php';
        if (is_file($mailer)) require_once $mailer;
    }
    if (!function_exists('sendApprovalEmail')) return false;
    $left = countCustomTopicsRemaining();
    $whenLabel = $scheduledStr;
    $subject = 'Scheduled ' . strtoupper((string)$platform) . ' · ' . $whenLabel . ' · ' . ($item['title'] ?? 'Blog');
    $html = buildAutoScheduledBlogEmail($item, $pub, $status, $platform, $scheduledStr, $left);
    return sendApprovalEmail($userId, $subject, $html);
}

function markAutoScheduleEmailSent($db, $itemId) {
    try {
        $db->prepare('UPDATE campaign_items SET schedule_email_sent = 1 WHERE id = ?')->execute([$itemId]);
    } catch (Throwable $e) {}
}

function processAutoBlogCampaigns($htmlLimit = 1, $publishLimit = 5) {
    return withAutoBlogLock(function () use ($htmlLimit, $publishLimit) {
        return processAutoBlogCampaignsUnlocked($htmlLimit, $publishLimit);
    });
}

function processAutoBlogCampaignsUnlocked($htmlLimit = 1, $publishLimit = 5) {
    @set_time_limit(180);
    @ini_set('max_execution_time', '180');
    $db = getDB();
    $today = date('Y-m-d');
    $started = time();
    $out = [
        'processed' => 0,
        'published' => 0,
        'scheduled' => 0,
        'html' => 0,
        'created' => 0,
        'skipped_ready' => 0,
        'waiting_html' => 0,
        'waiting_window' => 0,
        'emailed' => 0,
        'errors' => [],
        'items' => [],
        'custom_topics_left' => countCustomTopicsRemaining(),
    ];

    $jobs = [];
    try {
        $stmt = $db->query("SELECT * FROM auto_blog_jobs WHERE enabled = 1 ORDER BY id DESC");
        $jobs = $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $e) {
        $jobs = [];
    }

    if (empty($jobs)) {
        $stmt = $db->query("SELECT * FROM campaigns WHERE workflow_mode = 'auto' AND status IN ('Auto Running','Roadmap Review') ORDER BY id DESC");
        $campaigns = $stmt ? $stmt->fetchAll() : [];
        if (empty($campaigns)) {
            $out['message'] = 'No Auto Blog job is Active. Start one from the Auto Blog tab.';
            return $out;
        }
    } else {
        $campaigns = [];
        foreach ($jobs as $job) {
            if (!autoJobIsInWindow($job, $today)) {
                $end = $job['end_date'] ?? '';
                if (intval($job['no_end'] ?? 1) === 0 && $end && $today > $end) {
                    try { $db->prepare('UPDATE auto_blog_jobs SET enabled = 0 WHERE id = ?')->execute([$job['id']]); } catch (Throwable $e) {}
                    try { $db->prepare("UPDATE campaigns SET status = 'Paused' WHERE id = ?")->execute([$job['campaign_id'] ?? 0]); } catch (Throwable $e) {}
                    $out['errors'][] = 'Reached end date. Job set Inactive.';
                }
                continue;
            }
            $camp = null;
            if (!empty($job['campaign_id'])) {
                $cs = $db->prepare('SELECT * FROM campaigns WHERE id = ?');
                $cs->execute([$job['campaign_id']]);
                $camp = $cs->fetch();
            }
            if (!$camp) {
                $cs = $db->prepare("SELECT * FROM campaigns WHERE user_id = ? AND workflow_mode = 'auto' AND status IN ('Auto Running','Roadmap Review') ORDER BY id DESC LIMIT 1");
                $cs->execute([$job['user_id']]);
                $camp = $cs->fetch();
            }
            if (!$camp) continue;
            $camp['_job'] = $job;
            $campaigns[] = $camp;
        }
    }

    if (empty($campaigns)) {
        $out['message'] = 'Auto Blog is Active but today is outside the start/end window.';
        return $out;
    }

    $htmlDid = 0;
    $pubDid = 0;

    foreach ($campaigns as $camp) {
        $userId = intval($camp['user_id']);
        $slot = intval($camp['slot_number'] ?? 1);
        $platform = $camp['target_platform'] ?? 'blogger';
        $job = $camp['_job'] ?? null;

        if ($job && (time() - $started) < 80) {
            $batch = ensureTodayAutoDrafts($job, $camp, max(1, intval($job['posts_per_day'] ?: 1)));
            foreach ($batch['created'] as $made) {
                $out['created']++;
                $out['items'][] = ['id' => $made['item_id'], 'title' => $made['title'], 'action' => 'topic_queued', 'scheduled_time' => $made['scheduled_time'] ?? ''];
            }
            foreach ($batch['errors'] as $err) {
                $out['errors'][] = $err;
                try { $db->prepare('UPDATE auto_blog_jobs SET last_error = ? WHERE id = ?')->execute([$err, $job['id']]); } catch (Throwable $e) {}
            }
        }

        $itemsStmt = $db->prepare("SELECT * FROM campaign_items WHERE campaign_id = ? AND article_status NOT IN ('Published','Scheduled') ORDER BY scheduled_date, scheduled_time, post_number");
        $itemsStmt->execute([$camp['id']]);
        $items = $itemsStmt->fetchAll();

        foreach ($items as $item) {
            if ((time() - $started) > 150) {
                $out['errors'][] = 'Stopped this run to stay under Hostinger time limit. Next worker tick continues.';
                break 2;
            }

            if (!in_array($item['plan_status'], ['Approved', 'Provisional Approved'], true)) {
                $db->prepare("UPDATE campaign_items SET plan_status = 'Approved' WHERE id = ?")->execute([$item['id']]);
                $item['plan_status'] = 'Approved';
            }

            if (!autoItemIsInPrepareWindow($item)) {
                $out['waiting_window']++;
                $ts = autoItemScheduleTs($item);
                $when = $ts ? date('Y-m-d H:i', $ts - autoBlogPrepareLeadSeconds()) : '';
                $db->prepare("UPDATE campaign_items SET last_error = ? WHERE id = ?")->execute([
                    'Waiting. Master HTML + schedule start 1 hour before ' . ($item['scheduled_time'] ?? '') . ($when ? " (worker begins $when)" : ''),
                    $item['id']
                ]);
                $out['items'][] = ['id' => $item['id'], 'title' => $item['title'], 'action' => 'waiting_1h_window'];
                continue;
            }

            $check = inspectAutoItemHtml($item);
            $ready = $check['ready'];

            if (!$ready && $htmlDid < $htmlLimit) {
                $htmlResult = generateHtmlForCampaignItem($item, $userId, $slot, $db, true);
                $htmlDid++;
                if (!empty($htmlResult['success'])) {
                    $out['html']++;
                    $item['html_path'] = $htmlResult['html_path'];
                    $item['article_status'] = 'HTML Ready';
                    $check = inspectAutoItemHtml($item);
                    $ready = $check['ready'];
                    $out['items'][] = ['id' => $item['id'], 'title' => $item['title'], 'action' => 'master_html'];
                } else {
                    $msg = $htmlResult['error'] ?? 'HTML generation failed';
                    $out['errors'][] = $item['title'] . ': ' . $msg;
                    $out['waiting_html']++;
                    $out['items'][] = ['id' => $item['id'], 'title' => $item['title'], 'action' => 'html_failed', 'error' => $msg];
                    continue;
                }
            } elseif (!$ready) {
                $out['waiting_html']++;
                $msg = 'Inside the 1-hour window. Master HTML will be written on the next worker tick.';
                $db->prepare("UPDATE campaign_items SET last_error = ? WHERE id = ?")->execute([$msg, $item['id']]);
                continue;
            } else {
                $out['skipped_ready']++;
            }

            if (!$ready) continue;
            if ($pubDid >= $publishLimit) continue;

            $schedDate = $item['scheduled_date'] ?: $today;
            $schedTime = $item['scheduled_time'] ?: '10:00';
            if (strlen($schedTime) === 5) $schedTime .= ':00';
            $scheduledStr = $schedDate . ' ' . $schedTime;
            $future = strtotime($scheduledStr) > time() + 90;
            $itemPlatform = $item['target_platform'] ?: $platform;

            $useBloggerSchedule = ($itemPlatform === 'blogger' && $future);
            $pub = publishItemToSelectedPlatform(
                $userId,
                $item,
                $itemPlatform,
                $useBloggerSchedule ? $scheduledStr : ($itemPlatform === 'website' && $future ? $scheduledStr : null)
            );
            $pubDid++;
            $out['processed']++;
            if (!empty($pub['success']) && empty($pub['partial'])) {
                $status = ($useBloggerSchedule || (!empty($pub['status']) && $pub['status'] === 'scheduled')) ? 'Scheduled' : 'Published';
                $db->prepare("UPDATE campaign_items SET article_status = ?, last_error = NULL WHERE id = ?")->execute([$status, $item['id']]);
                if ($status === 'Scheduled') $out['scheduled']++;
                else $out['published']++;
                $out['items'][] = ['id' => $item['id'], 'title' => $item['title'], 'action' => strtolower($status), 'url' => $pub['url'] ?? ''];
                $already = intval($item['schedule_email_sent'] ?? 0);
                if (!$already) {
                    $mailed = sendAutoScheduledBlogMail($userId, $item, $pub, $status, $itemPlatform, $scheduledStr);
                    if ($mailed) {
                        markAutoScheduleEmailSent($db, $item['id']);
                        $out['emailed']++;
                    } else {
                        $out['errors'][] = 'Scheduled, but blog email did not send for ' . $item['title'];
                    }
                }
            } else {
                $msg = $pub['error'] ?? ($pub['message'] ?? 'publish failed');
                $db->prepare("UPDATE campaign_items SET last_error = ? WHERE id = ?")->execute([$msg, $item['id']]);
                $out['errors'][] = 'Platform failed for ' . $item['title'] . ': ' . $msg;
                $out['items'][] = ['id' => $item['id'], 'title' => $item['title'], 'action' => 'publish_failed', 'error' => $msg];
            }
        }
    }

    try {
        if (function_exists('getBlogPublisher')) {
            list($wpub, $wpubErr) = getBlogPublisher();
            if (!$wpubErr && $wpub && method_exists($wpub, 'publishScheduled')) {
                $wpRes = $wpub->publishScheduled();
                if (!empty($wpRes['published'])) $out['published'] += intval($wpRes['published']);
            }
        }
    } catch (Throwable $e) {}

    $out['custom_topics_left'] = countCustomTopicsRemaining();
    $out['message'] = 'Auto Blog: topics queued ' . $out['created']
        . ', Master HTML ' . $out['html']
        . ', scheduled ' . $out['scheduled']
        . ', published ' . $out['published']
        . ', emailed ' . $out['emailed']
        . ', waiting 1-hour window ' . $out['waiting_window']
        . ', waiting HTML ' . $out['waiting_html']
        . ', custom topics left ' . $out['custom_topics_left']
        . ', errors ' . count($out['errors']) . '.';
    return $out;
}

function resolveAutoHtmlPath($item) {
    if (function_exists('resolveCampaignHtmlFile')) {
        $p = resolveCampaignHtmlFile($item['html_path'] ?? '');
        if ($p) return $p;
    }
    $base = basename($item['html_path'] ?? '');
    if ($base === '') return '';
    $cands = [
        (defined('OUTPUT_DIR') ? OUTPUT_DIR : dirname(__DIR__) . '/published_posts') . '/demo/' . $base,
        dirname(__DIR__) . '/published_posts/demo/' . $base,
    ];
    foreach ($cands as $p) if (is_file($p)) return $p;
    return '';
}
