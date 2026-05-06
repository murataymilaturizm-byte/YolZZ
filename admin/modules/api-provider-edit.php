<?php
$page_title = 'API Sağlayıcı Düzenle';
$active_menu = 'api-providers';

require_once __DIR__ . '/../../includes/bootstrap.php';

$id = (int)get('id', 0);
$isEdit = $id > 0;
$provider = null;

if ($isEdit) {
    $provider = db()->fetch("SELECT * FROM api_providers WHERE id = ?", [$id]);
    if (!$provider) {
        flash_error('Sağlayıcı bulunamadı.');
        redirect(admin_url('modules/api-providers.php'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('_csrf'))) {
        flash_error('Güvenlik hatası.');
        redirect($_SERVER['REQUEST_URI']);
    }

    // Auth config & headers JSON olarak topla
    $authConfig = [];
    if (post('auth_type') === 'api_key') {
        $authConfig = ['header_name' => post('auth_header_name', 'X-API-Key'), 'api_key' => post('auth_api_key')];
    } elseif (post('auth_type') === 'bearer') {
        $authConfig = ['token' => post('auth_token')];
    } elseif (post('auth_type') === 'basic') {
        $authConfig = ['username' => post('auth_username'), 'password' => post('auth_password')];
    } elseif (post('auth_type') === 'custom') {
        // Rentline tarzı: Key_Hack + User_Name + User_Pass
        $authConfig = [
            'key_hack' => post('auth_key_hack'),
            'user_name' => post('auth_user_name'),
            'user_pass' => post('auth_user_pass'),
        ];
    }

    $customHeaders = [];
    $hNames = $_POST['header_name'] ?? [];
    $hValues = $_POST['header_value'] ?? [];
    foreach ($hNames as $i => $name) {
        if (trim($name) && isset($hValues[$i])) {
            $customHeaders[trim($name)] = trim($hValues[$i]);
        }
    }

    // Field mapping: her satır bir alan eşleştirmesi
    $mapping = [];
    $mapping['list_path'] = post('map_list_path');
    $ourFields = ['id','brand','model','year','category','fuel_type','transmission','seats','luggage','daily_price','image','features'];
    foreach ($ourFields as $f) {
        $val = trim(post("map_$f"));
        if ($val !== '') $mapping[$f] = $val;
    }

    $data = [
        'name' => post('name'),
        'slug' => slugify(post('name')),
        'provider_type' => post('provider_type', 'custom'),
        'display_name' => post('display_name') ?: null,
        'image_base_url' => trim(post('image_base_url')) ?: null,
        'test_mode' => !empty($_POST['test_mode']) ? 1 : 0,
        'description' => post('description'),
        'base_url' => rtrim(post('base_url'), '/'),
        'auth_type' => post('auth_type', 'none'),
        'auth_config' => !empty($authConfig) ? json_encode($authConfig) : null,
        'custom_headers' => !empty($customHeaders) ? json_encode($customHeaders) : null,
        'endpoint_vehicles' => post('endpoint_vehicles'),
        'endpoint_availability' => post('endpoint_availability'),
        'endpoint_booking' => post('endpoint_booking'),
        'endpoint_cancel' => post('endpoint_cancel'),
        'field_mapping' => json_encode($mapping, JSON_UNESCAPED_UNICODE),
        'default_currency' => post('default_currency', 'TRY'),
        'price_markup_percent' => (float)post('price_markup_percent', 0),
        'sync_interval_minutes' => (int)post('sync_interval_minutes', 60),
        'is_active' => (int)post('is_active', 0),
        'priority' => (int)post('priority', 0)
    ];

    if ($isEdit) {
        db()->update('api_providers', $data, 'id = :id', ['id' => $id]);
        log_activity('update', 'api_providers', "API güncellendi: {$data['name']}", $id);
        flash_success('API sağlayıcı güncellendi.');
    } else {
        $newId = db()->insert('api_providers', $data);
        log_activity('create', 'api_providers', "API eklendi: {$data['name']}", $newId);
        flash_success('API sağlayıcı eklendi. Şimdi "Test" ile bağlantıyı kontrol edin.');
    }
    redirect(admin_url('modules/api-providers.php'));
}

require_once __DIR__ . '/../includes/header.php';

$mapping = $provider && $provider['field_mapping'] ? json_decode($provider['field_mapping'], true) : [];
$authConfig = $provider && $provider['auth_config'] ? json_decode($provider['auth_config'], true) : [];
$customHeaders = $provider && $provider['custom_headers'] ? json_decode($provider['custom_headers'], true) : [];

function p($provider, $key, $default = '') {
    return e($provider[$key] ?? $default);
}
?>

<div class="page-header">
  <div class="page-header-left">
    <h2><?= $isEdit ? 'API Sağlayıcı Düzenle' : 'Yeni API Sağlayıcı' ?></h2>
    <div class="subtitle"><?= $isEdit ? e($provider['name']) : 'Yeni bir araç tedarikçisi API\'si ekleyin' ?></div>
  </div>
  <a href="<?= admin_url('modules/api-providers.php') ?>" class="btn btn-outline">← Geri</a>
</div>

<form method="POST">
<?= csrf_field() ?>

<!-- TEMEL BİLGİLER -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-header"><div class="card-title">1. Temel Bilgiler</div></div>
  <div class="card-body">

    <!-- PRESET SEÇİMİ -->
    <?php if (!$isEdit): ?>
    <div class="form-row" style="background: #F0F7FF; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
      <label class="form-label"><strong>⚡ Hızlı Başlangıç</strong></label>
      <p style="color: #555; font-size: 13px; margin-bottom: 10px;">Tedarikçini seç, alanlar otomatik dolsun:</p>
      <button type="button" class="btn btn-outline" onclick="applyRentlinePreset()">
        🇹🇷 Rentline (TRVrac) Preset'i Uygula
      </button>
    </div>
    <?php endif ?>

    <div class="form-grid">
      <div class="form-row">
        <label class="form-label">Sağlayıcı Adı <span class="required">*</span></label>
        <input type="text" name="name" id="fld_name" class="form-input" value="<?= p($provider, 'name') ?>" placeholder="Örn: XYZ Rent API" required>
      </div>
      <div class="form-row">
        <label class="form-label">Sağlayıcı Tipi</label>
        <select name="provider_type" id="fld_provider_type" class="form-select">
          <option value="custom" <?= ($provider['provider_type'] ?? '') === 'custom' ? 'selected' : '' ?>>Özel (Generic)</option>
          <option value="rentline" <?= ($provider['provider_type'] ?? '') === 'rentline' ? 'selected' : '' ?>>Rentline (TRVrac)</option>
          <option value="local" <?= ($provider['provider_type'] ?? '') === 'local' ? 'selected' : '' ?>>Lokal</option>
        </select>
      </div>
      <div class="form-row">
        <label class="form-label">Görüntüleme Adı <span class="hint">Sitede araç kartında görünür</span></label>
        <input type="text" name="display_name" class="form-input" value="<?= p($provider, 'display_name') ?>" placeholder="Örn: Europcar, Avis veya boş bırak (Sağlayıcı Adı kullanılır)">
      </div>
      <div class="form-row">
        <label class="form-label">Görsel Base URL <span class="hint">API image_path için prefix</span></label>
        <input type="text" name="image_base_url" class="form-input" value="<?= p($provider, 'image_base_url') ?>" placeholder="Örn: http://sistemjson1.trvrac.com/Images">
      </div>
      <div class="form-row">
        <label class="form-label">Test Modu</label>
        <label class="form-check" style="padding-top:10px;">
          <input type="checkbox" name="test_mode" value="1" <?= ((int)($provider['test_mode'] ?? 1) === 1) ? 'checked' : '' ?>>
          <span><strong>AÇIK</strong> = Rezervasyonlar API'ye gönderilmez (sadece DB'ye kayıt). KAPALI = Gerçek API rezervasyonu.</span>
        </label>
      </div>
      <div class="form-row">
        <label class="form-label">Öncelik <span class="hint">Yüksek olan önce</span></label>
        <input type="number" name="priority" class="form-input" value="<?= p($provider, 'priority', 0) ?>">
      </div>
      <div class="form-row full">
        <label class="form-label">Base URL <span class="required">*</span></label>
        <input type="url" name="base_url" class="form-input" value="<?= p($provider, 'base_url') ?>" placeholder="https://api.example.com" required>
      </div>
      <div class="form-row full">
        <label class="form-label">Açıklama</label>
        <textarea name="description" class="form-textarea" rows="2"><?= p($provider, 'description') ?></textarea>
      </div>
    </div>
  </div>
