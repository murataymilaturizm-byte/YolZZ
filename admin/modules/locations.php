<?php
$page_title = 'Lokasyonlar (SEO Sayfaları)';
$active_menu = 'locations';

require_once __DIR__ . '/../../includes/bootstrap.php';

$action = get('action');
$id = (int)get('id', 0);

// PASIFLE (eski davranış — is_active = 0)
if ($action === 'delete' && $id) {
    db()->update('locations', ['is_active' => 0], 'id = :id', ['id' => $id]);
    flash_success('Lokasyon pasif hale getirildi.');
    redirect(admin_url('modules/locations.php'));
}

// HARD DELETE — kaydı tamamen sil (geri alınamaz)
if ($action === 'hard_delete' && $id) {
    // Önce kaydı al — silmeden önce kontrol için
    $loc = db()->fetchOne("SELECT id, name_tr, slug FROM locations WHERE id = :id", ['id' => $id]);
    if (!$loc) {
        flash_error('Lokasyon bulunamadı.');
        redirect(admin_url('modules/locations.php'));
    }

    // Tamam — sil (locations tablosu standalone, başka tabloya bağlı değil)
    try {
        db()->delete('locations', 'id = :id', ['id' => $id]);
        flash_success('Lokasyon "' . htmlspecialchars($loc['name_tr']) . '" tamamen silindi (slug: ' . htmlspecialchars($loc['slug']) . ').');
    } catch (\Throwable $e) {
        flash_error('Silinemedi: ' . $e->getMessage());
    }
    redirect(admin_url('modules/locations.php'));
}

// SAVE (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('_csrf'))) { flash_error('Güvenlik hatası.'); redirect(admin_url('modules/locations.php')); }

    $nameTr = trim(post('name_tr'));
    $nameEn = trim(post('name_en'));

    // Slug: manuel verilmişse kullan, yoksa name_tr'den üret
    $slug = trim(post('slug'));
    if (empty($slug)) $slug = slugify($nameTr);

    $data = [
        'name_tr' => $nameTr,
        'name_en' => $nameEn ?: $nameTr,
        'slug' => $slug,
        'type' => post('type', 'city'),
        'city_id' => (int)post('city_id') ?: null,
        'parent_id' => (int)post('parent_id') ?: null,
        'latitude' => (float)post('latitude') ?: null,
        'longitude' => (float)post('longitude') ?: null,
        'short_desc_tr' => post('short_desc_tr'),
        'short_desc_en' => post('short_desc_en'),
        'content_tr' => post('content_tr'),
        'content_en' => post('content_en'),
        'meta_title_tr' => post('meta_title_tr'),
        'meta_title_en' => post('meta_title_en'),
        'meta_description_tr' => post('meta_description_tr'),
        'meta_description_en' => post('meta_description_en'),
        'nearby_info' => post('nearby_info'),
        'is_featured' => (int)post('is_featured', 0),
        'is_active' => (int)post('is_active', 1),
        'sort_order' => (int)post('sort_order', 0)
    ];

    // Görsel yükleme
    if (!empty($_FILES['image']['name'])) {
        $uploaded = upload_file($_FILES['image'], 'locations');
        if ($uploaded) $data['image'] = $uploaded;
    } else {
        // Görsel alanı boş bırakılmışsa mevcut değeri koru
        $data['image'] = post('existing_image');
    }

    try {
        if ($id) {
            db()->update('locations', $data, 'id = :id', ['id' => $id]);
            flash_success('Lokasyon güncellendi.');
        } else {
            db()->insert('locations', $data);
            flash_success('Lokasyon eklendi.');
        }
    } catch (Throwable $e) {
        flash_error('Kayıt hatası: ' . $e->getMessage());
        redirect(admin_url('modules/locations.php?action=' . ($id ? "edit&id=$id" : 'new')));
    }
    redirect(admin_url('modules/locations.php'));
}

require_once __DIR__ . '/../includes/header.php';

$location = null;
if ($action === 'edit' && $id) {
    $location = db()->fetch("SELECT * FROM locations WHERE id = ?", [$id]);
    if (!$location) { flash_error('Lokasyon bulunamadı.'); redirect(admin_url('modules/locations.php')); }
}

// LIST veya EDIT görünümü
if ($action === 'edit' || $action === 'new'):
    $cities = db()->fetchAll("SELECT * FROM cities WHERE is_active = 1 ORDER BY name");
    $allLocations = db()->fetchAll("SELECT id, name_tr, type FROM locations WHERE is_active = 1 ORDER BY name_tr");
?>

<div class="page-header">
  <div class="page-header-left">
    <h2><?= $location ? 'Lokasyon Düzenle: ' . e($location['name_tr']) : 'Yeni Lokasyon' ?></h2>
    <div class="subtitle">SEO odaklı landing sayfası</div>
  </div>
  <a href="<?= admin_url('modules/locations.php') ?>" class="btn btn-outline">← Geri</a>
