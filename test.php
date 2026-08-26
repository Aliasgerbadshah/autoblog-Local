<?php
echo '<h1>AutoBlog Diagnostics</h1>';
echo '<p>PHP Version: ' . phpversion() . '</p>';
echo '<p>Request URI: ' . htmlspecialchars($_SERVER['REQUEST_URI']) . '</p>';
echo '<p>Document Root: ' . $_SERVER['DOCUMENT_ROOT'] . '</p>';
echo '<p>__DIR__: ' . __DIR__ . '</p>';
echo '<p>Script Name: ' . $_SERVER['SCRIPT_NAME'] . '</p>';

// Check files
$files = ['index.php', 'login.php', 'register.php', 'logout.php', 'includes/config.php', 'includes/database.php', 'includes/auth.php', 'includes/helpers.php', 'includes/autoblog_engine.php', 'includes/mailer.php', 'templates/index.html', '.htaccess'];
echo '<h2>File Check</h2><ul>';
foreach ($files as $f) {
    $full = __DIR__ . '/' . $f;
    echo '<li>' . $f . ': ' . (file_exists($full) ? '<b style="color:green">EXISTS</b>' : '<b style="color:red">MISSING</b>') . '</li>';
}
echo '</ul>';

// Test database
echo '<h2>Database Test</h2>';
try {
    require_once __DIR__ . '/includes/config.php';
    echo '<p>Config loaded. DB_PATH: ' . DB_PATH . '</p>';
    echo '<p>APP_BASE_URL: ' . APP_BASE_URL . '</p>';
    if (file_exists(DB_PATH)) {
        echo '<p>DB file exists: YES</p>';
    } else {
        echo '<p>DB file exists: NO (will be created on first load)</p>';
        echo '<p>Dir writable: ' . (is_writable(dirname(DB_PATH)) ? 'YES' : 'NO') . '</p>';
    }
} catch (Exception $e) {
    echo '<p style="color:red">Config Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

// Test session
echo '<h2>Session Test</h2>';
try {
    session_start();
    $_SESSION['test'] = 'working';
    echo '<p>Session: WORKING</p>';
} catch (Exception $e) {
    echo '<p style="color:red">Session Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>
