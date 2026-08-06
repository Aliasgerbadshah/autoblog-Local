<?php
/**
 * AutoBlog SaaS - Content Generator, Publisher, Traffic Amplifier
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/anti_ai_sanitizer.php';
require_once __DIR__ . '/research_agent.php';
require_once __DIR__ . '/ai_provider.php';

class BloggerOAuthHelper {
    public static function refreshAccessToken($clientId, $clientSecret, $refreshToken) {
        $payload = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
        ];
        $result = curlPostForm('https://oauth2.googleapis.com/token', $payload, [], 10);
        if ($result['success'] && $result['http_code'] === 200) {
            return ['success' => true, 'access_token' => $result['data']['access_token'] ?? ''];
        }
        return ['success' => false, 'error' => "Token Refresh Error ({$result['http_code']}): " . ($result['raw'] ?? 'Unknown')];
    }
}

class TrafficAmplifier {
    public static function generateGoogleFaqSchema($q1, $a1, $q2, $a2) {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => [
                ['@type' => 'Question', 'name' => $q1, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a1]],
                ['@type' => 'Question', 'name' => $q2, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a2]]
            ]
        ];
        return '<script type="application/ld+json">' . json_encode($schema) . '</script>';
    }

    public static function getYoutubeEmbedCode($topicKeyword) {
        $videoIds = ["L_LUpnjgPso", "a2JmI3AAnA8", "3yB4Wp_Bsh8", "k8N00Lp1QYg"];
        $chosenId = $videoIds[array_rand($videoIds)];
        $escapedKeyword = escapeHtml(ucwords($topicKeyword));
        return <<<HTML
<div class="video-embed-card" style="margin:32px 0; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0; background:#fafafa;">
    <div style="background:#0f172a; color:#ffffff; padding:10px 18px; font-size:0.85rem; font-weight:700;">📺 Video Walkthrough: {$escapedKeyword} Analysis</div>
    <div style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden;">
        <iframe src="https://www.youtube.com/embed/{$chosenId}" style="position:absolute; top:0; left:0; width:100%; height:100%; border:0;" allowfullscreen></iframe>
    </div>
</div>
HTML;
    }
}

class ContentGenerator {
    private static $HUMAN_IMAGE_GALLERY = [
        "https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1200&q=80",
        "https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=1200&q=80",
        "https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=1200&q=80",
        "https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1200&q=80",
        "https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?auto=format&fit=crop&w=1200&q=80",
        "https://images.unsplash.com/photo-1499750310107-5fef28a66643?auto=format&fit=crop&w=1200&q=80"
    ];

    public static function slugify($text) {
        return slugify($text);
    }

    public static function fetchUserPreviousInternalLinks($userId) {
        $db = getDB();
        $stmt = $db->prepare('SELECT title, published_url FROM posts WHERE user_id = ? ORDER BY id DESC LIMIT 3');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function generateHumanArticle1000Words($keyword, $category = 'General', $targetLink = null, $targetAnchor = null, $userId = 1, $slotNumber = 1, $enableYoutube = true, $enableFaqSchema = true) {
        $keywordCap = ucwords($keyword);
        $nowYear = date('Y');
        $title = "How to Master $keywordCap: Practical Strategies for $nowYear";
        $slug = slugify($title);

        $numImages = [2, 3, 4][array_rand([2, 3, 4])];
        $imageKeys = array_rand(self::$HUMAN_IMAGE_GALLERY, $numImages);
        if (!is_array($imageKeys)) $imageKeys = [$imageKeys];
        $images = [];
        foreach ($imageKeys as $k) $images[] = self::$HUMAN_IMAGE_GALLERY[$k];

        // Crawl subpages
        $crawledSubpages = [];
        if ($targetLink) {
            $crawledSubpages = ResearchAgent::crawlAndExtractSitePages($targetLink, $userId);
        }

        $subLink1 = $crawledSubpages[0]['page_url'] ?? ($targetLink ?? '#');
        $subAnchor1 = $crawledSubpages[0]['page_title'] ?? ($targetAnchor ?? 'our primary collection page');
        $subLink2 = $crawledSubpages[1]['page_url'] ?? $subLink1;
        $subAnchor2 = $crawledSubpages[1]['page_title'] ?? 'our seasonal catalog';
        $subLink3 = $crawledSubpages[2]['page_url'] ?? $subLink1;
        $subAnchor3 = $crawledSubpages[2]['page_title'] ?? 'our featured product selections';

        $previousPosts = self::fetchUserPreviousInternalLinks($userId);
        $prevPostUrl = $previousPosts[0]['published_url'] ?? '#';
        $prevPostTitle = $previousPosts[0]['title'] ?? 'our foundational strategy overview';

        $q1 = "What is the single best approach to $keywordCap?";
        $a1 = "Start with baseline metrics, focus on high-intent targets, and maintain a consistent publication schedule without skipping periodic performance audits.";
        $q2 = "How long does it take to see measurable search lift?";
        $a2 = "Most domains register noticeable organic impression improvements between 3 to 6 weeks following consistent publishing and multi-channel distribution.";

        $faqSchemaHtml = $enableFaqSchema ? TrafficAmplifier::generateGoogleFaqSchema($q1, $a1, $q2, $a2) : "";
        $youtubeHtml = $enableYoutube ? TrafficAmplifier::getYoutubeEmbedCode($keyword) : "";

        $dateStr = date('F d, Y');
        $prevPostTitleEsc = escapeHtml($prevPostTitle);

        $imageHtml1 = "<figure style=\"margin-bottom:32px; border-radius:12px; overflow:hidden;\"><img src=\"{$images[0]}\" alt=\"$keywordCap\" style=\"width:100%; height:auto; display:block; object-fit:cover; max-height:420px;\"></figure>";
        $imageHtml2 = $numImages >= 2 ? "<figure style=\"margin:36px 0; border-radius:12px; overflow:hidden;\"><img src=\"{$images[1]}\" alt=\"Execution Framework\" style=\"width:100%; height:auto; display:block; object-fit:cover; max-height:380px;\"></figure>" : '';
        $imageHtml3 = $numImages >= 3 ? "<figure style=\"margin:36px 0; border-radius:12px; overflow:hidden;\"><img src=\"{$images[2]}\" alt=\"Metrics Data\" style=\"width:100%; height:auto; display:block; object-fit:cover; max-height:360px;\"></figure>" : '';

        $rawArticle = <<<ARTICLE
<article class="monochrome-editorial-article" style="font-family:'Montserrat', -apple-system, sans-serif; line-height:1.85; color:#334155; max-width:840px; margin:0 auto; font-size:1.02rem;">
    $faqSchemaHtml
    
    <header style="margin-bottom:28px;">
        <h1 style="font-size:2.5rem; font-weight:800; color:#0f172a; margin-bottom:12px; line-height:1.2; letter-spacing:-0.02em;">$title</h1>
        <p style="font-size:1.1rem; color:#475569; font-weight:500; margin-bottom:20px; line-height:1.6;">Learn key operational strategies for $keywordCap ($nowYear update). Understand core frameworks, compare performance data, and implement best practices.</p>
        <div style="display:flex; align-items:center; justify-content:space-between; border-top:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0; padding:12px 0; font-size:0.85rem; color:#64748b; font-weight:600; flex-wrap:wrap; gap:12px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="background:#0f172a; color:#ffffff; font-weight:800; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.85rem;">ED</div>
                <span><strong>Editorial Desk</strong> &bull; Senior Analyst</span>
            </div>
            <div><span>📅 Last Updated: $dateStr</span></div>
        </div>
    </header>

    <nav class="table-of-contents-box" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:22px 26px; margin-bottom:32px;">
        <h3 style="font-size:0.95rem; font-weight:800; color:#0f172a; margin-bottom:12px; text-transform:uppercase; letter-spacing:0.04em;">Table of Contents</h3>
        <ul style="margin:0; padding-left:20px; color:#475569; font-weight:600; line-height:2; font-size:0.92rem; list-style-type:square;">
            <li><a href="#section-1" style="color:#0f172a; text-decoration:underline;">1. Structural Roadblocks & Common Mistakes</a></li>
            <li><a href="#section-2" style="color:#0f172a; text-decoration:underline;">2. The 3-Phase Operational Execution Blueprint</a></li>
            <li><a href="#section-3" style="color:#0f172a; text-decoration:underline;">3. Video Walkthrough & Dwell-Time Optimization</a></li>
            <li><a href="#section-4" style="color:#0f172a; text-decoration:underline;">4. Real-World Case Benchmarks & Data Table</a></li>
            <li><a href="#section-5" style="color:#0f172a; text-decoration:underline;">5. Protocol Checklist & Final Take</a></li>
            <li><a href="#faq-section" style="color:#0f172a; text-decoration:underline;">6. Frequently Asked Questions</a></li>
        </ul>
    </nav>

    $imageHtml1

    <section class="intro-block">
        <p style="margin-bottom:18px;">Setting up an effective, scalable workflow for <strong>$keywordCap</strong> isn't about chasing temporary trends or relying on high-level buzzwords. It comes down to knowing what delivers verifiable results in practical environments. Similar to how developers reference <a href="https://platform.openai.com/docs" target="_blank" rel="nofollow noopener" style="color:#0f172a; font-weight:700; text-decoration:underline;">OpenAI's API documentation</a> or <a href="https://www.w3.org/WAI/standards-guidelines/" target="_blank" rel="nofollow noopener" style="color:#0f172a; font-weight:700; text-decoration:underline;">W3C global accessibility standards</a>, setting up long-term search performance requires applying documented industry principles.</p>
        <p style="margin-bottom:18px;">Last quarter, our research group conducted a 90-day benchmark test analyzing real campaigns across mid-size brands. When reviewing choices inside <a href="$subLink1" target="_blank" style="color:#0f172a; font-weight:700; text-decoration:underline;">$subAnchor1</a>, structuring page options logically creates an immediate reduction in bounce rate while helping readers find exact answers faster.</p>
        <p style="margin-bottom:18px;">Many online tutorials focus heavily on abstract theory while skipping operational implementation. As detailed in our recent analysis of <a href="$prevPostUrl" style="color:#0f172a; font-weight:700; text-decoration:underline;">$prevPostTitleEsc</a>, building a resilient pipeline requires focusing on repeatable routines rather than one-off hacks.</p>
        <p style="margin-bottom:18px;">Before diving into specific technical steps, let's establish why consistency is the foundational element that dictates long-term success. Search algorithms evaluate domain stability and steady signals over erratic bursts of activity.</p>
    </section>

    <section id="section-1" style="margin-top:40px; padding-top:10px;">
        <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin-bottom:16px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">1. Structural Roadblocks & Common Mistakes</h2>
        <p style="margin-bottom:18px;">The most frequent breakdown occurs when teams attempt to scale output before solidifying their foundational pipeline. When reviewing options inside <a href="$subLink2" target="_blank" style="color:#0f172a; font-weight:700; text-decoration:underline;">$subAnchor2</a>, organizing products and guides into clear categories prevents user fatigue.</p>
        <p style="margin-bottom:18px;">During our field audit of 22 brand operations, three recurring bottlenecks accounted for nearly 80% of stalled growth:</p>
        <ul style="margin:16px 0; padding-left:22px; line-height:2;">
            <li style="margin-bottom:8px;"><strong>Fragmented Distribution Schedules:</strong> Publishing erratically confuses search crawlers and disrupts reader habits.</li>
            <li style="margin-bottom:8px;"><strong>Isolated Sub-Pages:</strong> When new articles exist in isolation without linking back to core collection hubs, readers hit dead ends.</li>
            <li style="margin-bottom:8px;"><strong>Generic Automated Repetitions:</strong> Ensuring natural sentence variation, concrete numbers, and direct conversational tone is vital.</li>
        </ul>
        <p style="margin-bottom:18px;">According to search indexation benchmark studies documented on <a href="https://en.wikipedia.org/wiki/Search_engine_optimization" target="_blank" rel="nofollow noopener" style="color:#0f172a; font-weight:700; text-decoration:underline;">Wikipedia's SEO Architecture Guide</a>, domains that maintain clean internal cross-linking enjoy up to 2.8x faster crawling speed for newly added sub-pages.</p>
    </section>

    $imageHtml2

    <section id="section-2" style="padding-top:10px;">
        <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin-bottom:16px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">2. The 3-Phase Operational Execution Blueprint</h2>
        <p style="margin-bottom:18px;">To achieve consistent, predictable momentum with $keyword, implement a structured three-phase pipeline:</p>
        <h3 style="font-size:1.2rem; font-weight:700; color:#0f172a; margin:20px 0 10px 0;">Phase A: Intent Discovery & Audience Mapping</h3>
        <p style="margin-bottom:16px;">Begin by identifying long-tail search terms where buyer intent is unmistakable. For additional category examples, browse <a href="$subLink3" target="_blank" style="color:#0f172a; font-weight:700; text-decoration:underline;">$subAnchor3</a> to see how logical segmentation satisfies user intent.</p>
        <h3 style="font-size:1.2rem; font-weight:700; color:#0f172a; margin:20px 0 10px 0;">Phase B: High-Depth Content Production</h3>
        <p style="margin-bottom:16px;">Format content for high skimmability. Incorporate clear sub-headings, key summary boxes, data tables, and high-resolution visual breaks.</p>
        <h3 style="font-size:1.2rem; font-weight:700; color:#0f172a; margin:20px 0 10px 0;">Phase C: Autonomous Multi-Channel Distribution</h3>
        <p style="margin-bottom:16px;">Once published on your main blog, immediately syndicate summaries across Web 2.0 properties equipped with canonical tags pointing back to your primary URL.</p>
    </section>

    <section id="section-3" style="margin-top:36px; padding-top:10px;">
        <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin-bottom:16px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">3. Video Walkthrough & Dwell-Time Optimization</h2>
        <p style="margin-bottom:18px;">Search engines evaluate user interaction signals to validate ranking positions. Incorporating dynamic elements like embedded video walkthroughs keeps visitors engaged significantly longer.</p>
        $youtubeHtml
    </section>

    $imageHtml3

    <section id="section-4" style="padding-top:10px;">
        <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin-bottom:16px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">4. Real-World Case Benchmarks & Data Table</h2>
        <p style="margin-bottom:18px;">To measure the tangible impact of this approach, review the 90-day comparison matrix below:</p>
        <div style="overflow-x:auto; margin:20px 0;">
            <table style="width:100%; border-collapse:collapse; background:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #e2e8f0; font-size:0.9rem;">
                <thead><tr style="background:#0f172a; color:#ffffff; text-align:left;"><th style="padding:12px 16px; font-weight:700;">Performance Metric</th><th style="padding:12px 16px; font-weight:700;">Manual / Legacy Approach</th><th style="padding:12px 16px; font-weight:700; color:#38bdf8;">Automated Engine</th></tr></thead>
                <tbody>
                    <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:12px 16px; font-weight:600; color:#0f172a;">Weekly Output Volume</td><td style="padding:12px 16px; color:#64748b;">1 Post / Week</td><td style="padding:12px 16px; font-weight:700; color:#10b981;">7 to 14 Posts / Week</td></tr>
                    <tr style="border-bottom:1px solid #e2e8f0; background:#f8fafc;"><td style="padding:12px 16px; font-weight:600; color:#0f172a;">Average Dwell Time</td><td style="padding:12px 16px; color:#64748b;">1 Min 12 Seconds</td><td style="padding:12px 16px; font-weight:700; color:#10b981;">3 Min 48 Seconds</td></tr>
                    <tr style="background:#ffffff;"><td style="padding:12px 16px; font-weight:600; color:#0f172a;">90-Day Organic Reach</td><td style="padding:12px 16px; color:#64748b;">+28% Baseline</td><td style="padding:12px 16px; font-weight:700; color:#10b981;">+184% Accelerated</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section id="section-5" style="margin-top:36px; padding-top:10px;">
        <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin-bottom:16px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">5. Implementation Checklist & Final Take</h2>
        <p style="margin-bottom:14px; font-weight:600;">Before launching your next publication campaign, verify these core checkpoints:</p>
        <ul style="margin:0; padding-left:20px; line-height:2; color:#475569;">
            <li>☑️ Target query intent matches active buyer decision stages.</li>
            <li>☑️ Embedded high-resolution visual breaks with topic captions.</li>
            <li>☑️ Weaved natural in-text links pointing to internal collection sub-pages.</li>
            <li>☑️ Configured structured JSON-LD FAQ schema for Google snippet inclusion.</li>
        </ul>
    </section>

    <section id="faq-section" style="background:#f8fafc; padding:28px; border-radius:12px; border:1px solid #e2e8f0; margin-top:40px;">
        <h2 style="font-size:1.4rem; font-weight:800; color:#0f172a; margin-bottom:20px;">6. Frequently Asked Questions</h2>
        <div style="margin-bottom:18px;"><h4 style="font-size:1rem; font-weight:700; color:#0f172a; margin-bottom:4px;">$q1</h4><p style="margin:0; color:#475569; font-size:0.95rem;">$a1</p></div>
        <div><h4 style="font-size:1rem; font-weight:700; color:#0f172a; margin-bottom:4px;">$q2</h4><p style="margin:0; color:#475569; font-size:0.95rem;">$a2</p></div>
    </section>

    <footer style="margin-top:40px; border-top:1px solid #e2e8f0; padding-top:24px; display:flex; align-items:center; gap:16px;">
        <div style="background:#0f172a; color:#fff; font-weight:800; width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.9rem;">ED</div>
        <div><h4 style="font-size:0.95rem; font-weight:800; color:#0f172a; margin:0 0 2px 0;">Editorial Desk &bull; Growth Operations</h4><p style="font-size:0.8rem; color:#64748b; margin:0;">Published via AutoBlog Autonomous Network.</p></div>
    </footer>
</article>
ARTICLE;

        $humanizedContent = AntiAiSanitizer::sanitizeText($rawArticle);

        return [
            'title' => $title,
            'slug' => $slug,
            'content' => trim($humanizedContent),
            'category' => $category,
            'keyword' => $keyword,
            'featured_image' => $images[0]
        ];
    }

    public static function generateProgrammaticPseoMatrix($seedNiche, $competitorsList = ['Option A', 'Option B', 'Option C']) {
        $matrixPosts = [];
        foreach ($competitorsList as $c) {
            $title = "$seedNiche vs $c: Complete Comparison Guide";
            $matrixPosts[] = [
                'title' => $title,
                'slug' => slugify($title),
                'keyword' => "$seedNiche vs $c"
            ];
        }
        return $matrixPosts;
    }
}

class Publisher {

    public static function publishLocal($userId, $title, $slug, $content, $category = 'General', $keyword = '', $featuredImage = '') {
        ensureDir(OUTPUT_DIR);
        $fileName = "$slug.html";
        $filePath = OUTPUT_DIR . '/' . $fileName;
        $nowYear = date('Y');

        $htmlTemplate = <<<HTML
<!DOCTYPE html>
<html lang="en" style="scroll-behavior: smooth;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$title</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        html { scroll-behavior: smooth; }
        body { font-family: 'Montserrat', sans-serif; line-height: 1.8; max-width: 880px; margin: 0 auto; padding: 36px 20px; color: #0f172a; background: #fafafa; }
        .nav-back { margin-bottom: 24px; display: inline-block; color: #0f172a; font-weight: 800; text-decoration: underline; font-size: 0.9rem; }
        article { background: #ffffff; padding: 48px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; }
        footer { margin-top: 48px; font-size: 0.85rem; text-align: center; color: #64748b; font-weight: 600; }
    </style>
</head>
<body>
    <a href="index.html" class="nav-back">&larr; Back to Main Blog Index</a>
    $content
    <footer>&copy; $nowYear AutoBlog Autonomous Magazine Network</footer>
</body>
</html>
HTML;

        file_put_contents($filePath, $htmlTemplate);

        $publishedUrl = "/published_posts/$fileName";
        $db = getDB();
        $now = nowString();
        $stmt = $db->prepare('INSERT INTO posts (user_id, title, slug, content, keyword_or_source, category, source_type, status, published_url, featured_image, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $title, $slug, $content, $keyword, $category, 'Local Magazine', 'Published', $publishedUrl, $featuredImage, $now]);

        self::rebuildLocalIndex();
        return $publishedUrl;
    }

    public static function publishBlogger($userId, $blogId, $apiKey, $title, $content) {
        if (empty($blogId) || empty($apiKey)) {
            return ['success' => false, 'error' => 'Missing Blogger Blog ID or API Key.'];
        }

        $url = "https://www.googleapis.com/blogger/v3/blogs/" . trim($blogId) . "/posts/?key=" . trim($apiKey);
        $payload = [
            'kind' => 'blogger#post',
            'blog' => ['id' => trim($blogId)],
            'title' => $title,
            'content' => $content
        ];

        $result = curlPost($url, $payload, [
            'Content-Type: application/json'
        ], 12);

        $data = $result['data'] ?? [];
        if ($result['success'] && in_array($result['http_code'], [200, 201])) {
            $bloggerUrl = $data['url'] ?? '';
            $db = getDB();
            $now = nowString();
            $stmt = $db->prepare('INSERT INTO posts (user_id, title, slug, content, keyword_or_source, category, source_type, status, published_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $title, slugify($title), $content, $blogId, 'Blogger Post', 'Blogger REST API', 'Published', $bloggerUrl, $now]);
            return ['success' => true, 'url' => $bloggerUrl, 'message' => 'Successfully published live to Google Blogger!'];
        }

        $errorMsg = $data['error']['message'] ?? ($result['raw'] ?? 'Unknown error');
        return ['success' => false, 'error' => "Blogger API Error ({$result['http_code']}): $errorMsg"];
    }

    public static function publishWordpress($userId, $wpSiteUrl, $username, $appPassword, $title, $content, $status = 'publish') {
        $apiUrl = rtrim($wpSiteUrl, '/') . '/wp-json/wp/v2/posts';
        $payload = ['title' => $title, 'content' => $content, 'status' => $status];

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_USERPWD => "$username:$appPassword",
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if (in_array($httpCode, [200, 201])) {
            $wpPostUrl = $data['link'] ?? '';
            $db = getDB();
            $now = nowString();
            $stmt = $db->prepare('INSERT INTO posts (user_id, title, slug, content, keyword_or_source, category, source_type, status, published_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $title, slugify($title), $content, $wpSiteUrl, 'WordPress Post', 'WordPress REST API', 'Published', $wpPostUrl, $now]);
            return ['success' => true, 'url' => $wpPostUrl];
        }

        return ['success' => false, 'error' => "WP REST API Error ($httpCode): $response"];
    }

    public static function publishWebhook($userId, $webhookUrl, $title, $content, $category = 'General') {
        $payload = ['title' => $title, 'content' => $content, 'category' => $category];
        $result = curlPost($webhookUrl, $payload, ['Content-Type: application/json'], 12);

        if ($result['success'] && in_array($result['http_code'], [200, 201])) {
            $db = getDB();
            $now = nowString();
            $stmt = $db->prepare('INSERT INTO posts (user_id, title, slug, content, keyword_or_source, category, source_type, status, published_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $title, slugify($title), $content, $webhookUrl, 'Webhook Post', 'Webhook', 'Published', $webhookUrl, $now]);
            return ['success' => true, 'url' => $webhookUrl];
        }
        return ['success' => false, 'error' => "Webhook Error ({$result['http_code']})"];
    }

    public static function rebuildLocalIndex() {
        ensureDir(OUTPUT_DIR);
        $db = getDB();
        $stmt = $db->query("SELECT * FROM posts WHERE source_type = 'Local Magazine' ORDER BY id DESC");
        $posts = $stmt->fetchAll();

        $postItems = '';
        foreach ($posts as $p) {
            $featImg = $p['featured_image'] ?? '';
            $imgTag = $featImg ? "<img src=\"$featImg\" style=\"width:120px; height:80px; object-fit:cover; border-radius:8px; flex-shrink:0;\">" : '';
            $escTitle = escapeHtml($p['title']);
            $escCat = escapeHtml($p['category']);
            $escDate = $p['created_at'];
            $slug = $p['slug'];
            $postItems .= <<<ITEM
<li style="background:#fff; margin-bottom:18px; padding:20px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.03); border:1px solid #e2e8f0; display:flex; gap:20px; align-items:center; list-style:none;">
    $imgTag
    <div>
        <h3 style="margin:0 0 6px 0; font-size:1.15rem;"><a href="{$slug}.html" style="color:#0f172a; text-decoration:underline; font-weight:800;">$escTitle</a></h3>
        <p style="margin:0; color:#64748b; font-size:0.85rem; font-weight:600;">Category: $escCat | Published: $escDate</p>
    </div>
</li>
ITEM;
        }

        $nowYear = date('Y');
        $emptyMsg = empty($postItems) ? '<p style="text-align:center; color:#64748b;">No magazine articles generated yet.</p>' : '';
        $indexHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoBlog Local Magazine Archive</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', sans-serif; line-height: 1.6; max-width: 860px; margin: 0 auto; padding: 30px 20px; color: #0f172a; background: #fafafa; }
        h1 { color: #0f172a; text-align: center; font-weight: 800; margin-bottom: 30px; }
        ul { padding: 0; }
    </style>
</head>
<body>
    <h1>AutoBlog Autonomous Magazine Feed</h1>
    <ul>$postItems</ul>
    $emptyMsg
</body>
</html>
HTML;

        file_put_contents(OUTPUT_DIR . '/index.html', $indexHtml);
    }
}

/**
 * Insert thumbnail HTML right after the first H1 tag in the article content.
 */
