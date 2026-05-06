<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Araç Kiralama Rehberi & Seyahat Blog | Yolzz';
$pageDescription = 'Araç kiralamaya dair ipuçları, seyahat rehberleri, sürüş önerileri ve güncel kampanyalar. Türkiye\'yi keşfetmek için pratik bilgiler.';
$pageKeywords = 'araç kiralama blog, rent a car rehberi, seyahat ipuçları, araba kiralama tavsiyeleri, türkiye rotaları';
$currentPage = '/blog';

$categorySlug = get('category');
$search = get('q');

$where = ["p.status = 'published'"];
$params = [];

if ($categorySlug) {
    $where[] = "bc.slug = :cs"; $params['cs'] = $categorySlug;
}
if ($search) {
    $where[] = "(p.title_tr LIKE :s OR p.content_tr LIKE :s2)";
    $params['s'] = "%$search%"; $params['s2'] = "%$search%";
}

$page = max(1, (int)get('page', 1));
$perPage = 9;
$offset = ($page - 1) * $perPage;

$total = (int)db()->fetchColumn(
    "SELECT COUNT(*) FROM blog_posts p LEFT JOIN blog_categories bc ON bc.id = p.category_id WHERE " . implode(' AND ', $where),
    $params
);

$posts = db()->fetchAll(
    "SELECT p.*, bc.name_tr AS category_name, bc.slug AS category_slug
     FROM blog_posts p
     LEFT JOIN blog_categories bc ON bc.id = p.category_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY p.is_featured DESC, p.published_at DESC, p.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

$categories = db()->fetchAll("SELECT bc.*, COUNT(p.id) AS post_count FROM blog_categories bc
    LEFT JOIN blog_posts p ON p.category_id = bc.id AND p.status = 'published'
    WHERE bc.is_active = 1
    GROUP BY bc.id ORDER BY bc.sort_order, bc.name_tr");

$totalPages = ceil($total / $perPage);

include __DIR__ . '/includes/frontend/header.php';
?>

<section class="page-hero">
  <div class="container" style="max-width:1200px; margin:0 auto; padding:40px 20px; text-align:center;">
    <h1 style="font-size:36px; color:var(--ink); margin-bottom:10px;">📝 Blog</h1>
    <p style="color:var(--text); font-size:16px;">Yolculuklarınız için ipuçları, rehberler ve daha fazlası</p>
  </div>
</section>

<section style="padding:40px 0 80px;">
  <div class="container" style="max-width:1200px; margin:0 auto; padding:0 20px;">

    <!-- Kategori filtreleri -->
    <div class="category-tabs">
      <a href="<?= url('blog') ?>" class="<?= !$categorySlug ? 'active' : '' ?>">Tümü</a>
      <?php foreach ($categories as $cat): ?>
        <a href="<?= url('blog?category=' . $cat['slug']) ?>" class="<?= $categorySlug === $cat['slug'] ? 'active' : '' ?>">
          <?= e($cat['name_tr']) ?> <span>(<?= $cat['post_count'] ?>)</span>
        </a>
      <?php endforeach ?>
    </div>

    <?php if (empty($posts)): ?>
      <div class="no-results">
        <div class="no-results-icon">📝</div>
        <h3>Yazı bulunamadı</h3>
        <p>Bu kategoride henüz yazı bulunmuyor.</p>
      </div>
    <?php else: ?>

    <div class="blog-listing-grid">
      <?php foreach ($posts as $p): ?>
        <a href="<?= url('blog/' . e($p['slug'])) ?>" class="blog-list-card">
          <?php if ($p['cover_image']): ?>
            <img src="<?= upload_url($p['cover_image']) ?>" alt="<?= e($p['title_tr']) ?>" loading="lazy">
          <?php else: ?>
            <div class="blc-placeholder">📝</div>
          <?php endif ?>
          <div class="blc-body">
            <?php if ($p['category_name']): ?>
              <span class="blc-cat"><?= e($p['category_name']) ?></span>
            <?php endif ?>
            <h3><?= e($p['title_tr']) ?></h3>
            <p><?= e(str_excerpt($p['excerpt_tr'] ?: $p['content_tr'], 120)) ?></p>
            <div class="blc-meta">
              <span>📅 <?= tr_date($p['published_at'] ?: $p['created_at']) ?></span>
              <span>⏱ <?= (int)$p['reading_time'] ?> dk</span>
            </div>
          </div>
        </a>
      <?php endforeach ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="listing-pagination">
      <?php $qs = $_GET; unset($qs['page']); $base = '?' . http_build_query($qs) . '&page='; ?>
      <?php for ($i=1; $i<=$totalPages; $i++): ?>
        <a href="<?= $base.$i ?>" class="<?= $i===$page?'active':'' ?>"><?= $i ?></a>
      <?php endfor ?>
    </div>
    <?php endif ?>

    <?php endif ?>
  </div>
</section>

<style>
.page-hero { background: linear-gradient(135deg, var(--brand-wash), var(--accent-wash)); }
.category-tabs { display: flex; gap: 8px; margin-bottom: 30px; flex-wrap: wrap; }
.category-tabs a { padding: 8px 16px; border: 1px solid var(--border); border-radius: 20px; text-decoration: none; color: var(--ink); font-size: 13px; font-weight: 500; background: #fff; transition: all 0.2s; }
.category-tabs a.active, .category-tabs a:hover { background: var(--brand); color: #fff; border-color: var(--brand); }
.category-tabs a span { opacity: 0.7; font-size: 11px; }

.blog-listing-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px,1fr)); gap: 24px; }
.blog-list-card { background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid var(--border); text-decoration: none; color: var(--ink); transition: all 0.3s; }
.blog-list-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
.blog-list-card img { width: 100%; height: 200px; object-fit: cover; }
.blc-placeholder { height: 200px; background: var(--bg-soft); display: flex; align-items: center; justify-content: center; font-size: 48px; }
.blc-body { padding: 20px; }
.blc-cat { display: inline-block; padding: 3px 10px; background: var(--brand-wash); color: var(--brand); border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; margin-bottom: 10px; }
.blc-body h3 { font-size: 18px; font-weight: 700; line-height: 1.3; margin-bottom: 8px; color: var(--ink); }
.blc-body p { font-size: 13px; color: var(--text); line-height: 1.6; margin-bottom: 12px; }
.blc-meta { display: flex; gap: 12px; font-size: 12px; color: var(--text-muted); }
</style>

<?php include __DIR__ . '/includes/frontend/footer.php'; ?>
