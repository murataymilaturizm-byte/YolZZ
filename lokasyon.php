<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/includes/bootstrap.php';

$slug = get('slug');
if (!$slug) { redirect(url('lokasyonlar')); }

$loc = db()->fetch("SELECT * FROM locations WHERE slug = ? AND is_active = 1", [$slug]);
if (!$loc) {
    http_response_code(404);
    $pageTitle = 'Lokasyon Bulunamadı';
    include __DIR__ . '/includes/frontend/header.php';
    echo '<div style="text-align:center; padding:100px 20px; min-height:400px;"><h1 style="font-size:48px; margin-bottom:10px;">404</h1><p>Aradığınız lokasyon bulunamadı.</p><a href="' . url('lokasyonlar') . '" style="color:#1d71b8;">← Tüm Lokasyonlara Dön</a></div>';
    include __DIR__ . '/includes/frontend/footer.php';
    exit;
}

// View count artır
db()->query("UPDATE locations SET view_count = view_count + 1 WHERE id = ?", [$loc['id']]);

// SEO meta
$name = lang_field_value($loc, 'name');
$pageTitle = $loc['meta_title_tr'] ?: ($name . ' Araç Kiralama 2026 — En Uygun Fiyatlar | Yolzz');
$pageDescription = $loc['meta_description_tr'] ?: ($name . ' bölgesinde günlük araç kiralama. Havalimanı/ofis teslim, 7/24 destek, güvenli online rezervasyon. Hemen kampanyaları görün.');
$pageKeywords = $name . ' araç kiralama, ' . $name . ' rent a car, ' . $name . ' oto kiralama, ' . strtolower($name) . ' kiralık araba';
$currentPage = '/lokasyon/' . $slug;
if (!empty($loc['image'])) {
    $pageOgImage = (strpos($loc['image'], 'http') === 0) ? $loc['image'] : upload_url($loc['image']);
}

// Schema.org Place + BreadcrumbList + FAQ extraction
$typeLabel = match($loc['type'] ?? 'city') {
    'airport' => 'Airport',
    'city' => 'City',
    'district' => 'AdministrativeArea',
    'landmark' => 'TouristAttraction',
    default => 'Place'
};

// İçerikten FAQ soru-cevaplarını çıkar (H3 + P paternini yakala)
$faqItems = [];
if (!empty($loc['content_tr'])) {
    // "Sık Sorulan Sorular" bölümünden sonraki H3 + P eşleşmelerini bul
    if (preg_match('/Sık Sorulan Sorular[^<]*<\/h2>(.*?)(?=<h2|$)/is', $loc['content_tr'], $faqSection)) {
        preg_match_all('/<h3[^>]*>(.+?)<\/h3>\s*<p[^>]*>(.+?)<\/p>/is', $faqSection[1], $faqMatches, PREG_SET_ORDER);
        foreach ($faqMatches as $faq) {
            $question = trim(strip_tags($faq[1]));
            $answer = trim(strip_tags($faq[2]));
            if ($question && $answer) {
                $faqItems[] = [
                    '@type' => 'Question',
                    'name' => $question,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $answer
                    ]
                ];
            }
        }
    }
}

$graph = [
    [
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => SITE_URL],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Lokasyonlar', 'item' => url('lokasyonlar')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $name, 'item' => url('lokasyon/' . $slug)]
        ]
    ],
    [
        '@type' => $typeLabel,
        'name' => $name,
        'description' => $pageDescription,
        'url' => url('lokasyon/' . $slug)
    ] + (($loc['latitude'] && $loc['longitude']) ? [
        'geo' => [
            '@type' => 'GeoCoordinates',
            'latitude' => (float)$loc['latitude'],
            'longitude' => (float)$loc['longitude']
        ]
    ] : []),
    [
        '@type' => 'Service',
        'serviceType' => 'Araç Kiralama',
        'provider' => [
            '@type' => 'Organization',
            'name' => 'Yolzz',
            'url' => SITE_URL
        ],
        'areaServed' => [
            '@type' => $typeLabel,
            'name' => $name
        ]
    ]
];

// FAQ schema varsa ekle
if (!empty($faqItems)) {
    $graph[] = [
        '@type' => 'FAQPage',
        'mainEntity' => $faqItems
    ];
}

