<?php
/**
 * RentlineProvider
 * Rentline (TRVrac) JSON API entegrasyonu.
 *
 * Dokümantasyona göre:
 * - Base URL örnek: http://sistemjson1.trvrac.com
 * - JsonLocations.aspx — Lokasyon listesi
 * - JsonGroup.aspx — Araç grupları
 * - JsonRez.aspx — Müsait araç sorgu
 * - JsonRez_Save.aspx — Rezervasyon kaydet
 * - JsonCancel.aspx — Rezervasyon iptal
 *
 * Auth: Key_Hack + User_Name + User_Pass query string'de gidiyor.
 */

if (!defined('YOLZZ_APP')) { die('Forbidden'); }

require_once __DIR__ . '/VehicleProviderInterface.php';
require_once __DIR__ . '/ProviderHttpClient.php';

class RentlineProvider implements VehicleProviderInterface
{
    private array $config;       // api_providers satırı
    private array $authConfig;   // decoded auth_config
    private ProviderHttpClient $http;

    public function __construct(array $providerRow)
    {
        $this->config = $providerRow;
        $this->authConfig = is_string($providerRow['auth_config'] ?? null)
            ? (json_decode($providerRow['auth_config'], true) ?: [])
            : ($providerRow['auth_config'] ?? []);

        $this->http = new ProviderHttpClient([
            'timeout' => 10,
            'connect_timeout' => 5,
            'retries' => 1,
        ]);
    }

    public function getName(): string {
        return $this->config['display_name'] ?: ($this->config['name'] ?? 'Rentline');
    }
    public function getSourceType(): string { return 'api'; }
    public function isTestMode(): bool {
        return !isset($this->config['test_mode']) || (int)$this->config['test_mode'] === 1;
    }

    private function authParams(): array
    {
        return [
            'Key_Hack' => $this->authConfig['key_hack'] ?? '',
            'User_Name' => $this->authConfig['user_name'] ?? '',
            'User_Pass' => $this->authConfig['user_pass'] ?? '',
        ];
    }

    private function endpoint(string $path): string
    {
        $base = rtrim($this->config['base_url'] ?? '', '/');
        $path = ltrim($path, '/');
        return $base . '/' . $path;
    }

