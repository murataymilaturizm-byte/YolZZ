<?php
$page_title = 'Müşteriler';
$active_menu = 'customers';

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/header.php';

if (($_GET['action'] ?? '') === 'toggle' && !empty($_GET['id'])) {
    $cust = db()->fetch("SELECT is_active FROM customers WHERE id = ?", [(int)$_GET['id']]);
    if ($cust) {
        db()->update('customers', ['is_active' => !$cust['is_active']], 'id = :id', ['id' => (int)$_GET['id']]);
        flash_success('Müşteri durumu güncellendi.');
    }
    redirect(admin_url('modules/customers.php'));
}

$search = get('q');
$where = ['1=1']; $params = [];
if ($search) {
    $where[] = "(first_name LIKE :s OR last_name LIKE :s2 OR email LIKE :s3 OR phone LIKE :s4)";
    $params['s'] = "%$search%"; $params['s2'] = "%$search%"; $params['s3'] = "%$search%"; $params['s4'] = "%$search%";
}
$page = max(1, (int)get('page', 1)); $perPage = 25; $offset = ($page-1) * $perPage;
$total = (int)db()->fetchColumn("SELECT COUNT(*) FROM customers WHERE " . implode(' AND ', $where), $params);
$customers = db()->fetchAll(
    "SELECT c.*, (SELECT COUNT(*) FROM bookings WHERE customer_id = c.id) AS booking_count
     FROM customers c WHERE " . implode(' AND ', $where) . " ORDER BY c.created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);
$totalPages = ceil($total / $perPage);
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Müşteriler</h2>
    <div class="subtitle"><?= number_format($total) ?> kayıtlı müşteri</div>
  </div>
</div>

<form method="GET" class="filters-bar">
  <div class="search-input-wrap" style="flex:1;">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
    <input type="text" name="q" class="form-input search-input" placeholder="Ad, e-posta veya telefon ara..." value="<?= e($search) ?>">
  </div>
  <button type="submit" class="btn btn-secondary">Ara</button>
</form>

<div class="card">
<?php if (empty($customers)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">👥</div>
    <div class="empty-state-title">Müşteri yok</div>
    <div class="empty-state-text">Henüz kayıtlı müşteri bulunmuyor.</div>
  </div>
<?php else: ?>
<div class="table-wrapper" style="border:0; border-radius:0;">
  <table class="table">
    <thead>
      <tr>
        <th>Ad Soyad</th>
        <th>İletişim</th>
        <th>Şehir</th>
        <th>Rezervasyon</th>
        <th>Kayıt</th>
        <th>Durum</th>
        <th style="width:120px;"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($customers as $c): ?>
      <tr>
        <td>
          <strong><?= e($c['first_name'] . ' ' . $c['last_name']) ?></strong>
          <?php if ($c['is_verified']): ?><span class="badge badge-success" style="margin-left:6px; font-size:10px;">✓</span><?php endif ?>
        </td>
        <td style="font-size:12px;">
          <?= e($c['email']) ?><br>
          <span style="color:var(--text-muted);"><?= e($c['phone']) ?></span>
        </td>
        <td><?= e($c['city'] ?? '—') ?></td>
        <td><span class="badge badge-gray"><?= $c['booking_count'] ?></span></td>
        <td style="font-size:12px;"><?= tr_date($c['created_at']) ?></td>
        <td>
          <?php if ($c['is_active']): ?>
            <span class="badge badge-success">Aktif</span>
          <?php else: ?>
            <span class="badge badge-danger">Pasif</span>
          <?php endif ?>
        </td>
        <td class="table-actions">
          <a href="?action=toggle&id=<?= $c['id'] ?>" class="btn btn-outline btn-sm"><?= $c['is_active'] ? 'Pasifle' : 'Aktifle' ?></a>
        </td>
      </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php $qs = $_GET; unset($qs['page']); $base = '?' . http_build_query($qs) . '&page='; ?>
  <?php for ($p=1; $p<=$totalPages; $p++): ?>
    <a href="<?= $base.$p ?>" class="<?= $p===$page?'active':'' ?>"><?= $p ?></a>
  <?php endfor ?>
</div>
<?php endif ?>
<?php endif ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
