<?php
if (!defined('YOLZZ_APP')) {
    require_once __DIR__ . '/../includes/bootstrap.php';
}

$lang = current_lang();
$siteName = setting('site_name', 'Yolzz');
$pageTitle = $pageTitle ?? $siteName;
$pageDescription = $pageDescription ?? setting('site_description', 'Türkiye\'nin her yerinde uygun fiyatlı araç kiralama. 100+ ofis, binlerce araç, güvenli online rezervasyon.');
$pageKeywords = $pageKeywords ?? setting('site_keywords', 'araç kiralama, rent a car, Türkiye, oto kiralama, rent a car fiyatları');

// Canonical URL
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'yolzz.com') . ($_SERVER['REQUEST_URI'] ?? '/');
$canonicalUrl = $canonicalUrl ?? strtok($currentUrl, '?'); // Query string'i at

// OG image fallback
$pageOgImage = $pageOgImage ?? url('assets/img/og-image.jpg');

// Menüyü yükle
$mainMenu = db()->fetchAll(
    "SELECT * FROM menu_items WHERE menu_location = 'header' AND is_active = 1 AND parent_id IS NULL ORDER BY sort_order, id"
);

$currentPage = $currentPage ?? '';
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<meta name="theme-color" content="#1d71b8">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>">
<meta name="keywords" content="<?= e($pageKeywords) ?>">
<meta name="author" content="Yolzz">
<meta name="robots" content="<?= e($pageRobots ?? 'index, follow') ?>">
<link rel="canonical" href="<?= e($canonicalUrl) ?>">

<!-- Open Graph -->
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDescription) ?>">
<meta property="og:type" content="<?= e($pageOgType ?? 'website') ?>">
<meta property="og:url" content="<?= e($canonicalUrl) ?>">
<meta property="og:image" content="<?= e($pageOgImage) ?>">
<meta property="og:locale" content="tr_TR">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($pageTitle) ?>">
<meta name="twitter:description" content="<?= e($pageDescription) ?>">
<meta name="twitter:image" content="<?= e($pageOgImage) ?>">

<!-- JSON-LD Organization Schema -->
<?php if (empty($pageNoOrgSchema)): ?>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Yolzz",
  "url": "<?= e(SITE_URL) ?>",
  "logo": "<?= e(url('assets/img/logo-large.png')) ?>",
  "description": "Türkiye'nin araç kiralama karşılaştırma platformu",
  "contactPoint": {
    "@type": "ContactPoint",
    "telephone": "<?= e(setting('site_phone', '+90 850 000 00 00')) ?>",
    "contactType": "customer service",
    "areaServed": "TR",
    "availableLanguage": ["Turkish", "English"]
  },
  "sameAs": [
    <?php
      $socials = [];
      foreach (['facebook_url','instagram_url','twitter_url','linkedin_url','youtube_url'] as $s) {
        $v = setting($s);
        if ($v) $socials[] = '"' . e($v) . '"';
      }
      echo implode(",\n    ", $socials);
    ?>
  ]
}
</script>
<?php endif ?>

<?php if (!empty($pageJsonLd)): ?>
<script type="application/ld+json"><?= $pageJsonLd ?></script>
<?php endif ?>

<!-- Poppins self-hosted (main.min.css içinde @font-face) -->
<link rel="preload" href="<?= url('assets/fonts/woff2/poppins-400.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= url('assets/fonts/woff2/poppins-800.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= url('assets/css/main.min.css') ?>?v=<?= @filemtime(__DIR__ . '/../../assets/css/main.min.css') ?: '1' ?>">

<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="<?= url('favicon.ico') ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= url('assets/img/favicon-32x32.png') ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= url('assets/img/favicon-16x16.png') ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?= url('assets/img/apple-touch-icon.png') ?>">
</head>
<body class="<?= !empty($bodyClass) ? e($bodyClass) : '' ?>">

<!-- Flash mesajları -->
<?php foreach (flash_get_all() as $flash): ?>
<div class="global-flash flash-<?= e($flash['type']) ?>">
  <div class="flash-inner">
    <span><?= e($flash['message']) ?></span>
    <button onclick="this.parentElement.parentElement.remove()" class="flash-close">×</button>
  </div>
</div>
<?php endforeach ?>

<!-- ========== ANNOUNCEMENT ========== -->
<?php if (setting('announcement_enabled', '1') === '1' && setting('announcement_text')): ?>
<div class="annc-bar">
  <?= setting('announcement_text') ?>
</div>
<?php endif ?>

