<?php
/**
 * AutoBlog SaaS - Main Application Router
 * Hostinger Shared Hosting Compatible
 */

// Set timezone — Hostinger server may not match user timezone
date_default_timezone_set('Asia/Kolkata');

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

// Error handling — log to file, never display in output (breaks JSON API responses)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/data/php_error.log');

// Catch any fatal errors. API routes MUST return JSON (never HTML),
// otherwise the dashboard shows: Unexpected token '<', "<h1>PHP Er"...
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $isApi = (strpos($uri, '/api/') !== false);
        http_response_code(500);
        $msg = ($error['message'] ?? 'Fatal error') . ' in ' . basename($error['file'] ?? '') . ':' . ($error['line'] ?? 0);
        error_log('[FATAL] ' . $msg);
        if ($isApi) {
            if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'PHP Error: ' . $msg], JSON_UNESCAPED_UNICODE);
        } else {
            echo '<h1>PHP Error</h1><pre>' . htmlspecialchars(print_r($error, true)) . '</pre>';
        }
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
    require_once __DIR__ . '/includes/google_keyword_planner.php';
    require_once __DIR__ . '/includes/keyword_flow.php';
    require_once __DIR__ . '/includes/auto_daily.php';
} catch (Exception $e) {
    http_response_code(500);
    echo '<h1>AutoBlog Setup Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p><p>Check that all files are uploaded correctly and PHP version is 8.0+</p>';
    exit;
}

startSession();

/**
 * Get WebsitePublisher instance.
 * Tries multiple paths: blog/ at public_html/blog/, or blog/ inside sub_apps/.
 * Returns [publisher, error]. If publisher is null, error has the message.
 */
function getBlogPublisher() {
    $paths = [
        dirname(__DIR__) . '/blog/includes/publisher.php',  // public_html/blog/
        __DIR__ . '/blog/includes/publisher.php',            // sub_apps/blog/
        __DIR__ . '/website_blog/includes/publisher.php',    // legacy folder name
    ];
    $tried = [];
    foreach ($paths as $p) {
        $tried[] = $p;
        if (file_exists($p)) {
            require_once $p;
            try {
                return [new WebsitePublisher(), null];
            } catch (Throwable $e) {
                return [null, 'WebsitePublisher error: ' . $e->getMessage()];
            }
        }
    }
    return [null, 'Blog publisher not found. Upload blog/ to public_html/blog/ (for colorfiind.com/blog/) or public_html/sub_apps/blog/. Tried: ' . implode(' | ', $tried)];
}

/** Resolve generated campaign HTML on disk (sub_apps vs public_html). */
function resolveCampaignHtmlFile($htmlPath) {
    if (empty($htmlPath)) return null;
    $base = basename($htmlPath);
    $rel = ltrim(str_replace('\\', '/', $htmlPath), '/');
    $outDir = defined('OUTPUT_DIR') ? rtrim(OUTPUT_DIR, '/') : (__DIR__ . '/published_posts');
    $candidates = [
        __DIR__ . '/' . $rel,
        $outDir . '/' . $base,
        $outDir . '/demo/' . $base,
        __DIR__ . '/published_posts/demo/' . $base,
        __DIR__ . '/published_posts/' . $base,
        dirname(__DIR__) . '/' . $rel,
    ];
    foreach ($candidates as $p) {
        if ($p && @is_file($p)) return $p;
    }
    return null;
}

