<?php
$page_title = 'Dashboard';
$active_menu = 'dashboard';

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/header.php';

// İstatistikler
$total_vehicles = db()->fetchColumn("SELECT COUNT(*) FROM vehicles WHERE status = 'active'") ?: 0;
$total_bookings = db()->fetchColumn("SELECT COUNT(*) FROM bookings") ?: 0;
$pending_bookings_count = db()->fetchColumn("SELECT COUNT(*) FROM bookings WHERE status = 'pending'") ?: 0;
$total_customers = db()->fetchColumn("SELECT COUNT(*) FROM customers") ?: 0;
$total_offices = db()->fetchColumn("SELECT COUNT(*) FROM offices WHERE is_active = 1") ?: 0;
$total_revenue_month = db()->fetchColumn(
    "SELECT COALESCE(SUM(grand_total), 0) FROM bookings
     WHERE status IN ('confirmed','in_progress','completed')
     AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())"
) ?: 0;
$unread_msg = db()->fetchColumn("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'") ?: 0;
$pending_dealers = db()->fetchColumn("SELECT COUNT(*) FROM dealers WHERE status = 'pending'") ?: 0;

// Son rezervasyonlar
$recent_bookings = db()->fetchAll("
    SELECT b.*,
           CONCAT(COALESCE(c.first_name, b.guest_first_name), ' ', COALESCE(c.last_name, b.guest_last_name)) AS customer_name,
           v.model AS vehicle_model,
           vb.name AS vehicle_brand
    FROM bookings b
    LEFT JOIN customers c ON c.id = b.customer_id
    LEFT JOIN vehicles v ON v.id = b.vehicle_id
    LEFT JOIN vehicle_brands vb ON vb.id = v.brand_id
    ORDER BY b.created_at DESC
    LIMIT 8
");

// Son mesajlar
$recent_messages = db()->fetchAll("
    SELECT * FROM contact_messages
    WHERE status = 'new'
    ORDER BY created_at DESC
    LIMIT 5
");

// Son 7 günün rezervasyon grafiği
$chart_data = db()->fetchAll("
    SELECT DATE(created_at) AS day, COUNT(*) AS cnt
    FROM bookings
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");

// Grafiği 7 günlük diziye dönüştür
$chart_series = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = tr_date($date);
    $count = 0;
    foreach ($chart_data as $row) {
        if ($row['day'] === $date) { $count = (int)$row['cnt']; break; }
    }
    $chart_series[] = ['label' => date('d.m', strtotime($date)), 'count' => $count];
}

$status_labels = [
    'pending' => ['label' => 'Beklemede', 'class' => 'warning'],
    'confirmed' => ['label' => 'Onaylı', 'class' => 'info'],
    'in_progress' => ['label' => 'Devam', 'class' => 'brand'],
    'completed' => ['label' => 'Tamamlandı', 'class' => 'success'],
    'cancelled' => ['label' => 'İptal', 'class' => 'danger'],
    'no_show' => ['label' => 'Gelmedi', 'class' => 'gray']
];
?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon blue">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    </div>
    <div>
      <div class="stat-label">Aktif Araç</div>
      <div class="stat-value"><?= number_format($total_vehicles) ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon orange">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
    </div>
    <div>
      <div class="stat-label">Toplam Rezervasyon</div>
      <div class="stat-value"><?= number_format($total_bookings) ?></div>
      <?php if ($pending_bookings_count > 0): ?>
        <div class="stat-change up"><?= $pending_bookings_count ?> beklemede</div>
      <?php endif ?>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon green">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    <div>
      <div class="stat-label">Bu Ay Gelir</div>
      <div class="stat-value"><?= tl($total_revenue_month) ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon purple">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
    </div>
    <div>
      <div class="stat-label">Müşteri</div>
      <div class="stat-value"><?= number_format($total_customers) ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon yellow">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
    </div>
    <div>
      <div class="stat-label">Aktif Ofis</div>
      <div class="stat-value"><?= $total_offices ?></div>
    </div>
  </div>
</div>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px; margin-bottom:16px;" class="dashboard-grid">
  <!-- GRAFIK -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Son 7 Gün Rezervasyon Hareketi</div>
    </div>
    <div class="card-body">
      <canvas id="bookingsChart" height="80"></canvas>
    </div>
  </div>

  <!-- BEKLEYEN İŞLER -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Bekleyen İşler</div>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="pending-list">
        <a href="<?= admin_url('modules/bookings.php?status=pending') ?>" class="pending-row">
          <div class="pending-icon" style="background:var(--accent-wash);color:var(--accent);">🚘</div>
          <div>
            <div class="pending-label"><?= $pending_bookings_count ?> rezervasyon onay bekliyor</div>
            <div class="pending-sub">Detay için tıklayın</div>
          </div>
        </a>
        <a href="<?= admin_url('modules/messages.php?status=new') ?>" class="pending-row">
          <div class="pending-icon" style="background:var(--brand-wash);color:var(--brand);">💬</div>
          <div>
            <div class="pending-label"><?= $unread_msg ?> yeni mesaj</div>
            <div class="pending-sub">Okunmamış iletişim mesajları</div>
          </div>
        </a>
        <a href="<?= admin_url('modules/dealers.php?status=pending') ?>" class="pending-row">
          <div class="pending-icon" style="background:#F3E8FF;color:#8B5CF6;">🤝</div>
          <div>
            <div class="pending-label"><?= $pending_dealers ?> bayilik başvurusu</div>
            <div class="pending-sub">İncelenmeyi bekliyor</div>
          </div>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- SON REZERVASYONLAR -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Son Rezervasyonlar</div>
    <a href="<?= admin_url('modules/bookings.php') ?>" class="btn btn-outline btn-sm">Tümünü Gör →</a>
  </div>
  <?php if (empty($recent_bookings)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">📋</div>
      <div class="empty-state-title">Henüz rezervasyon yok</div>
      <div class="empty-state-text">İlk rezervasyon geldiğinde burada listelenecek.</div>
    </div>
  <?php else: ?>
  <div class="table-wrapper" style="border:0; border-radius:0;">
    <table class="table">
      <thead>
        <tr>
          <th>Kod</th>
          <th>Müşteri</th>
          <th>Araç</th>
          <th>Alış / İade</th>
          <th>Tutar</th>
          <th>Durum</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent_bookings as $b):
          $st = $status_labels[$b['status']] ?? ['label' => $b['status'], 'class' => 'gray'];
        ?>
        <tr>
          <td><span class="row-id"><?= e($b['booking_code']) ?></span></td>
          <td><strong><?= e(trim($b['customer_name']) ?: '—') ?></strong></td>
          <td><?= e(trim(($b['vehicle_brand'] ?? '') . ' ' . ($b['vehicle_model'] ?? '')) ?: '—') ?></td>
          <td style="font-size:12px;">
            <?= tr_date($b['pickup_datetime']) ?><br>
            <span style="color:var(--text-muted);">→ <?= tr_date($b['return_datetime']) ?></span>
          </td>
          <td><strong><?= tl($b['grand_total']) ?></strong></td>
          <td><span class="badge badge-<?= $st['class'] ?>"><?= $st['label'] ?></span></td>
          <td class="table-actions">
            <a href="<?= admin_url('modules/booking-detail.php?id=' . $b['id']) ?>" class="btn btn-outline btn-sm">Detay</a>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php endif ?>
</div>

<style>
.dashboard-grid { display: grid; }
@media (max-width: 991px) { .dashboard-grid { grid-template-columns: 1fr !important; } }
.pending-list { }
.pending-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 22px;
  border-bottom: 1px solid var(--border-soft);
  transition: background 0.15s;
}
.pending-row:last-child { border-bottom: 0; }
.pending-row:hover { background: var(--bg-soft); }
.pending-icon {
  width: 40px; height: 40px; border-radius: var(--radius);
  display: flex; align-items: center; justify-content: center;
  font-size: 18px;
}
.pending-label { font-size: 13px; font-weight: 600; color: var(--ink); }
.pending-sub { font-size: 11px; color: var(--text-muted); }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('bookingsChart');
if (ctx) {
  const labels = <?= json_encode(array_column($chart_series, 'label')) ?>;
  const data = <?= json_encode(array_column($chart_series, 'count')) ?>;
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Rezervasyon',
        data: data,
        borderColor: '#1d71b8',
        backgroundColor: 'rgba(29,113,184,0.1)',
        borderWidth: 2,
        tension: 0.35,
        fill: true,
        pointBackgroundColor: '#e94e1b',
        pointRadius: 4,
        pointHoverRadius: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: {
          beginAtZero: true,
          ticks: { precision: 0 },
          grid: { color: 'rgba(220,228,238,0.5)' }
        },
        x: { grid: { display: false } }
      }
    }
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
