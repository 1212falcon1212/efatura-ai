import * as React from 'react'
import { useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'
import PageHeader from '../components/ui/PageHeader'
import Skeleton from '../components/ui/Skeleton'
import { IconChart, IconMail, IconCard } from '../components/ui/Icons'

const Sparkline = React.lazy(() => import('../components/charts/Sparkline'))

async function fetchInvoices() {
	const res = await api.get('/invoices')
	return res.data?.data ?? []
}

async function fetchWallet() {
  const res = await api.get('/credits/wallet')
  return res.data
}

async function fetchProviderBalance() {
  const res = await api.get('/credits/provider-balance')
  return res.data
}

async function fetchFailedWebhookCount() {
  const res = await api.get('/webhooks/deliveries', { params: { status: 'failed', per_page: 1 } })
  return res.data?.meta?.total ?? 0
}

export default function DashboardPage() {
	const { data: invoices, isLoading, isError } = useQuery({ queryKey: ['dash_invoices'], queryFn: fetchInvoices })
  const { data: wallet } = useQuery({ queryKey: ['dash_wallet'], queryFn: fetchWallet })
  const { data: provider } = useQuery({ queryKey: ['dash_provider'], queryFn: fetchProviderBalance })
  const { data: failedWh } = useQuery({ queryKey: ['dash_webhook_failed'], queryFn: fetchFailedWebhookCount })
	const today = new Date().toISOString().slice(0, 10)
	const sentToday = (invoices || []).filter((i: any) => i.status === 'sent' && (i.issueDate || '').startsWith(today)).length
	const failed = (invoices || []).filter((i: any) => i.status === 'failed').length
	const series = (invoices || [])
		.slice(-20)
		.map((i: any, idx: number) => ({ idx, total: Number(i?.totals?.grandTotal?.amount || 0), status: i.status }))
	return (
		<div>
			<PageHeader title="Dashboard" subtitle="Genel bakış" crumbs={[{ label: 'Panel', href: '/app' }, { label: 'Dashboard' }]} />
			{isLoading && (
				<div style={{ display: 'flex', gap: 16 }}>
					<Skeleton height={90} />
					<Skeleton height={90} />
				</div>
			)}
			{isError && <div>Hata oluştu</div>}
			{!isLoading && !isError && (
				<div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 16 }}>
					<div className="card" style={{ display: 'grid', gap: 8, borderTop: '4px solid #0ea5e9' }}>
						<div style={{ display:'flex', alignItems:'center', gap:8 }}>
							<span style={{ width:28, height:28, borderRadius:8, background:'rgba(14,165,233,.12)', color:'#0ea5e9', display:'inline-grid', placeItems:'center' }}><IconChart /></span>
							<div style={{ fontWeight: 600 }}>Bugün Gönderilen</div>
						</div>
						<div style={{ display:'flex', alignItems:'baseline', gap:8 }}>
							<div style={{ fontSize: 28, fontWeight: 800 }}>{sentToday}</div>
							<span className="badge" style={{ background:'rgba(34,197,94,.12)', borderColor:'rgba(34,197,94,.25)', color:'#166534' }}>+2.1%</span>
						</div>
						<div style={{ height: 80 }}>
							<React.Suspense fallback={<Skeleton height={80} />}>
								<Sparkline data={series} color="#0ea5e9" />
							</React.Suspense>
						</div>
					</div>
					<div className="card" style={{ display: 'grid', gap: 8, borderTop: '4px solid #ef4444' }}>
						<div style={{ display:'flex', alignItems:'center', gap:8 }}>
							<span style={{ width:28, height:28, borderRadius:8, background:'rgba(239,68,68,.10)', color:'#ef4444', display:'inline-grid', placeItems:'center' }}><IconMail /></span>
							<div style={{ fontWeight: 600 }}>Başarısız</div>
						</div>
						<div style={{ display:'flex', alignItems:'baseline', gap:8 }}>
							<div style={{ fontSize: 28, fontWeight: 800 }}>{failed}</div>
							<span className="badge" style={{ background:'rgba(239,68,68,.12)', borderColor:'rgba(239,68,68,.25)', color:'#991b1b' }}>-1.4%</span>
						</div>
						<div style={{ height: 80 }}>
							<React.Suspense fallback={<Skeleton height={80} />}>
								<Sparkline data={series} color="#ef4444" />
							</React.Suspense>
						</div>
					</div>
				  <div className="card" style={{ display: 'grid', gap: 8, borderTop: '4px solid #22c55e' }}>
						<div style={{ display:'flex', alignItems:'center', gap:8 }}>
							<span style={{ width:28, height:28, borderRadius:8, background:'rgba(34,197,94,.10)', color:'#16a34a', display:'inline-grid', placeItems:'center' }}><IconCard /></span>
							<div style={{ fontWeight: 600 }}>Cüzdan Bakiyesi</div>
						</div>
				    <div style={{ fontSize: 28, fontWeight: 800 }}>{Number(wallet?.balance ?? 0).toFixed(2)} {wallet?.currency || 'TRY'}</div>
				    <div style={{ color: '#64748b' }}>Son kullanım: {wallet?.updated_at ? new Date(wallet.updated_at).toLocaleString() : '-'}</div>
				  </div>
				  <div className="card" style={{ display: 'grid', gap: 8, borderTop: '4px solid #f59e0b' }}>
						<div style={{ display:'flex', alignItems:'center', gap:8 }}>
							<span style={{ width:28, height:28, borderRadius:8, background:'rgba(245,158,11,.12)', color:'#b45309', display:'inline-grid', placeItems:'center' }}><IconMail /></span>
							<div style={{ fontWeight: 600 }}>Webhook Hata</div>
						</div>
				    <div style={{ fontSize: 28, fontWeight: 800 }}>{failedWh ?? 0}</div>
				    <div style={{ color: '#64748b' }}>Sağlayıcı Bakiye: {provider?.balance ?? '-'}{provider?.unit ? ` ${provider.unit}` : ''}</div>
				  </div>
				</div>
			)}
		</div>
	)
}


