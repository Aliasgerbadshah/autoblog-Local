<?php
/**
 * cron/debug.php — AutoBlog cron diagnostic (READ-ONLY, publishes nothing).
 *
 * Open this in your browser AFTER uploading it next to tick.php:
 *
 *     https://apps.colorfiind.com/cron/debug.php
 *
 * Copy the WHOLE output and paste it to the developer. It shows:
 *   - PHP version / server software / where the app files are
 *   - which required files exist or are MISSING
 *   - a PHP syntax (parse) check of every .php file in the app
 *   - whether the NEW versions of the files are actually uploaded
 *   - a step-by-step loading test of includes/ + the database
 *   - the last lines of data/php_error.log (the real fatal error, if logged)
 *
 * When you are done debugging, delete this file from the server.
 */
error_reporting(E_ALL);
@ini_set('display_errors', '1');
@ini_set('log_errors', '0');
if (!headers_sent()) header('Content-Type: text/plain; charset=utf-8');
date_default_timezone_set('Asia/Kolkata');

function dbg($s) { echo $s . "\n"; }

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        echo "\n[FATAL during diagnostic] " . $e['message'] . ' @ ' . $e['file'] . ':' . $e['line'] . "\n";
    }
});

dbg('==============================================');
dbg('AutoBlog Cron Diagnostic - ' . date('Y-m-d H:i:s'));
dbg('==============================================');
dbg('PHP version : ' . PHP_VERSION . ' (' . PHP_SAPI . ')');
dbg('Server      : ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown'));
dbg('This file   : ' . __FILE__);
dbg('Doc root    : ' . ($_SERVER['DOCUMENT_ROOT'] ?? 'unknown'));
$root = dirname(__DIR__);
dbg('App root    : ' . $root . ' (cron folder is: ' . __DIR__ . ')');
dbg('');

dbg('--- 1. Key files / folders ---');
$checks = array(
    'cron/tick.php'            => __DIR__ . '/tick.php',
    'cron/scheduler.php'       => __DIR__ . '/scheduler.php',
    'cron/approval_timer.php'  => __DIR__ . '/approval_timer.php',
    'includes dir'             => $root . '/includes',
    'includes/database.php'    => $root . '/includes/database.php',
    'includes/config.php'      => $root . '/includes/config.php',
    'includes/helpers.php'     => $root . '/includes/helpers.php',
    'includes/auth.php'        => $root . '/includes/auth.php',
    'includes/autoblog_engine.php' => $root . '/includes/autoblog_engine.php',
    'includes/auto_daily.php'  => $root . '/includes/auto_daily.php',
    'includes/ai_provider.php' => $root . '/includes/ai_provider.php',
    'includes/anti_ai_sanitizer.php' => $root . '/includes/anti_ai_sanitizer.php',
    'includes/google_keyword_planner.php' => $root . '/includes/google_keyword_planner.php',
    'includes/keyword_flow.php' => $root . '/includes/keyword_flow.php',
    'includes/mailer.php'      => $root . '/includes/mailer.php',
    'data dir'                 => $root . '/data',
    'data/cron_secret.txt'     => $root . '/data/cron_secret.txt',
    'data/php_error.log'       => $root . '/data/php_error.log',
    'index.php'                => $root . '/index.php',
    'DB file (seo_autoblog.db)' => $root . '/seo_autoblog.db',
);
foreach ($checks as $label => $p) {
    $exists = @is_file($p) || @is_dir($p);
    $writable = $exists ? (@is_writable($p) ? 'writable' : 'NOT writable') : 'n/a';
    dbg(str_pad($label, 36) . ($exists ? 'FOUND (' . $writable . ')' : '*** MISSING ***'));
}

dbg('');
dbg('--- 2. Are the NEW versions uploaded? ---');
$markers = array(
    __DIR__ . '/tick.php'                          => 'AutoBlog Cron FATAL',
    $root . '/includes/autoblog_engine.php'        => 'topicPhotoUrlForTitle',
    $root . '/includes/auto_daily.php'             => 'processAutoBlogCampaignsUnlocked',
    $root . '/index.php'                           => 'auto-blog/cron-check',
);
foreach ($markers as $file => $needle) {
    if (!is_file($file)) { dbg(str_pad(basename(dirname($file)) . '/' . basename($file), 36) . 'MISSING'); continue; }
    $c = @file_get_contents($file);
    dbg(str_pad(basename(dirname($file)) . '/' . basename($file), 36) . (strpos((string)$c, $needle) !== false ? 'NEW version OK' : 'OLD version (missing marker)'));
}

