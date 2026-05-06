<?php
$page_title = 'Ofisler';
$active_menu = 'offices';

require_once __DIR__ . '/../../includes/bootstrap.php';

$action = get('action');
$id = (int)get('id', 0);

// DELETE
if ($action === 'delete' && $id) {
    db()->update('offices', ['is_active' => 0], 'id = :id', ['id' => $id]);
    flash_success('Ofis pasif hale getirildi.');
    redirect(admin_url('modules/offices.php'));
}

// SAVE (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('_csrf'))) { flash_error('Güvenlik hatası.'); redirect(admin_url('modules/offices.php')); }

    $data = [
        'name' => post('name'),
        'slug' => slugify(post('name')),
        'city_id' => (int)post('city_id'),
        'district' => post('district'),
        'address' => post('address'),
        'phone' => post('phone'),
        'email' => post('email'),
        'latitude' => (float)post('latitude') ?: null,
        'longitude' => (float)post('longitude') ?: null,
        'working_hours' => post('working_hours', '08:00 - 22:00'),
        'is_airport' => (int)post('is_airport', 0),
        'airport_code' => post('airport_code'),
        'is_24h' => (int)post('is_24h', 0),
        'description' => post('description'),
        'is_active' => (int)post('is_active', 1),
        'show_in_search' => (int)post('show_in_search', 1),
        'show_on_page' => (int)post('show_on_page', 1),
        'sort_order' => (int)post('sort_order', 0)
    ];

    if (!empty($_FILES['image']['name'])) {
        $uploaded = upload_file($_FILES['image'], 'offices');
        if ($uploaded) $data['image'] = $uploaded;
    }

    if ($id) {
        db()->update('offices', $data, 'id = :id', ['id' => $id]);
        flash_success('Ofis güncellendi.');
    } else {
        db()->insert('offices', $data);
        flash_success('Ofis eklendi.');
    }
    redirect(admin_url('modules/offices.php'));
}

require_once __DIR__ . '/../includes/header.php';

$office = null;
if ($action === 'edit' && $id) {
    $office = db()->fetch("SELECT * FROM offices WHERE id = ?", [$id]);
}

// LIST veya EDIT görünümü
if ($action === 'edit' || $action === 'new'):
    $cities = db()->fetchAll("SELECT * FROM cities WHERE is_active = 1 ORDER BY name");
?>

<div class="page-header">
  <div class="page-header-left">
    <h2><?= $office ? 'Ofis Düzenle' : 'Yeni Ofis' ?></h2>
  </div>
  <a href="<?= admin_url('modules/offices.php') ?>" class="btn btn-outline">← Geri</a>
</div>

