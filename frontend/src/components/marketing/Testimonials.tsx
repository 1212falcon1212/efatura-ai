export default function Testimonials() {
  return (
    <section className="features" style={{ padding: '32px 0' }}>
      <div className="container">
        <h2 className="section-title">Müşterilerimiz ne diyor?</h2>
        <div className="grid" style={{ marginTop: 12 }}>
          <div className="card glass"><p>“Entegrasyon 1 günde tamamlandı. Panel sayesinde operasyon çok rahat.”</p><div style={{ color: 'var(--muted)', marginTop: 8 }}>— Lojistik SaaS</div></div>
          <div className="card glass"><p>“Webhook ve raporlar sayesinde kullanıcı destek süreçlerimiz hızlandı.”</p><div style={{ color: 'var(--muted)', marginTop: 8 }}>— E‑ticaret</div></div>
          <div className="card glass"><p>“Idempotent yazma ve kuyruk mimarisi ile güvenimiz tam.”</p><div style={{ color: 'var(--muted)', marginTop: 8 }}>— Fintech</div></div>
        </div>
      </div>
    </section>
  )
}


