## efatura.ai Webhook’lar

### Abonelik
`POST /v1/webhooks/subscriptions`
```json
{ "url": "https://example.com/webhooks/efatura", "secret": "whsec_...", "events": ["invoice.sent", "invoice.failed"] }
```
Yanıt (201):
```json
{ "id": 123, "url": "https://example.com/webhooks/efatura", "secret": "whsec_...", "events": ["invoice.sent", "invoice.failed"] }
```

### Güvenlik ve İmza Doğrulama
- Başlıklar:
  - `X-EF-Event`: olay adı (örn. `invoice.sent`)
  - `X-EF-Timestamp`: Unix zaman damgası (saniye)
  - `X-EF-Signature`: `sha256=<hexdigest>`
- İmza: `HMAC-SHA256(secret, timestamp + "." + rawBody)`
- Doğrulama adımları:
  - `timestamp` tazeyse (≤ 5 dk) kabul edin
  - `sha256=<hexdigest>` ile hesapladığınız imzayı constant‑time karşılaştırın

#### Node.js (Express) örnek
```javascript
import crypto from 'node:crypto'
import express from 'express'

const app = express()
app.use(express.raw({ type: 'application/json' }))

app.post('/webhooks/efatura', (req, res) => {
  const secret = process.env.EFATURA_WEBHOOK_SECRET
  const body = req.body.toString('utf8')
  const timestamp = req.header('X-EF-Timestamp') || ''
  const sigHeader = req.header('X-EF-Signature') || ''
  const expected = 'sha256=' + crypto.createHmac('sha256', secret).update(`${timestamp}.${body}`).digest('hex')
  const valid = crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(sigHeader))
  if (!valid) return res.status(400).send('invalid signature')
  const event = req.header('X-EF-Event')
  const json = JSON.parse(body)
  // handle event/json
  res.sendStatus(200)
})
```

#### PHP (Laravel) örnek
```php
Route::post('/webhooks/efatura', function (\Illuminate\Http\Request $request) {
    $secret = env('EFATURA_WEBHOOK_SECRET');
    $body = $request->getContent();
    $ts = $request->header('X-EF-Timestamp');
    $sig = $request->header('X-EF-Signature');
    $calc = 'sha256=' . hash_hmac('sha256', $ts . '.' . $body, $secret);
    if (! hash_equals($calc, $sig)) {
        return response('invalid signature', 400);
    }
    $event = $request->header('X-EF-Event');
    $payload = json_decode($body, true);
    // handle $event / $payload
    return response('ok', 200);
});
```

### Olay Kapsamı (örnekler)
- `invoice.created` — Fatura kaydınız oluşturuldu (durum: queued)
- `invoice.sent` — Sağlayıcıya iletim başarılı
- `invoice.failed` — İletim veya doğrulama hatası (detaylar payload’da)
- `credit.low` — Kontör bakiyesi eşik altına düştü

### Payload Örneği (`invoice.sent`)
```json
{
  "event": "invoice.sent",
  "data": { "id": 123, "status": "sent" }
}
```

### Yeniden Gönderim ve Backoff
- Manuel: `POST /v1/webhooks/deliveries/{id}/replay`
- Otomatik: 8 denemeye kadar artan gecikme ile tekrar (60s, 120s, 180s ... üst sınır 3600s)
- Kayıt: her denemede `response_status` ve `response_body` saklanır; son durum `pending|delivered|failed`.

