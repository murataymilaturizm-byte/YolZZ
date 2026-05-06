<?php
$page_title = 'Araç Düzenle';
$active_menu = 'vehicles';

require_once __DIR__ . '/../../includes/bootstrap.php';

$id = (int)get('id', 0);
$isEdit = $id > 0;
$vehicle = null;

if ($isEdit) {
    $vehicle = db()->fetch("SELECT * FROM vehicles WHERE id = ?", [$id]);
    if (!$vehicle) {
        flash_error('Araç bulunamadı.');
        redirect(admin_url('modules/vehicles.php'));
    }
    $page_title = 'Araç Düzenle: ' . $vehicle['model'];
}

// Kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('_csrf'))) {
        flash_error('Güvenlik hatası. Lütfen tekrar deneyin.');
        redirect($_SERVER['REQUEST_URI']);
    }

    // Features toplama (checkbox dizisi)
    $features = $_POST['features'] ?? [];
    $features = is_array($features) ? array_values(array_filter($features)) : [];

    $data = [
        'brand_id'      => (int)post('brand_id'),
        'category_id'   => (int)post('category_id'),
        'rental_company_id' => post('rental_company_id') ? (int)post('rental_company_id') : null,
        'model'         => post('model'),
        'slug'          => slugify(post('model') . '-' . post('year')),
        'year'          => (int)post('year'),
        'fuel_type'     => post('fuel_type'),
        'transmission'  => post('transmission'),
        'seats'         => (int)post('seats'),
        'doors'         => (int)post('doors', 4),
        'luggage'       => (int)post('luggage'),
        'engine_cc'     => (int)post('engine_cc') ?: null,
        'color'         => post('color'),
        'daily_price'   => (float)post('daily_price'),
        'weekly_price'  => (float)post('weekly_price') ?: null,
        'monthly_price' => (float)post('monthly_price') ?: null,
        'deposit'       => (float)post('deposit'),
        'km_limit'      => post('km_limit', 'Sınırsız'),
        'min_age'       => (int)post('min_age', 21),
        'min_license_year' => (int)post('min_license_year', 1),
        'features'      => !empty($features) ? json_encode($features, JSON_UNESCAPED_UNICODE) : null,
        'insurance_info'=> post('insurance_info', 'Zorunlu Trafik'),
        'office_id'     => (int)post('office_id') ?: null,
        'tag'           => post('tag', ''),
        'discount_percent' => (int)post('discount_percent', 0),
        'status'        => post('status', 'active'),
        'description_tr'=> post('description_tr'),
        'description_en'=> post('description_en'),
        'sort_order'    => (int)post('sort_order', 0),
    ];

    // Validation
    $errors = [];
    if (!$data['brand_id']) $errors[] = 'Marka seçiniz.';
    if (!$data['category_id']) $errors[] = 'Kategori seçiniz.';
    if (!$data['model']) $errors[] = 'Model zorunludur.';
    if (!$data['daily_price'] || $data['daily_price'] <= 0) $errors[] = 'Geçerli bir günlük fiyat giriniz.';

    if ($errors) {
        foreach ($errors as $e) flash_error($e);
        save_old($_POST);
    } else {
        // Görsel yükleme
        if (!empty($_FILES['main_image']['name'])) {
            $uploaded = upload_file($_FILES['main_image'], 'vehicles');
            if ($uploaded) {
                $data['main_image'] = $uploaded;
            }
        }

        // Galeri yükleme
        if (!empty($_FILES['gallery']['name'][0])) {
            $gallery = $isEdit && $vehicle['gallery'] ? (json_decode($vehicle['gallery'], true) ?: []) : [];
            foreach ($_FILES['gallery']['name'] as $i => $name) {
                if (empty($name)) continue;
                $file = [
                    'name' => $_FILES['gallery']['name'][$i],
                    'type' => $_FILES['gallery']['type'][$i],
                    'tmp_name' => $_FILES['gallery']['tmp_name'][$i],
                    'error' => $_FILES['gallery']['error'][$i],
                    'size' => $_FILES['gallery']['size'][$i]
                ];
                $uploaded = upload_file($file, 'vehicles');
                if ($uploaded) $gallery[] = $uploaded;
            }
            $data['gallery'] = json_encode($gallery, JSON_UNESCAPED_UNICODE);
        }

        if ($isEdit) {
            db()->update('vehicles', $data, 'id = :id', ['id' => $id]);
            log_activity('update', 'vehicles', "Araç güncellendi: {$data['model']}", $id, 'vehicle');
            flash_success('Araç başarıyla güncellendi.');
        } else {
            $data['source_type'] = 'manual';
            $newId = db()->insert('vehicles', $data);
            log_activity('create', 'vehicles', "Araç eklendi: {$data['model']}", $newId, 'vehicle');
            flash_success('Araç başarıyla eklendi.');
            $id = $newId;
        }
        clear_old();
        redirect(admin_url('modules/vehicles.php'));
    }
}

