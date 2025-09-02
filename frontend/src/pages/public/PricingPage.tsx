import { Link } from 'react-router-dom'

export default function PricingPage() {
  return (
    <div className="container" style={{ padding: '64px 0' }}>
      <div className="card pricing-card" style={{ maxWidth: 720, margin: '0 auto' }}>
        <h2 style={{ marginTop: 0 }}>Tek Plan</h2>
        <div className="price">590₺/ay</div>
        <ul>
          <li>E‑Fatura, E‑Arşiv, E‑Makbuz, E‑İrsaliye</li>
          <li>Webhook’lar ve raporlar</li>
          <li>Profesyonel panel ve API desteği</li>
        </ul>
        <Link to="/signup"><button>Kayıt Ol</button></Link>
      </div>
    </div>
  )
}


