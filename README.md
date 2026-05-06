# 🚗 Rentalcarzz - Araç Kiralama Yönetim Sistemi

Türkiye çapında araç kiralama hizmeti için geliştirilmiş tam kapsamlı PHP + MySQL tabanlı çözüm.

## ✨ Özellikler

### 🎨 Frontend
- **Anasayfa** — Arama formu, öne çıkan kampanyalar, ofisler, blog yazıları
- **Filo** — Gelişmiş filtreleme (kategori, marka, yakıt, vites, fiyat)
- **Rezervasyon** — Tam checkout (ekstra hizmetler, kupon kodu, ödeme)
- **Blog** — Kategori filtresi, detay sayfası, ilgili yazılar
- **Kampanyalar** — Liste ve detay, kupon kodu kopyalama
- **Ofisler** — Şehre göre gruplanmış, harita entegrasyonu
- **SSS** — Kategoriye göre gruplanmış accordion
- **İletişim** — Form, spam koruması
- **Bayilik Başvurusu** — Detaylı form

### 🛠 Admin Panel
- **Dashboard** — İstatistikler, grafik, bekleyen işler
- **Araç Yönetimi** — Tam CRUD, çoklu görsel, galeri, etiketler
- **Rezervasyon Yönetimi** — Durum takibi, ödeme, manuel oluşturma
- **Müşteri/Bayi Yönetimi** — Onay, komisyon ayarı
- **API Sağlayıcıları** — Esnek Field Mapping ile API entegrasyonu
- **Ofis Yönetimi** — Havalimanı/24 saat özellikleri, harita koordinatları
- **İçerik** — Blog, Kampanya, Sayfa, SSS, Menü editörleri
- **Ayarlar** — Gruplara ayrılmış site ayarları
- **Çok Dilli** — TR + EN hazır

### 🔌 API Entegrasyonu
Esnek Field Mapping sistemi ile farklı tedarikçilerin API'lerini kolayca bağlayın:
- Dot notation: `data.vehicles`, `pricing.daily`, `images[0].url`
- Auth tipleri: None, API Key, Bearer Token, Basic Auth
- Custom header desteği
- Otomatik fiyat markup'ı
- Senkronizasyon log'ları

## 📋 Gereksinimler

- PHP 8.0+
- MySQL 5.7+ veya MariaDB 10.3+
- Apache (mod_rewrite) veya Nginx
- PHP eklentileri: `pdo_mysql`, `mbstring`, `gd`, `curl`, `json`

## 🚀 Kurulum

### 1. Dosyaları yükleyin
Tüm dosyaları hosting'inizin `public_html` klasörüne yükleyin.

### 2. Veritabanı oluşturun
Hosting kontrol panelinden yeni bir MySQL veritabanı oluşturun (örn: `rentalcarzz`).

### 3. Kurulum sihirbazını çalıştırın
Tarayıcıdan `https://site.com/install.php` adresine gidin ve:
- Veritabanı bağlantı bilgilerini girin
- Admin hesabını oluşturun

### 4. install.php'yi silin
Güvenlik için kurulumdan sonra `install.php` dosyasını silin.

### 5. Admin panele girin
`https://site.com/admin/` adresinden giriş yapın.

## ⚙️ Yapılandırma

