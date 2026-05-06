<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// Zaten giriş yapmışsa dashboard'a yönlendir
if (is_logged_in()) {
    redirect(admin_url('index.php'));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = post('email');
    $password = post('password');

    if (!$email || !$password) {
        $error = 'E-posta ve şifre zorunludur.';
    } else {
        $user = db()->fetch(
            "SELECT * FROM admin_users WHERE email = ? AND is_active = 1 LIMIT 1",
            [$email]
        );

        if ($user && password_verify($password, $user['password'])) {
            // Başarılı giriş
            $_SESSION['admin_user_id'] = $user['id'];
            session_regenerate_id(true);

            db()->update('admin_users', [
                'last_login_at' => date('Y-m-d H:i:s'),
                'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? null
            ], 'id = :id', ['id' => $user['id']]);

            log_activity('login', 'auth', 'Panel girişi yapıldı');

            redirect(admin_url('index.php'));
        } else {
            $error = 'E-posta veya şifre hatalı.';
            // Kötü amaçlı brute-force yavaşlatma
            usleep(500000);
        }
    }
}
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Giriş — Yolzz</title>
<link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
<link rel="alternate icon" href="<?= url('assets/img/favicon.ico') ?>">
<meta name="robots" content="noindex, nofollow">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --brand: #1d71b8; --brand-dark: #155788; --brand-deep: #0A1F33;
    --accent: #e94e1b; --accent-light: #F56F3C;
    --text: #3D5269; --border: #DCE4EE;
  }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #0A1F33 0%, #1d71b8 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    position: relative;
    overflow: hidden;
  }
  body::before {
    content: '';
    position: absolute;
    top: -20%; right: -10%;
    width: 600px; height: 600px;
    background: radial-gradient(circle, rgba(233,78,27,0.2), transparent 60%);
    filter: blur(60px);
  }
  body::after {
    content: '';
    position: absolute;
    bottom: -20%; left: -10%;
    width: 500px; height: 500px;
    background: radial-gradient(circle, rgba(58,142,207,0.25), transparent 60%);
    filter: blur(60px);
  }

  .login-box {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 25px 60px rgba(0,0,0,0.3);
    width: 100%;
    max-width: 420px;
    padding: 40px;
    position: relative;
    z-index: 1;
  }
  .login-logo {
    text-align: center;
    margin-bottom: 28px;
  }
  .logo-mark {
    display: inline-block;
    margin-bottom: 6px;
  }
  .logo-mark svg { width: 200px; height: auto; display: block; margin: 0 auto; }
  .logo-sub {
    color: var(--text);
    font-size: 13px;
    margin-top: 4px;
  }

  h1 {
    font-size: 20px;
    color: var(--brand-deep);
    margin-bottom: 4px;
    font-weight: 700;
  }
  .subtitle {
    color: var(--text);
    font-size: 14px;
    margin-bottom: 24px;
  }

  .form-group { margin-bottom: 16px; }
  label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--brand-deep);
    margin-bottom: 6px;
  }
  input {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 14px;
    font-family: inherit;
    outline: none;
    transition: all 0.2s;
  }
  input:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(29,113,184,0.1);
  }

  .btn-login {
    width: 100%;
    background: var(--accent);
    color: #fff;
    border: none;
    padding: 13px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 15px;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 8px;
  }
  .btn-login:hover {
    background: #C23E0F;
    transform: translateY(-1px);
    box-shadow: 0 8px 20px rgba(233,78,27,0.3);
  }

  .error {
    background: #FEE;
    color: #C00;
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 13px;
    margin-bottom: 16px;
    border-left: 3px solid #C00;
  }

  .forgot {
    text-align: center;
    margin-top: 20px;
    font-size: 13px;
    color: var(--text);
  }
  .forgot a {
    color: var(--brand);
    text-decoration: none;
    font-weight: 500;
  }

  .back-link {
    text-align: center;
    margin-top: 16px;
    font-size: 13px;
  }
  .back-link a {
    color: #fff;
    opacity: 0.8;
    text-decoration: none;
  }
  .back-link a:hover { opacity: 1; }
</style>
</head>
<body>

<div>
<div class="login-box">
  <div class="login-logo">
    <div class="logo-mark">
      <svg viewBox="0 0 260 48" xmlns="http://www.w3.org/2000/svg" aria-label="Yolzz">
        <text x="0" y="36" font-family="Poppins, sans-serif" font-weight="800" font-size="34" letter-spacing="-0.8" fill="#1d71b8">Rental</text>
        <circle cx="15" cy="29" r="2.6" fill="#e94e1b"/>
        <text x="130" y="36" font-family="Poppins, sans-serif" font-weight="800" font-size="34" letter-spacing="-0.8" fill="#e94e1b">carzz</text>
      </svg>
    </div>
    <div class="logo-sub">Yönetim Paneli</div>
  </div>

  <h1>Hoş geldiniz 👋</h1>
  <p class="subtitle">Devam etmek için hesabınıza giriş yapın</p>

  <?php if ($error): ?>
    <div class="error">⚠ <?= e($error) ?></div>
  <?php endif ?>

  <form method="POST" autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-group">
      <label>E-posta</label>
      <input type="email" name="email" value="<?= e(post('email')) ?>" required autofocus>
    </div>
    <div class="form-group">
      <label>Şifre</label>
      <input type="password" name="password" required>
    </div>
    <button type="submit" class="btn-login">Giriş Yap</button>
  </form>

  <div class="forgot">
    <a href="#">Şifremi unuttum</a>
  </div>
</div>

<div class="back-link">
  <a href="<?= SITE_URL ?>">← Site'ye geri dön</a>
</div>
</div>

</body>
</html>