require_once __DIR__ . '/../includes/header.php';

$categories = db()->fetchAll("SELECT * FROM vehicle_categories WHERE is_active = 1 ORDER BY sort_order");
$brands = db()->fetchAll("SELECT * FROM vehicle_brands WHERE is_active = 1 ORDER BY name");
$rentalCompanies = db()->fetchAll("SELECT * FROM rental_companies WHERE is_active = 1 ORDER BY sort_order, name");
$offices = db()->fetchAll("SELECT o.*, c.name AS city_name FROM offices o JOIN cities c ON c.id = o.city_id WHERE o.is_active = 1 ORDER BY c.name, o.name");

$currentFeatures = [];
if ($vehicle && $vehicle['features']) {
    $currentFeatures = json_decode($vehicle['features'], true) ?: [];
}

$commonFeatures = [
    'Klima', 'ABS', 'Airbag', 'Hız Sabitleyici', 'Park Sensörü', 'Geri Görüş Kamerası',
    'Bluetooth', 'USB', 'Navigasyon', 'Deri Koltuk', 'Çelik Jant', 'Sunroof',
    'Start/Stop', 'Otomatik Park', 'Şerit Takip', 'Merkezi Kilit', 'Elektrikli Cam'
];

function v($vehicle, $key, $default = '') {
    return e($vehicle[$key] ?? old($key) ?? $default);
}
?>

<div class="page-header">
  <div class="page-header-left">
    <h2><?= $isEdit ? 'Araç Düzenle' : 'Yeni Araç Ekle' ?></h2>
    <div class="subtitle"><?= $isEdit ? 'Araç bilgilerini güncelleyin' : 'Filoya yeni araç ekleyin' ?></div>
  </div>
  <a href="<?= admin_url('modules/vehicles.php') ?>" class="btn btn-outline">← Listeye Dön</a>
</div>

<?php if ($isEdit && $vehicle['source_type'] === 'api'): ?>
<div class="flash flash-warning" style="margin-bottom:16px;">
  <span class="flash-icon">🔌</span>
  Bu araç <strong>API üzerinden</strong> geldi. Bazı alanlar senkronizasyon sırasında üzerine yazılabilir.
</div>
<?php endif ?>

