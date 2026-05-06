<?php
$page_title = 'Profilim';
$active_menu = '';

require_once __DIR__ . '/../includes/bootstrap.php';
require_auth();

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('_csrf'))) { flash_error('Güvenlik hatası.'); redirect(admin_url('profile.php')); }

    $data = ['name' => post('name'), 'email' => post('email'), 'phone' => post('phone'), 'lang' => post('lang', 'tr')];

    if (post('new_password')) {
        if (!password_verify(post('current_password'), $user['password'])) {
            flash_error('Mevcut parola hatalı.');
            redirect(admin_url('profile.php'));
        }
        if (strlen(post('new_password')) < 6) {
            flash_error('Yeni parola en az 6 karakter olmalı.');
            redirect(admin_url('profile.php'));
        }
        $data['password'] = password_hash(post('new_password'), PASSWORD_BCRYPT);
    }

    if (!empty($_FILES['avatar']['name'])) {
        $uploaded = upload_file($_FILES['avatar']);
        if ($uploaded) $data['avatar'] = $uploaded;
    }

    db()->update('admin_users', $data, 'id = :id', ['id' => $user['id']]);
    flash_success('Profil güncellendi.');
    redirect(admin_url('profile.php'));
}

require_once __DIR__ . '/includes/header.php';
$user = db()->fetch("SELECT * FROM admin_users WHERE id = ?", [$user['id']]); // fresh
?>

<div class="page-header">
  <div class="page-header-left"><h2>Profilim</h2></div>
</div>

<form method="POST" enctype="multipart/form-data">
<?= csrf_field() ?>
<div class="card" style="max-width:700px;">
  <div class="card-body">
    <div class="form-grid">
      <div class="form-row">
        <label class="form-label">Ad Soyad</label>
        <input type="text" name="name" class="form-input" value="<?= e($user['name']) ?>" required>
      </div>
      <div class="form-row">
        <label class="form-label">E-posta</label>
        <input type="email" name="email" class="form-input" value="<?= e($user['email']) ?>" required>
      </div>
      <div class="form-row">
        <label class="form-label">Telefon</label>
        <input type="text" name="phone" class="form-input" value="<?= e($user['phone']) ?>">
      </div>
      <div class="form-row">
        <label class="form-label">Dil</label>
        <select name="lang" class="form-select">
          <option value="tr" <?= $user['lang'] === 'tr' ? 'selected' : '' ?>>Türkçe</option>
          <option value="en" <?= $user['lang'] === 'en' ? 'selected' : '' ?>>English</option>
        </select>
      </div>
      <div class="form-row full">
        <label class="form-label">Avatar</label>
        <?php if ($user['avatar']): ?>
          <img src="<?= upload_url($user['avatar']) ?>" style="width:80px; height:80px; border-radius:50%; margin-bottom:8px; object-fit:cover;">
        <?php endif ?>
        <input type="file" name="avatar" class="form-input" accept="image/*">
      </div>
    </div>

    <h4 style="margin:24px 0 12px; color:var(--ink);">Parola Değiştir</h4>
    <div class="form-grid">
      <div class="form-row">
        <label class="form-label">Mevcut Parola</label>
        <input type="password" name="current_password" class="form-input">
      </div>
      <div class="form-row">
        <label class="form-label">Yeni Parola</label>
        <input type="password" name="new_password" class="form-input" minlength="6">
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Güncelle</button>
    </div>
  </div>
</div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
