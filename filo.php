<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/includes/bootstrap.php';

$currentPage = '/filo';
$pageTitle = 'Araç Kiralama Fiyatları 2026 — Rent a Car Karşılaştırma | Yolzz';
$pageDescription = 'Günlük araç kiralama fiyatlarını karşılaştırın, en uygun fırsatları bulun. Türkiye\'nin her yerinde havalimanı ve şehir merkezi teslim, 7/24 destek, güvenli ödeme.';
$pageKeywords = 'araç kiralama fiyatları, rent a car, kiralık araç, günlük araba kiralama, ucuz rent a car, araç kiralama karşılaştırma';

// Breadcrumb Schema
$pageJsonLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => SITE_URL],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Araç Filosu', 'item' => url('filo')]
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Arama parametreleri
$pickupCity = (int)get('pickup_city');
$pickupOffice = (int)get('pickup_office');
$returnCity = (int)get('return_city');
$returnOffice = (int)get('return_office');

// Ana sayfa arama formundan gelen pickup_location / return_location parse
// Değer formatı: "city_5", "office_12", "airport_3" gibi
$pickupLocation = get('pickup_location');
$returnLocation = get('return_location');

function parseLocationParam($val) {
    if (!$val) return [null, null];
    // Format: "city:5", "office:12", "airport:3", "location:8"
    if (preg_match('/^(city|office|airport|location)[:_](\d+)$/', $val, $m)) {
        return [$m[1], (int)$m[2]];
    }
    // Sadece sayı ise şehir kabul et
    if (is_numeric($val)) return ['city', (int)$val];
    return [null, null];
}

[$pickupType, $pickupId] = parseLocationParam($pickupLocation);
[$returnType, $returnId] = parseLocationParam($returnLocation);

// Eğer pickup_location geldiyse pickup_city/office'a map et
if ($pickupType === 'city' && $pickupId) {
    $pickupCity = $pickupId;
} elseif ($pickupType === 'office' && $pickupId) {
    $pickupOffice = $pickupId;
    // Office'ten city_id'yi çıkar
    $o = db()->fetch("SELECT city_id FROM offices WHERE id = ?", [$pickupId]);
    if ($o) $pickupCity = (int)$o['city_id'];
} elseif ($pickupType === 'airport' && $pickupId) {
    // Airport bir office (is_airport=1)
    $pickupOffice = $pickupId;
    $o = db()->fetch("SELECT city_id FROM offices WHERE id = ?", [$pickupId]);
    if ($o) $pickupCity = (int)$o['city_id'];
} elseif ($pickupType === 'location' && $pickupId) {
    // Locations tablosundan, city_id'sini al
    $loc = db()->fetch("SELECT city_id FROM locations WHERE id = ?", [$pickupId]);
    if ($loc && $loc['city_id']) $pickupCity = (int)$loc['city_id'];
}
// Aynısı return için
if ($returnType === 'city' && $returnId) {
    $returnCity = $returnId;
} elseif ($returnType === 'office' && $returnId) {
    $returnOffice = $returnId;
    $o = db()->fetch("SELECT city_id FROM offices WHERE id = ?", [$returnId]);
    if ($o) $returnCity = (int)$o['city_id'];
}

$pickupDate = get('pickup_date');
$returnDate = get('return_date');
$pickupTime = get('pickup_time', '10:00');
$returnTime = get('return_time', '10:00');
$differentReturn = (int)get('different_return', 0);
$category = (int)get('category');
$brand = (int)get('brand');
$fuel = get('fuel');
$transmission = get('transmission');
$minPrice = (float)get('min_price');
$maxPrice = (float)get('max_price');
$sortBy = get('sort', 'popular');

// Gün sayısı hesapla (toplam fiyat için)
$rentalDays = 1;
if ($pickupDate && $returnDate) {
    $diff = strtotime($returnDate) - strtotime($pickupDate);
    $rentalDays = max(1, (int)ceil($diff / 86400));
} else {
    $rentalDays = 3; // Varsayılan - kullanıcı tarih seçmediyse 3 gün varsay
}

// WHERE
$where = ["v.status = 'active'"];
$params = [];

// Ofis filtresi öncelikli (tedarikçi ayrımı için spesifik ofis bazlı)
if ($pickupOffice) {
    $where[] = "(v.office_id = :officeId OR v.office_id IS NULL)";
    $params['officeId'] = $pickupOffice;
} elseif ($pickupCity) {
    // Geriye uyumluluk: eski pickup_city linklerinde tüm şehirdeki ofisler
    $where[] = "(v.office_id IN (SELECT id FROM offices WHERE city_id = :cityId) OR v.office_id IS NULL)";
    $params['cityId'] = $pickupCity;
}
if ($category) { $where[] = "v.category_id = :cat"; $params['cat'] = $category; }
if ($brand) { $where[] = "v.brand_id = :br"; $params['br'] = $brand; }
if ($fuel) { $where[] = "v.fuel_type = :f"; $params['f'] = $fuel; }
if ($transmission) { $where[] = "v.transmission = :tr"; $params['tr'] = $transmission; }
if ($minPrice > 0) { $where[] = "v.daily_price >= :minp"; $params['minp'] = $minPrice; }
if ($maxPrice > 0 && $maxPrice < 10000) { $where[] = "v.daily_price <= :maxp"; $params['maxp'] = $maxPrice; }

$whereSql = implode(' AND ', $where);

// Sıralama
$orderBy = match($sortBy) {
    'price_asc' => 'v.daily_price ASC',
    'price_desc' => 'v.daily_price DESC',
    'newest' => 'v.created_at DESC',
    default => 'v.sort_order ASC, v.is_featured DESC, v.daily_price ASC'
};