<form method="POST" enctype="multipart/form-data">
<?= csrf_field() ?>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px;" class="vehicle-edit-grid">

  <!-- SOL KOLON -->
  <div>
    <!-- TEMEL BİLGİLER -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <div class="card-title">Temel Bilgiler</div>
      </div>
      <div class="card-body">
        <div class="form-grid">
          <div class="form-row">
            <label class="form-label">Marka <span class="required">*</span></label>
            <select name="brand_id" class="form-select" required>
              <option value="">Seçin...</option>
              <?php foreach ($brands as $b): ?>
                <option value="<?= $b['id'] ?>" <?= ($vehicle['brand_id'] ?? 0) == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
              <?php endforeach ?>
            </select>
          </div>

          <div class="form-row">
            <label class="form-label">Kategori <span class="required">*</span></label>
            <select name="category_id" class="form-select" required>
              <option value="">Seçin...</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($vehicle['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name_tr']) ?></option>
              <?php endforeach ?>
            </select>
          </div>

          <div class="form-row">
            <label class="form-label">Firma <span class="hint">Avis, Europcar vs. — boş bırakılabilir</span></label>
            <select name="rental_company_id" class="form-select" id="rentalCompanySelect" onchange="updateCompanyLogo()">
              <option value="">— Seçilmemiş —</option>
              <?php foreach ($rentalCompanies as $rc): ?>
                <option value="<?= $rc['id'] ?>"
                        data-logo="<?= $rc['logo'] ? e(upload_url($rc['logo'])) : '' ?>"
                        <?= ($vehicle['rental_company_id'] ?? 0) == $rc['id'] ? 'selected' : '' ?>>
                  <?= e($rc['name']) ?>
                </option>
              <?php endforeach ?>
            </select>
            <div id="companyLogoPreview" style="margin-top: 8px; display: none; padding: 10px; background: #F5F5F5; border-radius: 6px;">
              <img id="companyLogoImg" src="" alt="" style="max-width: 150px; max-height: 50px; object-fit: contain;">
            </div>
            <?php if (empty($rentalCompanies)): ?>
              <div style="margin-top: 6px; font-size: 12px; color: #888;">
                Henüz firma yok. <a href="<?= admin_url('modules/rental-company-edit.php') ?>" target="_blank">+ Yeni Firma Ekle</a>
              </div>
            <?php endif ?>
          </div>

          <div class="form-row">
            <label class="form-label">Model <span class="required">*</span> <span class="hint">Örn: Fiat Egea 1.3 Multijet</span></label>
            <input type="text" name="model" class="form-input" value="<?= v($vehicle, 'model') ?>" required>
          </div>

          <div class="form-row">
            <label class="form-label">Model Yılı <span class="required">*</span></label>
            <input type="number" name="year" class="form-input" min="2000" max="<?= date('Y') + 1 ?>" value="<?= v($vehicle, 'year', date('Y')) ?>" required>
          </div>

          <div class="form-row">
            <label class="form-label">Yakıt Tipi</label>
            <select name="fuel_type" class="form-select">
              <?php foreach (['Benzin','Dizel','LPG','Elektrik','Hibrit'] as $f): ?>
                <option value="<?= $f ?>" <?= ($vehicle['fuel_type'] ?? 'Benzin') === $f ? 'selected' : '' ?>><?= $f ?></option>
              <?php endforeach ?>
            </select>
          </div>

          <div class="form-row">
            <label class="form-label">Vites</label>
            <select name="transmission" class="form-select">
              <?php foreach (['Manuel','Otomatik','Yarı Otomatik'] as $t): ?>
                <option value="<?= $t ?>" <?= ($vehicle['transmission'] ?? 'Manuel') === $t ? 'selected' : '' ?>><?= $t ?></option>
              <?php endforeach ?>
            </select>
          </div>

          <div class="form-row">
            <label class="form-label">Koltuk Sayısı</label>
            <input type="number" name="seats" class="form-input" min="1" max="9" value="<?= v($vehicle, 'seats', 5) ?>">
          </div>

          <div class="form-row">
            <label class="form-label">Bagaj Kapasitesi</label>
            <input type="number" name="luggage" class="form-input" min="0" max="6" value="<?= v($vehicle, 'luggage', 2) ?>">
          </div>

          <div class="form-row">
            <label class="form-label">Kapı Sayısı</label>
            <input type="number" name="doors" class="form-input" min="2" max="5" value="<?= v($vehicle, 'doors', 4) ?>">
          </div>

          <div class="form-row">
            <label class="form-label">Motor Hacmi (cc) <span class="hint">Opsiyonel</span></label>
            <input type="number" name="engine_cc" class="form-input" value="<?= v($vehicle, 'engine_cc') ?>">
          </div>

          <div class="form-row">
            <label class="form-label">Renk</label>
            <input type="text" name="color" class="form-input" value="<?= v($vehicle, 'color') ?>">
          </div>

          <div class="form-row">
            <label class="form-label">Ofis</label>
            <select name="office_id" class="form-select">
              <option value="">Belirsiz</option>
              <?php foreach ($offices as $o): ?>
                <option value="<?= $o['id'] ?>" <?= ($vehicle['office_id'] ?? 0) == $o['id'] ? 'selected' : '' ?>>
                  <?= e($o['city_name']) ?> — <?= e($o['name']) ?>
                </option>
              <?php endforeach ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- FİYATLANDIRMA -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <div class="card-title">Fiyatlandırma</div>
      </div>
      <div class="card-body">
        <div class="form-grid">
          <div class="form-row">
            <label class="form-label">Günlük Fiyat (₺) <span class="required">*</span></label>
            <input type="number" step="0.01" name="daily_price" class="form-input" value="<?= v($vehicle, 'daily_price') ?>" required>
          </div>
          <div class="form-row">
            <label class="form-label">Haftalık Fiyat (₺) <span class="hint">Opsiyonel</span></label>
            <input type="number" step="0.01" name="weekly_price" class="form-input" value="<?= v($vehicle, 'weekly_price') ?>">
          </div>
          <div class="form-row">
            <label class="form-label">Aylık Fiyat (₺) <span class="hint">Opsiyonel</span></label>
            <input type="number" step="0.01" name="monthly_price" class="form-input" value="<?= v($vehicle, 'monthly_price') ?>">
          </div>
          <div class="form-row">
            <label class="form-label">Depozito (₺)</label>
            <input type="number" step="0.01" name="deposit" class="form-input" value="<?= v($vehicle, 'deposit', 3000) ?>">
          </div>
          <div class="form-row">
            <label class="form-label">Etiket</label>
            <select name="tag" class="form-select">
              <option value="">— Yok —</option>
              <?php
              $tags = ['popular' => '🔥 Popüler', 'new' => '✨ Yeni', 'discount' => '💰 İndirim', 'luxury' => '👑 Lüks', 'eco' => '🌱 Eko'];
              foreach ($tags as $val => $label):
              ?>
                <option value="<?= $val ?>" <?= ($vehicle['tag'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="form-row">
            <label class="form-label">İndirim Yüzdesi (%)</label>
            <input type="number" name="discount_percent" class="form-input" min="0" max="99" value="<?= v($vehicle, 'discount_percent', 0) ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- ÖZELLİKLER -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <div class="card-title">Özellikler</div>
      </div>
      <div class="card-body">
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(160px,1fr)); gap:8px;">
          <?php foreach ($commonFeatures as $feat): ?>
            <label class="form-check">
              <input type="checkbox" name="features[]" value="<?= e($feat) ?>" <?= in_array($feat, $currentFeatures) ? 'checked' : '' ?>>
              <span><?= e($feat) ?></span>
            </label>
          <?php endforeach ?>
        </div>
      </div>
    </div>

    <!-- KIRALAMA KOŞULLARI -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <div class="card-title">Kiralama Koşulları</div>
      </div>
      <div class="card-body">
        <div class="form-grid">
          <div class="form-row">
            <label class="form-label">KM Limiti</label>
            <input type="text" name="km_limit" class="form-input" value="<?= v($vehicle, 'km_limit', 'Sınırsız') ?>" placeholder="Ör: Sınırsız veya 200 km/gün">
          </div>
          <div class="form-row">
            <label class="form-label">Minimum Yaş</label>
            <input type="number" name="min_age" class="form-input" min="18" max="80" value="<?= v($vehicle, 'min_age', 21) ?>">
          </div>
          <div class="form-row">
            <label class="form-label">Min. Ehliyet Yılı</label>
            <input type="number" name="min_license_year" class="form-input" min="0" max="10" value="<?= v($vehicle, 'min_license_year', 1) ?>">
          </div>
          <div class="form-row">
            <label class="form-label">Sigorta Bilgisi</label>
            <input type="text" name="insurance_info" class="form-input" value="<?= v($vehicle, 'insurance_info', 'Zorunlu Trafik') ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- AÇIKLAMALAR -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <div class="card-title">Açıklamalar</div>
      </div>
      <div class="card-body">
        <div class="form-row">
          <label class="form-label">Açıklama (Türkçe)</label>
          <textarea name="description_tr" class="form-textarea" rows="4"><?= v($vehicle, 'description_tr') ?></textarea>
        </div>
        <div class="form-row">
          <label class="form-label">Açıklama (English)</label>
          <textarea name="description_en" class="form-textarea" rows="4"><?= v($vehicle, 'description_en') ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <!-- SAĞ KOLON -->
  <div>
    <!-- GÖRSEL -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <div class="card-title">Ana Görsel</div>
      </div>
      <div class="card-body">
        <?php if ($vehicle && $vehicle['main_image']): ?>
          <img src="<?= upload_url($vehicle['main_image']) ?>" style="width:100%; border-radius:8px; margin-bottom:12px; background:var(--bg-soft);">
        <?php endif ?>
        <input type="file" name="main_image" class="form-input" accept="image/*">
        <div class="form-help">JPG, PNG, WebP - Max 5MB</div>
      </div>
    </div>

    <!-- GALERİ -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <div class="card-title">Galeri</div>
      </div>
      <div class="card-body">
        <?php
        $gallery = [];
        if ($vehicle && $vehicle['gallery']) {
            $gallery = json_decode($vehicle['gallery'], true) ?: [];
        }
        if (!empty($gallery)):
        ?>
        <div style="display:grid; grid-template-columns: repeat(3,1fr); gap:6px; margin-bottom:10px;">
          <?php foreach ($gallery as $img): ?>
            <img src="<?= upload_url($img) ?>" style="width:100%; aspect-ratio:1; object-fit:cover; border-radius:6px;">
          <?php endforeach ?>
        </div>
        <?php endif ?>
        <input type="file" name="gallery[]" class="form-input" accept="image/*" multiple>
        <div class="form-help">Birden fazla görsel seçebilirsiniz</div>
      </div>
    </div>

    <!-- YAYIN -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Yayın Durumu</div>
      </div>
      <div class="card-body">
        <div class="form-row">
          <label class="form-label">Durum</label>
          <select name="status" class="form-select">
            <option value="active" <?= ($vehicle['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktif</option>
            <option value="maintenance" <?= ($vehicle['status'] ?? '') === 'maintenance' ? 'selected' : '' ?>>Bakımda</option>
            <option value="inactive" <?= ($vehicle['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Pasif</option>
          </select>
        </div>
        <div class="form-row">
          <label class="form-label">Sıralama <span class="hint">Küçük olan üstte</span></label>
          <input type="number" name="sort_order" class="form-input" value="<?= v($vehicle, 'sort_order', 0) ?>">
        </div>

        <div class="form-actions" style="flex-direction:column;">
          <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">
            <?= $isEdit ? '💾 Güncelle' : '➕ Ekle' ?>
          </button>
          <a href="<?= admin_url('modules/vehicles.php') ?>" class="btn btn-outline" style="width:100%; justify-content:center;">İptal</a>
        </div>
      </div>
    </div>
  </div>
</div>
</form>

<style>
@media (max-width: 991px) {
  .vehicle-edit-grid { grid-template-columns: 1fr !important; }
}
</style>

<script>
function updateCompanyLogo() {
  const sel = document.getElementById('rentalCompanySelect');
  const opt = sel.options[sel.selectedIndex];
  const logoUrl = opt?.dataset.logo;
  const preview = document.getElementById('companyLogoPreview');
  const img = document.getElementById('companyLogoImg');
  if (logoUrl) {
    img.src = logoUrl;
    preview.style.display = 'block';
  } else {
    preview.style.display = 'none';
  }
}
// Sayfa açılınca mevcut seçimi göster
document.addEventListener('DOMContentLoaded', updateCompanyLogo);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
