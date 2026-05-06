<?php
$page_title = 'Rezervasyonlar';
$active_menu = 'bookings';

require_once __DIR__ . '/../../includes/bootstrap.php';

// Durum güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'update_status') {
    require_auth();
    $id = (int)post('id');
    $newStatus = post('new_status');
    $allowed = ['pending','confirmed','in_progress','completed','cancelled','no_show'];
    if ($id && in_array($newStatus, $allowed)) {
        $updates = ['status' => $newStatus];
        if ($newStatus === 'cancelled') {
            $updates['cancelled_at'] = date('Y-m-d H:i:s');
        }
        db()->update('bookings', $updates, 'id = :id', ['id' => $id]);
        log_activity('status_change', 'bookings', "Rezervasyon durumu → $newStatus", $id, 'booking');
        flash_success('Durum güncellendi.');
    }
    redirect(admin_url('modules/bookings.php'));
}

require_once __DIR__ . '/../includes/header.php';

$search = get('q');
$status = get('status', '');
$from = get('from', '');
$to = get('to', '');

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(b.booking_code LIKE :s OR b.guest_phone LIKE :s2 OR b.guest_email LIKE :s3
                 OR c.first_name LIKE :s4 OR c.last_name LIKE :s5)";
    $params['s'] = "%$search%"; $params['s2'] = "%$search%"; $params['s3'] = "%$search%";
    $params['s4'] = "%$search%"; $params['s5'] = "%$search%";
}
if ($status) { $where[] = "b.status = :st"; $params['st'] = $status; }
if ($from) { $where[] = "b.pickup_datetime >= :fr"; $params['fr'] = $from . ' 00:00:00'; }
if ($to) { $where[] = "b.pickup_datetime <= :to_"; $params['to_'] = $to . ' 23:59:59'; }

$whereSql = implode(' AND ', $where);