</div>

<!-- YETKİLENDİRME -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-header"><div class="card-title">2. Yetkilendirme</div></div>
  <div class="card-body">
    <div class="form-row">
      <label class="form-label">Auth Tipi</label>
      <select name="auth_type" id="auth_type" class="form-select" onchange="toggleAuthFields(this.value)">
        <option value="none" <?= ($provider['auth_type'] ?? '') === 'none' ? 'selected' : '' ?>>Yok</option>
        <option value="api_key" <?= ($provider['auth_type'] ?? '') === 'api_key' ? 'selected' : '' ?>>API Key</option>
        <option value="bearer" <?= ($provider['auth_type'] ?? '') === 'bearer' ? 'selected' : '' ?>>Bearer Token</option>
        <option value="basic" <?= ($provider['auth_type'] ?? '') === 'basic' ? 'selected' : '' ?>>Basic Auth</option>
        <option value="custom" <?= ($provider['auth_type'] ?? '') === 'custom' ? 'selected' : '' ?>>Custom (Key_Hack + User + Pass — Rentline)</option>
      </select>
    </div>

    <div id="auth_api_key" class="auth-block" style="display:none;">
      <div class="form-grid">
        <div class="form-row">
          <label class="form-label">Header Adı</label>
          <input type="text" name="auth_header_name" class="form-input" value="<?= e($authConfig['header_name'] ?? 'X-API-Key') ?>">
        </div>
        <div class="form-row">
          <label class="form-label">API Key</label>
          <input type="text" name="auth_api_key" class="form-input" value="<?= e($authConfig['api_key'] ?? '') ?>">
        </div>
      </div>
    </div>

    <div id="auth_bearer" class="auth-block" style="display:none;">
      <div class="form-row">
        <label class="form-label">Bearer Token</label>
        <input type="text" name="auth_token" class="form-input" value="<?= e($authConfig['token'] ?? '') ?>">
      </div>
    </div>

    <div id="auth_basic" class="auth-block" style="display:none;">
      <div class="form-grid">
        <div class="form-row">
          <label class="form-label">Kullanıcı Adı</label>
          <input type="text" name="auth_username" class="form-input" value="<?= e($authConfig['username'] ?? '') ?>">
        </div>
        <div class="form-row">
          <label class="form-label">Şifre</label>
          <input type="password" name="auth_password" class="form-input" value="<?= e($authConfig['password'] ?? '') ?>">
        </div>
      </div>
    </div>

    <!-- Custom auth — Rentline tarzı Key_Hack + User + Pass -->
    <div id="auth_custom" class="auth-block" style="display:none;">
      <div class="form-grid">
        <div class="form-row full">
          <label class="form-label">Key_Hack <span class="hint">API tedarikçisinin verdiği benzersiz anahtar</span></label>
          <input type="text" name="auth_key_hack" id="fld_key_hack" class="form-input" value="<?= e($authConfig['key_hack'] ?? '') ?>" placeholder="Örn: 8814e1cb-9fd1-482e-aace-b56e2f9977c5">
        </div>
        <div class="form-row">
          <label class="form-label">User_Name</label>
          <input type="text" name="auth_user_name" id="fld_user_name" class="form-input" value="<?= e($authConfig['user_name'] ?? '') ?>">
        </div>
        <div class="form-row">
          <label class="form-label">User_Pass</label>
          <input type="password" name="auth_user_pass" id="fld_user_pass" class="form-input" value="<?= e($authConfig['user_pass'] ?? '') ?>">
        </div>
      </div>
    </div>

    <!-- Custom Headers -->
    <div class="form-row">
      <label class="form-label">Özel Header'lar <span class="hint">Her isteğe eklenir</span></label>
      <div id="custom_headers">
        <?php if (empty($customHeaders)): ?>
          <div class="header-row" style="display:flex; gap:8px; margin-bottom:6px;">
            <input type="text" name="header_name[]" class="form-input" placeholder="Header adı" style="flex:1;">
            <input type="text" name="header_value[]" class="form-input" placeholder="Değer" style="flex:2;">
            <button type="button" class="btn btn-outline btn-sm" onclick="this.parentElement.remove()">✕</button>
          </div>
        <?php else: foreach ($customHeaders as $hName => $hValue): ?>
          <div class="header-row" style="display:flex; gap:8px; margin-bottom:6px;">
            <input type="text" name="header_name[]" class="form-input" placeholder="Header adı" value="<?= e($hName) ?>" style="flex:1;">
            <input type="text" name="header_value[]" class="form-input" placeholder="Değer" value="<?= e($hValue) ?>" style="flex:2;">
            <button type="button" class="btn btn-outline btn-sm" onclick="this.parentElement.remove()">✕</button>
          </div>
        <?php endforeach; endif ?>
      </div>
      <button type="button" class="btn btn-outline btn-sm" onclick="addHeaderRow()">+ Header Ekle</button>
    </div>
  </div>
