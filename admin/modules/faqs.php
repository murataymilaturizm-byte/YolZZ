<?php
$page_title = 'Sıkça Sorulan Sorular';
$active_menu = 'faqs';

require_once __DIR__ . '/../../includes/bootstrap.php';

$action = get('action');
$id = (int)get('id', 0);

if ($action === 'delete' && $id) {
    db()->delete('faqs', 'id = :id', ['id' => $id]);
    flash_success('SSS silindi.');
    redirect(admin_url('modules/faqs.php'));
}

if ($action === 'toggle' && $id) {
    $f = db()->fetch("SELECT is_active FROM faqs WHERE id = ?", [$id]);
    if ($f) db()->update('faqs', ['is_active' => !$f['is_active']], 'id = :id', ['id' => $id]);
    redirect(admin_url('modules/faqs.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('_csrf'))) { flash_error('Güvenlik hatası.'); redirect(admin_url('modules/faqs.php')); }

    $data = [
        'category_tr' => post('category_tr') ?: 'Genel',
        'category_en' => post('category_en') ?: 'General',
        'question_tr' => post('question_tr'),
        'question_en' => post('question_en'),
        'answer_tr' => post('answer_tr'),
        'answer_en' => post('answer_en'),
        'sort_order' => (int)post('sort_order', 0),
        'is_active' => (int)post('is_active', 1)
    ];

    if ($id) {
        db()->update('faqs', $data, 'id = :id', ['id' => $id]);
        flash_success('SSS güncellendi.');
    } else {
        db()->insert('faqs', $data);
        flash_success('SSS eklendi.');
    }
    redirect(admin_url('modules/faqs.php'));
}

require_once __DIR__ . '/../includes/header.php';

$faq = null;
if ($action === 'edit' && $id) $faq = db()->fetch("SELECT * FROM faqs WHERE id = ?", [$id]);

if ($action === 'edit' || $action === 'new'):
?>

<div class="page-header">
  <div class="page-header-left"><h2><?= $faq ? 'SSS Düzenle' : 'Yeni Soru-Cevap' ?></h2></div>
  <a href="<?= admin_url('modules/faqs.php') ?>" class="btn btn-outline">← Geri</a>
</div>

<form method="POST">
<?= csrf_field() ?>
<div class="card">
  <div class="card-body">
    <div class="form-grid">
      <div class="form-row">
        <label class="form-label">Kategori (TR)</label>
        <input type="text" name="category_tr" class="form-input" value="<?= e($faq['category_tr'] ?? 'Genel') ?>" placeholder="Genel, Ödeme, Rezervasyon...">
      </div>
      <div class="form-row">
        <label class="form-label">Sıra</label>
        <input type="number" name="sort_order" class="form-input" value="<?= (int)($faq['sort_order'] ?? 0) ?>">
      </div>
      <div class="form-row full">
        <label class="form-label">Soru (TR) <span class="required">*</span></label>
        <input type="text" name="question_tr" class="form-input" value="<?= e($faq['question_tr'] ?? '') ?>" required>
      </div>
      <div class="form-row full">
        <label class="form-label">Soru (EN)</label>
        <input type="text" name="question_en" class="form-input" value="<?= e($faq['question_en'] ?? '') ?>">
      </div>
      <div class="form-row full">
        <label class="form-label">Cevap (TR) <span class="required">*</span></label>
        <textarea name="answer_tr" class="form-textarea" rows="5" required><?= e($faq['answer_tr'] ?? '') ?></textarea>
      </div>
      <div class="form-row full">
        <label class="form-label">Cevap (EN)</label>
        <textarea name="answer_en" class="form-textarea" rows="4"><?= e($faq['answer_en'] ?? '') ?></textarea>
      </div>
    </div>
    <label class="form-check">
      <input type="checkbox" name="is_active" value="1" <?= ($faq['is_active'] ?? 1) ? 'checked' : '' ?>>
      Aktif
    </label>
    <div class="form-actions">
      <a href="<?= admin_url('modules/faqs.php') ?>" class="btn btn-outline">İptal</a>
      <button type="submit" class="btn btn-primary">Kaydet</button>
    </div>
  </div>
</div>
</form>

<?php else:
    $faqs = db()->fetchAll("SELECT * FROM faqs ORDER BY category_tr, sort_order, id");
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Sıkça Sorulan Sorular</h2>
    <div class="subtitle"><?= count($faqs) ?> soru</div>
  </div>
  <a href="?action=new" class="btn btn-primary">+ Yeni Soru</a>
</div>

<div class="card">
<?php if (empty($faqs)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">❓</div>
    <div class="empty-state-title">SSS yok</div>
    <a href="?action=new" class="btn btn-primary">İlk Soruyu Ekle</a>
  </div>
<?php else: ?>
<div class="table-wrapper" style="border:0; border-radius:0;">
  <table class="table">
    <thead><tr><th>Soru</th><th>Kategori</th><th>Sıra</th><th>Durum</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($faqs as $f): ?>
      <tr>
        <td><strong><?= e($f['question_tr']) ?></strong></td>
        <td><span class="badge badge-gray"><?= e($f['category_tr']) ?></span></td>
        <td><?= $f['sort_order'] ?></td>
        <td><span class="badge badge-<?= $f['is_active'] ? 'success' : 'gray' ?>"><?= $f['is_active'] ? 'Aktif' : 'Pasif' ?></span></td>
        <td class="table-actions">
          <a href="?action=toggle&id=<?= $f['id'] ?>" class="btn btn-outline btn-sm"><?= $f['is_active'] ? 'Pasifle' : 'Aktifle' ?></a>
          <a href="?action=edit&id=<?= $f['id'] ?>" class="btn btn-outline btn-sm">Düzenle</a>
          <a href="?action=delete&id=<?= $f['id'] ?>" class="btn btn-outline btn-sm" style="color:var(--danger);" onclick="return confirm('Silinsin mi?')">Sil</a>
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