dbg('');
dbg('--- 3. PHP syntax (parse) check of every .php file in the app ---');
$phpFiles = array();
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$limit = 0;
foreach ($rii as $file) {
    if ($file->isDir()) continue;
    $p = $file->getPathname();
    if (strpos($p, '/data/') !== false || strpos($p, '/published_posts/') !== false) continue;
    if (substr($p, -4) !== '.php') continue;
    $limit++;
    if ($limit > 400) break;
    $phpFiles[] = $p;
}
sort($phpFiles);
$bad = 0;
foreach ($phpFiles as $p) {
    $code = @file_get_contents($p);
    if ($code === false) { dbg(str_pad(substr($p, strlen($root) + 1), 50) . 'CANNOT READ (permission?)'); $bad++; continue; }
    try {
        token_get_all($code, TOKEN_PARSE);
        // dbg(str_pad(substr($p, strlen($root)+1), 50) . 'OK');  // uncomment for full list
    } catch (ParseError $e) {
        dbg('PARSE ERROR -> ' . substr($p, strlen($root) + 1) . '  @ line ' . $e->getLine() . ': ' . $e->getMessage());
        $bad++;
    }
}
dbg('Checked ' . count($phpFiles) . ' PHP files. ' . ($bad === 0 ? 'NO PARSE ERRORS — syntax is fine.' : ($bad . ' file(s) with PARSE ERRORS above.')));

dbg('');
dbg('--- 4. Step-by-step loading of includes (like cron/tick.php does) ---');
$files = array('database.php','config.php','helpers.php','auth.php','autoblog_engine.php','anti_ai_sanitizer.php','ai_provider.php','research_agent.php','mailer.php','google_keyword_planner.php','keyword_flow.php','internal_links.php','auto_daily.php');
$okAll = true;
foreach ($files as $f) {
    $p = $root . '/includes/' . $f;
    echo 'require includes/' . $f . ' ... ';
    if (!is_file($p)) { echo "MISSING\n"; $okAll = false; continue; }
    try {
        require_once $p;
        echo "OK\n";
    } catch (Throwable $e) {
        echo 'ERROR: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
        $okAll = false;
    }
}

dbg('');
dbg('--- 5. Database ---');
dbg('PDO sqlite extension: ' . (extension_loaded('pdo_sqlite') ? 'loaded' : '*** NOT LOADED ***'));
if ($okAll && function_exists('getDB')) {
    try {
        $db = getDB();
        dbg('DB opened: ' . DB_PATH);
        $q = $db->query('SELECT COUNT(*) FROM users');
        dbg('users rows : ' . $q->fetchColumn());
        $q = $db->query('SELECT COUNT(*) FROM campaigns');
        dbg('campaigns  : ' . $q->fetchColumn());
        try {
            $q = $db->query('SELECT COUNT(*) FROM auto_blog_jobs');
            dbg('auto_blog_jobs rows : ' . $q->fetchColumn());
        } catch (Throwable $e) {
            dbg('auto_blog_jobs table ERROR: ' . $e->getMessage());
        }
        try {
            $q = $db->query('SELECT COUNT(*) FROM auto_cron_log');
            dbg('auto_cron_log rows : ' . $q->fetchColumn());
        } catch (Throwable $e) {
            dbg('auto_cron_log table ERROR: ' . $e->getMessage());
        }
    } catch (Throwable $e) {
        dbg('DB ERROR: ' . $e->getMessage());
    }
}
dbg('Key functions after load:');
dbg('  processAutoBlogCampaigns : ' . (function_exists('processAutoBlogCampaigns') ? 'YES' : 'NO'));
dbg('  getAutoBlogCronSecret    : ' . (function_exists('getAutoBlogCronSecret') ? 'YES' : 'NO'));
dbg('  recordAutoCronRun        : ' . (function_exists('recordAutoCronRun') ? 'YES' : 'NO'));
dbg('  AIProviderClient class   : ' . (class_exists('AIProviderClient') ? 'YES' : 'NO'));

dbg('');
dbg('--- 6. Last lines of data/php_error.log (real errors, newest at bottom) ---');
$logf = $root . '/data/php_error.log';
if (is_file($logf)) {
    $lines = file($logf);
    if ($lines) {
        $tail = array_slice($lines, -25);
        dbg('(showing last ' . count($tail) . ' of ' . count($lines) . ' lines)');
        foreach ($tail as $ln) dbg(rtrim($ln));
    } else {
        dbg('php_error.log exists but is empty.');
    }
} else {
    dbg('No data/php_error.log file yet.');
}
dbg('');
dbg('--- 7. Cron secret check ---');
if (function_exists('getAutoBlogCronSecret')) {
    $s = getAutoBlogCronSecret();
    dbg('Secret file present/value length: ' . strlen($s) . ' chars. First 6: ' . substr($s, 0, 6) . '...');
} else {
    dbg('getAutoBlogCronSecret() not available — auto_daily.php did not load.');
}
dbg('');
dbg('END OF DIAGNOSTIC. Paste everything above to the developer.');
