<?php
$page_title = 'Aktivite Logları';
$active_menu = 'activity-logs';

require_once __DIR__ . '/../../includes/bootstrap.php';
require_role(['super_admin', 'admin']);
require_once __DIR__ . '/../includes/header.php';

$user_filter = (int)get('user_id');
$action_filter = get('action_type', '');

$where = ['1=1']; $params = [];
if ($user_filter) { $where[] = "l.admin_user_id = :uid"; $params['uid'] = $user_filter; }
if ($action_filter) { $where[] = "l.action = :act"; $params['act'] = $action_filter; }

$page = max(1, (int)get('page', 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$total = (int)db()->fetchColumn(
    "SELECT COUNT(*) FROM activity_logs l WHERE " . implode(' AND ', $where), $params
);

$logs = db()->fetchAll(
    "SELECT l.*, au.name AS user_name FROM activity_logs l
     LEFT JOIN admin_users au ON au.id = l.admin_user_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY l.created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);

$users = db()->fetchAll("SELECT id, name FROM admin_users ORDER BY name");
$actions = db()->fetchAll("SELECT DISTINCT action FROM activity_logs ORDER BY action");
$totalPages = ceil($total / $perPage);
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Aktivite Logları</h2>
    <div class="subtitle"><?= number_format($total) ?> kayıt</div>
  </div>
</div>

<form method="GET" class="filters-bar">
  <select name="user_id" class="form-select" style="max-width:200px;">
    <option value="">Tüm Kullanıcılar</option>
    <?php foreach ($users as $u): ?>
      <option value="<?= $u['id'] ?>" <?= $user_filter == $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
    <?php endforeach ?>
  </select>
  <select name="action_type" class="form-select" style="max-width:200px;">
    <option value="">Tüm Eylemler</option>
    <?php foreach ($actions as $a): ?>
      <option value="<?= e($a['action']) ?>" <?= $action_filter === $a['action'] ? 'selected' : '' ?>><?= e($a['action']) ?></option>
    <?php endforeach ?>
  </select>
  <button type="submit" class="btn btn-secondary">Filtrele</button>
  <?php if ($user_filter || $action_filter): ?>
    <a href="<?= admin_url('modules/activity-logs.php') ?>" class="btn btn-outline">Temizle</a>
  <?php endif ?>
</form>

<div class="card">
<?php if (empty($logs)): ?>
  <div class="empty-state"><div class="empty-state-icon">📜</div><div class="empty-state-title">Log yok</div></div>
<?php else: ?>
<div class="table-wrapper" style="border:0; border-radius:0;">
  <table class="table">
    <thead><tr><th>Tarih</th><th>Kullanıcı</th><th>Eylem</th><th>Modül</th><th>Açıklama</th><th>IP</th></tr></thead>
    <tbody>
      <?php foreach ($logs as $l): ?>
      <tr>
        <td style="font-size:12px;"><?= tr_date($l['created_at'], true) ?></td>
        <td><?= e($l['user_name'] ?? 'Sistem') ?></td>
        <td><span class="badge badge-gray"><?= e($l['action']) ?></span></td>
        <td style="font-size:12px;"><?= e($l['module']) ?></td>
        <td><?= e($l['description']) ?></td>
        <td style="font-family:monospace; font-size:11px; color:var(--text-muted);"><?= e($l['ip_address']) ?></td>
      </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php $qs = $_GET; unset($qs['page']); $base = '?' . http_build_query($qs) . '&page='; ?>
  <?php for ($p = max(1,$page-2); $p <= min($totalPages, $page+2); $p++): ?>
    <a href="<?= $base.$p ?>" class="<?= $p===$page?'active':'' ?>"><?= $p ?></a>
  <?php endfor ?>
</div>
<?php endif ?>
<?php endif ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
