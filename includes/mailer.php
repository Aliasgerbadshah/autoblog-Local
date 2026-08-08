<?php
/**
 * AutoBlog SaaS - Brevo Email Sender & Rich Approval Email Builder
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/config.php';

function sendBrevoEmail($toEmail, $subject, $htmlContent, $apiKey = null, $senderEmail = null) {
    $apiKey = $apiKey ?: DEFAULT_BREVO_API_KEY;
    $senderEmail = $senderEmail ?: DEFAULT_BREVO_SENDER_EMAIL;

    $payload = [
        'sender' => ['email' => $senderEmail, 'name' => 'AutoBlog Approval Desk'],
        'to' => [['email' => $toEmail]],
        'subject' => $subject,
        'htmlContent' => $htmlContent
    ];

    $result = curlPost('https://api.brevo.com/v3/smtp/email', $payload, [
        'api-key: ' . $apiKey,
        'Content-Type: application/json'
    ], 15);

    if ($result['success'] && in_array($result['http_code'], [200, 201])) {
        return true;
    }
    error_log("[Brevo Email Error] HTTP {$result['http_code']}: " . ($result['raw'] ?? 'Unknown'));
    return false;
}

function sendApprovalEmail($userId, $subject, $htmlContent) {
    $db = getDB();
    $stmt = $db->prepare('SELECT email FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row || empty($row['email'])) {
        error_log("[Approval Email Error] No recipient email for user $userId");
        return false;
    }

    $apiKey = getenv('BREVO_API_KEY') ?: DEFAULT_BREVO_API_KEY;
    $sender = getenv('BREVO_SENDER_EMAIL') ?: DEFAULT_BREVO_SENDER_EMAIL;

    return sendBrevoEmail($row['email'], $subject, $htmlContent, $apiKey, $sender);
}

/**
 * Build a rich HTML email body containing the full draft content
 * for each campaign item, with APPROVE / DISAPPROVE buttons.
 */
