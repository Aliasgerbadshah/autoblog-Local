<?php
/**
 * AutoBlog SaaS - Main Application Router
 * Hostinger Shared Hosting Compatible
 */

// PHP 8.0 polyfills for older PHP versions
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return $needle !== '' && substr($haystack, -strlen($needle)) === $needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && strpos($haystack, $needle) !== false;
    }
}

// Enable error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Catch any fatal errors and display them
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo '<h1>PHP Error</h1><pre>' . htmlspecialchars(print_r($error, true)) . '</pre>';
    }
});

try {
    require_once __DIR__ . '/includes/database.php';
    require_once __DIR__ . '/includes/session.php';
    require_once __DIR__ . '/includes/auth.php';
    require_once __DIR__ . '/includes/helpers.php';
    require_once __DIR__ . '/includes/autoblog_engine.php';
    require_once __DIR__ . '/includes/backlink_engine.php';
    require_once __DIR__ . '/includes/social_engine.php';
    require_once __DIR__ . '/includes/ai_provider.php';
    require_once __DIR__ . '/includes/research_agent.php';
    require_once __DIR__ . '/includes/mailer.php';
} catch (Exception $e) {
    http_response_code(500);
    echo '<h1>AutoBlog Setup Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p><p>Check that all files are uploaded correctly and PHP version is 8.0+</p>';
    exit;
}

startSession();

// Parse the request URI
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestUri = rtrim($requestUri, '/');
if (empty($requestUri)) $requestUri = '/';

// Route the request
if ($requestUri === '/' || $requestUri === '/index.php') {
    if (!isLoggedIn()) { header('Location: /login.php'); exit; }
    $user = ['username' => $_SESSION['username'] ?? '', 'email' => $_SESSION['email'] ?? ''];
    include __DIR__ . '/templates/index.html';
    exit;
}

// API Routes
if (str_starts_with($requestUri, '/api/')) {
    // Content-Type is set per-route: JSON in jsonResponse(), HTML in review/decision routes.
    handleApiRoute($requestUri);
    exit;
}

// Published posts
if (str_starts_with($requestUri, '/published_posts/')) {
    $filename = basename($requestUri);
    $filePath = OUTPUT_DIR . '/' . $filename;
    if (file_exists($filePath)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($filePath);
        exit;
    }
    http_response_code(404);
    echo 'File not found';
    exit;
}

http_response_code(404);
echo 'Page not found';