    /**
     * Müsait araç sorgu — JsonRez.aspx
     */
    public function searchVehicles(array $params): array
    {
        $pickupExt = $params['pickup_external_id'] ?? null;
        $returnExt = $params['return_external_id'] ?? $pickupExt;

        if (!$pickupExt) {
            return [];
        }

        $p = strtotime($params['pickup_date'] ?? 'now');
        $r = strtotime($params['return_date'] ?? '+1 day');
        if (!$p || !$r) return [];

        $pickupTime = $params['pickup_time'] ?? '10:00';
        $returnTime = $params['return_time'] ?? '10:00';

        // Rentline API field isimleri büyük harf kullanır (PDF'e göre).
        // Lokasyon yanıtı küçük harf dönüyor ama parametreler için büyük harf gerekiyor.
        // Pickup_ID gibi string yerine integer olarak gönder (API "Pickup_ID hatalı" diyorsa
        // muhtemelen tip beklentisi farklı)
        $query = array_merge($this->authParams(), [
            'Pickup_ID' => (int)$pickupExt,
            'Drop_Off_ID' => (int)$returnExt,
            'Pickup_Day' => (int)date('d', $p),
            'Pickup_Month' => (int)date('m', $p),
            'Pickup_Year' => (int)date('Y', $p),
            'Drop_Off_Day' => (int)date('d', $r),
            'Drop_Off_Month' => (int)date('m', $r),
            'Drop_Off_Year' => (int)date('Y', $r),
            'Pickup_Hour' => (int)substr($pickupTime, 0, 2),
            'Pickup_Min' => (int)substr($pickupTime, 3, 2),
            'Drop_Off_Hour' => (int)substr($returnTime, 0, 2),
            'Drop_Off_Min' => (int)substr($returnTime, 3, 2),
            'Currency' => $params['currency'] ?? 'TL',
        ]);

        $url = $this->endpoint($this->config['endpoint_availability'] ?? 'JsonRez.aspx');
        $resp = $this->http->get($url, $query);

        if (!$resp['ok'] || !is_array($resp['json'])) {
            $this->logSync('failed', 'Araç sorgu başarısız: ' . ($resp['error'] ?? "HTTP {$resp['status']}"));
            return [];
        }

        // Hata kontrolü: API {"success":"False","error":"..."} dönüyor
        $items = $resp['json'];
        $first = is_array($items) ? reset($items) : null;
        if (is_array($first) && isset($first['success']) && $first['success'] === 'False') {
            $errMsg = 'Rentline API hatası: ' . ($first['error'] ?? 'bilinmiyor');
            error_log('RentlineProvider searchVehicles: ' . $errMsg);
            $this->logSync('failed', $errMsg);
            return [];
        }

        // Wrapper aç
        if (isset($items['Cars']) && is_array($items['Cars'])) $items = $items['Cars'];
        elseif (isset($items['cars']) && is_array($items['cars'])) $items = $items['cars'];
        elseif (isset($items['Data']) && is_array($items['Data'])) $items = $items['Data'];
        elseif (isset($items['Result']) && is_array($items['Result'])) $items = $items['Result'];

        if (!is_array($items)) return [];

        // Gün sayısı
        $days = max(1, (int)ceil(($r - $p) / 86400));
        $markup = (float)($this->config['price_markup_percent'] ?? 0);

        // Firma eşleştirme cache (cars_park_id → company)
        $companyMap = [];
        $discoveredIds = []; // Yeni keşfedilenler
        try {
            if (function_exists('db') && !empty($this->config['id'])) {
                $rows = db()->fetchAll("
                    SELECT m.external_id, m.external_name, m.rental_company_id,
                           c.name AS company_name, c.logo AS company_logo, c.slug AS company_slug
                    FROM api_company_map m
                    LEFT JOIN rental_companies c ON c.id = m.rental_company_id
                    WHERE m.api_provider_id = ? AND m.is_active = 1
                ", [(int)$this->config['id']]);
                foreach ($rows as $row) {
                    $companyMap[(string)$row['external_id']] = $row;
                }
            }
        } catch (\Throwable $e) {
            // api_company_map tablosu henüz yoksa sessizce devam et
        }

        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;

            // Esnek field arama (Rentline yanıt format'ı kararsız)
            $daily = (float)($this->pickField($item, ['Daily_Rental', 'daily_rental', 'DailyRental', 'dailyRental']) ?? 0);
            if ($daily <= 0) continue;

            if ($markup > 0) {
                $daily = $daily * (1 + $markup / 100);
            }

            $carName = $this->pickField($item, ['Car_Name', 'car_name', 'CarName', 'Name']) ?? '';
            $brand = $this->pickField($item, ['Brand', 'brand']) ?? '';
            $type = $this->pickField($item, ['Type', 'type']) ?? '';
            // brand+type varsa onları kullan (daha temiz), yoksa car_name'i parse et
            if ($brand && $type) {
                $brandName = $brand;
                $modelName = $type;
                $fullName = trim($brand . ' ' . $type);
            } else {
                $brandName = $carName;
                $modelName = $carName;
                $fullName = $carName;
            }

            $carsParkId = $this->pickField($item, ['Cars_Park_ID', 'cars_park_id', 'CarsParkID']);
            $carsParkName = $this->pickField($item, ['Cars_Park_Name', 'cars_park_name', 'CarsParkName']);
            $rezId = $this->pickField($item, ['Rez_ID', 'rez_id', 'RezID']);
            $groupId = $this->pickField($item, ['Group_ID', 'group_id', 'GroupID']);
            $imagePath = $this->pickField($item, ['Image_Path', 'image_path', 'ImagePath', 'image']);
            $sipp = $this->pickField($item, ['SIPP', 'sipp', 'Sipp']);
            $fuel = $this->pickField($item, ['Fuel', 'fuel']);
            $transmission = $this->pickField($item, ['Transmission', 'transmission']);
            $chairs = (int)($this->pickField($item, ['Chairs', 'chairs', 'Seats', 'seats']) ?? 5);
            $bigBags = (int)($this->pickField($item, ['Big_Bags', 'big_bags']) ?? 0);
            $smallBags = (int)($this->pickField($item, ['Small_Bags', 'small_bags']) ?? 0);
            $provision = (float)($this->pickField($item, ['Provision', 'provision']) ?? 0);
            $kmLimit = $this->pickField($item, ['Km_Limit', 'km_limit']);
            $currency = $this->pickField($item, ['Currency', 'currency']) ?? 'TRY';
            $apiDays = (int)($this->pickField($item, ['Days', 'days']) ?? 0);
            $totalRental = (float)($this->pickField($item, ['Total_Rental', 'total_rental']) ?? 0);
            $driverAge = (int)($this->pickField($item, ['Driver_Age', 'driver_age']) ?? 0);
            $licenseAge = (int)($this->pickField($item, ['Driving_License_Age', 'driving_license_age']) ?? 0);

            // Services array'i parse et (Rentline yeni format)
            $servicesMap = [];
            $services = $item['Services'] ?? $item['services'] ?? [];
            if (is_array($services)) {
                foreach ($services as $svc) {
                    $name = $svc['service_name'] ?? '';
                    if ($name) {
                        $servicesMap[$name] = [
                            'title' => $svc['service_title'] ?? $name,
                            'price' => (float)($svc['service_total_price'] ?? 0),
                            'desc' => $svc['service_desc'] ?? '',
                            'calc' => $svc['calc_description'] ?? '',
                        ];
                    }
                }
            }

            // Firma eşleştirme - cars_park_id'ye göre
            $companyInfo = null;
            if ($carsParkId && isset($companyMap[(string)$carsParkId])) {
                $companyInfo = $companyMap[(string)$carsParkId];
            } elseif ($carsParkId && !isset($discoveredIds[(string)$carsParkId])) {
                // Bilinmeyen cars_park_id - sonra DB'ye eklenecek
                $discoveredIds[(string)$carsParkId] = $carsParkName ?? null;
            }

            $result[] = [
                'source' => 'api',
                'provider_id' => (int)$this->config['id'],
                'provider_name' => $this->getName(),
                'source_label' => $this->getName(),
                'external_id' => (string)($carsParkId ?? $rezId ?? ''),
                'cars_park_id' => $carsParkId,
                'local_id' => null,
                'brand' => $brandName,
                'model' => $modelName,
                'full_name' => $fullName,
                'year' => 0,
                'category' => $sipp ?? '',
                'category_id' => null,
                'brand_id' => null,
                'fuel_type' => $fuel ?? '',
                'transmission' => $transmission ?? '',
                'seats' => $chairs,
                'doors' => 4,
                'luggage' => $bigBags + $smallBags,
                'daily_price' => round($daily, 2),
                'currency' => $currency,
                'deposit' => $provision,
                'total_price' => $totalRental > 0 ? round($totalRental, 2) : round($daily * $days, 2),
                'days' => $apiDays > 0 ? $apiDays : $days,
                'image' => $imagePath ? $this->buildImageUrl($imagePath) : '',
                'features' => [
                    'cdw' => isset($servicesMap['CDW']),
                    'scdw' => isset($servicesMap['SCDW']),
                    'lcf' => isset($servicesMap['LCF']),
                    'pai' => isset($servicesMap['PAI']),
                    'km_limit' => $kmLimit,
                ],
                'extra_services' => $servicesMap,
                'office_id' => null,
                'office_name' => null,
                'city_name' => null,
                'pickup_office_name' => '',
                'available' => true,
                'slug' => null,
                'tag' => 'api',
                'discount_percent' => 0,
                'min_age' => $driverAge ?: 21,
                'min_license_year' => $licenseAge ?: 1,
                // Firma eşleştirme (eşleşme yoksa null)
                'rental_company_id' => $companyInfo['rental_company_id'] ?? null,
                'rental_company_name' => $companyInfo['company_name'] ?? null,
                'rental_company_logo' => $companyInfo['company_logo'] ?? null,
                'rental_company_slug' => $companyInfo['company_slug'] ?? null,
                'raw' => $item,
                'group_id' => $groupId,
                'rez_id' => $rezId,
            ];
        }

        // Bilinmeyen cars_park_id'leri otomatik DB'ye kaydet (admin sonra firmasını seçer)
        if (!empty($discoveredIds)) {
            try {
                foreach ($discoveredIds as $extId => $extName) {
                    db()->query(
                        "INSERT IGNORE INTO api_company_map (api_provider_id, external_id, external_name) VALUES (?, ?, ?)",
                        [(int)$this->config['id'], $extId, $extName]
                    );
                }
            } catch (\Throwable $e) {
                // Tablo yoksa veya başka bir sorunsa sessizce geç
            }
        }

        return $result;
    }