<form method="POST" enctype="multipart/form-data">
<?= csrf_field() ?>
<div class="card">
  <div class="card-body">
    <div class="form-grid">
      <div class="form-row">
        <label class="form-label">Ofis Adı <span class="required">*</span></label>
        <input type="text" name="name" class="form-input" value="<?= e($office['name'] ?? '') ?>" required>
      </div>
      <div class="form-row">
        <label class="form-label">Şehir <span class="required">*</span></label>
        <select name="city_id" class="form-select" required>
          <option value="">Seçin</option>
          <?php foreach ($cities as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($office['city_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="form-row">
        <label class="form-label">İlçe</label>
        <input type="text" name="district" class="form-input" value="<?= e($office['district'] ?? '') ?>">
      </div>
      <div class="form-row">
        <label class="form-label">Telefon</label>
        <input type="text" name="phone" class="form-input" value="<?= e($office['phone'] ?? '') ?>">
      </div>
      <div class="form-row">
        <label class="form-label">E-posta</label>
        <input type="email" name="email" class="form-input" value="<?= e($office['email'] ?? '') ?>">
      </div>
      <div class="form-row">
        <label class="form-label">Çalışma Saatleri</label>
        <input type="text" name="working_hours" class="form-input" value="<?= e($office['working_hours'] ?? '08:00 - 22:00') ?>">
      </div>
      <div class="form-row full">
        <label class="form-label">Adres</label>
        <textarea name="address" class="form-textarea" rows="2"><?= e($office['address'] ?? '') ?></textarea>
      </div>
      <div class="form-row">
        <label class="form-label">Enlem (Latitude)</label>
        <input type="text" name="latitude" class="form-input" value="<?= e($office['latitude'] ?? '') ?>">
      </div>
      <div class="form-row">
        <label class="form-label">Boylam (Longitude)</label>
        <input type="text" name="longitude" class="form-input" value="<?= e($office['longitude'] ?? '') ?>">
      </div>
      <div class="form-row">
        <label class="form-label">Havalimanı Kodu <span class="hint">IST, SAW, AYT...</span></label>
        <input type="text" name="airport_code" class="form-input" value="<?= e($office['airport_code'] ?? '') ?>">
      </div>
      <div class="form-row">
        <label class="form-label">Sıralama</label>
        <input type="number" name="sort_order" class="form-input" value="<?= (int)($office['sort_order'] ?? 0) ?>">
      </div>
      <div class="form-row full">
        <label class="form-label">Açıklama</label>
        <textarea name="description" class="form-textarea" rows="3"><?= e($office['description'] ?? '') ?></textarea>
      </div>
      <div class="form-row">
        <label class="form-label">Görsel</label>
        <input type="file" name="image" class="form-input" accept="image/*">
        <?php if (!empty($office['image'])): ?>
          <img src="<?= upload_url($office['image']) ?>" style="width:120px; margin-top:8px; border-radius:6px;">
        <?php endif ?>
      </div>
      <div class="form-row">
        <label class="form-label">&nbsp;</label>
        <label class="form-check"><input type="checkbox" name="is_airport" value="1" <?= !empty($office['is_airport']) ? 'checked' : '' ?>> Havalimanı ofisi</label>
        <label class="form-check" style="margin-top:6px;"><input type="checkbox" name="is_24h" value="1" <?= !empty($office['is_24h']) ? 'checked' : '' ?>> 24 Saat açık</label>
        <label class="form-check" style="margin-top:6px;"><input type="checkbox" name="is_active" value="1" <?= ($office['is_active'] ?? 1) ? 'checked' : '' ?>> Aktif</label>
        <label class="form-check" style="margin-top:6px;"><input type="checkbox" name="show_in_search" value="1" <?= ($office['show_in_search'] ?? 1) ? 'checked' : '' ?>> Arama formunda göster
          <span style="display:block; font-size:11px; color:var(--text-muted); margin-top:2px; margin-left:26px;">Kapatırsanız kullanıcılar bu ofisi dropdown'da göremez (dahili/gizli ofisler için).</span>
        </label>
        <label class="form-check" style="margin-top:6px;"><input type="checkbox" name="show_on_page" value="1" <?= ($office['show_on_page'] ?? 1) ? 'checked' : '' ?>> Ofislerimiz sayfasında göster
          <span style="display:block; font-size:11px; color:var(--text-muted); margin-top:2px; margin-left:26px;">Kapatırsanız bu ofis /ofislerimiz sayfasında listelenmez (sadece iç kullanım için).</span>
        </label>
      </div>
    </div>
    <div class="form-actions">
      <a href="<?= admin_url('modules/offices.php') ?>" class="btn btn-outline">İptal</a>
      <button type="submit" class="btn btn-primary">Kaydet</button>
    </div>
  </div>
</div>
</form>

<?php else:
    $search = get('q');
    $where = ['1=1']; $params = [];
    if ($search) {
        $where[] = "(o.name LIKE :s OR c.name LIKE :s2 OR o.district LIKE :s3)";
        $params['s'] = "%$search%"; $params['s2'] = "%$search%"; $params['s3'] = "%$search%";
    }
    $offices = db()->fetchAll(
        "SELECT o.*, c.name AS city_name FROM offices o JOIN cities c ON c.id = o.city_id
         WHERE " . implode(' AND ', $where) . " ORDER BY c.name, o.sort_order, o.name",
        $params
    );
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Ofisler (Fiziksel Şubeler)</h2>
    <div class="subtitle"><?= count($offices) ?> ofis · <em style="color:var(--text-muted);">SEO lokasyon sayfaları için "Lokasyonlar" menüsünü kullanın</em></div>
  </div>
  <a href="?action=new" class="btn btn-primary">+ Yeni Ofis</a>
</div>

<form method="GET" class="filters-bar">
  <div class="search-input-wrap" style="flex:1;">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
    <input type="text" name="q" class="form-input search-input" placeholder="Şehir, ofis adı ara..." value="<?= e($search) ?>">
  </div>
  <button type="submit" class="btn btn-secondary">Ara</button>
</form>

<div class="card">
<?php if (empty($offices)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">📍</div>
    <div class="empty-state-title">Ofis bulunamadı</div>
    <a href="?action=new" class="btn btn-primary">İlk Ofisi Ekle</a>
  </div>
<?php else: ?>
<div class="table-wrapper" style="border:0; border-radius:0;">
  <table class="table">
    <thead><tr><th>Ofis</th><th>Şehir</th><th>İletişim</th><th>Tip</th><th>Durum</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($offices as $o): ?>
      <tr>
        <td>
          <strong><?= e($o['name']) ?></strong>
          <?php if ($o['district']): ?><div style="font-size:11px; color:var(--text-muted);"><?= e($o['district']) ?></div><?php endif ?>
        </td>
        <td><?= e($o['city_name']) ?></td>
        <td style="font-size:12px;"><?= e($o['phone']) ?><br><span style="color:var(--text-muted);"><?= e($o['working_hours']) ?></span></td>
        <td>
          <?php if ($o['is_airport']): ?><span class="badge badge-info">✈ Havalimanı</span><?php endif ?>
          <?php if ($o['is_24h']): ?><span class="badge badge-success">24/7</span><?php endif ?>
          <?php if (empty($o['show_in_search'])): ?><span class="badge badge-gray" title="Arama formunda görünmez">🔒 Arama Gizli</span><?php endif ?>
          <?php if (empty($o['show_on_page'])): ?><span class="badge badge-gray" title="Ofislerimiz sayfasında görünmez">🙈 Sayfa Gizli</span><?php endif ?>
        </td>
        <td><span class="badge badge-<?= $o['is_active'] ? 'success' : 'gray' ?>"><?= $o['is_active'] ? 'Aktif' : 'Pasif' ?></span></td>
        <td class="table-actions">
          <a href="?action=edit&id=<?= $o['id'] ?>" class="btn btn-outline btn-sm">Düzenle</a>
          <a href="?action=delete&id=<?= $o['id'] ?>" class="btn btn-outline btn-sm" style="color:var(--danger);" onclick="return confirm('Pasif yapılsın mı?')">Pasifle</a>
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
