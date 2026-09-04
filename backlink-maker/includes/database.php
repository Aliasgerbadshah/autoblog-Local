<?php
/**
 * AutoBacklink - Database (own SQLite file: backlink_maker.db)
 */
require_once __DIR__ . '/config.php';

function getDB() {
    static $db = null;
    if ($db === null) {
        if (!is_dir(dirname(DB_PATH))) mkdir(dirname(DB_PATH), 0755, true);
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA journal_mode = WAL');
        initSchema($db);
    }
    return $db;
}

function initSchema(PDO $db) {
    $db->exec('CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY CHECK (id = 1),
        main_site_url TEXT DEFAULT "",
        anchor_pool TEXT DEFAULT "[]",
        custom_topics TEXT DEFAULT "",
        daily_count INTEGER DEFAULT ' . DEFAULT_DAILY_COUNT . ',
        daily_time TEXT DEFAULT "' . DEFAULT_DAILY_TIME . '",
        email_digest INTEGER DEFAULT 0,
        smtp_json TEXT DEFAULT "{}",
        updated_at TEXT
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS targets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        site_url TEXT NOT NULL,
        target_type TEXT DEFAULT "blog",
        publish_mode TEXT DEFAULT "manual",
        platform TEXT DEFAULT "",
        credential_json TEXT DEFAULT "{}",
        niche TEXT DEFAULT "",
        min_interval_days INTEGER DEFAULT ' . DEFAULT_MIN_INTERVAL_DAYS . ',
        is_active INTEGER DEFAULT 1,
        account_notes TEXT DEFAULT "",
        last_posted_at TEXT,
        post_count INTEGER DEFAULT 0,
        created_at TEXT
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS jobs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        target_id INTEGER NOT NULL,
        run_date TEXT NOT NULL,
        angle TEXT DEFAULT "",
        topic TEXT DEFAULT "",
        title TEXT DEFAULT "",
        anchor_text TEXT DEFAULT "",
        content_html TEXT DEFAULT "",
        content_text TEXT DEFAULT "",
        image_url TEXT DEFAULT "",
        image_file TEXT DEFAULT "",
        instructions TEXT DEFAULT "",
        status TEXT DEFAULT "Queued",
        published_url TEXT DEFAULT "",
        is_dofollow INTEGER,
        last_verified_at TEXT,
        error_message TEXT DEFAULT "",
        created_at TEXT,
        posted_at TEXT
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS used_topics (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT,
        keyword TEXT,
        used_at TEXT
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS run_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        run_date TEXT,
        message TEXT,
        created_at TEXT
    )');

    // single-admin auth (created on first setup)
    $db->exec('CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        created_at TEXT
    )');

    // generic key/value storage (API credentials, etc.)
    $db->exec('CREATE TABLE IF NOT EXISTS app_data (
        key TEXT PRIMARY KEY,
        value TEXT
    )');

    // default settings row
    $db->exec("INSERT OR IGNORE INTO settings (id, updated_at) VALUES (1, datetime('now'))");
}

function getData($key, $default = null) {
    $db = getDB();
    $st = $db->prepare('SELECT value FROM app_data WHERE key = ?');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? $default : $v;
}

function putData($key, $value) {
    $db = getDB();
    $st = $db->prepare('INSERT INTO app_data (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $st->execute([$key, $value]);
}

function getSettings() {
    $db = getDB();
    $row = $db->query('SELECT * FROM settings WHERE id = 1')->fetch();
    if (!$row) {
        $db->exec("INSERT INTO settings (id, updated_at) VALUES (1, datetime('now'))");
        $row = $db->query('SELECT * FROM settings WHERE id = 1')->fetch();
    }
    $row['anchor_pool'] = json_decode($row['anchor_pool'] ?: '[]', true) ?: [];
    $row['smtp'] = json_decode($row['smtp_json'] ?: '{}', true) ?: [];
    return $row;
}

function saveSettings(array $patch) {
    $db = getDB();
    $fields = ['main_site_url','anchor_pool','custom_topics','daily_count','daily_time','email_digest','smtp_json'];
    $sets = [];
    $vals = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $patch)) {
            $sets[] = "$f = ?";
            $vals[] = is_array($patch[$f]) ? json_encode($patch[$f]) : $patch[$f];
        }
    }
    if (!$sets) return;
    $sets[] = "updated_at = ?";
    $vals[] = nowString();
    $st = $db->prepare('UPDATE settings SET ' . implode(', ', $sets) . ' WHERE id = 1');
    $st->execute($vals);
}
