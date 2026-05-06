<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/includes/bootstrap.php';

$vehicleId = (int)get('vehicle');
$pickupDate = get('pickup_date');
$returnDate = get('return_date');
$pickupCityId = (int)get('pickup_city');
$pickupOfficeId = (int)get('pickup_office');

// === API ARAÇ ALGILAMA ===
$apiProviderId = (int)get('api_provider');
$apiVehicleId = get('api_vehicle');
$apiGroupId = get('api_group');
$apiRezId = get('api_rez');
$isApiBooking = ($apiProviderId > 0 && !empty($apiVehicleId));

$pageTitle = 'Rezervasyon Tamamla — Güvenli Ödeme | Yolzz';
$pageDescription = 'Aracınızı hemen kiralayın. Güvenli ödeme, anında onay, 7/24 destek.';
$pageKeywords = 'araç kiralama rezervasyon, online ödeme, güvenli rezervasyon';
$pageRobots = 'noindex, nofollow';
$currentPage = '/checkout';
$bodyClass = 'checkout-page';

$vehicle = null;
$apiVehicle = null;
$apiProvider = null;
$apiServices = [];

if ($isApiBooking) {
    // === API ARAÇ AKIŞI ===
    require_once __DIR__ . '/includes/providers/ProviderFactory.php';

    $apiProvider = db()->fetch("SELECT * FROM api_providers WHERE id = ? AND is_active = 1", [$apiProviderId]);
    if (!$apiProvider) {
        flash_error('API sağlayıcı bulunamadı veya pasif.');
        redirect(url('filo'));
    }

    // Pickup ofisinin external_id'sini bul
    $pickupExtId = null;
    if ($pickupOfficeId) {
        $pickupExtId = db()->fetchColumn("
            SELECT external_id FROM api_location_map
            WHERE api_provider_id = ? AND local_office_id = ? AND is_active = 1
        ", [$apiProviderId, $pickupOfficeId]);
    }

    if (!$pickupExtId) {
        flash_error('Bu lokasyon için araç sorgu yapılamadı.');
        redirect(url('filo'));
    }

    // Araç bilgisini API'den tekrar al (cache'den anında dönecek)
    $providerInst = ProviderFactory::createFromRow($apiProvider);
    if (!$providerInst) {
        flash_error('Sağlayıcı yüklenemedi.');
        redirect(url('filo'));
    }

    $apiResults = $providerInst->searchVehicles([
        'pickup_date' => $pickupDate,
        'return_date' => $returnDate,
        'pickup_time' => '10:00',
        'return_time' => '10:00',
        'pickup_external_id' => $pickupExtId,
        'return_external_id' => $pickupExtId,
        'currency' => 'TL',
    ]);

    // Aracı bul (external_id veya rez_id eşleşsin)
    foreach ($apiResults as $av) {
        if ((string)$av['external_id'] === (string)$apiVehicleId
            || (string)$av['rez_id'] === (string)$apiRezId) {
            $apiVehicle = $av;
            break;
        }
    }

    if (!$apiVehicle) {
        flash_error('Seçtiğiniz araç artık müsait değil. Lütfen tekrar arama yapın.');
        redirect(url('filo'));
    }

    // Tarihleri valide
    if (!$pickupDate || !$returnDate) {
        $pickupDate = date('Y-m-d', strtotime('+1 day'));
        $returnDate = date('Y-m-d', strtotime('+4 day'));
    }

    $days = $apiVehicle['days'] ?? max(1, (int)ceil((strtotime($returnDate) - strtotime($pickupDate)) / 86400));
    $subtotal = (float)$apiVehicle['total_price'];
    $apiServices = $apiVehicle['extra_services'] ?? [];

} else {
    // === LOKAL ARAÇ AKIŞI (mevcut) ===
    $vehicle = db()->fetch("
        SELECT v.*, vb.name AS brand_name, vc.name_tr AS category_name
        FROM vehicles v
        LEFT JOIN vehicle_brands vb ON vb.id = v.brand_id
        LEFT JOIN vehicle_categories vc ON vc.id = v.category_id
        WHERE v.id = ? AND v.status = 'active'
    ", [$vehicleId]);

    if (!$vehicle) {
        flash_error('Araç bulunamadı.');
        redirect(url('filo'));
    }

    if (!$pickupDate || !$returnDate) {
        $pickupDate = date('Y-m-d\TH:i', strtotime('+1 day'));
        $returnDate = date('Y-m-d\TH:i', strtotime('+4 day'));
    }

    $days = max(1, (int)ceil((strtotime($returnDate) - strtotime($pickupDate)) / 86400));
    $subtotal = $vehicle['daily_price'] * $days;
    if ($vehicle['discount_percent'] > 0) {
        $subtotal = $subtotal * (1 - $vehicle['discount_percent'] / 100);
    }
}

$offices = db()->fetchAll("SELECT o.*, c.name AS city_name FROM offices o
    JOIN cities c ON c.id = o.city_id
    WHERE o.is_active = 1 ORDER BY c.name, o.name");

// POST handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('_csrf'))) {
        flash_error('Güvenlik hatası.');
        redirect($_SERVER['REQUEST_URI']);
    }

    $pickupOfficeId = (int)post('pickup_office_id');
    $returnOfficeId = (int)post('return_office_id') ?: $pickupOfficeId;
    $actualPickup = post('pickup_datetime') ?: $pickupDate;
    $actualReturn = post('return_datetime') ?: $returnDate;
    $actualDays = max(1, (int)ceil((strtotime($actualReturn) - strtotime($actualPickup)) / 86400));

    $extras = [];
    $extrasTotal = 0;

    if ($isApiBooking) {
        // API ekstra hizmetleri (Services array'inden)
        $apiExtrasSelected = $_POST['api_extras'] ?? [];
        foreach ($apiExtrasSelected as $svcKey) {
            if (isset($apiServices[$svcKey])) {
                $svc = $apiServices[$svcKey];
                $extras[] = ['key' => $svcKey, 'name' => $svc['title'], 'price' => $svc['price']];
                $extrasTotal += $svc['price'];
            }
        }
    } else {
        // Lokal araç ekstraları (mevcut)
        $extraOptions = [
            'baby_seat' => ['name' => 'Bebek Koltuğu', 'price' => 50],
            'gps' => ['name' => 'Navigasyon', 'price' => 30],
            'additional_driver' => ['name' => 'Ek Sürücü', 'price' => 40],
            'full_insurance' => ['name' => 'Tam Kasko', 'price' => 80]
        ];
        foreach ($extraOptions as $key => $opt) {
            if (!empty($_POST['extras'][$key])) {
                $extras[] = ['key' => $key, 'name' => $opt['name'], 'price' => $opt['price'] * $actualDays];
                $extrasTotal += $opt['price'] * $actualDays;
            }
        }
    }

    // Kupon (sadece lokal araçta — API'de yok)
    $discountTotal = 0;
    $campaignCode = post('coupon_code');
    $campaign = null;
    if (!$isApiBooking && $campaignCode) {
        $campaign = db()->fetch("SELECT * FROM campaigns WHERE coupon_code = ? AND status = 'active'
            AND (start_date IS NULL OR start_date <= CURDATE())
            AND (end_date IS NULL OR end_date >= CURDATE())", [$campaignCode]);
        if ($campaign) {
            if ($campaign['discount_type'] === 'percent') {
                $discountTotal = ($subtotal + $extrasTotal) * ($campaign['discount_value'] / 100);
            } elseif ($campaign['discount_type'] === 'fixed') {
                $discountTotal = min($campaign['discount_value'], $subtotal + $extrasTotal);
            }
        }
    }

    $totalBeforeTax = $subtotal + $extrasTotal - $discountTotal;
    // API araçlarda fiyat genelde KDV dahil, lokalde KDV ayrı
    if ($isApiBooking) {
        $tax = 0;
        $grandTotal = $totalBeforeTax;
    } else {
        $tax = round($totalBeforeTax * 0.20, 2);
        $grandTotal = $totalBeforeTax + $tax;
    }

    $bookingCode = generate_booking_code();

    $booking = [
        'booking_code' => $bookingCode,
        'vehicle_id' => $isApiBooking ? null : $vehicle['id'],
        'pickup_office_id' => $pickupOfficeId,
        'return_office_id' => $returnOfficeId,
        'pickup_datetime' => $actualPickup,
        'return_datetime' => $actualReturn,
        'total_days' => $actualDays,
        'guest_first_name' => post('first_name'),
        'guest_last_name' => post('last_name'),
        'guest_email' => post('email'),
        'guest_phone' => post('phone'),
        'guest_tc' => post('tc'),
        'subtotal' => $subtotal,
        'extras_total' => $extrasTotal,
        'discount_total' => $discountTotal,
        'tax_total' => $tax,
        'grand_total' => $grandTotal,
        'campaign_code' => $campaignCode ?: null,
        'extras' => !empty($extras) ? json_encode($extras, JSON_UNESCAPED_UNICODE) : null,
        'customer_note' => post('customer_note'),
        'status' => 'pending',
        'payment_status' => 'pending',
        'payment_method' => post('payment_method', 'cash_on_pickup'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
    ];

    // API rezervasyon ek alanları
    if ($isApiBooking) {
        $booking['api_provider_id'] = $apiProviderId;
        $booking['api_status'] = 'pending';
        $booking['api_external_pickup_id'] = $pickupExtId ?? null;
        $booking['api_external_dropoff_id'] = $pickupExtId ?? null;
        $booking['api_extra_data'] = json_encode([
            'cars_park_id' => $apiVehicle['external_id'] ?? '',
            'group_id' => $apiVehicle['group_id'] ?? '',
            'rez_id' => $apiVehicle['rez_id'] ?? '',
            'car_name' => $apiVehicle['full_name'] ?? '',
            'brand' => $apiVehicle['brand'] ?? '',
            'model' => $apiVehicle['model'] ?? '',
            'image' => $apiVehicle['image'] ?? '',
            'daily_price' => $apiVehicle['daily_price'] ?? 0,
            'fuel' => $apiVehicle['fuel_type'] ?? '',
            'transmission' => $apiVehicle['transmission'] ?? '',
        ], JSON_UNESCAPED_UNICODE);
    }

    $newId = db()->insert('bookings', $booking);
    log_activity('create', 'bookings', 'Yeni rezervasyon: ' . $booking['booking_code'] . ($isApiBooking ? ' (API)' : ''), $newId, 'booking');

    // === API'YE GÖNDER (test_mode kapalıysa) ===
    if ($isApiBooking) {
        $providerInst = ProviderFactory::createFromRow($apiProvider);
        $isTestMode = !isset($apiProvider['test_mode']) || (int)$apiProvider['test_mode'] === 1;

        if ($isTestMode) {
            // Test modu: API'ye GÖNDERME, sadece DB'de tut
            db()->update('bookings', [
                'api_status' => 'pending',
                'api_response' => json_encode(['test_mode' => true, 'message' => 'Test modu aktif, API\'ye gerçek post atılmadı.'], JSON_UNESCAPED_UNICODE),
            ], 'id = :id', ['id' => $newId]);

            error_log("Booking #$newId API rezervasyon TEST MODU — gerçek post atılmadı");
        } else {
            // GERÇEK MOD: API'ye post at
            try {
                $apiResult = $providerInst->createBooking([
                    'pickup_external_id' => $pickupExtId,
                    'return_external_id' => $pickupExtId,
                    'pickup_date' => $actualPickup,
                    'return_date' => $actualReturn,
                    'pickup_time' => date('H:i', strtotime($actualPickup)) ?: '10:00',
                    'return_time' => date('H:i', strtotime($actualReturn)) ?: '10:00',
                    'first_name' => post('first_name'),
                    'last_name' => post('last_name'),
                    'phone' => post('phone'),
                    'email' => post('email'),
                    'tc_or_id' => post('tc'),
                    'cars_park_id' => $apiVehicle['external_id'] ?? '',
                    'group_id' => $apiVehicle['group_id'] ?? '',
                    'rez_id' => $apiVehicle['rez_id'] ?? '',
                    'currency' => 'TL',
                    'flight_number' => post('flight_number', ''),
                    'address' => post('address', ''),
                    'city' => post('city', ''),
                    'country' => 'Turkey',
                    'our_booking_code' => $bookingCode,
                    'rent_price' => $subtotal,
                    'extra_price' => $extrasTotal,
                    'drop_price' => 0,
                    'payment_type' => 0, // 0 = ödeme alınmadı (ofiste/havale)
                ]);

                $updateData = [
                    'api_status' => $apiResult['success'] ? 'sent' : 'failed',
                    'api_reservation_id' => $apiResult['external_id'],
                    'api_response' => json_encode($apiResult, JSON_UNESCAPED_UNICODE),
                ];
                db()->update('bookings', $updateData, 'id = :id', ['id' => $newId]);

                if (!$apiResult['success']) {
                    error_log("Booking #$newId API'ye post başarısız: " . $apiResult['message']);
                    // Yine de DB'de kaldı, admin manuel müdahale yapabilir
                }
            } catch (Throwable $e) {
                db()->update('bookings', [
                    'api_status' => 'failed',
                    'api_response' => json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
                ], 'id = :id', ['id' => $newId]);
                error_log("Booking #$newId API exception: " . $e->getMessage());
            }
        }
    }

    // Kampanya kullanım sayacını artır
    if ($campaignCode && !empty($campaign)) {
        db()->query("UPDATE campaigns SET usage_count = usage_count + 1 WHERE id = ?", [$campaign['id']]);
    }

    redirect(url('confirmation?code=' . $booking['booking_code']));
}

include __DIR__ . '/includes/frontend/header.php';
?>

<section class="checkout-sec" style="background:var(--bg-soft); min-height:100vh; padding:40px 0;">
  <div class="container" style="max-width:1200px; margin:0 auto; padding:0 20px;">
    <h1 class="visually-hidden" style="position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0;">Rezervasyon Tamamla</h1>
    <div style="display:grid; grid-template-columns: 1fr 400px; gap:24px;" class="checkout-grid">

      <!-- FORM -->
      <form method="POST" id="checkoutForm">
        <?= csrf_field() ?>

        <?php if ($isApiBooking): ?>
          <!-- API Booking için gizli alanlar (POST sonrası kayıp olmasın) -->
          <input type="hidden" name="api_provider" value="<?= (int)$apiProviderId ?>">
          <input type="hidden" name="api_vehicle" value="<?= e($apiVehicleId) ?>">
          <input type="hidden" name="api_group" value="<?= e($apiGroupId) ?>">
          <input type="hidden" name="api_rez" value="<?= e($apiRezId) ?>">

          <?php
            // Site adı + firma adı
            $siteName = function_exists('setting') ? (setting('site_name') ?: 'Yolzz') : 'Yolzz';
            $partnerName = $apiVehicle['rental_company_name'] ?? '';
            $partnerLogo = $apiVehicle['rental_company_logo'] ?? '';
          ?>

          <?php if ($partnerName || $partnerLogo): ?>
          <!-- Bilgi banner: Markamız + iş ortağı -->
          <div class="checkout-card" style="background:#E3F2FD; border-left:4px solid #1d71b8; margin-bottom:16px; display:flex; align-items:center; gap:14px;">
            <?php if ($partnerLogo): ?>
              <img src="<?= upload_url($partnerLogo) ?>" alt="<?= e($partnerName) ?>" style="height:36px; max-width:120px; object-fit:contain; flex-shrink:0;">
            <?php endif ?>
            <p style="margin:0; font-size:14px; color:#0D47A1;">
              <strong><?= e($siteName) ?></strong> üzerinden
              <?php if ($partnerName): ?><strong><?= e($partnerName) ?></strong><?php else: ?>iş ortağımızdan<?php endif ?>
              araç kiralıyorsunuz. Rezervasyon sonrası onay için iletişime geçilecektir.
            </p>
          </div>
          <?php endif ?>

          <?php if (!empty($apiVehicle['deposit'])): ?>
          <!-- Depozito uyarısı (üst) -->
          <div class="checkout-card" style="background:#FFF8E1; border-left:4px solid #FFA000; margin-bottom:16px;">
            <div style="display:flex; align-items:center; gap:14px;">
              <div style="font-size:32px; flex-shrink:0;">🛡</div>
              <div style="flex:1;">
                <div style="font-size:11px; color:#E65100; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Depozito Bilgilendirmesi</div>
                <div style="margin-top:2px; font-size:14px; color:#5D4037;">
                  Araç tesliminde <strong style="color:#BF360C; font-size:16px;"><?= tl($apiVehicle['deposit']) ?></strong> depozito alınır.
                  Araç sorunsuz iadesinde tarafınıza geri ödenir.
                </div>
              </div>
            </div>
          </div>
          <?php endif ?>
        <?php endif ?>


        <!-- Rezervasyon Özeti (Alış/İade) -->
        <div class="checkout-card">
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <h2 style="margin:0;">📍 Rezervasyon Detayı</h2>
            <button type="button" onclick="document.getElementById('editDetails').classList.toggle('hidden'); this.textContent = this.textContent.includes('Düzenle') ? '✕ Kapat' : '✏ Düzenle'"
                    style="padding:8px 14px; background:transparent; border:1px solid var(--border); border-radius:8px; font-size:13px; color:var(--brand); cursor:pointer; font-family:inherit;">
              ✏ Düzenle
            </button>
          </div>

          <!-- Özet (her zaman görünür) -->
          <div class="reservation-summary" style="margin-top:16px;">
            <?php
              $pickupOfficeSel = null;
              // Önce URL'den gelen pickup_office varsa onu kullan (spesifik tedarikçi/ofis)
              if (!empty($pickupOfficeId)) {
                foreach ($offices as $o) {
                  if ($o['id'] == $pickupOfficeId) { $pickupOfficeSel = $o; break; }
                }
              }
              // Yoksa pickup_city'ye göre eşleşme ara (geriye uyumluluk)
              if (!$pickupOfficeSel && $pickupCityId) {
                foreach ($offices as $o) {
                  if ($o['city_id'] == $pickupCityId) { $pickupOfficeSel = $o; break; }
                }
              }
              // Bulunamadıysa: ilk aktif ofisi varsayılan yap
              if (!$pickupOfficeSel && !empty($offices)) {
                $pickupOfficeSel = $offices[0];
              }
              $pickupOfficeSelLabel = $pickupOfficeSel ? ($pickupOfficeSel['city_name'] . ' · ' . $pickupOfficeSel['name']) : 'Ofis seçilmedi';
            ?>
            <div class="rs-item">
              <div class="rs-icon">🚘</div>
              <div class="rs-info">
                <div class="rs-label">Alış</div>
                <div class="rs-value" id="rsPickupOffice"><?= e($pickupOfficeSelLabel) ?></div>
                <div class="rs-sub"><?= tr_date($pickupDate) ?> · <?= date('H:i', strtotime($pickupDate)) ?></div>
              </div>
            </div>
            <div class="rs-arrow">→</div>
            <div class="rs-item">
              <div class="rs-icon">🏁</div>
              <div class="rs-info">
                <div class="rs-label">İade</div>
                <div class="rs-value" id="rsReturnOffice"><?= e($pickupOfficeSelLabel) ?></div>
                <div class="rs-sub"><?= tr_date($returnDate) ?> · <?= date('H:i', strtotime($returnDate)) ?></div>
              </div>
            </div>
            <div class="rs-days-badge"><?= $days ?> gün</div>
          </div>

          <!-- Düzenleme bölümü (varsayılan gizli) -->
          <div id="editDetails" class="hidden" style="margin-top:20px; padding-top:20px; border-top:1px solid var(--border-soft);">
            <p style="font-size:12px; color:var(--text-muted); margin-bottom:12px;">
              ℹ️ Tarih veya ofis değiştirmek istersen aşağıdan düzenleyebilirsin.
            </p>
            <div class="form-grid-2">
              <div>
                <label>Alış Ofisi *</label>
                <select name="pickup_office_id" required class="co-input" id="pickupOfficeSelect">
                  <option value="">Seçin</option>
                  <?php foreach ($offices as $o): ?>
                    <option value="<?= $o['id'] ?>" <?= ($pickupOfficeSel && $o['id'] == $pickupOfficeSel['id']) ? 'selected' : '' ?>>
                      <?= e($o['city_name'] . ' - ' . $o['name']) ?>
                    </option>
                  <?php endforeach ?>
                </select>
              </div>
              <div>
                <label>Alış Tarihi *</label>
                <input type="datetime-local" name="pickup_datetime" value="<?= e(date('Y-m-d\TH:i', strtotime($pickupDate))) ?>" class="co-input" required>
              </div>
              <div>
                <label>İade Ofisi</label>
                <select name="return_office_id" class="co-input" id="returnOfficeSelect">
                  <option value="">Aynı ofis</option>
                  <?php foreach ($offices as $o): ?>
                    <option value="<?= $o['id'] ?>"><?= e($o['city_name'] . ' - ' . $o['name']) ?></option>
                  <?php endforeach ?>
                </select>
              </div>
              <div>
                <label>İade Tarihi *</label>
                <input type="datetime-local" name="return_datetime" value="<?= e(date('Y-m-d\TH:i', strtotime($returnDate))) ?>" class="co-input" required>
              </div>
            </div>
          </div>
        </div>

        <!-- Kişisel Bilgi -->
        <div class="checkout-card">
          <h2>👤 Kişisel Bilgileriniz</h2>
          <div class="form-grid-2">
            <div>
              <label>Ad *</label>
              <input type="text" name="first_name" class="co-input" required>
            </div>
            <div>
              <label>Soyad *</label>
              <input type="text" name="last_name" class="co-input" required>
            </div>
            <div>
              <label>E-posta *</label>
              <input type="email" name="email" class="co-input" required>
            </div>
            <div>
              <label>Telefon *</label>
              <input type="tel" name="phone" class="co-input" required placeholder="0532 123 45 67">
            </div>
            <div>
              <label>TC Kimlik No *</label>
              <input type="text" name="tc" class="co-input" required maxlength="11" pattern="[0-9]{11}">
            </div>
          </div>
        </div>

        <!-- Ek Hizmetler (açılır-kapanır) -->
        <div class="checkout-card collapse-card">
          <button type="button" class="collapse-head" data-target="extrasBody" aria-expanded="false">
            <span><h2 style="margin:0;">🎁 Ek Hizmetler</h2><small style="display:block; color:var(--text-muted); font-weight:400; margin-top:2px;">İhtiyacınız varsa açıp seçin</small></span>
            <svg class="collapse-arrow" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
          </button>
          <div class="collapse-body" id="extrasBody" hidden>
          <?php if ($isApiBooking && !empty($apiServices)): ?>
            <!-- API ARAÇ EKSTRA HİZMETLERİ -->
            <div class="extras-grid">
              <?php
              $iconMap = [
                'CDW' => '🛡', 'SCDW' => '🛡', 'LCF' => '🛡', 'PAI' => '⚕',
                'Baby_Seat' => '🍼', 'Navigation' => '🗺', 'Addition_Drive' => '👥',
                'Winter_Tire' => '❄', 'Young_Driver' => '👤', 'IMM' => '🔒',
                'Exemption' => '✓',
              ];
              foreach ($apiServices as $svcKey => $svc):
                $icon = $iconMap[$svcKey] ?? '✨';
              ?>
                <label class="extra-item">
                  <input type="checkbox" name="api_extras[]" value="<?= e($svcKey) ?>">
                  <div>
                    <div class="ex-name"><?= $icon ?> <?= e($svc['title']) ?></div>
                    <div class="ex-price"><?= tl($svc['price']) ?> toplam</div>
                  </div>
                </label>
              <?php endforeach ?>
            </div>
          <?php else: ?>
            <!-- LOKAL ARAÇ EKSTRALARI (mevcut) -->
            <div class="extras-grid">
              <label class="extra-item">
                <input type="checkbox" name="extras[baby_seat]" value="1">
                <div><div class="ex-name">🍼 Bebek Koltuğu</div><div class="ex-price">₺50 /gün</div></div>
              </label>
              <label class="extra-item">
                <input type="checkbox" name="extras[gps]" value="1">
                <div><div class="ex-name">🗺 Navigasyon</div><div class="ex-price">₺30 /gün</div></div>
              </label>
              <label class="extra-item">
                <input type="checkbox" name="extras[additional_driver]" value="1">
                <div><div class="ex-name">👥 Ek Sürücü</div><div class="ex-price">₺40 /gün</div></div>
              </label>
              <label class="extra-item">
                <input type="checkbox" name="extras[full_insurance]" value="1">
                <div><div class="ex-name">🛡 Tam Kasko</div><div class="ex-price">₺80 /gün</div></div>
              </label>
            </div>
          <?php endif ?>
          </div>
        </div>

        <!-- Kupon -->
        <?php if (!$isApiBooking): ?>
        <div class="checkout-card">
          <h2>🎟 Kupon Kodu</h2>
          <div style="display:flex; gap:8px;">
            <input type="text" name="coupon_code" class="co-input" placeholder="Kupon kodunuz">
            <button type="button" class="btn-outline-brand" onclick="alert('Kupon siparişte uygulanacak')">Uygula</button>
          </div>
        </div>
        <?php endif ?>

        <!-- Ödeme -->
        <div class="checkout-card">
          <h2>💳 Ödeme Yöntemi</h2>
          <div class="payment-methods">
            <label class="payment-method">
              <input type="radio" name="payment_method" value="cash_on_pickup" checked>
              <div>
                <div style="font-weight:600;">💵 Teslim Anında Nakit</div>
                <div style="font-size:12px; color:var(--text-muted);">Aracı teslim alırken ödeyin</div>
              </div>
            </label>
            <label class="payment-method">
              <input type="radio" name="payment_method" value="credit_card">
              <div>
                <div style="font-weight:600;">💳 Kredi Kartı (Online)</div>
                <div style="font-size:12px; color:var(--text-muted);">Güvenli SSL ile</div>
              </div>
            </label>
            <label class="payment-method">
              <input type="radio" name="payment_method" value="bank_transfer">
              <div>
                <div style="font-weight:600;">🏦 Havale / EFT</div>
                <div style="font-size:12px; color:var(--text-muted);">24 saat içinde onay</div>
              </div>
            </label>
          </div>
        </div>

        <!-- Not (açılır-kapanır) -->
        <div class="checkout-card collapse-card">
          <button type="button" class="collapse-head" data-target="noteBody" aria-expanded="false">
            <span><h2 style="margin:0;">📝 Notunuz</h2><small style="display:block; color:var(--text-muted); font-weight:400; margin-top:2px;">İsteğe bağlı — bize özel bir mesajınız varsa</small></span>
            <svg class="collapse-arrow" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
          </button>
          <div class="collapse-body" id="noteBody" hidden>
            <textarea name="customer_note" class="co-input" rows="3" placeholder="Bize iletmek istediğiniz özel bir not..."></textarea>
          </div>
        </div>

        <!-- Onay -->
        <div class="checkout-card">
          <label class="kvkk-row" style="margin-bottom:0">
            <input type="checkbox" required>
            <span class="kvkk-row-text">
              <a href="<?= url('kvkk') ?>" target="_blank">KVKK aydınlatma metnini</a> ve <a href="<?= url('kiralama-sartlari') ?>" target="_blank">kiralama şartlarını</a> okuduğumu, kabul ettiğimi beyan ederim.
            </span>
          </label>
        </div>

        <button type="submit" class="btn-checkout">
          🔒 Rezervasyonu Tamamla — <span id="btnTotalAmount"><?= $isApiBooking ? tl($subtotal) : tl($subtotal * 1.20) ?></span>
        </button>
      </form>

      <!-- SUMMARY -->
      <aside class="checkout-summary">
        <div class="cs-sticky">
          <div class="checkout-card">
            <h3>Rezervasyon Özeti</h3>

            <?php if ($isApiBooking): ?>
              <!-- API ARAÇ ÖZETİ -->
              <div class="summary-vehicle">
                <?php if (!empty($apiVehicle['image'])): ?>
                  <img src="<?= e($apiVehicle['image']) ?>" alt="<?= e($apiVehicle['full_name']) ?>" loading="lazy" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <?php endif ?>
                <div style="<?= empty($apiVehicle['image']) ? '' : 'display:none;' ?>width:100%; height:150px; background:var(--bg-soft); display:flex; align-items:center; justify-content:center; font-size:48px;">🚗</div>

                <?php if (!empty($apiVehicle['rental_company_logo'])): ?>
                  <div style="background:#fff; padding:6px 10px; border-radius:6px; box-shadow:0 2px 6px rgba(0,0,0,0.12); display:inline-block; margin:8px 0 4px 0;">
                    <img src="<?= upload_url($apiVehicle['rental_company_logo']) ?>" alt="<?= e($apiVehicle['rental_company_name']) ?>" style="height:18px; max-width:100px; object-fit:contain; display:block;">
                  </div>
                <?php elseif (!empty($apiVehicle['rental_company_name'])): ?>
                  <div style="background:#1d71b8; color:#fff; padding:4px 10px; border-radius:6px; font-size:11px; font-weight:700; display:inline-block; margin:8px 0 4px 0;">
                    <?= e($apiVehicle['rental_company_name']) ?>
                  </div>
                <?php else: ?>
                  <div style="background:#1d71b8; color:#fff; padding:4px 10px; border-radius:6px; font-size:11px; font-weight:700; display:inline-block; margin:8px 0 4px 0;">
                    <?= e($apiProvider['display_name'] ?: $apiProvider['name']) ?>
                  </div>
                <?php endif ?>

                <h4><?= e($apiVehicle['full_name']) ?></h4>
                <div style="font-size:12px; color:var(--text-muted); margin-bottom:10px;">
                  <?= e($apiVehicle['fuel_type']) ?> · <?= e($apiVehicle['transmission']) ?>
                </div>
                <div class="sv-features">
                  <span>👥 <?= (int)$apiVehicle['seats'] ?></span>
                  <span>🧳 <?= (int)$apiVehicle['luggage'] ?></span>
                  <?php if (!empty($apiVehicle['features']['km_limit'])): ?>
                    <span>📍 <?= e($apiVehicle['features']['km_limit']) ?> km</span>
                  <?php endif ?>
                </div>
              </div>

              <hr style="margin:16px 0; border:0; border-top:1px solid var(--border);">

              <div class="summary-row"><span>Günlük</span><strong><?= tl($apiVehicle['daily_price']) ?></strong></div>
              <div class="summary-row"><span>Gün Sayısı</span><strong><?= $days ?></strong></div>
              <div class="summary-row"><span>Ara Toplam</span><strong><?= tl($subtotal) ?></strong></div>

              <!-- Canlı eklenen ek hizmetler buraya gelir -->
              <div id="apiExtrasLines"></div>

              <hr style="margin:16px 0; border:0; border-top:1px solid var(--border);">

              <div class="summary-total">
                <span>TOPLAM</span>
                <strong id="apiTotalAmount" data-base="<?= (float)$subtotal ?>"><?= tl($subtotal) ?></strong>
              </div>

              <?php if (!empty($apiVehicle['deposit'])): ?>
                <div style="margin-top:12px; padding:12px 14px; background:#FFF3E0; border:2px solid #FFB74D; border-radius:8px; text-align:center;">
                  <div style="font-size:11px; color:#E65100; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">
                    🛡 Depozito (İade Edilecek)
                  </div>
                  <div style="font-size:18px; color:#BF360C; font-weight:800;">
                    <?= tl($apiVehicle['deposit']) ?>
                  </div>
                </div>
              <?php endif ?>

            <?php else: ?>
              <!-- LOKAL ARAÇ ÖZETİ (mevcut) -->
              <div class="summary-vehicle">
                <?php if ($vehicle['main_image']): ?>
                  <img src="<?= upload_url($vehicle['main_image']) ?>" alt="<?= e($vehicle['model']) ?>" loading="lazy">
                <?php else: ?>
                  <div style="width:100%; height:150px; background:var(--bg-soft); display:flex; align-items:center; justify-content:center; font-size:48px;">🚗</div>
                <?php endif ?>
                <h4><?= e($vehicle['brand_name'] . ' ' . $vehicle['model']) ?></h4>
                <div style="font-size:12px; color:var(--text-muted); margin-bottom:10px;">
                  <?= $vehicle['year'] ?> · <?= e($vehicle['fuel_type']) ?> · <?= e($vehicle['transmission']) ?>
                </div>
                <div class="sv-features">
                  <span>👥 <?= $vehicle['seats'] ?></span>
                  <span>🧳 <?= $vehicle['luggage'] ?></span>
                  <span>🚪 <?= $vehicle['doors'] ?></span>
                </div>
              </div>

              <hr style="margin:16px 0; border:0; border-top:1px solid var(--border);">

              <div class="summary-row"><span>Günlük</span><strong><?= tl($vehicle['daily_price']) ?></strong></div>
              <div class="summary-row"><span>Gün Sayısı</span><strong><?= $days ?></strong></div>
              <?php if ($vehicle['discount_percent'] > 0): ?>
                <div class="summary-row" style="color:var(--success);">
                  <span>İndirim (%<?= $vehicle['discount_percent'] ?>)</span>
                  <strong>-<?= tl(($vehicle['daily_price'] * $days) - $subtotal) ?></strong>
                </div>
              <?php endif ?>
              <div class="summary-row"><span>Ara Toplam</span><strong><?= tl($subtotal) ?></strong></div>
              <div class="summary-row" style="color:var(--text-muted); font-size:12px;">
                <span>KDV (%20)</span><strong><?= tl($subtotal * 0.20) ?></strong>
              </div>

              <hr style="margin:16px 0; border:0; border-top:1px solid var(--border);">

              <div class="summary-total">
                <span>TOPLAM</span>
                <strong><?= tl($subtotal * 1.20) ?></strong>
              </div>
            <?php endif ?>

            <div style="margin-top:16px; padding:10px; background:var(--brand-wash); border-radius:8px; font-size:11px; color:var(--brand); text-align:center;">
              🔒 Güvenli Rezervasyon
            </div>
          </div>
        </div>
      </aside>
    </div>
  </div>
</section>

<style>
.checkout-grid { display: grid; }
@media (max-width: 991px) { .checkout-grid { grid-template-columns: 1fr !important; } }
.checkout-card { background: #fff; border: 1px solid var(--border); border-radius: 16px; padding: 24px; margin-bottom: 16px; }
.checkout-card h2, .checkout-card h3 { font-size: 16px; color: var(--ink); margin-bottom: 16px; font-weight: 700; }
.checkout-card h3 { font-size: 15px; }
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 600px) { .form-grid-2 { grid-template-columns: 1fr; } }
.form-grid-2 label, .checkout-card > label { display: block; font-size: 12px; font-weight: 600; color: var(--ink); margin-bottom: 4px; }
.co-input { width: 100%; padding: 12px 14px; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; font-size: 14px; background: #fff; }
.co-input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-wash); }
.extras-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
@media (max-width: 600px) { .extras-grid { grid-template-columns: 1fr; } }
.extra-item { display: flex; gap: 10px; padding: 14px; border: 1px solid var(--border); border-radius: 10px; cursor: pointer; transition: all 0.2s; align-items: center; }
.extra-item:has(input:checked) { border-color: var(--brand); background: var(--brand-wash); }
.extra-item input { accent-color: var(--brand); }
.ex-name { font-size: 13px; font-weight: 600; color: var(--ink); }
.ex-price { font-size: 11px; color: var(--accent); font-weight: 600; }
.payment-methods { display: flex; flex-direction: column; gap: 8px; }
.payment-method { display: flex; gap: 12px; padding: 14px 16px; border: 1px solid var(--border); border-radius: 10px; cursor: pointer; align-items: center; transition: all 0.2s; }
.payment-method:has(input:checked) { border-color: var(--brand); background: var(--brand-wash); }
.payment-method input { accent-color: var(--brand); }
.btn-checkout { width: 100%; padding: 18px; background: var(--accent); color: #fff; border: 0; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; box-shadow: var(--shadow-accent); transition: all 0.2s; }
.btn-checkout:hover { background: var(--accent-dark); transform: translateY(-2px); }

.cs-sticky { position: sticky; top: 80px; }
.summary-vehicle { text-align: center; }
.summary-vehicle img { width: 100%; height: 150px; object-fit: cover; border-radius: 10px; margin-bottom: 12px; }
.summary-vehicle h4 { font-size: 16px; color: var(--ink); margin-bottom: 4px; }
.sv-features { display: flex; justify-content: center; gap: 10px; font-size: 12px; color: var(--text); background: var(--bg-soft); padding: 8px; border-radius: 8px; }
.summary-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; color: var(--text); }
.summary-total { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; }
.summary-total span { font-size: 14px; color: var(--text-muted); font-weight: 600; }
.summary-total strong { font-size: 24px; color: var(--accent); font-weight: 800; }
</style>

<script>
// Alış & İade ofisi değişince özette güncelle
(function(){
  const pickupSel = document.getElementById('pickupOfficeSelect');
  const returnSel = document.getElementById('returnOfficeSelect');
  const rsPickupOffice = document.getElementById('rsPickupOffice');
  const rsReturnOffice = document.getElementById('rsReturnOffice');
  if (!pickupSel || !rsPickupOffice || !rsReturnOffice) return;

  function getLabel(selectEl) {
    const idx = selectEl.selectedIndex;
    if (idx < 0) return '';
    const text = (selectEl.options[idx].text || '').trim();
    if (!text || text === 'Aynı ofis' || text === 'Seçin') return '';
    return text.replace(' - ', ' · ');
  }

  function syncPickup() {
    const label = getLabel(pickupSel);
    if (label) {
      rsPickupOffice.textContent = label;
      // Return boş ise (Aynı ofis) — return görünümü de pickup'la aynı olsun
      if (!returnSel.value) rsReturnOffice.textContent = label;
    }
  }
  function syncReturn() {
    const returnLabel = getLabel(returnSel);
    if (returnLabel) {
      rsReturnOffice.textContent = returnLabel;
    } else {
      // Return boş (aynı ofis) → pickup label'i kullan
      const pickupLabel = getLabel(pickupSel);
      if (pickupLabel) rsReturnOffice.textContent = pickupLabel;
    }
  }

  pickupSel.addEventListener('change', syncPickup);
  returnSel.addEventListener('change', syncReturn);

  // İlk yüklemede bir kez senkron et (sayfa açıldığında varsayılan seçim yansısın)
  syncPickup();
  syncReturn();
})();

// === API EK HİZMETLER CANLI TOPLAM ===
(function(){
  const totalEl = document.getElementById('apiTotalAmount');
  if (!totalEl) return; // Lokal araçta yok

  const baseAmount = parseFloat(totalEl.dataset.base) || 0;
  const linesContainer = document.getElementById('apiExtrasLines');
  const btnTotal = document.getElementById('btnTotalAmount');
  const checkboxes = document.querySelectorAll('input[name="api_extras[]"]');

  function formatTl(amount) {
    return '₺' + amount.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function recalc() {
    let extrasTotal = 0;
    let linesHtml = '';
    checkboxes.forEach(cb => {
      if (cb.checked) {
        const label = cb.closest('label').querySelector('.ex-name')?.textContent.trim() || '';
        const priceText = cb.closest('label').querySelector('.ex-price')?.textContent.trim() || '';
        // "₺3.720,00 toplam" gibi → sayıyı çıkar
        const priceMatch = priceText.replace(/\./g, '').replace(',', '.').match(/[\d.]+/);
        const price = priceMatch ? parseFloat(priceMatch[0]) : 0;
        extrasTotal += price;
        linesHtml += '<div class="summary-row" style="font-size:12px; color:#1d71b8;">'
          + '<span>+ ' + label + '</span><strong>' + formatTl(price) + '</strong></div>';
      }
    });

    if (linesContainer) linesContainer.innerHTML = linesHtml;

    const grandTotal = baseAmount + extrasTotal;
    totalEl.textContent = formatTl(grandTotal);
    if (btnTotal) btnTotal.textContent = formatTl(grandTotal);
  }

  checkboxes.forEach(cb => cb.addEventListener('change', recalc));
})();
</script>

<!-- ========== COLLAPSIBLE (Ek Hizmetler + Notunuz) ========== -->
<style>
.collapse-card { padding: 0 !important; overflow: hidden; }
.collapse-head {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px;
  background: transparent;
  border: 0;
  cursor: pointer;
  text-align: left;
  font: inherit;
  color: inherit;
  transition: background 0.15s;
}
.collapse-head:hover { background: rgba(0,0,0,0.02); }
.collapse-head h2 { margin: 0; font-size: 17px; }
.collapse-arrow {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  transition: transform 0.25s;
  color: var(--text-muted, #888);
}
.collapse-head[aria-expanded="true"] .collapse-arrow { transform: rotate(180deg); }
.collapse-body {
  padding: 0 20px 20px 20px;
  animation: slideDown 0.25s ease-out;
}
.collapse-body[hidden] { display: none; }
@keyframes slideDown {
  from { opacity: 0; transform: translateY(-6px); }
  to   { opacity: 1; transform: translateY(0); }
}
</style>

<script>
(function(){
  document.querySelectorAll('.collapse-head').forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.dataset.target;
      const body = document.getElementById(targetId);
      if (!body) return;
      const isOpen = !body.hidden;
      body.hidden = isOpen;
      btn.setAttribute('aria-expanded', String(!isOpen));
    });
  });
})();
</script>

<?php include __DIR__ . '/includes/frontend/footer.php'; ?>
