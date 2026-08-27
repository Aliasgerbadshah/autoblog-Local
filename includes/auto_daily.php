<?php
/**
 * Automated daily blogging (no email approval). Isolated from Human Article Writer.
 *
 * Each cron tick (and Run Auto Cron):
 *  1) If job is Active and inside start/end (or no end): create today's missing drafts
 *     from Custom Topics + Keyword Planner only.
 *  2) Write HTML for ONE missing draft (Hostinger time limit).
 *  3) Schedule/publish drafts that already have HTML.
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
        'ready' => $htmlOk && $words >= 200,
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

    $topic = function_exists('takeNextCustomTopic') ? takeNextCustomTopic() : '';
    if ($topic === '') {
        return ['created' => false, 'error' => 'No custom topics left. Add topics in Custom Topics. Auto Blog does not invent AI topics.'];
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
        if (function_exists('writeCustomTopicsList') && function_exists('readCustomTopicsList')) {
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
    $stmt = $db->prepare('INSERT INTO campaign_items (campaign_id, day_number, post_number, title, primary_keyword, keyword_data, internal_links, external_links, headings, image_prompts, video_url, plan_status, article_status, scheduled_date, scheduled_time, target_platform, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $camp['id'], $dayNumber, $postNum, $plan['title'], $plan['primary_keyword'],
        json_encode($plan['keywords']), json_encode($plan['internal_links']), json_encode($plan['external_links']),
        json_encode($plan['headings']), json_encode($plan['image_prompts']), '',
        'Approved', 'Not Created', $today, $schedTime, $platform, $now
    ]);
    $itemId = $db->lastInsertId();
    return ['created' => true, 'item_id' => $itemId, 'title' => $plan['title']];
}

function generateHtmlForCampaignItem($item, $userId, $slot, $db) {
    if (!function_exists('generateArticleHtmlReliable')) {
        return ['success' => false, 'error' => 'HTML engine missing'];
    }
    try {
        $htmlResult = generateArticleHtmlReliable($item, $userId, $slot, $db);
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

function processAutoBlogCampaigns($htmlLimit = 1, $publishLimit = 5) {
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
        'errors' => [],
        'items' => [],
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
            $made = createNextAutoBlogDraft($job, $camp);
            if (!empty($made['created'])) {
                $out['created']++;
                $out['items'][] = ['id' => $made['item_id'], 'title' => $made['title'], 'action' => 'draft_created'];
            } elseif (!empty($made['error'])) {
                $out['errors'][] = $made['error'];
                try { $db->prepare('UPDATE auto_blog_jobs SET last_error = ? WHERE id = ?')->execute([$made['error'], $job['id']]); } catch (Throwable $e) {}
            }
        }

        $itemsStmt = $db->prepare("SELECT * FROM campaign_items WHERE campaign_id = ? AND article_status NOT IN ('Published','Scheduled') ORDER BY day_number, post_number");
        $itemsStmt->execute([$camp['id']]);
        $items = $itemsStmt->fetchAll();

        foreach ($items as $item) {
            if ((time() - $started) > 150) {
                $out['errors'][] = 'Stopped this run to stay under Hostinger time limit. Next cron continues.';
                break 2;
            }

            if (!in_array($item['plan_status'], ['Approved', 'Provisional Approved'], true)) {
                $db->prepare("UPDATE campaign_items SET plan_status = 'Approved' WHERE id = ?")->execute([$item['id']]);
                $item['plan_status'] = 'Approved';
            }

            $check = inspectAutoItemHtml($item);
            $ready = $check['ready'];

            if (!$ready && $htmlDid < $htmlLimit) {
                $htmlResult = generateHtmlForCampaignItem($item, $userId, $slot, $db);
                $htmlDid++;
                if (!empty($htmlResult['success'])) {
                    $out['html']++;
                    $item['html_path'] = $htmlResult['html_path'];
                    $item['article_status'] = 'HTML Ready';
                    $check = inspectAutoItemHtml($item);
                    $ready = $check['ready'];
                    $out['items'][] = ['id' => $item['id'], 'title' => $item['title'], 'action' => 'html'];
                } else {
                    $msg = $htmlResult['error'] ?? 'HTML generation failed';
                    $out['errors'][] = $item['title'] . ': ' . $msg;
                    $out['waiting_html']++;
                    $out['items'][] = ['id' => $item['id'], 'title' => $item['title'], 'action' => 'html_failed', 'error' => $msg];
                    continue;
                }
            } elseif (!$ready) {
                $out['waiting_html']++;
                $msg = 'HTML not generated yet. Cron will write the next missing HTML on the following run.';
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

    $out['message'] = 'Auto cron: drafts ' . $out['created'] . ', HTML ' . $out['html'] . ', scheduled ' . $out['scheduled'] . ', published ' . $out['published'] . ', waiting HTML ' . $out['waiting_html'] . ', errors ' . count($out['errors']) . '.';
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
