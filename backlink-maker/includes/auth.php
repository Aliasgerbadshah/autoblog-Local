<?php
/**
 * AutoBacklink - Single-admin auth
 * DB-backed login token (portable: works under PHP-FPM and the WASM preview).
 */
require_once __DIR__ . '/database.php';

function bkStartSession() {
    // no-op kept for backward compatibility
}

// Ensure sessions table exists
(function () {
    $db = getDB();
    $db->exec('CREATE TABLE IF NOT EXISTS bk_sessions (
        token TEXT PRIMARY KEY,
        admin_id INTEGER NOT NULL,
        created_at TEXT,
        expires_at TEXT
    )');
})();

function bkCookieToken() {
    return $_COOKIE['autobacklink_tok'] ?? ($_SERVER['HTTP_AUTOBACKLINK_TOK'] ?? '');
}

function bkCurrentAdmin() {
    static $cache = false;
    if ($cache !== false) return $cache;
    $tok = bkCookieToken();
    $cache = false;
    if ($tok === '') return false;
    $db = getDB();
    $st = $db->prepare('SELECT a.id, a.username FROM bk_sessions s JOIN admins a ON a.id = s.admin_id WHERE s.token = ? AND s.expires_at > datetime(\'now\')');
    $st->execute([$tok]);
    $row = $st->fetch();
    if ($row) {
        $cache = ['id' => $row['id'], 'username' => $row['username']];
    }
    return $cache;
}

function bkHasAdmin() {
    $db = getDB();
    return (int)$db->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
}

function bkAdminExists($username) {
    $db = getDB();
    $st = $db->prepare('SELECT COUNT(*) FROM admins WHERE username = ?');
    $st->execute([$username]);
    return (int)$st->fetchColumn() > 0;
}

function bkCreateAdmin($username, $password) {
    $db = getDB();
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $st = $db->prepare('INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, datetime(\'now\'))');
    $st->execute([$username, $hash]);
    return true;
}

function bkIssueToken($adminId) {
    $tok = bin2hex(random_bytes(32));
    $db = getDB();
    $st = $db->prepare('INSERT INTO bk_sessions (token, admin_id, created_at, expires_at) VALUES (?, ?, datetime(\'now\'), datetime(\'now\', \'+30 days\'))');
    $st->execute([$tok, $adminId]);
    setcookie('autobacklink_tok', $tok, [
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    return $tok;
}

function bkLogin($username, $password) {
    $db = getDB();
    $st = $db->prepare('SELECT * FROM admins WHERE username = ?');
    $st->execute([$username]);
    $admin = $st->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        bkIssueToken($admin['id']);
        return true;
    }
    return false;
}

function bkLogout() {
    $tok = bkCookieToken();
    if ($tok !== '') {
        $db = getDB();
        $st = $db->prepare('DELETE FROM bk_sessions WHERE token = ?');
        $st->execute([$tok]);
    }
    setcookie('autobacklink_tok', '', ['path' => '/', 'expires' => time() - 3600]);
}

function bkIsLoggedIn() {
    return bkCurrentAdmin() !== false;
}

function bkRequireLogin() {
    if (!bkIsLoggedIn()) {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '/', '/api/')) {
            bkJson(['error' => 'Not logged in.'], 401);
        }
        header('Location: /login.php');
        exit;
    }
}

function bkJson($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
