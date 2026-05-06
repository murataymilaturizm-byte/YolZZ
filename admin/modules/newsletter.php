<?php
$page_title = 'Bülten Aboneleri';
$active_menu = 'newsletter';

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/header.php';

if (($_GET['action'] ?? '') === 'delete' && !empty($_GET['id'])) {
    db()->delete('newsletter_subscribers', 'id = :id', ['id' => (int)$_GET['id']]);
    flash_success('Abone silindi.');
    redirect(admin_url('modules/newsletter.php'));
}

if (($_GET['action'] ?? '') === 'export') {
    $subs = db()->fetchAll("SELECT email, subscribed_at FROM newsletter_subscribers WHERE is_active = 1 ORDER BY subscribed_at DESC");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="aboneler_' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM
    $out = fopen('php://output', 'w');
    fputcsv($out, ['E-posta', 'Abonelik Tarihi']);
    foreach ($subs as $s) fputcsv($out, [$s['email'], $s['subscribed_at']]);
    fclose($out); exit;
}

$subs = db()->fetchAll("SELECT * FROM newsletter_subscribers ORDER BY subscribed_at DESC LIMIT 500");
$total = db()->fetchColumn("SELECT COUNT(*) FROM newsletter_subscribers WHERE is_active = 1");
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Bülten Aboneleri</h2>
    <div class="subtitle"><?= number_format($total) ?> aktif abone</div>
  </div>
  <a href="?action=export" class="btn btn-primary">📥 CSV İndir</a>
</div>

<div class="card">
<?php if (empty($subs)): ?>
  <div class="empty-state"><div class="empty-state-icon">📬</div><div class="empty-state-title">Abone yok</div></div>
<?php else: ?>
<div class="table-wrapper" style="border:0; border-radius:0;">
  <table class="table">
    <thead><tr><th>E-posta</th><th>Dil</th><th>Abonelik</th><th>Durum</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($subs as $s): ?>
      <tr>
        <td><strong><?= e($s['email']) ?></strong></td>
        <td><?= strtoupper(e($s['lang'])) ?></td>
        <td style="font-size:12px;"><?= tr_date($s['subscribed_at']) ?></td>
        <td><span class="badge badge-<?= $s['is_active'] ? 'success' : 'gray' ?>"><?= $s['is_active'] ? 'Aktif' : 'İptal' ?></span></td>
        <td class="table-actions">
          <a href="?action=delete&id=<?= $s['id'] ?>" class="btn btn-outline btn-sm" style="color:var(--danger);" onclick="return confirm('Silinsin mi?')">Sil</a>
        </td>
      </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>
<?php endif ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
