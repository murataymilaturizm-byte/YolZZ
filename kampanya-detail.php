<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/includes/bootstrap.php';

$slug = get('slug');
if (!$slug) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $parts = explode('/', trim(parse_url($uri, PHP_URL_PATH), '/'));
    $slug = end($parts);
}

$c = db()->fetch("SELECT * FROM campaigns WHERE slug = ? AND status = 'active'", [$slug]);

if (!$c) {
    http_response_code(404);
    $pageTitle = '404';
    include __DIR__ . '/includes/frontend/header.php';
    echo '<div style="text-align:center; padding:80px 20px;"><h1>Kampanya Bulunamadı</h1><a href="' . url('kampanyalar') . '" class="btn-outline-brand">Kampanyalara Dön</a></div>';
    include __DIR__ . '/includes/frontend/footer.php';
    exit;
}

$pageTitle = $c['title_tr'] . ' — Araç Kiralama Kampanyası | Yolzz';
$pageDescription = $c['subtitle_tr']
    ?? ($c['description_tr']
        ? str_excerpt(strip_tags($c['description_tr']), 160)
        : ($c['title_tr'] . ' kampanyası ile ucuz araç kiralama fırsatı. Hemen rezervasyon yapın, indirimli fiyatlarla yola çıkın.'));
$pageKeywords = 'araç kiralama kampanyası, ' . $c['title_tr'] . ', rent a car indirimi, ucuz araç kiralama';
$pageOgType = 'article';
if (!empty($c['cover_image'])) {
    $pageOgImage = upload_url($c['cover_image']);
}
$currentPage = '/kampanyalar';

// JSON-LD: Offer + BreadcrumbList
$offerData = [
    '@type' => 'Offer',
    'name' => $c['title_tr'],
    'description' => $pageDescription,
    'url' => url('kampanya/' . $c['slug']),
    'availability' => 'https://schema.org/InStock',
    'category' => 'Araç Kiralama Kampanyası'
];
if (!empty($c['start_date'])) $offerData['validFrom'] = $c['start_date'];
if (!empty($c['end_date']))   $offerData['validThrough'] = $c['end_date'];
if ($c['discount_type'] === 'percent' && !empty($c['discount_value'])) {
    $offerData['priceCurrency'] = 'TRY';
    $offerData['eligibleQuantity'] = ['@type' => 'QuantitativeValue'];
}

$pageJsonLd = json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => SITE_URL],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Kampanyalar', 'item' => url('kampanyalar')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $c['title_tr'], 'item' => url('kampanya/' . $c['slug'])]
            ]
        ],
        $offerData
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

include __DIR__ . '/includes/frontend/header.php';
?>

<section class="page-hero" style="background:linear-gradient(135deg, var(--brand), var(--accent)); color:#fff; padding:80px 0;">
  <div class="container" style="max-width:900px; margin:0 auto; padding:0 20px; text-align:center;">
    <div style="margin-bottom:16px;">
      <a href="<?= url('kampanyalar') ?>" style="color:rgba(255,255,255,0.9); text-decoration:none;">← Tüm Kampanyalar</a>
    </div>
    <?php if ($c['discount_type'] === 'percent' && $c['discount_value']): ?>
      <div style="display:inline-block; padding:10px 24px; background:rgba(255,255,255,0.2); border-radius:30px; margin-bottom:20px; font-weight:700;">
        %<?= (int)$c['discount_value'] ?> İNDİRİM
      </div>
    <?php endif ?>
    <h1 style="font-size:40px; margin-bottom:16px;"><?= e($c['title_tr']) ?></h1>
    <p style="font-size:18px; opacity:0.95;"><?= e($c['short_desc_tr']) ?></p>
  </div>
</section>

<?php if ($c['banner_image']): ?>
<div class="container" style="max-width:1200px; margin:0 auto; padding:40px 20px 0;">
  <img src="<?= upload_url($c['banner_image']) ?>" alt="<?= e($c['title_tr']) ?>" style="width:100%; border-radius:16px;" loading="lazy">