$schemaData = [
    '@context' => 'https://schema.org',
    '@graph' => $graph
];
$pageJsonLd = json_encode($schemaData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$pageNoOrgSchema = true; // Zaten @graph'ta Organization benzeri var

// Bu lokasyondaki araçlar (şehre göre)
$vehicles = [];
if ($loc['city_id']) {
    $vehicles = db()->fetchAll("
        SELECT v.*, vb.name AS brand_name, vc.name_tr AS category_name,
               o.name AS office_name, c.name AS office_city
        FROM vehicles v
        LEFT JOIN vehicle_brands vb ON vb.id = v.brand_id
        LEFT JOIN vehicle_categories vc ON vc.id = v.category_id
        LEFT JOIN offices o ON o.id = v.office_id
        LEFT JOIN cities c ON c.id = o.city_id
        WHERE v.status = 'active' AND (o.city_id = :cid OR v.office_id IS NULL)
        ORDER BY v.is_featured DESC, v.sort_order
        LIMIT 6
    ", ['cid' => $loc['city_id']]);
}

// Aynı şehirdeki diğer lokasyonlar (sidebar için)
$nearbyLocations = [];
if ($loc['city_id']) {
    $nearbyLocations = db()->fetchAll("
        SELECT * FROM locations
        WHERE is_active = 1 AND city_id = ? AND id != ?
        ORDER BY sort_order LIMIT 6
    ", [$loc['city_id'], $loc['id']]);
}

// Arama formu için tüm ofisler (filo sayfasındaki dropdown ile aynı veri)
$allOfficesForForm = db()->fetchAll("SELECT o.id, o.name, o.is_airport, c.name AS city_name
    FROM offices o JOIN cities c ON c.id = o.city_id
    WHERE o.is_active = 1 AND o.show_in_search = 1
    ORDER BY o.is_airport DESC, c.name, o.name");
$allLocationsForForm = [];
foreach ($allOfficesForForm as $o) {
    $allLocationsForForm[] = [
        'value' => 'office:' . $o['id'],
        'label' => $o['city_name'] . ' - ' . $o['name'],
        'sub' => $o['is_airport'] ? 'Havalimanı' : 'Ofis',
        'icon' => $o['is_airport'] ? '✈' : '📍'
    ];
}

// Bu lokasyonun şehrinde bir ofis varsa, varsayılan alış olarak seç
$defaultPickupLabel = '';
$defaultPickupValue = '';
if ($loc['city_id']) {
    foreach ($allOfficesForForm as $o) {
        if (isset($loc['city_id']) && $o['city_name'] === (db()->fetchColumn("SELECT name FROM cities WHERE id = ?", [$loc['city_id']]) ?: '')) {
            // Havalimanı lokasyonuysa havalimanı ofisini tercih et
            if ($loc['type'] === 'airport' && $o['is_airport']) {
                $defaultPickupLabel = $o['city_name'] . ' - ' . $o['name'];
                $defaultPickupValue = 'office:' . $o['id'];
                break;
            }
            // Diğer tüm durumlarda ilk ofis
            if (!$defaultPickupValue) {
                $defaultPickupLabel = $o['city_name'] . ' - ' . $o['name'];
                $defaultPickupValue = 'office:' . $o['id'];
            }
        }
    }
}

include __DIR__ . '/includes/frontend/header.php';
?>

<!-- Hero görsel + başlık -->
<section class="loc-detail-hero">
  <?php if (!empty($loc['image'])): ?>
    <img src="<?= (strpos($loc['image'], 'http') === 0) ? e($loc['image']) : upload_url($loc['image']) ?>" alt="<?= e($name) ?>" class="loc-detail-hero-img" loading="lazy">
  <?php endif ?>
  <div class="loc-detail-hero-overlay"></div>

  <div class="loc-detail-hero-content">
    <nav style="margin-bottom: 20px;" class="loc-hero-nav">
      <a href="<?= url('lokasyonlar') ?>" style="color: rgba(255,255,255,0.85); font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
        ← Tüm Lokasyonlar
      </a>
    </nav>

    <h1 class="loc-detail-title loc-hero-title"><?= e($name) ?></h1>

    <?php if (!empty($loc['short_desc_tr'])): ?>
      <p class="loc-detail-desc loc-hero-desc"><?= e(lang_field_value($loc, 'short_desc')) ?></p>
    <?php endif ?>

    <!-- Arama Formu (her lokasyonda görünür — turistik bölgeler dahil) -->
    <form class="search-pro loc-hero-search-pro loc-hero-form" action="<?= url('filo') ?>" method="GET" id="searchForm">
        <div class="search-pro-header">
          <div class="sph-title">Aracınızı bulun</div>
          <label class="sph-toggle">
            <input type="checkbox" name="different_return" id="diffReturn" value="1">
            <span class="sph-toggle-slider"></span>
            <span class="sph-toggle-label">Farklı yerde iade et</span>
          </label>
        </div>

        <!-- Alış Lokasyonu (Searchable) -->
        <div class="sp-field sp-field-location">
          <label class="sp-label">📍 Alış Lokasyonu</label>
          <div class="searchable-dropdown" data-target="pickup_location">
            <input type="text" class="sd-input" placeholder="Şehir, havalimanı veya ofis ara..." autocomplete="off" id="pickupInput" value="<?= e($defaultPickupLabel) ?>">
            <input type="hidden" name="pickup_location" id="pickupHidden" value="<?= e($defaultPickupValue) ?>" required>
            <div class="sd-list" id="pickupList"></div>
          </div>
        </div>

        <!-- İade Lokasyonu (konditional, toggle ile açılır) -->
        <div class="sp-field sp-field-location" id="returnLocationField" style="display:none;">
          <label class="sp-label">📍 İade Lokasyonu</label>
          <div class="searchable-dropdown" data-target="return_location">
            <input type="text" class="sd-input" placeholder="İade yerini seçin..." autocomplete="off" id="returnInput">
            <input type="hidden" name="return_location" id="returnHidden">
            <div class="sd-list" id="returnList"></div>
          </div>
        </div>

        <!-- Tarihler - Alış Grubu -->
        <div class="sp-datetime-group">
          <label class="sp-label">📅 Alış Zamanı</label>
          <div class="sp-datetime-row">
            <div class="sp-date-wrap">
              <input type="text" class="sp-date-input sp-date-picker" id="pickupDateInput" data-role="pickup" readonly placeholder="Tarih seçin" value="<?= date('d.m.Y', strtotime('+1 day')) ?>" required>
              <input type="hidden" name="pickup_date" id="pickupDateHidden" value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
            </div>
            <select class="sp-time-input" name="pickup_time" required>
              <?php for ($h = 8; $h <= 22; $h++): ?>
                <option value="<?= sprintf('%02d:00', $h) ?>" <?= $h == 10 ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                <option value="<?= sprintf('%02d:30', $h) ?>"><?= sprintf('%02d:30', $h) ?></option>
              <?php endfor ?>
            </select>
          </div>
        </div>

        <!-- Tarihler - İade Grubu -->
        <div class="sp-datetime-group">
          <label class="sp-label">📅 İade Zamanı</label>
          <div class="sp-datetime-row">
            <div class="sp-date-wrap">
              <input type="text" class="sp-date-input sp-date-picker" id="returnDateInput" data-role="return" readonly placeholder="Tarih seçin" value="<?= date('d.m.Y', strtotime('+4 days')) ?>" required>
              <input type="hidden" name="return_date" id="returnDateHidden" value="<?= date('Y-m-d', strtotime('+4 days')) ?>">
            </div>
            <select class="sp-time-input" name="return_time" required>
              <?php for ($h = 8; $h <= 22; $h++): ?>
                <option value="<?= sprintf('%02d:00', $h) ?>" <?= $h == 10 ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                <option value="<?= sprintf('%02d:30', $h) ?>"><?= sprintf('%02d:30', $h) ?></option>
              <?php endfor ?>
            </select>
          </div>
        </div>

        <!-- RANGE DATE PICKER (popup) -->
        <div class="rdp-backdrop" id="rdpBackdrop"></div>
        <div class="rdp" id="rdpPopup" role="dialog" aria-modal="true" aria-label="Tarih Aralığı Seçin">
          <div class="rdp-header">
            <div class="rdp-title">
              <div class="rdp-title-main">Tarih Aralığı Seçin</div>
              <div class="rdp-title-sub" id="rdpSelInfo">Alış ve iade tarihlerini seçin</div>
            </div>
            <button type="button" class="rdp-close" aria-label="Kapat">✕</button>
          </div>
          <div class="rdp-nav rdp-nav-mobile-bar">
            <button type="button" class="rdp-nav-btn rdp-nav-btn-big" id="rdpPrev" aria-label="Önceki ay">
              <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <div class="rdp-current-month" id="rdpCurrentMonth"></div>
            <button type="button" class="rdp-nav-btn rdp-nav-btn-big" id="rdpNext" aria-label="Sonraki ay">
              <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
          </div>
          <div class="rdp-months" id="rdpMonths"></div>
          <div class="rdp-footer">
            <button type="button" class="rdp-clear" id="rdpClear">Temizle</button>
            <button type="button" class="rdp-apply" id="rdpApply">Tamam</button>
          </div>
        </div>

        <!-- TIME PICKER (saat seçici) -->
        <div class="tp-backdrop" id="tpBackdrop"></div>
        <div class="tp-popup" id="tpPopup" role="dialog" aria-modal="true" aria-label="Saat Seçin">
          <div class="tp-header">
            <div class="tp-title-wrap">
              <div class="tp-title-main" id="tpTitle">Aracınızı saat kaçta almak istiyorsunuz?</div>
            </div>
            <button type="button" class="tp-close" id="tpClose" aria-label="Kapat">✕</button>
          </div>
          <div class="tp-body">
            <div class="tp-grid" id="tpGrid"></div>
          </div>
        </div>

        <button type="submit" class="sp-submit">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          Araçları Göster
        </button>
      </form>
  </div>
</section>

<!-- İçerik -->
<section class="loc-detail-content-section" style="background: #F5F8FC; padding: 40px 20px;">
  <div style="max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1fr 320px; gap: 30px;" class="loc-detail-grid">

    <!-- Ana içerik -->
    <div>
      <?php if (!empty($loc['content_tr'])): ?>
        <div class="loc-detail-content">
          <?= $loc['content_tr'] /* HTML içerik, admin'den gelecek */ ?>
        </div>
      <?php endif ?>

      <!-- Bu lokasyondaki araçlar -->
      <?php if (!empty($vehicles)): ?>
        <div style="margin-top: 40px;">
          <h2 style="font-size: 22px; color: #0A1F33; margin-bottom: 20px;">
            <?= e($name) ?>'da Popüler Araçlar
          </h2>
          <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px;">
            <?php foreach ($vehicles as $v): ?>
              <a href="<?= url('checkout?vehicle=' . $v['id']) ?>" style="background: #fff; border-radius: 12px; padding: 16px; text-decoration: none; color: inherit; border: 1px solid #EDF1F7; transition: all 0.2s; display: block;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 30px rgba(10,31,51,0.08)';" onmouseout="this.style.transform=''; this.style.boxShadow='';">
                <div style="height: 100px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #F5F8FC, #E8EEF5); border-radius: 8px; margin-bottom: 12px;">
                  <?php if ($v['main_image']): ?>
                    <img src="<?= upload_url($v['main_image']) ?>" alt="<?= e($v['brand_name']) ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;" loading="lazy">
                  <?php else: ?>
                    <span style="font-size: 40px;">🚗</span>
                  <?php endif ?>
                </div>
                <div style="font-size: 10px; color: #8A96A8; font-weight: 600; text-transform: uppercase; margin-bottom: 4px;"><?= e($v['category_name']) ?></div>
                <div style="font-weight: 700; color: #0A1F33; margin-bottom: 8px;"><?= e($v['brand_name'] . ' ' . $v['model']) ?></div>
                <div style="font-size: 20px; color: #1d71b8; font-weight: 800;"><?= tl($v['daily_price']) ?><span style="font-size: 11px; color: #627388; font-weight: 500;">/gün</span></div>
              </a>
            <?php endforeach ?>
          </div>
          <div style="text-align: center; margin-top: 20px;">
            <a href="<?= url('filo?pickup_city=' . $loc['city_id']) ?>" class="btn-outline-brand">Tüm Araçları Gör →</a>
          </div>
        </div>
      <?php endif ?>

      <?php if (!empty($loc['nearby_info'])): ?>
        <div class="loc-detail-content" style="margin-top: 30px; background: #fff; padding: 24px; border-radius: 14px;">
          <h2 style="font-size: 20px; color: #0A1F33; margin-bottom: 14px;">Yakın Yerler ve İlgi Alanları</h2>
          <?= $loc['nearby_info'] ?>
        </div>
      <?php endif ?>
    </div>

    <!-- Sidebar -->
    <aside>
      <!-- Hızlı rezervasyon -->
      <div style="background: #fff; padding: 20px; border-radius: 14px; border: 1px solid #EDF1F7; margin-bottom: 20px; position: sticky; top: 20px;">
        <h3 style="font-size: 16px; color: #0A1F33; margin-bottom: 14px;">Hızlı Rezervasyon</h3>
        <p style="font-size: 13px; color: #627388; margin-bottom: 18px; line-height: 1.5;">
          <?= e($name) ?>'de anlık araç müsaitliği ve fiyatları görmek için aramayı başlatın.
        </p>
        <?php if ($loc['city_id']): ?>
          <a href="<?= url('filo?pickup_city=' . $loc['city_id']) ?>" style="display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 14px; background: #e94e1b; color: #fff; text-decoration: none; border-radius: 10px; font-weight: 700; box-shadow: 0 4px 12px rgba(233,78,27,0.25);">
            🔍 Araç Ara
          </a>
        <?php endif ?>
      </div>

      <!-- Yakın lokasyonlar -->
      <?php if (!empty($nearbyLocations)): ?>
        <div style="background: #fff; padding: 20px; border-radius: 14px; border: 1px solid #EDF1F7;">
          <h3 style="font-size: 16px; color: #0A1F33; margin-bottom: 14px;">Yakın Lokasyonlar</h3>
          <?php foreach ($nearbyLocations as $nearby): ?>
            <a href="<?= url('lokasyon/' . e($nearby['slug'])) ?>" style="display: flex; align-items: center; gap: 10px; padding: 10px; border-radius: 8px; text-decoration: none; color: inherit; transition: background 0.2s;" onmouseover="this.style.background='#F5F8FC'" onmouseout="this.style.background=''">
              <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #E8F1F9, #DCE4EE); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <?= $nearby['type'] === 'airport' ? '✈' : ($nearby['type'] === 'city' ? '🏙' : '📍') ?>
              </div>
              <div style="flex: 1; min-width: 0;">
                <div style="font-weight: 600; color: #0A1F33; font-size: 14px; margin-bottom: 2px;"><?= e(lang_field_value($nearby, 'name')) ?></div>
                <div style="font-size: 11px; color: #8A96A8; text-transform: uppercase; letter-spacing: 0.05em;">
                  <?= $nearby['type'] === 'airport' ? 'Havalimanı' : ($nearby['type'] === 'city' ? 'Şehir' : ($nearby['type'] === 'district' ? 'Semt' : 'Turistik')) ?>
                </div>
              </div>
            </a>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </aside>

  </div>
</section>

<style>
.loc-detail-hero {
  position: relative;
  min-height: 420px;
  /* overflow: hidden KALDIRILDI — dropdown aşağı doğru düzgün açılsın */
  background: linear-gradient(135deg, #0A1F33, #1d71b8);
}
.loc-detail-hero-img {
  position: absolute; inset: 0;
  width: 100%; height: 100%;
  object-fit: cover; object-position: center;
  overflow: hidden;
}
.loc-detail-hero-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(180deg, rgba(10,31,51,0.3) 0%, rgba(10,31,51,0.85) 100%);
  z-index: 1;
}
.loc-detail-hero-content {
  position: relative; z-index: 2;
  max-width: 1200px; margin: 0 auto;
  padding: 50px 20px;
  color: #fff;
  height: 100%;
  display: flex; flex-direction: column; justify-content: flex-end;
}
.loc-detail-tag {
  display: inline-block;
  padding: 6px 14px;
  background: #e94e1b;
  color: #fff;
  font-size: 12px; font-weight: 700;
  border-radius: 100px;
  margin-bottom: 14px;
  align-self: flex-start;
}
.loc-detail-title {
  font-size: 42px; font-weight: 800;
  letter-spacing: -0.02em;
  margin-bottom: 12px;
  text-shadow: 0 2px 20px rgba(0,0,0,0.3);
}
.loc-detail-desc {
  font-size: 16px;
  color: rgba(255,255,255,0.92);
  max-width: 680px;
  line-height: 1.6;
  margin-bottom: 20px;
}
.loc-detail-cta {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 14px 26px;
  background: #e94e1b;
  color: #fff;
  font-size: 15px; font-weight: 700;
  border-radius: 10px;
  text-decoration: none;
  box-shadow: 0 8px 24px rgba(233,78,27,0.4);
  align-self: flex-start;
  transition: transform 0.2s;
}
.loc-detail-cta:hover { transform: translateY(-2px); }

.loc-detail-content {
  background: #fff;
  padding: 28px 32px;
  border-radius: 14px;
  border: 1px solid #EDF1F7;
  color: #3D5269;
  line-height: 1.8;
}
.loc-detail-content h2, .loc-detail-content h3 {
  color: #0A1F33;
  margin-top: 20px; margin-bottom: 10px;
}
.loc-detail-content h2 { font-size: 22px; }
.loc-detail-content h3 { font-size: 17px; }
.loc-detail-content p { margin-bottom: 12px; }
.loc-detail-content ul, .loc-detail-content ol { margin: 12px 0 12px 24px; }
.loc-detail-content li { margin-bottom: 6px; }
.loc-detail-content strong { color: #0A1F33; }

@media (max-width: 991px) {
  .loc-detail-grid { grid-template-columns: 1fr !important; }
  .loc-detail-hero { height: auto; min-height: 360px; }
  .loc-detail-title { font-size: 30px; }
  .loc-detail-desc { font-size: 14px; }
  .loc-detail-hero-content { padding: 30px 20px; }
  .loc-detail-content { padding: 22px 20px; }
}

/* ===== MOBİLDE: Açıklama formdan SONRA gelsin ===== */
@media (max-width: 768px) {
  .loc-detail-hero-content {
    display: flex !important;
    flex-direction: column !important;
  }
  .loc-hero-nav    { order: 1; margin-bottom: 14px !important; }
  .loc-hero-title  { order: 2; margin-bottom: 14px !important; font-size: 22px !important; line-height: 1.25 !important; }
  .loc-hero-form   { order: 3; margin-top: 6px !important; margin-bottom: 18px !important; }
  .loc-hero-desc   { order: 4; margin-top: 0 !important; font-size: 13.5px !important; opacity: 0.92; line-height: 1.5; }
}

/* ========== HERO İÇİNDE ARAMA FORMU ========== */
.loc-hero-search-pro {
  margin-top: 20px;
  max-width: 100%;
  background: rgba(255, 255, 255, 0.98) !important;
}

/* Hero banner'da form geniş yayılsın */
.loc-detail-hero-content { max-width: 1200px; }

/* ÖNEMLİ: Dropdown hero section'dan taşıp beyaz içerik üstünde görünsün */
.loc-detail-hero { overflow: visible !important; position: relative; z-index: 10; }
.loc-detail-hero-content { overflow: visible; }
.loc-hero-search-pro { position: relative; z-index: 20; }
.loc-hero-search-pro .searchable-dropdown { position: relative; z-index: 30; }
.loc-hero-search-pro .sd-list { z-index: 9999 !important; }

/* Alttaki içerik section'ı dropdown'un üstünde görünmesin */
.loc-detail-content-section { position: relative; z-index: 1; }

/* ===== MASAÜSTÜ YATAY DÜZEN (>768px) ===== */
@media (min-width: 769px) {
  /* 4 kolon: Lokasyon geniş, datetime'lar datetime saatlerle birlikte geniş */
  .loc-hero-search-pro {
    display: grid !important;
    grid-template-columns: 1.3fr 1.2fr 1.2fr auto;
    grid-template-areas:
      "header header header header"
      "pickup datetime1 datetime2 submit";
    gap: 12px;
    align-items: end;
    padding: 16px 20px !important;
  }

  /* 5 kolon: Farklı iade açıkken */
  .loc-hero-search-pro.has-return {
    grid-template-columns: 1.1fr 1.1fr 1.1fr 1.1fr auto;
    grid-template-areas:
      "header header header header header"
      "pickup return datetime1 datetime2 submit";
  }

  .loc-hero-search-pro .search-pro-header {
    grid-area: header;
    margin-bottom: 0;
  }
  .loc-hero-search-pro #returnLocationField { grid-area: return; }
  .loc-hero-search-pro .sp-field-location:not(#returnLocationField) { grid-area: pickup; }
  .loc-hero-search-pro .sp-datetime-group:nth-of-type(1) { grid-area: datetime1; }
  .loc-hero-search-pro .sp-datetime-group:nth-of-type(2) { grid-area: datetime2; }
  .loc-hero-search-pro .sp-submit {
    grid-area: submit;
    white-space: nowrap;
    height: 52px;
    padding: 0 22px;
    align-self: end;
  }

  /* Grid item'lar taşmasın */
  .loc-hero-search-pro .sp-field,
  .loc-hero-search-pro .sp-datetime-group {
    min-width: 0;
  }

  /* DATETIME ROW: tarih + saat HER ZAMAN yan yana, alt satıra düşmesin */
  .loc-hero-search-pro .sp-datetime-row {
    display: grid !important;
    grid-template-columns: 1.4fr 1fr;
    gap: 6px;
    min-width: 0;
  }
  .loc-hero-search-pro .sp-date-wrap,
  .loc-hero-search-pro .sp-time-input {
    min-width: 0;
  }
  .loc-hero-search-pro .sp-date-input {
    width: 100%;
    min-width: 0;
    font-size: 13px;
    padding: 12px 10px;
  }
  .loc-hero-search-pro .sp-time-input {
    font-size: 13px;
    padding: 12px 6px;
    padding-right: 22px;
  }
}
</style>

<script>
// Farklı yerde iade açıldığında grid'i 5 kolona genişlet
document.addEventListener('DOMContentLoaded', function() {
  var diffToggle = document.getElementById('diffReturn');
  var form = document.querySelector('.loc-hero-search-pro');
  if (diffToggle && form) {
    if (diffToggle.checked) form.classList.add('has-return');
    diffToggle.addEventListener('change', function() {
      form.classList.toggle('has-return', diffToggle.checked);
    });
  }
});
</script>

<script>
// Searchable Dropdown — Alış/İade Lokasyonu
(function(){
  const locations = <?= json_encode($allLocationsForForm, JSON_UNESCAPED_UNICODE) ?>;

  function setupDropdown(inputId, hiddenId, listId) {
    const input = document.getElementById(inputId);
    const hidden = document.getElementById(hiddenId);
    const list = document.getElementById(listId);
    if (!input || !hidden || !list) return;

    // TÜRKÇE NORMALİZE — i/İ/ı/I ve diğer aksanları düzleştir
    function normalize(str) {
      if (!str) return '';
      return str
        .replace(/İ/g, 'i').replace(/I/g, 'i').replace(/ı/g, 'i')
        .replace(/Ş/g, 's').replace(/ş/g, 's')
        .replace(/Ğ/g, 'g').replace(/ğ/g, 'g')
        .replace(/Ü/g, 'u').replace(/ü/g, 'u')
        .replace(/Ö/g, 'o').replace(/ö/g, 'o')
        .replace(/Ç/g, 'c').replace(/ç/g, 'c')
        .toLowerCase()
        .trim();
    }

    function render(query) {
      const q = normalize(query);

      if (!q) {
        // Boş arama: Havalimanları + Şehir Ofisleri (şehir filtresi yok)
        const airports = locations.filter(l => l.sub === 'Havalimanı');
        const offices = locations.filter(l => l.sub === 'Ofis');

        let html = '';
        if (airports.length) {
          html += '<div class="sd-group-header">✈ HAVALİMANLARI</div>';
          html += airports.map(l => itemHtml(l)).join('');
        }
        if (offices.length) {
          html += '<div class="sd-group-header">📍 ŞEHİR OFİSLERİ</div>';
          html += offices.map(l => itemHtml(l)).join('');
        }
        list.innerHTML = html || '<div class="sd-empty">Henüz ofis tanımlı değil</div>';
      } else {
        // Arama: başlıkta eşleşenler + içinde eşleşenler sırayla (Türkçe normalize ile)
        const starts = locations.filter(l => normalize(l.label).startsWith(q));
        const contains = locations.filter(l =>
          !normalize(l.label).startsWith(q) &&
          (normalize(l.label).includes(q) || normalize(l.sub).includes(q))
        );
        const combined = [...starts, ...contains].slice(0, 30);

        if (combined.length === 0) {
          list.innerHTML = '<div class="sd-empty">Sonuç bulunamadı</div>';
        } else {
          list.innerHTML = combined.map(l => itemHtml(l)).join('');
        }
      }

      function itemHtml(l) {
        const matchedLabel = q ? highlightMatch(l.label, query) : l.label;
        return `<div class="sd-item" data-value="${l.value}" data-label="${l.label}">
          <span class="sd-icon">${l.icon}</span>
          <div class="sd-text">
            <div class="sd-label">${matchedLabel}</div>
            <div class="sd-sub">${l.sub}</div>
          </div>
        </div>`;
      }

      function highlightMatch(text, queryRaw) {
        const queryNorm = normalize(queryRaw);
        const textNorm = normalize(text);
        const idx = textNorm.indexOf(queryNorm);
        if (idx === -1) return text;
        // Orijinal stringde aynı pozisyonu vurgula (Türkçe karakter uzunluğu aynı)
        return text.substring(0, idx) +
               '<strong style="color:#e94e1b;">' + text.substring(idx, idx + queryNorm.length) + '</strong>' +
               text.substring(idx + queryNorm.length);
      }

      list.querySelectorAll('.sd-item').forEach(item => {
        item.addEventListener('mousedown', (e) => {
          e.preventDefault();
          input.value = item.dataset.label;
          hidden.value = item.dataset.value;
          list.classList.remove('open');
          // MOBİL: klavyeyi kapat
          input.blur();
          if (document.activeElement && document.activeElement !== document.body) {
            document.activeElement.blur();
          }
        });
      });
    }

    // Input'a girilince mobilde input-mode text yerine klavye kontrolü
    input.setAttribute('inputmode', 'search');

    // Focus davranışı: mevcut değer varsa tümünü seç (kullanıcı yeni yazarsa silinir)
    let originalValue = input.value;
    let originalHidden = hidden.value;

    input.addEventListener('focus', () => {
      originalValue = input.value;
      originalHidden = hidden.value;
      render(input.value);
      list.classList.add('open');
      setTimeout(() => {
        if (input === document.activeElement && input.value) {
          input.select();
        }
      }, 50);
    });
    input.addEventListener('input', () => {
      render(input.value);
      list.classList.add('open');
      hidden.value = ''; // input değişirse seçim sıfırlansın
    });
    input.addEventListener('blur', () => {
      setTimeout(() => {
        list.classList.remove('open');
        // Eğer yeni seçim yapılmadıysa eski değere geri dön
        if (!hidden.value && originalHidden) {
          input.value = originalValue;
          hidden.value = originalHidden;
        }
      }, 150);
    });
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        input.value = originalValue;
        hidden.value = originalHidden;
        list.classList.remove('open');
        input.blur();
      }
    });
  }

  setupDropdown('pickupInput', 'pickupHidden', 'pickupList');
  setupDropdown('returnInput', 'returnHidden', 'returnList');

  // Farklı yerde iade toggle
  const diffReturn = document.getElementById('diffReturn');
  const returnField = document.getElementById('returnLocationField');
  if (diffReturn && returnField) {
    diffReturn.addEventListener('change', () => {
      returnField.style.display = diffReturn.checked ? 'block' : 'none';
      const returnHidden = document.getElementById('returnHidden');
      if (returnHidden) returnHidden.required = diffReturn.checked;
    });
  }

  // Form submit: hidden lokasyon boşsa uyar + loader göster
  document.getElementById('searchForm')?.addEventListener('submit', (e) => {
    const pickupHidden = document.getElementById('pickupHidden');
    if (!pickupHidden.value) {
      e.preventDefault();
      alert('Lütfen alış lokasyonu seçin.');
      document.getElementById('pickupInput').focus();
      return;
    }
    // Loader'ı göster
    const loader = document.getElementById('homeSearchLoader');
    if (loader) {
      loader.classList.add('active');
      const s1 = document.getElementById('hslStep1');
      const s2 = document.getElementById('hslStep2');
      const s3 = document.getElementById('hslStep3');
      if (s1 && s2 && s3) {
        setTimeout(() => { s1.classList.add('done'); s1.classList.remove('active'); s2.classList.add('active'); }, 700);
        setTimeout(() => { s2.classList.add('done'); s2.classList.remove('active'); s3.classList.add('active'); }, 1400);
      }
    }
  });
})();

// =================== RANGE DATE PICKER ===================
(function(){
  const pickupInput = document.getElementById('pickupDateInput');
  const pickupHidden = document.getElementById('pickupDateHidden');
  const returnInput = document.getElementById('returnDateInput');
  const returnHidden = document.getElementById('returnDateHidden');
  const popup = document.getElementById('rdpPopup');
  const backdrop = document.getElementById('rdpBackdrop');
  const monthsWrap = document.getElementById('rdpMonths');
  const prevBtn = document.getElementById('rdpPrev');
  const nextBtn = document.getElementById('rdpNext');
  const applyBtn = document.getElementById('rdpApply');
  const clearBtn = document.getElementById('rdpClear');
  const closeBtn = popup.querySelector('.rdp-close');
  const selInfo = document.getElementById('rdpSelInfo');

  if (!pickupInput || !popup) return;

  const MONTH_NAMES = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
  const DAY_NAMES = ['Pzt','Sal','Çar','Per','Cum','Cmt','Paz'];

  let startDate = parseDMY(pickupInput.value);
  let endDate = parseDMY(returnInput.value);
  // Saatleri sıfırla (karşılaştırma için) — önemli
  if (startDate) startDate.setHours(0,0,0,0);
  if (endDate) endDate.setHours(0,0,0,0);
  let selecting = 'start'; // 'start' | 'end'
  let viewYear = startDate ? startDate.getFullYear() : new Date().getFullYear();
  let viewMonth = startDate ? startDate.getMonth() : new Date().getMonth();

  function parseDMY(str) {
    if (!str) return null;
    const parts = str.split('.');
    if (parts.length !== 3) return null;
    const d = new Date(parseInt(parts[2]), parseInt(parts[1]) - 1, parseInt(parts[0]));
    d.setHours(0,0,0,0);
    return d;
  }
  function formatDMY(d) {
    if (!d) return '';
    return String(d.getDate()).padStart(2,'0') + '.' + String(d.getMonth()+1).padStart(2,'0') + '.' + d.getFullYear();
  }
  function formatYMD(d) {
    if (!d) return '';
    return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
  }
  function sameDay(a, b) {
    return a && b && a.getFullYear() === b.getFullYear()
           && a.getMonth() === b.getMonth()
           && a.getDate() === b.getDate();
  }
  function stripTime(d) { const x = new Date(d); x.setHours(0,0,0,0); return x; }

  function openPopup(startingWith) {
    selecting = startingWith === 'return' ? 'end' : 'start';
    if (selecting === 'start') {
      selInfo.textContent = 'Alış tarihini seçin';
    } else {
      selInfo.textContent = 'İade tarihini seçin';
    }
    if (startDate) { viewYear = startDate.getFullYear(); viewMonth = startDate.getMonth(); }
    render();
    popup.classList.add('open');
    backdrop.classList.add('open');
    document.body.classList.add('rdp-lock');
  }

  function closePopup() {
    popup.classList.remove('open');
    backdrop.classList.remove('open');
    document.body.classList.remove('rdp-lock');
  }

  function applyRange() {
    if (startDate) {
      pickupInput.value = formatDMY(startDate);
      pickupHidden.value = formatYMD(startDate);
    }
    if (endDate) {
      returnInput.value = formatDMY(endDate);
      returnHidden.value = formatYMD(endDate);
    }
    closePopup();
    // Tarih kapandıktan hemen sonra alış saatini sor (her ekranda)
    if (startDate) {
      setTimeout(() => { if (window.openTimePicker) window.openTimePicker('pickup'); }, 200);
    }
  }

  function renderMonth(year, month) {
    const first = new Date(year, month, 1);
    const last = new Date(year, month + 1, 0);
    let startDay = first.getDay();
    startDay = startDay === 0 ? 6 : startDay - 1; // Pazartesi başlangıç

    let html = '<div class="rdp-month">';
    html += '<div class="rdp-month-title">' + MONTH_NAMES[month] + ' ' + year + '</div>';
    html += '<div class="rdp-weekdays">';
    DAY_NAMES.forEach(d => { html += '<span>' + d + '</span>'; });
    html += '</div>';
    html += '<div class="rdp-days">';
    for (let i = 0; i < startDay; i++) html += '<span class="rdp-day rdp-empty"></span>';

    const today = stripTime(new Date());
    for (let d = 1; d <= last.getDate(); d++) {
      const date = new Date(year, month, d);
      const isPast = date < today;
      const isToday = sameDay(date, today);
      const isStart = sameDay(date, startDate);
      const isEnd = sameDay(date, endDate);
      const isInRange = startDate && endDate && date > startDate && date < endDate;
      const classes = ['rdp-day'];
      if (isPast) classes.push('rdp-past');
      if (isToday) classes.push('rdp-today');
      if (isStart) classes.push('rdp-start');
      if (isEnd) classes.push('rdp-end');
      if (isInRange) classes.push('rdp-in-range');
      html += '<span class="' + classes.join(' ') + '" data-date="' + formatYMD(date) + '">' + d + '</span>';
    }
    html += '</div></div>';
    return html;
  }

  function render() {
    const nextMonth = viewMonth + 1 > 11 ? 0 : viewMonth + 1;
    const nextYear = viewMonth + 1 > 11 ? viewYear + 1 : viewYear;
    const isMobile = window.innerWidth < 720;
    if (isMobile) {
      monthsWrap.innerHTML = renderMonth(viewYear, viewMonth);
    } else {
      monthsWrap.innerHTML = renderMonth(viewYear, viewMonth) + renderMonth(nextYear, nextMonth);
    }
    const cmEl = document.getElementById('rdpCurrentMonth');
    if (cmEl) {
      if (isMobile) {
        cmEl.textContent = MONTH_NAMES[viewMonth] + ' ' + viewYear;
      } else {
        cmEl.textContent = MONTH_NAMES[viewMonth] + ' ' + viewYear + ' — ' + MONTH_NAMES[nextMonth] + ' ' + nextYear;
      }
    }
    monthsWrap.querySelectorAll('.rdp-day:not(.rdp-past):not(.rdp-empty)').forEach(el => {
      el.addEventListener('click', () => {
        const d = new Date(el.dataset.date);
        onDateClick(d);
      });
    });
  }

  function onDateClick(date) {
    // Saat karşılaştırma sorunlarını önlemek için saati sıfırla
    date = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    if (selecting === 'start') {
      startDate = date;
      endDate = null;
      selecting = 'end';
      selInfo.textContent = 'İade tarihini seçin';
    } else {
      if (date < startDate) {
        // Eğer seçili tarih başlangıçtan önceyse yeniden başla
        startDate = date;
        endDate = null;
        selInfo.textContent = 'İade tarihini seçin';
      } else {
        endDate = date;
        selInfo.textContent = formatDMY(startDate) + ' → ' + formatDMY(endDate);
        // Otomatik uygula (kullanıcı deneyimi)
        setTimeout(() => applyRange(), 250);
      }
    }
    render();
  }

  prevBtn.addEventListener('click', () => {
    if (viewMonth === 0) { viewMonth = 11; viewYear--; } else { viewMonth--; }
    render();
  });
  nextBtn.addEventListener('click', () => {
    if (viewMonth === 11) { viewMonth = 0; viewYear++; } else { viewMonth++; }
    render();
  });
  applyBtn.addEventListener('click', applyRange);
  closeBtn.addEventListener('click', closePopup);
  backdrop.addEventListener('click', closePopup);
  clearBtn.addEventListener('click', () => {
    startDate = null; endDate = null;
    selInfo.textContent = 'Alış tarihini seçin';
    selecting = 'start';
    render();
  });

  pickupInput.addEventListener('click', () => openPopup('pickup'));
  returnInput.addEventListener('click', () => openPopup('return'));

  // ESC kapatma
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && popup.classList.contains('open')) closePopup();
  });

  // Ekran boyutu değişirse yeniden çiz
  window.addEventListener('resize', () => {
    if (popup.classList.contains('open')) render();
  });
})();
</script>

