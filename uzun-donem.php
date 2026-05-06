<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Uzun Dönem Araç Kiralama — Aylık & Yıllık Kurumsal Çözümler | Yolzz';
$pageDescription = 'Uzun dönem araç kiralama avantajlı fiyatlarla. Aylık, yıllık ve filo kiralama seçenekleri. Bireysel ve kurumsal müşterilerimize özel paketler.';
$pageKeywords = 'uzun dönem araç kiralama, aylık araç kiralama, yıllık kiralama, filo kiralama, kurumsal araç kiralama';
$currentPage = '/uzun-donem';

// Pages tablosundan var mı?
$pageContent = db()->fetch("SELECT * FROM pages WHERE slug = 'uzun-donem' AND status = 'published'");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('_csrf'))) {
        flash_error('Güvenlik doğrulaması başarısız.');
    } else {
        $data = [
            'name' => post('name'),
            'email' => post('email'),
            'phone' => post('phone'),
            'subject' => 'Uzun Dönem Kiralama Talebi',
            'message' => "Uzun dönem kiralama talebi\n\nSüre: " . post('duration') . "\nİhtiyaç: " . post('requirement') . "\n\nNot:\n" . post('message'),
            'type' => 'corporate',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'status' => 'new'
        ];
        if (!$data['name'] || !$data['email'] || !$data['phone']) {
            flash_error('Ad, e-posta ve telefon zorunludur.');
        } else {
            db()->insert('contact_messages', $data);
            flash_success('Talebiniz alındı! En kısa sürede sizinle iletişime geçeceğiz.');
            redirect(url('uzun-donem'));
        }
    }
}

include __DIR__ . '/includes/frontend/header.php';
?>

<section class="page-hero" style="padding:70px 0; background:linear-gradient(135deg, var(--brand), var(--brand-darker)); color:#fff;">
  <div class="container" style="max-width:900px; margin:0 auto; padding:0 20px; text-align:center;">
    <h1 style="font-size:40px; margin-bottom:12px;">📅 Uzun Dönem Kiralama</h1>
    <p style="font-size:16px; opacity:0.95;">1 ay ve üzeri kiralamalarda özel fiyatlar, daha fazla avantaj</p>
  </div>
</section>

<?php if ($pageContent && $pageContent['content_tr']): ?>
<!-- Admin panelden özel içerik eklendiyse onu göster -->
<section style="padding:60px 0;">
  <div class="container" style="max-width:900px; margin:0 auto; padding:0 20px;">
    <div class="uzun-content"><?= $pageContent['content_tr'] ?></div>
  </div>
</section>
<?php else: ?>

<!-- Varsayılan içerik -->
<section style="padding:60px 0;">
  <div class="container" style="max-width:1100px; margin:0 auto; padding:0 20px;">
    <div style="text-align:center; margin-bottom:40px;">
      <h2 style="font-size:28px; color:var(--ink); margin-bottom:12px;">Neden Uzun Dönem Kiralama?</h2>
      <p style="color:var(--text); max-width:600px; margin:0 auto;">Uzun dönem kiralama, bireyler ve işletmeler için araç sahipliğine göre büyük avantajlar sunar.</p>
    </div>

    <div class="uzun-benefits">
      <div class="uzun-card">
        <div class="uc-icon">💰</div>
        <h3>Ekonomik Maliyet</h3>
        <p>Günlük kiralamaya göre %40'a varan indirimler. Amortisman, sigorta, bakım dert değil.</p>
      </div>
      <div class="uzun-card">
        <div class="uc-icon">🛠</div>
        <h3>Bakım Dahil</h3>
        <p>Periyodik bakım, lastik değişimi, sigorta, kasko — tamamı kiralama bedeline dahildir.</p>
      </div>
      <div class="uzun-card">
        <div class="uc-icon">📊</div>
        <h3>Vergi Avantajı</h3>
        <p>Kurumsal kiralamalarda KDV ve stopaj avantajları ile bütçenize katkı sağlayın.</p>
      </div>
      <div class="uzun-card">
        <div class="uc-icon">🔄</div>
        <h3>Araç Değişimi</h3>
        <p>Dönem sonunda yeni modellere geçiş yapabilir, her zaman güncel araçlarla yola çıkabilirsiniz.</p>
      </div>
      <div class="uzun-card">
        <div class="uc-icon">📞</div>
        <h3>7/24 Destek</h3>
        <p>Yol yardım, ikame araç ve müşteri destek ekibimiz her an yanınızda.</p>
      </div>
      <div class="uzun-card">
        <div class="uc-icon">📋</div>
        <h3>Kolay Raporlama</h3>
        <p>Tek fatura, aylık rapor, yakıt takibi gibi kurumsal çözümler.</p>
      </div>
    </div>
  </div>
</section>

<!-- SÜRE PAKETLERİ -->
<section style="padding:40px 0 60px; background:var(--bg-soft);">
  <div class="container" style="max-width:1100px; margin:0 auto; padding:0 20px;">
    <div style="text-align:center; margin-bottom:40px;">
      <h2 style="font-size:28px; color:var(--ink); margin-bottom:10px;">Kiralama Süreleri</h2>
      <p style="color:var(--text);">İhtiyacınıza uygun periyodu seçin, size özel teklif alın</p>
    </div>
    <div class="uzun-packages">
      <div class="uzun-pkg">
        <div class="up-period">1-3 Ay</div>
        <div class="up-badge">Esnek</div>
        <ul>
          <li>Kısa vadeli projeler</li>
          <li>Esnek iade opsiyonu</li>
          <li>Hızlı teslim</li>
        </ul>
      </div>
      <div class="uzun-pkg uzun-pkg-featured">
        <div class="up-period">3-12 Ay</div>
        <div class="up-badge">En Popüler</div>
        <ul>
          <li>En avantajlı fiyatlar</li>
          <li>Bakım, sigorta dahil</li>
          <li>Kurumsal raporlama</li>
        </ul>
      </div>
      <div class="uzun-pkg">
        <div class="up-period">12+ Ay</div>
        <div class="up-badge">Premium</div>
        <ul>
          <li>Maksimum indirim</li>
          <li>Yeni model araç seçenekleri</li>
          <li>VIP destek</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- FORM -->
