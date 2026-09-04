<?php
/**
 * AutoBacklink - Publishers (Lane A: API auto-posting)
 * Blogger API v3 (OAuth), WordPress REST (app password), Ghost Admin API, Webhook.
 * Same call patterns as AutoBlog, adapted for short backlink posts.
 */
require_once __DIR__ . '/helpers.php';

class BacklinkPublisher {

    // ---------------- Blogger (OAuth 2.0) ----------------

    public static function refreshBloggerToken($clientId, $clientSecret, $refreshToken) {
        if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
            return ['success' => false, 'error' => 'Client ID, Client Secret and Refresh Token are all required.'];
        }
        $clientId = trim($clientId);
        if (!str_ends_with(strtolower($clientId), '.apps.googleusercontent.com')) {
            return ['success' => false, 'error' => 'Invalid Client ID — it must end with ".apps.googleusercontent.com" (OAuth 2.0 Client ID from Google Cloud Console, not a Service Account key).'];
        }
        $body = http_build_query([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
        $result = bkHttp('POST', 'https://oauth2.googleapis.com/token',
            ['Content-Type: application/x-www-form-urlencoded'], $body, 15);
        $data = $result['data'] ?? [];
        if ($result['http_code'] === 200 && !empty($data['access_token'])) {
            return ['success' => true, 'access_token' => $data['access_token']];
        }
        $err = $data['error'] ?? '';
        $desc = $data['error_description'] ?? '';
        $hint = '';
        if ($err === 'invalid_grant') $hint = ' Refresh token expired or revoked (in Testing mode tokens expire after 7 days — publish the OAuth app or re-generate the token).';
        if ($err === 'unauthorized_client') $hint = ' Refresh token was created with a different Client ID/Secret than the ones saved here.';
        if ($err === 'invalid_client') $hint = ' Client Secret is wrong (should start with GOCSPX-).';
        return ['success' => false, 'error' => "Google OAuth: $err — $desc.$hint"];
    }

    public static function publishBlogger(array $cred, $title, $html, $imageUrl = '') {
        $blogId = trim($cred['blog_id'] ?? '');
        if (empty($blogId)) return ['success' => false, 'error' => 'Blogger Blog ID is required.'];

        $rf = self::refreshBloggerToken($cred['client_id'] ?? '', $cred['client_secret'] ?? '', $cred['refresh_token'] ?? '');
        if (!$rf['success']) return $rf;

        // Upload image to the blog if a local/remote image is available
        $mediaUrl = '';
        if ($imageUrl && !str_starts_with($imageUrl, 'data:')) {
            $up = self::bloggerUploadImage($rf['access_token'], $blogId, $imageUrl);
            $mediaUrl = $up['url'] ?? '';
        }
        $body = $html;
        if ($mediaUrl && !preg_match('/<img[^>]+src="/i', $body)) {
            $body = '<figure><img src="' . $mediaUrl . '" alt=""></figure>' . $body;
        } elseif ($mediaUrl && preg_match('/<img[^>]+src=""/i', $body)) {
            $body = preg_replace('/<img[^>]+src=""/i', '<img src="' . $mediaUrl . '"', $body, 1);
        }

        $payload = ['kind' => 'blogger#post', 'blog' => ['id' => $blogId], 'title' => $title, 'content' => $body];
        $res = bkHttp('POST', "https://www.googleapis.com/blogger/v3/blogs/$blogId/posts",
            ['Authorization: Bearer ' . $rf['access_token'], 'Content-Type: application/json'],
            json_encode($payload), 30);
        $data = $res['data'] ?? [];
        if (in_array($res['http_code'], [200, 201]) && !empty($data['url'])) {
            return ['success' => true, 'url' => $data['url']];
        }
        return ['success' => false, 'error' => 'Blogger API (' . $res['http_code'] . '): ' . ($data['error']['message'] ?? substr((string)$res['raw'], 0, 300))];
    }

    private static function bloggerUploadImage($accessToken, $blogId, $imageUrl) {
        $res = bkHttp('POST',
            "https://www.googleapis.com/upload/blogger/v3/blogs/$blogId/posts/media",
            ['Authorization: Bearer ' . $accessToken, 'Content-Type: image/jpeg'],
            $imageUrl, 30);
        $data = $res['data'] ?? [];
        if ($res['http_code'] === 200 && !empty($data['url'])) return ['url' => $data['url']];
        return ['url' => ''];
    }

    // ---------------- WordPress (REST + Application Password) ----------------

    public static function publishWordpress(array $cred, $title, $html) {
        $site = rtrim(trim($cred['site_url'] ?? ''), '/');
        $user = trim($cred['username'] ?? '');
        $pass = trim($cred['app_password'] ?? '');
        if (empty($site) || empty($user) || empty($pass)) {
            return ['success' => false, 'error' => 'WordPress site URL, username and application password are required.'];
        }
        $apiUrl = $site . '/wp-json/wp/v2/posts';
        $payload = json_encode(['title' => $title, 'content' => $html, 'status' => 'publish']);

        if (!SANDBOX_MODE && function_exists('curl_init')) {
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_USERPWD => "$user:$pass",
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $raw = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $data = json_decode((string)$raw, true);
            if (in_array($code, [200, 201])) return ['success' => true, 'url' => $data['link'] ?? ''];
            return ['success' => false, 'error' => "WP REST ($code): " . substr((string)$raw, 0, 300)];
        }
        // sandbox: report clearly
        return ['success' => false, 'error' => 'WordPress publishing needs outbound HTTP — works once deployed to your subdomain.'];
    }

    // ---------------- Ghost (Admin API) ----------------

    public static function publishGhost(array $cred, $title, $html) {
        $site = rtrim(trim($cred['site_url'] ?? ''), '/');
        $key = trim($cred['admin_key'] ?? '');
        if (empty($site) || empty($key)) {
            return ['success' => false, 'error' => 'Ghost site URL and Admin API key are required.'];
        }
        $payload = json_encode(['posts' => [['title' => $title, 'html' => $html, 'status' => 'published']]]);
        $res = bkHttp('POST', $site . '/ghost/api/admin/posts/',
            ['Authorization: Token ' . $key, 'Content-Type: application/json'], $payload, 20);
        $data = $res['data'] ?? [];
        if (in_array($res['http_code'], [200, 201])) {
            $post = $data['posts'][0] ?? [];
            return ['success' => true, 'url' => !empty($post['url']) ? ($site . '/' . ltrim($post['url'], '/')) : ''];
        }
        return ['success' => false, 'error' => 'Ghost API (' . $res['http_code'] . '): ' . substr((string)$res['raw'], 0, 300)];
    }

    // ---------------- Webhook (Make/Zapier/anything) ----------------

    public static function publishWebhook(array $cred, $title, $html) {
        $url = trim($cred['webhook_url'] ?? '');
        if (empty($url)) return ['success' => false, 'error' => 'Webhook URL is required.'];
        $payload = json_encode([
            'title' => $title, 'content' => $html,
            'main_site' => $cred['main_site'] ?? '', 'source' => 'AutoBacklink',
        ]);
        $res = bkHttp('POST', $url, ['Content-Type: application/json'], $payload, 20);
        if (in_array($res['http_code'], [200, 201])) {
            return ['success' => true, 'url' => $url];
        }
        return ['success' => false, 'error' => 'Webhook (' . $res['http_code'] . '): ' . substr((string)$res['raw'], 0, 200)];
    }

    // ---------------- Dispatcher ----------------

    public static function publish(array $target, $title, $html, $imageUrl = '') {
        $mode = $target['publish_mode'] ?? 'manual';
        if ($mode !== 'api') {
            return ['success' => false, 'error' => 'Target is in manual mode.'];
        }
        $platform = $target['platform'] ?? '';
        $cred = json_decode($target['credential_json'] ?? '{}', true) ?: [];
        switch ($platform) {
            case 'blogger':   return self::publishBlogger($cred, $title, $html, $imageUrl);
            case 'wordpress': return self::publishWordpress($cred, $title, $html);
            case 'ghost':     return self::publishGhost($cred, $title, $html);
            case 'webhook':   return self::publishWebhook($cred, $title, $html);
            default:          return ['success' => false, 'error' => 'Unsupported platform: ' . $platform];
        }
    }
}
