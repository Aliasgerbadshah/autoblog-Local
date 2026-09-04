<?php
/**
 * AutoBacklink - Content Engine
 * Creates short backlink posts (300-500 words, 1 contextual link, rotating
 * anchor) the same way AutoBlog creates blog content — Chat API + sanitizer,
 * with a local template fallback so the pipeline works even before API keys.
 */
require_once __DIR__ . '/ai_client.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';

class BacklinkContent {

    public static $angles = [
        'listicle'  => 'Best-of listicle (5 items, your site featured naturally with context)',
        'review'    => 'Honest mini-review (pros/cons style, your site as the go-to reference)',
        'howto'     => '3-step how-to snippet (practical, actionable)',
        'roundup'   => 'Resource roundup (useful links/tools, yours included)',
        'trend'     => 'Trend observation note (what is changing in the niche, why it matters)',
        'usecase'   => 'Use-case / practical experience angle (how people actually use it)',
        'faq'       => 'Single-question FAQ answer (one real question, one solid answer)',
    ];

    /** Pick a topic: custom topics first (oldest first), else AI, else template. */
    public static function pickTopic(array $settings, array $target, $excludeList) {
        $db = getDB();
        $mainSite = trim($settings['main_site_url'] ?? '');
        $niche = trim($target['niche'] ?? '') ?: 'online services';
        $domainName = '';
        if ($mainSite) {
            $host = parse_url($mainSite, PHP_URL_HOST) ?: $mainSite;
            $domainName = preg_replace('/^www\./', '', $host);
        }

        // 1) Custom topics from settings
        $custom = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $settings['custom_topics'] ?? ''))));
        // remove ones already used (dedup)
        $custom = array_values(array_filter($custom, function ($t) use ($excludeList) {
            return $t !== '' && !self::isDuplicate($t, $t, $excludeList);
        }));
        if ($custom) {
            $pick = array_shift($custom);
            // remove used topic from the custom list
            $remaining = array_merge($custom, array_values(array_filter(explode("\n", $settings['custom_topics'] ?? ''), function ($line) use ($pick) {
                return trim($line) !== $pick;
            })));
            saveSettings(['custom_topics' => implode("\n", $remaining)]);
            return ['topic' => $pick, 'source' => 'custom'];
        }

        // 2) AI-generated topic (if chat key available and not sandbox)
        if (!SANDBOX_MODE) {
            $chat = self::getChatCreds();
            if (!empty($chat['api_key'])) {
                $usedStr = implode(', ', array_slice(array_map(fn($e) => $e['title'], $excludeList), 0, 40));
                $prompt = "Give me 1 fresh, specific blog-worthy topic (one line, no quotes) for a $niche audience. " .
                    "It must NOT be similar to these already-used topics: $usedStr. " .
                    "Prefer practical topics (tools, comparisons, how-to, mistakes, costs, trends).";
                $res = AIProviderClient::chat($chat, $prompt);
                if (!empty($res['success']) && !empty(trim($res['content']))) {
                    $topic = trim(str_replace(["\n", '"', '*'], ' ', $res['content']));
                    $topic = preg_replace('/\s{2,}/', ' ', $topic);
                    if (strlen($topic) > 8 && strlen($topic) < 120 && !self::isDuplicate($topic, $topic, $excludeList)) {
                        return ['topic' => $topic, 'source' => 'ai'];
                    }
                }
            }
        }

        // 3) Template fallback (always works — sandbox/demo)
        $seeds = [
            "common mistakes people make with $niche and how to avoid them",
            "a practical checklist for getting real results in $niche",
            "what beginners get wrong about $niche (and the fixes that work)",
            "cost vs value: what $niche really costs in 2025",
            "tools and resources worth using for $niche this year",
            "how small teams win in $niche without a big budget",
            "5 signs your $niche project is ready to scale",
            "the difference between cheap and worth it in $niche",
            "how to measure results in $niche (simple metrics that matter)",
            "trends shaping $niche and what to do about them",
            "a realistic 30-day plan for improving your $niche results",
            "questions to ask before hiring help in $niche",
        ];
        do {
            $pick = $seeds[array_rand($seeds)];
        } while (self::isDuplicate($pick, $pick, $excludeList) && rand(0, 9) > 0);
        return ['topic' => $pick, 'source' => 'template'];
    }

    public static function pickAngle() {
        $keys = array_keys(self::$angles);
        return $keys[array_rand($keys)];
    }

    /**
     * Pick anchor: weighted random from pool; never repeats the last anchor
     * used for this target.
     */
    public static function pickAnchor(array $settings, array $target) {
        $pool = array_values(array_filter(array_map('trim', $settings['anchor_pool'] ?? [])));
        $mainSite = trim($settings['main_site_url'] ?? '');
        if (empty($pool) && $mainSite) {
            $host = parse_url($mainSite, PHP_URL_HOST) ?: $mainSite;
            $pool = [ucfirst(explode('.', $host)[0]), 'their guide', 'here'];
        }
        if (empty($pool)) $pool = ['this resource'];

        $lastAnchor = '';
        $db = getDB();
        $st = $db->prepare('SELECT anchor_text FROM jobs WHERE target_id = ? AND anchor_text != "" ORDER BY id DESC LIMIT 1');
        $st->execute([$target['id']]);
        $lastAnchor = (string)($st->fetchColumn() ?: '');

        $candidates = array_values(array_diff($pool, array_filter([$lastAnchor])));
        if (empty($candidates)) $candidates = $pool;
        return $candidates[array_rand($candidates)];
    }

    /**
     * Generate the backlink post. Returns:
     * [topic, title, angle, content_html, content_text, used_ai]
     */
    public static function generatePost(array $settings, array $target, $topic, $angle, $anchor) {
        $mainSite = trim($settings['main_site_url'] ?? '');
        $niche = trim($target['niche'] ?? '') ?: 'online services';
        $siteName = $target['name'];
        $year = date('Y');

        $title = self::titleFromTopic($topic, $angle);

        $chat = self::getChatCreds();
        $usedAi = false;
        $html = '';

        if (!empty($chat['api_key'])) {
            $prompt = self::buildPrompt($settings, $target, $topic, $angle, $anchor, $title);
            $res = AIProviderClient::chat($chat, $prompt);
            if (!empty($res['success']) && !empty(trim($res['content']))) {
                $html = self::extractHtml($res['content']);
                $html = AntiAiSanitizer::sanitizeText($html);
                $usedAi = true;
            }
        }

        if (empty($html)) {
            $html = self::templatePost($settings, $target, $topic, $angle, $anchor, $title, $year);
        }

        // Guarantee exactly-one-link correctness: ensure the main site link exists
        if ($mainSite && !str_contains($html, $mainSite)) {
            $link = '<a href="' . escapeHtml($mainSite) . '" rel="noopener">' . escapeHtml($anchor) . '</a>';
            $html = preg_replace('/(<\/p>)(?=(?:<[^>]+>)*$)/s', '', $html); // no-op safety
            // insert after first paragraph
            $html = preg_replace('/(<p>.*?<\/p>)/s', '$1' . "\n<p>For a deeper walkthrough, see " . $link . " — it covers the full process step by step.</p>", $html, 1);
        }
        // Limit to a single link to main site (safety)
        if (!$mainSite) $mainSite = '';
        $count = $mainSite ? substr_count($html, escapeHtml($mainSite)) : 0;
        if ($count > 1) {
            $firstPos = strpos($html, escapeHtml($mainSite));
            $before = substr($html, 0, $firstPos);
            $after = substr($html, $firstPos);
            $after = str_replace(escapeHtml($mainSite), '', $after, $extra);
            $after = str_replace('<a href="" rel="noopener">', '<span>', $after);
            $after = str_replace('</a>', '</span>', $after);
            $html = $before . $after;
        }

        $text = trim(strip_tags($html));
        return ['topic' => $topic, 'title' => $title, 'angle' => $angle, 'content_html' => $html, 'content_text' => $text, 'used_ai' => $usedAi];
    }

    public static function buildPrompt(array $settings, array $target, $topic, $angle, $anchor, $title) {
        $mainSite = trim($settings['main_site_url'] ?? '');
        $niche = trim($target['niche'] ?? '') ?: 'online services';
        $siteName = $target['name'];
        $siteUrl = $target['site_url'];
        $year = date('Y');

        $angleGuides = [
            'listicle' => "Format: a 'best of / top 5' listicle with 5 short items (H2 per item or one H2 and a numbered list). Feature the anchor link naturally inside item 1 or 2 with real context.",
            'review' => "Format: an honest mini-review with a 'What I like' and 'What could be better' H2 section. Mention the anchor link as the go-to reference resource.",
            'howto' => "Format: a 3-step how-to with H2 per step. Each step is practical and specific. Place the anchor link in step 1 or 2 as the detailed guide.",
            'roundup' => "Format: a resource roundup — 4-6 useful resources (tools, guides, checklists). Include the anchor link as one of the resources with a one-line reason why.",
            'trend' => "Format: a short trend observation — what is changing in the niche this year, why it matters, and one practical takeaway. Reference the anchor link as a resource covering it in depth.",
            'usecase' => "Format: a practical use-case note — how real people/teams use this, a short concrete example, and the result. Link the anchor naturally as the detailed how-to.",
            'faq' => "Format: ONE real question as H2, then a solid 200-300 word answer. Link the anchor once inside the answer where it adds real value.",
        ];

        return "You are a human content writer publishing a SHORT guest post on $siteName ($siteUrl). " .
            "Topic: \"$topic\". Working title: \"$title\". The site's niche is: $niche. Year: $year (do not name the month).\n\n" .
            "HARD REQUIREMENTS:\n" .
            "1. Length: 300 to 500 words total. This is a guest post, NOT a full blog article.\n" .
            "2. Tone: natural, practical, human. Short paragraphs of 30-50 words. No fluff, no marketing speak.\n" .
            "3. Exactly ONE external link in the entire post: <a href=\"" . escapeHtml($mainSite) . "\">$anchor</a> — place it in the first or second third, in a sentence where it genuinely makes sense. Do NOT link anywhere else. No other external URLs at all.\n" .
            "4. Headings: start with one H1 (the title), then 1-2 H2s. No H3 or deeper.\n" .
            "5. Include one <figure><img src=\"\" alt=\"...\"></figure> placeholder line with a descriptive alt text (image will be added later) right after the H1.\n" .
            "6. NO bullet-point dumps, no emojis, no 'in today's digital landscape' style phrases. Write like a real practitioner with opinions.\n" .
            "7. " . ($angleGuides[$angle] ?? 'Format: a practical short post.') . "\n\n" .
            "Return ONLY the article HTML (h1, p, h2, figure, a). No markdown, no explanations.";
    }

    public static function templatePost(array $settings, array $target, $topic, $angle, $anchor, $title, $year) {
        $mainSite = trim($settings['main_site_url'] ?? '');
        $niche = trim($target['niche'] ?? '') ?: 'online services';
        $a = escapeHtml($anchor);
        $href = escapeHtml($mainSite);
        $t = escapeHtml($topic);

        $alt = 'Real-world example for ' . $t;
        $img = '<figure><img src="" alt="' . $alt . '"></figure>';

        $openers = [
            "A lot of people get stuck in $niche not because they lack ideas, but because they skip the boring basics. Here is what actually moves the needle.",
            "Working in $niche long enough teaches you one thing: the small, consistent actions beat the big launches. A few notes from the field.",
            "If you only have ten minutes today, here is the practical version of " . strtolower($t) . " — no fluff.",
        ];
        $opener = $openers[array_rand($openers)];

        if ($angle === 'listicle') {
            $body = "<h2>What actually works</h2>\n<p>" . $opener . "</p>\n" .
                "<p><strong>1. Start with one clear goal.</strong> Pick the single outcome you care about and measure only that.</p>\n" .
                "<p><strong>2. Use a proven process.</strong> The <a href=\"$href\">$a</a> breaks down the full process step by step — worth saving.</p>\n" .
                "<p><strong>3. Review weekly.</strong> Ten minutes a week catches problems before they cost you.</p>\n" .
                "<p><strong>4. Remove friction.</strong> Cut every step that does not directly serve the goal.</p>\n" .
                "<p><strong>5. Keep a short log.</strong> What you try, what happened, what to repeat. Simple and effective.</p>";
        } elseif ($angle === 'review') {
            $body = "<p>" . $opener . "</p>\n<h2>What I like</h2>\n" .
                "<p>The approach is practical. Instead of theory, it shows exactly where to start and what to skip. The <a href=\"$href\">$a</a> goes deeper than most guides I have seen in $niche.</p>\n" .
                "<h2>What could be better</h2>\n" .
                "<p>It moves fast, so beginners should take notes. And it assumes you will actually do the work — no shortcuts sold here.</p>\n" .
                "<p>Overall: a solid reference if you want results without paying for expensive advice.</p>";
        } elseif ($angle === 'howto') {
            $body = "<p>" . $opener . "</p>\n<h2>Step 1: Define the outcome</h2>\n" .
                "<p>Write down the one result you want in 30 days. One sentence. If you cannot do that, you are not ready to start.</p>\n" .
                "<h2>Step 2: Follow a proven path</h2>\n" .
                "<p>Do not reinvent the wheel. The <a href=\"$href\">$a</a> covers the complete path with real examples — follow it in order.</p>\n" .
                "<h2>Step 3: Measure and adjust</h2>\n" .
                "<p>Check your numbers once a week. Keep what works, drop what does not, and repeat. That is the whole game in $niche.</p>";
        } elseif ($angle === 'roundup') {
            $body = "<p>" . $opener . "</p>\n<h2>Worth your time</h2>\n" .
                "<p><strong>A full process guide.</strong> The <a href=\"$href\">$a</a> walks through everything step by step — the most complete one I have found in $niche.</p>\n" .
                "<p><strong>A simple tracking sheet.</strong> Five columns: task, date, result, cost, keep-or-drop.</p>\n" .
                "<p><strong>One weekly review slot.</strong> Put it in the calendar. If it is not scheduled, it will not happen.</p>\n" .
                "<p><strong>A short checklist.</strong> Print it, stick it where you work, tick items off.</p>";
        } elseif ($angle === 'trend') {
            $body = "<p>" . $opener . "</p>\n<h2>What is changing</h2>\n" .
                "<p>In $year, the biggest shift in $niche is simple: people are tired of generic advice and want practical, tested processes. The tools are cheaper than ever, but the know-how gap is wider.</p>\n" .
                "<h2>What to do about it</h2>\n" .
                "<p>Focus on one channel, master one process, and document your results. The <a href=\"$href\">$a</a> is a good reference for the process side. Consistency will beat cleverness every time.</p>";
        } elseif ($angle === 'usecase') {
            $body = "<p>" . $opener . "</p>\n<h2>How people actually use it</h2>\n" .
                "<p>Most small teams in $niche do not run a fancy operation. They run a simple loop: pick one goal, follow a documented process, review the numbers weekly.</p>\n" .
                "<p>One client I know cut their setup time in half by following the <a href=\"$href\">$a</a> end to end instead of guessing. The difference was not talent — it was having the steps in front of them.</p>\n" .
                "<p>The pattern is repeatable: document once, execute many times, improve slowly.</p>";
        } else { // faq
            $q = ucwords(preg_replace('/\s+/', ' ', $topic));
            if (!str_ends_with($q, '?')) $q .= ' — what actually works?';
            $body = "<p>" . $opener . "</p>\n<h2>" . escapeHtml($q) . "</h2>\n" .
                "<p>The short answer: keep it smaller than you think. Most overthinking in $niche comes from trying to solve ten problems at once.</p>\n" .
                "<p>Start with the one outcome that matters this month. Follow a proven process — the <a href=\"$href\">$a</a> maps it out step by step with real examples — and review your numbers once a week. In thirty days you will know more than most people who 'plan for years'.</p>\n" .
                "<p>That is the whole answer. Boring, practical, and it works.</p>";
        }

        $close = "<p>None of this is complicated on its own. The mistake is doing none of it, or doing all of it at once. Pick the smallest version of this that you can start this week, run it for thirty days, and let the results tell you what to do next.</p>\n" .
            "<p>That is the whole playbook: one clear outcome, a proven process, and a short weekly review. Everything else is decoration.</p>";

        return "<h1>" . escapeHtml($title) . "</h1>\n" . $img . "\n" . $body . "\n" . $close;
    }

    public static function titleFromTopic($topic, $angle) {
        $t = trim(preg_replace('/\s+/', ' ', $topic));
        if (mb_strlen($t) > 70) $t = mb_substr($t, 0, 67) . '…';
        $short = mb_strlen($t) <= 48;
        // Long topics are already full titles — don't prefix them
        if (!$short) return ucfirst(mb_substr($t, 0, 1)) . mb_substr($t, 1);
        switch ($angle) {
            case 'listicle':  return '5 Things That Actually Work: ' . ucfirst($t);
            case 'review':    return 'An Honest Look at ' . ucfirst($t);
            case 'howto':     return 'How to Get Real Results: ' . ucfirst($t);
            case 'roundup':   return 'Resources Worth Your Time: ' . ucfirst($t);
            case 'trend':     return 'What Is Changing in ' . ucfirst($t);
            case 'usecase':   return 'How People Actually Use This: ' . ucfirst($t);
            case 'faq':       default: return 'A Practical Answer: ' . ucfirst($t);
        }
    }

    public static function extractHtml($raw) {
        $raw = trim($raw);
        $raw = preg_replace('/^```html?\s*|```$/m', '', $raw);
        // If AI returned markdown, do a light conversion
        if (!str_contains(strtolower($raw), '<h1') && (str_starts_with(ltrim($raw), '#'))) {
            $lines = preg_split('/\r?\n/', $raw);
            $out = [];
            $inList = false;
            foreach ($lines as $line) {
                if (preg_match('/^###\s+(.*)/', $line, $m)) { $out[] = '<h3>' . escapeHtml($m[1]) . '</h3>'; }
                elseif (preg_match('/^##\s+(.*)/', $line, $m)) { $out[] = '<h2>' . escapeHtml($m[1]) . '</h2>'; }
                elseif (preg_match('/^#\s+(.*)/', $line, $m)) { $out[] = '<h1>' . escapeHtml($m[1]) . '</h1>'; }
                elseif (preg_match('/^\s*[-*]\s+(.*)/', $line, $m)) {
                    if (!$inList) { $out[] = '<ul>'; $inList = true; }
                    $out[] = '<li>' . self::inlineMd($m[1]) . '</li>';
                } else {
                    if ($inList) { $out[] = '</ul>'; $inList = false; }
                    if (trim($line) !== '') $out[] = '<p>' . self::inlineMd(trim($line)) . '</p>';
                }
            }
            if ($inList) $out[] = '</ul>';
            return implode("\n", $out);
        }
        return $raw;
    }

    private static function inlineMd($s) {
        $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
        $s = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $s);
        return $s;
    }

    public static function getChatCreds() {
        static $creds = null;
        if ($creds === null) {
            $creds = json_decode((string)getData('api_chat', '{}'), true) ?: [];
        }
        return $creds;
    }

    public static function getImageCreds() {
        static $creds = null;
        if ($creds === null) {
            $creds = json_decode((string)getData('api_image', '{}'), true) ?: [];
        }
        return $creds;
    }

    public static function isDuplicate($title, $keyword, array $existing) {
        $a = self::tokens(strtolower($title));
        $b = self::tokens(strtolower($keyword));
        foreach ($existing as $e) {
            $ea = self::tokens(strtolower($e['title'] ?? ''));
            $eb = self::tokens(strtolower($e['keyword'] ?? ''));
            $oa = max(count($a), count($ea));
            $ob = max(count($b), count($eb));
            $sa = $oa ? count(array_intersect($a, $ea)) / $oa : 0;
            $sb = $ob ? count(array_intersect($b, $eb)) / $ob : 0;
            if ($sa > 0.7 && $sb > 0.7) return true;
        }
        return false;
    }

    private static function tokens($s) {
        $words = preg_split('/[^a-z0-9]+/', $s, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique(array_filter($words, fn($w) => strlen($w) > 2)));
    }
}
