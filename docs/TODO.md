## efatura.ai — Yol Haritası ve Görev Listesi

### 0) Proje İskeleti ve Altyapı
- [x] Monorepo yapısı: `backend/` (Laravel), `frontend/` (React)
  - [x] `backend/` oluşturuldu (Laravel 11)
  - [x] `frontend/` (React)
- [x] Laravel projesi kurulum (PHP 8.4+, Composer)
- [x] Temel paketler: Sanctum, Horizon, Telescope, Pint, Redis (phpredis)
- [x] Env ve config: Queue=redis, Cache=redis, Log kanalları
- [x] Sağlık uçları: `/api/health` (readiness) ve `/up` (liveness)
- [x] Frontend SEO/PWA: `manifest.webmanifest`, `robots.txt`, `sitemap.xml`, basit `sw.js`, `index.html` meta/OG
- [x] Docker Compose healthcheck’ler ve bağımlılıklara göre başlatma (nginx/php/mysql)

### 1) Kimlik, Organizasyon ve Yetki (RBAC)
- [x] `organizations` şeması
- [x] `organization_user` pivot şeması
- [x] `users` (Laravel varsayılan)
- [x] Davet akışı (e-posta ile, rol atama)
- [x] RBAC: Owner/Admin/Finance/Developer rolleri ve policy’ler (RoleMiddleware)
- [ ] 2FA (opsiyonel, panelden)

### 2) Abonelik ve Planlar
- [x] `plans`, `subscriptions`, `subscription_invoices`
- [x] Plan katalogu: paketler, limitler, fiyatlandırma (TRY)
- [x] Yenileme, iptal/dondurma akışları (temel uçlar)
- [ ] Ödeme sağlayıcı entegrasyonu (iyzico/PayTR/Stripe kararı)

### 3) Kontör Cüzdanı
- [x] `credit_wallets`, `credit_transactions`
- [x] Bakiye düşüm kuralları (fatura başına kullanım)
- [x] Eşik altı uyarıları ve otomatik yükleme opsiyonu

### 4) Fatura Motoru (Kolaysoft) Entegrasyonu
- [x] Client iskeleti (SOAP) ve konfig (mock modu)
- [x] Kuyruk-temelli işleme iskeleti (job: `DispatchInvoiceToKolaysoft`)
- [x] E‑Arşiv: gerçek `sendInvoice` mapping (InputDocument) + auth header (EArchiveInvoiceWS/sendInvoice, `invoiceXMLList`, boş `sourceUrn`/`destinationUrn`, `email`)
- [x] UBL 2.1 üretimi: Internet Satış (`AdditionalDocumentReference/INTERNET_SATIS`), Kargo (`Delivery/CarrierParty`), satır ve toplam hesapları, imza iskeleti ve şema uyumu
- [x] XML şema kontrolü: `ControlInvoiceXML` (InvoiceWS)
- [x] Hata kodu eşleme config’i (`config/kolaysoft_errors.php`)
- [x] Job’larda backoff + kalıcı failure ve kullanıcı-dostu hata kaydı
- [x] E‑Fatura: gerçek `SendInvoice` mapping (InvoiceWS) ve alias/URN akışı (ProfileID=TEMELFATURA)
- [x] Hata sınıfları ve kapsamlı error mapping
- [x] Retry/backoff politikaları, circuit breaker ve DLQ
- [ ] Webhook işleme ve imza doğrulama (Kolaysoft’ta varsa)
- [ ] e‑Despatch: canlı PK/GB listesi ve diğer sorgular için doğrudan `DespatchWS` client çağrıları (header/timeout/compression ayarlı)
- [x] Sağlayıcı fault → kullanıcı mesajı eşleme tablosu (error mapping)

### 5) Zunapro Entegrasyonu
- [ ] Kimlik/anahtar yönetimi, bağlantı doğrulama
- [ ] Veri normalizasyonu (sipariş → taslak fatura)
- [ ] Eşitleme job’ları, delta/pagination
- [ ] Hata listesi ve manuel müdahale ekranları

### 6) Public API (v1) — Uçların Uygulanması
- [x] `GET /v1/organizations/current`
- [x] `GET/POST /v1/invoices`, `GET /v1/invoices/{id}`
- [x] `POST /v1/invoices/{id}/cancel`, `POST /v1/invoices/{id}/retry`
- [x] `GET /v1/credits/wallet`, `GET /v1/credits/transactions`
- [x] `POST/GET /v1/webhooks/subscriptions`, `POST /v1/webhooks/deliveries/{id}/replay`
- [x] API Key yönetimi (oluştur/iptal, kısıtlama, IP allowlist)
  - [x] Model + middleware + CLI ile üretim (endpoint’ler bekliyor)
