<?php
/**
 * ============================================================
 * Bootstrap — Tüm sayfalar bu dosyayı include eder
 * ============================================================
 */

if (!defined('YOLZZ_APP')) {
    define('YOLZZ_APP', true);
}

error_reporting(E_ALL);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

// Prodüksiyonda hataları gizle
if (defined('APP_ENV') && APP_ENV === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

    // Custom error handler: Stack trace'lerde sadece dosya adı göster
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        $file = basename($errfile);
        error_log("[$errno] $errstr in $file:$errline");
        return false; // Varsayılan handler'ı çalıştır
    });

    // Fatal error'lar için shutdown function
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $file = basename($error['file']);
            error_log("Fatal error: {$error['message']} in $file:{$error['line']}");
        }
    });
} else {
    ini_set('display_errors', '1');
}

// Zaman dilimi
if (defined('APP_TIMEZONE')) {
    date_default_timezone_set(APP_TIMEZONE);
}

// Session başlat
if (session_status() === PHP_SESSION_NONE) {
    if (defined('SESSION_NAME')) {
        session_name(SESSION_NAME);
    }
    session_set_cookie_params([
        'lifetime' => defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 7200,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Dil değiştirme (?lang=tr|en)
if (isset($_GET['lang']) && in_array($_GET['lang'], ['tr', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
