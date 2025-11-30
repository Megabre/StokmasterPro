# StokMaster Pro v1.1.3

**Profesyonel Stok ve Envanter Yönetim Sistemi**

StokMaster Pro, işletmelerin stok, ürün, müşteri ve sipariş takibini kolaylıkla yapmasını sağlayan kapsamlı bir web tabanlı yönetim sistemidir. Modern arayüzü, güçlü özellikleri ve esnek yapısı ile küçük ve orta ölçekli işletmeler için ideal bir çözümdür.

---

##  Özellikler

### Genel Özellikler
-  Modern ve responsive Tabler.io tabanlı arayüz
-  Çoklu dil desteği (Türkçe/İngilizce)
-  Rol tabanlı yetkilendirme sistemi
-  Detaylı aktivite loglama (audit trail)
-  Dinamik alan sistemi (ürünler, kategoriler, müşteriler, stok)
-  Gelişmiş raporlama ve analiz
-  Excel/CSV içe/dışa aktarma
-  Otomatik yedekleme sistemi
-  Veritabanı optimizasyon araçları
-  Çoklu para birimi desteği
-  Müşteri etiketleme sistemi
-  Ölçü birimi yönetimi

### Teknik Özellikler
- PHP 7.4+ uyumlu
- MySQL/MariaDB veritabanı
- PDO ile güvenli veritabanı erişimi
- CSRF koruması
- XSS koruması
- SQL injection koruması
- Session yönetimi
- Cache sistemi

---

##  Sistem Gereksinimleri

### Sunucu Gereksinimleri
- **PHP:** 7.4 veya üzeri
- **MySQL/MariaDB:** 5.7 veya üzeri
- **Web Sunucusu:** Apache 2.4+ veya Nginx
- **PHP Eklentileri:**
  - PDO
  - PDO_MySQL
  - GD veya Imagick (resim işleme için)
  - mbstring
  - json
  - session
  - fileinfo

---

##  Kurulum

### 1. Dosyaları Yükleme

```bash
# Projeyi klonlayın veya ZIP dosyasını çıkarın
cd /path/to/your/web/directory
```

### 2. Veritabanı Kurulumu

1. MySQL/MariaDB'de yeni bir veritabanı oluşturun:
```sql
CREATE DATABASE stok CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. `stok.sql` dosyasını içe aktarın:
```bash
mysql -u kullanici_adi -p stok < stok.sql
```

veya phpMyAdmin üzerinden `stok.sql` dosyasını import edin.

### 3. Yapılandırma

`config/database.php` dosyasını düzenleyin:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'stok');
define('DB_USER', 'kullanici_adi');
define('DB_PASS', 'sifre');
define('DB_CHARSET', 'utf8mb4');
```

`config/config.php` dosyasında BASE_URL'yi düzenleyin:

```php
define('APP_URL', 'http://yourdomain.com/stok');
```

### 4. Klasör İzinleri

Aşağıdaki klasörlerin yazılabilir olması gerekir:

```bash
chmod 755 uploads/
chmod 755 uploads/company/
chmod 755 uploads/products/
chmod 755 uploads/profile/
chmod 755 uploads/import/
chmod 755 backup/
chmod 755 cache/
```

### 5. İlk Giriş

Varsayılan admin bilgileri:
- **Kullanıcı Adı:** admin
- **Şifre:** admin123

 **Güvenlik Uyarısı:** İlk girişten sonra mutlaka şifrenizi değiştirin!

---

##  Modüller

### 1. Dashboard (Ana Sayfa)

**Özellikler:**
- Genel istatistikler (toplam ürün, müşteri, sipariş, stok)
- Hızlı işlemler menüsü
- Aylık ödemeler ve borçlar grafiği (stacked bar chart)
- Ürün kategorileri dağılımı (donut chart)
- Son işlemler listesi
- Özelleştirilebilir widget görünürlüğü

**Erişim:** Tüm kullanıcılar

---

### 2. Ürünler Modülü

**Özellikler:**
- Ürün ekleme, düzenleme, silme
- Kategori bazlı ürün yönetimi
- SKU ve barkod yönetimi
- Ürün fiyatlandırma
- Minimum stok seviyesi takibi
- Ürün görseli yükleme
- Dinamik alan desteği
- Toplu işlemler
- Gelişmiş filtreleme ve arama
- Stok durumu gösterimi (stokta, kritik, tükendi)

