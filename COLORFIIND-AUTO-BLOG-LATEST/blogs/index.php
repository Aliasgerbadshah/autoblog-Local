<?php
/**
 * /blogs -> /blog/ redirect shim.
 *
 * DEPLOY: upload this whole "blogs" folder to public_html/blogs/ so that
 * https://colorfiind.com/blogs lands here instead of the main site's homepage.
 *
 * WHY: the main website's "Blog" menu option points at /blogs (plural), which
 * is not a real page — the visitor just sees the palette homepage again. The
 * real blog list lives at /blog/ (singular). This shim 301-redirects every
 * /blogs/* URL to its /blog/* equivalent:
 *   /blogs              -> /blog/
 *   /blogs/category/X/  -> /blog/category/X/
 *   /blogs/page/2/      -> /blog/page/2/  (and ?page=2 style links)
 *
 * NOTE: the permanent fix is to change the Blog menu link in the MAIN website
 * code to https://colorfiind.com/blog/ — this shim just guarantees visitors
 * (and Google) always land on the real blog list either way.
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/blogs/', PHP_URL_PATH);
$rest = preg_replace('#^/blogs/?#', '', (string)$uri);
$target = 'https://colorfiind.com/blog/' . ltrim($rest, '/');
if (!empty($_SERVER['QUERY_STRING'])) {
    $target .= '?' . $_SERVER['QUERY_STRING'];
}
header('Location: ' . $target, true, 301);
header('Cache-Control: max-age=86400');
exit;
