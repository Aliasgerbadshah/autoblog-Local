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
     * Per-site pacing: is this target's blog due?
     * Interval = max(min_interval_days, 24h / blog_daily_count) — the minimum gap always wins (safe).
     */
    public static function isBlogDue(array $t) {
        $last = !empty($t['last_posted_at']) ? strtotime($t['last_posted_at']) : null;
        $gapDays = max(1, intval($t['min_interval_days'] ?? 1));
        $perDay = max(1, min(5, intval($t['blog_daily_count'] ?? 1)));
        $intervalHours = max($gapDays * 24, max(4, (int)floor(24 / $perDay)));
        if ($last === null) return true;
        return (time() - $last) >= $intervalHours * 3600;
    }

    /** Per-site community pacing: due if the last comment is older than 24h / comments_per_day. */
    public static function isCommunityDue(array $t) {
        $perDay = max(1, min(3, intval($t['community_daily_count'] ?? 2)));
        $intervalHours = max(4, (int)floor(24 / $perDay));
        $last = !empty($t['community_last_at']) ? strtotime($t['community_last_at']) : null;
        if ($last === null) return true;
        return (time() - $last) >= $intervalHours * 3600;
    }

    /**
     * Timer window: true if $now is inside the day's posting cycle.
     * The cycle starts at $timeStr and lasts (postsPerDay-1) intervals + 2h,
     * so "2-3 per day + timer" still spreads the posts through the day.
     * Empty time = any time.
     */
    public static function inTimeWindow($timeStr, $now = null, $postsPerDay = 1) {
        $now = $now ?: time();
        $timeStr = trim((string)$timeStr);
        if ($timeStr === '' || !preg_match('/^(\d{1,2}):(\d{2})$/', $timeStr, $m)) return true;
        $perDay = max(1, min(5, intval($postsPerDay)));
        $interval = max(4 * 3600, (int)floor(24 * 3600 / $perDay));
        $cycleSeconds = ($perDay - 1) * $interval + 2 * 3600;
        $todayTarget = mktime((int)$m[1], (int)$m[2], 0, date('n', $now), date('j', $now), date('Y', $now));
        if ($now < $todayTarget) $todayTarget -= 86400;
        return $now >= $todayTarget && $now <= $todayTarget + $cycleSeconds;
    }

    /**
     * Run the batch (cron or "Run Now"). Per-site scheduling:
     * each site decides for itself (mode, posts/day, timer, community).
     * $force = bypass due-checks & timers (manual "Run Now" from the panel).
     */
    public static function runDaily($force = false) {
        $settings = getSettings();
        $db = getDB();
        $targets = $db->query('SELECT * FROM targets WHERE is_active = 1 ORDER BY id ASC')->fetchAll();

        $summary = ['queued' => 0, 'published' => 0, 'manual_ready' => 0, 'failed' => 0, 'community_posted' => 0, 'community_failed' => 0, 'details' => []];
        $budget = MAX_DAILY_JOBS;
        $dueCount = 0;

        foreach ($targets as $target) {
            // Wix site without any credentials yet → skip quietly (no point failing every 30 min)
            if (($target['platform'] ?? '') === 'wix') {
                $wcred = json_decode($target['credential_json'] ?? '{}', true) ?: [];
                if (empty($wcred['client_id']) && empty($wcred['access_token'])) {
                    $summary['details'][] = ['job_id' => null, 'target' => $target['name'], 'title' => '', 'status' => 'Skipped (Wix keys not saved)', 'url' => '', 'error' => ''];
                    continue;
                }
            }
            // ---- Blog lane ----
            $mode = $target['blog_mode'] ?? 'auto';
            if ($mode !== 'manual' && $budget > 0) {
                $due = $force || self::isBlogDue($target);
                $win = $force || self::inTimeWindow($target['blog_time'] ?? '', null, $target['blog_daily_count'] ?? 1);
                if ($due && $win) {
                    $dueCount++;
                    $job = self::processTarget($settings, $target);
                    $status = $job['status'] ?? 'Failed';
                    if ($status === 'Published') $summary['published']++;
                    elseif ($status === 'Manual Ready') $summary['manual_ready']++;
                    else $summary['failed']++;
                    $summary['queued']++;
                    $budget--;
                    $summary['details'][] = [
                        'job_id' => $job['id'] ?? null,
                        'target' => $target['name'],
                        'title' => $job['title'] ?? '',
                        'status' => $status,
                        'url' => $job['published_url'] ?? '',
                        'error' => $job['error_message'] ?? '',
                    ];
                }
            }

            // ---- Community lane (Wix only) ----
            if (($target['platform'] ?? '') === 'wix' && intval($target['community_enabled'] ?? 0) === 1) {
                $cdue = $force || self::isCommunityDue($target);
                $cwin = $force || self::inTimeWindow($target['community_time'] ?? '', null, $target['community_daily_count'] ?? 1);
                if ($cdue && $cwin) {
                    $dueCount++;
                    $cres = self::runCommunityOnce($settings, $target);
                    if (!empty($cres['success'])) $summary['community_posted']++;
                    else $summary['community_failed']++;
                    $summary['details'][] = [
                        'job_id' => null,
                        'target' => $target['name'] . ' (community)',
                        'title' => $cres['topic']['title'] ?? '',
                        'status' => $cres['success'] ? 'Commented' : 'Failed',
                        'url' => $cres['topic']['url'] ?? '',
                        'error' => $cres['success'] ? '' : ($cres['error'] ?? ''),
                    ];
                }
            }
        }

        if ($dueCount === 0) {
            addRunLog($force ? 'Run now: nothing to do (all sites in manual mode or community off).' : 'Run: no sites due right now (pacing/timer not reached).');
            $summary['message'] = $force
                ? 'Nothing to do — every site is in "manual/copy" mode or has community turned off.'
                : 'No sites are due right now. Each site follows its own pace (posts/day + minimum gap) and timer in Site Panels.';
        } else {
            $msg = "Run finished: {$summary['published']} auto-posted, {$summary['manual_ready']} ready for paste, {$summary['community_posted']} community comment(s) posted, {$summary['failed']} failed.";
            addRunLog($msg);
            $summary['message'] = $msg;
        }
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
            if (!empty($res['cred']) && is_array($res['cred'])) self::persistCred($target['id'], $res['cred']);
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

    /** Persist refreshed/learned credentials (Wix token cache, member id, discovered endpoints). */
    private static function persistCred($targetId, array $cred) {
        $db = getDB();
        $db->prepare('UPDATE targets SET credential_json = ? WHERE id = ?')->execute([json_encode($cred), $targetId]);
    }

    /**
     * Community lane for Wix: find a relevant topic in the chosen group,
     * write a helpful comment (AI or template) with our backlink, post it.
     * Rate-limited per-site by community_daily_count (1-3/day, bot-safe).
     */
    public static function runCommunityOnce(array $settings, array $target) {
        $db = getDB();
        $cred = json_decode($target['credential_json'] ?? '{}', true) ?: [];

        $tk = BacklinkPublisher::wixEnsureToken($cred);
        if (!$tk['ok']) return ['success' => false, 'error' => $tk['error']];
        $cred = $tk['cred'];

        $groupId = trim((string)($target['community_group_id'] ?? '')) ?: trim((string)($cred['group_id'] ?? ''));
        $rg = BacklinkPublisher::wixResolveGroup($cred, $groupId);
        if (!$rg['ok']) {
            self::persistCred($target['id'], $rg['cred']);
            return ['success' => false, 'error' => $rg['error']];
        }
        $groupId = $rg['group_id'];
        if (!empty($rg['auto_picked'])) {
            // remember the auto-picked group so the panel can show it
            $db->prepare('UPDATE targets SET community_group_id = ? WHERE id = ?')->execute([$groupId, $target['id']]);
            addRunLog("Wix community: auto-picked group \"{$rg['auto_picked']}\" for {$target['name']}");
        }
        $list = BacklinkPublisher::wixListTopics($rg['cred'], $groupId);
        self::persistCred($target['id'], $list['cred'] ?? $rg['cred']);
        if (empty($list['success']) || empty($list['topics'])) {
            return ['success' => false, 'error' => $list['error'] ?? 'No topics returned.'];
        }

        // never comment twice on the same topic
        $st = $db->prepare('SELECT topic_id FROM community_actions WHERE target_id = ? AND topic_id != ""');
        $st->execute([$target['id']]);
        $done = array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));

        $cands = array_values(array_filter($list['topics'], fn($tp) => !empty($tp['id']) && !in_array(strval($tp['id']), $done, true)));
        if (!$cands) {
            return ['success' => false, 'error' => 'No NEW topics to comment on — every visible topic was already answered. Wait for new topics or pick a different group in Site Panels.'];
        }
        // most relevant to our niche first (recent list order preserved for ties)
        $niche = trim((string)($target['niche'] ?? ''));
        usort($cands, function ($a, $b) use ($niche) {
            return BacklinkContent::topicRelevance($a['title'] . ' ' . $a['text'], $niche)
                <=> BacklinkContent::topicRelevance($b['title'] . ' ' . $b['text'], $niche);
        });
        $topic = $cands[0];

        $c = BacklinkContent::generateComment($settings, $target, $topic['title'], $topic['text']);
        $curCred = $list['cred'] ?? $rg['cred'];
        $res = BacklinkPublisher::wixPostReply($curCred, $groupId, $topic['id'], $c['text']);
        self::persistCred($target['id'], $res['cred'] ?? $curCred);

        $ok = !empty($res['success']);
        $st2 = $db->prepare('INSERT INTO community_actions (target_id, group_id, topic_id, topic_url, topic_title, comment_text, status, error_message, created_at, posted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\'), ?)');
        $st2->execute([$target['id'], $groupId, $topic['id'], $topic['url'], $topic['title'], $c['text'], $ok ? 'Posted' : 'Failed', $ok ? '' : ($res['error'] ?? '?'), $ok ? nowString() : null]);

        if ($ok) {
            $db->prepare("UPDATE targets SET community_last_at = datetime('now') WHERE id = ?")->execute([$target['id']]);
            addRunLog("Wix community: helpful comment posted on \"{$topic['title']}\" ({$target['name']})");
        } else {
            addRunLog("Wix community FAILED on {$target['name']}: " . ($res['error'] ?? '?'));
        }
        return ['success' => $ok, 'topic' => $topic, 'comment' => $c['text'], 'used_ai' => $c['used_ai'], 'error' => $ok ? '' : ($res['error'] ?? '?')];
    }

    /**
     * Copy & Paste Studio: generate a full backlink article (title + image +
     * body with one contextual backlink) WITHOUT posting — the user pastes it.
     * No job row is created until the user queues or marks it posted.
     */
    public static function generateCopyPackage(array $target) {
        $settings = getSettings();
        $db = getDB();
        $usedRows = $db->query('SELECT title, keyword FROM used_topics ORDER BY id DESC LIMIT 300')->fetchAll();

        $picked = BacklinkContent::pickTopic($settings, $target, $usedRows);
        $angle = BacklinkContent::pickAngle();
        $anchor = BacklinkContent::pickAnchor($settings, $target);
        $post = BacklinkContent::generatePost($settings, $target, $picked['topic'], $angle, $anchor);

        // image (same pipeline as the auto run)
        $slug = slugify($post['title']);
        $today = date('Y-m-d');
        $dirRel = 'packages/' . $today . '/' . $slug;
        $dirAbs = APP_ROOT . '/' . $dirRel;
        if (!is_dir($dirAbs)) mkdir($dirAbs, 0755, true);
        $imageFile = $dirRel . '/image.png';
        $imageUrl = '';
        $imageCreds = BacklinkContent::getImageCreds();
        if (!empty($imageCreds['api_key'])) {
            $imgPrompt = 'Editorial photograph illustrating: ' . $picked['topic'] . '. Natural lighting, professional magazine style, no text, no logos, no watermark.';
            $imgRes = AIProviderClient::image($imageCreds, $imgPrompt);
            if (!empty($imgRes['success']) && !empty($imgRes['url'])) {
                $imageUrl = $imgRes['url'];
                $saved = bkSaveImage($imgRes['url'], $dirAbs . '/image.png');
                if ($saved) $imageFile = $saved;
            }
        }
        if (!file_exists($dirAbs . '/image.png')) bkPlaceholderImage($picked['topic'], $dirAbs . '/image.png');

        $webImg = '/' . ltrim($imageFile, '/');
        $contentHtml = $post['content_html'];
        $contentHtml = preg_replace('/<img([^>]*)src=""/', '<img$1src="' . escapeHtml($webImg) . '"', $contentHtml, 1);

        $st = $db->prepare('INSERT INTO used_topics (title, keyword, used_at) VALUES (?, ?, datetime(\'now\'))');
        $st->execute([$post['title'], $picked['topic']]);

        // markdown version: make the image URL absolute (pasted elsewhere, e.g. Hashnode)
        $mdHtml = $contentHtml;
        if (defined('APP_BASE_URL') && APP_BASE_URL) {
            $mdHtml = str_replace('src="/', 'src="' . APP_BASE_URL . '/', $mdHtml);
        }

        return [
            'title' => $post['title'],
            'topic' => $picked['topic'],
            'angle' => $angle,
            'anchor' => $anchor,
            'html' => $contentHtml,
            'text' => $post['content_text'],
            'markdown' => BacklinkPublisher::htmlToMarkdown($mdHtml),
            'image_file' => $webImg,
            'image_url' => $imageUrl,
        ];
    }

    /**
     * Copy Studio → create a job row: either "Manual Ready" (queued for paste)
     * or straight "Manual Posted" with the live URL (starts link tracking).
     */
    public static function saveCopyJob(array $target, array $pkg, $publishedUrl = '') {
        $db = getDB();
        $publishedUrl = trim((string)$publishedUrl);
        $status = $publishedUrl ? 'Manual Posted' : 'Manual Ready';
        $st = $db->prepare('INSERT INTO jobs (target_id, run_date, angle, topic, title, anchor_text, content_html, content_text, image_url, image_file, status, published_url, posted_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))');
        $st->execute([
            $target['id'], date('Y-m-d'), $pkg['angle'] ?? '', $pkg['topic'] ?? '', $pkg['title'], $pkg['anchor'] ?? '',
            $pkg['html'], $pkg['text'] ?? '', $pkg['image_url'] ?? '', $pkg['image_file'] ?? '',
            $status, $publishedUrl, $publishedUrl ? nowString() : null,
        ]);
        $jobId = (int)$db->lastInsertId();
        if ($publishedUrl) {
            $db->prepare("UPDATE targets SET last_posted_at = datetime('now'), post_count = post_count + 1 WHERE id = ?")->execute([$target['id']]);
            $check = LinkVerifier::verify($publishedUrl, (string)getSettings()['main_site_url']);
            $db->prepare("UPDATE jobs SET is_dofollow = ?, last_verified_at = datetime('now') WHERE id = ?")->execute([$check['is_dofollow'] === null ? null : (int)$check['is_dofollow'], $jobId]);
        }
        addRunLog($publishedUrl
            ? "Copy Studio: post marked posted for {$target['name']} → $publishedUrl"
            : "Copy Studio: package queued for {$target['name']} (waiting for your paste)");
        return ['job_id' => $jobId, 'status' => $status];
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