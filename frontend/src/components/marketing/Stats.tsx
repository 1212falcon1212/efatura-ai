export default function Stats() {
  return (
    <section className="features" style={{ padding: '24px 0' }}>
      <div className="container">
        <div className="glass kpi" style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 12 }}>
          <div>
            <div className="kpi-value">99.9%</div>
            <div className="kpi-label">Uptime</div>
          </div>
          <div>
            <div className="kpi-value"><span>24/7</span></div>
            <div className="kpi-label">Destek</div>
          </div>
          <div>
            <div className="kpi-value"><span>ms</span></div>
            <div className="kpi-label">API Gecikme</div>
          </div>
          <div>
            <div className="kpi-value">+∞</div>
            <div className="kpi-label">Ölçek</div>
          </div>
        </div>
      </div>
    </section>
  )
}


