<?php
/**
 * ============================================================
 * Admin Panel — Ortak Header & Sidebar
 * ============================================================
 * Tüm admin sayfaları bu dosyayı include eder.
 * Auth kontrolü, header render, sidebar menü.
 * ============================================================
 */

if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}

require_auth();

$current_user = current_user();
$page_title = $page_title ?? 'Panel';
$active_menu = $active_menu ?? '';

// Okunmamış mesaj ve bekleyen rezervasyon sayıları (header badge için)
$pending_bookings = db()->fetchColumn("SELECT COUNT(*) FROM bookings WHERE status = 'pending'") ?: 0;
$unread_messages = db()->fetchColumn("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'") ?: 0;
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title) ?> — Yolzz Admin</title>
<link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
<link rel="alternate icon" href="<?= url('assets/img/favicon.ico') ?>">
<meta name="robots" content="noindex, nofollow">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= ADMIN_URL ?>/assets/css/admin.css?v=2">
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-head">
    <a href="<?= admin_url('index.php') ?>" class="sidebar-logo">
      <svg class="sidebar-logo-svg" viewBox="0 0 260 48" xmlns="http://www.w3.org/2000/svg" aria-label="Yolzz">
        <text x="0" y="36" font-family="Poppins, sans-serif" font-weight="800" font-size="34" letter-spacing="-0.8" fill="#fff">Rental</text>
        <circle cx="15" cy="29" r="2.6" fill="#e94e1b"/>
        <text x="130" y="36" font-family="Poppins, sans-serif" font-weight="800" font-size="34" letter-spacing="-0.8" fill="#e94e1b">carzz</text>
      </svg>
    </a>
    <button class="sidebar-close" onclick="toggleSidebar()">✕</button>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-group-title">Genel</div>
    <a href="<?= admin_url('index.php') ?>" class="nav-item <?= $active_menu === 'dashboard' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Dashboard
    </a>

    <div class="nav-group-title">Kiralama</div>
    <a href="<?= admin_url('modules/vehicles.php') ?>" class="nav-item <?= $active_menu === 'vehicles' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      Araçlar
    </a>
    <a href="<?= admin_url('modules/rental-companies.php') ?>" class="nav-item <?= $active_menu === 'rental-companies' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
      Firmalar
    </a>
    <a href="<?= admin_url('modules/bookings.php') ?>" class="nav-item <?= $active_menu === 'bookings' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      Rezervasyonlar
      <?php if ($pending_bookings > 0): ?>
        <span class="nav-badge"><?= $pending_bookings ?></span>
      <?php endif ?>
    </a>
    <a href="<?= admin_url('modules/offices.php') ?>" class="nav-item <?= $active_menu === 'offices' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
      Ofisler
    </a>
    <a href="<?= admin_url('modules/locations.php') ?>" class="nav-item <?= $active_menu === 'locations' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      Lokasyonlar (SEO)
    </a>

    <div class="nav-group-title">Kullanıcılar</div>
    <a href="<?= admin_url('modules/customers.php') ?>" class="nav-item <?= $active_menu === 'customers' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
      Müşteriler
    </a>
    <a href="<?= admin_url('modules/dealers.php') ?>" class="nav-item <?= $active_menu === 'dealers' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
      Bayiler
    </a>
    <?php if (has_role('super_admin')): ?>
    <a href="<?= admin_url('modules/admin-users.php') ?>" class="nav-item <?= $active_menu === 'admin-users' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
      Admin Kullanıcılar
    </a>
    <?php endif ?>

    <div class="nav-group-title">Entegrasyonlar</div>
    <a href="<?= admin_url('modules/api-providers.php') ?>" class="nav-item <?= $active_menu === 'api-providers' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      API Sağlayıcıları
    </a>

    <div class="nav-group-title">İçerik</div>
    <a href="<?= admin_url('modules/blog.php') ?>" class="nav-item <?= $active_menu === 'blog' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2M5 8h4m-4 4h4"/></svg>
      Blog Yazıları
    </a>
    <a href="<?= admin_url('modules/campaigns.php') ?>" class="nav-item <?= $active_menu === 'campaigns' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
      Kampanyalar
    </a>
    <a href="<?= admin_url('modules/pages.php') ?>" class="nav-item <?= $active_menu === 'pages' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Sayfalar
    </a>
    <a href="<?= admin_url('modules/faqs.php') ?>" class="nav-item <?= $active_menu === 'faqs' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      SSS
    </a>
    <a href="<?= admin_url('modules/menus.php') ?>" class="nav-item <?= $active_menu === 'menus' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
      Menü Yönetimi
    </a>

    <div class="nav-group-title">Diğer</div>
    <a href="<?= admin_url('modules/messages.php') ?>" class="nav-item <?= $active_menu === 'messages' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
      Mesajlar
      <?php if ($unread_messages > 0): ?>
        <span class="nav-badge"><?= $unread_messages ?></span>
      <?php endif ?>
    </a>
    <a href="<?= admin_url('modules/newsletter.php') ?>" class="nav-item <?= $active_menu === 'newsletter' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
      Bülten Aboneleri
    </a>
    <?php if (has_role(['super_admin','admin'])): ?>
    <a href="<?= admin_url('modules/settings.php') ?>" class="nav-item <?= $active_menu === 'settings' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      Ayarlar
    </a>
    <?php endif ?>
    <a href="<?= admin_url('modules/activity-logs.php') ?>" class="nav-item <?= $active_menu === 'activity-logs' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      İşlem Logları
    </a>
  </nav>
</aside>

<!-- MAIN AREA -->
<div class="main-area">
  <!-- TOP HEADER -->
  <header class="topbar">
    <button class="sidebar-toggle" onclick="toggleSidebar()">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>

    <h1 class="topbar-title"><?= e($page_title) ?></h1>

    <div class="topbar-right">
      <a href="<?= SITE_URL ?>" target="_blank" class="topbar-btn" title="Site'yi görüntüle">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
      </a>

      <div class="user-menu">
        <button class="user-btn" onclick="toggleUserMenu()">
          <div class="user-avatar"><?= strtoupper(mb_substr($current_user['name'], 0, 1)) ?></div>
          <div class="user-info">
            <div class="user-name"><?= e($current_user['name']) ?></div>
            <div class="user-role"><?= e(ucfirst(str_replace('_', ' ', $current_user['role']))) ?></div>
          </div>
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div class="user-dropdown" id="userDropdown">
          <a href="<?= admin_url('profile.php') ?>">👤 Profilim</a>
          <?php if (has_role(['super_admin','admin'])): ?>
            <a href="<?= admin_url('modules/settings.php') ?>">⚙ Ayarlar</a>
          <?php endif ?>
          <div class="dropdown-divider"></div>
          <a href="<?= admin_url('logout.php') ?>" class="logout">🚪 Çıkış Yap</a>
        </div>
      </div>
    </div>
  </header>

  <!-- CONTENT -->
  <main class="content">
    <?= render_flashes() ?>
