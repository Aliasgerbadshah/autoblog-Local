<?php
/**
 * AutoBlog SaaS - Configuration
 * Hostinger Shared Hosting Compatible
 */

// Database
define('DB_PATH', __DIR__ . '/../seo_autoblog.db');

// Application
define('APP_BASE_URL', 'https://apps.colorfiind.com');
// SAFE_TEST_MODE: When true, blocks live API calls for testing. Default: false (live mode).
// Only DataForSEO is gated separately by DATAFORSEO_ENABLED.
define('SAFE_TEST_MODE', getenv('SAFE_TEST_MODE') === '1'); // Default: false (live)
define('DATAFORSEO_ENABLED', getenv('DATAFORSEO_ENABLED') === '1');
define('APPROVAL_WINDOW_MINUTES', SAFE_TEST_MODE ? 5 : 10);
define('REMINDER_INTERVAL_MINUTES', SAFE_TEST_MODE ? 15 : 240);
define('STATUS_DIGEST_MINUTES', SAFE_TEST_MODE ? 5 : 10);

// Brevo Email Defaults
define('DEFAULT_BREVO_API_KEY', getenv('BREVO_API_KEY') ?: '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');
define('DEFAULT_BREVO_SENDER_EMAIL', getenv('BREVO_SENDER_EMAIL') ?: 'aliasgershabbir52@gmail.com');
define('DEFAULT_BREVO_SENDER_NAME', 'AutoBlog SaaS Security');

// Output
define('OUTPUT_DIR', __DIR__ . '/../published_posts');

// Timezone
date_default_timezone_set('Asia/Kolkata');
