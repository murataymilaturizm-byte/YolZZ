<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Bayilik Başvurusu — Yolzz İş Ortağı Olun | Rent a Car Franchise';
$pageDescription = 'Yolzz ailesine katılın, Türkiye\'nin en büyük araç kiralama platformunda yerinizi alın. Bayi başvurusu ve kazanma fırsatları.';
$pageKeywords = 'rent a car bayilik, araç kiralama franchise, bayi başvurusu, iş ortağı, yolzz bayi';
$currentPage = '/bayilik';

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('_csrf'))) {
        flash_error('Güvenlik doğrulaması başarısız.');
    } else {
        $required = ['company_name', 'contact_name', 'email', 'phone', 'city'];
        $valid = true;
        foreach ($required as $f) {
            if (empty(post($f))) { $valid = false; break; }
        }
        if (!$valid) {
            flash_error('Lütfen zorunlu alanları doldurun.');
        } else {
            // E-posta zaten kayıtlı mı?
            $exists = db()->fetchColumn("SELECT COUNT(*) FROM dealers WHERE email = ?", [post('email')]);
            if ($exists > 0) {
                flash_warning('Bu e-posta ile daha önce başvuru yapılmış.');
            } else {
                $data = [
                    'company_name' => post('company_name'),
                    'tax_number' => post('tax_number'),
                    'contact_name' => post('contact_name'),
                    'email' => post('email'),
                    'phone' => post('phone'),
                    'website' => post('website'),
                    'city' => post('city'),
                    'district' => post('district'),
                    'address' => post('address'),
                    'fleet_size' => (int)post('fleet_size'),
                    'years_in_business' => (int)post('years_in_business'),
                    'description' => post('description'),
                    'status' => 'pending'
                ];
                db()->insert('dealers', $data);
                log_activity('dealer_application', 'dealers', 'Yeni bayi başvurusu: ' . $data['company_name']);
                $success = true;
            }
        }
    }
}

$cities = db()->fetchAll("SELECT * FROM cities WHERE is_active = 1 ORDER BY name");

include __DIR__ . '/includes/frontend/header.php';
?>

<section class="page-hero" style="padding:70px 0; background:linear-gradient(135deg, var(--accent), var(--brand)); color:#fff;">
  <div class="container" style="max-width:900px; margin:0 auto; padding:0 20px; text-align:center;">
    <h1 style="font-size:40px; margin-bottom:12px;">🤝 Bayilik Başvurusu</h1>
    <p style="font-size:16px; opacity:0.95;">Yolzz ailesine katılın. Türkiye'nin en büyük kiralama ağında yerinizi alın.</p>
  </div>
</section>

