<?php
/**
 * AutoBacklink - Maker (daily run orchestration)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/content_engine.php';
require_once __DIR__ . '/publishers.php';

class BacklinkMaker {

    /**
     * Pick targets due for posting today.
     * Round-robin: oldest last-posted first, respecting min_interval_days.
     */
    public static function pickDueTargets($limit = DEFAULT_DAILY_COUNT) {
        $db = getDB();
        $now = time();
        $rows = $db->query('SELECT * FROM targets WHERE is_active = 1 ORDER BY id ASC')->fetchAll();
        $due = [];
        foreach ($rows as $t) {
            if (count($due) >= $limit) break;
            $last = !empty($t['last_posted_at']) ? strtotime($t['last_posted_at']) : null;
            $interval = max(1, intval($t['min_interval_days'] ?? DEFAULT_MIN_INTERVAL_DAYS)) * 86400;
            if ($last === null || ($now - $last) >= $interval) {
                $due[] = $t;
            }
        }
        return $due;
    }

    /**
     * Run the daily batch. Returns summary array.
     */
    public static function runDaily() {
        $settings = getSettings();
        $count = min(intval($settings['daily_count'] ?? DEFAULT_DAILY_COUNT), MAX_DAILY_JOBS);
        $targets = self::pickDueTargets($count);

        addRunLog("Daily run started — " . count($targets) . " target(s) due.");
        $summary = ['queued' => 0, 'published' => 0, 'manual_ready' => 0, 'failed' => 0, 'details' => []];
        $today = date('Y-m-d');

        if (empty($targets)) {
            addRunLog("No targets due today (interval not reached, or no active targets).");
            $summary['message'] = 'No targets are due today. Check each target interval, or lower min_interval_days.';
            return $summary;
        }

        foreach ($targets as $target) {
            $job = self::processTarget($settings, $target, $today);
            $status = $job['status'] ?? 'Failed';
            if ($status === 'Published') $summary['published']++;
            elseif ($status === 'Manual Ready') $summary['manual_ready']++;
            else $summary['failed']++;
            $summary['queued']++;
            $summary['details'][] = [
                'job_id' => $job['id'] ?? null,
                'target' => $target['name'],
                'title' => $job['title'] ?? '',
                'status' => $status,
                'url' => $job['published_url'] ?? '',
                'error' => $job['error_message'] ?? '',
            ];
        }

        $msg = "Daily run finished: {$summary['published']} auto-posted, {$summary['manual_ready']} ready for manual paste, {$summary['failed']} failed.";
        addRunLog($msg);
        $summary['message'] = $msg;
        return $summary;
    }

    /**
     * Process ONE target: topic → angle → anchor → content → image → publish/package.
     */
    public static function processTarget(array $settings, array $target, $today = null) {
        $db = getDB();
        $today = $today ?: date('Y-m-d');
        $now = nowString();

        // Load used topics for dedup
        $usedRows = $db->query('SELECT title, keyword FROM used_topics ORDER BY id DESC LIMIT 300')->fetchAll();

        // 1) Topic
        $picked = BacklinkContent::pickTopic($settings, $target, $usedRows);
        $topic = $picked['topic'];

        // 2) Angle + anchor
        $angle = BacklinkContent::pickAngle();
        $anchor = BacklinkContent::pickAnchor($settings, $target);

        // 3) Content
        $post = BacklinkContent::generatePost($settings, $target, $topic, $angle, $anchor);

        // 4) Image
        $slug = slugify($post['title']);
        $dirRel = 'packages/' . $today . '/' . $slug;
        $dirAbs = APP_ROOT . '/' . $dirRel;
        if (!is_dir($dirAbs)) mkdir($dirAbs, 0755, true);

        $imageFile = $dirRel . '/image.png';
        $imageUrl = '';
        $imageCreds = BacklinkContent::getImageCreds();
        if (!empty($imageCreds['api_key'])) {
            $imgPrompt = 'Editorial photograph illustrating: ' . $topic . '. Natural lighting, professional magazine style, no text, no logos, no watermark.';
            $imgRes = AIProviderClient::image($imageCreds, $imgPrompt);
            if (!empty($imgRes['success']) && !empty($imgRes['url'])) {
                $imageUrl = $imgRes['url'];
                $saved = bkSaveImage($imgRes['url'], $dirAbs . '/image.png');
                if ($saved) $imageFile = $saved;
            } else {
                addRunLog('Image API failed for "' . $topic . '": ' . ($imgRes['error'] ?? '?') . ' — using fallback image.');
            }
        }
        if (!file_exists($dirAbs . '/image.png')) {
            bkPlaceholderImage($topic, $dirAbs . '/image.png');
        }
        // If API gave a remote URL we couldn't download, still keep it for API posting
        if (empty($imageUrl) && file_exists($dirAbs . '/image.png')) {
            // local-only image
        }

        // 5) Save package files (for the paste queue)
        $altText = $topic;
        $contentHtml = $post['content_html'];
        // fill the empty <img src=""> placeholder with our image (relative web path)
        $webImg = '/' . ltrim($imageFile, '/');
        $contentHtml = preg_replace('/<img([^>]*)src=""/', '<img$1src="' . escapeHtml($webImg) . '"', $contentHtml, 1);
        file_put_contents($dirAbs . '/post.html', self::packageHtmlPage($post['title'], $contentHtml, $target));
        file_put_contents($dirAbs . '/copy.txt', "TITLE:\n" . $post['title'] . "\n\nANCHOR TEXT USED:\n" . $anchor . "\n\nBODY (plain text):\n" . $post['content_text'] . "\n");

        // 6) Insert job row
        $st = $db->prepare('INSERT INTO jobs (target_id, run_date, angle, topic, title, anchor_text, content_html, content_text, image_url, image_file, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([$target['id'], $today, $angle, $topic, $post['title'], $anchor, $contentHtml, $post['content_text'], $imageUrl, $imageFile, 'Content Ready', $now]);
        $jobId = (int)$db->lastInsertId();

        // 7) Publish or prepare manual package
        if (($target['publish_mode'] ?? 'manual') === 'api') {
            $st2 = $db->prepare("UPDATE jobs SET status = 'Auto Posting' WHERE id = ?");
            $st2->execute([$jobId]);
            $res = BacklinkPublisher::publish($target, $post['title'], $contentHtml, $imageUrl);
            if (!empty($res['success'])) {
                $pubUrl = trim((string)($res['url'] ?? ''));
                if ($pubUrl === '') {
                    // Safety: never mark "Published" without a real URL
                    $st3 = $db->prepare("UPDATE jobs SET status = 'Failed', error_message = ? WHERE id = ?");
                    $st3->execute(['Publisher said success but returned NO post URL — the post was likely not created. Re-run and check the error text.'], $jobId);
                    addRunLog("FAILED {$target['name']} (no URL returned): {$post['title']}");
                    return ['id' => $jobId, 'status' => 'Failed', 'title' => $post['title'], 'error_message' => 'No post URL returned — post likely not created.'];
                }
                $st3 = $db->prepare("UPDATE jobs SET status = 'Published', published_url = ?, posted_at = datetime('now') WHERE id = ?");
                $st3->execute([$pubUrl, $jobId]);
                $st4 = $db->prepare("UPDATE targets SET last_posted_at = datetime('now'), post_count = post_count + 1 WHERE id = ?");
                $st4->execute([$target['id']]);
                addRunLog("Published to {$target['name']}: {$post['title']} → " . ($res['url'] ?? ''));
                return ['id' => $jobId, 'status' => 'Published', 'title' => $post['title'], 'published_url' => $res['url'] ?? ''];
            }
            $st3 = $db->prepare("UPDATE jobs SET status = 'Failed', error_message = ? WHERE id = ?");
            $st3->execute([trim((string)($res['error'] ?? 'Unknown error')), $jobId]);
            addRunLog("FAILED {$target['name']}: " . ($res['error'] ?? '?'));
            return ['id' => $jobId, 'status' => 'Failed', 'title' => $post['title'], 'error_message' => $res['error'] ?? ''];
        }

        // Manual lane
        $instructions = self::buildInstructions($target, $settings, $post['title'], $slug);
        $st2 = $db->prepare("UPDATE jobs SET status = 'Manual Ready', instructions = ? WHERE id = ?");
        $st2->execute([$instructions, $jobId]);
        addRunLog("Manual package ready for {$target['name']}: {$post['title']}");
        return ['id' => $jobId, 'status' => 'Manual Ready', 'title' => $post['title']];
    }

    /**
     * Mark a manual job as posted (user pasted the live URL).
     */
    public static function markPosted($jobId, $publishedUrl) {
        $db = getDB();
        $st = $db->prepare('SELECT * FROM jobs WHERE id = ?');
        $st->execute([$jobId]);
        $job = $st->fetch();
        if (!$job) return ['success' => false, 'error' => 'Job not found.'];
        if (empty($publishedUrl)) return ['success' => false, 'error' => 'Published URL is required.'];

        $st2 = $db->prepare("UPDATE jobs SET status = 'Manual Posted', published_url = ?, posted_at = datetime('now') WHERE id = ?");
        $st2->execute([$publishedUrl, $jobId]);

        $st3 = $db->prepare("UPDATE targets SET last_posted_at = datetime('now'), post_count = post_count + 1 WHERE id = ?");
        $st3->execute([$job['target_id']]);

        // Verify immediately (best effort)
        $check = LinkVerifier::verify($publishedUrl, (string)getData('main_site_url', ''));
        $st4 = $db->prepare("UPDATE jobs SET is_dofollow = ?, last_verified_at = datetime('now') WHERE id = ?");
        $st4->execute([$check['is_dofollow'] === null ? null : (int)$check['is_dofollow'], $jobId]);

        addRunLog("Marked posted job #$jobId → $publishedUrl");
        return ['success' => true, 'verified' => $check];
    }

    /**
     * Step-by-step instructions for the manual lane, tailored to target type.
     */
    public static function buildInstructions(array $target, array $settings, $title, $slug) {
        $type = $target['target_type'] ?? 'blog';
        $url = $target['site_url'];
        $notes = trim($target['account_notes'] ?? '');
        $noteLine = $notes ? " (Your saved note: $notes)" : '';
        $mainSite = trim($settings['main_site_url'] ?? '');
        $imgRel = '/packages/' . date('Y-m-d') . '/' . $slug . '/image.png';

        $base = [
            "1. Open $url",
            "2. Log in to your account$noteLine",
        ];

        $typeSteps = [
            'directory' => [
                "3. Create (or edit) your business/profile listing",
                "4. Paste the TITLE: \"$title\"",
                "5. Paste the BODY text (use the Copy Body button)",
                "6. Upload the image file (image.png — Download Image button)",
                "7. Put $mainSite in the Website/URL field (most important step!)",
                "8. Fill remaining fields honestly, then Submit",
            ],
            'forum' => [
                "3. Go to the right section/category (introductions or the niche section)",
                "4. Start a new post with TITLE: \"$title\"",
                "5. Paste the BODY (Copy Body button)",
                "6. Attach the image (Download Image button → upload it here)",
                "7. The link to $mainSite is already inside the body — keep it",
                "8. Post it. If the forum requires approval, just leave it as pending",
            ],
            'qa' => [
                "3. Search the site for a related question first — answer an existing one if it fits better",
                "4. Write the answer using the BODY (Copy Body button), adjust the tone to the question",
                "5. Add the image if the platform allows it",
                "6. Make sure the link to $mainSite stays in the answer (it is already there)",
                "7. Submit the answer",
            ],
            'blog' => [
                "3. Create a new post with TITLE: \"$title\"",
                "4. Paste the BODY (Copy Body button) — it includes the link to $mainSite",
                "5. Add the image (Download Image button → upload it here, place it at the top)",
                "6. Publish",
            ],
            'social' => [
                "3. Create a new post/note",
                "4. Paste the BODY (Copy Body button) — keep the link to $mainSite visible",
                "5. Attach the image (Download Image button)",
                "6. Publish",
            ],
            'review' => [
                "3. Write a new review with TITLE: \"$title\"",
                "4. Paste the BODY (Copy Body button)",
                "5. Add the image if allowed",
                "6. Submit the review",
            ],
            'other' => [
                "3. Create a new post/entry with TITLE: \"$title\"",
                "4. Paste the BODY (Copy Body button) — it already contains the link to $mainSite",
                "5. Add the image if the site allows it (Download Image button)",
                "6. Publish/Submit",
            ],
        ];

        $steps = array_merge($base, $typeSteps[$type] ?? $typeSteps['other']);
        $steps[] = "9. Copy the LIVE URL of your new post and paste it below → click \"Mark as posted\"";
        return implode("\n", $steps);
    }

    /**
     * Full standalone HTML page version of a package (for pasting into plain editors).
     */
    public static function packageHtmlPage($title, $html, $target) {
        $body = $html;
        return "<!doctype html><html><head><meta charset=\"utf-8\"><title>" . escapeHtml($title) . "</title></head><body style=\"font-family:Arial,sans-serif;max-width:760px;margin:24px auto;line-height:1.7;color:#222;\">$body<p style=\"margin-top:28px;font-size:0.85rem;color:#888;\">Generated by AutoBacklink for " . escapeHtml($target['name']) . ' on ' . date('Y-m-d') . "</p></body></html>";
    }
}

