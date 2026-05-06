<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// DEBUG: Hatayı doğrudan JSON yanıtta görmek için (geçici)
// SORUN ÇÖZÜLÜNCE ?debug=1 olmadan gayet normal mesaj döner
$debug = isset($_GET['debug']) || isset($_POST['debug']);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Geçersiz istek.', 405);
    }

    $email = trim((string)post('email', ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Geçerli bir e-posta adresi girin.');
    }

    // Tablo var mı kontrol et (yoksa net hata ver)
    try {
        db()->fetch("SELECT 1 FROM newsletter_subscribers LIMIT 1");
    } catch (Throwable $tableErr) {
        error_log('Newsletter: tablo erişim hatası: ' . $tableErr->getMessage());
        json_error(
            $debug
                ? 'TABLO HATASI: ' . $tableErr->getMessage()
                : 'Abonelik sistemi geçici olarak kullanılamıyor. Lütfen daha sonra deneyin.',
            500
        );
    }

    // IP adresi - çok uzun IPv6 adreslerinde kısaltma
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    if ($ip && strlen($ip) > 45) {
        $ip = substr($ip, 0, 45);
    }

    // Dil
    $lang = function_exists('current_lang') ? current_lang() : 'tr';
    if (strlen($lang) > 2) $lang = substr($lang, 0, 2);

    // Hangi kolonların mevcut olduğunu tespit et (güvenli insert için)
    $cols = db()->fetchAll("SHOW COLUMNS FROM newsletter_subscribers");
    $colNames = array_column($cols, 'Field');

    // Zaten abone mi?
    $existing = db()->fetch("SELECT * FROM newsletter_subscribers WHERE email = ?", [$email]);

    if ($existing) {
        $isActiveField = in_array('is_active', $colNames) ? 'is_active' : null;
        if ($isActiveField && (int)$existing[$isActiveField] === 1) {
            json_error('Bu e-posta zaten listemize abone. Tekrar eklemeye gerek yok. 🎉');
        } else {
            // Kolonlara göre güvenli update
            $updateData = [];
            if (in_array('is_active', $colNames))      $updateData['is_active'] = 1;
            if (in_array('subscribed_at', $colNames))  $updateData['subscribed_at'] = date('Y-m-d H:i:s');
            if (in_array('unsubscribed_at', $colNames)) $updateData['unsubscribed_at'] = null;
            if (in_array('lang', $colNames))           $updateData['lang'] = $lang;
            if (in_array('ip_address', $colNames))     $updateData['ip_address'] = $ip;

            if (!empty($updateData)) {
                db()->update('newsletter_subscribers', $updateData, 'id = :id', ['id' => $existing['id']]);
            }
            json_success([], 'Aboneliğiniz yenilendi. Hoş geldiniz!');
        }
    }

    // Insert — sadece var olan kolonları kullan
    $insertData = ['email' => $email];
    if (in_array('lang', $colNames))       $insertData['lang'] = $lang;
    if (in_array('ip_address', $colNames)) $insertData['ip_address'] = $ip;
    if (in_array('is_active', $colNames))  $insertData['is_active'] = 1;

    db()->insert('newsletter_subscribers', $insertData);

    json_success([], 'Başarıyla abone oldunuz! Kampanyalardan ilk siz haberdar olacaksınız. 🎉');

} catch (Throwable $e) {
    // Detaylı hata log'u
    error_log('Newsletter API hatası: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());

    $msg = $e->getMessage();

    // Unique constraint
    if (stripos($msg, 'Duplicate') !== false || stripos($msg, 'UNIQUE') !== false) {
        json_error('Bu e-posta zaten abone.', 400);
    }

    // Debug modda gerçek hatayı göster
    if ($debug) {
        json_error('HATA: ' . $msg . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')', 500);
    }

    json_error('Abone olma işlemi sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyin.', 500);
}