// Sayfalama
$page = max(1, (int)get('page', 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$totalCount = (int)db()->fetchColumn(
    "SELECT COUNT(*) FROM vehicles v WHERE $whereSql", $params
);

$vehicles = db()->fetchAll(
    "SELECT v.*, vb.name AS brand_name, vc.name_tr AS category_name,
            o.name AS office_name, c.name AS office_city,
            rc.name AS rental_company_name, rc.logo AS rental_company_logo, rc.slug AS rental_company_slug
     FROM vehicles v
     LEFT JOIN vehicle_brands vb ON vb.id = v.brand_id
     LEFT JOIN vehicle_categories vc ON vc.id = v.category_id
     LEFT JOIN offices o ON o.id = v.office_id
     LEFT JOIN cities c ON c.id = o.city_id
     LEFT JOIN rental_companies rc ON rc.id = v.rental_company_id
     WHERE $whereSql
     ORDER BY $orderBy
     LIMIT $perPage OFFSET $offset",
    $params
);

// Lokal araçlara source bilgisi ekle (template'te ayırt etmek için)
foreach ($vehicles as &$v) {
    $v['source_label'] = 'Manuel';
    $v['source_provider_id'] = null;
    $v['is_api'] = false;
}
unset($v);

// === API SAĞLAYICI ARAÇLARI (feature flag açıksa) ===
$apiVehicles = [];
$apiProvidersQueried = [];
$apiEnabled = false;
try {
    $apiEnabled = db()->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'api_providers_enabled'") === '1';
} catch (Throwable $e) { $apiEnabled = false; }

if ($apiEnabled && $page === 1) {
    // Sadece 1. sayfada API'ye git (pagination karmaşık olmasın)
    require_once __DIR__ . '/includes/providers/ProviderOrchestrator.php';

    // Pickup office'in API external_id eşleşmesini bul
    $pickupExternalIds = [];
    if ($pickupOffice) {
        $maps = db()->fetchAll("
            SELECT api_provider_id, external_id
            FROM api_location_map
            WHERE local_office_id = ? AND is_active = 1
        ", [$pickupOffice]);
        foreach ($maps as $m) {
            $pickupExternalIds[(int)$m['api_provider_id']] = $m['external_id'];
        }
    }

    if (!empty($pickupExternalIds)) {
        try {
            $orch = new ProviderOrchestrator();
            // Aktif tüm API provider'larından sırayla araç çek
            $apiRows = db()->fetchAll("SELECT * FROM api_providers WHERE is_active = 1");
            require_once __DIR__ . '/includes/providers/ProviderFactory.php';

            foreach ($apiRows as $apiRow) {
                $extId = $pickupExternalIds[(int)$apiRow['id']] ?? null;
                if (!$extId) continue; // Bu sağlayıcıda eşleşme yok

                $provider = ProviderFactory::createFromRow($apiRow);
                if (!$provider) continue;

                $t0 = microtime(true);
                try {
                    $apiResults = $provider->searchVehicles([
                        'pickup_date' => $pickupDate,
                        'return_date' => $returnDate,
                        'pickup_time' => '10:00',
                        'return_time' => '10:00',
                        'pickup_external_id' => $extId,
                        'return_external_id' => $extId,
                        'currency' => 'TL',
                    ]);
                    foreach ($apiResults as $av) {
                        $apiVehicles[] = $av;
                    }
                    $apiProvidersQueried[] = [
                        'name' => $apiRow['name'],
                        'count' => count($apiResults),
                        'duration_ms' => (int)((microtime(true) - $t0) * 1000),
                    ];
                } catch (Throwable $e) {
                    error_log("filo.php API search ({$apiRow['name']}) hatası: " . $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            error_log("filo.php Orchestrator hatası: " . $e->getMessage());
        }
    }
}

// === API ARAÇLARINI LOKAL FORMATA DÖNÜŞTÜR ve $vehicles dizisine ekle ===
foreach ($apiVehicles as $av) {
    // Filo template'inin beklediği formata dönüştür
    $vehicles[] = [
        'id' => 'api_' . ($av['provider_id'] ?? 0) . '_' . ($av['external_id'] ?? ''),
        'brand_name' => $av['brand'] ?? '',
        'model' => $av['model'] ?? '',
        'category_name' => sippToCategory($av['category'] ?? ''),
        'fuel_type' => $av['fuel_type'] ?? '',
        'transmission' => $av['transmission'] ?? '',
        'seats' => $av['seats'] ?? 5,
        'doors' => $av['doors'] ?? 4,
        'luggage' => $av['luggage'] ?? 2,
        'year' => $av['year'] ?? 0,
        'daily_price' => $av['daily_price'] ?? 0,
        'discount_percent' => 0,
        'main_image' => $av['image'] ?? '',
        'tag' => null,
        // API features'ı okunabilir string listesine çevir
        'features' => json_encode((function($f) {
            $out = [];
            if (!empty($f['cdw'])) $out[] = 'CDW Güvencesi';
            if (!empty($f['scdw'])) $out[] = 'SCDW Güvencesi';
            if (!empty($f['lcf'])) $out[] = 'LCF Güvencesi';
            if (!empty($f['pai'])) $out[] = 'PAI Güvencesi';
            return $out;
        })($av['features'] ?? []), JSON_UNESCAPED_UNICODE),
        'office_name' => $av['pickup_office_name'] ?? '',
        'office_city' => '',
        'km_limit' => $av['features']['km_limit'] ?? '—',
        'min_age' => $av['min_age'] ?? 21,
        'min_license_year' => $av['min_license_year'] ?? 1,
        'insurance_info' => 'Tedarikçi tarafından sağlanır',
        'deposit' => $av['deposit'] ?? 0,
        'currency' => $av['currency'] ?? 'TRY',
        // API ham veriler (filtre için)
        'raw' => ['sipp' => $av['category'] ?? '', 'brand' => $av['brand'] ?? ''],
        // Source bilgileri
        'source_label' => $av['source_label'] ?? 'API',
        'source_provider_id' => $av['provider_id'] ?? null,
        'is_api' => true,
        'api_external_id' => $av['external_id'] ?? '',
        'api_group_id' => $av['group_id'] ?? '',
        'api_rez_id' => $av['rez_id'] ?? '',
        'api_extra_services' => $av['extra_services'] ?? [],
        // Firma eşleştirme (mapping varsa dolu, yoksa null)
        'rental_company_id' => $av['rental_company_id'] ?? null,
        'rental_company_name' => $av['rental_company_name'] ?? null,
        'rental_company_logo' => $av['rental_company_logo'] ?? null,
        'rental_company_slug' => $av['rental_company_slug'] ?? null,
    ];
}

// SIPP kodunu okunabilir kategoriye çevir
function sippToCategory($sipp) {
    if (empty($sipp) || strlen($sipp) < 1) return 'Standart';
    $first = strtoupper(substr($sipp, 0, 1));
    $map = [
        'M' => 'Mini',
        'N' => 'Mini Elite',
        'E' => 'Ekonomik',
        'H' => 'Ekonomik Elite',
        'C' => 'Kompakt',
        'D' => 'Kompakt Elite',
        'I' => 'Orta Sınıf',
        'J' => 'Orta Sınıf Elite',
        'S' => 'Standart',
        'R' => 'Standart Elite',
        'F' => 'Tam Boy',
        'G' => 'Tam Boy Elite',
        'P' => 'Premium',
        'U' => 'Premium Elite',
        'L' => 'Lüks',
        'W' => 'Lüks Elite',
        'O' => 'Geniş (Van/Minivan)',
        'X' => 'Özel',
    ];
    return $map[$first] ?? 'Standart';
}


if (!empty($apiVehicles)) {
    // Marka filtresi için brand adını çıkar (API araçlarda string brand)
    $brandName = null;
    if ($brand) {
        $brandName = db()->fetchColumn("SELECT name FROM vehicle_brands WHERE id = ?", [$brand]);
    }

    // Kategori filtresi için category SIPP harfi/grubu
    $categorySippLetters = null;
    if ($category) {
        $catRow = db()->fetch("SELECT name_tr FROM vehicle_categories WHERE id = ?", [$category]);
        // Kategori SIPP eşlemesi (sippToCategory ters mantık)
        $sippMap = [
            'mini' => ['M', 'N'],
            'ekonomik' => ['E', 'H'],
            'kompakt' => ['C', 'D'],
            'orta' => ['I', 'J'],
            'standart' => ['S', 'R'],
            'tam' => ['F', 'G'],
            'premium' => ['P', 'U'],
            'lüks' => ['L', 'W'],
            'luks' => ['L', 'W'],
            'lux' => ['L', 'W'],
            'van' => ['O'],
            'minivan' => ['O'],
        ];
        $catLower = mb_strtolower($catRow['name_tr'] ?? '', 'UTF-8');
        foreach ($sippMap as $keyword => $letters) {
            if (str_contains($catLower, $keyword)) {
                $categorySippLetters = $letters;
                break;
            }
        }
    }

    // Yakıt/vites Türkçe → API olası değerleri (eşleme)
    $fuelMap = [
        'Benzin'   => ['benzin', 'petrol', 'gasoline', 'unleaded'],
        'Dizel'    => ['dizel', 'diesel'],
        'LPG'      => ['lpg', 'gas', 'gpl'],
        'Elektrik' => ['elektrik', 'electric', 'ev'],
        'Hibrit'   => ['hibrit', 'hybrid'],
    ];
    $transMap = [
        'Manuel'    => ['manuel', 'manual', 'mt', 'std'],
        'Otomatik'  => ['otomatik', 'automatic', 'auto', 'at'],
    ];

    // API araçlarına filtreleri uygula
    $vehicles = array_filter($vehicles, function($v) use ($fuel, $transmission, $minPrice, $maxPrice, $brandName, $categorySippLetters, $brand, $category, $fuelMap, $transMap) {
        if (empty($v['is_api'])) return true; // Lokal araçlar zaten SQL'de filtrelenmiş

        // Yakıt — Türkçe filtre değerini birden çok karşılığa eşle
        if ($fuel) {
            $needles = $fuelMap[$fuel] ?? [mb_strtolower($fuel, 'UTF-8')];
            $haystack = mb_strtolower($v['fuel_type'] ?? '', 'UTF-8');
            $hit = false;
            foreach ($needles as $n) { if ($n !== '' && stripos($haystack, $n) !== false) { $hit = true; break; } }
            if (!$hit) return false;
        }
        // Vites
        if ($transmission) {
            $needles = $transMap[$transmission] ?? [mb_strtolower($transmission, 'UTF-8')];
            $haystack = mb_strtolower($v['transmission'] ?? '', 'UTF-8');
            $hit = false;
            foreach ($needles as $n) { if ($n !== '' && stripos($haystack, $n) !== false) { $hit = true; break; } }
            if (!$hit) return false;
        }
        // Min/Max fiyat
        if ($minPrice > 0 && (float)$v['daily_price'] < $minPrice) return false;
        if ($maxPrice > 0 && $maxPrice < 10000 && (float)$v['daily_price'] > $maxPrice) return false;

        // Marka (filtrede ID seçilince brandName çevirisi yapılmıştı)
        if ($brand && $brandName) {
            // Tüm olası alanları topla — brand_name (mapping sonrası), raw.brand (orijinal API), full_name
            $haystack = trim(
                ($v['brand_name'] ?? '') . ' ' .
                ($v['raw']['brand'] ?? '') . ' ' .
                ($v['full_name'] ?? '') . ' ' .
                ($v['model'] ?? '')
            );
            if ($haystack === '' || stripos($haystack, $brandName) === false) return false;
        }

        // Kategori (SIPP ilk harfle eşleşme)
        if ($category && $categorySippLetters) {
            $vehicleSipp = strtoupper(substr($v['raw']['sipp'] ?? $v['raw']['SIPP'] ?? '', 0, 1));
            if (!in_array($vehicleSipp, $categorySippLetters)) return false;
        }

        return true;
    });
    $vehicles = array_values($vehicles);

    // Tüm liste için sıralama (lokal + API beraber)
    usort($vehicles, function($a, $b) use ($sortBy) {
        $pa = (float)($a['daily_price'] ?? 99999);
        $pb = (float)($b['daily_price'] ?? 99999);
        return match($sortBy) {
            'price_asc'  => $pa <=> $pb,
            'price_desc' => $pb <=> $pa,
            'newest'     => 0,
            default      => $pa <=> $pb,
        };
    });
}

$categories = db()->fetchAll("SELECT * FROM vehicle_categories WHERE is_active = 1 ORDER BY sort_order, name_tr");
$brands = db()->fetchAll("SELECT * FROM vehicle_brands WHERE is_active = 1 ORDER BY name");
$cities = db()->fetchAll("SELECT DISTINCT c.* FROM cities c JOIN offices o ON o.city_id = c.id WHERE c.is_active = 1 AND o.is_active = 1 ORDER BY c.name");

// Searchable dropdown için tüm ofisler (sadece show_in_search=1 olanlar)
$allLocations = [];
$allOfficesForDropdown = db()->fetchAll("SELECT o.id, o.name, o.is_airport, c.name AS city_name
    FROM offices o JOIN cities c ON c.id = o.city_id
    WHERE o.is_active = 1 AND o.show_in_search = 1
    ORDER BY o.is_airport DESC, c.name, o.name");
foreach ($allOfficesForDropdown as $o) {
    $allLocations[] = [
        'type' => 'office',
        'value' => 'office:' . $o['id'],
        'label' => $o['city_name'] . ' - ' . $o['name'],
        'sub' => $o['is_airport'] ? 'Havalimanı' : 'Ofis',
        'icon' => $o['is_airport'] ? '✈' : '📍'
    ];
}

// Seçili lokasyonun label'ını çıkar (placeholder/display için)
$pickupLabel = '';
$returnLabel = '';
if ($pickupLocation) {
    foreach ($allLocations as $l) {
        if ($l['value'] === $pickupLocation) { $pickupLabel = $l['label']; break; }
    }
    // Geriye uyumluluk: pickup_city ile geliyorsa ilk ofisi bul
    if (!$pickupLabel && $pickupCity) {
        foreach ($allOfficesForDropdown as $o) {
            if ($o['city_name'] === (db()->fetchColumn("SELECT name FROM cities WHERE id = ?", [$pickupCity]) ?: '')) {
                $pickupLabel = $o['city_name'] . ' - ' . $o['name'];
                $pickupLocation = 'office:' . $o['id'];
                $pickupOffice = $o['id'];
                break;
            }
        }
    }
} elseif ($pickupCity) {
    // Eski linkler: pickup_city=X → ilk matching ofis
    foreach ($allOfficesForDropdown as $o) {
        if ($o['city_name'] === (db()->fetchColumn("SELECT name FROM cities WHERE id = ?", [$pickupCity]) ?: '')) {
            $pickupLabel = $o['city_name'] . ' - ' . $o['name'];
            $pickupLocation = 'office:' . $o['id'];
            $pickupOffice = $o['id'];
            break;
        }
    }
}
if ($returnLocation) {
    foreach ($allLocations as $l) {
        if ($l['value'] === $returnLocation) { $returnLabel = $l['label']; break; }
    }
}

$totalPages = (int)ceil($totalCount / $perPage);

$bodyClass = 'filo-page';
include __DIR__ . '/includes/frontend/header.php';
?>

<!-- MINI SEARCH BAR -->
<!-- ÜST ARAMA BARI (Mobilde tıklanınca açılır) -->
<section class="listing-search-bar" id="topSearchBar">
  <div class="container" style="max-width:1280px; margin:0 auto; padding:12px 20px;">
    <!-- Mobil özet (compact) -->
    <button type="button" class="lsb-compact" onclick="document.getElementById('topSearchBar').classList.toggle('open')">
      <span class="lsb-compact-icon">🔍</span>
      <span class="lsb-compact-text">
        <?php
          echo $pickupLabel ? e($pickupLabel) : 'Lokasyon seçin';
          if ($returnLabel && $returnLabel !== $pickupLabel) echo ' → ' . e($returnLabel);
          if ($pickupDate) echo ' · ' . date('d.m.Y', strtotime($pickupDate));
          if ($returnDate) echo ' → ' . date('d.m.Y', strtotime($returnDate));
        ?>
      </span>
      <span class="lsb-compact-edit">Değiştir</span>
    </button>

    <!-- Form (mobilde açılır, masaüstünde her zaman açık) -->
    <form method="GET" class="lsb-form" id="lsbForm">
      <!-- Mevcut filtreleri koru -->
      <?php foreach (['category','brand','fuel','transmission','min_price','max_price','sort'] as $k):
        $v = get($k);
        if ($v !== null && $v !== '') : ?>
          <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
      <?php endif; endforeach ?>

      <!-- Farklı iade toggle (hem masaüstü hem mobilde en üstte, tek) -->
      <div class="lsb-toggle-row">
        <label class="lsb-toggle">
          <input type="checkbox" name="different_return" id="lsbDiffReturn" value="1" <?= $differentReturn ? 'checked' : '' ?>>
          <span class="lsb-toggle-slider"></span>
          <span class="lsb-toggle-label">Farklı yerde iade et</span>
        </label>
      </div>

      <div class="lsb-grid <?= $differentReturn ? 'lsb-grid-with-return' : '' ?>" id="lsbGrid">
        <!-- Alış Lokasyonu (searchable) -->
        <div class="lsb-field lsb-field-location">
          <label>📍 Alış Lokasyonu</label>
          <div class="searchable-dropdown">
            <input type="text" class="sd-input sd-input-sm" placeholder="Şehir, havalimanı, ofis..." autocomplete="off" id="lsbPickupInput" value="<?= e($pickupLabel) ?>">
            <input type="hidden" name="pickup_location" id="lsbPickupHidden" value="<?= e($pickupLocation) ?>">
            <div class="sd-list" id="lsbPickupList"></div>
          </div>
        </div>

        <!-- İade Lokasyonu (koşullu) -->
        <div class="lsb-field lsb-field-location" id="lsbReturnField" style="<?= $differentReturn ? '' : 'display:none;' ?>">
          <label>📍 İade Lokasyonu</label>
          <div class="searchable-dropdown">
            <input type="text" class="sd-input sd-input-sm" placeholder="İade yerini seçin..." autocomplete="off" id="lsbReturnInput" value="<?= e($returnLabel) ?>">
            <input type="hidden" name="return_location" id="lsbReturnHidden" value="<?= e($returnLabel) ?>">
            <div class="sd-list" id="lsbReturnList"></div>
          </div>
        </div>

        <!-- Alış Zamanı (Tarih + Saat tek kart) -->
        <div class="lsb-field lsb-field-datetime">
          <label>📅 Alış Zamanı</label>
          <div class="lsb-datetime-row">
            <input type="text" class="lsb-date-display" id="lsbPickupDateDisplay" readonly placeholder="Tarih seçin" value="<?= $pickupDate ? date('d.m.Y', strtotime($pickupDate)) : date('d.m.Y', strtotime('+1 day')) ?>">
            <input type="hidden" name="pickup_date" id="lsbPickupDateHidden" value="<?= e($pickupDate ? date('Y-m-d', strtotime($pickupDate)) : date('Y-m-d', strtotime('+1 day'))) ?>">
            <select name="pickup_time" class="lsb-time-select">
              <?php for ($h = 8; $h <= 22; $h++): ?>
                <option value="<?= sprintf('%02d:00', $h) ?>" <?= $pickupTime === sprintf('%02d:00', $h) ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                <option value="<?= sprintf('%02d:30', $h) ?>" <?= $pickupTime === sprintf('%02d:30', $h) ? 'selected' : '' ?>><?= sprintf('%02d:30', $h) ?></option>
              <?php endfor ?>
            </select>
          </div>
        </div>

        <!-- İade Zamanı (Tarih + Saat tek kart) -->
        <div class="lsb-field lsb-field-datetime">
          <label>📅 İade Zamanı</label>
          <div class="lsb-datetime-row">
            <input type="text" class="lsb-date-display" id="lsbReturnDateDisplay" readonly placeholder="Tarih seçin" value="<?= $returnDate ? date('d.m.Y', strtotime($returnDate)) : date('d.m.Y', strtotime('+4 days')) ?>">
            <input type="hidden" name="return_date" id="lsbReturnDateHidden" value="<?= e($returnDate ? date('Y-m-d', strtotime($returnDate)) : date('Y-m-d', strtotime('+4 days'))) ?>">
            <select name="return_time" class="lsb-time-select">
              <?php for ($h = 8; $h <= 22; $h++): ?>
                <option value="<?= sprintf('%02d:00', $h) ?>" <?= $returnTime === sprintf('%02d:00', $h) ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
                <option value="<?= sprintf('%02d:30', $h) ?>" <?= $returnTime === sprintf('%02d:30', $h) ? 'selected' : '' ?>><?= sprintf('%02d:30', $h) ?></option>
              <?php endfor ?>
            </select>
          </div>
        </div>

        <button type="submit" class="lsb-btn">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          Güncelle
        </button>
      </div>

      <!-- Formu kapat butonu (sadece mobilde açıldığında görünür) -->
      <button type="button" class="lsb-close-btn" onclick="document.getElementById('topSearchBar').classList.remove('open')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        Formu Kapat
      </button>
    </form>
  </div>
</section>

<!-- RANGE DATE PICKER (popup — section dışında, body seviyesinde) -->
<div class="lsb-rdp-backdrop" id="lsbRdpBackdrop"></div>
<div class="lsb-rdp-popup" id="lsbRdpPopup">
  <div class="lsb-rdp-header">
    <button type="button" class="lsb-rdp-nav" id="lsbRdpPrev" aria-label="Önceki ay">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </button>
    <div class="lsb-rdp-info" id="lsbRdpInfo">Alış tarihini seçin</div>
    <button type="button" class="lsb-rdp-nav" id="lsbRdpNext" aria-label="Sonraki ay">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    </button>
    <button type="button" class="lsb-rdp-close" id="lsbRdpClose" aria-label="Kapat">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="lsb-rdp-weekdays">
    <span>Pzt</span><span>Sal</span><span>Çar</span><span>Per</span><span>Cum</span><span>Cmt</span><span>Paz</span>
  </div>
  <div class="lsb-rdp-months" id="lsbRdpMonths"></div>
  <div class="lsb-rdp-footer">
    <button type="button" class="lsb-rdp-clear" id="lsbRdpClear">Temizle</button>
    <button type="button" class="lsb-rdp-apply" id="lsbRdpApply">Tamam</button>
  </div>
</div>

<!-- Searchable Dropdown JS - üst arama barı için -->
<script>
(function(){
  const locations = <?= json_encode($allLocations, JSON_UNESCAPED_UNICODE) ?>;

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

    function itemHtml(l, queryRaw) {
      let label = l.label;
      if (queryRaw) {
        const q = normalize(queryRaw);
        const idx = normalize(l.label).indexOf(q);
        if (idx >= 0) {
          label = l.label.substring(0, idx) +
            '<strong style="color:#e94e1b;">' + l.label.substring(idx, idx + q.length) + '</strong>' +
            l.label.substring(idx + q.length);
        }
      }
      return `<div class="sd-item" data-value="${l.value}" data-label="${l.label}">
        <span class="sd-icon">${l.icon}</span>
        <div class="sd-text">
          <div class="sd-label">${label}</div>
          <div class="sd-sub">${l.sub}</div>
        </div>
      </div>`;
    }

    function render(query) {
      const qNorm = normalize(query);
      let html = '';
      if (!qNorm) {
        const airports = locations.filter(l => l.sub === 'Havalimanı');
        const offices = locations.filter(l => l.sub === 'Ofis');
        if (airports.length) { html += '<div class="sd-group-header">✈ HAVALİMANLARI</div>'; html += airports.map(l => itemHtml(l, '')).join(''); }
        if (offices.length) { html += '<div class="sd-group-header">📍 ŞEHİR OFİSLERİ</div>'; html += offices.map(l => itemHtml(l, '')).join(''); }
        if (!html) html = '<div class="sd-empty">Henüz ofis tanımlı değil</div>';
      } else {
        const starts = locations.filter(l => normalize(l.label).startsWith(qNorm));
        const contains = locations.filter(l =>
          !normalize(l.label).startsWith(qNorm) &&
          (normalize(l.label).includes(qNorm) || normalize(l.sub).includes(qNorm))
        );
        const combined = [...starts, ...contains].slice(0, 30);
        if (combined.length === 0) {
          html = '<div class="sd-empty">Sonuç bulunamadı</div>';
        } else {
          html = combined.map(l => itemHtml(l, query)).join('');
        }
      }
      list.innerHTML = html;
      list.querySelectorAll('.sd-item').forEach(item => {
        item.addEventListener('mousedown', (e) => {
          e.preventDefault();
          input.value = item.dataset.label;
          hidden.value = item.dataset.value;
          list.classList.remove('open');
          input.blur();
        });
      });
    }

    input.setAttribute('inputmode', 'search');

    // Focus davranışı: mevcut değer varsa tümünü seç (kullanıcı yeni yazarsa silinir)
    // Böylece kullanıcı mevcut seçimi değiştirmek için silmek zorunda kalmaz.
    let originalValue = input.value;
    let originalHidden = hidden.value;

    input.addEventListener('focus', () => {
      originalValue = input.value;
      originalHidden = hidden.value;
      render(input.value);
      list.classList.add('open');
      // Küçük bir gecikme ile select - mobilde tıklamayı engellememesi için
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
        // Eğer kullanıcı bir şey seçmeden çıktıysa ve hidden boşsa, eski değere geri dön
        if (!hidden.value && originalHidden) {
          input.value = originalValue;
          hidden.value = originalHidden;
        }
      }, 150);
    });
    // ESC ile iptal
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        input.value = originalValue;
        hidden.value = originalHidden;
        list.classList.remove('open');
        input.blur();
      }
    });
  }

  setupDropdown('lsbPickupInput', 'lsbPickupHidden', 'lsbPickupList');
  setupDropdown('lsbReturnInput', 'lsbReturnHidden', 'lsbReturnList');

  // Farklı iade toggle
  const diffReturn = document.getElementById('lsbDiffReturn');
  const returnField = document.getElementById('lsbReturnField');
  const lsbGrid = document.getElementById('lsbGrid');

  if (diffReturn && returnField) {
    diffReturn.addEventListener('change', () => {
      const state = diffReturn.checked;
      returnField.style.display = state ? '' : 'none';
      if (lsbGrid) {
        lsbGrid.classList.toggle('lsb-grid-with-return', state);
      }
      if (!state) {
        const ri = document.getElementById('lsbReturnInput');
        const rh = document.getElementById('lsbReturnHidden');
        if (ri) ri.value = '';
        if (rh) rh.value = '';
      }
    });
  }

  // Submit: farklı iade değilse return_location alanını form'dan temizle
  const form = document.getElementById('lsbForm');
  if (form) {
    form.addEventListener('submit', (e) => {
      if (diffReturn && !diffReturn.checked) {
        const rh = document.getElementById('lsbReturnHidden');
        if (rh) rh.value = '';
      }
    });
  }
})();

// =================== RANGE DATE PICKER (Filo üst bar için) ===================
(function(){
  const pickupDisplay = document.getElementById('lsbPickupDateDisplay');
  const returnDisplay = document.getElementById('lsbReturnDateDisplay');
  const pickupHidden = document.getElementById('lsbPickupDateHidden');
  const returnHidden = document.getElementById('lsbReturnDateHidden');
  const popup = document.getElementById('lsbRdpPopup');
  const backdrop = document.getElementById('lsbRdpBackdrop');
  const info = document.getElementById('lsbRdpInfo');
  const monthsWrap = document.getElementById('lsbRdpMonths');
  const prevBtn = document.getElementById('lsbRdpPrev');
  const nextBtn = document.getElementById('lsbRdpNext');
  const closeBtn = document.getElementById('lsbRdpClose');
  const clearBtn = document.getElementById('lsbRdpClear');
  const applyBtn = document.getElementById('lsbRdpApply');
  if (!pickupDisplay || !popup) return;

  const MONTH_NAMES = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];

  function parseDMY(s) {
    if (!s) return null;
    const p = s.split('.');
    if (p.length !== 3) return null;
    const d = new Date(+p[2], +p[1]-1, +p[0]);
    d.setHours(0,0,0,0);
    return d;
  }
  function fmtDMY(d) {
    if (!d) return '';
    return String(d.getDate()).padStart(2,'0') + '.' + String(d.getMonth()+1).padStart(2,'0') + '.' + d.getFullYear();
  }
  function fmtYMD(d) {
    if (!d) return '';
    return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
  }
  function sameDay(a, b) {
    return a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
  }
  function stripTime(d) { const x = new Date(d); x.setHours(0,0,0,0); return x; }

  let startDate = parseDMY(pickupDisplay.value);
  let endDate = parseDMY(returnDisplay.value);
  let selecting = 'start';
  let viewYear = startDate ? startDate.getFullYear() : new Date().getFullYear();
  let viewMonth = startDate ? startDate.getMonth() : new Date().getMonth();

  function renderMonth(year, month) {
    const first = new Date(year, month, 1);
    const last = new Date(year, month + 1, 0);
    let startDay = first.getDay();
    startDay = startDay === 0 ? 6 : startDay - 1;

    let html = '<div class="lsb-rdp-month">';
    html += '<div class="lsb-rdp-month-title">' + MONTH_NAMES[month] + ' ' + year + '</div>';
    html += '<div class="lsb-rdp-days">';
    for (let i = 0; i < startDay; i++) html += '<span class="lsb-rdp-day rdp-empty"></span>';

    const today = stripTime(new Date());
    for (let d = 1; d <= last.getDate(); d++) {
      const date = new Date(year, month, d);
      const isPast = date < today;
      const isToday = sameDay(date, today);
      const isStart = sameDay(date, startDate);
      const isEnd = sameDay(date, endDate);
      const isInRange = startDate && endDate && date > startDate && date < endDate;
      const classes = ['lsb-rdp-day'];
      if (isPast) classes.push('rdp-past');
      if (isToday) classes.push('rdp-today');
      if (isStart) classes.push('rdp-start');
      if (isEnd) classes.push('rdp-end');
      if (isInRange) classes.push('rdp-in-range');
      html += '<span class="' + classes.join(' ') + '" data-date="' + fmtYMD(date) + '">' + d + '</span>';
    }
    html += '</div></div>';
    return html;
  }

  function render() {
    const isMobile = window.innerWidth < 720;
    if (isMobile) {
      monthsWrap.innerHTML = renderMonth(viewYear, viewMonth);
    } else {
      const nextM = viewMonth + 1 > 11 ? 0 : viewMonth + 1;
      const nextY = viewMonth + 1 > 11 ? viewYear + 1 : viewYear;
      monthsWrap.innerHTML = renderMonth(viewYear, viewMonth) + renderMonth(nextY, nextM);
    }
    monthsWrap.querySelectorAll('.lsb-rdp-day:not(.rdp-past):not(.rdp-empty)').forEach(el => {
      el.addEventListener('click', () => {
        const d = new Date(el.dataset.date);
        onDateClick(d);
      });
    });
  }

  function onDateClick(date) {
    date = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    if (selecting === 'start') {
      startDate = date;
      endDate = null;
      selecting = 'end';
      info.textContent = 'İade tarihini seçin';
    } else {
      if (date < startDate) {
        startDate = date;
        endDate = null;
        info.textContent = 'İade tarihini seçin';
      } else {
        endDate = date;
        info.textContent = fmtDMY(startDate) + ' → ' + fmtDMY(endDate);
        setTimeout(() => applyRange(), 250);
      }
    }
    render();
  }

  function applyRange() {
    if (startDate) {
      pickupDisplay.value = fmtDMY(startDate);
      pickupHidden.value = fmtYMD(startDate);
    }
    if (endDate) {
      returnDisplay.value = fmtDMY(endDate);
      returnHidden.value = fmtYMD(endDate);
    }
    closePopup();
  }

  function openPopup(mode) {
    selecting = mode === 'return' ? 'end' : 'start';
    info.textContent = selecting === 'start' ? 'Alış tarihini seçin' : 'İade tarihini seçin';
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

  prevBtn.addEventListener('click', () => {
    if (viewMonth === 0) { viewMonth = 11; viewYear--; } else viewMonth--;
    render();
  });
  nextBtn.addEventListener('click', () => {
    if (viewMonth === 11) { viewMonth = 0; viewYear++; } else viewMonth++;
    render();
  });
  closeBtn.addEventListener('click', closePopup);
  applyBtn.addEventListener('click', applyRange);
  backdrop.addEventListener('click', closePopup);
  clearBtn.addEventListener('click', () => {
    startDate = null; endDate = null;
    selecting = 'start';
    info.textContent = 'Alış tarihini seçin';
    render();
  });
  pickupDisplay.addEventListener('click', () => openPopup('pickup'));
  returnDisplay.addEventListener('click', () => openPopup('return'));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && popup.classList.contains('open')) closePopup();
  });
})();
</script>