    /**
     * Lokasyon listesi — JsonLocations.aspx
     * Esnek parsing: Rentline farklı field isimleri dönebilir
     */
    public function getLocations(): array
    {
        $url = $this->endpoint('JsonLocations.aspx');
        $resp = $this->http->get($url, $this->authParams());

        if (!$resp['ok'] || !is_array($resp['json'])) {
            error_log('Rentline getLocations: yanıt yok veya JSON değil');
            return [];
        }

        $items = $resp['json'];

        // Olası wrapper'ları aç (Rentline bazen sarmalayabilir)
        if (isset($items['Locations']) && is_array($items['Locations'])) $items = $items['Locations'];
        elseif (isset($items['locations']) && is_array($items['locations'])) $items = $items['locations'];
        elseif (isset($items['Data']) && is_array($items['Data'])) $items = $items['Data'];
        elseif (isset($items['data']) && is_array($items['data'])) $items = $items['data'];
        elseif (isset($items['Result']) && is_array($items['Result'])) $items = $items['Result'];

        if (!is_array($items)) {
            error_log('Rentline getLocations: items array değil, keys: ' . implode(',', array_keys($resp['json'])));
            return [];
        }

        // Eğer ilk eleman dizi değilse, $items zaten tek bir lokasyon objesidir, sarmala
        $first = reset($items);
        if (!is_array($first)) {
            $items = [$items];
        }

        $out = [];
        foreach ($items as $loc) {
            if (!is_array($loc)) continue;

            // Esnek field arama — büyük/küçük harf farkını telafi et
            $extId = $this->pickField($loc, ['Location_ID', 'LocationID', 'location_id', 'locationId', 'ID', 'Id', 'id']);
            $name = $this->pickField($loc, ['Location_Name', 'LocationName', 'location_name', 'locationName', 'Name', 'name']);
            $address = $this->pickField($loc, ['Address', 'address', 'Adress', 'adress']);
            $phone = $this->pickField($loc, ['Telephone', 'telephone', 'Phone', 'phone']);
            $email = $this->pickField($loc, ['Mail_Adress', 'Mail_Address', 'mail_adress', 'mail_address', 'Email', 'email']);

            if (empty($extId) || empty($name)) {
                error_log('Rentline location atlandı (eksik id/name): ' . json_encode($loc));
                continue;
            }

            $out[] = [
                'external_id' => (string)$extId,
                'name' => (string)$name,
                'address' => (string)$address,
                'phone' => (string)$phone,
                'email' => (string)$email,
                'is_airport' => stripos($name, 'havaliman') !== false
                             || stripos($name, 'airport') !== false,
                'raw' => $loc,
            ];
        }

        if (empty($out)) {
            // Debug için ilk item'ın anahtarlarını logla
            $sample = reset($items);
            if (is_array($sample)) {
                error_log('Rentline locations: hiçbir field eşleşmedi. Mevcut anahtarlar: ' . implode(',', array_keys($sample)));
            }
        }

        return $out;
    }

