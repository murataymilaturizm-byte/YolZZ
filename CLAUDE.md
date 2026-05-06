# YOlzz Projesi Referans Rehberi

## Proje Özeti
- **Site**: yolzz.com (canlı, hosting.com.tr'de barındırılıyor)
- **Açıklama**: Türk turistlere yönelik araç kiralama platformu. Kullanıcılar araç kiralayabilir, kampanyaları görüntüleyebilir, rezervasyon sorgulayabilir.
- **Geçmiş**: rentalcarzz.com'dan yolzz.com'a 1-2 hafta önce taşındı (rebrand).

## Teknik Yapı
- **Backend**: PHP
- **Veritabanı**: MariaDB (MySQL uyumlu)
- **Hosting**: cPanel (hosting.com.tr)
- **Frontend**: HTML, CSS, JavaScript (assets/ klasöründe)
- **Diğer**: Çok dilli destek (lang/en.php, lang/tr.php), API endpoint'leri (api/ klasörü)

## Önemli Dosya Yolları
- **Ana Sayfa**: index.php
- **Blog**: blog.php, blog-detail.php
- **Rezervasyon**: checkout.php, rezervasyon-sorgula.php
- **Kampanyalar**: kampanyalar.php, kampanya-detail.php
- **Lokasyonlar**: lokasyon.php, lokasyonlar.php
- **Admin Paneli**: admin/index.php, admin/login.php
- **API**: api/contact.php, api/coupon.php, api/search-vehicles.php
- **Konfigürasyon**: config/config.php, config/database.sql
- **Yardımcı Dosyalar**: includes/Database.php, includes/ApiProvider.php
- **Assets**: assets/css/, assets/js/, assets/img/
- **Uploads**: uploads/vehicles/, uploads/locations/, vb.
- **Dil Dosyaları**: lang/tr.php, lang/en.php

## Veritabanı Bilgileri
- **DB Adı**: turzzcom_rentalcarzz
- **Not**: Rebrand sırasında değişmedi.
- **Tablolar**: Araçlar, kampanyalar, lokasyonlar, kullanıcılar, vb. (database.sql'den kontrol et)

## Renk Paleti ve Marka Kuralları
- **Birincil Renk**: Mavi #1d71b8
- **İkincil Renk**: Turuncu #e94e1b
- **Logo**: assets/img/logo.png (Yolzz, Poppins Bold fontu)
- **Telefon**: 0850 500 0 777
- **SEO Şehir Sayfaları**: Sivas, Gaziantep, Samsun, Trabzon, Bodrum, Dalaman, Dubai

## Yapma Listesi
- **Production Deploy**: 
  - Hosting.com.tr cPanel'e giriş yap.
  - FTP veya File Manager ile dosyaları upload et (YOlzz/ klasörünü kök dizine koy).
  - Veritabanı yedekle (phpMyAdmin'den export).
  - Değişiklikleri test et (staging ortamı yok, dikkatli ol).
  - Sezon dışında yedek/test zor, production'a doğrudan deploy.
- **Güvenlik**: .htaccess ile redirect'ler (yolzz-rebrand-htaccess-redirect.txt'ye bak).
- **Backup**: Düzenli veritabanı ve dosya yedekleri al.

## Şu An Üzerinde Çalıştığım Pending İşler
- [ ] (Henüz belirlenmemiş - güncellemeler için bu listeyi doldur)