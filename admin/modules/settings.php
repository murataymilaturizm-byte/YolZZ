<?php
$page_title = 'Site Ayarları';
$active_menu = 'settings';

require_once __DIR__ . '/../../includes/bootstrap.php';
require_role(['super_admin', 'admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('_csrf'))) { flash_error('Güvenlik hatası.'); redirect(admin_url('modules/settings.php')); }

    $updates = $_POST['settings'] ?? [];
    foreach ($updates as $key => $value) {
        if (is_array($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        db()->query(
            "UPDATE settings SET setting_value = :v WHERE setting_key = :k",
            ['v' => $value, 'k' => $key]
        );
    }

    // Logo yükleme
    if (!empty($_FILES['logo_upload']['name'])) {
        $uploaded = upload_file($_FILES['logo_upload']);
        if ($uploaded) {
            db()->query("UPDATE settings SET setting_value = :v WHERE setting_key = 'site_logo'", ['v' => $uploaded]);
        }
    }

    log_activity('update', 'settings', 'Site ayarları güncellendi');
    flash_success('Ayarlar kaydedildi.');
    redirect(admin_url('modules/settings.php?group=' . get('group', 'general')));
}

require_once __DIR__ . '/../includes/header.php';

$group = get('group', 'general');
$groups = db()->fetchAll("SELECT DISTINCT group_name FROM settings ORDER BY group_name");
$settings = db()->fetchAll("SELECT * FROM settings WHERE group_name = ? ORDER BY id", [$group]);

$groupLabels = [
    'general' => '🏢 Genel',
    'contact' => '📞 İletişim',
    'social' => '📱 Sosyal Medya',
    'seo' => '🔍 SEO',
    'booking' => '🚗 Rezervasyon',
    'email' => '📧 E-posta',
    'payment' => '💳 Ödeme'
];
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Site Ayarları</h2>
    <div class="subtitle">Genel yapılandırma</div>
  </div>
</div>

<div style="display:grid; grid-template-columns: 220px 1fr; gap:16px;" class="settings-grid">
  <!-- GROUP TABS -->
  <div class="card" style="padding:8px;">
    <?php foreach ($groups as $g):
      $label = $groupLabels[$g['group_name']] ?? ucfirst($g['group_name']);
    ?>
      <a href="?group=<?= urlencode($g['group_name']) ?>"
         style="display:block; padding:10px 12px; border-radius:8px; text-decoration:none; color:var(--ink); font-size:13px; font-weight:<?= $g['group_name'] === $group ? '700' : '500' ?>; background:<?= $g['group_name'] === $group ? 'var(--brand-wash)' : 'transparent' ?>;">
        <?= $label ?>
      </a>
    <?php endforeach ?>
  </div>

  <!-- SETTINGS FORM -->
  <form method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="card">
      <div class="card-header">
        <div class="card-title"><?= $groupLabels[$group] ?? ucfirst($group) ?></div>
      </div>
      <div class="card-body">
        <?php if (empty($settings)): ?>
          <div class="empty-state"><div class="empty-state-text">Bu grupta ayar yok.</div></div>
        <?php else: foreach ($settings as $s):
          $label = $s['setting_key'];
          $label = ucwords(str_replace('_', ' ', $label));
        ?>
          <div class="form-row">
            <label class="form-label"><?= e($label) ?><?php if ($s['description']): ?> <span class="hint"><?= e($s['description']) ?></span><?php endif ?></label>
            <?php if ($s['setting_type'] === 'textarea'): ?>
              <textarea name="settings[<?= e($s['setting_key']) ?>]" class="form-textarea" rows="3"><?= e($s['setting_value']) ?></textarea>
            <?php elseif ($s['setting_type'] === 'boolean'): ?>
              <label class="form-check">
                <input type="hidden" name="settings[<?= e($s['setting_key']) ?>]" value="0">
                <input type="checkbox" name="settings[<?= e($s['setting_key']) ?>]" value="1" <?= $s['setting_value'] ? 'checked' : '' ?>>
                <span>Aktif</span>
              </label>
            <?php elseif ($s['setting_type'] === 'number'): ?>
              <input type="number" name="settings[<?= e($s['setting_key']) ?>]" class="form-input" value="<?= e($s['setting_value']) ?>">
            <?php elseif ($s['setting_type'] === 'image' && $s['setting_key'] === 'site_logo'): ?>
              <?php if ($s['setting_value']): ?>
                <img src="<?= upload_url($s['setting_value']) ?>" style="max-width:200px; max-height:80px; margin-bottom:8px;">
              <?php endif ?>
              <input type="file" name="logo_upload" class="form-input" accept="image/*">
              <input type="hidden" name="settings[<?= e($s['setting_key']) ?>]" value="<?= e($s['setting_value']) ?>">
            <?php elseif ($s['setting_type'] === 'email'): ?>
              <input type="email" name="settings[<?= e($s['setting_key']) ?>]" class="form-input" value="<?= e($s['setting_value']) ?>">
            <?php else: ?>
              <input type="text" name="settings[<?= e($s['setting_key']) ?>]" class="form-input" value="<?= e($s['setting_value']) ?>">
            <?php endif ?>
          </div>
        <?php endforeach; endif ?>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Ayarları Kaydet</button>
        </div>
      </div>
    </div>
  </form>
</div>

<style>@media (max-width: 991px) { .settings-grid { grid-template-columns: 1fr !important; } }</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
