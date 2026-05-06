<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Ofislerimiz — Türkiye Genelinde Teslim Noktaları | Yolzz';
$pageDescription = 'Yolzz\'ın Türkiye genelindeki ofis ve havalimanı teslim noktaları. İstanbul, Ankara, İzmir, Antalya ve daha fazlası. Size en yakın ofisi bulun.';
$pageKeywords = 'yolzz ofisleri, araç kiralama ofisi, havalimanı teslim, şube listesi, rent a car şubeleri';
$currentPage = '/ofislerimiz';

$cityFilter = (int)get('city');
$type = get('type');

$where = ["o.is_active = 1", "o.show_on_page = 1"];
$params = [];
if ($cityFilter) { $where[] = "o.city_id = :c"; $params['c'] = $cityFilter; }
if ($type === 'airport') { $where[] = "o.is_airport = 1"; }

$offices = db()->fetchAll(
    "SELECT o.*, c.name AS city_name FROM offices o
     JOIN cities c ON c.id = o.city_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY c.name, o.sort_order, o.name",
    $params
);

$cities = db()->fetchAll("SELECT DISTINCT c.* FROM cities c JOIN offices o ON o.city_id = c.id WHERE c.is_active = 1 AND o.is_active = 1 AND o.show_on_page = 1 ORDER BY c.name");

// Şehre göre grupla
$grouped = [];
foreach ($offices as $o) {
    $grouped[$o['city_name']][] = $o;
}

include __DIR__ . '/includes/frontend/header.php';
?>

