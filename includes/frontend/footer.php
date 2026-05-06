<?php
if (!defined('YOLZZ_APP')) {
    require_once __DIR__ . '/../includes/bootstrap.php';
}

// Footer menü grupları
$footerCompany = db()->fetchAll("SELECT * FROM menu_items WHERE menu_location = 'footer' AND parent_id IS NULL AND is_active = 1 ORDER BY sort_order LIMIT 6");
$offices = db()->fetchAll("SELECT o.*, c.name AS city_name FROM offices o JOIN cities c ON c.id = o.city_id WHERE o.is_active = 1 ORDER BY o.sort_order LIMIT 6");

// Newsletter'ın GÖSTERİLMEYECEĞİ sayfalar
$hideNewsletter = [
    '/filo',
    '/uzun-donem',
    '/bayilik',
    '/iletisim',
    '/checkout',
    '/confirmation',
    '/rezervasyon-sorgula',
];
$showNewsletter = !in_array($currentPage ?? '', $hideNewsletter, true);
?>

<?php if ($showNewsletter): ?>
<!-- ========== NEWSLETTER ========== -->
<section class="newsletter-sec" style="background:var(--bg-soft); padding: 60px 0;">
  <div class="container" style="max-width:1200px; margin:0 auto; padding:0 20px; text-align:center;">
    <h2 style="color:var(--ink); font-size:28px; margin-bottom:10px;">Fırsatları Kaçırmayın</h2>
    <p style="color:var(--text); margin-bottom:24px;">Kampanyalar ve yeni araçlardan ilk siz haberdar olun</p>
    <form id="newsletterForm" style="display:flex; gap:8px; max-width:500px; margin:0 auto;">
      <input type="email" name="email" placeholder="E-posta adresiniz" required style="flex:1; padding:14px 16px; border:1px solid var(--border); border-radius:10px; font-size:14px; font-family:inherit;">
      <button type="submit" class="btn-accent" style="padding:14px 24px; background:var(--accent); color:#fff; border:0; border-radius:10px; font-weight:600; cursor:pointer;">Abone Ol</button>
    </form>
  </div>
</section>
<?php endif ?>

<!-- ========== FOOTER ========== -->
<style>
/* filo + checkout sayfalarında footer ve newsletter SADECE MOBİLDE gizle */
@media (max-width: 768px) {
  body.filo-page footer,
  body.checkout-page footer,
  body.filo-page .newsletter-sec,
  body.checkout-page .newsletter-sec { display: none !important; }
}
</style>
<footer>
  <div class="foot-inner">
    <div class="foot-grid">
      <div class="foot-brand">
        <?php if (setting('site_logo')): ?>
          <img src="<?= upload_url(setting('site_logo')) ?>" alt="Yolzz" class="logo-img" style="filter:brightness(0) invert(1);">
        <?php else: ?>
          <!-- Yolzz logosu (footer - beyaz filtreli) -->
          <img src="<?= url('assets/img/logo.png') ?>" alt="Yolzz" style="height:36px; width:auto; display:block; filter:brightness(0) invert(1);">
        <?php endif ?>
        <p><?= e(setting('footer_about', "Türkiye'nin her yerinde uygun fiyatlı, güvenilir ve kolay araç kiralama hizmeti — parmaklarınızın ucunda.")) ?></p>
        <div class="foot-social">
          <?php if (setting('social_facebook')): ?>
          <a href="<?= e(setting('social_facebook')) ?>" class="fs-icon" aria-label="Facebook" target="_blank"><svg fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
          <?php endif ?>
          <?php if (setting('social_instagram')): ?>
          <a href="<?= e(setting('social_instagram')) ?>" class="fs-icon" aria-label="Instagram" target="_blank"><svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg></a>
          <?php endif ?>
          <?php if (setting('social_twitter')): ?>
          <a href="<?= e(setting('social_twitter')) ?>" class="fs-icon" aria-label="Twitter" target="_blank"><svg fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
          <?php endif ?>
          <?php if (setting('social_linkedin')): ?>
          <a href="<?= e(setting('social_linkedin')) ?>" class="fs-icon" aria-label="LinkedIn" target="_blank"><svg fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.063 2.063 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>
          <?php endif ?>
        </div>
      </div>

      <div>
        <div class="foot-col-title">Şirket</div>
        <div class="foot-list">
          <a href="<?= url('hakkimizda') ?>">Hakkımızda</a>
          <a href="<?= url('filo') ?>">Filomuz</a>
          <a href="<?= url('ofislerimiz') ?>">Ofislerimiz</a>
          <a href="<?= url('iletisim') ?>">İletişim</a>
          <a href="<?= url('bayilik') ?>">Bayilik Başvurusu</a>
          <a href="<?= url('blog') ?>">Blog</a>
        </div>
      </div>

      <div>
        <div class="foot-col-title">Hizmetler</div>
        <div class="foot-list">
          <a href="<?= url('filo') ?>">Günlük Kiralama</a>
          <a href="<?= url('uzun-donem') ?>">Uzun Dönem Kiralama</a>
          <a href="<?= url('ofislerimiz?type=airport') ?>">Havalimanı Kiralama</a>
          <a href="<?= url('kampanyalar') ?>">Kampanyalar</a>
          <a href="<?= url('sss') ?>">SSS</a>
          <a href="<?= url('rezervasyon-sorgula') ?>">Rezervasyon Sorgula</a>
        </div>
      </div>

      <div>
        <div class="foot-col-title">İletişim</div>
        <div class="foot-contact">
          <?php $phone = setting('site_phone', '0850 500 0 777'); ?>
          <a href="tel:<?= preg_replace('/\s+/', '', $phone) ?>" class="fc-item">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            <span><?= e($phone) ?><br><small style="opacity:0.6">7/24 Müşteri Hizmetleri</small></span>
          </a>
          <?php $email = setting('contact_email', 'info@yolzz.com'); ?>
          <a href="mailto:<?= e($email) ?>" class="fc-item">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <?= e($email) ?>
          </a>
          <div class="fc-item">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span><?= (int)setting('total_offices', 110) ?>+ Ofis<br><small style="opacity:0.6">Türkiye Geneli</small></span>
          </div>
        </div>
      </div>
    </div>

    <div class="foot-bottom">
      <div>© <?= date('Y') ?> Yolzz. Tüm hakları saklıdır.</div>
      <div class="foot-legal">
        <a href="<?= url('kvkk') ?>">KVKK</a>
        <a href="<?= url('gizlilik-politikasi') ?>">Gizlilik Politikası</a>
        <a href="<?= url('cerez-politikasi') ?>">Çerez Politikası</a>
        <a href="<?= url('kiralama-sartlari') ?>">Kiralama Şartları</a>
      </div>
      <div class="pay-row">
        <span class="pay-badge">VISA</span>
        <span class="pay-badge">MASTER</span>
        <span class="pay-badge">AXESS</span>
        <span class="pay-badge">BONUS</span>
        <span class="pay-badge">WORLD</span>
        <span class="pay-badge">SSL</span>
      </div>
    </div>
  </div>