**Erişim:** Admin, Manager, Staff

**Alt Modüller:**
- `index.php` - Ürün listesi
- `add.php` - Yeni ürün ekleme
- `edit.php` - Ürün düzenleme
- `delete.php` - Ürün silme
- `view.php` - Ürün detayları
- `fields.php` - Dinamik alan yönetimi

---

### 3. Kategoriler Modülü

**Özellikler:**
- Kategori ekleme, düzenleme, silme
- Kategori bazlı dinamik alan tanımlama
- Kategori hiyerarşisi (gelecekte)
- Kategori bazlı raporlama
- Kategori istatistikleri

**Erişim:** Admin, Manager

**Alt Modüller:**
- `index.php` - Kategori listesi
- `add.php` - Yeni kategori ekleme
- `edit.php` - Kategori düzenleme
- `delete.php` - Kategori silme
- `fields.php` - Kategori dinamik alanları

---

### 4. Müşteriler Modülü

**Özellikler:**
- Müşteri ekleme, düzenleme, silme
- Müşteri detay sayfası
- Müşteri sipariş geçmişi
- Müşteri mali geçmişi (ödeme/borç)
- Müşteri bakiyesi takibi
- Müşteri etiketleme sistemi
- Dinamik alan desteği
- Gelişmiş filtreleme (isim, telefon, e-posta, şirket)
- Müşteri borç/ödeme ekleme (hızlı işlemler)

**Erişim:** Admin, Manager, Accountant, Staff

**Alt Modüller:**
- `index.php` - Müşteri listesi
- `add.php` - Yeni müşteri ekleme
- `edit.php` - Müşteri düzenleme
- `delete.php` - Müşteri silme
- `view.php` - Müşteri detay sayfası
- `fields.php` - Müşteri dinamik alanları
- `filter.php` - Gelişmiş filtreleme

---

### 5. Stok Modülü

**Özellikler:**
- Stok giriş/çıkış işlemleri
- Stok düzeltme (adjustment)
- Stok hareket geçmişi
- Ürün bazlı stok takibi
- Dinamik alan desteği
- Stok hareket notları
- Birim bazlı stok yönetimi
- Negatif stok kontrolü (ayarlanabilir)

**Erişim:** Admin, Manager, Staff

**Alt Modüller:**
- `index.php` - Stok hareket listesi
- `add.php` - Yeni stok hareketi
- `edit.php` - Stok hareketi düzenleme
- `delete.php` - Stok hareketi silme
- `fields.php` - Stok dinamik alanları

---

### 6. Siparişler Modülü

**Özellikler:**
- Sipariş oluşturma
- Sipariş düzenleme ve silme
- Sipariş durumu yönetimi (Beklemede, İşleniyor, Tamamlandı, İptal)
- Sipariş detay sayfası
- Sipariş yazdırma (PDF benzeri format)
- Müşteri bazlı sipariş listesi
- Tarih bazlı filtreleme
- Sipariş özeti ve toplamları
- KDV hesaplama

**Erişim:** Admin, Manager, Accountant, Staff

**Alt Modüller:**
- `index.php` - Sipariş listesi
- `add.php` - Yeni sipariş oluşturma
- `edit.php` - Sipariş düzenleme
- `delete.php` - Sipariş silme
- `view.php` - Sipariş detay sayfası
- `print.php` - Sipariş yazdırma
- `update_status.php` - Sipariş durumu güncelleme

---

### 7. Mali İşlemler Modülü

**Özellikler:**
- Ödeme ekleme/düzenleme/silme
- Borç ekleme/düzenleme/silme
- Gider ekleme/düzenleme/silme
- Çoklu para birimi desteği
- Ödeme yöntemleri (Nakit, Çek, Senet, Kredi Kartı, Havale/EFT)
- Müşteri bazlı mali geçmiş
- Tarih bazlı filtreleme
- Nakit özeti
- Gider kategorileri

**Erişim:** Admin, Manager, Accountant