$page = max(1, (int)get('page', 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$total = (int)db()->fetchColumn(
    "SELECT COUNT(*) FROM bookings b LEFT JOIN customers c ON c.id = b.customer_id WHERE $whereSql",
    $params
);

$bookings = db()->fetchAll(
    "SELECT b.*,
            CONCAT(COALESCE(c.first_name, b.guest_first_name), ' ', COALESCE(c.last_name, b.guest_last_name)) AS customer_name,
            COALESCE(c.phone, b.guest_phone) AS customer_phone,
            vb.name AS brand_name, v.model AS vehicle_model,
            po.name AS pickup_office_name, ro.name AS return_office_name,
            ap.name AS api_provider_name, ap.display_name AS api_provider_display_name
     FROM bookings b
     LEFT JOIN customers c ON c.id = b.customer_id
     LEFT JOIN vehicles v ON v.id = b.vehicle_id
     LEFT JOIN vehicle_brands vb ON vb.id = v.brand_id
     LEFT JOIN offices po ON po.id = b.pickup_office_id
     LEFT JOIN offices ro ON ro.id = b.return_office_id
     LEFT JOIN api_providers ap ON ap.id = b.api_provider_id
     WHERE $whereSql
     ORDER BY b.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

$totalPages = (int)ceil($total / $perPage);

// İstatistik
$stats = db()->fetch("
    SELECT
        SUM(status = 'pending') AS pending,
        SUM(status = 'confirmed') AS confirmed,
        SUM(status = 'in_progress') AS in_progress,
        SUM(status = 'completed') AS completed,
        SUM(status = 'cancelled') AS cancelled
    FROM bookings
");

$status_labels = [
    'pending' => ['Beklemede', 'warning', '⏳'],
    'confirmed' => ['Onaylı', 'info', '✓'],
    'in_progress' => ['Devam Ediyor', 'brand', '🚗'],
    'completed' => ['Tamamlandı', 'success', '✅'],
    'cancelled' => ['İptal', 'danger', '✕'],
    'no_show' => ['Gelmedi', 'gray', '❌']
];
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Rezervasyon Yönetimi</h2>
    <div class="subtitle"><?= number_format($total) ?> rezervasyon</div>
  </div>
  <div style="display:flex; gap:8px;">
    <a href="<?= admin_url('modules/booking-create.php') ?>" class="btn btn-primary">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Manuel Rezervasyon
    </a>
  </div>
</div>

<!-- HIZLI İSTATİSTİK -->
<div class="stats-grid" style="margin-bottom:16px;">
  <a href="?status=pending" class="stat-card" style="text-decoration:none;">
    <div class="stat-icon yellow">⏳</div>
    <div>
      <div class="stat-label">Beklemede</div>
      <div class="stat-value"><?= (int)$stats['pending'] ?></div>
    </div>
  </a>
  <a href="?status=confirmed" class="stat-card" style="text-decoration:none;">
    <div class="stat-icon blue">✓</div>
    <div>
      <div class="stat-label">Onaylı</div>
      <div class="stat-value"><?= (int)$stats['confirmed'] ?></div>
    </div>
  </a>
  <a href="?status=in_progress" class="stat-card" style="text-decoration:none;">
    <div class="stat-icon orange">🚗</div>
    <div>
      <div class="stat-label">Devam Eden</div>
      <div class="stat-value"><?= (int)$stats['in_progress'] ?></div>
    </div>
  </a>
  <a href="?status=completed" class="stat-card" style="text-decoration:none;">
    <div class="stat-icon green">✅</div>
    <div>
      <div class="stat-label">Tamamlanan</div>
      <div class="stat-value"><?= (int)$stats['completed'] ?></div>
    </div>
  </a>
</div>

<!-- FİLTRELER -->
<form method="GET" class="filters-bar">
  <div class="search-input-wrap" style="flex:2;">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
    <input type="text" name="q" class="form-input search-input" placeholder="Kod, telefon, e-posta, ad..." value="<?= e($search) ?>">
  </div>
  <select name="status" class="form-select" style="max-width:160px;">
    <option value="">Tüm Durumlar</option>
    <?php foreach ($status_labels as $val => $info): ?>
      <option value="<?= $val ?>" <?= $status === $val ? 'selected' : '' ?>><?= $info[0] ?></option>
    <?php endforeach ?>
  </select>
  <input type="date" name="from" class="form-input" style="max-width:150px;" value="<?= e($from) ?>" title="Başlangıç">
  <input type="date" name="to" class="form-input" style="max-width:150px;" value="<?= e($to) ?>" title="Bitiş">
  <button type="submit" class="btn btn-secondary">Filtrele</button>
  <?php if ($search || $status || $from || $to): ?>
    <a href="<?= admin_url('modules/bookings.php') ?>" class="btn btn-outline">Temizle</a>
  <?php endif ?>
</form>

<div class="card">
<?php if (empty($bookings)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">📋</div>
    <div class="empty-state-title">Rezervasyon bulunamadı</div>
    <div class="empty-state-text">Filtrelerinize uygun rezervasyon yok.</div>
  </div>
<?php else: ?>
<div class="table-wrapper" style="border:0; border-radius:0;">
  <table class="table">
    <thead>
      <tr>
        <th>Kod</th>
        <th>Müşteri</th>
        <th>Araç</th>
        <th>Kaynak</th>
        <th>Ofis (Alış → İade)</th>
        <th>Tarih</th>
        <th>Tutar</th>
        <th>Durum</th>
        <th style="width:140px;"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($bookings as $b):
        $st = $status_labels[$b['status']] ?? [$b['status'], 'gray', ''];
        $isApi = !empty($b['api_provider_id']);
        // API rezervasyonsa araç adını api_extra_data'dan al
        $apiCarName = '';
        if ($isApi && !empty($b['api_extra_data'])) {
            $apiData = json_decode($b['api_extra_data'], true);
            $apiCarName = $apiData['car_name'] ?? '';
        }
      ?>
      <tr>
        <td>
          <strong style="color:var(--brand);"><?= e($b['booking_code']) ?></strong>
          <div style="font-size:11px; color:var(--text-muted);"><?= tr_date($b['created_at'], true) ?></div>
        </td>
        <td>
          <strong><?= e(trim($b['customer_name']) ?: '—') ?></strong>
          <div style="font-size:11px; color:var(--text-muted);"><?= e($b['customer_phone']) ?></div>
        </td>
        <td>
          <?php if ($isApi): ?>
            <?= e($apiCarName ?: '—') ?>
          <?php else: ?>
            <?= e(trim(($b['brand_name'] ?? '') . ' ' . ($b['vehicle_model'] ?? '')) ?: '—') ?>
          <?php endif ?>
        </td>
        <td>
          <?php if ($isApi): ?>
            <?php
              $providerName = $b['api_provider_display_name'] ?: $b['api_provider_name'];
              $apiStatusBadge = '';
              switch ($b['api_status']) {
                case 'sent':      $apiStatusBadge = '<span title="API\'ye gönderildi" style="color:#2E7D32;">✓</span>'; break;
                case 'failed':    $apiStatusBadge = '<span title="API hatası" style="color:#C62828;">✗</span>'; break;
                case 'pending':   $apiStatusBadge = '<span title="Gönderilmedi (test modu veya beklemede)" style="color:#E65100;">⏳</span>'; break;
                case 'confirmed': $apiStatusBadge = '<span title="Onaylı" style="color:#2E7D32;">✓✓</span>'; break;
              }
            ?>
            <span style="background:#1d71b8; color:#fff; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600;">
              <?= e($providerName) ?>
            </span>
            <?= $apiStatusBadge ?>
          <?php else: ?>
            <span style="color:var(--text-muted); font-size:11px;">📝 Manuel</span>
          <?php endif ?>
        </td>
        <td style="font-size:12px;">
          <?= e($b['pickup_office_name'] ?? '—') ?><br>
          <?php if ($b['pickup_office_id'] != $b['return_office_id']): ?>
            <span style="color:var(--text-muted);">→ <?= e($b['return_office_name'] ?? '—') ?></span>
          <?php endif ?>
        </td>
        <td style="font-size:12px;">
          <?= tr_date($b['pickup_datetime'], true) ?><br>
          <span style="color:var(--text-muted);">→ <?= tr_date($b['return_datetime'], true) ?></span>
          <div><span class="badge badge-gray"><?= $b['total_days'] ?> gün</span></div>
        </td>
        <td>
          <strong><?= tl($b['grand_total']) ?></strong>
          <?php if ($b['payment_status'] === 'paid'): ?>
            <div><span class="badge badge-success" style="font-size:10px;">Ödendi</span></div>
          <?php elseif ($b['payment_status'] === 'pending'): ?>
            <div><span class="badge badge-warning" style="font-size:10px;">Bekliyor</span></div>
          <?php endif ?>
        </td>
        <td><span class="badge badge-<?= $st[1] ?>"><?= $st[2] ?> <?= $st[0] ?></span></td>
        <td class="table-actions">
          <a href="<?= admin_url('modules/booking-detail.php?id=' . $b['id']) ?>" class="btn btn-outline btn-sm">Detay</a>
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
  <a href="<?= $base . max(1, $page - 1) ?>" class="<?= $page === 1 ? 'disabled' : '' ?>">‹</a>
  <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
    <a href="<?= $base . $p ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
  <?php endfor ?>
  <a href="<?= $base . min($totalPages, $page + 1) ?>" class="<?= $page === $totalPages ? 'disabled' : '' ?>">›</a>
</div>
<?php endif ?>
<?php endif ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