</footer>

<script>
// Newsletter
document.getElementById('newsletterForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const form = e.target;
  const email = form.email.value.trim();
  const btn = form.querySelector('button');
  const oldText = btn.textContent;
  btn.textContent = '...';
  btn.disabled = true;

  // Önceki mesajı temizle
  const existingMsg = form.parentElement.querySelector('.newsletter-msg');
  if (existingMsg) existingMsg.remove();

  function showMsg(text, isSuccess) {
    const bg = isSuccess ? '#16A34A' : '#EF4444';
    form.insertAdjacentHTML('afterend',
      '<div class="newsletter-msg" style="margin-top:12px; padding:12px 16px; background:'+bg+'; color:#fff; border-radius:10px; font-size:14px; max-width:500px; margin-left:auto; margin-right:auto;">'+text+'</div>'
    );
  }

  try {
    const res = await fetch('<?= url('api/newsletter.php') ?>', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'email=' + encodeURIComponent(email)
    });
    let data;
    try {
      data = await res.json();
    } catch(jsonErr) {
      // JSON parse hatası - sunucu HTML hata dönmüş olabilir
      showMsg('Sunucu yanıt vermedi. Lütfen daha sonra tekrar deneyin.', false);
      btn.textContent = oldText;
      btn.disabled = false;
      return;
    }

    if (data.success) {
      form.innerHTML = '<div style="padding:14px 16px; background:#16A34A; color:#fff; border-radius:10px; width:100%; text-align:center; font-weight:600;">✓ ' + (data.message || 'Aboneliğiniz alındı!') + '</div>';
    } else {
      showMsg(data.message || 'Bir hata oluştu. Lütfen tekrar deneyin.', false);
      btn.textContent = oldText;
      btn.disabled = false;
    }
  } catch(err) {
    showMsg('Bağlantı hatası. İnternet bağlantınızı kontrol edip tekrar deneyin.', false);
    btn.textContent = oldText;
    btn.disabled = false;
  }
});

// Dropdown dışı tıklama
document.addEventListener('click', function(e) {
  if (!e.target.closest('.lang-dropdown')) {
    document.querySelectorAll('.lang-menu.open').forEach(el => el.classList.remove('open'));
  }
});
</script>

