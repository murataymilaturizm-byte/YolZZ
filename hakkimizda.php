<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Hakkımızda — Türkiye\'nin Güvenilir Araç Kiralama Platformu | Yolzz';
$pageDescription = '2024\'te kurulan Yolzz, Türkiye\'nin 81 ilinde 100+ ofis ve 5000+ araç ile hizmet veren bağımsız araç kiralama karşılaştırma platformudur. Güvenli, şeffaf, hesaplı.';
$pageKeywords = 'yolzz hakkında, araç kiralama firması, türkiye rent a car, güvenilir rent a car, yolzz tarihçe, rent a car platformu';
$currentPage = '/hakkimizda';

// AboutPage + Breadcrumb schema
$pageJsonLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'AboutPage',
    'name' => 'Hakkımızda — Yolzz',
    'description' => $pageDescription,
    'mainEntity' => [
        '@type' => 'Organization',
        'name' => 'Yolzz',
        'url' => SITE_URL,
        'logo' => url('assets/img/logo-full.svg'),
        'foundingDate' => '2024',
        'areaServed' => ['@type' => 'Country', 'name' => 'Türkiye'],
        'description' => 'Türkiye\'nin araç kiralama karşılaştırma platformu'
    ],
    'breadcrumb' => [
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => SITE_URL],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Hakkımızda', 'item' => url('hakkimizda')]
        ]
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Sistem sayfası içeriği
$pageContent = db()->fetch("SELECT * FROM pages WHERE slug = 'hakkimizda' AND status = 'published'");

$stats = [
    'vehicles' => db()->fetchColumn("SELECT COUNT(*) FROM vehicles WHERE status = 'active'") ?: 5000,
    'offices' => db()->fetchColumn("SELECT COUNT(*) FROM offices WHERE is_active = 1") ?: 110,
    'cities' => db()->fetchColumn("SELECT COUNT(DISTINCT city_id) FROM offices WHERE is_active = 1") ?: 81,
    'customers' => db()->fetchColumn("SELECT COUNT(*) FROM customers") ?: 50000
];

include __DIR__ . '/includes/frontend/header.php';
?>

<section class="page-hero" style="padding:80px 0; background:linear-gradient(135deg, var(--brand), var(--brand-darker)); color:#fff;">
  <div class="container" style="max-width:900px; margin:0 auto; padding:0 20px; text-align:center;">
    <h1 style="font-size:44px; margin-bottom:16px;">Hakkımızda</h1>
    <p style="font-size:18px; opacity:0.9; line-height:1.6;">Türkiye'nin araç kiralama yolculuğunu kolaylaştırıyoruz.</p>
  </div>
</section>

<section style="padding:60px 0;">
  <div class="container" style="max-width:900px; margin:0 auto; padding:0 20px;">
    <?php if ($pageContent && $pageContent['content_tr']): ?>
      <div class="blog-content"><?= $pageContent['content_tr'] ?></div>
    <?php else: ?>
      <div class="blog-content">
        <h2>Yolculuğumuz</h2>
        <p>Yolzz olarak, Türkiye'nin dört bir yanında uygun fiyatlı, güvenilir ve kolay erişilebilir araç kiralama hizmeti sunuyoruz. İster iş, ister tatil, ister kısa bir kaçamak için; her yolculukta yanınızdayız.</p>

        <h2>Misyonumuz</h2>
        <p>Araç kiralamayı basit ve şeffaf hale getirmek. Sürpriz ücretler olmadan, açık fiyatlarla ve 7/24 destek ile her müşteriye en iyi deneyimi yaşatmak.</p>

        <h2>Vizyonumuz</h2>
        <p>Türkiye'nin en güvenilir araç kiralama markası olmak ve müşteri memnuniyeti standardını her geçen gün yükseltmek.</p>

        <h2>Neden Yolzz?</h2>
        <ul>
          <li><strong>Geniş Filo:</strong> Ekonomikten lükse, SUV'dan minivana her ihtiyaca uygun araç seçeneği.</li>
          <li><strong>Türkiye Kapsamı:</strong> 81 şehirde, havalimanlarında ve şehir merkezlerinde ofislerimiz.</li>
          <li><strong>Şeffaf Fiyatlandırma:</strong> Gördüğünüz fiyat, ödediğiniz fiyat.</li>
          <li><strong>7/24 Müşteri Desteği:</strong> Yol boyunca yanınızdayız.</li>
          <li><strong>Güvenli Ödeme:</strong> 256-bit SSL şifreli ödeme altyapısı.</li>
        </ul>
      </div>
    <?php endif ?>
  </div>
</section>

<!-- İSTATİSTİKLER -->
<section style="background:var(--bg-soft); padding:60px 0;">
  <div class="container" style="max-width:1200px; margin:0 auto; padding:0 20px;">
    <div class="stats-row">
      <div class="stat-item">
        <div class="si-value"><?= number_format($stats['vehicles']) ?>+</div>
        <div class="si-label">Araç</div>
      </div>
      <div class="stat-item">
        <div class="si-value"><?= (int)$stats['offices'] ?>+</div>
        <div class="si-label">Ofis</div>
      </div>
      <div class="stat-item">
        <div class="si-value"><?= (int)$stats['cities'] ?></div>
        <div class="si-label">Şehir</div>
      </div>
      <div class="stat-item">
        <div class="si-value"><?= number_format($stats['customers']) ?>+</div>
        <div class="si-label">Mutlu Müşteri</div>
      </div>
    </div>
  </div>
</section>

<section style="padding:60px 0;">
  <div class="container" style="max-width:900px; margin:0 auto; padding:0 20px; text-align:center;">
    <h2 style="font-size:30px; color:var(--ink); margin-bottom:16px;">Yolculuğa Hazır mısınız?</h2>
    <p style="color:var(--text); margin-bottom:24px;">Hemen bir araç seçin, maceranıza başlayın.</p>
    <a href="<?= url('filo') ?>" class="btn-primary-lg" style="display:inline-block; padding:14px 30px; background:var(--accent); color:#fff; border-radius:12px; text-decoration:none; font-weight:700; box-shadow:var(--shadow-accent);">Araç Ara →</a>
  </div>
</section>

<style>
.blog-content { font-size: 16px; line-height: 1.8; color: var(--text); }
.blog-content h2 { font-size: 26px; color: var(--ink); margin: 30px 0 14px; }
.blog-content p, .blog-content ul, .blog-content ol { margin-bottom: 18px; }
.blog-content ul { padding-left: 24px; }
.blog-content li { margin-bottom: 8px; }
.stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px,1fr)); gap: 20px; }
.stat-item { text-align: center; padding: 24px; background: #fff; border-radius: 16px; border: 1px solid var(--border); }
.si-value { font-size: 36px; font-weight: 800; color: var(--brand); line-height: 1; margin-bottom: 8px; }
.si-label { font-size: 13px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
</style>

<?php include __DIR__ . '/includes/frontend/footer.php'; ?>
