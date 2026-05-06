<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) {
    log_activity('logout', 'auth', 'Panel çıkışı');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

redirect(admin_url('login.php'));