<!-- Mobil Filtre butonu (drawer için) -->
<div class="mobile-filter-btn-wrap">
  <div class="container" style="max-width:1280px; margin:0 auto; padding:12px 20px; display:flex; gap:10px;">
    <button type="button" class="mobile-filter-btn" onclick="document.getElementById('filterDrawer').classList.add('open')">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
      Filtrele
    </button>
    <select onchange="window.location.href=this.value" class="mobile-sort">
      <?php
      $sortOptions = [
        'popular' => 'Popüler',
        'price_asc' => 'Fiyat: Düşük → Yüksek',
        'price_desc' => 'Fiyat: Yüksek → Düşük',
        'newest' => 'En Yeni'
      ];
      foreach ($sortOptions as $val => $label):
        $qs = array_merge($_GET, ['sort' => $val]); unset($qs['page']);
      ?>
        <option value="?<?= http_build_query($qs) ?>" <?= $sortBy === $val ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach ?>
    </select>
  </div>
</div>

<section class="listing-sec" style="padding:40px 0 80px;">
  <div class="container" style="max-width:1280px; margin:0 auto; padding:0 20px;">
    <div style="display:grid; grid-template-columns: 280px 1fr; gap:24px;" class="listing-grid">

      <!-- SIDEBAR FILTERS (Mobilde drawer) -->
      <aside class="listing-sidebar" id="filterDrawer">
        <div class="filter-drawer-header">
          <div class="fdh-title">Filtreler</div>
          <button type="button" class="fdh-close" onclick="document.getElementById('filterDrawer').classList.remove('open')">✕</button>
        </div>
        <form method="GET" id="filterForm">
          <!-- Gizli parametreleri koru -->
          <input type="hidden" name="pickup_city" value="<?= e($pickupCity) ?>">
          <input type="hidden" name="pickup_office" value="<?= e($pickupOffice) ?>">
          <input type="hidden" name="return_office" value="<?= e($returnOffice ?? $pickupOffice) ?>">
          <input type="hidden" name="pickup_date" value="<?= e($pickupDate) ?>">
          <input type="hidden" name="return_date" value="<?= e($returnDate) ?>">
          <input type="hidden" name="pickup_time" value="<?= e($pickupTime) ?>">
          <input type="hidden" name="return_time" value="<?= e($returnTime) ?>">

          <!-- FİYAT ARALIĞI (slider ile) -->
          <div class="filter-card">
            <div class="filter-title">Fiyat Aralığı (₺/gün)</div>
            <div style="margin-top:8px;">
              <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--text); margin-bottom:10px; font-weight:600;">
                <span>Min: <strong id="minPriceLabel" style="color:var(--brand);"><?= number_format($minPrice ?: 0, 0, ',', '.') ?> ₺</strong></span>
                <span>Max: <strong id="maxPriceLabel" style="color:var(--brand);"><?= ($maxPrice && $maxPrice < 10000) ? number_format($maxPrice, 0, ',', '.') : '10.000' ?>+ ₺</strong></span>
              </div>
              <div class="price-range-wrap">
                <input type="range" id="minPriceSlider" name="min_price" min="0" max="10000" step="250" value="<?= $minPrice ?: 0 ?>" class="price-slider">
                <input type="range" id="maxPriceSlider" name="max_price" min="0" max="10000" step="250" value="<?= ($maxPrice && $maxPrice < 10000) ? $maxPrice : 10000 ?>" class="price-slider">
              </div>
              <div style="display:flex; justify-content:space-between; font-size:10px; color:var(--text-muted); margin-top:4px;">
                <span>0 ₺</span>
                <span>10.000 ₺+</span>
              </div>
              <div id="priceAutoHint" style="font-size:11px; color:var(--text-muted); margin-top:8px; text-align:center; opacity:0.8;">
                Slider bırakıldığında otomatik uygulanır
              </div>
            </div>
          </div>

          <div class="filter-card">
            <div class="filter-title">Kategori</div>
            <div class="filter-options">
              <?php foreach ($categories as $c): ?>
                <label class="filter-check">
                  <input type="radio" name="category" value="<?= $c['id'] ?>" <?= $category == $c['id'] ? 'checked' : '' ?> onchange="this.form.submit()">
                  <span><?= e($c['name_tr']) ?></span>
                </label>
              <?php endforeach ?>
              <?php if ($category): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['category' => ''])) ?>" style="font-size:12px; color:var(--accent);">× Temizle</a>
              <?php endif ?>
            </div>
          </div>

          <div class="filter-card">
            <div class="filter-title">Yakıt Tipi</div>
            <div class="filter-options">
              <?php foreach (['Benzin','Dizel','LPG','Elektrik','Hibrit'] as $f): ?>
                <label class="filter-check">
                  <input type="radio" name="fuel" value="<?= $f ?>" <?= $fuel === $f ? 'checked' : '' ?> onchange="this.form.submit()">
                  <span><?= $f ?></span>
                </label>
              <?php endforeach ?>
            </div>
          </div>

          <div class="filter-card">
            <div class="filter-title">Vites</div>
            <div class="filter-options">
              <?php foreach (['Manuel','Otomatik'] as $t): ?>
                <label class="filter-check">
                  <input type="radio" name="transmission" value="<?= $t ?>" <?= $transmission === $t ? 'checked' : '' ?> onchange="this.form.submit()">
                  <span><?= $t ?></span>
                </label>
              <?php endforeach ?>
            </div>
          </div>

          <div class="filter-card">
            <div class="filter-title">Marka</div>
            <div class="filter-options">
              <?php foreach ($brands as $b): ?>
                <label class="filter-check">
                  <input type="radio" name="brand" value="<?= $b['id'] ?>" <?= $brand == $b['id'] ? 'checked' : '' ?> onchange="this.form.submit()">
                  <span><?= e($b['name']) ?></span>
                </label>
              <?php endforeach ?>
            </div>
          </div>

          <a href="<?= url('filo') ?>" style="display:block; text-align:center; padding:10px; color:var(--accent); font-size:13px; text-decoration:none;">Tüm Filtreleri Temizle</a>

          <button type="button" class="filter-drawer-apply" onclick="document.getElementById('filterDrawer').classList.remove('open')">Sonuçları Göster</button>
        </form>
      </aside>
      <div class="filter-drawer-backdrop" onclick="document.getElementById('filterDrawer').classList.remove('open')"></div>

      <!-- RESULTS -->
      <div>
        <div class="listing-header">
          <div>
            <h1><?= number_format($totalCount + count($apiVehicles)) ?> Araç Bulundu</h1>
            <p>Türkiye genelinde rezervasyon yapabilirsiniz</p>
          </div>
          <div>
            <select onchange="window.location.href=this.value" style="padding:10px 14px; border:1px solid var(--border); border-radius:8px;">
              <?php
              $sortOptions = [
                'popular' => 'Popüler',
                'price_asc' => 'Fiyat: Düşük → Yüksek',
                'price_desc' => 'Fiyat: Yüksek → Düşük',
                'newest' => 'En Yeni'
              ];
              foreach ($sortOptions as $val => $label):
                $qs = array_merge($_GET, ['sort' => $val]); unset($qs['page']);
              ?>
                <option value="?<?= http_build_query($qs) ?>" <?= $sortBy === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach ?>
            </select>
          </div>
        </div>

        <?php if (empty($vehicles)): ?>
          <div class="no-results">
            <div class="no-results-icon">🔍</div>
            <h3>Sonuç Bulunamadı</h3>
            <p>Arama kriterlerinizi değiştirerek tekrar deneyin</p>
            <a href="<?= url('filo') ?>" class="btn-outline-brand">Filtreleri Temizle</a>
          </div>
        <?php else: ?>
          <div class="cars-grid" id="vehiclesGrid" data-initial="6">
            <?php foreach ($vehicles as $v):
              $features = is_string($v['features'] ?? null) ? (json_decode($v['features'], true) ?: []) : ($v['features'] ?? []);
              $vehicleId = $v['id'];
              $isApi = !empty($v['is_api']);
              $effectivePrice = $v['discount_percent'] > 0
                ? $v['daily_price'] * (1 - $v['discount_percent']/100)
                : $v['daily_price'];
              $totalPrice = $effectivePrice * $rentalDays;
              if ($isApi) {
                // API aracı için özel checkout URL'si
                $checkoutUrl = url('checkout?api_provider=' . $v['source_provider_id']
                    . '&api_vehicle=' . urlencode($v['api_external_id'])
                    . '&api_group=' . urlencode($v['api_group_id'] ?? '')
                    . '&api_rez=' . urlencode($v['api_rez_id'] ?? '')
                    . '&pickup_date=' . urlencode($pickupDate)
                    . '&return_date=' . urlencode($returnDate)
                    . ($pickupOffice ? '&pickup_office=' . $pickupOffice : ''));
              } else {
                $checkoutUrl = url('checkout?vehicle=' . $v['id'] . '&pickup_date=' . urlencode($pickupDate) . '&return_date=' . urlencode($returnDate) . '&pickup_city=' . $pickupCity . ($pickupOffice ? '&pickup_office=' . $pickupOffice : ''));
              }
            ?>
              <article class="car vehicle-card" data-source="<?= $isApi ? 'api' : 'local' ?>">
                <?php if (!empty($v['rental_company_logo'])): ?>
                  <span class="company-badge" title="<?= e($v['rental_company_name']) ?>">
                    <img src="<?= upload_url($v['rental_company_logo']) ?>" alt="<?= e($v['rental_company_name']) ?>">
                  </span>
                <?php elseif (!empty($v['rental_company_name'])): ?>
                  <span class="company-badge company-badge--text"><?= e($v['rental_company_name']) ?></span>
                <?php elseif ($isApi): ?>
                  <span class="company-badge company-badge--text" title="<?= e($v['source_label']) ?>"><?= e($v['source_label']) ?></span>
                <?php endif ?>
                <div class="car-img">
                  <?php if ($v['tag']): ?>
                    <?php
                    $tagMap = ['popular' => 'En Popüler', 'new' => 'Yeni', 'discount' => 'İndirimli', 'luxury' => 'Lüks', 'eco' => 'Eko'];
                    $tagLabel = $tagMap[$v['tag']] ?? ucfirst($v['tag']);
                    $tagClass = in_array($v['tag'], ['new','eco']) ? 'green' : (in_array($v['tag'], ['luxury']) ? 'blue' : '');
                    ?>
                    <span class="car-tag <?= $tagClass ?>"><?= e($tagLabel) ?></span>
                  <?php elseif ($v['discount_percent'] > 0): ?>
                    <span class="car-tag green">%<?= $v['discount_percent'] ?> İndirim</span>
                  <?php endif ?>

                  <?php if ($v['main_image']): ?>
                    <img src="<?= upload_url($v['main_image']) ?>" alt="<?= e($v['brand_name'] . ' ' . $v['model']) ?>" style="width:100%; height:100%; object-fit:cover;" loading="lazy">
                  <?php else: ?>
                    <!-- Araba ikonu SVG (placeholder) -->
                    <svg viewBox="0 0 300 140" xmlns="http://www.w3.org/2000/svg">
                      <path d="M40 100 L50 75 Q55 65 70 60 L120 55 Q140 40 170 40 L210 42 Q230 45 245 60 L265 65 Q275 68 278 78 L280 100 Z" fill="#1d71b8"/>
                      <path d="M130 55 L170 42 L205 43 L215 58 Z" fill="#B0D4EE" opacity="0.7"/>
                      <circle cx="90" cy="105" r="18" fill="#0A1F33"/>
                      <circle cx="90" cy="105" r="10" fill="#3D5269"/>
                      <circle cx="230" cy="105" r="18" fill="#0A1F33"/>
                      <circle cx="230" cy="105" r="10" fill="#3D5269"/>
                      <rect x="255" y="70" width="15" height="6" rx="2" fill="#e94e1b"/>
                    </svg>
                  <?php endif ?>
                </div>

                <div class="car-body">
                  <div class="car-cat"><?= e($v['category_name']) ?></div>
                  <h3 class="car-name"><?= e($v['brand_name'] . ' ' . $v['model']) ?></h3>
                  <div class="car-sub">veya benzeri · <?= e($v['fuel_type']) ?></div>
                  <div class="car-specs">
                    <div class="car-spec">
                      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                      <strong><?= $v['seats'] ?></strong> Kişi
                    </div>
                    <?php if (!empty($v['luggage'])): ?>
                    <div class="car-spec">
                      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                      <strong><?= (int)$v['luggage'] ?></strong> Bagaj
                    </div>
                    <?php endif ?>
                    <div class="car-spec">
                      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                      <strong><?= e($v['transmission']) ?></strong>
                    </div>
                    <?php if (!empty($v['km_limit']) && $v['km_limit'] !== '—'): ?>
                    <div class="car-spec">
                      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                      <strong><?= e($v['km_limit']) ?></strong> km
                    </div>
                    <?php endif ?>
                    <?php if (!empty($v['deposit']) && (float)$v['deposit'] > 0): ?>
                    <div class="car-spec">
                      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                      <strong><?= number_format((float)$v['deposit'], 0, ',', '.') ?>₺</strong> Depozito
                    </div>
                    <?php endif ?>
                  </div>
                  <div class="car-foot">
                    <div class="car-foot-row">
                      <div>
                        <div class="car-price-lbl">Günlük</div>
                        <div class="car-price"><?= tl($effectivePrice) ?><span class="car-price-dy">/gün</span></div>
                        <div class="car-total"><?= $rentalDays ?> gün toplam: <strong><?= tl($totalPrice) ?></strong></div>
                      </div>
                    </div>
                    <a href="<?= $checkoutUrl ?>" class="car-btn">
                      <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                      Kirala
                    </a>
                  </div>
                </div>

                <div class="car-detail-btn" onclick="let c=this.closest('.car'); c.classList.toggle('detail-open'); let p=c.querySelector('.car-detail'); p.style.maxHeight = p.style.maxHeight ? null : p.scrollHeight + 'px'">
                  <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                  <span>Detaylar</span>
                </div>

                <div class="car-detail">
                  <div class="car-detail-inner">
                    <div class="cd-section-title">Araç Bilgileri</div>
                    <div class="cd-grid">
                      <div class="cd-item"><div class="cd-item-label">Yakıt</div><div class="cd-item-val"><?= e($v['fuel_type'] ?: '—') ?></div></div>
                      <div class="cd-item"><div class="cd-item-label">Vites</div><div class="cd-item-val"><?= e($v['transmission'] ?: '—') ?></div></div>
                      <div class="cd-item"><div class="cd-item-label">KM Limiti</div><div class="cd-item-val"><?= e($v['km_limit'] ?: '—') ?></div></div>
                      <div class="cd-item"><div class="cd-item-label">Yaş Sınırı</div><div class="cd-item-val"><?= !empty($v['min_age']) ? $v['min_age'] . '+' : '—' ?></div></div>
                      <div class="cd-item"><div class="cd-item-label">Ehliyet Süresi</div><div class="cd-item-val"><?= !empty($v['min_license_year']) ? $v['min_license_year'] . '+ Yıl' : '—' ?></div></div>
                      <div class="cd-item"><div class="cd-item-label">Depozito</div><div class="cd-item-val"><?= !empty($v['deposit']) ? number_format($v['deposit'], 0, ',', '.') . ' ₺' : '—' ?></div></div>
                      <div class="cd-item"><div class="cd-item-label">Sigorta</div><div class="cd-item-val"><?= !empty($v['insurance_info']) ? e($v['insurance_info']) : '—' ?></div></div>
                    </div>

                    <?php if (!empty($features)): ?>
                    <div class="cd-section-title">Özellikler</div>
                    <div class="cd-features">
                      <?php foreach ($features as $feat): ?>
                        <span class="cd-feat">
                          <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                          <?= e($feat) ?>
                        </span>
                      <?php endforeach ?>
                    </div>
                    <?php endif ?>

                    <?php if ($v['office_name']): ?>
                    <div class="cd-section-title">Teslim Alma Noktası</div>
                    <div class="cd-note" style="background:var(--brand-wash);">
                      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                      <span><strong><?= e($v['office_city']) ?></strong> — <?= e($v['office_name']) ?></span>
                    </div>
                    <?php endif ?>

                    <div class="cd-note">
                      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                      <span>Araç görseli temsilidir. Kiralama koşulları ofise göre değişiklik gösterebilir.</span>
                    </div>

                    <a href="<?= $checkoutUrl ?>" class="cd-cta">
                      <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                      Hemen Kirala — Toplam <?= tl($totalPrice) ?>
                    </a>
                  </div>
                </div>
              </article>
            <?php endforeach ?>
          </div>

          <!-- Lazy Load Sentinel (scroll'da görünür olunca yeni kart açar) -->
          <div class="load-more-sentinel" id="loadMoreSentinel" style="display:none;">
            <div class="load-more-spinner"></div>
          </div>

          <?php if ($totalPages > 1): ?>
          <div class="listing-pagination" id="paginationWrap">
            <?php
            $qs = $_GET; unset($qs['page']);
            $base = '?' . http_build_query($qs) . '&page=';
            ?>
            <?php if ($page > 1): ?>
              <a href="<?= $base . ($page - 1) ?>">‹ Önceki</a>
            <?php endif ?>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
              <a href="<?= $base . $p ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor ?>
            <?php if ($page < $totalPages): ?>
              <a href="<?= $base . ($page + 1) ?>">Sonraki ›</a>
            <?php endif ?>
          </div>
          <?php endif ?>
        <?php endif ?>
      </div>
    </div>
  </div>
</section>

<!-- ARAÇ ARANIYOR LOADING OVERLAY (arama formu submit'inde tetiklenir) -->
<div class="vehicle-loader-overlay" id="vehicleLoader">
  <div class="vlo-inner">
    <div class="vlo-icon">
      <span class="vlo-car">🚗</span>
    </div>
    <div class="vlo-title">Size en uygun araçlar aranıyor</div>
    <div class="vlo-sub">Tüm firmaların güncel müsaitlik ve fiyatları kontrol ediliyor.<br>Bu birkaç saniye sürebilir.</div>
    <div class="vlo-steps">
      <div class="vlo-step active" id="vloStep1">
        <div class="vlo-step-icon"></div>
        <span>Müsait araçlar taranıyor...</span>
      </div>
      <div class="vlo-step" id="vloStep2">
        <div class="vlo-step-icon"></div>
        <span>Firma fiyatları karşılaştırılıyor...</span>
      </div>
      <div class="vlo-step" id="vloStep3">
        <div class="vlo-step-icon"></div>
        <span>En iyi seçenekler sıralanıyor...</span>
      </div>
    </div>
  </div>
</div>

<style>
.listing-grid { display: grid; grid-template-columns: 280px 1fr; gap: 24px; }

.filter-card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 16px; margin-bottom: 12px; }
.filter-title { font-size: 13px; font-weight: 700; color: var(--ink); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.03em; }
.filter-options { display: flex; flex-direction: column; gap: 8px; }
.filter-check { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text); cursor: pointer; }
.filter-check input { accent-color: var(--brand); }