</div>

<!-- ENDPOINTLER -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-header"><div class="card-title">3. Endpoint'ler</div></div>
  <div class="card-body">
    <div class="form-row">
      <label class="form-label">Araç Listeleme Endpoint <span class="required">*</span> <span class="hint">Örn: /v1/vehicles</span></label>
      <input type="text" name="endpoint_vehicles" class="form-input" value="<?= p($provider, 'endpoint_vehicles') ?>" required>
    </div>
    <div class="form-grid">
      <div class="form-row">
        <label class="form-label">Müsaitlik Endpoint</label>
        <input type="text" name="endpoint_availability" class="form-input" value="<?= p($provider, 'endpoint_availability') ?>">
      </div>
      <div class="form-row">
        <label class="form-label">Rezervasyon Endpoint</label>
        <input type="text" name="endpoint_booking" class="form-input" value="<?= p($provider, 'endpoint_booking') ?>">
      </div>
      <div class="form-row">
        <label class="form-label">İptal Endpoint</label>
        <input type="text" name="endpoint_cancel" class="form-input" value="<?= p($provider, 'endpoint_cancel') ?>">
      </div>
    </div>
  </div>
</div>

<!-- FIELD MAPPING -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-header">
    <div class="card-title">4. Alan Eşleştirmesi (Field Mapping) 🎯</div>
  </div>
  <div class="card-body">
    <div style="background:var(--bg-soft); padding:12px; border-radius:8px; margin-bottom:16px; font-size:12px; line-height:1.6;">
      <strong>💡 Dot notation kullanın:</strong> <code>data.vehicles</code>, <code>pricing.daily</code>, <code>images[0].url</code><br>
      <strong>list_path:</strong> Dönen JSON'daki araç listesinin bulunduğu anahtar. Kök dizide ise boş bırakın.
    </div>

    <div class="form-grid">
      <div class="form-row full">
        <label class="form-label">Liste Yolu (list_path)</label>
        <input type="text" name="map_list_path" class="form-input" value="<?= e($mapping['list_path'] ?? '') ?>" placeholder="örn: data.vehicles">
      </div>

      <?php
      $fields = [
        'id' => ['Araç ID', 'vehicle_id veya id'],
        'brand' => ['Marka', 'make, brand, manufacturer'],
        'model' => ['Model', 'model, name'],
        'year' => ['Yıl', 'year, model_year'],
        'category' => ['Kategori', 'category, class'],
        'fuel_type' => ['Yakıt', 'fuel, fuel_type'],
        'transmission' => ['Vites', 'transmission, gearbox'],
        'seats' => ['Koltuk', 'seats, passenger_count'],
        'luggage' => ['Bagaj', 'luggage, bags'],
        'daily_price' => ['Günlük Fiyat', 'pricing.daily, price.day'],
        'image' => ['Ana Görsel', 'images[0].url, main_image'],
        'features' => ['Özellikler', 'features (array)']
      ];
      foreach ($fields as $f => $info):
      ?>
        <div class="form-row">
          <label class="form-label"><?= $info[0] ?> <span class="hint"><?= $info[1] ?></span></label>
          <input type="text" name="map_<?= $f ?>" class="form-input" value="<?= e($mapping[$f] ?? '') ?>">
        </div>
      <?php endforeach ?>
    </div>
  </div>