function publishToWebsiteBlog($item, $title, $articleContent, $scheduledStr = null) {
    try {
        list($wpub, $wpubErr) = getBlogPublisher();
        if ($wpubErr || !$wpub) {
            return ['success' => false, 'error' => $wpubErr ?: 'Website publisher unavailable'];
        }
        $slug = function_exists('slugify') ? slugify($title) : preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
        $cat = $item['category'] ?? 'General';
        $kw = $item['primary_keyword'] ?? '';
        $thumbUrl = '';
        if (preg_match('#<img[^>]+src=["\']([^"\']+)["\']#i', $articleContent, $m)) {
            $thumbUrl = $m[1];
        }
        $payload = [
            'title' => $title,
            'slug' => $slug,
            'content_html' => $articleContent,
            'category' => $cat,
            'tags' => array_values(array_filter([$kw, $cat])),
            'thumbnail_url' => $thumbUrl,
            'author' => 'ColorFiind Team',
            'meta_description' => substr(strip_tags($articleContent), 0, 160),
            'meta_keywords' => $kw,
        ];
        if (!empty($scheduledStr)) {
            $payload['scheduled_date'] = $scheduledStr;
        }
        return $wpub->publish($payload);
    } catch (Throwable $e) {
        error_log('[Website Blog] Publish error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        return ['success' => false, 'error' => 'Website blog publish failed: ' . $e->getMessage()];
    }
}

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
    $outDir = defined('OUTPUT_DIR') ? rtrim(OUTPUT_DIR, '/') : (__DIR__ . '/published_posts');
    $candidates = [
        $outDir . '/' . $filename,
        $outDir . '/demo/' . $filename,
        __DIR__ . '/published_posts/' . $filename,
        __DIR__ . '/published_posts/demo/' . $filename,
    ];
    foreach ($candidates as $filePath) {
        if ($filePath && is_file($filePath)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($filePath);
            exit;
        }
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
        foreach (['blogger_api', 'brevo_api', 'wordpress_api', 'dataforseo_api', 'chat_api', 'image_api', 'google_keyword_planner'] as $s) {
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
        $allowed = ['chat_api','image_api','dataforseo_api','blogger_api','wordpress_api','google_keyword_planner'];
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

    // Generate OAuth URL for Blogger — gives user the URL to visit for authorization
    if ($uri === '/api/vault/blogger-oauth-url' && $method === 'POST') {
        $clientId = trim($input['client_id'] ?? '');
        if (!$clientId) {
            $bloggerVault = SecurityVault::getApiCredentials($userId, 'blogger_api');
            $clientId = trim($bloggerVault['client_id'] ?? '');
        }
        if (!$clientId) {
            jsonResponse(['success' => false, 'error' => 'Client ID is required. Enter your Google OAuth Client ID first.'], 400);
        }
        $redirectUri = $input['redirect_uri'] ?? 'https://developers.google.com/oauthplayground';
        $authUrl = BloggerOAuthHelper::getOAuthAuthorizationUrl($clientId, $redirectUri);
        jsonResponse(['success' => true, 'auth_url' => $authUrl, 'instructions' => 'Visit this URL, authorize with your Google account, copy the authorization code from the redirect URL, then use the Exchange Auth Code button or paste it in the OAuth Playground Step 2.']);
    }

    // Exchange OAuth authorization code for refresh token
    if ($uri === '/api/vault/blogger-exchange-code' && $method === 'POST') {
        $clientId = trim($input['client_id'] ?? '');
        $clientSecret = trim($input['client_secret'] ?? '');
        $authCode = trim($input['auth_code'] ?? '');
        $redirectUri = $input['redirect_uri'] ?? 'https://developers.google.com/oauthplayground';
        
        if (!$clientId || !$clientSecret) {
            $bloggerVault = SecurityVault::getApiCredentials($userId, 'blogger_api');
            $clientId = $clientId ?: trim($bloggerVault['client_id'] ?? '');
            $clientSecret = $clientSecret ?: trim($bloggerVault['client_secret'] ?? '');
        }
        if (!$authCode) jsonResponse(['success' => false, 'error' => 'Authorization code is required. Get it from the OAuth URL redirect.'], 400);
        if (!$clientId || !$clientSecret) jsonResponse(['success' => false, 'error' => 'Client ID and Client Secret are required.'], 400);
        
        $result = BloggerOAuthHelper::exchangeAuthCode($clientId, $clientSecret, $authCode, $redirectUri);
        if ($result['success']) {
            // Auto-save the new refresh token to the vault
            $vault = SecurityVault::getApiCredentials($userId, 'blogger_api');
            $vault['client_id'] = $clientId;
            $vault['client_secret'] = $clientSecret;
            $vault['refresh_token'] = $result['refresh_token'];
            $vault['access_token'] = $result['access_token'];
            $alias = $vault['account_alias'] ?? 'Primary Blogger Account';
            unset($vault['account_alias']);
            SecurityVault::saveApiCredentials($userId, 'blogger_api', $vault, $alias);
            
            jsonResponse([
                'success' => true, 
                'refresh_token' => $result['refresh_token'], 
                'access_token' => substr($result['access_token'], 0, 20) . '...', // Don't expose full access token
                'message' => '✅ New refresh token obtained and saved to vault! You can now test the Blogger connection.'
            ]);
        }
        jsonResponse(['success' => false, 'error' => 'Auth code exchange failed: ' . ($result['error'] ?? 'Unknown error')], 400);
    }

    // ========== Google Keyword Planner — Get real keyword volumes ==========
    if ($uri === '/api/keyword-planner/search' && $method === 'POST') {
        $seedKeywords = $input['keywords'] ?? [];
        $country = $input['country'] ?? 'India';
        $language = $input['language_code'] ?? 'en';
        
        if (empty($seedKeywords)) {
            jsonResponse(['success' => false, 'error' => 'At least one seed keyword is required.'], 400);
        }
        if (!is_array($seedKeywords)) $seedKeywords = [$seedKeywords];
        
        // Get Google Ads credentials from vault
        $gkwVault = SecurityVault::getApiCredentials($userId, 'google_keyword_planner');
        $developerToken = trim($input['developer_token'] ?? $gkwVault['developer_token'] ?? '');
        $customerId = trim($input['customer_id'] ?? $gkwVault['customer_id'] ?? '');
        $loginCustomerId = trim($input['login_customer_id'] ?? $gkwVault['login_customer_id'] ?? $customerId);
        
        if (empty($developerToken) || empty($customerId)) {
            jsonResponse(['success' => false, 'error' => 'Google Ads Developer Token and Customer ID are required. Save them in the Keyword Planner vault section. Get Developer Token at: Google Ads → Tools & Settings → API Center'], 400);
        }
        
        list($clientId, $clientSecret, $refreshToken) = resolveKeywordPlannerOAuth($userId, $input);
        if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
            jsonResponse(['success' => false, 'error' => 'OAuth Client ID, Client Secret, and Refresh Token are required. Save them in the Keyword Planner vault (or Blogger vault). Client ID must end with .apps.googleusercontent.com — not a Google Ads account number.'], 400);
        }
        if (strpos($clientId, '.apps.googleusercontent.com') === false) {
            jsonResponse(['success' => false, 'error' => 'That Client ID is not a Google Cloud OAuth Client ID. It must look like xxx.apps.googleusercontent.com. You probably pasted a Google Ads customer/manager ID.'], 400);
        }
        
        // Map country to location ID
        $locationMap = ['india' => 2356, 'united states' => 2840, 'usa' => 2840, 'united kingdom' => 2826, 'canada' => 2124, 'australia' => 2036, 'uae' => 2784, 'germany' => 2276, 'france' => 2250, 'brazil' => 2070, 'japan' => 2392];
        $locationId = $locationMap[strtolower($country)] ?? 2356;
        
        // Map language to Google Ads language constant
        $langMap = ['en' => '1000', 'hi' => '1001', 'es' => '1003', 'fr' => '1002', 'de' => '1004', 'pt' => '1006', 'ja' => '1005', 'ar' => '1019'];
        $languageCode = $langMap[$language] ?? '1000';
        
        // Step 1: Get fresh access token
        $tokenResult = GoogleKeywordPlanner::getAccessToken($clientId, $clientSecret, $refreshToken);
        if (!$tokenResult['success']) {
            jsonResponse(['success' => false, 'error' => 'OAuth failed: ' . $tokenResult['error']], 400);
        }
        $accessToken = $tokenResult['access_token'];
        
        // Step 2: Call Google Ads API for keyword ideas
        $kwResult = GoogleKeywordPlanner::generateKeywordIdeas(
            $developerToken, $customerId, $loginCustomerId, $accessToken,
            $seedKeywords, $languageCode, $locationId
        );
        
        if ($kwResult['success']) {
            jsonResponse($kwResult);
        }
        jsonResponse(['success' => false, 'error' => $kwResult['error'] ?? 'Keyword Planner API failed.'], 400);
    }

    // Test Google Keyword Planner connection
    if ($uri === '/api/vault/test-keyword-planner' && $method === 'POST') {
        $developerToken = trim($input['developer_token'] ?? '');
        $customerId = trim($input['customer_id'] ?? '');
        $loginCustomerId = trim($input['login_customer_id'] ?? $customerId);
        
        if (empty($developerToken) || empty($customerId)) {
            $gkwVault = SecurityVault::getApiCredentials($userId, 'google_keyword_planner');
            $developerToken = $developerToken ?: trim($gkwVault['developer_token'] ?? '');
            $customerId = $customerId ?: trim($gkwVault['customer_id'] ?? '');
            $loginCustomerId = trim($gkwVault['login_customer_id'] ?? $customerId);
        }
        
        if (empty($developerToken) || empty($customerId)) {
            jsonResponse(['success' => false, 'error' => 'Developer Token and Customer ID are required.'], 400);
        }
        
        list($clientId, $clientSecret, $refreshToken) = resolveKeywordPlannerOAuth($userId, $input);
        if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
            jsonResponse(['success' => false, 'error' => 'OAuth Client ID, Client Secret, and Refresh Token are required. Paste them in the Keyword Planner vault (same Google Cloud Web app that created the refresh token).'], 400);
        }
        if (strpos($clientId, '.apps.googleusercontent.com') === false) {
            jsonResponse(['success' => false, 'error' => 'That Client ID is not a Google Cloud OAuth Client ID. It must end with .apps.googleusercontent.com. Do not paste a Google Ads customer/manager ID there.'], 400);
        }
        
        // Test with a simple keyword
        $tokenResult = GoogleKeywordPlanner::getAccessToken($clientId, $clientSecret, $refreshToken);
        if (!$tokenResult['success']) {
            jsonResponse(['success' => false, 'error' => 'OAuth failed: ' . $tokenResult['error']], 400);
        }
        
        $kwResult = GoogleKeywordPlanner::generateKeywordIdeas(
            $developerToken, $customerId, $loginCustomerId, $tokenResult['access_token'],
            ['digital marketing'], '1000', 2356
        );
        
        if ($kwResult['success'] && !empty($kwResult['keywords'])) {
            jsonResponse(['success' => true, 'message' => '✅ Google Keyword Planner connected! Found ' . $kwResult['count'] . ' keyword ideas for test query.', 'sample_count' => $kwResult['count']]);
        }
        jsonResponse(['success' => false, 'error' => $kwResult['error'] ?? 'No keywords returned. Check your Developer Token and Customer ID.'], 400);
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
            $errMsg = $rfRes['error'] ?? 'Check your Client ID, Client Secret, and Refresh Token.';
            jsonResponse(['success' => false, 'error' => 'OAuth Token Refresh Failed — ' . $errMsg, 'error_type' => 'oauth_refresh'], 400);
        }
        $accessToken = $rfRes['access_token'];
        
        // Step 2: Use the fresh access token to GET blog info
        $result = curlGet("https://www.googleapis.com/blogger/v3/blogs/" . trim($blogId), ["Authorization: Bearer " . trim($accessToken)], 10);
        $data = $result['json'] ?? [];
        
        if ($result['http_code'] === 200 && !empty($data['name'])) {
            // Save ALL OAuth credentials back to vault (including the ones used for test)
            $vault = SecurityVault::getApiCredentials($userId, 'blogger_api');
            if (!empty($vault)) {
                $vault['blogger_blog_id'] = $blogId;
                $vault['client_id'] = $clientId;
                $vault['client_secret'] = $clientSecret;
                $vault['refresh_token'] = $refreshToken;
                $vault['access_token'] = $accessToken;
                $alias = $vault['account_alias'] ?? 'Primary Blogger Account';
                unset($vault['account_alias']);
                SecurityVault::saveApiCredentials($userId, 'blogger_api', $vault, $alias);
            } else {
                // No existing vault entry — create one
                SecurityVault::saveApiCredentials($userId, 'blogger_api', [
                    'blogger_blog_id' => $blogId,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                    'access_token' => $accessToken
                ], 'Primary Blogger Account');
            }
            jsonResponse(['success' => true, 'blog_name' => $data['name'] ?? '', 'url' => $data['url'] ?? '', 'message' => '✅ OAuth connected! All credentials saved to vault.']);
        }
        
        // Error from Blogger API
        $errorMsg = $data['error']['message'] ?? '';
        $errorReason = $data['error']['errors'][0]['reason'] ?? '';
        if ($errorMsg) {
            $extra = '';
            if ($result['http_code'] === 401) $extra = ' (Access token may be invalid or wrong scope — make sure you used scope https://www.googleapis.com/auth/blogger when generating the refresh token, NOT the read-only scope)';
            if ($result['http_code'] === 403) $extra = ' (Blogger API may not be enabled, or blog ID is wrong, or you used a read-only OAuth scope. Use scope: https://www.googleapis.com/auth/blogger)';
            if ($result['http_code'] === 404) $extra = ' (Blog ID not found — check the Blog ID. You can find it at https://www.blogger.com/about/ → your blog → settings)';
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
        $neededCount = $days * $postsPerDay;
        if (!$domain) jsonResponse(['error' => 'Website URL is required.'], 400);

        // ===== READ CUSTOM TOPICS CSV FIRST =====
        $csvPath = customTopicsCsvPath();
        $customTopics = [];
        if (file_exists($csvPath)) {
            $fp = @fopen($csvPath, 'r');
            if ($fp) {
                fgetcsv($fp); // skip header
                while (($row = fgetcsv($fp)) !== false) {
                    if (!empty($row[0]) && trim($row[0]) !== '') $customTopics[] = trim($row[0]);
                }
                fclose($fp);
            }
        }
        // Dedup custom topics against existing used topics
        $db = getDB();
        $existingTopics = getAllUsedTopics($db, $userId);
        $customTopics = array_values(array_filter($customTopics, fn($t) => !isTopicDuplicate($t, $t, $existingTopics)));
        $csvCount = min(count($customTopics), $neededCount);
        $researchCount = max(0, $neededCount - $csvCount);
        $selectedCsvTopics = array_slice($customTopics, 0, $csvCount);

        $pages = ResearchAgent::crawlAndExtractSitePages($domain, $userId);
        $info = ResearchAgent::analyzeCustomerWebsite($domain);
        $chat = SecurityVault::getApiCredentials($userId, 'chat_api');
        if (empty($chat['api_key'])) jsonResponse(['error' => 'Save and select a Chat API before live research.'], 400);

        $pageContext = implode("\n", array_map(fn($p) => "URL: {$p['page_url']} | Page topic: {$p['page_title']}", array_slice($pages, 0, 100)));
        $nowYear = date('Y');
        $nowMonth = date('F');
        $countryNote = ($country !== 'India' && $country !== '') ? " The target country is $country — topics MUST be relevant to $country's market, culture, regulations, and business landscape. Do NOT reuse generic topics that could apply to any country. Make them specific to $country." : "";
        $prompt = "You are an SEO research strategist. Search the web broadly for the business website $domain in target country $country, language $language. The current year is $nowYear — use it in content but do NOT mention the current month in article titles. Create exactly $researchCount UNIQUE article plans for a $days-day campaign with $postsPerDay article(s) per day. Each article MUST cover a DIFFERENT angle — do not repeat the same concept with slight wording changes. Think broadly: search different blogs, industry reports, competitor analysis, trending news, how-to guides, comparison reviews, case studies, tool roundups, expert opinions, FAQ compilations, myth-busting articles, beginner tutorials, advanced strategies, cost analyses, ROI discussions, compliance/legal aspects, regional opportunities, technology integrations, workflow optimizations, and productivity hacks. Return ONLY valid JSON with this shape: {\"articles\":[{\"title\":\"...\",\"primary_keyword\":\"...\",\"keywords\":[{\"keyword\":\"...\",\"volume\":0,\"difficulty\":\"\",\"intent\":\"...\"}],\"internal_links\":[{\"url\":\"...\",\"anchor_text\":\"...\",\"reason\":\"...\"}],\"external_links\":[{\"url\":\"...\",\"anchor_text\":\"...\",\"reason\":\"...\"}],\"headings\":{\"H1\":\"...\",\"H2\":[\"...\"],\"H3\":[\"...\"]},\"image_prompts\":[\"...\"]}]}. IMPORTANT: (1) Each article title must be UNIQUE in concept — not just different wording of the same idea. (2) Do NOT include the month name in any title. (3) Include the year $nowYear in titles only when natural. (4) Do NOT mention the country in the title unless it specifically adds targeting value. (5) For external_links: Provide at least 4 REAL working URLs to well-known authority sites (Wikipedia, Google docs, Mozilla MDN, Schema.org, Moz, Ahrefs, Neil Patel, HubSpot, Backlinko, etc.) that are RELATED to each article topic. Do NOT invent or guess URLs. (6) Include 1-2 of the client's crawled pages in internal_links.$countryNote Crawled pages:\n$pageContext";

        $result = AIProviderClient::chat($chat, $prompt);
        if (!$result['success']) jsonResponse(['error' => 'Chat research failed: ' . ($result['error'] ?? 'Unknown error')], 400);

        $raw = trim($result['content']);
        $raw = str_replace(['```json', '```'], '', $raw);
        $plans = json_decode(trim($raw), true)['articles'] ?? [];
        
        // ===== RESEARCH CUSTOM CSV TOPICS DEEPLY USING AI =====
        // Send each CSV topic to the chat model for proper keyword research,
        // related customer website pages, external references, headings, etc.
        $csvPlanObjects = [];
        if (!empty($selectedCsvTopics)) {
            $csvTopicsList = implode("\n", array_map(fn($i, $t) => ($i + 1) . '. ' . $t, array_keys($selectedCsvTopics), $selectedCsvTopics));
            $csvResearchPrompt = "You are an SEO research strategist. The user has provided these specific article topics for the website $domain in target country $country, language $language. The current year is $nowYear.\n\nUSER TOPICS:\n$csvTopicsList\n\nFor EACH topic above, do deep research:\n1. Keep the user topic as the article TITLE. Do NOT invent search volume or difficulty. Keyword Planner will supply keywords.\n2. Find 1-2 of the client's crawled website pages that are MOST RELATED to each topic (for internal links)\n3. Find at least 4 REAL working URLs to well-known authority sites that are SPECIFICALLY RELATED to each topic (NOT generic SEO links — find Wikipedia articles, Google docs, industry reports, tool pages, etc. that match the actual topic)\n4. Create detailed headings (H1, H2s, H3s) for a comprehensive article\n5. Create 2 image prompts for editorial-style images\n\nDo NOT include the month name in any title. Include the year $nowYear only when natural. Do NOT mention the country in the title unless it adds targeting value.$countryNote\n\nCrawled website pages:\n$pageContext\n\nReturn ONLY valid JSON: {\"articles\":[{\"title\":\"...\",\"primary_keyword\":\"...\",\"keywords\":[{\"keyword\":\"...\",\"volume\":0,\"difficulty\":\"\",\"intent\":\"...\"}],\"internal_links\":[{\"url\":\"...\",\"anchor_text\":\"...\",\"reason\":\"...\"}],\"external_links\":[{\"url\":\"...\",\"anchor_text\":\"...\",\"reason\":\"...\"}],\"headings\":{\"H1\":\"...\",\"H2\":[\"...\"],\"H3\":[\"...\"]},\"image_prompts\":[\"...\"]}]}";
            
            $csvResult = AIProviderClient::chat($chat, $csvResearchPrompt);
            if (!empty($csvResult['success']) && !empty($csvResult['content'])) {
                $csvRaw = trim($csvResult['content']);
                $csvRaw = str_replace(['```json', '```'], '', $csvRaw);
                $csvPlans = json_decode(trim($csvRaw), true)['articles'] ?? [];
                if (!empty($csvPlans)) {
                    $csvPlanObjects = $csvPlans;
                }
            }
            // Fallback: if AI research for CSV topics failed, create basic plan objects
            if (empty($csvPlanObjects)) {
                foreach ($selectedCsvTopics as $csvTopic) {
                    $csvPlanObjects[] = [
                        'title' => $csvTopic,
                        'primary_keyword' => $csvTopic,
                        'keywords' => [],
                        'internal_links' => [['url' => $domain, 'anchor_text' => 'client website']],
                        'external_links' => array_slice(array_map(fn($p) => ['url' => $p['page_url'], 'anchor_text' => $p['page_title'] ?: basename($p['page_url'])], $pages), 0, 4) ?: [['url' => $domain, 'anchor_text' => 'Customer Website']],
                        'headings' => ['H1' => $csvTopic, 'H2' => ['Overview', 'Key Insights', 'How to Apply', 'FAQ'], 'H3' => ['Details', 'Common Questions']],
                        'image_prompts' => ["Editorial image for $csvTopic, professional, no text."]
                    ];
                }
            }
        }
        $plans = array_merge($csvPlanObjects, $plans);
        
        if (empty($plans)) jsonResponse(['error' => 'Chat API did not return valid JSON roadmap and no custom topics available.', 'raw_preview' => substr($raw, 0, 1000)], 400);

        $db = getDB();
        $now = nowString();
        
        // ===== STRICT TOPIC DEDUP: Load ALL existing topics before creating campaign =====
        $existingTopics = getAllUsedTopics($db, $userId);
        $dedupStats = ['total_proposed' => count($plans), 'duplicates_removed' => 0];
        $plans = array_filter($plans, function($plan) use ($existingTopics, &$dedupStats) {
            $title = $plan['title'] ?? '';
            $keyword = $plan['primary_keyword'] ?? '';
            if (isTopicDuplicate($title, $keyword, $existingTopics)) {
                $dedupStats['duplicates_removed']++;
                return false;
            }
            return true;
        });
        $plans = array_values($plans);
        // If too many duplicates were removed, request MORE topics from AI
        $neededCount = $days * $postsPerDay;
        if (count($plans) < $neededCount) {
            $extraNeeded = $neededCount - count($plans);
            $usedTitles = array_map(fn($p) => $p['title'] ?? '', $plans);
            $usedTitlesStr = implode(', ', $usedTitles);
            $extraPrompt = "I already have these article topics planned: $usedTitlesStr. I need $extraNeeded MORE completely different article topics for the website $domain in country $country. Each must be a UNIQUE angle not covered by the existing topics. Think of different blogs, industry angles, tool reviews, case studies, comparisons, FAQs, myths, regional opportunities, technology trends, compliance aspects, ROI analysis, workflow tips. Do NOT repeat any concept from the existing list. Return ONLY valid JSON: {\"articles\":[{\"title\":\"...\",\"primary_keyword\":\"...\",\"keywords\":[{\"keyword\":\"...\",\"volume\":0,\"difficulty\":\"\",\"intent\":\"...\"}],\"internal_links\":[{\"url\":\"...\",\"anchor_text\":\"...\",\"reason\":\"...\"}],\"external_links\":[{\"url\":\"...\",\"anchor_text\":\"...\",\"reason\":\"...\"}],\"headings\":{\"H1\":\"...\",\"H2\":[\"...\"],\"H3\":[\"...\"]},\"image_prompts\":[\"...\"]}]}. Do NOT include month in titles. Include year $nowYear only when natural.";
            $extraResult = AIProviderClient::chat($chat, $extraPrompt);
            if (!empty($extraResult['success']) && !empty($extraResult['content'])) {
                $extraRaw = trim($extraResult['content']);
                $extraRaw = str_replace(['```json', '```'], '', $extraRaw);
                $extraPlans = json_decode(trim($extraRaw), true)['articles'] ?? [];
                foreach ($extraPlans as $ep) {
                    if (count($plans) >= $neededCount) break;
                    $et = $ep['title'] ?? '';
                    $ek = $ep['primary_keyword'] ?? '';
                    if (!isTopicDuplicate($et, $ek, $existingTopics)) {
                        $plans[] = $ep;
                        // Also add to existingTopics so next iteration checks against it
                        $existingTopics[] = ['topic' => $et, 'keyword' => $ek];
                    }
                }
            }
        }
        if (empty($plans)) jsonResponse(['error' => 'ALL proposed topics are duplicates of existing blogs. The software has covered this niche extensively. Try a different website or industry angle.', 'dedup_stats' => $dedupStats], 400);
        $keywordSource = 'planner';
        $plannerUsedCount = 0;
        $csvTitleSet = array_fill_keys(array_map('strtolower', $selectedCsvTopics), true);
        foreach ($plans as &$plan) {
            $isCustom = !empty($csvTitleSet[strtolower(trim($plan['title'] ?? ''))]);
            $kwRes = requirePlannerKeywordsOnPlan($plan, $userId, $country, $language, true);
            if (empty($kwRes['success'])) {
                jsonResponse(['error' => 'Keyword Planner is required. It failed: ' . ($kwRes['error'] ?? 'unknown')], 400);
            }
            $plannerUsedCount++;
            if (empty($plan['keywords'][0]['keyword'])) {
                jsonResponse(['error' => 'Keyword Planner returned empty keywords for: ' . ($plan['title'] ?? '')], 400);
            }
            $plan['primary_keyword'] = $plan['keywords'][0]['keyword'];
        }
        unset($plan);
        // PRESERVE APPROVED ITEMS: Only archive campaigns with NO approved/finalized items
        $db->exec("UPDATE approval_tokens SET decision = 'Expired' WHERE user_id = $userId AND decision IN ('Pending','Provisional') AND campaign_item_id NOT IN (SELECT id FROM campaign_items WHERE plan_status IN ('Approved','Provisional Approved') OR article_status IN ('HTML Ready','Final Article Approved'))");
        $db->exec("UPDATE campaigns SET status = 'Archived' WHERE user_id = $userId AND status = 'Roadmap Review' AND id NOT IN (SELECT DISTINCT campaign_id FROM campaign_items WHERE plan_status IN ('Approved','Provisional Approved') OR article_status IN ('HTML Ready','Final Article Approved'))");

        $startDate = $input['start_date'] ?? date('Y-m-d');
        $postingTimes = $input['posting_times'] ?? ['10:00'];
        $targetPlatform = $input['target_platform'] ?? 'blogger';
        $workflowMode = (($input['workflow_mode'] ?? 'manual') === 'auto') ? 'auto' : 'manual';

        $stmt = $db->prepare('INSERT INTO campaigns (user_id, slot_number, domain_url, target_country, language_code, days, posts_per_day, status, start_date, posting_times, target_platform, created_at, workflow_mode, keyword_source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $activeSlot, $domain, $country, $language, $days, $postsPerDay, $workflowMode === 'auto' ? 'Auto Running' : 'Roadmap Review', $startDate, json_encode($postingTimes), $targetPlatform, $now, $workflowMode, $keywordSource]);
        $campaignId = $db->lastInsertId();
        if ($workflowMode === 'auto') {
            saveAutoBlogJob($userId, $activeSlot, $campaignId, $domain, $country, $language, $days, $postsPerDay, $startDate, $postingTimes, $targetPlatform, $keywordSource);
        }

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
            $planStatus = $workflowMode === 'auto' ? 'Approved' : 'Pending';
            $stmt->execute([$campaignId, $dayNum, $postNum, $plan['title'] ?? 'Untitled', $plan['primary_keyword'] ?? '', json_encode($kws), json_encode($links), json_encode($ext), json_encode($heads), json_encode($prompts), $plan['video_url'] ?? '', $planStatus, 'Not Created', $schedDate, $schedTime, $targetPlatform, $now]);
            $itemId = $db->lastInsertId();

            if ($workflowMode !== 'auto') {
            $token = generateToken();
            $stmt = $db->prepare('INSERT INTO approval_tokens (user_id, campaign_item_id, approval_type, token, created_at) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $itemId, 'roadmap', $token, $now]);
            }

            $firstKw = $kws[0] ?? [];
            $support = implode(', ', array_slice(array_filter(array_map(fn($x) => $x['keyword'] ?? '', $kws)), 1, 5));
            $roadmapRows[] = ['plan_id' => $itemId, 'day' => intval($i / $postsPerDay) + 1, 'topic' => $plan['title'] ?? '', 'keyword' => $plan['primary_keyword'] ?? '', 'supporting' => $support, 'competition' => $firstKw['difficulty'] ?? ($keywordSource === 'planner' ? 'Keyword Planner' : 'AI researched'), 'search_volume' => $firstKw['volume'] ?? '', 'difficulty' => $firstKw['difficulty'] ?? '', 'keyword_source' => $keywordSource === 'planner' ? 'Google Keyword Planner' : 'AI', 'target_link' => '', 'target_anchor' => 'See approved research in email'];
            
            // Record topic to persistent JSON + CSV for dedup
            addTopicToJsonFile($plan['title'] ?? 'Untitled', $plan['primary_keyword'] ?? '', $domain, 'pending', $campaignId);
            addTopicToCsv($plan['title'] ?? 'Untitled', $plan['primary_keyword'] ?? '', $domain, 'pending', $campaignId, $now);
            // Also record to DB table for faster dedup queries
            try {
                $stmt = $db->prepare('INSERT OR IGNORE INTO created_blog_topics (user_id, title, primary_keyword, domain_url, campaign_id, created_at) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$userId, $plan['title'] ?? 'Untitled', $plan['primary_keyword'] ?? '', $domain, $campaignId, $now]);
            } catch (Exception $e) {}
        }

        // Auto-sync all topics to CSV after campaign creation
        syncTopicsCsv();

        // Build and send rich approval email with full draft content
        $stmt = $db->prepare('SELECT * FROM campaign_items WHERE campaign_id = ? ORDER BY day_number, post_number');
        $stmt->execute([$campaignId]);
        $allCampaignItems = $stmt->fetchAll();
        $campaignRow = ['domain_url' => $domain, 'days' => $days, 'posts_per_day' => $postsPerDay];
        $sent = false;
        if ($workflowMode !== 'auto') {
            $richEmailHtml = buildRichApprovalEmailHtml($allCampaignItems, $campaignRow, $db);
            $sent = sendApprovalEmail($userId, 'Your AI Research Roadmap Draft — Approve or Disapprove Each Article', $richEmailHtml);
        }
        
        // ===== REMOVE USED TOPICS FROM CUSTOM CSV =====
        if ($csvCount > 0) {
            $remainCustom = array_slice($customTopics, $csvCount);
            $fp = @fopen($csvPath, 'w');
            if ($fp) {
                fputcsv($fp, ['Topic']);
                foreach ($remainCustom as $t) fputcsv($fp, [$t]);
                fclose($fp);
            }
        }
        
        $csvMsg = $csvCount > 0 ? " ($csvCount from Custom Topics CSV, $researchCount from AI Research)" : '';
        $srcMsg = " Keywords are Google Keyword Planner only (exact volume + difficulty). AI did not invent keywords.";
        jsonResponse(['success' => true, 'campaign_id' => $campaignId, 'articles' => count($roadmapRows), 'email_sent' => $sent, 'from_csv' => $csvCount, 'from_research' => $researchCount, 'keyword_source' => $keywordSource, 'suggested_roadmap' => $roadmapRows, 'message' => ($sent ? 'Roadmap created and approval email sent.' : 'Roadmap created. Brevo email not sent.') . $csvMsg . $srcMsg]);
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
        $neededCount = $days * $perDay;

        // ===== READ CUSTOM TOPICS CSV FIRST =====
        $csvPath = customTopicsCsvPath();
        $customTopics = [];
        if (file_exists($csvPath)) {
            $fpCt = @fopen($csvPath, 'r');
            if ($fpCt) {
                fgetcsv($fpCt);
                while (($rowCt = fgetcsv($fpCt)) !== false) {
                    if (!empty($rowCt[0]) && trim($rowCt[0]) !== '') $customTopics[] = trim($rowCt[0]);
                }
                fclose($fpCt);
            }
        }

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
        $nowYear = date('Y');
        $nowMonth = date('F');
        $nowYear = date('Y');
        // Generate MANY diverse topic angles — enough for days * posts_per_day
        $neededCount = $days * $perDay;
        $topicAngles = [
            "benefits of $seed for businesses $nowYear",
            "how $seed improves ROI and efficiency",
            "$seed vs traditional methods comparison $nowYear",
            "top $seed strategies that actually work",
            "why $seed is trending in $nowYear",
            "$seed implementation step by step guide",
            "common $seed mistakes and how to avoid them",
            "$seed cost analysis and budgeting tips",
            "best tools and resources for $seed",
            "how to measure $seed performance results",
            "$seed case studies and success stories",
            "future of $seed what experts predict",
            "$seed for beginners complete starter guide",
            "advanced $seed techniques for professionals",
            "$seed automation and time saving tips",
            "integrating $seed with existing workflows",
            "$seed compliance and best practices",
            "how $seed drives customer engagement",
            "scaling $seed for enterprise growth",
            "$seed troubleshooting and problem solving",
            "outsourcing vs in-house $seed management",
            "$seed data analysis and reporting methods",
            "building a $seed focused team from scratch",
            "$seed security considerations and safeguards",
            "how $seed impacts long term brand positioning",
            "$seed productivity hacks for busy teams",
            "sustainable $seed practices for growth",
            "maximizing $seed returns on limited budgets",
            "$seed workflow optimization techniques",
            "$seed expert roundup and industry insights",
            "deep dive into $seed analytics and metrics",
            "quick wins with $seed for immediate results",
            "myth busting common $seed misconceptions",
            "behind the scenes of $seed implementation",
            "practical $seed playbook for teams",
            "$seed decision framework for leaders",
            "real world $seed applications and examples",
            "$seed tool comparison and review",
            "how to pitch $seed to stakeholders",
            "$seed roadmap for next 12 months",
        ];
        // Add country-specific topics if not default
        if (!empty($country) && $country !== 'India') {
            $topicAngles[] = "$seed market landscape in $country $nowYear";
            $topicAngles[] = "how $country regulations affect $seed";
            $topicAngles[] = "$seed opportunities unique to $country";
            $topicAngles[] = "comparing $seed approaches in $country vs global";
        }
        $base = array_merge($base, $topicAngles);

        $db = getDB();
        $now = nowString();
        
        // ===== STRICT TOPIC DEDUP: Load ALL existing topics before creating campaign =====
        $existingTopics = getAllUsedTopics($db, $userId);
        // Dedup custom CSV topics against existing ones
        $customTopics = array_values(array_filter($customTopics, fn($t) => !isTopicDuplicate($t, $t, $existingTopics)));
        $csvCount = min(count($customTopics), $neededCount);
        $selectedCsvTopics = array_slice($customTopics, 0, $csvCount);
        $researchCount = max(0, $neededCount - $csvCount);
        // Filter base topics against existing ones
        $base = array_filter($base, function($topic) use ($existingTopics) {
            return !isTopicDuplicate($topic, $topic, $existingTopics);
        });
        $base = array_values($base);
        // Prepend CSV topics to base (they get used first)
        $csvResearchedPlans = []; // Will hold AI-researched plan objects for CSV topics
        if (!empty($selectedCsvTopics)) {
            $chatVaultDemo = SecurityVault::getApiCredentials($userId, 'chat_api');
            if (!empty($chatVaultDemo['api_key'])) {
                $pageContextDemo = implode("\n", array_map(fn($p) => "URL: {$p['page_url']} | Page topic: {$p['page_title']}", array_slice($pages, 0, 50)));
                $csvTopicsListDemo = implode("\n", array_map(fn($i, $t) => ($i + 1) . '. ' . $t, array_keys($selectedCsvTopics), $selectedCsvTopics));
                $countryNoteDemo = ($country !== 'India' && $country !== '') ? " Target country is $country." : "";
                $csvDemoPrompt = "You are an SEO research strategist. For the website $domain, research these topics deeply:\n$csvTopicsListDemo\n\nFor EACH topic: find the best primary keyword, 5-8 supporting keywords, 1-2 most related client pages from the list below, at least 4 REAL authority site URLs SPECIFICALLY related to the topic, detailed headings (H1, H2s, H3s), and 2 image prompts. Current year: $nowYear. Do NOT include month in titles.$countryNoteDemo\n\nClient pages:\n$pageContextDemo\n\nReturn ONLY valid JSON: {\"articles\":[{\"title\":\"...\",\"primary_keyword\":\"...\",\"keywords\":[{\"keyword\":\"...\",\"volume\":0,\"difficulty\":\"\",\"intent\":\"...\"}],\"internal_links\":[{\"url\":\"...\",\"anchor_text\":\"...\",\"reason\":\"...\"}],\"external_links\":[{\"url\":\"...\",\"anchor_text\":\"...\",\"reason\":\"...\"}],\"headings\":{\"H1\":\"...\",\"H2\":[\"...\"],\"H3\":[\"...\"]},\"image_prompts\":[\"...\"]}]}";
                $csvDemoResult = AIProviderClient::chat($chatVaultDemo, $csvDemoPrompt);
                if (!empty($csvDemoResult['success']) && !empty($csvDemoResult['content'])) {
                    $csvDemoRaw = trim($csvDemoResult['content']);
                    $csvDemoRaw = str_replace(['```json', '```'], '', $csvDemoRaw);
                    $csvResearchedPlans = json_decode(trim($csvDemoRaw), true)['articles'] ?? [];
                }
            }
            // Always prepend CSV topic strings to base (used as fallback if AI research fails)
            $base = array_merge($selectedCsvTopics, $base);
        }
        // Ensure we have at least $neededCount topics
        if (count($base) < $neededCount) {
            // Generate more unique variations
            $extraAngles = ['expert roundup', 'deep dive analysis', 'quick wins', 'myth busting', 'behind the scenes', 'industry secrets', 'practical playbook', 'decision framework', 'checklist edition', 'real world applications'];
            $i = 0;
            while (count($base) < $neededCount && $i < 50) {
                $angle = $extraAngles[$i % count($extraAngles)];
                $newTopic = "$seed $angle insights $nowYear";
                if (!isTopicDuplicate($newTopic, $seed, $existingTopics) && !in_array(strtolower($newTopic), array_map('strtolower', $base))) {
                    $base[] = $newTopic;
                }
                $i++;
            }
        }
        // PRESERVE APPROVED ITEMS: Only archive campaigns with no approved/finalized items
        $db->exec("UPDATE approval_tokens SET decision = 'Expired' WHERE user_id = $userId AND decision IN ('Pending','Provisional') AND campaign_item_id NOT IN (SELECT id FROM campaign_items WHERE plan_status IN ('Approved','Provisional Approved') OR article_status IN ('HTML Ready','Final Article Approved'))");
        $db->exec("UPDATE campaigns SET status = 'Archived' WHERE user_id = $userId AND status = 'Roadmap Review' AND id NOT IN (SELECT DISTINCT campaign_id FROM campaign_items WHERE plan_status IN ('Approved','Provisional Approved') OR article_status IN ('HTML Ready','Final Article Approved'))");

        $startDate = $input['start_date'] ?? date('Y-m-d');
        $postingTimes = $input['posting_times'] ?? ['10:00'];
        $targetPlatform = $input['target_platform'] ?? 'blogger';
        $keywordSource = 'planner';
        $workflowMode = (($input['workflow_mode'] ?? 'manual') === 'auto') ? 'auto' : 'manual';

        $stmt = $db->prepare('INSERT INTO campaigns (user_id, slot_number, domain_url, target_country, language_code, days, posts_per_day, status, start_date, posting_times, target_platform, created_at, workflow_mode, keyword_source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $activeSlot, $domain, $country, $language, $days, $perDay, $workflowMode === 'auto' ? 'Auto Running' : 'Roadmap Review', $startDate, json_encode($postingTimes), $targetPlatform, $now, $workflowMode, $keywordSource]);
        $campaignId = $db->lastInsertId();
        if ($workflowMode === 'auto') {
            saveAutoBlogJob($userId, $activeSlot, $campaignId, $domain, $country, $language, $days, $perDay, $startDate, $postingTimes, $targetPlatform, $keywordSource);
        }

        for ($day = 1; $day <= $days; $day++) {
            for ($post = 1; $post <= $perDay; $post++) {
                $idx = ($day - 1) * $perDay + ($post - 1);
                $kw = $base[$idx % count($base)];
                
                // Check if this is a CSV topic that was AI-researched
                $csvPlan = null;
                if (!empty($csvResearchedPlans) && $idx < count($csvResearchedPlans)) {
                    $csvPlan = $csvResearchedPlans[$idx];
                }
                
                $page = $pages[$idx % max(1, count($pages))] ?? ['page_url' => $domain, 'page_title' => 'relevant service page'];
                
                // If we have AI-researched plan for this CSV topic, use its rich data
                if (!empty($csvPlan)) {
                    $headings = $csvPlan['headings'] ?? ['H1' => ucwords($kw), 'H2' => ['Overview', 'Key Insights', 'How to Apply', 'FAQ'], 'H3' => ['Details', 'Common Questions']];
                    $internal = $csvPlan['internal_links'] ?? [['url' => $domain, 'anchor_text' => 'client website']];
                    $external = $csvPlan['external_links'] ?? array_slice(array_map(fn($p) => ['url' => $p['page_url'], 'anchor_text' => $p['page_title'] ?: basename($p['page_url'])], $pages), 0, 4) ?: [['url' => $domain, 'anchor_text' => 'Customer Website']];
                    $prompts = $csvPlan['image_prompts'] ?? ["Editorial image for $kw, professional, no text.", "Practical scene for $kw, authentic style."];
                    $title = $csvPlan['title'] ?? ucwords($kw);
                    $primaryKw = $kw;
                    $kws = [];
                    $gkwCsv = fetchKeywordPlannerKeywords($userId, [$kw, $title], $country, $language);
                    if (empty($gkwCsv['success'])) {
                        jsonResponse(['error' => 'Keyword Planner is required. It failed: ' . ($gkwCsv['error'] ?? 'unknown')], 400);
                    }
                    $kws = array_slice(plannerRowsToKeywordData($gkwCsv['keywords'] ?? []), 0, 5);
                    if (empty($kws[0]['keyword'])) {
                        jsonResponse(['error' => 'Keyword Planner returned no keywords for: ' . $kw], 400);
                    }
                    $primaryKw = $kws[0]['keyword'];
                } else {
                $title = '';
                $primaryKw = '';
                $articleKeywords = array_slice(array_merge(array_slice($base, $idx % count($base)), array_slice($base, 0, $idx % count($base))), 0, 8);

                $kws = [];
                $gkwResult = fetchKeywordPlannerKeywords($userId, [$kw, $title ?: $kw], $country, $language);
                    if (empty($gkwResult['success'])) {
                        jsonResponse(['error' => 'Keyword Planner is required. It failed: ' . ($gkwResult['error'] ?? 'unknown')], 400);
                    }
                    $kws = array_slice(plannerRowsToKeywordData($gkwResult['keywords'] ?? []), 0, 5);
                    if (empty($kws[0]['keyword'])) {
                        jsonResponse(['error' => 'Keyword Planner returned no keywords for: ' . $kw], 400);
                    }
                    $primaryKw = $kws[0]['keyword'];
                    if ($title === '') $title = ucwords($kw);
                    error_log("[Demo Campaign] Keyword Planner for '$kw' primary=" . $primaryKw . " vol=" . ($kws[0]['volume'] ?? 0));
                $headings = ['H1' => ucwords($kw), 'H2' => ['Overview and practical use', 'What competitors miss', 'How to choose the right option', 'Frequently Asked Questions'], 'H3' => ['Costs and examples', 'Common mistakes', 'Implementation steps'], 'H4' => ['Checklist', 'Useful references']];
                $relatedPages = [];
                for ($offset = 0; $offset < min(3, count($pages)); $offset++) {
                    $candidate = $pages[($idx + $offset) % count($pages)];
                    if (!in_array($candidate['page_url'], array_column($relatedPages, 'page_url'))) $relatedPages[] = $candidate;
                }
                $internal = array_map(fn($x) => ['url' => $x['page_url'], 'anchor_text' => $x['page_title'] ?: 'related website page'], $relatedPages) ?: [['url' => $domain, 'anchor_text' => 'customer website']];
                // Use crawled customer website pages as external references (not hardcoded)
                // Only use pages that actually exist on the customer's website
                $external = [];
                $allCrawledUrls = [];
                foreach ($pages as $cp) {
                    $cpUrl = $cp['page_url'] ?? '';
                    $cpTitle = $cp['page_title'] ?? '';
                    if (!empty($cpUrl) && $cpUrl !== $domain && !in_array($cpUrl, $allCrawledUrls)) {
                        $allCrawledUrls[] = $cpUrl;
                        $external[] = ['url' => $cpUrl, 'anchor_text' => $cpTitle ?: basename($cpUrl)];
                    }
                }
                // If not enough customer pages, add the domain itself
                if (count($external) < 4) {
                    $external[] = ['url' => $domain, 'anchor_text' => 'Customer Website'];
                }
                $prompts = ["Editorial photograph illustrating $kw, natural lighting, no text, no logos, professional magazine style.", "Practical real-world scene related to $kw, authentic people and setting, no text or logos."];
                    if (empty($title)) $title = ucwords($kw);
                    if (empty($primaryKw)) $primaryKw = $kw;
                }

                $schedDate = (new DateTime($startDate))->modify(($day - 1) . ' days')->format('Y-m-d');
                $schedTime = $postingTimes[min($post - 1, count($postingTimes) - 1)] ?? '10:00';

                $stmt = $db->prepare('INSERT INTO campaign_items (campaign_id, day_number, post_number, title, primary_keyword, keyword_data, internal_links, external_links, headings, image_prompts, video_url, plan_status, article_status, scheduled_date, scheduled_time, target_platform, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$campaignId, $day, $post, $title, $primaryKw, json_encode($kws), json_encode($internal), json_encode($external), json_encode($headings), json_encode($prompts), '', $workflowMode === 'auto' ? 'Approved' : 'Pending', 'Not Created', $schedDate, $schedTime, $targetPlatform, $now]);
                $itemId = $db->lastInsertId();
                if ($workflowMode !== 'auto') {
                $token = generateToken();
                $stmt = $db->prepare('INSERT INTO approval_tokens (user_id, campaign_item_id, approval_type, token, created_at) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$userId, $itemId, 'roadmap', $token, $now]);
                }
                
                // Record topic to persistent JSON + CSV for dedup
                addTopicToJsonFile($title, $primaryKw, $domain, 'pending', $campaignId);
                addTopicToCsv($title, $primaryKw, $domain, 'pending', $campaignId, $now);
                // Also record to DB table for faster dedup queries
                try {
                    $stmt = $db->prepare('INSERT OR IGNORE INTO created_blog_topics (user_id, title, primary_keyword, domain_url, campaign_id, created_at) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$userId, $title, $primaryKw, $domain, $campaignId, $now]);
                } catch (Exception $e) {}
            }
        }

        // Auto-sync all topics to CSV after campaign creation
        syncTopicsCsv();

        // Build and send rich approval email with full draft content
        $stmt = $db->prepare('SELECT * FROM campaign_items WHERE campaign_id = ? ORDER BY day_number, post_number');
        $stmt->execute([$campaignId]);
        $allItems = $stmt->fetchAll();
        $campaignRow = ['domain_url' => $domain, 'days' => $days, 'posts_per_day' => $perDay];
        $sent = false;
        if ($workflowMode !== 'auto') {
            $richEmailHtml = buildRichApprovalEmailHtml($allItems, $campaignRow, $db);
            $sent = sendApprovalEmail($userId, 'Your AutoBlog Roadmap Draft — Approve or Disapprove Each Article', $richEmailHtml);
        }
        
        // ===== REMOVE USED TOPICS FROM CUSTOM CSV =====
        if ($csvCount > 0) {
            $remainCustom = array_slice($customTopics, $csvCount);
            $fpRm = @fopen($csvPath, 'w');
            if ($fpRm) {
                fputcsv($fpRm, ['Topic']);
                foreach ($remainCustom as $t) fputcsv($fpRm, [$t]);
                fclose($fpRm);
            }
        }
        
        $csvMsg = $csvCount > 0 ? " ($csvCount from Custom Topics CSV, " . ($days * $perDay - $csvCount) . " from Demo)" : '';
        $srcMsg = ' Keywords are Google Keyword Planner only (exact volume + difficulty).';
        jsonResponse(['success' => true, 'campaign_id' => $campaignId, 'items' => $days * $perDay, 'email_sent' => $sent, 'from_csv' => $csvCount, 'keyword_source' => $keywordSource, 'base_url' => APP_BASE_URL, 'message' => ($sent ? 'Roadmap created and approval email sent.' : 'Roadmap created locally.') . $csvMsg . $srcMsg]);
    }

    // Demo campaign status — Human Article Writer only (manual campaigns)
    if ($uri === '/api/demo/campaign-status') {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM campaigns WHERE user_id = ? AND (workflow_mode IS NULL OR workflow_mode = 'manual') ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId]);
        $campaign = $stmt->fetch();
        if (!$campaign) jsonResponse(['campaign' => null, 'items' => []]);

        $stmt = $db->prepare('SELECT id, day_number, post_number, title, primary_keyword, keyword_data, plan_status, article_status, html_path, scheduled_date, scheduled_time, target_platform, last_error, html_retry_count FROM campaign_items WHERE campaign_id = ? ORDER BY day_number, post_number');
        $stmt->execute([$campaign['id']]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $stmt2 = $db->prepare("SELECT token FROM approval_tokens WHERE campaign_item_id = ? AND approval_type = 'roadmap' AND decision IN ('Pending','Provisional') ORDER BY id DESC LIMIT 1");
            $stmt2->execute([$row['id']]);
            $tok = $stmt2->fetch();
            $row['approval_token'] = $tok ? $tok['token'] : '';

            // HTML approval token for in-dashboard HTML approve/disapprove
            $stmt3 = $db->prepare("SELECT token FROM approval_tokens WHERE campaign_item_id = ? AND approval_type = 'html' AND decision = 'Pending' ORDER BY id DESC LIMIT 1");
            $stmt3->execute([$row['id']]);
            $htmlTok = $stmt3->fetch();
            $row['html_approval_token'] = $htmlTok ? $htmlTok['token'] : '';
        }
        jsonResponse(['campaign' => $campaign, 'items' => $rows]);
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
        $htmlFilePath = resolveCampaignHtmlFile($item['html_path'] ?? '');
        
        $articleContent = '';
        if ($htmlFilePath) {
            $fullHtml = file_get_contents($htmlFilePath);
            // Extract <article> content for Blogger
            if (preg_match('#<article[^>]*>(.*?)</article>#is', $fullHtml, $artMatch)) {
                $articleContent = trim($artMatch[1]);
            } else {
                $articleContent = $fullHtml;
            }
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
            
            $result = Publisher::publishBlogger($userId, $blogId, $title, $articleContent, $clientId, $clientSecret, $refreshToken);
        } elseif ($platform === 'wordpress') {
            $vault = SecurityVault::getApiCredentials($userId, 'wordpress_api');
            $result = Publisher::publishWordpress($userId, $vault['wp_site_url'] ?? '', $vault['wp_username'] ?? '', $vault['wp_app_password'] ?? '', $title, $articleContent);
        } elseif ($platform === 'website') {
            $result = publishToWebsiteBlog($item, $title, $articleContent);
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
            $htmlFilePath = resolveCampaignHtmlFile($item['html_path'] ?? '');
            $articleContent = '';
            if ($htmlFilePath) {
                $fullHtml = file_get_contents($htmlFilePath);
                if (preg_match('#<article[^>]*>(.*?)</article>#is', $fullHtml, $artMatch)) {
                    $articleContent = trim($artMatch[1]);
                } else { $articleContent = $fullHtml; }
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
                $result = Publisher::publishBlogger($userId, $blogId, $title, $articleContent, $clientId, $clientSecret, $refreshToken);
            } elseif ($platform === 'wordpress') {
                $vault = SecurityVault::getApiCredentials($userId, 'wordpress_api');
                $result = Publisher::publishWordpress($userId, $vault['wp_site_url'] ?? '', $vault['wp_username'] ?? '', $vault['wp_app_password'] ?? '', $title, $articleContent);
            } elseif ($platform === 'website') {
                $result = publishToWebsiteBlog($item, $title, $articleContent, $scheduledStr);
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
        
        // Future schedule — schedule DIRECTLY on Blogger (skip cron since it doesn't work)
        // Load the HTML content
        $schedHtmlFilePath = null;
        if (!empty($item['html_path'])) {
            $schedPathPatterns = [
                dirname(__DIR__) . ltrim($item['html_path'], '/'),
                OUTPUT_DIR . '/../' . ltrim($item['html_path'], '/'),
                OUTPUT_DIR . '/demo/' . basename($item['html_path']),
                dirname(__DIR__) . '/published_posts/demo/' . basename($item['html_path']),
            ];
            foreach ($schedPathPatterns as $p) { if (file_exists($p)) { $schedHtmlFilePath = $p; break; } }
        }
        $schedArticleContent = '';
        if ($schedHtmlFilePath) {
            $schedFullHtml = file_get_contents($schedHtmlFilePath);
            if (preg_match('#<article[^>]*>(.*?)</article>#is', $schedFullHtml, $schedArtMatch)) {
                $schedArticleContent = trim($schedArtMatch[1]);
            } else { $schedArticleContent = $schedFullHtml; }
        }
        
        $schedTitle = $item['title'];
        $schedDirectResult = null;
        
        if ($platform === 'blogger' && !empty($schedArticleContent)) {
            $schedVault = SecurityVault::getApiCredentials($userId, 'blogger_api');
            $schedBlogId = $schedVault['blogger_blog_id'] ?? '';
            $schedClientId = $schedVault['client_id'] ?? '';
            $schedClientSecret = $schedVault['client_secret'] ?? '';
            $schedRefreshToken = $schedVault['refresh_token'] ?? '';
            
            if (!empty($schedBlogId) && !empty($schedRefreshToken)) {
                // Schedule DIRECTLY on Blogger with publishDate
                $schedDirectResult = Publisher::publishBlogger($userId, $schedBlogId, $schedTitle, $schedArticleContent, $schedClientId, $schedClientSecret, $schedRefreshToken, $scheduledStr);
                if (!empty($schedDirectResult['success'])) {
                    $stmt = $db->prepare("UPDATE campaign_items SET article_status = 'Scheduled', scheduled_date = ?, scheduled_time = ? WHERE id = ?");
                    $stmt->execute([$schedDate, $schedTime, $itemId]);
                    $stmt = $db->prepare("UPDATE scheduled_queue SET status = 'Published' WHERE topic_title = ? AND user_id = ? AND status = 'Scheduled'");
                    $stmt->execute([$schedTitle, $userId]);
                    jsonResponse(['success' => true, 'message' => "Scheduled directly on Blogger for $scheduledStr. " . ($schedDirectResult['message'] ?? ''), 'url' => $schedDirectResult['url'] ?? '']);
                }
            }
        } elseif ($platform === 'wordpress' && !empty($schedArticleContent)) {
            $schedWpVault = SecurityVault::getApiCredentials($userId, 'wordpress_api');
            $schedDirectResult = Publisher::publishWordpress($userId, $schedWpVault['wp_site_url'] ?? '', $schedWpVault['wp_username'] ?? '', $schedWpVault['wp_app_password'] ?? '', $schedTitle, $schedArticleContent);
            if (!empty($schedDirectResult['success'])) {
                $stmt = $db->prepare("UPDATE campaign_items SET article_status = 'Published' WHERE id = ?");
                $stmt->execute([$itemId]);
                jsonResponse(['success' => true, 'message' => 'Published to WordPress.', 'url' => $schedDirectResult['url'] ?? '']);
            }
        } elseif ($platform === 'website') {
            if (empty($schedArticleContent)) {
                $schedArticleContent = loadCampaignArticleContent($item);
            }
            if (empty($schedArticleContent)) {
                jsonResponse(['success' => false, 'error' => 'HTML is created in the dashboard but the file was not found on disk. Click Generate All HTML, then publish again. Path: ' . ($item['html_path'] ?? 'none')], 400);
            }
            $schedDirectResult = publishToWebsiteBlog($item, $schedTitle, $schedArticleContent, $scheduledStr);
            if (!empty($schedDirectResult['success'])) {
                $stmt = $db->prepare("UPDATE campaign_items SET article_status = ?, scheduled_date = ?, scheduled_time = ? WHERE id = ?");
                $stmt->execute([$schedDirectResult['status'] === 'scheduled' ? 'Scheduled' : 'Published', $schedDate, $schedTime, $itemId]);
                jsonResponse(['success' => true, 'message' => $schedDirectResult['message'], 'url' => $schedDirectResult['url'] ?? '']);
            }
        }
        
        // Fallback: add to scheduled_queue as backup
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
        
        $schedExtraMsg = !empty($schedDirectResult['error']) ? ' Direct Blogger scheduling failed: ' . $schedDirectResult['error'] . ' - added to cron queue as backup.' : '';
        jsonResponse(['success' => true, 'message' => "Scheduled for $scheduledStr on $platform. Click Publish Now to publish immediately." . $schedExtraMsg]);

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

        $stmt = $db->prepare("SELECT id FROM campaigns WHERE user_id = ? AND status = 'Roadmap Review' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$tok['user_id']]);
        $active = $stmt->fetch();
        if (!$item || !$active || $item['campaign_id'] != $active['id']) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<h2>This approval email belongs to an older campaign and is no longer active.</h2>';
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

                // Auto-generate HTML article
                $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
                $stmt->execute([$item['id']]);
                $freshItem = $stmt->fetch();
                $htmlResult = generateArticleHtmlReliable($freshItem, $tok['user_id'], $activeSlot, $db);
                if (!empty($htmlResult['success'])) {
                    $stmt = $db->prepare("UPDATE campaign_items SET article_status = 'HTML Ready', html_path = ?, last_error = NULL WHERE id = ?");
                    $stmt->execute([$htmlResult['html_path'], $item['id']]);
                    $htmlToken = generateToken();
                    $stmt = $db->prepare('INSERT INTO approval_tokens (user_id, campaign_item_id, approval_type, token, created_at) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$tok['user_id'], $item['id'], 'html', $htmlToken, $now]);
                    $previewEmailHtml = buildHtmlPreviewEmailHtml($freshItem, $htmlResult['html_path'], $htmlToken, $htmlResult['used_chat_api']);
                    sendApprovalEmail($tok['user_id'], 'Blog HTML Preview - ' . escapeHtml($freshItem['title']), $previewEmailHtml);
                } else {
                    $stmt = $db->prepare("UPDATE campaign_items SET last_error = ?, html_retry_count = COALESCE(html_retry_count,0)+1 WHERE id = ?");
                    $stmt->execute([$htmlResult['error'] ?? 'HTML generation failed after approve', $item['id']]);
                }
                header('Location: ' . APP_BASE_URL . '/api/demo/approval-result?status=approved');
            } else {
                // Disapprove: IMMEDIATELY create replacement (don't wait)
                $stmt = $db->prepare('UPDATE campaign_items SET plan_status = ? WHERE id = ?');
                $stmt->execute(['Provisional Disapproved', $item['id']]);

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

            // Generate HTML article
            $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
            $stmt->execute([$item['id']]);
            $freshItem = $stmt->fetch();
            $htmlResult = generateArticleHtmlReliable($freshItem, $tok['user_id'], $activeSlot, $db);
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

                // ===== DIRECT BLOGGER SCHEDULING =====
                // Try to schedule directly on Blogger (not just cron queue)
                // Cron queue is backup — but we schedule on Blogger immediately if possible
                if ($platform === 'blogger') {
                    $bloggerVault = SecurityVault::getApiCredentials($tok['user_id'], 'blogger_api');
                    $blogId = $bloggerVault['blogger_blog_id'] ?? '';
                    $bClientId = $bloggerVault['client_id'] ?? '';
                    $bClientSecret = $bloggerVault['client_secret'] ?? '';
                    $bRefreshToken = $bloggerVault['refresh_token'] ?? '';
                    
                    if (!empty($blogId) && !empty($bRefreshToken)) {
                        // Load the HTML content
                        $bHtmlFilePath = null;
                        if (!empty($ci['html_path'])) {
                            $bPathPatterns = [
                                dirname(__DIR__) . ltrim($ci['html_path'], '/'),
                                OUTPUT_DIR . '/../' . ltrim($ci['html_path'], '/'),
                                OUTPUT_DIR . '/demo/' . basename($ci['html_path']),
                                dirname(__DIR__) . '/published_posts/demo/' . basename($ci['html_path']),
                            ];
                            foreach ($bPathPatterns as $p) { if (file_exists($p)) { $bHtmlFilePath = $p; break; } }
                        }
                        if ($bHtmlFilePath) {
                            $bFullHtml = file_get_contents($bHtmlFilePath);
                            $bArticleContent = $bFullHtml;
                            if (preg_match('#<article[^>]*>(.*?)</article>#is', $bFullHtml, $bArtMatch)) {
                                $bArticleContent = trim($bArtMatch[1]);
                            }
                            // Schedule directly on Blogger using the publishDate param
                            $bResult = Publisher::publishBlogger($tok['user_id'], $blogId, $ci['title'], $bArticleContent, $bClientId, $bClientSecret, $bRefreshToken, $scheduledStr);
                            if (!empty($bResult['success'])) {
                                // Blogger scheduled successfully — update status
                                $stmt = $db->prepare("UPDATE campaign_items SET article_status = 'Scheduled' WHERE id = ?");
                                $stmt->execute([$tok['campaign_item_id']]);
                                $stmt = $db->prepare("UPDATE scheduled_queue SET status = 'Published' WHERE topic_title = ? AND user_id = ? AND status = 'Scheduled'");
                                $stmt->execute([$ci['title'], $tok['user_id']]);
                            }
                        }
                    }
                }
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
                $regenResult = generateArticleHtmlReliable($reItem, $tok['user_id'], $activeSlot, $db, 'Take a completely different practical angle. Focus on real-world case studies, alternative methods, and contrarian insights. Use different examples and a fresh narrative voice.');
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
    // Generate HTML for all approved items (manual trigger)
    if (preg_match('#^/api/demo/generate-html/(\d+)$#', $uri, $m) && $method === 'POST') {
        $campaignId = $m[1];
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM campaign_items WHERE campaign_id = ? AND plan_status IN ("Approved","Provisional Approved") AND (html_path IS NULL OR html_path = "" OR article_status IS NULL OR article_status = "" OR article_status = "Not Created" OR article_status = "Regenerating HTML") LIMIT 1');
        $stmt->execute([$campaignId]);
        $items = $stmt->fetchAll();
        $generated = [];

        $failed = [];
        $campRow = $db->prepare('SELECT workflow_mode FROM campaigns WHERE id = ?');
        $campRow->execute([$campaignId]);
        $campMode = ($campRow->fetch()['workflow_mode'] ?? 'manual');
        foreach ($items as $item) {
            $htmlResult = generateArticleHtmlReliable($item, $userId, $activeSlot, $db);
            if (!empty($htmlResult['success'])) {
                $stmt = $db->prepare("UPDATE campaign_items SET article_status = 'HTML Ready', html_path = ?, last_error = NULL WHERE id = ?");
                $stmt->execute([$htmlResult['html_path'], $item['id']]);
                if ($campMode !== 'auto') {
                $htmlToken = generateToken();
                $nowG = nowString();
                $stmt = $db->prepare('INSERT INTO approval_tokens (user_id, campaign_item_id, approval_type, token, created_at) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$userId, $item['id'], 'html', $htmlToken, $nowG]);
                $previewEmailHtml = buildHtmlPreviewEmailHtml($item, $htmlResult['html_path'], $htmlToken, $htmlResult['used_chat_api']);
                sendApprovalEmail($userId, 'Blog HTML Preview - ' . escapeHtml($item['title']), $previewEmailHtml);
                }
                $generated[] = ['id' => $item['id'], 'url' => $htmlResult['html_path'], 'title' => $item['title']];
            } else {
                $err = $htmlResult['error'] ?? 'HTML generation failed';
                $stmt = $db->prepare("UPDATE campaign_items SET last_error = ?, html_retry_count = COALESCE(html_retry_count,0)+1 WHERE id = ?");
                $stmt->execute([$err, $item['id']]);
                $failed[] = ['id' => $item['id'], 'title' => $item['title'], 'error' => $err];
            }
        }
        jsonResponse(['success' => true, 'articles' => $generated, 'failed' => $failed, 'message' => count($generated) . ' articles generated. ' . count($failed) . ' failed.']);
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

    // ========== TOPICS CSV — View/Download the Excel-compatible topics file ==========
    if ($uri === '/api/topics-csv' && $method === 'GET') {
        $count = syncTopicsCsv();
        $csvPath = getTopicsCsvPath();
        if (!file_exists($csvPath)) { jsonResponse(['success' => true, 'topics' => [], 'count' => 0]); }
        $topics = [];
        $fp = @fopen($csvPath, 'r');
        if (!$fp) { jsonResponse(['success' => true, 'topics' => [], 'count' => 0]); }
        while (($row = fgetcsv($fp)) !== false) {
            if (count($row) >= 7) $topics[] = ['sno' => intval($row[0]), 'topic' => $row[1], 'keyword' => $row[2], 'domain' => $row[3], 'status' => $row[4], 'campaign_id' => $row[5], 'date' => $row[6]];
        }
        fclose($fp);
        jsonResponse(['success' => true, 'topics' => $topics, 'count' => count($topics)]);
    }

    if ($uri === '/api/topics-csv/download' && $method === 'GET') {
        syncTopicsCsv();
        $csvPath = getTopicsCsvPath();
        if (!file_exists($csvPath) || @filesize($csvPath) === 0) { $fp = @fopen($csvPath, 'w'); if ($fp) { fputcsv($fp, ['S.No', 'Topic (Blog Title)', 'Primary Keyword', 'Domain/Website', 'Status', 'Campaign ID', 'Created Date']); fclose($fp); } }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="autoblog_topics_' . date('Y-m-d') . '.csv"');
        header('Content-Length: ' . filesize($csvPath));
        readfile($csvPath);
        exit;
    }

    if ($uri === '/api/topics-csv/sync' && $method === 'POST') {
        $count = syncTopicsCsv();
        jsonResponse(['success' => true, 'count' => $count, 'message' => "CSV synced with $count topics."]);
    }

    // ========== CUSTOM TOPICS CSV — User-provided topics for blog generation ==========
    if ($uri === '/api/custom-topics' && $method === 'GET') {
        $csvPath = customTopicsCsvPath();
        if (!file_exists($csvPath)) { jsonResponse(['success' => true, 'topics' => [], 'count' => 0]); }
        $topics = [];
        $fp = @fopen($csvPath, 'r');
        if (!$fp) { jsonResponse(['success' => true, 'topics' => [], 'count' => 0]); }
        fgetcsv($fp); // skip header
        while (($row = fgetcsv($fp)) !== false) {
            if (!empty($row[0]) && trim($row[0]) !== '') $topics[] = trim($row[0]);
        }
        fclose($fp);
        jsonResponse(['success' => true, 'topics' => $topics, 'count' => count($topics)]);
    }

    if ($uri === '/api/custom-topics/upload' && $method === 'POST') {
        $topicsList = $input['topics'] ?? [];
        if (!is_array($topicsList)) $topicsList = [$topicsList];
        $csvPath = customTopicsCsvPath();
        // Ensure data directory exists
        $dataDir = dirname($csvPath);
        if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
        $fp = @fopen($csvPath, 'w');
        if (!$fp) jsonResponse(['success' => false, 'error' => 'Cannot write to custom_topics.csv — check data/ folder permissions.'], 500);
        fputcsv($fp, ['Topic']);
        $savedCount = 0;
        foreach ($topicsList as $t) {
            $t = trim($t);
            if (!empty($t)) { fputcsv($fp, [$t]); $savedCount++; }
        }
        fclose($fp);
        jsonResponse(['success' => true, 'count' => $savedCount, 'message' => $savedCount . ' topics saved to custom topics CSV.']);
    }

    if ($uri === '/api/custom-topics/remove' && $method === 'POST') {
        $removeTopics = $input['topics'] ?? [];
        if (!is_array($removeTopics)) $removeTopics = [$removeTopics];
        $csvPath = customTopicsCsvPath();
        $existing = [];
        if (file_exists($csvPath)) {
            $fp = @fopen($csvPath, 'r');
            if ($fp) {
                fgetcsv($fp);
                while (($row = fgetcsv($fp)) !== false) {
                    if (!empty($row[0])) $existing[] = trim($row[0]);
                }
                fclose($fp);
            }
        }
        $remaining = array_values(array_filter($existing, fn($t) => !in_array($t, $removeTopics)));
        $fp = @fopen($csvPath, 'w');
        if (!$fp) jsonResponse(['success' => false, 'error' => 'Cannot write to custom_topics.csv'], 500);
        fputcsv($fp, ['Topic']);
        foreach ($remaining as $t) fputcsv($fp, [$t]);
        fclose($fp);
        jsonResponse(['success' => true, 'removed' => count($removeTopics), 'remaining' => count($remaining), 'message' => 'Removed ' . count($removeTopics) . ' topics. ' . count($remaining) . ' remaining.']);
    }

    // ========== GENERATE CAMPAIGN FROM CUSTOM TOPICS CSV ==========
    if ($uri === '/api/custom-topics/generate-campaign' && $method === 'POST') {
        $domain = trim($input['domain_url'] ?? '');
        $country = $input['country'] ?? 'India';
        $language = $input['language_code'] ?? 'en';
        $days = intval($input['days'] ?? 7);
        $perDay = intval($input['posts_per_day'] ?? 1);
        $startDate = $input['start_date'] ?? date('Y-m-d');
        $postingTimes = $input['posting_times'] ?? ['10:00'];
        $targetPlatform = $input['target_platform'] ?? 'blogger';
        $neededCount = $days * $perDay;
        
        if (empty($domain)) jsonResponse(['error' => 'Website URL is required.'], 400);
        
        $db = getDB();
        $now = nowString();
        $nowYear = date('Y');
        
        // Load custom topics CSV
        $csvPath = customTopicsCsvPath();
        $customTopics = [];
        if (file_exists($csvPath)) {
            $fp = @fopen($csvPath, 'r');
            if ($fp) {
                fgetcsv($fp);
                while (($row = fgetcsv($fp)) !== false) {
                    if (!empty($row[0]) && trim($row[0]) !== '') $customTopics[] = trim($row[0]);
                }
                fclose($fp);
            }
        }
        
        // Dedup custom topics against existing used topics
        $existingTopics = getAllUsedTopics($db, $userId);
        $customTopics = array_values(array_filter($customTopics, fn($t) => !isTopicDuplicate($t, $t, $existingTopics)));
        
        $csvCount = min(count($customTopics), $neededCount);
        $researchCount = max(0, $neededCount - $csvCount);
        
        // Take topics from custom CSV first
        $selectedTopics = array_slice($customTopics, 0, $csvCount);
        
        // If we need more, do AI research
        $researchedTopics = [];
        if ($researchCount > 0) {
            $chatVault = SecurityVault::getApiCredentials($userId, 'chat_api');
            if (!empty($chatVault['api_key'])) {
                $usedListStr = implode(', ', array_merge($selectedTopics, array_map(fn($et) => $et['topic'] ?? '', $existingTopics)));
                $countryNote = ($country !== 'India' && $country !== '') ? " Target country is $country — make topics specific to $country's market." : "";
                $researchPrompt = "I need $researchCount UNIQUE article topics for the website $domain. I already have these topics covered: $usedListStr. Give me $researchCount completely different topics that are NOT similar to any of the above. Think broadly: different blogs, industry angles, tool reviews, case studies, comparisons, FAQs, myths, regional opportunities, technology trends, compliance, ROI analysis, workflow tips. Return ONLY a JSON array of strings, no other text. Example: [\"topic one\",\"topic two\"]$countryNote";
                $chatResult = AIProviderClient::chat($chatVault, $researchPrompt);
                if (!empty($chatResult['success']) && !empty($chatResult['content'])) {
                    $rawRes = trim($chatResult['content']);
                    $rawRes = str_replace(['```json', '```'], '', $rawRes);
                    $researchedRaw = json_decode(trim($rawRes), true);
                    if (is_array($researchedRaw)) {
                        foreach ($researchedRaw as $rt) {
                            if (count($researchedTopics) >= $researchCount) break;
                            if (is_string($rt) && strlen($rt) > 3 && !isTopicDuplicate($rt, $rt, array_merge($existingTopics, [['topic' => $rt, 'keyword' => $rt]]))) {
                                $researchedTopics[] = $rt;
                            }
                        }
                    }
                }
            }
            // If AI research didn't give enough, generate generic ones
            while (count($researchedTopics) < $researchCount) {
                $i = count($researchedTopics) + 1;
                $genTopic = "$domain topic $i $nowYear unique angle";
                if (!isTopicDuplicate($genTopic, $genTopic, $existingTopics)) {
                    $researchedTopics[] = $genTopic;
                } else { break; }
            }
        }
        
        $allTopics = array_merge($selectedTopics, $researchedTopics);
        if (empty($allTopics)) jsonResponse(['error' => 'No topics available. Add topics to the Custom Topics CSV first.'], 400);
        
        // Create campaign
        $pages = ResearchAgent::crawlAndExtractSitePages($domain, $userId);
        
        $stmt = $db->prepare('INSERT INTO campaigns (user_id, slot_number, domain_url, target_country, language_code, days, posts_per_day, status, start_date, posting_times, target_platform, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $activeSlot, $domain, $country, $language, $days, $perDay, 'Roadmap Review', $startDate, json_encode($postingTimes), $targetPlatform, $now]);
        $campaignId = $db->lastInsertId();
        
        for ($day = 1; $day <= $days; $day++) {
            for ($post = 1; $post <= $perDay; $post++) {
                $idx = ($day - 1) * $perDay + ($post - 1);
                $topicTitle = $allTopics[$idx % count($allTopics)] ?? ($allTopics[0] ?? 'Blog Article');
                $kw = $topicTitle;
                $page = $pages[$idx % max(1, count($pages))] ?? ['page_url' => $domain, 'page_title' => 'relevant page'];
                
                $kws = [];
                $planTmp = ['title' => $topicTitle, 'primary_keyword' => $kw, 'keywords' => []];
                $kwFix = requirePlannerKeywordsOnPlan($planTmp, $userId, $country, $language, true);
                if (empty($kwFix['success'])) jsonResponse(['error' => 'Keyword Planner is required. It failed: ' . ($kwFix['error'] ?? 'unknown')], 400);
                $kws = $planTmp['keywords'];
                $kw = $planTmp['primary_keyword'];
                $headings = ['H1' => ucwords($kw), 'H2' => ['Overview and practical context', 'What research reveals', 'How to apply the insights', 'Frequently Asked Questions'], 'H3' => ['Key findings', 'Common misconceptions', 'Action steps']];
                $internal = [['url' => $page['page_url'] ?? $domain, 'anchor_text' => $page['page_title'] ?? 'related website page']];
                // Use crawled customer pages as external links (not hardcoded)
                $external = array_slice(array_map(fn($p) => ['url' => $p['page_url'], 'anchor_text' => $p['page_title'] ?: basename($p['page_url'])], $pages), 0, 4) ?: [['url' => $domain, 'anchor_text' => 'Customer Website']];
                $prompts = ["Editorial image for $kw, professional, no text.", "Practical scene for $kw, authentic style."];
                
                $schedDate = (new DateTime($startDate))->modify(($day - 1) . ' days')->format('Y-m-d');
                $schedTime = $postingTimes[min($post - 1, count($postingTimes) - 1)] ?? '10:00';
                
                $stmt = $db->prepare('INSERT INTO campaign_items (campaign_id, day_number, post_number, title, primary_keyword, keyword_data, internal_links, external_links, headings, image_prompts, video_url, plan_status, article_status, scheduled_date, scheduled_time, target_platform, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$campaignId, $day, $post, ucwords($topicTitle), $kw, json_encode($kws), json_encode($internal), json_encode($external), json_encode($headings), json_encode($prompts), '', 'Approved', 'Not Created', $schedDate, $schedTime, $targetPlatform, $now]);
                $itemId = $db->lastInsertId();
                
                // Generate HTML immediately since items are auto-approved
                $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
                $stmt->execute([$itemId]);
                $freshItem = $stmt->fetch();
                $htmlResult = generateArticleHtmlReliable($freshItem, $userId, $activeSlot, $db);
                if (!empty($htmlResult['success'])) {
                    $stmt = $db->prepare("UPDATE campaign_items SET article_status = 'HTML Ready', html_path = ? WHERE id = ?");
                    $stmt->execute([$htmlResult['html_path'], $itemId]);
                    $htmlToken = generateToken();
                    $stmt = $db->prepare('INSERT INTO approval_tokens (user_id, campaign_item_id, approval_type, token, created_at) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$userId, $itemId, 'html', $htmlToken, $now]);
                }
                
                // Record topic
                addTopicToJsonFile(ucwords($topicTitle), $kw, $domain, 'approved', $campaignId);
                addTopicToCsv(ucwords($topicTitle), $kw, $domain, 'approved', $campaignId, $now);
                try {
                    $stmt = $db->prepare('INSERT OR IGNORE INTO created_blog_topics (user_id, title, primary_keyword, domain_url, campaign_id, created_at) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$userId, ucwords($topicTitle), $kw, $domain, $campaignId, $now]);
                } catch (Exception $e) {}
            }
        }
        
        // Remove used topics from custom CSV
        $remainCustom = array_slice($customTopics, $csvCount);
        $fp = @fopen($csvPath, 'w');
        if ($fp) {
            fputcsv($fp, ['Topic']);
            foreach ($remainCustom as $t) fputcsv($fp, [$t]);
            fclose($fp);
        }
        
        syncTopicsCsv();
        
        $msg = "Campaign created with $neededCount blogs. $csvCount from Custom Topics CSV, $researchCount from AI Research.";
        jsonResponse(['success' => true, 'campaign_id' => $campaignId, 'from_csv' => $csvCount, 'from_research' => $researchCount, 'total' => count($allTopics), 'remaining_in_csv' => count($remainCustom), 'message' => $msg]);
    }

    // ========== RUN CRON NOW — Trigger scheduler + approval timer via web ==========
    if ($uri === '/api/run-scheduler' && $method === 'POST') {
        ob_start();
        // Run approval timer (generates HTML for approved items, auto-schedules)
        $timerPath = __DIR__ . '/cron/approval_timer.php';
        $timerOutput = '';
        if (file_exists($timerPath)) {
            // Simulate POST request environment for the cron script
            $_SERVER['REQUEST_METHOD'] = 'POST';
            ob_start();
            try { include $timerPath; } catch (Exception $e) { $timerOutput .= 'Timer error: ' . $e->getMessage(); }
            $timerOutput .= ob_get_clean();
        }
        // Run scheduler (publishes due items)
        $schedulerPath = __DIR__ . '/cron/scheduler.php';
        $schedulerOutput = '';
        if (file_exists($schedulerPath)) {
            $_SERVER['REQUEST_METHOD'] = 'POST';
            ob_start();
            try { include $schedulerPath; } catch (Exception $e) { $schedulerOutput .= 'Scheduler error: ' . $e->getMessage(); }
            $schedulerOutput .= ob_get_clean();
        }
        $autoOutput = '';
        $autoRes = ['html' => 0, 'published' => 0, 'scheduled' => 0, 'processed' => 0, 'errors' => [], 'message' => ''];
        if (function_exists('processAutoBlogCampaigns')) {
            try {
                $autoRes = processAutoBlogCampaigns(1, 3);
                $autoOutput = json_encode($autoRes);
                if (function_exists('recordAutoCronRun')) {
                    recordAutoCronRun('run_cron_now', $autoRes, $userId);
                }
            } catch (Throwable $e) {
                $autoOutput = 'Auto daily error: ' . $e->getMessage();
                $autoRes['errors'][] = $e->getMessage();
            }
        }
        ob_end_clean();
        // Also publish any scheduled website blog posts
        $websitePubCount = 0;
        list($wpub, $wpubErr) = getBlogPublisher();
        if (!$wpubErr && $wpub) {
            try {
                $wpRes = $wpub->publishScheduled();
                $websitePubCount = $wpRes['published'] ?? 0;
            } catch (Throwable $e) {
                error_log('[Website Blog] Scheduled publish error: ' . $e->getMessage());
            }
        }
        $autoMsg = $autoRes['message'] ?? '';
        jsonResponse([
            'success' => true,
            'timer_output' => $timerOutput,
            'scheduler_output' => $schedulerOutput,
            'auto' => $autoRes,
            'auto_output' => $autoOutput,
            'website_published' => $websitePubCount,
            'last_run_at' => date('Y-m-d H:i:s'),
            'message' => 'Cron ran at ' . date('Y-m-d H:i:s') . '. Auto: ' . $autoMsg . ' Human approval timer + due-queue scheduler also ran.' . ($websitePubCount ? " + {$websitePubCount} website posts." : ''),
        ]);
    }

    // ========== AUTO BLOG — isolated from Human Article Writer ==========
    if ($uri === '/api/auto-blog/start' && $method === 'POST') {
        $domain = trim($input['domain_url'] ?? '');
        if ($domain === '') jsonResponse(['error' => 'Website URL is required.'], 400);
        $country = $input['country'] ?? 'India';
        $language = $input['language_code'] ?? 'en';
        $perDay = intval($input['posts_per_day'] ?? 1);
        if (!in_array($perDay, [1, 2, 3], true)) $perDay = 1;
        $startDate = $input['start_date'] ?? date('Y-m-d');
        $noEnd = !empty($input['no_end']) ? 1 : 0;
        $endDate = $noEnd ? null : ($input['end_date'] ?? null);
        $postingTimes = $input['posting_times'] ?? ['10:00'];
        $targetPlatform = $input['target_platform'] ?? 'blogger';
        $now = nowString();
        $db = getDB();
        $stmt = $db->prepare('INSERT INTO campaigns (user_id, slot_number, domain_url, target_country, language_code, days, posts_per_day, status, start_date, end_date, no_end, posting_times, target_platform, created_at, workflow_mode, keyword_source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $activeSlot, $domain, $country, $language, 0, $perDay, 'Auto Running', $startDate, $endDate, $noEnd, json_encode($postingTimes), $targetPlatform, $now, 'auto', 'planner']);
        $campaignId = $db->lastInsertId();
        saveAutoBlogJob($userId, $activeSlot, $campaignId, $domain, $country, $language, 0, $perDay, $startDate, $postingTimes, $targetPlatform, 'planner', $endDate, $noEnd, 1);
        $created = 0;
        $htmlMade = 0;
        $errors = [];
        if (function_exists('createNextAutoBlogDraft')) {
            $camp = $db->query('SELECT * FROM campaigns WHERE id = ' . intval($campaignId))->fetch();
            $jobRow = $db->prepare('SELECT * FROM auto_blog_jobs WHERE user_id = ? AND slot_number = ?');
            $jobRow->execute([$userId, $activeSlot]);
            $job = $jobRow->fetch();
            if ($camp && $job) {
                $made = createNextAutoBlogDraft($job, $camp);
                if (!empty($made['created'])) {
                    $created = 1;
                    $it = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
                    $it->execute([$made['item_id']]);
                    $item = $it->fetch();
                    if ($item && function_exists('generateHtmlForCampaignItem')) {
                        $hr = generateHtmlForCampaignItem($item, $userId, $activeSlot, $db);
                        if (!empty($hr['success'])) $htmlMade = 1;
                        else $errors[] = $hr['error'] ?? 'HTML failed';
                    }
                } elseif (!empty($made['error'])) {
                    $errors[] = $made['error'];
                }
            }
        }
        jsonResponse(['success' => true, 'campaign_id' => $campaignId, 'created' => $created, 'html' => $htmlMade, 'errors' => $errors, 'keyword_source' => 'planner', 'message' => ($htmlMade ? 'Auto Blog is Active. First draft HTML was written from Keyword Planner.' : ('Auto Blog is Active. ' . (implode(' ', $errors) ?: 'Cron will create the next daily post from Custom Topics + Keyword Planner.')))]);
    }

    if ($uri === '/api/auto-blog/toggle' && $method === 'POST') {
        $enabled = !empty($input['enabled']) ? 1 : 0;
        $db = getDB();
        $db->prepare('UPDATE auto_blog_jobs SET enabled = ? WHERE user_id = ? AND slot_number = ?')->execute([$enabled, $userId, $activeSlot]);
        $status = $enabled ? 'Auto Running' : 'Paused';
        $db->prepare("UPDATE campaigns SET status = ? WHERE user_id = ? AND workflow_mode = 'auto' AND id IN (SELECT campaign_id FROM auto_blog_jobs WHERE user_id = ? AND slot_number = ?)")->execute([$status, $userId, $userId, $activeSlot]);
        jsonResponse(['success' => true, 'enabled' => $enabled, 'message' => $enabled ? 'Auto Blog is Active.' : 'Auto Blog is Inactive. Daily posting stopped.']);
    }

    if ($uri === '/api/auto-blog/status' && $method === 'GET') {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM campaigns WHERE user_id = ? AND workflow_mode = 'auto' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId]);
        $campaign = $stmt->fetch();
        $items = [];
        if ($campaign) {
            $stmt = $db->prepare('SELECT id, day_number, post_number, title, primary_keyword, keyword_data, plan_status, article_status, html_path, scheduled_date, scheduled_time, target_platform, last_error, html_retry_count FROM campaign_items WHERE campaign_id = ? ORDER BY day_number, post_number');
            $stmt->execute([$campaign['id']]);
            $items = $stmt->fetchAll();
        }
        $cron = function_exists('getLatestAutoCronStatus') ? getLatestAutoCronStatus($userId) : [];
        $job = null;
        try {
            $stmt = $db->prepare('SELECT * FROM auto_blog_jobs WHERE user_id = ? AND slot_number = ?');
            $stmt->execute([$userId, $activeSlot]);
            $job = $stmt->fetch() ?: null;
        } catch (Throwable $e) {}
        jsonResponse(['campaign' => $campaign, 'items' => $items, 'cron' => $cron, 'job' => $job]);
    }

    if ($uri === '/api/auto-blog/run' && $method === 'POST') {
        @set_time_limit(180);
        if (!function_exists('processAutoBlogCampaigns')) {
            jsonResponse(['success' => false, 'error' => 'Auto daily engine missing.'], 500);
        }
        $autoRes = processAutoBlogCampaigns(8, 5);
        if (function_exists('recordAutoCronRun')) {
            recordAutoCronRun('auto_blog_page', $autoRes, $userId);
        }
        jsonResponse(['success' => true, 'auto' => $autoRes, 'last_run_at' => date('Y-m-d H:i:s'), 'message' => $autoRes['message'] ?? 'Auto cron finished.']);
    }

    // ========== GENERATE HTML FOR ONE DRAFT (last step — not cron) ==========
    if (preg_match('#^/api/campaign-item/generate-html/(\d+)$#', $uri, $m) && $method === 'POST') {
        $itemId = intval($m[1]);
        $db = getDB();
        $stmt = $db->prepare('SELECT ci.*, c.user_id AS camp_user_id, c.workflow_mode, c.slot_number FROM campaign_items ci JOIN campaigns c ON c.id = ci.campaign_id WHERE ci.id = ?');
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if (!$item || intval($item['camp_user_id']) !== intval($userId)) jsonResponse(['error' => 'Item not found.'], 404);
        @set_time_limit(180);
        $slot = intval($item['slot_number'] ?? $activeSlot);
        $htmlResult = generateArticleHtmlReliable($item, $userId, $slot, $db);
        if (!empty($htmlResult['success'])) {
            $db->prepare("UPDATE campaign_items SET article_status = 'HTML Ready', html_path = ?, last_error = NULL WHERE id = ?")->execute([$htmlResult['html_path'], $itemId]);
            jsonResponse(['success' => true, 'html_path' => $htmlResult['html_path'], 'title' => $item['title'], 'message' => 'HTML created for ' . $item['title']]);
        }
        $err = $htmlResult['error'] ?? 'HTML generation failed';
        $db->prepare("UPDATE campaign_items SET last_error = ?, html_retry_count = COALESCE(html_retry_count,0)+1 WHERE id = ?")->execute([$err, $itemId]);
        jsonResponse(['success' => false, 'error' => $err, 'title' => $item['title']], 400);
    }

    // ========== DELETE CAMPAIGN ITEM =========="
    if (preg_match('#^/api/campaign-item/delete/(\d+)$#', $uri, $m) && $method === 'POST') {
        $itemId = $m[1];
        $db = getDB();
        // Only allow deleting items that are NOT published
        $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if (!$item) jsonResponse(['error' => 'Item not found.'], 404);
        if ($item['article_status'] === 'Published') jsonResponse(['error' => 'Cannot delete a published article.'], 400);
        // Delete related approval tokens
        $stmt = $db->prepare('DELETE FROM approval_tokens WHERE campaign_item_id = ?');
        $stmt->execute([$itemId]);
        // Delete the item
        $stmt = $db->prepare('DELETE FROM campaign_items WHERE id = ?');
        $stmt->execute([$itemId]);
        jsonResponse(['success' => true, 'message' => 'Item deleted successfully.']);
    }

    // ========== RESEND TOPIC TO CUSTOM TOPICS CSV ==========
    if (preg_match('#^/api/campaign-item/resend-topic/(\d+)$#', $uri, $m) && $method === 'POST') {
        $itemId = $m[1];
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if (!$item) jsonResponse(['error' => 'Item not found.'], 404);
        if ($item['article_status'] === 'Published') jsonResponse(['error' => 'Cannot resend a published article.'], 400);
        
        $topicTitle = trim($item['title'] ?? '');
        if (empty($topicTitle)) jsonResponse(['error' => 'Item has no title to resend.'], 400);
        
        // Add topic back to custom_topics.csv
        $csvPath = customTopicsCsvPath();
        $existingTopics = [];
        if (file_exists($csvPath)) {
            $fpR = @fopen($csvPath, 'r');
            if ($fpR) {
                fgetcsv($fpR); // skip header
                while (($row = fgetcsv($fpR)) !== false) {
                    if (!empty($row[0]) && trim($row[0]) !== '') $existingTopics[] = trim($row[0]);
                }
                fclose($fpR);
            }
        }
        // Don't add if already in CSV
        if (!in_array($topicTitle, $existingTopics)) {
            $existingTopics[] = $topicTitle;
            $fpW = @fopen($csvPath, 'w');
            if ($fpW) {
                fputcsv($fpW, ['Topic']);
                foreach ($existingTopics as $t) fputcsv($fpW, [$t]);
                fclose($fpW);
            }
        }
        
        // Delete the campaign item and its tokens
        $stmt = $db->prepare('DELETE FROM approval_tokens WHERE campaign_item_id = ?');
        $stmt->execute([$itemId]);
        $stmt = $db->prepare('DELETE FROM campaign_items WHERE id = ?');
        $stmt->execute([$itemId]);
        
        jsonResponse(['success' => true, 'message' => "Topic '$topicTitle' resent to Custom Topics CSV. It will be available for future campaigns."]);
    }

    // ========== DOWNLOAD HTML ==========
    if (preg_match('#^api/campaign-item/download-html/(\d+)$#', $uri, $m)) {
        $itemId = $m[1];
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM campaign_items WHERE id = ?');
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if (!$item || empty($item['html_path'])) jsonResponse(['error' => 'No HTML file for this item.'], 404);
        
        $htmlFilePath = null;
        $pathPatterns = [
            dirname(__DIR__) . ltrim($item['html_path'], '/'),
            OUTPUT_DIR . '/../' . ltrim($item['html_path'], '/'),
            OUTPUT_DIR . '/demo/' . basename($item['html_path']),
            dirname(__DIR__) . '/published_posts/demo/' . basename($item['html_path']),
        ];
        foreach ($pathPatterns as $p) {
            if (file_exists($p)) { $htmlFilePath = $p; break; }
        }
        if (!$htmlFilePath) jsonResponse(['error' => 'HTML file not found on disk.'], 404);
        
        $filename = slugify($item['title']) . '.html';
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($htmlFilePath));
        readfile($htmlFilePath);
        exit;
    }

    // ========== WEBSITE BLOG DIAGNOSTIC (JSON) ==========
    if ($uri === '/api/website-blog/test') {
        $paths = [
            'public_html/blog' => dirname(__DIR__) . '/blog/includes/publisher.php',
            'sub_apps/blog' => __DIR__ . '/blog/includes/publisher.php',
            'legacy website_blog' => __DIR__ . '/website_blog/includes/publisher.php',
        ];
        $checked = [];
        $found = null;
        foreach ($paths as $label => $p) {
            $exists = file_exists($p);
            $checked[] = ['label' => $label, 'path' => $p, 'exists' => $exists];
            if ($exists && !$found) $found = $p;
        }
        $cfg = null;
        $cfgPath = null;
        if ($found) {
            $cfgPath = dirname(dirname($found)) . '/config.php';
            if (file_exists($cfgPath)) $cfg = require $cfgPath;
        }
        $autoblogRoot = is_array($cfg) ? ($cfg['autoblog_root'] ?? '') : '';
        $dbFile = $autoblogRoot ? ($autoblogRoot . '/includes/database.php') : '';
        $postsDir = $found ? (dirname(dirname($found)) . '/posts') : '';
        jsonResponse([
            'success' => true,
            'publisher_found' => (bool)$found,
            'publisher_path' => $found,
            'paths_checked' => $checked,
            'config_path' => $cfgPath,
            'autoblog_root' => $autoblogRoot,
            'database_file_exists' => $dbFile ? file_exists($dbFile) : false,
            'database_class_exists' => class_exists('Database'),
            'getDB_exists' => function_exists('getDB'),
            'blog_url' => is_array($cfg) ? ($cfg['site_url'] ?? '') : '',
            'posts_dir' => $postsDir,
            'posts_dir_writable' => $postsDir ? (is_dir($postsDir) ? is_writable($postsDir) : is_writable(dirname($postsDir))) : false,
        ]);
    }

    // 404 for unmatched API routes
    jsonResponse(['error' => 'API endpoint not found'], 404);
}
