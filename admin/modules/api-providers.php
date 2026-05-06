<?php
$page_title = 'API Sağlayıcıları';
$active_menu = 'api-providers';

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/providers/ProviderFactory.php';
require_once __DIR__ . '/../../includes/providers/ProviderOrchestrator.php';
require_once __DIR__ . '/../../includes/providers/ProviderHttpClient.php';

require_auth();

$action = get('action');
$id = (int)get('id', 0);

// =====================================================================
// REDIRECT-BASED ACTIONS (form/link sonrası geri dönenler)
// =====================================================================

// Bağlantı testi
if ($action === 'test' && $id) {
    $provider = ProviderFactory::createFromId($id);
    if (!$provider) {
        flash_error('Sağlayıcı bulunamadı.');
    } else {
        $result = $provider->testConnection();
        if ($result['success']) {
            flash_success('Bağlantı başarılı: ' . $result['message']);
        } else {
            flash_error('Bağlantı başarısız: ' . $result['message']);
        }
    }
    redirect(admin_url('modules/api-providers.php'));
}

// Lokasyonları API'den çek, mapping tablosuna yaz
if ($action === 'sync_locations' && $id) {
    $provider = ProviderFactory::createFromId($id);
    if (!$provider) {
        flash_error('Sağlayıcı bulunamadı.');
        redirect(admin_url('modules/api-providers.php'));
    }
    $locs = $provider->getLocations();
    $added = 0;
    $updated = 0;
    foreach ($locs as $loc) {
        if (empty($loc['external_id'])) continue;
        $existing = db()->fetch(
            "SELECT id FROM api_location_map WHERE api_provider_id = ? AND external_id = ?",
            [$id, $loc['external_id']]
        );
        $data = [
            'api_provider_id' => $id,
            'external_id' => $loc['external_id'],
            'external_name' => $loc['name'],
            'last_synced_at' => date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            db()->update('api_location_map', $data, 'id = :id', ['id' => $existing['id']]);
            $updated++;
        } else {
            db()->insert('api_location_map', $data);
            $added++;
        }
    }
    flash_success("Lokasyon senkronizasyonu: $added yeni, $updated güncellendi.");
    redirect(admin_url('modules/api-providers.php?action=locations&id=' . $id));
}

// Aktiflik toggle
if ($action === 'toggle' && $id) {
    $p = db()->fetch("SELECT is_active FROM api_providers WHERE id = ?", [$id]);
    if ($p) {
        db()->update('api_providers', ['is_active' => !$p['is_active']], 'id = :id', ['id' => $id]);
        flash_success('Durum güncellendi.');
    }
    redirect(admin_url('modules/api-providers.php'));
}

// Sil
if ($action === 'delete' && $id) {
    db()->query("DELETE FROM api_providers WHERE id = ?", [$id]);
    flash_success('Sağlayıcı silindi.');
    redirect(admin_url('modules/api-providers.php'));
}

// Cache temizle
if ($action === 'clear_cache') {
    $orch = new ProviderOrchestrator();
    $orch->clearCache();
    flash_success('Cache temizlendi.');
    redirect(admin_url('modules/api-providers.php'));
}

// Feature flag toggle
if ($action === 'toggle_feature') {
    $current = db()->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'api_providers_enabled'");
    $new = ($current === '1') ? '0' : '1';
    $exists = db()->fetch("SELECT id FROM settings WHERE setting_key = 'api_providers_enabled'");
    if ($exists) {
        db()->update('settings', ['setting_value' => $new], 'setting_key = :k', ['k' => 'api_providers_enabled']);
    } else {
        db()->insert('settings', [
            'group_name' => 'api',
            'setting_key' => 'api_providers_enabled',
            'setting_value' => $new,
            'setting_type' => 'boolean',
            'label' => 'API Sağlayıcı Entegrasyonu Aktif',
        ]);
    }
    flash_success('API entegrasyonu ' . ($new === '1' ? 'AÇILDI' : 'KAPATILDI'));
    redirect(admin_url('modules/api-providers.php'));
}

// =====================================================================
// VIEW ACTIONS (sayfa render eden)
// =====================================================================