function buildRichApprovalEmailHtml($campaignItems, $campaignData, $db) {
    $domain = $campaignData['domain_url'] ?? 'your website';
    $days = $campaignData['days'] ?? 7;
    $perDay = $campaignData['posts_per_day'] ?? 1;
    $total = count($campaignItems);
    $baseUrl = APP_BASE_URL;

    $articlesHtml = '';
    $itemNum = 0;

    foreach ($campaignItems as $item) {
        $itemNum++;
        $kws = json_decode($item['keyword_data'] ?? '[]', true) ?: [];
        $links = json_decode($item['internal_links'] ?? '[]', true) ?: [];
        $ext = json_decode($item['external_links'] ?? '[]', true) ?: [];
        $heads = json_decode($item['headings'] ?? '{}', true) ?: [];
        $prompts = json_decode($item['image_prompts'] ?? '[]', true) ?: [];

        // Get the approval token for this item
        $stmt = $db->prepare("SELECT token FROM approval_tokens WHERE campaign_item_id = ? AND approval_type = 'roadmap' AND decision IN ('Pending','Provisional') ORDER BY id DESC LIMIT 1");
        $stmt->execute([$item['id']]);
        $tok = $stmt->fetch();
        $token = $tok ? $tok['token'] : '';

        $approveUrl = $baseUrl . '/api/demo/decision/' . $token . '/approve';
        $rejectUrl = $baseUrl . '/api/demo/decision/' . $token . '/reject';

        // Keywords table
        $kwRows = '';
        foreach ($kws as $x) {
            $kwRows .= '<tr>'
                . '<td style="padding:6px 10px;border:1px solid #e2e8f0;font-weight:600;">' . escapeHtml($x['keyword'] ?? '') . '</td>'
                . '<td style="padding:6px 10px;border:1px solid #e2e8f0;">' . escapeHtml($x['volume'] ?? '—') . '</td>'
                . '<td style="padding:6px 10px;border:1px solid #e2e8f0;">' . escapeHtml($x['difficulty'] ?? '—') . '</td>'
                . '<td style="padding:6px 10px;border:1px solid #e2e8f0;">' . escapeHtml($x['intent'] ?? '—') . '</td>'
                . '</tr>';
        }

        // Internal links
        $intLinks = '';
        foreach ($links as $x) {
            $intLinks .= '<li style="margin-bottom:4px;"><a href="' . escapeHtml($x['url'] ?? '') . '" style="color:#1b57f6;text-decoration:none;font-weight:600;">' . escapeHtml($x['anchor_text'] ?? $x['url']) . '</a></li>';
        }

        // External links
        $extLinks = '';
        foreach ($ext as $x) {
            $extLinks .= '<li style="margin-bottom:4px;"><a href="' . escapeHtml($x['url'] ?? '') . '" style="color:#1b57f6;text-decoration:none;font-weight:600;">' . escapeHtml($x['anchor_text'] ?? $x['url']) . '</a></li>';
        }

        // Heading structure
        $h1 = escapeHtml($heads['H1'] ?? $item['title']);
        $h2List = implode(' &rarr; ', array_map('escapeHtml', $heads['H2'] ?? []));
        $h3List = implode(' &rarr; ', array_map('escapeHtml', $heads['H3'] ?? []));

        // Image plan
        $imgPlanHtml = '';
        foreach ($prompts as $pi => $prompt) {
            $imgPlanHtml .= '<p style="margin:4px 0;color:#475569;font-size:0.85rem;">&#128247; Image ' . ($pi + 1) . ': ' . escapeHtml($prompt) . '</p>';
        }

        // Primary keyword display
        $pkDisplay = escapeHtml($item['primary_keyword']);

        // Approve/Reject buttons
        $buttonsHtml = '';
        if ($token) {
            $buttonsHtml = '<div style="margin-top:16px;text-align:center;">'
                . '<a href="' . $approveUrl . '" style="display:inline-block;background:#10b981;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:0.95rem;margin-right:10px;">&#9989; APPROVE</a>'
                . '<a href="' . $rejectUrl . '" style="display:inline-block;background:#ef4444;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:0.95rem;">&#10060; DISAPPROVE</a>'
                . '</div>';
        } else {
            $buttonsHtml = '<p style="color:#64748b;font-style:italic;">No pending approval token for this item.</p>';
        }

        $dayNum = $item['day_number'];
        $postNum = $item['post_number'];

        $articlesHtml .= '<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:24px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.04);">'
            . '<div style="color:#64748b;font-size:0.8rem;font-weight:700;margin-bottom:8px;">ARTICLE ' . $itemNum . ' / ' . $total . ' &mdash; Day ' . $dayNum . ' &middot; Blog ' . $postNum . '</div>'
            . '<h2 style="color:#0f172a;font-size:1.3rem;font-weight:800;margin:0 0 6px 0;">' . $h1 . '</h2>'
            . '<p style="color:#1b57f6;font-weight:700;font-size:0.9rem;margin-bottom:14px;">Primary keyword: <code style="background:#f1f5f9;padding:2px 8px;border-radius:4px;">' . $pkDisplay . '</code></p>'

            . '<h3 style="color:#0f172a;font-size:0.95rem;font-weight:700;margin:14px 0 6px 0;">&#128202; Keyword Research</h3>'
            . '<table style="width:100%;border-collapse:collapse;font-size:0.85rem;">'
            . '<tr style="background:#f1f5f9;"><th style="padding:6px 10px;border:1px solid #e2e8f0;text-align:left;">Keyword</th><th style="padding:6px 10px;border:1px solid #e2e8f0;text-align:left;">Volume</th><th style="padding:6px 10px;border:1px solid #e2e8f0;text-align:left;">Difficulty</th><th style="padding:6px 10px;border:1px solid #e2e8f0;text-align:left;">Intent</th></tr>'
            . $kwRows
            . '</table>'

            . '<h3 style="color:#0f172a;font-size:0.95rem;font-weight:700;margin:14px 0 6px 0;">&#127959; Heading Structure</h3>'
            . '<p style="color:#475569;font-size:0.85rem;margin:0 0 4px 0;"><b>H1:</b> ' . $h1 . '</p>'
            . '<p style="color:#475569;font-size:0.85rem;margin:0 0 4px 0;"><b>H2:</b> ' . $h2List . '</p>'
            . '<p style="color:#475569;font-size:0.85rem;margin:0 0 4px 0;"><b>H3:</b> ' . $h3List . '</p>'

            . '<h3 style="color:#0f172a;font-size:0.95rem;font-weight:700;margin:14px 0 6px 0;">&#128279; Internal Links</h3>'
            . '<ul style="margin:0;padding-left:18px;color:#475569;font-size:0.85rem;">' . $intLinks . '</ul>'

            . '<h3 style="color:#0f172a;font-size:0.95rem;font-weight:700;margin:14px 0 6px 0;">&#127760; External References</h3>'
            . '<ul style="margin:0;padding-left:18px;color:#475569;font-size:0.85rem;">' . $extLinks . '</ul>'

            . '<h3 style="color:#0f172a;font-size:0.95rem;font-weight:700;margin:14px 0 6px 0;">&#128444; Image Generation Plan</h3>'
            . $imgPlanHtml

            . $buttonsHtml
            . '</div>';
    }

    $emailHtml = '<!DOCTYPE html><html><head><meta charset="utf-8"></head>'
        . '<body style="font-family:Arial,Helvetica,sans-serif;background:#f8fafc;color:#0f172a;margin:0;padding:0;">'
        . '<div style="max-width:700px;margin:0 auto;padding:24px;">'

        . '<div style="text-align:center;margin-bottom:24px;">'
        . '<h1 style="color:#0f172a;font-size:1.6rem;font-weight:800;margin:0 0 6px 0;">&#10024; AutoBlog Roadmap Draft</h1>'
        . '<p style="color:#64748b;font-size:0.9rem;margin:0;">Website: <strong>' . escapeHtml($domain) . '</strong> &middot; ' . $days . '-day campaign &middot; ' . $perDay . ' article(s)/day &middot; ' . $total . ' total articles</p>'
        . '</div>'

        . '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 18px;margin-bottom:20px;font-size:0.85rem;color:#1e40af;">'
        . '<strong>How this works:</strong> Review each draft below. Click <b>APPROVE</b> to confirm the article plan, or <b>DISAPPROVE</b> to request a replacement with a different research angle. After two clicks, the decision is final.'
        . '</div>'

        . $articlesHtml

        . '<div style="text-align:center;margin-top:24px;padding-top:16px;border-top:1px solid #e2e8f0;color:#64748b;font-size:0.8rem;">'
        . 'AutoBlog SaaS Approval Desk &middot; You can also review from your <a href="' . $baseUrl . '" style="color:#1b57f6;font-weight:700;">dashboard</a>'
        . '</div>'

        . '</div></body></html>';

    return $emailHtml;
}

