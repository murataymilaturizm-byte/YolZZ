<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/includes/bootstrap.php';

$currentPage = '/';
$pageTitle = 'Yolzz — Türkiye\'nin Araç Kiralama Karşılaştırma Platformu';
$pageDescription = '100+ şehir, binlerce araç ile Türkiye\'nin her yerinde uygun fiyatlı araç kiralama. Havalimanı teslim, 7/24 destek, güvenli online rezervasyon.';
$pageKeywords = 'araç kiralama, rent a car, oto kiralama, Türkiye rent a car, İstanbul araç kiralama, havalimanı araç kiralama, günlük kiralık araç, Yolzz';
$pageOgType = 'website';

// Schema.org WebSite + SearchAction (arama motorları site içi arama gösterebilir)
$pageJsonLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => 'Yolzz',
    'url' => SITE_URL,
    'description' => $pageDescription,
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => SITE_URL . '/filo?pickup_location={search_term_string}',
        'query-input' => 'required name=search_term_string'
    ],
    'inLanguage' => 'tr-TR'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Öne çıkan kampanyalar
$featuredCampaigns = db()->fetchAll("
    SELECT * FROM campaigns
    WHERE status = 'active' AND is_featured = 1
    AND (start_date IS NULL OR start_date <= CURDATE())
    AND (end_date IS NULL OR end_date >= CURDATE())
    ORDER BY created_at DESC LIMIT 3
");

// Öne çıkan blog yazıları
$recentPosts = db()->fetchAll("
    SELECT p.*, bc.name_tr AS category_name
    FROM blog_posts p
    LEFT JOIN blog_categories bc ON bc.id = p.category_id
    WHERE p.status = 'published'
    ORDER BY p.published_at DESC, p.created_at DESC LIMIT 3
");

// Şehirler (arama dropdown için)
$cities = db()->fetchAll("SELECT DISTINCT c.* FROM cities c
    JOIN offices o ON o.city_id = c.id AND o.is_active = 1
    WHERE c.is_active = 1 ORDER BY c.name");

// TÜM LOKASYONLAR - searchable dropdown için (sadece ofisler - show_in_search=1)
$allLocations = [];
// Ofisler (havalimanları önce, sonra normal ofisler)
$allOfficesForDropdown = db()->fetchAll("SELECT o.id, o.name, o.is_airport, c.name AS city_name
    FROM offices o JOIN cities c ON c.id = o.city_id
    WHERE o.is_active = 1 AND o.show_in_search = 1
    ORDER BY o.is_airport DESC, c.name, o.name");
foreach ($allOfficesForDropdown as $o) {
    $allLocations[] = [
        'type' => 'office',
        'id' => 'office-' . $o['id'],
        'value' => 'office:' . $o['id'],
        'label' => $o['city_name'] . ' - ' . $o['name'],
        'sub' => $o['is_airport'] ? 'Havalimanı' : 'Ofis',
        'icon' => $o['is_airport'] ? '✈' : '📍'
    ];
}

// Öne çıkan lokasyonlar (yeni locations tablosu — SEO için)
$featuredLocations = db()->fetchAll("
    SELECT * FROM locations
    WHERE is_active = 1 AND is_featured = 1
    ORDER BY sort_order LIMIT 4
");
// Eğer locations tablosu boşsa veya yoksa, eski offices verisini fallback olarak kullan
if (empty($featuredLocations)) {
    $featuredLocations = db()->fetchAll("
        SELECT o.id, o.name AS name_tr, o.name AS name_en, o.slug, o.image,
               o.is_airport, o.city_id,
               c.name AS city_name,
               CASE WHEN o.is_airport = 1 THEN 'airport' ELSE 'city' END AS type
        FROM offices o
        JOIN cities c ON c.id = o.city_id
        WHERE o.is_active = 1
        ORDER BY o.is_airport DESC, o.sort_order LIMIT 4
    ");
}
// Eski sorgu (uyumluluk için - ofislerimiz bölümü varsa)
$featuredOffices = db()->fetchAll("
    SELECT o.*, c.name AS city_name FROM offices o
    JOIN cities c ON c.id = o.city_id
    WHERE o.is_active = 1
    ORDER BY o.is_airport DESC, o.sort_order LIMIT 4
");

// İstatistikler
$stats = [
    'vehicles' => db()->fetchColumn("SELECT COUNT(*) FROM vehicles WHERE status = 'active'") ?: 5000,
    'offices' => db()->fetchColumn("SELECT COUNT(*) FROM offices WHERE is_active = 1") ?: 110,
    'cities' => db()->fetchColumn("SELECT COUNT(DISTINCT city_id) FROM offices WHERE is_active = 1") ?: 81,
    'customers' => db()->fetchColumn("SELECT COUNT(*) FROM customers") ?: 50000
];

include __DIR__ . '/includes/frontend/header.php';
?>

<!-- ========== HERO ========== -->
<section class="hero-pro">
  <div class="hero-pro-bg"></div>
  <div class="hero-pro-inner">
    <div class="hero-pro-copy">
      <div class="hero-pro-badge hide-mobile"><span>✨</span> <?= e(setting('hero_badge_text', "Türkiye'nin Lider Araç Kiralama Platformu")) ?></div>
      <h1 class="hero-pro-title"><?= e(setting('hero_title', "Ara, Karşılaştır,")) ?><br><span class="accent-text"><?= e(setting('hero_title_accent', "Kirala")) ?></span></h1>
      <p class="hero-pro-sub hide-mobile"><?= e(setting('hero_subtitle', "110+ ofis, 5000+ araç. Size özel fiyatlarla, güvenle, dakikalar içinde rezervasyon.")) ?></p>

      <div class="hero-trust-row hide-mobile">
        <span class="htr-item"><strong><?= number_format($stats['vehicles']) ?>+</strong> Araç</span>
        <span class="htr-item"><strong><?= (int)$stats['offices'] ?>+</strong> Ofis</span>
        <span class="htr-item"><strong><?= (int)$stats['cities'] ?></strong> Şehir</span>
        <span class="htr-item"><strong>4.8</strong>/5 ⭐</span>
      </div>
    </div>

    <!-- ARAMA FORMU (PROFESYONEL) -->
    <form class="search-pro" action="<?= url('filo') ?>" method="GET" id="searchForm">
      <div class="search-pro-header">
        <div class="sph-title">Aracınızı bulun</div>
        <label class="sph-toggle">
          <input type="checkbox" name="different_return" id="diffReturn" value="1">
          <span class="sph-toggle-slider"></span>
          <span class="sph-toggle-label">Farklı yerde iade et</span>
        </label>
      </div>

      <!-- Alış Lokasyonu (Searchable) -->
      <div class="sp-field sp-field-location">
        <label class="sp-label">📍 Alış Lokasyonu</label>
        <div class="searchable-dropdown" data-target="pickup_location">
          <input type="text" class="sd-input" placeholder="Şehir, havalimanı veya ofis ara..." autocomplete="off" id="pickupInput">
          <input type="hidden" name="pickup_location" id="pickupHidden" required>
          <div class="sd-list" id="pickupList"></div>
        </div>
      </div>

      <!-- İade Lokasyonu (konditional, toggle ile açılır) -->
      <div class="sp-field sp-field-location" id="returnLocationField" style="display:none;">
        <label class="sp-label">📍 İade Lokasyonu</label>
        <div class="searchable-dropdown" data-target="return_location">
          <input type="text" class="sd-input" placeholder="İade yerini seçin..." autocomplete="off" id="returnInput">
          <input type="hidden" name="return_location" id="returnHidden">
          <div class="sd-list" id="returnList"></div>
        </div>
      </div>

      <!-- Tarihler - Alış Grubu -->
      <div class="sp-datetime-group">
        <label class="sp-label">📅 Alış Zamanı</label>
        <div class="sp-datetime-row">
          <div class="sp-date-wrap">
            <input type="text" class="sp-date-input sp-date-picker" id="pickupDateInput" data-role="pickup" readonly placeholder="Tarih seçin" value="<?= date('d.m.Y', strtotime('+1 day')) ?>" required>
            <input type="hidden" name="pickup_date" id="pickupDateHidden" value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
          </div>
          <select class="sp-time-input" name="pickup_time" required>
            <?php for ($h = 8; $h <= 22; $h++): ?>
              <option value="<?= sprintf('%02d:00', $h) ?>" <?= $h == 10 ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
              <option value="<?= sprintf('%02d:30', $h) ?>"><?= sprintf('%02d:30', $h) ?></option>
            <?php endfor ?>
          </select>
        </div>
      </div>

      <!-- Tarihler - İade Grubu -->
      <div class="sp-datetime-group">
        <label class="sp-label">📅 İade Zamanı</label>
        <div class="sp-datetime-row">
          <div class="sp-date-wrap">
            <input type="text" class="sp-date-input sp-date-picker" id="returnDateInput" data-role="return" readonly placeholder="Tarih seçin" value="<?= date('d.m.Y', strtotime('+4 days')) ?>" required>
            <input type="hidden" name="return_date" id="returnDateHidden" value="<?= date('Y-m-d', strtotime('+4 days')) ?>">
          </div>
          <select class="sp-time-input" name="return_time" required>
            <?php for ($h = 8; $h <= 22; $h++): ?>
              <option value="<?= sprintf('%02d:00', $h) ?>" <?= $h == 10 ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
              <option value="<?= sprintf('%02d:30', $h) ?>"><?= sprintf('%02d:30', $h) ?></option>
            <?php endfor ?>
          </select>
        </div>
      </div>

      <!-- RANGE DATE PICKER (popup) -->
      <div class="rdp-backdrop" id="rdpBackdrop"></div>
      <div class="rdp" id="rdpPopup" role="dialog" aria-modal="true" aria-label="Tarih Aralığı Seçin">
        <div class="rdp-header">
          <div class="rdp-title">
            <div class="rdp-title-main">Tarih Aralığı Seçin</div>
            <div class="rdp-title-sub" id="rdpSelInfo">Alış ve iade tarihlerini seçin</div>
          </div>
          <button type="button" class="rdp-close" aria-label="Kapat">✕</button>
        </div>
        <div class="rdp-nav rdp-nav-mobile-bar">
          <button type="button" class="rdp-nav-btn rdp-nav-btn-big" id="rdpPrev" aria-label="Önceki ay">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <div class="rdp-current-month" id="rdpCurrentMonth"></div>
          <button type="button" class="rdp-nav-btn rdp-nav-btn-big" id="rdpNext" aria-label="Sonraki ay">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
        <div class="rdp-months" id="rdpMonths"></div>
        <div class="rdp-footer">
          <button type="button" class="rdp-clear" id="rdpClear">Temizle</button>
          <button type="button" class="rdp-apply" id="rdpApply">Tamam</button>
        </div>
      </div>

      <!-- TIME PICKER (saat seçici — masaüstü modal / mobil bottom sheet) -->
      <div class="tp-backdrop" id="tpBackdrop"></div>
      <div class="tp-popup" id="tpPopup" role="dialog" aria-modal="true" aria-label="Saat Seçin">
        <div class="tp-header">
          <div class="tp-title-wrap">
            <div class="tp-title-main" id="tpTitle">Aracınızı saat kaçta almak istiyorsunuz?</div>
          </div>
          <button type="button" class="tp-close" id="tpClose" aria-label="Kapat">✕</button>
        </div>
        <div class="tp-body">
          <div class="tp-grid" id="tpGrid"></div>
        </div>
      </div>

      <button type="submit" class="sp-submit">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        Araçları Göster
      </button>
    </form>
  </div>
</section>

<script>
// Searchable Dropdown — Alış/İade Lokasyonu
(function(){
  const locations = <?= json_encode($allLocations, JSON_UNESCAPED_UNICODE) ?>;

  function setupDropdown(inputId, hiddenId, listId) {
    const input = document.getElementById(inputId);
    const hidden = document.getElementById(hiddenId);
    const list = document.getElementById(listId);
    if (!input || !hidden || !list) return;

    // TÜRKÇE NORMALİZE — i/İ/ı/I ve diğer aksanları düzleştir
    function normalize(str) {
      if (!str) return '';
      return str
        .replace(/İ/g, 'i').replace(/I/g, 'i').replace(/ı/g, 'i')
        .replace(/Ş/g, 's').replace(/ş/g, 's')
        .replace(/Ğ/g, 'g').replace(/ğ/g, 'g')
        .replace(/Ü/g, 'u').replace(/ü/g, 'u')
        .replace(/Ö/g, 'o').replace(/ö/g, 'o')
        .replace(/Ç/g, 'c').replace(/ç/g, 'c')
        .toLowerCase()
        .trim();
    }

    function render(query) {
      const q = normalize(query);

      if (!q) {
        // Boş arama: Havalimanları + Şehir Ofisleri (şehir filtresi yok)
        const airports = locations.filter(l => l.sub === 'Havalimanı');
        const offices = locations.filter(l => l.sub === 'Ofis');

        let html = '';
        if (airports.length) {
          html += '<div class="sd-group-header">✈ HAVALİMANLARI</div>';
          html += airports.map(l => itemHtml(l)).join('');
        }
        if (offices.length) {
          html += '<div class="sd-group-header">📍 ŞEHİR OFİSLERİ</div>';
          html += offices.map(l => itemHtml(l)).join('');
        }
        list.innerHTML = html || '<div class="sd-empty">Henüz ofis tanımlı değil</div>';
      } else {
        // Arama: başlıkta eşleşenler + içinde eşleşenler sırayla (Türkçe normalize ile)
        const starts = locations.filter(l => normalize(l.label).startsWith(q));
        const contains = locations.filter(l =>
          !normalize(l.label).startsWith(q) &&
          (normalize(l.label).includes(q) || normalize(l.sub).includes(q))
        );
        const combined = [...starts, ...contains].slice(0, 30);

        if (combined.length === 0) {
          list.innerHTML = '<div class="sd-empty">Sonuç bulunamadı</div>';
        } else {
          list.innerHTML = combined.map(l => itemHtml(l)).join('');
        }
      }

      function itemHtml(l) {
        const matchedLabel = q ? highlightMatch(l.label, query) : l.label;
        return `<div class="sd-item" data-value="${l.value}" data-label="${l.label}">
          <span class="sd-icon">${l.icon}</span>
          <div class="sd-text">
            <div class="sd-label">${matchedLabel}</div>
            <div class="sd-sub">${l.sub}</div>
          </div>
        </div>`;
      }

      function highlightMatch(text, queryRaw) {
        const queryNorm = normalize(queryRaw);
        const textNorm = normalize(text);
        const idx = textNorm.indexOf(queryNorm);
        if (idx === -1) return text;
        // Orijinal stringde aynı pozisyonu vurgula (Türkçe karakter uzunluğu aynı)
        return text.substring(0, idx) +
               '<strong style="color:#e94e1b;">' + text.substring(idx, idx + queryNorm.length) + '</strong>' +
               text.substring(idx + queryNorm.length);
      }

      list.querySelectorAll('.sd-item').forEach(item => {
        item.addEventListener('mousedown', (e) => {
          e.preventDefault();
          input.value = item.dataset.label;
          hidden.value = item.dataset.value;
          list.classList.remove('open');
          // MOBİL: klavyeyi kapat
          input.blur();
          if (document.activeElement && document.activeElement !== document.body) {
            document.activeElement.blur();
          }
        });
      });
    }

    // Input'a girilince mobilde input-mode text yerine klavye kontrolü
    input.setAttribute('inputmode', 'search');

    // Focus davranışı: mevcut değer varsa tümünü seç (kullanıcı yeni yazarsa silinir)
    let originalValue = input.value;
    let originalHidden = hidden.value;

    input.addEventListener('focus', () => {
      originalValue = input.value;
      originalHidden = hidden.value;
      render(input.value);
      list.classList.add('open');
      setTimeout(() => {
        if (input === document.activeElement && input.value) {
          input.select();
        }
      }, 50);
    });
    input.addEventListener('input', () => {
      render(input.value);
      list.classList.add('open');
      hidden.value = ''; // input değişirse seçim sıfırlansın
    });
    input.addEventListener('blur', () => {
      setTimeout(() => {
        list.classList.remove('open');
        // Eğer yeni seçim yapılmadıysa eski değere geri dön
        if (!hidden.value && originalHidden) {
          input.value = originalValue;
          hidden.value = originalHidden;
        }
      }, 150);
    });
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        input.value = originalValue;
        hidden.value = originalHidden;
        list.classList.remove('open');
        input.blur();
      }
    });
  }

  setupDropdown('pickupInput', 'pickupHidden', 'pickupList');
  setupDropdown('returnInput', 'returnHidden', 'returnList');

  // Farklı yerde iade toggle
  const diffReturn = document.getElementById('diffReturn');
  const returnField = document.getElementById('returnLocationField');
  if (diffReturn && returnField) {
    diffReturn.addEventListener('change', () => {
      returnField.style.display = diffReturn.checked ? 'block' : 'none';
      const returnHidden = document.getElementById('returnHidden');
      if (returnHidden) returnHidden.required = diffReturn.checked;
    });
  }

  // Form submit: hidden lokasyon boşsa uyar + loader göster
  document.getElementById('searchForm')?.addEventListener('submit', (e) => {
    const pickupHidden = document.getElementById('pickupHidden');
    if (!pickupHidden.value) {
      e.preventDefault();
      alert('Lütfen alış lokasyonu seçin.');
      document.getElementById('pickupInput').focus();
      return;
    }
    // Loader'ı göster
    const loader = document.getElementById('homeSearchLoader');
    if (loader) {
      loader.classList.add('active');
      const s1 = document.getElementById('hslStep1');
      const s2 = document.getElementById('hslStep2');
      const s3 = document.getElementById('hslStep3');
      if (s1 && s2 && s3) {
        setTimeout(() => { s1.classList.add('done'); s1.classList.remove('active'); s2.classList.add('active'); }, 700);
        setTimeout(() => { s2.classList.add('done'); s2.classList.remove('active'); s3.classList.add('active'); }, 1400);
      }
    }
  });
})();

// =================== RANGE DATE PICKER ===================
(function(){
  const pickupInput = document.getElementById('pickupDateInput');
  const pickupHidden = document.getElementById('pickupDateHidden');
  const returnInput = document.getElementById('returnDateInput');
  const returnHidden = document.getElementById('returnDateHidden');
  const popup = document.getElementById('rdpPopup');
  const backdrop = document.getElementById('rdpBackdrop');
  const monthsWrap = document.getElementById('rdpMonths');
  const prevBtn = document.getElementById('rdpPrev');
  const nextBtn = document.getElementById('rdpNext');
  const applyBtn = document.getElementById('rdpApply');
  const clearBtn = document.getElementById('rdpClear');
  const closeBtn = popup.querySelector('.rdp-close');
  const selInfo = document.getElementById('rdpSelInfo');

  if (!pickupInput || !popup) return;

  const MONTH_NAMES = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
  const DAY_NAMES = ['Pzt','Sal','Çar','Per','Cum','Cmt','Paz'];

  let startDate = parseDMY(pickupInput.value);
  let endDate = parseDMY(returnInput.value);
  // Saatleri sıfırla (karşılaştırma için) — önemli
  if (startDate) startDate.setHours(0,0,0,0);
  if (endDate) endDate.setHours(0,0,0,0);
  let selecting = 'start'; // 'start' | 'end'
  let viewYear = startDate ? startDate.getFullYear() : new Date().getFullYear();
  let viewMonth = startDate ? startDate.getMonth() : new Date().getMonth();

  function parseDMY(str) {
    if (!str) return null;
    const parts = str.split('.');
    if (parts.length !== 3) return null;
    const d = new Date(parseInt(parts[2]), parseInt(parts[1]) - 1, parseInt(parts[0]));
    d.setHours(0,0,0,0);
    return d;
  }
  function formatDMY(d) {
    if (!d) return '';
    return String(d.getDate()).padStart(2,'0') + '.' + String(d.getMonth()+1).padStart(2,'0') + '.' + d.getFullYear();
  }
  function formatYMD(d) {
    if (!d) return '';
    return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
  }
  function sameDay(a, b) {
    return a && b && a.getFullYear() === b.getFullYear()
           && a.getMonth() === b.getMonth()
           && a.getDate() === b.getDate();
  }
  function stripTime(d) { const x = new Date(d); x.setHours(0,0,0,0); return x; }

  function openPopup(startingWith) {
    selecting = startingWith === 'return' ? 'end' : 'start';
    if (selecting === 'start') {
      selInfo.textContent = 'Alış tarihini seçin';
    } else {
      selInfo.textContent = 'İade tarihini seçin';
    }
    if (startDate) { viewYear = startDate.getFullYear(); viewMonth = startDate.getMonth(); }
    render();
    popup.classList.add('open');
    backdrop.classList.add('open');
    document.body.classList.add('rdp-lock');
  }

  function closePopup() {
    popup.classList.remove('open');
    backdrop.classList.remove('open');
    document.body.classList.remove('rdp-lock');
  }

  function applyRange() {
    if (startDate) {
      pickupInput.value = formatDMY(startDate);
      pickupHidden.value = formatYMD(startDate);
    }
    if (endDate) {
      returnInput.value = formatDMY(endDate);
      returnHidden.value = formatYMD(endDate);
    }
    closePopup();
    // Tarih kapandıktan hemen sonra alış saatini sor (her ekranda)
    if (startDate) {
      setTimeout(() => { if (window.openTimePicker) window.openTimePicker('pickup'); }, 200);
    }
  }

  function renderMonth(year, month) {
    const first = new Date(year, month, 1);
    const last = new Date(year, month + 1, 0);
    let startDay = first.getDay();
    startDay = startDay === 0 ? 6 : startDay - 1; // Pazartesi başlangıç

    let html = '<div class="rdp-month">';
    html += '<div class="rdp-month-title">' + MONTH_NAMES[month] + ' ' + year + '</div>';
    html += '<div class="rdp-weekdays">';
    DAY_NAMES.forEach(d => { html += '<span>' + d + '</span>'; });
    html += '</div>';
    html += '<div class="rdp-days">';
    for (let i = 0; i < startDay; i++) html += '<span class="rdp-day rdp-empty"></span>';

    const today = stripTime(new Date());
    for (let d = 1; d <= last.getDate(); d++) {
      const date = new Date(year, month, d);
      const isPast = date < today;
      const isToday = sameDay(date, today);
      const isStart = sameDay(date, startDate);
      const isEnd = sameDay(date, endDate);
      const isInRange = startDate && endDate && date > startDate && date < endDate;
      const classes = ['rdp-day'];
      if (isPast) classes.push('rdp-past');
      if (isToday) classes.push('rdp-today');
      if (isStart) classes.push('rdp-start');
      if (isEnd) classes.push('rdp-end');
      if (isInRange) classes.push('rdp-in-range');
      html += '<span class="' + classes.join(' ') + '" data-date="' + formatYMD(date) + '">' + d + '</span>';
    }
    html += '</div></div>';
    return html;
  }

  function render() {
    const nextMonth = viewMonth + 1 > 11 ? 0 : viewMonth + 1;
    const nextYear = viewMonth + 1 > 11 ? viewYear + 1 : viewYear;
    const isMobile = window.innerWidth < 720;
    if (isMobile) {
      monthsWrap.innerHTML = renderMonth(viewYear, viewMonth);
    } else {
      monthsWrap.innerHTML = renderMonth(viewYear, viewMonth) + renderMonth(nextYear, nextMonth);
    }
    // Mobil bar: mevcut ay başlığını göster
    const cmEl = document.getElementById('rdpCurrentMonth');
    if (cmEl) {
      if (isMobile) {
        cmEl.textContent = MONTH_NAMES[viewMonth] + ' ' + viewYear;
      } else {
        cmEl.textContent = MONTH_NAMES[viewMonth] + ' ' + viewYear + ' — ' + MONTH_NAMES[nextMonth] + ' ' + nextYear;
      }
    }
    monthsWrap.querySelectorAll('.rdp-day:not(.rdp-past):not(.rdp-empty)').forEach(el => {
      el.addEventListener('click', () => {
        const d = new Date(el.dataset.date);
        onDateClick(d);
      });
    });
  }

  function onDateClick(date) {
    // Saat karşılaştırma sorunlarını önlemek için saati sıfırla
    date = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    if (selecting === 'start') {
      startDate = date;
      endDate = null;
      selecting = 'end';
      selInfo.textContent = 'İade tarihini seçin';
    } else {
      if (date < startDate) {
        // Eğer seçili tarih başlangıçtan önceyse yeniden başla
        startDate = date;
        endDate = null;
        selInfo.textContent = 'İade tarihini seçin';
      } else {
        endDate = date;
        selInfo.textContent = formatDMY(startDate) + ' → ' + formatDMY(endDate);
        // Otomatik uygula (kullanıcı deneyimi)
        setTimeout(() => applyRange(), 250);
      }
    }
    render();
  }

  prevBtn.addEventListener('click', () => {
    if (viewMonth === 0) { viewMonth = 11; viewYear--; } else { viewMonth--; }
    render();
  });
  nextBtn.addEventListener('click', () => {
    if (viewMonth === 11) { viewMonth = 0; viewYear++; } else { viewMonth++; }
    render();
  });
  applyBtn.addEventListener('click', applyRange);
  closeBtn.addEventListener('click', closePopup);
  backdrop.addEventListener('click', closePopup);
  clearBtn.addEventListener('click', () => {
    startDate = null; endDate = null;
    selInfo.textContent = 'Alış tarihini seçin';
    selecting = 'start';
    render();
  });

  pickupInput.addEventListener('click', () => openPopup('pickup'));
  returnInput.addEventListener('click', () => openPopup('return'));

  // ESC kapatma
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && popup.classList.contains('open')) closePopup();
  });

  // Ekran boyutu değişirse yeniden çiz
  window.addEventListener('resize', () => {
    if (popup.classList.contains('open')) render();
  });
})();
</script>

<!-- ========== TIME PICKER (saat seçici) ========== -->
<script>
(function() {
  const tpBackdrop = document.getElementById('tpBackdrop');
  const tpPopup = document.getElementById('tpPopup');
  const tpTitle = document.getElementById('tpTitle');
  const tpClose = document.getElementById('tpClose');
  const grid = document.getElementById('tpGrid');
  const pickupTimeSelect = document.querySelector('select[name="pickup_time"]');
  const returnTimeSelect = document.querySelector('select[name="return_time"]');
  if (!tpPopup || !pickupTimeSelect) return;

  // ÖNEMLI: tp-popup ve tp-backdrop'u body'e taşı — aksi halde
  // ata elemanlarda backdrop-filter / overflow:hidden olduğu için
  // position:fixed viewport'a göre değil, parent'a göre çalışır
  // (CSS containing block kuralı). Bu yüzden picker form içinde sıkışıyordu.
  if (tpBackdrop && tpBackdrop.parentNode !== document.body) {
    document.body.appendChild(tpBackdrop);
  }
  if (tpPopup && tpPopup.parentNode !== document.body) {
    document.body.appendChild(tpPopup);
  }

  let currentRole = 'pickup';

  // Tüm saatler 06:00–23:30 arası (yarım saatlik) — TEK GRID
  function buildSlots() {
    const make = (h, m) => {
      const t = String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0');
      return '<button type="button" class="tp-slot" data-time="' + t + '">' + t + '</button>';
    };
    let html = '';
    for (let h = 6; h <= 23; h++) { html += make(h, 0); html += make(h, 30); }
    grid.innerHTML = html;
  }
  buildSlots();

  function highlightSelected() {
    const sel = currentRole === 'pickup' ? pickupTimeSelect.value : returnTimeSelect.value;
    tpPopup.querySelectorAll('.tp-slot').forEach(b => {
      if (b.dataset.time === sel) b.classList.add('active');
      else b.classList.remove('active');
    });
  }

  // Diğer scriptlerin çağırabilmesi için global fonksiyon
  window.openTimePicker = function(role) {
    currentRole = role;
    if (role === 'pickup') {
      tpTitle.textContent = 'Aracınızı saat kaçta almak istiyorsunuz?';
    } else {
      tpTitle.textContent = 'Aracı saat kaçta teslim edeceksiniz?';
    }
    highlightSelected();
    // iOS scroll lock — pozisyonu kaydet
    if (window.innerWidth <= 720) {
      const scrollY = window.scrollY;
      document.body.dataset.tpScrollY = String(scrollY);
      document.body.style.top = '-' + scrollY + 'px';
    }
    tpBackdrop.classList.add('open');
    tpPopup.classList.add('open');
    document.body.classList.add('rdp-lock');
    // Seçili slotu görünür alana scrollla
    setTimeout(() => {
      const active = tpPopup.querySelector('.tp-slot.active');
      if (active) {
        const tpBody = tpPopup.querySelector('.tp-body');
        if (tpBody) tpBody.scrollTop = Math.max(0, active.offsetTop - 100);
      }
    }, 50);
  };

  function closeTp() {
    tpBackdrop.classList.remove('open');
    tpPopup.classList.remove('open');
    document.body.classList.remove('rdp-lock');
    // iOS scroll lock — pozisyonu geri yükle
    if (window.innerWidth <= 720 && document.body.dataset.tpScrollY !== undefined) {
      const y = parseInt(document.body.dataset.tpScrollY || '0', 10);
      document.body.style.top = '';
      delete document.body.dataset.tpScrollY;
      window.scrollTo(0, y);
    }
  }

  tpClose.addEventListener('click', closeTp);
  tpBackdrop.addEventListener('click', closeTp);

  tpPopup.addEventListener('click', (e) => {
    const slot = e.target.closest('.tp-slot');
    if (!slot) return;
    const t = slot.dataset.time;
    if (currentRole === 'pickup') {
      let opt = pickupTimeSelect.querySelector('option[value="' + t + '"]');
      if (opt) { pickupTimeSelect.value = t; }
      closeTp();
      // Alış saati seçildikten sonra iade saatini de sor (her ekranda)
      setTimeout(() => window.openTimePicker('return'), 250);
    } else {
      let opt = returnTimeSelect.querySelector('option[value="' + t + '"]');
      if (opt) { returnTimeSelect.value = t; }
      closeTp();
    }
  });

  // ESC kapatma
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && tpPopup.classList.contains('open')) closeTp();
  });

  // Saat <select>'lerine tıklayınca büyük picker'ı aç (her ekranda)
  function openOnSelectClick(selectEl, role) {
    if (!selectEl) return;
    selectEl.addEventListener('mousedown', (e) => {
      e.preventDefault();
      selectEl.blur();
      window.openTimePicker(role);
    });
    // Tab ile odaklanan klavye kullanıcıları için
    selectEl.addEventListener('focus', (e) => {
      // Sadece klavye ile (mouse zaten yukarıda halledildi)
      if (e.relatedTarget !== null) {
        window.openTimePicker(role);
      }
    });
  }
  openOnSelectClick(pickupTimeSelect, 'pickup');
  openOnSelectClick(returnTimeSelect, 'return');
})();
</script>