<!-- ========== HEADER ========== -->
<header class="header">
  <div class="header-inner">
    <a href="<?= url('/') ?>" aria-label="Yolzz" class="logo-link">
      <?php $logo = setting('site_logo'); ?>
      <?php if ($logo): ?>
        <img src="<?= upload_url($logo) ?>" alt="Yolzz" class="logo-img">
      <?php else: ?>
        <!-- Yolzz logosu (PNG) -->
        <img src="<?= url('assets/img/logo.png') ?>" alt="Yolzz - Araç Kiralama" class="logo-img" style="height:52px; width:auto; display:block;">
      <?php endif ?>
    </a>

    <nav class="nav-desktop">
      <?php foreach ($mainMenu as $item):
        $itemUrl = $item['url'];
        if (strpos($itemUrl, 'http') !== 0 && strpos($itemUrl, '/') === 0) {
          $itemUrl = url(ltrim($itemUrl, '/'));
        }
        $isActive = ($currentPage === $item['url'] || $currentPage === trim($item['url'], '/'));
      ?>
        <a href="<?= e($itemUrl) ?>" class="<?= $isActive ? 'active' : '' ?>" target="<?= e($item['target']) ?>">
          <?= e(lang_field_value($item, 'title')) ?>
        </a>
      <?php endforeach ?>
    </nav>

    <div class="head-actions">
      <?php $phone = setting('site_phone', '0850 500 0 777'); ?>
      <a href="tel:<?= preg_replace('/\s+/', '', $phone) ?>" class="phone-cta">
        <svg fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
        <?= e($phone) ?>
      </a>
      <div class="lang-dropdown" style="position:relative;">
        <button class="lang-btn" onclick="this.nextElementSibling.classList.toggle('open')">
          <?= strtoupper(e($lang)) ?>
          <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div class="lang-menu">
          <a href="?lang=tr">🇹🇷 Türkçe</a>
          <a href="?lang=en">🇬🇧 English</a>
        </div>
      </div>
      <button class="hamburger" id="menuBtn" aria-label="Menü" onclick="document.getElementById('mobileNav').classList.add('open')">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<!-- MOBİL NAV -->
<div class="mobile-nav" id="mobileNav">
  <div class="mobile-nav-inner">
    <button class="mobile-nav-close" onclick="document.getElementById('mobileNav').classList.remove('open')">×</button>
    <nav>
      <?php foreach ($mainMenu as $item):
        $itemUrl = $item['url'];
        if (strpos($itemUrl, 'http') !== 0 && strpos($itemUrl, '/') === 0) {
          $itemUrl = url(ltrim($itemUrl, '/'));
        }
      ?>
        <a href="<?= e($itemUrl) ?>"><?= e(lang_field_value($item, 'title')) ?></a>
      <?php endforeach ?>
    </nav>
    <div class="mobile-nav-footer">
      <a href="tel:<?= preg_replace('/\s+/', '', $phone) ?>" class="btn-primary-lg" style="display:block; text-align:center; margin-top:20px;">📞 <?= e($phone) ?></a>
    </div>
  </div>
</div>

<style>
.lang-menu { position: absolute; top: 100%; right: 0; background: #fff; border: 1px solid var(--border); border-radius: 8px; padding: 4px; min-width: 140px; margin-top: 4px; box-shadow: var(--shadow); display: none; z-index: 100; }
.lang-menu.open { display: block; }
.lang-menu a { display: block; padding: 8px 12px; font-size: 13px; color: var(--ink); text-decoration: none; border-radius: 6px; }
.lang-menu a:hover { background: var(--bg-soft); }
.mobile-nav { position: fixed; inset: 0; background: rgba(10,31,51,0.4); z-index: 999; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
.mobile-nav.open { opacity: 1; pointer-events: auto; }
.mobile-nav-inner { width: 85%; max-width: 360px; height: 100%; background: #fff; margin-left: auto; padding: 24px; overflow-y: auto; transform: translateX(100%); transition: transform 0.3s; }
.mobile-nav.open .mobile-nav-inner { transform: translateX(0); }
.mobile-nav-close { float: right; font-size: 28px; background: none; border: 0; cursor: pointer; color: var(--ink); }
.mobile-nav nav { margin-top: 40px; }
.mobile-nav nav a { display: block; padding: 14px 0; font-size: 16px; color: var(--ink); text-decoration: none; font-weight: 500; border-bottom: 1px solid var(--border-soft); }
.global-flash { position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px; animation: slideIn 0.3s; }
@keyframes slideIn { from { transform: translateX(400px); } to { transform: translateX(0); } }
.flash-inner { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-radius: 10px; box-shadow: var(--shadow-lg); font-size: 14px; color: #fff; }
.flash-success .flash-inner { background: #16A34A; }
.flash-error .flash-inner { background: #EF4444; }
.flash-warning .flash-inner { background: #F59E0B; }
.flash-info .flash-inner { background: #1d71b8; }
.flash-close { background: none; border: 0; color: #fff; font-size: 24px; cursor: pointer; margin-left: auto; line-height: 1; }
</style>