</div>
<?php endif ?>

<section style="padding:60px 0;">
  <div class="container" style="max-width:900px; margin:0 auto; padding:0 20px;">
    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:40px;" class="campaign-detail-grid">
      <div>
        <div class="blog-content">
          <?= $c['content_tr'] ?: '<p>' . nl2br(e($c['short_desc_tr'])) . '</p>' ?>
        </div>
      </div>
      <aside>
        <div class="campaign-info-box">
          <h3>Kampanya Detayları</h3>

          <?php if ($c['coupon_code']): ?>
          <div class="cib-row">
            <div class="cib-label">Kupon Kodu</div>
            <div class="coupon-code-big" onclick="navigator.clipboard.writeText(this.textContent); this.textContent='✓ Kopyalandı';">
              <?= e($c['coupon_code']) ?>
            </div>
          </div>
          <?php endif ?>

          <?php if ($c['discount_value']): ?>
          <div class="cib-row">
            <div class="cib-label">İndirim</div>
            <div class="cib-value">
              <?php if ($c['discount_type'] === 'percent'): ?>
                %<?= (int)$c['discount_value'] ?>
              <?php else: ?>
                <?= tl($c['discount_value']) ?>
              <?php endif ?>
            </div>
          </div>
          <?php endif ?>

          <?php if ($c['min_days'] > 1): ?>
          <div class="cib-row">
            <div class="cib-label">Min. Gün</div>
            <div class="cib-value"><?= $c['min_days'] ?> gün</div>
          </div>
          <?php endif ?>

          <?php if ($c['min_amount'] > 0): ?>
          <div class="cib-row">
            <div class="cib-label">Min. Tutar</div>
            <div class="cib-value"><?= tl($c['min_amount']) ?></div>
          </div>
          <?php endif ?>

          <?php if ($c['start_date']): ?>
          <div class="cib-row">
            <div class="cib-label">Başlangıç</div>
            <div class="cib-value"><?= tr_date($c['start_date']) ?></div>
          </div>
          <?php endif ?>

          <?php if ($c['end_date']): ?>
          <div class="cib-row">
            <div class="cib-label">Bitiş</div>
            <div class="cib-value"><?= tr_date($c['end_date']) ?></div>
          </div>
          <?php endif ?>

          <a href="<?= url('filo') ?>" class="btn-primary-lg" style="display:block; text-align:center; margin-top:20px; padding:14px; background:var(--accent); color:#fff; border-radius:10px; text-decoration:none; font-weight:700;">
            Hemen Rezervasyon Yap
          </a>
        </div>
      </aside>
    </div>
  </div>
</section>

<style>
.campaign-detail-grid { display: grid; }
@media (max-width: 768px) { .campaign-detail-grid { grid-template-columns: 1fr !important; } }
.blog-content { font-size: 16px; line-height: 1.8; color: var(--text); }
.blog-content h2 { font-size: 24px; color: var(--ink); margin: 24px 0 14px; }
.blog-content p { margin-bottom: 16px; }
.blog-content ul, .blog-content ol { padding-left: 24px; margin-bottom: 16px; }
.campaign-info-box { background: #fff; border: 1px solid var(--border); border-radius: 16px; padding: 24px; position: sticky; top: 90px; }
.campaign-info-box h3 { margin-bottom: 16px; color: var(--ink); }
.cib-row { padding: 12px 0; border-bottom: 1px solid var(--border-soft); }
.cib-row:last-child { border-bottom: 0; }
.cib-label { font-size: 11px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; letter-spacing: 0.05em; font-weight: 600; }
.cib-value { font-size: 15px; color: var(--ink); font-weight: 600; }
.coupon-code-big { font-family: monospace; font-size: 18px; font-weight: 800; color: var(--brand); background: var(--brand-wash); padding: 12px; border-radius: 10px; border: 2px dashed var(--brand); text-align: center; cursor: pointer; letter-spacing: 0.1em; }
</style>

<?php include __DIR__ . '/includes/frontend/footer.php'; ?>