<!-- ========== TIME PICKER (saat seçici) ========== -->
<script>
(function() {
  const tpBackdrop = document.getElementById('tpBackdrop');
  const tpPopup = document.getElementById('tpPopup');
  if (!tpPopup) return;
  const tpTitle = document.getElementById('tpTitle');
  const tpClose = document.getElementById('tpClose');
  const grid = document.getElementById('tpGrid');
  const pickupTimeSelect = document.querySelector('select[name="pickup_time"]');
  const returnTimeSelect = document.querySelector('select[name="return_time"]');
  if (!pickupTimeSelect) return;

  // ÖNEMLI: tp-popup ve tp-backdrop'u body'e taşı — aksi halde
  // ata elemanlarda backdrop-filter / overflow:hidden olduğu için
  // position:fixed viewport'a göre değil, parent'a göre çalışır
  if (tpBackdrop && tpBackdrop.parentNode !== document.body) {
    document.body.appendChild(tpBackdrop);
  }
  if (tpPopup && tpPopup.parentNode !== document.body) {
    document.body.appendChild(tpPopup);
  }

  let currentRole = 'pickup';

  function buildSlots() {
    const make = (h, m) => {
      const t = String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0');
      return '<button type="button" class="tp-slot" data-time="' + t + '">' + t + '</button>';
    };
    let html = '';
    for (let h = 6; h <= 23; h++) { html += make(h, 0); html += make(h, 30); }
    grid.innerHTML = html;
  }
  buildSlots();

  function highlightSelected() {
    const sel = currentRole === 'pickup' ? pickupTimeSelect.value : returnTimeSelect.value;
    tpPopup.querySelectorAll('.tp-slot').forEach(b => {
      if (b.dataset.time === sel) b.classList.add('active');
      else b.classList.remove('active');
    });
  }

  window.openTimePicker = function(role) {
    currentRole = role;
    if (role === 'pickup') {
      tpTitle.textContent = 'Aracınızı saat kaçta almak istiyorsunuz?';
    } else {
      tpTitle.textContent = 'Aracı saat kaçta teslim edeceksiniz?';
    }
    highlightSelected();
    if (window.innerWidth <= 720) {
      const scrollY = window.scrollY;
      document.body.dataset.tpScrollY = String(scrollY);
      document.body.style.top = '-' + scrollY + 'px';
    }
    tpBackdrop.classList.add('open');
    tpPopup.classList.add('open');
    document.body.classList.add('rdp-lock');
    setTimeout(() => {
      const active = tpPopup.querySelector('.tp-slot.active');
      if (active) {
        const tpBody = tpPopup.querySelector('.tp-body');
        if (tpBody) tpBody.scrollTop = Math.max(0, active.offsetTop - 100);
      }
    }, 50);
  };

  function closeTp() {
    tpBackdrop.classList.remove('open');
    tpPopup.classList.remove('open');
    document.body.classList.remove('rdp-lock');
    if (window.innerWidth <= 720 && document.body.dataset.tpScrollY !== undefined) {
      const y = parseInt(document.body.dataset.tpScrollY || '0', 10);
      document.body.style.top = '';
      delete document.body.dataset.tpScrollY;
      window.scrollTo(0, y);
    }
  }

  tpClose.addEventListener('click', closeTp);
  tpBackdrop.addEventListener('click', closeTp);

  tpPopup.addEventListener('click', (e) => {
    const slot = e.target.closest('.tp-slot');
    if (!slot) return;
    const t = slot.dataset.time;
    if (currentRole === 'pickup') {
      let opt = pickupTimeSelect.querySelector('option[value="' + t + '"]');
      if (opt) { pickupTimeSelect.value = t; }
      closeTp();
      // İade saatini de sor (her ekranda)
      setTimeout(() => window.openTimePicker('return'), 250);
    } else {
      let opt = returnTimeSelect.querySelector('option[value="' + t + '"]');
      if (opt) { returnTimeSelect.value = t; }
      closeTp();
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && tpPopup.classList.contains('open')) closeTp();
  });

  function openOnSelectClick(selectEl, role) {
    if (!selectEl) return;
    selectEl.addEventListener('mousedown', (e) => {
      e.preventDefault();
      selectEl.blur();
      window.openTimePicker(role);
    });
    selectEl.addEventListener('focus', (e) => {
      if (e.relatedTarget !== null) {
        window.openTimePicker(role);
      }
    });
  }
  openOnSelectClick(pickupTimeSelect, 'pickup');
  openOnSelectClick(returnTimeSelect, 'return');
})();
</script>

