import { Link, Outlet, useLocation } from 'react-router-dom'
import { useState } from 'react'
import logoUrl from '../assets/logo.svg'

export default function PublicLayout() {
  const [open, setOpen] = useState(false)
  const location = useLocation()
  function close() { setOpen(false) }
  return (
    <div>
      <header className="site-header">
        <div className="container" style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
          <Link to="/" className="brand" onClick={close}>
            <img src={logoUrl} alt="efatura.ai" height={24} />
            <span className="brand-text">efatura.ai</span>
          </Link>
          <nav className="site-nav">
            <Link to="/" className={location.pathname === '/' ? 'active' : ''} onClick={close}>Ana Sayfa</Link>
            <a href="#services" onClick={close}>Hizmetler</a>
            <Link to="/pricing" onClick={close}>Fiyatlandırma</Link>
            <a href="/docs" target="_blank" rel="noreferrer">Doküman</a>
          </nav>
          <div className="site-actions">
            <Link to="/login" onClick={close}><button className="btn-secondary">Giriş</button></Link>
            <Link to="/signup" onClick={close}><button>Kayıt</button></Link>
          </div>
          <button className="site-burger" aria-label="Menü" onClick={() => setOpen(v => !v)}>☰</button>
        </div>
        {open && (
          <div className="site-mobile-menu" onClick={close}>
            <div className="site-mobile-panel" onClick={(e) => e.stopPropagation()}>
              <nav className="site-mobile-nav">
                <Link to="/" onClick={close}>Ana Sayfa</Link>
                <a href="#services" onClick={close}>Hizmetler</a>
                <Link to="/pricing" onClick={close}>Fiyatlandırma</Link>
                <a href="/docs" target="_blank" rel="noreferrer">Doküman</a>
              </nav>
              <div className="site-mobile-actions">
                <Link to="/login" onClick={close}><button className="btn-secondary" style={{ width: '100%' }}>Giriş</button></Link>
                <Link to="/signup" onClick={close}><button style={{ width: '100%' }}>Kayıt</button></Link>
              </div>
            </div>
          </div>
        )}
      </header>
      <main>
        <Outlet />
      </main>
    </div>
  )
}


