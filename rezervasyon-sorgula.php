<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Rezervasyon Sorgula | Yolzz';
$pageDescription = 'Rezervasyonunuzu kodla sorgulayın, detayları görüntüleyin. 7/24 çevrimiçi rezervasyon takibi.';
$pageKeywords = 'rezervasyon sorgula, rent a car takip, araç kiralama rezervasyon';
$currentPage = '/rezervasyon-sorgula';

$code = trim(get('code', ''));
$email = trim(get('email', ''));
$booking = null;
$error = '';

if ($code) {
    // Hem code hem email ile güvenli doğrulama
    $where = "b.booking_code = ?";
    $params = [strtoupper($code)];

    if ($email) {
        $where .= " AND (b.guest_email = ? OR c.email = ?)";
        $params[] = $email;
        $params[] = $email;
    }

    $booking = db()->fetch("
        SELECT b.*, c.email AS cust_email, c.first_name AS cust_first, c.last_name AS cust_last,
               vb.name AS brand_name, v.model AS vehicle_model, v.main_image AS vehicle_image,
               po.name AS pickup_office_name,
               ro.name AS return_office_name
        FROM bookings b
        LEFT JOIN customers c ON c.id = b.customer_id
        LEFT JOIN vehicles v ON v.id = b.vehicle_id
        LEFT JOIN vehicle_brands vb ON vb.id = v.brand_id
        LEFT JOIN offices po ON po.id = b.pickup_office_id
        LEFT JOIN offices ro ON ro.id = b.return_office_id
        WHERE $where
        LIMIT 1
    ", $params);

    if (!$booking) {
        $error = 'Girdiğiniz rezervasyon kodu ile kayıt bulunamadı. Lütfen kodu kontrol edin veya destek hattımızdan bize ulaşın.';
    }
}

include __DIR__ . '/includes/frontend/header.php';

$status_labels = [
    'pending' => ['Beklemede', '#F59E0B'],
    'confirmed' => ['Onaylı', '#3B82F6'],
    'in_progress' => ['Devam Ediyor', '#1d71b8'],
    'completed' => ['Tamamlandı', '#10B981'],
    'cancelled' => ['İptal Edildi', '#EF4444'],
    'no_show' => ['Gelmedi', '#6B7280'],
];
?>

<section style="background:var(--bg-soft); padding: 50px 20px; min-height: 60vh;">
  <div style="max-width:700px; margin:0 auto;">

    <div style="text-align:center; margin-bottom:30px;">
      <h1 style="font-size:32px; color:var(--ink); margin-bottom:10px;">Rezervasyon Sorgula</h1>
      <p style="color:var(--text-muted);">Rezervasyon kodunuzu girerek detaylarınızı görüntüleyin</p>
    </div>

    <div style="background:#fff; padding:28px; border-radius:16px; box-shadow:var(--shadow);">
      <form method="GET">
        <div style="margin-bottom:16px;">
          <label style="display:block; font-size:13px; font-weight:600; color:var(--ink); margin-bottom:6px;">Rezervasyon Kodu</label>
          <input type="text" name="code" value="<?= e($code) ?>" required placeholder="RNT-2026-XXXXX"
                 style="width:100%; padding:14px 16px; border:1.5px solid var(--border); border-radius:10px; font-size:15px; font-family:inherit; text-transform:uppercase;">
        </div>

        <div style="margin-bottom:20px;">
          <label style="display:block; font-size:13px; font-weight:600; color:var(--ink); margin-bottom:6px;">E-posta (opsiyonel, güvenlik için)</label>
          <input type="email" name="email" value="<?= e($email) ?>" placeholder="ornek@mail.com"
                 style="width:100%; padding:14px 16px; border:1.5px solid var(--border); border-radius:10px; font-size:15px; font-family:inherit;">
        </div>

        <button type="submit" style="width:100%; padding:14px; background:var(--accent); color:#fff; border:0; border-radius:10px; font-size:15px; font-weight:700; font-family:inherit; cursor:pointer;">
          🔍 Rezervasyonumu Bul
        </button>
      </form>

      <?php if ($error): ?>
        <div style="margin-top:16px; padding:14px; background:#FEE2E2; color:#B91C1C; border-radius:10px; font-size:14px;">
          ⚠️ <?= e($error) ?>
        </div>
      <?php endif ?>
    </div>

    <?php if ($booking): ?>
      <?php
        $statusInfo = $status_labels[$booking['status']] ?? ['Bilinmiyor', '#6B7280'];
        $fullName = $booking['cust_first']
          ? ($booking['cust_first'] . ' ' . $booking['cust_last'])
          : ($booking['guest_first_name'] . ' ' . $booking['guest_last_name']);
      ?>
      <div style="margin-top:30px; background:#fff; padding:28px; border-radius:16px; box-shadow:var(--shadow);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:16px; border-bottom:1px solid var(--border-soft);">
          <div>
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600;">Rezervasyon Kodu</div>
            <div style="font-size:20px; font-weight:700; color:var(--ink); font-family:monospace;"><?= e($booking['booking_code']) ?></div>
          </div>
          <span style="padding:8px 16px; background:<?= $statusInfo[1] ?>; color:#fff; border-radius:20px; font-size:13px; font-weight:600;">
            <?= e($statusInfo[0]) ?>
          </span>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; font-size:14px;">
          <div>
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600; margin-bottom:4px;">Müşteri</div>
            <div style="color:var(--ink); font-weight:600;"><?= e($fullName) ?></div>
          </div>
          <div>
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600; margin-bottom:4px;">Araç</div>
            <div style="color:var(--ink); font-weight:600;"><?= e($booking['brand_name'] . ' ' . $booking['vehicle_model']) ?></div>
          </div>
          <div>
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600; margin-bottom:4px;">Alış</div>
            <div style="color:var(--ink); font-weight:600;"><?= e($booking['pickup_office_name']) ?></div>
            <div style="color:var(--text-muted); font-size:13px;"><?= date('d.m.Y H:i', strtotime($booking['pickup_datetime'])) ?></div>
          </div>
          <div>
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600; margin-bottom:4px;">İade</div>
            <div style="color:var(--ink); font-weight:600;"><?= e($booking['return_office_name']) ?></div>
            <div style="color:var(--text-muted); font-size:13px;"><?= date('d.m.Y H:i', strtotime($booking['return_datetime'])) ?></div>
          </div>
          <div style="grid-column:1 / -1; padding-top:16px; border-top:1px solid var(--border-soft);">
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <div>
                <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600;">Toplam Tutar</div>
                <div style="font-size:22px; font-weight:700; color:var(--brand);"><?= number_format($booking['grand_total'], 2, ',', '.') ?> ₺</div>
              </div>
              <a href="<?= url('confirmation?code=' . $booking['booking_code']) ?>"
                 style="padding:10px 20px; background:var(--brand); color:#fff; border-radius:10px; text-decoration:none; font-weight:600; font-size:14px;">
                Detayları Gör →
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endif ?>

    <div style="margin-top:24px; text-align:center; color:var(--text-muted); font-size:13px;">
      Yardım mı lazım? <a href="<?= url('iletisim') ?>" style="color:var(--brand);">İletişime geçin</a> veya
      <a href="tel:08505000777" style="color:var(--brand);">0850 500 0 777</a> çağrı merkezimizi arayın.
    </div>

  </div>
</section>

<?php include __DIR__ . '/includes/frontend/footer.php'; ?>
