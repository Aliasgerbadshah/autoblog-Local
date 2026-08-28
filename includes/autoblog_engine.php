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
        if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
            return ['success' => false, 'error' => 'Client ID, Client Secret, and Refresh Token are all required.'];
        }
        
        // Validate Client ID format — must end with .apps.googleusercontent.com
        $clientIdLower = strtolower(trim($clientId));
        if (!str_ends_with($clientIdLower, '.apps.googleusercontent.com')) {
            return ['success' => false, 'error' => 'Invalid Client ID format. It must end with ".apps.googleusercontent.com". You appear to be using a Service Account or wrong credential type. Go to Google Cloud Console → APIs & Services → Credentials → Create "OAuth 2.0 Client ID" → Application type: "Web application". Copy the Client ID (ends with .apps.googleusercontent.com) and Client Secret.'];
        }
        
        $payload = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
        ];
        $result = curlPostForm('https://oauth2.googleapis.com/token', $payload, [], 15);
        
        if ($result['success'] && $result['http_code'] === 200 && !empty($result['data']['access_token'])) {
            return ['success' => true, 'access_token' => $result['data']['access_token']];
        }
        
        // Build a clear error message with specific guidance per error type
        $errData = $result['data'] ?? [];
        $errMsg = $errData['error'] ?? '';
        $errDesc = $errData['error_description'] ?? '';
        
        if ($errMsg === 'unauthorized_client') {
            return [
                'success' => false, 
                'error' => "Google OAuth Error: unauthorized_client — This means your Refresh Token was generated with a DIFFERENT Client ID/Secret than what you entered. FIX: (1) Go to Google OAuth Playground (https://developers.google.com/oauthplayground), (2) Click the gear/settings icon, (3) Check 'Use your own OAuth credentials', (4) Enter YOUR Client ID and Client Secret, (5) Add https://developers.google.com/oauthplayground as Authorized Redirect URI in your Google Cloud Console → Credentials → OAuth 2.0 Client → Authorized redirect URIs, (6) Authorize with scope https://www.googleapis.com/auth/blogger, (7) Exchange the auth code for tokens, (8) Copy the NEW refresh_token and paste it in the vault. The old refresh token will NOT work with your custom client ID."
            ];
        }
        
        if ($errMsg === 'invalid_grant') {
            return [
                'success' => false,
                'error' => "Google OAuth Error: invalid_grant — Your Refresh Token has expired or been revoked. Google refresh tokens expire after 7 days if the OAuth app is in 'Testing' mode. FIX: (1) Go to Google Cloud Console → OAuth consent screen → Publish App to 'In production', OR (2) Generate a new Refresh Token from OAuth Playground using your own Client ID/Secret."
            ];
        }
        
        if ($errMsg === 'invalid_client') {
            return [
                'success' => false,
                'error' => "Google OAuth Error: invalid_client — The Client Secret is wrong. FIX: Go to Google Cloud Console → APIs & Services → Credentials → Click your OAuth 2.0 Client ID → Copy the EXACT Client Secret (starts with GOCSPX-). Do NOT use a Service Account key."
            ];
        }
        
        if ($errMsg) {
            return ['success' => false, 'error' => "Google OAuth Error: $errMsg — $errDesc (HTTP {$result['http_code']})"];
        }
        return ['success' => false, 'error' => "Token Refresh Failed (HTTP {$result['http_code']}): " . substr($result['raw'] ?? 'Unknown', 0, 500)];
    }
    
    /**
     * Generate the Google OAuth authorization URL for manual token generation.
     * User visits this URL, authorizes, gets an auth code, then exchanges it for tokens.
     */
    public static function getOAuthAuthorizationUrl($clientId, $redirectUri = 'https://developers.google.com/oauthplayground') {
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'https://www.googleapis.com/auth/blogger',
            'access_type' => 'offline',
            'response_type' => 'code',
            'prompt' => 'consent',
        ];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    /**
     * Exchange an authorization code for access token + refresh token.
     * Called after user visits the OAuth URL and gets an auth code.
     */
    public static function exchangeAuthCode($clientId, $clientSecret, $authCode, $redirectUri = 'https://developers.google.com/oauthplayground') {
        $payload = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $authCode,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ];
        $result = curlPostForm('https://oauth2.googleapis.com/token', $payload, [], 15);
        
        if ($result['success'] && $result['http_code'] === 200 && !empty($result['data']['access_token'])) {
            return [
                'success' => true,
                'access_token' => $result['data']['access_token'],
                'refresh_token' => $result['data']['refresh_token'] ?? '',
                'expires_in' => $result['data']['expires_in'] ?? 3600
            ];
        }
        
        $errData = $result['data'] ?? [];
        $errMsg = $errData['error'] ?? '';
        $errDesc = $errData['error_description'] ?? '';
        return ['success' => false, 'error' => "Auth code exchange failed: $errMsg — $errDesc (HTTP {$result['http_code']})"];
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

        @file_put_contents($filePath, $htmlTemplate);

        $publishedUrl = "/published_posts/$fileName";
        $db = getDB();
        $now = nowString();
        $stmt = $db->prepare('INSERT INTO posts (user_id, title, slug, content, keyword_or_source, category, source_type, status, published_url, featured_image, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $title, $slug, $content, $keyword, $category, 'Local Magazine', 'Published', $publishedUrl, $featuredImage, $now]);

        self::rebuildLocalIndex();
        return $publishedUrl;
    }

    public static function publishBlogger($userId, $blogId, $title, $content, $clientId = null, $clientSecret = null, $refreshToken = null, $publishDate = null) {
        // Blogger API v3: POST (create post) requires OAuth 2.0 Bearer token.
        // We must have OAuth refresh credentials to publish.
        
        if (empty($blogId)) {
            return ['success' => false, 'error' => 'Missing Blogger Blog ID.'];
        }
        
        $url = "https://www.googleapis.com/blogger/v3/blogs/" . trim($blogId) . "/posts/";
        $authHeader = null;
        
        // Use OAuth with auto-refresh
        if ($refreshToken && $clientId && $clientSecret) {
            $rfRes = BloggerOAuthHelper::refreshAccessToken($clientId, $clientSecret, $refreshToken);
            if ($rfRes['success'] && !empty($rfRes['access_token'])) {
                $authHeader = 'Authorization: Bearer ' . trim($rfRes['access_token']);
                // Save the fresh access token back to vault for next time
                $vault = SecurityVault::getApiCredentials($userId, 'blogger_api');
                if (!empty($vault)) {
                    $vault['access_token'] = $rfRes['access_token'];
                    $alias = $vault['account_alias'] ?? 'Primary Blogger Account';
                    unset($vault['account_alias']);
                    SecurityVault::saveApiCredentials($userId, 'blogger_api', $vault, $alias);
                }
            } else {
                return ['success' => false, 'error' => 'OAuth token refresh failed: ' . ($rfRes['error'] ?? 'Check Client ID, Secret, and Refresh Token.')];
            }
        }
        
        if (!$authHeader) {
            return ['success' => false, 'error' => 'OAuth credentials required. Save Client ID, Client Secret, and Refresh Token in the Blogger vault.'];
        }

        // ========== BLOGGER CSS OVERRIDES ==========
        // Inject CSS to make Blogger article look same-to-same as local HTML preview.
        // Hides Blogger's default title (our H1 is center-aligned in the article).
        // Forces full-width layout, scoped with !important to override Blogger templates.
        $bloggerOverrides = <<<CSS
<style>
/* === AUTOBLOG BLOGGER OVERRIDES — Scoped to article, !important to beat template === */
/* Hide Blogger's default post title — we use our own H1 inside the article */
.post-title, .post-title.entry-title, h3.post-title, .entry-title, .blog-post h3.post-title, .post h3.post-title, .post-title.entry-title.a, .post-title { display:none!important; }
/* Make the article container full-width */
.content-outer, .content-inner, .post-outer, .post, .blog-posts, .post-body, .entry-content, #content, .region-inner, .post-body.entry-content { max-width:100%!important; width:100%!important; padding:0!important; margin:0 auto!important; }
/* Article styling — match local HTML preview exactly */
article.monochrome-editorial-article, article { max-width:960px!important; margin:0 auto!important; padding:24px 20px!important; font-family:'Montserrat',-apple-system,sans-serif!important; line-height:1.85!important; color:#334155!important; background:#ffffff!important; border-radius:0!important; box-shadow:none!important; }
/* Center-align our H1 */
article h1, article .monochrome-editorial-article h1 { text-align:center!important; font-size:2.2rem!important; font-weight:800!important; color:#0f172a!important; margin-bottom:16px!important; line-height:1.2!important; }
article h2 { font-size:1.5rem!important; font-weight:800!important; color:#0f172a!important; margin-top:36px!important; margin-bottom:16px!important; border-bottom:1px solid #e2e8f0!important; padding-bottom:8px!important; }
article h3 { font-size:1.15rem!important; font-weight:700!important; color:#0f172a!important; margin-top:24px!important; margin-bottom:12px!important; }
article p { margin-bottom:18px!important; font-size:1.02rem!important; text-align:justify!important; }
article a { color:#1b57f6!important; font-weight:600!important; text-decoration:none!important; }
article a:hover { text-decoration:underline!important; }
article ul, article ol { margin:16px 0!important; padding-left:22px!important; line-height:2!important; }
article blockquote { border-left:4px solid #1b57f6!important; padding:16px 20px!important; margin:24px 0!important; background:#f8fafc!important; border-radius:0 8px 8px 0!important; }
article table { width:100%!important; border-collapse:collapse!important; margin:20px 0!important; display:block!important; overflow-x:auto!important; -webkit-overflow-scrolling:touch!important; }
article td, article th { border:1px solid #e2e8f0!important; padding:12px 14px!important; text-align:left!important; }
article th { background:#f1f5f9!important; font-weight:700!important; }
article figure { margin:24px 0!important; }
article img { max-width:100%!important; height:auto!important; border-radius:12px!important; display:block!important; }
/* Thumbnail must fill full article width — no blank space */
article .blog-thumbnail { width:100%!important; max-width:100%!important; margin:0 0 24px 0!important; }
article .blog-thumbnail img { width:100%!important; display:block!important; object-fit:cover!important; border-radius:12px!important; }
article footer { margin-top:48px!important; font-size:0.85rem!important; text-align:center!important; color:#64748b!important; font-weight:600!important; }
/* Mobile responsive for Blogger */
@media(max-width:768px) {
  article { padding:16px 12px!important; }
  article h1 { font-size:1.6rem!important; }
  article h2 { font-size:1.25rem!important; margin-top:24px!important; }
  article h3 { font-size:1.05rem!important; }
  article p { font-size:0.95rem!important; margin-bottom:14px!important; text-align:justify!important; }
  article ul, article ol { padding-left:18px!important; font-size:0.95rem!important; }
  article td, article th { padding:8px 10px!important; font-size:0.85rem!important; }
  article blockquote { padding:12px 16px!important; }
  article figure { margin:16px 0!important; }
  article img { border-radius:8px!important; }
}
@media(max-width:480px) {
  article h1 { font-size:1.4rem!important; }
  article h2 { font-size:1.15rem!important; }
  article { padding:12px 8px!important; }
}
/* Load Montserrat font in Blogger */
</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
CSS;

        // Prepend Blogger CSS overrides to the content
        $content = $bloggerOverrides . "\n" . $content;

        $payload = [
            'kind' => 'blogger#post',
            'blog' => ['id' => trim($blogId)],
            'title' => $title,
            'content' => $content
        ];

        // If a future publish date is provided, create as DRAFT then use /publish endpoint
        // Blogger API v3 correct scheduling flow:
        //   Step 1: POST /blogs/{blogId}/posts?isDraft=true  → creates draft, returns postId
        //   Step 2: POST /blogs/{blogId}/posts/{postId}/publish?publishDate={RFC3339}  → schedules it
        if (!empty($publishDate)) {
            $publishTs = strtotime($publishDate);
            if ($publishTs !== false && $publishTs > time()) {
                // Step 1: Create as DRAFT using ?isDraft=true query param (NOT status in body)
                $draftUrl = $url . "?isDraft=true";
                $result = curlPost($draftUrl, $payload, ['Content-Type: application/json', $authHeader], 15);
                $data = $result['data'] ?? [];
                
                if ($result['success'] && in_array($result['http_code'], [200, 201]) && !empty($data['id'])) {
                    $postId = $data['id'];
                    
                    // Step 2: Call dedicated /publish endpoint with publishDate query param
                    // Format: RFC 3339 with timezone offset (Asia/Kolkata = +05:30)
                    $dt = new DateTime('@' . $publishTs);
                    $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                    $rfc3339 = $dt->format('Y-m-d\TH:i:sP'); // e.g. 2025-08-15T10:00:00+05:30
                    
                    $publishEndpoint = "https://www.googleapis.com/blogger/v3/blogs/" . trim($blogId) . "/posts/" . $postId . "/publish?publishDate=" . urlencode($rfc3339);
                    
                    $ch = curl_init($publishEndpoint);
                    curl_setopt_array($ch, [
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => '',  // No body per API docs
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 15,
                        CURLOPT_HTTPHEADER => ['Content-Length: 0', $authHeader],
                        CURLOPT_SSL_VERIFYPEER => false,
                    ]);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlErr = curl_error($ch);
                    curl_close($ch);
                    $publishData = json_decode($response, true);
                    
                    if (in_array($httpCode, [200, 201]) && !empty($publishData['url'])) {
                        // Successfully scheduled on Blogger
                        $bloggerUrl = $publishData['url'];
                        $db = getDB();
                        $now = nowString();
                        $stmt = $db->prepare('INSERT INTO posts (user_id, title, slug, content, keyword_or_source, category, source_type, status, published_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                        $stmt->execute([$userId, $title, slugify($title), $content, $blogId, 'Blogger Post', 'Blogger REST API', 'Scheduled', $bloggerUrl, $now]);
                        return ['success' => true, 'url' => $bloggerUrl, 'message' => 'Scheduled on Blogger for ' . $rfc3339 . ' (post status: ' . ($publishData['status'] ?? 'scheduled') . ')'];
                    }
                    
                    // If /publish endpoint failed, try fallback: isDraft=false + published date on insert
                    // This is the alternative approach from StackOverflow: insert with isDraft=false & published date
                    // First, delete the draft we just created (to avoid duplicates)
                    $deleteUrl = "https://www.googleapis.com/blogger/v3/blogs/" . trim($blogId) . "/posts/" . $postId;
                    $delCh = curl_init($deleteUrl);
                    curl_setopt_array($delCh, [
                        CURLOPT_CUSTOMREQUEST => 'DELETE',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_HTTPHEADER => [$authHeader],
                        CURLOPT_SSL_VERIFYPEER => false,
                    ]);
                    curl_exec($delCh);
                    curl_close($delCh);
                    
                    $fallbackUrl = $url . "?isDraft=false";
                    $payload['published'] = $rfc3339;
                    $fbResult = curlPost($fallbackUrl, $payload, ['Content-Type: application/json', $authHeader], 15);
                    $fbData = $fbResult['data'] ?? [];
                    
                    if ($fbResult['success'] && in_array($fbResult['http_code'], [200, 201]) && !empty($fbData['url'])) {
                        $bloggerUrl = $fbData['url'];
                        $db = getDB();
                        $now = nowString();
                        $stmt = $db->prepare('INSERT INTO posts (user_id, title, slug, content, keyword_or_source, category, source_type, status, published_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                        $stmt->execute([$userId, $title, slugify($title), $content, $blogId, 'Blogger Post', 'Blogger REST API', 'Scheduled', $bloggerUrl, $now]);
                        return ['success' => true, 'url' => $bloggerUrl, 'message' => 'Scheduled on Blogger for ' . $rfc3339 . ' (fallback method, status: ' . ($fbData['status'] ?? 'unknown') . ')'];
                    }
                    
                    // Both methods failed — but draft exists, return partial success with the draft URL
                    $draftUrl_web = $data['url'] ?? '';
                    $apiError = !empty($publishData['error']['message']) ? $publishData['error']['message'] : ($curlErr ?: "HTTP $httpCode");
                    $fbError = !empty($fbData['error']['message']) ? $fbData['error']['message'] : ("HTTP " . ($fbResult['http_code'] ?? 0));
                    return ['success' => true, 'url' => $draftUrl_web, 'message' => "Created as draft on Blogger. Auto-schedule failed (publish API: $apiError; fallback: $fbError). Please schedule manually in Blogger.", 'draft_id' => $postId, 'partial' => true];
                }
                $errorMsg = $data['error']['message'] ?? ($result['raw'] ?? 'Unknown error');
                return ['success' => false, 'error' => "Blogger API Error creating draft ({$result['http_code']}): $errorMsg"];
            }
        }

        $result = curlPost($url, $payload, ['Content-Type: application/json', $authHeader], 15);

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

        @file_put_contents(OUTPUT_DIR . '/index.html', $indexHtml);
    }
}

/**
 * Insert thumbnail HTML right after the first H1 tag in the article content.
 */
/**
 * Split long paragraphs into shorter ones (strictly 45-50 words per <p>).
 * Preserves HTML tags, links, and formatting within paragraphs.
 */
function splitLongParagraphs($html, $minWords = 45, $maxWords = 50) {
    // Match all <p> tags and split their content if too long
    $result = preg_replace_callback('#<p([^>]*)>(.*?)</p>#is', function($match) use ($minWords, $maxWords) {
        $attrs = $match[1];
        $content = $match[2];
        
        // Strip HTML tags to count words in plain text
        $plainText = strip_tags($content);
        $words = preg_split('/\s+/', trim($plainText));
        $wordCount = count($words);
        
        if ($wordCount <= $maxWords) {
            return $match[0]; // Short enough, keep as-is
        }
        
        // For long paragraphs, split at sentence boundaries targeting 45-50 words
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($plainText));
        
        $paragraphs = [];
        $currentPara = '';
        $currentWords = 0;
        
        foreach ($sentences as $sentence) {
            $sentenceWords = count(preg_split('/\s+/', trim($sentence)));
            
            if ($currentWords + $sentenceWords > $maxWords && $currentPara !== '') {
                $paragraphs[] = trim($currentPara);
                $currentPara = $sentence;
                $currentWords = $sentenceWords;
            } else {
                $currentPara .= ($currentPara ? ' ' : '') . $sentence;
                $currentWords += $sentenceWords;
            }
        }
        
        if (trim($currentPara)) {
            $paragraphs[] = trim($currentPara);
        }
        
        if (count($paragraphs) <= 1) {
            return $match[0]; // Couldn't split meaningfully
        }
        
        // Check if the original <p> contained links we should preserve
        if (stripos($content, '<a ') !== false) {
            // Has links — split the HTML content at sentence boundaries
            $htmlSentences = preg_split('/(?<=[.!?])\s+/', trim($content));
            $htmlParagraphs = [];
            $currentHtml = '';
            $currentW = 0;
            
            foreach ($htmlSentences as $hSent) {
                $hWords = count(preg_split('/\s+/', trim(strip_tags($hSent))));
                if ($currentW + $hWords > $maxWords && $currentHtml !== '') {
                    $htmlParagraphs[] = '<p' . $attrs . '>' . trim($currentHtml) . '</p>';
                    $currentHtml = $hSent;
                    $currentW = $hWords;
                } else {
                    $currentHtml .= ($currentHtml ? ' ' : '') . $hSent;
                    $currentW += $hWords;
                }
            }
            if (trim($currentHtml)) {
                $htmlParagraphs[] = '<p' . $attrs . '>' . trim($currentHtml) . '</p>';
            }
            return implode("\n", $htmlParagraphs);
        }
        
        // No links — safe to rebuild from plain text
        return implode("\n", array_map(fn($p) => '<p' . $attrs . '>' . escapeHtml($p) . '</p>', $paragraphs));
    }, $html);
    
    return $result;
}

/**
 * Validate external links in HTML content.
 * Replaces broken/unreachable <a> links with plain text.
 * Only validates external links (http/https), skips internal and anchor links.
 */
function validateAndFixExternalLinks($html, $checkLive = false) {
    if (!$checkLive) return $html;
    return preg_replace_callback('#<a[^>]*href=["\']?(https?://[^"\'>\s]+)["\']?[^>]*>(.*?)</a>#is', function($match) {
        $url = $match[1];
        $linkText = $match[2];
        $fullTag = $match[0];
        
        // Skip known working domains (whitelist)
        $trustedDomains = ['wikipedia.org', 'developers.google.com', 'developer.mozilla.org', 
            'schema.org', 'www.w3.org', 'support.google.com', 'moz.com', 'ahrefs.com',
            'searchengineland.com', 'neilpatel.com', 'hubspot.com', 'backlinko.com',
            'semrush.com', 'yoast.com', 'google.com', 'github.com', 'stackoverflow.com',
            'www.nngroup.com', 'opensource.google', 'ai.google'];
        
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        foreach ($trustedDomains as $trusted) {
            if (str_ends_with($host, $trusted) || $host === $trusted) {
                return $fullTag; // Trust it, keep the link
            }
        }
        
        // For non-whitelisted URLs, do a quick HEAD check
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_NOBODY => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; AutoBlog/1.0)',
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Keep link if it returns 200 or 301/302 (redirect is OK)
        if (in_array($httpCode, [200, 301, 302, 303, 307, 308])) {
            return $fullTag;
        }
        
        // Link is broken — replace with plain text + warning
        return $linkText;
    }, $html);
}

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
 * Insert content (e.g. a content image) after the 2nd H2 tag in HTML.
 */
function insertContentAfterSecondH2($content, $insertHtml) {
    if (empty($insertHtml)) return $content;
    // Find the 2nd <h2> tag
    $count = 0;
    $offset = 0;
    while (preg_match('#<h2[^>]*>.*?</h2>#is', $content, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $count++;
        if ($count === 2) {
            $pos = $match[0][1] + strlen($match[0][0]);
            return substr($content, 0, $pos) . "\n" . $insertHtml . "\n" . substr($content, $pos);
        }
        $offset = $match[0][1] + strlen($match[0][0]);
    }
    // No 2nd H2 found — insert after first H2
    if (preg_match('#<h2[^>]*>.*?</h2>#is', $content, $match, PREG_OFFSET_CAPTURE)) {
        $pos = $match[0][1] + strlen($match[0][0]);
        return substr($content, 0, $pos) . "\n" . $insertHtml . "\n" . substr($content, $pos);
    }
    // No H2 found — just append
    return $content . "\n" . $insertHtml;
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
 * Ask Chat for a short photorealistic prompt that captures this article's core concept.
 * Used for the thumbnail and in-article image so they match the blog, not a generic keyword shot.
 */
function buildCoreConceptImagePrompt($item, $chatVault = [], $articleHtml = '') {
    $title = trim((string)($item['title'] ?? ''));
    $keyword = trim((string)($item['primary_keyword'] ?? ''));
    $headings = json_decode($item['headings'] ?? '{}', true) ?: [];
    $h2s = $headings['H2'] ?? [];
    $h2List = implode(', ', array_slice(array_map('strval', is_array($h2s) ? $h2s : []), 0, 4));
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string)$articleHtml)));
    $excerpt = substr($plain, 0, 500);
    $fallback = trim("Photorealistic editorial photo of the core idea of \"$title\"" . ($keyword ? " (about $keyword)" : '') . '. Real-world scene, specific subject, natural lighting, no text, no logos.');
    if (empty($chatVault['api_key']) || !class_exists('AIProviderClient')) {
        return $fallback;
    }
    $ask = "Write ONE image-generation prompt (max 40 words) for a photorealistic photo that shows the CORE CONCEPT of this blog article. Be specific about the scene, people, objects, and setting. No text, no logos, no watermarks, no collage.\nTITLE: $title\nPRIMARY KEYWORD: $keyword\nH2 SECTIONS: $h2List\nARTICLE START: $excerpt\nReturn ONLY the prompt.";
    try {
        $res = AIProviderClient::chat($chatVault, $ask);
        if (!empty($res['success']) && !empty($res['content'])) {
            $prompt = trim(preg_replace('/\s+/', ' ', strip_tags($res['content'])));
            $prompt = trim($prompt, "\"'` \t\n\r");
            if (strlen($prompt) > 40) {
                $words = preg_split('/\s+/', $prompt);
                if (is_array($words) && count($words) > 40) {
                    $prompt = implode(' ', array_slice($words, 0, 40));
                }
            }
            if (strlen($prompt) >= 20) {
                return $prompt;
            }
        }
    } catch (Throwable $e) {
        error_log('[Image] core-concept prompt failed: ' . $e->getMessage());
    }
    return $fallback;
}

/**
 * Generate a real HTML article from a campaign item using Chat API + Image API.
 * Returns ['success' => bool, 'html_path' => string, 'used_chat_api' => bool, 'featured_image' => string, 'error' => string]
 */
function saveCampaignHtmlFile($title, $articleInnerHtml, $itemId) {
    $slug = slugify($title) . '-' . $itemId;
    $escTitle = escapeHtml($title);
    $nowYear = date('Y');
    $dateStr = date('F d, Y');
    $sharedArticleCss = '* { box-sizing: border-box; } article { font-family: Montserrat, sans-serif; line-height: 1.85; color: #334155; max-width: 960px; margin: 0 auto; font-size: 1.02rem; background: #ffffff; padding: 48px; border: 1px solid #e2e8f0; } h1 { font-size: 2.2rem; font-weight: 800; color: #0f172a; text-align: center; } h2 { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 36px; } p { margin-bottom: 18px; text-align: justify; } img { max-width: 100%; height: auto; border-radius: 12px; }';
    $fullHtml = "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"><title>{$escTitle}</title><style>body{font-family:Montserrat,sans-serif;max-width:960px;margin:0 auto;padding:36px 20px;background:#fafafa;}{$sharedArticleCss}</style></head><body><article>\n{$articleInnerHtml}\n</article><footer>&copy; {$nowYear} AutoBlog &middot; {$dateStr}</footer></body></html>";
    $dirs = [];
    if (defined('OUTPUT_DIR')) $dirs[] = rtrim(OUTPUT_DIR, '/') . '/demo';
    $dirs[] = dirname(__DIR__) . '/published_posts/demo';
    $filePath = '';
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $try = $dir . '/' . $slug . '.html';
        $ok = @file_put_contents($try, $fullHtml);
        if ($ok !== false && is_file($try) && filesize($try) > 400) {
            $filePath = $try;
            break;
        }
    }
    return [
        'success' => $filePath !== '',
        'html_path' => '/published_posts/demo/' . $slug . '.html',
        'html_file' => $filePath,
        'error' => $filePath === '' ? 'Could not write HTML file. Check published_posts/demo permissions (775).' : '',
    ];
}

function articleLooksLikeDraftHtml($html) {
    $h = (string)$html;
    return (stripos($h, 'This section covers key practical aspects') !== false)
        || (stripos($h, 'Keep a simple scorecard') !== false)
        || (stripos($h, 'mapping the outcome you want from') !== false)
        || (stripos($h, 'This draft will be replaced by Master HTML') !== false)
        || (stripos($h, 'Do not treat this placeholder as the published article') !== false);
}

function relatedStockPhotoUrl($title, $keyword) {
    $pool = [
        'https://images.unsplash.com/photo-1480714378408-67cf0d13bc1b?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1499750310107-5fef28a66643?auto=format&fit=crop&w=1200&q=80',
    ];
    $i = abs(crc32((string)$title . '|' . (string)$keyword)) % count($pool);
    return $pool[$i];
}

function generateArticleHtmlFromCampaignItem($item, $userId, $activeSlot, $db, $contentAngle = '', $wantMaster = true) {
    $webLimit = (PHP_SAPI === 'cli') ? 90 : 45;
    @set_time_limit($webLimit);
    @ini_set('max_execution_time', (string)$webLimit);
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

    // Write a real HTML file FIRST so Hostinger timeouts still leave a draft.
    $earlyInner = generateFallbackArticleHtml($title, $keyword, $h1, $h2s, $h3s, $kws, $links, $ext, $prompts);
    $saved = saveCampaignHtmlFile($title, $earlyInner, $item['id'] ?? 'draft');
    if (empty($saved['success'])) {
        return ['success' => false, 'html_path' => '', 'html_file' => '', 'used_chat_api' => false, 'featured_image' => '', 'error' => $saved['error']];
    }
    $earlyPath = $saved['html_path'];
    $earlyFile = $saved['html_file'];
    if (!empty($item['id']) && $db) {
        try { $db->prepare("UPDATE campaign_items SET article_status = 'Draft HTML', html_path = ?, last_error = ? WHERE id = ?")->execute([$earlyPath, 'Draft HTML saved. Chat API is writing the master article...', $item['id']]); } catch (Throwable $e) {}
    }

    if (!$wantMaster) {
        return [
            'success' => true,
            'html_path' => $earlyPath,
            'html_file' => $earlyFile,
            'used_chat_api' => false,
            'featured_image' => '',
            'error' => 'Draft HTML only. Click Write Master HTML for Chat + image.',
        ];
    }

    // Get Chat API credentials
    $stmt = $db->prepare('SELECT chat_credential_id, image_credential_id FROM user_workspace_slots WHERE user_id = ? AND slot_number = ?');
    $stmt->execute([$userId, $activeSlot]);
    $selection = $stmt->fetch();
    $chatVault = !empty($selection['chat_credential_id']) ? SecurityVault::getApiCredentialsById($userId, 'chat_api', $selection['chat_credential_id']) : SecurityVault::getApiCredentials($userId, 'chat_api');
    $imageVault = !empty($selection['image_credential_id']) ? SecurityVault::getApiCredentialsById($userId, 'image_api', $selection['image_credential_id']) : SecurityVault::getApiCredentials($userId, 'image_api');

    $chatContent = '';
    $chatUsed = false;
    $chatResult = ['success' => false, 'error' => 'Chat API not called'];

    $kws = array_slice(array_values($kws), 0, 5);
    $primaryKw = $kws[0]['keyword'] ?? $keyword;
    $secondaryKws = array_values(array_filter(array_map(fn($x) => $x['keyword'] ?? '', array_slice($kws, 1))));

    if (!empty($chatVault['api_key'])) {
        $kwList = implode(', ', array_map(fn($x) => $x['keyword'] ?? '', $kws));
        $intLinkList = implode("\n", array_map(fn($x) => "- {$x['anchor_text']}: {$x['url']}", $links));
        $extLinkList = implode("\n", array_map(fn($x) => "- {$x['anchor_text']}: {$x['url']}", $ext));
        $h2List = implode(' | ', $h2s);
        $angleNote = $contentAngle ? "\nCONTENT ANGLE: $contentAngle" : '';
        $secList = implode(', ', $secondaryKws);
        $kwByVolume = '';
        foreach ($kws as $ki => $krow) {
            $role = ($ki === 0) ? 'PRIMARY (highest search volume — main target)' : ('SECONDARY ' . $ki . ' (supporting)');
            $kwByVolume .= '- ' . $role . ': "' . ($krow['keyword'] ?? '') . '" | monthly volume: ' . ($krow['volume'] ?? 'n/a') . ' | difficulty: ' . ($krow['difficulty'] ?? 'n/a') . ' | intent: ' . ($krow['intent'] ?? '') . "\n";
        }
        $h3List = implode(' | ', array_map('strval', is_array($h3s) ? $h3s : []));
        $secOnce = '';
        foreach ($secondaryKws as $si => $sk) {
            $secOnce .= '- Use secondary keyword "' . $sk . '" naturally 1–2 times in H2 section ' . ($si + 2) . " (do not replace it with the primary).\n";
        }

        $nowMonth = date('F');
        $nowYear = date('Y');
        $prompt = "Write a publication-ready HTML magazine article (editorial theme) about \"$title\".\n\nTITLE: $title\nH1 (must include the PRIMARY keyword): $h1\nH2 SECTIONS (use these, in order): $h2List\nH3 SUPPORT: $h3List\n\nGOOGLE KEYWORD PLANNER TARGETS (exact volume — do NOT invent extra keywords or fake volumes):\n$kwByVolume\nKEYWORD PLACEMENT:\n- PRIMARY \"$primaryKw\" = highest volume. Use in: H1, first 40–60 words (direct answer), one H2 or H3, and the closing paragraph. Do NOT repeat it in every paragraph.\n$secOnce- Never stuff. Never invent keywords. Never paste a Keyword Research Data table as the article body.\n\nINTERNAL LINKS (weave 1–2 naturally with the given anchor text — ONLY these URLs):\n$intLinkList\nEXTERNAL REFERENCES (cite at least 3, ONLY these URLs, no invented Wikipedia/Moz links):\n$extLinkList\n$angleNote\n\nLENGTH: 1,000 to 1,200 words of original body copy. Finish the full article in this response.\n\nFORMAT / THEME (semantic HTML only: header, h1, h2, h3, p, ul, ol, li, table, blockquote, strong. NO img, figure, html, head, or body tags):\n1) <header> with <h1> then a one-sentence dek.\n2) Opening: AEO direct answer in the first paragraph (what it is + who it is for + the practical takeaway).\n3) Each H2: 2–4 short paragraphs (40–50 words each) that are precise and informative, not filler.\n4) At least TWO real <table>s: (a) comparison or decision matrix, (b) checklist / specs / steps. Tables must teach the topic, not list keywords.\n5) One short bullet list of key takeaways (GEO: quotable facts, named entities, clear definitions).\n6) FAQ as <h2>Frequently Asked Questions</h2> then 4 items: each <h3> is a real search-style question; <p> is a 2–3 sentence direct answer. Put one secondary keyword in one FAQ answer.\n7) Close with a practical next-step paragraph using the primary keyword once.\n\nSEO + AEO + GEO:\n- SEO: logical H1→H2→H3, primary in intro, secondaries in later sections, descriptive anchors, year $nowYear where natural.\n- AEO: answer-first, FAQ questions people actually ask, snippet-ready short definitions.\n- GEO: entity-clear writing (who/what/where), cite only the URLs above, no invented statistics or quotes.\n- Do NOT write \"This section covers key practical aspects\". No AI cliches. No month name ($nowMonth) in headings.\n- Return ONLY the article HTML.";

        $chatResult = ['success' => false];
        if ($wantMaster) {
            $chatVault['auto_model'] = false;
            $chatVault['model_pool'] = [];
            if (PHP_SAPI !== 'cli' && (($chatVault['provider'] ?? '') === 'pollinations')) {
                $pm = strtolower(trim((string)($chatVault['model'] ?? '')));
                if ($pm === '' || $pm === 'openai' || $pm === 'openai-large' || $pm === 'gpt-4o' || $pm === 'gpt-4o-mini') {
                    $chatVault['model'] = 'openai-fast';
                }
            }
            $chatTimeout = (PHP_SAPI === 'cli') ? 50 : 18;
            $chatResult = AIProviderClient::chat($chatVault, $prompt, $chatTimeout);
        } else {
            $chatResult = ['success' => false, 'error' => 'Draft-only write (no Chat on this request).'];
        }
        if (!empty($chatResult['success']) && !empty($chatResult['content'])) {
            $chatContent = AntiAiSanitizer::sanitizeText($chatResult['content']);
            $chatContent = trim($chatContent);
            if (preg_match('/^```(?:html)?\s*\n(.+)\n```$/s', $chatContent, $m)) {
                $chatContent = $m[1];
            }
            $plainLen = strlen(strip_tags($chatContent));
            $words = str_word_count(strip_tags($chatContent));
            $chatUsed = ($plainLen > 800) && ($words >= 350) && !articleLooksLikeDraftHtml($chatContent);
            if (!$chatUsed) {
                $chatResult['error'] = 'Chat returned draft-like or too-short HTML (' . $words . ' words). Keeping Draft HTML.';
                $chatContent = '';
            }
        }
    }

    // Post-process: split long paragraphs (strictly 45-50 words) for readability
    if ($chatUsed && !empty($chatContent)) {
        $chatContent = splitLongParagraphs($chatContent, 45, 50);
        // Validate external links (replace broken ones with plain text)
        $chatContent = validateAndFixExternalLinks($chatContent);
    }

    $chatError = '';
    if (!$chatUsed) {
        $chatContent = generateFallbackArticleHtml($title, $keyword, $h1, $h2s, $h3s, $kws, $links, $ext, $prompts);
        if (empty($chatVault['api_key'])) {
            $chatError = 'Chat API key missing. Master HTML was not written.';
        } else {
            $chatError = 'Chat API failed: ' . ($chatResult['error'] ?? 'empty content') . '. Draft HTML is shown until Chat succeeds.';
        }
        if (!empty($item['id']) && $db) {
            try { $db->prepare("UPDATE campaign_items SET last_error = ? WHERE id = ?")->execute([$chatError, $item['id']]); } catch (Throwable $e) {}
        }
    }

    $featuredImgUrl = '';
    $imgError = '';
    // Hostinger nginx ~60s: never wait on HuggingFace/Gemini/OpenAI image (those use 15–180s).
    // Pollinations is URL-only (no download). Anything else → related stock photo so the article is not empty.
    if ($chatUsed) {
        $imgProvider = strtolower((string)($imageVault['provider'] ?? ''));
        if ($imgProvider === 'pollinations' && !empty($imageVault['api_key'])) {
            $thumbPrompt = "Photorealistic editorial photo of the core idea of \"$title\" about $keyword. Real-world scene, natural light, no text, no logos. Landscape blog thumbnail.";
            $imgResult = AIProviderClient::image($imageVault, $thumbPrompt);
            if (!empty($imgResult['success']) && !empty($imgResult['url'])) {
                $featuredImgUrl = $imgResult['url'];
            } else {
                $imgError = $imgResult['error'] ?? 'Image API returned no URL';
            }
        }
        if ($featuredImgUrl === '') {
            $featuredImgUrl = relatedStockPhotoUrl($title, $keyword);
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
    // Build the thumbnail HTML - NO gradient placeholder, always use real image or simple colored div
    $thumbHtml = '';
    $escKw = escapeHtml($keyword);
    if ($chatUsed && $featuredImgUrl) {
        $escImgUrl = escapeHtml($featuredImgUrl);
        $thumbHtml = "<figure class=\"blog-thumbnail\" style=\"margin:0 0 24px 0;border-radius:12px;overflow:hidden;width:100%;\"><img class=\"blog-thumb-img\" data-kw=\"{$escKw}\" src=\"{$escImgUrl}\" alt=\"{$escKw} - Blog Thumbnail\" style=\"width:100%;display:block;object-fit:cover;\" loading=\"eager\"></figure>";
        $chatContent = insertThumbnailAfterH1($chatContent, $thumbHtml);
    }

    // One related photo only on MASTER HTML. Skip a second Image API call (causes 504).

    // Build the full HTML document
    $slug = slugify($title) . '-' . $item['id'];
    ensureDir(OUTPUT_DIR . '/demo');
    $filePath = OUTPUT_DIR . "/demo/$slug.html";
    $htmlUrl = "/published_posts/demo/$slug.html";

    $nowYear = date('Y');
    $dateStr = date('F d, Y');
    $escTitle = escapeHtml($title);

    // === BUILD SHARED ARTICLE CSS (used in both local HTML and Blogger) ===
    $sharedArticleCss = <<<CSS
* { box-sizing: border-box; }
article { font-family: 'Montserrat', -apple-system, sans-serif; line-height: 1.85; color: #334155; max-width: 960px; margin: 0 auto; font-size: 1.02rem; background: #ffffff; padding: 48px; border: 1px solid #e2e8f0; }
.blog-thumbnail { width: 100%; margin: 0 0 24px 0; }
.blog-thumbnail img { width: 100% !important; display: block !important; object-fit: cover !important; border-radius: 12px !important; }
h1 { font-size: 2.2rem; font-weight: 800; color: #0f172a; margin-bottom: 12px; line-height: 1.2; text-align: center; }
h2 { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 36px; margin-bottom: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
h3 { font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-top: 24px; margin-bottom: 12px; }
p { margin-bottom: 18px; text-align: justify; }
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
@media (max-width: 768px) {
    article { padding: 24px 16px; }
    h1 { font-size: 1.6rem; }
    h2 { font-size: 1.25rem; margin-top: 24px; }
    h3 { font-size: 1.05rem; }
    p { font-size: 0.95rem; margin-bottom: 14px; text-align: justify; }
    ul, ol { padding-left: 18px; font-size: 0.95rem; }
    td, th { padding: 8px 10px; font-size: 0.85rem; }
    blockquote { padding: 12px 16px; }
    figure { margin: 16px 0; }
    img { border-radius: 8px; }
}
@media (max-width: 480px) {
    h1 { font-size: 1.4rem; }
    h2 { font-size: 1.15rem; }
    article { padding: 16px 12px; }

}
.thumb-placeholder { aspect-ratio: 9/16; background: linear-gradient(135deg, #1b57f6, #8b5cf6); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.5rem; font-weight: 800; padding: 40px; text-align: center; min-height: 300px; border-radius: 12px; }
CSS;

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
        html { scroll-behavior: smooth; }
        body { font-family: 'Montserrat', sans-serif; max-width: 960px; margin: 0 auto; padding: 36px 20px; color: #0f172a; background: #fafafa; }
        .nav-back { margin-bottom: 24px; display: inline-block; color: #0f172a; font-weight: 800; text-decoration: underline; font-size: 0.9rem; }
        $sharedArticleCss
    </style>
    <script>
        // Image error handler
        document.addEventListener('error', function(e) {
            if (e.target.tagName !== 'IMG') return;
            var img = e.target;
            if (img.classList.contains('blog-thumb-img')) {
                var fig = img.parentElement;
                var kw = img.getAttribute('data-kw') || '';
                var div = document.createElement('div');
                div.className = 'thumb-placeholder';
                div.textContent = kw || 'Blog Thumbnail';
                if (fig) { img.style.display = 'none'; fig.insertBefore(div, img.nextSibling); }
            } else {
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

    $tmpPath = $filePath . '.tmp';
    $written = @file_put_contents($tmpPath, $fullHtml);
    if ($written !== false && is_file($tmpPath) && filesize($tmpPath) > 400) {
        @rename($tmpPath, $filePath);
        $written = is_file($filePath) ? filesize($filePath) : false;
    }
    if ($written === false || !is_file($filePath) || filesize($filePath) < 400) {
        $altDir = dirname(__DIR__) . '/published_posts/demo';
        ensureDir($altDir);
        $altPath = $altDir . "/$slug.html";
        $written = @file_put_contents($altPath, $fullHtml);
        if ($written !== false && is_file($altPath)) {
            $filePath = $altPath;
        } else {
            return [
                'success' => false,
                'html_path' => '',
                'html_file' => '',
                'used_chat_api' => $chatUsed,
                'featured_image' => $featuredImgUrl,
                'error' => 'Could not write HTML file. Check published_posts/ permissions.'
            ];
        }
    }

    return [
        'success' => $wantMaster ? $chatUsed : true,
        'html_path' => $htmlUrl,
        'html_file' => $filePath,
        'used_chat_api' => $chatUsed,
        'featured_image' => $featuredImgUrl,
        'error' => $chatUsed ? '' : ($chatError ?: 'Draft HTML only. Chat did not write master.')
    ];
}


/**
 * Generate fallback HTML article when Chat API is not available.
 */
function generateFallbackArticleHtml($title, $keyword, $h1, $h2s, $h3s, $kws, $links, $ext, $prompts) {
    $escH1 = escapeHtml($h1);
    $escKw = escapeHtml($keyword);
    $dateStr = date('F d, Y');

    $kwNames = [];
    foreach ((array)$kws as $row) {
        $nm = trim((string)($row['keyword'] ?? ''));
        if ($nm !== '') $kwNames[] = $nm;
    }
    if (!$kwNames) $kwNames = [$keyword];
    $primaryName = $kwNames[0];
    $secondaries = array_slice($kwNames, 1, 4);

    $sectionsHtml = '';
    foreach ($h2s as $i => $h2) {
        $escH2 = escapeHtml($h2);
        $focusKw = escapeHtml($secondaries[$i % max(1, count($secondaries))] ?? $primaryName);
        $escPrimary = escapeHtml($primaryName);
        $sectionContent = "<p>" . escapeHtml($h2) . " is a practical part of " . $escPrimary . ". This draft will be replaced by Master HTML from Chat API. Supporting term for this section: <strong>{$focusKw}</strong>.</p>";
        $sectionContent .= "<p>Do not treat this placeholder as the published article. Planner keywords stay in the table below (primary plus secondaries). Chat must write original body copy that uses those terms naturally.</p>";
        if (isset($h3s[$i])) {
            $sectionContent .= "<h3>" . escapeHtml($h3s[$i]) . "</h3><p>Understanding the nuances of " . escapeHtml($h3s[$i]) . " is critical for building a sustainable long-term strategy around $escKw. Use examples from your own pipeline instead of invented statistics.</p>";
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

function validateGeneratedArticleFile($filePath) {
    if (empty($filePath) || !is_file($filePath)) return false;
    $html = @file_get_contents($filePath);
    if ($html === false || strlen($html) < 400) return false;
    $words = str_word_count(strip_tags($html));
    if ($words < 80) return false;
    if (stripos($html, '<h1') === false && stripos($html, '<article') === false) return false;
    return true;
}

function loadCampaignArticleContent($item) {
    $htmlPath = $item['html_path'] ?? '';
    $base = $htmlPath ? basename($htmlPath) : '';
    $outDir = defined('OUTPUT_DIR') ? rtrim(OUTPUT_DIR, '/') : (dirname(__DIR__) . '/published_posts');
    $candidates = [];
    if (function_exists('resolveCampaignHtmlFile')) {
        $resolved = resolveCampaignHtmlFile($htmlPath);
        if ($resolved) $candidates[] = $resolved;
    }
    if ($htmlPath) {
        $rel = ltrim(str_replace('\\', '/', $htmlPath), '/');
        $candidates[] = dirname(__DIR__) . '/' . $rel;
        $candidates[] = $outDir . '/' . $base;
        $candidates[] = $outDir . '/demo/' . $base;
        $candidates[] = dirname(__DIR__) . '/published_posts/demo/' . $base;
        $candidates[] = dirname(__DIR__) . '/published_posts/' . $base;
    }
    foreach ($candidates as $p) {
        if ($p && @is_file($p)) {
            $fullHtml = file_get_contents($p);
            if (preg_match('#<article[^>]*>(.*?)</article>#is', $fullHtml, $artMatch)) {
                return trim($artMatch[1]);
            }
            return $fullHtml;
        }
    }
    return '';
}

function generateArticleHtmlReliable($item, $userId, $activeSlot, $db, $contentAngle = '', $wantMaster = true) {
    try {
        $last = generateArticleHtmlFromCampaignItem($item, $userId, $activeSlot, $db, $contentAngle, $wantMaster);
    } catch (Throwable $e) {
        error_log('[HTML] generate exception: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage(), 'html_path' => '', 'html_file' => ''];
    }
    $file = $last['html_file'] ?? '';
    if ($wantMaster && empty($last['used_chat_api'])) {
        return $last;
    }
    if (!empty($last['success']) && ($file === '' || validateGeneratedArticleFile($file) || (is_file($file) && filesize($file) > 400))) {
        $last['success'] = true;
        return $last;
    }
    if ($file && is_file($file) && filesize($file) > 400) {
        $last['success'] = true;
        return $last;
    }
    return $last;
}

function publishItemToSelectedPlatform($userId, $item, $platform, $scheduledStr = null) {
    $title = $item['title'] ?? 'Untitled';
    $articleContent = loadCampaignArticleContent($item);
    if (trim(strip_tags($articleContent)) === '') {
        return ['success' => false, 'error' => 'HTML file not found or empty. Path: ' . ($item['html_path'] ?? 'none')];
    }
    $platform = $platform ?: ($item['target_platform'] ?? 'blogger');
    if ($platform === 'blogger') {
        $vault = SecurityVault::getApiCredentials($userId, 'blogger_api');
        $blogId = $vault['blogger_blog_id'] ?? '';
        if (empty($blogId)) return ['success' => false, 'error' => 'Blogger Blog ID is missing in Vault.'];
        return Publisher::publishBlogger($userId, $blogId, $title, $articleContent, $vault['client_id'] ?? '', $vault['client_secret'] ?? '', $vault['refresh_token'] ?? '', $scheduledStr);
    }
    if ($platform === 'wordpress') {
        $vault = SecurityVault::getApiCredentials($userId, 'wordpress_api');
        return Publisher::publishWordpress($userId, $vault['wp_site_url'] ?? '', $vault['wp_username'] ?? '', $vault['wp_app_password'] ?? '', $title, $articleContent);
    }
    if ($platform === 'website') {
        if (function_exists('publishToWebsiteBlog')) {
            return publishToWebsiteBlog($item, $title, $articleContent, $scheduledStr);
        }
        $pubFile = dirname(__DIR__) . '/blog/includes/publisher.php';
        $alt = dirname(__DIR__, 2) . '/blog/includes/publisher.php';
        if (file_exists($alt)) require_once $alt;
        elseif (file_exists($pubFile)) require_once $pubFile;
        else return ['success' => false, 'error' => 'Website publisher not found.'];
        if (!class_exists('WebsitePublisher')) return ['success' => false, 'error' => 'WebsitePublisher class missing.'];
        try {
            $wpub = new WebsitePublisher();
            $thumbUrl = '';
            if (preg_match('#<img[^>]+src=["\']([^"\']+)["\']#i', $articleContent, $m)) $thumbUrl = $m[1];
            return $wpub->publish([
                'title' => $title,
                'slug' => slugify($title),
                'content_html' => $articleContent,
                'category' => $item['category'] ?? 'General',
                'tags' => array_values(array_filter([$item['primary_keyword'] ?? '', $item['category'] ?? ''])),
                'thumbnail_url' => $thumbUrl,
                'author' => 'ColorFiind Team',
                'meta_description' => substr(strip_tags($articleContent), 0, 160),
                'meta_keywords' => $item['primary_keyword'] ?? '',
                'scheduled_date' => $scheduledStr,
            ]);
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Website blog publish failed: ' . $e->getMessage()];
        }
    }
    $url = Publisher::publishLocal($userId, $title, slugify($title), $articleContent, 'General', $item['primary_keyword'] ?? '', '');
    return ['success' => true, 'url' => $url, 'message' => 'Published locally.'];
}
