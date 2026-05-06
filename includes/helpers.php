<?php
/**
 * ============================================================
 * Helpers — Küresel yardımcı fonksiyonlar
 * ============================================================
 */

// --- GÜVENLİK ------------------------------------------------

/** HTML escape */
function e(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** CSRF token üret */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** CSRF token doğrula */
function csrf_verify(?string $token): bool
{
    return !empty($_SESSION['csrf_token']) &&
           !empty($token) &&
           hash_equals($_SESSION['csrf_token'], $token);
}

/** CSRF alanını HTML olarak basar */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

// --- STRING --------------------------------------------------

/** Türkçe karakterleri slug'a çevirir */
function slugify(string $text): string
{
    $tr = ['ç','Ç','ğ','Ğ','ı','I','İ','i','ö','Ö','ş','Ş','ü','Ü'];
    $en = ['c','c','g','g','i','i','i','i','o','o','s','s','u','u'];
    $text = str_replace($tr, $en, $text);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('~[^a-z0-9]+~', '-', $text);
    $text = trim($text, '-');
    return $text ?: 'n-a';
}

/** Sayıyı TL formatına çevirir */
function tl(float $amount, bool $symbol = true): string
{
    $formatted = number_format($amount, 2, ',', '.');
    return $symbol ? '₺' . $formatted : $formatted;
}

/** Metni kısaltır */
function str_excerpt(?string $text, int $len = 150): string
{
    if (!$text) return '';
    $text = strip_tags($text);
    if (mb_strlen($text) <= $len) return $text;
    return mb_substr($text, 0, $len) . '…';
}

// --- TARİH ---------------------------------------------------

/** Tarihi Türkçe formatta gösterir */
function tr_date(?string $date, bool $withTime = false): string
{
    if (!$date) return '-';
    $ts = strtotime($date);
    if (!$ts) return '-';

    $months = ['Oca','Şub','Mar','Nis','May','Haz','Tem','Ağu','Eyl','Eki','Kas','Ara'];
    $d = date('j', $ts);
    $m = $months[(int)date('n', $ts) - 1];
    $y = date('Y', $ts);

    $result = "$d $m $y";
    if ($withTime) {
        $result .= ', ' . date('H:i', $ts);
    }
    return $result;
}

/** İki tarih arası gün sayısı */
function date_diff_days(string $start, string $end): int
{
    $s = strtotime($start);
    $e = strtotime($end);
    if (!$s || !$e || $e <= $s) return 0;
    return (int)ceil(($e - $s) / 86400);
}

// --- YÖNLENDİRME & URL ---------------------------------------

/** Belirli URL'e yönlendirir */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Geri yönlendirir */
function redirect_back(): void
{
    $ref = $_SERVER['HTTP_REFERER'] ?? SITE_URL;
    redirect($ref);
}

/** URL oluşturur */
function url(string $path = ''): string
{
    return SITE_URL . '/' . ltrim($path, '/');
}

/** Admin URL */
function admin_url(string $path = ''): string
{
    return ADMIN_URL . '/' . ltrim($path, '/');
}

/** Upload URL */
function upload_url(?string $file, string $fallback = ''): string
{
    if (!$file) {
        return $fallback ? url($fallback) : '';
    }
    if (str_starts_with($file, 'http')) return $file;
    $file = ltrim($file, '/');
    $url = UPLOADS_URL . '/' . $file;
    // Cache busting: dosya modifiye zamanını ekle (sadece uploads/ içi)
    $path = UPLOADS_PATH . '/' . $file;
    if (file_exists($path)) {
        $url .= '?v=' . filemtime($path);
    }
    return $url;
}

// --- FLASH MESAJLAR ------------------------------------------

function flash(string $key, $value = null)
{
    if ($value === null) {
        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }
    $_SESSION['_flash'][$key] = $value;
}

function flash_success(string $msg): void { flash('success', $msg); }
function flash_error(string $msg): void { flash('error', $msg); }
function flash_warning(string $msg): void { flash('warning', $msg); }
function flash_info(string $msg): void { flash('info', $msg); }

/** Tüm flash mesajlarını HTML olarak basar */
function render_flashes(): string
{
    $types = ['success' => '✓', 'error' => '✕', 'warning' => '⚠', 'info' => 'ℹ'];
    $html = '';
    foreach ($types as $type => $icon) {
        $msg = flash($type);
        if ($msg) {
            $html .= '<div class="flash flash-' . $type . '"><span class="flash-icon">' . $icon . '</span> ' . e($msg) . '</div>';
        }
    }
    return $html;
}

/** Tüm flash mesajlarını dizi olarak al (frontend için) */
function flash_get_all(): array
{
    $out = [];
    foreach (['success', 'error', 'warning', 'info'] as $type) {
        $msg = flash($type);
        if ($msg) $out[] = ['type' => $type, 'message' => $msg];
    }
    return $out;
}

/** Kayıt dizisinden çok dilli alan değeri al. $row['title_tr'] veya ['title_en'] */
function lang_field_value(array $row, string $field, ?string $lang = null): string
{
    $lang = $lang ?: current_lang();
    $key = $field . '_' . $lang;
    if (!empty($row[$key])) return $row[$key];
    // fallback
    if (!empty($row[$field . '_tr'])) return $row[$field . '_tr'];
    if (!empty($row[$field])) return $row[$field];
    return '';
}

// --- INPUT ---------------------------------------------------

/** $_POST değeri al (temiz) */
function post(string $key, $default = '')
{
    $val = $_POST[$key] ?? $default;
    if (is_string($val)) $val = trim($val);
    return $val;
}

/** $_GET değeri al (temiz) */
function get(string $key, $default = '')
{
    $val = $_GET[$key] ?? $default;
    if (is_string($val)) $val = trim($val);
    return $val;
}

/** Eski form değeri (validation sonrası) */
function old(string $key, $default = ''): string
{
    $val = $_SESSION['_old'][$key] ?? $default;
    return is_string($val) ? e($val) : '';
}

/** Formdan gelen değeri eski olarak sakla */
function save_old(array $data): void
{
    $_SESSION['_old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

// --- AYARLAR -------------------------------------------------

/** Site ayarı oku (cache'li) */
function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        try {
            $rows = db()->fetchAll("SELECT setting_key, setting_value, setting_type FROM settings");
            $cache = [];
            foreach ($rows as $row) {
                $val = $row['setting_value'];
                switch ($row['setting_type']) {
                    case 'integer': $val = (int)$val; break;
                    case 'boolean': $val = (bool)(int)$val; break;
                    case 'json':    $val = json_decode($val, true); break;
                }
                $cache[$row['setting_key']] = $val;
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

// --- DİL -----------------------------------------------------

/** Aktif dili döner (tr/en) */
function current_lang(): string
{
    if (isset($_GET['lang']) && in_array($_GET['lang'], AVAILABLE_LANGS)) {
        $_SESSION['lang'] = $_GET['lang'];
    }
    return $_SESSION['lang'] ?? DEFAULT_LANG;
}

/** Çeviri getir (lang/tr.php, lang/en.php dosyalarından) */
function __(string $key, array $replace = []): string
{
    static $translations = [];
    $lang = current_lang();

    if (!isset($translations[$lang])) {
        $file = __DIR__ . '/../lang/' . $lang . '.php';
        $translations[$lang] = file_exists($file) ? require $file : [];
    }

    $text = $translations[$lang][$key] ?? $key;
    foreach ($replace as $k => $v) {
        $text = str_replace(':' . $k, $v, $text);
    }
    return $text;
}

/** Alan adına göre dil versiyonunu getir: col('title') -> title_tr veya title_en */
function lang_field(string $field): string
{
    return $field . '_' . current_lang();
}

// --- AUTH (Admin) --------------------------------------------

function is_logged_in(): bool
{
    return !empty($_SESSION['admin_user_id']);
}

function current_user(): ?array
{
    if (!is_logged_in()) return null;
    static $user = null;
    if ($user === null) {
        $user = db()->fetch(
            "SELECT id, name, email, role, avatar, is_active FROM admin_users WHERE id = ? AND is_active = 1",
            [$_SESSION['admin_user_id']]
        );
    }
    return $user;
}

function require_auth(): void
{
    if (!is_logged_in()) {
        flash_error('Lütfen giriş yapınız.');
        redirect(admin_url('login.php'));
    }
    $user = current_user();
    if (!$user) {
        session_destroy();
        redirect(admin_url('login.php'));
    }
}

function has_role(string|array $roles): bool
{
    $user = current_user();
    if (!$user) return false;
    $roles = is_array($roles) ? $roles : [$roles];
    return in_array($user['role'], $roles);
}

function require_role(string|array $roles): void
{
    require_auth();
    if (!has_role($roles)) {
        http_response_code(403);
        die('<h1>403 — Erişim Reddedildi</h1><p>Bu sayfaya erişim yetkiniz yok.</p>');
    }
}

// --- LOG -----------------------------------------------------

function log_activity(string $action, string $module = '', string $description = '', ?int $entityId = null, ?string $entityType = null, $oldData = null, $newData = null): void
{
    try {
        db()->insert('activity_logs', [
            'admin_user_id' => $_SESSION['admin_user_id'] ?? null,
            'action'        => $action,
            'module'        => $module,
            'entity_type'   => $entityType,
            'entity_id'     => $entityId,
            'description'   => $description,
            'old_data'      => $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
            'new_data'      => $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
            'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'    => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
        ]);
    } catch (Throwable $e) {
        // Log kaydı yapılamadıysa sessizce geç
    }
}

// --- DOSYA YÜKLEME ------------------------------------------

/**
 * Yüklenen dosyayı uploads klasörüne taşır.
 * Görsel (jpg/png/webp) ise otomatik webp'e dönüştürür ve max genişliğe göre küçültür.
 * @param array $file $_FILES elemanı
 * @param string $subdir alt klasör (örn: 'offices', 'vehicles')
 * @param array $allowedExt izin verilen uzantılar
 * @param int $maxWidth görsel için maksimum genişlik (0 = küçültme yok)
 * @param int $quality webp kalitesi 0-100
 * @return string|null Relatif dosya yolu (örn: "offices/abc.webp") veya null
 */
function upload_file(array $file, string $subdir = 'general', array $allowedExt = ['jpg','jpeg','png','gif','webp'], int $maxWidth = 1600, int $quality = 82): ?string
{
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt)) return null;

    // Boyut kontrolü (max 10MB - webp'e dönüştüğü için esnek olalım)
    if ($file['size'] > 10 * 1024 * 1024) return null;

    $dir = UPLOADS_PATH . '/' . $subdir;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    // Görsel mi? (gif hariç - gif'te webp animasyonu sorunlu)
    $imageExts = ['jpg', 'jpeg', 'png', 'webp'];
    $canConvertToWebp = in_array($ext, $imageExts) && function_exists('imagewebp');

    if ($canConvertToWebp) {
        // WebP'e dönüştür + küçült
        $webpFilename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.webp';
        $webpPath = $dir . '/' . $webpFilename;

        if (_process_image_to_webp($file['tmp_name'], $webpPath, $ext, $maxWidth, $quality)) {
            return $subdir . '/' . $webpFilename;
        }
        // Dönüştürme başarısızsa, orijinali kaydet (fallback)
    }

    // Orijinal dosyayı kaydet
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $path = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $path)) return null;

    return $subdir . '/' . $filename;
}

/**
 * Görseli WebP formatına çevirir ve gerekirse küçültür
 * @internal
 */
function _process_image_to_webp(string $srcPath, string $destPath, string $srcExt, int $maxWidth = 1600, int $quality = 82): bool
{
    // GD kütüphanesi var mı?
    if (!function_exists('imagecreatefromjpeg') || !function_exists('imagewebp')) return false;

    try {
        // Kaynak görseli oku
        $src = null;
        switch ($srcExt) {
            case 'jpg':
            case 'jpeg':
                $src = @imagecreatefromjpeg($srcPath);
                break;
            case 'png':
                $src = @imagecreatefrompng($srcPath);
                break;
            case 'webp':
                $src = @imagecreatefromwebp($srcPath);
                break;
        }
        if (!$src) return false;

        $width = imagesx($src);
        $height = imagesy($src);

        // Maksimum genişliğe göre küçült (orantıyı koru)
        if ($maxWidth > 0 && $width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int)(($maxWidth / $width) * $height);

            $resized = imagecreatetruecolor($newWidth, $newHeight);

            // PNG şeffaflığını koru
            if ($srcExt === 'png' || $srcExt === 'webp') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
            }

            imagecopyresampled($resized, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($src);
            $src = $resized;
        } else if ($srcExt === 'png' || $srcExt === 'webp') {
            imagealphablending($src, false);
            imagesavealpha($src, true);
        }

        // WebP olarak kaydet
        $success = imagewebp($src, $destPath, $quality);
        imagedestroy($src);

        return $success;
    } catch (\Throwable $e) {
        return false;
    }
}

// --- REZERVASYON KODU ---------------------------------------

function generate_booking_code(): string
{
    $prefix = setting('booking_code_prefix', 'RNT');
    $year = date('Y');
    $random = strtoupper(bin2hex(random_bytes(3)));
    return "$prefix-$year-$random";
}

// --- JSON RESPONSE ------------------------------------------

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_success($data = [], string $msg = 'OK'): void
{
    json_response(['success' => true, 'message' => $msg, 'data' => $data]);
}

function json_error(string $msg, int $status = 400, $errors = null): void
{
    json_response(['success' => false, 'message' => $msg, 'errors' => $errors], $status);
}
