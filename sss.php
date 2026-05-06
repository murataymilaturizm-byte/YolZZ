<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Sıkça Sorulan Sorular — Araç Kiralama Rehberi | Yolzz';
$pageDescription = 'Araç kiralama hakkında en çok sorulan sorular: Ehliyet şartları, sigorta, depozito, ödeme seçenekleri, iptal koşulları ve daha fazlası.';
$pageKeywords = 'araç kiralama sss, rent a car soruları, kiralama koşulları, depozito, sigorta, yaş sınırı';
$currentPage = '/sss';

$faqs = db()->fetchAll("SELECT * FROM faqs WHERE is_active = 1 ORDER BY category_tr, sort_order, id");

// Kategoriye göre grupla
$grouped = [];
foreach ($faqs as $f) {
    $grouped[$f['category_tr'] ?: 'Genel'][] = $f;
}

// FAQ Schema (Google'da zengin sonuç görünümü için)
if (!empty($faqs)) {
    $faqSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => []
    ];
    foreach ($faqs as $f) {
        $faqSchema['mainEntity'][] = [
            '@type' => 'Question',
            'name' => $f['question_tr'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => strip_tags($f['answer_tr'])
            ]
        ];
    }
    $pageJsonLd = json_encode($faqSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

include __DIR__ . '/includes/frontend/header.php';
?>

<section class="page-hero" style="padding:50px 0;">
  <div class="container" style="max-width:900px; margin:0 auto; padding:0 20px; text-align:center;">
    <h1 style="font-size:36px; color:var(--ink); margin-bottom:10px;">❓ Sıkça Sorulan Sorular</h1>
    <p style="color:var(--text);">Aklınızdaki tüm soruların cevapları burada</p>
  </div>
</section>

<section style="padding:30px 0 80px;">
  <div class="container" style="max-width:900px; margin:0 auto; padding:0 20px;">
    <?php if (empty($faqs)): ?>
      <div class="no-results">
        <div class="no-results-icon">❓</div>
        <h3>Henüz soru eklenmemiş</h3>
        <p>Sorularınız için <a href="<?= url('iletisim') ?>" style="color:var(--brand);">bize ulaşın</a></p>
      </div>
    <?php else: ?>
      <?php foreach ($grouped as $cat => $items): ?>
        <div style="margin-bottom:40px;">
          <h2 style="font-size:22px; color:var(--ink); margin-bottom:16px; padding-bottom:10px; border-bottom:2px solid var(--brand-wash);">
            <?= e($cat) ?>
          </h2>
          <div class="faq-list">
            <?php foreach ($items as $f): ?>
              <details class="faq-item">
                <summary>
                  <span><?= e($f['question_tr']) ?></span>
                  <span class="faq-chevron">▼</span>
                </summary>
                <div class="faq-answer">
                  <?= nl2br(e($f['answer_tr'])) ?>
                </div>
              </details>
            <?php endforeach ?>
          </div>
        </div>
      <?php endforeach ?>

      <div style="background:linear-gradient(135deg, var(--brand-wash), var(--accent-wash)); padding:30px; border-radius:16px; text-align:center; margin-top:40px;">
        <h3 style="margin-bottom:10px; color:var(--ink);">Başka sorunuz mu var?</h3>
        <p style="margin-bottom:20px; color:var(--text);">Size yardımcı olmaktan mutluluk duyarız</p>
        <a href="<?= url('iletisim') ?>" class="btn-primary-lg" style="display:inline-block; padding:12px 24px; background:var(--brand); color:#fff; border-radius:10px; text-decoration:none; font-weight:600;">Bize Ulaşın</a>
      </div>
    <?php endif ?>
  </div>
</section>

<style>
.faq-list { display: flex; flex-direction: column; gap: 10px; }
.faq-item { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 16px 20px; cursor: pointer; transition: all 0.2s; }
.faq-item[open] { border-color: var(--brand); box-shadow: var(--shadow); }
.faq-item summary { display: flex; justify-content: space-between; align-items: center; font-weight: 600; color: var(--ink); font-size: 15px; list-style: none; }
.faq-item summary::-webkit-details-marker { display: none; }
.faq-chevron { color: var(--brand); transition: transform 0.2s; font-size: 12px; }
.faq-item[open] .faq-chevron { transform: rotate(180deg); }
.faq-answer { margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border-soft); color: var(--text); line-height: 1.6; font-size: 14px; }
</style>

<?php include __DIR__ . '/includes/frontend/footer.php'; ?>
