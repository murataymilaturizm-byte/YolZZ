<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'İletişim — Bize Ulaşın | Yolzz Araç Kiralama';
$pageDescription = 'Yolzz müşteri hizmetlerine 7/24 ulaşın. Telefon, WhatsApp, e-posta ile rezervasyon ve destek. Sorularınızı hemen yanıtlıyoruz.';
$pageKeywords = 'yolzz iletişim, araç kiralama iletişim, rent a car müşteri hizmetleri, whatsapp destek';
$currentPage = '/iletisim';

// LocalBusiness (AutoRental) Schema
$pageJsonLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'AutoRental',
    'name' => 'Yolzz',
    'url' => SITE_URL,
    'telephone' => setting('site_phone', '+90 850 000 00 00'),
    'email' => setting('site_email', 'info@yolzz.com'),
    'description' => $pageDescription,
    'areaServed' => [
        '@type' => 'Country',
        'name' => 'Türkiye'
    ],
    'priceRange' => '₺₺',
    'openingHours' => 'Mo-Su 00:00-23:59',
    'image' => url('assets/img/logo-full.svg')
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('_csrf'))) {
        flash_error('Güvenlik doğrulaması başarısız.');
    } else {
        $data = [
            'name' => post('name'),
            'email' => post('email'),
            'phone' => post('phone'),
            'subject' => post('subject'),
            'message' => post('message'),
            'type' => post('type', 'general'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'status' => 'new'
        ];
        if (!$data['name'] || !$data['email'] || !$data['message']) {
            flash_error('Lütfen tüm zorunlu alanları doldurun.');
        } else {
            db()->insert('contact_messages', $data);
            flash_success('Mesajınız alındı! En kısa sürede dönüş yapacağız.');
            redirect(url('iletisim'));
        }
    }
}

include __DIR__ . '/includes/frontend/header.php';
?>

<section class="page-hero" style="padding:50px 0;">
  <div class="container" style="max-width:1200px; margin:0 auto; padding:0 20px; text-align:center;">
    <h1 style="font-size:36px; color:var(--ink); margin-bottom:10px;">📞 İletişim</h1>
    <p style="color:var(--text);">Size nasıl yardımcı olabiliriz?</p>
  </div>
</section>

<section style="padding:30px 0 80px;">
  <div class="container" style="max-width:1100px; margin:0 auto; padding:0 20px;">
    <div style="display:grid; grid-template-columns: 1fr 2fr; gap:40px;" class="contact-grid">

      <!-- Bilgi -->
      <div>
        <div class="contact-info-card">
          <h3>📞 Telefon</h3>
          <p>7/24 müşteri hizmetleri</p>
          <a href="tel:<?= preg_replace('/\s+/', '', setting('site_phone', '08505000777')) ?>"><?= e(setting('site_phone', '0850 500 0 777')) ?></a>
        </div>

        <div class="contact-info-card">
          <h3>✉ E-posta</h3>
          <p>Mesajınızı gönderin, hızlıca dönelim</p>
          <a href="mailto:<?= e(setting('contact_email', 'info@yolzz.com')) ?>"><?= e(setting('contact_email', 'info@yolzz.com')) ?></a>
        </div>

        <div class="contact-info-card">
          <h3>📍 Merkez Ofis</h3>
          <p><?= e(setting('company_address', "Şişli, İstanbul")) ?></p>
          <?php if (setting('company_map_url')): ?>
            <a href="<?= e(setting('company_map_url')) ?>" target="_blank">Haritada Gör →</a>
          <?php endif ?>
        </div>

        <div class="contact-info-card">
          <h3>⏰ Çalışma Saatleri</h3>
          <p>Haftaiçi: 08:00 - 22:00<br>Haftasonu: 09:00 - 20:00<br>Havalimanı ofisleri: 7/24</p>
        </div>
      </div>

      <!-- Form -->
      <div>
        <div class="contact-card-big">
          <h2>Bize Yazın</h2>
          <p style="color:var(--text-muted); margin-bottom:24px;">Formu doldurun, 24 saat içinde size döneceğiz.</p>

          <form method="POST">
            <?= csrf_field() ?>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px;" class="cf-grid">
              <div>
                <label>Adınız *</label>
                <input type="text" name="name" class="co-input" required>
              </div>
              <div>
                <label>E-posta *</label>
                <input type="email" name="email" class="co-input" required>
              </div>
              <div>
                <label>Telefon</label>
                <input type="tel" name="phone" class="co-input">
              </div>
              <div>
                <label>Konu Türü</label>
                <select name="type" class="co-input">
                  <option value="general">Genel</option>
                  <option value="booking">Rezervasyon</option>
                  <option value="complaint">Şikayet</option>
                  <option value="corporate">Kurumsal</option>
                </select>
              </div>
              <div style="grid-column: 1 / -1;">
                <label>Konu</label>
                <input type="text" name="subject" class="co-input">
              </div>
              <div style="grid-column: 1 / -1;">
                <label>Mesajınız *</label>
                <textarea name="message" class="co-input" rows="6" required></textarea>
              </div>
            </div>
            <button type="submit" class="btn-accent-full" style="margin-top:20px; width:100%; padding:16px; background:var(--accent); color:#fff; border:0; border-radius:10px; font-weight:700; font-size:15px; cursor:pointer;">
              Mesajı Gönder
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<style>
.contact-grid { display: grid; }
@media (max-width: 768px) { .contact-grid { grid-template-columns: 1fr !important; } }
.contact-info-card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 12px; }
.contact-info-card h3 { font-size: 15px; color: var(--ink); margin-bottom: 4px; }
.contact-info-card p { font-size: 12px; color: var(--text-muted); margin-bottom: 8px; }
.contact-info-card a { color: var(--brand); text-decoration: none; font-weight: 600; }
.contact-card-big { background: #fff; border: 1px solid var(--border); border-radius: 16px; padding: 32px; box-shadow: var(--shadow); }
.contact-card-big h2 { font-size: 22px; color: var(--ink); margin-bottom: 8px; }
.cf-grid label { display: block; font-size: 12px; font-weight: 600; color: var(--ink); margin-bottom: 4px; }
.co-input { width: 100%; padding: 12px 14px; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; font-size: 14px; }
.co-input:focus { outline: none; border-color: var(--brand); }
@media (max-width: 600px) { .cf-grid { grid-template-columns: 1fr !important; } }
</style>

<?php include __DIR__ . '/includes/frontend/footer.php'; ?>
