<?php
/**
 * Automated daily blogging (no email approval). Isolated from Human Article Writer.
 *
 * Cron only schedules/publishes articles that already have HTML.
 * HTML is created as the last draft step (Approve or Generate HTML), not by cron.
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
        'runs' => $rows,
    ];
}

function inspectAutoItemHtml($item) {
    $htmlPath = resolveAutoHtmlPath($item);
    $htmlOk = function_exists('validateGeneratedArticleFile') ? validateGeneratedArticleFile($htmlPath) : false;
    $content = ($htmlOk && function_exists('loadCampaignArticleContent')) ? loadCampaignArticleContent($item) : ($htmlOk ? (string)@file_get_contents($htmlPath) : '');
    $words = str_word_count(strip_tags($content));
    $hasImg = articleHasRealImage($content);
    return [
        'html_file' => $htmlPath,
        'html_ok' => $htmlOk,
        'words' => $words,
        'has_image' => $hasImg,
        'ready' => $htmlOk && $words >= 400 && $hasImg,
    ];
}

function processAutoBlogCampaigns($htmlLimit = 8, $publishLimit = 5) {
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
        'skipped_ready' => 0,
        'waiting_html' => 0,
        'errors' => [],
        'items' => [],
    ];

    $stmt = $db->query("SELECT * FROM campaigns WHERE workflow_mode = 'auto' AND status IN ('Auto Running','Roadmap Review') ORDER BY id DESC");
    $campaigns = $stmt ? $stmt->fetchAll() : [];
    if (empty($campaigns)) {
        $out['message'] = 'No Auto Blog campaign is running. Start one from the Auto Blog tab.';
        return $out;
    }

    $htmlDid = 0;
    $pubDid = 0;

    foreach ($campaigns as $camp) {
        $userId = intval($camp['user_id']);
        $slot = intval($camp['slot_number'] ?? 1);
        $platform = $camp['target_platform'] ?? 'blogger';
        $itemsStmt = $db->prepare("SELECT * FROM campaign_items WHERE campaign_id = ? AND article_status NOT IN ('Published','Scheduled') ORDER BY day_number, post_number");
        $itemsStmt->execute([$camp['id']]);
        $items = $itemsStmt->fetchAll();

        foreach ($items as $item) {
            if ((time() - $started) > 150) {
                $out['errors'][] = 'Stopped this run to stay under Hostinger time limit. Next cron continues remaining drafts.';
                break 2;
            }

            if (!in_array($item['plan_status'], ['Approved', 'Provisional Approved'], true)) {
                $db->prepare("UPDATE campaign_items SET plan_status = 'Approved' WHERE id = ?")->execute([$item['id']]);
                $item['plan_status'] = 'Approved';
            }

            $check = inspectAutoItemHtml($item);
            $ready = $check['ready'];

            if (!$ready) {
                $out['waiting_html']++;
                $msg = 'HTML not generated yet. Click Generate HTML — cron only schedules ready articles.';
                $db->prepare("UPDATE campaign_items SET last_error = ? WHERE id = ?")->execute([$msg, $item['id']]);
                $out['items'][] = ['id' => $item['id'], 'title' => $item['title'], 'action' => 'waiting_html', 'error' => $msg];
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

            $useBloggerSchedule = ($platform === 'blogger' && $future);
            $pub = publishItemToSelectedPlatform(
                $userId,
                $item,
                $platform,
                $useBloggerSchedule ? $scheduledStr : ($platform === 'website' && $future ? $scheduledStr : null)
            );
            $pubDid++;
            $out['processed']++;
            if (!empty($pub['success']) && empty($pub['partial'])) {
                $status = ($useBloggerSchedule || (!empty($pub['status']) && $pub['status'] === 'scheduled')) ? 'Scheduled' : 'Published';
                $db->prepare("UPDATE campaign_items SET article_status = ?, last_error = NULL WHERE id = ?")->execute([$status, $item['id']]);
                if ($status === 'Scheduled') $out['scheduled']++;
                else $out['published']++;
                $out['items'][] = ['id' => $item['id'], 'title' => $item['title'], 'action' => strtolower($status), 'url' => $pub['url'] ?? ''];
            } else {
                $msg = $pub['error'] ?? ($pub['message'] ?? 'publish failed');
                $db->prepare("UPDATE campaign_items SET last_error = ? WHERE id = ?")->execute([$msg, $item['id']]);
                $out['errors'][] = 'Platform failed for ' . $item['title'] . ': ' . $msg;
                $out['items'][] = ['id' => $item['id'], 'title' => $item['title'], 'action' => 'publish_failed', 'error' => $msg];
            }
        }
    }

    $out['message'] = 'Auto cron: HTML created ' . $out['html'] . ', scheduled ' . $out['scheduled'] . ', published ' . $out['published'] . ', retries/errors ' . count($out['errors']) . '.';
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
