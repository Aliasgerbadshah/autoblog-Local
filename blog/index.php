<?php
/**
 * Website Blog — Homepage / Listing
 * Lists all published articles with pagination, categories, search.
 * COMPLETELY SEPARATE from Blogger.
 */
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/includes/publisher.php';

$cfg = require __DIR__ . '/config.php';
$publisher = new WebsitePublisher();

// Auto-publish any scheduled posts (cron replacement)
if ($cfg['auto_publish_on_visit']) {
    $publisher->publishScheduled();
}

// Pagination & filters
$page = max(1, intval($_GET['page'] ?? 1));
$category = trim($_GET['category'] ?? '');
$tag = trim($_GET['tag'] ?? '');
$search = trim($_GET['q'] ?? '');
$posts = $publisher->getPosts($page, $cfg['posts_per_page'], $category ?: null, $tag ?: null, $search ?: null);

// Get categories for sidebar
$categories = [];
$dbFile = $cfg['autoblog_root'] . '/includes/database.php';
if (file_exists($dbFile)) {
    require_once $dbFile;
    $db = Database::getInstance();
    $categories = $db->fetchAll("SELECT * FROM website_blog_categories WHERE post_count > 0 ORDER BY name ASC") ?: [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($cfg['site_name']); if ($category) echo ' — ' . htmlspecialchars($category); if ($search) echo ' — Search: ' . htmlspecialchars($search); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($cfg['site_tagline']); ?>">
    <link rel="alternate" type="application/rss+xml" title="<?php echo htmlspecialchars($cfg['site_name']); ?>" href="<?php echo $cfg['site_url']; ?>/rss.xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: <?php echo $cfg['primary_color']; ?>;
            --text: <?php echo $cfg['text_color']; ?>;
            --text-muted: <?php echo $cfg['text_muted']; ?>;
            --bg: <?php echo $cfg['bg_color']; ?>;
            --bg-light: <?php echo $cfg['bg_light']; ?>;
            --border: <?php echo $cfg['border_color']; ?>;
            --font: <?php echo $cfg['font_family']; ?>;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font); color: var(--text); background: var(--bg); line-height: 1.7; }
        
        /* HEADER */
        .blog-header { background: var(--bg); border-bottom: 1px solid var(--border); padding: 16px 0; position: sticky; top: 0; z-index: 100; }
        .blog-header-inner { max-width: 1140px; margin: 0 auto; padding: 0 24px; display: flex; justify-content: space-between; align-items: center; }
        .blog-logo { font-size: 1.3rem; font-weight: 800; color: var(--primary); text-decoration: none; letter-spacing: -0.02em; }
        .blog-nav a { color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 0.9rem; margin-left: 24px; }
        .blog-nav a:hover { color: var(--primary); }
        
        /* LAYOUT */
        .blog-container { max-width: 1140px; margin: 2rem auto; padding: 0 24px; display: grid; grid-template-columns: 1fr 320px; gap: 2.5rem; }
        
        /* SEARCH */
        .blog-search { margin-bottom: 1.5rem; }
        .blog-search input { width: 100%; padding: 12px 16px; border: 1px solid var(--border); border-radius: 10px; font-size: 0.95rem; font-family: var(--font); outline: none; }
        .blog-search input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(27, 87, 246, 0.1); }
        
        /* POST CARDS */
        .posts-grid { display: flex; flex-direction: column; gap: 1.5rem; }
        .post-card { display: grid; grid-template-columns: 1fr 280px; gap: 24px; align-items: center; padding: 1.5rem; background: var(--bg-light); border: 1px solid var(--border); border-radius: 16px; text-decoration: none; color: var(--text); transition: box-shadow 0.2s, transform 0.2s; }
        .post-card:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.08); transform: translateY(-2px); }
        .post-card-text { display: flex; flex-direction: column; gap: 8px; }
        .post-card-cat { display: inline-block; background: rgba(27,87,246,0.1); color: var(--primary); padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; width: fit-content; }
        .post-card-title { font-size: 1.15rem; font-weight: 800; line-height: 1.3; letter-spacing: -0.01em; }
        .post-card-excerpt { color: var(--text-muted); font-size: 0.88rem; line-height: 1.6; }
        .post-card-meta { color: var(--text-muted); font-size: 0.78rem; font-weight: 500; }
        .post-card-img { width: 100%; aspect-ratio: 16/10; object-fit: cover; border-radius: 12px; }
        
        /* SIDEBAR */
        .sidebar { display: flex; flex-direction: column; gap: 1.5rem; }
        .sidebar-box { background: var(--bg-light); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem; }
        .sidebar-title { font-size: 0.9rem; font-weight: 800; margin-bottom: 0.75rem; color: var(--text); text-transform: uppercase; letter-spacing: 0.05em; }
        .sidebar-item { display: block; padding: 6px 0; color: var(--text-muted); font-size: 0.85rem; text-decoration: none; font-weight: 500; border-bottom: 1px solid var(--border); }
        .sidebar-item:last-child { border-bottom: none; }
        .sidebar-item:hover { color: var(--primary); }
        .sidebar-item span { float: right; background: var(--bg); padding: 1px 8px; border-radius: 10px; font-size: 0.72rem; font-weight: 700; }
        
        /* PAGINATION */
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 2rem; }
        .pagination a, .pagination span { padding: 8px 14px; border: 1px solid var(--border); border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 0.85rem; color: var(--text-muted); }
        .pagination .current { background: var(--primary); color: #fff; border-color: var(--primary); }
        
        /* FOOTER */
        .blog-footer { border-top: 1px solid var(--border); padding: 2rem; text-align: center; color: var(--text-muted); font-size: 0.85rem; }
        
        /* EMPTY STATE */
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state h2 { font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem; color: var(--text); }
        
        /* MOBILE */
        @media (max-width: 768px) {
            .blog-container { grid-template-columns: 1fr; }
            .sidebar { display: none; }
            .post-card { grid-template-columns: 1fr; }
            .post-card-img { aspect-ratio: 16/9; }
        }
    </style>
</head>
<body>

<div class="blog-header">
    <div class="blog-header-inner">
        <a href="/blog/" class="blog-logo"><?php echo htmlspecialchars($cfg['site_name']); ?></a>
        <nav class="blog-nav">
            <a href="/blog/">All Posts</a>
            <?php foreach (array_slice($categories, 0, 4) as $cat): ?>
                <a href="/blog/?category=<?php echo urlencode($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></a>
            <?php endforeach; ?>
            <a href="/blog/rss.xml">RSS</a>
        </nav>
    </div>
</div>

<div class="blog-container">
    <main>
        <!-- SEARCH -->
        <div class="blog-search">
            <form method="get" action="/blog/">
                <input type="text" name="q" placeholder="Search articles..." value="<?php echo htmlspecialchars($search); ?>">
            </form>
        </div>
        
        <?php if ($category): ?>
            <h2 style="font-size:1.3rem;font-weight:800;margin-bottom:1rem;">Category: <?php echo htmlspecialchars($category); ?></h2>
        <?php endif; ?>
        <?php if ($tag): ?>
            <h2 style="font-size:1.3rem;font-weight:800;margin-bottom:1rem;">Tag: <?php echo htmlspecialchars($tag); ?></h2>
        <?php endif; ?>
        
        <?php if (empty($posts)): ?>
            <div class="empty-state">
                <h2>No articles yet</h2>
                <p>Articles will appear here once they are published from the AutoBlog dashboard.</p>
            </div>
        <?php else: ?>
            <div class="posts-grid">
                <?php foreach ($posts as $p): 
                    $pubDate = strtotime($p['published_date']);
                    $excerpt = substr(strip_tags($p['content_html'] ?? $p['meta_description'] ?? ''), 0, $cfg['excerpt_length']) . '...';
                ?>
                    <a href="<?php echo htmlspecialchars($p['url']); ?>" class="post-card">
                        <div class="post-card-text">
                            <span class="post-card-cat"><?php echo htmlspecialchars($p['category']); ?></span>
                            <h3 class="post-card-title"><?php echo htmlspecialchars($p['title']); ?></h3>
                            <p class="post-card-excerpt"><?php echo htmlspecialchars($excerpt); ?></p>
                            <div class="post-card-meta">
                                📅 <?php echo date('F j, Y', $pubDate); ?> · ⏱️ <?php echo $p['reading_time']; ?> min · ✍️ <?php echo htmlspecialchars($p['author'] ?? 'ColorFiind Team'); ?>
                            </div>
                        </div>
                        <?php if (!empty($p['thumbnail_url'])): ?>
                            <img src="<?php echo htmlspecialchars($p['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($p['title']); ?>" class="post-card-img" loading="lazy">
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <?php if ($page > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&category=<?php echo urlencode($category); ?>&q=<?php echo urlencode($search); ?>">← Previous</a>
                    <?php endif; ?>
                    <span class="current">Page <?php echo $page; ?></span>
                    <a href="?page=<?php echo $page + 1; ?>&category=<?php echo urlencode($category); ?>&q=<?php echo urlencode($search); ?>">Next →</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    
    <?php if ($cfg['sidebar_enabled']): ?>
    <aside class="sidebar">
        <?php if ($cfg['sidebar_categories'] && !empty($categories)): ?>
        <div class="sidebar-box">
            <h4 class="sidebar-title">Categories</h4>
            <?php foreach ($categories as $cat): ?>
                <a href="/blog/?category=<?php echo urlencode($cat['name']); ?>" class="sidebar-item">
                    <?php echo htmlspecialchars($cat['name']); ?> <span><?php echo $cat['post_count']; ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="sidebar-box">
            <h4 class="sidebar-title">Subscribe</h4>
            <p style="font-size:0.82rem;color:var(--text-muted);margin-bottom:8px;">Get the latest articles via RSS.</p>
            <a href="/blog/rss.xml" style="color:var(--primary);font-weight:700;font-size:0.85rem;text-decoration:none;">📡 RSS Feed →</a>
        </div>
    </aside>
    <?php endif; ?>
</div>

<div class="blog-footer">
    <?php echo htmlspecialchars($cfg['footer_text']); ?>
</div>

</body>
</html>
