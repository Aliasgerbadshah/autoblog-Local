<?php
/**
 * Automated daily blogging (no email approval).
 * Same options as manual: destination, keyword source, times, days.
 * Cron validates HTML + image/content then publishes; retries on failure.
 */

function processAutoBlogCampaigns($limit = 3) {
    $db = getDB();
    $now = nowString();
    $today = date('Y-m-d');
    $out = ['processed' => 0, 'published' => 0, 'html' => 0, 'errors' => []];

    $stmt = $db->query("SELECT * FROM campaigns WHERE workflow_mode = 'auto' AND status IN ('Auto Running','Roadmap Review') ORDER BY id DESC");
    $campaigns = $stmt ? $stmt->fetchAll() : [];
    foreach ($campaigns as $camp) {
        $userId = intval($camp['user_id']);
        $slot = intval($camp['slot_number'] ?? 1);
        $platform = $camp['target_platform'] ?? 'blogger';
        $itemsStmt = $db->prepare("SELECT * FROM campaign_items WHERE campaign_id = ? AND article_status != 'Published' ORDER BY day_number, post_number");
        $itemsStmt->execute([$camp['id']]);
        $items = $itemsStmt->fetchAll();
        $did = 0;
        foreach ($items as $item) {
            if ($did >= $limit) break;
            $schedDate = $item['scheduled_date'] ?: $today;
            if ($schedDate > $today) continue;

            $planOk = in_array($item['plan_status'], ['Approved', 'Provisional Approved'], true);
            if (!$planOk) {
                $db->prepare("UPDATE campaign_items SET plan_status = 'Approved' WHERE id = ?")->execute([$item['id']]);
            }

            $htmlOk = validateGeneratedArticleFile(resolveAutoHtmlPath($item));
            if (!$htmlOk) {
                $retries = intval($item['html_retry_count'] ?? 0);
                if ($retries >= 8) {
                    $out['errors'][] = 'Gave up HTML after 8 tries: ' . $item['title'];
                    continue;
                }
                $htmlResult = generateArticleHtmlReliable($item, $userId, $slot, $db);
                $db->prepare("UPDATE campaign_items SET html_retry_count = ?, last_error = ? WHERE id = ?")->execute([
                    $retries + 1,
                    empty($htmlResult['success']) ? ($htmlResult['error'] ?? 'HTML failed') : null,
                    $item['id']
                ]);
                if (empty($htmlResult['success']) || !validateGeneratedArticleFile($htmlResult['html_file'] ?? '')) {
                    $out['errors'][] = 'HTML retry for ' . $item['title'] . ': ' . ($htmlResult['error'] ?? 'invalid');
                    continue;
                }
                $db->prepare("UPDATE campaign_items SET article_status = 'HTML Ready', html_path = ? WHERE id = ?")->execute([$htmlResult['html_path'], $item['id']]);
                $item['html_path'] = $htmlResult['html_path'];
                $out['html']++;
            }

            $content = loadCampaignArticleContent($item);
            $words = str_word_count(strip_tags($content));
            $hasImg = (bool)preg_match('#<img[^>]+src=#i', $content);
            if ($words < 250) {
                $out['errors'][] = 'Content too thin, will retry: ' . $item['title'];
                $db->prepare("UPDATE campaign_items SET article_status = 'Not Created', html_path = '' WHERE id = ?")->execute([$item['id']]);
                continue;
            }

            $schedTime = $item['scheduled_time'] ?: '10:00';
            $dueAt = strtotime($schedDate . ' ' . $schedTime);
            if ($dueAt && $dueAt > time() + 60) {
                continue; // not due yet
            }

            $pub = publishItemToSelectedPlatform($userId, $item, $platform);
            $did++;
            $out['processed']++;
            if (!empty($pub['success'])) {
                $db->prepare("UPDATE campaign_items SET article_status = 'Published', last_error = NULL WHERE id = ?")->execute([$item['id']]);
                $out['published']++;
            } else {
                $db->prepare("UPDATE campaign_items SET last_error = ? WHERE id = ?")->execute([$pub['error'] ?? 'publish failed', $item['id']]);
                $out['errors'][] = 'Publish failed ' . $item['title'] . ': ' . ($pub['error'] ?? '');
            }
        }
    }
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
