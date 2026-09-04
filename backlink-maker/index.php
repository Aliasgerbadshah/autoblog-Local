<?php
/**
 * AutoBacklink - Main Router (dashboard + API)
 * Standalone software — deploy the whole folder to a subdomain.
 */
date_default_timezone_set('Asia/Kolkata');
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/content_engine.php';
require_once __DIR__ . '/includes/maker.php';

bkStartSession();

// ---------- Parse URI ----------
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = rtrim($uri, '/');
if ($uri === '') $uri = '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = json_decode(file_get_contents('php://input') ?: '', true) ?? [];

// ---------- Serve generated package images / files ----------
if (preg_match('#^/packages/.+#', $uri)) {
    $file = APP_ROOT . $uri;
    $real = realpath($file);
    $rootReal = realpath(APP_ROOT . '/packages');
    if ($real && $rootReal && str_starts_with($real, $rootReal) && is_file($real)) {
        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'html' => 'text/html', 'txt' => 'text/plain'][$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($real));
        readfile($real);
        exit;
    }
    http_response_code(404);
    echo 'Not found';
    exit;
}

// ---------- Pages ----------
if ($uri === '/login.php' || $uri === '/login') {
    if (bkIsLoggedIn()) { header('Location: /'); exit; }
    require __DIR__ . '/login.php';
    exit;
}
if ($uri === '/logout.php' || $uri === '/logout') {
    bkLogout();
    header('Location: /login.php');
    exit;
}
if ($uri === '/') {
    if (!bkHasAdmin()) {
        // first visit → setup screen
        include __DIR__ . '/setup.php';
        exit;
    }
    if (!bkIsLoggedIn()) { header('Location: /login.php'); exit; }
    include __DIR__ . '/templates/dashboard.html';
    exit;
}

// ---------- API ----------
if (str_starts_with($uri, '/api/')) {
    handleApi($uri, $method, $input);
    exit;
}

http_response_code(404);
echo 'Not found';

