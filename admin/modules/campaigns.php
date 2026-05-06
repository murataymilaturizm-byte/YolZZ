<?php
$page_title = 'Kampanyalar';
$active_menu = 'campaigns';

require_once __DIR__ . '/../../includes/bootstrap.php';

$action = get('action');
$id = (int)get('id', 0);

if ($action === 'delete' && $id) {
    db()->delete('campaigns', 'id = :id', ['id' => $id]);
    flash_success('Kampanya silindi.');
    redirect(admin_url('modules/campaigns.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('_csrf'))) { flash_error('Güvenlik hatası.'); redirect(admin_url('modules/campaigns.php')); }

    $data = [
        'title_tr' => post('title_tr'),
        'title_en' => post('title_en'),
        'slug' => slugify(post('title_tr')),
        'short_desc_tr' => post('short_desc_tr'),
        'short_desc_en' => post('short_desc_en'),
        'content_tr' => $_POST['content_tr'] ?? '',
        'content_en' => $_POST['content_en'] ?? '',
        'discount_type' => post('discount_type', 'percent'),
        'discount_value' => (float)post('discount_value'),
        'coupon_code' => post('coupon_code') ?: null,
        'min_days' => (int)post('min_days', 1),
        'min_amount' => (float)post('min_amount'),
        'usage_limit' => (int)post('usage_limit') ?: null,
        'start_date' => post('start_date') ?: null,
        'end_date' => post('end_date') ?: null,
        'status' => post('status', 'draft'),
        'is_featured' => (int)post('is_featured', 0)
    ];

    if (!empty($_FILES['cover_image']['name'])) {
        $uploaded = upload_file($_FILES['cover_image'], 'campaigns');
        if ($uploaded) $data['cover_image'] = $uploaded;
    }
    if (!empty($_FILES['banner_image']['name'])) {
        $uploaded = upload_file($_FILES['banner_image'], 'campaigns');
        if ($uploaded) $data['banner_image'] = $uploaded;
    }

    if ($id) {
        db()->update('campaigns', $data, 'id = :id', ['id' => $id]);
        flash_success('Kampanya güncellendi.');
    } else {
        db()->insert('campaigns', $data);
        flash_success('Kampanya oluşturuldu.');
    }
    redirect(admin_url('modules/campaigns.php'));
}

require_once __DIR__ . '/../includes/header.php';

$campaign = null;
if ($action === 'edit' && $id) $campaign = db()->fetch("SELECT * FROM campaigns WHERE id = ?", [$id]);

if ($action === 'edit' || $action === 'new'):
?>

<div class="page-header">
  <div class="page-header-left">
    <h2><?= $campaign ? 'Kampanya Düzenle' : 'Yeni Kampanya' ?></h2>
  </div>
  <a href="<?= admin_url('modules/campaigns.php') ?>" class="btn btn-outline">← Geri</a>
</div>

<form method="POST" enctype="multipart/form-data">
<?= csrf_field() ?>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px;" class="campaign-edit-grid">
  <div>
    <div class="card" style="margin-bottom:16px;">
      <div class="card-body">
        <div class="form-row">
          <label class="form-label">Başlık (TR) <span class="required">*</span></label>
          <input type="text" name="title_tr" class="form-input" value="<?= e($campaign['title_tr'] ?? '') ?>" required>
        </div>
        <div class="form-row">
          <label class="form-label">Başlık (EN)</label>
          <input type="text" name="title_en" class="form-input" value="<?= e($campaign['title_en'] ?? '') ?>">
        </div>
        <div class="form-row">
          <label class="form-label">Kısa Açıklama (TR)</label>
          <textarea name="short_desc_tr" class="form-textarea" rows="2"><?= e($campaign['short_desc_tr'] ?? '') ?></textarea>
        </div>
        <div class="form-row">
          <label class="form-label">Detaylı İçerik (TR)</label>
          <textarea name="content_tr" class="form-textarea" rows="8"><?= e($campaign['content_tr'] ?? '') ?></textarea>
        </div>
        <div class="form-row">
          <label class="form-label">Detaylı İçerik (EN)</label>
          <textarea name="content_en" class="form-textarea" rows="6"><?= e($campaign['content_en'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><div class="card-title">İndirim</div></div>
      <div class="card-body">
        <div class="form-grid">
          <div class="form-row">
            <label class="form-label">İndirim Tipi</label>
            <select name="discount_type" class="form-select">
              <option value="percent" <?= ($campaign['discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>>Yüzde</option>
              <option value="fixed" <?= ($campaign['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Sabit Tutar</option>
              <option value="free_extra" <?= ($campaign['discount_type'] ?? '') === 'free_extra' ? 'selected' : '' ?>>Ücretsiz Ek Hizmet</option>
              <option value="none" <?= ($campaign['discount_type'] ?? '') === 'none' ? 'selected' : '' ?>>Yok</option>
            </select>
          </div>
          <div class="form-row">
            <label class="form-label">İndirim Miktarı</label>
            <input type="number" step="0.01" name="discount_value" class="form-input" value="<?= e($campaign['discount_value'] ?? '') ?>">
          </div>
          <div class="form-row">
            <label class="form-label">Kupon Kodu</label>
            <input type="text" name="coupon_code" class="form-input" value="<?= e($campaign['coupon_code'] ?? '') ?>" placeholder="ÖRN: YAZ2026">
          </div>
          <div class="form-row">
            <label class="form-label">Min. Gün</label>
            <input type="number" name="min_days" class="form-input" value="<?= (int)($campaign['min_days'] ?? 1) ?>">
          </div>
          <div class="form-row">
            <label class="form-label">Min. Tutar (₺)</label>
            <input type="number" step="0.01" name="min_amount" class="form-input" value="<?= e($campaign['min_amount'] ?? '') ?>">
          </div>
          <div class="form-row">
            <label class="form-label">Kullanım Limiti</label>
            <input type="number" name="usage_limit" class="form-input" value="<?= e($campaign['usage_limit'] ?? '') ?>">
          </div>
        </div>
      </div>
    </div>
  </div>

  <div>
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header"><div class="card-title">Yayın</div></div>
      <div class="card-body">
        <div class="form-row">
          <label class="form-label">Durum</label>
          <select name="status" class="form-select">
            <option value="draft" <?= ($campaign['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Taslak</option>
            <option value="active" <?= ($campaign['status'] ?? '') === 'active' ? 'selected' : '' ?>>Aktif</option>
            <option value="paused" <?= ($campaign['status'] ?? '') === 'paused' ? 'selected' : '' ?>>Durdurulmuş</option>
            <option value="ended" <?= ($campaign['status'] ?? '') === 'ended' ? 'selected' : '' ?>>Bitmiş</option>
          </select>
        </div>
        <div class="form-row">
          <label class="form-label">Başlangıç</label>
          <input type="date" name="start_date" class="form-input" value="<?= e($campaign['start_date'] ?? '') ?>">
        </div>
        <div class="form-row">
          <label class="form-label">Bitiş</label>
          <input type="date" name="end_date" class="form-input" value="<?= e($campaign['end_date'] ?? '') ?>">
        </div>
        <label class="form-check">
          <input type="checkbox" name="is_featured" value="1" <?= !empty($campaign['is_featured']) ? 'checked' : '' ?>>
          Ana sayfada öne çıkar
        </label>
      </div>
    </div>

    <div class="card" style="margin-bottom:16px;">
      <div class="card-header"><div class="card-title">Görseller</div></div>
      <div class="card-body">
        <div class="form-row">
          <label class="form-label">Kapak</label>
          <?php if (!empty($campaign['cover_image'])): ?>
            <img src="<?= upload_url($campaign['cover_image']) ?>" style="width:100%; border-radius:6px; margin-bottom:6px;">
          <?php endif ?>
          <input type="file" name="cover_image" class="form-input" accept="image/*">
        </div>
        <div class="form-row">
          <label class="form-label">Banner</label>
          <?php if (!empty($campaign['banner_image'])): ?>
            <img src="<?= upload_url($campaign['banner_image']) ?>" style="width:100%; border-radius:6px; margin-bottom:6px;">
          <?php endif ?>
          <input type="file" name="banner_image" class="form-input" accept="image/*">
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Kaydet</button>
  </div>
</div>
</form>

<style>@media (max-width: 991px) { .campaign-edit-grid { grid-template-columns: 1fr !important; } }</style>

<?php else:
    $campaigns = db()->fetchAll("SELECT * FROM campaigns ORDER BY is_featured DESC, created_at DESC");
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Kampanyalar</h2>
    <div class="subtitle"><?= count($campaigns) ?> kampanya</div>
  </div>
  <a href="?action=new" class="btn btn-primary">+ Yeni Kampanya</a>
</div>

<div class="card">
<?php if (empty($campaigns)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">🎁</div>
    <div class="empty-state-title">Kampanya yok</div>
    <a href="?action=new" class="btn btn-primary">İlk Kampanyayı Oluştur</a>
  </div>
<?php else: ?>
<div class="table-wrapper" style="border:0; border-radius:0;">
  <table class="table">
    <thead><tr><th>Başlık</th><th>İndirim</th><th>Kupon</th><th>Tarih</th><th>Durum</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($campaigns as $c): ?>
      <tr>
        <td>
          <strong><?= e($c['title_tr']) ?></strong>
          <?php if ($c['is_featured']): ?><span class="badge badge-accent" style="margin-left:6px;">⭐</span><?php endif ?>
        </td>
        <td>
          <?php if ($c['discount_type'] === 'percent'): ?>
            <span class="badge badge-success">%<?= (int)$c['discount_value'] ?></span>
          <?php elseif ($c['discount_type'] === 'fixed'): ?>
            <span class="badge badge-success"><?= tl($c['discount_value']) ?></span>
          <?php endif ?>
        </td>
        <td><?= $c['coupon_code'] ? '<code>' . e($c['coupon_code']) . '</code>' : '—' ?></td>
        <td style="font-size:12px;">
          <?= $c['start_date'] ? tr_date($c['start_date']) : '—' ?><br>
          <?= $c['end_date'] ? tr_date($c['end_date']) : '—' ?>
        </td>
        <td>
          <?php
          $sm = ['draft'=>['gray','Taslak'],'active'=>['success','Aktif'],'paused'=>['warning','Durmuş'],'ended'=>['gray','Bitmiş']];
          $s = $sm[$c['status']];
          ?>
          <span class="badge badge-<?= $s[0] ?>"><?= $s[1] ?></span>
        </td>
        <td class="table-actions">
          <a href="?action=edit&id=<?= $c['id'] ?>" class="btn btn-outline btn-sm">Düzenle</a>
          <a href="?action=delete&id=<?= $c['id'] ?>" class="btn btn-outline btn-sm" style="color:var(--danger);" onclick="return confirm('Silinsin mi?')">Sil</a>
        </td>
      </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>
<?php endif ?>
</div>

<?php endif ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