/**
 * Build a replacement email for a disapproved item.
 */
function buildReplacementEmailHtml($item, $newToken, $db) {
    $baseUrl = APP_BASE_URL;
    $approveUrl = $baseUrl . '/api/demo/decision/' . $newToken . '/approve';
    $rejectUrl = $baseUrl . '/api/demo/decision/' . $newToken . '/reject';

    $kws = json_decode($item['keyword_data'] ?? '[]', true) ?: [];
    $heads = json_decode($item['headings'] ?? '{}', true) ?: [];
    $links = json_decode($item['internal_links'] ?? '[]', true) ?: [];
    $prompts = json_decode($item['image_prompts'] ?? '[]', true) ?: [];

    $kwRows = '';
    foreach ($kws as $x) {
        $kwRows .= '<tr>'
            . '<td style="padding:6px 10px;border:1px solid #e2e8f0;font-weight:600;">' . escapeHtml($x['keyword'] ?? '') . '</td>'
            . '<td style="padding:6px 10px;border:1px solid #e2e8f0;">' . escapeHtml($x['volume'] ?? '—') . '</td>'
            . '<td style="padding:6px 10px;border:1px solid #e2e8f0;">' . escapeHtml($x['difficulty'] ?? '—') . '</td>'
            . '<td style="padding:6px 10px;border:1px solid #e2e8f0;">' . escapeHtml($x['intent'] ?? '—') . '</td>'
            . '</tr>';
    }

    $intLinks = '';
    foreach ($links as $x) {
        $intLinks .= '<li style="margin-bottom:4px;"><a href="' . escapeHtml($x['url'] ?? '') . '" style="color:#1b57f6;text-decoration:none;font-weight:600;">' . escapeHtml($x['anchor_text'] ?? $x['url']) . '</a></li>';
    }

    $h1 = escapeHtml($heads['H1'] ?? $item['title']);
    $h2List = implode(' &rarr; ', array_map('escapeHtml', $heads['H2'] ?? []));
    $pkDisplay = escapeHtml($item['primary_keyword']);

    $imgPlanHtml = '';
    foreach ($prompts as $pi => $prompt) {
        $imgPlanHtml .= '<p style="margin:4px 0;color:#475569;font-size:0.85rem;">&#128247; Image ' . ($pi + 1) . ': ' . escapeHtml($prompt) . '</p>';
    }

    $emailHtml = '<!DOCTYPE html><html><head><meta charset="utf-8"></head>'
        . '<body style="font-family:Arial,Helvetica,sans-serif;background:#f8fafc;color:#0f172a;margin:0;padding:0;">'
        . '<div style="max-width:700px;margin:0 auto;padding:24px;">'

        . '<div style="text-align:center;margin-bottom:24px;">'
        . '<h1 style="color:#f59e0b;font-size:1.4rem;font-weight:800;margin:0 0 6px 0;">&#128260; Replacement Blog Plan</h1>'
        . '<p style="color:#64748b;font-size:0.9rem;margin:0;">The original was disapproved. Here is the replacement with a new research angle.</p>'
        . '</div>'

        . '<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:24px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.04);">'
        . '<h2 style="color:#0f172a;font-size:1.3rem;font-weight:800;margin:0 0 6px 0;">' . $h1 . '</h2>'
        . '<p style="color:#1b57f6;font-weight:700;font-size:0.9rem;margin-bottom:14px;">Primary keyword: <code style="background:#f1f5f9;padding:2px 8px;border-radius:4px;">' . $pkDisplay . '</code></p>'

        . '<h3 style="color:#0f172a;font-size:0.95rem;font-weight:700;margin:14px 0 6px 0;">&#128202; Keyword Research</h3>'
        . '<table style="width:100%;border-collapse:collapse;font-size:0.85rem;">'
        . '<tr style="background:#f1f5f9;"><th style="padding:6px 10px;border:1px solid #e2e8f0;text-align:left;">Keyword</th><th style="padding:6px 10px;border:1px solid #e2e8f0;text-align:left;">Volume</th><th style="padding:6px 10px;border:1px solid #e2e8f0;text-align:left;">Difficulty</th><th style="padding:6px 10px;border:1px solid #e2e8f0;text-align:left;">Intent</th></tr>'
        . $kwRows
        . '</table>'

        . '<h3 style="color:#0f172a;font-size:0.95rem;font-weight:700;margin:14px 0 6px 0;">&#127959; Heading Structure</h3>'
        . '<p style="color:#475569;font-size:0.85rem;margin:0 0 4px 0;"><b>H1:</b> ' . $h1 . '</p>'
        . '<p style="color:#475569;font-size:0.85rem;margin:0 0 4px 0;"><b>H2:</b> ' . $h2List . '</p>'

        . '<h3 style="color:#0f172a;font-size:0.95rem;font-weight:700;margin:14px 0 6px 0;">&#128279; Internal Links</h3>'
        . '<ul style="margin:0;padding-left:18px;color:#475569;font-size:0.85rem;">' . $intLinks . '</ul>'

        . '<h3 style="color:#0f172a;font-size:0.95rem;font-weight:700;margin:14px 0 6px 0;">&#128444; Image Generation Plan</h3>'
        . $imgPlanHtml

        . '<div style="margin-top:16px;text-align:center;">'
        . '<a href="' . $approveUrl . '" style="display:inline-block;background:#10b981;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:0.95rem;margin-right:10px;">&#9989; APPROVE REPLACEMENT</a>'
        . '<a href="' . $rejectUrl . '" style="display:inline-block;background:#ef4444;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:0.95rem;">&#10060; DISAPPROVE AGAIN</a>'
        . '</div>'
        . '</div>'

        . '<div style="text-align:center;margin-top:24px;padding-top:16px;border-top:1px solid #e2e8f0;color:#64748b;font-size:0.8rem;">'
        . 'AutoBlog SaaS Approval Desk &middot; <a href="' . $baseUrl . '" style="color:#1b57f6;font-weight:700;">Return to Dashboard</a>'
        . '</div>'

        . '</div></body></html>';

    return $emailHtml;
}

