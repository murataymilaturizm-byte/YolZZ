<?php
/**
 * ============================================================
 * ApiProvider — Dış API Entegrasyon Motoru
 * ============================================================
 * Bu sınıf, admin panelden eklenen herhangi bir API'den
 * araç verilerini çekip veritabanına yazar.
 *
 * Field Mapping sistemi sayesinde farklı JSON yapıları
 * ortak bir şemaya eşlenir.
 *
 * Örnek field_mapping:
 * {
 *   "list_path": "data.vehicles",       // Dizi yolu
 *   "id": "vehicle_id",
 *   "brand": "make",
 *   "model": "model",
 *   "year": "year",
 *   "fuel_type": "fuel",
 *   "transmission": "gearbox",
 *   "seats": "passenger_count",
 *   "daily_price": "price.daily",
 *   "image": "images[0].url",
 *   "features": "features"
 * }
 * ============================================================
 */

class ApiProvider
{
    private array $provider;

    public function __construct(array $provider)
    {
        $this->provider = $provider;
    }

    /** ID'ye göre provider yükler */
    public static function load(int $id): ?ApiProvider
    {
        $row = db()->fetch("SELECT * FROM api_providers WHERE id = ?", [$id]);
        return $row ? new self($row) : null;
    }

    /** Aktif tüm provider'ları döner */
    public static function allActive(): array
    {
        return db()->fetchAll("SELECT * FROM api_providers WHERE is_active = 1 ORDER BY priority DESC");
    }

