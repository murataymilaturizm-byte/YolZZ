<?php
$page_title = 'Araçlar';
$active_menu = 'vehicles';

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/header.php';

// Silme işlemi
if (($_GET['action'] ?? '') === 'delete' && !empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $vehicle = db()->fetch("SELECT * FROM vehicles WHERE id = ?", [$id]);
    if ($vehicle) {
        // API'den gelen araç mı?
        if ($vehicle['source_type'] === 'api') {
            flash_error('API üzerinden gelen araçlar buradan silinemez. Pasif hale getirebilirsiniz.');
        } else {
            // İlgili rezervasyon var mı?
            $hasBookings = db()->fetchColumn("SELECT COUNT(*) FROM bookings WHERE vehicle_id = ?", [$id]);
            if ($hasBookings > 0) {
                db()->update('vehicles', ['status' => 'inactive'], 'id = :id', ['id' => $id]);
                flash_warning('Araç aktif rezervasyona sahip olduğu için silinmedi, pasif hale getirildi.');
            } else {
                db()->delete('vehicles', 'id = :id', ['id' => $id]);
                log_activity('delete', 'vehicles', "Araç silindi: {$vehicle['model']}", $id, 'vehicle');
                flash_success('Araç başarıyla silindi.');
            }
        }
    }
    redirect(admin_url('modules/vehicles.php'));
}

// Durum değiştirme (aktif/pasif toggle)
if (($_GET['action'] ?? '') === 'toggle' && !empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $v = db()->fetch("SELECT status FROM vehicles WHERE id = ?", [$id]);
    if ($v) {
        $newStatus = $v['status'] === 'active' ? 'inactive' : 'active';
        db()->update('vehicles', ['status' => $newStatus], 'id = :id', ['id' => $id]);
        flash_success('Araç durumu güncellendi.');
    }
    redirect(admin_url('modules/vehicles.php'));
}

// Filtreler
$search = get('q');
$category_filter = (int)get('category');
$brand_filter = (int)get('brand');
$status_filter = get('status', '');
$source_filter = get('source', '');

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(v.model LIKE :s OR vb.name LIKE :s2)";
    $params['s'] = "%$search%";
    $params['s2'] = "%$search%";
}
if ($category_filter) { $where[] = "v.category_id = :cat"; $params['cat'] = $category_filter; }
if ($brand_filter) { $where[] = "v.brand_id = :br"; $params['br'] = $brand_filter; }
if ($status_filter) { $where[] = "v.status = :st"; $params['st'] = $status_filter; }
if ($source_filter) { $where[] = "v.source_type = :src"; $params['src'] = $source_filter; }

$whereSql = implode(' AND ', $where);

