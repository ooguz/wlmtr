# Monument Data Synchronization Jobs

Bu dokümantasyon, anıt verilerinin Wikidata'dan otomatik olarak senkronize edilmesi için oluşturulan kuyruk görevlerini açıklar.

## Oluşturulan Job'lar

### 1. SyncMonumentLocations
- **Amaç**: Anıtların konum bilgilerini (`location_hierarchy_tr`) Wikidata'dan çeker
- **Çalışma Sıklığı**: Her 2 saatte bir
- **Batch Size**: 50 anıt
- **Timeout**: 5 dakika

### 2. SyncMonumentDescriptions
- **Amaç**: Anıtların açıklama bilgilerini (`description_tr`, `description_en`) Wikidata'dan çeker
- **Çalışma Sıklığı**: Her 3 saatte bir
- **Batch Size**: 50 anıt
- **Timeout**: 5 dakika

### 3. SyncAllMonumentData
- **Amaç**: Hem konum hem açıklama bilgilerini kapsamlı olarak senkronize eder
- **Çalışma Sıklığı**: Her saatte bir
- **Batch Size**: 25 anıt
- **Timeout**: 10 dakika

## Zamanlanmış Görevler

Laravel Scheduler aracılığıyla otomatik olarak çalışır:

```bash
# Scheduler'ı başlatmak için (production'da cron job olarak çalışmalı)
php artisan schedule:work

# Veya cron job olarak:
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Manuel Kullanım

### Komut Satırından Çalıştırma

```bash
# Tüm verileri senkronize et (hemen çalıştır)
php artisan monuments:sync-data

# Sadece konum bilgilerini senkronize et
php artisan monuments:sync-data --type=locations

# Sadece açıklama bilgilerini senkronize et
php artisan monuments:sync-data --type=descriptions

# Job'u kuyruğa gönder (arka planda çalışır)
php artisan monuments:sync-data --dispatch
```

### Programatik Kullanım

```php
use App\Jobs\SyncAllMonumentData;
use App\Jobs\SyncMonumentLocations;
use App\Jobs\SyncMonumentDescriptions;

// Hemen çalıştır
SyncAllMonumentData::dispatch();

// Gecikmeli çalıştır
SyncMonumentLocations::dispatch()->delay(now()->addMinutes(30));
```

## Queue Worker

Job'ların çalışması için queue worker'ın aktif olması gerekir:

```bash
# Queue worker'ı başlat
php artisan queue:work

# Supervisor ile sürekli çalıştır (production için önerilen)
# /etc/supervisor/conf.d/laravel-worker.conf dosyası oluştur
```

## Log Takibi

Tüm senkronizasyon işlemleri Laravel log dosyasına kaydedilir:

```bash
# Log dosyasını takip et
tail -f storage/logs/laravel.log

# Sadece senkronizasyon loglarını filtrele
tail -f storage/logs/laravel.log | grep "monument.*sync"
```

## Performans Optimizasyonları

### Batch Processing
- Job'lar küçük gruplar halinde çalışır (25-50 anıt)
- Büyük veri setlerini işlerken sistem kaynaklarını korur
- Hata durumunda sadece küçük bir grup etkilenir

### Rate Limiting
- Job'lar arasında gecikme süreleri var
- Wikidata API'sine aşırı yük bindirmez
- Otomatik retry mekanizması (3 deneme)

### Error Handling
- Başarısız job'lar log'a kaydedilir
- Tek bir anıtın hatası tüm batch'i durdurmaz
- Timeout koruması

## Monitoring

### Scheduler Durumu
```bash
# Zamanlanmış görevleri listele
php artisan schedule:list

# Scheduler'ın çalışıp çalışmadığını test et
php artisan schedule:test
```

### Queue Durumu
```bash
# Kuyruk durumunu kontrol et
php artisan queue:monitor

# Başarısız job'ları listele
php artisan queue:failed

# Başarısız job'ı tekrar dene
php artisan queue:retry [job-id]
```

## Veritabanı Güncellemeleri

Job'lar çalıştıkça şu alanlar güncellenir:
- `location_hierarchy_tr`: Konum hiyerarşisi (örn: "İstanbul, Türkiye")
- `description_tr`: Türkçe açıklama
- `description_en`: İngilizce açıklama
- `last_synced_at`: Son senkronizasyon tarihi

## Troubleshooting

### Job'lar Çalışmıyor
1. Queue worker'ın çalıştığını kontrol edin
2. Scheduler'ın aktif olduğunu kontrol edin
3. Log dosyalarını inceleyin

### Wikidata API Hataları
1. İnternet bağlantısını kontrol edin
2. Wikidata API limitlerini kontrol edin
3. Job'ların retry mekanizması otomatik olarak devreye girer

### Performans Sorunları
1. Batch size'ları azaltın
2. Job'lar arasındaki gecikme sürelerini artırın
3. Queue worker sayısını artırın