function insertThumbnailAfterH1($content, $thumbHtml) {
    if (empty($thumbHtml)) return $content;
    if (preg_match('#(<h1[^>]*>.*?</h1>)#is', $content, $match, PREG_OFFSET_CAPTURE)) {
        $pos = $match[0][1] + strlen($match[0][0]);
        return substr($content, 0, $pos) . "\n" . $thumbHtml . "\n" . substr($content, $pos);
    }
    if (preg_match('#(</header>)#is', $content, $match, PREG_OFFSET_CAPTURE)) {
        $pos = $match[0][1] + strlen($match[0][0]);
        return substr($content, 0, $pos) . "\n" . $thumbHtml . "\n" . substr($content, $pos);
    }
    return $thumbHtml . "\n" . $content;
}

/**
 * Strip and validate all <img> tags from AI-generated content.
 * Removes obviously broken images, adds onerror handlers to remaining ones.
 */
function stripAndValidateImages($content) {
    $pattern = '#<img([^>]*)src=["\'\']([^"\'\']+)["\'\']([^>]*)/?>#is';
    $result = preg_replace_callback($pattern, function($matches) {
        $beforeSrc = $matches[1];
        $src = $matches[2];
        $afterSrc = $matches[3];
        $fullTag = $matches[0];
        if (stripos($fullTag, 'onerror') !== false) return $fullTag;
        $srcLower = strtolower(trim($src));
        if (empty($src) || $srcLower === 'null' || $srcLower === 'undefined' || 
            $srcLower === '#' || strpos($srcLower, 'example.com') !== false ||
            strpos($srcLower, 'placeholder') !== false ||
            strpos($srcLower, 'via.placeholder') !== false) {
            return '';
        }
        // Add a class for broken image handling via JS instead of inline onerror
        return '<img class="blog-content-img"' . $beforeSrc . 'src="' . $src . '"' . $afterSrc . '>';
    }, $content);
    $result = preg_replace('#<figure[^>]*>\s*</figure>#is', '', $result);
    return $result;
}

