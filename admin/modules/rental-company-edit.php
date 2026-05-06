<?php
$page_title = 'Firma Düzenle';
$active_menu = 'rental-companies';

require_once __DIR__ . '/../../includes/bootstrap.php';
require_auth();

$id = (int)get('id', 0);
$company = $id ? db()->fetch("SELECT * FROM rental_companies WHERE id = ?", [$id]) : null;

if ($id && !$company) {
    flash_error('Firma bulunamadı.');
    redirect(admin_url('modules/rental-companies.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('_csrf'))) {
        flash_error('Güvenlik hatası.');
        redirect($_SERVER['REQUEST_URI']);
    }

    $name = trim(post('name'));
    if (empty($name)) {
        flash_error('Firma adı zorunludur.');
        redirect($_SERVER['REQUEST_URI']);
    }

    $slug = post('slug') ? slugify(post('slug')) : slugify($name);

    // Slug benzersizlik kontrolü
    $existing = db()->fetch("SELECT id FROM rental_companies WHERE slug = ? AND id != ?", [$slug, $id]);
    if ($existing) {
        flash_error('Bu slug başka bir firma tarafından kullanılıyor: ' . $slug);
        redirect($_SERVER['REQUEST_URI']);
    }

    $data = [
        'name' => $name,
        'slug' => $slug,
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        'sort_order' => (int)post('sort_order', 0),
    ];

    // Logo yükleme (WebP'ye otomatik dönüşür)
    if (!empty($_FILES['logo']['name'])) {
        $logoPath = upload_file($_FILES['logo'], 'companies', ['jpg','jpeg','png','webp','gif'], 600, 90);
        if ($logoPath) {
            // Eski logoyu sil
            if ($company && $company['logo']) {
                $oldPath = __DIR__ . '/../../uploads/' . $company['logo'];
                if (file_exists($oldPath)) @unlink($oldPath);
            }
            $data['logo'] = $logoPath;
        } else {
            flash_warning('Logo yüklenemedi (geçersiz dosya veya boyut sorunu).');
        }
    }

    if ($id) {
        db()->update('rental_companies', $data, 'id = :id', ['id' => $id]);
        log_activity('update', 'rental_companies', 'Firma güncellendi: ' . $name, $id, 'company');
        flash_success('Firma güncellendi.');
    } else {
        $newId = db()->insert('rental_companies', $data);
        log_activity('create', 'rental_companies', 'Yeni firma: ' . $name, $newId, 'company');
        flash_success('Firma eklendi.');
    }

    redirect(admin_url('modules/rental-companies.php'));
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h2><?= $id ? 'Firma Düzenle' : 'Yeni Firma' ?></h2>
    <div class="subtitle">Avis, Europcar, Hertz gibi araç kiralama firmaları</div>
  </div>
  <a href="<?= admin_url('modules/rental-companies.php') ?>" class="btn btn-outline">← Geri</a>
</div>

<form method="POST" enctype="multipart/form-data" class="card" style="padding: 24px; max-width: 700px;">
  <?= csrf_field() ?>

  <div class="form-row">
    <label class="form-label">Firma Adı <span style="color: red;">*</span></label>
    <input type="text" name="name" class="form-input" value="<?= e($company['name'] ?? '') ?>" required placeholder="Örn: Avis, Europcar, Hertz">
  </div>

  <div class="form-row">
    <label class="form-label">Slug (URL) <span class="hint">Boş bırakırsan otomatik üretilir</span></label>
    <input type="text" name="slug" class="form-input" value="<?= e($company['slug'] ?? '') ?>" placeholder="avis">
  </div>

  <div class="form-row">
    <label class="form-label">Logo <span class="hint">PNG önerilen (şeffaf arka plan). Otomatik WebP'ye dönüşür.</span></label>
    <?php if (!empty($company['logo'])): ?>
      <div style="margin-bottom: 12px; padding: 14px; background: #F5F5F5; border-radius: 8px; display: inline-block;">
        <img src="<?= upload_url($company['logo']) ?>" alt="" style="max-width: 200px; max-height: 80px; object-fit: contain;">
        <div style="font-size: 11px; color: #999; margin-top: 6px;">Mevcut logo</div>
      </div>
    <?php endif ?>
    <input type="file" name="logo" id="logoInput" class="form-input" accept="image/png,image/jpeg,image/webp">
    <div id="logoPreview" style="margin-top: 12px; display: none;">
      <div style="padding: 14px; background: #E3F2FD; border-radius: 8px; display: inline-block;">
        <img id="logoPreviewImg" src="" alt="" style="max-width: 200px; max-height: 80px; object-fit: contain;">
        <div style="font-size: 11px; color: #1976D2; margin-top: 6px;">📌 Yeni logo (kaydedince güncellenir)</div>
      </div>
    </div>
  </div>

  <div class="form-row">
    <label class="form-label">Sıralama <span class="hint">Düşük olan önce</span></label>
    <input type="number" name="sort_order" class="form-input" value="<?= e($company['sort_order'] ?? 0) ?>" style="max-width: 150px;">
  </div>

  <div class="form-row">
    <label class="form-check" style="padding-top: 10px;">
      <input type="checkbox" name="is_active" value="1" <?= !$id || ($company['is_active'] ?? 0) == 1 ? 'checked' : '' ?>>
      <span>Aktif</span>
    </label>
  </div>

  <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #eee; display: flex; gap: 12px;">
    <button type="submit" class="btn btn-primary">💾 Kaydet</button>
    <a href="<?= admin_url('modules/rental-companies.php') ?>" class="btn btn-outline">İptal</a>
  </div>
</form>

<script>
// Logo önizleme
document.getElementById('logoInput')?.addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = function(ev) {
    document.getElementById('logoPreviewImg').src = ev.target.result;
    document.getElementById('logoPreview').style.display = 'block';
  };
  reader.readAsDataURL(file);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
