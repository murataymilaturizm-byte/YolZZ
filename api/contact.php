<?php
define('YOLZZ_APP', true);
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Geçersiz istek.', 405);
}

$name = trim(post('name'));
$email = trim(post('email'));
$phone = trim(post('phone'));
$subject = trim(post('subject'));
$message = trim(post('message'));
$type = post('type', 'general');

if (!$name || !$email || !$message) {
    json_error('Ad, e-posta ve mesaj alanları zorunludur.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Geçerli bir e-posta girin.');
}

// Spam koruması: aynı IP'den son 5 dakikada 3+ mesaj geldiyse blok
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
if ($ip) {
    $recent = db()->fetchColumn(
        "SELECT COUNT(*) FROM contact_messages WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
        [$ip]
    );
    if ($recent >= 3) {
        json_error('Çok fazla istek gönderdiniz. Lütfen biraz bekleyin.', 429);
    }
}

$id = db()->insert('contact_messages', [
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'subject' => $subject,
    'message' => $message,
    'type' => in_array($type, ['general','booking','complaint','corporate']) ? $type : 'general',
    'ip_address' => $ip,
    'status' => 'new'
]);

json_success(['id' => $id], 'Mesajınız alındı!');