// Sayfalama
$page = max(1, (int)get('page', 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$total = (int)db()->fetchColumn(
    "SELECT COUNT(*) FROM vehicles v
     LEFT JOIN vehicle_brands vb ON vb.id = v.brand_id
     WHERE $whereSql",
    $params
);

$vehicles = db()->fetchAll(
    "SELECT v.*, vb.name AS brand_name, vc.name_tr AS category_name, o.name AS office_name
     FROM vehicles v
     LEFT JOIN vehicle_brands vb ON vb.id = v.brand_id
     LEFT JOIN vehicle_categories vc ON vc.id = v.category_id
     LEFT JOIN offices o ON o.id = v.office_id
     WHERE $whereSql
     ORDER BY v.sort_order ASC, v.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

$categories = db()->fetchAll("SELECT * FROM vehicle_categories WHERE is_active = 1 ORDER BY sort_order");
$brands = db()->fetchAll("SELECT * FROM vehicle_brands WHERE is_active = 1 ORDER BY name");

$totalPages = (int)ceil($total / $perPage);
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Araç Yönetimi</h2>
    <div class="subtitle"><?= number_format($total) ?> araç listeleniyor</div>
  </div>
  <div style="display:flex; gap:8px;">
    <a href="<?= admin_url('modules/api-providers.php') ?>" class="btn btn-outline">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
      API Senkronize
    </a>
    <a href="<?= admin_url('modules/vehicle-edit.php') ?>" class="btn btn-primary">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Yeni Araç Ekle
    </a>
  </div>
</div>

<!-- FİLTRELER -->
<form method="GET" class="filters-bar">
  <div class="search-input-wrap" style="flex:2;">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
    <input type="text" name="q" class="form-input search-input" placeholder="Marka veya model ara..." value="<?= e($search) ?>">
  </div>
  <select name="category" class="form-select" style="max-width:160px;">
    <option value="">Tüm Kategoriler</option>
    <?php foreach ($categories as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $category_filter == $c['id'] ? 'selected' : '' ?>><?= e($c['name_tr']) ?></option>
    <?php endforeach ?>
  </select>
  <select name="brand" class="form-select" style="max-width:140px;">
    <option value="">Tüm Markalar</option>
    <?php foreach ($brands as $b): ?>
      <option value="<?= $b['id'] ?>" <?= $brand_filter == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
    <?php endforeach ?>
  </select>
  <select name="status" class="form-select" style="max-width:130px;">
    <option value="">Tüm Durumlar</option>
    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Aktif</option>
    <option value="maintenance" <?= $status_filter === 'maintenance' ? 'selected' : '' ?>>Bakımda</option>
    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Pasif</option>
  </select>
  <select name="source" class="form-select" style="max-width:130px;">
    <option value="">Tüm Kaynaklar</option>
    <option value="manual" <?= $source_filter === 'manual' ? 'selected' : '' ?>>Manuel</option>
    <option value="api" <?= $source_filter === 'api' ? 'selected' : '' ?>>API</option>
  </select>
  <button type="submit" class="btn btn-secondary">Filtrele</button>
  <?php if ($search || $category_filter || $brand_filter || $status_filter || $source_filter): ?>
    <a href="<?= admin_url('modules/vehicles.php') ?>" class="btn btn-outline">Temizle</a>
  <?php endif ?>
</form>

<!-- LİSTE -->
<div class="card">
<?php if (empty($vehicles)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">🚗</div>
    <div class="empty-state-title">Araç bulunamadı</div>
    <div class="empty-state-text">
      <?= $search || $category_filter || $brand_filter ? 'Arama kriterlerine uygun araç yok.' : 'Henüz araç eklenmemiş. Başlamak için bir araç ekleyin.' ?>
    </div>
    <a href="<?= admin_url('modules/vehicle-edit.php') ?>" class="btn btn-primary">İlk Aracı Ekle</a>
  </div>
<?php else: ?>
<div class="table-wrapper" style="border:0; border-radius:0;">
  <table class="table">
    <thead>
      <tr>
        <th style="width:70px;">Görsel</th>
        <th>Marka / Model</th>
        <th>Kategori</th>
        <th>Özellikler</th>
        <th>Ofis</th>
        <th>Fiyat (Günlük)</th>
        <th>Kaynak</th>
        <th>Durum</th>
        <th style="width:180px;"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($vehicles as $v): ?>
      <tr>
        <td>
          <?php if ($v['main_image']): ?>
            <img src="<?= upload_url($v['main_image']) ?>" alt="" style="width:50px; height:40px; object-fit:cover; border-radius:6px; background:#f5f8fc;">
          <?php else: ?>
            <div style="width:50px; height:40px; background:var(--bg-soft); border-radius:6px; display:flex; align-items:center; justify-content:center; color:var(--text-muted); font-size:18px;">🚗</div>
          <?php endif ?>
        </td>
        <td>
          <strong><?= e($v['brand_name'] . ' ' . $v['model']) ?></strong>
          <div style="font-size:11px; color:var(--text-muted);"><?= $v['year'] ?> Model</div>
        </td>
        <td><span class="badge badge-gray"><?= e($v['category_name']) ?></span></td>
        <td style="font-size:12px;">
          <?= e($v['fuel_type']) ?> · <?= e($v['transmission']) ?><br>
          <span style="color:var(--text-muted);"><?= $v['seats'] ?> Kişi · <?= $v['luggage'] ?> Bagaj</span>
        </td>
        <td style="font-size:12px;"><?= e($v['office_name'] ?? '—') ?></td>
        <td><strong><?= tl($v['daily_price']) ?></strong></td>
        <td>
          <?php if ($v['source_type'] === 'api'): ?>
            <span class="badge badge-info">🔌 API</span>
          <?php else: ?>
            <span class="badge badge-gray">Manuel</span>
          <?php endif ?>
        </td>
        <td>
          <?php
          $statusMap = [
            'active' => ['success','Aktif'],
            'maintenance' => ['warning','Bakımda'],
            'inactive' => ['danger','Pasif']
          ];
          $s = $statusMap[$v['status']] ?? ['gray', $v['status']];
          ?>
          <span class="badge badge-<?= $s[0] ?>"><?= $s[1] ?></span>
        </td>
        <td class="table-actions">
          <a href="<?= admin_url('modules/vehicles.php?action=toggle&id=' . $v['id']) ?>" class="btn btn-outline btn-sm" title="Aktif/Pasif">
            <?= $v['status'] === 'active' ? '⏸' : '▶' ?>
          </a>
          <a href="<?= admin_url('modules/vehicle-edit.php?id=' . $v['id']) ?>" class="btn btn-outline btn-sm">Düzenle</a>
          <a href="<?= admin_url('modules/vehicles.php?action=delete&id=' . $v['id']) ?>" class="btn btn-outline btn-sm" style="color:var(--danger);" onclick="return confirm('Bu aracı silmek istediğinize emin misiniz?');">Sil</a>
        </td>
      </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php
  $qs = $_GET; unset($qs['page']);
  $base = '?' . http_build_query($qs) . '&page=';
  ?>
  <a href="<?= $base . max(1, $page - 1) ?>" class="<?= $page === 1 ? 'disabled' : '' ?>">‹ Önceki</a>
  <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
    <a href="<?= $base . $p ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
  <?php endfor ?>
  <a href="<?= $base . min($totalPages, $page + 1) ?>" class="<?= $page === $totalPages ? 'disabled' : '' ?>">Sonraki ›</a>
</div>
<?php endif ?>

<?php endif ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
