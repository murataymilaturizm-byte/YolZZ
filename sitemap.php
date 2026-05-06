<?php
/**
 * Dinamik Sitemap XML Oluşturucu
 * URL: /sitemap.xml
 *
 * .htaccess'te şu satır olmalı:
 * RewriteRule ^sitemap\.xml$ sitemap.php [L]
 */
define('YOLZZ_APP', true);
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$base = rtrim(SITE_URL, '/');
$today = date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

function url_node($loc, $lastmod = null, $changefreq = 'weekly', $priority = '0.7') {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n";
    if ($lastmod) echo "    <lastmod>" . $lastmod . "</lastmod>\n";
    echo "    <changefreq>" . $changefreq . "</changefreq>\n";
    echo "    <priority>" . $priority . "</priority>\n";
    echo "  </url>\n";
}

// STATİK SAYFALAR
url_node($base . '/', $today, 'daily', '1.0');
url_node($base . '/filo', $today, 'daily', '0.9');
url_node($base . '/lokasyonlar', $today, 'weekly', '0.9');
url_node($base . '/kampanyalar', $today, 'daily', '0.8');
url_node($base . '/blog', $today, 'daily', '0.8');
url_node($base . '/sss', $today, 'monthly', '0.6');
url_node($base . '/hakkimizda', $today, 'monthly', '0.5');
url_node($base . '/iletisim', $today, 'monthly', '0.5');
url_node($base . '/ofislerimiz', $today, 'weekly', '0.7');
url_node($base . '/uzun-donem', $today, 'monthly', '0.7');
url_node($base . '/bayilik', $today, 'monthly', '0.6');

// LOKASYON SAYFALARI (SEO hedefli)
$locations = db()->fetchAll("SELECT slug, updated_at FROM locations WHERE is_active = 1");
foreach ($locations as $l) {
    $lastmod = $l['updated_at'] ? substr($l['updated_at'], 0, 10) : $today;
    url_node($base . '/lokasyon/' . $l['slug'], $lastmod, 'weekly', '0.85');
}

// BLOG YAZILARI
$posts = db()->fetchAll("SELECT slug, updated_at FROM blog_posts WHERE status = 'published'");
foreach ($posts as $p) {
    $lastmod = $p['updated_at'] ? substr($p['updated_at'], 0, 10) : $today;
    url_node($base . '/blog/' . $p['slug'], $lastmod, 'monthly', '0.6');
}

// KAMPANYALAR (aktif)
$campaigns = db()->fetchAll(
    "SELECT slug, updated_at FROM campaigns
     WHERE status = 'active'
     AND (start_date IS NULL OR start_date <= CURDATE())
     AND (end_date IS NULL OR end_date >= CURDATE())"
);
foreach ($campaigns as $c) {
    $lastmod = $c['updated_at'] ? substr($c['updated_at'], 0, 10) : $today;
    url_node($base . '/kampanya/' . $c['slug'], $lastmod, 'weekly', '0.7');
}

// STATİK İÇERİK SAYFALARI (KVKK, Gizlilik vb.)
$pages = db()->fetchAll("SELECT slug, updated_at FROM pages WHERE status = 'published'");
foreach ($pages as $pg) {
    $lastmod = $pg['updated_at'] ? substr($pg['updated_at'], 0, 10) : $today;
    url_node($base . '/' . $pg['slug'], $lastmod, 'yearly', '0.4');
}

echo '</urlset>' . "\n";
