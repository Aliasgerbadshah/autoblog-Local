<?php
/**
 * AutoBlog SaaS - Approval Timer Cron Job
 * 
 * */5 * * * * php /home/USERNAME/public_html/cron/approval_timer.php
 *
 * Processes:
 * 1. Sends reminder emails for items still pending
 * 2. Auto-finalizes provisional approvals after APPROVAL_WINDOW_MINUTES
 * 3. Generates HTML for ALL approved items that don't have HTML yet
 * 4. Auto-schedules items whose HTML has been approved (Final Article Approved)
 */

// SAPI guard removed — Hostinger runs PHP as CGI/fPM, not CLI.
// Allow execution from both cron and web triggers.
if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cgi-fcgi' && php_sapi_name() !== 'fpm-fcgi') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die('Method not allowed. Use POST to trigger approval timer.');
    }
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/autoblog_engine.php';
require_once __DIR__ . '/../includes/anti_ai_sanitizer.php';
require_once __DIR__ . '/../includes/ai_provider.php';
require_once __DIR__ . '/../includes/mailer.php';

$db = getDB();
$now = new DateTime();
$nowStr = $now->format('Y-m-d H:i:s');
$cutoff = (clone $now)->modify('-' . APPROVAL_WINDOW_MINUTES . ' minutes')->format('Y-m-d H:i:s');

echo "[Approval Timer] Running at $nowStr\n";

// ============ 1. PROCESS PROVISIONAL APPROVALS THAT HAVE TIMED OUT ============
$stmt = $db->prepare("SELECT * FROM approval_tokens WHERE approval_type = 'roadmap' AND decision = 'Provisional' AND first_clicked_at <= ? AND first_clicked_at IS NOT NULL");
$stmt->execute([$cutoff]);
$provisional = $stmt->fetchAll();