    /**
     * Bir array'den birden çok olası field isminden ilk bulunanı döner
     */
    private function pickField(array $arr, array $candidates)
    {
        foreach ($candidates as $key) {
            if (isset($arr[$key]) && $arr[$key] !== '') {
                return $arr[$key];
            }
        }
        return null;
    }

    /**
     * Görsel için tam URL oluştur (varsa image_base_url prefix ekle)
     */
    private function buildImageUrl(string $path): string
    {
        // Zaten http ile başlıyorsa olduğu gibi döndür
        if (preg_match('/^https?:\/\//i', $path)) return $path;

        $base = $this->config['image_base_url'] ?? '';
        if (empty($base)) {
            // Image base URL tanımlı değilse boş döndür (görsel gösterme)
            return '';
        }
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Rezervasyon kaydet — JsonRez_Save.aspx
     */
    public function createBooking(array $bookingData): array
    {
        $url = $this->endpoint($this->config['endpoint_booking'] ?? 'JsonRez_Save.aspx');

        $p = strtotime($bookingData['pickup_date'] ?? 'now');
        $r = strtotime($bookingData['return_date'] ?? '+1 day');

        $payload = array_merge($this->authParams(), [
            'Pickup_ID' => (int)($bookingData['pickup_external_id'] ?? 0),
            'Drop_Off_ID' => (int)($bookingData['return_external_id'] ?? ($bookingData['pickup_external_id'] ?? 0)),
            'Name' => $bookingData['first_name'] ?? '',
            'SurName' => $bookingData['last_name'] ?? '',
            'MobilePhone' => $bookingData['phone'] ?? '',
            'Mail_Adress' => $bookingData['email'] ?? '',
            'Rental_ID' => $bookingData['tc_or_id'] ?? '',
            'Cars_Park_ID' => $bookingData['cars_park_id'] ?? '',
            'Group_ID' => $bookingData['group_id'] ?? '',
            'Rez_ID' => $bookingData['rez_id'] ?? '',
            'Pickup_Day' => (int)date('d', $p),
            'Pickup_Month' => (int)date('m', $p),
            'Pickup_Year' => (int)date('Y', $p),
            'Drop_Off_Day' => (int)date('d', $r),
            'Drop_Off_Month' => (int)date('m', $r),
            'Drop_Off_Year' => (int)date('Y', $r),
            'Pickup_Hour' => (int)substr($bookingData['pickup_time'] ?? '10:00', 0, 2),
            'Pickup_Min' => (int)substr($bookingData['pickup_time'] ?? '10:00', 3, 2),
            'Drop_Off_Hour' => (int)substr($bookingData['return_time'] ?? '10:00', 0, 2),
            'Drop_Off_Min' => (int)substr($bookingData['return_time'] ?? '10:00', 3, 2),
            'Currency' => $bookingData['currency'] ?? 'TL',
            'Flight_Number' => $bookingData['flight_number'] ?? '',
            'Adress' => $bookingData['address'] ?? '',
            'City' => $bookingData['city'] ?? '',
            'Country' => $bookingData['country'] ?? 'Turkey',
            'Your_Rez_ID' => $bookingData['our_booking_code'] ?? '',
            'Your_Rent_Price' => $bookingData['rent_price'] ?? 0,
            'Your_Extra_Price' => $bookingData['extra_price'] ?? 0,
            'Your_Drop_Price' => $bookingData['drop_price'] ?? 0,
            'Payment_Type' => (int)($bookingData['payment_type'] ?? 0),
        ]);

        $resp = $this->http->post($url, $payload);

        // Esnek response parsing
        $json = $resp['json'];
        $first = is_array($json) ? reset($json) : null;

        // Hata kontrolü
        if (is_array($first) && isset($first['success']) && $first['success'] === 'False') {
            return [
                'success' => false,
                'external_id' => null,
                'message' => 'Rentline API hatası: ' . ($first['error'] ?? 'bilinmiyor'),
                'raw' => $json,
                'sent_payload' => $payload,
            ];
        }

        // Başarılı yanıt: rez_id veya ID döner
        $extId = null;
        if (is_array($first)) {
            $extId = $first['rez_id'] ?? $first['Rez_ID'] ?? $first['ID'] ?? $first['id'] ?? null;
            $isSuccess = isset($first['Status']) ? $first['Status'] === 'True'
                       : (isset($first['success']) ? $first['success'] === 'True'
                       : ($extId !== null));
        } else {
            $isSuccess = false;
        }

        return [
            'success' => $resp['ok'] && $isSuccess,
            'external_id' => $extId ? (string)$extId : null,
            'message' => $resp['ok'] ? ($isSuccess ? 'OK' : 'Bilinmeyen yanıt') : ($resp['error'] ?? 'API error'),
            'raw' => $json ?? $resp['body'],
            'sent_payload' => $payload,
        ];
    }

    /**
     * Rezervasyon iptal — JsonCancel.aspx
     */
    public function cancelBooking(string $externalId): array
    {
        $url = $this->endpoint($this->config['endpoint_cancel'] ?? 'JsonCancel.aspx');
        $query = array_merge($this->authParams(), [
            'Rez_ID' => $externalId,
        ]);
        $resp = $this->http->get($url, $query);
        return [
            'success' => $resp['ok'] && !empty($resp['json']['Status']),
            'message' => $resp['ok'] ? 'OK' : ($resp['error'] ?? 'API error'),
            'raw' => $resp['json'] ?? $resp['body'],
        ];
    }

    /**
     * Bağlantı testi
     */
    public function testConnection(): array
    {
        $url = $this->endpoint('JsonLocations.aspx');
        $resp = $this->http->get($url, $this->authParams());

        if ($resp['ok'] && is_array($resp['json'])) {
            $count = 0;
            if (is_array($resp['json'])) $count = count($resp['json']);
            return [
                'success' => true,
                'message' => "Bağlantı OK (süre: {$resp['duration_ms']}ms, lokasyon: $count)",
                'raw' => $resp['json'],
            ];
        }

        return [
            'success' => false,
            'message' => 'Bağlantı başarısız: ' . ($resp['error'] ?? "HTTP {$resp['status']}"),
            'raw' => $resp,
        ];
    }

    private function logSync(string $status, string $message): void
    {
        try {
            db()->update('api_providers', [
                'last_sync_at' => date('Y-m-d H:i:s'),
                'last_sync_status' => $status,
                'last_sync_message' => substr($message, 0, 500),
            ], 'id = :id', ['id' => (int)$this->config['id']]);
        } catch (Throwable $e) {
            error_log('RentlineProvider logSync failed: ' . $e->getMessage());
        }
    }
}
