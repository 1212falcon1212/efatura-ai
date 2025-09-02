## SLA ve SLO Taslağı (efatura.ai)

### Kapsam
- Hizmetler: API (Public v1), Panel (Web UI), Kolaysoft entegrasyonlu e‑belge gönderimi
- Ortamlar: Staging, Production

### SLA (Dış Paydaşlara Taahhüt)
- Uptime (Aylık): %99.5
- Olay Yanıt Süresi: P1 kritik < 15 dk, P2 yüksek < 1 saat
- Çözüm Süresi Hedefi: P1 < 4 saat, P2 < 1 iş günü
- Destek Saatleri: Hafta içi 09:00–18:00 TR, P1 7/24 on‑call

### SLO (İç Hedefler)
- API Başarı Oranı: ≥ %99.9 (5xx hata oranı ≤ %0.1)
- Ortalama Yanıt Süresi (p95):
  - Okuma uçları: ≤ 300 ms
  - Yazma/kuyruklanan işlemler: ≤ 700 ms (sync kabul), async iş tamamlama ≤ 5 dk p95
- Webhook Teslimat Başarı Oranı: ≥ %99.9, p95 gecikme ≤ 60 sn

### Ölçümleme ve Gözlem
- Healthchecks: `/api/health`, `/up` ve Better Uptime/Healthchecks entegrasyonu
- APM/Tracing: Sentry Performance (backend) + yapılandırılmış JSON loglar
- Metrikler: istek sayısı, hata oranı, p95 latency, kuyruk gecikmesi, webhook başarısı

### Bakım Pencereleri
- Planlı bakım: en az 48 saat önce bildirim, gece 02:00–05:00 TR

### İhlal ve Kredi
- Uzlaşı: SLA ihlali durumunda bir sonraki faturalamaya hizmet kredisi (örn. %10–%25 arası, ihlalin seviyesine göre)

### Güvenlik ve KVKK Atfı
- KVKK/Sözleşmeler dokümanına atıf; veri işleme amaçları, saklama süreleri, erişim kontrolleri, ihlal bildirimi prosedürü bu dokümanlarda detaylandırılır.


