<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Kampanyalar & Fırsatlar — Ucuz Araç Kiralama | Yolzz';
$pageDescription = 'Güncel araç kiralama kampanyaları, erken rezervasyon indirimleri, hafta sonu fırsatları ve özel teklifler. Tasarruf edin, rahat seyahat edin.';
$pageKeywords = 'araç kiralama kampanyası, rent a car indirimi, ucuz araç kiralama, erken rezervasyon, fırsat';
$currentPage = '/kampanyalar';

$campaigns = db()->fetchAll("
    SELECT * FROM campaigns
    WHERE status = 'active'
    AND (start_date IS NULL OR start_date <= CURDATE())
    AND (end_date IS NULL OR end_date >= CURDATE())
    ORDER BY is_featured DESC, created_at DESC
");

include __DIR__ . '/includes/frontend/header.php';
?>

<section class="page-hero" style="background:linear-gradient(135deg, var(--accent-wash), var(--brand-wash)); padding:60px 0;">
  <div class="container" style="max-width:1200px; margin:0 auto; padding:0 20px; text-align:center;">
    <h1 style="font-size:40px; color:var(--ink); margin-bottom:10px;">🎁 Kampanyalar & Fırsatlar</h1>
    <p style="color:var(--text); font-size:16px;">Size özel indirim ve avantajları keşfedin</p>
  </div>
</section>

<section style="padding:60px 0; background:var(--bg-soft);">
  <div class="container" style="max-width:1200px; margin:0 auto; padding:0 20px;">
    <?php if (empty($campaigns)): ?>
      <div class="no-results">
        <div class="no-results-icon">🎁</div>
        <h3>Henüz aktif kampanya yok</h3>
        <p>Yeni kampanyalar için takipte kalın</p>
      </div>
    <?php else: ?>
    <div class="camp-listing">
      <?php foreach ($campaigns as $c): ?>
        <a href="<?= url('kampanya/' . e($c['slug'])) ?>" class="camp-list-card">
          <div class="clc-img">
            <?php if ($c['cover_image']): ?>
              <img src="<?= upload_url($c['cover_image']) ?>" alt="<?= e($c['title_tr']) ?>" loading="lazy">
            <?php else: ?>
              <div style="height:100%; background:linear-gradient(135deg, var(--brand), var(--accent));"></div>
            <?php endif ?>
            <?php if ($c['discount_type'] === 'percent' && $c['discount_value']): ?>
              <span class="clc-badge">%<?= (int)$c['discount_value'] ?><br><small>İNDİRİM</small></span>
            <?php elseif ($c['discount_type'] === 'fixed' && $c['discount_value']): ?>
              <span class="clc-badge"><?= tl($c['discount_value'], false) ?>₺<br><small>İNDİRİM</small></span>
            <?php endif ?>
          </div>
          <div class="clc-body">
            <h3><?= e($c['title_tr']) ?></h3>
            <p><?= e($c['short_desc_tr']) ?></p>
            <div class="clc-meta">
              <?php if ($c['coupon_code']): ?>
                <span class="clc-code">KOD: <?= e($c['coupon_code']) ?></span>
              <?php endif ?>
              <?php if ($c['end_date']): ?>
                <span style="font-size:12px; color:var(--text-muted);">⏰ Son: <?= tr_date($c['end_date']) ?></span>
              <?php endif ?>
            </div>
          </div>
        </a>
      <?php endforeach ?>
    </div>
    <?php endif ?>
  </div>
</section>

<style>
.camp-listing { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px,1fr)); gap: 24px; }
.camp-list-card { background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid var(--border); text-decoration: none; color: var(--ink); transition: all 0.3s; }
.camp-list-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); border-color: var(--accent); }
.clc-img { position: relative; height: 200px; overflow: hidden; }
.clc-img img { width: 100%; height: 100%; object-fit: cover; }
.clc-badge { position: absolute; top: 16px; right: 16px; background: var(--accent); color: #fff; padding: 10px 16px; border-radius: 12px; font-weight: 800; font-size: 18px; text-align: center; box-shadow: var(--shadow-accent); }
.clc-badge small { font-size: 9px; font-weight: 600; opacity: 0.9; }
.clc-body { padding: 24px; }
.clc-body h3 { font-size: 20px; margin-bottom: 10px; color: var(--ink); }
.clc-body p { font-size: 14px; color: var(--text); line-height: 1.6; margin-bottom: 14px; }
.clc-meta { display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid var(--border-soft); flex-wrap: wrap; gap: 8px; }
.clc-code { font-family: monospace; background: var(--brand-wash); color: var(--brand); padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 12px; border: 1px dashed var(--brand); }
</style>

<?php include __DIR__ . '/includes/frontend/footer.php'; ?>