function handleApi($uri, $method, $input) {
    // ---- Auth (no login required) ----
    if ($uri === '/api/auth/setup' && $method === 'POST') {
        if (bkHasAdmin()) bkJson(['error' => 'Admin account already exists.'], 400);
        $username = trim($input['username'] ?? '');
        $password = (string)($input['password'] ?? '');
        if ($username < 3 || strlen($password) < 6) bkJson(['error' => 'Username (3+ chars) and password (6+ chars) required.'], 400);
        bkCreateAdmin($username, $password);
        bkLogin($username, $password);
        bkJson(['success' => true, 'message' => 'Admin account created. Welcome!']);
    }
    if ($uri === '/api/auth/login' && $method === 'POST') {
        $ok = bkLogin(trim($input['username'] ?? ''), (string)($input['password'] ?? ''));
        if ($ok) bkJson(['success' => true]);
        bkJson(['error' => 'Wrong username or password.'], 401);
    }
    if ($uri === '/api/auth/logout' && $method === 'POST') {
        bkLogout();
        bkJson(['success' => true]);
    }
    if ($uri === '/api/auth/state') {
        bkJson(['has_admin' => bkHasAdmin(), 'logged_in' => bkIsLoggedIn(), 'sandbox' => SANDBOX_MODE, 'base_url' => APP_BASE_URL]);
    }

    // ---- All below require login ----
    bkRequireLogin();

    // ---- Settings ----
    if ($uri === '/api/settings') {
        if ($method === 'POST') {
            $patch = [];
            if (array_key_exists('main_site_url', $input)) $patch['main_site_url'] = trim((string)$input['main_site_url']);
            if (array_key_exists('anchor_pool', $input)) $patch['anchor_pool'] = is_array($input['anchor_pool']) ? $input['anchor_pool'] : [];
            if (array_key_exists('custom_topics', $input)) $patch['custom_topics'] = (string)$input['custom_topics'];
            if (array_key_exists('daily_count', $input)) $patch['daily_count'] = max(1, min(MAX_DAILY_JOBS, intval($input['daily_count'])));
            if (array_key_exists('daily_time', $input)) $patch['daily_time'] = (string)$input['daily_time'];
            saveSettings($patch);
            bkJson(['success' => true, 'message' => 'Settings saved.']);
        }
        $s = getSettings();
        bkJson($s);
    }

    // ---- API vault (Chat + Image — same providers as AutoBlog) ----
    if (preg_match('#^/api/vault/(chat|image)$#', $uri, $m)) {
        $key = 'api_' . $m[1];
        if ($method === 'POST') {
            putData($key, json_encode($input));
            bkJson(['success' => true, 'message' => ($m[1] === 'chat' ? 'Chat API' : 'Image API') . ' saved.']);
        }
        $creds = json_decode((string)getData($key, '{}'), true) ?: [];
        $hasKey = !empty($creds['api_key']);
        bkJson(['configured' => $hasKey, 'provider' => $creds['provider'] ?? '', 'model' => $creds['model'] ?? '', 'has_key' => $hasKey]);
    }
    if (preg_match('#^/api/vault/test/(chat|image)$#', $uri, $m) && $method === 'POST') {
        $key = 'api_' . $m[1];
        $creds = json_decode((string)getData($key, '{}'), true) ?: [];
        if (empty($creds['api_key'])) bkJson(['success' => false, 'error' => 'Save an API key first.'], 400);
        if ($m[1] === 'chat') {
            $res = AIProviderClient::chat($creds, 'Reply with exactly: AutoBacklink API connection successful');
            bkJson($res, $res['success'] ? 200 : 400);
        } else {
            $res = AIProviderClient::image($creds, 'A simple test image: a plain dark gradient, no text');
            bkJson(['success' => $res['success'], 'error' => $res['error'] ?? '', 'preview' => $res['success'] ? substr($res['url'], 0, 200) : ''], $res['success'] ? 200 : 400);
        }
    }

    // ---- Targets (Backlink Websites panel) ----
    if ($uri === '/api/targets') {
        if ($method === 'POST') {
            $name = trim($input['name'] ?? '');
            $url = trim($input['site_url'] ?? '');
            if (!$name || !$url) bkJson(['error' => 'Name and site URL are required.'], 400);
            if (!preg_match('#^https?://#', $url)) $url = 'https://' . $url;
            $db = getDB();
            $cred = json_encode($input['credentials'] ?? []);
            $st = $db->prepare('INSERT INTO targets (name, site_url, target_type, publish_mode, platform, credential_json, niche, min_interval_days, is_active, account_notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, datetime(\'now\'))');
            $st->execute([
                $name, $url,
                $input['target_type'] ?? 'blog',
                $input['publish_mode'] ?? 'manual',
                $input['platform'] ?? '',
                $cred,
                trim($input['niche'] ?? ''),
                max(1, intval($input['min_interval_days'] ?? DEFAULT_MIN_INTERVAL_DAYS)),
                trim($input['account_notes'] ?? ''),
            ]);
            bkJson(['success' => true, 'id' => (int)$db->lastInsertId()]);
        }
        $db = getDB();
        bkJson($db->query('SELECT * FROM targets ORDER BY id DESC')->fetchAll());
    }
    if (preg_match('#^/api/targets/(\d+)$#', $uri, $m)) {
        $id = intval($m[1]);
        $db = getDB();
        if ($method === 'PUT') {
            $st = $db->prepare('SELECT * FROM targets WHERE id = ?');
            $st->execute([$id]);
            $cur = $st->fetch();
            if (!$cur) bkJson(['error' => 'Target not found.'], 404);
            $fields = ['name','site_url','target_type','publish_mode','platform','niche','min_interval_days','is_active','account_notes'];
            $sets = []; $vals = [];
            foreach ($fields as $f) {
                if (array_key_exists($f, $input)) { $sets[] = "$f = ?"; $vals[] = $input[$f]; }
            }
            if (array_key_exists('credentials', $input)) { $sets[] = 'credential_json = ?'; $vals[] = json_encode($input['credentials']); }
            if (!$sets) bkJson(['success' => true]);
            $vals[] = $id;
            $db->exec('UPDATE targets SET ' . implode(', ', $sets) . ' WHERE id = ' . $id);
            bkJson(['success' => true]);
        }
        if ($method === 'DELETE') {
            $st = $db->prepare('DELETE FROM targets WHERE id = ?');
            $st->execute([$id]);
            bkJson(['success' => true]);
        }
        $st = $db->prepare('SELECT * FROM targets WHERE id = ?');
        $st->execute([$id]);
        $t = $st->fetch();
        if (!$t) bkJson(['error' => 'Not found.'], 404);
        $t['credentials'] = json_decode($t['credential_json'] ?? '{}', true) ?: [];
        unset($t['credential_json']);
        bkJson($t);
    }
    if ($uri === '/api/targets/import' && $method === 'POST') {
        $rows = $input['rows'] ?? [];
        if (!is_array($rows) || empty($rows)) bkJson(['error' => 'No rows to import.'], 400);
        $db = getDB();
        $n = 0;
        foreach ($rows as $r) {
            $name = trim($r['name'] ?? ''); $url = trim($r['site_url'] ?? '');
            if (!$name || !$url) continue;
            if (!preg_match('#^https?://#', $url)) $url = 'https://' . $url;
            $st = $db->prepare('INSERT INTO targets (name, site_url, target_type, publish_mode, platform, niche, min_interval_days, account_notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))');
            $st->execute([$name, $url, $r['type'] ?? 'blog', $r['publish_mode'] ?? 'manual', $r['platform'] ?? '', $r['niche'] ?? '', max(1, intval($r['min_interval_days'] ?? 7)), $r['account_notes'] ?? '']);
            $n++;
        }
        bkJson(['success' => true, 'imported' => $n]);
    }

    // ---- Run now ----
    if ($uri === '/api/run' && $method === 'POST') {
        $summary = BacklinkMaker::runDaily();
        bkJson(['success' => true] + $summary);
    }

    // ---- Jobs ----
    if ($uri === '/api/jobs') {
        $db = getDB();
        $status = $input['status'] ?? ($_GET['status'] ?? '');
        $limit = intval($_GET['limit'] ?? 30);
        if ($status) {
            $st = $db->prepare('SELECT j.*, t.name AS target_name, t.site_url AS target_site_url, t.target_type FROM jobs j JOIN targets t ON t.id = j.target_id WHERE j.status = ? ORDER BY j.id DESC LIMIT ?');
            $st->execute([$status, $limit]);
        } else {
            $st = $db->prepare('SELECT j.*, t.name AS target_name, t.site_url AS target_site_url, t.target_type FROM jobs j JOIN targets t ON t.id = j.target_id ORDER BY j.id DESC LIMIT ?');
            $st->execute([$limit]);
        }
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            unset($r['content_html'], $r['content_text']);
        }
        bkJson($rows);
    }
    if (preg_match('#^/api/jobs/(\d+)$#', $uri, $m)) {
        $db = getDB();
        $st = $db->prepare('SELECT j.*, t.name AS target_name, t.site_url AS target_site_url, t.target_type, t.account_notes FROM jobs j JOIN targets t ON t.id = j.target_id WHERE j.id = ?');
        $st->execute([intval($m[1])]);
        $job = $st->fetch();
        if (!$job) bkJson(['error' => 'Job not found.'], 404);
        bkJson($job);
    }
    if (preg_match('#^/api/jobs/(\d+)/posted$#', $uri, $m) && $method === 'POST') {
        $res = BacklinkMaker::markPosted(intval($m[1]), trim((string)($input['published_url'] ?? '')));
        bkJson($res, $res['success'] ? 200 : 400);
    }
    if (preg_match('#^/api/jobs/(\d+)/copy$#', $uri, $m)) {
        $db = getDB();
        $st = $db->prepare('SELECT * FROM jobs WHERE id = ?');
        $st->execute([intval($m[1])]);
        $job = $st->fetch();
        if (!$job) bkJson(['error' => 'Not found.'], 404);
        bkJson([
            'title' => $job['title'],
            'html' => $job['content_html'],
            'text' => $job['content_text'],
            'anchor' => $job['anchor_text'],
        ]);
    }
    if (preg_match('#^/api/jobs/(\d+)/image$#', $uri, $m)) {
        $db = getDB();
        $st = $db->prepare('SELECT image_file FROM jobs WHERE id = ?');
        $st->execute([intval($m[1])]);
        $f = (string)$st->fetchColumn();
        $abs = APP_ROOT . '/' . ltrim($f, '/');
        if (!$f || !file_exists($abs)) bkJson(['error' => 'Image not found.'], 404);
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="backlink-image.png"');
        header('Content-Length: ' . filesize($abs));
        readfile($abs);
        exit;
    }

    // ---- Link health ----
    if ($uri === '/api/links' ) {
        $db = getDB();
        $rows = $db->query("SELECT j.id, j.title, j.published_url, j.is_dofollow, j.last_verified_at, j.status, j.run_date, t.name AS target_name FROM jobs j JOIN targets t ON t.id = j.target_id WHERE j.published_url != '' ORDER BY j.id DESC LIMIT 200")->fetchAll();
        bkJson($rows);
    }
    if ($uri === '/api/links/recheck' && $method === 'POST') {
        if (SANDBOX_MODE) bkJson(['success' => false, 'error' => 'Link re-check needs outbound HTTP — works once deployed to your subdomain.'], 400);
        $res = LinkVerifier::recheckAll();
        bkJson(['success' => true, 'checked' => count($res)]);
    }

    // ---- Run log ----
    if ($uri === '/api/runlog') {
        $db = getDB();
        bkJson($db->query('SELECT * FROM run_log ORDER BY id DESC LIMIT 100')->fetchAll());
    }

    // ---- Stats ----
    if ($uri === '/api/status') {
        $db = getDB();
        $stats = [
            'targets_active' => (int)$db->query('SELECT COUNT(*) FROM targets WHERE is_active = 1')->fetchColumn(),
            'targets_total' => (int)$db->query('SELECT COUNT(*) FROM targets')->fetchColumn(),
            'posts_today' => (int)$db->query("SELECT COUNT(*) FROM jobs WHERE run_date = date('now')")->fetchColumn(),
            'published_total' => (int)$db->query("SELECT COUNT(*) FROM jobs WHERE status IN ('Published','Manual Posted')")->fetchColumn(),
            'dofollow_live' => (int)$db->query("SELECT COUNT(*) FROM jobs WHERE is_dofollow = 1")->fetchColumn(),
            'manual_waiting' => (int)$db->query("SELECT COUNT(*) FROM jobs WHERE status = 'Manual Ready'")->fetchColumn(),
            'failed_total' => (int)$db->query("SELECT COUNT(*) FROM jobs WHERE status = 'Failed'")->fetchColumn(),
            'sandbox' => SANDBOX_MODE,
        ];
        $s = getSettings();
        $stats['chat_configured'] = !empty(json_decode((string)getData('api_chat', '{}'), true)['api_key']);
        $stats['image_configured'] = !empty(json_decode((string)getData('api_image', '{}'), true)['api_key']);
        $stats['main_site_url'] = $s['main_site_url'] ?? '';
        bkJson($stats);
    }

    bkJson(['error' => 'API endpoint not found'], 404);
}