</div>

<form method="POST" enctype="multipart/form-data">
<?= csrf_field() ?>
<input type="hidden" name="existing_image" value="<?= e($location['image'] ?? '') ?>">

<!-- TEMEL BİLGİLER -->
<div class="card">
  <div class="card-header"><strong>📍 Temel Bilgiler</strong></div>
  <div class="card-body">
    <div class="form-grid">
      <div class="form-row">
        <label class="form-label">İsim (Türkçe) <span class="required">*</span></label>
        <input type="text" name="name_tr" class="form-input" value="<?= e($location['name_tr'] ?? '') ?>" required placeholder="Örn: Sabiha Gökçen Havalimanı">
      </div>
      <div class="form-row">
        <label class="form-label">İsim (İngilizce)</label>
        <input type="text" name="name_en" class="form-input" value="<?= e($location['name_en'] ?? '') ?>" placeholder="Sabiha Gokcen Airport">
      </div>
      <div class="form-row">
        <label class="form-label">URL Slug <span class="hint">Boş bırakılırsa otomatik üretilir</span></label>
        <input type="text" name="slug" class="form-input" value="<?= e($location['slug'] ?? '') ?>" placeholder="sabiha-gokcen-havalimani">
        <small style="color:var(--text-muted); font-size:11px;">→ yolzz.com/lokasyon/<strong><?= e($location['slug'] ?? 'slug') ?></strong></small>
      </div>
      <div class="form-row">
        <label class="form-label">Tip <span class="required">*</span></label>
        <select name="type" class="form-select" required>
          <?php $t = $location['type'] ?? 'city'; ?>
          <option value="city" <?= $t==='city'?'selected':''?>>🏙 Şehir</option>
          <option value="airport" <?= $t==='airport'?'selected':''?>>✈ Havalimanı</option>
          <option value="district" <?= $t==='district'?'selected':''?>>📍 İlçe / Semt</option>
          <option value="landmark" <?= $t==='landmark'?'selected':''?>>🗺 Turistik Bölge</option>
        </select>
      </div>
      <div class="form-row">
        <label class="form-label">Şehir (bağlı)</label>
        <select name="city_id" class="form-select">
          <option value="">— Yok —</option>
          <?php foreach ($cities as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($location['city_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="form-row">
        <label class="form-label">Üst Lokasyon</label>
        <select name="parent_id" class="form-select">
          <option value="">— Yok —</option>
          <?php foreach ($allLocations as $l):
            if ($location && $l['id'] == $location['id']) continue; ?>
            <option value="<?= $l['id'] ?>" <?= ($location['parent_id'] ?? 0) == $l['id'] ? 'selected' : '' ?>><?= e($l['name_tr']) ?> (<?= e($l['type']) ?>)</option>
          <?php endforeach ?>
        </select>
      </div>
    </div>
  </div>
</div>

<!-- GÖRSELLER -->
<div class="card">
  <div class="card-header"><strong>🖼 Görsel</strong></div>
  <div class="card-body">
    <div class="form-row">
      <label class="form-label">Kapak Görseli <span class="hint">JPG, PNG, WebP · Max 5 MB</span></label>
      <input type="file" name="image" class="form-input" accept="image/*">
      <?php if (!empty($location['image'])): ?>
        <div style="margin-top:10px;">
          <?php
          $imgUrl = (strpos($location['image'], 'http') === 0) ? $location['image'] : upload_url($location['image']);
          ?>
          <img src="<?= e($imgUrl) ?>?t=<?= time() ?>" style="width:240px; height:160px; object-fit:cover; border-radius:8px; border:1px solid var(--border);">
          <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">Mevcut: <?= e($location['image']) ?></div>
        </div>
      <?php endif ?>
      <small style="color:var(--text-muted); font-size:11px; display:block; margin-top:6px;">
        Yeni görsel yüklenmezse mevcut görsel korunur. Görsel değiştirdikten sonra sitede görmek için Ctrl+Shift+R yap.
      </small>
    </div>
  </div>
</div>

<!-- İÇERİK -->
<div class="card">
  <div class="card-header"><strong>📝 İçerik (SEO)</strong></div>
  <div class="card-body">
    <div class="form-grid">
      <div class="form-row full">
        <label class="form-label">Kısa Açıklama (TR) <span class="hint">Kart altında görünür, max 300 karakter</span></label>
        <textarea name="short_desc_tr" class="form-textarea" rows="2" maxlength="300"><?= e($location['short_desc_tr'] ?? '') ?></textarea>
      </div>
      <div class="form-row full">
        <label class="form-label">Kısa Açıklama (EN)</label>
        <textarea name="short_desc_en" class="form-textarea" rows="2" maxlength="300"><?= e($location['short_desc_en'] ?? '') ?></textarea>
      </div>
      <div class="form-row full">
        <label class="form-label">Uzun İçerik / Ana Metin (TR) <span class="hint">HTML destekli · SEO için hayati</span></label>
        <textarea name="content_tr" class="form-textarea" rows="10" style="font-family:monospace; font-size:13px;"><?= e($location['content_tr'] ?? '') ?></textarea>
        <small style="color:var(--text-muted); font-size:11px;">&lt;p&gt;, &lt;h2&gt;, &lt;h3&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;strong&gt; gibi HTML etiketlerini kullanabilirsiniz.</small>
      </div>
      <div class="form-row full">
        <label class="form-label">Uzun İçerik (EN)</label>
        <textarea name="content_en" class="form-textarea" rows="6" style="font-family:monospace; font-size:13px;"><?= e($location['content_en'] ?? '') ?></textarea>
      </div>
      <div class="form-row full">
        <label class="form-label">Yakın Yerler / Ek Bilgi (TR)</label>
        <textarea name="nearby_info" class="form-textarea" rows="4" style="font-family:monospace; font-size:13px;"><?= e($location['nearby_info'] ?? '') ?></textarea>
      </div>
    </div>
  </div>
</div>

<!-- SEO META -->
<div class="card">
  <div class="card-header"><strong>🔍 SEO Meta</strong></div>
  <div class="card-body">
    <div class="form-grid">
      <div class="form-row full">
        <label class="form-label">Meta Title (TR) <span class="hint">Max 60 karakter önerilir</span></label>
        <input type="text" name="meta_title_tr" class="form-input" value="<?= e($location['meta_title_tr'] ?? '') ?>" maxlength="200" placeholder="Sabiha Gökçen Havalimanı Araç Kiralama | Yolzz">
      </div>
      <div class="form-row full">
        <label class="form-label">Meta Description (TR) <span class="hint">Max 160 karakter önerilir</span></label>
        <textarea name="meta_description_tr" class="form-textarea" rows="2" maxlength="300"><?= e($location['meta_description_tr'] ?? '') ?></textarea>
      </div>
      <div class="form-row full">
        <label class="form-label">Meta Title (EN)</label>
        <input type="text" name="meta_title_en" class="form-input" value="<?= e($location['meta_title_en'] ?? '') ?>" maxlength="200">
      </div>
      <div class="form-row full">
        <label class="form-label">Meta Description (EN)</label>
        <textarea name="meta_description_en" class="form-textarea" rows="2" maxlength="300"><?= e($location['meta_description_en'] ?? '') ?></textarea>
      </div>
    </div>
  </div>
</div>

<!-- KONUM & AYARLAR -->
<div class="card">
  <div class="card-header"><strong>⚙ Ayarlar</strong></div>
  <div class="card-body">
    <div class="form-grid">
      <div class="form-row">
        <label class="form-label">Enlem (Latitude)</label>
        <input type="text" name="latitude" class="form-input" value="<?= e($location['latitude'] ?? '') ?>" placeholder="40.8986">
      </div>
      <div class="form-row">
        <label class="form-label">Boylam (Longitude)</label>
        <input type="text" name="longitude" class="form-input" value="<?= e($location['longitude'] ?? '') ?>" placeholder="29.3092">
      </div>
      <div class="form-row">
        <label class="form-label">Sıralama</label>
        <input type="number" name="sort_order" class="form-input" value="<?= (int)($location['sort_order'] ?? 0) ?>">
      </div>
      <div class="form-row">
        <label class="form-label">Durum</label>
        <label class="form-check" style="margin-top:8px;">
          <input type="checkbox" name="is_featured" value="1" <?= !empty($location['is_featured']) ? 'checked' : '' ?>>
          ⭐ Ana sayfada öne çıkar
        </label>
        <label class="form-check" style="margin-top:6px;">
          <input type="checkbox" name="is_active" value="1" <?= ($location['is_active'] ?? 1) ? 'checked' : '' ?>>
          ✓ Aktif (yayında)
        </label>
      </div>
    </div>
    <div class="form-actions">
      <a href="<?= admin_url('modules/locations.php') ?>" class="btn btn-outline">İptal</a>
      <button type="submit" class="btn btn-primary">💾 Kaydet</button>
    </div>
  </div>
</div>
</form>

<?php else:
    $search = get('q');
    $typeFilter = get('type', '');
    $where = ['1=1']; $params = [];
    if ($search) {
        $where[] = "(name_tr LIKE :s OR slug LIKE :s2)";
        $params['s'] = "%$search%"; $params['s2'] = "%$search%";
    }
    if ($typeFilter) {
        $where[] = "type = :t";
        $params['t'] = $typeFilter;
    }
    $locations = db()->fetchAll(
        "SELECT * FROM locations WHERE " . implode(' AND ', $where) . " ORDER BY is_featured DESC, sort_order, name_tr",
        $params
    );
    $typeLabels = [
        'airport' => '✈ Havalimanı',
        'city' => '🏙 Şehir',
        'district' => '📍 İlçe/Semt',
        'landmark' => '🗺 Turistik'
    ];
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Lokasyonlar (SEO Sayfaları)</h2>
    <div class="subtitle"><?= count($locations) ?> lokasyon · <em style="color:var(--text-muted);">Fiziksel şubeler için "Ofisler" menüsüne gidin</em></div>
  </div>
  <a href="?action=new" class="btn btn-primary">+ Yeni Lokasyon</a>
</div>

<form method="GET" class="filters-bar">
  <div class="search-input-wrap" style="flex:1;">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
    <input type="text" name="q" class="form-input search-input" placeholder="Lokasyon adı / slug ara..." value="<?= e($search) ?>">
  </div>
  <select name="type" class="form-select" style="max-width:200px;">
    <option value="">Tüm Tipler</option>
    <?php foreach ($typeLabels as $k => $lbl): ?>
      <option value="<?= $k ?>" <?= $typeFilter === $k ? 'selected' : '' ?>><?= $lbl ?></option>
    <?php endforeach ?>
  </select>
  <button type="submit" class="btn btn-secondary">Ara</button>
</form>

<div class="card">
<?php if (empty($locations)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">📍</div>
    <div class="empty-state-title">Lokasyon bulunamadı</div>
    <div class="empty-state-desc">SEO için sayfa eklemek üzere başlayın.</div>
    <a href="?action=new" class="btn btn-primary">İlk Lokasyonu Ekle</a>
  </div>
<?php else: ?>
<div class="table-wrapper" style="border:0; border-radius:0;">
  <table class="table">
    <thead>
      <tr>
        <th>Lokasyon</th>
        <th>Tip</th>
        <th>Slug / URL</th>
        <th>Görüntülenme</th>
        <th>Durum</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($locations as $l): ?>
      <tr>
        <td>
          <div style="display:flex; align-items:center; gap:10px;">
            <?php if (!empty($l['image'])):
              $imgUrl = (strpos($l['image'], 'http') === 0) ? $l['image'] : upload_url($l['image']);
            ?>
              <img src="<?= e($imgUrl) ?>?t=<?= time() ?>" style="width:40px; height:40px; object-fit:cover; border-radius:6px;">
            <?php else: ?>
              <div style="width:40px; height:40px; background:var(--bg-soft); border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:18px;">📍</div>
            <?php endif ?>
            <div>
              <strong><?= e($l['name_tr']) ?></strong>
              <?php if ($l['is_featured']): ?><span class="badge badge-warning" style="margin-left:6px; font-size:10px;">⭐ Öne Çıkan</span><?php endif ?>
              <?php if ($l['short_desc_tr']): ?><div style="font-size:11px; color:var(--text-muted); max-width:360px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e($l['short_desc_tr']) ?></div><?php endif ?>
            </div>
          </div>
        </td>
        <td><?= $typeLabels[$l['type']] ?? $l['type'] ?></td>
        <td style="font-family:monospace; font-size:11px;">
          <a href="<?= url('lokasyon/' . $l['slug']) ?>" target="_blank" style="color:var(--brand);">/lokasyon/<?= e($l['slug']) ?></a>
        </td>
        <td><?= number_format((int)($l['view_count'] ?? 0)) ?></td>
        <td><span class="badge badge-<?= $l['is_active'] ? 'success' : 'gray' ?>"><?= $l['is_active'] ? 'Aktif' : 'Pasif' ?></span></td>
        <td class="table-actions">
          <a href="?action=edit&id=<?= $l['id'] ?>" class="btn btn-outline btn-sm">Düzenle</a>
          <a href="?action=delete&id=<?= $l['id'] ?>" class="btn btn-outline btn-sm" style="color:var(--warning, #d97706);" onclick="return confirm('Bu lokasyon PASİF hale getirilecek (kayıt silinmeyecek). Emin misiniz?')">Pasifle</a>
          <a href="?action=hard_delete&id=<?= $l['id'] ?>" class="btn btn-outline btn-sm" style="color:#fff; background:var(--danger, #dc2626); border-color:var(--danger, #dc2626);" onclick="return confirm('⚠ DİKKAT!\n\nBu lokasyon TAMAMEN SİLİNECEK.\n\nKayıt: <?= addslashes($l['name_tr']) ?> (<?= addslashes($l['slug']) ?>)\n\nGeri alınamaz! Devam etmek istiyor musunuz?')">Sil</a>
        </td>
      </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>
<?php endif ?>
</div>

<?php endif ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
