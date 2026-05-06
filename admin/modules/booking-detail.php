<?php
$page_title = 'Rezervasyon Detay';
$active_menu = 'bookings';

require_once __DIR__ . '/../../includes/bootstrap.php';

$id = (int)get('id', 0);

// Durum güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'update_status') {
    require_auth();
    if (!csrf_verify(post('_csrf'))) {
        flash_error('Güvenlik hatası.');
        redirect(admin_url('modules/booking-detail.php?id=' . $id));
    }
    $newStatus = post('status');
    $allowed = ['pending','confirmed','in_progress','completed','cancelled','no_show'];
    if (in_array($newStatus, $allowed)) {
        $upd = ['status' => $newStatus, 'admin_note' => post('admin_note')];
        if ($newStatus === 'cancelled') {
            $upd['cancelled_at'] = date('Y-m-d H:i:s');
            $upd['cancel_reason'] = post('cancel_reason');
        }
        db()->update('bookings', $upd, 'id = :id', ['id' => $id]);
        log_activity('status_change', 'bookings', "Durum: $newStatus", $id, 'booking');
        flash_success('Rezervasyon güncellendi.');
    }
    redirect(admin_url('modules/booking-detail.php?id=' . $id));
}

// Ödeme durumu güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'update_payment') {
    require_auth();
    $newPayment = post('payment_status');
    $paidAmount = (float)post('paid_amount');
    db()->update('bookings', [
        'payment_status' => $newPayment,
        'paid_amount' => $paidAmount,
        'payment_method' => post('payment_method')
    ], 'id = :id', ['id' => $id]);
    log_activity('payment_update', 'bookings', "Ödeme: $newPayment / " . tl($paidAmount), $id, 'booking');
    flash_success('Ödeme bilgisi güncellendi.');
    redirect(admin_url('modules/booking-detail.php?id=' . $id));
}

require_once __DIR__ . '/../includes/header.php';

