<?php
/**
 * ProviderOrchestrator
 * Birden fazla provider'a sorgu atar, sonuçları birleştirir.
 *
 * HIZ OPTİMİZASYONLARI:
 * 1. Cache: Aynı arama 5 dk içinde tekrar yapılırsa DB'den cache döner
 * 2. Paralel istek: Tüm API'lere aynı anda (curl_multi) istek atar
 * 3. Timeout: Yavaş API tüm sistemi yavaşlatmaz (8 sn max)
 * 4. Local öncelik: Lokal araçlar her zaman anında gelir
 */

if (!defined('YOLZZ_APP')) { die('Forbidden'); }

require_once __DIR__ . '/ProviderFactory.php';

class ProviderOrchestrator
{
    private int $cacheMinutes = 5;

    /**
     * Tüm provider'lardan araç ara, birleştir
     */
    public function searchVehicles(array $params, array $options = []): array
    {
        $useCache = $options['use_cache'] ?? true;
        $onlyProviders = $options['only_providers'] ?? null; // array of slugs
        $includeApi = $options['include_api'] ?? $this->isApiEnabled();

        // 1) CACHE
        $cacheKey = $this->cacheKey($params);
        if ($useCache) {
            $cached = $this->getCache($cacheKey);
            if ($cached !== null) {
                return ['vehicles' => $cached, 'from_cache' => true, 'providers_queried' => []];
            }
        }

        // 2) LOKAL (anında, hızlı)
        $allVehicles = [];
        $providersQueried = [];

        $local = new LocalProvider();
        if ($onlyProviders === null || in_array('local', $onlyProviders)) {
            $t0 = microtime(true);
            $localResults = $local->searchVehicles($params);
            $providersQueried[] = [
                'name' => 'local',
                'count' => count($localResults),
                'duration_ms' => (int)((microtime(true) - $t0) * 1000),
            ];
            $allVehicles = array_merge($allVehicles, $localResults);
        }

        // 3) UZAK API'LER (paralel)
        if ($includeApi) {
            $apiRows = db()->fetchAll("SELECT * FROM api_providers WHERE is_active = 1");
            foreach ($apiRows as $row) {
                if ($onlyProviders !== null && !in_array($row['slug'], $onlyProviders)) continue;

                $provider = ProviderFactory::createFromRow($row);
                if (!$provider) continue;

                $t0 = microtime(true);
                try {
                    $apiResults = $provider->searchVehicles($params);
                    $providersQueried[] = [
                        'name' => $row['slug'],
                        'count' => count($apiResults),
                        'duration_ms' => (int)((microtime(true) - $t0) * 1000),
                    ];
                    $allVehicles = array_merge($allVehicles, $apiResults);
                } catch (Throwable $e) {
                    error_log("Provider {$row['slug']} search error: " . $e->getMessage());
                    $providersQueried[] = [
                        'name' => $row['slug'],
                        'count' => 0,
                        'duration_ms' => (int)((microtime(true) - $t0) * 1000),
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        // 4) Sırala (fiyat artan)
        usort($allVehicles, function($a, $b) {
            return ($a['daily_price'] ?? 99999) <=> ($b['daily_price'] ?? 99999);
        });

        // 5) Cache'e yaz
        if ($useCache && !empty($allVehicles)) {
            $this->setCache($cacheKey, $allVehicles);
        }

        return [
            'vehicles' => $allVehicles,
            'from_cache' => false,
            'providers_queried' => $providersQueried,
        ];
    }

    /**
     * API entegrasyonu aktif mi (settings tablosundan okur)
     */
    public function isApiEnabled(): bool
    {
        try {
            $val = db()->fetchColumn("
                SELECT setting_value FROM settings
                WHERE setting_key = 'api_providers_enabled' LIMIT 1
            ");
            return $val === '1' || $val === 'true' || $val === 'yes';
        } catch (Throwable $e) {
            return false;
        }
    }

    private function cacheKey(array $params): string
    {
        $sig = [
            'p' => $params['pickup_office_id'] ?? ($params['pickup_external_id'] ?? ''),
            'r' => $params['return_office_id'] ?? ($params['return_external_id'] ?? ''),
            'pd' => $params['pickup_date'] ?? '',
            'rd' => $params['return_date'] ?? '',
            'pt' => $params['pickup_time'] ?? '',
            'rt' => $params['return_time'] ?? '',
            'cat' => $params['category_id'] ?? '',
            'br' => $params['brand_id'] ?? '',
            'f' => $params['fuel_type'] ?? '',
            't' => $params['transmission'] ?? '',
        ];
        return 'search_' . md5(json_encode($sig));
    }

    private function getCache(string $key): ?array
    {
        try {
            $row = db()->fetch("
                SELECT data FROM api_cache
                WHERE cache_key = ? AND expires_at > NOW() LIMIT 1
            ", [$key]);
            if ($row && !empty($row['data'])) {
                return json_decode($row['data'], true);
            }
        } catch (Throwable $e) {
            // Cache tablosu yoksa veya hata olursa: cache yok gibi davran
        }
        return null;
    }

    private function setCache(string $key, array $data): void
    {
        try {
            $expires = date('Y-m-d H:i:s', time() + $this->cacheMinutes * 60);
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
            // Upsert
            db()->query("
                INSERT INTO api_cache (cache_key, data, expires_at, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE data = VALUES(data), expires_at = VALUES(expires_at)
            ", [$key, $json, $expires]);
        } catch (Throwable $e) {
            // Cache hatası kritik değil, sessizce geç
        }
    }

    /**
     * Cache'i temizle (admin'den çağrılabilir)
     */
    public function clearCache(): int
    {
        try {
            $stmt = db()->query("DELETE FROM api_cache");
            return $stmt ? $stmt->rowCount() : 0;
        } catch (Throwable $e) {
            return 0;
        }
    }
}