<section style="padding:60px 0; background:var(--bg-soft); min-height:60vh;">
  <div class="container" style="max-width:900px; margin:0 auto; padding:0 20px;">

    <?php if ($success): ?>
      <div class="success-box">
        <div class="ch-icon">
          <svg width="60" height="60" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h2>Başvurunuz Alındı!</h2>
        <p>En kısa sürede başvurunuzu değerlendirip size geri dönüş yapacağız. Teşekkürler!</p>
        <a href="<?= url('/') ?>" class="btn-primary-lg" style="margin-top:20px; display:inline-block; padding:12px 24px; background:var(--brand); color:#fff; border-radius:10px; text-decoration:none; font-weight:600;">Ana Sayfaya Dön</a>
      </div>
    <?php else: ?>

    <!-- Avantajlar -->
    <div class="benefits-grid">
      <div class="benefit-card">
        <div class="bc-icon">📈</div>
        <h3>Düzenli Gelir</h3>
        <p>Filonuzu değerlendirin, istikrarlı bir gelir elde edin.</p>
      </div>
      <div class="benefit-card">
        <div class="bc-icon">🌐</div>
        <h3>Geniş Müşteri Ağı</h3>
        <p>Türkiye genelinde milyonlarca müşteriye ulaşın.</p>
      </div>
      <div class="benefit-card">
        <div class="bc-icon">🛠</div>
        <h3>Teknik Destek</h3>
        <p>Online sistem, eğitim ve 7/24 teknik destek.</p>
      </div>
      <div class="benefit-card">
        <div class="bc-icon">💼</div>
        <h3>Pazarlama Desteği</h3>
        <p>Ulusal reklamlar ve dijital pazarlama desteği.</p>
      </div>
      <div class="benefit-card">
        <div class="bc-icon">💳</div>
        <h3>Güvenli Ödeme</h3>
        <p>Tüm ödemeler bankalar üzerinden güvenli şekilde tahsil edilir.</p>
      </div>
      <div class="benefit-card">
        <div class="bc-icon">🚀</div>
        <h3>Hızlı Başlangıç</h3>
        <p>Başvurunuz onaylandıktan sonra 24 saatte sistemde yer alın.</p>
      </div>
    </div>

    <div class="dealer-form-card">
      <h2>Başvuru Formu</h2>
      <p style="color:var(--text-muted); margin-bottom:24px;">Formu eksiksiz doldurun. Uygun bulunan başvurulara geri dönüş yapılacaktır.</p>

      <form method="POST">
        <?= csrf_field() ?>

        <h3 class="dform-section">🏢 Firma Bilgileri</h3>
        <div class="form-grid-2">
          <div>
            <label>Firma Adı *</label>
            <input type="text" name="company_name" class="co-input" required>
          </div>
          <div>
            <label>Vergi Numarası</label>
            <input type="text" name="tax_number" class="co-input">
          </div>
          <div>
            <label>Faaliyet Yılı (kaç yıldır)</label>
            <input type="number" name="years_in_business" class="co-input" min="0">
          </div>
          <div>
            <label>Filo Büyüklüğü (araç sayısı)</label>
            <input type="number" name="fleet_size" class="co-input" min="0">
          </div>
          <div style="grid-column: 1 / -1;">
            <label>Web Sitesi</label>
            <input type="url" name="website" class="co-input" placeholder="https://">
          </div>
        </div>

        <h3 class="dform-section">👤 Yetkili Kişi</h3>
        <div class="form-grid-2">
          <div>
            <label>Yetkili Ad Soyad *</label>
            <input type="text" name="contact_name" class="co-input" required>
          </div>
          <div>
            <label>E-posta *</label>
            <input type="email" name="email" class="co-input" required>
          </div>
          <div>
            <label>Telefon *</label>
            <input type="tel" name="phone" class="co-input" required>
          </div>
        </div>

        <h3 class="dform-section">📍 Adres</h3>
        <div class="form-grid-2">
          <div>
            <label>Şehir *</label>
            <select name="city" class="co-input" required>
              <option value="">Seçin</option>
              <?php foreach ($cities as $c): ?>
                <option value="<?= e($c['name']) ?>"><?= e($c['name']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div>
            <label>İlçe</label>
            <input type="text" name="district" class="co-input">
          </div>
          <div style="grid-column: 1 / -1;">
            <label>Adres</label>
            <textarea name="address" class="co-input" rows="2"></textarea>
          </div>
        </div>

        <h3 class="dform-section">📝 Ek Bilgi</h3>
        <div style="margin-bottom:20px;">
          <label>Firma Hakkında Kısa Açıklama</label>
          <textarea name="description" class="co-input" rows="4" placeholder="Şirketiniz, faaliyet alanlarınız ve hedefleriniz hakkında..."></textarea>
        </div>

        <label class="kvkk-row">
          <input type="checkbox" required>
          <span class="kvkk-row-text">
            <a href="<?= url('kvkk') ?>" target="_blank">KVKK aydınlatma metnini</a> okudum, kişisel verilerimin işlenmesini kabul ediyorum.
          </span>
        </label>

        <button type="submit" class="btn-accent-full" style="width:100%; padding:16px; background:var(--accent); color:#fff; border:0; border-radius:12px; font-weight:700; font-size:16px; cursor:pointer; box-shadow:var(--shadow-accent);">
          Başvuruyu Gönder
        </button>
      </form>
    </div>

    <?php endif ?>
  </div>
</section>

<style>
.benefits-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 30px; }
@media (max-width: 768px) { .benefits-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .benefits-grid { grid-template-columns: 1fr; } }
.benefit-card { background: #fff; padding: 24px; border-radius: 16px; text-align: center; border: 1px solid var(--border); }
.bc-icon { font-size: 36px; margin-bottom: 12px; }
.benefit-card h3 { font-size: 15px; color: var(--ink); margin-bottom: 6px; }
.benefit-card p { font-size: 12px; color: var(--text-muted); line-height: 1.5; }

.dealer-form-card { background: #fff; border-radius: 16px; padding: 32px; box-shadow: var(--shadow); }
.dealer-form-card h2 { font-size: 24px; color: var(--ink); margin-bottom: 6px; }
.dform-section { font-size: 15px; color: var(--brand); margin: 24px 0 14px; padding-bottom: 8px; border-bottom: 2px solid var(--brand-wash); }
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 600px) { .form-grid-2 { grid-template-columns: 1fr; } }
.form-grid-2 > div label, .dform-section + div label { display: block; font-size: 12px; font-weight: 600; color: var(--ink); margin-bottom: 4px; }
.co-input { width: 100%; padding: 12px 14px; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; font-size: 14px; }
.co-input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-wash); }

.success-box { background: #fff; padding: 50px 30px; border-radius: 16px; text-align: center; box-shadow: var(--shadow); }
.ch-icon { width: 90px; height: 90px; border-radius: 50%; background: var(--success); color: #fff; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
.success-box h2 { color: var(--ink); margin-bottom: 10px; }
.success-box p { color: var(--text-muted); }
</style>

<?php include __DIR__ . '/includes/frontend/footer.php'; ?>