### config/config.php
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'rentalcarzz');
define('DB_USER', 'root');
define('DB_PASS', '');
define('SITE_URL', 'https://site.com');
define('APP_ENV', 'production'); // production | development
```

### İlk Adımlar (Admin Panel)
1. **Ayarlar → Genel**: Site adı, logo, iletişim bilgilerini güncelleyin
2. **Ayarlar → Sosyal Medya**: Sosyal medya linklerinizi ekleyin
3. **Ofisler**: İlk ofislerinizi ekleyin
4. **Araçlar**: Araçlarınızı ekleyin veya...
5. **API Sağlayıcıları**: Bir tedarikçi API'si bağlayarak otomatik sync açın
6. **Blog & Kampanyalar**: İlk içeriklerinizi oluşturun

## 🔑 API Sağlayıcı Eklemek

Yeni bir araç tedarikçisinin API'sini bağlamak için:

1. Admin panel → **API Sağlayıcıları → Yeni API Ekle**
2. **Temel Bilgiler**: Ad, Base URL
3. **Yetkilendirme**: API Key/Bearer/Basic seçin ve değerleri girin
4. **Endpoint'ler**: `/v1/vehicles` gibi endpoint yolunu girin
5. **Alan Eşleştirmesi** (en önemli):
   - `list_path`: `data.vehicles` (dönen JSON'daki liste anahtarı)
   - `brand`: `make` veya `brand_name`
   - `model`: `model` veya `name`
   - `daily_price`: `pricing.daily`
   - `image`: `images[0].url`
   - ... (diğer alanlar)
6. **Test** butonuyla bağlantıyı kontrol edin
7. **Sync** butonuyla araçları içeri aktarın

## 🎨 Özelleştirme

### Tema Renkleri
`assets/css/main.css` dosyasının en üstündeki CSS değişkenlerini düzenleyin:
```css
:root {
  --brand: #1d71b8;      /* Ana marka rengi */
  --accent: #e94e1b;     /* Aksan rengi */
  --brand-deep: #0A1F33; /* Koyu lacivert */
}
```

### Yeni Dil Ekleme
1. `lang/tr.php` dosyasını örnek alıp `lang/xx.php` oluşturun
2. `includes/helpers.php` içindeki `current_lang()` fonksiyonuna dil kodunu ekleyin

## 📁 Klasör Yapısı

```
rentalcarzz/
├── admin/              # Admin panel
│   ├── assets/         # Panel CSS/JS
│   ├── includes/       # Header/footer
│   ├── modules/        # Her modül
│   ├── index.php       # Dashboard
│   └── login.php
├── api/                # Public API endpoint'leri
├── assets/             # Frontend CSS/JS/resimler
├── config/             # Yapılandırma
├── includes/           # Core dosyalar
│   ├── Database.php    # PDO wrapper
│   ├── ApiProvider.php # API entegrasyon motoru
│   ├── helpers.php     # Helper fonksiyonlar
│   └── frontend/       # Frontend header/footer
├── lang/               # Dil dosyaları
├── uploads/            # Yüklenen dosyalar
├── database.sql        # Veritabanı şeması
├── install.php         # Kurulum sihirbazı
├── index.php           # Anasayfa
├── filo.php            # Araç listeleme
├── checkout.php        # Rezervasyon
└── .htaccess           # Apache yönlendirmeleri
```

## 🔐 Güvenlik

- ✅ CSRF koruması (tüm formlarda)
- ✅ PDO prepared statements (SQL Injection koruması)
- ✅ XSS koruması (`e()` helper)
- ✅ Şifrelenmiş parolalar (bcrypt)
- ✅ Session güvenliği (HttpOnly, Secure, SameSite)
- ✅ Upload klasöründe PHP yürütme engelli
- ✅ Hassas dosyalara erişim engeli
- ✅ Spam koruması (iletişim formu)

## 📝 Notlar

- **Yedekleme**: Düzenli olarak hem veritabanı hem de `uploads/` klasörünü yedekleyin
- **Güncellemeler**: API tedarikçilerinizi günlük olarak senkronize edin (cron job)
- **İzinler**: `uploads/` klasörü 755, dosyalar 644 olmalı

## 🆘 Sorun Giderme

**"500 Internal Server Error"**
- `.htaccess` dosyasını kontrol edin
- `mod_rewrite` aktif mi?
- PHP 8.0+ çalışıyor mu?

**Admin'e giriş yapamıyorum**
- `install.php` çalıştırıldı mı?
- `admin_users` tablosunda kullanıcı var mı?

**Görsel yüklenmiyor**
- `uploads/` klasörü ve alt klasörleri 755 izinli mi?
- PHP `upload_max_filesize` yeterli mi?

## 📧 İletişim

Bu sistem özel bir geliştirmedir. Teknik destek için proje sahibiyle iletişime geçin.

---

**Rentalcarzz** — Türkiye'nin araç kiralama çözümü · © 2026
