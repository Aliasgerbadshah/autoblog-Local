<?php
/**
 * AutoBlog SaaS - Database Initialization
 * Creates all SQLite tables for the application
 */

require_once __DIR__ . '/config.php';

function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA journal_mode=WAL');
        $db->exec('PRAGMA foreign_keys=ON');
    }
    return $db;
}

function initAutoblogDB() {
    $db = getDB();

    $db->exec('CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER DEFAULT 1,
        slot_number INTEGER DEFAULT 1,
        title TEXT NOT NULL,
        slug TEXT NOT NULL,
        content TEXT NOT NULL,
        keyword_or_source TEXT,
        category TEXT,
        source_type TEXT DEFAULT \'AI/Template\',
        status TEXT DEFAULT \'Published\',
        published_url TEXT,
        featured_image TEXT,
        created_at TEXT
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS site_crawled_pages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        domain_url TEXT NOT NULL,
        page_url TEXT NOT NULL,
        page_title TEXT,
        created_at TEXT NOT NULL
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS scheduled_queue (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        slot_number INTEGER DEFAULT 1,
        topic_title TEXT NOT NULL,
        keyword TEXT NOT NULL,
        category TEXT DEFAULT \'General\',
        scheduled_time TEXT NOT NULL,
        target_platform TEXT DEFAULT \'local\',
        status TEXT DEFAULT \'Scheduled\',
        created_at TEXT NOT NULL,
        target_link TEXT,
        target_anchor TEXT,
        plan_id INTEGER,
        human_approved_at TEXT,
        error_message TEXT
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS keyword_research (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        slot_number INTEGER DEFAULT 1,
        domain_url TEXT,
        keyword TEXT NOT NULL,
        search_volume INTEGER,
        keyword_difficulty REAL,
        competition TEXT,
        intent TEXT,
        competitor_data TEXT,
        source TEXT DEFAULT \'DataForSEO\',
        status TEXT DEFAULT \'Discovered\',
        created_at TEXT NOT NULL,
        UNIQUE(user_id, slot_number, keyword)
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS campaigns (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        slot_number INTEGER DEFAULT 1,
        domain_url TEXT NOT NULL,
        target_country TEXT NOT NULL,
        language_code TEXT DEFAULT \'en\',
        days INTEGER NOT NULL,
        posts_per_day INTEGER NOT NULL,
        status TEXT DEFAULT \'Researching\',
        active_email_version INTEGER DEFAULT 1,
        start_date TEXT,
        posting_times TEXT DEFAULT \'["10:00"]\',
        target_platform TEXT DEFAULT \'blogger\',
        created_at TEXT NOT NULL
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS campaign_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_id INTEGER NOT NULL,
        day_number INTEGER NOT NULL,
        post_number INTEGER NOT NULL,
        title TEXT NOT NULL,
        primary_keyword TEXT NOT NULL,
        keyword_data TEXT NOT NULL,
        internal_links TEXT NOT NULL,
        external_links TEXT NOT NULL,
        headings TEXT NOT NULL,
        image_prompts TEXT NOT NULL,
        video_url TEXT,
        plan_status TEXT DEFAULT \'Pending\',
        article_status TEXT DEFAULT \'Not Created\',
        html_path TEXT,
        scheduled_time TEXT,
        scheduled_date TEXT,
        target_platform TEXT DEFAULT \'local\',
        created_at TEXT NOT NULL,
        FOREIGN KEY (campaign_id) REFERENCES campaigns(id)
    )');

    // Safe column additions for existing databases
    try { $db->exec('ALTER TABLE campaigns ADD COLUMN start_date TEXT'); } catch (Exception $e) {}
    try { $db->exec('ALTER TABLE campaigns ADD COLUMN posting_times TEXT DEFAULT \'["10:00"]\''); } catch (Exception $e) {}
    try { $db->exec('ALTER TABLE campaigns ADD COLUMN target_platform TEXT DEFAULT \'blogger\''); } catch (Exception $e) {}
    try { $db->exec('ALTER TABLE campaign_items ADD COLUMN scheduled_date TEXT'); } catch (Exception $e) {}
    try { $db->exec('ALTER TABLE campaign_items ADD COLUMN target_platform TEXT DEFAULT \'local\''); } catch (Exception $e) {}
    try { $db->exec('ALTER TABLE campaigns ADD COLUMN workflow_mode TEXT DEFAULT \'manual\''); } catch (Exception $e) {}
    try { $db->exec('ALTER TABLE campaigns ADD COLUMN keyword_source TEXT DEFAULT \'ai\''); } catch (Exception $e) {}
    try { $db->exec('ALTER TABLE campaign_items ADD COLUMN html_retry_count INTEGER DEFAULT 0'); } catch (Exception $e) {}
    try { $db->exec('ALTER TABLE campaign_items ADD COLUMN last_error TEXT'); } catch (Exception $e) {}
    try { $db->exec('ALTER TABLE scheduled_queue ADD COLUMN retry_count INTEGER DEFAULT 0'); } catch (Exception $e) {}
    try { $db->exec('ALTER TABLE scheduled_queue ADD COLUMN campaign_item_id INTEGER'); } catch (Exception $e) {}

    $db->exec('CREATE TABLE IF NOT EXISTS auto_cron_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        source TEXT DEFAULT \'cron\',
        ran_at TEXT NOT NULL,
        html_created INTEGER DEFAULT 0,
        published INTEGER DEFAULT 0,
        scheduled INTEGER DEFAULT 0,
        processed INTEGER DEFAULT 0,
        failed INTEGER DEFAULT 0,
        details TEXT,
        created_at TEXT NOT NULL
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS auto_blog_jobs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        slot_number INTEGER DEFAULT 1,
        enabled INTEGER DEFAULT 1,
        campaign_id INTEGER,
        domain_url TEXT NOT NULL,
        country TEXT DEFAULT \'India\',
        language_code TEXT DEFAULT \'en\',
        days INTEGER DEFAULT 7,
        posts_per_day INTEGER DEFAULT 1,
        start_date TEXT,
        posting_times TEXT DEFAULT \'["10:00"]\',
        target_platform TEXT DEFAULT \'blogger\',
        keyword_source TEXT DEFAULT \'planner\',
        last_run_at TEXT,
        last_error TEXT,
        created_at TEXT NOT NULL,
        UNIQUE(user_id, slot_number)
    )');

    // Created blog topics tracking (for dedup across campaigns)
    $db->exec('CREATE TABLE IF NOT EXISTS created_blog_topics (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        primary_keyword TEXT NOT NULL,
        domain_url TEXT,
        campaign_id INTEGER,
        created_at TEXT NOT NULL
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS approval_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        campaign_item_id INTEGER NOT NULL,
        approval_type TEXT NOT NULL,
        token TEXT UNIQUE NOT NULL,
        decision TEXT DEFAULT \'Pending\',
        created_at TEXT NOT NULL,
        email_version INTEGER DEFAULT 1,
        click_count INTEGER DEFAULT 0,
        first_decision TEXT,
        first_clicked_at TEXT
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS demo_emails (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        subject TEXT NOT NULL,
        html_content TEXT NOT NULL,
        created_at TEXT NOT NULL
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS content_plans (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        slot_number INTEGER DEFAULT 1,
        keyword_id INTEGER,
        title TEXT NOT NULL,
        primary_keyword TEXT NOT NULL,
        supporting_keywords TEXT,
        target_link TEXT,
        target_anchor TEXT,
        external_sources TEXT,
        image_plan TEXT,
        video_needed INTEGER DEFAULT 0,
        status TEXT DEFAULT \'Planned\',
        human_notes TEXT,
        created_at TEXT NOT NULL,
        approved_at TEXT
    )');
}

function initBacklinkDB() {
    $db = getDB();

    $db->exec('CREATE TABLE IF NOT EXISTS backlinks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER DEFAULT 1,
        slot_number INTEGER DEFAULT 1,
        target_site TEXT NOT NULL,
        backlink_url TEXT NOT NULL,
        my_url TEXT NOT NULL,
        anchor_text TEXT,
        is_dofollow INTEGER DEFAULT 0,
        status_code INTEGER,
        is_found INTEGER DEFAULT 0,
        last_checked TEXT,
        notes TEXT
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS outreach (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER DEFAULT 1,
        slot_number INTEGER DEFAULT 1,
        domain TEXT NOT NULL,
        contact_email TEXT,
        status TEXT DEFAULT \'Pending\',
        pitch_template TEXT,
        date_added TEXT
    )');
}

function initSocialDB() {
    $db = getDB();

    $db->exec('CREATE TABLE IF NOT EXISTS social_accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER DEFAULT 1,
        slot_number INTEGER DEFAULT 1,
        platform TEXT NOT NULL,
        account_name TEXT NOT NULL,
        access_token TEXT,
        page_id_or_board_id TEXT,
        posts_per_day INTEGER DEFAULT 2,
        is_active INTEGER DEFAULT 1,
        created_at TEXT
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS social_posts_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER DEFAULT 1,
        slot_number INTEGER DEFAULT 1,
        article_title TEXT NOT NULL,
        article_url TEXT NOT NULL,
        platform TEXT NOT NULL,
        post_id TEXT,
        status TEXT DEFAULT \'Sent\',
        error_message TEXT,
        timestamp TEXT
    )');
}

function initAuthDB() {
    $db = getDB();

    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        active_slot_id INTEGER DEFAULT 1,
        daily_email_notifications INTEGER DEFAULT 1,
        created_at TEXT NOT NULL
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS user_workspace_slots (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        slot_number INTEGER NOT NULL,
        slot_name TEXT NOT NULL,
        domain_url TEXT,
        target_goal TEXT DEFAULT \'Organic Search Traffic\',
        word_count_target TEXT DEFAULT \'1500-2000\',
        destination_platform TEXT DEFAULT \'local\',
        chat_credential_id TEXT,
        image_credential_id TEXT,
        seo_credential_id TEXT,
        blogger_credential_id TEXT,
        target_country TEXT,
        target_region TEXT,
        target_city TEXT,
        target_language TEXT,
        is_active INTEGER DEFAULT 1,
        created_at TEXT NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id),
        UNIQUE(user_id, slot_number)
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS email_otps (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        otp_code TEXT NOT NULL,
        expires_at TEXT NOT NULL,
        is_used INTEGER DEFAULT 0,
        created_at TEXT NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS user_credentials_vault (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        service_name TEXT NOT NULL,
        account_alias TEXT NOT NULL DEFAULT \'Default Account\',
        credential_data TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');
}

// Initialize all databases on include (with error handling)
try {
    initAutoblogDB();
    initBacklinkDB();
    initSocialDB();
    initAuthDB();
} catch (Exception $e) {
    // If database init fails, log but don't crash
    error_log('AutoBlog DB Init Error: ' . $e->getMessage());
}

/**
 * Lightweight wrapper used by the website blog publisher.
 * Existing code uses getDB() (PDO). The blog folder was written against
 * Database::getInstance() with exec/fetchOne/fetchAll + bound params.
 */
if (!class_exists('Database')) {
    class Database {
        public static function getInstance() {
            static $instance = null;
            if ($instance === null) {
                $instance = new self();
            }
            return $instance;
        }

        public function exec($sql, $params = []) {
            $pdo = getDB();
            if ($params === [] || $params === null) {
                return $pdo->exec($sql);
            }
            $stmt = $pdo->prepare($sql);
            return $stmt->execute(array_values($params));
        }

        public function fetchOne($sql, $params = []) {
            $pdo = getDB();
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($params));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }

        public function fetchAll($sql, $params = []) {
            $pdo = getDB();
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($params));
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}
