import { Link, Outlet, useLocation } from 'react-router-dom'

export default function Layout() {
	const location = useLocation()
	return (
		<div>
			<header style={{ display: 'flex', gap: 16, alignItems: 'center', padding: 16, borderBottom: '1px solid #eee' }}>
				<strong>efatura.ai Panel</strong>
				<nav style={{ display: 'flex', gap: 12 }}>
					<Link to="/" style={{ fontWeight: location.pathname === '/' ? 'bold' : 'normal' }}>Dashboard</Link>
					<Link to="/invoices" style={{ fontWeight: location.pathname.startsWith('/invoices') || location.pathname.startsWith('/invoice') ? 'bold' : 'normal' }}>Faturalar</Link>
					<Link to="/vouchers" style={{ fontWeight: location.pathname.startsWith('/vouchers') ? 'bold' : 'normal' }}>E‑Makbuz</Link>
					<Link to="/despatches" style={{ fontWeight: location.pathname.startsWith('/despatches') ? 'bold' : 'normal' }}>E‑İrsaliye</Link>
					<Link to="/customers" style={{ fontWeight: location.pathname.startsWith('/customers') ? 'bold' : 'normal' }}>Müşteriler</Link>
					<Link to="/products" style={{ fontWeight: location.pathname.startsWith('/products') ? 'bold' : 'normal' }}>Ürünler</Link>
					<Link to="/credits/wallet" style={{ fontWeight: location.pathname.startsWith('/credits/wallet') ? 'bold' : 'normal' }}>Cüzdan</Link>
					<Link to="/credits/transactions" style={{ fontWeight: location.pathname.startsWith('/credits/transactions') ? 'bold' : 'normal' }}>Hareketler</Link>
					<Link to="/webhooks/subscriptions" style={{ fontWeight: location.pathname.startsWith('/webhooks/subscriptions') ? 'bold' : 'normal' }}>Webhook’lar</Link>
					<Link to="/webhooks/deliveries" style={{ fontWeight: location.pathname.startsWith('/webhooks/deliveries') ? 'bold' : 'normal' }}>Teslimatlar</Link>
				</nav>
				<div style={{ marginLeft: 'auto' }}>
					<input
						placeholder="X-Api-Key"
						defaultValue={localStorage.getItem('apiKey') || ''}
						onBlur={(e) => {
							localStorage.setItem('apiKey', e.target.value)
							window.location.reload()
						}}
						style={{ width: 280 }}
					/>
				</div>
			</header>
			<main>
				<Outlet />
			</main>
		</div>
	)
}


