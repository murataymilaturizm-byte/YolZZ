<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/includes/bootstrap.php';

$slug = get('slug');
if (!$slug) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $parts = explode('/', trim(parse_url($uri, PHP_URL_PATH), '/'));
    $slug = end($parts);
}

$page = db()->fetch("SELECT * FROM pages WHERE slug = ? AND status = 'published'", [$slug]);

if (!$page) {
    http_response_code(404);
    $pageTitle = 'Sayfa Bulunamadı';
    include __DIR__ . '/includes/frontend/header.php';
    echo '<div style="text-align:center; padding:80px 20px;"><h1>404 - Sayfa Bulunamadı</h1><p>Aradığınız sayfa mevcut değil.</p><a href="' . url('/') . '" class="btn-outline-brand">Ana Sayfa</a></div>';
    include __DIR__ . '/includes/frontend/footer.php';
    exit;
}

$pageTitle = ($page['meta_title_tr'] ?: $page['title_tr']) . ' | Yolzz';
$pageDescription = $page['meta_description_tr']
    ?: str_excerpt(strip_tags($page['content_tr'] ?: $page['title_tr']), 160);
$pageKeywords = $page['title_tr'] . ', yolzz, araç kiralama';
$currentPage = '/' . $page['slug'];

// Yasal/gizlilik sayfalarında crawl et ama PageRank pompalamayı kısıtla
$legalSlugs = ['kvkk', 'gizlilik', 'kullanim-kosullari', 'cerez-politikasi', 'kiralama-sartlari'];
if (in_array($page['slug'], $legalSlugs, true)) {
    $pageRobots = 'index, nofollow';
}

// BreadcrumbList JSON-LD
$pageJsonLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => SITE_URL],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $page['title_tr'], 'item' => url($page['slug'])]
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

include __DIR__ . '/includes/frontend/header.php';
?>

<section class="page-hero" style="padding:60px 0;">
  <div class="container" style="max-width:900px; margin:0 auto; padding:0 20px; text-align:center;">
    <h1 style="font-size:36px; color:var(--ink);"><?= e($page['title_tr']) ?></h1>
  </div>
</section>

<section style="padding:40px 0 80px;">
  <div class="container" style="max-width:800px; margin:0 auto; padding:0 20px;">
    <div class="page-content">
      <?= $page['content_tr'] ?: '<p style="color:var(--text-muted); text-align:center; padding:40px 0;">İçerik henüz eklenmedi.</p>' ?>
    </div>
  </div>
</section>

<style>
.page-content { font-size: 16px; line-height: 1.8; color: var(--text); background: #fff; padding: 32px; border-radius: 16px; border: 1px solid var(--border); }
.page-content h2 { font-size: 26px; color: var(--ink); margin: 24px 0 14px; }
.page-content h3 { font-size: 22px; color: var(--ink); margin: 20px 0 12px; }
.page-content p, .page-content ul, .page-content ol { margin-bottom: 16px; }
.page-content ul, .page-content ol { padding-left: 24px; }
.page-content a { color: var(--brand); }
</style>

<?php include __DIR__ . '/includes/frontend/footer.php'; ?>
