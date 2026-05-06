<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$code = strtoupper(trim(post('code', get('code'))));
$subtotal = (float)post('subtotal', get('subtotal'));
$days = (int)post('days', get('days', 1));

if (!$code) {
    json_error('Kupon kodu gerekli.');
}

$campaign = db()->fetch("
    SELECT * FROM campaigns
    WHERE UPPER(coupon_code) = ?
    AND status = 'active'
    AND (start_date IS NULL OR start_date <= CURDATE())
    AND (end_date IS NULL OR end_date >= CURDATE())
", [$code]);

if (!$campaign) {
    json_error('Geçersiz veya süresi dolmuş kupon.');
}

if ($campaign['usage_limit'] > 0 && $campaign['usage_count'] >= $campaign['usage_limit']) {
    json_error('Bu kuponun kullanım limiti doldu.');
}

if ($days < $campaign['min_days']) {
    json_error("Bu kupon en az {$campaign['min_days']} günlük kiralama için geçerli.");
}

if ($campaign['min_amount'] > 0 && $subtotal < $campaign['min_amount']) {
    json_error('Minimum ' . tl($campaign['min_amount']) . ' tutarında kiralama gerekli.');
}

// İndirim hesapla
$discount = 0;
if ($campaign['discount_type'] === 'percent') {
    $discount = $subtotal * ($campaign['discount_value'] / 100);
} elseif ($campaign['discount_type'] === 'fixed') {
    $discount = min($campaign['discount_value'], $subtotal);
}

json_success([
    'valid' => true,
    'code' => $campaign['coupon_code'],
    'title' => $campaign['title_tr'],
    'discount_type' => $campaign['discount_type'],
    'discount_value' => (float)$campaign['discount_value'],
    'discount_amount' => round($discount, 2),
    'new_total' => round($subtotal - $discount, 2)
], 'Kupon uygulandı!');
