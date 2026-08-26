<?php
/**
 * Automated daily blogging (no email approval).
 *
 * Blogger: once HTML+image pass, the post is sent to Blogger's own scheduler
 * (draft + publishDate). Blogger goes live at that time — our cron does not
 * push the live post at 10:00.
 *
 * Cron is only used to: generate HTML, check quality, retry failures, and
 * hand the finished article to Blogger/Website.
 */

function articleHasRealImage($html) {
    if (!preg_match_all('#<img[^>]+src=["\']([^"\']+)["\']#i', (string)$html, $m)) {
        return false;
    }
    foreach ($m[1] as $src) {
        $src = trim($src);
        if ($src === '' || strpos($src, 'placeholder') !== false) continue;
        if (stripos($src, 'http://') === 0 || stripos($src, 'https://') === 0 || stripos($src, 'data:image/') === 0) {
            return true;
        }
    }
    return false;
}

function processAutoBlogCampaigns($limit = 3) {
    $db = getDB();
    $today = date('Y-m-d');
    $out = ['processed' => 0, 'published' => 0, 'scheduled' => 0, 'html' => 0, 'errors' => []];

    $stmt = $db->query("SELECT * FROM campaigns WHERE workflow_mode = 'auto' AND status IN ('Auto Running','Roadmap Review') ORDER BY id DESC");
    $campaigns = $stmt ? $stmt->fetchAll() : [];
    foreach ($campaigns as $camp) {
        $userId = intval($camp['user_id']);
        $slot = intval($camp['slot_number'] ?? 1);
        $platform = $camp['target_platform'] ?? 'blogger';
        $itemsStmt = $db->prepare("SELECT * FROM campaign_items WHERE campaign_id = ? AND article_status NOT IN ('Published','Scheduled') ORDER BY day_number, post_number");
        $itemsStmt->execute([$camp['id']]);
        $items = $itemsStmt->fetchAll();
        $did = 0;
        foreach ($items as $item) {
            if ($did >= $limit) break;

            if (!in_array($item['plan_status'], ['Approved', 'Provisional Approved'], true)) {
                $db->prepare("UPDATE campaign_items SET plan_status = 'Approved' WHERE id = ?")->execute([$item['id']]);
            }

            $htmlPath = resolveAutoHtmlPath($item);
            $htmlOk = validateGeneratedArticleFile($htmlPath);
            $content = $htmlOk ? loadCampaignArticleContent($item) : '';
            $words = str_word_count(strip_tags($content));
            $hasImg = articleHasRealImage($content);
            $ready = $htmlOk && $words >= 400 && $hasImg;

            if (!$ready) {
                $retries = intval($item['html_retry_count'] ?? 0);
                if ($retries >= 8) {
                    $out['errors'][] = 'Gave up after 8 tries (need real HTML + image): ' . $item['title'];
                    continue;
                }
                $htmlResult = generateArticleHtmlReliable($item, $userId, $slot, $db);
                $file = $htmlResult['html_file'] ?? '';
                $genContent = (!empty($htmlResult['success']) && is_file($file)) ? (string)@file_get_contents($file) : '';
                $chatOk = !empty($htmlResult['used_chat_api']);
                $imgOk = articleHasRealImage($genContent) || !empty($htmlResult['featured_image']);
                $wordsOk = str_word_count(strip_tags($genContent)) >= 400;
                $ok = !empty($htmlResult['success']) && validateGeneratedArticleFile($file) && $chatOk && $imgOk && $wordsOk;
                $err = $ok ? null : (
                    !$chatOk ? 'Content generation failed — will retry Chat API'
                    : (!$imgOk ? 'Image generation failed — will retry Image API'
                    : ($htmlResult['error'] ?? 'HTML not ready'))
                );
                $db->prepare("UPDATE campaign_items SET html_retry_count = ?, last_error = ?, article_status = ?, html_path = ? WHERE id = ?")->execute([
                    $retries + 1,
                    $err,
                    $ok ? 'HTML Ready' : 'Not Created',
                    $ok ? ($htmlResult['html_path'] ?? '') : '',
                    $item['id']
                ]);
                if (!$ok) {
                    $out['errors'][] = $item['title'] . ': ' . $err;
                    continue;
                }
                $item['html_path'] = $htmlResult['html_path'];
                $out['html']++;
            }

            $schedDate = $item['scheduled_date'] ?: $today;
            $schedTime = $item['scheduled_time'] ?: '10:00';
            if (strlen($schedTime) === 5) $schedTime .= ':00';
            $scheduledStr = $schedDate . ' ' . $schedTime;
            $future = strtotime($scheduledStr) > time() + 90;

            // Blogger: hand the post to Blogger's scheduler as soon as HTML+image pass.
            // Do not wait for cron at the posting minute.
            $useBloggerSchedule = ($platform === 'blogger' && $future);
            $pub = publishItemToSelectedPlatform(
                $userId,
                $item,
                $platform,
                $useBloggerSchedule ? $scheduledStr : ($platform === 'website' && $future ? $scheduledStr : null)
            );
            $did++;
            $out['processed']++;
            if (!empty($pub['success']) && empty($pub['partial'])) {
                $status = ($useBloggerSchedule || (!empty($pub['status']) && $pub['status'] === 'scheduled')) ? 'Scheduled' : 'Published';
                $db->prepare("UPDATE campaign_items SET article_status = ?, last_error = NULL WHERE id = ?")->execute([$status, $item['id']]);
                if ($status === 'Scheduled') $out['scheduled']++;
                else $out['published']++;
            } else {
                $msg = $pub['error'] ?? ($pub['message'] ?? 'publish failed');
                $db->prepare("UPDATE campaign_items SET last_error = ? WHERE id = ?")->execute([$msg, $item['id']]);
                $out['errors'][] = 'Blogger/platform failed for ' . $item['title'] . ': ' . $msg;
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