**Alt Modüller:**
- `index.php` - İşlem listesi
- `add-payment.php` - Ödeme ekleme
- `add-debt.php` - Borç ekleme
- `add-expense.php` - Gider ekleme
- `delete.php` - İşlem silme
- `expenses.php` - Gider listesi
- `cash-summary.php` - Nakit özeti
- `show.php` - İşlem detayı

---

### 8. Araçlar Modülü

#### 8.1. Raporlar
- **Satış Raporu:** Günlük satış grafiği, kategori bazlı satışlar, en çok satan ürünler
- **Stok Raporu:** Kategori bazlı stok dağılımı, stok durumu, düşük stok uyarıları
- **Müşteri Raporu:** Müşteri dağılımı, aktif/pasif müşteri analizi
- Tarih aralığı filtreleme
- Yazdırma desteği (şirket logosu ve bilgileri ile)

#### 8.2. Hesaplayıcılar
- Birim dönüşümleri
- Fiyat hesaplamaları
- KDV hesaplamaları

#### 8.3. Veritabanı Optimizasyonu
- Tablo optimizasyonu
- Fragmentasyon analizi
- Cache temizleme
- Tablo analiz ve kontrol
- Toplu işlemler

#### 8.4. Yedekleme
- Otomatik veritabanı yedekleme
- Manuel yedekleme
- Yedek geri yükleme
- Yedek listesi ve indirme

#### 8.5. İçe/Dışa Aktarım
- Ürün, müşteri, kategori, stok, sipariş, işlem verilerini Excel/CSV formatında dışa aktarma
- Excel/CSV dosyalarından veri içe aktarma
- Şablon dosyaları
- Toplu veri güncelleme

#### 8.6. Cache Yönetimi
- Cache temizleme
- Cache istatistikleri

**Erişim:** Admin, Manager (bazı araçlar sadece Admin)

---

### 9. Ayarlar Modülü

#### 9.1. Sistem Ayarları
- Site adı
- Firma bilgileri (ad, adres, telefon, e-posta, vergi no)
- Firma logosu yükleme
- Zaman dilimi
- Tarih formatı
- Varsayılan para birimi
- Maksimum yükleme boyutu
- Son işlemler tutma süresi
- Sistem bilgileri (versiyon, PHP, MySQL)

#### 9.2. Kullanıcı Yönetimi
- Kullanıcı ekleme, düzenleme, silme
- Rol yönetimi (Admin, Manager, Accountant, Staff, Viewer)
- Kullanıcı profil resmi
- Şifre sıfırlama

#### 9.3. Envanter Ayarları
- Düşük stok uyarı seviyesi
- Varsayılan ölçü birimi
- Otomatik SKU oluşturma
- SKU ön eki
- Stok hareket notları zorunluluğu
- Negatif stok izni
- Stok geçmişi tutma
- Sipariş otomatik durumu
- İptal edilen siparişlerin stoka geri dönmesi

#### 9.4. Para Birimleri
- Para birimi ekleme, düzenleme, silme
- Döviz kurları
- Para birimi formatları
- Varsayılan para birimi

#### 9.5. Müşteri Etiketleri
- Etiket ekleme, düzenleme, silme
- Etiket renkleri
- İndirim yüzdeleri

#### 9.6. Ölçü Birimleri
- Birim ekleme, düzenleme, silme
- Birim sembolleri
- Varsayılan birim

**Erişim:** Sadece Admin

---

### 10. Son İşlemler Modülü

**Özellikler:**
- Tüm sistem aktivitelerinin detaylı loglanması
- Kullanıcı bazlı filtreleme
- İşlem türü bazlı filtreleme
- Tarih bazlı filtreleme
- Değişiklik detayları (eski/yeni değerler)
- Timeline görünümü
- IP adresi ve tarayıcı bilgisi
- Tutma süresi yönetimi (ayarlardan)

**Loglanan İşlemler:**
- Ürün ekleme/düzenleme/silme
- Kategori ekleme/düzenleme/silme
- Müşteri ekleme/düzenleme/silme
- Stok hareketleri
- Sipariş işlemleri
- Mali işlemler (ödeme, borç, gider)
- Ayarlar değişiklikleri
- Kullanıcı işlemleri
- Profil güncellemeleri