<style>
.filter-btn { padding: 8px 16px; border: 1px solid var(--border); border-radius: 20px; text-decoration: none; color: var(--ink); font-size: 13px; background: #fff; }
.filter-btn.active, .filter-btn:hover { background: var(--brand); color: #fff; border-color: var(--brand); }
.offices-grid {
  display: grid !important;
  grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
  gap: 20px !important;
  width: 100%;
}
@media (max-width: 900px) {
  .offices-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
}
@media (max-width: 600px) {
  .offices-grid { grid-template-columns: minmax(0, 1fr) !important; }
}
.office-card {
  position: relative; background: #fff; border: 1px solid var(--border);
  border-radius: 12px; padding: 20px; min-width: 0; box-sizing: border-box;
}
.office-card:hover { border-color: var(--brand); box-shadow: var(--shadow); }
.office-tag { position: absolute; top: 12px; right: 12px; background: var(--accent); color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 10px; font-weight: 700; }
.office-card h3 { font-size: 16px; color: var(--ink); margin-bottom: 4px; margin-top: 8px; word-break: break-word; }
.oc-district { font-size: 12px; color: var(--text-muted); margin-bottom: 12px; }
.oc-meta { font-size: 12px; color: var(--text); margin-bottom: 6px; word-break: break-word; }
.oc-meta a { color: var(--brand); text-decoration: none; }
</style>

<section class="page-hero" style="padding:50px 0;">
  <div class="container" style="max-width:1200px; margin:0 auto; padding:0 20px; text-align:center;">
    <h1 style="font-size:36px; color:var(--ink); margin-bottom:10px;">📍 Ofislerimiz</h1>
    <p style="color:var(--text);"><?= count($offices) ?> ofis ile Türkiye'nin her yerinde yanınızdayız</p>
  </div>
</section>

<section style="padding:30px 0 80px;">
  <div class="container" style="max-width:1200px; margin:0 auto; padding:0 20px;">

    <!-- Filtre -->
    <div style="display:flex; gap:10px; margin-bottom:30px; flex-wrap:wrap; align-items:center;">
      <a href="<?= url('ofislerimiz') ?>" class="<?= !$cityFilter && !$type ? 'filter-btn active' : 'filter-btn' ?>">Tümü</a>
      <a href="<?= url('ofislerimiz?type=airport') ?>" class="<?= $type === 'airport' ? 'filter-btn active' : 'filter-btn' ?>">✈ Havalimanı</a>
      <select onchange="if(this.value) window.location.href='?city=' + this.value" style="padding:8px 14px; border:1px solid var(--border); border-radius:20px; margin-left:auto;">
        <option value="">Şehir Seçin</option>
        <?php foreach ($cities as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $cityFilter == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach ?>
      </select>
    </div>

    <?php if (empty($offices)): ?>
      <div class="no-results">
        <div class="no-results-icon">📍</div>
        <h3>Ofis bulunamadı</h3>
      </div>
    <?php else: ?>
      <?php foreach ($grouped as $city => $list): ?>
        <div style="margin-bottom:40px;">
          <h2 style="font-size:22px; color:var(--ink); margin-bottom:16px; padding-bottom:10px; border-bottom:2px solid var(--brand-wash);">
            📍 <?= e($city) ?> <span style="font-size:13px; color:var(--text-muted); font-weight:500;">(<?= count($list) ?> ofis)</span>
          </h2>
          <div class="offices-grid">
            <?php foreach ($list as $o): ?>
              <div class="office-card">
                <?php if (!empty($o['image'])): ?>
                  <div class="office-card-img">
                    <img src="<?= (strpos($o['image'], 'http') === 0) ? e($o['image']) : upload_url($o['image']) ?>" alt="<?= e($o['name']) ?>" loading="lazy">
                    <?php if ($o['is_airport']): ?>
                      <span class="office-tag">✈ HAVALİMANI</span>
                    <?php endif ?>
                    <?php if ($o['is_24h']): ?>
                      <span class="office-tag" style="background:var(--success); right:auto; left:12px;">24/7</span>
                    <?php endif ?>
                  </div>
                <?php else: ?>
                  <?php if ($o['is_airport']): ?>
                    <span class="office-tag">✈ HAVALİMANI</span>
                  <?php endif ?>
                  <?php if ($o['is_24h']): ?>
                    <span class="office-tag" style="background:var(--success); right:auto; left:12px;">24/7</span>
                  <?php endif ?>
                <?php endif ?>
                <div class="office-card-body" style="padding:<?= !empty($o['image']) ? '18px' : '22px' ?>;">
                  <h3><?= e($o['name']) ?></h3>
                  <?php if ($o['district']): ?>
                    <div class="oc-district"><?= e($o['district']) ?></div>
                  <?php endif ?>
                  <?php if ($o['address']): ?>
                    <div class="oc-meta">🏢 <?= e(str_excerpt($o['address'], 80)) ?></div>
                  <?php endif ?>
                  <?php if ($o['phone']): ?>
                    <div class="oc-meta">📞 <a href="tel:<?= e($o['phone']) ?>"><?= e($o['phone']) ?></a></div>
                  <?php endif ?>
                  <?php if ($o['working_hours']): ?>
                    <div class="oc-meta">🕐 <?= e($o['working_hours']) ?></div>
                  <?php endif ?>
                  <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border-soft); display:flex; gap:8px;">
                    <a href="<?= url('filo?pickup_city=' . $o['city_id']) ?>" style="flex:1; padding:8px; background:var(--brand); color:#fff; text-align:center; border-radius:8px; text-decoration:none; font-size:13px; font-weight:600;">Araç Kirala</a>
                    <?php if ($o['latitude'] && $o['longitude']): ?>
                      <a href="https://maps.google.com/?q=<?= $o['latitude'] ?>,<?= $o['longitude'] ?>" target="_blank" style="padding:8px 12px; border:1px solid var(--brand); color:var(--brand); border-radius:8px; text-decoration:none; font-size:13px;">🗺</a>
                    <?php endif ?>
                  </div>
                </div>
              </div>
            <?php endforeach ?>
          </div>
        </div>
      <?php endforeach ?>
    <?php endif ?>
  </div>
</section>

<?php include __DIR__ . '/includes/frontend/footer.php'; ?>
