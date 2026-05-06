<?php
$page_title = 'Sayfalar';
$active_menu = 'pages';

require_once __DIR__ . '/../../includes/bootstrap.php';

$action = get('action');
$id = (int)get('id', 0);

if ($action === 'delete' && $id) {
    $p = db()->fetch("SELECT is_system FROM pages WHERE id = ?", [$id]);
    if ($p && $p['is_system']) {
        flash_error('Sistem sayfaları silinemez.');
    } else {
        db()->delete('pages', 'id = :id', ['id' => $id]);
        flash_success('Sayfa silindi.');
    }
    redirect(admin_url('modules/pages.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('_csrf'))) { flash_error('Güvenlik hatası.'); redirect(admin_url('modules/pages.php')); }

    $data = [
        'title_tr' => post('title_tr'),
        'title_en' => post('title_en'),
        'slug' => post('slug') ?: slugify(post('title_tr')),
        'content_tr' => $_POST['content_tr'] ?? '',
        'content_en' => $_POST['content_en'] ?? '',
        'meta_title_tr' => post('meta_title_tr'),
        'meta_description_tr' => post('meta_description_tr'),
        'template' => post('template', 'default'),
        'status' => post('status', 'draft')
    ];

    if ($id) {
        db()->update('pages', $data, 'id = :id', ['id' => $id]);
        flash_success('Sayfa güncellendi.');
    } else {
        db()->insert('pages', $data);
        flash_success('Sayfa oluşturuldu.');
    }
    redirect(admin_url('modules/pages.php'));
}

require_once __DIR__ . '/../includes/header.php';

$page = null;
if ($action === 'edit' && $id) $page = db()->fetch("SELECT * FROM pages WHERE id = ?", [$id]);

if ($action === 'edit' || $action === 'new'):
?>

<div class="page-header">
  <div class="page-header-left">
    <h2><?= $page ? 'Sayfa Düzenle' : 'Yeni Sayfa' ?></h2>
    <?php if ($page && $page['is_system']): ?><span class="badge badge-info">Sistem Sayfası</span><?php endif ?>
  </div>
  <a href="<?= admin_url('modules/pages.php') ?>" class="btn btn-outline">← Geri</a>
</div>

<form method="POST">
<?= csrf_field() ?>

<div class="card">
  <div class="card-body">
    <div class="form-grid">
      <div class="form-row">
        <label class="form-label">Başlık (TR) <span class="required">*</span></label>
        <input type="text" name="title_tr" class="form-input" value="<?= e($page['title_tr'] ?? '') ?>" required>
      </div>
      <div class="form-row">
        <label class="form-label">Başlık (EN)</label>
        <input type="text" name="title_en" class="form-input" value="<?= e($page['title_en'] ?? '') ?>">
      </div>
      <div class="form-row">
        <label class="form-label">Slug <span class="hint">URL'de görünecek ad</span></label>
        <input type="text" name="slug" class="form-input" value="<?= e($page['slug'] ?? '') ?>" <?= !empty($page['is_system']) ? 'readonly' : '' ?>>
      </div>
      <div class="form-row">
        <label class="form-label">Durum</label>
        <select name="status" class="form-select">
          <option value="draft" <?= ($page['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Taslak</option>
          <option value="published" <?= ($page['status'] ?? '') === 'published' ? 'selected' : '' ?>>Yayında</option>
        </select>
      </div>
      <div class="form-row full">
        <label class="form-label">İçerik (TR)</label>
        <textarea name="content_tr" class="form-textarea" rows="15"><?= e($page['content_tr'] ?? '') ?></textarea>
      </div>
      <div class="form-row full">
        <label class="form-label">İçerik (EN)</label>
        <textarea name="content_en" class="form-textarea" rows="10"><?= e($page['content_en'] ?? '') ?></textarea>
      </div>
      <div class="form-row">
        <label class="form-label">Meta Title</label>
        <input type="text" name="meta_title_tr" class="form-input" value="<?= e($page['meta_title_tr'] ?? '') ?>">
      </div>
      <div class="form-row">
        <label class="form-label">Meta Description</label>
        <input type="text" name="meta_description_tr" class="form-input" value="<?= e($page['meta_description_tr'] ?? '') ?>">
      </div>
    </div>
    <div class="form-actions">
      <a href="<?= admin_url('modules/pages.php') ?>" class="btn btn-outline">İptal</a>
      <button type="submit" class="btn btn-primary">Kaydet</button>
    </div>
  </div>
</div>
</form>

<?php else:
    $pages = db()->fetchAll("SELECT * FROM pages ORDER BY is_system DESC, title_tr");
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Sayfalar</h2>
    <div class="subtitle"><?= count($pages) ?> sayfa</div>
  </div>
  <a href="?action=new" class="btn btn-primary">+ Yeni Sayfa</a>
</div>

<div class="card">
<div class="table-wrapper" style="border:0; border-radius:0;">
  <table class="table">
    <thead><tr><th>Başlık</th><th>Slug</th><th>Şablon</th><th>Durum</th><th>Tip</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($pages as $p): ?>
      <tr>
        <td><strong><?= e($p['title_tr']) ?></strong></td>
        <td><code style="font-size:12px;">/<?= e($p['slug']) ?></code></td>
        <td><?= e($p['template']) ?></td>
        <td>
          <?php $sm = ['draft'=>['gray','Taslak'],'published'=>['success','Yayında']]; $s = $sm[$p['status']] ?? ['gray', $p['status']]; ?>
          <span class="badge badge-<?= $s[0] ?>"><?= $s[1] ?></span>
        </td>
        <td>
          <?php if ($p['is_system']): ?><span class="badge badge-info">Sistem</span><?php else: ?><span class="badge badge-gray">Özel</span><?php endif ?>
        </td>
        <td class="table-actions">
          <a href="?action=edit&id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Düzenle</a>
          <?php if (!$p['is_system']): ?>
            <a href="?action=delete&id=<?= $p['id'] ?>" class="btn btn-outline btn-sm" style="color:var(--danger);" onclick="return confirm('Silinsin mi?')">Sil</a>
          <?php endif ?>
        </td>
      </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>
</div>

<?php endif ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
