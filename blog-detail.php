<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/includes/bootstrap.php';

$slug = get('slug');
if (!$slug) {
    // URL /blog/slug şeklinde ise slug'ı URL'den al
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $parts = explode('/', trim(parse_url($uri, PHP_URL_PATH), '/'));
    $slug = end($parts);
}

$post = db()->fetch("
    SELECT p.*, bc.name_tr AS category_name, bc.slug AS category_slug, au.name AS author_name
    FROM blog_posts p
    LEFT JOIN blog_categories bc ON bc.id = p.category_id
    LEFT JOIN admin_users au ON au.id = p.author_id
    WHERE p.slug = ? AND p.status = 'published'
", [$slug]);

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Yazı Bulunamadı';
    include __DIR__ . '/includes/frontend/header.php';
    echo '<div style="text-align:center; padding:80px 20px;"><h1>Yazı Bulunamadı</h1><a href="' . url('blog') . '" class="btn-outline-brand">Bloga Dön</a></div>';
    include __DIR__ . '/includes/frontend/footer.php';
    exit;
}

// Görüntülenme sayısını artır
db()->query("UPDATE blog_posts SET view_count = view_count + 1 WHERE id = ?", [$post['id']]);

// İlgili yazılar
$related = db()->fetchAll("
    SELECT p.*, bc.name_tr AS category_name
    FROM blog_posts p
    LEFT JOIN blog_categories bc ON bc.id = p.category_id
    WHERE p.status = 'published' AND p.id != ? AND (p.category_id = ? OR p.is_featured = 1)
    ORDER BY p.published_at DESC LIMIT 3
", [$post['id'], $post['category_id']]);

$pageTitle = ($post['meta_title_tr'] ?: $post['title_tr']) . ' | Yolzz Blog';
$pageDescription = $post['meta_description_tr'] ?: str_excerpt($post['excerpt_tr'] ?: strip_tags($post['content_tr']), 160);
$pageKeywords = ($post['meta_keywords'] ?? '') ?: 'blog, araç kiralama, rent a car, ' . ($post['category_name'] ?? 'seyahat');
$pageOgType = 'article';
if (!empty($post['cover_image'])) {
    $pageOgImage = upload_url($post['cover_image']);
}
$currentPage = '/blog';

// Article Schema
$pageJsonLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $post['title_tr'],
    'description' => $pageDescription,
    'image' => !empty($post['cover_image']) ? upload_url($post['cover_image']) : url('assets/img/og-default.jpg'),
    'author' => [
        '@type' => 'Person',
        'name' => $post['author_name'] ?? 'Yolzz Editör'
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'Yolzz',
        'logo' => [
            '@type' => 'ImageObject',
            'url' => url('assets/img/logo-full.svg')
        ]
    ],
    'datePublished' => $post['published_at'] ?? $post['created_at'],
    'dateModified' => $post['updated_at'] ?? $post['created_at'],
    'mainEntityOfPage' => [
        '@type' => 'WebPage',
        '@id' => url('blog/' . $post['slug'])
    ],
    'inLanguage' => 'tr-TR'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

include __DIR__ . '/includes/frontend/header.php';
?>

<article class="blog-detail">
  <!-- Hero -->
  <div class="blog-detail-hero">
    <div class="container" style="max-width:900px; margin:0 auto; padding:40px 20px;">
      <div style="margin-bottom:20px;">
        <a href="<?= url('blog') ?>" style="color:var(--brand); text-decoration:none; font-size:13px;">← Bloga dön</a>
      </div>
      <?php if ($post['category_name']): ?>
        <span class="blc-cat" style="display:inline-block; margin-bottom:16px;"><?= e($post['category_name']) ?></span>
      <?php endif ?>
      <h1 style="font-size:36px; color:var(--ink); line-height:1.2; margin-bottom:16px;"><?= e($post['title_tr']) ?></h1>
      <div style="display:flex; gap:20px; color:var(--text-muted); font-size:13px;">
        <span>📅 <?= tr_date($post['published_at'] ?: $post['created_at']) ?></span>
        <span>⏱ <?= (int)$post['reading_time'] ?> dakika okuma</span>
        <?php if ($post['author_name']): ?><span>✍️ <?= e($post['author_name']) ?></span><?php endif ?>
      </div>
    </div>
  </div>

  <?php if ($post['cover_image']): ?>
  <div class="container" style="max-width:900px; margin:0 auto; padding:0 20px;">
    <img src="<?= upload_url($post['cover_image']) ?>" alt="<?= e($post['title_tr']) ?>" style="width:100%; border-radius:16px; margin-bottom:30px;" loading="lazy">
  </div>
  <?php endif ?>

  <div class="container" style="max-width:720px; margin:0 auto; padding:0 20px 60px;">
    <div class="blog-content">
      <?= $post['content_tr'] ?>
    </div>

    <!-- Paylaş -->
    <div class="share-box">
      <h4>Bu yazıyı paylaş</h4>
      <div class="share-buttons">
        <a href="https://twitter.com/intent/tweet?text=<?= urlencode($post['title_tr']) ?>&url=<?= urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>" target="_blank" class="share-btn" style="background:#1DA1F2;">Twitter</a>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>" target="_blank" class="share-btn" style="background:#1877F2;">Facebook</a>
        <a href="https://api.whatsapp.com/send?text=<?= urlencode($post['title_tr'] . ' ' . 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>" target="_blank" class="share-btn" style="background:#25D366;">WhatsApp</a>
      </div>
    </div>
  </div>

  <?php if (!empty($related)): ?>
  <section style="background:var(--bg-soft); padding:60px 0;">
    <div class="container" style="max-width:1200px; margin:0 auto; padding:0 20px;">
      <h3 style="text-align:center; margin-bottom:30px; color:var(--ink);">İlgili Yazılar</h3>
      <div class="blog-listing-grid">
        <?php foreach ($related as $r): ?>
          <a href="<?= url('blog/' . e($r['slug'])) ?>" class="blog-list-card">
            <?php if ($r['cover_image']): ?>
              <img src="<?= upload_url($r['cover_image']) ?>" alt="<?= e($r['title_tr']) ?>" loading="lazy">
            <?php else: ?>
              <div class="blc-placeholder">📝</div>
            <?php endif ?>
            <div class="blc-body">
              <span class="blc-cat"><?= e($r['category_name']) ?></span>
              <h3><?= e($r['title_tr']) ?></h3>
              <p><?= e(str_excerpt($r['excerpt_tr'] ?: $r['content_tr'], 100)) ?></p>
            </div>
          </a>
        <?php endforeach ?>
      </div>
    </div>
  </section>
  <?php endif ?>
</article>

<style>
.blog-detail-hero { background: linear-gradient(135deg, var(--brand-wash), var(--accent-wash)); }
.blog-content { font-size: 17px; line-height: 1.8; color: var(--text); }
.blog-content h2 { font-size: 26px; color: var(--ink); margin: 30px 0 16px; }
.blog-content h3 { font-size: 22px; color: var(--ink); margin: 24px 0 12px; }
.blog-content p { margin-bottom: 20px; }
.blog-content img { max-width: 100%; border-radius: 12px; margin: 20px 0; }
.blog-content ul, .blog-content ol { margin-bottom: 20px; padding-left: 24px; }
.blog-content li { margin-bottom: 8px; }
.blog-content a { color: var(--brand); text-decoration: underline; }
.blog-content blockquote { border-left: 4px solid var(--accent); padding: 12px 20px; margin: 20px 0; font-style: italic; background: var(--bg-soft); border-radius: 0 10px 10px 0; }

.share-box { margin-top: 40px; padding: 24px; background: var(--bg-soft); border-radius: 12px; text-align: center; }
.share-box h4 { margin-bottom: 12px; color: var(--ink); }
.share-buttons { display: flex; gap: 8px; justify-content: center; }
.share-btn { padding: 10px 20px; color: #fff; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; }

.blog-listing-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px,1fr)); gap: 20px; }
.blog-list-card { background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid var(--border); text-decoration: none; color: var(--ink); }
.blog-list-card img { width: 100%; height: 180px; object-fit: cover; }
.blc-placeholder { height: 180px; background: var(--bg-soft); display: flex; align-items: center; justify-content: center; font-size: 48px; }
.blc-body { padding: 16px; }
.blc-cat { display: inline-block; padding: 3px 10px; background: var(--brand-wash); color: var(--brand); border-radius: 6px; font-size: 11px; font-weight: 600; margin-bottom: 8px; }
.blc-body h3 { font-size: 16px; margin-bottom: 6px; }
.blc-body p { font-size: 13px; color: var(--text-muted); line-height: 1.5; }
</style>

<?php include __DIR__ . '/includes/frontend/footer.php'; ?>
