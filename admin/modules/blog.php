<?php
$page_title = 'Blog Yazıları';
$active_menu = 'blog';

require_once __DIR__ . '/../../includes/bootstrap.php';

$action = get('action');
$id = (int)get('id', 0);

if ($action === 'delete' && $id) {
    db()->delete('blog_posts', 'id = :id', ['id' => $id]);
    flash_success('Yazı silindi.');
    redirect(admin_url('modules/blog.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('_csrf'))) { flash_error('Güvenlik hatası.'); redirect(admin_url('modules/blog.php')); }

    $data = [
        'category_id' => (int)post('category_id') ?: null,
        'title_tr' => post('title_tr'),
        'title_en' => post('title_en'),
        'slug' => slugify(post('title_tr')),
        'excerpt_tr' => post('excerpt_tr'),
        'excerpt_en' => post('excerpt_en'),
        'content_tr' => $_POST['content_tr'] ?? '',
        'content_en' => $_POST['content_en'] ?? '',
        'meta_title_tr' => post('meta_title_tr'),
        'meta_description_tr' => post('meta_description_tr'),
        'reading_time' => (int)post('reading_time', 5),
        'status' => post('status', 'draft'),
        'is_featured' => (int)post('is_featured', 0)
    ];

    if ($data['status'] === 'published' && !$id) {
        $data['published_at'] = date('Y-m-d H:i:s');
    }

    if (!empty($_FILES['cover_image']['name'])) {
        $uploaded = upload_file($_FILES['cover_image'], 'blog');
        if ($uploaded) $data['cover_image'] = $uploaded;
    }

    if ($id) {
        db()->update('blog_posts', $data, 'id = :id', ['id' => $id]);
        flash_success('Yazı güncellendi.');
    } else {
        $data['author_id'] = current_user()['id'];
        db()->insert('blog_posts', $data);
        flash_success('Yazı oluşturuldu.');
    }
    redirect(admin_url('modules/blog.php'));
}

require_once __DIR__ . '/../includes/header.php';

$post = null;
if ($action === 'edit' && $id) {
    $post = db()->fetch("SELECT * FROM blog_posts WHERE id = ?", [$id]);
}

if ($action === 'edit' || $action === 'new'):
    $categories = db()->fetchAll("SELECT * FROM blog_categories ORDER BY sort_order, name_tr");
?>

<div class="page-header">
  <div class="page-header-left">
    <h2><?= $post ? 'Blog Yazısı Düzenle' : 'Yeni Blog Yazısı' ?></h2>
  </div>
  <a href="<?= admin_url('modules/blog.php') ?>" class="btn btn-outline">← Geri</a>
</div>

<form method="POST" enctype="multipart/form-data">
<?= csrf_field() ?>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px;" class="blog-edit-grid">
  <div>
    <div class="card" style="margin-bottom:16px;">
      <div class="card-body">
        <div class="form-row">
          <label class="form-label">Başlık (TR) <span class="required">*</span></label>
          <input type="text" name="title_tr" class="form-input" value="<?= e($post['title_tr'] ?? '') ?>" required>
        </div>
        <div class="form-row">
          <label class="form-label">Başlık (EN)</label>
          <input type="text" name="title_en" class="form-input" value="<?= e($post['title_en'] ?? '') ?>">
        </div>
        <div class="form-row">
          <label class="form-label">Özet (TR)</label>
          <textarea name="excerpt_tr" class="form-textarea" rows="2"><?= e($post['excerpt_tr'] ?? '') ?></textarea>
        </div>
        <div class="form-row">
          <label class="form-label">İçerik (TR) <span class="required">*</span></label>
          <textarea name="content_tr" class="form-textarea" rows="12" required><?= e($post['content_tr'] ?? '') ?></textarea>
          <div class="form-help">HTML tag'leri kullanabilirsiniz</div>
        </div>
        <div class="form-row">
          <label class="form-label">İçerik (EN)</label>
          <textarea name="content_en" class="form-textarea" rows="10"><?= e($post['content_en'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><div class="card-title">SEO</div></div>
      <div class="card-body">
        <div class="form-row">
          <label class="form-label">Meta Title</label>
          <input type="text" name="meta_title_tr" class="form-input" value="<?= e($post['meta_title_tr'] ?? '') ?>">
        </div>
        <div class="form-row">
          <label class="form-label">Meta Description</label>
          <textarea name="meta_description_tr" class="form-textarea" rows="2"><?= e($post['meta_description_tr'] ?? '') ?></textarea>
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
            <option value="draft" <?= ($post['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Taslak</option>
            <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>Yayında</option>
            <option value="archived" <?= ($post['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Arşiv</option>
          </select>
        </div>
        <div class="form-row">
          <label class="form-label">Kategori</label>
          <select name="category_id" class="form-select">
            <option value="">—</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($post['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name_tr']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="form-row">
          <label class="form-label">Okuma Süresi (dk)</label>
          <input type="number" name="reading_time" class="form-input" value="<?= (int)($post['reading_time'] ?? 5) ?>">
        </div>
        <label class="form-check">
          <input type="checkbox" name="is_featured" value="1" <?= !empty($post['is_featured']) ? 'checked' : '' ?>>
          Öne Çıkan
        </label>
      </div>
    </div>

    <div class="card" style="margin-bottom:16px;">
      <div class="card-header"><div class="card-title">Kapak Görseli</div></div>
      <div class="card-body">
        <?php if (!empty($post['cover_image'])): ?>
          <img src="<?= upload_url($post['cover_image']) ?>" style="width:100%; border-radius:8px; margin-bottom:10px;">
        <?php endif ?>
        <input type="file" name="cover_image" class="form-input" accept="image/*">
      </div>
    </div>

    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Kaydet</button>
  </div>
</div>
</form>

<style>
@media (max-width: 991px) { .blog-edit-grid { grid-template-columns: 1fr !important; } }
</style>

<?php else:
    $posts = db()->fetchAll("
        SELECT p.*, bc.name_tr AS category_name, au.name AS author_name
        FROM blog_posts p
        LEFT JOIN blog_categories bc ON bc.id = p.category_id
        LEFT JOIN admin_users au ON au.id = p.author_id
        ORDER BY p.created_at DESC
    ");
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Blog Yazıları</h2>
    <div class="subtitle"><?= count($posts) ?> yazı</div>
  </div>
  <a href="?action=new" class="btn btn-primary">+ Yeni Yazı</a>
</div>

<div class="card">
<?php if (empty($posts)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">📝</div>
    <div class="empty-state-title">Henüz yazı yok</div>
    <a href="?action=new" class="btn btn-primary">İlk Yazıyı Oluştur</a>
  </div>
<?php else: ?>
<div class="table-wrapper" style="border:0; border-radius:0;">
  <table class="table">
    <thead><tr><th>Başlık</th><th>Kategori</th><th>Yazar</th><th>Durum</th><th>Tarih</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($posts as $p): ?>
      <tr>
        <td>
          <strong><?= e($p['title_tr']) ?></strong>
          <?php if ($p['is_featured']): ?><span class="badge badge-accent" style="margin-left:6px;">⭐</span><?php endif ?>
        </td>
        <td><?= e($p['category_name'] ?? '—') ?></td>
        <td><?= e($p['author_name'] ?? '—') ?></td>
        <td>
          <?php
          $sm = ['draft'=>['gray','Taslak'],'published'=>['success','Yayında'],'archived'=>['warning','Arşiv']];
          $s = $sm[$p['status']];
          ?>
          <span class="badge badge-<?= $s[0] ?>"><?= $s[1] ?></span>
        </td>
        <td style="font-size:12px;"><?= tr_date($p['created_at']) ?></td>
        <td class="table-actions">
          <a href="?action=edit&id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Düzenle</a>
          <a href="?action=delete&id=<?= $p['id'] ?>" class="btn btn-outline btn-sm" style="color:var(--danger);" onclick="return confirm('Silmek istediğinize emin misiniz?')">Sil</a>
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
