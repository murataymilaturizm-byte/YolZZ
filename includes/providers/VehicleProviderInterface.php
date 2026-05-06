<?php
/**
 * VehicleProviderInterface
 * Tüm araç tedarikçi API'leri bu arayüzü uygular.
 * Lokal (kendi DB) ve uzak API'ler (Rentline vb.) aynı şekilde kullanılır.
 */

if (!defined('YOLZZ_APP')) { die('Forbidden'); }

interface VehicleProviderInterface
{
    /**
     * Tedarikçi adı (log/debug için)
     */
    public function getName(): string;

    /**
     * Tedarikçi kaynağı tipi: 'local' veya 'api'
     */
    public function getSourceType(): string;

    /**
     * Araç arama — en kritik method
     *
     * @param array $params [
     *     'pickup_date' => Y-m-d,
     *     'pickup_time' => H:i,
     *     'return_date' => Y-m-d,
     *     'return_time' => H:i,
     *     'pickup_office_id' => int|null (lokal ofis id),
     *     'pickup_external_id' => string|null (provider tarafı lokasyon id),
     *     'return_office_id' => int|null,
     *     'category_id' => int|null,
     *     'brand_id' => int|null,
     *     'fuel_type' => string|null,
     *     'transmission' => string|null,
     *     'min_price' => float|null,
     *     'max_price' => float|null,
     * ]
     *
     * @return array Standart araç dizisi:
     *   [
     *     'source' => 'local'|'api',
     *     'provider_id' => int|null,
     *     'provider_name' => string,
     *     'external_id' => string|null,
     *     'local_id' => int|null (varsa),
     *     'brand' => string, 'model' => string, 'year' => int,
     *     'category' => string, 'fuel_type' => string, 'transmission' => string,
     *     'seats' => int, 'doors' => int, 'luggage' => int,
     *     'daily_price' => float, 'currency' => string,
     *     'deposit' => float, 'total_price' => float,
     *     'image' => string (url),
     *     'features' => array,
     *     'pickup_office_name' => string,
     *     'available' => bool,
     *   ]
     */
    public function searchVehicles(array $params): array;

    /**
     * Rezervasyon oluştur — yalnızca uzak API provider'larda çalışır.
     * LocalProvider için no-op, sistemin kendi bookings tablosu kullanılır.
     *
     * @return array ['success' => bool, 'external_id' => string|null, 'message' => string, 'raw' => mixed]
     */
    public function createBooking(array $bookingData): array;

    /**
     * Rezervasyon iptal et
     */
    public function cancelBooking(string $externalId): array;

    /**
     * Lokasyon listesini çek (provider tarafındaki)
     */
    public function getLocations(): array;

    /**
     * Bağlantıyı test et
     */
    public function testConnection(): array;
}