foreach ($provisional as $tok) {
    $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
    $stmt->execute([$tok['campaign_item_id']]);
    $item = $stmt->fetch();
    if (!$item) continue;

    if ($tok['first_decision'] === 'approve') {
        $stmt = $db->prepare("UPDATE approval_tokens SET decision = 'Approved', click_count = 2 WHERE id = ?");
        $stmt->execute([$tok['id']]);
        $stmt = $db->prepare("UPDATE campaign_items SET plan_status = 'Approved' WHERE id = ?");
        $stmt->execute([$item['id']]);

        $activeSlot = 1;
        $stmt = $db->prepare('SELECT active_slot_id FROM users WHERE id = ?');
        $stmt->execute([$tok['user_id']]);
        $uRow = $stmt->fetch();
        if ($uRow) $activeSlot = $uRow['active_slot_id'] ?? 1;

        $htmlResult = generateArticleHtmlFromCampaignItem($item, $tok['user_id'], $activeSlot, $db);
        if (!empty($htmlResult['success'])) {
            $stmt = $db->prepare("UPDATE campaign_items SET article_status = 'HTML Ready', html_path = ? WHERE id = ?");
            $stmt->execute([$htmlResult['html_path'], $item['id']]);
            $htmlToken = generateToken();
            $stmt = $db->prepare('INSERT INTO approval_tokens (user_id, campaign_item_id, approval_type, token, created_at) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$tok['user_id'], $item['id'], 'html', $htmlToken, $nowStr]);
            $previewEmail = buildHtmlPreviewEmailHtml($item, $htmlResult['html_path'], $htmlToken, $htmlResult['used_chat_api']);
            sendApprovalEmail($tok['user_id'], 'Blog HTML Preview - ' . escapeHtml($item['title']), $previewEmail);
            echo "[Timer] Auto-approved & generated HTML for: {$item['title']}\n";
        }
    } else {
        $newTitle = $item['title'] . ' — New Research Angle';
        $newKeyword = $item['primary_keyword'] . ' alternatives';
        $oldKws = json_decode($item['keyword_data'] ?? '[]', true);
        $newKws = array_map(fn($x) => ['keyword' => $newKeyword, 'volume' => $x['volume'] ?? 'Estimate', 'difficulty' => $x['difficulty'] ?? 'Research required', 'intent' => $x['intent'] ?? 'Informational'], $oldKws);
        $newHeadings = ['H1' => $newTitle, 'H2' => ['A different practical angle', 'What the evidence shows', 'How to apply the advice', 'FAQ'], 'H3' => ['Examples and comparisons', 'Common mistakes', 'Checklist']];
        $newPrompts = ["Editorial image illustrating $newKeyword, natural light, no text or logos.", "Practical scene related to $newKeyword."];

        $stmt = $db->prepare("UPDATE approval_tokens SET decision = 'Rejected', click_count = 2 WHERE id = ?");
        $stmt->execute([$tok['id']]);
        $stmt = $db->prepare("UPDATE campaign_items SET title = ?, primary_keyword = ?, keyword_data = ?, headings = ?, image_prompts = ?, plan_status = 'Replacement Pending', article_status = 'Not Created' WHERE id = ?");
        $stmt->execute([$newTitle, $newKeyword, json_encode($newKws), json_encode($newHeadings), json_encode($newPrompts), $item['id']]);

        $newToken = generateToken();
        $stmt = $db->prepare('INSERT INTO approval_tokens (user_id, campaign_item_id, approval_type, token, created_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$tok['user_id'], $item['id'], 'roadmap', $newToken, $nowStr]);

        $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
        $stmt->execute([$item['id']]);
        $updatedItem = $stmt->fetch();
        $replacementEmail = buildReplacementEmailHtml($updatedItem, $newToken, $db);
        sendApprovalEmail($tok['user_id'], 'Replacement Blog Plan - ' . escapeHtml($newTitle), $replacementEmail);
        echo "[Timer] Auto-rejected & created replacement for: {$item['title']}\n";
    }
}

// ============ 2. SEND REMINDER EMAILS FOR PENDING ITEMS ============
$stmt = $db->prepare("SELECT id, user_id FROM campaigns WHERE status = 'Roadmap Review' ORDER BY id DESC LIMIT 1");
$stmt->execute();
$campaign = $stmt->fetch();

if ($campaign) {
    $stmt = $db->prepare("SELECT * FROM campaign_items WHERE campaign_id = ? AND plan_status IN ('Pending', 'Provisional Approved', 'Provisional Disapproved', 'Replacement Pending')");
    $stmt->execute([$campaign['id']]);
    $pending = $stmt->fetchAll();

    if (!empty($pending)) {
        $stmt = $db->prepare("SELECT created_at FROM demo_emails WHERE user_id = ? AND subject LIKE '%Reminder%' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$campaign['user_id']]);
        $last = $stmt->fetch();
        $due = true;
        if ($last) {
            $lastTime = strtotime($last['created_at']);
            if ($lastTime !== false && (time() - $lastTime) < REMINDER_INTERVAL_MINUTES * 60) $due = false;
        }
        if ($due) {
            $campaignRow = ['domain_url' => $campaign['domain_url'] ?? '', 'days' => $campaign['days'] ?? 7, 'posts_per_day' => $campaign['posts_per_day'] ?? 1];
            $reminderHtml = buildRichApprovalEmailHtml($pending, $campaignRow, $db);
            sendApprovalEmail($campaign['user_id'], 'Reminder: Pending Blog Approvals Need Your Decision', $reminderHtml);
            $stmt = $db->prepare('INSERT INTO demo_emails (user_id, subject, html_content, created_at) VALUES (?, ?, ?, ?)');
            $stmt->execute([$campaign['user_id'], 'Reminder: Pending Blog Approvals', $reminderHtml, $nowStr]);
            echo "[Timer] Sent reminder email for campaign {$campaign['id']}\n";
        }
    }
}

// ============ 3. GENERATE HTML FOR ALL APPROVED ITEMS THAT DON'T HAVE HTML YET ============
$stmt = $db->prepare("SELECT ci.* FROM campaign_items ci WHERE ci.plan_status = 'Approved' AND (ci.article_status IS NULL OR ci.article_status = '' OR ci.article_status = 'Not Created' OR ci.html_path IS NULL OR ci.html_path = '') ORDER BY ci.id ASC");
$stmt->execute();
$needHtml = $stmt->fetchAll();

foreach ($needHtml as $item) {
    $stmt = $db->prepare('SELECT user_id FROM campaigns WHERE id = ?');
    $stmt->execute([$item['campaign_id']]);
    $campRow = $stmt->fetch();
    if (!$campRow) continue;
    $userId = $campRow['user_id'];

    $activeSlot = 1;
    $stmt = $db->prepare('SELECT active_slot_id FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $uRow = $stmt->fetch();
    if ($uRow) $activeSlot = $uRow['active_slot_id'] ?? 1;

    $htmlResult = generateArticleHtmlFromCampaignItem($item, $userId, $activeSlot, $db);
    if (!empty($htmlResult['success'])) {
        $stmt = $db->prepare("UPDATE campaign_items SET article_status = 'HTML Ready', html_path = ? WHERE id = ?");
        $stmt->execute([$htmlResult['html_path'], $item['id']]);
        $htmlToken = generateToken();
        $stmt = $db->prepare('INSERT INTO approval_tokens (user_id, campaign_item_id, approval_type, token, created_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $item['id'], 'html', $htmlToken, $nowStr]);
        $previewEmail = buildHtmlPreviewEmailHtml($item, $htmlResult['html_path'], $htmlToken, $htmlResult['used_chat_api']);
        sendApprovalEmail($userId, 'Blog HTML Preview - ' . escapeHtml($item['title']), $previewEmail);
        echo "[Timer] Generated HTML for approved item: {$item['title']}\n";
    } else {
        echo "[Timer] HTML generation FAILED for: {$item['title']} - " . ($htmlResult['error'] ?? 'Unknown error') . "\n";
    }
}

// ============ 4. AUTO-SCHEDULE ITEMS WHOSE HTML IS APPROVED BUT NOT YET IN QUEUE ============
$stmt = $db->prepare("SELECT ci.*, c.user_id as camp_user_id, c.start_date, c.posting_times, c.target_platform as camp_platform FROM campaign_items ci JOIN campaigns c ON c.id = ci.campaign_id LEFT JOIN scheduled_queue sq ON sq.topic_title = ci.title AND sq.user_id = c.user_id AND sq.status = 'Scheduled' WHERE ci.article_status = 'Final Article Approved' AND sq.id IS NULL ORDER BY ci.id ASC");
$stmt->execute();
$needSchedule = $stmt->fetchAll();

foreach ($needSchedule as $item) {
    $userId = $item['camp_user_id'];
    $activeSlot = 1;
    $stmt = $db->prepare('SELECT slot_number FROM user_workspace_slots WHERE user_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$userId]);
    $slotRow = $stmt->fetch();
    if ($slotRow) $activeSlot = $slotRow['slot_number'] ?? 1;

    $platform = !empty($item['target_platform']) && $item['target_platform'] !== 'local' ? $item['target_platform'] : ($item['camp_platform'] ?? 'local');

    $schedDate = $item['scheduled_date'] ?? null;
    $schedTime = $item['scheduled_time'] ?? null;
    if (!empty($schedDate) && !empty($schedTime)) {
        $parts = explode(':', $schedTime);
        $scheduledDate = new DateTime($schedDate);
        $scheduledDate->setTime(intval($parts[0] ?? 10), intval($parts[1] ?? 0), 0);
        if ($scheduledDate <= new DateTime()) $scheduledDate->modify('+1 day');
        $scheduledStr = $scheduledDate->format('Y-m-d H:i:s');
    } else {
        $startDate = !empty($item['start_date']) ? $item['start_date'] : date('Y-m-d');
        $postingTimes = json_decode($item['posting_times'] ?? '["10:00"]', true) ?: ['10:00'];
        $dayNum = intval($item['day_number'] ?? 1);
        $postNum = intval($item['post_number'] ?? 1);
        $timeStr = $postingTimes[min($postNum - 1, count($postingTimes) - 1)] ?? '10:00';
        $parts = explode(':', $timeStr);
        $scheduledDate = (new DateTime($startDate))->modify(($dayNum - 1) . ' days');
        $scheduledDate->setTime(intval($parts[0]), intval($parts[1]), 0);
        if ($scheduledDate <= new DateTime()) $scheduledDate->modify('+1 day');
        $scheduledStr = $scheduledDate->format('Y-m-d H:i:s');
    }

    $stmt = $db->prepare('INSERT INTO scheduled_queue (user_id, slot_number, topic_title, keyword, category, scheduled_time, target_platform, status, created_at, target_link, target_anchor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $activeSlot, $item['title'], $item['primary_keyword'], 'Approved Article', $scheduledStr, $platform, 'Scheduled', $nowStr, $item['internal_links'] ?? '', $item['primary_keyword'] ?? '']);
    echo "[Timer] Auto-scheduled: {$item['title']} for $scheduledStr on $platform\n";
}

echo "[Approval Timer] Completed at $nowStr\n";