/**
 * Generate a real HTML article from a campaign item using Chat API + Image API.
 * Returns ['success' => bool, 'html_path' => string, 'used_chat_api' => bool, 'featured_image' => string, 'error' => string]
 */
function generateArticleHtmlFromCampaignItem($item, $userId, $activeSlot, $db, $contentAngle = '') {
    $headings = json_decode($item['headings'] ?? '{}', true) ?: [];
    $kws = json_decode($item['keyword_data'] ?? '[]', true) ?: [];
    $links = json_decode($item['internal_links'] ?? '[]', true) ?: [];
    $ext = json_decode($item['external_links'] ?? '[]', true) ?: [];
    $prompts = json_decode($item['image_prompts'] ?? '[]', true) ?: [];

    $title = $item['title'] ?? 'Untitled Article';
    $keyword = $item['primary_keyword'] ?? 'general topic';
    $h1 = $headings['H1'] ?? $title;
    $h2s = $headings['H2'] ?? ['Overview and Practical Context', 'What Competitors Miss', 'How to Choose the Right Option', 'Frequently Asked Questions'];
    $h3s = $headings['H3'] ?? ['Costs and Examples', 'Common Mistakes', 'Implementation Steps'];

    // Get Chat API credentials
    $stmt = $db->prepare('SELECT chat_credential_id, image_credential_id FROM user_workspace_slots WHERE user_id = ? AND slot_number = ?');
    $stmt->execute([$userId, $activeSlot]);
    $selection = $stmt->fetch();
    $chatVault = !empty($selection['chat_credential_id']) ? SecurityVault::getApiCredentialsById($userId, 'chat_api', $selection['chat_credential_id']) : SecurityVault::getApiCredentials($userId, 'chat_api');
    $imageVault = !empty($selection['image_credential_id']) ? SecurityVault::getApiCredentialsById($userId, 'image_api', $selection['image_credential_id']) : SecurityVault::getApiCredentials($userId, 'image_api');

    $chatContent = '';
    $chatUsed = false;

    if (!empty($chatVault['api_key'])) {
        $kwList = implode(', ', array_map(fn($x) => $x['keyword'] ?? '', $kws));
        $intLinkList = implode("\n", array_map(fn($x) => "- {$x['anchor_text']}: {$x['url']}", $links));
        $extLinkList = implode("\n", array_map(fn($x) => "- {$x['anchor_text']}: {$x['url']}", $ext));
        $h2List = implode(' | ', $h2s);
        $angleNote = $contentAngle ? "\nCONTENT ANGLE: $contentAngle" : '';

        $prompt = "Write a complete, publication-ready HTML blog article about \"$keyword\".\n\nTITLE: $title\nH1: $h1\nH2 SECTIONS: $h2List\nSUPPORTING KEYWORDS: $kwList\nINTERNAL LINKS (weave naturally into the text):\n$intLinkList\nEXTERNAL REFERENCES (cite naturally):\n$extLinkList\nIMAGE PROMPTS: " . implode('; ', $prompts) . "\n$angleNote\n\nREQUIREMENTS:\n- 1,800 to 2,200 words of original, researched content\n- Use semantic HTML: proper H1, H2, H3, H4, p, ul, li, table, figure, blockquote tags\n- Write in a natural, authoritative human voice - no AI cliches or banned phrases\n- Include a FAQ section at the end with 3 real questions and answers about $keyword\n- Include 2-3 internal links with natural anchor text\n- Include 1-2 external authority references\n- Add a comparison data table where relevant\n- Do NOT include html/head/body tags - only the article content\n- Do NOT invent facts, statistics, or quotes\n- Return ONLY the article HTML, no markdown fences";

        $chatResult = AIProviderClient::chat($chatVault, $prompt);
        if (!empty($chatResult['success']) && !empty($chatResult['content'])) {
            $chatContent = AntiAiSanitizer::sanitizeText($chatResult['content']);
            $chatContent = trim($chatContent);
            if (preg_match('/^```(?:html)?\s*\n(.+)\n```$/s', $chatContent, $m)) {
                $chatContent = $m[1];
            }
            $chatUsed = true;
        }
    }

    // Fallback: generate structured content if Chat API not available
    if (!$chatUsed) {
        $chatContent = generateFallbackArticleHtml($title, $keyword, $h1, $h2s, $h3s, $kws, $links, $ext, $prompts);
    }

    // Featured THUMBNAIL image via Image API — 9:16 ratio, MANDATORY first image
    // This is the ONLY image we generate. Retries up to 3 times.
    $featuredImgUrl = '';
    if (!empty($imageVault['api_key'])) {
        for ($imgAttempt = 1; $imgAttempt <= 3; $imgAttempt++) {
            $thumbPrompt = "YouTube thumbnail image for a blog about $keyword. Vertical 9:16 aspect ratio, 1080x1920. Eye-catching, professional, bold visual representing the concept. No text, no logos, no watermarks. Clean editorial style.";
            $imgResult = AIProviderClient::image($imageVault, $thumbPrompt);
            if (!empty($imgResult['success']) && !empty($imgResult['url'])) {
                if (validateImageUrl($imgResult['url'])) {
                    $featuredImgUrl = $imgResult['url'];
                    break;
                }
                error_log("[Image Validation] Thumbnail attempt $imgAttempt URL not accessible: " . $imgResult['url']);
            }
        }
    }

    // Strip ALL images/figures from Chat API content to prevent duplicates
    // (we insert our own thumbnail after H1). Also strip prompt/alt text paragraphs.
    $chatContent = preg_replace('#<figure[^>]*>.*?</figure>#is', '', $chatContent);
    $chatContent = preg_replace('#<img[^>]*/?>#is', '', $chatContent);
    $chatContent = preg_replace('#<p[^>]*>\s*(?:Image\s*\d+|Prompt|Alt)[^<]*</p>#is', '', $chatContent);

    // Strip broken images from Chat API content and add onerror handlers to remaining ones
    $chatContent = stripAndValidateImages($chatContent);

    // Insert 9:16 thumbnail RIGHT AFTER the H1 tag in the article content
    // Build the thumbnail HTML first - NO inline onerror (causes PHP parse errors)
    // Instead we use class="blog-thumb-img" and add a JS handler at page bottom
    $thumbHtml = '';
    $escKw = escapeHtml($keyword);
    if ($featuredImgUrl) {
        $escImgUrl = escapeHtml($featuredImgUrl);
        $thumbHtml = "<figure class=\"blog-thumbnail\" style=\"margin:0 0 24px 0;border-radius:12px;overflow:hidden;aspect-ratio:9/16;max-height:520px;\"><img class=\"blog-thumb-img\" data-kw=\"{$escKw}\" src=\"{$escImgUrl}\" alt=\"{$escKw} - Blog Thumbnail\" style=\"width:100%;height:100%;display:block;object-fit:cover;\" loading=\"eager\"></figure>";
    } else {
        $thumbHtml = "<figure class=\"blog-thumbnail\" style=\"margin:0 0 24px 0;border-radius:12px;overflow:hidden;aspect-ratio:9/16;max-height:520px;\"><div style=\"aspect-ratio:9/16;background:linear-gradient(135deg,#1b57f6,#8b5cf6);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.5rem;font-weight:800;padding:40px;text-align:center;min-height:300px;\">{$escKw}</div></figure>";
    }
    $chatContent = insertThumbnailAfterH1($chatContent, $thumbHtml);

    // Build the full HTML document
    $slug = slugify($title) . '-' . $item['id'];
    ensureDir(OUTPUT_DIR . '/demo');
    $filePath = OUTPUT_DIR . "/demo/$slug.html";
    $htmlUrl = "/published_posts/demo/$slug.html";

    $nowYear = date('Y');
    $dateStr = date('F d, Y');
    $escTitle = escapeHtml($title);

    $fullHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$escTitle</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Montserrat', sans-serif; line-height: 1.8; max-width: 880px; margin: 0 auto; padding: 36px 20px; color: #0f172a; background: #fafafa; }
        .nav-back { margin-bottom: 24px; display: inline-block; color: #0f172a; font-weight: 800; text-decoration: underline; font-size: 0.9rem; }
        article { background: #ffffff; padding: 48px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; }
        .blog-thumbnail { width: 100%; }
        h1 { font-size: 2.2rem; font-weight: 800; color: #0f172a; margin-bottom: 12px; line-height: 1.2; }
        h2 { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 36px; margin-bottom: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
        h3 { font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-top: 24px; margin-bottom: 12px; }
        p { margin-bottom: 18px; }
        a { color: #1b57f6; font-weight: 600; text-decoration: none; }
        a:hover { text-decoration: underline; }
        ul, ol { margin: 16px 0; padding-left: 22px; line-height: 2; }
        blockquote { border-left: 4px solid #1b57f6; padding: 16px 20px; margin: 24px 0; background: #f8fafc; border-radius: 0 8px 8px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        thead { display: table-header-group; }
        tbody { display: table-row-group; }
        tr { display: table-row; }
        td, th { border: 1px solid #e2e8f0; padding: 12px 14px; text-align: left; display: table-cell; }
        th { background: #f1f5f9; font-weight: 700; }
        figure { margin: 24px 0; }
        img { max-width: 100%; height: auto; border-radius: 12px; display: block; }
        footer { margin-top: 48px; font-size: 0.85rem; text-align: center; color: #64748b; font-weight: 600; }
        /* Mobile optimization */
        @media (max-width: 768px) {
            body { padding: 16px 12px; }
            article { padding: 24px 16px; border-radius: 14px; }
            h1 { font-size: 1.6rem; }
            h2 { font-size: 1.25rem; margin-top: 24px; }
            h3 { font-size: 1.05rem; }
            p { font-size: 0.95rem; margin-bottom: 14px; }
            ul, ol { padding-left: 18px; font-size: 0.95rem; }
            td, th { padding: 8px 10px; font-size: 0.85rem; }
            blockquote { padding: 12px 16px; }
            .blog-thumbnail { max-height: 360px; }
            figure { margin: 16px 0; }
            img { border-radius: 8px; }
        }
        @media (max-width: 480px) {
            h1 { font-size: 1.4rem; }
            h2 { font-size: 1.15rem; }
            article { padding: 16px 12px; }
            .blog-thumbnail { max-height: 280px; }
        }
        /* Image error fallback via JS */
        .thumb-placeholder { aspect-ratio: 9/16; background: linear-gradient(135deg, #1b57f6, #8b5cf6); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.5rem; font-weight: 800; padding: 40px; text-align: center; min-height: 300px; border-radius: 12px; }
    </style>
    <script>
        // Image error handler
        document.addEventListener('error', function(e) {
            if (e.target.tagName !== 'IMG') return;
            var img = e.target;
            if (img.classList.contains('blog-thumb-img')) {
                // Thumbnail: replace with gradient placeholder
                var fig = img.parentElement;
                var kw = img.getAttribute('data-kw') || '';
                var div = document.createElement('div');
                div.className = 'thumb-placeholder';
                div.textContent = kw || 'Blog Thumbnail';
                if (fig) { img.style.display = 'none'; fig.insertBefore(div, img.nextSibling); }
            } else {
                // Other images: hide
                img.style.display = 'none';
                var fig = img.closest('figure');
                if (fig && !fig.querySelector('.img-fallback')) {
                    var div2 = document.createElement('div');
                    div2.className = 'img-fallback';
                    div2.style.cssText = 'background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;text-align:center;color:#94a3b8;font-size:0.85rem;';
                    div2.textContent = 'Image could not be loaded';
                    fig.appendChild(div2);
                }
            }
        }, true);
    </script>
</head>
<body>
    <a href="/index.php" class="nav-back">&larr; Back to Dashboard</a>
    <article>
        $chatContent
    </article>
    <footer>&copy; $nowYear AutoBlog Autonomous Magazine Network &middot; Published $dateStr</footer>
</body>
</html>
HTML;

    file_put_contents($filePath, $fullHtml);

    return [
        'success' => true,
        'html_path' => $htmlUrl,
        'html_file' => $filePath,
        'used_chat_api' => $chatUsed,
        'featured_image' => $featuredImgUrl,
        'error' => ''
    ];
}

/**
 * Generate fallback HTML article when Chat API is not available.
 */
function generateFallbackArticleHtml($title, $keyword, $h1, $h2s, $h3s, $kws, $links, $ext, $prompts) {
    $escH1 = escapeHtml($h1);
    $escKw = escapeHtml($keyword);
    $dateStr = date('F d, Y');

    $sectionsHtml = '';
    foreach ($h2s as $i => $h2) {
        $escH2 = escapeHtml($h2);
        $sectionContent = "<p>This section covers key practical aspects of $keyword that professionals and business owners need to understand. The following analysis draws on documented industry practices and verified operational data.</p>";
        if (isset($h3s[$i])) {
            $sectionContent .= "<h3>" . escapeHtml($h3s[$i]) . "</h3><p>Understanding the nuances of " . escapeHtml($h3s[$i]) . " is critical for building a sustainable long-term strategy around $keyword.</p>";
        }
        if (isset($links[$i])) {
            $link = $links[$i];
            $sectionContent .= '<p>For related insights, see <a href="' . escapeHtml($link['url'] ?? '') . '">' . escapeHtml($link['anchor_text'] ?? 'our related guide') . '</a>.</p>';
        }
        $sectionsHtml .= "<h2>$escH2</h2>$sectionContent";
    }

    $faqHtml = "<h2>Frequently Asked Questions</h2>";
    $faqItems = [
        ["What is the most effective approach to $keyword?", "Start with baseline metrics, focus on high-intent targets, and maintain a consistent schedule with periodic performance audits."],
        ["How long does it take to see measurable results from $keyword?", "Most domains register noticeable improvements between 3 to 6 weeks following consistent implementation and multi-channel distribution."],
        ["What are the most common mistakes in $keyword?", "The three most frequent errors are: fragmented execution schedules, isolated content without internal links, and generic repetitive messaging."]
    ];
    foreach ($faqItems as $faq) {
        $faqHtml .= '<div style="background:#f8fafc;padding:16px;border-radius:8px;margin:12px 0;border:1px solid #e2e8f0;"><h3 style="margin:0 0 8px 0;">' . escapeHtml($faq[0]) . '</h3><p style="margin:0;color:#475569;">' . escapeHtml($faq[1]) . '</p></div>';
    }

    $kwTableHtml = '';
    if (!empty($kws)) {
        $kwTableHtml = "<h2>Keyword Research Data</h2><table><tr><th>Keyword</th><th>Volume</th><th>Difficulty</th><th>Intent</th></tr>";
        foreach ($kws as $x) {
            $kwTableHtml .= '<tr><td>' . escapeHtml($x['keyword'] ?? '') . '</td><td>' . escapeHtml($x['volume'] ?? '-') . '</td><td>' . escapeHtml($x['difficulty'] ?? '-') . '</td><td>' . escapeHtml($x['intent'] ?? '-') . '</td></tr>';
        }
        $kwTableHtml .= '</table>';
    }

    $html = <<<ARTICLE
<header style="margin-bottom:28px;">
    <h1>$escH1</h1>
    <p style="font-size:1.1rem;color:#475569;font-weight:500;margin-bottom:16px;">Comprehensive analysis and practical strategies for $escKw - updated $dateStr.</p>
    <div style="display:flex;align-items:center;gap:12px;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;padding:12px 0;font-size:0.85rem;color:#64748b;font-weight:600;">
        <div style="background:#0f172a;color:#fff;font-weight:800;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.85rem;">ED</div>
        <span><strong>Editorial Desk</strong> &bull; Senior Analyst &bull; $dateStr</span>
    </div>
</header>
$sectionsHtml
$kwTableHtml
$faqHtml
ARTICLE;

    return $html;
}