.listing-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.listing-header h1 { font-size: 24px; color: var(--ink); margin-bottom: 4px; }
.listing-header p { color: var(--text-muted); font-size: 13px; }
@media (max-width: 991px) {
  .listing-header > div:nth-child(2) { display: none; } /* Mobilde sırala dropdown'ı üst barda */
}

.listing-pagination { display: flex; gap: 6px; justify-content: center; margin-top: 40px; }
.listing-pagination a { padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; text-decoration: none; color: var(--ink); font-size: 13px; font-weight: 500; }
.listing-pagination a.active { background: var(--brand); color: #fff; border-color: var(--brand); }
.listing-pagination a:hover:not(.active) { background: var(--bg-soft); }

.no-results { text-align: center; padding: 60px 20px; background: #fff; border-radius: 16px; border: 1px solid var(--border); }
.no-results-icon { font-size: 48px; margin-bottom: 16px; }
.no-results h3 { font-size: 20px; color: var(--ink); margin-bottom: 8px; }
.no-results p { color: var(--text-muted); margin-bottom: 20px; }
.btn-outline-brand { display: inline-block; padding: 10px 20px; border: 2px solid var(--brand); color: var(--brand); border-radius: 10px; text-decoration: none; font-weight: 600; transition: all 0.2s; }
.btn-outline-brand:hover { background: var(--brand); color: #fff; }
</style>

<script>
// Fiyat slider - min max birbirini geçmesin + canlı label + otomatik uygula
(function(){
  const minS = document.getElementById('minPriceSlider');
  const maxS = document.getElementById('maxPriceSlider');
  const minL = document.getElementById('minPriceLabel');
  const maxL = document.getElementById('maxPriceLabel');
  if (!minS || !maxS) return;
  const MAX_RANGE = 10000;
  function fmt(v){ return Number(v).toLocaleString('tr-TR') + ' ₺'; }
  function fmtMax(v){
    const n = parseInt(v);
    return n >= MAX_RANGE ? MAX_RANGE.toLocaleString('tr-TR') + '+ ₺' : fmt(n);
  }
  function update(){
    const minV = parseInt(minS.value);
    const maxV = parseInt(maxS.value);
    if (minV > maxV - 250) { minS.value = maxV - 250; }
    if (maxV < minV + 250) { maxS.value = minV + 250; }
    minL.textContent = fmt(minS.value);
    maxL.textContent = fmtMax(maxS.value);
  }
  minS.addEventListener('input', update);
  maxS.addEventListener('input', update);
  update();

  // Slider bırakıldığında (change event) otomatik form submit
  // Debounce ile (eğer hızlıca iki slider hareket ettirilirse sadece son halini gönderir)
  let submitTimer = null;
  function autoSubmit() {
    clearTimeout(submitTimer);
    submitTimer = setTimeout(() => {
      const form = minS.closest('form');
      if (form) form.submit();
    }, 350);
  }
  minS.addEventListener('change', autoSubmit);
  maxS.addEventListener('change', autoSubmit);
})();

// =================== LAZY LOAD (sayfa içi) ===================
(function(){
  const grid = document.getElementById('vehiclesGrid');
  const sentinel = document.getElementById('loadMoreSentinel');
  const paginationWrap = document.getElementById('paginationWrap');
  if (!grid) return;

  const initialCount = parseInt(grid.dataset.initial || '6');
  const batchSize = 4;
  const cards = Array.from(grid.querySelectorAll('.vehicle-card'));

  // Az araç varsa hepsini hemen göster, lazy load gerekmez
  if (cards.length <= 12) {
    if (sentinel) sentinel.style.display = 'none';
    return;
  }

  if (cards.length <= initialCount) return; // Lazy load gerekmiyor

  // İlk N kart görünür, geri kalanı gizle
  let visibleCount = initialCount;
  cards.forEach((card, i) => {
    if (i >= initialCount) card.style.display = 'none';
  });

  sentinel.style.display = 'flex';
  if (paginationWrap) paginationWrap.style.display = 'none';

  function loadMore() {
    const next = Math.min(visibleCount + batchSize, cards.length);
    for (let i = visibleCount; i < next; i++) {
      cards[i].style.display = '';
      cards[i].style.animation = 'vloPop 0.4s cubic-bezier(0.2,0.8,0.2,1)';
    }
    visibleCount = next;
    if (visibleCount >= cards.length) {
      sentinel.style.display = 'none';
      if (paginationWrap) paginationWrap.style.display = 'flex';
      if (window.__vloObserver) {
        window.__vloObserver.disconnect();
        window.__vloObserver = null;
      }
    }
  }

  // Intersection Observer: sentinel ekrana girince yeni kartlar aç
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting && visibleCount < cards.length) {
          setTimeout(loadMore, 200); // Daha kısa gecikme
        }
      });
    }, { rootMargin: '200px' });
    io.observe(sentinel);
    window.__vloObserver = io;
  } else {
    // Fallback: scroll event
    window.addEventListener('scroll', () => {
      const rect = sentinel.getBoundingClientRect();
      if (rect.top < window.innerHeight + 200) loadMore();
    });
  }
})();

