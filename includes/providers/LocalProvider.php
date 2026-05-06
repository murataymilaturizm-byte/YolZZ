<?php
/**
 * LocalProvider
 * Senin kendi DB'ndeki (manuel eklenmiş) araçları standart formatta döner.
 * Böylece filo.php ve diğer yerler, lokal ve API araçlarını aynı şekilde işleyebilir.
 *
 * ÖNEMLİ: Bu provider mevcut DB'yi okur, hiçbir veriyi değiştirmez.
 * Sistem şimdikiyle birebir aynı çalışır, sadece "provider" sarmalayıcısı eklenir.
 */

if (!defined('YOLZZ_APP')) { die('Forbidden'); }

require_once __DIR__ . '/VehicleProviderInterface.php';

class LocalProvider implements VehicleProviderInterface
{
    public function getName(): string { return 'Kendi Araçlarım'; }
    public function getSourceType(): string { return 'local'; }

    public function searchVehicles(array $params): array
    {
        $where = ["v.status = 'active'"];
        // Sadece lokal araçlar (source_type null veya 'local')
        $where[] = "(v.source_type IS NULL OR v.source_type = 'local' OR v.api_provider_id IS NULL)";
        $bind = [];

        if (!empty($params['pickup_office_id'])) {
            $where[] = "(v.office_id = :officeId OR v.office_id IS NULL)";
            $bind['officeId'] = (int)$params['pickup_office_id'];
        }
        if (!empty($params['category_id'])) {
            $where[] = "v.category_id = :cat"; $bind['cat'] = (int)$params['category_id'];
        }
        if (!empty($params['brand_id'])) {
            $where[] = "v.brand_id = :br"; $bind['br'] = (int)$params['brand_id'];
        }
        if (!empty($params['fuel_type'])) {
            $where[] = "v.fuel_type = :f"; $bind['f'] = $params['fuel_type'];
        }
        if (!empty($params['transmission'])) {
            $where[] = "v.transmission = :tr"; $bind['tr'] = $params['transmission'];
        }
        if (!empty($params['min_price'])) {
            $where[] = "v.daily_price >= :minp"; $bind['minp'] = (float)$params['min_price'];
        }
        if (!empty($params['max_price'])) {
            $where[] = "v.daily_price <= :maxp"; $bind['maxp'] = (float)$params['max_price'];
        }

        $sql = "
            SELECT v.*,
                   b.name AS brand_name,
                   c.name AS category_name,
                   o.name AS office_name,
                   cc.name AS city_name
            FROM vehicles v
            LEFT JOIN vehicle_brands b ON b.id = v.brand_id
            LEFT JOIN vehicle_categories c ON c.id = v.category_id
            LEFT JOIN offices o ON o.id = v.office_id
            LEFT JOIN cities cc ON cc.id = o.city_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY v.featured DESC, v.daily_price ASC
            LIMIT 200
        ";

        $rows = db()->fetchAll($sql, $bind);

        // Rezervasyon günlerini hesapla
        $days = 1;
        if (!empty($params['pickup_date']) && !empty($params['return_date'])) {
            $p = strtotime($params['pickup_date']);
            $r = strtotime($params['return_date']);
            if ($p && $r && $r > $p) {
                $days = max(1, (int)ceil(($r - $p) / 86400));
            }
        }

        $result = [];
        foreach ($rows as $v) {
            $daily = (float)$v['daily_price'];
            // İndirim varsa uygula
            if (!empty($v['discount_percent']) && $v['discount_percent'] > 0) {
                $daily = $daily * (1 - (float)$v['discount_percent'] / 100);
            }

            $result[] = [
                'source' => 'local',
                'provider_id' => null,
                'provider_name' => 'Kendi Araçlarım',
                'source_label' => 'Manuel',
                'external_id' => null,
                'local_id' => (int)$v['id'],
                'brand' => $v['brand_name'] ?? '',
                'model' => $v['model'] ?? '',
                'full_name' => trim(($v['brand_name'] ?? '') . ' ' . ($v['model'] ?? '')),
                'year' => (int)($v['year'] ?? 0),
                'category' => $v['category_name'] ?? '',
                'category_id' => (int)$v['category_id'],
                'brand_id' => (int)$v['brand_id'],
                'fuel_type' => $v['fuel_type'] ?? '',
                'transmission' => $v['transmission'] ?? '',
                'seats' => (int)($v['seats'] ?? 5),
                'doors' => (int)($v['doors'] ?? 4),
                'luggage' => (int)($v['luggage'] ?? 2),
                'daily_price' => round($daily, 2),
                'currency' => $v['currency'] ?? 'TRY',
                'deposit' => (float)($v['deposit'] ?? 0),
                'total_price' => round($daily * $days, 2),
                'days' => $days,
                'image' => $v['main_image'] ?? $v['image'] ?? '',
                'features' => $v['features'] ? json_decode($v['features'], true) : [],
                'office_id' => $v['office_id'] ? (int)$v['office_id'] : null,
                'office_name' => $v['office_name'] ?? null,
                'city_name' => $v['city_name'] ?? null,
                'pickup_office_name' => $v['office_name'] ?? '',
                'available' => true,
                'slug' => $v['slug'] ?? '',
                'tag' => $v['tag'] ?? null,
                'discount_percent' => (float)($v['discount_percent'] ?? 0),
                'raw_id' => (int)$v['id'],
            ];
        }

        return $result;
    }

    public function createBooking(array $bookingData): array
    {
        // Lokal rezervasyon zaten normal checkout flow'undan bookings tablosuna yazılıyor.
        // Bu method yalnızca uzak API'ler için anlamlı. LocalProvider no-op.
        return ['success' => true, 'external_id' => null, 'message' => 'Local booking', 'raw' => null];
    }

    public function cancelBooking(string $externalId): array
    {
        return ['success' => true, 'message' => 'Local cancel', 'raw' => null];
    }

    public function getLocations(): array
    {
        $rows = db()->fetchAll("
            SELECT o.id, o.name, o.is_airport, c.name AS city_name
            FROM offices o
            JOIN cities c ON c.id = o.city_id
            WHERE o.is_active = 1
            ORDER BY c.name, o.name
        ");
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'external_id' => (string)$r['id'],
                'name' => $r['city_name'] . ' - ' . $r['name'],
                'is_airport' => (bool)$r['is_airport'],
            ];
        }
        return $out;
    }

    public function testConnection(): array
    {
        try {
            db()->fetchColumn("SELECT 1");
            return ['success' => true, 'message' => 'Lokal DB bağlantısı OK'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
