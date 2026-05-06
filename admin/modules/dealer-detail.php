<?php
$page_title = 'Bayi Detayı';
$active_menu = 'dealers';

require_once __DIR__ . '/../../includes/bootstrap.php';

$id = (int)get('id', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('_csrf'))) { flash_error('Güvenlik hatası.'); redirect(admin_url('modules/dealers.php')); }
    db()->update('dealers', [
        'status' => post('status'),
        'admin_note' => post('admin_note'),
        'commission_percent' => (float)post('commission_percent')
    ], 'id = :id', ['id' => $id]);
    log_activity('update', 'dealers', 'Bayi güncellendi', $id);
    flash_success('Güncellendi.');
    redirect(admin_url('modules/dealer-detail.php?id=' . $id));
}

require_once __DIR__ . '/../includes/header.php';

$d = db()->fetch("SELECT * FROM dealers WHERE id = ?", [$id]);
if (!$d) { flash_error('Bayi bulunamadı.'); redirect(admin_url('modules/dealers.php')); }
?>

<div class="page-header">
  <div class="page-header-left">
    <h2><?= e($d['company_name']) ?></h2>
    <div class="subtitle">Başvuru: <?= tr_date($d['created_at']) ?></div>
  </div>
  <a href="<?= admin_url('modules/dealers.php') ?>" class="btn btn-outline">← Geri</a>
</div>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px;" class="dealer-detail-grid">
  <div>
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header"><div class="card-title">Firma Bilgileri</div></div>
      <div class="card-body">
        <div class="form-grid">
          <div><div style="font-size:11px; color:var(--text-muted);">FİRMA</div><strong><?= e($d['company_name']) ?></strong></div>
          <div><div style="font-size:11px; color:var(--text-muted);">VERGİ NO</div><?= e($d['tax_number']) ?: '—' ?></div>
          <div><div style="font-size:11px; color:var(--text-muted);">YETKİLİ</div><strong><?= e($d['contact_name']) ?></strong></div>
          <div><div style="font-size:11px; color:var(--text-muted);">E-POSTA</div><?= e($d['email']) ?></div>
          <div><div style="font-size:11px; color:var(--text-muted);">TELEFON</div><?= e($d['phone']) ?></div>
          <div><div style="font-size:11px; color:var(--text-muted);">WEBSITE</div><?= $d['website'] ? '<a href="'.e($d['website']).'" target="_blank">'.e($d['website']).'</a>' : '—' ?></div>
          <div><div style="font-size:11px; color:var(--text-muted);">ŞEHİR</div><?= e($d['city']) ?></div>
          <div><div style="font-size:11px; color:var(--text-muted);">İLÇE</div><?= e($d['district']) ?: '—' ?></div>
          <div><div style="font-size:11px; color:var(--text-muted);">FİLO BÜYÜKLÜĞÜ</div><strong><?= (int)$d['fleet_size'] ?> araç</strong></div>
          <div><div style="font-size:11px; color:var(--text-muted);">FAALİYET YILI</div><?= e($d['years_in_business']) ?: '—' ?></div>
        </div>
        <?php if ($d['address']): ?>
          <div style="margin-top:16px;">
            <div style="font-size:11px; color:var(--text-muted);">ADRES</div>
            <?= nl2br(e($d['address'])) ?>
          </div>
        <?php endif ?>
        <?php if ($d['description']): ?>
          <div style="margin-top:16px;">
            <div style="font-size:11px; color:var(--text-muted);">FİRMA HAKKINDA</div>
            <div style="padding:10px; background:var(--bg-soft); border-radius:6px;"><?= nl2br(e($d['description'])) ?></div>
          </div>
        <?php endif ?>
      </div>
    </div>
  </div>

  <div>
    <form method="POST">
      <?= csrf_field() ?>
      <div class="card">
        <div class="card-header"><div class="card-title">Yönetim</div></div>
        <div class="card-body">
          <div class="form-row">
            <label class="form-label">Durum</label>
            <select name="status" class="form-select">
              <?php foreach (['pending'=>'Beklemede','approved'=>'Onaylı','rejected'=>'Reddedildi','suspended'=>'Askıda'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= $d['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="form-row">
            <label class="form-label">Komisyon (%)</label>
            <input type="number" step="0.01" name="commission_percent" class="form-input" value="<?= e($d['commission_percent']) ?>">
          </div>
          <div class="form-row">
            <label class="form-label">Yönetim Notu</label>
            <textarea name="admin_note" class="form-textarea" rows="4"><?= e($d['admin_note']) ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Güncelle</button>
        </div>
      </div>
    </form>
  </div>
</div>

<style>@media (max-width: 991px) { .dealer-detail-grid { grid-template-columns: 1fr !important; } }</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
