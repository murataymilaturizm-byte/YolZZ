<?php
$page_title = 'İletişim Mesajları';
$active_menu = 'messages';

require_once __DIR__ . '/../../includes/bootstrap.php';

$action = get('action');
$id = (int)get('id', 0);

if ($action === 'delete' && $id) {
    db()->delete('contact_messages', 'id = :id', ['id' => $id]);
    flash_success('Mesaj silindi.');
    redirect(admin_url('modules/messages.php'));
}

if ($action === 'mark' && $id) {
    $status = get('status', 'read');
    db()->update('contact_messages', ['status' => $status], 'id = :id', ['id' => $id]);
    redirect(admin_url('modules/messages.php' . ($action === 'view' ? "?action=view&id=$id" : '')));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'reply') {
    $reply = post('admin_reply');
    db()->update('contact_messages', [
        'admin_reply' => $reply,
        'status' => 'replied',
        'replied_at' => date('Y-m-d H:i:s'),
        'replied_by' => current_user()['id']
    ], 'id = :id', ['id' => $id]);
    flash_success('Yanıt kaydedildi. Lütfen müşteriye e-posta ile gönderin.');
    redirect(admin_url('modules/messages.php'));
}

require_once __DIR__ . '/../includes/header.php';

if ($action === 'view' && $id):
    $msg = db()->fetch("SELECT * FROM contact_messages WHERE id = ?", [$id]);
    if (!$msg) { flash_error('Mesaj bulunamadı.'); redirect(admin_url('modules/messages.php')); }
    if ($msg['status'] === 'new') {
        db()->update('contact_messages', ['status' => 'read'], 'id = :id', ['id' => $id]);
    }
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Mesaj Detayı</h2>
    <div class="subtitle"><?= tr_date($msg['created_at'], true) ?></div>
  </div>
  <a href="<?= admin_url('modules/messages.php') ?>" class="btn btn-outline">← Geri</a>
</div>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px;" class="msg-detail-grid">
  <div>
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <div class="card-title"><?= e($msg['subject'] ?: 'Konu Yok') ?></div>
      </div>
      <div class="card-body">
        <div style="padding:12px; background:var(--bg-soft); border-radius:8px; white-space:pre-wrap; line-height:1.7;"><?= e($msg['message']) ?></div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><div class="card-title">✉ Yanıt</div></div>
      <div class="card-body">
        <?php if ($msg['admin_reply']): ?>
          <div style="padding:12px; background:var(--brand-wash); border-left:3px solid var(--brand); border-radius:6px; margin-bottom:12px; white-space:pre-wrap;"><?= e($msg['admin_reply']) ?></div>
          <div style="font-size:11px; color:var(--text-muted);">Yanıtlandı: <?= tr_date($msg['replied_at'], true) ?></div>
        <?php else: ?>
          <form method="POST">
            <input type="hidden" name="action" value="reply">
            <div class="form-row">
              <textarea name="admin_reply" class="form-textarea" rows="6" placeholder="Müşteriye yanıtınızı yazın..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Yanıtı Kaydet</button>
          </form>
        <?php endif ?>
      </div>
    </div>
  </div>

  <div>
    <div class="card">
      <div class="card-header"><div class="card-title">Gönderen</div></div>
      <div class="card-body">
        <div style="font-size:16px; font-weight:600; margin-bottom:8px;"><?= e($msg['name']) ?></div>
        <div style="font-size:13px; margin-bottom:4px;">📧 <a href="mailto:<?= e($msg['email']) ?>"><?= e($msg['email']) ?></a></div>
        <?php if ($msg['phone']): ?>
          <div style="font-size:13px; margin-bottom:4px;">📱 <a href="tel:<?= e($msg['phone']) ?>"><?= e($msg['phone']) ?></a></div>
        <?php endif ?>
        <div style="margin-top:12px;"><span class="badge badge-info"><?= e($msg['type']) ?></span></div>
        <hr style="margin:16px 0; border:0; border-top:1px solid var(--border);">
        <a href="?action=delete&id=<?= $msg['id'] ?>" class="btn btn-outline btn-sm" style="width:100%; justify-content:center; color:var(--danger);" onclick="return confirm('Silinsin mi?')">🗑 Sil</a>
      </div>
    </div>
  </div>
</div>

<style>@media (max-width: 991px) { .msg-detail-grid { grid-template-columns: 1fr !important; } }</style>

<?php else:
    $status_filter = get('status', '');
    $where = ['1=1']; $params = [];
    if ($status_filter) { $where[] = "status = :st"; $params['st'] = $status_filter; }

    $messages = db()->fetchAll(
        "SELECT * FROM contact_messages WHERE " . implode(' AND ', $where) . " ORDER BY
         CASE status WHEN 'new' THEN 1 WHEN 'read' THEN 2 ELSE 3 END, created_at DESC LIMIT 100",
        $params
    );
    $counts = db()->fetch("SELECT
        SUM(status='new') AS new_c, SUM(status='read') AS read_c, SUM(status='replied') AS replied_c FROM contact_messages");
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>İletişim Mesajları</h2>
    <div class="subtitle"><?= count($messages) ?> mesaj</div>
  </div>
</div>

<div class="stats-grid" style="margin-bottom:16px;">
  <a href="?status=new" class="stat-card" style="text-decoration:none;">
    <div class="stat-icon yellow">✉</div>
    <div><div class="stat-label">Yeni</div><div class="stat-value"><?= (int)$counts['new_c'] ?></div></div>
  </a>
  <a href="?status=read" class="stat-card" style="text-decoration:none;">
    <div class="stat-icon blue">👁</div>
    <div><div class="stat-label">Okundu</div><div class="stat-value"><?= (int)$counts['read_c'] ?></div></div>
  </a>
  <a href="?status=replied" class="stat-card" style="text-decoration:none;">
    <div class="stat-icon green">↩</div>
    <div><div class="stat-label">Yanıtlandı</div><div class="stat-value"><?= (int)$counts['replied_c'] ?></div></div>
  </a>
</div>

<div class="card">
<?php if (empty($messages)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">📨</div>
    <div class="empty-state-title">Mesaj yok</div>
  </div>
<?php else: ?>
<div class="table-wrapper" style="border:0; border-radius:0;">
  <table class="table">
    <thead><tr><th>Gönderen</th><th>Konu</th><th>Tür</th><th>Durum</th><th>Tarih</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($messages as $m): ?>
      <tr style="<?= $m['status'] === 'new' ? 'background:rgba(255,237,213,0.4);' : '' ?>">
        <td>
          <strong><?= e($m['name']) ?></strong>
          <div style="font-size:11px; color:var(--text-muted);"><?= e($m['email']) ?></div>
        </td>
        <td><?= e(str_excerpt($m['subject'] ?: $m['message'], 60)) ?></td>
        <td><span class="badge badge-gray"><?= e($m['type']) ?></span></td>
        <td>
          <?php $sm = ['new'=>['accent','Yeni'],'read'=>['gray','Okundu'],'replied'=>['success','Yanıtlandı'],'archived'=>['gray','Arşiv']]; $s = $sm[$m['status']]; ?>
          <span class="badge badge-<?= $s[0] ?>"><?= $s[1] ?></span>
        </td>
        <td style="font-size:12px;"><?= tr_date($m['created_at']) ?></td>
        <td class="table-actions">
          <a href="?action=view&id=<?= $m['id'] ?>" class="btn btn-primary btn-sm">Gör</a>
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
