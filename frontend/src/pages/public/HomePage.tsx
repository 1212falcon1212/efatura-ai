import { Link } from 'react-router-dom'
import logoUrl from '../../assets/logo.svg'
import heroUrl from '../../assets/hero.svg'
import Stats from '../../components/marketing/Stats'
import Testimonials from '../../components/marketing/Testimonials'
import FAQ from '../../components/marketing/FAQ'

export default function HomePage() {
  return (
    <div>
      <section className="hero hero-gradient" style={{ padding: '96px 0 64px', textAlign: 'center' }}>
        <div className="container">
          <span className="hero-badge">E‑dönüşüm çözümleri</span>
          <h1 style={{ marginTop: 12, fontSize: 46 }}>Tek API ile E‑Belge Entegrasyonu</h1>
          <p style={{ maxWidth: 720, margin: '8px auto 0', color: 'var(--muted)' }}>
            E‑Fatura, E‑Arşiv, E‑Makbuz, E‑İrsaliye gönderin. Geliştirici dostu dokümanlar, güvenilir gönderim, zengin webhook’lar ve profesyonel bir panel.
          </p>
          <div className="cta-row" style={{ marginTop: 20 }}>
            <Link to="/signup"><button>Hemen Başla</button></Link>
            <a href="/docs" target="_blank"><button className="btn-secondary">Dokümanı Gör</button></a>
          </div>
          <div className="logo-row" style={{ marginTop: 28 }}>
            <img src={logoUrl} height={22} />
            <img src={logoUrl} height={22} />
            <img src={logoUrl} height={22} />
            <img src={logoUrl} height={22} />
          </div>
        </div>
      </section>

      <section className="features" style={{ padding: '24px 0' }}>
        <div className="container two-col">
          <img src={heroUrl} alt="hero" style={{ width: '100%', borderRadius: 16, border: '1px solid var(--panel-border)' }} />
          <div style={{ display: 'grid', gap: 12 }}>
            <h2 className="section-title" style={{ textAlign: 'left' }}>Zahmetsiz e‑dönüşüm</h2>
            <div className="glass kpi" style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 12 }}>
              <div><div className="kpi-value">99.9%</div><div className="kpi-label">Uptime</div></div>
              <div><div className="kpi-value"><span>24/7</span></div><div className="kpi-label">Destek</div></div>
              <div><div className="kpi-value"><span>+∞</span></div><div className="kpi-label">Ölçek</div></div>
            </div>
            <p style={{ color: 'var(--muted)' }}>Tek API ve modern panel ile e‑belge süreçlerinizi dakikalar içinde hayata geçirin. Güçlü altyapı, şeffaf raporlama ve pratik operasyon.</p>
            <div className="cta-row">
              <Link to="/signup"><button>Ücretsiz Başla</button></Link>
              <a href="/docs" target="_blank"><button className="btn-secondary">API Dokümanı</button></a>
            </div>
          </div>
        </div>
      </section>

      <section id="services" className="features" style={{ padding: '32px 0' }}>
        <div className="container">
          <div className="banner">
            <div>
              <h2 className="section-title" style={{ textAlign: 'left' }}>Hizmetlerimiz</h2>
              <div className="section-sub" style={{ textAlign: 'left' }}>İhtiyacınıza göre modüler, kolay entegre edilebilen ve güvenilir servisler</div>
              <div className="grid" style={{ marginTop: 12 }}>
                <div className="card"><div className="feature-icon">⚙️</div><h3>Gönderim Motoru</h3><p>Asenkron kuyruk, otomatik retry ve idempotent yazma ile kayıpsız işleme.</p></div>
                <div className="card"><div className="feature-icon">🔌</div><h3>Kolay Entegrasyon</h3><p>Basit REST uçları, örnekler ve Postman koleksiyonu ile hızlı kurulum.</p></div>
                <div className="card"><div className="feature-icon">📣</div><h3>Bildirimler</h3><p>Webhook ve panel bildirimleri; imzalı, tekrar oynatılabilir olaylar.</p></div>
                <div className="card"><div className="feature-icon">💳</div><h3>Kontör Yönetimi</h3><p>Bakiye ve işlem geçmişi, düşük bakiye uyarıları ve yükleme seçenekleri.</p></div>
              </div>
            </div>
            <img src={heroUrl} alt="services" style={{ width: '100%', borderRadius: 16, border: '1px solid var(--panel-border)' }} />
          </div>
        </div>
      </section>

      <section className="features" style={{ padding: '24px 0' }}>
        <div className="container">
          <div className="banner">
            <div>
              <h2 className="section-title" style={{ textAlign: 'left' }}>Neden efatura.ai?</h2>
              <div className="grid" style={{ marginTop: 12 }}>
                <div className="card"><div className="feature-icon">🖥️</div><h3>Tek Panel</h3><p>Tüm e‑belgeleri tek yerden yönetin; filtre, sıralama, export.</p></div>
                <div className="card"><div className="feature-icon">🛡️</div><h3>Güvenilir</h3><p>Gözlemlenebilirlik, hata yönetimi ve tekrar deneme stratejileri.</p></div>
                <div className="card"><div className="feature-icon">📈</div><h3>Ölçeklenebilir</h3><p>Büyüyen hacimlere uygun, yatayda genişleyebilir mimari.</p></div>
                <div className="card"><div className="feature-icon">🤝</div><h3>Destek</h3><p>Kurulum ve canlı kullanımda hızlı teknik destek.</p></div>
              </div>
            </div>
            <img src={heroUrl} alt="why" style={{ width: '100%', borderRadius: 16, border: '1px solid var(--panel-border)' }} />
          </div>
        </div>
      </section>

      {/* SEO odaklı banner: Kullanım senaryoları */}
      <section className="features" style={{ padding: '32px 0' }}>
        <div className="container">
          <h2 className="section-title" style={{ textAlign:'left' }}>Kullanım Senaryoları</h2>
          <div className="grid" style={{ marginTop: 12 }}>
            <div className="card">
              <h3>E‑Fatura Entegrasyonu</h3>
              <p>Mevcut ERP/Ön muhasebe sisteminize tek API ile e‑Fatura gönderimi ve gelen kutusu yönetimi ekleyin.</p>
            </div>
            <div className="card">
              <h3>Otomatik Bildirim</h3>
              <p>Webhook’larla durum güncellemelerini alın, başarısız iletimlerde yeniden deneme ve imzalı doğrulama kullanın.</p>
            </div>
            <div className="card">
              <h3>Güvenli Ödeme ve Kontör</h3>
              <p>Moka POS ile güvenli tahsilat, otomatik kontör satın alma ve havuz yönetimi.</p>
            </div>
            <div className="card">
              <h3>Raporlama ve İzleme</h3>
              <p>Gelişmiş loglama, uyarılar ve SLA/SLO ile operasyonel görünürlük.</p>
            </div>
          </div>
        </div>
      </section>

      <Stats />
      <Testimonials />

      <section className="pricing" style={{ padding: '32px 0' }}>
        <div className="container">
          <h2 className="section-title">Fiyatlandırma</h2>
          <div className="grid" style={{ marginTop: 12 }}>
            <div className="card pricing-card" style={{ gridColumn: 'span 3' }}>
              <h3>Tek Plan</h3>
              <div className="price">590₺/ay</div>
              <p>Tüm özellikler: e‑belgeler, webhook’lar, panel, raporlar. Ücretsiz sürüm yoktur.</p>
              <Link to="/signup"><button>Kayıt Ol</button></Link>
            </div>
          </div>
        </div>
      </section>

      <FAQ />

      <section className="features" style={{ padding: '24px 0' }}>
        <div className="container cta-wide">
          <div>
            <div style={{ fontWeight: 800, fontSize: 20 }}>Hemen başlayın</div>
            <div style={{ color: 'var(--muted)', marginTop: 4 }}>5 dakikada API anahtarınızı alın, ilk e‑belgenizi gönderin.</div>
          </div>
          <div className="cta-row" style={{ justifyContent: 'flex-end' }}>
            <Link to="/signup"><button>Kayıt Ol</button></Link>
            <a href="/docs" target="_blank"><button className="btn-secondary">Doküman</button></a>
          </div>
        </div>
      </section>

      <footer style={{ padding: 24, textAlign: 'center', color: '#64748b' }}>© {new Date().getFullYear()} efatura.ai — Tüm hakları saklıdır.</footer>

      {/* Structured Data (JSON-LD) */}
      <script type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify({
          "@context": "https://schema.org",
          "@type": "Organization",
          "name": "efatura.ai",
          "url": typeof window !== 'undefined' ? window.location.origin : 'https://efatura.ai',
          "logo": typeof window !== 'undefined' ? (new URL('/logo.svg', window.location.origin)).toString() : '/logo.svg'
        }) }} />
      <script type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify({
          "@context": "https://schema.org",
          "@type": "WebSite",
          "url": typeof window !== 'undefined' ? window.location.origin : 'https://efatura.ai',
          "potentialAction": {
            "@type": "SearchAction",
            "target": (typeof window !== 'undefined' ? window.location.origin : 'https://efatura.ai') + "/search?q={search_term_string}",
            "query-input": "required name=search_term_string"
          }
        }) }} />
      <script type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify({
          "@context": "https://schema.org",
          "@type": "SoftwareApplication",
          "name": "efatura.ai",
          "applicationCategory": "BusinessApplication",
          "operatingSystem": "Web",
          "offers": { "@type": "Offer", "price": "590", "priceCurrency": "TRY" }
        }) }} />
      <script type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify({
          "@context": "https://schema.org",
          "@type": "FAQPage",
          "mainEntity": [
            { "@type": "Question", "name": "efatura.ai nedir?", "acceptedAnswer": { "@type": "Answer", "text": "E‑Fatura, E‑Arşiv, E‑İrsaliye gibi e‑belgeleri tek API ve panel ile yönetmenizi sağlayan bir platformdur." }},
            { "@type": "Question", "name": "Entegrasyon süresi ne kadar?", "acceptedAnswer": { "@type": "Answer", "text": "Doküman ve örneklerle dakikalar içinde ilk belgenizi gönderebilirsiniz." }},
            { "@type": "Question", "name": "Ödemeler güvenli mi?", "acceptedAnswer": { "@type": "Answer", "text": "Moka POS ve 3D Secure seçenekleriyle güvenli ödeme altyapısı sunuyoruz." }}
          ]
        }) }} />
    </div>
  )
}


