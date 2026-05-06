<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/includes/bootstrap.php';

$code = get('code');
$booking = null;

if ($code) {
    $booking = db()->fetch("
        SELECT b.*,
               vb.name AS brand_name, v.model AS vehicle_model, v.main_image AS vehicle_image,
               v.year AS vehicle_year, v.fuel_type AS vehicle_fuel, v.transmission AS vehicle_trans,
               po.name AS pickup_office_name, po.address AS pickup_address, po.phone AS pickup_phone,
               ro.name AS return_office_name,
               pc.name AS pickup_city
        FROM bookings b
        LEFT JOIN vehicles v ON v.id = b.vehicle_id
        LEFT JOIN vehicle_brands vb ON vb.id = v.brand_id
        LEFT JOIN offices po ON po.id = b.pickup_office_id
        LEFT JOIN offices ro ON ro.id = b.return_office_id
        LEFT JOIN cities pc ON pc.id = po.city_id
        WHERE b.booking_code = ?
    ", [$code]);
}

if (!$booking) {
    $pageTitle = 'Rezervasyon Onayı | Yolzz';
$pageDescription = 'Rezervasyonunuz başarıyla alındı.';
$pageKeywords = '';
$pageRobots = 'noindex, nofollow';
$pageRobots = 'noindex, nofollow';
    include __DIR__ . '/includes/frontend/header.php';
    echo '<div style="text-align:center; padding:80px 20px;"><h1>Rezervasyon bulunamadı</h1><a href="' . url('/') . '" class="btn-outline-brand">Ana Sayfaya Dön</a></div>';
    include __DIR__ . '/includes/frontend/footer.php';
    exit;
}

$pageTitle = 'Rezervasyon Onaylandı';
$currentPage = '/confirmation';
include __DIR__ . '/includes/frontend/header.php';
?>

<section class="confirm-sec" style="padding:60px 0; background:var(--bg-soft); min-height:80vh;">
  <div class="container" style="max-width:800px; margin:0 auto; padding:0 20px;">

    <div class="confirm-hero">
      <div class="ch-icon">
        <svg width="60" height="60" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
      </div>
      <h1>🎉 Rezervasyon Alındı!</h1>
      <p>Talebiniz alındı. Kısa süre içinde onay için sizinle iletişime geçeceğiz.</p>
      <div class="booking-code">
        <div>Rezervasyon Kodu</div>
        <strong><?= e($booking['booking_code']) ?></strong>
      </div>
    </div>

    <div class="confirm-card">
      <div class="confirm-row">
        <div class="cr-label">🚗 Araç</div>
        <div class="cr-value">
          <strong><?= e($booking['brand_name'] . ' ' . $booking['vehicle_model']) ?></strong><br>
          <small><?= $booking['vehicle_year'] ?> · <?= e($booking['vehicle_fuel']) ?> · <?= e($booking['vehicle_trans']) ?></small>
        </div>
      </div>

      <div class="confirm-row">
        <div class="cr-label">📅 Tarih</div>
        <div class="cr-value">
          <strong><?= tr_date($booking['pickup_datetime'], true) ?></strong> →
          <strong><?= tr_date($booking['return_datetime'], true) ?></strong><br>
          <small><?= $booking['total_days'] ?> gün</small>
        </div>
      </div>

      <div class="confirm-row">
        <div class="cr-label">📍 Alış</div>
        <div class="cr-value">
          <strong><?= e($booking['pickup_office_name']) ?></strong><br>
          <small><?= e($booking['pickup_address']) ?></small><br>
          <small>📞 <?= e($booking['pickup_phone']) ?></small>
        </div>
      </div>

      <?php if ($booking['return_office_id'] != $booking['pickup_office_id']): ?>
      <div class="confirm-row">
        <div class="cr-label">📍 İade</div>
        <div class="cr-value"><strong><?= e($booking['return_office_name']) ?></strong></div>
      </div>
      <?php endif ?>

      <div class="confirm-row">
        <div class="cr-label">👤 Müşteri</div>
        <div class="cr-value">
          <strong><?= e($booking['guest_first_name'] . ' ' . $booking['guest_last_name']) ?></strong><br>
          <small><?= e($booking['guest_email']) ?> · <?= e($booking['guest_phone']) ?></small>
        </div>
      </div>

      <div class="confirm-row">
        <div class="cr-label">💰 Toplam</div>
        <div class="cr-value">
          <strong style="font-size:24px; color:var(--accent);"><?= tl($booking['grand_total']) ?></strong>
          <div style="font-size:12px; color:var(--text-muted);">
            Ödeme: <?php
              $pm = ['cash_on_pickup' => 'Teslim Anında Nakit', 'credit_card' => 'Kredi Kartı', 'bank_transfer' => 'Havale/EFT'];
              echo e($pm[$booking['payment_method']] ?? $booking['payment_method']);
            ?>
          </div>
        </div>
      </div>
    </div>

    <div class="next-steps">
      <h3>Sıradaki Adımlar</h3>
      <div class="ns-grid">
        <div class="ns-item">
          <div class="ns-num">1</div>
          <div>
            <strong>E-posta Alacaksınız</strong>
            <p>Rezervasyon detaylarınız <?= e($booking['guest_email']) ?> adresine gönderildi.</p>
          </div>
        </div>
        <div class="ns-item">
          <div class="ns-num">2</div>
          <div>
            <strong>Onay Araması</strong>
            <p>Ekibimiz en kısa sürede sizi arayarak rezervasyonunuzu onaylayacak.</p>
          </div>
        </div>
        <div class="ns-item">
          <div class="ns-num">3</div>
          <div>
            <strong>Aracı Teslim Alın</strong>
            <p>Belirtilen tarihte kimlik ve ehliyetinizle ofisimize gelin.</p>
          </div>
        </div>
      </div>
    </div>

    <div style="text-align:center; margin-top:30px;" class="no-print">
      <a href="<?= url('/') ?>" class="btn-outline-brand" style="margin-right:10px;">Ana Sayfa</a>
      <button type="button" onclick="window.print()" class="btn-outline-brand" style="margin-right:10px; cursor:pointer; background:transparent; font-family:inherit;">
        🖨️ Yazdır
      </button>
      <a href="<?= url('filo') ?>" class="btn-primary-lg" style="background:var(--brand); color:#fff; padding:12px 24px; border-radius:10px; text-decoration:none; display:inline-block;">Başka Araç Ara</a>
    </div>
  </div>
</section>

<style>
.confirm-hero { text-align: center; padding: 40px 20px; background: #fff; border-radius: 20px; margin-bottom: 24px; box-shadow: var(--shadow); }
.ch-icon { width: 90px; height: 90px; border-radius: 50%; background: var(--success); color: #fff; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
.confirm-hero h1 { font-size: 28px; color: var(--ink); margin-bottom: 10px; }
.confirm-hero p { color: var(--text-muted); margin-bottom: 20px; }
.booking-code { display: inline-block; padding: 16px 28px; background: var(--brand-wash); border-radius: 12px; border: 2px dashed var(--brand); }
.booking-code div { font-size: 11px; color: var(--brand); text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600; }
.booking-code strong { font-size: 24px; color: var(--ink); font-family: monospace; letter-spacing: 0.05em; }
.confirm-card { background: #fff; border-radius: 16px; padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow); }
.confirm-row { display: grid; grid-template-columns: 120px 1fr; gap: 20px; padding: 16px 0; border-bottom: 1px solid var(--border-soft); align-items: start; }
.confirm-row:last-child { border-bottom: 0; }
.cr-label { font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
.cr-value { font-size: 14px; color: var(--ink); }
.cr-value small { font-size: 12px; color: var(--text-muted); }
@media (max-width: 600px) { .confirm-row { grid-template-columns: 1fr; gap: 4px; } }

.next-steps { background: #fff; border-radius: 16px; padding: 24px; box-shadow: var(--shadow); }
.next-steps h3 { margin-bottom: 16px; color: var(--ink); }
.ns-grid { display: flex; flex-direction: column; gap: 16px; }
.ns-item { display: flex; gap: 16px; }
.ns-num { flex-shrink: 0; width: 36px; height: 36px; border-radius: 50%; background: var(--brand); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; }
.ns-item strong { display: block; color: var(--ink); font-size: 14px; margin-bottom: 2px; }
.ns-item p { font-size: 13px; color: var(--text-muted); }

/* ============ PRINT CSS ============ */
@media print {
  @page { margin: 1cm; }
  body { background: #fff !important; color: #000 !important; }
  header, footer, .site-header, .site-footer, .no-print, .global-flash, nav, .listing-search-bar { display: none !important; }
  section { padding: 0 !important; background: #fff !important; }
  .confirm-hero, .confirm-card, .next-steps { box-shadow: none !important; border: 1px solid #ccc; page-break-inside: avoid; }
  .confirm-hero h1 { color: #000 !important; }
  a { color: #000 !important; text-decoration: none !important; }
  .booking-code { border-color: #000 !important; background: transparent !important; }
  .ns-num { background: #000 !important; }
}
</style>

<?php include __DIR__ . '/includes/frontend/footer.php'; ?>
