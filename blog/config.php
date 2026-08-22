<?php
/**
 * Website Blog Configuration
 * Change THESE settings to customize your blog — no code editing needed.
 * This is COMPLETELY SEPARATE from Blogger settings.
 */

return [
    // ═══════════════════════════════════════════
    // SITE IDENTITY
    // ═══════════════════════════════════════════
    'site_name'        => 'ColorFiind Blog',
    'site_tagline'     => 'Insights, Trends & Strategies',
    'site_url'         => 'https://colorfiind.com/blog',
    'logo_url'         => '/blog/assets/images/logo.png',
    'favicon_url'      => '/blog/assets/images/favicon.png',

    // ═══════════════════════════════════════════
    // DESIGN & COLORS
    // ═══════════════════════════════════════════
    'primary_color'    => '#1b57f6',
    'primary_hover'    => '#1243cb',
    'accent_color'     => '#10b981',
    'text_color'       => '#0f172a',
    'text_muted'       => '#64748b',
    'bg_color'         => '#ffffff',
    'bg_light'         => '#f8fafc',
    'border_color'     => '#e2e8f0',
    'font_family'      => "'Montserrat', -apple-system, sans-serif",
    'font_size_base'   => '16px',

    // ═══════════════════════════════════════════
    // LAYOUT: "heading-left-image-right"
    // ═══════════════════════════════════════════
    'layout'           => 'heading-left-image-right',
    // Hero section: H1 + category on LEFT, thumbnail image on RIGHT
    // Below hero: full-width centered article content

    'posts_per_page'   => 12,
    'excerpt_length'   => 160,       // Characters for listing excerpt

    // ═══════════════════════════════════════════
    // ARTICLE DISPLAY
    // ═══════════════════════════════════════════
    'show_author'          => true,
    'show_date'            => true,
    'show_reading_time'    => true,
    'show_categories'      => true,
    'show_tags'            => true,
    'show_related_posts'   => true,
    'related_posts_count'  => 3,
    'show_share_buttons'   => true,
    'show_breadcrumb'      => true,

    // ═══════════════════════════════════════════
    // SEO
    // ═══════════════════════════════════════════
    'meta_title_suffix'  => ' | ColorFiind Blog',
    'og_image_default'   => '/blog/assets/images/og-default.jpg',
    'twitter_handle'     => '@colorfiind',

    // ═══════════════════════════════════════════
    // SIDEBAR (shown on listing page only)
    // ═══════════════════════════════════════════
    'sidebar_enabled'        => true,
    'sidebar_recent_count'  => 5,
    'sidebar_categories'    => true,
    'sidebar_tags'          => true,
    'sidebar_search'        => true,

    // ═══════════════════════════════════════════
    // FOOTER
    // ═══════════════════════════════════════════
    'footer_text'       => '© 2026 ColorFiind. All rights reserved.',
    'social_links'      => [
        // ['platform' => 'Twitter', 'url' => 'https://twitter.com/colorfiind', 'icon' => '𝕏'],
        // ['platform' => 'LinkedIn', 'url' => 'https://linkedin.com/company/colorfiind', 'icon' => 'in'],
        // ['platform' => 'Instagram', 'url' => 'https://instagram.com/colorfiind', 'icon' => '📷'],
    ],

    // ═══════════════════════════════════════════
    // SCHEDULING (works WITHOUT cron)
    // ═══════════════════════════════════════════
    // When a visitor loads the blog, this file checks for due scheduled posts
    // and publishes them automatically. "Run Cron Now" button also triggers it.
    'auto_publish_on_visit'  => true,    // Publish due postsB when anyone visits
    'schedule_check_limit'   => 5,       // Max posts to publish per check

    // ═══════════════════════════════════════════
    // RSS & SITEMAP
    // ═══════════════════════════════════════════
    'rss_enabled'       => true,
    'sitemap_enabled'   => true,

    // ═══════════════════════════════════════════
    // DATABASE (shared with autoblog — same DB, different tables)
    // ═══════════════════════════════════════════
    'db_table_posts'    => 'website_blog_posts',    // Separate table for website posts
    'db_table_categories' => 'website_blog_categories',
    'db_table_tags'     => 'website_blog_tags',

    // ═══════════════════════════════════════════
    // INTEGRATION WITH AUTOBLOG SYSTEM
    // ═══════════════════════════════════════════
    // Path back to autoblog root (for shared DB, AI, images)
    // colorfiind.com/blog/ → public_html/blog/
    // apps.colorfiind.com → public_html/sub_apps/
    'autoblog_root'     => dirname(__DIR__) . '/sub_apps',
];