/**
 * Build an email for HTML article preview approval.
 * Contains a link to view the full preview, and APPROVE / REWRITE buttons.
 */
function buildHtmlPreviewEmailHtml($item, $htmlUrl, $htmlToken, $usedChatApi) {
    $baseUrl = APP_BASE_URL;
    $approveUrl = $baseUrl . '/api/demo/html-decision/' . $htmlToken . '/approve';
    $rejectUrl = $baseUrl . '/api/demo/html-decision/' . $htmlToken . '/reject';
    $previewUrl = $baseUrl . $htmlUrl;

    $title = escapeHtml($item['title'] ?? 'Untitled');
    $keyword = escapeHtml($item['primary_keyword'] ?? '');
    $apiNote = $usedChatApi ? 'Written by your selected Chat AI model with AI-generated visuals' : 'Structured from approved research data (save a Chat API for AI-written content)';

    return '<!DOCTYPE html><html><head><meta charset="utf-8"></head>'
        . '<body style="font-family:Arial,Helvetica,sans-serif;background:#f8fafc;color:#0f172a;margin:0;padding:0;">'
        . '<div style="max-width:700px;margin:0 auto;padding:24px;">'
        . '<div style="text-align:center;margin-bottom:24px;">'
        . '<h1 style="color:#0f172a;font-size:1.5rem;font-weight:800;margin:0 0 6px 0;">&#128196; Blog HTML Preview Ready</h1>'
        . '<p style="color:#64748b;font-size:0.9rem;margin:0;">' . $title . '</p></div>'
        . '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 18px;margin-bottom:20px;font-size:0.85rem;color:#1e40af;">'
        . '<strong>Article generated!</strong> ' . $apiNote . '. Review the preview and approve or request a rewrite.</div>'
        . '<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:24px;margin-bottom:20px;">'
        . '<h2 style="color:#0f172a;font-size:1.2rem;font-weight:800;margin:0 0 10px 0;">' . $title . '</h2>'
        . '<p style="color:#1b57f6;font-weight:700;font-size:0.9rem;margin-bottom:16px;">Primary keyword: <code style="background:#f1f5f9;padding:2px 8px;border-radius:4px;">' . $keyword . '</code></p>'
        . '<p style="color:#475569;font-size:0.85rem;margin-bottom:16px;">Click below to open the full preview in your browser and review layout, content quality, images, links, and FAQ section.</p>'
        . '<a href="' . $previewUrl . '" style="display:inline-block;background:#1b57f6;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:0.95rem;">&#128065; View Full HTML Preview</a></div>'
        . '<div style="text-align:center;margin-bottom:20px;">'
        . '<a href="' . $approveUrl . '" style="display:inline-block;background:#10b981;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:1rem;margin-right:10px;">&#9989; APPROVE HTML ARTICLE</a>'
        . '<a href="' . $rejectUrl . '" style="display:inline-block;background:#ef4444;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:1rem;">&#128260; REWRITE ARTICLE</a></div>'
        . '<div style="background:#fef3c7;border:1px solid #fbbf24;border-radius:10px;padding:14px 18px;margin-bottom:20px;font-size:0.85rem;color:#92400e;">'
        . '<strong>If you REWRITE:</strong> The system will generate a completely new article with a different angle, new content, and new images.</div>'
        . '<div style="text-align:center;margin-top:24px;padding-top:16px;border-top:1px solid #e2e8f0;color:#64748b;font-size:0.8rem;">'
        . 'AutoBlog SaaS &middot; <a href="' . $baseUrl . '" style="color:#1b57f6;font-weight:700;">Return to Dashboard</a></div>'
        . '</div></body></html>';
}
