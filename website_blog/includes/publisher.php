<?php
/**
 * Website Blog Publisher
 * Publishes articles to the website_blog/posts/ folder as static HTML.
 * COMPLETELY SEPARATE from Blogger publishing.
 */

class WebsitePublisher {
    
    private $config;
    private $postsDir;
    
    public function __construct() {
        $this->config = require __DIR__ . '/../config.php';
        $this->postsDir = __DIR__ . '/../posts';
        $this->ensureDirs();
    }
    
    private function ensureDirs() {
        if (!is_dir($this->postsDir)) mkdir($this->postsDir, 0755, true);
        $year = date('Y');
        $month = date('m');
        $dir = "{$this->postsDir}/{$year}/{$month}";
        if (!is_dir($dir)) mkdir($dir, 0755, true);
    }
    
    /**
     * Publish an article to the website blog.
     * Creates a static HTML file in posts/YEAR/MONTH/slug/index.html
     * 
     * @param array $article  [title, slug, content_html, category, tags, thumbnail_url, author, scheduled_date, meta_description, meta_keywords]
     * @return array [success, url, path, message]
     */
    public function publish($article) {
        $title = $article['title'] ?? 'Untitled';
        $slug = $article['slug'] ?? $this->generateSlug($title);
        $contentHtml = $article['content_html'] ?? '';
        $category = $article['category'] ?? 'General';
        $tags = $article['tags'] ?? [];
        $thumbnailUrl = $article['thumbnail_url'] ?? '';
        $author = $article['author'] ?? 'ColorFiind Team';
        $scheduledDate = $article['scheduled_date'] ?? null;
        $metaDesc = $article['meta_description'] ?? '';
        $metaKeywords = $article['meta_keywords'] ?? '';
        $isDraft = !empty($scheduledDate) && strtotime($scheduledDate) > time();
        
        // Determine publish path
        $pubDate = $scheduledDate ? strtotime($scheduledDate) : time();
        $year = date('Y', $pubDate);
        $month = date('m', $pubDate);
        
        $dir = "{$this->postsDir}/{$year}/{$month}/{$slug}";
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        
        $filePath = "{$dir}/index.html";
        
        // Build the full page HTML
        $pageHtml = $this->renderArticlePage([
            'title' => $title,
            'slug' => $slug,
            'content_html' => $contentHtml,
            'category' => $category,
            'tags' => $tags,
            'thumbnail_url' => $thumbnailUrl,
            'author' => $author,
            'published_date' => date('Y-m-d', $pubDate),
            'published_date_formatted' => date('F j, Y', $pubDate),
            'meta_description' => $metaDesc,
            'meta_keywords' => $metaKeywords,
            'is_draft' => $isDraft,
            'url' => "/blog/posts/{$year}/{$month}/{$slug}/",
            'reading_time' => $this->calculateReadingTime($contentHtml),
        ]);
        
        $result = file_put_contents($filePath, $pageHtml);
        if ($result === false) {
            return ['success' => false, 'error' => "Failed to write file: $filePath"];
        }
        
        // Save to database for listing/scheduling
        $this->saveToDatabase([
            'title' => $title,
            'slug' => $slug,
            'category' => $category,
            'tags' => is_array($tags) ? implode(',', $tags) : $tags,
            'thumbnail_url' => $thumbnailUrl,
            'author' => $author,
            'published_date' => date('Y-m-d H:i:s', $pubDate),
            'file_path' => $filePath,
            'url' => "/blog/posts/{$year}/{$month}/{$slug}/",
            'status' => $isDraft ? 'scheduled' : 'published',
            'scheduled_date' => $scheduledDate,
            'meta_description' => $metaDesc,
            'reading_time' => $this->calculateReadingTime($contentHtml),
            'content_html' => $contentHtml,
        ]);
        
        // Update RSS and sitemap
        $this->updateRSS();
        $this->updateSitemap();
        
        $fullUrl = $this->config['site_url'] . "/posts/{$year}/{$month}/{$slug}/";
        
        return [
            'success' => true,
            'url' => $fullUrl,
            'path' => $filePath,
            'status' => $isDraft ? 'scheduled' : 'published',
            'message' => $isDraft 
                ? "Article scheduled for {$scheduledDate}. File saved at: {$filePath}" 
                : "Article published! URL: {$fullUrl}",
        ];
    }
    
