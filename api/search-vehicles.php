<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$pickupCity = (int)get('pickup_city');
$pickupDate = get('pickup_date');
$returnDate = get('return_date');
$category = (int)get('category');
$limit = min(50, (int)get('limit', 20));

$where = ["v.status = 'active'"];
$params = [];

if ($pickupCity) {
    $where[] = "(v.office_id IN (SELECT id FROM offices WHERE city_id = :c) OR v.office_id IS NULL)";
    $params['c'] = $pickupCity;
}
if ($category) { $where[] = "v.category_id = :cat"; $params['cat'] = $category; }

$vehicles = db()->fetchAll(
    "SELECT v.id, v.model, v.year, v.fuel_type, v.transmission, v.seats, v.luggage,
            v.daily_price, v.discount_percent, v.tag, v.main_image,
            vb.name AS brand_name, vc.name_tr AS category_name
     FROM vehicles v
     LEFT JOIN vehicle_brands vb ON vb.id = v.brand_id
     LEFT JOIN vehicle_categories vc ON vc.id = v.category_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY v.sort_order, v.daily_price ASC
     LIMIT $limit",
    $params
);

// Upload URL'lerini ekle
foreach ($vehicles as &$v) {
    if ($v['main_image']) {
        $v['image_url'] = upload_url($v['main_image']);
    }
    $v['final_price'] = $v['discount_percent'] > 0
        ? $v['daily_price'] * (1 - $v['discount_percent'] / 100)
        : $v['daily_price'];
}

json_success(['vehicles' => $vehicles, 'count' => count($vehicles)]);
