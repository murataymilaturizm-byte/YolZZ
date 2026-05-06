<?php
$page_title = 'Firmalar';
$active_menu = 'rental-companies';

require_once __DIR__ . '/../../includes/bootstrap.php';
require_auth();

// Toggle is_active
if (($_GET['action'] ?? '') === 'toggle' && !empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $row = db()->fetch("SELECT is_active FROM rental_companies WHERE id = ?", [$id]);
    if ($row) {
        db()->update('rental_companies', ['is_active' => !$row['is_active']], 'id = :id', ['id' => $id]);
        flash_success('Durum güncellendi.');
    }
    redirect(admin_url('modules/rental-companies.php'));
}

// Sil
if (($_GET['action'] ?? '') === 'delete' && !empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    // Bağlı araç var mı?
    $vehicleCount = (int)db()->fetchColumn("SELECT COUNT(*) FROM vehicles WHERE rental_company_id = ?", [$id]);
    if ($vehicleCount > 0) {
        flash_error("Bu firmaya bağlı $vehicleCount araç var. Önce o araçların firmasını değiştirin.");
    } else {
        // Logo dosyasını da sil
        $row = db()->fetch("SELECT logo FROM rental_companies WHERE id = ?", [$id]);
        if ($row && $row['logo']) {
            $logoPath = __DIR__ . '/../../uploads/' . $row['logo'];
            if (file_exists($logoPath)) @unlink($logoPath);
        }
        db()->query("DELETE FROM rental_companies WHERE id = ?", [$id]);
        flash_success('Firma silindi.');
    }
    redirect(admin_url('modules/rental-companies.php'));
}

require_once __DIR__ . '/../includes/header.php';

$companies = db()->fetchAll("
    SELECT c.*,
           (SELECT COUNT(*) FROM vehicles WHERE rental_company_id = c.id) AS vehicle_count
    FROM rental_companies c
    ORDER BY c.sort_order, c.name
");
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Kiralama Firmaları</h2>
    <div class="subtitle">Avis, Europcar gibi marka firmalar — manuel araçlarla ve API eşleştirmeleriyle kullanılır</div>
  </div>
  <div>
    <a href="<?= admin_url('modules/rental-company-edit.php') ?>" class="btn btn-primary">+ Yeni Firma</a>
  </div>
</div>

<div class="card" style="padding: 0;">
  <?php if (empty($companies)): ?>
    <div style="padding: 60px 20px; text-align: center;">
      <h3>Henüz firma eklenmemiş</h3>
      <p style="color: #666; margin-bottom: 16px;">İlk kiralama firmanı ekle (Avis, Europcar, Hertz vs.).</p>
      <a href="<?= admin_url('modules/rental-company-edit.php') ?>" class="btn btn-primary">+ İlk Firmayı Ekle</a>
    </div>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th style="width: 80px;">Logo</th>
          <th>Ad</th>
          <th>Slug</th>
          <th>Araç</th>
          <th>Durum</th>
          <th style="width: 220px;">İşlemler</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($companies as $c): ?>
          <tr>
            <td>
              <?php if ($c['logo']): ?>
                <img src="<?= upload_url($c['logo']) ?>" alt="<?= e($c['name']) ?>" style="max-width: 60px; max-height: 40px; object-fit: contain;">
              <?php else: ?>
                <span style="font-size:24px; color:#ccc;">🏢</span>
              <?php endif ?>
            </td>
            <td>
              <strong><?= e($c['name']) ?></strong>
            </td>
            <td><code><?= e($c['slug']) ?></code></td>
            <td>
              <?php if ($c['vehicle_count'] > 0): ?>
                <span class="badge badge-info"><?= (int)$c['vehicle_count'] ?> araç</span>
              <?php else: ?>
                <span style="color: #999; font-size: 12px;">—</span>
              <?php endif ?>
            </td>
            <td>
              <?php if ($c['is_active']): ?>
                <span class="badge badge-success">Aktif</span>
              <?php else: ?>
                <span class="badge badge-gray">Pasif</span>
              <?php endif ?>
            </td>
            <td class="table-actions">
              <a href="<?= admin_url('modules/rental-company-edit.php?id=' . $c['id']) ?>" class="btn btn-sm btn-outline">Düzenle</a>
              <a href="<?= admin_url('modules/rental-companies.php?action=toggle&id=' . $c['id']) ?>" class="btn btn-sm btn-outline">
                <?= $c['is_active'] ? 'Pasifleştir' : 'Aktifleştir' ?>
              </a>
              <?php if ($c['vehicle_count'] == 0): ?>
                <a href="<?= admin_url('modules/rental-companies.php?action=delete&id=' . $c['id']) ?>"
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('Silmek istediğinden emin misin?')">Sil</a>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
