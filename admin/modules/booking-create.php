<?php
$page_title = 'Manuel Rezervasyon';
$active_menu = 'bookings';

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('_csrf'))) { flash_error('Güvenlik hatası.'); redirect(admin_url('modules/booking-create.php')); }

    $pickup = post('pickup_datetime');
    $return = post('return_datetime');
    $days = max(1, (int)ceil((strtotime($return) - strtotime($pickup)) / 86400));

    $vehicle = db()->fetch("SELECT * FROM vehicles WHERE id = ?", [(int)post('vehicle_id')]);
    $subtotal = $vehicle ? $vehicle['daily_price'] * $days : 0;
    $tax = round($subtotal * 0.20, 2);
    $total = $subtotal + $tax;

    $data = [
        'booking_code' => generate_booking_code(),
        'vehicle_id' => (int)post('vehicle_id'),
        'pickup_office_id' => (int)post('pickup_office_id'),
        'return_office_id' => (int)post('return_office_id') ?: (int)post('pickup_office_id'),
        'pickup_datetime' => $pickup,
        'return_datetime' => $return,
        'total_days' => $days,
        'guest_first_name' => post('first_name'),
        'guest_last_name' => post('last_name'),
        'guest_email' => post('email'),
        'guest_phone' => post('phone'),
        'guest_tc' => post('tc'),
        'subtotal' => $subtotal,
        'tax_total' => $tax,
        'grand_total' => $total,
        'status' => 'confirmed',
        'payment_status' => 'pending',
        'customer_note' => post('customer_note'),
        'admin_note' => post('admin_note')
    ];

    $newId = db()->insert('bookings', $data);
    log_activity('create', 'bookings', 'Manuel rezervasyon oluşturuldu: ' . $data['booking_code'], $newId, 'booking');
    flash_success('Rezervasyon oluşturuldu: ' . $data['booking_code']);
    redirect(admin_url('modules/booking-detail.php?id=' . $newId));
}

require_once __DIR__ . '/../includes/header.php';

$vehicles = db()->fetchAll("SELECT v.id, v.model, v.daily_price, vb.name AS brand FROM vehicles v
    LEFT JOIN vehicle_brands vb ON vb.id = v.brand_id WHERE v.status = 'active' ORDER BY vb.name, v.model");
$offices = db()->fetchAll("SELECT o.id, o.name, c.name AS city FROM offices o JOIN cities c ON c.id = o.city_id
    WHERE o.is_active = 1 ORDER BY c.name, o.name");
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Manuel Rezervasyon</h2>
    <div class="subtitle">Telefon ile veya ofis üstünden gelen rezervasyonu ekleyin</div>
  </div>
  <a href="<?= admin_url('modules/bookings.php') ?>" class="btn btn-outline">← Geri</a>
</div>

<form method="POST">
<?= csrf_field() ?>

<div class="card" style="margin-bottom:16px;">
  <div class="card-header"><div class="card-title">🚗 Araç & Tarih</div></div>
  <div class="card-body">
    <div class="form-grid">
      <div class="form-row full">
        <label class="form-label">Araç <span class="required">*</span></label>
        <select name="vehicle_id" class="form-select" required>
          <option value="">Seçin</option>
          <?php foreach ($vehicles as $v): ?>
            <option value="<?= $v['id'] ?>"><?= e(($v['brand']??'').' '.$v['model']) ?> — <?= tl($v['daily_price']) ?>/gün</option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="form-row">
        <label class="form-label">Alış Ofisi <span class="required">*</span></label>
        <select name="pickup_office_id" class="form-select" required>
          <?php foreach ($offices as $o): ?>
            <option value="<?= $o['id'] ?>"><?= e($o['city'].' - '.$o['name']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="form-row">
        <label class="form-label">İade Ofisi</label>
        <select name="return_office_id" class="form-select">
          <option value="">Aynı ofis</option>
          <?php foreach ($offices as $o): ?>
            <option value="<?= $o['id'] ?>"><?= e($o['city'].' - '.$o['name']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="form-row">
        <label class="form-label">Alış Tarihi/Saati <span class="required">*</span></label>
        <input type="datetime-local" name="pickup_datetime" class="form-input" required>
      </div>
      <div class="form-row">
        <label class="form-label">İade Tarihi/Saati <span class="required">*</span></label>
        <input type="datetime-local" name="return_datetime" class="form-input" required>
      </div>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:16px;">
  <div class="card-header"><div class="card-title">👤 Müşteri Bilgisi</div></div>
  <div class="card-body">
    <div class="form-grid">
      <div class="form-row">
        <label class="form-label">Ad <span class="required">*</span></label>
        <input type="text" name="first_name" class="form-input" required>
      </div>
      <div class="form-row">
        <label class="form-label">Soyad <span class="required">*</span></label>
        <input type="text" name="last_name" class="form-input" required>
      </div>
      <div class="form-row">
        <label class="form-label">Telefon <span class="required">*</span></label>
        <input type="tel" name="phone" class="form-input" required>
      </div>
      <div class="form-row">
        <label class="form-label">E-posta</label>
        <input type="email" name="email" class="form-input">
      </div>
      <div class="form-row">
        <label class="form-label">TC Kimlik</label>
        <input type="text" name="tc" class="form-input" maxlength="11">
      </div>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:16px;">
  <div class="card-header"><div class="card-title">Notlar</div></div>
  <div class="card-body">
    <div class="form-row">
      <label class="form-label">Müşteri Notu</label>
      <textarea name="customer_note" class="form-textarea" rows="2"></textarea>
    </div>
    <div class="form-row">
      <label class="form-label">Yönetim Notu (iç)</label>
      <textarea name="admin_note" class="form-textarea" rows="2"></textarea>
    </div>
  </div>
</div>

<div class="form-actions" style="margin-bottom:30px;">
  <a href="<?= admin_url('modules/bookings.php') ?>" class="btn btn-outline">İptal</a>
  <button type="submit" class="btn btn-primary">Rezervasyonu Oluştur</button>
</div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
