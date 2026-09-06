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

    // ---------------- Hashnode (GraphQL publishPost) ----------------

    public static function publishHashnode(array $cred, $title, $html) {
        $token = trim($cred['personal_token'] ?? '');
        $pubId = trim($cred['publication_id'] ?? '');
        if (empty($token) || empty($pubId)) {
            return ['success' => false, 'error' => 'Hashnode needs a Personal Access Token AND a Publication ID. Get the token at hashnode.com → (your avatar) → Settings → Developer. The Publication ID is in your Hashnode dashboard URL.'];
        }
        // Hashnode fetches images itself — relative /packages/... URLs must be absolute
        if (defined('APP_BASE_URL') && APP_BASE_URL) {
            $html = str_replace('src="/', 'src="' . APP_BASE_URL . '/', $html);
        }
        $md = self::htmlToMarkdown($html);
        $payload = [
            'query' => 'mutation ($input: PublishPostInput!) { publishPost(input: $input) { post { url } } }',
            'variables' => ['input' => [
                'publicationId' => $pubId,
                'title' => $title,
                'contentMarkdown' => $md,
            ]],
        ];
        $res = bkHttp('POST', 'https://gql.hashnode.com',
            ['Authorization: ' . $token, 'Content-Type: application/json', 'Accept: application/json'],
            json_encode($payload), 30);
        if (in_array($res['http_code'], [401, 403])) {
            return ['success' => false, 'error' => 'Hashnode API: token rejected (HTTP ' . $res['http_code'] . '). Check the Personal Access Token.'];
        }
        $data = $res['data'] ?? [];
        if (!empty($data['errors'])) {
            return ['success' => false, 'error' => 'Hashnode API: ' . ($data['errors'][0]['message'] ?? substr((string)$res['raw'], 0, 250))];
        }
        $pp = $data['data']['publishPost'] ?? null;
        if ($pp === null || !is_array($pp)) {
            return ['success' => false, 'error' => 'Hashnode API returned no result — post was NOT created. Raw: ' . substr((string)$res['raw'], 0, 300)];
        }
        if (array_key_exists('ok', $pp) && empty($pp['ok'])) {
            return ['success' => false, 'error' => 'Hashnode API: post was NOT created (ok=false). Raw: ' . substr((string)$res['raw'], 0, 300)];
        }
        $url = $pp['post']['url'] ?? '';
        if ($url === '') {
            return ['success' => false, 'error' => 'Hashnode API: no post URL came back — post may not be live. Raw: ' . substr((string)$res['raw'], 0, 300)];
        }
        return ['success' => true, 'url' => $url];
    }

    /**
     * Lightweight HTML → Markdown for Hashnode (our content is structured:
     * h1/h2/p/a/img/figure/ul/li/strong/em).
     */
    public static function htmlToMarkdown($html) {
        $md = (string)$html;
        $md = preg_replace('/<figure[^>]*>(.*?)<\/figure>/is', '$1', $md);
        $md = preg_replace('/<img[^>]*src="([^"]*)"[^>]*alt="([^"]*)"[^>]*\/?>/i', '![$2]($1)', $md);
        $md = preg_replace('/<img[^>]*alt="([^"]*)"[^>]*src="([^"]*)"[^>]*\/?>/i', '![$1]($2)', $md);
        $md = preg_replace('/<img[^>]*src="([^"]*)"[^>]*\/?>/i', '![]($1)', $md);
        $md = preg_replace('/<h1[^>]*>.*?<\/h1>/is', '', $md); // title is separate in Hashnode
        $md = preg_replace('/<h2[^>]*>(.*?)<\/h2>/is', "\n\n## $1\n\n", $md);
        $md = preg_replace('/<h3[^>]*>(.*?)<\/h3>/is', "\n\n### $1\n\n", $md);
        $md = preg_replace('/<li[^>]*>(.*?)<\/li>/is', "- $1\n", $md);
        $md = preg_replace('/<\/?(ul|ol)[^>]*>/i', '', $md);
        $md = preg_replace('/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/is', '[$2]($1)', $md);
        $md = preg_replace('/<strong[^>]*>(.*?)<\/strong>/is', '**$1**', $md);
        $md = preg_replace('/<b[^>]*>(.*?)<\/b>/is', '**$1**', $md);
        $md = preg_replace('/<em[^>]*>(.*?)<\/em>/is', '*$1*', $md);
        $md = preg_replace('/<p[^>]*>(.*?)<\/p>/is', "$1\n\n", $md);
        $md = preg_replace('/<(br|hr)\s*\/?>/i', "\n\n", $md);
        $md = preg_replace('/<[^>]+>/', '', $md);
        $md = html_entity_decode($md, ENT_QUOTES, 'UTF-8');
        $md = preg_replace('/\n{3,}/', "\n\n", $md);
        return trim($md);
    }

    /**
     * Test a target's API connection — runs on the deployed server and shows
     * exactly what the platform's API answers (so problems are diagnosable).
     */
    public static function testTarget(array $target) {
        $platform = $target['platform'] ?? '';
        $cred = json_decode($target['credential_json'] ?? '{}', true) ?: [];
        if (SANDBOX_MODE) {
            return ['success' => false, 'error' => 'Connection tests run on your deployed server, not in the preview sandbox.'];
        }
        switch ($platform) {
            case 'hashnode':  return self::diagnoseHashnode($cred);
            case 'blogger':   return self::diagnoseBlogger($cred);
            case 'wordpress': return self::diagnoseWordpress($cred);
            case 'ghost':     return self::diagnoseGhost($cred);
            case 'wix':       return self::diagnoseWix($cred);
            default:          return ['success' => false, 'error' => 'No connection test for platform: ' . $platform];
        }
    }

    public static function diagnoseHashnode(array $cred) {
        $token = trim($cred['personal_token'] ?? '');
        $pubId = trim($cred['publication_id'] ?? '');
        if (empty($token)) return ['success' => false, 'error' => 'No Personal Access Token saved yet — paste it in the Backlink Websites panel first.'];
        if (empty($pubId)) return ['success' => false, 'error' => 'No Publication ID saved yet — paste it in the Backlink Websites panel first.'];

        $out = ['endpoint' => 'https://gql.hashnode.com', 'http_code' => 0, 'final_url' => '', 'content_type' => '', 'response_start' => ''];
        $ch = curl_init('https://gql.hashnode.com');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['query' => 'query { me { id username } }']),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: ' . $token],
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (AutoBacklink diagnostic)',
        ]);
        $raw = curl_exec($ch);
        $out['http_code'] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $out['final_url'] = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $out['content_type'] = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $out['response_start'] = substr((string)$raw, 0, 400);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            return $out + ['success' => false, 'error' => 'Could not reach the Hashnode API at all: ' . $curlErr];
        }
        $data = json_decode((string)$raw, true);
        if (is_array($data)) {
            if (!empty($data['errors'])) {
                return $out + ['success' => false, 'error' => 'Hashnode API answered (JSON) with error: ' . ($data['errors'][0]['message'] ?? 'unknown')];
            }
            if (!empty($data['data']['me'])) {
                return $out + ['success' => true, 'message' => '✅ Connected as @' . ($data['data']['me']['username'] ?? '?') . ' — token works. Next, make sure the Publication ID belongs to the blog you expect (Dashboard → Posts shows which blog a post lands in).'];
            }
            return $out + ['success' => false, 'error' => 'Hashnode API answered (JSON) but no user found: ' . substr((string)$raw, 0, 300)];
        }
        // API answered with a WEB PAGE (HTML) instead of JSON
        $why = 'The Hashnode API endpoint answered with a WEB PAGE (HTML) instead of an API answer. This is NOT a content problem. Most common causes: (1) the account needs a Pro subscription for API access, or (2) the endpoint redirected to the website — check the final_url below. Send me this whole test result and I will pinpoint it.';
        return $out + ['success' => false, 'error' => $why];
    }

    public static function diagnoseBlogger(array $cred) {
        $rf = self::refreshBloggerToken($cred['client_id'] ?? '', $cred['client_secret'] ?? '', $cred['refresh_token'] ?? '');
        if (!$rf['success']) return ['success' => false, 'error' => 'Blogger OAuth failed: ' . $rf['error']];
        $blogId = trim($cred['blog_id'] ?? '');
        $res = bkHttp('GET', "https://www.googleapis.com/blogger/v3/blogs/$blogId", ['Authorization: Bearer ' . $rf['access_token']], null, 15);
        $data = $res['data'] ?? [];
        if ($res['success'] && !empty($data['id'])) {
            return ['success' => true, 'message' => '✅ Blogger connected — blog "' . ($data['name'] ?? $blogId) . '" is accessible. Auto-posting should work.'];
        }
        return ['success' => false, 'error' => 'Blogger OAuth works, but Blog ID ' . $blogId . ' is not accessible (' . $res['http_code'] . '): ' . ($data['error']['message'] ?? substr((string)$res['raw'], 0, 200))];
    }

    public static function diagnoseWordpress(array $cred) {
        $site = rtrim(trim($cred['site_url'] ?? ''), '/');
        $user = trim($cred['username'] ?? '');
        $pass = trim($cred['app_password'] ?? '');
        if (!$site || !$user || !$pass) return ['success' => false, 'error' => 'WordPress site URL, username and application password are all needed.'];
        $ch = curl_init($site . '/wp-json/wp/v2/users/me');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_USERPWD => "$user:$pass", CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode((string)$raw, true);
        if (in_array($code, [200, 201]) && !empty($data['name'])) {
            return ['success' => true, 'message' => '✅ WordPress connected as ' . $data['name'] . ' — auto-posting should work.'];
        }
        return ['success' => false, 'error' => 'WordPress login failed (HTTP ' . $code . '). Check the Application Password (WP admin → Users → Profile).'];
    }

    public static function diagnoseGhost(array $cred) {
        $site = rtrim(trim($cred['site_url'] ?? ''), '/');
        $key = trim($cred['admin_key'] ?? '');
        if (!$site || !$key) return ['success' => false, 'error' => 'Ghost site URL and Admin API key are both needed.'];
        $res = bkHttp('GET', $site . '/ghost/api/admin/site/', ['Authorization: Token ' . $key], null, 15);
        $data = $res['data'] ?? [];
        if (in_array($res['http_code'], [200, 201]) && !empty($data['name'])) {
            return ['success' => true, 'message' => '✅ Ghost connected — site "' . $data['name'] . '" is accessible. Auto-posting should work.'];
        }
        return ['success' => false, 'error' => 'Ghost connection failed (HTTP ' . $res['http_code'] . '): ' . substr((string)$res['raw'], 0, 250)];
    }

    // ---------------- Wix (Blog v3 + Groups/Community) ----------------
    // Docs: wixapis.com — OAuth client-credentials token, blog/v3, social-groups-proxy.
    // NOTE: Wix removed the old Forum API (Oct 2025) → community = "Groups".
    // Topic/reply paths are not fully documented, so we try candidate endpoints
    // on YOUR server and remember the one that works (saved in the target's creds).

    private static function wixHeaders(array $cred) {
        $h = ['Authorization: Bearer ' . $cred['access_token'], 'Content-Type: application/json'];
        if (!empty($cred['site_id'])) $h[] = 'wix-site-id: ' . $cred['site_id'];
        return $h;
    }

    /** Resolve/refresh the Wix access token. Returns ['ok'=>bool,'cred'=>array,'error'=>string]. */
    public static function wixEnsureToken(array $cred) {
        $tok = trim((string)($cred['access_token'] ?? ''));
        $exp = (int)($cred['token_expires_at'] ?? 0);
        if ($tok && time() < $exp - 300) return ['ok' => true, 'cred' => $cred];
        $cid = trim((string)($cred['client_id'] ?? ''));
        $cs  = trim((string)($cred['client_secret'] ?? ''));
        if ($tok && (!$cid || !$cs)) return ['ok' => true, 'cred' => $cred]; // pasted token, best effort
        if (!$cid || !$cs) {
            return ['ok' => false, 'cred' => $cred, 'error' => 'Wix: save the OAuth Client ID + Client Secret (Wix Developer Dashboard → your app → OAuth tab), or paste an Access Token.'];
        }
        $body = ['grant_type' => 'client_credentials', 'client_id' => $cid, 'client_secret' => $cs];
        if (!empty($cred['instance_id'])) $body['instance_id'] = trim((string)$cred['instance_id']);
        $res = bkHttp('POST', 'https://www.wixapis.com/oauth2/token', ['Content-Type: application/json'], json_encode($body), 20);
        $data = $res['data'] ?? [];
        if ($res['http_code'] === 200 && !empty($data['access_token'])) {
            $cred['access_token'] = $data['access_token'];
            $cred['token_expires_at'] = time() + (int)($data['expires_in'] ?? 14400);
            return ['ok' => true, 'cred' => $cred];
        }
        $err = $data['error_description'] ?? ($data['error']['message'] ?? ($data['error'] ?? ''));
        if (!$err) $err = $res['http_code'] === 0 ? 'no network response (server cannot reach wixapis.com)' : substr((string)$res['raw'], 0, 250);
        return ['ok' => false, 'cred' => $cred, 'error' => "Wix OAuth token failed (HTTP {$res['http_code']}): $err. Check Client ID / Client Secret / Instance ID."];
    }

    /** Auto-detect a member ID (required by Wix for third-party app posts). Soft-fail. */
    private static function wixEnsureMember(array $cred) {
        if (!empty($cred['member_id'])) return ['ok' => true, 'cred' => $cred, 'warn' => ''];
        $res = bkHttp('GET', 'https://www.wixapis.com/members/v1/members?fieldsets=FULL&paging.limit=5', self::wixHeaders($cred), null, 20);
        $data = $res['data'] ?? [];
        $members = $data['members'] ?? [];
        if (in_array($res['http_code'], [200, 201]) && is_array($members) && !empty($members[0]['id'])) {
            $cred['member_id'] = $members[0]['id'];
            return ['ok' => true, 'cred' => $cred, 'warn' => ''];
        }
        $warn = 'Could not auto-detect a Wix Member ID (' . ($data['error']['message'] ?? 'HTTP ' . $res['http_code']) . ') — trying without it; if posting fails, set member_id manually in credentials.';
        return ['ok' => true, 'cred' => $cred, 'warn' => $warn];
    }

    /** Import an external image into Wix Media (required before using it in a post). */
    private static function wixImportImage(array $cred, $absoluteUrl, $name = 'AutoBacklink image') {
        $siteId = trim((string)($cred['site_id'] ?? ''));
        $res = bkHttp('POST', 'https://www.wixapis.com/site-media/v1/files/import?siteId=' . $siteId,
            self::wixHeaders($cred), json_encode(['url' => $absoluteUrl, 'mediaType' => 'IMAGE', 'displayName' => $name]), 60);
        $data = $res['data'] ?? [];
        $fid = $data['file']['id'] ?? ($data['id'] ?? '');
        if (in_array($res['http_code'], [200, 201]) && $fid) return ['ok' => true, 'id' => $fid, 'error' => ''];
        return ['ok' => false, 'id' => '', 'error' => $data['error']['message'] ?? ('HTTP ' . $res['http_code'] . ' ' . substr((string)$res['raw'], 0, 200))];
    }

    // ---- Ricos (Wix rich content) builders ----

    private static function ricosId(&$ids) {
        $ids[0]++;
        return 'bk' . $ids[0];
    }

    /** Inline HTML (strong/em/a/text) → Ricos TEXT nodes with decorations. */
    private static function ricosInline($html, &$ids) {
        $nodes = [];
        $push = function ($txt, $deco) use (&$nodes, &$ids) {
            $txt = html_entity_decode(trim((string)$txt), ENT_QUOTES);
            $txt = preg_replace('/\s+/u', ' ', $txt);
            if ($txt !== '') $nodes[] = ['type' => 'TEXT', 'id' => self::ricosId($ids), 'textData' => ['text' => $txt, 'decorations' => $deco]];
        };
        $pos = 0;
        while (preg_match('/<(strong|b|em|i|a)\b([^>]*)>(.*?)<\/\1>/is', $html, $m, PREG_OFFSET_CAPTURE, $pos)) {
            if ($m[0][1] > $pos) $push(strip_tags(substr($html, $pos, $m[0][1] - $pos)), []);
            $tag = strtolower($m[1][0]);
            $deco = [];
            if ($tag === 'strong' || $tag === 'b') $deco[] = ['type' => 'BOLD', 'fontWeightValue' => 700];
            if ($tag === 'em' || $tag === 'i') $deco[] = ['type' => 'ITALIC'];
            if ($tag === 'a' && preg_match('/href="([^"]+)"/', $m[2][0], $hm)) {
                $deco[] = ['type' => 'LINK', 'linkData' => ['link' => ['url' => $hm[1], 'target' => 'BLANK']]];
            }
            $push(strip_tags($m[3][0]), $deco);
            $pos = $m[0][1] + strlen($m[0][0]);
        }
        if ($pos < strlen($html)) $push(strip_tags(substr($html, $pos)), []);
        return $nodes;
    }

    /**
     * Convert our structured post HTML → Ricos document.
     * $imageMediaId = imported Wix media id for the post image (may be '').
     */
    public static function htmlToRicos($html, $imageMediaId = '') {
        $ids = [0];
        $nodes = [];
        $blocks = [];
        if (preg_match_all('/<(h2|h3|ul|figure|p)\b[^>]*>(.*?)<\/\1>|<img\b[^>]*\/?>/is', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $b) {
                $full = $b[0];
                if (str_starts_with(ltrim($full), '<img')) {
                    if ($imageMediaId) $nodes[] = self::ricosImage($imageMediaId, $ids);
                    continue;
                }
                $tag = strtolower($b[1]);
                $inner = $b[2];
                if ($tag === 'figure') {
                    if ($imageMediaId) $nodes[] = self::ricosImage($imageMediaId, $ids);
                    continue;
                }
                if ($tag === 'ul') {
                    $items = [];
                    if (preg_match_all('/<li\b[^>]*>(.*?)<\/li>/is', $inner, $lm, PREG_SET_ORDER)) {
                        foreach ($lm as $li) {
                            $pn = self::ricosInline($li[1], $ids);
                            if ($pn) $items[] = ['type' => 'LIST_ITEM', 'id' => self::ricosId($ids), 'nodes' => [['type' => 'PARAGRAPH', 'id' => self::ricosId($ids), 'nodes' => $pn, 'paragraphData' => []]]];
                        }
                    }
                    if ($items) $nodes[] = ['type' => 'BULLETED_LIST', 'id' => self::ricosId($ids), 'nodes' => $items];
                    continue;
                }
                $tn = self::ricosInline($inner, $ids);
                if (!$tn) continue;
                if ($tag === 'h2' || $tag === 'h3') {
                    $nodes[] = ['type' => 'HEADING', 'id' => self::ricosId($ids), 'nodes' => $tn, 'headingData' => ['level' => $tag === 'h2' ? 2 : 3]];
                } else {
                    $nodes[] = ['type' => 'PARAGRAPH', 'id' => self::ricosId($ids), 'nodes' => $tn, 'paragraphData' => []];
                }
            }
        }
        return ['nodes' => $nodes];
    }

    private static function ricosImage($mediaId, &$ids) {
        return [
            'type' => 'IMAGE', 'id' => self::ricosId($ids),
            'imageData' => [
                'containerData' => ['width' => ['size' => 'CONTENT'], 'alignment' => 'CENTER'],
                'image' => ['src' => ['id' => $mediaId], 'width' => 900, 'height' => 600],
            ],
        ];
    }

    /**
     * Publish (or draft) a post on a Wix blog.
     * $cred keys: site_id, client_id, client_secret, instance_id, access_token, token_expires_at, member_id, site_url
     * Returns ['success'=>bool, 'url'=>..., 'warn'=>...].
     */
    public static function publishWix(array $cred, $title, $html, $imageUrl = '', $draft = false) {
        $siteId = trim((string)($cred['site_id'] ?? ''));
        if (!$siteId) return ['success' => false, 'error' => 'Wix Site ID is required. Find it in your Wix dashboard URL (wix.com/dashboard/<SITE_ID>/...) or Dev Center → Site list.'];

        $tk = self::wixEnsureToken($cred);
        if (!$tk['ok']) return ['success' => false, 'error' => $tk['error']];
        $cred = $tk['cred'];
        $mem = self::wixEnsureMember($cred);
        $cred = $mem['cred'];
        $warn = $mem['warn'];

        // images: our path may be relative → absolute; Wix needs an import first
        if (defined('APP_BASE_URL') && APP_BASE_URL) {
            $html = str_replace('src="/', 'src="' . APP_BASE_URL . '/', $html);
        }
        $mediaId = '';
        if ($imageUrl && !str_starts_with($imageUrl, 'data:')) {
            $absImg = $imageUrl;
            if (!preg_match('#^https?://#', $absImg)) $absImg = (defined('APP_BASE_URL') ? APP_BASE_URL : '') . '/' . ltrim($absImg, '/');
            $imp = self::wixImportImage($cred, $absImg);
            if ($imp['ok']) { $mediaId = $imp['id']; }
            else { $warn = ($warn ? $warn . ' ' : '') . 'Image import failed (post created without image): ' . $imp['error']; }
        }

        $endpoint = "https://www.wixapis.com/blog/v3/draft-posts?siteId=" . $siteId;
        $base = ['publish' => !$draft, 'fieldsets' => ['URL']];
        $res = null;
        // Attempt 1: official Ricos rich content
        $ricos = self::htmlToRicos($html, $mediaId);
        $dp = ['title' => $title, 'richContent' => $ricos];
        if (!empty($cred['member_id'])) $dp['memberId'] = $cred['member_id'];
        if ($mediaId) $dp['media'] = ['wixMedia' => ['image' => ['id' => $mediaId]], 'displayed' => true, 'custom' => true];
        $res = bkHttp('POST', $endpoint, self::wixHeaders($cred), json_encode($base + ['draftPost' => $dp]), 40);
        // Attempt 2: simple HTML content (some Wix blog setups accept this)
        if (in_array($res['http_code'], [400, 405, 415])) {
            $dp2 = ['title' => $title, 'content' => ['html' => $html]];
            if (!empty($cred['member_id'])) $dp2['memberId'] = $cred['member_id'];
            $res = bkHttp('POST', $endpoint, self::wixHeaders($cred), json_encode($base + ['draftPost' => $dp2]), 40);
        }

        $data = $res['data'] ?? [];
        if (in_array($res['http_code'], [200, 201])) {
            $post = $data['post'] ?? ($data['draftPost'] ?? []);
            $url = $post['url'] ?? '';
            if (!$url && !empty($post['slug'])) {
                $baseSite = rtrim(trim((string)($cred['site_url'] ?? '')), '/');
                if ($baseSite) $url = $baseSite . '/blog/' . ltrim($post['slug'], '/');
            }
            return ['success' => true, 'url' => $url, 'post_id' => $post['id'] ?? '', 'cred' => $cred, 'warn' => $warn];
        }
        $err = $data['error']['message'] ?? ($data['message'] ?? '');
        if (!$err) $err = substr((string)$res['raw'], 0, 350);
        $hint = '';
        if (str_contains($err, 'memberId') || str_contains($err, 'member')) {
            $hint = ' Hint: Wix wants a Member ID — the auto-detect may be blocked; add member_id in the site credentials.';
        }
        if ($res['http_code'] === 403) {
            $hint = ' Hint: 403 usually means the token lacks permission for this site (app not installed on the site, or missing "Manage Blog"/"Read Members" permissions in the Dev Center app).';
        }
        return ['success' => false, 'error' => "Wix Blog API (HTTP {$res['http_code']}): $err.$hint", 'cred' => $cred, 'warn' => $warn];
    }

    /** 🔌 Connection diagnostic for a Wix target. */
    public static function diagnoseWix(array $cred) {
        $siteId = trim((string)($cred['site_id'] ?? ''));
        if (!$siteId) return ['success' => false, 'error' => 'Site ID not saved yet — open the site in Backlink Websites → Edit and fill the Wix Site ID.'];
        $tk = self::wixEnsureToken($cred);
        if (!$tk['ok']) return ['success' => false, 'error' => $tk['error']];
        $cred = $tk['cred'];

        $msg = "✅ Wix token OK (site $siteId).\n";
        $okAll = true;
        // Blog
        $res = bkHttp('GET', "https://www.wixapis.com/blog/v3/posts?siteId=$siteId&paging.limit=1", self::wixHeaders($cred), null, 20);
        $data = $res['data'] ?? [];
        if (in_array($res['http_code'], [200, 201])) {
            $n = $data['metadata']['count'] ?? (is_array($data['posts'] ?? null) ? count($data['posts']) : 0);
            $msg .= "✅ Blog API reachable — blog has $n post(s). Auto-posting should work.\n";
        } else {
            $okAll = false;
            $msg .= "❌ Blog API answered HTTP {$res['http_code']}: " . ($data['error']['message'] ?? substr((string)$res['raw'], 0, 200)) . "\n";
        }
        // Community groups
        $res2 = bkHttp('GET', 'https://www.wixapis.com/social-groups-proxy/groups/v2/groups', self::wixHeaders($cred), null, 20);
        $data2 = $res2['data'] ?? [];
        $groups = $data2['groups'] ?? [];
        if (in_array($res2['http_code'], [200, 201]) && is_array($groups) && $groups) {
            $names = implode(', ', array_map(fn($g) => ($g['name'] ?? '?') . ' [' . ($g['id'] ?? '') . ']', array_slice($groups, 0, 5)));
            $msg .= "✅ Community (Groups) API reachable — groups: $names\n";
        } else {
            $msg .= "⚠️ Community (Groups) API: HTTP {$res2['http_code']}. If your Wix site has no Community/Groups app installed, that's normal — community commenting needs the Groups app on the site. " . ($data2['error']['message'] ?? '') . "\n";
        }
        return ['success' => $okAll, 'message' => trim($msg), 'cred' => $cred];
    }

    // ---- Wix Community (Groups): list groups, topics, post replies ----

    public static function wixListGroups(array $cred) {
        $res = bkHttp('GET', 'https://www.wixapis.com/social-groups-proxy/groups/v2/groups', self::wixHeaders($cred), null, 20);
        $data = $res['data'] ?? [];
        $groups = $data['groups'] ?? [];
        if (!is_array($groups) && is_array($data)) $groups = array_values(array_filter($data, fn($x) => is_array($x) && !empty($x['id'])));
        $out = ['success' => in_array($res['http_code'], [200, 201]) && is_array($groups), 'groups' => [], 'cred' => $cred, 'error' => ''];
        if ($out['success']) {
            foreach (array_slice($groups, 0, 20) as $g) {
                $out['groups'][] = ['id' => $g['id'] ?? '', 'name' => $g['name'] ?? ($g['title'] ?? '?')];
            }
        } else {
            $out['error'] = 'Groups API HTTP ' . $res['http_code'] . ': ' . ($data['error']['message'] ?? substr((string)$res['raw'], 0, 200));
        }
        return $out;
    }

    /**
     * Resolve the community group: if $groupId is empty, auto-pick the site's
     * first group (and report its name so the caller can remember it).
     */
    public static function wixResolveGroup(array $cred, $groupId = '') {
        $groupId = trim((string)$groupId);
        if ($groupId) return ['ok' => true, 'group_id' => $groupId, 'groups' => [], 'cred' => $cred, 'error' => ''];
        $list = self::wixListGroups($cred);
        if (empty($list['success']) || empty($list['groups'])) {
            return ['ok' => false, 'group_id' => '', 'groups' => $list['groups'] ?? [], 'cred' => $list['cred'] ?? $cred,
                    'error' => 'No Wix community (Groups) found on this site. Install the Groups/Communities app in your Wix site editor, then try again. ' . ($list['error'] ?? '')];
        }
        $first = $list['groups'][0];
        return ['ok' => true, 'group_id' => $first['id'], 'groups' => $list['groups'], 'cred' => $list['cred'] ?? $cred, 'error' => '', 'auto_picked' => $first['name'] ?? ''];
    }

    private static function wixTopicCandidates($groupId) {
        return [
            "https://www.wixapis.com/social-groups-proxy/topics/v1/groups/$groupId/topics",
            "https://www.wixapis.com/social-groups-proxy/topics/v2/groups/$groupId/topics",
            "https://www.wixapis.com/social-groups-proxy/posts/v1/groups/$groupId/posts",
            "https://www.wixapis.com/social-groups-proxy/posts/v2/groups/$groupId/posts",
        ];
    }

    /**
     * List topics of a Wix group. Tries candidate endpoints (not all are
     * documented), remembers the working one in $cred['topics_endpoint'].
     */
    public static function wixListTopics(array $cred, $groupId) {
        if (!$groupId) return ['success' => false, 'topics' => [], 'cred' => $cred, 'tried' => [], 'error' => 'No community group selected — save a Group ID in the site panel first.'];
        $candidates = !empty($cred['topics_endpoint']) ? array_merge([$cred['topics_endpoint']], self::wixTopicCandidates($groupId)) : self::wixTopicCandidates($groupId);
        $tried = [];
        foreach ($candidates as $ep) {
            $res = bkHttp('GET', $ep, self::wixHeaders($cred), null, 20);
            $data = $res['data'] ?? null;
            $tried[] = ['endpoint' => $ep, 'http' => $res['http_code']];
            if (in_array($res['http_code'], [200, 201]) && is_array($data)) {
                $items = $data['topics'] ?? ($data['posts'] ?? ($data['items'] ?? null));
                if ($items === null && isset($data[0])) $items = $data;
                if (is_array($items) && $items) {
                    $cred['topics_endpoint'] = $ep;
                    $topics = [];
                    foreach (array_slice($items, 0, 30) as $it) {
                        if (!is_array($it)) continue;
                        $rawText = $it['content']['html'] ?? ($it['content']['text'] ?? ($it['text'] ?? ($it['description'] ?? '')));
                        $topics[] = [
                            'id' => $it['id'] ?? '',
                            'title' => $it['title'] ?? ($it['name'] ?? ''),
                            'text' => mb_substr(strip_tags((string)$rawText), 0, 500),
                            'url' => $it['url'] ?? '',
                        ];
                    }
                    return ['success' => true, 'topics' => $topics, 'cred' => $cred, 'tried' => $tried, 'error' => ''];
                }
            }
        }
        $triedText = array_map(fn($t) => str_replace("https://www.wixapis.com/social-groups-proxy/", '', $t['endpoint']) . " → HTTP " . $t['http'], $tried);
        return ['success' => false, 'topics' => [], 'cred' => $cred, 'tried' => $tried, 'error' => 'Could not list topics. Endpoints tried:' . PHP_EOL . implode(PHP_EOL, $triedText) . PHP_EOL . 'Send me this output — I will pin the right endpoint for your site (or your community may need the Groups app enabled).'];
    }

    /**
     * Post a reply (comment) on a Wix group topic. Tries candidate payloads
     * and endpoints; remembers the working reply path in $cred['replies_endpoint'].
     */
    public static function wixPostReply(array $cred, $groupId, $topicId, $text) {
        if (!$groupId || !$topicId) return ['success' => false, 'cred' => $cred, 'error' => 'Group ID and topic ID are required.'];
        $candidateBases = [];
        if (!empty($cred['topics_endpoint'])) {
            $base = preg_replace('#/topics$#', '', $cred['topics_endpoint']);
            $candidateBases[] = $base;
        }
        foreach (self::wixTopicCandidates($groupId) as $ep) {
            $candidateBases[] = preg_replace('#/topics$#', '', $ep);
        }
        $candidateBases = array_values(array_unique($candidateBases));
        $payloads = [
            ['reply' => ['content' => $text]],
            ['reply' => ['text' => $text]],
            ['content' => ['html' => $text]],
            ['text' => $text],
        ];
        $tried = [];
        foreach ($candidateBases as $base) {
            $url = rtrim($base, '/') . "/topics/$topicId/replies";
            foreach ($payloads as $i => $pl) {
                $res = bkHttp('POST', $url, self::wixHeaders($cred), json_encode($pl), 25);
                $data = $res['data'] ?? [];
                $tried[] = $url . " [payload " . ($i + 1) . "] → HTTP " . $res['http_code'];
                if (in_array($res['http_code'], [200, 201])) {
                    $cred['replies_endpoint'] = $url;
                    return ['success' => true, 'cred' => $cred, 'reply_id' => $data['reply']['id'] ?? ($data['id'] ?? ''), 'url' => $url];
                }
            }
        }
        return ['success' => false, 'cred' => $cred, 'error' => 'Could not post the reply. Tried:' . PHP_EOL . implode(PHP_EOL, $tried) . PHP_EOL . 'Send me this output so I can pin the exact reply format for your site.'];
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
            case 'hashnode':  return self::publishHashnode($cred, $title, $html);
            case 'wix':       return self::publishWix($cred, $title, $html, $imageUrl);
            case 'webhook':   return self::publishWebhook($cred, $title, $html);
            default:          return ['success' => false, 'error' => 'Unsupported platform: ' . $platform];
        }
    }
}
