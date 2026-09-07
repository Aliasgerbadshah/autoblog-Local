<?php
/**
 * AutoBlog SaaS - Backlink Engine
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';

class BacklinkChecker {

    public static function verifyBacklink($pageUrl, $targetMyUrl) {
        $headers = ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36'];
        $result = [
            'page_url' => $pageUrl,
            'target_my_url' => $targetMyUrl,
            'status_code' => 0,
            'is_found' => false,
            'anchor_text' => '',
            'is_dofollow' => true,
            'error' => null
        ];

        $httpResult = curlGet($pageUrl, $headers, 10);
        $result['status_code'] = $httpResult['http_code'] ?? 0;

        if (!$httpResult['success'] || $httpResult['http_code'] !== 200) {
            $result['error'] = 'HTTP ' . ($httpResult['http_code'] ?? 0);
            return $result;
        }

        $dom = new DOMDocument();
        @$dom->loadHTML($httpResult['data'], LIBXML_NOERROR);
        $links = $dom->getElementsByTagName('a');
        $normalizedTarget = rtrim(strtolower($targetMyUrl), '/');

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (empty($href)) continue;
            $normalizedHref = rtrim(strtolower($href), '/');

            if (str_contains($normalizedHref, $normalizedTarget) || ($normalizedTarget && str_ends_with($normalizedTarget, $normalizedHref))) {
                $result['is_found'] = true;
                $result['anchor_text'] = trim($link->textContent) ?: '[Image/Empty]';

                $rel = strtolower($link->getAttribute('rel') ?? '');
                if (str_contains($rel, 'nofollow') || str_contains($rel, 'ugc') || str_contains($rel, 'sponsored')) {
                    $result['is_dofollow'] = false;
                } else {
                    $result['is_dofollow'] = true;
                }
                break;
            }
        }

        return $result;
    }

    public static function addAndCheckBacklink($targetSite, $backlinkUrl, $myUrl, $notes = '') {
        $checkRes = self::verifyBacklink($backlinkUrl, $myUrl);

        $db = getDB();
        $now = nowString();
        $stmt = $db->prepare('INSERT INTO backlinks (target_site, backlink_url, my_url, anchor_text, is_dofollow, status_code, is_found, last_checked, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $targetSite,
            $backlinkUrl,
            $myUrl,
            $checkRes['anchor_text'],
            $checkRes['is_dofollow'] ? 1 : 0,
            $checkRes['status_code'],
            $checkRes['is_found'] ? 1 : 0,
            $now,
            $notes
        ]);
        return $checkRes;
    }

    public static function getAllBacklinks($userId = null, $slotNumber = null) {
        $db = getDB();
        if ($userId && $slotNumber) {
            $stmt = $db->prepare('SELECT * FROM backlinks WHERE user_id = ? AND slot_number = ? ORDER BY id DESC');
            $stmt->execute([$userId, $slotNumber]);
        } else {
            $stmt = $db->query('SELECT * FROM backlinks ORDER BY id DESC');
        }
        return $stmt->fetchAll();
    }

    public static function auditAll($userId = null, $slotNumber = null) {
        $db = getDB();
        if ($userId && $slotNumber) {
            $stmt = $db->prepare('SELECT * FROM backlinks WHERE user_id = ? AND slot_number = ?');
            $stmt->execute([$userId, $slotNumber]);
        } else {
            $stmt = $db->query('SELECT * FROM backlinks');
        }
        $rows = $stmt->fetchAll();

        $updatedResults = [];
        foreach ($rows as $row) {
            $res = self::verifyBacklink($row['backlink_url'], $row['my_url']);
            $now = nowString();
            $stmt = $db->prepare('UPDATE backlinks SET anchor_text=?, is_dofollow=?, status_code=?, is_found=?, last_checked=? WHERE id=?');
            $stmt->execute([
                $res['anchor_text'],
                $res['is_dofollow'] ? 1 : 0,
                $res['status_code'],
                $res['is_found'] ? 1 : 0,
                $now,
                $row['id']
            ]);
            $updatedResults[] = $res;
        }
        return $updatedResults;
    }
}

class OutreachGenerator {

    public static function generatePitch($domain, $contactName, $myWebsite, $topicNiche, $articleTitle) {
        $contact = $contactName ?: 'Team';
        return <<<PITCH
Subject: Contribution / Collaboration Inquiry regarding $topicNiche on $domain

Hi $contact,

I hope this email finds you well!

I've been following $domain for a while and loved your recent articles on $topicNiche.

I am currently editing an in-depth piece titled "$articleTitle" on $myWebsite, which covers valuable insights, data points, and practical tips that your readers would find highly relevant.

Would you be open to:
1. Publishing a original high-quality guest article tailored specifically for $domain?
2. Mentioning or linking to our comprehensive resource on $articleTitle where it fits naturally in your existing content?

In return, I'd be happy to feature $domain in our upcoming content syndication network or share your content across our channels.

Looking forward to hearing your thoughts!

Best regards,
Content Operations Team
$myWebsite
PITCH;
    }

    public static function generateSyndicationTag($canonicalUrl, $contentTitle) {
        return [
            'html_head_tag' => "<link rel=\"canonical\" href=\"$canonicalUrl\" />",
            'footer_attribution' => "<p><em>Originally published on <a href=\"$canonicalUrl\" rel=\"canonical\">$contentTitle</a>.</em></p>",
            'rss_enclosure' => "<source url=\"$canonicalUrl\">$contentTitle</source>"
        ];
    }
}
