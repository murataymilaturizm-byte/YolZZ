<?php
$page_title = 'Bayiler';
$active_menu = 'dealers';

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/header.php';

// Durum güncelleme
if (($_GET['action'] ?? '') === 'approve' && !empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    db()->update('dealers', ['status' => 'approved', 'approved_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
    log_activity('approve', 'dealers', 'Bayi onaylandı', $id, 'dealer');
    flash_success('Bayi onaylandı.');
    redirect(admin_url('modules/dealers.php'));
}
if (($_GET['action'] ?? '') === 'reject' && !empty($_GET['id'])) {
    db()->update('dealers', ['status' => 'rejected'], 'id = :id', ['id' => (int)$_GET['id']]);
    flash_warning('Bayi başvurusu reddedildi.');
    redirect(admin_url('modules/dealers.php'));
}

$status_filter = get('status', '');
$where = ['1=1']; $params = [];
if ($status_filter) { $where[] = "status = :st"; $params['st'] = $status_filter; }

$dealers = db()->fetchAll(
    "SELECT * FROM dealers WHERE " . implode(' AND ', $where) . " ORDER BY
     CASE status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'suspended' THEN 3 ELSE 4 END,
     created_at DESC",
    $params
);

$counts = db()->fetch("SELECT
    SUM(status='pending') AS pending,
    SUM(status='approved') AS approved,
    SUM(status='rejected') AS rejected,
    SUM(status='suspended') AS suspended FROM dealers");
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Bayi Yönetimi</h2>
    <div class="subtitle"><?= count($dealers) ?> bayi listeleniyor</div>
  </div>
</div>

<div class="stats-grid" style="margin-bottom:16px;">
  <a href="?status=pending" class="stat-card" style="text-decoration:none;">
    <div class="stat-icon yellow">⏳</div>
    <div><div class="stat-label">Beklemede</div><div class="stat-value"><?= (int)$counts['pending'] ?></div></div>
  </a>
  <a href="?status=approved" class="stat-card" style="text-decoration:none;">
    <div class="stat-icon green">✓</div>
    <div><div class="stat-label">Onaylı</div><div class="stat-value"><?= (int)$counts['approved'] ?></div></div>
  </a>
  <a href="?status=suspended" class="stat-card" style="text-decoration:none;">
    <div class="stat-icon orange">⚠</div>
    <div><div class="stat-label">Askıda</div><div class="stat-value"><?= (int)$counts['suspended'] ?></div></div>
  </a>
  <a href="?" class="stat-card" style="text-decoration:none;">
    <div class="stat-icon blue">🤝</div>
    <div><div class="stat-label">Toplam</div><div class="stat-value"><?= (int)($counts['pending']+$counts['approved']+$counts['suspended']+$counts['rejected']) ?></div></div>
  </a>
</div>

<div class="card">
<?php if (empty($dealers)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">🤝</div>
    <div class="empty-state-title">Bayi bulunamadı</div>
    <div class="empty-state-text">Bu durumda bayi yok.</div>
  </div>
<?php else: ?>
<div class="table-wrapper" style="border:0; border-radius:0;">
  <table class="table">
    <thead>
      <tr>
        <th>Firma</th>
        <th>Yetkili</th>
        <th>Şehir</th>
        <th>Filo</th>
        <th>Durum</th>
        <th>Başvuru</th>
        <th style="width:200px;"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($dealers as $d): ?>
      <tr>
        <td>
          <strong><?= e($d['company_name']) ?></strong>
          <div style="font-size:11px; color:var(--text-muted);"><?= e($d['email']) ?></div>
        </td>
        <td>
          <?= e($d['contact_name']) ?><br>
          <span style="font-size:11px; color:var(--text-muted);"><?= e($d['phone']) ?></span>
        </td>
        <td><?= e($d['city']) ?><?= $d['district'] ? ' / ' . e($d['district']) : '' ?></td>
        <td><span class="badge badge-gray"><?= (int)$d['fleet_size'] ?> araç</span></td>
        <td>
          <?php
          $statusMap = [
            'pending' => ['warning','Beklemede'],'approved' => ['success','Onaylı'],
            'rejected' => ['danger','Reddedildi'],'suspended' => ['gray','Askıda']
          ];
          $s = $statusMap[$d['status']] ?? ['gray', $d['status']];
          ?>
          <span class="badge badge-<?= $s[0] ?>"><?= $s[1] ?></span>
        </td>
        <td style="font-size:12px;"><?= tr_date($d['created_at']) ?></td>
        <td class="table-actions">
          <?php if ($d['status'] === 'pending'): ?>
            <a href="?action=approve&id=<?= $d['id'] ?>" class="btn btn-success btn-sm">Onayla</a>
            <a href="?action=reject&id=<?= $d['id'] ?>" class="btn btn-outline btn-sm" style="color:var(--danger);">Reddet</a>
          <?php else: ?>
            <a href="<?= admin_url('modules/dealer-detail.php?id=' . $d['id']) ?>" class="btn btn-outline btn-sm">Detay</a>
          <?php endif ?>
        </td>
      </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>
<?php endif ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
