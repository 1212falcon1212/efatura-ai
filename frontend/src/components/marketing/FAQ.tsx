export default function FAQ() {
  const qs: Array<{ q: string; a: string }> = [
    { q: 'Hangi e‑belgeleri destekliyorsunuz?', a: 'E‑Fatura, E‑Arşiv, E‑Makbuz ve E‑İrsaliye gönderimi ve durum takibi.' },
    { q: 'Kurulum ne kadar sürer?', a: 'API anahtarınızı alıp örnek istekle dakikalar içinde canlı test yapabilirsiniz.' },
    { q: 'Webhook güvenliği nasıl sağlanır?', a: 'Gönderimler HMAC‑SHA256 imzasıyla doğrulanır, olaylar tekrar oynatılabilir.' },
    { q: 'Faturalama nasıl işler?', a: 'Aylık 590 TL sabit ücret. Kullanımınıza göre kontör cüzdanı ayrıca yönetilebilir.' },
  ]
  return (
    <section className="features" style={{ padding: '32px 0' }}>
      <div className="container">
        <h2 className="section-title">Sık Sorulan Sorular</h2>
        <div className="faq-grid">
          {qs.map((item, i) => (
            <details key={i} className="faq-item">
              <summary>{item.q}</summary>
              <div className="faq-body">{item.a}</div>
            </details>
          ))}
        </div>
      </div>
    </section>
  )
}


