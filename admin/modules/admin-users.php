<?php
$page_title = 'Yönetici Kullanıcılar';
$active_menu = 'admin-users';

require_once __DIR__ . '/../../includes/bootstrap.php';
require_role(['super_admin']);

$action = get('action');
$id = (int)get('id', 0);

if ($action === 'delete' && $id) {
    if ($id == current_user()['id']) {
        flash_error('Kendi hesabınızı silemezsiniz.');
    } else {
        db()->delete('admin_users', 'id = :id', ['id' => $id]);
        flash_success('Yönetici silindi.');
    }
    redirect(admin_url('modules/admin-users.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('_csrf'))) { flash_error('Güvenlik hatası.'); redirect(admin_url('modules/admin-users.php')); }

    $email = post('email');
    $name = post('name');
    $role = post('role', 'editor');
    $password = post('password');

    if (!$email || !$name) {
        flash_error('Ad ve e-posta zorunludur.');
    } elseif ($id) {
        $data = ['email' => $email, 'name' => $name, 'role' => $role, 'is_active' => (int)post('is_active', 1)];
        if ($password) $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        db()->update('admin_users', $data, 'id = :id', ['id' => $id]);
        flash_success('Yönetici güncellendi.');
    } else {
        if (!$password || strlen($password) < 6) {
            flash_error('Parola en az 6 karakter olmalı.');
            redirect(admin_url('modules/admin-users.php?action=new'));
        }
        db()->insert('admin_users', [
            'email' => $email, 'name' => $name, 'role' => $role,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'is_active' => 1
        ]);
        flash_success('Yeni yönetici oluşturuldu.');
    }
    redirect(admin_url('modules/admin-users.php'));
}

require_once __DIR__ . '/../includes/header.php';

$user = null;
if ($action === 'edit' && $id) $user = db()->fetch("SELECT * FROM admin_users WHERE id = ?", [$id]);

if ($action === 'edit' || $action === 'new'):
?>

<div class="page-header">
  <div class="page-header-left"><h2><?= $user ? 'Yönetici Düzenle' : 'Yeni Yönetici' ?></h2></div>
  <a href="<?= admin_url('modules/admin-users.php') ?>" class="btn btn-outline">← Geri</a>
</div>

<form method="POST">
<?= csrf_field() ?>
<div class="card" style="max-width:600px;">
  <div class="card-body">
    <div class="form-row">
      <label class="form-label">Ad Soyad <span class="required">*</span></label>
      <input type="text" name="name" class="form-input" value="<?= e($user['name'] ?? '') ?>" required>
    </div>
    <div class="form-row">
      <label class="form-label">E-posta <span class="required">*</span></label>
      <input type="email" name="email" class="form-input" value="<?= e($user['email'] ?? '') ?>" required>
    </div>
    <div class="form-row">
      <label class="form-label">Rol</label>
      <select name="role" class="form-select">
        <option value="super_admin" <?= ($user['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>Süper Yönetici</option>
        <option value="admin" <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Yönetici</option>
        <option value="editor" <?= ($user['role'] ?? 'editor') === 'editor' ? 'selected' : '' ?>>Editör</option>
        <option value="support" <?= ($user['role'] ?? '') === 'support' ? 'selected' : '' ?>>Destek</option>
      </select>
    </div>
    <div class="form-row">
      <label class="form-label">Parola <?= !$user ? '<span class="required">*</span>' : '<span class="hint">Boş bırakılırsa değişmez</span>' ?></label>
      <input type="password" name="password" class="form-input" <?= !$user ? 'required minlength="6"' : '' ?>>
    </div>
    <label class="form-check">
      <input type="checkbox" name="is_active" value="1" <?= ($user['is_active'] ?? 1) ? 'checked' : '' ?>> Aktif
    </label>
    <div class="form-actions">
      <a href="<?= admin_url('modules/admin-users.php') ?>" class="btn btn-outline">İptal</a>
      <button type="submit" class="btn btn-primary">Kaydet</button>
    </div>
  </div>
</div>
</form>

<?php else:
    $users = db()->fetchAll("SELECT * FROM admin_users ORDER BY role, name");
    $roleLabels = ['super_admin' => 'Süper Yönetici', 'admin' => 'Yönetici', 'editor' => 'Editör', 'support' => 'Destek'];
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Yönetici Kullanıcılar</h2>
    <div class="subtitle"><?= count($users) ?> yönetici</div>
  </div>
  <a href="?action=new" class="btn btn-primary">+ Yeni Yönetici</a>
</div>

<div class="card">
<div class="table-wrapper" style="border:0; border-radius:0;">
  <table class="table">
    <thead><tr><th>Ad</th><th>E-posta</th><th>Rol</th><th>Son Giriş</th><th>Durum</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><strong><?= e($u['name']) ?></strong><?= $u['id'] == current_user()['id'] ? ' <span class="badge badge-info">Sen</span>' : '' ?></td>
        <td><?= e($u['email']) ?></td>
        <td><span class="badge badge-brand"><?= e($roleLabels[$u['role']] ?? $u['role']) ?></span></td>
        <td style="font-size:12px;"><?= $u['last_login_at'] ? tr_date($u['last_login_at'], true) : '—' ?></td>
        <td><span class="badge badge-<?= $u['is_active'] ? 'success' : 'gray' ?>"><?= $u['is_active'] ? 'Aktif' : 'Pasif' ?></span></td>
        <td class="table-actions">
          <a href="?action=edit&id=<?= $u['id'] ?>" class="btn btn-outline btn-sm">Düzenle</a>
          <?php if ($u['id'] != current_user()['id']): ?>
            <a href="?action=delete&id=<?= $u['id'] ?>" class="btn btn-outline btn-sm" style="color:var(--danger);" onclick="return confirm('Silinsin mi?')">Sil</a>
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