    /**
     * Generate a URL-safe slug from title.
     */
    private function generateSlug($title) {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');
        $slug = substr($slug, 0, 80);
        
        // Avoid duplicate slugs
        $base = $slug;
        $i = 1;
        while (glob("{$this->postsDir}/*/*/{$slug}")) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
    
    /**
     * Calculate reading time in minutes.
     */
    private function calculateReadingTime($html) {
        $text = strip_tags($html);
        $words = str_word_count($text);
        return max(1, ceil($words / 200));
    }
    
    /**
     * Render the full article page using the template.
     */
    private function renderArticlePage($data) {
        $cfg = $this->config;
        $t = $data;
        
        // Load template
        $templateFile = __DIR__ . '/../templates/article.html';
        if (file_exists($templateFile)) {
            $template = file_get_contents($templateFile);
        } else {
            $template = $this->getDefaultArticleTemplate();
        }
        
        // Load components
        $header = $this->loadComponent('header');
        $footer = $this->loadComponent('footer');
        
        // Replace template variables
        $replacements = [
            '{{HEADER}}' => $header,
            '{{FOOTER}}' => $footer,
            '{{SITE_NAME}}' => $cfg['site_name'],
            '{{SITE_URL}}' => $cfg['site_url'],
            '{{SITE_TAGLINE}}' => $cfg['site_tagline'],
            '{{PRIMARY_COLOR}}' => $cfg['primary_color'],
            '{{FONT_FAMILY}}' => $cfg['font_family'],
            '{{TITLE}}' => htmlspecialchars($t['title']),
            '{{CATEGORY}}' => htmlspecialchars($t['category']),
            '{{CONTENT_HTML}}' => $t['content_html'],
            '{{THUMBNAIL_URL}}' => $t['thumbnail_url'],
            '{{AUTHOR}}' => htmlspecialchars($t['author']),
            '{{PUBLISHED_DATE}}' => $t['published_date'],
            '{{PUBLISHED_DATE_FORMATTED}}' => $t['published_date_formatted'],
            '{{READING_TIME}}' => $t['reading_time'],
            '{{META_DESCRIPTION}}' => htmlspecialchars($t['meta_description']),
            '{{META_KEYWORDS}}' => htmlspecialchars($t['meta_keywords'] ?? ''),
            '{{ARTICLE_URL}}' => $cfg['site_url'] . $t['url'],
            '{{OG_IMAGE}}' => $t['thumbnail_url'] ?: $cfg['og_image_default'],
            '{{TWITTER_HANDLE}}' => $cfg['twitter_handle'],
            '{{BREADCRUMB}}' => $this->renderBreadcrumb($t),
            '{{TAGS_HTML}}' => $this->renderTags($t['tags'] ?? []),
            '{{SHARE_BUTTONS}}' => $cfg['show_share_buttons'] ? $this->renderShareButtons($t) : '',
            '{{RELATED_POSTS}}' => $cfg['show_related_posts'] ? $this->renderRelatedPosts($t) : '',
            '{{DRAFT_META}}' => $t['is_draft'] ? '<meta name="robots" content="noindex,nofollow">' : '',
        ];
        
        foreach ($replacements as $key => $value) {
            $template = str_replace($key, $value, $template);
        }
        
        return $template;
    }
    
    /**
     * Load a template component.
     */
    private function loadComponent($name) {
        $file = __DIR__ . "/../templates/components/{$name}.html";
        if (file_exists($file)) return file_get_contents($file);
        return '';
    }
    
    /**
     * Render breadcrumb.
     */
    private function renderBreadcrumb($t) {
        if (!$this->config['show_breadcrumb']) return '';
        return '<nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/blog/">Home</a> <span>›</span>
            <a href="/blog/category/' . urlencode($t['category']) . '/">' . htmlspecialchars($t['category']) . '</a> <span>›</span>
            <span>' . htmlspecialchars($t['title']) . '</span>
        </nav>';
    }
    
    /**
     * Render tags.
     */
    private function renderTags($tags) {
        if (!$this->config['show_tags'] || empty($tags)) return '';
        if (is_string($tags)) $tags = explode(',', $tags);
        $html = '<div class="article-tags">';
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if (empty($tag)) continue;
            $html .= '<a href="/blog/tag/' . urlencode($tag) . '/" class="tag">' . htmlspecialchars($tag) . '</a>';
        }
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render share buttons.
     */
    private function renderShareButtons($t) {
        $url = urlencode($this->config['site_url'] . $t['url']);
        $title = urlencode($t['title']);
        return '<div class="share-buttons">
            <span class="share-label">Share:</span>
            <a href="https://twitter.com/intent/tweet?url=' . $url . '&text=' . $title . '" target="_blank" rel="noopener" class="share-btn share-twitter">𝕏</a>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url=' . $url . '" target="_blank" rel="noopener" class="share-btn share-linkedin">in</a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=' . $url . '" target="_blank" rel="noopener" class="share-btn share-facebook">f</a>
            <a href="javascript:navigator.clipboard.writeText(\'' . $this->config['site_url'] . $t['url'] . '\');alert(\'Link copied!\')" class="share-btn share-copy">📋</a>
        </div>';
    }
    
    /**
     * Render related posts.
     */
    private function renderRelatedPosts($t) {
        // Will be populated from database
        return '<div class="related-posts" id="related-posts" data-category="' . htmlspecialchars($t['category']) . '" data-exclude="' . htmlspecialchars($t['slug']) . '">
            <h3 class="related-title">Related Articles</h3>
            <div class="related-grid" id="related-grid">Loading...</div>
        </div>';
    }
    
    /**
     * Save post to database.
     */
    private function saveToDatabase($data) {
        $autoblogRoot = $this->config['autoblog_root'];
        $dbFile = $autoblogRoot . '/includes/database.php';
        if (!file_exists($dbFile)) return;
        
        require_once $dbFile;
        $db = Database::getInstance();
        
        // Create table if not exists
        $db->exec("CREATE TABLE IF NOT EXISTS website_blog_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            category TEXT DEFAULT 'General',
            tags TEXT DEFAULT '',
            thumbnail_url TEXT DEFAULT '',
            author TEXT DEFAULT 'ColorFiind Team',
            published_date DATETIME,
            file_path TEXT,
            url TEXT,
            status TEXT DEFAULT 'published',
            scheduled_date DATETIME,
            meta_description TEXT DEFAULT '',
            reading_time INTEGER DEFAULT 1,
            content_html TEXT DEFAULT '',
            views INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS website_blog_categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            description TEXT DEFAULT '',
            post_count INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS website_blog_tags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            post_count INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Insert or update post
        $existing = $db->fetchOne("SELECT id FROM website_blog_posts WHERE slug = ?", [$data['slug']]);
        if ($existing) {
            $db->exec("UPDATE website_blog_posts SET 
                title=?, category=?, tags=?, thumbnail_url=?, author=?, 
                published_date=?, file_path=?, url=?, status=?, scheduled_date=?, 
                meta_description=?, reading_time=?, content_html=?, updated_at=CURRENT_TIMESTAMP 
                WHERE slug=?", [
                $data['title'], $data['category'], $data['tags'], $data['thumbnail_url'], $data['author'],
                $data['published_date'], $data['file_path'], $data['url'], $data['status'], $data['scheduled_date'],
                $data['meta_description'], $data['reading_time'], $data['content_html'], $data['slug']
            ]);
        } else {
            $db->exec("INSERT INTO website_blog_posts 
                (title, slug, category, tags, thumbnail_url, author, published_date, file_path, url, status, scheduled_date, meta_description, reading_time, content_html) 
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [
                $data['title'], $data['slug'], $data['category'], $data['tags'], $data['thumbnail_url'], $data['author'],
                $data['published_date'], $data['file_path'], $data['url'], $data['status'], $data['scheduled_date'],
                $data['meta_description'], $data['reading_time'], $data['content_html']
            ]);
        }
        
        // Update category count
        if (!empty($data['category'])) {
            $catSlug = strtolower(preg_replace('/[^a-z0-9]+/', '-', strtolower($data['category'])));
            $db->exec("INSERT OR IGNORE INTO website_blog_categories (name, slug) VALUES (?, ?)", [$data['category'], $catSlug]);
            $db->exec("UPDATE website_blog_categories SET post_count = (SELECT COUNT(*) FROM website_blog_posts WHERE category = ? AND status = 'published') WHERE name = ?", [$data['category'], $data['category']]);
        }
        
        // Update tag counts
        if (!empty($data['tags'])) {
            $tagList = is_array($data['tags']) ? $data['tags'] : explode(',', $data['tags']);
            foreach ($tagList as $tag) {
                $tag = trim($tag);
                if (empty($tag)) continue;
                $tagSlug = strtolower(preg_replace('/[^a-z0-9]+/', '-', strtolower($tag)));
                $db->exec("INSERT OR IGNORE INTO website_blog_tags (name, slug) VALUES (?, ?)", [$tag, $tagSlug]);
                $db->exec("UPDATE website_blog_tags SET post_count = (SELECT COUNT(*) FROM website_blog_posts WHERE tags LIKE ? AND status = 'published') WHERE name = ?", ['%' . $tag . '%', $tag]);
            }
        }
    }
    
    /**
     * Publish any scheduled posts whose time has come.
     * Called on blog visit or "Run Cron Now" button.
     */
    public function publishScheduled() {
        $autoblogRoot = $this->config['autoblog_root'];
        $dbFile = $autoblogRoot . '/includes/database.php';
        if (!file_exists($dbFile)) return ['published' => 0];
        
        require_once $dbFile;
        $db = Database::getInstance();
        
        $limit = $this->config['schedule_check_limit'] ?? 5;
        $now = date('Y-m-d H:i:s');
        
        $rows = $db->fetchAll(
            "SELECT * FROM website_blog_posts WHERE status = 'scheduled' AND scheduled_date <= ? ORDER BY scheduled_date ASC LIMIT ?",
            [$now, $limit]
        );
        
        $published = 0;
        foreach ($rows as $row) {
            // Update status
            $db->exec("UPDATE website_blog_posts SET status = 'published', updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$row['id']]);
            
            // If the file exists, remove draft meta tag
            if (!empty($row['file_path']) && file_exists($row['file_path'])) {
                $html = file_get_contents($row['file_path']);
                $html = str_replace('<meta name="robots" content="noindex,nofollow">', '', $html);
                file_put_contents($row['file_path'], $html);
            }
            
            $published++;
        }
        
        if ($published > 0) {
            $this->updateRSS();
            $this->updateSitemap();
        }
        
        return ['published' => $published];
    }
    
    /**
     * Get posts for listing page.
     */
    public function getPosts($page = 1, $perPage = null, $category = null, $tag = null, $search = null) {
        $autoblogRoot = $this->config['autoblog_root'];
        $dbFile = $autoblogRoot . '/includes/database.php';
        if (!file_exists($dbFile)) return [];
        
        require_once $dbFile;
        $db = Database::getInstance();
        
        $perPage = $perPage ?: $this->config['posts_per_page'];
        $offset = ($page - 1) * $perPage;
        
        $where = "status = 'published'";
        $params = [];
        
        if ($category) {
            $where .= " AND category = ?";
            $params[] = $category;
        }
        if ($tag) {
            $where .= " AND tags LIKE ?";
            $params[] = "%{$tag}%";
        }
        if ($search) {
            $where .= " AND (title LIKE ? OR meta_description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        
        $params[] = $perPage;
        $params[] = $offset;
        
        return $db->fetchAll(
            "SELECT * FROM website_blog_posts WHERE {$where} ORDER BY published_date DESC LIMIT ? OFFSET ?",
            $params
        );
    }
    
    /**
     * Update RSS feed.
     */
    public function updateRSS() {
        if (!$this->config['rss_enabled']) return;
        
        $posts = $this->getPosts(1, 50);
        $cfg = $this->config;
        
        $rss = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $rss .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $rss .= '<channel>' . "\n";
        $rss .= '<title>' . htmlspecialchars($cfg['site_name']) . '</title>' . "\n";
        $rss .= '<link>' . $cfg['site_url'] . '</link>' . "\n";
        $rss .= '<description>' . htmlspecialchars($cfg['site_tagline']) . '</description>' . "\n";
        $rss .= '<atom:link href="' . $cfg['site_url'] . '/rss.xml" rel="self" type="application/rss+xml"/>' . "\n";
        
        foreach ($posts as $p) {
            $rss .= '<item>' . "\n";
            $rss .= '<title>' . htmlspecialchars($p['title']) . '</title>' . "\n";
            $rss .= '<link>' . $cfg['site_url'] . $p['url'] . '</link>' . "\n";
            $rss .= '<description>' . htmlspecialchars($p['meta_description'] ?: substr(strip_tags($p['content_html'] ?? ''), 0, 200)) . '</description>' . "\n";
            $rss .= '<pubDate>' . date('r', strtotime($p['published_date'])) . '</pubDate>' . "\n";
            $rss .= '<author>' . htmlspecialchars($p['author'] ?? 'ColorFiind Team') . '</author>' . "\n";
            $rss .= '<category>' . htmlspecialchars($p['category']) . '</category>' . "\n";
            $rss .= '</item>' . "\n";
        }
        
        $rss .= '</channel></rss>';
        
        file_put_contents(__DIR__ . '/../rss.xml', $rss);
    }
    
    /**
     * Update sitemap.xml.
     */
    public function updateSitemap() {
        if (!$this->config['sitemap_enabled']) return;
        
        $posts = $this->getPosts(1, 1000);
        $cfg = $this->config;
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // Homepage
        $xml .= '<url><loc>' . $cfg['site_url'] . '/</loc><changefreq>daily</changefreq><priority>1.0</priority></url>' . "\n";
        
        foreach ($posts as $p) {
            $xml .= '<url>' . "\n";
            $xml .= '<loc>' . $cfg['site_url'] . $p['url'] . '</loc>' . "\n";
            $xml .= '<lastmod>' . date('Y-m-d', strtotime($p['updated_at'] ?? $p['published_date'])) . '</lastmod>' . "\n";
            $xml .= '<changefreq>monthly</changefreq><priority>0.8</priority>' . "\n";
            $xml .= '</url>' . "\n";
        }
        
        $xml .= '</urlset>';
        
        file_put_contents(__DIR__ . '/../sitemap.xml', $xml);
    }
    
    /**
     * Delete a post.
     */
    public function deletePost($slug) {
        $autoblogRoot = $this->config['autoblog_root'];
        $dbFile = $autoblogRoot . '/includes/database.php';
        if (!file_exists($dbFile)) return ['success' => false, 'error' => 'Database not found'];
        
        require_once $dbFile;
        $db = Database::getInstance();
        
        $row = $db->fetchOne("SELECT * FROM website_blog_posts WHERE slug = ?", [$slug]);
        if (!$row) return ['success' => false, 'error' => 'Post not found'];
        
        // Delete file
        if (!empty($row['file_path']) && file_exists($row['file_path'])) {
            $dir = dirname($row['file_path']);
            unlink($row['file_path']);
            // Remove empty directory
            if (is_dir($dir) && count(scandir($dir)) <= 2) rmdir($dir);
        }
        
        // Delete from DB
        $db->exec("DELETE FROM website_blog_posts WHERE slug = ?", [$slug]);
        
        $this->updateRSS();
        $this->updateSitemap();
        
        return ['success' => true, 'message' => 'Post deleted'];
    }
}
