<?php
/**
 * Separate internal-link sheets for Blogger vs Website.
 * Excel-compatible CSV. Related links only — never random.
 */

function internalLinksCsvPath($platform) {
    $name = ($platform === 'website') ? 'internal_links_website.csv' : 'internal_links_blogger.csv';
    $dir = dirname(__DIR__) . '/data';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir . '/' . $name;
}

function internalLinksCsvHeader() {
    return ['Title', 'URL', 'Keyword', 'Status', 'Platform', 'Updated'];
}

function readInternalLinkRows($platform) {
    $path = internalLinksCsvPath($platform);
    $rows = [];
    if (!is_file($path)) return $rows;
    $fp = @fopen($path, 'r');
    if (!$fp) return $rows;
    $header = fgetcsv($fp);
    while (($row = fgetcsv($fp)) !== false) {
        if (empty($row[1])) continue;
        $rows[] = [
            'title' => trim((string)($row[0] ?? '')),
            'url' => trim((string)($row[1] ?? '')),
            'keyword' => trim((string)($row[2] ?? '')),
            'status' => trim((string)($row[3] ?? '')),
            'platform' => trim((string)($row[4] ?? $platform)),
            'updated' => trim((string)($row[5] ?? '')),
        ];
    }
    fclose($fp);
    return $rows;
}

function writeInternalLinkRows($platform, $rows) {
    $path = internalLinksCsvPath($platform);
    $fp = @fopen($path, 'w');
    if (!$fp) return false;
    fputcsv($fp, internalLinksCsvHeader());
    foreach ($rows as $r) {
        if (empty($r['url'])) continue;
        fputcsv($fp, [
            $r['title'] ?? '',
            $r['url'] ?? '',
            $r['keyword'] ?? '',
            $r['status'] ?? '',
            $r['platform'] ?? $platform,
            $r['updated'] ?? date('Y-m-d H:i:s'),
        ]);
    }
    fclose($fp);
    return true;
}

function recordInternalLinkRow($platform, $title, $url, $keyword = '', $status = 'published') {
    $url = trim((string)$url);
    $title = trim((string)$title);
    if ($url === '' || $title === '') return false;
    if (!preg_match('#^https?://#i', $url) && $url[0] !== '/') return false;
    $platform = ($platform === 'website') ? 'website' : 'blogger';
    $rows = readInternalLinkRows($platform);
    $found = false;
    foreach ($rows as &$r) {
        if (strcasecmp((string)$r['url'], $url) === 0) {
            $r['title'] = $title;
            $r['keyword'] = $keyword !== '' ? $keyword : ($r['keyword'] ?? '');
            $r['status'] = $status;
            $r['platform'] = $platform;
            $r['updated'] = date('Y-m-d H:i:s');
            $found = true;
            break;
        }
    }
    unset($r);
    if (!$found) {
        $rows[] = [
            'title' => $title,
            'url' => $url,
            'keyword' => $keyword,
            'status' => $status,
            'platform' => $platform,
            'updated' => date('Y-m-d H:i:s'),
        ];
    }
    return writeInternalLinkRows($platform, $rows);
}

function internalLinkRelevanceScore($title, $keyword, $row) {
    $a = strtolower(trim($title . ' ' . $keyword));
    $b = strtolower(trim(($row['title'] ?? '') . ' ' . ($row['keyword'] ?? '')));
    if ($a === '' || $b === '') return 0;
    similar_text($a, $b, $pct);
    $wa = array_values(array_filter(preg_split('/\W+/', $a), function ($w) { return strlen($w) > 3; }));
    $wb = array_values(array_filter(preg_split('/\W+/', $b), function ($w) { return strlen($w) > 3; }));
    $overlap = count(array_intersect($wa, $wb));
    return $pct + ($overlap * 10);
}

/**
 * Related published/scheduled posts only. Prefer same platform, then the other if relevant.
 */
function findRelatedInternalLinks($title, $keyword, $preferPlatform = 'blogger', $excludeUrl = '', $limit = 2) {
    $preferPlatform = ($preferPlatform === 'website') ? 'website' : 'blogger';
    $other = ($preferPlatform === 'website') ? 'blogger' : 'website';
    $candidates = [];
    foreach ([$preferPlatform, $other] as $plat) {
        foreach (readInternalLinkRows($plat) as $row) {
            if (($row['url'] ?? '') === '' || strcasecmp((string)$row['url'], (string)$excludeUrl) === 0) continue;
            if (stripos($row['title'] ?? '', $title) !== false && strcasecmp($row['title'] ?? '', $title) === 0) continue;
            $score = internalLinkRelevanceScore($title, $keyword, $row);
            if ($score < 28) continue;
            $row['_score'] = $score + (($plat === $preferPlatform) ? 8 : 0);
            $candidates[] = $row;
        }
    }
    usort($candidates, function ($a, $b) { return ($b['_score'] <=> $a['_score']); });
    $out = [];
    $seen = [];
    foreach ($candidates as $row) {
        $u = strtolower($row['url']);
        if (isset($seen[$u])) continue;
        $seen[$u] = true;
        $out[] = [
            'url' => $row['url'],
            'anchor_text' => $row['title'],
            'platform' => $row['platform'],
            'keyword' => $row['keyword'] ?? '',
        ];
        if (count($out) >= $limit) break;
    }
    return $out;
}

function mergeRelatedLinksIntoPlan($links, $title, $keyword, $platform, $domainUrl = '') {
    $links = is_array($links) ? $links : [];
    $have = [];
    foreach ($links as $l) {
        $u = strtolower(trim((string)($l['url'] ?? '')));
        if ($u !== '') $have[$u] = true;
    }
    if ($domainUrl) {
        $du = rtrim($domainUrl, '/');
        if ($du !== '' && empty($have[strtolower($du)])) {
            $links[] = ['url' => $du, 'anchor_text' => 'our website'];
            $have[strtolower($du)] = true;
        }
    }
    foreach (findRelatedInternalLinks($title, $keyword, $platform) as $rel) {
        $u = strtolower($rel['url']);
        if (isset($have[$u])) continue;
        $links[] = ['url' => $rel['url'], 'anchor_text' => $rel['anchor_text']];
        $have[$u] = true;
    }
    return $links;
}