    /** Bağlantıyı test eder */
    public function testConnection(): array
    {
        try {
            $endpoint = $this->provider['endpoint_vehicles'] ?? '';
            if (!$endpoint) {
                return ['success' => false, 'message' => 'Araç endpoint tanımlı değil'];
            }
            $response = $this->makeRequest($endpoint, 'GET');
            return [
                'success' => true,
                'message' => 'Bağlantı başarılı',
                'http_code' => $response['http_code'],
                'sample' => is_array($response['data']) ? array_slice($response['data'], 0, 1) : null
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Araçları API'den çekip DB'ye yazar */
    public function syncVehicles(): array
    {
        $startTime = microtime(true);
        $stats = ['added' => 0, 'updated' => 0, 'removed' => 0, 'errors' => 0];

        try {
            $endpoint = $this->provider['endpoint_vehicles'];
            if (!$endpoint) {
                throw new Exception('Araç endpoint tanımlı değil');
            }

            $response = $this->makeRequest($endpoint, 'GET');
            $mapping = json_decode($this->provider['field_mapping'] ?? '{}', true) ?: [];
            $listPath = $mapping['list_path'] ?? '';

            // Liste yolunu takip et
            $vehicles = $listPath
                ? $this->getByPath($response['data'], $listPath)
                : $response['data'];

            if (!is_array($vehicles)) {
                throw new Exception('API yanıtı beklenen dizi formatında değil');
            }

            $externalIds = [];

            foreach ($vehicles as $item) {
                try {
                    $mapped = $this->mapFields($item, $mapping);
                    if (empty($mapped['external_id'])) continue;

                    $externalIds[] = $mapped['external_id'];

                    $result = $this->saveVehicle($mapped);
                    if ($result === 'added')   $stats['added']++;
                    if ($result === 'updated') $stats['updated']++;
                } catch (Throwable $e) {
                    $stats['errors']++;
                }
            }

            // Mevcut olmayan araçları pasife çek (silmek yerine)
            if (!empty($externalIds)) {
                $placeholders = implode(',', array_fill(0, count($externalIds), '?'));
                $params = array_merge([$this->provider['id']], $externalIds);
                $affected = db()->query(
                    "UPDATE vehicles SET status = 'inactive'
                     WHERE api_provider_id = ? AND external_id NOT IN ($placeholders)",
                    $params
                )->rowCount();
                $stats['removed'] = $affected;
            }

            $duration = (int)((microtime(true) - $startTime) * 1000);

            // Log kaydı
            db()->insert('api_sync_logs', [
                'api_provider_id' => $this->provider['id'],
                'sync_type'       => 'vehicles',
                'status'          => $stats['errors'] > 0 ? 'partial' : 'success',
                'vehicles_added'  => $stats['added'],
                'vehicles_updated'=> $stats['updated'],
                'vehicles_removed'=> $stats['removed'],
                'duration_ms'     => $duration
            ]);

            // Provider durumunu güncelle
            db()->update('api_providers', [
                'last_sync_at'          => date('Y-m-d H:i:s'),
                'last_sync_status'      => $stats['errors'] > 0 ? 'partial' : 'success',
                'last_sync_message'     => "Eklendi: {$stats['added']}, Güncellendi: {$stats['updated']}, Pasif: {$stats['removed']}",
                'total_vehicles_synced' => count($externalIds)
            ], 'id = :id', ['id' => $this->provider['id']]);

            return ['success' => true, 'stats' => $stats, 'duration_ms' => $duration];

        } catch (Throwable $e) {
            $duration = (int)((microtime(true) - $startTime) * 1000);

            db()->insert('api_sync_logs', [
                'api_provider_id' => $this->provider['id'],
                'sync_type'       => 'vehicles',
                'status'          => 'failed',
                'error_message'   => $e->getMessage(),
                'duration_ms'     => $duration
            ]);

            db()->update('api_providers', [
                'last_sync_at'      => date('Y-m-d H:i:s'),
                'last_sync_status'  => 'failed',
                'last_sync_message' => $e->getMessage()
            ], 'id = :id', ['id' => $this->provider['id']]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Araç verisini DB'ye kaydet/güncelle */
    private function saveVehicle(array $mapped): string
    {
        // Marka eşleştirmesi (varsa kullan, yoksa oluştur)
        $brandId = $this->findOrCreateBrand($mapped['brand'] ?? 'Diğer');
        $categoryId = $this->findOrCreateCategory($mapped['category'] ?? 'Ekonomik');

        // Fiyat markup uygula
        $markup = (float)($this->provider['price_markup_percent'] ?? 0);
        $dailyPrice = (float)($mapped['daily_price'] ?? 0);
        if ($markup > 0) {
            $dailyPrice = $dailyPrice * (1 + $markup / 100);
        }

        $data = [
            'source_type'      => 'api',
            'api_provider_id'  => $this->provider['id'],
            'external_id'      => $mapped['external_id'],
            'brand_id'         => $brandId,
            'category_id'      => $categoryId,
            'model'            => $mapped['model'] ?? 'Bilinmeyen',
            'slug'             => slugify(($mapped['brand'] ?? '') . '-' . ($mapped['model'] ?? '') . '-' . $mapped['external_id']),
            'year'             => (int)($mapped['year'] ?? date('Y')),
            'fuel_type'        => $this->normalizeFuel($mapped['fuel_type'] ?? 'Benzin'),
            'transmission'     => $this->normalizeTransmission($mapped['transmission'] ?? 'Manuel'),
            'seats'            => (int)($mapped['seats'] ?? 5),
            'luggage'          => (int)($mapped['luggage'] ?? 2),
            'daily_price'      => $dailyPrice,
            'currency'         => $this->provider['default_currency'] ?? 'TRY',
            'main_image'       => $mapped['image'] ?? null,
            'features'         => isset($mapped['features']) ? json_encode($mapped['features'], JSON_UNESCAPED_UNICODE) : null,
            'status'           => 'active'
        ];

        // Mevcut var mı?
        $existing = db()->fetch(
            "SELECT id FROM vehicles WHERE api_provider_id = ? AND external_id = ?",
            [$this->provider['id'], $mapped['external_id']]
        );

        if ($existing) {
            db()->update('vehicles', $data, 'id = :id', ['id' => $existing['id']]);
            return 'updated';
        } else {
            db()->insert('vehicles', $data);
            return 'added';
        }
    }

    /** Field mapping uygula */
    private function mapFields(array $item, array $mapping): array
    {
        $result = [];
        foreach ($mapping as $ourField => $theirPath) {
            if ($ourField === 'list_path') continue;
            if ($ourField === 'id') {
                $result['external_id'] = (string)$this->getByPath($item, $theirPath);
            } else {
                $result[$ourField] = $this->getByPath($item, $theirPath);
            }
        }
        return $result;
    }

    /**
     * JSON yolunda veri çek — "a.b.c" veya "a.b[0].c"
     */
    private function getByPath(mixed $data, string $path): mixed
    {
        if (!$path) return $data;
        // a.b[0].c → a, b, 0, c
        $path = preg_replace('/\[(\d+)\]/', '.$1', $path);
        $parts = explode('.', $path);
        foreach ($parts as $part) {
            if (is_array($data) && array_key_exists($part, $data)) {
                $data = $data[$part];
            } else {
                return null;
            }
        }
        return $data;
    }

    /** HTTP isteği */
    private function makeRequest(string $endpoint, string $method = 'GET', array $body = null): array
    {
        $url = rtrim($this->provider['base_url'], '/') . '/' . ltrim($endpoint, '/');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $headers = ['Accept: application/json', 'Content-Type: application/json'];

        // Custom headers
        $custom = json_decode($this->provider['custom_headers'] ?? '{}', true) ?: [];
        foreach ($custom as $k => $v) {
            $headers[] = "$k: $v";
        }

        // Auth
        $authConfig = json_decode($this->provider['auth_config'] ?? '{}', true) ?: [];
        switch ($this->provider['auth_type']) {
            case 'api_key':
                $keyName = $authConfig['header_name'] ?? 'X-API-Key';
                $headers[] = $keyName . ': ' . ($authConfig['api_key'] ?? '');
                break;
            case 'bearer':
                $headers[] = 'Authorization: Bearer ' . ($authConfig['token'] ?? '');
                break;
            case 'basic':
                curl_setopt($ch, CURLOPT_USERPWD, ($authConfig['username'] ?? '') . ':' . ($authConfig['password'] ?? ''));
                break;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('CURL hatası: ' . $error);
        }

        if ($httpCode >= 400) {
            throw new Exception("HTTP $httpCode - Response: " . substr($response, 0, 500));
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Geçersiz JSON yanıtı');
        }

        return ['http_code' => $httpCode, 'data' => $data];
    }

    // --- Normalizasyon yardımcıları ---

    private function normalizeFuel(string $fuel): string
    {
        $fuel = mb_strtolower($fuel, 'UTF-8');
        $map = [
            'petrol' => 'Benzin', 'gasoline' => 'Benzin', 'benzin' => 'Benzin',
            'diesel' => 'Dizel', 'dizel' => 'Dizel',
            'lpg' => 'LPG',
            'electric' => 'Elektrik', 'elektrik' => 'Elektrik', 'ev' => 'Elektrik',
            'hybrid' => 'Hibrit', 'hibrit' => 'Hibrit'
        ];
        foreach ($map as $k => $v) {
            if (str_contains($fuel, $k)) return $v;
        }
        return 'Benzin';
    }

    private function normalizeTransmission(string $trans): string
    {
        $t = mb_strtolower($trans, 'UTF-8');
        if (str_contains($t, 'auto') || str_contains($t, 'otomatik')) return 'Otomatik';
        if (str_contains($t, 'semi') || str_contains($t, 'yarı')) return 'Yarı Otomatik';
        return 'Manuel';
    }

    private function findOrCreateBrand(string $name): int
    {
        $slug = slugify($name);
        $brand = db()->fetch("SELECT id FROM vehicle_brands WHERE slug = ?", [$slug]);
        if ($brand) return (int)$brand['id'];
        return db()->insert('vehicle_brands', ['name' => $name, 'slug' => $slug]);
    }

    private function findOrCreateCategory(string $name): int
    {
        $slug = slugify($name);
        $cat = db()->fetch("SELECT id FROM vehicle_categories WHERE slug = ?", [$slug]);
        if ($cat) return (int)$cat['id'];
        return db()->insert('vehicle_categories', [
            'name_tr' => $name, 'name_en' => $name, 'slug' => $slug
        ]);
    }
}
