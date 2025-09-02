## efatura.ai API Kılavuzu (v1)

Bu belge, üçüncü parti entegratörlerin efatura.ai REST API’sini güvenli ve tutarlı şekilde kullanması için önerileri ve sözleşmeleri içerir.

### Kimlik Doğrulama
- Tüm istekler `X-Api-Key: <anahtar>` başlığıyla yapılır (organizasyon bazlı anahtar).
- Geliştirme için sandbox anahtarları `test_` önekiyle verilir.

### Versiyonlama
- URL tabanlı versiyonlama kullanılır: `/v1/...`.
- Geriye dönük uyumsuz değişikliklerde yeni ana sürüm (`/v2`) yayımlanır; en az 6 ay birlikte yaşatma/deprecation süresi.

### Idempotency
- Tüm yazma işlemleri (POST/PUT/PATCH/DELETE) `X-Idempotency-Key` gerektirir.
- Aynı anahtarla 24 saat içinde gelen tekrar istekler aynı sonucu döner.

### Oran Sınırı (Rate Limit)
- Varsayılan: 300 istek/dk/organizasyon (değişebilir planlara göre).
- Yanıt başlıkları: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`.

### Sayfalama
- Cursor tabanlı sayfalama: `page[after]` ve `page[limit]`.
- Sonraki sayfa için `X-Next-Cursor` başlığı döner.

### Hata Modeli
```json
{
  "code": "invalid_request",
  "message": "Missing required field: customer.vkn",
  "requestId": "req_01HV2...",
  "details": { "field": "customer.vkn" }
}
```
- Yaygın kodlar: `unauthorized`, `forbidden`, `not_found`, `conflict`, `rate_limited`, `invalid_request`, `internal_error`.

### İzlenebilirlik
- Tüm yanıtlarda `X-Request-Id` döner. Destek taleplerinde bu kimliği paylaşın.

### Yeniden Deneme Politikası
- 429/5xx için üstel geri çekilme (exponential backoff) + jitter kullanın. 5 denemeyi aşmayın.

### Webhook Güvenliği
- Gönderiler `X-Signature: t=timestamp, v1=hexdigest` başlığı taşır.
- İmza, `HMAC-SHA256(secret, timestamp + '.' + body)` ile üretilir.
- 5 dakikadan eski timestamp’leri reddedin. Tekrar saldırılarına karşı nonce/Idempotency uygulayın.

### Test / Sandbox
- Sandbox ortamında sağlayıcı simülatörüyle sahte durum geçişleri yapılabilir.
- Test anahtarları ve uçları production’dan yalıtılmıştır.

### SDK’lar ve Koleksiyonlar
- Resmi SDK planı: Node.js, PHP, Python.
- Postman/Insomnia koleksiyonları ve ortam değişkenleri sağlanır.

### Deprecation Politikası
- Değişiklikler `Deprecation` ve `Sunset` başlıkları ile ve değişiklik günlüğünde duyurulur.

### Olay Türleri (Örnek)
- `invoice.created`, `invoice.sent`, `invoice.failed`, `invoice.canceled`
- `credit.debited`, `credit.top_up`, `credit.low`
- `subscription.renewed`, `subscription.canceled`

### Örnek Akış: Fatura Oluşturma
1) `POST /v1/invoices` (idempotent)
2) `202 Accepted` → durum `queued`
3) Durum `sent/failed` olduğunda webhook tetiklenir; veya `GET /v1/invoices/{id}` ile sorgulanır.