$b = db()->fetch("
    SELECT b.*,
           CONCAT(COALESCE(c.first_name, b.guest_first_name), ' ', COALESCE(c.last_name, b.guest_last_name)) AS customer_name,
           COALESCE(c.email, b.guest_email) AS customer_email,
           COALESCE(c.phone, b.guest_phone) AS customer_phone,
           c.id AS customer_exists,
           vb.name AS brand_name, v.model AS vehicle_model, v.main_image AS vehicle_image,
           v.year AS vehicle_year, v.fuel_type AS vehicle_fuel, v.transmission AS vehicle_trans,
           po.name AS pickup_office_name, ro.name AS return_office_name,
           pc.name AS pickup_city, rc.name AS return_city
    FROM bookings b
    LEFT JOIN customers c ON c.id = b.customer_id
    LEFT JOIN vehicles v ON v.id = b.vehicle_id
    LEFT JOIN vehicle_brands vb ON vb.id = v.brand_id
    LEFT JOIN offices po ON po.id = b.pickup_office_id
    LEFT JOIN offices ro ON ro.id = b.return_office_id
    LEFT JOIN cities pc ON pc.id = po.city_id
    LEFT JOIN cities rc ON rc.id = ro.city_id
    LEFT JOIN api_providers ap ON ap.id = b.api_provider_id
    WHERE b.id = ?
", [$id]);

if (!$b) {
    flash_error('Rezervasyon bulunamadı.');
    redirect(admin_url('modules/bookings.php'));
}

// API rezervasyon bilgileri
$isApiBooking = !empty($b['api_provider_id']);
$apiData = $isApiBooking && !empty($b['api_extra_data']) ? json_decode($b['api_extra_data'], true) : null;
$apiResponse = $isApiBooking && !empty($b['api_response']) ? json_decode($b['api_response'], true) : null;
$apiProviderName = '';
if ($isApiBooking) {
    $apiProvider = db()->fetch("SELECT name, display_name FROM api_providers WHERE id = ?", [$b['api_provider_id']]);
    $apiProviderName = $apiProvider['display_name'] ?: $apiProvider['name'];
}

$status_labels = [
    'pending' => ['Beklemede', 'warning'],
    'confirmed' => ['Onaylı', 'info'],
    'in_progress' => ['Devam Ediyor', 'brand'],
    'completed' => ['Tamamlandı', 'success'],
    'cancelled' => ['İptal', 'danger'],
    'no_show' => ['Gelmedi', 'gray']
];
$st = $status_labels[$b['status']];
$extras = $b['extras'] ? json_decode($b['extras'], true) : [];
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Rezervasyon #<?= e($b['booking_code']) ?></h2>
    <div class="subtitle">Oluşturulma: <?= tr_date($b['created_at'], true) ?></div>
  </div>
  <div style="display:flex; gap:8px;">
    <a href="<?= admin_url('modules/bookings.php') ?>" class="btn btn-outline">← Geri</a>
    <button onclick="window.print()" class="btn btn-secondary">🖨 Yazdır</button>
  </div>
</div>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px;" class="booking-detail-grid">

  <!-- SOL KOLON -->
  <div>

    <?php if ($isApiBooking): ?>
    <!-- API REZERVASYON DURUMU -->
    <div class="card" style="margin-bottom:16px; border-left:4px solid #1d71b8; background:#F5F9FD;">
      <div class="card-header">
        <div class="card-title" style="display:flex; align-items:center; gap:10px;">
          🌐 API Rezervasyon
          <span style="background:#1d71b8; color:#fff; padding:3px 10px; border-radius:4px; font-size:12px;">
            <?= e($apiProviderName) ?>
          </span>
        </div>
      </div>
      <div class="card-body">
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
          <div>
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; margin-bottom:4px;">API Durumu</div>
            <?php
              $statusMap = [
                'none' => ['—', 'gray', ''],
                'pending' => ['Beklemede', 'warning', '⏳'],
                'sent' => ['Gönderildi', 'info', '✓'],
                'confirmed' => ['Onaylandı', 'success', '✓✓'],
                'failed' => ['Hata', 'danger', '✗'],
              ];
              $apiSt = $statusMap[$b['api_status'] ?? 'none'] ?? ['—', 'gray', ''];
            ?>
            <span class="badge badge-<?= $apiSt[1] ?>" style="font-size:13px;">
              <?= $apiSt[2] ?> <?= e($apiSt[0]) ?>
            </span>
          </div>
          <div>
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; margin-bottom:4px;">API Rez ID</div>
            <strong><?= e($b['api_reservation_id'] ?: '—') ?></strong>
          </div>
        </div>

        <?php if ($b['api_status'] === 'failed' && is_array($apiResponse)): ?>
          <div style="margin-top:12px; padding:10px; background:#FFEBEE; border-radius:6px;">
            <strong style="color:#C62828;">⚠️ Hata Mesajı:</strong>
            <div style="margin-top:4px; font-size:13px;"><?= e($apiResponse['message'] ?? 'Bilinmeyen hata') ?></div>
          </div>
        <?php endif ?>

        <?php if ($b['api_status'] === 'pending' && is_array($apiResponse) && !empty($apiResponse['test_mode'])): ?>
          <div style="margin-top:12px; padding:10px; background:#FFF3E0; border-radius:6px;">
            <strong style="color:#E65100;">🧪 Test Modu:</strong>
            <div style="margin-top:4px; font-size:13px;">Bu rezervasyon DB'ye kaydedildi ama API'ye gerçekten gönderilmedi. Provider'ın test modunu kapatınca gerçek post atılır.</div>
          </div>
        <?php endif ?>

        <details style="margin-top:12px;">
          <summary style="cursor:pointer; font-size:12px; color:var(--text-muted);">📋 API Detayları (geliştirici)</summary>
          <pre style="background:#F5F5F5; padding:8px; border-radius:6px; font-size:11px; max-height:200px; overflow:auto; margin-top:8px;"><?= e(json_encode($apiResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
          <?php if ($apiData): ?>
            <strong style="font-size:12px;">Araç Bilgileri:</strong>
            <pre style="background:#F5F5F5; padding:8px; border-radius:6px; font-size:11px; max-height:150px; overflow:auto;"><?= e(json_encode($apiData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
          <?php endif ?>
        </details>
      </div>
    </div>
    <?php endif ?>

    <!-- ARAÇ BİLGİSİ -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <div class="card-title">🚗 Kiralanan Araç</div>
      </div>
      <div class="card-body">
        <div style="display:flex; gap:16px; align-items:center;">
          <?php if ($isApiBooking && !empty($apiData['image'])): ?>
            <img src="<?= e($apiData['image']) ?>" style="width:120px; height:80px; object-fit:cover; border-radius:8px;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div style="display:none; width:120px; height:80px; background:var(--bg-soft); border-radius:8px; align-items:center; justify-content:center; font-size:32px;">🚗</div>
          <?php elseif ($b['vehicle_image']): ?>
            <img src="<?= upload_url($b['vehicle_image']) ?>" style="width:120px; height:80px; object-fit:cover; border-radius:8px;">
          <?php else: ?>
            <div style="width:120px; height:80px; background:var(--bg-soft); border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:32px;">🚗</div>
          <?php endif ?>
          <div style="flex:1;">
            <?php if ($isApiBooking && $apiData): ?>
              <h3 style="font-size:18px; color:var(--ink); margin-bottom:4px;">
                <?= e($apiData['car_name'] ?? '—') ?>
              </h3>
              <div style="color:var(--text-muted); font-size:13px;">
                <?= e($apiData['fuel'] ?? '') ?> · <?= e($apiData['transmission'] ?? '') ?>
              </div>
            <?php else: ?>
              <h3 style="font-size:18px; color:var(--ink); margin-bottom:4px;"><?= e($b['brand_name'] . ' ' . $b['vehicle_model']) ?></h3>
              <div style="color:var(--text-muted); font-size:13px;">
                <?= e($b['vehicle_year']) ?> · <?= e($b['vehicle_fuel']) ?> · <?= e($b['vehicle_trans']) ?>
              </div>
            <?php endif ?>
          </div>
          <?php if ($b['vehicle_id']): ?>
            <a href="<?= admin_url('modules/vehicle-edit.php?id=' . $b['vehicle_id']) ?>" class="btn btn-outline btn-sm">Aracı Gör</a>
          <?php endif ?>
        </div>
      </div>
    </div>

    <!-- TARİHLER & OFİSLER -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <div class="card-title">📅 Kiralama Detayı</div>
      </div>
      <div class="card-body">
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
          <div>
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Alış</div>
            <div style="font-size:15px; font-weight:600; color:var(--ink);"><?= tr_date($b['pickup_datetime'], true) ?></div>
            <div style="font-size:13px; color:var(--text); margin-top:4px;">
              📍 <?= e($b['pickup_office_name']) ?><br>
              <span style="color:var(--text-muted);"><?= e($b['pickup_city']) ?></span>
            </div>
          </div>
          <div>
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">İade</div>
            <div style="font-size:15px; font-weight:600; color:var(--ink);"><?= tr_date($b['return_datetime'], true) ?></div>
            <div style="font-size:13px; color:var(--text); margin-top:4px;">
              📍 <?= e($b['return_office_name']) ?><br>
              <span style="color:var(--text-muted);"><?= e($b['return_city']) ?></span>
            </div>
          </div>
        </div>
        <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border); text-align:center;">
          <span class="badge badge-brand" style="font-size:13px; padding:6px 14px;"><?= $b['total_days'] ?> Gün</span>
        </div>
      </div>
    </div>

    <!-- MÜŞTERİ BİLGİSİ -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <div class="card-title">👤 Müşteri Bilgileri</div>
        <?php if ($b['customer_exists']): ?>
          <span class="badge badge-success">Kayıtlı Müşteri</span>
        <?php else: ?>
          <span class="badge badge-gray">Misafir</span>
        <?php endif ?>
      </div>
      <div class="card-body">
        <div class="form-grid">
          <div>
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">Ad Soyad</div>
            <div style="font-size:14px; font-weight:600;"><?= e(trim($b['customer_name']) ?: '—') ?></div>
          </div>
          <div>
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">Telefon</div>
            <div style="font-size:14px;"><a href="tel:<?= e($b['customer_phone']) ?>"><?= e($b['customer_phone']) ?></a></div>
          </div>
          <div>
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">E-posta</div>
            <div style="font-size:14px;"><a href="mailto:<?= e($b['customer_email']) ?>"><?= e($b['customer_email']) ?></a></div>
          </div>
          <?php if ($b['guest_tc']): ?>
          <div>
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">TC Kimlik</div>
            <div style="font-size:14px; font-family:monospace;"><?= e($b['guest_tc']) ?></div>
          </div>
          <?php endif ?>
        </div>
        <?php if ($b['customer_note']): ?>
          <div style="margin-top:16px; padding:12px; background:var(--bg-soft); border-radius:8px; border-left:3px solid var(--brand);">
            <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">MÜŞTERİ NOTU</div>
            <div style="font-size:13px;"><?= nl2br(e($b['customer_note'])) ?></div>
          </div>
        <?php endif ?>
      </div>
    </div>

    <!-- FİYAT DÖKÜMÜ -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <div class="card-title">💰 Fiyat Dökümü</div>
      </div>
      <div class="card-body">
        <table style="width:100%; font-size:14px;">
          <tr>
            <td style="padding:8px 0;">Günlük Fiyat × <?= $b['total_days'] ?> gün</td>
            <td style="text-align:right; font-weight:600;"><?= tl($b['subtotal']) ?></td>
          </tr>
          <?php if ($b['extras_total'] > 0 && !empty($extras)): ?>
            <tr><td colspan="2" style="padding-top:12px; color:var(--text-muted); font-size:12px;">EK HİZMETLER:</td></tr>
            <?php foreach ($extras as $ex): ?>
              <tr>
                <td style="padding:4px 0 4px 12px; font-size:13px;"><?= e($ex['name'] ?? '') ?></td>
                <td style="text-align:right;"><?= tl($ex['price'] ?? 0) ?></td>
              </tr>
            <?php endforeach ?>
          <?php endif ?>
          <?php if ($b['discount_total'] > 0): ?>
          <tr style="color:var(--success);">
            <td style="padding:8px 0;">İndirim<?= $b['campaign_code'] ? ' (' . e($b['campaign_code']) . ')' : '' ?></td>
            <td style="text-align:right; font-weight:600;">-<?= tl($b['discount_total']) ?></td>
          </tr>
          <?php endif ?>
          <?php if ($b['tax_total'] > 0): ?>
          <tr>
            <td style="padding:8px 0; color:var(--text-muted); font-size:13px;">KDV</td>
            <td style="text-align:right; color:var(--text-muted);"><?= tl($b['tax_total']) ?></td>
          </tr>
          <?php endif ?>
          <tr style="border-top:2px solid var(--border);">
            <td style="padding:12px 0; font-size:16px; font-weight:700; color:var(--ink);">TOPLAM</td>
            <td style="text-align:right; font-size:20px; font-weight:800; color:var(--brand);"><?= tl($b['grand_total']) ?></td>
          </tr>
        </table>
      </div>
    </div>
  </div>

  <!-- SAĞ KOLON -->
  <div>
    <!-- DURUM GÜNCELLEME -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <div class="card-title">Durum</div>
      </div>
      <div class="card-body">
        <div style="text-align:center; padding:12px 0 20px;">
          <span class="badge badge-<?= $st[1] ?>" style="font-size:15px; padding:8px 20px;"><?= $st[0] ?></span>
        </div>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_status">
          <div class="form-row">
            <label class="form-label">Yeni Durum</label>
            <select name="status" class="form-select">
              <?php foreach ($status_labels as $val => $info): ?>
                <option value="<?= $val ?>" <?= $b['status'] === $val ? 'selected' : '' ?>><?= $info[0] ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="form-row">
            <label class="form-label">Yönetim Notu</label>
            <textarea name="admin_note" class="form-textarea" rows="3"><?= e($b['admin_note']) ?></textarea>
          </div>
          <div class="form-row" id="cancelReasonRow" style="display:none;">
            <label class="form-label">İptal Sebebi</label>
            <textarea name="cancel_reason" class="form-textarea" rows="2"><?= e($b['cancel_reason']) ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Durumu Güncelle</button>
        </form>
      </div>
    </div>

    <!-- ÖDEME -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <div class="card-title">💳 Ödeme</div>
      </div>
      <div class="card-body">
        <div style="background:var(--bg-soft); padding:12px; border-radius:8px; margin-bottom:16px;">
          <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:13px;">
            <span style="color:var(--text-muted);">Ödenen:</span>
            <strong><?= tl($b['paid_amount']) ?></strong>
          </div>
          <div style="display:flex; justify-content:space-between; font-size:13px;">
            <span style="color:var(--text-muted);">Kalan:</span>
            <strong style="color:<?= ($b['grand_total'] - $b['paid_amount']) > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
              <?= tl($b['grand_total'] - $b['paid_amount']) ?>
            </strong>
          </div>
        </div>

        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_payment">
          <div class="form-row">
            <label class="form-label">Ödeme Durumu</label>
            <select name="payment_status" class="form-select">
              <option value="pending" <?= $b['payment_status'] === 'pending' ? 'selected' : '' ?>>Bekliyor</option>
              <option value="partial" <?= $b['payment_status'] === 'partial' ? 'selected' : '' ?>>Kısmi</option>
              <option value="paid" <?= $b['payment_status'] === 'paid' ? 'selected' : '' ?>>Ödendi</option>
              <option value="refunded" <?= $b['payment_status'] === 'refunded' ? 'selected' : '' ?>>İade</option>
              <option value="failed" <?= $b['payment_status'] === 'failed' ? 'selected' : '' ?>>Başarısız</option>
            </select>
          </div>
          <div class="form-row">
            <label class="form-label">Ödenen Tutar (₺)</label>
            <input type="number" step="0.01" name="paid_amount" class="form-input" value="<?= $b['paid_amount'] ?>">
          </div>
          <div class="form-row">
            <label class="form-label">Ödeme Yöntemi</label>
            <select name="payment_method" class="form-select">
              <option value="">—</option>
              <option value="credit_card" <?= $b['payment_method'] === 'credit_card' ? 'selected' : '' ?>>Kredi Kartı</option>
              <option value="cash_on_pickup" <?= $b['payment_method'] === 'cash_on_pickup' ? 'selected' : '' ?>>Teslim Anında Nakit</option>
              <option value="bank_transfer" <?= $b['payment_method'] === 'bank_transfer' ? 'selected' : '' ?>>Havale/EFT</option>
            </select>
          </div>
          <button type="submit" class="btn btn-success" style="width:100%; justify-content:center;">Ödemeyi Kaydet</button>
        </form>
      </div>
    </div>
  </div>
</div>

<style>
@media (max-width: 991px) {
  .booking-detail-grid { grid-template-columns: 1fr !important; }
}
@media print {
  .sidebar, .topbar, .btn, .page-header a, .page-header button, .booking-detail-grid > div:nth-child(2) { display: none !important; }
  .main-area { margin-left: 0 !important; }
  .booking-detail-grid { grid-template-columns: 1fr !important; }
}
</style>

<script>
document.querySelector('select[name="status"]')?.addEventListener('change', function(){
  document.getElementById('cancelReasonRow').style.display = this.value === 'cancelled' ? 'block' : 'none';
});
if (document.querySelector('select[name="status"]')?.value === 'cancelled') {
  document.getElementById('cancelReasonRow').style.display = 'block';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