// =================== LOADING OVERLAY (arama submit) ===================
(function(){
  const loader = document.getElementById('vehicleLoader');
  if (!loader) return;

  // Sayfadaki form submit'lerinde göster (filtre uygula, sırala vs.)
  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', () => {
      showLoader();
    });
  });

  // Sort dropdown ve "Tüm filtreleri temizle" gibi linklerde de göster
  document.querySelectorAll('.mobile-sort, .listing-pagination a, a[href*="sort="], a[href*="filo"]').forEach(el => {
    if (el.tagName === 'A' && el.href) {
      el.addEventListener('click', () => {
        if (el.href.includes('filo')) showLoader();
      });
    }
  });

  // Ana sayfadaki arama formundan gelince: URL'de arama parametresi varsa kısa süre göster
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has('pickup_city') || urlParams.has('pickup_location') || urlParams.has('pickup_date')) {
    // İlk yüklemede sayfa zaten hazır - mini loader gösterip kısa sürede kapat
    // (Gerçek API aramasına geçince fetch içinde gösterilir)
  }

  function showLoader() {
    loader.classList.add('active');
    // Adımları sırayla aktif et
    const s1 = document.getElementById('vloStep1');
    const s2 = document.getElementById('vloStep2');
    const s3 = document.getElementById('vloStep3');
    if (s1 && s2 && s3) {
      setTimeout(() => { s1.classList.add('done'); s1.classList.remove('active'); s2.classList.add('active'); }, 900);
      setTimeout(() => { s2.classList.add('done'); s2.classList.remove('active'); s3.classList.add('active'); }, 1800);
    }
  }
})();
</script>