<!-- MOBİL ALT SABİT İLETİŞİM ÇUBUĞU -->
<?php
$phoneRaw = setting('site_phone', '0850 500 0 777');
$phoneDigits = preg_replace('/\D+/', '', $phoneRaw);
// Türkiye için WhatsApp: 905XXX formatı
$waPhone = $phoneDigits;
if (strlen($waPhone) === 11 && str_starts_with($waPhone, '0')) {
  $waPhone = '9' . $waPhone; // 0XXX -> 90XXX
} elseif (strlen($waPhone) === 10) {
  $waPhone = '90' . $waPhone;
}
?>
<div class="mobile-contact-bar">
  <a href="tel:<?= e($phoneDigits) ?>" class="mcb-phone">
    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
    </svg>
    <div class="mcb-phone-text">
      <span class="mcb-label">HEMEN ARA</span>
      <span class="mcb-number"><?= e($phoneRaw) ?></span>
    </div>
  </a>
  <a href="https://wa.me/<?= e($waPhone) ?>" class="mcb-wa" target="_blank" rel="noopener" aria-label="WhatsApp ile yazın">
    <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor">
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
    </svg>
  </a>
</div>

<!-- KVKK Modal (tüm sayfalarda kullanılır) -->
<div class="kvkk-modal-backdrop" id="kvkkModal" role="dialog" aria-modal="true">
  <div class="kvkk-modal">
    <div class="kvkk-modal-header">
      <h3 class="kvkk-modal-title" id="kvkkModalTitle">Yükleniyor...</h3>
      <button type="button" class="kvkk-modal-close" id="kvkkModalClose" aria-label="Kapat">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
    <div class="kvkk-modal-body" id="kvkkModalBody">
      <div class="kvkk-loading">İçerik yükleniyor...</div>
    </div>
    <div class="kvkk-modal-footer">
      <button type="button" class="kvkk-modal-btn" id="kvkkModalOk">Anladım</button>
    </div>
  </div>
</div>

<script>
// KVKK / Kiralama Şartları / Benzeri sayfaları popup olarak aç
(function(){
  const modal = document.getElementById('kvkkModal');
  if (!modal) return;
  const body = document.getElementById('kvkkModalBody');
  const title = document.getElementById('kvkkModalTitle');
  const closeBtn = document.getElementById('kvkkModalClose');
  const okBtn = document.getElementById('kvkkModalOk');

  // Modal'da açılacak sayfalar (slug → başlık)
  const MODAL_PAGES = {
    '/kvkk': 'KVKK Aydınlatma Metni',
    '/kiralama-sartlari': 'Kiralama Şartları',
    '/gizlilik': 'Gizlilik Politikası',
    '/kullanim-kosullari': 'Kullanım Koşulları',
    '/cerez-politikasi': 'Çerez Politikası'
  };

  function openModal(url, modalTitle) {
    title.textContent = modalTitle || 'Yükleniyor...';
    body.innerHTML = '<div class="kvkk-loading">İçerik yükleniyor...</div>';
    modal.classList.add('open');
    document.body.classList.add('kvkk-modal-open');

    // Sayfayı fetch et, içeriğini al
    fetch(url, { credentials: 'same-origin' })
      .then(r => r.text())
      .then(html => {
        // HTML parse et, sadece içerik bölümünü al
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        // page-content, main, article gibi ana içerik container'larını dene
        let content = doc.querySelector('.page-content, .page-body, main article, main .container, main');
        if (!content) {
          // Fallback: body içinden header/footer çıkar
          content = doc.body.cloneNode(true);
          const h = content.querySelector('header.header, .site-header, nav');
          if (h) h.remove();
          const f = content.querySelector('footer, .site-footer, .mobile-contact-bar');
          if (f) f.remove();
        }
        body.innerHTML = content ? content.innerHTML : '<p>İçerik yüklenemedi. <a href="' + url + '">Yeni sekmede aç</a></p>';
      })
      .catch(err => {
        body.innerHTML = '<p>İçerik yüklenirken hata oluştu. <a href="' + url + '" target="_blank">Yeni sekmede aç</a></p>';
      });
  }

  function closeModal() {
    modal.classList.remove('open');
    document.body.classList.remove('kvkk-modal-open');
  }

  // Tüm sayfadaki KVKK/Kiralama Şartları link'lerine event bağla
  document.addEventListener('click', function(e) {
    const link = e.target.closest('a');
    if (!link) return;
    const href = link.getAttribute('href');
    if (!href) return;
    // URL'den path'i çıkar
    let path = href;
    try {
      if (href.startsWith('http')) {
        const u = new URL(href);
        path = u.pathname;
      } else if (href.startsWith('/')) {
        path = href.split('?')[0].split('#')[0];
      }
    } catch(err) {}
    // Trailing slash temizle
    path = path.replace(/\/$/, '');

    if (MODAL_PAGES[path]) {
      e.preventDefault();
      openModal(href, MODAL_PAGES[path]);
    }
  });

  closeBtn.addEventListener('click', closeModal);
  okBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', function(e) {
    if (e.target === modal) closeModal();
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
  });
})();
</script>

</body>
</html>
