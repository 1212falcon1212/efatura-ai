export default function Footer() {
  return (
    <footer style={{ padding: 32, borderTop: '1px solid var(--panel-border)', marginTop: 24 }}>
      <div className="container" style={{ display: 'grid', gap: 16, gridTemplateColumns: '2fr 1fr 1fr 1fr' }}>
        <div>
          <div style={{ fontWeight: 800, fontSize: 18 }}>efatura.ai</div>
          <div style={{ color: 'var(--muted)', marginTop: 6 }}>Tek API, profesyonel panel.</div>
        </div>
        <div>
          <div style={{ fontWeight: 600, marginBottom: 8 }}>Ürün</div>
          <a href="#services">Hizmetler</a><br/>
          <a href="/docs" target="_blank" rel="noreferrer">Doküman</a>
        </div>
        <div>
          <div style={{ fontWeight: 600, marginBottom: 8 }}>Şirket</div>
          <a href="/pricing">Fiyatlandırma</a><br/>
          <a href="#">Hakkımızda</a>
        </div>
        <div>
          <div style={{ fontWeight: 600, marginBottom: 8 }}>İletişim</div>
          <a href="mailto:hello@efatura.ai">hello@efatura.ai</a>
        </div>
      </div>
      <div className="container" style={{ color: 'var(--muted)', textAlign: 'center', marginTop: 16 }}>© {new Date().getFullYear()} efatura.ai</div>
    </footer>
  )
}