<section style="padding:60px 0;">
  <div class="container" style="max-width:800px; margin:0 auto; padding:0 20px;">
    <div class="uzun-form-card">
      <h2 style="font-size:24px; color:var(--ink); margin-bottom:8px; text-align:center;">Teklif İsteyin</h2>
      <p style="color:var(--text-muted); margin-bottom:24px; text-align:center;">İhtiyacınıza uygun özel teklifi 24 saat içinde size gönderelim</p>

      <form method="POST">
        <?= csrf_field() ?>
        <div class="form-grid-2">
          <div>
            <label>Ad Soyad *</label>
            <input type="text" name="name" class="co-input" required>
          </div>
          <div>
            <label>E-posta *</label>
            <input type="email" name="email" class="co-input" required>
          </div>
          <div>
            <label>Telefon *</label>
            <input type="tel" name="phone" class="co-input" required>
          </div>
          <div>
            <label>Planlanan Süre</label>
            <select name="duration" class="co-input">
              <option value="1-3 ay">1-3 Ay</option>
              <option value="3-6 ay">3-6 Ay</option>
              <option value="6-12 ay">6-12 Ay</option>
              <option value="12+ ay">12 Ay ve üzeri</option>
            </select>
          </div>
          <div style="grid-column: 1 / -1;">
            <label>Araç İhtiyacı (kaç araç, segment vs.)</label>
            <input type="text" name="requirement" class="co-input" placeholder="Örn: 2 adet sedan, 1 adet SUV">
          </div>
          <div style="grid-column: 1 / -1;">
            <label>Ek Bilgi / Notlar</label>
            <textarea name="message" class="co-input" rows="4" placeholder="Kiralama sürecinizle ilgili detaylar..."></textarea>
          </div>
        </div>
        <button type="submit" style="margin-top:20px; width:100%; padding:16px; background:var(--accent); color:#fff; border:0; border-radius:12px; font-weight:700; font-size:15px; cursor:pointer; box-shadow:var(--shadow-accent);">
          Teklif Talebi Gönder
        </button>
      </form>
    </div>
  </div>
</section>

<?php endif; ?>

<style>
.uzun-benefits { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
@media (max-width: 768px) { .uzun-benefits { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .uzun-benefits { grid-template-columns: 1fr; } }
.uzun-card { background: #fff; padding: 28px 22px; border-radius: 16px; border: 1px solid var(--border); text-align: center; transition: all 0.3s; }
.uzun-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); border-color: var(--brand-light); }
.uc-icon { font-size: 38px; margin-bottom: 14px; }
.uzun-card h3 { font-size: 16px; color: var(--ink); margin-bottom: 10px; font-weight: 700; }
.uzun-card p { font-size: 13px; color: var(--text); line-height: 1.6; }

.uzun-packages { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
@media (max-width: 768px) { .uzun-packages { grid-template-columns: 1fr; } }
.uzun-pkg { background: #fff; border: 1px solid var(--border); border-radius: 16px; padding: 32px 24px; text-align: center; position: relative; }
.uzun-pkg-featured { border-color: var(--brand); border-width: 2px; transform: scale(1.03); box-shadow: 0 10px 40px rgba(29,113,184,0.15); }
.up-period { font-size: 28px; font-weight: 800; color: var(--brand); margin-bottom: 10px; }
.up-badge { display: inline-block; padding: 4px 12px; background: var(--brand-wash); color: var(--brand); border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 20px; }
.uzun-pkg-featured .up-badge { background: var(--accent); color: #fff; }
.uzun-pkg ul { list-style: none; padding: 0; margin: 0; }
.uzun-pkg li { padding: 8px 0; font-size: 13px; color: var(--text); border-bottom: 1px solid var(--border-soft); }
.uzun-pkg li:last-child { border-bottom: 0; }
.uzun-pkg li::before { content: "✓"; color: var(--success); font-weight: 700; margin-right: 8px; }

.uzun-form-card { background: #fff; border: 1px solid var(--border); border-radius: 16px; padding: 32px; box-shadow: var(--shadow); }
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 600px) { .form-grid-2 { grid-template-columns: 1fr; } }
.form-grid-2 label { display: block; font-size: 12px; font-weight: 600; color: var(--ink); margin-bottom: 4px; }
.co-input { width: 100%; padding: 12px 14px; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; font-size: 14px; }
.co-input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-wash); }

.uzun-content { font-size: 16px; line-height: 1.8; color: var(--text); background: #fff; padding: 32px; border-radius: 16px; border: 1px solid var(--border); }
.uzun-content h2 { font-size: 24px; color: var(--ink); margin: 24px 0 14px; }
.uzun-content p, .uzun-content ul { margin-bottom: 16px; }
.uzun-content ul { padding-left: 24px; }
</style>

<?php include __DIR__ . '/includes/frontend/footer.php'; ?>
