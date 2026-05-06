<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Araç Kiralama Lokasyonları — 81 İl ve Havalimanı | Yolzz';
$pageDescription = 'Türkiye\'nin her şehri ve havalimanında araç kiralama hizmeti. İstanbul, Ankara, İzmir, Antalya, Sabiha Gökçen, Adnan Menderes ve daha fazlası. En uygun fiyatlarla rent a car.';
$pageKeywords = 'araç kiralama lokasyonları, türkiye rent a car, şehir listesi, havalimanı araç kiralama, oto kiralama noktaları';
$currentPage = '/lokasyonlar';

// Filtre: tip
$filterType = get('tip', '');

// Lokasyonları grupla
$types = ['airport' => 'Havalimanları', 'city' => 'Şehirler', 'district' => 'Semtler', 'landmark' => 'Turistik Bölgeler'];
$grouped = [];
foreach ($types as $t => $label) {
    $where = ["is_active = 1", "type = :t"];
    $params = ['t' => $t];
    if (!$filterType || $filterType === $t) {
        $grouped[$t] = [
            'label' => $label,
            'items' => db()->fetchAll(
                "SELECT * FROM locations WHERE is_active = 1 AND type = :t ORDER BY sort_order, name_tr",
                ['t' => $t]
            )
        ];
    }
}

$totalCount = db()->fetchColumn("SELECT COUNT(*) FROM locations WHERE is_active = 1");

include __DIR__ . '/includes/frontend/header.php';
?>

<!-- Hero -->
<section style="background: linear-gradient(135deg, #0A1F33 0%, #1d71b8 100%); padding: 60px 20px; text-align: center; color: #fff;">
  <div style="max-width: 900px; margin: 0 auto;">
    <div style="display:inline-flex; align-items:center; gap:8px; padding:8px 18px; background:rgba(255,255,255,0.15); backdrop-filter:blur(8px); border:1px solid rgba(255,255,255,0.25); border-radius:100px; font-size:12px; font-weight:600; margin-bottom:20px;">
      📍 Türkiye Geneli <?= $totalCount ?>+ Lokasyon
    </div>
    <h1 style="font-size: 36px; font-weight: 800; margin-bottom: 16px; line-height: 1.2;">
      Tüm Lokasyonlar
    </h1>
    <p style="font-size: 16px; color: rgba(255,255,255,0.9); max-width: 620px; margin: 0 auto 24px; line-height: 1.6;">
      Türkiye'nin dört bir yanındaki havalimanı, şehir ve turistik bölgelerde
      uygun fiyatlı araç kiralama seçeneklerini keşfedin.
    </p>
  </div>
</section>

<!-- Filtre barı -->
<section style="background: #fff; padding: 20px; border-bottom: 1px solid #EDF1F7; position: sticky; top: 0; z-index: 50;">
  <div style="max-width: 1200px; margin: 0 auto; display: flex; gap: 8px; flex-wrap: wrap; justify-content: center;">
    <a href="<?= url('lokasyonlar') ?>" class="lf-chip <?= !$filterType ? 'active' : '' ?>">Tümü</a>
    <?php foreach ($types as $t => $label): ?>
      <a href="<?= url('lokasyonlar?tip=' . $t) ?>" class="lf-chip <?= $filterType === $t ? 'active' : '' ?>">
        <?= $t === 'airport' ? '✈' : ($t === 'city' ? '🏙' : ($t === 'district' ? '📍' : '🗺')) ?>
        <?= e($label) ?>
      </a>
    <?php endforeach ?>
  </div>
</section>

<!-- Lokasyonlar Grid -->
<section style="background: #F5F8FC; padding: 40px 20px; min-height: 500px;">
  <div style="max-width: 1200px; margin: 0 auto;">

    <?php foreach ($grouped as $type => $group): ?>
      <?php if (empty($group['items'])) continue ?>

      <div style="margin-bottom: 48px;">
        <h2 style="font-size: 24px; color: #0A1F33; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
          <span><?= $type === 'airport' ? '✈' : ($type === 'city' ? '🏙' : ($type === 'district' ? '📍' : '🗺')) ?></span>
          <?= e($group['label']) ?>
          <span style="font-size: 14px; color: #8A96A8; font-weight: 500;">(<?= count($group['items']) ?>)</span>
        </h2>

        <div class="loc-grid">
          <?php foreach ($group['items'] as $loc): ?>
            <a href="<?= url('lokasyon/' . e($loc['slug'])) ?>" class="loc-card">
              <div class="loc-card-img">
                <?php if (!empty($loc['image'])): ?>
                  <img src="<?= (strpos($loc['image'], 'http') === 0) ? e($loc['image']) : upload_url($loc['image']) ?>" alt="<?= e($loc['name_tr']) ?>" loading="lazy">
                <?php else: ?>
                  <div class="loc-card-img-placeholder">
                    <span style="font-size:64px; color:rgba(255,255,255,0.3);">
                      <?= $loc['type'] === 'airport' ? '✈' : ($loc['type'] === 'city' ? '🏙' : '📍') ?>
                    </span>
                  </div>
                <?php endif ?>
                <div class="loc-card-overlay"></div>

                <div class="loc-card-content">
                  <?php if (($loc['type'] === 'district' || $loc['type'] === 'airport') && !empty($loc['city_id'])):
                    $cityName = db()->fetchColumn("SELECT name FROM cities WHERE id = ?", [$loc['city_id']]);
                  ?>
                    <?php if ($cityName): ?>
                      <div class="loc-card-city"><?= e($cityName) ?></div>
                    <?php endif ?>
                  <?php endif ?>
                  <h3 class="loc-card-name"><?= e(lang_field_value($loc, 'name')) ?></h3>
                  <div class="loc-card-cta">
                    <span>Detaylar</span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                  </div>
                </div>
              </div>
            </a>
          <?php endforeach ?>
        </div>
      </div>
    <?php endforeach ?>

    <?php if (empty(array_filter($grouped, fn($g) => !empty($g['items'])))): ?>
      <div style="text-align: center; padding: 60px 20px; background: #fff; border-radius: 16px;">
        <div style="font-size: 48px; margin-bottom: 14px;">📍</div>
        <h3 style="color: #0A1F33; margin-bottom: 8px;">Lokasyon Bulunamadı</h3>
        <p style="color: #627388;">Bu kategoride henüz lokasyon eklenmemiş.</p>
      </div>
    <?php endif ?>

  </div>
</section>

<style>
.lf-chip {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 10px 18px;
  background: #F5F8FC; color: #3D5269;
  border: 1px solid #DCE4EE;
  border-radius: 100px;
  font-size: 13px; font-weight: 600;
  text-decoration: none;
  transition: all 0.2s;
}
.lf-chip:hover { background: #E8F1F9; border-color: #1d71b8; color: #1d71b8; }
.lf-chip.active {
  background: #1d71b8 !important; color: #fff !important;
  border-color: #1d71b8 !important;
  box-shadow: 0 4px 12px rgba(29,113,184,0.25);
}
</style>

<?php include __DIR__ . '/includes/frontend/footer.php'; ?>