</div>

<!-- AYARLAR -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-header"><div class="card-title">5. Senkronizasyon & Fiyatlandırma</div></div>
  <div class="card-body">
    <div class="form-grid">
      <div class="form-row">
        <label class="form-label">Varsayılan Para Birimi</label>
        <select name="default_currency" class="form-select">
          <option value="TRY" <?= ($provider['default_currency'] ?? 'TRY') === 'TRY' ? 'selected' : '' ?>>TRY</option>
          <option value="USD" <?= ($provider['default_currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD</option>
          <option value="EUR" <?= ($provider['default_currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR</option>
        </select>
      </div>
      <div class="form-row">
        <label class="form-label">Fiyat Markup (%) <span class="hint">Üzerine eklenecek kar</span></label>
        <input type="number" step="0.01" name="price_markup_percent" class="form-input" value="<?= p($provider, 'price_markup_percent', 0) ?>">
      </div>
      <div class="form-row">
        <label class="form-label">Senkronizasyon Sıklığı (dk)</label>
        <input type="number" name="sync_interval_minutes" class="form-input" value="<?= p($provider, 'sync_interval_minutes', 60) ?>">
      </div>
      <div class="form-row">
        <label class="form-label">Aktif</label>
        <label class="form-check" style="padding-top:10px;">
          <input type="checkbox" name="is_active" value="1" <?= ($provider['is_active'] ?? 0) ? 'checked' : '' ?>>
          <span>Bu API sağlayıcıyı aktifleştir</span>
        </label>
      </div>
    </div>
  </div>
</div>

<div class="form-actions" style="margin-bottom:30px;">
  <a href="<?= admin_url('modules/api-providers.php') ?>" class="btn btn-outline">İptal</a>
  <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Güncelle' : 'Oluştur' ?></button>
</div>
</form>

<script>
function toggleAuthFields(v) {
  document.querySelectorAll('.auth-block').forEach(el => el.style.display = 'none');
  const el = document.getElementById('auth_' + v);
  if (el) el.style.display = 'block';
}
toggleAuthFields(document.getElementById('auth_type').value);

function addHeaderRow() {
  const row = document.createElement('div');
  row.className = 'header-row';
  row.style.cssText = 'display:flex; gap:8px; margin-bottom:6px;';
  row.innerHTML = '<input type="text" name="header_name[]" class="form-input" placeholder="Header adı" style="flex:1;"><input type="text" name="header_value[]" class="form-input" placeholder="Değer" style="flex:2;"><button type="button" class="btn btn-outline btn-sm" onclick="this.parentElement.remove()">✕</button>';
  document.getElementById('custom_headers').appendChild(row);
}

// Rentline (TRVrac) Preset — tek tıkla tüm alanları doldur
function applyRentlinePreset() {
  if (!confirm('Form Rentline (TRVrac) şablonuyla doldurulsun mu? Mevcut alanlar değişir.')) return;

  const setV = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
  const setN = (name, val) => { const el = document.querySelector('[name="'+name+'"]'); if (el) el.value = val; };

  setV('fld_name', 'Rentline');
  setV('fld_provider_type', 'rentline');
  setN('description', 'TRVrac / Rentline araç yönetim sistemi. Kendi ofisimin araçları buradan geliyor.');
  setN('base_url', 'http://sistemjson1.trvrac.com');
  setN('auth_type', 'custom');

  // Dropdown değişince auth bloğunu güncelle
  toggleAuthFields('custom');

  setN('endpoint_vehicles', 'JsonLocations.aspx');
  setN('endpoint_availability', 'JsonRez.aspx');
  setN('endpoint_booking', 'JsonRez_Save.aspx');
  setN('endpoint_cancel', 'JsonCancel.aspx');
  setN('default_currency', 'TRY');

  // Kullanıcı key/user/pass'i kendi doldursun (PDF'te verilen değerleri)
  // Güvenlik gereği otomatik yazmıyoruz — sen manuel gir
  alert('Rentline şablonu uygulandı.\n\nŞimdi Key_Hack, User_Name ve User_Pass alanlarını sana verilen değerlerle doldur, ardından "Oluştur" diyerek kaydet.');
  document.getElementById('fld_key_hack')?.focus();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