/**
 * Link verification (dofollow/nofollow + liveness) — same approach as
 * AutoBlog's Backlink Watchdog.
 */
class LinkVerifier {

    public static function verify($pageUrl, $myUrl) {
        $result = ['is_found' => null, 'is_dofollow' => null, 'status_code' => 0, 'error' => ''];
        if (SANDBOX_MODE || empty($pageUrl)) {
            $result['error'] = SANDBOX_MODE ? 'Verification disabled in preview sandbox — works after deployment.' : 'No URL';
            return $result;
        }
        if (!filter_var($myUrl, FILTER_VALIDATE_URL)) {
            // compare by host if main site is bare domain
            $myUrl = 'https://' . ltrim($myUrl, '/');
        }
        $res = curlGet($pageUrl, [], 12);
        $result['status_code'] = $res['http_code'];
        if ($res['http_code'] !== 200) {
            $result['error'] = 'HTTP ' . $res['http_code'];
            $result['is_found'] = false;
            return $result;
        }
        $html = (string)$res['raw'];
        $normalizedTarget = strtolower(parse_url($myUrl, PHP_URL_HOST) ?: $myUrl);
        $found = false; $dofollow = null;
        if (preg_match_all('/<a\b[^>]*href=["\']([^"\']+)["\']([^>]*)>/i', $html, $matches)) {
            foreach ($matches[1] as $i => $href) {
                $hrefHost = strtolower(parse_url($href, PHP_URL_HOST) ?: '');
                if (str_contains($hrefHost, $normalizedTarget) || str_contains(strtolower($href), $normalizedTarget)) {
                    $found = true;
                    $rel = strtolower($matches[2][$i] ?? '');
                    $dofollow = !(str_contains($rel, 'nofollow') || str_contains($rel, 'ugc') || str_contains($rel, 'sponsored'));
                    break;
                }
            }
        }
        $result['is_found'] = $found;
        $result['is_dofollow'] = $found ? $dofollow : null;
        return $result;
    }

    public static function recheckAll() {
        $db = getDB();
        $jobs = $db->query("SELECT * FROM jobs WHERE published_url != '' AND (status = 'Manual Posted' OR status = 'Published')")->fetchAll();
        $myUrl = (string)getData('main_site_url', '') ?: trim(getSettings()['main_site_url'] ?? '');
        $results = [];
        foreach ($jobs as $j) {
            if (SANDBOX_MODE) break;
            $check = self::verify($j['published_url'], $myUrl);
            $st = $db->prepare("UPDATE jobs SET is_dofollow = ?, last_verified_at = datetime('now') WHERE id = ?");
            $st->execute([$check['is_dofollow'] === null ? null : (int)$check['is_dofollow'], $j['id']]);
            $results[] = ['id' => $j['id'], 'url' => $j['published_url'], 'check' => $check];
        }
        return $results;
    }
}