// LOKASYON DEBUG
if ($action === 'debug' && $id) {
    $provider = ProviderFactory::createFromId($id);
    if (!$provider) {
        flash_error('Sağlayıcı bulunamadı.');
        redirect(admin_url('modules/api-providers.php'));
    }
    $providerRow = db()->fetch("SELECT * FROM api_providers WHERE id = ?", [$id]);
    $authConfig = json_decode($providerRow['auth_config'], true) ?: [];

    $http = new ProviderHttpClient(['timeout' => 10]);
    $url = rtrim($providerRow['base_url'], '/') . '/JsonLocations.aspx';
    $params = [
        'Key_Hack' => $authConfig['key_hack'] ?? '',
        'User_Name' => $authConfig['user_name'] ?? '',
        'User_Pass' => $authConfig['user_pass'] ?? '',
    ];
    $resp = $http->get($url, $params);
    $parsed = $provider->getLocations();

    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="page-header">
      <div class="page-header-left">
        <h2>Lokasyon Debug: <?= e($providerRow['name']) ?></h2>
        <div class="subtitle">JsonLocations.aspx — ham yanıt + parse</div>
      </div>
      <a href="<?= admin_url('modules/api-providers.php') ?>" class="btn btn-outline">← Geri</a>
    </div>

    <div class="card" style="padding: 20px; margin-bottom: 16px;">
      <h3>HTTP Yanıt</h3>
      <p>
        <strong>Status:</strong> <?= (int)$resp['status'] ?> |
        <strong>Süre:</strong> <?= (int)$resp['duration_ms'] ?>ms |
        <strong>OK:</strong> <?= $resp['ok'] ? '✅' : '❌' ?>
      </p>
      <?php if (!empty($resp['error'])): ?>
        <p style="color:red;"><strong>Hata:</strong> <?= e($resp['error']) ?></p>
      <?php endif; ?>
    </div>

    <div class="card" style="padding: 20px; margin-bottom: 16px;">
      <h3>Ham JSON</h3>
      <pre style="background:#F5F5F5;padding:10px;border-radius:6px;font-size:11px;max-height:400px;overflow:auto;"><?= e(mb_substr($resp['body'] ?? '(boş)', 0, 5000)) ?></pre>
    </div>

    <div class="card" style="padding: 20px; margin-bottom: 16px;">
      <h3>Parse Edilmiş Lokasyonlar (<?= count($parsed) ?>)</h3>
      <pre style="background:#F5F5F5;padding:10px;border-radius:6px;font-size:11px;max-height:400px;overflow:auto;"><?= e(json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

// ARAÇ SORGU DEBUG
if ($action === 'debug_search' && $id) {
    $provider = ProviderFactory::createFromId($id);
    if (!$provider) {
        flash_error('Sağlayıcı bulunamadı.');
        redirect(admin_url('modules/api-providers.php'));
    }
    $providerRow = db()->fetch("SELECT * FROM api_providers WHERE id = ?", [$id]);
    $authConfig = json_decode($providerRow['auth_config'], true) ?: [];

    $mappings = db()->fetchAll("
        SELECT m.*, o.name AS office_name, c.name AS city_name
        FROM api_location_map m
        LEFT JOIN offices o ON o.id = m.local_office_id
        LEFT JOIN cities c ON c.id = o.city_id
        WHERE m.api_provider_id = ?
        ORDER BY m.external_name
    ", [$id]);

    $selectedExtId = post('external_id') ?: get('external_id') ?: ($mappings[0]['external_id'] ?? '');
    $pickupDate = post('pickup_date') ?: date('Y-m-d', strtotime('+1 day'));
    $returnDate = post('return_date') ?: date('Y-m-d', strtotime('+4 days'));
    $pickupTime = post('pickup_time') ?: '10:00';
    $returnTime = post('return_time') ?: '10:00';

    $rawResponse = null;
    $parsedResults = [];
    $sentParams = [];
    $apiUrl = '';
    $duration = 0;
    $hasQuery = ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['external_id']));

    if ($hasQuery && $selectedExtId) {
        $http = new ProviderHttpClient(['timeout' => 15]);
        $apiUrl = rtrim($providerRow['base_url'], '/') . '/' . ltrim($providerRow['endpoint_availability'] ?? 'JsonRez.aspx', '/');

        $p = strtotime($pickupDate);
        $r = strtotime($returnDate);

        $sentParams = [
            'Key_Hack' => $authConfig['key_hack'] ?? '',
            'User_Name' => $authConfig['user_name'] ?? '',
            'User_Pass' => $authConfig['user_pass'] ?? '',
            'Pickup_ID' => (int)$selectedExtId,
            'Drop_Off_ID' => (int)$selectedExtId,
            'Pickup_Day' => (int)date('d', $p),
            'Pickup_Month' => (int)date('m', $p),
            'Pickup_Year' => (int)date('Y', $p),
            'Drop_Off_Day' => (int)date('d', $r),
            'Drop_Off_Month' => (int)date('m', $r),
            'Drop_Off_Year' => (int)date('Y', $r),
            'Pickup_Hour' => (int)substr($pickupTime, 0, 2),
            'Pickup_Min' => (int)substr($pickupTime, 3, 2),
            'Drop_Off_Hour' => (int)substr($returnTime, 0, 2),
            'Drop_Off_Min' => (int)substr($returnTime, 3, 2),
            'Currency' => 'TL',
        ];

        $t0 = microtime(true);
        $rawResponse = $http->get($apiUrl, $sentParams);
        $duration = (int)((microtime(true) - $t0) * 1000);

        $parsedResults = $provider->searchVehicles([
            'pickup_date' => $pickupDate,
            'return_date' => $returnDate,
            'pickup_time' => $pickupTime,
            'return_time' => $returnTime,
            'pickup_external_id' => $selectedExtId,
            'return_external_id' => $selectedExtId,
            'currency' => 'TL',
        ]);
    }

    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="page-header">
      <div class="page-header-left">
        <h2>Araç Sorgu Debug: <?= e($providerRow['name']) ?></h2>
        <div class="subtitle">JsonRez.aspx — müsait araç sorgusu</div>
      </div>
      <a href="<?= admin_url('modules/api-providers.php') ?>" class="btn btn-outline">← Geri</a>
    </div>

    <form method="POST" class="card" style="padding: 20px; margin-bottom: 16px;">
      <h3>Sorgu Parametreleri</h3>

      <?php if (empty($mappings)): ?>
        <p style="color:#C62828;">⚠️ Bu sağlayıcı için lokasyon eşleştirmesi yok. Önce lokasyonları çekmen ve eşleştirmen lazım.</p>
      <?php else: ?>
      <div style="margin: 12px 0;">
        <label>Lokasyon (eşleşmiş)</label>
        <select name="external_id" class="form-select">
          <?php foreach ($mappings as $m): ?>
            <option value="<?= e($m['external_id']) ?>" <?= $m['external_id'] === $selectedExtId ? 'selected' : '' ?>>
              <?= e($m['external_name']) ?> (ID: <?= e($m['external_id']) ?>)
              <?= $m['office_name'] ? '→ ' . e($m['city_name']) . ' / ' . e($m['office_name']) : '— EŞLEŞMEMİŞ' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div>
          <label>Alış Tarihi</label>
          <input type="date" name="pickup_date" class="form-input" value="<?= e($pickupDate) ?>">
        </div>
        <div>
          <label>İade Tarihi</label>
          <input type="date" name="return_date" class="form-input" value="<?= e($returnDate) ?>">
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div>
          <label>Alış Saati</label>
          <input type="time" name="pickup_time" class="form-input" value="<?= e($pickupTime) ?>">
        </div>
        <div>
          <label>İade Saati</label>
          <input type="time" name="return_time" class="form-input" value="<?= e($returnTime) ?>">
        </div>
      </div>
      <button type="submit" class="btn btn-primary">🔍 Sorgu Yap</button>
      <?php endif; ?>
    </form>

    <?php if ($rawResponse): ?>
    <div class="card" style="padding: 20px; margin-bottom: 16px;">
      <h3>Atılan İstek</h3>
      <p><strong>URL:</strong> <code style="font-size:11px;word-break:break-all;"><?= e($apiUrl) ?></code></p>
      <p><strong>Tam URL (tarayıcıda açıp test edebilirsin):</strong></p>
      <textarea readonly style="width:100%;height:80px;font-family:monospace;font-size:11px;padding:8px;"><?= e($apiUrl . '?' . http_build_query($sentParams)) ?></textarea>
    </div>

    <div class="card" style="padding: 20px; margin-bottom: 16px;">
      <h3>HTTP Yanıt</h3>
      <p>
        <strong>Status:</strong> <?= (int)$rawResponse['status'] ?> |
        <strong>Süre:</strong> <?= (int)$duration ?>ms |
        <strong>OK:</strong> <?= $rawResponse['ok'] ? '✅' : '❌' ?>
      </p>
      <?php if (!empty($rawResponse['error'])): ?>
        <p style="color:red;"><strong>Hata:</strong> <?= e($rawResponse['error']) ?></p>
      <?php endif; ?>
    </div>

    <div class="card" style="padding: 20px; margin-bottom: 16px;">
      <h3>Ham JSON Yanıt</h3>
      <pre style="background:#F5F5F5;padding:10px;border-radius:6px;font-size:11px;max-height:400px;overflow:auto;"><?= e(mb_substr($rawResponse['body'] ?? '(boş)', 0, 8000)) ?></pre>
    </div>

    <div class="card" style="padding: 20px; margin-bottom: 16px;">
      <h3>Parse Edilmiş Araçlar (<?= count($parsedResults) ?> adet)</h3>
      <?php if (empty($parsedResults)): ?>
        <div style="background:#FFEBEE;padding:14px;border-radius:6px;margin-bottom:12px;">
          <strong>⚠️ Hiç araç parse edilemedi!</strong><br><br>
          Olası sebepler:
          <ul style="margin:6px 0 0 20px;">
            <li>O tarih/lokasyonda gerçekten araç yok</li>
            <li>Field isimleri PDF'tekinden farklı</li>
            <li>JSON sarmalayıcı yanlış parse ediliyor</li>
          </ul>
        </div>

        <?php
        $json = $rawResponse['json'] ?? null;
        if (is_array($json)) {
            echo '<p><strong>JSON Yapısı Analizi:</strong></p><ul>';
            $isList = (array_keys($json) === range(0, count($json) - 1));
            echo '<li>Tip: ' . ($isList ? ('Düz dizi (' . count($json) . ' eleman)') : 'Object') . '</li>';
            echo '<li>Üst seviye anahtarlar: <code>' . e(implode(', ', array_keys($json))) . '</code></li>';
            $first = reset($json);
            if (is_array($first)) {
                echo '<li>İlk öğenin anahtarları: <code style="color:#1976D2;">' . e(implode(', ', array_keys($first))) . '</code></li>';
                echo '<li>İlk öğe örneği:</li></ul>';
                echo '<pre style="background:#E3F2FD;padding:10px;border-radius:6px;font-size:11px;">' . e(json_encode($first, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
            } else {
                echo '<li>İlk öğe array değil — yanıt boş veya hata mesajı</li></ul>';
            }
        } else {
            echo '<p style="color:red;">JSON parse edilemedi (geçerli JSON değil)</p>';
        }
        ?>
      <?php else: ?>
        <p>İlk 3 araç:</p>
        <pre style="background:#E8F5E9;padding:10px;border-radius:6px;font-size:11px;max-height:400px;overflow:auto;"><?= e(json_encode(array_slice($parsedResults, 0, 3), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

// API FİRMA EŞLEŞTİRME
if ($action === 'companies' && $id) {
    $provider = db()->fetch("SELECT * FROM api_providers WHERE id = ?", [$id]);
    if (!$provider) {
        flash_error('Sağlayıcı bulunamadı.');
        redirect(admin_url('modules/api-providers.php'));
    }

    // Yeni mapping ekleme
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('add_mapping')) {
        $extId = trim(post('external_id'));
        $extName = trim(post('external_name'));
        $companyId = (int)post('rental_company_id');
        if ($extId === '') {
            flash_error('External ID zorunludur.');
        } else {
            $exists = db()->fetch("SELECT id FROM api_company_map WHERE api_provider_id = ? AND external_id = ?", [$id, $extId]);
            if ($exists) {
                flash_error("Bu External ID zaten mevcut: $extId");
            } else {
                db()->insert('api_company_map', [
                    'api_provider_id' => $id,
                    'external_id' => $extId,
                    'external_name' => $extName ?: null,
                    'rental_company_id' => $companyId ?: null,
                ]);
                flash_success('Eşleştirme eklendi.');
            }
        }
        redirect(admin_url('modules/api-providers.php?action=companies&id=' . $id));
    }

    // Toplu güncelleme
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('save_mappings')) {
        $map = post('map') ?? [];
        $names = post('name') ?? [];
        foreach ($map as $mapId => $companyId) {
            db()->update('api_company_map', [
                'rental_company_id' => $companyId ? (int)$companyId : null,
                'external_name' => $names[$mapId] ?? null,
            ], 'id = :id AND api_provider_id = :p', ['id' => (int)$mapId, 'p' => $id]);
        }
        flash_success('Eşleştirmeler kaydedildi.');
        redirect(admin_url('modules/api-providers.php?action=companies&id=' . $id));
    }

    // Mapping silme
    if ($action === 'companies' && get('delete_map')) {
        $mapId = (int)get('delete_map');
        db()->query("DELETE FROM api_company_map WHERE id = ? AND api_provider_id = ?", [$mapId, $id]);
        flash_success('Eşleştirme silindi.');
        redirect(admin_url('modules/api-providers.php?action=companies&id=' . $id));
    }

    $mappings = db()->fetchAll("
        SELECT m.*, c.name AS company_name, c.logo AS company_logo
        FROM api_company_map m
        LEFT JOIN rental_companies c ON c.id = m.rental_company_id
        WHERE m.api_provider_id = ?
        ORDER BY m.external_id + 0
    ", [$id]);

    $companies = db()->fetchAll("SELECT id, name, logo FROM rental_companies WHERE is_active = 1 ORDER BY sort_order, name");

    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="page-header">
      <div class="page-header-left">
        <h2>API Firma Eşleştirme: <?= e($provider['name']) ?></h2>
        <div class="subtitle">API'den gelen <code>cars_park_id</code> gibi numaraları sistemdeki firmalarla eşleştir</div>
      </div>
      <a href="<?= admin_url('modules/api-providers.php') ?>" class="btn btn-outline">← Geri</a>
    </div>

    <?php if (empty($companies)): ?>
      <div class="card" style="padding: 24px; background: #FFF3E0; border-left: 4px solid #E65100;">
        <strong>⚠️ Henüz firma yok.</strong> Önce <a href="<?= admin_url('modules/rental-companies.php') ?>">Firmalar</a> sayfasından firma (Avis, Europcar vb.) ekle, sonra buradan eşleştirmeyi yap.
      </div>
    <?php endif ?>

    <!-- YENİ EŞLEŞTİRME EKLE -->
    <form method="POST" class="card" style="padding: 20px; margin-bottom: 20px;">
      <?= csrf_field() ?>
      <input type="hidden" name="add_mapping" value="1">
      <h3 style="margin-bottom: 14px;">+ Yeni Eşleştirme Ekle</h3>
      <p style="color: #666; font-size: 13px; margin-bottom: 14px;">
        API sağlayıcıdan öğrendiğin <code>cars_park_id</code> numarasını ve karşılık geldiği firmayı buraya ekle.
        Örnek: Sağlayıcı "23 = Europcar" dediyse → External ID: 23, Firma: Europcar
      </p>
      <div style="display:grid; grid-template-columns: 150px 1fr 1.5fr auto; gap:12px; align-items:end;">
        <div>
          <label class="form-label" style="font-size: 12px;">External ID *</label>
          <input type="text" name="external_id" class="form-input" placeholder="Örn: 23" required>
        </div>
        <div>
          <label class="form-label" style="font-size: 12px;">Açıklama (opsiyonel)</label>
          <input type="text" name="external_name" class="form-input" placeholder="Sağlayıcının kullandığı isim">
        </div>
        <div>
          <label class="form-label" style="font-size: 12px;">Firma *</label>
          <select name="rental_company_id" class="form-select" required>
            <option value="">— Seçiniz —</option>
            <?php foreach ($companies as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">+ Ekle</button>
      </div>
    </form>

    <!-- MEVCUT EŞLEŞTİRMELER -->
    <?php if (!empty($mappings)): ?>
    <form method="POST" class="card" style="padding: 0;">
      <?= csrf_field() ?>
      <input type="hidden" name="save_mappings" value="1">
      <table class="table">
        <thead>
          <tr>
            <th style="width: 100px;">External ID</th>
            <th>Açıklama</th>
            <th>Firma</th>
            <th style="width: 80px;">Logo</th>
            <th style="width: 70px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($mappings as $m): ?>
            <tr>
              <td><strong><code><?= e($m['external_id']) ?></code></strong></td>
              <td>
                <input type="text" name="name[<?= (int)$m['id'] ?>]" class="form-input" value="<?= e($m['external_name']) ?>" placeholder="—">
              </td>
              <td>
                <select name="map[<?= (int)$m['id'] ?>]" class="form-select">
                  <option value="">— Eşleşmemiş —</option>
                  <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $m['rental_company_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                  <?php endforeach ?>
                </select>
              </td>
              <td>
                <?php if ($m['company_logo']): ?>
                  <img src="<?= upload_url($m['company_logo']) ?>" style="max-width: 60px; max-height: 30px; object-fit: contain;">
                <?php else: ?>
                  <span style="color: #ccc;">—</span>
                <?php endif ?>
              </td>
              <td>
                <a href="<?= admin_url('modules/api-providers.php?action=companies&id=' . $id . '&delete_map=' . $m['id']) ?>"
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('Bu eşleştirme silinsin mi?')">Sil</a>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
      <div style="padding: 16px;">
        <button type="submit" class="btn btn-primary">💾 Değişiklikleri Kaydet</button>
      </div>
    </form>
    <?php else: ?>
      <div class="card" style="padding: 40px; text-align: center;">
        <p style="font-size: 16px; margin-bottom: 6px;">Henüz eşleştirme yok.</p>
        <p style="color: #666; font-size: 13px;">Yukarıdaki formdan ilk eşleştirmeyi ekle.</p>
      </div>
    <?php endif ?>

    <div class="card" style="padding: 16px; margin-top: 20px; background: #E3F2FD; border-left: 4px solid #1d71b8;">
      <strong>💡 Nasıl Çalışır?</strong>
      <ul style="margin: 8px 0 0 20px; font-size: 13px; line-height: 1.7;">
        <li>API'den gelen her aracın bir <code>cars_park_id</code> numarası vardır</li>
        <li>Sağlayıcıdan hangi numara hangi firmaya ait öğren ve buraya gir</li>
        <li>Eşleştirme yaptığın araçlar listede ilgili firmanın LOGOSU ile gösterilir</li>
        <li>Eşleşmemiş araçlar fallback olarak provider rozeti ("Rentline" vs.) gösterir</li>
      </ul>
    </div>

    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

// LOKASYON EŞLEŞTİRME EKRANI
if ($action === 'locations' && $id) {
    $provider = db()->fetch("SELECT * FROM api_providers WHERE id = ?", [$id]);
    if (!$provider) {
        flash_error('Sağlayıcı bulunamadı.');
        redirect(admin_url('modules/api-providers.php'));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('save_mapping')) {
        $map = post('map') ?? [];
        foreach ($map as $mapId => $officeId) {
            db()->update('api_location_map', [
                'local_office_id' => $officeId ? (int)$officeId : null,
            ], 'id = :id AND api_provider_id = :p', ['id' => (int)$mapId, 'p' => $id]);
        }
        flash_success('Eşleştirmeler kaydedildi.');
        redirect(admin_url('modules/api-providers.php?action=locations&id=' . $id));
    }

    $mappings = db()->fetchAll("
        SELECT m.*, o.name AS office_name, c.name AS city_name
        FROM api_location_map m
        LEFT JOIN offices o ON o.id = m.local_office_id
        LEFT JOIN cities c ON c.id = o.city_id
        WHERE m.api_provider_id = ?
        ORDER BY m.external_name
    ", [$id]);

    $offices = db()->fetchAll("
        SELECT o.id, o.name, c.name AS city_name, o.is_airport
        FROM offices o JOIN cities c ON c.id = o.city_id
        WHERE o.is_active = 1
        ORDER BY c.name, o.name
    ");

    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="page-header">
      <div class="page-header-left">
        <h2>Lokasyon Eşleştirme: <?= e($provider['name']) ?></h2>
        <div class="subtitle">API tarafındaki lokasyonları kendi ofislerinle eşleştir</div>
      </div>
      <div>
        <a href="<?= admin_url('modules/api-providers.php?action=sync_locations&id=' . $id) ?>"
           class="btn btn-primary" onclick="return confirm('API\'den lokasyonlar yeniden çekilsin mi?')">
          ⟳ Lokasyonları Yenile
        </a>
        <a href="<?= admin_url('modules/api-providers.php') ?>" class="btn btn-outline">← Geri</a>
      </div>
    </div>

    <?php if (empty($mappings)): ?>
      <div class="card" style="padding: 40px; text-align: center;">
        <p style="font-size: 16px; margin-bottom: 10px;">Henüz lokasyon eşleştirmesi yok.</p>
        <p style="color: #666;">Yukarıdaki "Lokasyonları Yenile" butonuna basarak API'den lokasyon listesini çekebilirsin.</p>
      </div>
    <?php else: ?>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="save_mapping" value="1">
      <div class="card" style="padding: 0;">
        <table class="table">
          <thead>
            <tr>
              <th>API Lokasyon Adı</th>
              <th>External ID</th>
              <th>Kendi Ofisim</th>
              <th>Son Sync</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($mappings as $m): ?>
              <tr>
                <td><strong><?= e($m['external_name']) ?></strong></td>
                <td><code><?= e($m['external_id']) ?></code></td>
                <td>
                  <select name="map[<?= (int)$m['id'] ?>]" class="form-select">
                    <option value="">— Eşleşmemiş —</option>
                    <?php foreach ($offices as $o): ?>
                      <option value="<?= (int)$o['id'] ?>" <?= $m['local_office_id'] == $o['id'] ? 'selected' : '' ?>>
                        <?= e($o['city_name']) ?> - <?= e($o['name']) ?>
                        <?= $o['is_airport'] ? '✈' : '' ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><?= !empty($m['last_synced_at']) ? tr_date($m['last_synced_at'], true) : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="margin-top: 20px;">
        <button type="submit" class="btn btn-primary">Eşleştirmeleri Kaydet</button>
      </div>
    </form>
    <?php endif; ?>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

// =====================================================================
// ANA LİSTE GÖRÜNÜMÜ
// =====================================================================

require_once __DIR__ . '/../includes/header.php';

$providers = db()->fetchAll("SELECT * FROM api_providers ORDER BY name");
$featureEnabled = db()->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'api_providers_enabled'") === '1';
$cacheCount = 0;
try {
    $cacheCount = (int)db()->fetchColumn("SELECT COUNT(*) FROM api_cache WHERE expires_at > NOW()");
} catch (Throwable $e) {
    $cacheCount = 0;
}
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>API Sağlayıcıları</h2>
    <div class="subtitle">Uzak araç sistemleriyle entegrasyon</div>
  </div>
  <div>
    <a href="<?= admin_url('modules/api-provider-edit.php') ?>" class="btn btn-primary">+ Yeni Sağlayıcı</a>
  </div>
</div>

<!-- FEATURE FLAG -->
<div class="card" style="padding: 20px; margin-bottom: 20px; background: <?= $featureEnabled ? '#E8F5E9' : '#FFF3E0' ?>;">
  <div style="display: flex; justify-content: space-between; align-items: center; gap: 20px;">
    <div>
      <h3 style="margin: 0 0 8px 0;">
        <?= $featureEnabled ? '✅ API Entegrasyonu AKTİF' : '⚠️ API Entegrasyonu KAPALI' ?>
      </h3>
      <p style="margin: 0; color: #555;">
        <?php if ($featureEnabled): ?>
          Kullanıcı arama yaptığında API sağlayıcılarına da sorgu gönderiliyor.
        <?php else: ?>
          Şu an sadece kendi manuel eklediğin araçlar gösteriliyor. API'ler pasif.
        <?php endif; ?>
      </p>
    </div>
    <a href="<?= admin_url('modules/api-providers.php?action=toggle_feature') ?>"
       class="btn <?= $featureEnabled ? 'btn-danger' : 'btn-primary' ?>"
       onclick="return confirm('Emin misin?')">
      <?= $featureEnabled ? 'KAPAT' : 'AÇ' ?>
    </a>
  </div>
</div>

<!-- CACHE -->
<div class="card" style="padding: 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
  <div>
    <strong>📦 Cache:</strong> <?= (int)$cacheCount ?> aktif kayıt
    <span style="color: #888; margin-left: 10px;">(aramalar 5 dk önbelleklenir)</span>
  </div>
  <a href="<?= admin_url('modules/api-providers.php?action=clear_cache') ?>"
     class="btn btn-outline" onclick="return confirm('Tüm cache silinsin mi?')">
    Cache'i Temizle
  </a>
</div>

<!-- LİSTE -->
<div class="card" style="padding: 0;">
  <?php if (empty($providers)): ?>
    <div style="padding: 60px 20px; text-align: center;">
      <h3>Henüz sağlayıcı eklenmemiş</h3>
      <p style="color: #666;">İlk API sağlayıcısını eklemek için yukarıdaki "+ Yeni Sağlayıcı" butonuna tıkla.</p>
    </div>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Ad</th>
          <th>Tip</th>
          <th>Base URL</th>
          <th>Durum</th>
          <th>Son Sync</th>
          <th style="width: 420px;">İşlemler</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($providers as $p): ?>
          <tr>
            <td>
              <strong><?= e($p['name']) ?></strong>
              <?php if (!empty($p['description'])): ?>
                <div style="font-size: 12px; color: #888;"><?= e(mb_substr($p['description'], 0, 80)) ?></div>
              <?php endif; ?>
            </td>
            <td><code><?= e($p['provider_type'] ?? 'custom') ?></code></td>
            <td><small><?= e($p['base_url']) ?></small></td>
            <td>
              <?php if ($p['is_active']): ?>
                <span class="badge badge-success">Aktif</span>
              <?php else: ?>
                <span class="badge badge-gray">Pasif</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (($p['last_sync_status'] ?? '') === 'success'): ?>
                <span class="badge badge-success">✓</span>
              <?php elseif (($p['last_sync_status'] ?? '') === 'failed'): ?>
                <span class="badge badge-danger">✗</span>
              <?php endif; ?>
              <?= !empty($p['last_sync_at']) ? tr_date($p['last_sync_at'], true) : '—' ?>
            </td>
            <td>
              <a href="<?= admin_url('modules/api-providers.php?action=test&id=' . (int)$p['id']) ?>" class="btn btn-sm btn-outline">🔌 Test</a>
              <a href="<?= admin_url('modules/api-providers.php?action=debug&id=' . (int)$p['id']) ?>" class="btn btn-sm btn-outline">📍 Loc Debug</a>
              <a href="<?= admin_url('modules/api-providers.php?action=debug_search&id=' . (int)$p['id']) ?>" class="btn btn-sm btn-outline">🚗 Araç Debug</a>
              <a href="<?= admin_url('modules/api-providers.php?action=locations&id=' . (int)$p['id']) ?>" class="btn btn-sm btn-outline">Eşleştir</a>
              <a href="<?= admin_url('modules/api-providers.php?action=companies&id=' . (int)$p['id']) ?>" class="btn btn-sm btn-outline">🏢 Firma</a>
              <a href="<?= admin_url('modules/api-provider-edit.php?id=' . (int)$p['id']) ?>" class="btn btn-sm btn-outline">Düzenle</a>
              <a href="<?= admin_url('modules/api-providers.php?action=toggle&id=' . (int)$p['id']) ?>" class="btn btn-sm btn-outline"><?= $p['is_active'] ? 'Pasifleştir' : 'Aktifleştir' ?></a>
              <a href="<?= admin_url('modules/api-providers.php?action=delete&id=' . (int)$p['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Silinsin mi?')">Sil</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