<!-- ========== FİRMA ROZETİ + ÜSTE ÇIK BUTONU ========== -->
<style>
/* Firma rozeti — kartın sağ üst köşesinde, görselin yanındaki boşlukta */
.vehicle-card { position: relative; }
.car-img { position: relative; }
.company-badge {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 10;
  background: #fff;
  padding: 6px 10px;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(10,31,51,0.12);
  border: 1px solid #EDF1F7;
  display: inline-flex;
  align-items: center;
  pointer-events: none;
}
.company-badge img {
  height: 22px;
  max-width: 110px;
  object-fit: contain;
  display: block;
}
.company-badge--text {
  background: #1d71b8;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.4px;
  padding: 5px 10px;
  border-radius: 6px;
  border: 0;
}
/* Mobilde küçült */
@media (max-width: 767px) {
  .company-badge { top: 8px; right: 8px; padding: 4px 7px; border-radius: 6px; }
  .company-badge img { height: 16px; max-width: 70px; }
  .company-badge--text { font-size: 9px; padding: 3px 7px; }
}

/* Üste çık butonu - sadece masaüstü */
.scroll-top-btn {
  position: fixed;
  right: 24px;
  bottom: 24px;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: var(--accent, #e94e1b);
  color: #fff;
  border: 0;
  display: none;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(0,0,0,0.25);
  z-index: 999;
  transition: transform 0.2s, opacity 0.2s;
}
.scroll-top-btn:hover { transform: translateY(-3px); }
.scroll-top-btn svg { width: 22px; height: 22px; }
@media (min-width: 769px) {
  .scroll-top-btn.visible { display: flex; }
}
@media (max-width: 768px) {
  .scroll-top-btn { display: none !important; }
}
</style>

<button id="scrollTopBtn" class="scroll-top-btn" aria-label="Üste çık">
  <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
  </svg>
</button>

<script>
(function(){
  const btn = document.getElementById('scrollTopBtn');
  if (!btn) return;
  function toggle() {
    if (window.scrollY > 400) btn.classList.add('visible');
    else btn.classList.remove('visible');
  }
  window.addEventListener('scroll', toggle, { passive: true });
  btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  toggle();
})();
</script>

<?php include __DIR__ . '/includes/frontend/footer.php'; ?>