function handleApiRoute($uri) {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // Auth routes (no login required)
    if ($uri === '/api/auth/register' && $method === 'POST') {
        $username = $input['username'] ?? '';
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        if (!$username || !$email || !$password) {
            jsonResponse(['error' => 'Username, email, and password are required.'], 400);
        }
        $res = SecurityVault::registerUser($username, $email, $password);
        if ($res['success']) {
            jsonResponse(['success' => true, 'message' => 'Registration successful. Please log in.']);
        }
        jsonResponse(['error' => $res['error']], 400);
    }

    if ($uri === '/api/auth/login-request-otp' && $method === 'POST') {
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';
        if (!$username || !$password) {
            jsonResponse(['error' => 'Username/Email and password are required.'], 400);
        }
        $authRes = SecurityVault::authenticateCredentials($username, $password);
        if (!$authRes['success']) {
            jsonResponse(['error' => $authRes['error']], 401);
        }
        $user = $authRes['user'];
        $otpCode = SecurityVault::generateUniqueOtp($user['id']);
        $brevoKey = getenv('BREVO_API_KEY') ?: DEFAULT_BREVO_API_KEY;
        $senderEmail = getenv('BREVO_SENDER_EMAIL') ?: DEFAULT_BREVO_SENDER_EMAIL;
        $otpResult = SecurityVault::sendBrevoOtpEmail($user['email'], $otpCode, $brevoKey, $senderEmail);
        error_log('[OTP Email] ' . json_encode($otpResult));

        $_SESSION['pending_otp_user_id'] = $user['id'];
        $_SESSION['pending_otp_email'] = $user['email'];

        $emailParts = explode('@', $user['email']);
        $maskedEmail = substr($emailParts[0], 0, 2) . '***@' . ($emailParts[1] ?? '');
        jsonResponse([
            'success' => true,
            'otp_required' => true,
            'user_id' => $user['id'],
            'masked_email' => $maskedEmail,
            'message' => "Security OTP code sent to {$user['email']}"
        ]);
    }

    if ($uri === '/api/auth/verify-otp' && $method === 'POST') {
        $userId = $input['user_id'] ?? $_SESSION['pending_otp_user_id'] ?? null;
        $otpCode = $input['otp_code'] ?? '';
        if (!$userId || !$otpCode) {
            jsonResponse(['error' => 'User ID and OTP code are required.'], 400);
        }
        $verifyRes = SecurityVault::verifyUserOtp($userId, $otpCode);
        if ($verifyRes['success']) {
            $db = getDB();
            $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            unset($_SESSION['pending_otp_user_id'], $_SESSION['pending_otp_email']);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['active_slot_id'] = $user['active_slot_id'] ?? 1;
            jsonResponse(['success' => true, 'message' => 'OTP Verified successfully!', 'user' => $user]);
        }
        jsonResponse(['error' => $verifyRes['error']], 400);
    }

    // All routes below require login
    loginRequired();
    $userId = getCurrentUserId();
    $activeSlot = getActiveSlot();

    // Switch workspace slot
    if ($uri === '/api/auth/switch-slot' && $method === 'POST') {
        $slotNumber = intval($input['slot_number'] ?? 1);
        if ($slotNumber < 1 || $slotNumber > 5) {
            jsonResponse(['error' => 'Slot number must be between 1 and 5'], 400);
        }
        SecurityVault::switchActiveSlot($userId, $slotNumber);
        $_SESSION['active_slot_id'] = $slotNumber;
        $slotDetails = SecurityVault::getSlotDetails($userId, $slotNumber);
        jsonResponse(['status' => 'slot switched', 'active_slot' => $slotDetails]);
    }

    // Slot settings
    if ($uri === '/api/auth/slot-settings') {
        if ($method === 'POST') {
            $slotName = $input['slot_name'] ?? "Slot #$activeSlot";
            $domainUrl = $input['domain_url'] ?? '';
            $targetGoal = $input['target_goal'] ?? 'Organic Search Traffic';
            $wordCountTarget = $input['word_count_target'] ?? '1500-2000';
            $destinationPlatform = $input['destination_platform'] ?? 'local';
            SecurityVault::updateWorkspaceSlot($userId, $activeSlot, $slotName, $domainUrl, $targetGoal, $wordCountTarget, $destinationPlatform);
            jsonResponse(['status' => 'slot settings saved successfully']);
        }
        $details = SecurityVault::getSlotDetails($userId, $activeSlot);
        jsonResponse($details);
    }

    // Stats
    if ($uri === '/api/stats') {
        $db = getDB();
        $stmt = $db->prepare('SELECT COUNT(*) FROM posts WHERE user_id = ? AND slot_number = ?');
        $stmt->execute([$userId, $activeSlot]); $totalPosts = $stmt->fetchColumn();
        $stmt = $db->prepare('SELECT COUNT(*) FROM backlinks WHERE user_id = ? AND slot_number = ?');
        $stmt->execute([$userId, $activeSlot]); $totalBacklinks = $stmt->fetchColumn();
        $stmt = $db->prepare('SELECT COUNT(*) FROM backlinks WHERE user_id = ? AND slot_number = ? AND is_found = 1 AND is_dofollow = 1');
        $stmt->execute([$userId, $activeSlot]); $activeDofollow = $stmt->fetchColumn();
        $stmt = $db->prepare('SELECT COUNT(*) FROM scheduled_queue WHERE user_id = ? AND slot_number = ? AND status = \'Scheduled\'');
        $stmt->execute([$userId, $activeSlot]); $scheduledCount = $stmt->fetchColumn();
        $stmt = $db->prepare('SELECT COUNT(*) FROM social_posts_log WHERE user_id = ? AND slot_number = ? AND status = \'Success\'');
        $stmt->execute([$userId, $activeSlot]); $socialShares = $stmt->fetchColumn();
        jsonResponse(['active_slot' => $activeSlot, 'total_posts' => $totalPosts, 'total_backlinks' => $totalBacklinks, 'active_dofollow' => $activeDofollow, 'scheduled_count' => $scheduledCount, 'social_shares' => $socialShares]);
    }

    // Vault credentials
    if ($uri === '/api/vault/credentials') {
        if ($method === 'POST') {
            $serviceName = $input['service_name'] ?? '';
            $accountAlias = $input['account_alias'] ?? 'Primary Account';
            $credentials = $input['credentials'] ?? [];
            if (!$serviceName) jsonResponse(['error' => 'service_name is required'], 400);
            SecurityVault::saveApiCredentials($userId, $serviceName, $credentials, $accountAlias);
            jsonResponse(['status' => 'credentials securely saved in workspace vault']);
        }
        $summary = SecurityVault::getAllUserVaultSummary($userId);
        $services = [];
        foreach (['blogger_api', 'brevo_api', 'wordpress_api', 'dataforseo_api', 'chat_api', 'image_api'] as $s) {
            $services[$s] = SecurityVault::getApiCredentials($userId, $s);
        }
        jsonResponse(['summary' => $summary, 'services' => $services]);
    }

    // Project settings
    if ($uri === '/api/projects/settings') {
        if ($method === 'POST') {
            $db = getDB();
            $fields = ['domain_url','destination_platform','chat_credential_id','image_credential_id','seo_credential_id','blogger_credential_id','target_country','target_region','target_city','target_language'];
            $values = array_map(fn($f) => $input[$f] ?? null, $fields);
            $setClause = implode(', ', array_map(fn($f) => "$f = ?", $fields));
            $stmt = $db->prepare("UPDATE user_workspace_slots SET $setClause WHERE user_id = ? AND slot_number = ?");
            $stmt->execute(array_merge($values, [$userId, $activeSlot]));
            jsonResponse(['success' => true, 'message' => 'Project research and model selections saved.']);
        }
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM user_workspace_slots WHERE user_id = ? AND slot_number = ?');
        $stmt->execute([$userId, $activeSlot]);
        $row = $stmt->fetch();
        jsonResponse($row ?: []);
    }

    // Vault accounts
    if (preg_match('#^/api/vault/accounts/(.+)$#', $uri, $m)) {
        $serviceName = $m[1];
        $allowed = ['chat_api','image_api','dataforseo_api','blogger_api','wordpress_api'];
        if (!in_array($serviceName, $allowed)) jsonResponse(['error' => 'Unsupported service.'], 400);
        $accounts = SecurityVault::getServiceCredentials($userId, $serviceName);
        $result = array_map(fn($x) => ['id' => $x['id'], 'account_alias' => $x['account_alias'], 'provider' => $x['data']['provider'] ?? null, 'model' => $x['data']['model'] ?? null, 'updated_at' => $x['updated_at']], $accounts);
        jsonResponse($result);
    }

    // Delete vault credential
    if (preg_match('#^/api/vault/delete-credential/(\d+)$#', $uri, $m)) {
        $credId = $m[1];
        $db = getDB();
        $stmt = $db->prepare('DELETE FROM user_credentials_vault WHERE id = ? AND user_id = ?');
        $stmt->execute([$credId, $userId]);
        jsonResponse(['status' => 'success', 'message' => 'Vault credential removed.']);
    }

    // Test AI
    if ($uri === '/api/vault/test-ai' && $method === 'POST') {
        $service = $input['service_name'] ?? 'chat_api';
        if (SAFE_TEST_MODE) {
            jsonResponse(['success' => true, 'test_mode' => true, 'message' => 'Safe Test Mode: no Chat or Image API request was made.']);
        }
        $creds = SecurityVault::getApiCredentials($userId, $service);
        if (!empty($input['api_key'])) {
            foreach (['provider','api_key','model','endpoint'] as $k) {
                if (isset($input[$k]) && $input[$k] !== null) $creds[$k] = $input[$k];
            }
        }
        if ($service === 'image_api') {
            $result = AIProviderClient::image($creds, 'A simple neutral test image for API connectivity');
        } else {
            $result = AIProviderClient::chat($creds, 'Reply with exactly: API connection successful');
        }
        jsonResponse($result, $result['success'] ? 200 : 400);
    }

    // Test Blogger — uses OAuth (refresh token → access token → Bearer header)
    if ($uri === '/api/vault/test-blogger' && $method === 'POST') {
        $blogId = trim($input['blogger_blog_id'] ?? '');
        $clientId = trim($input['client_id'] ?? '');
        $clientSecret = trim($input['client_secret'] ?? '');
        $refreshToken = trim($input['refresh_token'] ?? '');
        
        // Fall back to vault if not provided in request
        if (!$blogId || !$clientId || !$clientSecret || !$refreshToken) {
            $bloggerVault = SecurityVault::getApiCredentials($userId, 'blogger_api');
            $blogId = $blogId ?: trim($bloggerVault['blogger_blog_id'] ?? '');
            $clientId = $clientId ?: trim($bloggerVault['client_id'] ?? '');
            $clientSecret = $clientSecret ?: trim($bloggerVault['client_secret'] ?? '');
            $refreshToken = $refreshToken ?: trim($bloggerVault['refresh_token'] ?? '');
        }
        
        if (!$blogId) {
            jsonResponse(['success' => false, 'error' => 'Blogger Blog ID is required.'], 400);
        }
        if (!$clientId || !$clientSecret || !$refreshToken) {
            $missing = [];
            if (!$clientId) $missing[] = 'Client ID';
            if (!$clientSecret) $missing[] = 'Client Secret';
            if (!$refreshToken) $missing[] = 'Refresh Token';
            jsonResponse(['success' => false, 'error' => 'Missing: ' . implode(', ', $missing) . '. All OAuth fields are required.'], 400);
        }
        
        // Step 1: Refresh the access token using OAuth
        $rfRes = BloggerOAuthHelper::refreshAccessToken($clientId, $clientSecret, $refreshToken);
        if (!$rfRes['success']) {
            jsonResponse(['success' => false, 'error' => 'OAuth Token Refresh Failed — ' . ($rfRes['error'] ?? 'Check your Client ID, Client Secret, and Refresh Token.')], 400);
        }
        $accessToken = $rfRes['access_token'];
        
        // Step 2: Use the fresh access token to GET blog info
        $result = curlGet("https://www.googleapis.com/blogger/v3/blogs/" . trim($blogId), ["Authorization: Bearer " . trim($accessToken)], 10);
        $data = $result['json'] ?? [];
        
        if ($result['http_code'] === 200 && !empty($data['name'])) {
            // Save the fresh access token back to vault for next time
            $vault = SecurityVault::getApiCredentials($userId, 'blogger_api');
            if (!empty($vault)) {
                $vault['access_token'] = $accessToken;
                $alias = $vault['account_alias'] ?? 'Primary Blogger Account';
                unset($vault['account_alias']);
                SecurityVault::saveApiCredentials($userId, 'blogger_api', $vault, $alias);
            }
            jsonResponse(['success' => true, 'blog_name' => $data['name'] ?? '', 'url' => $data['url'] ?? '', 'message' => '✅ OAuth connected! Access token refreshed and saved.']);
        }
        
        // Error from Blogger API
        $errorMsg = $data['error']['message'] ?? '';
        $errorReason = $data['error']['errors'][0]['reason'] ?? '';
        if ($errorMsg) {
            $extra = '';
            if ($result['http_code'] === 401) $extra = ' (Access token may be invalid — try getting a new Refresh Token from OAuth Playground)';
            if ($result['http_code'] === 403) $extra = ' (Blogger API may not be enabled, or blog ID is wrong)';
            if ($result['http_code'] === 404) $extra = ' (Blog ID not found — check the Blog ID)';
            jsonResponse(['success' => false, 'error' => "Blogger API Error ({$result['http_code']}): $errorMsg$extra"]);
        }
        jsonResponse(['success' => false, 'error' => "Blogger API returned HTTP {$result['http_code']} with no useful error message."]);
    }

    // Generate article (Chat API + Image API required; DataForSEO not needed)
    if ($uri === '/api/autoblog/generate' && $method === 'POST') {
        $keyword = $input['keyword'] ?? 'Digital Growth Blueprint';
        $category = $input['category'] ?? 'General';
        $targetPlatform = $input['target_platform'] ?? 'blogger';
        $autoShareSocial = $input['auto_share_social'] ?? false;
        $targetLink = $input['target_link'] ?? null;
        $targetAnchor = $input['target_anchor'] ?? null;

        $db = getDB();
        $stmt = $db->prepare('SELECT chat_credential_id, image_credential_id, seo_credential_id, blogger_credential_id FROM user_workspace_slots WHERE user_id = ? AND slot_number = ?');
        $stmt->execute([$userId, $activeSlot]);
        $selection = $stmt->fetch();

        $wpVault = SecurityVault::getApiCredentials($userId, 'wordpress_api');
        $bloggerVault = !empty($selection['blogger_credential_id']) ? SecurityVault::getApiCredentialsById($userId, 'blogger_api', $selection['blogger_credential_id']) : SecurityVault::getApiCredentials($userId, 'blogger_api');
        $chatVault = !empty($selection['chat_credential_id']) ? SecurityVault::getApiCredentialsById($userId, 'chat_api', $selection['chat_credential_id']) : SecurityVault::getApiCredentials($userId, 'chat_api');
        $imageVault = !empty($selection['image_credential_id']) ? SecurityVault::getApiCredentialsById($userId, 'image_api', $selection['image_credential_id']) : SecurityVault::getApiCredentials($userId, 'image_api');

        if (empty($chatVault['api_key'])) {
            jsonResponse(['error' => 'Save a Chat API in API Vault before writing a blog. Image API is optional but recommended.'], 400);
        }

        $researchContext = $input['research_context'] ?? 'Use the approved website research, keyword plan, internal-link map, external sources, FAQ questions, and image requirements.';
        $prompt = "Write a researched HTML blog article of 1,800 to 2,200 words about: $keyword. Category: $category.\n$researchContext\nUse one H1, logical H2-H6 headings, natural internal and external links, a real FAQ section, valid Article and FAQ JSON-LD only when supported, varied relevant image positions with descriptive alt text, and no banned AI words or phrases. Do not invent facts or URLs. CRITICAL: Keep all paragraphs SHORT — strictly 45 to 50 words per <p> tag. Every paragraph must have between 45 and 50 words. Break long paragraphs into multiple short ones. Include 2-3 external links to real, verified authority sites (Wikipedia, official docs, etc). Return only the article HTML.";

        $chatResult = AIProviderClient::chat($chatVault, $prompt);
        if (!$chatResult['success']) {
            jsonResponse(['error' => 'Chat API writing failed: ' . ($chatResult['error'] ?? 'Unknown error')], 400);
        }

        $art = ContentGenerator::generateHumanArticle1000Words($keyword, $category, $targetLink, $targetAnchor, $userId, $activeSlot);
        $art['content'] = AntiAiSanitizer::sanitizeText($chatResult['content']);

        $imageResult = ['success' => false];
        if (!empty($imageVault['api_key'])) {
            $imageResult = AIProviderClient::image($imageVault, "Relevant editorial image for $keyword; monochrome professional photography, no text, no logos.");
        }
        if (!empty($imageResult['success']) && !empty($imageResult['url'])) {
            $art['featured_image'] = $imageResult['url'];
            $art['content'] = '<figure><img src="' . $imageResult['url'] . '" alt="Relevant image for ' . escapeHtml($keyword) . '" loading="eager" style="max-width:100%;height:auto;"></figure>' . $art['content'];
        }

        $results = [];
        $publishedUrl = '';

        if ($targetPlatform === 'blogger') {
            $blogId = $input['blogger_blog_id'] ?? $bloggerVault['blogger_blog_id'] ?? '';
            $clientId = $bloggerVault['client_id'] ?? '';
            $clientSecret = $bloggerVault['client_secret'] ?? '';
            $refreshToken = $bloggerVault['refresh_token'] ?? '';
            $res = Publisher::publishBlogger($userId, $blogId, $art['title'], $art['content'], $clientId, $clientSecret, $refreshToken);
            $results[] = $res;
            if ($res['success']) { $publishedUrl = $res['url'] ?? ''; }
            else { jsonResponse(['error' => $res['error'] ?? 'Blogger publishing failed.', 'results' => $results], 400); }
        } elseif ($targetPlatform === 'wordpress') {
            $wpSite = $input['wp_site'] ?? $wpVault['wp_site_url'] ?? '';
            $wpUser = $input['wp_user'] ?? $wpVault['wp_username'] ?? '';
            $wpPass = $input['wp_pass'] ?? $wpVault['wp_app_password'] ?? '';
            $res = Publisher::publishWordpress($userId, $wpSite, $wpUser, $wpPass, $art['title'], $art['content']);
            $results[] = $res;
            if ($res['success']) { $publishedUrl = $res['url'] ?? ''; }
            else { jsonResponse(['error' => $res['error'] ?? 'WordPress publishing failed.', 'results' => $results], 400); }
        } elseif ($targetPlatform === 'webhook') {
            $webhookUrl = $input['webhook_url'] ?? '';
            $res = Publisher::publishWebhook($userId, $webhookUrl, $art['title'], $art['content'], $category);
            $results[] = $res;
            $publishedUrl = $webhookUrl;
        } else {
            $publishedUrl = Publisher::publishLocal($userId, $art['title'], $art['slug'], $art['content'], $category, $keyword, $art['featured_image']);
            $results[] = ['success' => true, 'url' => $publishedUrl, 'title' => $art['title']];
        }

        if ($autoShareSocial && $publishedUrl) {
            $socialRes = SocialPublisher::broadcastAllSocials($art['title'], $publishedUrl, $category, $art['featured_image'], $userId, $activeSlot);
            $results[] = ['social_shares' => $socialRes];
        }

        jsonResponse(['status' => 'completed', 'results' => $results]);
    }

    // List posts
    if ($uri === '/api/autoblog/posts') {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM posts WHERE user_id = ? AND slot_number = ? ORDER BY id DESC');
        $stmt->execute([$userId, $activeSlot]);
        jsonResponse($stmt->fetchAll());
    }

    // Delete post
    if (preg_match('#^/api/autoblog/delete-post/(\d+)$#', $uri, $m)) {
        $postId = $m[1];
        $db = getDB();
        $stmt = $db->prepare('DELETE FROM posts WHERE id = ? AND user_id = ?');
        $stmt->execute([$postId, $userId]);
        jsonResponse(['status' => 'success', 'message' => 'Article deleted successfully.']);
    }

    // Schedule blog
    if ($uri === '/api/autoblog/schedule' && $method === 'POST') {
        $planId = $input['plan_id'] ?? null;
        $scheduledTime = $input['scheduled_time'] ?? '';
        $keyword = $input['keyword'] ?? '';
        $title = $input['title'] ?? '';
        $targetLink = $input['target_link'] ?? null;
        $targetAnchor = $input['target_anchor'] ?? null;
        $category = $input['category'] ?? 'General';

        if ($planId) {
            $db = getDB();
            $stmt = $db->prepare('SELECT * FROM content_plans WHERE id = ? AND user_id = ? AND slot_number = ?');
            $stmt->execute([$planId, $userId, $activeSlot]);
            $plan = $stmt->fetch();
            if (!$plan) jsonResponse(['error' => 'Content plan not found.'], 404);
            if ($plan['status'] !== 'Approved') jsonResponse(['error' => 'A human must approve the content plan before scheduling.'], 400);
            $keyword = $keyword ?: $plan['primary_keyword'];
            $title = $title ?: $plan['title'];
            $targetLink = $plan['target_link'];
            $targetAnchor = $plan['target_anchor'];
        } else {
            if (!$keyword || !$title) jsonResponse(['error' => 'Use an approved plan or provide title and keyword.'], 400);
        }

        if (!$scheduledTime) jsonResponse(['error' => 'Choose a future schedule date and time.'], 400);
        $when = strtotime($scheduledTime);
        if ($when === false || $when <= time()) jsonResponse(['error' => 'Schedule time must be in the future.'], 400);

        $db = getDB();
        $now = nowString();
        $whenStr = date('Y-m-d H:i:s', $when);
        $stmt = $db->prepare('INSERT INTO scheduled_queue (user_id, slot_number, topic_title, keyword, category, scheduled_time, target_platform, status, created_at, target_link, target_anchor, plan_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $activeSlot, $title, $keyword, $category, $whenStr, $input['target_platform'] ?? 'local', 'Scheduled', $now, $targetLink, $targetAnchor, $planId]);
        $qid = $db->lastInsertId();
        jsonResponse(['success' => true, 'queue_id' => $qid, 'status' => 'Scheduled', 'message' => 'Approved article added to the publishing queue.']);
    }

    // Schedule campaign
    if ($uri === '/api/autoblog/schedule-campaign' && $method === 'POST') {
        $days = intval($input['days'] ?? 7);
        $perDay = intval($input['posts_per_day'] ?? 1);
        $times = $input['times'] ?? [];
        $destination = $input['target_platform'] ?? 'blogger';

        if ($days < 1 || $days > 365) jsonResponse(['error' => 'Choose 1-365 days.'], 400);
        if (!in_array($perDay, [1, 2, 3])) jsonResponse(['error' => 'Posts per day must be 1, 2, or 3.'], 400);
        if (count($times) !== $perDay) jsonResponse(['error' => "Enter exactly $perDay posting time(s)."], 400);

        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM content_plans WHERE user_id = ? AND slot_number = ? AND status = 'Approved' ORDER BY id ASC");
        $stmt->execute([$userId, $activeSlot]);
        $plans = $stmt->fetchAll();
        if (empty($plans)) jsonResponse(['error' => 'No approved roadmap articles are ready. Approve the roadmap first.'], 400);

        $now = new DateTime();
        $created = nowString();
        $count = 0;

        for ($day = 0; $day < $days; $day++) {
            for ($n = 0; $n < $perDay; $n++) {
                $plan = $plans[$count % count($plans)];
                $hhmm = $times[$n] ?? '10:00';
                $parts = explode(':', $hhmm);
                $hh = intval($parts[0] ?? 10);
                $mm = intval($parts[1] ?? 0);

                $when = (clone $now)->modify("+{$day} days");
                $when->setTime($hh, $mm, 0);
                if ($when <= $now) $when->modify('+1 day');

                $whenStr = $when->format('Y-m-d H:i:s');
                $stmt = $db->prepare('INSERT INTO scheduled_queue (user_id, slot_number, topic_title, keyword, category, scheduled_time, target_platform, status, created_at, target_link, target_anchor, plan_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$userId, $activeSlot, $plan['title'], $plan['primary_keyword'], 'Planned Article', $whenStr, $destination, 'Scheduled', $created, $plan['target_link'], $plan['target_anchor'], $plan['id']]);
                $count++;
            }
        }
        jsonResponse(['success' => true, 'scheduled_count' => $count, 'message' => "$count approved article slots scheduled."]);
    }

    // List queue
    if ($uri === '/api/autoblog/queue') {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM scheduled_queue WHERE user_id = ? AND slot_number = ? ORDER BY scheduled_time ASC');
        $stmt->execute([$userId, $activeSlot]);
        jsonResponse($stmt->fetchAll());
    }

    // Cancel queue item
    if (preg_match('#^/api/autoblog/queue/(\d+)$#', $uri, $m)) {
        $queueId = $m[1];
        $db = getDB();
        $stmt = $db->prepare("UPDATE scheduled_queue SET status = 'Cancelled' WHERE id = ? AND user_id = ?");
        $stmt->execute([$queueId, $userId]);
        jsonResponse(['success' => true, 'status' => 'Cancelled']);
    }

    // Backlinks
    if ($uri === '/api/backlinks') {
        if ($method === 'POST') {
            $targetSite = $input['target_site'] ?? 'Target Domain';
            $backlinkUrl = $input['backlink_url'] ?? '';
            $myUrl = $input['my_url'] ?? '';
            $notes = $input['notes'] ?? '';
            if (!$backlinkUrl || !$myUrl) jsonResponse(['error' => 'backlink_url and my_url are required'], 400);
            $res = BacklinkChecker::addAndCheckBacklink($targetSite, $backlinkUrl, $myUrl, $notes);
            jsonResponse(['status' => 'backlink added and checked', 'result' => $res]);
        }
        $allLinks = BacklinkChecker::getAllBacklinks($userId, $activeSlot);
        jsonResponse($allLinks);
    }

    // Backlinks audit
    if ($uri === '/api/backlinks/audit' && $method === 'POST') {
        $updated = BacklinkChecker::auditAll($userId, $activeSlot);
        jsonResponse(['status' => 'audit completed', 'audited_count' => count($updated), 'details' => $updated]);
    }

    // Social accounts
    if ($uri === '/api/social/accounts') {
        if ($method === 'POST') {
            $platform = $input['platform'] ?? 'facebook';
            $accountName = $input['account_name'] ?? 'Main Account';
            $accessToken = $input['access_token'] ?? '';
            $pageIdOrBoardId = $input['page_id_or_board_id'] ?? '';
            $db = getDB();
            $now = nowString();
            $stmt = $db->prepare('INSERT INTO social_accounts (user_id, slot_number, platform, account_name, access_token, page_id_or_board_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $activeSlot, $platform, $accountName, $accessToken, $pageIdOrBoardId, $now]);
            jsonResponse(['status' => 'social account added successfully']);
        }
        $db = getDB();
        $stmt = $db->prepare('SELECT id, platform, account_name, page_id_or_board_id, is_active, created_at FROM social_accounts WHERE user_id = ? AND slot_number = ? ORDER BY id DESC');
        $stmt->execute([$userId, $activeSlot]);
        jsonResponse($stmt->fetchAll());
    }

    // Analyze domain
    if ($uri === '/api/auditor/analyze-domain' && $method === 'POST') {
        $domain = trim($input['domain_url'] ?? '');
        $country = trim($input['country'] ?? 'India');
        $region = trim($input['region'] ?? '');
        $city = trim($input['city'] ?? '');
        $languageCode = $input['language_code'] ?? 'en';
        if (!$domain) jsonResponse(['error' => 'Website URL is required.'], 400);

        $locationCodeMap = ['india' => 2356, 'united states' => 2840, 'usa' => 2840, 'united kingdom' => 2826, 'canada' => 2124, 'australia' => 2036, 'uae' => 2784];
        $locationCode = $locationCodeMap[strtolower($country)] ?? 2356;

        $info = ResearchAgent::analyzeCustomerWebsite($domain);
        $pages = ResearchAgent::crawlAndExtractSitePages($domain, $userId);
        $seedParts = array_merge($info['headings'] ?? [], [$info['title'] ?? '', $info['description'] ?? '']);
        $seed = '';
        foreach ($seedParts as $s) { if (!empty($s)) { $seed = $s; break; } }
        if (empty($seed)) $seed = 'business services';

        $ideas = ResearchAgent::searchKeywordsSerpapi($seed);

        // Also try Chat API for deeper keyword research (optional, not required)
        $chatVault = SecurityVault::getApiCredentials($userId, 'chat_api');
        if (!empty($chatVault['api_key'])) {
            $chatPrompt = "You are an SEO keyword researcher for the website $domain in $country. Given the seed topic \"$seed\", suggest exactly 8 specific long-tail keyword phrases people search for. Return ONLY a JSON array of strings, no other text. Example: [\"keyword one\",\"keyword two\"]";
            $chatResult = AIProviderClient::chat($chatVault, $chatPrompt);
            if (!empty($chatResult['success']) && !empty($chatResult['content'])) {
                $chatRaw = trim($chatResult['content']);
                $chatRaw = str_replace(['```json', '```'], '', $chatRaw);
                $chatKeywords = json_decode($chatRaw, true);
                if (is_array($chatKeywords)) {
                    foreach ($chatKeywords as $ck) {
                        if (is_string($ck) && strlen($ck) > 3) {
                            $ideas['keywords'][] = $ck;
                        }
                    }
                }
            }
        }

        $keywords = array_unique(array_filter(array_merge([$seed], $ideas['keywords'] ?? [], $ideas['questions'] ?? [])));
        $keywords = array_slice(array_values($keywords), 0, 10);

        $db = getDB();
        $now = nowString();

        // DataForSEO
        $stmt = $db->prepare('SELECT seo_credential_id FROM user_workspace_slots WHERE user_id = ? AND slot_number = ?');
        $stmt->execute([$userId, $activeSlot]);
        $seoRow = $stmt->fetch();
        $seoCreds = !empty($seoRow['seo_credential_id']) ? SecurityVault::getApiCredentialsById($userId, 'dataforseo_api', $seoRow['seo_credential_id']) : SecurityVault::getApiCredentials($userId, 'dataforseo_api');
        $labsResult = ['success' => false, 'data' => []];
        if (!empty($seoCreds['login']) && !empty($seoCreds['password'])) {
            $labsResult = DataForSEOClient::labsKeywordOverview($seoCreds, $keywords, $locationCode, $languageCode);
        }

        $metricMap = [];
        try {
            $items = $labsResult['data']['tasks'][0]['result'][0]['items'] ?? [];
            foreach ($items as $item) {
                $metricMap[$item['keyword'] ?? ''] = $item['keyword_info'] ?? [];
            }
        } catch (Exception $e) {}

        $roadmap = [];
        foreach ($keywords as $i => $kw) {
            $page = $pages[$i % count($pages)] ?? ['page_url' => $domain, 'page_title' => 'main service'];
            $metrics = $metricMap[$kw] ?? [];
            $volume = $metrics['search_volume'] ?? null;
            $competition = strval($metrics['competition_level'] ?? 'Research required');
            $difficulty = $metrics['keyword_difficulty'] ?? null;

            $stmt = $db->prepare('INSERT OR IGNORE INTO keyword_research (user_id, slot_number, domain_url, keyword, search_volume, keyword_difficulty, competition, intent, source, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $activeSlot, $domain, $kw, $volume, $difficulty, $competition, 'Informational / commercial', $labsResult['success'] ? 'DataForSEO Labs' : 'Website + Google', $now]);

            $stmt = $db->prepare("SELECT id FROM content_plans WHERE user_id = ? AND slot_number = ? AND primary_keyword = ? AND status IN ('Planned','Approved') ORDER BY id DESC LIMIT 1");
            $stmt->execute([$userId, $activeSlot, $kw]);
            $existingPlan = $stmt->fetch();
            $planId = $existingPlan ? $existingPlan['id'] : null;

            if (!$planId) {
                $stmt = $db->prepare('INSERT INTO content_plans (user_id, slot_number, title, primary_keyword, supporting_keywords, target_link, target_anchor, external_sources, image_plan, video_needed, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$userId, $activeSlot, ucwords($kw), $kw, '[]', $page['page_url'], $page['page_title'], '[]', '{"count":"2-4 relevant images","alt_text":true}', 0, 'Planned', $now]);
                $planId = $db->lastInsertId();
            }

            $roadmap[] = ['day' => $i + 1, 'topic' => ucwords($kw), 'keyword' => $kw, 'search_volume' => $volume, 'difficulty' => $difficulty, 'competition' => $competition, 'target_link' => $page['page_url'], 'target_anchor' => $page['page_title'], 'plan_id' => $planId];
        }

        jsonResponse(['status' => 'success', 'domain_info' => $info, 'pages' => $pages, 'location' => ['country' => $country, 'region' => $region, 'city' => $city, 'language_code' => $languageCode, 'location_code' => $locationCode], 'suggested_roadmap' => $roadmap, 'message' => 'Roadmap saved.']);
    }

    // AI Research Roadmap (uses Chat API for research; DataForSEO optional)
    if ($uri === '/api/research/ai-roadmap' && $method === 'POST') {
        $domain = trim($input['domain_url'] ?? '');
        $country = $input['country'] ?? 'India';
        $language = $input['language_code'] ?? 'en';
        $days = intval($input['days'] ?? 7);
        $postsPerDay = intval($input['posts_per_day'] ?? 1);
        if (!$domain) jsonResponse(['error' => 'Website URL is required.'], 400);

        $pages = ResearchAgent::crawlAndExtractSitePages($domain, $userId);
        $info = ResearchAgent::analyzeCustomerWebsite($domain);
        $chat = SecurityVault::getApiCredentials($userId, 'chat_api');
        if (empty($chat['api_key'])) jsonResponse(['error' => 'Save and select a Chat API before live research.'], 400);

        $pageContext = implode("\n", array_map(fn($p) => "URL: {$p['page_url']} | Page topic: {$p['page_title']}", array_slice($pages, 0, 100)));
        $prompt = "You are an SEO research strategist. Research the current web for the business website $domain in target country $country, language $language. Create exactly " . ($days * $postsPerDay) . " article plans for a $days-day campaign with $postsPerDay article(s) per day. Return ONLY valid JSON with this shape: {\"articles\":[{\"title\":\"...\",\"primary_keyword\":\"...\",\"keywords\":[{\"keyword\":\"...\",\"volume\":\"AI estimate\",\"difficulty\":\"Low/Medium/High\",\"intent\":\"...\"}],\"internal_links\":[{\"url\":\"...\",\"anchor_text\":\"...\",\"reason\":\"...\"}],\"external_links\":[{\"url\":\"...\",\"anchor_text\":\"...\",\"reason\":\"...\"}],\"headings\":{\"H1\":\"...\",\"H2\":[\"...\"],\"H3\":[\"...\"]},\"image_prompts\":[\"...\"]}]}. IMPORTANT for external_links: Provide 2-3 REAL working URLs to well-known authority sites (Wikipedia, Google docs, Mozilla MDN, Schema.org, Moz, etc.) that are RELATED to each article topic. Do NOT invent or guess URLs — only use URLs you are certain exist. Also include 1-2 of the client's crawled pages in internal_links. Crawled pages:\n$pageContext";

        $result = AIProviderClient::chat($chat, $prompt);
        if (!$result['success']) jsonResponse(['error' => 'Chat research failed: ' . ($result['error'] ?? 'Unknown error')], 400);

        $raw = trim($result['content']);
        $raw = str_replace(['```json', '```'], '', $raw);
        $plans = json_decode(trim($raw), true)['articles'] ?? [];
        if (empty($plans)) jsonResponse(['error' => 'Chat API did not return valid JSON roadmap.', 'raw_preview' => substr($raw, 0, 1000)], 400);

        $db = getDB();
        $now = nowString();
        // CLEAN UP: Delete old unapproved items, keep approved/published
        $db->exec("DELETE FROM campaign_items WHERE user_id = $userId AND plan_status NOT IN ('Approved','Provisional Approved') AND article_status NOT IN ('HTML Ready','Final Article Approved','Published','Scheduled') AND campaign_id IN (SELECT id FROM campaigns WHERE user_id = $userId AND status != 'Archived')");
        $db->exec("DELETE FROM approval_tokens WHERE user_id = $userId AND campaign_item_id NOT IN (SELECT id FROM campaign_items)");
        $db->exec("UPDATE campaigns SET status = 'Archived' WHERE user_id = $userId AND status = 'Roadmap Review' AND id NOT IN (SELECT DISTINCT campaign_id FROM campaign_items WHERE plan_status IN ('Approved','Provisional Approved') OR article_status IN ('HTML Ready','Final Article Approved','Published'))");

        $startDate = $input['start_date'] ?? date('Y-m-d');
        $postingTimes = $input['posting_times'] ?? ['10:00'];
        $targetPlatform = $input['target_platform'] ?? 'blogger';

        $stmt = $db->prepare('INSERT INTO campaigns (user_id, slot_number, domain_url, target_country, language_code, days, posts_per_day, status, start_date, posting_times, target_platform, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $activeSlot, $domain, $country, $language, $days, $postsPerDay, 'Roadmap Review', $startDate, json_encode($postingTimes), $targetPlatform, $now]);
        $campaignId = $db->lastInsertId();

        $roadmapRows = [];
        foreach (array_slice($plans, 0, $days * $postsPerDay) as $i => $plan) {
            $kws = $plan['keywords'] ?? [];
            $links = $plan['internal_links'] ?? [];
            $ext = $plan['external_links'] ?? [];
            $heads = $plan['headings'] ?? [];
            $prompts = $plan['image_prompts'] ?? [];

            $dayNum = intval($i / $postsPerDay) + 1;
            $postNum = ($i % $postsPerDay) + 1;
            $schedDate = (new DateTime($startDate))->modify(($dayNum - 1) . ' days')->format('Y-m-d');
            $schedTime = $postingTimes[min($postNum - 1, count($postingTimes) - 1)] ?? '10:00';

            $stmt = $db->prepare('INSERT INTO campaign_items (campaign_id, day_number, post_number, title, primary_keyword, keyword_data, internal_links, external_links, headings, image_prompts, video_url, plan_status, article_status, scheduled_date, scheduled_time, target_platform, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$campaignId, $dayNum, $postNum, $plan['title'] ?? 'Untitled', $plan['primary_keyword'] ?? '', json_encode($kws), json_encode($links), json_encode($ext), json_encode($heads), json_encode($prompts), $plan['video_url'] ?? '', 'Pending', 'Not Created', $schedDate, $schedTime, $targetPlatform, $now]);
            $itemId = $db->lastInsertId();

            $token = generateToken();
            $stmt = $db->prepare('INSERT INTO approval_tokens (user_id, campaign_item_id, approval_type, token, created_at) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $itemId, 'roadmap', $token, $now]);

            $roadmapRows[] = ['plan_id' => $itemId, 'day' => intval($i / $postsPerDay) + 1, 'topic' => $plan['title'] ?? '', 'keyword' => $plan['primary_keyword'] ?? '', 'competition' => 'AI researched', 'target_link' => '', 'target_anchor' => 'See approved research in email'];
        }

        // Build and send rich approval email with full draft content
        $stmt = $db->prepare('SELECT * FROM campaign_items WHERE campaign_id = ? ORDER BY day_number, post_number');
        $stmt->execute([$campaignId]);
        $allCampaignItems = $stmt->fetchAll();
        $campaignRow = ['domain_url' => $domain, 'days' => $days, 'posts_per_day' => $postsPerDay];
        $richEmailHtml = buildRichApprovalEmailHtml($allCampaignItems, $campaignRow, $db);
        $sent = sendApprovalEmail($userId, 'Your AI Research Roadmap Draft — Approve or Disapprove Each Article', $richEmailHtml);
        jsonResponse(['success' => true, 'campaign_id' => $campaignId, 'articles' => count($roadmapRows), 'email_sent' => $sent, 'suggested_roadmap' => $roadmapRows, 'message' => $sent ? 'Live Chat research roadmap created and approval email sent with full draft content.' : 'Live Chat research roadmap created. Brevo email was not sent.']);
    }

    // SEO Research
    if ($uri === '/api/seo/research' && $method === 'POST') {
        if (!DATAFORSEO_ENABLED) jsonResponse(['success' => false, 'disabled' => true, 'error' => 'DataForSEO is disabled.'], 400);
        if (SAFE_TEST_MODE) jsonResponse(['success' => true, 'test_mode' => true, 'message' => 'Safe Test Mode: no DataForSEO request was made.']);
        $keywords = array_filter($input['keywords'] ?? []);
        if (empty($keywords)) jsonResponse(['error' => 'Provide at least one keyword.'], 400);
        $creds = SecurityVault::getApiCredentials($userId, 'dataforseo_api');
        if (!empty($input['login']) && !empty($input['password'])) {
            $creds = ['login' => $input['login'], 'password' => $input['password']];
        }
        $volume = DataForSEOClient::keywordVolume($creds, $keywords, $input['location_code'] ?? 2356, $input['language_code'] ?? 'en');
        jsonResponse(['success' => $volume['success'] ?? false, 'volume' => $volume, 'message' => 'DataForSEO research completed or returned a provider error.']);
    }

    // Demo campaign
    if ($uri === '/api/demo/campaign' && $method === 'POST') {
        $domain = trim($input['domain_url'] ?? '');
        $country = $input['country'] ?? 'India';
        $language = $input['language_code'] ?? 'en';
        $days = intval($input['days'] ?? 7);
        $perDay = intval($input['posts_per_day'] ?? 2);
        if (!$domain) jsonResponse(['error' => 'Website URL is required.'], 400);
        if ($days < 1 || $days > 30 || !in_array($perDay, [1, 2, 3])) jsonResponse(['error' => 'Demo mode supports 1-30 days and 1-3 posts per day.'], 400);

        $info = ResearchAgent::analyzeCustomerWebsite($domain);
        $pages = ResearchAgent::crawlAndExtractSitePages($domain, $userId);
        $pageTopics = array_filter(array_map(fn($p) => trim($p['page_title'] ?? ''), $pages));
        $seed = !empty($pageTopics) ? $pageTopics[0] : ($info['title'] ?? 'Customer business');
        $seed = trim($seed);

        $base = [];
        foreach (array_slice($pageTopics, 0, 12) as $topic) {
            $clean = preg_replace('/\s+/', ' ', $topic);
            if (strlen($clean) > 4 && !in_array(strtolower($clean), array_map('strtolower', $base))) $base[] = $clean;
        }
        if (empty($base)) $base = [$seed];
        // Add current year/month to ALL base topics so they're always fresh
        $nowYear = date('Y');
        $nowMonth = date('F');
        $base = array_merge($base, [
            "$seed strategies $nowMonth $nowYear",
            "best $seed solutions $nowYear",
            "$seed complete guide $nowYear",
            "$seed tips and tricks $nowMonth $nowYear",
            "$seed common mistakes to avoid $nowYear"
        ]);

        // Avoid duplicate topics: check ALL sources — database + persistent JSON file
        // Normalize domain for matching: strip protocol, www, trailing slash
        $domainNorm = preg_replace('#^(https?://)?(www\.)?#', '', strtolower(rtrim($domain, '/')));
        $db = getDB();
        $usedTopics = [];
        
        // SOURCE 1: Persistent JSON file (survives redeployment!)
        $topicFilePath = dirname(__DIR__) . '/data/used_topics.json';
        if (file_exists($topicFilePath)) {
            $topicFile = json_decode(file_get_contents($topicFilePath), true);
            if (!empty($topicFile['topics'])) {
                foreach ($topicFile['topics'] as $t) {
                    $tDomain = preg_replace('#^(https?://)?(www\.)?#', '', strtolower(rtrim($t['domain'] ?? '', '/')));
                    if ($tDomain === $domainNorm || empty($tDomain)) {
                        $usedTopics[strtolower(trim($t['topic']))] = true;
                        $usedTopics[strtolower(trim($t['keyword']))] = true;
                    }
                }
            }
        }
        
        // SOURCE 2: Database created_blog_topics table
        $stmt = $db->prepare('SELECT title, primary_keyword, domain_url FROM created_blog_topics WHERE user_id = ?');
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $ut) {
            $utDomain = preg_replace('#^(https?://)?(www\.)?#', '', strtolower(rtrim($ut['domain_url'] ?? '', '/')));
            if ($utDomain === $domainNorm || empty($utDomain)) {
                $usedTopics[strtolower(trim($ut['title']))] = true;
                $usedTopics[strtolower(trim($ut['primary_keyword']))] = true;
            }
        }
        // SOURCE 3: ALL campaign_items for this user
        $stmt = $db->prepare('SELECT ci.title, ci.primary_keyword, c.domain_url FROM campaign_items ci JOIN campaigns c ON c.id = ci.campaign_id WHERE c.user_id = ?');
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $ut) {
            $utDomain = preg_replace('#^(https?://)?(www\.)?#', '', strtolower(rtrim($ut['domain_url'] ?? '', '/')));
            if ($utDomain === $domainNorm || empty($utDomain)) {
                $usedTopics[strtolower(trim($ut['title']))] = true;
                $usedTopics[strtolower(trim($ut['primary_keyword']))] = true;
            }
        }
        // Filter out already-used topics (using levenshtein + substring matching)
        $nowYear = date('Y');
        $nowMonth = date('F');
        $freshTopics = [];
        foreach ($base as $b) {
            $bLower = strtolower(trim($b));
            $isUsed = false;
            foreach ($usedTopics as $used => $_) {
                // Skip if either string is empty or too short for meaningful comparison
                if (strlen($bLower) < 3 || strlen($used) < 3) continue;
                // Match if very similar (levenshtein < 12% of longer string) OR one contains the other
                $maxLen = max(strlen($bLower), strlen($used));
                $levThreshold = max(8, (int)($maxLen * 0.12));
                if (levenshtein($bLower, $used) < $levThreshold || strpos($used, $bLower) !== false || strpos($bLower, $used) !== false) {
                    $isUsed = true;
                    break;
                }
            }
            if (!$isUsed) {
                $freshTopics[] = $b;
            }
        }
        // If we filtered too many, add trending/current topics with unique markers
        $trendCounter = 0;
        if (count($freshTopics) < $days * $perDay) {
            $trendingAdditions = [
                "$seed trends $nowMonth $nowYear",
                "$seed latest updates $nowYear",
                "$seed new strategies $nowYear",
                "how $seed is evolving in $nowYear",
                "$seed best practices $nowMonth $nowYear",
                "$seed innovations $nowYear",
                "top $seed tips for $nowMonth $nowYear",
                "$seed future outlook $nowYear",
                "$seed case studies $nowYear",
                "$seed comparison guide $nowYear",
                "$seed vs alternatives $nowYear",
                "why $seed matters in $nowYear"
            ];
            foreach ($trendingAdditions as $ta) {
                $taLower = strtolower(trim($ta));
                $isUsed = false;
                foreach ($usedTopics as $used => $_) {
                    if (strlen($taLower) < 3 || strlen($used) < 3) continue;
                    $maxLen = max(strlen($taLower), strlen($used));
                    $levThreshold = max(8, (int)($maxLen * 0.12));
                    if (levenshtein($taLower, $used) < $levThreshold || strpos($used, $taLower) !== false || strpos($taLower, $used) !== false) {
                        $isUsed = true; break;
                    }
                }
                // Also check it's not already in freshTopics (exact or very similar)
                if (!$isUsed) {
                    foreach ($freshTopics as $ft) {
                        $ftLower = strtolower(trim($ft));
                        if (levenshtein($taLower, $ftLower) < 8 || strpos($ftLower, $taLower) !== false || strpos($taLower, $ftLower) !== false) {
                            $isUsed = true; break;
                        }
                    }
                }
                if (!$isUsed) {
                    $freshTopics[] = $ta;
                }
            }
        }
        if (!empty($freshTopics)) {
            $base = $freshTopics;
        }

        $db = getDB();
        $now = nowString();
        // CLEAN UP: Delete old unapproved items from previous campaigns
        // Keep approved/published items, delete everything else from old campaigns
        $db->exec("DELETE FROM campaign_items WHERE user_id = $userId AND plan_status NOT IN ('Approved','Provisional Approved') AND article_status NOT IN ('HTML Ready','Final Article Approved','Published','Scheduled') AND campaign_id IN (SELECT id FROM campaigns WHERE user_id = $userId AND status != 'Archived')");
        // Delete orphaned approval tokens for removed items
        $db->exec("DELETE FROM approval_tokens WHERE user_id = $userId AND campaign_item_id NOT IN (SELECT id FROM campaign_items)");
        // Archive old campaigns that now have no items
        $db->exec("UPDATE campaigns SET status = 'Archived' WHERE user_id = $userId AND status = 'Roadmap Review' AND id NOT IN (SELECT DISTINCT campaign_id FROM campaign_items WHERE plan_status IN ('Approved','Provisional Approved') OR article_status IN ('HTML Ready','Final Article Approved','Published'))");

        $startDate = $input['start_date'] ?? date('Y-m-d');
        $postingTimes = $input['posting_times'] ?? ['10:00'];
        $targetPlatform = $input['target_platform'] ?? 'blogger';

        $stmt = $db->prepare('INSERT INTO campaigns (user_id, slot_number, domain_url, target_country, language_code, days, posts_per_day, status, start_date, posting_times, target_platform, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $activeSlot, $domain, $country, $language, $days, $perDay, 'Roadmap Review', $startDate, json_encode($postingTimes), $targetPlatform, $now]);
        $campaignId = $db->lastInsertId();

        for ($day = 1; $day <= $days; $day++) {
            for ($post = 1; $post <= $perDay; $post++) {
                $idx = ($day - 1) * $perDay + ($post - 1);
                $kw = $base[$idx % count($base)];
                $page = $pages[$idx % count($pages)] ?? ['page_url' => $domain, 'page_title' => 'relevant service page'];
                $articleKeywords = array_slice(array_merge(array_slice($base, $idx % count($base)), array_slice($base, 0, $idx % count($base))), 0, 8);

                $kws = [];
                foreach ($articleKeywords as $j => $k) {
                    $kws[] = ['keyword' => $k, 'volume' => 'Demo estimate: ' . max(90, 1200 - $j * 130), 'difficulty' => ['Low', 'Medium', 'High'][$j % 3], 'intent' => $j % 2 ? 'Commercial' : 'Informational'];
                }
                $headings = ['H1' => ucwords($kw), 'H2' => ['Overview and practical use', 'What competitors miss', 'How to choose the right option', 'Frequently Asked Questions'], 'H3' => ['Costs and examples', 'Common mistakes', 'Implementation steps'], 'H4' => ['Checklist', 'Useful references']];
                $relatedPages = [];
                for ($offset = 0; $offset < min(3, count($pages)); $offset++) {
                    $candidate = $pages[($idx + $offset) % count($pages)];
                    if (!in_array($candidate['page_url'], array_column($relatedPages, 'page_url'))) $relatedPages[] = $candidate;
                }
                $internal = array_map(fn($x) => ['url' => $x['page_url'], 'anchor_text' => $x['page_title'] ?: 'related website page'], $relatedPages) ?: [['url' => $domain, 'anchor_text' => 'customer website']];
                // External links: guaranteed-working authority URLs + topic-relevant ones
                $externalBase = [
                    ['url' => 'https://en.wikipedia.org/wiki/Search_engine_optimization', 'anchor_text' => 'Wikipedia: Search Engine Optimization'],
                    ['url' => 'https://developers.google.com/search/docs/fundamentals/seo-starter-guide', 'anchor_text' => 'Google SEO Starter Guide'],
                    ['url' => 'https://moz.com/beginners-guide-to-seo', 'anchor_text' => 'Moz Beginner Guide to SEO'],
                    ['url' => 'https://schema.org/Article', 'anchor_text' => 'Schema.org Article Structured Data'],
                    ['url' => 'https://www.nngroup.com/articles/', 'anchor_text' => 'Nielsen Norman Group UX Research'],
                ];
                // Add topic-relevant external links based on keyword
                $kwLower = strtolower($kw);
                if (strpos($kwLower, 'marketing') !== false || strpos($kwLower, 'seo') !== false || strpos($kwLower, 'digital') !== false) {
                    $externalBase[] = ['url' => 'https://ahrefs.com/blog/', 'anchor_text' => 'Ahrefs SEO Blog'];
                    $externalBase[] = ['url' => 'https://searchengineland.com/', 'anchor_text' => 'Search Engine Land'];
                } elseif (strpos($kwLower, 'web') !== false || strpos($kwLower, 'design') !== false || strpos($kwLower, 'develop') !== false) {
                    $externalBase[] = ['url' => 'https://developer.mozilla.org/en-US/docs/Learn', 'anchor_text' => 'MDN Web Docs: Learn Web Development'];
                    $externalBase[] = ['url' => 'https://www.w3.org/WAI/standards-guidelines/', 'anchor_text' => 'W3C Web Accessibility Standards'];
                } elseif (strpos($kwLower, 'ai') !== false || strpos($kwLower, 'machine') !== false || strpos($kwLower, 'automat') !== false) {
                    $externalBase[] = ['url' => 'https://platform.openai.com/docs', 'anchor_text' => 'OpenAI API Documentation'];
                    $externalBase[] = ['url' => 'https://cloud.google.com/ai-platform', 'anchor_text' => 'Google Cloud AI Platform'];
                } else {
                    $externalBase[] = ['url' => 'https://www.hbr.org/', 'anchor_text' => 'Harvard Business Review'];
                    $externalBase[] = ['url' => 'https://www.youtube.com/', 'anchor_text' => 'YouTube'];
                }
                $external = $externalBase;
                $prompts = ["Editorial photograph illustrating $kw, natural lighting, no text, no logos, professional magazine style.", "Practical real-world scene related to $kw, authentic people and setting, no text or logos."];

                $schedDate = (new DateTime($startDate))->modify(($day - 1) . ' days')->format('Y-m-d');
                $schedTime = $postingTimes[min($post - 1, count($postingTimes) - 1)] ?? '10:00';

                $stmt = $db->prepare('INSERT INTO campaign_items (campaign_id, day_number, post_number, title, primary_keyword, keyword_data, internal_links, external_links, headings, image_prompts, video_url, plan_status, article_status, scheduled_date, scheduled_time, target_platform, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$campaignId, $day, $post, ucwords($kw), $kw, json_encode($kws), json_encode($internal), json_encode($external), json_encode($headings), json_encode($prompts), '', 'Pending', 'Not Created', $schedDate, $schedTime, $targetPlatform, $now]);
                $itemId = $db->lastInsertId();
                // Track this topic to avoid duplicates in future campaigns
                $stmt = $db->prepare('INSERT OR IGNORE INTO created_blog_topics (user_id, campaign_id, title, primary_keyword, domain_url, created_at) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$userId, $campaignId, ucwords($kw), $kw, $domain, $now]);
                // ALSO save to persistent JSON file (survives redeployment!)
                $topicFilePath = dirname(__DIR__) . '/data/used_topics.json';
                $topicFileData = file_exists($topicFilePath) ? json_decode(file_get_contents($topicFilePath), true) : ['topics' => []];
                if (!is_array($topicFileData)) $topicFileData = ['topics' => []];
                if (!isset($topicFileData['topics'])) $topicFileData['topics'] = [];
                $topicFileData['topics'][] = ['topic' => ucwords($kw), 'keyword' => $kw, 'domain' => $domain, 'date' => $now, 'status' => 'pending', 'user_id' => $userId, 'campaign_id' => $campaignId];
                $topicFileData['_last_updated'] = date('Y-m-d H:i:s');
                file_put_contents($topicFilePath, json_encode($topicFileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $token = generateToken();
                $stmt = $db->prepare('INSERT INTO approval_tokens (user_id, campaign_item_id, approval_type, token, created_at) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$userId, $itemId, 'roadmap', $token, $now]);
            }
        }

        // Build and send rich approval email with full draft content
        $stmt = $db->prepare('SELECT * FROM campaign_items WHERE campaign_id = ? ORDER BY day_number, post_number');
        $stmt->execute([$campaignId]);
        $allItems = $stmt->fetchAll();
        $campaignRow = ['domain_url' => $domain, 'days' => $days, 'posts_per_day' => $perDay];
        $richEmailHtml = buildRichApprovalEmailHtml($allItems, $campaignRow, $db);
        $sent = sendApprovalEmail($userId, 'Your AutoBlog Roadmap Draft — Approve or Disapprove Each Article', $richEmailHtml);
        jsonResponse(['success' => true, 'campaign_id' => $campaignId, 'items' => $days * $perDay, 'email_sent' => $sent, 'base_url' => APP_BASE_URL, 'message' => $sent ? 'Roadmap created and Brevo approval email sent with full draft content.' : 'Roadmap created locally. Brevo email was not sent.']);
    }

    // Demo campaign status
    if ($uri === '/api/demo/campaign-status') {
        $db = getDB();
        // Get the LATEST campaign for pending items
        $stmt = $db->prepare('SELECT id, domain_url, days, posts_per_day, status FROM campaigns WHERE user_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$userId]);
        $latestCampaign = $stmt->fetch();
        
        $allRows = [];
        
        if ($latestCampaign) {
            // Get pending items from ONLY the latest campaign (exclude cancelled/rejected)
            $stmt = $db->prepare('SELECT id, day_number, post_number, title, primary_keyword, plan_status, article_status, html_path, scheduled_date, scheduled_time, target_platform, campaign_id FROM campaign_items WHERE campaign_id = ? AND plan_status NOT IN ("Provisional Disapproved","Rejected","Replacement Pending") ORDER BY day_number, post_number');
            $stmt->execute([$latestCampaign['id']]);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$row) {
                $stmt2 = $db->prepare("SELECT token FROM approval_tokens WHERE campaign_item_id = ? AND approval_type = 'roadmap' AND decision IN ('Pending','Provisional') ORDER BY id DESC LIMIT 1");
                $stmt2->execute([$row['id']]);
                $tok = $stmt2->fetch();
                $row['approval_token'] = $tok ? $tok['token'] : '';
                $stmt3 = $db->prepare("SELECT token FROM approval_tokens WHERE campaign_item_id = ? AND approval_type = 'html' AND decision = 'Pending' ORDER BY id DESC LIMIT 1");
                $stmt3->execute([$row['id']]);
                $htmlTok = $stmt3->fetch();
                $row['html_approval_token'] = $htmlTok ? $htmlTok['token'] : '';
                $row['campaign_domain'] = $latestCampaign['domain_url'] ?? '';
            }
            $allRows = $rows;
        }
        
        // Also get PUBLISHED items from ALL campaigns (so user can see what's live)
        $stmt = $db->prepare('SELECT ci.id, ci.day_number, ci.post_number, ci.title, ci.primary_keyword, ci.plan_status, ci.article_status, ci.html_path, ci.scheduled_date, ci.scheduled_time, ci.target_platform, ci.campaign_id FROM campaign_items ci JOIN campaigns c ON c.id = ci.campaign_id WHERE c.user_id = ? AND ci.article_status = "Published" ORDER BY ci.id DESC');
        $stmt->execute([$userId]);
        $publishedRows = $stmt->fetchAll();
        
        // Merge: remove duplicates (published items that are also in latest campaign)
        $latestIds = array_column($allRows, 'id');
        foreach ($publishedRows as $pr) {
            if (!in_array($pr['id'], $latestIds)) {
                $pr['approval_token'] = '';
                $pr['html_approval_token'] = '';
                $pr['campaign_domain'] = '';
                $allRows[] = $pr;
            }
        }
        
        jsonResponse(['campaign' => $latestCampaign, 'items' => $allRows]);
    }

    // ========== PUBLISH NOW — Immediately publish an approved HTML article to Blogger ==========
    if ($uri === '/api/publish-now' && $method === 'POST') {
        $itemId = intval($input['item_id'] ?? 0);
        if (!$itemId) jsonResponse(['success' => false, 'error' => 'Item ID required.'], 400);
        
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if (!$item) jsonResponse(['success' => false, 'error' => 'Item not found.'], 404);
        
        if ($item['article_status'] !== 'Final Article Approved' && $item['article_status'] !== 'HTML Ready') {
            jsonResponse(['success' => false, 'error' => 'Article must be HTML Ready or Final Approved before publishing. Current: ' . $item['article_status']], 400);
        }
        
        // Get the campaign for platform info
        $stmt = $db->prepare('SELECT * FROM campaigns WHERE id = ?');
        $stmt->execute([$item['campaign_id']]);
        $camp = $stmt->fetch();
        $platform = $input['platform'] ?? ($item['target_platform'] ?? ($camp['target_platform'] ?? 'blogger'));
        
        // Load the HTML file
        $htmlFilePath = null;
        if (!empty($item['html_path'])) {
            $pathPatterns = [
                dirname(__DIR__) . ltrim($item['html_path'], '/'),
                OUTPUT_DIR . '/../' . ltrim($item['html_path'], '/'),
                OUTPUT_DIR . '/demo/' . basename($item['html_path']),
                dirname(__DIR__) . '/published_posts/demo/' . basename($item['html_path']),
            ];
            foreach ($pathPatterns as $p) {
                if (file_exists($p)) { $htmlFilePath = $p; break; }
            }
        }
        
        $articleContent = '';
        $bloggerReadyContent = '';  // Version with embedded CSS for Blogger
        if ($htmlFilePath) {
            $fullHtml = file_get_contents($htmlFilePath);
            // For Blogger: extract <style> from <head> + <article> content
            // This makes the blog look same-to-same on Blogger
            $styleBlock = '';
            if (preg_match('#<style[^>]*>(.*?)</style>#is', $fullHtml, $styleMatch)) {
                $styleBlock = '<style>' . $styleMatch[1] . '</style>';
            }
            $scriptBlock = '';
            if (preg_match('#<script[^>]*>(.*?)</script>#is', $fullHtml, $scriptMatch)) {
                $scriptBlock = '<script>' . $scriptMatch[1] . '</script>';
            }
            // Extract article content
            $articleBody = '';
            if (preg_match('#<article[^>]*>(.*?)</article>#is', $fullHtml, $artMatch)) {
                $articleBody = trim($artMatch[1]);
            } elseif (preg_match('#<body[^>]*>(.*?)</body>#is', $fullHtml, $bodyMatch)) {
                $articleBody = trim($bodyMatch[1]);
            } else {
                $articleBody = $fullHtml;
            }
            // For Blogger: embed CSS + article content (Blogger renders this as the post body)
            $bloggerReadyContent = $styleBlock . "\n<article>\n" . $articleBody . "\n</article>\n" . $scriptBlock;
            // Also set articleContent for non-Blogger platforms
            $articleContent = $articleBody;
        }
        
        if (empty($articleContent)) {
            jsonResponse(['success' => false, 'error' => 'HTML file not found or empty. Path: ' . ($item['html_path'] ?? 'none')], 400);
        }
        
        $title = $item['title'];
        $result = null;
        
        if ($platform === 'blogger') {
            $vault = SecurityVault::getApiCredentials($userId, 'blogger_api');
            $blogId = $vault['blogger_blog_id'] ?? '';
            $clientId = $vault['client_id'] ?? '';
            $clientSecret = $vault['client_secret'] ?? '';
            $refreshToken = $vault['refresh_token'] ?? '';
            
            if (empty($blogId)) jsonResponse(['success' => false, 'error' => 'Blogger Blog ID is missing. Save it in the Vault first.'], 400);
            
            // Use Blogger-ready content (with embedded CSS) so blog looks same-to-same
            $contentForBlogger = !empty($bloggerReadyContent) ? $bloggerReadyContent : $articleContent;
            $result = Publisher::publishBlogger($userId, $blogId, $title, $contentForBlogger, $clientId, $clientSecret, $refreshToken);
        } elseif ($platform === 'wordpress') {
            $vault = SecurityVault::getApiCredentials($userId, 'wordpress_api');
            $result = Publisher::publishWordpress($userId, $vault['wp_site_url'] ?? '', $vault['wp_username'] ?? '', $vault['wp_app_password'] ?? '', $title, $articleContent);
        } else {
            Publisher::publishLocal($userId, $title, slugify($title), $articleContent, 'General', $item['primary_keyword'] ?? '', '');
            $result = ['success' => true, 'url' => '/published_posts/' . slugify($title) . '.html', 'message' => 'Published locally.'];
        }
        
        if ($result && !empty($result['success'])) {
            // Update item status
            $stmt = $db->prepare("UPDATE campaign_items SET article_status = 'Published' WHERE id = ?");
            $stmt->execute([$itemId]);
            // Also update scheduled_queue if exists
            $stmt = $db->prepare("UPDATE scheduled_queue SET status = 'Published' WHERE topic_title = ? AND user_id = ?");
            $stmt->execute([$title, $userId]);
            jsonResponse(['success' => true, 'url' => $result['url'] ?? '', 'message' => $result['message'] ?? 'Published successfully!']);
        }
        
        jsonResponse(['success' => false, 'error' => $result['error'] ?? 'Publishing failed. Check Vault credentials.'], 400);
    }

    // ========== SCHEDULE POST — Add to scheduled_queue for cron, or schedule in Blogger ==========
    if ($uri === '/api/schedule-post' && $method === 'POST') {
        $itemId = intval($input['item_id'] ?? 0);
        $schedDate = $input['scheduled_date'] ?? '';
        $schedTime = $input['scheduled_time'] ?? '';
        $platform = $input['platform'] ?? '';
        
        if (!$itemId) jsonResponse(['success' => false, 'error' => 'Item ID required.'], 400);
        if (!$schedDate || !$schedTime) jsonResponse(['success' => false, 'error' => 'Scheduled date and time required.'], 400);
        
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if (!$item) jsonResponse(['success' => false, 'error' => 'Item not found.'], 404);
        
        if ($item['article_status'] !== 'Final Article Approved' && $item['article_status'] !== 'HTML Ready') {
            jsonResponse(['success' => false, 'error' => 'Article must be HTML Ready or Final Approved. Current: ' . $item['article_status']], 400);
        }
        
        // Get campaign for platform
        $stmt = $db->prepare('SELECT * FROM campaigns WHERE id = ?');
        $stmt->execute([$item['campaign_id']]);
        $camp = $stmt->fetch();
        if (empty($platform)) $platform = $item['target_platform'] ?? ($camp['target_platform'] ?? 'blogger');
        
        // Build scheduled datetime
        $parts = explode(':', $schedTime);
        $scheduledDate = new DateTime($schedDate);
        $scheduledDate->setTime(intval($parts[0] ?? 10), intval($parts[1] ?? 0), 0);
        $scheduledStr = $scheduledDate->format('Y-m-d H:i:s');
        
        // If scheduled time is in the past, publish immediately instead
        $now = new DateTime();
        $publishNow = ($scheduledDate <= $now);
        
        if ($publishNow) {
            // Time is now or past — publish immediately
            $htmlFilePath = null;
            if (!empty($item['html_path'])) {
                $pathPatterns = [
                    dirname(__DIR__) . ltrim($item['html_path'], '/'),
                    OUTPUT_DIR . '/../' . ltrim($item['html_path'], '/'),
                    OUTPUT_DIR . '/demo/' . basename($item['html_path']),
                    dirname(__DIR__) . '/published_posts/demo/' . basename($item['html_path']),
                ];
                foreach ($pathPatterns as $p) { if (file_exists($p)) { $htmlFilePath = $p; break; } }
            }
            $articleContent = '';
            $bloggerReadyContent = '';
            if ($htmlFilePath) {
                $fullHtml = file_get_contents($htmlFilePath);
                // Extract style + article for Blogger (same-to-same look)
                $styleBlock = '';
                if (preg_match('#<style[^>]*>(.*?)</style>#is', $fullHtml, $styleMatch)) {
                    $styleBlock = '<style>' . $styleMatch[1] . '</style>';
                }
                $scriptBlock = '';
                if (preg_match('#<script[^>]*>(.*?)</script>#is', $fullHtml, $scriptMatch)) {
                    $scriptBlock = '<script>' . $scriptMatch[1] . '</script>';
                }
                $articleBody = '';
                if (preg_match('#<article[^>]*>(.*?)</article>#is', $fullHtml, $artMatch)) {
                    $articleBody = trim($artMatch[1]);
                } elseif (preg_match('#<body[^>]*>(.*?)</body>#is', $fullHtml, $bodyMatch)) {
                    $articleBody = trim($bodyMatch[1]);
                } else { $articleBody = $fullHtml; }
                $bloggerReadyContent = $styleBlock . "\n<article>\n" . $articleBody . "\n</article>\n" . $scriptBlock;
                $articleContent = $articleBody;
            }
            if (empty($articleContent)) jsonResponse(['success' => false, 'error' => 'HTML file not found.'], 400);
            
            $title = $item['title'];
            $result = null;
            if ($platform === 'blogger') {
                $vault = SecurityVault::getApiCredentials($userId, 'blogger_api');
                $blogId = $vault['blogger_blog_id'] ?? '';
                $clientId = $vault['client_id'] ?? '';
                $clientSecret = $vault['client_secret'] ?? '';
                $refreshToken = $vault['refresh_token'] ?? '';
                if (empty($blogId)) jsonResponse(['success' => false, 'error' => 'Blogger Blog ID missing in Vault.'], 400);
                $contentForBlogger = !empty($bloggerReadyContent) ? $bloggerReadyContent : $articleContent;
                $result = Publisher::publishBlogger($userId, $blogId, $title, $contentForBlogger, $clientId, $clientSecret, $refreshToken);
            } elseif ($platform === 'wordpress') {
                $vault = SecurityVault::getApiCredentials($userId, 'wordpress_api');
                $result = Publisher::publishWordpress($userId, $vault['wp_site_url'] ?? '', $vault['wp_username'] ?? '', $vault['wp_app_password'] ?? '', $title, $articleContent);
            } else {
                Publisher::publishLocal($userId, $title, slugify($title), $articleContent, 'General', $item['primary_keyword'] ?? '', '');
                $result = ['success' => true, 'url' => '/published_posts/' . slugify($title) . '.html'];
            }
            if ($result && !empty($result['success'])) {
                $stmt = $db->prepare("UPDATE campaign_items SET article_status = 'Published' WHERE id = ?");
                $stmt->execute([$itemId]);
                jsonResponse(['success' => true, 'url' => $result['url'] ?? '', 'message' => 'Published immediately (scheduled time was in the past).']);
            }
            jsonResponse(['success' => false, 'error' => $result['error'] ?? 'Publishing failed.'], 400);
        }
        
        // Future schedule — use Blogger's built-in scheduler (no cron needed!)
        if ($platform === 'blogger') {
            // Load HTML content for Blogger scheduling
            $htmlFilePath = null;
            if (!empty($item['html_path'])) {
                $pathPatterns = [
                    dirname(__DIR__) . ltrim($item['html_path'], '/'),
                    OUTPUT_DIR . '/../' . ltrim($item['html_path'], '/'),
                    OUTPUT_DIR . '/demo/' . basename($item['html_path']),
                    dirname(__DIR__) . '/published_posts/demo/' . basename($item['html_path']),
                ];
                foreach ($pathPatterns as $p) { if (file_exists($p)) { $htmlFilePath = $p; break; } }
            }
            $articleContent = '';
            $bloggerReadyContent = '';
            if ($htmlFilePath) {
                $fullHtml = file_get_contents($htmlFilePath);
                $styleBlock = '';
                if (preg_match('#<style[^>]*>(.*?)</style>#is', $fullHtml, $styleMatch)) {
                    $styleBlock = '<style>' . $styleMatch[1] . '</style>';
                }
                $scriptBlock = '';
                if (preg_match('#<script[^>]*>(.*?)</script>#is', $fullHtml, $scriptMatch)) {
                    $scriptBlock = '<script>' . $scriptMatch[1] . '</script>';
                }
                $articleBody = '';
                if (preg_match('#<article[^>]*>(.*?)</article>#is', $fullHtml, $artMatch)) {
                    $articleBody = trim($artMatch[1]);
                } elseif (preg_match('#<body[^>]*>(.*?)</body>#is', $fullHtml, $bodyMatch)) {
                    $articleBody = trim($bodyMatch[1]);
                } else { $articleBody = $fullHtml; }
                $bloggerReadyContent = $styleBlock . "\n<article>\n" . $articleBody . "\n</article>\n" . $scriptBlock;
                $articleContent = $articleBody;
            }
            if (empty($articleContent)) jsonResponse(['success' => false, 'error' => 'HTML file not found.'], 400);

            $vault = SecurityVault::getApiCredentials($userId, 'blogger_api');
            $blogId = $vault['blogger_blog_id'] ?? '';
            $clientId = $vault['client_id'] ?? '';
            $clientSecret = $vault['client_secret'] ?? '';
            $refreshToken = $vault['refresh_token'] ?? '';
            if (empty($blogId)) jsonResponse(['success' => false, 'error' => 'Blogger Blog ID missing in Vault.'], 400);

            // Convert scheduled date to RFC 3339 format (required by Blogger API)
            $rfc3339Date = $scheduledDate->format('Y-m-d\TH:i:sP');

            $contentForBlogger = !empty($bloggerReadyContent) ? $bloggerReadyContent : $articleContent;
            $result = Publisher::publishBlogger($userId, $blogId, $item['title'], $contentForBlogger, $clientId, $clientSecret, $refreshToken, $rfc3339Date);
            if ($result && !empty($result['success'])) {
                $stmt = $db->prepare("UPDATE campaign_items SET article_status = 'Scheduled', scheduled_date = ?, scheduled_time = ? WHERE id = ?");
                $stmt->execute([$schedDate, $schedTime, $itemId]);
                // Also track in scheduled_queue for dashboard visibility
                $stmt = $db->prepare("SELECT id FROM scheduled_queue WHERE topic_title = ? AND user_id = ? AND status = 'Scheduled'");
                $stmt->execute([$item['title'], $userId]);
                $existing = $stmt->fetch();
                if (!$existing) {
                    $nowS = nowString();
                    $stmt = $db->prepare('INSERT INTO scheduled_queue (user_id, slot_number, topic_title, keyword, category, scheduled_time, target_platform, status, created_at, target_link, target_anchor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$userId, $activeSlot, $item['title'], $item['primary_keyword'] ?? '', 'Approved Article', $scheduledStr, $platform, 'Scheduled', $nowS, '', $item['primary_keyword'] ?? '']);
                }
                jsonResponse(['success' => true, 'url' => $result['url'] ?? '', 'message' => "Scheduled on Blogger for $scheduledStr. Blogger will auto-publish at that time — no cron needed!"]);
            }
            jsonResponse(['success' => false, 'error' => $result['error'] ?? 'Blogger scheduling failed.'], 400);
        }

        // For non-Blogger platforms, add to scheduled_queue (cron-based)
        $stmt = $db->prepare("SELECT id FROM scheduled_queue WHERE topic_title = ? AND user_id = ? AND status = 'Scheduled'");
        $stmt->execute([$item['title'], $userId]);
        $existing = $stmt->fetch();
        if ($existing) {
            $stmt = $db->prepare("UPDATE scheduled_queue SET scheduled_time = ?, target_platform = ? WHERE id = ?");
            $stmt->execute([$scheduledStr, $platform, $existing['id']]);
        } else {
            $nowS = nowString();
            $stmt = $db->prepare('INSERT INTO scheduled_queue (user_id, slot_number, topic_title, keyword, category, scheduled_time, target_platform, status, created_at, target_link, target_anchor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $activeSlot, $item['title'], $item['primary_keyword'] ?? '', 'Approved Article', $scheduledStr, $platform, 'Scheduled', $nowS, '', $item['primary_keyword'] ?? '']);
        }
        
        // Update item status  
        $stmt = $db->prepare("UPDATE campaign_items SET article_status = 'Final Article Approved', scheduled_date = ?, scheduled_time = ? WHERE id = ?");
        $stmt->execute([$schedDate, $schedTime, $itemId]);
        
        jsonResponse(['success' => true, 'message' => "Scheduled for $scheduledStr on $platform. " . ($platform === 'blogger' ? 'Blogger will auto-publish — no cron needed!' : 'Cron will publish at that time.')]);
    }

    // ========== CRON TEST — Check if cron/scheduler is working ==========
    // ========== TOPIC HISTORY — Export/Import persistent topic file ==========
    if ($uri === '/api/topic-history' && $method === 'GET') {
        $topicFilePath = dirname(__DIR__) . '/data/used_topics.json';
        $topicFileData = file_exists($topicFilePath) ? json_decode(file_get_contents($topicFilePath), true) : ['topics' => []];
        // Also merge with database topics
        $db = getDB();
        $stmt = $db->prepare('SELECT title, primary_keyword, domain_url, created_at FROM created_blog_topics WHERE user_id = ?');
        $stmt->execute([$userId]);
        $dbTopics = $stmt->fetchAll();
        jsonResponse(['success' => true, 'file_topics' => count($topicFileData['topics'] ?? []), 'db_topics' => count($dbTopics), 'topics' => $topicFileData['topics'] ?? [], 'db_rows' => $dbTopics]);
    }
    if ($uri === '/api/topic-history/import' && $method === 'POST') {
        $importTopics = $input['topics'] ?? [];
        if (empty($importTopics)) jsonResponse(['success' => false, 'error' => 'No topics array provided.'], 400);
        $topicFilePath = dirname(__DIR__) . '/data/used_topics.json';
        $topicFileData = file_exists($topicFilePath) ? json_decode(file_get_contents($topicFilePath), true) : ['topics' => []];
        if (!isset($topicFileData['topics'])) $topicFileData['topics'] = [];
        $added = 0;
        $existingKeys = [];
        foreach ($topicFileData['topics'] as $et) { $existingKeys[strtolower(trim($et['topic'] ?? ''))] = true; }
        foreach ($importTopics as $t) {
            $key = strtolower(trim($t['topic'] ?? ''));
            if (!empty($key) && !isset($existingKeys[$key])) {
                $topicFileData['topics'][] = $t;
                $existingKeys[$key] = true;
                $added++;
            }
        }
        $topicFileData['_last_updated'] = date('Y-m-d H:i:s');
        file_put_contents($topicFilePath, json_encode($topicFileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        jsonResponse(['success' => true, 'added' => $added, 'total' => count($topicFileData['topics']), 'message' => "Imported $added new topics. Total: " . count($topicFileData['topics'])]);
    }
    if ($uri === '/api/topic-history/sync-db' && $method === 'POST') {
        // Sync all database topics into the persistent JSON file
        $db = getDB();
        $stmt = $db->prepare('SELECT cbt.title, cbt.primary_keyword, cbt.domain_url, cbt.created_at, cbt.user_id, cbt.campaign_id, ci.plan_status FROM created_blog_topics cbt LEFT JOIN campaign_items ci ON ci.campaign_id = cbt.campaign_id AND ci.primary_keyword = cbt.primary_keyword WHERE cbt.user_id = ?');
        $stmt->execute([$userId]);
        $dbTopics = $stmt->fetchAll();
        $topicFilePath = dirname(__DIR__) . '/data/used_topics.json';
        $topicFileData = file_exists($topicFilePath) ? json_decode(file_get_contents($topicFilePath), true) : ['topics' => []];
        if (!isset($topicFileData['topics'])) $topicFileData['topics'] = [];
        $existingKeys = [];
        foreach ($topicFileData['topics'] as $et) { $existingKeys[strtolower(trim($et['topic'] ?? ''))] = true; }
        $added = 0;
        foreach ($dbTopics as $t) {
            $key = strtolower(trim($t['title'] ?? ''));
            if (!empty($key) && !isset($existingKeys[$key])) {
                $topicFileData['topics'][] = ['topic' => $t['title'], 'keyword' => $t['primary_keyword'], 'domain' => $t['domain_url'] ?? '', 'date' => $t['created_at'] ?? '', 'status' => strtolower($t['plan_status'] ?? 'pending'), 'user_id' => $t['user_id'], 'campaign_id' => $t['campaign_id']];
                $existingKeys[$key] = true;
                $added++;
            }
        }
        $topicFileData['_last_updated'] = date('Y-m-d H:i:s');
        file_put_contents($topicFilePath, json_encode($topicFileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        jsonResponse(['success' => true, 'added' => $added, 'total_in_file' => count($topicFileData['topics']), 'total_in_db' => count($dbTopics), 'message' => "Synced $added new topics from database to file. File total: " . count($topicFileData['topics'])]);
    }

    // ========== DELETE ITEM — Remove a campaign item ==========
    if ($uri === '/api/delete-item' && $method === 'POST') {
        $itemId = intval($input['item_id'] ?? 0);
        if (!$itemId) jsonResponse(['success' => false, 'error' => 'Item ID required.'], 400);
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if (!$item) jsonResponse(['success' => false, 'error' => 'Item not found.'], 404);
        // Don't allow deleting published items
        if ($item['article_status'] === 'Published') jsonResponse(['success' => false, 'error' => 'Cannot delete a published item.'], 400);
        // Delete approval tokens, scheduled queue entries, then the item
        $stmt = $db->prepare('DELETE FROM approval_tokens WHERE campaign_item_id = ?');
        $stmt->execute([$itemId]);
        $stmt = $db->prepare("DELETE FROM scheduled_queue WHERE topic_title = ? AND user_id = ?");
        $stmt->execute([$item['title'], $userId]);
        $stmt = $db->prepare('DELETE FROM campaign_items WHERE id = ?');
        $stmt->execute([$itemId]);
        // Also remove from topic history file
        $topicFilePath = dirname(__DIR__) . '/data/used_topics.json';
        if (file_exists($topicFilePath)) {
            $topicFileData = json_decode(file_get_contents($topicFilePath), true);
            if (!empty($topicFileData['topics'])) {
                $titleLower = strtolower(trim($item['title']));
                $topicFileData['topics'] = array_values(array_filter($topicFileData['topics'], function($t) use ($titleLower) {
                    return strtolower(trim($t['topic'] ?? '')) !== $titleLower;
                }));
                $topicFileData['_last_updated'] = date('Y-m-d H:i:s');
                file_put_contents($topicFilePath, json_encode($topicFileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
        jsonResponse(['success' => true, 'message' => 'Item deleted.']);
    }

    // ========== DOWNLOAD HTML — Download the HTML file for an item ==========
    if (preg_match('#^/api/download-html/(\d+)$#', $uri, $m) && $method === 'GET') {
        $itemId = intval($m[1]);
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if (!$item) jsonResponse(['success' => false, 'error' => 'Item not found.'], 404);
        if (empty($item['html_path'])) jsonResponse(['success' => false, 'error' => 'No HTML generated yet.'], 400);
        
        // Find the HTML file
        $htmlFilePath = null;
        $pathPatterns = [
            dirname(__DIR__) . ltrim($item['html_path'], '/'),
            OUTPUT_DIR . '/../' . ltrim($item['html_path'], '/'),
            OUTPUT_DIR . '/demo/' . basename($item['html_path']),
            dirname(__DIR__) . '/published_posts/demo/' . basename($item['html_path']),
        ];
        foreach ($pathPatterns as $p) { if (file_exists($p)) { $htmlFilePath = $p; break; } }
        
        if (!$htmlFilePath) jsonResponse(['success' => false, 'error' => 'HTML file not found on disk.'], 404);
        
        // Send as downloadable file
        $filename = slugify($item['title']) . '.html';
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($htmlFilePath));
        readfile($htmlFilePath);
        exit;
    }

    if ($uri === '/api/cron-test' && $method === 'GET') {
        $db = getDB();
        // Check scheduled_queue
        $stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='Scheduled' THEN 1 ELSE 0 END) as scheduled, SUM(CASE WHEN status='Published' THEN 1 ELSE 0 END) as published, SUM(CASE WHEN status='Failed' THEN 1 ELSE 0 END) as failed FROM scheduled_queue WHERE user_id = ?");
        $stmt->execute([$userId]);
        $queueStats = $stmt->fetch();
        // Check last cron run (look for a marker file)
        $cronMarker = dirname(__DIR__) . '/cron/.last_run';
        $lastCronRun = file_exists($cronMarker) ? file_get_contents($cronMarker) : 'Never';
        // Try to run scheduler directly and capture output
        $schedulerPath = dirname(__DIR__) . '/cron/scheduler.php';
        $schedulerOk = file_exists($schedulerPath);
        jsonResponse([
            'success' => true,
            'queue' => $queueStats,
            'last_cron_run' => $lastCronRun,
            'scheduler_file_exists' => $schedulerOk,
            'scheduler_path' => $schedulerPath,
            'php_sapi' => php_sapi_name(),
            'cron_command' => '*/5 * * * * php /home/u783910899/public_html/sub_apps/cron/scheduler.php',
            'recommendation' => 'If scheduled > 0 but nothing is publishing, set up the cron job above in Hostinger Cron Jobs panel. Or use the Schedule button (uses Blogger built-in scheduler — no cron needed!).'
        ]);
    }

    // Demo review page (HTML)
    if (preg_match('#^/api/demo/review/(\d+)$#', $uri, $m)) {
        header('Content-Type: text/html; charset=utf-8');
        $campaignId = $m[1];
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM campaigns WHERE id = ? AND user_id = ?');
        $stmt->execute([$campaignId, $userId]);
        $campaign = $stmt->fetch();
        if (!$campaign) { echo '<h2>Campaign not found.</h2>'; exit; }

        $stmt = $db->prepare('SELECT * FROM campaign_items WHERE campaign_id = ? ORDER BY day_number, post_number');
        $stmt->execute([$campaignId]);
        $items = $stmt->fetchAll();

        $cards = '';
        foreach ($items as $item) {
            $kws = json_decode($item['keyword_data'] ?? '[]', true);
            $links = json_decode($item['internal_links'] ?? '[]', true);
            $ext = json_decode($item['external_links'] ?? '[]', true);
            $heads = json_decode($item['headings'] ?? '{}', true);
            $prompts = json_decode($item['image_prompts'] ?? '[]', true);

            $stmt2 = $db->prepare("SELECT token FROM approval_tokens WHERE campaign_item_id = ? AND approval_type = 'roadmap' AND decision IN ('Pending','Provisional') ORDER BY id DESC LIMIT 1");
            $stmt2->execute([$item['id']]);
            $tok = $stmt2->fetch();
            $token = $tok ? $tok['token'] : '';

            $kwRows = '';
            foreach ($kws as $x) {
                $kwRows .= '<tr><td>' . escapeHtml($x['keyword'] ?? '') . '</td><td>' . escapeHtml($x['volume'] ?? '') . '</td><td>' . escapeHtml($x['difficulty'] ?? '') . '</td><td>' . escapeHtml($x['intent'] ?? '') . '</td></tr>';
            }
            $internalHtml = '';
            foreach ($links as $x) {
                $internalHtml .= '<li><a href="' . escapeHtml($x['url'] ?? '') . '">' . escapeHtml($x['anchor_text'] ?? '') . '</a></li>';
            }
            $externalHtml = '';
            foreach ($ext as $x) {
                $externalHtml .= '<li><a href="' . escapeHtml($x['url'] ?? '') . '">' . escapeHtml($x['anchor_text'] ?? '') . '</a></li>';
            }
            $promptsHtml = implode('<br>', array_map('escapeHtml', $prompts));

            $cards .= "<section class=\"card\" id=\"item-{$item['id']}\">
                <div class=\"meta\">Day {$item['day_number']} &middot; Blog {$item['post_number']} &middot; <span class=\"status\">" . escapeHtml($item['plan_status']) . "</span></div>
                <h2>" . escapeHtml($item['title']) . "</h2>
                <p><b>Primary keyword:</b> " . escapeHtml($item['primary_keyword']) . "</p>
                <table><tr><th>Keyword</th><th>Volume</th><th>Difficulty</th><th>Intent</th></tr>$kwRows</table>
                <h3>Internal links</h3><ul>$internalHtml</ul>
                <h3>External references</h3><ul>$externalHtml</ul>
                <h3>Heading plan</h3><p><b>H1:</b> " . escapeHtml($heads['H1'] ?? $item['title']) . "</p><p><b>H2:</b> " . escapeHtml(implode(' | ', $heads['H2'] ?? [])) . "</p>
                <h3>Image prompts</h3><p>$promptsHtml</p>
                <p><button class=\"yes\" onclick=\"decide('$token','approve',this)\">APPROVE</button><button class=\"no\" onclick=\"decide('$token','reject',this)\">DISAPPROVE</button></p>
            </section>";
        }

        echo "<!doctype html><html><head><meta charset=\"utf-8\"><title>Roadmap Review</title><style>body{font-family:Arial;background:#f4f6fa;color:#172033;padding:24px}.card{background:#fff;padding:24px;margin:20px 0;border-radius:14px;box-shadow:0 3px 15px #dbe2ec}.meta{color:#64748b;font-weight:bold}table{width:100%;border-collapse:collapse}td,th{border:1px solid #dbe2ec;padding:8px;text-align:left}th{background:#eef2f7}a{color:#0f172a;font-weight:bold}.yes,.no{border:0;border-radius:7px;color:#fff;padding:12px 18px;font-weight:bold;margin-right:8px;cursor:pointer}.yes{background:#10b981}.no{background:#ef4444}.done{opacity:.55}</style><script>async function decide(token,decision,button){if(!token){alert('This item has no active approval token.');return;}button.disabled=true;const r=await fetch('/api/demo/decision/'+token+'/'+decision,{method:'POST'});if(r.ok){button.closest('.card').classList.add('done');button.closest('.card').querySelector('.status').innerText=decision==='approve'?'Approved':'Replacement Pending';}else{button.disabled=false;alert('This approval link is expired or already finalized.');}}</script></head><body><div style=\"max-width:1000px;margin:auto\"><h1>Roadmap Review</h1>$cards</div></body></html>";
        exit;
    }

    // Demo decision (can return HTML redirect or plain HTML)
    if (preg_match('#^/api/demo/decision/([^/]+)/([^/]+)$#', $uri, $m)) {
        $token = $m[1];
        $decision = $m[2];
        if (!in_array($decision, ['approve', 'reject'])) jsonResponse(['error' => 'Invalid decision.'], 400);

        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM approval_tokens WHERE token = ? AND approval_type = 'roadmap' AND decision IN ('Pending','Provisional')");
        $stmt->execute([$token]);
        $tok = $stmt->fetch();
        if (!$tok) { header('Content-Type: text/html; charset=utf-8'); echo '<h2>This approval link has already been finalized or is invalid.</h2>'; exit; }

        $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
        $stmt->execute([$tok['campaign_item_id']]);
        $item = $stmt->fetch();

        // Get the campaign this item belongs to (ANY campaign, not just latest)
        $stmt = $db->prepare('SELECT * FROM campaigns WHERE id = ?');
        $stmt->execute([$item['campaign_id']]);
        $active = $stmt->fetch();
        if (!$item || !$active) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<h2>This approval link is invalid or the campaign no longer exists.</h2>';
            exit;
        }

        $now = nowString();

        // SINGLE-CLICK DECISION: First click is provisional. If same click again, finalize.
        // If no 2nd click within APPROVAL_WINDOW_MINUTES, cron auto-finalizes.
        $clickCount = intval($tok['click_count'] ?? 0);

        if ($clickCount === 0) {
            // First click: mark as provisional
            $stmt = $db->prepare("UPDATE approval_tokens SET decision = 'Provisional', first_decision = ?, first_clicked_at = ?, click_count = 1 WHERE id = ?");
            $stmt->execute([$decision, $now, $tok['id']]);

            if ($decision === 'approve') {
                $stmt = $db->prepare('UPDATE campaign_items SET plan_status = ? WHERE id = ?');
                $stmt->execute(['Provisional Approved', $item['id']]);
                // Immediately finalize approval on 1st click (no waiting for 2nd click)
                $stmt = $db->prepare("UPDATE approval_tokens SET decision = 'Approved', click_count = 2 WHERE id = ?");
                $stmt->execute([$tok['id']]);
                $stmt = $db->prepare("UPDATE campaign_items SET plan_status = 'Approved' WHERE id = ?");
                $stmt->execute([$item['id']]);
                // Update persistent topic file
                updateTopicStatusInFile($item['title'], 'approved');

                // Auto-generate HTML article
                $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
                $stmt->execute([$item['id']]);
                $freshItem = $stmt->fetch();
                $htmlResult = generateArticleHtmlFromCampaignItem($freshItem, $tok['user_id'], $activeSlot, $db);
                if (!empty($htmlResult['success'])) {
                    $stmt = $db->prepare("UPDATE campaign_items SET article_status = 'HTML Ready', html_path = ? WHERE id = ?");
                    $stmt->execute([$htmlResult['html_path'], $item['id']]);
                    $htmlToken = generateToken();
                    $stmt = $db->prepare('INSERT INTO approval_tokens (user_id, campaign_item_id, approval_type, token, created_at) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$tok['user_id'], $item['id'], 'html', $htmlToken, $now]);
                    $previewEmailHtml = buildHtmlPreviewEmailHtml($freshItem, $htmlResult['html_path'], $htmlToken, $htmlResult['used_chat_api']);
                    sendApprovalEmail($tok['user_id'], 'Blog HTML Preview - ' . escapeHtml($freshItem['title']), $previewEmailHtml);
                }
                header('Location: ' . APP_BASE_URL . '/api/demo/approval-result?status=approved');
            } else {
                // Disapprove: IMMEDIATELY create replacement (don't wait)
                $stmt = $db->prepare('UPDATE campaign_items SET plan_status = ? WHERE id = ?');
                $stmt->execute(['Provisional Disapproved', $item['id']]);
                updateTopicStatusInFile($item['title'], 'rejected');

                $newToken = generateToken();
                $newTitle = $item['title'] . ' — New Research Angle';
                $newKeyword = $item['primary_keyword'] . ' alternatives';
                $oldKws = json_decode($item['keyword_data'] ?? '[]', true);
                $newKws = array_map(fn($x) => ['keyword' => $newKeyword, 'volume' => $x['volume'] ?? 'Demo estimate', 'difficulty' => $x['difficulty'] ?? 'Research required', 'intent' => $x['intent'] ?? 'Informational'], $oldKws);
                $newHeadings = ['H1' => $newTitle, 'H2' => ['A different practical angle', 'What the evidence shows', 'How to apply the advice', 'Frequently Asked Questions'], 'H3' => ['Examples and comparisons', 'Common mistakes', 'Practical checklist']];
                $newPrompts = ["Editorial image illustrating $newKeyword, natural light, no text or logos.", "Practical real-world scene related to $newKeyword."];

                // Finalize rejection
                $stmt = $db->prepare("UPDATE approval_tokens SET decision = 'Rejected', click_count = 2 WHERE id = ?");
                $stmt->execute([$tok['id']]);

                $stmt = $db->prepare("UPDATE campaign_items SET title = ?, primary_keyword = ?, keyword_data = ?, headings = ?, image_prompts = ?, plan_status = 'Replacement Pending', article_status = 'Not Created' WHERE id = ?");
                $stmt->execute([$newTitle, $newKeyword, json_encode($newKws), json_encode($newHeadings), json_encode($newPrompts), $item['id']]);
                $stmt = $db->prepare('INSERT INTO approval_tokens (user_id, campaign_item_id, approval_type, token, created_at) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$tok['user_id'], $item['id'], 'roadmap', $newToken, $now]);

                // Fetch updated item and send rich replacement email
                $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
                $stmt->execute([$item['id']]);
                $updatedItem = $stmt->fetch();
                $replacementHtml = buildReplacementEmailHtml($updatedItem, $newToken, $db);
                sendApprovalEmail($tok['user_id'], 'Replacement Blog Plan - ' . escapeHtml($newTitle), $replacementHtml);
                header('Location: ' . APP_BASE_URL . '/api/demo/approval-result?status=replacement_created');
            }
            exit;
        }

        // 2nd click on an already-provisional item: finalize
        $stmt = $db->prepare('UPDATE approval_tokens SET decision = ?, click_count = 2 WHERE id = ?');
        $stmt->execute([$decision === 'approve' ? 'Approved' : 'Rejected', $tok['id']]);

        if ($decision === 'approve') {
            $stmt = $db->prepare("UPDATE campaign_items SET plan_status = 'Approved' WHERE id = ?");
            $stmt->execute([$item['id']]);
            updateTopicStatusInFile($item['title'], 'approved');

            // Generate HTML article
            $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
            $stmt->execute([$item['id']]);
            $freshItem = $stmt->fetch();
            $htmlResult = generateArticleHtmlFromCampaignItem($freshItem, $tok['user_id'], $activeSlot, $db);
            if (!empty($htmlResult['success'])) {
                $stmt = $db->prepare("UPDATE campaign_items SET article_status = 'HTML Ready', html_path = ? WHERE id = ?");
                $stmt->execute([$htmlResult['html_path'], $item['id']]);
                $htmlToken = generateToken();
                $stmt = $db->prepare('INSERT INTO approval_tokens (user_id, campaign_item_id, approval_type, token, created_at) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$tok['user_id'], $item['id'], 'html', $htmlToken, $now]);
                $previewEmailHtml = buildHtmlPreviewEmailHtml($freshItem, $htmlResult['html_path'], $htmlToken, $htmlResult['used_chat_api']);
                sendApprovalEmail($tok['user_id'], 'Blog HTML Preview - ' . escapeHtml($freshItem['title']), $previewEmailHtml);
            }

            header('Location: ' . APP_BASE_URL . '/api/demo/approval-result?status=approved');
        } else {
            $newToken = generateToken();
            $newTitle = $item['title'] . ' — New Research Angle';
            $newKeyword = $item['primary_keyword'] . ' alternatives';
            $oldKws = json_decode($item['keyword_data'] ?? '[]', true);
            $newKws = array_map(fn($x) => ['keyword' => $newKeyword, 'volume' => $x['volume'] ?? 'Demo estimate', 'difficulty' => $x['difficulty'] ?? 'Research required', 'intent' => $x['intent'] ?? 'Informational'], $oldKws);
            $newHeadings = ['H1' => $newTitle, 'H2' => ['A different practical angle', 'What the evidence shows', 'How to apply the advice', 'Frequently Asked Questions'], 'H3' => ['Examples and comparisons', 'Common mistakes', 'Practical checklist']];
            $newPrompts = ["Editorial image illustrating $newKeyword, natural light, no text or logos.", "Practical real-world scene related to $newKeyword."];

            $stmt = $db->prepare("UPDATE campaign_items SET title = ?, primary_keyword = ?, keyword_data = ?, headings = ?, image_prompts = ?, plan_status = 'Replacement Pending', article_status = 'Not Created' WHERE id = ?");
            $stmt->execute([$newTitle, $newKeyword, json_encode($newKws), json_encode($newHeadings), json_encode($newPrompts), $item['id']]);
            $stmt = $db->prepare('INSERT INTO approval_tokens (user_id, campaign_item_id, approval_type, token, created_at) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$tok['user_id'], $item['id'], 'roadmap', $newToken, $now]);

            // Fetch updated item for the replacement email
            $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
            $stmt->execute([$item['id']]);
            $updatedItem = $stmt->fetch();
            $replacementHtml = buildReplacementEmailHtml($updatedItem, $newToken, $db);
            sendApprovalEmail($tok['user_id'], 'Replacement Blog Plan — ' . escapeHtml($newTitle), $replacementHtml);
            header('Location: ' . APP_BASE_URL . '/api/demo/approval-result?status=replacement_created');
        }
        exit;
    }

    // HTML decision
    if (preg_match('#^/api/demo/html-decision/([^/]+)/([^/]+)$#', $uri, $m)) {
        $token = $m[1];
        $decision = $m[2];
        if (!in_array($decision, ['approve', 'reject'])) jsonResponse(['error' => 'Invalid decision.'], 400);

        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM approval_tokens WHERE token = ? AND approval_type = 'html' AND decision = 'Pending'");
        $stmt->execute([$token]);
        $tok = $stmt->fetch();
        if (!$tok) { header('Content-Type: text/html; charset=utf-8'); echo '<h2>This HTML approval link is expired or already used.</h2>'; exit; }

        if ($decision === 'approve') {
            $stmt = $db->prepare("UPDATE approval_tokens SET decision = 'Approved' WHERE id = ?");
            $stmt->execute([$tok['id']]);
            $stmt = $db->prepare("UPDATE campaign_items SET article_status = 'Final Article Approved' WHERE id = ?");
            $stmt->execute([$tok['campaign_item_id']]);

            // Auto-schedule: use campaign_item scheduled_date/scheduled_time and campaign platform
            $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
            $stmt->execute([$tok['campaign_item_id']]);
            $ci = $stmt->fetch();
            if ($ci) {
                $stmt = $db->prepare("SELECT * FROM campaigns WHERE id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$ci['campaign_id']]);
                $camp = $stmt->fetch();

                // Determine platform: item > campaign > slot > default
                $platform = !empty($ci['target_platform']) && $ci['target_platform'] !== 'local' ? $ci['target_platform'] : 'local';
                if ($camp) {
                    if ($platform === 'local' && !empty($camp['target_platform'])) $platform = $camp['target_platform'];
                    if ($platform === 'local') {
                        $stmt = $db->prepare('SELECT destination_platform FROM user_workspace_slots WHERE user_id = ?');
                        $stmt->execute([$tok['user_id']]);
                        $slotRow = $stmt->fetch();
                        if ($slotRow && !empty($slotRow['destination_platform'])) $platform = $slotRow['destination_platform'];
                    }
                }

                // Calculate scheduled time from campaign_item scheduled_date/scheduled_time
                $schedDate = $ci['scheduled_date'] ?? null;
                $schedTime = $ci['scheduled_time'] ?? null;
                if (!empty($schedDate) && !empty($schedTime)) {
                    $parts = explode(':', $schedTime);
                    $scheduledDate = new DateTime($schedDate);
                    $scheduledDate->setTime(intval($parts[0] ?? 10), intval($parts[1] ?? 0), 0);
                    if ($scheduledDate <= new DateTime()) $scheduledDate->modify('+1 day');
                    $scheduledStr = $scheduledDate->format('Y-m-d H:i:s');
                } else {
                    // Fallback: calculate from campaign start_date and posting_times
                    $startDate = !empty($camp['start_date']) ? $camp['start_date'] : date('Y-m-d');
                    $postingTimes = json_decode($camp['posting_times'] ?? '["10:00"]', true) ?: ['10:00'];
                    $dayNum = intval($ci['day_number'] ?? 1);
                    $postNum = intval($ci['post_number'] ?? 1);
                    $timeStr = $postingTimes[min($postNum - 1, count($postingTimes) - 1)] ?? '10:00';
                    $parts = explode(':', $timeStr);
                    $scheduledDate = (new DateTime($startDate))->modify(($dayNum - 1) . ' days');
                    $scheduledDate->setTime(intval($parts[0]), intval($parts[1]), 0);
                    if ($scheduledDate <= new DateTime()) $scheduledDate->modify('+1 day');
                    $scheduledStr = $scheduledDate->format('Y-m-d H:i:s');
                }

                $nowS = nowString();
                $stmt = $db->prepare('INSERT INTO scheduled_queue (user_id, slot_number, topic_title, keyword, category, scheduled_time, target_platform, status, created_at, target_link, target_anchor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$tok['user_id'], $activeSlot, $ci['title'], $ci['primary_keyword'], 'Approved Article', $scheduledStr, $platform, 'Scheduled', $nowS, $ci['internal_links'] ?? '', $ci['primary_keyword'] ?? '']);
            }
        } else {
            $stmt = $db->prepare("UPDATE approval_tokens SET decision = 'Rejected' WHERE id = ?");
            $stmt->execute([$tok['id']]);
            $stmt = $db->prepare("UPDATE campaign_items SET article_status = 'Regenerating HTML' WHERE id = ?");
            $stmt->execute([$tok['campaign_item_id']]);

            // Auto-regenerate HTML with a different content angle
            $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
            $stmt->execute([$tok['campaign_item_id']]);
            $reItem = $stmt->fetch();
            if ($reItem) {
                $regenResult = generateArticleHtmlFromCampaignItem($reItem, $tok['user_id'], $activeSlot, $db, 'Take a completely different practical angle. Focus on real-world case studies, alternative methods, and contrarian insights. Use different examples and a fresh narrative voice.');
                if (!empty($regenResult['success'])) {
                    $stmt = $db->prepare("UPDATE campaign_items SET article_status = 'HTML Ready', html_path = ? WHERE id = ?");
                    $stmt->execute([$regenResult['html_path'], $tok['campaign_item_id']]);
                    $newHtmlToken = generateToken();
                    $nowH = nowString();
                    $stmt = $db->prepare('INSERT INTO approval_tokens (user_id, campaign_item_id, approval_type, token, created_at) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$tok['user_id'], $tok['campaign_item_id'], 'html', $newHtmlToken, $nowH]);
                    // Fetch fresh item for email
                    $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
                    $stmt->execute([$tok['campaign_item_id']]);
                    $freshItem = $stmt->fetch();
                    $previewEmail = buildHtmlPreviewEmailHtml($freshItem, $regenResult['html_path'], $newHtmlToken, $regenResult['used_chat_api']);
                    sendApprovalEmail($tok['user_id'], 'Rewritten Blog HTML - ' . escapeHtml($freshItem['title'] ?? 'Article'), $previewEmail);
                }
            }
        }
        header('Location: ' . APP_BASE_URL . '/api/demo/approval-result?status=html_' . $decision);
        exit;
    }

    // Approval result (HTML page)
    if ($uri === '/api/demo/approval-result') {
        header('Content-Type: text/html; charset=utf-8');
        $status = $_GET['status'] ?? 'updated';
        $messages = [
            'approved' => 'The blog plan was approved. An HTML article is being generated and a preview email will be sent shortly.',
            'provisional' => 'First approval click recorded. Click once more in the email to finalize.',
            'replacement_created' => 'The blog was disapproved. A replacement plan has been created and emailed.',
            'html_approve' => 'The HTML article is approved and finalized! It is ready for publishing.',
            'html_reject' => 'The HTML article was rejected. A rewritten version with a new angle is being generated and will be emailed shortly.',
        ];
        $text = $messages[$status] ?? 'Status updated.';
        echo "<!doctype html><html><head><meta charset=\"utf-8\"><meta http-equiv=\"refresh\" content=\"4;url=" . APP_BASE_URL . "\"><style>body{font-family:Arial;background:#f4f6fa;padding:60px;text-align:center}.box{background:white;border-radius:14px;padding:34px;max-width:560px;margin:auto}h1{color:#10b981}</style></head><body><div class=\"box\"><h1>AutoBlog Updated</h1><p>$text</p><p>You can close this window.</p></div></body></html>";
        exit;
    }

    // Demo emails
    if ($uri === '/api/demo/emails') {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, subject, created_at FROM demo_emails WHERE user_id = ? ORDER BY id DESC');
        $stmt->execute([$userId]);
        jsonResponse($stmt->fetchAll());
    }

    // Demo email content
    if (preg_match('#^/api/demo/email/(\d+)$#', $uri, $m)) {
        $emailId = $m[1];
        $db = getDB();
        $stmt = $db->prepare('SELECT html_content FROM demo_emails WHERE id = ? AND user_id = ?');
        $stmt->execute([$emailId, $userId]);
        $row = $stmt->fetch();
        if ($row) { header('Content-Type: text/html'); echo $row['html_content']; exit; }
        jsonResponse(['error' => 'Email not found'], 404);
    }

    // Generate demo HTML
    // Generate HTML for all approved items missing HTML (manual trigger)
    if (preg_match('#^/api/demo/generate-html/(\d+)$#', $uri, $m) && $method === 'POST') {
        $campaignId = $m[1];
        $db = getDB();
        // Get ALL approved items that don't have HTML yet (any article_status except Published)
        $stmt = $db->prepare('SELECT * FROM campaign_items WHERE campaign_id = ? AND plan_status IN ("Approved","Provisional Approved") AND (article_status = "Not Created" OR article_status = "HTML Ready" AND (html_path IS NULL OR html_path = ""))');
        $stmt->execute([$campaignId]);
        $items = $stmt->fetchAll();
        $generated = [];
        $errors = [];

        foreach ($items as $item) {
            try {
                $htmlResult = generateArticleHtmlFromCampaignItem($item, $userId, $activeSlot, $db);
                if (!empty($htmlResult['success'])) {
                    $stmt = $db->prepare("UPDATE campaign_items SET article_status = 'HTML Ready', html_path = ? WHERE id = ?");
                    $stmt->execute([$htmlResult['html_path'], $item['id']]);
                    $htmlToken = generateToken();
                    $nowG = nowString();
                    $stmt = $db->prepare('INSERT INTO approval_tokens (user_id, campaign_item_id, approval_type, token, created_at) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$userId, $item['id'], 'html', $htmlToken, $nowG]);
                    $previewEmailHtml = buildHtmlPreviewEmailHtml($item, $htmlResult['html_path'], $htmlToken, $htmlResult['used_chat_api']);
                    sendApprovalEmail($userId, 'Blog HTML Preview - ' . escapeHtml($item['title']), $previewEmailHtml);
                    $generated[] = ['id' => $item['id'], 'url' => $htmlResult['html_path'], 'title' => $item['title']];
                } else {
                    $errors[] = ['id' => $item['id'], 'title' => $item['title'], 'error' => $htmlResult['error'] ?? 'Unknown error'];
                }
            } catch (Exception $e) {
                $errors[] = ['id' => $item['id'], 'title' => $item['title'], 'error' => $e->getMessage()];
            }
        }
        jsonResponse(['success' => true, 'articles' => $generated, 'errors' => $errors, 'message' => count($generated) . ' articles generated.' . (count($errors) ? ' ' . count($errors) . ' errors.' : '')]);
    }

    // Generate HTML for ALL approved items across ALL campaigns (bulk trigger)
    if ($uri === '/api/generate-all-html' && $method === 'POST') {
        $db = getDB();
        $stmt = $db->prepare('SELECT ci.* FROM campaign_items ci JOIN campaigns c ON c.id = ci.campaign_id WHERE c.user_id = ? AND ci.plan_status IN ("Approved","Provisional Approved") AND (ci.article_status = "Not Created" OR (ci.article_status = "HTML Ready" AND (ci.html_path IS NULL OR ci.html_path = ""))) ORDER BY ci.id');
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll();
        $generated = [];
        $errors = [];

        foreach ($items as $item) {
            try {
                $htmlResult = generateArticleHtmlFromCampaignItem($item, $userId, $activeSlot, $db);
                if (!empty($htmlResult['success'])) {
                    $stmt = $db->prepare("UPDATE campaign_items SET article_status = 'HTML Ready', html_path = ? WHERE id = ?");
                    $stmt->execute([$htmlResult['html_path'], $item['id']]);
                    $generated[] = ['id' => $item['id'], 'url' => $htmlResult['html_path'], 'title' => $item['title']];
                } else {
                    $errors[] = ['id' => $item['id'], 'title' => $item['title'], 'error' => $htmlResult['error'] ?? 'Unknown error'];
                }
            } catch (Exception $e) {
                $errors[] = ['id' => $item['id'], 'title' => $item['title'], 'error' => $e->getMessage()];
            }
        }
        jsonResponse(['success' => true, 'articles' => $generated, 'errors' => $errors, 'total_found' => count($items), 'message' => count($generated) . ' of ' . count($items) . ' articles generated.' . (count($errors) ? ' ' . count($errors) . ' errors.' : '')]);
    }

    // Content plans
    if ($uri === '/api/content-plans') {
        if ($method === 'POST') {
            $keyword = $input['primary_keyword'] ?? '';
            $title = $input['title'] ?? '';
            if (!$keyword || !$title) jsonResponse(['error' => 'Title and primary keyword are required.'], 400);
            $db = getDB();
            $now = nowString();
            $stmt = $db->prepare('INSERT INTO content_plans (user_id, slot_number, keyword_id, title, primary_keyword, supporting_keywords, target_link, target_anchor, external_sources, image_plan, video_needed, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $activeSlot, $input['keyword_id'] ?? null, $title, $keyword, json_encode($input['supporting_keywords'] ?? []), $input['target_link'] ?? null, $input['target_anchor'] ?? null, json_encode($input['external_sources'] ?? []), json_encode($input['image_plan'] ?? []), !empty($input['video_needed']) ? 1 : 0, 'Planned', $now]);
            jsonResponse(['success' => true, 'plan_id' => $db->lastInsertId(), 'status' => 'Planned']);
        }
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM content_plans WHERE user_id = ? AND slot_number = ? ORDER BY id DESC');
        $stmt->execute([$userId, $activeSlot]);
        jsonResponse($stmt->fetchAll());
    }

    // Approve content plan
    if (preg_match('#^/api/content-plans/(\d+)/approve$#', $uri, $m) && $method === 'POST') {
        $planId = $m[1];
        $db = getDB();
        $now = nowString();
        $stmt = $db->prepare("UPDATE content_plans SET status = 'Approved', approved_at = ? WHERE id = ? AND user_id = ? AND slot_number = ?");
        $stmt->execute([$now, $planId, $userId, $activeSlot]);
        if ($stmt->rowCount()) jsonResponse(['success' => true, 'status' => 'Approved', 'message' => 'Human approval recorded.']);
        jsonResponse(['error' => 'Plan not found in this workspace.'], 404);
    }

    // 404 for unmatched API routes
    jsonResponse(['error' => 'API endpoint not found'], 404);
}