- [x] Oran sınırı
  - [x] Idempotency tablosu ve middleware (POST /invoices üzerinde)

### 7) Webhook Sistemi
- [x] Abonelik modeli ve teslimat kuyruğu (SendWebhookDelivery job, backoff)
- [x] İmza (HMAC) üretimi (gönderimde `X-Webhook-Signature`)
- [x] İmza doğrulama (alıcı tarafı için örnek middleware)
- [x] Yeniden gönderim (retry/backoff), son denemede failed işaretleme
- [x] Dead-letter kuyruğu (opsiyonel ayrı queue ve görünürlük)

### 8) Panel (React) — v1
- [x] Auth + organizasyon seçimi
- [x] Dashboard (bakiye, kullanım, hata kutucuğu)
- [x] Kontör işlemleri (satın alma, ayarlar)
- [x] Plan/abonelik sayfaları
- [x] Fatura listesi/durumları, yeniden dene
- [ ] Zunapro bağlantısı ve günlükler
- [x] Geliştirici ayarları (API key, IP list, webhook’lar)

### 14) Katalog API’ları
- [x] Customers: `GET/POST /v1/customers`, `GET /v1/customers/{id}`
- [x] Products: `GET/POST /v1/products`, `GET /v1/products/{id}`
- [x] Invoice Items: tablo, model ve yazma entegrasyonu (POST /invoices)

### 15) Fatura İş Akışı İyileştirmeleri
- [x] Toplamların (subtotal, vatTotal, grandTotal) otomatik hesaplanması
- [x] Müşteri auto-upsert (tckn_vkn bazlı)
- [x] Ürün auto-upsert (SKU bazlı)
- [x] İptal/yeniden dene iş kuralları (Kolaysoft yanıtlarına göre)

### 12) Dokümantasyon ve SDK’lar
- [x] OpenAPI taslağı (`api/openapi.yaml`)
- [x] Portal yayını (Swagger UI / ReDoc)
- [x] Postman koleksiyonu
- [x] Entegrasyon kılavuzu ve webhook dokümanı
- [x] Mock server (OpenAPI’den)
- [x] SDK’lar: Node.js, PHP, Python (OpenAPI Generator)

### 9) Admin İç Panel
- [x] Müşteri/organizasyon yönetimi (listeleme)
- [x] Plan ve fiyat yönetimi (liste/ekle)
- [x] Kupon/kampanyalar
- [ ] Kullanım anomalileri, oran limitleri
- [ ] Webhook/entegrasyon sağlık izleme

### 10) Gözlemlenebilirlik ve Güvenlik
- [x] Sentry (backend) ve temel APM örnek yapılandırması
- [x] Log redaksiyonu ve PII maskeleme, yapılandırılmış JSON log
- [x] Audit log (immutable) kritik mutasyonlar için
- [x] Rate limiting, IP allowlist, brute-force koruması
- [x] Uptime/health monitor (Better Uptime/Healthchecks) ve alarm eşikleri

### 11) CI/CD ve Kalite
- [ ] Lint (Pint/ESLint), testler, coverage raporu
- [ ] Migration ve seed süreçleri
- [x] Staging ortamı, deploy pipeline (GitHub Actions)
- [x] Frontend test altyapısı (Vitest + Testing Library) ve temel testler (Login, DataTable)
- [x] Backend test altyapısı (PHPUnit) ve smoke/entegrasyon testleri

### 16) Dokümantasyon ve SDK’lar (bakım)
- [ ] OpenAPI güncel tutma ve portal (Swagger/ReDoc)
- [ ] Mock server (OpenAPI’den)
- [ ] SDK’lar: Node.js, PHP, Python (OpenAPI Generator)
- [ ] Değişiklik günlüğü ve deprecation politikası

### 13) Yayın Hazırlığı
- [ ] Fiyatlandırma/limitlerin doğrulanması
- [x] SLA/SLO ve destek süreçleri
- [x] Hukuki (KVKK, sözleşmeler) ve güvenlik kontrol listesi