<style>
/* === Tarih picker mobil ay nav iyileştirme === */
.rdp-nav-mobile-bar {
  display: flex !important;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px !important;
  gap: 12px;
  background: linear-gradient(135deg, #F5F8FC, #fff);
  border-bottom: 1px solid #EDF1F7;
}
.rdp-nav-btn-big {
  width: 48px !important;
  height: 48px !important;
  background: #fff !important;
  border: 1.5px solid #DCE4EE !important;
  border-radius: 12px !important;
  color: #1d71b8 !important;
  cursor: pointer;
  display: flex !important;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  box-shadow: 0 2px 6px rgba(10,31,51,0.06);
  flex-shrink: 0;
}
.rdp-nav-btn-big:hover {
  background: #1d71b8 !important;
  color: #fff !important;
  border-color: #1d71b8 !important;
  transform: scale(1.05);
}
.rdp-nav-btn-big:active { transform: scale(0.97); }
.rdp-current-month {
  flex: 1;
  text-align: center;
  font-size: 16px;
  font-weight: 800;
  color: #0A1F33;
  letter-spacing: -0.01em;
  user-select: none;
}
@media (max-width: 720px) {
  .rdp-current-month { font-size: 17px; }
  .rdp-nav-btn-big { width: 52px !important; height: 52px !important; }
  .rdp-day { font-size: 15px !important; min-height: 44px; }
}

/* === Time picker (saat seçici) === */
.tp-backdrop {
  display: none;
  position: fixed; inset: 0;
  background: rgba(10,31,51,0.6);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  z-index: 10001;
}
.tp-backdrop.open { display: block; }
.tp-popup {
  display: none;
  position: fixed;
  z-index: 10002;
  background: #fff;
  flex-direction: column;
  overflow: hidden;
}
.tp-popup.open { display: flex; }

/* Desktop: tarih picker gibi ortada modal */
.tp-popup {
  top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  width: 560px;
  max-width: calc(100vw - 32px);
  max-height: 85vh;
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.tp-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 18px 22px;
  border-bottom: 1px solid #EDF1F7;
  background: linear-gradient(135deg, #F5F8FC, #fff);
  gap: 14px;
}
.tp-title-main {
  font-size: 17px;
  font-weight: 800;
  color: #0A1F33;
  letter-spacing: -0.01em;
  line-height: 1.3;
}
.tp-close {
  width: 36px; height: 36px;
  border-radius: 10px;
  background: #fff;
  border: 1.5px solid #DCE4EE;
  cursor: pointer;
  font-size: 16px;
  color: #627388;
  display: flex; align-items: center; justify-content: center;
  transition: all 0.2s;
  flex-shrink: 0;
}
.tp-close:hover {
  background: #e94e1b; color: #fff; border-color: #e94e1b;
  transform: rotate(90deg);
}
.tp-body {
  padding: 18px;
  overflow-y: auto;
  flex: 1;
}
/* TEK GRID — masaüstü 6 sütun, mobil 4 sütun (taşmayacak) */
.tp-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 8px;
}
.tp-slot {
  padding: 12px 4px;
  background: #F5F8FC;
  border: 1.5px solid #DCE4EE;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 700;
  color: #0A1F33;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.15s;
  min-height: 46px;
  text-align: center;
  white-space: nowrap;
}
.tp-slot:hover {
  background: #E8F1F9;
  border-color: #1d71b8;
  color: #1d71b8;
  transform: translateY(-1px);
}
.tp-slot.active {
  background: #1d71b8 !important;
  color: #fff !important;
  border-color: #1d71b8 !important;
  box-shadow: 0 4px 12px rgba(29,113,184,0.3);
}
.tp-slot:active { transform: scale(0.97); }

/* Mobil: tam ekran bottom sheet, 4 sütun grid (taşmaz) */
@media (max-width: 720px) {
  .tp-popup {
    /* Tam ekran modal — tarih picker ile aynı tarzda */
    top: 0 !important;
    left: 0 !important;
    bottom: auto !important;
    right: auto !important;
    transform: none !important;
    width: 100vw !important;
    height: 100vh !important;
    max-width: 100vw !important;
    max-height: 100vh !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    animation: tpSlideUp 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
    /* iOS safe-area dahil */
    padding-top: env(safe-area-inset-top, 0px);
    box-sizing: border-box;
  }
  @keyframes tpSlideUp {
    from { transform: translateY(100%); opacity: 0.6; }
    to { transform: translateY(0); opacity: 1; }
  }
  .tp-popup.open {
    display: flex !important;
    flex-direction: column !important;
  }
  /* Header — akışın ilk elemanı, scroll OLMAZ */
  .tp-popup .tp-header {
    padding: 18px 18px 16px !important;
    flex-shrink: 0 !important;
    flex-grow: 0 !important;
    background: linear-gradient(135deg, #F5F8FC, #fff) !important;
    border-bottom: 1px solid #EDF1F7 !important;
    position: relative !important;  /* sticky DEĞİL — akışta */
    top: auto !important;
    z-index: 2;
    display: flex !important;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    width: 100%;
    box-sizing: border-box;
  }
  .tp-popup .tp-title-wrap {
    flex: 1;
    min-width: 0;
  }
  .tp-popup .tp-title-main {
    font-size: 16px !important;
    line-height: 1.35 !important;
    color: #0A1F33 !important;
    font-weight: 800 !important;
    margin: 0;
  }
  .tp-popup .tp-close {
    flex-shrink: 0;
  }
  /* Body — kalan alanı doldur, içinde scroll */
  .tp-popup .tp-body {
    padding: 14px 14px calc(20px + env(safe-area-inset-bottom, 0px)) !important;
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch !important;
    flex: 1 1 auto !important;
    min-height: 0 !important;
  }
  .tp-popup .tp-grid { grid-template-columns: repeat(4, 1fr); gap: 8px; }
  .tp-popup .tp-slot {
    padding: 14px 2px;
    font-size: 14px;
    min-height: 50px;
  }
  /* iOS scroll lock — body fix */
  body.rdp-lock {
    position: fixed !important;
    width: 100% !important;
    overflow: hidden !important;
  }
}
@media (max-width: 360px) {
  .tp-popup .tp-grid { grid-template-columns: repeat(3, 1fr); }
  .tp-popup .tp-slot { font-size: 13.5px; min-height: 46px; }
}
</style>

<!-- ========== TRUST STRIP — Premium, özgün ========== -->
<section class="trust-v2">
  <div class="trust-v2-inner">
    <div class="trust-v2-eyebrow">
      <span class="tv2-line"></span>
      <span class="tv2-text">GÜVENCE VE HIZ</span>
      <span class="tv2-line"></span>
    </div>

    <div class="trust-v2-grid">

      <!-- 1. HIZ -->
      <div class="tv2-card" data-accent="blue">
        <div class="tv2-icon-wrap">
          <div class="tv2-icon-bg"></div>
          <svg class="tv2-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
          </svg>
          <div class="tv2-orbit"></div>
        </div>
        <div class="tv2-body">
          <div class="tv2-title">60 Saniyede Rezervasyon</div>
          <div class="tv2-sub">Hızlı form, anında onay</div>
        </div>
        <div class="tv2-metric">60<span>sn</span></div>
      </div>

      <!-- 2. GÜVENLİ ÖDEME -->
      <div class="tv2-card" data-accent="green">
        <div class="tv2-icon-wrap">
          <div class="tv2-icon-bg"></div>
          <svg class="tv2-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="5" width="20" height="14" rx="2.5"/>
            <path d="M2 10h20"/>
            <path d="M6 15h4"/>
          </svg>
          <div class="tv2-shield"></div>
        </div>
        <div class="tv2-body">
          <div class="tv2-title">Güvenli Ödeme</div>
          <div class="tv2-sub">256-bit SSL şifreleme</div>
        </div>
        <div class="tv2-metric tv2-metric-sm">SSL<span>✓</span></div>
      </div>

      <!-- 3. SİGORTA -->
      <div class="tv2-card" data-accent="orange">
        <div class="tv2-icon-wrap">
          <div class="tv2-icon-bg"></div>
          <svg class="tv2-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2L4 6v6c0 5 3.5 9.5 8 10 4.5-.5 8-5 8-10V6l-8-4z"/>
            <path d="M9 12l2 2 4-4"/>
          </svg>
        </div>
        <div class="tv2-body">
          <div class="tv2-title">Tam Kapsamlı Sigorta</div>
          <div class="tv2-sub">Her kiralamada ücretsiz</div>
        </div>
        <div class="tv2-metric tv2-metric-sm">%100</div>
      </div>

      <!-- 4. 7/24 DESTEK -->
      <div class="tv2-card" data-accent="purple">
        <div class="tv2-icon-wrap">
          <div class="tv2-icon-bg"></div>
          <svg class="tv2-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
          </svg>
          <span class="tv2-live"></span>
        </div>
        <div class="tv2-body">
          <div class="tv2-title">7/24 Canlı Destek</div>
          <div class="tv2-sub">Anında telefon ve WhatsApp</div>
        </div>
        <div class="tv2-metric tv2-metric-sm">
          <span class="tv2-pulse"></span>
          AÇIK
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ========== LOCATIONS ========== -->
<section class="loc-sec">
  <div class="sec-inner">
    <div class="sec-head" style="display:none;">
      <div class="sec-eyebrow">POPÜLER LOKASYONLAR</div>
      <h2 class="sec-title">Popüler Lokasyonlar</h2>
      <p class="sec-desc">En çok tercih edilen havalimanı ve şehirler</p>
    </div>
    <div class="loc-grid">
      <?php foreach ($featuredLocations as $o):
        // Uyumluluk: locations tablosu → name_tr, offices fallback → name
        $locName = $o['name_tr'] ?? $o['name'] ?? '';
        $locSlug = $o['slug'] ?? '';
        $locImage = $o['image'] ?? '';
        $locIsAirport = ($o['type'] ?? '') === 'airport' || !empty($o['is_airport']);
        $locCityName = $o['city_name'] ?? '';
        // URL: locations tablosundan geliyorsa /lokasyon/slug, offices fallback ise /filo?pickup_city
        $locUrl = isset($o['type']) && !isset($o['working_hours'])
          ? url('lokasyon/' . e($locSlug))
          : url('filo?pickup_city=' . ($o['city_id'] ?? ''));
      ?>
        <a href="<?= $locUrl ?>" class="loc-card">
          <div class="loc-card-img">
            <?php if (!empty($locImage)): ?>
              <img src="<?= (strpos($locImage, 'http') === 0) ? e($locImage) : upload_url($locImage) ?>" alt="<?= e($locName) ?>" loading="lazy">
            <?php else: ?>
              <div class="loc-card-img-placeholder">
                <span style="font-size:64px; color:rgba(255,255,255,0.3);"><?= $locIsAirport ? '✈' : '📍' ?></span>
              </div>
            <?php endif ?>
            <div class="loc-card-overlay"></div>
            <div class="loc-card-content">
              <?php if ($locCityName && $locCityName !== $locName): ?>
                <div class="loc-card-city"><?= e($locCityName) ?></div>
              <?php endif ?>
              <h3 class="loc-card-name"><?= e($locName) ?></h3>
              <div class="loc-card-cta">
                <span>İncele</span>
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
              </div>
            </div>
          </div>
        </a>
      <?php endforeach ?>
    </div>
    <div style="text-align:center; margin-top:30px;">
      <a href="<?= url('lokasyonlar') ?>" class="btn-outline-brand">Tüm Lokasyonları Gör →</a>
    </div>
  </div>
</section>

<!-- ========== CAMPAIGNS ========== -->
<?php if (!empty($featuredCampaigns)): ?>
<section class="camp-sec">
  <div class="sec-inner">
    <div class="sec-head">
      <div class="sec-eyebrow">FIRSATLAR</div>
      <h2 class="sec-title">Güncel Kampanyalar</h2>
      <p class="sec-desc">Kaçırılmayacak indirim ve avantajlar</p>
    </div>
    <div class="camp-grid">
      <?php foreach ($featuredCampaigns as $c): ?>
        <a href="<?= url('kampanya/' . e($c['slug'])) ?>" class="camp-card">
          <?php if ($c['cover_image']): ?>
            <img src="<?= upload_url($c['cover_image']) ?>" alt="<?= e($c['title_tr']) ?>" loading="lazy">
          <?php else: ?>
            <div style="height:200px; background:linear-gradient(135deg, var(--brand), var(--accent));"></div>
          <?php endif ?>
          <div class="cc-body">
            <?php if ($c['discount_type'] === 'percent' && $c['discount_value']): ?>
              <span class="cc-badge">%<?= (int)$c['discount_value'] ?> İNDİRİM</span>
            <?php endif ?>
            <h3><?= e(lang_field_value($c, 'title')) ?></h3>
            <p><?= e(lang_field_value($c, 'short_desc')) ?></p>
            <?php if ($c['end_date']): ?>
              <div class="cc-end">📅 Son: <?= tr_date($c['end_date']) ?></div>
            <?php endif ?>
          </div>
        </a>
      <?php endforeach ?>
    </div>
    <div style="text-align:center; margin-top:30px;">
      <a href="<?= url('kampanyalar') ?>" class="btn-outline-brand">Tüm Kampanyalar →</a>
    </div>
  </div>
</section>
<?php endif ?>

<!-- ========== 3 ADIM — PREMIUM TURUNCU ========== -->
<section class="steps-v2">
  <!-- Dekoratif arka plan öğeleri -->
  <div class="steps-v2-bg-glow steps-v2-bg-glow-1"></div>
  <div class="steps-v2-bg-glow steps-v2-bg-glow-2"></div>

  <div class="steps-v2-inner">
    <div class="steps-v2-eyebrow">
      <span class="sv2-line"></span>
      <span class="sv2-text">3 BASİT ADIMDA</span>
      <span class="sv2-line"></span>
    </div>

    <h2 class="steps-v2-title">Aracınızı Kiralayın</h2>
    <p class="steps-v2-sub">Saniyeler içinde rezervasyon, dakikalar içinde aracınız hazır</p>

    <div class="steps-v2-grid">

      <!-- ADIM 1: ARA -->
      <div class="sv2-card" data-step="1">
        <div class="sv2-number">01</div>
        <div class="sv2-icon-wrap">
          <div class="sv2-icon-bg"></div>
          <svg class="sv2-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="7"/>
            <path d="M21 21l-4.35-4.35"/>
          </svg>
          <div class="sv2-icon-ring"></div>
        </div>
        <div class="sv2-body">
          <h3>Ara & Karşılaştır</h3>
          <p>Lokasyonunu ve tarihini seç. Tüm firmaların güncel fiyatlarını ve araçlarını tek ekranda karşılaştır.</p>
        </div>
        <div class="sv2-arrow">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
        </div>
      </div>

      <!-- ADIM 2: ÖDE -->
      <div class="sv2-card" data-step="2">
        <div class="sv2-number">02</div>
        <div class="sv2-icon-wrap">
          <div class="sv2-icon-bg"></div>
          <svg class="sv2-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="5" width="20" height="14" rx="2.5"/>
            <path d="M2 10h20"/>
            <path d="M7 15h2"/>
          </svg>
          <div class="sv2-icon-check">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
          </div>
        </div>
        <div class="sv2-body">
          <h3>Güvenle Öde</h3>
          <p>256-bit SSL şifreleme ile kredi kartı veya havale seçenekleri. Tüm kart bilgileriniz güvende.</p>
        </div>
        <div class="sv2-arrow">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
        </div>
      </div>

      <!-- ADIM 3: YOLA ÇIK -->
      <div class="sv2-card" data-step="3">
        <div class="sv2-number">03</div>
        <div class="sv2-icon-wrap">
          <div class="sv2-icon-bg"></div>
          <svg class="sv2-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="8" cy="15" r="3"/>
            <path d="M10.5 13L16 7.5l3 3L13.5 16"/>
            <path d="M14 9l3 3"/>
          </svg>
          <div class="sv2-icon-spark"></div>
        </div>
        <div class="sv2-body">
          <h3>Aracını Al, Yola Çık</h3>
          <p>Seçtiğin ofisten aracını anında teslim al. Tam depo yakıt, temizlik ve sigorta zaten dahil.</p>
        </div>
        <div class="sv2-finish">
          <span class="sv2-finish-dot"></span>
          <span>Hazır!</span>
        </div>
      </div>

    </div>

    <!-- CTA -->
    <div class="steps-v2-cta">
      <a href="#searchForm" class="sv2-cta-btn">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        Hemen Rezervasyon Yap
      </a>
      <span class="sv2-cta-meta">⏱ 60 saniyede tamamlanır</span>
    </div>
  </div>
</section>

<!-- ========== SSS (FAQ) ========== -->
<?php
$homeFaqs = db()->fetchAll("SELECT * FROM faqs WHERE is_active = 1 ORDER BY sort_order, id LIMIT 6");
?>
<?php if (!empty($homeFaqs)): ?>
<section class="faq-sec">
  <div class="sec-inner">
    <div class="sec-head">
      <div class="sec-eyebrow">SIKÇA SORULANLAR</div>
      <h2 class="sec-title" style="display:none;">Merak Ettikleriniz</h2>
      <p class="sec-desc" style="display:none;">Araç kiralama süreci hakkında en çok sorulan sorular</p>
    </div>
    <div class="faq-list">
      <?php foreach ($homeFaqs as $i => $faq): ?>
        <details class="faq-item" <?= $i === 0 ? 'open' : '' ?>>
          <summary class="faq-q">
            <span><?= e($faq['question_tr']) ?></span>
            <svg class="faq-icon" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
          </summary>
          <div class="faq-a"><?= nl2br(e($faq['answer_tr'])) ?></div>
        </details>
      <?php endforeach ?>
    </div>
    <div style="text-align:center; margin-top:24px;">
      <a href="<?= url('sss') ?>" class="btn-outline-brand">Tüm Sorular →</a>
    </div>
  </div>
</section>
<?php else: ?>
<!-- FAQ boşsa varsayılanlarla göster -->
<section class="faq-sec">
  <div class="sec-inner">
    <div class="sec-head">
      <div class="sec-eyebrow">SIKÇA SORULANLAR</div>
      <h2 class="sec-title" style="display:none;">Merak Ettikleriniz</h2>
      <p class="sec-desc" style="display:none;">Araç kiralama süreci hakkında en çok sorulan sorular</p>
    </div>
    <div class="faq-list">
      <details class="faq-item" open>
        <summary class="faq-q"><span>Araç kiralamak için hangi belgeler gerekli?</span><svg class="faq-icon" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></summary>
        <div class="faq-a">TC kimlik kartı, ehliyet (en az 1 yıllık) ve kredi kartı yeterlidir. Yabancı uyruklu müşteriler için pasaport ve uluslararası ehliyet gerekir.</div>
      </details>
      <details class="faq-item">
        <summary class="faq-q"><span>Minimum kiralama yaşı kaç?</span><svg class="faq-icon" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></summary>
        <div class="faq-a">Ekonomik segment için 21+, orta segment için 23+, lüks araçlar için 25+ yaş sınırı bulunur. Ayrıca ehliyet süresi de her segmentte farklılık gösterir.</div>
      </details>
      <details class="faq-item">
        <summary class="faq-q"><span>Sigorta kapsamı nedir?</span><svg class="faq-icon" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></summary>
        <div class="faq-a">Tüm araçlarımız zorunlu trafik sigortası ile kiralanır. Tam kasko ve süper kasko paketleri ek ücretli olarak eklenebilir.</div>
      </details>
      <details class="faq-item">
        <summary class="faq-q"><span>Farklı şehirde iade edebilir miyim?</span><svg class="faq-icon" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></summary>
        <div class="faq-a">Evet! Farklı şehir/ofiste iade seçeneği mevcuttur. Ek ücret lokasyonlara göre değişir, rezervasyon sırasında görebilirsiniz.</div>
      </details>
      <details class="faq-item">
        <summary class="faq-q"><span>Rezervasyon iptali nasıl yapılır?</span><svg class="faq-icon" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></summary>
        <div class="faq-a">Alış tarihinden 24 saat önceye kadar ücretsiz iptal hakkınız vardır. Daha geç iptaller için belirli koşullar uygulanır.</div>
      </details>
    </div>
  </div>
</section>
<?php endif ?>

<!-- ========== MÜŞTERİ YORUMLARI ========== -->
<section class="testi-sec">
  <div class="sec-inner">
    <div class="sec-head" style="text-align:center; margin-bottom:30px;">
      <h2 class="sec-title testi-centered-title">Binlerce Mutlu Müşteri</h2>
    </div>

    <!-- İstatistik bandı -->
    <div class="testi-stats">
      <div class="testi-stat">
        <div class="ts-big">4.8<span>/5</span></div>
        <div class="ts-stars">★★★★★</div>
        <div class="ts-small">2.400+ değerlendirme</div>
      </div>
      <div class="testi-stat-divider"></div>
      <div class="testi-stat">
        <div class="ts-big">%97</div>
        <div class="ts-small">Tekrar tercih oranı</div>
      </div>
      <div class="testi-stat-divider"></div>
      <div class="testi-stat">
        <div class="ts-big">15K+</div>
        <div class="ts-small">Tamamlanan rezervasyon</div>
      </div>
    </div>

    <!-- Yorum kartları -->
    <div class="testi-grid">
      <div class="testi-card">
        <div class="tc-header">
          <div class="tc-avatar" style="background: linear-gradient(135deg, #1d71b8, #2F8FD8);">MY</div>
          <div class="tc-user">
            <div class="tc-name">Mehmet Yılmaz</div>
            <div class="tc-meta">İstanbul · 2 hafta önce</div>
          </div>
          <div class="tc-stars">★★★★★</div>
        </div>
        <div class="tc-quote">
          <svg class="tc-quote-icon" viewBox="0 0 32 32" fill="currentColor"><path d="M9.352 4C4.456 7.456 1 13.12 1 20.224 1 26.136 4.624 30 9.08 30c3.776 0 6.728-3.056 6.728-6.784 0-3.36-2.272-5.872-5.336-5.872-.72 0-1.616.128-1.872.256.416-3.424 3.696-7.664 7.04-9.792L9.352 4zm18.088 0c-4.88 3.456-8.344 9.12-8.344 16.224C19.096 26.136 22.72 30 27.176 30c3.776 0 6.728-3.056 6.728-6.784 0-3.36-2.272-5.872-5.336-5.872-.72 0-1.616.128-1.872.256.416-3.424 3.696-7.664 7.04-9.792L27.44 4z"/></svg>
          <p>İstanbul Havalimanı'nda araç kiraladım, işlem 10 dakika sürdü. Araç tertemiz, yakıtı dolu. Fiyat diğer firmalardan belirgin şekilde uygun. Kesinlikle tavsiye ediyorum.</p>
        </div>
        <div class="tc-footer">
          <span class="tc-vehicle">🚗 Fiat Egea · 4 gün</span>
          <span class="tc-verified">✓ Doğrulanmış</span>
        </div>
      </div>

      <div class="testi-card testi-card-highlight">
        <div class="tc-header">
          <div class="tc-avatar" style="background: linear-gradient(135deg, #e94e1b, #F97316);">AK</div>
          <div class="tc-user">
            <div class="tc-name">Ayşe Kaya</div>
            <div class="tc-meta">Antalya · 1 ay önce</div>
          </div>
          <div class="tc-stars">★★★★★</div>
        </div>
        <div class="tc-quote">
          <svg class="tc-quote-icon" viewBox="0 0 32 32" fill="currentColor"><path d="M9.352 4C4.456 7.456 1 13.12 1 20.224 1 26.136 4.624 30 9.08 30c3.776 0 6.728-3.056 6.728-6.784 0-3.36-2.272-5.872-5.336-5.872-.72 0-1.616.128-1.872.256.416-3.424 3.696-7.664 7.04-9.792L9.352 4zm18.088 0c-4.88 3.456-8.344 9.12-8.344 16.224C19.096 26.136 22.72 30 27.176 30c3.776 0 6.728-3.056 6.728-6.784 0-3.36-2.272-5.872-5.336-5.872-.72 0-1.616.128-1.872.256.416-3.424 3.696-7.664 7.04-9.792L27.44 4z"/></svg>
          <p>Ailece Antalya tatilimiz için SUV kiraladık. Çocuk koltuğu ekstrası ücretsiz geldi, web sitesi söylediği fiyata sadık kaldı. Destek ekibi her aradığımda çok ilgiliydi.</p>
        </div>
        <div class="tc-footer">
          <span class="tc-vehicle">🚗 Hyundai Tucson · 7 gün</span>
          <span class="tc-verified">✓ Doğrulanmış</span>
        </div>
      </div>

      <div class="testi-card">
        <div class="tc-header">
          <div class="tc-avatar" style="background: linear-gradient(135deg, #16A34A, #22C55E);">EÖ</div>
          <div class="tc-user">
            <div class="tc-name">Emre Özdemir</div>
            <div class="tc-meta">İzmir · 3 hafta önce</div>
          </div>
          <div class="tc-stars">★★★★<span style="opacity:0.4">★</span></div>
        </div>
        <div class="tc-quote">
          <svg class="tc-quote-icon" viewBox="0 0 32 32" fill="currentColor"><path d="M9.352 4C4.456 7.456 1 13.12 1 20.224 1 26.136 4.624 30 9.08 30c3.776 0 6.728-3.056 6.728-6.784 0-3.36-2.272-5.872-5.336-5.872-.72 0-1.616.128-1.872.256.416-3.424 3.696-7.664 7.04-9.792L9.352 4zm18.088 0c-4.88 3.456-8.344 9.12-8.344 16.224C19.096 26.136 22.72 30 27.176 30c3.776 0 6.728-3.056 6.728-6.784 0-3.36-2.272-5.872-5.336-5.872-.72 0-1.616.128-1.872.256.416-3.424 3.696-7.664 7.04-9.792L27.44 4z"/></svg>
          <p>İş seyahati için kullandım. Online rezervasyon sistemi gerçekten hızlı. Havalimanında aracımı 5 dakikada teslim aldım. Tek eksi ekstra sürücü ücreti biraz yüksek geldi.</p>
        </div>
        <div class="tc-footer">
          <span class="tc-vehicle">🚗 VW Polo · 3 gün</span>
          <span class="tc-verified">✓ Doğrulanmış</span>
        </div>
      </div>
    </div>

    <!-- Trust logos -->
    <div class="testi-trust">
      <div class="tt-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
        <span>Google 4.8 ★</span>
      </div>
      <div class="tt-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L4 6v6c0 5 3.5 9.5 8 10 4.5-.5 8-5 8-10V6l-8-4z"/></svg>
        <span>SSL Sertifikalı</span>
      </div>
      <div class="tt-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
        <span>Güvenli Ödeme</span>
      </div>
      <div class="tt-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        <span>15.000+ Müşteri</span>
      </div>
    </div>
  </div>
</section>

<!-- ========== BLOG — Yenilenmiş ========== -->
<?php if (!empty($recentPosts)): ?>
<section class="blog-v2">
  <div class="sec-inner">
    <div class="sec-head-v2">
      <div>
        <div class="sec-eyebrow">REHBER & İPUÇLARI</div>
        <h2 class="sec-title">Yolculuk Rehberiniz</h2>
      </div>
      <a href="<?= url('blog') ?>" class="blog-v2-all">
        Tüm Yazılar
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
      </a>
    </div>

    <div class="blog-v2-grid">
      <?php foreach ($recentPosts as $idx => $p): ?>
        <a href="<?= url('blog/' . e($p['slug'])) ?>" class="blog-v2-card <?= $idx === 0 ? 'blog-v2-card-featured' : '' ?>">
          <div class="bv2-img">
            <?php if ($p['cover_image']): ?>
              <img src="<?= upload_url($p['cover_image']) ?>" alt="<?= e(lang_field_value($p, 'title')) ?>" loading="lazy">
            <?php else: ?>
              <div class="bv2-img-placeholder">
                <svg viewBox="0 0 100 60" xmlns="http://www.w3.org/2000/svg">
                  <rect width="100" height="60" fill="url(#bg<?= $p['id'] ?>)"/>
                  <defs>
                    <linearGradient id="bg<?= $p['id'] ?>" x1="0" y1="0" x2="1" y2="1">
                      <stop offset="0%" stop-color="#1d71b8"/>
                      <stop offset="100%" stop-color="#e94e1b"/>
                    </linearGradient>
                  </defs>
                </svg>
              </div>
            <?php endif ?>
            <div class="bv2-img-overlay"></div>
            <?php if (!empty($p['category_name'])): ?>
              <span class="bv2-cat"><?= e($p['category_name']) ?></span>
            <?php endif ?>
            <?php if ($idx === 0): ?>
              <span class="bv2-featured">⭐ Öne Çıkan</span>
            <?php endif ?>
          </div>
          <div class="bv2-body">
            <div class="bv2-meta">
              <span class="bv2-date">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                <?= tr_date($p['published_at'] ?: $p['created_at']) ?>
              </span>
              <span class="bv2-dot">·</span>
              <span class="bv2-read">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                <?= (int)$p['reading_time'] ?> dk okuma
              </span>
            </div>
            <h3 class="bv2-title"><?= e(lang_field_value($p, 'title')) ?></h3>
            <p class="bv2-excerpt"><?= e(str_excerpt(lang_field_value($p, 'excerpt') ?: strip_tags(lang_field_value($p, 'content')), $idx === 0 ? 320 : 140)) ?></p>
            <div class="bv2-cta">
              Yazıyı Oku
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
            </div>
          </div>
        </a>
      <?php endforeach ?>
    </div>
  </div>
</section>
<?php endif ?>

<!-- ========== CTA ========== -->
<section class="cta-sec">
  <div class="cta-inner">
    <h2>Hazır mısınız?</h2>
    <p>Türkiye'nin en güvenilir araç kiralama platformuyla yolculuğunuza başlayın</p>
    <div class="cta-btns">
      <a href="<?= url('filo') ?>" class="cta-btn-primary">🚗 Araç Ara</a>
      <a href="tel:<?= preg_replace('/\s+/', '', setting('site_phone', '08505000777')) ?>" class="cta-btn-outline">📞 Hemen Ara</a>
    </div>
  </div>
</section>

<!-- ARAMA LOADER (form submit'te görünür) -->
<div class="vehicle-loader-overlay" id="homeSearchLoader">
  <div class="vlo-inner">
    <div class="vlo-icon">
      <span class="vlo-car">🚗</span>
    </div>
    <div class="vlo-title">Size en uygun araçlar aranıyor</div>
    <div class="vlo-sub">Tüm firmaların güncel müsaitlik ve fiyatları kontrol ediliyor.<br>Bu birkaç saniye sürebilir.</div>
    <div class="vlo-steps">
      <div class="vlo-step active" id="hslStep1">
        <div class="vlo-step-icon"></div>
        <span>Müsait araçlar taranıyor...</span>
      </div>
      <div class="vlo-step" id="hslStep2">
        <div class="vlo-step-icon"></div>
        <span>Firma fiyatları karşılaştırılıyor...</span>
      </div>
      <div class="vlo-step" id="hslStep3">
        <div class="vlo-step-icon"></div>
        <span>En iyi seçenekler sıralanıyor...</span>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/frontend/footer.php'; ?>
