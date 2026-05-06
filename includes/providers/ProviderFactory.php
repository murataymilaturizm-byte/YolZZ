<?php
/**
 * ProviderFactory
 * DB'deki api_providers kaydına göre doğru provider sınıfını yükler.
 */

if (!defined('YOLZZ_APP')) { die('Forbidden'); }

require_once __DIR__ . '/LocalProvider.php';
require_once __DIR__ . '/RentlineProvider.php';

class ProviderFactory
{
    /**
     * Slug'a göre provider yükle
     */
    public static function createFromSlug(string $slug): ?VehicleProviderInterface
    {
        if ($slug === 'local') {
            return new LocalProvider();
        }
        $row = db()->fetch("SELECT * FROM api_providers WHERE slug = ? AND is_active = 1", [$slug]);
        return $row ? self::createFromRow($row) : null;
    }

    /**
     * ID'ye göre provider yükle
     */
    public static function createFromId(int $id): ?VehicleProviderInterface
    {
        $row = db()->fetch("SELECT * FROM api_providers WHERE id = ?", [$id]);
        return $row ? self::createFromRow($row) : null;
    }

    /**
     * DB satırına göre uygun provider sınıfını oluştur
     */
    public static function createFromRow(array $row): ?VehicleProviderInterface
    {
        $slug = strtolower($row['slug'] ?? '');
        $providerType = strtolower($row['provider_type'] ?? 'custom');

        // Önce slug kontrolü, sonra type
        if (str_contains($slug, 'rentline') || str_contains($slug, 'trvrac') || $providerType === 'rentline') {
            return new RentlineProvider($row);
        }

        // Diğer tedarikçiler eklendikçe buraya case ekle:
        // if ($providerType === 'amadeus') return new AmadeusProvider($row);
        // if ($providerType === 'sixt') return new SixtProvider($row);

        return null;
    }

    /**
     * Tüm aktif provider'ları döndür (Local dahil)
     */
    public static function getAllActive(): array
    {
        $providers = [new LocalProvider()];

        $rows = db()->fetchAll("SELECT * FROM api_providers WHERE is_active = 1 ORDER BY name");
        foreach ($rows as $row) {
            $p = self::createFromRow($row);
            if ($p) $providers[] = $p;
        }
        return $providers;
    }
}
