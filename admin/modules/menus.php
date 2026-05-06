<?php
$page_title = 'Menü Düzenle';
$active_menu = 'menus';

require_once __DIR__ . '/../../includes/bootstrap.php';

$action = get('action');
$id = (int)get('id', 0);

if ($action === 'delete' && $id) {
    db()->delete('menu_items', 'id = :id', ['id' => $id]);
    flash_success('Menü öğesi silindi.');
    redirect(admin_url('modules/menus.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('action') === 'save_order') {
        $items = $_POST['order'] ?? [];
        foreach ($items as $order => $itemId) {
            db()->update('menu_items', ['sort_order' => $order], 'id = :id', ['id' => (int)$itemId]);
        }
        echo 'ok'; exit;
    }

    if (!csrf_verify(post('_csrf'))) { flash_error('Güvenlik hatası.'); redirect(admin_url('modules/menus.php')); }

    $data = [
        'menu_location' => post('menu_location', 'header'),
        'title_tr' => post('title_tr'),
        'title_en' => post('title_en'),
        'url' => post('url'),
        'target' => post('target', '_self'),
        'icon' => post('icon'),
        'parent_id' => (int)post('parent_id') ?: null,
        'sort_order' => (int)post('sort_order', 0),
        'is_active' => (int)post('is_active', 1)
    ];

    if ($id) {
        db()->update('menu_items', $data, 'id = :id', ['id' => $id]);
        flash_success('Menü öğesi güncellendi.');
    } else {
        db()->insert('menu_items', $data);
        flash_success('Menü öğesi eklendi.');
    }
    redirect(admin_url('modules/menus.php?location=' . $data['menu_location']));
}

require_once __DIR__ . '/../includes/header.php';

$item = null;
if ($action === 'edit' && $id) $item = db()->fetch("SELECT * FROM menu_items WHERE id = ?", [$id]);

$currentLocation = get('location', 'header');

if ($action === 'edit' || $action === 'new'):
    $parents = db()->fetchAll("SELECT * FROM menu_items WHERE menu_location = ? AND parent_id IS NULL ORDER BY sort_order",
        [$currentLocation]);
?>

<div class="page-header">
  <div class="page-header-left"><h2><?= $item ? 'Menü Öğesi Düzenle' : 'Yeni Menü Öğesi' ?></h2></div>
  <a href="<?= admin_url('modules/menus.php?location=' . $currentLocation) ?>" class="btn btn-outline">← Geri</a>
</div>

<form method="POST">
<?= csrf_field() ?>
<div class="card">
  <div class="card-body">
    <div class="form-grid">
      <div class="form-row">
        <label class="form-label">Menü Konumu</label>
        <select name="menu_location" class="form-select">
          <option value="header" <?= ($item['menu_location'] ?? $currentLocation) === 'header' ? 'selected' : '' ?>>Üst Menü</option>
          <option value="footer" <?= ($item['menu_location'] ?? $currentLocation) === 'footer' ? 'selected' : '' ?>>Footer</option>
          <option value="mobile" <?= ($item['menu_location'] ?? '') === 'mobile' ? 'selected' : '' ?>>Mobil</option>
        </select>
      </div>
      <div class="form-row">
        <label class="form-label">Üst Öğe</label>
        <select name="parent_id" class="form-select">
          <option value="">— Ana Öğe —</option>
          <?php foreach ($parents as $p):
            if ($item && $item['id'] == $p['id']) continue;
          ?>
            <option value="<?= $p['id'] ?>" <?= ($item['parent_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>><?= e($p['title_tr']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="form-row">
        <label class="form-label">Başlık (TR) <span class="required">*</span></label>
        <input type="text" name="title_tr" class="form-input" value="<?= e($item['title_tr'] ?? '') ?>" required>
      </div>
      <div class="form-row">
        <label class="form-label">Başlık (EN)</label>
        <input type="text" name="title_en" class="form-input" value="<?= e($item['title_en'] ?? '') ?>">
      </div>
      <div class="form-row">
        <label class="form-label">URL <span class="required">*</span> <span class="hint">/filo, /kampanyalar, https://...</span></label>
        <input type="text" name="url" class="form-input" value="<?= e($item['url'] ?? '') ?>" required>
      </div>
      <div class="form-row">
        <label class="form-label">Hedef</label>
        <select name="target" class="form-select">
          <option value="_self" <?= ($item['target'] ?? '_self') === '_self' ? 'selected' : '' ?>>Aynı Sayfa</option>
          <option value="_blank" <?= ($item['target'] ?? '') === '_blank' ? 'selected' : '' ?>>Yeni Sayfa</option>
        </select>
      </div>
      <div class="form-row">
        <label class="form-label">İkon <span class="hint">Emoji veya sınıf</span></label>
        <input type="text" name="icon" class="form-input" value="<?= e($item['icon'] ?? '') ?>">
      </div>
      <div class="form-row">
        <label class="form-label">Sıralama</label>
        <input type="number" name="sort_order" class="form-input" value="<?= (int)($item['sort_order'] ?? 0) ?>">
      </div>
    </div>
    <label class="form-check">
      <input type="checkbox" name="is_active" value="1" <?= ($item['is_active'] ?? 1) ? 'checked' : '' ?>> Aktif
    </label>
    <div class="form-actions">
      <a href="<?= admin_url('modules/menus.php?location=' . $currentLocation) ?>" class="btn btn-outline">İptal</a>
      <button type="submit" class="btn btn-primary">Kaydet</button>
    </div>
  </div>
</div>
</form>

<?php else:
    $items = db()->fetchAll("SELECT * FROM menu_items WHERE menu_location = ? ORDER BY parent_id IS NULL DESC, parent_id, sort_order",
        [$currentLocation]);
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Menü Düzenle</h2>
    <div class="subtitle"><?= count($items) ?> öğe · Konum: <strong><?= ucfirst($currentLocation) ?></strong></div>
  </div>
  <a href="?action=new&location=<?= $currentLocation ?>" class="btn btn-primary">+ Yeni Öğe</a>
</div>

<!-- Konum tabları -->
<div style="display:flex; gap:6px; margin-bottom:16px;">
  <?php foreach (['header' => '🔝 Üst Menü', 'footer' => '⬇ Footer', 'mobile' => '📱 Mobil'] as $loc => $label): ?>
    <a href="?location=<?= $loc ?>" class="btn <?= $currentLocation === $loc ? 'btn-primary' : 'btn-outline' ?>"><?= $label ?></a>
  <?php endforeach ?>
</div>

<div class="card">
<?php if (empty($items)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">🧭</div>
    <div class="empty-state-title">Menü öğesi yok</div>
    <a href="?action=new&location=<?= $currentLocation ?>" class="btn btn-primary">İlk Öğeyi Ekle</a>
  </div>
<?php else: ?>
<div class="table-wrapper" style="border:0; border-radius:0;">
  <table class="table">
    <thead><tr><th>Başlık</th><th>URL</th><th>Hedef</th><th>Sıra</th><th>Durum</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($items as $m):
        $indent = $m['parent_id'] ? '↳ ' : '';
      ?>
      <tr>
        <td><?= $indent ?><strong><?= e($m['title_tr']) ?></strong></td>
        <td><code style="font-size:12px;"><?= e($m['url']) ?></code></td>
        <td><?= $m['target'] === '_blank' ? '↗ Yeni' : 'Aynı' ?></td>
        <td><?= $m['sort_order'] ?></td>
        <td><span class="badge badge-<?= $m['is_active'] ? 'success' : 'gray' ?>"><?= $m['is_active'] ? 'Aktif' : 'Pasif' ?></span></td>
        <td class="table-actions">
          <a href="?action=edit&id=<?= $m['id'] ?>" class="btn btn-outline btn-sm">Düzenle</a>
          <a href="?action=delete&id=<?= $m['id'] ?>" class="btn btn-outline btn-sm" style="color:var(--danger);" onclick="return confirm('Silinsin mi?')">Sil</a>
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