<style>
/* === Tarih picker mobil ay nav iyileştirme === */
.rdp-nav-mobile-bar {
  display: flex !important;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px !important;
  gap: 12px;
  background: linear-gradient(135deg, #F5F8FC, #fff);
  border-bottom: 1px solid #EDF1F7;
}
.rdp-nav-btn-big {
  width: 48px !important; height: 48px !important;
  background: #fff !important;
  border: 1.5px solid #DCE4EE !important;
  border-radius: 12px !important;
  color: #1d71b8 !important;
  cursor: pointer;
  display: flex !important; align-items: center; justify-content: center;
  transition: all 0.2s;
  box-shadow: 0 2px 6px rgba(10,31,51,0.06);
  flex-shrink: 0;
}
.rdp-nav-btn-big:hover {
  background: #1d71b8 !important; color: #fff !important;
  border-color: #1d71b8 !important; transform: scale(1.05);
}
.rdp-nav-btn-big:active { transform: scale(0.97); }
.rdp-current-month {
  flex: 1; text-align: center;
  font-size: 16px; font-weight: 800;
  color: #0A1F33; letter-spacing: -0.01em;
  user-select: none;
}
@media (max-width: 720px) {
  .rdp-current-month { font-size: 17px; }
  .rdp-nav-btn-big { width: 52px !important; height: 52px !important; }
  .rdp-day { font-size: 15px !important; min-height: 44px; }
}

/* === Time picker (saat seçici) === */
.tp-backdrop {
  display: none; position: fixed; inset: 0;
  background: rgba(10,31,51,0.6);
  backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
  z-index: 10001;
}
.tp-backdrop.open { display: block; }
.tp-popup {
  display: none;
  position: fixed; z-index: 10002;
  background: #fff;
  flex-direction: column; overflow: hidden;
  top: 50%; left: 50%; transform: translate(-50%, -50%);
  width: 560px; max-width: calc(100vw - 32px); max-height: 85vh;
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.tp-popup.open { display: flex; }
.tp-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 18px 22px;
  border-bottom: 1px solid #EDF1F7;
  background: linear-gradient(135deg, #F5F8FC, #fff);
  gap: 14px;
}
.tp-title-main {
  font-size: 17px; font-weight: 800; color: #0A1F33;
  letter-spacing: -0.01em; line-height: 1.3;
}
.tp-close {
  width: 36px; height: 36px; border-radius: 10px;
  background: #fff; border: 1.5px solid #DCE4EE;
  cursor: pointer; font-size: 16px; color: #627388;
  display: flex; align-items: center; justify-content: center;
  transition: all 0.2s; flex-shrink: 0;
}
.tp-close:hover { background: #e94e1b; color: #fff; border-color: #e94e1b; transform: rotate(90deg); }
.tp-body { padding: 18px; overflow-y: auto; flex: 1; }
.tp-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 8px;
}
.tp-slot {
  padding: 12px 4px;
  background: #F5F8FC;
  border: 1.5px solid #DCE4EE;
  border-radius: 10px;
  font-size: 14px; font-weight: 700; color: #0A1F33;
  cursor: pointer; font-family: inherit;
  transition: all 0.15s; min-height: 46px;
  text-align: center; white-space: nowrap;
}
.tp-slot:hover { background: #E8F1F9; border-color: #1d71b8; color: #1d71b8; transform: translateY(-1px); }
.tp-slot.active { background: #1d71b8 !important; color: #fff !important; border-color: #1d71b8 !important; box-shadow: 0 4px 12px rgba(29,113,184,0.3); }
.tp-slot:active { transform: scale(0.97); }

@media (max-width: 720px) {
  .tp-popup {
    top: 0 !important;
    left: 0 !important;
    bottom: auto !important;
    right: auto !important;
    transform: none !important;
    width: 100vw !important;
    height: 100vh !important;
    max-width: 100vw !important;
    max-height: 100vh !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    animation: tpSlideUp 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
    padding-top: env(safe-area-inset-top, 0px);
    box-sizing: border-box;
  }
  @keyframes tpSlideUp { from { transform: translateY(100%); opacity: 0.6; } to { transform: translateY(0); opacity: 1; } }
  .tp-popup.open { display: flex !important; flex-direction: column !important; }
  .tp-popup .tp-header {
    padding: 18px 18px 16px !important;
    flex-shrink: 0 !important;
    flex-grow: 0 !important;
    background: linear-gradient(135deg, #F5F8FC, #fff) !important;
    border-bottom: 1px solid #EDF1F7 !important;
    position: relative !important;
    top: auto !important;
    z-index: 2;
    display: flex !important;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    width: 100%;
    box-sizing: border-box;
  }
  .tp-popup .tp-title-wrap { flex: 1; min-width: 0; }
  .tp-popup .tp-title-main {
    font-size: 16px !important;
    line-height: 1.35 !important;
    color: #0A1F33 !important;
    font-weight: 800 !important;
    margin: 0;
  }
  .tp-popup .tp-close { flex-shrink: 0; }
  .tp-popup .tp-body {
    padding: 14px 14px calc(20px + env(safe-area-inset-bottom, 0px)) !important;
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch !important;
    flex: 1 1 auto !important;
    min-height: 0 !important;
  }
  .tp-popup .tp-grid { grid-template-columns: repeat(4, 1fr); gap: 8px; }
  .tp-popup .tp-slot { padding: 14px 2px; font-size: 14px; min-height: 50px; }
  body.rdp-lock {
    position: fixed !important;
    width: 100% !important;
    overflow: hidden !important;
  }
}
@media (max-width: 360px) {
  .tp-popup .tp-grid { grid-template-columns: repeat(3, 1fr); }
  .tp-popup .tp-slot { font-size: 13.5px; min-height: 46px; }
}
</style>

<?php include __DIR__ . '/includes/frontend/footer.php'; ?>