**Erişim:** Tüm kullanıcılar (kendi işlemlerini görebilir)

---

### 11. Profil Modülü

**Özellikler:**
- Profil bilgileri güncelleme
- Profil resmi yükleme
- Şifre değiştirme
- Dil tercihi
- Aktivite özeti

**Erişim:** Tüm kullanıcılar (kendi profilleri)

---

## ⚙️ Yapılandırma

### Veritabanı Ayarları
`config/database.php` dosyasında veritabanı bağlantı bilgilerini düzenleyin.

### Uygulama Ayarları
`config/config.php` dosyasında:
- `APP_NAME`: Uygulama adı
- `APP_VERSION`: Versiyon numarası
- `APP_URL`: Uygulama URL'i
- `APP_TIMEZONE`: Zaman dilimi

### Dil Ayarları
- `lang/tr.php`: Türkçe çeviriler
- `lang/en.php`: İngilizce çeviriler

Yeni dil eklemek için `lang/` klasörüne yeni bir PHP dosyası ekleyin.

---

##  Güvenlik

### Güvenlik Özellikleri
- ✅ CSRF token koruması
- ✅ XSS koruması (htmlspecialchars)
- ✅ SQL injection koruması (PDO prepared statements)
- ✅ Session güvenliği
- ✅ Şifre hashleme (password_hash)
- ✅ Rol tabanlı erişim kontrolü
- ✅ Dosya yükleme validasyonu
- ✅ Input sanitization

### Güvenlik Önerileri
1. İlk kurulumdan sonra admin şifresini değiştirin
2. Güçlü şifreler kullanın
3. Düzenli yedekleme yapın
4. Sistem güncellemelerini takip edin
5. HTTPS kullanın (production ortamında)
6. `config/` klasörünün erişim izinlerini kontrol edin

---
##  Yedekleme ve Geri Yükleme

### Otomatik Yedekleme
- Ayarlar > Araçlar > Yedekleme menüsünden otomatik yedekleme yapılabilir
- Yedekler `backup/` klasörüne kaydedilir

### Manuel Yedekleme
```bash
mysqldump -u kullanici_adi -p stok > backup_manuel.sql
```

### Geri Yükleme
1. Ayarlar > Araçlar > Yedekleme
2. Geri yüklenecek yedeği seçin
3. "Geri Yükle" butonuna tıklayın

---

##  Destek

### Resmi Destek
- **Web Sitesi:** https://megabre.com
- **E-posta:** hello@megabre.com
- **Dokümantasyon:** [https://megabre.com/](https://www.megabre.com/stokmaster-pro.php)

### Topluluk Desteği
- GitHub Issues
- Forum

---

##  Versiyon Geçmişi

### v1.1.3 (Güncel)
- Tabler.io entegrasyonu
- ApexCharts entegrasyonu
- Detaylı aktivite loglama sistemi
- Müşteri detay sayfası
- Rapor yazdırma iyileştirmeleri
- Veritabanı optimizasyon modülü
- Versiyon yönetim sistemi

### v1.0.0
- İlk sürüm
- Temel modüller
- Kullanıcı yönetimi
- Stok yönetimi

---

##  Lisans

Bu proje özel lisans altındadır. Tüm hakları saklıdır.

**© 2024 Megabre. Tüm hakları saklıdır.**

---

##  Geliştirici

**Ali Çömez / Slaweally**
- **Web:** https://megabre.com
- **E-posta:** info@megabre.com

---

## 🙏 Teşekkürler

- Tabler.io - Modern UI framework
- ApexCharts - Grafik kütüphanesi
- Bootstrap - CSS framework
- jQuery - JavaScript kütüphanesi
- Select2 - Gelişmiş select bileşeni
- DataTables - Tablo eklentisi

---

---

##  Sistem İstatistikleri

- **Toplam Modül:** 11
- **Toplam Sayfa:** 50+
- **Desteklenen Dil:** 2 (Türkçe, İngilizce)
- **Kullanıcı Rolleri:** 5
- **Dinamik Alan Desteği:** 4 modül

---

**Son Güncelleme:** 2025
**Versiyon:** 1.1.3