### Frontend TODO (UI/UX — Tablolar, Auth/Hesap ve Panel)

**Tamamlananlar**
- [x] Panel layout (sidebar/topbar), collapsible sidebar
- [x] Tema geçişi (koyu/aydınlık)
- [x] Hesap sayfası (profil güncelleme, şifre değiştirme, çıkış)
- [x] Invoices: gelişmiş tablo (sıralama, sticky header, skeleton, boş/hata durumları)
- [x] Vouchers/Despatches: gelişmiş tablo (sıralama, sticky header, skeleton, boş/hata)
- [x] Customers/Products: liste + hızlı arama, boş/hata, skeleton
- [x] URL senkronizasyonu: filtre/arama/sıralama/paginasyon query string
- [x] Ortak Tablo bileşeni (`DataTable`): kolon tanımı, sıralama, boş/yükleme/hata durumu
- [x] CSV export (Invoices, Customers, Products, Vouchers, Despatches) — UTF‑8 BOM (Excel uyumlu)
- [x] XLSX export (Invoices, Customers, Products, Vouchers, Despatches)
- [x] Tablo: kolon görünürlüğü, kolon yeniden sıralama, yoğunluk modu (Rahat/Normal/Sıkı)
- [x] Tablo: kolon genişliği sürükle-bırak ile yeniden boyutlandırma (kalıcı)
- [x] Tablo: çoklu kolon sıralama (Shift+klik), sıra numarası gösterimi ve URL ile senkronizasyon (ms)
- [x] Tablo: başlık menüsü (yeniden adlandırma, hizalama, görünürlük, sıfırla; Tümü/Hiçbiri/Öntanım)
- [x] Tablo: satır eylem menüsü (⋯) — Invoices/Vouchers/Despatches için retry/cancel
- [x] Satır eylemlerinde toast + otomatik yenileme (Invoices/Vouchers/Despatches)
- [x] Arama kutusu: debounce + '/' ve Ctrl/Cmd+K kısayolu (Invoices/Customers/Products)
- [x] Onay modalları: `ConfirmProvider`, danger varyantı + ARIA, global odak
- [x] Dashboard mini grafikler (Recharts) + code-splitting (lazy)
- [x] Global error boundary
- [x] InvoiceCreate: react-hook-form + zod (field array, doğrulama, hata özetleri)
- [x] Account sayfası: react-hook-form + zod
- [x] DataTable: sanallaştırma (opsiyonel) + tüm liste sayfalarında aktif kullanım
- [x] Webhook Subscriptions/Deliveries: sayfalar, filtreler, cursor pagination, replay
- [x] Webhook Subscriptions/Deliveries: CSV/XLSX export
- [x] Webhook Deliveries: detay modal + ayrı route (ok tuşları ile gezinme, JSON kopyala)
- [x] Webhook filtreleri: URL senkronizasyonu + localStorage kalıcılığı

**Şimdi (hemen yapılacaklar)**
- [x] Formlar: diğer sayfaları da react-hook-form + zod'a taşıma (Despatch/Voucher create tamamlandı; kalan formlar gözden geçirilecek)
- [x] Webhook sayfaları: detay görünümü iyileştirmeleri
- [ ] Webhook sayfaları: hata durumlarında retry policy/görsel durumlar

**Yakın vadede (1–2 iterasyon)**
- [x] Bildirim merkezi (OK/FAIL/webhook)
- [x] Organizasyon seçici
- [x] Kullanıcı menüsünde avatar/baş harf
- [x] I18n (TR/EN) altyapısı ve dil seçici, erişilebilirlik (klavye/ARIA) başlangıç
- [x] Performans: sanallaştırma ayarları (satır yüksekliği otomatik), büyük paketleri dinamik yükleme (XLSX dinamik import)
- [x] Performans: React Query cache tuning
- [x] Webhook filtre presetleri (kaydet/çağır) ve hızlı filtre chip’leri

**Gelişmiş (ürün seviyesi)**
- [ ] Tema sistemi: koyu/açık ince ayar, marka renkleri, logolar
- [ ] Responsive optimizasyonlar (mobil/tablet), sticky action bar’lar
- [ ] Testler: kritik akışlar için component/integration testleri
- [x] Public landing (Notus esinli): hero, KPI, hizmetler/neden, fiyat, FAQ, testimonials, footer
- [x] SEO/PWA: manifest, robots, sitemap, meta/OG, SW kaydı

